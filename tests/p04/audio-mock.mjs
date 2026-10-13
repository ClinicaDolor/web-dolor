import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync('public/assets/js/voice-utilities.js', 'utf8');
const calls = { speak: [], cancel: 0 };
const textNode = { textContent: 'Pregunta sintética de prueba.', dataset: {} };
const section = {
  id: 'seccion-sintetica',
  getAttribute: () => 'false',
  setAttribute: () => {},
  querySelector: () => textNode,
};

const speechSynthesis = {
  speaking: false,
  pending: false,
  onvoiceschanged: null,
  getVoices: () => [{ lang: 'es-MX' }],
  speak(utterance) {
    calls.speak.push(utterance.text);
    this.speaking = true;
  },
  cancel() {
    calls.cancel += 1;
    this.speaking = false;
  },
};

class MockUtterance {
  constructor(text) {
    this.text = text;
  }
}

const context = {
  console,
  window: { speechSynthesis },
  speechSynthesis,
  SpeechSynthesisUtterance: MockUtterance,
  document: {
    readyState: 'complete',
    getElementById: (id) => id === section.id ? section : null,
    querySelector: (selector) => selector.includes('.sectionQuestion') ? section : null,
    querySelectorAll: () => [],
    addEventListener: () => {},
  },
  IntersectionObserver: undefined,
};

vm.createContext(context);
vm.runInContext(`${source}\n;globalThis.__voice = { leerTextoSeccion, detenerLectura };`, context);

const firstRequest = context.__voice.leerTextoSeccion(section.id);
if (!firstRequest || calls.speak.length !== 1 || calls.speak[0] !== textNode.textContent) {
  throw new Error('La narración mock no construyó el texto esperado.');
}

const secondRequest = context.__voice.leerTextoSeccion(section.id);
if (secondRequest !== false || calls.cancel !== 2) {
  throw new Error(`La narración mock no canceló la lectura anterior: ${JSON.stringify({ secondRequest, calls })}`);
}

context.__voice.detenerLectura();
if (calls.cancel !== 3 || calls.speak.length !== 1) {
  throw new Error('El mock permitió narraciones superpuestas.');
}

console.log(JSON.stringify({
  ok: true,
  constructedText: calls.speak[0],
  speakCalls: calls.speak.length,
  cancelCalls: calls.cancel,
  audiblePlayback: false,
}));

