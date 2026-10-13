<?php

declare(strict_types=1);

/**
 * Imports only the verified P10 catalog into the disposable synthetic DB.
 * The archive SQL is parsed as data; it is never executed as a dump.
 */
$allowedDatabase = 'clinica_dolor_demo_20261010_final';
$root = dirname(__DIR__, 2);
$database = '';
$archive = '';
$json = in_array('--json', $argv, true);
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $database = substr($argument, 11);
    if (str_starts_with($argument, '--archive=')) $archive = substr($argument, 10);
}

$emit = static function (array $payload, bool $json): never {
    if ($json) {
        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    } else {
        fwrite(($payload['ok'] ?? false) ? STDOUT : STDERR, (($payload['ok'] ?? false) ? 'PASS' : 'FAIL') . ' P10 catalog import' . PHP_EOL);
    }
    exit(($payload['ok'] ?? false) ? 0 : 2);
};

if ($database !== $allowedDatabase) {
    $emit(['ok' => false, 'error' => 'Sólo se permite la base sintética allowlisted.'], $json);
}
if ($archive === '' || !is_file($archive)) {
    $emit(['ok' => false, 'error' => 'No se encontró el archivo ZIP autorizado.'], $json);
}
if (!class_exists(ZipArchive::class)) {
    $emit(['ok' => false, 'error' => 'La extensión ZIP no está disponible.'], $json);
}

$expected = [
    1 => 'Terapia Física',
    2 => 'Terapia Ocupacional',
    3 => 'Terapia en Alberca',
    4 => 'Ultrasonido',
    5 => 'TENS',
    6 => 'Quiropráctico',
    7 => 'Masajes',
    8 => 'Terapia Psicológica',
    9 => 'Biofeedback',
    10 => 'Puntos Gatillo',
];

try {
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true || $zip->numFiles !== 2) {
        throw new RuntimeException('El paquete no coincide con la estructura esperada.');
    }
    $sql = '';
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = basename((string) $zip->getNameIndex($index));
        if ($name === 'pac_tratamientos_dolor_modulo_8.sql') {
            $sql = (string) $zip->getFromIndex($index);
        }
    }
    $zip->close();
    if ($sql === '' || hash('sha256', $sql) !== '63b2a558c30d60a798f5cecfc6db5290f9e60085d27d44d9fe608429e989a950') {
        throw new RuntimeException('El SQL del catálogo no coincide con la fuente autorizada.');
    }
    if (!preg_match('/INSERT\s+INTO\s+`?pac_tratamientos_dolor_modulo_8`?\s*\(\s*`?id`?\s*,\s*`?procedimiento`?\s*\)\s*VALUES\s*(.+?);/is', $sql, $insertMatch)) {
        throw new RuntimeException('No se encontró el INSERT autorizado del catálogo.');
    }
    preg_match_all("/\\(\\s*(\\d+)\\s*,\\s*'((?:''|[^'])*)'\\s*\\)/u", $insertMatch[1], $rows, PREG_SET_ORDER);
    $catalog = [];
    foreach ($rows as $row) $catalog[(int) $row[1]] = str_replace("''", "'", $row[2]);
    if ($catalog !== $expected) throw new RuntimeException('El catálogo no coincide exactamente con los 10 registros autorizados.');

    $env = [];
    foreach (file($root . DIRECTORY_SEPARATOR . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) continue;
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
    $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $allowedDatabase), $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $active = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($active !== $allowedDatabase || $active === 'bd_clinica') throw new RuntimeException('La base activa no coincide con la allowlist.');

    $schemaChanged = false;
    $columns = array_map('strval', $pdo->query('SHOW COLUMNS FROM pac_tratamientos_dolor_modulo_8')->fetchAll(PDO::FETCH_COLUMN));
    $catalogCount = (int) $pdo->query('SELECT COUNT(*) FROM pac_tratamientos_dolor_modulo_8')->fetchColumn();
    if (!in_array('procedimiento', $columns, true) && in_array('tratamiento', $columns, true) && $catalogCount === 0) {
        $pdo->exec('ALTER TABLE pac_tratamientos_dolor_modulo_8 CHANGE tratamiento procedimiento TEXT NOT NULL');
        $schemaChanged = true;
        $columns = array_map('strval', $pdo->query('SHOW COLUMNS FROM pac_tratamientos_dolor_modulo_8')->fetchAll(PDO::FETCH_COLUMN));
    }
    if (!in_array('id', $columns, true) || !in_array('procedimiento', $columns, true)) {
        throw new RuntimeException('La tabla existente no es compatible y no se modificó.');
    }

    $responseColumns = array_map('strval', $pdo->query('SHOW COLUMNS FROM pac_respuestas_paciente_modulo_8')->fetchAll(PDO::FETCH_COLUMN));
    $commentsAdded = false;
    if (!in_array('comentarios', $responseColumns, true)) {
        $pdo->exec('ALTER TABLE pac_respuestas_paciente_modulo_8 ADD COLUMN comentarios TEXT NULL AFTER descripcion');
        $commentsAdded = true;
    }

    $existing = [];
    foreach ($pdo->query('SELECT id, procedimiento FROM pac_tratamientos_dolor_modulo_8 ORDER BY id')->fetchAll() as $row) $existing[(int) $row['id']] = (string) $row['procedimiento'];
    if ($existing !== [] && $existing !== $expected) throw new RuntimeException('Ya existen datos de catálogo diferentes; se detuvo sin sobrescribir.');
    $inserted = false;
    if ($existing === []) {
        $pdo->beginTransaction();
        $insert = $pdo->prepare('INSERT INTO pac_tratamientos_dolor_modulo_8 (id, procedimiento) VALUES (:id, :procedimiento)');
        foreach ($expected as $id => $procedure) $insert->execute([':id' => $id, ':procedimiento' => $procedure]);
        $pdo->commit();
        $inserted = true;
    }
    $final = [];
    foreach ($pdo->query('SELECT id, procedimiento FROM pac_tratamientos_dolor_modulo_8 ORDER BY id')->fetchAll() as $row) $final[(int) $row['id']] = (string) $row['procedimiento'];
    if ($final !== $expected) throw new RuntimeException('La verificación final del catálogo falló.');
    $emit(['ok' => true, 'database' => $active, 'catalog_count' => count($final), 'catalog' => $final, 'schema_changed' => $schemaChanged, 'comments_column_added' => $commentsAdded, 'inserted' => $inserted, 'writes' => $inserted || $schemaChanged || $commentsAdded], $json);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $emit(['ok' => false, 'error' => 'No se pudo importar de forma segura el catálogo P10.'], $json);
}
