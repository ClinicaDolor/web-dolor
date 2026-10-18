<?php

declare(strict_types=1);

/**
 * Focal M07 synthetic restoration.
 *
 * This is intentionally not part of the application. It is a manual,
 * fail-closed maintenance tool for the owner-approved synthetic fixture.
 * PRECHECK performs reads only. RESTORE updates one existing row only after
 * every precondition has been revalidated inside a transaction.
 */

ini_set('display_errors', '0');

const AUTHORIZED_DATABASE = 'clinica_dolor_demo_20261010_final';
const PATIENT_A = 9301;
const PATIENT_B = 9302;
const CLINIC_A = 9101;
const CLINIC_B = 9102;
const TARGET_QUESTION = 1;

const TARGET_COLUMNS = [
    'm07_respuesta_state',
    'm07_dosis_state',
    'm07_resultados_state',
    'm07_consumo_state',
    'm07_descripcion_state',
    'm07_eficacia_codigo',
    'm07_eficacia_state',
    'm07_tolerabilidad_state',
    'm07_efectos_descripcion_state',
    'm07_version',
    'm07_updated_at',
    'm07_updated_by',
    'm07_provenance',
];

const M07_TABLES = [
    'pac_respuestas_paciente_modulo_7',
    'pac_m07_medicamentos_no_catalogados',
    'pac_m07_operaciones',
    'pac_m07_revision_clinica',
];

final class FocalGuardException extends RuntimeException {}

$options = [];
foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--')) {
        continue;
    }
    [$key, $value] = array_pad(explode('=', $argument, 2), 2, '1');
    $options[substr($key, 2)] = $value;
}

$mode = strtolower(trim((string)($options['mode'] ?? '')));
$database = (string)($options['database'] ?? '');
$originalPath = (string)($options['original-snapshot'] ?? '');
$postFailurePath = (string)($options['post-failure-snapshot'] ?? '');

$report = [
    'phase' => 'M07_AUTHORIZED_FOCAL_RESTORATION',
    'owner_authorization' => 'YES',
    'database' => $database,
    'patient_id' => PATIENT_A,
    'question_id' => TARGET_QUESTION,
    'target_rows' => 1,
    'target_columns' => count(TARGET_COLUMNS),
    'original_snapshot' => is_file($originalPath) ? 'PRESENT' : 'MISSING',
    'post_failure_snapshot' => is_file($postFailurePath) ? 'PRESENT' : 'MISSING',
    'precheck_result' => 'NOT_RUN',
    'database_guard' => 'NOT_RUN',
    'tenant_guard' => 'NOT_RUN',
    'primary_key_verified' => 'NOT_RUN',
    'current_state_matches_post_failure' => 'NOT_RUN',
    'only_authorized_differences' => 'NOT_RUN',
    'triggers_guard' => 'NOT_RUN',
    'transaction_started' => 'NOT_STARTED',
    'row_lock_verified' => 'NOT_REACHED',
    'rows_updated' => 'NOT_REACHED',
    'post_update_row_match' => 'NOT_REACHED',
    'commit_status' => 'NOT_EXECUTED',
    'rollback_status' => 'NOT_EXECUTED',
    'independent_baseline_comparison' => 'NOT_EXECUTED',
    'different_tables' => [],
    'mysql_writes' => 'NO',
    'errors' => [],
];

$emit = static function (array $payload, int $exitCode): never {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($exitCode);
};

$fail = static function (string $step, string $message) use (&$report, $emit): never {
    $report['precheck_result'] = $report['precheck_result'] === 'PASS' ? 'FAIL' : 'BLOCKED';
    $report['first_failed_step'] = $step;
    $report['error_message_sanitized'] = $message;
    $report['errors'][] = $message;
    $emit($report, 2);
};

$parseEnv = static function (string $path): array {
    if (!is_file($path)) {
        throw new FocalGuardException('No existe la configuración local requerida.');
    }
    $env = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
    foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
        if (!array_key_exists($key, $env)) {
            throw new FocalGuardException('Falta configuración local de conexión.');
        }
    }
    if (!in_array(strtolower((string)$env['DB_HOST']), ['127.0.0.1', 'localhost', '::1'], true)
        || (string)$env['DB_PORT'] !== '3306') {
        throw new FocalGuardException('La conexión no apunta a MySQL local en el puerto 3306.');
    }
    return $env;
};

$connect = static function (array $env): PDO {
    try {
        $db = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], AUTHORIZED_DATABASE),
            $env['DB_USERNAME'],
            $env['DB_PASSWORD'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (Throwable) {
        throw new FocalGuardException('No fue posible conectar con MySQL local.');
    }
    if ((string)$db->query('SELECT DATABASE()')->fetchColumn() !== AUTHORIZED_DATABASE) {
        throw new FocalGuardException('La base activa no coincide con la allowlist.');
    }
    return $db;
};

$loadSnapshot = static function (string $path): array {
    if (!is_file($path)) {
        throw new FocalGuardException('No existe un snapshot autorizado.');
    }
    try {
        $snapshot = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        throw new FocalGuardException('El snapshot no es JSON válido.');
    }
    if (!is_array($snapshot)
        || ($snapshot['schema_version'] ?? '') !== 'M07_CLINICAL_BASELINE_V1'
        || ($snapshot['database'] ?? '') !== AUTHORIZED_DATABASE
        || ($snapshot['database_host_scope'] ?? '') !== 'LOCAL_MYSQL_ONLY'
        || ($snapshot['patient_ids'] ?? []) !== [PATIENT_A, PATIENT_B]
        || ($snapshot['clinic_ids'] ?? []) !== [CLINIC_A, CLINIC_B]
        || ($snapshot['tables'] ?? []) !== M07_TABLES
        || !is_array($snapshot['rows'] ?? null)
        || !is_array($snapshot['manifest'] ?? null)
        || !is_string($snapshot['snapshot_sha256'] ?? null)) {
        throw new FocalGuardException('El snapshot no cumple el contrato M07 autorizado.');
    }
    $copy = $snapshot;
    unset($copy['snapshot_sha256']);
    $canonicalHash = hash('sha256', json_encode($copy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    if (!hash_equals((string)$snapshot['snapshot_sha256'], $canonicalHash)) {
        throw new FocalGuardException('La huella del snapshot no coincide con su contenido.');
    }
    foreach (M07_TABLES as $table) {
        $rows = $snapshot['rows'][$table] ?? null;
        $manifest = $snapshot['manifest'][$table] ?? null;
        if (!is_array($rows) || !is_array($manifest)
            || (int)($manifest['row_count'] ?? -1) !== count($rows)) {
            throw new FocalGuardException('El manifiesto del snapshot no coincide con sus filas.');
        }
        $rowsHash = hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        if (!hash_equals((string)($manifest['sha256'] ?? ''), $rowsHash)) {
            throw new FocalGuardException('La huella de una tabla del snapshot no coincide.');
        }
    }
    return $snapshot;
};

$readRows = static function (PDO $db): array {
    $queries = [
        'pac_respuestas_paciente_modulo_7' => [
            'SELECT * FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente IN (?, ?) ORDER BY id',
            [PATIENT_A, PATIENT_B],
        ],
        'pac_m07_medicamentos_no_catalogados' => [
            'SELECT * FROM pac_m07_medicamentos_no_catalogados WHERE (id_paciente = ? AND id_clinica = ?) OR (id_paciente = ? AND id_clinica = ?) ORDER BY id',
            [PATIENT_A, CLINIC_A, PATIENT_B, CLINIC_B],
        ],
        'pac_m07_operaciones' => [
            'SELECT * FROM pac_m07_operaciones WHERE (id_paciente = ? AND id_clinica = ?) OR (id_paciente = ? AND id_clinica = ?) ORDER BY id',
            [PATIENT_A, CLINIC_A, PATIENT_B, CLINIC_B],
        ],
        'pac_m07_revision_clinica' => [
            'SELECT * FROM pac_m07_revision_clinica WHERE (id_paciente = ? AND id_clinica = ?) OR (id_paciente = ? AND id_clinica = ?) ORDER BY id',
            [PATIENT_A, CLINIC_A, PATIENT_B, CLINIC_B],
        ],
    ];
    $rows = [];
    foreach ($queries as $table => [$sql, $params]) {
        $statement = $db->prepare($sql);
        $statement->execute($params);
        $rows[$table] = $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    return $rows;
};

$rowKey = static function (array $row): string {
    return (string)($row['id'] ?? '');
};

$rowEqual = static function (array $left, array $right): bool {
    return json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) === json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

$diffRows = static function (array $baselineRows, array $currentRows) use ($rowKey, $rowEqual): array {
    $baseline = [];
    $current = [];
    foreach ($baselineRows as $row) {
        $key = $rowKey($row);
        if ($key === '' || isset($baseline[$key])) {
            return ['invalid' => true, 'added' => [], 'removed' => [], 'modified' => [], 'duplicate' => true];
        }
        $baseline[$key] = $row;
    }
    foreach ($currentRows as $row) {
        $key = $rowKey($row);
        if ($key === '' || isset($current[$key])) {
            return ['invalid' => true, 'added' => [], 'removed' => [], 'modified' => [], 'duplicate' => true];
        }
        $current[$key] = $row;
    }
    $added = array_values(array_diff(array_keys($current), array_keys($baseline)));
    $removed = array_values(array_diff(array_keys($baseline), array_keys($current)));
    $modified = [];
    foreach (array_intersect(array_keys($baseline), array_keys($current)) as $key) {
        if ($rowEqual($baseline[$key], $current[$key])) {
            continue;
        }
        $columns = array_unique(array_merge(array_keys($baseline[$key]), array_keys($current[$key])));
        $changed = [];
        foreach ($columns as $column) {
            $before = $baseline[$key][$column] ?? null;
            $after = $current[$key][$column] ?? null;
            if (json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !== json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) {
                $changed[] = $column;
            }
        }
        $modified[] = ['id' => $key, 'changed_columns' => $changed];
    }
    return ['invalid' => false, 'added' => $added, 'removed' => $removed, 'modified' => $modified, 'duplicate' => false];
};

$findTarget = static function (array $rows): array {
    $matches = array_values(array_filter($rows, static function (array $row): bool {
        return (int)($row['id_paciente'] ?? 0) === PATIENT_A && (int)($row['id_pregunta'] ?? 0) === TARGET_QUESTION;
    }));
    if (count($matches) !== 1) {
        throw new FocalGuardException('La fila objetivo no es única.');
    }
    return $matches[0];
};

$triggerCount = static function (PDO $db): int {
    $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = ?');
    $statement->execute(['pac_respuestas_paciente_modulo_7']);
    return (int)$statement->fetchColumn();
};

$verifyIdentity = static function (PDO $db) use (&$report): void {
    $statement = $db->prepare('SELECT id, id_clinica FROM pc_paciente WHERE id IN (?, ?) ORDER BY id');
    $statement->execute([PATIENT_A, PATIENT_B]);
    $patients = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (count($patients) !== 2
        || (int)($patients[0]['id'] ?? 0) !== PATIENT_A
        || (int)($patients[0]['id_clinica'] ?? 0) !== CLINIC_A
        || (int)($patients[1]['id'] ?? 0) !== PATIENT_B
        || (int)($patients[1]['id_clinica'] ?? 0) !== CLINIC_B) {
        throw new FocalGuardException('Las identidades sintéticas o sus clínicas no coinciden con la autorización.');
    }
    $clinicCheck = $db->prepare('SELECT COUNT(*) FROM clinicas WHERE id IN (?, ?)');
    $clinicCheck->execute([CLINIC_A, CLINIC_B]);
    if ((int)$clinicCheck->fetchColumn() !== 2) {
        throw new FocalGuardException('Las clínicas sintéticas no están verificadas.');
    }
    $report['tenant_guard'] = 'PASS';
};

$buildChecks = static function (PDO $db, array $original, array $postFailure, array $current) use ($diffRows, $findTarget, &$report): array {
    $currentMatchesPost = true;
    $otherDifferences = [];
    foreach (M07_TABLES as $table) {
        $diff = $diffRows($postFailure['rows'][$table], $current[$table]);
        if ($diff['invalid'] || $diff['duplicate'] || $diff['added'] !== [] || $diff['removed'] !== [] || $diff['modified'] !== []) {
            $currentMatchesPost = false;
        }
        if ($table !== 'pac_respuestas_paciente_modulo_7') {
            $againstOriginal = $diffRows($original['rows'][$table], $postFailure['rows'][$table]);
            if ($againstOriginal['invalid'] || $againstOriginal['duplicate'] || $againstOriginal['added'] !== [] || $againstOriginal['removed'] !== [] || $againstOriginal['modified'] !== []) {
                $otherDifferences[] = $table;
            }
        }
    }
    $originalTarget = $findTarget($original['rows']['pac_respuestas_paciente_modulo_7']);
    $postTarget = $findTarget($postFailure['rows']['pac_respuestas_paciente_modulo_7']);
    $currentTarget = $findTarget($current['pac_respuestas_paciente_modulo_7']);
    $primaryKeyVerified = (string)$originalTarget['id'] === (string)$postTarget['id']
        && (string)$originalTarget['id'] === (string)$currentTarget['id'];

    $responseDiff = $diffRows($original['rows']['pac_respuestas_paciente_modulo_7'], $postFailure['rows']['pac_respuestas_paciente_modulo_7']);
    $targetDifference = array_values(array_filter($responseDiff['modified'], static fn(array $item): bool => (string)$item['id'] === (string)$originalTarget['id']));
    $nonTargetDifferences = array_values(array_filter($responseDiff['modified'], static fn(array $item): bool => (string)$item['id'] !== (string)$originalTarget['id']));
    $changedColumns = $targetDifference[0]['changed_columns'] ?? [];
    sort($changedColumns);
    $allowedColumns = TARGET_COLUMNS;
    sort($allowedColumns);
    $onlyAuthorized = !$responseDiff['invalid']
        && !$responseDiff['duplicate']
        && $responseDiff['added'] === []
        && $responseDiff['removed'] === []
        && count($targetDifference) === 1
        && $nonTargetDifferences === []
        && $changedColumns === $allowedColumns
        && $otherDifferences === [];

    $report['primary_key_verified'] = $primaryKeyVerified ? 'PASS' : 'FAIL';
    $report['current_state_matches_post_failure'] = $currentMatchesPost ? 'PASS' : 'FAIL';
    $report['only_authorized_differences'] = $onlyAuthorized ? 'PASS' : 'FAIL';
    $report['different_tables'] = $otherDifferences;

    return [
        'ok' => $currentMatchesPost && $primaryKeyVerified && $onlyAuthorized,
        'original_target' => $originalTarget,
        'post_target' => $postTarget,
        'current_target' => $currentTarget,
    ];
};

if ($mode === '' || !in_array($mode, ['precheck', 'restore'], true)) {
    $fail('arguments', 'El modo debe ser precheck o restore.');
}
if ($database !== AUTHORIZED_DATABASE) {
    $fail('database_allowlist', 'La base no coincide con la única base sintética autorizada.');
}
if ($originalPath === '' || $postFailurePath === '') {
    $fail('snapshot_arguments', 'Se requieren los dos snapshots autorizados.');
}

$root = dirname(__DIR__, 2);
$original = null;
$postFailure = null;
$db = null;

try {
    $original = $loadSnapshot($originalPath);
    $postFailure = $loadSnapshot($postFailurePath);
    $env = $parseEnv($root . DIRECTORY_SEPARATOR . '.env');
    $db = $connect($env);
    $report['database_guard'] = 'PASS';
    $verifyIdentity($db);
    $report['triggers_guard'] = $triggerCount($db) === 0 ? 'PASS_NONE_FOUND' : 'BLOCKED_TRIGGERS_PRESENT';
    if ($report['triggers_guard'] !== 'PASS_NONE_FOUND') {
        throw new FocalGuardException('Existen triggers en la tabla objetivo; no se puede garantizar el alcance focal.');
    }
    $current = $readRows($db);
    $checks = $buildChecks($db, $original, $postFailure, $current);
    if (!$checks['ok']) {
        throw new FocalGuardException('Las precondiciones de estado focal no se cumplen.');
    }
    $report['precheck_result'] = 'PASS';

    if ($mode === 'precheck') {
        $emit($report, 0);
    }

    $report['transaction_started'] = 'YES';
    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT * FROM pac_respuestas_paciente_modulo_7 WHERE id = :lock_id AND id_paciente = :lock_patient AND id_pregunta = :lock_question FOR UPDATE');
        $lock->execute([
            ':lock_id' => (int)$checks['original_target']['id'],
            ':lock_patient' => PATIENT_A,
            ':lock_question' => TARGET_QUESTION,
        ]);
        $lockedRow = $lock->fetch(PDO::FETCH_ASSOC);
        if (!is_array($lockedRow) || json_encode($lockedRow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !== json_encode($checks['post_target'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) {
            throw new FocalGuardException('La fila bloqueada ya no coincide con la evidencia POST-FAILURE.');
        }
        $report['row_lock_verified'] = 'PASS';

        $transactionRows = $readRows($db);
        $transactionChecks = $buildChecks($db, $original, $postFailure, $transactionRows);
        if (!$transactionChecks['ok']) {
            throw new FocalGuardException('La revalidación transaccional detectó cambios fuera del alcance.');
        }

        $assignments = [];
        $parameters = [
            ':target_id' => (int)$checks['original_target']['id'],
            ':target_patient' => PATIENT_A,
            ':target_question' => TARGET_QUESTION,
        ];
        foreach (TARGET_COLUMNS as $index => $column) {
            $placeholder = ':focal_' . $index;
            $assignments[] = '`' . $column . '` = ' . $placeholder;
            $parameters[$placeholder] = $checks['original_target'][$column] ?? null;
        }
        $update = $db->prepare(
            'UPDATE pac_respuestas_paciente_modulo_7 SET ' . implode(', ', $assignments)
            . ' WHERE id = :target_id AND id_paciente = :target_patient AND id_pregunta = :target_question'
        );
        foreach ($parameters as $placeholder => $value) {
            $update->bindValue($placeholder, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }
        $update->execute();
        $report['rows_updated'] = $update->rowCount() === 1 ? 1 : 0;
        if ($report['rows_updated'] !== 1) {
            throw new FocalGuardException('La actualización focal no afectó exactamente una fila.');
        }

        $readback = $db->prepare('SELECT * FROM pac_respuestas_paciente_modulo_7 WHERE id = :read_id AND id_paciente = :read_patient AND id_pregunta = :read_question');
        $readback->execute([
            ':read_id' => (int)$checks['original_target']['id'],
            ':read_patient' => PATIENT_A,
            ':read_question' => TARGET_QUESTION,
        ]);
        $readbackRow = $readback->fetch(PDO::FETCH_ASSOC);
        if (!is_array($readbackRow) || json_encode($readbackRow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !== json_encode($checks['original_target'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) {
            throw new FocalGuardException('La fila recuperada no coincide exactamente con el snapshot original.');
        }
        $report['post_update_row_match'] = 'PASS';
        $db->commit();
        $report['commit_status'] = 'COMMITTED_ONE_ROW';
        $report['mysql_writes'] = 'ONE_AUTHORIZED_UPDATE';
        $emit($report, 0);
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
            $report['rollback_status'] = 'ROLLBACK_COMPLETED';
        } else {
            $report['rollback_status'] = 'NOT_REQUIRED';
        }
        $report['commit_status'] = 'NOT_COMMITTED';
        $report['errors'][] = $exception instanceof FocalGuardException
            ? $exception->getMessage()
            : 'Error transaccional sanitizado; no se confirmó ningún cambio.';
        $emit($report, 1);
    }
} catch (Throwable $exception) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
        $report['rollback_status'] = 'ROLLBACK_COMPLETED';
    }
    $fail('precheck', $exception instanceof FocalGuardException ? $exception->getMessage() : 'Fallo de precheck sanitizado.');
}
