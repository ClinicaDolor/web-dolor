<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$allowed = 'clinica_dolor_demo_20261010_final';
$requested = '';
$json = in_array('--json', $argv, true);
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $requested = substr($argument, 11);
}
$emit = static function (array $payload, bool $json): never {
    if ($json) fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    else fwrite(($payload['ok'] ?? false) ? STDOUT : STDERR, (($payload['ok'] ?? false) ? 'PASS' : 'FAIL') . ' P09 synthetic prepare' . PHP_EOL);
    exit(($payload['ok'] ?? false) ? 0 : 2);
};
if ($requested !== $allowed) $emit(['ok' => false, 'error' => 'La base solicitada no está allowlisted para P09.'], $json);

$env = [];
foreach (file($root . DIRECTORY_SEPARATOR . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}

try {
    $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $allowed), $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $active = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($active !== $allowed) throw new RuntimeException('La base activa no coincide con la allowlist.');
    $required = [
        'pac_temas_modulo_7' => ['id', 'tema'],
        'pac_preguntas_modulo_7' => ['id', 'id_tema', 'pregunta'],
        'pac_respuestas_paciente_modulo_7' => ['id', 'id_pregunta', 'id_paciente', 'respuesta', 'dosis', 'resultados', 'descripcion', 'consumo'],
    ];
    $columnQuery = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ?');
    $tables = [];
    foreach ($required as $table => $columns) {
        $columnQuery->execute([$allowed, $table]);
        $available = array_map('strval', $columnQuery->fetchAll(PDO::FETCH_COLUMN));
        $missing = array_values(array_diff($columns, $available));
        if ($missing) throw new RuntimeException('Faltan columnas de ' . $table . ': ' . implode(', ', $missing));
        $tables[$table] = count($available);
    }
    $patients = $pdo->query('SELECT id, id_clinica FROM pc_paciente WHERE id IN (9301, 9302) ORDER BY id')->fetchAll();
    if (count($patients) !== 2 || (int)$patients[0]['id_clinica'] === (int)$patients[1]['id_clinica']) throw new RuntimeException('No se confirmaron los pacientes sintéticos en clínicas distintas.');
    $themes = (int)$pdo->query('SELECT COUNT(*) FROM pac_temas_modulo_7')->fetchColumn();
    $questions = (int)$pdo->query('SELECT COUNT(*) FROM pac_preguntas_modulo_7')->fetchColumn();
    if ($themes !== 6 || $questions !== 46) throw new RuntimeException('El catálogo de P09 no coincide con los 6 temas y 46 preguntas autorizados.');
    $emit(['ok' => true, 'database' => $active, 'themes' => $themes, 'questions' => $questions, 'patients' => array_map(static fn($row) => ['id' => (int)$row['id'], 'id_clinica' => (int)$row['id_clinica']], $patients), 'writes' => false], $json);
} catch (Throwable $exception) {
    $emit(['ok' => false, 'error' => 'No fue posible confirmar el fixture sintético de P09.'], $json);
}
