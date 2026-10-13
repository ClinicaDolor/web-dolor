<?php

declare(strict_types=1);

/**
 * Prepara exclusivamente el esquema de la base sintética de P05.
 *
 * Por defecto sólo prepara el esquema. Con --seed-catalog carga el fixture
 * extraído de los SQL originales aprobados, sin ejecutar los volcados completos.
 * La reparación de esquema exige --repair-schema y sólo admite la base exacta
 * allowlisted.
 */

$projectRoot = dirname(__DIR__, 2);
$allowedDatabase = 'clinica_dolor_demo_20261010_final';
$requestedDatabase = '';
$repairSchema = false;
$seedCatalog = false;
$json = in_array('--json', $argv, true);

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) {
        $requestedDatabase = substr($argument, strlen('--database='));
    }
    if ($argument === '--repair-schema') {
        $repairSchema = true;
    }
    if ($argument === '--seed-catalog') {
        $seedCatalog = true;
    }
}

$emit = static function (array $payload, bool $json): never {
    if ($json) {
        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    } else {
        $status = ($payload['ok'] ?? false) ? 'PASS' : 'FAIL';
        fwrite(STDOUT, 'P05 synthetic prepare: ' . $status . PHP_EOL);
        foreach ($payload as $key => $value) {
            if ($key === 'ok') continue;
            if (is_scalar($value) || $value === null) {
                fwrite(STDOUT, $key . ': ' . var_export($value, true) . PHP_EOL);
            }
        }
    }
    exit(($payload['ok'] ?? false) ? 0 : 2);
};

if ($requestedDatabase !== $allowedDatabase) {
    $emit([
        'ok' => false,
        'error' => 'La base solicitada no pertenece a la allowlist sintética de P05.',
    ], $json);
}

if (!$repairSchema) {
    $emit([
        'ok' => false,
        'error' => 'La reparación exige --repair-schema explícito.',
    ], $json);
}

$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envPath)) {
    $emit(['ok' => false, 'error' => 'No existe .env para resolver la conexión local.'], $json);
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
        $emit(['ok' => false, 'error' => 'Falta una variable de conexión local requerida.'], $json);
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
    $emit(['ok' => false, 'error' => 'No fue posible conectar con la base sintética.'], $json);
}

$activeDatabase = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ($activeDatabase !== $allowedDatabase) {
    $emit([
        'ok' => false,
        'error' => 'La conexión activa no coincide con la base sintética autorizada.',
        'active_database' => $activeDatabase,
    ], $json);
}

$tableExists = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
);
$tableExists->execute([$allowedDatabase, 'pac_preguntas_modulo_3']);
if ((int)$tableExists->fetchColumn() !== 1) {
    $emit(['ok' => false, 'error' => 'Falta pac_preguntas_modulo_3 en la base sintética.'], $json);
}

$columnExists = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?'
);
$columnExists->execute([$allowedDatabase, 'pac_preguntas_modulo_3', 'pregunta']);
$hadPreguntaColumn = (int)$columnExists->fetchColumn() === 1;

$questionCount = (int)$pdo->query('SELECT COUNT(*) FROM pac_preguntas_modulo_3')->fetchColumn();
$schemaChanged = false;
if (!$hadPreguntaColumn) {
    if ($questionCount !== 0) {
        $emit([
            'ok' => false,
            'error' => 'No se agrega la columna porque la tabla ya contiene filas.',
            'question_count' => $questionCount,
        ], $json);
    }

    // El tipo y nulabilidad coinciden con tests/fixtures/security-fixtures.sql
    // y con el SELECT de AntecedentesNoPatologicosModel.
    $pdo->exec('ALTER TABLE pac_preguntas_modulo_3 ADD COLUMN pregunta TEXT NOT NULL AFTER id_tema');
    $schemaChanged = true;
}

$columnExists->execute([$allowedDatabase, 'pac_preguntas_modulo_3', 'pregunta']);
$hasPreguntaColumnAfter = (int)$columnExists->fetchColumn() === 1;
$themeCount = (int)$pdo->query('SELECT COUNT(*) FROM pac_temas_modulo_3')->fetchColumn();
$questionCountAfter = (int)$pdo->query('SELECT COUNT(*) FROM pac_preguntas_modulo_3')->fetchColumn();

$catalogSeeded = false;
if ($seedCatalog) {
    $catalogPath = $projectRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'p05-catalog.php';
    if (!is_file($catalogPath)) {
        $emit(['ok' => false, 'error' => 'No existe el fixture de catálogo P05.'], $json);
    }

    $catalog = require $catalogPath;
    $themes = is_array($catalog['themes'] ?? null) ? $catalog['themes'] : [];
    $questions = is_array($catalog['questions'] ?? null) ? $catalog['questions'] : [];
    if (count($themes) !== 7 || count($questions) !== 25) {
        $emit(['ok' => false, 'error' => 'El fixture P05 no contiene exactamente 7 temas y 25 preguntas.'], $json);
    }

    $existingThemes = $pdo->query('SELECT id, tema FROM pac_temas_modulo_3 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $existingQuestions = $pdo->query('SELECT id, id_tema, pregunta, tipo FROM pac_preguntas_modulo_3 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $expectedThemes = array_map(static fn(array $row): array => [
        'id' => (string)$row['id'],
        'tema' => (string)$row['tema'],
    ], $themes);
    $expectedQuestions = array_map(static fn(array $row): array => [
        'id' => (string)$row['id'],
        'id_tema' => (string)$row['id_tema'],
        'pregunta' => (string)$row['pregunta'],
        'tipo' => (string)$row['tipo'],
    ], $questions);
    $normalize = static fn(array $rows): array => array_map(static function (array $row): array {
        return array_map(static fn($value): string => (string)$value, $row);
    }, $rows);

    $themesMatch = $normalize($existingThemes) === $expectedThemes;
    $questionsMatch = $normalize($existingQuestions) === $expectedQuestions;
    $catalogEmpty = $existingThemes === [] && $existingQuestions === [];
    if (!$catalogEmpty && !($themesMatch && $questionsMatch)) {
        $emit(['ok' => false, 'error' => 'El catálogo sintético contiene filas en conflicto; no se modificó.'], $json);
    }

    if ($catalogEmpty) {
        try {
            $pdo->beginTransaction();
            $themeInsert = $pdo->prepare('INSERT INTO pac_temas_modulo_3 (id, tema) VALUES (:id, :tema)');
            foreach ($themes as $theme) {
                $themeInsert->execute([':id' => (int)$theme['id'], ':tema' => (string)$theme['tema']]);
            }
            $questionInsert = $pdo->prepare('INSERT INTO pac_preguntas_modulo_3 (id, id_tema, pregunta, tipo) VALUES (:id, :id_tema, :pregunta, :tipo)');
            foreach ($questions as $question) {
                $questionInsert->execute([
                    ':id' => (int)$question['id'],
                    ':id_tema' => (int)$question['id_tema'],
                    ':pregunta' => (string)$question['pregunta'],
                    ':tipo' => (string)$question['tipo'],
                ]);
            }
            $pdo->commit();
            $catalogSeeded = true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $emit(['ok' => false, 'error' => 'No fue posible cargar el catálogo sintético P05.'], $json);
        }
    }

    $themeCount = (int)$pdo->query('SELECT COUNT(*) FROM pac_temas_modulo_3')->fetchColumn();
    $questionCountAfter = (int)$pdo->query('SELECT COUNT(*) FROM pac_preguntas_modulo_3')->fetchColumn();
    if ($themeCount !== 7 || $questionCountAfter !== 25) {
        $emit(['ok' => false, 'error' => 'La verificación posterior del catálogo P05 no coincide con 7/25.'], $json);
    }
}

$emit([
    'ok' => $hasPreguntaColumnAfter,
    'database' => $activeDatabase,
    'schema_changed' => $schemaChanged,
    'pregunta_column_present_before' => $hadPreguntaColumn,
    'pregunta_column_present_after' => $hasPreguntaColumnAfter,
    'theme_count' => $themeCount,
    'question_count' => $questionCountAfter,
    'catalog_source' => $seedCatalog ? 'approved-sql-extracted-fixture' : 'not-requested',
    'catalog_seeded' => $catalogSeeded,
], $json);
