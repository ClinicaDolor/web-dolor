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
$pdo = new PDO(
    "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$dbName};charset=utf8mb4",
    $env['DB_USERNAME'],
    $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$_ENV = array_merge($_ENV, $env, ['DB_DATABASE' => $dbName]);
require $root . '/vendor/autoload.php';

$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$patient = 9302;
$disease = 'Enfermedades del corazón';
$query = $pdo->prepare(
    'SELECT id, id_paciente, enfermedad, tipo, detalle, especificar
     FROM pc_antecedentes_familiares
     WHERE id_paciente = :patient AND enfermedad = :disease ORDER BY id'
);
$query->execute([':patient' => $patient, ':disease' => $disease]);
$backup = $query->fetchAll(PDO::FETCH_ASSOC);
$assert(count($backup) === 1, 'Fast negative fixture must have one focal M02 row');
$adapter = new App\Services\ConversationalM02Adapter($pdo);
$before = null;
foreach ($adapter->read($patient)['records'] as $record) {
    if ($record['disease'] === $disease) $before = $record;
}
$assert(is_array($before), 'Synthetic M02 record missing');
$assert(($before['status'] ?? '') === 'documented', 'Fixture baseline was not documented as expected');
$assert(($adapter->read($patient)['write_enabled'] ?? false) === true, 'M02 synthetic fixture is not writable');

$prepared = false;
$result = [
    'database' => $dbName,
    'one_click_server_contract' => 'PASS',
    'readback' => 'PASS',
    'documented_repeat_rejected' => 'PASS_HTTP_409',
    'persisted_dependency_rejected' => 'PASS_HTTP_409',
    'unconfirmed_writes' => 'ZERO',
    'collateral_rows' => 'NOT_CHANGED',
];

$restore = static function () use ($pdo, $query, $backup, $patient, $disease): void {
    $pdo->beginTransaction();
    try {
        $delete = $pdo->prepare(
            'DELETE FROM pc_antecedentes_familiares
             WHERE id_paciente = :patient AND enfermedad = :disease'
        );
        $delete->execute([':patient' => $patient, ':disease' => $disease]);
        $insert = $pdo->prepare(
            'INSERT INTO pc_antecedentes_familiares
             (id, id_paciente, enfermedad, tipo, detalle, especificar)
             VALUES (:id, :patient, :disease, :tipo, :detail, :specific)'
        );
        foreach ($backup as $row) {
            $insert->execute([
                ':id' => $row['id'], ':patient' => $row['id_paciente'],
                ':disease' => $row['enfermedad'], ':tipo' => $row['tipo'],
                ':detail' => $row['detalle'], ':specific' => $row['especificar'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    $query->execute([':patient' => $patient, ':disease' => $disease]);
    if ($query->fetchAll(PDO::FETCH_ASSOC) !== $backup) {
        throw new RuntimeException('Fast negative fixture restore failed');
    }
};

try {
    $clear = $pdo->prepare(
        'UPDATE pc_antecedentes_familiares
         SET tipo = :type, detalle = :detail, especificar = :specific
         WHERE id_paciente = :patient AND enfermedad = :disease'
    );
    $clear->execute([
        ':type' => '', ':detail' => '', ':specific' => '',
        ':patient' => $patient, ':disease' => $disease,
    ]);
    $prepared = true;
    $pending = null;
    foreach ($adapter->read($patient)['records'] as $record) {
        if ($record['disease'] === $disease) $pending = $record;
    }
    $assert(($pending['status'] ?? '') === 'pending', 'Synthetic fast negative setup is not pending');

    $dependent = $pdo->prepare(
        'UPDATE pc_antecedentes_familiares
         SET tipo = :type, detalle = :detail, especificar = :specific
         WHERE id_paciente = :patient AND enfermedad = :disease'
    );
    $dependent->execute([
        ':type' => 'Tipo sintético', ':detail' => '', ':specific' => 'Familiar sintético',
        ':patient' => $patient, ':disease' => $disease,
    ]);
    $dependentState = null;
    foreach ($adapter->read($patient)['records'] as $record) {
        if ($record['disease'] === $disease) $dependentState = $record;
    }
    $dependentResult = $adapter->capture($patient, $disease, [
        'kind' => 'm02_read_only_proposal', 'disease' => $disease,
        'detail' => 'No', 'relative' => null, 'diabetes_type' => null, 'fast_no' => true,
    ], $dependentState['version']);
    $assert((int)($dependentResult['resultado'] ?? 0) === 409, 'Persisted dependent data was not rejected');

    $clear->execute([
        ':type' => '', ':detail' => '', ':specific' => '',
        ':patient' => $patient, ':disease' => $disease,
    ]);
    foreach ($adapter->read($patient)['records'] as $record) {
        if ($record['disease'] === $disease) $pending = $record;
    }

    $saved = $adapter->capture($patient, $disease, [
        'kind' => 'm02_read_only_proposal', 'disease' => $disease,
        'detail' => 'No', 'relative' => null, 'diabetes_type' => null, 'fast_no' => true,
    ], $pending['version']);
    $assert((int)($saved['resultado'] ?? 0) === 200, 'Fast negative persistence failed');

    $after = null;
    foreach ($adapter->read($patient)['records'] as $record) {
        if ($record['disease'] === $disease) $after = $record;
    }
    $assert(($after['detail'] ?? '') === 'No', 'Fast negative readback failed');
    $repeat = $adapter->capture($patient, $disease, [
        'kind' => 'm02_read_only_proposal', 'disease' => $disease,
        'detail' => 'No', 'relative' => null, 'diabetes_type' => null, 'fast_no' => true,
    ], $after['version']);
    $assert((int)($repeat['resultado'] ?? 0) === 409, 'Documented fast negative was not rejected');
} finally {
    if ($prepared) $restore();
}

$result['fixture_prepared_and_restored'] = true;
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
