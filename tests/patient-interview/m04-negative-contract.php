<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\ConversationalInterviewService;
use App\Services\ConversationalM04Adapter;
use App\Services\ConversationalModuleFinalizationService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$database = static function (bool $withNegativeState, bool $withSurgery = false): PDO {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE pc_paciente (id INTEGER PRIMARY KEY, id_clinica INTEGER NOT NULL)');
    $db->exec('CREATE TABLE pac_historia_clinica_finalizar (id INTEGER PRIMARY KEY AUTOINCREMENT, id_modulo INTEGER NOT NULL, id_paciente INTEGER NOT NULL)');
    $db->exec('CREATE TABLE pc_antecedentes_quirurgicos (id INTEGER PRIMARY KEY AUTOINCREMENT, id_paciente INTEGER NOT NULL, fecha TEXT NULL, cirugia TEXT NULL, observaciones TEXT NULL)');
    $db->exec('INSERT INTO pc_paciente (id, id_clinica) VALUES (9301, 9101), (9302, 9102)');
    if ($withNegativeState) {
        $db->exec('CREATE TABLE pc_antecedentes_quirurgicos_estado (id INTEGER PRIMARY KEY AUTOINCREMENT, id_paciente INTEGER NOT NULL, id_clinica INTEGER NOT NULL, estado TEXT NOT NULL, origen TEXT NOT NULL, version INTEGER NOT NULL DEFAULT 1, declarado_en TEXT NOT NULL, actualizado_en TEXT NOT NULL)');
    }
    if ($withSurgery) {
        $db->exec("INSERT INTO pc_antecedentes_quirurgicos (id_paciente, fecha, cirugia, observaciones) VALUES (9301, NULL, 'rodilla', NULL)");
    }
    return $db;
};

$db = $database(true);
$adapter = new ConversationalM04Adapter($db);
$stateBefore = $adapter->read(9301);
$assert(($stateBefore['response_state'] ?? null) === 'PENDING', 'empty M04 must remain pending');
$negative = $adapter->interpret('No');
$assert(($negative['kind'] ?? null) === 'm04_negative_proposal', 'No must be a negative proposal');
$assert(($negative['direct_save'] ?? false) === true, 'explicit No must use the single save flow');
$assert(($negative['semantic_state'] ?? null) === 'NEGATIVE_DECLARED', 'negative semantic state');

$saved = $adapter->capture(9301, $negative, $stateBefore['version']);
$assert((int)($saved['resultado'] ?? 0) === 200, 'negative capture');
$assert(($saved['readback'] ?? false) === true, 'negative readback');
$stateAfter = $adapter->read(9301);
$assert(($stateAfter['negative_declaration']['estado'] ?? null) === 'NEGATIVE_DECLARED', 'negative state persisted');
$assert(($stateAfter['negative_declaration']['origen'] ?? null) === 'PATIENT_DECLARATION', 'negative provenance');
$assert(($stateAfter['response_state'] ?? null) === 'NEGATIVE_DECLARED', 'negative response state');
$assert($stateAfter['records'] === [], 'negative capture must not create a surgery');
$assert($adapter->read(9301)['version'] === $stateAfter['version'], 'read version stable');

$idempotent = $adapter->capture(9301, $negative, $stateAfter['version']);
$assert((int)($idempotent['resultado'] ?? 0) === 200, 'repeated negative capture');
$assert((int)$adapter->read(9301)['negative_declaration']['id'] === (int)$stateAfter['negative_declaration']['id'], 'idempotence must not duplicate state');

$finalization = (new ConversationalModuleFinalizationService($db))->finalizeIfComplete(9301, 4);
$assert((int)($finalization['resultado'] ?? 0) === 200 && ($finalization['finalized'] ?? false) === true, 'negative declaration must use the traditional finalization contract');
$stateAfter = $adapter->read(9301);
$assert(($stateAfter['write_enabled'] ?? true) === false, 'finalized M04 must reject ordinary edits');

$contract = (new ConversationalInterviewService())->buildUnifiedContract(['M04' => $stateAfter]);
$m04Question = $contract['modules']['M04']['questions'][0] ?? [];
$assert(($m04Question['response']['value'] ?? null) === 'No', 'contract exposes No');
$assert(($m04Question['response']['status'] ?? null) === 'documented', 'negative response documented');
$assert(($m04Question['response']['persisted'] ?? false) === true, 'negative response persisted in contract');
$assert(($contract['modules']['M04']['completion']['status'] ?? null) === 'complete', 'negative response completes M04');

$conflictDb = $database(true, true);
$conflictAdapter = new ConversationalM04Adapter($conflictDb);
$conflictState = $conflictAdapter->read(9301);
$conflict = $conflictAdapter->capture(9301, $conflictAdapter->interpret('No'), $conflictState['version']);
$assert((int)($conflict['resultado'] ?? 0) === 409, 'negative conflicts with surgery');
$assert(($conflict['codigo'] ?? null) === 'M04_NEGATIVE_CONFLICTING_SURGERY_RECORDS', 'conflict code');
$assert(($conflictAdapter->read(9301)['response_state'] ?? null) === 'PROCEDURES_DOCUMENTED', 'procedure state');
$assert((int)$conflictDb->query('SELECT COUNT(*) FROM pc_antecedentes_quirurgicos_estado')->fetchColumn() === 0, 'conflict must not create state');

$correctionDb = $database(true);
$correctionAdapter = new ConversationalM04Adapter($correctionDb);
$correctionBefore = $correctionAdapter->read(9301);
$correctionAdapter->capture(9301, $correctionAdapter->interpret('No'), $correctionBefore['version']);
$correctionState = $correctionAdapter->read(9301);
$positiveCorrection = $correctionAdapter->interpret('Sí, me operaron de la rodilla en 2019');
$corrected = $correctionAdapter->capture(9301, $positiveCorrection, $correctionState['version']);
$assert((int)($corrected['resultado'] ?? 0) === 200, 'authorized positive correction');
$assert(($correctionAdapter->read(9301)['negative_declaration']['estado'] ?? null) === 'SUPERSEDED_BY_SURGERY', 'negative provenance remains after correction');
$assert(($correctionAdapter->read(9301)['response_state'] ?? null) === 'PROCEDURES_DOCUMENTED', 'positive correction response state');
$assert((int)$correctionDb->query('SELECT COUNT(*) FROM pc_antecedentes_quirurgicos WHERE id_paciente=9301 AND cirugia<>\'\'')->fetchColumn() === 1, 'positive correction creates one real surgery');

$rollbackDb = $database(true);
$rollbackDb->exec("CREATE TRIGGER m04_negative_abort BEFORE INSERT ON pc_antecedentes_quirurgicos_estado BEGIN SELECT RAISE(ABORT, 'synthetic rollback'); END");
$rollbackAdapter = new ConversationalM04Adapter($rollbackDb);
$rollbackState = $rollbackAdapter->read(9301);
$rollback = $rollbackAdapter->capture(9301, $rollbackAdapter->interpret('No'), $rollbackState['version']);
$assert((int)($rollback['resultado'] ?? 0) === 422, 'persistence failure must fail closed');
$assert((int)$rollbackDb->query('SELECT COUNT(*) FROM pc_antecedentes_quirurgicos_estado')->fetchColumn() === 0, 'failed negative capture must roll back');
$assert(!$rollbackDb->inTransaction(), 'failed negative capture must close its transaction');

$unmigratedDb = $database(false);
$unmigratedAdapter = new ConversationalM04Adapter($unmigratedDb);
$unmigratedState = $unmigratedAdapter->read(9301);
$unmigrated = $unmigratedAdapter->capture(9301, $unmigratedAdapter->interpret('No'), $unmigratedState['version']);
$assert((int)($unmigrated['resultado'] ?? 0) === 409, 'unmigrated schema must block');
$assert(($unmigrated['codigo'] ?? null) === 'M04_NEGATIVE_STATE_SCHEMA_REQUIRED', 'unmigrated schema code');
$assert((int)$unmigratedDb->query('SELECT COUNT(*) FROM pc_antecedentes_quirurgicos')->fetchColumn() === 0, 'unmigrated schema must not fake a surgery');

$yes = (new ConversationalM04Adapter(null))->interpret('Sí, me operaron de la rodilla en 2019');
$assert(($yes['kind'] ?? null) === 'm04_proposal', 'Yes flow remains a surgery proposal');
$assert(($yes['surgery'] ?? null) === 'la rodilla', 'Yes surgery detail');
$yesWithoutDetails = (new ConversationalM04Adapter(null))->interpret('Sí');
$assert(($yesWithoutDetails['kind'] ?? null) === 'clarification', 'Yes without surgery details must remain pending');
$assert((new ConversationalM04Adapter(null))->interpret('No sé') ['kind'] === 'clarification', 'unknown is not negative');

echo "PASS M04 negative declaration contract\n";
echo "M04_NEGATIVE_CONTRACT: PASS\n";
