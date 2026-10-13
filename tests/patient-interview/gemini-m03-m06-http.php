<?php
declare(strict_types=1);

use App\Services\ConversationalM03Adapter;
use App\Services\ConversationalM06Adapter;
use Firebase\JWT\JWT;

$root = dirname(__DIR__, 2);
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$name, $value] = explode('=', $line, 2);
    $env[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
}
require $root . '/vendor/autoload.php';

$database = 'clinica_dolor_browser_20261012';
$patient = 9302;
$port = (int)(getenv('GEMINI_HTTP_TEST_PORT') ?: '8120');
if ($port < 1024 || $port > 65535) throw new RuntimeException('INVALID_HTTP_TEST_PORT');
$probeOnly = in_array('--probe', $argv ?? [], true);
$verifyOnly = in_array('--verify-only', $argv ?? [], true);
$traceFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'clinica-fase67v-' . getmypid() . '.jsonl';
@unlink($traceFile);
$server = null;
$serverPid = 0;
$pipes = [];
$pdo = null;
$before = [];
$result = ['database' => $database, 'patient_synthetic' => $patient, 'real_gemini_calls' => 0, 'errors' => []];

$hash = static function (PDO $db, string $table, int $id): string {
    $allowed = ['pac_respuestas_paciente_modulo_3', 'pac_medicacion_actual_modulo_6', 'pac_historia_clinica_finalizar'];
    if (!in_array($table, $allowed, true)) throw new RuntimeException('TABLE_NOT_ALLOWED');
    $stmt = $db->prepare('SELECT * FROM ' . $table . ' WHERE id_paciente=:id ORDER BY 1');
    $stmt->execute([':id' => $id]);
    return hash('sha256', serialize($stmt->fetchAll(PDO::FETCH_ASSOC)));
};

$request = static function (string $url, string $jwt, string $csrf, array $payload): array {
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $context = stream_context_create(['http' => [
        'method' => 'POST', 'ignore_errors' => true, 'follow_location' => 0,
        'header' => "Content-Type: application/json\r\nCookie: CLINICAJWT={$jwt}\r\nX-M03-CSRF: {$csrf}\r\n",
        'content' => $body, 'timeout' => 30,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $match)) { $status = (int)$match[1]; break; }
    $json = json_decode((string)$raw, true);
    return [$status, is_array($json) ? $json : []];
};

$get = static function (string $url): array {
    $context = stream_context_create(['http' => [
        'method' => 'GET', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $match)) { $status = (int)$match[1]; break; }
    $json = json_decode((string)$raw, true);
    return [$status, is_array($json) ? $json : []];
};

$stopServer = static function () use (&$server, &$serverPid, &$pipes): void {
    if (!is_resource($server)) return;
    $status = @proc_get_status($server);
    $serverPid = (int)($status['pid'] ?? $serverPid);
    if (($status['running'] ?? false) === true) @proc_terminate($server);
    for ($i = 0; $i < 10; $i++) {
        usleep(50000);
        $status = @proc_get_status($server);
        if (!is_array($status) || ($status['running'] ?? false) !== true) break;
    }
    if ($serverPid > 0 && PHP_OS_FAMILY === 'Windows') {
        $ignored = [];
        $ignoredCode = 0;
        @exec('taskkill /PID ' . $serverPid . ' /T /F', $ignored, $ignoredCode);
    }
    foreach ($pipes as $pipe) if (is_resource($pipe)) @fclose($pipe);
    @proc_close($server);
    $server = null;
    $serverPid = 0;
    $pipes = [];
};

$restore = static function (PDO $db, int $patient, array $beforeM03, array $beforeM06): void {
    $m03Stmt = $db->prepare('SELECT id FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=:patient ORDER BY id');
    $m03Stmt->execute([':patient' => $patient]);
    $beforeM03Ids = array_map('intval', array_column($beforeM03, 'id'));
    foreach ($m03Stmt->fetchAll(PDO::FETCH_COLUMN) as $id) if (!in_array((int)$id, $beforeM03Ids, true)) {
        $delete = $db->prepare('DELETE FROM pac_respuestas_paciente_modulo_3 WHERE id=:id AND id_paciente=:patient');
        $delete->execute([':id' => (int)$id, ':patient' => $patient]);
    }
    $updateM03 = $db->prepare('UPDATE pac_respuestas_paciente_modulo_3 SET respuesta=:respuesta WHERE id=:id AND id_paciente=:patient');
    foreach ($beforeM03 as $row) $updateM03->execute([':respuesta' => $row['respuesta'], ':id' => (int)$row['id'], ':patient' => $patient]);
    $m06Stmt = $db->prepare('SELECT id FROM pac_medicacion_actual_modulo_6 WHERE id_paciente=:patient ORDER BY id');
    $m06Stmt->execute([':patient' => $patient]);
    $beforeM06Ids = array_map('intval', array_column($beforeM06, 'id'));
    foreach ($m06Stmt->fetchAll(PDO::FETCH_COLUMN) as $id) if (!in_array((int)$id, $beforeM06Ids, true)) {
        $delete = $db->prepare('DELETE FROM pac_medicacion_actual_modulo_6 WHERE id=:id AND id_paciente=:patient');
        $delete->execute([':id' => (int)$id, ':patient' => $patient]);
    }
};

try {
    if (trim((string)($env['GEMINI_API_KEY'] ?? '')) === '') throw new RuntimeException('GEMINI_KEY_NOT_CONFIGURED');
    $portProbe = @fsockopen('127.0.0.1', $port, $portErrno, $portError, 0.2);
    if (is_resource($portProbe)) { fclose($portProbe); throw new RuntimeException('PORT_ALREADY_OCCUPIED'); }
    $pdo = new PDO('mysql:host=' . $env['DB_HOST'] . ';port=' . ($env['DB_PORT'] ?? '3306') . ';dbname=' . $database . ';charset=utf8mb4', $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) throw new RuntimeException('DATABASE_NOT_ALLOWED');
    $patientStmt = $pdo->prepare('SELECT id,id_clinica FROM pc_paciente WHERE id=:id LIMIT 1');
    $patientStmt->execute([':id' => $patient]);
    $patientRow = $patientStmt->fetch();
    if (!is_array($patientRow)) throw new RuntimeException('SYNTHETIC_PATIENT_NOT_FOUND');

    $beforeM03Stmt = $pdo->prepare('SELECT * FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=:patient ORDER BY id');
    $beforeM03Stmt->execute([':patient' => $patient]);
    $beforeM03 = $beforeM03Stmt->fetchAll();
    $beforeM06Stmt = $pdo->prepare('SELECT * FROM pac_medicacion_actual_modulo_6 WHERE id_paciente=:patient ORDER BY id');
    $beforeM06Stmt->execute([':patient' => $patient]);
    $beforeM06 = $beforeM06Stmt->fetchAll();
    $before = ['m03' => $hash($pdo, 'pac_respuestas_paciente_modulo_3', $patient), 'm06' => $hash($pdo, 'pac_medicacion_actual_modulo_6', $patient), 'finalization' => $hash($pdo, 'pac_historia_clinica_finalizar', $patient)];
    $questionId = (int)$pdo->query("SELECT id FROM pac_preguntas_modulo_3 WHERE id_tema=1 AND tipo='select' ORDER BY id LIMIT 1")->fetchColumn();
    if ($questionId < 1) throw new RuntimeException('M03_SELECT_QUESTION_NOT_FOUND');
    $m03State = (new ConversationalM03Adapter($pdo))->read($patient);
    $m06State = (new ConversationalM06Adapter($pdo))->read($patient);
    if (!$m03State['write_enabled'] || !$m06State['write_enabled']) throw new RuntimeException('SYNTHETIC_CAPTURE_NOT_EDITABLE');

    $appKey = bin2hex(random_bytes(32));
    $processEnv = getenv();
    if (!is_array($processEnv)) $processEnv = [];
    $processEnv = array_merge($processEnv, $env, ['DB_DATABASE' => $database, 'P04_TEST_DATABASE' => $database, 'APP_ENV' => 'testing', 'APP_KEY' => $appKey, 'CLINICAL_AI_SYNTHETIC_MODE' => '1', 'CLINICAL_AI_EXTERNAL_ENABLED' => '1', 'CLINICAL_AI_TRACE' => '1', 'CLINICAL_AI_TRACE_ID' => 'fase67v-' . getmypid(), 'CLINICAL_AI_TRACE_FILE' => $traceFile]);
    $router = $root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'patient-interview' . DIRECTORY_SEPARATOR . 'gemini-m03-m06-http-router.php';
    $command = '"' . PHP_BINARY . '" -d variables_order=EGPCS -S 127.0.0.1:' . $port . ' "' . $router . '"';
    $server = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $processEnv);
    if (!is_resource($server)) throw new RuntimeException('HTTP_SERVER_START_FAILED');
    $serverStatus = proc_get_status($server);
    if ((int)($serverStatus['pid'] ?? 0) < 1) throw new RuntimeException('HTTP_SERVER_PID_UNVERIFIED');
    $ready = false;
    for ($i = 0; $i < 40; $i++) { $probe = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2); if (is_resource($probe)) { fclose($probe); $ready = true; break; } usleep(100000); }
    if (!$ready) throw new RuntimeException('HTTP_SERVER_NOT_READY');

    [$envStatus, $envResponse] = $get('http://127.0.0.1:' . $port . '/__fase67/env');
    $result['http_environment'] = [
        'status' => $envStatus,
        'external_enabled' => $envResponse['external_enabled'] ?? null,
        'synthetic_mode' => $envResponse['synthetic_mode'] ?? null,
        'configured_database' => $envResponse['configured_database'] ?? null,
        'effective_database' => $envResponse['effective_database'] ?? null,
        'gemini_key_present' => $envResponse['gemini_key_present'] ?? null,
    ];
    if ($envStatus !== 200 || ($envResponse['external_enabled'] ?? '') !== '1' || ($envResponse['synthetic_mode'] ?? '') !== '1' || ($envResponse['effective_database'] ?? '') !== $database || ($envResponse['gemini_key_present'] ?? false) !== true) {
        throw new RuntimeException('HTTP_SYNTHETIC_ENV_NOT_ACTIVE');
    }
    if ($probeOnly) {
        $stopServer();
        $result['status'] = 'PROBE_PASS';
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    }

    $clinic = (int)$patientRow['id_clinica'];
    $jwt = JWT::encode(['iat' => time(), 'exp' => time() + 600, 'id_clinica' => $clinic, 'id_usuario' => $patient, 'nombre' => 'Sintético', 'vista' => 'historia-clinica', 'rol' => 'Paciente'], $appKey, 'HS256');
    $csrf = hash_hmac('sha256', 'm03-capture:' . $patient . ':' . hash('sha256', $jwt), $appKey);
    $base = 'http://127.0.0.1:' . $port . '/historia-clinica/entrevista';
    [$m03Status, $m03Response] = $request($base . '/interpretar', $jwt, $csrf, ['idPaciente' => $patient, 'idPregunta' => $questionId, 'modulo' => 'M03', 'utterance' => 'No, nunca he fumado', 'context' => []]);
    $m03Proposal = $m03Response['proposal'] ?? [];
    $m03Meta = is_array($m03Proposal['provider_meta'] ?? null) ? $m03Proposal['provider_meta'] : [];
    $result['m03_http_diagnostic'] = ['status' => $m03Status, 'resultado' => $m03Response['resultado'] ?? null, 'top_level_keys' => array_keys($m03Response), 'proposal_keys' => array_keys(is_array($m03Proposal) ? $m03Proposal : [])];
    $result['m03_real_http'] = $m03Status === 200 && ($m03Response['resultado'] ?? false) === true && ($m03Proposal['mode'] ?? '') === 'gemini' && ($m03Proposal['kind'] ?? '') === 'm03_proposal' && ($m03Proposal['structured_value'] ?? '') === 'No';
    $result['m03_provider_meta'] = ['model' => $m03Meta['model'] ?? null, 'latency_ms' => $m03Meta['latency_ms'] ?? null, 'usage' => $m03Meta['usage'] ?? null];
    $result['real_gemini_calls']++;

    [$envStatusM06, $envResponseM06] = $get('http://127.0.0.1:' . $port . '/__fase67/env');
    $result['http_environment_before_m06'] = [
        'status' => $envStatusM06,
        'external_enabled' => $envResponseM06['external_enabled'] ?? null,
        'synthetic_mode' => $envResponseM06['synthetic_mode'] ?? null,
        'effective_database' => $envResponseM06['effective_database'] ?? null,
        'gemini_key_present' => $envResponseM06['gemini_key_present'] ?? null,
    ];
    if ($envStatusM06 !== 200 || ($envResponseM06['external_enabled'] ?? '') !== '1' || ($envResponseM06['synthetic_mode'] ?? '') !== '1' || ($envResponseM06['effective_database'] ?? '') !== $database || ($envResponseM06['gemini_key_present'] ?? false) !== true) {
        throw new RuntimeException('HTTP_SYNTHETIC_ENV_NOT_ACTIVE_BEFORE_M06');
    }

    [$m06Status, $m06Response] = $request($base . '/interpretar', $jwt, $csrf, ['idPaciente' => $patient, 'modulo' => 'M06', 'utterance' => 'Tomo un medicamento llamado gabapentina por dolor nervioso. Llevo tres meses, tomo 300 mg por la noche y me lo indicó el doctor de la clínica', 'context' => []]);
    $m06Proposal = $m06Response['proposal'] ?? [];
    $m06Meta = is_array($m06Proposal['provider_meta'] ?? null) ? $m06Proposal['provider_meta'] : [];
    $m06Fields = is_array($m06Proposal['fields'] ?? null) ? $m06Proposal['fields'] : [];
    $result['m06_http_diagnostic'] = ['status' => $m06Status, 'resultado' => $m06Response['resultado'] ?? null, 'top_level_keys' => array_keys($m06Response), 'proposal_keys' => array_keys(is_array($m06Proposal) ? $m06Proposal : [])];
    $result['m06_real_http'] = $m06Status === 200 && ($m06Response['resultado'] ?? false) === true && ($m06Proposal['mode'] ?? '') === 'gemini' && ($m06Proposal['kind'] ?? '') === 'm06_proposal' && count(array_filter($m06Fields, static fn($value): bool => is_string($value) && trim($value) !== '')) === 5;
    $result['m06_provider_meta'] = ['model' => $m06Meta['model'] ?? null, 'latency_ms' => $m06Meta['latency_ms'] ?? null, 'usage' => $m06Meta['usage'] ?? null];
    $result['real_gemini_calls']++;
    $result['zero_writes_before_confirmation'] = $before === ['m03' => $hash($pdo, 'pac_respuestas_paciente_modulo_3', $patient), 'm06' => $hash($pdo, 'pac_medicacion_actual_modulo_6', $patient), 'finalization' => $hash($pdo, 'pac_historia_clinica_finalizar', $patient)];

    if (!$verifyOnly) {
        $m03Question = null;
        foreach ($m03State['questions'] as $row) if ((int)$row['idPregunta'] === $questionId) { $m03Question = $row; break; }
        if (!is_array($m03Question)) throw new RuntimeException('M03_QUESTION_READBACK_FAILED');
        [$m03CaptureStatus, $m03Capture] = $request($base . '/capturar', $jwt, $csrf, ['idPaciente' => $patient, 'idPregunta' => $questionId, 'idTema' => 1, 'detalle' => (string)($m03Proposal['structured_value'] ?? ''), 'expectedVersion' => $m03State['version'], 'expectedRespuesta' => (string)($m03Question['respuesta'] ?? '')]);
        $result['m03_synthetic_confirmation'] = $m03CaptureStatus === 200 && ($m03Capture['resultado'] ?? false) === true;
        [$m06CaptureStatus, $m06Capture] = $request($base . '/capturar-m06', $jwt, $csrf, ['idPaciente' => $patient, 'expectedVersion' => $m06State['version'], 'proposal' => $m06Proposal]);
        $result['m06_synthetic_confirmation'] = $m06CaptureStatus === 200 && ($m06Capture['resultado'] ?? false) === true;

        $m03After = (new ConversationalM03Adapter($pdo))->read($patient);
        $m03Readback = null;
        foreach ($m03After['questions'] as $row) if ((int)$row['idPregunta'] === $questionId) { $m03Readback = $row; break; }
        $m06After = (new ConversationalM06Adapter($pdo))->read($patient);
        $result['m03_persistence_readback'] = is_array($m03Readback) && (string)$m03Readback['respuesta'] === 'No';
        $result['m06_persistence_readback'] = count($m06After['records']) === count($beforeM06) + 1 && is_array($m06Capture['record'] ?? null);
        $result['no_m03_duplicate_question_rows'] = count(array_filter($m03After['questions'], static fn(array $row): bool => (int)$row['idPregunta'] === $questionId)) === 1;
    } else {
        $result['confirmation_requests'] = 0;
    }
    $stopServer();
    if (!$verifyOnly) $restore($pdo, $patient, $beforeM03, $beforeM06);
    $result['fixture_restored'] = $before === ['m03' => $hash($pdo, 'pac_respuestas_paciente_modulo_3', $patient), 'm06' => $hash($pdo, 'pac_medicacion_actual_modulo_6', $patient), 'finalization' => $hash($pdo, 'pac_historia_clinica_finalizar', $patient)];
    $traceEvents = [];
    foreach (@file($traceFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $event = json_decode($line, true);
        if (is_array($event)) $traceEvents[] = $event;
    }
    $result['trace_events'] = $traceEvents;
    @unlink($traceFile);
} catch (Throwable $error) {
    $result['errors'][] = preg_replace('/[^A-Z0-9_]+/', '_', strtoupper($error->getMessage() ?: 'REAL_HTTP_SYNTHETIC_FAILED'));
} finally {
    if (is_resource($server)) $stopServer();
    if (!$verifyOnly && $pdo instanceof PDO && isset($beforeM03, $beforeM06)) { try { $restore($pdo, $patient, $beforeM03, $beforeM06); } catch (Throwable) { $result['restore_error'] = 'RESTORE_FAILED'; } }
    @unlink($traceFile);
}

$result['status'] = $result['errors'] === [] && ($result['real_gemini_calls'] ?? 0) === 2 && ($result['fixture_restored'] ?? false) === true && !in_array(false, $result, true) ? 'PASS' : 'PARTIAL';
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($result['status'] === 'PASS' ? 0 : 1);
