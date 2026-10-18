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
    && str_contains($method, 'pac_m07_operaciones');
$checks['atomic_rollback_preserved'] = str_contains($method, 'beginTransaction()')
    && str_contains($method, 'rollBack()')
    && str_contains($method, 'M07_ATOMIC_ROLLBACK');
$checks['idempotency_and_stable_entry_key_preserved'] = str_contains($method, 'assertIdempotencyKey')
    && str_contains($method, '$entryKey = $this->uuidV4()');

if (in_array(false, $checks, true)) {
    fwrite(STDERR, "M07 other-medication SQL contract: FAIL\n" . json_encode($checks, JSON_PRETTY_PRINT) . "\n");
    exit(1);
}

echo 'M07 other-medication SQL contract: PASS (' . count($checks) . " static assertions; no database opened)\n";
