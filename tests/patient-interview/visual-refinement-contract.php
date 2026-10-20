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

$checks = [
    'local_feather_loaded' => str_contains($view, 'feather-icons/feather.min.js'),
    'sidebar_toggle_accessible_without_duplicate_injection' => str_contains($view, 'data-interview-sidebar-toggle') && str_contains($view, 'aria-controls="sidebar"') && !str_contains($view, 'patient-interview-sidebar-header-toggle'),
    'interview_sidebar_item_is_active' => str_contains($sidebar, "'titulo' => 'Entrevista Conversacional'") && str_contains($controller, "setActivarItem('Entrevista Conversacional')"),
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
