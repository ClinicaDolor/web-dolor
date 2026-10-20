<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/clinica/pacientes-cofepris.php');
$routes = file_get_contents($root . '/app/Config/routes.php');

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(str_contains($view, 'class="doctor-patient-cofepris-edit"'), 'the edit link must remain present');
$assert(str_contains($view, 'tbody a[href*="/editar/"]') && str_contains($view, "link.classList.add('doctor-patient-cofepris-edit')"), 'redrawn DataTables edit links must receive the delegated edit class');
$assert(str_contains($view, 'event.preventDefault();') && str_contains($view, 'abrirEdicionCofepris(editLink)'), 'normal edit clicks must stay in the listing document');
$assert(str_contains($view, 'fetch(link.href') && str_contains($view, 'new DOMParser()'), 'in-place edit loading must reuse the existing edit route');
$assert(str_contains($view, 'cofeprisFormFieldIds') && str_contains($view, 'parsed.getElementById(fieldId)'), 'edit data must be copied into existing controls');
$assert(!str_contains($view, 'document.importNode') && !str_contains($view, 'content.replaceChildren'), 'untrusted edit response HTML must not be inserted into the modal');
$assert(str_contains($view, 'data-direct-edit') && str_contains($view, 'data-listing-url'), 'direct-edit context must be distinguishable from in-list editing');
$assert(str_contains($view, 'window.history.replaceState'), 'direct edit close must normalize only its own URL without adding a history entry');
$assert(!str_contains($view, 'window.history.back()') && !str_contains($view, 'history.go(-2)'), 'internal modal close must not navigate the history');
$assert(str_contains($view, 'cerrarCofeprisFormModal') && str_contains($view, 'closeCofeprisFormButton'), 'the existing close flow must remain');
$assert(str_contains($view, 'openNuevoCofeprisButton') && str_contains($view, 'detalle-cofepris-modal'), 'create and detail modals must remain present');
$assert(str_contains($routes, "'/cofepris/paciente/{idPaciente}/editar/{idCofepris}'"), 'the direct edit route must remain registered');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "COFEPRIS browser history fix contract: PASS" . PHP_EOL;
