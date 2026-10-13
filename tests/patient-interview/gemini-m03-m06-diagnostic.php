<?php
declare(strict_types=1);

use App\Services\ConversationalInterviewService;
use App\Services\ConversationalProvider;

/**
 * Fase 6.7R: replay local, sin red, para separar provider/JSON/mapping/UX.
 * El proveedor simulado no recibe ni imprime expedientes ni respuestas reales.
 */
$root = dirname(__DIR__, 2);
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$name, $value] = explode('=', $line, 2);
    $env[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
}
foreach (array_merge($env, [
    'DB_DATABASE' => 'clinica_dolor_browser_20261012',
    'P04_TEST_DATABASE' => 'clinica_dolor_browser_20261012',
    'CLINICAL_AI_SYNTHETIC_MODE' => '1',
    'CLINICAL_AI_EXTERNAL_ENABLED' => '1',
]) as $name => $value) {
    putenv($name . '=' . (string)$value);
    $_ENV[$name] = (string)$value;
    $_SERVER[$name] = (string)$value;
}
require $root . '/vendor/autoload.php';

final class Fase67RDiagnosticProvider implements ConversationalProvider
{
    public function __construct(private string $scenario) {}

    public function interpretConversation(array $input): array
    {
        $module = (string)($input['module'] ?? '');
        if ($this->scenario === 'http_error') throw new RuntimeException('GEMINI_HTTP_400');
        if ($this->scenario === 'transport_connection') throw new RuntimeException('GEMINI_TRANSPORT_CURL_7');
        if ($this->scenario === 'transport_timeout') throw new RuntimeException('GEMINI_TRANSPORT_CURL_28');
        if ($this->scenario === 'invalid_m03' && $module === 'M03') return ['structured_value' => 'No'];
        if ($this->scenario === 'invalid_m06' && $module === 'M06') return ['fields' => ['descripcion' => 'gabapentina']];

        $common = [
            'kind' => 'synthetic', 'structured_value' => '', 'interpretation' => '',
            'clarification' => '', 'relative' => '', 'diabetes_type' => '', 'explanation' => '',
        ];
        if ($module === 'M02') {
            $common['structured_value'] = 'Si';
            $common['interpretation'] = 'Sí';
            $common['relative'] = 'madre';
            $common['diabetes_type'] = 'Tipo 2';
            return $common;
        }
        if ($module === 'M03') {
            $common['structured_value'] = 'No';
            $common['interpretation'] = 'No';
            return $common;
        }
        $complete = $this->scenario !== 'incomplete_m06';
        $common['fields'] = [
            'descripcion' => 'gabapentina',
            'medicamento' => 'dolor',
            'tiempo_uso' => $complete ? 'tres meses' : '',
            'dosis' => $complete ? '300 mg' : '',
            'medico' => $complete ? 'médico tratante' : '',
        ];
        return $common;
    }
}

$database = 'clinica_dolor_browser_20261012';
$patient = 9302;
$events = [];
$trace = static function (array $event) use (&$events): void {
    // Sólo se retienen categorías y etapas; nunca texto clínico.
    $events[] = $event;
};
$hash = static function (PDO $db, string $table, int $id): string {
    $allowed = ['pac_respuestas_paciente_modulo_3', 'pac_medicacion_actual_modulo_6'];
    if (!in_array($table, $allowed, true)) throw new RuntimeException('TABLE_NOT_ALLOWED');
    $stmt = $db->prepare('SELECT * FROM ' . $table . ' WHERE id_paciente=:id ORDER BY 1');
    $stmt->execute([':id' => $id]);
    return hash('sha256', serialize($stmt->fetchAll(PDO::FETCH_ASSOC)));
};
$eventMatch = static function (array $events, string $module, string $stage, string $category, ?string $reason = null): bool {
    foreach ($events as $event) {
        if (($event['module'] ?? '') !== $module || ($event['stage'] ?? '') !== $stage || ($event['category'] ?? '') !== $category) continue;
        if ($reason === null || ($event['reason'] ?? '') === $reason) return true;
    }
    return false;
};

$result = ['database' => $database, 'patient_synthetic' => $patient, 'real_gemini_calls' => 0];
try {
    $pdo = new PDO(
        'mysql:host=' . $env['DB_HOST'] . ';port=' . ($env['DB_PORT'] ?? '3306') . ';dbname=' . $database . ';charset=utf8mb4',
        $env['DB_USERNAME'], $env['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) throw new RuntimeException('DATABASE_NOT_ALLOWED');
    $questionId = (int)$pdo->query("SELECT id FROM pac_preguntas_modulo_3 WHERE id_tema=1 AND tipo='select' ORDER BY id LIMIT 1")->fetchColumn();
    if ($questionId < 1) throw new RuntimeException('M03_SELECT_QUESTION_NOT_FOUND');
    $before = [
        'm03' => $hash($pdo, 'pac_respuestas_paciente_modulo_3', $patient),
        'm06' => $hash($pdo, 'pac_medicacion_actual_modulo_6', $patient),
    ];

    $m03Valid = (new ConversationalInterviewService(null, new Fase67RDiagnosticProvider('valid'), $trace, 'm03-valid'))->interpret($patient, $questionId, 'No');
    $m06Valid = (new ConversationalInterviewService(null, new Fase67RDiagnosticProvider('valid'), $trace, 'm06-valid'))->interpretM06('Tomo gabapentina para el dolor');
    $m06Incomplete = (new ConversationalInterviewService(null, new Fase67RDiagnosticProvider('incomplete_m06'), $trace, 'm06-incomplete'))->interpretM06('Tomo gabapentina para el dolor');
    $m03Invalid = (new ConversationalInterviewService(null, new Fase67RDiagnosticProvider('invalid_m03'), $trace, 'm03-invalid'))->interpret($patient, $questionId, 'No');
    $m06Invalid = (new ConversationalInterviewService(null, new Fase67RDiagnosticProvider('invalid_m06'), $trace, 'm06-invalid'))->interpretM06('Tomo gabapentina para el dolor');
    $m03Http = (new ConversationalInterviewService(null, new Fase67RDiagnosticProvider('http_error'), $trace, 'm03-http-error'))->interpret($patient, $questionId, 'No');
    $m03Transport = (new ConversationalInterviewService(null, new Fase67RDiagnosticProvider('transport_connection'), $trace, 'm03-transport'))->interpret($patient, $questionId, 'No');
    $m06Transport = (new ConversationalInterviewService(null, new Fase67RDiagnosticProvider('transport_timeout'), $trace, 'm06-transport'))->interpretM06('Tomo gabapentina para el dolor');
    $m02Valid = (new ConversationalInterviewService(null, new Fase67RDiagnosticProvider('valid'), $trace, 'm02-regression'))->interpretM02('Diabetes Mellitus', 'Sí, mi madre tiene diabetes tipo 2');

    $after = [
        'm03' => $hash($pdo, 'pac_respuestas_paciente_modulo_3', $patient),
        'm06' => $hash($pdo, 'pac_medicacion_actual_modulo_6', $patient),
    ];
    $result += [
        'm03_valid_provider_accepted' => ($m03Valid['mode'] ?? '') === 'gemini' && ($m03Valid['kind'] ?? '') === 'm03_proposal',
        'm06_valid_provider_accepted' => ($m06Valid['mode'] ?? '') === 'gemini' && ($m06Valid['kind'] ?? '') === 'm06_proposal' && count(array_filter($m06Valid['fields'] ?? [], static fn($value): bool => trim((string)$value) !== '')) === 5,
        'm06_incomplete_clarification_accepted' => ($m06Incomplete['mode'] ?? '') === 'gemini' && ($m06Incomplete['kind'] ?? '') === 'clarification' && in_array('dosis', $m06Incomplete['missing'] ?? [], true),
        'm03_invalid_output_rejected' => ($m03Invalid['mode'] ?? '') === 'deterministic',
        'm06_invalid_output_rejected' => ($m06Invalid['mode'] ?? '') === 'deterministic',
        'm03_http_error_fallback' => ($m03Http['mode'] ?? '') === 'deterministic',
        'm03_transport_connection_fallback' => ($m03Transport['mode'] ?? '') === 'deterministic' && $eventMatch($events, 'M03', 'provider_invocation', 'rejected', 'connection_error'),
        'm06_transport_timeout_fallback' => ($m06Transport['mode'] ?? '') === 'deterministic' && $eventMatch($events, 'M06', 'provider_invocation', 'rejected', 'timeout_error'),
        'm02_regression' => ($m02Valid['mode'] ?? '') === 'gemini' && ($m02Valid['kind'] ?? '') === 'm02_read_only_proposal',
        'm03_invalid_first_rejection_stage' => $eventMatch($events, 'M03', 'structured_json_validation', 'rejected', 'invalid_json'),
        'm06_invalid_first_rejection_stage' => $eventMatch($events, 'M06', 'structured_json_validation', 'rejected', 'invalid_json'),
        'm03_http_first_rejection_stage' => $eventMatch($events, 'M03', 'provider_invocation', 'rejected', 'http_error'),
        'm06_incomplete_not_provider_error' => $eventMatch($events, 'M06', 'orchestrator_acceptance', 'clarification_accepted'),
        'unconfirmed_writes' => $before === $after ? 0 : 1,
        'fixture_restored' => $before === $after,
    ];
    $result['trace_categories'] = array_values(array_unique(array_map(static fn(array $event): string => (string)$event['category'], $events)));
    $result['trace_stages'] = array_values(array_unique(array_map(static fn(array $event): string => (string)$event['stage'], $events)));
    $result['trace_event_count'] = count($events);
} catch (Throwable $error) {
    $result['error_code'] = preg_replace('/[^A-Z0-9_]+/', '_', strtoupper($error->getMessage() ?: 'DIAGNOSTIC_FAILED'));
}

$result['status'] = isset($result['error_code']) || in_array(false, $result, true) || ($result['unconfirmed_writes'] ?? 1) !== 0 ? 'PARTIAL' : 'PASS';
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($result['status'] === 'PASS' ? 0 : 1);
