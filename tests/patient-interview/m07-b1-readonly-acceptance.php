<?php

declare(strict_types=1);

/**
 * Read-only functional preparation for M07 B1.
 *
 * This runner intentionally does not create sessions, credentials, response
 * rows, snapshots, or clinical records. It exercises the shared canonical
 * read service and the two PHP renderers only after the synthetic scope has
 * been verified. Values from clinical rows never leave this process.
 */

ini_set('display_errors', '0');

$options = [];
foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--')) {
        continue;
    }
    [$key, $value] = array_pad(explode('=', $argument, 2), 2, '1');
    $options[substr($key, 2)] = $value;
}

$database = (string)($options['database'] ?? '');
$patientA = (int)($options['patient-a'] ?? 0);
$patientB = (int)($options['patient-b'] ?? 0);

$result = [
    'phase' => 'M07_B1_READ_ONLY_FUNCTIONAL_PREPARATION',
    'read_only' => true,
    'mysql_writes' => 'NO',
    'status' => 'NOT_RUN',
    'database_effective' => 'NOT_VERIFIED',
    'patient_scope' => 'NOT_VERIFIED',
    'shared_service' => 'NOT_VERIFIED',
    'conversational_read' => 'NOT_VERIFIED',
    'p09_patient_read' => 'NOT_VERIFIED',
    'p09_doctor_read' => 'NOT_VERIFIED',
    'catalog_46_questions' => 'NOT_VERIFIED',
    'catalog_6_themes' => 'NOT_VERIFIED',
    'unknown_states_preserved' => 'NOT_VERIFIED',
    'pending_not_negative' => 'NOT_VERIFIED',
    'non_catalog_medications' => 'NOT_VERIFIED',
    'cross_clinic_isolation' => 'NOT_VERIFIED',
    'get_without_clinical_writes' => 'NOT_VERIFIED',
];

$finish = static function (array $payload, int $exitCode): never {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($exitCode);
};

$fail = static function (string $step, string $message) use (&$result, $finish): never {
    $result['status'] = 'BLOCKED_READ_ONLY_ACCEPTANCE';
    $result['first_failed_step'] = $step;
    $result['error_message_sanitized'] = $message;
    $finish($result, 2);
};

if ($database !== 'clinica_dolor_demo_20261010_final') {
    $fail('database_allowlist', 'La base no coincide con la única base sintética autorizada.');
}
if ($patientA !== 9301 || $patientB !== 9302) {
    $fail('patient_allowlist', 'Los pacientes deben ser exactamente 9301 y 9302.');
}

$root = dirname(__DIR__, 2);
$envPath = $root . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envPath)) {
    $fail('local_configuration', 'No existe la configuración local requerida.');
}

$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}
foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
    if (!array_key_exists($key, $env)) {
        $fail('local_configuration', 'Falta configuración local de conexión.');
    }
}
if (!in_array(strtolower((string)$env['DB_HOST']), ['127.0.0.1', 'localhost', '::1'], true)
    || (string)$env['DB_PORT'] !== '3306') {
    $fail('local_connection_guard', 'La conexión debe apuntar a MySQL local en el puerto 3306.');
}

try {
    $db = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $database),
        $env['DB_USERNAME'],
        $env['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (Throwable) {
    $fail('mysql_connection', 'No fue posible conectar con MySQL local.');
}

$activeDatabase = (string)$db->query('SELECT DATABASE()')->fetchColumn();
if ($activeDatabase !== $database) {
    $fail('database_identity', 'La base efectiva no coincide con la allowlist sintética.');
}
$result['database_effective'] = 'PASS';

require_once $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$tableExists = static function (PDO $connection, string $table): bool {
    $statement = $connection->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([$table]);
    return (int)$statement->fetchColumn() === 1;
};
$requiredTables = [
    'pc_paciente', 'clinicas', 'pac_temas_modulo_7', 'pac_preguntas_modulo_7',
    'pac_respuestas_paciente_modulo_7', 'pac_m07_medicamentos_no_catalogados',
    'pac_m07_operaciones', 'pac_m07_revision_clinica',
];
foreach ($requiredTables as $table) {
    if (!$tableExists($db, $table)) {
        $fail('schema_guard', 'Falta una tabla requerida del contrato M07.');
    }
}

$scopeStatement = $db->prepare('SELECT id, id_clinica FROM pc_paciente WHERE id IN (?, ?) ORDER BY id');
$scopeStatement->execute([$patientA, $patientB]);
$scopeRows = $scopeStatement->fetchAll(PDO::FETCH_ASSOC);
$scopeByPatient = [];
foreach ($scopeRows as $row) {
    $scopeByPatient[(int)$row['id']] = (int)$row['id_clinica'];
}
if ($scopeByPatient !== [$patientA => 9101, $patientB => 9102]) {
    $fail('patient_clinic_scope', 'La pertenencia paciente-clínica no coincide con los fixtures autorizados.');
}
$result['patient_scope'] = 'PASS_9301_9101_9302_9102';

$digest = static function (PDO $connection) use ($patientA, $patientB): string {
    $definitions = [
        'pac_respuestas_paciente_modulo_7' => ['id_paciente IN (?, ?)', [$patientA, $patientB]],
        'pac_m07_medicamentos_no_catalogados' => [
            '(id_paciente = ? AND id_clinica = ?) OR (id_paciente = ? AND id_clinica = ?)',
            [$patientA, 9101, $patientB, 9102],
        ],
        'pac_m07_operaciones' => [
            '(id_paciente = ? AND id_clinica = ?) OR (id_paciente = ? AND id_clinica = ?)',
            [$patientA, 9101, $patientB, 9102],
        ],
        'pac_m07_revision_clinica' => [
            '(id_paciente = ? AND id_clinica = ?) OR (id_paciente = ? AND id_clinica = ?)',
            [$patientA, 9101, $patientB, 9102],
        ],
    ];
    $rows = [];
    foreach ($definitions as $table => [$where, $parameters]) {
        $statement = $connection->prepare("SELECT * FROM {$table} WHERE {$where} ORDER BY id");
        $statement->execute($parameters);
        $rows[$table] = $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
};
$beforeDigest = $digest($db);

$service = new App\Services\M07CanonicalService($db, new App\Services\M07CanonicalActivationGate(false));
$adapter = new App\Services\ConversationalM07Adapter($db);
$model = new App\Models\MedicacionDolorModel($db);
$expectedStateKeys = ['respuesta', 'dosis', 'resultados', 'consumo', 'descripcion', 'eficacia', 'tolerabilidad', 'efectos_descripcion'];
$themeIds = [];

foreach ([$patientA => 9101, $patientB => 9102] as $patientId => $clinicId) {
    $canonicalPatient = $service->read($patientId, $clinicId, 'patient');
    $canonicalDoctor = $service->read($patientId, $clinicId, 'doctor');
    if (($canonicalPatient['version'] ?? '') !== ($canonicalDoctor['version'] ?? '')) {
        $fail('shared_service_version', 'La lectura por rol no conserva la misma versión canónica.');
    }

    $records = $canonicalPatient['records'] ?? [];
    if (count($records) !== 46) {
        $fail('catalog_question_count', 'El catálogo canónico no contiene exactamente 46 preguntas.');
    }
    foreach ($records as $record) {
        if (($record['source'] ?? '') !== 'CATALOG'
            || (int)($record['patient_id'] ?? 0) !== $patientId
            || (int)($record['clinic_id'] ?? 0) !== $clinicId
            || (int)($record['question_id'] ?? 0) < 1
            || !array_key_exists('states', $record)
            || array_diff($expectedStateKeys, array_keys((array)$record['states'])) !== []) {
            $fail('canonical_record_projection', 'Una lectura canónica no conserva su identidad o sus estados.');
        }
        $themeIds[(int)$record['theme_id']] = true;
        if (($record['status'] ?? '') === 'pending'
            && trim((string)($record['fields']['respuesta'] ?? '')) === 'No'
            && (string)($record['states']['respuesta'] ?? '') !== 'CONFIRMED') {
            $fail('pending_negative_projection', 'Una entrada pendiente fue proyectada como negativa no confirmada.');
        }
        if (($record['status'] ?? '') === 'pending' && trim((string)($record['pending_reason'] ?? '')) === '') {
            $fail('pending_state_reason', 'Una entrada pendiente no conserva su motivo canónico.');
        }
    }
    foreach ($canonicalPatient['other_medications'] ?? [] as $other) {
        if (($other['source'] ?? '') !== 'OTHER'
            || (int)($other['patient_id'] ?? 0) !== $patientId
            || (int)($other['clinic_id'] ?? 0) !== $clinicId
            || ($other['question_id'] ?? null) !== null) {
            $fail('other_medication_scope', 'Un medicamento no catalogado salió del alcance sintético autorizado.');
        }
    }

    $conversational = $adapter->read($patientId, $clinicId, 'patient');
    if (($conversational['canonical_read'] ?? false) !== true
        || ($conversational['write_enabled'] ?? true) !== false
        || ($conversational['finalization_enabled'] ?? true) !== false
        || ($conversational['version'] ?? '') !== ($canonicalPatient['version'] ?? '')) {
        $fail('conversational_projection', 'La entrevista no conserva la lectura canónica o sus compuertas cerradas.');
    }

    foreach (array_keys($themeIds) as $themeId) {
        $patientHtml = $model->mostrarPreguntasM7Paciente($patientId, 0, $themeId);
        $doctorHtml = $model->mostrarPreguntasM7($patientId, 'Doctor', $themeId);
        if (!str_contains($patientHtml, 'data-m07-canonical-read-only="1"')
            || !str_contains($doctorHtml, 'data-m07-canonical-read-only="1"')) {
            $fail('p09_read_projection', 'La vista P09 no expone la lectura canónica en solo lectura.');
        }
    }
}

if (count($themeIds) !== 6) {
    $fail('catalog_theme_count', 'El catálogo canónico no contiene exactamente seis categorías.');
}
$result['shared_service'] = 'PASS';
$result['conversational_read'] = 'PASS_CANONICAL_READ_ONLY';
$result['p09_patient_read'] = 'PASS_CANONICAL_READ_ONLY';
$result['p09_doctor_read'] = 'PASS_CANONICAL_READ_ONLY';
$result['catalog_46_questions'] = 'PASS';
$result['catalog_6_themes'] = 'PASS';
$result['unknown_states_preserved'] = 'PASS_STATES_PRESENT';
$result['pending_not_negative'] = 'PASS';
$result['non_catalog_medications'] = 'PASS_SCOPED_OR_EMPTY';

foreach ([[$patientA, 9102], [$patientB, 9101]] as [$patientId, $wrongClinic]) {
    try {
        $service->read($patientId, $wrongClinic, 'patient');
        $fail('cross_clinic_isolation', 'La lectura cruzada no fue rechazada.');
    } catch (App\Services\M07CanonicalException $exception) {
        if ($exception->codeName !== 'M07_CLINIC_DENIED') {
            $fail('cross_clinic_isolation', 'La lectura cruzada produjo un rechazo distinto al contrato.');
        }
    }
}
$result['cross_clinic_isolation'] = 'PASS_SCOPE_DENIED';

$afterDigest = $digest($db);
if (!hash_equals($beforeDigest, $afterDigest)) {
    $fail('read_write_integrity', 'La lectura B1 alteró el estado sintético cubierto.');
}
$result['get_without_clinical_writes'] = 'PASS_DIGEST_UNCHANGED';
$result['status'] = 'PASS_B1_READ_ONLY';
$finish($result, 0);

