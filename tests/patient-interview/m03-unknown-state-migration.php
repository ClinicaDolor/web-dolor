<?php

declare(strict_types=1);

/**
 * M03 UNKNOWN/NOT_REMEMBERED additive migration preparation.
 *
 * This tool is intentionally not part of the application bootstrap. It is
 * fail-closed, allowlisted to the synthetic database, and defaults to a
 * read-only preflight. The apply mode is present for the separately
 * authorized synthetic phase but is not executed by this change.
 *
 * Modes:
 *   --mode=preflight  inspect the real schema without writes
 *   --mode=apply      create a durable snapshot and add the columns
 *   --mode=restore    restore the pre-DDL table from a snapshot
 */

$root = dirname(__DIR__, 2);
$options = [];
foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--')) continue;
    [$key, $value] = array_pad(explode('=', $argument, 2), 2, '1');
    $options[substr($key, 2)] = $value;
}

$mode = (string)($options['mode'] ?? 'preflight');
$database = (string)($options['database'] ?? '');
$snapshotPath = (string)($options['snapshot'] ?? '');
$allowWrite = array_key_exists('allow-synthetic-write', $options);
$table = 'pac_respuestas_paciente_modulo_3';
$columnsToAdd = [
    'estado_respuesta' => 'VARCHAR(24) NULL',
    'origen_respuesta' => 'VARCHAR(32) NULL',
    'estado_respuesta_at' => 'DATETIME NULL',
];
$result = [
    'phase' => 'M03_DURABLE_UNKNOWN_CONTRACT_V1',
    'mode' => $mode,
    'database' => $database,
    'table' => $table,
    'read_only' => $mode !== 'apply' && $mode !== 'restore',
    'migration_execution' => 'NOT_EXECUTED',
    'errors' => [],
];

$fail = static function (string $message) use (&$result): never {
    $result['status'] = 'BLOCKED';
    $result['errors'][] = $message;
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
};

if (!in_array($mode, ['preflight', 'apply', 'restore'], true)) $fail('Modo M03 no válido.');
if ($database !== 'clinica_dolor_demo_20261010_final') $fail('Sólo se permite la base sintética autorizada.');
if ($mode !== 'preflight' && !$allowWrite) $fail('Apply/restore requieren --allow-synthetic-write explícito.');
if ($mode !== 'preflight' && $snapshotPath === '') $fail('Apply/restore requieren --snapshot=<ruta durable>.');

$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) if (!array_key_exists($key, $env)) $fail('Falta configuración de conexión local.');
if (!in_array(strtolower((string)$env['DB_HOST']), ['127.0.0.1', 'localhost', '::1'], true) || (string)$env['DB_PORT'] !== '3306') $fail('Sólo se permite MySQL local en el puerto 3306.');

try {
    $db = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $database), $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
} catch (Throwable) {
    $fail('No fue posible conectar con la base sintética. No se ejecutó DDL ni escritura clínica.');
}

if ((string)$db->query('SELECT DATABASE()')->fetchColumn() !== $database) $fail('SELECT DATABASE() no coincide con la base autorizada.');

$exists = static function (PDO $db, string $name): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :name');
    $stmt->execute([':name' => $name]);
    return (int)$stmt->fetchColumn() === 1;
};
$columns = static function (PDO $db, string $name): array {
    $stmt = $db->prepare('SELECT column_name, data_type, is_nullable, column_default, column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :name ORDER BY ordinal_position');
    $stmt->execute([':name' => $name]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};

if (!$exists($db, $table)) $fail('No existe la tabla canónica de respuestas M03.');
$currentColumns = $columns($db, $table);
$columnNames = array_map(static fn(array $row): string => (string)$row['column_name'], $currentColumns);
$baseRequired = ['id', 'id_pregunta', 'id_paciente', 'respuesta'];
if (array_diff($baseRequired, $columnNames) !== []) $fail('La tabla M03 no cumple el contrato base esperado.');
$present = array_values(array_intersect(array_keys($columnsToAdd), $columnNames));
$missing = array_values(array_diff(array_keys($columnsToAdd), $columnNames));
$result['current_columns'] = $currentColumns;
$result['additive_columns_present'] = $present;
$result['additive_columns_missing'] = $missing;
$result['partial_extension'] = $present !== [] && $missing !== [];
if ($result['partial_extension']) $fail('La extensión M03 está parcialmente aplicada; no se completa automáticamente.');

if ($mode === 'preflight') {
    $result['state_column_design'] = 'estado_respuesta VARCHAR(24) NULL; origen_respuesta VARCHAR(32) NULL; estado_respuesta_at DATETIME NULL';
    $result['historical_policy'] = 'No backfill automático; NULL conserva semántica histórica no clasificada.';
    $result['status'] = $missing === [] ? 'PASS_ALREADY_PRESENT' : 'PASS_ADDITIVE_DDL_READY';
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

if (!is_file($snapshotPath) && $mode === 'restore') $fail('No existe el snapshot indicado para restauración.');

if ($mode === 'apply') {
    if ($missing === []) {
        $result['status'] = 'PASS_ALREADY_PRESENT';
        $result['migration_execution'] = 'NOT_NEEDED';
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    }
    $snapshot = [
        'version' => 1,
        'database' => $database,
        'table' => $table,
        'columns' => $currentColumns,
        'rows' => $db->query('SELECT * FROM pac_respuestas_paciente_modulo_3 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        'historical_policy' => 'No se clasifican automáticamente respuestas históricas.',
    ];
    $snapshot['sha256'] = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $directory = dirname($snapshotPath);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) $fail('No se pudo preparar el directorio durable del snapshot.');
    if (file_put_contents($snapshotPath, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) $fail('No se pudo crear el snapshot antes del DDL.');
    try {
        $snapshotDisk = json_decode((string)file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR);
        $snapshotDiskHash = (string)($snapshotDisk['sha256'] ?? '');
        unset($snapshotDisk['sha256']);
        $snapshotDiskComputedHash = hash('sha256', json_encode($snapshotDisk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        if ($snapshotDiskHash === '' || !hash_equals($snapshot['sha256'], $snapshotDiskComputedHash)) {
            $fail('El snapshot escrito no supera la verificación SHA-256 previa al DDL.');
        }
    } catch (Throwable) {
        $fail('No se pudo volver a leer y verificar el snapshot antes del DDL.');
    }
    $result['snapshot'] = 'CREATED_BEFORE_DDL';
    $result['snapshot_verified_before_ddl'] = 'PASS_SHA256';
    $definitions = [];
    foreach ($missing as $column) $definitions[] = '`' . $column . '` ' . $columnsToAdd[$column];
    try {
        $db->exec('ALTER TABLE `' . $table . '` ADD COLUMN ' . implode(', ADD COLUMN ', $definitions));
    } catch (Throwable) {
        $fail('El DDL falló; conservar el snapshot para revisión y restauración manual.');
    }
    $result['migration_execution'] = 'DDL_APPLIED_SYNTHETIC_ONLY';
    $result['historical_backfill'] = 'NOT_EXECUTED';
    $result['status'] = 'PASS_APPLIED_SYNTHETIC';
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$snapshot = json_decode((string)file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR);
if (($snapshot['database'] ?? '') !== $database || ($snapshot['table'] ?? '') !== $table || !isset($snapshot['columns'], $snapshot['rows'], $snapshot['sha256']) || !is_array($snapshot['columns']) || !is_array($snapshot['rows'])) $fail('El snapshot no corresponde al contrato M03.');
$snapshotPayload = $snapshot;
$snapshotHash = (string)($snapshotPayload['sha256'] ?? '');
unset($snapshotPayload['sha256']);
$computedSnapshotHash = hash('sha256', json_encode($snapshotPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
if ($snapshotHash === '' || !hash_equals($snapshotHash, $computedSnapshotHash)) $fail('La integridad SHA-256 del snapshot no es válida; no se ejecutó restauración.');
$currentAfter = $columns($db, $table);
$currentAfterNames = array_map(static fn(array $row): string => (string)$row['column_name'], $currentAfter);
$originalNames = array_map(static fn(array $row): string => (string)$row['column_name'], $snapshot['columns']);
$expectedNames = array_merge($originalNames, array_values(array_diff(array_keys($columnsToAdd), $originalNames)));
if (array_diff($expectedNames, $currentAfterNames) !== [] || array_diff($currentAfterNames, $expectedNames) !== []) $fail('El esquema actual no coincide exactamente con el snapshot y la extensión M03 esperada.');
$drop = array_values(array_diff(array_keys($columnsToAdd), $originalNames));
if ($drop === []) $fail('El snapshot no representa un esquema previo a la migración.');
try {
    $db->beginTransaction();
    $db->exec('DELETE FROM `' . $table . '`');
    $rowColumns = array_keys($snapshot['rows'][0] ?? []);
    if ($rowColumns !== []) {
        $quoted = implode(',', array_map(static fn(string $column): string => '`' . $column . '`', $rowColumns));
        $placeholders = implode(',', array_fill(0, count($rowColumns), '?'));
        $insert = $db->prepare('INSERT INTO `' . $table . '` (' . $quoted . ') VALUES (' . $placeholders . ')');
        foreach ($snapshot['rows'] as $row) $insert->execute(array_map(static fn(string $column): mixed => $row[$column] ?? null, $rowColumns));
    }
    $db->commit();
    foreach ($drop as $column) $db->exec('ALTER TABLE `' . $table . '` DROP COLUMN `' . $column . '`');
} catch (Throwable) {
    if ($db->inTransaction()) $db->rollBack();
    $fail('La restauración M03 no fue completada; conservar el snapshot y detenerse.');
}
$result['migration_execution'] = 'RESTORED_PRE_MIGRATION_SCHEMA';
$result['status'] = 'PASS_RESTORED_SYNTHETIC';
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
