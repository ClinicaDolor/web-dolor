import fs from 'node:fs';
import assert from 'node:assert/strict';

const view = fs.readFileSync('app/views/historia-clinica-paciente/antecedentes-personales-patologicos.php', 'utf8');
const model = fs.readFileSync('app/Models/AntecedentesPatologicosModel.php', 'utf8');
const controller = fs.readFileSync('app/Controllers/ModulosController.php', 'utf8');
const searches = fs.readFileSync('app/Controllers/BusquedasController.php', 'utf8');

assert.match(view, /data-p07-control/);
assert.match(view, /async function p07AdvanceV1/);
assert.match(view, /async function p07AdvanceV2/);
assert.match(view, /p07SaveActive/);
assert.match(view, /p07StopVoice/);
assert.match(view, /document\.addEventListener\('click'/);
assert.match(view, /document\.addEventListener\('change'/);
assert.match(view, /p07:/);
assert.match(model, /obtenerContextoRespuestaV1/);
assert.match(model, /obtenerContextoRespuestaV2/);
assert.match(model, /id_paciente = :id_paciente/);
assert.doesNotMatch(model, /data-p07-control=\"true\"[^>]*onchange=/);
assert.match(controller, /str_starts_with\(\$path, '\/historia-clinica\/'\)/);
assert.match(searches, /rolReal = \(\$auth\['vista'\] === 'historia-clinica'\)/);
console.log('P07 contract: PASS');
