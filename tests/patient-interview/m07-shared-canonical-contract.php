<?php

declare(strict_types=1);

/** Static guard for the opt-in implementation; no database is opened. */
$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';

$service = file_get_contents($root . '/app/Services/M07CanonicalService.php') ?: '';
$gate = file_get_contents($root . '/app/Services/M07CanonicalActivationGate.php') ?: '';
$migration = file_get_contents(__DIR__ . '/m07-shared-canonical-migration.php') ?: '';
$validator = file_get_contents(__DIR__ . '/m07-shared-canonical-validate-existing.php') ?: '';
$baseline = file_get_contents(__DIR__ . '/m07-durable-clinical-baseline.php') ?: '';
$baselineRunner = file_get_contents(__DIR__ . '/run-m07-clinical-baseline-medium.ps1') ?: '';
$otherMedicationContract = file_get_contents(__DIR__ . '/m07-other-medication-sql-contract.php') ?: '';
$adapter = file_get_contents($root . '/app/Services/ConversationalM07Adapter.php') ?: '';
$routes = file_get_contents($root . '/app/Config/routes.php') ?: '';
$integration = file_get_contents(__DIR__ . '/m07-shared-canonical-integration.php') ?: '';
$runner = file_get_contents(__DIR__ . '/run-m07-validate-existing-medium.ps1') ?: '';

$checks = [
    'service_exists' => class_exists(App\Services\M07CanonicalService::class),
    'shared_dto_exists' => class_exists(App\Services\M07CanonicalRecord::class),
    'gate_defaults_closed' => (new App\Services\M07CanonicalActivationGate())->isEnabled() === false,
    'schema_gate_present' => str_contains($service, 'M07_SCHEMA_UNAVAILABLE'),
    'write_gate_present' => str_contains($service, 'M07_CANONICAL_DISABLED'),
    'draft_existing_response_guard' => str_contains($service, 'M07_DRAFT_EXISTING_RESPONSE'),
    'field_mask_present' => str_contains($service, 'fieldMask'),
    'version_guard_present' => str_contains($service, 'M07_VERSION_CONFLICT'),
    'idempotency_guard_present' => str_contains($service, 'M07_IDEMPOTENCY_CONFLICT'),
    'audit_table_present' => str_contains($service, 'pac_m07_operaciones'),
    'review_stale_guard_present' => str_contains($service, 'M07_REVIEW_STALE'),
    'three_new_tables_only' => substr_count($migration, 'CREATE TABLE pac_m07_') === 3,
    'synthetic_allowlist_present' => str_contains($migration, 'clinica_dolor_demo_20261010_final'),
    'adapter_read_integration_keeps_writes_closed' => str_contains($adapter, 'M07CanonicalService') && str_contains($adapter, "'write_enabled' => false") && str_contains($adapter, "'finalization_enabled' => false"),
    'current_routes_not_activated' => !str_contains($routes, 'M07CanonicalService'),
    'finalization_disabled' => str_contains($service, 'finalization_enabled') || str_contains($service, 'finalization_enabled\' => false'),
    'child_previous_exception_inspected' => str_contains($integration, 'getPrevious()'),
    'child_original_exception_class_reported' => str_contains($integration, 'first_error_original_class'),
    'child_driver_diagnostics_reported' => str_contains($integration, 'first_error_driver_code'),
    'child_rollback_check_before_restoration' => str_contains($integration, 'rollback_check_before_restoration'),
    'child_single_json_emitter' => str_contains($integration, '$emitJson') && str_contains($integration, 'outputEmitted'),
    'child_shutdown_json_fallback' => str_contains($integration, 'register_shutdown_function'),
    'clinical_snapshot_guard' => str_contains($integration, 'clinicalSnapshotTaken'),
    'clinical_restore_transaction' => str_contains($integration, '$db->beginTransaction()') && str_contains($integration, 'restoreClinicalSnapshot'),
    'parent_json_protocol_guard' => str_contains($runner, 'Get-SafeJsonProtocolFailure') && str_contains($runner, 'ChildJsonError'),
    'draft_fixture_requires_empty_entry' => str_contains($integration, 'draft_fixture_is_unanswered'),
    'draft_readback_uses_question_identity' => str_contains($integration, '$recordByQuestion'),
    'runner_structured_error_propagation' => str_contains($runner, 'FIRST_ERROR_ORIGINAL_CLASS') && str_contains($runner, 'DRIVER_CODE'),
    'read_only_validator_scoped_clinical_state' => str_contains($validator, 'scoped_catalog_state_value_consistency')
        && str_contains($validator, 'clinical_state_integrity'),
    'read_only_validator_covers_additive_tables' => str_contains($validator, 'scoped_other_domains_valid')
        && str_contains($validator, 'scoped_operation_domains_valid')
        && str_contains($validator, 'scoped_review_domains_valid'),
    'read_only_validator_rejects_missing_historical_baseline' => str_contains($validator, 'NOT_VERIFIABLE')
        && str_contains($validator, 'NO_DURABLE_CLINICAL_ARTIFACT_PROVIDED'),
    'durable_baseline_allowlist' => str_contains($baseline, 'M07_CLINICAL_BASELINE_V1')
        && str_contains($baseline, 'clinica_dolor_demo_20261010_final')
        && str_contains($baseline, '9301') && str_contains($baseline, '9302'),
    'durable_baseline_contains_rows_and_hash_manifest' => str_contains($baseline, "'rows'")
        && str_contains($baseline, "'manifest'") && str_contains($baseline, 'snapshot_sha256'),
    'durable_baseline_double_read_and_readback' => str_contains($baseline, 'firstRows')
        && str_contains($baseline, 'secondRows') && str_contains($baseline, 'snapshot_file_readback'),
    'durable_baseline_no_sql_writes' => !preg_match('/\b(?:INSERT\s+INTO|UPDATE\s+[^\s]+\s+SET|DELETE\s+FROM|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $baseline),
    'durable_baseline_medium_runner' => str_contains($baselineRunner, 'Medium Mandatory Level')
        && str_contains($baselineRunner, "--mode=create")
        && !str_contains($baselineRunner, 'AllowSyntheticWrites'),
    'other_medication_sql_regression_present' => str_contains($otherMedicationContract, 'named_placeholders_are_unique')
        && str_contains($otherMedicationContract, 'execute_keys_match_sql_markers'),
];

if (in_array(false, $checks, true)) {
    fwrite(STDERR, "M07 shared canonical contract: FAIL\n" . json_encode($checks, JSON_PRETTY_PRINT) . "\n");
    exit(1);
}

echo 'M07 shared canonical contract: PASS (' . count($checks) . " static assertions; no database opened)\n";
