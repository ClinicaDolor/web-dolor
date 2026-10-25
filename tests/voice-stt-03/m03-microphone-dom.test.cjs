'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const {pathToFileURL} = require('node:url');

const root = path.resolve(__dirname, '..', '..');
const uiPath = path.join(root, 'public', 'assets', 'js', 'patient-interview-m03-dictation-ui.js');
const uiSource = fs.readFileSync(uiPath, 'utf8');
const dictationSource = fs.readFileSync(path.join(root, 'public', 'assets', 'js', 'patient-interview-local-dictation.js'), 'utf8');
const viewSource = fs.readFileSync(path.join(root, 'app', 'views', 'paciente', 'entrevista.php'), 'utf8');
const doctorRepeatMarkup = viewSource.match(/<button type="button" data-doctor-repeat\b[\s\S]*?<\/button>/g) || [];
assert.equal(doctorRepeatMarkup.length, 2, 'the actual view must retain both avatar repeat controls.');
const doctorStopMarkup = viewSource.match(/<button type="button" data-doctor-stop\b[\s\S]*?<\/button>/g) || [];
assert.equal(doctorStopMarkup.length, 1, 'the actual view must expose the avatar stop control in the panel.');
const doctorHelpMarkup = viewSource.match(/<button type="button" data-doctor-help\b[\s\S]*?<\/button>/g) || [];
assert.equal(doctorHelpMarkup.length, 2, 'the actual view must expose both avatar help controls.');
assert.match(viewSource, /<script src="<\?=\$m03DictationUiJsUrl;\?>"><\/script>\s*<script>/,
    'the M03 DOM helper must load before the conversation controller inline script.');
const permissionSync = viewSource.match(/const syncM03DictationPermission = \(\) => \{([\s\S]*?)\n    \};/);
assert.ok(permissionSync, 'the M03 permission synchronizer must exist.');
assert.match(permissionSync[1], /PatientInterviewM03DictationUi\?\.syncCard/,
    'the view must use the DOM helper to mount the M03 microphone.');
assert.match(permissionSync[1], /fieldVisible[\s\S]*diagnoseM03DictationEligibility/,
    'visibility is decided separately from the strict eligibility result.');
assert.match(permissionSync[1], /m03MicPermissionDenied/,
    'Chrome microphone denial must feed the disabled UI state.');
assert.match(permissionSync[1], /canInitiate\([\s\S]*permissionDenied:\s*m03MicPermissionDenied/,
    'an unresolved permission prompt must not participate in the start-enabled predicate.');
const extractViewFunction = (pattern, name) => {
    const match = viewSource.match(pattern);
    assert.ok(match, `actual view function ${name} must be extractable for the browser regression.`);
    assert.doesNotMatch(match[0], /<\?=/, `${name} must be executable without PHP rendering or a replacement implementation.`);
    return match[0].trim();
};
const extractViewFunctionWithLocalEndpoint = (pattern, name) => {
    const match = viewSource.match(pattern);
    assert.ok(match, `actual view function ${name} must be extractable for the browser regression.`);
    const endpointCount = (match[0].match(/<\?=SERVIDOR;\?>historia-clinica\/entrevista\/capturar/g) || []).length;
    assert.equal(endpointCount, 1, `${name} must contain only its one expected server endpoint.`);
    const executable = match[0].replace('<?=SERVIDOR;?>historia-clinica/entrevista/capturar', '/synthetic/entrevista/capturar');
    assert.doesNotMatch(executable, /<\?=/, `${name} must have no remaining PHP interpolation after the endpoint boundary is stubbed.`);
    return executable.trim();
};
const actualViewFunctionParts = [
    extractViewFunction(/const activeM03DictationTarget = \(\) => m03PreviousEditOnly && m03ReviewTarget\n        \? m03ReviewTarget\n        : \(conversationCoordinator\.snapshot\(\)\.currentTarget \|\| ''\);/, 'activeM03DictationTarget'),
    extractViewFunction(/const m03VoiceTargetForCard = card => \(m03ReviewOnly \|\| m03PreviousEditOnly\)\n        && card === questions\[active\]\n        \? m03ReviewTarget\n        : '';/, 'm03VoiceTargetForCard'),
    extractViewFunction(/const hasM03HistoricalEditAuthorization = \(question, returnIndex\) => \{[\s\S]*?\n    \};/, 'hasM03HistoricalEditAuthorization'),
    extractViewFunction(/const speakM03VisibleQuestion = \(question, target, options = \{\}\) => \{[\s\S]*?\n    \};/, 'speakM03VisibleQuestion'),
    extractViewFunction(/const showQuestion = \(index, options = \{\}\) => \{[\s\S]*?\n    \};/, 'showQuestion'),
    extractViewFunction(/const updateM03AnswerCount = question => \{[\s\S]*?\n    \};/, 'updateM03AnswerCount'),
    extractViewFunction(/const updateM03PreviousButton = \(\) => \{[\s\S]*?\n    \};/, 'updateM03PreviousButton'),
    extractViewFunction(/const navigateM03Previous = button => \{[\s\S]*?\n    \};/, 'navigateM03Previous'),
    extractViewFunction(/const isPersistedEligibleM03Question = question => \{[\s\S]*?\n    \};/, 'isPersistedEligibleM03Question'),
    extractViewFunction(/const navigateM03Next = button => \{[\s\S]*?\n    \};/, 'navigateM03Next'),
    extractViewFunction(/const diagnoseM03DictationEligibility = \(target, input\) => \{[\s\S]*?\n    \};/, 'diagnoseM03DictationEligibility'),
    extractViewFunction(/const syncM03DictationPermission = \(\) => \{[\s\S]*?\n    \};/, 'syncM03DictationPermission'),
    extractViewFunction(/const syncM03MicPermissionState = \(\) => \{[\s\S]*?\n    \};/, 'syncM03MicPermissionState'),
    extractViewFunction(/const stableTargetForMessage = message => \{[\s\S]*?\n    \};/, 'stableTargetForMessage'),
    extractViewFunction(/const resolveActiveRepeatEvent = \(avatarOnly = false\) => \{[\s\S]*?\n    \};/, 'resolveActiveRepeatEvent'),
    extractViewFunction(/const refreshAvatarRepeatControls = \(\) => \{[\s\S]*?\n    \};/, 'refreshAvatarRepeatControls'),
    extractViewFunction(/syncAvatarRepeatControls = refreshAvatarRepeatControls;/, 'avatar repeat synchronization hook'),
    extractViewFunction(/const refreshAvatarStopControls = \(snapshot = null\) => \{[\s\S]*?\n    \};/, 'refreshAvatarStopControls'),
    extractViewFunction(/syncAvatarStopControls = refreshAvatarStopControls;/, 'avatar stop synchronization hook'),
    extractViewFunction(/const repeatActiveQuestion = \(button = null\) => \{[\s\S]*?\n    \};/, 'repeatActiveQuestion'),
    extractViewFunction(/const bindDoctorRepeatControls = \(\) => \{[\s\S]*?\n    \};/, 'bindDoctorRepeatControls'),
    extractViewFunction(/const bindDoctorStopControls = \(\) => \{[\s\S]*?\n    \};/, 'bindDoctorStopControls'),
    extractViewFunction(/const resolveActiveHelpTarget = \(\) => \{[\s\S]*?\n    \};/, 'resolveActiveHelpTarget'),
    extractViewFunction(/const refreshAvatarHelpControls = \(\) => \{[\s\S]*?\n    \};/, 'refreshAvatarHelpControls'),
    extractViewFunction(/syncAvatarHelpControls = refreshAvatarHelpControls;/, 'avatar help synchronization hook'),
    extractViewFunction(/const bindDoctorHelpControls = \(\) => \{[\s\S]*?\n    \};/, 'bindDoctorHelpControls'),
    extractViewFunction(/const showContractHelp = button => \{[\s\S]*?\n    \};/, 'showContractHelp'),
    extractViewFunctionWithLocalEndpoint(/const captureM03ContextualAction = async \(question, button\) => \{[\s\S]*?\n    \};/, 'captureM03ContextualAction'),
    extractViewFunction(/const syncM03ContextualActions = \(\) => \{[\s\S]*?\n    \};/, 'syncM03ContextualActions'),
    extractViewFunction(/const applyM03ServerEligibility = \(data, confirmedQuestionId\) => \{[\s\S]*?\n    \};/, 'applyM03ServerEligibility'),
    extractViewFunction(/const isCurrentM03View = \(question, revision\) => [\s\S]*?;/, 'isCurrentM03View'),
    extractViewFunction(/const showNextM03Question = \(question, nextQuestionId = null, finalized = false\) => \{[\s\S]*?\n    \};/, 'showNextM03Question'),
    extractViewFunction(/document\.querySelectorAll\('\[data-m03-contextual-action\]'\)\.forEach\(button => button\.addEventListener\('click', \(\) => \{[\s\S]*?\n    \}\)\);/, 'contextual action click binding'),
    extractViewFunction(/function bindVoiceControls\(\) \{[\s\S]*?\n    \}/, 'bindVoiceControls'),
];
for (const [index, source] of actualViewFunctionParts.entries()) {
    assert.doesNotThrow(() => new Function(source), `extracted interview function ${index + 1} must form valid standalone JavaScript.`);
}
const actualViewFunctions = actualViewFunctionParts.join('\n');
const candidates = [
    process.env.CHROME_PATH,
    path.join(process.env.ProgramFiles || 'C:\\Program Files', 'Google', 'Chrome', 'Application', 'chrome.exe'),
    path.join(process.env['ProgramFiles(x86)'] || 'C:\\Program Files (x86)', 'Google', 'Chrome', 'Application', 'chrome.exe'),
].filter(Boolean);
const chrome = candidates.find(candidate => fs.existsSync(candidate));
assert.ok(chrome, 'A local Chrome installation is required for the isolated DOM test.');

const pageScript = String.raw`
(() => {
  const api = window.PatientInterviewM03DictationUi;
  const LocalDictation = window.PatientInterviewLocalDictation;
  const host = document.querySelector('#q2 .m03-input-wrap');
  const q1 = document.querySelector('#q1');
  const q2 = document.querySelector('#q2');
  const input1 = q1.querySelector('.interview-text');
  const input2 = q2.querySelector('.interview-text');
  let starts = 0;
  const check = (condition, message) => {
    if (!condition) {
      document.body.dataset.domTestError = message;
      throw new Error(message);
    }
  };
  const sync = (card, input, options = {}) => api.syncCard({
    card, active: options.active !== false, fieldVisible: options.fieldVisible !== false,
    input, eligibility: options.eligibility || {allowed: true},
    available: options.available ?? api.canInitiate({
      featureEnabled: options.featureEnabled !== false,
      clientSupported: options.clientSupported !== false,
      permissionDenied: options.permissionDenied === true,
      permissionPending: options.permissionPending === true
    }),
    unavailableReason: options.unavailableReason || (options.permissionDenied
      ? 'Chrome bloqueó el permiso del micrófono. Puedes responder por escrito.'
      : 'El dictado local no está disponible en este entorno. Puedes responder por escrito.'),
    runtimeState: options.runtimeState || 'IDLE',
    runtimeMessage: options.runtimeMessage || '',
    localDiagnostics: options.localDiagnostics || null,
    onStart: (button, selectedInput) => { check(button.parentElement === selectedInput.parentElement, 'mic must stay inside the active input wrapper'); starts += 1; }
  });

  check(api.canInitiate({featureEnabled: true, clientSupported: true, permissionDenied: false, permissionPending: true}),
    'pending Chrome permission must not block a user click');
  const first = sync(q2, input2, {permissionPending: true});
  const mic2 = q2.querySelector('[data-m03-dictation-control]');
  check(first.mounted && first.enabled, 'visible editable M03:question:2 should mount an enabled mic when Vosk is available');
  check(mic2 && mic2.parentElement === host && !mic2.disabled, 'question 2 mic must be inside its input and enabled');
  check(q2.querySelectorAll('[data-m03-dictation-control]').length === 1, 'exactly one mic must be mounted');
  sync(q2, input2);
  check(q2.querySelectorAll('[data-m03-dictation-control]').length === 1, 'rebinding must not duplicate the mic');
  mic2.click();
  check(starts === 1, 'one click must invoke one listener for the question 2 input');

  const unavailable = sync(q2, input2, {available: false});
  const note = q2.querySelector('[data-m03-dictation-status]');
  check(unavailable.mounted && !unavailable.enabled && mic2.disabled, 'editable field keeps a disabled mic when Vosk is unavailable');
  check(!note.hidden && note.textContent.includes('dictado local no está disponible'), 'unavailable reason must be visible');
  check(mic2.getAttribute('aria-describedby') === note.id, 'disabled explanation must be associated accessibly');

  const deniedPermission = sync(q2, input2, {permissionDenied: true});
  check(!deniedPermission.enabled && mic2.disabled, 'a confirmed Chrome denial stays disabled');
  check(note.textContent.includes('Chrome bloqueó') && note.textContent.includes('responder por escrito'),
    'a confirmed permission denial is explained and keeps a written-response fallback');

  const connecting = sync(q2, input2, {runtimeState: 'CONNECTING', runtimeMessage: 'Solicitando acceso al micrófono…'});
  check(!connecting.enabled && mic2.disabled && note.textContent === 'Solicitando acceso al micrófono…',
    'a click-in-progress shows the real permission/connection stage and prevents duplicate starts');
  const recovered = sync(q2, input2, {runtimeState: 'ERROR', runtimeMessage: 'El servicio local no respondió. Puedes reintentar.'});
  check(recovered.enabled && !mic2.disabled && note.textContent.includes('Puedes reintentar'),
    'a transient service error preserves a retryable microphone');

  const blocked = sync(q2, input2, {
    eligibility: {allowed: false, code: 'M03_WRITE_DISABLED'},
    localDiagnostics: {
      module_active: true, question_visible: true, question_eligible: true, question_editable: true,
      module_write_enabled: false, question_contract_available: true, question_write_enabled: true,
      question_response_status: 'pending', question_response_persisted: false,
      module_finalized: false, stable_id_matches: true,
      vosk_client_available: true, microphone_permission_state: 'granted',
    },
  });
  check(blocked.mounted && !blocked.enabled && mic2.disabled, 'unauthorized write mounts only a disabled mic');
  check(!note.hidden && note.textContent.includes('no está autorizada'), 'authorization reason must be visible without exposing identifiers');
  check(!note.textContent.includes('Diagnóstico local') && !note.textContent.includes('module_write_enabled')
    && !note.textContent.includes('M03_WRITE_DISABLED'),
    'patient-facing status must not expose diagnostic fields or machine codes even when local diagnostics are supplied');
  check(mic2.dataset.dictationDisabledBy === 'PatientInterviewM03DictationUi.syncCard',
    'the button records the final function that set its disabled state');

  const questionWriteBlocked = sync(q2, input2, {
    eligibility: {allowed: false, code: 'QUESTION_WRITE_DISABLED'},
    localDiagnostics: {
      module_active: true, question_visible: true, question_eligible: true, question_editable: true,
      module_write_enabled: true, question_contract_available: true, question_write_enabled: false,
      question_response_status: 'pending', question_response_persisted: false,
      module_finalized: false, stable_id_matches: true, vosk_client_available: true,
      microphone_permission_state: 'granted',
    },
  });
  check(!questionWriteBlocked.enabled && mic2.disabled, 'an editable field cannot override the explicit question write prohibition');
  check(note.textContent.includes('no está autorizada') && !note.textContent.includes('question_write_enabled')
    && !note.textContent.includes('question_response_status'),
    'a false question-level write flag produces only a concise patient-facing explanation');
  sync(q2, input2, {
    eligibility: {allowed: false, code: 'QUESTION_WRITE_DISABLED'},
    localDiagnostics: {
      module_active: true, question_visible: true, question_eligible: true, question_editable: true,
      module_write_enabled: true, question_contract_available: true, question_write_enabled: false,
      question_response_status: 'documented', question_response_persisted: true,
      module_finalized: false, stable_id_matches: true, vosk_client_available: true,
      microphone_permission_state: 'granted',
    },
  });
  check(mic2.disabled && note.textContent.includes('no está autorizada')
    && !note.textContent.includes('documented') && !note.textContent.includes('persisted'),
    'a persisted response without question-level write authorization remains blocked without technical diagnostics');

  const staleTarget = sync(q2, input2, {
    eligibility: {allowed: false, code: 'M03_ACTIVE_TARGET_STALE'},
    localDiagnostics: {stable_id_matches: true, question_response_status: 'documented', question_response_persisted: true},
  });
  check(staleTarget.mounted && !staleTarget.enabled && mic2.disabled && note.hidden && note.textContent === '',
    'a transient stale active-target state stays fail-closed without a visible technical or transient message');

  const hiddenQuestion = sync(q2, input2, {active: false});
  check(!hiddenQuestion.mounted && q2.querySelectorAll('[data-m03-dictation-control]').length === 0, 'inactive question must not retain an operative mic');
  const firstQuestion = sync(q1, input1);
  check(firstQuestion.enabled && q1.querySelectorAll('[data-m03-dictation-control]').length === 1, 'question 1 should mount independently when active');
  check(q2.querySelectorAll('[data-m03-dictation-control]').length === 0, 'question change must remove the prior question mic');
  q1.querySelector('[data-m03-dictation-control]').click();
  check(starts === 2, 'question 1 click must target its own input after question 2 was removed');
  const inactiveModule = sync(q1, input1, {active: false});
  check(!inactiveModule.mounted && q1.querySelectorAll('[data-m03-dictation-control]').length === 0, 'inactive M03 stage must remove the mic');

  // Reproduce the actual transition defect: the rendered eligibility had
  // already advanced to Q2, while the shared contract still pointed at Q1.
  const contract = {
    modules: {M03: {
      state: {write_enabled: true, finalized: false},
      conversation_state: {state: 'ANSWERABLE', stable_id: 'M03:question:1', write_enabled: true},
      questions: [
        {module_id: 'M03', stable_id: 'M03:question:1', question_id: 1, write_enabled: true, response: {status: 'pending', persisted: false, eligible: true, version: 'm03-v1'}},
        {module_id: 'M03', stable_id: 'M03:question:2', question_id: 2, write_enabled: true, response: {status: 'pending', persisted: false, eligible: false, version: 'm03-v1'}},
      ],
    }},
    question_index: {},
  };
  contract.modules.M03.questions.forEach(question => { contract.question_index[question.stable_id] = question; });
  let activeStage = 'M02';
  let activeTarget = 'M02:family:synthetic';
  check(!sync(q2, input2, {active: activeStage === 'M03'}).mounted, 'M02 stage must not retain an M03 microphone');
  activeStage = 'M03';
  activeTarget = 'M03:question:1';
  const beforeReadback = LocalDictation.isM03DictationContractEligible(
    activeTarget, contract.question_index[activeTarget], contract.modules.M03,
  );
  check(beforeReadback, 'the initially authorized M03 question may start dictation');

  // This is the server response shape returned only after capture readback.
  // The old code updated the card dataset but left the contract projection stale.
  const transitionReadback = {
  readback: true, eligible_question_ids: [1, 2], editable_question_ids: [1, 2], next_question_id: 2,
    version: 'm03-v2', finalized: false, semantic_state: 'KNOWN', readback_value: 'Sí',
  };
  q2.dataset.eligible = '1';
  const staleTargetAllowed = LocalDictation.isM03DictationContractEligible(
    'M03:question:2', contract.question_index['M03:question:2'], contract.modules.M03,
  );
  check(!staleTargetAllowed, 'DOM eligibility alone must not override a stale shared contract');
  const applied = api.applyServerReadback({contract, cards: [q1, q2], questionId: 1, data: transitionReadback});
  check(applied.applied && applied.nextStableId === 'M03:question:2', 'confirmed server readback advances the local contract target');
  activeTarget = 'M03:question:2';
  q1.hidden = true;
  q2.hidden = false;
  const question2Allowed = LocalDictation.isM03DictationContractEligible(
    activeTarget, contract.question_index[activeTarget], contract.modules.M03,
  );
  const question2Sync = sync(q2, input2, {eligibility: {allowed: question2Allowed}, available: true});
  const question2Mic = q2.querySelector('[data-m03-dictation-control]');
  check(question2Allowed && question2Sync.enabled && question2Mic && !question2Mic.disabled,
    'after confirmed Q1 readback, active editable Q2 has a clickable microphone');
  check(question2Mic.dataset.dictationDisabledBy === '', 'authorized resynchronization clears the disabled-writer marker');
  check(contract.question_index['M03:question:1'].response.persisted === true
    && contract.question_index['M03:question:1'].response.value === 'Sí'
    && contract.question_index['M03:question:1'].response.source === 'clinical_record'
    && contract.question_index['M03:question:1'].completion.status === 'complete'
    && contract.question_index['M03:question:2'].response.eligible === true
    && contract.question_index['M03:question:2'].response.version === 'm03-v2',
    'readback updates persisted status, server eligibility and current M03 version');

  contract.modules.M03.state.write_enabled = false;
  const writeDisabled = LocalDictation.isM03DictationContractEligible(
    activeTarget, contract.question_index[activeTarget], contract.modules.M03,
  );
  const deniedSync = sync(q2, input2, {eligibility: {allowed: writeDisabled, code: 'M03_WRITE_DISABLED'}, available: true});
  check(!writeDisabled && !deniedSync.enabled && question2Mic.disabled,
    'a read-only server projection remains disabled after the transition reconciliation');

  document.body.dataset.domTestResult = 'PASS';
  document.body.dataset.domTestCases = 'question1,question2,m02-to-m03-stale-contract,server-readback-reconciliation,question2-enabled-after-readback,server-write-disabled,question-write-disabled-no-diagnostics,persisted-question-without-write-auth,stale-target-silent,permission-pending,permission-denied,connecting,error-retry,disabled-unavailable,disabled-unauthorized,no-duplicates,question-change,inactive-module';
})()
`;

const actualViewRuntimeScript = String.raw`
(async () => {
  const errors = [];
  window.addEventListener('error', event => errors.push(event.message || 'window.error'));
  window.addEventListener('unhandledrejection', event => {
    errors.push(String(event.reason?.message || event.reason || 'unhandledrejection'));
    event.preventDefault();
  });
  const check = (condition, message) => { if (!condition) throw new Error(message); };
  const waitForPermissionSync = () => new Promise(resolve => setTimeout(resolve, 0));
  const main = document.querySelector('#main');
  main.dataset.m02DictationDiagnostics = '1';
  main.dataset.patient = '9301';
  main.dataset.csrf = 'synthetic-csrf';
  main.insertAdjacentHTML('beforeend', '<article data-module="M02"><div class="patient-interview-answer interview-composer"><div class="interview-composer-input"><input type="text" value="synthetic"></div></div></article>');
  const q1 = document.querySelector('#q1');
  const q2 = document.querySelector('#q2');
  const q3 = document.querySelector('#q3');
  const q4 = document.querySelector('#q4');
  const questions = [q1, q2, q3, q4];
  const input1 = q1.querySelector('.interview-text');
  const input2 = q2.querySelector('.interview-text');
  const input3 = q3.querySelector('.interview-text');
  const input4 = q4.querySelector('.interview-text');
  const responseZone = card => card.querySelector('[data-interview-zone="response"]');
  q1.hidden = true;
  responseZone(q1).hidden = true;
  q2.hidden = false;
  responseZone(q2).hidden = false;
  q3.hidden = true;
  responseZone(q3).hidden = true;
  q4.hidden = true;
  responseZone(q4).hidden = true;
  q1.dataset.eligible = q2.dataset.eligible = '1';
  q1.dataset.m03WriteEnabled = q2.dataset.m03WriteEnabled = '1';
  let active = 1;
  let m03ReviewOnly = false;
  let m03PreviousEditOnly = false;
  let m03ReviewReturnIndex = null;
  let m03ReviewTarget = '';
  let m03QuestionViewRevision = 0;
  let conversationStage = 0;
  const conversationStages = [{type: 'm03'}];
  const moduleCards = [];
  const contractQuestion = (id, status = 'pending') => ({
    module_id: 'M03', stable_id: 'M03:question:' + id, question_id: id, theme_id: 1, write_enabled: true,
    voice_text: 'Pregunta sintética ' + id, help: 'Ayuda sintética ' + id,
    response: {status, persisted: status === 'documented', eligible: true, version: 'm03-v1'},
  });
  const question1 = contractQuestion('1');
  const question2 = contractQuestion('2');
  const question3 = contractQuestion('3');
  const question4 = contractQuestion('4');
  q1.dataset.type = 'select';
  const conversationContract = {
    modules: {M03: {
      state: {write_enabled: true, finalized: false},
      conversation_state: {state: 'ANSWERABLE', stable_id: 'M03:question:2', write_enabled: true},
      questions: [question1, question2, question3, question4],
    }},
    question_index: {'M03:question:1': question1, 'M03:question:2': question2, 'M03:question:3': question3, 'M03:question:4': question4},
  };
  let currentTarget = 'M03:question:2';
  const conversationCoordinator = {
    snapshot: () => ({currentTarget, paused: false, activeOperation: null}),
    questionContract: target => conversationContract.question_index[target] || null,
    setTarget: target => { currentTarget = target; return target; },
    invalidate: () => { invalidations += 1; },
    actionContract: () => null,
    voiceEvent: (type, target) => ({type, stable_id: target, text: type === 'HELP'
      ? conversationContract.question_index[target]?.help
      : conversationContract.question_index[target]?.voice_text || 'Pregunta sintética'}),
  };
  let invalidations = 0;
  let statusText = '';
  let fetchCount = 0;
  window.fetch = (...args) => { fetchCount += 1; return Promise.reject(new Error('unexpected fetch')); };
  const setStatus = message => { statusText = String(message || ''); };
  const setCurrentModule = () => {};
  const updateProgress = () => {};
  const hasConfirmedAnswer = card => Boolean(card?.dataset.savedValue?.trim())
    || ['UNKNOWN', 'NOT_REMEMBERED'].includes(String(card?.dataset.semanticState || '').toUpperCase());
  let narrationActive = false;
  let overlappingNarrations = 0;
  let narrationCancellations = 0;
  let syncAvatarRepeatControls = () => {};
  let syncAvatarStopControls = () => {};
  let syncAvatarHelpControls = () => {};
  const stopAssistantVoice = () => {
    if (narrationActive) narrationCancellations += 1;
    narrationActive = false;
    if (typeof syncAvatarStopControls === 'function') syncAvatarStopControls({speaking: false, audioPending: false, paused: false, audioBlocked: false});
  };
  const stopRecognition = () => Promise.resolve();
  const speechSupported = () => true;
  const setVoiceButtonState = () => {};
  const moduleBlockedVoiceEventFor = () => null;
  const writeVoiceMutePreference = () => {};
  const voiceEvents = [];
  const voiceController = {
    enabled: true, muted: false, lastEvent: {type: 'QUESTION'},
    supported: () => true,
    snapshot: () => ({speaking: narrationActive, audioBlocked: false, paused: false, audioPending: false}),
    speakEvent: event => {
      if (narrationActive) overlappingNarrations += 1;
      narrationActive = true;
      voiceEvents.push({...event});
      if (typeof syncAvatarStopControls === 'function') syncAvatarStopControls({speaking: true, audioPending: false, paused: false, audioBlocked: false});
      return true;
    },
    activate: event => {
      if (narrationActive) overlappingNarrations += 1;
      narrationActive = true;
      voiceEvents.push({...event});
      if (typeof syncAvatarStopControls === 'function') syncAvatarStopControls({speaking: true, audioPending: false, paused: false, audioBlocked: false});
      return true;
    },
    cancel: () => {
      if (narrationActive) narrationCancellations += 1;
      narrationActive = false;
      if (typeof syncAvatarStopControls === 'function') syncAvatarStopControls({speaking: false, audioPending: false, paused: false, audioBlocked: false});
      return true;
    },
  };
  let clearContextualHelp = () => {};
  const localDictationEnabled = true;
  const localDictation = {supported: () => true, start: () => Promise.resolve(true)};
  const ensureLocalDictation = () => localDictation;
  const PatientInterviewLocalDictation = window.PatientInterviewLocalDictation;
  const recognitionTargets = [];
  const startRecognition = (button, input) => { recognitionTargets.push(input.closest('[data-module]')?.dataset.module || 'unknown'); };
  const status = document.createElement('div');
  status.id = 'status';
  document.body.appendChild(status);
  let refreshM03DictationUi = null;
  let m03MicPermissionDenied = false;
  let m03MicPermissionStatus = null;
  let m03DictationRuntime = {target: currentTarget, state: 'IDLE', message: ''};
  const permissionListeners = new Set();
  const permission = {
    state: 'granted',
    addEventListener: (type, listener) => { if (type === 'change') permissionListeners.add(listener); },
    removeEventListener: (type, listener) => { if (type === 'change') permissionListeners.delete(listener); },
    change(state) { this.state = state; [...permissionListeners].forEach(listener => listener()); },
  };
  Object.defineProperty(navigator, 'permissions', {
    configurable: true,
    value: {query: () => Promise.resolve(permission)},
  });
  ${actualViewFunctions}

  const doctorRepeats = Array.from(document.querySelectorAll('[data-doctor-repeat]'));
  check(doctorRepeats.length === 2, 'the assistant panel and floating shortcut each render one repeat control');
  const doctorStop = document.querySelector('[data-doctor-stop]');
  check(doctorStop !== null, 'the assistant panel renders one stop audio control');
  const doctorHelps = Array.from(document.querySelectorAll('[data-doctor-help]'));
  check(doctorHelps.length === 2, 'the assistant panel and floating shortcut each render one help control');
  const assertAvatarRepeat = expectedTarget => {
    const answerState = () => JSON.stringify({
      currentTarget, active,
      questions: questions.map(card => ({
        hidden: card.hidden, savedValue: card.dataset.savedValue, version: card.dataset.m03Version,
        value: card.querySelector('.interview-text')?.value,
      })),
    });
    const before = answerState();
    const beforeFetch = fetchCount;
    const beforeEvents = voiceEvents.length;
    check(doctorRepeats.every(button => !button.disabled && button.getAttribute('aria-disabled') === 'false'),
      'both actual avatar controls must be available for ' + expectedTarget);
    doctorRepeats.forEach(button => button.click());
    const repeated = voiceEvents.slice(beforeEvents);
    check(repeated.length === 2 && repeated.every(event => event.type === 'QUESTION' && event.stable_id === expectedTarget),
      'each actual avatar control must repeat exactly the visible question ' + expectedTarget);
    check(answerState() === before && fetchCount === beforeFetch,
      'avatar repetition must not advance, alter answers or request a save');
  };
  bindDoctorRepeatControls();
  bindDoctorStopControls();
  bindDoctorHelpControls();
  await syncM03MicPermissionState();
  await waitForPermissionSync();
  syncM03ContextualActions();
  bindVoiceControls();
  const mic2 = q2.querySelector('[data-m03-dictation-control]');
  check(mic2 && !mic2.disabled, 'the actual view synchronizer must mount an enabled Q2 microphone under its valid contract');
  check(q2.querySelectorAll('[data-m03-dictation-control]').length === 1, 'actual view must mount exactly one Q2 microphone');
  mic2.click();
  check(recognitionTargets.join(',') === 'M03', 'clicking the actual view M03 mic must reach its recognition handler once');
  const m02Input = main.querySelector('[data-module="M02"] input[type="text"]');
  const m02Mic = m02Input.parentElement.querySelector('.chat-mic-button');
  check(m02Mic && !m02Mic.disabled, 'the existing M02 binding must continue to mount its original microphone');
  m02Mic.click();
  check(recognitionTargets.join(',') === 'M03,M02', 'M02 microphone click must retain its existing dispatch path');
  const repeat2 = q2.querySelector('.chat-voice-button');
  const help2 = q2.querySelector('.chat-help-button');
  check(!repeat2, 'actual bindVoiceControls must not render the old repeat button in the question card');
  check(!help2, 'actual bindVoiceControls must not render the old help button in the question card');
  check(doctorRepeats.every(button => !button.disabled), 'both doctor repeat controls enable for the active editable M03 question and supported voice');
  check(doctorHelps.every(button => !button.disabled), 'both doctor help controls enable for the active editable M03 question');
  check(doctorStop.disabled && doctorStop.getAttribute('aria-disabled') === 'true', 'stop audio button is disabled when idle');

  // Verify avatar help functionality
  doctorHelps[0].click();
  check(voiceEvents.some(event => event.type === 'HELP' && event.stable_id === 'M03:question:2'), 'panel help triggers HELP event for active M03:question:2');
  const helpPanel2 = q2.querySelector('[data-question-help]');
  check(helpPanel2 && !helpPanel2.hidden && helpPanel2.textContent.includes('Ayuda sintética 2'), 'panel help mounts contextual help block in active card');
  doctorHelps[1].click();
  check(voiceEvents.filter(event => event.type === 'HELP' && event.stable_id === 'M03:question:2').length >= 2, 'floating help triggers HELP event for active M03:question:2');

  // Verify stop audio initiated from panel repeat
  doctorRepeats[0].click();
  check(narrationActive, 'panel repeat starts question narration');
  check(!doctorStop.disabled && doctorStop.getAttribute('aria-disabled') === 'false', 'stop audio button enables when narration is active');
  doctorStop.click();
  check(!narrationActive, 'clicking stop audio cancels narration from panel repeat');
  check(doctorStop.disabled && doctorStop.getAttribute('aria-disabled') === 'true', 'stop audio disables after being stopped');

  // Verify double click on stop audio is safe
  doctorStop.click();
  doctorStop.click();

  // Verify stop audio initiated from floating shortcut repeat
  doctorRepeats[1].click();
  check(narrationActive, 'floating shortcut repeat starts question narration');
  check(!doctorStop.disabled, 'stop audio button enables when floating repeat starts narration');
  doctorStop.click();
  check(!narrationActive, 'stop audio cancels narration initiated from floating shortcut');
  check(doctorStop.disabled, 'stop audio disables after floating repeat stopped');

  const beforeAvatarM03 = voiceEvents.length;
  doctorRepeats[0].click();
  doctorRepeats[1].click();
  doctorRepeats[1].click();
  check(voiceEvents.slice(beforeAvatarM03).length === 3
    && voiceEvents.slice(beforeAvatarM03).every(event => event.type === 'QUESTION' && event.stable_id === 'M03:question:2')
    && overlappingNarrations === 0 && narrationCancellations >= 4,
    'both avatar controls route through the current M03 event and cancel the preceding request instead of overlapping');
  const initialQ2Action = q2.querySelector('[data-m03-contextual-action]');
  check(initialQ2Action && !initialQ2Action.hidden && !initialQ2Action.disabled,
    'the active answerable question exposes its single server-provided contextual action');
  doctorRepeats[0].click();
  doctorHelps[0].click();
  check(voiceEvents.some(event => event.type === 'QUESTION' && event.stable_id === 'M03:question:2')
    && voiceEvents.some(event => event.type === 'HELP' && event.stable_id === 'M03:question:2'),
    'avatar repeat and avatar help handlers must address the currently active stable ID');

  const m02StableId = 'M02:family:synthetic';
  conversationContract.question_index[m02StableId] = {
    module_id: 'M02', stable_id: m02StableId, voice_text: 'Pregunta familiar sintética',
  };
  const m02Card = document.createElement('article');
  m02Card.className = 'interview-module-card';
  m02Card.dataset.module = 'M02';
  m02Card.dataset.stableId = m02StableId;
  m02Card.innerHTML = '<div class="patient-interview-message assistant"><div class="interview-message-header"></div><p>Pregunta familiar sintética</p></div>';
  main.appendChild(m02Card);
  q2.hidden = true;
  responseZone(q2).hidden = true;
  currentTarget = m02StableId;
  bindDoctorRepeatControls();
  const beforeAvatarM02 = voiceEvents.length;
  doctorRepeats[0].click();
  doctorRepeats[1].click();
  check(voiceEvents.slice(beforeAvatarM02).length === 2
    && voiceEvents.slice(beforeAvatarM02).every(event => event.type === 'QUESTION'
      && event.stable_id === m02StableId && event.module_id === 'M02'),
    'the same two avatar controls route a visible M02 card through its canonical module event');
  assertAvatarRepeat(m02StableId);
  m02Card.hidden = true;
  q2.hidden = false;
  responseZone(q2).hidden = false;
  currentTarget = 'M03:question:2';
  conversationContract.modules.M03.conversation_state.stable_id = currentTarget;
  bindDoctorRepeatControls();

  bindVoiceControls();
  check(q2.querySelectorAll('[data-m03-dictation-control]').length === 1
    && q2.querySelectorAll('.chat-voice-button').length === 0
    && q2.querySelectorAll('.chat-help-button').length === 0,
  'repeated actual binding must not duplicate mic controls and must not generate old repeat or help buttons');
  mic2.click();
  check(recognitionTargets.join(',') === 'M03,M02,M03', 'repeated actual binding must not duplicate the microphone click handler');

  q2.hidden = true;
  responseZone(q2).hidden = true;
  q1.hidden = false;
  responseZone(q1).hidden = false;
  active = 0;
  currentTarget = 'M03:question:1';
  conversationContract.modules.M03.conversation_state.stable_id = currentTarget;
  m03DictationRuntime = {target: currentTarget, state: 'IDLE', message: ''};
  bindVoiceControls();
  check(!q2.querySelector('[data-m03-dictation-control]'), 'question transition must remove the now-inactive Q2 microphone');
  assertAvatarRepeat('M03:question:1');
  q1.hidden = true;
  bindVoiceControls();
  const beforeHiddenRepeat = voiceEvents.length;
  doctorRepeats.forEach(button => button.click());
  check(doctorRepeats.every(button => button.disabled && button.getAttribute('aria-disabled') === 'true')
    && voiceEvents.length === beforeHiddenRepeat, 'avatar controls disable when no question is visible');
  q1.hidden = false;
  bindVoiceControls();
  assertAvatarRepeat('M03:question:1');
  const mic1 = q1.querySelector('[data-m03-dictation-control]');
  check(mic1 && !mic1.disabled, 'question transition must mount an enabled microphone for the active Q1 contract');
  mic1.click();
  check(recognitionTargets.join(',') === 'M03,M02,M03,M03', 'the Q1 microphone must dispatch once after the question change');
  check(!q1.querySelector('.chat-voice-button') && !q1.querySelector('.chat-help-button'), 'question card 1 does not have old repeat or help buttons');
  doctorRepeats[0].click();
  doctorHelps[0].click();
  check(voiceEvents.some(event => event.type === 'QUESTION' && event.stable_id === currentTarget)
    && voiceEvents.some(event => event.type === 'HELP' && event.stable_id === currentTarget),
    'repeat and help via avatar controls rebind to the newly active stable ID');

  // Reproduce the reported in-session transition: Q2 has a server-defined
  // action in the HTML, but stays hidden until Q1 readback authorizes Q2.
  question1.response.status = 'pending';
  question1.response.persisted = false;
  question1.response.eligible = true;
  question1.response.value = null;
  question1.completion = {required: true, status: 'pending'};
  question2.response.status = 'pending';
  question2.response.persisted = false;
  question2.response.eligible = false;
  q1.dataset.savedValue = '';
  q1.dataset.semanticState = '';
  q1.dataset.eligible = '1';
  q2.dataset.eligible = '0';
  conversationContract.modules.M03.conversation_state = {
    state: 'ANSWERABLE', stable_id: 'M03:question:1', write_enabled: true,
  };
  q1.hidden = true;
  responseZone(q1).hidden = true;
  q2.hidden = true;
  responseZone(q2).hidden = true;
  showQuestion(0);
  await waitForPermissionSync();
  const q1Action = q1.querySelector('[data-m03-contextual-action]');
  const q2Action = q2.querySelector('[data-m03-contextual-action]');
  check(q1Action && !q1Action.hidden && !q1Action.disabled,
    'the active eligible Q1 action remains available under the existing module write authorization');
  check(q2Action && q2Action.hidden && q2Action.disabled,
    'the dependent Q2 action exists in the rendered DOM but remains unavailable before server eligibility');
  const q1Readback = {
    readback: true, readback_value: 'Sí, fumo ocasionalmente', response_id: 901,
    semantic_state: 'KNOWN', eligible_question_ids: [1, 2], editable_question_ids: [1], next_question_id: 2,
    version: 'm03-v2', finalized: false,
  };
  check(applyM03ServerEligibility(q1Readback, 1), 'a valid server readback must reconcile M03 state without page reload');
  check(question1.response.value === q1Readback.readback_value
    && question1.response.persisted === true && question1.completion.status === 'complete',
    'the shared contract must expose the exact confirmed value and completion state after readback');
  check(q2Action.hidden,
    'eligible-question metadata alone must not reveal a contextual action for a non-active target');
  const staleViewRevision = m03QuestionViewRevision;
  showNextM03Question(q1, q1Readback.next_question_id, false);
  await waitForPermissionSync();
  bindVoiceControls();
  check(active === 1 && !q2.hidden && !responseZone(q2).hidden
    && currentTarget === 'M03:question:2',
    'confirmed Q1 advances directly to the server-designated eligible Q2 without F5');
  check(!q2Action.hidden && !q2Action.disabled
    && q2Action.dataset.actionKey === 'M03_TOBACCO_AGE_NOT_REMEMBERED'
    && q2Action.textContent.trim() === 'No recuerdo, guardar y continuar',
    'the previously hidden action appears exactly once with the official server-provided Q2 action and label');
  check(input2.value === '' && q2.querySelectorAll('[data-m03-contextual-action]').length === 1
    && q2.querySelectorAll('.interview-submit').length === 1,
    'the newly active question retains its own response controls without duplicate action or submit buttons');
  check(!q2.querySelector('.chat-voice-button') && !q2.querySelector('.chat-help-button')
    && voiceEvents.some(event => event.type === 'QUESTION' && event.stable_id === 'M03:question:2'),
    'old repeat and help buttons are absent and active narration target stays aligned with the newly visible question');
  check(!isCurrentM03View(q1, staleViewRevision),
    'a late response from a prior active view cannot navigate over the current question');
  const projectionSnapshot = card => ({
    questionId: card.dataset.questionId,
    questionText: card.querySelector('.interview-question-text')?.textContent.trim(),
    visible: !card.hidden,
    inputValue: card.querySelector('.interview-text')?.value,
    inputDisabled: card.querySelector('.interview-text')?.disabled,
    sendDisabled: card.querySelector('.interview-submit')?.disabled,
    actionKey: card.querySelector('[data-m03-contextual-action]')?.dataset.actionKey,
    actionLabel: card.querySelector('[data-m03-contextual-action]')?.textContent.trim(),
    actionVisible: !card.querySelector('[data-m03-contextual-action]')?.hidden,
    actionDisabled: card.querySelector('[data-m03-contextual-action]')?.disabled,
    microphoneVisible: Boolean(card.querySelector('[data-m03-dictation-control]')),
    microphoneDisabled: card.querySelector('[data-m03-dictation-control]')?.disabled,
    hasOldRepeat: Boolean(card.querySelector('.chat-voice-button')),
    hasOldHelp: Boolean(card.querySelector('.chat-help-button')),
    avatarRepeatDisabled: doctorRepeats.map(button => button.disabled),
    avatarHelpDisabled: doctorHelps.map(button => button.disabled),
    version: card.dataset.m03Version,
    activeTarget: currentTarget,
  });
  const dynamicQuestion2Snapshot = projectionSnapshot(q2);
  assertAvatarRepeat('M03:question:2');
  showQuestion(1); // Reinitialize from the same confirmed server projection; no reload or request.
  await waitForPermissionSync();
  bindVoiceControls();
  const readbackInitializedQuestion2Snapshot = projectionSnapshot(q2);
  check(JSON.stringify(dynamicQuestion2Snapshot) === JSON.stringify(readbackInitializedQuestion2Snapshot),
    'normal navigation and initialization from the equivalent confirmed readback produce the same active-question DOM contract');
  assertAvatarRepeat('M03:question:2');

  const micAfterTransition = q2.querySelector('[data-m03-dictation-control]');
  permission.change('denied');
  await waitForPermissionSync();
  check(m03MicPermissionDenied && micAfterTransition.disabled,
    'the actual permission change handler must disable dictation after Chrome denial (denied=' + m03MicPermissionDenied + ', disabled=' + micAfterTransition.disabled + ', listeners=' + permissionListeners.size + ', diagnostic=' + q2.dataset.m03DictationDiagnostics + ')');
  check(q2.querySelector('[data-m03-dictation-status]')?.textContent.includes('Chrome bloqueó'),
    'denied permission must expose the existing accessible explanation');
  permission.change('granted');
  await waitForPermissionSync();
  check(!m03MicPermissionDenied && !micAfterTransition.disabled, 'the actual permission change handler must restore an eligible microphone');
  check(permissionListeners.size === 1, 'permission resynchronization must not register duplicate change listeners');

  delete conversationContract.question_index[currentTarget];
  syncM03DictationPermission();
  check(micAfterTransition.disabled && q2.dataset.m03DictationDiagnostics.includes('"question_contract_available":false'),
    'a missing canonical question contract must stay visibly disabled and fail closed without throwing');

  conversationContract.question_index['M03:question:2'] = question2;
  question1.response.status = 'documented';
  question1.response.persisted = true;
  question1.response.eligible = true;
  question2.response.status = 'pending';
  question2.response.persisted = false;
  question2.response.eligible = true;
  conversationContract.modules.M03.conversation_state = {
    state: 'ANSWERABLE', stable_id: 'M03:question:2', write_enabled: true,
  };
  q1.dataset.savedValue = 'Sí, fumo dos cigarros al día';
  q1.dataset.eligible = '1';
  q2.dataset.eligible = '1';
  input1.value = 'valor local que debe reemplazarse por el readback';
  const confirmation1 = q1.querySelector('[data-confirmed-message]');
  confirmation1.textContent = 'Respuesta confirmada: ' + q1.dataset.savedValue;
  confirmation1.hidden = false;
  q1.hidden = true;
  responseZone(q1).hidden = true;
  q2.hidden = false;
  responseZone(q2).hidden = false;
  active = 1;
  currentTarget = 'M03:question:2';
  m03ReviewOnly = false;
  m03ReviewReturnIndex = null;
  m03ReviewTarget = '';
  m03DictationRuntime = {target: currentTarget, state: 'IDLE', message: ''};
  updateM03PreviousButton();
  const q2Previous = q2.querySelector('[data-m03-previous]');
  check(!q2Previous.hidden && !q2Previous.disabled, 'Q2 exposes its existing previous-question control after confirmed Q1');
  const fetchesBeforeReview = fetchCount;
  const currentTargetBeforeReview = currentTarget;
  check(navigateM03Previous(q2Previous), 'previous navigation should enter the prior confirmed question');
  await waitForPermissionSync();
  bindVoiceControls();
  check(active === 0 && !q1.hidden && q2.hidden, 'previous navigation renders the confirmed Q1 card');
  check(currentTarget === currentTargetBeforeReview
    && conversationContract.modules.M03.conversation_state.stable_id === 'M03:question:2',
    'editing Q1 must not replace the server-authorized active Q2 target');
  assertAvatarRepeat('M03:question:1');
  check(!responseZone(q1).hidden && !q1.querySelector('.m03-composer').hidden
    && !input1.disabled && input1.value === q1.dataset.savedValue && confirmation1.hidden,
    'authorized previous-edit mode reuses the original editable form and preloads the persisted answer');
  const editMic1 = q1.querySelector('[data-m03-dictation-control]');
  check(editMic1 && !editMic1.disabled,
    'the saved immediate tobacco predecessor mounts an enabled Vosk control under the active edit authorization');
  const q1Return = q1.querySelector('[data-m03-previous]');
  const q1Next = q1.querySelector('[data-m03-next]');
  check(q1Return.hidden && q1Next && !q1Next.hidden,
    'history exposes sequential forward navigation instead of repurposing the previous control as a return shortcut');
  check(!q1.querySelector('.chat-voice-button') && !q1.querySelector('.chat-help-button'), 'historical question card does not have old repeat or help buttons');
  doctorRepeats[0].click();
  doctorHelps[0].click();
  check(voiceEvents.some(event => event.type === 'QUESTION' && event.stable_id === 'M03:question:1')
    && voiceEvents.some(event => event.type === 'HELP' && event.stable_id === 'M03:question:1'),
    'avatar repeat and avatar help continue to address the visible prior question');
  const patientTextDuringReview = document.body.innerText;
  check(!patientTextDuringReview.includes('M03_ACTIVE_TARGET_STALE')
    && !patientTextDuringReview.includes('Diagnóstico local')
    && !patientTextDuringReview.includes('stable_id_matches'),
    'technical diagnostics never appear in the patient-facing DOM text');
  input1.value = 'respuesta editada sin confirmar';
  check(navigateM03Next(q1Next), 'the next-question control returns from Q1 history to the active pending Q2');
  await waitForPermissionSync();
  bindVoiceControls();
  const returnedMic = q2.querySelector('[data-m03-dictation-control]');
  check(active === 1 && !q2.hidden && !responseZone(q2).hidden && !input2.disabled,
    'returning to Q2 restores its editable response controls');
  check(currentTarget === 'M03:question:2' && !m03ReviewOnly && returnedMic && !returnedMic.disabled,
    'returning to the server-authorized pending target restores the enabled microphone');
  assertAvatarRepeat('M03:question:2');
  check(q1.querySelectorAll('[data-m03-dictation-control]').length === 0
    && q2.querySelectorAll('[data-m03-dictation-control]').length === 1,
    'question navigation removes the prior mic and preserves exactly one active mic');
  returnedMic.click();
  returnedMic.click();
  returnedMic.click();
  check(fetchCount === fetchesBeforeReview && currentTarget === 'M03:question:2'
    && invalidations === 2 && statusText === '',
    'edit navigation, return, and repeated local mic clicks perform no clinical save and preserve the pending target');
  check(input1.value === q1.dataset.savedValue,
    'returning to the pending question discards unsaved previous-answer edits and restores the stored value');
  check(q2Action && !q2Action.hidden && !q2Action.disabled,
    'returning from previous-answer editing reconstructs the current question action without stale controls');
  let contextualRequest = null;
  window.fetch = (url, options = {}) => {
    fetchCount += 1;
    contextualRequest = {url: String(url), body: JSON.parse(String(options.body || '{}'))};
    return Promise.resolve({ok: true, json: () => Promise.resolve({
      resultado: true, readback: true, readback_value: '', response_id: 902,
      semantic_state: 'NOT_REMEMBERED', eligible_question_ids: [1, 2, 3], editable_question_ids: [1, 2],
      next_question_id: 3, version: 'm03-v3', finalized: false,
    })});
  };
  q2Action.click();
  await new Promise(resolve => setTimeout(resolve, 0));
  await waitForPermissionSync();
  check(contextualRequest?.body?.idPregunta === 2
    && contextualRequest.body.contextualActionKey === 'M03_TOBACCO_AGE_NOT_REMEMBERED',
    'the actual contextual click handler posts the active question id and server-owned action key');
  check(question2.response.status === 'documented'
    && question2.response.semantic_state === 'NOT_REMEMBERED'
    && question2.response.persisted === true
    && question2.response.value === null
    && question2.completion.status === 'complete',
    'NOT_REMEMBERED readback is documented semantically without inventing a clinical age');
  check(active === 2 && currentTarget === 'M03:question:3'
    && !q3.hidden && !responseZone(q3).hidden && input3.value === '',
    'successful contextual readback advances to the server-selected next eligible M03 question');
  const q3Action = q3.querySelector('[data-m03-contextual-action]');
  const q3Mic = q3.querySelector('[data-m03-dictation-control]');
  check(q3Action && !q3Action.hidden && !q3Action.disabled
    && q3Action.dataset.actionKey === 'M03_Q3_NOT_REMEMBERED'
    && q3Action.textContent.trim() === 'No recuerdo'
    && q3.querySelectorAll('[data-m03-contextual-action]').length === 1,
    'the third consecutive question receives only its own catalog-authorized contextual action');
  check(q3Mic && !q3Mic.disabled && q3.querySelectorAll('[data-m03-dictation-control]').length === 1
    && q2.querySelectorAll('[data-m03-dictation-control]').length === 0,
    'microphone ownership follows the active third question without retaining the previous mic');
  check(!q3.querySelector('.chat-voice-button') && !q3.querySelector('.chat-help-button'), 'question card 3 does not have old repeat or help buttons');
  doctorRepeats[1].click();
  doctorHelps[1].click();
  check(voiceEvents.some(event => event.type === 'QUESTION' && event.stable_id === 'M03:question:3')
    && voiceEvents.some(event => event.type === 'HELP' && event.stable_id === 'M03:question:3'),
    'Piper question (via avatar) and avatar help resolve against the visible third question');
  const technicalText = document.body.innerText;
  check(!technicalText.includes('M03_ACTIVE_TARGET_STALE')
    && !technicalText.includes('QUESTION_WRITE_DISABLED')
    && !technicalText.includes('stable_id_matches'),
    'normal transitions do not expose internal authorization diagnostics');
  let resolveLateCapture;
  window.fetch = () => new Promise(resolve => { resolveLateCapture = resolve; });
  const q3Previous = q3.querySelector('[data-m03-previous]');
  check(q3Previous && !q3Previous.hidden, 'the active third question exposes the existing previous-question navigation');
  q3Action.click();
  check(typeof resolveLateCapture === 'function', 'the actual contextual handler starts one bounded async capture request');
  check(navigateM03Previous(q3Previous), 'a simulated navigation change can move the view while the response is in flight');
  check(active === 1 && !q2.hidden && m03PreviousEditOnly && !m03ReviewOnly,
    'the user-selected authorized historical question becomes directly editable before the late response returns');
  resolveLateCapture({ok: true, json: () => Promise.resolve({
    resultado: true, readback: true, readback_value: '', response_id: 903,
    semantic_state: 'NOT_REMEMBERED', eligible_question_ids: [1, 2, 3], editable_question_ids: [1, 2, 3],
    next_question_id: null, version: 'm03-v4', finalized: false,
  })});
  await new Promise(resolve => setTimeout(resolve, 0));
  check(active === 1 && !q2.hidden && q3.hidden
    && question3.response.persisted === true
    && !statusText.includes('Respuesta guardada y verificada'),
    'late server readback reconciles its own response but cannot overwrite the newer visible question or status');

  // Full browser-DOM traversal: three saved eligible answers and the first
  // pending question. Navigation changes only the visible view; no request is sent.
  const historicalValues = ['Sí, fumo cinco cigarros al día', '42', '10 años'];
  [q1, q2, q3].forEach((card, index) => {
    const question = conversationContract.question_index['M03:question:' + (index + 1)];
    question.response.status = 'documented';
    question.response.persisted = true;
    question.response.eligible = true;
    question.response.editable = true;
    question.response.value = historicalValues[index];
    card.dataset.savedValue = historicalValues[index];
    card.dataset.semanticState = 'KNOWN';
    card.dataset.eligible = '1';
    card.dataset.historicalEditable = '1';
    card.dataset.responseId = String(910 + index);
    card.querySelector('.interview-text').value = 'borrador local a descartar';
  });
  question4.response.status = 'pending';
  question4.response.persisted = false;
  question4.response.eligible = true;
  question4.response.editable = false;
  question4.response.value = null;
  q4.dataset.savedValue = '';
  q4.dataset.semanticState = '';
  q4.dataset.eligible = '1';
  q4.dataset.historicalEditable = '0';
  q4.dataset.responseId = '';
  q4.dataset.m03WriteEnabled = '1';
  q4.dataset.m03Version = 'm03-v5';
  conversationContract.modules.M03.conversation_state = {
    state: 'ANSWERABLE', stable_id: 'M03:question:4', write_enabled: true,
  };
  [q1, q2, q3, q4].forEach((card, index) => {
    card.hidden = index !== 3;
    responseZone(card).hidden = index !== 3;
  });
  active = 3;
  currentTarget = 'M03:question:4';
  m03ReviewOnly = false;
  m03PreviousEditOnly = false;
  m03ReviewReturnIndex = null;
  m03ReviewTarget = '';
  showQuestion(3);
  await waitForPermissionSync();
  bindVoiceControls();
  const noWriteBeforeTraversal = fetchCount;
  const q4Previous = q4.querySelector('[data-m03-previous]');
  check(q4Previous && !q4Previous.hidden && navigateM03Previous(q4Previous),
    'returning from the first pending Q4 enters the last persisted eligible history item Q3');
  await waitForPermissionSync(); bindVoiceControls();
  check(active === 2 && !q3.hidden && input3.value === '10 años'
    && !input3.disabled && q3.querySelector('[data-m03-dictation-control]')?.disabled === false,
    'Q3 history restores its persisted value, editable input and authorized dictation');
  check(navigateM03Previous(q3.querySelector('[data-m03-previous]')),
    'Q3 history navigates to the preceding persisted Q2');
  await waitForPermissionSync(); bindVoiceControls();
  check(active === 1 && !q2.hidden && input2.value === '42', 'Q2 historical answer is restored while stepping backward');
  check(navigateM03Previous(q2.querySelector('[data-m03-previous]')),
    'Q2 history navigates to the preceding persisted Q1');
  await waitForPermissionSync(); bindVoiceControls();
  check(active === 0 && !q1.hidden && input1.value === historicalValues[0], 'Q1 historical answer is restored while stepping backward');
  check(navigateM03Next(q1.querySelector('[data-m03-next]')), 'Q1 advances to persisted history Q2');
  await waitForPermissionSync(); bindVoiceControls();
  check(active === 1 && input2.value === '42', 'Q2 is restored while stepping forward');
  check(navigateM03Next(q2.querySelector('[data-m03-next]')), 'Q2 advances to persisted history Q3');
  await waitForPermissionSync(); bindVoiceControls();
  check(active === 2 && input3.value === '10 años', 'Q3 is restored while stepping forward');
  check(navigateM03Next(q3.querySelector('[data-m03-next]')), 'Q3 returns to first pending Q4 without a save');
  await waitForPermissionSync(); bindVoiceControls();
  check(active === 3 && currentTarget === 'M03:question:4' && !q4.hidden
    && !input4.disabled && q4.querySelector('.interview-submit')?.disabled === false,
    'forward traversal returns to the authorized pending question and restores its answer controls');
  check(fetchCount === noWriteBeforeTraversal,
    'backward and forward history traversal causes no insert, update or HTTP save request');
  check(!q3.querySelector('.chat-voice-button')
    && !q3.querySelector('.chat-help-button')
    && voiceEvents.some(event => event.type === 'QUESTION' && event.stable_id === 'M03:question:3'),
    'history narration and avatar repeat/help remain synchronized to the visible historical question without old repeat or help buttons');
  check(errors.length === 0, 'actual view functions raised browser errors: ' + errors.join(' | '));
  document.body.dataset.actualViewRuntimeResult = 'PASS';
  document.body.dataset.actualViewRuntimeCases = 'actual-bind,actual-sync,permission-grant-deny,question-change,mic-click,avatar-repeat-m02-m03,avatar-stop-audio,old-repeat-removed,double-click-cancellation,repeat-help-targets,previous-answer-input-preload,authorized-inline-edit,microphone-in-edit,bidirectional-q1-q3-history,q4-return-to-pending,no-write-navigation,active-target-preserved,contextual-action-revealed-after-readback,no-f5-transition,readback-reload-equivalence,not-remembered-readback,three-consecutive-questions,piper-question-help-alignment,late-response-does-not-overwrite-current-view,no-diagnostics,no-duplicates,missing-contract-fail-closed,m02-regression,no-window-errors,no-unhandled-rejections';
})().catch(error => {
  document.body.dataset.actualViewRuntimeResult = 'FAIL';
  document.body.dataset.actualViewRuntimeError = String(error?.stack || error).replace(/[<>"&]/g, ' ');
});
`;
assert.doesNotThrow(() => new Function(actualViewRuntimeScript),
    'the actual extracted interview view functions must form valid executable JavaScript.');

const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'm03-mic-dom-chrome-'));
const fixture = path.join(profile, 'fixture.html');
const chromeProfile = path.join(profile, 'chrome-profile');
const questionMarkup = (id, hidden) => `<article id="q${id}" class="patient-interview-question patient-interview-active-question interview-module-card" data-module="M03" data-question-index="${id - 1}" data-question-id="${id}" data-theme-id="1" data-theme-name="Tabaquismo" data-eligible="${id === 1 ? '1' : '0'}" data-type="input" data-m03-version="m03-v1" data-m03-write-enabled="1"${hidden ? ' hidden' : ''}>
<button type="button" class="interview-previous-button" data-m03-previous hidden>← Pregunta anterior</button><button type="button" class="interview-previous-button" data-m03-next hidden>→ Pregunta siguiente</button>
<div class="patient-interview-message assistant clinical-question-header"><div class="interview-message-header"><strong class="clinical-question-module">M03 · Tabaquismo</strong></div><h3 class="interview-question-text clinical-question-title">${id === 1 ? '¿Usted fuma cigarros de nicotina?' : (id === 2 ? '¿Desde qué edad empezó a fumar?' : '¿Durante cuánto tiempo ha fumado?')}</h3></div>
<div class="interview-composer-shell" data-interview-zone="response"${hidden ? ' hidden' : ''}><div class="patient-interview-answer interview-composer m03-composer"><div class="interview-composer-input m03-input-wrap"><label class="visually-hidden" for="answer-${id}">Respuesta</label><input id="answer-${id}" class="form-control interview-text" type="text" maxlength="2000"></div><button type="button" class="btn btn-primary chat-send-button interview-submit"><span class="chat-send-label">Enviar</span></button><div class="m03-quick-actions" aria-label="Respuestas rápidas"${id === 1 ? '' : ' hidden'}><button type="button" class="btn btn-primary m02-safe-no-button" data-m03-contextual-action data-action-key="${id === 1 ? 'M03_TOBACCO_NEGATIVE' : (id === 2 ? 'M03_TOBACCO_AGE_NOT_REMEMBERED' : 'M03_Q3_NOT_REMEMBERED')}" data-confirmation-label="${id === 1 ? 'No' : 'Dato no recordado'}"${id === 1 ? '' : ' hidden disabled'}>${id === 1 ? 'No, guardar y continuar' : (id === 2 ? 'No recuerdo, guardar y continuar' : 'No recuerdo')}</button></div></div><div class="interview-composer-footer"><p class="interview-composer-help">Escribe o dicta tu respuesta.</p><span class="interview-answer-count" data-answer-count>0/2000</span></div></div>
<div class="patient-interview-message patient" data-confirmed-message hidden></div><small id="m03-dictation-status-${id}" data-m03-dictation-status role="status" aria-live="polite" hidden></small></article>`;
const html = `<!doctype html><html><head><meta charset="utf-8"><title>M03 dictation DOM fixture</title></head><body>
 <main id="main" data-stage="M02"><section id="interview-chat" aria-label="Entrevista Conversacional"><header class="interview-header"><h1>Entrevista Conversacional</h1><div class="interview-progress" role="progressbar"></div></header><div class="interview-thread">${questionMarkup(1, false)}${questionMarkup(2, true)}${questionMarkup(3, true)}${questionMarkup(4, true)}</div></section></main>
 <aside aria-label="Asistente virtual">${doctorRepeatMarkup.join('')}${doctorStopMarkup.join('')}${doctorHelpMarkup.join('')}<span id="patient-interview-doctor-repeat-status" role="status"></span></aside>
<script>${uiSource}</script><script>${dictationSource}</script><script>${pageScript}</script><script>${actualViewRuntimeScript}</script></body></html>`;
fs.writeFileSync(fixture, html, 'utf8');

try {
    const result = spawnSync(chrome, [
        '--headless=new', '--virtual-time-budget=6000', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
        '--disable-background-networking', '--disable-sync', '--disable-extensions',
        `--user-data-dir=${chromeProfile}`, '--dump-dom', pathToFileURL(fixture).href,
    ], {encoding: 'utf8', timeout: 20000, windowsHide: true, maxBuffer: 2 * 1024 * 1024});
    assert.equal(result.error, undefined, `Chrome DOM execution failed: ${result.error?.message || 'unknown process error'}`);
    assert.equal(result.status, 0, `Chrome exited with ${result.status}; ${String(result.stderr || '').slice(-600)}`);
    const bodyTag = result.stdout.match(/<body\b[^>]*>/)?.[0] || '';
    const domTestError = bodyTag.match(/data-dom-test-error="([^"]*)"/)?.[1] || 'no sanitized DOM detail';
    assert.match(bodyTag, /data-dom-test-result="PASS"/, `Chrome DOM fixture did not report PASS; ${domTestError}`);
    const runtimeError = bodyTag.match(/data-actual-view-runtime-error="([^"]*)"/)?.[1] || 'no sanitized runtime detail';
    assert.match(bodyTag, /data-actual-view-runtime-result="PASS"/,
        `actual view JavaScript binding/regression test did not report PASS; ${runtimeError}`);
    console.log('M03_MICROPHONE_DOM_TEST=PASS (isolated headless Chrome; no application session, HTTP, microphone or database)');
    console.log('AVATAR_REPEAT=PASS_FIXTURE (actual panel/shortcut markup and handlers; M02/M03, no-F5, equivalent initialization, history, double-click cancellation, no save or answer changes; no real audio)');
    console.log('AVATAR_STOP_AUDIO=PASS_FIXTURE (actual assistant panel stop control; stops active voice from panel repeat and floating shortcut, enables when speaking, disables when idle, double-click safe; no real audio)');
    console.log('OLD_REPEAT_AND_HELP_REMOVED=PASS_FIXTURE (old repeat and help buttons no longer generated in question cards)');
    console.log('AVATAR_HELP=PASS_FIXTURE (actual panel/shortcut help markup and handlers; M02/M03, active question help block mounted, HELP event spoken, no save or answer changes; no real audio)');
    console.log('ACTUAL_VIEW_RUNTIME=PASS (actual question navigation, previous-answer input preload, authorized inline edit, unsaved-edit cancellation, repeat/help targeting, microphone guards and M02 binding executed from entrevista.php source)');
    console.log('DOM_CASES=question1,question2,m02-to-m03-stale-contract,server-readback-reconciliation,question2-enabled-after-readback,server-write-disabled,question-write-disabled-no-diagnostics,persisted-question-without-write-auth,stale-target-silent,permission-pending,permission-denied,connecting,error-retry,disabled-unavailable,disabled-unauthorized,no-duplicates,question-change,inactive-module');
    console.log('ACTUAL_VIEW_CASES=actual-bind,actual-sync,permission-grant-deny,question-change,mic-click,repeat-help-targets,previous-answer-input-preload,authorized-inline-edit,microphone-in-edit,active-target-preserved,return-discards-unsaved-edit,return-to-pending,bidirectional-history-navigation,history-edit-target,contextual-action-revealed-after-readback,no-f5-transition,readback-reload-equivalence,not-remembered-readback,three-consecutive-questions,piper-question-help-alignment,late-response-does-not-overwrite-current-view,no-diagnostics,no-duplicates,missing-contract-fail-closed,m02-regression,no-window-errors,no-unhandled-rejections');
} finally {
    const tempRoot = path.resolve(os.tmpdir());
    const resolvedProfile = path.resolve(profile);
    assert.ok(resolvedProfile.startsWith(tempRoot + path.sep) && path.basename(resolvedProfile).startsWith('m03-mic-dom-chrome-'), 'Refusing to clean an unexpected Chrome profile path.');
    fs.rmSync(resolvedProfile, {recursive: true, force: true});
}
