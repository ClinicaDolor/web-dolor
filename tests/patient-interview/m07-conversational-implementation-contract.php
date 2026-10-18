<?php

declare(strict_types=1);

/**
 * M07 implementation contract test.  It uses an in-memory SQLite database for
 * read/interpret checks only; it never opens a project database and never
 * executes a clinical write.
 */
$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';

$adapterSource = file_get_contents($root . '/app/Services/ConversationalM07Adapter.php');
$modelSource = file_get_contents($root . '/app/Models/MedicacionDolorModel.php');
$controllerSource = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$routesSource = file_get_contents($root . '/app/Config/routes.php');
$viewSource = file_get_contents($root . '/app/views/paciente/entrevista.php');

$assertions = [
    'adapter_exists' => is_string($adapterSource),
    'adapter_read' => is_string($adapterSource) && str_contains($adapterSource, 'public function read'),
    'adapter_interpret' => is_string($adapterSource) && str_contains($adapterSource, 'public function interpret'),
    'transaction' => is_string($adapterSource) && str_contains($adapterSource, '$db->beginTransaction()') && str_contains($adapterSource, '$db->rollBack()'),
    'canonical_model' => is_string($adapterSource) && str_contains($adapterSource, 'MedicacionDolorModel'),
    'model_connection_injection' => is_string($modelSource) && str_contains($modelSource, '__construct(?\\PDO $db = null)') && str_contains($adapterSource, 'new \\App\\Models\\MedicacionDolorModel($db)'),
    'no_legacy_initializer' => is_string($adapterSource) && !str_contains($adapterSource, 'medicacionDolorQuest('),
    'version_guard' => is_string($adapterSource) && str_contains($adapterSource, 'M07_VERSION_CONFLICT'),
    'duplicate_guard' => is_string($adapterSource) && str_contains($adapterSource, 'M07_DUPLICATE_ITEM'),
    'unknown_pending' => is_string($adapterSource) && str_contains($adapterSource, 'M07_UNKNOWN_PENDING'),
    'combined_pending' => is_string($adapterSource) && str_contains($adapterSource, 'M07_COMBINED_OUTCOME_REVIEW'),
    'other_blocked' => is_string($adapterSource) && str_contains($adapterSource, 'OTHER_MEDICATION_NAME_UNREPRESENTABLE'),
    'controller_reads_m07_with_scope' => is_string($controllerSource) && (
        str_contains($controllerSource, "'m07' => (new ConversationalM07Adapter())->read(\$patientId, (int)\$auth['id_clinica'], 'patient')")
        || (str_contains($controllerSource, "\$m07State = (new ConversationalM07Adapter())->read(\$patientId, (int)\$auth['id_clinica'], 'patient')") && str_contains($controllerSource, "'m07' => \$m07State"))
    ),
    'controller_capture_m07' => is_string($controllerSource) && str_contains($controllerSource, 'public function captureM07'),
    'route_capture_m07' => is_string($routesSource) && str_contains($routesSource, "'/entrevista/capturar-m07'"),
    'grouped_ux' => is_string($viewSource) && str_contains($viewSource, 'data-m07-category') && str_contains($viewSource, 'data-m07-used'),
    'explicit_negative_action' => is_string($viewSource) && str_contains($viewSource, 'data-m07-confirm-negatives'),
    'sequential_navigation' => is_string($viewSource) && str_contains($viewSource, 'data-m07-previous-category') && str_contains($viewSource, 'data-m07-next-category') && str_contains($viewSource, 'm07CategoryCanLeave'),
    'navigation_does_not_write' => is_string($viewSource) && str_contains($viewSource, 'data-dirty') && str_contains($viewSource, 'Confirma las respuestas pendientes'),
    'no_placeholder' => is_string($viewSource) && !str_contains($viewSource, 'catálogo oficial de preguntas no está disponible en esta entrega'),
    'finalization_disabled' => is_string($adapterSource) && str_contains($adapterSource, "'finalization_enabled' => false"),
];

if (in_array(false, $assertions, true)) {
    fwrite(STDERR, "M07 implementation contract: FAIL\n" . json_encode($assertions, JSON_PRETTY_PRINT) . "\n");
    exit(1);
}

$behavior = ['read_no_write' => 'BLOCKED', 'unknown_pending' => 'BLOCKED', 'combined_pending' => 'BLOCKED', 'yes_proposal' => 'BLOCKED'];
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE pac_temas_modulo_7 (id INTEGER PRIMARY KEY, tema TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE pac_preguntas_modulo_7 (id INTEGER PRIMARY KEY, id_tema INTEGER NOT NULL, pregunta TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE pac_respuestas_paciente_modulo_7 (id INTEGER PRIMARY KEY AUTOINCREMENT, id_pregunta INTEGER NOT NULL, id_paciente INTEGER NOT NULL, respuesta TEXT, dosis TEXT, resultados TEXT, consumo TEXT, descripcion TEXT)');
    $pdo->exec('CREATE TABLE pac_historia_clinica_finalizar (id INTEGER PRIMARY KEY AUTOINCREMENT, id_modulo INTEGER, id_paciente INTEGER)');
    $pdo->exec("INSERT INTO pac_temas_modulo_7 (id, tema) VALUES (1, 'Sintético'), (2, 'Otra categoría')");
    $pdo->exec("INSERT INTO pac_preguntas_modulo_7 (id, id_tema, pregunta) VALUES (1, 1, 'Medicamento sintético'), (2, 2, 'Otro medicamento')");
    $adapter = new App\Services\ConversationalM07Adapter($pdo);
    $before = (int)$pdo->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7')->fetchColumn();
    $state = $adapter->read(9302);
    $after = (int)$pdo->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7')->fetchColumn();
    $behavior['read_no_write'] = ($before === 0 && $after === 0 && $state['pending_items'] === 2) ? 'PASS' : 'FAIL';
    $unknown = $adapter->interpret(1, 'respuesta', 'no recuerdo');
    $behavior['unknown_pending'] = (($unknown['kind'] ?? '') === 'm07_pending' && ($unknown['can_confirm'] ?? true) === false) ? 'PASS' : 'FAIL';
    $combined = $adapter->interpret(1, 'resultados', 'funcionó pero tuve efectos adversos');
    $behavior['combined_pending'] = (($combined['code'] ?? '') === 'M07_COMBINED_OUTCOME_REVIEW' && ($combined['can_confirm'] ?? true) === false) ? 'PASS' : 'FAIL';
    $yes = $adapter->interpret(1, 'respuesta', 'sí');
    $behavior['yes_proposal'] = (($yes['structured_value'] ?? '') === 'Si' && ($yes['can_confirm'] ?? false) === true) ? 'PASS' : 'FAIL';
}

if (in_array('FAIL', $behavior, true)) {
    fwrite(STDERR, "M07 implementation behavior: FAIL\n" . json_encode($behavior, JSON_PRETTY_PRINT) . "\n");
    exit(1);
}

echo 'M07 implementation contract: PASS (' . count($assertions) . " static assertions; " . json_encode($behavior) . ")\n";
