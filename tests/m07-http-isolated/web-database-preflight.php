<?php

declare(strict_types=1);

/**
 * Prepend-only guard for the isolated M07 Apache process.
 *
 * This file is not a route and must remain denied by the isolated Apache
 * configuration. It copies only process-provided values into $_ENV before
 * public/index.php loads Dotenv, then reuses the application's Database
 * singleton. No second PDO connection is created.
 */

$fail = static function (string $code): never {
    // Código diagnóstico no sensible: no contiene credenciales, DSN ni
    // detalles de la excepción. El estado HTTP y el bloqueo no cambian.
    header('X-M07-Preflight-Failure-Code: ' . $code);
    http_response_code(503);
    if ((string) (getenv('PREFLIGHT_ONLY') ?: '') === '1') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "FAIL\n";
    } else {
        echo 'SYNTHETIC_PREFLIGHT_FAILED';
    }
    exit(1);
};

if (PHP_SAPI !== 'apache2handler') {
    $fail('SAPI_MISMATCH');
}

$required = [
    'APP_ENV',
    'APP_KEY',
    'APP_NAME',
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
    'CLINICAL_AI_SYNTHETIC_MODE',
    'CLINICAL_AI_EXTERNAL_ENABLED',
    'CLINICAL_AI_SUMMARY_ENABLED',
    'M07_HTTP_ISOLATED',
];

$optionalExternalConfiguration = [
    'GEMINI_API_KEY',
    'GEMINI_ENDPOINT',
    'OPENAI_API_KEY',
];

$values = [];
foreach ($required as $name) {
    $value = getenv($name);
    if ($value === false && array_key_exists($name, $_SERVER)) {
        $value = $_SERVER[$name];
    }
    if ($value === false || !is_scalar($value)) {
        // El nombre de la variable no es secreto y permite distinguir una
        // ausencia de propagación sin revelar su valor.
        $fail('ENV_MISSING_OR_INVALID_' . $name);
    }

    $values[$name] = (string) $value;
    // Dotenv is immutable. Seeding $_ENV before public/index.php prevents
    // the shared .env from replacing the isolated process configuration.
    $_ENV[$name] = $values[$name];
    $_SERVER[$name] = $values[$name];
}

foreach ($optionalExternalConfiguration as $name) {
    $value = getenv($name);
    if ($value === false && array_key_exists($name, $_SERVER)) {
        $value = $_SERVER[$name];
    }
    if ($value === false) {
        // La ausencia equivale a vacío sólo para configuración externa. La
        // política posterior exige que la IA esté explícitamente deshabilitada
        // y que ninguna de estas variables contenga un valor.
        $value = '';
    }
    if (!is_scalar($value)) {
        $fail('ENV_INVALID_' . $name);
    }

    $values[$name] = (string) $value;
    $_ENV[$name] = $values[$name];
    $_SERVER[$name] = $values[$name];
}

$syntheticMode = filter_var($values['CLINICAL_AI_SYNTHETIC_MODE'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
$externalEnabled = filter_var($values['CLINICAL_AI_EXTERNAL_ENABLED'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

if (
    $values['M07_HTTP_ISOLATED'] !== '1'
    || $values['APP_ENV'] !== 'testing'
    || $values['APP_KEY'] === ''
    || $values['APP_NAME'] !== 'ClinicaDolor-M07-Synthetic'
    || $values['DB_HOST'] !== '127.0.0.1'
    || $values['DB_PORT'] !== '3306'
    || $values['DB_DATABASE'] !== 'clinica_dolor_demo_20261010_final'
    || $syntheticMode !== true
    || $externalEnabled !== false
    || $values['CLINICAL_AI_SUMMARY_ENABLED'] !== '0'
    || $values['GEMINI_API_KEY'] !== ''
    || $values['GEMINI_ENDPOINT'] !== ''
    || $values['OPENAI_API_KEY'] !== ''
) {
    $fail('ENV_POLICY');
}

try {
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
    $database = \App\Config\Database::getInstance();
    $effectiveDatabase = (string) $database->query('SELECT DATABASE()')->fetchColumn();
    if ($effectiveDatabase !== 'clinica_dolor_demo_20261010_final') {
        $fail('DATABASE_MISMATCH');
    }
} catch (\Throwable) {
    $fail('DB_CONNECTION_OR_QUERY');
}

// Safe, non-sensitive marker consumed by the coordinator on normal requests.
// The permanent guard has already reused the application PDO and verified
// SELECT DATABASE(); this header does not expose connection details.
header('X-M07-Database-Guard: PASS');

if ((string) (getenv('PREFLIGHT_ONLY') ?: '') === '1') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "PASS\n";
    exit;
}
