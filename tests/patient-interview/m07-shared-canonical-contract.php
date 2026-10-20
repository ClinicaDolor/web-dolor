<?php

declare(strict_types=1);

/** Static guard for the opt-in implementation; no database is opened. */
$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';
require_once __DIR__ . '/m07-snapshot-inventory.php';
require_once __DIR__ . '/m07-integration-commit-fault.php';

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
    'adapter_uses_gate_for_writes' => str_contains($adapter, 'M07CanonicalService') && str_contains($adapter, '$canonical->active()') && str_contains($adapter, 'M07_CANONICAL_DISABLED'),
    'current_routes_not_activated' => !str_contains($routes, 'M07CanonicalService'),
    'finalization_contract_implemented' => str_contains($service, 'function finalizeModule(') && str_contains($service, 'M07_PENDING_RESPONSES') && str_contains($service, 'pac_historia_clinica_finalizar'),
    'finalization_requires_full_catalog_and_no_pending' => str_contains($service, "count(\$current['records'] ?? []) !== 46") && str_contains($service, "(int)(\$current['pending_items'] ?? 0) !== 0"),
    'finalization_readback_matches_operation_hash' => str_contains($service, "hash_equals(\$afterHash, (string)\$readback['version'])"),
    'application_gate_remains_closed' => (new App\Services\M07CanonicalActivationGate())->isEnabled() === false,
    'child_previous_exception_inspected' => str_contains($integration, 'getPrevious()'),
    'child_original_exception_class_reported' => str_contains($integration, 'first_error_original_class'),
    'child_driver_diagnostics_reported' => str_contains($integration, 'first_error_driver_code'),
    'child_rollback_check_before_restoration' => str_contains($integration, 'rollback_check_before_restoration'),
    'child_single_json_emitter' => str_contains($integration, '$emitJson') && str_contains($integration, 'outputEmitted'),
    'child_shutdown_json_fallback' => str_contains($integration, 'register_shutdown_function'),
    'clinical_snapshot_guard' => str_contains($integration, 'clinicalSnapshotTaken'),
    'durable_snapshot_required_before_dml' => str_contains($integration, "snapshot']") && str_contains($integration, 'PASS_HASH_AND_SCOPE_VERIFIED') && str_contains($integration, 'snapshot durable sintético legible antes de DML'),
    'append_only_audit_never_deleted' => !str_contains($integration, 'DELETE FROM pac_m07_operaciones') && str_contains($integration, 'NOT_PERFORMED_APPEND_ONLY_AUDIT_PRESERVED'),
    'synthetic_remainders_explicitly_inventoried' => str_contains($integration, 'retained_test_records') && str_contains($integration, 'PASS_EXPECTED_SYNTHETIC_DELTA_ONLY'),
    'patient_isolation_uses_read_contract' => !str_contains($integration, "\$patientBState['patient_id']") && str_contains($integration, 'patientBUnchangedAfterDenied') && str_contains($integration, 'sourceUnchangedAfterDenied'),
    'rollback_snapshot_is_table_keyed' => str_contains($integration, '$snapshotPatientTables') && str_contains($integration, '$operationSnapshot[\'A\'][$table]'),
    'snapshot_rows_and_manifest_require_exact_inventory' => str_contains($integration, 'm07SnapshotInventoryHasExactTables($durableSnapshot[\'rows\']')
        && str_contains($integration, 'm07SnapshotInventoryHasExactTables($durableSnapshot[\'manifest\']'),
    'snapshot_inventory_complete_fixture' => m07SnapshotInventoryHasExactTables([
        'pac_respuestas_paciente_modulo_7' => [],
        'pac_m07_medicamentos_no_catalogados' => [],
        'pac_m07_operaciones' => [],
        'pac_m07_revision_clinica' => [],
    ], ['pac_respuestas_paciente_modulo_7','pac_m07_medicamentos_no_catalogados','pac_m07_operaciones','pac_m07_revision_clinica']),
    'snapshot_inventory_incomplete_fixture_rejected' => !m07SnapshotInventoryHasExactTables([
        'pac_respuestas_paciente_modulo_7' => [],
        'pac_m07_medicamentos_no_catalogados' => [],
        'pac_m07_operaciones' => [],
    ], ['pac_respuestas_paciente_modulo_7','pac_m07_medicamentos_no_catalogados','pac_m07_operaciones','pac_m07_revision_clinica']),
    'snapshot_inventory_unexpected_name_rejected' => !m07SnapshotInventoryHasExactTables([
        'pac_respuestas_paciente_modulo_7' => [],
        'pac_m07_medicamentos_no_catalogados' => [],
        'pac_m07_operaciones' => [],
        'pac_m07_revision_clinica' => [],
        'unexpected_table' => [],
    ], ['pac_respuestas_paciente_modulo_7','pac_m07_medicamentos_no_catalogados','pac_m07_operaciones','pac_m07_revision_clinica']),
    'authorized_retained_id_inventory_validated_by_patient' => m07RowsContainExpectedIdsForPatient([
        'pac_respuestas_paciente_modulo_7'=>[['id'=>314,'id_paciente'=>9301]],
        'pac_m07_medicamentos_no_catalogados'=>array_map(static fn(int $id): array=>['id'=>$id,'id_paciente'=>9301],range(3,8)),
        'pac_m07_operaciones'=>array_map(static fn(int $id): array=>['id'=>$id,'id_paciente'=>9301],range(23,49)),
        'pac_m07_revision_clinica'=>[],
    ], ['pac_respuestas_paciente_modulo_7'=>[314],'pac_m07_medicamentos_no_catalogados'=>[3,4,5,6,7,8],'pac_m07_operaciones'=>range(23,49),'pac_m07_revision_clinica'=>[]], 9301),
    'authorized_retained_id_wrong_patient_rejected' => !m07RowsContainExpectedIdsForPatient([
        'pac_respuestas_paciente_modulo_7'=>[['id'=>314,'id_paciente'=>9302]],
        'pac_m07_medicamentos_no_catalogados'=>array_map(static fn(int $id): array=>['id'=>$id,'id_paciente'=>9301],range(3,8)),
        'pac_m07_operaciones'=>array_map(static fn(int $id): array=>['id'=>$id,'id_paciente'=>9301],range(23,49)),
        'pac_m07_revision_clinica'=>[],
    ], ['pac_respuestas_paciente_modulo_7'=>[314],'pac_m07_medicamentos_no_catalogados'=>[3,4,5,6,7,8],'pac_m07_operaciones'=>range(23,49),'pac_m07_revision_clinica'=>[]], 9301),
    'other_medication_payload_conflict_regression_prepared' => str_contains($integration, 'other_medication_idempotency_payload_conflict')
        && str_contains($integration, '$otherConflictNoMutation'),
    'other_medication_stale_version_independent_immutability_prepared' => str_contains($integration, '$staleNoMutation')
        && str_contains($integration, '$otherAfterStale'),
    'pending_dml_suite_is_opt_in_and_limited_to_four_cases' => str_contains($integration, "array_key_exists('pending-dml-only', \$options)")
        && str_contains($integration, 'PASS_PENDING_SYNTHETIC_DML_CASES')
        && str_contains($integration, "?['other_medication_idempotency_payload_conflict','other_medication_stale_version_conflict','append_only_operation_audit','other_medication_atomic_rollback']"),
    'pending_dml_requires_exact_34_row_snapshot_scope_before_write' => str_contains($integration, "'previous_rows_expected'] = 34")
        && str_contains($integration, 'snapshot no coincide exactamente con los 34 registros sintéticos previamente autorizados')
        && str_contains($integration, 'PASS_PATIENT_CLINIC_QUESTION_SCOPE')
        && str_contains($integration, "'pac_respuestas_paciente_modulo_7' => [310, 314]"),
    'previous_synthetic_response_row_310_is_not_patient_310' => str_contains($integration, '$responseRowsById[310]')
        && str_contains($integration, "\$responseRowsById[310]['id_paciente'] ?? 0) !== \$patientA")
        && str_contains($integration, "\$responseRowsById[310]['id_pregunta'] ?? 0) !== 1"),
    'pending_dml_checks_effective_pc_3306_and_scope_each_case' => str_contains($integration, "@@hostname AS server_name, @@port AS server_port")
        && str_contains($integration, "!== 'pc'") && str_contains($integration, '$assertDatabaseIdentity($rollbackDb,$readbackDb)'),
    'pending_dml_identical_replay_conflict_and_per_case_preservation' => str_contains($integration, '$pendingReplaySameRecord')
        && str_contains($integration, '$verifyPendingState(1,1)') && str_contains($integration, 'Un registro preexistente fue alterado durante una prueba.'),
    'pending_dml_write_budget_enforced' => str_contains($integration, 'PASS_ONE_MEDICATION_ONE_AUDIT_NO_OTHER_ROWS')
        && str_contains($integration, '$newMedicationCount === 1 && $newAuditCount === 1 && $otherCreatedCount === 0'),
    'pending_dml_runner_is_separate_from_broad_fixture_mode' => str_contains($runner, '[switch]$PendingDmlOnly')
        && str_contains($runner, 'Invoke-ExpectedPriorBaselineDiff')
        && str_contains($runner, 'PASS_EXACT_PREVIOUS_34_ROW_DELTA')
        && str_contains($runner, '--pending-dml-only') && str_contains($runner, 'No se combina el modo acotado con el modo amplio de escrituras.'),
    'append_only_test_verifies_prior_rows_unchanged_and_new_keys_traceable' => str_contains($integration, '$preexistingAuditPreserved')
        && str_contains($integration, '$newAuditRowsTraceable') && str_contains($integration, '$generatedKeySet'),
    'post_run_reconciliation_has_independent_integrity_indicators' => str_contains($integration, "'original_baseline_preserved'")
        && str_contains($integration, "'authorized_synthetic_rows_retained'")
        && str_contains($integration, "'unexpected_data_differences'"),
    'unexpected_post_run_differences_fail_closed' => str_contains($integration, "'FAIL_UNEXPECTED_DATA_DIFFERENCE'")
        && str_contains($integration, "'PASS_EXPECTED_SYNTHETIC_DELTA_ONLY'"),
    'rollback_regression_uses_test_only_one_shot_commit_failure' => str_contains($integration, 'M07IntegrationOneShotCommitFailurePdo')
        && str_contains($integration, 'other_medication_atomic_rollback')
        && str_contains($integration, 'M07_ATOMIC_ROLLBACK')
        && !str_contains($integration, 'CREATE TRIGGER')
        && !str_contains($integration, 'DROP TRIGGER'),
    'rollback_fault_double_is_one_shot_without_database' => (static function (): bool {
        $double = (new ReflectionClass(M07IntegrationOneShotCommitFailurePdo::class))->newInstanceWithoutConstructor();
        try {
            $double->commit();
            return false;
        } catch (PDOException $exception) {
            return $exception->getMessage() === 'Synthetic one-shot commit failure.';
        }
    })(),
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
    'other_question_link_migration_is_additive_fail_closed' => str_contains(file_get_contents($root . '/database/migrations/20261019_m07_other_question_link.php') ?: '', "'id_pregunta'")
        && str_contains(file_get_contents($root . '/database/migrations/20261019_m07_other_question_link.php') ?: '', 'clinica_dolor_demo_20261010_final')
        && str_contains(file_get_contents($root . '/database/migrations/20261019_m07_other_question_link.php') ?: '', 'FOREIGN KEY')
        && str_contains(file_get_contents($root . '/database/migrations/20261019_m07_other_question_link.php') ?: '', 'RESTRICT'),
    'other_question_mapping_and_capture_contract' => str_contains($service, 'authorizedOtherQuestion')
        && str_contains($service, 'function updateOtherMedication(')
        && str_contains($adapter, "other_action'] === 'create'")
        && str_contains($adapter, "other_action'] === 'update'")
        && str_contains($adapter, 'M07_OTHER_CREATE_REQUIRED'),
    'other_question_ui_uses_existing_capture_route' => str_contains(file_get_contents($root . '/app/views/paciente/entrevista.php') ?: '', 'data-m07-create-other')
        && str_contains(file_get_contents($root . '/app/views/paciente/entrevista.php') ?: '', 'data-m07-save-other')
        && str_contains(file_get_contents($root . '/app/Controllers/PatientInterviewController.php') ?: '', "'record' =>"),
];

if (in_array(false, $checks, true)) {
    fwrite(STDERR, "M07 shared canonical contract: FAIL\n" . json_encode($checks, JSON_PRETTY_PRINT) . "\n");
    exit(1);
}

echo 'M07 shared canonical contract: PASS (' . count($checks) . " static assertions; no database opened)\n";
