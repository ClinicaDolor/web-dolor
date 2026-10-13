<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$allowedDatabase = 'clinica_dolor_demo_20261010_final';
$database = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $database = substr($argument, strlen('--database='));
}
if ($database !== $allowedDatabase) {
    fwrite(STDERR, "P05 cleanup: FAIL (base no allowlisted)\n");
    exit(2);
}

$environment = [];
$envPath = $root . DIRECTORY_SEPARATOR . '.env';
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $environment[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $environment['DB_HOST'], $environment['DB_PORT'], $database),
        $environment['DB_USERNAME'],
        $environment['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $allowedDatabase) {
        throw new RuntimeException('La conexión activa no coincide con la base sintética.');
    }
    $pdo->beginTransaction();
    $counts = [];
    foreach ([
        'pac_respuestas_paciente_modulo_3' => 'DELETE FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente IN (9301, 9302)',
        'pac_historia_clinica_comentario' => 'DELETE FROM pac_historia_clinica_comentario WHERE id_paciente IN (9301, 9302) AND id_modulo = 3',
        'pac_historia_clinica_finalizar' => 'DELETE FROM pac_historia_clinica_finalizar WHERE id_paciente IN (9301, 9302) AND id_modulo = 3',
    ] as $table => $sql) {
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $counts[$table] = $statement->rowCount();
    }
    $pdo->commit();
    echo json_encode(['ok' => true, 'database' => $allowedDatabase, 'deleted' => $counts], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "P05 cleanup: FAIL\n" . $exception->getMessage() . "\n");
    exit(1);
}
