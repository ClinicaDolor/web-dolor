from __future__ import annotations

import argparse
from pathlib import Path

import av


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("source", type=Path)
    parser.add_argument("destination", type=Path)
    args = parser.parse_args()

    source = av.open(str(args.source))
    input_stream = next(stream for stream in source.streams if stream.type == "audio")
    output = av.open(str(args.destination), mode="w", format="webm")
    output_stream = output.add_stream("libopus", rate=48000)
    output_stream.layout = "mono"
    resampler = av.AudioResampler(format="s16", layout="mono", rate=48000)
    try:
        for frame in source.decode(input_stream):
            for resampled in resampler.resample(frame):
                for packet in output_stream.encode(resampled):
                    output.mux(packet)
        for packet in output_stream.encode(None):
            output.mux(packet)
    finally:
        output.close()
        source.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
