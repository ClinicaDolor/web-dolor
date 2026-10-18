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
        'questions' => [],
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
$assert($pendingXPath->query('//*[@data-module="M02"]//*[@data-m02-disease]')->length === 1, 'rendered M02 must contain one active disease row');
$assert($pendingXPath->query('//*[@data-module="M02"]//*[@data-m02-input]')->length === 1, 'rendered M02 must contain one response input');
$assert($pendingXPath->query('//*[@data-module="M02"]//*[@data-m02-resolution-action]')->length === 0, 'pending M02 must not render the finalization action');
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
$assert($readyXPath->query('//*[@data-module="M02"]//*[@data-m02-disease]')->length === 0, 'ready M02 must not render all documented cards');
$assert($readyXPath->query('//*[@data-module="M02"]//*[@data-m02-input]')->length === 0, 'ready M02 must not render a response input');
$assert($readyXPath->query('//*[@data-module="M02"]//*[@data-m02-resolution-action]')->length === 1, 'ready M02 must render one finalization action');
$assert(str_contains($readyModule->textContent, 'Tus antecedentes familiares están registrados'), 'ready M02 must explain the finalization action');

echo "M02 single-question rendered contract: PASS (pending and ready-to-finalize states)\n";
