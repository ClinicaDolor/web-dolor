<?php

declare(strict_types=1);

/**
 * Contrato estático del sidebar global colapsable.
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

$sidebar = $read('app/Helpers/Sidebar.php');
$main = $read('public/assets/js/main.js');
$css = $read('public/assets/css/app.css');
$loader = $read('public/assets/js/loader.js');
$interview = $read('app/views/paciente/entrevista.php');

$assert(str_contains($sidebar, 'id="sidebar" class="active"'), 'El sidebar compartido debe conservar su contenedor y estado inicial expandido.');
$assert(str_contains($sidebar, 'class="sidebar-toggler btn x"'), 'El sidebar debe conservar el control de cierre móvil.');
$assert(str_contains($sidebar, 'aria-label="'), 'Cada enlace debe conservar un nombre accesible derivado de su etiqueta.');
$assert(str_contains($sidebar, 'title="'), 'Cada enlace debe conservar tooltip nativo en modo compacto.');
$assert(str_contains($main, 'sidebar-compact'), 'El JS debe alternar el estado compacto de escritorio.');
$assert(str_contains($main, 'aria-pressed'), 'El control debe exponer el estado compacto accesiblemente.');
$assert(str_contains($main, 'aria-expanded'), 'El control debe conservar aria-expanded para el drawer móvil.');
$assert(str_contains($main, 'window.addEventListener(\'resize\''), 'El shell debe sincronizarse al cambiar entre escritorio y móvil.');
$assert(!str_contains($main, 'localStorage') && !str_contains($main, 'sessionStorage'), 'No debe introducirse persistencia de layout no existente.');
$assert(str_contains($css, '#sidebar.sidebar-compact .sidebar-wrapper'), 'El CSS debe definir la variante compacta del sidebar.');
$assert(str_contains($css, '#sidebar.sidebar-compact ~ #main'), 'El contenido debe refluir al ancho compacto.');
$assert(str_contains($css, 'width: 84px'), 'El ancho compacto debe permanecer dentro del rango institucional aprobado.');
$assert(str_contains($css, 'clip-path: inset(50%)'), 'Las etiquetas deben ocultarse visualmente sin perder accesibilidad.');
$assert(substr_count($loader, 'window.toggleSize = function toggleSize()') === 1, 'toggleSize() debe permanecer intacta y única.');
$assert(str_contains($interview, 'class="patient-interview-sidebar-toggle sidebar-toggler"'), 'Entrevista debe reutilizar el control compartido del sidebar.');
$assert(str_contains($interview, 'main.js'), 'Entrevista debe cargar el controlador compartido del sidebar.');
$assert(!str_contains($interview, 'is-compact'), 'Entrevista no debe conservar un estado compacto paralelo.');
$assert(!str_contains($interview, 'data-interview-sidebar-toggle'), 'Entrevista no debe conservar un toggle paralelo sin consumidor.');

echo "TH_CLINICA_GLOBAL_SIDEBAR_COLLAPSIBLE_IMPLEMENTATION_11: PASS_STATIC\n";
