<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$assetDirectory = $root . '/public/assets/audio/patient-interview/m02/piper-claude';
$manifestPath = $assetDirectory . '/manifest.json';
$viewPath = $root . '/app/views/paciente/entrevista.php';
$voicePath = $root . '/public/assets/js/patient-interview-voice.js';
$servicePath = $root . '/app/Services/ConversationalInterviewService.php';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$manifestRaw = is_file($manifestPath) ? file_get_contents($manifestPath) : false;
$manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
$assert(is_array($manifest) && json_last_error() === JSON_ERROR_NONE, 'Piper M02 manifest must exist and parse as JSON.');
$assert(($manifest['schema_version'] ?? null) === 1 && ($manifest['voice'] ?? '') === 'es_MX-claude-high', 'Manifest voice/schema identity is not the approved pilot.');
$assert(count($manifest['entries'] ?? []) === 5, 'Only the five approved M02 entries should be mapped.');
$assert(preg_match('/\$m02PiperManifestSha256 = \'([a-f0-9]{64})\'/', (string)file_get_contents($viewPath), $pinMatch) === 1
    && hash_equals($pinMatch[1], hash('sha256', $manifestRaw)), 'Pinned manifest SHA-256 does not match.');

$expected = [
    'Enfermedades del corazón' => '¿Algún familiar tiene enfermedades del corazón?',
    'Hipertensión arterial sistémica' => '¿Algún familiar tiene hipertensión arterial sistémica?',
    'Enfermedad cerebrovascular' => '¿Algún familiar tiene enfermedad cerebrovascular?',
    'Diabetes Mellitus' => '¿Algún familiar tiene diabetes mellitus?',
    'Cáncer' => '¿Algún familiar tiene cáncer?',
];
$expectedById = [];
$approvedQuestionAudioHashes = [
    'M02:family:enfermedades-del-coraz-n' => 'ffc6ae3c409ff0f8c5136fec5225aacdb7f84e1b74c436bf1d6a56645e309ea5',
    'M02:family:hipertensi-n-arterial-sist-mica' => 'f272cf52cd1d80b32fcef7985ff0a9013670fb2d1b73ca7ef6030be933537e97',
    'M02:family:enfermedad-cerebrovascular' => '0dbc9c103ed406035880117e94bd081e9c66ee99f02ac428fe75524f656aec5a',
    'M02:family:diabetes-mellitus' => 'b3431dbc9f679c092b86d7e5ddd665e707ceef49a3052052c60c25b6efa53c72',
    'M02:family:c-ncer' => 'ee9e2b9ad0d9651a755b6c64d5414caf67d24cb84411427416b47b06e782f90b',
];
foreach ($expected as $disease => $text) {
    $token = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $disease), '-'));
    $expectedById['M02:family:' . ($token !== '' ? $token : 'item')] = $text;
}
$seen = [];
foreach ($manifest['entries'] as $entry) {
    $stableId = (string)($entry['stable_id'] ?? '');
    $audioFile = (string)($entry['audio_file'] ?? '');
    $assert(isset($expectedById[$stableId]) && !isset($seen[$stableId]), 'Manifest contains an unknown or duplicate canonical M02 stable_id.');
    $seen[$stableId] = true;
    $assert(($entry['text'] ?? null) === $expectedById[$stableId], 'Manifest text differs from its canonical M02 question.');
    $assert(hash('sha256', $entry['text']) === ($entry['text_sha256'] ?? ''), 'Canonical question text SHA-256 mismatch.');
    $assert($audioFile !== '' && basename($audioFile) === $audioFile && strtolower(pathinfo($audioFile, PATHINFO_EXTENSION)) === 'mp3', 'Audio entry must be a safe MP3 basename.');
    $audioPath = $assetDirectory . '/' . $audioFile;
    $assert(is_file($audioPath) && filesize($audioPath) > 0, 'An approved MP3 is missing or empty.');
    $assert(hash_file('sha256', $audioPath) === ($entry['audio_sha256'] ?? ''), 'Approved MP3 SHA-256 mismatch.');
    $assert(($entry['audio_sha256'] ?? '') === ($approvedQuestionAudioHashes[$stableId] ?? null), 'Previously approved M02 question audio must remain byte-for-byte unchanged.');
}
$assert(count($seen) === 5, 'Manifest must map all five approved M02 stable_ids exactly once.');

require_once $root . '/vendor/autoload.php';
$conversation = new App\Services\ConversationalInterviewService();
$questionsMethod = new ReflectionMethod($conversation, 'contractM02Questions');
$questionsMethod->setAccessible(true);
$records = array_map(static fn(string $disease): array => [
    'disease' => $disease, 'status' => 'pending', 'detail' => null, 'version' => 'test-version',
], array_merge(array_keys($expected), ['Enfermedad familiar agregada']));
$canonicalQuestions = $questionsMethod->invoke($conversation, ['records' => $records]);
$canonicalHelpByHash = [];
foreach ($canonicalQuestions as $question) {
    $assert(str_starts_with((string)($question['stable_id'] ?? ''), 'M02:family:'), 'M02 canonical question stable_id must remain module-scoped.');
    $text = (string)($question['help'] ?? '');
    $canonicalHelpByHash[hash('sha256', $text)] = $text;
}
$assert(count($canonicalHelpByHash) === 2, 'Current canonical M02 contract should expose the generic and diabetes help texts.');
$helpManifest = $manifest['help_catalog'] ?? [];
$assert(is_array($helpManifest) && count($helpManifest) === count($canonicalHelpByHash), 'Manifest must provide a separate Piper clip for each unique canonical Help text.');
$helpHashes = [];
$allMainAudioFiles = array_column($manifest['entries'], 'audio_file');
$usedMediaFiles = array_fill_keys($allMainAudioFiles, true);
foreach ($helpManifest as $helpEntry) {
    $text = (string)($helpEntry['text'] ?? '');
    $textHash = (string)($helpEntry['text_sha256'] ?? '');
    $audioFile = (string)($helpEntry['audio_file'] ?? '');
    $assert(($helpEntry['module_id'] ?? '') === 'M02' && isset($canonicalHelpByHash[$textHash])
        && $canonicalHelpByHash[$textHash] === $text && hash('sha256', $text) === $textHash, 'Help catalog text/hash must exactly match current canonical service text.');
    $assert(!isset($helpHashes[$textHash]), 'Help catalog must deduplicate identical canonical help text.');
    $helpHashes[$textHash] = true;
    $assert($audioFile !== '' && basename($audioFile) === $audioFile && strtolower(pathinfo($audioFile, PATHINFO_EXTENSION)) === 'mp3'
        && !isset($usedMediaFiles[$audioFile]), 'Help audio must be a distinct safe MP3 and never alias another clip.');
    $usedMediaFiles[$audioFile] = true;
    $assert(($helpEntry['stable_id_prefix'] ?? '') === 'M02:family:' || in_array('M02:family:diabetes-mellitus', $helpEntry['stable_ids'] ?? [], true), 'Help text catalog must declare the canonical stable_id scope to which it may bind.');
    $audioPath = $assetDirectory . '/' . $audioFile;
    $assert(is_file($audioPath) && filesize($audioPath) > 0 && hash_file('sha256', $audioPath) === ($helpEntry['audio_sha256'] ?? ''), 'Help audio must exist and match its manifest SHA-256.');
}
$errorTexts = [
    'EMPTY_RESPONSE' => 'Escribe una respuesta para revisarla.',
    'INVALID_RESPONSE' => 'No pude validar esa respuesta. No se guardó ningún dato.',
    'INTERPRET_FAILED' => 'No fue posible revisar tu respuesta. Puedes intentarlo de nuevo.',
    'SAVE_FAILED' => 'No pude confirmar el guardado. Revisa la respuesta antes de volver a intentar.',
    'READBACK_FAILED' => 'No pude confirmar que tu respuesta quedara guardada. No avancé.',
];
$assert(count($manifest['error_audio'] ?? []) === count($errorTexts), 'Only the explicitly approved, finite M02 error states may have Piper clips.');
$errorCodes = [];
foreach ($manifest['error_audio'] as $errorEntry) {
    $code = (string)($errorEntry['error_code'] ?? '');
    $text = (string)($errorEntry['text'] ?? '');
    $textHash = (string)($errorEntry['text_sha256'] ?? '');
    $audioFile = (string)($errorEntry['audio_file'] ?? '');
    $assert(($errorEntry['module_id'] ?? '') === 'M02' && isset($errorTexts[$code]) && $text === $errorTexts[$code]
        && ($errorEntry['stable_id'] ?? '') === 'M02:error:' . $code && hash('sha256', $text) === $textHash,
        'Error audio must be keyed by a known safe M02 code and exact user-facing text.');
    $assert(!isset($errorCodes[$code]) && !isset($usedMediaFiles[$audioFile]), 'Error audio codes/files must be unique and separate from all other clips.');
    $errorCodes[$code] = true;
    $usedMediaFiles[$audioFile] = true;
    $audioPath = $assetDirectory . '/' . $audioFile;
    $assert(basename($audioFile) === $audioFile && strtolower(pathinfo($audioFile, PATHINFO_EXTENSION)) === 'mp3'
        && is_file($audioPath) && filesize($audioPath) > 0 && hash_file('sha256', $audioPath) === ($errorEntry['audio_sha256'] ?? ''),
        'Error audio must be a safe, present, hash-matched MP3.');
}

$view = file_get_contents($viewPath);
$voice = file_get_contents($voicePath);
$service = file_get_contents($servicePath);
$assert(is_string($view) && is_string($voice) && is_string($service), 'Required source files must be readable.');
$assert(str_contains($view, "\$requestHost === 'clinica.test'") && str_contains($view, "in_array(\$remoteAddress, ['127.0.0.1', '::1'], true)") && str_contains($view, "in_array(\$serverAddress, ['127.0.0.1', '::1'], true)"), 'Piper pilot must be restricted to the local host, loopback clients, and loopback server.');
$assert(str_contains($view, "\$_GET['m02_voice'] ?? '') !== 'web'"), 'A local-only Web Speech opt-out must remain available.');
$assert(str_contains($view, "\$m02PiperManifestSha256 = '" . hash('sha256', $manifestRaw) . "'") , 'The application must pin the current manifest digest.');
$assert(str_contains($view, "\$canonicalQuestion['module_id'] ?? null) !== 'M02'") && str_contains($view, "\$canonicalText !== \$manifestText"), 'Server must require the matching canonical M02 question text.');
$assert(str_contains($voice, "!['QUESTION', 'HELP'].includes(event?.type)") && str_contains($voice, "event.type === 'HELP' ? mappedEntry?.help : mappedEntry") && str_contains($voice, "event?.stable_id || ''))"), 'Piper routing must require a canonical M02 event and select a distinct help asset only for HELP.');
$assert(str_contains($view, "\$m02PiperManifest['help_catalog'] ?? []")
    && str_contains($view, "foreach (\$questionIndex as \$stableId => \$canonicalQuestion)")
    && str_contains($view, "hash('sha256', \$canonicalHelp)")
    && str_contains($view, "\$canonicalQuestion['voice']['HELP'] ?? ''"), 'Each active canonical stable_id, including dynamic M02 records, must map only from an exact HELP text digest.');
$assert(str_contains($view, "\$m02PiperManifest['error_audio'] ?? []")
    && str_contains($view, "\$allowedM02ErrorCodes[\$errorCode]")
    && str_contains($view, "'stable_id' => 'M02:error:' . \$errorCode")
    && str_contains($view, "'shared_modules' => \$errorCode === 'EMPTY_RESPONSE' ? ['M02', 'M03'] : ['M02']"), 'The server may map only the finite allowlisted error catalog, and only EMPTY_RESPONSE is shared with M03.');
$assert(str_contains($view, 'const announceM02Error = (card, code)')
    && str_contains($view, "type: 'ERROR', module_id: 'M02', error_code: code")
    && str_contains($view, 'voiceController.muted'), 'M02 error narration must be tied to the active canonical question and respect voice mute.');
$assert(str_contains($view, "if (!utterance) { announceEmptyResponse(question, 'M03'); return; }")
    && str_contains($view, "const announceEmptyResponse = (card, moduleId) =>")
    && str_contains($view, "m02ErrorMessages.EMPTY_RESPONSE")
    && str_contains($view, "question.module_id !== moduleId")
    && str_contains($view, "voiceController?.cancel();"), 'M03 empty input must emit one active-question-scoped shared error and cancel stale narration before replay.');
$m03InterpretStart = strpos($view, 'const interpret = async question =>');
$m03InterpretEnd = strpos($view, 'const captureSafeM03No =', $m03InterpretStart ?: 0);
$m03InterpretBody = $m03InterpretStart !== false && $m03InterpretEnd !== false
    ? substr($view, $m03InterpretStart, $m03InterpretEnd - $m03InterpretStart) : '';
$emptyBranchPosition = strpos($m03InterpretBody, "if (!utterance) { announceEmptyResponse(question, 'M03'); return; }");
$fetchPosition = strpos($m03InterpretBody, 'fetch(');
$assert($emptyBranchPosition !== false && $fetchPosition !== false && $emptyBranchPosition < $fetchPosition,
    'An empty M03 submission must stop locally before any request or persistence attempt.');
$assert(str_contains($view, "'EMPTY_RESPONSE' => 'Escribe una respuesta para revisarla.'")
    && str_contains($view, "EMPTY_RESPONSE: 'Escribe una respuesta para revisarla.'"), 'The visible message must exactly match the signed Piper clip text.');
$assert(str_contains($voice, "if (event?.type === 'ERROR')")
    && str_contains($voice, 'this.piperAudio.errors?.[errorCode]')
    && str_contains($voice, "errorCode === 'EMPTY_RESPONSE'")
    && str_contains($voice, "errorEntry.shared_modules.includes('M03')")
    && str_contains($voice, 'event.type !== \'ERROR\''), 'Errors use only approved assets; only the explicitly shared empty-response clip can serve M03 and no error replaces the repeatable question event.');
$assert(str_contains($view, "'EMPTY_RESPONSE' => 'Escribe una respuesta para revisarla.'")
    && str_contains($view, "announceM02Error(card, 'READBACK_FAILED')")
    && str_contains($view, "announceM02Error(card, 'INVALID_RESPONSE')"), 'Only genuine empty, invalid, interpretation, save, and readback failure branches should invoke error speech.');
$inputHandlerStart = strpos($view, "document.querySelectorAll('[data-m02-input]').forEach(input => input.addEventListener('input'");
$inputHandlerEnd = strpos($view, "document.querySelectorAll('[data-m02-input]').forEach(input => input.dispatchEvent", $inputHandlerStart ?: 0);
$inputHandler = $inputHandlerStart !== false && $inputHandlerEnd !== false ? substr($view, $inputHandlerStart, $inputHandlerEnd - $inputHandlerStart) : '';
$assert($inputHandler !== '' && !str_contains($inputHandler, 'announceM02Error') && !str_contains($inputHandler, 'speakEvent('), 'Typing and changing an answer must never start an error narration loop.');
$assert(str_contains($voice, 'source.origin !== global.location.origin') && str_contains($voice, 'PIPER_AUDIO_LOAD_OR_DECODE_FAILED'), 'Cross-origin audio must be rejected and media failures must fall back.');
$assert(str_contains($service, "'M02:family:' . \$this->stableToken(\$disease)") && str_contains($service, "'¿Algún familiar tiene ' . mb_strtolower(\$disease, 'UTF-8') . '?'") , 'Canonical M02 IDs and source question wording must remain service-derived.');

echo "PIPER_M02_MANIFEST_CONTRACT=PASS\n";
