<?php

declare(strict_types=1);

use App\Config\Database;
use Dotenv\Dotenv;
use Firebase\JWT\JWT;

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_demo_20261010_final';
$baseUrl = 'http://127.0.0.1:8081';
$appKey = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $database = substr($argument, strlen('--database='));
    if (str_starts_with($argument, '--base-url=')) $baseUrl = rtrim(substr($argument, strlen('--base-url=')), '/');
    if (str_starts_with($argument, '--app-key=')) $appKey = substr($argument, strlen('--app-key='));
}
if ($database !== 'clinica_dolor_demo_20261010_final') {
    fwrite(STDERR, "P04 HTTP CRUD: FAIL (base no allowlisted)\n");
    exit(2);
}

require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->load();
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;
$signingKey = $appKey !== '' ? $appKey : (string) ($_ENV['APP_KEY'] ?? '');
if ($signingKey === '') {
    fwrite(STDERR, "P04 HTTP CRUD: FAIL (falta APP_KEY efímera)\n");
    exit(2);
}
$pdo = Database::getInstance();
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
    fwrite(STDERR, "P04 HTTP CRUD: FAIL (base activa no coincide)\n");
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

$request = static function (string $url, string $jwt = '', ?array $payload = null): array {
    $headers = $jwt === '' ? '' : "Cookie: CLINICAJWT={$jwt}\r\n";
    if ($payload !== null) $headers .= "Content-Type: application/json\r\n";
    $body = file_get_contents($url, false, stream_context_create(['http' => [
        'method' => $payload === null ? 'GET' : 'POST',
        'ignore_errors' => true,
        'follow_location' => 0,
        'header' => $headers,
        'content' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]]));
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }
    return [$status, (string) $body, is_array($decoded = json_decode((string) $body, true)) ? $decoded : []];
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$snapshot = static function (PDO $pdo): string {
    $rows = $pdo->query('SELECT id, id_paciente, enfermedad, detalle, tipo, especificar FROM pc_antecedentes_familiares WHERE id_paciente IN (9301, 9302) ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    return json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
};

$fixedDisease = 'Diabetes Mellitus';
$original = null;
$createdId = 0;
try {
    $before = $snapshot($pdo);
    [$status, $page] = $request($baseUrl . '/historia-clinica/antecedentes-familiares/paciente/9301', $token(9101, 9301));
    $afterPage = $snapshot($pdo);
    $assert($status === 200 && $before === $afterPage, 'GET propio P04 escribió o no fue autorizado. HTTP=' . $status);
    [$status, $ajax] = $request($baseUrl . '/buscar/contenido-preguntas-modulo-2/9301/Paciente', $token(9101, 9301));
    $afterAjax = $snapshot($pdo);
    $assert($status === 200 && str_contains($ajax, 'pregunta-container') && $before === $afterAjax, 'GET AJAX P04 escribió o no renderizó el catálogo. HTTP=' . $status);
    [$status] = $request($baseUrl . '/historia-clinica/antecedentes-familiares/paciente/9301');
    $assert($status === 302, 'GET P04 sin sesión no redirigió.');
    [$status] = $request($baseUrl . '/historia-clinica/antecedentes-familiares/paciente/9301', $token(9102, 9302));
    $assert($status === 403, 'GET P04 cruzado no fue rechazado.');
    [$status] = $request($baseUrl . '/buscar/contenido-preguntas-modulo-2/9301/Paciente', $token(9102, 9302));
    $assert($status === 403, 'AJAX P04 cruzado no fue rechazado.');

    $find = $pdo->prepare('SELECT id, enfermedad, detalle, tipo, especificar FROM pc_antecedentes_familiares WHERE id_paciente = ? AND enfermedad = ? LIMIT 1');
    $find->execute([9301, $fixedDisease]);
    $original = $find->fetch(PDO::FETCH_ASSOC) ?: null;
    $id = $original ? (int) $original['id'] : 0;
    [$status, , $json] = $request($baseUrl . '/historia-clinica/paciente/editar-enfermedad-antecedentes', $token(9101, 9301), [
        'idPaciente' => 9301,
        'idEnfermedad' => $id,
        'enfermedad' => $fixedDisease,
        'edicion' => 2,
        'detalle' => 'No',
    ]);
    $assert($status === 200 && ($json['resultado'] ?? false), 'Primera captura POST P04 no fue aceptada.');
    if (!$original) {
        $find->execute([9301, $fixedDisease]);
        $createdId = (int) $find->fetchColumn();
        $assert($createdId > 0, 'No se identificó la fila sintética creada por POST P04.');
    }
    [$status] = $request($baseUrl . '/historia-clinica/paciente/editar-enfermedad-antecedentes', $token(9102, 9302), [
        'idPaciente' => 9301,
        'idEnfermedad' => $createdId ?: (int) $original['id'],
        'enfermedad' => $fixedDisease,
        'edicion' => 2,
        'detalle' => 'Si',
    ]);
    $assert($status === 403, 'POST P04 cruzado no fue rechazado.');
    echo json_encode([
        'ok' => true,
        'database' => $database,
        'own_get_without_write' => 'PASS',
        'ajax_get_without_write' => 'PASS',
        'anonymous_get' => 'PASS',
        'cross_get_and_ajax' => 'PASS',
        'first_post_capture' => 'PASS',
        'cross_post' => 'PASS',
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, "P04 HTTP CRUD: FAIL\n" . $exception->getMessage() . "\n");
    exit(1);
} finally {
    if ($createdId > 0) {
        $pdo->prepare('DELETE FROM pc_antecedentes_familiares WHERE id = ? AND id_paciente = 9301')->execute([$createdId]);
    } elseif ($original) {
        $restore = $pdo->prepare('UPDATE pc_antecedentes_familiares SET enfermedad = ?, detalle = ?, tipo = ?, especificar = ? WHERE id = ? AND id_paciente = 9301');
        $restore->execute([$original['enfermedad'], $original['detalle'], $original['tipo'], $original['especificar'], $original['id']]);
    }
}
