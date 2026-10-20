const fs = require('fs');
const path = require('path');
const vm = require('vm');

const coordinatorSource = fs.readFileSync(path.resolve(__dirname, '../../public/assets/js/patient-interview-coordinator.js'), 'utf8');
const voiceSource = fs.readFileSync(path.resolve(__dirname, '../../public/assets/js/patient-interview-voice.js'), 'utf8');

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

class MockUtterance {
    constructor(text) { this.text = text; this.lang = ''; this.rate = 1; this.onend = null; this.onerror = null; }
}

class MockSpeechSynthesis {
    constructor() { this.calls = []; this.current = null; this.speaking = false; this.paused = false; }
    getVoices() { return [{lang: 'es-MX', name: 'synthetic'}]; }
    speak(utterance) { this.current = utterance; this.speaking = true; this.calls.push(utterance); }
    cancel() { this.speaking = false; }
    finish() { const current = this.current; this.current = null; this.speaking = false; current?.onend?.(); }
}

const contract = {
    version: 'synthetic-contract-v11',
    modules: {
        M03: {
            version: 'm03-r1',
            questions: [
                {stable_id: 'M03:question:1', module_id: 'M03', theme_id: 1, theme: 'Tabaquismo', question_id: 1, visible_text: '¿Fumas?', instruction: 'Responde sobre tabaco.', help: 'Ayuda de tabaquismo.', voice: {QUESTION: 'Pregunta de tabaquismo.', INSTRUCTION: 'Instrucción de tabaquismo.', HELP: 'Ayuda de tabaquismo.'}},
                {stable_id: 'M03:question:2', module_id: 'M03', theme_id: 2, theme: 'Alcoholismo', question_id: 2, visible_text: '¿Consumes alcohol?', instruction: 'Responde sobre alcohol.', help: 'Ayuda de alcoholismo.', voice: {QUESTION: 'Pregunta de alcoholismo.', INSTRUCTION: 'Instrucción de alcoholismo.', HELP: 'Ayuda de alcoholismo.'}},
            ],
        },
        M04: {
            version: 'm04-r1',
            questions: [
                {stable_id: 'M04:record:new', module_id: 'M04', question_id: null, visible_text: '¿Te realizaron alguna cirugía?', instruction: 'Responde Sí o No.', help: 'Conserva la precisión temporal.', voice: {QUESTION: 'Pregunta de cirugía.', FIRST_START: 'Ahora revisaremos tus antecedentes quirúrgicos.', RESUME: 'Continuemos con tus antecedentes quirúrgicos.', HELP: 'Ayuda de cirugía.'}},
            ],
        },
    },
    question_index: {},
};
contract.modules.M03.questions.forEach(question => { contract.question_index[question.stable_id] = question; });
contract.modules.M04.questions.forEach(question => { contract.question_index[question.stable_id] = question; });

const coordinatorContext = {window: {AbortController: global.AbortController, Response: global.Response}};
vm.runInNewContext(coordinatorSource, coordinatorContext);
const Coordinator = coordinatorContext.window.PatientInterviewCoordinator;
const events = [];
const coordinator = new Coordinator({patientId: 9301, contract, onVoiceEvent: event => events.push(event)});

coordinator.setTarget('M03:question:1');
assert(events.at(-1).text === 'Pregunta de tabaquismo.', 'La primera pregunta debe narrar Tabaquismo.');
assert(coordinator.snapshot().context.theme === 'Tabaquismo' && coordinator.snapshot().context.question_id === 1, 'El contexto de Tabaquismo debe conservar tema e identificador.');

coordinator.setTarget('M03:question:2');
assert(events.at(-1).text === 'Pregunta de alcoholismo.', 'Alcoholismo debe narrar su propia pregunta, no Tabaquismo.');
assert(coordinator.snapshot().context.theme === 'Alcoholismo' && coordinator.snapshot().context.question_id === 2, 'El contexto de Alcoholismo debe reemplazar al anterior.');
assert(coordinator.voiceEvent('HELP', 'M03:question:2', false).text === 'Ayuda de alcoholismo.', 'La ayuda debe seguir el mismo contexto activo.');

coordinator.setTarget('M03:question:999');
assert(coordinator.questionContract('M03:question:999') === null, 'Un identificador desconocido no debe caer en la primera pregunta.');
assert(coordinator.voiceEvent('QUESTION', 'M03:question:999', false).text === null, 'Un identificador desconocido no debe narrar contenido de otro tema.');

coordinator.setTarget('M04:record:new');
assert(coordinator.snapshot().currentTarget === 'M04:record:new', 'M04 debe conservar el stable_id canónico de su registro nuevo.');
assert(events.at(-1).text === 'Pregunta de cirugía.', 'M04 debe narrar la pregunta canónica activa.');
assert(coordinator.voiceEvent('FIRST_START', 'M04:record:new', false).text === 'Ahora revisaremos tus antecedentes quirúrgicos.', 'M04 debe narrar su introducción específica.');
assert(coordinator.voiceEvent('RESUME', 'M04:record:new', false).text === 'Continuemos con tus antecedentes quirúrgicos.', 'M04 debe narrar su reanudación específica.');

['M03:question:1', 'M03:question:2', 'M03:question:1'].forEach(target => coordinator.setTarget(target));
const rapidTexts = events.slice(-3).map(event => event.text);
assert(rapidTexts.join('|') === 'Pregunta de tabaquismo.|Pregunta de alcoholismo.|Pregunta de tabaquismo.', 'Los cambios rápidos deben conservar el orden y contexto de cada pregunta.');

const speech = new MockSpeechSynthesis();
const voiceContext = {window: {SpeechSynthesisUtterance: MockUtterance}};
vm.runInNewContext(voiceSource, voiceContext);
const VoiceController = voiceContext.window.PatientInterviewVoiceController;
const voice = new VoiceController({speechApi: speech, utteranceConstructor: MockUtterance});
voice.activate({type: 'WELCOME', text: 'Bienvenida.'});
speech.finish();
voice.speakEvent({type: 'QUESTION', stable_id: 'M03:question:1', text: 'Pregunta de tabaquismo.'}, {force: true});
const stale = speech.current;
voice.speakEvent({type: 'QUESTION', stable_id: 'M03:question:2', text: 'Pregunta de alcoholismo.'}, {force: true});
stale?.onend?.();
assert(speech.current?.text === 'Pregunta de alcoholismo.', 'Un evento tardío de Tabaquismo no debe cancelar la narración de Alcoholismo.');
assert(voice.lastEvent?.stable_id === 'M03:question:2', 'El motor debe conservar el stable_id más reciente.');

console.log('VOICE_CONTEXT_INTEGRITY_TEST: PASS');
console.log('TOBACCO_TO_ALCOHOL_CONTEXT: PASS');
console.log('HELP_CONTEXT_ALIGNMENT: PASS');
console.log('UNKNOWN_TARGET_FAIL_CLOSED: PASS');
console.log('RAPID_CHANGE_ORDER: PASS');
console.log('STALE_TTS_EVENT_IGNORED: PASS');
