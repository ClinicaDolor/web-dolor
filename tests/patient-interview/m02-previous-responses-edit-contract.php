<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$adapter = new App\Services\ConversationalM02Adapter();
$service = new App\Services\ConversationalInterviewService();
$heart = 'Enfermedades del corazón';
$answer = 'Mi madre y mi padre';
$proposal = $adapter->interpret($heart, $answer);
$assert(($proposal['kind'] ?? '') === 'm02_read_only_proposal'
    && ($proposal['detail'] ?? null) === 'Si'
    && ($proposal['relative_description'] ?? '') === $answer,
    'An edited positive M02 answer must preserve the existing deterministic text contract.');
$negative = $adapter->interpret($heart, 'No');
$assert(($negative['kind'] ?? '') === 'm02_read_only_proposal' && ($negative['detail'] ?? null) === 'No',
    'An edited negative answer must remain a canonical M02 negative.');
$ambiguous = $adapter->interpret($heart, 'Sí');
$assert(($ambiguous['kind'] ?? '') === 'clarification', 'An incomplete affirmative must not reach update persistence.');

$fixture = [
    'finalized' => true,
    'write_enabled' => false,
    'records' => [
        ['id' => 301, 'disease' => $heart, 'detail' => 'Si', 'status' => 'documented', 'legacy_relative_text' => 'Mi madre', 'relative_description' => 'Mi madre', 'relatives' => [], 'invalid_relatives' => [], 'diabetes_type' => '', 'inconsistent' => false, 'version' => str_repeat('a', 64)],
        ['id' => 302, 'disease' => 'Diabetes Mellitus', 'detail' => 'Si', 'status' => 'documented', 'legacy_relative_text' => '', 'relative_description' => null, 'relatives' => ['madre'], 'invalid_relatives' => [], 'diabetes_type' => 'Tipo 2', 'inconsistent' => false, 'version' => str_repeat('b', 64)],
    ],
];
$contract = $service->buildUnifiedContract(['M02' => $fixture]);
$heartQuestion = $contract['question_index']['M02:family:enfermedades-del-coraz-n'] ?? null;
$diabetesQuestion = $contract['question_index']['M02:family:diabetes-mellitus'] ?? null;
$assert(is_array($heartQuestion) && ($heartQuestion['module_id'] ?? '') === 'M02'
    && ($heartQuestion['response']['persisted'] ?? false) === true,
    'The review contract must expose the existing canonical heart-question identity and persisted state.');
$assert(is_array($diabetesQuestion) && ($diabetesQuestion['response']['relatives'] ?? []) === ['madre'],
    'The shared canonical contract must preserve structured relatives for read-only display.');
$assert(($contract['modules']['M02']['state']['finalized'] ?? false) === true
    && ($contract['modules']['M02']['state']['write_enabled'] ?? true) === false,
    'A finalized M02 remains finalized and ordinarily non-writable in the shared contract.');
$editPolicy = ['database_authorized' => true, 'relative_storage_absent_confirmed' => true, 'duplicate_diseases' => [], 'finalized' => true, 'write_enabled' => false];
$scalarExisting = ['id' => 301, 'disease' => $heart, 'detail' => 'Si', 'legacy_relative_text' => 'Mi madre', 'relatives' => [], 'invalid_relatives' => [], 'inconsistent' => false];
$structuredExisting = ['id' => 302, 'disease' => 'Diabetes Mellitus', 'detail' => 'Si', 'legacy_relative_text' => '', 'relatives' => ['madre'], 'invalid_relatives' => [], 'inconsistent' => false];
$assert($adapter->existingResponseEditBlockerCode($editPolicy, $scalarExisting) === null,
    'An existing scalar response remains eligible for the separate review edit even after finalization.');
$assert($adapter->existingResponseEditBlockerCode($editPolicy, $structuredExisting) === null,
    'A valid affirmative structured response may use the explicit relationship editor.');
$assert(App\Services\ConversationalM02Adapter::normalizeStructuredRelatives(['hermano', 'madre']) === ['madre', 'hermano']
    && App\Services\ConversationalM02Adapter::normalizeStructuredRelatives(['madre', 'madre']) === null
    && App\Services\ConversationalM02Adapter::normalizeStructuredRelatives(['desconocido']) === null,
    'Structured relationship input must be allowlisted, unique, and normalized to canonical order.');
$structuredNegative = $structuredExisting;
$structuredNegative['detail'] = 'No';
$assert($adapter->existingResponseEditBlockerCode($editPolicy, $structuredNegative) === 'M02_STRUCTURED_RELATION_EDIT_UNSUPPORTED',
    'A structured response may not be changed to a contradictory negative.');
$unauthorizedPolicy = $editPolicy;
$unauthorizedPolicy['database_authorized'] = false;
$assert($adapter->existingResponseEditBlockerCode($unauthorizedPolicy, $scalarExisting) === 'M02_DATABASE_NOT_AUTHORIZED',
    'The review edit must remain unavailable outside the adapter’s authorized database policy.');

$controllerSource = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$adapterSource = file_get_contents($root . '/app/Services/ConversationalM02Adapter.php');
$routesSource = file_get_contents($root . '/app/Config/routes.php');
$interviewSource = file_get_contents($root . '/app/views/paciente/entrevista.php');
$viewSource = file_get_contents($root . '/app/views/paciente/respuestas-previas.php');
$assert(str_contains($routesSource, "'/respuestas-previas', ['PatientInterviewController', 'previousResponses']")
    && str_contains($routesSource, "'/respuestas-previas/m02', ['PatientInterviewController', 'updatePreviousM02']"),
    'The authenticated patient group must register exactly one GET and one M02 update endpoint.');
$assert(str_contains($interviewSource, 'href="<?=SERVIDOR;?>historia-clinica/respuestas-previas"')
    && !preg_match('/<div\b[^>]*data-chat-history-panel/', $interviewSource),
    'The interview button must navigate to the independent page and the old inline panel markup must be absent.');

$getStart = strpos($controllerSource, 'public function previousResponses(): void');
$postStart = strpos($controllerSource, 'public function updatePreviousM02(): void');
$formatStart = strpos($controllerSource, 'private function formatM02CurrentAnswer');
$assert($getStart !== false && $postStart !== false && $formatStart !== false, 'Both focused controller actions must exist.');
$getBody = substr($controllerSource, $getStart, $postStart - $getStart);
$postBody = substr($controllerSource, $postStart, $formatStart - $postStart);
$assert(str_contains($getBody, "new AuthMiddleware('historia-clinica')")
    && str_contains($getBody, 'assertPatientAccess($patientId, $auth)')
    && str_contains($getBody, "buildUnifiedContract(['M02' => \$m02State])")
    && !str_contains($getBody, 'INSERT INTO')
    && !str_contains($getBody, 'UPDATE pc_')
    && !str_contains($getBody, 'DELETE FROM'),
    'The GET must use authenticated-patient ownership and canonical M02 read-only projection.');
$assert(str_contains($postBody, "new AuthMiddleware('historia-clinica')")
    && str_contains($postBody, 'assertPatientAccess($patientId, $auth)')
    && str_contains($postBody, 'validCaptureCsrf($patientId')
    && str_contains($postBody, "\$_SERVER['HTTP_X_M03_CSRF']")
    && !str_contains($postBody, "\$data['idPaciente']"),
    'The update endpoint must derive ownership from the patient session and require the session-bound CSRF token.');
$assert(str_contains($postBody, "\$question['module_id'] ?? null) !== 'M02'")
    && str_contains($postBody, "\$question['response']['persisted'] ?? false) !== true")
    && str_contains($postBody, 'expected_version')
    && str_contains($postBody, "\$result['readback'] ?? false")
    && !str_contains($postBody, 'ConversationalModuleFinalizationService'),
    'The endpoint must require a persisted canonical M02 question, optimistic version, readback, and no finalization side effect.');

$editStart = strpos($adapterSource, 'public function editExistingResponse(');
$nextMethod = strpos($adapterSource, 'private function findRecordFromState', $editStart ?: 0);
$assert($editStart !== false && $nextMethod !== false, 'The adapter must expose the focal existing-answer update operation.');
$editBody = substr($adapterSource, $editStart, $nextMethod - $editStart);
$assert(str_contains($editBody, 'UPDATE pc_antecedentes_familiares SET detalle=:detail,tipo=:type,especificar=:description WHERE id=:id AND id_paciente=:patient AND enfermedad=:disease')
    && !preg_match('/\bINSERT\s+INTO\s+pc_antecedentes_familiares\s*\(/i', $editBody)
    && !str_contains($editBody, 'DELETE FROM pc_antecedentes_familiares WHERE id=:id')
    && str_contains($editBody, 'hash_equals((string)$current[\'version\'], $expectedVersion)')
    && str_contains($editBody, "'readback' => true")
    && str_contains($adapterSource, 'M02_STRUCTURED_RELATION_EDIT_UNSUPPORTED')
    && str_contains($editBody, 'M02_STALE_RELATIONSHIPS')
    && str_contains($editBody, 'if ($structuredBefore && $targetRelatives !== ($current[\'relatives\'] ?? []))')
    && str_contains($editBody, 'M02_RELATION_READBACK_FAILED')
    && str_contains($editBody, 'targetRelatives === []')
    && str_contains($editBody, 'pc_antecedentes_familiares_parentescos WHERE id_antecedente=:id ORDER BY parentesco FOR UPDATE')
    && str_contains($editBody, "\$readback['relatives'] ?? []) !== \$targetRelatives")
    && str_contains($editBody, 'beginTransaction()') && str_contains($editBody, 'rollBack()'),
    'The edit must update only the existing parent row, reconcile selected relationships transactionally, verify readback, and reject stale/empty sets.');
$assert(!str_contains($editBody, 'pac_historia_clinica_finalizar')
    && !str_contains($editBody, 'UPDATE pac_historia_clinica_finalizar'),
    'Editing an answer must not change the M02 finalization record.');

foreach (['Guardar cambios', 'Cancelar', 'Cerrar revisión y volver a la entrevista', 'M02 está finalizado'] as $text) {
    $assert(str_contains($viewSource, $text), 'The page must provide its requested UI element/message: ' . $text);
}
$assert(str_contains($viewSource, 'credentials: \'same-origin\'')
    && str_contains($viewSource, "'X-M03-CSRF': csrf")
    && str_contains($viewSource, 'result.readback !== true')
    && str_contains($viewSource, 'response.status === 409')
    && str_contains($viewSource, 'editor.dataset.editValue ||')
    && str_contains($viewSource, 'showMessage(savedMessage, \'Respuesta actualizada.\', \'success\')'),
    'The browser flow must keep session/CSRF, require readback, handle stale conflicts, retain draft on error, and show success only after readback.');

foreach (['RUTA_CSS', 'RUTA_JS', 'RUTA_PUBLIC', 'RUTA_IMAGES', 'SERVIDOR'] as $constant) {
    if (!defined($constant)) define($constant, '/assets/');
}
$data = [
    'title' => 'Respuestas previas',
    'csrf' => 'csrf-synthetic-only',
    'sidebar' => '<nav aria-label="Navegación sintética"></nav>',
    'm02Finalized' => true,
    'entries' => [
        ['stable_id' => $heartQuestion['stable_id'], 'question' => $heartQuestion['original_question'], 'disease' => $heart, 'answer' => 'Mi madre & <script>no-ejecutable</script>', 'answer_input' => 'Mi madre', 'relatives' => [], 'relative_options' => [], 'structured_editable' => false, 'diabetes_type' => '', 'version' => str_repeat('a', 64), 'editable' => true, 'structured' => false, 'edit_blocker' => ''],
        ['stable_id' => $diabetesQuestion['stable_id'], 'question' => $diabetesQuestion['original_question'], 'disease' => 'Diabetes Mellitus', 'answer' => 'Sí — parentescos registrados: madre', 'answer_input' => '', 'relatives' => ['madre'], 'relative_options' => ['madre' => 'Madre', 'padre' => 'Padre'], 'structured_editable' => true, 'diabetes_type' => 'Tipo 2', 'version' => str_repeat('b', 64), 'editable' => true, 'structured' => true, 'edit_blocker' => 'Actualiza los parentescos.'],
        ['stable_id' => 'M02:family:cancer', 'question' => '¿Algún familiar tiene cáncer?', 'disease' => 'Cáncer', 'answer' => 'Sí — parentesco no verificable', 'answer_input' => '', 'relatives' => [], 'relative_options' => [], 'structured_editable' => false, 'diabetes_type' => '', 'version' => str_repeat('c', 64), 'editable' => false, 'structured' => true, 'edit_blocker' => 'Solo lectura.'],
    ],
];
ob_start();
require $root . '/app/views/paciente/respuestas-previas.php';
$rendered = (string)ob_get_clean();
$assert(str_contains($rendered, 'Mi madre &amp; &lt;script&gt;no-ejecutable&lt;/script&gt;')
    && !str_contains($rendered, 'Mi madre & <script>no-ejecutable</script>'),
    'Persisted answers must be escaped in the HTML page.');
$assert(str_contains($rendered, 'data-stable-id="M02:family:enfermedades-del-coraz-n"'), 'Rendered HTML must retain the canonical stable_id.');
$assert(str_contains($rendered, 'data-editable="0"'), 'Unsupported entries must remain visibly read-only.');
$assert(str_contains($viewSource, 'Conservar el tipo actual') && str_contains($rendered, 'Tipo 2'), 'The diabetes editor must preserve its optional type and the current rendered response must show its stored type.');
$assert(str_contains($rendered, 'type="submit" class="btn previous-response-save"'), 'Editable entries must render a save action.');
$assert(str_contains($rendered, 'data-relative-input value="madre" checked')
    && str_contains($rendered, 'data-relative-values="[&quot;madre&quot;]"')
    && str_contains($viewSource, 'relativeInputs.filter(option => option.checked)')
    && str_contains($viewSource, 'relatives} : {}'),
    'Structured editing must preload the stored relationship selection and submit selected values.');

echo "PASS M02 previous responses edit contract (static/rendered/synthetic validation; no database connection).\n";
