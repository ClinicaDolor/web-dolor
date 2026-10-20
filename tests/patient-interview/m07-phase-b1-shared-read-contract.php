<?php

declare(strict_types=1);

/**
 * Static B1 contract checks. This test never opens MySQL and never performs a
 * clinical write; it verifies that both read consumers use the same DTO path
 * when the canonical schema is available.
 */
$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';
$service = file_get_contents($root . '/app/Services/M07CanonicalService.php');
$record = file_get_contents($root . '/app/Services/M07CanonicalRecord.php');
$adapter = file_get_contents($root . '/app/Services/ConversationalM07Adapter.php');
$model = file_get_contents($root . '/app/Models/MedicacionDolorModel.php');
$clinicalDetail = file_get_contents($root . '/app/Services/ClinicalDetailService.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$acceptance = file_get_contents($root . '/tests/patient-interview/m07-b1-readonly-acceptance.php');
$allowlist = file_get_contents($root . '/tests/patient-interview/m07-b1-allowlist-regression.php');
$probe = file_get_contents($root . '/tests/patient-interview/m07-b1-cli-argv-probe.php');
$runner = file_get_contents($root . '/tests/patient-interview/run-m07-b1-readonly-medium.ps1');

$checks = [
    'canonical_service_read_contract' => is_string($service) && str_contains($service, 'public function read(int $patientId, int $clinicId, string $role): array'),
    'canonical_scope_guard' => is_string($service) && str_contains($service, "M07_CLINIC_DENIED") && str_contains($service, 'id_clinica'),
    'canonical_dto_states' => is_string($record) && str_contains($record, 'public readonly array $states = []') && str_contains($record, "'states' => \$this->states"),
    'adapter_uses_canonical_service' => is_string($adapter) && str_contains($adapter, 'new M07CanonicalService($this->connection())') && str_contains($adapter, 'readCanonical') && str_contains($adapter, 'captureCatalog('),
    'adapter_preserves_other_entries' => is_string($adapter) && str_contains($adapter, "'other_medications' => \$state['other_medications'] ?? []"),
    'adapter_defaults_to_closed_gate' => is_string($adapter) && str_contains($adapter, 'new M07CanonicalService($this->connection())') && str_contains($service, 'M07CanonicalActivationGate::fromTrustedServerConfiguration()') && str_contains($service, '?? M07CanonicalActivationGate::fromTrustedServerConfiguration()'),
    'application_gate_remains_closed' => (new App\Services\M07CanonicalActivationGate())->isEnabled() === false,
    'controller_passes_authenticated_clinic' => is_string($controller) && str_contains($controller, "(int)\$auth['id_clinica'], 'patient'"),
    'p09_model_uses_canonical_service' => is_string($model) && str_contains($model, 'new M07CanonicalService($this->bd)') && str_contains($model, 'canonicalM07Projection'),
    'doctor_summary_uses_canonical_service' => is_string($clinicalDetail) && str_contains($clinicalDetail, 'canonicalM07Module') && str_contains($clinicalDetail, 'new M07CanonicalService($this->db)'),
    'doctor_summary_preserves_non_catalogued_entries' => is_string($clinicalDetail) && str_contains($clinicalDetail, "'source_kind' => 'NON_CATALOGUED'") && str_contains($clinicalDetail, "'entry_key'"),
    'p09_read_only_projection' => is_string($model) && str_contains($model, 'data-m07-canonical-read-only') && str_contains($model, 'disabled'),
    'legacy_write_guard_preserved' => is_string($model) && str_contains($model, 'validateLegacyM07Write') && str_contains($model, 'M07_EXTENDED_DATA_REQUIRES_UPDATED_FORM'),
    'no_legacy_initializer_in_projection' => is_string($model) && str_contains($model, 'canonicalM07Projection') && !str_contains(substr($model, strpos($model, 'private function canonicalM07Projection'), 5000), 'INSERT INTO'),
    'six_theme_catalog_preserved' => is_string($model) && str_contains($model, 'obtenerModulosM7()') && is_string($adapter) && str_contains($adapter, "'categories' => array_values(\$categories)"),
    'readonly_acceptance_scoped' => is_string($acceptance) && str_contains($acceptance, "clinica_dolor_demo_20261010_final") && str_contains($acceptance, '9301') && str_contains($acceptance, '9302'),
    'allowlist_regression_is_database_free' => is_string($allowlist) && str_contains($allowlist, "'status' => 'PASS_ALLOWLIST_EXACT'") && !str_contains($allowlist, 'new PDO'),
    'allowlist_regression_rejects_real_database' => is_string($allowlist) && str_contains($allowlist, "'--database=bd_clinica'") && str_contains($allowlist, "'expected' => false"),
    'cli_probe_is_database_free' => is_string($probe) && str_contains($probe, "'status' => \$argumentsIndependent ? 'PASS_CLI_ARGUMENTS'") && !str_contains($probe, 'new PDO'),
    'readonly_acceptance_has_no_sql_writes' => is_string($acceptance) && !preg_match('/^\s*(INSERT|UPDATE|DELETE|ALTER|DROP|TRUNCATE)\s+/im', $acceptance),
    'readonly_runner_uses_approved_baseline' => is_string($runner) && str_contains($runner, 'm07-clinical-baseline-20261015T152934Z-7701ca5e.json') && str_contains($runner, '--mode=compare'),
    'readonly_runner_does_not_start_http' => is_string($runner) && !str_contains($runner, 'start-m07-apache') && !str_contains($runner, 'Invoke-WebRequest'),
    'readonly_runner_does_not_enable_writes' => is_string($runner) && !str_contains($runner, 'AllowSyntheticWrites') && str_contains($runner, "mysql_writes = 'NO'"),
    'readonly_runner_preserves_child_exit_code' => is_string($runner) && str_contains($runner, 'child_exit_code') && str_contains($runner, 'ExitCode = $exitCode'),
    'readonly_runner_preserves_sanitized_failure' => is_string($runner) && str_contains($runner, 'first_failed_condition') && str_contains($runner, 'root_cause_classification') && str_contains($runner, 'stderr_category'),
    'readonly_runner_reports_database_argument_match' => is_string($runner) && str_contains($runner, 'database_argument_match') && str_contains($runner, 'child_requested_database'),
    'readonly_runner_quotes_only_values_needing_it' => is_string($runner) && str_contains($runner, 'function Quote-ProcessArgument') && str_contains($runner, 'Quote-ProcessArgument $argument'),
    'readonly_runner_builds_functional_arguments_independently' => is_string($runner) && str_contains($runner, "('--database=' + [string]\$Database)") && str_contains($runner, "('--patient-a=' + [string]\$PatientA)") && str_contains($runner, "('--patient-b=' + [string]\$PatientB)"),
    'cli_probe_uses_actual_invocation_path' => is_string($runner) && str_contains($runner, '($common + @(') && str_contains($runner, "'cli-argv-probe-actual-path'") && str_contains($runner, 'actual_runner_invocation_path_tested'),
    'readonly_runner_does_not_emit_child_output' => is_string($runner) && !str_contains($runner, 'Write-Output $stdout') && !str_contains($runner, 'Write-Output $stderr'),
];

if (in_array(false, $checks, true)) {
    fwrite(STDERR, "M07 B1 shared read contract: FAIL\n" . json_encode($checks, JSON_PRETTY_PRINT) . "\n");
    exit(1);
}

echo 'M07 B1 shared read contract: PASS (' . count($checks) . " static assertions)\n";
