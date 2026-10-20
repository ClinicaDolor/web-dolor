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
$assert($pendingXPath->query('//*[@data-module="M02"]//*[@data-m02-quick]')->length === 1, 'M02 must expose one affirmative quick action');
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
$assert($readyXPath->query('//*[@data-module="M02"]//*[@data-m02-input]')->length === 0, 'ready M02 must not render a response input');
$assert($readyXPath->query('//*[@data-module="M02"]//*[@data-m02-resolution-action]')->length === 1, 'ready M02 must render one finalization action');
$assert(str_contains($readyModule->textContent, 'Tus antecedentes familiares están registrados'), 'ready M02 must explain the finalization action');
$readyAction = $readyContract['modules']['M02']['conversation_state'] ?? [];
$assert(($readyAction['action_id'] ?? '') === 'M02:action:finalize' && ($readyAction['stable_id'] ?? null) === null, 'ready M02 must expose a separate validated action id');
$assert(($readyAction['voice_text'] ?? '') === 'Tus antecedentes familiares están registrados. Puedes finalizar esta sección y continuar.', 'ready M02 TTS must use the approved finalization narration');
$assert(str_contains($pendingHtml, 'body.patient-interview-page { overflow-x:clip; }') && str_contains($readyHtml, 'body.patient-interview-page { overflow-x:clip; }'), 'page-level overflow protection must remain explicit');
$assert(!preg_match('/\.patient-interview-main\s*\{[^}]*overflow-x\s*:\s*clip/i', $pendingHtml) && !preg_match('/\.patient-interview-main\s*\{[^}]*overflow-x\s*:\s*clip/i', $readyHtml), 'responsive layout must not hide inner overflow with overflow-x:clip');
$assert(str_contains($pendingHtml, 'max-width:1199.98px') && str_contains($pendingHtml, 'max-width:767.98px') && str_contains($pendingHtml, 'max-width:430px'), 'responsive header breakpoints must cover desktop, tablet and narrow layouts');
$assert(str_contains($pendingHtml, 'Preparando la entrevista'), 'progress must not render a false Etapa 1 de 1 placeholder before the client computes the canonical stage count');

echo "M02 single-question rendered contract: PASS (pending and ready-to-finalize states)\n";
