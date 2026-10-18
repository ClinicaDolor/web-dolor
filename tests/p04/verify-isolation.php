<?php

declare(strict_types=1);

/**
 * Preflight de solo lectura para las pruebas sintéticas de P04.
 *
 * La base sólo puede seleccionarse explícitamente y debe pertenecer a la
 * allowlist local. Para bd_clinica se exige además un par de IDs sintéticos
 * proporcionados por el responsable; nunca se descubren ni se adivinan aquí.
 */

$projectRoot = dirname(__DIR__, 2);
$allowedDatabases = [
    'bd_clinica',
    'clinica_dolor_demo_20261010_final',
];
$jsonOutput = in_array('--json', $argv, true);
$arguments = [];

foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--') || $argument === '--json') {
        continue;
    }

    [$key, $value] = array_pad(explode('=', $argument, 2), 2, '');
    $arguments[substr($key, 2)] = $value;
}

$requestedDatabase = $arguments['database'] ?? (getenv('P04_TEST_DATABASE') ?: '');
$patientA = $arguments['patient-a'] ?? (getenv('P04_TEST_PATIENT_A') ?: '');
$patientB = $arguments['patient-b'] ?? (getenv('P04_TEST_PATIENT_B') ?: '');
$isolationMode = $arguments['isolation-mode'] ?? (getenv('P04_TEST_ISOLATION_MODE') ?: '');
$ownerAttestation = $arguments['owner-attestation'] ?? (getenv('P04_SYNTHETIC_OWNER_ATTESTATION') ?: '');
$checks = [];

$fail = static function (string $message, array $checks = [], bool $json = false): never {
    $payload = [
        'ok' => false,
        'error' => $message,
        'checks' => $checks,
        'read_only' => true,
    ];

    if ($json) {
        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    } else {
        fwrite(STDERR, $message . PHP_EOL);
    }

    exit(2);
};

if ($requestedDatabase === '' || !in_array($requestedDatabase, $allowedDatabases, true)) {
    $fail('La base debe indicarse explícitamente y pertenecer a la allowlist local de P04.', $checks, $jsonOutput);
}

if (!in_array($isolationMode, ['intra-clinic', 'cross-clinic'], true)) {
    $fail('Indique --isolation-mode=intra-clinic o --isolation-mode=cross-clinic explícitamente.', $checks, $jsonOutput);
}

if ($isolationMode === 'intra-clinic') {
    if ($requestedDatabase !== 'bd_clinica') {
        $fail('El modo intra-clinic sólo puede ejecutarse contra bd_clinica.', $checks, $jsonOutput);
    }
    if ($ownerAttestation !== 'bd_clinica-ficticia-confirmada') {
        $fail('Falta la atestación explícita del propietario para el uso ficticio de bd_clinica.', $checks, $jsonOutput);
    }
}

if (!ctype_digit((string) $patientA) || !ctype_digit((string) $patientB) || (int) $patientA < 1 || (int) $patientB < 1 || $patientA === $patientB) {
    $fail('Se requieren dos IDs sintéticos distintos mediante --patient-a/--patient-b o P04_TEST_PATIENT_A/B.', $checks, $jsonOutput);
}

$patientA = (int) $patientA;
$patientB = (int) $patientB;
if ($patientA === $patientB) {
    $fail('Los IDs sintéticos deben corresponder a dos pacientes distintos.', $checks, $jsonOutput);
}
$checks['explicit_database'] = true;
$checks['explicit_patient_ids'] = true;
$checks['explicit_isolation_mode'] = true;
$checks['owner_attestation_scoped'] = $isolationMode !== 'intra-clinic' || $ownerAttestation === 'bd_clinica-ficticia-confirmada';

$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envPath)) {
    $fail('No existe .env para resolver únicamente la conexión local; no se continúa.', $checks, $jsonOutput);
}

$environment = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $value = trim($value);
    if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
        $value = substr($value, 1, -1);
    }
    $environment[trim($key)] = $value;
}

foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $requiredKey) {
    if (!array_key_exists($requiredKey, $environment)) {
        $fail('Falta una variable de conexión local requerida en .env.', $checks, $jsonOutput);
    }
}

$localHosts = ['127.0.0.1', 'localhost', '::1'];
$checks['local_host'] = in_array(strtolower($environment['DB_HOST']), $localHosts, true);
$checks['local_mysql_port'] = (string) $environment['DB_PORT'] === '3306';
if (!$checks['local_host'] || !$checks['local_mysql_port']) {
    $fail('La conexión no apunta al MySQL local esperado (host local y puerto 3306).', $checks, $jsonOutput);
}

try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $environment['DB_HOST'],
            $environment['DB_PORT'],
            $requestedDatabase
        ),
        $environment['DB_USERNAME'],
        $environment['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (Throwable $exception) {
    $fail('No fue posible conectar con la base local autorizada; no se ejecutan pruebas.', $checks, $jsonOutput);
}

$activeDatabase = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$checks['requested_database'] = $requestedDatabase;
$checks['active_database_matches_requested'] = $activeDatabase === $requestedDatabase;
if (!$checks['active_database_matches_requested']) {
    $fail('La conexión activa no coincide exactamente con la base solicitada.', $checks, $jsonOutput);
}

$tableExists = static function (PDO $connection, string $database, string $table): bool {
    $statement = $connection->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
    );
    $statement->execute([$database, $table]);
    return (int) $statement->fetchColumn() === 1;
};

$requiredTables = [
    'pc_paciente',
    'pc_paciente_acceso',
    'historia_clinica_modulos',
    'pc_antecedentes_familiares',
];
$missingTables = array_values(array_filter(
    $requiredTables,
    static fn (string $table): bool => !$tableExists($pdo, $activeDatabase, $table)
));
$checks['required_tables_present'] = $missingTables === [];
$checks['missing_tables'] = $missingTables;
if ($missingTables !== []) {
    $fail('Faltan tablas mínimas para las pruebas focalizadas de P04.', $checks, $jsonOutput);
}

$patientsStatement = $pdo->prepare(
    'SELECT id, id_clinica, nombres, apellido_paterno, apellido_materno
     FROM pc_paciente
     WHERE id IN (?, ?)
     ORDER BY id'
);
$patientsStatement->execute([$patientA, $patientB]);
$patients = $patientsStatement->fetchAll();

$syntheticNames = static function (array $patient): bool {
    $name = strtolower(
        (string) ($patient['nombres'] ?? '') . ' ' .
        (string) ($patient['apellido_paterno'] ?? '') . ' ' .
        (string) ($patient['apellido_materno'] ?? '')
    );
    return str_contains($name, 'sint') || str_contains($name, 'test') || str_contains($name, 'prueba');
};

$patientIds = array_map(static fn (array $patient): int => (int) $patient['id'], $patients);
$expectedPatientIds = [$patientA, $patientB];
sort($patientIds);
sort($expectedPatientIds);
$clinicIds = array_values(array_unique(array_map(
    static fn (array $patient): int => (int) $patient['id_clinica'],
    $patients
)));
$checks['synthetic_patients_present'] = count($patients) === 2 && $patientIds === $expectedPatientIds;
$checks['synthetic_patient_markers_present'] = count($patients) === 2 && count(array_filter($patients, $syntheticNames)) === 2;
$checks['synthetic_clinics_are_distinct'] = count($clinicIds) === 2;
$checks['same_clinic_id_1'] = count($patients) === 2 && count($clinicIds) === 1 && $clinicIds[0] === 1;

if (!$checks['synthetic_patients_present']) {
    $fail('Los dos IDs proporcionados no corresponden a expedientes existentes y distintos.', $checks, $jsonOutput);
}
if ($isolationMode === 'intra-clinic' && !$checks['same_clinic_id_1']) {
    $fail('El modo intra-clinic exige que ambos pacientes pertenezcan a id_clinica=1.', $checks, $jsonOutput);
}
if ($isolationMode === 'cross-clinic' && (!$checks['synthetic_patient_markers_present'] || !$checks['synthetic_clinics_are_distinct'])) {
    $fail('El modo cross-clinic exige marcadores sintéticos y clínicas distintas.', $checks, $jsonOutput);
}

$accessStatement = $pdo->prepare(
    'SELECT paciente_id,
            COUNT(*) AS total,
            SUM(CASE WHEN estatus = 0 AND fecha_expiracion > NOW() THEN 1 ELSE 0 END) AS activos
     FROM pc_paciente_acceso
     WHERE paciente_id IN (?, ?)
     GROUP BY paciente_id'
);
$accessStatement->execute([$patientA, $patientB]);
$accessRows = $accessStatement->fetchAll();
$checks['synthetic_access_rows'] = count($accessRows) === 2 && count(array_filter(
    $accessRows,
    static fn (array $row): bool => (int) $row['total'] >= 1 && (int) $row['activos'] >= 1
)) === 2;
$distinctAccessStatement = $pdo->prepare(
    'SELECT COUNT(DISTINCT pin)
     FROM pc_paciente_acceso
     WHERE paciente_id IN (?, ?) AND estatus = 0 AND fecha_expiracion > NOW()'
);
$distinctAccessStatement->execute([$patientA, $patientB]);
$checks['independent_active_access_credentials'] = (int) $distinctAccessStatement->fetchColumn() >= 2;
if (!$checks['synthetic_access_rows'] || !$checks['independent_active_access_credentials']) {
    $fail('No se pudieron confirmar dos identidades de acceso activas e independientes.', $checks, $jsonOutput);
}

$payload = [
    'ok' => true,
    'database' => $activeDatabase,
    'isolation_mode' => $isolationMode,
    'patient_ids' => [$patientA, $patientB],
    'checks' => $checks,
    'read_only' => true,
    'message' => 'Entorno local y par sintético confirmados; no se ejecutaron escrituras.',
];

if ($jsonOutput) {
    fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
} else {
    fwrite(STDOUT, "P04 isolation PASS: {$activeDatabase}; patient IDs {$patientA}, {$patientB}\n");
}
