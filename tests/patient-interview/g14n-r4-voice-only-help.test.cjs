'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { spawnSync } = require('node:child_process');
const { pathToFileURL } = require('node:url');

const root = path.resolve(__dirname, '..', '..');
const viewPath = path.join(root, 'app', 'views', 'paciente', 'entrevista.php');
const view = fs.readFileSync(viewPath, 'utf8');

console.log('--- G14N-R4 Voice-Only Contextual Help Test Suite ---');

// 1. Static inspection: .interview-contextual-help visual suppression
assert.ok(view.includes('.interview-contextual-help { position:absolute !important; width:1px !important; height:1px !important; padding:0 !important; margin:-1px !important; overflow:hidden !important; clip:rect(0,0,0,0) !important; white-space:nowrap !important; border:0 !important; }'),
    'CSS must strictly suppress the visual presentation of contextual help (.interview-contextual-help)');
assert.ok(view.includes("panel.className = 'interview-contextual-help visually-hidden'"),
    'Contextual help node must be tagged with visually-hidden for non-visual accessibility');
console.log('CHECK 1 PASS: CSS and class-level visual suppression verified');

// 2. Doctor avatar controls and order intact
const panelActionsMatch = view.match(/<div class="patient-interview-doctor-actions"[^>]*>([\s\S]*?)<\/div>/);
assert.ok(panelActionsMatch, 'Doctor panel actions container must exist');
const actionButtons = [...panelActionsMatch[1].matchAll(/<(?:button|a)\b[^>]*>([\s\S]*?)<\/(?:button|a)>/g)].map(m => m[0]);
assert.equal(actionButtons.length, 4, 'Doctor panel must contain exactly 4 action controls');
assert.ok(actionButtons[0].includes('data-doctor-repeat'), 'Control 1: Repetir pregunta');
assert.ok(actionButtons[1].includes('data-doctor-stop'), 'Control 2: Detener audio');
assert.ok(actionButtons[2].includes('data-doctor-help'), 'Control 3: Ayuda con esta pregunta');
assert.ok(actionButtons[3].includes('historia-clinica/respuestas-previas'), 'Control 4: Respuestas previas');
console.log('CHECK 2 PASS: Avatar controls, order, and Respuestas previas link fully intact');

// 3. Headless Chrome browser suite for behavioral validation
const chromeCandidates = [
    process.env.CHROME_PATH,
    path.join(process.env.ProgramFiles || 'C:\\Program Files', 'Google', 'Chrome', 'Application', 'chrome.exe'),
    path.join(process.env['ProgramFiles(x86)'] || 'C:\\Program Files (x86)', 'Google', 'Chrome', 'Application', 'chrome.exe'),
].filter(Boolean);
const chrome = chromeCandidates.find(c => fs.existsSync(c));
assert.ok(chrome, 'A local Chrome installation is required for browser-level validation.');

const extractFn = (regex, name) => {
    const match = view.match(regex);
    assert.ok(match, `Could not extract ${name}`);
    return match[0].trim();
};

const resolveActiveHelpTargetSrc = extractFn(/const resolveActiveHelpTarget = \(\) => \{[\s\S]*?\n    \};/, 'resolveActiveHelpTarget');
const refreshAvatarHelpControlsSrc = extractFn(/const refreshAvatarHelpControls = \(\) => \{[\s\S]*?\n    \};/, 'refreshAvatarHelpControls');
const bindDoctorHelpControlsSrc = extractFn(/const bindDoctorHelpControls = \(\) => \{[\s\S]*?\n    \};/, 'bindDoctorHelpControls');
const showContractHelpSrc = extractFn(/const showContractHelp = button => \{[\s\S]*?\n    \};/, 'showContractHelp');
const stableTargetForMessageSrc = extractFn(/const stableTargetForMessage = message => \{[\s\S]*?\n    \};/, 'stableTargetForMessage');

const browserTestScript = `
(async () => {
    const check = (condition, msg) => { if (!condition) throw new Error(msg); };
    let currentTarget = 'M02:record:family_heart';
    let narrationActive = false;
    let spokenEvents = [];
    let stoppedVoices = 0;
    let overlappingAudios = 0;
    let statusText = '';
    const setStatus = msg => { statusText = msg; };
    const moduleBlockedVoiceEventFor = () => null;
    const speechSupported = () => true;
    const piperM02Audio = { m04: { enabled: false } };
    let syncAvatarStopControls = () => {};

    const voiceController = {
        enabled: true,
        muted: false,
        speakEvent: event => {
            if (narrationActive) overlappingAudios++;
            narrationActive = true;
            spokenEvents.push(event);
            syncAvatarStopControls({ speaking: true, audioPending: false, paused: false, audioBlocked: false });
            return true;
        },
        speakSequence: events => {
            if (narrationActive) overlappingAudios++;
            narrationActive = true;
            spokenEvents.push(...events);
            syncAvatarStopControls({ speaking: true, audioPending: false, paused: false, audioBlocked: false });
            return true;
        },
        cancel: () => {
            if (narrationActive) stoppedVoices++;
            narrationActive = false;
            syncAvatarStopControls({ speaking: false, audioPending: false, paused: false, audioBlocked: false });
            return true;
        },
        snapshot: () => ({ speaking: narrationActive, audioPending: false, paused: false, audioBlocked: false }),
    };
    const stopAssistantVoice = () => { voiceController.cancel(); };

    const questionsData = {
        'M02:record:family_heart': {
            module_id: 'M02',
            stable_id: 'M02:record:family_heart',
            help: 'Explica antecedentes de enfermedades cardiacas.',
            voice_text: '¿Tiene antecedentes de enfermedades del corazón?',
        },
        'M03:question:1': {
            module_id: 'M03',
            stable_id: 'M03:question:1',
            help: 'Explica consumo de tabaco activo.',
            voice_text: '¿Usted fuma cigarros de nicotina?',
        },
    };

    const conversationCoordinator = {
        snapshot: () => ({ currentTarget }),
        actionContract: () => null,
        questionContract: target => questionsData[target] || null,
        voiceEvent: (type, target) => ({
            type,
            stable_id: target,
            text: type === 'HELP' ? questionsData[target]?.help : questionsData[target]?.voice_text,
        }),
    };

    let active = 0;
    let m03ReviewOnly = false;
    let m03PreviousEditOnly = false;
    let m03ReviewTarget = '';
    const questions = Array.from(document.querySelectorAll('.patient-interview-question'));

    ${stableTargetForMessageSrc}
    ${resolveActiveHelpTargetSrc}
    ${refreshAvatarHelpControlsSrc}
    let syncAvatarHelpControls = refreshAvatarHelpControls;
    ${bindDoctorHelpControlsSrc}
    ${showContractHelpSrc}

    const helpButtons = Array.from(document.querySelectorAll('[data-doctor-help]'));
    const stopButton = document.querySelector('[data-doctor-stop]');
    check(helpButtons.length === 2, 'Two help buttons present');
    check(stopButton !== null, 'Stop button present');

    syncAvatarStopControls = snapshot => {
        const active = Boolean(snapshot && (snapshot.speaking || snapshot.paused || snapshot.audioPending || snapshot.audioBlocked));
        stopButton.disabled = !active;
        stopButton.setAttribute('aria-disabled', active ? 'false' : 'true');
    };
    stopButton.addEventListener('click', () => { stopAssistantVoice(); });

    bindDoctorHelpControls();

    // 1 & 2. Panel and Floating shortcut trigger voice-only explanation
    helpButtons[0].click();
    check(narrationActive, 'Panel help started voice narration');
    check(spokenEvents.some(e => e.type === 'HELP' && e.stable_id === 'M02:record:family_heart'), 'Voice received M02 HELP event');
    check(!stopButton.disabled, 'Stop audio enabled while help is speaking');

    // 4. Verify contextual help is strictly non-visual
    const helpNode = document.querySelector('#q-m02-1 [data-question-help]');
    check(helpNode !== null, 'Help node mounted in DOM');
    check(helpNode.classList.contains('visually-hidden'), 'Help node has visually-hidden class');
    const computedStyle = window.getComputedStyle(helpNode);
    check(computedStyle.position === 'absolute' || helpNode.offsetWidth <= 1, 'Help element does not occupy visual layout space');
    const questionTextEl = document.querySelector('#q-m02-1 .interview-question-text');
    check(questionTextEl && !questionTextEl.hidden, '5. Clinical question remains visible');

    // 7. Stop audio interrupts help explanation
    stopButton.click();
    check(!narrationActive, '7. Stop audio interrupted help narration');
    check(stopButton.disabled, 'Stop audio disabled after stopping');

    // 8. Help can be repeated after being stopped
    helpButtons[1].click();
    check(narrationActive, '8. Help can be repeated after being stopped');
    check(spokenEvents.filter(e => e.type === 'HELP').length === 2, 'Spoke help second time');
    check(overlappingAudios === 0, '9. No overlapping audio playback');

    // 6. Help remains available and updates after transitioning to M03
    stopAssistantVoice();
    currentTarget = 'M03:question:1';
    document.querySelector('#q-m02-1').hidden = true;
    document.querySelector('#q-m03-1').hidden = false;
    active = 1;
    refreshAvatarHelpControls();
    check(!helpButtons[0].disabled && !helpButtons[1].disabled, '6. Help buttons enabled on active M03 question');
    helpButtons[0].click();
    check(spokenEvents.some(e => e.type === 'HELP' && e.stable_id === 'M03:question:1'), 'Help audio addresses active M03 question');

    const m03HelpNode = document.querySelector('#q-m03-1 [data-question-help]');
    check(m03HelpNode && m03HelpNode.classList.contains('visually-hidden'), 'M03 help node is visually hidden');

    document.body.dataset.testResult = 'PASS';
})().catch(err => {
    document.body.dataset.testResult = 'FAIL';
    document.body.dataset.testError = String(err.stack || err);
});
`;

const fixtureDir = fs.mkdtempSync(path.join(os.tmpdir(), 'g14n-r4-test-'));
const fixtureFile = path.join(fixtureDir, 'fixture.html');
const chromeProfileDir = path.join(fixtureDir, 'chrome-profile');

const fixtureHtml = `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
.interview-contextual-help { position:absolute !important; width:1px !important; height:1px !important; padding:0 !important; margin:-1px !important; overflow:hidden !important; clip:rect(0,0,0,0) !important; white-space:nowrap !important; border:0 !important; }
.visually-hidden { position:absolute !important; width:1px !important; height:1px !important; padding:0 !important; margin:-1px !important; overflow:hidden !important; clip:rect(0,0,0,0) !important; white-space:nowrap !important; border:0 !important; }
</style>
</head>
<body>
    <div class="patient-interview-doctor-actions">
        <button type="button" data-doctor-repeat disabled>Repetir</button>
        <button type="button" data-doctor-stop disabled>Detener</button>
        <button type="button" data-doctor-help disabled>Ayuda</button>
        <a href="<?=SERVIDOR;?>historia-clinica/respuestas-previas">Respuestas previas</a>
    </div>
    <div class="patient-interview-doctor-shortcuts">
        <button type="button" data-doctor-repeat disabled>Repetir</button>
        <button type="button" data-doctor-help disabled>Ayuda</button>
    </div>

    <article id="q-m02-1" class="patient-interview-question" data-module="M02" data-stable-id="M02:record:family_heart">
        <div class="patient-interview-message assistant">
            <h3 class="interview-question-text">¿Tiene antecedentes del corazón?</h3>
        </div>
    </article>
    <article id="q-m03-1" class="patient-interview-question" data-module="M03" data-question-id="1" data-eligible="1" hidden>
        <div class="patient-interview-message assistant">
            <h3 class="interview-question-text">¿Usted fuma?</h3>
        </div>
    </article>

    <script>${browserTestScript}</script>
</body>
</html>`;

fs.writeFileSync(fixtureFile, fixtureHtml, 'utf8');

try {
    const res = spawnSync(chrome, [
        '--headless=new', '--virtual-time-budget=6000', '--disable-gpu', '--no-first-run',
        '--no-default-browser-check', '--disable-background-networking', '--disable-sync',
        '--disable-extensions', `--user-data-dir=${chromeProfileDir}`, '--dump-dom',
        pathToFileURL(fixtureFile).href,
    ], { encoding: 'utf8', timeout: 20000, windowsHide: true, maxBuffer: 2 * 1024 * 1024 });

    assert.equal(res.error, undefined, `Chrome execution failed: ${res.error?.message}`);
    const matchResult = res.stdout.match(/data-test-result="([^"]*)"/)?.[1];
    const matchError = res.stdout.match(/data-test-error="([^"]*)"/)?.[1];
    assert.equal(matchResult, 'PASS', `Test did not report PASS. Error: ${matchError}`);

    console.log('CHECK 3 PASS: Browser suite verified voice-only help, visual suppression, stop audio and repeat');
    console.log('G14N_R4_VOICE_ONLY_HELP_TEST=PASS');
} finally {
    fs.rmSync(fixtureDir, { recursive: true, force: true });
}
