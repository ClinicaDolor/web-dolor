<?php

declare(strict_types=1);

/**
 * Preflight de P05. Sólo lee la base sintética autorizada.
 * No toma la base configurada por .env como autorización implícita.
 */

$projectRoot = dirname(__DIR__, 2);
$allowedDatabase = 'clinica_dolor_demo_20261010_final';
$requestedDatabase = getenv('P05_TEST_DATABASE') ?: '';

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) {
        $requestedDatabase = substr($argument, strlen('--database='));
    }
}

$json = in_array('--json', $argv, true);
$fail = static function (string $message, array $checks, bool $json): never {
    $payload = ['ok' => false, 'error' => $message, 'checks' => $checks];
    if ($json) {
        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    } else {
        fwrite(STDERR, $message . PHP_EOL);
    }
    exit(2);
};

$checks = [];
if ($requestedDatabase !== $allowedDatabase) {
    $fail('La base solicitada no pertenece a la allowlist sintética de P05.', $checks, $json);
}

$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envPath)) {
    $fail('No existe .env para resolver la conexión local.', $checks, $json);
}

$environment = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $environment[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}

foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
    if (!array_key_exists($key, $environment)) {
        $fail('Falta una variable de conexión local requerida.', $checks, $json);
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
    $fail('No fue posible conectar con la base sintética.', $checks, $json);
}

$activeDatabase = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$checks['requested_database'] = $requestedDatabase;
$checks['active_database'] = $activeDatabase;
$checks['active_database_is_allowlisted'] = $activeDatabase === $allowedDatabase;
if (!$checks['active_database_is_allowlisted']) {
    $fail('La conexión activa no coincide con la base sintética autorizada.', $checks, $json);
}

$requiredTables = [
    'pc_paciente',
    'pac_temas_modulo_3',
    'pac_preguntas_modulo_3',
    'pac_respuestas_paciente_modulo_3',
];
$tableStatement = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
);
$missingTables = [];
foreach ($requiredTables as $table) {
    $tableStatement->execute([$allowedDatabase, $table]);
    if ((int)$tableStatement->fetchColumn() !== 1) $missingTables[] = $table;
}
$checks['missing_tables'] = $missingTables;
if ($missingTables !== []) {
    $fail('Faltan tablas requeridas para P05.', $checks, $json);
}

$columnStatement = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?'
);
$columnStatement->execute([$allowedDatabase, 'pac_preguntas_modulo_3', 'pregunta']);
$checks['pregunta_column_present'] = (int)$columnStatement->fetchColumn() === 1;

$patients = $pdo->query(
    'SELECT id, id_clinica FROM pc_paciente WHERE id IN (9301, 9302) ORDER BY id'
)->fetchAll();
$checks['synthetic_patients'] = $patients;
$checks['patients_have_distinct_clinics'] = count($patients) === 2
    && (int)$patients[0]['id_clinica'] !== (int)$patients[1]['id_clinica'];

$checks['theme_count'] = (int)$pdo->query('SELECT COUNT(*) FROM pac_temas_modulo_3')->fetchColumn();
$checks['question_count'] = (int)$pdo->query('SELECT COUNT(*) FROM pac_preguntas_modulo_3')->fetchColumn();
$checks['response_count'] = (int)$pdo->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3')->fetchColumn();
$checks['catalog_ready_for_seven_theme_tests'] =
    $checks['pregunta_column_present'] && $checks['theme_count'] === 7 && $checks['question_count'] > 0;

$payload = [
    'ok' => true,
    'database' => $activeDatabase,
    'catalog_ready' => $checks['catalog_ready_for_seven_theme_tests'],
    'checks' => $checks,
];
if ($json) {
    fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
} else {
    fwrite(STDOUT, 'P05 preflight: PASS (aislamiento confirmado)' . PHP_EOL);
    fwrite(STDOUT, 'Catálogo de siete temas listo: ' . ($payload['catalog_ready'] ? 'SÍ' : 'NO') . PHP_EOL);
}
