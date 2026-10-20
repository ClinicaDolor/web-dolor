<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Models\MedicacionActualModel;
use App\Services\ConversationalModuleFinalizationService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$root = dirname(__DIR__, 2);
$model = file_get_contents($root . '/app/Models/MedicacionActualModel.php') ?: '';
$adapter = file_get_contents($root . '/app/Services/ConversationalM06Adapter.php') ?: '';
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php') ?: '';
$view = file_get_contents($root . '/app/views/paciente/entrevista.php') ?: '';

$assert(str_contains($model, 'pac_medicacion_omisiones_m06'), 'M06 omission table contract is missing.');
$assert(str_contains($model, "['descripcion', 'tiempo_uso', 'dosis', 'medico']") && str_contains($model, "['UNKNOWN', 'SKIPPED']"), 'M06 omission allowlists are incomplete.');
$assert(str_contains($model, 'ON DUPLICATE KEY UPDATE'), 'M06 omission persistence is not idempotent.');
$assert(str_contains($model, 'id = :id AND id_paciente = :id_paciente'), 'M06 omission writes lack patient ownership validation.');
$assert(str_contains($model, 'DELETE FROM pac_medicacion_omisiones_m06'), 'M06 omission resolution is missing.');
$assert(str_contains($adapter, 'captureOmission') && str_contains($adapter, 'M06_OMISSION_READBACK_FAILED'), 'M06 omission capture/readback is missing.');
$assert(str_contains($adapter, 'readM06Omissions') && str_contains($adapter, "'field_states'"), 'M06 omission state projection is missing.');
$assert(str_contains($controller, "\$data['omission']") && str_contains($controller, 'captureOmission'), 'M06 omission endpoint integration is missing.');
$assert(!str_contains($view, 'data-m06-omission-actions') && str_contains($view, 'data-m06-omissions-by-record') && str_contains($view, 'data-m06-revisit'), 'M06 omitted-field corrections must remain outside the active question.');

$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->exec('CREATE TABLE pac_medicacion_actual_modulo_6 (id INTEGER PRIMARY KEY AUTOINCREMENT, id_paciente INTEGER NOT NULL, medicamento TEXT NOT NULL, descripcion TEXT NOT NULL, tiempo_uso TEXT NOT NULL, dosis TEXT NOT NULL, medico TEXT NOT NULL)');
$created = (new MedicacionActualModel($sqlite))->agregarMedicacionPaciente([
    'idPaciente' => 9301,
    'fields' => ['medicamento' => 'Paracetamol'],
]);
$assert((int)($created['resultado'] ?? 0) === 200, 'Name-only M06 capture must be compatible with TEXT NOT NULL clinical columns.');
$createdRow = $sqlite->query('SELECT medicamento, descripcion, tiempo_uso, dosis, medico FROM pac_medicacion_actual_modulo_6')->fetch(PDO::FETCH_ASSOC);
$assert($createdRow === ['medicamento' => 'Paracetamol', 'descripcion' => '', 'tiempo_uso' => '', 'dosis' => '', 'medico' => ''], 'Name-only capture must use empty strings, never NULL, for unresolved clinical columns.');

$service = new ConversationalModuleFinalizationService();
$method = new ReflectionMethod($service, 'm06Complete');
$method->setAccessible(true);

$completeFields = [
    'medicamento' => 'ANSWERED',
    'descripcion' => 'UNKNOWN',
    'tiempo_uso' => 'SKIPPED',
    'dosis' => 'ANSWERED',
    'medico' => 'SKIPPED',
];
$completeState = [
    'records' => [[
        'id' => 1,
        'medicamento' => 'Paracetamol',
        'field_states' => $completeFields,
        'pending_fields' => [],
    ]],
];
$assert($method->invoke($service, $completeState, false) === true, 'Explicit UNKNOWN/SKIPPED states must allow M06 completion.');

$pendingState = $completeState;
$pendingState['records'][0]['field_states']['dosis'] = 'PENDING';
$pendingState['records'][0]['pending_fields'] = ['dosis'];
$assert($method->invoke($service, $pendingState, false) === false, 'A field without answer or omission must remain pending.');

$legacyComplete = [
    'records' => [[
        'medicamento' => 'Paracetamol', 'descripcion' => 'Dolor',
        'tiempo_uso' => 'Una semana', 'dosis' => '500 mg', 'medico' => 'Internista',
    ]],
];
$assert($method->invoke($service, $legacyComplete, false) === true, 'Complete legacy M06 records must remain finalizable.');

echo "M06_OMISSION_CONTRACT: PASS (static plus finalization doubles; no MySQL/HTTP)\n";
echo "UNKNOWN_SKIPPED_STATES: PASS_STATIC\n";
echo "PENDING_DISTINCTION: PASS_STATIC\n";
echo "FINALIZATION_RULE: PASS_STATIC\n";
echo "REAL_MYSQL: NOT_EXECUTED\n";
