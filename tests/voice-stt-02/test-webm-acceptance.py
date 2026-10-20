from __future__ import annotations

import json
import http.client
import sys
import tempfile
import threading
import wave
from pathlib import Path

import av


ROOT = Path(__file__).resolve().parents[2]
VOICE_DIR = Path(__file__).resolve().parent
sys.path.insert(0, str(VOICE_DIR))
import stt_service  # noqa: E402


class FakeWorker:
    def __init__(self, text: str = "No fumo y no tomo medicamentos.", error: str | None = None) -> None:
        self.text = text
        self.error = error

    def transcribe(self, _path: str) -> dict:
        if self.error:
            raise RuntimeError(self.error)
        return {"text": self.text, "latency_seconds": 0.001}

    def stop(self) -> None:
        return


def request(port: int, body: bytes, content_type: str, token: str | None = "synthetic-token", declared_length: int | None = None) -> tuple[int, dict]:
    connection = http.client.HTTPConnection("127.0.0.1", port, timeout=5)
    headers = {"Content-Type": content_type, "Content-Length": str(len(body) if declared_length is None else declared_length)}
    if token:
        headers["X-Voice-Prototype-Token"] = token
    try:
        connection.request("POST", "/transcribe", body=body, headers=headers)
        response = connection.getresponse()
        payload = response.read().decode("utf-8")
        return response.status, json.loads(payload)
    finally:
        connection.close()


def write_silence(path: Path) -> None:
    with wave.open(str(path), "wb") as audio:
        audio.setnchannels(1)
        audio.setsampwidth(2)
        audio.setframerate(16000)
        audio.writeframes(b"\0" * (16000 * 2 * 3))


def make_webm(source: Path, destination: Path) -> None:
    source_container = av.open(str(source))
    input_stream = next(stream for stream in source_container.streams if stream.type == "audio")
    output_container = av.open(str(destination), mode="w", format="webm")
    output_stream = output_container.add_stream("libopus", rate=48000)
    output_stream.layout = "mono"
    resampler = av.AudioResampler(format="s16", layout="mono", rate=48000)
    try:
        for frame in source_container.decode(input_stream):
            for resampled in resampler.resample(frame):
                for packet in output_stream.encode(resampled):
                    output_container.mux(packet)
        for packet in output_stream.encode(None):
            output_container.mux(packet)
    finally:
        output_container.close()
        source_container.close()


def remove_duration_metadata(source: Path, destination: Path) -> None:
    data = bytearray(source.read_bytes())
    duration_id = b"\x44\x89"
    offset = data.find(duration_id)
    if offset < 0 or offset + 11 > len(data):
        raise RuntimeError("DURATION_ELEMENT_NOT_FOUND")
    size_byte = data[offset + 2]
    if size_byte != 0x88:
        raise RuntimeError("UNEXPECTED_DURATION_ELEMENT")
    data[offset + 3 : offset + 11] = b"\0" * 8
    destination.write_bytes(data)


def run_case(server: stt_service.ThreadingHTTPServer, name: str, body: bytes, content_type: str, expected_status: int, expected_code: str | None = None, declared_length: int | None = None) -> str:
    status, payload = request(server.server_port, body, content_type, declared_length=declared_length)
    if status != expected_status:
        raise AssertionError(f"{name}: HTTP {status} != {expected_status}; payload={payload}")
    if expected_code and payload.get("codigo") != expected_code:
        raise AssertionError(f"{name}: code {payload.get('codigo')} != {expected_code}")
    return f"{name}=HTTP_{status}:{payload.get('codigo', 'OK')}"


def main() -> int:
    source_wav = ROOT / "tests" / "voice-stt-01" / "audio" / "audio-03s.wav"
    with tempfile.TemporaryDirectory(prefix="voice-stt-02e-test-") as temp_dir:
        temp = Path(temp_dir)
        webm = temp / "valid.webm"
        no_duration_webm = temp / "no-duration.webm"
        silence = temp / "silence.wav"
        oversized = temp / "oversized.webm"
        make_webm(source_wav, webm)
        remove_duration_metadata(webm, no_duration_webm)
        write_silence(silence)
        oversized.write_bytes(b"\0" * (stt_service.MAX_BYTES + 1))

        service = stt_service.Service("synthetic-token", Path(temp_dir))
        service.worker = FakeWorker()
        server = stt_service.ThreadingHTTPServer(("127.0.0.1", 0), stt_service.Handler)
        server.daemon_threads = True
        server.service = service  # type: ignore[attr-defined]
        thread = threading.Thread(target=server.serve_forever, kwargs={"poll_interval": 0.05}, daemon=True)
        thread.start()
        results: list[str] = []
        try:
            results.append(run_case(server, "A_WAV_VALID", source_wav.read_bytes(), "audio/wav", 200))
            results.append(run_case(server, "B_WEBM_OPUS_VALID", webm.read_bytes(), "audio/webm;codecs=opus", 200))
            results.append(run_case(server, "B_WEBM_NO_DURATION_VALID", no_duration_webm.read_bytes(), "audio/webm;codecs=opus", 200))
            results.append(run_case(server, "C_MALFORMED_WEBM", b"not-a-webm", "audio/webm", 422, "CONTAINER_INVALID"))
            results.append(run_case(server, "D_EMPTY", b"", "audio/webm", 413))
            results.append(run_case(server, "E_FORGED_MIME", webm.read_bytes(), "audio/wav", 422, "CONTAINER_INVALID"))
            results.append(run_case(server, "F_OVERSIZED", b"", "audio/webm", 413, declared_length=stt_service.MAX_BYTES + 1))

            service.worker = FakeWorker(text="")
            results.append(run_case(server, "G_NO_SPEECH", silence.read_bytes(), "audio/wav", 200))

            status, payload = request(server.server_port, source_wav.read_bytes(), "audio/wav", token=None)
            if status != 403:
                raise AssertionError(f"H_DIRECT_NO_AUTH: HTTP {status} != 403; payload={payload}")
            results.append("H_DIRECT_NO_AUTH=HTTP_403")

            service.worker = FakeWorker(error="STT_TRANSCRIPTION_FAILED")
            results.append(run_case(server, "I_SERVICE_ERROR", source_wav.read_bytes(), "audio/wav", 503, "STT_TRANSCRIPTION_FAILED"))
            service.worker = FakeWorker(error="STT_TIMEOUT")
            results.append(run_case(server, "I_TIMEOUT", source_wav.read_bytes(), "audio/wav", 504, "STT_TIMEOUT"))
        finally:
            server.shutdown()
            server.server_close()
            thread.join(timeout=3)
            service.worker.stop()

        leftovers = list(Path(tempfile.gettempdir()).glob("voice-stt-02-*"))
        if leftovers:
            raise AssertionError(f"J_TEMP_FILES_REMAINING: {leftovers}")
        results.append("J_TEMP_FILES_REMAINING=0")

        print("\n".join(results))
        print("VALIDATION_HARNESS=PASS")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
