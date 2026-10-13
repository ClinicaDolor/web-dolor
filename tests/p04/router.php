<?php

declare(strict_types=1);

// Front controller de pruebas: sólo mapea assets locales y entrega las rutas
// a public/index.php. No crea sesiones ni omite autenticación.
$projectRoot = dirname(__DIR__, 2);
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$assetPath = null;
if (str_starts_with($requestPath, '/public/')) {
    $assetPath = $projectRoot . $requestPath;
} elseif (str_starts_with($requestPath, '/assets/')) {
    $assetPath = $projectRoot . '/public' . $requestPath;
}

if ($assetPath !== null) {
    $realAsset = realpath($assetPath);
    $publicRoot = realpath($projectRoot . '/public');
    $publicPrefix = $publicRoot ? rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';

    if ($realAsset && is_file($realAsset) && strncmp($realAsset, $publicPrefix, strlen($publicPrefix)) === 0) {
        $mime = match (strtolower(pathinfo($realAsset, PATHINFO_EXTENSION))) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            default => mime_content_type($realAsset) ?: 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        readfile($realAsset);
        return true;
    }

    http_response_code(404);
    return true;
}

require $projectRoot . '/public/index.php';
return true;

