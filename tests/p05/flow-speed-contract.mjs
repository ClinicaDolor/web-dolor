import fs from 'node:fs';
import assert from 'node:assert/strict';

const root = new URL('../../', import.meta.url);
const view = fs.readFileSync(new URL('app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php', root), 'utf8');
const model = fs.readFileSync(new URL('app/Models/AntecedentesNoPatologicosModel.php', root), 'utf8');
const catalog = fs.readFileSync(new URL('tests/fixtures/p05-catalog.php', root), 'utf8');

assert.match(model, /data-p05-control="true"/, 'El generador no marca los controles dinámicos P05.');
assert.match(model, /data-p05-saved-value=/, 'Los controles no exponen el valor confirmado necesario para evitar UPDATE duplicados.');
assert.match(model, /data-p05-next(?:\s|>)/, 'El generador no conserva el control Siguiente delegado.');
assert.match(model, /data-p05-next-theme=/, 'El generador no conserva la transición de tema.');
assert.match(model, /data-p05-finalize/, 'El generador no conserva la transición a comentarios.');

assert.match(view, /document\.addEventListener\('click'/, 'P05 no usa delegación de clic para contenido AJAX.');
assert.match(view, /document\.addEventListener\('change'/, 'P05 no usa delegación de cambio para inputs dinámicos.');
assert.match(view, /async function p05AdvanceCurrentQuestion/, 'No existe un único punto asíncrono de transición.');
assert.match(view, /if \(p05SaveInFlight\) \{[\s\S]*p05QueueAdvance/, 'Un clic durante un guardado no queda encolado.');
assert.match(view, /for \(const control of dirtyControls\)/, 'Siguiente no espera cada guardado pendiente antes de avanzar.');
assert.match(view, /p05ClearDependentControls/, 'La limpieza de dependencias para No no está conectada al guardado confirmado.');
assert.match(view, /selected\.value === 'No' && activeIndex === 0/, 'La transición automática de No no usa la primera pregunta real del tema.');
assert.match(view, /p05ReadQuestionWhenReady\(\)/, 'La narración de la nueva pregunta no está conectada a la navegación.');
assert.doesNotMatch(model, /data-p05-control="true"[^>]*onchange=/, 'El HTML paciente combina control delegado con onchange inline.');

const themeCount = (catalog.match(/\['id' => \d+, 'tema'/g) || []).length;
const questionCount = (catalog.match(/\['id' => \d+, 'id_tema'/g) || []).length;
assert.equal(themeCount, 7, 'El fixture P05 debe conservar siete temas.');
assert.equal(questionCount, 25, 'El fixture P05 debe conservar 25 preguntas.');
assert.match(catalog, /\['id' => 27, 'id_tema' => 7, [\s\S]*tipo' => 'input'/, 'Falta el primer campo de Grupo Sanguíneo.');
assert.match(catalog, /\['id' => 28, 'id_tema' => 7, [\s\S]*tipo' => 'input'/, 'Falta el segundo campo de Grupo Sanguíneo.');

console.log('P05 flow-speed contract: PASS');
