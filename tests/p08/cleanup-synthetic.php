<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$allowed = 'clinica_dolor_demo_20261010_final';
$requested = '';
foreach ($argv as $argument) if (str_starts_with($argument, '--database=')) $requested = substr($argument, 11);
if ($requested !== $allowed) exit("Refused: P08 database not allowlisted.\n");
$env = [];
foreach (file($root . DIRECTORY_SEPARATOR . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $allowed), $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $allowed) exit("Refused: active database mismatch.\n");
$pdo->prepare('DELETE FROM pac_medicacion_actual_modulo_6 WHERE descripcion = :marker')->execute([':marker' => 'P08-SYNTH-20261011']);
$pdo->prepare('DELETE FROM pac_historia_clinica_finalizar WHERE id_modulo = 6 AND id_paciente IN (9301, 9302)')->execute();
$remaining = (int)$pdo->query("SELECT COUNT(*) FROM pac_medicacion_actual_modulo_6 WHERE descripcion = 'P08-SYNTH-20261011'")->fetchColumn();
if ($remaining !== 0) exit("Refused: P08 fixture cleanup incomplete.\n");
echo "P08 synthetic rows cleaned; no production database touched.\n";
