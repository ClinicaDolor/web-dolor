<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_demo_20261010_final';
require $root . '/vendor/autoload.php';

$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $database),
    $env['DB_USERNAME'], $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) throw new RuntimeException('base sintética no activa');

$patient = $pdo->query('SELECT id,id_clinica FROM pc_paciente WHERE id = 9301')->fetch();
$other = $pdo->query('SELECT id,id_clinica FROM pc_paciente WHERE id = 9302')->fetch();
if (!$patient || !$other || (int)$patient['id_clinica'] === (int)$other['id_clinica']) throw new RuntimeException('fixture de pacientes inválido');

$types = $pdo->query('SELECT id,tipo FROM pac_preguntas_modulo_3 WHERE id_tema = 1 AND id IN (1,2,3,4,5) ORDER BY id')->fetchAll();
$typeMap = [];
foreach ($types as $row) $typeMap[(int)$row['id']] = (string)$row['tipo'];
if (($typeMap[1] ?? '') !== 'select' || count($typeMap) !== 5 || count(array_filter($typeMap, static fn(string $type): bool => $type === 'input')) !== 4) throw new RuntimeException('catálogo M03/P1-P5 inesperado');

$baselineStmt = $pdo->prepare('SELECT id,id_pregunta,id_paciente,respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente IN (9301,9302) ORDER BY id');
$baselineStmt->execute();
$baseline = $baselineStmt->fetchAll();
if ($baseline !== []) throw new RuntimeException('fixture no está limpio; no se sobrescriben respuestas existentes');

$snapshotPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'clinica-browser-m03-9301-20261012.json';
file_put_contents($snapshotPath, json_encode([
    'database' => $database,
    'patients' => [$patient, $other],
    'baseline_rows' => $baseline,
    'scope' => 'M03/P1-P5 synthetic browser acceptance',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX);

$pdo->beginTransaction();
try {
    $insert = $pdo->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta,id_paciente,respuesta) VALUES (?,?,?)');
    foreach ([1 => 'Si', 2 => 'SYNTHETIC-P2', 3 => 'SYNTHETIC-P3', 4 => 'SYNTHETIC-P4', 5 => ''] as $question => $value) $insert->execute([$question, 9301, $value]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

echo json_encode([
    'database' => $database,
    'patient' => 9301,
    'patient_clinic' => (int)$patient['id_clinica'],
    'other_patient' => 9302,
    'other_clinic' => (int)$other['id_clinica'],
    'question_types_validated' => true,
    'seeded_rows' => 5,
    'p5_empty' => true,
    'snapshot' => $snapshotPath,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
