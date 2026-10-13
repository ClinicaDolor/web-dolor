<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$provider = file_get_contents($root . '/app/Services/GeminiProvider.php');
$service = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$env = file_get_contents($root . '/.env');
$assertions = [
    'existing_provider_reused' => str_contains($service, 'GeminiProvider'),
    'm02_service_bridge' => str_contains($service, 'public function interpretM02'),
    'm03_service_bridge' => str_contains($controller, 'conversationService()->interpret('),
    'm02_controller_bridge' => str_contains($controller, '->interpretM02('),
    'synthetic_db_allowlist' => str_contains($service, "clinica_dolor_browser_20261012") && str_contains($service, 'SELECT DATABASE()'),
    'usage_metadata_read' => str_contains($provider, 'usageMetadata') && str_contains($provider, 'promptTokenCount'),
    'structured_json_requested' => str_contains($provider, 'responseMimeType') && str_contains($provider, 'responseSchema'),
    'safe_context_bounded' => str_contains($provider, 'safeConversationInput') && str_contains($provider, 'allowed_values'),
    'response_size_limited' => str_contains($provider, 'GEMINI_RESPONSE_TOO_LARGE') && str_contains($provider, '65536'),
    'global_external_disabled' => preg_match('/^CLINICAL_AI_EXTERNAL_ENABLED\s*=\s*0\s*$/m', $env) === 1,
    'real_data_release_not_enabled' => preg_match('/^CLINICAL_AI_SYNTHETIC_MODE\s*=\s*0\s*$/m', $env) === 1,
];
if (in_array(false, $assertions, true)) {
    fwrite(STDERR, "Gemini interview contract: FAIL\n" . json_encode($assertions, JSON_PRETTY_PRINT) . "\n");
    exit(1);
}
echo 'Gemini interview contract: PASS (' . count($assertions) . " assertions; interview-only bridge)\n";
