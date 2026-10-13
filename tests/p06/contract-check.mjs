import fs from 'node:fs';

const viewPath = 'app/views/historia-clinica-paciente/antecedentes-personales-quirurgicos.php';
const view = fs.readFileSync(viewPath, 'utf8');
const model = fs.readFileSync('app/Models/AntecedentesQuirurgicos.php', 'utf8');
const controller = fs.readFileSync('app/Controllers/ModulosController.php', 'utf8');
const searches = fs.readFileSync('app/Controllers/BusquedasController.php', 'utf8');
const cssArgument = process.argv.find((argument) => argument.startsWith('--css-path='));
const cssPath = cssArgument ? cssArgument.slice('--css-path='.length) : 'public/assets/css/app.css';
const css = fs.readFileSync(cssPath, 'utf8');

const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

assert(view.includes('patient-surgery-page'), 'P06 no tiene scope visual propio.');
assert(view.includes('MI HISTORIA CLÍNICA'), 'Falta la etiqueta institucional de P06.');
assert(view.includes('p06OpenInstructions'), 'Falta el modal de instrucciones de P06.');
assert(view.includes('p06ReadInstructions'), 'Falta la narración de instrucciones de P06.');
assert(view.includes('p06ReadCurrentQuestion'), 'Falta la narración del registro activo.');
assert(view.includes('p06StopVoice'), 'Falta la detención explícita de voz.');
assert(view.includes('p06:'), 'El progreso de P06 no está aislado por paciente.');
assert(!view.includes('localStorage.setItem("preguntaActual"'), 'P06 conserva una clave global de progreso.');
assert(view.includes('window.confirm'), 'P06 no confirma la eliminación.');
assert(view.includes("data.next_url"), 'P06 no consume next_url al finalizar.');
assert(view.includes("data.next_url.startsWith('/')"), 'P06 no valida que next_url sea local.');
assert(model.includes('Agregar otra cirugía'), 'Falta la acción visible para agregar otra cirugía.');
assert(model.includes('Guardar cambios'), 'Falta el control visible de guardado.');
assert(model.includes('No hay cirugías registradas'), 'Falta el estado vacío de P06.');
assert(view.includes('id="p06InstructionsModal"'), 'Falta el diálogo accesible de instrucciones.');

assert(model.includes('SELECT id, id_paciente, fecha, cirugia, observaciones'), 'La consulta de P06 no limita las columnas.');
assert(model.includes('WHERE id_paciente = :id_paciente'), 'La lectura de P06 no está parametrizada por paciente.');
assert(model.includes('WHERE id = :id AND id_paciente = :id_paciente'), 'La edición/eliminación no vincula registro y paciente.');
assert(!/\bconst\b/.test(model), 'El modelo PHP contiene una marca JS inesperada.');
assert(model.includes('htmlspecialchars'), 'La salida clínica de P06 no se escapa.');
assert(model.includes('$columns = [1 => \'fecha\', 2 => \'cirugia\', 3 => \'observaciones\']'), 'El selector de campo no usa lista permitida.');

const p06Start = controller.indexOf('public function pacienteInsertCirugia()');
const p06End = controller.indexOf('//----- 5. ANTECEDENTES PATOLOGICOS', p06Start);
const p06Controller = controller.slice(p06Start, p06End);
assert(p06Controller.includes('strpos($requestPath, \'/historia-clinica/\')'), 'P06 no deriva el ámbito de paciente desde la ruta.');
assert(p06Controller.includes('assertPatientAccess($idPaciente, $auth)'), 'P06 no autoriza el paciente antes de escribir.');
assert(p06Controller.includes('filter_var($data[\'idCirugia\']'), 'P06 no valida el identificador de edición.');
assert(p06Controller.includes('filter_var($data[\'idQuirurgico\']'), 'P06 no valida el identificador de eliminación.');
assert(searches.includes("$rolReal = ($auth['vista'] === 'historia-clinica') ? 'Paciente' : 'Doctor';"), 'El GET de P06 todavía confía en idRol del navegador.');

const cssStart = css.indexOf('/* P06-COMPLETE-01');
const cssEnd = css.indexOf('/* END P06-COMPLETE-01', cssStart);
assert(cssStart >= 0 && cssEnd > cssStart, 'No se pudo delimitar el bloque CSS de P06.');
const scopedCss = css.slice(cssStart, cssEnd);
assert(scopedCss.includes('patient-surgery-page'), 'El CSS de P06 no está delimitado al módulo.');
assert(!scopedCss.includes('!important'), 'El CSS P06 introduce !important innecesario.');

console.log('P06 contract checks: PASS');
