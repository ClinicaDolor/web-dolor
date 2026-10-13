<?php

declare(strict_types=1);

$database = '';
foreach ($argv as $argument) if (str_starts_with($argument, '--database=')) $database = substr($argument, 11);
if ($database !== 'clinica_dolor_demo_20261010_final') exit("Refused: database not allowlisted.\n");
$root = dirname(__DIR__, 2);
$env = [];
foreach (file($root . DIRECTORY_SEPARATOR . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $database), $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) exit("Refused: active database mismatch.\n");
$pdo->exec('DELETE FROM pac_respuestas_paciente_modulo_5 WHERE id_paciente IN (9301, 9302)');
$pdo->exec('DELETE FROM pac_enfermedades_respuestas_modulo_5 WHERE id_paciente IN (9301, 9302)');
$pdo->exec('DELETE FROM pac_historia_clinica_finalizar WHERE id_modulo = 5 AND id_paciente IN (9301, 9302)');
$remaining = (int)$pdo->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_5 WHERE id_paciente IN (9301, 9302)')->fetchColumn();
$remaining += (int)$pdo->query('SELECT COUNT(*) FROM pac_enfermedades_respuestas_modulo_5 WHERE id_paciente IN (9301, 9302)')->fetchColumn();
$remaining += (int)$pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 5 AND id_paciente IN (9301, 9302)')->fetchColumn();
if ($remaining !== 0) exit("Refused: synthetic cleanup incomplete.\n");
echo "P07 synthetic responses cleaned; catalog retained.\n";
