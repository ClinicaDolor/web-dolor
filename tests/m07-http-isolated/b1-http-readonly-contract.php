<?php

declare(strict_types=1);

/** Static contract for the B1 HTTP read-only preparation. No services, HTTP or DB. */
$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => is_file($path) ? (string)file_get_contents($path) : '';
$matrix = $read($root . '/tests/m07-http-isolated/m07-b1-http-readonly-matrix.php');
$runner = $read($root . '/tests/m07-http-isolated/run-m07-b1-http-readonly-medium.ps1');
$start = $read($root . '/tests/m07-http-isolated/start-m07-apache.ps1');
$stopper = $read($root . '/tests/m07-http-isolated/stop-m07-apache.ps1');
$cleanupFocal = $read($root . '/tests/m07-http-isolated/b1-apache-pid-cleanup-focal.ps1');
$a5 = $read($root . '/tests/m07-http-isolated/run-m07-http-matrix-medium.ps1');
$routes = $read($root . '/app/Config/routes.php');
$adapter = $read($root . '/app/Services/ConversationalM07Adapter.php');
$service = $read($root . '/app/Services/M07CanonicalService.php');
$model = $read($root . '/app/Models/MedicacionDolorModel.php');
$checks = [
    'matrix_exists' => $matrix !== '',
    'runner_exists' => $runner !== '',
    'cleanup_focal_exists' => $cleanupFocal !== '',
    'apache_scripts_no_reserved_pid_assignment' => preg_match('/^\s*\$pid\s*=/im', $runner . $start . $stopper . $a5) === 0,
    'runner_reports_cleanup_separately' => str_contains($runner, 'CLEANUP_FAILURE') && str_contains($runner, 'INTEGRITY_FAILURE'),
    'b1_preflight_status_matches_validator' => str_contains($runner, "PASS_READ_ONLY_EXISTING_SCHEMA") && !str_contains($runner, "PASS_READ_ONLY_PREFLIGHT"),
    'b1_failure_stage_observability' => str_contains($runner, 'FIRST_FAILED_STAGE') && str_contains($runner, 'LAST_COMPLETED_CHECKPOINT'),
    'exact_database_allowlist' => str_contains($matrix, "clinica_dolor_demo_20261010_final") && str_contains($runner, "clinica_dolor_demo_20261010_final"),
    'authorized_patients_and_clinics' => str_contains($matrix, '$patientA !== 9301') && str_contains($matrix, '$patientB !== 9302') && str_contains($matrix, '9101') && str_contains($matrix, '9102'),
    'doctor_identity_claims' => str_contains($matrix, '9201') && str_contains($matrix, '9202') && str_contains($matrix, 'id_clinica'),
    'interview_route' => str_contains($matrix, '/historia-clinica/entrevista'),
    'p09_patient_route' => str_contains($matrix, '/historia-clinica/medicacion-control-dolor/paciente/'),
    'p09_doctor_route' => str_contains($matrix, '/clinica/medicacion-control-dolor/paciente/'),
    'p09_content_route' => str_contains($matrix, '/buscar/contenido-preguntas-modulo-7/'),
    'only_login_posts' => !str_contains($matrix, 'capturar-m07') && !str_contains($matrix, 'finalizar-modulo-paciente') && !str_contains($matrix, 'editar-cuestionario'),
    'clinical_get_digest' => str_contains($matrix, 'clinicalState') && str_contains($matrix, 'SELECT * FROM'),
    'effective_database_guard' => str_contains($matrix, 'SELECT DATABASE()'),
    'cross_clinic_rejection' => str_contains($matrix, "'cross_clinic_patient_get_rejected'") && str_contains($matrix, "'cross_clinic_doctor_get_rejected'"),
    'not_covered_supported' => str_contains($matrix, "'NOT_COVERED'") && str_contains($matrix, 'unknown_states_coverage'),
    'canonical_adapter_static' => str_contains($adapter, 'new M07CanonicalService($db)') && str_contains($adapter, "'write_enabled' => false"),
    'canonical_service_static' => str_contains($service, 'function read(') && str_contains($service, 'SELECT DATABASE') === false,
    'p09_model_static' => str_contains($model, 'new M07CanonicalService($this->bd)') && str_contains($model, 'data-m07-canonical-read-only'),
    'routes_static' => str_contains($routes, "addGroup('/historia-clinica'") && str_contains($routes, "addRoute('GET', '/entrevista'") && str_contains($routes, '/buscar/contenido-preguntas-modulo-7/'),
    'a5_not_modified_by_contract' => $a5 !== '' && str_contains($a5, 'm07-http-negative-matrix.php'),
    'activation_gate_not_enabled' => !str_contains($matrix, 'M07CanonicalActivationGate(true)') && !str_contains($runner, 'M07CanonicalActivationGate(true)'),
];
$failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
if ($failed !== []) {
    fwrite(STDERR, "M07 B1 HTTP read-only contract: FAIL\n" . json_encode(['failed' => $failed], JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}
echo 'M07 B1 HTTP read-only contract: PASS (' . count($checks) . " static assertions)\n";
