<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$adapter = new App\Services\ConversationalM02Adapter();
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$disease = 'Hipertensión arterial sistémica';

foreach (['Papá', 'Mi padre', 'Mi tío materno', 'Mi abuela y dos hermanos', 'Padres, hermana mayor y paciente'] as $text) {
    $answer = $adapter->interpret($disease, $text);
    $assert(($answer['kind'] ?? '') === 'm02_read_only_proposal'
        && ($answer['detail'] ?? null) === 'Si'
        && ($answer['relative_description'] ?? null) === $text,
        'Natural family descriptions must be preserved verbatim, independently of the old relative catalog: ' . $text);
}
$partial = $adapter->interpret($disease, 'Mi papá no, pero mi mamá sí');
$assert(($partial['kind'] ?? '') === 'clarification' && ($partial['direct_save'] ?? false) !== true, 'Mixed polarity must not be guessed or saved');

foreach (['No recuerdo', 'No lo sé', 'Desconozco'] as $text) {
    $uncertain = $adapter->interpret($disease, $text);
    $assert(($uncertain['kind'] ?? '') === 'clarification'
        && ($uncertain['clarification_reason'] ?? '') === 'uncertainty_not_persisted'
        && ($uncertain['detail'] ?? null) === null,
        'Uncertainty must not be inferred as either polarity');
}
$unrelated = $adapter->interpret($disease, 'Mi papá tiene diabetes');
$assert(($unrelated['kind'] ?? '') === 'clarification' && ($unrelated['direct_save'] ?? false) !== true, 'A different disease must not be captured under the active question');

$adapterSource = file_get_contents($root . '/app/Services/ConversationalM02Adapter.php');
$captureStart = strpos($adapterSource, 'public function capture(');
$editStart = strpos($adapterSource, 'public function editExistingResponse(');
$captureBody = ($captureStart !== false && $editStart !== false) ? substr($adapterSource, $captureStart, $editStart - $captureStart) : '';
$assert(str_contains($adapterSource, 'GET_LOCK(:lock_name, 5)') && str_contains($adapterSource, 'FOR UPDATE'), 'Persistence must serialize same patient/disease and lock owned rows');
$assert(str_contains($adapterSource, 'UPDATE pc_antecedentes_familiares SET detalle=:detail,tipo=:type,especificar=:description'), 'Free text must be persisted in the existing especificar column');
$assert($captureBody !== '' && !str_contains($captureBody, 'INSERT INTO pc_antecedentes_familiares_parentescos'), 'The auxiliary table must not be required for ordinary new free-text writes');
$assert(str_contains($adapterSource, 'M02_REPLACEMENT_CONFIRMATION_REQUIRED') && str_contains($adapterSource, 'confirm_replace_existing'), 'Replacing existing data must require explicit confirmation');
$assert(str_contains($adapterSource, 'beginTransaction()') && str_contains($adapterSource, 'rollBack()') && str_contains($adapterSource, 'findRecord($patientId, $disease)'), 'Text update must remain transactional and perform an independent readback');

echo "M02_NATURAL_LANGUAGE_FREE_TEXT_CONTRACT=PASS (no database connection or writes)\n";
