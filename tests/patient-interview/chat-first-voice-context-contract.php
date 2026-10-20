<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$service = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');
$coordinator = file_get_contents($root . '/public/assets/js/patient-interview-coordinator.js');
$sidebar = file_get_contents($root . '/app/Controllers/SidebarController.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
if ($view === false || $service === false || $coordinator === false || $sidebar === false || $controller === false) throw new RuntimeException('CHAT_FIRST_FILES_NOT_READABLE');

$checks = [
    'm03_stable_question_target' => str_contains($view, 'M03:question:${question.dataset.questionId') && str_contains($service, "'M03:question:' . " . '$questionId'),
    'm03_theme_context' => str_contains($view, 'data-theme-name') && str_contains($service, "'theme_id'") && str_contains($service, "'theme'") ,
    'no_hardcoded_m03_theme_label' => !str_contains($view, 'M03 · Tabaquismo') && !str_contains($view, 'M03/Tabaquismo completado'),
    'unknown_question_fail_closed' => str_contains($coordinator, "key.includes(':') && key !== 'interview:current'") && str_contains($coordinator, 'return null;'),
    'voice_context_in_events' => str_contains($coordinator, 'context: this.contextFor(target)') && str_contains($coordinator, 'operation_generation'),
    'voice_controls_bind_target' => str_contains($view, 'button.dataset.voiceTarget = voiceTarget') && str_contains($view, 'stableTargetForMessage'),
    'explicit_voice_action_uses_target' => str_contains($view, 'voiceEvent(\'QUESTION\', target || conversationCoordinator.snapshot().currentTarget, false)') && str_contains($view, 'voiceController.speakEvent(contractVoice, {force:true, userGesture:true})'),
    'stale_voice_canceled_on_target_change' => str_contains($view, "['target_changed', 'previous_question']") && str_contains($view, 'stopAssistantVoice'),
    'single_sidebar_control' => !str_contains($view, 'patient-interview-sidebar-header-toggle'),
    'interview_navigation_is_explicit' => str_contains($sidebar, "'titulo' => 'Entrevista Conversacional'") && str_contains($controller, "setActivarItem('Entrevista Conversacional')"),
    'compact_header' => str_contains($view, 'Responde una pregunta a la vez; revisa cualquier propuesta'),
    'compact_header_typography' => str_contains($view, 'font-size:clamp(1.6rem,2vw,1.85rem)') && str_contains($view, 'font-size:.92rem') && str_contains($view, 'white-space:nowrap'),
    'compact_header_actions' => str_contains($view, '> Respuestas previas</button>') && str_contains($view, '> Pausar</button>') && str_contains($view, '> Mi historia clínica</a>') && str_contains($view, 'flex-wrap:nowrap'),
    'compact_header_progress' => str_contains($view, 'height:.4rem') && str_contains($view, 'class="progress mt-1"'),
    'conversation_follows_header' => str_contains($view, 'patient-interview-header mb-3') && str_contains($view, 'mt-0 patient-interview-conversation-card'),
    'chat_first_topbar' => str_contains($view, 'Mi historia clínica') && str_contains($view, 'portal-status-dot'),
    'integrated_header_progress' => str_contains($view, 'patient-interview-header-progress') && str_contains($view, 'interview-progress-bar'),
    'context_panel_present' => str_contains($view, 'patient-interview-conversation-layout') && str_contains($view, 'patient-interview-context-panel') && str_contains($view, 'data-context-current-module') && str_contains($view, 'data-context-summary'),
    'single_voice_panel' => substr_count($view, 'data-voice-state="DISABLED"') === 1,
    'composer_separate_from_bubble' => str_contains($view, 'interview-composer-shell') && str_contains($view, 'patient-interview-question > .patient-interview-message.assistant'),
    'composer_send_label_and_counter' => str_contains($view, 'chat-send-label') && str_contains($view, 'data-answer-count') && str_contains($view, 'maxlength="2000"'),
    'send_button_no_word_split' => str_contains($view, 'white-space:nowrap') && str_contains($view, 'overflow-wrap:normal') && str_contains($view, 'min-width:8rem'),
    'readable_question_hierarchy' => str_contains($view, 'font-size:clamp(1.35rem,2.2vw,1.65rem)') && str_contains($view, 'font-size:16px'),
    'question_voice_label' => str_contains($view, 'Escuchar esta pregunta') && !str_contains($view, "button.textContent = speaking ? '⏹ Detener' : '🔊 Leer'"),
    'responsive_1024' => str_contains($view, 'max-width:1024px') && str_contains($view, 'grid-template-columns:1fr'),
    'm07_gate_closed' => str_contains($view, 'M07_CANONICAL_DISABLED'),
];
foreach ($checks as $name => $passed) echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
if (in_array(false, $checks, true)) throw new RuntimeException('CHAT_FIRST_VOICE_CONTEXT_CONTRACT: FAIL');
echo 'CHAT_FIRST_VOICE_CONTEXT_CONTRACT: PASS (' . count($checks) . " assertions; static/view-only; no database writes)\n";
