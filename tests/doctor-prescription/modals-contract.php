<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$viewPath = $root . '/app/views/clinica/pacientes-recetas.php';
$cssPath = $root . '/public/assets/css/app.css';
$view = file_get_contents($viewPath);
$css = file_get_contents($cssPath);
$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(is_string($view) && is_string($css), 'prescription view and CSS must be readable');

if (is_string($view)) {
    foreach (['agregar-receta-modal', 'detalle-receta-modal', 'modalImprimirReceta'] as $modalId) {
        $assert(substr_count($view, 'id="' . $modalId . '"') === 1, $modalId . ' must exist exactly once');
        $modalPattern = '/id="' . preg_quote($modalId, '/') . '" class="doctor-recipe-modal[^"]*" role="dialog"/';
        $assert((bool)preg_match($modalPattern, $view), $modalId . ' must be an accessible dialog');
    }

    foreach (['Diagnostico', 'TA', 'FC', 'SpO2', 'Temperatura', 'snow', 'mensaje'] as $fieldId) {
        $assert(substr_count($view, 'id="' . $fieldId . '"') === 1, $fieldId . ' must remain unique');
    }

    $assert(str_contains($view, 'id="openAgregarRecetaButton"'), 'list header must expose the create-modal trigger');
    $assert(str_contains($view, 'id="closeAgregarRecetaButton"') && str_contains($view, 'id="closeDetalleRecetaButton"'), 'both modal close controls must remain');
    $assert(substr_count($view, 'aria-label="Cerrar"') === 3, 'all custom modals must have one named close control');
    $assert(!str_contains($view, 'doctor-recipe-support-row'), 'permanent support row must be removed');
    $assert(!str_contains($view, 'doctor-recipe-detail-card'), 'permanent detail card must be removed');
    $assert(!str_contains($view, 'doctor-recipe-create-card'), 'permanent create card must be removed');
    $assert(str_contains($view, 'fixedHeight: false'), 'Simple-DataTables must keep natural table height');
    $assert(str_contains($view, 'searchable: true'), 'table search must remain enabled');
    $assert(str_contains($view, 'perPageSelect: false'), 'table pagination contract must remain');
    $assert(str_contains($view, 'function tableRecetas') && str_contains($view, 'function DetalleReceta'), 'existing table and detail functions must remain');
    $assert(str_contains($view, 'imprimirReceta(recordId, \'registrado\')'), 'existing print flow must remain available');
    $assert(str_contains($view, 'removeAttribute(\'onclick\')'), 'legacy AJAX row handlers must be normalized before binding');
    $assert(str_contains($view, 'data-receta-id='), 'rows must retain the real record identifier');
    $assert(str_contains($view, 'aria-haspopup\', \'dialog\''), 'row detail trigger must expose dialog semantics');
    $assert(str_contains($view, 'const requestId = ++recetaDetalleRequest'), 'detail requests must have an order guard');
    $assert(str_contains($view, 'resultsContainer.textContent = \'\';'), 'stale detail content must be cleared before a request');
    $assert(str_contains($view, 'status.textContent = \'No fue posible cargar el detalle de la receta.\';'), 'detail failures must clear content and report a controlled status');
    $assert(str_contains($view, 'X-M03-CSRF'), 'existing CSRF header must remain');
    $assert(str_contains($view, 'setRecetaSavingState(true)') && str_contains($view, 'setRecetaSavingState(false)'), 'double-submit protection must remain');
    $assert(str_contains($view, 'window.confirm(\'Hay información sin guardar.'), 'closing a changed create form must warn');
}

if (is_string($css)) {
    $modalRule = 'body.app-shell-body.doctor-patient-recipes-page .doctor-recipe-modal';
    $assert(str_contains($css, $modalRule), 'modal CSS must be scoped to the prescription page');
    $assert(str_contains($css, 'doctor-recipe-modal-open'), 'modal scroll lock must be scoped to the prescription page');
    $assert(str_contains($css, 'min-height: 44px'), 'create action must retain a touch-sized target');
    $assert(str_contains($css, 'width: 44px') && str_contains($css, 'height: 44px'), 'modal close controls must retain touch-sized targets');
    $assert(str_contains($css, '@media (max-width: 767px)'), 'modal CSS must include the responsive layout');
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Prescription modal contract: PASS" . PHP_EOL;
