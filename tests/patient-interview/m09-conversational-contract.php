<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
$database = 'clinica_dolor_browser_20261012';
$_ENV = array_merge($_ENV, $env, ['DB_DATABASE' => $database]);
$pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$database};charset=utf8mb4", $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
require $root . '/vendor/autoload.php';
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$patient = 9302;
$table = 'pc_paciente_evaluacion_dolor';
$before = $pdo->prepare("SELECT id,id_paciente,dolor,tiempo_dolor,descripcion,incremento FROM {$table} WHERE id_paciente=:id ORDER BY id");
$before->execute([':id' => $patient]);
$original = $before->fetchAll(PDO::FETCH_ASSOC);

try {
    $adapter = new App\Services\ConversationalM09Adapter($pdo);
    $clarification = $adapter->interpret('dolor: Sí; tiempo: 3 meses');
    $assert(($clarification['kind'] ?? '') === 'clarification', 'M09 clarification failed');
    $proposal = $adapter->interpret('dolor: Sí; tiempo: 3 meses; descripción: inició gradualmente; incremento: igual');
    $assert(($proposal['kind'] ?? '') === 'm09_proposal', 'M09 proposal failed');
    $state = $adapter->read($patient);
    $saved = $adapter->capture($patient, $proposal, $state['version']);
    $assert((int)($saved['resultado'] ?? 0) === 200, 'M09 capture failed: ' . json_encode($saved, JSON_UNESCAPED_UNICODE));
    $after = $adapter->read($patient);
    $assert(($after['evaluation']['dolor'] ?? '') === 'Si', 'M09 pain flag not persisted');
    $assert(count($after['evaluation'] ? [$after['evaluation']] : []) === 1, 'M09 readback failed');
    $stale = $adapter->capture($patient, $proposal, $state['version']);
    $assert((int)($stale['resultado'] ?? 0) === 409, 'M09 stale version failed');
    $rows = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id_paciente=:id");
    $rows->execute([':id' => $patient]);
    $assert((int)$rows->fetchColumn() === 1, 'M09 duplicate rows detected');
} finally {
    $pdo->prepare("DELETE FROM {$table} WHERE id_paciente=:id")->execute([':id' => $patient]);
    $insert = $pdo->prepare("INSERT INTO {$table} (id,id_paciente,dolor,tiempo_dolor,descripcion,incremento) VALUES (:id,:patient,:dolor,:tiempo,:descripcion,:incremento)");
    foreach ($original as $row) $insert->execute([':id' => $row['id'], ':patient' => $row['id_paciente'], ':dolor' => $row['dolor'], ':tiempo' => $row['tiempo_dolor'], ':descripcion' => $row['descripcion'], ':incremento' => $row['incremento']]);
}
echo json_encode(['database' => $database, 'm09_interpretation' => 'PASS', 'm09_persistence' => 'PASS', 'm09_clarification' => 'PASS', 'm09_stale_409' => 'PASS', 'm09_no_duplicates' => 'PASS', 'fixture_restored' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
