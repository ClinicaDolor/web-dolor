from __future__ import annotations

import hashlib
import json
import os
import re
import sys
import threading
import time
import unicodedata
import wave
from pathlib import Path

import psutil
from faster_whisper import WhisperModel


CRITICAL_GROUPS = {
    "audio-03s": [["no"], ["fumo"], ["medicamentos"]],
    "audio-10s": [["no"], ["fumo"], ["ibuprofeno"], ["cuatrocientos", "400"], ["miligramos", "mg"], ["dolor"]],
    "audio-20s": [["no"], ["fumo"], ["ibuprofeno"], ["cuatrocientos", "400"], ["miligramos", "mg"], ["paracetamol"], ["quinientos", "500"], ["dolor"]],
}


def normalize(value: str) -> list[str]:
    value = unicodedata.normalize("NFKD", value.casefold())
    value = "".join(char for char in value if not unicodedata.combining(char))
    return re.findall(r"[a-z0-9]+", value)


def wav_duration(path: Path) -> float:
    with wave.open(str(path), "rb") as source:
        return source.getnframes() / float(source.getframerate())


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


class ResourceMonitor:
    def __init__(self) -> None:
        self.process = psutil.Process(os.getpid())
        self.stop = threading.Event()
        self.samples: list[tuple[int, float]] = []
        self.thread = threading.Thread(target=self._run, daemon=True)

    def _run(self) -> None:
        self.process.cpu_percent(None)
        while not self.stop.is_set():
            try:
                self.samples.append((self.process.memory_info().rss, self.process.cpu_percent(0.1)))
            except (psutil.NoSuchProcess, psutil.AccessDenied):
                return

    def __enter__(self) -> "ResourceMonitor":
        self.thread.start()
        return self

    def __exit__(self, *_args: object) -> None:
        self.stop.set()
        self.thread.join(timeout=2)

    def summary(self) -> dict[str, float | None]:
        return {
            "peak_ram_mb": round(max((rss for rss, _ in self.samples), default=0) / 1024 / 1024, 1),
            "peak_process_cpu_percent": round(max((cpu for _, cpu in self.samples), default=0), 1),
        }


def transcribe(model: WhisperModel, path: Path) -> tuple[str, float]:
    started = time.perf_counter()
    segments, info = model.transcribe(
        str(path), language="es", task="transcribe", beam_size=5, vad_filter=True,
        condition_on_previous_text=False,
    )
    text = " ".join(segment.text.strip() for segment in segments).strip()
    elapsed = time.perf_counter() - started
    return text, elapsed


def evaluate(sample_id: str, expected: str, actual: str) -> dict[str, object]:
    tokens = set(normalize(actual))
    groups = CRITICAL_GROUPS.get(sample_id, [])
    missing = ["/".join(group) for group in groups if not any(token in tokens for token in group)]
    return {
        "expected_text": expected,
        "transcription": actual,
        "critical_groups": groups,
        "missing_critical_groups": missing,
        "clinical_significant_error": bool(missing),
        "token_overlap": round(sum(1 for token in set(normalize(expected)) if token in tokens) / max(1, len(set(normalize(expected)))), 3),
    }


def main() -> int:
    if len(sys.argv) != 4:
        print("usage: benchmark.py MODEL_SNAPSHOT AUDIO_DIR OUTPUT_JSON", file=sys.stderr)
        return 2
    model_path = Path(sys.argv[1]).resolve()
    audio_dir = Path(sys.argv[2]).resolve()
    output_path = Path(sys.argv[3]).resolve()
    manifest = json.loads((audio_dir / "manifest.json").read_text(encoding="utf-8-sig"))
    process = psutil.Process(os.getpid())

    started = time.perf_counter()
    model = WhisperModel(str(model_path), device="cpu", compute_type="int8", cpu_threads=max(1, min(8, os.cpu_count() or 1)))
    cold_start_seconds = time.perf_counter() - started

    results = []
    with ResourceMonitor() as monitor:
        for sample in manifest:
            path = Path(sample["file"])
            text, elapsed = transcribe(model, path)
            results.append({
                "id": sample["id"],
                "duration_seconds": round(wav_duration(path), 3),
                "sha256": sha256(path),
                "latency_seconds": round(elapsed, 3),
                "real_time_factor": round(elapsed / max(0.001, wav_duration(path)), 3),
                "evaluation": evaluate(sample["id"], sample["expected_text"], text),
            })
    resources = monitor.summary()
    output = {
        "phase": "VOICE-STT-01",
        "python": sys.version,
        "model_snapshot": str(model_path),
        "device": "cpu",
        "compute_type": "int8",
        "cold_start_seconds": round(cold_start_seconds, 3),
        "warm_transcription": results,
        "peak_ram_mb": resources["peak_ram_mb"],
        "peak_process_cpu_percent": resources["peak_process_cpu_percent"],
        "offline_after_model_load": os.environ.get("HF_HUB_OFFLINE") == "1",
        "external_audio_transmission": False,
        "synthetic_audio_only": True,
    }
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(json.dumps(output, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps(output, ensure_ascii=False, indent=2))
    return 0 if all(not item["evaluation"]["clinical_significant_error"] for item in results) else 1


if __name__ == "__main__":
    raise SystemExit(main())
