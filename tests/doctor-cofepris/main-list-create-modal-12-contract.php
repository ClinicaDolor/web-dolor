<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$viewPath = $root . '/app/views/clinica/configuracion-cofepris.php';
$cssPath = $root . '/public/assets/css/app.css';
$routesPath = $root . '/app/Config/routes.php';

$view = file_get_contents($viewPath);
$css = file_get_contents($cssPath);
$routes = file_get_contents($routesPath);
$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(is_string($view) && is_string($css) && is_string($routes), 'COFEPRIS files must be readable');

if (is_string($view)) {
    $assert(str_contains($view, 'doctor-cofepris-config-list-card'), 'the main route must retain one list card');
    $assert(str_contains($view, 'id="openCofeprisConfigModal"'), 'the New record trigger must exist');
    $assert(str_contains($view, 'id="cofepris-config-modal"'), 'the create modal must exist');
    $assert(str_contains($view, 'id="cofepris-folios-form"'), 'the existing folio operation must be represented by one form');
    $assert(!str_contains($view, 'doctor-cofepris-config-entry-card'), 'the permanent create card must be removed');
    $assert(!str_contains($view, 'Agregar folio <'), 'the obsolete permanent-card action must be removed');

    foreach (['Folio', 'Archivo', 'mensaje', 'cofepris-config-modal', 'cofepris-folios-form', 'openCofeprisConfigModal'] as $id) {
        $assert(substr_count($view, 'id="' . $id . '"') === 1, $id . ' must remain unique');
    }

    $assert(str_contains($view, 'id="Folio"') && str_contains($view, 'id="Archivo"'), 'the original fields must remain');
    $assert(str_contains($view, 'accept="application/pdf,.pdf"'), 'the PDF input restriction must remain explicit');
    $assert(str_contains($view, "fetch('/clinica/cofepris/insert-folios'"), 'the existing folio endpoint must be reused');
    $assert(str_contains($view, "fetch(`/buscar/tabla-cofepris-folios`"), 'the existing table endpoint must remain');
    $assert(str_contains($view, 'searchable: true') && str_contains($view, 'fixedHeight: true'), 'table search and existing datatable behavior must remain');
    $assert(str_contains($view, 'function abrirCofeprisConfigModal') && str_contains($view, 'function cerrarCofeprisConfigModal'), 'modal open and close functions must exist');
    $assert(str_contains($view, "event.key === 'Escape'") && str_contains($view, 'cofeprisModalTrigger.focus()'), 'Escape and focus restoration must remain');
    $assert(str_contains($view, 'event.preventDefault();') && str_contains($view, 'AgregarCofepris();'), 'form submission must be handled without navigation');
    $assert(str_contains($view, 'cofeprisFoliosRequestInFlight'), 'duplicate submissions must be guarded');
    $assert(str_contains($view, 'if (!response.ok) throw new Error'), 'HTTP failures must not be treated as success');
    $assert(!str_contains($view, 'idPaciente') && !str_contains($view, 'clinica/cofepris/paciente/'), 'the global folio route must not invent patient context');
    $assert(str_contains($view, 'aria-haspopup="dialog"') && str_contains($view, 'aria-label="Cerrar"'), 'modal controls must expose accessible names');
}

if (is_string($css)) {
    $assert(str_contains($css, 'TH-DOCTOR-COFEPRIS-MAIN-LIST-CREATE-MODAL-12'), 'new CSS must be explicitly marked');
    $assert(str_contains($css, 'body.app-shell-body.doctor-cofepris-config-page .doctor-cofepris-config-new-button'), 'trigger CSS must be page-scoped');
    $assert(str_contains($css, 'body.app-shell-body.doctor-cofepris-config-page .doctor-cofepris-config-modal'), 'modal CSS must be page-scoped');
    $assert(str_contains($css, 'min-height: 44px;') && str_contains($css, ':focus-visible'), 'action controls must retain touch size and focus styling');
}

if (is_string($routes)) {
    $assert(str_contains($routes, "'/cofepris'") && str_contains($routes, "'/cofepris/insert-folios'"), 'the existing main and folio POST routes must remain');
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "COFEPRIS main list/create modal contract: PASS" . PHP_EOL;
