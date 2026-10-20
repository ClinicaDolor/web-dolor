(function (global) {
    'use strict';

    const STATES = Object.freeze({
        DISABLED: 'DISABLED',
        READY: 'READY',
        SPEAKING: 'SPEAKING',
        PAUSED: 'PAUSED',
        MUTED: 'MUTED',
        AUDIO_PENDING: 'AUDIO_PENDING',
        AUDIO_BLOCKED: 'AUDIO_BLOCKED',
        VOICE_UNAVAILABLE: 'VOICE_UNAVAILABLE',
        VOICE_ERROR: 'VOICE_ERROR',
    });
    const SILENT_COORDINATOR_EVENTS = new Set(['SAVING', 'SUCCESS', 'ERROR', 'SESSION_EXPIRED']);

    class PatientInterviewVoiceController {
        constructor(options = {}) {
            this.speechApi = options.speechApi || null;
            this.utteranceConstructor = options.utteranceConstructor || global.SpeechSynthesisUtterance;
            this.onStateChange = typeof options.onStateChange === 'function' ? options.onStateChange : () => {};
            this.onError = typeof options.onError === 'function' ? options.onError : () => {};
            this.enabled = false;
            this.muted = false;
            this.state = this.supported() ? STATES.DISABLED : STATES.VOICE_UNAVAILABLE;
            this.rate = .95;
            this.generation = 0;
            this.activeUtterance = null;
            this.lastEvent = null;
            this.pendingEvents = null;
            this.pendingActivation = null;
            this.playbackTimer = null;
            this.automaticMode = false;
            this.lastHandledEventKey = null;
            this.emit();
        }

        supported() {
            return Boolean(this.speechApi && typeof this.utteranceConstructor === 'function');
        }

        selectVoice() {
            const voices = this.speechApi && typeof this.speechApi.getVoices === 'function' ? this.speechApi.getVoices() : [];
            return voices.find(voice => /^es-MX/i.test(voice.lang || ''))
                || voices.find(voice => /^es(-|_)/i.test(voice.lang || ''))
                || voices[0]
                || null;
        }

        emit() {
            this.onStateChange(this.snapshot());
        }

        setState(state) {
            this.state = state;
            this.emit();
        }

        snapshot() {
            return {
                state: this.state,
                enabled: this.enabled,
                muted: this.muted,
                speaking: this.state === STATES.SPEAKING,
                paused: this.state === STATES.PAUSED,
                audioPending: this.state === STATES.AUDIO_PENDING,
                audioBlocked: this.state === STATES.AUDIO_BLOCKED,
                lastEvent: this.lastEvent,
                pendingEvent: this.pendingEvents?.[0] || null,
            };
        }

        activate(welcomeEvent, afterWelcome = null, options = {}) {
            if (!this.supported()) {
                this.setState(STATES.VOICE_UNAVAILABLE);
                return false;
            }
            this.enabled = true;
            this.automaticMode = options.automatic === true;
            if (options.preserveMute !== true) this.muted = false;
            this.setState(this.muted ? STATES.MUTED : STATES.READY);
            if (this.muted) return false;
            return this.speakEvent(welcomeEvent, {force: true, after: afterWelcome, automatic: this.automaticMode, userGesture: options.userGesture === true});
        }

        activateAutomatically(welcomeEvent, afterWelcome = null, questionEvent = null) {
            if (questionEvent) {
                return this.activateStartup(welcomeEvent, questionEvent, {automatic: true, preserveMute: true, after: afterWelcome});
            }
            return this.activate(welcomeEvent, afterWelcome, {automatic: true, preserveMute: true});
        }

        startupEvent(introEvent, questionEvent) {
            const introText = String(introEvent?.text || '').trim();
            const questionText = String(questionEvent?.text || '').trim();
            if (!introText || !questionText) return null;
            return {
                ...questionEvent,
                type: 'INTERVIEW_STARTUP',
                text: `${introText} ${questionText}`,
            };
        }

        reportError(error = {}) {
            this.pendingActivation = null;
            this.clearPlaybackTimer();
            this.activeUtterance = null;
            this.setState(STATES.VOICE_ERROR);
            this.onError(error);
            return false;
        }

        activateStartup(introEvent, questionEvent, options = {}) {
            const startup = this.startupEvent(introEvent, questionEvent);
            if (!startup) {
                return this.reportError({code: !String(questionEvent?.text || '').trim() ? 'QUESTION_TEXT_MISSING' : 'STARTUP_INTRO_MISSING'});
            }
            const after = () => {
                this.lastEvent = questionEvent;
                if (typeof options.after === 'function') options.after();
            };
            const activationOptions = {...options};
            delete activationOptions.after;
            return this.activate(startup, after, activationOptions);
        }

        speakStartup(introEvent, questionEvent, options = {}) {
            const startup = this.startupEvent(introEvent, questionEvent);
            if (!startup) {
                return this.reportError({code: !String(questionEvent?.text || '').trim() ? 'QUESTION_TEXT_MISSING' : 'STARTUP_INTRO_MISSING'});
            }
            const after = () => {
                this.lastEvent = questionEvent;
                if (typeof options.after === 'function') options.after();
            };
            const speakOptions = {...options, force: true, after};
            delete speakOptions.afterCallback;
            return this.speakEvent(startup, speakOptions);
        }

        unlock() {
            if (!this.enabled || !this.pendingActivation) return false;
            const pending = this.pendingActivation;
            this.pendingActivation = null;
            return this.speakEvent(pending.event, {force: true, after: pending.after, userGesture: true, automatic: false});
        }

        handleEvent(event) {
            if (!this.enabled || this.muted || SILENT_COORDINATOR_EVENTS.has(String(event?.type || ''))) return false;
            const key = this.eventKey(event);
            if (key && key === this.lastHandledEventKey) return false;
            if (key) this.lastHandledEventKey = key;
            return this.speakEvent(event, {automatic: this.automaticMode});
        }

        handleSequence(events) {
            if (!this.enabled || this.muted) return false;
            const sequence = [];
            (Array.isArray(events) ? events : []).forEach(event => {
                if (!event || !String(event.text || '').trim() || SILENT_COORDINATOR_EVENTS.has(String(event.type || ''))) return;
                const key = this.eventKey(event);
                if (key && key === this.lastHandledEventKey) return;
                if (key) this.lastHandledEventKey = key;
                sequence.push(event);
            });
            if (sequence.length === 0) return false;
            return this.speakSequence(sequence, {automatic: this.automaticMode});
        }

        speakSequence(events, options = {}) {
            const sequence = Array.isArray(events) ? events.filter(event => event && String(event.text || '').trim()) : [];
            if (sequence.length === 0) return false;
            let index = 0;
            const speakNext = () => {
                const event = sequence[index++];
                if (!event) return false;
                return this.speakEvent(event, {...options, after: () => { if (index < sequence.length) speakNext(); }});
            };
            return speakNext();
        }

        eventKey(event) {
            if (!event || !event.type) return null;
            return [event.type, event.stable_id || '', event.revision ?? '', event.operation_generation ?? '', String(event.text || '')].join('|');
        }

        speakEvent(event, options = {}) {
            if (!event || !String(event.text || '').trim()) return false;
            if (!this.supported()) {
                this.setState(STATES.VOICE_UNAVAILABLE);
                return false;
            }
            if (this.muted || (!this.enabled && options.force !== true)) return false;
            this.pendingEvents = null;
            this.clearPlaybackTimer();
            const generation = ++this.generation;
            const automatic = options.automatic === true || (this.automaticMode && options.userGesture !== true);
            try { this.speechApi.cancel(); } catch (error) {}
            const utterance = new this.utteranceConstructor(String(event.text));
            const voice = this.selectVoice();
            utterance.lang = voice?.lang || 'es-MX';
            if (voice) utterance.voice = voice;
            utterance.rate = this.rate;
            let playbackStarted = false;
            this.activeUtterance = utterance;
            this.lastEvent = event;
            this.setState(automatic ? STATES.AUDIO_PENDING : STATES.SPEAKING);
            utterance.onend = () => {
                if (generation !== this.generation) return;
                this.clearPlaybackTimer();
                this.activeUtterance = null;
                this.setState(this.muted ? STATES.MUTED : (this.enabled ? STATES.READY : STATES.DISABLED));
                if (typeof options.after === 'function') options.after();
                const pending = this.pendingEvents;
                this.pendingEvents = null;
                if (pending && this.enabled && !this.muted) this.speakSequence(pending);
            };
            utterance.onstart = () => {
                if (generation !== this.generation) return;
                playbackStarted = true;
                this.setState(STATES.SPEAKING);
            };
            utterance.onerror = error => {
                if (generation !== this.generation) return;
                this.clearPlaybackTimer();
                this.activeUtterance = null;
                if (automatic && !options.userGesture) {
                    this.blockPlayback(event, options.after);
                    return;
                }
                this.setState(STATES.VOICE_ERROR);
                this.onError(error);
            };
            try {
                this.speechApi.speak(utterance);
            } catch (error) {
                if (generation === this.generation) {
                    if (automatic && !options.userGesture) {
                        this.blockPlayback(event, options.after);
                    } else {
                        this.activeUtterance = null;
                        this.setState(STATES.VOICE_ERROR);
                        this.onError(error);
                    }
                }
                return false;
            }
            if (automatic) this.watchAutomaticPlayback(generation, event, options.after, () => playbackStarted);
            return true;
        }

        clearPlaybackTimer() {
            if (this.playbackTimer !== null && typeof global.clearTimeout === 'function') global.clearTimeout(this.playbackTimer);
            this.playbackTimer = null;
        }

        watchAutomaticPlayback(generation, event, after, hasStarted) {
            if (typeof global.setTimeout !== 'function') return;
            this.playbackTimer = global.setTimeout(() => {
                this.playbackTimer = null;
                if (generation !== this.generation || !this.activeUtterance || this.state !== STATES.AUDIO_PENDING) return;
                if (hasStarted()) return;
                this.blockPlayback(event, after);
            }, 1200);
        }

        blockPlayback(event, after = null) {
            this.generation += 1;
            this.clearPlaybackTimer();
            this.pendingEvents = null;
            this.pendingActivation = {event, after};
            try { this.speechApi?.cancel(); } catch (error) {}
            this.activeUtterance = null;
            this.setState(STATES.AUDIO_BLOCKED);
            this.onError({code: 'AUDIO_BLOCKED'});
        }

        repeat(userGesture = false) {
            return this.lastEvent ? this.speakEvent(this.lastEvent, {force: true, userGesture}) : false;
        }

        pause() {
            if (!this.speechApi || !this.speechApi.speaking) return false;
            try { this.speechApi.pause(); } catch (error) { return false; }
            this.setState(STATES.PAUSED);
            return true;
        }

        resume() {
            if (this.state === STATES.PAUSED && this.speechApi?.paused) {
                try { this.speechApi.resume(); } catch (error) { return false; }
                this.setState(STATES.SPEAKING);
                return true;
            }
            return false;
        }

        toggleMute() {
            this.muted = !this.muted;
            if (this.muted) this.cancel();
            else this.setState(this.enabled ? STATES.READY : STATES.DISABLED);
            return this.muted;
        }

        setRate(rate) {
            const next = Number(rate);
            if (Number.isFinite(next) && next >= .5 && next <= 2) this.rate = next;
            return this.rate;
        }

        setMuted(muted) {
            this.muted = muted === true;
            if (this.muted) this.cancel();
            else this.setState(this.enabled ? STATES.READY : STATES.DISABLED);
            return this.muted;
        }

        stop() {
            return this.cancel();
        }

        cancel() {
            this.generation += 1;
            this.clearPlaybackTimer();
            this.pendingEvents = null;
            this.pendingActivation = null;
            try { this.speechApi?.cancel(); } catch (error) {}
            this.activeUtterance = null;
            this.setState(this.muted ? STATES.MUTED : (this.enabled ? STATES.READY : STATES.DISABLED));
            return true;
        }
    }

    global.PatientInterviewVoiceController = PatientInterviewVoiceController;
    global.PatientInterviewVoiceStates = STATES;
})(window);
