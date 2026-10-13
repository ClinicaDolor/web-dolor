<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$adapter = file_get_contents($root . '/app/Services/ConversationalM01Adapter.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');

$assertions = [
    str_contains($adapter, 'SELECT fecha_nacimiento'),
    str_contains($adapter, "'write_enabled' => false"),
    !str_contains($adapter, 'INSERT'),
    !str_contains($adapter, 'UPDATE'),
    str_contains($controller, 'ConversationalM01Adapter'),
    str_contains($view, 'data-module="M01"'),
    str_contains($view, 'M01 consultado'),
];

if (in_array(false, $assertions, true)) {
    fwrite(STDERR, "M01 conversational contract: FAIL\n");
    exit(1);
}

echo 'M01 conversational contract: PASS (' . count($assertions) . " assertions; read-only)\n";
