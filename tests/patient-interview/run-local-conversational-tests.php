<?php

declare(strict_types=1);

/**
 * Reusable local test entry point for Codex Desktop.
 *
 * Modes:
 *   preflight       Read-only PHP/PDO/schema check, pinned to the approved
 *                   synthetic M07 database by m07-windows-local-preflight.php.
 *   conversational  Runs the preflight, database-free M06/M07 regressions,
 *                   and guarded anonymous GET checks on isolated Apache.
 *
 * No clinical writes, authenticated sessions, browser actions, or M07 activation
 * are launched by this runner. HTTP is limited to anonymous GETs against the
 * existing loopback-only Apache runtime, with the permanent PDO database guard.
 */

$root = dirname(__DIR__, 2);
$mode = strtolower((string)($argv[1] ?? 'conversational'));
$validModes = ['preflight', 'conversational'];
if (!in_array($mode, $validModes, true)) {
    fwrite(STDERR, "Usage: php run-local-conversational-tests.php [preflight|conversational]\n");
    exit(2);
}

$php = (string)(getenv('CLINICA_PHP') ?: PHP_BINARY);
$node = (string)(getenv('CLINICA_NODE') ?: 'node');
$report = [
    'runner' => 'WINDOWS_LOCAL_CONVERSATIONAL_TESTS_V1',
    'mode' => $mode,
    'workspace' => $root,
    'php_binary' => $php,
    'php_version' => PHP_VERSION,
    'pdo_mysql' => extension_loaded('pdo_mysql') ? 'AVAILABLE' : 'MISSING',
    'database' => 'clinica_dolor_demo_20261010_final',
    'gate' => 'CLOSED_BY_DEFAULT',
    'preflight' => 'NOT_RUN',
    'tests' => [],
    'mysql_writes' => 'NO',
    'http' => 'NOT_EXECUTED',
    'browser' => 'NOT_EXECUTED',
    'm07_write_integration' => 'NOT_EXECUTED_GATE_CLOSED_AND_DML_AUTHORIZATION_REQUIRED',
];

$run = static function (array $command, int $timeoutSeconds = 60) use ($root): array {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open($command, $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        return ['exit_code' => 127, 'stdout' => '', 'stderr' => 'PROCESS_START_FAILED'];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + $timeoutSeconds;
    $observedExitCode = null;
    $timedOut = false;

    do {
        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        $status = proc_get_status($process);
        if (!$status['running']) {
            $observedExitCode = (int)$status['exitcode'];
            break;
        }
        if (microtime(true) >= $deadline) {
            $timedOut = true;
            proc_terminate($process);
            usleep(200000);
            $status = proc_get_status($process);
            if ($status['running']) proc_terminate($process, 9);
            break;
        }
        usleep(50000);
    } while (true);

    $stdout .= stream_get_contents($pipes[1]) ?: '';
    $stderr .= stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closeCode = proc_close($process);
    $exitCode = $timedOut ? 124 : ($observedExitCode !== null && $observedExitCode >= 0 ? $observedExitCode : $closeCode);

    return [
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'timed_out' => $timedOut,
    ];
};

$record = static function (string $name, array $result) use (&$report): void {
    $report['tests'][$name] = [
        'status' => $result['exit_code'] === 0 ? 'PASS' : 'FAIL',
        'exit_code' => $result['exit_code'],
        'timed_out' => (bool)($result['timed_out'] ?? false),
        'output' => trim((string)($result['stdout'] ?? '')),
        'error_category' => trim((string)($result['stderr'] ?? '')) === '' ? 'NONE' : 'CHILD_STDERR',
    ];
};

$preflight = $run([$php, $root . '/tests/patient-interview/m07-windows-local-preflight.php'], 45);
$record('read_only_synthetic_preflight', $preflight);
$preflightPayload = json_decode((string)$preflight['stdout'], true);
$preflightPassed = $preflight['exit_code'] === 0
    && is_array($preflightPayload)
    && ($preflightPayload['status'] ?? '') === 'PASS_READ_ONLY_PREFLIGHT'
    && ($preflightPayload['database_effective'] ?? '') === 'clinica_dolor_demo_20261010_final'
    && ($preflightPayload['activation_opt_in'] ?? '') === 'CLOSED_BY_DEFAULT'
    && ($preflightPayload['writes'] ?? '') === 'NO'
    && ($preflightPayload['ddl'] ?? '') === 'NO';
$report['preflight'] = $preflightPassed ? 'PASS_READ_ONLY_SYNTHETIC' : 'BLOCKED_OR_FAILED';
$report['preflight_result'] = is_array($preflightPayload) ? $preflightPayload : [
    'status' => $preflight['timed_out'] ?? false ? 'TIMEOUT' : 'NO_VALID_JSON',
    'failure_code' => 'PREFLIGHT_RESULT_UNAVAILABLE',
];

if ($mode === 'conversational') {
    $phpTests = [
        'piper-m02-manifest-contract.php',
        'm02-free-text-contract.php',
        'm02-previous-responses-edit-contract.php',
        'm02-contextual-help-contract.php',
        'm02-context-memory.php',
        'm02-natural-language-relationship-contract.php',
        'm02-multiple-relatives-contract.php',
        'voice-panel-ui-contract.php',
        'interview-pause-resume-ui-contract.php',
        'm07-conversational-implementation-contract.php',
        'm07-shared-canonical-contract.php',
        'm07-phase-b1-shared-read-contract.php',
        'm07-phase-a-security-contract.php',
        'm06-conversational-flow-contract.php',
        'm06-omission-contract.php',
        'm06-traditional-edit-contract.php',
        'm04-m06-functional-contract.php',
        'unified-conversational-contract.php',
    ];
    foreach ($phpTests as $test) {
        $record($test, $run([$php, $root . '/tests/patient-interview/' . $test], 60));
    }
    $record('local_dictation_ticket_contract', $run([$php, $root . '/tests/voice-stt-03/ticket-contract-test.php'], 30));

    $nodeTests = [
        'piper-m02-audio.test.js',
        'conversation-coordinator.test.js',
        'm07-voice-target-contract.test.js',
        'm06-contextual-buttons.test.js',
        'm06-input-operation-regression.test.js',
        'voice-context-integrity.test.js',
        'voice-autoplay.test.js',
        'voice-selector.test.js',
    ];
    foreach ($nodeTests as $test) {
        $record($test, $run([$node, $root . '/tests/patient-interview/' . $test], 60));
    }
    $record('local_dictation_client_contract', $run([$node, $root . '/tests/voice-stt-03/client-contract-test.cjs'], 30));

    if ($preflightPassed) {
        $httpCommand = [
            'powershell.exe', '-NoLogo', '-NoProfile', '-ExecutionPolicy', 'Bypass',
            '-File', $root . '/tests/m07-http-isolated/validate-m07-apache-preflight-medium.ps1',
            '-Port', '8117', '-TimeoutSeconds', '15', '-ApplicationReadOnly',
        ];
        $httpRun = $run($httpCommand, 75);
        $httpPayload = json_decode((string)$httpRun['stdout'], true);
        $httpPassed = $httpRun['exit_code'] === 0
            && is_array($httpPayload)
            && ($httpPayload['RESULT'] ?? '') === 'PASS'
            && ($httpPayload['HTTP_RESULT'] ?? '') === 'PASS'
            && ($httpPayload['CLEANUP'] ?? '') === 'PASS';
        $report['http'] = $httpPassed ? 'PASS_REAL_ANONYMOUS_GETS' : 'FAIL_OR_BLOCKED';
        $report['tests']['isolated_apache_anonymous_gets'] = [
            'status' => $httpPassed ? 'PASS' : (($httpPayload['RESULT'] ?? '') === 'FAIL' ? 'FAIL' : 'BLOCKED'),
            'exit_code' => $httpRun['exit_code'],
            'timed_out' => (bool)($httpRun['timed_out'] ?? false),
            'failure' => is_array($httpPayload) ? (string)($httpPayload['FAILURE'] ?? 'NONE') : 'NO_VALID_JSON',
            'cases' => is_array($httpPayload) ? json_decode((string)($httpPayload['HTTP_CASE_RESULTS'] ?? '[]'), true) : [],
            'cleanup' => is_array($httpPayload) ? (string)($httpPayload['CLEANUP'] ?? 'NOT_REPORTED') : 'NOT_REPORTED',
        ];
    } else {
        $report['http'] = 'BLOCKED_DATABASE_PREFLIGHT';
        $report['tests']['isolated_apache_anonymous_gets'] = [
            'status' => 'BLOCKED',
            'reason' => 'READ_ONLY_SYNTHETIC_PREFLIGHT_NOT_PASS',
        ];
    }
}

$failed = false;
$blocked = false;
foreach ($report['tests'] as $testResult) {
    if (($testResult['status'] ?? '') === 'FAIL') $failed = true;
    if (($testResult['status'] ?? '') === 'BLOCKED') $blocked = true;
}
$report['status'] = $failed ? 'FAIL' : ($blocked ? 'BLOCKED' : 'PASS_STATIC_READ_ONLY_AND_HTTP');
$report['real_database_preflight'] = $preflightPassed ? 'PASS' : 'NOT_CONFIRMED';
$report['real_browser_acceptance'] = 'NOT_EXECUTED_BROWSER_RUNTIME_NOT_INTEGRATED';
$report['real_m07_capture_finalization'] = 'NOT_EXECUTED_GATE_CLOSED';

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(($failed || $blocked) ? 1 : 0);
