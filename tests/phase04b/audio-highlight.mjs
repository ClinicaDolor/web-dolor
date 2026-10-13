import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const utilityPath = path.join(root, 'public/assets/js/patient-narration-highlight.js');
const utility = fs.readFileSync(utilityPath, 'utf8');
const viewNames = [
  'antecedentes-familiares.php',
  'antecedentes-personales-no-patologicos.php',
  'antecedentes-personales-quirurgicos.php',
  'antecedentes-personales-patologicos.php',
  'medicacion-actual.php',
  'medicacion-control-dolor.php',
  'procedimientos-control-dolor.php',
  'evaluacion-dolor.php'
];

assert.match(utility, /window\.PatientNarrationHighlight/);
assert.match(utility, /speechApi\.speak/);
assert.match(utility, /speechApi\.cancel/);
assert.match(utility, /onboundary/);
assert.match(utility, /sin-eventos-boundary/);
assert.doesNotMatch(utility, /setTimeout|setInterval|requestAnimationFrame/);

for (const viewName of viewNames) {
  const viewPath = path.join(root, 'app/views/historia-clinica-paciente', viewName);
  const source = fs.readFileSync(viewPath, 'utf8');
  assert.match(source, /data-narration-instructions/, viewName + ': falta marca de instrucciones');
  assert.match(source, /patient-narration-highlight\.js/, viewName + ': falta controlador compartido');
}

const css = fs.readFileSync(path.join(root, 'public/assets/css/app.css'), 'utf8');
assert.match(css, /patient-narration-token\.is-reading/);
assert.match(css, /body\.app-shell-body\.patient-clinical-form/);

console.log('PHASE_04B_03_AUDIO_HIGHLIGHT_STATIC: PASS');
console.log('MODULES: ' + viewNames.length);
console.log('CONTRACT: shared speech wrapper, boundary sync, explicit unsupported-boundary fallback, no timers');
