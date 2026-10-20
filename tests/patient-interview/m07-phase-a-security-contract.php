<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static function (string $relative) use ($root): string {
    $contents = file_get_contents($root . '/' . $relative);
    if (!is_string($contents)) throw new RuntimeException('No se pudo leer el contrato estático.');
    return $contents;
};
$section = static function (string $source, string $start, string $end = ''): string {
    $offset = strpos($source, $start);
    if ($offset === false) return '';
    $part = substr($source, $offset);
    if ($end !== '') {
        $endOffset = strpos($part, $end, strlen($start));
        if ($endOffset !== false) $part = substr($part, 0, $endOffset);
    }
    return $part;
};
$checks = [];
$check = static function (array &$checks, string $name, bool $ok): void {
    $checks[$name] = $ok ? 'PASS' : 'FAIL';
};

$controller = $read('app/Controllers/ModulosController.php');
$model = $read('app/Models/MedicacionDolorModel.php');
$patientView = $read('app/views/historia-clinica-paciente/medicacion-control-dolor.php');
$doctorView = $read('app/views/historia-clinica-doctor/medicacion-control-dolor.php');
$canonical = $read('app/Services/M07CanonicalService.php');

$editSection = $section($controller, 'public function pacienteEditarCuestionarioM7', 'public function ');
$finalizeSection = $section($controller, 'public function pacienteFinalizarModulo', 'public function ');
$readSection = $section($model, 'public function mostrarPreguntasM7Paciente', 'public function ');
$writeSection = $section($model, 'public function editarCuestionarioM7Paciente', 'public function ');
$gate = $read('app/Services/M07CanonicalActivationGate.php');

$check($checks, 'csrf_legacy_write_controller', str_contains($editSection, 'validCaptureCsrf') && str_contains($editSection, 'HTTP_X_M03_CSRF'));
$check($checks, 'csrf_legacy_finalize_controller', str_contains($finalizeSection, 'validCaptureCsrf') && str_contains($finalizeSection, 'HTTP_X_M03_CSRF'));
$check($checks, 'csrf_patient_write_frontend', str_contains($patientView, "'X-M03-CSRF': document.getElementById('main')?.dataset.m03Csrf"));
$check($checks, 'csrf_patient_finalize_frontend', substr_count($patientView, "'X-M03-CSRF': document.getElementById('main')?.dataset.m03Csrf") >= 2);
$check($checks, 'csrf_doctor_frontend', str_contains($doctorView, "'X-M03-CSRF': document.getElementById('main')?.dataset.m03Csrf"));
$check($checks, 'doctor_question_id_payload', str_contains($doctorView, 'idPregunta') && str_contains($model, 'data-id-pregunta=') && str_contains($model, 'pac_preguntas_modulo_7.id AS idPregunta'));
$check($checks, 'csrf_token_scoped_to_page', str_contains($patientView, 'data-m03-csrf=') && str_contains($doctorView, 'data-m03-csrf='));

$check($checks, 'legacy_extended_data_code', str_contains($model, 'M07_EXTENDED_DATA_REQUIRES_UPDATED_FORM'));
$check($checks, 'canonical_schema_fail_closed', str_contains($model, "'status' => 'canonical_schema'") && str_contains($model, 'pac_m07_operaciones'));
$check($checks, 'legacy_guard_before_write', strpos($writeSection, 'validateLegacyM07Write') !== false && strpos($writeSection, 'validateLegacyM07Write') < strpos($writeSection, 'INSERT INTO pac_respuestas_paciente_modulo_7'));
$check($checks, 'legacy_finalize_guard', str_contains($finalizeSection, 'validateLegacyM07Finalization') && str_contains($finalizeSection, '409'));
$check($checks, 'omitted_fields_not_converted', str_contains($model, 'm07_') && !str_contains($writeSection, "m07_respuesta_state = ''"));
$check($checks, 'read_has_no_initializer', !str_contains($readSection, 'medicacionDolorQuest(') && !str_contains($readSection, 'INSERT INTO'));
$check($checks, 'canonical_gate_remains_closed',
    str_contains($canonical, 'M07CanonicalActivationGate::fromTrustedServerConfiguration()')
    && str_contains($gate, 'getenv(\'M07_LOCAL_CANONICAL_ENABLED\') === \'1\'')
    && str_contains($gate, 'private bool $enabled = false')
    && str_contains($gate, 'private bool $syntheticScopeOnly = false'));

$failed = array_keys(array_filter($checks, static fn(string $status): bool => $status !== 'PASS'));
echo json_encode([
    'ok' => $failed === [],
    'status' => $failed === [] ? 'PASS' : 'FAIL',
    'checks' => $checks,
    'failed_checks' => $failed,
    'mysql_writes' => 'NO',
    'scope' => 'M07_PHASE_A_STATIC_ONLY',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failed === [] ? 0 : 1);
