import fs from 'node:fs';

const read = path => fs.readFileSync(path, 'utf8');
const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

const modules = [
  {
    id: 'P04',
    view: 'app/views/historia-clinica-paciente/antecedentes-familiares.php',
    scope: 'patient-family-history-page',
    cssMarker: 'patient-family-history-page',
    modal: 'p04InstructionsModal',
    voice: ['p04ReadInstructions', 'p04ReadCurrentQuestion', 'p04StopVoice']
  },
  {
    id: 'P05',
    view: 'app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php',
    scope: 'patient-nonpathological-page',
    cssMarker: 'patient-nonpathological-page',
    modal: 'p05InstructionsModal',
    voice: ['p05ReadInstructions', 'p05ReadCurrentQuestion', 'p05StopVoice']
  },
  {
    id: 'P06',
    view: 'app/views/historia-clinica-paciente/antecedentes-personales-quirurgicos.php',
    scope: 'patient-surgery-page',
    cssMarker: 'patient-surgery-page',
    modal: 'p06InstructionsModal',
    voice: ['p06ReadInstructions', 'p06ReadCurrentQuestion', 'p06StopVoice']
  }
];

const css = read('public/assets/css/app.css');
for (const module of modules) {
  const view = read(module.view);
  assert(view.includes(module.scope), `${module.id}: falta el scope de página.`);
  assert(view.includes(`id="${module.modal}"`), `${module.id}: falta el modal de instrucciones.`);
  for (const fn of module.voice) assert(view.includes(`function ${fn}`), `${module.id}: falta ${fn}.`);
  assert(view.includes('speechSynthesis'), `${module.id}: falta integración SpeechSynthesis.`);
  const cssStart = css.indexOf(module.cssMarker);
  assert(cssStart >= 0, `${module.id}: falta CSS scoped.`);
  const scopedCss = css.slice(cssStart);
  assert(scopedCss.includes('hero'), `${module.id}: falta composición del hero.`);
  assert(scopedCss.includes('card'), `${module.id}: falta composición de tarjeta.`);
  assert(scopedCss.includes('modal'), `${module.id}: falta tratamiento del modal.`);
  assert(scopedCss.includes('@media'), `${module.id}: falta regla responsive.`);
}

const p05 = read(modules[1].view);
assert(p05.includes('patient-nonpathological-question-tools'), 'P05: faltan controles de voz del cuestionario.');
assert(p05.includes('p05SpeechStarted = true'), 'P05: onstart no confirma el inicio real.');
assert(p05.includes('p05SpeechStarted = false'), 'P05: falta limpiar el estado de reproducción.');
assert(p05.includes('}, 1500);'), 'P05: falta límite de espera para narración bloqueada.');
assert(p05.includes('window.speechSynthesis.cancel();'), 'P05: falta cancelación de voz bloqueada o previa.');
assert(!p05.includes('id="mensaje"'), 'P05: no debe duplicar el mensaje global de P04.');
assert(p05.includes('p05MarkSidebarActive'), 'P05: falta sincronizar el estado activo del sidebar.');
assert(p05.includes('aria-current'), 'P05: el sidebar activo no tiene nombre accesible.');

console.log('PATIENT_MODULE_VISUAL_PARITY_GATE: PASS (contratos estáticos P04/P05/P06)');
console.log('Geometría renderizada y consola: requieren ejecución del navegador documentada por separado.');
