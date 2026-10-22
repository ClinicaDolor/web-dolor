<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/app/Controllers/BaseController.php';
require_once dirname(__DIR__, 2) . '/app/Services/ClinicalSummarySecurity.php';
require_once dirname(__DIR__, 2) . '/app/Services/LocalDictationTicketService.php';
require_once dirname(__DIR__, 2) . '/app/Services/LocalVoiceDiagnosticPolicy.php';
require_once dirname(__DIR__, 2) . '/app/Core/HttpMethod.php';
require_once dirname(__DIR__, 2) . '/app/Controllers/LocalVoiceDiagnosticController.php';

use App\Controllers\LocalVoiceDiagnosticController;
use Firebase\JWT\JWT;
use function FastRoute\simpleDispatcher;

$checks = 0;
$assert = static function (bool $condition, string $name) use (&$checks): void {
    $checks++;
    if (!$condition) { fwrite(STDERR, "FAIL {$name}\n"); exit(1); }
};
$key = random_bytes(32);
$_ENV['APP_KEY'] = $key;
$_ENV['APP_NAME'] = 'voice-diagnostic-synthetic-test';
$controller = new LocalVoiceDiagnosticController();
$method = new ReflectionMethod($controller, 'authenticatedPatientId');
$method->setAccessible(true);
$now = time();
$base = ['iat' => $now, 'iss' => $_ENV['APP_NAME'], 'exp' => $now + 60, 'id_clinica' => 9101, 'id_usuario' => 9301, 'nombre' => 'Synthetic', 'vista' => 'historia-clinica', 'rol' => 'Paciente'];
$_COOKIE['CLINICAJWT'] = JWT::encode($base, $key, 'HS256');
$assert($method->invoke($controller) === 9301, 'valid signed patient session accepted without database');
$csrf = App\Services\ClinicalSummarySecurity::captureCsrfToken(9301);
$assert(App\Services\ClinicalSummarySecurity::validCaptureCsrf(9301, $csrf), 'capture CSRF is bound to the authenticated cookie');
$assert(!App\Services\ClinicalSummarySecurity::validCaptureCsrf(9302, $csrf), 'capture CSRF cannot cross patient scope');
$_COOKIE['CLINICAJWT'] = JWT::encode([...$base, 'rol' => 'Doctor', 'vista' => 'clinica'], $key, 'HS256');
$assert($method->invoke($controller) === null, 'doctor session rejected by patient-only diagnostic');
$_COOKIE['CLINICAJWT'] = JWT::encode([...$base, 'exp' => $now - 1], $key, 'HS256');
$assert($method->invoke($controller) === null, 'expired session rejected');
unset($_COOKIE['CLINICAJWT']);
$assert($method->invoke($controller) === null, 'anonymous request rejected');
$dispatcher = simpleDispatcher(require dirname(__DIR__, 2) . '/app/Config/routes.php');
$pageRoute = $dispatcher->dispatch('GET', '/historia-clinica/diagnostico-voz');
$ticketRoute = $dispatcher->dispatch('POST', '/historia-clinica/diagnostico-voz/ticket');
$clinicalTicketRoute = $dispatcher->dispatch('POST', '/historia-clinica/entrevista/dictation-ticket');
$assert($pageRoute[0] === FastRoute\Dispatcher::FOUND && $pageRoute[1] === ['LocalVoiceDiagnosticController', 'index'], 'diagnostic GET route resolves');
$assert($ticketRoute[0] === FastRoute\Dispatcher::FOUND && $ticketRoute[1] === ['LocalVoiceDiagnosticController', 'issueTicket'], 'diagnostic ticket route resolves separately');
$assert($clinicalTicketRoute[0] === FastRoute\Dispatcher::FOUND && $clinicalTicketRoute[1] === ['PatientInterviewController', 'issueDictationTicket'], 'clinical ticket route remains unchanged');

fwrite(STDOUT, json_encode(['status' => 'PASS', 'checks' => $checks, 'database' => 'NOT_CONNECTED'], JSON_THROW_ON_ERROR) . PHP_EOL);
