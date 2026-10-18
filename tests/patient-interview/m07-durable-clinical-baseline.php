<?php

declare(strict_types=1);

/**
 * Durable, read-only M07 clinical baseline for the two synthetic patients.
 *
 * This script never performs SQL writes. The create mode stores complete rows
 * (not only hashes) outside the repository and the XAMPP document root. The
 * compare mode only reads the stored baseline and the current database.
 */

ini_set('display_errors', '0');

$options = [];
foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--')) {
        continue;
    }
    [$key, $value] = array_pad(explode('=', $argument, 2), 2, '1');
    $options[substr($key, 2)] = $value;
}

$mode = (string)($options['mode'] ?? '');
$database = (string)($options['database'] ?? '');
$patientA = (int)($options['patient-a'] ?? 0);
$patientB = (int)($options['patient-b'] ?? 0);
$requestedSnapshot = (string)($options['snapshot'] ?? '');

$fail = static function (string $step, string $message): never {
    echo json_encode([
        'ok' => false,
        'status' => 'BLOCKED_READ_ONLY_BASELINE',
        'first_failed_step' => $step,
        'error_message_sanitized' => $message,
        'mysql_writes' => 'NO',
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(2);
};

if (!in_array($mode, ['create', 'compare', 'forensic-diff'], true)) {
    $fail('arguments', 'El modo debe ser create, compare o forensic-diff.');
}
if ($database !== 'clinica_dolor_demo_20261010_final') {
    $fail('database_allowlist', 'La base no coincide con la única base sintética autorizada.');
}
if ($patientA !== 9301 || $patientB !== 9302) {
    $fail('synthetic_patient_allowlist', 'Los pacientes deben ser exactamente 9301 y 9302.');
}
if (in_array($mode, ['compare', 'forensic-diff'], true) && $requestedSnapshot === '') {
    $fail('snapshot_argument', 'El modo de comparación requiere un snapshot explícito.');
}

$root = dirname(__DIR__, 2);
$envPath = $root . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envPath)) {
    $fail('local_configuration', 'No existe la configuración local requerida.');
}

$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
    if (!array_key_exists($key, $env)) {
        $fail('local_configuration', 'Falta configuración local de conexión.');
    }
}
if (!in_array(strtolower((string)$env['DB_HOST']), ['127.0.0.1', 'localhost', '::1'], true)
    || (string)$env['DB_PORT'] !== '3306') {
    $fail('local_connection_guard', 'La conexión no apunta a MySQL local en el puerto 3306.');
}

$connect = static function () use ($env, $database): PDO {
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $database),
            $env['DB_USERNAME'],
            $env['DB_PASSWORD'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (Throwable) {
        throw new RuntimeException('No fue posible conectar con MySQL local.');
    }
    if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
        throw new RuntimeException('La base activa no coincide con la allowlist.');
    }
    return $pdo;
};

$exists = static function (PDO $db, string $table): bool {
    $statement = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([$table]);
    return (int)$statement->fetchColumn() === 1;
};

$tableDefinitions = [
    'pac_respuestas_paciente_modulo_7' => [
        'where' => 'id_paciente IN (?, ?)',
        'params' => [$patientA, $patientB],
    ],
    'pac_m07_medicamentos_no_catalogados' => [
        'where' => '(id_paciente = ? AND id_clinica = ?) OR (id_paciente = ? AND id_clinica = ?)',
        'params' => [],
    ],
    'pac_m07_operaciones' => [
        'where' => '(id_paciente = ? AND id_clinica = ?) OR (id_paciente = ? AND id_clinica = ?)',
        'params' => [],
    ],
    'pac_m07_revision_clinica' => [
        'where' => '(id_paciente = ? AND id_clinica = ?) OR (id_paciente = ? AND id_clinica = ?)',
        'params' => [],
    ],
];

$hashRows = static function (array $rows): string {
    return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
};
$manifestHash = static function (array $manifest): string {
    return hash('sha256', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
};
$canonicalSnapshotHash = static function (array $snapshot) use ($manifestHash): string {
    unset($snapshot['snapshot_sha256']);
    return $manifestHash($snapshot);
};

$readSnapshotRows = static function (PDO $db, array $definitions): array {
    $rows = [];
    foreach ($definitions as $table => $definition) {
        $statement = $db->prepare("SELECT * FROM {$table} WHERE {$definition['where']} ORDER BY id");
        $statement->execute($definition['params']);
        $rows[$table] = $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    return $rows;
};

$buildDefinitions = static function (int $patientA, int $clinicA, int $patientB, int $clinicB) use ($tableDefinitions): array {
    $definitions = $tableDefinitions;
    foreach (array_keys($definitions) as $table) {
        if ($table !== 'pac_respuestas_paciente_modulo_7') {
            $definitions[$table]['params'] = [$patientA, $clinicA, $patientB, $clinicB];
        }
    }
    return $definitions;
};

$validateSnapshot = static function (array $snapshot) use ($database, $patientA, $patientB, $hashRows, $canonicalSnapshotHash, $fail): void {
    $expectedTables = [
        'pac_respuestas_paciente_modulo_7',
        'pac_m07_medicamentos_no_catalogados',
        'pac_m07_operaciones',
        'pac_m07_revision_clinica',
    ];
    if (($snapshot['schema_version'] ?? '') !== 'M07_CLINICAL_BASELINE_V1'
        || ($snapshot['database'] ?? '') !== $database
        || ($snapshot['patient_ids'] ?? []) !== [$patientA, $patientB]
        || ($snapshot['tables'] ?? []) !== $expectedTables
        || !isset($snapshot['manifest'], $snapshot['rows'], $snapshot['snapshot_sha256'])) {
        $fail('snapshot_validation', 'El snapshot no cumple el contrato durable sintético.');
    }
    if (!hash_equals((string)$snapshot['snapshot_sha256'], $canonicalSnapshotHash($snapshot))) {
        $fail('snapshot_validation', 'La huella del snapshot no coincide con su contenido.');
    }
    foreach ($expectedTables as $table) {
        $rows = $snapshot['rows'][$table] ?? null;
        $manifest = $snapshot['manifest'][$table] ?? null;
        if (!is_array($rows) || !is_array($manifest)
            || (int)($manifest['row_count'] ?? -1) !== count($rows)
            || !hash_equals((string)($manifest['sha256'] ?? ''), $hashRows($rows))) {
            $fail('snapshot_validation', 'La copia durable no coincide con su manifiesto.');
        }
    }
};

try {
    $db = $connect();
    $tables = array_keys($tableDefinitions);
    foreach ($tables as $table) {
        if (!$exists($db, $table)) {
            $fail('required_table', 'Falta una tabla clínica M07 requerida.');
        }
    }

    $patientStatement = $db->prepare(
        'SELECT id, id_clinica, nombres, apellido_paterno, apellido_materno
         FROM pc_paciente WHERE id IN (?, ?) ORDER BY id'
    );
    $patientStatement->execute([$patientA, $patientB]);
    $patients = $patientStatement->fetchAll(PDO::FETCH_ASSOC);
    $syntheticMarker = static function (array $patient): bool {
        $name = strtolower(implode(' ', [
            (string)($patient['nombres'] ?? ''),
            (string)($patient['apellido_paterno'] ?? ''),
            (string)($patient['apellido_materno'] ?? ''),
        ]));
        return str_contains($name, 'sint') || str_contains($name, 'test') || str_contains($name, 'prueba');
    };
    if (count($patients) !== 2 || count(array_filter($patients, $syntheticMarker)) !== 2) {
        $fail('synthetic_patient_guard', 'Los dos expedientes no cumplen el guard sintético.');
    }
    $clinicA = (int)$patients[0]['id_clinica'];
    $clinicB = (int)$patients[1]['id_clinica'];
    if ($clinicA < 1 || $clinicB < 1 || $clinicA === $clinicB) {
        $fail('synthetic_clinic_guard', 'Las clínicas sintéticas no están separadas.');
    }
    $clinicCheck = $db->prepare('SELECT COUNT(*) FROM clinicas WHERE id IN (?, ?)');
    $clinicCheck->execute([$clinicA, $clinicB]);
    if ((int)$clinicCheck->fetchColumn() !== 2) {
        $fail('synthetic_clinic_guard', 'Las clínicas sintéticas no están verificadas.');
    }

    $definitions = $buildDefinitions($patientA, $clinicA, $patientB, $clinicB);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->beginTransaction();
    try {
        $firstRows = $readSnapshotRows($db, $definitions);
        $secondRows = $readSnapshotRows($db, $definitions);
        foreach (array_keys($definitions) as $table) {
            if ($hashRows($firstRows[$table]) !== $hashRows($secondRows[$table])) {
                throw new RuntimeException('La segunda lectura consistente no coincide.');
            }
        }
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }

    $manifest = [];
    foreach (array_keys($definitions) as $table) {
        $manifest[$table] = [
            'row_count' => count($firstRows[$table]),
            'sha256' => $hashRows($firstRows[$table]),
        ];
    }
    $snapshot = [
        'schema_version' => 'M07_CLINICAL_BASELINE_V1',
        'created_at_utc' => gmdate('c'),
        'database' => $database,
        'database_host_scope' => 'LOCAL_MYSQL_ONLY',
        'patient_ids' => [$patientA, $patientB],
        'clinic_ids' => [$clinicA, $clinicB],
        'tables' => array_keys($definitions),
        'manifest' => $manifest,
        'rows' => $firstRows,
        'snapshot_sha256' => null,
    ];
    $snapshot['snapshot_sha256'] = $canonicalSnapshotHash($snapshot);

    if (in_array($mode, ['compare', 'forensic-diff'], true)) {
        if (!is_file($requestedSnapshot)) {
            $fail('snapshot_read', 'No existe el snapshot indicado.');
        }
        $stored = json_decode((string)file_get_contents($requestedSnapshot), true, 512, JSON_THROW_ON_ERROR);
        $validateSnapshot($stored);

        if ($mode === 'forensic-diff') {
            $columnCategory = static function (string $column): string {
                $clinical = [
                    'respuesta', 'dosis', 'resultados', 'consumo', 'descripcion',
                    'm07_respuesta_state', 'm07_dosis_state', 'm07_resultados_state',
                    'm07_consumo_state', 'm07_efectos_descripcion', 'm07_efectos_descripcion_state',
                    'm07_eficacia_codigo', 'm07_eficacia_state',
                    'm07_tolerabilidad_codigo', 'm07_tolerabilidad_state',
                ];
                $traceability = ['m07_version', 'm07_provenance'];
                $technical = [
                    'id', 'id_paciente', 'id_pregunta', 'id_clinica',
                    'created_at', 'updated_at', 'm07_updated_at', 'm07_updated_by',
                ];
                if (in_array($column, $clinical, true)) return 'clinical';
                if (in_array($column, $traceability, true)) return 'traceability';
                if (in_array($column, $technical, true)) return 'technical';
                return 'technical';
            };
            $rowKey = static function (array $row): string {
                $id = trim((string)($row['id'] ?? ''));
                if ($id !== '') return 'id:' . $id;
                return 'scope:' . (string)($row['id_paciente'] ?? '') . ':' . (string)($row['id_pregunta'] ?? '');
            };
            $metadata = static function (array $row) use ($patientA, $patientB): array {
                $patientId = (int)($row['id_paciente'] ?? 0);
                $questionId = (int)($row['id_pregunta'] ?? 0);
                return [
                    'patient_id' => in_array($patientId, [$patientA, $patientB], true) ? $patientId : 0,
                    'question_id' => $questionId > 0 ? $questionId : 0,
                ];
            };
            $diffRows = static function (array $baselineRows, array $currentRows) use ($rowKey, $metadata, $columnCategory): array {
                $baseline = [];
                $current = [];
                $baselineDuplicates = 0;
                $currentDuplicates = 0;
                foreach ($baselineRows as $row) {
                    $key = $rowKey($row);
                    if (isset($baseline[$key])) $baselineDuplicates++;
                    $baseline[$key] = $row;
                }
                foreach ($currentRows as $row) {
                    $key = $rowKey($row);
                    if (isset($current[$key])) $currentDuplicates++;
                    $current[$key] = $row;
                }
                $added = array_diff(array_keys($current), array_keys($baseline));
                $removed = array_diff(array_keys($baseline), array_keys($current));
                $modified = [];
                foreach (array_intersect(array_keys($baseline), array_keys($current)) as $key) {
                    $columns = array_unique(array_merge(array_keys($baseline[$key]), array_keys($current[$key])));
                    $changed = [];
                    foreach ($columns as $column) {
                        $before = $baseline[$key][$column] ?? null;
                        $after = $current[$key][$column] ?? null;
                        if (json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !== json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) {
                            $changed[] = $column;
                        }
                    }
                    if ($changed !== []) {
                        $categories = array_values(array_unique(array_map($columnCategory, $changed)));
                        $modified[] = [
                            'metadata' => $metadata($current[$key]),
                            'changed_columns' => $changed,
                            'categories' => $categories,
                        ];
                    }
                }
                $addedMetadata = array_map(static fn(string $key): array => $metadata($current[$key]), $added);
                $removedMetadata = array_map(static fn(string $key): array => $metadata($baseline[$key]), $removed);
                $all = array_merge($addedMetadata, $removedMetadata, array_map(static fn(array $item): array => $item['metadata'], $modified));
                $patientIds = array_values(array_unique(array_filter(array_map(static fn(array $item): int => (int)$item['patient_id'], $all))));
                sort($patientIds);
                $questionIds = array_values(array_unique(array_filter(array_map(static fn(array $item): int => (int)$item['question_id'], $all))));
                sort($questionIds);
                $changedColumns = [];
                $categories = [];
                foreach ($modified as $item) {
                    $changedColumns = array_merge($changedColumns, $item['changed_columns']);
                    $categories = array_merge($categories, $item['categories']);
                }
                foreach (array_merge($addedMetadata, $removedMetadata) as $_) {
                    $categories[] = 'clinical';
                }
                return [
                    'rows_added' => count($added),
                    'rows_removed' => count($removed),
                    'rows_modified' => count($modified),
                    'affected_patient_ids' => $patientIds,
                    'affected_question_ids' => $questionIds,
                    'changed_columns' => array_values(array_unique($changedColumns)),
                    'difference_categories' => array_values(array_unique($categories)),
                    'duplicate_keys_detected' => $baselineDuplicates + $currentDuplicates,
                ];
            };

            $tableReports = [];
            $anyDifference = false;
            foreach (array_keys($definitions) as $table) {
                $diff = $diffRows($stored['rows'][$table], $firstRows[$table]);
                $hasDifference = ($diff['rows_added'] + $diff['rows_removed'] + $diff['rows_modified']) > 0;
                $anyDifference = $anyDifference || $hasDifference;
                $tableReports[] = [
                    'table' => $table,
                    'baseline_rows' => count($stored['rows'][$table]),
                    'current_rows' => count($firstRows[$table]),
                    'rows_added' => $diff['rows_added'],
                    'rows_removed' => $diff['rows_removed'],
                    'rows_modified' => $diff['rows_modified'],
                    'affected_patient_ids' => $diff['affected_patient_ids'],
                    'affected_question_ids' => $diff['affected_question_ids'],
                    'changed_columns' => $diff['changed_columns'],
                    'difference_categories' => $diff['difference_categories'],
                    'difference_is_clinical' => in_array('clinical', $diff['difference_categories'], true),
                    'difference_is_technical' => in_array('technical', $diff['difference_categories'], true),
                    'difference_is_traceability' => in_array('traceability', $diff['difference_categories'], true),
                    'duplicate_keys_detected' => $diff['duplicate_keys_detected'],
                ];
            }
            echo json_encode([
                'ok' => !$anyDifference,
                'status' => $anyDifference ? 'FORENSIC_DIFFERENCE_FOUND' : 'FORENSIC_BASELINE_MATCH',
                'database_effective' => $database,
                'synthetic_patients' => '9301_9302_ONLY',
                'tables_compared' => count($definitions),
                'table_reports' => $tableReports,
                'historical_restoration_verifiable' => 'NOT_DETERMINABLE_FROM_DURABLE_BASELINE',
                'snapshot_recoverable' => 'PASS_ROWS_AND_MANIFEST',
                'mysql_writes' => 'NO',
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
            exit($anyDifference ? 1 : 0);
        }
        $differences = [];
        foreach (array_keys($definitions) as $table) {
            if ($manifest[$table]['sha256'] !== $stored['manifest'][$table]['sha256']) {
                $differences[] = $table;
            }
        }
        echo json_encode([
            'ok' => $differences === [],
            'status' => $differences === [] ? 'PASS_BASELINE_MATCH' : 'FAIL_BASELINE_DIFFERENCE',
            'database_effective' => $database,
            'tables_compared' => count($definitions),
            'different_tables' => $differences,
            'snapshot_recoverable' => 'PASS_ROWS_AND_MANIFEST',
            'mysql_writes' => 'NO',
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit($differences === [] ? 0 : 1);
    }

    $localAppData = getenv('LOCALAPPDATA');
    if (!is_string($localAppData) || trim($localAppData) === '') {
        $fail('private_storage', 'LOCALAPPDATA no está disponible; no se usó una ruta pública o del proyecto.');
    }
    $storage = rtrim($localAppData, '\\/') . DIRECTORY_SEPARATOR . 'ClinicaDolor' . DIRECTORY_SEPARATOR . 'M07-baselines';
    if (!is_dir($storage) && !mkdir($storage, 0700, true) && !is_dir($storage)) {
        $fail('private_storage', 'No fue posible crear el almacenamiento local privado.');
    }
    $projectRoot = strtolower(str_replace('/', '\\', realpath($root) ?: $root));
    $storageRealParent = strtolower(str_replace('/', '\\', realpath(dirname($storage)) ?: dirname($storage)));
    if (str_starts_with($storageRealParent, $projectRoot . '\\')) {
        $fail('private_storage', 'La ruta de almacenamiento no puede estar dentro del proyecto.');
    }
    $fileName = 'm07-clinical-baseline-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(4)) . '.json';
    $target = $storage . DIRECTORY_SEPARATOR . $fileName;
    $temporary = $target . '.tmp';
    if (is_file($target)) {
        $fail('private_storage', 'El nombre de snapshot ya existe; no se sobrescribe.');
    }
    $encoded = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($temporary, $encoded, LOCK_EX) === false || !rename($temporary, $target)) {
        if (is_file($temporary)) {
            unlink($temporary);
        }
        $fail('private_storage', 'No fue posible escribir atómicamente el snapshot durable.');
    }
    $stored = json_decode((string)file_get_contents($target), true, 512, JSON_THROW_ON_ERROR);
    $validateSnapshot($stored);
    if ($stored['snapshot_sha256'] !== $snapshot['snapshot_sha256']) {
        $fail('snapshot_readback', 'La copia durable no coincide con la lectura de origen.');
    }

    echo json_encode([
        'ok' => true,
        'status' => 'PASS_DURABLE_BASELINE_CREATED',
        'database_effective' => $database,
        'synthetic_patients' => '9301_9302_ONLY',
        'tables_covered' => count($definitions),
        'consistent_snapshot' => 'PASS_DOUBLE_READ_SAME_TRANSACTION',
        'snapshot_recoverable' => 'PASS_FULL_ROWS_AND_MANIFEST',
        'snapshot_file_readback' => 'PASS',
        'snapshot_path' => $target,
        'mysql_writes' => 'NO',
        'ddl_executed' => 'NO',
        'backfill_executed' => 'NO',
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
} catch (JsonException) {
    $fail('serialization', 'No fue posible serializar o leer el snapshot sin pérdida.');
} catch (Throwable $exception) {
    $fail('read_only_snapshot', 'La preparación read-only no pudo completarse de forma segura.');
}
