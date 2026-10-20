<?php

declare(strict_types=1);

/** Pure PHP CLI probe. It never loads configuration and never opens MySQL. */
$received = array_values(array_slice($argv, 1));
$expectedFunctional = [
    '--database=clinica_dolor_demo_20261010_final',
    '--patient-a=9301',
    '--patient-b=9302',
];
$receivedFunctional = array_slice($received, 0, 3);
$receivedExtra = array_slice($received, 3);
$extraAllowed = $receivedExtra === []
    || $receivedExtra === ['--json=1']
    || $receivedExtra === ['--label=synthetic value'];

$database = '';
foreach ($received as $argument) {
    if (str_starts_with($argument, '--database=')) {
        $database = substr($argument, strlen('--database='));
        break;
    }
}

$argumentsIndependent = $receivedFunctional === $expectedFunctional && $extraAllowed;
echo json_encode([
    'status' => $argumentsIndependent ? 'PASS_CLI_ARGUMENTS' : 'FAIL_CLI_ARGUMENTS',
    'database' => $database,
    'argument_count' => count($received),
    'functional_argument_count' => count($receivedFunctional),
    'arguments_independent' => $argumentsIndependent,
    'database_argument_exact' => $receivedFunctional[0] === $expectedFunctional[0],
    'patient_a_argument_exact' => $receivedFunctional[1] === $expectedFunctional[1],
    'patient_b_argument_exact' => $receivedFunctional[2] === $expectedFunctional[2],
    'patient_arguments_exact' => $receivedFunctional === $expectedFunctional,
    'order_exact' => $receivedFunctional === $expectedFunctional,
    'trailing_runner_argument_allowed' => $extraAllowed,
    'spaced_value_exact' => $receivedExtra === ['--label=synthetic value'],
    'mysql_executed' => 'NO',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($argumentsIndependent ? 0 : 1);

