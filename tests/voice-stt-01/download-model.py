from __future__ import annotations

import hashlib
import json
import os
import sys
from pathlib import Path

from huggingface_hub import snapshot_download


REPOSITORY = "Systran/faster-whisper-small"


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def main() -> int:
    if len(sys.argv) != 2:
        print("usage: download-model.py MODEL_CACHE", file=sys.stderr)
        return 2
    cache = Path(sys.argv[1]).resolve()
    cache.mkdir(parents=True, exist_ok=True)
    snapshot = Path(
        snapshot_download(
            repo_id=REPOSITORY,
            cache_dir=str(cache),
            allow_patterns=["*.json", "*.txt", "*.model", "*.bin", "*.safetensors"],
        )
    )
    files = []
    for path in sorted(snapshot.rglob("*")):
        if path.is_file():
            files.append({"name": str(path.relative_to(snapshot)), "bytes": path.stat().st_size})
    license_file = next((p for p in (snapshot / "LICENSE", snapshot / "LICENSE.md") if p.is_file()), None)
    manifest = {
        "repository": REPOSITORY,
        "snapshot_revision": snapshot.name,
        "snapshot_path": str(snapshot),
        "license_file": str(license_file) if license_file else None,
        "license_sha256": sha256(license_file) if license_file else None,
        "files": files,
        "downloaded_with_network": True,
        "subsequent_benchmark_mode": "local_snapshot_only",
    }
    output = cache / "model-manifest.json"
    output.write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps(manifest, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
