import fs from 'node:fs';

const read = path => fs.readFileSync(path, 'utf8');
const assert = (value, message) => { if (!value) throw new Error(message); };

const view = read('app/views/historia-clinica-paciente/medicacion-control-dolor.php');
const model = read('app/Models/MedicacionDolorModel.php');
const search = read('app/Controllers/BusquedasController.php');
const controller = read('app/Controllers/ModulosController.php');
const css = read('public/assets/css/app.css');

assert(view.includes('patient-pain-medication-page'), 'P09: falta el scope visual.');
assert(view.includes('id="p09InstructionsModal"'), 'P09: falta el modal automático.');
assert(view.includes('function p09OpenInstructions'), 'P09: falta apertura de instrucciones.');
assert(view.includes('function p09ReadInstructions'), 'P09: falta narración de instrucciones.');
assert(view.includes('function p09ReadCurrentQuestion'), 'P09: falta narración de pregunta.');
assert(view.includes('function p09StopVoice'), 'P09: falta detener/cancelar audio.');
assert(view.includes("idModulo: p09ModuleId"), 'P09: la finalización no usa el módulo 7.');
assert(view.includes("data.next_url !== 'string'"), 'P09: falta validar next_url.');
assert(view.includes('data-p09-control="response"'), 'P09: falta contrato de respuesta.');
assert(view.includes('data-p09-control="result"'), 'P09: falta contrato de resultados.');
assert(view.includes('data-p09-control="consumption"'), 'P09: falta contrato de consumo.');
assert(!view.includes('onchange="respuestaPreguntaSelect'), 'P09: no debe conservar handlers inline heredados.');
assert(!view.includes('SELECT * FROM receta_medica'), 'P09: no debe contener consultas SQL directas.');
assert(model.includes('function mostrarPreguntasM7Paciente'), 'P09: falta renderer seguro para paciente.');
assert(model.includes('WHERE id = :idRespuesta AND id_paciente = :idPaciente AND id_pregunta = :idPregunta'), 'P09: el UPDATE no está acotado al propietario.');
assert(model.includes("['Si', 'No']") && model.includes("'Tuve efectos adversos'"), 'P09: faltan allowlists de valores.');
assert(search.includes('mostrarPreguntasM7Paciente'), 'P09: el endpoint no usa el renderer de paciente.');
assert(controller.includes('public function pacienteEditarCuestionarioM7'), 'P09: falta controlador de guardado.');
assert(controller.includes("$data['idRol'] = 'Paciente';"), 'P09: el rol no se deriva de la ruta.');
assert(css.includes('patient-pain-medication-page'), 'P09: falta CSS scoped para dependencias.');

console.log('P09_CONTRACT_CHECK: PASS (contratos estáticos; navegador y persistencia requieren ejecución separada)');
