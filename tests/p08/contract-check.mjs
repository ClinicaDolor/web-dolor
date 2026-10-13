import fs from 'node:fs';
import vm from 'node:vm';

const view = fs.readFileSync('app/views/historia-clinica-paciente/medicacion-actual.php', 'utf8');
const model = fs.readFileSync('app/Models/MedicacionActualModel.php', 'utf8');
const controller = fs.readFileSync('app/Controllers/ModulosController.php', 'utf8');
const searches = fs.readFileSync('app/Controllers/BusquedasController.php', 'utf8');
const css = fs.readFileSync('public/assets/css/app.css', 'utf8');
const assert = (condition, message) => { if (!condition) throw new Error(message); };

for (const token of ['patient-medication-page', 'MI HISTORIA CLÍNICA', 'p08InstructionsModal', 'p08OpenInstructions', 'p08Speak', 'p08StopVoice', 'p08:', 'Guardar cambios', 'data-action="finalize"']) {
  assert(view.includes(token), `Falta contrato P08: ${token}`);
}
assert(model.includes('No hay medicamentos registrados'), 'Falta el estado vacío seguro de P08.');
assert(!view.includes('onclick='), 'P08 conserva handlers inline.');
assert(!view.includes('localStorage.setItem("preguntaActual"'), 'P08 conserva una clave de progreso global.');
assert(view.includes('idModulo: 6'), 'P08 no finaliza con idModulo 6.');
assert(view.includes("data.next_url !== 'string'") || view.includes("typeof data.next_url !== 'string'"), 'P08 no valida next_url.');
for (const field of ['descripcion', 'medicamento', 'tiempo_uso', 'dosis', 'medico']) assert(view.includes(`data-p08-field="${field}"`) || model.includes(`'${field}'`), `Falta campo P08: ${field}`);
assert(model.includes('mostrarPreguntasM6Paciente'), 'Falta renderer paciente P08.');
assert(model.includes('WHERE id = :id AND id_paciente = :id_paciente'), 'La edición P08 no vincula registro y paciente.');
assert(model.includes('DELETE FROM pac_medicacion_actual_modulo_6 WHERE id = :idMedicamento AND id_paciente = :id_paciente'), 'La eliminación P08 no vincula registro y paciente.');
assert(model.includes('if (!$changed) return [\'resultado\' => 200'), 'P08 no evita UPDATE sin cambios.');
assert(model.includes('finalizarModuloPaciente'), 'Falta finalización idempotente P08.');
const controllerStart = controller.indexOf('public function pacienteInsertMedicamento()');
const controllerEnd = controller.indexOf('//----- 7. MEDICACION PARA CONTROLAR EL DOLOR', controllerStart);
const p08Controller = controller.slice(controllerStart, controllerEnd);
assert(p08Controller.includes("str_starts_with($path, '/historia-clinica/')"), 'P08 no deriva contexto por ruta.');
assert(p08Controller.includes('assertPatientAccess($idPaciente, $auth)'), 'P08 no autoriza antes de CRUD.');
assert(searches.includes('mostrarPreguntasM6Paciente'), 'El endpoint M6 no usa renderer seguro de paciente.');
assert(css.includes('/* P08-COMPLETE-01'), 'Falta scope CSS P08.');

const scripts = [...view.matchAll(/<script\s*>([\s\S]*?)<\/script>/gi)].map(match => match[1]).filter(script => script.trim());
scripts.forEach((script, index) => { try { new vm.Script(script, {filename: `p08-inline-${index}.js`}); } catch (error) { throw new Error(`JS inline P08 inválido en bloque ${index}: ${error.message}; inicio=${JSON.stringify(script.slice(0, 180))}`); } });
console.log('P08 contract checks: PASS');
