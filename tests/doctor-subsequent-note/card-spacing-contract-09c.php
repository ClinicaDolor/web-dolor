<?php

declare(strict_types=1);

/**
 * Contrato estático del espaciado de tarjetas de Nueva nota subsecuente.
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
$css = $read('public/assets/css/app.css');

$assert(substr_count($editor, 'doctor-note-entry-primary') === 1, 'Debe existir un contenedor primario único.');
$assert(substr_count($editor, 'doctor-note-entry-related') === 1, 'Debe existir un contenedor relacionado único.');
$assert(str_contains($editor, 'doctor-note-recipe-card'), 'Debe conservarse la tarjeta de Receta médica.');
$assert(str_contains($editor, 'doctor-note-lab-card'), 'Debe conservarse la tarjeta de Laboratorio.');
$assert(str_contains($editor, 'doctor-note-appointment-card'), 'Debe conservarse la tarjeta de Próxima cita.');
$assert(substr_count($editor, 'id="mensaje"') === 1, 'El mensaje clínico debe conservar un único contenedor.');

$start = strpos($css, '/* TH-DOCTOR-SUBSEQUENT-NOTE-CARD-SPACING-FIX-09C:');
$end = strpos($css, '/* TH-DOCTOR-SUBSEQUENT-NOTE-ACTION-BUTTONS-REMEDIATION-09D:', $start === false ? 0 : $start);
$assert($start !== false && $end !== false, 'Debe existir un bloque CSS scoped para 09C.');
$block = substr($css, $start, $end - $start);

$assert(str_contains($block, 'min-height: 0;'), 'El feedback no debe reservar altura vacía.');
$assert(str_contains($block, 'margin: 0;'), 'El feedback no debe acumular margen con el gap.');
$assert(str_contains($block, '#mensaje:empty'), 'El feedback vacío debe salir del flujo de layout.');

$layoutStart = strpos($css, '/* TH-DOCTOR-SUBSEQUENT-NOTE-CREATE-UX-REDESIGN-09:');
$layoutEnd = strpos($css, '/* UI-CLOSE-D09:', $layoutStart === false ? 0 : $layoutStart);
$assert($layoutStart !== false && $layoutEnd !== false, 'Debe existir el bloque de layout de creación.');
$layout = substr($css, $layoutStart, $layoutEnd - $layoutStart);
$assert(str_contains($layout, 'gap: 1.5rem;'), 'El espaciado de escritorio debe ser de 24 px.');
$assert(str_contains($layout, 'row-gap: 1rem;'), 'El espaciado móvil debe reducirse a 16 px.');
$assert(!str_contains($block, 'margin-left: -'), 'No deben utilizarse márgenes negativos.');
$assert(!str_contains($block, 'margin-top: -'), 'No deben utilizarse compensaciones negativas.');

$assert(str_contains($css, 'TH-DOCTOR-SUBSEQUENT-NOTE-ACTION-BUTTONS-REMEDIATION-09D'), 'La barra 09D debe seguir presente.');
$assert(str_contains($css, 'min-height: 44px;'), 'Las proporciones de botones 09D deben conservarse.');

echo "TH_DOCTOR_SUBSEQUENT_NOTE_CARD_SPACING_FIX_09C: PASS_STATIC\n";
