<?php

declare(strict_types=1);

/**
 * Static and simulated regression for the M07/P09 HTTP harness.
 * It never starts Apache, opens PDO, or sends HTTP requests.
 */

$root = dirname(__DIR__, 2);
$runner = file_get_contents($root . '/tests/m07-http-isolated/run-m07-http-matrix-medium.ps1');
$matrix = file_get_contents($root . '/tests/m07-http-isolated/m07-http-negative-matrix.php');
$stopper = file_get_contents($root . '/tests/m07-http-isolated/stop-m07-apache.ps1');

$assertions = [];
$assert = static function (bool $condition, string $name) use (&$assertions): void {
    $assertions[] = ['name' => $name, 'pass' => $condition];
};

$expectedCases = [
    'patient_access_page',
    'unauthenticated_get_rejected',
    'authentication_a',
    'authentication_b',
    'authenticated_m07_gets',
    'get_requests_no_clinical_write',
    'cross_clinic_patient_get_rejected',
    'cross_get_no_clinical_write',
    'csrf_missing_rejected_without_write',
    'csrf_invalid_rejected_without_write',
    'cross_clinic_post_rejected_without_write',
    'm07_finalization_rejected_without_write',
    'doctor_idPregunta_legacy_rejection',
];

foreach ($expectedCases as $case) {
    $assert(str_contains($runner, "'$case'"), 'runner_inventory_' . $case);
    $assert(str_contains($matrix, "'$case'"), 'matrix_inventory_' . $case);
}

$assert(str_contains($matrix, "'expected_http_operations' => 14"), 'fourteen_http_operations_declared');
$assert(str_contains($matrix, "'http_operations' => []"), 'http_operations_are_reported');
$assert(str_contains($matrix, "'cases_passed' => []"), 'passed_cases_are_reported');
$assert(str_contains($matrix, "'cases_failed' => []"), 'failed_cases_are_reported');
$assert(str_contains($matrix, "'cases_not_reached'"), 'not_reached_cases_are_reported');
$assert(str_contains($matrix, "'case_inventory_reconciled'"), 'inventory_reconciliation_is_reported');
$assert(!str_contains($runner, 'NONE_RECORDED'), 'ambiguous_none_recorded_removed');
$assert(str_contains($runner, 'if ($statusCode -ne 200)'), 'guard_requires_http_200');
$assert(str_contains($runner, "if (\$ExpectPreflightPass -and \$body.Trim() -ne 'PASS')"), 'preflight_requires_complete_pass_body');
$assert(str_contains($runner, "X-M07-Database-Guard"), 'normal_guard_marker_is_checked');
$assert(str_contains($stopper, 'ALREADY_EXITED_CLEAN'), 'already_exited_requires_clean_state');
$assert(str_contains($stopper, 'Get-SyntheticProcessRecords'), 'synthetic_process_tree_is_inspected');
$assert(str_contains($stopper, "LocalAddress '127.0.0.1'"), 'reserved_listener_is_verified');

$reconcile = static function (array $checks, int $operations) use ($expectedCases): array {
    $passed = [];
    $failed = [];
    $notReached = [];
    foreach ($expectedCases as $case) {
        $status = (string)($checks[$case] ?? 'NOT_REACHED');
        if ($status === 'PASS') {
            $passed[] = $case;
        } elseif ($status === 'FAIL') {
            $failed[] = $case;
        } else {
            $notReached[] = $case;
        }
    }
    return [
        'passed' => $passed,
        'failed' => $failed,
        'not_reached' => $notReached,
        'reconciled' => count(array_diff(array_keys($checks), $expectedCases)) === 0,
        'operations' => $operations,
    ];
};

$complete = array_fill_keys($expectedCases, 'PASS');
$partial = array_slice($complete, 0, 5, true);
$failed = $complete;
$failed['doctor_idPregunta_legacy_rejection'] = 'FAIL';

$completeResult = $reconcile($complete, 14);
$partialResult = $reconcile($partial, 5);
$failedResult = $reconcile($failed, 14);
$assert(count($completeResult['passed']) === 13 && count($completeResult['failed']) === 0 && count($completeResult['not_reached']) === 0 && $completeResult['operations'] === 14, 'complete_report_simulation');
$assert(count($partialResult['not_reached']) === 8, 'partial_report_simulation');
$assert(count($failedResult['failed']) === 1 && $failedResult['not_reached'] === [], 'failed_report_simulation');

$failureModes = [
    ['status' => 200, 'body' => 'PASS', 'marker' => '', 'pass' => true],
    ['status' => 200, 'body' => 'PASS parcial', 'marker' => '', 'pass' => false],
    ['status' => 503, 'body' => 'FAIL', 'marker' => 'ENV_POLICY', 'pass' => false],
    ['status' => 404, 'body' => '', 'marker' => '', 'pass' => false],
];
foreach ($failureModes as $index => $mode) {
    $guardPass = $mode['status'] === 200 && trim($mode['body']) === 'PASS' && $mode['marker'] === '';
    $assert($guardPass === $mode['pass'], 'guard_response_simulation_' . $index);
}

$failedAssertions = array_values(array_filter($assertions, static fn(array $item): bool => !$item['pass']));
if ($failedAssertions !== []) {
    fwrite(STDERR, "M07 HTTP harness contract: FAIL\n" . json_encode($failedAssertions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    exit(1);
}

echo 'M07 HTTP harness contract: PASS (' . count($assertions) . " assertions; no HTTP, Apache or database access)\n";
