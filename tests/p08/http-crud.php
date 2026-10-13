<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Firebase\JWT\JWT;

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_demo_20261010_final';
$baseUrl = 'http://127.0.0.1:8108';
$appKeyArgument = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $database = substr($argument, 11);
    if (str_starts_with($argument, '--base-url=')) $baseUrl = rtrim(substr($argument, 11), '/');
    if (str_starts_with($argument, '--app-key=')) $appKeyArgument = substr($argument, 10);
}
if ($database !== 'clinica_dolor_demo_20261010_final') { fwrite(STDERR, "P08 HTTP: FAIL (base no allowlisted)\n"); exit(2); }
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->load();
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;
$environment = [];
foreach (file($root . DIRECTORY_SEPARATOR . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) continue;
    [$key, $value] = explode('=', $line, 2);
    $environment[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $environment['DB_HOST'], $environment['DB_PORT'], $database), $environment['DB_USERNAME'], $environment['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) { fwrite(STDERR, "P08 HTTP: FAIL (base activa no coincide)\n"); exit(2); }
$appKey = $appKeyArgument !== '' ? $appKeyArgument : (string)($_ENV['APP_KEY'] ?? '');
if ($appKey === '') { fwrite(STDERR, "P08 HTTP: FAIL (falta APP_KEY efímera)\n"); exit(2); }
$token = static fn(int $clinic, int $patient): string => JWT::encode(['iat' => time(), 'exp' => time() + 600, 'id_clinica' => $clinic, 'id_usuario' => $patient, 'nombre' => 'Paciente sintético ' . $patient, 'vista' => 'historia-clinica', 'rol' => 'Paciente'], $appKey, 'HS256');
$request = static function (string $url, string $jwt = '', ?array $payload = null): array {
    $method = $payload === null ? 'GET' : 'POST';
    $headers = $jwt === '' ? '' : "Cookie: CLINICAJWT={$jwt}\r\n";
    if ($payload !== null) $headers .= "Content-Type: application/json\r\n";
    $body = file_get_contents($url, false, stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'header' => $headers, 'content' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE)]]));
    $status = 0;
    foreach ($http_response_header ?? [] as $header) if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) { $status = (int)$match[1]; break; }
    $decoded = json_decode((string)$body, true);
    return [$status, (string)$body, is_array($decoded) ? $decoded : []];
};
$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$row = $pdo->prepare('SELECT id, medicamento, descripcion, tiempo_uso, dosis, medico FROM pac_medicacion_actual_modulo_6 WHERE id_paciente = ? AND descripcion = ? LIMIT 1');
$row->execute([9301, 'P08-SYNTH-20261011']); $own = $row->fetch(PDO::FETCH_ASSOC);
$row->execute([9302, 'P08-SYNTH-20261011']); $cross = $row->fetch(PDO::FETCH_ASSOC);
if (!$own || !$cross) { fwrite(STDERR, "P08 HTTP: FAIL (fixture ausente; ejecute prepare-synthetic.php)\n"); exit(2); }
$ownId = (int)$own['id']; $crossId = (int)$cross['id']; $createdId = 0; $finalizationCreated = false;
try {
    [$status, $body] = $request($baseUrl . '/historia-clinica/medicacion-actual/paciente/9301', $token(9101, 9301));
    $assert($status === 200 && str_contains($body, 'Medicación Actual'), 'GET propio P08 no fue autorizado; HTTP ' . $status . '; cuerpo=' . substr(trim(strip_tags($body)), 0, 240));
    [$status] = $request($baseUrl . '/historia-clinica/medicacion-actual/paciente/9301');
    $assert($status === 302, 'GET P08 sin sesión no redirigió.');
    [$status] = $request($baseUrl . '/historia-clinica/medicacion-actual/paciente/9301', $token(9102, 9302));
    $assert($status === 403, 'GET P08 cruzado no fue rechazado.');
    [$status, $body] = $request($baseUrl . '/buscar/contenido-preguntas-modulo-6/9301/Doctor', $token(9101, 9301));
    $assert($status === 200 && str_contains($body, 'P08-SYNTH-20261011'), 'El endpoint M6 no derivó el rol real o no renderizó el registro propio.');
    [$status] = $request($baseUrl . '/buscar/contenido-preguntas-modulo-6/9301/Paciente', $token(9102, 9302));
    $assert($status === 403, 'AJAX P08 cruzado no fue rechazado.');

    $newFields = ['descripcion' => 'P08 nombre actualizado', 'medicamento' => 'P08 motivo actualizado', 'tiempo_uso' => 'P08 12 meses', 'dosis' => 'P08 2 veces', 'medico' => 'P08 médica sintética'];
    [$status, , $json] = $request($baseUrl . '/historia-clinica/paciente/editar-medicacion-actual', $token(9101, 9301), ['idPaciente' => 9301, 'idMedicamento' => $ownId, 'fields' => $newFields]);
    $assert($status === 200 && ($json['resultado'] ?? false), 'Edición propia P08 no fue aceptada.');
    $stored = $pdo->prepare('SELECT medicamento, descripcion, tiempo_uso, dosis, medico FROM pac_medicacion_actual_modulo_6 WHERE id = ? AND id_paciente = ?'); $stored->execute([$ownId, 9301]);
    $savedFields = $stored->fetch(PDO::FETCH_ASSOC);
    foreach ($newFields as $field => $value) $assert(($savedFields[$field] ?? null) === $value, 'La edición propia P08 no persistió el campo ' . $field . '.');
    [$status, , $json] = $request($baseUrl . '/historia-clinica/paciente/editar-medicacion-actual', $token(9101, 9301), ['idPaciente' => 9301, 'idMedicamento' => $ownId, 'fields' => $newFields]);
    $assert($status === 200 && ($json['resultado'] ?? false) && str_contains((string)($json['mensaje'] ?? ''), 'No hubo cambios'), 'El no-op P08 no fue idempotente.');
    [$status] = $request($baseUrl . '/historia-clinica/paciente/editar-medicacion-actual', $token(9102, 9302), ['idPaciente' => 9302, 'idMedicamento' => $ownId, 'fields' => ['descripcion' => 'CRUCE-RECHAZADO']]);
    $assert($status === 403, 'Edición cruzada P08 no fue rechazada.');
    [$status] = $request($baseUrl . '/historia-clinica/paciente/eliminar-medicacion-actual', $token(9102, 9302), ['idPaciente' => 9302, 'idMedicamento' => $ownId]);
    $assert($status === 403, 'Eliminación cruzada P08 no fue rechazada.');
    [$status] = $request($baseUrl . '/historia-clinica/paciente/editar-medicacion-actual', $token(9101, 9301), ['idPaciente' => 9301, 'idMedicamento' => $ownId, 'fields' => ['campo_inventado' => 'x']]);
    $assert($status === 422, 'Campo no permitido P08 no fue rechazado.');

    [$status, , $json] = $request($baseUrl . '/historia-clinica/paciente/agregar-medicacion-actual', $token(9101, 9301), ['idPaciente' => 9301]);
    $assert($status === 200 && ($json['resultado'] ?? false), 'Alta propia P08 no fue aceptada.');
    $createdId = (int)$pdo->query('SELECT MAX(id) FROM pac_medicacion_actual_modulo_6 WHERE id_paciente = 9301')->fetchColumn();
    $assert($createdId > 0 && $createdId !== $ownId, 'No se identificó el registro nuevo P08.');
    [$status, , $json] = $request($baseUrl . '/historia-clinica/paciente/eliminar-medicacion-actual', $token(9101, 9301), ['idPaciente' => 9301, 'idMedicamento' => $createdId]);
    $assert($status === 200 && ($json['resultado'] ?? false), 'Baja propia P08 no fue aceptada.');
    $exists = $pdo->prepare('SELECT COUNT(*) FROM pac_medicacion_actual_modulo_6 WHERE id = ? AND id_paciente = 9301'); $exists->execute([$createdId]); $assert((int)$exists->fetchColumn() === 0, 'El registro nuevo P08 no fue eliminado.'); $createdId = 0;

    $existingFinal = (int)$pdo->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo = 6 AND id_paciente = 9301')->fetchColumn();
    $assert($existingFinal === 0, 'La línea base P08 ya estaba finalizada.');
    [$status, , $json] = $request($baseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente', $token(9101, 9301), ['idModulo' => 6, 'idPaciente' => 9301]);
    $assert($status === 200 && ($json['resultado'] ?? false) && is_string($json['next_url'] ?? null) && str_starts_with($json['next_url'], '/'), 'Finalización P08 no devolvió next_url local.');
    $finalizationCreated = true;
    [$status, , $json] = $request($baseUrl . '/historia-clinica/paciente/finalizar-modulo-paciente', $token(9101, 9301), ['idModulo' => 6, 'idPaciente' => 9301]);
    $assert($status === 200 && ($json['resultado'] ?? false), 'La segunda finalización P08 no fue idempotente.');
    echo json_encode(['ok' => true, 'database' => $database, 'own_get' => 'PASS', 'anonymous_get' => 'PASS', 'cross_get' => 'PASS', 'ajax_role_derived' => 'PASS', 'own_update' => 'PASS', 'no_op_without_update' => 'PASS', 'cross_update' => 'PASS', 'cross_delete' => 'PASS', 'own_insert_delete' => 'PASS', 'finalization_and_next_url' => 'PASS', 'finalization_idempotent' => 'PASS'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    if ($createdId > 0) $pdo->prepare('DELETE FROM pac_medicacion_actual_modulo_6 WHERE id = ? AND id_paciente = 9301')->execute([$createdId]);
    fwrite(STDERR, "P08 HTTP: FAIL\n" . $exception->getMessage() . "\n");
    exit(1);
} finally {
    if ($finalizationCreated) $pdo->prepare('DELETE FROM pac_historia_clinica_finalizar WHERE id_modulo = 6 AND id_paciente = 9301')->execute();
}
