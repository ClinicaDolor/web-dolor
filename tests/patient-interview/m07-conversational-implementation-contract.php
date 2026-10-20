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
$gateSource = file_get_contents($root . '/app/Services/M07CanonicalActivationGate.php');
$canonicalSource = file_get_contents($root . '/app/Services/M07CanonicalService.php');
$conversationSource = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');
$canonicalViewStart = strpos($viewSource, 'const m07CanonicalForm');
$canonicalViewSource = $canonicalViewStart === false ? '' : substr($viewSource, $canonicalViewStart);

$assertions = [
    'adapter_exists' => is_string($adapterSource),
    'adapter_read' => is_string($adapterSource) && str_contains($adapterSource, 'public function read'),
    'adapter_interpret' => is_string($adapterSource) && str_contains($adapterSource, 'public function interpret'),
    'canonical_writer' => is_string($adapterSource) && str_contains($adapterSource, '->captureCatalog(') && !str_contains($adapterSource, 'editarCuestionarioM7Paciente'),
    'canonical_service_injection' => is_string($adapterSource) && str_contains($adapterSource, '?M07CanonicalService $canonical = null') && str_contains($adapterSource, 'canonicalService()'),
    'single_active_question' => is_string($adapterSource) && str_contains($adapterSource, 'M07_SINGLE_ACTIVE_QUESTION_REQUIRED'),
    'idempotent_replay_before_stale_version' => is_string($adapterSource) && strpos($adapterSource, '$replay = $canonical->replayCatalogCapture(') < strpos($adapterSource, '$canonicalRecord = $canonical->captureCatalog('),
    'independent_readback' => is_string($adapterSource) && str_contains($adapterSource, 'M07_READBACK_MISMATCH') && str_contains($adapterSource, 'fieldsMatch'),
    'no_legacy_initializer' => is_string($adapterSource) && !str_contains($adapterSource, 'medicacionDolorQuest('),
    'version_guard' => is_string($adapterSource) && str_contains($adapterSource, 'M07_VERSION_CONFLICT'),
    'duplicate_guard' => is_string($adapterSource) && str_contains($adapterSource, 'M07_DUPLICATE_ITEM'),
    'unknown_pending' => is_string($adapterSource) && str_contains($adapterSource, 'M07_UNKNOWN_PENDING'),
    'combined_pending' => is_string($adapterSource) && str_contains($adapterSource, 'M07_COMBINED_OUTCOME_REVIEW'),
    'other_capture_uses_question_linked_canonical_flow' => is_string($adapterSource) && str_contains($adapterSource, 'createOtherMedication(') && str_contains($adapterSource, 'updateOtherMedication(') && str_contains($adapterSource, 'M07_OTHER_CREATE_REQUIRED'),
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
    'finalization_uses_existing_contract' => is_string($adapterSource) && str_contains($adapterSource, 'finalizeModule(') && str_contains($adapterSource, 'M07_CANONICAL_DISABLED'),
    'finalization_waits_for_readback' => is_string($viewSource) && str_contains($viewSource, 'data.finalization_readback === true'),
    'local_gate_trusted_process_only' => is_string($gateSource) && str_contains($gateSource, 'fromTrustedServerConfiguration') && str_contains($gateSource, "getenv('M07_LOCAL_CANONICAL_ENABLED') === '1'") && str_contains($gateSource, "PHP_SAPI === 'apache2handler'"),
    'local_gate_exact_database_and_server' => is_string($gateSource) && str_contains($gateSource, 'return $database === self::DATABASE') && str_contains($gateSource, "'127.0.0.1'") && str_contains($gateSource, "'8117'"),
    'local_gate_synthetic_patient_clinic_allowlist' => is_string($gateSource) && str_contains($gateSource, '9301 => 9101') && str_contains($gateSource, '9302 => 9102'),
    'canonical_gate_is_default_application_policy' => is_string($canonicalSource) && str_contains($canonicalSource, 'M07CanonicalActivationGate::fromTrustedServerConfiguration()'),
    'canonical_active_question_projection' => is_string($adapterSource) && str_contains($adapterSource, "'stable_id' => 'M07:question:' . \$questionId") && str_contains($adapterSource, 'resolveActiveQuestion'),
    'single_canonical_question_renderer' => is_string($viewSource) && str_contains($viewSource, 'data-m07-canonical-editor') && str_contains($viewSource, 'data-stable-id=') && str_contains($viewSource, 'data-m07-send'),
    'legacy_view_conditional_and_blocked_controls_absent' => is_string($viewSource) && str_contains($viewSource, "elseif (!empty(\$m07State['write_enabled']))") && str_contains($viewSource, 'data-m07-renderer="blocked"'),
    'capture_requires_backend_active_question' => is_string($adapterSource) && str_contains($adapterSource, 'M07_NOT_ACTIVE_QUESTION') && str_contains($adapterSource, 'active_question'),
    'readback_before_next_server_render' => $canonicalViewSource !== '' && strpos($canonicalViewSource, 'data.version !== data.readback.version') < strpos($canonicalViewSource, 'window.location.reload();'),
    'm07_capture_never_auto_finalizes' => is_string($controllerSource) && str_contains($controllerSource, 'finalizeAfterCapture((int)$patientId, 7, $result, false)'),
    'm07_voice_uses_active_canonical_target' => is_string($viewSource) && str_contains($viewSource, "if (moduleId === 'M07')") && str_contains($viewSource, 'question.active === true'),
];

if (in_array(false, $assertions, true)) {
    fwrite(STDERR, "M07 implementation contract: FAIL\n" . json_encode($assertions, JSON_PRETTY_PRINT) . "\n");
    exit(1);
}

$behavior = ['read_no_write' => 'BLOCKED', 'unknown_pending' => 'BLOCKED', 'combined_pending' => 'BLOCKED', 'yes_proposal' => 'BLOCKED', 'simulated_gate_closed' => 'BLOCKED', 'simulated_active_question' => 'BLOCKED', 'simulated_next_question_stays_locked' => 'BLOCKED'];
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE pac_temas_modulo_7 (id INTEGER PRIMARY KEY, tema TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE pac_preguntas_modulo_7 (id INTEGER PRIMARY KEY, id_tema INTEGER NOT NULL, pregunta TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE pac_respuestas_paciente_modulo_7 (id INTEGER PRIMARY KEY AUTOINCREMENT, id_pregunta INTEGER NOT NULL, id_paciente INTEGER NOT NULL, respuesta TEXT, dosis TEXT, resultados TEXT, consumo TEXT, descripcion TEXT)');
    $pdo->exec('CREATE TABLE pac_historia_clinica_finalizar (id INTEGER PRIMARY KEY AUTOINCREMENT, id_modulo INTEGER, id_paciente INTEGER)');
    $pdo->exec('CREATE TABLE pc_paciente (id INTEGER PRIMARY KEY, id_clinica INTEGER NOT NULL)');
    $pdo->exec('INSERT INTO pc_paciente (id,id_clinica) VALUES (9302,9102)');
    $pdo->exec("INSERT INTO pac_temas_modulo_7 (id, tema) VALUES (1, 'Sintético'), (2, 'Otra categoría')");
    $pdo->exec("INSERT INTO pac_preguntas_modulo_7 (id, id_tema, pregunta) VALUES (1, 1, 'Medicamento sintético'), (2, 2, 'Otro medicamento')");
    $adapter = new App\Services\ConversationalM07Adapter($pdo);
    $before = (int)$pdo->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7')->fetchColumn();
    $state = $adapter->read(9302);
    $after = (int)$pdo->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7')->fetchColumn();
    $behavior['read_no_write'] = ($before === 0 && $after === 0 && $state['pending_items'] === 2) ? 'PASS' : 'FAIL';
    $behavior['gate_closed_in_simulation'] = ($state['write_enabled'] === false && $state['finalization_enabled'] === false) ? 'PASS' : 'FAIL';
    $blockedFinalization = $adapter->finalize(9302, $state['version']);
    $behavior['premature_finalization_blocked'] = (($blockedFinalization['code'] ?? '') === 'M07_CANONICAL_DISABLED' && ($blockedFinalization['finalized'] ?? true) === false) ? 'PASS' : 'FAIL';
    $unknown = $adapter->interpret(1, 'respuesta', 'no recuerdo');
    $behavior['unknown_pending'] = (($unknown['kind'] ?? '') === 'm07_pending' && ($unknown['can_confirm'] ?? true) === false) ? 'PASS' : 'FAIL';
    $combined = $adapter->interpret(1, 'resultados', 'funcionó pero tuve efectos adversos');
    $behavior['combined_pending'] = (($combined['code'] ?? '') === 'M07_COMBINED_OUTCOME_REVIEW' && ($combined['can_confirm'] ?? true) === false) ? 'PASS' : 'FAIL';
    $yes = $adapter->interpret(1, 'respuesta', 'sí');
    $behavior['yes_proposal'] = (($yes['structured_value'] ?? '') === 'Si' && ($yes['can_confirm'] ?? false) === true) ? 'PASS' : 'FAIL';
}

$contractBuilder = new App\Services\ConversationalInterviewService();
$simulatedItems = [
    ['question_id'=>8,'question'=>'¿Utilizas el primer medicamento?','response_id'=>0,'fields'=>['respuesta'=>'','dosis'=>'','resultados'=>'','consumo'=>'','descripcion'=>''],'status'=>'pending','version'=>0],
    ['question_id'=>9,'question'=>'¿Utilizas el siguiente medicamento?','response_id'=>0,'fields'=>['respuesta'=>'','dosis'=>'','resultados'=>'','consumo'=>'','descripcion'=>''],'status'=>'pending','version'=>0],
];
$simulatedEnabled = $contractBuilder->buildUnifiedContract(['M07'=>[
    'title'=>'Medicamentos para el dolor','write_enabled'=>true,'write_blocker'=>null,'finalization_enabled'=>false,'finalized'=>false,'canonical_read'=>true,'version'=>'simulated-version',
    'categories'=>[['theme_id'=>1,'theme'=>'Sintético','items'=>$simulatedItems]],
    'active_question'=>['stable_id'=>'M07:question:8','question_id'=>8,'prompt'=>$simulatedItems[0]['question'],'mode'=>'catalog','field'=>'respuesta','write_enabled'=>true],
]]);
$simModule = $simulatedEnabled['modules']['M07'] ?? [];
$simQuestions = $simModule['questions'] ?? [];
$behavior['simulated_active_question'] = (($simModule['conversation_state']['state'] ?? '') === 'ANSWERABLE'
    && ($simModule['conversation_state']['stable_id'] ?? '') === 'M07:question:8'
    && ($simQuestions[0]['active'] ?? false) === true
    && ($simQuestions[0]['confirmation']['write_enabled'] ?? false) === true) ? 'PASS' : 'FAIL';
$behavior['simulated_next_question_stays_locked'] = (($simQuestions[1]['active'] ?? true) === false
    && ($simQuestions[1]['confirmation']['write_enabled'] ?? true) === false
    && ($simQuestions[1]['stable_id'] ?? '') === 'M07:question:9') ? 'PASS' : 'FAIL';
$simulatedClosed = $contractBuilder->buildUnifiedContract(['M07'=>[
    'title'=>'Medicamentos para el dolor','write_enabled'=>false,'write_blocker'=>'M07_CANONICAL_DISABLED','finalization_enabled'=>false,'finalized'=>false,'canonical_read'=>true,'version'=>'simulated-version',
    'categories'=>[['theme_id'=>1,'theme'=>'Sintético','items'=>$simulatedItems]],
    'active_question'=>['stable_id'=>'M07:question:8','question_id'=>8,'prompt'=>$simulatedItems[0]['question'],'mode'=>'catalog','field'=>'respuesta','write_enabled'=>false],
]]);
$behavior['simulated_gate_closed'] = (($simulatedClosed['modules']['M07']['conversation_state']['state'] ?? '') === 'BLOCKED_PREREQUISITE'
    && ($simulatedClosed['modules']['M07']['questions'][0]['confirmation']['write_enabled'] ?? true) === false
    && ($simulatedClosed['modules']['M07']['state']['finalization_enabled'] ?? true) === false) ? 'PASS' : 'FAIL';

if (in_array('FAIL', $behavior, true)) {
    fwrite(STDERR, "M07 implementation behavior: FAIL\n" . json_encode($behavior, JSON_PRETTY_PRINT) . "\n");
    exit(1);
}

echo 'M07 implementation contract: PASS (' . count($assertions) . " static assertions; " . json_encode($behavior) . ")\n";
