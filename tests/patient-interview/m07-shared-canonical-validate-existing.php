<?php

declare(strict_types=1);

/**
 * Read-only post-migration validator for the synthetic M07 schema.
 *
 * This script never performs DDL or DML. It verifies the effective database,
 * the complete additive schema, deterministic backfill invariants, historical
 * referential integrity, and the optional migration snapshot metadata.
 * Clinical values are used only in aggregate predicates and are never emitted.
 */

$options = [];
foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--')) {
        continue;
    }
    [$key, $value] = array_pad(explode('=', $argument, 2), 2, '1');
    $options[substr($key, 2)] = $value;
}

$database = (string)($options['database'] ?? '');
$patientA = (int)($options['patient-a'] ?? 0);
$patientB = (int)($options['patient-b'] ?? 0);
$snapshotPath = (string)($options['snapshot'] ?? '');

$result = [
    'phase' => 'M07_POST_MIGRATION_READ_ONLY_VALIDATION',
    'database' => $database,
    'read_only' => true,
    'status' => 'NOT_RUN',
    'database_effective' => 'NOT_VERIFIED',
    'clinical_state_integrity' => 'NOT_VERIFIED',
    'referential_integrity' => 'NOT_VERIFIED',
    'canonical_state_value_consistency' => 'NOT_VERIFIED',
    'durable_previous_snapshot_found' => 'NOT_PROVIDED',
    'previous_snapshot_source' => 'NOT_PROVIDED',
    'previous_snapshot_trustworthy' => 'NOT_VERIFIED',
    'current_vs_previous_comparison' => 'NOT_PERFORMED',
    'historical_restoration_verified' => 'NOT_VERIFIABLE',
    'schema_integrity' => 'NOT_VERIFIED',
    'backfill_current_state' => 'NOT_VERIFIED',
    'historical_data_integrity' => 'NOT_VERIFIED',
    'migration_snapshot_available' => 'NOT_PROVIDED',
    'checks' => [],
];

$finish = static function (array $payload, int $exitCode): never {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($exitCode);
};

$fail = static function (string $step, string $message) use (&$result, $finish): never {
    $result['status'] = 'BLOCKED_READ_ONLY_VALIDATION';
    $result['first_failed_step'] = $step;
    $result['error_message_sanitized'] = $message;
    $finish($result, 2);
};

if ($database !== 'clinica_dolor_demo_20261010_final') {
    $fail('allowlist', 'La base no coincide con la única base sintética autorizada.');
}
if ($patientA !== 9301 || $patientB !== 9302) {
    $fail('synthetic_patient_allowlist', 'Los pacientes deben ser exactamente 9301 y 9302.');
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
if (!in_array(strtolower((string)$env['DB_HOST']), ['127.0.0.1', 'localhost', '::1'], true) || (string)$env['DB_PORT'] !== '3306') {
    $fail('local_connection_guard', 'La conexión no apunta a MySQL local en el puerto 3306.');
}

try {
    $db = new PDO(
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
    $fail('mysql_connection', 'No fue posible conectar con MySQL local; no se verificó el esquema.');
}

$activeDatabase = (string)$db->query('SELECT DATABASE()')->fetchColumn();
$result['database_effective'] = $activeDatabase === $database ? 'PASS' : 'FAIL';
if ($activeDatabase !== $database) {
    $fail('database_identity', 'La base activa no coincide exactamente con la solicitada.');
}

$exists = static function (PDO $connection, string $table): bool {
    $statement = $connection->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([$table]);
    return (int)$statement->fetchColumn() === 1;
};

$engine = static function (PDO $connection, string $table): string {
    $statement = $connection->prepare(
        'SELECT COALESCE(MAX(engine), \'\') FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([$table]);
    return (string)$statement->fetchColumn();
};

$columnNames = static function (PDO $connection, string $table): array {
    $statement = $connection->prepare(
        'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([$table]);
    return array_map(static fn(array $row): string => (string)$row['column_name'], $statement->fetchAll());
};

$baseTables = ['pc_paciente', 'clinicas', 'pac_temas_modulo_7', 'pac_preguntas_modulo_7', 'pac_respuestas_paciente_modulo_7'];
$newTables = ['pac_m07_medicamentos_no_catalogados', 'pac_m07_operaciones', 'pac_m07_revision_clinica'];
$newColumns = [
    'm07_respuesta_state', 'm07_dosis_state', 'm07_resultados_state',
    'm07_consumo_state', 'm07_descripcion_state', 'm07_eficacia_codigo',
    'm07_eficacia_state', 'm07_tolerabilidad_codigo', 'm07_tolerabilidad_state',
    'm07_efectos_descripcion', 'm07_efectos_descripcion_state', 'm07_version',
    'm07_updated_at', 'm07_updated_by', 'm07_provenance',
];

$missingBaseTables = array_values(array_filter($baseTables, static fn(string $table): bool => !$exists($db, $table)));
$missingNewTables = array_values(array_filter($newTables, static fn(string $table): bool => !$exists($db, $table)));
$responseColumns = $columnNames($db, 'pac_respuestas_paciente_modulo_7');
$missingNewColumns = array_values(array_diff($newColumns, $responseColumns));
$newTableEngines = array_values(array_filter(
    array_map(static fn(string $table): string => $engine($db, $table), $newTables),
    static fn(string $value): bool => $value !== 'InnoDB'
));
$responseEngine = $engine($db, 'pac_respuestas_paciente_modulo_7');

$uniqueIndex = static function (PDO $connection, string $table, string $index): bool {
    $statement = $connection->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? AND non_unique = 0'
    );
    $statement->execute([$table, $index]);
    return (int)$statement->fetchColumn() > 0;
};

$foreignKeyCount = static function (PDO $connection, string $table): int {
    $statement = $connection->prepare(
        'SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([$table]);
    return (int)$statement->fetchColumn();
};

$indexesOk = $uniqueIndex($db, 'pac_m07_medicamentos_no_catalogados', 'm07_other_entry_key')
    && $uniqueIndex($db, 'pac_m07_operaciones', 'm07_operation_key')
    && $uniqueIndex($db, 'pac_m07_revision_clinica', 'm07_review_scope');
$foreignKeysOk = $foreignKeyCount($db, 'pac_m07_medicamentos_no_catalogados') >= 3
    && $foreignKeyCount($db, 'pac_m07_operaciones') >= 2
    && $foreignKeyCount($db, 'pac_m07_revision_clinica') >= 2;

$result['checks']['base_tables_present'] = $missingBaseTables === [];
$result['checks']['new_tables_present'] = $missingNewTables === [];
$result['checks']['response_columns_present'] = $missingNewColumns === [];
$result['checks']['response_engine_innodb'] = $responseEngine === 'InnoDB';
$result['checks']['new_tables_innodb'] = $newTableEngines === [];
$result['checks']['required_unique_indexes'] = $indexesOk;
$result['checks']['required_foreign_keys'] = $foreignKeysOk;
$schemaOk = $missingBaseTables === [] && $missingNewTables === [] && $missingNewColumns === []
    && $responseEngine === 'InnoDB' && $newTableEngines === [] && $indexesOk && $foreignKeysOk;
$result['schema_integrity'] = $schemaOk ? 'PASS' : 'FAIL';
if (!$schemaOk) {
    $fail('schema_integrity', 'El esquema canónico no está completo o no cumple sus índices y relaciones.');
}

$patientStatement = $db->prepare(
    'SELECT id, id_clinica, nombres, apellido_paterno, apellido_materno FROM pc_paciente WHERE id IN (?, ?) ORDER BY id'
);
$patientStatement->execute([$patientA, $patientB]);
$patients = $patientStatement->fetchAll();
$syntheticMarker = static function (array $patient): bool {
    $name = strtolower(implode(' ', [
        (string)($patient['nombres'] ?? ''),
        (string)($patient['apellido_paterno'] ?? ''),
        (string)($patient['apellido_materno'] ?? ''),
    ]));
    return str_contains($name, 'sint') || str_contains($name, 'test') || str_contains($name, 'prueba');
};
$clinicIds = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['id_clinica'], $patients)));
$result['checks']['synthetic_patients_verified'] = count($patients) === 2 && count(array_filter($patients, $syntheticMarker)) === 2;
$result['checks']['synthetic_clinics_distinct'] = count($clinicIds) === 2;
if (!$result['checks']['synthetic_patients_verified'] || !$result['checks']['synthetic_clinics_distinct']) {
    $fail('synthetic_patient_guard', 'Los expedientes no cumplen el fixture sintético independiente.');
}

$duplicateGroups = (int)$db->query(
    'SELECT COUNT(*) FROM (SELECT id_paciente, id_pregunta, COUNT(*) AS row_count FROM pac_respuestas_paciente_modulo_7 GROUP BY id_paciente, id_pregunta HAVING row_count > 1) duplicates'
)->fetchColumn();
$orphanPatients = (int)$db->query(
    'SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7 r LEFT JOIN pc_paciente p ON p.id = r.id_paciente WHERE p.id IS NULL'
)->fetchColumn();
$orphanQuestions = (int)$db->query(
    'SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7 r LEFT JOIN pac_preguntas_modulo_7 q ON q.id = r.id_pregunta WHERE q.id IS NULL'
)->fetchColumn();

$missingStateRows = (int)$db->query(
    "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7
     WHERE m07_respuesta_state IS NULL OR m07_dosis_state IS NULL OR m07_resultados_state IS NULL
        OR m07_consumo_state IS NULL OR m07_descripcion_state IS NULL OR m07_eficacia_state IS NULL
        OR m07_tolerabilidad_state IS NULL OR m07_efectos_descripcion_state IS NULL
        OR m07_version IS NULL OR m07_provenance IS NULL"
)->fetchColumn();
$invalidResponseValues = (int)$db->query(
    "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7 WHERE COALESCE(TRIM(respuesta), '') NOT IN ('', 'Si', 'No')"
)->fetchColumn();
$invalidConsumptionValues = (int)$db->query(
    "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7 WHERE COALESCE(TRIM(consumo), '') NOT IN ('', 'Si', 'No')"
)->fetchColumn();
$invalidResultValues = (int)$db->query(
    "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7 WHERE COALESCE(TRIM(resultados), '') NOT IN ('', 'Funciono', 'No funciono', 'Tuve efectos adversos')"
)->fetchColumn();
$invalidProvenance = (int)$db->query(
    "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7 WHERE COALESCE(m07_provenance, '') NOT IN ('LEGACY', 'INTERVIEW')"
)->fetchColumn();
$historicalConflicts = (int)$db->query(
    "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7
     WHERE TRIM(COALESCE(respuesta, '')) = 'No'
       AND (TRIM(COALESCE(dosis, '')) <> '' OR TRIM(COALESCE(resultados, '')) <> ''
            OR TRIM(COALESCE(consumo, '')) <> '' OR TRIM(COALESCE(descripcion, '')) <> '')"
)->fetchColumn();

$result['checks']['duplicate_patient_question_groups_zero'] = $duplicateGroups === 0;
$result['checks']['orphan_patient_rows_zero'] = $orphanPatients === 0;
$result['checks']['orphan_question_rows_zero'] = $orphanQuestions === 0;
$result['checks']['backfill_state_columns_complete'] = $missingStateRows === 0;
$result['checks']['response_domain_values_valid'] = $invalidResponseValues === 0 && $invalidConsumptionValues === 0 && $invalidResultValues === 0;
$result['checks']['provenance_domain_valid'] = $invalidProvenance === 0;
$result['checks']['historical_conflicts_zero'] = $historicalConflicts === 0;
$backfillOk = $missingStateRows === 0 && $invalidResponseValues === 0 && $invalidConsumptionValues === 0
    && $invalidResultValues === 0 && $invalidProvenance === 0;
$historyOk = $duplicateGroups === 0 && $orphanPatients === 0 && $orphanQuestions === 0 && $historicalConflicts === 0;
$result['backfill_current_state'] = $backfillOk ? 'PASS' : 'REVIEW_REQUIRED';
$result['historical_data_integrity'] = $historyOk ? 'PASS' : 'REVIEW_REQUIRED';

/*
 * Scoped clinical-state checks. These are SELECT-only and deliberately report
 * booleans rather than clinical values, identifiers, or hashes. The historical
 * checks above cover the whole table; these checks prove the two authorized
 * synthetic patients and the three additive tables are safe to use as a
 * read-only basis for a later, separately authorized write run.
 */
$scopedParams = [$patientA, $patientB];
$stateValues = [
    'NOT_DOCUMENTED_HISTORICAL', 'CONFIRMED', 'NOT_APPLICABLE',
    'PENDING_REVIEW', 'UNANSWERED', 'DOES_NOT_REMEMBER',
];
$statePlaceholders = implode(',', array_fill(0, count($stateValues), '?'));
$invalidCatalogStateRows = 0;
foreach ([
    'm07_respuesta_state', 'm07_dosis_state', 'm07_resultados_state',
    'm07_consumo_state', 'm07_descripcion_state', 'm07_eficacia_state',
    'm07_tolerabilidad_state', 'm07_efectos_descripcion_state',
] as $stateColumn) {
    $statement = $db->prepare(
        "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7
         WHERE id_paciente IN (?, ?)
           AND COALESCE({$stateColumn}, '') NOT IN ({$statePlaceholders})"
    );
    $statement->execute(array_merge($scopedParams, $stateValues));
    $invalidCatalogStateRows += (int)$statement->fetchColumn();
}

$scopedDuplicateGroups = (int)$db->query(
    sprintf(
        'SELECT COUNT(*) FROM (SELECT id_paciente, id_pregunta, COUNT(*) AS row_count
         FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente IN (%d, %d)
         GROUP BY id_paciente, id_pregunta HAVING row_count > 1) duplicates',
        $patientA,
        $patientB
    )
)->fetchColumn();
$scopedOrphanResponseRows = (int)$db->query(
    sprintf(
        'SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7 r
         LEFT JOIN pc_paciente p ON p.id = r.id_paciente
         LEFT JOIN pac_preguntas_modulo_7 q ON q.id = r.id_pregunta
         WHERE r.id_paciente IN (%d, %d) AND (p.id IS NULL OR q.id IS NULL)',
        $patientA,
        $patientB
    )
)->fetchColumn();
$catalogStateValueConflicts = (int)$db->query(
    sprintf(
        "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7
         WHERE id_paciente IN (%d, %d) AND (
             (TRIM(COALESCE(respuesta, '')) = '' AND m07_respuesta_state = 'CONFIRMED')
             OR (TRIM(COALESCE(respuesta, '')) <> '' AND m07_respuesta_state = 'UNANSWERED')
             OR (m07_eficacia_codigo IS NOT NULL AND
                 (m07_eficacia_state <> 'CONFIRMED' OR m07_eficacia_codigo NOT IN ('FUNCIONO', 'NO_FUNCIONO')))
             OR (m07_tolerabilidad_codigo IS NOT NULL AND
                 (m07_tolerabilidad_state <> 'CONFIRMED' OR m07_tolerabilidad_codigo <> 'PRESENTO_EFECTOS'))
             OR (m07_efectos_descripcion_state = 'CONFIRMED' AND TRIM(COALESCE(m07_efectos_descripcion, '')) = '')
         )",
        $patientA,
        $patientB
    )
)->fetchColumn();

$otherOrphanRows = (int)$db->query(
    sprintf(
        'SELECT COUNT(*) FROM pac_m07_medicamentos_no_catalogados o
         LEFT JOIN pc_paciente p ON p.id = o.id_paciente
         LEFT JOIN clinicas c ON c.id = o.id_clinica
         LEFT JOIN pac_temas_modulo_7 t ON t.id = o.id_tema
         WHERE o.id_paciente IN (%d, %d) AND (p.id IS NULL OR c.id IS NULL OR t.id IS NULL)',
        $patientA,
        $patientB
    )
)->fetchColumn();
$otherDuplicateGroups = (int)$db->query(
    sprintf(
        'SELECT COUNT(*) FROM (SELECT id_paciente, id_clinica, ambito, entry_key, COUNT(*) AS row_count
         FROM pac_m07_medicamentos_no_catalogados WHERE id_paciente IN (%d, %d)
         GROUP BY id_paciente, id_clinica, ambito, entry_key HAVING row_count > 1) duplicates',
        $patientA,
        $patientB
    )
)->fetchColumn();
$invalidOtherRows = (int)$db->query(
    sprintf(
        "SELECT COUNT(*) FROM pac_m07_medicamentos_no_catalogados
         WHERE id_paciente IN (%d, %d) AND (
             estado_registro NOT IN ('ACTIVO', 'INACTIVO')
             OR COALESCE(respuesta, '') NOT IN ('', 'Si', 'No')
             OR COALESCE(respuesta_state, '') NOT IN ('NOT_DOCUMENTED_HISTORICAL', 'CONFIRMED', 'NOT_APPLICABLE', 'PENDING_REVIEW', 'UNANSWERED', 'DOES_NOT_REMEMBER')
             OR COALESCE(provenance, '') NOT IN ('LEGACY', 'INTERVIEW')
             OR version < 1
         )",
        $patientA,
        $patientB
    )
)->fetchColumn();
$operationOrphanRows = (int)$db->query(
    sprintf(
        'SELECT COUNT(*) FROM pac_m07_operaciones o
         LEFT JOIN pc_paciente p ON p.id = o.id_paciente
         LEFT JOIN clinicas c ON c.id = o.id_clinica
         WHERE o.id_paciente IN (%d, %d) AND (p.id IS NULL OR c.id IS NULL)',
        $patientA,
        $patientB
    )
)->fetchColumn();
$operationDuplicateGroups = (int)$db->query(
    sprintf(
        'SELECT COUNT(*) FROM (SELECT id_paciente, idempotency_key, COUNT(*) AS row_count
         FROM pac_m07_operaciones WHERE id_paciente IN (%d, %d)
         GROUP BY id_paciente, idempotency_key HAVING row_count > 1) duplicates',
        $patientA,
        $patientB
    )
)->fetchColumn();
$invalidOperationRows = (int)$db->query(
    sprintf(
        "SELECT COUNT(*) FROM pac_m07_operaciones
         WHERE id_paciente IN (%d, %d) AND (
             TRIM(COALESCE(ambito, '')) = '' OR TRIM(COALESCE(target_type, '')) = ''
             OR TRIM(COALESCE(action, '')) = '' OR TRIM(COALESCE(result_code, '')) = ''
             OR COALESCE(provenance, '') NOT IN ('LEGACY', 'INTERVIEW')
         )",
        $patientA,
        $patientB
    )
)->fetchColumn();
$reviewOrphanRows = (int)$db->query(
    sprintf(
        'SELECT COUNT(*) FROM pac_m07_revision_clinica r
         LEFT JOIN pc_paciente p ON p.id = r.id_paciente
         LEFT JOIN clinicas c ON c.id = r.id_clinica
         WHERE r.id_paciente IN (%d, %d) AND (p.id IS NULL OR c.id IS NULL)',
        $patientA,
        $patientB
    )
)->fetchColumn();
$reviewDuplicateGroups = (int)$db->query(
    sprintf(
        'SELECT COUNT(*) FROM (SELECT id_paciente, id_clinica, scope, COUNT(*) AS row_count
         FROM pac_m07_revision_clinica WHERE id_paciente IN (%d, %d)
         GROUP BY id_paciente, id_clinica, scope HAVING row_count > 1) duplicates',
        $patientA,
        $patientB
    )
)->fetchColumn();
$invalidReviewRows = (int)$db->query(
    sprintf(
        "SELECT COUNT(*) FROM pac_m07_revision_clinica
         WHERE id_paciente IN (%d, %d) AND (
             scope <> 'M07' OR status NOT IN ('PENDING', 'ACCEPTED', 'REQUIRES_CORRECTION') OR version < 1
         )",
        $patientA,
        $patientB
    )
)->fetchColumn();

$result['checks']['scoped_catalog_states_valid'] = $invalidCatalogStateRows === 0;
$result['checks']['scoped_catalog_duplicates_zero'] = $scopedDuplicateGroups === 0;
$result['checks']['scoped_catalog_orphans_zero'] = $scopedOrphanResponseRows === 0;
$result['checks']['scoped_catalog_state_value_consistency'] = $catalogStateValueConflicts === 0;
$result['checks']['scoped_other_orphans_zero'] = $otherOrphanRows === 0;
$result['checks']['scoped_other_duplicates_zero'] = $otherDuplicateGroups === 0;
$result['checks']['scoped_other_domains_valid'] = $invalidOtherRows === 0;
$result['checks']['scoped_operation_orphans_zero'] = $operationOrphanRows === 0;
$result['checks']['scoped_operation_duplicates_zero'] = $operationDuplicateGroups === 0;
$result['checks']['scoped_operation_domains_valid'] = $invalidOperationRows === 0;
$result['checks']['scoped_review_orphans_zero'] = $reviewOrphanRows === 0;
$result['checks']['scoped_review_duplicates_zero'] = $reviewDuplicateGroups === 0;
$result['checks']['scoped_review_domains_valid'] = $invalidReviewRows === 0;

$referentialOk = $scopedOrphanResponseRows === 0 && $otherOrphanRows === 0
    && $operationOrphanRows === 0 && $reviewOrphanRows === 0;
$duplicateOk = $scopedDuplicateGroups === 0 && $otherDuplicateGroups === 0
    && $operationDuplicateGroups === 0 && $reviewDuplicateGroups === 0;
$stateValueOk = $invalidCatalogStateRows === 0 && $catalogStateValueConflicts === 0
    && $invalidOtherRows === 0 && $invalidOperationRows === 0 && $invalidReviewRows === 0;
$result['referential_integrity'] = $referentialOk ? 'PASS' : 'FAIL';
$result['canonical_state_value_consistency'] = $stateValueOk ? 'PASS' : 'FAIL';
$result['clinical_state_integrity'] = $referentialOk && $duplicateOk && $stateValueOk ? 'PASS' : 'FAIL';

/* The runner's temporary P04 snapshot is credential-only; it is intentionally
 * not accepted as a previous clinical baseline. A future baseline must be a
 * separately named, durable clinical artifact with an explicit owner. */
$result['durable_previous_snapshot_found'] = 'NO';
$result['previous_snapshot_source'] = 'NO_DURABLE_CLINICAL_ARTIFACT_PROVIDED';
$result['previous_snapshot_trustworthy'] = 'NO';
$result['current_vs_previous_comparison'] = 'NOT_PERFORMED';
$result['historical_restoration_verified'] = 'NOT_VERIFIABLE';

if ($snapshotPath !== '') {
    if (!is_file($snapshotPath)) {
        $result['migration_snapshot_available'] = 'NOT_FOUND';
    } else {
        try {
            $snapshot = json_decode((string)file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR);
            $validSnapshot = ($snapshot['database'] ?? '') === $database
                && isset($snapshot['original_columns'], $snapshot['original_rows']);
            $result['migration_snapshot_available'] = $validSnapshot ? 'YES_METADATA_VALID' : 'INVALID';
        } catch (Throwable) {
            $result['migration_snapshot_available'] = 'INVALID';
        }
    }
}

$allReadOnlyChecksPass = $schemaOk && $backfillOk && $historyOk
    && $result['checks']['synthetic_patients_verified'] && $result['checks']['synthetic_clinics_distinct']
    && $result['clinical_state_integrity'] === 'PASS';
if (!$allReadOnlyChecksPass) {
    $result['status'] = 'BLOCKED_READ_ONLY_VALIDATION';
    $result['first_failed_step'] = $result['clinical_state_integrity'] !== 'PASS'
        ? 'scoped_clinical_state_integrity'
        : 'schema_or_historical_integrity';
    $result['error_message_sanitized'] = 'El esquema o las invariantes clínicas no permiten reanudar con seguridad.';
    $finish($result, 2);
}

$result['status'] = 'PASS_READ_ONLY_EXISTING_SCHEMA';
$finish($result, 0);
