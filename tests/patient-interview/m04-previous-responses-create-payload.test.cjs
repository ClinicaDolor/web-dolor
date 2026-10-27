'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..', '..');
const view = fs.readFileSync(path.join(root, 'app/views/paciente/respuestas-previas.php'), 'utf8');
for (const scriptMatch of view.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)) {
    if (/\bsrc\s*=/.test(scriptMatch[0].slice(0, scriptMatch[0].indexOf('>'))) || scriptMatch[1].trim() === '') continue;
    const executable = scriptMatch[1].replace(/<\?=[\s\S]*?\?>/g, 'null').replace(/<\?php[\s\S]*?\?>/g, '');
    assert.doesNotThrow(() => new vm.Script(executable), 'The previous-responses inline JavaScript must parse after PHP placeholders are rendered.');
}
const createStart = view.indexOf('    const createDialog = root.querySelector');
const createHandler = createStart >= 0 ? view.slice(createStart) : '';
assert.ok(createHandler, 'M04 create modal handler must exist.');

const payloadMatch = createHandler.match(/body:\s*JSON\.stringify\(\{([\s\S]*?)\}\),\s*\}\);/);
assert.ok(payloadMatch, 'Create POST must build its JSON body inside the modal submit handler.');
const buildPayload = new Function('root', 'surgery', 'observations', `return ({${payloadMatch[1]}});`);
const payload = buildPayload({dataset: {m04Version: 'a'.repeat(64)}}, 'Apendicectomía', 'Observación sintética');
assert.deepEqual(payload, {
    expected_version: 'a'.repeat(64),
    cirugia: 'Apendicectomía',
    fecha: null,
    observaciones: 'Observación sintética',
}, 'Create POST must map the declared observations variable and explicitly leave date NULL.');

const editStart = view.indexOf('    const bindM04ReviewCard = card =>');
const editEnd = view.indexOf("    document.querySelectorAll('[data-m04-review-card]", editStart);
const editHandler = editStart >= 0 && editEnd > editStart ? view.slice(editStart, editEnd) : '';
const editPayloadMatch = editHandler.match(/body:\s*JSON\.stringify\(\{([\s\S]*?)\}\),\s*\}\);/);
assert.ok(editPayloadMatch, 'Existing surgery edit must retain its JSON POST body.');
const buildEditPayload = new Function('card', 'surgery', 'observations', `return ({${editPayloadMatch[1]}});`);
const editPayload = buildEditPayload({dataset: {stableId: 'M04:record:7', recordId: '7', version: 'b'.repeat(64)}}, 'Vesícula', 'Edición sintética');
assert.equal(editPayload.observaciones, 'Edición sintética', 'Existing edit must map the declared observations variable.');
assert.equal(Object.hasOwn(editPayload, 'fecha'), false, 'Edit POST must not depend on the removed date input.');

console.log('M04 previous-responses payload: PASS (create payload keeps fecha null; edit payload omits fecha and maps name/observations).');
