<?php

declare(strict_types=1);

use App\Config\Database;
use Dotenv\Dotenv;
use Firebase\JWT\JWT;

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_demo_20261010_final';
$baseUrl = 'http://127.0.0.1:8087';
$appKey = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $database = substr($argument, strlen('--database='));
    if (str_starts_with($argument, '--base-url=')) $baseUrl = rtrim(substr($argument, strlen('--base-url=')), '/');
    if (str_starts_with($argument, '--app-key=')) $appKey = substr($argument, strlen('--app-key='));
}

if ($database !== 'clinica_dolor_demo_20261010_final') {
    fwrite(STDERR, "P05 HTTP: FAIL (base no allowlisted)\n");
    exit(2);
}
$p05AppKey = $appKey !== '' ? $appKey : (string)($_ENV['APP_KEY'] ?? '');
if ($p05AppKey === '') {
    fwrite(STDERR, "P05 HTTP: FAIL (falta APP_KEY del servidor)\n");
    exit(2);
}

require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->load();
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;
$pdo = Database::getInstance();
if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
    fwrite(STDERR, "P05 HTTP: FAIL (base activa no coincide)\n");
    exit(2);
}

$token = static function (int $clinic, int $patient) use ($p05AppKey): string {
    return JWT::encode([
        'iat' => time(),
        'exp' => time() + 600,
        'id_clinica' => $clinic,
        'id_usuario' => $patient,
        'nombre' => 'Paciente sintético ' . $patient,
        'vista' => 'historia-clinica',
        'rol' => 'Paciente',
    ], $p05AppKey, 'HS256');
};

$request = static function (string $url, string $jwt, ?array $payload = null) use ($p05AppKey): array {
    $method = $payload === null ? 'GET' : 'POST';
    $headers = "Host: p05clean.localhost:8087\r\nCookie: CLINICAJWT={$jwt}\r\n";
    if ($payload !== null && isset($payload['idPaciente'])) {
        $patientId = (int)$payload['idPaciente'];
        $csrf = hash_hmac(
            'sha256',
            'm03-capture:' . $patientId . ':' . hash('sha256', $jwt),
            $p05AppKey
        );
        $headers .= "X-M03-CSRF: {$csrf}\r\n";
    }
    $options = [
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'follow_location' => 0,
            'header' => $headers . ($payload === null ? '' : "Content-Type: application/json\r\n"),
            'content' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
        ],
    ];
    $body = file_get_contents($url, false, stream_context_create($options));
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            $status = (int)$matches[1];
            break;
        }
    }
    return [$status, (string)$body];
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$response = $pdo->prepare(
    'SELECT id, respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = ? AND id_pregunta = 1 LIMIT 1'
);
$response->execute([9301]);
$beforeRow = $response->fetch(PDO::FETCH_ASSOC);

try {
    [$statusPage, $bodyPage] = $request(
        $baseUrl . '/historia-clinica/antecedentes-personales-no-patologicos/paciente/9301',
        $token(9101, 9301)
    );
    $assert($statusPage === 200 && str_contains($bodyPage, 'Antecedentes Personales No Patológicos'), 'GET de la vista P05 no fue autorizado.');

    [$statusOwnGet, $bodyOwnGet] = $request(
        $baseUrl . '/buscar/contenido-preguntas-modulo-3/9301/Paciente/1',
        $token(9101, 9301)
    );
    $assert($statusOwnGet === 200 && str_contains($bodyOwnGet, 'pregunta-container'), 'GET propio no fue autorizado; HTTP ' . $statusOwnGet . '; respuesta: ' . substr(trim(strip_tags($bodyOwnGet)), 0, 120));

    $response->execute([9301]);
    $row = $response->fetch(PDO::FETCH_ASSOC);
    $assert(($row === false) === ($beforeRow === false), 'El GET propio cambió la existencia de la respuesta sintética.');
    if ($beforeRow !== false) {
        $assert((int)$row['id'] === (int)$beforeRow['id'] && (string)$row['respuesta'] === (string)$beforeRow['respuesta'], 'El GET propio modificó la respuesta sintética.');
    }
    $idRespuesta = $row === false ? 0 : (int)$row['id'];
    $respuestaOriginal = $row === false ? '' : (string)$row['respuesta'];
    $payload = [
        'idPaciente' => 9301,
        'idRespuesta' => $idRespuesta,
        'idPregunta' => 1,
        'idTema' => 1,
        'idTipo' => 'select',
        'detalle' => 'Si',
        'expectedRespuesta' => $respuestaOriginal,
    ];

    [$statusCrossGet, $bodyCrossGet] = $request(
        $baseUrl . '/buscar/contenido-preguntas-modulo-3/9302/Paciente/1',
        $token(9101, 9301)
    );
    $assert($statusCrossGet === 403 && !str_contains($bodyCrossGet, 'Tabaquismo'), 'GET cruzado no fue rechazado.');

    [$statusOwnPost, $bodyOwnPost] = $request(
        $baseUrl . '/historia-clinica/paciente/editar-cuestionario-modulo3',
        $token(9101, 9301),
        $payload
    );
    $assert($statusOwnPost === 200, 'POST propio no fue aceptado.');

    [$statusStalePost, $bodyStalePost] = $request(
        $baseUrl . '/historia-clinica/paciente/editar-cuestionario-modulo3',
        $token(9101, 9301),
        [
            ...$payload,
            'detalle' => 'No',
            'expectedRespuesta' => '__respuesta_obsoleta__',
        ]
    );
    $assert($statusStalePost === 409, 'La respuesta obsoleta no fue rechazada con 409; HTTP ' . $statusStalePost . '; respuesta: ' . substr(trim(strip_tags($bodyStalePost)), 0, 160));

    $response->execute([9301]);
    $crossTargetRow = $response->fetch(PDO::FETCH_ASSOC);
    $assert($crossTargetRow !== false, 'No se pudo obtener una respuesta sintética para la prueba cruzada.');

    [$statusCrossPost] = $request(
        $baseUrl . '/historia-clinica/paciente/editar-cuestionario-modulo3',
        $token(9102, 9302),
        [
            ...$payload,
            'idPaciente' => 9302,
            'idRespuesta' => (int)$crossTargetRow['id'],
            'detalle' => 'CRUCE-RECHAZADO',
            'expectedRespuesta' => (string)$crossTargetRow['respuesta'],
        ]
    );
    $assert($statusCrossPost === 403, 'POST cruzado no fue rechazado.');

    $restored = true;
    if ($respuestaOriginal !== '') {
        $response->execute([9301]);
        $currentRow = $response->fetch(PDO::FETCH_ASSOC);
        $assert($currentRow !== false, 'No se pudo leer la respuesta sintética antes de restaurar.');
        $restore = $request(
            $baseUrl . '/historia-clinica/paciente/editar-cuestionario-modulo3',
            $token(9101, 9301),
            [
                ...$payload,
                'detalle' => $respuestaOriginal,
                'expectedRespuesta' => (string)$currentRow['respuesta'],
            ]
        );
        $assert($restore[0] === 200, 'No se pudo restaurar la respuesta sintética.');
    } else {
        $restored = 'deferred_to_cleanup';
    }

    echo json_encode([
        'ok' => true,
        'own_get' => 'PASS',
        'cross_get' => 'PASS',
        'own_write' => 'PASS',
        'stale_write' => 'PASS',
        'cross_write' => 'PASS',
        'restored' => $restored,
        'persistent_write' => false,
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    try {
        if (!isset($payload, $respuestaOriginal)) {
            throw $exception;
        }
        $request(
            $baseUrl . '/historia-clinica/paciente/editar-cuestionario-modulo3',
            $token(9101, 9301),
            [
                ...$payload,
                'detalle' => $respuestaOriginal,
                'expectedRespuesta' => 'Si',
            ]
        );
    } catch (Throwable) {
        // El error principal se reporta sin ocultarlo; la limpieza global se ejecuta aparte.
    }
    fwrite(STDERR, "P05 HTTP: FAIL\n" . $exception->getMessage() . "\n");
    exit(1);
}
