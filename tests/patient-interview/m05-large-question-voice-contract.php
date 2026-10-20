<?php

declare(strict_types=1);

/**
 * Focal M05 regression. This test is static/deterministic only: it does not
 * open PDO, HTTP, Apache or a clinical database.
 */
$root = dirname(__DIR__, 2);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

require $root . '/vendor/autoload.php';

use App\Services\ConversationalInterviewService;

$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$voice = file_get_contents($root . '/public/assets/js/patient-interview-voice.js');
$service = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');
$m07Service = file_get_contents($root . '/app/Services/M07CanonicalService.php');

$states = [
    'M05' => [
        'key' => 'M05',
        'title' => 'Antecedentes personales patológicos',
        'questions' => [[
            'question_id' => 21,
            'pregunta' => '¿Ha experimentado alguna sensación de falta de aire?',
            'tipo' => 'input',
            'respuesta' => '',
            'response_id' => 0,
            'id_tema' => 4,
            'tema' => 'Oxigenación',
        ]],
        'diseases' => [[
            'disease_id' => 7,
            'enfermedad' => 'Diabetes Mellitus',
            'respuesta' => '',
            'response_id' => 0,
            'year_diagnostico' => null,
        ]],
        'write_enabled' => true,
        'disease_write_enabled' => true,
    ],
];
$contract = (new ConversationalInterviewService())->buildUnifiedContract($states);
$v1 = $contract['question_index']['M05:v1:21'] ?? null;
$v2 = $contract['question_index']['M05:v2:7'] ?? null;

$assert(is_array($v1) && ($v1['module_id'] ?? '') === 'M05' && ($v1['variant'] ?? '') === 'v1', 'M05 V1 canonical question missing');
$assert(is_array($v2) && ($v2['module_id'] ?? '') === 'M05' && ($v2['variant'] ?? '') === 'v2', 'M05 V2 canonical question missing');
$assert(str_contains($view, 'data-m05-theme-label') && str_contains($view, '<h3 id="m05-question-title" class="interview-question-text" data-m05-question-label>'), 'M05 V1 question is not semantic and large');
$assert(str_contains($view, '<h3 id="m05-v2-question-title" class="interview-question-text" data-m05-v2-question-label>'), 'M05 V2 question is not semantic and large');
$assert(str_contains($view, 'aria-label="Enviar respuesta M05"') && str_contains($view, 'aria-label="Enviar respuesta M05 V2"'), 'M05 send controls are missing');
$assert(!str_contains($view, 'data-m05-prompt') && !str_contains($view, 'data-m05-v2-prompt'), 'M05 retains the old secondary-question container');
$assert(str_contains($view, "['M04', 'M05', 'M05-V2'].includes(stage.card.dataset.module)"), 'M05 stages do not use canonical target resolution');
$assert(str_contains($view, "const contractModuleId = moduleId === 'M05-V2' ? 'M05' : moduleId"), 'M05 V2 contract module mapping is missing');
$assert(str_contains($view, "const contractModule = module === 'M05-V2' ? 'M05' : module"), 'M05 V2 visual stage label is not grouped under M05');
$assert(str_contains($view, "const variant = moduleId === 'M05-V2' ? 'v2' : 'v1'"), 'M05 variants are not separated by target resolution');
$assert(str_contains($view, 'voiceController.reportError({code: \'QUESTION_TEXT_MISSING\'})'), 'M05 voice resolution failure does not leave an explicit voice error state');
$assert(str_contains($voice, 'reportError(error = {})') && str_contains($voice, 'STATES.VOICE_ERROR'), 'Voice controller error state transition is missing');
$assert(str_contains($service, "'M05:v1:'") && str_contains($service, "'M05:v2:'"), 'M05 service stable ids are not variant-specific');
$assert(str_contains($view, 'chat-mic-button') && str_contains($view, "document.querySelectorAll('.patient-interview-answer input[type=\"text\"]"), 'M05 native microphone binding is not preserved');
$assert(str_contains($m07Service, 'M07_CANONICAL_DISABLED'), 'M07 closed gate marker disappeared');

echo "M05 large-question and voice-resolution contract: PASS (V1/V2, semantic DOM, send/mic controls, stable targets)\n";
