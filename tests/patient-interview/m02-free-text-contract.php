<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$adapter = new App\Services\ConversationalM02Adapter();
$service = new App\Services\ConversationalInterviewService();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$disease = 'Enfermedades del corazón';

foreach ([
    'Papá',
    'Mamá',
    'Papá y mamá',
    'Mis padres',
    'Padres, hermana mayor y paciente',
    'Mi tío materno',
    'Mi abuela y dos hermanos',
    'Mi hermana mayor y mi papá',
    'Familiares de distintas edades, descritos con mis propias palabras',
] as $answer) {
    $proposal = $adapter->interpret($disease, $answer);
    $assert(($proposal['kind'] ?? '') === 'm02_read_only_proposal', 'Valid free-text family answer must produce a direct proposal: ' . $answer);
    $assert(($proposal['detail'] ?? null) === 'Si', 'Free-text family answer must imply affirmative for the active disease');
    $assert(($proposal['relative_description'] ?? null) === $answer, 'Free-text must be preserved literally: ' . $answer);
    $assert(($proposal['direct_save'] ?? false) === true, 'A complete free-text answer must use the existing direct capture path');
}

$yes = $adapter->interpret($disease, 'Sí');
$afterYes = $adapter->interpret($disease, 'Mis padres y mi hermana mayor', $yes['context']);
$assert(($yes['kind'] ?? '') === 'clarification' && ($yes['clarification'] ?? '') === '¿Qué familiar o familiares tienen esa enfermedad?', 'A bare yes must ask only who in the family');
$assert(($afterYes['kind'] ?? '') === 'm02_read_only_proposal' && ($afterYes['relative_description'] ?? '') === 'Mis padres y mi hermana mayor', 'Free-text after yes must save directly without another confirmation');

foreach (['No', 'Ningún familiar tiene esta enfermedad', 'Nadie en mi familia padece la enfermedad', 'No hay antecedentes familiares de esta enfermedad'] as $negative) {
    $answer = $adapter->interpret($disease, $negative);
    $assert(($answer['kind'] ?? '') === 'm02_read_only_proposal' && ($answer['detail'] ?? null) === 'No', 'Clear global negative must remain negative: ' . $negative);
    $assert(($answer['relative_description'] ?? '') === '', 'Negative must not persist an affirmative description');
}
$ambiguousNegative = $adapter->interpret($disease, 'Mi papá no, pero mi mamá sí');
$assert(($ambiguousNegative['kind'] ?? '') === 'clarification' && ($ambiguousNegative['direct_save'] ?? false) !== true, 'Mixed/partial negative must not be classified or stored as an affirmative');

foreach (['No recuerdo', 'No lo sé', 'Desconozco', 'No lo recuerdo'] as $uncertainText) {
    $uncertain = $adapter->interpret($disease, $uncertainText, ['detail' => 'Si', 'relative_description' => 'Papá']);
    $assert(($uncertain['kind'] ?? '') === 'clarification' && ($uncertain['clarification_reason'] ?? '') === 'uncertainty_not_persisted', 'Uncertainty must be explicitly unresolved: ' . $uncertainText);
    $assert(($uncertain['detail'] ?? null) === null && ($uncertain['relative_description'] ?? '') === '', 'Uncertainty must not inherit a pending affirmative or description');
    $assert(($uncertain['direct_save'] ?? false) !== true, 'Uncertainty must never reach capture');
}

$otherDisease = $adapter->interpret('Hipertensión arterial sistémica', 'Mi papá tiene diabetes');
$assert(($otherDisease['kind'] ?? '') === 'clarification' && ($otherDisease['clarification_reason'] ?? '') === 'different_disease', 'An unrelated disease must not be captured under the active question');

$diabetes = $adapter->interpret('Diabetes Mellitus', 'Mi abuela y mi tío');
$assert(($diabetes['kind'] ?? '') === 'm02_read_only_proposal'
    && ($diabetes['detail'] ?? null) === 'Si'
    && ($diabetes['relative_description'] ?? '') === 'Mi abuela y mi tío'
    && ($diabetes['diabetes_type'] ?? null) === null, 'Diabetes family description must be valid without requiring its type');
$diabetesType = $adapter->interpret('Diabetes Mellitus', 'Mi papá tiene diabetes tipo 2');
$assert(($diabetesType['kind'] ?? '') === 'm02_read_only_proposal'
    && ($diabetesType['relative_description'] ?? '') === 'Mi papá tiene diabetes tipo 2'
    && ($diabetesType['diabetes_type'] ?? '') === 'Tipo 2', 'An explicitly stated type may be retained without altering the free-text description');
$typeOnly = $adapter->interpret('Diabetes Mellitus', 'Tipo 2');
$assert(($typeOnly['kind'] ?? '') === 'clarification' && ($typeOnly['clarification_reason'] ?? '') === 'positive_first'
    && ($typeOnly['relative_description'] ?? '') === '', 'A diabetes type alone must not be stored as a family description');

$recordMethod = new ReflectionMethod($adapter, 'record');
$recordMethod->setAccessible(true);
$legacy = $recordMethod->invoke($adapter, ['id' => 7, 'detalle' => 'Si', 'tipo' => '', 'especificar' => 'Papá'], $disease);
$assert(($legacy['relative_description'] ?? null) === 'Papá' && ($legacy['relatives'] ?? []) === [], 'Legacy free-text must not be reclassified as a catalog relationship');
$mixedLegacy = $recordMethod->invoke($adapter, ['id' => 8, 'detalle' => 'Si', 'tipo' => '', 'especificar' => 'Mi tío materno', '_relatives' => ['madre']], $disease);
$assert(($mixedLegacy['relative_description'] ?? null) === 'Mi tío materno' && ($mixedLegacy['relatives'] ?? []) === ['madre'], 'Legacy text and structured historical values must remain independently readable');
$oldAnswer = ['disease' => $disease, 'status' => 'documented', 'detail' => 'Si', 'relative_description' => 'Papá', 'legacy_relative_text' => 'Papá', 'relatives' => [], 'diabetes_type' => '', 'version' => 'v1'];
$newAnswer = ['detail' => 'Si', 'relative_description' => 'Mamá', 'diabetes_type' => ''];
$sameAnswer = ['detail' => 'Si', 'relative_description' => 'Papá', 'diabetes_type' => ''];
$assert($adapter->replacementConfirmationRequired(['records' => [$oldAnswer]], $disease, $newAnswer), 'Replacing existing free text must require explicit confirmation');
$assert(!$adapter->replacementConfirmationRequired(['records' => [$oldAnswer]], $disease, $sameAnswer), 'An identical replay must remain idempotent without a replacement prompt');
$structuredOnly = ['disease' => $disease, 'status' => 'pending', 'detail' => null, 'relative_description' => null, 'legacy_relative_text' => null, 'relatives' => ['madre'], 'diabetes_type' => '', 'version' => 'v2'];
$assert($adapter->replacementConfirmationRequired(['records' => [$structuredOnly]], $disease, $newAnswer), 'A structured historical answer must require explicit confirmation before replacement');

$contract = new ReflectionMethod($service, 'contractM02Questions');
$contract->setAccessible(true);
$questions = $contract->invoke($service, ['write_enabled' => true, 'records' => [['disease' => $disease, 'status' => 'pending', 'detail' => null, 'version' => 'v1']]]);
$help = $questions[0]['help'] ?? '';
$assert(str_contains($help, 'tus propias palabras') && str_contains($help, 'No, guardar y continuar') && str_contains($help, 'no se registrará como Sí ni como No'), 'Help must match current free-text, negative, and uncertainty actions');
$assert(($questions[0]['options']['relative_description'] ?? null) === 'free_text', 'M02 contract must define family details as free text');

$diabetesQuestions = $contract->invoke($service, ['write_enabled' => true, 'records' => [['disease' => 'Diabetes Mellitus', 'status' => 'pending', 'detail' => null, 'version' => 'v2']]]);
$diabetesQuestion = $diabetesQuestions[0] ?? [];
$assert(str_contains((string)($diabetesQuestion['help'] ?? ''), 'no es obligatorio'), 'Diabetes help must state that type is optional');
$assert(!str_contains((string)($diabetesQuestion['instruction'] ?? ''), 'indica también el tipo'), 'Diabetes instruction must not require a type');
$assert(!in_array(['when' => 'disease=Diabetes Mellitus AND detail=Si', 'requires' => ['diabetes_type']], $diabetesQuestion['dependencies'] ?? [], true), 'Diabetes type must not be a required dependency');

$finalizer = new App\Services\ConversationalModuleFinalizationService();
$m02Complete = new ReflectionMethod($finalizer, 'm02Complete');
$m02Complete->setAccessible(true);
$assert($m02Complete->invoke($finalizer, ['records' => [
    ['disease' => 'Diabetes Mellitus', 'status' => 'documented', 'detail' => 'Si', 'legacy_relative_text' => 'Papá', 'relatives' => [], 'inconsistent' => false, 'diabetes_type' => ''],
]]) === true, 'M02 completion must accept a documented diabetes description with unspecified type');
$assert($m02Complete->invoke($finalizer, ['records' => [
    ['disease' => 'Diabetes Mellitus', 'status' => 'documented', 'detail' => 'Si', 'legacy_relative_text' => '', 'relatives' => [], 'inconsistent' => false, 'diabetes_type' => ''],
]]) === false, 'M02 completion must still reject a positive answer without family description');

$adapterSource = file_get_contents($root . '/app/Services/ConversationalM02Adapter.php');
$assert(str_contains($adapterSource, 'UPDATE pc_antecedentes_familiares SET detalle=:detail,tipo=:type,especificar=:description'), 'Capture must write the existing TEXT column');
$assert(str_contains($adapterSource, 'M02_REPLACEMENT_CONFIRMATION_REQUIRED') && str_contains($adapterSource, 'confirm_replace_existing'), 'Existing data replacement must require explicit confirmation');
$assert(str_contains($adapterSource, 'M02_STRUCTURED_HISTORY_CONFLICT') && !str_contains($adapterSource, 'DELETE FROM pc_antecedentes_familiares_parentescos'), 'Structured historical rows must never be deleted by the new text capture; unsafe negative replacement must fail closed');
$assert(str_contains($adapterSource, 'beginTransaction()') && str_contains($adapterSource, 'rollBack()') && str_contains($adapterSource, 'readback' . "' => true"), 'Capture must remain transactional with readback and rollback');
$assert(!str_contains($adapterSource, 'INSERT INTO pc_antecedentes_familiares_parentescos'), 'New free-text answers must not depend on structured relationship inserts');
$assert(str_contains($adapterSource, 'isAuthorizedLocalWriteDatabase') && str_contains($adapterSource, 'SELECT DATABASE()'), 'M02 persistence must remain restricted to the exact authorized local database');
$assert(str_contains($adapterSource, 'GET_LOCK(:lock_name, 5)'), 'Same-patient/disease duplicate capture must remain serialized');
$preserveTypeExpression = <<<'CODE'
($diabetesType !== '' ? $diabetesType : (string)($record['diabetes_type'] ?? ''))
CODE;
$assert(str_contains($adapterSource, $preserveTypeExpression), 'An omitted type on description update must preserve a previously stored type');

$finalizationSource = file_get_contents($root . '/app/Services/ConversationalModuleFinalizationService.php');
$obsoleteFinalizationCheck = <<<'CODE'
($record['disease'] ?? '') === 'Diabetes Mellitus' && ($record['detail'] ?? '') === 'Si' && trim((string)($record['diabetes_type'] ?? '')) === ''
CODE;
$assert(!str_contains($finalizationSource, $obsoleteFinalizationCheck), 'M02 finalization must not require diabetes type');
$progressModel = file_get_contents($root . '/app/Models/PacienteModulosModelo.php');
$clinicModel = file_get_contents($root . '/app/Models/ClinicaModel.php');
$assert(!str_contains($progressModel, "f.tipo IN ('Tipo 1','Tipo 2','Gestacional')") && !str_contains($clinicModel, "tipo IN ('Tipo 1','Tipo 2','Gestacional')"), 'M02 progress must accept an answer without diabetes type');

echo "M02_FREE_TEXT_CONTRACT=PASS (no database connection or writes)\n";
