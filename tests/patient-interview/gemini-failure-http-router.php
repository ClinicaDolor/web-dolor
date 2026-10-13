<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
foreach (['APP_KEY', 'APP_ENV', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'P04_TEST_DATABASE', 'CLINICAL_AI_SYNTHETIC_MODE', 'CLINICAL_AI_EXTERNAL_ENABLED'] as $name) {
    $value = getenv($name);
    if ($value !== false) {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

require $root . '/vendor/autoload.php';
require $root . '/app/Config/constantes.php';

final class Fase66SyntheticProvider implements App\Services\ConversationalProvider
{
    private static int $lateCalls = 0;

    public function __construct(private string $scenario) {}

    public function interpretConversation(array $input): array
    {
        return match ($this->scenario) {
            'timeout' => throw new RuntimeException('GEMINI_TIMEOUT_SIMULATED'),
            'network' => throw new RuntimeException('GEMINI_TRANSPORT'),
            'empty' => [],
            'malformed' => ['structured_value' => 'Si'],
            'out_of_catalog' => $this->response('', 'tío'),
            'contradictory' => $this->response('No', 'padre'),
            'late_first' => throw new RuntimeException('GEMINI_LATE_RESPONSE_SIMULATED'),
            'late_retry' => $this->response('', 'padre'),
            'late' => $this->lateResponse(),
            default => $this->normalResponse((string)($input['utterance'] ?? '')),
        };
    }

    private function lateResponse(): array
    {
        if (self::$lateCalls++ === 0) {
            usleep(100000);
            throw new RuntimeException('GEMINI_LATE_RESPONSE_SIMULATED');
        }
        return $this->response('', 'padre');
    }

    private function normalResponse(string $utterance): array
    {
        return preg_match('/\b(s[ií]|sí)\b/iu', $utterance) === 1
            ? $this->response('Si', 'padre')
            : $this->response('', 'padre');
    }

    private function response(string $structuredValue, string $relative): array
    {
        return [
            'kind' => 'synthetic', 'structured_value' => $structuredValue,
            'interpretation' => $structuredValue, 'clarification' => '',
            'relative' => $relative, 'diabetes_type' => '', 'explanation' => '',
        ];
    }
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path !== '/__fase66/interpret') {
    http_response_code(404);
    echo 'NOT_FOUND';
    return true;
}

$scenario = is_string($_GET['scenario'] ?? null) ? (string)$_GET['scenario'] : 'repeat';
$controller = new App\Controllers\PatientInterviewController(
    new App\Services\ConversationalInterviewService(null, new Fase66SyntheticProvider($scenario))
);
$controller->interpret();
return true;
