<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\ConversationalInterviewService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$states = [
    'M01' => ['key' => 'M01', 'title' => 'Identificación', 'fields' => [['key' => 'edad', 'label' => 'Edad', 'value' => 'No registrado']], 'write_enabled' => false],
    'M02' => ['key' => 'M02', 'title' => 'Antecedentes familiares', 'records' => [['disease' => 'Diabetes Mellitus', 'detail' => null, 'status' => 'pending', 'version' => 'm02-v1']], 'write_enabled' => true, 'finalized' => false],
    'M03' => ['key' => 'M03', 'title' => 'Antecedentes no patológicos', 'questions' => [['idPregunta' => 6, 'pregunta' => '¿Fumas?', 'tipo' => 'select', 'respuesta' => '', 'idRespuesta' => 0, 'idTema' => 2, 'tema' => 'Tabaquismo', 'eligible' => true], ['idPregunta' => 12, 'pregunta' => '¿Con qué frecuencia consumes alcohol?', 'tipo' => 'input', 'respuesta' => '', 'idRespuesta' => 0, 'idTema' => 3, 'tema' => 'Alcoholismo', 'eligible' => true]], 'write_enabled' => true, 'version' => 'm03-v1'],
    'M04' => ['key' => 'M04', 'title' => 'Antecedentes quirúrgicos', 'records' => [['id' => 4, 'cirugia' => 'rodilla', 'fecha' => null, 'observaciones' => 'Año referido: 2019']], 'write_enabled' => true],
    'M05' => ['key' => 'M05', 'title' => 'Antecedentes patológicos', 'questions' => [['question_id' => 1, 'pregunta' => '¿Hipertensión?', 'tipo' => 'select', 'respuesta' => '', 'response_id' => 0, 'theme_id' => 1]], 'diseases' => [['disease_id' => 1, 'enfermedad' => 'Diabetes Mellitus', 'respuesta' => '', 'response_id' => 0, 'year_diagnostico' => null]], 'write_enabled' => true],
    'M06' => ['key' => 'M06', 'title' => 'Medicación actual', 'records' => [['id' => 1, 'medicamento' => '', 'descripcion' => '', 'tiempo_uso' => '', 'dosis' => '', 'medico' => '']], 'write_enabled' => true],
    'M07' => ['key' => 'M07', 'title' => 'Medicamentos para el dolor', 'categories' => [['theme_id' => 1, 'theme' => 'Analgésicos', 'items' => [['question_id' => 1, 'question' => 'Medicamento sintético', 'response_id' => 0, 'fields' => ['respuesta' => '', 'dosis' => '', 'resultados' => '', 'consumo' => '', 'descripcion' => ''], 'status' => 'pending', 'other_blocked' => false]]]], 'write_enabled' => false, 'write_blocker' => 'M07_CANONICAL_DISABLED', 'canonical_read' => true, 'finalization_enabled' => false],
    'M08' => ['key' => 'M08', 'title' => 'Tratamientos', 'items' => [['id_tratamiento' => 1, 'tratamiento' => 'Tratamiento sintético', 'fields' => ['utilizo' => ''], 'status' => 'pending']], 'write_enabled' => true],
    'M09' => ['key' => 'M09', 'title' => 'Evaluación del dolor', 'evaluation' => null, 'write_enabled' => true],
    'M09Map' => ['images' => ['frente' => ['available' => false], 'espalda' => ['available' => false]], 'version' => 'map-v1'],
];

$contract = (new ConversationalInterviewService())->buildUnifiedContract($states);
$assert(($contract['schema'] ?? '') === 'conversation-contract-v1', 'contract schema');
$assert(($contract['module_order'] ?? []) === ['M01', 'M02', 'M03', 'M04', 'M05', 'M06', 'M07', 'M08', 'M09'], 'module order');
$assert(count($contract['modules'] ?? []) === 9, 'all modules present');
$assert(is_string($contract['version'] ?? null) && strlen($contract['version']) === 64, 'contract version');

$allIds = [];
foreach ($contract['modules'] as $moduleId => $module) {
    $assert(($module['module_id'] ?? '') === $moduleId, $moduleId . ': module identity');
    $assert(is_array($module['questions'] ?? null), $moduleId . ': questions');
    foreach ($module['questions'] as $question) {
        foreach (['stable_id', 'original_question', 'visible_text', 'instruction', 'help', 'voice', 'options', 'dependencies', 'clarification_conditions', 'unknown_response', 'confirmation', 'advance', 'response', 'navigation', 'completion'] as $field) {
            $assert(array_key_exists($field, $question), $moduleId . ': missing ' . $field);
        }
        foreach (['QUESTION', 'INSTRUCTION', 'HELP', 'CLARIFICATION', 'CONFIRMATION', 'SUCCESS', 'ERROR', 'RESUME', 'COMPLETION'] as $voiceEvent) {
            $assert(array_key_exists($voiceEvent, $question['voice']), $moduleId . ': missing voice ' . $voiceEvent);
        }
        $allIds[] = $question['stable_id'];
        $assert(($question['unknown_response']['never_infer_negative'] ?? false) === true, $moduleId . ': unknown response safety');
        $assert(($question['advance']['after'] ?? '') !== 'interpretation_only', $moduleId . ': false advance');
    }
}
$assert(count($allIds) === count(array_unique($allIds)), 'stable question ids are unique');
$assert(count($contract['question_index'] ?? []) === count($allIds), 'question index complete');
$assert(isset($contract['question_index']['M03:question:6']), 'M03 stable identity');
$assert(str_contains($contract['question_index']['M03:question:6']['help'], 'Tabaquismo') && str_contains($contract['question_index']['M03:question:6']['help'], '¿Fumas?'), 'Tabaquismo contextual help');
$assert(str_contains($contract['question_index']['M03:question:12']['help'], 'Alcoholismo') && str_contains($contract['question_index']['M03:question:12']['help'], '¿Con qué frecuencia consumes alcohol?') && !str_contains($contract['question_index']['M03:question:12']['help'], 'Tabaquismo'), 'Alcoholismo contextual help');
$assert($contract['question_index']['M03:question:12']['voice']['HELP'] === $contract['question_index']['M03:question:12']['help'], 'Help text and TTS remain synchronized');
$assert(($contract['question_index']['M04:record:4']['response']['temporal_precision'] ?? '') === 'partial_or_relative', 'M04 temporal precision preserved');
$assert(($contract['modules']['M07']['state']['blocked'] ?? false) === true, 'M07 write remains blocked');
$assert(($contract['modules']['M07']['state']['finalization_enabled'] ?? true) === false, 'M07 finalization remains disabled');
$assert(($contract['question_index']['M09:body-map']['source'] ?? '') === 'existing_visual_component', 'M09 existing map preserved');
$assert(($contract['question_index']['M06:current-medication:1']['response']['status'] ?? '') === 'pending', 'M06 pending preserved');
$assert(isset($contract['voice']['WELCOME'], $contract['voice']['SESSION_EXPIRED']), 'generic voice contract present');
$assert(($contract['voice']['FIRST_START'] ?? '') === 'Bienvenido a tu entrevista de historia clínica. Te realizaré algunas preguntas para conocer tus antecedentes y registrar tu información médica. Responde con tus propias palabras. Después podrás revisar y confirmar tus respuestas antes de guardarlas. Si necesitas una explicación, puedes utilizar el botón Ayuda. Comencemos.', 'FIRST_START literal welcome');
$freshStates = $states;
$freshStates['M04']['records'] = [];
$freshStates['M06']['records'] = [];
$freshStates['M09']['evaluation'] = null;
$freshContract = (new ConversationalInterviewService())->buildUnifiedContract($freshStates);
$assert(($freshContract['interview_progress']['has_confirmed_responses'] ?? true) === false, 'Read-only M01 data must not create interview progress');
$assert(($freshContract['interview_progress']['first_pending_module_id'] ?? '') === 'M02', 'First pending module must be server-authorized');
$assert(($freshContract['interview_progress']['first_pending_stable_id'] ?? '') === 'M02:family:diabetes-mellitus', 'First pending question must be server-authorized');
$assert(isset($contract['modules']['M07']['voice']['MODULE_BLOCKED']), 'module blocked voice contract present');
$assert(isset($contract['question_index']['M03:question:6']['voice']['OPTIONS'], $contract['question_index']['M03:question:6']['voice']['SAVING']), 'question voice options and saving present');

$root = dirname(__DIR__, 2);
$serviceSource = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');
$controllerSource = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$viewSource = file_get_contents($root . '/app/views/paciente/entrevista.php');
$assert(str_contains($serviceSource, 'buildUnifiedContract'), 'service integration');
$assert(!str_contains($serviceSource, 'Retomaremos desde la primera pregunta pendiente sin cambiar respuestas anteriores.'), 'obsolete resume narration must not remain in executable service code');
$assert(($contract['question_index']['M03:question:12']['voice']['RESUME'] ?? '') === 'Continuemos con tu entrevista.', 'resume narration must be brief and non-technical');
$assert(str_contains($controllerSource, 'buildUnifiedContract'), 'controller integration');
$assert(str_contains($viewSource, 'window.patientInterviewContract'), 'frontend contract exposure');

echo "PASS unified conversational contract M01-M09\n";
echo "UNIFIED_CONVERSATIONAL_CONTRACT: PASS\n";
