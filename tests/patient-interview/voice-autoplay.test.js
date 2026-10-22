const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../public/assets/js/patient-interview-voice.js'), 'utf8');
const viewSource = fs.readFileSync(path.resolve(__dirname, '../../app/views/paciente/entrevista.php'), 'utf8');

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

class MockUtterance {
    constructor(text) { this.text = text; this.lang = ''; this.voice = null; this.rate = 1; this.onstart = null; this.onend = null; this.onerror = null; }
}

class MockSpeechSynthesis {
    constructor() { this.calls = []; this.current = null; this.cancelCount = 0; this.voices = [{lang: 'es-MX'}]; }
    getVoices() { return this.voices; }
    speak(utterance) { this.current = utterance; this.calls.push(utterance); }
    cancel() { this.cancelCount++; this.current = null; }
    finish() { const current = this.current; this.current = null; current?.onend?.(); }
}

const loadController = speech => {
    const context = {window: {SpeechSynthesisUtterance: MockUtterance, setTimeout, clearTimeout}};
    vm.runInNewContext(source, context);
    return {Controller: context.window.PatientInterviewVoiceController, States: context.window.PatientInterviewVoiceStates, speech};
};

const wait = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));

(async () => {
    const startupBlock = viewSource.slice(viewSource.indexOf('const startupVoiceEvents ='), viewSource.indexOf('voiceStartButton?.addEventListener'));
    const resumeBlock = viewSource.slice(viewSource.indexOf("if (event.type === 'RESUME')"), viewSource.indexOf('if (event.action_id)', viewSource.indexOf("if (event.type === 'RESUME')")));
    assert(startupBlock.includes("return {question: !blockedM07 && moduleId === 'M02'") && !startupBlock.includes("voiceEvent('WELCOME')") && !startupBlock.includes("voiceEvent('FIRST_START'") && !startupBlock.includes("voiceEvent('RESUME'"), 'Startup must resolve only the active target, with no generic preface.');
    const repeatBlock = viewSource.slice(viewSource.indexOf('const repeatActiveQuestion ='), viewSource.indexOf('const showContractHelp ='));
    assert(startupBlock.includes('activateAutomatically(question)') && repeatBlock.includes('voiceController.activate(event, null, {userGesture:true})') && repeatBlock.includes('voiceController.speakEvent(event, {force:true, userGesture:true})'), 'Automatic start and manual repeat must send only the freshly resolved active question through the voice controller.');
    assert(viewSource.includes('const repeatOrUnlockVoice = () => repeatActiveQuestion();'), 'The sidebar repeat action must share the active-question repeat path.');
    assert(resumeBlock.includes("voiceController.handleEvent(canonicalQuestion?.module_id === 'M02' ? {...question, module_id: 'M02'} : question)") && !resumeBlock.includes('speakStartup'), 'Interview resume must replay only the current canonical question and preserve M02 Piper routing.');

    const automaticSpeech = new MockSpeechSynthesis();
    const automatic = loadController(automaticSpeech);
    const controller = new automatic.Controller({speechApi: automaticSpeech, utteranceConstructor: MockUtterance});
    assert(typeof controller.setMuted === 'function', 'La API pública debe exponer setMuted para la vista.');
    assert(controller.setMuted(false) === false, 'setMuted debe aceptar el estado inicial sin lanzar una excepción.');
    const firstQuestion = {type: 'QUESTION', stable_id: 'M02:family:heart', text: '¿Algún familiar tiene enfermedades del corazón?'};
    assert(controller.activateAutomatically(firstQuestion) === true, 'La activación automática debe iniciar la pregunta activa.');
    assert(controller.state === automatic.States.AUDIO_PENDING, 'La reproducción automática debe quedar pendiente hasta onstart.');
    assert(automaticSpeech.calls.length === 1 && automaticSpeech.calls[0].text === firstQuestion.text, 'La primera solicitud TTS debe contener únicamente la pregunta activa.');
    automaticSpeech.current.onstart();
    assert(controller.state === automatic.States.SPEAKING, 'onstart debe confirmar reproducción.');
    automaticSpeech.finish();
    assert(controller.state === automatic.States.READY, 'La secuencia automática debe finalizar.');

    const automaticStartupSpeech = new MockSpeechSynthesis();
    const automaticStartup = loadController(automaticStartupSpeech);
    const automaticStartupController = new automaticStartup.Controller({speechApi: automaticStartupSpeech, utteranceConstructor: MockUtterance});
    const allowedQuestion = {type: 'QUESTION', stable_id: 'M02:family:heart', text: '¿Algún familiar tiene enfermedades del corazón?'};
    assert(automaticStartupController.activateAutomatically(allowedQuestion) === true, 'El inicio automático permitido debe comenzar.');
    assert(automaticStartupSpeech.calls.length === 1 && automaticStartupSpeech.calls[0].text === allowedQuestion.text, 'TTS debe recibir solamente el texto de la pregunta activa, sin bienvenida.');
    automaticStartupSpeech.current.onstart();
    automaticStartupSpeech.finish();
    assert(automaticStartupController.lastEvent === allowedQuestion, 'Al finalizar el arranque automático, Repetir debe apuntar a la pregunta vigente.');
    assert(automaticStartupController.repeat(true) === true && automaticStartupSpeech.current?.text === allowedQuestion.text, 'Repetir debe narrar únicamente la pregunta activa.');
    automaticStartupSpeech.current.onstart();
    automaticStartupSpeech.finish();
    const nextQuestion = {type: 'QUESTION', stable_id: 'M02:family:cancer', revision: 2, text: '¿Algún familiar tiene antecedentes de cáncer?'};
    const callsBeforeNextQuestion = automaticStartupSpeech.calls.length;
    const cancelsBeforeNextQuestion = automaticStartupSpeech.cancelCount;
    assert(automaticStartupController.handleEvent(nextQuestion) === true, 'El cambio de pregunta debe enviar el nuevo evento activo.');
    assert(automaticStartupSpeech.calls.length === callsBeforeNextQuestion + 1 && automaticStartupSpeech.current?.text === nextQuestion.text, 'El cambio de pregunta debe narrar una sola vez y no anteponer introducciones.');
    assert(automaticStartupSpeech.cancelCount > cancelsBeforeNextQuestion, 'El cambio de pregunta debe cancelar cualquier narración anterior.');
    const callsBeforeDuplicate = automaticStartupSpeech.calls.length;
    assert(automaticStartupController.handleEvent(nextQuestion) === false && automaticStartupSpeech.calls.length === callsBeforeDuplicate, 'Una transición duplicada no debe volver a narrarse.');

    const blockedSpeech = new MockSpeechSynthesis();
    const blocked = loadController(blockedSpeech);
    const blockedController = new blocked.Controller({speechApi: blockedSpeech, utteranceConstructor: MockUtterance});
    const blockedQuestion = {type: 'QUESTION', stable_id: 'M02:family:heart', text: '¿Algún familiar tiene enfermedades del corazón?'};
    blockedController.activateAutomatically(blockedQuestion);
    await wait(1300);
    assert(blockedController.state === blocked.States.AUDIO_BLOCKED, 'La ausencia de onstart debe clasificarse como audio bloqueado.');
    assert(blockedController.unlock() === true && blockedController.state === blocked.States.SPEAKING && blockedSpeech.current?.text === blockedQuestion.text, 'Una interacción debe desbloquear únicamente la pregunta vigente.');

    class SynchronousAutoplayBlockedSpeech extends MockSpeechSynthesis {
        constructor() { super(); this.userGesture = false; }
        speak(utterance) {
            if (!this.userGesture) throw new Error('NotAllowedError');
            super.speak(utterance);
        }
    }

    const synchronousBlockedSpeech = new SynchronousAutoplayBlockedSpeech();
    const synchronousBlocked = loadController(synchronousBlockedSpeech);
    const synchronousController = new synchronousBlocked.Controller({speechApi: synchronousBlockedSpeech, utteranceConstructor: MockUtterance});
    const synchronousQuestion = {type: 'QUESTION', stable_id: 'M02:family:heart', text: '¿Algún familiar tiene enfermedades del corazón?'};
    assert(synchronousController.activateAutomatically(synchronousQuestion) === false, 'Una denegación síncrona debe detener el intento automático.');
    assert(synchronousController.state === synchronousBlocked.States.AUDIO_BLOCKED, 'Una denegación síncrona debe ofrecer activación de audio.');
    synchronousBlockedSpeech.userGesture = true;
    assert(synchronousController.unlock() === true && synchronousBlockedSpeech.current?.text === synchronousQuestion.text, 'Activar audio debe reutilizar únicamente la pregunta pendiente con un gesto real.');

    const atomicBlockedSpeech = new SynchronousAutoplayBlockedSpeech();
    const atomicBlocked = loadController(atomicBlockedSpeech);
    const atomicBlockedController = new atomicBlocked.Controller({speechApi: atomicBlockedSpeech, utteranceConstructor: MockUtterance});
    const startupQuestion = {type: 'QUESTION', stable_id: 'M02:family:heart', text: '¿Algún familiar tiene enfermedades del corazón?'};
    assert(atomicBlockedController.activateAutomatically(startupQuestion) === false, 'Una denegación síncrona debe dejar la pregunta pendiente de activación.');
    await wait(1300);
    assert(atomicBlockedController.state === atomicBlocked.States.AUDIO_BLOCKED, 'El inicio automático bloqueado debe conservar un estado accionable.');
    atomicBlockedSpeech.userGesture = true;
    assert(atomicBlockedController.unlock() === true, 'Un clic en Activar audio debe desbloquear el inicio compuesto.');
    assert(atomicBlockedSpeech.current?.text === startupQuestion.text, 'El desbloqueo debe reproducir la pregunta, sin texto de reanudación genérico.');

    const staleBlockedSpeech = new SynchronousAutoplayBlockedSpeech();
    const staleBlocked = loadController(staleBlockedSpeech);
    const staleBlockedController = new staleBlocked.Controller({speechApi: staleBlockedSpeech, utteranceConstructor: MockUtterance});
    assert(staleBlockedController.activateAutomatically(startupQuestion) === false, 'Una narración bloqueada debe quedar pendiente antes del cambio de pregunta.');
    staleBlockedController.cancel();
    staleBlockedSpeech.userGesture = true;
    assert(staleBlockedController.unlock() === false, 'Una pregunta obsoleta cancelada no debe reproducirse al desbloquear audio.');

    const notPausedController = new synchronousBlocked.Controller({speechApi: synchronousBlockedSpeech, utteranceConstructor: MockUtterance});
    assert(notPausedController.resume() === false, 'Reanudar no debe actuar como sustituto de Activar audio cuando no existe una pausa real.');

    const invalidStartupController = new automatic.Controller({speechApi: automaticSpeech, utteranceConstructor: MockUtterance});
    assert(invalidStartupController.activateStartup({type: 'FIRST_START', text: 'Introducción.'}, null) === false, 'Un arranque sin pregunta debe rechazarse.');
    assert(invalidStartupController.state === automatic.States.VOICE_ERROR, 'Un fallo de resolución no debe dejar el panel en activación indefinida.');

    console.log('VOICE_AUTOPLAY_TEST: PASS');
    console.log('AUTOMATIC_ACTIVATION: PASS');
    console.log('QUESTION_ONLY_STARTUP: PASS');
    console.log('QUESTION_ONLY_RESUME_AND_REPEAT: PASS');
    console.log('SINGLE_NARRATION_PER_TRANSITION: PASS');
    console.log('AUTOPLAY_BLOCKED_REQUIRES_GESTURE: PASS');
    console.log('ONE_TIME_UNLOCK: PASS');
    console.log('AUTO_START_QUESTION_ONLY: PASS');
    console.log('RESUME_IS_NOT_AUDIO_UNLOCK: PASS');
})().catch(error => {
    console.error(`VOICE_AUTOPLAY_TEST: FAIL - ${error.message}`);
    process.exitCode = 1;
});
