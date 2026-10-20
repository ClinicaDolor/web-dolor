<?php

declare(strict_types=1);

/**
 * M05 V1 conditional-navigation regression. Static/deterministic only: no
 * PDO, HTTP, Apache or clinical writes are used here.
 */
$root = dirname(__DIR__, 2);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

require $root . '/vendor/autoload.php';

use App\Services\ConversationalM05Adapter;
use App\Services\ConversationalInterviewService;

$catalog = [
    ['theme_id' => 42, 'tema' => 'Oxigenación', 'question_id' => 101, 'pregunta' => '¿Ha experimentado alguna sensación de falta de aire?', 'tipo' => 'select', 'respuesta' => '', 'response_id' => 0],
    ['theme_id' => 42, 'tema' => 'Oxigenación', 'question_id' => 102, 'pregunta' => '¿Desde cuándo ha experimentado esta sensación?', 'tipo' => 'input', 'respuesta' => '', 'response_id' => 0],
    ['theme_id' => 42, 'tema' => 'Oxigenación', 'question_id' => 103, 'pregunta' => '¿Con qué frecuencia ocurre?', 'tipo' => 'input', 'respuesta' => '', 'response_id' => 0],
    ['theme_id' => 43, 'tema' => 'Otro tema', 'question_id' => 201, 'pregunta' => 'Pregunta independiente', 'tipo' => 'select', 'respuesta' => '', 'response_id' => 0],
];

$negative = $catalog;
$negative[0]['respuesta'] = 'No';
$negativeState = ConversationalM05Adapter::resolveV1Eligibility($negative);
$byId = static function (array $rows, int $id): array {
    foreach ($rows as $row) if ((int)($row['question_id'] ?? 0) === $id) return $row;
    return [];
};
$negativeFollowup = $byId($negativeState, 102);
$negativeSecondFollowup = $byId($negativeState, 103);
$negativeNext = null;
foreach ($negativeState as $row) {
    if (($row['eligible'] ?? false) === true && trim((string)($row['respuesta'] ?? '')) === '') {
        $negativeNext = (int)$row['question_id'];
        break;
    }
}
$assert(($negativeFollowup['eligible'] ?? true) === false, 'Negative parent left temporal follow-up eligible');
$assert(($negativeFollowup['dependency_state'] ?? '') === 'skipped_by_negative', 'Negative dependency state is not explicit');
$assert(($negativeSecondFollowup['eligible'] ?? true) === false, 'Negative parent left a second dependent eligible');
$assert($negativeNext === 201, 'Negative parent did not advance to the next independent question');

$affirmative = $catalog;
$affirmative[0]['respuesta'] = 'Si';
$affirmativeState = ConversationalM05Adapter::resolveV1Eligibility($affirmative);
$affirmativeFollowup = $byId($affirmativeState, 102);
$assert(($affirmativeFollowup['eligible'] ?? false) === true, 'Affirmative parent did not enable temporal follow-up');
$assert(($affirmativeFollowup['dependency_state'] ?? '') === 'enabled_by_affirmative', 'Affirmative dependency state is not explicit');

$historical = $negative;
$historical[1]['respuesta'] = 'dato histórico conservado';
$historicalState = ConversationalM05Adapter::resolveV1Eligibility($historical);
$historicalFollowup = $byId($historicalState, 102);
$assert(($historicalFollowup['eligible'] ?? true) === false, 'Historical dependent became eligible after a negative parent');
$assert(($historicalFollowup['historical_preserved'] ?? false) === true, 'Historical dependent was not marked as preserved');
$assert(($historicalFollowup['respuesta'] ?? '') === 'dato histórico conservado', 'Historical dependent value was altered by the projection');

$contract = (new ConversationalInterviewService())->buildUnifiedContract([
    'M05' => ['key' => 'M05', 'title' => 'Antecedentes personales patológicos', 'questions' => $negativeState, 'diseases' => [], 'write_enabled' => true],
]);
$contractQuestions = $contract['modules']['M05']['questions'] ?? [];
$contractFollowup = $byId($contractQuestions, 102);
$contractNext = $contract['modules']['M05']['completion']['pending_question_ids'][0] ?? null;
$assert(($contractFollowup['eligibility']['eligible'] ?? true) === false, 'Canonical contract re-enabled a skipped follow-up');
$assert(($contractFollowup['response']['status'] ?? '') === 'not_applicable', 'Skipped follow-up is still pending in the canonical contract');
$assert(($contractFollowup['required'] ?? true) === false, 'Skipped follow-up remains required in the canonical contract');
$assert($contractNext === 'M05:v1:201', 'Canonical completion did not select the next independent question');

$unknowns = ['No lo sé', 'No recuerdo', 'Ahora no, pero antes sí'];
foreach ($unknowns as $utterance) {
    $classification = ConversationalM05Adapter::classifyBinaryResponse($utterance);
    $assert(($classification['value'] ?? null) === null, 'Unknown/contextual response was classified as a binary value: ' . $utterance);
}
$assert((ConversationalM05Adapter::classifyBinaryResponse('No')['value'] ?? null) === 'No', 'Explicit No was not classified as No');
$assert((ConversationalM05Adapter::classifyBinaryResponse('Sí, desde hace dos semanas')['value'] ?? null) === 'Si', 'Explicit affirmative with temporal detail was not classified as Yes');

$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$adapter = file_get_contents($root . '/app/Services/ConversationalM05Adapter.php');
$service = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');
$assert(str_contains($view, 'data-m05-quick-actions'), 'M05 quick-action group is missing');
$assert(str_contains($view, 'data-value="Si">Sí</button>'), 'M05 Sí quick action is missing');
$assert(str_contains($view, 'data-value="No">No, siguiente pregunta</button>'), 'M05 negative quick action is missing');
$assert(str_contains($view, 'const submitM05V1'), 'M05 quick actions do not share the server submission path');
$assert(str_contains($view, 'refreshM05QuestionVoice'), 'M05 next question does not refresh its voice target');
$assert(str_contains($adapter, 'resolveV1Eligibility') && str_contains($adapter, "'skipped_by_negative'"), 'Server-side M05 eligibility projection is missing');
$assert(str_contains($adapter, 'classifyBinaryResponse') && str_contains($adapter, 'ambiguous_or_unknown'), 'M05 unknown/contextual guard is missing');
$assert(str_contains($service, "'not_applicable'") && str_contains($service, "'eligibility' =>"), 'Canonical M05 contract does not expose inactive dependency state');

echo "M05 conditional navigation contract: PASS (negative skip, affirmative enablement, unknown safety, quick actions, voice target refresh)\n";
