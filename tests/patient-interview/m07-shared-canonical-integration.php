<?php

declare(strict_types=1);

/**
 * Real MySQL synthetic integration for M07CanonicalService.
 *
 * The test is opt-in, requires the already-applied synthetic extension and
 * restores only the two explicit synthetic patient scopes in finally(). It
 * never opens bd_clinica and never calls the application routes.
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
$allow = array_key_exists('allow-synthetic-write', $options);
$connectionOnly = array_key_exists('connection-only', $options);
$outputEmitted = false;
$currentOperation = 'startup';
$currentTest = 'NOT_REACHED';
$clinicalSnapshotTaken = false;
$restorationAttempted = false;
$restoreClinicalSnapshot = null;
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
register_shutdown_function(static function () use (&$outputEmitted, &$report, &$currentOperation, &$currentTest, &$clinicalSnapshotTaken, &$restorationAttempted, &$restoreClinicalSnapshot, $emitJson): void {
    if ($outputEmitted) return;
    if ($clinicalSnapshotTaken && !$restorationAttempted && $restoreClinicalSnapshot instanceof Closure) {
        $restorationAttempted = true;
        try {
            $restoreClinicalSnapshot();
            $report['restoration'] = 'PASS_FOCAL';
            $report['clinical_restoration'] = 'PASS_FOCAL';
        } catch (Throwable) {
            $report['restoration'] = 'FAIL';
            $report['clinical_restoration'] = 'FAIL';
            $report['errors'][] = 'Restauración clínica focal fallida durante terminación anormal.';
        }
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
if($connectionOnly) {
    $report['preflight']='PASS_READ_ONLY_CHILD_CONNECTION';
    $report['status']='PASS_READ_ONLY_CHILD_CONNECTION';
    $report['tests']['child_database_identity']='PASS';
    $report['tests']['synthetic_patients_read']='PASS';
    $emitJson($report, 0);
}
$report['last_completed_operation']='synthetic_patient_read';
$tableRows=static function(PDO $db,string $table,int $patient):array{$s=$db->prepare("SELECT * FROM {$table} WHERE id_paciente=? ORDER BY id");$s->execute([$patient]);return $s->fetchAll(PDO::FETCH_ASSOC);};
$hash=static fn(array $rows):string=>hash('sha256',json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
$tables=['pac_respuestas_paciente_modulo_7','pac_m07_medicamentos_no_catalogados','pac_m07_operaciones','pac_m07_revision_clinica'];
try {
    $beforeA=[];$beforeB=[];foreach($tables as $table){$beforeA[$table]=$tableRows($db,$table,$patientA);$beforeB[$table]=$tableRows($db,$table,$patientB);}
    $clinicalSnapshotTaken = true;
    $report['clinical_snapshot'] = 'PASS';
} catch(Throwable $exception) {
    $report['connection_stage']='post_connection_sql';
    $report['connection_failure_category']='post_connection_sql';
    $report['connection_error_code']=(string)$exception->getCode();
    $blocked('Fallo SQL posterior a conexión PDO exitosa.');
}
$restoreClinicalSnapshot = static function () use ($db, $tables, $beforeA, $beforeB, $tableRows, $hash, $patientA, $patientB): void {
    $db->beginTransaction();
    try {
        foreach (array_reverse($tables) as $table) {
            $rows = $beforeA[$table];
            $db->prepare("DELETE FROM {$table} WHERE id_paciente=?")->execute([$patientA]);
            if ($rows !== []) {
                $columns = array_keys($rows[0]);
                $quoted = implode(',', array_map(static fn(string $column): string => '`' . $column . '`', $columns));
                $placeholders = implode(',', array_fill(0, count($columns), '?'));
                $insert = $db->prepare("INSERT INTO {$table} ({$quoted}) VALUES ({$placeholders})");
                foreach ($rows as $row) $insert->execute(array_values($row));
            }
        }
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        throw $exception;
    }
    foreach ($tables as $table) {
        if ($hash($tableRows($db, $table, $patientA)) !== $hash($beforeA[$table]) || $hash($tableRows($db, $table, $patientB)) !== $hash($beforeB[$table])) {
            throw new RuntimeException('Restauración focal no verificada.');
        }
    }
};
$scope=['patient_id'=>$patientA,'clinic_id'=>$clinicA,'role'=>'synthetic'];
$recordByQuestion=static function(array $records,int $questionId):?array{
    foreach($records as $record) if((int)($record['question_id']??0)===$questionId) return $record;
    return null;
};
$currentOperation='service_initialization';
$currentTest='NOT_REACHED';
$operationSnapshot = null;
$allTests=['unified_read_46_catalog_records','finalization_disabled','activation_gate_closed_by_default','draft_fixture_is_unanswered','explicit_unanswered_draft','draft_is_not_negative','confirmed_catalog_capture','canonical_readback','idempotency_replay','optimistic_version_conflict','other_medication_entry_key_identity','multiple_other_medications','review_staleness_protection','cross_clinic_denial','append_only_operation_audit'];
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
    $report['child_service_initialized']='PASS';
    $report['last_completed_operation']='service_initialization';
    $currentOperation='initial_service_read';
    $currentTest='unified_read_46_catalog_records';
    $initial=$enabled->read($patientA,$clinicA,'synthetic');
    $assert(count($initial['records'])===46,'unified_read_46_catalog_records');
    $assert($initial['finalization_enabled']===false,'finalization_disabled');
    $currentOperation='gate_closed_write_attempt';
    $currentTest='activation_gate_closed_by_default';
    try{$service->captureCatalog($scope,1,['respuesta'=>'Si'],['respuesta'],0,'00000000-0000-4000-8000-000000000001');$disabled=false;}catch(App\Services\M07CanonicalException $e){$disabled=$e->codeName==='M07_CANONICAL_DISABLED';}
    $assert($disabled,'activation_gate_closed_by_default');
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
    $operationSnapshot=['A'=>array_map(static fn(string $table):array=>$tableRows($db,$table,$patientA),$tables),'B'=>array_map(static fn(string $table):array=>$tableRows($db,$table,$patientB),$tables)];
    $draft=$enabled->saveUnansweredDraft($scope,$questionId,(int)$first['version'],'00000000-0000-4000-8000-000000000002',1001);$assert(($draft['code']??'')==='M07_DRAFT_SAVED','explicit_unanswered_draft');
    $operationSnapshot=null;
    $currentOperation='read_after_draft';
    $currentTest='draft_is_not_negative';
    $draftRead=$enabled->read($patientA,$clinicA,'synthetic');
    $draftRecord=$recordByQuestion($draftRead['records'],$questionId);
    $assert(is_array($draftRecord)&&($draftRecord['fields']['respuesta']??'')===''&&($draftRecord['status']??'')==='pending','draft_is_not_negative');
    $currentOperation='confirmed_capture';
    $currentTest='confirmed_catalog_capture';
    $operationSnapshot=['A'=>array_map(static fn(string $table):array=>$tableRows($db,$table,$patientA),$tables),'B'=>array_map(static fn(string $table):array=>$tableRows($db,$table,$patientB),$tables)];
    $confirmed=$enabled->captureCatalog($scope,$questionId,['respuesta'=>'Si','dosis'=>'10 mg sintético','resultados'=>'Funciono','consumo'=>'No'],['respuesta','dosis','resultados','consumo'],(int)$draftRecord['version'],'00000000-0000-4000-8000-000000000003','INTERVIEW',1001);
    $operationSnapshot=null;
    $assert(($confirmed['code']??'')==='M07_CONFIRMED','confirmed_catalog_capture');
    $currentOperation='read_after_confirmation';
    $currentTest='canonical_readback';
    $afterRead=$enabled->read($patientA,$clinicA,'synthetic');
    $after=$recordByQuestion($afterRead['records'],$questionId);
    $assert(is_array($after)&&($after['fields']['respuesta']??'')==='Si'&&($after['efficacy']['code']??'')==='FUNCIONO','canonical_readback');
    $currentOperation='idempotency_replay';
    $currentTest='idempotency_replay';
    $replay=$enabled->captureCatalog($scope,$questionId,['respuesta'=>'Si','dosis'=>'10 mg sintético','resultados'=>'Funciono','consumo'=>'No'],['respuesta','dosis','resultados','consumo'],(int)$draftRecord['version'],'00000000-0000-4000-8000-000000000003','INTERVIEW',1001);$assert(($replay['code']??'')==='M07_IDEMPOTENT_REPLAY','idempotency_replay');
    $currentOperation='stale_version_capture';
    $currentTest='optimistic_version_conflict';
    try{$enabled->captureCatalog($scope,$questionId,['respuesta'=>'No'],['respuesta'],(int)$draftRecord['version'],'00000000-0000-4000-8000-000000000004','INTERVIEW',1001);$stale=false;}catch(App\Services\M07CanonicalException $e){$stale=$e->codeName==='M07_VERSION_CONFLICT';}$assert($stale,'optimistic_version_conflict');
    $currentOperation='other_medication_capture';
    $currentTest='other_medication_entry_key_identity';
    $operationSnapshot=['A'=>array_map(static fn(string $table):array=>$tableRows($db,$table,$patientA),$tables),'B'=>array_map(static fn(string $table):array=>$tableRows($db,$table,$patientB),$tables)];
    $otherOne=$enabled->createOtherMedication($scope,1,'Medicamento sintético A',1001,'00000000-0000-4000-8000-000000000005');
    $operationSnapshot=null;
    $operationSnapshot=['A'=>array_map(static fn(string $table):array=>$tableRows($db,$table,$patientA),$tables),'B'=>array_map(static fn(string $table):array=>$tableRows($db,$table,$patientB),$tables)];
    $otherTwo=$enabled->createOtherMedication($scope,2,'Medicamento sintético A',1001,'00000000-0000-4000-8000-000000000006');
    $operationSnapshot=null;
    $assert(($otherOne['entry_key']??'')!==($otherTwo['entry_key']??''),'other_medication_entry_key_identity');
    $currentOperation='read_other_medications';
    $currentTest='multiple_other_medications';
    $assert(count($enabled->read($patientA,$clinicA,'synthetic')['other_medications'])>=2,'multiple_other_medications');
    $currentOperation='review_staleness_capture';
    $currentTest='review_staleness_protection';
    $reviewSnapshot=$enabled->read($patientA,$clinicA,'synthetic')['version'];
    $currentRead=$enabled->read($patientA,$clinicA,'synthetic');
    $current=$recordByQuestion($currentRead['records'],$questionId);
    $enabled->captureCatalog($scope,$questionId,['respuesta'=>'No'],['respuesta'],(int)($current['version']??0),'00000000-0000-4000-8000-000000000007','INTERVIEW',1001,true);
    $currentOperation='review_staleness_record';
    $currentTest='review_staleness_protection';
    $operationSnapshot=['A'=>array_map(static fn(string $table):array=>$tableRows($db,$table,$patientA),$tables),'B'=>array_map(static fn(string $table):array=>$tableRows($db,$table,$patientB),$tables)];
    try{$enabled->recordReview($scope,$reviewSnapshot,'ACCEPTED',2001);$reviewStale=false;}catch(App\Services\M07CanonicalException $e){$reviewStale=$e->codeName==='M07_REVIEW_STALE';}
    $operationSnapshot=null;
    $assert($reviewStale,'review_staleness_protection');
    $currentOperation='cross_clinic_read';
    $currentTest='cross_clinic_denial';
    $crossScope=['patient_id'=>$patientA,'clinic_id'=>$clinicB,'role'=>'synthetic'];try{$enabled->read($crossScope['patient_id'],$crossScope['clinic_id'],$crossScope['role']);$crossDenied=false;}catch(App\Services\M07CanonicalException $e){$crossDenied=$e->codeName==='M07_CLINIC_DENIED';}$assert($crossDenied,'cross_clinic_denial');
    $currentOperation='operation_audit_readback';
    $currentTest='append_only_operation_audit';
    $assert($hash($tableRows($db,'pac_m07_operaciones',$patientA))!==$hash($beforeA['pac_m07_operaciones']),'append_only_operation_audit');
    $report['preflight']='PASS';$report['status']='PASS_SYNTHETIC_CANONICAL_SERVICE';
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
    } else {
        $restorationAttempted=true;
        try {
            $restoreClinicalSnapshot();
            $report['restoration']='PASS_FOCAL';
            $report['clinical_restoration']='PASS_FOCAL';
        } catch (Throwable $exception) {
            $report['restoration']='FAIL';
            $report['clinical_restoration']='FAIL';
            $report['status']='FAIL';
            $report['errors'][]='Restauración focal fallida.';
            if($report['first_failed_operation']==='NOT_REACHED'){
                $report['first_failed_operation']='clinical_restoration';
                $report['first_error_class']='RestorationError';
                $report['first_error_sqlstate']=$exception instanceof PDOException?(string)$exception->getCode():'NONE';
                $report['first_error_message_sanitized']='Restauración focal no verificada.';
            }
        }
    }
}
$report['child_exit_code']=(($report['status']??'')==='PASS_SYNTHETIC_CANONICAL_SERVICE'&&$report['restoration']==='PASS_FOCAL')?0:1;
$emitJson($report, (int)$report['child_exit_code']);
