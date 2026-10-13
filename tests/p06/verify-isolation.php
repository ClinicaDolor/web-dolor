<?php

declare(strict_types=1);

use App\Config\Database;
use App\Models\AntecedentesQuirurgicos;
use Dotenv\Dotenv;

$root = dirname(__DIR__, 2);
$allowed = 'clinica_dolor_demo_20261010_final';
$requested = '';
$json = in_array('--json', $argv, true);
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $requested = substr($argument, strlen('--database='));
}

$emit = static function (array $payload, bool $json): never {
    if ($json) fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    else fwrite(STDOUT, 'P06 synthetic verification: ' . (($payload['ok'] ?? false) ? 'PASS' : 'FAIL') . PHP_EOL);
    exit(($payload['ok'] ?? false) ? 0 : 2);
};

if ($requested !== $allowed) $emit(['ok' => false, 'error' => 'Base no allowlisted.'], $json);
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->load();
$_ENV['DB_DATABASE'] = $allowed;
$_SERVER['DB_DATABASE'] = $allowed;

try {
    $pdo = Database::getInstance();
    $active = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($active !== $allowed) $emit(['ok' => false, 'error' => 'La conexión activa no coincide.', 'database' => $active], $json);

    $table = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $table->execute([$allowed, 'pc_antecedentes_quirurgicos']);
    if ((int) $table->fetchColumn() !== 1) $emit(['ok' => false, 'error' => 'Falta pc_antecedentes_quirurgicos.'], $json);

    $patients = $pdo->prepare('SELECT id, id_clinica, nombres, apellido_paterno, apellido_materno FROM pc_paciente WHERE id IN (?, ?) ORDER BY id');
    $patients->execute([9301, 9302]);
    $rows = $patients->fetchAll(PDO::FETCH_ASSOC);
    $synthetic = count($rows) === 2 && count(array_unique(array_column($rows, 'id_clinica'))) === 2;
    foreach ($rows as $row) {
        $name = strtolower(trim(($row['nombres'] ?? '') . ' ' . ($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '')));
        $synthetic = $synthetic && str_contains($name, 'sint');
    }
    if (!$synthetic) $emit(['ok' => false, 'error' => 'No se confirmaron dos pacientes sintéticos en clínicas distintas.'], $json);

    $model = new AntecedentesQuirurgicos();
    $pdo->beginTransaction();
    $created = $model->agregarCirugiaPaciente(['idPaciente' => 9301]);
    if (($created['resultado'] ?? 0) !== 200) throw new RuntimeException('No se pudo crear el registro sintético dentro de la transacción.');
    $id = (int) $pdo->lastInsertId();
    $updated = $model->editarCirugiaPaciente(['idPaciente' => 9301, 'idCirugia' => $id, 'edicion' => 2, 'detalle' => 'Cirugía sintética P06']);
    if (($updated['resultado'] ?? 0) !== 200) throw new RuntimeException('La edición propia no fue aceptada.');
    $crossUpdate = $model->editarCirugiaPaciente(['idPaciente' => 9302, 'idCirugia' => $id, 'edicion' => 2, 'detalle' => 'Intento cruzado']);
    if (($crossUpdate['resultado'] ?? 0) !== 403) throw new RuntimeException('La edición cruzada no fue rechazada.');
    $crossDelete = $model->eliminarCirugiaPaciente(['idPaciente' => 9302, 'idQuirurgico' => $id]);
    if (($crossDelete['resultado'] ?? 0) !== 403) throw new RuntimeException('La eliminación cruzada no fue rechazada.');
    $ownDelete = $model->eliminarCirugiaPaciente(['idPaciente' => 9301, 'idQuirurgico' => $id]);
    if (($ownDelete['resultado'] ?? 0) !== 200) throw new RuntimeException('La eliminación propia no fue aceptada.');
    $pdo->rollBack();

    $html = $model->mostrarPreguntasM4(9301, 'Paciente');
    if (!str_contains($html, 'No hay cirugías registradas') && !str_contains($html, 'p06-record')) throw new RuntimeException('El render sintético de P06 no produjo un estado válido.');

    $emit(['ok' => true, 'database' => $active, 'crud_transaction' => 'PASS', 'cross_patient_write' => 'PASS', 'render' => 'PASS', 'persistent_write' => false], $json);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $emit(['ok' => false, 'error' => 'La verificación sintética falló.', 'detail' => $exception->getMessage()], $json);
}
