import fs from 'node:fs';

const view = fs.readFileSync(
  'app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php',
  'utf8'
);
const model = fs.readFileSync('app/Models/AntecedentesNoPatologicosModel.php', 'utf8');

const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

// Contrato de render sintético: el catálogo real sigue vacío y no se inventan
// preguntas. Se verifica la salida que el modelo debe construir para un select.
const emptySelectBranch = model.match(/if \(!\$respuestaLista\) \{([\s\S]*?)\} elseif/);
assert(emptySelectBranch, 'No se encontró la rama de respuesta vacía.');
assert(emptySelectBranch[1].includes('patient-nonpathological-next'), 'Siguiente no existe en la rama vacía.');
assert(emptySelectBranch[1].includes('disabled'), 'Siguiente no queda bloqueado sin respuesta.');
assert(emptySelectBranch[1].includes('title="Selecciona una respuesta para continuar"'), 'Falta el estado accesible de respuesta vacía.');
assert(!emptySelectBranch[1].includes('p05-navigation-hint'), 'La respuesta vacía agrega un mensaje permanente al footer.');

assert(model.includes('data-p05-next'), 'La respuesta guardada no conserva Siguiente.');
assert(view.includes('function p05AdvanceCurrentQuestion'), 'No existe el controlador único de guardado y avance.');
assert(view.includes('if (p05SaveInFlight)'), 'La navegación no conserva la cola ante un guardado pendiente.');
assert(view.includes('p05PendingAdvance'), 'La intención de navegación pendiente no está representada.');
assert(view.includes('data-p05-control'), 'Los controles no exponen metadatos para eventos delegados.');
assert(view.includes('if (!p05QuestionIsReadyToAdvance(activeQuestion))'), 'La navegación no valida la pregunta activa.');
assert(view.includes("p05SetMessage('Selecciona una respuesta para continuar.')"), 'No existe mensaje de bloqueo de avance.');
assert(view.includes('irASiguienteSeccion'), 'No se conserva el cambio entre temas.');
assert(view.includes('finalizarPreguntas()'), 'No se conserva el paso a comentarios.');

console.log('P05 navigation synthetic contract: PASS (persistencia/catalog real no verificados)');
