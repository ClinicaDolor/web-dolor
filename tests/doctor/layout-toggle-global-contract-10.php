<?php

declare(strict_types=1);

/**
 * Contrato estático de limpieza global del control Cambiar distribución.
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

$loader = $read('public/assets/js/loader.js');
$css = $read('public/assets/css/app.css');

$functionalViews = [
    'app/views/clinica/recetas.php' => 'doctor-recipes-toggle',
    'app/views/clinica/receta-externo.php' => 'doctor-external-recipe-layout-toggle',
    'app/views/clinica/nota-subsecuente.php' => 'doctor-followup-toggle',
];

$removedViews = [
    'app/views/clinica/pacientes-detalle.php',
    'app/views/clinica/pacientes-cofepris.php',
    'app/views/clinica/pacientes-laboratorio.php',
    'app/views/clinica/pacientes-nota-subsecuente.php',
    'app/views/clinica/pacientes-nota-subsecuente-nueva.php',
    'app/views/clinica/pacientes-recetas.php',
    'app/views/clinica/recetas-nuevas.php',
];

$assert(substr_count($loader, 'window.toggleSize = function toggleSize()') === 1, 'Debe existir una única función global toggleSize().');
$assert(str_contains($loader, "col.classList.toggle('col-sm-12')"), 'toggleSize() debe seguir alternando el ancho de las columnas.');
$assert(!str_contains($loader, 'localStorage'), 'toggleSize() no debe introducir persistencia de layout no existente.');
$assert(!str_contains($loader, 'sessionStorage'), 'toggleSize() no debe introducir persistencia de layout no existente.');

foreach ($functionalViews as $relative => $class) {
    $view = $read($relative);
    $assert(substr_count($view, 'onclick="toggleSize()"') === 1, $relative . ' debe conservar su control funcional.');
    $assert(str_contains($view, 'class="btn ' . $class . '"'), $relative . ' debe conservar su clase específica.');
    $assert(substr_count($view, 'resizable') >= 2, $relative . ' debe conservar sus columnas redimensionables.');
}

foreach ($removedViews as $relative) {
    $view = $read($relative);
    $assert(!str_contains($view, 'onclick="toggleSize()"'), $relative . ' no debe conservar un toggle sin efecto.');
    $assert(!str_contains($view, 'id="toggleButton"'), $relative . ' no debe conservar el botón eliminado.');
    $assert(!str_contains($view, 'id="toggleIcon"'), $relative . ' no debe conservar el icono huérfano.');
    $assert(!str_contains($view, 'resizable'), $relative . ' no debe conservar una clase resizable sin consumidor.');
}

foreach ([
    'patient-record-layout-toggle',
    'doctor-patient-cofepris-toggle',
    'doctor-lab-toggle',
    'doctor-note-toggle',
    'doctor-recipe-toggle',
] as $removedClass) {
    $assert(!str_contains($css, $removedClass), 'No deben quedar estilos huérfanos para .' . $removedClass . '.');
}

foreach (array_values($functionalViews) as $preservedClass) {
    $assert(str_contains($css, $preservedClass), 'Debe conservarse CSS para .' . $preservedClass . '.');
}

$assert(str_contains($css, 'body.app-shell-body .patient-record-header-actions .btn'), 'El encabezado de expediente debe conservar una regla de distribución para su acción restante.');

echo "TH_DOCTOR_LAYOUT_TOGGLE_GLOBAL_AUDIT_AND_CLEANUP_10: PASS_STATIC\n";
