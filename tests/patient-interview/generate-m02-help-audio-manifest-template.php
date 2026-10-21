<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\ConversationalInterviewService;

$root = dirname(__DIR__, 2);
$questionManifestPath = $root . '/public/assets/audio/patient-interview/m02/piper-claude/manifest.json';
$questionManifest = json_decode((string)file_get_contents($questionManifestPath), true);
if (!is_array($questionManifest) || !is_array($questionManifest['entries'] ?? null)) {
    fwrite(STDERR, "M02_HELP_MANIFEST_TEMPLATE_ERROR=QUESTION_MANIFEST_UNAVAILABLE\n");
    exit(1);
}

$diseases = [
    'Enfermedades del corazón',
    'Hipertensión arterial sistémica',
    'Enfermedad cerebrovascular',
    'Diabetes Mellitus',
    'Cáncer',
    'Enfermedad familiar agregada',
];
$records = array_map(static fn(string $disease): array => [
    'disease' => $disease,
    'detail' => null,
    'status' => 'pending',
    'version' => 'template-only',
], $diseases);
$contract = (new ConversationalInterviewService())->buildUnifiedContract([
    'M02' => ['records' => $records, 'write_enabled' => false, 'finalized' => false],
]);

$questions = [];
foreach ($contract['question_index'] as $question) {
    if (($question['module_id'] ?? null) === 'M02' && str_starts_with((string)($question['stable_id'] ?? ''), 'M02:family:')) {
        $questions[$question['stable_id']] = $question;
    }
}
$entries = [];
$helpCatalogByHash = [];
foreach ($questionManifest['entries'] as $questionAudio) {
    $stableId = (string)($questionAudio['stable_id'] ?? '');
    $question = $questions[$stableId] ?? null;
    if (!is_array($question) || !is_string($question['voice']['HELP'] ?? null) || trim($question['voice']['HELP']) === '') {
        fwrite(STDERR, "M02_HELP_MANIFEST_TEMPLATE_ERROR=CANONICAL_HELP_UNAVAILABLE\n");
        exit(1);
    }
    unset($questionAudio['help_audio']);
    $entries[] = $questionAudio;
}
foreach ($questions as $question) {
    $helpText = trim((string)($question['voice']['HELP'] ?? ''));
    if ($helpText === '') {
        fwrite(STDERR, "M02_HELP_MANIFEST_TEMPLATE_ERROR=CANONICAL_HELP_UNAVAILABLE\n");
        exit(1);
    }
    $textHash = hash('sha256', $helpText);
    if (!isset($helpCatalogByHash[$textHash])) {
        $isDiabetesHelp = ($question['stable_id'] ?? '') === 'M02:family:diabetes-mellitus';
        $helpCatalogEntry = [
            'module_id' => 'M02',
            'stable_ids' => [],
            'text' => $helpText,
            'text_sha256' => $textHash,
            'audio_file' => $isDiabetesHelp ? 'M02-help-diabetes.mp3' : 'M02-help-generic.mp3',
            'audio_sha256' => '',
        ];
        if (!$isDiabetesHelp) $helpCatalogEntry['stable_id_prefix'] = 'M02:family:';
        $helpCatalogByHash[$textHash] = $helpCatalogEntry;
    }
    $helpCatalogByHash[$textHash]['stable_ids'][] = $question['stable_id'];
}
if (count($entries) !== 5 || count($questions) !== 6 || count($helpCatalogByHash) !== 2) {
    fwrite(STDERR, "M02_HELP_MANIFEST_TEMPLATE_ERROR=FIXED_AUDIO_OR_DYNAMIC_HELP_CONTRACT_MISMATCH\n");
    exit(1);
}

$errorMessages = [
    'EMPTY_RESPONSE' => 'Escribe una respuesta para revisarla.',
    'INVALID_RESPONSE' => 'No pude validar esa respuesta. No se guardó ningún dato.',
    'INTERPRET_FAILED' => 'No fue posible revisar tu respuesta. Puedes intentarlo de nuevo.',
    'SAVE_FAILED' => 'No pude confirmar el guardado. Revisa la respuesta antes de volver a intentar.',
    'READBACK_FAILED' => 'No pude confirmar que tu respuesta quedara guardada. No avancé.',
];
$errorAudio = [];
foreach ($errorMessages as $errorCode => $text) {
    $errorAudio[] = [
        'module_id' => 'M02',
        'error_code' => $errorCode,
        'stable_id' => 'M02:error:' . $errorCode,
        'text' => $text,
        'text_sha256' => hash('sha256', $text),
        'audio_file' => 'M02-error-' . strtolower(str_replace('_', '-', $errorCode)) . '.mp3',
        'audio_sha256' => '',
    ];
}

$template = [
    'template_only' => true,
    'schema_version' => 1,
    'voice' => 'es_MX-claude-high',
    'purpose' => 'M02 contextual HELP and bounded error audio; template only until every MP3 is generated, hashed, reviewed, and the manifest pin is explicitly updated.',
    'entries' => $entries,
    'help_catalog' => array_values($helpCatalogByHash),
    'error_audio' => $errorAudio,
];
echo json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
