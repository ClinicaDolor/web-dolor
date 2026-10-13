<?php
declare(strict_types=1);

// Router exclusivo del arnés Fase 6.7. Reaplica los overrides sintéticos
// después de cargar .env para que el front controller no reactive la
// configuración habitual del proyecto. Nunca imprime secretos.
$projectRoot = dirname(__DIR__, 2);
$names = [
    'APP_KEY', 'APP_ENV', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME',
    'DB_PASSWORD', 'P04_TEST_DATABASE', 'CLINICAL_AI_SYNTHETIC_MODE',
    'CLINICAL_AI_EXTERNAL_ENABLED', 'CLINICAL_AI_TRACE', 'CLINICAL_AI_TRACE_ID',
    'CLINICAL_AI_TRACE_FILE', 'GEMINI_API_KEY', 'GEMINI_MODEL', 'GEMINI_ENDPOINT',
];

$applyProcessEnvironment = static function () use ($names): void {
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
};

$applyProcessEnvironment();

// Dotenv es inmutable, pero la carga debe ocurrir antes de fijar el contrato
// sintético final del proceso para evitar que el .env habitual sustituya los
// flags del arnés.
require $projectRoot . '/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable($projectRoot))->load();
$applyProcessEnvironment();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/__fase67/env') {
    $database = null;
    try {
        $pdo = new PDO(
            'mysql:host=' . (string)($_ENV['DB_HOST'] ?? '') . ';port=' . (string)($_ENV['DB_PORT'] ?? '3306') . ';dbname=' . (string)($_ENV['DB_DATABASE'] ?? '') . ';charset=utf8mb4',
            (string)($_ENV['DB_USERNAME'] ?? ''),
            (string)($_ENV['DB_PASSWORD'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    } catch (Throwable) {
        $database = null;
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'external_enabled' => (string)($_ENV['CLINICAL_AI_EXTERNAL_ENABLED'] ?? ''),
        'synthetic_mode' => (string)($_ENV['CLINICAL_AI_SYNTHETIC_MODE'] ?? ''),
        'configured_database' => (string)($_ENV['DB_DATABASE'] ?? ''),
        'effective_database' => $database,
        'gemini_key_present' => trim((string)($_ENV['GEMINI_API_KEY'] ?? '')) !== '',
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    return true;
}

require $projectRoot . '/tests/p04/router.php';
return true;
