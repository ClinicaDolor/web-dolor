<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Services/LocalDictationTicketService.php';

use App\Services\LocalDictationTicketService;

$checks = 0;
$assert = static function (bool $condition, string $name) use (&$checks): void {
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL {$name}\n");
        exit(1);
    }
};
$rejects = static function (callable $operation): bool {
    try { $operation(); return false; } catch (Throwable) { return true; }
};

$secret = random_bytes(32);
$service = new LocalDictationTicketService($secret);
$now = 1_800_000_000;
$issued = $service->issue(9301, 'synthetic-jwt-not-a-real-credential', 'M02:family:diabetes', 'https://clinica.test:8443', $now);
$claims = $service->verify($issued['ticket'], 'https://clinica.test:8443', $now + 1);
$assert($claims['pid'] === 9301, 'patient claim roundtrip');
$assert($claims['question'] === 'M02:family:diabetes', 'stable question roundtrip');
$assert($issued['expires_at'] === $now + 30, 'short expiry');
$assert($rejects(fn() => $service->verify($issued['ticket'] . 'x', 'https://clinica.test:8443', $now + 1)), 'tampering rejected');
$assert($rejects(fn() => $service->verify($issued['ticket'], 'https://evil.test:8443', $now + 1)), 'wrong origin rejected');
$assert($rejects(fn() => $service->verify($issued['ticket'], 'https://clinica.test:8443', $now + 30)), 'expired ticket rejected');
$assert($rejects(fn() => $service->issue(9301, 'synthetic', 'M07:question:1', 'https://clinica.test:8443', $now)), 'M07 ticket rejected');
$assert($rejects(fn() => $service->issue(9301, 'synthetic', 'M03:alcohol:frequency', 'https://clinica.test', $now)), 'non-M02 clinical ticket rejected');
$assert($rejects(fn() => $service->issue(0, 'synthetic', 'M02:family:diabetes', 'https://clinica.test:8443', $now)), 'invalid patient rejected');
$normalIssued = $service->issue(9302, 'synthetic-jwt-not-a-real-credential', 'M02:family:diabetes-mellitus', 'https://clinica.test', $now);
$normalClaims = $service->verify($normalIssued['ticket'], 'https://clinica.test', $now + 1);
$assert($normalClaims['origin'] === 'https://clinica.test' && $normalClaims['question'] === 'M02:family:diabetes-mellitus', 'normal HTTPS M02 ticket roundtrip');
$assert($rejects(fn() => $service->issue(9302, 'synthetic', 'M02:family:diabetes-mellitus', 'https://clinica.test.evil', $now)), 'lookalike normal origin rejected');
$diagnostic = $service->issueDiagnostic('synthetic-jwt-diagnostic', 'https://clinica.test:8443', $now);
$diagnosticClaims = $service->verifyDiagnostic($diagnostic['ticket'], 'https://clinica.test:8443', $now + 1);
$assert($diagnosticClaims['purpose'] === LocalDictationTicketService::DIAGNOSTIC_PURPOSE, 'diagnostic purpose explicit');
$assert(!isset($diagnosticClaims['pid']) && !isset($diagnosticClaims['question']), 'diagnostic ticket has no patient or question identifiers');
$assert($diagnostic['expires_at'] === $now + 30, 'diagnostic ticket short expiry');
$assert($rejects(fn() => $service->verify($diagnostic['ticket'], 'https://clinica.test:8443', $now + 1)), 'diagnostic ticket rejected by clinical verifier');
$assert($rejects(fn() => $service->issueDiagnostic('synthetic', 'https://clinica.test', $now)), 'diagnostic ticket cannot target normal application origin');
$assert($rejects(fn() => $service->verifyDiagnostic($diagnostic['ticket'], 'https://evil.test', $now + 1)), 'diagnostic ticket rejects wrong origin');
$assert($rejects(fn() => $service->verifyDiagnostic($diagnostic['ticket'], 'https://clinica.test:8443', $now + 30)), 'diagnostic ticket expires');

fwrite(STDOUT, json_encode(['status' => 'PASS', 'checks' => $checks, 'database' => 'NOT_CONNECTED'], JSON_THROW_ON_ERROR) . PHP_EOL);
