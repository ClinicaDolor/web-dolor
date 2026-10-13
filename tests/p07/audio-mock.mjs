import fs from 'node:fs';

const view = fs.readFileSync(
  'app/views/historia-clinica-paciente/antecedentes-personales-patologicos.php',
  'utf8'
);

const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

// Contrato estático/mock: no afirma reproducción audible en un navegador real.
assert(view.includes('id="p07InstructionsModal"'), 'Falta el modal de instrucciones P07.');
assert(view.includes('id="p07HelpButton"'), 'Falta el botón de ayuda P07.');
assert(view.includes('function p07OpenInstructions'), 'Falta la apertura controlada del modal.');
assert(view.includes('function p07CloseInstructions'), 'Falta el cierre controlado del modal.');
assert(view.includes('new SpeechSynthesisUtterance(cleanText)'), 'No se crea la utterance con texto limpio.');
assert(view.includes("utterance.lang = voice ? voice.lang : 'es-MX'"), 'No se prioriza voz es-MX/español.');
assert(view.includes('utterance.onstart'), 'Falta estado de inicio de lectura.');
assert(view.includes('utterance.onend'), 'Falta estado de finalización de lectura.');
assert(view.includes('utterance.onerror'), 'Falta manejo seguro de error de voz.');
assert(view.includes('window.speechSynthesis.cancel()'), 'No se cancela audio anterior.');
assert(view.includes('p07ReadInstructions();'), 'La apertura no solicita narración de instrucciones.');
assert(view.includes('p07ReadQuestionWhenReady();'), 'El cierre no programa la narración de la pregunta.');
assert(!view.includes('class="img-fluid btnLeer pointer"'), 'P07 conserva un control legado .btnLeer.');
assert((view.match(/p07StopVoice\(\);/g) || []).length >= 4, 'Las transiciones no detienen lecturas previas.');

console.log('P07 audio/modal mock contract: PASS (audio audible real no verificado)');
