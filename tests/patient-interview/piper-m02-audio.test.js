const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../public/assets/js/patient-interview-voice.js'), 'utf8');
const viewSource = fs.readFileSync(path.resolve(__dirname, '../../app/views/paciente/entrevista.php'), 'utf8');
const question = {
    type: 'QUESTION',
    module_id: 'M02',
    stable_id: 'M02:family:enfermedades-del-coraz-n',
    text: '¿Algún familiar tiene enfermedades del corazón?',
};
const validEntry = {
    module_id: 'M02', stable_id: question.stable_id, text: question.text,
    text_sha256: 'b'.repeat(64), audio_sha256: 'f'.repeat(64),
    src: '/assets/audio/patient-interview/m02/piper-claude/M02-01.mp3?v=1',
};
const help = {
    type: 'HELP', module_id: 'M02', stable_id: question.stable_id,
    text: 'Ayuda canónica sintética para esta pregunta.',
};
const validHelpEntry = {
    module_id: 'M02', stable_id: question.stable_id, text: help.text,
    text_sha256: 'a'.repeat(64), audio_sha256: 'e'.repeat(64),
    src: '/assets/audio/patient-interview/m02/piper-claude/M02-01-help.mp3?v=1',
};
const error = {
    type: 'ERROR', module_id: 'M02', error_code: 'EMPTY_RESPONSE',
    stable_id: 'M02:error:EMPTY_RESPONSE', question_stable_id: question.stable_id,
    text: 'Escribe una respuesta para revisarla.',
};
const validErrorEntry = {
    module_id: 'M02', error_code: error.error_code, stable_id: error.stable_id,
    shared_modules: ['M02', 'M03'],
    text: error.text, text_sha256: 'c'.repeat(64), audio_sha256: 'd'.repeat(64),
    src: '/assets/audio/patient-interview/m02/piper-claude/M02-error-empty-response.mp3?v=1',
};
const finalizeAction = {
    type: 'ACTION', module_id: 'M02', action_id: 'M02:action:finalize', stable_id: null,
    text: 'Tus antecedentes familiares están registrados. ¿Deseas finalizar esta sección y continuar?',
};
const validActionEntry = {
    module_id: 'M02', action_id: finalizeAction.action_id, stable_id: null, text: finalizeAction.text,
    text_sha256: '9'.repeat(64), audio_sha256: '8'.repeat(64),
    src: '/assets/audio/patient-interview/m02/piper-claude/M02-finalize.mp3?v=1',
};
const finalizeHelp = {
    type: 'HELP', module_id: 'M02', action_id: 'M02:action:finalize', stable_id: null,
    text: 'Ya respondiste las preguntas de antecedentes familiares. Puedes revisar tus respuestas en Respuestas previas antes de finalizar. Si toda la información es correcta y deseas continuar, pulsa el botón Finalizar antecedentes familiares y continuar.',
};
const validFinalizeHelpEntry = {
    module_id: 'M02', action_id: finalizeHelp.action_id, stable_id: null, text: finalizeHelp.text,
    text_sha256: '7'.repeat(64), audio_sha256: '6'.repeat(64),
    src: '/assets/audio/patient-interview/m02/piper-claude/M02-finalize-help.mp3?v=1',
};

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

const assertM02PiperUnavailable = (result, message) => {
    assert(result.speech.calls.length === 0 && result.controller.snapshot().state === 'VOICE_ERROR'
        && result.controller.getVoiceDiagnostic()?.source === 'unavailable', message);
};

class MockUtterance {
    constructor(text) { this.text = text; this.lang = ''; this.onend = null; this.onerror = null; }
}

class MockSpeechSynthesis {
    constructor() { this.speaking = false; this.paused = false; this.calls = []; this.cancelCount = 0; }
    getVoices() { return [{name: 'Microsoft Laura', lang: 'es-MX', voiceURI: 'laura', localService: true}]; }
    speak(utterance) { this.calls.push(utterance); this.current = utterance; this.speaking = true; }
    cancel() { this.cancelCount++; this.speaking = false; this.paused = false; this.current = null; }
    pause() { this.paused = true; }
    resume() { this.paused = false; this.speaking = true; }
}

class MockAudio {
    constructor() { this.src = ''; this.paused = true; this.currentTime = 0; this.playbackRate = 1; this.playCount = 0; MockAudio.instances.push(this); }
    play() {
        this.paused = false;
        this.playCount += 1;
        if (MockAudio.rejectNextPlay) {
            MockAudio.rejectNextPlay = false;
            const error = new Error('synthetic media play rejection');
            error.name = MockAudio.rejectName || 'NotAllowedError';
            MockAudio.rejectName = null;
            return Promise.reject(error);
        }
        this.onplaying?.();
        return Promise.resolve();
    }
    pause() { this.paused = true; }
    load() {}
    removeAttribute(name) { if (name === 'src') this.src = ''; }
    end() { this.paused = true; this.onended?.(); }
    fail() { this.onerror?.(new Error('audio_decode_error')); }
}
MockAudio.instances = [];
MockAudio.rejectNextPlay = false;
MockAudio.rejectName = null;

function createController(entries = {[question.stable_id]: {...validEntry}}, enabled = true, preferredVoice = null, errors = {}, actions = {}) {
    const window = {
        SpeechSynthesisUtterance: MockUtterance,
        Audio: MockAudio,
        URL,
        location: {href: 'http://clinica.test/historia-clinica/entrevista', origin: 'http://clinica.test'},
    };
    vm.runInNewContext(source, {window, URL, setTimeout, clearTimeout});
    const speech = new MockSpeechSynthesis();
    const Controller = window.PatientInterviewVoiceController;
    const controller = new Controller({
        speechApi: speech,
        utteranceConstructor: MockUtterance,
        audioConstructor: MockAudio,
        piperAudio: {enabled, entries, errors, actions},
        preferredVoice,
    });
    return {controller, speech};
}

async function run() {
    const startupBlock = viewSource.slice(viewSource.indexOf('const startupVoiceEvents ='), viewSource.indexOf('const autoStartVoice ='));
    assert(startupBlock.includes('question.action_id === snapshot.currentTarget && question.stable_id === null')
        && startupBlock.includes("moduleId === 'M02' ? {...question, module_id: 'M02'} : question"),
        'Startup must preserve the canonical M02 target and module identity so a finalization ACTION resolves its Piper clip.');
    const legacyCompositeStartup = createController();
    legacyCompositeStartup.controller.activateStartup({type: 'FIRST_START', text: 'Introducción sintética.'}, question);
    assert(MockAudio.instances.length === 0 && legacyCompositeStartup.speech.calls.length === 0
        && legacyCompositeStartup.controller.getVoiceDiagnostic()?.source === 'unavailable',
        'An unmapped composite INTERVIEW_STARTUP must never narrate M02 with a different browser voice.');
    MockAudio.instances.length = 0;
    const {controller, speech} = createController();
    controller.activate(question, null, {automatic: true});
    assert(MockAudio.instances.length === 1 && MockAudio.instances[0].src === validEntry.src, 'The active canonical M02 question must select its mapped Piper MP3.');
    assert(speech.calls.length === 0, 'Piper playback must not overlap Web Speech.');
    await Promise.resolve();
    assert(controller.snapshot().state === 'SPEAKING', 'Successful HTMLAudio playback must update the shared speaking state.');
    assert(controller.pause() && MockAudio.instances[0].paused && controller.snapshot().paused, 'Pause must pause the Piper HTMLAudio element.');
    assert(controller.resume(), 'Resume must resume a paused Piper HTMLAudio element.');
    await Promise.resolve();
    assert(!MockAudio.instances[0].paused && controller.snapshot().speaking, 'Resumed Piper playback must return to speaking state.');
    assert(controller.repeat(true), 'Repeat must replay the current canonical question.');
    assert(MockAudio.instances.length === 2 && MockAudio.instances[1].src === validEntry.src, 'Repeat must restart the same question audio.');
    await Promise.resolve();
    assert(controller.setRate(1.1) === 1.1 && MockAudio.instances[1].playbackRate === 1.1, 'The speed selector rate must update active Piper playback.');
    const speechCancelsBeforeStop = speech.cancelCount;
    assert(controller.stop(), 'Stop must cancel current Piper playback.');
    assert(MockAudio.instances[1].paused && MockAudio.instances[1].src === '' && controller.snapshot().state === 'READY', 'Stop must halt and release Piper audio without retaining a paused state.');
    assert(speech.cancelCount > speechCancelsBeforeStop, 'Stop must also cancel any Web Speech playback.');
    assert(controller.repeat(true), 'Repeat must work again after Stop.');
    assert(MockAudio.instances.length === 3 && MockAudio.instances[2].src === validEntry.src, 'Repeat after Stop must restart the active canonical MP3.');
    await Promise.resolve();
    const nextEvent = {type: 'QUESTION', module_id: 'M03', stable_id: 'M03:question:1', text: 'Pregunta M03 de regresión.'};
    assert(controller.speakEvent(nextEvent, {force: true}), 'A question outside M02 must continue through Web Speech.');
    assert(MockAudio.instances[2].paused && MockAudio.instances[2].src === '', 'Changing question must stop and detach the preceding MP3 immediately.');
    assert(speech.calls.length === 1, 'Only the non-M02 next question should use Web Speech.');

    MockAudio.instances.length = 0;
    const automaticFinalize = createController({}, true, null, {}, {[finalizeAction.action_id]: {...validActionEntry}});
    automaticFinalize.controller.activateAutomatically(finalizeAction);
    assert(MockAudio.instances.length === 1 && MockAudio.instances[0].src === validActionEntry.src
        && automaticFinalize.speech.calls.length === 0,
        'M02 startup in READY_TO_FINALIZE must use the dedicated Piper ACTION asset without browser-voice fallback.');

    MockAudio.instances.length = 0;
    const actionVoice = createController({}, true, null, {}, {[finalizeAction.action_id]: {...validActionEntry}});
    actionVoice.controller.speakEvent(finalizeAction, {force: true, userGesture: true});
    assert(MockAudio.instances.length === 1 && MockAudio.instances[0].src === validActionEntry.src,
        'The canonical M02 finalization ACTION must select only its dedicated mapped Piper asset despite having no stable_id.');
    assert(actionVoice.speech.calls.length === 0 && actionVoice.controller.lastEvent === finalizeAction,
        'Piper action playback must avoid duplicate Web Speech and remain the repeat target.');
    assert(actionVoice.controller.repeat(true) && MockAudio.instances.length === 2
        && MockAudio.instances[1].src === validActionEntry.src,
        'Repeat must replay the mapped finalization action clip.');

    MockAudio.instances.length = 0;
    const actionHelpVoice = createController({}, true, null, {}, {
        [finalizeAction.action_id]: {...validActionEntry, help: {...validFinalizeHelpEntry}},
    });
    actionHelpVoice.controller.speakEvent(finalizeAction, {force: true, userGesture: true});
    const repeatableAction = actionHelpVoice.controller.lastEvent;
    actionHelpVoice.controller.speakEvent(finalizeHelp, {force: true, userGesture: true});
    assert(MockAudio.instances.length === 2 && MockAudio.instances[1].src === validFinalizeHelpEntry.src,
        'Finalization Help must select its dedicated ACTION-help clip instead of the confirmation clip.');
    assert(MockAudio.instances[0].paused && MockAudio.instances[0].src === '',
        'Starting finalization Help must cancel and detach the previous confirmation narration.');
    assert(actionHelpVoice.speech.calls.length === 0 && actionHelpVoice.controller.lastEvent === repeatableAction,
        'Temporary Help narration must not replace the repeat target or call Web Speech over its own Piper clip.');
    assert(actionHelpVoice.controller.repeat(true) && MockAudio.instances.length === 3
        && MockAudio.instances[2].src === validActionEntry.src,
        'Repeating after finalization Help must replay the main confirmation, not the Help clip.');

    const m03Help = {type: 'HELP', module_id: 'M03', stable_id: 'M03:question:1', text: 'Ayuda de regresión M03.'};
    actionHelpVoice.controller.speakEvent(m03Help, {force: true, userGesture: true});
    assert(actionHelpVoice.controller.lastEvent === m03Help,
        'The focused M02 Help repeat-target behavior must not change M03 behavior.');

    MockAudio.instances.length = 0;
    const actionHelpWithoutClip = createController({}, true, null, {}, {[finalizeAction.action_id]: {...validActionEntry}});
    actionHelpWithoutClip.controller.speakEvent(finalizeHelp, {force: true, userGesture: true});
    assert(MockAudio.instances.length === 0, 'Missing action-help audio must not reuse the finalization clip or switch the M02 voice.');
    assertM02PiperUnavailable(actionHelpWithoutClip, 'Missing action-help audio must expose a local unavailable diagnostic.');

    MockAudio.instances.length = 0;
    const mismatchedFinalizeHelp = createController({}, true, null, {}, {
        [finalizeAction.action_id]: {...validActionEntry, help: {...validFinalizeHelpEntry, text: 'Ayuda obsoleta.'}},
    });
    mismatchedFinalizeHelp.controller.speakEvent(finalizeHelp, {force: true, userGesture: true});
    assert(MockAudio.instances.length === 0, 'A mismatched finalization Help text must never select another voice or an unrelated Piper clip.');
    assertM02PiperUnavailable(mismatchedFinalizeHelp, 'A mismatched finalization Help clip must be diagnosed as unavailable.');

    MockAudio.instances.length = 0;
    const actionWithoutAudio = createController({[question.stable_id]: {...validEntry}});
    actionWithoutAudio.controller.speakEvent(finalizeAction, {force: true, userGesture: true});
    assert(MockAudio.instances.length === 0, 'Without a dedicated action mapping, finalization must not reuse a question clip or switch voice.');
    assertM02PiperUnavailable(actionWithoutAudio, 'A missing finalization clip must be reported as unavailable.');

    MockAudio.instances.length = 0;
    const mismatchedAction = createController({}, true, null, {}, {[finalizeAction.action_id]: {...validActionEntry, text: 'Texto incorrecto.'}});
    mismatchedAction.controller.speakEvent(finalizeAction, {force: true, userGesture: true});
    assert(MockAudio.instances.length === 0, 'A finalization action with mismatched canonical text must not use a different-voice fallback.');
    assertM02PiperUnavailable(mismatchedAction, 'A mismatched finalization clip must be diagnosed as unavailable.');

    MockAudio.instances.length = 0;
    const wrongActionIdentity = createController({}, true, null, {}, {[finalizeAction.action_id]: {...validActionEntry}});
    wrongActionIdentity.controller.speakEvent({...finalizeAction, module_id: 'M03'}, {force: true, userGesture: true});
    assert(MockAudio.instances.length === 0 && wrongActionIdentity.speech.calls.length === 0,
        'An M02 action mislabeled as another module must fail closed without speaking with a different voice.');
    assertM02PiperUnavailable(wrongActionIdentity, 'A mismatched action module identity must be diagnosed locally.');

    MockAudio.instances.length = 0;
    const actionWithQuestionIdentity = createController({}, true, null, {}, {[finalizeAction.action_id]: {...validActionEntry}});
    actionWithQuestionIdentity.controller.speakEvent({...finalizeAction, stable_id: question.stable_id}, {force: true, userGesture: true});
    assert(MockAudio.instances.length === 0, 'A finalization ACTION must keep stable_id null and reject question identity leakage without switching voice.');
    assertM02PiperUnavailable(actionWithQuestionIdentity, 'Invalid finalization identity must fail closed.');

    MockAudio.instances.length = 0;
    const crossOriginAction = createController({}, true, null, {}, {[finalizeAction.action_id]: {...validActionEntry, src: 'https://audio.example.invalid/finalize.mp3'}});
    crossOriginAction.controller.speakEvent(finalizeAction, {force: true, userGesture: true});
    assert(MockAudio.instances.length === 0, 'A finalization Piper clip must remain same-origin and must not switch to Web Speech.');
    assertM02PiperUnavailable(crossOriginAction, 'Cross-origin finalization audio must fail closed with an unavailable diagnostic.');

    MockAudio.instances.length = 0;
    const mismatched = createController({[question.stable_id]: {...validEntry, text: 'Texto obsoleto.'}});
    mismatched.controller.activate(question);
    assert(MockAudio.instances.length === 0, 'A manifest/text mismatch must not replace the M02 Piper voice with Web Speech.');
    assertM02PiperUnavailable(mismatched, 'A manifest/text mismatch must be diagnosed as unavailable.');

    MockAudio.instances.length = 0;
    const failedFile = createController(undefined, true, {voiceURI: 'laura', lang: 'es-MX', name: 'Microsoft Laura'});
    failedFile.controller.activate(question);
    const failedAudio = MockAudio.instances[0];
    failedAudio.fail();
    assert(failedAudio.paused && failedAudio.src === '', 'A failed MP3 must be stopped and detached.');
    assertM02PiperUnavailable(failedFile, 'An M02 MP3 load/decode failure must not silently switch to the browser voice.');

    MockAudio.instances.length = 0;
    MockAudio.rejectNextPlay = true;
    const blockedAutoplay = createController();
    blockedAutoplay.controller.activateAutomatically(question);
    await Promise.resolve();
    await Promise.resolve();
    assert(MockAudio.instances.length === 1 && MockAudio.instances[0].src === '', 'A rejected automatic Piper play must stop and detach the blocked audio.');
    assert(blockedAutoplay.speech.calls.length === 0 && blockedAutoplay.controller.snapshot().audioBlocked,
        'A Piper autoplay denial must remain pending for a real user gesture without switching to Web Speech.');
    assert(blockedAutoplay.controller.getVoiceDiagnostic()?.source === 'blocked_autoplay'
        && blockedAutoplay.controller.getVoiceDiagnostic()?.fallbackReason === 'PIPER_AUDIO_AUTOPLAY_BLOCKED',
        'Autoplay denial must be distinguished from a missing or invalid Piper asset.');
    assert(blockedAutoplay.controller.repeat(true) && MockAudio.instances.length === 2
        && MockAudio.instances[1].src === validEntry.src && blockedAutoplay.speech.calls.length === 0,
        'The existing Repeat control must retry only the current Piper question after a user gesture.');

    MockAudio.instances.length = 0;
    MockAudio.rejectNextPlay = true;
    MockAudio.rejectName = 'NotSupportedError';
    const invalidAutomaticPlayback = createController();
    invalidAutomaticPlayback.controller.activateAutomatically(question);
    await Promise.resolve();
    await Promise.resolve();
    assert(invalidAutomaticPlayback.speech.calls.length === 0
        && invalidAutomaticPlayback.controller.snapshot().state === 'VOICE_ERROR'
        && invalidAutomaticPlayback.controller.getVoiceDiagnostic()?.source === 'unavailable',
        'Non-policy Piper playback rejection must be classified unavailable, not mistaken for autoplay or sent to Web Speech.');

    MockAudio.instances.length = 0;
    const wrongOrigin = createController({[question.stable_id]: {...validEntry, src: 'https://audio.example.invalid/M02-01.mp3'}});
    wrongOrigin.controller.activate(question);
    assert(MockAudio.instances.length === 0 && wrongOrigin.speech.calls.length === 0, 'M02 audio outside the same origin must be rejected without switching voices.');
    assertM02PiperUnavailable(wrongOrigin, 'Cross-origin M02 audio must fail closed with an unavailable diagnostic.');

    MockAudio.instances.length = 0;
    const disabled = createController({[question.stable_id]: {...validEntry}}, false);
    disabled.controller.activate(question);
    assert(MockAudio.instances.length === 0 && disabled.speech.calls.length === 1, 'A disabled/local-nonpilot configuration must preserve Web Speech.');

    MockAudio.instances.length = 0;
    const noAudioForQuestion = createController({});
    noAudioForQuestion.controller.activate(question);
    assert(MockAudio.instances.length === 0, 'An M02 question without an approved entry must not silently use Web Speech.');
    assertM02PiperUnavailable(noAudioForQuestion, 'A missing M02 question asset must expose an unavailable diagnostic.');

    MockAudio.instances.length = 0;
    const helpWithoutOwnAudio = createController({[question.stable_id]: {...validEntry}});
    helpWithoutOwnAudio.controller.speakEvent(help, {force: true, userGesture: true});
    assert(MockAudio.instances.length === 0, 'M02 Help without its own asset must not reuse the question MP3 or switch voice.');
    assertM02PiperUnavailable(helpWithoutOwnAudio, 'M02 Help without an approved resource must be diagnosed as unavailable.');

    MockAudio.instances.length = 0;
    const helpWithOwnAudio = createController({[question.stable_id]: {...validEntry, help: {...validHelpEntry}}});
    helpWithOwnAudio.controller.speakEvent(help, {force: true, userGesture: true});
    assert(MockAudio.instances.length === 1 && MockAudio.instances[0].src === validHelpEntry.src, 'A separately mapped canonical M02 help MP3 must play for HELP only.');
    assert(helpWithOwnAudio.speech.calls.length === 0, 'Dedicated Piper help playback must not overlap Web Speech.');

    MockAudio.instances.length = 0;
    const mismatchedHelp = createController({[question.stable_id]: {...validEntry, help: {...validHelpEntry, text: 'Ayuda obsoleta.'}}});
    mismatchedHelp.controller.speakEvent(help, {force: true, userGesture: true});
    assert(MockAudio.instances.length === 0, 'A help audio text mismatch must not fall back to Web Speech or question audio.');
    assertM02PiperUnavailable(mismatchedHelp, 'A mismatched M02 Help asset must be diagnosed as unavailable.');

    MockAudio.instances.length = 0;
    const dynamicStableId = 'M02:family:enfermedad-familiar-agregada';
    const dynamicHelp = {...help, stable_id: dynamicStableId};
    const dynamicHelpEntry = {...validHelpEntry, stable_id: dynamicStableId};
    const dynamicHelpController = createController({[dynamicStableId]: {module_id: 'M02', stable_id: dynamicStableId, help: dynamicHelpEntry}});
    dynamicHelpController.controller.speakEvent(dynamicHelp, {force: true, userGesture: true});
    assert(MockAudio.instances.length === 1 && MockAudio.instances[0].src === dynamicHelpEntry.src, 'A dynamically generated canonical M02 question may use generic Help audio only when its own exact stable_id and Help text are mapped.');

    MockAudio.instances.length = 0;
    const errorController = createController(undefined, true, null, {[error.error_code]: {...validErrorEntry}});
    errorController.controller.activate(question, null, {automatic: true});
    const originalQuestionEvent = errorController.controller.lastEvent;
    errorController.controller.speakEvent(error, {force: true});
    assert(MockAudio.instances.length === 2 && MockAudio.instances[1].src === validErrorEntry.src, 'A known current M02 error code must select its own Piper error MP3.');
    assert(errorController.controller.lastEvent === originalQuestionEvent, 'A temporary error notice must not replace the active clinical question used by Repeat.');
    assert(errorController.speech.calls.length === 0, 'M02 error audio must not overlap Web Speech.');
    assert(errorController.controller.repeat(true), 'The existing Repeat control must remain available after an error notice.');
    assert(MockAudio.instances.length === 3 && MockAudio.instances[2].src === validEntry.src
        && MockAudio.instances[1].paused && MockAudio.instances[1].src === '', 'Repeat after an error must cancel its audio and replay the active question, not the error.');

    MockAudio.instances.length = 0;
    const m03Question = {type: 'QUESTION', module_id: 'M03', stable_id: 'M03:question:12', text: 'Pregunta M03 sintética.'};
    const m03EmptyError = {...error, module_id: 'M03', question_stable_id: m03Question.stable_id};
    const sharedEmptyResponse = createController(undefined, true, null, {[error.error_code]: {...validErrorEntry}});
    sharedEmptyResponse.controller.setRate(1.15);
    sharedEmptyResponse.controller.activate(question, null, {automatic: true});
    const m03QuestionAudio = MockAudio.instances[0];
    sharedEmptyResponse.controller.speakEvent(m03EmptyError, {force: true});
    assert(MockAudio.instances.length === 2 && MockAudio.instances[1].src === validErrorEntry.src,
        'An explicitly shared EMPTY_RESPONSE may reuse the signed Piper clip for an active canonical M03 question.');
    assert(m03QuestionAudio.paused && m03QuestionAudio.src === '', 'An empty-response notice must cancel the previous Piper question before starting its own clip.');
    assert(sharedEmptyResponse.speech.calls.length === 0, 'Shared Piper error playback must not overlap Web Speech.');
    assert(sharedEmptyResponse.controller.lastEvent === question, 'A shared M03 error must not replace the repeatable active clinical question.');
    assert(MockAudio.instances[1].playbackRate === 1.15, 'Shared Piper error playback must honor the current voice speed.');

    MockAudio.instances.length = 0;
    const unsharedM03Error = createController(undefined, true, null, {[error.error_code]: {...validErrorEntry, shared_modules: ['M02']}});
    unsharedM03Error.controller.speakEvent(m03EmptyError, {force: true});
    assert(MockAudio.instances.length === 0 && unsharedM03Error.speech.calls.length === 1,
        'M03 must not use an M02 clip unless the server config explicitly scopes the empty-response asset to M03.');

    MockAudio.instances.length = 0;
    const nonEmptyM03Error = createController(undefined, true, null, {[error.error_code]: {...validErrorEntry}});
    nonEmptyM03Error.controller.speakEvent({...m03EmptyError, error_code: 'SAVE_FAILED', stable_id: 'M02:error:SAVE_FAILED'}, {force: true});
    assert(MockAudio.instances.length === 0 && nonEmptyM03Error.speech.calls.length === 1,
        'Cross-module sharing is limited to EMPTY_RESPONSE, not unrelated M02 errors.');

    MockAudio.instances.length = 0;
    const unknownError = createController(undefined, true, null, {[error.error_code]: {...validErrorEntry}});
    unknownError.controller.speakEvent({...error, error_code: 'CSRF_ERROR', stable_id: 'M02:error:CSRF_ERROR'}, {force: true});
    assert(MockAudio.instances.length === 0, 'An unapproved M02 error must neither route to Piper nor switch to Web Speech.');
    assertM02PiperUnavailable(unknownError, 'An unapproved M02 error must fail closed with a local diagnostic.');

    MockAudio.instances.length = 0;
    const staleError = createController(undefined, true, null, {[error.error_code]: {...validErrorEntry}});
    staleError.controller.speakEvent({...error, question_stable_id: 'M03:question:1'}, {force: true});
    assert(MockAudio.instances.length === 0, 'A stale M02 error must not be narrated using an unrelated voice.');
    assertM02PiperUnavailable(staleError, 'A stale M02 error must fail closed.');

    MockAudio.instances.length = 0;
    const restoredAfterReload = createController();
    restoredAfterReload.controller.activate(question);
    assert(MockAudio.instances.length === 1 && restoredAfterReload.speech.calls.length === 0, 'A newly initialized controller (F5 recovery) must resolve audio from the active stable_id, not prior playback state.');
    restoredAfterReload.controller.handleEvent(question);
    restoredAfterReload.controller.handleEvent(question);
    assert(MockAudio.instances.length === 1, 'Repeated delivery of the same canonical question event after startup must not create duplicate playback.');

    console.log('PIPER_M02_AUDIO_TESTS=PASS');
}

run().catch(error => { console.error(error); process.exitCode = 1; });
