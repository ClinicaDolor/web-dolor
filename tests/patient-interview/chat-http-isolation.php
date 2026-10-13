<?php

declare(strict_types=1);

use Firebase\JWT\JWT;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable($root)->load();
$database = 'clinica_dolor_demo_20261010_final';
$baseUrl = 'http://127.0.0.1:8087';
$appKey = (string)($_ENV['APP_KEY'] ?? '');
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--base-url=')) $baseUrl = rtrim(substr($argument, 11), '/');
    if (str_starts_with($argument, '--app-key=') && $appKey === '') $appKey = substr($argument, 10);
}

if ($appKey === '') {
    fwrite(STDERR, "CHAT HTTP: FAIL (missing test app key)\n");
    exit(2);
}

$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $database),
    $env['DB_USERNAME'],
    $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
    throw new RuntimeException('synthetic database assertion failed');
}

$patient = 9301;
$clinic = 9101;
$otherPatient = 9302;
$snapshot = $pdo->prepare('SELECT id,id_pregunta,id_paciente,respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = ? ORDER BY id');
$snapshot->execute([$patient]);
$original = $snapshot->fetchAll();
$restore = static function (PDO $pdo, int $patient, array $rows): void {
    $pdo->beginTransaction();
    try {
        $delete = $pdo->prepare('DELETE FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = ?');
        $delete->execute([$patient]);
        $insert = $pdo->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id,id_pregunta,id_paciente,respuesta) VALUES (?,?,?,?)');
        foreach ($rows as $row) $insert->execute([(int)$row['id'], (int)$row['id_pregunta'], (int)$row['id_paciente'], $row['respuesta']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
};

$token = static fn (int $clinicId, int $patientId): string => JWT::encode([
    'iat' => time(), 'exp' => time() + 600, 'id_clinica' => $clinicId,
    'id_usuario' => $patientId, 'nombre' => 'Sintético', 'vista' => 'historia-clinica', 'rol' => 'Paciente',
], $appKey, 'HS256');
$request = static function (string $url, string $jwt, ?array $payload = null, string $csrfMode = 'valid') use ($appKey): array {
    $headers = "Host: chat-p05.localhost:8087\r\nCookie: CLINICAJWT={$jwt}\r\n";
    if ($payload !== null) {
        $headers .= "Content-Type: application/json\r\n";
        if ($csrfMode !== 'missing') {
            $id = (int)$payload['idPaciente'];
            $csrf = $csrfMode === 'invalid' ? str_repeat('0', 64) : hash_hmac('sha256', 'm03-capture:' . $id . ':' . hash('sha256', $jwt), $appKey);
            $headers .= 'X-M03-CSRF: ' . $csrf . "\r\n";
        }
    }
    $options = ['http' => ['method' => $payload === null ? 'GET' : 'POST', 'ignore_errors' => true, 'follow_location' => 0, 'header' => $headers, 'content' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE)]];
    $body = file_get_contents($url, false, stream_context_create($options));
    $status = 0;
    foreach ($http_response_header ?? [] as $header) if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) { $status = (int)$match[1]; break; }
    return [$status, (string)$body];
};

try {
    // Synthetic setup only; the finally block restores the exact pre-test snapshot.
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = ?')->execute([$patient]);
    $insert = $pdo->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta,id_paciente,respuesta) VALUES (?,?,?)');
    foreach ([1 => 'SYNTHETIC-P1', 2 => 'SYNTHETIC-P2', 3 => 'SYNTHETIC-P3', 4 => 'SYNTHETIC-P4', 5 => ''] as $question => $value) $insert->execute([$question, $patient, $value]);
    $pdo->commit();

    $jwt = $token($clinic, $patient);
    $beforeGet = (int)$pdo->query("SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = {$patient}")->fetchColumn();
    [$getStatus] = $request($baseUrl . '/historia-clinica/entrevista', $jwt);
    $afterGet = (int)$pdo->query("SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = {$patient}")->fetchColumn();

    $post = ['idPaciente' => $patient, 'idPregunta' => 5, 'idTema' => 1, 'detalle' => 'SYNTHETIC-P5', 'expectedRespuesta' => ''];
    [$postStatus] = $request($baseUrl . '/historia-clinica/entrevista/capturar', $jwt, $post);
    $rows = $pdo->query("SELECT id,id_pregunta,id_paciente,respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = {$patient} ORDER BY id_pregunta")->fetchAll();
    $byQuestion = [];
    foreach ($rows as $row) $byQuestion[(int)$row['id_pregunta']] = $row;
    $previousPreserved = true;
    foreach ([1, 2, 3, 4] as $question) $previousPreserved = $previousPreserved && $byQuestion[$question]['respuesta'] === 'SYNTHETIC-P' . $question;
    $singleP5 = count($rows) === 5 && $byQuestion[5]['respuesta'] === 'SYNTHETIC-P5';

    $stale = $post;
    $stale['detalle'] = 'SYNTHETIC-STALE';
    [$staleStatus] = $request($baseUrl . '/historia-clinica/entrevista/capturar', $jwt, $stale);
    [$crossStatus] = $request($baseUrl . '/historia-clinica/entrevista/capturar', $token(9102, $otherPatient), $post + ['idPaciente' => $patient]);
    [$missingCsrfStatus] = $request($baseUrl . '/historia-clinica/entrevista/capturar', $jwt, $post, 'missing');
    [$invalidCsrfStatus] = $request($baseUrl . '/historia-clinica/entrevista/capturar', $jwt, $post, 'invalid');

    echo json_encode([
        'database' => $database, 'get_status' => $getStatus, 'get_no_write' => $beforeGet === $afterGet,
        'p5_status' => $postStatus, 'p5_single_question' => $singleP5, 'p1_p4_preserved' => $previousPreserved,
        'stale_status' => $staleStatus, 'cross_patient_status' => $crossStatus, 'missing_csrf_status' => $missingCsrfStatus,
        'invalid_csrf_status' => $invalidCsrfStatus, 'current_row_count' => count($rows),
        'restored_after_test' => true,
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    $restore($pdo, $patient, $original);
}
