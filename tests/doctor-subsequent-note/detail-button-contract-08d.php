<?php

declare(strict_types=1);

/**
 * Contrato estático del botón único de detalle del listado de Nota subsecuente.
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
$css = $read('public/assets/css/app.css');

$assert(str_contains($routes, "'/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}/editar/{idNota}'"), 'La ruta de edición debe conservarse en backend.');
$assert(str_contains($routes, "['ClinicaController', 'pacienteEditarNotaSubsecuente']"), 'La ruta de edición debe conservar su controlador.');
$assert(str_contains($controller, 'public function pacienteEditarNotaSubsecuente('), 'El controlador de edición debe conservarse.');
$assert(str_contains($controller, 'assertPatientAccess'), 'La autorización del paciente debe conservarse.');
$assert(str_contains($model, 'getForPatientReference'), 'El modelo debe conservar la consulta autorizada de edición.');
$assert(str_contains($model, 'editarNotaSubsecuente'), 'El modelo debe conservar la actualización existente.');

$assert(str_contains($list, 'ACCIONES'), 'La columna ACCIONES debe permanecer.');
$assert(!str_contains($list, 'data-action-toggle'), 'El toggle del dropdown debe desaparecer del listado.');
$assert(!str_contains($list, 'doctor-note-actions-menu'), 'El menú desplegable debe desaparecer del listado.');
$assert(!str_contains($list, 'doctor-note-actions-item'), 'Los elementos del menú deben desaparecer del listado.');
$assert(!str_contains($list, '>Editar</span>'), 'Editar no debe ofrecerse como acción del listado.');
$assert(!str_contains($list, '>Detalle</span>'), 'La etiqueta antigua Detalle no debe permanecer.');
$assert(!str_contains($list, 'openActionMenu'), 'El JavaScript del dropdown debe eliminarse.');
$assert(!str_contains($list, 'getBoundingClientRect'), 'El posicionamiento del dropdown debe eliminarse.');
$assert(!str_contains($list, '/editar/'), 'La URL de edición no debe exponerse en el listado.');
$assert(substr_count($list, 'class="btn btn-sm doctor-note-detail-action"') === 1, 'Debe existir un único botón de detalle por plantilla.');
$assert(substr_count($list, '>Ver detalle</span>') === 1, 'La plantilla debe mostrar una única etiqueta Ver detalle.');
$assert(str_contains($list, 'data-feather="file-text"'), 'El botón debe conservar el icono de detalle.');
$assert(str_contains($list, 'aria-label="Ver detalle de la nota <?=$registroId;?>"'), 'El botón debe conservar un nombre accesible por registro.');
$assert(str_contains($list, 'clinica/nota-subsecuente/'), 'El botón debe conservar el endpoint de detalle.');
$assert(str_contains($list, 'fixedHeight: false'), 'La tabla debe conservar altura natural.');
$assert(str_contains($list, 'Nueva nota subsecuente'), 'La acción de nueva nota debe permanecer.');
$assert(!str_contains($list, 'notaSubsecuenteForm'), 'El listado no debe contener un formulario de edición/alta.');

$assert(str_contains($css, '.doctor-note-detail-action'), 'Debe existir CSS scoped para el botón de detalle.');
$assert(str_contains($css, '.doctor-note-detail-action {'), 'Debe existir una regla base del botón de detalle.');
$assert(str_contains($css, 'background-color: #fff;'), 'El estado normal debe tener fondo blanco.');
$assert(str_contains($css, '.doctor-note-detail-action:hover'), 'Debe existir estado hover scoped.');
$assert(str_contains($css, '.doctor-note-detail-action:active'), 'Debe existir estado active scoped.');
$assert(str_contains($css, 'background-color: var(--clinic-primary);'), 'Hover/active debe usar el morado institucional.');
$assert(str_contains($css, 'color: #fff;'), 'Hover/active debe usar texto blanco.');
$assert(str_contains($css, '.doctor-note-detail-action:focus-visible'), 'Debe existir foco visible.');
$assert(str_contains($css, '.doctor-note-detail-action svg'), 'El icono debe heredar el color del control.');
$assert(!str_contains($css, '.doctor-note-actions-menu'), 'No debe quedar CSS del menú desplegable.');

$assert(str_contains($create, 'notaSubsecuenteForm'), 'La vista independiente de creación debe permanecer.');
$assert(str_contains($editor, 'data-id-nota'), 'El editor debe conservar el identificador de edición.');
$assert(str_contains($controller, 'validCaptureCsrf'), 'La protección CSRF debe conservarse.');
$assert(str_contains($model, 'beginTransaction'), 'La transacción de Phase 07 debe conservarse.');

echo "TH_DOCTOR_SUBSEQUENT_NOTE_DETAIL_BUTTON_FIX_08D: PASS_STATIC\n";
