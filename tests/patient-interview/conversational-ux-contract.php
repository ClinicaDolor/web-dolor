<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$coordinator = file_get_contents($root . '/public/assets/js/patient-interview-coordinator.js');

if ($view === false || $coordinator === false) {
    throw new RuntimeException('CONVERSATIONAL_UX_FILES_NOT_READABLE');
}

$checks = [
    'five_zone_header' => str_contains($view, 'data-interview-zone="header"'),
    'five_zone_progress' => str_contains($view, 'data-interview-zone="progress"'),
    'five_zone_conversation' => str_contains($view, 'data-interview-zone="conversation"'),
    'five_zone_assistant' => str_contains($view, 'data-interview-zone="assistant"'),
    'five_zone_response' => str_contains($view, 'data-interview-zone="response"'),
    'generic_confirmation_zone_removed' => !str_contains($view, 'data-interview-zone="confirmation"'),
    'five_zone_navigation' => str_contains($view, 'data-interview-zone="navigation"'),
    'current_module_visible' => str_contains($view, 'data-interview-current-module'),
    'coordinator_state_visible' => str_contains($view, 'data-interview-state-label'),
    'state_labels_come_from_coordinator' => str_contains($view, 'syncCoordinatorState') && str_contains($view, 'onStateChange: event'),
    'progress_is_programmatic' => str_contains($view, 'role="progressbar"') && str_contains($view, 'aria-valuenow') && str_contains($view, 'aria-valuetext'),
    'status_is_live' => str_contains($view, 'data-interview-status') && str_contains($view, 'aria-live="polite"') && str_contains($view, 'aria-atomic="true"'),
    'empty_status_is_hidden' => str_contains($view, 'data-interview-status') && str_contains($view, 'aria-atomic="true" hidden') && str_contains($view, 'status.hidden = text ==='),
    'status_box_only_has_content' => str_contains($view, '.patient-interview-status:not(:empty)'),
    'error_status_is_alert' => str_contains($view, 'role\', alertStates.has(state) ? \'alert\' : \'status\''),
    'busy_state_is_exposed' => str_contains($view, 'aria-busy') && str_contains($view, 'busyStates.has(state)'),
    'response_composer_is_single_row' => str_contains($view, '.interview-composer-input') && str_contains($view, '.interview-composer .chat-send-button'),
    'mic_is_inside_input_host' => str_contains($view, 'host.appendChild(button)'),
    'quick_actions_second_row' => str_contains($view, '.interview-quick-actions, .m03-quick-actions { grid-column:1 / -1; grid-row:2;'),
    'voice_unavailable_explained' => str_contains($view, 'chat-mic-unavailable') && str_contains($view, 'usa la captura escrita'),
    'pause_resume_available' => str_contains($view, 'data-interview-pause') && str_contains($view, 'data-interview-resume'),
    'generic_confirmation_controls_removed' => !str_contains($view, 'data-proposal-confirm') && !str_contains($view, 'data-m02-confirm') && !str_contains($view, 'data-m04-confirm') && !str_contains($view, 'data-m05-confirm') && !str_contains($view, 'data-m06-confirm') && !str_contains($view, 'data-m08-confirm') && !str_contains($view, 'data-m09-confirm'),
    'm09_map_confirmation_preserved' => str_contains($view, 'data-map-confirm'),
    'direct_save_handlers_present' => str_contains($view, 'saveM02Proposal') && str_contains($view, 'saveM04Proposal') && str_contains($view, 'saveM05Proposal') && str_contains($view, 'saveM06Proposal') && str_contains($view, 'saveM08Proposal') && str_contains($view, 'saveM09Proposal'),
    'generic_review_copy_removed' => !str_contains($view, 'Interpretación propuesta') && !str_contains($view, 'Revisión clínica</strong>') && !str_contains($view, 'Revisa la propuesta'),
    'keyboard_focus_visible' => str_contains($view, 'button:focus-visible'),
    'responsive_1440_to_768' => str_contains($view, 'max-width:1199.98px') && str_contains($view, 'max-width:767.98px'),
    'responsive_430' => str_contains($view, 'max-width:430px'),
    'responsive_375' => str_contains($view, 'max-width:375px'),
    'reduced_motion' => str_contains($view, 'prefers-reduced-motion:reduce'),
    'overflow_protection' => str_contains($view, 'body.patient-interview-page { overflow-x:clip; }') && str_contains($view, 'overflow-wrap:anywhere'),
    'hidden_voice_targets_ignored' => str_contains($view, ':not([hidden]):not(.chat-stage-hidden)') && str_contains($view, "closest('[hidden], .chat-stage-hidden')"),
    'session_scoped_voice_preference' => !str_contains($view, 'localStorage') && str_contains($view, 'sessionStorage') && !str_contains($coordinator, 'localStorage') && !str_contains($coordinator, 'sessionStorage'),
    'no_external_voice_engine' => !str_contains($view, 'MediaRecorder') && !str_contains($view, 'fetch(' . "'https://"),
    'canonical_gate_preserved' => str_contains($view, 'M07_CANONICAL_DISABLED'),
];

foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
}

if (in_array(false, $checks, true)) {
    throw new RuntimeException('CONVERSATIONAL_UX_CONTRACT: FAIL');
}

echo 'CONVERSATIONAL_UX_CONTRACT: PASS (' . count($checks) . " assertions; static/view-only; no database writes)\n";
