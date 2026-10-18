<?php

declare(strict_types=1);

/**
 * Focal M07 MySQL integration harness.
 *
 * This script is fail-closed. It never touches bd_clinica, requires the
 * synthetic database and patient IDs explicitly, and requires an explicit
 * --allow-synthetic-write before taking the focal snapshot and test writes.
 * The snapshot contains only the selected synthetic patient's M07 rows and is
 * restored in finally(). Values are never printed.
 */
$root = dirname(__DIR__, 2);
$options = [];
foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--')) continue;
    [$key, $value] = array_pad(explode('=', $argument, 2), 2, '1');
    $options[substr($key, 2)] = $value;
}

$database = (string)($options['database'] ?? '');
$patientA = (int)($options['patient-a'] ?? 0);
$patientB = (int)($options['patient-b'] ?? 0);
$allowWrite = array_key_exists('allow-synthetic-write', $options);
$result = [
    'phase' => 'M07_CONVERSATIONAL_IMPLEMENTATION_02',
    'database' => $database,
    'patient_a' => $patientA,
    'patient_b' => $patientB,
    'allow_synthetic_write' => $allowWrite,
    'preflight' => 'NOT_RUN',
    'read' => 'NOT_RUN',
    'write' => 'NOT_RUN',
    'rollback' => 'NOT_RUN',
    'restore' => 'NOT_RUN',
    'errors' => [],
];

$fail = static function (string $message) use (&$result): never {
    $result['errors'][] = $message;
    $result['status'] = 'BLOCKED';
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(2);
};

if ($database !== 'clinica_dolor_demo_20261010_final') $fail('La base no coincide con la única base sintética autorizada.');
if ($patientA < 1 || $patientB < 1 || $patientA === $patientB) $fail('Se requieren dos pacientes sintéticos distintos.');
if (!$allowWrite) $fail('La prueba MySQL requiere --allow-synthetic-write explícito.');

$environment = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $environment[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) if (!array_key_exists($key, $environment)) $fail('Falta configuración local requerida; no se continúa.');
if (!in_array(strtolower($environment['DB_HOST']), ['127.0.0.1', 'localhost', '::1'], true) || (string)$environment['DB_PORT'] !== '3306') $fail('La conexión no apunta a MySQL local en el puerto 3306.');
foreach ($environment as $key => $value) {
    $_ENV[$key] = $value;
    putenv($key . '=' . $value);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $environment['DB_HOST'], $environment['DB_PORT'], $database),
        $environment['DB_USERNAME'], $environment['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (Throwable) {
    $fail('MySQL local no disponible; no se ejecutaron escrituras.');
}

if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) $fail('La base activa no coincide exactamente con la solicitada.');
$patientStmt = $pdo->prepare('SELECT id, id_clinica, nombres, apellido_paterno, apellido_materno FROM pc_paciente WHERE id IN (?, ?) ORDER BY id');
$patientStmt->execute([$patientA, $patientB]);
$patients = $patientStmt->fetchAll();
$isSynthetic = static function (array $row): bool {
    $name = strtolower((string)($row['nombres'] ?? '') . ' ' . (string)($row['apellido_paterno'] ?? '') . ' ' . (string)($row['apellido_materno'] ?? ''));
    return str_contains($name, 'sint') || str_contains($name, 'test') || str_contains($name, 'prueba');
};
if (count($patients) !== 2 || count(array_filter($patients, $isSynthetic)) !== 2) $fail('Los IDs no corresponden a dos pacientes sintéticos verificables.');
$result['preflight'] = 'PASS';

$rowHash = static function (array $rows): string {
    return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
};
$fetchRows = static function (PDO $connection, int $patientId): array {
    $statement = $connection->prepare('SELECT * FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente = ? ORDER BY id');
    $statement->execute([$patientId]);
    return $statement->fetchAll();
};
$originalRowsA = $fetchRows($pdo, $patientA);
$originalRowsB = $fetchRows($pdo, $patientB);
$otherPatientHash = $rowHash($originalRowsB);
$restoreAttempted = false;
$currentOperation = 'startup';
$firstErrorRecorded = false;
$recordFailure = static function (Throwable $exception, string $operation) use (&$result, &$firstErrorRecorded): void {
    if ($firstErrorRecorded) {
        return;
    }
    $firstErrorRecorded = true;
    $result['first_failed_operation'] = $operation;
    $result['first_error_original_class'] = $exception instanceof PDOException ? 'PDOException' : (new ReflectionClass($exception))->getShortName();
    $result['first_error_class'] = $result['first_error_original_class'];
    $result['first_error_sqlstate'] = 'NONE';
    $result['first_error_driver_code'] = 'NONE';
    if ($exception instanceof PDOException && is_array($exception->errorInfo ?? null)) {
        $sqlState = (string)($exception->errorInfo[0] ?? '');
        $driverCode = (string)($exception->errorInfo[1] ?? '');
        $result['first_error_sqlstate'] = preg_match('/^[0-9A-Z]{5}$/', $sqlState) === 1 ? $sqlState : 'NONE';
        $result['first_error_driver_code'] = preg_match('/^\d+$/', $driverCode) === 1 ? $driverCode : 'NONE';
    }
    $result['first_error_message_sanitized'] = $exception instanceof PDOException
        ? 'PDOException durante la operación P09 sintética.'
        : 'Fallo de aserción o adaptador durante la operación P09 sintética.';
};

try {
    $currentOperation = 'adapter_read';
    putenv('DB_DATABASE=' . $database);
    $_ENV['DB_DATABASE'] = $database;
    $_SERVER['DB_DATABASE'] = $database;
    require_once $root . '/vendor/autoload.php';
    $adapter = new App\Services\ConversationalM07Adapter($pdo);
    $initial = $adapter->read($patientA);
    $questionCount = array_sum(array_map(static fn(array $category): int => count($category['items'] ?? []), $initial['categories'] ?? []));
    $result['read'] = ($questionCount === 46 && count($initial['categories'] ?? []) === 6 && count($originalRowsA) === count($fetchRows($pdo, $patientA))) ? 'PASS' : 'FAIL';
    if ($result['read'] !== 'PASS') throw new RuntimeException('El catálogo o la lectura M07 no coincide con 6 categorías y 46 medicamentos.');

    $candidate = null;
    foreach ($initial['categories'] as $category) foreach ($category['items'] as $item) {
        if (!$item['other_blocked'] && ($item['status'] ?? '') !== 'duplicate') { $candidate = ['question_id' => (int)$item['question_id'], 'theme_id' => (int)$category['theme_id'], 'item' => $item]; break 2; }
    }
    if (!$candidate) throw new RuntimeException('No hay medicamento sintético elegible para prueba focal.');

    $longDose = str_repeat('x', 5001);
    $currentOperation = 'atomic_rollback';
    $rollback = $adapter->captureBatch($patientA, [[
        'question_id' => $candidate['question_id'], 'theme_id' => $candidate['theme_id'], 'fields' => ['respuesta' => 'Si', 'dosis' => $longDose, 'resultados' => 'Funciono', 'consumo' => 'No'], 'confirm_clear' => true,
    ]], $initial['version']);
    $afterRollback = $adapter->read($patientA);
    $rollbackRestoredState = $afterRollback['version'] === $initial['version'] && $rowHash($fetchRows($pdo, $patientA)) === $rowHash($originalRowsA);
    $result['rollback_code'] = $rollback['code'] ?? null;
    $result['rollback_version_same'] = $afterRollback['version'] === $initial['version'];
    $result['rollback_rows_same'] = $rowHash($fetchRows($pdo, $patientA)) === $rowHash($originalRowsA);
    $result['rollback'] = (($rollback['code'] ?? '') === 'M07_ATOMIC_ROLLBACK' && $rollbackRestoredState) ? 'PASS' : 'FAIL';
    if ($result['rollback'] !== 'PASS') throw new RuntimeException('El rollback de la operación compuesta no pudo verificarse.');

    $currentOperation = 'confirmed_capture';
    $confirmed = $adapter->captureBatch($patientA, [[
        'question_id' => $candidate['question_id'], 'theme_id' => $candidate['theme_id'], 'fields' => ['respuesta' => 'Si', 'dosis' => '10 mg sintético', 'resultados' => 'Funciono', 'consumo' => 'No'], 'confirm_clear' => true,
    ]], $initial['version']);
    if (($confirmed['resultado'] ?? 0) !== 200) throw new RuntimeException('La persistencia canónica M07 no fue aceptada.');
    $afterConfirm = $adapter->read($patientA);
    $readbackItem = null;
    foreach ($afterConfirm['categories'] as $category) foreach ($category['items'] as $item) if ((int)$item['question_id'] === $candidate['question_id']) $readbackItem = $item;
    $result['write'] = $readbackItem && ($readbackItem['fields']['respuesta'] ?? '') === 'Si' && ($readbackItem['fields']['resultados'] ?? '') === 'Funciono' ? 'PASS' : 'FAIL';
    if ($result['write'] !== 'PASS') throw new RuntimeException('El readback M07 no coincidió con la respuesta confirmada.');

    $currentOperation = 'stale_version';
    $stale = $adapter->captureBatch($patientA, [[
        'question_id' => $candidate['question_id'], 'theme_id' => $candidate['theme_id'], 'fields' => ['respuesta' => 'No'], 'confirm_clear' => true,
    ]], $initial['version']);
    if (($stale['code'] ?? '') !== 'M07_VERSION_CONFLICT') throw new RuntimeException('No se rechazó la versión obsoleta.');

    $currentOperation = 'edit_capture';
    $edited = $adapter->captureBatch($patientA, [[
        'question_id' => $candidate['question_id'], 'theme_id' => $candidate['theme_id'], 'fields' => ['respuesta' => 'No'], 'confirm_clear' => true,
    ]], $afterConfirm['version']);
    $afterEdit = $adapter->read($patientA);
    $editedItem = null;
    foreach ($afterEdit['categories'] as $category) foreach ($category['items'] as $item) if ((int)$item['question_id'] === $candidate['question_id']) $editedItem = $item;
    if (($edited['resultado'] ?? 0) !== 200 || ($editedItem['fields']['respuesta'] ?? '') !== 'No' || ($editedItem['fields']['dosis'] ?? '') !== '') throw new RuntimeException('La edición M07 no limpió dependencias de forma canónica.');

    $negativeCandidates = [];
    foreach ($afterEdit['categories'] as $category) foreach ($category['items'] as $item) if (!$item['other_blocked'] && ($item['status'] ?? '') === 'pending') $negativeCandidates[] = ['question_id' => (int)$item['question_id'], 'theme_id' => (int)$category['theme_id'], 'fields' => ['respuesta' => 'No'], 'confirm_clear' => true];
    if (count($negativeCandidates) >= 2) {
        $currentOperation = 'negative_group_capture';
        $group = $adapter->captureBatch($patientA, array_slice($negativeCandidates, 0, 2), $afterEdit['version']);
        if (($group['resultado'] ?? 0) !== 200) throw new RuntimeException('La confirmación negativa grupal no fue aceptada.');
    }
    $result['write'] = $result['write'] === 'PASS' ? 'PASS' : 'FAIL';
    $result['idempotency_version_protection'] = 'PASS';
    $result['readback'] = 'PASS';
} catch (Throwable $exception) {
    $recordFailure($exception, $currentOperation);
    $result['errors'][] = 'Prueba focal fallida; revisar first_error_* sanitizado.';
    $result['status'] = 'FAIL';
} finally {
    $restoreAttempted = true;
    try {
        $currentOperation = 'clinical_restore';
        $pdo->beginTransaction();
        $restoreRows = static function (PDO $connection, int $patientId, array $rows): void {
            $connection->prepare('DELETE FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente = ?')->execute([$patientId]);
            if ($rows === []) {
                return;
            }
            $columns = array_keys($rows[0]);
            $quotedColumns = implode(',', array_map(static fn(string $column): string => '`' . $column . '`', $columns));
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $insert = $connection->prepare("INSERT INTO pac_respuestas_paciente_modulo_7 ({$quotedColumns}) VALUES ({$placeholders})");
            foreach ($rows as $row) {
                $insert->execute(array_map(static fn(string $column): mixed => $row[$column] ?? null, $columns));
            }
        };
        $restoreRows($pdo, $patientA, $originalRowsA);
        $restoreRows($pdo, $patientB, $originalRowsB);
        $pdo->commit();
        $restoredRowsA = $fetchRows($pdo, $patientA);
        $restoredRowsB = $fetchRows($pdo, $patientB);
        $otherAfterHash = $rowHash($fetchRows($pdo, $patientB));
        $result['restore'] = ($rowHash($restoredRowsA) === $rowHash($originalRowsA) && $rowHash($restoredRowsB) === $rowHash($originalRowsB) && $otherAfterHash === $otherPatientHash) ? 'PASS_FOCAL' : 'FAIL';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $result['restore'] = 'FAIL';
        $result['errors'][] = 'No fue posible completar la restauración focal.';
        if (!$firstErrorRecorded) {
            $recordFailure($exception, 'clinical_restore');
        }
    }
}

if ($restoreAttempted && $result['restore'] !== 'PASS_FOCAL') $result['status'] = 'FAIL';
if (($result['status'] ?? '') !== 'FAIL') $result['status'] = 'PASS_SYNTHETIC_MYSQL_FOCAL';
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit(($result['status'] === 'PASS_SYNTHETIC_MYSQL_FOCAL') ? 0 : 1);
