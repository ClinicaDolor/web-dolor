'use strict';

const assert = require('node:assert/strict');
const autocomplete = require('../../public/assets/js/patient-previous-responses-surgery-autocomplete.js');

class FakeEvent {
    constructor(type, options = {}) {
        this.type = type;
        this.bubbles = Boolean(options.bubbles);
        this.defaultPrevented = false;
        this.propagationStopped = false;
    }
    preventDefault() { this.defaultPrevented = true; }
    stopPropagation() { this.propagationStopped = true; }
}

class FakeNode {
    constructor(tagName = 'div') {
        this.tagName = tagName.toUpperCase();
        this.attributes = new Map();
        this.dataset = {};
        this.children = [];
        this.listeners = new Map();
        this.hidden = false;
        this.value = '';
        this.parentElement = null;
        this.ownerDocument = null;
    }
    setAttribute(name, value) {
        this.attributes.set(name, String(value));
        if (name === 'id') this.id = String(value);
    }
    getAttribute(name) { return this.attributes.has(name) ? this.attributes.get(name) : null; }
    removeAttribute(name) { this.attributes.delete(name); }
    addEventListener(name, callback) {
        if (!this.listeners.has(name)) this.listeners.set(name, []);
        this.listeners.get(name).push(callback);
    }
    dispatchEvent(event) {
        if (!event.target) event.target = this;
        for (const callback of this.listeners.get(event.type) || []) callback(event);
        return !event.defaultPrevented;
    }
    appendChild(child) { child.parentElement = this; child.ownerDocument = this.ownerDocument; this.children.push(child); return child; }
    replaceChildren(...children) { this.children = []; children.forEach(child => this.appendChild(child)); }
    querySelectorAll(selector) { return selector === '[role="option"]' ? this.children.filter(child => child.getAttribute('role') === 'option') : []; }
    closest(selector) {
        if (selector === '[role="option"]') return this.getAttribute('role') === 'option' ? this : this.parentElement?.closest(selector) || null;
        return null;
    }
    contains(node) { return this === node || this.children.some(child => child.contains(node)); }
    scrollIntoView() {}
}

class FakeDocument {
    constructor() {
        this.listeners = new Map();
        this.input = new FakeNode('input');
        this.list = new FakeNode('div');
        this.dialog = new FakeNode('dialog');
        this.defaultView = {Event: FakeEvent};
        [this.input, this.list, this.dialog].forEach(node => { node.ownerDocument = this; });
        this.input.closest = selector => selector === 'dialog' ? this.dialog : null;
        this.dialog.appendChild(this.input);
        this.dialog.appendChild(this.list);
    }
    querySelector(selector) {
        if (selector === '[data-m04-create-name]') return this.input;
        if (selector === '[data-m04-create-suggestions]') return this.list;
        return null;
    }
    createElement(tagName) { const node = new FakeNode(tagName); node.ownerDocument = this; return node; }
    addEventListener(name, callback) {
        if (!this.listeners.has(name)) this.listeners.set(name, []);
        this.listeners.get(name).push(callback);
    }
}

assert.equal(autocomplete.normalize('  APÉNDICE  '), 'apendice');
assert.equal(autocomplete.findSuggestions('apend')[0]?.name, 'Apendicectomía', 'Accents and case do not prevent a partial match.');
assert.equal(autocomplete.findSuggestions('VESIC')[0]?.name, 'Colecistectomía', 'Existing M04 catalog details remain searchable.');
assert.equal(autocomplete.findSuggestions('rodilla').length, 2, 'Related knee procedures are suggested.');
assert.equal(autocomplete.findSuggestions('x').length, 0, 'One character is not enough to open suggestions.');
assert.equal(autocomplete.findSuggestions('procedimiento no catalogado').length, 0, 'Free text is not forcibly replaced.');

const doc = new FakeDocument();
assert.equal(autocomplete.initialize(doc), true);
assert.equal(autocomplete.initialize(doc), false, 'Initialization is idempotent.');
assert.equal(doc.input.getAttribute('role'), 'combobox');
assert.equal(doc.input.getAttribute('aria-controls'), 'm04-create-surgery-suggestions');

doc.input.value = 'APEND';
doc.input.dispatchEvent(new FakeEvent('input'));
assert.equal(doc.list.hidden, false, 'Typing shows the matching suggestion.');
assert.equal(doc.list.querySelectorAll('[role="option"]').length, 1);
const down = new FakeEvent('keydown');
down.key = 'ArrowDown';
doc.input.dispatchEvent(down);
assert.equal(down.defaultPrevented, true, 'ArrowDown moves the active option without submitting.');
assert.equal(doc.input.getAttribute('aria-activedescendant'), 'm04-create-surgery-suggestions-option-1');
const enter = new FakeEvent('keydown');
enter.key = 'Enter';
doc.input.dispatchEvent(enter);
assert.equal(enter.defaultPrevented, true, 'Enter selects the active suggestion instead of submitting the form.');
assert.equal(doc.input.value, 'Apendicectomía');
assert.equal(doc.list.hidden, true);
assert.equal(doc.input.getAttribute('aria-expanded'), 'false');

doc.input.value = 'rodilla';
doc.input.dispatchEvent(new FakeEvent('input'));
const mouseOption = doc.list.querySelectorAll('[role="option"]')[1];
const pointerDown = new FakeEvent('pointerdown');
pointerDown.target = mouseOption;
doc.list.dispatchEvent(pointerDown);
assert.equal(pointerDown.defaultPrevented, true, 'Pointer selection keeps focus on the input until click.');
const click = new FakeEvent('click');
click.target = mouseOption;
doc.list.dispatchEvent(click);
assert.equal(doc.input.value, 'Reemplazo de rodilla', 'Mouse selection completes the input value.');
assert.equal(doc.list.hidden, true);

doc.input.value = 'Cirugía ambulatoria no listada';
doc.input.dispatchEvent(new FakeEvent('input'));
assert.equal(doc.input.value, 'Cirugía ambulatoria no listada', 'Free text remains unchanged.');
assert.equal(doc.list.hidden, true);

doc.input.value = 'apend';
doc.input.dispatchEvent(new FakeEvent('input'));
const escape = new FakeEvent('keydown');
escape.key = 'Escape';
doc.input.dispatchEvent(escape);
assert.equal(escape.defaultPrevented, true);
assert.equal(escape.propagationStopped, true, 'Escape closes suggestions without closing the dialog.');
assert.equal(doc.list.hidden, true);

console.log('M04 previous-responses autocomplete: PASS (accent-insensitive suggestions, ARIA, keyboard/mouse selection, Escape, free text, no submit on selection).');
