from __future__ import annotations

print("STAGE=SCRIPT_STARTED", flush=True)

import asyncio
from array import array
import base64
import hashlib
import hmac
import json
import ssl
import struct
import sys
import time
import uuid
import wave
from pathlib import Path

import websockets
from vosk import Model, SetLogLevel
from websockets.asyncio.server import serve

from vosk_gateway import VoskGateway

print("STAGE=IMPORTS_READY", flush=True)

async def main() -> None:
    print("STAGE=MAIN_ENTERED", flush=True)
    user_data = Path.home() / "AppData" / "Local" / "ClinicaDolor" / "vosk-realtime"
    model_path = user_data / "models" / "vosk-model-small-es-0.42"
    key_path = user_data / "ticket.key"
    cert_path = user_data / "tls" / "clinica-test-local.pem"
    private_key_path = user_data / "tls" / "clinica-test-local-key.pem"
    fixture = Path(__file__).resolve().parents[1] / "voice-stt-01" / "audio" / "audio-03s.wav"
    expected_text = "No fumo y no tomo medicamentos."
    origin = "https://clinica.test:8443"
    secret = key_path.read_bytes()
    print("STAGE=KEY_READ", flush=True)
    if len(secret) != 32:
        raise RuntimeError("TEST_KEY_INVALID")
    SetLogLevel(-1)
    print("STAGE=MODEL_LOAD", flush=True)
    model = Model(str(model_path))
    print("STAGE=MODEL_READY", flush=True)
    gateway = VoskGateway(model, secret, origin)
    server_tls = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    server_tls.minimum_version = ssl.TLSVersion.TLSv1_2
    server_tls.load_cert_chain(str(cert_path), str(private_key_path))
    client_tls = ssl.create_default_context(cafile=str(cert_path))
    client_tls.minimum_version = ssl.TLSVersion.TLSv1_2

    with wave.open(str(fixture), "rb") as source:
        if source.getnchannels() != 1 or source.getsampwidth() != 2:
            raise RuntimeError("SYNTHETIC_FIXTURE_FORMAT_INVALID")
        input_rate = source.getframerate()
        samples = array("h")
        samples.frombytes(source.readframes(source.getnframes()))
        if sys.byteorder != "little":
            samples.byteswap()
    output_count = round(len(samples) * 16000 / input_rate)
    pcm = bytearray()
    for index in range(output_count):
        position = index * input_rate / 16000
        left = min(int(position), len(samples) - 1)
        right = min(left + 1, len(samples) - 1)
        fraction = position - left
        value = round(samples[left] * (1 - fraction) + samples[right] * fraction)
        pcm.extend(struct.pack("<h", max(-32768, min(32767, value))))
    pcm16 = bytes(pcm)

    server = await serve(gateway.handle, "127.0.0.1", 0, ssl=server_tls, origins=[origin], max_size=12800, compression=None)
    port = server.sockets[0].getsockname()[1]
    print("STAGE=WSS_LOOPBACK_READY", flush=True)
    messages: list[dict] = []
    try:
        async with websockets.connect(f"wss://clinica.test:{port}/dictation", ssl=client_tls, origin=origin, compression=None) as socket:
            await socket.send(json.dumps({"type": "AUTH", "ticket": issue_ticket(secret, int(time.time()))}, separators=(",", ":")))
            ready = json.loads(await asyncio.wait_for(socket.recv(), timeout=5))
            if ready != {"type": "READY", "sample_rate": 16000}:
                raise RuntimeError("GATEWAY_AUTH_OR_SAMPLE_RATE_FAILED")
            print("STAGE=TICKET_ACCEPTED", flush=True)
            chunk_bytes = 3200
            for offset in range(0, len(pcm16), chunk_bytes):
                frame = pcm16[offset : offset + chunk_bytes]
                if len(frame) % 2:
                    frame = frame[:-1]
                if frame:
                    await socket.send(frame)
            await socket.send(json.dumps({"type": "STOP"}, separators=(",", ":")))
            while True:
                message = json.loads(await asyncio.wait_for(socket.recv(), timeout=8))
                messages.append(message)
                if message.get("type") == "DONE":
                    break
        print("STAGE=SYNTHETIC_STREAM_COMPLETE", flush=True)
    finally:
        server.close()
        await server.wait_closed()

    final_text = " ".join(message.get("text", "") for message in messages if message.get("type") == "FINAL").strip()
    partial_count = sum(message.get("type") == "PARTIAL" for message in messages)
    if not final_text:
        raise RuntimeError("NO_FINAL_TRANSCRIPT")
    print(json.dumps({
        "status": "PASS_REAL_LOCAL_WSS_SYNTHETIC_AUDIO",
        "fixture": fixture.name,
        "expected_text": expected_text,
        "recognized_text": final_text,
        "partial_events": partial_count,
        "final_events": sum(message.get("type") == "FINAL" for message in messages),
        "sample_rate_in": input_rate,
        "sample_rate_out": 16000,
        "audio_written": "NO",
        "database_connected": "NO",
    }, ensure_ascii=False))


def issue_ticket(secret: bytes, now: int) -> str:
    claims = {
        "v": 1, "aud": "clinica-vosk-local-v1", "pid": 9301, "sid": hashlib.sha256(b"fictional-session").hexdigest(),
        "question": "M02:family:diabetes", "origin": "https://clinica.test:8443", "iat": now, "exp": now + 30,
        "jti": uuid.uuid4().hex,
    }
    payload = base64.urlsafe_b64encode(json.dumps(claims, separators=(",", ":")).encode()).decode().rstrip("=")
    signature = hmac.new(secret, payload.encode("ascii"), hashlib.sha256).digest()
    return payload + "." + base64.urlsafe_b64encode(signature).decode().rstrip("=")


if __name__ == "__main__":
    asyncio.run(asyncio.wait_for(main(), timeout=45))
