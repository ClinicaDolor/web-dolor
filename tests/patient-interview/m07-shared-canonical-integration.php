<?php

declare(strict_types=1);

/**
 * Real MySQL synthetic integration for M07CanonicalService.
 *
 * The test is opt-in and requires a durable pre-run snapshot of the exact
 * synthetic scope. It preserves append-only operation audit rows and therefore
 * never attempts destructive cleanup of audited clinical records. It never
 * opens bd_clinica and never calls the application routes.
 */
$root = dirname(__DIR__, 2);
require_once __DIR__ . '/m07-snapshot-inventory.php';
require_once __DIR__ . '/m07-integration-commit-fault.php';

$options = [];
foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--')) continue;
    [$key, $value] = array_pad(explode('=', $argument, 2), 2, '1');
    $options[substr($key, 2)] = $value;
}
$database = (string)($options['database'] ?? '');
$patientA = (int)($options['patient-a'] ?? 0);
$patientB = (int)($options['patient-b'] ?? 0);
$durableSnapshotPath = (string)($options['snapshot'] ?? '');
$allow = array_key_exists('allow-synthetic-write', $options);
$connectionOnly = array_key_exists('connection-only', $options);
$pendingDmlOnly = array_key_exists('pending-dml-only', $options);
$outputEmitted = false;
$currentOperation = 'startup';
$currentTest = 'NOT_REACHED';
$clinicalSnapshotTaken = false;
$testIdempotencyKeys = [];
$runId = bin2hex(random_bytes(12));
$testKey = static function () use (&$testIdempotencyKeys): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $key = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    $testIdempotencyKeys[] = $key;
    return $key;
};
$report = [
    'phase' => 'M07_SHARED_CANONICAL_IMPLEMENTATION_01',
    'database' => $database,
    'preflight' => 'NOT_RUN',
    'tests' => [],
    'restoration' => 'NOT_RUN',
    'clinical_snapshot' => 'NOT_REACHED',
    'errors' => [],
    'connection_stage' => 'argument_validation',
    'connection_failure_category' => '',
    'connection_error_code' => '',
    'child_process_started' => 'PASS',
    'child_pdo_connected' => 'NOT_REACHED',
    'child_database_verified' => 'NOT_REACHED',
    'independent_readback_connection' => 'NOT_REACHED',
    'child_service_initialized' => 'NOT_REACHED',
    'last_completed_operation' => 'argument_validation',
    'first_failed_operation' => 'NOT_REACHED',
    'first_failed_test' => 'NOT_REACHED',
    'first_error_class' => 'NONE',
    'first_error_original_class' => 'NONE',
    'first_error_sqlstate' => 'NONE',
    'first_error_driver_code' => 'NONE',
    'first_error_table' => 'NONE',
    'first_error_constraint' => 'NONE',
    'first_error_message_sanitized' => 'NONE',
    'rollback_check_before_restoration' => 'NOT_REACHED',
    'child_exit_code' => 0,
    'tests_started' => [],
    'tests_passed' => [],
    'tests_failed' => [],
    'tests_not_reached' => [],
    'clinical_restoration' => 'NOT_REACHED',
    'durable_snapshot' => 'NOT_REACHED',
    'question_link_schema' => 'NOT_REACHED',
    'test_data_cleanup' => 'NOT_REACHED',
    'test_run_id' => $runId,
    'retained_test_records' => [],
    'original_baseline_preserved' => 'NOT_REACHED',
    'authorized_synthetic_rows_retained' => 'NOT_REACHED',
    'unexpected_data_differences' => 'NOT_REACHED',
    'credential_restoration' => 'RUNNER_OWNS_CREDENTIAL_RESTORATION',
    'connection_diagnostics' => [
        'php_executable' => PHP_BINARY,
        'php_version' => PHP_VERSION,
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'host' => 'NOT_VERIFIED',
        'port' => 'NOT_VERIFIED',
        'database_requested' => $database,
        'database_effective' => 'NOT_VERIFIED',
    ],
];
$emitJson = static function (array $payload, int $exitCode = 0) use (&$outputEmitted): never {
    $outputEmitted = true;
    while (ob_get_level() > 0) ob_end_clean();
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($encoded === false) {
        $encoded = '{"status":"FAIL","error":"No fue posible serializar el resultado JSON.","child_exit_code":1}';
        $exitCode = 1;
    }
    echo $encoded . PHP_EOL;
    exit($exitCode);
};
ob_start();
register_shutdown_function(static function () use (&$outputEmitted, &$report, &$currentOperation, &$currentTest, &$clinicalSnapshotTaken, $emitJson): void {
    if ($outputEmitted) return;
    if ($clinicalSnapshotTaken) {
        $report['restoration'] = 'NOT_PERFORMED_APPEND_ONLY_AUDIT_PRESERVED';
        $report['clinical_restoration'] = 'NOT_PERFORMED_APPEND_ONLY_AUDIT_PRESERVED';
        $report['test_data_cleanup'] = 'BLOCKED_AUDIT_TRACEABILITY_PRESERVED';
    }
    $lastError = error_get_last();
    $report['status'] = 'FAIL';
    $report['first_failed_operation'] = $currentOperation;
    $report['first_failed_test'] = $currentTest;
    $report['first_error_class'] = 'PhpFatalError';
    $report['first_error_message_sanitized'] = 'El proceso PHP terminó de forma anormal antes de emitir su resultado JSON.';
    $report['errors'][] = 'Salida JSON no emitida por terminación anormal del proceso hijo.';
    $report['child_exit_code'] = 1;
    if (is_array($lastError) && isset($lastError['type'])) {
        $report['first_error_type'] = (string)$lastError['type'];
    }
    $emitJson($report, 1);
});
$blocked = static function(string $message, int $exitCode = 2) use (&$report, $emitJson): never {
    $report['status']='BLOCKED';
    $report['errors'][]=$message;
    $report['child_exit_code']=$exitCode;
    if($report['first_failed_operation']==='NOT_REACHED'){
        $report['first_failed_operation']=$report['connection_stage'];
        $report['first_error_class']='GuardBlocked';
        $report['first_error_message_sanitized']=$message;
    }
    $emitJson($report, $exitCode);
};
$findPdoException = static function (?Throwable $exception): ?PDOException {
    $current = $exception;
    while ($current instanceof Throwable) {
        if ($current instanceof PDOException) return $current;
        $current = $current->getPrevious();
    }
    return null;
};
$safeIdentifier = static function (?string $value): string {
    $value = trim((string)$value);
    return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $value) === 1 ? $value : 'NONE';
};
$safeSchemaNames = static function (?string $message) use ($safeIdentifier): array {
    $message = (string)$message;
    $table = 'NONE';
    $constraint = 'NONE';
    if (preg_match("/\\btable\\s+[`'\"]?(?:[A-Za-z][A-Za-z0-9_]*\\.)?([A-Za-z][A-Za-z0-9_]*)[`'\"]?/i", $message, $match)) {
        $table = $safeIdentifier($match[1]);
    }
    if (preg_match("/\\bconstraint\\s+[`'\"]?([A-Za-z][A-Za-z0-9_]*)[`'\"]?/i", $message, $match)) {
        $constraint = $safeIdentifier($match[1]);
    } elseif (preg_match("/\\bfor\\s+key\\s+[`'\"]?([A-Za-z][A-Za-z0-9_]*)[`'\"]?/i", $message, $match)) {
        $constraint = $safeIdentifier($match[1]);
    }
    return ['table' => $table, 'constraint' => $constraint];
};
$sanitizeFailure = static function(Throwable $exception) use ($findPdoException, $safeSchemaNames): array {
    $pdoException = $findPdoException($exception);
    $sqlstate = 'NONE';
    $driverCode = 'NONE';
    $table = 'NONE';
    $constraint = 'NONE';
    $originalClass = get_class($exception);
    if ($pdoException !== null) {
        $originalClass = 'PDOException';
        $errorInfo = is_array($pdoException->errorInfo ?? null) ? $pdoException->errorInfo : [];
        $sqlstate = (string)($errorInfo[0] ?? $pdoException->getCode() ?: 'NONE');
        $driverCode = (string)($errorInfo[1] ?? 'NONE');
        $names = $safeSchemaNames($pdoException->getMessage());
        $table = $names['table'];
        $constraint = $names['constraint'];
    }
    if ($exception instanceof App\Services\M07CanonicalException) {
        $class = $pdoException !== null ? 'M07CanonicalException+PDOException' : 'M07CanonicalException';
        $message = 'M07CanonicalException: '.$exception->codeName;
        if ($pdoException !== null) {
            $message .= '; SQLSTATE='.$sqlstate.'; DRIVER_CODE='.$driverCode;
            if ($table !== 'NONE') $message .= '; TABLE='.$table;
            if ($constraint !== 'NONE') $message .= '; CONSTRAINT='.$constraint;
        }
        return ['class' => $class, 'original_class' => $originalClass, 'sqlstate' => $sqlstate, 'driver_code' => $driverCode, 'table' => $table, 'constraint' => $constraint, 'message' => $message];
    }
    if ($pdoException !== null) {
        return ['class' => 'PDOException', 'original_class' => 'PDOException', 'sqlstate' => $sqlstate, 'driver_code' => $driverCode, 'table' => $table, 'constraint' => $constraint, 'message' => 'PDOException; SQLSTATE='.$sqlstate.'; DRIVER_CODE='.$driverCode];
    }
    if ($exception instanceof RuntimeException) {
        return ['class' => 'RuntimeException', 'original_class' => 'RuntimeException', 'sqlstate' => 'NONE', 'driver_code' => 'NONE', 'table' => 'NONE', 'constraint' => 'NONE', 'message' => 'Aserción o error de prueba sanitizado.'];
    }
    return ['class' => get_class($exception), 'original_class' => get_class($exception), 'sqlstate' => 'NONE', 'driver_code' => 'NONE', 'table' => 'NONE', 'constraint' => 'NONE', 'message' => 'Excepción de prueba sanitizada.'];
};
if($database!=='clinica_dolor_demo_20261010_final') $blocked('Base no allowlisted.');
if($patientA<1||$patientB<1||$patientA===$patientB) $blocked('Se requieren dos pacientes sintéticos distintos.');
if(!$allow && !$connectionOnly) $blocked('Se requiere --allow-synthetic-write explícito.');
if($allow && ($durableSnapshotPath === '' || !is_file($durableSnapshotPath) || !is_readable($durableSnapshotPath))) $blocked('Se requiere un snapshot durable sintético legible antes de DML.');
$env=[]; foreach(file($root.'/.env',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){$line=trim($line);if($line===''||str_starts_with($line,'#')||!str_contains($line,'='))continue;[$k,$v]=explode('=',$line,2);$env[trim($k)]=trim($v," \t\n\r\0\x0B\"'");}
$report['connection_diagnostics']['host'] = (string)($env['DB_HOST'] ?? 'NOT_VERIFIED');
$report['connection_diagnostics']['port'] = (string)($env['DB_PORT'] ?? 'NOT_VERIFIED');
foreach(['DB_HOST','DB_PORT','DB_USERNAME','DB_PASSWORD'] as $key) {
    if(!array_key_exists($key,$env)) {
        $report['connection_failure_category']='parameters_missing';
        $blocked('Falta configuración local.');
    }
}
if(!extension_loaded('pdo_mysql')) {
    $report['connection_failure_category']='driver_missing';
    $blocked('Driver PDO MySQL no disponible.');
}
if(!in_array(strtolower($env['DB_HOST']),['127.0.0.1','localhost','::1'],true)||(string)$env['DB_PORT']!=='3306') {
    $report['connection_failure_category']='parameters_invalid';
    $blocked('Conexión no local.');
}
$report['connection_stage']='pdo_connect';
try {
    $db=new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$env['DB_HOST'],$env['DB_PORT'],$database),$env['DB_USERNAME'],$env['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $report['child_pdo_connected']='PASS';
    $report['last_completed_operation']='pdo_connection';
} catch(PDOException $exception) {
    $message=strtolower($exception->getMessage());
    $code=(string)$exception->getCode();
    $category=($code==='1045'||str_contains($message,'access denied'))?'authentication_rejected':(($code==='1049'||str_contains($message,'unknown database'))?'database_incorrect':'server_unavailable');
    $report['connection_failure_category']=$category;
    $report['connection_error_code']=$code;
    $blocked('No fue posible establecer PDO MySQL sintético.');
}
$report['connection_stage']='database_identity';
try {
    $activeDatabase=(string)$db->query('SELECT DATABASE()')->fetchColumn();
} catch(Throwable $exception) {
    $report['connection_stage']='post_connection_sql';
    $report['connection_failure_category']='post_connection_sql';
    $report['connection_error_code']=(string)$exception->getCode();
    $blocked('Fallo SQL posterior a conexión PDO exitosa.');
}
$report['connection_diagnostics']['database_effective']=$activeDatabase;
if($activeDatabase!==$database) {
    $report['connection_failure_category']='database_incorrect';
    $blocked('La base activa no coincide.');
}
$report['child_database_verified']='PASS';
$report['last_completed_operation']='database_identity';
$report['connection_stage']='independent_readback_connection';
try {
    $readbackDb = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$env['DB_HOST'],$env['DB_PORT'],$database),$env['DB_USERNAME'],$env['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $readbackDatabase = (string)$readbackDb->query('SELECT DATABASE()')->fetchColumn();
    if ($readbackDatabase !== $database) $blocked('La conexión independiente de readback no coincide con la base allowlisted.');
    $report['independent_readback_connection']='PASS_EFFECTIVE_DATABASE_VERIFIED';
} catch (Throwable) {
    $report['connection_failure_category']='independent_readback_connection';
    $blocked('No se pudo establecer y verificar la conexión independiente de readback.');
}
$serverIdentity = static function(PDO $connection): array {
    $statement = $connection->query('SELECT DATABASE() AS database_name, @@hostname AS server_name, @@port AS server_port');
    return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
};
try {
    $primaryIdentity = $serverIdentity($db);
    $readbackIdentity = $serverIdentity($readbackDb);
    $identityMatches = static fn(array $identity): bool => ($identity['database_name'] ?? null) === $database
        && strtolower((string)($identity['server_name'] ?? '')) === 'pc'
        && (int)($identity['server_port'] ?? 0) === 3306;
    if (!$identityMatches($primaryIdentity) || !$identityMatches($readbackIdentity)) {
        $report['connection_failure_category'] = 'effective_server_identity_mismatch';
        $blocked('Servidor o puerto MySQL efectivo distinto de PC:3306.');
    }
    $report['connection_diagnostics']['server_name'] = 'PC';
    $report['connection_diagnostics']['server_port'] = 3306;
} catch (Throwable) {
    $report['connection_failure_category'] = 'effective_server_identity_unverified';
    $blocked('No se pudo verificar la identidad efectiva del servidor MySQL.');
}
$report['connection_stage']='post_connection_read';
try {
    $patientQuery=$db->prepare('SELECT id,id_clinica,nombres,apellido_paterno,apellido_materno FROM pc_paciente WHERE id IN (?,?) ORDER BY id');
    $patientQuery->execute([$patientA,$patientB]);
    $patients=$patientQuery->fetchAll();
} catch(Throwable $exception) {
    $report['connection_stage']='post_connection_sql';
    $report['connection_failure_category']='post_connection_sql';
    $report['connection_error_code']=(string)$exception->getCode();
    $blocked('Fallo SQL posterior a conexión PDO exitosa.');
}
$synthetic=static function(array $row):bool{$name=strtolower((string)$row['nombres'].' '.(string)$row['apellido_paterno'].' '.(string)$row['apellido_materno']);return str_contains($name,'sint')||str_contains($name,'test')||str_contains($name,'prueba');};
if(count($patients)!==2||count(array_filter($patients,$synthetic))!==2)$blocked('Los pacientes no son sintéticos verificables.');
$clinicA=(int)$patients[0]['id_clinica'];$clinicB=(int)$patients[1]['id_clinica'];
if ($pendingDmlOnly && ($clinicA !== 9101 || $clinicB !== 9102)) {
    $blocked('La asociación paciente-clínica no coincide con las fixtures sintéticas autorizadas.');
}
if($connectionOnly) {
    $report['preflight']='PASS_READ_ONLY_CHILD_CONNECTION';
    $report['status']='PASS_READ_ONLY_CHILD_CONNECTION';
    $report['tests']['child_database_identity']='PASS';
    $report['tests']['synthetic_patients_read']='PASS';
    $emitJson($report, 0);
}
$report['last_completed_operation']='synthetic_patient_read';
$tableRows=static function(PDO $db,string $table,int $patient):array{$s=$db->prepare("SELECT * FROM {$table} WHERE id_paciente=? ORDER BY id");$s->execute([$patient]);return $s->fetchAll(PDO::FETCH_ASSOC);};
$independentOtherRead=static function(int $entryId,int $questionId) use ($readbackDb,$patientA,$clinicA):?array{$statement=$readbackDb->prepare("SELECT id,id_paciente,id_clinica,id_tema,id_pregunta,nombre,dosis,consumo,eficacia_codigo,tolerabilidad_codigo,descripcion_efectos,version,estado_registro FROM pac_m07_medicamentos_no_catalogados WHERE id=:id AND id_paciente=:patient AND id_clinica=:clinic AND id_pregunta=:question AND ambito='M07_P09'");$statement->execute([':id'=>$entryId,':patient'=>$patientA,':clinic'=>$clinicA,':question'=>$questionId]);return $statement->fetch(PDO::FETCH_ASSOC)?:null;};
$operationCountForKey=static function(PDO $connection,int $patientId,string $key):int{$statement=$connection->prepare('SELECT COUNT(*) FROM pac_m07_operaciones WHERE id_paciente=? AND idempotency_key=?');$statement->execute([$patientId,$key]);return (int)$statement->fetchColumn();};
$hash=static fn(array $rows):string=>hash('sha256',json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
$assertDatabaseIdentity=static function(PDO $primary,PDO $independent) use ($database,$serverIdentity):void {
    foreach ([$primary,$independent] as $connection) {
        $identity = $serverIdentity($connection);
        if (($identity['database_name'] ?? null) !== $database
            || strtolower((string)($identity['server_name'] ?? '')) !== 'pc'
            || (int)($identity['server_port'] ?? 0) !== 3306) {
            throw new RuntimeException('Base o servidor efectivo cambió durante la prueba.');
        }
    }
};
$tables=['pac_respuestas_paciente_modulo_7','pac_m07_medicamentos_no_catalogados','pac_m07_operaciones','pac_m07_revision_clinica'];
$authorizedRetainedIds=[
    'pac_respuestas_paciente_modulo_7'=>[314],
    'pac_m07_medicamentos_no_catalogados'=>[3,4,5,6,7,8],
    'pac_m07_operaciones'=>range(23,49),
    'pac_m07_revision_clinica'=>[],
];
$snapshotPatientTables=static function(PDO $connection,int $patientId) use ($tables,$tableRows):array{$snapshot=[];foreach($tables as $table)$snapshot[$table]=$tableRows($connection,$table,$patientId);return $snapshot;};
if ($allow) {
    $linkColumn=$db->prepare('SELECT column_name,column_type,is_nullable FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
    $linkColumn->execute(['pac_m07_medicamentos_no_catalogados','id_pregunta']);
    $columnMetadata=$linkColumn->fetch(PDO::FETCH_ASSOC) ?: [];
    $linkIndex=$db->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=? AND column_name=? AND non_unique=1');
    $linkIndex->execute(['pac_m07_medicamentos_no_catalogados','m07_other_question_idx','id_pregunta']);
    $linkForeignKey=$db->prepare('SELECT rc.constraint_name,rc.delete_rule,rc.update_rule,kcu.referenced_table_name,kcu.referenced_column_name FROM information_schema.referential_constraints rc INNER JOIN information_schema.key_column_usage kcu ON kcu.constraint_schema=rc.constraint_schema AND kcu.constraint_name=rc.constraint_name WHERE rc.constraint_schema=DATABASE() AND kcu.table_name=? AND kcu.column_name=?');
    $linkForeignKey->execute(['pac_m07_medicamentos_no_catalogados','id_pregunta']);
    $foreignKeyMetadata=$linkForeignKey->fetch(PDO::FETCH_ASSOC) ?: [];
    if (($columnMetadata['column_type'] ?? '') !== 'int(11)' || ($columnMetadata['is_nullable'] ?? '') !== 'YES'
        || (int)$linkIndex->fetchColumn() !== 1
        || ($foreignKeyMetadata['constraint_name'] ?? '') !== 'm07_other_question_fk'
        || ($foreignKeyMetadata['delete_rule'] ?? '') !== 'RESTRICT'
        || ($foreignKeyMetadata['update_rule'] ?? '') !== 'RESTRICT'
        || ($foreignKeyMetadata['referenced_table_name'] ?? '') !== 'pac_preguntas_modulo_7'
        || ($foreignKeyMetadata['referenced_column_name'] ?? '') !== 'id') {
        $blocked('La columna, índice o FK de pregunta M07 no coincide exactamente con el esquema autorizado.');
    }
    $report['question_link_schema']='PASS_COLUMN_INDEX_FK_RESTRICT_VERIFIED';
    try {
        $durableSnapshot = json_decode((string)file_get_contents($durableSnapshotPath), true, 512, JSON_THROW_ON_ERROR);
        $snapshotHash = (string)($durableSnapshot['snapshot_sha256'] ?? '');
        $snapshotForHash = $durableSnapshot;
        unset($snapshotForHash['snapshot_sha256']);
        $computedSnapshotHash = hash('sha256', json_encode($snapshotForHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $expectedTables = $tables;
        if (($durableSnapshot['schema_version'] ?? '') !== 'M07_CLINICAL_BASELINE_V1'
            || ($durableSnapshot['database'] ?? '') !== $database
            || ($durableSnapshot['patient_ids'] ?? []) !== [$patientA, $patientB]
            || ($durableSnapshot['clinic_ids'] ?? []) !== [$clinicA, $clinicB]
            || ($durableSnapshot['tables'] ?? []) !== $expectedTables
            || !m07SnapshotInventoryHasExactTables($durableSnapshot['rows'] ?? null, $expectedTables)
            || !m07SnapshotInventoryHasExactTables($durableSnapshot['manifest'] ?? null, $expectedTables)
            || $snapshotHash === ''
            || !hash_equals($snapshotHash, $computedSnapshotHash)) {
            $blocked('El snapshot durable no cumple la identidad, alcance o integridad requeridos.');
        }
        foreach ($tables as $table) {
            $snapshotRows = $durableSnapshot['rows'][$table] ?? null;
            $tableManifest = $durableSnapshot['manifest'][$table] ?? null;
            if (!is_array($snapshotRows) || !is_array($tableManifest)
                || (int)($tableManifest['row_count'] ?? -1) !== count($snapshotRows)
                || !hash_equals((string)($tableManifest['sha256'] ?? ''), hash('sha256', json_encode($snapshotRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)))) {
                $blocked('El manifiesto durable no coincide con las filas almacenadas.');
            }
        }
        if ($pendingDmlOnly) {
            $expectedIds = [
                'pac_respuestas_paciente_modulo_7' => [310, 314],
                'pac_m07_medicamentos_no_catalogados' => [3, 4, 5, 6, 7, 8],
                'pac_m07_operaciones' => range(23, 49),
                'pac_m07_revision_clinica' => [],
            ];
            $snapshotPatients = [$patientA => [], $patientB => []];
            foreach ($tables as $table) {
                foreach ($durableSnapshot['rows'][$table] as $row) {
                    $owner = (int)($row['id_paciente'] ?? 0);
                    if (!array_key_exists($owner, $snapshotPatients)) {
                        $blocked('El snapshot contiene una fila fuera del alcance sintético autorizado.');
                    }
                    $snapshotPatients[$owner][$table][] = $row;
                }
                $actualIds = array_map(static fn(array $row): int => (int)$row['id'], $snapshotPatients[$patientA][$table] ?? []);
                sort($actualIds, SORT_NUMERIC);
                $requiredIds = $expectedIds[$table];
                sort($requiredIds, SORT_NUMERIC);
                if ($actualIds !== $requiredIds || ($snapshotPatients[$patientB][$table] ?? []) !== []) {
                    $blocked('El snapshot no coincide exactamente con los 34 registros sintéticos previamente autorizados.');
                }
            }
            $responseRowsById = [];
            foreach ($snapshotPatients[$patientA]['pac_respuestas_paciente_modulo_7'] as $row) $responseRowsById[(int)$row['id']] = $row;
            if (count($responseRowsById) !== 2
                || (int)($responseRowsById[310]['id_paciente'] ?? 0) !== $patientA
                || (int)($responseRowsById[310]['id_pregunta'] ?? 0) !== 1
                || (int)($responseRowsById[314]['id_paciente'] ?? 0) !== $patientA
                || (int)($responseRowsById[314]['id_pregunta'] ?? 0) !== 2) {
                $blocked('Las dos respuestas sintéticas conservada y añadida no coinciden con paciente y pregunta confirmados.');
            }
            $expectedOtherQuestions = [3 => 8, 4 => 15, 5 => 22, 6 => 30, 7 => 37, 8 => 46];
            foreach ($snapshotPatients[$patientA]['pac_m07_medicamentos_no_catalogados'] as $row) {
                $id = (int)$row['id'];
                if ((int)($row['id_clinica'] ?? 0) !== $clinicA
                    || (int)($row['id_pregunta'] ?? 0) !== $expectedOtherQuestions[$id]
                    || (string)($row['ambito'] ?? '') !== 'M07_P09') {
                    $blocked('Una relación de medicamento sintético retenido no coincide con paciente, clínica o pregunta.');
                }
            }
            foreach ($snapshotPatients[$patientA]['pac_m07_operaciones'] as $row) {
                if ((int)($row['id_clinica'] ?? 0) !== $clinicA) {
                    $blocked('Una operación sintética retenida pertenece a otra clínica.');
                }
            }
            $report['previous_rows_expected'] = 34;
            $report['previous_rows_verified'] = 34;
            $report['patient_b_prior_rows'] = 0;
            $report['previous_row_relations'] = 'PASS_PATIENT_CLINIC_QUESTION_SCOPE';
        }
        $report['durable_snapshot'] = 'PASS_HASH_AND_SCOPE_VERIFIED';
        $report['durable_snapshot_path'] = $durableSnapshotPath;
    } catch (Throwable) {
        $blocked('No fue posible validar el snapshot durable antes de DML.');
    }
}
try {
    $beforeA=[];$beforeB=[];foreach($tables as $table){$beforeA[$table]=$tableRows($db,$table,$patientA);$beforeB[$table]=$tableRows($db,$table,$patientB);}
    if ($allow) {
        foreach ($tables as $table) {
            $durableA = array_values(array_filter($durableSnapshot['rows'][$table], static fn(array $row): bool => (int)($row['id_paciente'] ?? 0) === $patientA));
            $durableB = array_values(array_filter($durableSnapshot['rows'][$table], static fn(array $row): bool => (int)($row['id_paciente'] ?? 0) === $patientB));
            $normalize = static fn(array $rows): string => hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if ($normalize($beforeA[$table]) !== $normalize($durableA) || $normalize($beforeB[$table]) !== $normalize($durableB)) {
                $blocked('El estado MySQL cambió desde la creación del snapshot durable; no se escribirá.');
            }
        }
    }
    $clinicalSnapshotTaken = true;
    $report['clinical_snapshot'] = 'PASS';
} catch(Throwable $exception) {
    $report['connection_stage']='post_connection_sql';
    $report['connection_failure_category']='post_connection_sql';
    $report['connection_error_code']=(string)$exception->getCode();
    $blocked('Fallo SQL posterior a conexión PDO exitosa.');
}
$scope=['patient_id'=>$patientA,'clinic_id'=>$clinicA,'role'=>'synthetic'];
$recordByQuestion=static function(array $records,int $questionId):?array{
    foreach($records as $record) if((int)($record['question_id']??0)===$questionId) return $record;
    return null;
};
$currentOperation='service_initialization';
$currentTest='NOT_REACHED';
$operationSnapshot = null;
$expectedCatalogIds=[];
$expectedOtherIds=[];
$pendingAuditKeys=[];
$allTests=$pendingDmlOnly
    ?['other_medication_idempotency_payload_conflict','other_medication_stale_version_conflict','append_only_operation_audit','other_medication_atomic_rollback']
    :['unified_read_46_catalog_records','finalization_gate_reflects_activation','activation_gate_closed_by_default','closed_gate_finalization_denied','other_question_catalog_mapping_all_six','other_question_invalid_id_denial','synthetic_target_rows_unoccupied','draft_fixture_is_unanswered','explicit_unanswered_draft','draft_is_not_negative','premature_finalization_rejected','confirmed_catalog_capture','canonical_readback','canonical_independent_readback','idempotency_replay','idempotency_payload_conflict','optimistic_version_conflict','other_medication_entry_key_identity','other_question_link_and_partial_status','other_question_theme_guard','other_active_entry_blocks_negative','other_medication_idempotent_replay','other_medication_idempotency_payload_conflict','other_medication_full_capture_readback','other_medication_stale_version_conflict','other_medication_atomic_rollback','other_medication_full_capture_all_six','other_medication_independent_readback','other_medication_f5_recovery','other_wrong_patient_denial','multiple_other_medications','review_staleness_protection','cross_clinic_denial','append_only_operation_audit'];
$assert=static function(bool $condition,string $name) use (&$report,&$currentOperation,&$currentTest):void{
    $currentTest=$name;
    $report['tests_started'][]=$name;
    $report['tests'][$name]=$condition?'PASS':'FAIL';
    if($condition){$report['tests_passed'][]=$name;$report['last_completed_operation']=$currentOperation;return;}
    $report['tests_failed'][]=$name;
    throw new RuntimeException($name);
};
try{
    require_once $root.'/vendor/autoload.php';
    $service=new App\Services\M07CanonicalService($db,new App\Services\M07CanonicalActivationGate(false));
    $enabled=new App\Services\M07CanonicalService($db,new App\Services\M07CanonicalActivationGate(true));
    $verifyPendingState=static function(int $expectedMedicationRows,int $expectedAuditRows) use ($assertDatabaseIdentity,$tables,$tableRows,$readbackDb,$patientA,$patientB,$clinicA,$beforeA,$beforeB,$hash,&$expectedOtherIds,&$pendingAuditKeys):void {
        $assertDatabaseIdentity($readbackDb,$readbackDb);
        $currentA=[];$currentB=[];
        foreach($tables as $table){$currentA[$table]=$tableRows($readbackDb,$table,$patientA);$currentB[$table]=$tableRows($readbackDb,$table,$patientB);}
        if($hash($currentB)!==$hash($beforeB))throw new RuntimeException('El paciente sintético B cambió durante la prueba.');
        $previousByTable=[];
        foreach($tables as $table){
            $previousByTable[$table]=[];
            foreach($beforeA[$table] as $row)$previousByTable[$table][(string)$row['id']]=$row;
            $currentById=[];foreach($currentA[$table] as $row)$currentById[(string)$row['id']]=$row;
            foreach($previousByTable[$table] as $id=>$row){
                if(!isset($currentById[$id])||$hash([$currentById[$id]])!==$hash([$row]))throw new RuntimeException('Un registro preexistente fue alterado durante una prueba.');
            }
        }
        $newMedications=[];
        foreach($currentA['pac_m07_medicamentos_no_catalogados'] as $row){
            if(!isset($previousByTable['pac_m07_medicamentos_no_catalogados'][(string)$row['id']]))$newMedications[]=$row;
        }
        $newOperations=[];
        foreach($currentA['pac_m07_operaciones'] as $row){
            if(!isset($previousByTable['pac_m07_operaciones'][(string)$row['id']]))$newOperations[]=$row;
        }
        $newOtherClinicalRows=[];
        foreach(['pac_respuestas_paciente_modulo_7','pac_m07_revision_clinica'] as $table){
            foreach($currentA[$table] as $row){if(!isset($previousByTable[$table][(string)$row['id']]))$newOtherClinicalRows[]=$row;}
        }
        if(count($newMedications)!==$expectedMedicationRows||count($newOperations)!==$expectedAuditRows||$newOtherClinicalRows!==[]){
            throw new RuntimeException('El delta por caso excede el presupuesto sintético autorizado.');
        }
        foreach($newMedications as $row){
            if(!isset($expectedOtherIds[(int)$row['id']])||(int)$row['id_paciente']!==$patientA||(int)$row['id_clinica']!==$clinicA||(int)$row['id_pregunta']!==8){
                throw new RuntimeException('Un medicamento nuevo no coincide con el fixture autorizado.');
            }
        }
        foreach($newOperations as $row){
            if(!isset($pendingAuditKeys[(string)($row['idempotency_key']??'')])||(int)$row['id_paciente']!==$patientA||(int)$row['id_clinica']!==$clinicA
                ||$row['action']!=='CREATE_OTHER'||(int)$row['target_id']!==(int)($newMedications[0]['id']??0)){
                throw new RuntimeException('Una operación nueva no corresponde a la única captura autorizada.');
            }
        }
    };
    $report['child_service_initialized']='PASS';
    $report['last_completed_operation']='service_initialization';
    if($pendingDmlOnly){
        $currentOperation='pending_dml_preconditions';
        $currentTest='other_medication_idempotency_payload_conflict';
        $assertDatabaseIdentity($db,$readbackDb);
        $verifyPendingState(0,0);
        $currentState=$enabled->read($patientA,$clinicA,'synthetic');
        $priorOther=null;
        foreach($currentState['other_medications'] as $entry){
            if((int)($entry['id']??0)===3&&(int)($entry['question_id']??0)===8&&(int)($entry['version']??0)>=2){$priorOther=$entry;break;}
        }
        $assert(is_array($priorOther),'other_medication_idempotency_payload_conflict');

        $currentOperation='pending_dml_idempotency_conflict';
        $pendingName='M07 DML pending '.$runId;
        $pendingCreateKey=$testKey();
        $pendingAuditKeys[$pendingCreateKey]=true;
        $pendingCreated=$enabled->createOtherMedication($scope,8,1,$pendingName,(string)$currentState['version'],1001,$pendingCreateKey);
        $pendingEntryId=(int)$pendingCreated['record']['id'];
        $expectedOtherIds[$pendingEntryId]=true;
        $pendingReplay=$enabled->createOtherMedication($scope,8,1,$pendingName,(string)$currentState['version'],1001,$pendingCreateKey);
        $pendingBeforeConflict=$independentOtherRead($pendingEntryId,8);
        $pendingReplaySameRecord=(int)($pendingReplay['record']['id']??0)===$pendingEntryId
            &&$operationCountForKey($readbackDb,$patientA,$pendingCreateKey)===1;
        try{
            $enabled->createOtherMedication($scope,8,1,'M07 DML conflict '.$runId,(string)$currentState['version'],1001,$pendingCreateKey);
            $pendingConflictDenied=false;
        }catch(App\Services\M07CanonicalException $e){$pendingConflictDenied=$e->codeName==='M07_IDEMPOTENCY_CONFLICT';}
        $pendingAfterConflict=$independentOtherRead($pendingEntryId,8);
        $pendingConflictUnchanged=is_array($pendingBeforeConflict)&&is_array($pendingAfterConflict)
            &&$hash([$pendingBeforeConflict])===$hash([$pendingAfterConflict])
            &&$operationCountForKey($readbackDb,$patientA,$pendingCreateKey)===1;
        $assertDatabaseIdentity($db,$readbackDb);
        $verifyPendingState(1,1);
        $assert($pendingReplaySameRecord&&$pendingConflictDenied&&$pendingConflictUnchanged,'other_medication_idempotency_payload_conflict');

        $currentOperation='pending_dml_stale_version';
        $assertDatabaseIdentity($db,$readbackDb);
        $verifyPendingState(1,1);
        $priorEntryId=(int)$priorOther['id'];
        $priorBefore=$independentOtherRead($priorEntryId,8);
        if(!is_array($priorBefore)||(int)$priorBefore['version']<2)throw new RuntimeException('Synthetic stale-version fixture is unavailable.');
        $staleState=$enabled->read($patientA,$clinicA,'synthetic');
        $staleKey=$testKey();
        try{
            $enabled->updateOtherMedication($scope,8,1,$priorEntryId,(int)$priorBefore['version']-1,['dosis'=>'M07 stale-version probe'],$staleState['version'],1001,$staleKey);
            $pendingStaleDenied=false;
        }catch(App\Services\M07CanonicalException $e){$pendingStaleDenied=$e->codeName==='M07_VERSION_CONFLICT';}
        $priorAfter=$independentOtherRead($priorEntryId,8);
        $pendingStaleUnchanged=is_array($priorBefore)&&is_array($priorAfter)
            &&$hash([$priorBefore])===$hash([$priorAfter])
            &&$operationCountForKey($readbackDb,$patientA,$staleKey)===0;
        $assertDatabaseIdentity($db,$readbackDb);
        $verifyPendingState(1,1);
        $assert($pendingStaleDenied&&$pendingStaleUnchanged,'other_medication_stale_version_conflict');

        $currentOperation='pending_dml_append_only_audit';
        $assertDatabaseIdentity($db,$readbackDb);
        $verifyPendingState(1,1);
        $auditRowsAfterCreate=$tableRows($readbackDb,'pac_m07_operaciones',$patientA);
        $oldAuditById=[];
        foreach($beforeA['pac_m07_operaciones'] as $row)$oldAuditById[(string)$row['id']]=$row;
        $auditById=[];
        foreach($auditRowsAfterCreate as $row)$auditById[(string)$row['id']]=$row;
        $priorAuditRowsUnchanged=true;
        foreach($oldAuditById as $id=>$row){
            if(!isset($auditById[$id])||$hash([$auditById[$id]])!==$hash([$row])){$priorAuditRowsUnchanged=false;break;}
        }
        $newAuditRows=[];
        foreach($auditById as $id=>$row){if(!isset($oldAuditById[$id]))$newAuditRows[]=$row;}
        $pendingOperation=$db->prepare('SELECT id,id_paciente,id_clinica,target_type,target_id,action,result_code FROM pac_m07_operaciones WHERE idempotency_key=? AND id_paciente=?');
        $pendingOperation->execute([$pendingCreateKey,$patientA]);
        $pendingAudit=$pendingOperation->fetch(PDO::FETCH_ASSOC) ?: null;
        $auditMatchesCreate=is_array($pendingAudit)
            &&(int)$pendingAudit['id_paciente']===$patientA
            &&(int)$pendingAudit['id_clinica']===$clinicA
            &&$pendingAudit['target_type']==='OTHER'
            &&(int)$pendingAudit['target_id']===$pendingEntryId
            &&$pendingAudit['action']==='CREATE_OTHER'
            &&$pendingAudit['result_code']==='M07_OTHER_CREATED';
        $assertDatabaseIdentity($db,$readbackDb);
        $verifyPendingState(1,1);
        $assert($priorAuditRowsUnchanged&&count($newAuditRows)===1&&$auditMatchesCreate,'append_only_operation_audit');

        $currentOperation='pending_dml_atomic_rollback';
        $assertDatabaseIdentity($db,$readbackDb);
        $verifyPendingState(1,1);
        $rollbackBefore=$independentOtherRead($pendingEntryId,8);
        if(!is_array($rollbackBefore))throw new RuntimeException('Pending DML rollback row is unavailable.');
        $rollbackKey=$testKey();
        $rollbackDb=new M07IntegrationOneShotCommitFailurePdo(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$env['DB_HOST'],$env['DB_PORT'],$database),
            $env['DB_USERNAME'],$env['DB_PASSWORD'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]
        );
        $rollbackEffectiveDatabase=(string)$rollbackDb->query('SELECT DATABASE()')->fetchColumn();
        $rollbackIdentity=$serverIdentity($rollbackDb);
        if($rollbackEffectiveDatabase!==$database||strtolower((string)($rollbackIdentity['server_name']??''))!=='pc'||(int)($rollbackIdentity['server_port']??0)!==3306)throw new RuntimeException('Pending rollback connection guard failed.');
        $rollbackService=new App\Services\M07CanonicalService($rollbackDb,new App\Services\M07CanonicalActivationGate(true));
        $rollbackState=$rollbackService->read($patientA,$clinicA,'synthetic');
        try{
            $rollbackService->updateOtherMedication($scope,8,1,$pendingEntryId,(int)$rollbackBefore['version'],['dosis'=>'M07 rollback probe'],$rollbackState['version'],1001,$rollbackKey);
            $rollbackObserved=false;
        }catch(App\Services\M07CanonicalException $e){$rollbackObserved=$e->codeName==='M07_ATOMIC_ROLLBACK';}
        $rollbackAfter=$independentOtherRead($pendingEntryId,8);
        $rollbackVerified=$rollbackObserved&&!$rollbackDb->inTransaction()
            &&is_array($rollbackAfter)&&$hash([$rollbackBefore])===$hash([$rollbackAfter])
            &&$operationCountForKey($readbackDb,$patientA,$rollbackKey)===0;
        $assertDatabaseIdentity($db,$readbackDb);
        $assertDatabaseIdentity($rollbackDb,$readbackDb);
        $verifyPendingState(1,1);
        $assert($rollbackVerified,'other_medication_atomic_rollback');
        $rollbackDb=null;
        $report['preflight']='PASS';
        $report['status']='PASS_PENDING_SYNTHETIC_DML_CASES';
    }else{
    $currentOperation='initial_service_read';
    $currentTest='unified_read_46_catalog_records';
    $initial=$enabled->read($patientA,$clinicA,'synthetic');
    $assert(count($initial['records'])===46,'unified_read_46_catalog_records');
    $assert($initial['finalization_enabled']===!($initial['finalized']??false),'finalization_gate_reflects_activation');
    $currentOperation='gate_closed_write_attempt';
    $currentTest='activation_gate_closed_by_default';
    try{$service->captureCatalog($scope,1,['respuesta'=>'Si'],['respuesta'],0,$testKey());$disabled=false;}catch(App\Services\M07CanonicalException $e){$disabled=$e->codeName==='M07_CANONICAL_DISABLED';}
    $assert($disabled,'activation_gate_closed_by_default');
    try{$service->finalizeModule($scope,(string)$initial['version'],$testKey(),1001);$closedFinalizeDenied=false;}catch(App\Services\M07CanonicalException $e){$closedFinalizeDenied=$e->codeName==='M07_CANONICAL_DISABLED';}
    $assert($closedFinalizeDenied,'closed_gate_finalization_denied');
    $expectedQuestionThemes=[8=>1,15=>2,22=>3,30=>4,37=>5,46=>6];
    $catalogQuery=$db->prepare('SELECT q.id,q.id_tema,q.pregunta FROM pac_preguntas_modulo_7 q WHERE q.id IN (8,15,22,30,37,46) ORDER BY q.id');
    $catalogQuery->execute();
    $catalogRows=$catalogQuery->fetchAll(PDO::FETCH_ASSOC);
    $questionThemes=[];
    foreach($catalogRows as $catalogRow){$questionThemes[(int)$catalogRow['id']]=(int)$catalogRow['id_tema'];}
    $otherMappingValid=count($catalogRows)===6;
    foreach($expectedQuestionThemes as $catalogQuestion=>$expectedTheme){
        $matches=array_values(array_filter($catalogRows,static fn(array $row):bool=>(int)$row['id']===$catalogQuestion));
        $otherMappingValid=$otherMappingValid&&count($matches)===1&&(int)$matches[0]['id_tema']===$expectedTheme&&preg_match('/\b(otro|otros|otra|otras)\b/iu',(string)$matches[0]['pregunta'])===1;
    }
    $currentTest='other_question_catalog_mapping_all_six';
    $assert($otherMappingValid,'other_question_catalog_mapping_all_six');
    try{$enabled->createOtherMedication($scope,7,1,'M07 synthetic rejected',(string)$initial['version'],1001,$testKey());$invalidOtherQuestionDenied=false;}catch(App\Services\M07CanonicalException $e){$invalidOtherQuestionDenied=$e->codeName==='M07_OTHER_QUESTION_INVALID';}
    $currentTest='other_question_invalid_id_denial';
    $assert($invalidOtherQuestionDenied,'other_question_invalid_id_denial');
    $existingCatalog=$db->prepare('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente=? AND id_pregunta IN (8,15,22,30,37,46)');
    $existingCatalog->execute([$patientA]);
    $existingOthers=$db->prepare('SELECT COUNT(*) FROM pac_m07_medicamentos_no_catalogados WHERE id_paciente=? AND id_pregunta IN (8,15,22,30,37,46)');
    $existingOthers->execute([$patientA]);
    $targetsUnoccupied=(int)$existingCatalog->fetchColumn()===0&&(int)$existingOthers->fetchColumn()===0;
    $currentTest='synthetic_target_rows_unoccupied';
    $assert($targetsUnoccupied,'synthetic_target_rows_unoccupied');
    $first=null;
    foreach($initial['records'] as $candidate){
        $fields=$candidate['fields']??[];
        $isUnanswered=($candidate['status']??'')==='pending'
            && trim((string)($fields['respuesta']??''))===''
            && trim((string)($fields['dosis']??''))===''
            && trim((string)($fields['resultados']??''))===''
            && trim((string)($fields['consumo']??''))===''
            && trim((string)($fields['descripcion']??''))==='';
        if($isUnanswered){$first=$candidate;break;}
    }
    $currentTest='draft_fixture_is_unanswered';
    $assert(is_array($first),'draft_fixture_is_unanswered');
    $questionId=(int)$first['question_id'];
    $currentOperation='draft_capture';
    $currentTest='explicit_unanswered_draft';
    $operationSnapshot=['A'=>$snapshotPatientTables($db,$patientA),'B'=>$snapshotPatientTables($db,$patientB)];
    $draftKey=$testKey();
    $draft=$enabled->saveUnansweredDraft($scope,$questionId,(int)$first['version'],$draftKey,1001);$assert(($draft['code']??'')==='M07_DRAFT_SAVED','explicit_unanswered_draft');
    $operationSnapshot=null;
    $currentOperation='read_after_draft';
    $currentTest='draft_is_not_negative';
    $draftRead=$enabled->read($patientA,$clinicA,'synthetic');
    $draftRecord=$recordByQuestion($draftRead['records'],$questionId);
    if(is_array($draftRecord)&&(int)($draftRecord['id']??0)>0)$expectedCatalogIds[(int)$draftRecord['id']]=true;
    $assert(is_array($draftRecord)&&($draftRecord['fields']['respuesta']??'')===''&&($draftRecord['status']??'')==='pending','draft_is_not_negative');
    try{$enabled->finalizeModule($scope,(string)$draftRead['version'],$testKey(),1001);$prematureFinalizeDenied=false;}catch(App\Services\M07CanonicalException $e){$prematureFinalizeDenied=$e->codeName==='M07_PENDING_RESPONSES';}
    $currentTest='premature_finalization_rejected';
    $assert($prematureFinalizeDenied,'premature_finalization_rejected');
    $currentOperation='confirmed_capture';
    $currentTest='confirmed_catalog_capture';
    $operationSnapshot=['A'=>$snapshotPatientTables($db,$patientA),'B'=>$snapshotPatientTables($db,$patientB)];
    $confirmedKey=$testKey();
    $confirmed=$enabled->captureCatalog($scope,$questionId,['respuesta'=>'Si','dosis'=>'10 mg sintético','resultados'=>'Funciono','consumo'=>'No'],['respuesta','dosis','resultados','consumo'],(int)$draftRecord['version'],$confirmedKey,'INTERVIEW',1001);
    $operationSnapshot=null;
    $assert(($confirmed['code']??'')==='M07_CONFIRMED','confirmed_catalog_capture');
    $currentOperation='read_after_confirmation';
    $currentTest='canonical_readback';
    $afterRead=$enabled->read($patientA,$clinicA,'synthetic');
    $after=$recordByQuestion($afterRead['records'],$questionId);
    $assert(is_array($after)&&($after['fields']['respuesta']??'')==='Si'&&($after['efficacy']['code']??'')==='FUNCIONO','canonical_readback');
    $canonicalRawReadback=$readbackDb->prepare('SELECT id,m07_respuesta_state,m07_version FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente=? AND id_pregunta=? AND respuesta=? ORDER BY id DESC LIMIT 1');
    $canonicalRawReadback->execute([$patientA,$questionId,'Si']);
    $canonicalRawRow=$canonicalRawReadback->fetch(PDO::FETCH_ASSOC);
    $assert(is_array($canonicalRawRow)&&(int)$canonicalRawRow['m07_version']>=(int)$confirmed['record']['version'],'canonical_independent_readback');
    $currentOperation='idempotency_replay';
    $currentTest='idempotency_replay';
    $replay=$enabled->captureCatalog($scope,$questionId,['respuesta'=>'Si','dosis'=>'10 mg sintético','resultados'=>'Funciono','consumo'=>'No'],['respuesta','dosis','resultados','consumo'],(int)$draftRecord['version'],$confirmedKey,'INTERVIEW',1001);$assert(($replay['code']??'')==='M07_IDEMPOTENT_REPLAY','idempotency_replay');
    try{$enabled->captureCatalog($scope,$questionId,['respuesta'=>'No'],['respuesta'],(int)$draftRecord['version'],$confirmedKey,'INTERVIEW',1001);$idempotencyConflict=false;}catch(App\Services\M07CanonicalException $e){$idempotencyConflict=$e->codeName==='M07_IDEMPOTENCY_CONFLICT';}
    $currentTest='idempotency_payload_conflict';
    $assert($idempotencyConflict,'idempotency_payload_conflict');
    $currentOperation='stale_version_capture';
    $currentTest='optimistic_version_conflict';
    try{$enabled->captureCatalog($scope,$questionId,['respuesta'=>'No'],['respuesta'],(int)$draftRecord['version'],$testKey(),'INTERVIEW',1001);$stale=false;}catch(App\Services\M07CanonicalException $e){$stale=$e->codeName==='M07_VERSION_CONFLICT';}$assert($stale,'optimistic_version_conflict');
    $currentOperation='other_medication_capture';
    $currentTest='other_medication_entry_key_identity';
    $operationSnapshot=['A'=>$snapshotPatientTables($db,$patientA),'B'=>$snapshotPatientTables($db,$patientB)];
    $otherState=$enabled->read($patientA,$clinicA,'synthetic');
    $otherOneKey=$testKey();
    $otherOne=$enabled->createOtherMedication($scope,8,1,'M07 prueba A',(string)$otherState['version'],1001,$otherOneKey);
    $expectedOtherIds[(int)$otherOne['record']['id']]=true;
    $operationSnapshot=null;
    $currentOther=$enabled->read($patientA,$clinicA,'synthetic');
    $operationSnapshot=['A'=>$snapshotPatientTables($db,$patientA),'B'=>$snapshotPatientTables($db,$patientB)];
    $otherTwoKey=$testKey();
    $otherTwo=$enabled->createOtherMedication($scope,15,2,'M07 prueba B',(string)$currentOther['version'],1001,$otherTwoKey);
    $expectedOtherIds[(int)$otherTwo['record']['id']]=true;
    $operationSnapshot=null;
    $assert(($otherOne['record']['entry_key']??'')!==($otherTwo['record']['entry_key']??''),'other_medication_entry_key_identity');
    $currentOperation='other_medication_capture';
    $currentTest='other_question_link_and_partial_status';
    $otherRead=$enabled->read($patientA,$clinicA,'synthetic');
    $otherQuestion=$recordByQuestion($otherRead['records'],8);
    $assert(($otherQuestion['fields']['respuesta']??'')==='Si'&&($otherQuestion['status']??'')==='pending'&&(int)($otherOne['record']['question_id']??0)===8,'other_question_link_and_partial_status');
    $currentOperation='other_medication_capture';
    $currentTest='other_question_theme_guard';
    try{$enabled->createOtherMedication($scope,8,2,'M07 prueba no permitida',(string)$otherRead['version'],1001,$testKey());$wrongThemeDenied=false;}catch(App\Services\M07CanonicalException $e){$wrongThemeDenied=$e->codeName==='M07_SCOPE_DENIED';}
    $assert($wrongThemeDenied,'other_question_theme_guard');
    $currentOperation='other_medication_capture';
    $currentTest='other_active_entry_blocks_negative';
    try{$enabled->captureCatalog($scope,8,['respuesta'=>'No'],['respuesta'],0,$testKey(),'INTERVIEW',1001);$negativeBlocked=false;}catch(App\Services\M07CanonicalException $e){$negativeBlocked=$e->codeName==='M07_OTHER_ACTIVE_ENTRIES_CONFLICT';}
    $assert($negativeBlocked,'other_active_entry_blocks_negative');
    $currentOperation='other_medication_capture';
    $currentTest='other_medication_idempotent_replay';
    $replayedOther=$enabled->createOtherMedication($scope,8,1,'M07 prueba A',(string)$otherState['version'],1001,$otherOneKey);
    $assert(($replayedOther['code']??'')==='M07_IDEMPOTENT_REPLAY' && ($replayedOther['record']['id']??0)===($otherOne['record']['id']??-1),'other_medication_idempotent_replay');
    $currentTest='other_medication_idempotency_payload_conflict';
    $otherBeforeConflict=$independentOtherRead((int)$otherOne['record']['id'],8);
    $operationCountBeforeConflict=$operationCountForKey($db,$patientA,$otherOneKey);
    try{$enabled->createOtherMedication($scope,8,1,'M07 carga distinta',(string)$otherState['version'],1001,$otherOneKey);$otherPayloadConflict=false;}catch(App\Services\M07CanonicalException $e){$otherPayloadConflict=$e->codeName==='M07_IDEMPOTENCY_CONFLICT';}
    $otherAfterConflict=$independentOtherRead((int)$otherOne['record']['id'],8);
    $operationCountAfterConflict=$operationCountForKey($db,$patientA,$otherOneKey);
    $otherConflictNoMutation=is_array($otherBeforeConflict)&&is_array($otherAfterConflict)
        &&$hash([$otherBeforeConflict])===$hash([$otherAfterConflict])
        &&$operationCountBeforeConflict===1&&$operationCountAfterConflict===1;
    $assert($otherPayloadConflict&&$otherConflictNoMutation,'other_medication_idempotency_payload_conflict');
    $currentOperation='other_medication_capture';
    $currentTest='other_medication_full_capture_readback';
    $beforeOtherUpdate=$enabled->read($patientA,$clinicA,'synthetic');
    $operationSnapshot=['A'=>$snapshotPatientTables($db,$patientA),'B'=>$snapshotPatientTables($db,$patientB)];
    $doseUpdate=$enabled->updateOtherMedication($scope,8,1,(int)$otherOne['record']['id'],(int)$otherOne['record']['version'],['dosis'=>'10 mg ficticios'],(string)$beforeOtherUpdate['version'],1001,$testKey());
    $afterDose=$enabled->read($patientA,$clinicA,'synthetic');
    $consumptionUpdate=$enabled->updateOtherMedication($scope,8,1,(int)$otherOne['record']['id'],(int)$doseUpdate['record']['version'],['consumo'=>'No'],(string)$afterDose['version'],1001,$testKey());
    $afterConsumption=$enabled->read($patientA,$clinicA,'synthetic');
    $outcomeUpdate=$enabled->updateOtherMedication($scope,8,1,(int)$otherOne['record']['id'],(int)$consumptionUpdate['record']['version'],['resultados'=>'Funciono'],(string)$afterConsumption['version'],1001,$testKey());
    $assert(($outcomeUpdate['record']['status']??'')==='complete' && ($outcomeUpdate['record']['fields']['resultados']??'')==='Funciono','other_medication_full_capture_readback');
    $completedOtherEntries = [8 => $outcomeUpdate['record']];
    $completeOther = static function (int $questionId, int $themeId, array $record, string $result) use ($enabled, $scope, $clinicA, $patientA, $independentOtherRead, $testKey, $assert): array {
        $entryId=(int)$record['id'];
        $updates=[['dosis','10 mg sintéticos'],['consumo','No'],['resultados',$result]];
        if($result==='Tuve efectos adversos') $updates[]=['descripcion','Efecto adverso sintético de prueba'];
        foreach($updates as [$field,$value]){
            $state=$enabled->read($patientA,$clinicA,'synthetic');
            $record=$enabled->updateOtherMedication($scope,$questionId,$themeId,$entryId,(int)$record['version'],[$field=>$value],(string)$state['version'],1001,$testKey())['record'];
            $persisted=$independentOtherRead($entryId,$questionId);
            $column=match($field){'dosis'=>'dosis','consumo'=>'consumo','descripcion'=>'descripcion_efectos','resultados'=>'eficacia_codigo',default=>'id'};
            $expected=match($field){'resultados'=>$result==='Funciono'?'FUNCIONO':($result==='No funciono'?'NO_FUNCIONO':null),'descripcion'=>$value,default=>$value};
            $actual=$persisted[$column]??null;
            $matched=$result==='Tuve efectos adversos'&&$field==='resultados' ? (($persisted['tolerabilidad_codigo']??null)==='PRESENTO_EFECTOS') : $actual===$expected;
            $assert(is_array($persisted)&&$matched,'other_independent_step_readback_'.$questionId.'_'.$field);
        }
        return $record;
    };
    $currentTest='other_medication_full_capture_all_six';
    $completedOtherEntries[15]=$completeOther(15,2,$otherTwo['record'],'Funciono');
    foreach([22,30,37,46] as $questionId){
        $currentTest='other_medication_full_capture_all_six';
        $state=$enabled->read($patientA,$clinicA,'synthetic');
        $created=$enabled->createOtherMedication($scope,$questionId,$questionThemes[$questionId],'M07 sintético '.$questionId,(string)$state['version'],1001,$testKey());
        $expectedOtherIds[(int)$created['record']['id']]=true;
        $createdRow=$independentOtherRead((int)$created['record']['id'],$questionId);
        $assert(is_array($createdRow)&&(int)$createdRow['id_tema']===$questionThemes[$questionId]&&$createdRow['nombre']==='M07 sintético '.$questionId,'other_independent_create_readback_'.$questionId);
        $result=$questionId===46?'Tuve efectos adversos':'Funciono';
        $completedOtherEntries[$questionId]=$completeOther($questionId,$questionThemes[$questionId],$created['record'],$result);
    }
    $currentOperation='other_medication_six_question_readback';
    $currentTest='other_medication_full_capture_all_six';
    $allSixComplete=count($completedOtherEntries)===6;
    foreach($expectedQuestionThemes as $questionId=>$themeId){
        $record=$enabled->read($patientA,$clinicA,'synthetic')['records'];
        $questionRecord=$recordByQuestion($record,$questionId);
        $allSixComplete=$allSixComplete&&is_array($questionRecord)&&($questionRecord['fields']['respuesta']??'')==='Si'&&($questionRecord['status']??'')==='complete';
    }
    $assert($allSixComplete,'other_medication_full_capture_all_six');
    $currentTest='other_medication_independent_readback';
    foreach($expectedQuestionThemes as $questionId=>$themeId){
        $record=$completedOtherEntries[$questionId]??null;
        $persisted=is_array($record)?$independentOtherRead((int)$record['id'],$questionId):null;
        $allSixComplete=$allSixComplete&&is_array($persisted)&&(int)$persisted['id_tema']===$themeId&&$persisted['estado_registro']==='ACTIVO'&&(int)$persisted['version']>=2;
    }
    $assert($allSixComplete,'other_medication_independent_readback');
    $recoveredService=new App\Services\M07CanonicalService($readbackDb,new App\Services\M07CanonicalActivationGate(true));
    $recoveredState=$recoveredService->read($patientA,$clinicA,'synthetic');
    $recoveredSix=true;
    foreach($expectedQuestionThemes as $questionId=>$themeId){$recoveredRecord=$recordByQuestion($recoveredState['records'],$questionId);$recoveredSix=$recoveredSix&&is_array($recoveredRecord)&&($recoveredRecord['status']??'')==='complete'&&($recoveredRecord['fields']['respuesta']??'')==='Si';}
    $currentTest='other_medication_f5_recovery';
    $assert($recoveredSix,'other_medication_f5_recovery');
    $patientBState=$recoveredService->read($patientB,$clinicB,'synthetic');
    $patientBContainsARecord=false;
    foreach($patientBState['other_medications'] as $patientBEntry){if(in_array((int)$patientBEntry['id'],array_map(static fn(array $row):int=>(int)$row['id'],$completedOtherEntries),true))$patientBContainsARecord=true;}
    $currentTest='other_wrong_patient_denial';
    $patientBUnchanged=true;
    foreach($tables as $table){if($hash($tableRows($readbackDb,$table,$patientB))!==$hash($beforeB[$table]))$patientBUnchanged=false;}
    $patientBAllowed=$patientBState['other_medications']===[]&&!$patientBContainsARecord&&$patientBUnchanged;
    $operationSnapshot=['A'=>$snapshotPatientTables($db,$patientA),'B'=>$snapshotPatientTables($db,$patientB)];
    $wrongPatientKey=$testKey();
    try{$bScope=['patient_id'=>$patientB,'clinic_id'=>$clinicB,'role'=>'synthetic'];$bVersion=(string)$patientBState['version'];$enabled->updateOtherMedication($bScope,8,1,(int)$otherOne['record']['id'],(int)$otherOne['record']['version'],['dosis'=>'cambio no autorizado'],$bVersion,1002,$wrongPatientKey);$wrongPatientDenied=false;}catch(App\Services\M07CanonicalException $e){$wrongPatientDenied=$e->codeName==='M07_SCOPE_DENIED';}
    $sourceAfterDenied=$independentOtherRead((int)$otherOne['record']['id'],8);
    $patientBUnchangedAfterDenied=true;
    foreach($tables as $table){if($hash($tableRows($readbackDb,$table,$patientB))!==$hash($operationSnapshot['B'][$table]))$patientBUnchangedAfterDenied=false;}
    $sourceUnchangedAfterDenied=is_array($sourceAfterDenied)&&(int)$sourceAfterDenied['version']===(int)$outcomeUpdate['record']['version']&&$sourceAfterDenied['dosis']===$outcomeUpdate['record']['fields']['dosis'];
    $assert($patientBAllowed&&$wrongPatientDenied&&$patientBUnchangedAfterDenied&&$sourceUnchangedAfterDenied,'other_wrong_patient_denial');
    $operationSnapshot=null;
    $currentTest='other_medication_stale_version_conflict';
    $otherBeforeStale=$independentOtherRead((int)$otherOne['record']['id'],8);
    $operationsBeforeStale=(int)$db->query('SELECT COUNT(*) FROM pac_m07_operaciones WHERE id_paciente='.(int)$patientA)->fetchColumn();
    try{$enabled->updateOtherMedication($scope,8,1,(int)$otherOne['record']['id'],(int)$otherOne['record']['version'],['dosis'=>'dosis obsoleta'],(string)$enabled->read($patientA,$clinicA,'synthetic')['version'],1001,$testKey());$staleOtherDenied=false;}catch(App\Services\M07CanonicalException $e){$staleOtherDenied=$e->codeName==='M07_VERSION_CONFLICT';}
    $otherAfterStale=$independentOtherRead((int)$otherOne['record']['id'],8);
    $operationsAfterStale=(int)$db->query('SELECT COUNT(*) FROM pac_m07_operaciones WHERE id_paciente='.(int)$patientA)->fetchColumn();
    $staleNoMutation=is_array($otherBeforeStale)&&is_array($otherAfterStale)
        &&$hash([$otherBeforeStale])===$hash([$otherAfterStale])
        &&$operationsBeforeStale===$operationsAfterStale;
    $assert($staleOtherDenied&&$staleNoMutation,'other_medication_stale_version_conflict');
    $operationSnapshot=null;
    $currentOperation='other_medication_atomic_rollback';
    $currentTest='other_medication_atomic_rollback';
    $rollbackEntryId=(int)$otherOne['record']['id'];
    $rollbackBefore=$independentOtherRead($rollbackEntryId,8);
    if(!is_array($rollbackBefore)) throw new RuntimeException('Synthetic rollback target is unavailable.');
    $rollbackKey=$testKey();
    $rollbackDb=new M07IntegrationOneShotCommitFailurePdo(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$env['DB_HOST'],$env['DB_PORT'],$database),
        $env['DB_USERNAME'],
        $env['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]
    );
    $rollbackEffectiveDatabase=(string)$rollbackDb->query('SELECT DATABASE()')->fetchColumn();
    if($rollbackEffectiveDatabase!==$database) throw new RuntimeException('Rollback connection database guard failed.');
    $rollbackService=new App\Services\M07CanonicalService($rollbackDb,new App\Services\M07CanonicalActivationGate(true));
    $rollbackState=$rollbackService->read($patientA,$clinicA,'synthetic');
    try{
        $rollbackService->updateOtherMedication($scope,8,1,$rollbackEntryId,(int)$rollbackBefore['version'],['dosis'=>'FALLO DE TRANSACCIÓN SINTÉTICO'],(string)$rollbackState['version'],1001,$rollbackKey);
        $atomicRollbackObserved=false;
    }catch(App\Services\M07CanonicalException $e){
        $atomicRollbackObserved=$e->codeName==='M07_ATOMIC_ROLLBACK';
    }
    $rollbackAfter=$independentOtherRead($rollbackEntryId,8);
    $rollbackAuditAbsent=$operationCountForKey($readbackDb,$patientA,$rollbackKey)===0;
    $rollbackVerified=$rollbackEffectiveDatabase===$database
        &&$atomicRollbackObserved
        &&!$rollbackDb->inTransaction()
        &&is_array($rollbackBefore)&&is_array($rollbackAfter)
        &&$hash([$rollbackBefore])===$hash([$rollbackAfter])
        &&$rollbackAuditAbsent;
    $assert($rollbackVerified,'other_medication_atomic_rollback');
    $rollbackDb=null;
    $currentOperation='read_other_medications';
    $currentTest='multiple_other_medications';
    $assert(count($enabled->read($patientA,$clinicA,'synthetic')['other_medications'])>=2,'multiple_other_medications');
    $currentOperation='review_staleness_capture';
    $currentTest='review_staleness_protection';
    $reviewSnapshot=$enabled->read($patientA,$clinicA,'synthetic')['version'];
    $currentRead=$enabled->read($patientA,$clinicA,'synthetic');
    $current=$recordByQuestion($currentRead['records'],$questionId);
    $enabled->captureCatalog($scope,$questionId,['respuesta'=>'No'],['respuesta'],(int)($current['version']??0),$testKey(),'INTERVIEW',1001,true);
    $currentOperation='review_staleness_record';
    $currentTest='review_staleness_protection';
    $operationSnapshot=['A'=>$snapshotPatientTables($db,$patientA),'B'=>$snapshotPatientTables($db,$patientB)];
    try{$enabled->recordReview($scope,$reviewSnapshot,'ACCEPTED',2001);$reviewStale=false;}catch(App\Services\M07CanonicalException $e){$reviewStale=$e->codeName==='M07_REVIEW_STALE';}
    $operationSnapshot=null;
    $assert($reviewStale,'review_staleness_protection');
    $currentOperation='cross_clinic_read';
    $currentTest='cross_clinic_denial';
    $crossScope=['patient_id'=>$patientA,'clinic_id'=>$clinicB,'role'=>'synthetic'];try{$enabled->read($crossScope['patient_id'],$crossScope['clinic_id'],$crossScope['role']);$crossDenied=false;}catch(App\Services\M07CanonicalException $e){$crossDenied=$e->codeName==='M07_CLINIC_DENIED';}$assert($crossDenied,'cross_clinic_denial');
    $currentOperation='operation_audit_readback';
    $currentTest='append_only_operation_audit';
    $currentAuditRows=$tableRows($readbackDb,'pac_m07_operaciones',$patientA);
    $baselineAuditById=[];
    foreach($beforeA['pac_m07_operaciones'] as $auditRow)$baselineAuditById[(string)$auditRow['id']]=$auditRow;
    $currentAuditById=[];
    foreach($currentAuditRows as $auditRow)$currentAuditById[(string)$auditRow['id']]=$auditRow;
    $generatedKeySet=array_fill_keys($testIdempotencyKeys,true);
    $preexistingAuditPreserved=true;
    foreach($baselineAuditById as $id=>$auditRow){
        if(!isset($currentAuditById[$id])||$hash([$currentAuditById[$id]])!==$hash([$auditRow])){$preexistingAuditPreserved=false;break;}
    }
    $newAuditRows=[];$newAuditRowsTraceable=true;
    foreach($currentAuditById as $id=>$auditRow){
        if(isset($baselineAuditById[$id]))continue;
        $newAuditRows[]=$auditRow;
        $newAuditRowsTraceable=$newAuditRowsTraceable
            &&isset($generatedKeySet[(string)($auditRow['idempotency_key']??'')])
            &&(int)($auditRow['id_paciente']??0)===$patientA
            &&(int)($auditRow['id_clinica']??0)===$clinicA;
    }
    $assert($preexistingAuditPreserved&&$newAuditRows!==[]&&$newAuditRowsTraceable,'append_only_operation_audit');
    $report['preflight']='PASS';$report['status']='PASS_SYNTHETIC_CANONICAL_SERVICE';
    }
}catch(Throwable $e){
    $failure=$sanitizeFailure($e);
    $report['first_failed_operation']=$currentOperation;
    $report['first_failed_test']=$currentTest;
    $report['first_error_class']=$failure['class'];
    $report['first_error_original_class']=$failure['original_class'];
    $report['first_error_sqlstate']=$failure['sqlstate'];
    $report['first_error_driver_code']=$failure['driver_code'];
    $report['first_error_table']=$failure['table'];
    $report['first_error_constraint']=$failure['constraint'];
    $report['first_error_message_sanitized']=$failure['message'];
    $report['errors'][]='Prueba focal fallida.';
    $report['status']='FAIL';
    if ($operationSnapshot !== null) {
        try {
            $rollbackOk=true;
            foreach ($tables as $table) {
                if ($hash($tableRows($db,$table,$patientA))!==$hash($operationSnapshot['A'][$table]) || $hash($tableRows($db,$table,$patientB))!==$hash($operationSnapshot['B'][$table])) $rollbackOk=false;
            }
            $report['rollback_check_before_restoration']=$rollbackOk?'PASS':'FAIL';
        } catch (Throwable $rollbackException) {
            $report['rollback_check_before_restoration']='FAIL';
            $report['errors'][]='Comprobación de rollback no verificable antes de restaurar.';
        }
    } else {
        $report['rollback_check_before_restoration']='NOT_APPLICABLE';
    }
}
finally{
    $report['tests_not_reached']=array_values(array_diff($allTests,$report['tests_started']));
    if (!$clinicalSnapshotTaken) {
        $report['restoration']='NOT_APPLICABLE';
        $report['clinical_restoration']='NOT_APPLICABLE';
        $report['test_data_cleanup']='NOT_APPLICABLE_NO_CLINICAL_SNAPSHOT';
    } else {
        $report['restoration']='NOT_PERFORMED_APPEND_ONLY_AUDIT_PRESERVED';
        $report['clinical_restoration']='NOT_PERFORMED_APPEND_ONLY_AUDIT_PRESERVED';
        $report['test_data_cleanup']='BLOCKED_APPEND_ONLY_AUDIT_PRESERVED';
        try {
            $baselineByTable=['A'=>$beforeA,'B'=>$beforeB];
            $currentByTable=['A'=>[],'B'=>[]];
            foreach($tables as $table){
                $currentByTable['A'][$table]=$tableRows($db,$table,$patientA);
                $currentByTable['B'][$table]=$tableRows($db,$table,$patientB);
            }
            $retained=[];
            $unexpected=[];
            $preRunSnapshotRowsPreserved=true;
            $generatedKeysForRun=array_fill_keys($testIdempotencyKeys,true);
            foreach(['A'=>$patientA,'B'=>$patientB] as $label=>$patientId){
                foreach($tables as $table){
                    $old=[];foreach($baselineByTable[$label][$table] as $row)$old[(string)$row['id']]=$row;
                    $new=[];foreach($currentByTable[$label][$table] as $row)$new[(string)$row['id']]=$row;
                    foreach($new as $id=>$row){
                        $kind=!isset($old[$id])?'CREATED':($hash([$old[$id]])!==$hash([$row])?'MODIFIED':null);
                        if($kind===null)continue;
                        $item=['table'=>$table,'change'=>$kind,'id'=>(int)$row['id'],'patient_id'=>(int)($row['id_paciente']??$patientId)];
                        if(isset($row['id_pregunta']))$item['question_id']=(int)$row['id_pregunta'];
                        if($table==='pac_m07_operaciones'){
                            $item['action']=(string)($row['action']??'');
                            $item['target_type']=(string)($row['target_type']??'');
                            $item['target_id']=(int)($row['target_id']??0);
                        }
                        $retained[]=$item;
                        $isExpectedCreated=false;
                        if($kind==='CREATED'&&$label==='A'&&$patientId===$patientA){
                            if($table==='pac_respuestas_paciente_modulo_7'){
                                $isExpectedCreated=isset($expectedCatalogIds[(int)$id]);
                            }elseif($table==='pac_m07_medicamentos_no_catalogados'){
                                $isExpectedCreated=isset($expectedOtherIds[(int)$id])
                                    &&(int)($row['id_clinica']??0)===$clinicA
                                    &&in_array((int)($row['id_pregunta']??0),[8,15,22,30,37,46],true);
                            }elseif($table==='pac_m07_operaciones'){
                                $isExpectedCreated=isset($generatedKeysForRun[(string)($row['idempotency_key']??'')])
                                    &&(int)($row['id_clinica']??0)===$clinicA
                                    &&in_array((string)($row['action']??''),['DRAFT','CAPTURE','CREATE_OTHER','UPDATE_OTHER'],true);
                            }
                        }
                        if($kind!=='CREATED'||!$isExpectedCreated){
                            $unexpected[]=$item;
                        }
                    }
                    foreach($old as $id=>$row){
                        if(isset($new[$id]))continue;
                        $preRunSnapshotRowsPreserved=false;
                        $item=['table'=>$table,'change'=>'REMOVED_UNEXPECTEDLY','id'=>(int)$row['id'],'patient_id'=>(int)($row['id_paciente']??$patientId)];
                        if(isset($row['id_pregunta']))$item['question_id']=(int)$row['id_pregunta'];
                        $retained[]=$item;
                        $unexpected[]=$item;
                    }
                    foreach($old as $id=>$row){
                        if(!isset($new[$id])||$hash([$row])!==$hash([$new[$id]])){
                            $preRunSnapshotRowsPreserved=false;
                            if(isset($new[$id])){
                                $unexpected[]=['table'=>$table,'change'=>'MODIFIED_PREEXISTING','id'=>(int)$row['id'],'patient_id'=>(int)($row['id_paciente']??$patientId)];
                            }
                        }
                    }
                }
            }
            $report['retained_test_records']=$retained;
            $authorizedRetainedBefore=m07RowsContainExpectedIdsForPatient($baselineByTable['A'],$authorizedRetainedIds,$patientA);
            $authorizedRetainedAfter=m07RowsContainExpectedIdsForPatient($currentByTable['A'],$authorizedRetainedIds,$patientA);
            $report['original_baseline_preserved']=$preRunSnapshotRowsPreserved?'PASS_PRE_RUN_SNAPSHOT_ROWS_UNCHANGED':'FAIL_PRE_RUN_SNAPSHOT_ROWS_CHANGED';
            $report['authorized_synthetic_rows_retained']=$authorizedRetainedBefore&&$authorizedRetainedAfter&&$preRunSnapshotRowsPreserved?'PASS':'FAIL';
            $report['unexpected_data_differences']=$unexpected;
            $pendingBudgetOk = true;
            if ($pendingDmlOnly) {
                $newMedicationCount = count(array_filter($retained, static fn(array $row): bool => $row['table'] === 'pac_m07_medicamentos_no_catalogados' && $row['change'] === 'CREATED'));
                $newAuditCount = count(array_filter($retained, static fn(array $row): bool => $row['table'] === 'pac_m07_operaciones' && $row['change'] === 'CREATED'));
                $otherCreatedCount = count(array_filter($retained, static fn(array $row): bool => $row['change'] === 'CREATED' && !in_array($row['table'], ['pac_m07_medicamentos_no_catalogados', 'pac_m07_operaciones'], true)));
                $report['new_medication_rows'] = $newMedicationCount;
                $report['new_audit_rows'] = $newAuditCount;
                $report['other_new_rows'] = $otherCreatedCount;
                $pendingBudgetOk = $newMedicationCount === 1 && $newAuditCount === 1 && $otherCreatedCount === 0;
                $report['pending_dml_write_budget'] = $pendingBudgetOk ? 'PASS_ONE_MEDICATION_ONE_AUDIT_NO_OTHER_ROWS' : 'FAIL_WRITE_BUDGET_EXCEEDED';
            }
            $report['data_integrity']=$unexpected===[]&&$preRunSnapshotRowsPreserved&&$authorizedRetainedBefore&&$authorizedRetainedAfter&&$pendingBudgetOk
                ?'PASS_EXPECTED_SYNTHETIC_DELTA_ONLY':'FAIL_UNEXPECTED_DATA_DIFFERENCE';
        } catch (Throwable $exception) {
            $report['data_integrity']='FAIL_REMAINDER_INVENTORY_UNAVAILABLE';
            $report['retained_test_records']='INVENTORY_UNAVAILABLE';
            $report['original_baseline_preserved']='FAIL_NOT_VERIFIABLE';
            $report['authorized_synthetic_rows_retained']='FAIL_NOT_VERIFIABLE';
            $report['unexpected_data_differences']='INVENTORY_UNAVAILABLE';
            $report['errors'][]='No se pudo verificar el inventario final de cambios sintéticos.';
        }
    }
}
$functionalPass=in_array(($report['status']??''),['PASS_SYNTHETIC_CANONICAL_SERVICE','PASS_PENDING_SYNTHETIC_DML_CASES'],true);
$report['child_exit_code']=($functionalPass&&$report['data_integrity']==='PASS_EXPECTED_SYNTHETIC_DELTA_ONLY')?0:1;
$emitJson($report, (int)$report['child_exit_code']);
