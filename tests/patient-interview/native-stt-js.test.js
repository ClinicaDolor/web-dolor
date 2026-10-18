const fs = require('fs');
const path = require('path');
const vm = require('vm');

const viewPath = path.resolve(__dirname, '../../app/views/paciente/entrevista.php');
const source = fs.readFileSync(viewPath, 'utf8');

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

assert(!source.includes('recognition.processLocally = true'), 'No debe forzarse processLocally.');
const recognitionBlockStart = source.indexOf('const startRecognition');
const recognitionBlockEnd = source.indexOf('function bindVoiceControls', recognitionBlockStart);
assert(recognitionBlockStart >= 0 && recognitionBlockEnd > recognitionBlockStart, 'No se encontró el bloque de SpeechRecognition.');
const recognitionBlock = source.slice(recognitionBlockStart, recognitionBlockEnd);
assert(!recognitionBlock.includes('window.confirm(') && !recognitionBlock.includes('alert('), 'No debe existir un diálogo experimental al pulsar el micrófono.');
assert(source.includes('Confirma explícitamente que los medicamentos no seleccionados'), 'Debe conservarse la confirmación clínica M07.');
assert(source.includes('.patient-interview-answer textarea'), 'El enlace debe admitir textarea.');
assert(source.includes('combineSpeechResults') && source.includes('const composedText'), 'Los segmentos deben combinarse sin duplicación.');
assert(source.includes('recognition.start()'), 'Debe iniciarse el reconocimiento real.');
assert(source.includes('recognition.onresult') && source.includes('recognition.onend') && source.includes('recognition.onerror'), 'Deben existir los eventos del ciclo de vida.');

const start = source.indexOf('const normalizedSpeechWords =');
const end = source.indexOf('const finishRecognition =', start);
assert(start >= 0 && end > start, 'No se encontró el bloque de sincronización de voz.');

const statusMessages = [];
const block = source.slice(start, end);
const context = {
    Event: class Event {
        constructor(type, init) {
            this.type = type;
            Object.assign(this, init || {});
        }
    },
    setStatus: message => statusMessages.push(String(message)),
};
vm.runInNewContext('const setRecognitionStatus = (state, message) => setStatus(message);\n' + block + '\nglobalThis.renderRecognitionResults = renderRecognitionResults;', context);

function result(transcript, isFinal) {
    return {0: {transcript}, isFinal};
}

function emit(state, entries, resultIndex = 0) {
    context.renderRecognitionResults({resultIndex, results: entries.map(([text, isFinal]) => result(text, isFinal))}, state);
}

const input = {
    value: '',
    events: [],
    dispatchEvent(event) { this.events.push(event); },
};
const state = {
    input,
    baseValue: '',
    finalText: '',
    interimText: '',
    resultParts: [],
    manualEdit: false,
    applying: false,
};

emit(state, [['hola', false]]);
assert(input.value === 'hola', 'El resultado provisional inicial no llegó al campo.');
emit(state, [['hola', true], ['hola buenas', false]]);
assert(input.value === 'hola buenas', 'La transición final/provisional duplicó palabras.');
emit(state, [['hola', true], ['hola buenas tardes', false]]);
assert(input.value === 'hola buenas tardes', 'El resultado provisional acumulado no se sincronizó.');
emit(state, [['hola', true], ['hola buenas', true], ['hola buenas tardes', true]]);
assert(input.value === 'hola buenas tardes', 'El resultado definitivo duplicó segmentos.');
assert(input.events.length === 4, 'Cada resultado debe actualizar el campo sin persistir.');

state.manualEdit = true;
input.value = 'hola buenas tardes corregido';
emit(state, [['hola', true], ['hola buenas', true], ['hola buenas tardes', true], ['hola buenas tardes final', true]]);
assert(input.value.startsWith('hola buenas tardes corregido'), 'La edición manual fue sobrescrita.');

const lifecycleStart = source.indexOf('const normalizedSpeechWords =');
const lifecycleEnd = source.indexOf('const inspectLocalLanguageAvailability =', lifecycleStart);
const lifecycleStatuses = [];
class MockRecognition {
    start() { this.started = true; }
    stop() { this.stopped = true; }
    abort() { this.aborted = true; }
}
const lifecycleContext = {
    Event: context.Event,
    MockRecognition,
    window: {confirm: () => { throw new Error('No debe solicitar confirmación experimental.'); }},
    setStatus: message => lifecycleStatuses.push(String(message)),
};
const lifecyclePrelude = [
    'const nativeSttEnabled = true;',
    'const speechRecognitionSupported = true;',
    'const recognitionConstructor = MockRecognition;',
    'let activeRecognition = null;',
    'let activeMicButton = null;',
    'let activeRecognitionState = null;',
    'let recognitionStartPending = false;',
    'let recognitionGeneration = 0;',
    'const conversationCoordinator = {snapshot: () => ({currentTarget: "synthetic", paused: false})};',
    'const speechApi = null;',
    'let voiceStops = 0;',
    'const stopAssistantVoice = () => { voiceStops += 1; };',
    'globalThis.getVoiceStops = () => voiceStops;',
    'const setRecognitionStatus = (state, message) => setStatus(message);',
].join('\n');
vm.runInNewContext(lifecyclePrelude + '\n' + source.slice(lifecycleStart, lifecycleEnd) + '\nglobalThis.startRecognition = startRecognition; globalThis.getActiveRecognition = () => activeRecognition;', lifecycleContext);

function control() {
    return {
        disabled: false,
        textContent: '',
        classList: {add() {}, remove() {}},
        setAttribute() {},
    };
}

(async () => {
    const lifecycleInput = {value: '', dispatchEvent() {}};
    const lifecycleButton = control();
    await lifecycleContext.startRecognition(lifecycleButton, lifecycleInput);
    const recognition = lifecycleContext.getActiveRecognition();
    assert(recognition && recognition.started === true, 'El botón no inició SpeechRecognition.');
    assert(lifecycleContext.getVoiceStops() > 0, 'El reconocimiento debe detener la narración antes de escuchar.');
    recognition.onresult({resultIndex: 0, results: [result('hola', false)]});
    assert(lifecycleInput.value === 'hola', 'El resultado del reconocimiento no actualizó el campo.');
    recognition.onerror({error: 'not-allowed'});
    assert(lifecycleStatuses.some(message => message.includes('Permiso de micrófono denegado')), 'El error de permiso no fue comunicado.');
    recognition.onend();
    assert(lifecycleButton.textContent === '🎙', 'onend no restableció el botón.');

    const stopInput = {value: '', dispatchEvent() {}};
    const stopButton = control();
    await lifecycleContext.startRecognition(stopButton, stopInput);
    const stopRecognitionInstance = lifecycleContext.getActiveRecognition();
    await lifecycleContext.startRecognition(stopButton, stopInput);
    assert(stopRecognitionInstance.stopped === true && stopButton.disabled === true, 'La detención no solicitó stop correctamente.');
    stopRecognitionInstance.onend();

    console.log('NATIVE_STT_JS_FOCUSED_TEST: PASS');
    console.log('INTERIM_SEQUENCE: PASS');
    console.log('FINAL_DEDUPLICATION: PASS');
    console.log('MANUAL_EDIT_PRESERVED: PASS');
    console.log('START_STOP_AND_ERRORS: PASS');
    console.log('NO_CLINICAL_REQUESTS: PASS');
    console.log('NO_EXPERIMENTAL_CONFIRMATION: PASS');
})().catch(error => { console.error(error.message); process.exit(1); });
