import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..', '..');

const files = {
  P01: 'app/views/login/login-paciente.php',
  P02: 'app/views/paciente/index.php',
  P03: 'app/views/historia-clinica-paciente/ficha-identificiacion.php',
  P04: 'app/views/historia-clinica-paciente/antecedentes-familiares.php',
  P05: 'app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php',
  P06: 'app/views/historia-clinica-paciente/antecedentes-personales-quirurgicos.php',
  P07: 'app/views/historia-clinica-paciente/antecedentes-personales-patologicos.php',
  P08: 'app/views/historia-clinica-paciente/medicacion-actual.php',
  P09: 'app/views/historia-clinica-paciente/medicacion-control-dolor.php',
  P10: 'app/views/historia-clinica-paciente/procedimientos-control-dolor.php',
  P11: 'app/views/historia-clinica-paciente/evaluacion-dolor.php',
};

const source = Object.fromEntries(Object.entries(files).map(([id, relative]) => {
  const absolute = path.join(root, relative);
  return [id, { relative, absolute, text: fs.readFileSync(absolute, 'utf8') }];
}));
const css = fs.readFileSync(path.join(root, 'public/assets/css/app.css'), 'utf8');

const checks = [];
const warnings = [];
function check(id, name, pass, detail = '') {
  checks.push({ id, name, pass: Boolean(pass), detail });
}
function count(text, expression) {
  return [...text.matchAll(expression)].length;
}

check('P01', 'login con shell de autenticación de paciente', /auth-page-patient/.test(source.P01.text));
check('P01', 'login incluye app.css', /RUTA_CSS|assets[\\/]css[\\/]app\.css/.test(source.P01.text));

for (const id of Object.keys(files).filter((item) => item !== 'P01')) {
  const text = source[id].text;
  check(id, 'shell paciente', /app-shell-body[\s\S]*app-shell-patient/.test(text));
  check(id, 'sidebar y toggler accesible', /\$data\['sidebar'\]|\$data\["sidebar"\]|sidebar-toggler/.test(text) && /aria-label="(?:Abrir menú principal|Abrir navegación|Mostrar navegación|Toggle navigation)"/.test(text));
  check(id, 'footer Doctor reutilizado una sola vez', count(text, /footer-doctor\.php/g) === 1, `incluye=${count(text, /footer-doctor\.php/g)}`);
  check(id, 'botón/acción de regreso presente', /Volver(?: a mi Historia Clínica| al expediente)|href=.*historia-clinica/.test(text));
  check(id, 'reglas de foco visibles en CSS', /patient-clinical-form[^\{]*[\s\S]*focus-visible|patient-[a-z-]+-page[^\{]*[\s\S]*focus-visible/.test(css));
}

check('P02', 'dashboard con sección de módulos y progreso', /patient-modules-section/.test(source.P02.text) && /patient-progress-panel/.test(source.P02.text));
check('P03', 'ficha con cuatro tarjetas clínicas', count(source.P03.text, /class="patient-identification-card"/g) === 4, `tarjetas=${count(source.P03.text, /class="patient-identification-card"/g)}`);
check('P03', 'ficha sin modal de instrucciones (comportamiento esperado)', !/patient-instructions-modal/.test(source.P03.text));

for (const id of ['P04', 'P05', 'P06', 'P07', 'P08', 'P09', 'P10', 'P11']) {
  const text = source[id].text;
  check(id, 'modal canónico de instrucciones', /patient-instructions-modal/.test(text) && /role="dialog"/.test(text) && /aria-modal="true"/.test(text) && /aria-labelledby=/.test(text) && /aria-describedby=/.test(text));
  check(id, 'modal y resaltado compartidos', /patient-instructions-modal\.js/.test(text) && /patient-narration-highlight\.js/.test(text));
  check(id, 'texto de instrucciones marcado para narración', count(text, /data-narration-instructions/g) === 1, `marcas=${count(text, /data-narration-instructions/g)}`);
  check(id, 'acciones del modal con controles de voz', /patient-instructions-modal-actions/.test(text) && /Escuchar/.test(text) && /Detener/.test(text));
  check(id, 'modal mantiene retorno contextual', /Volver(?: a mi Historia Clínica| al expediente)/.test(text));
  if (/fixedHeight\s*:\s*true/.test(text)) warnings.push(`${id}: se encontró fixedHeight:true en la vista.`);
  if (/height\s*:\s*100%/.test(text)) warnings.push(`${id}: se encontró height:100% en la vista; requiere revisión visual si afecta una tarjeta.`);
}

check('P11', 'contrato del mapa corporal y carga dinámica', /p11-canvas-wrap/.test(css) && /initMap\(canvas\)/.test(source.P11.text) && /data-p11-nav/.test(source.P11.text));
check('P11', 'mapa posterior disponible', /section === 'espalda'|data-p11-nav="espalda"|Imagen de espalda/.test(source.P11.text));

check('CSS', 'token institucional de acento disponible', /--clinic-accent\s*:/.test(css));
check('CSS', 'tarjetas clínicas con altura natural', /body\.app-shell-body\.patient-clinical-form \.card\s*\{[\s\S]*?height:\s*auto;/.test(css));
check('CSS', 'modal común con scroll vertical controlado', /patient-instructions-modal\s*\{[\s\S]*?overflow-y:\s*auto;/.test(css) && /max-height:\s*calc\(100vh\s*-/.test(css));
check('CSS', 'modal común responsive', /@media \(max-width:\s*767\.98px\)\s*\{[\s\S]*?patient-instructions-modal/.test(css));
check('CSS', 'resaltado de narración y reduced motion', /patient-narration-token\.is-reading/.test(css) && /prefers-reduced-motion/.test(css));
check('CSS', 'mapa P11 permite ajuste interno sin forzar ancho de página', /p11-canvas-wrap\s*\{[\s\S]*?overflow:\s*auto;/.test(css) && /p11-canvas-wrap canvas\s*\{[\s\S]*?max\-width|width:\s*min\(100%/.test(css));

for (const [id, { text }] of Object.entries(source)) {
  const ids = [...text.matchAll(/\bid="([^"{}]+)"/g)].map((match) => match[1]);
  const duplicates = [...new Set(ids.filter((value, index) => ids.indexOf(value) !== index))];
  if (duplicates.length) warnings.push(`${id}: IDs estáticos repetidos: ${duplicates.join(', ')}`);
}

const failed = checks.filter((item) => !item.pass);
const result = {
  phase: 'PHASE-04B-04',
  scope: 'P01-P11',
  static_checks: { total: checks.length, passed: checks.length - failed.length, failed: failed.length },
  checks,
  warnings,
  responsive_runtime: 'NO VERIFICADO: la API de navegador disponible en esta sesión no expone override de viewport.',
  screenshots: 'NO VERIFICADO como archivos persistentes; se observaron capturas del viewport disponible para P02, P04 y P11.',
};
console.log(JSON.stringify(result, null, 2));
process.exitCode = failed.length ? 1 : 0;
