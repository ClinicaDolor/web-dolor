from __future__ import annotations

import argparse
import json
import multiprocessing as mp
import os
import queue
import tempfile
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlsplit

import av
from faster_whisper import WhisperModel


MAX_BYTES = 8 * 1024 * 1024
MAX_SECONDS = 30.0
PROCESS_TIMEOUT_SECONDS = 20.0
ALLOWED_TYPES = {
    "audio/webm": ".webm",
    "audio/ogg": ".ogg",
    "audio/wav": ".wav",
    "audio/x-wav": ".wav",
    "audio/mpeg": ".mp3",
    "audio/mp4": ".mp4",
}


def json_bytes(payload: dict) -> bytes:
    return json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")


def worker_main(model_path: str, requests: mp.Queue, responses: mp.Queue) -> None:
    try:
        model = WhisperModel(model_path, device="cpu", compute_type="int8", cpu_threads=8)
        responses.put({"kind": "ready"})
    except Exception:
        responses.put({"kind": "startup_error"})
        return

    while True:
        path = requests.get()
        if path is None:
            return
        started = time.perf_counter()
        try:
            segments, _info = model.transcribe(
                path,
                language="es",
                task="transcribe",
                beam_size=5,
                vad_filter=True,
                condition_on_previous_text=False,
            )
            text = " ".join(segment.text.strip() for segment in segments).strip()
            responses.put({
                "kind": "result",
                "text": text,
                "latency_seconds": round(time.perf_counter() - started, 3),
            })
        except Exception:
            responses.put({"kind": "error"})


class TranscriptionWorker:
    def __init__(self, model_path: Path) -> None:
        self.model_path = model_path
        self.requests: mp.Queue = mp.Queue(maxsize=1)
        self.responses: mp.Queue = mp.Queue(maxsize=2)
        self.process: mp.Process | None = None

    def start(self) -> None:
        if self.process and self.process.is_alive():
            return
        self.process = mp.Process(
            target=worker_main,
            args=(str(self.model_path), self.requests, self.responses),
            name="voice-stt-02-worker",
        )
        self.process.start()
        try:
            ready = self.responses.get(timeout=30)
        except queue.Empty as error:
            self.stop()
            raise RuntimeError("STT_STARTUP_TIMEOUT") from error
        if ready.get("kind") != "ready":
            self.stop()
            raise RuntimeError("STT_STARTUP_FAILED")

    def transcribe(self, path: str) -> dict:
        self.start()
        try:
            self.requests.put(path, timeout=1)
            result = self.responses.get(timeout=PROCESS_TIMEOUT_SECONDS)
        except queue.Full as error:
            self.stop()
            raise RuntimeError("STT_BUSY") from error
        except queue.Empty as error:
            self.stop()
            raise RuntimeError("STT_TIMEOUT") from error
        if result.get("kind") == "result":
            return result
        raise RuntimeError("STT_TRANSCRIPTION_FAILED")

    def stop(self) -> None:
        if not self.process:
            return
        if self.process.is_alive():
            try:
                self.requests.put_nowait(None)
            except queue.Full:
                pass
            self.process.join(timeout=2)
        if self.process.is_alive():
            self.process.terminate()
            self.process.join(timeout=2)
        self.process = None


def validate_audio(path: Path, content_type: str) -> float:
    with path.open("rb") as source:
        header = source.read(16)
    valid_signature = (
        (content_type in {"audio/wav", "audio/x-wav"} and header[:4] == b"RIFF" and header[8:12] == b"WAVE")
        or (content_type == "audio/webm" and header[:4] == b"\x1a\x45\xdf\xa3")
        or (content_type == "audio/ogg" and header[:4] == b"OggS")
        or (content_type == "audio/mp4" and header[4:8] == b"ftyp")
        or (content_type == "audio/mpeg" and (header[:3] == b"ID3" or header[:2] == b"\xff\xfb"))
    )
    if not valid_signature:
        raise ValueError("CONTAINER_INVALID")

    try:
        container = av.open(str(path))
    except av.error.FFmpegError as error:
        raise ValueError("CONTAINER_INVALID") from error

    with container:
        audio_streams = [stream for stream in container.streams if stream.type == "audio"]
        if not audio_streams:
            raise ValueError("AUDIO_STREAM_MISSING")
        if content_type == "audio/webm":
            codecs = {(stream.codec_context.name or "").lower() for stream in audio_streams}
            if "opus" not in codecs:
                raise ValueError("CODEC_UNSUPPORTED")
        durations = []
        if container.duration is not None and container.duration > 0:
            durations.append(float(container.duration / av.time_base))
        for stream in audio_streams:
            if stream.duration is not None and stream.time_base is not None and stream.duration > 0:
                durations.append(float(stream.duration * stream.time_base))
        if not durations:
            decoded_seconds = 0.0
            try:
                for stream in audio_streams:
                    for frame in container.decode(stream.index):
                        if frame.samples and frame.sample_rate:
                            decoded_seconds += frame.samples / frame.sample_rate
            except av.error.FFmpegError as error:
                raise ValueError("AUDIO_DECODE_FAILED") from error
            durations.append(decoded_seconds)
        duration = max(durations, default=0.0)
    if duration <= 0 or duration > MAX_SECONDS:
        raise ValueError("AUDIO_DURATION_INVALID")
    return duration


class Handler(BaseHTTPRequestHandler):
    server_version = "VoiceSTT02/1.0"

    def log_message(self, _format: str, *_args: object) -> None:
        return

    @property
    def service(self) -> "Service":
        return self.server.service  # type: ignore[attr-defined]

    def reply(self, status: int, payload: dict) -> None:
        body = json_bytes(payload)
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Cache-Control", "no-store")
        self.send_header("X-Content-Type-Options", "nosniff")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def authorized(self) -> bool:
        expected = self.service.token
        received = self.headers.get("X-Voice-Prototype-Token", "")
        return bool(expected) and received == expected

    def do_GET(self) -> None:
        if urlsplit(self.path).path == "/health":
            self.reply(200, {"status": "ok", "offline": True, "loopback_only": True, "model": "small"})
            return
        self.reply(404, {"resultado": False, "mensaje": "Ruta no disponible."})

    def do_POST(self) -> None:
        if urlsplit(self.path).path != "/transcribe":
            self.reply(404, {"resultado": False, "mensaje": "Ruta no disponible."})
            return
        if not self.authorized():
            self.reply(403, {"resultado": False, "mensaje": "Autorización de prototipo inválida."})
            return
        if not self.service.lock.acquire(blocking=False):
            self.reply(429, {"resultado": False, "mensaje": "Ya hay una transcripción en curso."})
            return

        temporary_path: Path | None = None
        try:
            try:
                length = int(self.headers.get("Content-Length", "0"))
            except ValueError:
                length = 0
            content_type = self.headers.get_content_type().lower()
            if length <= 0 or length > MAX_BYTES:
                self.reply(413, {"resultado": False, "mensaje": "El audio supera el límite permitido."})
                return
            if content_type not in ALLOWED_TYPES:
                self.reply(415, {"resultado": False, "mensaje": "Formato de audio no permitido."})
                return
            data = self.rfile.read(length)
            if len(data) != length:
                self.reply(400, {"resultado": False, "mensaje": "Audio incompleto."})
                return
            with tempfile.NamedTemporaryFile(prefix="voice-stt-02-", suffix=ALLOWED_TYPES[content_type], delete=False) as handle:
                handle.write(data)
                temporary_path = Path(handle.name)
            try:
                duration = validate_audio(temporary_path, content_type)
                result = self.service.worker.transcribe(str(temporary_path))
            except ValueError as error:
                self.reply(422, {"resultado": False, "mensaje": "No se pudo validar el audio.", "codigo": str(error)})
                return
            except RuntimeError as error:
                code = str(error)
                status = 504 if code == "STT_TIMEOUT" else 503
                self.reply(status, {"resultado": False, "mensaje": "No fue posible transcribir el audio.", "codigo": code})
                return
            self.reply(200, {
                "resultado": True,
                "texto": result["text"],
                "duracion_segundos": round(duration, 3),
                "latencia_segundos": result["latency_seconds"],
                "persistido": False,
            })
        finally:
            if temporary_path and temporary_path.exists():
                try:
                    temporary_path.unlink()
                except OSError:
                    pass
            self.service.lock.release()


class Service:
    def __init__(self, token: str, model_path: Path) -> None:
        self.token = token
        self.lock = threading.Lock()
        self.worker = TranscriptionWorker(model_path)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--bind", default="127.0.0.1")
    parser.add_argument("--port", type=int, required=True)
    parser.add_argument("--model", required=True)
    args = parser.parse_args()
    token = os.environ.get("VOICE_STT_PROTO_TOKEN", "")
    model_path = Path(args.model).resolve()
    if args.bind != "127.0.0.1" or not token or not model_path.is_dir():
        print("VOICE_STT_START_BLOCKED", file=os.sys.stderr)
        return 2
    mp.set_start_method("spawn", force=True)
    service = Service(token, model_path)
    server = ThreadingHTTPServer((args.bind, args.port), Handler)
    server.daemon_threads = True
    server.service = service  # type: ignore[attr-defined]
    try:
        print(f"VOICE_STT_SERVICE_READY 127.0.0.1:{args.port}", flush=True)
        server.serve_forever(poll_interval=0.2)
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
        service.worker.stop()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
