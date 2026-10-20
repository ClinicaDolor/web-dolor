<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\ConversationalM04Adapter;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$adapter = new ConversationalM04Adapter(null);
$cases = [
    'year_only' => [
        'input' => 'Me operaron de la rodilla en 2019',
        'kind' => 'm04_proposal', 'surgery' => 'la rodilla', 'date' => null,
        'observation' => 'Año referido: 2019',
    ],
    'month_year' => [
        'input' => 'Me operaron de la rodilla en marzo de 2019',
        'kind' => 'm04_proposal', 'surgery' => 'la rodilla', 'date' => null,
        'observation' => 'Mes y año referidos: marzo de 2019',
    ],
    'exact_date' => [
        'input' => 'Me operaron de la rodilla el 15 de marzo de 2019',
        'kind' => 'm04_proposal', 'surgery' => 'la rodilla', 'date' => '2019-03-15',
        'observation' => null,
    ],
    'relative_time' => [
        'input' => 'Me operaron de la rodilla hace aproximadamente tres años',
        'kind' => 'm04_proposal', 'surgery' => 'la rodilla', 'date' => null,
        'observation' => 'Tiempo referido: aproximadamente tres años',
    ],
    'unknown_date' => [
        'input' => 'Me operaron de la rodilla pero no recuerdo cuándo',
        'kind' => 'clarification', 'surgery' => 'la rodilla', 'date' => null,
        'observation' => null,
    ],
    'year_without_surgery' => [
        'input' => 'Me operaron en 2019',
        'kind' => 'clarification', 'surgery' => null, 'date' => null,
        'observation' => null,
    ],
    'invalid_date' => [
        'input' => 'Me operaron de la rodilla el 31 de febrero de 2019',
        'kind' => 'clarification', 'surgery' => 'la rodilla', 'date' => null,
        'observation' => null,
    ],
];

foreach ($cases as $name => $expected) {
    $actual = $adapter->interpret($expected['input']);
    $assert(($actual['kind'] ?? null) === $expected['kind'], $name . ': kind');
    $assert(($actual['surgery'] ?? null) === $expected['surgery'], $name . ': surgery');
    $assert(($actual['date'] ?? null) === $expected['date'], $name . ': date');
    if ($expected['observation'] !== null) {
        $assert(($actual['observations'] ?? null) === $expected['observation'], $name . ': observation');
    } else {
        $assert(($actual['observations'] ?? null) === null, $name . ': unexpected observation');
    }
    $assert(($actual['date'] ?? null) !== '2019-01-01', $name . ': fabricated partial date');
}

$root = dirname(__DIR__, 2);
$adapterSource = file_get_contents($root . '/app/Services/ConversationalM04Adapter.php');
$modelSource = file_get_contents($root . '/app/Models/AntecedentesQuirurgicos.php');
$serviceSource = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');
$viewSource = file_get_contents($root . '/app/views/paciente/entrevista.php');
$assert(str_contains($adapterSource, 'new \\App\\Models\\AntecedentesQuirurgicos($db)'), 'M04 model connection injection');
$assert(str_contains($modelSource, 'function __construct(?\\PDO $db = null)'), 'M04 model accepts PDO');
$assert(str_contains($adapterSource, '$db->beginTransaction()') && str_contains($adapterSource, '$db->rollBack()'), 'M04 rollback boundary');
$assert(str_contains($serviceSource, "'M04:record:new'") && str_contains($serviceSource, 'm04QuestionVoice'), 'M04 canonical question and voice contract');
$assert(str_contains($viewSource, 'data-m04-stable-id') && str_contains($viewSource, 'canonicalStageTarget'), 'M04 view and coordinator share the canonical target');
$assert(str_contains($viewSource, 'Asistente · Antecedentes quirúrgicos'), 'M04 visible assistant heading');

echo "PASS M04 temporal precision and transaction contract\n";
echo "M04_TEMPORAL_PRECISION_STATIC: PASS\n";
