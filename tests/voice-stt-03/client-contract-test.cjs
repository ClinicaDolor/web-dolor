'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const {resampleMono, appendWithoutDuplicate, PatientInterviewLocalDictation} = require('./../../public/assets/js/patient-interview-local-dictation.js');

const interviewView = fs.readFileSync('./app/views/paciente/entrevista.php', 'utf8');
const m02Eligibility = interviewView.match(/const diagnoseM02DictationEligibility = \(target, input\) => \{([\s\S]*?)\n    const syncM02DictationPermission/);
const m02PermissionSync = interviewView.match(/const syncM02DictationPermission = card => \{([\s\S]*?)\n    \};/);
assert.ok(m02Eligibility, 'M02 dictation guard exposes one focused eligibility diagnostic');
assert.ok(m02PermissionSync, 'M02 dictation permission synchronizer exists');
assert.match(m02Eligibility[1], /MODULE_NOT_ANSWERABLE/);
assert.match(m02Eligibility[1], /M02_WRITE_DISABLED_IN_VIEW/);
assert.match(m02Eligibility[1], /VISIBLE_QUESTION_ID_MISMATCH/);
assert.match(m02Eligibility[1], /QUESTION_NOT_EDITABLE/);
assert.match(interviewView, /isTargetAuthorized: \(target, input\) => diagnoseM02DictationEligibility\(target, input\)\.allowed/, 'Vosk authorization must delegate to the diagnosed guard without duplicating policy');
assert.match(interviewView, /M02_DICTATION_BLOCKED:\$\{eligibility\.code\}/, 'local blocked state reports a technical code rather than clinical content');
assert.match(interviewView, /\['127\.0\.0\.1', '::1'\]/, 'detailed M02 diagnostics are restricted to a local loopback request');
assert.match(m02PermissionSync[1], /diagnoseM02DictationEligibility\(target, input\)/, 'visible M02 microphone must use the exact diagnosed startRecognition predicate');
assert.match(m02PermissionSync[1], /button\.hidden = false/, 'M02 microphone affordance remains visible in the original composer position');
assert.match(m02PermissionSync[1], /const unavailable = button\.matches\('\.chat-mic-unavailable'\) \|\| !canDictate/, 'unsupported or unauthorized dictation stays disabled without weakening the authorization guard');
assert.match(m02PermissionSync[1], /button\.title = button\.matches\('\.chat-mic-unavailable'\)[\s\S]*?unavailableReason/, 'disabled microphone explains the M02 read-only or inactive reason accessibly');
assert.match(interviewView, /\$localDictationJsUrl\s*=\s*\$versionedAsset\(/, 'dictation client asset must use the view cache-versioning helper');
assert.match(interviewView, /<script src="<\?=\$localDictationJsUrl;\?>"><\/script>/, 'view must load the cache-versioned dictation client');

for (const inputRate of [44100, 48000]) {
    const samples = new Float32Array(inputRate);
    for (let index = 0; index < samples.length; index += 1) samples[index] = Math.sin(index / 17) * 0.5;
    let state;
    let output = 0;
    const chunkSize = Math.floor(inputRate / 13);
    for (let offset = 0; offset < samples.length; offset += chunkSize) {
        const result = resampleMono(samples.subarray(offset, Math.min(samples.length, offset + chunkSize)), inputRate, 16000, state);
        state = result.state;
        output += result.samples.length;
    }
    assert.ok(Math.abs(output - 16000) <= 1, `${inputRate} Hz resampling length`);
}

assert.equal(appendWithoutDuplicate('dolor de cabeza', 'cabeza desde ayer'), 'dolor de cabeza desde ayer');
assert.equal(appendWithoutDuplicate('Sí, uso medicamento', 'uso medicamento'), 'Sí, uso medicamento');

const pendingM02 = {module_id: 'M02', stable_id: 'M02:family:diabetes', write_enabled: true, response: {status: 'pending', persisted: false}};
const savedM02 = {...pendingM02, response: {status: 'documented', persisted: true}};
const answerableM02 = {state: {write_enabled: true, finalized: false}, conversation_state: {state: 'ANSWERABLE', stable_id: 'M02:family:diabetes'}};
assert.equal(PatientInterviewLocalDictation.isM02DictationContractEligible('M02:family:diabetes', pendingM02, answerableM02), true, 'pending active M02 question is dictatable');
assert.equal(PatientInterviewLocalDictation.isM02DictationContractEligible('M02:family:diabetes', savedM02, {...answerableM02, conversation_state: {state: 'ANSWERABLE', stable_id: 'M02:family:other'}}), true, 'persisted historical M02 response is eligible when the read model remains answerable');
assert.equal(PatientInterviewLocalDictation.isM02DictationContractEligible('M02:family:diabetes', {...savedM02, response: {status: 'documented', persisted: false}}, answerableM02), false, 'unpersisted documented state is rejected');
assert.equal(PatientInterviewLocalDictation.isM02DictationContractEligible('M02:family:diabetes', savedM02, {...answerableM02, state: {write_enabled: false, finalized: true}}), false, 'finalized/read-only M02 is rejected');
assert.equal(PatientInterviewLocalDictation.isM02DictationContractEligible('M03:question:1', {...pendingM02, module_id: 'M03', stable_id: 'M03:question:1'}, answerableM02), false, 'non-M02 question is rejected');

let mediaRequests = 0;
Object.defineProperty(globalThis, 'isSecureContext', {configurable: true, value: false});
Object.defineProperty(globalThis, 'navigator', {configurable: true, value: {mediaDevices: {getUserMedia: async () => { mediaRequests += 1; }}}});
globalThis.AudioContext = class {};
globalThis.AudioWorkletNode = class {};
globalThis.WebSocket = class {};
let status;
const dictation = new PatientInterviewLocalDictation({
    getTarget: () => 'M02:family:diabetes', getPatientId: () => 9301, getContractVersion: () => 'synthetic',
    setStatus: (kind, message) => { status = {kind, message}; },
});
const button = {disabled: false, classList: {add() {}, remove() {}}, setAttribute() {}};
const inputListeners = new Map();
const input = {
    value: '', disabled: false, readOnly: false, isConnected: true,
    addEventListener(type, callback) { const callbacks = inputListeners.get(type) || new Set(); callbacks.add(callback); inputListeners.set(type, callbacks); },
    removeEventListener(type, callback) { inputListeners.get(type)?.delete(callback); },
    dispatchEvent(event) { for (const callback of inputListeners.get(event.type) || []) callback(event); return true; },
};
dictation.start(button, input).then(async result => {
    assert.equal(result, false);
    assert.equal(mediaRequests, 0, 'no microphone request outside a secure context');
    assert.equal(status.kind, 'UNAVAILABLE');
    Object.defineProperty(globalThis, 'isSecureContext', {configurable: true, value: true});
    globalThis.location = {href: 'https://clinica.test:8443/historia-clinica/entrevista'};
    const expectedStream = {getTracks: () => [{stop() {}}]};
    globalThis.navigator.mediaDevices.getUserMedia = async () => { mediaRequests += 1; return expectedStream; };
    let clinicalTicketRequests = 0;
    globalThis.fetch = async () => {
        clinicalTicketRequests += 1;
        return {ok: true, status: 200, json: async () => ({resultado: true, ticket: 'synthetic', websocket_url: 'wss://clinica.test:8766/dictation'})};
    };
    const sockets = [];
    class MockSocket {
        static OPEN = 1;
        static CLOSING = 2;
        constructor(url) { this.url = url; sockets.push(this); this.readyState = 1; this.bufferedAmount = 0; this.listeners = new Map(); queueMicrotask(() => this.emit('open', {})); }
        addEventListener(type, callback) { const callbacks = this.listeners.get(type) || new Set(); callbacks.add(callback); this.listeners.set(type, callbacks); }
        removeEventListener(type, callback) { this.listeners.get(type)?.delete(callback); }
        emit(type, event) { for (const callback of this.listeners.get(type) || []) callback(event); }
        send(payload) { if (typeof payload === 'string' && JSON.parse(payload).type === 'AUTH') queueMicrotask(() => this.emit('message', {data: JSON.stringify({type: 'READY', sample_rate: 16000})})); }
        close() { this.readyState = 3; }
    }
    globalThis.WebSocket = MockSocket;
    globalThis.AudioContext = class {
        constructor() { this.sampleRate = 48000; this.state = 'running'; this.audioWorklet = {addModule: async () => {}}; }
        async resume() {}
        createMediaStreamSource(stream) { assert.equal(stream, expectedStream, 'audio graph must use the granted microphone stream'); return {connect() {}, disconnect() {}}; }
        createGain() { return {gain: {value: 1}, connect() {}, disconnect() {}}; }
        async close() { this.state = 'closed'; }
    };
    globalThis.AudioWorkletNode = class {
        constructor() { this.port = {onmessage: null, postMessage() {}, addEventListener() {}, removeEventListener() {}, start() {}}; }
        connect() {}
        disconnect() {}
    };
    const unauthorizedDictation = new PatientInterviewLocalDictation({
        ticketUrl: '/dictation-ticket', getTarget: () => 'M02:family:diabetes',
        getPatientId: () => 9301, getContractVersion: () => 'synthetic',
        isTargetAuthorized: () => false, setStatus: (kind, message) => { status = {kind, message}; },
    });
    const deniedStart = await unauthorizedDictation.start(button, input);
    assert.equal(deniedStart, false, 'dictation fails closed unless the active question is explicitly authorized');
    assert.equal(clinicalTicketRequests, 0, 'unauthorized question cannot request a clinical ticket');
    assert.equal(mediaRequests, 0, 'unauthorized question cannot request microphone access');
    assert.equal(status.kind, 'UNAVAILABLE');
    const nonM02Dictation = new PatientInterviewLocalDictation({
        ticketUrl: '/dictation-ticket', getTarget: () => 'M03:alcohol:frequency',
        getPatientId: () => 9301, getContractVersion: () => 'synthetic',
        isTargetAuthorized: () => true,
    });
    assert.equal(await nonM02Dictation.start(button, input), false, 'clinical dictation client is restricted to M02 targets');
    assert.equal(clinicalTicketRequests, 0, 'non-M02 target cannot request a clinical ticket');
    assert.equal(mediaRequests, 0, 'non-M02 target cannot request microphone access');

    let liveTarget = 'M02:family:diabetes';
    let clinicalTicketRequest;
    globalThis.fetch = async (url, request) => {
        clinicalTicketRequests += 1;
        clinicalTicketRequest = {url, request};
        return {ok: true, status: 200, json: async () => ({resultado: true, ticket: 'synthetic', websocket_url: 'wss://clinica.test:8766/dictation'})};
    };
    const liveDictation = new PatientInterviewLocalDictation({
        ticketUrl: '/dictation-ticket', workletUrl: '/audio-worklet.js', getCsrf: () => 'synthetic',
        getTarget: () => liveTarget, getPatientId: () => 9301, getContractVersion: () => 'm02-contract-v1',
        isTargetAuthorized: (target, activeInput) => target === liveTarget && activeInput === input,
        setStatus: (kind, message) => { status = {kind, message}; },
    });
    const liveStarted = await liveDictation.start(button, input);
    assert.equal(liveStarted, true, 'a locally authorized microphone stream should initialize the audio graph');
    assert.equal(clinicalTicketRequest.url, '/dictation-ticket');
    assert.deepEqual(JSON.parse(clinicalTicketRequest.request.body), {
        idPaciente: 9301, stable_id: 'M02:family:diabetes', contract_version: 'm02-contract-v1',
    }, 'clinical ticket binds patient, active M02 stable id and contract version');
    assert.equal(clinicalTicketRequests, 1);
    assert.equal(mediaRequests, 1, 'microphone is requested only after the ticket and WSS READY');
    const liveSocket = sockets.at(-1);
    liveSocket.emit('message', {data: JSON.stringify({type: 'PARTIAL', text: 'respuesta ficticia provisional'})});
    assert.equal(input.value, 'respuesta ficticia provisional', 'authorized M02 partial transcript updates the active input');
    input.value = 'corrección manual';
    input.dispatchEvent(new Event('input', {bubbles: true}));
    liveSocket.emit('message', {data: JSON.stringify({type: 'PARTIAL', text: 'respuesta ficticia provisional ampliada'})});
    assert.equal(input.value, 'corrección manual ampliada', 'manual edits are retained when later local transcript arrives');
    liveSocket.emit('message', {data: JSON.stringify({type: 'FINAL', text: 'respuesta ficticia provisional ampliada'})});
    assert.equal(input.value, 'corrección manual ampliada', 'final transcript does not duplicate already displayed words');
    liveTarget = 'M03:alcohol:frequency';
    liveSocket.emit('message', {data: JSON.stringify({type: 'PARTIAL', text: 'pregunta obsoleta'})});
    assert.equal(input.value, 'corrección manual ampliada', 'late transcripts are ignored after the active target changes');
    await liveDictation.stop(true);
    globalThis.location.href = 'https://clinica.test/historia-clinica/entrevista';
    globalThis.location.origin = 'https://clinica.test';
    const socketValidator = new PatientInterviewLocalDictation({});
    assert.equal(socketValidator._validateSocketUrl('wss://clinica.test:8766/dictation'), 'wss://clinica.test:8766/dictation');
    assert.throws(() => socketValidator._validateSocketUrl('wss://clinica.test.evil:8766/dictation'));
    let diagnosticTicketRequest;
    globalThis.location.origin = 'https://clinica.test:8443';
    globalThis.fetch = async (url, request) => {
        diagnosticTicketRequest = {url, request};
        return {ok: true, status: 200, json: async () => ({resultado: true, ticket: 'diagnostic-synthetic', websocket_url: 'wss://clinica.test:8767/diagnostic'})};
    };
    const diagnosticInput = {value: 'Texto previo', disabled: false, readOnly: false, isConnected: true, addEventListener() {}, removeEventListener() {}, dispatchEvent() {}};
    const diagnosticStatuses = [];
    const diagnosticClient = new PatientInterviewLocalDictation({
        mode: 'diagnostic', ticketUrl: '/historia-clinica/diagnostico-voz/ticket',
        workletUrl: '/audio-worklet.js', getCsrf: () => 'synthetic-csrf',
        getTarget: () => 'LOCAL_VOICE_DIAGNOSTIC', setStatus: (kind, message) => diagnosticStatuses.push({kind, message}),
    });
    const diagnosticStarted = await diagnosticClient.start(button, diagnosticInput);
    assert.equal(diagnosticStarted, true, 'diagnostic flow starts without clinical target or patient id');
    assert.equal(diagnosticTicketRequest.url, '/historia-clinica/diagnostico-voz/ticket');
    assert.equal(diagnosticTicketRequest.request.body, '', 'diagnostic ticket request carries no clinical identifiers');
    assert.equal(diagnosticTicketRequest.request.headers['X-M03-CSRF'], 'synthetic-csrf');
    assert.equal(sockets.at(-1).url, 'wss://clinica.test:8767/diagnostic');
    sockets.at(-1).emit('message', {data: JSON.stringify({type: 'PARTIAL', text: 'palabras provisionales'})});
    assert.equal(diagnosticInput.value, 'Texto previo palabras provisionales', 'partial transcript appears progressively and preserves prior text');
    sockets.at(-1).emit('message', {data: JSON.stringify({type: 'FINAL', text: 'palabras provisionales confirmadas'})});
    assert.equal(diagnosticInput.value, 'Texto previo palabras provisionales confirmadas', 'final transcript replaces the partial without duplication');
    await diagnosticClient.stop(true);
    assert.ok(diagnosticStatuses.some(item => item.kind === 'RECOGNIZING'));
    const diagnosticSocketValidator = new PatientInterviewLocalDictation({mode: 'diagnostic'});
    assert.equal(diagnosticSocketValidator._validateSocketUrl('wss://clinica.test:8767/diagnostic'), 'wss://clinica.test:8767/diagnostic');
    assert.throws(() => diagnosticSocketValidator._validateSocketUrl('wss://clinica.test:8766/diagnostic'));
    globalThis.location.origin = 'https://clinica.test';
    assert.throws(() => diagnosticSocketValidator._validateSocketUrl('wss://clinica.test:8767/diagnostic'), 'diagnostic WSS is restricted to isolated HTTPS origin');
    process.stdout.write('PASS local dictation client contracts; no auto-submit; synthetic secure startup\n');
}).catch(error => { console.error(error); process.exitCode = 1; });
