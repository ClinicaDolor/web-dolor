<?php

// Router exclusivo para pruebas locales. Nunca debe exponerse como entrada pública.
$auditDatabase = getenv('CLINICA_AUDIT_DB') ?: 'clinica_dolor_audit_20261010_iter3';
putenv('DB_DATABASE=' . $auditDatabase);
$_ENV['DB_DATABASE'] = $auditDatabase;
$_SERVER['DOCUMENT_ROOT'] = __DIR__ . DIRECTORY_SEPARATOR . 'runtime';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$projectRoot = dirname(__DIR__, 2);
$publicRoot = realpath($projectRoot . '/public');

if (str_starts_with($requestPath, '/public/')) {
    $assetPath = realpath($projectRoot . $requestPath);
    $publicPrefix = $publicRoot ? rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
    if ($assetPath && is_file($assetPath) && strncmp($assetPath, $publicPrefix, strlen($publicPrefix)) === 0) {
        $mime = match (strtolower(pathinfo($assetPath, PATHINFO_EXTENSION))) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff', 'woff2' => 'font/woff2',
            default => mime_content_type($assetPath) ?: 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        readfile($assetPath);
        exit;
    }
    http_response_code(404);
    exit('Asset no encontrado.');
}

if ($requestPath === '/__audit-session/doctor' || $requestPath === '/__audit-session/patient') {
    require $projectRoot . '/vendor/autoload.php';
    Dotenv\Dotenv::createImmutable($projectRoot)->load();
    $isPatient = $requestPath === '/__audit-session/patient';
    $claims = [
        'iat' => time(),
        'exp' => time() + 3600,
        'id_clinica' => 9101,
        'id_usuario' => $isPatient ? 9301 : 9201,
        'nombre' => $isPatient ? 'Paciente sintético A' : 'Doctor sintético A',
        'vista' => $isPatient ? 'historia-clinica' : 'clinica',
        'rol' => $isPatient ? 'PACIENTE' : 'DOCTOR',
    ];
    $token = Firebase\JWT\JWT::encode($claims, $_ENV['APP_KEY'], 'HS256');
    setcookie('CLINICAJWT', $token, [
        'expires' => time() + 3600,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    header('Location: ' . ($isPatient ? '/historia-clinica' : '/clinica'));
    exit;
}

require dirname(__DIR__, 2) . '/public/index.php';
