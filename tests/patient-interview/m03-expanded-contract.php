<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
$dbName = 'clinica_dolor_browser_20261012';
$_ENV = array_merge($_ENV, $env, ['DB_DATABASE' => $dbName]);
$pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname=$dbName;charset=utf8mb4", $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
require $root . '/vendor/autoload.php';
$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$patient = 9302;
$table = 'pac_respuestas_paciente_modulo_3';
$readOriginal = $pdo->prepare("SELECT id,id_pregunta,id_paciente,respuesta FROM {$table} WHERE id_paciente=:id ORDER BY id");
$readOriginal->execute([':id' => $patient]);
$original = $readOriginal->fetchAll(PDO::FETCH_ASSOC);
try {
    $adapter = new App\Services\ConversationalM03Adapter($pdo);
    $state = $adapter->read($patient);
    $assert(count($state['questions']) === 25, 'M03 catalog count mismatch');
    $assert(($adapter->interpret($patient, 8, 'diario')['kind'] ?? '') === 'clarification', 'M03 dependency clarification failed');
    $parent = $adapter->interpret($patient, 6, 'sí');
    $assert(($parent['kind'] ?? '') === 'm03_proposal', 'M03 parent proposal failed');
    $assert((int)$adapter->capture($patient, $parent, $state['version'])['resultado'] === 200, 'M03 parent capture failed');
    $next = $adapter->read($patient);
    $child = $adapter->interpret($patient, 8, 'diario');
    $assert(($child['kind'] ?? '') === 'm03_proposal', 'M03 child proposal failed');
    $assert((int)$adapter->capture($patient, $child, $next['version'])['resultado'] === 200, 'M03 child capture failed');
    $after = $adapter->read($patient);
    $byId = [];
    foreach ($after['questions'] as $row) $byId[(int)$row['idPregunta']] = $row;
    $assert(($byId[6]['respuesta'] ?? '') === 'Si' && ($byId[8]['respuesta'] ?? '') === 'diario', 'M03 readback failed');
    $assert((int)$adapter->capture($patient, $child, $next['version'])['resultado'] === 409, 'M03 stale version failed');
    $pdo->prepare("DELETE FROM {$table} WHERE id_paciente=:patient AND id_pregunta=8")->execute([':patient' => $patient]);
    $safeProposal = $adapter->interpret($patient, 6, 'no');
    $safe = $adapter->capture($patient, $safeProposal, $adapter->read($patient)['version']);
    $assert((int)($safe['resultado'] ?? 0) === 200, 'M03 safe No failed: ' . json_encode($safe, JSON_UNESCAPED_UNICODE));
    $safeState = $adapter->read($patient);
    $safeById = [];
    foreach ($safeState['questions'] as $row) $safeById[(int)$row['idPregunta']] = $row;
    $assert(($safeById[6]['respuesta'] ?? '') === 'No' && ($safeById[8]['respuesta'] ?? '') === '', 'M03 safe No readback failed');
    $pdo->prepare("INSERT INTO {$table} (id_pregunta,id_paciente,respuesta) VALUES (8,:patient,'diario')")->execute([':patient' => $patient]);
    $conflictProposal = $adapter->interpret($patient, 6, 'no');
    $conflict = $adapter->capture($patient, $conflictProposal, $adapter->read($patient)['version']);
    $assert((int)($conflict['resultado'] ?? 0) === 409 && count($conflict['conflicts'] ?? []) === 1, 'M03 conflicting No was not blocked');
    $assert(($adapter->read($patient)['questions'][5]['respuesta'] ?? '') === 'No', 'M03 conflicting No changed parent');
} finally {
    $pdo->prepare("DELETE FROM {$table} WHERE id_paciente=:id")->execute([':id' => $patient]);
    $insert = $pdo->prepare("INSERT INTO {$table} (id,id_pregunta,id_paciente,respuesta) VALUES (:id,:question,:patient,:response)");
    foreach ($original as $row) $insert->execute([':id' => $row['id'], ':question' => $row['id_pregunta'], ':patient' => $row['id_paciente'], ':response' => $row['respuesta']]);
}
$count = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id_paciente=:id");
$count->execute([':id' => $patient]);
$assert((int)$count->fetchColumn() === count($original), 'M03 fixture restore failed');
echo json_encode(['database'=>$dbName,'m03_catalog_25'=>'PASS','m03_dependency_clarification'=>'PASS','m03_parent_and_child_capture'=>'PASS','m03_safe_no'=>'PASS','m03_conflicting_no_409'=>'PASS','m03_stale_409'=>'PASS','fixture_restored'=>true], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
