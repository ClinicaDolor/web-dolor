<?php

declare(strict_types=1);

/**
 * M07 B1: aceptación HTTP de lectura canónica compartida.
 *
 * Este arnés sólo ejecuta POST de autenticación y GET clínicos. No captura,
 * finaliza, edita ni crea datos clínicos. Los tokens Doctor son entregados por
 * el coordinador después de autenticarse en la misma instancia Apache.
 */

$root = dirname(__DIR__, 2);
$options = [];
foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--')) {
        continue;
    }
    [$key, $value] = array_pad(explode('=', $argument, 2), 2, '1');
    $options[substr($key, 2)] = $value;
}

$database = (string)($options['database'] ?? '');
$baseUrl = rtrim((string)($options['base-url'] ?? ''), '/');
$patientA = (int)($options['patient-a'] ?? 0);
$patientB = (int)($options['patient-b'] ?? 0);
$doctorTokenA = (string)(getenv('M07_B1_DOCTOR_TOKEN_A') ?: '');
$doctorTokenB = (string)(getenv('M07_B1_DOCTOR_TOKEN_B') ?: '');

$caseInventory = [
    'patient_a_login',
    'patient_b_login',
    'doctor_a_login',
    'doctor_b_login',
    'patient_a_interview_get',
    'patient_b_interview_get',
    'patient_a_p09_get',
    'patient_b_p09_get',
    'patient_a_p09_content_get',
    'patient_b_p09_content_get',
    'doctor_a_p09_get',
    'doctor_b_p09_get',
    'doctor_a_m07_content_get',
    'doctor_b_m07_content_get',
    'unauthenticated_get_rejected',
    'cross_clinic_patient_get_rejected',
    'cross_clinic_doctor_get_rejected',
    'get_requests_no_clinical_write',
    'unknown_states_coverage',
    'non_catalog_medication_coverage',
];

$result = [
    'ok' => false,
    'status' => 'NOT_REACHED',
    'database' => $database,
    'read_only_clinical_matrix' => true,
    'expected_cases' => $caseInventory,
    'checks' => [],
    'http_operations' => [],
    'cases_passed' => [],
    'cases_failed' => [],
    'cases_not_reached' => $caseInventory,
    'cases_not_covered' => [],
    'unexpected_cases' => [],
    'case_inventory_reconciled' => false,
    'clinical_state_unchanged' => 'NOT_REACHED',
    'clinical_write_detected' => false,
    'content' => [],
];

$check = static function (string $name, string $status, string $detail = '') use (&$result): void {
    if (!in_array($status, ['PASS', 'FAIL', 'NOT_REACHED', 'NOT_COVERED'], true)) {
        throw new InvalidArgumentException('Estado de comprobación no permitido.');
    }
    $result['checks'][$name] = ['status' => $status];
    if ($detail !== '') {
        $result['checks'][$name]['detail'] = $detail;
    }
};

$finalize = static function () use (&$result, $caseInventory): void {
    $passed = [];
    $failed = [];
    $notReached = [];
    $notCovered = [];
    $unexpected = [];
    foreach ($result['checks'] as $name => $checkResult) {
        if (!in_array($name, $caseInventory, true)) {
            $unexpected[] = $name;
            continue;
        }
        $status = (string)($checkResult['status'] ?? 'NOT_REACHED');
        if ($status === 'PASS') {
            $passed[] = $name;
        } elseif ($status === 'FAIL') {
            $failed[] = $name;
        } elseif ($status === 'NOT_COVERED') {
            $notCovered[] = $name;
        } else {
            $notReached[] = $name;
        }
    }
    foreach ($caseInventory as $name) {
        if (!array_key_exists($name, $result['checks'])) {
            $notReached[] = $name;
        }
    }
    $result['cases_passed'] = array_values(array_unique($passed));
    $result['cases_failed'] = array_values(array_unique($failed));
    $result['cases_not_reached'] = array_values(array_unique($notReached));
    $result['cases_not_covered'] = array_values(array_unique($notCovered));
    $result['unexpected_cases'] = array_values(array_unique($unexpected));
    $result['case_inventory_reconciled'] = $result['unexpected_cases'] === []
        && count(array_unique(array_merge($passed, $failed, $notReached, $notCovered))) === count($caseInventory);
};

$fail = static function (string $message) use (&$result, $finalize): never {
    $result['error'] = preg_replace('/[\r\n]+/', ' ', $message) ?: 'Fallo no detallado.';
    $finalize();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
};

if ($database !== 'clinica_dolor_demo_20261010_final') {
    $fail('La base solicitada no coincide con la allowlist sintética.');
}
if (!preg_match('/^http:\/\/127\.0\.0\.1:8117$/i', $baseUrl)) {
    $fail('La URL debe ser exclusivamente el listener loopback 8117.');
}
if ($patientA !== 9301 || $patientB !== 9302) {
    $fail('Los pacientes deben ser exactamente los fixtures sintéticos autorizados.');
}
if ($doctorTokenA === '' || $doctorTokenB === '') {
    $fail('Las dos sesiones Doctor sintéticas deben autenticarse antes de la matriz.');
}

$readEnv = static function (string $path): array {
    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
    return $values;
};

$env = $readEnv($root . DIRECTORY_SEPARATOR . '.env');
foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
    if (!array_key_exists($key, $env)) {
        $fail('Falta configuración local de conexión.');
    }
}
if (!in_array(strtolower((string)$env['DB_HOST']), ['127.0.0.1', 'localhost', '::1'], true)
    || (string)$env['DB_PORT'] !== '3306') {
    $fail('La conexión de comprobación no apunta a MySQL local en 3306.');
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $database),
        $env['DB_USERNAME'],
        $env['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $effectiveDatabase = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($effectiveDatabase !== $database) {
        $fail('La comprobación PDO no coincide con la base sintética.');
    }
    $result['database_effective'] = $effectiveDatabase;
} catch (Throwable) {
    $fail('No fue posible abrir la comprobación PDO local.');
}

$patientStatement = $pdo->prepare('SELECT id, id_clinica FROM pc_paciente WHERE id IN (?, ?) ORDER BY id');
$patientStatement->execute([$patientA, $patientB]);
$patients = $patientStatement->fetchAll(PDO::FETCH_ASSOC);
$patientMap = [];
foreach ($patients as $patient) {
    $patientMap[(int)$patient['id']] = (int)$patient['id_clinica'];
}
if (count($patients) !== 2 || (($patientMap[$patientA] ?? 0) !== 9101) || (($patientMap[$patientB] ?? 0) !== 9102)) {
    $fail('Los pacientes sintéticos no corresponden a las clínicas autorizadas.');
}

$rowsForTable = static function (PDO $connection, string $table, string $where, array $parameters): array {
    $statement = $connection->prepare("SELECT * FROM {$table} WHERE {$where} ORDER BY id");
    $statement->execute($parameters);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
};
$clinicalState = static function () use ($pdo, $patientA, $patientB, $rowsForTable): string {
    $definitions = [
        'pac_respuestas_paciente_modulo_7' => ['id_paciente IN (?, ?)', [$patientA, $patientB]],
        'pac_m07_medicamentos_no_catalogados' => ['id_paciente IN (?, ?)', [$patientA, $patientB]],
        'pac_m07_operaciones' => ['id_paciente IN (?, ?)', [$patientA, $patientB]],
        'pac_m07_revision_clinica' => ['id_paciente IN (?, ?)', [$patientA, $patientB]],
    ];
    $rows = [];
    foreach ($definitions as $table => [$where, $parameters]) {
        $rows[$table] = $rowsForTable($pdo, $table, $where, $parameters);
    }
    return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
};

$safeCode = static function (array $json): string {
    $code = (string)($json['code'] ?? '');
    return preg_match('/^[A-Z0-9_]{1,80}$/', $code) === 1 ? $code : '';
};
$request = static function (string $method, string $path, string $token = '', ?array $payload = null) use ($baseUrl, &$result, $safeCode): array {
    $headers = '';
    if ($token !== '') {
        $headers .= 'Cookie: CLINICAJWT=' . $token . "\r\n";
    }
    if ($payload !== null) {
        $headers .= "Content-Type: application/json\r\n";
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 15,
        'header' => $headers,
        'content' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]]);
    $responseHeaders = [];
    $body = @file_get_contents($baseUrl . $path, false, $context);
    foreach (($http_response_header ?? []) as $header) {
        $responseHeaders[] = (string)$header;
    }
    $status = 0;
    $location = false;
    foreach ($responseHeaders as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) {
            $status = (int)$match[1];
        }
        if (stripos($header, 'Location:') === 0) {
            $location = true;
        }
    }
    $text = (string)$body;
    $decoded = json_decode($text, true);
    $json = is_array($decoded) ? $decoded : [];
    $result['http_operations'][] = [
        'sequence' => count($result['http_operations']) + 1,
        'method' => $method,
        'path' => $path,
        'http_status' => $status,
        'contract_code' => $safeCode($json),
        'location_present' => $location,
    ];
    return [$status, $text, $json, $location];
};

$loginPatient = static function (string $pin) use ($request): string {
    [$status, , $json] = $request('POST', '/historia-clinica/login', '', ['pin' => $pin]);
    if ($status !== 200 || ($json['resultado'] ?? false) !== true || !is_string($json['token'] ?? null) || $json['token'] === '') {
        return '';
    }
    return (string)$json['token'];
};
$decodeClaims = static function (string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    $payload = strtr($parts[1], '-_', '+/');
    $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
    $decoded = base64_decode($payload, true);
    $claims = is_string($decoded) ? json_decode($decoded, true) : null;
    return is_array($claims) ? $claims : null;
};
$doctorClaimsOk = static function (string $token, int $doctorId, int $clinicId) use ($decodeClaims): bool {
    $claims = $decodeClaims($token);
    return is_array($claims)
        && (int)($claims['id_usuario'] ?? 0) === $doctorId
        && (int)($claims['id_clinica'] ?? 0) === $clinicId
        && (string)($claims['vista'] ?? '') === 'clinica'
        && (string)($claims['rol'] ?? '') === 'Doctor';
};

$parseInterview = static function (string $body): array {
    preg_match('/<div id="main"[^>]*data-patient="([0-9]+)"[^>]*data-csrf="([^"]+)"/s', $body, $main);
    preg_match('/<article[^>]*data-module="M07"[^>]*data-m07-write-enabled="([01])"/s', $body, $module);
    preg_match_all('/<section[^>]*data-m07-category\b/s', $body, $categories);
    preg_match_all('/<div class="m07-item"[^>]*data-question-id="([0-9]+)"[^>]*data-theme-id="([0-9]+)"[^>]*data-status="([^"]*)"/s', $body, $items);
    $statuses = array_values(array_filter(array_map('strval', $items[3] ?? []), static fn(string $value): bool => $value !== ''));
    $unknown = preg_match('/(?:NOT_DOCUMENTED_HISTORICAL|PENDING_REVIEW|desconocid|no documentado)/iu', $body) === 1;
    $other = str_contains($body, 'data-other-blocked="1"') || preg_match('/Otro\/Otros|no catalogado/iu', $body) === 1;
    return [
        'patient' => (string)($main[1] ?? ''),
        'csrf' => (string)($main[2] ?? ''),
        'write_enabled' => ($module[1] ?? '1') === '1',
        'categories' => count($categories[0] ?? []),
        'items' => count($items[0] ?? []),
        'statuses' => $statuses,
        'unknown' => $unknown,
        'other' => $other,
        'finalization_enabled' => str_contains($body, 'data-m07-finalize'),
    ];
};
$parseP09 = static function (string $body): array {
    preg_match_all('/data-p09-question-id="([0-9]+)"/s', $body, $questions);
    preg_match_all('/data-p09-theme="([0-9]+)"/s', $body, $themes);
    $readOnly = str_contains($body, 'data-m07-canonical-read-only="1"')
        && str_contains($body, 'disabled');
    return [
        'question_count' => count(array_unique($questions[1] ?? [])),
        'theme_ids' => array_values(array_unique(array_map('intval', $themes[1] ?? []))),
        'read_only' => $readOnly,
        'canonical_status' => str_contains($body, 'data-m07-canonical-status='),
        'has_question' => count($questions[0] ?? []) > 0,
        'page_shell' => str_contains($body, 'id="preguntas-container"') || str_contains($body, 'id="contePreguntas_1"'),
        'non_catalog' => preg_match('/no catalogado|Otro\/Otros/iu', $body) === 1,
    ];
};

$before = $clinicalState();
try {
    $pinA = (string)(getenv('P04_TEST_PIN_A') ?: '');
    $pinB = (string)(getenv('P04_TEST_PIN_B') ?: '');
    if ($pinA === '' || $pinB === '' || $pinA === $pinB) {
        $fail('Faltan credenciales sintéticas independientes en memoria.');
    }

    [$anonymousStatus, , , $anonymousLocation] = $request('GET', '/historia-clinica/entrevista');
    $check('unauthenticated_get_rejected', in_array($anonymousStatus, [302, 401, 403], true) && ($anonymousStatus !== 302 || $anonymousLocation) ? 'PASS' : 'FAIL');

    $tokenA = $loginPatient($pinA);
    $tokenB = $loginPatient($pinB);
    $check('patient_a_login', $tokenA !== '' ? 'PASS' : 'FAIL');
    $check('patient_b_login', $tokenB !== '' ? 'PASS' : 'FAIL');
    $check('doctor_a_login', $doctorClaimsOk($doctorTokenA, 9201, 9101) ? 'PASS' : 'FAIL');
    $check('doctor_b_login', $doctorClaimsOk($doctorTokenB, 9202, 9102) ? 'PASS' : 'FAIL');
    if ($tokenA === '' || $tokenB === '' || !$doctorClaimsOk($doctorTokenA, 9201, 9101) || !$doctorClaimsOk($doctorTokenB, 9202, 9102)) {
        $fail('No se pudieron verificar las cuatro sesiones sintéticas autorizadas.');
    }

    [$status, $body] = $request('GET', '/historia-clinica/entrevista', $tokenA);
    $pageA = $parseInterview($body);
    $result['content']['patient_a_interview'] = [
        'patient' => $pageA['patient'], 'categories' => $pageA['categories'], 'items' => $pageA['items'],
        'write_enabled' => $pageA['write_enabled'], 'finalization_enabled' => $pageA['finalization_enabled'],
    ];
    $check('patient_a_interview_get', $status === 200 && $pageA['patient'] === (string)$patientA && $pageA['categories'] === 6 && $pageA['items'] === 46 && !$pageA['write_enabled'] && !$pageA['finalization_enabled'] ? 'PASS' : 'FAIL');

    [$status, $body] = $request('GET', '/historia-clinica/entrevista', $tokenB);
    $pageB = $parseInterview($body);
    $result['content']['patient_b_interview'] = [
        'patient' => $pageB['patient'], 'categories' => $pageB['categories'], 'items' => $pageB['items'],
        'write_enabled' => $pageB['write_enabled'], 'finalization_enabled' => $pageB['finalization_enabled'],
    ];
    $check('patient_b_interview_get', $status === 200 && $pageB['patient'] === (string)$patientB && $pageB['categories'] === 6 && $pageB['items'] === 46 && !$pageB['write_enabled'] && !$pageB['finalization_enabled'] ? 'PASS' : 'FAIL');

    $p09PathA = '/historia-clinica/medicacion-control-dolor/paciente/' . $patientA;
    $p09PathB = '/historia-clinica/medicacion-control-dolor/paciente/' . $patientB;
    [$status, $body] = $request('GET', $p09PathA, $tokenA);
    $p09PatientA = $parseP09($body);
    $result['content']['patient_a_p09'] = $p09PatientA;
    $check('patient_a_p09_get', $status === 200 && $p09PatientA['page_shell'] ? 'PASS' : 'FAIL');
    [$status, $body] = $request('GET', $p09PathB, $tokenB);
    $p09PatientB = $parseP09($body);
    $result['content']['patient_b_p09'] = $p09PatientB;
    $check('patient_b_p09_get', $status === 200 && $p09PatientB['page_shell'] ? 'PASS' : 'FAIL');

    $contentA = '/buscar/contenido-preguntas-modulo-7/' . $patientA . '/Paciente/1';
    $contentB = '/buscar/contenido-preguntas-modulo-7/' . $patientB . '/Paciente/1';
    [$status, $body] = $request('GET', $contentA, $tokenA);
    $contentPatientA = $parseP09($body);
    $result['content']['patient_a_p09_content'] = $contentPatientA;
    $check('patient_a_p09_content_get', $status === 200 && $contentPatientA['read_only'] && $contentPatientA['has_question'] && in_array(1, $contentPatientA['theme_ids'], true) ? 'PASS' : 'FAIL');
    [$status, $body] = $request('GET', $contentB, $tokenB);
    $contentPatientB = $parseP09($body);
    $result['content']['patient_b_p09_content'] = $contentPatientB;
    $check('patient_b_p09_content_get', $status === 200 && $contentPatientB['read_only'] && $contentPatientB['has_question'] && in_array(1, $contentPatientB['theme_ids'], true) ? 'PASS' : 'FAIL');

    $doctorPathA = '/clinica/medicacion-control-dolor/paciente/' . $patientA;
    $doctorPathB = '/clinica/medicacion-control-dolor/paciente/' . $patientB;
    [$status, $body] = $request('GET', $doctorPathA, $doctorTokenA);
    $doctorA = $parseP09($body);
    $result['content']['doctor_a_p09'] = $doctorA;
    $check('doctor_a_p09_get', $status === 200 && $doctorA['page_shell'] ? 'PASS' : 'FAIL');
    [$status, $body] = $request('GET', $doctorPathB, $doctorTokenB);
    $doctorB = $parseP09($body);
    $result['content']['doctor_b_p09'] = $doctorB;
    $check('doctor_b_p09_get', $status === 200 && $doctorB['page_shell'] ? 'PASS' : 'FAIL');

    $doctorContentA = '/buscar/contenido-preguntas-modulo-7/' . $patientA . '/Doctor/1';
    $doctorContentB = '/buscar/contenido-preguntas-modulo-7/' . $patientB . '/Doctor/1';
    [$status, $body] = $request('GET', $doctorContentA, $doctorTokenA);
    $doctorContentResultA = $parseP09($body);
    $result['content']['doctor_a_m07_content'] = $doctorContentResultA;
    $check('doctor_a_m07_content_get', $status === 200 && $doctorContentResultA['read_only'] && $doctorContentResultA['has_question'] && in_array(1, $doctorContentResultA['theme_ids'], true) ? 'PASS' : 'FAIL');
    [$status, $body] = $request('GET', $doctorContentB, $doctorTokenB);
    $doctorContentResultB = $parseP09($body);
    $result['content']['doctor_b_m07_content'] = $doctorContentResultB;
    $check('doctor_b_m07_content_get', $status === 200 && $doctorContentResultB['read_only'] && $doctorContentResultB['has_question'] && in_array(1, $doctorContentResultB['theme_ids'], true) ? 'PASS' : 'FAIL');

    [$status, $body] = $request('GET', $p09PathA, $tokenB);
    $check('cross_clinic_patient_get_rejected', $status === 403 && trim($body) === 'No autorizado.' ? 'PASS' : 'FAIL');
    [$status, $body] = $request('GET', $doctorPathA, $doctorTokenB);
    $check('cross_clinic_doctor_get_rejected', $status === 403 && trim($body) === 'No autorizado.' ? 'PASS' : 'FAIL');

    $unknownCovered = $pageA['unknown'] || $pageB['unknown']
        || $contentPatientA['canonical_status'] || $contentPatientB['canonical_status'];
    $check('unknown_states_coverage', $unknownCovered ? 'PASS' : 'NOT_COVERED', $unknownCovered ? '' : 'La base sintética no expone un estado desconocido verificable en HTTP.');
    $otherCovered = $pageA['other'] || $pageB['other']
        || $contentPatientA['non_catalog'] || $contentPatientB['non_catalog']
        || $doctorContentResultA['non_catalog'] || $doctorContentResultB['non_catalog'];
    $check('non_catalog_medication_coverage', $otherCovered ? 'PASS' : 'NOT_COVERED', $otherCovered ? '' : 'La base sintética no contiene un medicamento no catalogado visible en estas lecturas.');
    $unchanged = $clinicalState() === $before;
    $result['clinical_state_unchanged'] = $unchanged;
    $result['clinical_write_detected'] = !$unchanged;
    $check('get_requests_no_clinical_write', $unchanged ? 'PASS' : 'FAIL');
    $result['status'] = 'PASS';
    $result['ok'] = true;
    foreach ($result['checks'] as $checkResult) {
        if (($checkResult['status'] ?? '') !== 'PASS') {
            $result['ok'] = false;
            $result['status'] = 'BLOCKED';
            break;
        }
    }
} catch (Throwable $exception) {
    $fail($exception->getMessage());
}

$finalize();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
