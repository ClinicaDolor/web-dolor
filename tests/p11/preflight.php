<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$allowed = 'clinica_dolor_demo_20261010_final';
$requested = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $requested = substr($argument, 11);
}
if ($requested !== $allowed) {
    fwrite(STDERR, "FAIL P11 database allowlist\n");
    exit(2);
}
$env = [];
foreach (file($root . DIRECTORY_SEPARATOR . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
try {
    $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $allowed), $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== $allowed) throw new RuntimeException('database-mismatch');
    $required = ['pc_paciente_evaluacion_dolor' => ['id', 'id_paciente', 'dolor', 'tiempo_dolor', 'descripcion', 'incremento'], 'pac_historia_clinica_finalizar' => ['id', 'id_modulo', 'id_paciente'], 'pc_paciente' => ['id', 'id_clinica', 'sexo']];
    $check = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table');
    foreach ($required as $table => $columns) {
        $check->execute([':schema' => $allowed, ':table' => $table]);
        $available = array_map('strval', $check->fetchAll(PDO::FETCH_COLUMN));
        $missing = array_values(array_diff($columns, $available));
        if ($missing) throw new RuntimeException($table . ':missing-columns');
    }
    $patients = $pdo->query('SELECT id, id_clinica, sexo FROM pc_paciente WHERE id IN (9301, 9302) ORDER BY id')->fetchAll();
    if (count($patients) !== 2 || (int) $patients[0]['id_clinica'] === (int) $patients[1]['id_clinica']) throw new RuntimeException('synthetic-patients');
    $evaluationRows = $pdo->query('SELECT id, id_paciente, dolor, tiempo_dolor, descripcion, incremento FROM pc_paciente_evaluacion_dolor WHERE id_paciente IN (9301, 9302) ORDER BY id')->fetchAll();
    $rows = count($evaluationRows);
    $finished = (int) $pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 9 AND id_paciente IN (9301, 9302)')->fetchColumn();
    echo json_encode(['ok' => true, 'database' => $allowed, 'patients' => $patients, 'evaluation_rows' => $rows, 'evaluation_baseline' => $evaluationRows, 'p11_finalizations' => $finished, 'writes' => false], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL P11 synthetic preflight\n");
    exit(2);
}
