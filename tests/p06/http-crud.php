<?php

declare(strict_types=1);

use App\Config\Database;
use Dotenv\Dotenv;
use Firebase\JWT\JWT;

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_demo_20261010_final';
$baseUrl = 'http://127.0.0.1:8086';
$concurrentBaseUrl = '';
$appKey = '';
$concurrentCapable = false;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $database = substr($argument, strlen('--database='));
    if (str_starts_with($argument, '--base-url=')) $baseUrl = rtrim(substr($argument, strlen('--base-url=')), '/');
    if (str_starts_with($argument, '--concurrent-base-url=')) $concurrentBaseUrl = rtrim(substr($argument, strlen('--concurrent-base-url=')), '/');
    if (str_starts_with($argument, '--app-key=')) $appKey = substr($argument, strlen('--app-key='));
    if ($argument === '--concurrent-capable=yes') $concurrentCapable = true;
}
$concurrentBaseUrl = $concurrentBaseUrl !== '' ? $concurrentBaseUrl : $baseUrl;
if ($database !== 'clinica_dolor_demo_20261010_final') {
    fwrite(STDERR, "P06 HTTP CRUD: FAIL (base no allowlisted)\n");
    exit(2);
}

require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->load();
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;
$signingKey = $appKey !== '' ? $appKey : (string) ($_ENV['APP_KEY'] ?? '');
if ($signingKey === '') {
    fwrite(STDERR, "P06 HTTP CRUD: FAIL (falta APP_KEY efímera)\n");
    exit(2);
}
$pdo = Database::getInstance();
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
    fwrite(STDERR, "P06 HTTP CRUD: FAIL (base activa no coincide)\n");
    exit(2);
}

$token = static function (int $clinic, int $patient) use ($signingKey): string {
    return JWT::encode([
        'iat' => time(),
        'exp' => time() + 600,
        'id_clinica' => $clinic,
        'id_usuario' => $patient,
        'nombre' => 'Paciente sintético ' . $patient,
        'vista' => 'historia-clinica',
        'rol' => 'Paciente',
    ], $signingKey, 'HS256');
};

$request = static function (string $url, ?string $jwt, array $payload): array {
    $cookie = $jwt === null ? '' : "Cookie: CLINICAJWT={$jwt}\r\n";
    $options = [
        'http' => [
            'method' => 'POST',
            'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\n{$cookie}",
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ],
    ];
    $body = file_get_contents($url, false, stream_context_create($options));
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }
    return [$status, json_decode((string) $body, true) ?: []];
};

$parallelRequest = static function (array $urls, string $jwt, array $payload): array {
    if (!function_exists('curl_multi_init')) return [];
    $multi = curl_multi_init();
    $handles = [];
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
    for ($index = 0; $index < 2; $index++) {
        $handle = curl_init($urls[$index] ?? $urls[0]);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Cookie: CLINICAJWT=' . $jwt,
            ],
            CURLOPT_POSTFIELDS => $encoded,
        ]);
        curl_multi_add_handle($multi, $handle);
        $handles[] = $handle;
    }
    do {
        $running = 0;
        $multiResult = curl_multi_exec($multi, $running);
        if ($running > 0 && $multiResult === CURLM_OK) curl_multi_select($multi, 1.0);
    } while ($running > 0 && $multiResult === CURLM_OK);
    $responses = [];
    foreach ($handles as $handle) {
        $body = curl_multi_getcontent($handle);
        $responses[] = [
            'status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
            'body' => json_decode((string) $body, true) ?: [],
        ];
        curl_multi_remove_handle($multi, $handle);
        curl_close($handle);
    }
    curl_multi_close($multi);
    return $responses;
};

$get = static function (string $url, string $jwt): array {
    $body = file_get_contents($url, false, stream_context_create(['http' => [
        'method' => 'GET',
        'ignore_errors' => true,
        'header' => "Cookie: CLINICAJWT={$jwt}\r\n",
    ]]));
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }
    return [$status, (string) $body];
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$marker = 'P06-SYNTH-' . strtoupper(bin2hex(random_bytes(5)));
$id = 0;
$finalizationCreated = false;
try {
    $beforeGet = (int) $pdo->query('SELECT COUNT(*) FROM pc_antecedentes_quirurgicos WHERE id_paciente IN (9301, 9302)')->fetchColumn();
    [$status] = $get($baseUrl . '/historia-clinica/antecedentes-personales-quirurgicos/paciente/9301', $token(9101, 9301));
    $afterGet = (int) $pdo->query('SELECT COUNT(*) FROM pc_antecedentes_quirurgicos WHERE id_paciente IN (9301, 9302)')->fetchColumn();
    $assert($status === 200 && $beforeGet === $afterGet, 'El GET de P06 modificó el estado clínico o no fue autorizado. HTTP=' . $status . ' antes=' . $beforeGet . ' después=' . $afterGet);

    $before = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM pc_antecedentes_quirurgicos WHERE id_paciente = 9301')->fetchColumn();
    [$status, $body] = $request($baseUrl . '/historia-clinica/paciente/agregar-cirugia-antecedentes', $token(9101, 9301), ['idPaciente' => 9301, 'idRol' => 'Paciente']);
    $assert($status === 200 && ($body['resultado'] ?? false), 'El alta HTTP sintética no fue aceptada.');
    $id = (int) $pdo->query('SELECT MAX(id) FROM pc_antecedentes_quirurgicos WHERE id_paciente = 9301')->fetchColumn();
    $assert($id > $before, 'No se identificó el registro HTTP sintético.');

    [$status, $body] = $request($baseUrl . '/historia-clinica/paciente/editar-cirugia-antecedentes', $token(9101, 9301), ['idPaciente' => 9301, 'idCirugia' => $id, 'edicion' => 2, 'detalle' => $marker, 'idRol' => 'Paciente']);
    $assert($status === 200 && ($body['resultado'] ?? false), 'La edición HTTP propia no fue aceptada.');
    $stored = $pdo->prepare('SELECT cirugia FROM pc_antecedentes_quirurgicos WHERE id = ? AND id_paciente = ?');
    $stored->execute([$id, 9301]);
    $assert($stored->fetchColumn() === $marker, 'La edición HTTP no persistió en la base sintética.');

    [$status] = $request($baseUrl . '/historia-clinica/paciente/editar-cirugia-antecedentes', $token(9102, 9302), ['idPaciente' => 9302, 'idCirugia' => $id, 'edicion' => 2, 'detalle' => 'CRUCE-RECHAZADO', 'idRol' => 'Paciente']);
    $assert($status === 403, 'La edición HTTP cruzada no fue rechazada.');
    [$status] = $request($baseUrl . '/historia-clinica/paciente/eliminar-cirugia-antecedentes', $token(9102, 9302), ['idPaciente' => 9302, 'idQuirurgico' => $id, 'idRol' => 'Paciente']);
    $assert($status === 403, 'La eliminación HTTP cruzada no fue rechazada.');
    [$status, $body] = $request($baseUrl . '/historia-clinica/paciente/eliminar-cirugia-antecedentes', $token(9101, 9301), ['idPaciente' => 9301, 'idQuirurgico' => $id, 'idRol' => 'Paciente']);
    $assert($status === 200 && ($body['resultado'] ?? false), 'La eliminación HTTP propia no fue aceptada.');
    $check = $pdo->prepare('SELECT COUNT(*) FROM pc_antecedentes_quirurgicos WHERE id = ?');
    $check->execute([$id]);
    $assert((int) $check->fetchColumn() === 0, 'La limpieza del registro HTTP sintético no se completó.');

    $existingFinalization = (int) $pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 4 AND id_paciente IN (9301, 9302)')->fetchColumn();
    $assert($existingFinalization === 0, 'La línea base P06 ya estaba finalizada para un paciente sintético.');

    [$status] = $request($baseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente', null, ['idModulo' => 4, 'idPaciente' => 9301]);
    $assert($status === 302, 'La finalización sin sesión no fue rechazada con redirección.');
    $beforeInvalid = (int) $pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 4 AND id_paciente IN (9301, 9302)')->fetchColumn();
    [$status] = $request($baseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente', $token(9101, 9301), ['idModulo' => 999, 'idPaciente' => 9301]);
    $afterInvalid = (int) $pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 4 AND id_paciente IN (9301, 9302)')->fetchColumn();
    $assert($status === 422 && $beforeInvalid === $afterInvalid, 'El módulo inválido no fue rechazado sin escritura.');

    $parallelResponses = $parallelRequest(
        [
            $baseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente',
            $concurrentBaseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente',
        ],
        $token(9102, 9302),
        ['idModulo' => 4, 'idPaciente' => 9302]
    );
    $assert(count($parallelResponses) === 2, 'No fue posible enviar el par de solicitudes de finalización.');
    $finalizationCreated = true;
    foreach ($parallelResponses as $response) {
        $assert($response['status'] === 200 && ($response['body']['resultado'] ?? false), 'Una solicitud paralela de P06 no fue aceptada.');
    }
    $patientTwoRows = (int) $pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 4 AND id_paciente = 9302')->fetchColumn();
    $assert($patientTwoRows === 1, 'La ráfaga paralela de P06 dejó más de una fila para 9302.');

    [$status, $body] = $request($baseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente', $token(9101, 9301), ['idModulo' => 4, 'idPaciente' => 9301]);
    $assert($status === 200 && ($body['resultado'] ?? false) && is_string($body['next_url'] ?? null) && str_starts_with($body['next_url'], '/'), 'Finalización P06 no devolvió next_url local.');
    $finalizationCreated = true;
    $firstNextUrl = $body['next_url'];
    [$repeatStatus, $body] = $request($baseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente', $token(9101, 9301), ['idModulo' => 4, 'idPaciente' => 9301]);
    $finalizationRows = (int) $pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 4 AND id_paciente = 9301')->fetchColumn();
    $finalizationIdempotent = $repeatStatus === 200 && ($body['resultado'] ?? false) && $finalizationRows === 1 && ($body['next_url'] ?? null) === $firstNextUrl;
    [$status] = $request($baseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente', $token(9102, 9302), ['idModulo' => 4, 'idPaciente' => 9301]);
    $assert($status === 403, 'La finalización cruzada de P06 no fue rechazada.');
    $assert($finalizationIdempotent, 'La finalización idempotente de P06 falló; filas=' . $finalizationRows . ' HTTP=' . $repeatStatus);

    echo json_encode([
        'ok' => true,
        'crud_http' => 'PASS',
        'cross_patient_write' => 'PASS',
        'finalization_and_next_url' => 'PASS',
        'finalization_idempotent' => 'PASS',
        'concurrent_finalization' => $concurrentCapable ? 'PASS' : 'NO VERIFICADO (servidor concurrente no acreditado)',
        'cross_finalization' => 'PASS',
        'anonymous_finalization' => 'PASS',
        'invalid_module' => 'PASS',
        'incomplete_form' => 'NO APLICA (P06 permite finalizar sin registros según contrato vigente)',
        'persistent_write' => false,
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    if ($id > 0) {
        $cleanup = $pdo->prepare('DELETE FROM pc_antecedentes_quirurgicos WHERE id = ? AND id_paciente = 9301');
        $cleanup->execute([$id]);
    }
    if ($finalizationCreated) {
        $pdo->prepare('DELETE FROM pac_historia_clinica_finalizar WHERE id_modulo = 4 AND id_paciente IN (9301, 9302)')->execute();
    }
    fwrite(STDERR, "P06 HTTP CRUD: FAIL\n" . $exception->getMessage() . "\n");
    exit(1);
} finally {
    if ($finalizationCreated) {
        $pdo->prepare('DELETE FROM pac_historia_clinica_finalizar WHERE id_modulo = 4 AND id_paciente IN (9301, 9302)')->execute();
    }
}
