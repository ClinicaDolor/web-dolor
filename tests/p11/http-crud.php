<?php
declare(strict_types=1);

use App\Config\Database;
use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_demo_20261010_final';
$baseUrl = 'http://127.0.0.1:8111';
$host = 'p11clean.localhost:8111';
$appKey = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $database = substr($argument, 11);
    if (str_starts_with($argument, '--base-url=')) $baseUrl = rtrim(substr($argument, 11), '/');
    if (str_starts_with($argument, '--host=')) $host = substr($argument, 7);
    if (str_starts_with($argument, '--app-key=')) $appKey = substr($argument, 10);
}
if ($database !== 'clinica_dolor_demo_20261010_final') { fwrite(STDERR, "FAIL P11 database allowlist\n"); exit(2); }
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->load();
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;
$key = $appKey !== '' ? $appKey : (string) ($_ENV['APP_KEY'] ?? '');
if ($key === '') { fwrite(STDERR, "FAIL P11 APP_KEY\n"); exit(2); }
$pdo = Database::getInstance();
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) { fwrite(STDERR, "FAIL P11 active database\n"); exit(2); }
$result = ['ok' => false, 'database' => $database, 'checks' => [], 'cleanup' => 'pending'];
$check = static function (array &$result, string $name, bool $pass, string $detail = ''): void { $result['checks'][$name] = ['status' => $pass ? 'PASS' : 'FAIL']; if ($detail !== '') $result['checks'][$name]['detail'] = $detail; };
$json = static fn(string $body): array => is_array($decoded = json_decode($body, true)) ? $decoded : [];
$request = static function (string $url, string $jwt = '', ?array $payload = null) use ($host): array {
    $headers = "Host: {$host}\r\n";
    if ($jwt !== '') $headers .= "Cookie: CLINICAJWT={$jwt}\r\n";
    if ($payload !== null) $headers .= "Content-Type: application/json\r\n";
    $context = stream_context_create(['http' => ['method' => $payload === null ? 'GET' : 'POST', 'ignore_errors' => true, 'follow_location' => 0, 'header' => $headers, 'content' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE), 'timeout' => 8]]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $header) if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) { $status = (int) $match[1]; break; }
    return [$status, (string) $body];
};
$tokenFor = static fn(int $clinic, int $patient) => JWT::encode(['iat' => time(), 'exp' => time() + 600, 'id_clinica' => $clinic, 'id_usuario' => $patient, 'nombre' => 'Paciente sintético ' . $patient, 'vista' => 'historia-clinica', 'rol' => 'Paciente'], $key, 'HS256');
$baselineRows = $pdo->query('SELECT id, id_paciente, dolor, tiempo_dolor, descripcion, incremento FROM pc_paciente_evaluacion_dolor WHERE id_paciente IN (9301,9302) ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$baselineFinal = $pdo->query('SELECT id, id_modulo, id_paciente FROM pac_historia_clinica_finalizar WHERE id_modulo = 9 AND id_paciente IN (9301,9302) ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$storage = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'evaluacion-dolor';
$savedFiles = [];
foreach (['frente_9301.png', 'espalda_9301.png'] as $name) { $path = $storage . DIRECTORY_SEPARATOR . $name; $savedFiles[$name] = is_file($path) ? file_get_contents($path) : null; }
$ownToken = $crossToken = '';
$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
try {
    [$loginStatus, $loginBody] = $request($baseUrl . '/historia-clinica/login', '', ['pin' => 'PIN-Synth-2026']);
    $login = $json($loginBody); $ownToken = (string) ($login['token'] ?? '');
    $check($result, 'login_sintetico_9301', $loginStatus === 200 && ($login['resultado'] ?? false) === true && $ownToken !== '', 'HTTP ' . $loginStatus);
    $crossToken = $tokenFor(9102, 9302);
    $beforeGetRows = (int) $pdo->query('SELECT COUNT(*) FROM pc_paciente_evaluacion_dolor WHERE id_paciente IN (9301,9302)')->fetchColumn();
    [$pageStatus, $pageBody] = $request($baseUrl . '/historia-clinica/evaluacion-dolor/paciente/9301', $ownToken);
    $afterGetRows = (int) $pdo->query('SELECT COUNT(*) FROM pc_paciente_evaluacion_dolor WHERE id_paciente IN (9301,9302)')->fetchColumn();
    $check($result, 'get_propio_sin_escrituras', $pageStatus === 200 && str_contains($pageBody, 'p11InstructionsModal') && $beforeGetRows === $afterGetRows, 'HTTP ' . $pageStatus);
    [$anonymousStatus] = $request($baseUrl . '/historia-clinica/evaluacion-dolor/paciente/9301');
    $check($result, 'sin_sesion_302', in_array($anonymousStatus, [302,401,403], true), 'HTTP ' . $anonymousStatus);
    [$crossPageStatus] = $request($baseUrl . '/historia-clinica/evaluacion-dolor/paciente/9301', $crossToken);
    $check($result, 'get_cruzado_403', $crossPageStatus === 403, 'HTTP ' . $crossPageStatus);
    [$frontStatus, $frontBody] = $request($baseUrl . '/buscar/contenido-evaluacion-frente-modulo-9/9301/Paciente', $ownToken);
    $check($result, 'ajax_frente_propio', $frontStatus === 200 && str_contains($frontBody, 'painFrente') && str_contains($frontBody, 'data-p11-map="frente"'), 'HTTP ' . $frontStatus);
    [$backStatus, $backBody] = $request($baseUrl . '/buscar/contenido-evaluacion-espalda-modulo-9/9301/Paciente', $ownToken);
    $check($result, 'ajax_espalda_propio', $backStatus === 200 && str_contains($backBody, 'painEspalda'), 'HTTP ' . $backStatus);
    [$questionStatus, $questionBody] = $request($baseUrl . '/buscar/contenido-preguntas-modulo-9/9301/Paciente', $ownToken);
    $check($result, 'ajax_cuestionario_propio', $questionStatus === 200 && str_contains($questionBody, 'p11Dolor') && str_contains($questionBody, 'tiempo_dolor'), 'HTTP ' . $questionStatus);
    [$crossAjaxStatus] = $request($baseUrl . '/buscar/contenido-evaluacion-frente-modulo-9/9301/Doctor', $crossToken);
    $check($result, 'ajax_cruzado_403', $crossAjaxStatus === 403, 'HTTP ' . $crossAjaxStatus);
    [$imageStatus, $imageBody] = $request($baseUrl . '/historia-clinica/paciente/imagen-evaluacion-dolor/frente/9301', $ownToken);
    $check($result, 'imagen_propia_autorizada', $imageStatus === 200 && str_starts_with($imageBody, "\x89PNG"), 'HTTP ' . $imageStatus);
    [$crossImageStatus] = $request($baseUrl . '/historia-clinica/paciente/imagen-evaluacion-dolor/frente/9301', $crossToken);
    $check($result, 'imagen_cruzada_403', $crossImageStatus === 403, 'HTTP ' . $crossImageStatus);
    [$saveFrontStatus, $saveFrontBody] = $request($baseUrl . '/historia-clinica/paciente/editar-img-frente-modulo9', $ownToken, ['idPaciente' => 9301, 'idSexo' => 'M', 'idRol' => 'Doctor', 'imgFrente' => $png]);
    [$saveBackStatus] = $request($baseUrl . '/historia-clinica/paciente/editar-img-espalda-modulo9', $ownToken, ['idPaciente' => 9301, 'idSexo' => 'M', 'idRol' => 'Doctor', 'imgEspalda' => $png]);
    $check($result, 'imagenes_guardadas_propio', $saveFrontStatus === 200 && ($json($saveFrontBody)['resultado'] ?? false) === true && $saveBackStatus === 200);
    [$crossSaveStatus] = $request($baseUrl . '/historia-clinica/paciente/editar-img-frente-modulo9', $crossToken, ['idPaciente' => 9301, 'imgFrente' => $png]);
    $check($result, 'escritura_imagen_cruzada_403', $crossSaveStatus === 403, 'HTTP ' . $crossSaveStatus);
    [$clearFrontStatus, $clearFrontBody] = $request($baseUrl . '/historia-clinica/paciente/eliminar-img-frente-modulo9', $ownToken, ['idPaciente' => 9301]);
    $check($result, 'limpieza_mapa_propia', $clearFrontStatus === 200 && ($json($clearFrontBody)['resultado'] ?? false) === true, 'HTTP ' . $clearFrontStatus);
    [$yesStatus] = $request($baseUrl . '/historia-clinica/paciente/editar-evaluacion-dolor-modulo9', $ownToken, ['idPaciente' => 9301, 'changes' => [1 => 'Si']]);
    [$dependenciesStatus] = $request($baseUrl . '/historia-clinica/paciente/editar-evaluacion-dolor-modulo9', $ownToken, ['idPaciente' => 9301, 'changes' => [2 => 'Dos semanas', 3 => 'Dolor sintético localizado', 4 => 'Permanece igual']]);
    $row = $pdo->query('SELECT dolor, tiempo_dolor, descripcion, incremento FROM pc_paciente_evaluacion_dolor WHERE id_paciente = 9301 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $check($result, 'cuestionario_si_dependencias_persistente', $yesStatus === 200 && $dependenciesStatus === 200 && $row['dolor'] === 'Si' && $row['tiempo_dolor'] === 'Dos semanas' && $row['descripcion'] === 'Dolor sintético localizado' && $row['incremento'] === 'Permanece igual');
    [$noopStatus] = $request($baseUrl . '/historia-clinica/paciente/editar-evaluacion-dolor-modulo9', $ownToken, ['idPaciente' => 9301, 'changes' => [1 => 'Si', 2 => 'Dos semanas', 3 => 'Dolor sintético localizado', 4 => 'Permanece igual']]);
    $check($result, 'cuestionario_noop_200', $noopStatus === 200, 'HTTP ' . $noopStatus . '; sentencia SQL no instrumentada');
    [$crossPostStatus] = $request($baseUrl . '/historia-clinica/paciente/editar-evaluacion-dolor-modulo9', $crossToken, ['idPaciente' => 9301, 'changes' => [1 => 'No']]);
    $check($result, 'cuestionario_cruzado_403', $crossPostStatus === 403, 'HTTP ' . $crossPostStatus);
    [$noStatus] = $request($baseUrl . '/historia-clinica/paciente/editar-evaluacion-dolor-modulo9', $ownToken, ['idPaciente' => 9301, 'changes' => [1 => 'No']]);
    $rowNo = $pdo->query('SELECT dolor, tiempo_dolor, descripcion, incremento FROM pc_paciente_evaluacion_dolor WHERE id_paciente = 9301 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $check($result, 'respuesta_no_limpia_dependencias', $noStatus === 200 && $rowNo['dolor'] === 'No' && $rowNo['tiempo_dolor'] === '' && $rowNo['descripcion'] === '' && $rowNo['incremento'] === '');
    [$finishStatus, $finishBody] = $request($baseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente', $ownToken, ['idPaciente' => 9301, 'idModulo' => 9]);
    $finish = $json($finishBody); $finishRows = (int) $pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 9 AND id_paciente = 9301')->fetchColumn();
    $check($result, 'finalizacion_next_url', $finishStatus === 200 && ($finish['resultado'] ?? false) === true && $finishRows === 1 && is_string($finish['next_url'] ?? null), 'HTTP ' . $finishStatus);
    [$repeatFinishStatus, $repeatFinishBody] = $request($baseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente', $ownToken, ['idPaciente' => 9301, 'idModulo' => 9]);
    $repeatFinish = $json($repeatFinishBody); $repeatRows = (int) $pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 9 AND id_paciente = 9301')->fetchColumn();
    $check($result, 'finalizacion_idempotente', $repeatFinishStatus === 200 && ($repeatFinish['resultado'] ?? false) === true && $repeatRows === 1);
    [$crossFinishStatus] = $request($baseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente', $crossToken, ['idPaciente' => 9301, 'idModulo' => 9]);
    $check($result, 'finalizacion_cruzada_403', $crossFinishStatus === 403, 'HTTP ' . $crossFinishStatus);
    $result['ok'] = !array_filter($result['checks'], static fn(array $item): bool => $item['status'] === 'FAIL');
} catch (Throwable $exception) {
    $result['error'] = 'La prueba HTTP no pudo completarse.';
}

try {
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM pc_paciente_evaluacion_dolor WHERE id_paciente IN (9301,9302)');
    $insert = $pdo->prepare('INSERT INTO pc_paciente_evaluacion_dolor (id, id_paciente, dolor, tiempo_dolor, descripcion, incremento) VALUES (:id, :id_paciente, :dolor, :tiempo_dolor, :descripcion, :incremento)');
    foreach ($baselineRows as $row) $insert->execute([':id' => $row['id'], ':id_paciente' => $row['id_paciente'], ':dolor' => $row['dolor'], ':tiempo_dolor' => $row['tiempo_dolor'], ':descripcion' => $row['descripcion'], ':incremento' => $row['incremento']]);
    $pdo->exec('DELETE FROM pac_historia_clinica_finalizar WHERE id_modulo = 9 AND id_paciente IN (9301,9302)');
    $insertFinal = $pdo->prepare('INSERT INTO pac_historia_clinica_finalizar (id, id_modulo, id_paciente) VALUES (:id, :id_modulo, :id_paciente)');
    foreach ($baselineFinal as $row) $insertFinal->execute([':id' => $row['id'], ':id_modulo' => $row['id_modulo'], ':id_paciente' => $row['id_paciente']]);
    $pdo->commit();
    foreach ($savedFiles as $name => $contents) { $path = $storage . DIRECTORY_SEPARATOR . $name; if ($contents === null) { if (is_file($path)) @unlink($path); } else { if (!is_dir($storage)) mkdir($storage, 0770, true); file_put_contents($path, $contents, LOCK_EX); } }
    $remaining = (int) $pdo->query('SELECT COUNT(*) FROM pc_paciente_evaluacion_dolor WHERE id_paciente IN (9301,9302)')->fetchColumn() + (int) $pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 9 AND id_paciente IN (9301,9302)')->fetchColumn();
    $result['cleanup'] = $remaining === count($baselineRows) + count($baselineFinal) ? 'PASS' : 'FAIL';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $result['cleanup'] = 'FAIL';
}
$result['ok'] = ($result['ok'] ?? false) && $result['cleanup'] === 'PASS';
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
