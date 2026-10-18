<?php

declare(strict_types=1);

/**
 * M07 shared-canonical additive migration.
 *
 * This is an explicitly invoked synthetic-fixture tool, not an application
 * bootstrap migration.  It refuses every database except the P04 allowlisted
 * fixture and never changes .env or bd_clinica.
 *
 * Modes:
 *   --mode=preflight  read-only schema and data checks
 *   --mode=apply      additive DDL + deterministic legacy backfill
 *   --mode=restore    restore a snapshot created by --mode=apply
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
$patientA = (int)($options['patient-a'] ?? 0);
$patientB = (int)($options['patient-b'] ?? 0);
$allowWrite = array_key_exists('allow-synthetic-write', $options);
$snapshotPath = (string)($options['snapshot'] ?? '');
$result = [
    'phase' => 'M07_SHARED_CANONICAL_IMPLEMENTATION_01',
    'mode' => $mode,
    'database' => $database,
    'read_only' => $mode === 'preflight',
    'schema' => 'NOT_RUN',
    'backfill' => 'NOT_RUN',
    'snapshot' => 'NOT_RUN',
    'errors' => [],
];

$fail = static function (string $message) use (&$result): never {
    $result['status'] = 'BLOCKED';
    $result['errors'][] = $message;
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
};

if (!in_array($mode, ['preflight', 'apply', 'restore'], true)) $fail('Modo de migración no válido.');
if ($database !== 'clinica_dolor_demo_20261010_final') $fail('La migración sólo permite la base sintética allowlisted de P04.');
if ($mode !== 'preflight' && !$allowWrite) $fail('Las escrituras requieren --allow-synthetic-write explícito.');
if ($mode !== 'preflight' && ($patientA < 1 || $patientB < 1 || $patientA === $patientB)) $fail('Apply/restore requieren dos pacientes sintéticos explícitos.');
if ($mode === 'restore' && $snapshotPath === '') $fail('Restore requiere --snapshot=<ruta temporal>.');

$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) if (!array_key_exists($key, $env)) $fail('Falta configuración de conexión local.');
if (!in_array(strtolower($env['DB_HOST']), ['127.0.0.1', 'localhost', '::1'], true) || (string)$env['DB_PORT'] !== '3306') $fail('La migración sólo permite MySQL local en 3306.');

try {
    $db = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $database), $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
} catch (Throwable) {
    $fail('No fue posible conectar con la base sintética local. No se ejecutó DDL ni backfill.');
}

if ((string)$db->query('SELECT DATABASE()')->fetchColumn() !== $database) $fail('La base activa no coincide con la base solicitada.');
$result['mysql_version'] = (string)$db->query('SELECT VERSION()')->fetchColumn();
$result['database_host'] = (string)$env['DB_HOST'];

$exists = static function (PDO $db, string $table): bool {
    $s = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $s->execute([$table]);
    return (int)$s->fetchColumn() === 1;
};
$columns = static function (PDO $db, string $table): array {
    $s = $db->prepare('SELECT column_name,data_type,is_nullable,column_default FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? ORDER BY ordinal_position');
    $s->execute([$table]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
};
$requiredBase = ['pc_paciente', 'clinicas', 'pac_temas_modulo_7', 'pac_preguntas_modulo_7', 'pac_respuestas_paciente_modulo_7'];
foreach ($requiredBase as $table) if (!$exists($db, $table)) $fail('Falta una tabla base requerida: ' . $table);

$baseColumns = array_map(static fn(array $row): string => (string)$row['column_name'], $columns($db, 'pac_respuestas_paciente_modulo_7'));
$newColumns = ['m07_respuesta_state','m07_dosis_state','m07_resultados_state','m07_consumo_state','m07_descripcion_state','m07_eficacia_codigo','m07_eficacia_state','m07_tolerabilidad_codigo','m07_tolerabilidad_state','m07_efectos_descripcion','m07_efectos_descripcion_state','m07_version','m07_updated_at','m07_updated_by','m07_provenance'];
$presentColumns = array_values(array_intersect($newColumns, $baseColumns));
$newTables = ['pac_m07_medicamentos_no_catalogados','pac_m07_operaciones','pac_m07_revision_clinica'];
$presentTables = array_values(array_filter($newTables, static fn(string $table): bool => $exists($db, $table)));
$extensionComplete = count($presentColumns) === count($newColumns) && count($presentTables) === count($newTables);
$partialExtension = $presentColumns !== [] || $presentTables !== [];
$result['schema_preflight'] = ['base_engine' => $db->query("SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='pac_respuestas_paciente_modulo_7'")->fetchColumn(), 'existing_extension_columns' => count($presentColumns), 'existing_extension_tables' => count($presentTables), 'extension_complete' => $extensionComplete];
if ($partialExtension && !$extensionComplete) $fail('Se detectó una extensión M07 parcial; no se intenta completar automáticamente.');

$duplicate = $db->query('SELECT COUNT(*) FROM (SELECT id_paciente,id_pregunta,COUNT(*) c FROM pac_respuestas_paciente_modulo_7 GROUP BY id_paciente,id_pregunta HAVING c>1) d')->fetchColumn();
$result['duplicate_patient_question_groups'] = (int)$duplicate;
if ((int)$duplicate > 0) $fail('Existen respuestas M07 duplicadas; se detiene la migración sin resolverlas automáticamente.');

if ($patientA > 0 || $patientB > 0) {
    if ($patientA < 1 || $patientB < 1 || $patientA === $patientB) $fail('Los pacientes sintéticos deben ser dos IDs distintos.');
    $s = $db->prepare('SELECT id,id_clinica,nombres,apellido_paterno,apellido_materno FROM pc_paciente WHERE id IN (?,?) ORDER BY id');
    $s->execute([$patientA, $patientB]); $patients = $s->fetchAll(PDO::FETCH_ASSOC);
    $synthetic = static function (array $row): bool { $name = strtolower((string)$row['nombres'] . ' ' . (string)$row['apellido_paterno'] . ' ' . (string)$row['apellido_materno']); return str_contains($name,'sint') || str_contains($name,'test') || str_contains($name,'prueba'); };
    if (count($patients) !== 2 || count(array_filter($patients, $synthetic)) !== 2) $fail('Los pacientes proporcionados no son ambos sintéticos verificables.');
    $result['synthetic_patient_guard'] = 'PASS';
}

if ($mode === 'preflight') {
    $result['schema'] = $extensionComplete ? 'READY_OR_ALREADY_APPLIED' : 'ADDITIVE_DDL_REQUIRED';
    $result['backfill'] = 'NOT_EXECUTED';
    $result['status'] = 'PASS_READ_ONLY_PREFLIGHT';
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

if ($mode === 'restore') {
    if (!is_file($snapshotPath)) $fail('No existe el snapshot focal indicado.');
    try {
        $snapshot = json_decode((string)file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR);
        if (($snapshot['database'] ?? '') !== $database || !isset($snapshot['original_columns'], $snapshot['original_rows'])) $fail('El snapshot no corresponde a la base solicitada.');
        foreach (array_reverse($newTables) as $table) if ($exists($db, $table)) $db->exec('DROP TABLE `' . $table . '`');
        $originalColumnNames = array_map(static fn(array $row): string => (string)$row['column_name'], $snapshot['original_columns']);
        $dropColumns = array_values(array_diff($newColumns, $originalColumnNames));
        if ($dropColumns !== []) $db->exec('ALTER TABLE pac_respuestas_paciente_modulo_7 ' . implode(', ', array_map(static fn(string $column): string => 'DROP COLUMN `' . $column . '`', $dropColumns)));
        $db->beginTransaction();
        $db->exec('DELETE FROM pac_respuestas_paciente_modulo_7');
        if ($snapshot['original_rows'] !== []) {
            $columnsForInsert = array_keys($snapshot['original_rows'][0]);
            $quoted = implode(',', array_map(static fn(string $column): string => '`' . $column . '`', $columnsForInsert));
            $placeholders = implode(',', array_fill(0, count($columnsForInsert), '?'));
            $insert = $db->prepare('INSERT INTO pac_respuestas_paciente_modulo_7 (' . $quoted . ') VALUES (' . $placeholders . ')');
            foreach ($snapshot['original_rows'] as $row) $insert->execute(array_values($row));
        }
        $db->commit();
        @unlink($snapshotPath);
        $result['schema'] = 'RESTORED_ORIGINAL_SCHEMA';
        $result['backfill'] = 'RESTORED_ORIGINAL_ROWS';
        $result['snapshot'] = 'REMOVED_AFTER_RESTORE';
        $result['status'] = 'PASS_RESTORED_SYNTHETIC';
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        $fail('La restauración focal falló; se requiere conservar el snapshot para recuperación manual.');
    }
}

$snapshot = null;
if ($mode === 'apply') {
    if ($extensionComplete) {
        $result['schema'] = 'IDEMPOTENT_ALREADY_PRESENT';
        $result['backfill'] = 'IDEMPOTENT_NO_WRITE';
        $result['status'] = 'PASS_ALREADY_APPLIED_SYNTHETIC';
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    } else {
        $snapshot = ['version' => 1, 'database' => $database, 'original_columns' => $columns($db, 'pac_respuestas_paciente_modulo_7'), 'original_rows' => []];
        $snapshot['original_rows'] = $db->query('SELECT * FROM pac_respuestas_paciente_modulo_7 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        if ($snapshotPath === '') $snapshotPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'm07-canonical-' . bin2hex(random_bytes(8)) . '.json';
        if (file_put_contents($snapshotPath, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) $fail('No se pudo crear el snapshot temporal antes del DDL.');
        $result['snapshot'] = 'CREATED_TEMPORARY';
        $result['snapshot_path'] = $snapshotPath;
        $missing = array_values(array_diff($newColumns, $baseColumns));
        if ($missing !== []) {
            $definitions = [];
            foreach ($missing as $column) $definitions[] = match ($column) {
                'm07_eficacia_codigo' => "$column VARCHAR(20) NULL",
                'm07_tolerabilidad_codigo' => "$column VARCHAR(24) NULL",
                'm07_efectos_descripcion' => "$column TEXT NULL",
                'm07_version' => "$column BIGINT UNSIGNED NULL",
                'm07_updated_at' => "$column DATETIME NULL",
                'm07_updated_by' => "$column BIGINT NULL",
                'm07_provenance' => "$column VARCHAR(16) NULL",
                default => "$column VARCHAR(32) NULL",
            };
            $db->exec('ALTER TABLE pac_respuestas_paciente_modulo_7 ADD COLUMN ' . implode(', ADD COLUMN ', $definitions));
        }
        $db->exec("CREATE TABLE pac_m07_medicamentos_no_catalogados (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, entry_key CHAR(36) NOT NULL, id_paciente INT NOT NULL, id_clinica INT NOT NULL, ambito VARCHAR(32) NOT NULL, id_tema INT NOT NULL, nombre VARCHAR(255) NOT NULL, nombre_normalizado VARCHAR(255) NOT NULL, estado_registro VARCHAR(16) NOT NULL, respuesta VARCHAR(20) NULL, respuesta_state VARCHAR(32) NOT NULL, dosis TEXT NULL, dosis_state VARCHAR(32) NOT NULL, consumo VARCHAR(20) NULL, consumo_state VARCHAR(32) NOT NULL, eficacia_codigo VARCHAR(20) NULL, eficacia_state VARCHAR(32) NOT NULL, tolerabilidad_codigo VARCHAR(24) NULL, tolerabilidad_state VARCHAR(32) NOT NULL, descripcion_efectos TEXT NULL, descripcion_efectos_state VARCHAR(32) NOT NULL, version BIGINT UNSIGNED NOT NULL, provenance VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by BIGINT NULL, updated_by BIGINT NULL, PRIMARY KEY(id), UNIQUE KEY m07_other_entry_key(id_paciente,id_clinica,ambito,entry_key), KEY m07_other_scope(id_paciente,id_clinica,ambito,id_tema,estado_registro), KEY m07_other_name_review(id_paciente,id_clinica,ambito,id_tema,nombre_normalizado), CONSTRAINT m07_other_patient_fk FOREIGN KEY(id_paciente) REFERENCES pc_paciente(id), CONSTRAINT m07_other_clinic_fk FOREIGN KEY(id_clinica) REFERENCES clinicas(id), CONSTRAINT m07_other_theme_fk FOREIGN KEY(id_tema) REFERENCES pac_temas_modulo_7(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE pac_m07_operaciones (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, idempotency_key CHAR(36) NOT NULL, id_paciente INT NOT NULL, id_clinica INT NOT NULL, ambito VARCHAR(32) NOT NULL, target_type VARCHAR(32) NOT NULL, target_id BIGINT UNSIGNED NOT NULL, expected_version BIGINT UNSIGNED NOT NULL, request_hash CHAR(64) NOT NULL, result_code VARCHAR(48) NOT NULL, action VARCHAR(32) NOT NULL, provenance VARCHAR(16) NOT NULL, before_hash CHAR(64) NULL, after_hash CHAR(64) NULL, actor_id BIGINT NULL, reason VARCHAR(500) NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id), UNIQUE KEY m07_operation_key(id_paciente,idempotency_key), KEY m07_operation_resource(id_paciente,id_clinica,ambito,target_type,target_id), CONSTRAINT m07_operation_patient_fk FOREIGN KEY(id_paciente) REFERENCES pc_paciente(id), CONSTRAINT m07_operation_clinic_fk FOREIGN KEY(id_clinica) REFERENCES clinicas(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE pac_m07_revision_clinica (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, id_paciente INT NOT NULL, id_clinica INT NOT NULL, scope VARCHAR(16) NOT NULL, status VARCHAR(24) NOT NULL, version BIGINT UNSIGNED NOT NULL, reviewed_by BIGINT NULL, reviewed_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id), UNIQUE KEY m07_review_scope(id_paciente,id_clinica,scope), CONSTRAINT m07_review_patient_fk FOREIGN KEY(id_paciente) REFERENCES pc_paciente(id), CONSTRAINT m07_review_clinic_fk FOREIGN KEY(id_clinica) REFERENCES clinicas(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $result['schema'] = 'ADDITIVE_DDL_APPLIED';
    }
}

$rows = $db->query('SELECT * FROM pac_respuestas_paciente_modulo_7 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$update = $db->prepare('UPDATE pac_respuestas_paciente_modulo_7 SET m07_respuesta_state=?,m07_dosis_state=?,m07_resultados_state=?,m07_consumo_state=?,m07_descripcion_state=?,m07_eficacia_codigo=?,m07_eficacia_state=?,m07_tolerabilidad_codigo=?,m07_tolerabilidad_state=?,m07_efectos_descripcion=?,m07_efectos_descripcion_state=?,m07_version=COALESCE(m07_version,1),m07_updated_at=NULL,m07_updated_by=NULL,m07_provenance=COALESCE(m07_provenance,\'LEGACY\') WHERE id=?');
foreach ($rows as $row) {
    $response=trim((string)($row['respuesta']??'')); $dose=trim((string)($row['dosis']??'')); $resultText=trim((string)($row['resultados']??'')); $consumption=trim((string)($row['consumo']??'')); $description=trim((string)($row['descripcion']??''));
    $rs=$response===''?'NOT_DOCUMENTED_HISTORICAL':(in_array($response,['Si','No'],true)?'CONFIRMED':'PENDING_REVIEW'); $ds=$dose===''?($response==='No'?'NOT_APPLICABLE':'NOT_DOCUMENTED_HISTORICAL'):($response==='No'?'PENDING_REVIEW':'CONFIRMED'); $cs=$consumption===''?($response==='No'?'NOT_APPLICABLE':'NOT_DOCUMENTED_HISTORICAL'):(in_array($consumption,['Si','No'],true)&&$response!=='No'?'CONFIRMED':($response==='No'?'PENDING_REVIEW':'PENDING_REVIEW')); $t3=match($resultText){'Funciono','No funciono','Tuve efectos adversos'=>'CONFIRMED',''=>($response==='No'?'NOT_APPLICABLE':'NOT_DOCUMENTED_HISTORICAL'),default=>'PENDING_REVIEW'}; $eff=$resultText==='Funciono'?'FUNCIONO':($resultText==='No funciono'?'NO_FUNCIONO':null); $effState=$resultText==='Funciono'||$resultText==='No funciono'?'CONFIRMED':($response==='No'?'NOT_APPLICABLE':($resultText===''?'NOT_DOCUMENTED_HISTORICAL':'PENDING_REVIEW')); $tol=$resultText==='Tuve efectos adversos'?'PRESENTO_EFECTOS':null; $tolState=$resultText==='Tuve efectos adversos'?'CONFIRMED':($response==='No'?'NOT_APPLICABLE':'NOT_DOCUMENTED_HISTORICAL'); $descState=$description===''?($response==='No'?'NOT_APPLICABLE':($resultText==='Tuve efectos adversos'?'NOT_DOCUMENTED_HISTORICAL':'NOT_APPLICABLE')):($resultText==='Tuve efectos adversos'?'CONFIRMED':'PENDING_REVIEW'); $effects=$resultText==='Tuve efectos adversos'&&$description!==''?$description:null;
    $update->execute([$rs,$ds,$t3,$cs,$description===''?$descState:($resultText==='Tuve efectos adversos'?'CONFIRMED':'PENDING_REVIEW'),$eff,$effState,$tol,$tolState,$effects,$descState,(int)$row['id']]);
}
$notNull = ['m07_respuesta_state','m07_dosis_state','m07_resultados_state','m07_consumo_state','m07_descripcion_state','m07_eficacia_state','m07_tolerabilidad_state','m07_efectos_descripcion_state','m07_version','m07_provenance'];
foreach ($notNull as $column) $db->exec("ALTER TABLE pac_respuestas_paciente_modulo_7 MODIFY COLUMN {$column} " . ($column === 'm07_version' ? 'BIGINT UNSIGNED' : ($column === 'm07_provenance' ? 'VARCHAR(16)' : 'VARCHAR(32)')) . ' NOT NULL');
$result['backfill'] = 'PASS_DETERMINISTIC_LEGACY';
$result['status'] = 'PASS_APPLIED_SYNTHETIC';
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
