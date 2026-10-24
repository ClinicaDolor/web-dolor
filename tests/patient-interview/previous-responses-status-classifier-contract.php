<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\M03TobaccoResponsePolicy;
use App\Services\PreviousResponsesStatusClassifier;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$m02Question = [
    'module_id' => 'M02', 'question_id' => 'Enfermedades del corazón', 'stable_id' => 'M02:family:corazon',
    'eligibility' => ['eligible' => true, 'state' => 'eligible'],
    'response' => ['persisted' => false, 'status' => 'pending'],
];
$m02State = ['finalized' => false, 'duplicate_diseases' => []];
$m02Pending = PreviousResponsesStatusClassifier::m02($m02Question, null, $m02State);
$assert($m02Pending['status'] === 'PENDIENTE' && $m02Pending['pending'], 'M02_PENDING_NOT_CLASSIFIED');
$m02NoApplyQuestion = $m02Question;
$m02NoApplyQuestion['eligibility'] = ['eligible' => false, 'state' => 'not_applicable'];
$assert(PreviousResponsesStatusClassifier::m02($m02NoApplyQuestion, null, $m02State)['status'] === 'NO_APLICA', 'M02_EXPLICIT_NOT_APPLICABLE_NOT_CLASSIFIED');
$m02Unknown = $m02Question;
$m02Unknown['response'] = ['persisted' => true, 'status' => 'documented', 'semantic_state' => 'UNKNOWN'];
$m02Unknown['eligibility'] = ['eligible' => true];
$assert(PreviousResponsesStatusClassifier::m02($m02Unknown, ['id' => 91], $m02State)['status'] === 'POR_ACLARAR', 'M02_EXPLICIT_UNKNOWN_NOT_CLASSIFIED');
$m02Ambiguous = $m02Question;
$m02Ambiguous['eligibility'] = [];
$assert(PreviousResponsesStatusClassifier::m02($m02Ambiguous, null, $m02State)['status'] === 'REQUIERE_REVISION', 'M02_MISSING_ELIGIBILITY_INFERRED');

$parent = [
    'idPregunta' => 1, 'idTema' => 1, 'tipo' => 'select', 'idRespuesta' => 1, 'respuesta' => 'Si, fumo actualmente',
    'semanticState' => 'KNOWN', 'eligible' => true, 'navigationDecision' => M03TobaccoResponsePolicy::AFFIRMATIVE,
];
$childPending = [
    'idPregunta' => 2, 'idTema' => 1, 'parentQuestionId' => 1, 'tipo' => 'input', 'idRespuesta' => 0,
    'respuesta' => '', 'semanticState' => 'UNANSWERED', 'eligible' => true,
];
$byId = [1 => [$parent], 2 => [$childPending]];
$assert(PreviousResponsesStatusClassifier::m03([$childPending], $byId, true)['status'] === 'PENDIENTE', 'M03_ELIGIBLE_DEPENDENT_NOT_PENDING');
$childPending['eligible'] = false;
$parentNo = $parent;
$parentNo['respuesta'] = 'No';
$parentNo['semanticState'] = 'NEGATIVE';
$parentNo['navigationDecision'] = M03TobaccoResponsePolicy::NEGATIVE;
$byId[1] = [$parentNo];
$assert(PreviousResponsesStatusClassifier::m03([$childPending], $byId, true)['status'] === 'NO_APLICA', 'M03_EXPLICIT_NEGATIVE_DEPENDENCY_NOT_NA');
$byId[1] = [$parent];
$childPending['eligible'] = false;
$assert(PreviousResponsesStatusClassifier::m03([$childPending], $byId, true)['status'] === 'REQUIERE_REVISION', 'M03_UNRESOLVED_DEPENDENCY_MARKED_NOT_APPLICABLE');
$childUnknown = $childPending;
$childUnknown['idRespuesta'] = 22;
$childUnknown['semanticState'] = 'UNKNOWN';
$childUnknown['respuesta'] = '';
$childUnknown['eligible'] = true;
$assert(PreviousResponsesStatusClassifier::m03([$childUnknown], [1 => [$parent], 2 => [$childUnknown]], true)['status'] === 'POR_ACLARAR', 'M03_UNKNOWN_NOT_PRESERVED');
$childNotRemembered = $childUnknown;
$childNotRemembered['semanticState'] = 'NOT_REMEMBERED';
$assert(PreviousResponsesStatusClassifier::m03([$childNotRemembered], [1 => [$parent], 2 => [$childNotRemembered]], true)['status'] === 'POR_ACLARAR', 'M03_NOT_REMEMBERED_NOT_PRESERVED');
$legacyUnknown = $childUnknown;
$legacyUnknown['respuesta'] = 'No lo sé';
$assert(PreviousResponsesStatusClassifier::m03([$legacyUnknown], [1 => [$parent], 2 => [$legacyUnknown]], false)['status'] === 'POR_ACLARAR', 'M03_LEGACY_UNKNOWN_NOT_PRESERVED');
$assert(PreviousResponsesStatusClassifier::m03([$childPending, $childPending], [1 => [$parent]], true)['status'] === 'REQUIERE_REVISION', 'M03_DUPLICATE_NOT_FLAGGED');
$finalizedChild = $childPending;
$finalizedChild['eligible'] = true;
$assert(PreviousResponsesStatusClassifier::m03([$finalizedChild], [1 => [$parent], 2 => [$finalizedChild]], true, true)['status'] === 'REQUIERE_REVISION', 'M03_FINALIZED_PENDING_MISLABELED');

$view = file_get_contents(dirname(__DIR__, 2) . '/app/views/paciente/respuestas-previas.php');
$assert(str_contains($view, 'data-review-filters') && str_contains($view, 'data-review-pending-filter'), 'FILTER_CONTROLS_MISSING');
$assert(str_contains($view, 'No encontramos preguntas con esos filtros.'), 'EMPTY_STATE_COPY_MISSING');
$assert(str_contains($view, 'if ($themeEntries === []): ?><p class="previous-responses-empty">Aún no hay respuestas registradas en este tema.'), 'M03_EMPTY_THEME_DOES_NOT_EXPLAIN_NO_REGISTERED_ANSWERS');
$assert(str_contains($view, 'data-m03-response-editor') && str_contains($view, 'data-response-editor'), 'EXISTING_EDITORS_MISSING');
$assert(str_contains($view, 'Tu borrador se conservará.'), 'EDIT_DRAFT_PROTECTION_MISSING');
echo "Previous-responses status classifier contract: PASS (M02/M03 states, conditional applicability, finalization and editor-preservation markup; fixtures only)\n";
