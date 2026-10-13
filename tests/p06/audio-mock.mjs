import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync('app/views/historia-clinica-paciente/antecedentes-personales-quirurgicos.php', 'utf8')
  .match(/<script>\s*([\s\S]*?)\s*<\/script>/)?.[1];
if (!source) throw new Error('No se encontró el script inline de P06.');

const calls = { speak: [], cancel: 0 };
const speechSynthesis = {
  speak(utterance) { calls.speak.push(utterance.text); },
  cancel() { calls.cancel += 1; },
};
const instructions = { textContent: 'Instrucciones sintéticas de cirugía.' };
const context = {
  console,
  SpeechSynthesisUtterance: class { constructor(text) { this.text = text; } },
  window: { speechSynthesis, addEventListener() {} },
  document: {
    addEventListener() {},
    querySelectorAll() { return []; },
    getElementById(id) { return id === 'p06InstructionsText' ? instructions : null; },
  },
  localStorage: { getItem() { return null; }, setItem() {} },
  fetch() { throw new Error('fetch no debe ejecutarse en el mock de voz.'); },
};
vm.createContext(context);
vm.runInContext(`${source}\n;globalThis.__p06 = { p06Speak, p06ReadInstructions, p06StopVoice };`, context);

if (!context.__p06.p06ReadInstructions() || calls.speak.length !== 1) {
  throw new Error('P06 no construyó la narración de instrucciones.');
}
context.__p06.p06Speak('Pregunta sintética de cirugía.');
if (calls.speak.length !== 2 || calls.cancel < 1) {
  throw new Error('P06 no canceló la narración anterior antes de iniciar otra.');
}
context.__p06.p06StopVoice();
if (calls.cancel < 2) throw new Error('P06 no expuso detención de voz.');

console.log(JSON.stringify({ ok: true, speakCalls: calls.speak.length, cancelCalls: calls.cancel, audiblePlayback: false }));
