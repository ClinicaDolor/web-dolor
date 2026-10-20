<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$coordinator = file_get_contents($root . '/public/assets/js/patient-interview-coordinator.js');
$m07Gate = file_get_contents($root . '/app/Services/M07CanonicalActivationGate.php');
$finalizer = file_get_contents($root . '/app/Services/ConversationalModuleFinalizationService.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$assert($view !== false && $controller !== false && $coordinator !== false && $m07Gate !== false && $finalizer !== false, 'Review-removal sources are not readable');
$assert(!str_contains($view, 'Revisión clínica</strong>'), 'Generic clinical-review card remains in the patient view');
$assert(!str_contains($view, 'Interpretación propuesta'), 'Duplicate interpretation text remains in the patient view');
$assert(!str_contains($view, 'revisa cualquier propuesta y confirma antes de guardar'), 'Generic review/confirmation instruction remains in the patient view');
$assert(!str_contains($view, 'data-proposal-input'), 'Duplicate proposal input remains in the patient view');
$assert(!str_contains($view, 'data-proposal-confirm'), 'Generic secondary save button remains in the patient view');
$assert(!str_contains($view, 'class="patient-interview-proposal"'), 'Generic proposal card remains in the patient view');
$assert(!str_contains($view, 'data-m02-confirmation') && !str_contains($view, 'data-m04-confirmation') && !str_contains($view, 'data-m05-confirmation') && !str_contains($view, 'data-m06-confirmation') && !str_contains($view, 'data-m08-confirmation') && !str_contains($view, 'data-m09-confirmation'), 'Module confirmation panels remain in the patient view');
$assert(str_contains($view, 'const saveM02Proposal = async') && str_contains($view, 'const saveM04Proposal = async') && str_contains($view, 'const saveM05Proposal = async'), 'Direct-save handlers M02-M05 are missing');
$assert(str_contains($view, 'const saveM06Proposal = async') && str_contains($view, 'const saveM08Proposal = async') && str_contains($view, 'const saveM09Proposal = async'), 'Direct-save handlers M06/M08/M09 are missing');
$assert(str_contains($view, 'proposal.direct_save === true && proposal.can_confirm === true'), 'M03 direct-save guard is missing');
$assert(str_contains($controller, "\$proposal['direct_save'] =") && str_contains($controller, "\$proposal['can_confirm'] = \$proposal['direct_save'];"), 'Controller does not gate direct-save by server authorization');
$assert(str_contains($coordinator, 'proposal.direct_save === true && proposal.can_confirm === true'), 'Coordinator direct-save branch is missing');
$assert(str_contains($view, 'data-map-confirm'), 'Explicit M09 map controls were removed');
$assert(str_contains($m07Gate, 'private bool $enabled = false') && str_contains($m07Gate, 'if (!$this->enabled)') && str_contains($finalizer, 'M07_CANONICAL_DISABLED'), 'M07 server-side gate is not demonstrably closed');

echo "REMOVE_CLINICAL_REVIEW_UI_CONTRACT: PASS (static, no database writes)\n";
