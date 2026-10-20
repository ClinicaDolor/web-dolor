<?php
declare(strict_types=1);

use App\Config\Database;
use App\Models\AntecedentesNoPatologicosModel;
use App\Services\ConversationalInterviewService;
use App\Services\ConversationalProvider;

/**
 * Delivery 13 local-only provider double. It uses SQLite only to satisfy the
 * service's database identity guard; it never opens MySQL or the network.
 */
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$syntheticDatabase = 'clinica_dolor_browser_20261012';
foreach ([
    'DB_DATABASE' => $syntheticDatabase,
    'CLINICAL_AI_SYNTHETIC_MODE' => '1',
    'CLINICAL_AI_EXTERNAL_ENABLED' => '1',
] as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

$sqlite = new PDO('sqlite::memory:');
$sqlite->sqliteCreateFunction('DATABASE', static fn(): string => $syntheticDatabase, 0);
$databaseReflection = new ReflectionClass(Database::class);
$databaseObject = $databaseReflection->newInstanceWithoutConstructor();
$pdoProperty = $databaseReflection->getProperty('pdo');
$pdoProperty->setValue($databaseObject, $sqlite);
$instanceProperty = $databaseReflection->getProperty('instance');
$instanceProperty->setValue(null, $databaseObject);

final class Delivery13LocalModel extends AntecedentesNoPatologicosModel
{
    public function __construct() {}

    public function obtenerPreguntasEntrevista(int $idPaciente, int $idTema = 1): array
    {
        return [[
            'idPregunta' => 1,
            'idTema' => 1,
            'pregunta' => '¿Fuma cigarrillos de nicotina?',
            'tipo' => 'select',
            'idRespuesta' => null,
            'respuesta' => '',
        ]];
    }
}

final class Delivery13LocalProvider implements ConversationalProvider
{
    public array $calls = [];

    public function __construct(private string $scenario = 'valid') {}

    public function interpretConversation(array $input): array
    {
        $module = (string)($input['module'] ?? '');
        $utterance = (string)($input['utterance'] ?? '');
        $this->calls[] = $module;
        if ($this->scenario === 'timeout') throw new RuntimeException('GEMINI_TRANSPORT_CURL_28');
        if ($this->scenario === 'invalid_json') return ['structured_value' => 'No'];

        $common = [
            'kind' => 'synthetic', 'structured_value' => '', 'interpretation' => '',
            'clarification' => '', 'relative' => '', 'diabetes_type' => '', 'explanation' => '',
        ];
        if ($module === 'M02') {
            $common['structured_value'] = 'Si';
            $common['interpretation'] = 'Sí';
            $common['relative'] = $this->scenario === 'm02_hallucinated' ? 'padre' : 'madre';
            $common['diabetes_type'] = 'Tipo 2';
            return $common;
        }
        if ($module === 'M03') {
            $common['structured_value'] = $this->scenario === 'm03_hallucinated' ? 'Si' : 'No';
            $common['interpretation'] = $common['structured_value'];
            return $common;
        }

        $common['fields'] = $this->scenario === 'm06_hallucinated'
            ? [
                'descripcion' => 'morfina', 'medicamento' => 'cáncer',
                'tiempo_uso' => 'dos años', 'dosis' => '100 mg', 'medico' => 'otro médico',
            ]
            : [
                'medicamento' => 'gabapentina', 'descripcion' => 'dolor',
                'tiempo_uso' => 'tres meses', 'dosis' => '300 mg por la noche', 'medico' => 'doctora Ana',
            ];
        $common['interpretation'] = 'Propuesta sintética para validación local.';
        return $common;
    }
}

$events = [];
$trace = static function (array $event) use (&$events): void { $events[] = $event; };
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$model = new Delivery13LocalModel();
$questionId = 1;

$validProvider = new Delivery13LocalProvider();
$validService = new ConversationalInterviewService($model, $validProvider, $trace, 'delivery13-valid');
$m02 = $validService->interpretM02('Diabetes Mellitus', 'Sí, mi madre tiene diabetes tipo 2');
$m03 = $validService->interpret(9302, $questionId, 'No, nunca he fumado');
$m06 = $validService->interpretM06('Tomo gabapentina para dolor desde hace tres meses, dosis 300 mg por la noche, me lo indicó la doctora Ana');
$assert(($m02['mode'] ?? '') === 'gemini' && ($m02['kind'] ?? '') === 'm02_read_only_proposal', 'M02 válido no fue aceptado.');
$assert(($m03['mode'] ?? '') === 'gemini' && ($m03['structured_value'] ?? '') === 'No', 'M03 válido no fue aceptado.');
$assert(($m06['mode'] ?? '') === 'gemini' && ($m06['kind'] ?? '') === 'm06_proposal', 'M06 válido no fue aceptado.');

$m03Hallucinated = (new ConversationalInterviewService($model, new Delivery13LocalProvider('m03_hallucinated'), $trace, 'delivery13-m03-hallucinated'))->interpret(9302, $questionId, 'No, nunca he fumado');
$m02Hallucinated = (new ConversationalInterviewService($model, new Delivery13LocalProvider('m02_hallucinated'), $trace, 'delivery13-m02-hallucinated'))->interpretM02('Diabetes Mellitus', 'Sí, mi madre tiene diabetes tipo 2');
$m06Hallucinated = (new ConversationalInterviewService($model, new Delivery13LocalProvider('m06_hallucinated'), $trace, 'delivery13-m06-hallucinated'))->interpretM06('Tomo una pastilla para la presión, pero no recuerdo cómo se llama.');
$assert(($m03Hallucinated['mode'] ?? '') === 'deterministic' && ($m03Hallucinated['structured_value'] ?? '') === 'No', 'M03 alucinado no fue rechazado.');
$assert(($m02Hallucinated['mode'] ?? '') === 'deterministic' && ($m02Hallucinated['relative'] ?? '') === 'madre', 'M02 alucinado no fue rechazado.');
$assert(($m06Hallucinated['mode'] ?? '') === 'deterministic' && ($m06Hallucinated['kind'] ?? '') === 'clarification', 'M06 alucinado no fue rechazado.');

$timeout = (new ConversationalInterviewService($model, new Delivery13LocalProvider('timeout'), $trace, 'delivery13-timeout'))->interpretM06('Tomo gabapentina para dolor');
$invalid = (new ConversationalInterviewService($model, new Delivery13LocalProvider('invalid_json'), $trace, 'delivery13-invalid'))->interpret(9302, $questionId, 'No');
$assert(($timeout['mode'] ?? '') === 'deterministic', 'El timeout no activó fallback seguro.');
$assert(($invalid['mode'] ?? '') === 'deterministic', 'El JSON inválido no activó fallback seguro.');
$assert($validProvider->calls === ['M02', 'M03', 'M06'], 'La selección de módulos del proveedor no fue estable.');
$assert((int)count(array_filter($events, static fn(array $event): bool => ($event['stage'] ?? '') === 'fallback')) >= 4, 'No quedó trazabilidad de los fallbacks.');

echo 'GEMINI_LOCAL_DOUBLES: PASS (M02/M03/M06, hallucination rejection, timeout, invalid JSON, zero writes)' . PHP_EOL;
