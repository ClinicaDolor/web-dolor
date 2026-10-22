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
$removedHeaderInstruction = 'Responde una pregunta a la vez; pulsa Enviar para validar y guardar cuando la respuesta sea válida.';

$checks = [
    'local_feather_loaded' => str_contains($view, 'feather-icons/feather.min.js'),
    'sidebar_toggle_accessible_without_duplicate_injection' => str_contains($view, 'data-interview-sidebar-toggle') && str_contains($view, 'aria-controls="sidebar"') && !str_contains($view, 'patient-interview-sidebar-header-toggle'),
    'interview_sidebar_item_is_active' => str_contains($sidebar, "'titulo' => 'Entrevista Conversacional'") && str_contains($controller, "setActivarItem('Entrevista Conversacional')"),
    'shared_guidance_replaces_old_intro' => substr_count($view, '<aside class="conversational-guidance"') === 1 && str_contains($view, 'Cómo responder') && !str_contains($view, 'Responderás una pregunta a la vez. Pulsa Enviar para validar y guardar tu respuesta.') && !str_contains($view, $removedHeaderInstruction),
    'shared_guidance_compact_accessible_and_responsive' => str_contains($view, 'data-interview-zone="assistant"') && str_contains($view, 'aria-label="Orientación para responder"') && str_contains($view, 'conversational-guidance__icon" aria-hidden="true"') && str_contains($view, 'conversational-guidance__description') && str_contains($view, '@media (max-width:600px)') && str_contains($view, 'overflow-wrap:anywhere'),
    'guidance_approved_gradient_and_desktop_scale' => str_contains($view, 'background:linear-gradient(110deg,#FBFAFF 0%,#F2ECFC 65%,#E8DDFA 100%)') && str_contains($view, 'min-height:6.75rem') && str_contains($view, 'padding:1.3rem 1.65rem') && str_contains($view, 'flex:0 0 3.75rem') && str_contains($view, 'font-size:1.2rem') && str_contains($view, 'font-size:1rem'),
    'guidance_tablet_mobile_sizes' => str_contains($view, '@media (max-width:900px)') && str_contains($view, 'min-height:6rem') && str_contains($view, '@media (max-width:600px)') && str_contains($view, 'min-height:0') && str_contains($view, 'flex-basis:3rem') && str_contains($view, 'font-size:.94rem'),
    'guidance_copy_tracks_available_dictation' => str_contains($view, 'Una pregunta a la vez. Escribe o dicta tu respuesta y revísala antes de enviarla.') && str_contains($view, 'Una pregunta a la vez. Escribe tu respuesta y revísala antes de enviarla.') && str_contains($view, "moduleId === 'M02'") && str_contains($view, 'main?.dataset.localDictationEnabled === \'1\'') && str_contains($view, 'localDictation?.supported() === true') && str_contains($view, "m02Card?.dataset.m02WriteEnabled === '1'") && str_contains($view, 'm02Card.dataset.m02Finalized !== \'1\'') && str_contains($view, 'm02Input && !m02Input.disabled && !m02Input.readOnly'),
    'm02_question_visual_remains_primary' => str_contains($view, 'clinical-question-header') && str_contains($view, 'clinical-question-title') && str_contains($view, 'background:#F2ECFC') && str_contains($view, 'clinical-answer-area'),
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
