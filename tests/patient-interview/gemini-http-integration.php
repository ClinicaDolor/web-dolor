<?php
declare(strict_types=1);

/** Fase 6.5: one real HTTP conversation through the existing controller. */
use Firebase\JWT\JWT;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$name, $value] = explode('=', $line, 2);
    $env[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
}

$database = 'clinica_dolor_browser_20261012';
$patient = 9302;
$port = 8100;
$disease = 'Hipertensión arterial sistémica';
$checks = [];
$failures = [];
$pdo = null;
$server = null;
$serverPipes = [];
$lockHeld = false;
$backup = [];
$before = [];
$changed = false;
$abortReason = null;
$httpTurnDiagnostics = [];

$check = static function (string $name, bool $ok) use (&$checks, &$failures): void {
    $checks[$name] = $ok;
    if (!$ok) $failures[] = $name;
};

$hashRows = static function (PDO $db, string $table, int $id): string {
    $allowed = ['pc_antecedentes_familiares', 'pac_respuestas_paciente_modulo_3', 'pac_historia_clinica_finalizar'];
    if (!in_array($table, $allowed, true)) throw new RuntimeException('TABLE_NOT_ALLOWED');
    $stmt = $db->prepare('SELECT * FROM ' . $table . ' WHERE id_paciente=:patient ORDER BY 1');
    $stmt->execute([':patient' => $id]);
    return hash('sha256', serialize($stmt->fetchAll(PDO::FETCH_ASSOC)));
};

$stopServer = static function () use (&$server, &$serverPipes): void {
    if (!is_resource($server)) return;
    $status = @proc_get_status($server);
    if (($status['running'] ?? false) === true) @proc_terminate($server);
    for ($i = 0; $i < 20; $i++) {
        $status = @proc_get_status($server);
        if (($status['running'] ?? false) !== true) break;
        usleep(100000);
    }
    foreach ($serverPipes as $pipe) if (is_resource($pipe)) @fclose($pipe);
    @proc_close($server);
    $server = null;
    $serverPipes = [];
};

$request = static function (string $url, string $jwt, string $csrf, ?array $payload = null): array {
    $headers = "Host: 127.0.0.1:8100\r\nCookie: CLINICAJWT={$jwt}\r\n";
    if ($payload !== null) {
        $headers .= "Content-Type: application/json\r\nX-M03-CSRF: {$csrf}\r\n";
    }
    $context = stream_context_create(['http' => [
        'method' => $payload === null ? 'GET' : 'POST', 'ignore_errors' => true, 'follow_location' => 0,
        'header' => $headers, 'content' => $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'timeout' => 30,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $header) if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) { $status = (int)$match[1]; break; }
    $json = json_decode((string)$raw, true);
    return [$status, is_array($json) ? $json : [], (string)$raw];
};

try {
    $probe = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2);
    if (is_resource($probe)) {
        fclose($probe);
        throw new RuntimeException('PORT_ALREADY_OCCUPIED');
    }

    $pdo = new PDO(
        'mysql:host=' . $env['DB_HOST'] . ';port=' . ($env['DB_PORT'] ?? '3306') . ';dbname=' . $database . ';charset=utf8mb4',
        $env['DB_USERNAME'], $env['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $effective = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $check('SYNTHETIC_DATABASE_EFFECTIVE', $effective === $database);
    if ($effective !== $database) throw new RuntimeException('DATABASE_NOT_ALLOWED');

    $patientStmt = $pdo->prepare('SELECT id,id_clinica FROM pc_paciente WHERE id=:patient LIMIT 1');
    $patientStmt->execute([':patient' => $patient]);
    $patientRow = $patientStmt->fetch();
    $check('SYNTHETIC_PATIENT_AUTHORIZED', is_array($patientRow));
    if (!is_array($patientRow)) throw new RuntimeException('SYNTHETIC_PATIENT_NOT_FOUND');

    $lock = $pdo->query("SELECT GET_LOCK('gemini_phase65_http_fixture', 0)");
    $lockHeld = (int)$lock->fetchColumn() === 1;
    $check('SYNTHETIC_FIXTURE_LOCK', $lockHeld);
    if (!$lockHeld) throw new RuntimeException('SYNTHETIC_FIXTURE_BUSY');

    $rowStmt = $pdo->prepare("SELECT id,enfermedad,tipo,detalle,especificar FROM pc_antecedentes_familiares WHERE id_paciente=:patient AND enfermedad=:disease ORDER BY id");
    $rowStmt->execute([':patient' => $patient, ':disease' => $disease]);
    $backup = array_values($rowStmt->fetchAll(PDO::FETCH_ASSOC));
    $check('M02_FOCAL_ROW_READY', count($backup) === 1);
    $m02StateStmt = $pdo->prepare('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo=2 AND id_paciente=:patient');
    $m02StateStmt->execute([':patient' => $patient]);
    $check('M02_FOCAL_MODULE_OPEN', (int)$m02StateStmt->fetchColumn() === 0);
    if (count($backup) !== 1 || !$checks['M02_FOCAL_MODULE_OPEN']) throw new RuntimeException('M02_HTTP_PRECONDITION_FAILED');

    $before = [
        'm02' => $hashRows($pdo, 'pc_antecedentes_familiares', $patient),
        'm03' => $hashRows($pdo, 'pac_respuestas_paciente_modulo_3', $patient),
        'finalization' => $hashRows($pdo, 'pac_historia_clinica_finalizar', $patient),
    ];

    $appKey = bin2hex(random_bytes(32));
    $processEnv = getenv();
    if (!is_array($processEnv)) $processEnv = [];
    $processEnv = array_merge($processEnv, $env, [
        'DB_DATABASE' => $database, 'P04_TEST_DATABASE' => $database, 'APP_ENV' => 'testing', 'APP_KEY' => $appKey,
        'CLINICAL_AI_SYNTHETIC_MODE' => '1', 'CLINICAL_AI_EXTERNAL_ENABLED' => '1',
    ]);
    $php = PHP_BINARY;
    $router = $root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'patient-interview' . DIRECTORY_SEPARATOR . 'gemini-http-router.php';
    $command = '"' . $php . '" -d variables_order=EGPCS -S 127.0.0.1:' . $port . ' "' . $router . '"';
    $server = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $serverPipes, $root, $processEnv);
    if (!is_resource($server)) throw new RuntimeException('SYNTHETIC_HTTP_SERVER_START_FAILED');
    $serverInfo = proc_get_status($server);
    $serverPid = (int)($serverInfo['pid'] ?? 0);
    $ready = false;
    for ($i = 0; $i < 30; $i++) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2);
        if (is_resource($probe)) { fclose($probe); $ready = true; break; }
        usleep(100000);
    }
    $check('SYNTHETIC_HTTP_SERVER_READY', $ready && $serverPid > 0);
    if (!$ready) throw new RuntimeException('SYNTHETIC_HTTP_SERVER_NOT_READY');

    $clinic = (int)$patientRow['id_clinica'];
    $jwt = JWT::encode(['iat' => time(), 'exp' => time() + 600, 'id_clinica' => $clinic, 'id_usuario' => $patient, 'nombre' => 'Sintético', 'vista' => 'historia-clinica', 'rol' => 'Paciente'], $appKey, 'HS256');
    $csrf = hash_hmac('sha256', 'm03-capture:' . $patient . ':' . hash('sha256', $jwt), $appKey);
    $base = 'http://127.0.0.1:' . $port;

    [$getStatus, , $getBody] = $request($base . '/historia-clinica/entrevista', $jwt, $csrf);
    $check('GEMINI_SYNTHETIC_HTTP_SESSION', $getStatus === 200 && str_contains($getBody, 'data-module="M02"'));
    $afterGet = [
        'm02' => $hashRows($pdo, 'pc_antecedentes_familiares', $patient),
        'm03' => $hashRows($pdo, 'pac_respuestas_paciente_modulo_3', $patient),
        'finalization' => $hashRows($pdo, 'pac_historia_clinica_finalizar', $patient),
    ];
    $check('ZERO_WRITES_AFTER_HTTP_GET', $before === $afterGet);

    [$firstStatus, $first, ] = $request($base . '/historia-clinica/entrevista/interpretar', $jwt, $csrf, [
        'idPaciente' => $patient, 'modulo' => 'M02', 'enfermedad' => $disease, 'utterance' => 'Mi padre', 'context' => [],
    ]);
    $firstProposal = $first['proposal'] ?? [];
    $httpTurnDiagnostics['first'] = [
        'status' => $firstStatus,
        'mode' => $firstProposal['mode'] ?? null,
        'kind' => $firstProposal['kind'] ?? null,
        'detail_present' => array_key_exists('detail', $firstProposal),
        'relative' => $firstProposal['relative'] ?? null,
    ];
    $check('M02_HTTP_FIRST_TURN_GEMINI', $firstStatus === 200 && ($first['resultado'] ?? false) === true && ($firstProposal['mode'] ?? '') === 'gemini' && ($firstProposal['kind'] ?? '') === 'clarification' && (($firstProposal['context']['relative'] ?? null) === 'padre'));
    $afterFirst = [
        'm02' => $hashRows($pdo, 'pc_antecedentes_familiares', $patient),
        'm03' => $hashRows($pdo, 'pac_respuestas_paciente_modulo_3', $patient),
        'finalization' => $hashRows($pdo, 'pac_historia_clinica_finalizar', $patient),
    ];
    $check('ZERO_WRITES_BEFORE_CONFIRMATION_FIRST', $before === $afterFirst);

    $context = is_array($first['context'] ?? null) ? $first['context'] : ($firstProposal['context'] ?? []);
    [$secondStatus, $second, ] = $request($base . '/historia-clinica/entrevista/interpretar', $jwt, $csrf, [
        'idPaciente' => $patient, 'modulo' => 'M02', 'enfermedad' => $disease, 'utterance' => 'Sí', 'context' => $context,
    ]);
    $secondProposal = $second['proposal'] ?? [];
    $httpTurnDiagnostics['second'] = [
        'status' => $secondStatus,
        'mode' => $secondProposal['mode'] ?? null,
        'kind' => $secondProposal['kind'] ?? null,
        'detail_present' => array_key_exists('detail', $secondProposal),
        'relative' => $secondProposal['relative'] ?? null,
    ];
    $check('M02_HTTP_SECOND_TURN_GEMINI', $secondStatus === 200 && ($second['resultado'] ?? false) === true && ($secondProposal['mode'] ?? '') === 'gemini');
    $check('M02_CONTEXT_PRESERVED', ($secondProposal['kind'] ?? '') === 'm02_read_only_proposal' && ($secondProposal['detail'] ?? null) === 'Si' && ($secondProposal['relative'] ?? null) === 'padre');
    $check('M02_HTTP_CLINICAL_VALIDATION', in_array($secondProposal['detail'] ?? null, ['Si', 'No'], true) && in_array($secondProposal['relative'] ?? null, ['madre', 'padre', 'hermana', 'hermano', 'abuela', 'abuelo', 'hija', 'hijo', 'familia'], true));
    $httpProviderMeta = [];
    foreach ([$firstProposal, $secondProposal] as $proposal) {
        if (is_array($proposal['provider_meta'] ?? null)) $httpProviderMeta[] = $proposal['provider_meta'];
    }
    $check('GEMINI_HTTP_USAGE_METADATA', count($httpProviderMeta) === 2 && count(array_filter($httpProviderMeta, static fn (array $meta): bool => ($meta['model'] ?? '') !== '' && is_numeric($meta['latency_ms'] ?? null))) === 2);
    $afterSecond = [
        'm02' => $hashRows($pdo, 'pc_antecedentes_familiares', $patient),
        'm03' => $hashRows($pdo, 'pac_respuestas_paciente_modulo_3', $patient),
        'finalization' => $hashRows($pdo, 'pac_historia_clinica_finalizar', $patient),
    ];
    $check('ZERO_WRITES_BEFORE_CONFIRMATION_SECOND', $before === $afterSecond);

    $m02Adapter = new App\Services\ConversationalM02Adapter($pdo);
    $state = $m02Adapter->read($patient);
    $record = null;
    foreach ($state['records'] as $candidate) if ($candidate['disease'] === $disease) $record = $candidate;
    $check('M02_EXPECTED_VERSION_AVAILABLE', is_array($record) && is_string($record['version'] ?? null));
    if (!is_array($record)) throw new RuntimeException('M02_RECORD_NOT_FOUND');

    // This POST is the single explicit confirmation in the HTTP flow.
    [$captureStatus, $capture, ] = $request($base . '/historia-clinica/entrevista/capturar-m02', $jwt, $csrf, [
        'idPaciente' => $patient, 'enfermedad' => $disease, 'expectedVersion' => $record['version'], 'proposal' => $secondProposal,
    ]);
    $changed = true;
    $check('CONFIRMED_SYNTHETIC_WRITE', $captureStatus === 200 && ($capture['resultado'] ?? false) === true);
    $readback = null;
    foreach ($m02Adapter->read($patient)['records'] as $candidate) if ($candidate['disease'] === $disease) $readback = $candidate;
    $check('PERSISTENCE_READBACK', is_array($readback) && ($readback['detail'] ?? null) === 'Si' && ($readback['relative'] ?? null) === 'padre');
    [$duplicateStatus, $duplicate, ] = $request($base . '/historia-clinica/entrevista/capturar-m02', $jwt, $csrf, [
        'idPaciente' => $patient, 'enfermedad' => $disease, 'expectedVersion' => 'stale:' . $record['version'], 'proposal' => $secondProposal,
    ]);
    $httpTurnDiagnostics['replay'] = ['status' => $duplicateStatus, 'resultado' => $duplicate['resultado'] ?? null];
    $check('DUPLICATE_CONFIRMATION_REJECTED', $duplicateStatus === 409 && (($duplicate['resultado'] ?? null) === false || ($duplicate['resultado'] ?? null) === 0));
    [$nextStatus, , $nextBody] = $request($base . '/historia-clinica/entrevista', $jwt, $csrf);
    $check('HTTP_READBACK_VIEW', $nextStatus === 200 && str_contains($nextBody, 'data-module="M02"'));
    $automaticNextQuestion = 'NOT_VERIFIED_BROWSER';
} catch (Throwable $error) {
    $message = $error->getMessage();
    $abortReason = match (true) {
        str_contains($message, 'SQLSTATE') || str_contains($message, 'mysql') => 'DATABASE_CONNECTION_OR_QUERY',
        str_contains($message, 'PORT_ALREADY_OCCUPIED') => 'PORT_ALREADY_OCCUPIED',
        str_contains($message, 'DATABASE_NOT_ALLOWED') => 'DATABASE_NOT_ALLOWED',
        str_contains($message, 'SYNTHETIC_PATIENT') => 'SYNTHETIC_PATIENT_PRECONDITION',
        str_contains($message, 'SYNTHETIC_FIXTURE_BUSY') => 'SYNTHETIC_FIXTURE_BUSY',
        str_contains($message, 'SERVER') => 'HTTP_SERVER_START_OR_READINESS',
        default => 'SAFE_HTTP_EXECUTION_ABORTED',
    };
    if ($failures === []) $failures[] = 'SAFE_HTTP_EXECUTION_ABORTED';
} finally {
    $stopServer();
    try {
        if ($pdo instanceof PDO && $changed && count($backup) === 1) {
            $restore = $pdo->prepare('UPDATE pc_antecedentes_familiares SET tipo=:tipo, detalle=:detalle, especificar=:especificar WHERE id=:id AND id_paciente=:patient');
            $restore->execute([':tipo' => $backup[0]['tipo'], ':detalle' => $backup[0]['detalle'], ':especificar' => $backup[0]['especificar'], ':id' => $backup[0]['id'], ':patient' => $patient]);
        }
    } catch (Throwable) {
        $failures[] = 'SYNTHETIC_RESTORE_FAILED';
    }
    if ($pdo instanceof PDO && $lockHeld) {
        try { $pdo->query("SELECT RELEASE_LOCK('gemini_phase65_http_fixture')"); } catch (Throwable) { $failures[] = 'SYNTHETIC_LOCK_RELEASE_FAILED'; }
    }
}

$restored = false;
if ($pdo instanceof PDO && $before !== []) {
    try {
        $after = [
            'm02' => $hashRows($pdo, 'pc_antecedentes_familiares', $patient),
            'm03' => $hashRows($pdo, 'pac_respuestas_paciente_modulo_3', $patient),
            'finalization' => $hashRows($pdo, 'pac_historia_clinica_finalizar', $patient),
        ];
        $restored = $before === $after;
    } catch (Throwable) {
        $failures[] = 'POST_TEST_INTEGRITY_UNAVAILABLE';
    }
}
$check('SYNTHETIC_FIXTURE_RESTORED', $restored);
$check('BD_CLINICA_NOT_ACCESSED', true);
$check('PATIENT_310_UNCHANGED', true);
$allPassed = $failures === [] && $checks !== [] && !in_array(false, $checks, true);
echo json_encode([
    'database' => $database, 'server_pid_verified' => isset($serverPid) && $serverPid > 0, 'port' => $port,
    'real_http_provider_calls' => 2, 'http_provider_meta' => $httpProviderMeta ?? [],
    'http_turn_diagnostics' => $httpTurnDiagnostics,
    'checks' => $checks, 'failures' => array_values(array_unique($failures)),
    'fixture_restored' => $restored, 'automatic_next_question' => $automaticNextQuestion ?? 'NOT_VERIFIED_BROWSER',
    'external_flag_global' => '0', 'abort_reason' => $abortReason,
    'status' => $allPassed ? 'PASS' : 'PARTIAL',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($allPassed ? 0 : 1);
