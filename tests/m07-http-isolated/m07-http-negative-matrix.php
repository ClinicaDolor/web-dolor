<?php

declare(strict_types=1);

/**
 * M07/P09 HTTP matrix without positive clinical writes.
 *
 * This harness is invoked only by the Medium-integrity coordinator after the
 * isolated Apache guard, P04 and the durable M07 baseline have passed. It
 * deliberately exercises only reads and requests that must be rejected before
 * clinical persistence. It never creates credentials and never restores data.
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
$patientA = (int)($options['patient-a'] ?? 9301);
$patientB = (int)($options['patient-b'] ?? 9302);
$doctorToken = (string)(getenv('M07_SYNTHETIC_DOCTOR_TOKEN') ?: '');
$caseInventory = [
    'patient_access_page' => ['operations' => ['GET /historia-clinica/acceso'], 'expected' => 'HTTP 200'],
    'unauthenticated_get_rejected' => ['operations' => ['GET /historia-clinica/entrevista'], 'expected' => 'HTTP 302 auth redirect'],
    'authentication_a' => ['operations' => ['POST /historia-clinica/login (A)'], 'expected' => 'resultado=true and token'],
    'authentication_b' => ['operations' => ['POST /historia-clinica/login (B)'], 'expected' => 'resultado=true and token'],
    'authenticated_m07_gets' => ['operations' => ['GET /historia-clinica/entrevista (A)', 'GET /historia-clinica/entrevista (B)'], 'expected' => 'HTTP 200, patient and M07 markers for A and B'],
    'get_requests_no_clinical_write' => ['operations' => ['GET /historia-clinica/entrevista (A/B)'], 'expected' => 'clinical state unchanged'],
    'cross_clinic_patient_get_rejected' => ['operations' => ['GET /historia-clinica/medicacion-control-dolor/paciente/{A} as B'], 'expected' => 'HTTP 403 with access rejection'],
    'cross_get_no_clinical_write' => ['operations' => ['GET /historia-clinica/medicacion-control-dolor/paciente/{A} as B'], 'expected' => 'clinical state unchanged'],
    'csrf_missing_rejected_without_write' => ['operations' => ['POST /historia-clinica/entrevista/capturar-m07 without CSRF'], 'expected' => 'HTTP 403 with CSRF rejection'],
    'csrf_invalid_rejected_without_write' => ['operations' => ['POST /historia-clinica/entrevista/capturar-m07 invalid CSRF'], 'expected' => 'HTTP 403 with CSRF rejection'],
    'cross_clinic_post_rejected_without_write' => ['operations' => ['POST /historia-clinica/entrevista/capturar-m07 as B for A'], 'expected' => 'HTTP 403 with access rejection'],
    'm07_finalization_rejected_without_write' => ['operations' => ['POST /historia-clinica/paciente/finalizar-modulo-paciente M07'], 'expected' => 'HTTP 409 with M07_EXTENDED_DATA_REQUIRES_UPDATED_FORM'],
    'doctor_idPregunta_legacy_rejection' => ['operations' => ['GET Doctor page', 'GET Doctor M07 questions', 'POST Doctor legacy edit'], 'expected' => 'HTTP 409 with M07_EXTENDED_DATA_REQUIRES_UPDATED_FORM'],
];
$result = [
    'ok' => false,
    'database' => $database,
    'read_only_clinical_matrix' => true,
    'checks' => [],
    'expected_cases' => array_keys($caseInventory),
    'expected_http_operations' => 14,
    'case_inventory' => $caseInventory,
    'http_operations' => [],
    'cases_passed' => [],
    'cases_failed' => [],
    'cases_not_reached' => array_keys($caseInventory),
    'unexpected_cases' => [],
    'case_inventory_reconciled' => false,
    'clinical_write_detected' => false,
    'clinical_state_unchanged' => 'NOT_REACHED',
];
$doctorDiagnostics = [
    'session' => $doctorToken === '' ? 'BLOCKED' : 'AVAILABLE',
    'page_status' => 'NOT_REACHED',
    'questions_status' => 'NOT_REACHED',
    'question_id_found' => false,
    'theme_id_source' => 'REQUEST_ROUTE',
    'csrf_found' => false,
    'case_ready' => false,
    'post_attempted' => false,
    'post_status' => 'NOT_REACHED',
    'post_expected_status' => '403_OR_409',
    'post_assertion' => 'NOT_REACHED',
    'post_contract_code' => 'NOT_REACHED',
];

$clinicalState = null;
$before = null;

$finalizeCaseReport = static function () use (&$result, $caseInventory): void {
    $expected = array_keys($caseInventory);
    $passed = [];
    $failed = [];
    $notReached = [];
    $unexpected = [];
    foreach ($result['checks'] as $name => $checkResult) {
        if (!in_array($name, $expected, true)) {
            $unexpected[] = $name;
            continue;
        }
        $status = (string)($checkResult['status'] ?? 'NOT_REACHED');
        if ($status === 'PASS') {
            $passed[] = $name;
        } elseif ($status === 'FAIL') {
            $failed[] = $name;
        } else {
            $notReached[] = $name;
        }
    }
    foreach ($expected as $name) {
        if (!array_key_exists($name, $result['checks'])) {
            $notReached[] = $name;
        }
    }
    $result['cases_passed'] = array_values(array_unique($passed));
    $result['cases_failed'] = array_values(array_unique($failed));
    $result['cases_not_reached'] = array_values(array_unique($notReached));
    $result['unexpected_cases'] = array_values(array_unique($unexpected));
    $result['case_inventory_reconciled'] = count($result['unexpected_cases']) === 0;
};

$check = static function (array &$target, string $name, string $status, string $detail = ''): void {
    if (!in_array($status, ['PASS', 'FAIL', 'NOT_REACHED'], true)) {
        throw new InvalidArgumentException('Estado de comprobación no permitido.');
    }
    $target['checks'][$name] = ['status' => $status];
    if ($detail !== '') {
        $target['checks'][$name]['detail'] = $detail;
    }
};

$fail = static function (string $message) use (&$result, &$finalizeCaseReport, &$clinicalState, &$before): never {
    $result['error'] = $message;
    if (is_callable($clinicalState) && $before !== null) {
        try {
            $result['clinical_state_unchanged'] = $clinicalState() === $before;
            $result['clinical_write_detected'] = $result['clinical_state_unchanged'] !== true;
        } catch (Throwable) {
            $result['clinical_state_unchanged'] = 'NOT_VERIFIED';
        }
    }
    $finalizeCaseReport();
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
    $fail('Los pacientes deben ser exactamente los dos fixtures sintéticos autorizados.');
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
    if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
        $fail('La comprobación PDO no coincide con la base sintética.');
    }
} catch (Throwable) {
    $fail('No fue posible abrir la comprobación PDO local.');
}

$patientsStatement = $pdo->prepare(
    'SELECT id, id_clinica, nombres, apellido_paterno, apellido_materno
     FROM pc_paciente WHERE id IN (?, ?) ORDER BY id'
);
$patientsStatement->execute([$patientA, $patientB]);
$patients = $patientsStatement->fetchAll(PDO::FETCH_ASSOC);
$synthetic = static function (array $patient): bool {
    $name = strtolower(implode(' ', [
        (string)($patient['nombres'] ?? ''),
        (string)($patient['apellido_paterno'] ?? ''),
        (string)($patient['apellido_materno'] ?? ''),
    ]));
    return str_contains($name, 'sint') || str_contains($name, 'test') || str_contains($name, 'prueba');
};
$clinicIds = array_values(array_unique(array_map(
    static fn(array $patient): int => (int)$patient['id_clinica'],
    $patients
)));
if (count($patients) !== 2 || count(array_filter($patients, $synthetic)) !== 2 || count($clinicIds) !== 2) {
    $fail('Los expedientes sintéticos no cumplen separación por clínica.');
}

$rowsForTable = static function (PDO $connection, string $table, string $where, array $parameters): array {
    $statement = $connection->prepare("SELECT * FROM {$table} WHERE {$where} ORDER BY id");
    $statement->execute($parameters);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
};
$clinicalState = static function () use ($pdo, $patientA, $patientB, $clinicIds, $rowsForTable): string {
    $definitions = [
        'pac_respuestas_paciente_modulo_7' => ['id_paciente IN (?, ?)', [$patientA, $patientB]],
        'pac_m07_medicamentos_no_catalogados' => [
            '(id_paciente = ? AND id_clinica = ?) OR (id_paciente = ? AND id_clinica = ?)',
            [$patientA, $clinicIds[0], $patientB, $clinicIds[1]],
        ],
        'pac_m07_operaciones' => [
            '(id_paciente = ? AND id_clinica = ?) OR (id_paciente = ? AND id_clinica = ?)',
            [$patientA, $clinicIds[0], $patientB, $clinicIds[1]],
        ],
        'pac_m07_revision_clinica' => [
            '(id_paciente = ? AND id_clinica = ?) OR (id_paciente = ? AND id_clinica = ?)',
            [$patientA, $clinicIds[0], $patientB, $clinicIds[1]],
        ],
    ];
    $rows = [];
    foreach ($definitions as $table => [$where, $parameters]) {
        $rows[$table] = $rowsForTable($pdo, $table, $where, $parameters);
    }
    return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
};

$safeJsonCode = static function (array $json): string {
    $code = (string)($json['code'] ?? '');
    return preg_match('/^[A-Z0-9_]{1,80}$/', $code) === 1 ? $code : '';
};
$classifyResponse = static function (int $status, string $body, array $json, bool $locationPresent) use ($safeJsonCode): string {
    if ($status === 0) {
        return 'NO_HTTP_RESPONSE';
    }
    if ($status === 302 && $locationPresent) {
        return 'AUTH_REDIRECT';
    }
    $code = $safeJsonCode($json);
    if ($code === 'M07_EXTENDED_DATA_REQUIRES_UPDATED_FORM') {
        return 'M07_LEGACY_REJECTION';
    }
    $message = strtolower(trim((string)($json['mensaje'] ?? '')));
    if ($status === 403 && ($message === 'token de seguridad inválido o ausente.' || str_contains($message, 'token de seguridad'))) {
        return 'CSRF_REJECTION';
    }
    if ($status === 403 && trim($body) === 'No autorizado.') {
        return 'ACCESS_REJECTION';
    }
    if ($status === 200 && (($json['resultado'] ?? null) === true || trim($body) === 'PASS')) {
        return 'SUCCESS';
    }
    if (trim($body) === '') {
        return 'EMPTY_RESPONSE';
    }
    if ($code !== '') {
        return 'JSON_CONTRACT_' . $code;
    }
    if (str_starts_with(ltrim($body), '<')) {
        return 'HTML_RESPONSE';
    }
    return 'TEXT_RESPONSE';
};
$request = static function (string $method, string $path, string $token = '', ?array $payload = null, string $csrf = '') use ($baseUrl, &$result, $classifyResponse, $safeJsonCode): array {
    $headers = '';
    if ($token !== '') {
        $headers .= 'Cookie: CLINICAJWT=' . $token . "\r\n";
    }
    if ($csrf !== '') {
        $headers .= 'X-M03-CSRF: ' . $csrf . "\r\n";
    }
    if ($payload !== null) {
        $headers .= "Content-Type: application/json\r\n";
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 12,
        'header' => $headers,
        'content' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]]);
    $http_response_header = [];
    $body = @file_get_contents($baseUrl . $path, false, $context);
    $status = 0;
    $locationPresent = false;
    foreach ($http_response_header as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) {
            $status = (int)$match[1];
        }
        if (stripos($header, 'Location:') === 0) {
            $locationPresent = true;
        }
    }
    $decoded = json_decode((string)$body, true);
    $json = is_array($decoded) ? $decoded : [];
    $result['http_operations'][] = [
        'sequence' => count($result['http_operations']) + 1,
        'method' => $method,
        'path' => $path,
        'http_status' => $status,
        'response_classification' => $classifyResponse($status, (string)$body, $json, $locationPresent),
        'contract_code' => $safeJsonCode($json),
    ];
    return [$status, (string)$body, $json];
};

$parseInterview = static function (string $body): array {
    preg_match('/<div id="main"[^>]*data-patient="([0-9]+)"[^>]*data-csrf="([^"]+)"/s', $body, $main);
    preg_match('/data-module="M07"[^>]*data-m07-version="([^"]*)"[^>]*data-m07-write-enabled="([01])"/s', $body, $m07);
    preg_match('/<div class="m07-item"[^>]*data-question-id="([0-9]+)"[^>]*data-theme-id="([0-9]+)"/s', $body, $item);
    preg_match_all('/<section[^>]*data-m07-category\b/s', $body, $categories);
    preg_match_all('/<div class="m07-item"[^>]*data-question-id="[0-9]+"/s', $body, $items);
    return [
        'patient' => (string)($main[1] ?? ''),
        'csrf' => (string)($main[2] ?? ''),
        'version' => (string)($m07[1] ?? ''),
        'write_enabled' => ($m07[2] ?? '0') === '1',
        'question_id' => (int)($item[1] ?? 0),
        'theme_id' => (int)($item[2] ?? 0),
        'categories' => count($categories[0] ?? []),
        'items' => count($items[0] ?? []),
        'has_finalization_control' => str_contains($body, 'data-m07-finalize'),
    ];
};

$login = static function (string $pin) use ($request): string {
    [$status, , $json] = $request('POST', '/historia-clinica/login', '', ['pin' => $pin]);
    if ($status !== 200 || ($json['resultado'] ?? false) !== true || !is_string($json['token'] ?? null) || $json['token'] === '') {
        return '';
    }
    return (string)$json['token'];
};

$before = $clinicalState();

try {
    $pinA = (string)(getenv('P04_TEST_PIN_A') ?: '');
    $pinB = (string)(getenv('P04_TEST_PIN_B') ?: '');
    if ($pinA === '' || $pinB === '' || $pinA === $pinB) {
        $fail('Faltan credenciales sintéticas independientes en memoria del proceso.');
    }

    [$accessStatus] = $request('GET', '/historia-clinica/acceso');
    $check($result, 'patient_access_page', $accessStatus === 200 ? 'PASS' : 'FAIL');

    [$anonymousStatus] = $request('GET', '/historia-clinica/entrevista');
    $anonymousOk = in_array($anonymousStatus, [302, 401, 403], true);
    $check($result, 'unauthenticated_get_rejected', $anonymousOk ? 'PASS' : 'FAIL');

    $tokenA = $login($pinA);
    $tokenB = $login($pinB);
    $check($result, 'authentication_a', $tokenA !== '' ? 'PASS' : 'FAIL');
    $check($result, 'authentication_b', $tokenB !== '' ? 'PASS' : 'FAIL');
    if ($tokenA === '' || $tokenB === '') {
        $fail('Las dos sesiones sintéticas no pudieron autenticarse.');
    }

    [$pageAStatus, $pageABody] = $request('GET', '/historia-clinica/entrevista', $tokenA);
    [$pageBStatus, $pageBBody] = $request('GET', '/historia-clinica/entrevista', $tokenB);
    $pageA = $parseInterview($pageABody);
    $pageB = $parseInterview($pageBBody);
    $pageOk = $pageAStatus === 200 && $pageBStatus === 200
        && $pageA['patient'] === (string)$patientA && $pageB['patient'] === (string)$patientB
        && $pageA['categories'] === 6 && $pageA['items'] === 46
        && $pageB['categories'] === 6 && $pageB['items'] === 46
        && !$pageA['has_finalization_control'] && !$pageB['has_finalization_control']
        && $pageA['csrf'] !== '' && $pageB['csrf'] !== ''
        && $pageA['version'] !== '' && $pageB['version'] !== ''
        && !$pageA['write_enabled'] && !$pageB['write_enabled'];
    $check($result, 'authenticated_m07_gets', $pageOk ? 'PASS' : 'FAIL');
    $check($result, 'get_requests_no_clinical_write', $clinicalState() === $before ? 'PASS' : 'FAIL');

    [$crossGetStatus, $crossGetBody, $crossGetJson] = $request('GET', '/historia-clinica/medicacion-control-dolor/paciente/' . $patientA, $tokenB);
    $crossGetOk = $crossGetStatus === 403 && $classifyResponse($crossGetStatus, $crossGetBody, $crossGetJson, false) === 'ACCESS_REJECTION';
    $check($result, 'cross_clinic_patient_get_rejected', $crossGetOk ? 'PASS' : 'FAIL', 'Se exige rechazo de acceso entre clínicas, no sólo un código HTTP genérico.');
    $check($result, 'cross_get_no_clinical_write', $clinicalState() === $before ? 'PASS' : 'FAIL');

    $item = [
        'question_id' => $pageA['question_id'],
        'theme_id' => $pageA['theme_id'],
        'fields' => ['respuesta' => 'No'],
    ];
    $capturePayload = [
        'idPaciente' => $patientA,
        'expectedVersion' => $pageA['version'],
        'items' => [$item],
    ];

    [$missingCsrfStatus, $missingCsrfBody, $missingCsrfJson] = $request('POST', '/historia-clinica/entrevista/capturar-m07', $tokenA, $capturePayload);
    $missingCsrfOk = $missingCsrfStatus === 403
        && $classifyResponse($missingCsrfStatus, $missingCsrfBody, $missingCsrfJson, false) === 'CSRF_REJECTION'
        && $clinicalState() === $before;
    $check($result, 'csrf_missing_rejected_without_write', $missingCsrfOk ? 'PASS' : 'FAIL');

    [$invalidCsrfStatus, $invalidCsrfBody, $invalidCsrfJson] = $request('POST', '/historia-clinica/entrevista/capturar-m07', $tokenA, $capturePayload, str_repeat('0', 64));
    $invalidCsrfOk = $invalidCsrfStatus === 403
        && $classifyResponse($invalidCsrfStatus, $invalidCsrfBody, $invalidCsrfJson, false) === 'CSRF_REJECTION'
        && $clinicalState() === $before;
    $check($result, 'csrf_invalid_rejected_without_write', $invalidCsrfOk ? 'PASS' : 'FAIL');

    [$crossPostStatus, $crossPostBody, $crossPostJson] = $request('POST', '/historia-clinica/entrevista/capturar-m07', $tokenB, [
        'idPaciente' => $patientA,
        'expectedVersion' => $pageA['version'],
        'items' => [$item],
    ], $pageA['csrf']);
    $crossPostOk = $crossPostStatus === 403
        && $classifyResponse($crossPostStatus, $crossPostBody, $crossPostJson, false) === 'ACCESS_REJECTION'
        && $clinicalState() === $before;
    $check($result, 'cross_clinic_post_rejected_without_write', $crossPostOk ? 'PASS' : 'FAIL');

    [$finalizeStatus, , $finalizeJson] = $request('POST', '/historia-clinica/paciente/finalizar-modulo-paciente', $tokenA, [
        'idPaciente' => $patientA,
        'idModulo' => 7,
    ], $pageA['csrf']);
    $finalizeOk = $finalizeStatus === 409
        && $safeJsonCode($finalizeJson) === 'M07_EXTENDED_DATA_REQUIRES_UPDATED_FORM'
        && $clinicalState() === $before;
    $check($result, 'm07_finalization_rejected_without_write', $finalizeOk ? 'PASS' : 'FAIL');

    if ($doctorToken === '') {
        $check($result, 'doctor_idPregunta_legacy_rejection', 'NOT_REACHED', 'Requiere sesión Doctor sintética válida para esta misma instancia Apache.');
    } else {
        [$doctorPageStatus, $doctorPageBody] = $request('GET', '/clinica/medicacion-control-dolor/paciente/' . $patientA, $doctorToken);
        [$doctorQuestionsStatus, $doctorQuestionsBody] = $request('GET', '/buscar/contenido-preguntas-modulo-7/' . $patientA . '/Doctor/1', $doctorToken);
        $doctorDiagnostics['page_status'] = $doctorPageStatus;
        $doctorDiagnostics['questions_status'] = $doctorQuestionsStatus;
        preg_match('/data-id-pregunta="([0-9]+)"/s', $doctorQuestionsBody, $doctorQuestion);
        preg_match('/<div id="main"[^>]*data-m03-csrf="([^"]+)"/s', $doctorPageBody, $doctorCsrfMatch);
        $doctorQuestionId = (int)($doctorQuestion[1] ?? 0);
        // The legacy Doctor endpoint carries the authorized theme in its
        // request path (/Doctor/1); its response row only exposes the question id.
        $doctorThemeId = 1;
        $doctorCsrf = (string)($doctorCsrfMatch[1] ?? '');
        $doctorDiagnostics['question_id_found'] = $doctorQuestionId > 0;
        $doctorDiagnostics['csrf_found'] = $doctorCsrf !== '';
        $doctorCaseReady = $doctorPageStatus === 200 && $doctorQuestionsStatus === 200 && $doctorQuestionId > 0 && $doctorThemeId > 0 && $doctorCsrf !== '';
        $doctorDiagnostics['case_ready'] = $doctorCaseReady;
        if (!$doctorCaseReady) {
            $check($result, 'doctor_idPregunta_legacy_rejection', 'FAIL');
        } else {
            $doctorDiagnostics['post_attempted'] = true;
            [$doctorWriteStatus, , $doctorWriteJson] = $request('POST', '/clinica/paciente/editar-cuestionario-modulo7', $doctorToken, [
                'idPaciente' => $patientA,
                'idRespuesta' => 0,
                'idPregunta' => $doctorQuestionId,
                'idTema' => $doctorThemeId,
                // The legacy endpoint validates idTipo as the numeric P09 type.
                'idTipo' => 1,
                'detalle' => 'No',
                'idRol' => 'Doctor',
            ], $doctorCsrf);
            $doctorDiagnostics['post_status'] = $doctorWriteStatus;
            $doctorDiagnostics['post_contract_code'] = $safeJsonCode($doctorWriteJson) !== '' ? $safeJsonCode($doctorWriteJson) : 'NONE';
            $doctorOk = $doctorWriteStatus === 409
                && $safeJsonCode($doctorWriteJson) === 'M07_EXTENDED_DATA_REQUIRES_UPDATED_FORM';
            $doctorOk = $doctorOk && $clinicalState() === $before;
            $doctorDiagnostics['post_assertion'] = $doctorOk ? 'PASS' : 'FAIL';
            $check($result, 'doctor_idPregunta_legacy_rejection', $doctorOk ? 'PASS' : 'FAIL');
        }
    }

    $result['doctor_http_steps'] = $doctorDiagnostics;
    $result['clinical_state_unchanged'] = $clinicalState() === $before;
    $result['doctor_session_required'] = $doctorToken === '';
} catch (Throwable) {
    $result['error'] = 'La matriz HTTP sintética terminó con un error controlado.';
}

if (($result['clinical_state_unchanged'] ?? false) !== true) {
    $result['clinical_write_detected'] = true;
}
$finalizeCaseReport();
$result['http_operations_reconciled'] = count($result['http_operations']) === (int)$result['expected_http_operations'];
$result['ok'] = $result['clinical_state_unchanged'] === true
    && $result['case_inventory_reconciled'] === true
    && $result['http_operations_reconciled'] === true
    && count($result['cases_failed']) === 0
    && count($result['cases_not_reached']) === 0
    && count($result['cases_passed']) === count($result['expected_cases']);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
