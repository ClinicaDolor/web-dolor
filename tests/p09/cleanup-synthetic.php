<?php

declare(strict_types=1);

$database = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $database = substr($argument, strlen('--database='));
}
if ($database !== 'clinica_dolor_demo_20261010_final') {
    fwrite(STDERR, "Refused: database not allowlisted.\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
$environment = [];
foreach (file($root . DIRECTORY_SEPARATOR . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) continue;
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
    if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) throw new RuntimeException('Active database mismatch.');
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM pac_historia_clinica_finalizar WHERE id_modulo = 7 AND id_paciente IN (9301, 9302)')->execute();
    $pdo->prepare('DELETE FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente IN (9301, 9302)')->execute();
    $pdo->commit();
    $remaining = (int)$pdo->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente IN (9301, 9302)')->fetchColumn();
    $remaining += (int)$pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 7 AND id_paciente IN (9301, 9302)')->fetchColumn();
    if ($remaining !== 0) throw new RuntimeException('Synthetic P09 cleanup incomplete.');
    echo "PASS P09 synthetic responses/finalizations cleaned; catalog retained.\n";
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "FAIL P09 synthetic cleanup.\n");
    exit(1);
}
