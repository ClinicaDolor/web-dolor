<?php
declare(strict_types=1);

use App\Services\ConversationalM03Adapter;
use Firebase\JWT\JWT;

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_browser_20261012';
$patient = 9302;
$port = 59995;
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}

$pdo = new PDO(
    "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$database};charset=utf8mb4",
    $env['DB_USERNAME'],
    $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) throw new RuntimeException('SYNTHETIC_DATABASE_GUARD_FAILED');
$patientStmt = $pdo->prepare('SELECT id,id_clinica FROM pc_paciente WHERE id=:id LIMIT 1');
$patientStmt->execute([':id' => $patient]);
$patientRow = $patientStmt->fetch();
if (!is_array($patientRow)) throw new RuntimeException('SYNTHETIC_PATIENT_NOT_FOUND');
$otherPatientStmt = $pdo->prepare('SELECT id,id_clinica FROM pc_paciente WHERE id=:id LIMIT 1');
$otherPatientStmt->execute([':id' => 9301]);
$otherPatientRow = $otherPatientStmt->fetch();
if (!is_array($otherPatientRow) || (int)$otherPatientRow['id_clinica'] === (int)$patientRow['id_clinica']) throw new RuntimeException('SYNTHETIC_CROSS_CLINIC_FIXTURE_NOT_AVAILABLE');

$_ENV = array_merge($_ENV, $env, [
    'DB_DATABASE' => $database,
    'APP_ENV' => 'testing',
    'APP_KEY' => bin2hex(random_bytes(32)),
    'CLINICAL_AI_SYNTHETIC_MODE' => '1',
    'CLINICAL_AI_EXTERNAL_ENABLED' => '0',
]);
require $root . '/vendor/autoload.php';

$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$adapter = new ConversationalM03Adapter($pdo);
$beforeRows = static function () use ($pdo, $patient): array {
    $stmt = $pdo->prepare('SELECT id,id_pregunta,id_paciente,respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=:patient ORDER BY id');
    $stmt->execute([':patient' => $patient]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};
$originalRows = $beforeRows();
$pdo->prepare('DELETE FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=:patient')->execute([':patient' => $patient]);
$seed = $pdo->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta,id_paciente,respuesta) VALUES (:question,:patient,:response)');
foreach ([[1, 'No'], [2, '7'], [3, '8']] as [$question, $response]) {
    $seed->execute([':question' => $question, ':patient' => $patient, ':response' => $response]);
}
$server = null;
$pipes = [];
$serverPid = 0;

$stopServer = static function () use (&$server, &$pipes, &$serverPid, $port): void {
    if (is_resource($server)) {
        $status = @proc_get_status($server);
        $serverPid = (int)($status['pid'] ?? $serverPid);
        @proc_terminate($server);
        if ($serverPid > 0) {
            $ignored = [];
            $ignoredCode = 0;
            @exec('taskkill /PID ' . $serverPid . ' /T /F >NUL 2>&1', $ignored, $ignoredCode);
        }
    }
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $lines = [];
        $ignoredCode = 0;
        @exec('netstat -ano -p tcp', $lines, $ignoredCode);
        $listenerPids = [];
        foreach ($lines as $line) if (preg_match('/^\s*TCP\s+127\.0\.0\.1:' . $port . '\s+\S+\s+LISTENING\s+(\d+)\s*$/i', $line, $match)) $listenerPids[] = (int)$match[1];
        if ($listenerPids === []) break;
        foreach (array_unique($listenerPids) as $listenerPid) {
            $ignored = [];
            @exec('taskkill /PID ' . $listenerPid . ' /T /F >NUL 2>&1', $ignored, $ignoredCode);
        }
        usleep(50000);
    }
    foreach ($pipes as $pipe) if (is_resource($pipe)) @fclose($pipe);
    if (is_resource($server)) @proc_close($server);
    $server = null;
    $pipes = [];
    $serverPid = 0;
};

$request = static function (string $url, string $jwt, string $csrf, array $payload): array {
    $context = stream_context_create(['http' => [
        'method' => 'POST', 'ignore_errors' => true, 'follow_location' => 0,
        'header' => "Content-Type: application/json\r\nCookie: CLINICAJWT={$jwt}\r\nX-M03-CSRF: {$csrf}\r\n",
        'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'timeout' => 10,
    ]]);
    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $match)) { $status = (int)$match[1]; break; }
    $json = json_decode((string)$raw, true);
    return [$status, is_array($json) ? $json : []];
};

try {
    $state = $adapter->read($patient);
    $candidate = null;
    foreach ($state['questions'] as $question) {
        if ((int)$question['idPregunta'] === 4 && trim((string)$question['respuesta']) === '' && !empty($question['eligible'])) { $candidate = $question; break; }
    }
    $assert(is_array($candidate), 'M03_P4_NOT_OPEN_IN_SYNTHETIC_FIXTURE');

    $router = $root . '/tests/patient-interview/gemini-m03-m06-http-router.php';
    $processEnv = array_merge(getenv() ?: [], $env, $_ENV, ['DB_DATABASE' => $database, 'P04_TEST_DATABASE' => $database, 'APP_KEY' => $_ENV['APP_KEY'], 'APP_ENV' => 'testing', 'CLINICAL_AI_SYNTHETIC_MODE' => '1', 'CLINICAL_AI_EXTERNAL_ENABLED' => '0']);
    $server = proc_open([PHP_BINARY, '-d', 'variables_order=EGPCS', '-S', '127.0.0.1:' . $port, $router], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $processEnv, ['bypass_shell' => true]);
    $assert(is_resource($server), 'HTTP_SERVER_START_FAILED');
    $serverStatus = proc_get_status($server);
    $serverPid = (int)($serverStatus['pid'] ?? 0);
    $assert($serverPid > 0, 'HTTP_SERVER_PID_UNVERIFIED');
    $ready = false;
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2);
        if (is_resource($probe)) { fclose($probe); $ready = true; break; }
        usleep(100000);
    }
    $assert($ready, 'HTTP_SERVER_NOT_READY');

    $jwt = JWT::encode(['iat' => time(), 'exp' => time() + 600, 'id_clinica' => (int)$patientRow['id_clinica'], 'id_usuario' => $patient, 'nombre' => 'Sintético', 'vista' => 'historia-clinica', 'rol' => 'Paciente'], $_ENV['APP_KEY'], 'HS256');
    $csrf = hash_hmac('sha256', 'm03-capture:' . $patient . ':' . hash('sha256', $jwt), $_ENV['APP_KEY']);
    $base = 'http://127.0.0.1:' . $port . '/historia-clinica/entrevista/capturar-directo';
    [$status, $response] = $request($base, $jwt, $csrf, ['idPaciente' => $patient, 'idPregunta' => 4, 'detalle' => '10', 'expectedVersion' => $state['version'], 'saveAndContinue' => true]);
    $assert($status === 200 && ($response['resultado'] ?? false) === true, 'DIRECT_HTTP_SAVE_FAILED');
    $assert(($response['mode'] ?? '') === 'direct' && ($response['code'] ?? '') === 'DIRECT_CAPTURE_ACCEPTED', 'DIRECT_HTTP_MODE_INVALID');
    $assert(($response['readback'] ?? false) === true && (int)($response['next_question_id'] ?? 0) > 0, 'DIRECT_HTTP_READBACK_OR_NEXT_MISSING');
    $after = $adapter->read($patient);
    $savedQuestion = null;
    foreach ($after['questions'] as $question) if ((int)$question['idPregunta'] === 4) { $savedQuestion = $question; break; }
    $assert((string)($savedQuestion['respuesta'] ?? '') === '10', 'DIRECT_HTTP_VALUE_NOT_PERSISTED');
    $assert((int)$pdo->query("SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente={$patient} AND id_pregunta=4")->fetchColumn() === 1, 'DIRECT_HTTP_DUPLICATE_CREATED');

    $tamperLookup = $pdo->prepare('SELECT q.id FROM pac_preguntas_modulo_3 q INNER JOIN pac_respuestas_paciente_modulo_3 r ON r.id_pregunta=q.id AND r.id_paciente=:patient WHERE q.tipo=\'input\' AND q.id<>4 AND TRIM(COALESCE(r.respuesta,\'\'))<>\'\' ORDER BY q.id LIMIT 1');
    $tamperLookup->execute([':patient' => $patient]);
    $tamperedQuestion = (int)$tamperLookup->fetchColumn();
    $assert($tamperedQuestion > 0, 'EDIT_TAMPERING_FIXTURE_NOT_AVAILABLE');
    [$tamperedStatus, $tamperedResponse] = $request($base, $jwt, $csrf, [
        'idPaciente' => $patient,
        'idPregunta' => $tamperedQuestion,
        'detalle' => '11',
        'expectedVersion' => $after['version'],
        'saveAndContinue' => true,
        'directMode' => 'numeric',
        'allowEdit' => true,
    ]);
    $assert($tamperedStatus === 409 && ($tamperedResponse['code'] ?? '') === 'DIRECT_EDIT_NOT_AUTHORIZED', 'EDIT_CLIENT_FLAG_TAMPERING_ACCEPTED');

    [$crossPatientStatus] = $request($base, $jwt, $csrf, [
        'idPaciente' => 9301,
        'idPregunta' => 4,
        'detalle' => '11',
        'expectedVersion' => $after['version'],
        'saveAndContinue' => true,
        'directMode' => 'numeric',
        'allowEdit' => true,
    ]);
    $assert($crossPatientStatus === 403, 'CROSS_PATIENT_EDIT_NOT_DENIED');

    $rowsBeforeInvalid = $beforeRows();
    $invalidQuestion = (int)($response['next_question_id'] ?? 0);
    [$invalidStatus] = $request($base, $jwt, $csrf, ['idPaciente' => $patient, 'idPregunta' => $invalidQuestion, 'detalle' => 'aproximadamente diez', 'expectedVersion' => $after['version'], 'saveAndContinue' => true]);
    $assert($invalidStatus === 422 && $rowsBeforeInvalid === $beforeRows(), 'INVALID_DIRECT_HTTP_CHANGED_FIXTURE');

    echo json_encode(['database' => $database, 'patient' => $patient, 'http_direct_save' => 'PASS', 'readback' => 'PASS', 'next_question_id' => 'PASS', 'invalid_no_write' => 'PASS', 'duplicate_count' => 0, 'tampered_allow_edit' => 'PASS', 'cross_patient_denial' => 'PASS', 'cross_clinic_denial' => 'PASS', 'real_gemini_calls' => 0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    $stopServer();
    $pdo->prepare('DELETE FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=:patient')->execute([':patient' => $patient]);
    $insert = $pdo->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id,id_pregunta,id_paciente,respuesta) VALUES (:id,:question,:patient,:response)');
    foreach ($originalRows as $row) $insert->execute([':id' => $row['id'], ':question' => $row['id_pregunta'], ':patient' => $row['id_paciente'], ':response' => $row['respuesta']]);
    $assert($originalRows === $beforeRows(), 'SYNTHETIC_FIXTURE_RESTORE_FAILED');
}
