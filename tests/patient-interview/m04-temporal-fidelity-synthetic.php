<?php
declare(strict_types=1);

use App\Services\ConversationalM04Adapter;

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_browser_20261012';
$patient = 9302;
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
$pdo = new PDO(
    "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$database};charset=utf8mb4",
    $env['DB_USERNAME'],
    $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) throw new RuntimeException('SYNTHETIC_DATABASE_GUARD_FAILED');
$_ENV = array_merge($_ENV, $env, ['DB_DATABASE' => $database]);
require $root . '/vendor/autoload.php';
$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$rows = static function () use ($pdo, $patient): array {
    $stmt = $pdo->prepare('SELECT id,id_paciente,fecha,cirugia,observaciones FROM pc_antecedentes_quirurgicos WHERE id_paciente=:patient ORDER BY id');
    $stmt->execute([':patient' => $patient]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};
$original = $rows();
$adapter = new ConversationalM04Adapter($pdo);

try {
    $state = $adapter->read($patient);
    $assert(($state['write_enabled'] ?? false) === true, 'M04_SYNTHETIC_FIXTURE_NOT_EDITABLE');
    $year = $adapter->interpret('Me operaron de la rodilla en 2019');
    $month = $adapter->interpret('Me operaron de la rodilla en marzo de 2019');
    $exact = $adapter->interpret('Me operaron de la rodilla el 15 de marzo de 2019');
    $relative = $adapter->interpret('Me operaron de la rodilla hace aproximadamente tres años');
    $unknown = $adapter->interpret('No recuerdo la fecha');
    $invalid = $adapter->interpret('Me operaron de la rodilla el 31 de febrero de 2019');
    $assert(($year['kind'] ?? '') === 'm04_proposal' && ($year['date'] ?? null) === null && str_contains((string)($year['observations'] ?? ''), '2019'), 'M04_YEAR_PRECISION_FAILED');
    $assert(($month['kind'] ?? '') === 'm04_proposal' && ($month['date'] ?? null) === null && str_contains((string)($month['observations'] ?? ''), 'marzo de 2019'), 'M04_MONTH_PRECISION_FAILED');
    $assert(($exact['kind'] ?? '') === 'm04_proposal' && ($exact['date'] ?? null) === '2019-03-15', 'M04_EXACT_DATE_FAILED');
    $assert(($relative['kind'] ?? '') === 'm04_proposal' && ($relative['date'] ?? null) === null && str_contains((string)($relative['observations'] ?? ''), 'aproximadamente tres años'), 'M04_RELATIVE_PRECISION_FAILED');
    $assert(($unknown['kind'] ?? '') === 'clarification', 'M04_UNKNOWN_DATE_NOT_CLARIFIED');
    $assert(($invalid['kind'] ?? '') === 'clarification', 'M04_INVALID_DATE_NOT_REJECTED');

    foreach ([$year, $month, $exact, $relative] as $proposal) {
        $current = $adapter->read($patient);
        $saved = $adapter->capture($patient, $proposal, $current['version']);
        $assert((int)($saved['resultado'] ?? 0) === 200, 'M04_PRECISION_CAPTURE_FAILED');
    }
    $after = $rows();
    $newRows = array_values(array_filter($after, static fn(array $row): bool => !in_array($row, $original, true)));
    $assert(count($newRows) === 4, 'M04_EXPECTED_SYNTHETIC_ROWS_MISSING');
    $yearRows = array_values(array_filter($newRows, static fn(array $row): bool => str_contains((string)($row['observaciones'] ?? ''), 'Año referido')));
    $monthRows = array_values(array_filter($newRows, static fn(array $row): bool => str_contains((string)($row['observaciones'] ?? ''), 'Mes y año referidos')));
    $relativeRows = array_values(array_filter($newRows, static fn(array $row): bool => str_contains((string)($row['observaciones'] ?? ''), 'Tiempo referido')));
    $exactRows = array_values(array_filter($newRows, static fn(array $row): bool => (string)($row['fecha'] ?? '') === '2019-03-15'));
    $assert(count($yearRows) === 1 && ($yearRows[0]['fecha'] ?? null) === null, 'M04_YEAR_WAS_CONVERTED_TO_DATE');
    $assert(count($monthRows) === 1 && ($monthRows[0]['fecha'] ?? null) === null, 'M04_MONTH_WAS_CONVERTED_TO_DATE');
    $assert(count($relativeRows) === 1 && ($relativeRows[0]['fecha'] ?? null) === null, 'M04_RELATIVE_WAS_CONVERTED_TO_DATE');
    $assert(count($exactRows) === 1, 'M04_EXACT_DATE_NOT_PERSISTED');
    echo json_encode([
        'database' => $database,
        'm04_temporal_fidelity' => 'PASS',
        'year_only' => 'PRESERVED_AS_OBSERVATION',
        'month_year' => 'PRESERVED_AS_OBSERVATION',
        'exact_date' => 'PRESERVED',
        'relative_time' => 'PRESERVED_AS_OBSERVATION',
        'unknown_and_invalid' => 'CLARIFICATION_NO_WRITE',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM pc_antecedentes_quirurgicos WHERE id_paciente=:patient')->execute([':patient' => $patient]);
        $insert = $pdo->prepare('INSERT INTO pc_antecedentes_quirurgicos (id,id_paciente,fecha,cirugia,observaciones) VALUES (:id,:patient,:date,:surgery,:observations)');
        foreach ($original as $row) $insert->execute([':id' => $row['id'], ':patient' => $row['id_paciente'], ':date' => $row['fecha'], ':surgery' => $row['cirugia'], ':observations' => $row['observaciones']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}
