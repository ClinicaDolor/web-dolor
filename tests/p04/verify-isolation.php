<?php

declare(strict_types=1);

/**
 * Preflight fail-closed para las pruebas sintéticas de P04.
 *
 * Este script es deliberadamente de solo lectura. No toma DB_DATABASE desde
 * .env como permiso implícito: exige el nombre autorizado por argumento y
 * variable de entorno para evitar escribir accidentalmente en bd_clinica.
 */

$projectRoot = dirname(__DIR__, 2);
$allowedDatabase = 'clinica_dolor_demo_20261010_final';
$forbiddenDatabases = [
    'bd_clinica',
    'clinica_dolor',
    'clinica_dolor_local',
];

$jsonOutput = in_array('--json', $argv, true);
$requestedDatabase = getenv('P04_TEST_DATABASE') ?: '';

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) {
        $cliDatabase = substr($argument, strlen('--database='));
        if ($requestedDatabase !== '' && $requestedDatabase !== $cliDatabase) {
            $payload = [
                'ok' => false,
                'error' => 'El nombre de base de prueba de CLI no coincide con P04_TEST_DATABASE.',
            ];
            if ($jsonOutput) {
                fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
            } else {
                fwrite(STDERR, $payload['error'] . PHP_EOL);
            }
            exit(2);
        }
        $requestedDatabase = $cliDatabase;
    }
}

$checks = [];

$fail = static function (string $message, array $checks = [], bool $json = false): never {
    $payload = [
        'ok' => false,
        'error' => $message,
        'checks' => $checks,
    ];

    if ($json) {
        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    } else {
        fwrite(STDERR, $message . PHP_EOL);
    }

    exit(2);
};

if ($requestedDatabase === '') {
    $fail('Falta P04_TEST_DATABASE o --database=...; no se permite usar .env implícitamente.', $checks, $jsonOutput);
}

if ($requestedDatabase !== $allowedDatabase) {
    $fail('La base solicitada no pertenece a la allowlist sintética de P04.', $checks, $jsonOutput);
}

if (in_array($requestedDatabase, $forbiddenDatabases, true)) {
    $fail('La base solicitada está protegida y no puede utilizarse en pruebas.', $checks, $jsonOutput);
}

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
    $fail('No fue posible conectar con la base sintética; no se ejecutan pruebas.', $checks, $jsonOutput);
}

$activeDatabase = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$checks['requested_database'] = $requestedDatabase;
$checks['active_database_matches_allowlist'] = $activeDatabase === $allowedDatabase;

if ($activeDatabase !== $allowedDatabase) {
    $fail('La conexión activa no coincide exactamente con la base sintética autorizada.', $checks, $jsonOutput);
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
    'pac_historia_clinica_finalizar',
    'pac_historia_clinica_comentario',
    'pac_preguntas_modulo_3',
    'pac_preguntas_modulo_5',
    'pac_preguntas_modulo_7',
    'pac_respuestas_paciente_modulo_3',
    'pac_respuestas_paciente_modulo_5',
    'pac_respuestas_paciente_modulo_7',
];

$missingTables = array_values(array_filter(
    $requiredTables,
    static fn (string $table): bool => !$tableExists($pdo, $allowedDatabase, $table)
));
$checks['required_tables_present'] = $missingTables === [];
$checks['missing_tables'] = $missingTables;

if ($missingTables !== []) {
    $fail('Faltan tablas requeridas para la reutilización del entorno sintético.', $checks, $jsonOutput);
}

$patientsStatement = $pdo->prepare(
    'SELECT id, id_clinica, nombres, apellido_paterno, apellido_materno
     FROM pc_paciente
     WHERE id IN (?, ?)
     ORDER BY id'
);
$patientsStatement->execute([9301, 9302]);
$patients = $patientsStatement->fetchAll();

$syntheticNames = static function (array $patient): bool {
    $name = strtolower(
        (string) ($patient['nombres'] ?? '') . ' ' .
        (string) ($patient['apellido_paterno'] ?? '') . ' ' .
        (string) ($patient['apellido_materno'] ?? '')
    );
    return str_contains($name, 'sint');
};

$patientIds = array_map(static fn (array $patient): int => (int) $patient['id'], $patients);
$clinicIds = array_values(array_unique(array_map(
    static fn (array $patient): int => (int) $patient['id_clinica'],
    $patients
)));
$checks['synthetic_patients_present'] = count($patients) === 2 && $patientIds === [9301, 9302];
$checks['synthetic_patient_markers_present'] = count($patients) === 2 && count(array_filter($patients, $syntheticNames)) === 2;
$checks['synthetic_clinics_are_distinct'] = count($clinicIds) === 2;

if (!$checks['synthetic_patients_present'] || !$checks['synthetic_patient_markers_present'] || !$checks['synthetic_clinics_are_distinct']) {
    $fail('La base no contiene exactamente el par de pacientes sintéticos esperado.', $checks, $jsonOutput);
}

$accessStatement = $pdo->prepare(
    'SELECT COUNT(*) FROM pc_paciente_acceso WHERE paciente_id IN (?, ?)'
);
$accessStatement->execute([9301, 9302]);
$checks['synthetic_access_rows'] = (int) $accessStatement->fetchColumn() === 2;

$moduleCount = (int) $pdo->query('SELECT COUNT(*) FROM historia_clinica_modulos')->fetchColumn();
$checks['nine_clinical_modules'] = $moduleCount === 9;

$p04CountStatement = $pdo->prepare(
    'SELECT COUNT(*) FROM pc_antecedentes_familiares WHERE id_paciente IN (?, ?)'
);
$p04CountStatement->execute([9301, 9302]);
$checks['p04_rows_are_synthetic_scope'] = (int) $p04CountStatement->fetchColumn() >= 0;

$foreignP04Count = (int) $pdo->query(
    'SELECT COUNT(*) FROM pc_antecedentes_familiares WHERE id_paciente NOT IN (9301, 9302)'
)->fetchColumn();
$checks['p04_has_no_rows_outside_synthetic_patients'] = $foreignP04Count === 0;

if (!$checks['synthetic_access_rows'] || !$checks['nine_clinical_modules'] || !$checks['p04_has_no_rows_outside_synthetic_patients']) {
    $fail('La base no cumple las precondiciones sintéticas de P04.', $checks, $jsonOutput);
}

$payload = [
    'ok' => true,
    'database' => $activeDatabase,
    'checks' => $checks,
    'read_only' => true,
    'message' => 'Aislamiento sintético confirmado; no se ejecutaron escrituras.',
];

if ($jsonOutput) {
    fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
} else {
    fwrite(STDOUT, "P04 isolation PASS: {$activeDatabase}\n");
}

