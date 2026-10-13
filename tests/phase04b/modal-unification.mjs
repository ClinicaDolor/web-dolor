import fs from 'node:fs';

const read = path => fs.readFileSync(path, 'utf8');
const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

const modules = [
  ['P04', 'app/views/historia-clinica-paciente/antecedentes-familiares.php', 'p04InstructionsModal'],
  ['P05', 'app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php', 'p05InstructionsModal'],
  ['P06', 'app/views/historia-clinica-paciente/antecedentes-personales-quirurgicos.php', 'p06InstructionsModal'],
  ['P07', 'app/views/historia-clinica-paciente/antecedentes-personales-patologicos.php', 'p07InstructionsModal'],
  ['P08', 'app/views/historia-clinica-paciente/medicacion-actual.php', 'p08InstructionsModal'],
  ['P09', 'app/views/historia-clinica-paciente/medicacion-control-dolor.php', 'p09InstructionsModal'],
  ['P10', 'app/views/historia-clinica-paciente/procedimientos-control-dolor.php', 'p10InstructionsModal'],
  ['P11', 'app/views/historia-clinica-paciente/evaluacion-dolor.php', 'p11InstructionsModal']
];

const canonicalClasses = [
  'patient-instructions-modal',
  'patient-instructions-dialog',
  'patient-instructions-modal-content',
  'patient-instructions-modal-header',
  'patient-instructions-modal-title',
  'patient-instructions-modal-body',
  'patient-instructions-modal-actions',
  'patient-instructions-secondary'
];

for (const [id, path, modalId] of modules) {
  const view = read(path);
  const start = view.indexOf(`<div id="${modalId}"`);
  assert(start >= 0, `${id}: falta el modal ${modalId}.`);
  const modal = view.slice(start, Math.min(view.length, start + 10000));
  const modalOpenEnd = modal.indexOf('>');
  assert(modalOpenEnd > 0, `${id}: apertura del modal incompleta.`);
  const modalOpen = modal.slice(0, modalOpenEnd + 1);
  assert(/class="[^"]*patient-instructions-modal/.test(modalOpen), `${id}: el contenedor raíz no usa la clase canónica.`);
  assert(/role="dialog"/.test(modalOpen) && /aria-modal="true"/.test(modalOpen), `${id}: contrato de diálogo incompleto.`);
  const labelledBy = modalOpen.match(/aria-labelledby="([^"]+)"/);
  const describedBy = modalOpen.match(/aria-describedby="([^"]+)"/);
  assert(labelledBy && describedBy, `${id}: falta relación accesible del modal.`);
  assert(modal.includes(`id="${labelledBy[1]}"`) && modal.includes(`id="${describedBy[1]}"`), `${id}: las referencias ARIA no apuntan a contenido existente.`);
  const structuralOrder = [
    'patient-instructions-dialog',
    'patient-instructions-modal-content',
    'patient-instructions-modal-header',
    'patient-instructions-modal-title',
    'patient-instructions-modal-body',
    'patient-instructions-modal-actions'
  ];
  let previous = modalOpenEnd;
  for (const marker of structuralOrder) {
    const position = modal.indexOf(marker, previous);
    assert(position > previous, `${id}: orden estructural inválido o falta ${marker}.`);
    previous = position;
  }
  assert(modal.indexOf('patient-instructions-secondary', previous) > previous, `${id}: faltan controles secundarios dentro del footer canónico.`);
  assert(modal.indexOf('Escuchar', previous) > previous && modal.indexOf('Detener', previous) > previous && modal.indexOf('Entendido, comenzar', previous) > previous, `${id}: faltan controles canónicos de voz/cierre.`);
  assert(view.includes('PatientInstructionsModal'), `${id}: falta integración del contrato de foco compartido.`);
  assert(view.includes('patient-instructions-modal.js'), `${id}: falta la carga del utilitario compartido.`);
}

const helper = read('public/assets/js/patient-instructions-modal.js');
for (const marker of ['capture', 'open', 'close', 'handleKeydown', 'event.key === \'Escape\'', 'event.key !== \'Tab\'']) {
  assert(helper.includes(marker), `Utilitario: falta ${marker}.`);
}

const css = read('public/assets/css/app.css');
assert(css.includes('patient-instructions-modal-body') && css.includes('overflow-y: auto'), 'CSS: el cuerpo canónico no tiene desplazamiento vertical seguro.');
assert(css.includes('max-height: calc(100vh - 2rem)'), 'CSS: falta límite de altura relativo al viewport.');

const p03 = read('app/views/historia-clinica-paciente/ficha-identificiacion.php');
assert(!p03.includes('InstructionsModal'), 'P03: no debe recibir un modal de instrucciones artificial.');

console.log('PHASE_04B_01_MODAL_UNIFICATION_CONTRACT: PASS (estructura y contrato estático P04-P11; navegador separado)');
