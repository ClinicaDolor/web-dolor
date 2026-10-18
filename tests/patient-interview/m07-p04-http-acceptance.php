<?php

declare(strict_types=1);

use App\Config\Database;
use Dotenv\Dotenv;

/**
 * M07 HTTP acceptance runner.
 *
 * The caller supplies only temporary PINs through environment variables. The
 * runner reports statuses and hashes, never credentials or clinical payloads,
 * and restores the focal M07 rows before exiting.
 */
$root = dirname(__DIR__, 2);
$args = [];
foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--')) continue;
    [$key, $value] = array_pad(explode('=', $argument, 2), 2, '');
    $args[substr($key, 2)] = $value;
}

$database = $args['database'] ?? '';
$baseUrl = rtrim($args['base-url'] ?? '', '/');
$patientA = (int)($args['patient-a'] ?? 0);
$patientB = (int)($args['patient-b'] ?? 0);
$pinA = (string)(getenv('P04_TEST_PIN_A') ?: '');
$pinB = (string)(getenv('P04_TEST_PIN_B') ?: '');
$appKey = (string)(getenv('P04_TEST_APP_KEY') ?: '');
$result = [
    'ok' => false,
    'database' => $database,
    'checks' => [],
    'restoration' => 'PENDING',
];

$check = static function (array &$target, string $name, bool $passed, string $detail = ''): void {
    $target['checks'][$name] = ['status' => $passed ? 'PASS' : 'FAIL'];
    if ($detail !== '') $target['checks'][$name]['detail'] = $detail;
};

$fail = static function (string $message) use (&$result): never {
    $result['error'] = $message;
    $result['ok'] = false;
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
};

if ($database !== 'clinica_dolor_demo_20261010_final') $fail('La base no coincide con la allowlist sintética.');
if (!preg_match('/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/i', $baseUrl)) $fail('La URL no apunta a loopback.');
if ($patientA < 1 || $patientB < 1 || $patientA === $patientB) $fail('Los pacientes sintéticos no son válidos.');
if ($pinA === '' || $pinB === '' || $pinA === $pinB) $fail('Faltan credenciales sintéticas independientes.');
if (strlen($appKey) < 32) $fail('Falta la clave efímera del servidor.');

require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->load();
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;
$_ENV['APP_KEY'] = $appKey;
$_SERVER['APP_KEY'] = $appKey;

try {
    $pdo = Database::getInstance();
    $activeDatabase = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($activeDatabase !== $database) $fail('La base efectiva no coincide.');

    $patientStatement = $pdo->prepare('SELECT id, id_clinica, nombres, apellido_paterno, apellido_materno FROM pc_paciente WHERE id IN (?, ?) ORDER BY id');
    $patientStatement->execute([$patientA, $patientB]);
    $patients = $patientStatement->fetchAll(PDO::FETCH_ASSOC);
    $synthetic = static function (array $patient): bool {
        $name = strtolower(implode(' ', [
            (string)($patient['nombres'] ?? ''),
            (string)($patient['apellido_paterno'] ?? ''),
            (string)($patient['apellido_materno'] ?? ''),
        ]));
        return str_contains($name, 'sint') || str_contains($name, 'test') || str_contains($name, 'prueba');
    };
    $clinicIds = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['id_clinica'], $patients)));
    $check($result, 'database_effective', $activeDatabase === $database);
    $check($result, 'synthetic_patients_and_clinics', count($patients) === 2 && count(array_filter($patients, $synthetic)) === 2 && count($clinicIds) === 2);
    if (count($patients) !== 2 || count(array_filter($patients, $synthetic)) !== 2 || count($clinicIds) !== 2) $fail('Las identidades sintéticas no cumplen el aislamiento.');

    $fetchM07Rows = static function (PDO $connection, int $first, int $second): array {
        $statement = $connection->prepare('SELECT id, id_pregunta, id_paciente, respuesta, dosis, resultados, consumo, descripcion FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente IN (?, ?) ORDER BY id');
        $statement->execute([$first, $second]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    };
    $rowsBefore = $fetchM07Rows($pdo, $patientA, $patientB);
    $hashRows = static fn(array $rows): string => hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $rowsBeforeHash = $hashRows($rowsBefore);
    $restored = false;

    $request = static function (string $method, string $path, string $token = '', ?array $payload = null, string $csrf = '') use ($baseUrl): array {
        $headers = '';
        if ($token !== '') $headers .= "Cookie: CLINICAJWT={$token}\r\n";
        if ($csrf !== '') $headers .= "X-M03-CSRF: {$csrf}\r\n";
        if ($payload !== null) $headers .= "Content-Type: application/json\r\n";
        $context = stream_context_create(['http' => [
            'method' => $method,
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 12,
            'header' => $headers,
            'content' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]]);
        $body = @file_get_contents($baseUrl . $path, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) {
                $status = (int)$match[1];
                break;
            }
        }
        $body = (string)$body;
        $json = json_decode($body, true);
        return [$status, $body, is_array($json) ? $json : []];
    };
    $login = static function (string $pin) use ($request): array {
        [$status, $body, $json] = $request('POST', '/historia-clinica/login', '', ['pin' => $pin]);
        return [$status, $body, $json, is_string($json['token'] ?? null) ? (string)$json['token'] : ''];
    };
    $parsePage = static function (string $body): array {
        preg_match('/<div id="main"[^>]*data-patient="([0-9]+)"[^>]*data-csrf="([^"]+)"/', $body, $main);
        preg_match('/data-module="M07"[^>]*data-m07-version="([^"]*)"[^>]*data-m07-write-enabled="([01])"/', $body, $m07);
        preg_match('/<div class="m07-item"[^>]*data-question-id="([0-9]+)"[^>]*data-theme-id="([0-9]+)"/', $body, $item);
        preg_match_all('/<section[^>]*data-m07-category\b/', $body, $categories);
        preg_match_all('/<div class="m07-item"[^>]*data-question-id="[0-9]+"/', $body, $items);
        return [
            'patient' => $main[1] ?? '',
            'csrf' => $main[2] ?? '',
            'version' => $m07[1] ?? '',
            'write_enabled' => ($m07[2] ?? '0') === '1',
            'question_id' => (int)($item[1] ?? 0),
            'theme_id' => (int)($item[2] ?? 0),
            'categories' => count($categories[0] ?? []),
            'items' => count($items[0] ?? []),
            'has_finalization_control' => str_contains($body, 'data-m07-finalize'),
        ];
    };
    $snapshot = static fn() => $hashRows($fetchM07Rows($pdo, $patientA, $patientB));
    $jsonCode = static fn(array $json): string => (string)($json['code'] ?? '');

    [$loginAStatus, , $loginAJson, $tokenA] = $login($pinA);
    [$loginBStatus, , $loginBJson, $tokenB] = $login($pinB);
    $check($result, 'authentication_a', $loginAStatus === 200 && ($loginAJson['resultado'] ?? false) === true && $tokenA !== '');
    $check($result, 'authentication_b', $loginBStatus === 200 && ($loginBJson['resultado'] ?? false) === true && $tokenB !== '');
    if ($tokenA === '' || $tokenB === '') $fail('La autenticación sintética no produjo dos sesiones.');

    [$pageAStatus, $pageABody] = $request('GET', '/historia-clinica/entrevista', $tokenA);
    $pageA = $parsePage($pageABody);
    [$pageBStatus, $pageBBody] = $request('GET', '/historia-clinica/entrevista', $tokenB);
    $pageB = $parsePage($pageBBody);
    $check($result, 'm07_http_catalog', $pageAStatus === 200 && $pageA['patient'] === (string)$patientA && $pageA['categories'] === 6 && $pageA['items'] === 46 && !$pageA['has_finalization_control']);
    $check($result, 'm07_http_read_no_write', $snapshot() === $rowsBeforeHash && $pageBStatus === 200 && $pageB['patient'] === (string)$patientB);
    if ($pageA['csrf'] === '' || $pageA['version'] === '' || $pageA['question_id'] < 1 || $pageA['theme_id'] < 1 || !$pageA['write_enabled']) $fail('La página M07 no entregó estado editable sintético válido.');

    $itemNo = ['question_id' => $pageA['question_id'], 'theme_id' => $pageA['theme_id'], 'fields' => ['respuesta' => 'No']];
    [$noStatus, , $noJson] = $request('POST', '/historia-clinica/entrevista/capturar-m07', $tokenA, ['idPaciente' => $patientA, 'expectedVersion' => $pageA['version'], 'items' => [$itemNo]], $pageA['csrf']);
    $afterNoHash = $snapshot();
    $check($result, 'm07_http_write_no_explicit', $noStatus === 200 && ($noJson['resultado'] ?? false) === true && $jsonCode($noJson) === 'M07_CONFIRMED' && $afterNoHash !== $rowsBeforeHash);

    [$readbackStatus, $readbackBody] = $request('GET', '/historia-clinica/entrevista', $tokenA);
    $readbackPage = $parsePage($readbackBody);
    $readbackComplete = str_contains($readbackBody, 'data-question-id="' . $pageA['question_id'] . '"') && str_contains($readbackBody, 'data-status="complete"');
    $check($result, 'm07_http_readback', $readbackStatus === 200 && $readbackComplete);

    [$staleStatus, , $staleJson] = $request('POST', '/historia-clinica/entrevista/capturar-m07', $tokenA, ['idPaciente' => $patientA, 'expectedVersion' => $pageA['version'], 'items' => [$itemNo]], $pageA['csrf']);
    $check($result, 'm07_http_version', $staleStatus === 409 && $jsonCode($staleJson) === 'M07_VERSION_CONFLICT' && $snapshot() === $afterNoHash);

    [$duplicateStatus, , $duplicateJson] = $request('POST', '/historia-clinica/entrevista/capturar-m07', $tokenA, ['idPaciente' => $patientA, 'expectedVersion' => $readbackPage['version'], 'items' => [$itemNo, $itemNo]], $pageA['csrf']);
    $check($result, 'm07_http_idempotency_duplicate_rejection', $duplicateStatus === 409 && $jsonCode($duplicateJson) === 'M07_DUPLICATE_ITEM' && $snapshot() === $afterNoHash);

    $invalidDependency = ['question_id' => $pageA['question_id'], 'theme_id' => $pageA['theme_id'], 'fields' => ['respuesta' => 'Si', 'dosis' => '', 'resultados' => 'Funciono', 'consumo' => 'Si']];
    [$invalidStatus, , $invalidJson] = $request('POST', '/historia-clinica/entrevista/capturar-m07', $tokenA, ['idPaciente' => $patientA, 'expectedVersion' => $readbackPage['version'], 'items' => [$invalidDependency]], $pageA['csrf']);
    $check($result, 'm07_http_dependency_validation', $invalidStatus === 422 && $jsonCode($invalidJson) === 'M07_DEPENDENCIES_INCOMPLETE' && $snapshot() === $afterNoHash);

    $rollbackItem = ['question_id' => $pageA['question_id'] + 1, 'theme_id' => $pageA['theme_id'], 'fields' => ['respuesta' => 'Si', 'dosis' => str_repeat('x', 5001), 'resultados' => 'Funciono', 'consumo' => 'Si']];
    [$rollbackStatus, , $rollbackJson] = $request('POST', '/historia-clinica/entrevista/capturar-m07', $tokenA, ['idPaciente' => $patientA, 'expectedVersion' => $readbackPage['version'], 'items' => [$rollbackItem]], $pageA['csrf']);
    $check($result, 'm07_http_rollback', $rollbackStatus === 422 && $jsonCode($rollbackJson) === 'M07_ATOMIC_ROLLBACK' && $snapshot() === $afterNoHash);

    $itemYes = ['question_id' => $pageA['question_id'], 'theme_id' => $pageA['theme_id'], 'fields' => ['respuesta' => 'Si', 'dosis' => 'dosis sintética', 'resultados' => 'Funciono', 'consumo' => 'No']];
    [$yesStatus, , $yesJson] = $request('POST', '/historia-clinica/entrevista/capturar-m07', $tokenA, ['idPaciente' => $patientA, 'expectedVersion' => $readbackPage['version'], 'items' => [$itemYes]], $pageA['csrf']);
    [$yesReadStatus, $yesReadBody] = $request('GET', '/historia-clinica/entrevista', $tokenA);
    $yesRead = $parsePage($yesReadBody);
    $check($result, 'm07_http_t1_t2_t3_t4_readback', $yesStatus === 200 && ($yesJson['resultado'] ?? false) === true && $yesReadStatus === 200 && str_contains($yesReadBody, 'dosis sintética'));

    [$editNoStatus, , $editNoJson] = $request('POST', '/historia-clinica/entrevista/capturar-m07', $tokenA, ['idPaciente' => $patientA, 'expectedVersion' => $yesRead['version'], 'items' => [$itemNo + ['confirm_clear' => true]]], $pageA['csrf']);
    [$editReadStatus, $editReadBody] = $request('GET', '/historia-clinica/entrevista', $tokenA);
    $check($result, 'm07_http_edit_authorized', $editNoStatus === 200 && ($editNoJson['resultado'] ?? false) === true && $editReadStatus === 200);

    [$crossPatientStatus] = $request('POST', '/historia-clinica/entrevista/capturar-m07', $tokenB, ['idPaciente' => $patientA, 'expectedVersion' => $readbackPage['version'], 'items' => [$itemNo]], $pageA['csrf']);
    $check($result, 'm07_cross_patient_denial', in_array($crossPatientStatus, [401, 403, 404], true) && $snapshot() === $afterNoHash);
    [$crossClinicStatus] = $request('POST', '/historia-clinica/entrevista/capturar-m07', $tokenB, ['idPaciente' => $patientA, 'expectedVersion' => $readbackPage['version'], 'items' => [$itemNo]], $pageA['csrf']);
    $check($result, 'm07_cross_clinic_denial', in_array($crossClinicStatus, [401, 403, 404], true) && $snapshot() === $afterNoHash);
    [$csrfStatus] = $request('POST', '/historia-clinica/entrevista/capturar-m07', $tokenA, ['idPaciente' => $patientA, 'expectedVersion' => $readbackPage['version'], 'items' => [$itemNo]], str_repeat('0', 64));
    $check($result, 'm07_csrf_denial', $csrfStatus === 403 && $snapshot() === $afterNoHash);
    [$anonymousStatus] = $request('POST', '/historia-clinica/entrevista/capturar-m07', '', ['idPaciente' => $patientA, 'expectedVersion' => $readbackPage['version'], 'items' => [$itemNo]], $pageA['csrf']);
    $check($result, 'm07_anonymous_denial', in_array($anonymousStatus, [302, 401, 403], true) && $snapshot() === $afterNoHash);

    [$unknownStatus, , $unknownJson] = $request('POST', '/historia-clinica/entrevista/interpretar', $tokenA, ['idPaciente' => $patientA, 'idPregunta' => $pageA['question_id'], 'modulo' => 'M07', 'campo' => 'respuesta', 'utterance' => 'no recuerdo'], $pageA['csrf']);
    $unknownProposal = $unknownJson['proposal'] ?? [];
    $check($result, 'm07_unknown_remains_pending', $unknownStatus === 200 && ($unknownProposal['code'] ?? '') === 'M07_UNKNOWN_PENDING' && ($unknownProposal['can_confirm'] ?? true) === false && $snapshot() === $afterNoHash);

    [$combinedStatus, , $combinedJson] = $request('POST', '/historia-clinica/entrevista/interpretar', $tokenA, ['idPaciente' => $patientA, 'idPregunta' => $pageA['question_id'], 'modulo' => 'M07', 'campo' => 'resultados', 'utterance' => 'funcionó pero tuve efectos adversos'], $pageA['csrf']);
    $combinedProposal = $combinedJson['proposal'] ?? [];
    $check($result, 'm07_combined_outcome_pending', $combinedStatus === 200 && ($combinedProposal['code'] ?? '') === 'M07_COMBINED_OUTCOME_REVIEW' && ($combinedProposal['can_confirm'] ?? true) === false && $snapshot() === $afterNoHash);

    $otherQuestion = $pdo->query("SELECT id, id_tema FROM pac_preguntas_modulo_7 WHERE pregunta REGEXP '(^|[[:space:]])[Oo]tr[oa]s?([[:space:]]|$)' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($otherQuestion) {
        $otherItem = ['question_id' => (int)$otherQuestion['id'], 'theme_id' => (int)$otherQuestion['id_tema'], 'fields' => ['respuesta' => 'Si', 'dosis' => 'dosis sintética', 'resultados' => 'Funciono', 'consumo' => 'No']];
        [$otherStatus, , $otherJson] = $request('POST', '/historia-clinica/entrevista/capturar-m07', $tokenA, ['idPaciente' => $patientA, 'expectedVersion' => $readbackPage['version'], 'items' => [$otherItem]], $pageA['csrf']);
        $check($result, 'm07_other_blocked', $otherStatus === 422 && $jsonCode($otherJson) === 'OTHER_MEDICATION_NAME_UNREPRESENTABLE' && $snapshot() === $afterNoHash);
    } else {
        $check($result, 'm07_other_blocked', true, 'No existe pregunta Otro/Otros en el catálogo sintético; no aplicable.');
    }

    $result['ok'] = !array_filter($result['checks'], static fn(array $check): bool => $check['status'] === 'FAIL');
} catch (Throwable $exception) {
    $result['error'] = 'Error técnico controlado del arnés HTTP.';
}

try {
    $pdo->beginTransaction();
    $delete = $pdo->prepare('DELETE FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente IN (?, ?)');
    $delete->execute([$patientA, $patientB]);
    $insert = $pdo->prepare('INSERT INTO pac_respuestas_paciente_modulo_7 (id, id_pregunta, id_paciente, respuesta, dosis, resultados, consumo, descripcion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($rowsBefore as $row) {
        $insert->execute([$row['id'], $row['id_pregunta'], $row['id_paciente'], $row['respuesta'], $row['dosis'], $row['resultados'], $row['consumo'], $row['descripcion']]);
    }
    $pdo->commit();
    $restored = $hashRows($fetchM07Rows($pdo, $patientA, $patientB)) === $rowsBeforeHash;
    $result['restoration'] = $restored ? 'PASS_FOCAL' : 'FAIL';
} catch (Throwable) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $result['restoration'] = 'FAIL';
}

$result['fixture_restored'] = $result['restoration'] === 'PASS_FOCAL';
$result['ok'] = ($result['ok'] ?? false) && $result['fixture_restored'];
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
