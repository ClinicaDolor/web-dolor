<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$routes = file_get_contents($root . '/app/Config/routes.php');
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');

$assertions = [
    'provider' => str_contains($service, 'interpretConversation'),
    'fallback' => str_contains($service, 'deterministic'),
    'clarification' => str_contains($service, 'needs_clarification'),
    'route' => str_contains($routes, "'/entrevista/interpretar', ['PatientInterviewController', 'interpret']"),
    'direct_route' => str_contains($routes, "'/entrevista/capturar-directo', ['PatientInterviewController', 'captureDirect']"),
    'controller' => str_contains($controller, 'public function interpret'),
    'direct_controller' => str_contains($controller, 'public function captureDirect'),
    'frontend_endpoint' => str_contains($view, 'historia-clinica/entrevista/interpretar'),
    'direct_frontend_endpoint' => str_contains($view, 'historia-clinica/entrevista/capturar-directo'),
    'direct_numeric_gate' => str_contains($view, 'isDirectM03Candidate'),
    'frontend_action' => str_contains($view, 'Interpretar respuesta'),
    'confirmation' => str_contains($view, 'data-proposal-confirm'),
    'explanation' => str_contains($service, "'kind' => 'explanation'"),
];

if (in_array(false, $assertions, true)) {
    fwrite(STDERR, "Conversational engine contract: FAIL\n" . json_encode($assertions) . "\n");
    exit(1);
}

echo 'Conversational engine contract: PASS (' . count($assertions) . " assertions; no database writes)\n";
