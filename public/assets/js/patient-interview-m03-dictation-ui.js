(function (global) {
    'use strict';

    const CONTROL_SELECTOR = '[data-m03-dictation-control]';

    const canInitiate = ({featureEnabled = false, clientSupported = false, permissionDenied = false} = {}) =>
        Boolean(featureEnabled && clientSupported && permissionDenied !== true);

    const applyServerReadback = ({contract, cards = [], questionId, data} = {}) => {
        const numericQuestionId = Number(questionId);
        const eligibleIdsRaw = data?.eligible_question_ids;
        const editableIdsRaw = data?.editable_question_ids;
        if (!contract || !Number.isInteger(numericQuestionId) || numericQuestionId < 1
            || data?.readback !== true || !Array.isArray(eligibleIdsRaw) || !Array.isArray(editableIdsRaw)) {
            return {applied: false, reason: 'M03_READBACK_CONTRACT_INVALID'};
        }

        const module = contract.modules?.M03;
        if (!module || !Array.isArray(module.questions) || !module.state || !module.conversation_state) {
            return {applied: false, reason: 'M03_CONTRACT_NOT_FOUND'};
        }
        const eligibleIds = new Set(eligibleIdsRaw.map(Number).filter(id => Number.isInteger(id) && id > 0));
        const editableIds = new Set(editableIdsRaw.map(Number).filter(id => Number.isInteger(id) && id > 0));
        const confirmedStableId = `M03:question:${numericQuestionId}`;
        const confirmed = module.questions.find(question => question?.stable_id === confirmedStableId
            && Number(question.question_id) === numericQuestionId);
        if (!confirmed) return {applied: false, reason: 'M03_CONFIRMED_QUESTION_NOT_FOUND'};

        const version = typeof data.version === 'string' && data.version.trim() !== '' ? data.version : null;
        const updateQuestion = question => {
            if (!question || question.module_id !== 'M03' || !Number.isInteger(Number(question.question_id))) return;
            const id = Number(question.question_id);
            question.response = question.response && typeof question.response === 'object' ? question.response : {};
            question.response.eligible = eligibleIds.has(id);
            question.response.editable = editableIds.has(id);
            if (version) question.response.version = version;
            if (id === numericQuestionId) {
                question.response.status = 'documented';
                question.response.persisted = true;
                if (Object.prototype.hasOwnProperty.call(data, 'readback_value')
                    && (typeof data.readback_value === 'string' || data.readback_value === null)) {
                    question.response.value = data.readback_value === '' ? null : data.readback_value;
                    question.response.source = 'clinical_record';
                }
                const semanticState = String(data.semantic_state || '').trim().toUpperCase();
                if (['KNOWN', 'NEGATIVE', 'UNKNOWN', 'NOT_REMEMBERED'].includes(semanticState)) {
                    question.response.semantic_state = semanticState;
                }
                question.completion = question.completion && typeof question.completion === 'object'
                    ? question.completion
                    : {};
                question.completion.status = 'complete';
            }
        };
        module.questions.forEach(updateQuestion);
        Object.values(contract.question_index || {}).forEach(updateQuestion);
        cards.forEach(card => {
            const id = Number(card?.dataset?.questionId);
            if (Number.isInteger(id) && id > 0) {
                card.dataset.eligible = eligibleIds.has(id) ? '1' : '0';
                card.dataset.historicalEditable = editableIds.has(id) ? '1' : '0';
            }
        });

        if (data.finalized === true) {
            module.state.finalized = true;
            module.state.write_enabled = false;
            module.conversation_state = {
                ...module.conversation_state,
                state: 'FINALIZED', action: 'CONTINUE', reason: 'PERSISTED_FINALIZATION',
                stable_id: null, write_enabled: false, next_action: 'CONTINUE_TO_NEXT_AUTHORIZED_MODULE',
            };
        } else {
            const nextQuestionId = Number(data.next_question_id);
            const nextStableId = Number.isInteger(nextQuestionId) && nextQuestionId > 0
                ? `M03:question:${nextQuestionId}`
                : '';
            const nextQuestion = nextStableId ? module.questions.find(question => question?.stable_id === nextStableId) : null;
            if (nextQuestion && eligibleIds.has(nextQuestionId)
                && nextQuestion.response?.status === 'pending'
                && module.state.write_enabled === true
                && module.state.finalized !== true) {
                module.conversation_state = {
                    ...module.conversation_state,
                    state: 'ANSWERABLE', action: 'ANSWER', reason: 'PENDING_QUESTION',
                    stable_id: nextStableId, write_enabled: true,
                    next_action: 'CAPTURE_AND_CONFIRM_RESPONSE',
                };
            }
        }

        return {applied: true, reason: 'M03_SERVER_READBACK_APPLIED', eligibleIds, nextStableId: module.conversation_state.stable_id || null};
    };

    const ensureStatus = card => {
        let status = card.querySelector('[data-m03-dictation-status]');
        if (status) return status;

        const footer = card.querySelector('.interview-composer-footer');
        if (!footer) return null;
        status = document.createElement('small');
        status.className = 'm03-dictation-note';
        status.dataset.m03DictationStatus = '1';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        status.setAttribute('aria-atomic', 'true');
        status.hidden = true;
        footer.parentElement?.insertBefore(status, footer.nextSibling);
        return status;
    };

    const removeControls = card => {
        card.querySelectorAll(CONTROL_SELECTOR).forEach(control => control.remove());
        const status = card.querySelector('[data-m03-dictation-status]');
        if (status) {
            status.hidden = true;
            status.textContent = '';
        }
    };

    const syncCard = ({
        card,
        active = false,
        fieldVisible = false,
        input = null,
        eligibility = {allowed: false, code: 'M03_QUESTION_NOT_VISIBLE'},
        available = false,
        unavailableReason = 'El dictado local no está disponible. Puedes responder por escrito.',
        unavailableReasonCode = 'DICTATION_UNAVAILABLE',
        runtimeState = 'IDLE',
        runtimeMessage = '',
        localDiagnostics = null,
        onStart = () => {},
    }) => {
        if (!card) return {mounted: false, enabled: false, reason: 'M03_CARD_NOT_FOUND'};

        const host = card.querySelector('.m03-input-wrap');
        const inputIsCurrent = Boolean(input && host && input.parentElement === host
            && input.matches('.m03-input-wrap > .interview-text'));
        if (!active || !fieldVisible || !inputIsCurrent) {
            removeControls(card);
            return {mounted: false, enabled: false, reason: active ? 'M03_INPUT_NOT_VISIBLE' : 'M03_QUESTION_INACTIVE'};
        }

        card.querySelectorAll(CONTROL_SELECTOR).forEach(control => {
            if (control.parentElement !== host) control.remove();
        });
        let controls = Array.from(host.querySelectorAll(CONTROL_SELECTOR));
        const button = controls.shift() || document.createElement('button');
        controls.forEach(control => control.remove());

        button.type = 'button';
        button.className = 'btn btn-outline-secondary chat-mic-button';
        button.dataset.m03DictationControl = '1';
        button.textContent = '🎙';
        button.hidden = false;
        if (button.__m03ListenerBound !== true) {
            button.addEventListener('click', () => button.__m03OnStart?.(button, button.__m03Input));
            button.__m03ListenerBound = true;
        }
        button.__m03Input = input;
        button.__m03OnStart = onStart;
        if (button.parentElement !== host) host.appendChild(button);

        const eligibilityMessages = {
            M03_QUESTION_NOT_ELIGIBLE: 'Esta pregunta no admite dictado. Puedes responder por escrito si el campo está disponible.',
            M03_MODULE_INACTIVE: 'La sección M03 no está activa. No se iniciará el dictado.',
            M03_WRITE_DISABLED: 'La escritura de esta pregunta no está autorizada. No se enviará dictado.',
            M03_FINALIZED: 'Esta sección está finalizada y no admite nuevas respuestas.',
            M03_ACTIVE_TARGET_STALE: '',
            M03_CONTRACT_ELIGIBILITY_STALE: '',
            M03_RESPONSE_NOT_DICTATABLE: 'El estado actual de esta respuesta no permite dictado.',
            QUESTION_INPUT_DISABLED: 'El campo está deshabilitado. No se iniciará el dictado.',
            QUESTION_NOT_EDITABLE: 'Esta respuesta está en modo de solo lectura.',
            QUESTION_TARGET_MISSING: 'No se pudo resolver la pregunta activa para dictado.',
            INTERVIEW_PAUSED: 'La entrevista está pausada. Reanúdala antes de dictar.',
            INTERVIEW_OPERATION_IN_PROGRESS: 'Espera a que termine la operación actual antes de dictar.',
            ACTIVE_QUESTION_ID_MISMATCH: 'La pregunta visible no coincide con la pregunta activa.',
            VISIBLE_QUESTION_ID_MISMATCH: 'La identidad de la pregunta visible no coincide; el dictado permanece bloqueado.',
            M03_QUESTION_NOT_VISIBLE: 'La pregunta no está visible para dictado.',
            M03_INPUT_NOT_FOUND: 'No se pudo verificar el campo de respuesta de esta pregunta.',
            QUESTION_NOT_IN_M03_CONTRACT: 'No se pudo validar esta pregunta para dictado.',
            DICTATION_CLIENT_NOT_INITIALIZED: 'El cliente local de dictado no se pudo inicializar. Puedes responder por escrito.',
            CONTRACT_QUESTION_ID_MISMATCH: 'La identidad de la pregunta no coincide con el contrato activo.',
            MODULE_NOT_ANSWERABLE: 'No hay una pregunta editable autorizada en este momento.',
            QUESTION_WRITE_DISABLED: 'La respuesta no está autorizada para edición o dictado.',
            M03_QUESTION_CONTRACT_INELIGIBLE: 'El contrato actual no permite dictar esta respuesta.',
        };
        const silentEligibilityCodes = new Set(['M03_ACTIVE_TARGET_STALE', 'M03_CONTRACT_ELIGIBILITY_STALE']);
        const busy = ['AUTHORIZING', 'CONNECTING', 'STOPPING'].includes(runtimeState);
        const listening = ['LISTENING', 'RECOGNIZING'].includes(runtimeState);
        const reason = !eligibility?.allowed
            ? (silentEligibilityCodes.has(eligibility?.code)
                ? ''
                : (eligibilityMessages[eligibility?.code] || 'La pregunta no está autorizada para dictado en este momento.'))
            : !available
                ? unavailableReason
                : busy
                    ? (runtimeMessage || (runtimeState === 'STOPPING' ? 'Deteniendo el dictado…' : 'Conectando el dictado local…'))
                    : listening
                        ? 'Dictado local activo. Pulsa el micrófono para detenerlo.'
                        : ['ERROR', 'UNAVAILABLE'].includes(runtimeState) && runtimeMessage
                            ? runtimeMessage
                            : '';
        const reasonCode = !eligibility?.allowed
            ? String(eligibility?.code || 'M03_NOT_AUTHORIZED')
            : !available
                ? String(unavailableReasonCode || 'DICTATION_UNAVAILABLE')
                : busy
                    ? `DICTATION_${runtimeState}`
                    : listening
                        ? 'DICTATION_LISTENING'
                        : ['ERROR', 'UNAVAILABLE'].includes(runtimeState) && runtimeMessage
                            ? `DICTATION_${runtimeState}`
                            : 'READY';
        const enabled = Boolean(eligibility?.allowed && available && !busy);
        button.disabled = !enabled;
        button.dataset.dictationReason = enabled ? '' : reasonCode;
        button.dataset.dictationDisabledBy = enabled ? '' : 'PatientInterviewM03DictationUi.syncCard';
        button.setAttribute('aria-disabled', enabled ? 'false' : 'true');
        button.classList.toggle('is-listening', listening);
        button.title = enabled
            ? (listening ? 'Detener dictado local' : 'Dictado local en español')
            : (reason || 'Dictado no disponible.');
        button.setAttribute('aria-label', enabled
            ? (listening ? 'Detener dictado' : 'Iniciar dictado')
            : `Dictado no disponible. ${reason || 'Puedes responder por escrito.'}`);

        const status = ensureStatus(card);
        if (status) {
            status.textContent = reason;
            status.hidden = reason === '';
            status.dataset.reasonCode = reason === '' ? '' : reasonCode;
            if (reason) {
                if (!status.id) status.id = `m03-dictation-status-${String(card.dataset.questionId || 'active')}`;
                button.setAttribute('aria-describedby', status.id);
            } else {
                button.removeAttribute('aria-describedby');
            }
        }
        return {mounted: true, enabled, reason, reasonCode, disabledBy: enabled ? null : 'PatientInterviewM03DictationUi.syncCard'};
    };

    const api = Object.freeze({syncCard, canInitiate, applyServerReadback});
    global.PatientInterviewM03DictationUi = api;
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
