<?php

declare(strict_types=1);

use App\Models\AntecedentesNoPatologicosModel;
use App\Services\ConversationalM03Adapter;
use App\Services\ConversationalInterviewService;
use App\Services\ConversationalProvider;

/**
 * M03 unknown/not-remembered regression.
 *
 * This test uses the approved catalog and local doubles only. It does not open
 * PDO, HTTP, Apache, Gemini or any clinical persistence path.
 */
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

putenv('CLINICAL_AI_EXTERNAL_ENABLED=0');
$_ENV['CLINICAL_AI_EXTERNAL_ENABLED'] = '0';
$_SERVER['CLINICAL_AI_EXTERNAL_ENABLED'] = '0';

$catalog = require $root . '/tests/fixtures/p05-catalog.php';
$serviceSource = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');
$adapterSource = file_get_contents($root . '/app/Services/ConversationalM03Adapter.php');
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$questions = $catalog['questions'] ?? [];
$assert(count($questions) === 25, 'M03 catalog must contain all 25 official questions');
$byId = [];
foreach ($questions as $question) $byId[(int)$question['id']] = $question;

$assert(($byId[27]['tipo'] ?? '') === 'input' && ($byId[28]['tipo'] ?? '') === 'input', 'Blood and Rh must remain the official input questions');
$assert(($byId[27]['id_tema'] ?? 0) === 7 && ($byId[28]['id_tema'] ?? 0) === 7, 'Blood and Rh must remain in theme 7');
$assert(str_contains($serviceSource, "'unknown_response'") && str_contains($serviceSource, "'never_infer_negative' => true"), 'Shared contract must expose the non-negative unknown guard');
$assert(str_contains($serviceSource, "'completion' => ['required' => true, 'status' => \$persisted ? 'complete' : 'pending']"), 'Persisted semantic M03 states must not remain pending in the contract projection');
$assert(str_contains($adapterSource, "M03_TEXTUAL_UNKNOWN_VALUE_INVALID") && str_contains($adapterSource, "respuesta_text"), 'Persistence adapter must validate the textual unknown fallback');

final class M03UnknownLocalModel extends AntecedentesNoPatologicosModel
{
    public function __construct() {}

    public function obtenerPreguntasEntrevista(int $idPaciente, int $idTema = 1): array
    {
        $catalog = require dirname(__DIR__, 2) . '/tests/fixtures/p05-catalog.php';
        $rows = [];
        foreach ($catalog['questions'] as $question) {
            if ($idTema > 0 && (int)$question['id_tema'] !== $idTema) continue;
            $rows[] = [
                'idPregunta' => (int)$question['id'],
                'idTema' => (int)$question['id_tema'],
                'pregunta' => (string)$question['pregunta'],
                'tipo' => (string)$question['tipo'],
                'idRespuesta' => null,
                'respuesta' => '',
            ];
        }
        return $rows;
    }
}

final class M03UnknownProvider implements ConversationalProvider
{
    public int $calls = 0;

    public function interpretConversation(array $input): array
    {
        $this->calls++;
        return [
            'structured_value' => 'No',
            'interpretation' => 'No',
            'clarification' => '',
            'explanation' => '',
        ];
    }
}

$classifications = [
    'No lo sé' => 'UNKNOWN',
    'Lo desconozco' => 'UNKNOWN',
    'No recuerdo' => 'NOT_REMEMBERED',
    'No me acuerdo de mi factor Rh' => 'NOT_REMEMBERED',
    'No estoy seguro' => 'UNKNOWN',
];
foreach ($classifications as $utterance => $expected) {
    $assert(ConversationalInterviewService::classifyM03Unknown($utterance) === $expected, 'Unknown classification mismatch: ' . $expected);
}
$assert(ConversationalInterviewService::classifyM03Unknown('No') === null, 'A confirmed No must not be classified as unknown');
$assert(ConversationalInterviewService::classifyM03Unknown('O') === null, 'A concrete blood type must not be classified as unknown');
$assert(ConversationalInterviewService::isM03BloodRhQuestion(['idTema' => 7, 'pregunta' => '¿Cuál es su grupo sanguíneo?']), 'Blood group question must resolve from official context');
$assert(ConversationalInterviewService::isM03BloodRhQuestion(['idTema' => 7, 'pregunta' => '¿Cuál es su factor RH?']), 'Rh question must resolve from official context');
$assert(!ConversationalInterviewService::isM03BloodRhQuestion(['idTema' => 1, 'pregunta' => '¿Usted fuma cigarros de nicotina?']), 'Binary question must not use blood/Rh normalization');

$model = new M03UnknownLocalModel();
$provider = new M03UnknownProvider();
$service = new ConversationalInterviewService($model, $provider, null, 'm03-unknown-contract');

$bloodUnknown = $service->interpret(9301, 27, 'No lo sé');
$rhUnknown = $service->interpret(9301, 28, 'No recuerdo');
$sleepUnknown = $service->interpret(9301, 21, 'No estoy seguro');
$negative = $service->interpret(9301, 1, 'No');
$bloodNegative = $service->interpret(9301, 27, 'No');
$rhNegative = $service->interpret(9301, 28, 'Nunca');
$affirmative = $service->interpret(9301, 1, 'Sí');
$freeText = $service->interpret(9301, 2, 'Dos cigarrillos al día');

foreach ([$bloodUnknown, $rhUnknown, $sleepUnknown] as $proposal) {
    $assert(($proposal['kind'] ?? '') === 'm03_proposal', 'Unknown M03 answer was not prepared for textual persistence');
    $assert(in_array(($proposal['structured_value'] ?? ''), ['No lo sé', 'No recuerdo'], true), 'Unknown M03 answer was not normalized canonically');
    $assert(($proposal['can_confirm'] ?? false) === true && ($proposal['direct_save'] ?? false) === true, 'Textual unknown answer was not confirmable through the normal save path');
    $assert(($proposal['unknown_response']['persist'] ?? false) === true && ($proposal['unknown_response']['storage'] ?? '') === 'respuesta_text', 'Textual unknown answer was not marked for canonical persistence');
    $assert(($proposal['unknown_response']['never_infer_negative'] ?? false) === true, 'Unknown answer lost the negative-inference guard');
}
$assert(($bloodUnknown['semantic_state'] ?? '') === 'UNKNOWN', 'Blood unknown state was not preserved');
$assert(($rhUnknown['semantic_state'] ?? '') === 'NOT_REMEMBERED', 'Rh not-remembered state was not preserved');
$assert(($negative['kind'] ?? '') === 'm03_proposal' && ($negative['structured_value'] ?? '') === 'No', 'Negative and unknown semantics were conflated');
$assert(($bloodNegative['kind'] ?? '') === 'clarification' && ($bloodNegative['structured_value'] ?? null) === null, 'Blood group No was accepted as a clinical value');
$assert(($rhNegative['kind'] ?? '') === 'clarification' && ($rhNegative['structured_value'] ?? null) === null, 'Rh No was accepted as a clinical value');
$assert(($negative['direct_save'] ?? false) === true, 'Deterministic M03 negative selection was not marked direct-save eligible');
$assert(($affirmative['direct_save'] ?? false) === true && ($affirmative['structured_value'] ?? '') === 'Si', 'Deterministic M03 affirmative selection was not marked direct-save eligible');
$assert(($freeText['direct_save'] ?? false) === true, 'Valid deterministic M03 text was not marked direct-save eligible');
$assert($provider->calls === 0, 'Unknown M03 input must not be sent to the provider');

$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->exec('CREATE TABLE pac_temas_modulo_3 (id INTEGER PRIMARY KEY, tema TEXT NOT NULL)');
$sqlite->exec('CREATE TABLE pac_preguntas_modulo_3 (id INTEGER PRIMARY KEY, id_tema INTEGER NOT NULL, pregunta TEXT NOT NULL, tipo TEXT NOT NULL)');
$sqlite->exec('CREATE TABLE pac_respuestas_paciente_modulo_3 (id INTEGER PRIMARY KEY AUTOINCREMENT, id_pregunta INTEGER NOT NULL, id_paciente INTEGER NOT NULL, respuesta TEXT NOT NULL)');
$sqlite->exec('CREATE TABLE pac_historia_clinica_finalizar (id_modulo INTEGER NOT NULL, id_paciente INTEGER NOT NULL)');
$themeInsert = $sqlite->prepare('INSERT INTO pac_temas_modulo_3 (id, tema) VALUES (?, ?)');
foreach (($catalog['themes'] ?? []) as $theme) $themeInsert->execute([(int)$theme['id'], (string)$theme['tema']]);
$questionInsert = $sqlite->prepare('INSERT INTO pac_preguntas_modulo_3 (id, id_tema, pregunta, tipo) VALUES (?, ?, ?, ?)');
foreach ($questions as $question) $questionInsert->execute([(int)$question['id'], (int)$question['id_tema'], (string)$question['pregunta'], (string)$question['tipo']]);
$adapter = new ConversationalM03Adapter($sqlite);
$adapterAffirmative = $adapter->interpret(9301, 1, 'Sí');
$adapterNegative = $adapter->interpret(9301, 1, 'No');
$adapterBloodLiteral = $adapter->interpret(9301, 27, 'O+');
$assert(($adapterAffirmative['direct_save'] ?? false) === true && ($adapterAffirmative['can_confirm'] ?? false) === true, 'Adapter did not expose direct-save for a clear affirmative select');
$assert(($adapterNegative['direct_save'] ?? false) === true && ($adapterNegative['can_confirm'] ?? false) === true, 'Adapter did not expose direct-save for a clear negative select');
$assert(($adapterBloodLiteral['direct_save'] ?? false) === true && ($adapterBloodLiteral['can_confirm'] ?? false) === true, 'Adapter did not expose direct-save for a valid literal blood/Rh value');
$assert(($adapterAffirmative['requires_confirmation'] ?? true) === false, 'Clear affirmative select still requires redundant review');
$adapterUnknown = $adapter->interpret(9301, 27, 'No lo sé');
$assert(($adapterUnknown['kind'] ?? '') === 'm03_proposal' && ($adapterUnknown['semantic_state'] ?? '') === 'UNKNOWN', 'Adapter did not prepare the textual blood unknown state');
$assert(($adapterUnknown['structured_value'] ?? '') === 'No lo sé' && ($adapterUnknown['direct_save'] ?? false) === true, 'Adapter did not normalize UNKNOWN to its canonical text');
$assert(($adapterUnknown['unknown_response']['storage'] ?? '') === 'respuesta_text' && ($adapterUnknown['unknown_response']['never_infer_negative'] ?? false) === true, 'Textual UNKNOWN storage contract is incomplete');
$beforeRows = (int)$sqlite->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3')->fetchColumn();
$textualSaved = $adapter->capture(9301, $adapterUnknown, null);
$afterRows = (int)$sqlite->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3')->fetchColumn();
$assert((int)($textualSaved['resultado'] ?? 0) === 200 && ($textualSaved['readback'] ?? false) === true, 'Textual UNKNOWN capture/readback failed');
$assert($afterRows === $beforeRows + 1, 'Textual UNKNOWN capture did not create exactly one response row');
$textualRow = $sqlite->query('SELECT respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=27')->fetch(PDO::FETCH_ASSOC);
$assert(($textualRow['respuesta'] ?? '') === 'No lo sé', 'Textual UNKNOWN was not persisted canonically');
$textualRead = $adapter->read(9301);
$textualQuestion = array_values(array_filter($textualRead['questions'], static fn(array $row): bool => (int)$row['idPregunta'] === 27))[0] ?? [];
$assert(($textualQuestion['semanticState'] ?? '') === 'UNKNOWN', 'Textual UNKNOWN readback was not projected semantically');
$variant = $adapter->interpret(9301, 28, 'No recuerdo exactamente');
$assert(($variant['semantic_state'] ?? '') === 'NOT_REMEMBERED' && ($variant['structured_value'] ?? '') === 'No recuerdo', 'NOT_REMEMBERED variant was not normalized canonically');
$blockedNegative = $adapter->capture(9301, [
    'kind' => 'm03_proposal',
    'question_id' => 27,
    'theme_id' => 7,
    'structured_value' => 'No',
], null);
$assert(($blockedNegative['code'] ?? '') === 'M03_BLOOD_RH_NEGATIVE_INVALID', 'Blood group No capture was not rejected');
$assert($afterRows === (int)$sqlite->query('SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3')->fetchColumn(), 'Invalid blood/Rh negative capture modified the local fixture');

$sqlite->exec('ALTER TABLE pac_respuestas_paciente_modulo_3 ADD COLUMN estado_respuesta VARCHAR(24) NULL');
$sqlite->exec('ALTER TABLE pac_respuestas_paciente_modulo_3 ADD COLUMN origen_respuesta VARCHAR(32) NULL');
$sqlite->exec('ALTER TABLE pac_respuestas_paciente_modulo_3 ADD COLUMN estado_respuesta_at DATETIME NULL');
$semanticAdapter = new ConversationalM03Adapter($sqlite);
$semanticProposal = $semanticAdapter->interpret(9301, 28, 'No lo sé');
$assert(($semanticProposal['kind'] ?? '') === 'm03_proposal' && ($semanticProposal['semantic_state'] ?? '') === 'UNKNOWN', 'Migrated schema did not enable durable UNKNOWN proposal');
$semanticSaved = $semanticAdapter->capture(9301, $semanticProposal, null);
$assert((int)($semanticSaved['resultado'] ?? 0) === 200 && ($semanticSaved['readback'] ?? false) === true, 'Durable UNKNOWN capture/readback failed in the isolated fixture: ' . json_encode($semanticSaved, JSON_UNESCAPED_UNICODE));
$semanticRow = $sqlite->query('SELECT respuesta,estado_respuesta,origen_respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 AND id_pregunta=28')->fetch(PDO::FETCH_ASSOC);
$assert(($semanticRow['respuesta'] ?? null) === '' && ($semanticRow['estado_respuesta'] ?? '') === 'UNKNOWN' && ($semanticRow['origen_respuesta'] ?? '') === 'PATIENT_DECLARATION', 'Durable UNKNOWN representation is incorrect');
$notRememberedProposal = $semanticAdapter->interpret(9301, 27, 'No recuerdo');
$assert(($notRememberedProposal['semantic_state'] ?? '') === 'NOT_REMEMBERED', 'Migrated schema did not distinguish NOT_REMEMBERED');
$migrationSource = file_get_contents(dirname(__FILE__) . '/m03-unknown-state-migration.php');
$assert(str_contains($migrationSource, 'estado_respuesta') && str_contains($migrationSource, 'migration_execution'), 'Additive migration preparation is missing');
$assert(str_contains($migrationSource, "clinica_dolor_demo_20261010_final") && str_contains($migrationSource, "SELECT DATABASE()"), 'Migration must retain the exact synthetic database guard');
$assert(str_contains($migrationSource, "--allow-synthetic-write") && str_contains($migrationSource, 'CREATED_BEFORE_DDL'), 'Migration writes must require explicit authorization and a pre-DDL snapshot');
$assert(str_contains($migrationSource, 'hash_equals') && str_contains($migrationSource, 'RESTORED_PRE_MIGRATION_SCHEMA'), 'Restoration must verify snapshot integrity before changing data');

echo 'M03 unknown/not-remembered contract: PASS (25 questions, blood/Rh independent, no negative inference, external database untouched; isolated SQLite fixture only)' . PHP_EOL;
