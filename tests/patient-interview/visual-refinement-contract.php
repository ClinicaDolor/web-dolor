<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$sidebar = file_get_contents($root . '/app/Controllers/SidebarController.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
if ($view === false || $sidebar === false || $controller === false) throw new RuntimeException('INTERVIEW_FILES_NOT_READABLE');
$previousPosition = strpos($view, 'data-m03-previous');
$questionPosition = strpos($view, 'patient-interview-question');
$assistantAfterPrevious = $previousPosition === false ? false : strpos($view, 'patient-interview-message assistant', $previousPosition);
$introText = 'Responderás una pregunta a la vez. Pulsa Enviar para validar y guardar tu respuesta.';
$removedHeaderInstruction = 'Responde una pregunta a la vez; pulsa Enviar para validar y guardar cuando la respuesta sea válida.';

$checks = [
    'local_feather_loaded' => str_contains($view, 'feather-icons/feather.min.js'),
    'sidebar_toggle_accessible_without_duplicate_injection' => str_contains($view, 'data-interview-sidebar-toggle') && str_contains($view, 'aria-controls="sidebar"') && !str_contains($view, 'patient-interview-sidebar-header-toggle'),
    'interview_sidebar_item_is_active' => str_contains($sidebar, "'titulo' => 'Entrevista Conversacional'") && str_contains($controller, "setActivarItem('Entrevista Conversacional')"),
    'general_instruction_only_in_assistant_bubble' => substr_count($view, $introText) === 1 && !str_contains($view, $removedHeaderInstruction),
    'assistant_intro_readable_desktop_mobile' => str_contains($view, 'font-size:clamp(1.125rem,1.3vw,1.25rem)') && str_contains($view, 'font-size:clamp(1rem,4vw,1.125rem)') && str_contains($view, 'line-height:1.55') && str_contains($view, 'padding:1.2rem 1.5rem') && str_contains($view, 'color:#33415f'),
    'assistant_intro_single_static_node' => substr_count($view, '<div class="patient-interview-message assistant" data-interview-zone="assistant">') === 1 && str_contains($view, '<strong>Asistente:</strong>'),
    'sidebar_compact_layout' => str_contains($view, '#sidebar.is-compact') && str_contains($view, 'is-compact'),
    'mobile_escape_close' => str_contains($view, "event.key === 'Escape'") && str_contains($view, "sidebar.classList.remove('active')"),
    'previous_before_assistant' => $previousPosition !== false && $questionPosition !== false && $previousPosition > $questionPosition && $assistantAfterPrevious !== false && $previousPosition < $assistantAfterPrevious,
    'history_structured_nodes' => str_contains($view, 'renderRegisteredAnswers') && str_contains($view, 'chat-history-list') && str_contains($view, 'createElement'),
    'history_no_dom_concatenation' => !str_contains($view, "rows.join(' · ')") && !str_contains($view, "assistant')?.textContent"),
    'history_hover_scoped' => str_contains($view, '[data-chat-history]:hover') && str_contains($view, 'body.patient-interview-page'),
    'previous_hides_confirmation' => str_contains($view, "previous.querySelector('[data-confirmed-message]')") && str_contains($view, 'confirmed.hidden = true'),
    'recovery_notice_removed' => !str_contains($view, 'Pregunta anterior recuperada.'),
    'no_history_back' => !str_contains($view, 'history.back('),
    'clinical_routes_preserved' => str_contains($view, 'capturar-directo') && str_contains($view, 'capturar-m09-mapa'),
    'contextual_help_handler' => str_contains($view, "voiceEvent('HELP', target, false)") && str_contains($view, 'questionContract(target)') && str_contains($view, 'No hay una explicación adicional disponible'),
    'question_repeat_label_and_speaker_icon' => str_contains($view, '<span>Repetir pregunta</span>') && str_contains($view, "setAttribute('aria-label', 'Repetir pregunta')") && str_contains($view, '<svg viewBox="0 0 24 24"'),
    'question_and_sidebar_share_repeat_path' => str_contains($view, 'const repeatActiveQuestion = (button = null)') && str_contains($view, "button.addEventListener('click', () => repeatActiveQuestion(button))") && str_contains($view, 'repeatActiveQuestion();'),
    'stale_repeat_target_rejected' => str_contains($view, 'buttonTarget !== activeTarget') && str_contains($view, "event.stable_id !== activeTarget"),
    'question_repeat_purple_accessible_states' => str_contains($view, '#interview-chat .chat-voice-button:hover:not(:disabled)') && str_contains($view, '#interview-chat .chat-voice-button:focus-visible') && str_contains($view, '#interview-chat .chat-voice-button:disabled') && str_contains($view, 'min-height:44px'),
    'purple_button_contrast' => str_contains($view, '.patient-interview-page .btn-outline-primary:hover') && str_contains($view, 'color:#fff') && str_contains($view, 'stroke:currentColor'),
    'button_focus_and_disabled_states' => str_contains($view, '.patient-interview-page .btn:focus-visible') && str_contains($view, '.patient-interview-page .btn-outline-primary:disabled'),
];

foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
}

if (in_array(false, $checks, true)) {
    throw new RuntimeException('VISUAL_REFINEMENT_CONTRACT: FAIL');
}

echo "VISUAL_REFINEMENT_CONTRACT: PASS\n";
