<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Services/LocalVoiceDiagnosticPolicy.php';

use App\Services\LocalVoiceDiagnosticPolicy;

$checks = 0;
$assert = static function (bool $condition, string $name) use (&$checks): void {
    $checks++;
    if (!$condition) { fwrite(STDERR, "FAIL {$name}\n"); exit(1); }
};

$oldProfile = getenv('USERPROFILE');
$profile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'voice-diagnostic-' . bin2hex(random_bytes(6));
$runtime = $profile . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'ClinicaDolor' . DIRECTORY_SEPARATOR . 'vosk-realtime';
mkdir($runtime, 0700, true);
file_put_contents($runtime . DIRECTORY_SEPARATOR . 'ticket.key', random_bytes(32));
$configPath = $runtime . DIRECTORY_SEPARATOR . 'diagnostic.json';
file_put_contents($configPath, json_encode(['version' => 1, 'enabled' => true, 'mode' => 'isolated_https', 'origin' => LocalVoiceDiagnosticPolicy::ORIGIN], JSON_THROW_ON_ERROR));
putenv('USERPROFILE=' . $profile);
$_SERVER = array_merge($_SERVER, [
    'HTTPS' => 'on', 'SERVER_NAME' => 'clinica.test', 'HTTP_HOST' => 'clinica.test:8443',
    'SERVER_PORT' => '8443', 'SERVER_ADDR' => '127.0.0.1', 'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_ORIGIN' => LocalVoiceDiagnosticPolicy::ORIGIN, 'HTTP_SEC_FETCH_SITE' => 'same-origin',
]);
$enabled = LocalVoiceDiagnosticPolicy::enabledRuntime();
$assert(is_array($enabled) && $enabled['ticket_key'] === $runtime . DIRECTORY_SEPARATOR . 'ticket.key', 'explicit local config and loopback request enable diagnostic');
$assert(LocalVoiceDiagnosticPolicy::validRequestOrigin($enabled), 'exact isolated origin and same-origin fetch required');
$_SERVER['REMOTE_ADDR'] = '192.0.2.5';
$assert(LocalVoiceDiagnosticPolicy::enabledRuntime() === null, 'remote request denied');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'clinica.test.evil';
$assert(LocalVoiceDiagnosticPolicy::enabledRuntime() === null, 'lookalike host denied');
$_SERVER['HTTP_HOST'] = 'clinica.test:8443';
$_SERVER['HTTP_ORIGIN'] = 'https://clinica.test';
$assert(!LocalVoiceDiagnosticPolicy::validRequestOrigin($enabled), 'normal application origin rejected');
$_SERVER['HTTP_ORIGIN'] = LocalVoiceDiagnosticPolicy::ORIGIN;
$_SERVER['SERVER_PORT'] = '443';
$assert(LocalVoiceDiagnosticPolicy::enabledRuntime() === null, 'normal HTTPS port rejected');
$_SERVER['SERVER_PORT'] = '8443';
file_put_contents($configPath, json_encode(['version' => 1, 'enabled' => false, 'mode' => 'isolated_https', 'origin' => LocalVoiceDiagnosticPolicy::ORIGIN], JSON_THROW_ON_ERROR));
$assert(LocalVoiceDiagnosticPolicy::enabledRuntime() === null, 'disabled config remains closed');

@unlink($configPath);
@unlink($runtime . DIRECTORY_SEPARATOR . 'ticket.key');
@rmdir($runtime);
@rmdir(dirname($runtime));
@rmdir(dirname(dirname($runtime)));
@rmdir(dirname(dirname(dirname($runtime))));
@rmdir($profile);
if ($oldProfile === false) putenv('USERPROFILE'); else putenv('USERPROFILE=' . $oldProfile);

fwrite(STDOUT, json_encode(['status' => 'PASS', 'checks' => $checks, 'database' => 'NOT_CONNECTED'], JSON_THROW_ON_ERROR) . PHP_EOL);
