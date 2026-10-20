<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$adapter = file_get_contents($root . '/app/Services/ConversationalM03Adapter.php');

$checks = [
    'existing_direct_endpoint' => str_contains($view, 'historia-clinica/entrevista/capturar-directo'),
    'duration_direct_mode' => str_contains($view, 'directDurationPattern') && str_contains($view, "directMode,\n                    allowEdit"),
    'previous_button_present' => str_contains($view, 'data-m03-previous') && str_contains($view, 'Pregunta anterior') && str_contains($view, 'aria-hidden="true">←</span>'),
    'conversation_navigation_not_history_back' => !str_contains($view, 'history.back('),
    'server_direct_text_adapter' => str_contains($controller, 'captureDirectText') && str_contains($adapter, 'captureDirectText'),
    'canonical_capture_reused' => str_contains($adapter, "'kind' => 'm03_proposal'") && str_contains($adapter, '$this->capture($patientId'),
    'version_guard_reused' => str_contains($adapter, '$expectedVersion'),
    'client_edit_flag_not_authorization' => !str_contains($controller, "allowEdit") && str_contains($adapter, 'unset($allowExisting)'),
    'server_edit_policy_present' => str_contains($adapter, 'serverAuthorizesPreviousEdit') && str_contains($adapter, 'DIRECT_EDIT_NOT_AUTHORIZED'),
    'edit_predecessor_policy_present' => str_contains($adapter, 'firstPending') && str_contains($adapter, '$eligible[$firstPending - 1]'),
    'capture_readback_required' => str_contains($adapter, 'M03_READBACK_FAILED') && str_contains($adapter, "'readback' => true"),
    'explicit_send_required' => str_contains($controller, 'DIRECT_EXPLICIT_SAVE_REQUIRED'),
    'no_gemini_for_direct_path' => !str_contains($controller, 'GeminiProvider') && !str_contains($adapter, 'GeminiProvider'),
];
$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name => $ok) echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
echo 'DIRECT_SUBMIT_BACK_CONTRACT: ' . ($failed === [] ? 'PASS' : 'FAIL') . PHP_EOL;
if ($failed !== []) {
    fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
