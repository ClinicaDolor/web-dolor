<?php

declare(strict_types=1);

/**
 * Contrato estático de las acciones finales de Nueva nota subsecuente.
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
$assert(substr_count($editor, 'data-note-exit') === 1, 'Cancelar debe conservar un único enlace funcional.');
$assert(substr_count($editor, 'onclick="AgregarNota()"') === 1, 'Guardar debe conservar un único evento.');
$assert(str_contains($editor, 'class="btn btn-outline-primary" data-note-exit'), 'Cancelar debe conservar su variante secundaria.');
$assert(str_contains($editor, 'class="btn btn-primary" onclick="AgregarNota()"'), 'Guardar debe conservar su variante principal.');
$assert(str_contains($editor, 'FechaProximaCita'), 'La barra debe permanecer después de Próxima cita.');
$assert(str_contains($view, 'data-note-exit'), 'La protección de cambios sin guardar debe seguir enlazada.');
$assert(str_contains($view, 'notaGuardando = true'), 'La prevención de doble envío debe conservarse.');
$assert(str_contains($view, 'button.disabled = true'), 'El estado disabled de guardado debe conservarse.');
$assert(str_contains($view, "button.setAttribute('aria-busy', 'true')"), 'El estado loading debe conservarse.');

$start = strpos($css, '/* TH-DOCTOR-SUBSEQUENT-NOTE-ACTION-BUTTONS-REMEDIATION-09D:');
$end = strpos($css, '/* TH-DOCTOR-SUBSEQUENT-NOTE-CREATE-UI-REMEDIATION-09A:', $start === false ? 0 : $start);
$assert($start !== false && $end !== false, 'Debe existir un bloque CSS scoped para 09D.');
$block = substr($css, $start, $end - $start);

$assert(str_contains($block, 'body.app-shell-body.doctor-note-create-page'), 'Los botones deben usar selectores scoped.');
$assert(str_contains($block, 'min-height: 44px;'), 'Ambos botones deben compartir una altura táctil uniforme.');
$assert(str_contains($block, 'padding: .6rem 1rem;'), 'Ambos botones deben compartir padding.');
$assert(str_contains($block, 'border-radius: 10px;'), 'Ambos botones deben compartir radio.');
$assert(str_contains($block, 'background: #fff;'), 'Cancelar debe iniciar con fondo blanco.');
$assert(str_contains($block, 'background: var(--clinic-primary);'), 'Guardar debe iniciar con fondo morado.');
$assert(str_contains($block, 'color: #fff;'), 'Los estados morados deben usar texto blanco.');
$assert(str_contains($block, 'stroke: currentColor;'), 'El icono debe seguir el color del botón.');
$assert(str_contains($block, ':focus-visible'), 'Debe existir un estado de foco visible.');
$assert(str_contains($block, '[aria-busy="true"]'), 'Debe conservarse un estado de carga estable.');
$assert(str_contains($block, 'width: 100%;'), 'La barra de acciones debe ocupar el ancho disponible.');
$assert(!preg_match('/(?<!body\.app-shell-body\.doctor-note-create-page\s)\.doctor-note-form-actions/', $block), 'No deben introducirse selectores globales para la barra.');

echo "TH_DOCTOR_SUBSEQUENT_NOTE_ACTION_BUTTONS_REMEDIATION_09D: PASS_STATIC\n";
