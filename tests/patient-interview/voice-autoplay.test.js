const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../public/assets/js/patient-interview-voice.js'), 'utf8');

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

class MockUtterance {
    constructor(text) { this.text = text; this.lang = ''; this.voice = null; this.rate = 1; this.onstart = null; this.onend = null; this.onerror = null; }
}

class MockSpeechSynthesis {
    constructor() { this.calls = []; this.current = null; this.voices = [{lang: 'es-MX'}]; }
    getVoices() { return this.voices; }
    speak(utterance) { this.current = utterance; this.calls.push(utterance); }
    cancel() { this.current = null; }
    finish() { const current = this.current; this.current = null; current?.onend?.(); }
}

const loadController = speech => {
    const context = {window: {SpeechSynthesisUtterance: MockUtterance, setTimeout, clearTimeout}};
    vm.runInNewContext(source, context);
    return {Controller: context.window.PatientInterviewVoiceController, States: context.window.PatientInterviewVoiceStates, speech};
};

const wait = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));

(async () => {
    const automaticSpeech = new MockSpeechSynthesis();
    const automatic = loadController(automaticSpeech);
    const controller = new automatic.Controller({speechApi: automaticSpeech, utteranceConstructor: MockUtterance});
    assert(typeof controller.setMuted === 'function', 'La API pública debe exponer setMuted para la vista.');
    assert(controller.setMuted(false) === false, 'setMuted debe aceptar el estado inicial sin lanzar una excepción.');
    assert(controller.activateAutomatically({type: 'WELCOME', text: 'Bienvenida sintética.'}, () => controller.handleEvent({type: 'QUESTION', text: 'Pregunta sintética.'})) === true, 'La activación automática debe encolar la bienvenida.');
    assert(controller.state === automatic.States.AUDIO_PENDING, 'La reproducción automática debe quedar pendiente hasta onstart.');
    automaticSpeech.current.onstart();
    assert(controller.state === automatic.States.SPEAKING, 'onstart debe confirmar reproducción.');
    automaticSpeech.finish();
    assert(automaticSpeech.current?.text === 'Pregunta sintética.' && controller.state === automatic.States.AUDIO_PENDING, 'La siguiente pregunta debe quedar encolada automáticamente.');
    automaticSpeech.current.onstart();
    automaticSpeech.finish();
    assert(controller.state === automatic.States.READY, 'La secuencia automática debe finalizar.');

    const automaticStartupSpeech = new MockSpeechSynthesis();
    const automaticStartup = loadController(automaticStartupSpeech);
    const automaticStartupController = new automaticStartup.Controller({speechApi: automaticStartupSpeech, utteranceConstructor: MockUtterance});
    const allowedIntro = {type: 'FIRST_START', text: 'Bienvenido a tu entrevista.'};
    const allowedQuestion = {type: 'QUESTION', stable_id: 'M02:family:heart', text: '¿Algún familiar tiene enfermedades del corazón?'};
    assert(automaticStartupController.activateAutomatically(allowedIntro, null, allowedQuestion) === true, 'El inicio automático permitido debe comenzar.');
    assert(automaticStartupSpeech.calls.length === 1 && automaticStartupSpeech.calls[0].text.includes(allowedIntro.text) && automaticStartupSpeech.calls[0].text.includes(allowedQuestion.text), 'El inicio automático permitido debe incluir la pregunta activa.');
    automaticStartupSpeech.current.onstart();
    automaticStartupSpeech.finish();
    assert(automaticStartupController.lastEvent === allowedQuestion, 'Al finalizar el arranque automático, Repetir debe apuntar a la pregunta vigente.');

    const blockedSpeech = new MockSpeechSynthesis();
    const blocked = loadController(blockedSpeech);
    const blockedController = new blocked.Controller({speechApi: blockedSpeech, utteranceConstructor: MockUtterance});
    blockedController.activateAutomatically({type: 'WELCOME', text: 'Audio bloqueado.'});
    await wait(1300);
    assert(blockedController.state === blocked.States.AUDIO_BLOCKED, 'La ausencia de onstart debe clasificarse como audio bloqueado.');
    assert(blockedController.unlock() === true && blockedController.state === blocked.States.SPEAKING, 'Una interacción debe desbloquear la narración pendiente.');

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
    assert(synchronousController.activateAutomatically({type: 'INTERVIEW_STARTUP', text: 'Introducción. Pregunta vigente.'}) === false, 'Una denegación síncrona debe detener el intento automático.');
    assert(synchronousController.state === synchronousBlocked.States.AUDIO_BLOCKED, 'Una denegación síncrona debe ofrecer activación de audio.');
    synchronousBlockedSpeech.userGesture = true;
    assert(synchronousController.unlock() === true && synchronousBlockedSpeech.current?.text === 'Introducción. Pregunta vigente.', 'Activar audio debe reutilizar la narración pendiente con un gesto real.');

    const atomicBlockedSpeech = new SynchronousAutoplayBlockedSpeech();
    const atomicBlocked = loadController(atomicBlockedSpeech);
    const atomicBlockedController = new atomicBlocked.Controller({speechApi: atomicBlockedSpeech, utteranceConstructor: MockUtterance});
    const startupIntro = {type: 'RESUME', text: 'Continuemos con tu entrevista.'};
    const startupQuestion = {type: 'QUESTION', stable_id: 'M02:family:heart', text: '¿Algún familiar tiene enfermedades del corazón?'};
    assert(atomicBlockedController.activateAutomatically(startupIntro, null, startupQuestion) === false, 'Una denegación síncrona debe dejar el arranque automático pendiente de activación.');
    await wait(1300);
    assert(atomicBlockedController.state === atomicBlocked.States.AUDIO_BLOCKED, 'El inicio automático bloqueado debe conservar un estado accionable.');
    atomicBlockedSpeech.userGesture = true;
    assert(atomicBlockedController.unlock() === true, 'Un clic en Activar audio debe desbloquear el inicio compuesto.');
    assert(atomicBlockedSpeech.current?.text.includes(startupIntro.text) && atomicBlockedSpeech.current?.text.includes(startupQuestion.text), 'El desbloqueo no debe reproducir solo la introducción.');

    const staleBlockedSpeech = new SynchronousAutoplayBlockedSpeech();
    const staleBlocked = loadController(staleBlockedSpeech);
    const staleBlockedController = new staleBlocked.Controller({speechApi: staleBlockedSpeech, utteranceConstructor: MockUtterance});
    assert(staleBlockedController.activateAutomatically(startupIntro, null, startupQuestion) === false, 'Una narración bloqueada debe quedar pendiente antes del cambio de pregunta.');
    staleBlockedController.cancel();
    staleBlockedSpeech.userGesture = true;
    assert(staleBlockedController.unlock() === false, 'Una pregunta obsoleta cancelada no debe reproducirse al desbloquear audio.');

    const notPausedController = new synchronousBlocked.Controller({speechApi: synchronousBlockedSpeech, utteranceConstructor: MockUtterance});
    assert(notPausedController.resume() === false, 'Reanudar no debe actuar como sustituto de Activar audio cuando no existe una pausa real.');

    console.log('VOICE_AUTOPLAY_TEST: PASS');
    console.log('AUTOMATIC_ACTIVATION: PASS');
    console.log('AUTOPLAY_BLOCKED_FALLBACK: PASS');
    console.log('ONE_TIME_UNLOCK: PASS');
    console.log('AUTO_START_INCLUDES_ACTIVE_QUESTION: PASS');
    console.log('RESUME_IS_NOT_AUDIO_UNLOCK: PASS');
})().catch(error => {
    console.error(`VOICE_AUTOPLAY_TEST: FAIL - ${error.message}`);
    process.exitCode = 1;
});
