const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../public/assets/js/patient-interview-coordinator.js'), 'utf8');
const viewSource = fs.readFileSync(path.resolve(__dirname, '../../app/views/paciente/entrevista.php'), 'utf8');
const context = {
    window: {
        Response: global.Response,
        AbortController: global.AbortController,
    },
};
vm.runInNewContext(source, context);
const Coordinator = context.window.PatientInterviewCoordinator;
const STATES = context.window.PatientInterviewCoordinatorStates;

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

function response(payload, status = 200) {
    if (typeof Response === 'function') {
        return new Response(JSON.stringify(payload), {status, headers: {'Content-Type': 'application/json'}});
    }
    return {ok: status >= 200 && status < 300, status, json: async () => payload};
}

async function main() {
    assert(!source.includes('localStorage') && !source.includes('sessionStorage'), 'El coordinador no debe almacenar datos clínicos en el navegador.');
    assert(viewSource.includes('patient-interview-coordinator.js') && viewSource.includes('new window.PatientInterviewCoordinator'), 'La entrevista debe usar el coordinador central existente.');
    assert(viewSource.includes("const fetch = (url, init) => conversationCoordinator.request('auto', url, init)"), 'Las peticiones del compositor deben pasar por el coordinador.');
    assert(viewSource.includes('chat-help-button') && viewSource.includes("voiceEvent('HELP'"), 'La ayuda debe utilizar el contrato de narración del servidor.');
    assert(Object.keys(STATES).length === 16, 'El coordinador debe exponer los 16 estados contractuales.');

    const events = [];
    const coordinator = new Coordinator({patientId: 9301, contract: {version: 'synthetic'}, onStateChange: event => events.push(event)});
    assert(coordinator.state === STATES.READY, 'La inicialización debe terminar en READY.');
    coordinator.setTarget('M03:1');
    assert(coordinator.state === STATES.ASKING, 'La primera pregunta debe pasar a ASKING.');
    assert(coordinator.transition(STATES.MODULE_COMPLETED) === false, 'Una transición inválida no debe permitirse.');
    assert(coordinator.state === STATES.ASKING, 'Una transición inválida no debe cambiar el estado.');

    const firstStartText = 'Bienvenido a tu entrevista de historia clínica. Te realizaré algunas preguntas para conocer tus antecedentes y registrar tu información médica. Responde con tus propias palabras. Después podrás revisar y confirmar tus respuestas antes de guardarlas. Si necesitas una explicación, puedes utilizar el botón Ayuda. Comencemos.';
    const firstStart = new Coordinator({contract: {
        interview_progress: {
            source: 'server_confirmed_clinical_progress',
            has_confirmed_responses: false,
            first_pending_module_id: 'M03',
            first_pending_stable_id: 'M03:question:1',
        },
        question_index: {'M03:question:1': {stable_id: 'M03:question:1', voice: {QUESTION: 'Pregunta clínica sintética.'}}},
        voice: {FIRST_START: firstStartText},
    }});
    firstStart.setTarget('M03:question:1');
    assert(firstStart.initialInterviewState() === 'FIRST_START', 'La primera pregunta sin respuestas confirmadas debe iniciar como FIRST_START.');
    assert(firstStart.voiceEvent('FIRST_START', 'M03:question:1', false).text === firstStartText, 'FIRST_START debe utilizar el texto contractual exacto.');
    const m02Contract = new Coordinator({contract: {question_index: {
        'M02:family:heart': {module_id: 'M02', question_id: 'Enfermedades del corazón', stable_id: 'M02:family:heart', voice: {QUESTION: '¿Algún familiar tiene enfermedades del corazón?'}},
    }}});
    assert(m02Contract.stableIdForQuestion('M02', 'Enfermedades del corazón') === 'M02:family:heart', 'M02 debe resolver la pregunta pendiente a su stable_id canónico.');
    assert(m02Contract.stableIdForQuestion('M02', 'No existe') === null, 'Una pregunta M02 desconocida debe fallar cerradamente.');
    const resumedStart = new Coordinator({contract: {
        interview_progress: {has_confirmed_responses: true, first_pending_module_id: 'M03', first_pending_stable_id: 'M03:question:1'},
    }});
    resumedStart.setTarget('M03:question:1');
    assert(resumedStart.initialInterviewState() === 'RESUME', 'Una respuesta confirmada debe iniciar como RESUME.');

    const finalizeActionEvents = [];
    const finalizeAction = new Coordinator({contract: {
        modules: {M02: {title: 'Antecedentes familiares', conversation_state: {
            state: 'READY_TO_FINALIZE', action: 'FINALIZE', action_id: 'M02:action:finalize', stable_id: null,
            voice_text: 'Tus antecedentes familiares están registrados. Puedes finalizar esta sección y continuar.',
        }, voice: {RESUME: 'Continuemos con tus antecedentes familiares.'}}},
    }, onVoiceEvent: event => finalizeActionEvents.push(event), fetcher: async () => response({resultado: true, finalized: true, finalization_readback: true, readback: true})});
    assert(finalizeAction.setActionTarget('M02:action:finalize') === 'M02:action:finalize', 'READY_TO_FINALIZE debe aceptar únicamente la acción contractual validada.');
    const actionSnapshot = finalizeAction.snapshot();
    assert(actionSnapshot.context.stable_id === null && actionSnapshot.context.action_id === 'M02:action:finalize', 'Una acción de finalización no debe convertirse en stable_id clínico.');
    const actionEvent = finalizeAction.voiceEvent('ACTION', 'M02:action:finalize', false);
    assert(actionEvent.action_id === 'M02:action:finalize' && actionEvent.stable_id === null && actionEvent.text.includes('registrados'), 'La acción M02 debe aportar narración propia y contexto validado.');
    assert(finalizeActionEvents.some(event => event.type === 'ACTION' && event.action_id === 'M02:action:finalize'), 'El cambio a la acción M02 debe emitir un evento de voz accionable.');
    const finalizeActionResponse = await finalizeAction.request('auto', '/entrevista/capturar-m02', {method: 'POST'});
    await finalizeActionResponse.json();
    assert(finalizeAction.state === STATES.RESPONSE_SAVED, 'La acción M02 debe conservar el guard de guardado y readback.');

    const interpret = new Coordinator({contract: {question_index: {'M03:1': {stable_id: 'M03:1', voice: {QUESTION: 'Pregunta sintética', CONFIRMATION: 'Confirma la propuesta.'}}}}, fetcher: async () => response({resultado: true, proposal: {can_confirm: true, kind: 'm03_proposal'}})});
    interpret.setTarget('M03:1');
    assert(interpret.voiceEvent('QUESTION').text === 'Pregunta sintética', 'La narración debe proceder del contrato de servidor.');
    const interpretResponse = await interpret.request('interpret', '/entrevista/interpretar', {method: 'POST'});
    await interpretResponse.json();
    assert(interpret.state === STATES.CONFIRMATION_REQUIRED, 'Una propuesta confirmable debe requerir confirmación.');

    const direct = new Coordinator({fetcher: async () => response({resultado: true, proposal: {can_confirm: true, direct_save: true, kind: 'm03_proposal'}})});
    direct.setTarget('M03:1');
    await (await direct.request('interpret', '/entrevista/interpretar', {method: 'POST'})).json();
    assert(direct.state === STATES.WAITING_FOR_RESPONSE, 'Una respuesta direct-save no debe pasar por el estado genérico de confirmación.');

    const clarify = new Coordinator({fetcher: async () => response({resultado: true, proposal: {kind: 'clarification', clarification: 'Aclara el dato.'}})});
    clarify.setTarget('M02:madre');
    const clarifyResponse = await clarify.request('interpret', '/entrevista/interpretar', {method: 'POST'});
    await clarifyResponse.json();
    assert(clarify.state === STATES.CLARIFICATION_REQUIRED, 'Una propuesta ambigua debe solicitar aclaración.');

    const save = new Coordinator({fetcher: async () => response({resultado: true, readback: true, version: '2'})});
    save.setTarget('M04:1');
    const saveResponse = await save.request('save', '/entrevista/capturar-m04', {method: 'POST'});
    await saveResponse.json();
    assert(save.state === STATES.RESPONSE_SAVED, 'El guardado solo debe terminar en RESPONSE_SAVED con readback aceptado.');
    assert(save.navigate('test') === true && save.state === STATES.NAVIGATING, 'La navegación debe ocurrir después del guardado confirmado.');

    const finalizeM02 = new Coordinator({fetcher: async () => response({resultado: true, finalized: true, finalization_readback: true, readback: true})});
    const finalizeResponse = await finalizeM02.request('auto', '/entrevista/capturar-m02', {method: 'POST'});
    const finalizePayload = await finalizeResponse.json();
    assert(finalizePayload.finalized === true && finalizeM02.state === STATES.RESPONSE_SAVED, 'La finalización M02 explícita debe usar el guard de guardado y readback del coordinador.');

    const savingEvents = [];
    const savingVoice = new Coordinator({contract: {modules: {M06: {questions: [], voice: {SAVING: 'Guardando.', SUCCESS: 'Guardado.'}}}}, fetcher: async () => response({resultado: true, readback: true, version: '2'}), onVoiceEvent: event => savingEvents.push(event)});
    savingVoice.setTarget('M06');
    await (await savingVoice.request('save', '/entrevista/capturar-m06', {method: 'POST'})).json();
    assert(savingEvents.some(event => event.type === 'SAVING') && savingEvents.some(event => event.type === 'SUCCESS'), 'Los eventos SAVING y SUCCESS deben proceder del coordinador.');

    const blockedEvents = [];
    const blockedVoice = new Coordinator({contract: {modules: {M07: {questions: [], voice: {MODULE_BLOCKED: 'Módulo bloqueado.'}}}}, onVoiceEvent: event => blockedEvents.push(event)});
    blockedVoice.setTarget('M07');
    blockedVoice.markModuleBlocked('M07_CANONICAL_DISABLED');
    assert(blockedEvents.some(event => event.type === 'MODULE_BLOCKED' && event.text === 'Módulo bloqueado.'), 'Módulo bloqueado debe tener narración contractual.');

    const missingReadback = new Coordinator({fetcher: async () => response({resultado: true})});
    missingReadback.setTarget('M04:1');
    const missingReadbackResponse = await missingReadback.request('save', '/entrevista/capturar-m04', {method: 'POST'});
    await missingReadbackResponse.json();
    assert(missingReadback.state === STATES.SAVE_FAILED, 'Una respuesta sin evidencia de readback no debe habilitar el avance.');

    const failedSave = new Coordinator({fetcher: async () => response({resultado: false, code: 'VERSION_CONFLICT'}, 409)});
    failedSave.setTarget('M04:1');
    const failedResponse = await failedSave.request('save', '/entrevista/capturar-m04', {method: 'POST'});
    await failedResponse.json();
    assert(failedSave.state === STATES.SAVE_FAILED, 'Un conflicto o error de guardado debe permanecer en SAVE_FAILED.');

    let resolvePending;
    const pending = new Coordinator({fetcher: () => new Promise(resolve => { resolvePending = resolve; })});
    pending.setTarget('M05:1');
    const first = pending.request('interpret', '/entrevista/interpretar', {method: 'POST'});
    const duplicate = await pending.request('interpret', '/entrevista/interpretar', {method: 'POST'});
    const duplicateBody = await duplicate.json();
    assert(duplicateBody.code === 'COORDINATOR_DUPLICATE_REQUEST', 'El doble envío debe rechazarse en el coordinador.');
    pending.setTarget('M05:2');
    resolvePending(response({resultado: true, proposal: {can_confirm: true}}));
    const stale = await (await first).json();
    assert(stale.code === 'COORDINATOR_STALE_RESPONSE', 'Una respuesta tardía debe descartarse después de cambiar de pregunta.');

    const paused = new Coordinator({fetcher: async () => response({resultado: true, proposal: {can_confirm: true}})});
    paused.setTarget('M06:1');
    assert(paused.pause() === true && paused.state === STATES.PAUSED, 'Pausar debe detener el flujo sin persistir información.');
    const pausedResponse = await paused.request('interpret', '/entrevista/interpretar', {method: 'POST'});
    assert((await pausedResponse.json()).code === 'COORDINATOR_PAUSED', 'Una entrevista pausada no debe enviar interpretación.');
    assert(paused.resume() === true && paused.state === STATES.ASKING, 'Reanudar debe regresar a la pregunta activa.');

    const expired = new Coordinator({fetcher: async () => response({resultado: false, code: 'SESSION_EXPIRED'}, 401)});
    expired.setTarget('M03:1');
    const expiredResponse = await expired.request('interpret', '/entrevista/interpretar', {method: 'POST'});
    await expiredResponse.json();
    assert(expired.state === STATES.SESSION_EXPIRED, 'Una sesión expirada debe bloquear el avance.');
    assert(events.some(event => event.state === STATES.ASKING), 'La trazabilidad de transiciones debe conservar ASKING.');

    console.log('CONVERSATION_COORDINATOR_TEST: PASS');
    console.log('STATE_MACHINE: PASS');
    console.log('INVALID_TRANSITIONS: PASS');
    console.log('DOUBLE_SUBMIT_PROTECTION: PASS');
    console.log('STALE_RESPONSE_PROTECTION: PASS');
    console.log('PAUSE_RESUME: PASS');
    console.log('SESSION_EXPIRY: PASS');
    console.log('NO_BROWSER_STORAGE: PASS');
    console.log('FIRST_START_WITHOUT_CONFIRMED_PROGRESS: PASS');
    console.log('FIRST_START_LITERAL_WELCOME: PASS');
}

main().catch(error => { console.error(error.message); process.exit(1); });
