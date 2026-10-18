<?php

declare(strict_types=1);

/**
 * Contrato estático de separación entre el listado y el editor de Nota subsecuente.
 * No inicia la aplicación, no abre PDO y no envía HTTP.
 */

$root = dirname(__DIR__, 2);
$read = static function (string $relative) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('No se pudo leer ' . $relative);
    }
    return $contents;
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$routes = $read('app/Config/routes.php');
$controller = $read('app/Controllers/ClinicaController.php');
$list = $read('app/views/clinica/pacientes-nota-subsecuente.php');
$new = $read('app/views/clinica/pacientes-nota-subsecuente-nueva.php');
$editor = $read('app/views/clinica/partials/nota-subsecuente-editor.php');
$model = $read('app/Models/NotaSubsecuenteModel.php');

$listRoute = "'/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}'";
$newRoute = "'/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}/nueva'";
$assert(substr_count($routes, $listRoute) === 1, 'Debe existir una sola ruta de listado.');
$assert(substr_count($routes, $newRoute) === 1, 'Debe existir una sola ruta de alta nueva.');
$assert(str_contains($routes, "['ClinicaController', 'pacienteNuevaNotaSubsecuente']"), 'La ruta nueva debe apuntar al controlador autorizado.');
$assert(str_contains($controller, 'public function pacienteNuevaNotaSubsecuente('), 'Debe existir el método controlador de alta.');
$assert(str_contains($controller, "pacientes-nota-subsecuente-nueva.php"), 'El controlador debe renderizar la vista nueva.');
$assert(str_contains($controller, 'assertPatientAccess'), 'Ambas entradas deben conservar la autorización del paciente.');
$assert(str_contains($controller, 'No carga una nota o receta'), 'La ruta nueva debe iniciar con contexto vacío y sin lecturas clínicas accesorias.');

$assert(str_contains($list, 'Nueva nota subsecuente'), 'El listado debe ofrecer la acción de nueva nota.');
$assert(str_contains($list, 'fixedHeight: false'), 'El historial debe conservar altura natural.');
$assert(str_contains($list, 'data-referencia'), 'El listado debe conservar el contexto de referencia.');
$assert(str_contains($controller, 'getHistoryByPatient'), 'El controlador debe solicitar el historial completo después de autorizar al paciente.');
$assert(str_contains($model, 'public function getHistoryByPatient'), 'El modelo debe exponer la consulta de historial completo.');
$assert(str_contains($model, 'WHERE id_paciente = :id_paciente'), 'El historial debe quedar limitado al paciente autorizado.');
$assert(str_contains($list, 'codigo_referencia'), 'La referencia original debe conservarse visible por registro.');
$assert(str_contains($list, 'doctor-note-history-card-header'), 'La acción debe vivir en el encabezado de la tarjeta de historial.');
$assert(substr_count($list, 'Nueva nota subsecuente') === 1, 'Debe existir un único botón de nueva nota.');
$assert(str_contains($list, 'No hay notas subsecuentes registradas para este paciente.'), 'Debe existir un único estado vacío propio para el historial sin registros.');
$assert(!str_contains($list, 'No hay notas subsecuentes registradas para esta referencia.'), 'No debe conservarse el estado vacío limitado a una referencia.');
$assert(!str_contains($list, 'No se encontró información'), 'No debe duplicarse el estado vacío genérico de la tabla.');
$assert(str_contains($list, 'clinica/nota-subsecuente/'), 'El detalle debe abrirse mediante la ruta existente.');
$assert(!str_contains($list, 'AgregarNota'), 'El listado no debe contener el guardado de nota.');
$assert(!str_contains($list, 'IncluirReceta'), 'El listado no debe contener el formulario de receta.');
$assert(!str_contains($list, 'snow1'), 'El listado no debe contener el editor Quill de nota.');
$assert(!str_contains($list, 'snow2'), 'El listado no debe contener el editor Quill de receta.');
$assert(!str_contains($list, 'snow3'), 'El listado no debe contener el editor Quill de laboratorio.');
$assert(!str_contains($list, 'FechaProximaCita'), 'El listado no debe contener la captura de próxima cita.');
$assert(!str_contains($list, 'doctor-note-detail-card'), 'El listado no debe contener un panel de detalle permanente.');

$assert(str_contains($new, "partials/nota-subsecuente-editor.php"), 'La vista nueva debe reutilizar el editor único.');
$assert(str_contains($new, 'id="notaSubsecuenteForm"'), 'La vista nueva debe tener un formulario identificable.');
$assert(str_contains($new, 'data-note-exit'), 'La vista nueva debe ofrecer cancelación con guardia de cambios.');
$assert(str_contains($new, 'data-list-url'), 'La vista nueva debe conocer el destino del listado.');
$assert(str_contains($new, 'fixedHeight: false'), 'Las tablas auxiliares deben conservar altura natural.');
$assert(str_contains($new, 'window.location.href = listUrl'), 'El guardado debe regresar al listado.');
$assert(str_contains($new, 'beforeunload'), 'La vista nueva debe advertir cambios sin guardar.');

foreach (['TA', 'FrecCardiaca', 'Pulso', 'Spo2', 'Fio2', 'Ecog', 'Karnovsky', 'Peso', 'Talla', 'snow1', 'IncluirReceta', 'Diagnostico', 'Temperatura', 'snow2', 'Titulo', 'Archivo', 'snow3', 'FechaProximaCita'] as $field) {
    $assert(str_contains($editor, 'id="' . $field . '"'), 'Campo clínico ausente: ' . $field);
}
$assert(str_contains($new, 'X-M03-CSRF'), 'Debe conservarse el envío CSRF.');
$assert(str_contains($model, 'beginTransaction'), 'La transacción de Phase 07 debe conservarse.');
$assert(str_contains($model, 'rollBack'), 'El rollback de Phase 07 debe conservarse.');
$assert(str_contains($new, 'incluirReceta: incluirReceta'), 'Debe conservarse el opt-in de receta.');
$assert(str_contains($new, "fc: incluirReceta ? frecCardiaca"), 'Debe conservarse el mapeo fc condicional.');
$assert(str_contains($new, "temperatura: incluirReceta ? temperatura"), 'Debe conservarse el mapeo de temperatura condicional.');

echo "TH_DOCTOR_SUBSEQUENT_NOTE_INDEPENDENT_VIEWS_IMPLEMENTATION_08: PASS_STATIC\n";
