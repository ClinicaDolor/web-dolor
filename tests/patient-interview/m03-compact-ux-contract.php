<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');

$assertions = [
    str_contains($view, 'data-m03-quick-yes'),
    str_contains($view, 'data-m03-safe-no'),
    str_contains($view, 'No, guardar y seguir'),
    str_contains($view, 'data-m03-write-enabled'),
    str_contains($view, 'chat-send-button interview-submit'),
    str_contains($view, 'aria-label="Interpretar respuesta"'),
    !str_contains($view, 'data-proposal-confirm'),
    !str_contains($view, 'class="btn btn-primary interview-submit">Interpretar respuesta</button>'),
    !str_contains($view, 'data-proposal-cancel'),
    !str_contains($view, 'interview-choice'),
    str_contains($view, '🎙 No disponible; el navegador no admite reconocimiento de voz'),
    !str_contains($view, '🎙 Dictar'),
    str_contains($view, 'const captureSafeM03No = async'),
    str_contains($view, 'proposal.direct_save === true && proposal.can_confirm === true'),
    str_contains($view, 'unknownStorageUnavailable'),
    str_contains($view, 'No se guardó el dato desconocido ni se interpretó como “No”'),
    str_contains($view, "confirmAndSave(question, proposal.structured_value || '', null, true, proposal.semantic_state || '')"),
    str_contains($view, 'estadoRespuesta: normalizedState'),
    str_contains($view, 'directContract && data.readback !== true'),
    str_contains($view, "event.key !== 'Enter'"),
    str_contains($view, 'question.dataset.m03Interpreting'),
    str_contains($view, 'chat-mic-unavailable'),
    str_contains($view, "answer.classList.contains('m03-composer')"),
    str_contains($view, 'No fue posible guardar la respuesta negativa.'),
    str_contains($view, 'expectedVersion: question.dataset.m03Version ||'),
    str_contains($view, 'item.dataset.m03Version = data.version'),
    str_contains($view, "const hasConfirmedAnswer = question => Boolean(question?.dataset.savedValue?.trim()) || semanticAnswerStates.has(semanticAnswerState(question));"),
    str_contains($view, 'const savedQuestions = questions.filter(hasConfirmedAnswer);'),
    str_contains($view, "question.dataset.semanticState = String(data.semantic_state || normalizedState).toUpperCase();"),
    str_contains($controller, "'readback' => " . '$result' . "['readback'] ?? false"),
    str_contains($controller, "'semantic_state' => " . '$result' . "['semantic_state'] ?? null"),
];

if (in_array(false, $assertions, true)) {
    fwrite(STDERR, "M03 compact UX contract: FAIL\n" . json_encode($assertions) . "\n");
    exit(1);
}

echo 'M03 compact UX contract: PASS (' . count($assertions) . " assertions; interview scope; no database writes)\n";
