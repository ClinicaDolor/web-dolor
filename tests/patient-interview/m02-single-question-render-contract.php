<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\ConversationalInterviewService;

foreach ([
    'RUTA_CSS' => '',
    'RUTA_IMAGES' => '',
    'RUTA_JS' => '',
    'RUTA_PUBLIC' => '',
    'SERVIDOR' => '/',
] as $constant => $value) {
    if (!defined($constant)) define($constant, $value);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$diseases = [
    'Enfermedades del corazón',
    'Hipertensión arterial sistémica',
    'Enfermedad cerebrovascular',
    'Diabetes Mellitus',
    'Cáncer',
    'Enfermedad renal',
];

$makeM02Records = static function (string $status) use ($diseases): array {
    return array_map(
        static fn(string $disease): array => [
            'disease' => $disease,
            'detail' => $status === 'documented' ? 'No' : null,
            'status' => $status,
            'version' => 'm02-render-v1',
        ],
        $diseases
    );
};

$render = static function (array $contract, array $m02): string {
    $data = [
        'title' => 'Entrevista sintética',
        'sidebar' => '',
        'idPaciente' => 9301,
        'csrf' => 'synthetic-csrf',
        'nativeSttEnabled' => false,
        'questions' => [[
            'idPregunta' => 901,
            'idTema' => 3,
            'tema' => 'M03 sintético',
            'pregunta' => 'Pregunta M03 sintética',
            'tipo' => 'input',
            'respuesta' => '',
            'idRespuesta' => 0,
            'eligible' => true,
            'parentQuestionId' => 0,
        ]],
        'm01' => [],
        'm02' => $m02,
        'm03' => [],
        'm04' => [],
        'm05' => [],
        'm06' => [],
        'm07' => [],
        'm08' => [],
        'm09' => [],
        'm09Map' => [],
        'conversationContract' => $contract,
    ];
    ob_start();
    include dirname(__DIR__, 2) . '/app/views/paciente/entrevista.php';
    return (string)ob_get_clean();
};

$service = new ConversationalInterviewService();
$pendingState = [
    'M02' => [
        'records' => $makeM02Records('pending'),
        'write_enabled' => true,
        'multi_relative_write_enabled' => false,
        'finalized' => false,
    ],
];
$pendingContract = $service->buildUnifiedContract($pendingState);
$pendingHtml = $render($pendingContract, $pendingState['M02']);
$pendingDom = new DOMDocument();
@$pendingDom->loadHTML($pendingHtml, LIBXML_NOERROR | LIBXML_NOWARNING);
$pendingXPath = new DOMXPath($pendingDom);
$pendingModule = $pendingXPath->query('//*[@data-module="M02"]')->item(0);
$assert($pendingModule instanceof DOMElement, 'rendered M02 card must exist');
$assert(str_contains((string)$pendingModule->getAttribute('class'), 'patient-interview-question'), 'M02 must reuse the single-question card layout');
$assert($pendingXPath->query('//*[@data-module="M02" and @data-m02-disease]')->length === 1, 'rendered M02 must contain one active disease row');
$assert($pendingXPath->query('//*[@data-module="M02"]//*[@data-m02-input]')->length === 1, 'rendered M02 must contain one response input');
$assert($pendingXPath->query('//*[@data-module="M02"]//*[@data-m02-interpret and not(@hidden)]')->length === 1, 'M02 must keep the primary Enviar button visible');
$assert($pendingXPath->query('//*[@data-module="M02"]//*[@data-m02-input]/ancestor::*[@data-m02-disease][1]')->length === 1, 'M02 input must belong to the active question');
$assert($pendingXPath->query('//*[@data-module="M02"]//*[@data-m02-interpret]/ancestor::*[@data-m02-disease][1]')->length === 1, 'M02 Enviar must belong to the active question');
$assert($pendingXPath->query('//*[@data-module="M02"]//h3[@id="m02-question-title"]')->length === 1, 'M02 must expose one clear question heading');
$assert($pendingXPath->query('//*[@data-module="M02"]//*[contains(@class,"patient-interview-context-grid")]')->length === 0, 'M02 must not render a secondary context/question card');
$assert(!str_contains($pendingModule->textContent, 'Pregunta activa ·'), 'M02 must not repeat the question in a secondary active-question row');
$assert(!str_contains($pendingModule->textContent, 'Responder después'), 'M02 must not expose the removed defer action');
$assert($pendingXPath->query('//*[@data-module="M02"]//*[@data-m02-quick]')->length === 0, 'M02 affirmative answers must use text and Enviar, not a quick Sí button');
$assert($pendingXPath->query('//*[@data-module="M02"]//*[@data-m02-safe-no]')->length === 1, 'M02 must expose one explicit negative quick action');
$assert($pendingXPath->query('//*[@data-module="M02"]//*[@data-m02-resolution-action]')->length === 0, 'pending M02 must not render the finalization action');
$assert(str_contains($pendingHtml, "querySelectorAll('.patient-interview-question[data-question-index]')"), 'M02 must not be included in the M03 question navigator');
$m02HiddenBeforeInitialization = $pendingModule->hasAttribute('hidden');
$m03QuestionNodes = $pendingXPath->query('//*[contains(concat(" ", normalize-space(@class), " "), " patient-interview-question ") and @data-question-index]');
$assert($m03QuestionNodes->length === 1, 'the initialization regression must include one M03 question card');
for ($i = 0; $i < $m03QuestionNodes->length; $i++) {
    $m03QuestionNodes->item($i)->setAttribute('hidden', 'hidden');
}
$assert($m02HiddenBeforeInitialization === false, 'M02 must start visible in the server-rendered ANSWERABLE state');
$assert(!$pendingModule->hasAttribute('hidden'), 'M02 must remain visible when the M03 navigator hides its own question cards');
$assert($pendingXPath->query('//*[@data-module="M02" and not(@hidden)]//*[@data-m02-input]')->length === 1, 'M02 input must remain in the visible DOM after coordinator initialization');
$assert($pendingXPath->query('//*[@data-module="M02" and not(@hidden)]//*[@data-m02-interpret and not(@hidden)]')->length === 1, 'M02 Enviar must remain in the visible DOM after coordinator initialization');
$assert((string)$pendingModule->getAttribute('data-m02-pending') === '6', 'server pending count must preserve all six pending records');
$activeStableId = (string)($pendingContract['interview_progress']['first_pending_stable_id'] ?? '');
$assert(str_starts_with($activeStableId, 'M02:family:enfermedades-del-coraz'), 'first pending stable_id must be canonical');
$assert(str_contains($pendingHtml, $activeStableId), 'rendered contract must expose the active stable_id');
$activeQuestion = $pendingContract['question_index'][$activeStableId] ?? [];
$assert(($activeQuestion['voice']['QUESTION'] ?? '') === ($activeQuestion['visible_text'] ?? ''), 'TTS must use the visible active question');
$assert(($activeQuestion['help'] ?? '') === 'Escribe qué familiar o familiares tienen esta enfermedad. Puedes responder con tus propias palabras, por ejemplo: “mi papá y mi mamá” o “mis padres y mi hermana mayor”. Si ninguno tiene esta enfermedad, selecciona “No, guardar y continuar”. Si no lo recuerdas, puedes indicarlo; no se registrará como Sí ni como No y la respuesta quedará sin guardar.', 'The M02 help must match free-text capture and the visible negative/uncertainty paths.');
$assert(($activeQuestion['voice']['HELP'] ?? '') === ($activeQuestion['help'] ?? ''), 'M02 help narration must match visible contextual help.');
$assert($pendingModule->getAttribute('data-stable-id') === $activeStableId, 'The rendered active M02 card must expose its canonical stable_id for contextual voice controls.');
$pendingQuestionHeader = $pendingXPath->query('//*[@data-module="M02"]//*[@data-m02-question-message]')->item(0);
$assert($pendingQuestionHeader instanceof DOMElement && !$pendingQuestionHeader->hasAttribute('hidden'), 'the normal M02 question must keep the shared clinical header visible.');
$assert($pendingXPath->query('//*[@data-module="M02"]//*[@data-m02-question-message]//*[@data-m02-question-text and contains(@class,"clinical-question-title")]')->length === 1
    && $pendingXPath->query('//*[@data-module="M02"]//*[@data-m02-question-instruction and not(@hidden)]')->length === 1,
    'normal M02 questions must preserve the shared large title and instruction.');
$secondM02Question = $pendingContract['question_index']['M02:family:hipertensi-n-arterial-sist-mica'] ?? [];
$assert(($secondM02Question['help'] ?? '') === ($activeQuestion['help'] ?? ''), 'Every M02 family question must use the same free-text, negative and uncertainty guidance.');
$assert(str_contains($pendingModule->textContent, 'Enfermedades del corazón'), 'active M02 question must be rendered');
foreach (array_slice($diseases, 1) as $otherDisease) {
    $assert(!str_contains($pendingModule->textContent, $otherDisease), 'non-active M02 questions must not be rendered');
}

$readyState = [
    'M02' => [
        'records' => $makeM02Records('documented'),
        'write_enabled' => true,
        'finalized' => false,
    ],
];
$readyContract = $service->buildUnifiedContract($readyState);
$readyHtml = $render($readyContract, $readyState['M02']);
$readyDom = new DOMDocument();
@$readyDom->loadHTML($readyHtml, LIBXML_NOERROR | LIBXML_NOWARNING);
$readyXPath = new DOMXPath($readyDom);
$readyModule = $readyXPath->query('//*[@data-module="M02"]')->item(0);
$assert($readyModule instanceof DOMElement, 'ready M02 card must exist');
$assert($readyXPath->query('//*[@data-module="M02" and @data-m02-disease]')->length === 0, 'ready M02 must not render all documented cards');
$assert($readyXPath->query('//*[@data-module="M02"]//*[@data-m02-input]')->length === 0, 'ready-to-finalize M02 must not render an answer composer before consultation.');
$assert($readyXPath->query('//*[@data-module="M02"]//*[@data-m02-resolution-action]')->length === 1, 'ready M02 must render one finalization action');
$readyHeader = $readyXPath->query('//*[@data-module="M02"]//*[@data-m02-question-message and not(@hidden)]')->item(0);
$assert($readyHeader instanceof DOMElement && str_contains((string)$readyHeader->getAttribute('class'), 'clinical-question-header'), 'ready M02 must reuse the visible shared clinical question header.');
$readyTitle = $readyXPath->query('//*[@data-module="M02"]//*[@data-m02-question-message]//*[@data-m02-question-text]')->item(0);
$readyVisibleVoiceText = trim((string)$readyTitle?->textContent);
$assert($readyVisibleVoiceText === 'Tus antecedentes familiares están registrados. ¿Deseas finalizar esta sección y continuar?', 'ready M02 must render the canonical confirmation text visibly');
$assert($readyTitle instanceof DOMElement && str_contains((string)$readyTitle->getAttribute('class'), 'clinical-question-title')
    && $readyTitle->getAttribute('data-m02-action-id') === 'M02:action:finalize', 'the finalization confirmation must use normal M02 title typography and the canonical action identity.');
$assert($readyXPath->query('//*[@data-module="M02"]//*[@data-m02-question-instruction and @hidden]')->length === 1, 'finalization must hide the normal answer instruction while retaining the shared header.');
$assert($readyXPath->query('//*[@data-module="M02"]//*[contains(concat(" ", normalize-space(@class), " "), " m02-finalization-actions ")]/*[self::div and contains(concat(" ", normalize-space(@class), " "), " patient-interview-message ")]')->length === 0,
    'the finalization action area must not render a second confirmation message/card.');
$assert($readyXPath->query('//*[@data-module="M02"]//*[contains(concat(" ", normalize-space(@class), " "), " m02-finalization-actions ")]//*[@data-m02-resolution-action]')->length === 1, 'the finalization action must remain inside its locally spaced wrapper');
$assert($readyXPath->query('//*[@data-module="M02"]//*[@data-m02-dictation-status]')->length === 0, 'ready M02 must not render an input-specific dictation status without a response composer');
$readyAction = $readyContract['modules']['M02']['conversation_state'] ?? [];
$assert(($readyAction['action_id'] ?? '') === 'M02:action:finalize' && ($readyAction['stable_id'] ?? null) === null, 'ready M02 must expose a separate validated action id');
$assert(($readyAction['voice_text'] ?? '') === $readyVisibleVoiceText, 'ready M02 TTS must exactly match the visible confirmation');
$assert(str_contains($readyHtml, "if (module?.conversation_state?.state === 'READY_TO_FINALIZE')")
    && strpos($readyHtml, "if (module?.conversation_state?.state === 'READY_TO_FINALIZE')") < strpos($readyHtml, 'const eligibility = diagnoseM02DictationEligibility(target, input);')
    && str_contains($readyHtml, "inlineStatus.textContent = '';"), 'canonical finalization must exit dictation diagnostics and clear its stale technical status before eligibility checks');
$startRecognitionPosition = strpos($readyHtml, 'const startRecognition = async (button, input) => {');
$readyDictationGuardPosition = strpos($readyHtml, "if (conversationContract.modules?.M02?.conversation_state?.state === 'READY_TO_FINALIZE')", $startRecognitionPosition ?: 0);
$readyDictationEligibilityPosition = strpos($readyHtml, 'const eligibility = diagnoseM02DictationEligibility(target, input);', $startRecognitionPosition ?: 0);
$assert($startRecognitionPosition !== false && $readyDictationGuardPosition !== false
    && $readyDictationEligibilityPosition !== false && $readyDictationGuardPosition < $readyDictationEligibilityPosition,
    'a stale microphone event must not surface internal no-input diagnostics during canonical finalization');
$assert(str_contains($readyHtml, "conversationCoordinator.voiceEvent(action ? 'ACTION' : 'QUESTION', activeTarget, false)")
    && str_contains($readyHtml, 'event?.action_id === activeTarget'), 'the existing repeat control must validate and replay the canonical finalization action');
$assert(str_contains($readyHtml, "actionArea.className = 'm02-finalization-actions'")
    && str_contains($readyHtml, 'conversationContract.modules?.M02?.conversation_state?.voice_text')
    && str_contains($readyHtml, 'questionTitle.textContent = finalizationText')
    && str_contains($readyHtml, "questionTitle.dataset.m02ActionId = 'M02:action:finalize'")
    && str_contains($readyHtml, 'if (instruction) instruction.hidden = true;'), 'a dynamically exposed finalization action must reuse the shared canonical header and action identity.');
$assert(str_contains($readyHtml, 'Antes de continuar')
    && str_contains($readyHtml, 'Ya respondiste las preguntas de antecedentes familiares. Revisa la información y confirma cuando quieras finalizar esta sección.')
    && str_contains($readyHtml, "m02Card.dataset.m02Consultation !== '1'"), 'contextual guidance must switch only for the active canonical M02 finalization state, not historical consultation');
$assert(str_contains($readyHtml, '.m02-finalization-actions { display:flex; flex-direction:column; align-items:flex-start; margin:1.2rem .25rem .75rem; }'), 'M02 finalization button spacing must remain scoped below the shared question header.');
$assert(str_contains($pendingHtml, 'body.patient-interview-page { overflow-x:clip; }') && str_contains($readyHtml, 'body.patient-interview-page { overflow-x:clip; }'), 'page-level overflow protection must remain explicit');
$assert(!preg_match('/\.patient-interview-main\s*\{[^}]*overflow-x\s*:\s*clip/i', $pendingHtml) && !preg_match('/\.patient-interview-main\s*\{[^}]*overflow-x\s*:\s*clip/i', $readyHtml), 'responsive layout must not hide inner overflow with overflow-x:clip');
$assert(str_contains($pendingHtml, 'max-width:1199.98px') && str_contains($pendingHtml, 'max-width:767.98px') && str_contains($pendingHtml, 'max-width:430px'), 'responsive header breakpoints must cover desktop, tablet and narrow layouts');
$assert(str_contains($pendingHtml, 'Preparando la entrevista'), 'progress must not render a false Etapa 1 de 1 placeholder before the client computes the canonical stage count');

$finalizedRecords = $makeM02Records('documented');
$finalizedRecords[0]['detail'] = 'Si';
$finalizedRecords[0]['relative_description'] = 'Mi tía materna';
$finalizedState = [
    'M02' => [
        'records' => $finalizedRecords,
        'write_enabled' => true,
        'finalized' => true,
    ],
];
$finalizedContract = $service->buildUnifiedContract($finalizedState);
$finalizedHtml = $render($finalizedContract, $finalizedState['M02']);
$finalizedDom = new DOMDocument();
@$finalizedDom->loadHTML($finalizedHtml, LIBXML_NOERROR | LIBXML_NOWARNING);
$finalizedXPath = new DOMXPath($finalizedDom);
$finalizedCard = $finalizedXPath->query('//*[@data-module="M02"]')->item(0);
$assert($finalizedCard instanceof DOMElement, 'finalized M02 must retain its single read-only consultation card');
$assert($finalizedXPath->query('//*[@data-module="M02"]//*[@data-m02-input and @readonly and not(@disabled)]')->length === 1, 'finalized M02 consultation must expose a readable but non-editable answer field.');
$assert($finalizedXPath->query('//*[@data-module="M02"]//*[@data-m02-question-source and @data-m02-answer="Mi tía materna"]')->length === 1, 'finalized M02 source must preserve the exact saved free-text answer for consultation.');
$assert($finalizedXPath->query('//*[@data-module="M02"]//*[@data-m02-question-source and @data-m02-answer="No"]')->length === 5, 'finalized M02 sources must preserve explicit negative answers as No.');
$assert($finalizedXPath->query('//*[@data-module="M02"]//*[@data-m02-safe-no]')->length === 0, 'finalized M02 consultation must not expose a write action.');

echo "M02 single-question rendered contract: PASS (pending and ready-to-finalize states)\n";
