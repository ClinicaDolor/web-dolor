<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\ConversationalInterviewService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$service = new ConversationalInterviewService();
$records = [
    [
        'id' => 701,
        'cirugia' => 'Apendicectomía',
        'fecha' => '2020-04-15',
        'observaciones' => '',
    ],
];

$finalized = $service->buildUnifiedContract([
    'M04' => [
        'records' => $records,
        'negative_declaration' => null,
        'version' => 'm04-final-v1',
        'write_enabled' => false,
        'finalized' => true,
    ],
]);
$finalizedModule = $finalized['modules']['M04'] ?? [];
$finalizedQuestionIds = array_map(
    static fn(array $question): string => (string)($question['stable_id'] ?? ''),
    $finalizedModule['questions'] ?? []
);
$assert(!in_array('M04:record:new', $finalizedQuestionIds, true), 'finalized M04 must not expose a new continuation question');
$assert(($finalizedModule['completion']['complete'] ?? false) === true, 'finalized M04 must remain complete after reload');
$assert(($finalizedModule['state']['finalized'] ?? false) === true, 'M04 finalization must come from persisted state');

$open = $service->buildUnifiedContract([
    'M04' => [
        'records' => $records,
        'negative_declaration' => null,
        'version' => 'm04-open-v1',
        'write_enabled' => true,
        'finalized' => false,
    ],
]);
$openModule = $open['modules']['M04'] ?? [];
$assert(in_array('M04:record:new', array_map(static fn(array $question): string => (string)($question['stable_id'] ?? ''), $openModule['questions'] ?? []), true), 'open M04 must expose the continuation question');
$assert(($openModule['completion']['complete'] ?? true) === false, 'open M04 must remain pending until explicit finish');
$assert(in_array('M04:record:new', $openModule['completion']['pending_question_ids'] ?? [], true), 'open M04 continuation must be pending');

$m07 = $service->buildUnifiedContract([
    'M07' => [
        'title' => 'Medicamentos para el dolor',
        'write_enabled' => false,
        'finalized' => false,
        'write_blocker' => 'M07_CANONICAL_DISABLED',
    ],
]);
$assert(($m07['modules']['M07']['completion']['status'] ?? '') === 'blocked', 'M07 must remain blocked during resume');
$assert(($m07['modules']['M07']['completion']['complete'] ?? true) === false, 'blocked M07 must not be treated as complete');

$m02Pending = $service->buildUnifiedContract([
    'M02' => [
        'records' => [[
            'disease' => 'Enfermedades del corazón',
            'detail' => null,
            'status' => 'pending',
            'version' => 'm02-v1',
        ]],
        'write_enabled' => true,
        'finalized' => false,
    ],
]);
$m02Module = $m02Pending['modules']['M02'] ?? [];
$assert(($m02Module['completion']['pending_question_ids'] ?? []) !== [], 'pending M02 must expose a canonical pending question');
$assert(($m02Module['conversation_state']['state'] ?? '') === 'ANSWERABLE', 'pending M02 must resolve to ANSWERABLE');
$assert(($m02Module['conversation_state']['action'] ?? '') === 'ANSWER', 'pending M02 must expose an answer action');
$m02PendingStableId = (string)($m02Module['completion']['pending_question_ids'][0] ?? '');
$m02PendingQuestion = $m02Pending['question_index'][$m02PendingStableId] ?? [];
$assert(str_starts_with($m02PendingStableId, 'M02:family:') && ($m02PendingQuestion['question_id'] ?? null) === 'Enfermedades del corazón', 'M02 pending stable_id must derive from the canonical disease');

$m02NeedsFinalization = $service->buildUnifiedContract([
    'M02' => [
        'records' => [[
            'disease' => 'Enfermedades del corazón',
            'detail' => 'No',
            'status' => 'documented',
            'version' => 'm02-v2',
        ]],
        'write_enabled' => true,
        'finalized' => false,
    ],
]);
$m02FinalizationModule = $m02NeedsFinalization['modules']['M02'] ?? [];
$assert(($m02FinalizationModule['completion']['pending_question_ids'] ?? []) === [], 'M02 with documented answers must not invent a pending question');
$assert(($m02FinalizationModule['completion']['complete'] ?? true) === false, 'M02 without persisted finalization must not be closed artificially');
$assert(($m02FinalizationModule['completion']['resolution'] ?? '') === 'AUTHORIZED_FINALIZATION_REQUIRED', 'M02 without finalization must expose its authorized resolution');
$assert(($m02FinalizationModule['conversation_state']['state'] ?? '') === 'READY_TO_FINALIZE', 'documented M02 must resolve to READY_TO_FINALIZE');
$assert(($m02FinalizationModule['conversation_state']['action'] ?? '') === 'FINALIZE', 'documented M02 must expose an explicit finalization action');
$assert(array_key_exists('stable_id', $m02FinalizationModule['conversation_state'] ?? []) && $m02FinalizationModule['conversation_state']['stable_id'] === null, 'M02 finalization must not reuse a clinical question stable_id');
$assert(($m02FinalizationModule['conversation_state']['action_id'] ?? '') === 'M02:action:finalize', 'M02 finalization must expose a validated action id');
$assert(trim((string)($m02FinalizationModule['conversation_state']['voice_text'] ?? '')) !== '', 'M02 finalization must expose action narration text');
$assert(($m02NeedsFinalization['interview_progress']['first_pending_module_id'] ?? null) === 'M02', 'M02 requiring authorized finalization must remain the server-selected resume module');
$assert(($m02NeedsFinalization['interview_progress']['first_pending_stable_id'] ?? null) === null, 'M02 finalization action must not invent a pending question stable_id');

// Reproduce the observed reload shape: M02 has documented answers but no
// persisted finalization, while M04 is already finalized. The server must
// still select M02 as the actionable module after F5.
$reloadedObservedState = $service->buildUnifiedContract([
    'M02' => [
        'records' => [[
            'disease' => 'Enfermedades del corazón',
            'detail' => 'No',
            'status' => 'documented',
            'version' => 'm02-reload-v1',
        ]],
        'write_enabled' => true,
        'finalized' => false,
    ],
    'M04' => [
        'records' => $records,
        'negative_declaration' => null,
        'version' => 'm04-reload-final-v1',
        'write_enabled' => false,
        'finalized' => true,
    ],
]);
$assert(($reloadedObservedState['modules']['M02']['completion']['complete'] ?? true) === false, 'observed reload state must keep M02 open');
$assert(($reloadedObservedState['modules']['M04']['completion']['complete'] ?? false) === true, 'observed reload state must preserve finalized M04');
$assert(($reloadedObservedState['interview_progress']['first_pending_module_id'] ?? null) === 'M02', 'observed reload state must not let finalized M04 displace actionable M02');
$assert(($reloadedObservedState['interview_progress']['first_pending_stable_id'] ?? null) === null, 'observed reload state must expose an action instead of a phantom question');

$m02Review = $service->buildUnifiedContract([
    'M02' => [
        'records' => [[
            'disease' => 'Enfermedades del corazón',
            'detail' => 'Si',
            'status' => 'documented',
            'version' => 'm02-review-v1',
        ]],
        'write_enabled' => true,
        'finalized' => false,
        'professional_review_pending' => true,
    ],
]);
$assert(($m02Review['modules']['M02']['conversation_state']['state'] ?? '') === 'AWAITING_PROFESSIONAL_REVIEW', 'explicit professional review must not become a negative or an answerable state');
$assert(($m02Review['modules']['M02']['conversation_state']['action'] ?? '') === 'AWAIT_REVIEW', 'professional review must expose a review action');

$m02Inconsistent = $service->buildUnifiedContract([
    'M02' => [
        'records' => [[
            'disease' => 'Enfermedades del corazón',
            'detail' => null,
            'status' => 'pending',
            'version' => 'm02-inconsistent-v1',
        ]],
        'write_enabled' => false,
        'finalized' => true,
    ],
]);
$assert(($m02Inconsistent['modules']['M02']['conversation_state']['state'] ?? '') === 'INCONSISTENT', 'finalized M02 with a pending question must be inconsistent');
$assert(($m07['modules']['M07']['conversation_state']['state'] ?? '') === 'BLOCKED_PREREQUISITE', 'M07 must remain blocked by the canonical activation gate');

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$serviceSource = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');
$controllerSource = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$assert(is_string($view) && str_contains($view, 'stage.completion') === false, 'resume must read the server module contract, not a stale stage-local completion');
$assert(is_string($view) && str_contains($view, 'conversationContract.modules?.[moduleId]?.completion'), 'resume must consult server completion for each stage');
$assert(is_string($view) && str_contains($view, 'conversationContract.modules?.[moduleId]?.conversation_state'), 'resume must consult the canonical conversation state resolver');
$assert(is_string($view) && str_contains($view, 'READY_TO_FINALIZE'), 'view must distinguish an explicit finalization action from a technical blocker');
$assert(is_string($view) && str_contains($view, "resolver.state === 'ANSWERABLE'"), 'view must only focus an M02 question when the resolver allows answering');
$assert(is_string($view) && str_contains($view, 'AWAITING_PROFESSIONAL_REVIEW'), 'view must preserve the professional review state without enabling capture');
$assert(is_string($view) && str_contains($view, 'const nextPendingStage = startIndex'), 'forward navigation must skip finalized stages');
$assert(is_string($view) && str_contains($view, 'const nextStage = nextPendingStage(conversationStage + 1)'), 'forward navigation must resolve the next pending stage');
$assert(is_string($view) && str_contains($view, 'conversationStages.findIndex(stageNeedsResume)'), 'resume must fall back to the first pending stage when the server does not nominate a module');
$assert(is_string($view) && str_contains($view, 'conversationContract.interview_progress?.first_pending_module_id'), 'resume must prefer the server-selected pending module');
$assert(is_string($view) && str_contains($view, "const stageModuleId = stage => stage?.type === 'm03' ? 'M03'"), 'resume must compare canonical module IDs across stage types');
$assert(is_string($view) && str_contains($view, "conversationStages.push({type:'m03', card:null})"), 'progress must retain M03 in the stage denominator even when no card is rendered');
$assert(is_string($view) && str_contains($view, 'conversationStages.length));'), 'completed interviews must have an explicit no-pending stage');
$assert(is_string($view) && str_contains($view, 'No hay otra etapa autorizada pendiente'), 'completed resume state must not show a stale module');
$assert(is_string($view) && str_contains($view, 'pending_question_ids'), 'M02 resolution must use canonical pending question IDs');
$assert(is_string($view) && str_contains($view, 'M02_ACTIVE_QUESTION_UNRESOLVED'), 'M02 without a resolvable question must expose a functional blocker');
$assert(is_string($view) && str_contains($view, 'M02_CONTRACT_BLOCKED'), 'M02 contract blocking must remain explicit');
$assert(is_string($view) && str_contains($view, 'conversationContract.question_index?.[target]?.module_id !== \'M02\''), 'M02 target must remain aligned with the canonical module');
$assert(is_string($view) && !str_contains($view, 'historia-clinica/antecedentes-familiares/paciente/'), 'M02 finalization must not redirect to the traditional form when the conversational action is available');
$assert(is_string($view) && str_contains($view, 'Finalizar antecedentes familiares y continuar'), 'M02 ready-to-finalize state must expose an explicit patient action');
$assert(is_string($view) && str_contains($view, 'finalize: true'), 'M02 finalization must be an explicit POST action');
$assert(is_string($view) && str_contains($view, 'data.finalization_readback !== true'), 'M02 finalization must require server readback before advancing');
$assert(is_string($view) && str_contains($view, 'keep requests enabled'), 'M02 finalization action must not enter the coordinator blocked state');
$assert(is_string($view) && str_contains($view, 'setActionTarget(resolver.action_id'), 'M02 finalization must use a validated action target rather than a clinical question id');
$assert(is_string($view) && str_contains($view, "event.action_id !== current"), 'M02 action narration must validate its action context');
$assert(is_string($controllerSource) && str_contains($controllerSource, 'ConversationalModuleFinalizationService'), 'M02 finalization must reuse the canonical finalization service');
$assert(is_string($controllerSource) && str_contains($controllerSource, 'validCaptureCsrf'), 'M02 finalization must require capture CSRF');
$assert(is_string($controllerSource) && str_contains($controllerSource, "'readback' => \$readback"), 'M02 finalization must expose generic coordinator readback');
$assert(is_string($controllerSource) && str_contains($controllerSource, "'finalize'"), 'M02 finalization must be explicit in the existing capture endpoint');
$assert(is_string($serviceSource) && str_contains($serviceSource, "if ((bool)(\$state['finalized'] ?? false))"), 'M04 contract must stop adding continuation after finalization');

echo "Resume finalized module contract: PASS (M04 finalization, pending continuation, M07 blocked gate)\n";
