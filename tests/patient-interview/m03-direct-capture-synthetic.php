<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_browser_20261012';
$patient = 9302;
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) continue;
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
$_ENV = array_merge($_ENV, $env, ['DB_DATABASE' => $database]);
require $root . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$table = 'pac_respuestas_paciente_modulo_3';
$readRows = static function () use ($pdo, $table, $patient): array {
    $stmt = $pdo->prepare("SELECT id,id_pregunta,id_paciente,respuesta FROM {$table} WHERE id_paciente=:patient ORDER BY id");
    $stmt->execute([':patient' => $patient]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};
$original = $readRows();
$adapter = new App\Services\ConversationalM03Adapter($pdo);

try {
    $before = $adapter->read($patient);
    $candidate = null;
    foreach ($before['questions'] as $question) {
        if ((int)$question['idPregunta'] === 4 && (string)$question['tipo'] === 'input' && trim((string)$question['respuesta']) === '' && !empty($question['eligible'])) {
            $candidate = $question;
            break;
        }
    }
    $assert(is_array($candidate), 'M03_CIGARETTES_PER_DAY_NOT_ELIGIBLE_IN_SYNTHETIC_FIXTURE');

    $saved = $adapter->captureDirectNumeric($patient, (int)$candidate['idPregunta'], '10', (string)$before['version']);
    $assert((int)($saved['resultado'] ?? 0) === 200, 'DIRECT_CAPTURE_FAILED');
    $assert(($saved['mode'] ?? '') === 'direct' && ($saved['readback'] ?? false) === true, 'DIRECT_READBACK_FAILED');
    $after = $adapter->read($patient);
    $savedQuestion = null;
    foreach ($after['questions'] as $question) if ((int)$question['idPregunta'] === (int)$candidate['idPregunta']) $savedQuestion = $question;
    $assert((string)($savedQuestion['respuesta'] ?? '') === '10', 'DIRECT_VALUE_NOT_PERSISTED');
    $assert((int)$pdo->query("SELECT COUNT(*) FROM {$table} WHERE id_paciente={$patient} AND id_pregunta=" . (int)$candidate['idPregunta'])->fetchColumn() === 1, 'DIRECT_DUPLICATE_CREATED');

    $invalidCandidate = null;
    foreach ($after['questions'] as $question) {
        if ((int)$question['idPregunta'] !== 4 && (string)$question['tipo'] === 'input' && trim((string)$question['respuesta']) === '' && !empty($question['eligible'])) {
            $invalidCandidate = $question;
            break;
        }
    }
    $assert(is_array($invalidCandidate), 'NO_SECOND_SYNTHETIC_INPUT_QUESTION');
    $invalidBefore = $readRows();
    $invalid = $adapter->captureDirectNumeric($patient, (int)$invalidCandidate['idPregunta'], 'aproximadamente diez', (string)$after['version']);
    $assert((int)($invalid['resultado'] ?? 0) === 422 && ($invalid['code'] ?? '') === 'DIRECT_NUMERIC_VALUE_REQUIRED', 'INVALID_VALUE_WAS_ACCEPTED');
    $assert($invalidBefore === $readRows(), 'INVALID_ATTEMPT_CHANGED_FIXTURE');
    $nonNumericQuestion = null;
    foreach ($after['questions'] as $question) {
        if ((int)$question['idPregunta'] === 27 && trim((string)$question['respuesta']) === '' && !empty($question['eligible'])) {
            $nonNumericQuestion = $question;
            break;
        }
    }
    $assert(is_array($nonNumericQuestion), 'NON_NUMERIC_SYNTHETIC_QUESTION_NOT_AVAILABLE');
    $nonNumeric = $adapter->captureDirectNumeric($patient, 27, '10', (string)$after['version']);
    $assert((int)($nonNumeric['resultado'] ?? 0) === 422 && ($nonNumeric['code'] ?? '') === 'DIRECT_NUMERIC_QUESTION_REQUIRED', 'NON_NUMERIC_QUESTION_ACCEPTED_DIRECTLY');
    $assert($invalidBefore === $readRows(), 'NON_NUMERIC_ATTEMPT_CHANGED_FIXTURE');
    $answeredAgain = $adapter->captureDirectNumeric($patient, (int)$candidate['idPregunta'], '11', (string)$after['version']);
    $assert((int)($answeredAgain['resultado'] ?? 0) === 409, 'ANSWERED_QUESTION_WAS_OVERWRITTEN');

    echo json_encode([
        'database' => $database,
        'patient' => $patient,
        'direct_capture' => 'PASS',
        'readback' => 'PASS',
        'invalid_attempt_no_write' => 'PASS',
        'duplicate_count' => 0,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    $pdo->prepare("DELETE FROM {$table} WHERE id_paciente=:patient")->execute([':patient' => $patient]);
    $insert = $pdo->prepare("INSERT INTO {$table} (id,id_pregunta,id_paciente,respuesta) VALUES (:id,:question,:patient,:response)");
    foreach ($original as $row) {
        $insert->execute([
            ':id' => $row['id'],
            ':question' => $row['id_pregunta'],
            ':patient' => $row['id_paciente'],
            ':response' => $row['respuesta'],
        ]);
    }
    $assert($original === $readRows(), 'SYNTHETIC_FIXTURE_RESTORE_FAILED');
}
