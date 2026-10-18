<?php
declare(strict_types=1);

use App\Services\ConversationalM08TreatmentAdapter;

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_browser_20261012';
$patient = 9302;
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
if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
    throw new RuntimeException('SYNTHETIC_DATABASE_GUARD_FAILED');
}
$_ENV = array_merge($_ENV, $env, ['DB_DATABASE' => $database]);
require $root . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$rows = static function () use ($pdo, $patient): array {
    $stmt = $pdo->prepare('SELECT * FROM pac_respuestas_paciente_modulo_8 WHERE id_paciente=:patient ORDER BY id');
    $stmt->execute([':patient' => $patient]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};
$columns = array_column($pdo->query('SHOW COLUMNS FROM pac_respuestas_paciente_modulo_8')->fetchAll(), 'Field');
$original = $rows();
$trigger = 'p1_m08_rollback_' . bin2hex(random_bytes(6));
$triggerInstalled = false;
$adapter = new ConversationalM08TreatmentAdapter($pdo);

try {
    $engine = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pac_respuestas_paciente_modulo_8'")->fetchColumn();
    $assert(strtoupper((string)$engine) === 'INNODB', 'M08_TRANSACTION_ENGINE_UNSUPPORTED');
    $state = $adapter->read($patient);
    $assert(($state['write_enabled'] ?? false) === true, 'M08_SYNTHETIC_FIXTURE_NOT_EDITABLE');
    $treatmentId = (int)($state['items'][0]['id_tratamiento'] ?? 0);
    $assert($treatmentId > 0, 'M08_SYNTHETIC_TREATMENT_NOT_FOUND');

    // Use an empty synthetic response row so the first field creates it and
    // the trigger fails only on the next field update.
    $delete = $pdo->prepare('DELETE FROM pac_respuestas_paciente_modulo_8 WHERE id_paciente=:patient AND id_tratamiento=:treatment');
    $delete->execute([':patient' => $patient, ':treatment' => $treatmentId]);
    $failureBaseline = $rows();
    $pdo->exec("CREATE TRIGGER `{$trigger}` BEFORE UPDATE ON pac_respuestas_paciente_modulo_8 FOR EACH ROW BEGIN IF NEW.id_paciente = {$patient} AND NEW.id_tratamiento = {$treatmentId} AND NEW.resultado = 'Funciono' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic intermediate failure'; END IF; END");
    $triggerInstalled = true;
    $failureProposal = $adapter->interpret($patient, $treatmentId, 'utilizo: Sí; resultado: Funciono; descripcion: respuesta sintética');
    $failureResult = $adapter->capture($patient, $failureProposal, $adapter->read($patient)['version']);
    $pdo->exec("DROP TRIGGER IF EXISTS `{$trigger}`");
    $triggerInstalled = false;
    $afterFailure = $rows();
    $assert((int)($failureResult['resultado'] ?? 0) === 500, 'M08_INTERMEDIATE_FAILURE_NOT_REPORTED');
    $assert($failureBaseline === $afterFailure, 'M08_ROLLBACK_DID_NOT_RESTORE_BASELINE');

    $successState = $adapter->read($patient);
    $successProposal = $adapter->interpret($patient, $treatmentId, 'utilizo: Sí; resultado: No funciono; descripcion: respuesta sintética');
    $successResult = $adapter->capture($patient, $successProposal, $successState['version']);
    $assert((int)($successResult['resultado'] ?? 0) === 200, 'M08_SUCCESSFUL_CAPTURE_FAILED');
    $afterSuccess = $rows();
    $assert(count($afterSuccess) === count($failureBaseline) + 1, 'M08_SUCCESSFUL_ROW_COUNT_INVALID');
    $readback = $adapter->read($patient);
    $readbackItem = null;
    foreach ($readback['items'] as $item) if ((int)($item['id_tratamiento'] ?? 0) === $treatmentId) { $readbackItem = $item; break; }
    $assert(($readbackItem['utilizo'] ?? '') === 'Si', 'M08_READBACK_UTILIZO_FAILED');
    $assert(($readbackItem['resultado'] ?? '') === 'No funciono', 'M08_READBACK_RESULT_FAILED');
    $assert(trim((string)($readbackItem['descripcion'] ?? '')) !== '', 'M08_READBACK_DESCRIPTION_FAILED');

    echo json_encode([
        'database' => $database,
        'm08_atomic_capture' => 'PASS',
        'intermediate_failure_rollback' => 'PASS',
        'successful_persistence' => 'PASS',
        'readback' => 'PASS',
        'no_external_calls' => true,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    if ($triggerInstalled) {
        try { $pdo->exec("DROP TRIGGER IF EXISTS `{$trigger}`"); } catch (Throwable) {}
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM pac_respuestas_paciente_modulo_8 WHERE id_paciente=:patient')->execute([':patient' => $patient]);
        if ($original !== []) {
            $names = array_values(array_intersect($columns, array_keys($original[0])));
            $sql = 'INSERT INTO pac_respuestas_paciente_modulo_8 (' . implode(',', array_map(static fn(string $name): string => '`' . $name . '`', $names)) . ') VALUES (' . implode(',', array_fill(0, count($names), '?')) . ')';
            $insert = $pdo->prepare($sql);
            foreach ($original as $row) $insert->execute(array_map(static fn(string $name) => $row[$name], $names));
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}
