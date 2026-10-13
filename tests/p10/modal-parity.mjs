import fs from 'node:fs';

const read = path => fs.readFileSync(path, 'utf8');
const assert = (value, message) => { if (!value) throw new Error(message); };
const p04 = read('app/views/historia-clinica-paciente/antecedentes-familiares.php');
const p10 = read('app/views/historia-clinica-paciente/procedimientos-control-dolor.php');
const css = read('public/assets/css/app.css');

const canonicalStructure = [
  'modal fade patient-family-history-modal patient-instructions-modal',
  'modal-dialog modal-dialog-centered patient-instructions-dialog',
  'modal-content patient-family-history-modal-content patient-instructions-modal-content',
  'modal-header patient-instructions-modal-header',
  'modal-body patient-instructions-modal-body',
  'modal-footer patient-family-history-modal-actions patient-instructions-modal-actions'
];
for (const marker of canonicalStructure) {
  assert(p04.includes(marker), `P04: falta marcador canónico ${marker}`);
  assert(p10.includes(marker), `P10: falta marcador canónico ${marker}`);
}

const p10ModalStart = p10.indexOf('<div id="p10InstructionsModal"');
const p10ModalEnd = p10.indexOf('</div>\n        <?php include_once', p10ModalStart);
const p10Modal = p10.slice(p10ModalStart, p10ModalEnd);
for (const marker of ['p10InstructionsText', 'p10-voice-prompt', 'patient-family-history-modal-note', 'p10ReadInstructionsButton', 'p10StopInstructionsButton', 'Entendido, comenzar']) {
  assert(p10Modal.includes(marker), `P10: falta contenido o control ${marker}`);
}
assert(p10Modal.indexOf('p10ReadInstructionsButton') < p10Modal.indexOf('p10StopInstructionsButton'), 'P10: orden Escuchar/Detener incorrecto.');
assert(p10Modal.indexOf('p10StopInstructionsButton') < p10Modal.indexOf('Entendido, comenzar'), 'P10: la acción principal no está al final.');
assert(!p10Modal.includes('patient-procedures-modal') && !p10Modal.includes('p10-secondary'), 'P10: quedaron clases del modal independiente.');

const p04Gradient = 'linear-gradient(135deg, rgba(255, 255, 255, .98), rgba(248, 245, 255, .98))';
assert(css.includes(`body.app-shell-body.patient-family-history-page .patient-family-history-modal .modal-header {`) && css.includes(p04Gradient), 'P04: no se encontró el fondo canónico del encabezado.');
assert(css.includes(`body.app-shell-body.patient-procedures-page .patient-family-history-modal .modal-header {`) && css.includes(p04Gradient), 'P10: el encabezado no reutiliza el fondo canónico de P04.');
assert(!css.includes('patient-procedures-modal-header') && !css.includes('patient-procedures-modal-content'), 'CSS: quedó el bloque visual verde de P10.');
assert(css.includes('.patient-family-history-secondary { background: #F3E8FA; border-color: #AB47BC; color: #5B1978; }'), 'P10: faltan estilos secundarios canónicos.');

console.log('P10_MODAL_PARITY: PASS estático (estructura y reglas P04 alineadas; renderizado visual requiere navegador)');
