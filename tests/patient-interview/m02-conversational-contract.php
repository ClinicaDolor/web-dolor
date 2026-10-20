<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$adapter = file_get_contents($root . '/app/Services/ConversationalM02Adapter.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$routes = file_get_contents($root . '/app/Config/routes.php');

$assertions = [
    str_contains($adapter, 'pc_antecedentes_familiares'),
    str_contains($adapter, 'FIXED_DISEASES'),
    str_contains($adapter, 'Tipo 1'),
    str_contains($adapter, 'M02_FINALIZED'),
    str_contains($adapter, 'public function capture'),
    !str_contains($adapter, 'INSERT'),
    !str_contains($adapter, 'UPDATE'),
    str_contains($controller, 'ConversationalM02Adapter'),
    str_contains($routes, "'/entrevista/capturar-m02', ['PatientInterviewController', 'captureM02']"),
    str_contains($view, 'data-module="M02"'),
    str_contains($view, 'data-m02-interpret'),
    str_contains($adapter, "'readback' => true"),
    str_contains($controller, "'readback' => \$result['readback'] ?? false"),
    str_contains(file_get_contents($root . '/app/Services/ConversationalM04Adapter.php'), 'pc_antecedentes_quirurgicos'),
];

if (in_array(false, $assertions, true)) {
    fwrite(STDERR, "M02 conversational contract: FAIL\n" . json_encode($assertions) . "\n");
    exit(1);
}

echo 'M02 conversational contract: PASS (' . count($assertions) . " assertions; no automatic writes)\n";
