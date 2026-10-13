<?php

declare(strict_types=1);

// El servidor sintético debe exponer explícitamente sus variables al contrato
// de configuración que usa la aplicación (`$_ENV`). No se carga `.env` aquí ni
// se habilita ningún bypass de autenticación.
foreach (['APP_KEY', 'APP_ENV', 'DB_DATABASE', 'P09_TEST_DATABASE', 'P04_TEST_DATABASE', 'P04_TEST_MODE'] as $name) {
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
