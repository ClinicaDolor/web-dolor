<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/Services/ClinicalSummarySecurity.php';

use App\Services\ClinicalSummarySecurity;

$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$security = file_get_contents($root . '/app/Services/ClinicalSummarySecurity.php');
$launcher = file_get_contents($root . '/tests/patient-interview/start-isolated-browser-server.ps1');
$routes = file_get_contents($root . '/app/Config/routes.php');
$dotenv = file_get_contents($root . '/.env');
$recognitionStart = strpos($view, 'const startRecognition');
$recognitionEnd = strpos($view, 'function bindVoiceControls');
$recognitionBlock = ($recognitionStart !== false && $recognitionEnd !== false && $recognitionEnd > $recognitionStart)
    ? substr($view, $recognitionStart, $recognitionEnd - $recognitionStart)
    : '';

$checks = [];
$check = static function (string $name, bool $ok) use (&$checks): void { $checks[$name] = $ok; };

$check('controller_passes_native_gate', str_contains($controller, "'nativeSttEnabled' => ClinicalSummarySecurity::nativeSpeechEnabled()"));
$check('temporary_authorization_route_removed', !str_contains($routes, 'autorizar-dictado-local'));
$check('real_database_not_authorized', !str_contains($security, "\$database === 'bd_clinica'"));
$check('synthetic_gate_requires_external_disabled', str_contains($security, '!self::externalEnabled()'));
$check('temporary_session_authorization_removed', !str_contains($security, 'native_stt_local_test') && !str_contains($security, 'authorizeNativeSpeechTestSession'));
$check('temporary_write_guard_removed', !str_contains($controller, 'rejectNativeSttTestWrite') && !str_contains($controller, 'NATIVE_STT_READ_ONLY_SESSION'));
$check('temporary_readonly_ui_removed', !str_contains($view, 'native-stt-test-panel') && !str_contains($view, 'nativeSttTestMode') && !str_contains($view, 'nativeSttActivationAvailable'));
$check('security_gate_requires_native_flag', str_contains($security, "self::flag('NATIVE_STT_ENABLED', false)"));
$check('security_gate_requires_synthetic_mode', str_contains($security, 'self::syntheticMode()'));
$check('security_gate_requires_testing_environment', str_contains($security, "\$environment === 'testing'"));
$check('synthetic_launcher_sets_native_flag', str_contains($launcher, "NATIVE_STT_ENABLED='1'"));
$check('synthetic_launcher_disables_external_ai', str_contains($launcher, "CLINICAL_AI_EXTERNAL_ENABLED='0'"));
$check('application_env_does_not_enable_native_stt', preg_match('/^NATIVE_STT_ENABLED\s*=\s*1\s*$/m', $dotenv) !== 1);
$check('application_env_external_ai_disabled', preg_match('/^CLINICAL_AI_EXTERNAL_ENABLED\s*=\s*0\s*$/m', $dotenv) === 1);
$check('application_env_real_patient_mode', preg_match('/^CLINICAL_AI_SYNTHETIC_MODE\s*=\s*0\s*$/m', $dotenv) === 1);
$check('single_main_compositor_root', substr_count($view, 'id="main"') === 1);
$check('speech_constructor_detected', str_contains($view, 'window.SpeechRecognition || window.webkitSpeechRecognition'));
$check('spanish_language_configured', str_contains($view, "recognition.lang = 'es-MX'"));
$check('continuous_mode_configured', str_contains($view, 'recognition.continuous = true'));
$check('interim_mode_configured', str_contains($view, 'recognition.interimResults = true'));
$check('all_required_events_bound', str_contains($view, 'recognition.onstart') && str_contains($view, 'recognition.onresult') && str_contains($view, 'recognition.onerror') && str_contains($view, 'recognition.onend'));
$check('local_processing_not_forced', !str_contains($view, 'processLocally'));
$check('textarea_binding_is_supported', str_contains($view, '.patient-interview-answer textarea'));
$check('speech_segments_are_merged', str_contains($view, 'combineSpeechResults') && str_contains($view, 'const composedText'));
$check('tts_stops_before_stt', str_contains($view, 'stopAssistantVoice();') && str_contains($view, 'waitForVoiceCancellation'));
$check('recognition_start_is_race_protected', str_contains($view, 'recognitionStartPending') && str_contains($view, 'recognitionGeneration'));
$check('recognition_errors_cleanup_state', str_contains($view, 'finishRecognition(state, state.error)') && str_contains($view, "'no-speech'"));
$check('navigation_stops_recognition', str_contains($view, "['NAVIGATING', 'PAUSED', 'MODULE_BLOCKED', 'MODULE_COMPLETED', 'SESSION_EXPIRED']") && str_contains($view, 'stopRecognition();'));
$check('stt_statuses_are_exposed', str_contains($view, "'PREPARING'") && str_contains($view, "'LISTENING'") && str_contains($view, "'TRANSCRIPT_READY'"));
$check('no_experimental_confirmation_dialog', !str_contains($recognitionBlock, 'window.confirm(') && !str_contains($recognitionBlock, 'alert('));
$check('clinical_confirmation_preserved', str_contains($view, 'Confirma explícitamente que los medicamentos no seleccionados'));
$check('privacy_warning_documented', str_contains(file_get_contents($root . '/docs/NATIVE_STT_IMPLEMENTATION_AND_CLOSURE.md'), 'No se fuerza `processLocally`') && str_contains(file_get_contents($root . '/docs/NATIVE_STT_IMPLEMENTATION_AND_CLOSURE.md'), 'procesar audio'));
$check('manual_send_only', $recognitionBlock !== '' && !str_contains($recognitionBlock, 'fetch('));
$check('single_active_recognizer_guard', str_contains($view, 'if (activeRecognition)'));
$check('manual_edit_state_present', str_contains($view, 'activeRecognitionState.manualEdit = true'));
$check('duplicate_overlap_guard_present', str_contains($view, 'appendSpeechText'));
$check('real_route_defaults_disabled', (function (): bool {
    $_ENV['APP_ENV'] = 'production';
    $_ENV['CLINICAL_AI_SYNTHETIC_MODE'] = '0';
    $_ENV['NATIVE_STT_ENABLED'] = '1';
    $_ENV['DB_DATABASE'] = 'bd_clinica';
    return ClinicalSummarySecurity::nativeSpeechEnabled() === false;
})());
$check('local_development_gate_rejects_real_database', (function (): bool {
    $_SERVER['HTTP_HOST'] = 'clinica.test';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_ENV['APP_ENV'] = 'development';
    $_ENV['CLINICAL_AI_SYNTHETIC_MODE'] = '0';
    $_ENV['CLINICAL_AI_EXTERNAL_ENABLED'] = '0';
    $_ENV['DB_DATABASE'] = 'bd_clinica';
    return ClinicalSummarySecurity::nativeSpeechEnabled() === false;
})());
$check('local_gate_rejects_production', (function (): bool {
    $_ENV['APP_ENV'] = 'production';
    return ClinicalSummarySecurity::nativeSpeechEnabled() === false;
})());
$check('testing_synthetic_gate_allows', (function (): bool {
    $_ENV['APP_ENV'] = 'testing';
    $_ENV['CLINICAL_AI_SYNTHETIC_MODE'] = '1';
    $_ENV['NATIVE_STT_ENABLED'] = '1';
    $_ENV['DB_DATABASE'] = 'clinica_dolor_browser_20261012';
    return ClinicalSummarySecurity::nativeSpeechEnabled() === true;
})());
$check('synthetic_gate_rejects_external_provider', (function (): bool {
    $_ENV['APP_ENV'] = 'testing';
    $_ENV['CLINICAL_AI_SYNTHETIC_MODE'] = '1';
    $_ENV['NATIVE_STT_ENABLED'] = '1';
    $_ENV['CLINICAL_AI_EXTERNAL_ENABLED'] = '1';
    $_ENV['DB_DATABASE'] = 'clinica_dolor_browser_20261012';
    $rejected = ClinicalSummarySecurity::nativeSpeechEnabled() === false;
    $_ENV['CLINICAL_AI_EXTERNAL_ENABLED'] = '0';
    return $rejected;
})());
$check('real_database_never_allowed', (function (): bool {
    $_ENV['APP_ENV'] = 'testing';
    $_ENV['CLINICAL_AI_SYNTHETIC_MODE'] = '1';
    $_ENV['NATIVE_STT_ENABLED'] = '1';
    $_ENV['DB_DATABASE'] = 'bd_clinica';
    return ClinicalSummarySecurity::nativeSpeechEnabled() === false;
})());

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name => $ok) echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
echo 'NATIVE_STT_CONTRACT: ' . ($failed === [] ? 'PASS' : 'FAIL') . PHP_EOL;
if ($failed !== []) {
    fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
