<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$guard = file_get_contents($root . '/tests/m07-http-isolated/web-database-preflight.php');
$provider = file_get_contents($root . '/app/Services/GeminiProvider.php');
$service = file_get_contents($root . '/app/Services/ClinicalSummaryService.php');

$checks = [
    'external_values_optional_only_in_guard' => str_contains($guard, '$optionalExternalConfiguration')
        && str_contains($guard, 'if ($value === false) {'),
    'external_disabled_required' => str_contains($guard, '|| $externalEnabled !== false'),
    'empty_gemini_key_rejected_if_present' => str_contains($guard, "|| \$values['GEMINI_API_KEY'] !== ''"),
    'empty_endpoint_rejected_if_present' => str_contains($guard, "|| \$values['GEMINI_ENDPOINT'] !== ''"),
    'empty_openai_key_rejected_if_present' => str_contains($guard, "|| \$values['OPENAI_API_KEY'] !== ''"),
    'synthetic_database_guard_preserved' => str_contains($guard, "SELECT DATABASE()")
        && str_contains($guard, 'clinica_dolor_demo_20261010_final'),
    'provider_requires_key_only_when_called' => str_contains($provider, "if (\$key==='') throw new \\RuntimeException('GEMINI_NOT_CONFIGURED');"),
    'service_does_not_call_provider_when_disabled' => str_contains($service, 'if (ClinicalSummarySecurity::externalEnabled())'),
    'no_external_endpoint_in_guard' => !str_contains($guard, 'curl_init')
        && !str_contains($guard, 'generateContent'),
];

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
if ($failed !== []) {
    fwrite(STDERR, 'Gemini environment policy contract: FAIL ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Gemini environment policy contract: PASS (' . count($checks) . " assertions; no HTTP/MySQL)\n";
