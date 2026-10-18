(function (global) {
    'use strict';

    const STATES = Object.freeze({
        INITIALIZING: 'INITIALIZING',
        READY: 'READY',
        ASKING: 'ASKING',
        WAITING_FOR_RESPONSE: 'WAITING_FOR_RESPONSE',
        INTERPRETING: 'INTERPRETING',
        CLARIFICATION_REQUIRED: 'CLARIFICATION_REQUIRED',
        CONFIRMATION_REQUIRED: 'CONFIRMATION_REQUIRED',
        SAVING: 'SAVING',
        SAVE_FAILED: 'SAVE_FAILED',
        RESPONSE_SAVED: 'RESPONSE_SAVED',
        NAVIGATING: 'NAVIGATING',
        PAUSED: 'PAUSED',
        RESUMING: 'RESUMING',
        MODULE_COMPLETED: 'MODULE_COMPLETED',
        MODULE_BLOCKED: 'MODULE_BLOCKED',
        SESSION_EXPIRED: 'SESSION_EXPIRED',
    });

    const TRANSITIONS = {
        INITIALIZING: ['READY', 'SESSION_EXPIRED'],
        // An explicit module-finalization action may be submitted without an
        // active question. It still enters the normal SAVING/readback guard;
        // it is never an implicit GET or an automatic transition.
        READY: ['ASKING', 'SAVING', 'PAUSED', 'RESUMING', 'MODULE_BLOCKED', 'SESSION_EXPIRED'],
        ASKING: ['WAITING_FOR_RESPONSE', 'INTERPRETING', 'SAVING', 'NAVIGATING', 'PAUSED', 'SESSION_EXPIRED', 'MODULE_BLOCKED'],
        WAITING_FOR_RESPONSE: ['INTERPRETING', 'SAVING', 'CONFIRMATION_REQUIRED', 'CLARIFICATION_REQUIRED', 'NAVIGATING', 'PAUSED', 'SESSION_EXPIRED'],
        INTERPRETING: ['WAITING_FOR_RESPONSE', 'CONFIRMATION_REQUIRED', 'CLARIFICATION_REQUIRED', 'SAVE_FAILED', 'PAUSED', 'SESSION_EXPIRED'],
        CLARIFICATION_REQUIRED: ['WAITING_FOR_RESPONSE', 'INTERPRETING', 'PAUSED', 'SESSION_EXPIRED'],
        CONFIRMATION_REQUIRED: ['SAVING', 'WAITING_FOR_RESPONSE', 'PAUSED', 'NAVIGATING', 'SESSION_EXPIRED'],
        SAVING: ['RESPONSE_SAVED', 'SAVE_FAILED', 'PAUSED', 'SESSION_EXPIRED'],
        SAVE_FAILED: ['CONFIRMATION_REQUIRED', 'WAITING_FOR_RESPONSE', 'SAVING', 'PAUSED', 'SESSION_EXPIRED'],
        RESPONSE_SAVED: ['NAVIGATING', 'ASKING', 'MODULE_COMPLETED', 'PAUSED', 'SESSION_EXPIRED'],
        NAVIGATING: ['ASKING', 'WAITING_FOR_RESPONSE', 'MODULE_COMPLETED', 'MODULE_BLOCKED', 'PAUSED', 'SESSION_EXPIRED'],
        PAUSED: ['RESUMING', 'SESSION_EXPIRED'],
        RESUMING: ['ASKING', 'WAITING_FOR_RESPONSE', 'READY', 'MODULE_BLOCKED', 'SESSION_EXPIRED'],
        MODULE_COMPLETED: ['NAVIGATING', 'ASKING', 'PAUSED', 'SESSION_EXPIRED'],
        MODULE_BLOCKED: ['READY', 'RESUMING', 'PAUSED', 'SESSION_EXPIRED'],
        SESSION_EXPIRED: ['RESUMING', 'READY'],
    };

    const jsonResponse = payload => {
        const body = JSON.stringify(payload);
        if (typeof global.Response === 'function') {
            return new global.Response(body, {status: 409, headers: {'Content-Type': 'application/json'}});
        }
        return {ok: false, status: 409, json: async () => payload};
    };

    class PatientInterviewCoordinator {
        constructor(options) {
            this.contract = options?.contract || {};
            this.patientId = Number(options?.patientId || 0);
            this.onStateChange = typeof options?.onStateChange === 'function' ? options.onStateChange : () => {};
            this.onVoiceEvent = typeof options?.onVoiceEvent === 'function' ? options.onVoiceEvent : () => {};
            const fetcher = options?.fetcher || global.fetch;
            this.fetcher = typeof fetcher === 'function' ? fetcher.bind(global) : () => Promise.reject(new Error('El navegador no dispone de fetch.'));
            this.state = STATES.INITIALIZING;
            this.currentTarget = null;
            this.currentContext = null;
            this.revision = 0;
            this.sequence = 0;
            this.activeOperation = null;
            this.paused = false;
            this.history = [];
            this.transition(STATES.READY, {reason: 'contract_loaded'});
        }

        questionContract(target = this.currentTarget) {
            const index = this.contract?.question_index || {};
            const key = this.targetKey(target);
            if (key && index[key]) return index[key];
            // A question-like target must resolve exactly. Falling back to the
            // first question here can narrate another clinical theme.
            if (key && key.includes(':') && key !== 'interview:current') return null;
            const moduleId = this.moduleContractId(key);
            const module = this.contract?.modules?.[moduleId];
            return module?.questions?.[0] || null;
        }

        stableIdForQuestion(moduleId, questionId) {
            const expectedModule = String(moduleId || '').trim();
            const expectedQuestion = String(questionId ?? '').trim();
            if (!expectedModule || !expectedQuestion) return null;
            const question = Object.values(this.contract?.question_index || {}).find(candidate => candidate
                && String(candidate.module_id || '').trim() === expectedModule
                && String(candidate.question_id ?? '').trim() === expectedQuestion);
            return question?.stable_id || null;
        }

        contextFor(target = this.currentTarget) {
            const key = this.targetKey(target);
            const question = this.questionContract(key);
            const moduleId = question?.module_id || this.moduleContractId(key);
            const module = this.contract?.modules?.[moduleId] || null;
            return {
                module_id: moduleId || null,
                theme_id: question?.theme_id ?? null,
                theme: question?.theme ?? null,
                question_id: question?.question_id ?? null,
                stable_id: question?.stable_id || (key.includes(':') && key !== 'interview:current' ? key : null),
                version: question?.version ?? module?.version ?? this.contract?.version ?? null,
                revision: this.revision,
                operation_generation: this.sequence,
                visible_text: question?.visible_text || null,
                instruction: question?.instruction || null,
                help: question?.help || null,
            };
        }

        moduleContractId(target = this.currentTarget) {
            const raw = String(target || '').split(':')[0];
            return raw === 'M09-MAP' ? 'M09' : raw;
        }

        voiceEvent(type, target = this.currentTarget, notify = true) {
            const question = this.questionContract(target);
            const key = this.targetKey(target);
            const questionLike = key.includes(':') && key !== 'interview:current';
            const module = this.contract?.modules?.[this.moduleContractId(key)] || null;
            const questionVoice = question?.voice || {};
            const moduleVoice = module?.voice || {};
            const contractVoice = this.contract?.voice || {};
            const aliases = type === 'COMPLETION' ? ['COMPLETION', 'MODULE_COMPLETED'] : [type];
            const text = aliases.map(voiceKey => questionVoice[voiceKey] || (!questionLike ? moduleVoice[voiceKey] : null) || contractVoice[voiceKey]).find(Boolean) || null;
            const event = {
                type,
                stable_id: question?.stable_id || target || null,
                text,
                state: this.state,
                patient_id: this.patientId,
                context: this.contextFor(target),
                revision: this.revision,
                operation_generation: this.sequence,
            };
            if (notify) this.onVoiceEvent(event);
            return event;
        }

        transition(next, metadata = {}) {
            const allowed = TRANSITIONS[this.state] || [];
            if (this.state !== next && !allowed.includes(next)) return false;
            const previous = this.state;
            this.state = next;
            const event = {previous, state: next, metadata: {...metadata}, revision: this.revision};
            this.history.push(event);
            if (this.history.length > 40) this.history.shift();
            this.onStateChange(event);
            if (next === STATES.SAVING || next === STATES.MODULE_BLOCKED || next === STATES.MODULE_COMPLETED) {
                this.voiceEvent(next, this.currentTarget);
            }
            return true;
        }

        targetKey(target) {
            if (typeof target === 'string' && target !== '') return target;
            if (target && typeof target === 'object') {
                if (target.stableId) return String(target.stableId);
                if (target.moduleId) return `${target.moduleId}:${target.questionId || 'stage'}`;
            }
            return 'interview:current';
        }

        initialInterviewState(target = this.currentTarget) {
            const progress = this.contract?.interview_progress;
            if (!progress || progress.has_confirmed_responses !== false) return 'RESUME';
            const key = this.targetKey(target);
            const firstModule = String(progress.first_pending_module_id || '');
            const firstStable = String(progress.first_pending_stable_id || '');
            if (!firstModule && !firstStable) return 'RESUME';
            return key === firstModule || key === firstStable ? 'FIRST_START' : 'RESUME';
        }

        setTarget(target) {
            const key = this.targetKey(target);
            if (this.currentTarget === key) return key;
            this.invalidate('target_changed');
            this.currentTarget = key;
            this.currentContext = this.contextFor(key);
            if (this.state === STATES.RESPONSE_SAVED || this.state === STATES.NAVIGATING) {
                this.transition(STATES.NAVIGATING, {target: key});
            }
            if (this.state === STATES.READY || this.state === STATES.RESUMING || this.state === STATES.NAVIGATING || this.state === STATES.MODULE_COMPLETED) {
                this.transition(STATES.ASKING, {target: key});
            } else if (this.state === STATES.MODULE_BLOCKED) {
                this.onStateChange({previous: this.state, state: this.state, metadata: {target: key}, revision: this.revision});
            }
            this.voiceEvent('QUESTION', key);
            return key;
        }

        invalidate(reason = 'invalidated') {
            this.revision += 1;
            if (this.activeOperation?.controller) {
                try { this.activeOperation.controller.abort(); } catch (error) {}
            }
            this.activeOperation = null;
            this.onStateChange({previous: this.state, state: this.state, metadata: {reason}, revision: this.revision});
        }

        beginOperation(action) {
            if (this.paused || this.activeOperation) return null;
            const operation = {
                id: `conversation-${++this.sequence}`,
                action,
                target: this.currentTarget || 'interview:current',
                revision: this.revision,
                controller: typeof global.AbortController === 'function' ? new global.AbortController() : null,
            };
            this.activeOperation = operation;
            const next = action === 'save' ? STATES.SAVING : action === 'interpret' ? STATES.INTERPRETING : STATES.WAITING_FOR_RESPONSE;
            if (!this.transition(next, {operationId: operation.id})) {
                this.activeOperation = null;
                return null;
            }
            return operation;
        }

        isCurrent(operation) {
            return Boolean(operation && this.activeOperation === operation && operation.revision === this.revision && operation.target === (this.currentTarget || 'interview:current'));
        }

        settle(operation, response, payload) {
            if (!this.isCurrent(operation)) return false;
            this.activeOperation = null;
            if (response.status === 401 || response.status === 419) {
                this.transition(STATES.SESSION_EXPIRED, {operationId: operation.id});
                this.voiceEvent('SESSION_EXPIRED');
                return false;
            }
            if (!response.ok || payload?.resultado === false) {
                this.transition(operation.action === 'save' ? STATES.SAVE_FAILED : STATES.WAITING_FOR_RESPONSE, {operationId: operation.id, status: response.status});
                this.voiceEvent('ERROR');
                return false;
            }
            if (operation.action === 'save') {
                const hasReadback = payload?.readback === true
                    || payload?.record !== null && payload?.record !== undefined
                    || payload?.item !== null && payload?.item !== undefined
                    || payload?.evaluation !== null && payload?.evaluation !== undefined
                    || payload?.version !== null && payload?.version !== undefined;
                if (!hasReadback || payload?.readback === false) {
                    this.transition(STATES.SAVE_FAILED, {operationId: operation.id, reason: 'readback_required'});
                    this.voiceEvent('ERROR');
                    return false;
                }
                this.transition(STATES.RESPONSE_SAVED, {operationId: operation.id, readback: true});
                this.voiceEvent('SUCCESS');
                return true;
            }
            const proposal = payload?.proposal || {};
            if (proposal.clarification || proposal.needs_clarification || proposal.kind === 'clarification') {
                this.transition(STATES.CLARIFICATION_REQUIRED, {operationId: operation.id});
                this.voiceEvent('CLARIFICATION');
            } else if (proposal.direct_save === true && proposal.can_confirm === true) {
                this.transition(STATES.WAITING_FOR_RESPONSE, {operationId: operation.id, directSave: true});
            } else if (proposal.can_confirm === true) {
                this.transition(STATES.CONFIRMATION_REQUIRED, {operationId: operation.id});
                this.voiceEvent('CONFIRMATION');
            } else {
                this.transition(STATES.WAITING_FOR_RESPONSE, {operationId: operation.id});
                this.voiceEvent('QUESTION');
            }
            return true;
        }

        async request(action, url, init = {}) {
            const path = String(url || '');
            const normalizedAction = action === 'auto' ? (path.includes('/interpretar') ? 'interpret' : path.includes('/capturar') ? 'save' : 'read') : action;
            const operation = this.beginOperation(normalizedAction);
            if (!operation) return jsonResponse({resultado: false, code: this.paused ? 'COORDINATOR_PAUSED' : 'COORDINATOR_DUPLICATE_REQUEST', mensaje: this.paused ? 'La entrevista está pausada.' : 'Ya existe una operación en curso.'});
            const requestInit = {...init};
            if (operation.controller) requestInit.signal = operation.controller.signal;
            try {
                const response = await this.fetcher(url, requestInit);
                if (!this.isCurrent(operation)) return jsonResponse({resultado: false, code: 'COORDINATOR_STALE_RESPONSE', mensaje: 'La respuesta llegó después de cambiar de pregunta.'});
                const json = typeof response.json === 'function' ? response.json.bind(response) : async () => ({});
                response.json = async () => {
                    let payload;
                    try { payload = await json(); } catch (error) {
                        this.activeOperation = null;
                        this.transition(normalizedAction === 'save' ? STATES.SAVE_FAILED : STATES.WAITING_FOR_RESPONSE, {operationId: operation.id, reason: 'invalid_json'});
                        throw error;
                    }
                    this.settle(operation, response, payload);
                    return payload;
                };
                return response;
            } catch (error) {
                const stale = !this.isCurrent(operation) || error?.name === 'AbortError';
                if (this.isCurrent(operation)) {
                    this.activeOperation = null;
                    this.transition(normalizedAction === 'save' ? STATES.SAVE_FAILED : STATES.WAITING_FOR_RESPONSE, {operationId: operation.id, reason: 'network'});
                }
                if (stale) return jsonResponse({resultado: false, code: 'COORDINATOR_STALE_RESPONSE', mensaje: 'La respuesta llegó después de cambiar de pregunta.'});
                throw error;
            }
        }

        navigate(reason = 'confirmed_response') {
            if (this.state !== STATES.RESPONSE_SAVED && this.state !== STATES.ASKING && this.state !== STATES.WAITING_FOR_RESPONSE) return false;
            return this.transition(STATES.NAVIGATING, {reason});
        }

        goBack() {
            this.invalidate('previous_question');
            return this.transition(STATES.NAVIGATING, {reason: 'previous_question'});
        }

        pause() {
            if (this.state === STATES.SAVING || this.state === STATES.INTERPRETING) return false;
            this.paused = true;
            this.invalidate('paused');
            return this.transition(STATES.PAUSED, {reason: 'patient_requested'});
        }

        resume() {
            if (!this.paused && this.state !== STATES.SESSION_EXPIRED && this.state !== STATES.PAUSED) return false;
            this.paused = false;
            this.invalidate('resuming');
            if (!this.transition(STATES.RESUMING, {reason: 'patient_requested'})) return false;
            const resumed = this.transition(this.currentTarget ? STATES.ASKING : STATES.READY, {reason: 'server_state_reloaded'});
            if (resumed) this.voiceEvent('RESUME');
            return resumed;
        }

        markModuleBlocked(reason) {
            this.invalidate(reason || 'module_blocked');
            return this.transition(STATES.MODULE_BLOCKED, {reason: reason || 'module_blocked'});
        }

        snapshot() {
            return {state: this.state, currentTarget: this.currentTarget, context: this.currentContext || this.contextFor(this.currentTarget), revision: this.revision, paused: this.paused, activeOperation: this.activeOperation ? {id: this.activeOperation.id, action: this.activeOperation.action, target: this.activeOperation.target} : null};
        }
    }

    global.PatientInterviewCoordinator = PatientInterviewCoordinator;
    global.PatientInterviewCoordinatorStates = STATES;
})(window);
