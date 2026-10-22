class ClinicalDictationCaptureProcessor extends AudioWorkletProcessor {
    constructor(options) {
        super();
        this.chunkSamples = Math.max(800, Math.round((options?.processorOptions?.sampleRate || sampleRate) / 10));
        this.pending = new Float32Array(this.chunkSamples);
        this.offset = 0;
        this.port.onmessage = event => {
            if (event.data?.type === 'RESET') this.offset = 0;
            if (event.data?.type === 'FLUSH') {
                if (this.offset > 0) {
                    const chunk = this.pending.slice(0, this.offset);
                    this.pending = new Float32Array(this.chunkSamples);
                    this.offset = 0;
                    this.port.postMessage({type: 'PCM_FLOAT', samples: chunk.buffer}, [chunk.buffer]);
                }
                this.port.postMessage({type: 'FLUSHED'});
            }
        };
    }

    process(inputs, outputs) {
        const output = outputs[0]?.[0];
        if (output) output.fill(0);
        const channels = inputs[0];
        const mono = channels?.[0];
        if (!mono) return true;
        let cursor = 0;
        while (cursor < mono.length) {
            const count = Math.min(this.chunkSamples - this.offset, mono.length - cursor);
            this.pending.set(mono.subarray(cursor, cursor + count), this.offset);
            this.offset += count;
            cursor += count;
            if (this.offset === this.chunkSamples) {
                const chunk = this.pending;
                this.pending = new Float32Array(this.chunkSamples);
                this.offset = 0;
                this.port.postMessage({type: 'PCM_FLOAT', samples: chunk.buffer}, [chunk.buffer]);
            }
        }
        return true;
    }
}

registerProcessor('clinical-dictation-capture', ClinicalDictationCaptureProcessor);
