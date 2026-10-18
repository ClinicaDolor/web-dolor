<?php

declare(strict_types=1);

/**
 * Contrato estático de alineación de las acciones finales de Nueva nota subsecuente.
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

$editor = $read('app/views/clinica/partials/nota-subsecuente-editor.php');
$view = $read('app/views/clinica/pacientes-nota-subsecuente-nueva.php');
$css = $read('public/assets/css/app.css');

$assert(substr_count($editor, 'doctor-note-action-row') === 1, 'La barra inferior debe existir una sola vez.');
$assert(substr_count($editor, 'data-note-cancel') === 1, 'Cancelar debe conservar un único marcador funcional.');
$assert(substr_count($editor, 'onclick="AgregarNota()"') === 1, 'Guardar debe conservar un único evento.');
$assert(str_contains($editor, 'class="btn btn-outline-primary" data-note-exit data-note-cancel'), 'Cancelar debe conservar la navegación 09E.');
$assert(str_contains($editor, 'class="btn btn-primary" onclick="AgregarNota()"'), 'Guardar debe conservar su variante principal.');
$assert(str_contains($editor, 'FechaProximaCita'), 'Las acciones deben permanecer después de Próxima cita.');
$assert(str_contains($view, 'data-note-exit]:not([data-note-cancel])'), 'La protección de salida ajena a Cancelar debe conservarse.');
$assert(str_contains($view, 'notaDirty = false;'), 'Cancelar debe conservar la limpieza de estado 09E.');

$marker = '/* TH-DOCTOR-SUBSEQUENT-NOTE-ACTION-BUTTONS-ALIGNMENT-FIX-09F:';
$start = strpos($css, $marker);
$end = strpos($css, 'body.app-shell-body.doctor-note-create-page .doctor-note-form-actions > [data-note-exit] svg', $start === false ? 0 : $start);
$assert($start !== false && $end !== false, 'Debe existir un bloque CSS scoped para 09F.');
$block = substr($css, $start, $end - $start);

$assert(str_contains($block, 'display: flex;'), 'El wrapper de Guardar debe participar en el alineamiento flex.');
$assert(str_contains($block, 'align-items: stretch;'), 'El wrapper no debe añadir desplazamiento vertical interno.');
$assert(str_contains($block, 'padding: 0;'), 'Debe eliminarse el padding heredado del wrapper de Guardar.');
$assert(str_contains($block, 'box-sizing: border-box;'), 'Los controles deben compartir el modelo de caja.');
$assert(str_contains($block, 'height: 44px;'), 'Ambos controles deben compartir altura exterior.');
$assert(str_contains($block, 'margin: 0;'), 'No deben existir márgenes individuales de compensación.');
$assert(!str_contains($block, 'position: absolute'), 'No debe utilizarse posicionamiento artificial.');
$assert(!str_contains($block, 'transform:'), 'No debe utilizarse transform para compensar la alineación.');

echo "TH_DOCTOR_SUBSEQUENT_NOTE_ACTION_BUTTONS_ALIGNMENT_FIX_09F: PASS_STATIC\n";
