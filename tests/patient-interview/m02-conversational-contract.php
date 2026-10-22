<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$adapter = file_get_contents($root . '/app/Services/ConversationalM02Adapter.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$routes = file_get_contents($root . '/app/Config/routes.php');
$finalization = file_get_contents($root . '/app/Services/ConversationalModuleFinalizationService.php');
$doctorModel = file_get_contents($root . '/app/Models/AntecedenteFamiliarModel.php');
$captureStart = strpos($adapter, 'public function capture(');
$editStart = strpos($adapter, 'public function editExistingResponse(');
$captureBody = ($captureStart !== false && $editStart !== false) ? substr($adapter, $captureStart, $editStart - $captureStart) : '';

$assertions = [
    str_contains($adapter, 'pc_antecedentes_familiares'),
    str_contains($adapter, 'FIXED_DISEASES'),
    str_contains($adapter, 'Tipo 1'),
    str_contains($adapter, 'M02_FINALIZED'),
    str_contains($adapter, 'public function capture'),
    $captureBody !== '' && !str_contains($captureBody, 'INSERT INTO pc_antecedentes_familiares_parentescos') && !str_contains($captureBody, 'DELETE FROM pc_antecedentes_familiares_parentescos'),
    str_contains($adapter, 'UPDATE pc_antecedentes_familiares SET detalle=:detail'),
    str_contains($controller, 'ConversationalM02Adapter'),
    str_contains($routes, "'/entrevista/capturar-m02', ['PatientInterviewController', 'captureM02']"),
    str_contains($view, 'data-module="M02"'),
    str_contains($view, 'data-m02-interpret'),
    str_contains($adapter, "'readback' => true"),
    str_contains($controller, "'readback' => \$result['readback'] ?? false"),
    str_contains(file_get_contents($root . '/app/Services/ConversationalM04Adapter.php'), 'pc_antecedentes_quirurgicos'),
    str_contains($adapter, 'SELECT DATABASE()') && str_contains($adapter, 'clinica_dolor_demo_20261010_final'),
    str_contains($adapter, 'GET_LOCK(:lock_name, 5)') && str_contains($adapter, 'FOR UPDATE'),
    str_contains($adapter, "'relatives' => \$relatives") && str_contains($adapter, "'relative_description' => \$legacyText"),
    str_contains($finalization, "\$record['legacy_relative_text'] ?? ''") && str_contains($finalization, "'duplicate_diseases'"),
    str_contains($doctorModel, 'structuredRelativesFor') && str_contains($doctorModel, 'requiere un editor compatible'),
    str_contains($controller, 'assertPatientAccess((int)$patientId, $auth)') && str_contains($controller, 'validCaptureCsrf'),
];

if (in_array(false, $assertions, true)) {
    fwrite(STDERR, "M02 conversational contract: FAIL\n" . json_encode($assertions) . "\n");
    exit(1);
}

echo 'M02 conversational contract: PASS (' . count($assertions) . " assertions; no automatic writes)\n";
