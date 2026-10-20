<?php

declare(strict_types=1);

/**
 * Read-only Windows/PHP preflight for the explicitly authorized local M07
 * environment. It is intentionally outside public/ and never performs DDL or
 * clinical writes.
 */

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable($root)->load();

// This preflight is deliberately pinned to the approved synthetic database.
// It borrows only the local connection credentials from .env; it never opens
// the application's normal database, even when .env points elsewhere.
$expectedDatabase = 'clinica_dolor_demo_20261010_final';
$_ENV['DB_DATABASE'] = $expectedDatabase;
$_SERVER['DB_DATABASE'] = $expectedDatabase;

$result = [
    'phase' => 'M07_WINDOWS_LOCAL_PREFLIGHT',
    'database_expected' => $expectedDatabase,
    'database_effective' => 'NOT_VERIFIED',
    'server' => 'NOT_VERIFIED',
    'port' => 'NOT_VERIFIED',
    'required_tables' => 'NOT_VERIFIED',
    'required_columns' => 'NOT_VERIFIED',
    'catalog_shape' => 'NOT_VERIFIED',
    'synthetic_patient_scope' => 'NOT_VERIFIED',
    'synthetic_doctor_scope' => 'NOT_VERIFIED',
    'canonical_read' => 'NOT_VERIFIED',
    'activation_opt_in' => 'CLOSED_BY_DEFAULT',
    'writes' => 'NO',
    'ddl' => 'NO',
    'status' => 'BLOCKED',
];

$fail = static function (string $code) use (&$result): never {
    $result['status'] = 'BLOCKED';
    $result['failure_code'] = preg_replace('/[^A-Z0-9_]/', '', strtoupper($code)) ?: 'PREFLIGHT_FAILED';
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
};

$host = strtolower(trim((string)($_ENV['DB_HOST'] ?? '')));
$port = (string)($_ENV['DB_PORT'] ?? '3306');
if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true) || $port !== '3306') {
    $fail('LOCAL_CONNECTION_CONFIGURATION_INVALID');
}
if (!extension_loaded('pdo_mysql')) {
    $fail('PDO_MYSQL_DRIVER_MISSING');
}
if ((string)($_ENV['DB_DATABASE'] ?? '') !== $expectedDatabase) {
    $fail('SYNTHETIC_DATABASE_OVERRIDE_FAILED');
}

try {
    // Use the same application connection factory as the web application.
    $pdo = App\Config\Database::getInstance();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $effective = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $result['database_effective'] = $effective;
    if ($effective !== $expectedDatabase) $fail('EFFECTIVE_DATABASE_MISMATCH');

    $server = $pdo->query('SELECT @@hostname AS server_name, @@port AS server_port')->fetch(PDO::FETCH_ASSOC) ?: [];
    $result['server'] = (string)($server['server_name'] ?? 'UNKNOWN');
    $result['port'] = (int)($server['server_port'] ?? 0);
    if ((int)$result['port'] !== 3306) $fail('MYSQL_PORT_MISMATCH');
} catch (Throwable $exception) {
    $pdoException = $exception;
    while ($pdoException !== null && !$pdoException instanceof PDOException) {
        $pdoException = $pdoException->getPrevious();
    }
    $result['failure_code'] = $pdoException instanceof PDOException
        ? 'PDO_CONNECTION_OR_READ_FAILURE'
        : 'APPLICATION_CONNECTION_FACTORY_FAILURE';
    if ($pdoException instanceof PDOException) {
        $errorInfo = is_array($pdoException->errorInfo ?? null) ? $pdoException->errorInfo : [];
        $sqlState = strtoupper((string)($errorInfo[0] ?? $pdoException->getCode()));
        if (preg_match('/^[A-Z0-9]{5}$/', $sqlState) === 1) {
            $result['failure_sqlstate'] = $sqlState;
        }
        $driverCode = (string)($errorInfo[1] ?? '');
        if (preg_match('/^[0-9]{1,6}$/', $driverCode) === 1) {
            $result['failure_driver_code'] = $driverCode;
        }
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
}

$tableNames = [
    'pc_paciente',
    'pac_temas_modulo_7',
    'pac_preguntas_modulo_7',
    'pac_respuestas_paciente_modulo_7',
    'pac_m07_medicamentos_no_catalogados',
    'pac_m07_operaciones',
    'pac_m07_revision_clinica',
    'pac_historia_clinica_finalizar',
    'clinicas',
    'doctores',
    'accesos_doctores',
];
$tableStatement = $pdo->prepare(
    'SELECT table_name FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name IN (' . implode(',', array_fill(0, count($tableNames), '?')) . ')'
);
$tableStatement->execute($tableNames);
$presentTables = array_fill_keys(array_map('strval', $tableStatement->fetchAll(PDO::FETCH_COLUMN)), true);
$missingTables = array_values(array_diff($tableNames, array_keys($presentTables)));
$result['missing_tables'] = $missingTables;
$result['required_tables'] = $missingTables === [] ? 'PASS' : 'FAIL';
if ($missingTables !== []) $fail('M07_REQUIRED_TABLE_MISSING');

$requiredColumns = [
    'm07_respuesta_state', 'm07_dosis_state', 'm07_resultados_state',
    'm07_consumo_state', 'm07_descripcion_state', 'm07_eficacia_codigo',
    'm07_eficacia_state', 'm07_tolerabilidad_codigo', 'm07_tolerabilidad_state',
    'm07_efectos_descripcion', 'm07_efectos_descripcion_state', 'm07_version',
    'm07_updated_at', 'm07_updated_by', 'm07_provenance',
];
$columnStatement = $pdo->prepare(
    'SELECT column_name FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = ? AND column_name IN (' . implode(',', array_fill(0, count($requiredColumns), '?')) . ')'
);
$columnStatement->execute(array_merge(['pac_respuestas_paciente_modulo_7'], $requiredColumns));
$presentColumns = array_map('strval', $columnStatement->fetchAll(PDO::FETCH_COLUMN));
$missingColumns = array_values(array_diff($requiredColumns, $presentColumns));
$result['missing_columns'] = $missingColumns;
$result['required_columns'] = $missingColumns === [] ? 'PASS' : 'FAIL';
if ($missingColumns !== []) $fail('M07_REQUIRED_CANONICAL_COLUMN_MISSING');

$catalogCounts = $pdo->query(
    'SELECT COUNT(DISTINCT t.id) AS themes, COUNT(q.id) AS questions
     FROM pac_temas_modulo_7 t
     INNER JOIN pac_preguntas_modulo_7 q ON q.id_tema = t.id'
)->fetch(PDO::FETCH_ASSOC) ?: [];
$result['catalog_shape'] = ((int)($catalogCounts['themes'] ?? 0) === 6 && (int)($catalogCounts['questions'] ?? 0) === 46)
    ? 'PASS'
    : 'FAIL';
if ($result['catalog_shape'] !== 'PASS') $fail('M07_CATALOG_SHAPE_MISMATCH');

$patientStatement = $pdo->prepare('SELECT id, id_clinica FROM pc_paciente WHERE id IN (?, ?) ORDER BY id');
$patientStatement->execute([9301, 9302]);
$patients = $patientStatement->fetchAll(PDO::FETCH_ASSOC);
$patientScope = [];
foreach ($patients as $patient) $patientScope[(int)$patient['id']] = (int)$patient['id_clinica'];
$result['synthetic_patient_scope'] = count($patients) === 2
    && ($patientScope[9301] ?? 0) === 9101
    && ($patientScope[9302] ?? 0) === 9102
    ? 'PASS'
    : 'FAIL';
if ($result['synthetic_patient_scope'] !== 'PASS') $fail('SYNTHETIC_PATIENT_SCOPE_INVALID');

$doctorStatement = $pdo->prepare(
    'SELECT d.id, d.clinica_id AS id_clinica, COUNT(ad.doctor_id) AS active_accesses
     FROM doctores d
     INNER JOIN clinicas c ON c.id = d.clinica_id
     LEFT JOIN accesos_doctores ad ON ad.doctor_id = d.id AND ad.activo = 1
     WHERE d.id IN (?, ?)
     GROUP BY d.id, d.clinica_id
     ORDER BY d.id'
);
$doctorStatement->execute([9201, 9202]);
$doctors = $doctorStatement->fetchAll(PDO::FETCH_ASSOC);
$doctorScope = [];
foreach ($doctors as $doctor) {
    $doctorScope[(int)$doctor['id']] = [
        'clinic' => (int)$doctor['id_clinica'],
        'active_accesses' => (int)$doctor['active_accesses'],
    ];
}
$result['synthetic_doctor_scope'] = count($doctors) === 2
    && ($doctorScope[9201]['clinic'] ?? 0) === 9101
    && ($doctorScope[9201]['active_accesses'] ?? 0) > 0
    && ($doctorScope[9202]['clinic'] ?? 0) === 9102
    && ($doctorScope[9202]['active_accesses'] ?? 0) > 0
    ? 'PASS'
    : 'FAIL';
if ($result['synthetic_doctor_scope'] !== 'PASS') $fail('SYNTHETIC_DOCTOR_SCOPE_INVALID');

try {
    $service = new App\Services\M07CanonicalService($pdo, new App\Services\M07CanonicalActivationGate(false));
    $state = $service->read(9301, 9101, 'patient');
    $recordCount = count($state['records'] ?? []);
    $result['canonical_read'] = $recordCount === 46 && ($state['finalization_enabled'] ?? true) === false
        ? 'PASS_READ_ONLY'
        : 'FAIL';
} catch (Throwable) {
    $result['canonical_read'] = 'FAIL';
}
if ($result['canonical_read'] !== 'PASS_READ_ONLY') $fail('M07_CANONICAL_READ_FAILED');

$gate = new App\Services\M07CanonicalActivationGate(false);
$result['activation_opt_in'] = $gate->isEnabled() ? 'UNEXPECTEDLY_OPEN' : 'CLOSED_BY_DEFAULT';
$result['status'] = 'PASS_READ_ONLY_PREFLIGHT';
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);
