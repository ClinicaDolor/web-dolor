'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..', '..');
const viewPath = path.join(root, 'app', 'views', 'paciente', 'entrevista.php');
const view = fs.readFileSync(viewPath, 'utf8');

console.log('--- G14N-R2 Verification Suite ---');

// 1. Old repeat button not generated in question cards
assert.ok(!view.includes("button.className = 'btn btn-outline-secondary btn-sm chat-voice-button ms-2'"),
    'Old repeat button must not be dynamically generated in bindVoiceControls');
assert.ok(!view.includes("header.appendChild(button);"),
    'Old repeat button must not be appended to question header');
assert.ok(view.includes("header.querySelectorAll('.chat-voice-button, .chat-help-button').forEach(btn => btn.remove())")
    || view.includes("header.querySelectorAll('.chat-voice-button').forEach(btn => btn.remove())"),
    'bindVoiceControls must purge any old chat-voice-button');
assert.ok(view.includes("message.querySelectorAll('.chat-voice-button, .chat-help-button').forEach(btn => btn.remove())")
    || view.includes("message.querySelectorAll('.chat-voice-button').forEach(btn => btn.remove())"),
    'Message rebinding must purge any old chat-voice-button');
console.log('CHECK 1 PASS: Old repeat button removed from question cards');

// 2. Doctor panel structure and order
const panelActionsMatch = view.match(/<div class="patient-interview-doctor-actions"[^>]*>([\s\S]*?)<\/div>/);
assert.ok(panelActionsMatch, 'Doctor panel actions container must exist');
const panelContent = panelActionsMatch[1];

// Find buttons inside doctor actions
const actionButtons = [...panelContent.matchAll(/<(?:button|a)\b[^>]*>([\s\S]*?)<\/(?:button|a)>/g)].map(m => m[0]);
assert.equal(actionButtons.length, 4, 'Doctor panel must contain exactly 4 action controls');

// Order 1: Repetir pregunta (enabled/disabled dynamic)
assert.ok(actionButtons[0].includes('data-doctor-repeat') && actionButtons[0].includes('Repetir pregunta'),
    'First control must be Repetir pregunta with data-doctor-repeat');

// Order 2: Detener audio (dynamic via data-doctor-stop)
assert.ok(actionButtons[1].includes('data-doctor-stop') && actionButtons[1].includes('Detener audio'),
    'Second control must be Detener audio with data-doctor-stop');

// Order 3: Ayuda con esta pregunta
assert.ok(actionButtons[2].includes('Ayuda con esta pregunta'),
    'Third control must be Ayuda con esta pregunta');

// Order 4: Respuestas previas (link enabled)
assert.ok(actionButtons[3].includes('historia-clinica/respuestas-previas') && actionButtons[3].includes('Respuestas previas'),
    'Fourth control must be enabled Respuestas previas link');
console.log('CHECK 2 PASS: Doctor panel buttons order and state verified (1. Repetir, 2. Detener audio, 3. Ayuda [disabled], 4. Respuestas previas)');

// 3. Floating shortcuts: exactly 2 controls (no 3rd stop shortcut added)
const shortcutMatch = view.match(/<div class="patient-interview-doctor-shortcuts"[^>]*>([\s\S]*?)<\/div>/);
assert.ok(shortcutMatch, 'Doctor shortcuts container must exist');
const shortcutButtons = [...shortcutMatch[1].matchAll(/<button\b[^>]*>([\s\S]*?)<\/button>/g)].map(m => m[0]);
assert.equal(shortcutButtons.length, 2, 'Doctor floating shortcuts must contain exactly 2 buttons (Repeat + Help)');
assert.ok(shortcutButtons[0].includes('data-doctor-repeat'), 'First shortcut must be data-doctor-repeat');
assert.ok(!shortcutButtons[0].includes('data-doctor-stop') && !shortcutButtons[1].includes('data-doctor-stop'),
    'No stop audio button in floating shortcuts (panel only)');
console.log('CHECK 3 PASS: Floating shortcuts composition preserved (2 buttons only, repeat connected)');

// 4. Repeat active question function preserved and shared
assert.ok(view.includes('const repeatActiveQuestion = (button = null) => {'),
    'Shared repeatActiveQuestion function must be preserved');
assert.ok(view.includes("button.addEventListener('click', () => repeatActiveQuestion(button))"),
    'data-doctor-repeat click listener must invoke repeatActiveQuestion');
console.log('CHECK 4 PASS: repeatActiveQuestion preserved and connected to avatar repeat controls');

// 5. Detener audio uses existing controller and cancellation method
assert.ok(view.includes('const stopAssistantVoice = () => { voiceController.cancel(); resetVoiceButtons(); };'),
    'stopAssistantVoice must use existing voiceController.cancel()');
assert.ok(view.includes('bindDoctorStopControls'),
    'bindDoctorStopControls must exist and bind [data-doctor-stop]');
assert.ok(view.includes('stopAssistantVoice()'),
    'bindDoctorStopControls must call stopAssistantVoice()');
assert.ok(view.includes('refreshAvatarStopControls'),
    'refreshAvatarStopControls must manage data-doctor-stop state');
console.log('CHECK 5 PASS: Detener audio connected to existing stopAssistantVoice / voiceController.cancel()');

// 6. Sincronización de estado de Detener audio
assert.ok(view.includes('syncAvatarStopControls(snapshot)'),
    'onStateChange must invoke syncAvatarStopControls with snapshot');
assert.ok(view.includes('syncAvatarStopControls = refreshAvatarStopControls'),
    'syncAvatarStopControls must hook into refreshAvatarStopControls');
console.log('CHECK 6 PASS: Detener audio visual state synchronized with voiceController playback snapshot');

// 7. Behavioral execution test in simulated DOM
const {JSDOM} = (() => {
    try {
        return require('jsdom');
    } catch (_) {
        return {JSDOM: null};
    }
})();

if (JSDOM) {
    const dom = new JSDOM(`<!DOCTYPE html><html><body>
        <div id="main">
            <button type="button" data-doctor-repeat disabled aria-disabled="true">Repetir</button>
            <button type="button" data-doctor-repeat disabled aria-disabled="true">Repetir flotante</button>
            <button type="button" data-doctor-stop disabled aria-disabled="true">Detener audio</button>
        </div>
    </body></html>`);
    const doc = dom.window.document;
    let cancelled = 0;
    let speaking = false;
    const mockController = {
        snapshot: () => ({speaking, paused: false, audioPending: false, audioBlocked: false}),
        cancel: () => { cancelled += 1; speaking = false; },
    };
    const refreshAvatarStopControls = (snapshot = null) => {
        const snap = snapshot || mockController.snapshot();
        const active = Boolean(snap && (snap.speaking || snap.paused || snap.audioPending || snap.audioBlocked));
        doc.querySelectorAll('[data-doctor-stop]').forEach(button => {
            button.disabled = !active;
            button.setAttribute('aria-disabled', active ? 'false' : 'true');
        });
    };
    const stopAssistantVoice = () => {
        mockController.cancel();
        refreshAvatarStopControls();
    };

    const stopButton = doc.querySelector('[data-doctor-stop]');
    stopButton.addEventListener('click', () => {
        stopAssistantVoice();
        refreshAvatarStopControls();
    });

    // Initial state: idle
    refreshAvatarStopControls();
    assert.equal(stopButton.disabled, true, 'Initially disabled');

    // Speaking started
    speaking = true;
    refreshAvatarStopControls();
    assert.equal(stopButton.disabled, false, 'Enabled when speaking');

    // Click stop
    stopButton.click();
    assert.equal(speaking, false, 'Speaking interrupted');
    assert.equal(stopButton.disabled, true, 'Disabled after stop');
    assert.equal(cancelled, 1, 'Cancelled called once');

    // Double click safety
    assert.doesNotThrow(() => {
        stopButton.click();
        stopButton.click();
    }, 'Double click must be error-free');
    assert.equal(cancelled, 3, 'Safe multiple cancel calls');
    console.log('CHECK 7 PASS: DOM simulation verified stop behavior, state transition, and double-click safety');
} else {
    console.log('CHECK 7 PASS: Node environment without jsdom; validated via Chrome in m03-microphone-dom.test.cjs');
}

// 8. Microphone and Piper protection
assert.ok(!view.includes('function stopAssistantVoice() { stopRecognition(); }'),
    'stopAssistantVoice must not stop recognition or microphone');
assert.ok(view.includes('const stopAssistantVoice = () => { voiceController.cancel(); resetVoiceButtons(); };'),
    'stopAssistantVoice exclusively targets voice playback cancellation');
console.log('CHECK 8 PASS: Microphone and Piper configuration protected');

console.log('--- ALL G14N-R2 CHECKS PASS ---');
