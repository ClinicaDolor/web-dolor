<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$adapterSource = file_get_contents($root . '/app/Services/ConversationalM05Adapter.php');
$finalizationSource = file_get_contents($root . '/app/Services/ConversationalModuleFinalizationService.php');
$controllerSource = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$viewSource = file_get_contents($root . '/app/views/paciente/entrevista.php');
$assert(is_string($adapterSource) && is_string($finalizationSource) && is_string($controllerSource) && is_string($viewSource), 'M05 contract sources unavailable');

// Use only an in-memory catalog for the deterministic open-text path. This
// intentionally does not connect to MySQL or invoke clinical persistence.
$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->exec('CREATE TABLE pac_preguntas_modulo_5 (id INTEGER PRIMARY KEY, id_tema INTEGER NOT NULL, pregunta TEXT NOT NULL, tipo TEXT NOT NULL)');
$insert = $sqlite->prepare('INSERT INTO pac_preguntas_modulo_5 (id,id_tema,pregunta,tipo) VALUES (:id,:theme,:question,:type)');
$insert->execute([':id' => 501, ':theme' => 50, ':question' => '¿A qué medicamentos es alérgico?', ':type' => 'input']);
$sqlite->exec('CREATE TABLE pac_enfermedades_modulo_5 (id INTEGER PRIMARY KEY, descripcion TEXT NOT NULL)');
$diseaseInsert = $sqlite->prepare('INSERT INTO pac_enfermedades_modulo_5 (id,descripcion) VALUES (:id,:description)');
$diseaseInsert->execute([':id' => 601, ':description' => 'Presión alta (Hipertensión arterial sistemática)']);
$diseaseInsert->execute([':id' => 602, ':description' => 'Diabetes Mellitus']);
$adapter = new \App\Services\ConversationalM05Adapter($sqlite);
$proposal = $adapter->interpret(501, 'Amoxicilina');
$assert(($proposal['kind'] ?? '') === 'm05_proposal', 'Amoxicilina was not accepted as an M05 V1 proposal');
$assert(($proposal['target'] ?? '') === 'v1', 'Open-text proposal changed M05 variant');
$assert(($proposal['question_type'] ?? '') === 'input', 'Medication-allergy detail was treated as binary');
$assert(($proposal['structured_value'] ?? '') === 'Amoxicilina', 'Open-text value was not preserved');
$assert(($proposal['direct_save'] ?? false) === true, 'Valid open-text proposal is not directly admissible');

$yes = App\Services\ConversationalM05Adapter::resolveV1Eligibility([
    ['theme_id' => 50, 'question_id' => 500, 'tipo' => 'select', 'respuesta' => 'Si'],
    ['theme_id' => 50, 'question_id' => 501, 'tipo' => 'input', 'respuesta' => ''],
]);
$assert(($yes[1]['eligible'] ?? false) === true, 'Affirmative allergy parent did not enable medication detail');
$assert(($yes[1]['dependency_parent_question_id'] ?? 0) === 500, 'Medication detail parent was not retained');

$no = App\Services\ConversationalM05Adapter::resolveV1Eligibility([
    ['theme_id' => 50, 'question_id' => 500, 'tipo' => 'select', 'respuesta' => 'No'],
    ['theme_id' => 50, 'question_id' => 501, 'tipo' => 'input', 'respuesta' => ''],
]);
$assert(($no[1]['eligible'] ?? true) === false, 'Negative allergy parent did not skip medication detail');
$assert(($no[1]['dependency_state'] ?? '') === 'skipped_by_negative', 'Skipped detail state is not explicit');

$hypertensionNo = $adapter->interpretV2(601, 'No');
$assert(($hypertensionNo['kind'] ?? '') === 'm05_v2_proposal', 'Hypertension No did not produce a V2 proposal');
$assert(($hypertensionNo['changes'][0]['detalle'] ?? '') === 'No', 'Hypertension No was not preserved as a negative answer');
$hypertensionYes = $adapter->interpretV2(601, 'Sí');
$assert(($hypertensionYes['kind'] ?? '') === 'm05_v2_proposal', 'Hypertension Sí did not produce a V2 proposal');
$unknown = $adapter->interpretV2(601, 'No lo sé');
$assert(($unknown['kind'] ?? '') === 'clarification', 'Unknown V2 response was converted into No');
$diabetesMissing = $adapter->interpretV2(602, 'Sí');
$assert(($diabetesMissing['kind'] ?? '') === 'clarification', 'Diabetes Sí without dependent details was accepted');
$diabetesComplete = $adapter->interpretV2(602, 'Sí, Tipo 2, 2020');
$assert(($diabetesComplete['kind'] ?? '') === 'm05_v2_proposal', 'Diabetes Sí with type and year was not proposed');

// This is the regression that previously made a valid module appear stuck:
// an ineligible dependent row must not block completion after an explicit No.
$finalizer = new App\Services\ConversationalModuleFinalizationService(new PDO('sqlite::memory:'));
$completeMethod = new ReflectionMethod($finalizer, 'm05Complete');
$completeMethod->setAccessible(true);
$assert($completeMethod->invoke($finalizer, [
    'questions' => [
        ['eligible' => true, 'respuesta' => 'Si'],
        ['eligible' => false, 'respuesta' => ''],
    ],
    'diseases' => [],
]) === true, 'Skipped M05 dependency still blocks completion');
$assert($completeMethod->invoke($finalizer, [
    'questions' => [
        ['eligible' => true, 'respuesta' => 'Si'],
        ['eligible' => true, 'respuesta' => ''],
    ],
    'diseases' => [],
]) === false, 'An eligible unanswered M05 question was ignored');
$assert($completeMethod->invoke($finalizer, [
    'questions' => [
        ['eligible' => true, 'needs_clarification' => true, 'respuesta' => 'tal vez'],
    ],
    'diseases' => [],
]) === false, 'An ambiguous M05 parent was treated as complete');
$assert($completeMethod->invoke($finalizer, [
    'questions' => [],
    'diseases' => [['enfermedad' => 'Diabetes Mellitus', 'respuesta' => 'Si', 'tipo' => '', 'year_diagnostico' => '']],
]) === false, 'Diabetes without dependent details was treated as complete');
$assert($completeMethod->invoke($finalizer, [
    'questions' => [],
    'diseases' => [['enfermedad' => 'Diabetes Mellitus', 'respuesta' => 'Si', 'tipo' => 'Tipo 2', 'year_diagnostico' => '2020']],
]) === true, 'Diabetes with dependent details was not treated as complete');

$assert(str_contains($adapterSource, "'readback'=>true"), 'M05 adapter does not expose successful readback');
$assert(str_contains($adapterSource, "'next_question'=>\$nextQuestion"), 'M05 adapter does not expose the next eligible V1 question');
$assert(str_contains($adapterSource, "'next_disease'=>\$nextDisease"), 'M05 adapter does not expose the next M05 V2 target');
$assert(str_contains($adapterSource, "'stable_id'=>'M05:v1:'"), 'M05 V1 next target does not expose its canonical stable_id');
$assert(str_contains($adapterSource, "'stable_id'=>'M05:v2:'"), 'M05 V2 next target does not expose its canonical stable_id');
$assert(str_contains($controllerSource, "'readback' => \$result['readback'] ?? false"), 'M05 controller does not forward readback');
$assert(str_contains($controllerSource, "'next_question' => \$result['next_question'] ?? null"), 'M05 controller does not forward next_question');
$assert(str_contains($viewSource, 'data.readback!==true'), 'M05 browser flow does not require readback before advancing');
$assert(str_contains($viewSource, 'showNextM05Question(card,data.next_question)'), 'M05 browser flow does not render the next question');
$assert(str_contains($viewSource, 'data.next_disease || data.finalized === true'), 'M05 browser flow does not leave V1 when the next target is V2 or finalized');
$assert(str_contains($viewSource, 'submitM05V1(card'), 'M05 Enviar handler is not connected to the active card');
$assert(str_contains($viewSource, 'conversationCoordinator.stableIdForQuestion(\'M05\', next.question_id)'), 'M05 navigation does not restore the canonical stable_id');
$assert(str_contains($viewSource, "`M05:v2:\${next.disease_id}`"), 'M05 V2 navigation does not restore the canonical stable_id');
$assert(str_contains($viewSource, 'data-m05-v2-followup'), 'M05 V2 does not preserve temporary affirmative follow-up context');
$assert(str_contains($viewSource, 'm05V2FollowupText'), 'M05 V2 follow-up input is not combined with its pending affirmative context');
$assert(str_contains($viewSource, 'rememberM05V2Followup'), 'M05 V2 follow-up context does not preserve only recognized details');
$eligibilityGuard = "if ((\$row['eligible'] ?? true) !== true) continue;";
$assert(str_contains($finalizationSource, $eligibilityGuard), 'M05 completion does not preserve dependency eligibility');

echo "M05 medication-allergy submit contract: PASS (open text, dependency, readback, next target and DOM navigation)\n";
