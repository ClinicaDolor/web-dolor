<?php

namespace App\Core;

/**
 * Boundary for unexpected application failures.
 *
 * The handler deliberately logs only a category. Exception messages may
 * contain SQL, filesystem paths or clinical values and must not be rendered
 * or copied to the application log by this boundary.
 */
final class SafeErrorHandler
{
    private static bool $responded = false;

    public static function register(): void
    {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
        ob_start();

        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(\Throwable $exception): void
    {
        error_log('[clinica] Unhandled application exception');
        self::respond(500);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if (!is_array($error) || !in_array($error['type'] ?? null, [
            E_ERROR,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_PARSE,
        ], true)) {
            return;
        }

        error_log('[clinica] Fatal application error');
        self::respond(500);
    }

    private static function respond(int $status): void
    {
        if (self::$responded) {
            return;
        }

        self::$responded = true;

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);
        header('Cache-Control: no-store');

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if (str_starts_with($path, '/buscar/tabla-')) {
            header('Content-Type: text/html; charset=UTF-8');
            echo '<div class="patient-record-async-state patient-record-async-state-error" role="alert">'
                . '<span>No se pudo cargar la información clínica.</span>'
                . '</div>';
            return;
        }

        if (self::expectsJson()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'resultado' => false,
                'mensaje' => 'No se pudo completar la solicitud.',
                'token' => '',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="es"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Solicitud no disponible</title></head><body>'
            . '<main role="alert"><h1>No se pudo completar la solicitud.</h1>'
            . '<p>Inténtalo más tarde.</p></main></body></html>';
    }

    private static function expectsJson(): bool
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type: application/json') === 0) {
                return true;
            }
        }

        return stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
    }
}
