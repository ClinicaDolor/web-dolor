<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\ConversationalM04Adapter;
use App\Services\ConversationalInterviewService;
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
$db->exec('INSERT INTO pc_paciente (id, id_clinica) VALUES (9301, 9101), (9302, 9102)');

$adapter = new ConversationalM04Adapter($db);
$namedProcedure = $adapter->interpret('Me hicieron una apendicectomía');
$assert(($namedProcedure['kind'] ?? null) === 'm04_proposal' && ($namedProcedure['surgery'] ?? null) === 'apendicectomía', 'named surgery phrase was not recognized');
$yesWithoutDetails = $adapter->interpret('Sí, me operaron');
$assert(($yesWithoutDetails['kind'] ?? null) === 'clarification', 'bare positive answer created a surgery');
$state = $adapter->read(9301);
$firstVersion = $state['version'];
$firstProposal = $adapter->interpret('Me operaron de la rodilla el 15 de marzo de 2019');
$inputs = [
    'Me operaron de la rodilla el 15 de marzo de 2019',
    'Me operaron de la vesícula en 2020',
    'Me operaron de la cadera hace aproximadamente tres años',
];

foreach ($inputs as $index => $input) {
    $proposal = $adapter->interpret($input);
    $assert(($proposal['kind'] ?? null) === 'm04_proposal', 'positive surgery proposal ' . ($index + 1));
    $saved = $adapter->capture(9301, $proposal, $state['version']);
    $assert((int)($saved['resultado'] ?? 0) === 200, 'positive surgery capture ' . ($index + 1));
    $assert(($saved['readback'] ?? false) === true, 'positive surgery readback ' . ($index + 1));
    if ($index === 2) {
        $savedRecord = $saved['record'] ?? [];
        $assert(str_contains((string)($savedRecord['observaciones'] ?? ''), 'Expresión original:'), 'approximate temporal expression was not preserved');
    }
    $state = $adapter->read(9301);
    $assert(count($state['records'] ?? []) === $index + 1, 'record count after surgery ' . ($index + 1));
    $assert(($state['finalized'] ?? true) === false, 'positive capture finalized M04 prematurely');
}

$assert((int)$db->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo=4 AND id_paciente=9301')->fetchColumn() === 0, 'positive captures must not finalize M04');
$duplicate = $adapter->capture(9301, $firstProposal, $state['version']);
$assert((int)($duplicate['resultado'] ?? 0) === 409 && ($duplicate['codigo'] ?? null) === 'M04_DUPLICATE_SURGERY', 'identical M04 surgery was duplicated');
$stale = $adapter->capture(9301, $adapter->interpret('Me operaron de la mano en 2021'), $firstVersion);
$assert((int)($stale['resultado'] ?? 0) === 409, 'stale M04 version was accepted');
$fourth = $adapter->capture(9301, $adapter->interpret('Me operaron de la mano en 2021'), $state['version']);
$assert((int)($fourth['resultado'] ?? 0) === 200, 'valid additional surgery was rejected');
$state = $adapter->read(9301);
$assert(count($state['records'] ?? []) === 4, 'M04 imposed an artificial surgery limit');
$contract = (new ConversationalInterviewService())->buildUnifiedContract([
    'M04' => [
        'records' => $state['records'],
        'negative_declaration' => null,
        'version' => $state['version'],
        'write_enabled' => true,
        'finalized' => false,
    ],
]);
$continuation = $contract['question_index']['M04:record:new'] ?? [];
$assert(($continuation['visible_text'] ?? null) === '¿Quieres agregar otra cirugía?', 'M04 continuation question is not canonical');
$assert(($continuation['instruction'] ?? null) === 'Puedes escribir otra cirugía o seleccionar «No, continuar» para pasar al siguiente módulo.', 'M04 continuation instruction is not canonical');
$assert(($continuation['voice']['QUESTION'] ?? null) === '¿Quieres agregar otra cirugía?', 'M04 continuation voice question is not aligned');

$finish = $adapter->finish(9301, $state['version']);
$assert((int)($finish['resultado'] ?? 0) === 200, 'explicit additional-surgeries finish was rejected');
$assert(($finish['finish_intent'] ?? null) === 'FINISH_ADDITIONAL_SURGERIES', 'finish intent was not state-derived');
$assert(($finish['request_finalization'] ?? false) === true && ($finish['readback'] ?? false) === true, 'finish did not return readback');

$closed = (new ConversationalModuleFinalizationService($db))->finalizeIfComplete(9301, 4);
$assert((int)($closed['resultado'] ?? 0) === 200 && ($closed['finalized'] ?? false) === true, 'M04 explicit finalization failed');
$assert(($closed['readback'] ?? false) === true, 'M04 finalization readback failed');
$assert((int)$db->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo=4 AND id_paciente=9301')->fetchColumn() === 1, 'M04 finalization row count');
$assert(count((new ConversationalM04Adapter($db))->read(9301)['records'] ?? []) === 4, 'readback lost a surgery record');

$negativeState = (new ConversationalM04Adapter($db))->read(9302);
$negativeFinish = (new ConversationalM04Adapter($db))->finish(9302, $negativeState['version']);
$assert((int)($negativeFinish['resultado'] ?? 0) === 200, 'negative M04 finish was rejected');
$assert(($negativeFinish['finish_intent'] ?? null) === 'NEGATIVE_HISTORY', 'negative finish intent missing');
$assert((new ConversationalM04Adapter($db))->read(9302)['records'] === [], 'negative finish created a surgery record');
$negativeClosed = (new ConversationalModuleFinalizationService($db))->finalizeIfComplete(9302, 4);
$assert((int)($negativeClosed['resultado'] ?? 0) === 200 && ($negativeClosed['readback'] ?? false) === true, 'negative M04 finalization failed');

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$adapterSource = file_get_contents($root . '/app/Services/ConversationalM04Adapter.php');
$m04Block = '';
if (preg_match('/data-module="M04".*?data-module="M05"/s', $view, $match)) $m04Block = $match[0];
$assert($m04Block !== '' && !str_contains($m04Block, 'data-value="Sí"'), 'M04 quick Sí button remains');
$assert(str_contains($m04Block, 'No, continuar') && str_contains($m04Block, 'FINISH_ADDITIONAL_SURGERIES'), 'M04 finish control contract missing');
$assert(str_contains($view, '$m04ValidDate') && str_contains($view, 'checkdate('), 'M04 date display is not strictly validated');
$assert(!str_contains($m04Block, 'Fecha no registrada'), 'M04 renders a placeholder for an absent date');
$assert(str_contains($m04Block, 'Cirugías registradas —') && str_contains($m04Block, 'count($m04Records)'), 'M04 surgery count heading is missing');
$assert(str_contains($m04Block, 'data-m04-question-title') && str_contains($m04Block, 'data-m04-question-instruction'), 'M04 continuation question targets are missing');
$assert(str_contains($controller, "intent !== 'FINISH_ADDITIONAL_SURGERIES'") && str_contains($controller, '$allowFinalization'), 'controller does not distinguish explicit M04 finish');
$assert(str_contains($controller, 'finalizeAfterCapture((int)$patientId, 4, $result, $allowFinalization)'), 'M04 capture does not pass the finalization policy');
$assert(str_contains($adapterSource, 'public function finish') && str_contains($adapterSource, "NEGATIVE_HISTORY"), 'M04 adapter finish contract missing');
$serviceSource = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');
$assert(str_contains($serviceSource, "'M04:record:new'") && str_contains($serviceSource, '¿Quieres agregar otra cirugía?'), 'M04 continuation contract missing');
$assert(str_contains($view, 'data.readback !== true') && str_contains($view, 'speakM04Continuation'), 'M04 voice is not gated by readback');
$assert(str_contains($view, 'M04_CONTINUATION_VOICE') && str_contains($view, 'voiceController.speakEvent'), 'M04 continuation voice does not use the existing TTS controller');
$assert(str_contains($view, 'Tu cirugía quedó registrada. Puedes agregar otra cirugía'), 'M04 post-save guidance text missing');
$assert(str_contains($view, "conversationCoordinator.transition('ASKING', {reason: 'm04_additional_surgery'})"), 'M04 does not reopen the coordinator for another surgery');

echo "M04 multiple surgeries contract: PASS (4 records, duplicate guard, explicit finish, negative history, version guard)\n";
