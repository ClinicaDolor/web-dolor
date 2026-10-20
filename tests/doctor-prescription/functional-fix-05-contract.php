<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/clinica/pacientes-recetas.php');
$css = file_get_contents($root . '/public/assets/css/app.css');
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
    }

    $assert(str_contains($view, 'const container = document.getElementById(\'conteRecetas\');'), 'row delegation must attach to the stable table container');
    $assert(str_contains($view, 'recetaRowsDelegationReady'), 'row delegation must not be registered repeatedly');
    $assert(str_contains($view, 'closest(\'tbody tr[data-receta-id]\')'), 'detail activation must use the selected row record identifier');
    $assert(str_contains($view, 'removeAttribute(\'onclick\')'), 'legacy inline row handlers must be normalized');
    $assert(str_contains($view, 'fetch(`/buscar/receta/${encodeURIComponent(recordId)}`)'), 'detail AJAX endpoint must remain record-specific');
    $assert(str_contains($view, 'const requestId = ++recetaDetalleRequest'), 'detail response ordering guard must remain');
    $assert(str_contains($view, 'resultsContainer.textContent = \'\';'), 'stale detail content must be cleared');
    $assert(str_contains($view, 'function imprimirReceta(id, tipo)'), 'print action must remain globally callable by the detail response');
    $assert(str_contains($view, 'function cargarVistaPreviaReceta(id, tipo)'), 'preview loading must have a dedicated flow');
    $assert(str_contains($view, 'fetch(endpoint, {') && str_contains($view, "'Accept': 'application/pdf'"), 'preview must request the existing PDF endpoint as same-origin PDF');
    $assert(str_contains($view, "method: 'POST'") && str_contains($view, 'con_formato: conFormato'), 'preview must preserve the existing POST format contract');
    $assert(str_contains($view, 'id="recetaPreviewFrame"'), 'preview must render the authorized document in an iframe');
    $assert(str_contains($view, 'function generarPDF()'), 'preview must expose an explicit print action');
    $assert(str_contains($view, 'function cerrarVistaPreviaReceta()'), 'preview must have an independent close flow');
    $assert(str_contains($view, 'recetaPrintReturnModal === \'detail\''), 'closing preview must restore the selected detail when applicable');
    $assert(str_contains($view, 'if (modalActivo === printModal) cerrarVistaPreviaReceta();'), 'Escape must close the active preview modal first');
    $assert(!str_contains($view, "include_once __DIR__ . '/../components/modal-impresion-receta.php'"), 'Bootstrap-only shared print component must not be duplicated on this target page');
    $assert(!str_contains($view, "$('#modalImprimirReceta').modal('show')"), 'preview must not depend on the previous jQuery modal call');
    $assert(substr_count($view, 'aria-label="Cerrar"') === 3, 'all three custom modals must have a named close control');
}

if (is_string($css)) {
    $scope = 'body.app-shell-body.doctor-patient-recipes-page';
    $assert(str_contains($css, $scope . ' .doctor-recipe-print-modal-content'), 'preview CSS must be scoped to the prescription page');
    $assert(str_contains($css, $scope . ' .doctor-recipe-preview-frame-wrap iframe'), 'preview iframe must have dedicated responsive styling');
    $assert(str_contains($css, $scope . ' .doctor-recipe-modal-close'), 'preview must reuse the accessible close treatment');
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Prescription functional fix contract: PASS" . PHP_EOL;
