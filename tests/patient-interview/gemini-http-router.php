<?php
declare(strict_types=1);

// The built-in server receives process variables through getenv(). Populate
// $_ENV explicitly before the immutable project bootstrap reads configuration.
foreach (['APP_KEY', 'APP_ENV', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'P04_TEST_DATABASE', 'CLINICAL_AI_SYNTHETIC_MODE', 'CLINICAL_AI_EXTERNAL_ENABLED', 'GEMINI_API_KEY'] as $name) {
    $value = getenv($name);
    if ($value !== false) {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

require dirname(__DIR__) . '/p04/router.php';
return true;
