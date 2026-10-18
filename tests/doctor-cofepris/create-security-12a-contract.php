<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Controllers/CofeprisController.php';
$homePath = $root . '/app/Controllers/HomeController.php';
$modelPath = $root . '/app/Models/CofeprisModel.php';
$servicePath = $root . '/app/Services/ClinicalSummarySecurity.php';
$viewPath = $root . '/app/views/clinica/configuracion-cofepris.php';
$routesPath = $root . '/app/Config/routes.php';

$controller = file_get_contents($controllerPath);
$home = file_get_contents($homePath);
$model = file_get_contents($modelPath);
$service = file_get_contents($servicePath);
$view = file_get_contents($viewPath);
$routes = file_get_contents($routesPath);
$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(
    is_string($controller) && is_string($home) && is_string($model)
        && is_string($service) && is_string($view) && is_string($routes),
    'COFEPRIS security files must be readable'
);

if (is_string($controller)) {
    $assert(str_contains($controller, 'use App\\Services\\ClinicalSummarySecurity;'), 'controller must use the shared security service');
    $assert(str_contains($controller, '$auth = $authMiddleware->authPermisos();'), 'endpoint must retain Doctor authentication');
    $assert(str_contains($controller, '$_SERVER[\'HTTP_X_COFEPRIS_CSRF\']'), 'endpoint must read the CSRF header');
    $assert(str_contains($controller, 'validClinicActionCsrf'), 'endpoint must validate CSRF server-side');
    $assert(str_contains($controller, 'http_response_code(403)'), 'invalid CSRF must return HTTP 403');
    $assert(str_contains($controller, 'is_uploaded_file($fileTmpName)'), 'upload must be verified as an HTTP upload');
    $assert(str_contains($controller, "new \\finfo(FILEINFO_MIME_TYPE)"), 'upload content type must be checked server-side');
    $assert(str_contains($controller, '$fileExtension !== \'pdf\''), 'upload extension must remain PDF-only');
    $assert(str_contains($controller, 'guardarFolio('), 'the existing model contract must remain in use');
    $assert(str_contains($controller, "http_response_code(500)"), 'unexpected persistence errors must return a safe HTTP 500');
    $assert(!str_contains($controller, '$_POST[\'id_clinica\']'), 'clinic context must not come from the browser');
    $csrfPosition = strpos($controller, 'validClinicActionCsrf');
    $filePosition = strpos($controller, '$_FILES');
    $persistPosition = strpos($controller, 'guardarFolio(');
    $assert($csrfPosition !== false && $filePosition !== false && $csrfPosition < $filePosition, 'CSRF must precede file processing');
    $assert($csrfPosition !== false && $persistPosition !== false && $csrfPosition < $persistPosition, 'CSRF must precede persistence');
}

if (is_string($home)) {
    $assert(str_contains($home, 'ClinicalSummarySecurity::clinicActionCsrfToken'), 'global COFEPRIS view must receive a clinic-bound token');
    $assert(str_contains($home, "'cofepris_csrf'"), 'global COFEPRIS data must expose the token to the view');
}

if (is_string($service)) {
    $assert(str_contains($service, 'function clinicActionCsrfToken'), 'clinic token generator must exist');
    $assert(str_contains($service, 'function validClinicActionCsrf'), 'clinic token validator must exist');
    $assert(str_contains($service, '$_COOKIE[\'CLINICAJWT\']'), 'clinic token must be bound to the authenticated cookie');
    $assert(str_contains($service, 'hash_hmac') && str_contains($service, 'hash_equals'), 'token generation and comparison must be cryptographic');
}

if (is_string($view)) {
    $assert(str_contains($view, 'data-cofepris-csrf='), 'modal form must carry the server-rendered token');
    $assert(str_contains($view, "'X-COFEPRIS-CSRF': csrfToken"), 'modal request must send the token in a header');
    $assert(!str_contains($view, 'insert-folios?') && !str_contains($view, 'insert-folios&'), 'token must not be placed in the endpoint URL');
    $assert(str_contains($view, 'cofeprisFoliosRequestInFlight'), 'frontend duplicate-submit guard must remain');
    $assert(str_contains($view, "fetch('/clinica/cofepris/insert-folios'"), 'existing create endpoint must remain');
}

if (is_string($model)) {
    $assert(str_contains($model, 'random_int(1000, 9999)'), 'folio folder suffix must use a secure random generator');
    $assert(str_contains($model, "'source_' . bin2hex(random_bytes(16)) . '.pdf'"), 'uploaded source must use a server-generated filename');
    $assert(str_contains($model, '0750'), 'storage directories must not be created world-writable');
    $assert(str_contains($model, '@unlink($archivoOriginal)'), 'temporary source must be removed after processing');
    $assert(str_contains($model, '$cleanup'), 'partial generated files must have compensating cleanup');
    $assert(!str_contains($model, '$uploadDir . $fileName'), 'client filename must not construct a storage path');
}

if (is_string($routes)) {
    $assert(str_contains($routes, "'/cofepris/insert-folios'"), 'existing POST route must remain');
}

$serviceLoaded = false;
if (is_file($servicePath)) {
    require_once $servicePath;
    $serviceLoaded = class_exists('App\\Services\\ClinicalSummarySecurity');
}

$assert($serviceLoaded, 'shared security service must load without application bootstrapping');
if ($serviceLoaded) {
    $hadAppKey = array_key_exists('APP_KEY', $_ENV);
    $previousAppKey = $_ENV['APP_KEY'] ?? null;
    $hadCookie = array_key_exists('CLINICAJWT', $_COOKIE);
    $previousCookie = $_COOKIE['CLINICAJWT'] ?? null;

    $_ENV['APP_KEY'] = 'synthetic-contract-key';
    $_COOKIE['CLINICAJWT'] = 'synthetic-contract-session';
    $token = \App\Services\ClinicalSummarySecurity::clinicActionCsrfToken(7);
    $assert($token !== '', 'clinic token must be generated for an authenticated clinic context');
    $assert(\App\Services\ClinicalSummarySecurity::validClinicActionCsrf(7, $token), 'generated clinic token must validate');
    $assert(!\App\Services\ClinicalSummarySecurity::validClinicActionCsrf(7, 'invalid'), 'invalid token must be rejected');
    $assert(!\App\Services\ClinicalSummarySecurity::validClinicActionCsrf(8, $token), 'token from another clinic scope must be rejected');

    unset($_COOKIE['CLINICAJWT']);
    $assert(\App\Services\ClinicalSummarySecurity::clinicActionCsrfToken(7) === '', 'token must not be generated without an authenticated cookie');

    if ($hadAppKey) {
        $_ENV['APP_KEY'] = $previousAppKey;
    } else {
        unset($_ENV['APP_KEY']);
    }
    if ($hadCookie) {
        $_COOKIE['CLINICAJWT'] = $previousCookie;
    } else {
        unset($_COOKIE['CLINICAJWT']);
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "COFEPRIS create security 12A contract: PASS" . PHP_EOL;
