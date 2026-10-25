<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Models\AntecedentesNoPatologicosModel;
use App\Services\ConversationalInterviewService;
use App\Services\ConversationalM03Adapter;
use App\Services\M03TobaccoResponsePolicy;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$schema = static function (string $responseType = 'TEXT', bool $semantic = false): PDO {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE pc_paciente (id INTEGER PRIMARY KEY, id_clinica INTEGER NOT NULL)');
    $db->exec('INSERT INTO pc_paciente (id, id_clinica) VALUES (9301, 9101), (9302, 9102)');
    $db->exec('CREATE TABLE pac_temas_modulo_3 (id INTEGER PRIMARY KEY, tema TEXT NOT NULL)');
    $themes = require dirname(__DIR__) . '/fixtures/p05-catalog.php';
    $insertTheme = $db->prepare('INSERT INTO pac_temas_modulo_3 (id, tema) VALUES (?, ?)');
    foreach ($themes['themes'] as $theme) $insertTheme->execute([(int)$theme['id'], (string)$theme['tema']]);
    $db->exec('CREATE TABLE pac_preguntas_modulo_3 (id INTEGER PRIMARY KEY, id_tema INTEGER NOT NULL, pregunta TEXT NOT NULL, tipo TEXT NOT NULL)');
    $insertQuestion = $db->prepare('INSERT INTO pac_preguntas_modulo_3 (id, id_tema, pregunta, tipo) VALUES (?, ?, ?, ?)');
    foreach ($themes['questions'] as $question) $insertQuestion->execute([(int)$question['id'], (int)$question['id_tema'], (string)$question['pregunta'], (string)$question['tipo']]);
    $db->exec("CREATE TABLE pac_respuestas_paciente_modulo_3 (id INTEGER PRIMARY KEY AUTOINCREMENT, id_pregunta INTEGER NOT NULL, id_paciente INTEGER NOT NULL, respuesta {$responseType} NOT NULL)");
    if ($semantic) {
        $db->exec('ALTER TABLE pac_respuestas_paciente_modulo_3 ADD COLUMN estado_respuesta VARCHAR(24) NULL');
        $db->exec('ALTER TABLE pac_respuestas_paciente_modulo_3 ADD COLUMN origen_respuesta VARCHAR(32) NULL');
        $db->exec('ALTER TABLE pac_respuestas_paciente_modulo_3 ADD COLUMN estado_respuesta_at DATETIME NULL');
    }
    $db->exec('CREATE TABLE pac_historia_clinica_finalizar (id INTEGER PRIMARY KEY AUTOINCREMENT, id_paciente INTEGER NOT NULL, id_modulo INTEGER NOT NULL)');
    return $db;
};

$question = ['idPregunta' => 1, 'idTema' => 1, 'tipo' => 'select'];
foreach ([
    'Sí' => M03TobaccoResponsePolicy::AFFIRMATIVE,
    'Si' => M03TobaccoResponsePolicy::AFFIRMATIVE,
    'Sí, fumo cinco cigarros al día' => M03TobaccoResponsePolicy::AFFIRMATIVE,
    'Sí, fumo ocasionalmente' => M03TobaccoResponsePolicy::AFFIRMATIVE,
    'No' => M03TobaccoResponsePolicy::NEGATIVE,
    'No, no fumo' => M03TobaccoResponsePolicy::NEGATIVE,
    'No, nunca he fumado' => M03TobaccoResponsePolicy::NEGATIVE,
    'No lo sé' => M03TobaccoResponsePolicy::UNKNOWN,
    'No recuerdo' => M03TobaccoResponsePolicy::NOT_REMEMBERED,
    'Sí y no' => M03TobaccoResponsePolicy::AMBIGUOUS,
    'A veces, pero ya no' => M03TobaccoResponsePolicy::AMBIGUOUS,
    'No estoy seguro' => M03TobaccoResponsePolicy::AMBIGUOUS,
    'Consumo tabaco' => M03TobaccoResponsePolicy::AMBIGUOUS,
    'Tengo el hábito' => M03TobaccoResponsePolicy::AMBIGUOUS,
] as $utterance => $expectedDecision) {
    $assert(M03TobaccoResponsePolicy::classify($utterance) === $expectedDecision, 'CLASSIFICATION_FAILED:' . $utterance);
}
$assert(M03TobaccoResponsePolicy::storedValue('Sí') === 'Si', 'SIMPLE_YES_NOT_CANONICALIZED');
$assert(M03TobaccoResponsePolicy::storedValue('Sí, fumo cinco cigarros al día') === 'Sí, fumo cinco cigarros al día', 'AFFIRMATIVE_DETAIL_NOT_PRESERVED');
$assert(M03TobaccoResponsePolicy::storedValue('No, nunca he fumado') === 'No', 'NEGATIVE_NOT_CANONICALIZED');
$assert(!M03TobaccoResponsePolicy::isQuestion(['idPregunta' => 6, 'idTema' => 2, 'tipo' => 'select']), 'POLICY_LEAKED_TO_OTHER_M03_THEME');

$db = $schema('TEXT', false);
$model = new AntecedentesNoPatologicosModel($db);
$service = new ConversationalInterviewService($model);
$positiveProposal = $service->interpret(9301, 1, 'Sí, fumo cinco cigarros al día');
$assert(($positiveProposal['structured_value'] ?? '') === 'Sí, fumo cinco cigarros al día', 'SERVICE_DROPPED_AFFIRMATIVE_DETAILS');
$assert(($positiveProposal['semantic_state'] ?? '') === 'KNOWN' && ($positiveProposal['navigation_decision'] ?? '') === 'AFFIRMATIVE', 'SERVICE_CANONICAL_DECISION_MISSING');
$ambiguousRowsBefore = (int)$db->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3')->fetchColumn();
$ambiguousProposal = $service->interpret(9301, 1, 'siiii');
$assert(($ambiguousProposal['kind'] ?? '') === 'clarification'
    && ($ambiguousProposal['error_code'] ?? '') === 'M03_TOBACCO_ANSWER_AMBIGUOUS'
    && ($ambiguousProposal['clarification'] ?? '') === 'No pude identificar tu respuesta. Para continuar, dime claramente si fumas cigarros de nicotina. Puedes responder: sí, fumo; no, no fumo; no lo sé o no lo recuerdo. Tu respuesta no se guardó. Puedes intentarlo nuevamente.'
    && ($ambiguousProposal['direct_save'] ?? true) === false
    && ($ambiguousProposal['can_confirm'] ?? true) === false,
    'AMBIGUOUS_Q1_RESPONSE_MUST_REMAIN_REJECTED_WITH_SCOPED_GUIDANCE');
$assert((int)$db->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3')->fetchColumn() === $ambiguousRowsBefore,
    'AMBIGUOUS_Q1_RESPONSE_MUST_NOT_WRITE');
$assert(!isset($positiveProposal['error_code']), 'VALID_AFFIRMATIVE_RESPONSE_MUST_NOT_TRIGGER_INVALID_GUIDANCE');

$adapter = new ConversationalM03Adapter($db);
$before = $adapter->read(9301);
$positive = $adapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 1, 'theme_id' => 1,
    'structured_value' => 'Sí, fumo cinco cigarros al día', 'semantic_state' => 'KNOWN',
], $before['version']);
$assert(($positive['resultado'] ?? 0) === 200 && ($positive['readback'] ?? false) === true, 'POSITIVE_CAPTURE_OR_READBACK_FAILED');
$assert((int)($positive['response_id'] ?? 0) > 0, 'POSITIVE_CAPTURE_RESPONSE_ID_MISSING');
$assert(($positive['navigation_decision'] ?? '') === 'AFFIRMATIVE', 'AFFIRMATIVE_NAVIGATION_DECISION_NOT_RETURNED');
$assert((int)($positive['next_question_id'] ?? 0) === 2, 'AFFIRMATIVE_DID_NOT_SELECT_QUESTION_2');
$assert(in_array(2, $positive['eligible_question_ids'] ?? [], true), 'AFFIRMATIVE_DID_NOT_ENABLE_DEPENDENTS_FROM_SERVER');

$directDb = $schema('TEXT', false);
$directAdapter = new ConversationalM03Adapter($directDb);
$directStart = $directAdapter->read(9301);
$directParent = $directAdapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 1, 'theme_id' => 1,
    'structured_value' => 'Si', 'semantic_state' => 'KNOWN',
], $directStart['version']);
$directAge = $directAdapter->captureDirectNumeric(9301, 2, '42', (string)($directParent['version'] ?? ''));
$assert(($directAge['resultado'] ?? 0) === 200
    && ($directAge['readback'] ?? false) === true
    && ($directAge['readback_value'] ?? null) === '42',
    'DIRECT_CAPTURE_DID_NOT_RETURN_THE_EXACT_INDEPENDENT_READBACK_VALUE');
$assert((int)$directDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=2')->fetchColumn() === 1,
    'DIRECT_CAPTURE_READBACK_FIX_CREATED_DUPLICATE_RESPONSE');

$afterPositive = $adapter->read(9301);
$q1 = array_values(array_filter($afterPositive['questions'], static fn(array $row): bool => (int)$row['idPregunta'] === 1))[0] ?? [];
$assert(($q1['respuesta'] ?? '') === 'Sí, fumo cinco cigarros al día', 'AFFIRMATIVE_FULL_TEXT_READBACK_FAILED');
$assert(($q1['navigationDecision'] ?? '') === M03TobaccoResponsePolicy::AFFIRMATIVE, 'AFFIRMATIVE_READ_DECISION_FAILED');
$rowCountQ1 = (int)$db->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=1')->fetchColumn();
$staleReplay = $adapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 1, 'theme_id' => 1,
    'structured_value' => 'Sí, fumo cinco cigarros al día', 'semantic_state' => 'KNOWN',
], $before['version']);
$assert(($staleReplay['resultado'] ?? 0) === 409, 'STALE_DOUBLE_SUBMIT_NOT_REJECTED');
$fresh = $adapter->read(9301);
$freshQ1 = array_values(array_filter($fresh['questions'], static fn(array $row): bool => (int)$row['idPregunta'] === 1))[0] ?? [];
$freshResponseId = (int)($freshQ1['idRespuesta'] ?? 0);
$missingResponseIdUpdate = $adapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 1, 'theme_id' => 1,
    'structured_value' => 'Sí, fumo ocasionalmente', 'semantic_state' => 'KNOWN',
], $fresh['version']);
$assert(($missingResponseIdUpdate['resultado'] ?? 0) === 409, 'PREVIOUS_EDIT_WITHOUT_RESPONSE_ID_NOT_REJECTED');
$wrongResponseIdUpdate = $adapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 1, 'theme_id' => 1,
    'structured_value' => 'Sí, fumo ocasionalmente', 'semantic_state' => 'KNOWN',
], $fresh['version'], $freshResponseId + 999);
$assert(($wrongResponseIdUpdate['resultado'] ?? 0) === 409, 'PREVIOUS_EDIT_WITH_WRONG_RESPONSE_ID_NOT_REJECTED');
$assert((string)$db->query('SELECT respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=1')->fetchColumn() === 'Sí, fumo cinco cigarros al día', 'REJECTED_RESPONSE_ID_EDIT_CHANGED_ANSWER');
$modelResponseIdConflict = (new AntecedentesNoPatologicosModel($db))->capturarRespuestaEntrevista([
    'idPaciente' => 9301, 'idPregunta' => 1, 'idTema' => 1,
    'detalle' => 'Sí, fumo ocasionalmente',
    'estadoRespuesta' => 'KNOWN', 'origenRespuesta' => 'PATIENT_DECLARATION',
    'expectedRespuesta' => 'Sí, fumo cinco cigarros al día', 'expectedResponseId' => $freshResponseId + 100,
]);
$modelResponseAfterConflict = (string)$db->query('SELECT respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=1')->fetchColumn();
$modelResponseIdGuardPassed = ($modelResponseIdConflict['resultado'] ?? 0) === 409
    && $modelResponseAfterConflict === 'Sí, fumo cinco cigarros al día';
$assert($modelResponseIdGuardPassed, 'MODEL_RESPONSE_ID_GUARD_DID_NOT_PRESERVE_EXISTING_ROW:'
    . (string)($modelResponseIdConflict['resultado'] ?? 'missing') . ':' . $modelResponseAfterConflict);
$updated = $adapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 1, 'theme_id' => 1,
    'structured_value' => 'Sí, fumo ocasionalmente', 'semantic_state' => 'KNOWN',
], $fresh['version'], $freshResponseId);
$assert(($updated['resultado'] ?? 0) === 200, 'SAME_RECORD_UPDATE_FAILED');
$assert((int)($updated['response_id'] ?? 0) === $freshResponseId, 'EDIT_READBACK_CHANGED_RESPONSE_ID');
$assert(($updated['readback'] ?? false) === true && ($updated['readback_value'] ?? '') === 'Sí, fumo ocasionalmente', 'EDIT_READBACK_VALUE_FAILED');
$assert((int)$db->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=1')->fetchColumn() === $rowCountQ1, 'DUPLICATE_RESPONSE_ROW_CREATED');
$storedUpdate = (string)$db->query('SELECT respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=1')->fetchColumn();
$assert($storedUpdate === 'Sí, fumo ocasionalmente', 'UPDATED_RESPONSE_NOT_READ_BACK');
$q3Insert = $db->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta, id_paciente, respuesta) VALUES (3, 9301, ?)');
$q3Insert->execute(['respuesta sintética no editable mientras Q2 sigue pendiente']);
$beforeUnauthorizedQ3 = $adapter->read(9301);
$q3Response = array_values(array_filter($beforeUnauthorizedQ3['questions'], static fn(array $row): bool => (int)$row['idPregunta'] === 3))[0] ?? [];
$unauthorizedNonAdjacentEdit = $adapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 3, 'theme_id' => 1,
    'structured_value' => 'intento de edición no autorizado', 'semantic_state' => 'KNOWN',
], $beforeUnauthorizedQ3['version'], (int)($q3Response['idRespuesta'] ?? 0));
$assert(($unauthorizedNonAdjacentEdit['resultado'] ?? 0) === 409, 'NON_ADJACENT_SAVED_QUESTION_EDIT_WAS_AUTHORIZED');
$assert((string)$db->query('SELECT respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=3')->fetchColumn() === 'respuesta sintética no editable mientras Q2 sigue pendiente', 'UNAUTHORIZED_NON_ADJACENT_EDIT_CHANGED_DATA');

// A complete, contiguous eligible history before the first pending question
// is editable by identity/version, not only the immediately preceding row.
$historyDb = $schema('TEXT', true);
$historyDb->exec("INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta,id_paciente,respuesta,estado_respuesta,origen_respuesta) VALUES
    (1,9301,'Sí','KNOWN','PATIENT_DECLARATION'),
    (2,9301,'42','KNOWN','PATIENT_DECLARATION'),
    (3,9301,'10 años','KNOWN','PATIENT_DECLARATION')");
$historyAdapter = new ConversationalM03Adapter($historyDb);
$historyBefore = $historyAdapter->read(9301);
$historyQ3 = array_values(array_filter($historyBefore['questions'], static fn(array $row): bool => (int)$row['idPregunta'] === 3))[0] ?? [];
$historyQ4 = array_values(array_filter($historyBefore['questions'], static fn(array $row): bool => (int)$row['idPregunta'] === 4))[0] ?? [];
$assert(($historyQ3['historicalEditable'] ?? false) === true
    && ($historyQ4['eligible'] ?? false) === true
    && trim((string)($historyQ4['respuesta'] ?? '')) === '',
    'CONTIGUOUS_HISTORICAL_QUESTION_NOT_AUTHORIZED_BEFORE_FIRST_PENDING');
$historyResponseId = (int)($historyQ3['idRespuesta'] ?? 0);
$historyRowCountBefore = (int)$historyDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=3')->fetchColumn();
$historyEdit = $historyAdapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 3, 'theme_id' => 1,
    'structured_value' => '12 años', 'semantic_state' => 'KNOWN',
], (string)$historyBefore['version'], $historyResponseId);
$assert(($historyEdit['resultado'] ?? 0) === 200
    && ($historyEdit['readback'] ?? false) === true
    && ($historyEdit['readback_value'] ?? '') === '12 años'
    && (int)($historyEdit['response_id'] ?? 0) === $historyResponseId,
    'HISTORICAL_TEN_TO_TWELVE_EDIT_OR_SAME_ROW_READBACK_FAILED');
$assert((int)$historyDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=3')->fetchColumn() === $historyRowCountBefore
    && (string)$historyDb->query('SELECT respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=3')->fetchColumn() === '12 años',
    'HISTORICAL_EDIT_CREATED_DUPLICATE_OR_FAILED_TO_UPDATE_EXISTING_ROW');
$historyAfterEdit = $historyAdapter->read(9301);
$historyQ3After = array_values(array_filter($historyAfterEdit['questions'], static fn(array $row): bool => (int)$row['idPregunta'] === 3))[0] ?? [];
$changesBeforeNoop = (int)$historyDb->query('SELECT total_changes()')->fetchColumn();
$historyNoop = $historyAdapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 3, 'theme_id' => 1,
    'structured_value' => '12 años', 'semantic_state' => 'KNOWN',
], (string)$historyAfterEdit['version'], (int)$historyQ3After['idRespuesta']);
$changesAfterNoop = (int)$historyDb->query('SELECT total_changes()')->fetchColumn();
$assert(($historyNoop['resultado'] ?? 0) === 200
    && ($historyNoop['readback'] ?? false) === true
    && ($historyNoop['no_op'] ?? false) === true
    && (int)($historyNoop['next_question_id'] ?? 0) === 4
    && $changesAfterNoop === $changesBeforeNoop,
    'UNCHANGED_HISTORICAL_ANSWER_MUST_READ_BACK_WITHOUT_UPDATE_AND_RETURN_NEXT_PENDING');
$changesBeforeStale = (int)$historyDb->query('SELECT total_changes()')->fetchColumn();
$staleHistoryEdit = $historyAdapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 3, 'theme_id' => 1,
    'structured_value' => '13 años', 'semantic_state' => 'KNOWN',
], (string)$historyBefore['version'], $historyResponseId);
$assert(($staleHistoryEdit['resultado'] ?? 0) === 409
    && (int)$historyDb->query('SELECT total_changes()')->fetchColumn() === $changesBeforeStale,
    'STALE_HISTORICAL_EDIT_MUST_BE_REJECTED_WITHOUT_WRITE');
$crossPatientHistoryEdit = $historyAdapter->capture(9302, [
    'kind' => 'm03_proposal', 'question_id' => 3, 'theme_id' => 1,
    'structured_value' => '12 años', 'semantic_state' => 'KNOWN',
], (string)$historyAfterEdit['version'], $historyResponseId);
$assert(($crossPatientHistoryEdit['resultado'] ?? 0) === 409
    && (int)$historyDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9302')->fetchColumn() === 0,
    'HISTORICAL_EDIT_RESPONSE_ID_CROSSED_PATIENT_BOUNDARY');

$contextualDb = $schema('TEXT', true);
$contextualAdapter = new ConversationalM03Adapter($contextualDb);
$contextualBefore = $contextualAdapter->read(9301);
$contextualQuestions = array_column($contextualBefore['questions'], null, 'idPregunta');
$assert(($contextualQuestions[1]['pregunta'] ?? '') === '¿Usted fuma cigarros de nicotina?'
    && ($contextualQuestions[1]['contextualAction']['label'] ?? '') === 'No, guardar y continuar'
    && ($contextualQuestions[1]['contextualAction']['semantic_state'] ?? '') === 'NEGATIVE', 'TOBACCO_CONTEXTUAL_ACTION_CONTRACT_MISSING');
$assert(($contextualQuestions[2]['pregunta'] ?? '') === '¿Desde qué edad empezó a fumar?'
    && ($contextualQuestions[2]['contextualAction']['label'] ?? '') === 'No recuerdo, guardar y continuar'
    && ($contextualQuestions[2]['contextualAction']['semantic_state'] ?? '') === 'NOT_REMEMBERED'
    && array_key_exists('structured_value', $contextualQuestions[2]['contextualAction'])
    && $contextualQuestions[2]['contextualAction']['structured_value'] === null, 'AGE_NOT_REMEMBERED_CONTEXTUAL_ACTION_CONTRACT_INVALID');
$prematureContextual = $contextualAdapter->captureContextualAction(9301, 2, 1, 'M03_TOBACCO_AGE_NOT_REMEMBERED', $contextualBefore['version']);
$assert(($prematureContextual['resultado'] ?? 0) === 422
    && (int)$contextualDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3')->fetchColumn() === 0, 'INELIGIBLE_AGE_ACTION_WROTE_DATA');
$contextualYes = $contextualAdapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 1, 'theme_id' => 1,
    'structured_value' => 'Sí', 'semantic_state' => 'KNOWN',
], $contextualBefore['version']);
$assert(($contextualYes['resultado'] ?? 0) === 200 && (int)($contextualYes['next_question_id'] ?? 0) === 2, 'CONTEXTUAL_TEST_DID_NOT_REACH_AGE_QUESTION');
$beforeNotRemembered = $contextualAdapter->read(9301);
$forgedContextual = $contextualAdapter->captureContextualAction(9301, 2, 1, 'M03_TOBACCO_NEGATIVE', $beforeNotRemembered['version']);
$assert(($forgedContextual['resultado'] ?? 0) === 422
    && (int)$contextualDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=2')->fetchColumn() === 0, 'WRONG_CONTEXTUAL_ACTION_KEY_WROTE_DATA');
$notRemembered = $contextualAdapter->captureContextualAction(9301, 2, 1, 'M03_TOBACCO_AGE_NOT_REMEMBERED', $beforeNotRemembered['version']);
$assert(($notRemembered['resultado'] ?? 0) === 200 && ($notRemembered['readback'] ?? false) === true, 'AGE_NOT_REMEMBERED_CAPTURE_OR_READBACK_FAILED');
$assert(($notRemembered['semantic_state'] ?? '') === 'NOT_REMEMBERED'
    && ($notRemembered['readback_value'] ?? 'unexpected') === '', 'AGE_NOT_REMEMBERED_SEMANTIC_READBACK_INVALID');
$assert((int)($notRemembered['next_question_id'] ?? 0) === 3, 'AGE_NOT_REMEMBERED_DID_NOT_ADVANCE_TO_SERVER_NEXT_QUESTION');
$notRememberedRow = $contextualDb->query('SELECT respuesta,estado_respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=2')->fetch(PDO::FETCH_ASSOC);
$assert(($notRememberedRow['respuesta'] ?? 'unexpected') === '' && ($notRememberedRow['estado_respuesta'] ?? '') === 'NOT_REMEMBERED', 'AGE_NOT_REMEMBERED_STORED_AS_AGE_OR_WRONG_STATE');
$contextualAfter = $contextualAdapter->read(9301);
$q2After = array_values(array_filter($contextualAfter['questions'], static fn(array $row): bool => (int)$row['idPregunta'] === 2))[0] ?? [];
$assert(($q2After['semanticState'] ?? '') === 'NOT_REMEMBERED' && ($q2After['eligible'] ?? false) === true, 'AGE_NOT_REMEMBERED_NOT_RESOLVED_ON_READ');
$assert((int)$contextualDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=2')->fetchColumn() === 1, 'AGE_NOT_REMEMBERED_DUPLICATED_RESPONSE');
$assert((string)$contextualDb->query('SELECT respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=1')->fetchColumn() === 'Si', 'AGE_ACTION_CHANGED_THE_PRIOR_TOBACCO_AFFIRMATION');
$assert((int)$contextualDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9302')->fetchColumn() === 0, 'AGE_NOT_REMEMBERED_CROSSED_PATIENT_BOUNDARY');
$staleNotRemembered = $contextualAdapter->captureContextualAction(9301, 2, 1, 'M03_TOBACCO_AGE_NOT_REMEMBERED', $beforeNotRemembered['version']);
$assert(($staleNotRemembered['resultado'] ?? 0) === 409, 'AGE_NOT_REMEMBERED_STALE_REPLAY_NOT_REJECTED');

$legacyContextualDb = $schema('TEXT', false);
$legacyContextualAdapter = new ConversationalM03Adapter($legacyContextualDb);
$legacyBefore = $legacyContextualAdapter->read(9301);
$legacyContextualAdapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 1, 'theme_id' => 1,
    'structured_value' => 'Sí', 'semantic_state' => 'KNOWN',
], $legacyBefore['version']);
$legacyAge = $legacyContextualAdapter->read(9301);
$legacyAgeResult = $legacyContextualAdapter->captureContextualAction(9301, 2, 1, 'M03_TOBACCO_AGE_NOT_REMEMBERED', $legacyAge['version']);
$assert(($legacyAgeResult['resultado'] ?? 0) === 200 && ($legacyAgeResult['readback'] ?? false) === true
    && ($legacyAgeResult['readback_value'] ?? '') === 'No recuerdo', 'LEGACY_SCHEMA_NOT_REMEMBERED_FALLBACK_FAILED');
$assert((string)$legacyContextualDb->query('SELECT respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=2')->fetchColumn() === 'No recuerdo', 'LEGACY_FALLBACK_DID_NOT_STORE_CANONICAL_STATUS');
$assert((int)($legacyAgeResult['next_question_id'] ?? 0) === 3, 'LEGACY_FALLBACK_DID_NOT_ADVANCE');

$simpleYesDb = $schema('TEXT', false);
$simpleYesAdapter = new ConversationalM03Adapter($simpleYesDb);
$simpleYesBefore = $simpleYesAdapter->read(9301);
$simpleYes = $simpleYesAdapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 1, 'theme_id' => 1,
    'structured_value' => 'Sí', 'semantic_state' => 'KNOWN',
], $simpleYesBefore['version']);
$assert(($simpleYes['resultado'] ?? 0) === 200 && ($simpleYes['readback'] ?? false) === true, 'SIMPLE_YES_CAPTURE_OR_READBACK_FAILED');
$assert((int)($simpleYes['next_question_id'] ?? 0) === 2, 'SIMPLE_YES_DID_NOT_SELECT_QUESTION_2');
$assert(in_array(2, $simpleYes['eligible_question_ids'] ?? [], true), 'SIMPLE_YES_DID_NOT_ENABLE_DEPENDENTS_FROM_SERVER');
$simpleYesStored = (string)$simpleYesDb->query('SELECT respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=1')->fetchColumn();
$assert($simpleYesStored === 'Si', 'SIMPLE_YES_NOT_STORED_CANONICALLY');

$negativeDb = $schema('TEXT', false);
$negativeAdapter = new ConversationalM03Adapter($negativeDb);
$negativeBefore = $negativeAdapter->read(9301);
$negative = $negativeAdapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 1, 'theme_id' => 1,
    'structured_value' => 'No, nunca he fumado', 'semantic_state' => 'NEGATIVE',
], $negativeBefore['version']);
$assert(($negative['resultado'] ?? 0) === 200 && ($negative['readback'] ?? false) === true, 'NEGATIVE_CAPTURE_OR_READBACK_FAILED');
$assert((string)$negativeDb->query('SELECT respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=1')->fetchColumn() === 'No', 'NEGATIVE_NOT_STORED_CANONICALLY');
$assert((int)($negative['next_question_id'] ?? 0) === 6, 'NEGATIVE_DID_NOT_SKIP_TO_QUESTION_6');
$assert(!array_intersect([2, 3, 4, 5], $negative['eligible_question_ids'] ?? []), 'NEGATIVE_LEFT_SMOKING_DEPENDENTS_ELIGIBLE');
$assert((int)$negativeDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta BETWEEN 2 AND 5')->fetchColumn() === 0, 'NEGATIVE_CREATED_IMPLICIT_DEPENDENT_ROWS');

$ambiguousDb = $schema('TEXT', false);
$ambiguousAdapter = new ConversationalM03Adapter($ambiguousDb);
$ambiguousBefore = $ambiguousAdapter->read(9301);
$ambiguous = $ambiguousAdapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 1, 'theme_id' => 1,
    'structured_value' => 'Sí y no', 'semantic_state' => '',
], $ambiguousBefore['version']);
$assert(($ambiguous['resultado'] ?? 0) === 422 && ($ambiguous['persist'] ?? true) === false, 'AMBIGUOUS_CAPTURE_NOT_REJECTED');
$assert((int)$ambiguousDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301')->fetchColumn() === 0, 'AMBIGUOUS_CAPTURE_WROTE_DATA');

$conflictDb = $schema('TEXT', false);
$conflictDb->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta,id_paciente,respuesta) VALUES (2,9301,?)')->execute(['synthetic dependent answer']);
$conflictAdapter = new ConversationalM03Adapter($conflictDb);
$conflictBefore = $conflictAdapter->read(9301);
$conflict = $conflictAdapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 1, 'theme_id' => 1,
    'structured_value' => 'No', 'semantic_state' => 'NEGATIVE',
], $conflictBefore['version']);
$assert(($conflict['resultado'] ?? 0) === 409, 'HISTORICAL_DEPENDENT_CONFLICT_NOT_BLOCKED');
$assert((int)$conflictDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=1')->fetchColumn() === 0, 'CONFLICTING_NO_WAS_PERSISTED');
$assert((string)$conflictDb->query('SELECT respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=2')->fetchColumn() === 'synthetic dependent answer', 'HISTORICAL_DEPENDENT_RESPONSE_CHANGED');

$shortColumnDb = $schema('VARCHAR(8)', false);
$shortColumnAdapter = new ConversationalM03Adapter($shortColumnDb);
$shortBefore = $shortColumnAdapter->read(9301);
$shortColumn = $shortColumnAdapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 1, 'theme_id' => 1,
    'structured_value' => 'Sí, fumo cinco cigarros al día', 'semantic_state' => 'KNOWN',
], $shortBefore['version']);
$assert(($shortColumn['resultado'] ?? 0) === 409 && ($shortColumn['code'] ?? '') === 'M03_TOBACCO_DETAIL_COLUMN_TOO_SMALL', 'SHORT_COLUMN_DID_NOT_FAIL_CLOSED');
$assert((int)$shortColumnDb->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301')->fetchColumn() === 0, 'SHORT_COLUMN_ATTEMPT_WROTE_DATA');

$unknownDb = $schema('TEXT', true);
$unknownModel = new AntecedentesNoPatologicosModel($unknownDb);
$unknownService = new ConversationalInterviewService($unknownModel);
$unknownProposal = $unknownService->interpret(9301, 1, 'No lo sé');
$assert(($unknownProposal['semantic_state'] ?? '') === 'UNKNOWN'
    && array_key_exists('structured_value', $unknownProposal)
    && $unknownProposal['structured_value'] === null,
    'UNKNOWN_SERVICE_CONTRACT_FAILED');
$unknownAdapter = new ConversationalM03Adapter($unknownDb);
$unknownBefore = $unknownAdapter->read(9301);
$unknown = $unknownAdapter->capture(9301, [
    'kind' => 'm03_proposal', 'question_id' => 1, 'theme_id' => 1,
    'structured_value' => null, 'semantic_state' => 'UNKNOWN',
], $unknownBefore['version']);
$assert(($unknown['resultado'] ?? 0) === 200 && ($unknown['readback'] ?? false) === true, 'UNKNOWN_PERSISTENCE_READBACK_FAILED');
$unknownRow = $unknownDb->query('SELECT respuesta,estado_respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=1')->fetch(PDO::FETCH_ASSOC);
$assert(($unknownRow['respuesta'] ?? 'unexpected') === '' && ($unknownRow['estado_respuesta'] ?? '') === 'UNKNOWN', 'UNKNOWN_WAS_STORED_AS_NEGATIVE_OR_LOST');
$assert(($unknown['navigation_decision'] ?? '') === 'UNKNOWN', 'UNKNOWN_NAVIGATION_DECISION_NOT_PRESERVED');

$legacyDb = $schema('TEXT', false);
$legacyDb->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta,id_paciente,respuesta) VALUES (1,9301,?)')->execute(['Si']);
$legacyAdapter = new ConversationalM03Adapter($legacyDb);
$legacy = $legacyAdapter->read(9301);
$legacyQ2 = array_values(array_filter($legacy['questions'], static fn(array $row): bool => (int)$row['idPregunta'] === 2))[0] ?? [];
$assert(($legacyQ2['eligible'] ?? false) === true, 'LEGACY_SI_NO_REMAINS_AFFIRMATIVE');
$negativeHistoryDb = $schema('TEXT', false);
$negativeHistoryDb->prepare('INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta,id_paciente,respuesta) VALUES (1,9301,?)')->execute(['No']);
$negativeHistory = (new ConversationalM03Adapter($negativeHistoryDb))->read(9301);
$negativeHistoryQ2 = array_values(array_filter($negativeHistory['questions'], static fn(array $row): bool => (int)$row['idPregunta'] === 2))[0] ?? [];
$assert(($negativeHistoryQ2['eligible'] ?? true) === false, 'LEGACY_NO_DEPENDENCY_GUARD_CHANGED');

$otherThemeDb = $schema('TEXT', false);
$otherThemeAdapter = new ConversationalM03Adapter($otherThemeDb);
$otherTheme = $otherThemeAdapter->interpret(9301, 6, 'No');
$assert(($otherTheme['structured_value'] ?? '') === 'No', 'OTHER_M03_SELECT_INTERPRETATION_CHANGED');

$serviceSource = file_get_contents(dirname(__DIR__, 2) . '/app/Services/ConversationalInterviewService.php');
$assert(str_contains($serviceSource, 'navigation_decision=AFFIRMATIVE'), 'UNIFIED_DEPENDENCY_CONTRACT_NOT_UPDATED');
$controllerSource = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/PatientInterviewController.php');
$viewSource = file_get_contents(dirname(__DIR__, 2) . '/app/views/paciente/entrevista.php');
$assert(str_contains($controllerSource, "'eligible_question_ids' => \$result['eligible_question_ids'] ?? null"), 'CONTROLLER_DID_NOT_RETURN_SERVER_ELIGIBILITY');
$assert(str_contains($controllerSource, 'captureContextualAction')
    && str_contains($controllerSource, "'readback_value' => \$result['readback_value'] ?? null"), 'SERVER_CONTEXTUAL_ACTION_OR_READBACK_RESPONSE_MISSING');
$assert(str_contains($viewSource, 'applyM03ServerEligibility(data, Number(question.dataset.questionId))')
    && str_contains($viewSource, 'showNextM03Question(question, data.next_question_id, data.finalized === true)'), 'CLIENT_DID_NOT_USE_POST_READBACK_NAVIGATION');
$assert(str_contains($viewSource, 'data-m03-contextual-action') && str_contains($viewSource, 'contextualActionKey: actionKey'), 'CLIENT_CONTEXTUAL_ACTION_NOT_CONTRACT_DRIVEN');
$assert(str_contains($viewSource, "data.readback !== true"), 'NEGATIVE_NAVIGATION_DOES_NOT_REQUIRE_READBACK');

echo "M03 tobacco affirmative/conditional navigation: PASS (in-memory SQLite only; no MySQL/HTTP)\n";
