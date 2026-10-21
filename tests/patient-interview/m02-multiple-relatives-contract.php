<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$adapter = new App\Services\ConversationalM02Adapter();
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$question = 'Enfermedades del corazón';
$text = 'Mis padres, hermana mayor y un tío materno';
$proposal = $adapter->interpret($question, $text);
$assert(($proposal['kind'] ?? '') === 'm02_read_only_proposal'
    && ($proposal['detail'] ?? null) === 'Si'
    && ($proposal['relative_description'] ?? null) === $text,
    'Multiple relatives must remain a single literal free-text answer');
$yes = $adapter->interpret($question, 'Sí');
$followup = $adapter->interpret($question, $text, $yes['context']);
$assert(($followup['relative_description'] ?? null) === $text && ($followup['kind'] ?? '') === 'm02_read_only_proposal', 'The yes clarification must lead directly to the free-text proposal');

$adapterSource = file_get_contents($root . '/app/Services/ConversationalM02Adapter.php');
$doctorSource = file_get_contents($root . '/app/Models/AntecedenteFamiliarModel.php');
$detailSource = file_get_contents($root . '/app/Services/ClinicalDetailService.php');
$snapshotSource = file_get_contents($root . '/app/Services/ClinicalSnapshotService.php');
$narrativeSource = file_get_contents($root . '/app/Services/ClinicalNarrativeService.php');
$finalizationSource = file_get_contents($root . '/app/Services/ConversationalModuleFinalizationService.php');
$assert(str_contains($adapterSource, 'pc_antecedentes_familiares_parentescos') && str_contains($adapterSource, "'relatives' => " . '$relatives'), 'Legacy structured relationships must remain readable');
$assert(str_contains($adapterSource, "'relative_description' =>") && str_contains($adapterSource, "'legacy_relative_text' => \$legacyText"), 'Free-text description must remain separate from structured legacy rows');
$assert(!str_contains($adapterSource, 'INSERT INTO pc_antecedentes_familiares_parentescos'), 'New writes must not classify or insert catalog relatives');
$assert(str_contains($adapterSource, 'M02_REPLACEMENT_CONFIRMATION_REQUIRED')
    && str_contains($adapterSource, 'beginTransaction()')
    && str_contains($adapterSource, 'rollBack()'), 'A confirmed replacement must be transactional and protected from accidental data loss');
$assert(str_contains($doctorSource, 'structuredRelativesFor') && str_contains($doctorSource, 'Parentescos estructurados anteriores'), 'Doctor view must preserve and label prior structured relatives');
$assert(!str_contains($doctorSource, 'DELETE FROM pc_antecedentes_familiares_parentescos')
    && !str_contains($doctorSource, 'DELETE r FROM pc_antecedentes_familiares_parentescos'), 'Traditional M02 paths must not delete structured historical rows');
$assert(str_contains($doctorSource, 'No se eliminó el antecedente porque ya contiene información clínica'), 'Deleting a clinical M02 history row must fail closed');
$assert(str_contains($detailSource, "'parentescos' => 'Parentescos estructurados anteriores'")
    && str_contains($detailSource, "'especificar' => 'Descripción familiar'"), 'Clinical details must expose both structured history and free-text description');
$assert(!str_contains($detailSource, "if (\$set !== []) \$row['especificar'] = null"), 'Clinical detail projection must not hide the original description');
$assert(!str_contains($snapshotSource, "unset(\$row['especificar'])"), 'Clinical snapshot projection must not drop free-text when structured history exists');
$assert(str_contains($narrativeSource, 'Parentescos estructurados anteriores') && str_contains($narrativeSource, 'Descripción familiar'), 'Narrative must keep the two historical/current fields distinguishable');
$assert(str_contains($finalizationSource, "\$record['legacy_relative_text'] ?? ''"), 'M02 completion must continue to require an affirmative description');

echo "M02_STRUCTURED_HISTORY_COMPATIBILITY=PASS (static and simulated only)\n";
