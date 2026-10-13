<?php

declare(strict_types=1);

/**
 * Importa únicamente el catálogo P09 y las restricciones compatibles en la
 * base sintética allowlisted. No ejecuta los SQL del ZIP como dumps.
 */

$projectRoot = dirname(__DIR__, 2);
$allowedDatabase = 'clinica_dolor_demo_20261010_final';
$archivePath = '';
$json = in_array('--json', $argv, true);

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--archive=')) {
        $archivePath = substr($argument, strlen('--archive='));
    }
}

$emit = static function (array $payload, bool $asJson): never {
    if ($asJson) {
        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    } else {
        fwrite(($payload['ok'] ?? false) ? STDOUT : STDERR, (($payload['ok'] ?? false) ? 'PASS' : 'FAIL') . " P09 catalog import\n");
    }
    exit(($payload['ok'] ?? false) ? 0 : 2);
};

$parseEnv = static function (string $path): array {
    $environment = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $environment[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
    return $environment;
};

$columns = static function (PDO $pdo, string $database, string $table): array {
    $statement = $pdo->prepare(
        'SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position'
    );
    $statement->execute([$database, $table]);
    return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
};

$tableExists = static function (PDO $pdo, string $database, string $table): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
    );
    $statement->execute([$database, $table]);
    return (int)$statement->fetchColumn() === 1;
};

$foreignKeyExists = static function (PDO $pdo, string $database, string $table, string $column, string $referencedTable, string $referencedColumn): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.key_column_usage
         WHERE table_schema = ? AND table_name = ? AND column_name = ?
           AND referenced_table_name = ? AND referenced_column_name = ?'
    );
    $statement->execute([$database, $table, $column, $referencedTable, $referencedColumn]);
    return (int)$statement->fetchColumn() > 0;
};

$decodeSqlString = static fn(string $value): string => str_replace("''", "'", $value);

$parseRows = static function (string $sql, string $table, int $fieldCount, array $numericIndexes) use ($decodeSqlString): array {
    $pattern = '/INSERT\\s+INTO\\s+`?' . preg_quote($table, '/') . '`?\\s*\\([^;]+?\\)\\s*VALUES\\s*(.*?);/is';
    if (!preg_match($pattern, $sql, $statementMatch)) {
        throw new RuntimeException('No se encontró el INSERT autorizado de ' . $table . '.');
    }

    $values = $statementMatch[1];
    $rows = [];
    $length = strlen($values);
    $inString = false;
    $depth = 0;
    $start = null;

    for ($index = 0; $index < $length; $index++) {
        $character = $values[$index];
        if ($character === "'") {
            if ($inString && $index + 1 < $length && $values[$index + 1] === "'") {
                $index++;
                continue;
            }
            $inString = !$inString;
            continue;
        }
        if ($inString) {
            continue;
        }
        if ($character === '(') {
            if ($depth === 0) {
                $start = $index + 1;
            }
            $depth++;
        } elseif ($character === ')') {
            $depth--;
            if ($depth === 0 && $start !== null) {
                $tuple = substr($values, $start, $index - $start);
                $parts = [];
                $partStart = 0;
                $partInString = false;
                $partLength = strlen($tuple);
                for ($partIndex = 0; $partIndex < $partLength; $partIndex++) {
                    $partCharacter = $tuple[$partIndex];
                    if ($partCharacter === "'") {
                        if ($partInString && $partIndex + 1 < $partLength && $tuple[$partIndex + 1] === "'") {
                            $partIndex++;
                            continue;
                        }
                        $partInString = !$partInString;
                    } elseif ($partCharacter === ',' && !$partInString) {
                        $parts[] = trim(substr($tuple, $partStart, $partIndex - $partStart));
                        $partStart = $partIndex + 1;
                    }
                }
                $parts[] = trim(substr($tuple, $partStart));
                if (count($parts) !== $fieldCount) {
                    throw new RuntimeException('Estructura de fila no compatible en ' . $table . '.');
                }
                foreach ($parts as $partIndex => $part) {
                    if (in_array($partIndex, $numericIndexes, true)) {
                        if (!ctype_digit($part)) {
                            throw new RuntimeException('Identificador no numérico en ' . $table . '.');
                        }
                        $parts[$partIndex] = (int)$part;
                    } elseif (strlen($part) >= 2 && $part[0] === "'" && substr($part, -1) === "'") {
                        $parts[$partIndex] = $decodeSqlString(substr($part, 1, -1));
                    } else {
                        throw new RuntimeException('Texto no sanitizado en ' . $table . '.');
                    }
                }
                $rows[] = $parts;
                $start = null;
            }
        }
    }

    return $rows;
};

if ($archivePath === '' || !is_file($archivePath)) {
    $emit(['ok' => false, 'error' => 'No se encontró el ZIP P09 autorizado.'], $json);
}

try {
    $zip = new ZipArchive();
    if ($zip->open($archivePath) !== true) {
        throw new RuntimeException('No fue posible abrir el ZIP.');
    }

    $expectedFiles = [
        'P09_README_SEGURO.md',
        'pac_temas_modulo_7_CATALOGO_SANITIZADO.sql',
        'pac_preguntas_modulo_7_CATALOGO_SANITIZADO.sql',
        'pac_respuestas_paciente_modulo_7_SOLO_ESTRUCTURA.sql',
    ];
    $actualFiles = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entry = $zip->getNameIndex($index);
        if ($entry !== false && !str_ends_with($entry, '/')) {
            $actualFiles[] = $entry;
        }
    }
    sort($actualFiles);
    $expectedSorted = $expectedFiles;
    sort($expectedSorted);
    if ($actualFiles !== $expectedSorted) {
        throw new RuntimeException('El ZIP no coincide con el paquete P09 autorizado.');
    }

    $readme = (string)$zip->getFromName('P09_README_SEGURO.md');
    if (!str_contains($readme, $allowedDatabase) || !str_contains($readme, 'NO importar dumps originales')) {
        throw new RuntimeException('El README no confirma el uso seguro del paquete.');
    }
    $themeSql = (string)$zip->getFromName('pac_temas_modulo_7_CATALOGO_SANITIZADO.sql');
    $questionSql = (string)$zip->getFromName('pac_preguntas_modulo_7_CATALOGO_SANITIZADO.sql');
    $responseSql = (string)$zip->getFromName('pac_respuestas_paciente_modulo_7_SOLO_ESTRUCTURA.sql');
    $zip->close();

    if (preg_match('/INSERT\\s+INTO\\s+`?pac_respuestas_paciente_modulo_7`?/i', $responseSql)) {
        throw new RuntimeException('El archivo de respuestas no debe contener INSERT.');
    }
    foreach (['id', 'id_pregunta', 'id_paciente', 'respuesta', 'dosis', 'resultados', 'descripcion', 'consumo'] as $field) {
        if (!preg_match('/`' . preg_quote($field, '/') . '`/i', $responseSql)) {
            throw new RuntimeException('La estructura sanitizada no contiene el campo esperado.');
        }
    }

    $themes = $parseRows($themeSql, 'pac_temas_modulo_7', 2, [0]);
    $questions = $parseRows($questionSql, 'pac_preguntas_modulo_7', 3, [0, 1]);
    if (count($themes) !== 6 || count($questions) !== 46) {
        throw new RuntimeException('El catálogo no contiene exactamente 6 clases y 46 preguntas.');
    }

    $themeIds = [];
    foreach ($themes as [$id, $name]) {
        if (isset($themeIds[$id]) || trim((string)$name) === '') {
            throw new RuntimeException('Catálogo de clases duplicado o vacío.');
        }
        $themeIds[$id] = true;
    }
    $questionIds = [];
    foreach ($questions as [$id, $themeId, $question]) {
        if (isset($questionIds[$id]) || !isset($themeIds[$themeId]) || trim((string)$question) === '') {
            throw new RuntimeException('Relación de preguntas no válida.');
        }
        $questionIds[$id] = true;
    }

    $environment = $parseEnv($projectRoot . DIRECTORY_SEPARATOR . '.env');
    foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
        if (!array_key_exists($key, $environment)) {
            throw new RuntimeException('Falta configuración de conexión sintética.');
        }
    }
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $environment['DB_HOST'], $environment['DB_PORT'], $allowedDatabase),
        $environment['DB_USERNAME'],
        $environment['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $activeDatabase = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($activeDatabase !== $allowedDatabase || $activeDatabase === 'bd_clinica') {
        throw new RuntimeException('La conexión activa no coincide con la base sintética allowlisted.');
    }

    foreach (['pc_paciente', 'pac_temas_modulo_7', 'pac_preguntas_modulo_7', 'pac_respuestas_paciente_modulo_7'] as $table) {
        if (!$tableExists($pdo, $allowedDatabase, $table)) {
            throw new RuntimeException('Falta una tabla requerida del entorno sintético.');
        }
    }
    $patients = $pdo->query('SELECT id, id_clinica FROM pc_paciente WHERE id IN (9301, 9302) ORDER BY id')->fetchAll();
    if (count($patients) !== 2 || (int)$patients[0]['id_clinica'] === (int)$patients[1]['id_clinica']) {
        throw new RuntimeException('No se confirmaron los dos pacientes sintéticos en clínicas distintas.');
    }

    $themeColumns = $columns($pdo, $allowedDatabase, 'pac_temas_modulo_7');
    $questionColumns = $columns($pdo, $allowedDatabase, 'pac_preguntas_modulo_7');
    $responseColumns = $columns($pdo, $allowedDatabase, 'pac_respuestas_paciente_modulo_7');
    foreach (['id', 'tema'] as $field) if (!in_array($field, $themeColumns, true)) throw new RuntimeException('Estructura de clases incompatible.');
    foreach (['id', 'id_tema'] as $field) if (!in_array($field, $questionColumns, true)) throw new RuntimeException('Estructura de preguntas incompatible.');
    foreach (['id', 'id_pregunta', 'id_paciente', 'respuesta', 'dosis', 'resultados', 'descripcion', 'consumo'] as $field) if (!in_array($field, $responseColumns, true)) throw new RuntimeException('Estructura de respuestas incompatible.');

    $schemaChanges = [];
    $questionCount = (int)$pdo->query('SELECT COUNT(*) FROM pac_preguntas_modulo_7')->fetchColumn();
    if (!in_array('pregunta', $questionColumns, true)) {
        if ($questionCount !== 0) {
            throw new RuntimeException('No se puede agregar pregunta sobre filas existentes sin fuente segura.');
        }
        $pdo->exec('ALTER TABLE pac_preguntas_modulo_7 ADD COLUMN pregunta TEXT NOT NULL AFTER id_tema');
        $questionColumns = $columns($pdo, $allowedDatabase, 'pac_preguntas_modulo_7');
        $schemaChanges[] = 'Añadida pac_preguntas_modulo_7.pregunta';
    }

    $currentThemes = $pdo->query('SELECT id, tema FROM pac_temas_modulo_7 ORDER BY id')->fetchAll();
    $currentQuestions = $pdo->query('SELECT id, id_tema, pregunta FROM pac_preguntas_modulo_7 ORDER BY id')->fetchAll();
    $expectedThemes = array_map(static fn(array $row): array => ['id' => (int)$row[0], 'tema' => (string)$row[1]], $themes);
    $expectedQuestions = array_map(static fn(array $row): array => ['id' => (int)$row[0], 'id_tema' => (int)$row[1], 'pregunta' => (string)$row[2]], $questions);
    $normalizeThemes = static fn(array $rows): array => array_map(static fn(array $row): array => ['id' => (int)$row['id'], 'tema' => (string)$row['tema']], $rows);
    $normalizeQuestions = static fn(array $rows): array => array_map(static fn(array $row): array => ['id' => (int)$row['id'], 'id_tema' => (int)$row['id_tema'], 'pregunta' => (string)$row['pregunta']], $rows);
    if ($currentThemes !== [] && $normalizeThemes($currentThemes) !== $expectedThemes) throw new RuntimeException('El catálogo de clases existente no coincide; no se sobrescribe.');
    if ($currentQuestions !== [] && $normalizeQuestions($currentQuestions) !== $expectedQuestions) throw new RuntimeException('El catálogo de preguntas existente no coincide; no se sobrescribe.');
    if ($currentThemes === [] && $currentQuestions !== []) throw new RuntimeException('Hay preguntas sin clases; no se corrige automáticamente.');

    $pdo->beginTransaction();
    try {
        if ($currentThemes === []) {
            $statement = $pdo->prepare('INSERT INTO pac_temas_modulo_7 (id, tema) VALUES (?, ?)');
            foreach ($themes as [$id, $name]) $statement->execute([(int)$id, (string)$name]);
        }
        if ($currentQuestions === []) {
            $statement = $pdo->prepare('INSERT INTO pac_preguntas_modulo_7 (id, id_tema, pregunta) VALUES (?, ?, ?)');
            foreach ($questions as [$id, $themeId, $question]) $statement->execute([(int)$id, (int)$themeId, (string)$question]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }

    $addForeignKey = static function (PDO $pdo, string $database, string $table, string $column, string $referencedTable, string $referencedColumn, string $constraint) use ($foreignKeyExists): bool {
        if ($foreignKeyExists($pdo, $database, $table, $column, $referencedTable, $referencedColumn)) return false;
        $pdo->exec("ALTER TABLE `$table` ADD CONSTRAINT `$constraint` FOREIGN KEY (`$column`) REFERENCES `$referencedTable` (`$referencedColumn`) ON DELETE CASCADE ON UPDATE CASCADE");
        return true;
    };
    if ($addForeignKey($pdo, $allowedDatabase, 'pac_preguntas_modulo_7', 'id_tema', 'pac_temas_modulo_7', 'id', 'p09_questions_theme_fk')) $schemaChanges[] = 'Añadida FK preguntas→temas';
    if ($addForeignKey($pdo, $allowedDatabase, 'pac_respuestas_paciente_modulo_7', 'id_paciente', 'pc_paciente', 'id', 'p09_responses_patient_fk')) $schemaChanges[] = 'Añadida FK respuestas→paciente';
    if ($addForeignKey($pdo, $allowedDatabase, 'pac_respuestas_paciente_modulo_7', 'id_pregunta', 'pac_preguntas_modulo_7', 'id', 'p09_responses_question_fk')) $schemaChanges[] = 'Añadida FK respuestas→preguntas';

    $finalThemes = (int)$pdo->query('SELECT COUNT(*) FROM pac_temas_modulo_7')->fetchColumn();
    $finalQuestions = (int)$pdo->query('SELECT COUNT(*) FROM pac_preguntas_modulo_7')->fetchColumn();
    $responseCount = (int)$pdo->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7')->fetchColumn();
    if ($finalThemes !== 6 || $finalQuestions !== 46 || $responseCount !== 0) throw new RuntimeException('La verificación posterior al catálogo no coincide.');
    $emit(['ok' => true, 'database' => $activeDatabase, 'themes' => $finalThemes, 'questions' => $finalQuestions, 'responses_imported' => 0, 'schema_changes' => $schemaChanges, 'idempotent' => true], $json);
} catch (Throwable $exception) {
    $emit(['ok' => false, 'error' => 'No fue posible importar el catálogo P09 de forma segura.'], $json);
}

