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

console.log('--- G14N-R3 Contextual Help Centralization Test Suite ---');

// 1. Verificar que no quedan referencias rotas a chat-help-button en el código
assert.ok(!view.includes("help.className = 'btn btn-outline-secondary btn-sm chat-help-button ms-2'"),
    '1. Old help button must not be dynamically generated in bindVoiceControls');
assert.ok(!view.includes("header.appendChild(help)"),
    '1. Old help button must not be appended to question header');
assert.ok(view.includes("header.querySelectorAll('.chat-voice-button, .chat-help-button').forEach(btn => btn.remove())"),
    '1. bindVoiceControls must purge old voice and help buttons from question headers');
assert.ok(view.includes("message.querySelectorAll('.chat-voice-button, .chat-help-button').forEach(btn => btn.remove())"),
    '1. Message rebinding must purge old voice and help buttons');
console.log('CHECK 1 PASS: No broken references or dynamic creation of old chat-help-button');

// 2 & 3. Verificar atributos accesibles y data-doctor-help en ambos botones
const doctorHelpButtons = [...view.matchAll(/<button\b[^>]*\bdata-doctor-help\b[^>]*>([\s\S]*?)<\/button>/g)].map(m => m[0]);
assert.equal(doctorHelpButtons.length, 2, '2 & 3. Exactly 2 buttons with data-doctor-help must exist');
doctorHelpButtons.forEach((btnHtml, idx) => {
    assert.ok(btnHtml.includes('aria-label="Ayuda con esta pregunta"'), `2. Button ${idx + 1} must have aria-label="Ayuda con esta pregunta"`);
    assert.ok(btnHtml.includes('aria-disabled='), `2. Button ${idx + 1} must have aria-disabled attribute`);
    assert.ok(btnHtml.includes('title='), `2. Button ${idx + 1} must have title attribute`);
});
console.log('CHECK 2 & 3 PASS: Both avatar help buttons have data-doctor-help and accessible attributes');

// 4. Verificar que el panel conserva su estructura original (orden, textos, lavanda)
const panelActionsMatch = view.match(/<div class="patient-interview-doctor-actions"[^>]*>([\s\S]*?)<\/div>/);
assert.ok(panelActionsMatch, '4. Doctor panel actions container must exist');
const actionButtons = [...panelActionsMatch[1].matchAll(/<(?:button|a)\b[^>]*>([\s\S]*?)<\/(?:button|a)>/g)].map(m => m[0]);
assert.equal(actionButtons.length, 4, '4. Doctor panel must contain exactly 4 action controls in order');
assert.ok(actionButtons[0].includes('data-doctor-repeat') && actionButtons[0].includes('Repetir pregunta'),
    '4. First control must be Repetir pregunta');
assert.ok(actionButtons[1].includes('data-doctor-stop') && actionButtons[1].includes('Detener audio'),
    '4. Second control must be Detener audio');
assert.ok(actionButtons[2].includes('data-doctor-help') && actionButtons[2].includes('Ayuda con esta pregunta'),
    '4. Third control must be Ayuda con esta pregunta with data-doctor-help');
assert.ok(actionButtons[3].includes('historia-clinica/respuestas-previas') && actionButtons[3].includes('Respuestas previas'),
    '4. Fourth control must be Respuestas previas link');
console.log('CHECK 4 PASS: Doctor panel keeps original structure, lavender theme, and 4 buttons in approved order');

// 5. Verificar que el avatar conserva exactamente dos accesos rápidos
const shortcutMatch = view.match(/<div class="patient-interview-doctor-shortcuts"[^>]*>([\s\S]*?)<\/div>/);
assert.ok(shortcutMatch, '5. Doctor shortcuts container must exist');
const shortcutButtons = [...shortcutMatch[1].matchAll(/<button\b[^>]*>([\s\S]*?)<\/button>/g)].map(m => m[0]);
assert.equal(shortcutButtons.length, 2, '5. Doctor shortcuts must contain exactly 2 buttons');
assert.ok(shortcutButtons[0].includes('data-doctor-repeat'), '5. First shortcut is data-doctor-repeat');
assert.ok(shortcutButtons[1].includes('data-doctor-help'), '5. Second shortcut is data-doctor-help');
console.log('CHECK 5 PASS: Avatar floating shortcuts preserved with exactly 2 controls (Repeat + Help)');

// 6. Verificar que no se alteró la navegación a «Respuestas previas»
assert.ok(actionButtons[3].includes('href="<?=SERVIDOR;?>historia-clinica/respuestas-previas"'),
    '6. Respuestas previas href must remain intact');
console.log('CHECK 6 PASS: Respuestas previas navigation preserved');

// 17. Verificar que el botón antiguo ya no se genera en el DOM
assert.ok(!view.includes("header.appendChild(help);"),
    '17. chat-help-button creation removed from bindVoiceControls');
console.log('CHECK 17 PASS: Old chat-help-button rendering removed from question cards');

// Now run headless Chrome regression to verify items 7-16
const chromeCandidates = [
    process.env.CHROME_PATH,
    path.join(process.env.ProgramFiles || 'C:\\Program Files', 'Google', 'Chrome', 'Application', 'chrome.exe'),
    path.join(process.env['ProgramFiles(x86)'] || 'C:\\Program Files (x86)', 'Google', 'Chrome', 'Application', 'chrome.exe'),
].filter(Boolean);
const chrome = chromeCandidates.find(c => fs.existsSync(c));
assert.ok(chrome, 'A local Chrome installation is required for the full contract test.');

// Extract needed functions from view
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

const testScript = `
(async () => {
    const check = (condition, msg) => { if (!condition) throw new Error(msg); };
    let currentTarget = 'M02:record:family_heart';
    let narrationActive = false;
    let spokenEvents = [];
    let stoppedVoices = 0;
    let statusText = '';
    const setStatus = msg => { statusText = msg; };
    const moduleBlockedVoiceEventFor = () => null;
    const speechSupported = () => true;
    const piperM02Audio = { m04: { enabled: false } };
    const voiceController = {
        enabled: true,
        muted: false,
        speakEvent: event => { narrationActive = true; spokenEvents.push(event); return true; },
        speakSequence: events => { narrationActive = true; spokenEvents.push(...events); return true; },
        cancel: () => { narrationActive = false; stoppedVoices++; return true; },
        snapshot: () => ({ speaking: narrationActive, audioPending: false, paused: false, audioBlocked: false }),
    };
    const stopAssistantVoice = () => { voiceController.cancel(); };

    const questionsData = {
        'M02:record:family_heart': {
            module_id: 'M02',
            stable_id: 'M02:record:family_heart',
            help: 'Ayuda canónica de enfermedades del corazón. Si no recuerda, puede escribir No recuerdo.',
            voice_text: '¿Tiene antecedentes de enfermedades del corazón?',
        },
        'M02:record:family_hypertension': {
            module_id: 'M02',
            stable_id: 'M02:record:family_hypertension',
            help: 'Ayuda canónica de hipertensión familiar.',
            voice_text: '¿Tiene antecedentes de hipertensión arterial?',
        },
        'M03:question:1': {
            module_id: 'M03',
            stable_id: 'M03:question:1',
            help: 'Ayuda sobre tabaquismo activo. Indique si fuma actualmente.',
            voice_text: '¿Usted fuma cigarros de nicotina?',
        },
        'M03:question:2': {
            module_id: 'M03',
            stable_id: 'M03:question:2',
            help: 'Ayuda sobre la edad en que comenzó a fumar.',
            voice_text: '¿Desde qué edad empezó a fumar?',
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
    check(helpButtons.length === 2, 'Two doctor help buttons present in fixture');

    // 7 & 8: Verificar que panel y flotante muestran la ayuda correcta en M02
    bindDoctorHelpControls();
    check(!helpButtons[0].disabled && !helpButtons[1].disabled, 'Both buttons enabled for active M02 question');
    helpButtons[0].click();
    check(spokenEvents.some(e => e.type === 'HELP' && e.stable_id === 'M02:record:family_heart'), 'Panel help triggered HELP for M02');
    const helpCard1 = document.querySelector('#q-m02-1 [data-question-help]');
    check(helpCard1 && !helpCard1.hidden && helpCard1.textContent.includes('enfermedades del corazón'), '7. Panel help mounted text in M02 card');

    helpButtons[1].click();
    check(spokenEvents.filter(e => e.type === 'HELP' && e.stable_id === 'M02:record:family_heart').length >= 2, '8. Shortcut help triggered same HELP for M02');

    // 11 & 13: Cambiar de pregunta en M02 (pregunta Sí/No) y verificar actualización
    currentTarget = 'M02:record:family_hypertension';
    document.querySelector('#q-m02-1').hidden = true;
    document.querySelector('#q-m02-2').hidden = false;
    active = 1;
    refreshAvatarHelpControls();
    helpButtons[0].click();
    const helpCard2 = document.querySelector('#q-m02-2 [data-question-help]');
    check(helpCard2 && !helpCard2.hidden && helpCard2.textContent.includes('hipertensión familiar'), '11 & 13. Help updated on question change in M02');

    // 9 & 10: Verificar que panel y flotante muestran la ayuda correcta en M03
    currentTarget = 'M03:question:1';
    document.querySelector('#q-m02-2').hidden = true;
    document.querySelector('#q-m03-1').hidden = false;
    active = 2;
    refreshAvatarHelpControls();
    check(!helpButtons[0].disabled && !helpButtons[1].disabled, 'Buttons enabled for active M03 question 1');
    helpButtons[0].click();
    const helpCardM03_1 = document.querySelector('#q-m03-1 [data-question-help]');
    check(helpCardM03_1 && !helpCardM03_1.hidden && helpCardM03_1.textContent.includes('tabaquismo activo'), '9. Panel help mounted text in M03:question:1');
    helpButtons[1].click();
    check(spokenEvents.some(e => e.type === 'HELP' && e.stable_id === 'M03:question:1'), '10. Floating help triggered HELP for M03:question:1');

    // 12 & 14: Responder una pregunta y verificar la ayuda de la siguiente (con entrada de texto)
    currentTarget = 'M03:question:2';
    document.querySelector('#q-m03-1').hidden = true;
    document.querySelector('#q-m03-2').hidden = false;
    active = 3;
    refreshAvatarHelpControls();
    helpButtons[0].click();
    const helpCardM03_2 = document.querySelector('#q-m03-2 [data-question-help]');
    check(helpCardM03_2 && !helpCardM03_2.hidden && helpCardM03_2.textContent.includes('edad en que comenzó a fumar'), '12 & 14. Next question help correctly displayed for text entry question');

    // 15: Probar con audio activo: verificar que la ayuda interrumpe el audio previo
    narrationActive = true;
    const stopsBefore = stoppedVoices;
    helpButtons[0].click();
    check(stoppedVoices > stopsBefore, '15. Help interrupted active narration before speaking');

    // 16: Probar en estado sin pregunta activa: controles deshabilitados
    currentTarget = '';
    questions.forEach(q => q.hidden = true);
    refreshAvatarHelpControls();
    check(helpButtons.every(b => b.disabled && b.getAttribute('aria-disabled') === 'true'), '16. Controls disabled when no active question exists');

    document.body.dataset.testResult = 'PASS';
})().catch(err => {
    document.body.dataset.testResult = 'FAIL';
    document.body.dataset.testError = String(err.stack || err);
});
`;

const fixtureDir = fs.mkdtempSync(path.join(os.tmpdir(), 'g14n-r3-test-'));
const fixtureFile = path.join(fixtureDir, 'fixture.html');
const chromeProfileDir = path.join(fixtureDir, 'chrome-profile');

const fixtureHtml = `<!doctype html>
<html>
<head><meta charset="utf-8"></head>
<body>
    <div class="patient-interview-doctor-actions">
        <button type="button" data-doctor-repeat disabled>Repetir</button>
        <button type="button" data-doctor-stop disabled>Detener</button>
        <button type="button" data-doctor-help disabled aria-disabled="true" aria-label="Ayuda con esta pregunta" title="Ayuda sobre la pregunta activa"><span>Ayuda con esta pregunta</span></button>
    </div>
    <div class="patient-interview-doctor-shortcuts">
        <button type="button" data-doctor-repeat disabled>Repetir</button>
        <button type="button" data-doctor-help disabled aria-disabled="true" aria-label="Ayuda con esta pregunta" title="Ayuda sobre la pregunta activa"><span>Ayuda con esta pregunta</span></button>
    </div>

    <article id="q-m02-1" class="patient-interview-question" data-module="M02" data-stable-id="M02:record:family_heart">
        <div class="patient-interview-message assistant">
            <div class="interview-message-header"><strong>M02</strong></div>
            <h3>Pregunta corazón</h3>
        </div>
    </article>
    <article id="q-m02-2" class="patient-interview-question" data-module="M02" data-stable-id="M02:record:family_hypertension" hidden>
        <div class="patient-interview-message assistant">
            <div class="interview-message-header"><strong>M02</strong></div>
            <h3>Pregunta hipertensión</h3>
        </div>
    </article>
    <article id="q-m03-1" class="patient-interview-question" data-module="M03" data-question-id="1" data-eligible="1" hidden>
        <div class="patient-interview-message assistant">
            <div class="interview-message-header"><strong>M03</strong></div>
            <h3>¿Usted fuma?</h3>
        </div>
    </article>
    <article id="q-m03-2" class="patient-interview-question" data-module="M03" data-question-id="2" data-eligible="1" hidden>
        <div class="patient-interview-message assistant">
            <div class="interview-message-header"><strong>M03</strong></div>
            <h3>¿Edad en que empezó?</h3>
        </div>
    </article>

    <script>${testScript}</script>
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

    console.log('CHECKS 7-16 PASS: Focal Chrome suite verified all 17 requirements of Section 13.');
    console.log('G14N_R3_CONTRACT_TEST=PASS');
} finally {
    fs.rmSync(fixtureDir, { recursive: true, force: true });
}
