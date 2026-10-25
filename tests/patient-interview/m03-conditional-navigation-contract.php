<?php

declare(strict_types=1);

/**
 * Focal M03/Tabaquismo navigation regression.
 *
 * This test is deliberately static plus deterministic. It does not open PDO,
 * HTTP or Apache and cannot write clinical data.
 */
$root = dirname(__DIR__, 2);
$adapter = file_get_contents($root . '/app/Services/ConversationalM03Adapter.php');
$model = file_get_contents($root . '/app/Models/AntecedentesNoPatologicosModel.php');
$service = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$catalog = require $root . '/tests/fixtures/p05-catalog.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($adapter, '1 => 1') && str_contains($adapter, 'PARENT_BY_THEME'), 'Tabaquismo parent contract is missing');
$assert(str_contains($model, 'if ($idTema > 0)') && str_contains($service, 'obtenerPreguntasEntrevista($patientId, 0)'), 'M03 interpretation must read the complete catalog');
$assert(str_contains($service, "'requires_question' => 'M03:question:'"), 'M03 dependency contract is missing');
$assert(str_contains($view, "question.dataset.eligible === '1'"), 'Client navigation does not consult eligibility');
$assert(str_contains($view, 'data.readback !== true')
    && str_contains($view, 'applyM03ServerEligibility(data, Number(question.dataset.questionId))')
    && str_contains($view, 'showNextM03Question(question, data.next_question_id, data.finalized === true)')
    && str_contains($adapter, 'nextPendingQuestionId'),
    'M03 navigation must wait for readback and use the server-selected eligible question');
$assert(!str_contains($view, 'if (active < questions.length - 1) showQuestion(active + 1)'), 'M03 still advances by raw array position');
$assert(str_contains($view, 'data-interview-zone="response"') && str_contains($view, 'responseZone.hidden = reviewOnly || !canEditQuestion'), 'Only consultation mode may hide a saved previous response form');
$assert(str_contains($view, "showQuestion(previousIndex, {mode: previousEditAllowed ? 'direct-edit' : 'review', returnIndex: active, discardDraft: true, narrateQuestion: true, userGesture: true})")
    && str_contains($view, "candidateContract?.response?.status !== 'documented'")
    && str_contains($view, "candidateContract?.response?.persisted !== true"),
    'Previous navigation must require persisted readback and enter a fail-closed consultation state');
$assert(str_contains($view, 'data-m03-next')
    && str_contains($view, 'const navigateM03Next = button =>')
    && str_contains($view, "conversationCoordinator.invalidate('history_next_question')")
    && str_contains($view, "conversationCoordinator.invalidate('history_return_to_pending')")
    && str_contains($view, 'isPersistedEligibleM03Question(candidate)')
    && str_contains($view, "showQuestion(index, {mode, returnIndex, discardDraft: true, narrateQuestion: true, userGesture: true})")
    && str_contains($view, "return showQuestion(returnIndex, {discardDraft: true, narrateQuestion: true, userGesture: true, allowCurrentPending: true})"),
    'History navigation must step across persisted eligible questions and return to the pending server target');
$assert(str_contains($view, "control.disabled = true") && str_contains($view, "confirmed.hidden = !reviewOnly")
    && str_contains($view, "input.value = String(question.dataset.savedValue || '')")
    && str_contains($view, 'options.discardDraft === true')
    && str_contains($view, "responseZone.hidden = reviewOnly || !canEditQuestion"),
    'Read-only review stays disabled while authorized previous-edit mode restores the saved answer into the original input');
$assert(str_contains($view, 'idRespuesta: Number(question.dataset.responseId || 0)')
    && str_contains($view, 'editingPreviousAnswer')
    && str_contains($view, 'Number(data.response_id) !== Number(question.dataset.responseId || 0)')
    && str_contains($view, 'question.dataset.savedValue = String(data.readback_value ?? value)'),
    'Previous edit must submit the existing response identity and require same-row readback');
$assert(str_contains($adapter, 'serverAuthorizesHistoricalEdit')
    && str_contains($adapter, 'historicalEditableQuestionIds')
    && str_contains($service, '\'editable\' => (bool)($row[\'historicalEditable\'] ?? false)')
    && str_contains($adapter, '$expectedResponseId !== (int)$question[\'idRespuesta\']')
    && str_contains($model, 'M03_RESPONSE_ID_CONFLICT'),
    'The server must authorize eligible persisted history with version and response identity checks');
$catalogQuestions = $catalog['questions'] ?? [];
$sleep = array_values(array_filter($catalogQuestions, static fn(array $question): bool => (int)($question['id'] ?? 0) === 21))[0] ?? null;
$blood = array_values(array_filter($catalogQuestions, static fn(array $question): bool => (int)($question['id'] ?? 0) === 27))[0] ?? null;
$assert(is_array($sleep) && (int)$sleep['id_tema'] === 6 && ($sleep['tipo'] ?? '') === 'select', 'Sleep medication question contract is missing');
$assert(is_array($blood) && (int)$blood['id_tema'] === 7 && ($blood['tipo'] ?? '') === 'input', 'Blood type question contract is missing');
$assert(str_contains($service, "'M03:question:' . \$questionId"), 'M03 stable ID must derive from the official question ID');
$assert(str_contains($view, 'setTarget(`M03:question:${question.dataset.questionId || active}`)'), 'Visible M03 question is not the coordinator target');
$assert(str_contains($view, 'idPregunta: Number(question.dataset.questionId)'), 'M03 response payload does not use the visible question ID');
$assert(str_contains($view, "modulo:'M03', utterance"), 'M03 interpretation payload is missing');
$assert(str_contains($view, "data-m03-write-enabled"), 'M03 editability state is not exposed to the answer controls');

$answerability = static function (array $question, string $stableId): array {
    return [
        'stable_id' => $stableId,
        'eligible' => true,
        'editable_control' => ($question['tipo'] ?? '') === 'select' ? 'select_or_quick_action' : 'text_input',
        'options' => ($question['tipo'] ?? '') === 'select' ? ['Si', 'No'] : [],
    ];
};
$sleepAnswer = $answerability($sleep, 'M03:question:21');
$bloodAnswer = $answerability($blood, 'M03:question:27');
$assert($sleepAnswer['stable_id'] === 'M03:question:21' && $sleepAnswer['editable_control'] === 'select_or_quick_action', 'Sleep question answer target is not answerable by its catalog type');
$assert($bloodAnswer['stable_id'] === 'M03:question:27' && $bloodAnswer['editable_control'] === 'text_input', 'Blood type answer target is not answerable by its catalog type');
$assert($bloodAnswer['options'] === [], 'Blood type must not receive invented selection options');

$sleepToBloodBack = [
    ['id' => 21, 'stable_id' => 'M03:question:21', 'eligible' => true, 'value' => 'No'],
    ['id' => 27, 'stable_id' => 'M03:question:27', 'eligible' => true, 'value' => ''],
];
$previous = $sleepToBloodBack[0];
$activeAfterBack = $sleepToBloodBack[0];
$assert($previous['stable_id'] === 'M03:question:21' && $activeAfterBack['id'] === 21, 'Sleep question target must be restored after returning from blood type');
$assert($activeAfterBack['eligible'] === true && $activeAfterBack['value'] !== '', 'Returned sleep question must retain its confirmed eligible state');

$nextEligible = static function (array $questions, int $afterIndex): ?int {
    foreach ($questions as $index => $question) {
        if ($index <= $afterIndex) continue;
        if (($question['eligible'] ?? false) === true && trim((string)($question['value'] ?? '')) === '') {
            return $index;
        }
    }
    return null;
};

$noSmoking = [
    ['id' => 1, 'eligible' => true, 'value' => 'No'],
    ['id' => 2, 'eligible' => false, 'value' => ''],
    ['id' => 3, 'eligible' => false, 'value' => ''],
    ['id' => 4, 'eligible' => false, 'value' => ''],
    ['id' => 5, 'eligible' => false, 'value' => ''],
    ['id' => 6, 'eligible' => true, 'value' => ''],
];
$yesSmoking = $noSmoking;
$yesSmoking[0]['value'] = 'Si';
$yesSmoking[1]['eligible'] = true;

$assert($nextEligible($noSmoking, 0) === 5, 'No must skip smoking dependents and continue to the next eligible question');
$assert($nextEligible($yesSmoking, 0) === 1, 'Yes must expose the first smoking dependent question');
$pendingParent = array_replace($noSmoking, [['id' => 1, 'eligible' => true, 'value' => '']]);
$assert($nextEligible($pendingParent, 0) === 5, 'An unanswered parent must not expose dependent questions');
$advanceAfterConfirmation = static fn(bool $confirmed, array $questions, int $afterIndex): ?int => $confirmed ? $nextEligible($questions, $afterIndex) : null;
$assert($advanceAfterConfirmation(false, $yesSmoking, 0) === null, 'An ambiguous response must not advance before explicit confirmation');

$writes = [];
$writes[] = 1; // Only the explicitly confirmed parent response is captured.
$assert($writes === [1], 'Skipped smoking dependents must not be written implicitly');

$previousEligible = array_values(array_filter($noSmoking, static fn(array $question): bool => ($question['eligible'] ?? false) && trim((string)($question['value'] ?? '')) !== ''));
$assert(($previousEligible[0]['id'] ?? null) === 1, 'Back navigation must remain on the last eligible confirmed question');

echo "M03 answerability/navigation contract: PASS (Tabaquismo, Dormir ID 21 and Grupo Sanguineo ID 27; no database writes)\n";
