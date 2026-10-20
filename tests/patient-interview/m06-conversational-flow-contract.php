<?php

declare(strict_types=1);

use App\Services\ConversationalInterviewService;
use App\Services\ConversationalM06Adapter;
use App\Services\ConversationalProvider;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$fields = ['medicamento', 'descripcion', 'tiempo_uso', 'dosis', 'medico'];
$adapter = new ConversationalM06Adapter();
$complete = $adapter->interpret('Tomo gabapentina para dolor nervioso durante tres meses, dosis 300 mg por la noche, médico López');
$assert(($complete['kind'] ?? '') === 'm06_proposal', 'A complete M06 utterance must produce a proposal.');
$assert(array_keys($complete['fields'] ?? []) === $fields, 'M06 field order or names changed.');
$assert(count(array_filter($complete['fields'] ?? [], static fn($value): bool => trim((string)$value) !== '')) === 5, 'A complete M06 proposal must contain all five canonical fields.');

$nameOnly = $adapter->interpret('Paracetamol', 'medicamento');
$assert(($nameOnly['kind'] ?? '') === 'm06_proposal' && ($nameOnly['direct_save'] ?? false) === true, 'A medication name alone must be immediately saveable.');
$assert(($nameOnly['fields']['medicamento'] ?? '') === 'Paracetamol', 'A name-only answer must populate medicamento.');
$assert(in_array('descripcion', $nameOnly['missing'] ?? [], true) && in_array('dosis', $nameOnly['missing'] ?? [], true), 'A name-only answer must leave optional fields pending.');

$composed = $adapter->interpret('Paracetamol de 500 mg, me lo recetó un internista');
$assert(($composed['fields']['medicamento'] ?? '') === 'Paracetamol', 'The composed medication example must preserve the declared medication name only.');
$assert(($composed['fields']['dosis'] ?? '') === '500 mg', 'The composed medication example must preserve the declared dose.');
$assert(($composed['fields']['medico'] ?? '') === 'un internista', 'The composed medication example must preserve the declared prescriber text.');
$assert(($composed['fields']['descripcion'] ?? '') === '', 'The composed medication example must leave the undeclared reason empty.');
$assert(in_array('descripcion', $composed['missing'] ?? [], true) && in_array('tiempo_uso', $composed['missing'] ?? [], true), 'The composed medication example must request only the missing optional fields.');

$partial = $adapter->interpret('Tomo gabapentina para dolor');
$assert(($partial['kind'] ?? '') === 'm06_proposal', 'Partial M06 input with a recognizable name must be immediately saveable.');
$assert(($partial['fields']['medicamento'] ?? '') === 'gabapentina', 'Partial M06 context lost the medication name.');
$assert(($partial['fields']['descripcion'] ?? '') === 'dolor', 'Partial M06 context lost the use/reason field.');
$assert(in_array('dosis', $partial['missing'] ?? [], true), 'Partial M06 input must request the missing dose.');

$unknown = $adapter->interpret('No recuerdo la dosis');
$assert(($unknown['kind'] ?? '') === 'clarification', 'Unknown dose must not become a saved proposal.');
$assert(in_array('dosis', $unknown['unknown_fields'] ?? [], true), 'Unknown dose must remain explicitly unknown.');
$unknownName = $adapter->interpret('Tomo una pastilla para la presión, pero no recuerdo cómo se llama.');
$assert(($unknownName['kind'] ?? '') === 'clarification' && in_array('medicamento', $unknownName['unknown_fields'] ?? [], true), 'An unknown or generic medication name must not be saved.');
$negative = $adapter->interpret('No');
$assert(($negative['kind'] ?? '') === 'clarification', 'A bare No must not be interpreted as a medication record.');

final class M06NoExternalProvider implements ConversationalProvider
{
    public function interpretConversation(array $input): array
    {
        throw new RuntimeException('local test provider: no external call');
    }
}

$service = new ConversationalInterviewService(null, new M06NoExternalProvider());
$serviceNameOnly = $service->interpretM06('Paracetamol', [], 'medicamento');
$assert(($serviceNameOnly['kind'] ?? '') === 'm06_proposal' && ($serviceNameOnly['fields']['medicamento'] ?? '') === 'Paracetamol', 'The service must preserve a direct name-only M06 answer.');
$turnOne = $service->interpretM06('Tomo gabapentina para dolor');
$turnTwo = $service->interpretM06('300 mg por la noche durante tres meses', $turnOne['context'] ?? []);
$turnThree = $service->interpretM06('Me lo indicó el doctor López', $turnTwo['context'] ?? []);
$assert(($turnOne['kind'] ?? '') === 'm06_proposal', 'M06 first conversational turn with a recognizable name must be saveable.');
$assert(($turnTwo['context']['medicamento'] ?? '') === 'gabapentina', 'M06 context did not survive the second turn.');
$assert(($turnThree['kind'] ?? '') === 'm06_proposal', 'M06 multi-turn context did not reach a complete proposal.');
$assert(count(array_filter($turnThree['fields'] ?? [], static fn($value): bool => trim((string)$value) !== '')) === 5, 'M06 multi-turn proposal is incomplete.');

$emptyFinalizedContract = $service->buildUnifiedContract([
    'M01' => [], 'M02' => [], 'M03' => [], 'M04' => [], 'M05' => [],
    'M06' => ['records' => [], 'finalized' => true, 'write_enabled' => false],
    'M07' => [], 'M08' => [], 'M09' => [], 'M09Map' => [],
]);
$emptyFinalizedM06 = $emptyFinalizedContract['modules']['M06'] ?? [];
$assert(($emptyFinalizedM06['completion']['complete'] ?? false) === true, 'An explicitly finalized empty M06 must remain complete after reload.');
$assert(($emptyFinalizedM06['completion']['pending_question_ids'] ?? []) === [], 'An explicitly finalized empty M06 must not recreate a pending question.');

$recordFinalizedContract = $service->buildUnifiedContract([
    'M01' => [], 'M02' => [], 'M03' => [], 'M04' => [], 'M05' => [],
    'M06' => ['records' => [[
        'id' => 1, 'medicamento' => 'Paracetamol', 'descripcion' => 'dolor',
        'tiempo_uso' => 'una semana', 'dosis' => '500 mg', 'medico' => 'internista',
    ]], 'finalized' => true, 'write_enabled' => false],
    'M07' => [], 'M08' => [], 'M09' => [], 'M09Map' => [],
]);
$recordFinalizedM06 = $recordFinalizedContract['modules']['M06'] ?? [];
$assert(($recordFinalizedM06['completion']['complete'] ?? false) === true, 'A finalized M06 with records must remain complete after reload.');
$assert(($recordFinalizedM06['completion']['pending_question_ids'] ?? []) === [], 'A finalized M06 with records must not show add-another as pending.');

$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php') ?: '';
$finalizer = file_get_contents($root . '/app/Services/ConversationalModuleFinalizationService.php') ?: '';
$view = file_get_contents($root . '/app/views/paciente/entrevista.php') ?: '';
$interview = file_get_contents($root . '/app/Services/ConversationalInterviewService.php') ?: '';
$assert(str_contains($controller, "FINISH_NO_CURRENT_MEDICATION") && str_contains($controller, "FINISH_ADDITIONAL_MEDICATIONS"), 'M06 explicit finalization intents are missing.');
$assert(str_contains($controller, "'readback' => \$result['readback'] ?? false"), 'M06 capture does not expose readback.');
$assert(str_contains($controller, "finalizeIfComplete(\n                (int)\$patientId,\n                6,"), 'M06 finalization does not reuse the existing finalizer.');
$assert(str_contains($finalizer, 'bool $allowEmptyM06 = false') && str_contains($finalizer, 'if ($records === []) return $allowEmpty;'), 'Empty M06 completion is not restricted to the explicit negative intent.');
$assert(str_contains($view, 'data-m06-question') && str_contains($view, 'data-m06-interpret') && str_contains($view, 'data-m06-quick'), 'M06 question/composer/quick actions are missing.');
$assert(str_contains($view, 'chat-send-label">Enviar') && str_contains($view, 'data-m06-stable-id="M06:current-medication:') && str_contains($view, "'new'"), 'M06 send button or canonical target is missing.');
$assert(str_contains($view, 'data-m06-context') && str_contains($view, 'data-m06-state') && str_contains($view, "'after-save'"), 'M06 reload state/context contract is missing.');
$assert(str_contains($controller, "'next_action' => \$result['next_action'] ?? null"), 'M06 capture does not expose the progressive transition.');
$assert(str_contains($controller, "'pending_record' => \$result['pending_record']['id'] ?? null"), 'M06 capture does not expose the pending record identifier.');
$assert(str_contains($controller, "'pending_fields' => \$finalization['pending_fields'] ?? []"), 'M06 finalization does not expose the contract fields that block closure.');
$assert(str_contains($finalizer, 'private function m06PendingFields') && str_contains($finalizer, 'M06 no puede finalizar todavía. Completa:'), 'M06 pending finalization must identify missing contract fields without exposing clinical values.');
$assert(str_contains($view, "const message = data.mensaje || 'M06 no pudo confirmar su finalización mediante readback.'") && str_contains($view, "result.textContent = message"), 'M06 must show the specific finalization blocker in the module result area.');
$assert(!str_contains($view, 'START_NEW_MEDICATION') && str_contains($view, 'data-m06-skip-mode="unknown"') && str_contains($view, 'data-m06-skip-mode="omit"') && str_contains($view, 'm06FieldPrompts.medicamento'), 'M06 contextual detail actions are missing or still expose the obsolete incomplete-add action.');
$assert(str_contains($adapterSource = file_get_contents($root . '/app/Services/ConversationalM06Adapter.php') ?: '', 'M06_RECORD_ID_MISSING') && str_contains($adapterSource, "'fields' => \$fields"), 'M06 progressive capture must return the generated record identifier and field payload.');
$assert(str_contains($adapterSource, 'create_new') && str_contains(file_get_contents($root . '/app/Models/MedicacionActualModel.php') ?: '', 'lastInsertId'), 'M06 progressive capture must distinguish a new medication and return its generated identifier.');
$assert(str_contains($modelSource = file_get_contents($root . '/app/Models/MedicacionActualModel.php') ?: '', 'INSERT INTO pac_medicacion_actual_modulo_6') && str_contains($modelSource, 'M06_OMISSION_FIELDS'), 'M06 insertion and durable omission helpers are missing.');
$assert(str_contains($modelSource, 'upsertM06Omission') && str_contains($modelSource, 'resolveM06Omissions') && str_contains($modelSource, 'readM06Omissions'), 'M06 omission persistence/readback methods are missing.');
$assert(str_contains($adapterSource, 'captureOmission') && str_contains($adapterSource, 'M06_OMISSION_READBACK_FAILED'), 'M06 omission capture/readback contract is missing.');
$assert(str_contains($controller, "\$data['omission']") && str_contains($controller, 'captureOmission'), 'M06 controller omission path is missing.');
$assert(str_contains($finalizer, "'UNKNOWN'") && str_contains($finalizer, "'SKIPPED'") && str_contains($finalizer, 'pending_fields'), 'M06 finalization does not accept explicit omission states.');
$assert(str_contains($interview, "'field_states' => \$fieldStates") && str_contains($interview, "'omissions' => \$record['omissions']"), 'The shared M06 contract does not preserve omission states for navigation/readback.');
$assert(str_contains($adapterSource = file_get_contents($root . '/app/Services/ConversationalM06Adapter.php') ?: '', 'new \\App\\Models\\MedicacionActualModel($db)'), 'M06 persistence must reuse the request PDO for atomic insert/update.');
$assert(str_contains($adapterSource, "error_log('[clinica] M06 capture failed; stage=") && str_contains($adapterSource, '$failureStage'), 'M06 failures must retain safe internal stage diagnostics.');
$assert(str_contains($adapterSource, "'finalized' => (\$policy['finalized'] ?? false) === true"), 'M06 policy read failures must not be treated as finalized.');
$assert(str_contains($modelSource = file_get_contents($root . '/app/Models/MedicacionActualModel.php') ?: '', 'clinica_m06_finalizacion_'), 'M06 finalization must serialize concurrent retries.');
$assert(str_contains($interview, 'M06:current-medication:new') && str_contains($interview, '¿Tomas actualmente algún medicamento?'), 'M06 initial canonical question is missing.');

if ($failures !== []) {
    fwrite(STDERR, "M06_CONVERSATIONAL_FLOW: FAIL\n" . implode("\n", $failures) . "\n");
    exit(1);
}

echo "M06_CONVERSATIONAL_FLOW: PASS (deterministic, multi-turn, no MySQL/HTTP writes)\n";
