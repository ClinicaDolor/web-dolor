<?php

declare(strict_types=1);

use App\Config\Database;
use Dotenv\Dotenv;
use Firebase\JWT\JWT;

/**
 * Prueba integrada focalizada de P1-AUTH-01 para P04.
 *
 * Reglas de seguridad del runner:
 * - la base y los dos pacientes se reciben explícitamente;
 * - sólo se aceptan bases locales allowlisted;
 * - la conexión PDO se usa únicamente para SELECT/assertions;
 * - los registros se crean por HTTP con la autorización real y nunca se
 *   eliminan automáticamente al terminar;
 * - la clave JWT sólo se lee de P04_TEST_APP_KEY y nunca se acepta por CLI.
 */

$root = dirname(__DIR__, 2);
$allowedDatabases = [
    'bd_clinica',
    'clinica_dolor_demo_20261010_final',
];
$arguments = [];
foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--')) {
        continue;
    }

    [$key, $value] = array_pad(explode('=', $argument, 2), 2, '');
    $arguments[substr($key, 2)] = $value;
}

$fail = static function (string $message, array $createdRecordIds = []): never {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'error' => $message,
        'created_record_ids' => $createdRecordIds,
        'automatic_cleanup' => false,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
};

$database = $arguments['database'] ?? (getenv('P04_TEST_DATABASE') ?: '');
$baseUrl = rtrim($arguments['base-url'] ?? (getenv('P04_TEST_BASE_URL') ?: ''), '/');
$patientA = $arguments['patient-a'] ?? (getenv('P04_TEST_PATIENT_A') ?: '');
$patientB = $arguments['patient-b'] ?? (getenv('P04_TEST_PATIENT_B') ?: '');
$isolationMode = $arguments['isolation-mode'] ?? (getenv('P04_TEST_ISOLATION_MODE') ?: '');
$ownerAttestation = $arguments['owner-attestation'] ?? (getenv('P04_SYNTHETIC_OWNER_ATTESTATION') ?: '');
$runId = getenv('P04_TEST_RUN_ID') ?: ('P04-AUTH-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4)));
$signingKey = (string) (getenv('P04_TEST_APP_KEY') ?: '');
$createdRecordIds = [];

if ($database === '' || !in_array($database, $allowedDatabases, true)) {
    $fail('La base debe indicarse explícitamente y pertenecer a la allowlist local de P04.');
}
if (!in_array($isolationMode, ['intra-clinic', 'cross-clinic'], true)) {
    $fail('Indique --isolation-mode=intra-clinic o --isolation-mode=cross-clinic explícitamente.');
}
if ($isolationMode === 'intra-clinic' && ($database !== 'bd_clinica' || $ownerAttestation !== 'bd_clinica-ficticia-confirmada')) {
    $fail('El modo intra-clinic exige bd_clinica y la atestación explícita del propietario.');
}
if (!preg_match('/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/i', $baseUrl)) {
    $fail('La URL base debe apuntar únicamente a localhost/127.0.0.1.');
}
if (!ctype_digit((string) $patientA) || !ctype_digit((string) $patientB) || (int) $patientA < 1 || (int) $patientB < 1 || $patientA === $patientB) {
    $fail('Se requieren dos IDs sintéticos distintos mediante --patient-a/--patient-b o P04_TEST_PATIENT_A/B.');
}
if (!preg_match('/^[A-Za-z0-9_-]{8,80}$/', $runId)) {
    $fail('P04_TEST_RUN_ID contiene caracteres o longitud no permitidos.');
}
if (strlen($signingKey) < 32) {
    $fail('Falta una APP_KEY efímera en P04_TEST_APP_KEY; nunca se acepta por argumento visible.');
}

$patientA = (int) $patientA;
$patientB = (int) $patientB;
if ($patientA === $patientB) {
    $fail('Los IDs sintéticos deben corresponder a dos pacientes distintos.');
}
$envPath = $root . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envPath)) {
    $fail('No existe .env para resolver la conexión local.');
}

$environment = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $value = trim($value);
    if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
        $value = substr($value, 1, -1);
    }
    $environment[trim($key)] = $value;
}

foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $requiredKey) {
    if (!array_key_exists($requiredKey, $environment)) {
        $fail('Falta una variable de conexión local requerida en .env.');
    }
}
if (!in_array(strtolower($environment['DB_HOST']), ['127.0.0.1', 'localhost', '::1'], true) || (string) $environment['DB_PORT'] !== '3306') {
    $fail('La conexión no apunta al MySQL local esperado (host local y puerto 3306).');
}

require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->load();
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;
$pdo = Database::getInstance();
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
    $fail('La conexión activa no coincide exactamente con la base solicitada.');
}

$patientStatement = $pdo->prepare(
    'SELECT id, id_clinica, nombres, apellido_paterno, apellido_materno
     FROM pc_paciente
     WHERE id IN (?, ?)
     ORDER BY id'
);
$patientStatement->execute([$patientA, $patientB]);
$patients = $patientStatement->fetchAll(PDO::FETCH_ASSOC);
$syntheticNames = static function (array $patient): bool {
    $name = strtolower(
        (string) ($patient['nombres'] ?? '') . ' ' .
        (string) ($patient['apellido_paterno'] ?? '') . ' ' .
        (string) ($patient['apellido_materno'] ?? '')
    );
    return str_contains($name, 'sint') || str_contains($name, 'test') || str_contains($name, 'prueba');
};
$patientIds = array_map(static fn (array $patient): int => (int) $patient['id'], $patients);
$expectedPatientIds = [$patientA, $patientB];
sort($patientIds);
sort($expectedPatientIds);
$clinicIds = array_values(array_unique(array_map(
    static fn (array $patient): int => (int) $patient['id_clinica'],
    $patients
)));
if (count($patients) !== 2 || $patientIds !== $expectedPatientIds) {
    $fail('Los dos IDs proporcionados no corresponden a expedientes existentes y distintos.');
}
if ($isolationMode === 'intra-clinic' && (count($clinicIds) !== 1 || $clinicIds[0] !== 1)) {
    $fail('El modo intra-clinic exige que ambos pacientes pertenezcan a id_clinica=1.');
}
if ($isolationMode === 'cross-clinic' && (count(array_filter($patients, $syntheticNames)) !== 2 || count($clinicIds) !== 2)) {
    $fail('El modo cross-clinic exige marcadores sintéticos y clínicas distintas.');
}

$accessStatement = $pdo->prepare(
    'SELECT paciente_id,
            COUNT(*) AS total,
            SUM(CASE WHEN estatus = 0 AND fecha_expiracion > NOW() THEN 1 ELSE 0 END) AS activos
     FROM pc_paciente_acceso
     WHERE paciente_id IN (?, ?)
     GROUP BY paciente_id'
);
$accessStatement->execute([$patientA, $patientB]);
$accessRows = $accessStatement->fetchAll(PDO::FETCH_ASSOC);
$distinctAccessStatement = $pdo->prepare(
    'SELECT COUNT(DISTINCT pin)
     FROM pc_paciente_acceso
     WHERE paciente_id IN (?, ?) AND estatus = 0 AND fecha_expiracion > NOW()'
);
$distinctAccessStatement->execute([$patientA, $patientB]);
$independentAccessCredentials = (int) $distinctAccessStatement->fetchColumn() >= 2;
if (count($accessRows) !== 2 || count(array_filter($accessRows, static fn (array $row): bool => (int) $row['total'] >= 1 && (int) $row['activos'] >= 1)) !== 2 || !$independentAccessCredentials) {
    $fail('No se pudieron confirmar dos identidades de acceso activas e independientes.');
}

$clinicByPatient = [];
foreach ($patients as $patient) {
    $clinicByPatient[(int) $patient['id']] = (int) $patient['id_clinica'];
}
$token = static function (int $patient) use ($signingKey, $clinicByPatient): string {
    return JWT::encode([
        'iat' => time(),
        'exp' => time() + 900,
        'id_clinica' => $clinicByPatient[$patient],
        'id_usuario' => $patient,
        'nombre' => 'Paciente sintético',
        'vista' => 'historia-clinica',
        'rol' => 'Paciente',
    ], $signingKey, 'HS256');
};

$request = static function (string $url, string $jwt = '', ?array $payload = null): array {
    $headers = $jwt === '' ? '' : "Cookie: CLINICAJWT={$jwt}\r\n";
    if ($payload !== null) {
        $headers .= "Content-Type: application/json\r\n";
    }
    $body = file_get_contents($url, false, stream_context_create(['http' => [
        'method' => $payload === null ? 'GET' : 'POST',
        'ignore_errors' => true,
        'follow_location' => 0,
        'header' => $headers,
        'content' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]]));
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }
    $body = (string) $body;
    $decoded = json_decode($body, true);
    return [$status, $body, is_array($decoded) ? $decoded : []];
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$snapshot = static function (PDO $connection, int $firstPatient, int $secondPatient): array {
    $statement = $connection->prepare(
        'SELECT id, id_paciente, enfermedad, detalle, tipo, especificar
         FROM pc_antecedentes_familiares
         WHERE id_paciente IN (?, ?)
         ORDER BY id'
    );
    $statement->execute([$firstPatient, $secondPatient]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
};

$recordIds = static function (PDO $connection, int $patient): array {
    $statement = $connection->prepare('SELECT id FROM pc_antecedentes_familiares WHERE id_paciente = ? ORDER BY id');
    $statement->execute([$patient]);
    return array_map(static fn (array $row): int => (int) $row['id'], $statement->fetchAll(PDO::FETCH_ASSOC));
};

$sameRowsExcept = static function (array $before, array $after, int $ignoredId): bool {
    $filter = static fn (array $rows): array => array_values(array_filter(
        $rows,
        static fn (array $row): bool => (int) $row['id'] !== $ignoredId
    ));
    return $filter($before) === $filter($after);
};

$createAuthorizedRecord = static function (PDO $connection, int $patient, string $jwt) use ($baseUrl, $request, $recordIds, $assert, &$createdRecordIds): int {
    $beforeIds = $recordIds($connection, $patient);
    [$status, , $json] = $request($baseUrl . '/historia-clinica/paciente/agregar-enfermedad-antecedentes', $jwt, [
        'idPaciente' => $patient,
        'idRol' => 'Paciente',
    ]);
    $assert($status === 200 && ($json['resultado'] ?? false), 'No se pudo crear el registro temporal mediante el flujo HTTP autorizado.');
    $newIds = array_values(array_diff($recordIds($connection, $patient), $beforeIds));
    $assert(count($newIds) === 1, 'La creación HTTP no produjo exactamente un registro nuevo identificable.');
    $createdRecordIds[] = ['patient' => $patient, 'record' => $newIds[0]];
    return $newIds[0];
};

$markAuthorizedRecord = static function (int $patient, int $record, string $jwt, string $marker) use ($baseUrl, $request, $assert): void {
    [$status, , $json] = $request($baseUrl . '/historia-clinica/paciente/editar-enfermedad-antecedentes', $jwt, [
        'idPaciente' => $patient,
        'idEnfermedad' => $record,
        'idRol' => 'Paciente',
        'edicion' => 1,
        'enfermedad' => $marker,
        'detalle' => $marker,
    ]);
    $assert($status === 200 && ($json['resultado'] ?? false), 'No se pudo etiquetar el registro nuevo mediante el flujo HTTP autorizado.');
};

try {
    $marker = 'P04 AUTH TEST ' . $runId;
    $beforeReadOnly = $snapshot($pdo, $patientA, $patientB);
    [$status] = $request($baseUrl . '/historia-clinica/antecedentes-familiares/paciente/' . $patientA, $token($patientA));
    $assert($status === 200 && $beforeReadOnly === $snapshot($pdo, $patientA, $patientB), 'GET propio P04 escribió o no fue autorizado.');
    [$status, $ajax] = $request($baseUrl . '/buscar/contenido-preguntas-modulo-2/' . $patientA . '/Paciente', $token($patientA));
    $assert($status === 200 && str_contains($ajax, 'pregunta-container') && $beforeReadOnly === $snapshot($pdo, $patientA, $patientB), 'GET AJAX propio P04 falló o escribió.');
    [$status] = $request($baseUrl . '/historia-clinica/antecedentes-familiares/paciente/' . $patientA);
    $assert($status === 302, 'GET P04 sin sesión no redirigió.');
    [$status] = $request($baseUrl . '/historia-clinica/antecedentes-familiares/paciente/' . $patientA, $token($patientB));
    $assert($status === 403, 'GET P04 cruzado no fue rechazado.');
    [$status] = $request($baseUrl . '/buscar/contenido-preguntas-modulo-2/' . $patientA . '/Paciente', $token($patientB));
    $assert($status === 403, 'AJAX P04 cruzado no fue rechazado.');

    $recordA = $createAuthorizedRecord($pdo, $patientA, $token($patientA));
    $recordB = $createAuthorizedRecord($pdo, $patientB, $token($patientB));
    $markAuthorizedRecord($patientA, $recordA, $token($patientA), $marker . ' A');
    $markAuthorizedRecord($patientB, $recordB, $token($patientB), $marker . ' B');

    $markerStatement = $pdo->prepare('SELECT COUNT(*) FROM pc_antecedentes_familiares WHERE id = ? AND id_paciente = ? AND enfermedad = ?');
    $markerStatement->execute([$recordA, $patientA, $marker . ' A']);
    $assert((int) $markerStatement->fetchColumn() === 1, 'No se confirmó la propiedad del registro nuevo de A.');
    $markerStatement->execute([$recordB, $patientB, $marker . ' B']);
    $assert((int) $markerStatement->fetchColumn() === 1, 'No se confirmó la propiedad del registro nuevo de B.');

    $beforeOwnDelete = $snapshot($pdo, $patientA, $patientB);
    [$status] = $request($baseUrl . '/historia-clinica/paciente/eliminar-enfermedad-antecedentes', $token($patientA), [
        'idEnfermedad' => $recordA,
        'idPaciente' => $patientB,
        'idRol' => 'Doctor',
    ]);
    $afterOwnDelete = $snapshot($pdo, $patientA, $patientB);
    $assert($status === 200 && $sameRowsExcept($beforeOwnDelete, $afterOwnDelete, $recordA), 'El paciente A no pudo eliminar su registro usando su identidad autorizada.');
    $remainingOwn = $pdo->prepare('SELECT COUNT(*) FROM pc_antecedentes_familiares WHERE id = ?');
    $remainingOwn->execute([$recordA]);
    $assert((int) $remainingOwn->fetchColumn() === 0, 'El registro propio de A continuó presente tras una eliminación autorizada.');

    $beforeCrossDelete = $snapshot($pdo, $patientA, $patientB);
    [$status] = $request($baseUrl . '/historia-clinica/paciente/eliminar-enfermedad-antecedentes', $token($patientA), [
        'idEnfermedad' => $recordB,
        'idPaciente' => $patientA,
        'idRol' => 'Paciente',
    ]);
    $afterCrossDelete = $snapshot($pdo, $patientA, $patientB);
    $assert($status === 404 && $beforeCrossDelete === $afterCrossDelete, 'La eliminación cruzada modificó o reveló el registro de B.');

    $beforeMissing = $snapshot($pdo, $patientA, $patientB);
    [$status] = $request($baseUrl . '/historia-clinica/paciente/eliminar-enfermedad-antecedentes', $token($patientA), [
        'idEnfermedad' => 2147483647,
        'idPaciente' => $patientA,
        'idRol' => 'Paciente',
    ]);
    $assert($status === 404 && $beforeMissing === $snapshot($pdo, $patientA, $patientB), 'El identificador inexistente no produjo una respuesta controlada.');

    $beforeAnonymous = $snapshot($pdo, $patientA, $patientB);
    [$status] = $request($baseUrl . '/historia-clinica/paciente/eliminar-enfermedad-antecedentes', '', [
        'idEnfermedad' => $recordB,
        'idPaciente' => $patientB,
        'idRol' => 'Paciente',
    ]);
    $assert(in_array($status, [302, 401, 403], true) && $beforeAnonymous === $snapshot($pdo, $patientA, $patientB), 'La eliminación sin sesión no fue rechazada de forma segura.');

    echo json_encode([
        'ok' => true,
        'database' => $database,
        'isolation_mode' => $isolationMode,
        'run_id' => $runId,
        'own_get_and_ajax_without_write' => 'PASS',
        'anonymous_and_cross_read_rejection' => 'PASS',
        'authorized_own_delete' => 'PASS',
        'cross_patient_delete' => 'PASS',
        'missing_record_delete' => 'PASS',
        'unauthenticated_delete' => 'PASS',
        'created_record_ids' => $createdRecordIds,
        'automatic_cleanup' => false,
        'manual_cleanup_requires_explicit_approval' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $exception) {
    $fail($exception->getMessage(), $createdRecordIds);
}
