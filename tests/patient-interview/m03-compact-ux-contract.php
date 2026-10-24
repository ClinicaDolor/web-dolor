<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$adapter = file_get_contents($root . '/app/Services/ConversationalM03Adapter.php');
$dictationUi = file_get_contents($root . '/public/assets/js/patient-interview-m03-dictation-ui.js');
$catalog = require $root . '/tests/fixtures/p05-catalog.php';

$section = static function (string $source, string $start, string $end): string {
    $startAt = strpos($source, $start);
    if ($startAt === false) return '';
    $endAt = strpos($source, $end, $startAt + strlen($start));
    return $endAt === false ? '' : substr($source, $startAt, $endAt - $startAt);
};

$loopStart = strpos($view, '<?php foreach ($questionList as $index => $question): ?>');
$loopEnd = $loopStart === false ? false : strpos($view, '<?php endforeach; ?>', $loopStart);
$questionTemplate = $loopStart !== false && $loopEnd !== false
    ? substr($view, $loopStart, $loopEnd - $loopStart)
    : '';
$actionDefinitions = $section($adapter, 'private const CONTEXTUAL_ACTIONS = [', '];');
$actionSync = $section($view, 'const syncM03ContextualActions = () => {', 'const coordinatorStateLabels = Object.freeze({');
$actionCaptureClient = $section($view, 'const captureM03ContextualAction = async (question, button) => {', "document.querySelectorAll('[data-m03-contextual-action]')");
$actionCaptureServer = $section($adapter, 'public function captureContextualAction(', 'private function contextualActionFor(');
$questionNavigation = $section($view, 'const showQuestion = (index, options = {}) => {', 'const valueFor = question => {');
$nextQuestionFlow = $section($view, 'const showNextM03Question = (question, nextQuestionId = null, finalized = false) => {', 'const applyM03ServerEligibility =');
$directSave = $section($view, 'const saveAndAdvanceM03Direct = async (question, value, directMode) => {', 'const confirmAndSave = async');
$confirmAndSave = $section($view, "const confirmAndSave = async (question, value, proposalBox = null, semanticState = '') => {", 'const interpret = async');
$historicalEdit = $section($view, 'const hasM03HistoricalEditAuthorization = (question, returnIndex) => {', 'const showQuestion = (index, options = {}) => {');
$dictationStart = strpos($actionCaptureClient, '} catch (error) {');
$dictationFailure = $dictationStart === false ? '' : substr($actionCaptureClient, $dictationStart);

$assertions = [
    'one_server_defined_contextual_action_per_question' => $questionTemplate !== ''
        && substr_count($questionTemplate, 'data-m03-contextual-action') === 1
        && str_contains($questionTemplate, '$m03ContextualAction[\'key\']')
        && str_contains($questionTemplate, '$m03ContextualAction[\'label\']')
        && !str_contains($questionTemplate, 'data-m03-quick-yes')
        && !str_contains($questionTemplate, 'data-m03-safe-no'),
    'contextual_labels_and_semantics_match_question_contract' => str_contains($actionDefinitions, "'label' => 'No, guardar y continuar'")
        && str_contains($actionDefinitions, "'action_type' => 'NOT_REMEMBERED'")
        && str_contains($actionDefinitions, "'action_type' => 'UNKNOWN'")
        && preg_match("/^\\s*2\\s*=>\\s*\\[[^\\r\\n]*'action_type' => 'NOT_REMEMBERED'[^\\r\\n]*'label' => 'No recuerdo, guardar y continuar'/m", $actionDefinitions) === 1
        && preg_match("/^\\s*11\\s*=>\\s*\\[[^\\r\\n]*'action_type' => 'UNKNOWN'[^\\r\\n]*'label' => 'No lo sé'/m", $actionDefinitions) === 1,
    'seven_theme_catalog_and_dependency_map_remain_explicit' => count($catalog['themes'] ?? []) === 7
        && count($catalog['questions'] ?? []) === 25
        && str_contains($adapter, 'PARENT_BY_THEME = [1 => 1, 2 => 6, 3 => 12, 4 => 16, 5 => 18, 6 => 21]')
        && str_contains($adapter, "\$question['eligible'] = \$parent === null || (int)\$question['idPregunta'] === \$parent || \$parentIsAffirmative;"),
    str_contains($view, 'data-m03-write-enabled'),
    str_contains($view, 'chat-send-button interview-submit'),
    str_contains($view, 'aria-label="Interpretar respuesta"'),
    !str_contains($view, 'data-proposal-confirm'),
    !str_contains($view, 'class="btn btn-primary interview-submit">Interpretar respuesta</button>'),
    !str_contains($view, 'data-proposal-cancel'),
    !str_contains($view, 'interview-choice'),
    'dictation_component_shows_accessible_disabled_reasons' => str_contains($dictationUi, 'const canInitiate =')
        && str_contains($dictationUi, 'featureEnabled && clientSupported && permissionDenied !== true')
        && str_contains($dictationUi, 'if (!active || !fieldVisible || !inputIsCurrent)')
        && str_contains($dictationUi, 'removeControls(card)')
        && str_contains($dictationUi, 'M03_WRITE_DISABLED:')
        && str_contains($dictationUi, 'M03_FINALIZED:')
        && str_contains($dictationUi, 'button.disabled = !enabled')
        && str_contains($dictationUi, "button.setAttribute('aria-label', enabled"),
    !str_contains($view, '🎙 Dictar'),
    'contextual_actions_are_hidden_for_inactive_or_noneditable_questions' => $actionSync !== ''
        && str_contains($actionSync, "conversationStages[conversationStage]?.type === 'm03'")
        && str_contains($actionSync, 'card === questions[active]')
        && str_contains($actionSync, '!m03ReviewOnly && !m03PreviousEditOnly')
        && str_contains($actionSync, "card.dataset.m03WriteEnabled === '1'")
        && str_contains($actionSync, "card.dataset.eligible === '1'")
        && str_contains($actionSync, "questionContract?.response?.status === 'pending'")
        && str_contains($actionSync, 'questionContract?.response?.persisted !== true')
        && str_contains($actionSync, 'container.hidden = !activeAndAuthorized')
        && str_contains($actionSync, 'button.disabled = !activeAndAuthorized || busy'),
    'one_question_is_active_and_ineligible_questions_are_rejected' => $questionNavigation !== ''
        && str_contains($questionNavigation, "candidate.dataset.eligible === '0' && !hasConfirmedAnswer(candidate)")
        && str_contains($questionNavigation, 'question.hidden = questionIndex !== active;')
        && str_contains($questionNavigation, 'conversationCoordinator.setTarget(`M03:question:${question.dataset.questionId || active}`)'),
    'contextual_capture_requires_key_version_and_server_readback_before_navigation' => $actionCaptureClient !== ''
        && str_contains($actionCaptureClient, "headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf}")
        && str_contains($actionCaptureClient, 'contextualActionKey: actionKey')
        && str_contains($actionCaptureClient, 'expectedVersion: question.dataset.m03Version ||')
        && str_contains($actionCaptureClient, 'data.readback !== true')
        && str_contains($actionCaptureClient, 'Array.isArray(data.eligible_question_ids)')
        && str_contains($actionCaptureClient, 'applyM03ServerEligibility(data, Number(question.dataset.questionId))')
        && strpos($actionCaptureClient, 'data.readback !== true') < strpos($actionCaptureClient, 'showNextM03Question(question, data.next_question_id, data.finalized === true)')
        && str_contains($dictationFailure, 'setStatus(error.message ||')
        && !str_contains($dictationFailure, 'showNextM03Question'),
    'server_revalidates_contextual_action_and_uses_server_owned_value' => $actionCaptureServer !== ''
        && str_contains($actionCaptureServer, 'if ($patientId < 1')
        && str_contains($actionCaptureServer, 'hash_equals((string)$state[\'version\'], $expectedVersion)')
        && str_contains($actionCaptureServer, '(int)$question[\'idTema\'] !== $themeId')
        && str_contains($actionCaptureServer, 'empty($state[\'write_enabled\'])')
        && str_contains($actionCaptureServer, 'empty($question[\'eligible\'])')
        && str_contains($actionCaptureServer, 'hash_equals((string)($action[\'key\'] ?? \'\'), $actionKey)')
        && str_contains($actionCaptureServer, "'structured_value' => \$action['structured_value']")
        && str_contains($actionCaptureServer, "'semantic_state' => \$action['semantic_state']"),
    str_contains($view, 'proposal.direct_save === true && proposal.can_confirm === true'),
    str_contains($view, 'unknownStorageUnavailable'),
    str_contains($view, 'No se guardó el dato desconocido ni se interpretó como “No”'),
    'deterministic_direct_answers_use_current_capture_signature_and_readback' => $directSave !== ''
        && str_contains($view, "confirmAndSave(question, proposal.structured_value || '', null, proposal.semantic_state || '')")
        && str_contains($directSave, "data.mode !== 'direct' || data.readback !== true")
        && str_contains($directSave, 'editingHistoricalAnswer')
        && str_contains($directSave, 'data.response_id'),
    'ordinary_answer_capture_preserves_readback_semantic_state_and_response_identity' => $confirmAndSave !== ''
        && str_contains($confirmAndSave, 'data.readback !== true')
        && str_contains($confirmAndSave, 'data.response_id')
        && str_contains($confirmAndSave, 'question.dataset.semanticState = String(data.semantic_state || normalizedState).toUpperCase()')
        && str_contains($confirmAndSave, 'showNextM03Question(question, data.next_question_id, data.finalized === true)'),
    str_contains($view, 'estadoRespuesta: normalizedState'),
    str_contains($view, "event.key !== 'Enter'"),
    str_contains($view, 'question.dataset.m03Interpreting'),
    str_contains($view, "answer.classList.contains('m03-composer')"),
    'failed_contextual_save_stays_on_current_question' => str_contains($actionCaptureClient, 'catch (error)')
        && str_contains($actionCaptureClient, 'No fue posible guardar la respuesta. Puedes reintentar.')
        && !str_contains($dictationFailure, 'advanceConversation()'),
    str_contains($view, 'expectedVersion: question.dataset.m03Version ||'),
    str_contains($view, 'item.dataset.m03Version = data.version'),
    str_contains($view, "const hasConfirmedAnswer = question => Boolean(question?.dataset.savedValue?.trim()) || semanticAnswerStates.has(semanticAnswerState(question));"),
    str_contains($view, 'const savedQuestions = questions.filter(hasConfirmedAnswer);'),
    str_contains($view, "question.dataset.semanticState = String(data.semantic_state || normalizedState).toUpperCase();"),
    str_contains($controller, "'readback' => " . '$result' . "['readback'] ?? false"),
    str_contains($controller, "'semantic_state' => " . '$result' . "['semantic_state'] ?? null"),
    'historical_edits_remain_limited_to_persisted_contiguous_eligible_prefix' => $historicalEdit !== ''
        && str_contains($historicalEdit, "candidateContract?.response?.status !== 'documented'")
        && str_contains($historicalEdit, 'candidateContract?.response?.persisted !== true')
        && str_contains($historicalEdit, 'candidateContract?.response?.editable !== true')
        && str_contains($historicalEdit, 'module.state?.finalized === true')
        && str_contains($historicalEdit, 'eligibleQuestions.slice(targetIndex + 1, pendingIndex).every'),
    'module_advance_requires_server_confirmed_finalization' => $nextQuestionFlow !== ''
        && str_contains($nextQuestionFlow, 'Number(candidate.dataset.questionId) === Number(nextQuestionId)')
        && str_contains($nextQuestionFlow, "candidate.dataset.eligible === '1'")
        && str_contains($nextQuestionFlow, '!hasConfirmedAnswer(candidate)')
        && str_contains($nextQuestionFlow, 'if (finalized !== true)')
        && strpos($nextQuestionFlow, 'if (finalized !== true)') < strpos($nextQuestionFlow, 'advanceConversation();')
        && str_contains($view, 'showNextM03Question(question, data.next_question_id, data.finalized === true)'),
];

if (in_array(false, $assertions, true)) {
    $failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
    fwrite(STDERR, "M03 compact UX contract: FAIL\n" . implode("\n", $failed) . "\n");
    exit(1);
}

echo 'M03 compact UX contract: PASS (' . count($assertions) . " focused assertions; static contract only; no database writes)\n";
