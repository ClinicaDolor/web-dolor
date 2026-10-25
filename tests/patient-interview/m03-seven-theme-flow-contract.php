<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Models\AntecedentesNoPatologicosModel;
use App\Services\ConversationalInterviewService;
use App\Services\ConversationalM03Adapter;
use App\Services\ConversationalModuleFinalizationService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$catalog = require dirname(__DIR__) . '/fixtures/p05-catalog.php';
$parents = [
    6 => ['theme' => 2, 'dependents' => [8, 9, 10, 11], 'next' => 12],
    12 => ['theme' => 3, 'dependents' => [13, 14, 15], 'next' => 16],
    16 => ['theme' => 4, 'dependents' => [17], 'next' => 18],
    18 => ['theme' => 5, 'dependents' => [19, 20], 'next' => 21],
    21 => ['theme' => 6, 'dependents' => [23, 24, 26], 'next' => 27],
];
$makeDb = static function (bool $semanticColumns = true) use ($catalog): PDO {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE pc_paciente (id INTEGER PRIMARY KEY, id_clinica INTEGER NOT NULL)');
    $db->exec('INSERT INTO pc_paciente (id,id_clinica) VALUES (9301,9101),(9302,9102)');
    $db->exec('CREATE TABLE pac_temas_modulo_3 (id INTEGER PRIMARY KEY, tema TEXT NOT NULL)');
    $theme = $db->prepare('INSERT INTO pac_temas_modulo_3 (id,tema) VALUES (?,?)');
    foreach ($catalog['themes'] as $row) $theme->execute([(int)$row['id'], (string)$row['tema']]);
    $db->exec('CREATE TABLE pac_preguntas_modulo_3 (id INTEGER PRIMARY KEY, id_tema INTEGER NOT NULL, pregunta TEXT NOT NULL, tipo TEXT NOT NULL)');
    $question = $db->prepare('INSERT INTO pac_preguntas_modulo_3 (id,id_tema,pregunta,tipo) VALUES (?,?,?,?)');
    foreach ($catalog['questions'] as $row) $question->execute([(int)$row['id'], (int)$row['id_tema'], (string)$row['pregunta'], (string)$row['tipo']]);
    $db->exec($semanticColumns
        ? 'CREATE TABLE pac_respuestas_paciente_modulo_3 (id INTEGER PRIMARY KEY AUTOINCREMENT, id_pregunta INTEGER NOT NULL, id_paciente INTEGER NOT NULL, respuesta TEXT NOT NULL, estado_respuesta VARCHAR(24), origen_respuesta VARCHAR(32), estado_respuesta_at DATETIME)'
        : 'CREATE TABLE pac_respuestas_paciente_modulo_3 (id INTEGER PRIMARY KEY AUTOINCREMENT, id_pregunta INTEGER NOT NULL, id_paciente INTEGER NOT NULL, respuesta TEXT NOT NULL)');
    $db->exec('CREATE TABLE pac_historia_clinica_finalizar (id INTEGER PRIMARY KEY AUTOINCREMENT, id_paciente INTEGER NOT NULL, id_modulo INTEGER NOT NULL)');
    return $db;
};
$applyPriorNegatives = static function (ConversationalM03Adapter $adapter, int $targetParent) use ($assert): void {
    foreach ([1, 6, 12, 16, 18, 21] as $parentId) {
        if ($parentId >= $targetParent) break;
        $state = $adapter->read(9301);
        $question = array_column($state['questions'], null, 'idPregunta')[$parentId] ?? null;
        $assert(is_array($question) && !empty($question['eligible']) && is_array($question['contextualAction'] ?? null), 'PRIOR_PARENT_NOT_REACHABLE:' . $parentId);
        $saved = $adapter->captureContextualAction(9301, $parentId, (int)$question['idTema'], (string)$question['contextualAction']['key'], (string)$state['version']);
        $assert((int)($saved['resultado'] ?? 0) === 200 && ($saved['readback'] ?? false) === true, 'PRIOR_PARENT_NEGATIVE_SETUP_FAILED:' . $parentId);
    }
};

$assert(count($catalog['themes']) === 7 && count($catalog['questions']) === 25, 'CATALOG_COVERAGE_INVALID');
$expectedIds = [1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 23, 24, 26, 27, 28];
$assert(array_column($catalog['questions'], 'id') === $expectedIds, 'OFFICIAL_QUESTION_IDS_CHANGED');
$contract = (new ConversationalInterviewService())->buildUnifiedContract(['M03' => [
    'questions' => array_map(static function (array $row) use ($catalog): array {
        $themeMap = array_column($catalog['themes'], 'tema', 'id');
        return ['idPregunta' => $row['id'], 'idTema' => $row['id_tema'], 'tema' => $themeMap[$row['id_tema']],
            'pregunta' => $row['pregunta'], 'tipo' => $row['tipo'], 'respuesta' => '', 'idRespuesta' => 0,
            'eligible' => true, 'semanticState' => 'UNANSWERED'];
    }, $catalog['questions']),
    'version' => 'M03-read-only-contract-fixture', 'write_enabled' => false,
]]);
foreach ($catalog['questions'] as $row) {
    $stable = 'M03:question:' . (int)$row['id'];
    $canonical = $contract['question_index'][$stable] ?? null;
    $assert(is_array($canonical) && ($canonical['visible_text'] ?? null) === $row['pregunta'], 'CANONICAL_QUESTION_TEXT_CHANGED:' . $stable);
    $assert(is_string($canonical['voice']['QUESTION'] ?? null) && $canonical['voice']['QUESTION'] === $row['pregunta'], 'QUESTION_VOICE_TEXT_MISMATCH:' . $stable);
    $assert(is_string($canonical['voice']['HELP'] ?? null) && trim($canonical['voice']['HELP']) !== '', 'QUESTION_HELP_MISSING:' . $stable);
}
$assert(str_contains($contract['question_index']['M03:question:11']['help'], 'no se supondrá') === false, 'Q11_HELP_MUST_NOT_MISSTATE_NOT_RECORDED_HISTORY');
$assert(str_contains($contract['question_index']['M03:question:17']['help'], 'referencias') && str_contains($contract['question_index']['M03:question:17']['help'], 'tus propias palabras'), 'STRESS_LEVEL_MUST_REMAIN_PATIENT_DECLARED_FREE_TEXT');
$assert(($contract['question_index']['M03:question:27']['options'] ?? []) === [], 'BLOOD_GROUP_MUST_NOT_GAIN_INVENTED_OPTIONS');

foreach ($parents as $parentId => $definition) {
    $db = $makeDb();
    $adapter = new ConversationalM03Adapter($db);
    $applyPriorNegatives($adapter, $parentId);
    $before = $adapter->read(9301);
    $parent = array_column($before['questions'], null, 'idPregunta')[$parentId] ?? null;
    $assert(is_array($parent) && !empty($parent['eligible']) && (int)$parent['idTema'] === $definition['theme'], 'PARENT_QUESTION_SCOPE_INVALID:' . $parentId);

    // Explicit No: persisted negative readback; dependents remain untouched and
    // the server selects the next eligible parent rather than parentId + 1.
    $negative = $adapter->captureContextualAction(9301, $parentId, $definition['theme'], (string)$parent['contextualAction']['key'], (string)$before['version']);
    $assert((int)($negative['resultado'] ?? 0) === 200 && ($negative['readback'] ?? false) === true
        && ($negative['semantic_state'] ?? '') === 'NEGATIVE' && ($negative['readback_value'] ?? '') === 'No'
        && (int)($negative['next_question_id'] ?? 0) === $definition['next'], 'NEGATIVE_BRANCH_OR_SERVER_ADVANCE_FAILED:' . $parentId);
    $afterNegative = $adapter->read(9301);
    $byId = array_column($afterNegative['questions'], null, 'idPregunta');
    foreach ($definition['dependents'] as $dependentId) {
        $assert(empty($byId[$dependentId]['eligible']) && (int)$byId[$dependentId]['idRespuesta'] === 0, 'NEGATIVE_BRANCH_EXPOSED_OR_WROTE_DEPENDENT:' . $dependentId);
    }
    $replay = $adapter->captureContextualAction(9301, $parentId, $definition['theme'], (string)$parent['contextualAction']['key'], (string)$before['version']);
    $assert((int)($replay['resultado'] ?? 0) === 409, 'STALE_NEGATIVE_REPLAY_NOT_REJECTED:' . $parentId);

    // Affirmative path: server readback exposes each dependent in catalog order;
    // answer each with synthetic fixture text and verify the next target.
    $dbYes = $makeDb();
    $adapterYes = new ConversationalM03Adapter($dbYes);
    $applyPriorNegatives($adapterYes, $parentId);
    $state = $adapterYes->read(9301);
    $affirmative = $adapterYes->capture(9301, [
        'kind' => 'm03_proposal', 'question_id' => $parentId, 'theme_id' => $definition['theme'],
        'structured_value' => 'Si', 'semantic_state' => 'KNOWN',
    ], (string)$state['version']);
    $assert((int)($affirmative['resultado'] ?? 0) === 200 && ($affirmative['readback'] ?? false) === true
        && (int)($affirmative['next_question_id'] ?? 0) === $definition['dependents'][0], 'AFFIRMATIVE_BRANCH_DID_NOT_EXPOSE_FIRST_DEPENDENT:' . $parentId);
    foreach ($definition['dependents'] as $index => $dependentId) {
        $state = $adapterYes->read(9301);
        $question = array_column($state['questions'], null, 'idPregunta')[$dependentId] ?? null;
        $assert(is_array($question) && !empty($question['eligible']), 'AFFIRMATIVE_DEPENDENT_NOT_ELIGIBLE:' . $dependentId);
        $next = $definition['dependents'][$index + 1] ?? $definition['next'];
        $saved = $adapterYes->capture(9301, [
            'kind' => 'm03_proposal', 'question_id' => $dependentId, 'theme_id' => $definition['theme'],
            'structured_value' => 'fixture answer for question ' . $dependentId, 'semantic_state' => 'KNOWN',
        ], (string)$state['version']);
        $assert((int)($saved['resultado'] ?? 0) === 200 && ($saved['readback'] ?? false) === true
            && (int)($saved['response_id'] ?? 0) > 0 && (int)($saved['next_question_id'] ?? 0) === $next,
            'DEPENDENT_CAPTURE_READBACK_OR_NEXT_TARGET_FAILED:' . $dependentId);
    }
    $priorParentCount = count(array_filter([1, 6, 12, 16, 18, 21], static fn(int $id): bool => $id < $parentId));
    $duplicateCount = (int)$dbYes->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3')->fetchColumn();
    $assert($duplicateCount === $priorParentCount + 1 + count($definition['dependents']), 'AFFIRMATIVE_FLOW_CREATED_UNEXPECTED_ROWS:' . $parentId);
    $assert((int)$dbYes->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9302')->fetchColumn() === 0, 'FLOW_CROSSED_SYNTHETIC_PATIENT_BOUNDARY:' . $parentId);
}

// Previous-responses M03 is a separate update-only flow. These fixtures use
// only in-memory SQLite and assert that finalization/progress remain untouched.
$editDb = $makeDb();
$editAdapter = new ConversationalM03Adapter($editDb);
$editDb->exec("INSERT INTO pac_historia_clinica_finalizar (id_paciente,id_modulo) VALUES (9301,3)");
$insertExisting = $editDb->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta,id_paciente,respuesta,estado_respuesta,origen_respuesta,estado_respuesta_at) VALUES (?,?,?,?,?,?)');
$insertExisting->execute([1, 9301, 'Si', 'KNOWN', 'PATIENT_DECLARATION', '2026-10-20 12:00:00']);
$insertExisting->execute([2, 9301, '10 años', 'KNOWN', 'PATIENT_DECLARATION', '2026-10-20 12:00:01']);
$beforeEdit = $editAdapter->read(9301);
$editRows = array_column($beforeEdit['questions'], null, 'idPregunta');
$q2BeforeEdit = $editRows[2] ?? null;
$assert(is_array($q2BeforeEdit) && !empty($q2BeforeEdit['eligible']) && (int)$q2BeforeEdit['idRespuesta'] > 0,
    'M03_PREVIOUS_EXISTING_ANSWER_NOT_PROJECTED');
$assert(($editAdapter->previousResponseEditBlockerCode($beforeEdit, $q2BeforeEdit) ?? '') === '',
    'M03_PREVIOUS_FINALIZED_ANSWER_NOT_EDITABLE_BY_SEPARATE_REVIEW_POLICY');
$responseId = (int)$q2BeforeEdit['idRespuesta'];
$version = ConversationalM03Adapter::previousResponseVersion(9301, $q2BeforeEdit);
$edited = $editAdapter->editExistingResponse(9301, 2, $responseId, $version, '12 años');
$assert((int)($edited['resultado'] ?? 0) === 200 && ($edited['readback'] ?? false) === true
    && ($edited['answer'] ?? '') === '12 años' && (int)($edited['response_id'] ?? 0) === $responseId,
    'M03_PREVIOUS_UPDATE_OR_READBACK_FAILED');
$q2Edited = array_column($editAdapter->read(9301)['questions'], null, 'idPregunta')[2] ?? null;
$assert(is_array($q2Edited) && (int)$q2Edited['idRespuesta'] === $responseId && $q2Edited['respuesta'] === '12 años'
    && (int)$editDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=2')->fetchColumn() === 1,
    'M03_PREVIOUS_EDIT_DID_NOT_UPDATE_SAME_ROW');
$assert((int)$editDb->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_paciente=9301 AND id_modulo=3')->fetchColumn() === 1,
    'M03_PREVIOUS_EDIT_CHANGED_FINALIZATION');
$staleEdit = $editAdapter->editExistingResponse(9301, 2, $responseId, $version, '14 años');
$assert((int)($staleEdit['resultado'] ?? 0) === 409 && ($staleEdit['readback'] ?? false) === false,
    'M03_PREVIOUS_STALE_VERSION_NOT_REJECTED');
$crossPatientEdit = $editAdapter->editExistingResponse(9302, 2, $responseId, $version, '14 años');
$assert((int)($crossPatientEdit['resultado'] ?? 0) === 409, 'M03_PREVIOUS_CROSS_PATIENT_ROW_NOT_REJECTED');
$missingRowEdit = $editAdapter->editExistingResponse(9301, 3, 9999, str_repeat('a', 64), 'fixture value');
$assert((int)($missingRowEdit['resultado'] ?? 0) === 409
    && (int)$editDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301')->fetchColumn() === 2,
    'M03_PREVIOUS_MISSING_ROW_INSERTED_OR_ACCEPTED');

$unknownVersion = ConversationalM03Adapter::previousResponseVersion(9301, $q2Edited);
$unknownEdit = $editAdapter->editExistingResponse(9301, 2, $responseId, $unknownVersion, 'No lo sé');
$unknownRow = array_column($editAdapter->read(9301)['questions'], null, 'idPregunta')[2] ?? null;
$assert((int)($unknownEdit['resultado'] ?? 0) === 200 && ($unknownEdit['semantic_state'] ?? '') === 'UNKNOWN'
    && is_array($unknownRow) && $unknownRow['semanticState'] === 'UNKNOWN' && $unknownRow['respuesta'] === '',
    'M03_PREVIOUS_UNKNOWN_STATE_NOT_PRESERVED');
$notRememberedVersion = ConversationalM03Adapter::previousResponseVersion(9301, $unknownRow);
$notRememberedEdit = $editAdapter->editExistingResponse(9301, 2, $responseId, $notRememberedVersion, 'No recuerdo');
$notRememberedRow = array_column($editAdapter->read(9301)['questions'], null, 'idPregunta')[2] ?? null;
$assert((int)($notRememberedEdit['resultado'] ?? 0) === 200 && ($notRememberedEdit['semantic_state'] ?? '') === 'NOT_REMEMBERED'
    && is_array($notRememberedRow) && $notRememberedRow['semanticState'] === 'NOT_REMEMBERED'
    && (int)$notRememberedRow['idRespuesta'] === $responseId,
    'M03_PREVIOUS_NOT_REMEMBERED_COLLAPSED_OR_DUPLICATED');
$parentVersion = ConversationalM03Adapter::previousResponseVersion(9301, array_column($editAdapter->read(9301)['questions'], null, 'idPregunta')[1]);
$dependentNegative = $editAdapter->editExistingResponse(9301, 1, (int)$editRows[1]['idRespuesta'], $parentVersion, 'No');
$parentAfterRejectedNegative = array_column($editAdapter->read(9301)['questions'], null, 'idPregunta')[1] ?? null;
$assert((int)($dependentNegative['resultado'] ?? 0) === 409 && is_array($parentAfterRejectedNegative)
    && $parentAfterRejectedNegative['respuesta'] === 'Si'
    && (int)$editDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=2')->fetchColumn() === 1,
    'M03_PREVIOUS_PARENT_NEGATIVE_DELETED_OR_CONTRADICTED_DEPENDENT');

$legacyDb = $makeDb(false);
$legacyAdapter = new ConversationalM03Adapter($legacyDb);
$legacyDb->exec("INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta,id_paciente,respuesta) VALUES (1,9301,'Si'),(2,9301,'No recuerdo')");
$legacyBefore = array_column($legacyAdapter->read(9301)['questions'], null, 'idPregunta');
$legacyQ2 = $legacyBefore[2] ?? null;
$assert(is_array($legacyQ2) && $legacyQ2['semanticState'] === 'NOT_REMEMBERED' && $legacyQ2['eligible'], 'M03_PREVIOUS_LEGACY_UNKNOWN_NOT_PROJECTED');
$legacyId = (int)$legacyQ2['idRespuesta'];
$legacyVersion = ConversationalM03Adapter::previousResponseVersion(9301, $legacyQ2);
$legacyKnown = $legacyAdapter->editExistingResponse(9301, 2, $legacyId, $legacyVersion, '8 años');
$legacyAfterKnown = array_column($legacyAdapter->read(9301)['questions'], null, 'idPregunta')[2] ?? null;
$assert((int)($legacyKnown['resultado'] ?? 0) === 200 && ($legacyKnown['semantic_state'] ?? '') === 'KNOWN'
    && is_array($legacyAfterKnown) && $legacyAfterKnown['respuesta'] === '8 años' && (int)$legacyAfterKnown['idRespuesta'] === $legacyId,
    'M03_PREVIOUS_LEGACY_SCHEMA_EDIT_OR_READBACK_FAILED');
$legacyUnknownVersion = ConversationalM03Adapter::previousResponseVersion(9301, $legacyAfterKnown);
$legacyUnknown = $legacyAdapter->editExistingResponse(9301, 2, $legacyId, $legacyUnknownVersion, 'No lo sé');
$legacyUnknownRow = array_column($legacyAdapter->read(9301)['questions'], null, 'idPregunta')[2] ?? null;
$assert((int)($legacyUnknown['resultado'] ?? 0) === 200 && ($legacyUnknown['semantic_state'] ?? '') === 'UNKNOWN'
    && is_array($legacyUnknownRow) && $legacyUnknownRow['semanticState'] === 'UNKNOWN' && $legacyUnknownRow['respuesta'] === 'No lo sé',
    'M03_PREVIOUS_LEGACY_UNKNOWN_NOT_PERSISTED_TEXTUALLY');

$detailDb = $makeDb();
$detailAdapter = new ConversationalM03Adapter($detailDb);
$detailDb->exec("INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta,id_paciente,respuesta,estado_respuesta,origen_respuesta,estado_respuesta_at) VALUES (1,9301,'Si, fumo cinco cigarros al día','KNOWN','PATIENT_DECLARATION','2026-10-20 12:00:00')");
$detailQuestion = array_column($detailAdapter->read(9301)['questions'], null, 'idPregunta')[1] ?? null;
$detailEdit = $detailAdapter->editExistingResponse(9301, 1, (int)$detailQuestion['idRespuesta'], ConversationalM03Adapter::previousResponseVersion(9301, $detailQuestion), 'Sí, fumo tres cigarros al día', 'KNOWN');
$detailAfter = array_column($detailAdapter->read(9301)['questions'], null, 'idPregunta')[1] ?? null;
$assert((int)($detailEdit['resultado'] ?? 0) === 200 && ($detailEdit['answer'] ?? '') === 'Sí, fumo tres cigarros al día'
    && is_array($detailAfter) && $detailAfter['respuesta'] === 'Sí, fumo tres cigarros al día'
    && (int)$detailAfter['idRespuesta'] === (int)$detailQuestion['idRespuesta'],
    'M03_PREVIOUS_DETAILED_TOBACCO_AFFIRMATIVE_WAS_COLLAPSED_TO_BOOLEAN');

$duplicateDb = $makeDb();
$duplicateAdapter = new ConversationalM03Adapter($duplicateDb);
$duplicateDb->exec("INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta,id_paciente,respuesta,estado_respuesta,origen_respuesta,estado_respuesta_at) VALUES (1,9301,'Si','KNOWN','PATIENT_DECLARATION','2026-10-20 12:00:00'),(2,9301,'10 años','KNOWN','PATIENT_DECLARATION','2026-10-20 12:00:01'),(2,9301,'11 años','KNOWN','PATIENT_DECLARATION','2026-10-20 12:00:02')");
$duplicateState = $duplicateAdapter->read(9301);
$duplicateQuestion = array_values(array_filter($duplicateState['questions'], static fn(array $row): bool => (int)$row['idPregunta'] === 2))[0] ?? null;
$assert(is_array($duplicateQuestion) && ($duplicateAdapter->previousResponseEditBlockerCode($duplicateState, $duplicateQuestion) ?? '') === 'M03_PREVIOUS_DUPLICATE_ROWS',
    'M03_PREVIOUS_DUPLICATE_ROWS_NOT_BLOCKED');

$controllerSource = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/PatientInterviewController.php');
$adapterSource = file_get_contents(dirname(__DIR__, 2) . '/app/Services/ConversationalM03Adapter.php');
$modelSource = file_get_contents(dirname(__DIR__, 2) . '/app/Models/AntecedentesNoPatologicosModel.php');
$routesSource = file_get_contents(dirname(__DIR__, 2) . '/app/Config/routes.php');
$viewSource = file_get_contents(dirname(__DIR__, 2) . '/app/views/paciente/respuestas-previas.php');
$assert(str_contains($routesSource, "'/respuestas-previas/m03', ['PatientInterviewController', 'updatePreviousM03']"),
    'M03_PREVIOUS_UPDATE_ROUTE_MISSING');
$m03EndpointStart = strpos($controllerSource, 'public function updatePreviousM03(): void');
$m03EndpointEnd = strpos($controllerSource, 'private function formatM02CurrentAnswer', $m03EndpointStart ?: 0);
$assert($m03EndpointStart !== false && $m03EndpointEnd !== false, 'M03_PREVIOUS_ENDPOINT_MISSING');
$m03Endpoint = substr($controllerSource, $m03EndpointStart, $m03EndpointEnd - $m03EndpointStart);
$assert(str_contains($m03Endpoint, "new AuthMiddleware('historia-clinica')")
    && str_contains($m03Endpoint, 'assertPatientAccess($patientId, $auth)')
    && str_contains($m03Endpoint, 'validCaptureCsrf($patientId')
    && str_contains($m03Endpoint, "\$_SERVER['HTTP_X_M03_CSRF']")
    && !str_contains($m03Endpoint, "\$data['idPaciente']")
    && !str_contains($m03Endpoint, 'finalizeAfterCapture'),
    'M03_PREVIOUS_ENDPOINT_AUTHORIZATION_OR_FINALIZATION_CONTRACT_INVALID');
$adapterEditStart = strpos($adapterSource, 'public function editExistingResponse(');
$adapterEditEnd = strpos($adapterSource, 'public static function formatPreviousResponse', $adapterEditStart ?: 0);
$assert($adapterEditStart !== false && $adapterEditEnd !== false, 'M03_PREVIOUS_UPDATE_ONLY_ADAPTER_MISSING');
$adapterEditBody = substr($adapterSource, $adapterEditStart, $adapterEditEnd - $adapterEditStart);
$assert(str_contains($adapterEditBody, 'previousResponseVersion')
    && str_contains($adapterEditBody, 'actualSemantic !== $semanticState')
    && str_contains($adapterEditBody, 'actualSemantic')
    && !preg_match('/\bINSERT\s+INTO\s+pac_respuestas_paciente_modulo_3\b/i', $adapterEditBody),
    'M03_PREVIOUS_ADAPTER_VERSION_READBACK_OR_UPDATE_ONLY_CONTRACT_INVALID');
$modelEditStart = strpos($modelSource, 'public function actualizarRespuestaM03Existente(');
$modelEditEnd = strpos($modelSource, 'private function persistSemanticResponse(', $modelEditStart ?: 0);
$assert($modelEditStart !== false && $modelEditEnd !== false, 'M03_PREVIOUS_UPDATE_ONLY_MODEL_MISSING');
$modelEditBody = substr($modelSource, $modelEditStart, $modelEditEnd - $modelEditStart);
$assert(str_contains($modelEditBody, 'beginTransaction()')
    && str_contains($modelEditBody, 'M03_DEPENDENCY_REVIEW_REQUIRED')
    && str_contains($modelEditBody, 'M03_PREVIOUS_READBACK_FAILED')
    && str_contains($modelEditBody, 'UPDATE pac_respuestas_paciente_modulo_3 SET respuesta=:detail')
    && !preg_match('/\bINSERT\s+INTO\s+pac_respuestas_paciente_modulo_3\b/i', $modelEditBody)
    && !str_contains($modelEditBody, 'DELETE FROM')
    && !str_contains($modelEditBody, 'pac_historia_clinica_finalizar'),
    'M03_PREVIOUS_MODEL_MUST_UPDATE_ONE_ROW_WITHOUT_INSERT_DELETE_OR_FINALIZATION');

foreach (['M03 · Antecedentes personales no patológicos', 'data-m03-review-card', 'Guardar cambios', 'Cancelar', 'data-m03-semantic-state', 'No lo sé', 'No recuerdo', 'Respuesta actualizada.'] as $required) {
    $assert(str_contains($viewSource, $required), 'M03_PREVIOUS_VIEW_CONTRACT_MISSING:' . $required);
}
$assert(str_contains($viewSource, 'credentials: \'same-origin\'')
    && str_contains($viewSource, "'X-M03-CSRF': csrf")
    && str_contains($viewSource, 'result.readback !== true')
    && str_contains($viewSource, 'response.status === 409'),
    'M03_PREVIOUS_BROWSER_EDIT_MUST_USE_SESSION_CSRF_READBACK_AND_CONFLICT_HANDLING');

foreach (['RUTA_CSS', 'RUTA_JS', 'RUTA_PUBLIC', 'RUTA_IMAGES', 'SERVIDOR'] as $constant) {
    if (!defined($constant)) define($constant, '/assets/');
}
$testThemes = [];
foreach ($catalog['themes'] as $theme) $testThemes[] = ['theme_id' => $theme['id'], 'theme' => $theme['tema'], 'entries' => [], 'conflicts' => []];
$testThemes[0]['entries'][] = [
    'number' => 1, 'question_id' => 1, 'stable_id' => 'M03:question:1', 'question' => '¿Usted fuma cigarros de nicotina?',
    'type' => 'select', 'editor_control' => 'text', 'answer' => 'Sí, fumo cinco cigarros al día', 'answer_input' => 'Sí, fumo cinco cigarros al día',
    'semantic_state' => 'KNOWN', 'response_id' => 76, 'version' => str_repeat('e', 64), 'editable' => true, 'edit_blocker' => '',
];
$testThemes[0]['entries'][] = [
    'number' => 2, 'question_id' => 2, 'stable_id' => 'M03:question:2', 'question' => '¿Desde qué edad empezó a fumar?',
    'type' => 'input', 'editor_control' => 'text', 'answer' => 'No lo sé', 'answer_input' => '', 'semantic_state' => 'UNKNOWN',
    'response_id' => 77, 'version' => str_repeat('d', 64), 'editable' => true, 'edit_blocker' => '',
];
$data = ['title' => 'Respuestas previas', 'csrf' => 'synthetic-only', 'sidebar' => '<nav></nav>', 'm02Finalized' => true,
    'entries' => [], 'm03Finalized' => true, 'm03EditUrl' => '/historia-clinica/respuestas-previas/m03', 'm03Themes' => $testThemes];
ob_start();
require dirname(__DIR__, 2) . '/app/views/paciente/respuestas-previas.php';
$renderedPrevious = (string)ob_get_clean();
$assert(!str_contains($renderedPrevious, 'class="LoaderPage"')
    && !str_contains($renderedPrevious, 'loader.js')
    && !str_contains($renderedPrevious, 'code.jquery.com/jquery'),
    'PREVIOUS_RESPONSES_BLOCKING_LOADER_OR_EXTERNAL_JQUERY_REMAINS');
$assert(str_contains($renderedPrevious, 'app.js')
    && str_contains($renderedPrevious, 'patient-previous-responses-filters.js'),
    'PREVIOUS_RESPONSES_REQUIRED_LOCAL_SCRIPTS_MISSING');
$assert(substr_count($renderedPrevious, 'class="previous-responses-theme-title"') === 7, 'M03_PREVIOUS_RENDER_THEME_COUNT_INVALID');
$assert(substr_count($renderedPrevious, 'data-m03-review-card data-stable-id=') === 2, 'M03_PREVIOUS_RENDER_CARD_COUNT_INVALID');
$assert(str_contains($renderedPrevious, 'data-edit-state="UNKNOWN"'), 'M03_PREVIOUS_RENDER_UNKNOWN_STATE_NOT_PRELOADED');
$assert(str_contains($renderedPrevious, 'data-edit-value="Sí, fumo cinco cigarros al día"')
    && str_contains($renderedPrevious, '<textarea class="form-control" id="m03-previous-1-input"'),
    'M03_PREVIOUS_DETAILED_TOBACCO_AFFIRMATIVE_WAS_NOT_PRELOADED_FOR_SAFE_EDIT');
$assert(str_contains($renderedPrevious, 'M03 está finalizado.'), 'M03_PREVIOUS_FINALIZATION_NOTICE_MISSING');
$assert(str_contains($renderedPrevious, 'No lo sé'), 'M03_PREVIOUS_UNKNOWN_LABEL_MISSING');

$data['m03Themes'] = [[
    'theme_id' => 1,
    'theme' => 'Tabaquismo',
    'entries' => [],
    'conflicts' => [],
    'status_items' => [[
        'number' => 1,
        'question_id' => 1,
        'stable_id' => 'M03:question:1',
        'question' => '¿Usted fuma cigarros de nicotina?',
        'status' => 'PENDIENTE',
        'pending' => true,
        'reason' => '',
    ]],
]];
ob_start();
require dirname(__DIR__, 2) . '/app/views/paciente/respuestas-previas.php';
$renderedEmptyTheme = (string)ob_get_clean();
$assert(str_contains($renderedEmptyTheme, 'Aún no hay respuestas registradas en este tema.')
    && str_contains($renderedEmptyTheme, 'data-review-status="PENDIENTE"')
    && !str_contains($renderedEmptyTheme, 'Respuesta registrada'),
    'M03_EMPTY_THEME_MUST_DISTINGUISH_PENDING_QUESTION_FROM_REGISTERED_ANSWER');

// Finalization's readiness predicate is exercised without invoking the
// MySQL-specific persisted finalizer (GET_LOCK); this is L1 fixture evidence.
$service = new ConversationalModuleFinalizationService(new PDO('sqlite::memory:'));
$isComplete = new ReflectionMethod($service, 'm03Complete');
$isComplete->setAccessible(true);
$pending = ['questions' => [['eligible' => true, 'respuesta' => '', 'semanticState' => 'UNANSWERED']]];
$unknownResolved = ['questions' => [['eligible' => true, 'respuesta' => '', 'semanticState' => 'UNKNOWN']]];
$notRememberedResolved = ['questions' => [['eligible' => true, 'respuesta' => '', 'semanticState' => 'NOT_REMEMBERED']]];
$skippedDependency = ['questions' => [['eligible' => false, 'respuesta' => '', 'semanticState' => 'UNANSWERED']]];
$assert($isComplete->invoke($service, $pending) === false, 'FINALIZATION_ALLOWED_WITH_PENDING_ANSWER');
$assert($isComplete->invoke($service, $unknownResolved) === true && $isComplete->invoke($service, $notRememberedResolved) === true, 'EXPLICIT_UNCERTAINTY_NOT_ACCEPTED_AS_RESOLVED');
$assert($isComplete->invoke($service, $skippedDependency) === true, 'INELIGIBLE_DEPENDENCY_BLOCKED_FINALIZATION');

echo "M03 seven-theme functional contract: PASS (catalog, all five conditional positive/negative routes, readback, stale replay, patient isolation, and finalization readiness; SQLite fixtures only)\n";
