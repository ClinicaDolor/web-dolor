<?php

declare(strict_types=1);

/**
 * Focused, additive M07 link migration for unlisted medications.
 *
 * This migration is deliberately separate from the historic M07 fixture
 * migration because that tool also performs backfill. No data backfill is
 * performed here. Every mode pins the connection to the one approved local
 * synthetic database.
 *
 * Modes: preflight, backup, apply, verify.
 */

ini_set('display_errors', '0');

$options = [];
foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--')) continue;
    [$key, $value] = array_pad(explode('=', $argument, 2), 2, '1');
    $options[substr($key, 2)] = $value;
}

$mode = (string)($options['mode'] ?? '');
$GLOBALS['mode'] = $mode;
$database = (string)($options['database'] ?? '');
$backupPath = (string)($options['backup'] ?? '');
$expectedDatabase = 'clinica_dolor_demo_20261010_final';
$table = 'pac_m07_medicamentos_no_catalogados';
$questionTable = 'pac_preguntas_modulo_7';
$column = 'id_pregunta';
$indexName = 'm07_other_question_idx';
$foreignKeyName = 'm07_other_question_fk';

$fail = static function (string $step, string $code, string $message): never {
    echo json_encode([
        'task' => 'M07_CANONICAL_OTHER_QUESTION_LINK',
        'mode' => $GLOBALS['mode'] ?? 'unknown',
        'status' => 'BLOCKED',
        'failed_step' => $step,
        'failure_code' => $code,
        'message_sanitized' => $message,
        'ddl_executed' => 'NO',
        'dml_executed' => 'NO',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
};

if (PHP_SAPI !== 'cli') $fail('runtime', 'CLI_REQUIRED', 'Esta migración sólo admite ejecución CLI.');
if (!in_array($mode, ['preflight', 'backup', 'apply', 'verify'], true)) $fail('arguments', 'MODE_INVALID', 'Modo no permitido.');
if ($database !== $expectedDatabase) $fail('database_allowlist', 'DATABASE_BLOCKED', 'La base no coincide con la allowlist sintética.');
if (in_array($mode, ['backup', 'apply'], true) && $backupPath === '') $fail('backup_argument', 'BACKUP_REQUIRED', 'El modo requiere una ruta de respaldo explícita.');

$root = dirname(__DIR__, 2);
$envPath = $root . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envPath)) $fail('configuration', 'LOCAL_CONFIG_MISSING', 'No existe la configuración local necesaria.');
$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $required) {
    if (!array_key_exists($required, $env)) $fail('configuration', 'LOCAL_CONFIG_INCOMPLETE', 'Falta configuración de conexión.');
}
if (!in_array(strtolower((string)$env['DB_HOST']), ['localhost', '127.0.0.1', '::1'], true)
    || (string)$env['DB_PORT'] !== '3306') {
    $fail('connection_allowlist', 'LOCAL_MYSQL_ONLY', 'Sólo se admite MySQL local en el puerto autorizado.');
}

try {
    $db = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $expectedDatabase),
        $env['DB_USERNAME'],
        $env['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $effective = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    if ($effective !== $expectedDatabase) $fail('database_guard', 'EFFECTIVE_DATABASE_MISMATCH', 'La conexión efectiva no es la base sintética autorizada.');
    $server = $db->query('SELECT @@hostname AS host, @@port AS port, VERSION() AS version')->fetch();
} catch (Throwable) {
    $fail('connection', 'PDO_CONNECTION_FAILED', 'No fue posible verificar la conexión local autorizada.');
}

$columnInfo = static function (PDO $db, string $tableName, string $columnName): ?array {
    $statement = $db->prepare('SELECT column_name, column_type, is_nullable FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
    $statement->execute([$tableName, $columnName]);
    return $statement->fetch() ?: null;
};
$tableInfo = static function (PDO $db, string $tableName): ?array {
    $statement = $db->prepare('SELECT engine FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $statement->execute([$tableName]);
    return $statement->fetch() ?: null;
};
$primaryKey = static function (PDO $db, string $tableName): ?array {
    $statement = $db->prepare("SELECT c.column_name,c.column_type,c.is_nullable FROM information_schema.columns c JOIN information_schema.key_column_usage k ON k.table_schema=c.table_schema AND k.table_name=c.table_name AND k.column_name=c.column_name WHERE c.table_schema=DATABASE() AND c.table_name=? AND k.constraint_name='PRIMARY' ORDER BY k.ordinal_position LIMIT 1");
    $statement->execute([$tableName]);
    return $statement->fetch() ?: null;
};
$indexInfo = static function (PDO $db, string $tableName, string $name): ?array {
    $statement = $db->prepare('SELECT index_name,non_unique,GROUP_CONCAT(column_name ORDER BY seq_in_index) AS columns_in_index FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=? GROUP BY index_name,non_unique');
    $statement->execute([$tableName, $name]);
    return $statement->fetch() ?: null;
};
$foreignKeyInfo = static function (PDO $db, string $tableName, string $name): ?array {
    $statement = $db->prepare('SELECT k.constraint_name,k.column_name,k.referenced_table_name,k.referenced_column_name,r.delete_rule,r.update_rule FROM information_schema.key_column_usage k LEFT JOIN information_schema.referential_constraints r ON r.constraint_schema=k.constraint_schema AND r.constraint_name=k.constraint_name WHERE k.table_schema=DATABASE() AND k.table_name=? AND k.constraint_name=? ORDER BY k.ordinal_position');
    $statement->execute([$tableName, $name]);
    return $statement->fetch() ?: null;
};

$targetTable = $tableInfo($db, $table);
$questionId = $primaryKey($db, $questionTable);
$questionTheme = $columnInfo($db, $questionTable, 'id_tema');
$themeId = $columnInfo($db, 'pac_temas_modulo_7', 'id');
$patientId = $columnInfo($db, 'pc_paciente', 'id');
$clinicId = $columnInfo($db, 'clinicas', 'id');
if ($targetTable === null || $questionId === null || $questionTheme === null || $themeId === null || $patientId === null || $clinicId === null) {
    $fail('schema_preflight', 'REQUIRED_SCHEMA_MISSING', 'Falta una tabla o columna de relación necesaria.');
}
if (strcasecmp((string)$targetTable['engine'], 'InnoDB') !== 0) $fail('schema_preflight', 'ENGINE_NOT_SUPPORTED', 'La tabla afectada no es InnoDB.');
if (strcasecmp((string)$questionTheme['column_type'], (string)$themeId['column_type']) !== 0) $fail('schema_preflight', 'QUESTION_THEME_TYPE_MISMATCH', 'La referencia de tema de la pregunta no coincide en tipo con la clave de tema.');
$questionTableInfo = $tableInfo($db, $questionTable);
if ($questionTableInfo === null || strcasecmp((string)$questionTableInfo['engine'], 'InnoDB') !== 0) {
    $fail('schema_preflight', 'QUESTION_TABLE_ENGINE_UNSUPPORTED', 'La tabla de preguntas no permite crear la relación segura.');
}
if ((string)$questionId['column_name'] !== 'id') $fail('schema_preflight', 'QUESTION_PRIMARY_KEY_UNEXPECTED', 'La clave primaria de preguntas no coincide con el contrato canónico.');
$approvedQuestionIds = [8, 15, 22, 30, 37, 46];
$questionStatement = $db->query('SELECT id,pregunta FROM ' . $questionTable . ' WHERE id IN (8,15,22,30,37,46) ORDER BY id');
$questionRows = $questionStatement->fetchAll();
$actualQuestionIds = array_map(static fn(array $row): int => (int)$row['id'], $questionRows);
$allAreOther = count($questionRows) === count($approvedQuestionIds)
    && $actualQuestionIds === $approvedQuestionIds
    && count(array_filter($questionRows, static fn(array $row): bool => preg_match('/^otros?$/iu', trim((string)$row['pregunta'])) === 1)) === count($approvedQuestionIds);
if (!$allAreOther) $fail('catalog_preflight', 'APPROVED_OTHER_QUESTION_SET_MISMATCH', 'El catálogo no coincide con las seis preguntas «Otro» autorizadas.');

$requiredOtherColumns = ['id','entry_key','id_paciente','id_clinica','ambito','id_tema','nombre','nombre_normalizado','estado_registro','respuesta','respuesta_state','dosis','dosis_state','consumo','consumo_state','eficacia_codigo','eficacia_state','tolerabilidad_codigo','tolerabilidad_state','descripcion_efectos','descripcion_efectos_state','version','provenance','created_at','updated_at','created_by','updated_by'];
$columnList = $db->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');
$columnList->execute([$table]);
$actualOtherColumns = array_map('strval', $columnList->fetchAll(PDO::FETCH_COLUMN));
$missingOtherColumns = array_values(array_diff($requiredOtherColumns, $actualOtherColumns));
if ($missingOtherColumns !== []) $fail('schema_preflight', 'OTHER_TABLE_SHAPE_MISMATCH', 'La estructura existente no coincide con el contrato esperado.');

$existingLink = $columnInfo($db, $table, $column);
$existingIndex = $indexInfo($db, $table, $indexName);
$existingForeignKey = $foreignKeyInfo($db, $table, $foreignKeyName);
if ($existingLink !== null && (strcasecmp((string)$existingLink['column_type'], (string)$questionId['column_type']) !== 0 || $existingLink['is_nullable'] !== 'YES')) {
    $fail('existing_link', 'LINK_COLUMN_INCOMPATIBLE', 'La columna de vínculo existente tiene una definición incompatible.');
}
if ($existingIndex !== null && ((int)$existingIndex['non_unique'] !== 1 || $existingIndex['columns_in_index'] !== $column)) {
    $fail('existing_index', 'LINK_INDEX_INCOMPATIBLE', 'El índice de vínculo existente no coincide con lo esperado.');
}
if ($existingForeignKey !== null && ($existingForeignKey['column_name'] !== $column || $existingForeignKey['referenced_table_name'] !== $questionTable || $existingForeignKey['referenced_column_name'] !== $questionId['column_name'] || $existingForeignKey['delete_rule'] !== 'RESTRICT' || $existingForeignKey['update_rule'] !== 'RESTRICT')) {
    $fail('existing_foreign_key', 'LINK_FOREIGN_KEY_INCOMPATIBLE', 'La relación existente no coincide con lo esperado.');
}
if ($existingLink !== null && ($existingIndex === null || $existingForeignKey === null)) {
    $fail('existing_link', 'PARTIAL_LINK_SCHEMA', 'El vínculo existente está incompleto; no se intentará repararlo automáticamente.');
}

$createTable = (string)$db->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
$count = (int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
$nullCount = $existingLink === null ? $count : (int)$db->query('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' IS NULL')->fetchColumn();
$result = [
    'task' => 'M07_CANONICAL_OTHER_QUESTION_LINK',
    'mode' => $mode,
    'status' => 'PASS',
    'database_effective' => $effective,
    'database_server' => (string)($server['host'] ?? 'UNKNOWN'),
    'database_port' => (int)($server['port'] ?? 0),
    'database_version' => (string)($server['version'] ?? 'UNKNOWN'),
    'table' => $table,
    'table_engine' => (string)$targetTable['engine'],
    'question_primary_key_type' => (string)$questionId['column_type'],
    'question_primary_key_nullable' => (string)$questionId['is_nullable'],
    'question_theme_type' => (string)$questionTheme['column_type'],
    'existing_link_state' => $existingLink === null ? 'ABSENT' : 'COMPATIBLE',
    'existing_index_state' => $existingIndex === null ? 'ABSENT' : 'COMPATIBLE',
    'existing_fk_state' => $existingForeignKey === null ? 'ABSENT' : 'COMPATIBLE',
    'row_count' => $count,
    'unlinked_row_count' => $nullCount,
    'schema_sha256' => hash('sha256', $createTable),
    'ddl_executed' => 'NO',
    'dml_executed' => 'NO',
];

$readAllRows = static function (PDO $db) use ($table): array {
    return $db->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
};
$rowsHash = static fn(array $rows): string => hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

if ($mode === 'backup') {
    $path = realpath(dirname($backupPath));
    if ($path === false || !is_dir($path) || is_link(dirname($backupPath)) || file_exists($backupPath)) {
        $fail('backup_path', 'BACKUP_PATH_INVALID', 'El directorio de respaldo no existe, la ruta no es segura o el archivo ya existe.');
    }
    if (str_starts_with(strtolower($path . DIRECTORY_SEPARATOR), strtolower($root . DIRECTORY_SEPARATOR))) {
        $fail('backup_path', 'BACKUP_MUST_BE_OUTSIDE_REPOSITORY', 'El respaldo debe quedar fuera del repositorio.');
    }
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->beginTransaction();
    try {
        $firstRows = $readAllRows($db);
        $secondRows = $readAllRows($db);
        if (!hash_equals($rowsHash($firstRows), $rowsHash($secondRows))) throw new RuntimeException('SNAPSHOT_READS_DIFFER');
        $db->commit();
    } catch (Throwable) {
        if ($db->inTransaction()) $db->rollBack();
        $fail('backup_snapshot', 'CONSISTENT_SNAPSHOT_FAILED', 'No se pudo obtener una lectura consistente del respaldo.');
    }
    $backup = [
        'format' => 'M07_OTHER_TABLE_RECOVERABLE_SNAPSHOT_V1',
        'created_at_utc' => gmdate('c'),
        'database' => $effective,
        'table' => $table,
        'schema_sql' => $createTable,
        'schema_sha256' => hash('sha256', $createTable),
        'row_count' => count($firstRows),
        'rows_sha256' => $rowsHash($firstRows),
        'rows' => $firstRows,
    ];
    $backup['snapshot_sha256'] = hash('sha256', json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $json = json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    $handle = @fopen($backupPath, 'xb');
    if ($handle === false) $fail('backup_write', 'BACKUP_CREATE_FAILED', 'No se pudo crear el archivo de respaldo exclusivo.');
    try {
        if (fwrite($handle, $json) !== strlen($json)) throw new RuntimeException('SHORT_WRITE');
        fflush($handle);
    } catch (Throwable) {
        fclose($handle);
        @unlink($backupPath);
        $fail('backup_write', 'BACKUP_WRITE_FAILED', 'No se completó el archivo de respaldo.');
    }
    fclose($handle);
    $stored = json_decode((string)@file_get_contents($backupPath), true);
    if (!is_array($stored) || ($stored['database'] ?? '') !== $expectedDatabase || ($stored['table'] ?? '') !== $table
        || (int)($stored['row_count'] ?? -1) !== count($firstRows) || !hash_equals($rowsHash($firstRows), (string)($stored['rows_sha256'] ?? ''))) {
        $fail('backup_verify', 'BACKUP_CONTENT_MISMATCH', 'El respaldo durable no coincide con su manifiesto.');
    }
    $manifest = $stored;
    $storedSnapshotHash = (string)($manifest['snapshot_sha256'] ?? '');
    unset($manifest['snapshot_sha256']);
    if (!hash_equals($storedSnapshotHash, hash('sha256', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)))) {
        $fail('backup_verify', 'BACKUP_MANIFEST_HASH_MISMATCH', 'La huella interna del respaldo no coincide.');
    }
    if (!hash_equals(hash('sha256', $json), hash_file('sha256', $backupPath) ?: '')) $fail('backup_verify', 'BACKUP_FILE_HASH_MISMATCH', 'La verificación del archivo de respaldo falló.');
    $result['backup_path'] = $backupPath;
    $result['backup_rows'] = count($firstRows);
    $result['backup_rows_sha256'] = $rowsHash($firstRows);
    $result['backup_file_sha256'] = hash_file('sha256', $backupPath) ?: 'UNAVAILABLE';
    $result['backup_verified'] = 'PASS';
}

$ddlAppliedNow = false;
if ($mode === 'apply') {
    if (!is_file($backupPath) || is_link($backupPath)) $fail('backup_guard', 'VERIFIED_BACKUP_REQUIRED', 'Se requiere el respaldo durable creado antes del DDL.');
    $snapshot = json_decode((string)file_get_contents($backupPath), true);
    if (!is_array($snapshot) || ($snapshot['format'] ?? '') !== 'M07_OTHER_TABLE_RECOVERABLE_SNAPSHOT_V1'
        || ($snapshot['database'] ?? '') !== $expectedDatabase || ($snapshot['table'] ?? '') !== $table) {
        $fail('backup_guard', 'BACKUP_IDENTITY_INVALID', 'El respaldo no corresponde a la tabla y base autorizadas.');
    }
    $storedSnapshotHash = (string)($snapshot['snapshot_sha256'] ?? '');
    $snapshotForHash = $snapshot;
    unset($snapshotForHash['snapshot_sha256']);
    if (!hash_equals($storedSnapshotHash, hash('sha256', json_encode($snapshotForHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)))
        || !hash_equals((string)($snapshot['schema_sha256'] ?? ''), hash('sha256', (string)($snapshot['schema_sql'] ?? '')))
        || count($snapshot['rows'] ?? []) !== (int)($snapshot['row_count'] ?? -1)
        || !hash_equals((string)($snapshot['rows_sha256'] ?? ''), $rowsHash($snapshot['rows'] ?? []))) {
        $fail('backup_guard', 'BACKUP_INTEGRITY_INVALID', 'La integridad del respaldo no pudo demostrarse.');
    }
    if ($existingLink !== null) {
        $result['ddl_executed'] = 'NO_ALREADY_APPLIED';
        $result['backup_verified'] = 'PASS_INTEGRITY';
    } else {
        $currentRows = $readAllRows($db);
        if (count($currentRows) !== (int)$snapshot['row_count'] || !hash_equals((string)$snapshot['rows_sha256'], $rowsHash($currentRows))) {
            $fail('pre_ddl_state_guard', 'TABLE_CHANGED_SINCE_BACKUP', 'La tabla cambió desde el respaldo; no se ejecutó DDL.');
        }
        $currentCreate = (string)$db->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
        if (!hash_equals((string)$snapshot['schema_sha256'], hash('sha256', $currentCreate))) {
            $fail('pre_ddl_schema_guard', 'SCHEMA_CHANGED_SINCE_BACKUP', 'El esquema cambió desde el respaldo; no se ejecutó DDL.');
        }
        if ($existingIndex !== null || $existingForeignKey !== null) {
            $fail('pre_ddl_schema_guard', 'PARTIAL_LINK_SCHEMA', 'Hay componentes parciales del vínculo; no se intentará repararlos.');
        }
        $quotedType = (string)$questionId['column_type'];
        if (preg_match('/^bigint(?:\([0-9]+\))?(?: unsigned)?$/i', $quotedType) !== 1
            && preg_match('/^int(?:\([0-9]+\))?(?: unsigned)?$/i', $quotedType) !== 1
            && preg_match('/^mediumint(?:\([0-9]+\))?(?: unsigned)?$/i', $quotedType) !== 1
            && preg_match('/^smallint(?:\([0-9]+\))?(?: unsigned)?$/i', $quotedType) !== 1
            && preg_match('/^tinyint(?:\([0-9]+\))?(?: unsigned)?$/i', $quotedType) !== 1) {
            $fail('pre_ddl_type_guard', 'QUESTION_KEY_TYPE_UNSUPPORTED', 'El tipo de la clave de pregunta no está dentro de los tipos enteros seguros.');
        }
        try {
            $db->exec('ALTER TABLE ' . $table
                . ' ADD COLUMN ' . $column . ' ' . $quotedType . ' NULL DEFAULT NULL'
                . ', ADD INDEX ' . $indexName . ' (' . $column . ')'
                . ', ADD CONSTRAINT ' . $foreignKeyName . ' FOREIGN KEY (' . $column . ') REFERENCES ' . $questionTable . ' (' . $questionId['column_name'] . ') ON UPDATE RESTRICT ON DELETE RESTRICT');
        } catch (Throwable) {
            $fail('ddl', 'ADDITIVE_DDL_FAILED', 'La migración no pudo aplicar el cambio aditivo; inspeccione el estado antes de reintentar.');
        }
        $ddlAppliedNow = true;
        $result['ddl_executed'] = 'YES';
    }
}

if ($mode === 'verify' || $mode === 'apply') {
    $linkAfter = $columnInfo($db, $table, $column);
    $indexAfter = $indexInfo($db, $table, $indexName);
    $fkAfter = $foreignKeyInfo($db, $table, $foreignKeyName);
    if ($linkAfter === null || strcasecmp((string)$linkAfter['column_type'], (string)$questionId['column_type']) !== 0 || $linkAfter['is_nullable'] !== 'YES'
        || $indexAfter === null || (int)$indexAfter['non_unique'] !== 1 || $indexAfter['columns_in_index'] !== $column
        || $fkAfter === null || $fkAfter['column_name'] !== $column || $fkAfter['referenced_table_name'] !== $questionTable || $fkAfter['referenced_column_name'] !== $questionId['column_name'] || $fkAfter['delete_rule'] !== 'RESTRICT' || $fkAfter['update_rule'] !== 'RESTRICT') {
        $fail('post_ddl_verify', 'LINK_SCHEMA_VERIFY_FAILED', 'La relación posterior no coincide con el contrato esperado.');
    }
    $countAfter = (int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    $nullAfter = (int)$db->query('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' IS NULL')->fetchColumn();
    if ($mode === 'apply' && $ddlAppliedNow) {
        if ($countAfter !== (int)($snapshot['row_count'] ?? -1) || $nullAfter !== $countAfter) {
            $fail('post_ddl_verify', 'HISTORICAL_ROWS_CHANGED', 'Las filas históricas no permanecen intactas y sin vínculo asignado.');
        }
        $postRows = $readAllRows($db);
        foreach ($postRows as &$postRow) unset($postRow[$column]);
        unset($postRow);
        if (!hash_equals((string)$snapshot['rows_sha256'], $rowsHash($postRows))) {
            $fail('post_ddl_verify', 'HISTORICAL_ROW_CONTENT_CHANGED', 'El contenido histórico no coincide con el respaldo previo.');
        }
    } elseif ($countAfter !== $count) {
        $fail('post_ddl_verify', 'ROW_COUNT_UNSTABLE', 'El conteo de la tabla cambió durante la lectura de verificación.');
    }
    $result['link_column_after'] = ['name' => $column, 'type' => (string)$linkAfter['column_type'], 'nullable' => (string)$linkAfter['is_nullable']];
    $result['link_index_after'] = 'PASS';
    $result['link_fk_after'] = 'PASS_RESTRICT_NO_CASCADE';
    $result['row_count_after'] = $countAfter;
    $result['historical_null_links_after'] = $nullAfter;
    $result['historical_rows_preserved'] = $ddlAppliedNow ? 'PASS_FULL_ROW_COMPARE_AND_NULL_LINKS' : 'NOT_REWRITTEN_BY_READ_ONLY_VERIFY';
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
