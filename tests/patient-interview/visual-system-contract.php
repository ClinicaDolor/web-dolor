<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');

$assertions = [
    str_contains($view, 'patient-interview-page'),
    str_contains($view, 'patient-interview-main'),
    str_contains($view, 'interview-progress-card'),
    str_contains($view, 'patient-interview-conversation-card'),
    substr_count($view, 'class="patient-interview-module-context interview-module-card"') === 10,
    str_contains($view, 'class="patient-interview-question interview-module-card"'),
    substr_count($view, 'interview-composer') >= 8,
    str_contains($view, 'interview-composer-input'),
    substr_count($view, 'interview-message-header') >= 9,
    str_contains($view, 'grid-template-columns:minmax(0,1fr) auto'),
    str_contains($view, 'grid-column:2; grid-row:1'),
    str_contains($view, 'interview-composer-help'),
    str_contains($view, 'interview-proposal-fields'),
    str_contains($view, 'Motivo de uso'),
    str_contains($view, 'Las respuestas complejas se revisan antes de guardarse.'),
    str_contains($view, 'chat-send-button'),
    str_contains($view, 'data-m03-quick-yes'),
    str_contains($view, 'data-m03-safe-no'),
    str_contains($view, 'function bindVoiceControls()'),
    str_contains($view, 'activeQuestionMessage'),
    str_contains($view, 'activeModuleMessage'),
    str_contains($view, 'chat-mic-unavailable'),
    str_contains($view, 'aria-label'),
    !str_contains($view, 'class="btn btn-outline-secondary" disabled>🎙 No disponible'),
    !str_contains($view, 'class="btn btn-primary interview-submit">Interpretar respuesta</button>'),
    !str_contains($view, 'interview-choice'),
    !str_contains($view, 'data-m01-input type='),
    str_contains($view, 'No fue posible interpretar la respuesta.'),
];

if (in_array(false, $assertions, true)) {
    fwrite(STDERR, "Interview visual system contract: FAIL\n" . json_encode($assertions) . "\n");
    exit(1);
}

echo 'Interview visual system contract: PASS (' . count($assertions) . " assertions; view-only; no database writes)\n";
