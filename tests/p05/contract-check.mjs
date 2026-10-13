import fs from 'node:fs';

const view = fs.readFileSync(
  'app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php',
  'utf8'
);
const model = fs.readFileSync('app/Models/AntecedentesNoPatologicosModel.php', 'utf8');
const controller = fs.readFileSync('app/Controllers/ModulosController.php', 'utf8');
const searches = fs.readFileSync('app/Controllers/BusquedasController.php', 'utf8');
const fixture = fs.readFileSync('tests/fixtures/security-fixtures.sql', 'utf8');
const m3Controller = controller.match(
  /public function pacienteEditarCuestionarioM3\(\)\s*\{([\s\S]*?)\/\/---------- 4\. ANTECEDENTES/
);
const commentController = controller.match(
  /public function pacienteComentarioModulo\(\)\s*\{([\s\S]*?)public function pacienteDeleteComentario/
);

const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

assert(view.includes("'p05:' + idPaciente + ':modulo-'"), 'La navegación no está aislada por paciente/módulo.');
assert(!view.includes('localStorage.getItem("seccionActual")'), 'Quedó una clave global de sección.');
assert(!view.includes('localStorage.setItem("seccionActual"'), 'Quedó una escritura global de sección.');
assert((model.match(/type="checkbox"/g) || []).length === 0, 'P05 todavía usa checkboxes para Sí/No.');
assert((model.match(/type="radio"/g) || []).length >= 2, 'P05 no conserva el par de radios Sí/No.');
assert(view.includes('p05SaveInFlight'), 'No existe guardia contra dobles guardados.');
assert(view.includes('p05FinalizationInFlight'), 'No existe guardia contra doble finalización.');
assert(view.includes('next_url'), 'La finalización no consume next_url.');
assert(view.includes('function p05QuestionIsReadyToAdvance'), 'No existe guardia de avance para una respuesta incompleta.');
assert(view.includes('p05NotifyMissingAnswer'), 'Falta el mensaje accesible de respuesta obligatoria.');
assert((view.match(/id="mensaje"/g) || []).length === 0, 'P05 conserva IDs mensaje duplicados.');
assert(view.includes('function p05SpeakText'), 'P05 no tiene la lectura por voz propia.');
assert(view.includes('function p05ReadInstructions'), 'P05 no tiene lectura de instrucciones.');
assert(view.includes('function p05ReadCurrentQuestion'), 'P05 no tiene lectura de la pregunta activa.');
assert(view.includes('function p05StopVoice'), 'P05 no tiene detención explícita de voz.');
assert(view.includes('window.speechSynthesis.cancel()'), 'P05 no cancela lecturas anteriores.');
assert(view.includes('voiceschanged'), 'P05 no contempla carga tardía de voces.');
assert((view.match(/id="p05ReadQuestionButton"/g) || []).length === 1, 'P05 debe tener un único control de lectura de pregunta.');
assert((view.match(/id="p05StopQuestionButton"/g) || []).length === 1, 'P05 debe tener un único control de detención de pregunta.');
assert((view.match(/id="p05ReadInstructionsButton"/g) || []).length === 1, 'P05 debe tener un único control de instrucciones.');
assert(!view.includes('class="img-fluid btnLeer pointer"'), 'P05 conserva controles de audio heredados duplicables.');
assert(model.includes('patient-nonpathological-next'), 'El modelo no renderiza el control Siguiente de P05.');
assert(model.includes('title="Selecciona una respuesta para continuar"'), 'El estado bloqueado de Siguiente no comunica el motivo.');
assert(!model.includes('class="p05-navigation-hint"'), 'P05 conserva el mensaje permanente junto a Siguiente.');
assert(!model.includes('continue; // Saltar a la siguiente iteración'), 'El modelo todavía omite la navegación cuando falta respuesta.');

assert(model.includes('respuestaPerteneceAlContexto'), 'Falta la comprobación de propiedad de la respuesta.');
assert(model.includes('r.id_paciente = :id_paciente'), 'La actualización no filtra por paciente.');
assert(model.includes('p.id_tema = :id_tema'), 'La actualización no filtra por tema.');
assert(controller.includes('new AuthMiddleware($view)'), 'El controlador no deriva el ámbito desde la ruta.');
assert(m3Controller && !m3Controller[1].includes('$idRol = $data'), 'M3 todavía confía en idRol para autorizar.');
assert(commentController && !commentController[1].includes('$idRol = $data'), 'Comentarios todavía confían en idRol para autorizar.');
const roleMarker = "$rolReal = ($auth['vista'] === 'historia-clinica') ? 'Paciente' : 'Doctor';";
assert(searches.includes(roleMarker), 'El GET confía en idRol del navegador.');
assert(fixture.includes('pregunta TEXT NOT NULL'), 'El fixture no refleja la columna pregunta consumida por el modelo.');

console.log('P05 contract checks: PASS');
