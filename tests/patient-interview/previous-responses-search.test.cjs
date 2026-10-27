const assert = require('node:assert/strict');
const filters = require('../../public/assets/js/patient-previous-responses-filters.js');

let activeObserver = null;
class BoundedMutationObserver {
    constructor(callback) {
        this.callback = callback;
        this.records = [];
        this.targets = new Map();
        activeObserver = this;
    }
    observe(target, options) { this.targets.set(target, options); }
    record(target, attributeName) {
        const options = this.targets.get(target);
        if (options && options.attributeFilter.includes(attributeName)) {
            this.records.push({type: 'attributes', attributeName, target});
        }
    }
    flush(maxDeliveries = 100) {
        let deliveries = 0;
        while (this.records.length > 0) {
            if (++deliveries > maxDeliveries) throw new Error('MUTATION_OBSERVER_DID_NOT_QUIESCE');
            this.callback(this.records.splice(0), this);
        }
        return deliveries;
    }
}
global.MutationObserver = BoundedMutationObserver;

class FakeNode {
    constructor(dataset = {}) {
        this.dataset = dataset;
        this._hidden = false;
        this.disabled = false;
        this.value = '';
        this.checked = false;
        this.textContent = '';
        this.listeners = {};
        this.children = [];
    }
    get hidden() { return this._hidden; }
    set hidden(value) {
        this._hidden = Boolean(value);
        if (activeObserver) activeObserver.record(this, 'hidden');
    }
    addEventListener(type, callback) { this.listeners[type] = callback; }
    appendChild(node) {
        this.children = this.children.filter(child => child !== node);
        this.children.push(node);
        return node;
    }
    dispatch(type) { if (this.listeners[type]) this.listeners[type]({target: this}); }
    focus() { this.focused = true; }
    querySelectorAll(selector) {
        if (selector === '[data-review-card]') return this.children;
        return [];
    }
}

const card = (moduleId, moduleName, theme, question, number, id, status, pending) => new FakeNode({
    reviewModuleId: moduleId,
    reviewModuleName: moduleName,
    reviewTheme: theme,
    reviewQuestion: question,
    reviewQuestionNumber: String(number),
    reviewQuestionId: id,
    reviewStatus: status,
    reviewPending: pending ? '1' : '0'
});

const m02Pending = card('M02', 'Antecedentes heredofamiliares', 'Antecedentes heredofamiliares', '¿Algún familiar tiene diabetes mellitus?', 4, 'M02:family:diabetes', 'PENDIENTE', true);
const m03Pending = card('M03', 'Antecedentes personales no patológicos', 'Tabaquismo', '¿Desde qué edad empezó a fumar?', 2, 'M03:question:2', 'PENDIENTE', true);
const m03Unknown = card('M03', 'Antecedentes personales no patológicos', 'Alcoholismo', '¿Cuánto tiempo lleva consumiendo bebidas alcohólicas?', 10, 'M03:question:10', 'POR_ACLARAR', true);
const m03NotApplicable = card('M03', 'Antecedentes personales no patológicos', 'Tabaquismo', '¿Cuántos cigarros consume?', 3, 'M03:question:3', 'NO_APLICA', false);
const m02Recorded = card('M02', 'Antecedentes heredofamiliares', 'Antecedentes heredofamiliares', '¿Algún familiar tiene cáncer?', 5, 'M02:family:cancer', 'REGISTRADA', false);
const m03TobaccoRecorded = card('M03', 'Antecedentes personales no patológicos', 'Tabaquismo', '¿Usted fuma cigarros de nicotina?', 1, 'M03:question:1', 'REGISTRADA', false);
m03TobaccoRecorded.editButton = new FakeNode();
const m03TobaccoFourth = card('M03', 'Antecedentes personales no patológicos', 'Tabaquismo', '¿Cuántos cigarrillos consume?', 4, 'M03:question:4', 'PENDIENTE', true);
const m03TobaccoFifth = card('M03', 'Antecedentes personales no patológicos', 'Tabaquismo', 'Si ya dejó de fumar, ¿hace cuánto tiempo lo suspendió?', 5, 'M03:question:5', 'REGISTRADA', false);
m03TobaccoFifth.editButton = new FakeNode();
const m04RecordedSurgery = card('M04', 'Antecedentes quirúrgicos', 'Antecedentes quirúrgicos', 'Vesícula', 1, 'M04:record:1', 'REGISTRADA', false);
const m03Exercise = card('M03', 'Antecedentes personales no patológicos', 'Ejercicio', '¿Realiza actividad física o practica deporte?', 12, 'M03:question:12', 'PENDIENTE', true);
const m03Stress = card('M03', 'Antecedentes personales no patológicos', 'Estres', 'Pregunta sintética de estrés', 16, 'M03:question:16', 'PENDIENTE', true);
const m03Drugs = card('M03', 'Antecedentes personales no patológicos', 'Drogas', 'Pregunta sintética de drogas', 18, 'M03:question:18', 'PENDIENTE', true);
const m03Sleep = card('M03', 'Antecedentes personales no patológicos', 'Dormir', '¿Cuántas horas duerme para descansar?', 21, 'M03:question:21', 'PENDIENTE', true);
const m03BloodType = card('M03', 'Antecedentes personales no patológicos', 'Grupo Sanguineo', 'Pregunta sintética de grupo sanguíneo', 27, 'M03:question:27', 'PENDIENTE', true);
const m03Cards = [m03TobaccoRecorded, m03Pending, m03NotApplicable, m03TobaccoFourth, m03TobaccoFifth, m03Unknown, m03Exercise, m03Stress, m03Drugs, m03Sleep, m03BloodType];
let cards = [m02Pending, ...m03Cards, m02Recorded];

const m03ThemeCases = [
    ['Tabaquismo', 'TABAQUÍSMO'],
    ['Alcoholismo', 'alcoholismo'],
    ['Ejercicio', 'EJERCICIO'],
    ['Estres', 'ESTRÉS'],
    ['Drogas', 'drogas'],
    ['Dormir', 'dormir'],
    ['Grupo Sanguineo', 'Grupo Sanguíneo']
];
m03ThemeCases.forEach(([theme, query]) => {
    const themeCard = card('M03', 'Antecedentes personales no patológicos', theme, 'Pregunta sintética', 1, 'M03:theme:test', 'REGISTRADA', false);
    assert.deepEqual(filters.filterReviewCards([themeCard], {query, module: '', pendingOnly: false}), [themeCard], 'M03_THEME_SEARCH_FAILED: ' + theme);
});

assert.equal(filters.normalizeSearchText('ÁRBOL, Qué edad'), 'arbol, que edad');
assert.deepEqual(filters.filterReviewCards(cards, {query: 'desde que edad empezo', module: '', pendingOnly: false}), [m03Pending]);
assert.deepEqual(filters.filterReviewCards(cards, {query: 'M03:question:2', module: 'M03', pendingOnly: false}), [m03Pending, m03Sleep, m03BloodType]);
assert.deepEqual(filters.filterReviewCards(m03Cards, {query: 'fumar', module: '', pendingOnly: false}), [m03Pending, m03TobaccoFifth, m03TobaccoRecorded, m03NotApplicable, m03TobaccoFourth]);
assert.deepEqual(filters.filterReviewCards(m03Cards, {query: 'cigarros', module: '', pendingOnly: false}), [m03TobaccoRecorded, m03NotApplicable, m03Pending, m03TobaccoFourth, m03TobaccoFifth]);
assert.deepEqual(filters.filterReviewCards(m03Cards, {query: 'cigarro', module: '', pendingOnly: false}), [m03TobaccoRecorded, m03NotApplicable, m03Pending, m03TobaccoFourth, m03TobaccoFifth]);
assert.deepEqual(filters.filterReviewCards(m03Cards, {query: 'tabaco', module: '', pendingOnly: false}), [m03TobaccoRecorded, m03Pending, m03NotApplicable, m03TobaccoFourth, m03TobaccoFifth]);
assert.deepEqual(filters.filterReviewCards(m03Cards, {query: 'bebidas', module: '', pendingOnly: false}), [m03Unknown]);
assert.deepEqual(filters.filterReviewCards(m03Cards, {query: 'alcohol', module: '', pendingOnly: false}), [m03Unknown]);
assert.deepEqual(filters.filterReviewCards(m03Cards, {query: 'deporte', module: '', pendingOnly: false}), [m03Exercise]);
assert.deepEqual(filters.filterReviewCards(m03Cards, {query: 'actividad fisica', module: '', pendingOnly: false}), [m03Exercise]);
assert.deepEqual(filters.filterReviewCards(m03Cards, {query: 'descanso', module: '', pendingOnly: false}), [m03Sleep]);
assert.deepEqual(filters.filterReviewCards(m03Cards, {query: 'horas de descanso', module: '', pendingOnly: false}), [m03Sleep]);
// A literal duration phrase takes precedence over a merely related onset age.
assert.deepEqual(filters.filterReviewCards(m03Cards, {query: 'cuánto tiempo', module: '', pendingOnly: false}), [m03TobaccoFifth, m03Unknown]);
for (const query of ['Si ya dejó de fumar, ¿hace cuánto tiempo lo suspendió?', ' SI YA DEJO DE FUMAR HACE CUANTO TIEMPO LO SUSPENDIO ', 'dejó de fumar']) {
    assert.deepEqual(filters.filterReviewCards(cards, {query}), [m03TobaccoFifth], 'SPECIFIC_QUESTION_MUST_NOT_EXPAND: ' + query);
}
assert.deepEqual(filters.filterReviewCards(cards, {query: 'diabetes mellitus'}), [m02Pending]);
const longerQuestion = card('M03', 'M03', 'Tabaquismo', m03TobaccoFifth.dataset.reviewQuestion + ' Indique detalles.', 99, 'test:99', 'PENDIENTE', true);
assert.deepEqual(filters.filterReviewCards([...cards, longerQuestion], {query: m03TobaccoFifth.dataset.reviewQuestion}), [m03TobaccoFifth], 'EXACT_QUESTION_MUST_EXCLUDE_LONGER_PARTIAL_MATCHES');
for (const query of ['si', 'lo', 'de', 'hace', 'qué', 'tiempo']) {
    assert.deepEqual(filters.filterReviewCards(cards, {query}), [], 'GENERIC_WORD_MUST_NOT_MATCH: ' + query);
}
assert.deepEqual(filters.filterReviewCards(cards, {query: 'diabetes', module: '', pendingOnly: false}), [m02Pending]);
assert.ok(filters.searchRelevance(m03TobaccoRecorded, 'cigarros') > filters.searchRelevance(m03Pending, 'cigarros'), 'LITERAL_MATCHES_MUST_RANK_BEFORE_SEMANTIC_MATCHES');
assert.deepEqual(filters.filterReviewCards(cards, {query: '', module: 'M02', pendingOnly: false}), [m02Pending, m02Recorded]);
assert.deepEqual(filters.filterReviewCards(cards, {query: '', module: 'M03', pendingOnly: true}), [m03Pending, m03TobaccoFourth, m03Unknown, m03Exercise, m03Stress, m03Drugs, m03Sleep, m03BloodType]);
assert.deepEqual(filters.filterReviewCards([m04RecordedSurgery], {query: '', module: 'M04', pendingOnly: false}), [m04RecordedSurgery], 'M04_MODULE_FILTER_FAILED');
assert.equal(filters.countLabel(0), '0 resultados');
assert.equal(filters.countLabel(1), '1 resultado');

const input = new FakeNode();
const moduleFilter = new FakeNode();
const pendingFilter = new FakeNode();
const clear = new FakeNode();
const resultCount = new FakeNode();
const lockMessage = new FakeNode();
const panel = new FakeNode();
panel.querySelector = selector => ({
    '[data-review-search]': input,
    '[data-review-module-filter]': moduleFilter,
    '[data-review-pending-filter]': pendingFilter,
    '[data-review-clear]': clear,
    '[data-review-count]': resultCount,
    '[data-review-filter-lock]': lockMessage
}[selector] || null);
const noResults = new FakeNode();
const m02Section = new FakeNode();
m02Section.children = [m02Pending, m02Recorded];
const m02List = new FakeNode();
m02List.children = [m02Pending, m02Recorded];
const m03Section = new FakeNode();
m03Section.children = m03Cards;
const m04Section = new FakeNode();
const m04List = new FakeNode();
const themesByName = new Map(m03ThemeCases.map(([theme]) => [theme, new FakeNode()]));
themesByName.get('Tabaquismo').children = [m03TobaccoRecorded, m03Pending, m03NotApplicable, m03TobaccoFourth, m03TobaccoFifth];
themesByName.get('Alcoholismo').children = [m03Unknown];
themesByName.get('Ejercicio').children = [m03Exercise];
themesByName.get('Estres').children = [m03Stress];
themesByName.get('Drogas').children = [m03Drugs];
themesByName.get('Dormir').children = [m03Sleep];
themesByName.get('Grupo Sanguineo').children = [m03BloodType];
const tobaccoTheme = themesByName.get('Tabaquismo');
const alcoholTheme = themesByName.get('Alcoholismo');
const editor = new FakeNode();
editor.hidden = true;
const m04Editor = new FakeNode();
m04Editor.hidden = true;
const main = new FakeNode();
const doc = {
    body: main,
    querySelector(selector) {
        if (selector === '[data-review-filters]') return panel;
        if (selector === '[data-review-no-results]') return noResults;
        if (selector === 'main') return main;
        return null;
    },
    querySelectorAll(selector) {
        if (selector === '[data-review-card]') return cards;
        if (selector === '[data-review-module]') return [m02Section, m03Section, m04Section];
        // Real cards carry data-review-theme too, not only theme containers.
        if (selector === '[data-review-theme]') return [...Array.from(themesByName.values()), ...cards];
        if (selector === '.previous-responses-list') return [m02List, ...Array.from(themesByName.values()), m04List];
        if (selector === '[data-response-editor], [data-m03-response-editor], [data-m04-response-editor]') return [editor, m04Editor];
        return [];
    }
};
const filterController = filters.initialize(doc);
assert.ok(activeObserver, 'FILTER_EDITOR_OBSERVER_NOT_INSTALLED');
assert.doesNotThrow(() => activeObserver.flush(100), 'FILTER_MUTATION_OBSERVER_LOOP');
assert.equal(input.disabled, false);
m04Editor.hidden = false;
assert.doesNotThrow(() => activeObserver.flush(100), 'M04_FILTER_LOCK_OBSERVER_LOOP');
assert.equal(input.disabled, true, 'M04_EDITOR_MUST_LOCK_FILTERS');
assert.equal(lockMessage.hidden, false, 'M04_EDITOR_LOCK_MESSAGE_MUST_BE_VISIBLE');
m04Editor.hidden = true;
assert.doesNotThrow(() => activeObserver.flush(100), 'M04_FILTER_UNLOCK_OBSERVER_LOOP');
assert.equal(input.disabled, false, 'M04_EDITOR_CANCEL_MUST_UNLOCK_FILTERS');
assert.equal(resultCount.textContent, '13 resultados');
input.value = 'Si ya dejó de fumar, ¿hace cuánto tiempo lo suspendió?';
input.dispatch('input');
assert.deepEqual(cards.filter(item => !item.hidden), [m03TobaccoFifth]);
assert.equal(m02Section.hidden, true, 'EMPTY_M02_MUST_BE_HIDDEN');
assert.equal(tobaccoTheme.hidden, false);
assert.equal(alcoholTheme.hidden, true);
assert.ok(m03TobaccoFifth.editButton);
input.value = '';
input.dispatch('input');
assert.equal(cards.filter(item => !item.hidden).length, 13);
for (const [query, expected] of [
    ['SI YA DEJO DE FUMAR HACE CUANTO TIEMPO LO SUSPENDIO', [m03TobaccoFifth]],
    ['dejó de fumar', [m03TobaccoFifth]],
    ['cigarros', [m03TobaccoRecorded, m03Pending, m03NotApplicable, m03TobaccoFourth, m03TobaccoFifth]],
    ['diabetes mellitus', [m02Pending]],
    ['alcohol', [m03Unknown]],
    ['Ejercicio', [m03Exercise]],
    ['', cards]
]) {
    input.value = query;
    input.dispatch('input');
    assert.deepEqual(cards.filter(item => !item.hidden), expected, 'VISIBLE_CARD_SET_MUST_MATCH_QUERY: ' + query);
    assert.equal(m02Section.hidden, !expected.some(item => item.dataset.reviewModuleId === 'M02'));
    assert.equal(m03Section.hidden, !expected.some(item => item.dataset.reviewModuleId === 'M03'));
    for (const [name, theme] of themesByName) {
        assert.equal(theme.hidden, !expected.some(item => item.dataset.reviewModuleId === 'M03' && item.dataset.reviewTheme === name));
    }
}
moduleFilter.value = 'M02';
moduleFilter.dispatch('change');
pendingFilter.checked = true;
pendingFilter.dispatch('change');
assert.equal(resultCount.textContent, '1 resultado');
input.value = 'Tabaquismo';
input.dispatch('input');
assert.equal(resultCount.textContent, '5 resultados', 'THEME_SEARCH_MUST_NOT_INHERIT_PREVIOUS_MODULE_OR_STATUS_FILTERS');
assert.equal(moduleFilter.value, '');
assert.equal(pendingFilter.checked, false);
assert.equal(tobaccoTheme.hidden, false);
assert.equal(alcoholTheme.hidden, true);
assert.ok(Array.from(themesByName.entries()).every(([name, theme]) => name === 'Tabaquismo' ? !theme.hidden : theme.hidden), 'THEME_SEARCH_MUST_HIDE_ONLY_NONMATCHING_M03_HEADINGS');
assert.equal(m03Pending.hidden, false);
assert.equal(m03NotApplicable.hidden, false, 'THEME_SEARCH_MUST_KEEP_REAL_STATUS_CARDS_VISIBLE');
assert.equal(m03TobaccoRecorded.hidden, false);
assert.ok(m03TobaccoRecorded.editButton, 'THEME_SEARCH_MUST_PRESERVE_EDIT_ACTIONS');
input.value = '';
input.dispatch('input');
assert.equal(resultCount.textContent, '13 resultados', 'CLEARING_THEME_SEARCH_MUST_RESTORE_ALL_RESPONSES');
assert.equal(tobaccoTheme.hidden, false);
assert.equal(alcoholTheme.hidden, false);
assert.ok(Array.from(themesByName.values()).every(theme => !theme.hidden), 'CLEARING_THEME_SEARCH_MUST_RESTORE_ALL_M03_THEMES');
moduleFilter.value = 'M02';
moduleFilter.dispatch('change');
pendingFilter.checked = true;
pendingFilter.dispatch('change');
input.value = 'cigarros';
input.dispatch('input');
assert.equal(resultCount.textContent, '5 resultados', 'GLOBAL_SYNONYM_SEARCH_MUST_OVERRIDE_PREVIOUS_MANUAL_FILTERS');
assert.equal(moduleFilter.value, '');
assert.equal(pendingFilter.checked, false);
assert.deepEqual(tobaccoTheme.children, [m03TobaccoRecorded, m03NotApplicable, m03Pending, m03TobaccoFourth, m03TobaccoFifth], 'LITERAL_RESULTS_MUST_PRECEDE_SEMANTIC_RESULTS_IN_THE_EXISTING_LIST');
moduleFilter.value = 'M02';
moduleFilter.dispatch('change');
assert.equal(moduleFilter.value, '', 'GLOBAL_SEARCH_MUST_REMAIN_UNSCOPED_WHILE_QUERY_IS_NONEMPTY');
assert.equal(resultCount.textContent, '5 resultados');
input.value = '';
input.dispatch('input');
assert.equal(resultCount.textContent, '13 resultados');
assert.deepEqual(tobaccoTheme.children, [m03TobaccoRecorded, m03Pending, m03NotApplicable, m03TobaccoFourth, m03TobaccoFifth], 'CLEARING_SEARCH_MUST_RESTORE_CANONICAL_CARD_ORDER');
m03ThemeCases.forEach(([theme, query]) => {
    moduleFilter.value = 'M02';
    moduleFilter.dispatch('change');
    pendingFilter.checked = true;
    pendingFilter.dispatch('change');
    input.value = query;
    input.dispatch('input');
    const matchingTheme = themesByName.get(theme);
    assert.equal(resultCount.textContent, matchingTheme.children.length + ' resultado' + (matchingTheme.children.length === 1 ? '' : 's'), 'M03_THEME_UI_SEARCH_FAILED: ' + theme);
    assert.ok(Array.from(themesByName.entries()).every(([name, item]) => name === theme ? !item.hidden : item.hidden), 'M03_THEME_UI_HEADING_FILTER_FAILED: ' + theme);
    assert.ok(matchingTheme.children.every(item => !item.hidden), 'M03_THEME_UI_CARDS_FILTER_FAILED: ' + theme);
    input.value = '';
    input.dispatch('input');
    assert.equal(resultCount.textContent, '13 resultados', 'M03_THEME_CLEAR_FAILED: ' + theme);
});
moduleFilter.value = 'M03';
moduleFilter.dispatch('change');
assert.equal(resultCount.textContent, '11 resultados');
pendingFilter.checked = true;
pendingFilter.dispatch('change');
assert.equal(resultCount.textContent, '8 resultados');
assert.equal(m03NotApplicable.hidden, true);
input.value = 'qNoExiste';
input.dispatch('input');
assert.equal(resultCount.textContent, '0 resultados');
assert.equal(noResults.hidden, false);
clear.dispatch('click');
assert.equal(resultCount.textContent, '13 resultados');
assert.equal(noResults.hidden, true);

editor.hidden = false;
assert.doesNotThrow(() => activeObserver.flush(100), 'FILTER_MUTATION_OBSERVER_LOOP_WHEN_EDITING');
input.value = 'No debe aplicar';
input.dispatch('input');
assert.equal(input.value, '');
assert.equal(resultCount.textContent, '13 resultados');
assert.equal(input.disabled, true);
assert.equal(lockMessage.hidden, false);
editor.hidden = true;
assert.doesNotThrow(() => activeObserver.flush(100), 'FILTER_MUTATION_OBSERVER_LOOP_WHEN_CLOSING_EDITOR');
input.dispatch('input');
assert.equal(input.disabled, false);

const createdSurgery = card('M04', 'Antecedentes quirúrgicos', 'Antecedentes quirúrgicos', 'Apendicectomía', 2, 'M04:record:902', 'REGISTRADA', false);
cards.push(createdSurgery);
m04List.children.push(createdSurgery);
m04Section.children = m04List.children;
assert.equal(filterController.refreshCards(), 14, 'DYNAMIC_M04_CREATE_MUST_REFRESH_FILTER_CARD_INDEX');
input.value = 'Apendicectomía';
input.dispatch('input');
assert.deepEqual(cards.filter(item => !item.hidden), [createdSurgery], 'DYNAMIC_M04_SURGERY_MUST_BE_SEARCHABLE_IMMEDIATELY');
assert.equal(m04Section.hidden, false, 'DYNAMIC_M04_SECTION_MUST_REMAIN_VISIBLE_FOR_MATCH');
input.value = '';
input.dispatch('input');
assert.equal(cards.filter(item => !item.hidden).length, 14, 'CLEARING_SEARCH_MUST_RESTORE_NEW_M04_SURGERY');

console.log('Previous-responses search/filter contract: PASS (exact question exclusivity, punctuation/accents, specific phrases, synonym fallback, generic-word rejection, real theme/card attribute overlap, module hiding, seven themes, editor protection and observer quiescence).');
