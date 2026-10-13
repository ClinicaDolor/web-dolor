<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$allowed = 'clinica_dolor_demo_20261010_final';
$requested = '';
$json = in_array('--json', $argv, true);
foreach ($argv as $argument) if (str_starts_with($argument, '--database=')) $requested = substr($argument, 11);
$emit = static function (array $payload, bool $json): never {
    if ($json) fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    else fwrite(($payload['ok'] ?? false) ? STDOUT : STDERR, (($payload['ok'] ?? false) ? 'PASS' : 'FAIL') . ' P08 synthetic prepare' . PHP_EOL);
    exit(($payload['ok'] ?? false) ? 0 : 2);
};
if ($requested !== $allowed) $emit(['ok' => false, 'error' => 'La base solicitada no está allowlisted para P08.'], $json);
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
    $required = ['id', 'id_paciente', 'medicamento', 'descripcion', 'tiempo_uso', 'dosis', 'medico'];
    $columns = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ?');
    $columns->execute([$allowed, 'pac_medicacion_actual_modulo_6']);
    $available = array_map('strval', $columns->fetchAll(PDO::FETCH_COLUMN));
    $missing = array_values(array_diff($required, $available));
    if ($missing) throw new RuntimeException('Faltan columnas de P08: ' . implode(', ', $missing));
    $patients = $pdo->query('SELECT id, id_clinica, nombres, apellido_paterno, apellido_materno FROM pc_paciente WHERE id IN (9301, 9302) ORDER BY id')->fetchAll();
    if (count($patients) !== 2 || (int)$patients[0]['id_clinica'] === (int)$patients[1]['id_clinica']) throw new RuntimeException('No se confirmaron los dos pacientes sintéticos en clínicas distintas.');
    $existingFinalization = (int)$pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 6 AND id_paciente IN (9301, 9302)')->fetchColumn();
    if ($existingFinalization !== 0) throw new RuntimeException('La línea base ya contiene finalizaciones P08; se detiene para no sobrescribirlas.');
    $marker = 'P08-SYNTH-20261011';
    $find = $pdo->prepare('SELECT id, id_paciente FROM pac_medicacion_actual_modulo_6 WHERE descripcion = :descripcion ORDER BY id');
    $find->execute([':descripcion' => $marker]);
    $rows = $find->fetchAll();
    if (count($rows) > 2) throw new RuntimeException('El marcador P08 ya tiene filas inesperadas.');
    $byPatient = [];
    foreach ($rows as $row) $byPatient[(int)$row['id_paciente']] = (int)$row['id'];
    $insert = $pdo->prepare('INSERT INTO pac_medicacion_actual_modulo_6 (id_paciente, medicamento, descripcion, tiempo_uso, dosis, medico) VALUES (:id_paciente, :medicamento, :descripcion, :tiempo_uso, :dosis, :medico)');
    foreach ([9301, 9302] as $patient) {
        if (!isset($byPatient[$patient])) {
            $insert->execute([':id_paciente' => $patient, ':medicamento' => 'Motivo sintético P08', ':descripcion' => $marker, ':tiempo_uso' => 'P08-6 meses', ':dosis' => 'P08-1 vez', ':medico' => 'Médico sintético P08']);
            $byPatient[$patient] = (int)$pdo->lastInsertId();
        }
    }
    $emit(['ok' => true, 'database' => $active, 'marker' => $marker, 'fixture_ids' => $byPatient, 'patients' => array_map(static fn($row) => ['id' => (int)$row['id'], 'id_clinica' => (int)$row['id_clinica']], $patients)], $json);
} catch (Throwable $exception) {
    $emit(['ok' => false, 'error' => 'No fue posible preparar el fixture sintético de P08.'], $json);
}
