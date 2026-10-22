(function (global) {
    'use strict';

    const TARGET_RATE = 16000;
    const MAX_BUFFERED_BYTES = 256 * 1024;
    const normalize = value => String(value || '').toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').split(/\s+/).filter(Boolean);

    function appendWithoutDuplicate(baseValue, additionValue) {
        const base = String(baseValue || '').trim();
        const addition = String(additionValue || '').trim();
        if (!addition) return base;
        if (!base) return addition;
        const baseWords = base.split(/\s+/);
        const additionWords = addition.split(/\s+/);
        const baseNormalized = normalize(base).join(' ');
        const additionNormalized = normalize(addition).join(' ');
        if (baseNormalized.includes(additionNormalized)) return base;
        let overlap = 0;
        for (let size = Math.min(12, baseWords.length, additionWords.length); size > 0; size -= 1) {
            if (normalize(baseWords.slice(-size).join(' ')).join(' ') === normalize(additionWords.slice(0, size).join(' ')).join(' ')) {
                overlap = size;
                break;
            }
        }
        return `${base} ${additionWords.slice(overlap).join(' ')}`.trim();
    }

    function textSuffixAfter(baselineValue, fullValue) {
        const baseline = String(baselineValue || '').trim().split(/\s+/).filter(Boolean);
        const full = String(fullValue || '').trim().split(/\s+/).filter(Boolean);
        if (!baseline.length) return full.join(' ');
        const matches = baseline.length <= full.length && baseline.every((word, index) => normalize(word).join(' ') === normalize(full[index]).join(' '));
        return matches ? full.slice(baseline.length).join(' ') : '';
    }

    function createResampleState() {
        return {inputTime: 0, outputIndex: 0, accumulated: 0, covered: 0};
    }

    function resampleMono(samples, inputRate, outputRate = TARGET_RATE, state = createResampleState()) {
        if (!samples || !Number.isFinite(inputRate) || inputRate <= 0 || !Number.isFinite(outputRate) || outputRate <= 0) {
            throw new TypeError('Frecuencia de muestreo no válida.');
        }
        if (inputRate === outputRate) return {samples: Float32Array.from(samples), state};
        const inputPeriod = 1 / inputRate;
        const outputPeriod = 1 / outputRate;
        const epsilon = outputPeriod * 1e-7;
        const converted = [];
        for (let i = 0; i < samples.length; i += 1) {
            let cursor = state.inputTime;
            const inputEnd = cursor + inputPeriod;
            while (cursor < inputEnd - epsilon) {
                const outputEnd = (state.outputIndex + 1) * outputPeriod;
                const overlapEnd = Math.min(inputEnd, outputEnd);
                const duration = overlapEnd - cursor;
                state.accumulated += Number(samples[i]) * duration;
                state.covered += duration;
                cursor = overlapEnd;
                if (state.covered >= outputPeriod - epsilon) {
                    converted.push(state.accumulated / outputPeriod);
                    state.outputIndex += 1;
                    state.accumulated = 0;
                    state.covered = 0;
                }
            }
            state.inputTime = inputEnd;
        }
        return {samples: Float32Array.from(converted), state};
    }

    class PatientInterviewLocalDictation {
        constructor(options = {}) {
            this.options = options;
            this.state = null;
            this.generation = 0;
            this.startPending = false;
        }

        supported() {
            return Boolean(global.isSecureContext
                && global.navigator?.mediaDevices?.getUserMedia
                && global.AudioContext
                && global.AudioWorkletNode
                && global.WebSocket);
        }

        static isM02DictationContractEligible(target, question, module) {
            if (!/^M02:/.test(String(target || ''))
                || question?.module_id !== 'M02'
                || question?.stable_id !== target
                || question?.write_enabled !== true
                || module?.state?.write_enabled !== true
                || module?.state?.finalized === true
                || module?.conversation_state?.state !== 'ANSWERABLE') return false;

            const response = question.response || {};
            const pendingAndActive = response.status === 'pending'
                && module.conversation_state.stable_id === target;
            const savedAnswerEditable = response.status === 'documented'
                && response.persisted === true;
            return pendingAndActive || savedAnswerEditable;
        }

        _targetAuthorized(target, input) {
            if (this.options.mode === 'diagnostic') return true;
            if (!/^M02:/.test(String(target || ''))) return false;
            try {
                return typeof this.options.isTargetAuthorized === 'function'
                    && this.options.isTargetAuthorized(target, input) === true;
            } catch (_) {
                return false;
            }
        }

        async start(button, input) {
            if (this.state) {
                if (this.state.button === button) await this.stop(false);
                return false;
            }
            if (this.startPending) return false;
            if (!this.supported()) {
                this.options.setStatus?.('UNAVAILABLE', 'El dictado local requiere un navegador compatible y HTTPS. Puedes responder por escrito.');
                return false;
            }
            const diagnostic = this.options.mode === 'diagnostic';
            const target = diagnostic ? 'LOCAL_VOICE_DIAGNOSTIC' : String(this.options.getTarget?.() || '');
            const patientId = diagnostic ? 0 : Number(this.options.getPatientId?.() || 0);
            const contractVersion = diagnostic ? '' : String(this.options.getContractVersion?.() || '');
            if ((!diagnostic && (!target || !Number.isInteger(patientId) || patientId < 1 || !contractVersion || this.options.isPaused?.() || !this._targetAuthorized(target, input))) || input.disabled || input.readOnly) {
                this.options.setStatus?.('UNAVAILABLE', diagnostic ? 'El diagnóstico de voz no está disponible.' : 'La pregunta activa no admite dictado en este momento.');
                return false;
            }
            this.startPending = true;
            const generation = ++this.generation;
            const state = {
                generation, target, button, input, baseText: input.value || '', manualText: input.value || '',
                finalText: '', partialText: '', applying: false, manualEdit: false, socket: null,
                stream: null, context: null, source: null, worklet: null, mute: null,
                resampleState: createResampleState(), pendingPcm: [], pendingSamples: 0,
                stopRequested: false, finalized: false, sampleRate: 0,
            };
            try {
                await Promise.resolve(this.options.stopVoice?.());
                if (!this._current(state)) { await this._release(state, true); return false; }

                this.options.setStatus?.('AUTHORIZING', diagnostic ? 'Verificando el acceso al diagnóstico local…' : 'Verificando la pregunta antes de solicitar acceso al micrófono…');
                const ticketResponse = diagnostic
                    ? await this._requestDiagnosticTicket()
                    : await this._requestTicket(patientId, target, contractVersion);
                if (!this._current(state)) { await this._release(state, true); return false; }
                const token = ticketResponse.ticket;
                const socketUrl = this._validateSocketUrl(ticketResponse.websocket_url);
                const socket = new global.WebSocket(socketUrl);
                socket.binaryType = 'arraybuffer';
                state.socket = socket;
                await this._waitSocketOpen(socket, state);
                if (!this._current(state)) { await this._release(state, true); return false; }
                socket.send(JSON.stringify({type: 'AUTH', ticket: token}));
                await this._waitGatewayReady(socket, state);
                if (!this._current(state)) { await this._release(state, true); return false; }

                // Ask for mic permission only after the authenticated server accepted this active question.
                this.options.setStatus?.('CONNECTING', diagnostic ? 'Conexión WSS autorizada; solicitando permiso del micrófono…' : 'Solicitando acceso al micrófono para dictado local…');
                state.stream = await global.navigator.mediaDevices.getUserMedia({
                    audio: {channelCount: {ideal: 1}, echoCancellation: true, noiseSuppression: true, autoGainControl: false},
                    video: false,
                });
                if (!this._current(state)) { await this._release(state, true); return false; }

                state.context = await this._createAudioContext();
                state.sampleRate = state.context.sampleRate;
                await state.context.audioWorklet.addModule(this.options.workletUrl);
                if (!this._current(state)) { await this._release(state, true); return false; }
                state.source = state.context.createMediaStreamSource(state.stream);
                state.worklet = new AudioWorkletNode(state.context, 'clinical-dictation-capture', {
                    numberOfInputs: 1, numberOfOutputs: 1, channelCount: 1,
                    channelCountMode: 'explicit', channelInterpretation: 'speakers',
                    processorOptions: {sampleRate: state.context.sampleRate},
                });
                state.mute = state.context.createGain();
                state.mute.gain.value = 0;
                state.worklet.port.onmessage = event => this._onAudioChunk(state, event.data);
                state.source.connect(state.worklet);
                state.worklet.connect(state.mute);
                state.mute.connect(state.context.destination);
                this.state = state;
                button.classList.add('is-listening');
                button.textContent = '⏹';
                button.title = 'Detener dictado local';
                button.setAttribute('aria-label', 'Detener dictado local');
                input.addEventListener('input', state.onManualInput = () => {
                    if (this.state === state && !state.applying) {
                        state.manualEdit = true;
                        state.manualText = input.value;
                        state.manualFinalBaseline = state.finalText;
                        state.manualPartialBaseline = state.partialText;
                    }
                });
                socket.addEventListener('message', event => this._onGatewayMessage(state, event));
                socket.addEventListener('close', () => {
                    if (this.state === state && !state.stopRequested) this._finish(state, 'El canal local se interrumpió. Revisa el texto y continúa por escrito si lo necesitas.');
                });
                socket.addEventListener('error', () => {
                    if (this.state === state) this._finish(state, 'El servicio local no respondió. Tu borrador permanece en el campo.');
                });
                this.options.setStatus?.('LISTENING', diagnostic ? 'Escuchando localmente. La transcripción no se enviará ni guardará.' : 'Escuchando localmente… el texto no se enviará ni guardará automáticamente.');
                return true;
            } catch (error) {
                if (this.state === state) this.state = null;
                this.generation += 1;
                await this._release(state, true);
                this._resetButton(button);
                const message = error?.name === 'NotAllowedError'
                    ? (this.options.mode === 'diagnostic' ? 'Permiso del micrófono denegado por Chrome.' : 'Permiso de micrófono denegado. Puedes responder por escrito.')
                    : error?.name === 'NotFoundError'
                        ? (this.options.mode === 'diagnostic' ? 'Chrome no encontró un micrófono disponible.' : 'No se encontró un micrófono. Puedes responder por escrito.')
                        : (this.options.mode === 'diagnostic'
                            ? 'No fue posible iniciar el diagnóstico local. El texto existente permanece en pantalla.'
                            : 'No fue posible iniciar el dictado local. Tu respuesta escrita permanece disponible.');
                this.options.setStatus?.('ERROR', message);
                return false;
            } finally {
                this.startPending = false;
            }
        }

        async stop(abort = true) {
            const state = this.state;
            if (!state) {
                this.generation += 1;
                this.startPending = false;
                return;
            }
            if (state.finalizing) return;
            state.stopRequested = true;
            state.finalizing = !abort;
            if (abort) {
                this.generation += 1;
                this.state = null;
                await this._release(state, true);
                this._resetButton(state.button);
                this.options.setStatus?.(state.input.value.trim() ? 'TRANSCRIPT_READY' : 'IDLE', state.input.value.trim()
                    ? (this.options.mode === 'diagnostic' ? 'Dictado cancelado; el texto temporal permanece en pantalla.' : 'Dictado cancelado; revisa el borrador antes de enviar.')
                    : (this.options.mode === 'diagnostic' ? 'Dictado detenido.' : 'Dictado detenido. Puedes responder por escrito.'));
                return;
            }

            // Stop capture and drain the worklet's sub-100ms tail before finalizing Vosk.
            if (state.stream) state.stream.getTracks().forEach(track => { try { track.stop(); } catch (_) {} });
            await this._flushWorklet(state, 300);
            this._flushPendingPcm(state);
            await this._release(state, false);
            if (state.socket?.readyState === global.WebSocket.OPEN) {
                try {
                    const done = this._waitForDone(state, 1800);
                    state.socket.send(JSON.stringify({type: 'STOP'}));
                    await done;
                } catch (_) {
                    this._render(state);
                }
            }
            if (state.socket?.readyState === global.WebSocket.OPEN) {
                try { state.socket.close(1000, 'Dictado detenido'); } catch (_) {}
            }
            this.generation += 1;
            if (this.state === state) this.state = null;
            this._resetButton(state.button);
            this.options.setStatus?.(state.input.value.trim() ? 'TRANSCRIPT_READY' : 'IDLE', state.input.value.trim()
                ? (this.options.mode === 'diagnostic' ? 'Dictado detenido. Revisa o corrige la transcripción temporal.' : 'Dictado detenido. Revisa o corrige la respuesta y pulsa Enviar.')
                : (this.options.mode === 'diagnostic' ? 'Dictado detenido.' : 'Dictado detenido. Puedes responder por escrito.'));
        }

        async _requestDiagnosticTicket() {
            const response = await global.fetch(this.options.ticketUrl, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': this.options.getCsrf?.()},
                body: '',
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.resultado || !data?.ticket || !data?.websocket_url) {
                const error = new Error('DICTATION_DIAGNOSTIC_TICKET_REJECTED');
                error.status = response.status;
                throw error;
            }
            return data;
        }

        async _requestTicket(patientId, stableId, contractVersion) {
            const response = await global.fetch(this.options.ticketUrl, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': this.options.getCsrf?.()},
                body: JSON.stringify({idPaciente: patientId, stable_id: stableId, contract_version: contractVersion}),
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.resultado || !data?.ticket || !data?.websocket_url) {
                const error = new Error('DICTATION_TICKET_REJECTED');
                error.status = response.status;
                throw error;
            }
            return data;
        }

        _validateSocketUrl(value) {
            const url = new URL(String(value || ''), global.location.href);
            const diagnostic = this.options.mode === 'diagnostic';
            if (url.protocol !== 'wss:' || url.hostname !== 'clinica.test' || url.port !== (diagnostic ? '8767' : '8766') || url.pathname !== (diagnostic ? '/diagnostic' : '/dictation') || url.username || url.password || url.search || url.hash
                || (diagnostic && global.location.origin !== 'https://clinica.test:8443')) {
                throw new Error('DICTATION_SOCKET_URL_INVALID');
            }
            return url.href;
        }

        _waitSocketOpen(socket, state) {
            return new Promise((resolve, reject) => {
                const timer = global.setTimeout(() => reject(new Error('DICTATION_SOCKET_TIMEOUT')), 5000);
                socket.addEventListener('open', () => { global.clearTimeout(timer); resolve(); }, {once: true});
                socket.addEventListener('error', () => { global.clearTimeout(timer); reject(new Error('DICTATION_SOCKET_FAILED')); }, {once: true});
                socket.addEventListener('close', () => { global.clearTimeout(timer); reject(new Error('DICTATION_SOCKET_CLOSED')); }, {once: true});
                if (!this._current(state)) { global.clearTimeout(timer); reject(new Error('DICTATION_TARGET_CHANGED')); }
            });
        }

        _waitGatewayReady(socket, state) {
            return new Promise((resolve, reject) => {
                const timer = global.setTimeout(() => reject(new Error('DICTATION_AUTH_TIMEOUT')), 5000);
                const cleanup = () => {
                    global.clearTimeout(timer);
                    socket.removeEventListener('message', onMessage);
                    socket.removeEventListener('close', onClose);
                };
                const onMessage = event => {
                    let data;
                    try { data = JSON.parse(event.data); } catch (_) { cleanup(); reject(new Error('DICTATION_RESPONSE_INVALID')); return; }
                    if (data.type === 'READY' && data.sample_rate === TARGET_RATE && this._current(state)) { cleanup(); resolve(); }
                    else if (data.type === 'ERROR') { cleanup(); reject(new Error('DICTATION_AUTH_REJECTED')); }
                };
                const onClose = () => { cleanup(); reject(new Error('DICTATION_AUTH_CLOSED')); };
                socket.addEventListener('message', onMessage);
                socket.addEventListener('close', onClose, {once: true});
                if (!this._current(state)) { cleanup(); reject(new Error('DICTATION_TARGET_CHANGED')); }
            });
        }

        async _createAudioContext() {
            try {
                const context = new global.AudioContext({sampleRate: TARGET_RATE, latencyHint: 'interactive'});
                await context.resume();
                return context;
            } catch (_) {
                const context = new global.AudioContext({latencyHint: 'interactive'});
                await context.resume();
                return context;
            }
        }

        _onAudioChunk(state, message) {
            if ((this.state !== state && !state.finalizing) || !this._current(state) || message?.type !== 'PCM_FLOAT') return;
            const samples = new Float32Array(message.samples);
            let converted;
            try { converted = resampleMono(samples, state.sampleRate, TARGET_RATE, state.resampleState).samples; }
            catch (_) { this._finish(state, 'No fue posible convertir el audio. Puedes responder por escrito.'); return; }
            if (!converted.length) return;
            const pcm = new ArrayBuffer(converted.length * 2);
            const view = new DataView(pcm);
            for (let i = 0; i < converted.length; i += 1) {
                const sample = Math.max(-1, Math.min(1, converted[i]));
                view.setInt16(i * 2, sample < 0 ? sample * 32768 : sample * 32767, true);
            }
            state.pendingPcm.push(new Uint8Array(pcm));
            state.pendingSamples += converted.length;
            while (state.pendingSamples >= 1600) {
                const bytes = this._takePcm(state, 1600);
                if (state.socket?.readyState !== global.WebSocket.OPEN || state.socket.bufferedAmount > MAX_BUFFERED_BYTES) {
                    this._finish(state, 'La conexión local está ocupada. Revisa el texto parcial o responde por escrito.');
                    return;
                }
                state.socket.send(bytes);
            }
        }

        _takePcm(state, count) {
            const neededBytes = count * 2;
            const output = new Uint8Array(neededBytes);
            let outputOffset = 0;
            while (outputOffset < neededBytes && state.pendingPcm.length) {
                const first = state.pendingPcm[0];
                const take = Math.min(first.length, neededBytes - outputOffset);
                output.set(first.subarray(0, take), outputOffset);
                outputOffset += take;
                if (take === first.length) state.pendingPcm.shift();
                else state.pendingPcm[0] = first.subarray(take);
            }
            state.pendingSamples -= count;
            return output.buffer;
        }

        _flushPendingPcm(state) {
            if (!state.pendingSamples || state.socket?.readyState !== global.WebSocket.OPEN) return;
            if (state.socket.bufferedAmount > MAX_BUFFERED_BYTES) return;
            state.socket.send(this._takePcm(state, state.pendingSamples));
        }

        _flushWorklet(state, timeoutMs) {
            if (!state.worklet) return Promise.resolve();
            return new Promise(resolve => {
                const timer = global.setTimeout(() => {
                    state.worklet?.port.removeEventListener('message', onMessage);
                    resolve();
                }, timeoutMs);
                const onMessage = event => {
                    if (event.data?.type !== 'FLUSHED') return;
                    global.clearTimeout(timer);
                    state.worklet.port.removeEventListener('message', onMessage);
                    resolve();
                };
                state.worklet.port.addEventListener('message', onMessage);
                state.worklet.port.start?.();
                state.worklet.port.postMessage({type: 'FLUSH'});
            });
        }

        _onGatewayMessage(state, event) {
            if (this.state !== state || !this._current(state)) return;
            let message;
            try { message = JSON.parse(event.data); } catch (_) { return; }
            if (message.type === 'PARTIAL' && typeof message.text === 'string') {
                state.partialText = message.text.trim();
                this._render(state);
                if (state.partialText) this.options.setStatus?.('RECOGNIZING', this.options.mode === 'diagnostic'
                    ? 'Reconociendo… las palabras provisionales se actualizan mientras hablas.'
                    : 'Texto provisional… sigue hablando o detén el dictado.');
            } else if (message.type === 'FINAL' && typeof message.text === 'string') {
                state.finalText = appendWithoutDuplicate(state.finalText, message.text);
                state.partialText = '';
                this._render(state);
                this.options.setStatus?.('TRANSCRIPT_READY', this.options.mode === 'diagnostic'
                    ? 'Segmento reconocido localmente; puedes corregir el texto temporal.'
                    : 'Texto reconocido localmente. Puedes corregirlo antes de pulsar Enviar.');
            } else if (message.type === 'ERROR') {
                this._finish(state, this.options.mode === 'diagnostic'
                    ? 'El servicio local detuvo el diagnóstico; el texto temporal permanece en pantalla.'
                    : 'El servicio local detuvo el dictado. Tu borrador permanece en el campo.');
            } else if (message.type === 'DONE') {
                state.finalized = true;
            }
        }

        _render(state) {
            if (!this._current(state) || !state.input.isConnected) return;
            const finalBaseline = state.manualFinalBaseline || state.manualPartialBaseline;
            const transcript = state.manualEdit
                ? appendWithoutDuplicate(textSuffixAfter(finalBaseline, state.finalText), textSuffixAfter(state.manualPartialBaseline, state.partialText))
                : appendWithoutDuplicate(state.finalText, state.partialText);
            const base = state.manualEdit ? state.manualText : state.baseText;
            state.applying = true;
            state.input.value = appendWithoutDuplicate(base, transcript);
            state.input.dispatchEvent(new global.Event('input', {bubbles: true}));
            state.applying = false;
        }

        _current(state) {
            return this.generation === state.generation
                && String(this.options.getTarget?.() || '') === state.target
                && !this.options.isPaused?.()
                && !state.input.disabled
                && !state.input.readOnly
                && this._targetAuthorized(state.target, state.input);
        }

        async _waitForDone(state, timeoutMs) {
            if (state.finalized) return;
            await new Promise(resolve => {
                const finish = () => {
                    global.clearTimeout(timer);
                    state.socket?.removeEventListener('message', handler);
                    resolve();
                };
                const timer = global.setTimeout(finish, timeoutMs);
                const handler = event => {
                    try {
                        const message = JSON.parse(event.data);
                        if (message.type === 'DONE') { finish(); return; }
                        if (message.type === 'FINAL' && typeof message.text === 'string' && String(this.options.getTarget?.() || '') === state.target) {
                            state.finalText = appendWithoutDuplicate(state.finalText, message.text);
                            state.partialText = '';
                            this._render(state);
                        }
                    } catch (_) { /* malformed server frames do not alter the answer */ }
                };
                state.socket?.addEventListener('message', handler);
            });
        }

        _finish(state, message) {
            if (this.state !== state) return;
            state.stopRequested = true;
            this.state = null;
            this.generation += 1;
            void this._release(state, true);
            this._resetButton(state.button);
            this.options.setStatus?.(state.input.value.trim() ? 'TRANSCRIPT_READY' : 'ERROR', this.options.mode === 'diagnostic'
                ? 'El canal local se interrumpió; el texto temporal permanece en pantalla.' : message);
        }

        async _release(state, closeSocket) {
            if (state.worklet) { state.worklet.port.onmessage = null; try { state.worklet.disconnect(); } catch (_) {} }
            if (state.source) { try { state.source.disconnect(); } catch (_) {} }
            if (state.mute) { try { state.mute.disconnect(); } catch (_) {} }
            if (state.stream) state.stream.getTracks().forEach(track => { try { track.stop(); } catch (_) {} });
            if (state.context && state.context.state !== 'closed') { try { await state.context.close(); } catch (_) {} }
            if (closeSocket && state.socket && state.socket.readyState < global.WebSocket.CLOSING) {
                try { state.socket.close(1000, 'Dictado detenido'); } catch (_) {}
            }
            if (state.onManualInput) state.input.removeEventListener('input', state.onManualInput);
            state.pendingPcm = [];
        }

        _resetButton(button) {
            if (!button) return;
            button.disabled = false;
            button.classList.remove('is-listening');
            button.textContent = '🎙';
            button.title = 'Dictado local en español';
            button.setAttribute('aria-label', 'Responder mediante micrófono');
        }
    }

    global.PatientInterviewLocalDictation = PatientInterviewLocalDictation;
    global.PatientInterviewDictationResample = resampleMono;
    global.PatientInterviewDictationAppend = appendWithoutDuplicate;
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {PatientInterviewLocalDictation, resampleMono, appendWithoutDuplicate, createResampleState, textSuffixAfter};
    }
})(typeof window !== 'undefined' ? window : globalThis);
