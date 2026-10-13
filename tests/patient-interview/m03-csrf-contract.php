<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\ClinicalSummarySecurity;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$_ENV['APP_KEY'] = 'synthetic-m03-csrf-test-key';
$_COOKIE['CLINICAJWT'] = 'synthetic-session-a';
$token = ClinicalSummarySecurity::captureCsrfToken(9301);

$assert($token !== '', 'authenticated synthetic cookie should produce a token');
$assert(!ClinicalSummarySecurity::validCaptureCsrf(9301, null), 'missing token must be rejected');
$assert(!ClinicalSummarySecurity::validCaptureCsrf(9301, 'invalid'), 'invalid token must be rejected');
$assert(!ClinicalSummarySecurity::validCaptureCsrf(9302, $token), 'token must be scoped to patient');
$assert(ClinicalSummarySecurity::validCaptureCsrf(9301, $token), 'matching token must be accepted');

$_COOKIE['CLINICAJWT'] = 'synthetic-session-b';
$assert(!ClinicalSummarySecurity::validCaptureCsrf(9301, $token), 'token must be bound to the session cookie');

if ($failures) {
    fwrite(STDERR, "M03 CSRF contract: FAIL\n" . implode("\n", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, "M03 CSRF contract: PASS (6 assertions; no database writes)\n");
