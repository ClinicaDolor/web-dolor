<?php
declare(strict_types=1);

use Firebase\JWT\JWT;

$root = dirname(__DIR__, 2);
$allowedDatabase = 'clinica_dolor_demo_20261010_final';
$database = '';
$baseUrl = 'http://127.0.0.1:8117';
$host = 'p11clean.localhost:8117';
$appKey = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) $database = substr($argument, 11);
    if (str_starts_with($argument, '--base-url=')) $baseUrl = rtrim(substr($argument, 11), '/');
    if (str_starts_with($argument, '--host=')) $host = substr($argument, 7);
    if (str_starts_with($argument, '--app-key=')) $appKey = substr($argument, 10);
}

if ($database !== $allowedDatabase || $appKey === '') {
    fwrite(STDERR, "FAIL phase01c allowlist or app key\n");
    exit(2);
}

require $root . '/vendor/autoload.php';

$env = [];
foreach (file($root . DIRECTORY_SEPARATOR . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $allowedDatabase),
    $env['DB_USERNAME'],
    $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== $allowedDatabase) {
    throw new RuntimeException('active-database-mismatch');
}

$clinicId = 9111;
$doctorId = 9211;
$patientA = 9311;
$patientB = 9312;
$ids = [$clinicId, $doctorId, $patientA, $patientB];
$storage = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'evaluacion-dolor';
$publicFront = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'evaluacion-dolor' . DIRECTORY_SEPARATOR . 'frente';
$publicBack = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'evaluacion-dolor' . DIRECTORY_SEPARATOR . 'espalda';
$temporaryFiles = [
    $storage . DIRECTORY_SEPARATOR . 'frente_9311.png',
    $storage . DIRECTORY_SEPARATOR . 'espalda_9311.png',
    $storage . DIRECTORY_SEPARATOR . 'frente_9312.png',
    $storage . DIRECTORY_SEPARATOR . 'espalda_9312.png',
    $publicFront . DIRECTORY_SEPARATOR . 'Frente_F_9311.png',
    $publicBack . DIRECTORY_SEPARATOR . 'Espalda_F_9311.png',
    $publicFront . DIRECTORY_SEPARATOR . 'Frente_M_9312.png',
    $publicBack . DIRECTORY_SEPARATOR . 'Espalda_M_9312.png',
];

$result = ['ok' => false, 'database' => $allowedDatabase, 'checks' => [], 'cleanup' => 'pending'];
$check = static function (array &$result, string $name, bool $pass, string $detail = ''): void {
    $result['checks'][$name] = ['status' => $pass ? 'PASS' : 'FAIL'];
    if ($detail !== '') $result['checks'][$name]['detail'] = $detail;
};
$request = static function (string $url, string $jwt = '', ?string $requestHost = null) use ($host): array {
    $headers = "Host: " . ($requestHost ?: $host) . "\r\n";
    if ($jwt !== '') $headers .= "Cookie: CLINICAJWT={$jwt}\r\n";
    $context = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true, 'follow_location' => 0, 'header' => $headers, 'timeout' => 8]]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) {
            $status = (int) $match[1];
            break;
        }
    }
    return [$status, (string) $body];
};
$token = static function (int $clinic, int $patient, string $vista, string $rol) use ($appKey): string {
    return JWT::encode([
        'iat' => time(),
        'exp' => time() + 600,
        'id_clinica' => $clinic,
        'id_usuario' => $patient,
        'nombre' => 'Fixture PHASE-01C',
        'vista' => $vista,
        'rol' => $rol,
    ], $appKey, 'HS256');
};

$created = false;
try {
    foreach ($temporaryFiles as $path) {
        if (is_file($path)) throw new RuntimeException('fixture-file-already-exists:' . basename($path));
    }
    $existing = $pdo->prepare('SELECT id FROM pc_paciente WHERE id IN (?, ?) UNION SELECT id FROM clinicas WHERE id = ? UNION SELECT id FROM doctores WHERE id = ?');
    $existing->execute([$patientA, $patientB, $clinicId, $doctorId]);
    if ($existing->fetch()) throw new RuntimeException('fixture-id-already-exists');

    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO clinicas (id, nombre, direccion, telefono, email, horario, horario_apertura, horario_cierre, sitio_web, estatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$clinicId, 'Clínica sintética PHASE-01C', 'Fixture', '0000000000', 'phase01c@example.invalid', '00:00-23:59', '00:00:00', '23:59:59', '', 1]);
    $pdo->prepare('INSERT INTO doctores (id, clinica_id, nombre, especialidad, email) VALUES (?, ?, ?, ?, ?)')
        ->execute([$doctorId, $clinicId, 'Doctor sintético PHASE-01C', 'Dolor', 'doctor-phase01c@example.invalid']);

    $patientSql = 'INSERT INTO pc_paciente (id, id_clinica, nombres, apellido_paterno, apellido_materno, edad, sexo, fecha_nacimiento, estado_civil, curp, lugar_origen, lugar_residencia, ocupacion, num_hijos, edad_hijos, medico_tratante, medico_refiere, diagnostico_inicial, calle, num_interior, num_exterior, colonia, delegacion, cp, municipio, estado, distancia, email, telefono, celular, res_nombre, res_telefono, cuidador, cuidador_telefono, aseguradora, informe_aseguradora, hoja_membretada, formato_aseguradora, honorarios, observaciones, motivo_atencion, quien_recomienda, redes_sociales, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $patient = static function (int $id, string $sex) use ($pdo, $patientSql, $clinicId): void {
        $pdo->prepare($patientSql)->execute([
            $id, $clinicId, 'Paciente sintético ' . $id, 'Fixture', 'Privacidad', 40, $sex,
            '1986-01-01', 'Soltero', 'CURP-FIXTURE-' . $id, 'Fixture', 'Fixture', 'Pruebas', 0, '',
            'Doctor sintético PHASE-01C', 'Fixture', 'Mapa clínico sintético', 'Calle Fixture', '', '1', 'Colonia Fixture',
            'Delegación Fixture', '00000', 'Municipio Fixture', 'Estado Fixture', '0', 'patient-' . $id . '@example.invalid',
            '0000000000', '0000000000', 'Responsable Fixture', '0000000000', 'Cuidador Fixture', '0000000000',
            '', 'N', 'N', 'N', 'N', 'Fixture', 'Prueba de privacidad', 'Fixture', 'N', 1,
        ]);
    };
    $patient($patientA, 'F');
    $patient($patientB, 'M');
    $pdo->commit();
    $created = true;

    if (!is_dir($storage) && !mkdir($storage, 0770, true) && !is_dir($storage)) throw new RuntimeException('storage-create-failed');
    $baseFront = file_get_contents($publicFront . DIRECTORY_SEPARATOR . 'Frente_F.png');
    $baseBack = file_get_contents($publicBack . DIRECTORY_SEPARATOR . 'Espalda_F.png');
    $baseFrontOther = file_get_contents($publicFront . DIRECTORY_SEPARATOR . 'Frente_M.png');
    $baseBackOther = file_get_contents($publicBack . DIRECTORY_SEPARATOR . 'Espalda_M.png');
    if ($baseFront === false || $baseBack === false || $baseFrontOther === false || $baseBackOther === false) throw new RuntimeException('generic-base-missing');
    file_put_contents($temporaryFiles[0], $baseFront, LOCK_EX);
    file_put_contents($temporaryFiles[1], $baseBack, LOCK_EX);
    file_put_contents($temporaryFiles[2], $baseFrontOther, LOCK_EX);
    file_put_contents($temporaryFiles[3], $baseBackOther, LOCK_EX);
    file_put_contents($temporaryFiles[4], $baseFront, LOCK_EX);
    file_put_contents($temporaryFiles[5], $baseBack, LOCK_EX);
    file_put_contents($temporaryFiles[6], $baseFrontOther, LOCK_EX);
    file_put_contents($temporaryFiles[7], $baseBackOther, LOCK_EX);

    $check($result, 'synthetic_fixture_isolation', (string) $pdo->query('SELECT DATABASE()')->fetchColumn() === $allowedDatabase && (int) $pdo->query('SELECT COUNT(*) FROM pc_paciente WHERE id IN (9311, 9312) AND id_clinica = 9111')->fetchColumn() === 2);
    $tokenA = $token($clinicId, $patientA, 'historia-clinica', 'Paciente');
    $tokenB = $token($clinicId, $patientB, 'historia-clinica', 'Paciente');
    $tokenDoctor = $token($clinicId, $doctorId, 'clinica', 'Doctor');
    [$status, $body] = $request($baseUrl . '/clinica/paciente/imagen-evaluacion-dolor/frente/9311', $tokenA);
    $check($result, 'patient_a_own_access', $status === 200 && $body === $baseFront, 'HTTP ' . $status);
    [$status, $body] = $request($baseUrl . '/clinica/paciente/imagen-evaluacion-dolor/frente/9312', $tokenB);
    $check($result, 'patient_b_own_access', $status === 200 && $body === $baseFrontOther, 'HTTP ' . $status);
    [$status, $body] = $request($baseUrl . '/clinica/paciente/imagen-evaluacion-dolor/frente/9312', $tokenA);
    $check($result, 'patient_a_cross_denial', in_array($status, [403, 404], true) && $body !== $baseFrontOther, 'HTTP ' . $status);
    [$status, $body] = $request($baseUrl . '/clinica/paciente/imagen-evaluacion-dolor/frente/9311', $tokenB);
    $check($result, 'patient_b_cross_denial', in_array($status, [403, 404], true) && $body !== $baseFront, 'HTTP ' . $status);
    [$statusA, $bodyA] = $request($baseUrl . '/clinica/paciente/imagen-evaluacion-dolor/frente/9311', $tokenDoctor);
    [$statusB, $bodyB] = $request($baseUrl . '/clinica/paciente/imagen-evaluacion-dolor/frente/9312', $tokenDoctor);
    $check($result, 'authorized_doctor_access', $statusA === 200 && $bodyA === $baseFront && $statusB === 200 && $bodyB === $baseFrontOther, 'HTTP ' . $statusA . '/' . $statusB);
    [$status, $body] = $request($baseUrl . '/clinica/paciente/imagen-evaluacion-dolor/frente/9311');
    $check($result, 'anonymous_denial', in_array($status, [302, 401, 403], true) && $body !== $baseFront, 'HTTP ' . $status);
    [$status, $body] = $request('http://clinica.test/assets/images/evaluacion-dolor/frente/Frente_F_9311.png', '', 'clinica.test');
    $check($result, 'apache_static_denial_a', $status === 403 && $body !== $baseFront, 'HTTP ' . $status);
    [$status, $body] = $request('http://clinica.test/assets/images/evaluacion-dolor/frente/Frente_M_9312.png', '', 'clinica.test');
    $check($result, 'apache_static_denial_b', $status === 403 && $body !== $baseFrontOther, 'HTTP ' . $status);
    $result['ok'] = !array_filter($result['checks'], static fn(array $item): bool => $item['status'] === 'FAIL');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $result['error'] = 'La prueba de aislamiento PHASE-01C no pudo completarse.';
}

try {
    foreach ($temporaryFiles as $path) if (is_file($path)) @unlink($path);
    if ($created) {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM pc_paciente WHERE id IN (?, ?)')->execute([$patientA, $patientB]);
        $pdo->prepare('DELETE FROM doctores WHERE id = ?')->execute([$doctorId]);
        $pdo->prepare('DELETE FROM clinicas WHERE id = ?')->execute([$clinicId]);
        $pdo->commit();
    }
    $remaining = (int) $pdo->query('SELECT COUNT(*) FROM pc_paciente WHERE id IN (9311, 9312)')
        ->fetchColumn();
    $remaining += (int) $pdo->query('SELECT COUNT(*) FROM clinicas WHERE id = 9111')->fetchColumn();
    $remaining += (int) $pdo->query('SELECT COUNT(*) FROM doctores WHERE id = 9211')->fetchColumn();
    foreach ($temporaryFiles as $path) if (is_file($path)) $remaining++;
    $result['cleanup'] = $remaining === 0 ? 'PASS' : 'FAIL';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $result['cleanup'] = 'FAIL';
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit(($result['ok'] ?? false) && $result['cleanup'] === 'PASS' ? 0 : 1);
