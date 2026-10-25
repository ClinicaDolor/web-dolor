'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const {resampleMono, appendWithoutDuplicate, PatientInterviewLocalDictation} = require('./../../public/assets/js/patient-interview-local-dictation.js');

const interviewView = fs.readFileSync('./app/views/paciente/entrevista.php', 'utf8');
const m03DictationUi = fs.readFileSync('./public/assets/js/patient-interview-m03-dictation-ui.js', 'utf8');
const ticketController = fs.readFileSync('./app/Controllers/PatientInterviewController.php', 'utf8');
const m03Adapter = fs.readFileSync('./app/Services/ConversationalM03Adapter.php', 'utf8');
const m02Eligibility = interviewView.match(/const diagnoseM02DictationEligibility = \(target, input\) => \{([\s\S]*?)\n    const syncM02DictationPermission/);
const m02PermissionSync = interviewView.match(/const syncM02DictationPermission = card => \{([\s\S]*?)\n    \};/);
const m03Eligibility = interviewView.match(/const diagnoseM03DictationEligibility = \(target, input\) => \{([\s\S]*?)\n    \};/);
const m03PermissionSync = interviewView.match(/const syncM03DictationPermission = \(\) => \{([\s\S]*?)\n    \};/);
const localDictationInitializer = interviewView.match(/const ensureLocalDictation = \(\) => \{([\s\S]*?)\r?\n    \};\r?\n    ensureLocalDictation\(\);/);
assert.ok(m02Eligibility, 'M02 dictation guard exposes one focused eligibility diagnostic');
assert.ok(m02PermissionSync, 'M02 dictation permission synchronizer exists');
assert.ok(m03Eligibility, 'M03 dictation has a focused active-question authorization guard');
assert.ok(m03PermissionSync, 'M03 microphone is synchronized from the active question only');
assert.ok(localDictationInitializer, 'shared dictation client can be recovered by its initializer');
assert.match(m02Eligibility[1], /MODULE_NOT_ANSWERABLE/);
assert.match(m02Eligibility[1], /M02_WRITE_DISABLED_IN_VIEW/);
assert.match(m02Eligibility[1], /VISIBLE_QUESTION_ID_MISMATCH/);
assert.match(m02Eligibility[1], /QUESTION_NOT_EDITABLE/);
assert.match(interviewView, /isTargetAuthorized: \(target, input\) => \{[\s\S]*?targetModule === 'M02'[\s\S]*?diagnoseM02DictationEligibility\(target, input\)\.allowed[\s\S]*?targetModule === 'M03'[\s\S]*?diagnoseM03DictationEligibility\(target, input\)\.allowed[\s\S]*?return false;/,
    'shared Vosk client dispatches M02/M03 to their own guards and denies other modules');
assert.match(m03PermissionSync[1], /canInitiate\([\s\S]*permissionDenied:\s*m03MicPermissionDenied/,
    'permission-pending must not disable an otherwise authorized M03 button');
assert.match(m03PermissionSync[1], /forEach\(card\s*=>\s*\{[\s\S]*?const target = `M03:question:\$\{String\(card\.dataset\.questionId \|\| ''\)\}`;\s*const question = conversationCoordinator\.questionContract\(target\);[\s\S]*?question\?\.write_enabled === true/,
    'each M03 card must resolve its own canonical question inside the per-card callback before reading question write state');
assert.match(m03PermissionSync[1], /activeCardVisible \? ensureLocalDictation\(\) : localDictation/,
    'an active M03 question lazily recovers the shared dictation client');
assert.match(interviewView, /const startRecognition = async \(button, input\) => \{[\s\S]*?ensureLocalDictation\(\);[\s\S]*?localDictation\?\.start/,
    'an authorized click can initialize the local client before starting recognition');
assert.match(interviewView, /M02_DICTATION_BLOCKED:\$\{eligibility\.code\}/, 'local blocked state reports a technical code rather than clinical content');
assert.match(interviewView, /\['127\.0\.0\.1', '::1'\]/, 'detailed M02 diagnostics are restricted to a local loopback request');
assert.match(m02PermissionSync[1], /diagnoseM02DictationEligibility\(target, input\)/, 'visible M02 microphone must use the exact diagnosed startRecognition predicate');
assert.match(m02PermissionSync[1], /button\.hidden = false/, 'M02 microphone affordance remains visible in the original composer position');
assert.match(m02PermissionSync[1], /const unavailable = button\.matches\('\.chat-mic-unavailable'\) \|\| !canDictate/, 'unsupported or unauthorized dictation stays disabled without weakening the authorization guard');
assert.match(m02PermissionSync[1], /button\.title = button\.matches\('\.chat-mic-unavailable'\)[\s\S]*?unavailableReason/, 'disabled microphone explains the M02 read-only or inactive reason accessibly');
assert.match(interviewView, /\$localDictationJsUrl\s*=\s*\$versionedAsset\(/, 'dictation client asset must use the view cache-versioning helper');
assert.match(interviewView, /<script src="<\?=\$localDictationJsUrl;\?>"><\/script>/, 'view must load the cache-versioned dictation client');
assert.match(interviewView, /hash_file\('sha256',\s*\$filesystemPath\)/, 'content fingerprints prevent stale voice assets after same-second edits');
assert.match(interviewView, /patient-interview-local-dictation\.js'\s*,\s*'assets\/js\/patient-interview-local-dictation\.js'\s*,\s*true\)/, 'shared Vosk asset uses content-based cache invalidation');
assert.match(interviewView, /patient-interview-m03-dictation-ui\.js'\s*,\s*'assets\/js\/patient-interview-m03-dictation-ui\.js'\s*,\s*true\)/, 'M03 microphone helper uses content-based cache invalidation');
assert.match(m03Eligibility[1], /M03:question:\$\{questionId\}/, 'M03 target is derived from the active card question id');
assert.match(m03Eligibility[1], /dataset\.eligible/);
assert.match(m03Eligibility[1], /m03WriteEnabled/);
assert.match(m03Eligibility[1], /card\.dataset\.m03Saving === '1' \|\| card\.dataset\.m03Interpreting === '1'/,
    'M03 save and interpretation locks remain disabled until their owner operation clears');
assert.match(m03Eligibility[1], /isM03DictationContractEligible/);
assert.match(m03PermissionSync[1], /PatientInterviewM03DictationUi\?\.syncCard/, 'M03 rendering delegates visual mounting to the focused UI helper');
assert.match(m03PermissionSync[1], /fieldVisible[\s\S]*diagnoseM03DictationEligibility/, 'M03 renders based on visible active input while keeping authorization separately diagnosed');
assert.match(m03PermissionSync[1], /localDiagnostics:\s*main\.dataset\.m02DictationDiagnostics === '1'/,
    'safe diagnostic state remains available only through the existing local-development gate');
assert.match(m03PermissionSync[1], /last_disable_function:\s*syncResult\?\.disabledBy/,
    'diagnostic records the final function that owns the button disabled state');
assert.match(interviewView, /refreshM03DictationUi\?\.\(\)/,
    'M03 restores the authoritative mic state after operation handlers toggle controls in bulk');
assert.match(m03DictationUi, /data-m03-dictation-control/, 'M03 UI helper owns the single microphone control marker');
assert.match(m03DictationUi, /status\.textContent = reason;/,
    'patient-facing dictation status contains only its concise actionable explanation');
assert.doesNotMatch(m03DictationUi, /Diagnóstico local:|diagnosticText|question_response_persisted=/,
    'local diagnostic fields are never appended to the patient-facing status region');
assert.match(m03DictationUi, /silentEligibilityCodes\.has\(eligibility\?\.code\)/,
    'transient stale-target synchronization is silent while the microphone remains fail-closed');
assert.match(m03DictationUi, /button\.dataset\.dictationDisabledBy = enabled \? '' : 'PatientInterviewM03DictationUi\.syncCard'/,
    'the last DOM writer is recorded on the microphone while it is disabled');
assert.equal(m03DictationUi.includes('permissionPending'), false, 'UI availability has no pre-grant/pending-permission requirement');

const initializerSource = localDictationInitializer[0].replace(/\n    ensureLocalDictation\(\);$/, '');
const buildInitializer = new Function('window', 'main', 'conversationCoordinator', 'diagnoseM02DictationEligibility', 'diagnoseM03DictationEligibility', 'stopAssistantVoice', 'setRecognitionStatus',
    `let localDictation = null; ${initializerSource}; return {ensureLocalDictation, getLocalDictation: () => localDictation};`);
const m02GuardCalls = [];
const m03GuardCalls = [];
class SyntheticDictationClient { constructor(options) { this.options = options; } }
const syntheticInitializer = buildInitializer(
    {PatientInterviewLocalDictation: SyntheticDictationClient},
    {dataset: {dictationTicketUrl: '/synthetic-ticket', dictationWorkletUrl: '/synthetic-worklet', patient: '9301', csrf: 'synthetic', conversationContractVersion: 'synthetic-v1'}},
    {snapshot: () => ({currentTarget: 'M03:question:2', paused: false})},
    (target, input) => { m02GuardCalls.push(target); return {allowed: false}; },
    (target, input) => { m03GuardCalls.push(target); return {allowed: target === 'M03:question:2'}; },
    () => {}, () => {},
);
assert.equal(syntheticInitializer.getLocalDictation(), null, 'fixture starts with an uninitialized Vosk client');
const initializedM03Client = syntheticInitializer.ensureLocalDictation();
assert.ok(initializedM03Client, 'client initializes on demand when its factory is available');
assert.equal(initializedM03Client.options.isTargetAuthorized('M03:question:2', {}), true, 'M03 question 2 uses the M03 guard');
assert.equal(m03GuardCalls[0], 'M03:question:2');
assert.equal(m02GuardCalls.length, 0, 'M03 does not accidentally pass through M02 eligibility');
assert.equal(initializedM03Client.options.isTargetAuthorized('M04:question:2', {}), false, 'other modules remain denied');
assert.match(interviewView, /function bindVoiceControls\(\)[\s\S]*?syncM03DictationPermission\(\);/,
    'active M03 dictation permission is rebound with the existing voice controls');
assert.match(interviewView, /#interview-chat \.patient-interview-question\[data-question-index\] \.chat-mic-button/,
    'M03 active question microphone receives the same scoped visual treatment');
assert.match(ticketController, /LocalDictationTicketService::isEligibleM03QuestionTarget\(\$stableId, \$question, \$module\)/,
    'the existing ticket route authorizes M03 against its read contract');
assert.match(interviewView, /if \(question\.write_enabled !== true\) return \{allowed: false, code: 'QUESTION_WRITE_DISABLED'\}/,
    'M03 client continues to fail closed on the explicit per-question write contract');
assert.match(interviewView, /question_write_enabled: question\?\.write_enabled === true/,
    'the local-only diagnostic exposes the exact question write predicate without clinical values');
assert.match(ticketController, /\$moduleId === 'M03'[\s\S]*\$question\['response'\]\['version'\][\s\S]*\$expectedDictationVersion/,
    'M03 tickets compare the active module read-model version after confirmed captures; other modules retain the shared contract version');
assert.match(ticketController, /'eligible_question_ids' => \$result\['eligible_question_ids'\] \?\? null,[\s\S]*'next_question_id'/,
    'the direct M03 capture response exposes server-read eligibility for client contract reconciliation');
assert.match(m03Adapter, /'eligible_question_ids' => \$eligibleIds,[\s\S]*'next_question_id' => \$nextQuestionId/,
    'direct M03 readback returns the server-calculated eligible question identifiers');
assert.match(interviewView, /getContractVersion: \(\) => \{[\s\S]*m03Version[\s\S]*conversationContractVersion/,
    'the shared dictation client sends the current M03 read-model version for M03 and preserves the shared version for M02');
assert.match(interviewView, /applyServerReadback\([\s\S]*contract: conversationContract[\s\S]*questionId: Number\(confirmedQuestionId\)/,
    'only a confirmed M03 readback reconciles the active eligibility and target projection');
assert.match(interviewView, /M03_ACTIVE_TARGET_STALE|M03_CONTRACT_ELIGIBILITY_STALE/,
    'stale client contract conditions have explicit safe diagnostic codes');

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
const pendingM03 = {module_id: 'M03', stable_id: 'M03:question:21', question_id: 21, write_enabled: true, response: {status: 'pending', persisted: false, eligible: true}};
const answerableM03 = {state: {write_enabled: true, finalized: false}, conversation_state: {state: 'ANSWERABLE', stable_id: 'M03:question:21'}};
assert.equal(PatientInterviewLocalDictation.isM03DictationContractEligible('M03:question:21', pendingM03, answerableM03), true, 'active eligible pending M03 question is dictatable');
assert.equal(PatientInterviewLocalDictation.isM03DictationContractEligible('M03:question:21', {...pendingM03, response: {...pendingM03.response, eligible: false}}, answerableM03), false, 'ineligible M03 question is rejected');
assert.equal(PatientInterviewLocalDictation.isM03DictationContractEligible('M03:question:21', pendingM03, {...answerableM03, state: {write_enabled: false, finalized: true}}), false, 'read-only/finalized M03 is rejected');
assert.equal(PatientInterviewLocalDictation.isM03DictationContractEligible('M03:question:021', pendingM03, answerableM03), false, 'noncanonical M03 stable id is rejected');
const documentedM03 = {...pendingM03, response: {status: 'documented', persisted: true, eligible: true, editable: true}};
assert.equal(PatientInterviewLocalDictation.isM03DictationContractEligible('M03:question:21', documentedM03, answerableM03), true, 'persisted editable M03 answer is dictatable while active');
assert.equal(PatientInterviewLocalDictation.isM03DictationContractEligible('M03:question:21', documentedM03, {...answerableM03, conversation_state: {state: 'ANSWERABLE', stable_id: 'M03:question:22'}}), true, 'persisted historical M03 answer is dictatable when the server marks its edit authorization');
assert.equal(PatientInterviewLocalDictation.isM03DictationContractEligible('M03:question:21', {...documentedM03, response: {...documentedM03.response, editable: false}}, {...answerableM03, conversation_state: {state: 'ANSWERABLE', stable_id: 'M03:question:22'}}), false, 'persisted historical M03 answer without server edit authorization is rejected');

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
    let simulatedPiperPlaying = false;
    let simulatedPiperStopCount = 0;
    globalThis.fetch = async (url, request) => {
        assert.equal(simulatedPiperPlaying, false, 'Piper must be stopped before any dictation ticket request');
        clinicalTicketRequests += 1;
        clinicalTicketRequest = {url, request};
        return {ok: true, status: 200, json: async () => ({resultado: true, ticket: 'synthetic', websocket_url: 'wss://clinica.test:8766/dictation'})};
    };
    const liveDictation = new PatientInterviewLocalDictation({
        ticketUrl: '/dictation-ticket', workletUrl: '/audio-worklet.js', getCsrf: () => 'synthetic',
        getTarget: () => liveTarget, getPatientId: () => 9301, getContractVersion: () => liveTarget.startsWith('M03:') ? 'm03-contract-v1' : 'm02-contract-v1',
        isTargetAuthorized: (target, activeInput) => target === liveTarget && activeInput === input,
        stopVoice: async () => { if (simulatedPiperPlaying) { simulatedPiperPlaying = false; simulatedPiperStopCount += 1; } },
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
    liveTarget = 'M03:question:2';
    input.value = 'Texto M03 ya escrito';
    simulatedPiperPlaying = true;
    const m03Started = await liveDictation.start(button, input);
    assert.equal(m03Started, true, 'the shared Vosk client starts for an authorized M03 question');
    assert.equal(simulatedPiperStopCount, 1, 'starting M03 dictation interrupts the active Piper narration');
    assert.equal(simulatedPiperPlaying, false);
    assert.deepEqual(JSON.parse(clinicalTicketRequest.request.body), {
        idPaciente: 9301, stable_id: 'M03:question:2', contract_version: 'm03-contract-v1',
    }, 'M03 ticket carries the exact active canonical id and contract version');
    const m03Socket = sockets.at(-1);
    m03Socket.emit('message', {data: JSON.stringify({type: 'PARTIAL', text: 'respuesta ficticia parcial'})});
    assert.equal(input.value, 'Texto M03 ya escrito respuesta ficticia parcial', 'M03 transcript preserves existing text and appears in its input');
    input.value = 'Texto M03 corregido';
    input.dispatchEvent(new Event('input', {bubbles: true}));
    m03Socket.emit('message', {data: JSON.stringify({type: 'FINAL', text: 'respuesta ficticia parcial final'})});
    assert.equal(input.value, 'Texto M03 corregido final', 'manual M03 edits are preserved and already recognized words are not duplicated');
    const ticketCountBeforeTranscript = clinicalTicketRequests;
    assert.equal(ticketCountBeforeTranscript, 2, 'dictation only requests one ticket per session and never submits a clinical answer');
    liveTarget = 'M03:question:3';
    m03Socket.emit('message', {data: JSON.stringify({type: 'PARTIAL', text: 'pregunta anterior obsoleta'})});
    assert.equal(input.value, 'Texto M03 corregido final', 'late M03 transcript is discarded after target changes');
    await liveDictation.stop(true);
    const mediaRequestsBeforeRejectedTicket = mediaRequests;
    let rejectedTicketStatus;
    globalThis.fetch = async () => ({ok: false, status: 409, json: async () => ({resultado: false})});
    const staleM03Dictation = new PatientInterviewLocalDictation({
        ticketUrl: '/dictation-ticket', workletUrl: '/audio-worklet.js',
        getTarget: () => 'M03:question:2', getPatientId: () => 9301, getContractVersion: () => 'm03-contract-v1',
        isTargetAuthorized: target => target === 'M03:question:2',
        setStatus: (kind, message) => { rejectedTicketStatus = {kind, message}; },
    });
    assert.equal(await staleM03Dictation.start(button, input), false, 'stale server authorization fails closed');
    assert.equal(mediaRequests, mediaRequestsBeforeRejectedTicket, 'a rejected ticket never requests microphone capture');
    assert.equal(rejectedTicketStatus.kind, 'ERROR');
    assert.match(rejectedTicketStatus.message, /dejó de estar activa o editable/i, 'HTTP 409 has a safe and specific recovery instruction');
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
