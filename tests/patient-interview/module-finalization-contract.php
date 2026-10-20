<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\ConversationalM04Adapter;
use App\Services\ConversationalModuleFinalizationService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE pc_paciente (id INTEGER PRIMARY KEY, id_clinica INTEGER NOT NULL)');
$db->exec('CREATE TABLE pac_historia_clinica_finalizar (id INTEGER PRIMARY KEY AUTOINCREMENT, id_modulo INTEGER NOT NULL, id_paciente INTEGER NOT NULL)');
$db->exec('CREATE TABLE pc_antecedentes_quirurgicos (id INTEGER PRIMARY KEY AUTOINCREMENT, id_paciente INTEGER NOT NULL, fecha TEXT NULL, cirugia TEXT NULL, observaciones TEXT NULL)');
$db->exec('CREATE TABLE pc_antecedentes_quirurgicos_estado (id INTEGER PRIMARY KEY AUTOINCREMENT, id_paciente INTEGER NOT NULL, id_clinica INTEGER NOT NULL, estado TEXT NOT NULL, origen TEXT NOT NULL, version INTEGER NOT NULL DEFAULT 1, declarado_en TEXT NOT NULL, actualizado_en TEXT NOT NULL)');
$db->exec('INSERT INTO pc_paciente (id, id_clinica) VALUES (9301, 9101)');

$adapter = new ConversationalM04Adapter($db);
$before = $adapter->read(9301);
$saved = $adapter->capture(9301, $adapter->interpret('No'), $before['version']);
$assert((int)($saved['resultado'] ?? 0) === 200, 'M04 declaration must persist before finalization');

$finalizer = new ConversationalModuleFinalizationService($db);
$closed = $finalizer->finalizeIfComplete(9301, 4);
$assert((int)($closed['resultado'] ?? 0) === 200, 'traditional M04 finalizer must succeed');
$assert(($closed['finalized'] ?? false) === true, 'finalization readback must be true');
$assert(($closed['readback'] ?? false) === true, 'finalization must be confirmed by readback');
$assert((int)$db->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo=4 AND id_paciente=9301')->fetchColumn() === 1, 'one canonical finalization row');

$after = $adapter->read(9301);
$assert(($after['finalized'] ?? false) === true, 'adapter must expose the traditional finalization');
$assert(($after['write_enabled'] ?? true) === false, 'ordinary edits must be locked after finalization');
$assert($after['records'] === [], 'negative finalization must not create surgery');
$lockedCapture = $adapter->capture(9301, $adapter->interpret('No'), $after['version']);
$assert((int)($lockedCapture['resultado'] ?? 0) === 409, 'finalized M04 must reject ordinary conversational writes');

$repeat = $finalizer->finalizeIfComplete(9301, 4);
$assert((int)($repeat['resultado'] ?? 0) === 200 && ($repeat['finalized'] ?? false) === true, 'finalization must be idempotent');

$failureDb = new PDO('sqlite::memory:');
$failureDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$failureDb->exec('CREATE TABLE pc_paciente (id INTEGER PRIMARY KEY, id_clinica INTEGER NOT NULL)');
$failureDb->exec('CREATE TABLE pac_historia_clinica_finalizar (id INTEGER PRIMARY KEY AUTOINCREMENT, id_modulo INTEGER NOT NULL, id_paciente INTEGER NOT NULL)');
$failureDb->exec('CREATE TABLE pc_antecedentes_quirurgicos (id INTEGER PRIMARY KEY AUTOINCREMENT, id_paciente INTEGER NOT NULL, fecha TEXT NULL, cirugia TEXT NULL, observaciones TEXT NULL)');
$failureDb->exec('CREATE TABLE pc_antecedentes_quirurgicos_estado (id INTEGER PRIMARY KEY AUTOINCREMENT, id_paciente INTEGER NOT NULL, id_clinica INTEGER NOT NULL, estado TEXT NOT NULL, origen TEXT NOT NULL, version INTEGER NOT NULL DEFAULT 1, declarado_en TEXT NOT NULL, actualizado_en TEXT NOT NULL)');
$failureDb->exec('INSERT INTO pc_paciente (id, id_clinica) VALUES (9301, 9101)');
$failureDb->exec("CREATE TRIGGER finalization_abort BEFORE INSERT ON pac_historia_clinica_finalizar BEGIN SELECT RAISE(ABORT, 'synthetic finalization failure'); END");
$failureAdapter = new ConversationalM04Adapter($failureDb);
$failureBefore = $failureAdapter->read(9301);
$failureSaved = $failureAdapter->capture(9301, $failureAdapter->interpret('No'), $failureBefore['version']);
$assert((int)($failureSaved['resultado'] ?? 0) === 200, 'failure fixture declaration must persist before finalization');
$failure = (new ConversationalModuleFinalizationService($failureDb))->finalizeIfComplete(9301, 4);
$assert((int)($failure['resultado'] ?? 0) === 500 && ($failure['finalized'] ?? true) === false, 'finalization failure must stay open');
$assert((int)$failureDb->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar')->fetchColumn() === 0, 'failed finalization must not create closure');

$m07 = $finalizer->finalizeIfComplete(9301, 7);
$assert((int)($m07['resultado'] ?? 0) === 409 && ($m07['code'] ?? null) === 'M07_CANONICAL_DISABLED', 'M07 gate remains closed');

echo "MODULE_FINALIZATION_CONTRACT_PASS\n";
