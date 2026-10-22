from __future__ import annotations

import argparse
import asyncio
import base64
import hashlib
import hmac
import json
import logging
import os
import re
import ssl
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from vosk import KaldiRecognizer, Model, SetLogLevel
from websockets.asyncio.server import ServerConnection, serve
from websockets.exceptions import ConnectionClosed


SAMPLE_RATE = 16000
MAX_FRAME_BYTES = 12800
MAX_STREAM_SECONDS = 120
MAX_ACTIVE_CONNECTIONS = 4
MAX_TICKET_TTL_SECONDS = 45
DIAGNOSTIC_TICKET_TTL_SECONDS = 30
DIAGNOSTIC_ORIGIN = "https://clinica.test:8443"
TICKET_AUDIENCE = "clinica-vosk-local-v1"
ALLOWED_ORIGINS = {"https://clinica.test", "https://clinica.test:8443"}
STABLE_ID_RE = re.compile(r"^M(?:02|03|04|05|06|08|09)(?::[A-Za-z0-9_-]+){1,4}$")


def _b64url_decode(value: str) -> bytes:
    if not value or not re.fullmatch(r"[A-Za-z0-9_-]+", value):
        raise ValueError("TICKET_INVALID")
    return base64.urlsafe_b64decode(value + "=" * (-len(value) % 4))


def diagnostic_session_matches(claims: dict[str, Any], cookie_header: str, secret: bytes) -> bool:
    match = re.search(r"(?:^|;\s*)CLINICAJWT=([A-Za-z0-9._~-]+)(?:;|$)", cookie_header)
    if not match:
        return False
    expected = hmac.new(secret, match.group(1).encode("utf-8"), hashlib.sha256).hexdigest()
    supplied = claims.get("sid") if isinstance(claims, dict) else None
    return isinstance(supplied, str) and hmac.compare_digest(expected, supplied)


@dataclass
class TicketReplayGuard:
    consumed: dict[str, int]

    @classmethod
    def create(cls) -> "TicketReplayGuard":
        return cls(consumed={})

    def verify_and_consume(self, token: str, secret: bytes, expected_origin: str, now: int | None = None) -> dict[str, Any]:
        now = int(time.time()) if now is None else int(now)
        if not isinstance(token, str) or len(token) > 4096 or token.count(".") != 1:
            raise ValueError("TICKET_INVALID")
        body, supplied_signature = token.split(".", 1)
        signature = _b64url_decode(supplied_signature)
        expected_signature = hmac.new(secret, body.encode("ascii"), hashlib.sha256).digest()
        if not hmac.compare_digest(signature, expected_signature):
            raise ValueError("TICKET_INVALID")
        try:
            claims = json.loads(_b64url_decode(body).decode("utf-8"))
        except (UnicodeDecodeError, json.JSONDecodeError) as error:
            raise ValueError("TICKET_INVALID") from error
        if not isinstance(claims, dict):
            raise ValueError("TICKET_INVALID")
        issued = claims.get("iat")
        expires = claims.get("exp")
        patient_id = claims.get("pid")
        stable_id = claims.get("question")
        nonce = claims.get("jti")
        session_fingerprint = claims.get("sid")
        if (
            claims.get("v") != 1
            or claims.get("aud") != TICKET_AUDIENCE
            or claims.get("origin") != expected_origin
            or not isinstance(issued, int)
            or not isinstance(expires, int)
            or issued > now + 5
            or expires <= now
            or expires - issued > MAX_TICKET_TTL_SECONDS
            or not isinstance(patient_id, int)
            or patient_id < 1
            or not isinstance(stable_id, str)
            or not STABLE_ID_RE.fullmatch(stable_id)
            or not isinstance(nonce, str)
            or not re.fullmatch(r"[a-f0-9]{32}", nonce)
            or not isinstance(session_fingerprint, str)
            or not re.fullmatch(r"[a-f0-9]{64}", session_fingerprint)
        ):
            raise ValueError("TICKET_INVALID")
        self.consumed = {key: ttl for key, ttl in self.consumed.items() if ttl > now}
        if nonce in self.consumed:
            raise ValueError("TICKET_REPLAYED")
        self.consumed[nonce] = expires
        return claims

    def verify_diagnostic_and_consume(self, token: str, secret: bytes, expected_origin: str, now: int | None = None) -> dict[str, Any]:
        now = int(time.time()) if now is None else int(now)
        if not isinstance(token, str) or len(token) > 4096 or token.count(".") != 1 or expected_origin != DIAGNOSTIC_ORIGIN:
            raise ValueError("DIAGNOSTIC_TICKET_INVALID")
        body, supplied_signature = token.split(".", 1)
        signature = _b64url_decode(supplied_signature)
        expected_signature = hmac.new(secret, body.encode("ascii"), hashlib.sha256).digest()
        if not hmac.compare_digest(signature, expected_signature):
            raise ValueError("DIAGNOSTIC_TICKET_INVALID")
        try:
            claims = json.loads(_b64url_decode(body).decode("utf-8"))
        except (UnicodeDecodeError, json.JSONDecodeError) as error:
            raise ValueError("DIAGNOSTIC_TICKET_INVALID") from error
        expected_keys = {"v", "aud", "purpose", "sid", "origin", "iat", "exp", "jti"}
        issued = claims.get("iat") if isinstance(claims, dict) else None
        expires = claims.get("exp") if isinstance(claims, dict) else None
        nonce = claims.get("jti") if isinstance(claims, dict) else None
        if (
            not isinstance(claims, dict) or set(claims) != expected_keys
            or type(claims.get("v")) is not int or claims.get("v") != 1
            or claims.get("aud") != "clinica-vosk-diagnostic-v1"
            or claims.get("purpose") != "local_voice_diagnostic"
            or claims.get("origin") != expected_origin
            or type(issued) is not int or type(expires) is not int
            or issued > now + 5 or expires <= now or expires <= issued or expires - issued > DIAGNOSTIC_TICKET_TTL_SECONDS
            or not isinstance(nonce, str) or not re.fullmatch(r"[a-f0-9]{32}", nonce)
            or not isinstance(claims.get("sid"), str) or not re.fullmatch(r"[a-f0-9]{64}", claims["sid"])
        ):
            raise ValueError("DIAGNOSTIC_TICKET_INVALID")
        self.consumed = {key: ttl for key, ttl in self.consumed.items() if ttl > now}
        if nonce in self.consumed:
            raise ValueError("TICKET_REPLAYED")
        self.consumed[nonce] = expires
        return claims


class VoskGateway:
    def __init__(self, model: Model, ticket_secret: bytes, expected_origin: str, diagnostic_only: bool = False) -> None:
        self.model = model
        self.ticket_secret = ticket_secret
        self.expected_origin = expected_origin
        self.diagnostic_only = diagnostic_only
        self.replay_guard = TicketReplayGuard.create()
        self.active_connections = 0
        self.stream_semaphore = asyncio.Semaphore(1)

    def route_allowed(self, path: str) -> bool:
        if path not in {"/dictation", "/diagnostic"}:
            return False
        if self.diagnostic_only and path != "/diagnostic":
            return False
        return path != "/diagnostic" or self.expected_origin == DIAGNOSTIC_ORIGIN

    async def handle(self, websocket: ServerConnection) -> None:
        if self.active_connections >= MAX_ACTIVE_CONNECTIONS:
            await websocket.close(code=1013, reason="Servicio ocupado")
            return
        self.active_connections += 1
        try:
            path = websocket.request.path
            if not self.route_allowed(path):
                await websocket.close(code=1008, reason="Ruta no autorizada")
                return
            headers = websocket.request.headers
            diagnostic = path == "/diagnostic"
            expected_origin = DIAGNOSTIC_ORIGIN if diagnostic else self.expected_origin
            if headers.get("Origin", "") != expected_origin:
                await websocket.close(code=1008, reason="Origen no autorizado")
                return
            try:
                first = await asyncio.wait_for(websocket.recv(), timeout=5)
                if not isinstance(first, str) or len(first) > 4096:
                    raise ValueError("AUTH_REQUIRED")
                auth_message = json.loads(first)
                if not isinstance(auth_message, dict) or set(auth_message) != {"type", "ticket"} or auth_message.get("type") != "AUTH":
                    raise ValueError("AUTH_REQUIRED")
                if diagnostic:
                    claims = self.replay_guard.verify_diagnostic_and_consume(auth_message.get("ticket"), self.ticket_secret, expected_origin)
                    if not diagnostic_session_matches(claims, headers.get("Cookie", ""), self.ticket_secret):
                        raise ValueError("DIAGNOSTIC_SESSION_MISMATCH")
                else:
                    claims = self.replay_guard.verify_and_consume(auth_message.get("ticket"), self.ticket_secret, expected_origin)
            except (asyncio.TimeoutError, json.JSONDecodeError, TypeError, ValueError):
                await websocket.send(json.dumps({"type": "ERROR", "code": "AUTH_REJECTED"}))
                await websocket.close(code=1008, reason="Autorización requerida")
                return
            if self.stream_semaphore.locked():
                await websocket.send(json.dumps({"type": "ERROR", "code": "DICTATION_BUSY"}))
                await websocket.close(code=1013, reason="Servicio ocupado")
                return

            async with self.stream_semaphore:
                await websocket.send(json.dumps({"type": "READY", "sample_rate": SAMPLE_RATE}, separators=(",", ":")))
                recognizer = KaldiRecognizer(self.model, SAMPLE_RATE)
                recognizer.SetWords(False)
                total_samples = 0
                last_partial = ""
                async for frame in websocket:
                    if isinstance(frame, str):
                        try:
                            command = json.loads(frame)
                        except json.JSONDecodeError:
                            await websocket.close(code=1003, reason="Mensaje no válido")
                            return
                        if command == {"type": "STOP"}:
                            result = self._recognized_json(recognizer.FinalResult())
                            if result:
                                await websocket.send(json.dumps({"type": "FINAL", "text": result}, ensure_ascii=False, separators=(",", ":")))
                            await websocket.send(json.dumps({"type": "DONE"}, separators=(",", ":")))
                            await websocket.close(code=1000, reason="Dictado finalizado")
                            return
                        await websocket.close(code=1003, reason="Comando no permitido")
                        return
                    if not isinstance(frame, bytes) or not frame or len(frame) > MAX_FRAME_BYTES or len(frame) % 2:
                        await websocket.close(code=1009, reason="Fragmento de audio no válido")
                        return
                    total_samples += len(frame) // 2
                    if total_samples > SAMPLE_RATE * MAX_STREAM_SECONDS:
                        await websocket.close(code=1008, reason="Duración máxima excedida")
                        return
                    accepted = await asyncio.to_thread(recognizer.AcceptWaveform, frame)
                    if accepted:
                        text = self._recognized_json(recognizer.Result())
                        last_partial = ""
                        if text:
                            await websocket.send(json.dumps({"type": "FINAL", "text": text}, ensure_ascii=False, separators=(",", ":")))
                    else:
                        partial = self._recognized_json(recognizer.PartialResult(), key="partial")
                        if partial and partial != last_partial:
                            last_partial = partial
                            await websocket.send(json.dumps({"type": "PARTIAL", "text": partial}, ensure_ascii=False, separators=(",", ":")))
        except ConnectionClosed:
            return
        except Exception:
            # Do not log audio, recognized text, tickets, identities, or payloads.
            try:
                await websocket.send(json.dumps({"type": "ERROR", "code": "TRANSCRIPTION_FAILED"}))
            except Exception:
                pass
        finally:
            self.active_connections = max(0, self.active_connections - 1)

    @staticmethod
    def _recognized_json(payload: str, key: str = "text") -> str:
        try:
            result = json.loads(payload)
        except (TypeError, json.JSONDecodeError):
            return ""
        value = result.get(key, "") if isinstance(result, dict) else ""
        return value.strip() if isinstance(value, str) else ""


async def run(args: argparse.Namespace) -> None:
    model_path = Path(args.model).resolve(strict=True)
    key_path = Path(args.ticket_key).resolve(strict=True)
    cert_path = Path(args.cert).resolve(strict=True)
    private_key_path = Path(args.private_key).resolve(strict=True)
    secret = key_path.read_bytes()
    if len(secret) != 32:
        raise SystemExit("ticket-key-invalid")
    if args.host != "127.0.0.1":
        raise SystemExit("gateway-loopback-only")
    if args.origin not in ALLOWED_ORIGINS:
        raise SystemExit("origin-not-allowlisted")
    SetLogLevel(-1)
    model = Model(str(model_path))
    gateway = VoskGateway(model, secret, args.origin, diagnostic_only=args.diagnostic_only)
    tls = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    tls.minimum_version = ssl.TLSVersion.TLSv1_2
    tls.load_cert_chain(certfile=str(cert_path), keyfile=str(private_key_path))
    async with serve(
        gateway.handle,
        args.host,
        args.port,
        ssl=tls,
        origins=[args.origin],
        max_size=MAX_FRAME_BYTES,
        max_queue=4,
        write_limit=32768,
        open_timeout=5,
        ping_interval=20,
        ping_timeout=20,
        compression=None,
        server_header=None,
    ):
        print("VOSK_GATEWAY_READY loopback-only tls origin-allowlisted", flush=True)
        if args.shutdown_file:
            shutdown_path = Path(args.shutdown_file).resolve(strict=False)
            while not shutdown_path.exists():
                await asyncio.sleep(0.2)
        else:
            await asyncio.Future()


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Loopback-only authenticated local Vosk WebSocket gateway")
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=8766)
    parser.add_argument("--origin", required=True)
    parser.add_argument("--model", required=True)
    parser.add_argument("--ticket-key", required=True)
    parser.add_argument("--cert", required=True)
    parser.add_argument("--private-key", required=True)
    parser.add_argument("--diagnostic-only", action="store_true")
    parser.add_argument("--shutdown-file", default="")
    return parser.parse_args()


if __name__ == "__main__":
    logging.disable(logging.CRITICAL)
    os.environ.setdefault("PYTHONUTF8", "1")
    asyncio.run(run(parse_args()))
