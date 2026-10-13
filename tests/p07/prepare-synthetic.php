<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$allowedDatabase = 'clinica_dolor_demo_20261010_final';
$requestedDatabase = '';
$json = in_array('--json', $argv, true);
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $requestedDatabase = substr($argument, 11);
}
$emit = static function (array $payload, bool $json): never {
    if ($json) fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    else fwrite(STDOUT, (($payload['ok'] ?? false) ? 'PASS' : 'FAIL') . ' P07 synthetic prepare' . PHP_EOL);
    exit(($payload['ok'] ?? false) ? 0 : 2);
};
if ($requestedDatabase !== $allowedDatabase) $emit(['ok' => false, 'error' => 'Base P07 no allowlisted.'], $json);
$env = [];
foreach (file($projectRoot . DIRECTORY_SEPARATOR . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $allowedDatabase),
        $env['DB_USERNAME'], $env['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $allowedDatabase) throw new RuntimeException('Base activa incorrecta.');
    $catalog = require $projectRoot . '/tests/fixtures/p07-catalog.php';
    $hasPregunta = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '$allowedDatabase' AND table_name = 'pac_preguntas_modulo_5' AND column_name = 'pregunta'")->fetchColumn() === 1;
    if (!$hasPregunta && (int)$pdo->query('SELECT COUNT(*) FROM pac_preguntas_modulo_5')->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE pac_preguntas_modulo_5 ADD COLUMN pregunta TEXT NOT NULL AFTER id_tema');
    }
    $counts = [
        'themes' => (int)$pdo->query('SELECT COUNT(*) FROM pac_temas_modulo_5')->fetchColumn(),
        'questions' => (int)$pdo->query('SELECT COUNT(*) FROM pac_preguntas_modulo_5')->fetchColumn(),
        'diseases' => (int)$pdo->query('SELECT COUNT(*) FROM pac_enfermedades_modulo_5')->fetchColumn(),
    ];
    if (array_sum($counts) !== 0 && ($counts['themes'] !== 2 || $counts['questions'] !== 2 || $counts['diseases'] !== 2)) {
        throw new RuntimeException('El catálogo P07 contiene filas no compatibles; no se modificó.');
    }
    if (array_sum($counts) === 0) {
        $pdo->beginTransaction();
        $theme = $pdo->prepare('INSERT INTO pac_temas_modulo_5 (id, tema) VALUES (?, ?)');
        foreach ($catalog['themes'] as $row) $theme->execute([(int)$row['id'], $row['tema']]);
        $question = $pdo->prepare('INSERT INTO pac_preguntas_modulo_5 (id, id_tema, pregunta, tipo) VALUES (?, ?, ?, ?)');
        foreach ($catalog['questions'] as $row) $question->execute([(int)$row['id'], (int)$row['id_tema'], $row['pregunta'], $row['tipo']]);
        $disease = $pdo->prepare('INSERT INTO pac_enfermedades_modulo_5 (id, descripcion) VALUES (?, ?)');
        foreach ($catalog['diseases'] as $row) $disease->execute([(int)$row['id'], $row['descripcion']]);
        $pdo->commit();
    }
    $emit(['ok' => true, 'database' => $allowedDatabase, 'synthetic_catalog' => true, 'counts' => $counts], $json);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $emit(['ok' => false, 'error' => 'No fue posible preparar el catálogo P07 sintético.'], $json);
}
