<?php

declare(strict_types=1);

use App\Config\Database;
use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_demo_20261010_final';
$baseUrl = 'http://127.0.0.1:8109';
$appKey = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $database = substr($argument, strlen('--database='));
    if (str_starts_with($argument, '--base-url=')) $baseUrl = rtrim(substr($argument, strlen('--base-url=')), '/');
    if (str_starts_with($argument, '--app-key=')) $appKey = substr($argument, strlen('--app-key='));
}

$result = ['ok' => false, 'database' => $database, 'checks' => [], 'cleanup' => 'pending'];
$createdData = false;
$check = static function (array &$result, string $name, bool $passed, string $detail = ''): void {
    $result['checks'][$name] = ['status' => $passed ? 'PASS' : 'FAIL'];
    if ($detail !== '') $result['checks'][$name]['detail'] = $detail;
};

if ($database !== 'clinica_dolor_demo_20261010_final') {
    fwrite(STDERR, "P09 HTTP: FAIL (base no allowlisted)\n");
    exit(2);
}

require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->load();
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;
$p09AppKey = $appKey !== '' ? $appKey : (string)($_ENV['APP_KEY'] ?? '');
if ($p09AppKey === '') {
    fwrite(STDERR, "P09 HTTP: FAIL (falta APP_KEY del servidor)\n");
    exit(2);
}
$pdo = Database::getInstance();
if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
    fwrite(STDERR, "P09 HTTP: FAIL (base activa no coincide)\n");
    exit(2);
}

$tokenFor = static function (int $clinic, int $patient) use ($p09AppKey): string {
    return JWT::encode([
        'iat' => time(), 'exp' => time() + 600, 'id_clinica' => $clinic,
        'id_usuario' => $patient, 'nombre' => 'Paciente sintético ' . $patient,
        'vista' => 'historia-clinica', 'rol' => 'Paciente',
    ], $p09AppKey, 'HS256');
};

$request = static function (string $url, string $jwt = '', ?array $payload = null, string $host = 'p09clean.localhost:8109'): array {
    $method = $payload === null ? 'GET' : 'POST';
    $headers = "Host: {$host}\r\n";
    if ($jwt !== '') $headers .= "Cookie: CLINICAJWT={$jwt}\r\n";
    if ($payload !== null) $headers .= "Content-Type: application/json\r\n";
    $options = ['http' => [
        'method' => $method, 'ignore_errors' => true, 'follow_location' => 0,
        'header' => $headers,
        'content' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
        'timeout' => 8,
    ]];
    $body = @file_get_contents($url, false, stream_context_create($options));
    $headersOut = $http_response_header ?? [];
    $status = 0;
    foreach ($headersOut as $header) {
        if (preg_match('/^HTTP\\/\\S+\\s+(\\d+)/', $header, $matches)) {
            $status = (int)$matches[1];
            break;
        }
    }
    return [$status, (string)$body, $headersOut];
};
$jsonBody = static function (string $body): array {
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
};

$ownToken = '';
$crossToken = $tokenFor(9102, 9302);
$responseId = 0;

try {
    $baselineResponses = (int)$pdo->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente IN (9301, 9302)')->fetchColumn();
    $baselineFinalizations = (int)$pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 7 AND id_paciente IN (9301, 9302)')->fetchColumn();
    if ($baselineResponses !== 0 || $baselineFinalizations !== 0) throw new RuntimeException('El fixture P09 no está limpio para una prueba determinista.');

    [$statusLogin, $loginBody] = $request($baseUrl . '/historia-clinica/login', '', ['pin' => 'PIN-Synth-2026']);
    $loginJson = $jsonBody($loginBody);
    $ownToken = (string)($loginJson['token'] ?? '');
    $check($result, 'login_sintetico', $statusLogin === 200 && ($loginJson['resultado'] ?? false) === true && $ownToken !== '', 'HTTP ' . $statusLogin);
    if ($ownToken === '') throw new RuntimeException('El login sintético no entregó sesión.');
    $decoded = JWT::decode($ownToken, new Key($p09AppKey, 'HS256'));
    $ownPatient = (int)($decoded->id_usuario ?? 0);
    $check($result, 'login_paciente_9301', $ownPatient === 9301, 'id_usuario=' . $ownPatient);

    [$statusPage, $bodyPage] = $request($baseUrl . '/historia-clinica/medicacion-control-dolor/paciente/9301', $ownToken);
    $createdData = true;
    $check($result, 'get_vista_propia', $statusPage === 200 && str_contains($bodyPage, 'p09InstructionsModal') && str_contains($bodyPage, 'contePreguntas_1'), 'HTTP ' . $statusPage);
    $check($result, 'seis_clases_en_pagina', substr_count($bodyPage, 'id="contePreguntas_') === 6);

    [$statusAnonymous, $bodyAnonymous] = $request($baseUrl . '/historia-clinica/medicacion-control-dolor/paciente/9301');
    $check($result, 'sin_sesion_bloqueado', in_array($statusAnonymous, [302, 401, 403], true) && !str_contains($bodyAnonymous, 'Parches de Buprenorfina'), 'HTTP ' . $statusAnonymous);

    $themeIds = array_map('intval', $pdo->query('SELECT id FROM pac_temas_modulo_7 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    foreach ($themeIds as $themeId) {
        [$statusAjax, $bodyAjax] = $request($baseUrl . '/buscar/contenido-preguntas-modulo-7/9301/Paciente/' . $themeId, $ownToken);
        $pass = $statusAjax === 200 && str_contains($bodyAjax, 'data-p09-control') && str_contains($bodyAjax, 'data-p09-theme="' . $themeId . '"');
        $check($result, 'ajax_tema_' . $themeId, $pass, 'HTTP ' . $statusAjax);
    }
    $check($result, 'ajax_seis_clases', count($themeIds) === 6);

    [$statusCrossPage, $bodyCrossPage] = $request($baseUrl . '/historia-clinica/medicacion-control-dolor/paciente/9301', $crossToken);
    $check($result, 'get_cruzado_bloqueado', $statusCrossPage === 403 && !str_contains($bodyCrossPage, 'Parches de Buprenorfina'), 'HTTP ' . $statusCrossPage);
    [$statusCrossAjax, $bodyCrossAjax] = $request($baseUrl . '/buscar/contenido-preguntas-modulo-7/9301/Paciente/1', $crossToken);
    $check($result, 'ajax_cruzado_bloqueado', $statusCrossAjax === 403 && !str_contains($bodyCrossAjax, 'Parches de Buprenorfina'), 'HTTP ' . $statusCrossAjax);

    $save = static function (array $payload) use ($request, $baseUrl, $ownToken, $jsonBody): array {
        [$status, $body] = $request($baseUrl . '/historia-clinica/paciente/editar-cuestionario-modulo7', $ownToken, $payload);
        return [$status, $jsonBody($body)];
    };
    [$createStatus, $createJson] = $save(['idPaciente' => 9301, 'idRespuesta' => 0, 'idPregunta' => 1, 'idTema' => 1, 'idRol' => 'Paciente', 'idTipo' => 1, 'detalle' => 'No']);
    $row = $pdo->query('SELECT id FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente = 9301 AND id_pregunta = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $check($result, 'primera_respuesta_crea_fila_en_POST', $createStatus === 200 && ($createJson['resultado'] ?? false) === true && (bool)$row, 'HTTP ' . $createStatus);
    if (!$row) throw new RuntimeException('No se creó la respuesta sintética del primer medicamento mediante POST.');
    $responseId = (int)$row['id'];
    $basePayload = ['idPaciente' => 9301, 'idRespuesta' => $responseId, 'idPregunta' => 1, 'idTema' => 1, 'idRol' => 'Paciente'];

    [$saveStatus, $saveJson] = $save($basePayload + ['idTipo' => 1, 'detalle' => 'No']);
    $stored = $pdo->query('SELECT respuesta, dosis, resultados, descripcion, consumo FROM pac_respuestas_paciente_modulo_7 WHERE id = ' . $responseId)->fetch(PDO::FETCH_ASSOC);
    $check($result, 'guardar_no', $saveStatus === 200 && ($saveJson['resultado'] ?? false) === true && $stored['respuesta'] === 'No' && $stored['dosis'] === '' && $stored['resultados'] === '' && $stored['descripcion'] === '' && $stored['consumo'] === '', 'HTTP ' . $saveStatus);

    [$repeatStatus, $repeatJson] = $save($basePayload + ['idTipo' => 1, 'detalle' => 'No']);
    $check($result, 'repetir_no_sin_error', $repeatStatus === 200 && ($repeatJson['resultado'] ?? false) === true, 'HTTP ' . $repeatStatus . '; no-op SQL no observable sin instrumentación.');

    [$yesStatus] = $save($basePayload + ['idTipo' => 1, 'detalle' => 'Si']);
    [$doseStatus] = $save($basePayload + ['idTipo' => 2, 'detalle' => 'P09-DOSE-SYNTH-20261011']);
    [$adverseStatus] = $save($basePayload + ['idTipo' => 3, 'detalle' => 'Tuve efectos adversos']);
    [$descriptionStatus] = $save($basePayload + ['idTipo' => 5, 'detalle' => 'Efecto adverso sintético controlado']);
    [$consumptionStatus] = $save($basePayload + ['idTipo' => 4, 'detalle' => 'Si']);
    $stored = $pdo->query('SELECT respuesta, dosis, resultados, descripcion, consumo FROM pac_respuestas_paciente_modulo_7 WHERE id = ' . $responseId)->fetch(PDO::FETCH_ASSOC);
    $check($result, 'guardar_si_dependencias', $yesStatus === 200 && $doseStatus === 200 && $adverseStatus === 200 && $descriptionStatus === 200 && $consumptionStatus === 200 && $stored['respuesta'] === 'Si' && $stored['dosis'] === 'P09-DOSE-SYNTH-20261011' && $stored['resultados'] === 'Tuve efectos adversos' && $stored['descripcion'] === 'Efecto adverso sintético controlado' && $stored['consumo'] === 'Si', 'statuses=' . implode(',', [$yesStatus, $doseStatus, $adverseStatus, $descriptionStatus, $consumptionStatus]));

    [$resultStatus] = $save($basePayload + ['idTipo' => 3, 'detalle' => 'Funciono']);
    $stored = $pdo->query('SELECT resultados, descripcion FROM pac_respuestas_paciente_modulo_7 WHERE id = ' . $responseId)->fetch(PDO::FETCH_ASSOC);
    $check($result, 'limpiar_descripcion_al_cambiar_resultado', $resultStatus === 200 && $stored['resultados'] === 'Funciono' && $stored['descripcion'] === '', 'HTTP ' . $resultStatus);

    [$statusRecovery, $bodyRecovery] = $request($baseUrl . '/buscar/contenido-preguntas-modulo-7/9301/Paciente/1', $ownToken);
    $check($result, 'recuperar_respuesta', $statusRecovery === 200 && str_contains($bodyRecovery, 'P09-DOSE-SYNTH-20261011') && str_contains($bodyRecovery, 'value="Funciono" checked'), 'HTTP ' . $statusRecovery);

    [$crossWriteStatus] = $request($baseUrl . '/historia-clinica/paciente/editar-cuestionario-modulo7', $crossToken, array_merge($basePayload, ['idPaciente' => 9302, 'idTipo' => 1, 'detalle' => 'No']));
    $afterCross = (string)$pdo->query('SELECT respuesta FROM pac_respuestas_paciente_modulo_7 WHERE id = ' . $responseId)->fetchColumn();
    $check($result, 'post_cruzado_bloqueado', $crossWriteStatus === 403 && $afterCross === 'Si', 'HTTP ' . $crossWriteStatus . '; respuesta=' . $afterCross);

    [$crossFinalizeStatus] = $request($baseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente', $crossToken, ['idModulo' => 7, 'idPaciente' => 9301]);
    $check($result, 'finalizacion_cruzada_bloqueada', $crossFinalizeStatus === 403, 'HTTP ' . $crossFinalizeStatus);

    [$finalStatus, $finalBody] = $request($baseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente', $ownToken, ['idModulo' => 7, 'idPaciente' => 9301]);
    $finalJson = $jsonBody($finalBody);
    $nextUrl = (string)($finalJson['next_url'] ?? '');
    $finalCount = (int)$pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 7 AND id_paciente = 9301')->fetchColumn();
    $check($result, 'finalizar_y_next_url', $finalStatus === 200 && ($finalJson['resultado'] ?? false) === true && str_starts_with($nextUrl, '/historia-clinica/') && $finalCount === 1, 'HTTP ' . $finalStatus . '; next_url=' . $nextUrl);

    [$repeatFinalStatus, $repeatFinalBody] = $request($baseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente', $ownToken, ['idModulo' => 7, 'idPaciente' => 9301]);
    $repeatFinalJson = $jsonBody($repeatFinalBody);
    $repeatFinalCount = (int)$pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 7 AND id_paciente = 9301')->fetchColumn();
    $check($result, 'finalizacion_idempotente', $repeatFinalStatus === 200 && ($repeatFinalJson['resultado'] ?? false) === true && $repeatFinalCount === 1, 'HTTP ' . $repeatFinalStatus . '; count=' . $repeatFinalCount);
    $result['ok'] = !array_filter($result['checks'], static fn(array $item): bool => $item['status'] === 'FAIL');
} catch (Throwable $exception) {
    $result['error'] = $exception->getMessage();
}

try {
    if ($createdData) {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM pac_historia_clinica_finalizar WHERE id_modulo = 7 AND id_paciente IN (9301, 9302)')->execute();
        $pdo->prepare('DELETE FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente IN (9301, 9302)')->execute();
        $pdo->commit();
    }
    $remaining = (int)$pdo->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente IN (9301, 9302)')->fetchColumn();
    $remaining += (int)$pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 7 AND id_paciente IN (9301, 9302)')->fetchColumn();
    $result['cleanup'] = $remaining === 0 ? 'PASS' : 'FAIL';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $result['cleanup'] = 'FAIL';
    $result['cleanup_error'] = 'No fue posible confirmar la limpieza sintética.';
}

$result['ok'] = ($result['ok'] ?? false) && $result['cleanup'] === 'PASS';
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
