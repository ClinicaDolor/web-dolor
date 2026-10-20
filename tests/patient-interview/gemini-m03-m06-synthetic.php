<?php
declare(strict_types=1);

use App\Services\ConversationalInterviewService;
use App\Services\ConversationalProvider;

$root = dirname(__DIR__, 2);
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$name, $value] = explode('=', $line, 2);
    $env[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
}
$processEnv = getenv();
if (!is_array($processEnv)) $processEnv = [];
$processEnv = array_merge($processEnv, $env, [
    'DB_DATABASE' => 'clinica_dolor_browser_20261012',
    'P04_TEST_DATABASE' => 'clinica_dolor_browser_20261012',
    'CLINICAL_AI_SYNTHETIC_MODE' => '1',
    'CLINICAL_AI_EXTERNAL_ENABLED' => '1',
]);
foreach ($processEnv as $name => $value) {
    if (is_scalar($value)) {
        putenv($name . '=' . (string)$value);
        $_ENV[$name] = (string)$value;
        $_SERVER[$name] = (string)$value;
    }
}

require $root . '/vendor/autoload.php';

final class Fase67SyntheticProvider implements ConversationalProvider
{
    public array $calls = [];

    public function interpretConversation(array $input): array
    {
        $module = (string)($input['module'] ?? '');
        $utterance = trim((string)($input['utterance'] ?? ''));
        $this->calls[] = $module;
        $blank = ['structured_value' => '', 'interpretation' => '', 'clarification' => 'Falta información obligatoria.', 'relative' => '', 'diabetes_type' => '', 'explanation' => ''];

        if ($module === 'M03') {
            if (preg_match('/no recuerdo|no sé|no se/iu', $utterance) === 1) $blank['clarification'] = 'Necesito aclarar el dato pendiente; no inventaré una fecha.';
            elseif (preg_match('/\bno\b|nunca/iu', $utterance) === 1) $blank['structured_value'] = 'No';
            elseif (preg_match('/\bs[ií]\b|fumo/iu', $utterance) === 1) $blank['structured_value'] = 'Si';
            else $blank['clarification'] = 'Necesito aclarar la situación actual antes de continuar.';
            $blank['interpretation'] = $blank['structured_value'];
            return $blank;
        }

        $context = is_array($input['context'] ?? null) ? $input['context'] : [];
        $fields = [
            'descripcion' => (string)($context['descripcion'] ?? ''),
            'medicamento' => (string)($context['medicamento'] ?? ''),
            'tiempo_uso' => (string)($context['tiempo_uso'] ?? ''),
            'dosis' => (string)($context['dosis'] ?? ''),
            'medico' => (string)($context['medico'] ?? ''),
        ];
        if (preg_match('/gabapentina.*dolor/iu', $utterance) === 1) {
            $fields['medicamento'] = 'gabapentina';
            $fields['descripcion'] = 'dolor';
        }
        if (preg_match('/300\s*mg/iu', $utterance) === 1) $fields['dosis'] = '300 mg por la noche';
        if (preg_match('/tres meses/iu', $utterance) === 1) $fields['tiempo_uso'] = 'tres meses';
        if (preg_match('/doctor de la clínica/iu', $utterance) === 1) $fields['medico'] = 'doctor de la clínica';
        if (preg_match('/no recuerdo la dosis|pregunta general/iu', $utterance) === 1) $fields['dosis'] = '';
        $complete = count(array_filter($fields, static fn(string $value): bool => trim($value) !== '')) === 5;
        return [
            'kind' => $complete ? 'm06_proposal' : 'clarification',
            'structured_value' => '', 'interpretation' => $complete ? 'Cinco campos expresados por el paciente.' : '',
            'clarification' => $complete ? '' : 'Indica los campos M06 que aún faltan; no se inventarán datos.',
            'relative' => '', 'diabetes_type' => '', 'explanation' => '', 'fields' => $fields,
        ];
    }
}

$database = 'clinica_dolor_browser_20261012';
$patient = 9302;
$pdo = null;
$result = [];
$hash = static function (PDO $db, string $table, int $id): string {
    $allowed = ['pac_respuestas_paciente_modulo_3', 'pac_medicacion_actual_modulo_6'];
    if (!in_array($table, $allowed, true)) throw new RuntimeException('TABLE_NOT_ALLOWED');
    $stmt = $db->prepare('SELECT * FROM ' . $table . ' WHERE id_paciente=:id ORDER BY 1');
    $stmt->execute([':id' => $id]);
    return hash('sha256', serialize($stmt->fetchAll(PDO::FETCH_ASSOC)));
};

try {
    $pdo = new PDO(
        'mysql:host=' . $_ENV['DB_HOST'] . ';port=' . ($_ENV['DB_PORT'] ?? '3306') . ';dbname=' . $database . ';charset=utf8mb4',
        $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) throw new RuntimeException('DATABASE_NOT_ALLOWED');
    $patientStmt = $pdo->prepare('SELECT id,id_clinica FROM pc_paciente WHERE id=:id LIMIT 1');
    $patientStmt->execute([':id' => $patient]);
    if (!is_array($patientStmt->fetch())) throw new RuntimeException('SYNTHETIC_PATIENT_NOT_FOUND');

    $before = [
        'm03' => $hash($pdo, 'pac_respuestas_paciente_modulo_3', $patient),
        'm06' => $hash($pdo, 'pac_medicacion_actual_modulo_6', $patient),
    ];
    $questionId = (int)$pdo->query("SELECT id FROM pac_preguntas_modulo_3 WHERE id_tema=1 AND tipo='select' ORDER BY id LIMIT 1")->fetchColumn();
    if ($questionId < 1) throw new RuntimeException('M03_SELECT_QUESTION_NOT_FOUND');

    $provider = new Fase67SyntheticProvider();
    $service = new ConversationalInterviewService(null, $provider);
    $negative = $service->interpret($patient, $questionId, 'No, nunca he fumado');
    $positive = $service->interpret($patient, $questionId, 'Sí, fumo');
    $historical = $service->interpret($patient, $questionId, 'Fumaba antes, pero ya lo dejé');
    $uncertain = $service->interpret($patient, $questionId, 'No recuerdo exactamente cuándo empecé');

    $m06Complete = $service->interpretM06('Tomo un medicamento llamado gabapentina por dolor nervioso. Llevo tres meses, tomo 300 mg por la noche y me lo indicó el doctor de la clínica');
    $m06First = $service->interpretM06('Tomo gabapentina para el dolor');
    $m06Second = $service->interpretM06('300 mg por la noche durante tres meses', $m06First['context'] ?? []);
    $m06Third = $service->interpretM06('Me lo indicó el doctor de la clínica', $m06Second['context'] ?? []);
    $m06UnknownDose = $service->interpretM06('No recuerdo la dosis');
    $m06Irrelevant = $service->interpretM06('¿Qué medicamento es mejor para mí?');

    $after = [
        'm03' => $hash($pdo, 'pac_respuestas_paciente_modulo_3', $patient),
        'm06' => $hash($pdo, 'pac_medicacion_actual_modulo_6', $patient),
    ];
    $result = [
        'database' => $database,
        'patient_synthetic' => $patient,
        'm03_negative_interpretation' => ($negative['mode'] ?? '') === 'gemini' && ($negative['structured_value'] ?? null) === 'No',
        'm03_positive_dependencies' => ($positive['mode'] ?? '') === 'gemini' && ($positive['structured_value'] ?? null) === 'Si',
        'm03_past_vs_current_smoking' => ($historical['mode'] ?? '') === 'gemini' && ($historical['needs_clarification'] ?? false) === true,
        'm03_uncertainty' => ($uncertain['mode'] ?? '') === 'gemini' && ($uncertain['needs_clarification'] ?? false) === true,
        'm03_zero_unconfirmed_writes' => $before['m03'] === $after['m03'],
        'm06_five_field_extraction' => ($m06Complete['mode'] ?? '') === 'gemini' && ($m06Complete['kind'] ?? '') === 'm06_proposal' && count(array_filter($m06Complete['fields'] ?? [], static fn(string $v): bool => trim($v) !== '')) === 5,
        'm06_incomplete_information' => ($m06First['mode'] ?? '') === 'gemini' && ($m06First['kind'] ?? '') === 'm06_proposal' && count($m06First['missing'] ?? []) >= 3,
        'm06_multiturn_context' => ($m06Third['mode'] ?? '') === 'gemini' && ($m06Third['kind'] ?? '') === 'm06_proposal' && count(array_filter($m06Third['fields'] ?? [], static fn(string $v): bool => trim($v) !== '')) === 5,
        'm06_unknown_dose' => ($m06UnknownDose['kind'] ?? '') === 'clarification' && in_array('dosis', $m06UnknownDose['missing'] ?? [], true),
        'm06_irrelevant_question' => ($m06Irrelevant['kind'] ?? '') === 'clarification' && !($m06Irrelevant['requires_confirmation'] ?? false),
        'm06_zero_unconfirmed_writes' => $before['m06'] === $after['m06'],
        'provider_calls' => count($provider->calls),
        'fixture_restored' => $before === $after,
        'real_gemini_calls' => 0,
    ];
} catch (Throwable $error) {
    $result = [
        'database' => $database,
        'real_gemini_calls' => 0,
        'error_code' => preg_replace('/[^A-Z0-9_]+/', '_', strtoupper($error->getMessage() ?: 'SYNTHETIC_TEST_FAILED')),
        'status' => 'PARTIAL',
    ];
}

$result['status'] = isset($result['error_code']) ? 'PARTIAL' : (in_array(false, $result, true) ? 'PARTIAL' : 'PASS');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($result['status'] === 'PASS' ? 0 : 1);
