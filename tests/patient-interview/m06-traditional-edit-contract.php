<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Models\MedicacionActualModel;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec(
    'CREATE TABLE pac_medicacion_actual_modulo_6 (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        id_paciente INTEGER NOT NULL,
        medicamento TEXT NULL,
        descripcion TEXT NULL,
        tiempo_uso TEXT NULL,
        dosis TEXT NULL,
        medico TEXT NULL
    )'
);
$insert = $db->prepare(
    'INSERT INTO pac_medicacion_actual_modulo_6
     (id_paciente, medicamento, descripcion, tiempo_uso, dosis, medico)
     VALUES (:patient, :medication, :reason, :duration, :dose, :doctor)'
);
$insert->execute([
    ':patient' => 9301,
    ':medication' => 'Inicial',
    ':reason' => 'Motivo inicial',
    ':duration' => 'Una semana',
    ':dose' => '100 mg',
    ':doctor' => 'Doctora Inicial',
]);
$insert->execute([
    ':patient' => 9301,
    ':medication' => null,
    ':reason' => null,
    ':duration' => null,
    ':dose' => null,
    ':doctor' => null,
]);

$model = new MedicacionActualModel($db);
$changes = [
    1 => ['medicamento', 'Paracetamol'],
    2 => ['descripcion', 'Dolor de cabeza'],
    3 => ['tiempo_uso', 'Dos semanas'],
    4 => ['dosis', '500 mg'],
    5 => ['medico', 'Internista'],
];
foreach ($changes as $edition => [$field, $value]) {
    $result = $model->editarMedicamentoPaciente([
        'idPaciente' => 9301,
        'idMedicamento' => 1,
        'edicion' => $edition,
        'detalle' => $value,
    ]);
    $assert((int)($result['resultado'] ?? 0) === 200, 'La edición tradicional no devolvió 200 para ' . $field);
}

$row = $db->query('SELECT medicamento, descripcion, tiempo_uso, dosis, medico FROM pac_medicacion_actual_modulo_6 WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
$assert($row === [
    'medicamento' => 'Paracetamol',
    'descripcion' => 'Dolor de cabeza',
    'tiempo_uso' => 'Dos semanas',
    'dosis' => '500 mg',
    'medico' => 'Internista',
], 'El mapa tradicional no conservó cada edición en su columna canónica.');

$partial = $model->editarMedicamentoPaciente([
    'idPaciente' => 9301,
    'idMedicamento' => 2,
    'edicion' => 4,
    'detalle' => '250 mg',
]);
$assert((int)($partial['resultado'] ?? 0) === 200, 'No se pudo editar un medicamento parcial.');
$partialRow = $db->query('SELECT medicamento, descripcion, tiempo_uso, dosis, medico FROM pac_medicacion_actual_modulo_6 WHERE id = 2')->fetch(PDO::FETCH_ASSOC);
$assert($partialRow['medicamento'] === null && $partialRow['descripcion'] === null && $partialRow['tiempo_uso'] === null && $partialRow['dosis'] === '250 mg' && $partialRow['medico'] === null, 'La edición parcial alteró campos no solicitados.');

$crossPatient = $model->editarMedicamentoPaciente([
    'idPaciente' => 9302,
    'idMedicamento' => 1,
    'edicion' => 1,
    'detalle' => 'Cruce no autorizado',
]);
$assert((int)($crossPatient['resultado'] ?? 0) === 403, 'La edición cruzada no fue rechazada.');

echo "M06_TRADITIONAL_EDIT_CONTRACT: PASS (SQLite aislado; sin MySQL/HTTP)\n";
