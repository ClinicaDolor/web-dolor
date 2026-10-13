<?php
declare(strict_types=1);

use Firebase\JWT\JWT;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}

$database = 'clinica_dolor_browser_20261012';
$patient = 9302;
$port = 8114;
$pdo = null;
$server = null;
$pipes = [];
$failures = [];
$before = [];
$results = [];
$diagnostics = [];
$serverPid = 0;
$serverErrorHint = null;

$hash = static function (PDO $db, string $table, int $id): string {
    $allowed = ['pc_antecedentes_familiares', 'pac_respuestas_paciente_modulo_3', 'pac_historia_clinica_finalizar'];
    if (!in_array($table, $allowed, true)) throw new RuntimeException('TABLE_NOT_ALLOWED');
    $stmt = $db->prepare('SELECT * FROM ' . $table . ' WHERE id_paciente=:id ORDER BY 1');
    $stmt->execute([':id' => $id]);
    return hash('sha256', serialize($stmt->fetchAll(PDO::FETCH_ASSOC)));
};

$request = static function (string $url, string $jwt, string $csrf): array {
    $body = json_encode([
        'idPaciente' => 9302, 'modulo' => 'M02',
        'enfermedad' => 'Hipertensión arterial sistémica', 'utterance' => 'Mi padre', 'context' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $context = stream_context_create(['http' => [
        'method' => 'POST', 'ignore_errors' => true, 'follow_location' => 0,
        'header' => "Content-Type: application/json\r\nCookie: CLINICAJWT={$jwt}\r\nX-M03-CSRF: {$csrf}\r\n",
        'content' => $body, 'timeout' => 8,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $match)) { $status = (int)$match[1]; break; }
    $trimmed = ltrim((string)$raw);
    $kind = $trimmed === '' ? 'empty' : (str_starts_with($trimmed, '<') ? 'html' : (str_starts_with($trimmed, '{') ? 'json_like' : 'text'));
    return [$status, json_decode((string)$raw, true) ?: [], strlen((string)$raw), hash('sha256', (string)$raw), $kind];
};

try {
    $portProbe = @fsockopen('127.0.0.1', $port, $portErrno, $portError, 0.2);
    if (is_resource($portProbe)) {
        fclose($portProbe);
        throw new RuntimeException('PORT_ALREADY_OCCUPIED');
    }
    $pdo = new PDO(
        'mysql:host=' . $env['DB_HOST'] . ';port=' . ($env['DB_PORT'] ?? '3306') . ';dbname=' . $database . ';charset=utf8mb4',
        $env['DB_USERNAME'], $env['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) throw new RuntimeException('DATABASE_NOT_ALLOWED');
    $before = [
        'm02' => $hash($pdo, 'pc_antecedentes_familiares', $patient),
        'm03' => $hash($pdo, 'pac_respuestas_paciente_modulo_3', $patient),
        'finalization' => $hash($pdo, 'pac_historia_clinica_finalizar', $patient),
    ];
    $patientStmt = $pdo->prepare('SELECT id_clinica FROM pc_paciente WHERE id=:id');
    $patientStmt->execute([':id' => $patient]);
    $clinic = (int)$patientStmt->fetchColumn();
    if ($clinic < 1) throw new RuntimeException('SYNTHETIC_PATIENT_NOT_FOUND');

    $appKey = bin2hex(random_bytes(32));
    $processEnv = getenv();
    if (!is_array($processEnv)) $processEnv = [];
    $processEnv = array_merge($processEnv, $env, [
        'DB_DATABASE' => $database, 'P04_TEST_DATABASE' => $database, 'APP_ENV' => 'testing', 'APP_KEY' => $appKey,
        'CLINICAL_AI_SYNTHETIC_MODE' => '1', 'CLINICAL_AI_EXTERNAL_ENABLED' => '1',
    ]);
    $router = $root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'patient-interview' . DIRECTORY_SEPARATOR . 'gemini-failure-http-router.php';
    $command = '"' . PHP_BINARY . '" -d variables_order=EGPCS -S 127.0.0.1:' . $port . ' "' . $router . '"';
    $server = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $processEnv);
    if (!is_resource($server)) throw new RuntimeException('HTTP_SERVER_START_FAILED');
    $serverStatus = proc_get_status($server);
    $serverPid = (int)($serverStatus['pid'] ?? 0);
    $ready = false;
    for ($i = 0; $i < 30; $i++) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2);
        if (is_resource($probe)) { fclose($probe); $ready = true; break; }
        usleep(100000);
    }
    if (!$ready) throw new RuntimeException('HTTP_SERVER_NOT_READY');

    $jwt = JWT::encode(['iat' => time(), 'exp' => time() + 600, 'id_clinica' => $clinic, 'id_usuario' => $patient, 'nombre' => 'Sintético', 'vista' => 'historia-clinica', 'rol' => 'Paciente'], $appKey, 'HS256');
    $csrf = hash_hmac('sha256', 'm03-capture:' . $patient . ':' . hash('sha256', $jwt), $appKey);
    $base = 'http://127.0.0.1:' . $port . '/__fase66/interpret?scenario=';
    foreach (['timeout', 'network', 'empty', 'malformed', 'out_of_catalog', 'contradictory'] as $scenario) {
        [$status, $body, $rawLength, $rawHash, $rawKind] = $request($base . rawurlencode($scenario), $jwt, $csrf);
        $proposal = $body['proposal'] ?? [];
        $expectedMode = $scenario === 'contradictory' ? 'gemini' : 'deterministic';
        $results[$scenario] = $status === 200 && ($body['resultado'] ?? false) === true && ($proposal['mode'] ?? '') === $expectedMode && ($proposal['kind'] ?? '') === 'clarification';
        $diagnostics[$scenario] = ['status' => $status, 'raw_length' => $rawLength, 'raw_sha256' => $rawHash, 'response_kind' => $rawKind, 'top_level_keys' => array_keys($body), 'resultado' => $body['resultado'] ?? null, 'mode' => $proposal['mode'] ?? null, 'kind' => $proposal['kind'] ?? null, 'error' => $body['error'] ?? null];
    }
    [$lateFirstStatus, $lateFirst] = $request($base . 'late_first', $jwt, $csrf);
    [$lateSecondStatus, $lateSecond] = $request($base . 'late_retry', $jwt, $csrf);
    $results['late_response_after_retry'] = $lateFirstStatus === 200 && $lateSecondStatus === 200 && ($lateFirst['proposal']['mode'] ?? '') === 'deterministic' && ($lateSecond['proposal']['mode'] ?? '') === 'gemini';
    $diagnostics['late_response_after_retry'] = ['first_status' => $lateFirstStatus, 'first_mode' => $lateFirst['proposal']['mode'] ?? null, 'second_status' => $lateSecondStatus, 'second_mode' => $lateSecond['proposal']['mode'] ?? null];
    [$repeatOneStatus, $repeatOne] = $request($base . 'repeat', $jwt, $csrf);
    [$repeatTwoStatus, $repeatTwo] = $request($base . 'repeat', $jwt, $csrf);
    $results['repeat_same_request'] = $repeatOneStatus === 200 && $repeatTwoStatus === 200 && ($repeatOne['proposal']['mode'] ?? '') === 'gemini' && ($repeatTwo['proposal']['mode'] ?? '') === 'gemini';
    $diagnostics['repeat_same_request'] = ['first_status' => $repeatOneStatus, 'first_mode' => $repeatOne['proposal']['mode'] ?? null, 'second_status' => $repeatTwoStatus, 'second_mode' => $repeatTwo['proposal']['mode'] ?? null];
} catch (Throwable $error) {
    $message = $error->getMessage();
    $failures[] = preg_match('/^[A-Z0-9_]+$/', $message) ? $message : 'SAFE_HTTP_FAILURE_ABORTED';
    if (isset($pipes[2]) && is_resource($pipes[2])) {
        $stderr = stream_get_contents($pipes[2]);
        $serverErrorHint = str_contains($stderr, 'Failed to listen') ? 'PORT_BIND_FAILED' : (str_contains($stderr, 'Fatal error') ? 'PHP_SERVER_FATAL' : ($stderr !== '' ? 'SERVER_STDERR' : null));
    }
} finally {
    if (is_resource($server)) {
        $status = @proc_get_status($server);
        if (($status['running'] ?? false) === true) @proc_terminate($server);
        foreach ($pipes as $pipe) if (is_resource($pipe)) @fclose($pipe);
        @proc_close($server);
    }
}

$after = [];
if ($pdo instanceof PDO && $before !== []) {
    $after = [
        'm02' => $hash($pdo, 'pc_antecedentes_familiares', $patient),
        'm03' => $hash($pdo, 'pac_respuestas_paciente_modulo_3', $patient),
        'finalization' => $hash($pdo, 'pac_historia_clinica_finalizar', $patient),
    ];
}
$restored = $before !== [] && $before === $after;
$allPassed = $failures === [] && count($results) === 8 && !in_array(false, $results, true) && $restored;
echo json_encode([
    'database' => $database, 'server_pid_verified' => $serverPid > 0, 'port' => $port,
    'scenarios' => $results, 'real_gemini_calls' => 0,
    'diagnostics' => $diagnostics,
    'fixture_restored' => $restored, 'unconfirmed_writes' => 0,
    'failures' => $failures, 'server_error_hint' => $serverErrorHint, 'status' => $allPassed ? 'PASS' : 'PARTIAL',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($allPassed ? 0 : 1);
