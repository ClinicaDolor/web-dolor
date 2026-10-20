<?php

declare(strict_types=1);

/** Static PDO regression for the M07 additional-medication write path. */
$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/app/Services/M07CanonicalService.php') ?: '';
$start = strpos($service, 'public function createOtherMedication');
$end = strpos($service, '/** Review can only be written', $start === false ? 0 : $start);
$method = $start === false || $end === false ? '' : substr($service, $start, $end - $start);

$checks = [];
$checks['method_found'] = $method !== '';

$insertSql = '';
$insertNeedle = '$insert = $this->db->prepare(\'';
$insertStart = strpos($method, $insertNeedle);
if ($insertStart !== false) {
    $insertStart += strlen($insertNeedle);
    $insertEnd = strpos($method, "');", $insertStart);
    if ($insertEnd !== false) {
        $insertSql = substr($method, $insertStart, $insertEnd - $insertStart);
    }
}

$sqlMarkers = [];
preg_match_all('/:([a-z][a-z0-9_]*)\b/i', $insertSql, $sqlMatches);
foreach ($sqlMatches[1] ?? [] as $marker) {
    $sqlMarkers[] = strtolower($marker);
}
$sqlMarkerCounts = array_count_values($sqlMarkers);
$executeMarkers = [];
$executeStart = strpos($method, '$insert->execute([');
if ($executeStart !== false) {
    $executeEnd = strpos($method, ']);', $executeStart);
    $executeText = $executeEnd === false ? '' : substr($method, $executeStart, $executeEnd - $executeStart);
    preg_match_all("/'\:([a-z][a-z0-9_]*)'\s*=>/i", $executeText, $executeMatches);
    foreach ($executeMatches[1] ?? [] as $marker) {
        $executeMarkers[] = strtolower($marker);
    }
}

$checks['named_placeholders_are_unique'] = $sqlMarkers !== [] && max($sqlMarkerCounts) === 1;
$checks['execute_keys_match_sql_markers'] = array_values(array_unique($sqlMarkers)) === $executeMarkers;
$checks['created_updated_actor_placeholders_distinct'] = str_contains($insertSql, ':created_by')
    && str_contains($insertSql, ':updated_by')
    && !preg_match('/\bVALUES\b[^;]*:actor\b/i', $insertSql);
$checks['additional_medication_insert_contract_preserved'] = str_contains($method, 'M07_OTHER_CREATED')
    && str_contains($method, 'entry_key')
    && str_contains($method, 'insertOtherOperation')
    && str_contains($service, 'INSERT INTO pac_m07_medicamentos_no_catalogados')
    && str_contains($service, 'INSERT INTO pac_m07_operaciones');
$checks['atomic_rollback_preserved'] = str_contains($method, 'beginTransaction()')
    && str_contains($method, 'rollBack()')
    && str_contains($method, 'M07_ATOMIC_ROLLBACK');
$checks['idempotency_and_stable_entry_key_preserved'] = str_contains($method, 'assertIdempotencyKey')
    && str_contains($method, '$entryKey = $this->uuidV4()');
$checks['question_link_is_inserted_from_server_validated_id'] = str_contains($method, 'id_pregunta')
    && str_contains($method, 'authorizedOtherQuestion')
    && str_contains($method, 'questionId');
$checks['partial_capture_stays_pending_until_contract_complete'] = str_contains($service, "'OTHER_MEDICATION_INCOMPLETE'")
    && str_contains($service, "'dosis_state'")
    && str_contains($service, 'self::UNANSWERED');
$checks['optimistic_version_and_audit_for_other_updates'] = str_contains($service, 'function updateOtherMedication(')
    && str_contains($service, 'version=:version')
    && str_contains($service, 'UPDATE_OTHER')
    && str_contains($service, 'M07_VERSION_CONFLICT');
$updateStart = strpos($service, 'public function updateOtherMedication');
$updateEnd = strpos($service, '/** Review can only be written', $updateStart === false ? 0 : $updateStart);
$updateMethod = $updateStart === false || $updateEnd === false ? '' : substr($service, $updateStart, $updateEnd - $updateStart);
$updateSqlStart = strpos($updateMethod, "prepare('UPDATE ");
$updateSqlEnd = $updateSqlStart === false ? false : strpos($updateMethod, "');", $updateSqlStart);
$updateSql = $updateSqlEnd === false ? '' : substr($updateMethod, $updateSqlStart + strlen("prepare('"), $updateSqlEnd - ($updateSqlStart + strlen("prepare('")));
$updateExecuteStart = strpos($updateMethod, '$update->execute([');
$updateExecuteEnd = $updateExecuteStart === false ? false : strpos($updateMethod, ']);', $updateExecuteStart);
$updateExecuteText = $updateExecuteEnd === false ? '' : substr($updateMethod, $updateExecuteStart, $updateExecuteEnd - $updateExecuteStart);
preg_match_all('/:([a-z][a-z0-9_]*)\b/i', $updateSql, $updateMarkerMatches);
preg_match_all("/'\:([a-z][a-z0-9_]*)'\s*=>/i", $updateExecuteText, $updateExecuteMatches);
$updateMarkers = array_map('strtolower', $updateMarkerMatches[1] ?? []);
$updateKeys = array_map('strtolower', $updateExecuteMatches[1] ?? []);
$checks['update_placeholder_binding_exact'] = $updateSql !== '' && $updateMarkers !== []
    && count($updateMarkers) === count(array_unique($updateMarkers))
    && array_values(array_unique($updateMarkers)) === $updateKeys;
$checks['legacy_unlinked_rows_are_not_backfilled'] = str_contains($service, "'question_id'=>isset")
    && str_contains($service, 'id_pregunta')
    && str_contains($service, ':null');
$checks['no_answer_cannot_hide_active_linked_other_rows'] = str_contains($service, 'M07_OTHER_ACTIVE_ENTRIES_CONFLICT')
    && str_contains($service, "id_pregunta=:question AND estado_registro=:status");
$checks['all_six_canonical_other_questions_are_allowlisted'] = str_contains($service, '[8,15,22,30,37,46]')
    && str_contains($service, 'M07_OTHER_QUESTION_INVALID')
    && str_contains($service, 'id_tema');
$checks['multiple_linked_entries_are_projected_without_legacy_backfill'] = str_contains($service, "'question_id'=>isset")
    && str_contains($service, 'linkedOther[$questionId][]')
    && str_contains($service, 'count($linked)');
$view = file_get_contents($root . '/app/views/paciente/entrevista.php') ?: '';
$checks['ui_captures_name_then_one_pending_field_and_readback'] = str_contains($view, 'data-m07-new-other-name')
    && str_contains($view, 'data-next-field="<?=$entryStep;?>"')
    && str_contains($view, 'Guardar y continuar')
    && str_contains($view, 'data.readback?.other_medications');

if (in_array(false, $checks, true)) {
    fwrite(STDERR, "M07 other-medication SQL contract: FAIL\n" . json_encode($checks, JSON_PRETTY_PRINT) . "\n");
    exit(1);
}

echo 'M07 other-medication SQL contract: PASS (' . count($checks) . " static assertions; no database opened)\n";
