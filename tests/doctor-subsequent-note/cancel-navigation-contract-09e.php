<?php

declare(strict_types=1);

/**
 * Contrato estático de la salida explícita Cancelar de Nueva nota subsecuente.
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

$assert(substr_count($editor, 'data-note-cancel') === 1, 'Cancelar debe tener una marca explícita única.');
$assert(substr_count($editor, 'data-note-exit') === 1, 'Cancelar debe conservar el contrato de salida existente.');
$assert(str_contains($editor, 'href="<?=htmlspecialchars((string)($data[\'list_url\'] ?? \'\')'), 'Cancelar debe reutilizar el historial dinámico.');
$cancelStart = strpos($editor, '<a class="btn btn-outline-primary" data-note-exit data-note-cancel');
$cancelEnd = $cancelStart === false ? false : strpos($editor, '</a>', $cancelStart);
$assert($cancelStart !== false && $cancelEnd !== false, 'Debe existir el enlace Cancelar completo.');
$cancelMarkup = substr($editor, $cancelStart, $cancelEnd - $cancelStart + 4);
$assert(str_ends_with($cancelMarkup, '>Cancelar</a>'), 'Cancelar debe conservar su texto visible.');
$assert(!str_contains($cancelMarkup, 'onclick='), 'Cancelar no debe ejecutar un guardado inline.');
$assert(str_contains($view, "querySelectorAll('[data-note-cancel]')"), 'Debe existir un manejador puntual para Cancelar.');
$assert(str_contains($view, 'notaDirty = false;'), 'Cancelar debe desactivar el beforeunload intencionalmente.');
$assert(str_contains($view, "querySelectorAll('[data-note-exit]:not([data-note-cancel])')"), 'Las demás salidas deben conservar la guardia de cambios.');
$assert(str_contains($view, "window.confirm('Hay cambios sin guardar. ¿Deseas salir de esta nota?')"), 'La confirmación debe mantenerse para otras navegaciones protegidas.');
$assert(str_contains($view, "window.addEventListener('beforeunload'"), 'La protección beforeunload debe permanecer instalada.');
$assert(str_contains($view, 'fetch(\'/clinica/paciente/insert-nota-subsecuente\''), 'Guardar debe conservar su endpoint.');
$assert(str_contains($view, 'notaGuardando = true;'), 'Guardar debe conservar su estado de envío.');
$assert(str_contains($view, 'IncluirReceta'), 'La receta opcional debe permanecer disponible.');
$assert(str_contains($view, 'IncluirLaboratorio'), 'El laboratorio opcional debe permanecer disponible.');

echo "TH_DOCTOR_SUBSEQUENT_NOTE_CANCEL_NAVIGATION_FIX_09E: PASS_STATIC\n";
