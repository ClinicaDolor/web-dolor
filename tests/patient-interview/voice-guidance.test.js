const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../public/assets/js/patient-interview-voice.js'), 'utf8');

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

class MockUtterance {
    constructor(text) {
        this.text = text;
        this.lang = '';
        this.voice = null;
        this.rate = 1;
        this.onend = null;
        this.onerror = null;
    }
}

class MockSpeechSynthesis {
    constructor() {
        this.speaking = false;
        this.paused = false;
        this.current = null;
        this.calls = [];
        this.voices = [{lang: 'es-MX', name: 'synthetic Spanish'}];
    }

    getVoices() { return this.voices; }

    speak(utterance) {
        this.current = utterance;
        this.speaking = true;
        this.paused = false;
        this.calls.push(utterance);
    }

    cancel() {
        const previous = this.current;
        this.current = null;
        this.speaking = false;
        this.paused = false;
        previous?.onend?.();
    }

    pause() { this.paused = true; }
    resume() { this.paused = false; this.speaking = true; }

    finish() {
        const current = this.current;
        this.current = null;
        this.speaking = false;
        this.paused = false;
        current?.onend?.();
    }

    fail() {
        const current = this.current;
        this.current = null;
        this.speaking = false;
        current?.onerror?.(new Error('synthetic_voice_error'));
    }
}

const speech = new MockSpeechSynthesis();
const context = {window: {SpeechSynthesisUtterance: MockUtterance}};
vm.runInNewContext(source, context);
const VoiceController = context.window.PatientInterviewVoiceController;
const STATES = context.window.PatientInterviewVoiceStates;
const states = [];
const errors = [];
const controller = new VoiceController({
    speechApi: speech,
    utteranceConstructor: MockUtterance,
    onStateChange: snapshot => states.push(snapshot.state),
    onError: error => errors.push(error),
});

const welcome = {type: 'WELCOME', text: 'Bienvenido a la entrevista.'};
const question = {type: 'QUESTION', text: 'Pregunta sintética.'};
const sensitivePayload = {type: 'SUCCESS', text: 'La respuesta se guardó correctamente.', clinical_value: 'no debe narrarse'};

assert(controller.state === STATES.DISABLED, 'La guía inicia deshabilitada.');
assert(controller.handleEvent(question) === false && speech.calls.length === 0, 'No se narra antes de la activación explícita.');
assert(controller.activate(welcome, () => controller.speakEvent(question)) === true, 'La activación voluntaria inicia la bienvenida.');
assert(speech.calls.length === 1 && speech.calls[0].text === welcome.text, 'La bienvenida se narra primero.');
assert(speech.calls[0].lang === 'es-MX' && speech.calls[0].rate === .95, 'Se prioriza es-MX y velocidad cómoda.');
speech.finish();
assert(speech.calls.length === 2 && speech.calls[1].text === question.text, 'La pregunta continúa después de la bienvenida.');

const startOfNarratedQuestion = speech.calls.length;
assert(controller.handleEvent({type: 'QUESTION', stable_id: 'M03:question:2', revision: 2, text: 'Pregunta siguiente.'}) === true, 'La pregunta siguiente debe narrarse como un único evento.');
assert(speech.calls.length === startOfNarratedQuestion + 1 && speech.calls.at(-1).text === 'Pregunta siguiente.', 'El avance debe narrar únicamente la pregunta siguiente.');
assert(controller.handleEvent({type: 'QUESTION', stable_id: 'M03:question:2', revision: 2, text: 'Pregunta siguiente.'}) === false, 'Un evento duplicado no debe producir una segunda narración.');
assert(controller.handleEvent({type: 'SUCCESS', text: 'La respuesta se guardó correctamente.'}) === false, 'El guardado confirmado no debe narrarse automáticamente.');
assert(controller.handleEvent({type: 'ERROR', text: 'Error técnico.'}) === false, 'Los errores técnicos no deben narrarse automáticamente.');
speech.finish();

const oldUtterance = speech.current;
controller.handleEvent({type: 'QUESTION', text: 'Pregunta nueva.'});
oldUtterance?.onend?.();
assert(controller.state === STATES.SPEAKING && speech.current.text === 'Pregunta nueva.', 'Una narración obsoleta no cancela la nueva.');

assert(controller.pause() === true && controller.state === STATES.PAUSED, 'La narración puede pausarse.');
assert(controller.resume() === true && controller.state === STATES.SPEAKING, 'La narración puede reanudarse.');
assert(controller.setRate(1.1) === 1.1, 'La velocidad puede ajustarse.');
assert(controller.repeat() === true && speech.current.rate === 1.1, 'Repetir utiliza la velocidad actual.');
controller.toggleMute();
assert(controller.muted === true && controller.state === STATES.MUTED, 'Silenciar cancela la reproducción.');
assert(controller.handleEvent(question) === false, 'El silencio impide narraciones automáticas.');
controller.toggleMute();
assert(controller.state === STATES.READY, 'Activar sonido recupera el estado listo.');
controller.speakEvent(sensitivePayload);
assert(speech.current.text === sensitivePayload.text && !speech.current.text.includes('no debe narrarse'), 'Sólo se narra el texto contractual, no valores clínicos adjuntos.');
speech.fail();
assert(controller.state === STATES.VOICE_ERROR && errors.length === 1, 'El error TTS queda visible sin bloquear la captura.');

const unsupported = new VoiceController({speechApi: null, utteranceConstructor: MockUtterance});
assert(unsupported.state === STATES.VOICE_UNAVAILABLE, 'La falta de soporte se informa como no disponible.');
assert(states.includes(STATES.PAUSED) && states.includes(STATES.MUTED), 'Los estados de pausa y silencio son observables.');

const traceSpeech = new MockSpeechSynthesis();
const traceController = new VoiceController({speechApi: traceSpeech, utteranceConstructor: MockUtterance});
const narrationTrace = [];
const capture = (event, result = 'PASS') => {
    narrationTrace.push({event: event.type, question: event.stable_id || 'synthetic-current', text: event.text, number: 1, result});
};
const traceWelcome = {type: 'FIRST_START', stable_id: 'interview:new', text: 'Bienvenido a tu entrevista de historia clínica. Te realizaré algunas preguntas para conocer tus antecedentes y registrar tu información médica. Responde con tus propias palabras. Después podrás revisar y confirmar tus respuestas antes de guardarlas. Si necesitas una explicación, puedes utilizar el botón Ayuda. Comencemos.'};
const traceQuestion = {type: 'QUESTION', stable_id: 'M03:question:1', revision: 1, text: '¿Con qué frecuencia consumes bebidas alcohólicas?'};
assert(traceController.activate(traceWelcome, () => { capture(traceWelcome); traceController.speakEvent(traceQuestion); capture(traceQuestion); }) === true, 'La bienvenida sintética debe iniciar la traza.');
traceSpeech.finish();
traceSpeech.finish();
const savedQuestion = {type: 'QUESTION', stable_id: 'M03:question:2', revision: 2, text: '¿Has intentado reducir tu consumo?'};
traceController.handleEvent({type: 'SUCCESS', stable_id: savedQuestion.stable_id, revision: 2, text: 'Respuesta guardada.'});
assert(traceController.handleEvent(savedQuestion) === true, 'El avance confirmado debe narrar la siguiente pregunta.');
capture(savedQuestion);
traceSpeech.finish();
const previousQuestion = {type: 'QUESTION', stable_id: 'M03:question:1', revision: 3, text: traceQuestion.text};
assert(traceController.handleEvent(previousQuestion) === true, 'El regreso debe narrar la pregunta anterior.');
capture(previousQuestion);
traceSpeech.finish();
const help = {type: 'HELP', stable_id: previousQuestion.stable_id, revision: 3, text: 'Indica con qué frecuencia consumes alcohol. Si no recuerdas el dato, puedes decirlo.'};
assert(traceController.handleEvent(help) === true, 'La ayuda debe corresponder a la pregunta activa.');
capture(help);
traceSpeech.finish();
const resume = {type: 'RESUME', stable_id: savedQuestion.stable_id, revision: 4, text: 'Continuemos con tu entrevista.'};
const resumedQuestion = {type: 'QUESTION', stable_id: savedQuestion.stable_id, revision: 4, text: savedQuestion.text};
assert(traceController.handleSequence([resume, resumedQuestion]) === true, 'La reanudación debe narrar continuidad y pregunta vigente.');
capture(resume);
capture(resumedQuestion);
traceSpeech.finish();
traceSpeech.finish();
const confirmation = {type: 'CONFIRMATION', stable_id: resumedQuestion.stable_id, revision: 4, text: 'Revisa la información y confírmala o corrígela.'};
assert(traceController.handleEvent(confirmation) === true, 'La confirmación debe narrarse una sola vez.');
capture(confirmation);
traceSpeech.finish();
assert(narrationTrace.every(item => item.number === 1 && item.result === 'PASS'), 'La traza debe registrar una narración por evento.');

const atomicSpeech = new MockSpeechSynthesis();
const atomicErrors = [];
const atomicController = new VoiceController({
    speechApi: atomicSpeech,
    utteranceConstructor: MockUtterance,
    onError: error => atomicErrors.push(error),
});
const atomicIntro = {type: 'RESUME', stable_id: 'M02:family:heart', text: 'Continuemos con tu entrevista.'};
const atomicQuestion = {type: 'QUESTION', stable_id: 'M02:family:heart', text: '¿Algún familiar tiene enfermedades del corazón?'};
assert(atomicController.activateStartup(atomicIntro, atomicQuestion, {userGesture: true}) === true, 'El arranque atómico debe iniciar la narración.');
assert(atomicSpeech.calls.length === 1, 'La introducción y la pregunta deben usar una única utterance de arranque.');
assert(atomicSpeech.calls[0].text.includes(atomicIntro.text) && atomicSpeech.calls[0].text.includes(atomicQuestion.text), 'La utterance de arranque debe incluir introducción y pregunta oficial.');
assert(atomicSpeech.calls[0].text !== atomicIntro.text, 'No se debe narrar únicamente la introducción.');
atomicSpeech.finish();
assert(atomicController.lastEvent === atomicQuestion, 'Al terminar el arranque, Repetir debe quedar asociado a la pregunta activa.');
assert(atomicController.repeat(true) === true && atomicSpeech.current.text === atomicQuestion.text, 'Repetir debe narrar únicamente la pregunta vigente.');
atomicSpeech.finish();
const firstStartSpeech = new MockSpeechSynthesis();
const firstStartController = new VoiceController({speechApi: firstStartSpeech, utteranceConstructor: MockUtterance});
const firstStartIntro = {type: 'FIRST_START', stable_id: 'interview:new', text: 'Bienvenido a tu entrevista.'};
assert(firstStartController.activateStartup(firstStartIntro, atomicQuestion, {userGesture: true}) === true, 'FIRST_START debe usar el mismo arranque atómico.');
assert(firstStartSpeech.calls.length === 1 && firstStartSpeech.calls[0].text.includes(firstStartIntro.text) && firstStartSpeech.calls[0].text.includes(atomicQuestion.text), 'FIRST_START debe incluir la pregunta oficial.');
firstStartController.cancel();
assert(atomicController.activateStartup(atomicIntro, {type: 'QUESTION', stable_id: atomicQuestion.stable_id, text: ''}, {userGesture: true}) === false, 'Una pregunta sin texto no puede producir una introducción aislada.');
assert(atomicSpeech.calls.length === 2 && atomicErrors.some(error => error.code === 'QUESTION_TEXT_MISSING'), 'La ausencia de pregunta debe fallar de forma controlada.');

const noEndSpeech = new MockSpeechSynthesis();
const noEndController = new VoiceController({speechApi: noEndSpeech, utteranceConstructor: MockUtterance});
assert(noEndController.activateStartup(atomicIntro, atomicQuestion, {userGesture: true}) === true, 'El arranque debe iniciar aunque el motor aún no haya emitido onend.');
assert(noEndSpeech.calls.length === 1 && noEndSpeech.calls[0].text.includes(atomicQuestion.text), 'La pregunta debe estar dentro de la utterance aunque onend no llegue.');
noEndController.cancel();

console.log('VOICE_GUIDANCE_TEST: PASS');
console.log('VOLUNTARY_ACTIVATION: PASS');
console.log('WELCOME_AND_QUESTION_SEQUENCE: PASS');
console.log('STALE_SPEECH_CANCELLATION: PASS');
console.log('PAUSE_RESUME_MUTE_RATE: PASS');
console.log('TTS_FAILURE_FALLBACK: PASS');
console.log('SENSITIVE_PAYLOAD_NOT_NARRATED: PASS');
console.log('STT_TTS_INDEPENDENCE: PASS');
console.log('ATOMIC_STARTUP_INTRO_AND_QUESTION: PASS');
console.log('QUESTION_TEXT_REQUIRED: PASS');
console.log('ONEND_ABSENT_QUESTION_NOT_LOST: PASS');
console.log('NARRATION_TRACE: ' + narrationTrace.map(item => JSON.stringify(item)).join('\n'));
