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
    // Reproduce the real conversation order: the M03 parent is already
    // answered, so the duration question is the first pending question.
    $seed = $pdo->prepare("INSERT INTO {$table} (id_pregunta,id_paciente,respuesta) VALUES (:question,:patient,:response)");
    $seed->execute([':question' => 1, ':patient' => $patient, ':response' => 'No']);
    $seed->execute([':question' => 2, ':patient' => $patient, ':response' => '7']);
    $before = $adapter->read($patient);
    $candidate = null;
    foreach ($before['questions'] as $question) {
        $prompt = (string)($question['pregunta'] ?? '');
        if ((string)$question['tipo'] === 'input'
            && trim((string)$question['respuesta']) === ''
            && !empty($question['eligible'])
            && preg_match('/\b(?:hace|desde hace|durante)\s+cu[aá]nt(?:o|os|as)\b/iu', $prompt) === 1) {
            $candidate = $question;
            break;
        }
    }
    $assert(is_array($candidate), 'M03_DIRECT_DURATION_QUESTION_NOT_AVAILABLE');

    $saved = $adapter->captureDirectText($patient, (int)$candidate['idPregunta'], 'hace dos años', (string)$before['version']);
    $assert((int)($saved['resultado'] ?? 0) === 200, 'DIRECT_DURATION_CAPTURE_FAILED');
    $assert(($saved['mode'] ?? '') === 'direct' && ($saved['readback'] ?? false) === true, 'DIRECT_DURATION_READBACK_FAILED');
    $after = $adapter->read($patient);
    $assert(count(array_filter($readRows(), static fn(array $row): bool => (int)$row['id_pregunta'] === (int)$candidate['idPregunta'])) === 1, 'DIRECT_DURATION_DUPLICATE_CREATED');

    $edited = $adapter->captureDirectText($patient, (int)$candidate['idPregunta'], 'hace tres años', (string)$after['version'], true);
    $assert((int)($edited['resultado'] ?? 0) === 200, 'DIRECT_DURATION_EDIT_FAILED');
    $assert(($edited['readback'] ?? false) === true, 'DIRECT_DURATION_EDIT_READBACK_FAILED');
    $assert(count(array_filter($readRows(), static fn(array $row): bool => (int)$row['id_pregunta'] === (int)$candidate['idPregunta'])) === 1, 'DIRECT_DURATION_EDIT_DUPLICATED');

    $beforeInvalid = $readRows();
    $invalid = $adapter->captureDirectText($patient, (int)$candidate['idPregunta'], 'no sé', (string)$adapter->read($patient)['version'], true);
    $assert((int)($invalid['resultado'] ?? 0) === 422, 'INVALID_DIRECT_DURATION_ACCEPTED');
    $assert($beforeInvalid === $readRows(), 'INVALID_DIRECT_DURATION_CHANGED_FIXTURE');

    $beforeAmbiguous = $readRows();
    $ambiguous = $adapter->captureDirectText($patient, (int)$candidate['idPregunta'], 'hace dos años y algo más', (string)$adapter->read($patient)['version'], true);
    $assert((int)($ambiguous['resultado'] ?? 0) === 422, 'AMBIGUOUS_DIRECT_DURATION_ACCEPTED');
    $assert($beforeAmbiguous === $readRows(), 'AMBIGUOUS_DIRECT_DURATION_CHANGED_FIXTURE');

    echo json_encode([
        'database' => $database,
        'patient' => $patient,
        'direct_duration' => 'PASS',
        'readback' => 'PASS',
        'edit_without_duplicate' => 'PASS',
        'invalid_no_write' => 'PASS',
        'ambiguous_requires_review' => 'PASS',
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
