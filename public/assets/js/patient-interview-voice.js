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
    const M02_PIPER_ERROR_CODES = new Set([
        'EMPTY_RESPONSE',
        'INVALID_RESPONSE',
        'INTERPRET_FAILED',
        'SAVE_FAILED',
        'READBACK_FAILED',
    ]);
    const M03_TOBACCO_INVALID_ERROR_CODE = 'M03_TOBACCO_ANSWER_AMBIGUOUS';
    const M03_TOBACCO_INVALID_ERROR_STABLE_ID = 'M03:error:tobacco-answer-ambiguous';

    class PatientInterviewVoiceController {
        constructor(options = {}) {
            this.speechApi = options.speechApi || null;
            this.utteranceConstructor = options.utteranceConstructor || global.SpeechSynthesisUtterance;
            this.onStateChange = typeof options.onStateChange === 'function' ? options.onStateChange : () => {};
            this.onError = typeof options.onError === 'function' ? options.onError : () => {};
            this.onVoicesChanged = typeof options.onVoicesChanged === 'function' ? options.onVoicesChanged : () => {};
            this.audioConstructor = options.audioConstructor || global.Audio;
            this.piperAudio = options.piperAudio && options.piperAudio.enabled === true && options.piperAudio.entries && typeof options.piperAudio.entries === 'object'
                ? options.piperAudio : {enabled: false, entries: {}};
            this.enabled = false;
            this.muted = false;
            this.state = this.supported() ? STATES.DISABLED : STATES.VOICE_UNAVAILABLE;
            this.rate = .95;
            this.pitch = 1;
            this.volume = 1;
            this.generation = 0;
            this.activeUtterance = null;
            this.activeAudio = null;
            this.activeAudioContext = null;
            this.activePreview = null;
            this.availableVoices = [];
            this.preferredVoiceIdentity = this.normalizeVoiceIdentity(options.preferredVoice || null);
            this.lastVoiceDiagnostic = null;
            this.voicesChangedRegistered = false;
            this.lastEvent = null;
            this.pendingEvents = null;
            this.pendingActivation = null;
            this.playbackTimer = null;
            this.automaticMode = false;
            this.lastHandledEventKey = null;
            this.registerVoicesChanged();
            this.refreshVoices();
            this.emit();
        }

        supported() {
            return Boolean(this.speechApi && typeof this.utteranceConstructor === 'function');
        }

        selectVoice() {
            let voices = [];
            try { voices = this.speechApi && typeof this.speechApi.getVoices === 'function' ? this.speechApi.getVoices() : []; } catch (error) {}
            voices = Array.isArray(voices) ? voices : [];
            this.availableVoices = voices.slice();
            const preferred = this.resolvePreferredVoice(voices);
            if (preferred) return preferred;
            return voices.find(voice => /^es-MX/i.test(voice.lang || ''))
                || voices.find(voice => /^es(-|_)/i.test(voice.lang || ''))
                || voices[0]
                || null;
        }

        normalizeVoiceIdentity(identity) {
            if (!identity || typeof identity !== 'object') return null;
            const voiceURI = String(identity.voiceURI || '').trim();
            const lang = String(identity.lang || '').trim();
            const name = String(identity.name || '').trim();
            if (!lang || (!voiceURI && !name)) return null;
            return {voiceURI, lang, name};
        }

        voiceIdentity(voice) {
            return voice ? this.normalizeVoiceIdentity({voiceURI: voice.voiceURI, lang: voice.lang, name: voice.name}) : null;
        }

        resolvePreferredVoice(voices = this.availableVoices) {
            const identity = this.preferredVoiceIdentity;
            if (!identity) return null;
            return this.resolveVoiceIdentity(identity, voices);
        }

        resolveVoiceIdentity(identity, voices = this.availableVoices) {
            if (!identity) return null;
            return voices.find(voice => {
                const candidate = this.voiceIdentity(voice);
                if (!candidate || candidate.lang !== identity.lang) return false;
                return identity.voiceURI ? candidate.voiceURI === identity.voiceURI : candidate.name === identity.name;
            }) || null;
        }

        setPreferredVoice(identity) {
            this.preferredVoiceIdentity = this.normalizeVoiceIdentity(identity);
            return this.getVoiceSelection();
        }

        getVoiceSelection() {
            const voices = this.availableVoices.slice();
            const applied = this.resolvePreferredVoice(voices);
            const effective = applied || voices.find(voice => /^es-MX/i.test(voice.lang || ''))
                || voices.find(voice => /^es(-|_)/i.test(voice.lang || '')) || voices[0] || null;
            return {
                preferred: this.preferredVoiceIdentity ? {...this.preferredVoiceIdentity} : null,
                available: Boolean(applied),
                applied: this.voiceIdentity(applied),
                effective: this.voiceIdentity(effective),
                fallbackReason: !effective ? 'NO_VOICE_AVAILABLE' : (this.preferredVoiceIdentity && !applied ? 'PREFERRED_VOICE_UNAVAILABLE' : null),
            };
        }

        getVoiceDiagnostic() {
            return this.lastVoiceDiagnostic ? {...this.lastVoiceDiagnostic} : null;
        }

        recordVoiceDiagnostic(voice, requested = this.preferredVoiceIdentity, context = 'clinical') {
            const identity = this.voiceIdentity(voice);
            const preferredFound = requested ? this.resolveVoiceIdentity(requested) : null;
            this.lastVoiceDiagnostic = {
                context,
                requested: requested ? {...requested} : null,
                effective: identity,
                fallbackReason: !identity ? 'NO_VOICE_AVAILABLE' : (requested && !preferredFound ? 'PREFERRED_VOICE_UNAVAILABLE' : null),
                rate: this.rate, pitch: this.pitch, volume: this.volume,
            };
        }

        registerVoicesChanged() {
            if (this.voicesChangedRegistered || !this.speechApi) return false;
            this.voiceChangeHandler = () => this.refreshVoices();
            if (typeof this.speechApi.addEventListener === 'function') {
                this.speechApi.addEventListener('voiceschanged', this.voiceChangeHandler);
                this.voicesChangedRegistered = true;
                return true;
            }
            if ('onvoiceschanged' in this.speechApi) {
                const previousHandler = this.speechApi.onvoiceschanged;
                this.speechApi.onvoiceschanged = event => {
                    try {
                        if (typeof previousHandler === 'function') previousHandler.call(this.speechApi, event);
                    } finally {
                        this.voiceChangeHandler(event);
                    }
                };
                this.voicesChangedRegistered = true;
                return true;
            }
            return false;
        }

        refreshVoices() {
            let voices = [];
            try {
                voices = typeof this.speechApi?.getVoices === 'function' ? this.speechApi.getVoices() : [];
            } catch (error) {
                voices = [];
            }
            this.availableVoices = Array.isArray(voices) ? voices.slice() : [];
            const inventory = this.voiceInventory();
            this.onVoicesChanged(inventory);
            this.emit();
            return inventory;
        }

        voiceInventory() {
            const occurrences = new Map();
            return this.availableVoices.map(voice => {
                const name = String(voice?.name || 'Voz sin nombre');
                const lang = String(voice?.lang || '');
                const voiceURI = String(voice?.voiceURI || '');
                const baseKey = `${voiceURI || name}|${lang}`;
                const occurrence = occurrences.get(baseKey) || 0;
                occurrences.set(baseKey, occurrence + 1);
                return {
                    key: `${baseKey}|${occurrence}`,
                    name,
                    lang,
                    voiceURI,
                    localService: typeof voice?.localService === 'boolean' ? voice.localService : null,
                    default: voice?.default === true,
                    voice,
                };
            });
        }

        voiceForKey(key) {
            return this.voiceInventory().find(entry => entry.key === key)?.voice || null;
        }

        preview(text, voice = null, options = {}) {
            const cleanText = String(text || '').trim();
            if (!this.supported() || this.muted || !cleanText) return false;
            this.notifyPreviewCancelled('replaced');
            this.generation += 1;
            const generation = this.generation;
            this.clearPlaybackTimer();
            this.pendingEvents = null;
            this.pendingActivation = null;
            try { this.speechApi.cancel(); } catch (error) {}
            this.clearActiveAudio(true);
            this.activeUtterance = null;
            this.setState(this.enabled ? STATES.READY : STATES.DISABLED);
            let utterance;
            try {
                utterance = new this.utteranceConstructor(cleanText);
                utterance.lang = voice?.lang || options.lang || 'es-MX';
                if (voice) utterance.voice = voice;
                const rate = Number(options.rate ?? this.rate);
                const pitch = Number(options.pitch ?? this.pitch);
                const volume = Number(options.volume ?? this.volume);
                utterance.rate = Number.isFinite(rate) && rate >= .5 && rate <= 2 ? rate : .95;
                utterance.pitch = Number.isFinite(pitch) && pitch >= 0 && pitch <= 2 ? pitch : 1;
                utterance.volume = Number.isFinite(volume) && volume >= 0 && volume <= 1 ? volume : 1;
            } catch (error) {
                options.onError?.(error);
                return false;
            }
            this.activeUtterance = utterance;
            this.activePreview = {generation, onCancel: options.onCancel};
            this.recordVoiceDiagnostic(voice, this.voiceIdentity(voice), 'preview');
            this.lastVoiceDiagnostic.rate = utterance.rate;
            this.lastVoiceDiagnostic.pitch = utterance.pitch;
            this.lastVoiceDiagnostic.volume = utterance.volume;
            this.emit();
            utterance.onstart = () => {
                if (generation !== this.generation) return;
                this.setState(STATES.SPEAKING);
                options.onStart?.();
            };
            utterance.onend = () => {
                if (generation !== this.generation) return;
                this.activeUtterance = null;
                this.activePreview = null;
                this.setState(this.muted ? STATES.MUTED : (this.enabled ? STATES.READY : STATES.DISABLED));
                options.onEnd?.();
            };
            utterance.onerror = error => {
                if (generation !== this.generation) return;
                this.activeUtterance = null;
                this.activePreview = null;
                this.setState(STATES.VOICE_ERROR);
                (typeof options.onError === 'function' ? options.onError : this.onError)(error);
            };
            try {
                this.speechApi.speak(utterance);
                return true;
            } catch (error) {
                if (generation === this.generation) {
                    this.activeUtterance = null;
                    this.activePreview = null;
                    this.setState(STATES.VOICE_ERROR);
                    options.onError?.(error);
                }
                return false;
            }
        }

        notifyPreviewCancelled(reason) {
            if (!this.activePreview) return;
            const preview = this.activePreview;
            this.activePreview = null;
            preview.onCancel?.(reason);
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
                voiceSelection: this.getVoiceSelection(),
                voiceDiagnostic: this.getVoiceDiagnostic(),
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

        handleEvent(event, options = {}) {
            if (!this.enabled || this.muted || SILENT_COORDINATOR_EVENTS.has(String(event?.type || ''))) return false;
            const key = this.eventKey(event);
            if (key && key === this.lastHandledEventKey) return false;
            if (key) this.lastHandledEventKey = key;
            return this.speakEvent(event, {
                automatic: options.automatic === undefined ? this.automaticMode : options.automatic === true,
                userGesture: options.userGesture === true,
            });
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
            return [event.type, event.stable_id || '', event.revision ?? '', event.operation_generation ?? '', event.question_stable_id || '', String(event.text || '')].join('|');
        }

        piperAudioEntryFor(event) {
            if (!this.piperAudio.enabled || typeof this.audioConstructor !== 'function') return null;
            if (event?.type === 'ERROR') {
                const errorCode = String(event.error_code || '');
                const questionStableId = String(event.question_stable_id || '');
                if (errorCode === M03_TOBACCO_INVALID_ERROR_CODE && event.module_id === 'M03') {
                    const errorEntry = this.piperAudio.m03_tobacco?.errors?.[errorCode];
                    if (!errorEntry || errorEntry.module_id !== 'M03' || errorEntry.error_code !== errorCode
                        || errorEntry.stable_id !== M03_TOBACCO_INVALID_ERROR_STABLE_ID
                        || errorEntry.question_stable_id !== 'M03:question:1'
                        || questionStableId !== errorEntry.question_stable_id
                        || event.stable_id !== errorEntry.stable_id
                        || typeof errorEntry.text !== 'string' || errorEntry.text !== String(event.text || '')
                        || !/^[a-f0-9]{64}$/.test(String(errorEntry.text_sha256 || ''))
                        || !/^[a-f0-9]{64}$/.test(String(errorEntry.audio_sha256 || ''))
                        || typeof errorEntry.src !== 'string' || !errorEntry.src) return null;
                    try {
                        const source = new URL(errorEntry.src, global.location?.href);
                        if (!global.location?.origin || source.origin !== global.location.origin) return null;
                    } catch (error) {
                        return null;
                    }
                    return errorEntry;
                }
                const sharedEmptyResponse = errorCode === 'EMPTY_RESPONSE'
                    && event.module_id === 'M03'
                    && /^M03:question:\d+$/.test(questionStableId);
                const moduleQuestionMatches = event.module_id === 'M02'
                    ? /^M02:family:[a-z0-9-]+$/.test(questionStableId)
                    : sharedEmptyResponse;
                if (!M02_PIPER_ERROR_CODES.has(errorCode) || !moduleQuestionMatches
                    || event.stable_id !== `M02:error:${errorCode}`) return null;
                const errorEntry = this.piperAudio.errors?.[errorCode];
                if (!errorEntry || errorEntry.module_id !== 'M02' || errorEntry.error_code !== errorCode
                    || (event.module_id === 'M03' && !Array.isArray(errorEntry.shared_modules))
                    || (event.module_id === 'M03' && !errorEntry.shared_modules.includes('M03'))
                    || errorEntry.stable_id !== event.stable_id
                    || typeof errorEntry.text !== 'string' || errorEntry.text !== String(event.text || '')
                    || !/^[a-f0-9]{64}$/.test(String(errorEntry.text_sha256 || ''))
                    || !/^[a-f0-9]{64}$/.test(String(errorEntry.audio_sha256 || ''))
                    || typeof errorEntry.src !== 'string' || !errorEntry.src) return null;
                try {
                    const source = new URL(errorEntry.src, global.location?.href);
                    if (!global.location?.origin || source.origin !== global.location.origin) return null;
                } catch (error) {
                    return null;
                }
                return errorEntry;
            }
            if (event?.type === 'ACTION') {
                if (event.module_id !== 'M02' || event.action_id !== 'M02:action:finalize' || event.stable_id !== null) return null;
                const actionEntry = this.piperAudio.actions?.[event.action_id];
                if (!actionEntry || actionEntry.module_id !== 'M02' || actionEntry.action_id !== event.action_id
                    || actionEntry.stable_id !== null
                    || typeof actionEntry.text !== 'string' || actionEntry.text !== String(event.text || '')
                    || !/^[a-f0-9]{64}$/.test(String(actionEntry.text_sha256 || ''))
                    || !/^[a-f0-9]{64}$/.test(String(actionEntry.audio_sha256 || ''))
                    || typeof actionEntry.src !== 'string' || !actionEntry.src) return null;
                try {
                    const source = new URL(actionEntry.src, global.location?.href);
                    if (!global.location?.origin || source.origin !== global.location.origin) return null;
                } catch (error) {
                    return null;
                }
                return actionEntry;
            }
            if (event?.type === 'HELP' && event.module_id === 'M02'
                && event.action_id === 'M02:action:finalize' && event.stable_id === null) {
                const actionHelp = this.piperAudio.actions?.[event.action_id]?.help;
                if (!actionHelp || actionHelp.module_id !== 'M02' || actionHelp.action_id !== event.action_id
                    || actionHelp.stable_id !== null
                    || typeof actionHelp.text !== 'string' || actionHelp.text !== String(event.text || '')
                    || !/^[a-f0-9]{64}$/.test(String(actionHelp.text_sha256 || ''))
                    || !/^[a-f0-9]{64}$/.test(String(actionHelp.audio_sha256 || ''))
                    || typeof actionHelp.src !== 'string' || !actionHelp.src) return null;
                try {
                    const source = new URL(actionHelp.src, global.location?.href);
                    if (!global.location?.origin || source.origin !== global.location.origin) return null;
                } catch (error) {
                    return null;
                }
                return actionHelp;
            }
            if (!['QUESTION', 'HELP'].includes(event?.type)) return null;
            const stableId = String(event?.stable_id || '');
            const isM03Question = /^M03:question:[1-9]\d*$/.test(stableId);
            const moduleId = isM03Question ? 'M03' : 'M02';
            if (isM03Question && event.module_id && event.module_id !== 'M03') return null;
            if (!isM03Question && Object.prototype.hasOwnProperty.call(event, 'module_id') && event.module_id !== 'M02') return null;
            if (!isM03Question && !/^M02:family:/.test(stableId)) return null;
            const entries = isM03Question
                ? (this.piperAudio.m03?.entries || this.piperAudio.m03_tobacco?.entries)
                : this.piperAudio.entries;
            const mappedEntry = entries?.[stableId];
            const entry = event.type === 'HELP' ? mappedEntry?.help : mappedEntry;
            if (!entry || entry.module_id !== moduleId || entry.stable_id !== stableId
                || typeof entry.text !== 'string' || entry.text !== String(event.text || '')
                || !/^[a-f0-9]{64}$/.test(String(entry.text_sha256 || ''))
                || !/^[a-f0-9]{64}$/.test(String(entry.audio_sha256 || ''))
                || typeof entry.src !== 'string' || !entry.src) return null;
            try {
                const source = new URL(entry.src, global.location?.href);
                if (!global.location?.origin || source.origin !== global.location.origin) return null;
            } catch (error) {
                return null;
            }
            return entry;
        }

        requiresM02PiperAudio(event) {
            const target = String(event?.stable_id || '');
            const isSharedM03Error = event?.type === 'ERROR' && event?.module_id === 'M03';
            const m02Identity = !isSharedM03Error && (event?.module_id === 'M02'
                || /^M02:(?:family|error):/.test(target)
                || event?.action_id === 'M02:action:finalize');
            return this.piperAudio.enabled === true && m02Identity
                && ['QUESTION', 'HELP', 'ACTION', 'ERROR', 'INTERVIEW_STARTUP'].includes(String(event.type || ''));
        }

        requiresM03TobaccoPiperAudio(event) {
            const stableId = String(event?.stable_id || '');
            if (event?.type === 'ERROR' && event?.module_id === 'M03'
                && event?.error_code === M03_TOBACCO_INVALID_ERROR_CODE) return this.piperAudio.m03_tobacco?.enabled === true;
            if (!['QUESTION', 'HELP'].includes(String(event?.type || ''))
                || !/^M03:question:[1-9]\d*$/.test(stableId)
                || (event.module_id && event.module_id !== 'M03')) return false;
            if (this.piperAudio.m03?.enabled === true) return true;
            return /^M03:question:[1-5]$/.test(stableId)
                && this.piperAudio.m03_tobacco?.enabled === true;
        }

        requiresConfiguredPiperAudio(event) {
            return this.requiresM02PiperAudio(event) || this.requiresM03TobaccoPiperAudio(event);
        }

        piperEntryFailureReason(event) {
            if (!this.piperAudio.enabled) return 'PIPER_AUDIO_DISABLED';
            if (typeof this.audioConstructor !== 'function') return 'PIPER_AUDIO_UNSUPPORTED';
            let candidate = null;
            if (event?.type === 'ACTION') candidate = this.piperAudio.actions?.[event.action_id];
            else if (event?.type === 'HELP' && event.action_id) candidate = this.piperAudio.actions?.[event.action_id]?.help;
            else if (event?.type === 'ERROR') candidate = event.module_id === 'M03'
                ? this.piperAudio.m03_tobacco?.errors?.[event.error_code]
                : this.piperAudio.errors?.[event.error_code];
            else {
                const stableId = String(event?.stable_id || '');
                const isM03Question = /^M03:question:[1-9]\d*$/.test(stableId);
                const mapped = isM03Question
                    ? (this.piperAudio.m03?.entries?.[stableId] || this.piperAudio.m03_tobacco?.entries?.[stableId])
                    : this.piperAudio.entries?.[stableId];
                candidate = event?.type === 'HELP' ? mapped?.help : mapped;
            }
            return candidate ? 'PIPER_AUDIO_ENTRY_INVALID' : 'PIPER_AUDIO_ENTRY_MISSING';
        }

        failPiperAudio(event, generation, reason) {
            if (generation !== this.generation) return false;
            this.clearPlaybackTimer();
            this.clearActiveAudio(true);
            this.activeUtterance = null;
            this.pendingActivation = null;
            if (event.type !== 'ERROR' && !(event.type === 'HELP' && ['M02', 'M03'].includes(event.module_id))) this.lastEvent = event;
            this.lastVoiceDiagnostic = {
                context: 'clinical', source: 'unavailable',
                requested: {name: 'Piper Claude', lang: 'es-MX', voiceURI: 'piper-claude'},
                effective: null, fallbackReason: reason,
                rate: this.rate, pitch: 1, volume: 1,
            };
            this.setState(STATES.VOICE_ERROR);
            this.onError({code: 'PIPER_AUDIO_UNAVAILABLE', reason});
            return false;
        }

        clearActiveAudio(resetSource = true) {
            const audio = this.activeAudio;
            this.activeAudio = null;
            this.activeAudioContext = null;
            if (!audio) return;
            audio.onplaying = null;
            audio.onplay = null;
            audio.onended = null;
            audio.onerror = null;
            try { audio.pause(); } catch (error) {}
            if (resetSource) {
                try { audio.removeAttribute?.('src'); audio.load?.(); } catch (error) {}
            }
        }

        completePlayback(generation, options = {}) {
            if (generation !== this.generation) return false;
            this.clearPlaybackTimer();
            this.activeUtterance = null;
            this.clearActiveAudio(false);
            this.setState(this.muted ? STATES.MUTED : (this.enabled ? STATES.READY : STATES.DISABLED));
            if (typeof options.after === 'function') options.after();
            const pending = this.pendingEvents;
            this.pendingEvents = null;
            if (pending && this.enabled && !this.muted) this.speakSequence(pending);
            return true;
        }

        watchPiperAudioStartup(audio, context) {
            this.clearPlaybackTimer();
            if (typeof global.setTimeout !== 'function') return;
            this.playbackTimer = global.setTimeout(() => {
                this.playbackTimer = null;
                if (context.generation === this.generation && this.activeAudio === audio && this.state === STATES.AUDIO_PENDING) {
                    context.handleFailure('PIPER_AUDIO_START_TIMEOUT');
                }
            }, 8000);
        }

        startPiperAudio(event, entry, options, generation, automatic) {
            let audio;
            try {
                audio = new this.audioConstructor();
                audio.preload = 'auto';
                audio.src = entry.src;
                audio.playbackRate = this.rate;
                if ('preservesPitch' in audio) audio.preservesPitch = true;
            } catch (error) {
                if (this.requiresConfiguredPiperAudio(event)) return this.failPiperAudio(event, generation, 'PIPER_AUDIO_UNAVAILABLE');
                return this.speakEvent(event, {...options, skipPiper: true});
            }

            const context = {event, entry, options: {...options, skipPiper: true}, automatic, generation, fallbackStarted: false};
            const fallbackToSpeech = reason => {
                if (context.fallbackStarted || generation !== this.generation) return;
                context.fallbackStarted = true;
                this.clearActiveAudio(true);
                this.lastVoiceDiagnostic = {
                    context: 'clinical', source: 'web_speech_fallback',
                    requested: {name: 'Piper Claude', lang: 'es-MX', voiceURI: 'piper-claude'},
                    effective: null, fallbackReason: reason,
                    rate: this.rate, pitch: this.pitch, volume: this.volume,
                };
                this.speakEvent(event, context.options);
                if (this.lastVoiceDiagnostic) {
                    this.lastVoiceDiagnostic.source = 'web_speech_fallback';
                    this.lastVoiceDiagnostic.fallbackReason = reason;
                    this.emit();
                }
            };
            const handleFailure = reason => this.requiresConfiguredPiperAudio(event)
                ? this.failPiperAudio(event, generation, reason)
                : fallbackToSpeech(reason);
            const blockPiperAutoplay = () => {
                if (generation !== this.generation) return false;
                this.clearActiveAudio(true);
                this.lastVoiceDiagnostic = {
                    context: 'clinical', source: 'blocked_autoplay',
                    requested: {name: 'Piper Claude', lang: 'es-MX', voiceURI: 'piper-claude'},
                    effective: null, fallbackReason: 'PIPER_AUDIO_AUTOPLAY_BLOCKED',
                    rate: this.rate, pitch: 1, volume: 1,
                };
                this.blockPlayback(event, options.after, {
                    source: 'blocked_autoplay',
                    fallbackReason: 'PIPER_AUDIO_AUTOPLAY_BLOCKED',
                });
                return false;
            };
            const handlePlayRejection = error => {
                if (this.requiresConfiguredPiperAudio(event) && automatic && options.userGesture !== true
                    && String(error?.name || '') === 'NotAllowedError') {
                    blockPiperAutoplay();
                    return;
                }
                handleFailure('PIPER_AUDIO_PLAY_REJECTED');
            };
            context.handleFailure = handleFailure;
            this.activeAudio = audio;
            this.activeAudioContext = {...context, fallbackToSpeech, handleFailure};
            this.activeUtterance = null;
            if (event.type !== 'ERROR' && !(event.type === 'HELP' && ['M02', 'M03'].includes(event.module_id))) this.lastEvent = event;
            this.lastVoiceDiagnostic = {
                context: 'clinical', source: 'piper_audio',
                requested: {name: 'Piper Claude', lang: 'es-MX', voiceURI: 'piper-claude'},
                effective: {name: 'Piper Claude', lang: 'es-MX', voiceURI: 'piper-claude'},
                fallbackReason: null, rate: this.rate, pitch: 1, volume: 1,
            };
            this.setState(STATES.AUDIO_PENDING);

            const beginSpeaking = () => {
                if (generation === this.generation && this.activeAudio === audio && this.state !== STATES.PAUSED) {
                    this.clearPlaybackTimer();
                    this.setState(STATES.SPEAKING);
                }
            };
            audio.onplaying = beginSpeaking;
            audio.onplay = beginSpeaking;
            audio.onended = () => this.completePlayback(generation, options);
            audio.onerror = () => handleFailure('PIPER_AUDIO_LOAD_OR_DECODE_FAILED');
            this.watchPiperAudioStartup(audio, this.activeAudioContext);
            try {
                const result = audio.play();
                if (result && typeof result.then === 'function') {
                    result.then(beginSpeaking).catch(handlePlayRejection);
                } else if (audio.paused === false) {
                    beginSpeaking();
                }
            } catch (error) {
                handlePlayRejection(error);
            }
            return true;
        }

        speakEvent(event, options = {}) {
            if (!event || !String(event.text || '').trim()) return false;
            if (!this.supported()) {
                this.setState(STATES.VOICE_UNAVAILABLE);
                return false;
            }
            if (this.muted || (!this.enabled && options.force !== true)) return false;
            const handledEventKey = this.eventKey(event);
            if (handledEventKey) this.lastHandledEventKey = handledEventKey;
            this.notifyPreviewCancelled('replaced');
            this.pendingEvents = null;
            this.clearPlaybackTimer();
            const generation = ++this.generation;
            const automatic = options.automatic === true || (this.automaticMode && options.userGesture !== true);
            try { this.speechApi.cancel(); } catch (error) {}
            this.clearActiveAudio(true);
            const piperEntry = options.skipPiper === true ? null : this.piperAudioEntryFor(event);
            if (piperEntry) return this.startPiperAudio(event, piperEntry, options, generation, automatic);
            if (options.skipPiper !== true && this.requiresConfiguredPiperAudio(event)) {
                return this.failPiperAudio(event, generation, this.piperEntryFailureReason(event));
            }
            const utterance = new this.utteranceConstructor(String(event.text));
            const voice = this.selectVoice();
            this.recordVoiceDiagnostic(voice, this.preferredVoiceIdentity, 'clinical');
            utterance.lang = voice?.lang || 'es-MX';
            if (voice) utterance.voice = voice;
            utterance.rate = this.rate;
            utterance.pitch = this.pitch;
            utterance.volume = this.volume;
            let playbackStarted = false;
            this.activeUtterance = utterance;
            if (event.type !== 'ERROR' && !(event.type === 'HELP' && event.module_id === 'M02')) this.lastEvent = event;
            this.setState(automatic ? STATES.AUDIO_PENDING : STATES.SPEAKING);
            utterance.onend = () => this.completePlayback(generation, options);
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

        blockPlayback(event, after = null, diagnostic = null) {
            this.generation += 1;
            this.clearPlaybackTimer();
            this.pendingEvents = null;
            this.pendingActivation = {event, after};
            try { this.speechApi?.cancel(); } catch (error) {}
            this.activeUtterance = null;
            if (diagnostic) {
                this.lastVoiceDiagnostic = {
                    context: 'clinical', source: diagnostic.source || 'blocked_autoplay',
                    requested: {name: 'Piper Claude', lang: 'es-MX', voiceURI: 'piper-claude'},
                    effective: null, fallbackReason: diagnostic.fallbackReason || 'PIPER_AUDIO_AUTOPLAY_BLOCKED',
                    rate: this.rate, pitch: 1, volume: 1,
                };
            }
            this.setState(STATES.AUDIO_BLOCKED);
            this.onError({code: 'AUDIO_BLOCKED'});
        }

        repeat(userGesture = false) {
            return this.lastEvent ? this.speakEvent(this.lastEvent, {force: true, userGesture}) : false;
        }

        pause() {
            if (this.activeAudio && (this.state === STATES.AUDIO_PENDING || !this.activeAudio.paused)) {
                this.clearPlaybackTimer();
                try { this.activeAudio.pause(); } catch (error) { return false; }
                this.setState(STATES.PAUSED);
                return true;
            }
            if (!this.speechApi || !this.speechApi.speaking) return false;
            try { this.speechApi.pause(); } catch (error) { return false; }
            this.setState(STATES.PAUSED);
            return true;
        }

        resume() {
            if (this.activeAudio && this.state === STATES.PAUSED) {
                const audio = this.activeAudio;
                const context = this.activeAudioContext;
                this.watchPiperAudioStartup(audio, context);
                try {
                    const result = audio.play();
                    if (result && typeof result.then === 'function') {
                        result.then(() => {
                            if (context?.generation === this.generation && this.activeAudio === audio && this.state === STATES.PAUSED) this.setState(STATES.SPEAKING);
                        }).catch(() => context?.handleFailure?.('PIPER_AUDIO_RESUME_REJECTED'));
                    } else {
                        this.setState(STATES.SPEAKING);
                    }
                    return true;
                } catch (error) {
                    context?.handleFailure?.('PIPER_AUDIO_RESUME_REJECTED');
                    return true;
                }
            }
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
            if (Number.isFinite(next) && next >= .5 && next <= 2) {
                this.rate = next;
                if (this.activeAudio) {
                    try { this.activeAudio.playbackRate = next; } catch (error) {}
                }
            }
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
            this.notifyPreviewCancelled('cancelled');
            this.generation += 1;
            this.clearPlaybackTimer();
            this.pendingEvents = null;
            this.pendingActivation = null;
            try { this.speechApi?.cancel(); } catch (error) {}
            this.clearActiveAudio(true);
            this.activeUtterance = null;
            this.setState(this.muted ? STATES.MUTED : (this.enabled ? STATES.READY : STATES.DISABLED));
            return true;
        }
    }

    global.PatientInterviewVoiceController = PatientInterviewVoiceController;
    global.PatientInterviewVoiceStates = STATES;
})(window);
