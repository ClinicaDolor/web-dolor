<?php

declare(strict_types=1);

use App\Config\Database;
use Dotenv\Dotenv;
use Firebase\JWT\JWT;

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_demo_20261010_final';
$baseUrl = 'http://127.0.0.4:8099';
$appKey = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $database = substr($argument, strlen('--database='));
    if (str_starts_with($argument, '--base-url=')) $baseUrl = rtrim(substr($argument, strlen('--base-url=')), '/');
    if (str_starts_with($argument, '--app-key=')) $appKey = substr($argument, strlen('--app-key='));
}

if ($database !== 'clinica_dolor_demo_20261010_final') {
    fwrite(STDERR, "P07 HTTP: FAIL (base no allowlisted)\n");
    exit(2);
}

require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->load();
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;
$pdo = Database::getInstance();
if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
    fwrite(STDERR, "P07 HTTP: FAIL (base activa no coincide)\n");
    exit(2);
}

$p07AppKey = $appKey !== '' ? $appKey : (string)($_ENV['APP_KEY'] ?? '');
if ($p07AppKey === '') {
    fwrite(STDERR, "P07 HTTP: FAIL (falta APP_KEY del servidor)\n");
    exit(2);
}

$token = static function (int $clinic, int $patient) use ($p07AppKey): string {
    return JWT::encode([
        'iat' => time(),
        'exp' => time() + 600,
        'id_clinica' => $clinic,
        'id_usuario' => $patient,
        'nombre' => 'Paciente sintético ' . $patient,
        'vista' => 'historia-clinica',
        'rol' => 'Paciente',
    ], $p07AppKey, 'HS256');
};

$request = static function (string $url, string $jwt, ?array $payload = null): array {
    $method = $payload === null ? 'GET' : 'POST';
    $headers = "Host: p07clean.localhost:8099\r\n";
    if ($jwt !== '') $headers .= "Cookie: CLINICAJWT={$jwt}\r\n";
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

$v1Id = null;
$v1Original = null;
$v2Id = null;
$v2Original = null;
$beforeV1Rows = $pdo->query('SELECT id, id_paciente, id_pregunta, respuesta FROM pac_respuestas_paciente_modulo_5 WHERE id_paciente IN (9301, 9302) ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$beforeV2Rows = $pdo->query('SELECT id, id_paciente, id_enfermedad, respuesta, tipo, year_diagnostico FROM pac_enfermedades_respuestas_modulo_5 WHERE id_paciente IN (9301, 9302) ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

try {
    [$statusPage, $bodyPage] = $request(
        $baseUrl . '/historia-clinica/antecedentes-personales-patologicos/paciente/9301',
        $token(9101, 9301)
    );
    $assert($statusPage === 200 && str_contains($bodyPage, 'Antecedentes Personales Patológicos'), 'GET propio de P07 no fue autorizado; HTTP ' . $statusPage . '; respuesta: ' . substr(trim(strip_tags($bodyPage)), 0, 240));

    [$statusAnonymous, $bodyAnonymous] = $request(
        $baseUrl . '/historia-clinica/antecedentes-personales-patologicos/paciente/9301',
        ''
    );
    $assert(in_array($statusAnonymous, [302, 401, 403], true) && !str_contains($bodyAnonymous, 'Paciente sintético'), 'GET sin sesión no fue bloqueado; HTTP ' . $statusAnonymous . '; respuesta: ' . substr(trim(strip_tags($bodyAnonymous)), 0, 240));

    [$statusV1, $bodyV1] = $request(
        $baseUrl . '/buscar/contenido-preguntasV1-modulo-5/9301/Paciente/1',
        $token(9101, 9301)
    );
    $assert($statusV1 === 200 && str_contains($bodyV1, 'data-p07-control'), 'Contenido V1 propio no disponible.');

    [$statusV2, $bodyV2] = $request(
        $baseUrl . '/buscar/contenido-preguntasV2-modulo-5/9301/Paciente',
        $token(9101, 9301)
    );
    $assert($statusV2 === 200 && str_contains($bodyV2, 'data-p07-edicion') && str_contains($bodyV2, 'Diabetes Mellitus'), 'Contenido V2 propio no disponible.');
    $afterV1Rows = $pdo->query('SELECT id, id_paciente, id_pregunta, respuesta FROM pac_respuestas_paciente_modulo_5 WHERE id_paciente IN (9301, 9302) ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $afterV2Rows = $pdo->query('SELECT id, id_paciente, id_enfermedad, respuesta, tipo, year_diagnostico FROM pac_enfermedades_respuestas_modulo_5 WHERE id_paciente IN (9301, 9302) ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $assert(json_encode($beforeV1Rows, JSON_UNESCAPED_UNICODE) === json_encode($afterV1Rows, JSON_UNESCAPED_UNICODE), 'El GET de P07 modificó respuestas V1.');
    $assert(json_encode($beforeV2Rows, JSON_UNESCAPED_UNICODE) === json_encode($afterV2Rows, JSON_UNESCAPED_UNICODE), 'El GET de P07 modificó respuestas V2.');

    $v1 = $pdo->prepare('SELECT id, respuesta FROM pac_respuestas_paciente_modulo_5 WHERE id_paciente = ? AND id_pregunta = 501 LIMIT 1');
    $v1->execute([9301]);
    $v1Row = $v1->fetch(PDO::FETCH_ASSOC);
    if ($v1Row === false) {
        [$createV1Status] = $request(
            $baseUrl . '/historia-clinica/paciente/editar-cuestionarioV1-modulo5',
            $token(9101, 9301),
            ['idPaciente' => 9301, 'idRespuesta' => 0, 'idPregunta' => 501, 'idTema' => 1, 'idTipo' => 'select', 'detalle' => 'No', 'idRol' => 'Paciente']
        );
        $assert($createV1Status === 200, 'La primera respuesta V1 no se creó mediante POST; HTTP ' . $createV1Status);
        $v1->execute([9301]);
        $v1Row = $v1->fetch(PDO::FETCH_ASSOC);
    }
    $assert($v1Row !== false, 'No existe respuesta V1 sintética para la prueba.');
    $v1Id = (int)$v1Row['id'];
    $v1Original = (string)$v1Row['respuesta'];

    $v2 = $pdo->prepare('SELECT id, respuesta, tipo, year_diagnostico FROM pac_enfermedades_respuestas_modulo_5 WHERE id_paciente = ? AND id_enfermedad = 1 LIMIT 1');
    $v2->execute([9301]);
    $v2Row = $v2->fetch(PDO::FETCH_ASSOC);
    if ($v2Row === false) {
        [$createV2Status] = $request(
            $baseUrl . '/historia-clinica/paciente/editar-cuestionarioV2-modulo5',
            $token(9101, 9301),
            ['idPaciente' => 9301, 'idEnfermedad' => 0, 'idEnfermedadCatalogo' => 1, 'edicion' => 1, 'detalle' => 'No', 'idRol' => 'Paciente']
        );
        $assert($createV2Status === 200, 'La primera respuesta V2 no se creó mediante POST; HTTP ' . $createV2Status);
        $v2->execute([9301]);
        $v2Row = $v2->fetch(PDO::FETCH_ASSOC);
    }
    $assert($v2Row !== false, 'No existe respuesta V2 sintética para la prueba.');
    $v2Id = (int)$v2Row['id'];
    $v2Original = [
        'respuesta' => (string)$v2Row['respuesta'],
        'tipo' => (string)$v2Row['tipo'],
        'year_diagnostico' => (string)$v2Row['year_diagnostico'],
    ];

    [$statusOwnV1] = $request(
        $baseUrl . '/historia-clinica/paciente/editar-cuestionarioV1-modulo5',
        $token(9101, 9301),
        ['idPaciente' => 9301, 'idRespuesta' => $v1Id, 'idPregunta' => 501, 'idTema' => 1, 'idTipo' => 'select', 'detalle' => 'No', 'idRol' => 'Paciente']
    );
    $assert($statusOwnV1 === 200, 'POST propio V1 no fue aceptado.');
    $v1->execute([9301]);
    $savedV1 = $v1->fetch(PDO::FETCH_ASSOC);
    $assert($savedV1 !== false && $savedV1['respuesta'] === 'No', 'La persistencia V1 no quedó en No.');

    [$statusOwnV2] = $request(
        $baseUrl . '/historia-clinica/paciente/editar-cuestionarioV2-modulo5',
        $token(9101, 9301),
        ['idPaciente' => 9301, 'idEnfermedad' => $v2Id, 'edicion' => 1, 'detalle' => 'No', 'idRol' => 'Paciente']
    );
    $assert($statusOwnV2 === 200, 'POST propio V2 no fue aceptado.');
    $v2->execute([9301]);
    $savedV2 = $v2->fetch(PDO::FETCH_ASSOC);
    $assert($savedV2['respuesta'] === 'No' && $savedV2['tipo'] === '' && $savedV2['year_diagnostico'] === '', 'La persistencia V2 no quedó en No con dependencias vacías.');

    [$statusCrossGet, $bodyCrossGet] = $request(
        $baseUrl . '/buscar/contenido-preguntasV1-modulo-5/9301/Paciente/1',
        $token(9102, 9302)
    );
    $assert($statusCrossGet === 403 && !str_contains($bodyCrossGet, 'P07 sintético'), 'GET cruzado no fue rechazado.');

    [$statusCrossV1] = $request(
        $baseUrl . '/historia-clinica/paciente/editar-cuestionarioV1-modulo5',
        $token(9102, 9302),
        ['idPaciente' => 9302, 'idRespuesta' => $v1Id, 'idPregunta' => 501, 'idTema' => 1, 'idTipo' => 'select', 'detalle' => 'Si', 'idRol' => 'Paciente']
    );
    $assert($statusCrossV1 === 403, 'POST cruzado V1 no fue rechazado.');

    [$statusCrossV2] = $request(
        $baseUrl . '/historia-clinica/paciente/editar-cuestionarioV2-modulo5',
        $token(9102, 9302),
        ['idPaciente' => 9302, 'idEnfermedad' => $v2Id, 'edicion' => 1, 'detalle' => 'Si', 'idRol' => 'Paciente']
    );
    $assert($statusCrossV2 === 403, 'POST cruzado V2 no fue rechazado.');

    echo json_encode([
        'ok' => true,
        'database' => $database,
        'own_get' => 'PASS',
        'anonymous_get' => 'PASS',
        'own_v1_write' => 'PASS',
        'own_v2_write' => 'PASS',
        'cross_get' => 'PASS',
        'cross_v1_write' => 'PASS',
        'cross_v2_write' => 'PASS',
        'restoration' => 'delegated_to_cleanup',
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, "P07 HTTP: FAIL\n" . $exception->getMessage() . "\n");
    exit(1);
}
