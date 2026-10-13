<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$adapter = file_get_contents($root . '/app/Services/ConversationalM02Adapter.php');

$assertions = [
    str_contains($controller, 'filter_var($data[\'fastNo\'] ?? false, FILTER_VALIDATE_BOOLEAN)'),
    str_contains($controller, "'detail' => 'No'"),
    str_contains($controller, "'relative' => null"),
    str_contains($controller, "'diabetes_type' => null"),
    str_contains($view, 'No, guardar y continuar'),
    str_contains($view, 'data-m02-safe-no'),
    str_contains($view, 'dataset.m02Saving'),
    str_contains($view, 'Guardando respuesta negativa'),
    str_contains($view, 'capturar-m02'),
    str_contains($adapter, 'La propuesta negativa no puede incluir datos dependientes.'),
];

if (in_array(false, $assertions, true)) {
    fwrite(STDERR, "M02 fast interaction contract: FAIL\n" . json_encode($assertions) . "\n");
    exit(1);
}

echo 'M02 fast interaction contract: PASS (' . count($assertions) . " assertions; scoped to interview)\n";
