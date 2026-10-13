import fs from 'node:fs';

const view = fs.readFileSync(
  'app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php',
  'utf8'
);

const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

// Este es un contrato mock: no afirma que el navegador haya reproducido audio.
assert(view.includes('function p05SpeakText'), 'Falta el punto de entrada de narración.');
assert(view.includes("new SpeechSynthesisUtterance(cleanText)"), 'No se crea la utterance con texto limpio.');
assert(view.includes("utterance.lang = voice ? voice.lang : 'es-MX'"), 'No se prioriza una voz española/México.');
assert(view.includes('utterance.onstart'), 'Falta estado de inicio de lectura.');
assert(view.includes('utterance.onend'), 'Falta estado de finalización de lectura.');
assert(view.includes('utterance.onerror'), 'Falta manejo seguro de error de voz.');
assert(view.includes('window.speechSynthesis.cancel()'), 'No se cancela audio anterior.');
assert(view.includes('p05ReadInstructions();'), 'La apertura de instrucciones no solicita narración automática.');
assert(view.includes('p05ReadQuestionWhenReady();'), 'El cambio de pregunta no contempla narración posterior.');
assert((view.match(/p05StopVoice\(\);/g) || []).length >= 4, 'La navegación no detiene lecturas previas.');

console.log('P05 audio mock contract: PASS (reproducción audible real no verificada)');
