<?php

declare(strict_types=1);

/**
 * Delivery 10 focal contract. Static plus deterministic only: it never opens
 * PDO, HTTP, Apache or a clinical database.
 */
$root = dirname(__DIR__, 2);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$m04Source = file_get_contents($root . '/app/Services/ConversationalM04Adapter.php');
$m05Source = file_get_contents($root . '/app/Services/ConversationalM05Adapter.php');
$m06Source = file_get_contents($root . '/app/Services/ConversationalM06Adapter.php');
$serviceSource = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');
$controllerSource = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$viewSource = file_get_contents($root . '/app/views/paciente/entrevista.php');

require $root . '/vendor/autoload.php';

$m04 = new App\Services\ConversationalM04Adapter();
$negative = $m04->interpret('Nunca me han operado');
$assert(($negative['kind'] ?? null) === 'm04_negative_proposal', 'M04 explicit negative was interpreted as positive');
$assert(($negative['date'] ?? null) === null, 'M04 negative received a date');
$assert(($negative['requires_confirmation'] ?? true) === false, 'M04 negative created a confirmation proposal');
$assert(($negative['direct_save'] ?? false) === true, 'M04 negative did not enter the single save flow');

$contradiction = $m04->interpret('No, me operaron de la rodilla');
$assert(($contradiction['kind'] ?? null) === 'clarification', 'M04 contradiction must require clarification');

$exact = $m04->interpret('Me operaron de la rodilla el 15 de marzo de 2019');
$assert(($exact['kind'] ?? null) === 'm04_proposal' && ($exact['date'] ?? null) === '2019-03-15', 'M04 exact date contract failed');
$year = $m04->interpret('Me operaron de la rodilla en 2019');
$assert(($year['date'] ?? null) === null && ($year['observations'] ?? null) === 'Año referido: 2019', 'M04 year precision was rounded or lost');
$unknownDate = $m04->interpret('Me operaron de la rodilla pero no recuerdo cuándo');
$assert(($unknownDate['kind'] ?? null) === 'clarification', 'M04 unknown date was accepted as a writable proposal');

$m06 = new App\Services\ConversationalM06Adapter();
$complete = $m06->interpret('Tomo ibuprofeno para dolor durante 2 semanas, dosis 400 mg, médico López');
$assert(($complete['kind'] ?? null) === 'm06_proposal', 'M06 complete utterance was not accepted');
$assert(($complete['fields']['descripcion'] ?? null) === 'ibuprofeno', 'M06 medication name mapping changed');
$assert(($complete['fields']['medicamento'] ?? null) === 'dolor', 'M06 reason mapping changed');
$assert(($complete['fields']['tiempo_uso'] ?? null) === '2 semanas', 'M06 duration mapping failed');
$assert(($complete['fields']['dosis'] ?? null) === '400 mg', 'M06 dose mapping failed');
$assert(($complete['fields']['medico'] ?? null) === 'López', 'M06 prescriber mapping failed');

$unknownDose = $m06->interpret('Tomo ibuprofeno para dolor durante 2 semanas, no recuerdo la dosis, médico López');
$assert(($unknownDose['kind'] ?? null) === 'clarification', 'M06 unknown dose was accepted as complete');
$assert(in_array('dosis', $unknownDose['unknown_fields'] ?? [], true), 'M06 unknown dose was not identified explicitly');
$assert(($unknownDose['requires_confirmation'] ?? true) === false, 'M06 incomplete response became confirmable');

// M04 preserves precision, confirmation, versioning and readback at the
// persistence boundary; M06 reuses pending drafts and rejects duplicates.
$assert(str_contains($m04Source, "'m04_negative_proposal'") && str_contains($m04Source, 'explicitNegative'), 'M04 negative guard missing');
$assert(str_contains($m04Source, "M04_NEGATIVE_STATE_SCHEMA_REQUIRED") && str_contains($m04Source, "M04_NEGATIVE_CONFLICTING_SURGERY_RECORDS"), 'M04 negative persistence guards missing');
$assert(str_contains($m04Source, '$expectedVersion') && str_contains($m04Source, 'findRecord'), 'M04 version/readback guard missing');
$assert(str_contains($m05Source, "'target'=>'v1'") && str_contains($m05Source, "'target'=>'v2'"), 'M05 V1/V2 targets missing');
$assert(str_contains($serviceSource, "'M05:v1:'") && str_contains($serviceSource, "'M05:v2:'"), 'M05 question identities are not variant-specific');
$assert(str_contains($m05Source, 'Faltan campos dependientes M05 V2') && str_contains($m05Source, '$dependent'), 'M05 conditional dependencies missing');
$assert(str_contains($m05Source, "'version'=>hash('sha256'") && str_contains($m05Source, 'next_question') && str_contains($m05Source, 'next_disease'), 'M05 version/readback navigation missing');
$assert(str_contains($m06Source, "'pending_record'") && str_contains($m06Source, 'M06_DUPLICATE_EXISTING'), 'M06 pending/duplicate protection missing');
$assert(str_contains($m06Source, 'M06_READBACK_FAILED') && str_contains($m06Source, 'M06_READBACK_MISMATCH'), 'M06 readback verification missing');
$assert(str_contains($controllerSource, 'captureM04') && str_contains($controllerSource, 'captureM05') && str_contains($controllerSource, 'captureM06'), 'M04-M06 controller capture routes missing');
$assert(substr_count($controllerSource, 'validCaptureCsrf') >= 6, 'Interview capture CSRF guard missing');
$assert(str_contains($viewSource, 'data-module="M04"') && str_contains($viewSource, 'data-module="M05"') && str_contains($viewSource, 'data-module="M05-V2"') && str_contains($viewSource, 'data-module="M06"'), 'M04-M06 conversational cards missing');

echo "PASS M04-M06 functional contract (static plus deterministic; no database writes)\n";
echo "M04_INTEGRATION: PASS\n";
echo "M05_V1_V2_CONTRACT: PASS\n";
echo "M06_INTEGRATION: PASS\n";
