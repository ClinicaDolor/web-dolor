import fs from 'node:fs';

const view = fs.readFileSync('app/views/historia-clinica-paciente/medicacion-control-dolor.php', 'utf8');
const required = [
  'lang = voice ? voice.lang : \'es-MX\'',
  'utterance.onstart',
  'utterance.onend',
  'utterance.onerror',
  'window.speechSynthesis.cancel()',
  'p09ReadInstructions()',
  'p09ReadCurrentQuestion()'
];
for (const marker of required) if (!view.includes(marker)) throw new Error(`P09 audio mock: falta ${marker}`);
console.log('P09_AUDIO_MOCK: PASS (contrato de voz; reproducción audible real NO VERIFICADA)');
