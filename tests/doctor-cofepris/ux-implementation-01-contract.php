<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$viewPath = $root . '/app/views/clinica/pacientes-cofepris.php';
$cssPath = $root . '/public/assets/css/app.css';
$labPath = $root . '/app/views/clinica/pacientes-laboratorio.php';
$recipePath = $root . '/app/views/clinica/pacientes-recetas.php';
$view = file_get_contents($viewPath);
$css = file_get_contents($cssPath);
$lab = file_get_contents($labPath);
$recipe = file_get_contents($recipePath);
$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(is_string($view) && is_string($css) && is_string($lab) && is_string($recipe), 'COFEPRIS and reference files must be readable');

if (is_string($view)) {
    $assert(str_contains($view, 'class="btn doctor-page-hero-action"'), 'back action must reuse the institutional hero action class');
    $assert(str_contains($view, '<i data-feather="arrow-left" aria-hidden="true"></i>') && str_contains($view, '<span>Volver al expediente</span>'), 'back action icon and label must match the approved pattern');
    $assert(!str_contains($view, 'Volver expediente'), 'legacy shortened back label must be absent');
    $assert(!str_contains($view, '310'), 'patient 310 must not be hardcoded');
    $assert(str_contains($view, 'SERVIDOR?>clinica/paciente/<?=$data[\'idPaciente\'];?>'), 'dynamic expediente destination must be preserved');

    $assert(str_contains($view, 'doctor-patient-cofepris-patient-strip'), 'compact patient strip must be present');
    $assert(str_contains($view, 'Expediente #<?=htmlspecialchars((string)(int)$data[\'idPaciente\']'), 'patient expediente number must remain dynamic');
    $assert(str_contains($view, 'col-12 resizable'), 'COFEPRIS list must use the full available column');
    $assert(str_contains($view, 'REGISTROS DISPONIBLES'), 'institutional records eyebrow must be present');
    $assert(str_contains($view, 'id="cofeprisCountValue"') && str_contains($view, '$cofeprisCount'), 'record counter must remain dynamic');
    $assert(str_contains($view, 'id="cofeprisEmptyState"') && str_contains($view, 'No hay registros COFEPRIS disponibles.'), 'empty state must be explicit and patient-scoped');

    $assert(str_contains($view, 'searchable: true'), 'table search must remain enabled');
    $assert(str_contains($view, 'fixedHeight: false'), 'table must keep natural height');
    $assert(str_contains($view, 'perPageSelect: false'), 'table pagination configuration must remain');
    $assert(str_contains($view, 'prepararFilasCofepris'), 'table rows must retain keyboard/detail handling after AJAX replacement');
    $assert(str_contains($view, 'href="<?=$cofeprisEditUrl;?>"'), 'record edit destination must remain dynamic');

    foreach (['cofepris-form-modal', 'detalle-cofepris-modal', 'openNuevoCofeprisButton', 'closeCofeprisFormButton', 'closeDetalleCofeprisButton'] as $id) {
        $assert(substr_count($view, 'id="' . $id . '"') === 1, $id . ' must exist exactly once');
    }

    foreach (['Fecha', 'Diagnostico', 'Medicamento', 'NumCajas', 'Presentacion', 'Dosificacion', 'NumDias', 'ViaAdministracion'] as $id) {
        $assert(substr_count($view, 'id="' . $id . '"') === 1, $id . ' must remain unique');
    }

    $assert(substr_count($view, 'aria-label="Cerrar"') === 2, 'both COFEPRIS modals must expose one accessible close control');
    $assert(str_contains($view, 'function abrirCofeprisFormModal') && str_contains($view, 'function cerrarCofeprisFormModal'), 'create modal open/close flow must remain local');
    $assert(str_contains($view, 'function DetalleCofepris') && str_contains($view, 'function cerrarDetalleCofepris'), 'detail modal open/close flow must remain local');
    $assert(str_contains($view, "event.key !== 'Escape'"), 'Escape handling must remain present');
    $assert(str_contains($view, 'function AgregarCofepris') && str_contains($view, '/clinica/paciente/insert-cofepris'), 'existing create endpoint and function must remain');
    $assert(str_contains($view, '/buscar/cofepris/') && str_contains($view, '/buscar/tabla-cofepris/'), 'existing detail and table endpoints must remain');
    $assert(str_contains($view, '/pdf/cofepris/'), 'existing COFEPRIS PDF flow must remain');
    $assert(str_contains($view, 'new CofeprisModel()'), 'existing detail data source must remain');
}

if (is_string($css)) {
    $assert(str_contains($css, 'TH-DOCTOR-COFEPRIS-UX-IMPLEMENTATION-01'), 'new CSS must be explicitly scoped to this implementation');
    $assert(str_contains($css, 'body.app-shell-body.doctor-patient-cofepris-page .doctor-page-hero-action'), 'COFEPRIS back action CSS must be page-scoped');
    $assert(str_contains($css, 'min-height: 44px;'), 'back and action controls must retain touch-sized height');
    $assert(str_contains($css, 'doctor-patient-cofepris-modal-close'), 'modal close styling must be scoped');
    $assert(str_contains($css, '@media (max-width: 767px)'), 'responsive COFEPRIS rules must be present');
}

if (is_string($lab) && is_string($recipe)) {
    $assert(str_contains($lab, '<span>Volver al expediente</span>') && str_contains($recipe, '<span>Volver al expediente</span>'), 'reference modules must expose the approved back label');
    $assert(str_contains($lab, 'data-feather="arrow-left"') && str_contains($recipe, 'data-feather="arrow-left"'), 'reference modules must expose the approved back icon');
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "COFEPRIS UX implementation contract: PASS" . PHP_EOL;
