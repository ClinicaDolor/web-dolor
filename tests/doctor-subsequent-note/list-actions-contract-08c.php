<?php

declare(strict_types=1);

/**
 * Contrato estático de acciones del listado de Nota subsecuente.
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
$model = $read('app/Models/NotaSubsecuenteModel.php');
$list = $read('app/views/clinica/pacientes-nota-subsecuente.php');
$create = $read('app/views/clinica/pacientes-nota-subsecuente-nueva.php');
$editor = $read('app/views/clinica/partials/nota-subsecuente-editor.php');

$assert(str_contains($routes, "'/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}/editar/{idNota}'"), 'Debe existir una ruta GET de edición.');
$assert(str_contains($routes, "['ClinicaController', 'pacienteEditarNotaSubsecuente']"), 'La ruta de edición debe apuntar al controlador autorizado.');
$assert(str_contains($controller, 'public function pacienteEditarNotaSubsecuente('), 'Debe existir el método controlador de edición.');
$assert(str_contains($controller, 'getForPatientReference'), 'La edición debe cargar la nota por paciente y referencia propios.');
$assert(str_contains($controller, 'assertPatientAccess'), 'La entrada de edición debe autorizar al paciente en servidor.');
$assert(str_contains($controller, 'edit_mode'), 'La vista compartida debe distinguir el modo de edición.');
$assert(str_contains($model, 'WHERE id = :id'), 'La consulta de edición debe restringir el registro.');
$assert(str_contains($model, 'AND id_paciente = :id_paciente'), 'La consulta de edición debe restringir el paciente.');
$assert(str_contains($model, 'AND codigo_referencia = :referencia'), 'La consulta de edición debe restringir la referencia.');
$assert(str_contains($model, 'UPDATE nota_subsecuente SET'), 'Debe conservarse la actualización de una nota existente.');

$assert(!str_contains($list, 'doctor-note-patient-card'), 'El listado no debe contener la tarjeta Contexto de la consulta.');
$assert(!str_contains($list, 'Contexto de la consulta'), 'El título Contexto de la consulta debe desaparecer del listado.');
$assert(str_contains($list, 'doctor-note-patient-strip'), 'La identificación compacta del paciente debe permanecer.');
$assert(str_contains($list, 'doctor-note-history-card-header'), 'La acción nueva debe permanecer dentro del encabezado de historial.');
$assert(str_contains($list, 'ACCIONES'), 'La columna debe llamarse ACCIONES.');
$assert(str_contains($list, 'data-action-toggle'), 'Cada fila debe tener un control de acciones.');
$assert(substr_count($list, '>Editar</span>') === 1, 'El menú debe ofrecer una única acción Editar por plantilla.');
$assert(substr_count($list, '>Detalle</span>') === 1, 'El menú debe ofrecer una única acción Detalle por plantilla.');
$assert(!str_contains($list, 'Ver detalle'), 'El botón Ver detalle debe ser sustituido.');
$assert(str_contains($list, 'data-action-item'), 'Las opciones deben ser elementos accionables.');
$assert(str_contains($list, "event.key === 'Escape'"), 'Escape debe cerrar el menú.');
$assert(str_contains($list, "event.target.closest('[data-action-toggle]')"), 'El control debe usar eventos delegados.');
$assert(str_contains($list, 'getBoundingClientRect'), 'El menú debe posicionarse para no quedar recortado por la tabla.');
$assert(!str_contains($list, 'notaSubsecuenteForm'), 'El listado no debe contener formularios permanentes.');
$assert(str_contains($list, 'clinica/nota-subsecuente/paciente/'), 'Editar debe conservar el contexto de paciente y referencia propios.');
$assert(str_contains($list, 'clinica/nota-subsecuente/'), 'Detalle debe conservar la ruta existente.');

$assert(str_contains($create, 'notaSubsecuenteForm'), 'La vista independiente de creación debe permanecer.');
$assert(str_contains($create, 'AgregarNota'), 'El guardado de creación debe permanecer.');
$assert(str_contains($create, "'/clinica/paciente/insert-nota-subsecuente'"), 'El endpoint de guardado debe permanecer.');
$assert(str_contains($editor, 'data-id-nota'), 'El editor debe transportar el identificador de edición.');
$assert(str_contains($editor, 'data-idreceta'), 'El editor debe transportar la receta asociada.');
$assert(str_contains($controller, 'validCaptureCsrf'), 'El POST debe conservar la validación CSRF.');
$assert(str_contains($controller, 'editarNotaSubsecuente'), 'La edición debe reutilizar el método de actualización existente.');

echo "TH_DOCTOR_SUBSEQUENT_NOTE_LIST_UX_ACTIONS_08C: PASS_STATIC\n";
