const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '../..');
const viewSource = fs.readFileSync(path.join(root, 'app/views/paciente/entrevista.php'), 'utf8');

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

function extract(pattern, label) {
    const match = viewSource.match(pattern);
    if (!match) throw new Error(`No se pudo extraer ${label} del renderizador M06.`);
    return match[0];
}

class FakeButton {
    constructor(dataset = {}) {
        this.dataset = {...dataset};
        this.hidden = false;
        this.textContent = '';
    }
}

function createCard(state, pendingRecord = '0', context = '{}') {
    const composer = {hidden: false};
    const startInitial = new FakeButton({m06Action: 'START_MEDICATION', m06Stage: 'initial'});
    const startAfterSave = new FakeButton({m06Action: 'START_MEDICATION', m06Stage: 'after-save'});
    const skipUnknown = new FakeButton({});
    const skipOmit = new FakeButton({});
    const finish = new FakeButton({m06Action: 'FINISH_ADDITIONAL_MEDICATIONS'});
    const quickActions = {
        querySelector(selector) {
            if (selector.includes('FINISH_')) return finish;
            return null;
        },
        querySelectorAll(selector) {
            if (selector.includes('START_MEDICATION')) return [startInitial, startAfterSave];
            if (selector.includes('data-m06-skip')) return [skipUnknown, skipOmit];
            return [];
        },
    };
    const card = {
        dataset: {m06State: state, m06PendingRecord: pendingRecord, m06Context: context, m06Skipped: '[]'},
        querySelector(selector) {
            if (selector === '[data-m06-composer]') return composer;
            if (selector === '[data-m06-quick-actions]') return quickActions;
            if (selector.includes('data-m06-quick') && selector.includes('FINISH_')) return finish;
            return null;
        },
    };
    return {card, composer, startInitial, startAfterSave, skipUnknown, skipOmit, finish};
}

const extracted = [
    extract(/const m06Fields = \[[^;]+;/, 'campos M06'),
    extract(/const m06ContextFrom = card => \{[\s\S]*?\n    \};/, 'contexto M06'),
    extract(/const m06SkippedFrom = card => \{[\s\S]*?\n    \};/, 'omisiones M06'),
    extract(/const m06CurrentField = card => \{[\s\S]*?\n    \};/, 'campo activo M06'),
    extract(/const m06NextField = \(context, skipped = \[\]\) =>[\s\S]*?;\r?\n/, 'siguiente campo M06'),
    extract(/const syncM06QuickActions = \(card, state\) => \{[\s\S]*?\n    \};/, 'acciones contextuales M06'),
].join('\n');

const context = {};
vm.runInNewContext(`${extracted}\nglobalThis.render = syncM06QuickActions;`, context);

const afterSave = createCard('after-save');
context.render(afterSave.card, 'after-save');
assert(afterSave.composer.hidden === true, 'ADD_ANOTHER debe ocultar el compositor.');
assert(afterSave.startAfterSave.hidden === false && afterSave.startInitial.hidden === true, 'ADD_ANOTHER debe mostrar solo el inicio del siguiente medicamento.');
assert(afterSave.finish.hidden === false && afterSave.skipUnknown.hidden === true && afterSave.skipOmit.hidden === true, 'ADD_ANOTHER debe ocultar omisiones y mostrar el cierre.');

const capturing = createCard('capturing', '93001', JSON.stringify({medicamento: 'Paracetamol'}));
context.render(capturing.card, 'capturing');
assert(capturing.composer.hidden === false, 'CAPTURING debe mostrar el compositor.');
assert(capturing.startAfterSave.hidden === true && capturing.finish.hidden === true, 'CAPTURING no debe mostrar acciones de ADD_ANOTHER.');
assert(capturing.skipUnknown.hidden === false && capturing.skipOmit.hidden === false, 'CAPTURING debe mostrar únicamente las omisiones del campo activo.');
assert(viewSource.includes('.m06-quick-actions { margin-top:1.25rem; gap:.85rem 1rem;'), 'ADD_ANOTHER debe conservar separación vertical y horizontal propia.');
assert(viewSource.includes('@media (max-width:600px) { .m06-quick-actions'), 'ADD_ANOTHER debe replegarse sin overflow en móvil.');

console.log('M06_CONTEXTUAL_BUTTONS_TRANSITIONS: PASS');
console.log('ADD_ANOTHER_VISIBLE_ACTIONS: 2');
console.log('NEW_MEDICATION_NO_CROSS_RECORD_CONTROLS: PASS');
console.log('REAL_CHROME: NOT_EXECUTED');
