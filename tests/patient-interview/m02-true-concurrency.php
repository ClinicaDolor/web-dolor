<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_browser_20261012';
$worker = in_array('--worker', $argv, true);
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
$env['DB_DATABASE'] = $database;
$_ENV = array_merge($_ENV, $env);
$pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$database};charset=utf8mb4", $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
require $root . '/vendor/autoload.php';

if ($worker) {
    $proposal = json_decode((string)($argv[2] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    $expected = (string)($argv[3] ?? '');
    $result = (new App\Services\ConversationalM02Adapter($pdo))->capture(9302, 'Cáncer', $proposal, $expected);
    echo json_encode(['status' => (int)($result['resultado'] ?? 0)], JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}

$baseline = $pdo->query("SELECT id,enfermedad,tipo,detalle,especificar FROM pc_antecedentes_familiares WHERE id_paciente=9302 AND enfermedad='Cáncer' ORDER BY id")->fetchAll();
$adapter = new App\Services\ConversationalM02Adapter($pdo);
$version = null;
foreach ($adapter->read(9302)['records'] as $record) if ($record['disease'] === 'Cáncer') $version = $record['version'];
if (!is_string($version)) throw new RuntimeException('No se pudo obtener la versión M02 sintética.');

$command = static function (string $relative, array $proposal, string $expected) use ($root): array {
    $script = $root . '/tests/patient-interview/m02-true-concurrency.php';
    return [PHP_BINARY, $script, '--worker', json_encode($proposal, JSON_THROW_ON_ERROR), $expected];
};
$processes = [];
foreach ([['relative'=>'madre'], ['relative'=>'padre']] as $proposal) {
    $pipes = [];
    $process = proc_open($command('', ['detail'=>'Si','relative'=>$proposal['relative'],'diabetes_type'=>null], $version), [1=>['pipe','w'], 2=>['pipe','w']], $pipes, $root);
    if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar worker concurrente.');
    $processes[] = [$process, $pipes];
}
$statuses = [];
$workerErrors = [];
foreach ($processes as [$process, $pipes]) {
    $stdout = stream_get_contents($pipes[1]);
    $workerErrors[] = trim(stream_get_contents($pipes[2]));
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($process);
    $statuses[] = (int)(json_decode($stdout, true)['status'] ?? 0);
}

$rows = $pdo->query("SELECT id,enfermedad,tipo,detalle,especificar FROM pc_antecedentes_familiares WHERE id_paciente=9302 AND enfermedad='Cáncer' ORDER BY id")->fetchAll();
$successful = count(array_filter($statuses, static fn(int $status): bool => $status === 200));
$stale = count(array_filter($statuses, static fn(int $status): bool => $status === 409));
$noDuplicates = count($rows) === 1;

// Restore only the tested synthetic disease to its exact pre-test state.
$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM pc_antecedentes_familiares WHERE id_paciente=9302 AND enfermedad='Cáncer'");
    $insert = $pdo->prepare('INSERT INTO pc_antecedentes_familiares (id,id_paciente,enfermedad,tipo,detalle,especificar) VALUES (:id,9302,:enfermedad,:tipo,:detalle,:especificar)');
    foreach ($baseline as $row) $insert->execute([':id'=>$row['id'],':enfermedad'=>$row['enfermedad'],':tipo'=>$row['tipo'],':detalle'=>$row['detalle'],':especificar'=>$row['especificar']]);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}
$restored = $pdo->query("SELECT id,enfermedad,tipo,detalle,especificar FROM pc_antecedentes_familiares WHERE id_paciente=9302 AND enfermedad='Cáncer' ORDER BY id")->fetchAll();

echo json_encode([
    'database'=>$database,
    'true_concurrent_requests'=>2,
    'statuses'=>$statuses,
    'worker_errors'=>$workerErrors,
    'one_success'=>$successful===1,
    'one_stale_409'=>$stale===1,
    'no_duplicates'=>$noDuplicates,
    'restored_exactly'=>$baseline===$restored,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
