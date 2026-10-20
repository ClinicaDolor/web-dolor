<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/clinica/pacientes-cofepris.php');
$lab = file_get_contents($root . '/app/views/clinica/pacientes-laboratorio.php');
$recipe = file_get_contents($root . '/app/views/clinica/pacientes-recetas.php');
$css = file_get_contents($root . '/public/assets/css/app.css');
$routes = file_get_contents($root . '/app/Config/routes.php');

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(str_contains($view, 'event.preventDefault();') && str_contains($view, 'abrirEdicionCofepris(editLink)'), 'edit from the listing must stay in the current document');
$assert(str_contains($view, 'fetch(link.href') && str_contains($view, 'new DOMParser()'), 'the existing edit route must supply data for the internal modal');
$assert(str_contains($view, 'parsed.getElementById(fieldId)') && str_contains($view, 'targetField.value'), 'edit data must populate existing controls without HTML injection');
$assert(!str_contains($view, 'window.history.back()') && !str_contains($view, 'history.go(-2)'), 'internal modal close must not navigate the history');
$assert(str_contains($view, 'window.history.replaceState'), 'direct edit close must have a safe listing fallback');
$assert(str_contains($view, "'/cofepris/paciente/{idPaciente}/editar/{idCofepris}'") === false, 'route declaration is not expected inside the view');
$assert(str_contains($routes, "'/cofepris/paciente/{idPaciente}/editar/{idCofepris}'"), 'direct edit route must remain registered');

$buttonMarkup = 'class="btn doctor-page-hero-action"';
$backLabel = '<span>Volver al expediente</span>';
$backIcon = 'data-feather="arrow-left" aria-hidden="true"';
foreach (['COFEPRIS' => $view, 'LABORATORIO' => $lab, 'RECETA' => $recipe] as $name => $source) {
    $assert(str_contains($source, $buttonMarkup), "$name must use the institutional back button class");
    $assert(str_contains($source, $backLabel), "$name must preserve the approved back label");
    $assert(str_contains($source, $backIcon), "$name must use the approved arrow icon");
}

$expectedCss = [
    'min-height: 44px',
    'border: 1px solid var(--clinic-border)',
    'background: #fff',
    'color: var(--clinic-primary-dark)',
    'padding-inline: 1rem',
    'border-color: #c8b0ed',
    'background: var(--clinic-primary-soft)',
];
foreach ($expectedCss as $declaration) {
    $assert(str_contains($css, $declaration), "institutional back button CSS declaration missing: $declaration");
}
$assert(str_contains($css, 'body.app-shell-body.doctor-patient-cofepris-page .doctor-page-hero-action'), 'COFEPRIS back button CSS must remain scoped');
$assert(str_contains($view, 'SERVIDOR?>clinica/paciente/<?=$data[\'idPaciente\'];?>'), 'back destination must remain dynamic by patient');
$assert(!str_contains($view, 'clinica/paciente/310"'), 'back destination must not hardcode patient 310');
$assert(str_contains($view, 'openNuevoCofeprisButton') && str_contains($view, 'detalle-cofepris-modal'), 'create and detail modals must remain present');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "COFEPRIS navigation and back-button contract: PASS" . PHP_EOL;
