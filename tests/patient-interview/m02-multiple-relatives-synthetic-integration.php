<?php
declare(strict_types=1);

use App\Config\Database;
use App\Services\ClinicalDetailService;
use App\Services\ClinicalNarrativeService;
use App\Services\ClinicalSnapshotService;
use App\Services\ConversationalM02Adapter;
use App\Services\ConversationalModuleFinalizationService;
use Dotenv\Dotenv;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
const M02_SYNTHETIC_DB = 'clinica_dolor_demo_20261010_final';
const M02_TARGET_DISEASE = 'Hipertensión arterial sistémica';
const M02_CONCURRENCY_DISEASE = 'Cáncer';
const M02_LEGACY_DISEASE = 'Enfermedad cerebrovascular';
const M02_LEGACY_TEXT = 'dato histórico sin clasificación & referencia antigua';
const M02_TARGET_PATIENT = 9302;
const M02_TEST_RELATIVE_SETS = [
    ['padre'], ['madre'], ['padre','madre'], ['padre','hermano'], ['madre','hermano'], ['padre','madre','hermano'],
];

/** Test-process-only PDO statement fault injector; never loaded by HTTP. */
final class M02FailureInjectionStatement extends PDOStatement
{
    private static ?string $armedNeedle = null;
    private static bool $wasTriggered = false;

    protected function __construct() {}

    public static function arm(string $sqlNeedle): void
    {
        self::$armedNeedle = $sqlNeedle;
        self::$wasTriggered = false;
    }

    public static function triggered(): bool
    {
        return self::$wasTriggered;
    }

    public static function disarm(): void
    {
        self::$armedNeedle = null;
    }

    public function execute(?array $params = null): bool
    {
        if (self::$armedNeedle !== null && stripos($this->queryString, self::$armedNeedle) !== false) {
            self::$armedNeedle = null;
            self::$wasTriggered = true;
            throw new PDOException('M02_TEST_INJECTED_CHILD_STATEMENT_FAILURE');
        }
        return parent::execute($params);
    }
}

function m02IntegrationPdo(): PDO
{
    global $root;
    Dotenv::createImmutable($root)->load();
    $host = strtolower(trim((string)($_ENV['DB_HOST'] ?? '')));
    $port = (string)($_ENV['DB_PORT'] ?? '3306');
    if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true) || $port !== '3306') {
        throw new RuntimeException('LOCAL_CONNECTION_NOT_ALLOWED');
    }
    $_ENV['DB_DATABASE'] = M02_SYNTHETIC_DB;
    $_SERVER['DB_DATABASE'] = M02_SYNTHETIC_DB;
    $pdo = Database::getInstance();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [M02FailureInjectionStatement::class, []]);
    if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== M02_SYNTHETIC_DB) {
        throw new RuntimeException('EFFECTIVE_DATABASE_MISMATCH');
    }
    $portCheck = (int)$pdo->query('SELECT @@port')->fetchColumn();
    if ($portCheck !== 3306) throw new RuntimeException('MYSQL_PORT_MISMATCH');
    return $pdo;
}

function m02AssertSyntheticDatabase(PDO $pdo): void
{
    if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== M02_SYNTHETIC_DB) {
        throw new RuntimeException('EFFECTIVE_DATABASE_MISMATCH_BEFORE_DML');
    }
}

function m02IntegrationFail(string $code): never
{
    throw new RuntimeException($code);
}

function m02RowsFingerprint(PDO $pdo, array $patientIds): string
{
    $marks = implode(',', array_fill(0, count($patientIds), '?'));
    $parents = $pdo->prepare("SELECT id,id_paciente,enfermedad,tipo,detalle,especificar FROM pc_antecedentes_familiares WHERE id_paciente IN ($marks) ORDER BY id");
    $parents->execute($patientIds);
    $parentRows = $parents->fetchAll(PDO::FETCH_ASSOC);
    $children = $pdo->prepare("SELECT c.id_antecedente,c.parentesco FROM pc_antecedentes_familiares_parentescos c INNER JOIN pc_antecedentes_familiares p ON p.id=c.id_antecedente WHERE p.id_paciente IN ($marks) ORDER BY c.id_antecedente,c.parentesco");
    $children->execute($patientIds);
    return hash('sha256', json_encode([
        'parents' => $parentRows,
        'children' => $children->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function m02ReadChildren(PDO $pdo, int $parentId): array
{
    $stmt = $pdo->prepare('SELECT parentesco FROM pc_antecedentes_familiares_parentescos WHERE id_antecedente=:id ORDER BY parentesco');
    $stmt->execute([':id' => $parentId]);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function m02FindDetailRecordByField(array $records, string $label, string $value): ?array
{
    foreach ($records as $record) {
        foreach (($record['groups'] ?? []) as $group) {
            foreach (($group['fields'] ?? []) as $field) {
                if (($field['label'] ?? null) === $label && ($field['value'] ?? null) === $value) return $record;
            }
        }
    }
    return null;
}

function m02Capture(ConversationalM02Adapter $adapter, int $patientId, string $disease, array $relatives, string $version): array
{
    return $adapter->capture($patientId, $disease, [
        'kind' => 'm02_read_only_proposal',
        'disease' => $disease,
        'detail' => 'Si',
        'relative' => count($relatives) === 1 ? $relatives[0] : null,
        'relatives' => $relatives,
        'diabetes_type' => null,
    ], $version);
}

function m02Worker(string $barrierDirectory, string $slot): never
{
    $pdo = m02IntegrationPdo();
    $adapter = new ConversationalM02Adapter($pdo);
    $state = $adapter->read(9302);
    if (empty($state['multi_relative_write_enabled'])) m02IntegrationFail('WORKER_WRITE_GATE_CLOSED');
    $record = null;
    foreach ($state['records'] as $candidate) if ($candidate['disease'] === M02_CONCURRENCY_DISEASE) $record = $candidate;
    if (!is_array($record) || $record['id'] !== null) m02IntegrationFail('WORKER_BASELINE_NOT_EMPTY');
    file_put_contents($barrierDirectory . DIRECTORY_SEPARATOR . 'ready-' . $slot . '.json', json_encode(['version' => $record['version']], JSON_THROW_ON_ERROR), LOCK_EX);
    $deadline = microtime(true) + 15;
    while (!is_file($barrierDirectory . DIRECTORY_SEPARATOR . 'go')) {
        if (microtime(true) >= $deadline) m02IntegrationFail('WORKER_BARRIER_TIMEOUT');
        usleep(20000);
    }
    $result = m02Capture($adapter, 9302, M02_CONCURRENCY_DISEASE, ['padre', 'madre'], (string)$record['version']);
    $payload = [
        'status' => (int)($result['resultado'] ?? 0),
        'readback' => (bool)($result['readback'] ?? false),
        'idempotent_replay' => (bool)($result['idempotent_replay'] ?? false),
        'record_id' => (int)($result['record']['id'] ?? 0),
    ];
    file_put_contents($barrierDirectory . DIRECTORY_SEPARATOR . 'result-' . $slot . '.json', json_encode($payload, JSON_THROW_ON_ERROR), LOCK_EX);
    echo json_encode(['worker' => $slot, 'status' => $payload['status']], JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($payload['status'] === 200 && $payload['readback'] ? 0 : 4);
}

if (($argv[1] ?? '') === '--worker') {
    try {
        m02Worker((string)($argv[2] ?? ''), (string)($argv[3] ?? ''));
    } catch (Throwable $exception) {
        echo json_encode(['worker' => 'FAIL', 'failure_code' => preg_replace('/[^A-Z0-9_]/', '', strtoupper($exception->getMessage())) ?: 'WORKER_FAILED'], JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(2);
    }
}

$phase = 'BOOTSTRAP';
$remainingOnly = in_array('--remaining-regressions', $argv, true);
$output = [
    'phase' => 'M02_MULTI_RELATIVES_SYNTHETIC_INTEGRATION',
    'run_scope' => $remainingOnly ? 'REMAINING_REGRESSIONS_ONLY' : 'FULL_SYNTHETIC_INTEGRATION',
    'database' => 'NOT_VERIFIED',
    'identities' => 'NOT_VERIFIED',
    'schema' => 'NOT_VERIFIED',
    'multi_capture_and_readback' => 'NOT_RUN',
    'idempotency' => 'NOT_RUN',
    'stale_version' => 'NOT_RUN',
    'concurrent_missing_parent' => 'NOT_RUN',
    'legacy_doctor_scalar_rejection' => 'NOT_RUN',
    'child_insert_failure' => 'NOT_RUN',
    'child_delete_failure' => 'NOT_RUN',
    'transaction_rollback' => 'NOT_RUN',
    'parent_row_preserved' => 'NOT_RUN',
    'child_set_preserved' => 'NOT_RUN',
    'no_false_save_or_conversation_advance' => 'NOT_RUN',
    'legacy_unknown_text_read' => 'NOT_RUN',
    'legacy_text_preserved' => 'NOT_RUN',
    'legacy_patient_projection' => 'NOT_RUN',
    'legacy_doctor_projection' => 'NOT_RUN',
    'legacy_clinical_summary' => 'NOT_RUN',
    'legacy_incompatible_update' => 'NOT_RUN',
    'negative_clears_relatives' => 'NOT_RUN',
    'rollback_atomicity' => 'NOT_RUN',
    'doctor_and_summary_projection' => 'NOT_RUN',
    'finalization_rule' => 'NOT_RUN',
    'baseline_reconciliation' => 'NOT_RUN',
    'temporary_parent_ids_created' => [],
    'temporary_parent_ids_restored' => [],
    'writes_scope' => 'M02_SYNTHETIC_PATIENTS_9301_9302_ONLY',
    'm07_gate' => 'CLOSED_UNCHANGED',
    'snapshot_path' => 'NOT_CREATED',
    'status' => 'BLOCKED',
];
$pdo = null;
$snapshotPath = null;
$baselineFingerprint = null;
$targetBaseline = null;
$legacyTestRowId = 0;
$targetTestRowId = 0;
$createdConcurrentId = 0;
$barrierDirectory = null;
$snapshotReady = false;
$workers = [];
$testOutcome = false;

try {
    $phase = 'SYNTHETIC_DATABASE_PREFLIGHT';
    $pdo = m02IntegrationPdo();
    $output['database'] = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $patientQuery = $pdo->prepare('SELECT id,id_clinica FROM pc_paciente WHERE id IN (9301,9302) ORDER BY id');
    $patientQuery->execute();
    $patients = $patientQuery->fetchAll(PDO::FETCH_KEY_PAIR);
    if (array_map('intval', $patients) !== [9301 => 9101, 9302 => 9102]) m02IntegrationFail('SYNTHETIC_PATIENT_CLINIC_SCOPE_MISMATCH');
    $output['identities'] = 'PASS_9301_CLINIC_9101_9302_CLINIC_9102';

    $engine = $pdo->prepare("SELECT table_name,engine FROM information_schema.TABLES WHERE table_schema=DATABASE() AND table_name IN ('pc_paciente','pc_antecedentes_familiares','pc_antecedentes_familiares_parentescos')");
    $engine->execute();
    $engines = array_column($engine->fetchAll(PDO::FETCH_ASSOC), 'engine', 'table_name');
    if (count($engines) !== 3 || count(array_filter($engines, static fn($value): bool => strtoupper((string)$value) === 'INNODB')) !== 3) m02IntegrationFail('M02_INNODB_SCHEMA_REQUIRED');
    $column = $pdo->query("SELECT column_type FROM information_schema.COLUMNS WHERE table_schema=DATABASE() AND table_name='pc_antecedentes_familiares' AND column_name='id'")->fetchColumn();
    if (strtolower((string)$column) !== 'int(11)') m02IntegrationFail('PARENT_KEY_TYPE_MISMATCH');
    $fk = $pdo->query("SELECT delete_rule FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE() AND table_name='pc_antecedentes_familiares_parentescos' AND constraint_name='fk_m02_antecedente_parentesco'")->fetchColumn();
    if (strtoupper((string)$fk) !== 'RESTRICT') m02IntegrationFail('CHILD_FOREIGN_KEY_POLICY_MISMATCH');
    $output['schema'] = 'PASS_INNODB_PK_FK_RESTRICT';

    $target = $pdo->prepare('SELECT id,id_paciente,enfermedad,tipo,detalle,especificar FROM pc_antecedentes_familiares WHERE id_paciente=:patient AND enfermedad=:disease ORDER BY id');
    $target->execute([':patient' => M02_TARGET_PATIENT, ':disease' => M02_TARGET_DISEASE]);
    $targetRows = $target->fetchAll(PDO::FETCH_ASSOC);
    if (count($targetRows) > 1) m02IntegrationFail('TARGET_BASELINE_DUPLICATE_ROWS');
    $targetBaseline = $targetRows[0] ?? null;
    $fixedCancer = $pdo->prepare('SELECT id FROM pc_antecedentes_familiares WHERE id_paciente=9302 AND enfermedad=:disease ORDER BY id');
    $fixedCancer->execute([':disease' => M02_CONCURRENCY_DISEASE]);
    $patientBExisting = array_map('intval', $fixedCancer->fetchAll(PDO::FETCH_COLUMN));
    if ($patientBExisting !== []) m02IntegrationFail('CONCURRENT_FIXTURE_DISEASE_ALREADY_EXISTS');
    $legacyCount = $pdo->prepare('SELECT COUNT(*) FROM pc_antecedentes_familiares WHERE id_paciente=:patient AND enfermedad=:disease');
    $legacyCount->execute([':patient'=>M02_TARGET_PATIENT, ':disease'=>M02_LEGACY_DISEASE]);
    if ((int)$legacyCount->fetchColumn() !== 0) m02IntegrationFail('LEGACY_TEXT_FIXTURE_COLLISION');
    $finalized = $pdo->prepare('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo=2 AND id_paciente=:patient');
    $finalized->execute([':patient' => M02_TARGET_PATIENT]);
    if ((int)$finalized->fetchColumn() !== 0) m02IntegrationFail('M02_TARGET_FIXTURE_ALREADY_FINALIZED');

    $targetChildren = [];
    if ($targetBaseline !== null) {
        $children = $pdo->prepare('SELECT id_antecedente,parentesco FROM pc_antecedentes_familiares_parentescos WHERE id_antecedente=:id ORDER BY parentesco');
        $children->execute([':id' => (int)$targetBaseline['id']]);
        $targetChildren = $children->fetchAll(PDO::FETCH_ASSOC);
    }
    $patientIds = [9301, 9302];
    $baselineFingerprint = m02RowsFingerprint($pdo, $patientIds);
    $localData = (string)(getenv('LOCALAPPDATA') ?: '');
    if ($localData === '' || !is_dir($localData)) m02IntegrationFail('PRIVATE_LOCAL_APPDATA_UNAVAILABLE');
    $snapshotDirectory = $localData . DIRECTORY_SEPARATOR . 'ClinicaDolor' . DIRECTORY_SEPARATOR . 'M02-multiple-relatives';
    if (!is_dir($snapshotDirectory) && !mkdir($snapshotDirectory, 0700, true) && !is_dir($snapshotDirectory)) m02IntegrationFail('DURABLE_SNAPSHOT_DIRECTORY_UNAVAILABLE');
    $snapshotPath = $snapshotDirectory . DIRECTORY_SEPARATOR . 'm02-baseline-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(4)) . '.json';
    $snapshot = [
        'schema' => 'm02-synthetic-baseline.v1',
        'created_at_utc' => gmdate('c'),
        'database' => M02_SYNTHETIC_DB,
        'patient_clinics' => ['9301' => 9101, '9302' => 9102],
        'target_parent' => $targetBaseline,
        'target_children' => $targetChildren,
        'legacy_parent' => null,
        'patients_m02_fingerprint' => $baselineFingerprint,
    ];
    if (file_put_contents($snapshotPath, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) m02IntegrationFail('DURABLE_SNAPSHOT_WRITE_FAILED');
    $snapshotCopy = json_decode((string)file_get_contents($snapshotPath), true, 64, JSON_THROW_ON_ERROR);
    if (($snapshotCopy['database'] ?? '') !== M02_SYNTHETIC_DB || ($snapshotCopy['target_parent']['id'] ?? null) !== ($targetBaseline['id'] ?? null) || ($snapshotCopy['legacy_parent'] ?? null) !== null) m02IntegrationFail('DURABLE_SNAPSHOT_READBACK_FAILED');
    $snapshotReady = true;
    $output['snapshot_path'] = $snapshotPath;

    if ($remainingOnly) {
        $phase = 'REMAINING_REGRESSION_SETUP';
        m02AssertSyntheticDatabase($pdo);
        $adapter = new ConversationalM02Adapter($pdo);
        $state = $adapter->read(M02_TARGET_PATIENT);
        if (empty($state['multi_relative_write_enabled'])) m02IntegrationFail('M02_SYNTHETIC_WRITE_GUARD_CLOSED');
        $record = null;
        foreach ($state['records'] as $candidate) if ($candidate['disease'] === M02_TARGET_DISEASE) $record = $candidate;
        if (!is_array($record) || (($targetBaseline === null) ? $record['id'] !== null : (int)$record['id'] !== (int)$targetBaseline['id'])) m02IntegrationFail('TARGET_READBACK_SCOPE_MISMATCH');
        $setup = m02Capture($adapter, M02_TARGET_PATIENT, M02_TARGET_DISEASE, ['madre','padre'], (string)$record['version']);
        if ((int)($setup['resultado'] ?? 0) !== 200 || empty($setup['readback'])) m02IntegrationFail('REMAINING_REGRESSION_SETUP_READBACK_FAILED');
        $record = $setup['record'];
        if ($targetBaseline === null && (int)($record['id'] ?? 0) > 0) {
            $targetTestRowId = (int)$record['id'];
            $output['temporary_parent_ids_created'][] = $targetTestRowId;
        }

        $phase = 'INJECTED_CHILD_STATEMENT_FAILURES';
        m02AssertSyntheticDatabase($pdo);
        $beforeChildInsertFailure = m02RowsFingerprint($pdo, $patientIds);
        M02FailureInjectionStatement::arm('INSERT INTO pc_antecedentes_familiares_parentescos');
        try {
            $insertFailure = m02Capture($adapter, M02_TARGET_PATIENT, M02_TARGET_DISEASE, ['madre','padre','hermano'], (string)$record['version']);
        } finally {
            $insertTriggered = M02FailureInjectionStatement::triggered();
            M02FailureInjectionStatement::disarm();
        }
        if (!$insertTriggered || (int)($insertFailure['resultado'] ?? 0) !== 422 || !empty($insertFailure['readback'])) m02IntegrationFail('CHILD_INSERT_FAILURE_NOT_REJECTED_WITHOUT_READBACK');
        if (m02RowsFingerprint($pdo, $patientIds) !== $beforeChildInsertFailure) m02IntegrationFail('CHILD_INSERT_FAILURE_DID_NOT_ROLL_BACK_ALL_ROWS');
        $output['child_insert_failure'] = 'PASS_INJECTED_PDO_EXCEPTION_ROLLBACK';
        $output['transaction_rollback'] = 'PASS_INSERT_FAILURE_ROLLED_BACK';
        $output['parent_row_preserved'] = 'PASS_INSERT_FAILURE_FINGERPRINT_UNCHANGED';
        $output['child_set_preserved'] = 'PASS_INSERT_FAILURE_FINGERPRINT_UNCHANGED';

        m02AssertSyntheticDatabase($pdo);
        $beforeChildDeleteFailure = m02RowsFingerprint($pdo, $patientIds);
        M02FailureInjectionStatement::arm('DELETE FROM pc_antecedentes_familiares_parentescos');
        try {
            $deleteFailure = m02Capture($adapter, M02_TARGET_PATIENT, M02_TARGET_DISEASE, ['madre'], (string)$record['version']);
        } finally {
            $deleteTriggered = M02FailureInjectionStatement::triggered();
            M02FailureInjectionStatement::disarm();
        }
        if (!$deleteTriggered || (int)($deleteFailure['resultado'] ?? 0) !== 422 || !empty($deleteFailure['readback'])) m02IntegrationFail('CHILD_DELETE_FAILURE_NOT_REJECTED_WITHOUT_READBACK');
        if (m02RowsFingerprint($pdo, $patientIds) !== $beforeChildDeleteFailure) m02IntegrationFail('CHILD_DELETE_FAILURE_DID_NOT_ROLL_BACK_ALL_ROWS');
        $output['child_delete_failure'] = 'PASS_INJECTED_PDO_EXCEPTION_ROLLBACK';
        $output['transaction_rollback'] = 'PASS_INSERT_AND_DELETE_FAILURES_ROLLED_BACK';
        $output['parent_row_preserved'] = 'PASS_BOTH_FAILURE_FINGERPRINTS_UNCHANGED';
        $output['child_set_preserved'] = 'PASS_BOTH_FAILURE_FINGERPRINTS_UNCHANGED';
        $output['no_false_save_or_conversation_advance'] = 'PASS_ERROR_WITHOUT_READBACK_OR_MUTATION';

        $phase = 'LEGACY_UNRECOGNIZED_TEXT_COMPATIBILITY';
        m02AssertSyntheticDatabase($pdo);
        $insertLegacy = $pdo->prepare("INSERT INTO pc_antecedentes_familiares (id_paciente,enfermedad,tipo,detalle,especificar) VALUES (:patient,:disease,'','Si',:legacy_text)");
        $insertLegacy->execute([':patient'=>M02_TARGET_PATIENT, ':disease'=>M02_LEGACY_DISEASE, ':legacy_text'=>M02_LEGACY_TEXT]);
        $legacyTestRowId = (int)$pdo->lastInsertId();
        if ($legacyTestRowId < 1) m02IntegrationFail('LEGACY_TEMP_ROW_ID_NOT_CAPTURED');
        $output['temporary_parent_ids_created'][] = $legacyTestRowId;
        $legacyState = (new ConversationalM02Adapter($pdo))->read(M02_TARGET_PATIENT);
        $legacyRecord = null;
        foreach ($legacyState['records'] as $candidate) if ($candidate['disease'] === M02_LEGACY_DISEASE) $legacyRecord = $candidate;
        if (!is_array($legacyRecord) || ($legacyRecord['legacy_relative_text'] ?? null) !== M02_LEGACY_TEXT || ($legacyRecord['relative'] ?? null) !== M02_LEGACY_TEXT || ($legacyRecord['relatives'] ?? []) !== []) m02IntegrationFail('LEGACY_ADAPTER_READ_CHANGED_OR_INFERRED_TEXT');
        if (m02ReadChildren($pdo, $legacyTestRowId) !== []) m02IntegrationFail('LEGACY_READ_CREATED_CHILD_ROWS');

        $model = new App\Models\AntecedenteFamiliarModel();
        $doctorLegacyHtml = $model->mostrarPreguntasM2(M02_TARGET_PATIENT, 'Doctor');
        if (!str_contains($doctorLegacyHtml, htmlspecialchars(M02_LEGACY_TEXT, ENT_QUOTES, 'UTF-8'))) m02IntegrationFail('LEGACY_DOCTOR_PROJECTION_LOST_TEXT');
        $legacyDetail = (new ClinicalDetailService($pdo))->build(M02_TARGET_PATIENT);
        $legacyDetailJson = json_encode($legacyDetail['modules']['M02']['records'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (!str_contains($legacyDetailJson, M02_LEGACY_TEXT)) m02IntegrationFail('LEGACY_PATIENT_DETAIL_LOST_TEXT');
        $legacySnapshot = (new ClinicalSnapshotService($pdo))->build(M02_TARGET_PATIENT);
        $legacySnapshotJson = json_encode($legacySnapshot['modules'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (!str_contains($legacySnapshotJson, M02_LEGACY_TEXT)) m02IntegrationFail('LEGACY_SNAPSHOT_LOST_TEXT');
        $legacyNarrativeRecord = m02FindDetailRecordByField($legacyDetail['modules']['M02']['records'] ?? [], 'Enfermedad', M02_LEGACY_DISEASE);
        if (!is_array($legacyNarrativeRecord)) m02IntegrationFail('LEGACY_NARRATIVE_INPUT_NOT_FOUND');
        $legacyNarrative = (new ClinicalNarrativeService())->build(['modules'=>['M02'=>['records'=>[$legacyNarrativeRecord]]]], []);
        $legacyNarrativeJson = json_encode($legacyNarrative, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (!str_contains($legacyNarrativeJson, M02_LEGACY_TEXT) || preg_match('/\\b(?:madre|padre|hermana|hermano|abuela|abuelo|hija|hijo)\\b/u', $legacyNarrativeJson) === 1) m02IntegrationFail('LEGACY_NARRATIVE_INFERRED_RELATION');
        $legacyBeforeInvalidUpdate = m02RowsFingerprint($pdo, $patientIds);
        $invalidLegacyUpdate = $adapter->capture(M02_TARGET_PATIENT, M02_LEGACY_DISEASE, ['detail'=>'Si','relative'=>'pariente-no-catalogado','relatives'=>['pariente-no-catalogado'],'diabetes_type'=>null], (string)$legacyRecord['version']);
        if ((int)($invalidLegacyUpdate['resultado'] ?? 0) !== 422 || m02RowsFingerprint($pdo, $patientIds) !== $legacyBeforeInvalidUpdate) m02IntegrationFail('INVALID_LEGACY_UPDATE_OVERWROTE_SOURCE');
        $output['legacy_unknown_text_read'] = 'PASS_UNRECOGNIZED_TEXT_NOT_PARSED';
        $output['legacy_text_preserved'] = 'PASS_EXACT_IN_ADAPTER_AND_STORAGE';
        $output['legacy_patient_projection'] = 'PASS_DETAIL_AND_SNAPSHOT';
        $output['legacy_doctor_projection'] = 'PASS_ESCAPED_HTML_TEXT';
        $output['legacy_clinical_summary'] = 'PASS_TEXT_WITHOUT_INFERRED_RELATION';
        $output['legacy_incompatible_update'] = 'PASS_REJECTED_WITHOUT_OVERWRITE';
        $testOutcome = true;
        $output['status'] = 'PASS_TESTS_RESTORATION_PENDING';
    } else {

    $phase = 'STRUCTURED_CAPTURE';
    m02AssertSyntheticDatabase($pdo);
    $adapter = new ConversationalM02Adapter($pdo);
    $state = $adapter->read(M02_TARGET_PATIENT);
    if (empty($state['multi_relative_write_enabled'])) m02IntegrationFail('M02_SYNTHETIC_WRITE_GUARD_CLOSED');
    $record = null;
    foreach ($state['records'] as $candidate) if ($candidate['disease'] === M02_TARGET_DISEASE) $record = $candidate;
    if (!is_array($record) || (($targetBaseline === null) ? $record['id'] !== null : (int)$record['id'] !== (int)$targetBaseline['id'])) m02IntegrationFail('TARGET_READBACK_SCOPE_MISMATCH');
    $captureCases = M02_TEST_RELATIVE_SETS;
    $last = null;
    foreach ($captureCases as $set) {
        $last = m02Capture($adapter, M02_TARGET_PATIENT, M02_TARGET_DISEASE, $set, (string)$record['version']);
        if ((int)($last['resultado'] ?? 0) !== 200 || empty($last['readback'])) m02IntegrationFail('CAPTURE_OR_READBACK_FAILED');
        $saved = $last['record']['relatives'] ?? [];
        sort($saved, SORT_STRING);
        $expected = $set;
        sort($expected, SORT_STRING);
        if ($saved !== $expected || (int)($last['record']['id'] ?? 0) < 1) m02IntegrationFail('STRUCTURED_SET_MISMATCH');
        $record = $last['record'];
        if ($targetBaseline === null && (int)($record['id'] ?? 0) > 0 && $targetTestRowId === 0) {
            $targetTestRowId = (int)$record['id'];
            $output['temporary_parent_ids_created'][] = $targetTestRowId;
        }
    }
    $output['multi_capture_and_readback'] = 'PASS_ONE_TWO_THREE_RELATIVES';

    $phase = 'IDEMPOTENCY_AND_STALE_VERSION';
    $sameReplay = m02Capture($adapter, M02_TARGET_PATIENT, M02_TARGET_DISEASE, ['padre','madre','hermano'], (string)$record['version']);
    if ((int)($sameReplay['resultado'] ?? 0) !== 200 || empty($sameReplay['readback']) || empty($sameReplay['idempotent_replay'])) m02IntegrationFail('IDENTICAL_REPLAY_NOT_IDEMPOTENT');
    $preUpdateVersion = (string)$record['version'];
    $update = m02Capture($adapter, M02_TARGET_PATIENT, M02_TARGET_DISEASE, ['padre','madre'], $preUpdateVersion);
    if ((int)($update['resultado'] ?? 0) !== 200 || empty($update['readback'])) m02IntegrationFail('SET_UPDATE_FAILED');
    $stale = m02Capture($adapter, M02_TARGET_PATIENT, M02_TARGET_DISEASE, ['padre'], $preUpdateVersion);
    if ((int)($stale['resultado'] ?? 0) !== 409) m02IntegrationFail('STALE_VERSION_NOT_REJECTED');
    $record = $update['record'];
    $output['idempotency'] = 'PASS_IDENTICAL_REPLAY_NO_DUPLICATES';
    $output['stale_version'] = 'PASS_CONFLICT_WITHOUT_CHANGE';

    $phase = 'INJECTED_CHILD_STATEMENT_FAILURES';
    m02AssertSyntheticDatabase($pdo);
    $beforeChildInsertFailure = m02RowsFingerprint($pdo, $patientIds);
    M02FailureInjectionStatement::arm('INSERT INTO pc_antecedentes_familiares_parentescos');
    try {
        $insertFailure = m02Capture($adapter, M02_TARGET_PATIENT, M02_TARGET_DISEASE, ['padre','madre','hermano'], (string)$record['version']);
    } finally {
        $insertTriggered = M02FailureInjectionStatement::triggered();
        M02FailureInjectionStatement::disarm();
    }
    if (!$insertTriggered || (int)($insertFailure['resultado'] ?? 0) !== 422 || !empty($insertFailure['readback'])) m02IntegrationFail('CHILD_INSERT_FAILURE_NOT_REJECTED_WITHOUT_READBACK');
    if (m02RowsFingerprint($pdo, $patientIds) !== $beforeChildInsertFailure) m02IntegrationFail('CHILD_INSERT_FAILURE_DID_NOT_ROLL_BACK_ALL_ROWS');
    $output['child_insert_failure'] = 'PASS_INJECTED_PDO_EXCEPTION_ROLLBACK';
    $output['transaction_rollback'] = 'PASS_INSERT_FAILURE_ROLLED_BACK';
    $output['parent_row_preserved'] = 'PASS_INSERT_FAILURE_FINGERPRINT_UNCHANGED';
    $output['child_set_preserved'] = 'PASS_INSERT_FAILURE_FINGERPRINT_UNCHANGED';

    m02AssertSyntheticDatabase($pdo);
    $beforeChildDeleteFailure = m02RowsFingerprint($pdo, $patientIds);
    M02FailureInjectionStatement::arm('DELETE FROM pc_antecedentes_familiares_parentescos');
    try {
        $deleteFailure = m02Capture($adapter, M02_TARGET_PATIENT, M02_TARGET_DISEASE, ['madre'], (string)$record['version']);
    } finally {
        $deleteTriggered = M02FailureInjectionStatement::triggered();
        M02FailureInjectionStatement::disarm();
    }
    if (!$deleteTriggered || (int)($deleteFailure['resultado'] ?? 0) !== 422 || !empty($deleteFailure['readback'])) m02IntegrationFail('CHILD_DELETE_FAILURE_NOT_REJECTED_WITHOUT_READBACK');
    if (m02RowsFingerprint($pdo, $patientIds) !== $beforeChildDeleteFailure) m02IntegrationFail('CHILD_DELETE_FAILURE_DID_NOT_ROLL_BACK_ALL_ROWS');
    $output['child_delete_failure'] = 'PASS_INJECTED_PDO_EXCEPTION_ROLLBACK';
    $output['transaction_rollback'] = 'PASS_INSERT_AND_DELETE_FAILURES_ROLLED_BACK';
    $output['parent_row_preserved'] = 'PASS_BOTH_FAILURE_FINGERPRINTS_UNCHANGED';
    $output['child_set_preserved'] = 'PASS_BOTH_FAILURE_FINGERPRINTS_UNCHANGED';
    $output['no_false_save_or_conversation_advance'] = 'PASS_ERROR_WITHOUT_READBACK_OR_MUTATION';

    $phase = 'LEGACY_DOCTOR_PROJECTION_AND_REJECTION';
    $model = new App\Models\AntecedenteFamiliarModel();
    $html = $model->mostrarPreguntasM2(M02_TARGET_PATIENT, 'Doctor');
    if (!is_string($html) || !str_contains($html, 'padre') || !str_contains($html, 'madre')) m02IntegrationFail('DOCTOR_VIEW_DID_NOT_PROJECT_STRUCTURED_RELATIVES');
    $beforeRejectedEdit = m02RowsFingerprint($pdo, $patientIds);
    $rejectedEdit = $model->editarEnfermedadPaciente([
        'idPaciente' => M02_TARGET_PATIENT, 'idEnfermedad' => (int)$record['id'], 'enfermedad' => M02_TARGET_DISEASE,
        'edicion' => 4, 'detalle' => 'abuelo',
    ]);
    if ((int)($rejectedEdit['resultado'] ?? 0) !== 409) m02IntegrationFail('LEGACY_SCALAR_EDITOR_NOT_REJECTED');
    if (m02RowsFingerprint($pdo, $patientIds) !== $beforeRejectedEdit) m02IntegrationFail('UNEXPECTED_DOCTOR_EDIT');
    $output['legacy_doctor_scalar_rejection'] = 'PASS_MULTI_SET_PROTECTED';

    $phase = 'PATIENT_DOCTOR_SUMMARY_PROJECTIONS';
    $freshState = (new ConversationalM02Adapter($pdo))->read(M02_TARGET_PATIENT);
    $freshRecord = null;
    foreach ($freshState['records'] as $candidate) if ($candidate['disease'] === M02_TARGET_DISEASE) $freshRecord = $candidate;
    $freshRelatives = $freshRecord['relatives'] ?? [];
    sort($freshRelatives, SORT_STRING);
    if ($freshRelatives !== ['madre','padre']) m02IntegrationFail('F5_READBACK_SET_MISMATCH');
    $detail = (new ClinicalDetailService($pdo))->build(M02_TARGET_PATIENT);
    $m02Records = $detail['modules']['M02']['records'] ?? [];
    $m02Text = json_encode($m02Records, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (!str_contains($m02Text, 'padre') || !str_contains($m02Text, 'madre')) m02IntegrationFail('CLINICAL_DETAIL_PROJECTION_MISMATCH');
    $snapshotRead = (new ClinicalSnapshotService($pdo))->build(M02_TARGET_PATIENT);
    $narrative = (new ClinicalNarrativeService())->build($detail, []);
    $narrativeText = json_encode($narrative, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (!str_contains($narrativeText, 'padre') || !str_contains($narrativeText, 'madre')) m02IntegrationFail('CLINICAL_NARRATIVE_PROJECTION_MISMATCH');
    $snapText = json_encode($snapshotRead['modules'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (!str_contains($snapText, 'padre') || !str_contains($snapText, 'madre')) m02IntegrationFail('CLINICAL_SNAPSHOT_PROJECTION_MISMATCH');
    $output['doctor_and_summary_projection'] = 'PASS_DETAIL_SNAPSHOT_NARRATIVE';

    $phase = 'LEGACY_UNRECOGNIZED_TEXT_COMPATIBILITY';
    m02AssertSyntheticDatabase($pdo);
    $insertLegacy = $pdo->prepare("INSERT INTO pc_antecedentes_familiares (id_paciente,enfermedad,tipo,detalle,especificar) VALUES (:patient,:disease,'','Si',:legacy_text)");
    $insertLegacy->execute([':patient'=>M02_TARGET_PATIENT, ':disease'=>M02_LEGACY_DISEASE, ':legacy_text'=>M02_LEGACY_TEXT]);
    $legacyTestRowId = (int)$pdo->lastInsertId();
    if ($legacyTestRowId < 1) m02IntegrationFail('LEGACY_TEMP_ROW_ID_NOT_CAPTURED');
    $output['temporary_parent_ids_created'][] = $legacyTestRowId;
    $legacyState = (new ConversationalM02Adapter($pdo))->read(M02_TARGET_PATIENT);
    $legacyRecord = null;
    foreach ($legacyState['records'] as $candidate) if ($candidate['disease'] === M02_LEGACY_DISEASE) $legacyRecord = $candidate;
    if (!is_array($legacyRecord) || ($legacyRecord['legacy_relative_text'] ?? null) !== M02_LEGACY_TEXT || ($legacyRecord['relative'] ?? null) !== M02_LEGACY_TEXT || ($legacyRecord['relatives'] ?? []) !== []) m02IntegrationFail('LEGACY_ADAPTER_READ_CHANGED_OR_INFERRED_TEXT');
    if (m02ReadChildren($pdo, $legacyTestRowId) !== []) m02IntegrationFail('LEGACY_READ_CREATED_CHILD_ROWS');

    $doctorLegacyHtml = $model->mostrarPreguntasM2(M02_TARGET_PATIENT, 'Doctor');
    if (!str_contains($doctorLegacyHtml, htmlspecialchars(M02_LEGACY_TEXT, ENT_QUOTES, 'UTF-8'))) m02IntegrationFail('LEGACY_DOCTOR_PROJECTION_LOST_TEXT');
    $legacyDetail = (new ClinicalDetailService($pdo))->build(M02_TARGET_PATIENT);
    $legacyDetailJson = json_encode($legacyDetail['modules']['M02']['records'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (!str_contains($legacyDetailJson, M02_LEGACY_TEXT)) m02IntegrationFail('LEGACY_PATIENT_DETAIL_LOST_TEXT');
    $legacySnapshot = (new ClinicalSnapshotService($pdo))->build(M02_TARGET_PATIENT);
    $legacySnapshotJson = json_encode($legacySnapshot['modules'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (!str_contains($legacySnapshotJson, M02_LEGACY_TEXT)) m02IntegrationFail('LEGACY_SNAPSHOT_LOST_TEXT');
    $legacyNarrativeRecord = m02FindDetailRecordByField($legacyDetail['modules']['M02']['records'] ?? [], 'Enfermedad', M02_LEGACY_DISEASE);
    if (!is_array($legacyNarrativeRecord)) m02IntegrationFail('LEGACY_NARRATIVE_INPUT_NOT_FOUND');
    $legacyNarrative = (new ClinicalNarrativeService())->build(['modules'=>['M02'=>['records'=>[$legacyNarrativeRecord]]]], []);
    $legacyNarrativeJson = json_encode($legacyNarrative, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (!str_contains($legacyNarrativeJson, M02_LEGACY_TEXT) || preg_match('/\b(?:madre|padre|hermana|hermano|abuela|abuelo|hija|hijo)\b/u', $legacyNarrativeJson) === 1) m02IntegrationFail('LEGACY_NARRATIVE_INFERRED_RELATION');
    $legacyBeforeInvalidUpdate = m02RowsFingerprint($pdo, $patientIds);
    $invalidLegacyUpdate = $adapter->capture(M02_TARGET_PATIENT, M02_LEGACY_DISEASE, ['detail'=>'Si','relative'=>'pariente-no-catalogado','relatives'=>['pariente-no-catalogado'],'diabetes_type'=>null], (string)$legacyRecord['version']);
    if ((int)($invalidLegacyUpdate['resultado'] ?? 0) !== 422 || m02RowsFingerprint($pdo, $patientIds) !== $legacyBeforeInvalidUpdate) m02IntegrationFail('INVALID_LEGACY_UPDATE_OVERWROTE_SOURCE');
    $output['legacy_unknown_text_read'] = 'PASS_UNRECOGNIZED_TEXT_NOT_PARSED';
    $output['legacy_text_preserved'] = 'PASS_EXACT_IN_ADAPTER_AND_STORAGE';
    $output['legacy_patient_projection'] = 'PASS_DETAIL_AND_SNAPSHOT';
    $output['legacy_doctor_projection'] = 'PASS_ESCAPED_HTML_TEXT';
    $output['legacy_clinical_summary'] = 'PASS_TEXT_WITHOUT_INFERRED_RELATION';
    $output['legacy_incompatible_update'] = 'PASS_REJECTED_WITHOUT_OVERWRITE';

    $phase = 'NEGATIVE_CAPTURE_AND_ROLLBACK_ATOMICITY';
    $no = $adapter->capture(M02_TARGET_PATIENT, M02_TARGET_DISEASE, ['detail'=>'No','relatives'=>[],'relative'=>null,'diabetes_type'=>null], (string)$freshRecord['version']);
    if ((int)($no['resultado'] ?? 0) !== 200 || empty($no['readback']) || ($no['record']['relatives'] ?? []) !== []) m02IntegrationFail('NEGATIVE_CAPTURE_DID_NOT_CLEAR_RELATIVES');
    $countChildren = $pdo->prepare('SELECT COUNT(*) FROM pc_antecedentes_familiares_parentescos WHERE id_antecedente=:id');
    $countChildren->execute([':id' => (int)$freshRecord['id']]);
    if ((int)$countChildren->fetchColumn() !== 0) m02IntegrationFail('NEGATIVE_LEFT_ORPHAN_RELATIVES');
    $output['negative_clears_relatives'] = 'PASS';

    // Exercise InnoDB rollback across parent and child rows without temporary
    // triggers or schema changes. This verifies storage atomicity, not an
    // injected PDO failure inside the adapter.
    m02AssertSyntheticDatabase($pdo);
    $rollbackFingerprint = m02RowsFingerprint($pdo, $patientIds);
    $pdo->beginTransaction();
    try {
        $temporaryParent = $pdo->prepare('UPDATE pc_antecedentes_familiares SET detalle=\'No\',tipo=\'\' WHERE id=:id AND id_paciente=:patient');
        $temporaryParent->execute([':id' => (int)$freshRecord['id'], ':patient' => M02_TARGET_PATIENT]);
        $temporaryChild = $pdo->prepare('INSERT INTO pc_antecedentes_familiares_parentescos (id_antecedente,parentesco) VALUES (:id,\'hermana\')');
        $temporaryChild->execute([':id' => (int)$freshRecord['id']]);
        throw new RuntimeException('FORCED_TEST_ROLLBACK');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'FORCED_TEST_ROLLBACK') throw $exception;
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
    if (m02RowsFingerprint($pdo, $patientIds) !== $rollbackFingerprint) m02IntegrationFail('INNODB_ROLLBACK_CHANGED_ROWS');
    $output['rollback_atomicity'] = 'PASS_TRANSACTION_ROLLED_BACK_PARENT_AND_CHILD';

    $phase = 'DUPLICATE_GUARD_AND_CONCURRENT_PARENT_CREATION';
    m02AssertSyntheticDatabase($pdo);
    $pdo->beginTransaction();
    try {
        $duplicateInsert = $pdo->prepare("INSERT INTO pc_antecedentes_familiares (id_paciente,enfermedad,tipo,detalle,especificar) VALUES (9302,:disease,'','Si','')");
        $duplicateInsert->execute([':disease' => M02_CONCURRENCY_DISEASE]);
        $duplicateInsert->execute([':disease' => M02_CONCURRENCY_DISEASE]);
        $duplicateState = (new ConversationalM02Adapter($pdo))->read(9302);
        if (!in_array(M02_CONCURRENCY_DISEASE, $duplicateState['duplicate_diseases'], true) || !empty($duplicateState['write_enabled'])) m02IntegrationFail('DUPLICATE_DISEASE_NOT_FAIL_CLOSED');
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }

    $barrierDirectory = $snapshotDirectory . DIRECTORY_SEPARATOR . 'concurrency-' . bin2hex(random_bytes(4));
    if (!mkdir($barrierDirectory, 0700, true)) m02IntegrationFail('WORKER_DIR_CREATE_FAILED');
    $workers = [];
    for ($slot = 1; $slot <= 2; $slot++) {
        $descriptors = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $barrierDirectory, (string)$slot], $descriptors, $pipes, $root);
        if (!is_resource($process)) m02IntegrationFail('CONCURRENCY_WORKER_START_FAILED');
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $workers[$slot] = ['process' => $process, 'pipes' => $pipes, 'stdout' => '', 'stderr' => ''];
    }
    $deadline = microtime(true) + 20;
    while (!is_file($barrierDirectory . DIRECTORY_SEPARATOR . 'ready-1.json') || !is_file($barrierDirectory . DIRECTORY_SEPARATOR . 'ready-2.json')) {
        if (microtime(true) >= $deadline) m02IntegrationFail('CONCURRENCY_WORKER_READY_TIMEOUT');
        usleep(30000);
    }
    $readyOne = json_decode((string)file_get_contents($barrierDirectory . DIRECTORY_SEPARATOR . 'ready-1.json'), true, 16, JSON_THROW_ON_ERROR);
    $readyTwo = json_decode((string)file_get_contents($barrierDirectory . DIRECTORY_SEPARATOR . 'ready-2.json'), true, 16, JSON_THROW_ON_ERROR);
    if (($readyOne['version'] ?? '') !== ($readyTwo['version'] ?? '')) m02IntegrationFail('WORKERS_DID_NOT_SHARE_BASELINE_VERSION');
    file_put_contents($barrierDirectory . DIRECTORY_SEPARATOR . 'go', 'GO', LOCK_EX);
    foreach ($workers as $slot => &$worker) {
        $deadline = microtime(true) + 20;
        $observedExitCode = -1;
        do {
            if (is_resource($worker['pipes'][1] ?? null)) $worker['stdout'] .= stream_get_contents($worker['pipes'][1]) ?: '';
            if (is_resource($worker['pipes'][2] ?? null)) $worker['stderr'] .= stream_get_contents($worker['pipes'][2]) ?: '';
            $status = proc_get_status($worker['process']);
            if (!$status['running']) {
                $observedExitCode = (int)($status['exitcode'] ?? -1);
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($worker['process']);
                m02IntegrationFail('CONCURRENCY_WORKER_TIMEOUT');
            }
            usleep(30000);
        } while (true);
        foreach ([1 => 'stdout', 2 => 'stderr'] as $pipeIndex => $streamName) {
            if (!is_resource($worker['pipes'][$pipeIndex] ?? null)) continue;
            $worker[$streamName] .= stream_get_contents($worker['pipes'][$pipeIndex]) ?: '';
            fclose($worker['pipes'][$pipeIndex]);
        }
        $closedExitCode = proc_close($worker['process']);
        $worker['process'] = null;
        $worker['exit'] = $observedExitCode >= 0 ? $observedExitCode : $closedExitCode;
        if ($worker['exit'] !== 0) m02IntegrationFail('CONCURRENCY_WORKER_NONZERO_EXIT');
        $worker['result'] = json_decode((string)file_get_contents($barrierDirectory . DIRECTORY_SEPARATOR . 'result-' . $slot . '.json'), true, 16, JSON_THROW_ON_ERROR);
        if (($worker['result']['status'] ?? 0) !== 200 || empty($worker['result']['readback'])) m02IntegrationFail('CONCURRENCY_CAPTURE_FAILED');
        $createdConcurrentId = (int)($worker['result']['record_id'] ?? 0);
    }
    unset($worker);
    $concurrentRows = $pdo->prepare('SELECT id FROM pc_antecedentes_familiares WHERE id_paciente=9302 AND enfermedad=:disease ORDER BY id');
    $concurrentRows->execute([':disease' => M02_CONCURRENCY_DISEASE]);
    $concurrentIds = array_map('intval', $concurrentRows->fetchAll(PDO::FETCH_COLUMN));
    if (count($concurrentIds) !== 1 || $createdConcurrentId !== $concurrentIds[0]) m02IntegrationFail('CONCURRENT_PARENT_CREATION_NOT_UNIQUE');
    $output['temporary_parent_ids_created'][] = $createdConcurrentId;
    $concurrentSet = m02ReadChildren($pdo, $createdConcurrentId); sort($concurrentSet, SORT_STRING);
    if ($concurrentSet !== ['madre','padre']) m02IntegrationFail('CONCURRENT_CHILD_READBACK_MISMATCH');
    $pdo->beginTransaction();
    $restrictConfirmed = false;
    try {
        $pdo->prepare('DELETE FROM pc_antecedentes_familiares WHERE id=:id AND id_paciente=9302')->execute([':id'=>$createdConcurrentId]);
    } catch (PDOException $exception) {
        $sqlState = strtoupper((string)($exception->errorInfo[0] ?? $exception->getCode()));
        $restrictConfirmed = $sqlState === '23000';
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
    if (!$restrictConfirmed || m02ReadChildren($pdo, $createdConcurrentId) !== ['madre','padre']) m02IntegrationFail('FK_DELETE_RESTRICT_NOT_CONFIRMED');
    $deleteResult = $model->eliminarEnfermedadPaciente(['idEnfermedad'=>$createdConcurrentId, 'idPaciente'=>9302]);
    if ((int)($deleteResult['resultado'] ?? 0) !== 200 || m02ReadChildren($pdo, $createdConcurrentId) !== []) m02IntegrationFail('AUTHORIZED_DELETE_DID_NOT_REMOVE_CHILDREN_ATOMICALLY');
    $output['temporary_parent_ids_restored'][] = $createdConcurrentId;
    $createdConcurrentId = 0;
    $output['concurrent_missing_parent'] = 'PASS_TWO_WORKERS_ONE_PARENT_COMPLETE_SET';
    $output['foreign_key_and_authorized_delete'] = 'PASS_RESTRICT_AND_EXPLICIT_CHILD_DELETE';

    $phase = 'FINALIZATION_RULE_TEST';
    $reflection = new ReflectionMethod(ConversationalModuleFinalizationService::class, 'm02Complete');
    $reflection->setAccessible(true);
    $completeFixture = ['records' => array_fill(0, 5, ['status'=>'documented','detail'=>'Si','relatives'=>['madre'],'legacy_relative_text'=>null,'disease'=>'Enfermedad']), 'duplicate_diseases'=>[]];
    $completeFixture['records'][3] = ['status'=>'documented','detail'=>'Si','relatives'=>['madre'],'legacy_relative_text'=>null,'diabetes_type'=>'Tipo 2','disease'=>'Diabetes Mellitus'];
    $pendingFixture = $completeFixture; $pendingFixture['records'][0]['relatives'] = [];
    $duplicateFixture = $completeFixture; $duplicateFixture['duplicate_diseases'] = ['Enfermedad'];
    if (!$reflection->invoke(new ConversationalModuleFinalizationService($pdo), $completeFixture)
        || $reflection->invoke(new ConversationalModuleFinalizationService($pdo), $pendingFixture)
        || $reflection->invoke(new ConversationalModuleFinalizationService($pdo), $duplicateFixture)) m02IntegrationFail('M02_FINALIZATION_CONTRACT_MISMATCH');
    $output['finalization_rule'] = 'PASS_POSITIVE_REQUIRES_RELATIVE_AND_DUPLICATES_BLOCK';

    $testOutcome = true;
    $output['status'] = 'PASS_TESTS_RESTORATION_PENDING';
    }
} catch (Throwable $exception) {
    $failureCode = preg_replace('/[^A-Z0-9_]/', '', strtoupper($exception->getMessage())) ?: 'UNCLASSIFIED_FAILURE';
    $output['failed_stage'] = $phase;
    $output['failure_code'] = $failureCode;
    $output['status'] = 'FAIL_RESTORATION_PENDING';
}

foreach ($workers as &$worker) {
    if (is_resource($worker['process'] ?? null)) {
        $status = proc_get_status($worker['process']);
        if ($status['running']) proc_terminate($worker['process']);
        @proc_close($worker['process']);
        foreach (($worker['pipes'] ?? []) as $pipe) if (is_resource($pipe)) @fclose($pipe);
        $worker['process'] = null;
    }
}
unset($worker);

if ($pdo instanceof PDO && $snapshotReady) {
    try {
        $phase = 'BASELINE_RESTORATION';
        m02AssertSyntheticDatabase($pdo);
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->beginTransaction();
        if ($targetBaseline !== null) {
            $parentCheck = $pdo->prepare('SELECT id FROM pc_antecedentes_familiares WHERE id=:id AND id_paciente=:patient AND enfermedad=:disease FOR UPDATE');
            $parentCheck->execute([':id' => (int)$targetBaseline['id'], ':patient' => M02_TARGET_PATIENT, ':disease' => M02_TARGET_DISEASE]);
            if (!$parentCheck->fetchColumn()) throw new RuntimeException('RESTORE_TARGET_ROW_MISSING');
            $restoreParent = $pdo->prepare('UPDATE pc_antecedentes_familiares SET tipo=:type,detalle=:detail,especificar=:specific WHERE id=:id AND id_paciente=:patient AND enfermedad=:disease');
            $restoreParent->execute([':type'=>$targetBaseline['tipo'], ':detail'=>$targetBaseline['detalle'], ':specific'=>$targetBaseline['especificar'], ':id'=>(int)$targetBaseline['id'], ':patient'=>M02_TARGET_PATIENT, ':disease'=>M02_TARGET_DISEASE]);
            $targetParentId = (int)$targetBaseline['id'];
            $deleteTargetChildren = $pdo->prepare('DELETE FROM pc_antecedentes_familiares_parentescos WHERE id_antecedente=:id');
            $deleteTargetChildren->execute([':id'=>$targetParentId]);
            if ($targetChildren !== []) {
                $restoreChild = $pdo->prepare('INSERT INTO pc_antecedentes_familiares_parentescos (id_antecedente,parentesco) VALUES (:id,:relative)');
                foreach ($targetChildren as $child) $restoreChild->execute([':id'=>(int)$child['id_antecedente'], ':relative'=>(string)$child['parentesco']]);
            }
        } else {
            $targetCandidates = $pdo->prepare('SELECT id,detalle,tipo,especificar FROM pc_antecedentes_familiares WHERE id_paciente=:patient AND enfermedad=:disease ORDER BY id FOR UPDATE');
            $targetCandidates->execute([':patient'=>M02_TARGET_PATIENT, ':disease'=>M02_TARGET_DISEASE]);
            $rows = $targetCandidates->fetchAll(PDO::FETCH_ASSOC);
            if ($rows !== []) {
                if (count($rows) !== 1 || !in_array((string)$rows[0]['detalle'], ['', 'Si', 'No'], true) || (string)$rows[0]['tipo'] !== '' || (string)$rows[0]['especificar'] !== '') {
                    throw new RuntimeException('RESTORE_UNEXPECTED_TARGET_ROW');
                }
                $targetParentId = (int)$rows[0]['id'];
                $childSet = m02ReadChildren($pdo, $targetParentId);
                sort($childSet, SORT_STRING);
                $allowedChildSets = [[]];
                foreach (M02_TEST_RELATIVE_SETS as $allowedSet) {
                    sort($allowedSet, SORT_STRING);
                    $allowedChildSets[] = $allowedSet;
                }
                if (!in_array($childSet, $allowedChildSets, true)) {
                    throw new RuntimeException('RESTORE_UNEXPECTED_TARGET_CHILD_SET');
                }
                if ((string)$rows[0]['detalle'] === 'No' && $childSet !== []) throw new RuntimeException('RESTORE_NEGATIVE_WITH_CHILDREN');
                $pdo->prepare('DELETE FROM pc_antecedentes_familiares_parentescos WHERE id_antecedente=:id')->execute([':id'=>$targetParentId]);
                $removeTarget = $pdo->prepare('DELETE FROM pc_antecedentes_familiares WHERE id=:id AND id_paciente=:patient AND enfermedad=:disease');
                $removeTarget->execute([':id'=>$targetParentId, ':patient'=>M02_TARGET_PATIENT, ':disease'=>M02_TARGET_DISEASE]);
                if ($removeTarget->rowCount() !== 1) throw new RuntimeException('RESTORE_TARGET_ROW_NOT_REMOVED');
                $output['temporary_parent_ids_restored'][] = $targetParentId;
            }
        }
        if ($legacyTestRowId > 0) {
            $legacyCheck = $pdo->prepare('SELECT detalle,tipo,especificar FROM pc_antecedentes_familiares WHERE id=:id AND id_paciente=:patient AND enfermedad=:disease FOR UPDATE');
            $legacyCheck->execute([':id'=>$legacyTestRowId, ':patient'=>M02_TARGET_PATIENT, ':disease'=>M02_LEGACY_DISEASE]);
            $legacyCurrent = $legacyCheck->fetch(PDO::FETCH_ASSOC);
            if (!is_array($legacyCurrent) || (string)$legacyCurrent['detalle'] !== 'Si' || (string)$legacyCurrent['tipo'] !== '' || (string)$legacyCurrent['especificar'] !== M02_LEGACY_TEXT || m02ReadChildren($pdo, $legacyTestRowId) !== []) {
                throw new RuntimeException('RESTORE_LEGACY_SYNTHETIC_ROW_MISMATCH');
            }
            $removeLegacy = $pdo->prepare('DELETE FROM pc_antecedentes_familiares WHERE id=:id AND id_paciente=:patient AND enfermedad=:disease');
            $removeLegacy->execute([':id'=>$legacyTestRowId, ':patient'=>M02_TARGET_PATIENT, ':disease'=>M02_LEGACY_DISEASE]);
            if ($removeLegacy->rowCount() !== 1) throw new RuntimeException('RESTORE_LEGACY_SYNTHETIC_ROW_FAILED');
            $output['temporary_parent_ids_restored'][] = $legacyTestRowId;
        } else {
            $legacyUnexpected = $pdo->prepare('SELECT COUNT(*) FROM pc_antecedentes_familiares WHERE id_paciente=:patient AND enfermedad=:disease');
            $legacyUnexpected->execute([':patient'=>M02_TARGET_PATIENT, ':disease'=>M02_LEGACY_DISEASE]);
            if ((int)$legacyUnexpected->fetchColumn() !== 0) throw new RuntimeException('RESTORE_UNTRACKED_LEGACY_ROW');
        }
        if ($createdConcurrentId > 0) {
            $concurrentChildRows = $pdo->prepare('SELECT parentesco FROM pc_antecedentes_familiares_parentescos WHERE id_antecedente=:id FOR UPDATE');
            $concurrentChildRows->execute([':id'=>$createdConcurrentId]);
            $values = array_map('strval', $concurrentChildRows->fetchAll(PDO::FETCH_COLUMN)); sort($values, SORT_STRING);
            if ($values !== ['madre','padre']) throw new RuntimeException('RESTORE_CONCURRENT_ROW_CONTENT_MISMATCH');
            $pdo->prepare('DELETE FROM pc_antecedentes_familiares_parentescos WHERE id_antecedente=:id')->execute([':id'=>$createdConcurrentId]);
            $deleteConcurrent = $pdo->prepare('DELETE FROM pc_antecedentes_familiares WHERE id=:id AND id_paciente=9302 AND enfermedad=:disease');
            $deleteConcurrent->execute([':id'=>$createdConcurrentId, ':disease'=>M02_CONCURRENCY_DISEASE]);
            if ($deleteConcurrent->rowCount() !== 1) throw new RuntimeException('RESTORE_CONCURRENT_PARENT_NOT_REMOVED');
            $output['temporary_parent_ids_restored'][] = $createdConcurrentId;
        } else {
            $unexpected = $pdo->prepare('SELECT id,id_paciente,enfermedad,tipo,detalle,especificar FROM pc_antecedentes_familiares WHERE id_paciente=9302 AND enfermedad=:disease ORDER BY id');
            $unexpected->execute([':disease'=>M02_CONCURRENCY_DISEASE]);
            $unexpectedRows = $unexpected->fetchAll(PDO::FETCH_ASSOC);
            if ($unexpectedRows !== []) {
                if (count($unexpectedRows) !== 1 || (string)$unexpectedRows[0]['detalle'] !== 'Si' || (string)$unexpectedRows[0]['tipo'] !== '' || (string)$unexpectedRows[0]['especificar'] !== '') {
                    throw new RuntimeException('RESTORE_UNTRACKED_SYNTHETIC_ROW');
                }
                $candidateId = (int)$unexpectedRows[0]['id'];
                $candidateChildren = m02ReadChildren($pdo, $candidateId); sort($candidateChildren, SORT_STRING);
                if ($candidateChildren !== ['madre','padre']) throw new RuntimeException('RESTORE_UNTRACKED_SYNTHETIC_CHILD_SET');
                $pdo->prepare('DELETE FROM pc_antecedentes_familiares_parentescos WHERE id_antecedente=:id')->execute([':id'=>$candidateId]);
                $removeCandidate = $pdo->prepare('DELETE FROM pc_antecedentes_familiares WHERE id=:id AND id_paciente=9302 AND enfermedad=:disease');
                $removeCandidate->execute([':id'=>$candidateId, ':disease'=>M02_CONCURRENCY_DISEASE]);
                if ($removeCandidate->rowCount() !== 1) throw new RuntimeException('RESTORE_UNTRACKED_SYNTHETIC_ROW_FAILED');
                $output['temporary_parent_ids_restored'][] = $candidateId;
            }
        }
        $pdo->commit();
        $output['baseline_reconciliation'] = m02RowsFingerprint($pdo, [9301,9302]) === $baselineFingerprint ? 'PASS_EXACT_M02_ROWS_MATCH' : 'FAIL_M02_ROWS_DIFFER';
        if ($output['baseline_reconciliation'] !== 'PASS_EXACT_M02_ROWS_MATCH') throw new RuntimeException('BASELINE_RECONCILIATION_FAILED');
        if ($testOutcome) $output['status'] = 'PASS';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $output['baseline_reconciliation'] = 'FAIL_RESTORATION_REQUIRED';
        $output['restoration_failure_code'] = preg_replace('/[^A-Z0-9_]/', '', strtoupper($exception->getMessage())) ?: 'RESTORE_FAILED';
        $output['status'] = 'RESTORATION_FAILED';
    }
}

if ($barrierDirectory !== null && is_dir($barrierDirectory) && ($output['baseline_reconciliation'] ?? '') === 'PASS_EXACT_M02_ROWS_MATCH') {
    foreach (glob($barrierDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) if (is_file($file)) @unlink($file);
    @rmdir($barrierDirectory);
}
$output['snapshot_retained_outside_repository'] = $snapshotPath !== null ? 'YES' : 'NO';
echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
exit(($output['status'] ?? '') === 'PASS' ? 0 : 1);
