<?php

$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$questionsJson = json_encode($data['questions'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$conversationContractJson = json_encode($data['conversationContract'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
$m02PiperAudioConfig = ['enabled' => false, 'entries' => [], 'errors' => [], 'actions' => []];
$m03PiperAudioConfig = ['enabled' => false, 'entries' => []];
$m03TobaccoPiperAudioConfig = ['enabled' => false, 'entries' => [], 'errors' => []];
$m02FinalizeHelpText = 'Ya respondiste las preguntas de antecedentes familiares. Puedes revisar tus respuestas en Respuestas previas antes de finalizar. Si toda la información es correcta y deseas continuar, pulsa el botón Finalizar antecedentes familiares y continuar.';
$requestHost = strtolower(trim(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]));
$remoteAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$serverAddress = (string)($_SERVER['SERVER_ADDR'] ?? '');
$localM02PiperPilot = $requestHost === 'clinica.test'
    && in_array($remoteAddress, ['127.0.0.1', '::1'], true)
    && in_array($serverAddress, ['127.0.0.1', '::1'], true)
    && (string)($_GET['m02_voice'] ?? '') !== 'web';
$m02PiperAssetUrlDirectory = 'audio/patient-interview/m02/piper-claude';
$m02PiperAssetDirectory = 'assets/' . $m02PiperAssetUrlDirectory;
$m02PiperManifestPath = realpath(dirname(__DIR__, 3) . '/public/' . $m02PiperAssetDirectory . '/manifest.json');
$m02PiperManifestSha256 = '6d4804c40a2b4e557812bf46a4d2b9ca4f369acfb3ebc2375dfd912ae3c51d38';
$m02PiperErrorTexts = [
    'EMPTY_RESPONSE' => 'Escribe una respuesta para revisarla.',
    'INVALID_RESPONSE' => 'No pude validar esa respuesta. No se guardó ningún dato.',
    'INTERPRET_FAILED' => 'No fue posible revisar tu respuesta. Puedes intentarlo de nuevo.',
    'SAVE_FAILED' => 'No pude confirmar el guardado. Revisa la respuesta antes de volver a intentar.',
    'READBACK_FAILED' => 'No pude confirmar que tu respuesta quedara guardada. No avancé.',
];
$approvedM02StableIds = [
    'M02:family:enfermedades-del-coraz-n',
    'M02:family:hipertensi-n-arterial-sist-mica',
    'M02:family:enfermedad-cerebrovascular',
    'M02:family:diabetes-mellitus',
    'M02:family:c-ncer',
];
if ($localM02PiperPilot && $m02PiperManifestPath !== false && is_file($m02PiperManifestPath)
    && hash_equals($m02PiperManifestSha256, (string)hash_file('sha256', $m02PiperManifestPath))) {
    $m02PiperManifest = json_decode((string)file_get_contents($m02PiperManifestPath), true);
    $questionIndex = $data['conversationContract']['question_index'] ?? [];
    if (is_array($m02PiperManifest)
        && ($m02PiperManifest['schema_version'] ?? null) === 1
        && ($m02PiperManifest['voice'] ?? null) === 'es_MX-claude-high'
        && is_array($m02PiperManifest['entries'] ?? null)) {
        $allowedPrefix = rtrim(dirname($m02PiperManifestPath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        foreach ($m02PiperManifest['entries'] as $manifestEntry) {
            $stableId = (string)($manifestEntry['stable_id'] ?? '');
            $audioFile = (string)($manifestEntry['audio_file'] ?? '');
            $canonicalQuestion = $questionIndex[$stableId] ?? null;
            if (!in_array($stableId, $approvedM02StableIds, true)
                || !is_array($canonicalQuestion)
                || ($canonicalQuestion['module_id'] ?? null) !== 'M02'
                || $audioFile === '' || basename($audioFile) !== $audioFile
                || !preg_match('/^[a-f0-9]{64}$/', (string)($manifestEntry['audio_sha256'] ?? ''))
                || !preg_match('/^[a-f0-9]{64}$/', (string)($manifestEntry['text_sha256'] ?? ''))) {
                continue;
            }
            $canonicalText = (string)($canonicalQuestion['voice']['QUESTION'] ?? '');
            $manifestText = (string)($manifestEntry['text'] ?? '');
            if ($canonicalText === '' || $canonicalText !== $manifestText
                || !hash_equals((string)$manifestEntry['text_sha256'], hash('sha256', $canonicalText))) {
                continue;
            }
            $audioPath = realpath(dirname($m02PiperManifestPath) . DIRECTORY_SEPARATOR . $audioFile);
            if ($audioPath === false || !is_file($audioPath) || !str_starts_with($audioPath, $allowedPrefix)
                || !hash_equals((string)$manifestEntry['audio_sha256'], (string)hash_file('sha256', $audioPath))) {
                continue;
            }
            $m02PiperAudioConfig['entries'][$stableId] = [
                'module_id' => 'M02',
                'stable_id' => $stableId,
                'text' => $canonicalText,
                'text_sha256' => (string)$manifestEntry['text_sha256'],
                'audio_sha256' => (string)$manifestEntry['audio_sha256'],
                'src' => RUTA_PUBLIC . $m02PiperAssetUrlDirectory . '/' . rawurlencode($audioFile) . '?v=' . (int)filemtime($audioPath),
            ];
        }
        $usedPiperAudioFiles = [];
        foreach ($m02PiperManifest['entries'] as $audioManifestEntry) {
            $fileName = (string)($audioManifestEntry['audio_file'] ?? '');
            if ($fileName !== '' && basename($fileName) === $fileName) $usedPiperAudioFiles[$fileName] = true;
        }
        $helpAudioByTextHash = [];
        foreach (($m02PiperManifest['help_catalog'] ?? []) as $helpManifestEntry) {
            $helpModuleId = (string)($helpManifestEntry['module_id'] ?? '');
            $helpStableIdPrefix = (string)($helpManifestEntry['stable_id_prefix'] ?? '');
            $helpStableIds = is_array($helpManifestEntry['stable_ids'] ?? null) ? $helpManifestEntry['stable_ids'] : [];
            $helpText = (string)($helpManifestEntry['text'] ?? '');
            $helpFile = (string)($helpManifestEntry['audio_file'] ?? '');
            $helpTextHash = (string)($helpManifestEntry['text_sha256'] ?? '');
            $helpAudioHash = (string)($helpManifestEntry['audio_sha256'] ?? '');
            $helpPath = $helpFile !== '' && basename($helpFile) === $helpFile
                ? realpath(dirname($m02PiperManifestPath) . DIRECTORY_SEPARATOR . $helpFile)
                : false;
            if ($helpModuleId !== 'M02' || ($helpStableIdPrefix !== 'M02:family:' && $helpStableIds === [])
                || $helpText === '' || $helpFile === '' || isset($usedPiperAudioFiles[$helpFile])
                || strtolower(pathinfo($helpFile, PATHINFO_EXTENSION)) !== 'mp3'
                || preg_match('/^[a-f0-9]{64}$/', $helpTextHash) !== 1
                || !hash_equals($helpTextHash, hash('sha256', $helpText))
                || preg_match('/^[a-f0-9]{64}$/', $helpAudioHash) !== 1
                || $helpPath === false || !is_file($helpPath) || !str_starts_with($helpPath, $allowedPrefix)
                || !hash_equals($helpAudioHash, (string)hash_file('sha256', $helpPath))) {
                continue;
            }
            $helpAudioByTextHash[$helpTextHash] = [
                'module_id' => $helpModuleId,
                'stable_id_prefix' => $helpStableIdPrefix,
                'stable_ids' => $helpStableIds,
                'text' => $helpText,
                'text_sha256' => $helpTextHash,
                'audio_sha256' => $helpAudioHash,
                'src' => RUTA_PUBLIC . $m02PiperAssetUrlDirectory . '/' . rawurlencode($helpFile) . '?v=' . (int)filemtime($helpPath),
            ];
            $usedPiperAudioFiles[$helpFile] = true;
        }
        // Bind a help clip to every current canonical M02 question by stable_id
        // and exact canonical HELP digest. This also covers contract-generated
        // M02 questions beyond the five fixed question recordings.
        foreach ($questionIndex as $stableId => $canonicalQuestion) {
            $stableId = (string)$stableId;
            if (!is_array($canonicalQuestion) || ($canonicalQuestion['module_id'] ?? null) !== 'M02'
                || ($canonicalQuestion['stable_id'] ?? null) !== $stableId
                || preg_match('/^M02:family:[a-z0-9-]+$/', $stableId) !== 1) continue;
            $canonicalHelp = (string)($canonicalQuestion['voice']['HELP'] ?? '');
            $canonicalHelpHash = hash('sha256', $canonicalHelp);
            $helpEntry = $helpAudioByTextHash[$canonicalHelpHash] ?? null;
            $stableIdAllowed = is_array($helpEntry)
                && (($helpEntry['stable_id_prefix'] ?? '') === 'M02:family:'
                    ? str_starts_with($stableId, (string)$helpEntry['stable_id_prefix'])
                    : in_array($stableId, $helpEntry['stable_ids'] ?? [], true));
            if ($canonicalHelp === '' || !is_array($helpEntry) || ($helpEntry['module_id'] ?? '') !== 'M02'
                || !$stableIdAllowed || $helpEntry['text'] !== $canonicalHelp) continue;
            if (!isset($m02PiperAudioConfig['entries'][$stableId])) {
                $m02PiperAudioConfig['entries'][$stableId] = ['module_id' => 'M02', 'stable_id' => $stableId];
            }
            $m02PiperAudioConfig['entries'][$stableId]['help'] = [
                'module_id' => 'M02',
                'stable_id' => $stableId,
                'text' => $canonicalHelp,
                'text_sha256' => $helpEntry['text_sha256'],
                'audio_sha256' => $helpEntry['audio_sha256'],
                'src' => $helpEntry['src'],
            ];
        }
        $allowedM02ErrorCodes = $m02PiperErrorTexts;
        foreach (($m02PiperManifest['error_audio'] ?? []) as $errorManifestEntry) {
            $errorCode = (string)($errorManifestEntry['error_code'] ?? '');
            $errorText = (string)($errorManifestEntry['text'] ?? '');
            $errorFile = (string)($errorManifestEntry['audio_file'] ?? '');
            $textHash = (string)($errorManifestEntry['text_sha256'] ?? '');
            $audioHash = (string)($errorManifestEntry['audio_sha256'] ?? '');
            $errorPath = $errorFile !== '' && basename($errorFile) === $errorFile
                ? realpath(dirname($m02PiperManifestPath) . DIRECTORY_SEPARATOR . $errorFile)
                : false;
            if (($errorManifestEntry['module_id'] ?? null) !== 'M02'
                || ($errorManifestEntry['stable_id'] ?? null) !== 'M02:error:' . $errorCode
                || !isset($allowedM02ErrorCodes[$errorCode]) || $errorText !== $allowedM02ErrorCodes[$errorCode]
                || isset($usedPiperAudioFiles[$errorFile]) || strtolower(pathinfo($errorFile, PATHINFO_EXTENSION)) !== 'mp3'
                || preg_match('/^[a-f0-9]{64}$/', $textHash) !== 1
                || !hash_equals($textHash, hash('sha256', $errorText))
                || preg_match('/^[a-f0-9]{64}$/', $audioHash) !== 1
                || $errorPath === false || !is_file($errorPath) || !str_starts_with($errorPath, $allowedPrefix)
                || !hash_equals($audioHash, (string)hash_file('sha256', $errorPath))) {
                continue;
            }
            $m02PiperAudioConfig['errors'][$errorCode] = [
                'module_id' => 'M02',
                'error_code' => $errorCode,
                'shared_modules' => $errorCode === 'EMPTY_RESPONSE' ? ['M02', 'M03'] : ['M02'],
                'stable_id' => 'M02:error:' . $errorCode,
                'text' => $errorText,
                'text_sha256' => $textHash,
                'audio_sha256' => $audioHash,
                'src' => RUTA_PUBLIC . $m02PiperAssetUrlDirectory . '/' . rawurlencode($errorFile) . '?v=' . (int)filemtime($errorPath),
            ];
            $usedPiperAudioFiles[$errorFile] = true;
        }
        $finalizeActionId = 'M02:action:finalize';
        $canonicalFinalizeText = trim((string)($data['conversationContract']['modules']['M02']['conversation_state']['voice_text'] ?? ''));
        $actionManifestEntry = $m02PiperManifest['action_audio'] ?? null;
        if (is_array($actionManifestEntry)) {
            $actionId = (string)($actionManifestEntry['action_id'] ?? '');
            $actionText = (string)($actionManifestEntry['text'] ?? '');
            $actionFile = (string)($actionManifestEntry['audio_file'] ?? '');
            $actionTextHash = (string)($actionManifestEntry['text_sha256'] ?? '');
            $actionAudioHash = (string)($actionManifestEntry['audio_sha256'] ?? '');
            $actionPath = $actionFile !== '' && basename($actionFile) === $actionFile
                ? realpath(dirname($m02PiperManifestPath) . DIRECTORY_SEPARATOR . $actionFile)
                : false;
            if (($actionManifestEntry['module_id'] ?? null) === 'M02'
                && $actionId === $finalizeActionId
                && $canonicalFinalizeText !== '' && $actionText === $canonicalFinalizeText
                && $actionFile !== '' && !isset($usedPiperAudioFiles[$actionFile])
                && strtolower(pathinfo($actionFile, PATHINFO_EXTENSION)) === 'mp3'
                && preg_match('/^[a-f0-9]{64}$/', $actionTextHash) === 1
                && hash_equals($actionTextHash, hash('sha256', $canonicalFinalizeText))
                && preg_match('/^[a-f0-9]{64}$/', $actionAudioHash) === 1
                && $actionPath !== false && is_file($actionPath) && str_starts_with($actionPath, $allowedPrefix)
                && hash_equals($actionAudioHash, (string)hash_file('sha256', $actionPath))) {
                $m02PiperAudioConfig['actions'][$finalizeActionId] = [
                    'module_id' => 'M02',
                    'action_id' => $finalizeActionId,
                    'stable_id' => null,
                    'text' => $canonicalFinalizeText,
                    'text_sha256' => $actionTextHash,
                    'audio_sha256' => $actionAudioHash,
                    'src' => RUTA_PUBLIC . $m02PiperAssetUrlDirectory . '/' . rawurlencode($actionFile) . '?v=' . (int)filemtime($actionPath),
                ];
                $usedPiperAudioFiles[$actionFile] = true;
            }
        }
        $actionHelpManifestEntry = $m02PiperManifest['action_help_audio'] ?? null;
        if (is_array($actionHelpManifestEntry)) {
            $actionHelpId = (string)($actionHelpManifestEntry['action_id'] ?? '');
            $actionHelpText = (string)($actionHelpManifestEntry['text'] ?? '');
            $actionHelpFile = (string)($actionHelpManifestEntry['audio_file'] ?? '');
            $actionHelpTextHash = (string)($actionHelpManifestEntry['text_sha256'] ?? '');
            $actionHelpAudioHash = (string)($actionHelpManifestEntry['audio_sha256'] ?? '');
            $actionHelpPath = $actionHelpFile !== '' && basename($actionHelpFile) === $actionHelpFile
                ? realpath(dirname($m02PiperManifestPath) . DIRECTORY_SEPARATOR . $actionHelpFile)
                : false;
            if (($actionHelpManifestEntry['module_id'] ?? null) === 'M02'
                && $actionHelpId === $finalizeActionId
                && array_key_exists('stable_id', $actionHelpManifestEntry) && $actionHelpManifestEntry['stable_id'] === null
                && $actionHelpText === $m02FinalizeHelpText
                && $actionHelpFile !== '' && !isset($usedPiperAudioFiles[$actionHelpFile])
                && strtolower(pathinfo($actionHelpFile, PATHINFO_EXTENSION)) === 'mp3'
                && preg_match('/^[a-f0-9]{64}$/', $actionHelpTextHash) === 1
                && hash_equals($actionHelpTextHash, hash('sha256', $m02FinalizeHelpText))
                && preg_match('/^[a-f0-9]{64}$/', $actionHelpAudioHash) === 1
                && $actionHelpPath !== false && is_file($actionHelpPath) && str_starts_with($actionHelpPath, $allowedPrefix)
                && hash_equals($actionHelpAudioHash, (string)hash_file('sha256', $actionHelpPath))) {
                if (!isset($m02PiperAudioConfig['actions'][$finalizeActionId])) {
                    $m02PiperAudioConfig['actions'][$finalizeActionId] = [
                        'module_id' => 'M02', 'action_id' => $finalizeActionId, 'stable_id' => null,
                    ];
                }
                $m02PiperAudioConfig['actions'][$finalizeActionId]['help'] = [
                    'module_id' => 'M02',
                    'action_id' => $finalizeActionId,
                    'stable_id' => null,
                    'text' => $m02FinalizeHelpText,
                    'text_sha256' => $actionHelpTextHash,
                    'audio_sha256' => $actionHelpAudioHash,
                    'src' => RUTA_PUBLIC . $m02PiperAssetUrlDirectory . '/' . rawurlencode($actionHelpFile) . '?v=' . (int)filemtime($actionHelpPath),
                ];
                $usedPiperAudioFiles[$actionHelpFile] = true;
            }
        }
    }
}
$localM03TobaccoPiperPilot = $requestHost === 'clinica.test'
    && in_array($remoteAddress, ['127.0.0.1', '::1'], true)
    && in_array($serverAddress, ['127.0.0.1', '::1'], true);
$m03TobaccoAudioUrlDirectory = 'audio/patient-interview/m03/piper-claude';
$m03TobaccoManifestPath = realpath(dirname(__DIR__, 3) . '/public/assets/' . $m03TobaccoAudioUrlDirectory . '/manifest.json');
$m03TobaccoManifestSha256 = 'b9378a5d1b95b0df42c38441d917e30e0e44a8fe5b96d0fc4b8f190fc65261ea';
$m03TobaccoInvalidErrorCode = 'M03_TOBACCO_ANSWER_AMBIGUOUS';
$m03TobaccoInvalidErrorText = 'No pude identificar tu respuesta. Para continuar, dime claramente si fumas cigarros de nicotina. Puedes responder: sí, fumo; no, no fumo; no lo sé o no lo recuerdo. Tu respuesta no se guardó. Puedes intentarlo nuevamente.';
$m03TobaccoStableIds = array_map(static fn(int $id): string => 'M03:question:' . $id, range(1, 5));
if ($localM03TobaccoPiperPilot && $m03TobaccoManifestPath !== false && is_file($m03TobaccoManifestPath)
    && hash_equals($m03TobaccoManifestSha256, (string)hash_file('sha256', $m03TobaccoManifestPath))) {
    $m03Manifest = json_decode((string)file_get_contents($m03TobaccoManifestPath), true);
    $questionIndex = $data['conversationContract']['question_index'] ?? [];
    if (is_array($m03Manifest)
        && ($m03Manifest['schema_version'] ?? null) === 1
        && ($m03Manifest['module_id'] ?? null) === 'M03'
        && ($m03Manifest['theme_id'] ?? null) === 1
        && ($m03Manifest['voice'] ?? null) === 'es_MX-claude-high'
        && is_array($m03Manifest['entries'] ?? null)
        && is_array($m03Manifest['help_audio'] ?? null)
        && is_array($m03Manifest['error_audio'] ?? null)) {
        $allowedPrefix = rtrim(dirname($m03TobaccoManifestPath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $resolvedEntries = [];
        $usedFiles = [];
        foreach ($m03Manifest['entries'] as $manifestEntry) {
            $stableId = (string)($manifestEntry['stable_id'] ?? '');
            $canonicalQuestion = $questionIndex[$stableId] ?? null;
            $audioFile = (string)($manifestEntry['audio_file'] ?? '');
            $audioPath = $audioFile !== '' && basename($audioFile) === $audioFile
                ? realpath(dirname($m03TobaccoManifestPath) . DIRECTORY_SEPARATOR . $audioFile)
                : false;
            $canonicalText = is_array($canonicalQuestion) ? (string)($canonicalQuestion['voice']['QUESTION'] ?? '') : '';
            $text = (string)($manifestEntry['text'] ?? '');
            $textHash = (string)($manifestEntry['text_sha256'] ?? '');
            $audioHash = (string)($manifestEntry['audio_sha256'] ?? '');
            if (!in_array($stableId, $m03TobaccoStableIds, true)
                || isset($resolvedEntries[$stableId])
                || !is_array($canonicalQuestion)
                || ($canonicalQuestion['module_id'] ?? null) !== 'M03'
                || (int)($canonicalQuestion['theme_id'] ?? 0) !== 1
                || (int)($canonicalQuestion['question_id'] ?? 0) !== (int)substr($stableId, strlen('M03:question:'))
                || $text === '' || $text !== $canonicalText
                || preg_match('/^[a-f0-9]{64}$/', $textHash) !== 1 || !hash_equals($textHash, hash('sha256', $text))
                || $audioFile === '' || strtolower(pathinfo($audioFile, PATHINFO_EXTENSION)) !== 'mp3'
                || preg_match('/^[a-f0-9]{64}$/', $audioHash) !== 1
                || $audioPath === false || !is_file($audioPath) || !str_starts_with($audioPath, $allowedPrefix)
                || !hash_equals($audioHash, (string)hash_file('sha256', $audioPath))
                || isset($usedFiles[$audioFile])) {
                continue;
            }
            $usedFiles[$audioFile] = true;
            $resolvedEntries[$stableId] = [
                'module_id' => 'M03', 'stable_id' => $stableId, 'text' => $text,
                'text_sha256' => $textHash, 'audio_sha256' => $audioHash,
                'src' => RUTA_PUBLIC . $m03TobaccoAudioUrlDirectory . '/' . rawurlencode($audioFile) . '?v=' . (int)filemtime($audioPath),
            ];
        }
        $resolvedHelp = [];
        foreach ($m03Manifest['help_audio'] as $helpEntry) {
            $stableId = (string)($helpEntry['stable_id'] ?? '');
            $canonicalQuestion = $questionIndex[$stableId] ?? null;
            $audioFile = (string)($helpEntry['audio_file'] ?? '');
            $audioPath = $audioFile !== '' && basename($audioFile) === $audioFile
                ? realpath(dirname($m03TobaccoManifestPath) . DIRECTORY_SEPARATOR . $audioFile)
                : false;
            $canonicalText = is_array($canonicalQuestion) ? (string)($canonicalQuestion['voice']['HELP'] ?? '') : '';
            $text = (string)($helpEntry['text'] ?? '');
            $textHash = (string)($helpEntry['text_sha256'] ?? '');
            $audioHash = (string)($helpEntry['audio_sha256'] ?? '');
            if (($helpEntry['module_id'] ?? null) !== 'M03'
                || !in_array($stableId, $m03TobaccoStableIds, true)
                || isset($resolvedHelp[$stableId])
                || !isset($resolvedEntries[$stableId])
                || !is_array($canonicalQuestion)
                || $text === '' || $text !== $canonicalText
                || preg_match('/^[a-f0-9]{64}$/', $textHash) !== 1 || !hash_equals($textHash, hash('sha256', $text))
                || $audioFile === '' || strtolower(pathinfo($audioFile, PATHINFO_EXTENSION)) !== 'mp3'
                || preg_match('/^[a-f0-9]{64}$/', $audioHash) !== 1
                || $audioPath === false || !is_file($audioPath) || !str_starts_with($audioPath, $allowedPrefix)
                || !hash_equals($audioHash, (string)hash_file('sha256', $audioPath))
                || isset($usedFiles[$audioFile])) {
                continue;
            }
            $usedFiles[$audioFile] = true;
            $resolvedHelp[$stableId] = [
                'module_id' => 'M03', 'stable_id' => $stableId, 'text' => $text,
                'text_sha256' => $textHash, 'audio_sha256' => $audioHash,
                'src' => RUTA_PUBLIC . $m03TobaccoAudioUrlDirectory . '/' . rawurlencode($audioFile) . '?v=' . (int)filemtime($audioPath),
            ];
        }
        $resolvedErrors = [];
        foreach ($m03Manifest['error_audio'] as $errorEntry) {
            $errorCode = (string)($errorEntry['error_code'] ?? '');
            $stableId = (string)($errorEntry['stable_id'] ?? '');
            $questionStableId = (string)($errorEntry['question_stable_id'] ?? '');
            $audioFile = (string)($errorEntry['audio_file'] ?? '');
            $audioPath = $audioFile !== '' && basename($audioFile) === $audioFile
                ? realpath(dirname($m03TobaccoManifestPath) . DIRECTORY_SEPARATOR . $audioFile)
                : false;
            $text = (string)($errorEntry['text'] ?? '');
            $textHash = (string)($errorEntry['text_sha256'] ?? '');
            $audioHash = (string)($errorEntry['audio_sha256'] ?? '');
            if (($errorEntry['module_id'] ?? null) !== 'M03'
                || $errorCode !== $m03TobaccoInvalidErrorCode
                || $stableId !== 'M03:error:tobacco-answer-ambiguous'
                || $questionStableId !== 'M03:question:1'
                || isset($resolvedErrors[$errorCode])
                || !isset($resolvedEntries[$questionStableId])
                || $text !== $m03TobaccoInvalidErrorText
                || preg_match('/^[a-f0-9]{64}$/', $textHash) !== 1 || !hash_equals($textHash, hash('sha256', $text))
                || $audioFile === '' || strtolower(pathinfo($audioFile, PATHINFO_EXTENSION)) !== 'mp3'
                || isset($usedFiles[$audioFile]) || preg_match('/^[a-f0-9]{64}$/', $audioHash) !== 1
                || $audioPath === false || !is_file($audioPath) || !str_starts_with($audioPath, $allowedPrefix)
                || !hash_equals($audioHash, (string)hash_file('sha256', $audioPath))) {
                continue;
            }
            $usedFiles[$audioFile] = true;
            $resolvedErrors[$errorCode] = [
                'module_id' => 'M03', 'error_code' => $errorCode, 'stable_id' => $stableId,
                'question_stable_id' => $questionStableId, 'text' => $text,
                'text_sha256' => $textHash, 'audio_sha256' => $audioHash,
                'src' => RUTA_PUBLIC . $m03TobaccoAudioUrlDirectory . '/' . rawurlencode($audioFile) . '?v=' . (int)filemtime($audioPath),
            ];
        }
        if (array_keys($resolvedEntries) !== [] && count($resolvedEntries) === 5 && count($resolvedHelp) === 5
            && array_diff($m03TobaccoStableIds, array_keys($resolvedEntries)) === []
            && array_diff($m03TobaccoStableIds, array_keys($resolvedHelp)) === []
            && count($resolvedErrors) === 1 && isset($resolvedErrors[$m03TobaccoInvalidErrorCode])) {
            foreach ($m03TobaccoStableIds as $stableId) {
                $resolvedEntries[$stableId]['help'] = $resolvedHelp[$stableId];
            }
            $m03TobaccoPiperAudioConfig = ['enabled' => true, 'entries' => $resolvedEntries, 'errors' => $resolvedErrors];
        }
    }
}
$m03PiperManifestSha256 = 'b9378a5d1b95b0df42c38441d917e30e0e44a8fe5b96d0fc4b8f190fc65261ea';
if ($localM03TobaccoPiperPilot && $m03TobaccoManifestPath !== false && is_file($m03TobaccoManifestPath)
    && preg_match('/^[a-f0-9]{64}$/', $m03PiperManifestSha256) === 1
    && hash_equals($m03PiperManifestSha256, (string)hash_file('sha256', $m03TobaccoManifestPath))) {
    $manifestForAllThemes = json_decode((string)file_get_contents($m03TobaccoManifestPath), true);
    $questionIndex = $data['conversationContract']['question_index'] ?? [];
    if (is_array($manifestForAllThemes)
        && ($manifestForAllThemes['schema_version'] ?? null) === 1
        && ($manifestForAllThemes['module_id'] ?? null) === 'M03'
        && ($manifestForAllThemes['theme_id'] ?? null) === 1
        && ($manifestForAllThemes['voice'] ?? null) === 'es_MX-claude-high'
        && is_array($manifestForAllThemes['entries'] ?? null)
        && is_array($manifestForAllThemes['help_audio'] ?? null)) {
        $allowedPrefix = rtrim(dirname($m03TobaccoManifestPath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $resolvedQuestions = [];
        $usedQuestionFiles = [];
        foreach ($manifestForAllThemes['entries'] as $entry) {
            $stableId = (string)($entry['stable_id'] ?? '');
            $canonical = $questionIndex[$stableId] ?? null;
            $audioFile = (string)($entry['audio_file'] ?? '');
            $audioPath = $audioFile !== '' && basename($audioFile) === $audioFile
                ? realpath(dirname($m03TobaccoManifestPath) . DIRECTORY_SEPARATOR . $audioFile)
                : false;
            $canonicalText = is_array($canonical) ? (string)($canonical['voice']['QUESTION'] ?? '') : '';
            $text = (string)($entry['text'] ?? '');
            $textHash = (string)($entry['text_sha256'] ?? '');
            $audioHash = (string)($entry['audio_sha256'] ?? '');
            $questionId = is_array($canonical) ? (int)($canonical['question_id'] ?? 0) : 0;
            $themeId = is_array($canonical) ? (int)($canonical['theme_id'] ?? 0) : 0;
            if (preg_match('/^M03:question:([1-9][0-9]*)$/', $stableId, $match) !== 1
                || !is_array($canonical) || ($canonical['module_id'] ?? null) !== 'M03'
                || $questionId !== (int)$match[1] || $themeId < 1 || $themeId > 7
                || (isset($entry['question_id']) && (int)$entry['question_id'] !== $questionId)
                || (isset($entry['theme_id']) && (int)$entry['theme_id'] !== $themeId)
                || isset($resolvedQuestions[$stableId]) || $text === '' || $text !== $canonicalText
                || preg_match('/^[a-f0-9]{64}$/', $textHash) !== 1 || !hash_equals($textHash, hash('sha256', $text))
                || $audioFile === '' || strtolower(pathinfo($audioFile, PATHINFO_EXTENSION)) !== 'mp3'
                || preg_match('/^[a-f0-9]{64}$/', $audioHash) !== 1
                || $audioPath === false || !is_file($audioPath) || !str_starts_with($audioPath, $allowedPrefix)
                || !hash_equals($audioHash, (string)hash_file('sha256', $audioPath))) {
                continue;
            }
            $sharedFile = $usedQuestionFiles[$audioFile] ?? null;
            if (is_array($sharedFile)
                && ($sharedFile['audio_sha256'] !== $audioHash || trim($sharedFile['spoken_text']) !== trim($text))) {
                continue;
            }
            $usedQuestionFiles[$audioFile] = ['audio_sha256' => $audioHash, 'spoken_text' => $text];
            $resolvedQuestions[$stableId] = [
                'module_id' => 'M03', 'stable_id' => $stableId, 'text' => $text,
                'text_sha256' => $textHash, 'audio_sha256' => $audioHash,
                'src' => RUTA_PUBLIC . $m03TobaccoAudioUrlDirectory . '/' . rawurlencode($audioFile) . '?v=' . (int)filemtime($audioPath),
            ];
        }
        $resolvedHelp = [];
        $usedHelpFiles = [];
        foreach ($manifestForAllThemes['help_audio'] as $entry) {
            $stableId = (string)($entry['stable_id'] ?? '');
            $canonical = $questionIndex[$stableId] ?? null;
            $audioFile = (string)($entry['audio_file'] ?? '');
            $audioPath = $audioFile !== '' && basename($audioFile) === $audioFile
                ? realpath(dirname($m03TobaccoManifestPath) . DIRECTORY_SEPARATOR . $audioFile)
                : false;
            $canonicalText = is_array($canonical) ? (string)($canonical['voice']['HELP'] ?? '') : '';
            $text = (string)($entry['text'] ?? '');
            $textHash = (string)($entry['text_sha256'] ?? '');
            $audioHash = (string)($entry['audio_sha256'] ?? '');
            if (($entry['module_id'] ?? null) !== 'M03' || !isset($resolvedQuestions[$stableId])
                || isset($resolvedHelp[$stableId]) || !is_array($canonical) || $text === '' || $text !== $canonicalText
                || (isset($entry['theme_id']) && (int)$entry['theme_id'] !== (int)($canonical['theme_id'] ?? 0))
                || preg_match('/^[a-f0-9]{64}$/', $textHash) !== 1 || !hash_equals($textHash, hash('sha256', $text))
                || $audioFile === '' || strtolower(pathinfo($audioFile, PATHINFO_EXTENSION)) !== 'mp3'
                || preg_match('/^[a-f0-9]{64}$/', $audioHash) !== 1
                || isset($usedHelpFiles[$audioFile]) || isset($usedQuestionFiles[$audioFile])
                || $audioPath === false || !is_file($audioPath) || !str_starts_with($audioPath, $allowedPrefix)
                || !hash_equals($audioHash, (string)hash_file('sha256', $audioPath))) {
                continue;
            }
            $usedHelpFiles[$audioFile] = true;
            $resolvedHelp[$stableId] = [
                'module_id' => 'M03', 'stable_id' => $stableId, 'text' => $text,
                'text_sha256' => $textHash, 'audio_sha256' => $audioHash,
                'src' => RUTA_PUBLIC . $m03TobaccoAudioUrlDirectory . '/' . rawurlencode($audioFile) . '?v=' . (int)filemtime($audioPath),
            ];
        }
        $expectedM03Targets = [];
        foreach ($questionIndex as $stableId => $question) {
            if (is_array($question) && ($question['module_id'] ?? null) === 'M03') $expectedM03Targets[] = (string)$stableId;
        }
        if (count($expectedM03Targets) === 25 && count($resolvedQuestions) === 25 && count($resolvedHelp) === 25
            && array_diff($expectedM03Targets, array_keys($resolvedQuestions)) === []
            && array_diff($expectedM03Targets, array_keys($resolvedHelp)) === []) {
            foreach ($expectedM03Targets as $stableId) $resolvedQuestions[$stableId]['help'] = $resolvedHelp[$stableId];
            $m03PiperAudioConfig = ['enabled' => true, 'entries' => $resolvedQuestions];
        }
    }
}
$m04PiperAudioConfig = ['enabled' => false, 'entries' => [], 'help_audio' => []];
$m04PiperAssetUrlDirectory = 'audio/patient-interview/m04/piper-claude';
$m04PiperManifestPath = realpath(dirname(__DIR__, 3) . '/public/assets/' . $m04PiperAssetUrlDirectory . '/manifest.json');
$m04PiperManifestSha256 = '00c341473ece2f63002eb90d500a2a1aff7960508c0e2517ccb6acc50683c5bb';
$m04QuestionIds = [
    'M04:record:new',
    'M04:record:new:name',
    'M04:record:new:observations',
    'M04:record:new:add-another',
];
$m04HelpTexts = [
    'M04:help:1' => ['M04:record:new', 'Si nunca le han realizado una cirugía, seleccione No, guardar y continuar.'],
    'M04:help:2' => ['M04:record:new:name', 'Escriba el nombre de la cirugía que le realizaron.'],
    'M04:help:3' => ['M04:record:new:observations', 'Puede indicar el año, la fecha aproximada o los detalles que recuerde.'],
    'M04:help:4' => ['M04:record:new:observations', 'Si no recuerda una respuesta, puede seleccionar No recuerdo.'],
    'M04:help:5' => ['M04:record:new:add-another', 'Si tuvo otra cirugía, puede agregarla antes de terminar el módulo.'],
];
if ($localM02PiperPilot && $m04PiperManifestPath !== false && is_file($m04PiperManifestPath)
    && preg_match('/^[a-f0-9]{64}$/', $m04PiperManifestSha256) === 1
    && hash_equals($m04PiperManifestSha256, (string)hash_file('sha256', $m04PiperManifestPath))) {
    $manifest = json_decode((string)file_get_contents($m04PiperManifestPath), true);
    $questionIndex = $data['conversationContract']['question_index'] ?? [];
    $assetPrefix = rtrim(dirname($m04PiperManifestPath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $usedFiles = [];
    $resolvedQuestions = [];
    $resolvedHelp = [];
    if (is_array($manifest) && ($manifest['schema_version'] ?? null) === 1
        && ($manifest['module_id'] ?? null) === 'M04'
        && ($manifest['voice'] ?? null) === 'es_MX-claude-high'
        && ($manifest['audio_format'] ?? null) === 'mp3'
        && is_array($manifest['entries'] ?? null) && is_array($manifest['help_audio'] ?? null)) {
        foreach ($manifest['entries'] as $stableId => $entry) {
            if (!in_array((string)$stableId, $m04QuestionIds, true) || !is_array($entry)) continue;
            $canonical = $questionIndex[$stableId] ?? null;
            $audioFile = (string)($entry['audio_file'] ?? '');
            $audioPath = $audioFile !== '' && basename($audioFile) === $audioFile
                ? realpath(dirname($m04PiperManifestPath) . DIRECTORY_SEPARATOR . $audioFile) : false;
            $text = (string)($entry['text'] ?? '');
            $textHash = (string)($entry['text_sha256'] ?? '');
            $audioHash = (string)($entry['audio_sha256'] ?? '');
            $canonicalText = is_array($canonical) ? (string)($canonical['voice']['QUESTION'] ?? '') : '';
            if (!is_array($canonical) || ($canonical['module_id'] ?? null) !== 'M04' || $text === '' || $text !== $canonicalText
                || preg_match('/^[a-f0-9]{64}$/', $textHash) !== 1 || !hash_equals($textHash, hash('sha256', $text))
                || strtolower(pathinfo($audioFile, PATHINFO_EXTENSION)) !== 'mp3'
                || preg_match('/^[a-f0-9]{64}$/', $audioHash) !== 1 || isset($usedFiles[$audioFile])
                || $audioPath === false || !str_starts_with($audioPath, $assetPrefix)
                || !hash_equals($audioHash, (string)hash_file('sha256', $audioPath))) continue;
            $usedFiles[$audioFile] = true;
            $resolvedQuestions[$stableId] = ['module_id' => 'M04', 'stable_id' => $stableId, 'text' => $text,
                'text_sha256' => $textHash, 'audio_sha256' => $audioHash,
                'src' => RUTA_PUBLIC . $m04PiperAssetUrlDirectory . '/' . rawurlencode($audioFile) . '?v=' . (int)filemtime($audioPath)];
        }
        foreach ($manifest['help_audio'] as $entry) {
            if (!is_array($entry)) continue;
            $helpId = (string)($entry['help_id'] ?? '');
            [$stableId, $expectedText] = $m04HelpTexts[$helpId] ?? [null, null];
            $audioFile = (string)($entry['audio_file'] ?? '');
            $audioPath = $audioFile !== '' && basename($audioFile) === $audioFile
                ? realpath(dirname($m04PiperManifestPath) . DIRECTORY_SEPARATOR . $audioFile) : false;
            $text = (string)($entry['text'] ?? '');
            $textHash = (string)($entry['text_sha256'] ?? '');
            $audioHash = (string)($entry['audio_sha256'] ?? '');
            if ($stableId === null || ($entry['stable_id'] ?? null) !== $stableId || $text !== $expectedText
                || preg_match('/^[a-f0-9]{64}$/', $textHash) !== 1 || !hash_equals($textHash, hash('sha256', $text))
                || strtolower(pathinfo($audioFile, PATHINFO_EXTENSION)) !== 'mp3'
                || preg_match('/^[a-f0-9]{64}$/', $audioHash) !== 1 || isset($usedFiles[$audioFile])
                || $audioPath === false || !str_starts_with($audioPath, $assetPrefix)
                || !hash_equals($audioHash, (string)hash_file('sha256', $audioPath))) continue;
            $usedFiles[$audioFile] = true;
            $resolvedHelp[$helpId] = ['module_id' => 'M04', 'stable_id' => $stableId, 'help_id' => $helpId, 'text' => $text,
                'text_sha256' => $textHash, 'audio_sha256' => $audioHash,
                'src' => RUTA_PUBLIC . $m04PiperAssetUrlDirectory . '/' . rawurlencode($audioFile) . '?v=' . (int)filemtime($audioPath)];
        }
        if (count($resolvedQuestions) === count($m04QuestionIds) && count($resolvedHelp) === count($m04HelpTexts)) {
            foreach ($resolvedHelp as $helpId => $entry) {
                if ($helpId === 'M04:help:4') continue;
                $resolvedQuestions[$entry['stable_id']]['help'] = $entry;
            }
            $m04PiperAudioConfig = ['enabled' => true, 'entries' => $resolvedQuestions, 'help_audio' => $resolvedHelp];
        }
    }
}
$m02PiperAudioConfig['m03_tobacco'] = $m03TobaccoPiperAudioConfig;
$m02PiperAudioConfig['m03'] = $m03PiperAudioConfig;
$m02PiperAudioConfig['m04'] = $m04PiperAudioConfig;
$m02PiperAudioConfig['enabled'] = $m02PiperAudioConfig['entries'] !== [] || $m02PiperAudioConfig['errors'] !== [] || $m02PiperAudioConfig['actions'] !== [];
$m02PiperAudioConfig['enabled'] = $m02PiperAudioConfig['enabled'] || $m03TobaccoPiperAudioConfig['enabled'] || $m03PiperAudioConfig['enabled'] || $m04PiperAudioConfig['enabled'];
$m02PiperAudioConfigJson = json_encode($m02PiperAudioConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{"enabled":false,"entries":[],"errors":[],"actions":[]}';
$m02FinalizeHelpTextJson = json_encode($m02FinalizeHelpText, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '""';
$questionList = $data['questions'] ?? [];
$versionedAsset = static function (string $baseUrl, string $assetPath, string $publicPath, bool $contentFingerprint = false): string {
    $filesystemPath = realpath(dirname(__DIR__, 3) . '/public/' . ltrim($publicPath, '/'));
    $version = false;
    if ($filesystemPath !== false && is_file($filesystemPath)) {
        $version = $contentFingerprint
            ? substr((string)(hash_file('sha256', $filesystemPath) ?: ''), 0, 16)
            : filemtime($filesystemPath);
    }

    return $baseUrl . $assetPath . ($version === false || $version === '' ? '' : '?v=' . rawurlencode((string)$version));
};
$bootstrapCssUrl = $versionedAsset(RUTA_CSS, 'bootstrap.css', 'assets/css/bootstrap.css');
$perfectScrollbarCssUrl = $versionedAsset(RUTA_PUBLIC, 'libs/perfect-scrollbar/perfect-scrollbar.css', 'assets/libs/perfect-scrollbar/perfect-scrollbar.css');
$appCssUrl = $versionedAsset(RUTA_CSS, 'app.css', 'assets/css/app.css');
$coordinatorJsUrl = $versionedAsset(RUTA_JS, 'patient-interview-coordinator.js', 'assets/js/patient-interview-coordinator.js');
$voiceJsUrl = $versionedAsset(RUTA_JS, 'patient-interview-voice.js', 'assets/js/patient-interview-voice.js');
$localDictationJsUrl = $versionedAsset(RUTA_JS, 'patient-interview-local-dictation.js', 'assets/js/patient-interview-local-dictation.js', true);
$m03DictationUiJsUrl = $versionedAsset(RUTA_JS, 'patient-interview-m03-dictation-ui.js', 'assets/js/patient-interview-m03-dictation-ui.js', true);
$logoUrl = $versionedAsset(RUTA_IMAGES, 'logo-clinica.png', 'assets/images/logo-clinica.png');
$doctorAvatarUrl = $versionedAsset(RUTA_IMAGES, 'doctora-virtual-entrevista-v2.png', 'assets/images/doctora-virtual-entrevista-v2.png');
$firstPending = count($questionList);
foreach ($questionList as $questionIndex => $questionItem) {
    $eligible = ($questionItem['eligible'] ?? true) === true;
    $semanticState = strtoupper(trim((string)($questionItem['semanticState'] ?? $questionItem['estadoRespuesta'] ?? '')));
    $hasSemanticAnswer = in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true);
    if ($eligible && trim((string)($questionItem['respuesta'] ?? '')) === '' && !$hasSemanticAnswer) { $firstPending = $questionIndex; break; }
}
if ($firstPending >= count($questionList) && $questionList) {
    foreach ($questionList as $questionIndex => $questionItem) {
        if (($questionItem['eligible'] ?? true) === true) { $firstPending = $questionIndex; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$escape($data['title'] ?? 'Entrevista Conversacional');?></title>
    <link rel="shortcut icon" href="<?=$logoUrl;?>">
    <link rel="stylesheet" href="<?=$bootstrapCssUrl;?>">
    <link rel="stylesheet" href="<?=$perfectScrollbarCssUrl;?>">
    <link rel="stylesheet" href="<?=$appCssUrl;?>">
    <style>
        body.patient-interview-page { overflow-x:clip; }
        body.patient-interview-page { min-width:0; }
        .patient-interview-page { background:#f4f0ff; }
        .patient-interview-main { min-width:0; min-height:calc(100vh - 4rem); background:linear-gradient(180deg,#f7f4ff 0%,#f1edff 100%); font-size:16px; }
        .patient-interview-shell { width:100%; max-width:1360px; min-width:0; margin:0 auto; }
        .patient-interview-header { display:grid; grid-template-columns:minmax(0,1.15fr) minmax(30rem,.85fr); gap:1rem; align-items:center; }
        .patient-interview-header, .patient-interview-conversation-card { border:1px solid #e5ddf7; background:#fff; border-radius:1.25rem; box-shadow:0 .45rem 1.25rem rgba(72,45,120,.08); }
        .patient-interview-header { padding:.75rem 1rem; }
        .patient-interview-header-copy { min-width:0; }
        .patient-interview-header-copy .dashboard-eyebrow { margin-bottom:.25rem; font-size:.72rem; letter-spacing:.08em; }
        .patient-interview-header-copy h1 { margin-bottom:.2rem; font-size:clamp(1.6rem,2vw,1.85rem); line-height:1.1; font-weight:600; }
        .patient-interview-header-side { display:grid; gap:.45rem; min-width:0; }
        .patient-interview-header-context { display:flex; justify-content:flex-end; align-items:center; gap:.4rem; flex-wrap:wrap; }
        .patient-interview-progress { width:100%; min-width:0; padding:0; border:0; background:transparent; box-shadow:none; }
        .patient-interview-progress .interview-progress-layout { align-items:baseline; gap:.25rem .75rem; }
        .patient-interview-progress #interview-progress-percentage { min-width:0; color:var(--interview-purple); font-size:.95rem; font-variant-numeric:tabular-nums; overflow-wrap:anywhere; }
        .patient-interview-progress #interview-progress { min-width:0; margin-left:auto; color:var(--interview-ink); font-size:.84rem; text-align:right; }
        body.patient-interview-page .patient-interview-progress .progress { height:.45rem !important; border:0; border-radius:999px; background:#ede7f7 !important; }
        body.patient-interview-page .patient-interview-progress .progress .progress-bar { border-radius:inherit; background:var(--interview-purple) !important; }
        .patient-interview-header-actions { justify-content:flex-end; flex-wrap:nowrap; gap:.75rem !important; }
        .patient-interview-header-actions > * { display:inline-flex; align-items:center; justify-content:center; gap:.45rem; min-height:44px; padding:.45rem .75rem; font-size:.875rem; }
        .patient-interview-header-actions [data-feather] { width:1rem; height:1rem; vertical-align:-.15em; }
        .interview-module-badge, .interview-state-badge { min-height:1.75rem; padding:.25rem .6rem; font-size:.78rem; }
        .patient-interview-page .btn { white-space:nowrap; word-break:normal; overflow-wrap:normal; }
        .patient-interview-topbar-inner { display:flex; align-items:center; gap:.9rem; }
        .patient-interview-breadcrumb { color:#3b2464; font-weight:650; }
        .patient-interview-breadcrumb span { margin-inline:.3rem; color:#8c71b8; font-size:1.2rem; }
        .portal-topbar .portal-badge { margin-left:auto; display:inline-flex; align-items:center; gap:.5rem; }
        .portal-status-dot { width:.7rem; height:.7rem; border-radius:50%; background:#1baa78; box-shadow:0 0 0 .22rem rgba(27,170,120,.14); }
        .patient-interview-header h1, #interview-chat h2 { color:#27154e; }
        .patient-interview-conversation-card > .card-body { padding:1.35rem; }
        .patient-interview-conversation-layout { display:grid; grid-template-columns:1fr; gap:1.35rem; align-items:start; max-width:min(1090px,calc(100% - 260px)); }
        #interview-chat { display:flex; flex-direction:column; gap:1rem; border:0; background:transparent; border-radius:0; box-shadow:none; }
        .patient-interview-conversation-card { min-width:0; overflow:visible; }
        .patient-interview-context-panel { display:grid; gap:.9rem; }
        .patient-interview-context-card { padding:1rem; border:1px solid #e5ddf7; border-radius:1rem; background:#fff; box-shadow:0 .25rem .8rem rgba(72,45,120,.05); }
        .patient-interview-context-card h3 { display:flex; align-items:center; gap:.55rem; margin:0 0 .75rem; color:#27154e; font-size:1rem; }
        .patient-interview-context-card h3 [data-feather] { width:1.15rem; height:1.15rem; color:#5b16c8; }
        .patient-interview-context-current { padding:.75rem; border-radius:.75rem; background:#f4efff; }
        .patient-interview-context-current strong { color:#5b16c8; }
        .patient-interview-context-current p { margin:.3rem 0 0; color:#587080; line-height:1.35; }
        .patient-interview-tips { margin:0; padding:0; list-style:none; display:grid; gap:.55rem; color:#587080; }
        .patient-interview-tips li { display:flex; gap:.55rem; line-height:1.35; }
        .patient-interview-tips li::before { content:'•'; color:#1b9f7d; font-weight:800; }
        .patient-interview-message { max-width:82%; min-width:0; padding:1rem 1.15rem; border-radius:1rem; box-shadow:0 0.35rem 1rem rgba(31,52,72,.08); overflow-wrap:anywhere; font-size:1rem; line-height:1.5; }
        .patient-interview-message.assistant { align-self:stretch; width:100%; max-width:100%; background:linear-gradient(135deg,#f4efff 0%,#eef5f7 100%); color:#173042; }
        .patient-interview-message.patient { align-self:flex-end; background:#1b6b73; color:#fff; }
        .patient-interview-message[hidden] { display:none; }
        .interview-module-card { padding:0; border:0; border-radius:0; background:transparent; box-shadow:none; }
        .interview-module-card[hidden] { display:none !important; }
        .interview-message-header { display:flex; align-items:center; justify-content:space-between; gap:.75rem; min-height:2.75rem; flex-wrap:wrap; }
        .interview-message-header > .chat-voice-button, .interview-message-header > .chat-help-button { flex:0 0 auto; }
        .interview-message-header::before { content:'✦'; flex:0 0 2.75rem; width:2.75rem; height:2.75rem; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; color:#5a13bb; background:#f0eaff; font-size:1.35rem; }
        .interview-message-header > strong { flex:1; min-width:0; }
        .interview-question-text { margin:.45rem 0 0; color:#274968; font-size:1.08rem; line-height:1.5; }
        .patient-interview-question > .patient-interview-message.assistant { padding:1.2rem 1.35rem; border:1px solid #e4daf7; background:linear-gradient(135deg,#f5f0ff 0%,#edf5f7 100%); }
        .patient-interview-question > .patient-interview-message.assistant .interview-message-header strong { color:#27154e; font-size:1rem; }
        .patient-interview-question > .patient-interview-message.assistant .interview-question-text { color:#1f2854; font-size:clamp(1.35rem,2.2vw,1.65rem); font-weight:700; line-height:1.25; }
        .interview-composer-shell { margin-top:.8rem; padding:1rem; border:1px solid #e2d9f4; border-radius:1rem; background:#fff; box-shadow:0 .2rem .7rem rgba(72,45,120,.05); }
        .interview-composer-help { display:flex; gap:.45rem; align-items:flex-start; margin:.7rem 0 0; color:#52627a; font-size:.95rem; line-height:1.4; }
        .interview-composer-footer { display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; }
        .m03-dictation-note { display:block; margin:.35rem 0 0; color:#74658d; font-size:.82rem; line-height:1.35; }
        .interview-answer-count { flex:0 0 auto; color:#587080; font-size:.82rem; white-space:nowrap; }
        .patient-interview-answer { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-top:1rem; }
        .patient-interview-answer input { min-width:min(100%, 25rem); }
        .patient-interview-answer button { min-height:2.9rem; }
        .patient-interview-muted { color:#52627a; font-size:.95rem; }
        .patient-interview-context-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(12rem,1fr)); gap:.75rem; margin-top:.85rem; }
        .patient-interview-context-grid > div { padding:.75rem; border:1px solid #dcebed; border-radius:.75rem; background:#fbfefe; }
        .patient-interview-context-grid dt { color:#587080; font-size:.82rem; }
        .patient-interview-context-grid dd { margin:.2rem 0 0; color:#173042; }
        .chat-p11-map { border:1px solid #dcebed; border-radius:1rem; padding:1rem; background:#fbfefe; }
        .chat-p11-map-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(16rem,1fr)); gap:1rem; }
        .chat-p11-canvas-wrap { overflow:auto; background:#f1f5f6; border-radius:.75rem; text-align:center; }
        .chat-p11-canvas-wrap canvas { width:100%; max-width:22rem; height:auto; touch-action:none; background:#fff; }
        .chat-p11-preview { max-width:22rem; width:100%; border:2px solid #1b6b73; border-radius:.75rem; }
        .chat-stage-hidden { display:none !important; }
        .chat-history-panel { border:1px solid #dcebed; border-radius:.75rem; padding:.75rem 1rem; background:#fbfefe; }
        .chat-history-list { display:grid; gap:.65rem; margin:0; padding:0; list-style:none; }
        .chat-history-item { padding:.8rem .9rem; border:1px solid #e4daf2; border-radius:.75rem; background:#fff; }
        .chat-history-item dl { margin:0; }
        .chat-history-item dt { color:#587080; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
        .chat-history-item dd { margin:.2rem 0 0; color:#173042; line-height:1.45; overflow-wrap:anywhere; }
        .chat-history-empty { margin:0; color:#587080; }
        .m07-categories { display:grid; gap:1rem; }
        .m07-category { border:1px solid #dcebed; border-radius:1rem; padding:1rem; background:#fbfefe; }
        .m07-items { display:grid; gap:.75rem; }
        .m07-item { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.5rem .75rem; align-items:start; padding:.85rem; border:1px solid #e2edf0; border-radius:.75rem; background:#fff; }
        .m07-item-choice { display:flex; gap:.6rem; align-items:flex-start; min-width:0; color:#173042; font-weight:600; }
        .m07-item-choice span { overflow-wrap:anywhere; }
        .m07-item-details { grid-column:1 / -1; padding-top:.5rem; border-top:1px solid #edf3f4; }
        .m07-item-details[hidden] { display:none; }
        .m07-actions { justify-content:flex-start; }
        .m07-category-nav { display:flex; flex-wrap:wrap; align-items:center; gap:.45rem; margin:.9rem 0 1rem; padding:.75rem; border:1px solid #e2edf0; border-radius:.75rem; background:#fff; }
        .m07-category-nav button[aria-current="true"] { color:#fff; background:#5b1978; border-color:#5b1978; }
        .m07-category[hidden] { display:none; }
        .m07-navigation { justify-content:space-between; }
        [data-m07-status] { white-space:normal; text-align:center; }
        body.patient-interview-page [data-chat-history] { display:inline-flex; align-items:center; gap:.4rem; color:#5b1978; background:#fff; border-color:#5b1978; transition:background-color .18s ease,color .18s ease,border-color .18s ease; }
        body.patient-interview-page [data-chat-history]:hover, body.patient-interview-page [data-chat-history]:focus-visible { color:#fff; background:#5b1978; border-color:#5b1978; }
        body.patient-interview-page [data-chat-history] svg { width:1rem; height:1rem; }
        .interview-previous-button { display:inline-flex; align-items:center; gap:.35rem; min-height:2.5rem; margin:0 0 .7rem; padding:.45rem .7rem; color:#5b1978; background:transparent; border:1px solid transparent; border-radius:.6rem; font-size:.9rem; font-weight:650; }
        .interview-previous-button:hover, .interview-previous-button:focus-visible { color:#5b1978; background:#f3e8fa; border-color:#d9c7eb; }
        .interview-previous-button[hidden] { display:none; }
        body.patient-interview-page .patient-interview-sidebar-toggle { display:inline-flex; align-items:center; justify-content:center; gap:.4rem; min-height:2.6rem; min-width:2.6rem; padding:.45rem .7rem; color:#5b1978; background:transparent; border:1px solid transparent; border-radius:.65rem; }
        body.patient-interview-page .navbar.navbar-header .patient-interview-sidebar-toggle::before { content:none; }
        body.patient-interview-page .patient-interview-sidebar-toggle:hover, body.patient-interview-page .patient-interview-sidebar-toggle:focus-visible { background:#f3e8fa; border-color:#d9c7eb; }
        @media (max-width:767.98px) { body.patient-interview-page .patient-interview-sidebar-toggle { display:inline-flex; } body.patient-interview-page .interview-previous-button { margin-bottom:.55rem; } }
        .interview-composer { display:grid; grid-template-columns:minmax(0,1fr) auto; grid-template-rows:auto auto; gap:.65rem .7rem; align-items:center; width:100%; min-width:0; }
        .interview-composer-input { grid-column:1; grid-row:1; min-width:0; min-height:3.9rem; display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:.25rem; padding:.2rem .35rem .2rem .9rem; border:1px solid #bdc7dc; border-radius:.85rem; background:#fff; box-shadow:0 .15rem .45rem rgba(38,56,91,.08); }
        .interview-composer-input > label { grid-column:1 / -1; }
        .interview-composer-input > input, .interview-composer-input > textarea { grid-column:1; grid-row:1; min-width:0; width:100%; min-height:3.35rem; padding:.75rem .25rem; border:0; box-shadow:none; background:transparent; }
        .interview-composer-input > input:focus, .interview-composer-input > textarea:focus { outline:0; box-shadow:none; }
        .interview-composer .chat-send-button { grid-column:2; grid-row:1; width:auto; min-width:8rem; flex:0 0 auto; min-height:3.9rem; padding:.25rem .95rem; border-radius:.8rem; font-size:1.15rem; line-height:1; white-space:nowrap; word-break:normal; overflow-wrap:normal; color:#fff; background:#5b16c8; border-color:#5b16c8; }
        .interview-quick-actions, .m03-quick-actions { grid-column:1 / -1; grid-row:2; display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
        .m03-quick-actions[hidden] { display:none; }
        .m04-quick-actions { grid-column:1 / -1; grid-row:2; display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
        .interview-quick-actions button, .m03-quick-actions button { min-height:2.75rem; white-space:nowrap; word-break:normal; overflow-wrap:normal; }
        .m06-quick-actions { margin-top:1.25rem; gap:.85rem 1rem; padding:.25rem 0; }
        .m06-quick-actions > button { flex:0 1 auto; min-width:12rem; padding-inline:1rem; }
        .m04-quick-actions button { min-height:2.75rem; white-space:nowrap; word-break:normal; overflow-wrap:normal; }
        .m03-composer { display:grid; }
        .m03-input-wrap { min-width:0; }
        .m03-input-wrap .interview-text { min-width:0; width:100%; }
        #interview-chat .btn-primary:hover, #interview-chat .btn-primary:focus-visible, #interview-chat .btn-primary:active { color:#fff; background:#4c1499; border-color:#4c1499; }
        #interview-chat .btn-outline-primary:hover, #interview-chat .btn-outline-primary:focus-visible, #interview-chat .btn-outline-primary:active { color:#fff; background:#5b21b6; border-color:#5b21b6; }
        #interview-chat .btn-outline-secondary:hover, #interview-chat .btn-outline-secondary:focus-visible, #interview-chat .btn-outline-secondary:active { color:#24134f; background:#eee6ff; border-color:#8b6bc9; }
        #interview-chat button:disabled { color:#6f6f78; background:#ececf0; border-color:#d2d2d8; opacity:.85; }
        #interview-chat button:focus-visible { outline:3px solid #f3c969; outline-offset:2px; }
        .patient-interview-page .btn-primary:hover, .patient-interview-page .btn-primary:focus-visible, .patient-interview-page .btn-primary:active,
        .patient-interview-page .btn-outline-primary:hover, .patient-interview-page .btn-outline-primary:focus-visible, .patient-interview-page .btn-outline-primary:active { color:#fff; }
        .patient-interview-page .btn-primary:hover, .patient-interview-page .btn-primary:focus-visible, .patient-interview-page .btn-primary:active { background:#4c1499; border-color:#4c1499; }
        .patient-interview-page .btn-outline-primary:hover, .patient-interview-page .btn-outline-primary:focus-visible, .patient-interview-page .btn-outline-primary:active { background:#5b21b6; border-color:#5b21b6; }
        .patient-interview-page .btn-primary svg, .patient-interview-page .btn-outline-primary svg { color:currentColor; stroke:currentColor; }
        .patient-interview-page .btn:focus-visible { outline:3px solid #f3c969; outline-offset:2px; }
        .patient-interview-page .btn-primary:disabled, .patient-interview-page .btn-outline-primary:disabled { color:#6f6f78; background:#ececf0; border-color:#d2d2d8; opacity:.85; }
        .chat-voice-button, .chat-mic-button, .chat-mic-unavailable { min-width:2.75rem; min-height:2.75rem; width:2.75rem; padding:.25rem; display:inline-flex; align-items:center; justify-content:center; }
        .chat-voice-button { width:auto; min-height:44px; padding:.55rem .85rem; gap:.45rem; white-space:nowrap; font-size:.95rem; }
        #interview-chat .chat-voice-button { color:#5b21b6; background:#F1EAFF; border-color:#C4B5FD; box-shadow:none; }
        #interview-chat .chat-voice-button:hover:not(:disabled), #interview-chat .chat-voice-button:active:not(:disabled) { color:#fff; background:#4c1499; border-color:#4c1499; }
        #interview-chat .chat-voice-button:focus-visible:not(:disabled) { color:#5b21b6; background:#e8dcff; border-color:#8b6bc9; }
        #interview-chat .chat-voice-button:focus-visible { outline:3px solid #f3c969; outline-offset:2px; }
        #interview-chat .chat-voice-button:disabled { color:#6f6f78; background:#ececf0; border-color:#d2d2d8; opacity:.85; cursor:not-allowed; }
        #interview-chat .chat-voice-button svg { width:1rem; height:1rem; color:currentColor; stroke:currentColor; flex:0 0 auto; }
        .chat-mic-button, .chat-mic-unavailable { grid-column:2; grid-row:1; border-radius:50%; color:#5a13bb; background:#f0eaff; border:1px solid #e0d2ff; font-size:1.2rem; }
        .chat-mic-unavailable { cursor:help; opacity:.82; }
        .chat-mic-button.is-listening { color:#fff !important; background:#b42318 !important; border-color:#b42318 !important; }
        .interview-voice-panel { display:flex; align-items:center; gap:.55rem; flex-wrap:wrap; width:100%; margin:0; padding:.75rem .85rem; border:1px solid #dcebed; border-radius:.85rem; background:#fbfefe; }
        .patient-interview-context-panel .interview-voice-panel { display:grid; grid-template-columns:1fr; gap:.65rem; }
        .interview-voice-panel > strong { color:#27154e; }
        .interview-voice-status { color:#587080; font-size:.88rem; }
        .interview-voice-panel button, .interview-voice-panel select { min-height:2.5rem; }
        .interview-voice-panel select { width:auto; min-width:6.5rem; padding:.35rem .55rem; border:1px solid #bdc7dc; border-radius:.55rem; background:#fff; color:#173042; }
        .interview-voice-panel [hidden] { display:none !important; }
        .interview-voice-panel .voice-speed-control { display:inline-flex; align-items:center; justify-content:space-between; gap:.4rem; margin-left:auto; color:#52627a; font-size:.95rem; }
        .interview-voice-panel .voice-selector { flex:1 0 100%; display:grid; gap:.45rem; min-width:0; padding:.75rem; border:1px solid #e4dcf5; border-radius:.7rem; background:#fff; color:#27154e; }
        .interview-voice-panel .voice-selector[hidden] { display:none !important; }
        .interview-voice-panel .voice-selector select { width:100%; min-width:0; }
        .interview-voice-panel .voice-selector [data-voice-compare] { min-height:7.5rem; }
        .voice-selector-note { color:#52627a; font-size:.82rem; line-height:1.4; }
        .voice-selector-warning { color:#79520b; font-size:.82rem; line-height:1.4; }
        .voice-selector-actions { display:flex; flex-wrap:wrap; gap:.45rem; }
        .voice-selector-actions button { flex:1 1 9rem; }
        .interview-voice-panel[data-voice-state="VOICE_UNAVAILABLE"] { color:#7a1e18; background:#fff4f2; border-color:#e4aaa5; }
        .interview-voice-panel[data-voice-state="SPEAKING"] { border-color:#acdcca; background:#eaf8f2; }
        .interview-voice-panel[data-voice-state="PAUSED"], .interview-voice-panel[data-voice-state="MUTED"] { border-color:#ddccfa; background:#f4eafd; }
        .interview-progress-layout { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
        .interview-progress-context { display:flex; align-items:center; justify-content:flex-end; gap:.5rem; flex-wrap:wrap; }
        .interview-module-badge, .interview-state-badge { display:inline-flex; align-items:center; min-height:2rem; padding:.35rem .7rem; border-radius:999px; font-size:.84rem; font-weight:700; line-height:1.2; }
        .interview-module-badge { color:#4b1372; background:#f0eaff; border:1px solid #ddccfa; }
        .interview-state-badge { color:#24465a; background:#eef5f7; border:1px solid #c9e0e4; }
        .interview-state-badge[data-state="INTERPRETING"], .interview-state-badge[data-state="SAVING"] { color:#6b3f00; background:#fff4d6; border-color:#f1d28a; }
        .interview-state-badge[data-state="CONFIRMATION_REQUIRED"], .interview-state-badge[data-state="CLARIFICATION_REQUIRED"] { color:#5b1978; background:#f4eafd; border-color:#dcc5f2; }
        .interview-state-badge[data-state="SAVE_FAILED"], .interview-state-badge[data-state="SESSION_EXPIRED"], .interview-state-badge[data-state="MODULE_BLOCKED"] { color:#7a1e18; background:#fff0ef; border-color:#e4aaa5; }
        .interview-state-badge[data-state="RESPONSE_SAVED"], .interview-state-badge[data-state="MODULE_COMPLETED"] { color:#155d4b; background:#eaf8f2; border-color:#acdcca; }
        .patient-interview-chat { min-width:0; }
        .patient-interview-chat > h2 { margin-bottom:0; }
        .patient-interview-status { color:#173042; }
        .patient-interview-status[hidden] { display:none !important; }
        .patient-interview-status:not(:empty) { display:block; min-height:1.5rem; margin-top:.25rem; padding:.55rem .75rem; border-left:.25rem solid #1b6b73; border-radius:.65rem; background:#f4fafb; }
        .patient-interview-status:not(:empty)[role="alert"] { color:#7a1e18; background:#fff4f2; border-left-color:#b42318; }
        .interview-module-card, .patient-interview-question { min-width:0; }
        .interview-composer-input { min-width:0; }
        .interview-composer-input > input, .interview-composer-input > textarea, .interview-proposal-fields input, .patient-interview-answer input { max-width:100%; }
        .interview-zone-label { margin:0 0 .55rem; color:#587080; font-size:.8rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
        #interview-chat .chat-help-button { min-height:44px; color:#27324a; background:#fff; border:1px solid #cbd2df; box-shadow:none; }
        #interview-chat .chat-help-button:hover:not(:disabled), #interview-chat .chat-help-button:active:not(:disabled), #interview-chat .chat-help-button:focus-visible:not(:disabled) { color:#202b42; background:#f5f6f9; border-color:#98a4b8; }
        #interview-chat .m02-active-question .m02-finalization-actions { display:flex; flex-direction:column; align-items:flex-start; margin:1.2rem .25rem .75rem; }
        #interview-chat .m02-active-question .m02-finalization-actions > [data-m02-resolution-action] { min-height:44px; margin:0; }
        #interview-chat .m02-active-question .m02-safe-no-button, #interview-chat .m03-composer .m02-safe-no-button { min-height:44px; color:#4c3380; background:#fff; border:1px solid #b9a3df; box-shadow:none; }
        #interview-chat .m02-active-question .m02-safe-no-button:hover:not(:disabled), #interview-chat .m02-active-question .m02-safe-no-button:focus-visible:not(:disabled), #interview-chat .m03-composer .m02-safe-no-button:hover:not(:disabled), #interview-chat .m03-composer .m02-safe-no-button:focus-visible:not(:disabled) { color:#3e286c; background:#f4efff; border-color:#8b6bc9; }
        .interview-contextual-help { position:absolute !important; width:1px !important; height:1px !important; padding:0 !important; margin:-1px !important; overflow:hidden !important; clip:rect(0,0,0,0) !important; white-space:nowrap !important; border:0 !important; }
        .interview-contextual-help[hidden] { display:none !important; }
        @media (max-width:1199.98px) { .patient-interview-shell { max-width:1100px; } }
        @media (max-width:1024px) { .patient-interview-shell { max-width:920px; } .patient-interview-header, .patient-interview-conversation-card > .card-body { padding:1rem; } .patient-interview-header { grid-template-columns:1fr; gap:.8rem; } .patient-interview-header-context, .patient-interview-header-actions { justify-content:flex-start; } }
        @media (max-width:900px) { .patient-interview-conversation-layout { grid-template-columns:1fr; } }
        @media (max-width:767.98px) { .patient-interview-shell { padding-inline:.25rem; } .patient-interview-header { align-items:stretch; } .patient-interview-header-side { width:100%; } .patient-interview-header-actions { flex-wrap:wrap; } .patient-interview-header-actions > * { flex:1 1 auto; } .interview-progress-context { justify-content:flex-start; } .patient-interview-message { max-width:100%; } }
        @media (max-width:600px) { .patient-interview-header-actions { display:grid !important; grid-template-columns:1fr; } .patient-interview-header-actions > * { width:100%; } }
        @media (max-width:430px) { .patient-interview-main { padding-inline:.5rem !important; } .patient-interview-header, .patient-interview-conversation-card > .card-body { padding:.85rem; } .interview-progress-layout { gap:.65rem; } .interview-progress-context { width:100%; justify-content:space-between; } .interview-module-badge, .interview-state-badge { font-size:.78rem; } .interview-message-header { align-items:flex-start; } .interview-quick-actions button, .m03-quick-actions button { flex:1 1 auto; } .interview-voice-panel { align-items:stretch; } .interview-voice-panel > * { flex:1 1 auto; } .interview-voice-panel .voice-speed-control { width:100%; margin-left:0; justify-content:space-between; } .interview-voice-panel select { flex:0 1 9rem; } .interview-composer-footer { align-items:flex-start; } }
        @media (max-width:600px) { .m06-quick-actions { gap:.75rem; } .m06-quick-actions > button { flex:1 1 100%; min-width:0; width:100%; } }
        @media (max-width:375px) { .patient-interview-main { padding-inline:.35rem !important; } .interview-composer { gap:.45rem; } .interview-composer-input { padding-left:.55rem; } .interview-composer .chat-send-button { width:2.75rem; min-width:2.75rem; padding-inline:.3rem; } .chat-send-label { display:none; } .chat-mic-button, .chat-mic-unavailable { width:2.55rem; min-width:2.55rem; } .interview-quick-actions, .m03-quick-actions { gap:.35rem; } .interview-quick-actions button, .m03-quick-actions button { padding-inline:.55rem; font-size:.88rem; } }
        @media (prefers-reduced-motion:reduce) { *, *::before, *::after { scroll-behavior:auto !important; animation-duration:.01ms !important; animation-iteration-count:1 !important; transition-duration:.01ms !important; } }

        /* Chat-first visual system: presentation-only overrides; functional hooks remain unchanged. */
        .patient-interview-page {
            --interview-ink:#17133d;
            --interview-muted:#5d6b86;
            --interview-purple:#5b21b6;
            --interview-purple-soft:#f4efff;
            --interview-border:#e3e7f2;
            background:#f7f8fc;
        }
        .patient-interview-main {
            background:linear-gradient(180deg,#fbfbff 0%,#f4f5fb 100%);
        }
        .patient-interview-shell { max-width:1440px; padding-inline:.25rem; }
        .patient-interview-header {
            grid-template-columns:minmax(16rem,.9fr) minmax(32rem,1.35fr);
            gap:1.15rem;
            padding:.8rem 1.1rem;
            border-color:var(--interview-border);
            border-radius:1rem;
            box-shadow:0 .35rem 1.1rem rgba(29,35,70,.06);
        }
        .patient-interview-header-copy .dashboard-eyebrow { color:var(--interview-purple); font-size:.68rem; font-weight:750; }
        .patient-interview-header-copy h1 { color:var(--interview-ink); font-size:clamp(1.55rem,2vw,2rem); letter-spacing:-.025em; }
        .patient-interview-header-side {
            grid-template-columns:auto minmax(11rem,1fr) auto;
            gap:.55rem;
            align-items:center;
        }
        .patient-interview-header-context { justify-content:flex-start; }
        .patient-interview-header-actions { justify-content:flex-end; gap:.35rem; }
        .patient-interview-header-actions > * { min-height:2.15rem; padding:.3rem .55rem; font-size:.8rem; }
        .patient-interview-header-actions .btn-outline-primary,
        .patient-interview-header-actions .btn-outline-secondary { border-color:#b6abd3; color:var(--interview-purple); background:#fff; }
        .patient-interview-conversation-layout {
            grid-template-columns:1fr;
            gap:1rem;
            align-items:start;
            max-width:min(1090px,calc(100% - 260px));
        }
        .patient-interview-conversation-card { border-color:var(--interview-border); border-radius:1.05rem; box-shadow:0 .35rem 1.1rem rgba(29,35,70,.055); }
        .patient-interview-conversation-card > .card-body { padding:1.15rem; }
        .patient-interview-chat { gap:.8rem; }
        .patient-interview-chat > h2 { margin-bottom:.1rem; color:var(--interview-ink); font-size:1.35rem; letter-spacing:-.015em; }
        .interview-zone-label { margin-bottom:.25rem; color:var(--interview-purple); font-size:.7rem; letter-spacing:.08em; }
        .patient-interview-message { box-shadow:none; border:1px solid var(--interview-border); border-radius:.9rem; }
        .patient-interview-message.assistant { background:#f7f8fe; color:#33415d; }
        .patient-interview-message.patient { background:#26747a; }
        .patient-interview-message[data-interview-zone="assistant"] { max-width:78%; padding:.75rem .95rem; background:#f4f5fc; }
        .patient-interview-message[data-interview-zone="assistant"] .interview-message-header::before { display:none; }
        .patient-interview-message[data-interview-zone="assistant"] .interview-question-text { margin:.2rem 0 0; font-size:.95rem; line-height:1.42; }
        .patient-interview-question:not([hidden]):not(.chat-stage-hidden) {
            padding:.8rem;
            border:1px solid #dcd3f4;
            border-left:.3rem solid var(--interview-purple);
            border-radius:1rem;
            background:#fff;
            box-shadow:0 .4rem 1.2rem rgba(91,33,182,.08);
        }
        .patient-interview-question > .patient-interview-message.assistant {
            padding:.85rem 1rem;
            border:0;
            border-radius:.75rem;
            background:var(--interview-purple-soft);
        }
        .patient-interview-question > .patient-interview-message.assistant .interview-message-header::before { flex-basis:2.25rem; width:2.25rem; height:2.25rem; font-size:1.05rem; }
        .patient-interview-question > .patient-interview-message.assistant .interview-question-text { color:var(--interview-ink); font-size:clamp(1.25rem,1.9vw,1.65rem); line-height:1.24; }
        .interview-composer-shell { margin-top:.65rem; padding:.75rem; border-color:var(--interview-border); border-radius:.8rem; box-shadow:none; }
        .interview-composer-input { min-height:3.55rem; border-color:#c8cfe0; border-radius:.7rem; box-shadow:none; }
        .interview-composer-input > input, .interview-composer-input > textarea { min-height:3rem; font-size:1rem; }
        .interview-composer .chat-send-button { min-height:3.55rem; min-width:7rem; border-radius:.7rem; font-size:1rem; }
        .chat-mic-button, .chat-mic-unavailable { min-width:2.55rem; width:2.55rem; min-height:2.55rem; height:2.55rem; font-size:1.05rem; }
        .interview-quick-actions, .m03-quick-actions { gap:.4rem; }
        .m04-quick-actions { gap:.4rem; }
        .interview-quick-actions button, .m03-quick-actions button { min-height:2.45rem; padding:.3rem .7rem; font-size:.9rem; }
        .m04-quick-actions button { min-height:2.45rem; padding:.3rem .7rem; font-size:.9rem; }
        .interview-composer-help { margin-top:.55rem; color:var(--interview-muted); font-size:.84rem; }
        .interview-answer-count { color:var(--interview-muted); font-size:.78rem; }
        .patient-interview-context-panel { gap:.7rem; }
        .patient-interview-context-card { padding:.85rem; border-color:var(--interview-border); border-radius:.85rem; box-shadow:0 .25rem .8rem rgba(29,35,70,.04); }
        .patient-interview-context-card h3 { margin-bottom:.55rem; color:var(--interview-ink); font-size:.95rem; }
        .patient-interview-context-current { padding:.65rem; background:#f5f1ff; }
        .patient-interview-context-current p, .patient-interview-tips { font-size:.88rem; }
        .patient-interview-tips { gap:.4rem; }
        .patient-interview-context-panel .interview-voice-panel { gap:.45rem; padding:.65rem; border-color:#dce9e5; background:#f8fcfa; }
        .patient-interview-context-panel .interview-voice-panel > strong { font-size:.9rem; }
        .patient-interview-context-panel .interview-voice-status { font-size:.8rem; }
        .patient-interview-context-panel .interview-voice-panel button,
        .patient-interview-context-panel .interview-voice-panel select { min-height:2.2rem; font-size:.85rem; }
        .patient-interview-context-panel .interview-voice-panel .voice-speed-control { margin-left:0; font-size:.82rem; }
        .patient-interview-context-panel .interview-voice-panel select { min-width:5.8rem; }
        .patient-interview-context-panel > .patient-interview-context-card[aria-labelledby="interview-voice-title"] {
            padding:0;
            border:0;
            background:transparent;
            box-shadow:none;
        }
        .patient-interview-context-panel > .patient-interview-context-card[aria-labelledby="interview-voice-title"] > h3 { display:none; }
        .patient-interview-context-panel > .patient-interview-context-card[aria-labelledby="interview-tips-title"] { display:none; }
        body.patient-interview-page #sidebar.is-compact,
        body.patient-interview-page #sidebar.sidebar-compact { transition:width .2s ease, transform .2s ease; }
        @media (max-width:1199.98px) {
            .patient-interview-header { grid-template-columns:minmax(15rem,.85fr) minmax(30rem,1.15fr); }
            .patient-interview-header-side { grid-template-columns:auto minmax(10rem,1fr); }
            .patient-interview-header-actions { grid-column:1 / -1; justify-content:flex-start; }
        }
        @media (max-width:1024px) {
            .patient-interview-shell { max-width:920px; }
            .patient-interview-header { grid-template-columns:1fr; gap:.7rem; }
            .patient-interview-header-side { grid-template-columns:auto minmax(12rem,1fr) auto; }
            .patient-interview-header-actions { grid-column:auto; justify-content:flex-end; }
        }
        @media (max-width:1024px) {
            .patient-interview-conversation-layout { max-width: calc(100% - 250px); }
        }
        @media (max-width:900px) {
            .patient-interview-conversation-layout { grid-template-columns:1fr; }
            .patient-interview-context-panel { grid-template-columns:repeat(3,minmax(0,1fr)); align-items:start; }
        }
        @media (max-width:767.98px) {
            .patient-interview-conversation-layout { max-width: 100%; }
            .patient-interview-shell { padding-inline:.15rem; }
            .patient-interview-header-side { grid-template-columns:1fr; }
            .patient-interview-header-context, .patient-interview-header-actions { justify-content:flex-start; }
            .patient-interview-header-actions { display:flex !important; }
            .patient-interview-header-actions > * { flex:1 1 auto; }
            .patient-interview-context-panel { grid-template-columns:1fr; }
            .patient-interview-message[data-interview-zone="assistant"] { max-width:100%; }
            .patient-interview-question:not([hidden]):not(.chat-stage-hidden) { padding:.55rem; }
        }
        @media (max-width:430px) {
            .patient-interview-header, .patient-interview-conversation-card > .card-body { padding:.75rem; }
            .patient-interview-header-actions { display:grid !important; grid-template-columns:repeat(2,minmax(0,1fr)); }
            .patient-interview-header-actions > * { width:100%; }
            .patient-interview-header-actions > :last-child { grid-column:1 / -1; }
            .interview-composer { grid-template-columns:minmax(0,1fr) auto; }
            .interview-composer .chat-send-button { min-width:6rem; }
        }
        @media (max-width:375px) {
            .patient-interview-header-actions { grid-template-columns:1fr; }
            .patient-interview-header-actions > :last-child { grid-column:auto; }
            .interview-composer .chat-send-button { min-width:2.75rem; }
        }

        /* Reference-parity correction: keep the existing DOM and hooks, but restore the open header and conversation hierarchy. */
        .patient-interview-main { padding-top:1rem !important; }
        .patient-interview-shell { max-width:1395px; padding-inline:0; }
        .patient-interview-header {
            grid-template-columns:minmax(0,1fr) minmax(31rem,.96fr);
            min-height:6.9rem;
            margin-bottom:.55rem !important;
            padding:.35rem .2rem .75rem;
            border:0;
            border-radius:0;
            background:transparent;
            box-shadow:none;
        }
        .patient-interview-header-copy { padding-top:.2rem; }
        .patient-interview-header-copy .dashboard-eyebrow { margin-bottom:.35rem; font-size:.73rem; }
        .patient-interview-header-copy h1 { margin-bottom:.35rem; font-size:clamp(2rem,2.55vw,2.85rem); font-weight:700; letter-spacing:-.035em; }
        .patient-interview-header-side {
            grid-template-columns:minmax(0,1fr) auto;
            grid-template-areas:"progress context" "actions actions";
            align-content:center;
            gap:.55rem .75rem;
        }
        .patient-interview-header-context { grid-area:context; align-self:start; justify-content:flex-end; }
        .patient-interview-header-actions { grid-area:actions; justify-content:flex-end; }
        .patient-interview-header-actions > * { min-height:2.2rem; padding:.3rem .7rem; font-size:.84rem; }
        .interview-module-badge, .interview-state-badge { min-height:2.1rem; padding:.35rem .8rem; font-size:.86rem; }
        .patient-interview-conversation-layout { grid-template-columns:1fr; gap:1.35rem; max-width:min(1090px,calc(100% - 260px)); }
        .patient-interview-conversation-card { border-color:#eceaf2; border-radius:1rem; box-shadow:0 .3rem 1rem rgba(35,30,77,.045); }
        .patient-interview-conversation-card > .card-body { padding:1.65rem 2rem; }
        .patient-interview-chat { gap:1.15rem; }
        .patient-interview-chat > .interview-zone-label,
        .patient-interview-chat > h2 { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
        .patient-interview-message.assistant {
            position:relative;
            width:min(100%,86%);
            max-width:86%;
            margin-left:3.7rem;
            padding:1rem 1.2rem;
            border:0;
            border-radius:.95rem;
            background:#f3f1ff;
            box-shadow:none;
        }
        .patient-interview-message.assistant .interview-message-header { position:relative; min-height:2.45rem; justify-content:flex-start; }
        .patient-interview-message.assistant .interview-message-header::before {
            position:absolute;
            left:-3.65rem;
            top:0;
            flex-basis:2.65rem;
            width:2.65rem;
            height:2.65rem;
            color:#5b21b6;
            background:#eee9ff;
            font-size:1.2rem;
        }
        .patient-interview-message.assistant .interview-question-text { margin-top:.35rem; color:#536484; font-size:1rem; line-height:1.45; }
        .patient-interview-message[data-interview-zone="assistant"] { width:min(100%,86%); max-width:86%; padding:.95rem 1.2rem; background:#f4f2ff; }
        .patient-interview-message[data-interview-zone="assistant"] .interview-message-header::before { display:inline-flex; }
        .patient-interview-question:not([hidden]):not(.chat-stage-hidden) {
            padding:0;
            border:0;
            border-radius:1rem;
            background:transparent;
            box-shadow:none;
        }
        .patient-interview-question > .patient-interview-message.assistant {
            width:100%;
            max-width:100%;
            margin-left:0;
            padding:1.05rem 1.2rem 1.15rem 4.45rem;
            border:1px solid #e4dcf7;
            border-radius:1rem 1rem .3rem .3rem;
            background:linear-gradient(135deg,#f3efff 0%,#f0f7f7 100%);
        }
        .patient-interview-question > .patient-interview-message.assistant .interview-message-header::before { left:-3.45rem; }
        .patient-interview-question > .patient-interview-message.assistant .interview-message-header strong { color:#1b1648; font-size:1rem; }
        .patient-interview-question > .patient-interview-message.assistant .interview-question-text { margin-top:.45rem; color:#191746; font-size:clamp(1.55rem,2.15vw,2rem); line-height:1.18; }
        /* M04 uses a module card, but its current conversational question
           needs the same visual hierarchy as the active M03 question. The
           data attributes and canonical text remain unchanged. */
        .patient-interview-module-context[data-module="M04"] > .patient-interview-message.assistant {
            width:100%;
            max-width:100%;
            margin-left:0;
            padding:1.05rem 1.2rem 1.15rem 4.45rem;
            border:1px solid #e4dcf7;
            border-radius:1rem 1rem .3rem .3rem;
            background:linear-gradient(135deg,#f3efff 0%,#f0f7f7 100%);
        }
        .patient-interview-module-context[data-module="M04"] > .patient-interview-message.assistant .interview-message-header::before { left:-3.45rem; }
        .patient-interview-module-context[data-module="M04"] > .patient-interview-message.assistant .interview-message-header strong { color:#1b1648; font-size:1rem; }
        .patient-interview-module-context[data-module="M04"] > .patient-interview-message.assistant .interview-question-text {
            margin-top:.45rem;
            color:#191746;
            font-size:clamp(2rem,2.8vw,2.5rem);
            line-height:1.16;
            font-weight:650;
        }
        .patient-interview-module-context[data-module="M04"] .m04-input-stage { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:.7rem; width:100%; }
        .patient-interview-module-context[data-module="M04"] .m04-input-stage[hidden],
        .patient-interview-module-context[data-module="M04"] .m04-stage-actions[hidden] { display:none !important; }
        .patient-interview-module-context[data-module="M04"] .m04-stage-actions { display:flex; flex-wrap:wrap; gap:.65rem; }
        .patient-interview-module-context[data-module="M04"] .m04-input-stage .interview-composer-input { width:100%; }
        /* M05 V1/V2 use one canonical question at a time. Keep the topic
           secondary and place the active question in the assistant bubble so
           it shares the hierarchy already used by M03/M04. */
        .patient-interview-module-context[data-module="M05"] > .patient-interview-message.assistant,
        .patient-interview-module-context[data-module="M05-V2"] > .patient-interview-message.assistant {
            width:100%;
            max-width:100%;
            margin-left:0;
            padding:1.05rem 1.2rem 1.15rem 4.45rem;
            border:1px solid #e4dcf7;
            border-radius:1rem 1rem .3rem .3rem;
            background:linear-gradient(135deg,#f3efff 0%,#f0f7f7 100%);
        }
        .patient-interview-module-context[data-module="M05"] > .patient-interview-message.assistant .interview-message-header::before,
        .patient-interview-module-context[data-module="M05-V2"] > .patient-interview-message.assistant .interview-message-header::before { left:-3.45rem; }
        .patient-interview-module-context[data-module="M05"] > .patient-interview-message.assistant .interview-message-header strong,
        .patient-interview-module-context[data-module="M05-V2"] > .patient-interview-message.assistant .interview-message-header strong { color:#1b1648; font-size:1rem; }
        .patient-interview-module-context[data-module="M05"] .patient-interview-question-topic,
        .patient-interview-module-context[data-module="M05-V2"] .patient-interview-question-topic {
            margin:.6rem 0 .15rem;
            color:#5b21b6;
            font-size:.95rem;
            font-weight:650;
        }
        .patient-interview-module-context[data-module="M05"] > .patient-interview-message.assistant .interview-question-text,
        .patient-interview-module-context[data-module="M05-V2"] > .patient-interview-message.assistant .interview-question-text {
            margin-top:.35rem;
            color:#191746;
            font-size:clamp(2rem,2.8vw,2.5rem);
            line-height:1.16;
            font-weight:650;
        }
        .patient-interview-module-context[data-module="M06"] > .patient-interview-message.assistant .interview-question-text {
            margin-top:.35rem;
            color:#191746;
            font-size:clamp(2rem,2.8vw,2.5rem);
            line-height:1.16;
            font-weight:650;
        }
        .interview-composer-shell { margin-top:0; padding:1rem 1.2rem 1.05rem; border:1px solid #e4dcf7; border-top:0; border-radius:.3rem  .3rem 1rem 1rem; background:#fff; box-shadow:0 .25rem .8rem rgba(35,30,77,.04); }
        .interview-composer-input { min-height:3.75rem; border-color:#c8c7dd; border-radius:.7rem; background:#fff; }
        .interview-composer-input > input, .interview-composer-input > textarea { min-height:3.2rem; padding-inline:.35rem; color:#27365a; font-size:1rem; }
        .interview-composer .chat-send-button { min-height:3.75rem; min-width:8.2rem; border-radius:.7rem; font-size:1.05rem; }
        .interview-composer-help { margin-top:.65rem; color:#687795; font-size:.86rem; }
        .interview-answer-count { color:#687795; font-size:.8rem; }
        .patient-interview-context-panel { gap:.85rem; }
        .patient-interview-context-card { padding:1rem; border:0; border-radius:1rem; background:rgba(255,255,255,.82); box-shadow:0 .25rem .8rem rgba(35,30,77,.04); }
        .patient-interview-context-card h3 { margin-bottom:.7rem; color:#1b1648; font-size:1rem; }
        .patient-interview-context-current { padding:.8rem; background:#f3efff; }
        .patient-interview-context-current strong { font-size:1.02rem; }
        .patient-interview-context-current p { color:#66718d; font-size:.95rem; }
        .patient-interview-context-panel .interview-voice-panel { gap:.55rem; padding:.8rem; border:1px solid #dfe9e7; border-radius:.8rem; background:#f8fcfb; }
        .patient-interview-context-panel .interview-voice-panel { display:grid; grid-template-columns:minmax(0,1fr); gap:.7rem; }
        .patient-interview-context-panel .interview-voice-panel button { min-height:44px; }
        .patient-interview-context-panel .voice-action-row { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.8rem; width:100%; }
        .patient-interview-context-panel .voice-action-button { display:inline-flex; align-items:center; justify-content:center; width:100%; min-width:44px; min-height:44px; padding:.45rem; border:1px solid #d7c8f2; border-radius:.7rem; color:#4c168f; background:#fff; transition:background-color .16s ease,border-color .16s ease,box-shadow .16s ease,transform .16s ease; }
        .patient-interview-context-panel .voice-action-button:hover:not(:disabled) { border-color:#7b43c5; background:#f5efff; }
        .patient-interview-context-panel .voice-action-button:active:not(:disabled) { transform:translateY(1px); background:#ede3ff; }
        .patient-interview-context-panel .voice-action-button:focus-visible { outline:3px solid #e0bf62; outline-offset:2px; }
        .patient-interview-context-panel .voice-action-button:disabled { cursor:not-allowed; color:#85849a; border-color:#e1dfeb; background:#f4f3f8; opacity:.72; }
        .patient-interview-context-panel .voice-action-button svg { width:1.2rem; height:1.2rem; stroke:currentColor; }
        .patient-interview-context-panel .voice-speed-control { grid-column:1 / -1; width:100%; margin:0; }
        .patient-interview-context-panel .voice-speed-control select { min-height:44px; }
        .patient-interview-context-panel .interview-voice-panel [data-voice-repeat] { color:#fff; background:#5b21b6; border-color:#5b21b6; }
        .patient-interview-context-panel .interview-voice-panel [data-voice-repeat]:hover:not(:disabled) { color:#fff; background:#4c1499; border-color:#4c1499; }
        .patient-interview-context-panel .interview-voice-panel .voice-action-button[data-voice-repeat]:disabled { color:#85849a; border-color:#e1dfeb; background:#f4f3f8; }
        .patient-interview-tips { color:#66718d; font-size:.9rem; }
        @media (max-width:1199.98px) {
            .patient-interview-header { grid-template-columns:minmax(0,1fr) minmax(28rem,1fr); }
        }
        @media (max-width:1024px) {
            .patient-interview-main { padding-top:.65rem !important; }
            .patient-interview-header { grid-template-columns:1fr; min-height:0; padding-inline:0; }
            .patient-interview-header-side { grid-template-columns:minmax(0,1fr) auto; grid-template-areas:"progress context" "actions actions"; }
            .patient-interview-header-actions { justify-content:flex-start; }
            .patient-interview-conversation-layout { max-width: calc(100% - 250px); }
        }
        @media (max-width:900px) {
            .patient-interview-conversation-layout { grid-template-columns:1fr; }
            .patient-interview-context-panel { grid-template-columns:repeat(3,minmax(0,1fr)); }
        }
        @media (max-width:767.98px) {
            .patient-interview-conversation-layout { max-width: 100%; width: 100%; }
            .patient-interview-conversation-card { padding-bottom: 3.5rem; }
            .patient-interview-header-copy h1 { font-size:clamp(1.8rem,7vw,2.35rem); }
            .patient-interview-header-side { grid-template-columns:1fr; grid-template-areas:"context" "progress" "actions"; }
            .patient-interview-header-context { justify-content:flex-start; }
            .patient-interview-header-actions { display:flex !important; flex-wrap:wrap; }
            .patient-interview-header-actions > * { flex:1 1 auto; }
            .patient-interview-conversation-card > .card-body { padding:1rem; }
            .patient-interview-context-panel { grid-template-columns:1fr; }
            .patient-interview-message.assistant,
            .patient-interview-message[data-interview-zone="assistant"] { width:calc(100% - 2.8rem); max-width:calc(100% - 2.8rem); margin-left:2.8rem; }
            .patient-interview-question > .patient-interview-message.assistant { width:100%; max-width:100%; margin-left:0; padding-left:3.75rem; }
            .patient-interview-question > .patient-interview-message.assistant .interview-question-text { font-size:clamp(1.3rem,5.5vw,1.7rem); }
            .patient-interview-module-context[data-module="M04"] > .patient-interview-message.assistant { width:100%; max-width:100%; margin-left:0; padding-left:3.75rem; }
            .patient-interview-module-context[data-module="M04"] > .patient-interview-message.assistant .interview-question-text { font-size:clamp(1.5rem,6vw,2rem); }
            .patient-interview-module-context[data-module="M05"] > .patient-interview-message.assistant,
            .patient-interview-module-context[data-module="M05-V2"] > .patient-interview-message.assistant { width:100%; max-width:100%; margin-left:0; padding-left:3.75rem; }
            .patient-interview-module-context[data-module="M05"] > .patient-interview-message.assistant .interview-question-text,
            .patient-interview-module-context[data-module="M05-V2"] > .patient-interview-message.assistant .interview-question-text { font-size:clamp(1.5rem,6vw,2rem); }
            .patient-interview-module-context[data-module="M06"] > .patient-interview-message.assistant .interview-question-text { font-size:clamp(1.5rem,6vw,2rem); }
            .interview-composer-shell { padding-inline:.75rem; }
        }
        @media (max-width:430px) {
            .patient-interview-header { padding-top:.1rem; }
            .patient-interview-header-actions { display:grid !important; grid-template-columns:repeat(2,minmax(0,1fr)); }
            .patient-interview-header-actions > :last-child { grid-column:1 / -1; }
            .patient-interview-message.assistant,
            .patient-interview-message[data-interview-zone="assistant"] { width:calc(100% - 2.25rem); max-width:calc(100% - 2.25rem); margin-left:2.25rem; padding-inline:.85rem; }
            .patient-interview-message.assistant .interview-message-header::before { left:-2.55rem; width:2.15rem; height:2.15rem; }
            .patient-interview-question > .patient-interview-message.assistant { width:100%; max-width:100%; margin-left:0; padding-left:3.2rem; }
            .patient-interview-module-context[data-module="M04"] > .patient-interview-message.assistant { padding-left:3.2rem; }
            .patient-interview-module-context[data-module="M04"] > .patient-interview-message.assistant .interview-question-text { font-size:clamp(1.35rem,7vw,1.8rem); }
            .patient-interview-module-context[data-module="M05"] > .patient-interview-message.assistant,
            .patient-interview-module-context[data-module="M05-V2"] > .patient-interview-message.assistant { padding-left:3.2rem; }
            .patient-interview-module-context[data-module="M05"] > .patient-interview-message.assistant .interview-question-text,
            .patient-interview-module-context[data-module="M05-V2"] > .patient-interview-message.assistant .interview-question-text { font-size:clamp(1.35rem,7vw,1.8rem); }
            .patient-interview-module-context[data-module="M06"] > .patient-interview-message.assistant .interview-question-text { font-size:clamp(1.35rem,7vw,1.8rem); }
            .interview-composer { grid-template-columns:minmax(0,1fr) auto; }
            .interview-composer .chat-send-button { min-width:6.8rem; }
        }
        @media (max-width:375px) {
            .patient-interview-header-actions { grid-template-columns:1fr; }
            .patient-interview-header-actions > :last-child { grid-column:auto; }
            .interview-composer .chat-send-button { min-width:2.75rem; }
        }

        /* Header layout; progress is presented inside the conversation card. */
        .patient-interview-header,
        .patient-interview-header-copy,
        .patient-interview-header-actions { min-width:0; max-width:100%; }
        .patient-interview-header {
            grid-template-columns:minmax(0,1fr) auto;
            grid-template-areas:"copy context" "actions actions";
            column-gap:1.15rem;
            row-gap:.9rem;
            align-items:start;
        }
        .patient-interview-header-copy { grid-area:copy; }
        .patient-interview-header-context {
            grid-area:context;
            display:flex;
            justify-content:flex-end;
            align-items:center;
            gap:.4rem;
            flex-wrap:wrap;
            min-width:0;
        }
        .patient-interview-header-copy h1,
        .patient-interview-header-actions { overflow-wrap:anywhere; }
        .patient-interview-header-copy h1 { margin-bottom:0; }
        .patient-interview-header-actions {
            grid-area:actions;
            justify-content:flex-end;
            flex-wrap:wrap;
        }
        .patient-interview-header-actions > * { flex:0 1 auto; max-width:100%; }
        .patient-interview-message[data-interview-zone="assistant"] { padding:1.2rem 1.5rem; }
        .patient-interview-message[data-interview-zone="assistant"] .interview-question-text {
            margin-top:.3rem;
            color:#33415f;
            font-size:clamp(1.125rem,1.3vw,1.25rem);
            line-height:1.55;
        }
        @media (max-width:1199.98px) {
            .patient-interview-header { grid-template-columns:minmax(0,1fr); }
            .patient-interview-header-context { justify-content:flex-start; }
            .patient-interview-header-actions { justify-content:flex-start; }
        }
        @media (max-width:767.98px) {
            .patient-interview-header { row-gap:.75rem; }
            .patient-interview-header-context,
            .patient-interview-header-actions { justify-content:flex-start; }
            .patient-interview-header-actions > * { flex:1 1 auto; }
            .patient-interview-message[data-interview-zone="assistant"] .interview-question-text {
                font-size:clamp(1rem,4vw,1.125rem);
                line-height:1.55;
            }
        }
        @media (max-width:430px) {
            .patient-interview-header { row-gap:.7rem; }
            .patient-interview-header-actions { display:grid !important; grid-template-columns:repeat(2,minmax(0,1fr)); }
            .patient-interview-header-actions > * { width:100%; }
            .patient-interview-header-actions > :last-child { grid-column:1 / -1; }
        }
        @media (max-width:375px) {
            .patient-interview-header-actions { grid-template-columns:1fr; }
            .patient-interview-header-actions > :last-child { grid-column:auto; }
        }

        /* Shared clinical question pattern for the approved M02 and M03 headers. */
        #interview-chat .clinical-question-header {
            padding:1.15rem 1.25rem;
            border:1px solid #E2D6F5;
            border-radius:.9rem;
            background:#F2ECFC;
            box-shadow:none;
        }
        #interview-chat .conversational-guidance {
            display:flex;
            align-items:center;
            gap:1.2rem;
            width:100%;
            min-height:6.75rem;
            margin:0 0 .9rem;
            padding:1.3rem 1.65rem;
            border:1px solid #E2D6F5;
            border-radius:1.2rem;
            color:#534665;
            background:linear-gradient(110deg,#FBFAFF 0%,#F2ECFC 65%,#E8DDFA 100%);
        }
        #interview-chat .conversational-guidance__icon {
            display:grid;
            place-items:center;
            flex:0 0 3.75rem;
            width:3.75rem;
            height:3.75rem;
            border-radius:50%;
            color:#6327A9;
            background:#E8DCFA;
        }
        #interview-chat .conversational-guidance__icon svg { width:1.8rem; height:1.8rem; }
        #interview-chat .conversational-guidance__content { min-width:0; }
        #interview-chat .conversational-guidance__title {
            margin:0 0 .3rem;
            color:#6327A9;
            font-size:1.2rem;
            font-weight:650;
            line-height:1.35;
        }
        #interview-chat .conversational-guidance__description {
            margin:0;
            color:#534665;
            font-size:1rem;
            line-height:1.5;
            overflow-wrap:anywhere;
        }
        @media (max-width:900px) {
            #interview-chat .conversational-guidance { min-height:6rem; gap:1rem; padding:1.1rem 1.3rem; }
            #interview-chat .conversational-guidance__icon { flex-basis:3.4rem; width:3.4rem; height:3.4rem; }
        }
        #interview-chat .clinical-question-header .interview-message-header { min-height:0; margin-bottom:.65rem; gap:.6rem; }
        #interview-chat .clinical-question-header .interview-message-header::before { display:none; }
        #interview-chat .clinical-question-module {
            display:inline-flex;
            align-items:center;
            gap:.4rem;
            min-width:0;
            color:#6327A9;
            font-size:.88rem;
            font-weight:650;
            line-height:1.35;
        }
        #interview-chat .clinical-question-module-icon { width:1rem; height:1rem; flex:0 0 1rem; color:#6327A9; stroke:currentColor; }
        #interview-chat .clinical-question-header .interview-message-header > .clinical-question-module { flex:1 1 auto; }
        #interview-chat .clinical-question-header .chat-voice-button,
        #interview-chat .clinical-question-header .chat-help-button {
            min-height:44px;
            color:#6327A9;
            background:#fff;
            border:1px solid #D5C6EA;
            box-shadow:none;
        }
        #interview-chat .clinical-question-header .chat-voice-button:hover:not(:disabled),
        #interview-chat .clinical-question-header .chat-help-button:hover:not(:disabled) { color:#2B174C; background:#faf8fd; border-color:#bda9d8; }
        #interview-chat .clinical-question-header .chat-voice-button:focus-visible,
        #interview-chat .clinical-question-header .chat-help-button:focus-visible { outline:3px solid #c3a8e6; outline-offset:2px; }
        #interview-chat .m02-question-navigation .interview-previous-button { min-height:44px; margin:0 0 .55rem; color:#6327A9; }
        #interview-chat .m02-question-navigation .interview-previous-button:hover:not(:disabled) { color:#2B174C; background:#F2ECFC; border-color:#E2D6F5; }
        #interview-chat .m02-question-navigation .interview-previous-button:focus-visible { outline:3px solid #c3a8e6; outline-offset:2px; }
        #interview-chat .clinical-question-title {
            margin:.15rem 0 .55rem;
            color:#2B174C;
            font-size:clamp(1.5rem,2.5vw,1.95rem);
            font-weight:650;
            line-height:1.25;
            overflow-wrap:anywhere;
        }
        #interview-chat .clinical-question-description { margin:0; color:#625a70; font-size:.94rem; line-height:1.5; }
        #interview-chat .clinical-answer-area {
            margin-top:.7rem;
            padding:.9rem;
            border:1px solid #E2D6F5;
            border-radius:.9rem;
            background:#fff;
            box-shadow:none;
        }
        #interview-chat .m02-active-question .interview-composer-input {
            min-height:3.5rem;
            padding:.2rem .4rem .2rem .85rem;
            border:1px solid #d8d3e5;
            border-radius:.75rem;
            background:#fff;
            box-shadow:none;
            transition:border-color .15s ease,box-shadow .15s ease;
        }
        #interview-chat .m02-active-question .interview-composer-input:focus-within { border-color:#7041b5; box-shadow:0 0 0 3px rgba(112,65,181,.14); }
        #interview-chat .m02-active-question .interview-composer-input > input { min-height:3rem; padding-inline:.25rem; color:#292540; font-size:1rem; }
        #interview-chat .m02-active-question .interview-composer-input > input::placeholder { color:#788197; opacity:1; }
        #interview-chat .m02-active-question .chat-mic-button,
        #interview-chat .m02-active-question .chat-mic-unavailable,
        #interview-chat .patient-interview-question[data-question-index] .chat-mic-button,
        #interview-chat .patient-interview-question[data-question-index] .chat-mic-unavailable {
            position:relative;
            width:2.75rem;
            min-width:2.75rem;
            height:2.75rem;
            min-height:2.75rem;
            padding:0;
            border:1px solid #e1d8f3;
            border-radius:50%;
            color:#6327A9;
            background:#f3effb;
            font-size:1.05rem;
            line-height:1;
            box-shadow:none;
        }
        #interview-chat .m02-active-question .chat-mic-button:hover:not(:disabled), #interview-chat .patient-interview-question[data-question-index] .chat-mic-button:hover:not(:disabled) { color:#2B174C; background:#F2ECFC; border-color:#D5C6EA; }
        #interview-chat .m02-active-question .chat-mic-button:focus-visible:not(:disabled), #interview-chat .patient-interview-question[data-question-index] .chat-mic-button:focus-visible:not(:disabled) { outline:3px solid #c3a8e6; outline-offset:2px; }
        #interview-chat .m02-active-question .chat-mic-button.is-listening, #interview-chat .patient-interview-question[data-question-index] .chat-mic-button.is-listening { color:#fff !important; background:#6327A9 !important; border-color:#6327A9 !important; }
        #interview-chat .m02-active-question .chat-mic-button.is-listening::after, #interview-chat .patient-interview-question[data-question-index] .chat-mic-button.is-listening::after {
            content:"";
            position:absolute;
            top:.15rem;
            right:.1rem;
            width:.5rem;
            height:.5rem;
            border:1px solid #fff;
            border-radius:50%;
            background:#20876C;
            animation:m02-mic-pulse 1.5s ease-in-out infinite;
        }
        @keyframes m02-mic-pulse { 50% { box-shadow:0 0 0 .22rem rgba(53,180,140,.2); } }
        #interview-chat .m02-active-question .chat-mic-button:disabled,
        #interview-chat .m02-active-question .chat-mic-unavailable:disabled,
        #interview-chat .patient-interview-question[data-question-index] .chat-mic-button:disabled,
        #interview-chat .patient-interview-question[data-question-index] .chat-mic-unavailable:disabled { color:#777286; background:#f3f1f7; border-color:#e3e0e9; opacity:.8; }
        #interview-chat .m02-active-question .chat-send-button {
            min-width:7.5rem;
            min-height:3.5rem;
            border-radius:.75rem;
            color:#fff;
            background:#6327A9;
            border-color:#6327A9;
            font-size:1rem;
            font-weight:600;
            box-shadow:none;
        }
        #interview-chat .m02-active-question .chat-send-button:hover:not(:disabled) { background:#51208b; border-color:#51208b; }
        #interview-chat .m02-active-question .chat-send-button:focus-visible { outline:3px solid #c3a8e6; outline-offset:2px; }
        #interview-chat .m02-active-question .chat-send-button:disabled { color:#777286; background:#efedf3; border-color:#dfdce5; }
        #interview-chat .m02-active-question .interview-quick-actions { margin-top:.12rem; }
        #interview-chat .m02-active-question .m02-safe-no-button, #interview-chat .m03-composer .m02-safe-no-button { padding-inline:.85rem; border-radius:.65rem; color:#4c3380; background:#fff; border:1px solid #c9b9e2; font-weight:550; }
        #interview-chat .m02-active-question .m02-safe-no-button:hover:not(:disabled), #interview-chat .m03-composer .m02-safe-no-button:hover:not(:disabled) { color:#3e286c; background:#f6f2fc; border-color:#a98dcf; }
        #interview-chat .m02-active-question .interview-composer-help { margin:.6rem 0 0; color:#68738b; font-size:.84rem; }
        #interview-chat .m02-active-question .interview-answer-count { color:#7a7890; font-size:.78rem; }
        #interview-chat .clinical-voice-status {
            display:flex;
            align-items:flex-start;
            gap:.5rem;
            margin:.55rem 0 0;
            padding:.45rem .6rem;
            border:1px solid #e8e2f3;
            border-radius:.6rem;
            color:#554b69;
            background:#faf8fd;
            font-size:.84rem;
            line-height:1.4;
        }
        #interview-chat .clinical-voice-status:not([hidden])::before { content:""; flex:0 0 .55rem; width:.55rem; height:.55rem; margin-top:.32rem; border-radius:50%; background:#8b72b5; }
        #interview-chat .clinical-voice-status[data-stt-state="LISTENING"]::before,
        #interview-chat .clinical-voice-status[data-stt-state="RECOGNIZING"]::before { background:#20876C; }
        #interview-chat .clinical-voice-status[data-stt-state="ERROR"]::before,
        #interview-chat .clinical-voice-status[data-stt-state="UNAVAILABLE"]::before { background:#a46b38; }
        #interview-chat .clinical-voice-status[hidden] { display:none !important; }
        #interview-chat:has(.m02-active-question:not([hidden]):not(.chat-stage-hidden)) > .patient-interview-message[data-interview-zone="assistant"] {
            margin-bottom:-.25rem;
            padding:.7rem .9rem;
            border:1px solid #ede8f5;
            border-radius:.75rem;
            background:#faf9fc;
            box-shadow:none;
        }
        #interview-chat:has(.m02-active-question:not([hidden]):not(.chat-stage-hidden)) > .patient-interview-message[data-interview-zone="assistant"] .interview-message-header { min-height:0; margin-bottom:.15rem; }
        #interview-chat:has(.m02-active-question:not([hidden]):not(.chat-stage-hidden)) > .patient-interview-message[data-interview-zone="assistant"] .interview-message-header::before { display:none; }
        #interview-chat:has(.m02-active-question:not([hidden]):not(.chat-stage-hidden)) > .patient-interview-message[data-interview-zone="assistant"] .interview-question-text { margin:.1rem 0 0; font-size:.92rem; line-height:1.4; }
        @media (max-width:600px) {
            #interview-chat .conversational-guidance { align-items:flex-start; gap:.8rem; min-height:0; padding:1rem; border-radius:1rem; }
            #interview-chat .conversational-guidance__icon { flex-basis:3rem; width:3rem; height:3rem; }
            #interview-chat .conversational-guidance__icon svg { width:1.5rem; height:1.5rem; }
            #interview-chat .conversational-guidance__title { margin-bottom:.2rem; font-size:1.1rem; }
            #interview-chat .conversational-guidance__description { font-size:.94rem; }
            #interview-chat .clinical-question-header { padding:1rem; }
            #interview-chat .clinical-answer-area { padding:.7rem; }
            #interview-chat .m02-active-question .interview-composer { gap:.55rem; }
            #interview-chat .m02-active-question .chat-send-button { min-width:6.5rem; }
            #interview-chat .clinical-question-header .interview-message-header > .chat-voice-button,
            #interview-chat .clinical-question-header .interview-message-header > .chat-help-button { flex:0 1 auto; }
        }
        @media (max-width:375px) { #interview-chat .m02-active-question .chat-send-button { min-width:2.75rem; } }
        @media (prefers-reduced-motion:reduce) { #interview-chat .m02-active-question .chat-mic-button.is-listening::after, #interview-chat .patient-interview-question[data-question-index] .chat-mic-button.is-listening::after { animation:none; } }
        @media (prefers-reduced-motion:reduce) {
            #interview-chat .m02-active-question *, #interview-chat .m02-active-question *::before,
            #interview-chat .m02-active-question *::after { scroll-behavior:auto !important; transition:none !important; animation:none !important; }
        }
        .patient-interview-doctor-float {
            position:fixed;
            right:max(1.25rem,calc((100vw - 1660px) / 2 + 1.25rem));
            bottom:max(.35rem,env(safe-area-inset-bottom));
            z-index:1035;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:flex-end;
            width:max-content;
            max-width:min(35rem,calc(100vw - 1.2rem));
            pointer-events:none;
            isolation:isolate;
        }
        .patient-interview-doctor-shortcuts {
            order:1;
            position:relative;
            z-index:4;
            display:flex;
            flex-direction:column;
            width:100%;
            min-width:12.5rem;
            max-width:14.2rem;
            gap:.5rem;
            margin-bottom:.5rem;
            opacity:1;
            visibility:visible;
            pointer-events:auto;
            transform:none;
        }
        .patient-interview-doctor-shortcuts button {
            display:flex;
            align-items:center;
            gap:.65rem;
            width:100%;
            min-width:0;
            min-height:2.75rem;
            padding:.38rem .95rem .38rem .5rem;
            border-radius:999px;
            font:inherit;
            font-size:.85rem;
            font-weight:600;
            line-height:1.25;
            text-align:left;
            color:#2d1b54;
            background:linear-gradient(180deg,#ffffff 0%,#fbf9ff 100%);
            box-shadow:0 .2rem .65rem rgba(64,30,111,.08),0 .06rem .15rem rgba(64,30,111,.04);
            cursor:pointer;
            transition:transform 200ms cubic-bezier(.2,.8,.4,1),box-shadow 200ms ease,background-color 200ms ease,border-color 200ms ease,color 200ms ease;
        }
        .patient-interview-doctor-shortcuts button[data-doctor-repeat] {
            border:1.5px solid #ded4f0;
            color:#3b1875;
            background:#fcfaff;
        }
        .patient-interview-doctor-shortcuts button[data-doctor-repeat]:hover:not(:disabled) {
            transform:translateY(-2px);
            border-color:#bfa5e6;
            background:#f5edfc;
            color:#290d59;
            box-shadow:0 .45rem 1.15rem rgba(75,26,141,.16),0 .12rem .35rem rgba(75,26,141,.08);
        }
        .patient-interview-doctor-shortcuts button[data-doctor-help] {
            border:1.5px solid #d7ece7;
            color:#1f4d45;
            background:#f8fcfb;
        }
        .patient-interview-doctor-shortcuts button[data-doctor-help]:hover:not(:disabled) {
            transform:translateY(-2px);
            border-color:#9fd9cc;
            background:#edf8f5;
            color:#133c35;
            box-shadow:0 .45rem 1.15rem rgba(27,112,98,.16),0 .12rem .35rem rgba(27,112,98,.08);
        }
        .patient-interview-doctor-shortcuts button:active:not(:disabled) {
            transform:translateY(0);
            box-shadow:0 .12rem .4rem rgba(64,30,111,.1);
            transition-duration:80ms;
        }
        .patient-interview-doctor-shortcuts button:disabled {
            cursor:not-allowed;
            opacity:.85;
            transform:none !important;
            box-shadow:0 .1rem .35rem rgba(42,25,75,.06);
        }
        .patient-interview-doctor-shortcuts button:not(:disabled):focus-visible {
            outline:3px solid #6327a9;
            outline-offset:2px;
        }
        .patient-interview-doctor-shortcuts svg {
            box-sizing:border-box;
            flex:0 0 2rem;
            width:2rem;
            height:2rem;
            padding:.45rem;
            border-radius:50%;
            stroke:currentColor;
            transition:transform 200ms ease;
        }
        .patient-interview-doctor-shortcuts button:hover:not(:disabled) svg {
            transform:scale(1.06);
        }
        .patient-interview-doctor-shortcuts button[data-doctor-repeat] svg {
            color:#fff;
            background:linear-gradient(145deg,#7829df 0%,#5716bd 100%);
            box-shadow:0 .15rem .45rem rgba(87,22,189,.28);
        }
        .patient-interview-doctor-shortcuts button[data-doctor-help] svg {
            color:#fff;
            background:linear-gradient(145deg,#1fa388 0%,#177a66 100%);
            box-shadow:0 .15rem .45rem rgba(23,122,102,.28);
        }
        .patient-interview-doctor-bubble {
            order:2;
            position:relative;
            z-index:3;
            width:max-content;
            max-width:min(13rem,calc(100vw - 8rem));
            margin-bottom:.45rem;
            padding:.48rem .95rem;
            border:1px solid rgba(224,213,239,.95);
            border-radius:1.2rem;
            color:#38245b;
            background:#fff;
            box-shadow:0 .25rem .75rem rgba(42,25,75,.09),0 .08rem .2rem rgba(42,25,75,.05);
            font-size:.84rem;
            font-weight:600;
            line-height:1.35;
            pointer-events:none;
        }
        .patient-interview-doctor-bubble::after {
            content:"";
            position:absolute;
            left:50%;
            bottom:-.36rem;
            width:.65rem;
            height:.65rem;
            border-right:1px solid rgba(224,213,239,.95);
            border-bottom:1px solid rgba(224,213,239,.95);
            background:#fff;
            transform:translateX(-50%) rotate(45deg);
        }
        .patient-interview-doctor-assistant {
            order:3;
            position:relative;
            display:grid;
            width:max-content;
            color:#27154e;
            pointer-events:none;
        }
        .patient-interview-doctor-assistant[open] {
            width:min(35rem,calc(100vw - 2rem));
            display:grid;
            grid-template-columns:minmax(0,20rem) minmax(11rem,14rem);
            gap:.75rem;
            align-items:end;
            pointer-events:auto;
        }
        .patient-interview-doctor-assistant::before,
        .patient-interview-doctor-assistant::after {
            content:"";
            position:absolute;
            z-index:0;
            pointer-events:none;
        }
        .patient-interview-doctor-assistant::before {
            right:.2rem;
            bottom:-.35rem;
            width:13.5rem;
            height:20.8rem;
            border:1px solid rgba(208,231,229,.78);
            border-radius:43% 57% 48% 52% / 34% 42% 58% 66%;
            background:radial-gradient(ellipse at 28% 72%,rgba(225,246,238,.9) 0 19%,transparent 53%),radial-gradient(ellipse at 66% 28%,rgba(239,230,253,.96) 0 34%,rgba(250,248,255,.82) 68%,transparent 100%);
            box-shadow:inset .3rem .2rem .9rem rgba(255,255,255,.65),0 .4rem 1rem rgba(47,35,77,.08);
            transform:rotate(4deg);
        }
        .patient-interview-doctor-assistant::after {
            right:.5rem;
            bottom:.65rem;
            width:12.4rem;
            height:19rem;
            border:1px solid rgba(151,220,208,.33);
            border-right-color:transparent;
            border-bottom-color:transparent;
            border-radius:56% 44% 61% 39% / 44% 54% 46% 56%;
            background:linear-gradient(155deg,rgba(227,246,239,.2),rgba(237,230,252,.36));
            transform:rotate(-8deg);
        }
        .patient-interview-doctor-assistant[open]::before {
            right:.1rem;
            bottom:-.35rem;
            width:13.8rem;
            height:20.8rem;
        }
        .patient-interview-doctor-assistant[open]::after {
            right:.45rem;
            bottom:.7rem;
            width:12.7rem;
            height:19.2rem;
        }
        .patient-interview-doctor-assistant > summary {
            grid-column:2;
            grid-row:1;
            justify-self:center;
            align-self:end;
            z-index:2;
            display:grid;
            place-items:center;
            width:13.5rem;
            aspect-ratio:1024 / 1536;
            margin:0;
            padding:0;
            border:0;
            border-radius:0;
            background:transparent;
            box-shadow:none;
            cursor:pointer;
            list-style:none;
            pointer-events:auto;
            -webkit-tap-highlight-color:transparent;
        }
        .patient-interview-doctor-assistant > summary::-webkit-details-marker { display:none; }
        .patient-interview-doctor-assistant > summary::marker { content:""; }
        .patient-interview-doctor-assistant > summary img { display:block; width:100%; height:100%; object-fit:contain; filter:saturate(1.08) drop-shadow(0 .25rem .55rem rgba(47,27,78,.24)); transition:transform .18s ease,filter .18s ease; }
        .patient-interview-doctor-assistant:not([open]) > summary:hover img { transform:translateY(-.15rem); filter:saturate(1.12) drop-shadow(0 .35rem .65rem rgba(47,27,78,.28)); }
        .patient-interview-doctor-assistant > summary:focus-visible { outline:3px solid #5b16c8; outline-offset:3px; border-radius:.85rem; }
        .patient-interview-doctor-assistant[open] ~ .patient-interview-doctor-shortcuts,
        .patient-interview-doctor-assistant[open] ~ .patient-interview-doctor-bubble {
            display:none !important;
        }
        .patient-interview-doctor-assistant > summary::after { content:"×"; display:none; color:#4b247b; font-size:1.65rem; font-weight:500; line-height:1; }
        .patient-interview-doctor-assistant[open] + .patient-interview-doctor-bubble {
            right:1.5rem;
            bottom:19rem;
            max-width:min(14rem,calc(100vw - 15rem));
        }
        .patient-interview-doctor-panel {
            position:relative;
            z-index:1;
            grid-column:1;
            grid-row:1;
            align-self:end;
            width:100%;
            max-height:min(28rem,calc(100dvh - 2rem));
            overflow:auto;
            box-sizing:border-box;
            padding:1.1rem;
            border:1px solid #e2d7f1;
            border-radius:1.4rem;
            background:linear-gradient(155deg,#fff 0%,#fff 55%,#fbf8ff 100%);
            box-shadow:0 .55rem 1.55rem rgba(42,25,75,.16),0 .08rem .25rem rgba(42,25,75,.06);
        }
        .patient-interview-doctor-panel::before { content:none; }
        .patient-interview-doctor-panel h2 { margin:0; color:#2b174c; font-size:1.1rem; font-weight:700; line-height:1.3; }
        .patient-interview-doctor-panel > p { margin:.45rem 0 .95rem; color:#52627a; font-size:.9rem; line-height:1.45; }
        .patient-interview-doctor-actions { display:grid; gap:.6rem; }
        .patient-interview-doctor-actions button,
        .patient-interview-doctor-actions a {
            display:flex;
            align-items:center;
            gap:.8rem;
            width:100%;
            min-width:0;
            min-height:4rem;
            padding:.55rem .8rem;
            border:1px solid #e3daf3;
            border-radius:1rem;
            color:#32204f;
            background:linear-gradient(180deg,#f8f4ff 0%,#f2ecfc 100%);
            font:inherit;
            font-size:.96rem;
            font-weight:650;
            text-align:left;
            box-shadow:inset 0 1px 0 rgba(255,255,255,.95),0 .08rem .2rem rgba(59,39,91,.05);
        }
        .patient-interview-doctor-actions button:disabled { cursor:not-allowed; opacity:1; }
        .patient-interview-doctor-actions button:disabled { color:#3c3153; }
        .patient-interview-doctor-actions button:not(:disabled) { cursor:pointer; transition:background-color .16s ease,border-color .16s ease,box-shadow .16s ease; }
        .patient-interview-doctor-actions button:not(:disabled):hover { border-color:#cdb7ed; background:linear-gradient(180deg,#f3ecff 0%,#ece2fb 100%); }
        .patient-interview-doctor-actions button:not(:disabled):focus-visible { outline:3px solid #6327a9; outline-offset:3px; }
        .patient-interview-doctor-actions a {
            color:#32204f;
            cursor:pointer;
            text-decoration:none;
            transition:background-color .16s ease,border-color .16s ease,box-shadow .16s ease;
        }
        .patient-interview-doctor-actions a:hover { color:#2b174c; border-color:#cdb7ed; background:linear-gradient(180deg,#f3ecff 0%,#ece2fb 100%); }
        .patient-interview-doctor-actions a:focus-visible { outline:3px solid #6327a9; outline-offset:3px; }
        .patient-interview-doctor-actions svg {
            box-sizing:border-box;
            flex:0 0 3.05rem;
            width:3.05rem;
            height:3.05rem;
            padding:.78rem;
            border-radius:50%;
            color:#fff;
            background:linear-gradient(145deg,#7829df 0%,#5716bd 100%);
            stroke:currentColor;
        }
        .patient-interview-doctor-panel .patient-interview-doctor-note { margin:.7rem 0 0; color:#766d84; font-size:.75rem; line-height:1.4; }
        .patient-interview-doctor-voice-settings {
            margin-top:.75rem;
            padding-top:.7rem;
            border-top:1px solid #e8e2f4;
            background:transparent;
            border-radius:0;
            display:block;
        }
        .patient-interview-doctor-voice-summary {
            display:flex;
            align-items:center;
            gap:.55rem;
            cursor:pointer;
            font-size:.86rem;
            font-weight:600;
            color:#4b1a8d;
            padding:.3rem .1rem;
            list-style:none;
            user-select:none;
        }
        .patient-interview-doctor-voice-summary::-webkit-details-marker { display:none; }
        .patient-interview-doctor-voice-summary svg {
            width:1.15rem;
            height:1.15rem;
            flex-shrink:0;
            stroke:currentColor;
        }
        .patient-interview-doctor-voice-settings .voice-speed-control {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:.6rem;
            margin-top:.6rem;
            font-size:.82rem;
            color:#463b59;
            font-weight:500;
        }
        .patient-interview-doctor-voice-settings .voice-speed-control select {
            min-height:2.2rem;
            padding:.2rem .5rem;
            border:1px solid #cfc7e4;
            border-radius:.5rem;
            background:#fff;
            color:#2b174c;
            font-size:.82rem;
        }
        .patient-interview-doctor-assistant[open] > summary {
            position:relative;
            grid-column:2;
            grid-row:1;
            justify-self:center;
            align-self:end;
            width:13rem;
            height:auto;
            aspect-ratio:1024 / 1536;
            margin:0;
            border:0;
            border-radius:0;
            background:transparent;
            box-shadow:none;
        }
        .patient-interview-doctor-assistant[open] > summary::after {
            position:absolute;
            top:.55rem;
            left:-.45rem;
            right:auto;
            display:grid;
            place-items:center;
            width:2.35rem;
            height:2.35rem;
            border:1px solid rgba(255,255,255,.94);
            border-radius:50%;
            color:#fff;
            background:linear-gradient(145deg,#7829df 0%,#5716bd 100%);
            box-shadow:0 .22rem .65rem rgba(64,30,111,.25);
        }
        @media (max-width:1024px) {
            .patient-interview-doctor-assistant:not([open]) > summary { width: 11.2rem; }
            .patient-interview-doctor-assistant:not([open])::before { width: 11.2rem; height: 17.2rem; }
            .patient-interview-doctor-assistant:not([open])::after { width: 10.2rem; height: 15.8rem; }
        }
        @media (max-width:768px) {
            .patient-interview-doctor-assistant:not([open]) > summary { width: 9.5rem; }
            .patient-interview-doctor-assistant:not([open])::before { width: 9.6rem; height: 14.8rem; }
            .patient-interview-doctor-assistant:not([open])::after { width: 8.8rem; height: 13.5rem; }
        }
        @media (max-width:600px) {
            .patient-interview-doctor-float { inset:auto max(.5rem,env(safe-area-inset-right)) max(.35rem,env(safe-area-inset-bottom)) auto; width:auto; max-width:min(13.5rem,calc(100vw - .8rem)); align-items:flex-end; }
            .patient-interview-doctor-shortcuts {
                order:1;
                display:flex;
                flex-direction:column;
                width:auto;
                min-width:0;
                max-width:11.2rem;
                gap:.3rem;
                margin-bottom:.35rem;
                opacity:1;
                visibility:visible;
                pointer-events:auto;
            }
            .patient-interview-doctor-shortcuts button { min-height:2.15rem; padding:.2rem .65rem .2rem .35rem; font-size:.74rem; gap:.4rem; }
            .patient-interview-doctor-shortcuts svg { flex:0 0 1.6rem; width:1.6rem; height:1.6rem; padding:.32rem; }
            .patient-interview-doctor-bubble { order:2; max-width:min(9.5rem,calc(100vw - 6rem)); margin-bottom:.3rem; padding:.35rem .65rem; font-size:.72rem; }
            .patient-interview-doctor-assistant { grid-template-columns:minmax(0,1fr) minmax(5rem,5.6rem); }
            .patient-interview-doctor-assistant[open] { width:100%; grid-template-columns:minmax(0,1fr); gap:0; }
            .patient-interview-doctor-assistant:not([open])::before { right:.05rem; width:5.6rem; height:8.6rem; }
            .patient-interview-doctor-assistant:not([open])::after { right:-.05rem; bottom:.35rem; width:5rem; height:7.8rem; }
            .patient-interview-doctor-assistant:not([open]) > summary { width:5.4rem; }
            .patient-interview-doctor-assistant[open]::before,
            .patient-interview-doctor-assistant[open]::after { visibility:hidden; }
            .patient-interview-doctor-assistant[open] > summary {
                position:absolute;
                top:.45rem;
                right:.45rem;
                grid-column:1;
                grid-row:1;
                justify-self:end;
                align-self:start;
                width:2.3rem;
                height:2.3rem;
                aspect-ratio:auto;
                margin:0;
                border:1px solid rgba(255,255,255,.92);
                border-radius:50%;
                background:linear-gradient(145deg,#7829df 0%,#5716bd 100%);
                box-shadow:0 .22rem .65rem rgba(64,30,111,.25);
            }
            .patient-interview-doctor-assistant[open] > summary img { display:none; }
            .patient-interview-doctor-assistant[open] > summary::after { top:0; right:0; width:100%; height:100%; }
            .patient-interview-doctor-assistant[open] > .patient-interview-doctor-panel { grid-column:1; grid-row:1; }
            .patient-interview-doctor-assistant[open] + .patient-interview-doctor-bubble { display:none; }
            .patient-interview-doctor-panel { max-height:min(25rem,calc(100dvh - 1.3rem)); padding:3.2rem .8rem .85rem; }
            .patient-interview-doctor-actions { gap:.5rem; }
            .patient-interview-doctor-actions button,
            .patient-interview-doctor-actions a { min-height:4rem; gap:.65rem; padding:.45rem .6rem; font-size:.86rem; }
            .patient-interview-doctor-actions svg { flex-basis:2.7rem; width:2.7rem; height:2.7rem; padding:.68rem; }
            .patient-interview-conversation-card { padding-bottom:3.5rem; }
        }
        @media (prefers-reduced-motion:reduce) { .patient-interview-doctor-assistant > summary img, .patient-interview-doctor-shortcuts, .patient-interview-doctor-shortcuts button, .patient-interview-doctor-shortcuts svg { transition:none !important; transform:none !important; } }
    </style>
</head>
<body class="app-shell-body app-shell-patient patient-interview-page">
<div id="app">
    <?=$data['sidebar'];?>
    <div id="main" data-patient="<?=$escape($data['idPaciente']);?>" data-csrf="<?=$escape($data['csrf']);?>" data-local-dictation-enabled="<?=!empty($_SERVER['CLINICA_VOSK_ROOT']) ? '1' : '0';?>" data-m02-dictation-diagnostics="<?=($_SERVER['HTTP_HOST'] ?? '') === 'clinica.test' && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) && !empty($_SERVER['CLINICA_VOSK_ROOT']) ? '1' : '0';?>" data-dictation-ticket-url="<?=$escape(SERVIDOR . 'historia-clinica/entrevista/dictation-ticket');?>" data-dictation-worklet-url="<?=$escape(RUTA_JS . 'patient-interview-audio-worklet.js');?>" data-conversation-contract-version="<?=$escape($data['conversationContract']['version'] ?? '');?>">
        <nav class="navbar navbar-header navbar-expand navbar-light portal-topbar" aria-label="Barra de herramientas del portal del paciente">
            <div class="container-fluid patient-interview-topbar-inner"><button type="button" class="patient-interview-sidebar-toggle sidebar-toggler" data-interview-sidebar-toggle aria-controls="sidebar" aria-expanded="true" aria-label="Compactar menú principal"><i data-feather="menu" aria-hidden="true"></i><span class="visually-hidden">Menú</span></button><span class="patient-interview-breadcrumb">Mi historia clínica <span aria-hidden="true">›</span></span><span class="portal-badge"><span class="portal-status-dot" aria-hidden="true"></span>Entrevista conversacional</span></div>
        </nav>
        <main class="main-content container-fluid patient-interview-main">
            <div class="patient-interview-shell">
                <header class="page-title portal-page-title patient-interview-header mb-3" data-interview-zone="header">
                    <div class="patient-interview-header-copy">
                        <p class="dashboard-eyebrow">MI HISTORIA CLÍNICA</p>
                        <h1>Entrevista Conversacional</h1>
                    </div>
                    <div class="patient-interview-header-context" aria-label="Contexto actual de la entrevista">
                        <span class="interview-module-badge" data-interview-current-module>Entrevista</span>
                        <span class="interview-state-badge" data-interview-state-label data-state="INITIALIZING" aria-live="polite" aria-atomic="true">Preparando</span>
                    </div>
                </header>
                <div class="patient-interview-conversation-layout" data-ux-layout="chat-first">
                <section class="card border-0 rounded-4 shadow-sm mt-0 patient-interview-conversation-card" data-interview-zone="conversation" aria-labelledby="interview-chat-title">
                    <div class="card-body patient-interview-chat" id="interview-chat" aria-describedby="interview-status" aria-busy="false">
                        <p class="interview-zone-label">Conversación y respuesta</p>
                        <h2 id="interview-chat-title" class="h4">Conversación clínica</h2>
                        <div class="patient-interview-progress" data-interview-zone="progress">
                            <div class="interview-progress-layout">
                                <strong id="interview-progress-percentage"></strong>
                                <div id="interview-progress" class="patient-interview-muted">Preparando la entrevista</div>
                            </div>
                            <div class="progress mt-1" role="progressbar" aria-label="Avance de la entrevista" aria-valuemin="0" aria-valuemax="1" aria-valuenow="0" aria-valuetext="Preparando la entrevista"><div id="interview-progress-bar" class="progress-bar" style="width:0%"></div></div>
                        </div>
                        <aside class="conversational-guidance" data-conversational-guidance data-interview-zone="assistant" aria-label="Orientación para responder">
                            <span class="conversational-guidance__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H7l-3 2v-5.1a7.5 7.5 0 1 1 16-4.4Z"/><path d="M8 10h8M8 13.5h5"/></svg></span>
                            <div class="conversational-guidance__content"><h3 class="conversational-guidance__title" data-guidance-title>Cómo responder</h3><p class="conversational-guidance__description" data-guidance-description>Una pregunta a la vez. Escribe tu respuesta y revísala antes de enviarla.</p></div>
                        </aside>
                        <?php if (!empty($data['m01']['fields'])): ?>
                        <article class="patient-interview-module-context interview-module-card chat-stage-hidden" data-module="M01">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M01</strong></div><p class="interview-question-text">Estos son tus datos de identificación ya registrados. Se muestran como lectura informativa; esta entrega no modifica M01.</p></div>
                            <div class="patient-interview-context-grid">
                                <?php foreach ($data['m01']['fields'] as $field): ?>
                                <div data-m01-field="<?=$escape($field['key']);?>">
                                    <dt><?=$escape($field['label']);?></dt><dd><?=$escape($field['value']);?></dd>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="patient-interview-muted mb-0">M01 consultado. La captura conversacional continúa en M03.</p>
                        </article>
                        <?php endif; ?>
                        <?php
                            $m02Records = $data['m02']['records'] ?? [];
                            $m02Contract = $data['conversationContract']['modules']['M02'] ?? [];
                            $m02Questions = is_array($m02Contract['questions'] ?? null) ? $m02Contract['questions'] : [];
                            $m02PendingIds = is_array($m02Contract['completion']['pending_question_ids'] ?? null)
                                ? array_values($m02Contract['completion']['pending_question_ids'])
                                : [];
                            $m02QuestionIndex = $data['conversationContract']['question_index'] ?? [];
                            $m02ActiveQuestion = null;
                            foreach ($m02PendingIds as $m02PendingId) {
                                $candidate = $m02QuestionIndex[$m02PendingId] ?? null;
                                if (is_array($candidate) && ($candidate['module_id'] ?? '') === 'M02') {
                                    $m02ActiveQuestion = $candidate;
                                    break;
                                }
                            }
                            $m02ActiveDisease = is_array($m02ActiveQuestion) ? (string)($m02ActiveQuestion['question_id'] ?? '') : '';
                            $m02ActiveRecord = null;
                            foreach ($m02Records as $m02Record) {
                                if ($m02ActiveDisease !== '' && (string)($m02Record['disease'] ?? '') === $m02ActiveDisease) {
                                    $m02ActiveRecord = $m02Record;
                                    break;
                                }
                            }
                            $m02ResolverState = (string)($m02Contract['conversation_state']['state'] ?? 'INCONSISTENT');
                            $m02FinalizeVoiceText = trim((string)($m02Contract['conversation_state']['voice_text'] ?? ''));
                            if ($m02FinalizeVoiceText === '') {
                                $m02FinalizeVoiceText = 'Tus antecedentes familiares están registrados. ¿Deseas finalizar esta sección y continuar?';
                            }
                            $m02Finalized = !empty($data['m02']['finalized']) || !empty($m02Contract['completion']['complete']);
                            $m02QuestionSources = [];
                            foreach ($m02Questions as $sourceQuestion) {
                                $sourceDisease = trim((string)($sourceQuestion['question_id'] ?? ''));
                                $sourceStableId = trim((string)($sourceQuestion['stable_id'] ?? ''));
                                $sourceRecord = null;
                                foreach ($m02Records as $candidateRecord) {
                                    if ($sourceDisease !== '' && (string)($candidateRecord['disease'] ?? '') === $sourceDisease) { $sourceRecord = $candidateRecord; break; }
                                }
                                if ($sourceRecord === null || $sourceStableId === '' || ($sourceQuestion['module_id'] ?? '') !== 'M02') continue;
                                $sourceLegacyText = (string)($sourceRecord['legacy_relative_text'] ?? $sourceRecord['relative_description'] ?? '');
                                $sourceRelatives = is_array($sourceRecord['relatives'] ?? null) ? array_values(array_filter($sourceRecord['relatives'], 'is_string')) : [];
                                $sourceDetail = (string)($sourceRecord['detail'] ?? '');
                                $sourceStructured = $sourceRelatives !== [] || !empty($sourceRecord['invalid_relatives']);
                                $sourceAnswer = $sourceLegacyText !== '' ? $sourceLegacyText
                                    : ($sourceDetail === 'No' ? 'No'
                                        : ($sourceRelatives !== [] ? implode(', ', $sourceRelatives) : trim((string)($sourceRecord['diabetes_type'] ?? ''))));
                                $m02QuestionSources[] = [
                                    'stable_id' => $sourceStableId,
                                    'disease' => $sourceDisease,
                                    'version' => (string)($sourceRecord['version'] ?? ''),
                                    'documented' => ($sourceRecord['status'] ?? '') === 'documented' || ($sourceQuestion['response']['status'] ?? '') === 'documented',
                                    'editable' => !empty($data['m02']['write_enabled']) && !$m02Finalized
                                        && (!$sourceStructured || ($sourceDetail === 'Si' && $sourceLegacyText !== '')),
                                    'answer' => $sourceAnswer,
                                    'question' => (string)($sourceQuestion['visible_text'] ?? ''),
                                    'instruction' => (string)($sourceQuestion['instruction'] ?? 'Responde la pregunta.'),
                                    'detail' => $sourceDetail,
                                ];
                            }
                            $m02ActiveSource = null;
                            foreach ($m02QuestionSources as $source) if ($source['stable_id'] === (string)($m02ActiveQuestion['stable_id'] ?? '')) { $m02ActiveSource = $source; break; }
                            $m02ActiveEditable = is_array($m02ActiveSource) && $m02ActiveSource['editable'];
                            $m02ActiveInputValue = is_array($m02ActiveSource) ? $m02ActiveSource['answer'] : '';
                        ?>
                        <?php if (!empty($m02Records)): ?>
                        <article class="patient-interview-question patient-interview-active-question interview-module-card m02-active-question chat-stage-hidden" data-module="M02" data-m02-pending="<?=count($m02PendingIds);?>"<?php if (is_array($m02ActiveQuestion)): ?> data-stable-id="<?=$escape($m02ActiveQuestion['stable_id'] ?? '');?>" data-m02-disease="<?=$escape($m02ActiveDisease);?>" data-m02-version="<?=$escape($m02ActiveRecord['version'] ?? '');?>"<?php endif; ?> data-m02-write-enabled="<?=!empty($data['m02']['write_enabled']) && !$m02Finalized ? '1' : '0';?>" data-m02-finalized="<?=$m02Finalized ? '1' : '0';?>" data-m02-consultation="0">
                            <nav class="m02-question-navigation" aria-label="Navegación de preguntas M02">
                                <button type="button" class="interview-previous-button" data-m02-previous hidden disabled aria-label="Volver a la pregunta anterior"><span aria-hidden="true">←</span> Pregunta anterior</button>
                                <button type="button" class="interview-previous-button" data-m02-next hidden disabled aria-label="Ir a la siguiente pregunta contestada">Siguiente pregunta <span aria-hidden="true">→</span></button>
                                <button type="button" class="interview-previous-button" data-m02-return-current hidden disabled aria-label="Volver a la pregunta actual"><span aria-hidden="true">→</span> <?=$m02Finalized ? 'Volver a respuestas previas' : 'Volver a pregunta actual';?></button>
                            </nav>
                            <div class="patient-interview-message assistant clinical-question-header" data-m02-question-message<?=is_array($m02ActiveQuestion) || $m02ResolverState === 'READY_TO_FINALIZE' ? '' : ' hidden';?>>
                                <div class="interview-message-header"><strong class="clinical-question-module"><svg class="clinical-question-module-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 20v-1.5A4.5 4.5 0 0 1 8 14h2a4.5 4.5 0 0 1 4.5 4.5V20"/><path d="M16 5.5a3 3 0 0 1 0 5.8M17 14h.5a3.5 3.5 0 0 1 3.5 3.5V19"/></svg>M02 · Antecedentes heredofamiliares</strong></div>
                                <h3 id="m02-question-title" class="interview-question-text clinical-question-title" data-m02-question-text<?=$m02ResolverState === 'READY_TO_FINALIZE' ? ' data-m02-action-id="M02:action:finalize" data-m02-finalize-help-text="' . $escape($m02FinalizeHelpText) . '"' : '';?>><?=$escape($m02ResolverState === 'READY_TO_FINALIZE' ? $m02FinalizeVoiceText : ($m02ActiveQuestion['visible_text'] ?? ''));?></h3>
                                <p class="patient-interview-muted mb-0 clinical-question-description" data-m02-question-instruction<?=is_array($m02ActiveQuestion) ? '' : ' hidden';?>><?=$escape($m02ActiveQuestion['instruction'] ?? 'Responde la pregunta.');?></p>
                            </div>
                            <?php if (is_array($m02ActiveQuestion) || $m02Finalized): ?>
                            <div class="interview-composer-shell clinical-answer-area" data-m02-composer-shell data-interview-zone="response"<?=is_array($m02ActiveQuestion) ? '' : ' hidden';?>>
                                <div class="patient-interview-answer interview-composer m02-composer">
                                    <div class="interview-composer-input">
                                        <label class="visually-hidden" for="m02-response-input">Respuesta sobre <span data-m02-question-label><?=$escape($m02ActiveDisease);?></span></label>
                                        <input id="m02-response-input" class="form-control" data-m02-input type="text" value="<?=$escape($m02ActiveInputValue);?>" placeholder="Ej. mi madre tiene esta enfermedad" autocomplete="off" maxlength="2000"<?=$m02ActiveEditable ? '' : ' readonly';?>>
                                    </div>
                                    <button type="button" class="btn btn-primary chat-send-button" data-m02-interpret aria-label="Enviar respuesta" title="Enviar respuesta"<?=$m02ActiveEditable ? '' : ' disabled';?>>➤ <span class="chat-send-label">Enviar</span></button>
                                    <?php if (!empty($data['m02']['write_enabled']) && !$m02Finalized): ?><div class="interview-quick-actions" aria-label="Acción negativa M02"><button type="button" class="btn btn-primary btn-sm m02-safe-no-button" data-m02-safe-no>No, guardar y continuar</button></div><?php endif; ?>
                                </div>
                                <p class="m02-dictation-status clinical-voice-status" data-m02-dictation-status role="status" aria-live="polite" aria-atomic="true" hidden></p>
                                <div class="interview-composer-footer"><p class="interview-composer-help"><span aria-hidden="true">ⓘ</span> Escribe o dicta tu respuesta y pulsa Enviar para validarla y guardarla.</p><span class="interview-answer-count" data-answer-count>0/2000</span></div>
                            </div>
                            <small class="d-block patient-interview-muted mt-1" data-m02-result role="status" aria-live="polite" hidden></small>
                            <?php endif; ?>
                            <?php if ($m02ResolverState === 'READY_TO_FINALIZE'): ?>
                            <div class="m02-finalization-actions">
                                <button type="button" class="btn btn-primary" data-m02-resolution-action aria-label="Finalizar antecedentes familiares y continuar">Finalizar antecedentes familiares y continuar</button>
                            </div>
                            <?php elseif ($m02ResolverState === 'AWAITING_PROFESSIONAL_REVIEW'): ?>
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · Antecedentes heredofamiliares</strong></div><p class="interview-question-text">Esta sección requiere revisión profesional antes de continuar.</p></div>
                            <?php elseif ($m02ResolverState !== 'ANSWERABLE'): ?>
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · Antecedentes heredofamiliares</strong></div><p class="interview-question-text">No se pudo resolver una única acción conversacional para esta sección.</p></div>
                            <?php endif; ?>
                            <?php foreach ($m02QuestionSources as $source): ?>
                            <template data-m02-question-source data-m02-disease="<?=$escape($source['disease']);?>" data-stable-id="<?=$escape($source['stable_id']);?>" data-m02-version="<?=$escape($source['version']);?>" data-m02-documented="<?=$source['documented'] ? '1' : '0';?>" data-m02-editable="<?=$source['editable'] ? '1' : '0';?>" data-m02-answer="<?=$escape($source['answer']);?>" data-m02-question-text="<?=$escape($source['question']);?>" data-m02-instruction="<?=$escape($source['instruction']);?>" data-m02-detail="<?=$escape($source['detail']);?>"></template>
                            <?php endforeach; ?>
                        </article>
                        <?php endif; ?>
                        <?php foreach ($questionList as $index => $question): ?>
                        <?php $m03Eligible = ($question['eligible'] ?? true) === true; $m03ThemeLabel = trim((string)($question['tema'] ?? '')); $m03Parent = (int)($question['parentQuestionId'] ?? 0); $m03DirectNumeric = ($question['tipo'] ?? '') === 'input' && preg_match('/\b(?:cu[aá]ntos|cu[aá]nta|cu[aá]nto|qu[eé] edad|hace cu[aá]nto|durante cu[aá]nto)\b/iu', (string)($question['pregunta'] ?? '')) === 1; $m03ContextualAction = is_array($question['contextualAction'] ?? null) ? $question['contextualAction'] : null; ?>
                        <?php $m03SemanticState = strtoupper(trim((string)($question['semanticState'] ?? $question['estadoRespuesta'] ?? ''))); $m03HasSemanticAnswer = in_array($m03SemanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true); $hasSavedAnswer = trim((string)($question['respuesta'] ?? '')) !== '' || $m03HasSemanticAnswer; $m03SemanticLabel = $m03SemanticState === 'UNKNOWN' ? 'Dato desconocido declarado por el paciente' : ($m03SemanticState === 'NOT_REMEMBERED' ? 'Dato no recordado declarado por el paciente' : ''); ?>
                        <article class="patient-interview-question patient-interview-active-question interview-module-card" data-question-index="<?=$index;?>" data-question-id="<?=$escape($question['idPregunta']);?>" data-theme-id="<?=$escape($question['idTema']);?>" data-theme-name="<?=$escape($question['tema'] ?? 'M03');?>" data-parent-id="<?=$escape($m03Parent);?>" data-eligible="<?=$m03Eligible ? '1' : '0';?>" data-historical-editable="<?=!empty($question['historicalEditable']) ? '1' : '0';?>" data-type="<?=$escape($question['tipo']);?>" data-response-id="<?=$escape($question['idRespuesta'] ?? '');?>" data-saved-value="<?=$escape($question['respuesta'] ?? '');?>" data-semantic-state="<?=$escape($m03SemanticState);?>" data-m03-version="<?=$escape($data['m03']['version'] ?? '');?>" data-m03-write-enabled="<?=!empty($data['m03']['write_enabled']) ? '1' : '0';?>" data-m03-direct-capable="<?=$m03DirectNumeric ? '1' : '0';?>" hidden>
                            <button type="button" class="interview-previous-button" data-m03-previous hidden disabled aria-label="Ir a la pregunta anterior"><span aria-hidden="true">←</span> Pregunta anterior</button>
                            <button type="button" class="interview-previous-button" data-m03-next hidden disabled aria-label="Ir a la pregunta siguiente"><span aria-hidden="true">→</span> Pregunta siguiente</button>
                            <div class="patient-interview-message assistant clinical-question-header">
                                <div class="interview-message-header"><strong class="clinical-question-module"><svg class="clinical-question-module-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 20v-1.5A4.5 4.5 0 0 1 8 14h2a4.5 4.5 0 0 1 4.5 4.5V20"/><path d="M16 5.5a3 3 0 0 1 0 5.8M17 14h.5a3.5 3.5 0 0 1 3.5 3.5V19"/></svg>M03<?=$m03ThemeLabel !== '' ? ' · ' . $escape($m03ThemeLabel) : '';?></strong></div>
                                <h3 class="interview-question-text clinical-question-title"><?=$escape($question['pregunta']);?></h3>
                            </div>
                            <div class="interview-composer-shell clinical-answer-area" data-interview-zone="response"<?=$hasSavedAnswer && $index < $firstPending ? ' hidden' : ''; ?>>
                                <div class="patient-interview-answer interview-composer m03-composer">
                                    <div class="interview-composer-input m03-input-wrap">
                                        <label class="visually-hidden" for="interview-answer-<?=$index;?>">Respuesta</label>
                                        <input id="interview-answer-<?=$index;?>" class="form-control interview-text" type="text" value="<?=$escape($question['respuesta'] ?? '');?>" placeholder="Escribe tu respuesta…" autocomplete="off" maxlength="2000">
                                    </div>
                                    <button type="button" class="btn btn-primary chat-send-button interview-submit" aria-label="Interpretar respuesta" title="Interpretar respuesta">➤ <span class="chat-send-label">Enviar</span></button>
                                    <?php if ($m03ContextualAction !== null && !empty($data['m03']['write_enabled'])): ?>
                                        <div class="m03-quick-actions" aria-label="Respuestas rápidas"<?=$m03Eligible ? '' : ' hidden';?>>
                                            <button type="button" class="btn btn-primary m02-safe-no-button" data-m03-contextual-action data-action-key="<?=$escape($m03ContextualAction['key'] ?? '');?>" data-confirmation-label="<?=$escape($m03ContextualAction['confirmation_label'] ?? 'Respuesta confirmada');?>"<?=$m03Eligible ? '' : ' hidden disabled';?>><?=$escape($m03ContextualAction['label'] ?? '');?></button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="interview-composer-footer"><p class="interview-composer-help"><span aria-hidden="true">ⓘ</span> Escribe o dicta tu respuesta y pulsa Enviar para validarla y guardarla.</p><span class="interview-answer-count" data-answer-count>0/2000</span></div>
                                <small id="m03-dictation-status-<?=$index;?>" class="m03-dictation-note" data-m03-dictation-status role="status" aria-live="polite" aria-atomic="true" hidden></small>
                            </div>
                            <div class="patient-interview-message patient" data-confirmed-message<?=$hasSavedAnswer ? '' : ' hidden';?>><?=$hasSavedAnswer ? $escape('Respuesta confirmada: ' . ($m03SemanticLabel !== '' ? $m03SemanticLabel : $question['respuesta'])) : '';?></div>
                        </article>
                        <?php endforeach; ?>
                        <?php if (!empty($data['m04'])):
                            $m04Questions = $data['conversationContract']['modules']['M04']['questions'] ?? [];
                            $m04QuestionIndex = $data['conversationContract']['question_index'] ?? [];
                            $m04Records = array_values(array_filter($data['m04']['records'] ?? [], static fn(array $record): bool => trim((string)($record['cirugia'] ?? '')) !== ''));
                            $m04HasRecords = !empty($m04Records);
                            $m04NegativeDeclaration = is_array($data['m04']['negative_declaration'] ?? null) ? $data['m04']['negative_declaration'] : null;
                            $m04NegativeConfirmed = ($m04NegativeDeclaration['estado'] ?? null) === 'NEGATIVE_DECLARED' && !$m04HasRecords;
                            $m04Finalized = !empty($data['m04']['finalized']);
                            $m04StableId = ($m04HasRecords && !$m04Finalized && is_array($m04QuestionIndex['M04:record:new:add-another'] ?? null))
                                ? 'M04:record:new:add-another'
                                : 'M04:record:new';
                            $m04Question = $m04QuestionIndex[$m04StableId] ?? ($m04Questions[0] ?? []);
                            $m04ValidDate = static function ($value): ?string {
                                $value = trim((string)$value);
                                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return null;
                                [$year, $month, $day] = array_map('intval', explode('-', $value));
                                if ($year < 1 || !checkdate($month, $day, $year)) return null;
                                return sprintf('%04d-%02d-%02d', $year, $month, $day);
                            };
                        ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M04" data-m04-record="<?=$m04HasRecords ? (int)($m04Records[0]['id'] ?? 0) : 0;?>" data-m04-stable-id="<?=$escape($m04StableId);?>" data-m04-version="<?=$escape($data['m04']['version'] ?? '');?>" data-m04-write-enabled="<?=!empty($data['m04']['write_enabled']) ? '1' : '0';?>" data-m04-negative-confirmed="<?=$m04NegativeConfirmed ? '1' : '0';?>" aria-labelledby="m04-question-title">
                            <div class="patient-interview-message assistant clinical-question-header"><div class="interview-message-header"><strong class="clinical-question-module"><svg class="clinical-question-module-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 20v-1.5A4.5 4.5 0 0 1 8 14h2a4.5 4.5 0 0 1 4.5 4.5V20"/><path d="M16 5.5a3 3 0 0 1 0 5.8M17 14h.5a3.5 3.5 0 0 1 3.5 3.5V19"/></svg>M04 · Antecedentes quirúrgicos</strong></div><h3 id="m04-question-title" class="interview-question-text clinical-question-title" data-m04-question-title><?=$escape($m04Question['visible_text'] ?? '¿Le han realizado alguna cirugía?');?></h3><p id="m04-question-instruction" class="patient-interview-muted mb-0 clinical-question-description" data-m04-question-instruction><?=$escape($m04Question['instruction'] ?? 'Indique si le han realizado alguna cirugía. Si nunca le han realizado una, seleccione «No, guardar y continuar».');?></p></div>
                            <section data-m04-record-list<?=$m04HasRecords ? '' : ' hidden';?> aria-label="Cirugías registradas">
                            <h4 class="patient-interview-context-heading" data-m04-record-count>Cirugías registradas — <?=count($m04Records);?> <?=count($m04Records) === 1 ? 'registro' : 'registros';?></h4>
                            <div class="patient-interview-context-grid" data-m04-records>
                                <?php foreach ($m04Records as $record): ?>
                                <?php $m04Date = $m04ValidDate($record['fecha'] ?? null); $m04Observations = trim((string)($record['observaciones'] ?? '')); ?>
                                <div class="patient-interview-context-item"><strong>Cirugía</strong><dl class="mb-0"><dt>Nombre</dt><dd><?= $escape($record['cirugia'] ?? ''); ?></dd><dt>Observaciones</dt><dd><?=$m04Observations !== '' ? $escape($m04Observations) : 'Sin observaciones adicionales';?></dd></dl><?php if ($m04Date !== null): ?><small class="patient-interview-muted">Fecha histórica registrada: <?=$escape($m04Date);?></small><?php endif; ?></div>
                                <?php endforeach; ?>
                            </div>
                            </section>
                            <?php if ($m04NegativeConfirmed): ?><p class="patient-interview-muted" data-m04-negative-note>Declaración confirmada: no se registraron cirugías.</p>
                            <?php elseif (!$m04HasRecords): ?><p class="patient-interview-muted" data-m04-pending-note>Datos quirúrgicos pendientes de respuesta.</p><?php endif; ?>
                            <?php if (!$m04Finalized && !empty($data['m04']['write_enabled'])): ?><div class="patient-interview-answer interview-composer">
                                <div class="m04-stage-actions" data-m04-stage-actions="initial" role="group" aria-label="Respuesta sobre cirugías"<?=$m04StableId === 'M04:record:new' ? '' : ' hidden';?>><button type="button" class="btn btn-primary" data-m04-start-surgery>Sí, registrar cirugía</button><button type="button" class="btn btn-outline-primary" data-m04-negative data-m04-quick-response>No, guardar y continuar</button></div>
                                <div class="m04-input-stage" data-m04-input-stage hidden><div class="interview-composer-input"><label class="visually-hidden" for="m04-input">Respuesta sobre antecedentes quirúrgicos</label><input id="m04-input" class="form-control" data-m04-input type="text" maxlength="2000" autocomplete="off" placeholder=""></div><button type="button" class="btn btn-primary chat-send-button" data-m04-submit aria-label="Continuar respuesta M04" title="Continuar respuesta M04">➤ <span class="chat-send-label">Continuar</span></button></div>
                                <div class="m04-stage-actions" data-m04-stage-actions="observations" role="group" aria-label="Opciones de observaciones" hidden><button type="button" class="btn btn-outline-primary" data-m04-unknown>No recuerdo</button></div>
                                <div class="m04-stage-actions" data-m04-stage-actions="add-another" role="group" aria-label="Agregar otra cirugía"<?=$m04StableId === 'M04:record:new:add-another' ? '' : ' hidden';?>><button type="button" class="btn btn-primary" data-m04-add-another>Sí, agregar otra</button><button type="button" class="btn btn-outline-primary" data-m04-finish data-m04-quick-response data-intent="FINISH_ADDITIONAL_SURGERIES">No, terminar</button></div>
                            </div><?php elseif ($m04Finalized): ?><p class="patient-interview-muted" role="status">M04 finalizado. La edición ordinaria está bloqueada.</p><?php endif; ?>
                            <small class="d-block patient-interview-muted mt-1" data-m04-result role="status" aria-live="polite" hidden></small>
                            <p class="patient-interview-muted mb-0">La respuesta válida se guarda después de la validación del servidor.</p>
                        </article>
                        <?php endif; ?>
                        <?php if (!empty($data['m05'])): $m05Pending = $data['m05']['pending_question'] ?? null; ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M05" data-m05-version="<?=$escape($data['m05']['version'] ?? '');?>" data-m05-write-enabled="<?=!empty($data['m05']['write_enabled']) ? '1' : '0';?>" data-m05-pending="<?=$m05Pending ? '1' : '0';?>" data-m05-pending-question="<?=$m05Pending ? (int)$m05Pending['question_id'] : 0;?>" aria-labelledby="m05-question-title">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · Antecedentes personales patológicos</strong></div><?php if ($m05Pending): ?><p class="patient-interview-question-topic">Tema: <span data-m05-theme-label><?=$escape($m05Pending['tema'] ?? 'Pregunta M05');?></span></p><h3 id="m05-question-title" class="interview-question-text" data-m05-question-label><?=$escape($m05Pending['pregunta'] ?? '');?></h3><?php else: ?><p class="interview-question-text">Continuaremos con antecedentes personales patológicos. Pulsa Enviar para validar y guardar la respuesta.</p><?php endif; ?></div>
                            <?php if ($m05Pending): ?>
                            <div class="patient-interview-answer interview-composer">
                                <div class="interview-composer-input"><label class="visually-hidden" for="m05-input">Respuesta M05</label>
                                <input id="m05-input" class="form-control" data-m05-input type="text" placeholder="Escribe tu respuesta…"></div>
                                <button type="button" class="btn btn-primary chat-send-button" data-m05-interpret aria-label="Enviar respuesta M05" title="Enviar respuesta M05">➤ <span class="chat-send-label">Enviar</span></button>
                            </div>
                            <div class="interview-quick-actions m05-quick-actions" data-m05-quick-actions role="group" aria-label="Respuestas rápidas M05"<?=($m05Pending && ($m05Pending['tipo'] ?? '') === 'select') ? '' : ' hidden';?>><button type="button" class="btn btn-outline-primary" data-m05-quick data-value="Si">Sí</button><button type="button" class="btn btn-primary" data-m05-quick data-value="No">No, siguiente pregunta</button></div>
                            <small class="d-block patient-interview-muted mt-1" data-m05-result role="status" aria-live="polite" hidden></small>
                            <?php else: ?><p class="patient-interview-muted">No hay preguntas M05 pendientes de respuesta.</p><?php endif; ?>
                        </article>
                        <?php endif; ?>
                        <?php if (!empty($data['m05']['diseases'])): $m05Disease = null; foreach ($data['m05']['diseases'] as $disease) { if (trim((string)($disease['respuesta'] ?? '')) === '') { $m05Disease = $disease; break; } } ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M05-V2" data-m05-version="<?=$escape($data['m05']['version'] ?? '');?>" data-m05-v2-pending="<?=$m05Disease ? '1' : '0';?>" data-m05-v2-disease="<?=$m05Disease ? (int)$m05Disease['disease_id'] : 0;?>" aria-labelledby="m05-v2-question-title">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · Antecedentes personales patológicos</strong></div><?php if ($m05Disease): ?><p class="patient-interview-question-topic">Enfermedad: <span data-m05-v2-disease-label><?=$escape($m05Disease['enfermedad']);?></span></p><h3 id="m05-v2-question-title" class="interview-question-text" data-m05-v2-question-label>¿Has sido diagnosticado con esta enfermedad?</h3><?php else: ?><p class="interview-question-text">Revisaremos el catálogo de enfermedades y sus datos dependientes. Pulsa Enviar para validar y guardar la respuesta.</p><?php endif; ?></div>
                            <?php if ($m05Disease): ?>
                            <div class="patient-interview-answer interview-composer"><div class="interview-composer-input"><label class="visually-hidden" for="m05-v2-input">Respuesta M05 V2</label><input id="m05-v2-input" class="form-control" data-m05-v2-input type="text" placeholder="Ej. sí, Tipo 2, 2020"></div><button type="button" class="btn btn-primary chat-send-button" data-m05-v2-interpret aria-label="Enviar respuesta M05 V2" title="Enviar respuesta M05 V2">➤ <span class="chat-send-label">Enviar</span></button></div>
                            <small class="d-block patient-interview-muted mt-1" data-m05-v2-result role="status" aria-live="polite" hidden></small>
                            <?php else: ?><p class="patient-interview-muted">No hay enfermedades M05 V2 pendientes.</p><?php endif; ?>
                        </article>
                        <?php endif; ?>
<?php if (!empty($data['m06'])): $m06Pending = $data['m06']['pending_record'] ?? null; $m06Records = is_array($data['m06']['records'] ?? null) ? $data['m06']['records'] : []; $m06OmissionRecord = null; $m06OmissionsByRecord = []; foreach ($m06Records as $m06Candidate) { $m06CandidateId = (int)($m06Candidate['id'] ?? 0); $m06CandidateOmissions = is_array($m06Candidate['omissions'] ?? null) ? $m06Candidate['omissions'] : []; if ($m06OmissionRecord === null && $m06CandidateOmissions) $m06OmissionRecord = $m06Candidate; if ($m06CandidateId > 0 && $m06CandidateOmissions) $m06OmissionsByRecord[(string)$m06CandidateId] = $m06CandidateOmissions; } $m06Finalized = !empty($data['m06']['finalized']); $m06State = $m06Pending ? 'capturing' : ($m06Records ? 'after-save' : 'initial'); $m06FieldPrompts = ['medicamento' => '¿Qué medicamento tomas?', 'descripcion' => '¿Para qué lo utilizas?', 'tiempo_uso' => '¿Desde cuándo lo utilizas?', 'dosis' => '¿Qué dosis utilizas?', 'medico' => '¿Qué médico te lo indicó?']; $m06PendingField = ''; $m06Context = []; $m06ContextSource = is_array($m06Pending) ? $m06Pending : (is_array($m06OmissionRecord) ? $m06OmissionRecord : []); $m06Omissions = is_array($m06ContextSource['omissions'] ?? null) ? $m06ContextSource['omissions'] : []; if (is_array($m06Pending)) { $m06PendingFields = is_array($m06Pending['pending_fields'] ?? null) ? $m06Pending['pending_fields'] : []; foreach (['medicamento', 'descripcion', 'tiempo_uso', 'dosis', 'medico'] as $m06Field) { $m06Value = trim((string)($m06Pending[$m06Field] ?? '')); if ($m06Value !== '') $m06Context[$m06Field] = $m06Value; elseif (in_array($m06Field, $m06PendingFields, true) && $m06PendingField === '') $m06PendingField = $m06Field; } } elseif (is_array($m06OmissionRecord)) { foreach (['medicamento', 'descripcion', 'tiempo_uso', 'dosis', 'medico'] as $m06Field) { $m06Value = trim((string)($m06OmissionRecord[$m06Field] ?? '')); if ($m06Value !== '') $m06Context[$m06Field] = $m06Value; } } $m06InitialQuestion = $m06Pending ? ($m06FieldPrompts[$m06PendingField] ?? 'Indica el dato pendiente del medicamento.') : ($m06Records ? '¿Quieres agregar otro medicamento?' : '¿Tomas actualmente algún medicamento?'); $m06ContextJson = json_encode($m06Context, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}'; $m06OmissionsJson = json_encode($m06Omissions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}'; $m06OmissionsByRecordJson = json_encode($m06OmissionsByRecord, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}'; $m06SkippedJson = json_encode(array_keys($m06Omissions), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]'; ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M06" data-m06-state="<?=$m06State;?>" data-m06-has-records="<?=$m06Records ? '1' : '0';?>" data-m06-pending-record="<?=$m06Pending ? (int)($m06Pending['id'] ?? 0) : 0;?>" data-m06-omission-record="<?=is_array($m06OmissionRecord) ? (int)($m06OmissionRecord['id'] ?? 0) : 0;?>" data-m06-omissions-by-record="<?=$escape($m06OmissionsByRecordJson);?>" data-m06-version="<?=$escape($data['m06']['version'] ?? '');?>" data-m06-stable-id="M06:current-medication:<?=$m06Pending ? (int)($m06Pending['id'] ?? 0) : 'new';?>" data-m06-context="<?=$escape($m06ContextJson);?>" data-m06-omissions="<?=$escape($m06OmissionsJson);?>" data-m06-skipped="<?=$escape($m06SkippedJson);?>" aria-labelledby="m06-question-title">
<div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · Medicación actual</strong></div><h3 id="m06-question-title" class="interview-question-text" data-m06-question><?=$escape($m06InitialQuestion);?></h3><p class="interview-question-instruction" data-m06-instruction><?= $m06Pending ? 'Indica sólo el dato solicitado. Si no lo recuerdas, puedes continuar sin registrarlo.' : ($m06Records ? 'Puedes agregar otro medicamento o terminar la medicación.' : 'Puedes escribir solamente el nombre del medicamento; los demás datos son opcionales y se preguntarán después.'); ?></p></div>
                            <?php if (!$m06Finalized): ?>
                            <div class="patient-interview-answer interview-composer" data-m06-composer<?= $m06State === 'after-save' ? ' hidden' : ''; ?>><div class="interview-composer-input"><label class="visually-hidden" for="m06-input">Respuesta sobre medicación actual</label><input id="m06-input" class="form-control" data-m06-input type="text" placeholder="Escribe o dicta tu respuesta…"></div><button type="button" class="btn btn-primary chat-send-button" data-m06-interpret aria-label="Enviar respuesta M06" title="Enviar respuesta M06">➤ <span class="chat-send-label">Enviar</span></button></div>
<div class="interview-quick-actions m06-quick-actions" data-m06-quick-actions role="group" aria-label="Respuestas rápidas M06"><button type="button" class="btn btn-outline-primary" data-m06-quick data-value="Sí" data-m06-action="START_MEDICATION" data-m06-stage="initial"<?= $m06State !== 'initial' ? ' hidden' : ''; ?>>Sí</button><button type="button" class="btn btn-primary" data-m06-quick data-value="Sí" data-m06-action="START_MEDICATION" data-m06-stage="after-save"<?= $m06State !== 'after-save' ? ' hidden' : ''; ?>>Sí, agregar otro</button><button type="button" class="btn btn-outline-secondary" data-m06-skip data-m06-skip-mode="unknown" hidden>No lo sé</button><button type="button" class="btn btn-outline-secondary" data-m06-skip data-m06-skip-mode="omit" hidden>Continuar sin este dato</button><button type="button" class="btn btn-outline-primary" data-m06-quick data-value="No" data-m06-action="<?=$m06State === 'after-save' ? 'FINISH_ADDITIONAL_MEDICATIONS' : 'FINISH_NO_CURRENT_MEDICATION';?>"><?= $m06State === 'after-save' ? 'No, terminar medicación' : 'No tomo medicamentos';?></button></div>
                            <small class="d-block patient-interview-muted mt-1" data-m06-result role="status" aria-live="polite" hidden></small>
                            <?php else: ?><p class="patient-interview-muted" role="status">M06 ya está finalizado. La edición ordinaria está bloqueada.</p><?php endif; ?>
                        </article>
                        <?php endif; ?>
                        <?php $m07State = $data['m07'] ?? []; $m07Categories = $m07State['categories'] ?? []; $m07StatusLabels = ['pending' => 'Pendiente', 'complete' => 'Completo', 'blocked' => 'M07 bloqueado por contrato', 'conflict' => 'Revisar inconsistencia', 'duplicate' => 'Duplicado']; $m07FirstCategory = 0; foreach ($m07Categories as $categoryIndex => $category) { $categoryPending = array_filter($category['items'] ?? [], static fn(array $item): bool => ($item['status'] ?? 'pending') !== 'complete'); if ($categoryPending) { $m07FirstCategory = (int)$categoryIndex; break; } } ?>
                        <?php if (!empty($m07State['canonical_read'])): $m07Active = is_array($m07State['active_question'] ?? null) ? $m07State['active_question'] : null; ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M07" data-m07-renderer="canonical" data-m07-version="<?=$escape($m07State['version'] ?? '');?>" data-m07-pending="<?=((int)($m07State['pending_items'] ?? 0) > 0) ? '1' : '0';?>" data-m07-write-enabled="<?=!empty($m07State['write_enabled']) ? '1' : '0';?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M07 medicamentos para el dolor</strong></div><?php if (!empty($m07State['write_enabled']) && $m07Active): ?><p class="patient-interview-question-topic"><?=$escape($m07Active['theme'] ?? 'Medicamentos para el dolor');?></p><h3 class="interview-question-text" data-m07-canonical-question><?=$escape($m07Active['prompt'] ?? '');?></h3><p class="interview-question-instruction"><?=$escape($m07Active['instruction'] ?? 'Responde la pregunta activa y espera la confirmación antes de continuar.');?></p><?php else: ?><h3 class="interview-question-text">La entrevista conversacional M07 no está habilitada en este entorno.</h3><?php endif; ?></div>
                            <?php if (empty($m07State['write_enabled'])): ?>
                                <div class="alert alert-info" role="status" data-m07-blocked>La captura clínica de M07 está bloqueada por la política del servidor. No se guardará información desde esta pantalla.</div>
                                <?php if (!$m07Active): ?><p class="patient-interview-muted">No hay preguntas canónicas pendientes para mostrar. La finalización permanece deshabilitada.</p><?php endif; ?>
                            <?php elseif (!$m07Active): ?>
                                <p class="patient-interview-muted" role="status">No hay preguntas canónicas pendientes. El servidor todavía no habilita la finalización de M07.</p>
                            <?php elseif (($m07Active['mode'] ?? '') === 'blocked'): ?>
                                <div class="alert alert-warning" role="status"><?=$escape($m07Active['blocked_reason'] ?? 'Esta pregunta requiere revisión antes de continuar.');?></div>
                            <?php else: $m07Mode = (string)($m07Active['mode'] ?? ''); $m07Fields = is_array($m07Active['fields'] ?? null) ? $m07Active['fields'] : []; $m07OtherFields = is_array($m07Active['entry_fields'] ?? null) ? $m07Active['entry_fields'] : []; ?>
                                <form class="patient-interview-answer interview-composer" data-m07-canonical-editor data-mode="<?=$escape($m07Mode);?>" data-stable-id="<?=$escape($m07Active['stable_id']);?>" data-question-id="<?=(int)$m07Active['question_id'];?>" data-theme-id="<?=(int)$m07Active['theme_id'];?>" data-field="<?=$escape($m07Active['field'] ?? 'respuesta');?>" data-entry-id="<?=(int)($m07Active['entry_id'] ?? 0);?>" data-entry-version="<?=(int)($m07Active['entry_version'] ?? 0);?>" data-entry-name="<?=$escape($m07Active['entry_name'] ?? '');?>" data-has-dependents="<?=array_filter(['dosis','resultados','consumo','descripcion'], static fn(string $field): bool => trim((string)($m07Fields[$field] ?? '')) !== '') ? '1' : '0';?>" novalidate>
                                    <?php if ($m07Mode === 'catalog' || $m07Mode === 'other_create'): ?>
                                        <fieldset class="mb-3"><legend class="form-label">Selecciona una respuesta</legend><label class="me-3"><input type="radio" name="m07-response" value="Si" data-m07-response <?=($m07Fields['respuesta'] ?? '') === 'Si' ? 'checked' : '';?>> Sí</label><label><input type="radio" name="m07-response" value="No" data-m07-response <?=($m07Fields['respuesta'] ?? '') === 'No' ? 'checked' : '';?>> No</label></fieldset>
                                    <?php endif; ?>
                                    <?php if ($m07Mode === 'catalog'): ?>
                                        <div data-m07-dependencies <?=($m07Fields['respuesta'] ?? '') === 'Si' ? '' : 'hidden';?>><div class="mb-3"><label class="form-label" for="m07-dose">Dosis</label><input id="m07-dose" class="form-control" type="text" data-m07-dose value="<?=$escape($m07Fields['dosis'] ?? '');?>" autocomplete="off"></div><div class="mb-3"><label class="form-label" for="m07-result">Resultado</label><select id="m07-result" class="form-select" data-m07-result><option value="">Selecciona</option><?php foreach (['Funciono'=>'Funcionó','No funciono'=>'No funcionó','Tuve efectos adversos'=>'Tuve efectos adversos'] as $value=>$label): ?><option value="<?=$value;?>" <?=($m07Fields['resultados'] ?? '') === $value ? 'selected' : '';?>><?=$label;?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label" for="m07-consumption">¿Todavía lo utilizas?</label><select id="m07-consumption" class="form-select" data-m07-consumption><option value="">Selecciona</option><option value="Si" <?=($m07Fields['consumo'] ?? '') === 'Si' ? 'selected' : '';?>>Sí</option><option value="No" <?=($m07Fields['consumo'] ?? '') === 'No' ? 'selected' : '';?>>No</option></select></div><div class="mb-3" data-m07-adverse-wrap <?=($m07Fields['resultados'] ?? '') === 'Tuve efectos adversos' ? '' : 'hidden';?>><label class="form-label" for="m07-adverse">Describe los efectos adversos</label><textarea id="m07-adverse" class="form-control" rows="2" data-m07-description><?=$escape($m07Fields['descripcion'] ?? '');?></textarea></div></div>
                                    <?php elseif ($m07Mode === 'other_create'): ?>
                                        <div class="mb-3" data-m07-other-name-wrap <?=($m07Fields['respuesta'] ?? '') === 'Si' ? '' : 'hidden';?>><label class="form-label" for="m07-other-name">¿Cuál es el nombre del medicamento?</label><input id="m07-other-name" class="form-control" type="text" data-m07-other-name maxlength="255" autocomplete="off"></div>
                                    <?php elseif ($m07Mode === 'other_update'): ?>
                                        <p class="patient-interview-muted">Medicamento: <strong><?=$escape($m07Active['entry_name'] ?? '');?></strong></p>
                                        <?php $m07Field = (string)($m07Active['field'] ?? ''); $m07Value = (string)($m07OtherFields[$m07Field] ?? ''); ?>
                                        <?php if (in_array($m07Field, ['consumo','resultados'], true)): ?><label class="form-label" for="m07-other-field"><?=$m07Field === 'consumo' ? '¿Todavía lo utilizas?' : 'Resultado';?></label><select id="m07-other-field" class="form-select" data-m07-other-field><option value="">Selecciona</option><?php foreach (($m07Active['options'] ?? []) as $value): ?><option value="<?=$escape($value);?>" <?=$m07Value === $value ? 'selected' : '';?>><?=$escape(match ($value) {'Si'=>'Sí','Funciono'=>'Funcionó','No funciono'=>'No funcionó',default=>$value});?></option><?php endforeach; ?></select><?php else: ?><label class="form-label" for="m07-other-field"><?=$escape($m07Active['field'] === 'dosis' ? 'Dosis' : 'Describe los efectos adversos');?></label><input id="m07-other-field" class="form-control" type="text" data-m07-other-field value="<?=$escape($m07Value);?>" autocomplete="off"><?php endif; ?>
                                    <?php endif; ?>
                                    <div class="d-flex gap-2 mt-3"><button type="submit" class="btn btn-primary chat-send-button" data-m07-send>Enviar</button><button type="button" class="btn chat-voice-button" data-m07-repeat aria-label="Repetir pregunta"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path><path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path></svg><span>Repetir pregunta</span></button><button type="button" class="btn btn-outline-secondary" data-m07-help title="Ayuda sobre la pregunta activa">Ayuda</button></div>
                                    <small class="d-block patient-interview-muted mt-2" data-m07-canonical-status role="status" aria-live="polite" hidden></small>
                                </form>
                            <?php endif; ?>
                            <p class="patient-interview-muted mb-0">M07 sólo avanzará después de que el servidor confirme el guardado y readback. La finalización canónica permanece deshabilitada.</p>
                        </article>
                        <?php elseif (!empty($m07State['write_enabled'])): ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M07" data-m07-renderer="legacy" data-m07-version="<?=$escape($m07State['version'] ?? '');?>" data-m07-pending="<?=((int)($m07State['pending_items'] ?? 0) > 0) ? '1' : '0';?>" data-m07-write-enabled="1">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M07 medicamentos para el dolor</strong></div><p class="interview-question-text">Revisaremos los medicamentos agrupados por categoría. Selecciona los que sí utilizaste o confirma explícitamente los que no utilizaste. Nada se guarda al seleccionar; la persistencia requiere una acción explícita.</p></div>
                            <?php if (!$m07Categories): ?><p class="patient-interview-muted">El catálogo P09 no está disponible; M07 permanece bloqueado y no se generaron respuestas.</p><?php endif; ?>
                            <?php if ($m07Categories): ?><nav class="m07-category-nav" data-m07-category-nav aria-label="Categorías de medicamentos M07"><span class="patient-interview-muted">Categoría:</span><?php foreach ($m07Categories as $categoryIndex => $category): ?><button type="button" class="btn btn-sm btn-outline-secondary" data-m07-category-goto="<?=$categoryIndex;?>" aria-controls="m07-category-<?=$categoryIndex;?>"><?=$escape($category['theme']);?></button><?php endforeach; ?></nav><?php endif; ?>
                            <div class="m07-categories" data-m07-categories>
                            <?php foreach ($m07Categories as $categoryIndex => $category): $categoryPending = array_filter($category['items'] ?? [], static fn(array $item): bool => ($item['status'] ?? 'pending') !== 'complete'); ?>
                                <section id="m07-category-<?=$categoryIndex;?>" class="m07-category" data-m07-category data-category-index="<?=$categoryIndex;?>" data-category-pending="<?=count($categoryPending);?>" data-theme-id="<?=(int)$category['theme_id'];?>"<?=$categoryIndex !== $m07FirstCategory ? ' hidden' : '';?> aria-labelledby="m07-category-title-<?=$categoryIndex;?>">
                                    <h3 id="m07-category-title-<?=$categoryIndex;?>" class="h5 mb-3"><?=$escape($category['theme']);?></h3>
                                    <div class="m07-items" role="list">
                                    <?php foreach (($category['items'] ?? []) as $item): $fields = $item['fields'] ?? []; $questionId = (int)$item['question_id']; $itemKey = 'm07-' . $questionId; $itemStatus = (string)($item['status'] ?? 'pending'); $isOther = !empty($item['other_blocked']); $used = ($fields['respuesta'] ?? '') === 'Si'; $otherEntries = array_values(array_filter($item['other_medications'] ?? [], static fn(array $entry): bool => ($entry['status'] ?? '') !== 'inactive')); ?>
                                        <div class="m07-item" data-m07-item data-question-id="<?=$questionId;?>" data-theme-id="<?=(int)$category['theme_id'];?>" data-status="<?=$escape($itemStatus);?>" data-other-blocked="<?=$isOther ? '1' : '0';?>" data-current-result="<?=$escape($fields['resultados'] ?? '');?>">
                                            <?php if (!$isOther): ?><label class="m07-item-choice" for="<?=$itemKey;?>-used">
                                                <input id="<?=$itemKey;?>-used" type="checkbox" class="form-check-input" data-m07-used <?=$used ? 'checked' : '';?> aria-describedby="<?=$itemKey;?>-status">
                                                <span><?=$escape($item['question'] ?? 'Medicamento');?></span>
                                            </label><?php else: ?><strong class="d-block mb-2"><?=$escape($item['question'] ?? 'Otro medicamento');?></strong><?php endif; ?>
                                            <span id="<?=$itemKey;?>-status" class="badge text-bg-light" data-m07-status><?=$escape($m07StatusLabels[$itemStatus] ?? 'Pendiente');?></span>
                                            <?php if ($isOther): ?>
                                                <?php if ($otherEntries): foreach ($otherEntries as $otherEntry): $entryFields = $otherEntry['fields'] ?? []; $entryId = (int)$otherEntry['id']; $entryStep = empty($entryFields['dosis']) ? 'dosis' : (empty($entryFields['consumo']) ? 'consumo' : (empty($entryFields['resultados']) ? 'resultados' : (($entryFields['resultados'] ?? '') === 'Tuve efectos adversos' && empty($entryFields['descripcion']) ? 'descripcion' : 'complete'))); ?>
                                                    <div class="m07-item-details mt-3" data-m07-other-entry data-entry-id="<?=$entryId;?>" data-entry-version="<?=(int)$otherEntry['version'];?>" data-next-field="<?=$entryStep;?>">
                                                        <label class="form-label" for="<?=$itemKey;?>-other-name-<?=$entryId;?>">Medicamento adicional</label>
                                                        <input id="<?=$itemKey;?>-other-name-<?=$entryId;?>" class="form-control mb-2" data-m07-other-name data-original-name="<?=$escape($otherEntry['name'] ?? '');?>" value="<?=$escape($otherEntry['name'] ?? '');?>" maxlength="255">
                                                        <div class="row g-2">
                                                            <div class="col-12 col-md-6" <?=($entryStep !== 'complete' && $entryStep !== 'dosis') ? 'hidden' : '';?>><label class="form-label" for="<?=$itemKey;?>-other-dose-<?=$entryId;?>">Dosis documentada</label><input id="<?=$itemKey;?>-other-dose-<?=$entryId;?>" class="form-control" type="text" data-m07-other-dose value="<?=$escape($entryFields['dosis'] ?? '');?>"></div>
                                                            <div class="col-12 col-md-6" <?=($entryStep !== 'complete' && $entryStep !== 'consumo') ? 'hidden' : '';?>><label class="form-label" for="<?=$itemKey;?>-other-consumption-<?=$entryId;?>">¿Todavía lo utilizas?</label><select id="<?=$itemKey;?>-other-consumption-<?=$entryId;?>" class="form-select" data-m07-other-consumption><option value="">Selecciona</option><option value="Si" <?=($entryFields['consumo'] ?? '') === 'Si' ? 'selected' : '';?>>Sí</option><option value="No" <?=($entryFields['consumo'] ?? '') === 'No' ? 'selected' : '';?>>No</option></select></div>
                                                            <div class="col-12" <?=($entryStep !== 'complete' && $entryStep !== 'resultados') ? 'hidden' : '';?>><label class="form-label" for="<?=$itemKey;?>-other-result-<?=$entryId;?>">Resultado</label><select id="<?=$itemKey;?>-other-result-<?=$entryId;?>" class="form-select" data-m07-other-result><option value="">Selecciona</option><option value="Funciono" <?=($entryFields['resultados'] ?? '') === 'Funciono' ? 'selected' : '';?>>Funcionó</option><option value="No funciono" <?=($entryFields['resultados'] ?? '') === 'No funciono' ? 'selected' : '';?>>No funcionó</option><option value="Tuve efectos adversos" <?=($entryFields['resultados'] ?? '') === 'Tuve efectos adversos' ? 'selected' : '';?>>Tuve efectos adversos</option></select></div>
                                                            <div class="col-12" data-m07-other-adverse-wrap <?=($entryStep === 'complete' || $entryStep === 'descripcion') && ($entryFields['resultados'] ?? '') === 'Tuve efectos adversos' ? '' : 'hidden';?>><label class="form-label" for="<?=$itemKey;?>-other-description-<?=$entryId;?>">Descripción de efectos adversos</label><textarea id="<?=$itemKey;?>-other-description-<?=$entryId;?>" class="form-control" rows="2" data-m07-other-description><?=$escape($entryFields['descripcion'] ?? '');?></textarea></div>
                                                            <div class="col-12"><button type="button" class="btn btn-primary" data-m07-save-other disabled><?=$entryStep === 'complete' ? 'Guardar corrección' : 'Guardar y continuar';?></button></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; endif; ?>
                                                <?php if (($fields['respuesta'] ?? '') !== 'No'): ?><div class="m07-item-details mt-3" data-m07-other-create>
                                                    <label class="form-label" for="<?=$itemKey;?>-new-name">Nombre del medicamento adicional</label>
                                                    <div class="input-group"><input id="<?=$itemKey;?>-new-name" class="form-control" data-m07-new-other-name maxlength="255" placeholder="Escribe el nombre del medicamento"><button type="button" class="btn btn-primary" data-m07-create-other disabled><?=$otherEntries ? 'Agregar otro medicamento' : 'Sí, agregar medicamento';?></button></div>
                                                </div><?php endif; ?>
                                                <?php if (!$otherEntries && !in_array(($fields['respuesta'] ?? ''), ['Si','No'], true)): ?><button type="button" class="btn btn-outline-secondary mt-2" data-m07-other-no disabled>No se utilizó</button><?php endif; ?>
                                            <?php else: ?><div class="m07-item-details" data-m07-details <?=$used ? '' : 'hidden';?>>
                                                <div class="row g-2">
                                                    <div class="col-12 col-md-6"><label class="form-label" for="<?=$itemKey;?>-dose">Dosis documentada</label><input id="<?=$itemKey;?>-dose" class="form-control" type="text" data-m07-dose value="<?=$escape($fields['dosis'] ?? '');?>" placeholder="Escribe la dosis, sin inventar unidades"></div>
                                                    <div class="col-12 col-md-6"><fieldset><legend class="form-label">Resultado</legend><div class="d-flex flex-wrap gap-2"><label><input type="radio" name="<?=$itemKey;?>-result" value="Funciono" data-m07-result <?=($fields['resultados'] ?? '') === 'Funciono' ? 'checked' : '';?>> Funcionó</label><label><input type="radio" name="<?=$itemKey;?>-result" value="No funciono" data-m07-result <?=($fields['resultados'] ?? '') === 'No funciono' ? 'checked' : '';?>> No funcionó</label><label><input type="radio" name="<?=$itemKey;?>-result" value="Tuve efectos adversos" data-m07-result <?=($fields['resultados'] ?? '') === 'Tuve efectos adversos' ? 'checked' : '';?>> Efectos adversos</label></div></fieldset></div>
                                                    <div class="col-12" data-m07-adverse-wrap <?=($fields['resultados'] ?? '') === 'Tuve efectos adversos' ? '' : 'hidden';?>><label class="form-label" for="<?=$itemKey;?>-adverse">Descripción de efectos adversos</label><textarea id="<?=$itemKey;?>-adverse" class="form-control" rows="2" data-m07-description><?=$escape($fields['descripcion'] ?? '');?></textarea></div>
                                                    <div class="col-12"><fieldset><legend class="form-label">¿Todavía lo utilizas?</legend><label class="me-3"><input type="radio" name="<?=$itemKey;?>-consumption" value="Si" data-m07-consumption <?=($fields['consumo'] ?? '') === 'Si' ? 'checked' : '';?>> Sí</label><label><input type="radio" name="<?=$itemKey;?>-consumption" value="No" data-m07-consumption <?=($fields['consumo'] ?? '') === 'No' ? 'checked' : '';?>> No</label></fieldset></div>
                                                </div>
                                            </div><?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                    <div class="patient-interview-answer mt-3 m07-actions">
                                        <button type="button" class="btn btn-outline-secondary" data-m07-confirm-negatives>Confirmar no utilizados</button>
                                        <button type="button" class="btn btn-primary" data-m07-save-selected>Guardar seleccionados</button>
                                    </div>
                                    <div class="patient-interview-answer mt-2 m07-navigation" aria-label="Navegación de categorías"><button type="button" class="btn btn-outline-secondary" data-m07-previous-category<?=$categoryIndex === 0 ? ' disabled' : '';?>>← Categoría anterior</button><span class="patient-interview-muted" data-m07-category-state><?=count($categoryPending) ? 'Respuestas pendientes en esta categoría.' : 'Categoría revisada.';?></span><button type="button" class="btn btn-outline-secondary" data-m07-next-category<?=$categoryIndex === count($m07Categories) - 1 ? ' disabled' : '';?>>Siguiente categoría →</button></div>
                                </section>
                            <?php endforeach; ?>
                            </div>
                            <div class="patient-interview-status mt-3" data-m07-message role="status" aria-live="polite" hidden></div>
                            <p class="patient-interview-muted mb-0">Los datos desconocidos permanecen pendientes. Las respuestas combinadas de eficacia y efectos adversos no se reducen a una opción. La finalización de M07 sigue deshabilitada hasta cerrar las reglas de completitud clínica.</p>
                        </article>
                        <?php else: ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M07" data-m07-renderer="blocked" data-m07-write-enabled="0"><div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M07 medicamentos para el dolor</strong></div><p class="interview-question-text">La entrevista conversacional M07 no está habilitada en este entorno.</p></div><p class="alert alert-info" role="status">No se muestran controles de escritura mientras la política canónica del servidor está cerrada.</p></article>
                        <?php endif; ?>
                        <?php if (!empty($data['m08'])) { $m08Items = $data['m08']['items'] ?? []; $m08Pending = null; foreach ($m08Items as $m08Item) { $m08Utilizo = trim((string)($m08Item['utilizo'] ?? '')); $m08Result = trim((string)($m08Item['resultado'] ?? '')); $m08Description = trim((string)($m08Item['descripcion'] ?? '')); $m08Incomplete = ($m08Utilizo === '') || ($m08Utilizo === 'Si' && ($m08Result === '' || ($m08Result === 'Tuve efectos adversos' && $m08Description === ''))); if ($m08Incomplete) { $m08Pending = $m08Item; break; } } ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M08" data-m08-version="<?=$escape($data['m08']['version'] ?? '');?>" data-m08-write-enabled="<?=!empty($data['m08']['write_enabled']) ? '1' : '0';?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M08 tratamientos</strong></div><p class="interview-question-text">Revisaremos los tratamientos del catálogo uno por uno. Pulsa Enviar para validar y guardar la respuesta.</p></div>
                            <?php if ($m08Pending): ?>
                            <p class="patient-interview-muted"><strong>Tratamiento: <?=$escape($m08Pending['tratamiento'] ?? '');?></strong></p>
                            <div class="patient-interview-answer interview-composer"><div class="interview-composer-input"><label class="visually-hidden" for="m08-input">Respuesta M08</label><input id="m08-input" class="form-control" data-m08-input type="text" placeholder="Ej. no; o utilizo: Sí; resultado: Funciono"></div><button type="button" class="btn btn-outline-primary chat-send-button" data-m08-interpret aria-label="Interpretar respuesta M08" title="Interpretar respuesta M08">➤</button></div>
                            <small class="d-block patient-interview-muted mt-1" data-m08-result role="status" aria-live="polite" hidden></small>
                            <?php else: ?><p class="patient-interview-muted">Tratamientos M08 completos o sin pendientes documentales.</p><?php endif; ?>
                            <p class="patient-interview-muted mb-0">Los procedimientos M08 permanecen separados del catálogo de tratamientos y se guardan únicamente cuando su contrato confirma que los campos están completos.</p>
                        </article>
                        <?php } ?>
                        <?php if (!empty($data['m09'])): ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M09" data-m09-version="<?=$escape($data['m09']['version'] ?? '');?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M09</strong></div><p class="interview-question-text">Revisaremos los cuatro campos de evaluación del dolor. Pulsa Enviar para validar y guardar la respuesta; el mapa corporal se mantiene separado.</p></div>
                            <div class="patient-interview-answer interview-composer"><div class="interview-composer-input"><label class="visually-hidden" for="m09-input">Respuesta M09</label><input id="m09-input" class="form-control" data-m09-input type="text" placeholder="dolor: Sí; tiempo: 3 meses; descripción: inició gradualmente; incremento: igual"></div><button type="button" class="btn btn-outline-primary chat-send-button" data-m09-interpret aria-label="Interpretar respuesta M09" title="Interpretar respuesta M09">➤</button></div>
                            <small class="d-block patient-interview-muted mt-1" data-m09-result role="status" aria-live="polite" hidden></small>
                            <p class="patient-interview-muted mb-0">La localización anatómica no se interpreta desde texto libre.</p>
                        </article>
                        <?php endif; ?>
                        <?php if (!empty($data['m09Map'])): $m09MapVersion = $data['m09Map']['version'] ?? ''; ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M09-MAP" data-m09-map-version="<?=$escape($m09MapVersion);?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M09 mapa corporal</strong></div><p class="interview-question-text">Ahora vamos a identificar dónde sientes dolor. Marca las zonas en el mapa, revisa la previsualización y confirma solo cuando la imagen sea correcta.</p></div>
                            <div class="chat-p11-map-grid">
                                <?php foreach (['frente' => 'Imagen frontal', 'espalda' => 'Imagen posterior'] as $orientation => $label): ?>
                                <section class="chat-p11-map" data-map-panel="<?=$orientation;?>" aria-labelledby="chat-map-title-<?=$orientation;?>">
                                    <h3 id="chat-map-title-<?=$orientation;?>" class="h5"><?=$escape($label);?></h3>
                                    <div class="d-flex flex-wrap gap-2 mb-2" role="group" aria-label="Tipo de sensación <?=$escape($label);?>">
                                        <?php foreach (['#ff0000' => 'Dolor', '#0000ff' => 'Adormecido', '#00ff00' => 'Molestia al roce', '#ffff00' => 'Sin sensibilidad', '#ff00ff' => 'Punzadas y calambres'] as $color => $colorLabel): ?><button type="button" class="btn btn-sm btn-outline-secondary chat-map-color" data-map-color="<?=$color;?>" aria-label="<?=$escape($colorLabel);?>" style="border-color:<?=$color;?>;">● <?=$escape($colorLabel);?></button><?php endforeach; ?>
                                    </div>
                                    <div class="chat-p11-canvas-wrap"><canvas width="1200" height="1800" data-map-canvas="<?=$orientation;?>" aria-label="Mapa corporal <?=$escape($label);?>"></canvas></div>
                                    <div class="patient-interview-answer mt-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-map-clear="<?=$orientation;?>">Limpiar edición</button><button type="button" class="btn btn-outline-primary btn-sm" data-map-preview="<?=$orientation;?>">Previsualizar PNG</button></div>
                                    <div class="mt-2" data-map-preview-box="<?=$orientation;?>" hidden><p class="patient-interview-muted mb-1">Previsualización: nada se ha guardado.</p><img class="chat-p11-preview" data-map-preview-image="<?=$orientation;?>" alt="Previsualización del mapa corporal"><div class="patient-interview-answer"><button type="button" class="btn btn-outline-secondary btn-sm" data-map-edit="<?=$orientation;?>">Volver a editar</button><button type="button" class="btn btn-primary btn-sm" data-map-confirm="<?=$orientation;?>">Confirmar y guardar</button><button type="button" class="btn btn-outline-secondary btn-sm" data-map-cancel="<?=$orientation;?>">Cancelar</button></div></div>
                                </section>
                                <?php endforeach; ?>
                            </div>
                            <p class="patient-interview-muted mb-0">Las marcas se guardan únicamente después de confirmar la previsualización. El mapa utiliza la persistencia PNG existente y no interpreta texto como regiones.</p>
                        </article>
                        <?php endif; ?>
                        <div id="interview-status" class="patient-interview-muted patient-interview-status" data-interview-status role="status" aria-live="polite" aria-atomic="true" hidden></div>
                    </div>
                </section>
                </div>
            </div>
            <div class="patient-interview-doctor-float">
            <details class="patient-interview-doctor-assistant" aria-label="Asistente virtual de la entrevista">
                <summary aria-label="Abrir o cerrar el panel de asistencia virtual">
                    <img src="<?=$doctorAvatarUrl;?>" width="1024" height="1536" alt="" aria-hidden="true" decoding="async">
                    <span class="visually-hidden">Abrir o cerrar el panel de asistencia virtual</span>
                </summary>
                <section class="patient-interview-doctor-panel" aria-labelledby="patient-interview-doctor-title">
                    <h2 id="patient-interview-doctor-title">Tu asistente virtual</h2>
                    <p>Estoy aquí para ayudarte con tu entrevista.</p>
                    <div class="patient-interview-doctor-actions" aria-label="Acciones de la entrevista">
                        <button type="button" data-doctor-repeat disabled aria-disabled="true" aria-label="Repetir pregunta (no disponible)" title="No hay una pregunta activa disponible para repetir." aria-describedby="patient-interview-doctor-note patient-interview-doctor-repeat-status"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.7-5.7L20 8.5"/><path d="M20 4v4.5h-4.5M20 12a8 8 0 0 1-13.7 5.7L4 15.5"/><path d="M4 20v-4.5h4.5"/></svg><span>Repetir pregunta</span></button>
                        <button type="button" data-doctor-stop disabled aria-disabled="true" aria-label="Detener audio (no hay audio activo)" title="No hay una reproducción activa para detener." aria-describedby="patient-interview-doctor-note"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2"/></svg><span>Detener audio</span></button>
                        <button type="button" data-doctor-help disabled aria-disabled="true" aria-label="Ayuda con esta pregunta" title="Ayuda sobre la pregunta activa" aria-describedby="patient-interview-doctor-note"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.7 9a2.4 2.4 0 1 1 4.2 1.6c-1.2 1.1-1.9 1.4-1.9 3"/><path d="M12 17h.01"/></svg><span>Ayuda con esta pregunta</span></button>
                        <a class="patient-interview-doctor-action-link" href="<?=SERVIDOR;?>historia-clinica/respuestas-previas" aria-label="Respuestas previas"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z"/><path d="M14 3.75v4h4M8.5 12h7M8.5 15.5h7"/></svg><span>Respuestas previas</span></a>
                    </div>
                    <details class="patient-interview-doctor-voice-settings interview-voice-panel" data-voice-panel data-voice-state="DISABLED" aria-labelledby="interview-voice-title">
                        <summary class="patient-interview-doctor-voice-summary" id="interview-voice-title"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg><span>Configuración de voz</span></summary>
                        <span class="visually-hidden" data-voice-status aria-live="polite">Activando guía por voz…</span>
                        <label class="voice-speed-control" for="interview-voice-rate">Velocidad <select id="interview-voice-rate" data-voice-rate aria-label="Velocidad de narración"><option value="0.85">Lenta</option><option value="1.0" selected>Normal</option><option value="1.1">Rápida</option></select></label>
                        <div class="voice-selector" id="interview-voice-selector" data-voice-selector hidden>
                            <p class="voice-selector-note">Compara voces de este navegador. Usa explícitamente una voz para aplicarla a la entrevista; la preferencia se guarda en este navegador, sin respuestas ni datos clínicos.</p>
                            <label for="interview-voice-compare">Voces disponibles (elige hasta 3)</label>
                            <select id="interview-voice-compare" data-voice-compare multiple size="4" aria-describedby="interview-voice-inventory"></select>
                            <small id="interview-voice-inventory" data-voice-inventory class="voice-selector-note" aria-live="polite">Detectando voces disponibles…</small>
                            <small data-voice-local-notice class="voice-selector-warning" aria-live="polite"></small>
                            <label for="interview-voice-sample">Frase ficticia</label>
                            <select id="interview-voice-sample" data-voice-sample aria-label="Frase ficticia para comparar voces">
                                <option value="¿Actualmente tomas algún medicamento para aliviar el dolor?">Pregunta 1</option>
                                <option value="¿Desde hace cuánto tiempo utilizas este medicamento?">Pregunta 2</option>
                                <option value="¿Has presentado alguna reacción adversa?">Pregunta 3</option>
                            </select>
                            <div class="voice-selector-actions">
                                <button type="button" class="btn btn-outline-primary btn-sm" data-voice-preview disabled>Escuchar muestra</button>
                                <button type="button" class="btn btn-primary btn-sm" data-voice-compare-start disabled>Comparar seleccionadas</button>
                            </div>
                            <div class="voice-selector-actions">
                                <button type="button" class="btn btn-primary btn-sm" data-voice-apply disabled>Usar esta voz</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-voice-auto>Usar voz automática</button>
                            </div>
                            <small data-voice-applied-status class="voice-selector-note" role="status" aria-live="polite">Usando selección automática de Chrome.</small>
                            <small data-voice-diagnostic class="voice-selector-note" aria-live="polite">Diagnóstico local: aún no hay reproducción.</small>
                            <small data-voice-preview-status class="voice-selector-note" role="status" aria-live="polite">No se ha reproducido ninguna muestra.</small>
                        </div>
                    </details>
                    <p class="patient-interview-doctor-note" id="patient-interview-doctor-note">Acciones visuales; los controles disponibles siguen junto a la pregunta y en la entrevista.</p>
                </section>
            </details>
            <div class="patient-interview-doctor-shortcuts" role="group" aria-label="Accesos rápidos de la entrevista">
                <button type="button" data-doctor-repeat disabled aria-disabled="true" aria-label="Repetir pregunta (no disponible)" title="No hay una pregunta activa disponible para repetir." aria-describedby="patient-interview-doctor-repeat-status"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.7-5.7L20 8.5"/><path d="M20 4v4.5h-4.5M20 12a8 8 0 0 1-13.7 5.7L4 15.5"/><path d="M4 20v-4.5h4.5"/></svg><span>Repetir pregunta</span></button>
                <button type="button" data-doctor-help disabled aria-disabled="true" aria-label="Ayuda con esta pregunta" title="Ayuda sobre la pregunta activa"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.7 9a2.4 2.4 0 1 1 4.2 1.6c-1.2 1.1-1.9 1.4-1.9 3"/><path d="M12 17h.01"/></svg><span>Ayuda con esta pregunta</span></button>
            </div>
            <span class="visually-hidden" id="patient-interview-doctor-repeat-status" role="status" aria-live="polite">No hay una pregunta activa disponible para repetir.</span>
            <span class="patient-interview-doctor-bubble" aria-hidden="true">Estoy aquí para ayudarte</span>
            </div>
        </main>
        <?php include_once __DIR__ . '/../components/footer-doctor.php';?>
    </div>
</div>
<script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
<script src="<?=$coordinatorJsUrl;?>"></script>
<script src="<?=$voiceJsUrl;?>"></script>
<script src="<?=$localDictationJsUrl;?>"></script>
<script src="<?=$m03DictationUiJsUrl;?>"></script>
<script>
(() => {
    const main = document.getElementById('main');
    const conversationContract = <?=$conversationContractJson;?>;
    const piperM02Audio = <?=$m02PiperAudioConfigJson;?>;
    window.patientInterviewContract = conversationContract;
    // Only M03 question cards participate in the legacy question navigator.
    // M02 is also rendered with the large question-card class, but it is a
    // module card controlled by showConversationStage(). Including it here
    // makes hideConversationCards() set hidden=true on M02 and leaves the
    // header active while the actual question is invisible.
    const questions = Array.from(document.querySelectorAll('.patient-interview-question[data-question-index]'));
    const status = document.getElementById('interview-status');
    const progress = document.getElementById('interview-progress');
    const progressBar = document.getElementById('interview-progress-bar');
    const progressMeter = progressBar?.closest('[role="progressbar"]');
    const conversationChat = document.getElementById('interview-chat');
    const stateBadge = document.querySelector('[data-interview-state-label]');
    const currentModuleBadge = document.querySelector('[data-interview-current-module]');
    const contextModuleLabel = document.querySelector('[data-context-current-module]');
    const contextModuleSummary = document.querySelector('[data-context-summary]');
    const responseGuidanceTitle = document.querySelector('[data-guidance-title]');
    const responseGuidanceDescription = document.querySelector('[data-guidance-description]');
    let active = questions.findIndex(question => !question.hidden);
    if (active < 0) active = 0;
    let m03ReviewOnly = false;
    let m03PreviousEditOnly = false;
    let m03ReviewReturnIndex = null;
    let m03ReviewTarget = '';
    let m03QuestionViewRevision = 0;
    const activeM03DictationTarget = () => m03PreviousEditOnly && m03ReviewTarget
        ? m03ReviewTarget
        : (conversationCoordinator.snapshot().currentTarget || '');
    const m03VoiceTargetForCard = card => (m03ReviewOnly || m03PreviousEditOnly)
        && card === questions[active]
        ? m03ReviewTarget
        : '';
    const setStatus = message => {
        if (!status) return;
        const text = String(message ?? '').trim();
        status.textContent = text;
        status.hidden = text === '';
    };
    let clearContextualHelp = () => {};
    const semanticAnswerStates = new Set(['UNKNOWN', 'NOT_REMEMBERED']);
    const semanticAnswerState = question => String(question?.dataset.semanticState || '').trim().toUpperCase();
    const hasConfirmedAnswer = question => Boolean(question?.dataset.savedValue?.trim()) || semanticAnswerStates.has(semanticAnswerState(question));
    const speakM03VisibleQuestion = (question, target, options = {}) => {
        if (conversationStages[conversationStage]?.type !== 'm03'
            || question !== questions[active]
            || question?.hidden
            || question?.closest('[hidden], .chat-stage-hidden')
            || target !== `M03:question:${question?.dataset.questionId || ''}`) return false;
        const canonical = conversationCoordinator.questionContract(target);
        const module = conversationContract.modules?.M03;
        const historicalQuestion = (m03ReviewOnly || m03PreviousEditOnly)
            && canonical?.response?.status === 'documented'
            && canonical.response?.persisted === true;
        const pendingReturnQuestion = options.allowCurrentPending === true
            && !m03ReviewOnly && !m03PreviousEditOnly
            && conversationCoordinator.snapshot().currentTarget === target
            && module?.conversation_state?.state === 'ANSWERABLE'
            && module.conversation_state?.stable_id === target
            && module.state?.write_enabled === true
            && module.state?.finalized !== true
            && canonical?.response?.status === 'pending'
            && canonical.response?.persisted !== true;
        if (canonical?.module_id !== 'M03'
            || canonical.stable_id !== target
            || canonical.response?.eligible !== true
            || (!historicalQuestion && !pendingReturnQuestion)) return false;
        const event = conversationCoordinator.voiceEvent('QUESTION', target, false);
        if (!event?.text || event.stable_id !== target || event.text !== canonical.voice?.QUESTION) return false;
        return voiceController?.handleEvent({...event, module_id: 'M03'}, {
            automatic: voiceController.automaticMode,
            userGesture: options.userGesture === true,
        }) === true;
    };
    const answerLabel = question => {
        const value = question?.dataset.savedValue?.trim() || '';
        if (value) return value;
        if (semanticAnswerState(question) === 'UNKNOWN') return 'Dato desconocido declarado por el paciente';
        if (semanticAnswerState(question) === 'NOT_REMEMBERED') return 'Dato no recordado declarado por el paciente';
        return '';
    };
    const syncM03ContextualActions = () => {
        const module = conversationContract.modules?.M03;
        const moduleIsWritable = module?.state?.write_enabled === true
            && module.state?.finalized !== true
            && module.conversation_state?.state === 'ANSWERABLE'
            && module.conversation_state?.write_enabled === true;
        questions.forEach(card => {
            const button = card.querySelector('[data-m03-contextual-action]');
            const container = button?.closest('.m03-quick-actions');
            if (!button || !container) return;
            const target = `M03:question:${card.dataset.questionId || ''}`;
            const questionContract = conversationContract.question_index?.[target];
            const activeAndAuthorized = conversationStages[conversationStage]?.type === 'm03'
                && card === questions[active]
                && !m03ReviewOnly && !m03PreviousEditOnly
                && card.dataset.m03WriteEnabled === '1'
                && card.dataset.eligible === '1'
                && moduleIsWritable
                && module.conversation_state?.stable_id === target
                && questionContract?.response?.eligible === true
                && questionContract?.response?.status === 'pending'
                && questionContract?.response?.persisted !== true
                && String(button.dataset.actionKey || '').trim() !== '';
            const busy = card.dataset.m03Saving === '1' || card.dataset.m03Interpreting === '1';
            container.hidden = !activeAndAuthorized;
            button.hidden = !activeAndAuthorized;
            button.disabled = !activeAndAuthorized || busy;
        });
    };
    const coordinatorStateLabels = Object.freeze({
        INITIALIZING: 'Preparando', READY: 'Lista para comenzar', ASKING: 'Pregunta activa',
        WAITING_FOR_RESPONSE: 'Esperando tu respuesta', INTERPRETING: 'Interpretando',
        CLARIFICATION_REQUIRED: 'Necesita aclaración', CONFIRMATION_REQUIRED: 'Confirmación requerida',
        SAVING: 'Guardando', SAVE_FAILED: 'No se pudo guardar', RESPONSE_SAVED: 'Respuesta guardada',
        NAVIGATING: 'Avanzando', PAUSED: 'Pausada', RESUMING: 'Reanudando',
        MODULE_COMPLETED: 'Módulo revisado', MODULE_BLOCKED: 'Módulo bloqueado', SESSION_EXPIRED: 'Sesión expirada'
    });
    const alertStates = new Set(['SAVE_FAILED', 'SESSION_EXPIRED']);
    const busyStates = new Set(['INTERPRETING', 'SAVING', 'NAVIGATING', 'RESUMING']);
    const syncCoordinatorState = event => {
        const state = String(event?.state || 'INITIALIZING');
        if (main) { main.dataset.conversationState = state; main.dataset.interviewState = state; }
        if (stateBadge) {
            stateBadge.dataset.state = state;
            stateBadge.textContent = coordinatorStateLabels[state] || state;
        }
        if (status) {
            status.dataset.state = state;
            status.setAttribute('role', alertStates.has(state) ? 'alert' : 'status');
        }
        if (conversationChat) conversationChat.setAttribute('aria-busy', busyStates.has(state) ? 'true' : 'false');
    };
    const conversationCoordinator = new window.PatientInterviewCoordinator({
        contract: conversationContract,
        patientId: Number(main?.dataset.patient || 0),
        onStateChange: event => {
            syncCoordinatorState(event);
            if (['PAUSED', 'INTERPRETING', 'SAVING', 'MODULE_BLOCKED', 'MODULE_COMPLETED', 'SESSION_EXPIRED'].includes(event.state) || ['target_changed', 'previous_question'].includes(event.metadata?.reason)) {
                if (typeof stopAssistantVoice === 'function') stopAssistantVoice();
            }
            if (['target_changed', 'previous_question'].includes(event.metadata?.reason)) clearContextualHelp();
            if (['NAVIGATING', 'PAUSED', 'MODULE_BLOCKED', 'MODULE_COMPLETED', 'SESSION_EXPIRED'].includes(event.state) && typeof stopRecognition === 'function') stopRecognition();
            if (event.state === 'SESSION_EXPIRED') setStatus('La sesión expiró. Recarga la entrevista para continuar sin perder respuestas ya confirmadas.');
        },
        onVoiceEvent: event => {
            if (main && event.type) main.dataset.lastVoiceEvent = event.type;
            if (typeof handleVoiceEvent === 'function') handleVoiceEvent(event);
        },
    });
    // Todos los POST conversacionales pasan por el coordinador; sus endpoints
    // existentes siguen validando sesión, CSRF, paciente, versión y persistencia.
    const fetch = (url, init) => conversationCoordinator.request('auto', url, init);
    window.patientInterviewCoordinator = conversationCoordinator;
    const moduleCards = Array.from(document.querySelectorAll('[data-module]'));
    const conversationStages = [];
    const addModuleStage = selector => { const card = document.querySelector(selector); if (card) conversationStages.push({type:'module', card}); };
    addModuleStage('[data-module="M02"]');
    conversationStages.push({type:'m03', card:null});
    addModuleStage('[data-module="M04"]');
    const m05Card = document.querySelector('[data-module="M05"]');
    const m05V2Card = document.querySelector('[data-module="M05-V2"]');
    if (m05Card?.dataset.m05Pending === '1') conversationStages.push({type:'module', card:m05Card});
    if (m05V2Card?.dataset.m05V2Pending === '1') conversationStages.push({type:'module', card:m05V2Card});
    addModuleStage('[data-module="M06"]');
    addModuleStage('[data-module="M07"]');
    addModuleStage('[data-module="M08"]');
    addModuleStage('[data-module="M09"]');
    addModuleStage('[data-module="M09-MAP"]');
    const stageCompletion = stage => {
        if (stage?.type === 'm03') return conversationContract.modules?.M03?.completion || null;
        const moduleId = stage?.card?.dataset.module || '';
        return moduleId ? (conversationContract.modules?.[moduleId]?.completion || null) : null;
    };
    const stageConversationState = stage => {
        const moduleId = stageModuleId(stage);
        return moduleId ? (conversationContract.modules?.[moduleId]?.conversation_state || null) : null;
    };
    const stageModuleId = stage => stage?.type === 'm03' ? 'M03' : (stage?.card?.dataset.module || '');
    const stageNeedsResume = stage => {
        const completion = stageCompletion(stage);
        const resolverState = stageConversationState(stage)?.state || '';
        return ['ANSWERABLE', 'READY_TO_FINALIZE', 'AWAITING_PROFESSIONAL_REVIEW', 'BLOCKED_PREREQUISITE', 'INCONSISTENT'].includes(resolverState)
            || completion?.resolution === 'AUTHORIZED_FINALIZATION_REQUIRED'
            || !completion
            || completion.complete !== true;
    };
    const nextPendingStage = startIndex => {
        for (let index = Math.max(0, startIndex); index < conversationStages.length; index += 1) {
            if (stageNeedsResume(conversationStages[index])) return index;
        }
        return conversationStages.length;
    };
    const serverResumeModuleId = String(conversationContract.interview_progress?.first_pending_module_id || '').trim();
    const serverPendingStage = serverResumeModuleId
        ? conversationStages.findIndex(stage => stageModuleId(stage) === serverResumeModuleId && stageNeedsResume(stage))
        : -1;
    const firstPendingStage = serverPendingStage >= 0
        ? serverPendingStage
        : conversationStages.findIndex(stageNeedsResume);
    let conversationStage = firstPendingStage >= 0 ? firstPendingStage : conversationStages.length;
    const stageLabel = stage => {
        if (!stage) return 'Entrevista';
        if (stage.type === 'm03') {
            const firstQuestion = questions.find(question => !question.hidden) || questions[0];
            return `M03 · ${firstQuestion?.dataset.themeName || 'Pregunta activa'}`;
        }
        const module = stage.card?.dataset.module || 'Entrevista';
        if (module === 'M09-MAP') return 'M09 · Mapa corporal';
        const contractModule = module === 'M05-V2' ? 'M05' : module;
        return conversationContract.modules?.[contractModule]?.title ? `${contractModule} · ${conversationContract.modules[contractModule].title}` : contractModule;
    };
    const canonicalStageTarget = stage => {
        const card = stage?.card;
        const moduleId = card?.dataset.module || '';
        if (!moduleId) return null;
        const contractModuleId = moduleId === 'M05-V2' ? 'M05' : moduleId;
        const moduleQuestions = conversationContract.modules?.[contractModuleId]?.questions || [];
        if (!moduleQuestions.length) return null;
        const declaredStableId = String(card.dataset.m04StableId || card.dataset.m05StableId || card.dataset.m06StableId || '').trim();
        if (declaredStableId && conversationContract.question_index?.[declaredStableId]?.module_id === contractModuleId) return declaredStableId;
        if (moduleId === 'M07') {
            const activeQuestion = moduleQuestions.find(question => question.active === true && question.confirmation?.write_enabled === true);
            return activeQuestion?.stable_id || null;
        }
        if (moduleId === 'M04') {
            const recordId = String(card.dataset.m04Record || '').trim();
            if (recordId && recordId !== '0') {
                const recordQuestion = moduleQuestions.find(question => String(question.question_id ?? '') === recordId);
                if (recordQuestion?.stable_id) return recordQuestion.stable_id;
            }
        }
        if (moduleId === 'M05' || moduleId === 'M05-V2') {
            const variant = moduleId === 'M05-V2' ? 'v2' : 'v1';
            const pendingId = moduleId === 'M05-V2'
                ? String(card.dataset.m05V2Disease || '').trim()
                : String(card.dataset.m05PendingQuestion || '').trim();
            const variantQuestions = moduleQuestions.filter(question => question.variant === variant && question.stable_id);
            const pendingQuestion = variantQuestions.find(question => String(question.question_id ?? '') === pendingId);
            return (pendingQuestion || variantQuestions[0])?.stable_id || null;
        }
        return moduleQuestions[0]?.stable_id || null;
    };
    const isM07AnswerableStage = stage => {
        const card = stage?.card;
        const moduleId = stageModuleId(stage);
        const resolver = stageConversationState(stage) || {};
        return moduleId === 'M07'
            && card?.dataset.m07WriteEnabled === '1'
            && resolver.state === 'ANSWERABLE';
    };
    const isM07BlockedStage = stage => stageModuleId(stage) === 'M07' && !isM07AnswerableStage(stage);
    const voiceTargetForStage = stage => {
        const moduleId = stageModuleId(stage);
        if (!moduleId) return null;
        const usesQuestionTarget = ['M04', 'M05', 'M05-V2', 'M06'].includes(moduleId) || isM07AnswerableStage(stage);
        return usesQuestionTarget ? (canonicalStageTarget(stage) || moduleId) : moduleId;
    };
    const contextSummaryFor = label => {
        const module = String(label || '').split('·')[0].trim();
        const summaries = {
            M01: 'Datos de identificación en modo informativo.',
            M02: 'Antecedentes familiares pendientes de revisión.',
            M03: 'Evaluación de hábitos de consumo.',
            M04: 'Antecedentes quirúrgicos y temporalidad.',
            M05: 'Antecedentes personales y dependencias activas.',
            'M05-V2': 'Seguimiento de antecedentes con campos relacionados.',
            M06: 'Medicamentos, dosis, duración y respuesta.',
            M07: 'Medicamentos para el dolor agrupados por categoría.',
            M08: 'Tratamientos y resultados documentados.',
            M09: 'Evaluación actual del dolor y su evolución.',
        };
        return summaries[module] || 'Contexto activo de la entrevista.';
    };
    const setCurrentModule = label => {
        const activeLabel = label || 'Entrevista';
        if (currentModuleBadge) currentModuleBadge.textContent = activeLabel;
        if (contextModuleLabel) contextModuleLabel.textContent = activeLabel;
        if (contextModuleSummary) contextModuleSummary.textContent = contextSummaryFor(activeLabel);
        const moduleId = activeLabel.split('·')[0].trim();
        const m02Card = document.querySelector('[data-module="M02"]');
        const finalizationAction = m02Card?.querySelector('[data-m02-resolution-action]');
        const finalizationConfirmation = moduleId === 'M02'
            && conversationContract.modules?.M02?.conversation_state?.state === 'READY_TO_FINALIZE'
            && Boolean(finalizationAction && !finalizationAction.hidden)
            && !m02Card.hidden
            && !m02Card.classList.contains('chat-stage-hidden')
            && m02Card.dataset.m02Consultation !== '1';
        if (responseGuidanceTitle) responseGuidanceTitle.textContent = finalizationConfirmation ? 'Antes de continuar' : 'Cómo responder';
        if (responseGuidanceDescription) {
            const m02Input = m02Card?.querySelector('[data-m02-input]');
            const dictationAvailable = moduleId === 'M02'
                && main?.dataset.localDictationEnabled === '1'
                && localDictation?.supported() === true
                && m02Card?.dataset.m02WriteEnabled === '1'
                && m02Card.dataset.m02Finalized !== '1'
                && m02Card.dataset.m02Consultation !== '1'
                && m02Input && !m02Input.disabled && !m02Input.readOnly;
            responseGuidanceDescription.textContent = finalizationConfirmation
                ? 'Ya respondiste las preguntas de antecedentes familiares. Revisa la información y confirma cuando quieras finalizar esta sección.'
                : dictationAvailable
                    ? 'Una pregunta a la vez. Escribe o dicta tu respuesta y revísala antes de enviarla.'
                    : 'Una pregunta a la vez. Escribe tu respuesta y revísala antes de enviarla.';
        }
    };
    const hideConversationCards = () => { moduleCards.forEach(card => card.classList.add('chat-stage-hidden')); questions.forEach(question => { question.hidden = true; }); };
    const finalizeM02 = async card => {
        if (!card || card.dataset.m02Finalizing === '1') return;
        card.dataset.m02Finalizing = '1';
        const action = card.querySelector('[data-m02-resolution-action]');
        if (action) action.disabled = true;
        setStatus('Comprobando y finalizando antecedentes familiares…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m02', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), finalize: true})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado || data.finalized !== true || data.finalization_readback !== true) {
                throw new Error(data.mensaje || 'No fue posible confirmar el cierre de antecedentes familiares.');
            }
            setStatus('Antecedentes familiares finalizados. Continuando…');
            // El next_url de captura M02 pertenece al flujo legacy de formularios
            // tradicionales. Recargar esta misma ruta vuelve a resolver el primer
            // módulo/pregunta pendiente desde el contrato conversacional del servidor.
            window.location.reload();
        } catch (error) {
            setStatus(error.message || 'No fue posible finalizar antecedentes familiares.');
            if (action) action.disabled = false;
        } finally {
            card.dataset.m02Finalizing = '0';
        }
    };
    const exposeM02ResolutionAction = card => {
        if (!card || card.dataset.module !== 'M02') return;
        const finalizationText = String(conversationContract.modules?.M02?.conversation_state?.voice_text
            || 'Tus antecedentes familiares están registrados. ¿Deseas finalizar esta sección y continuar?');
        const questionHeader = card.querySelector('[data-m02-question-message]');
        const questionTitle = questionHeader?.querySelector('[data-m02-question-text]');
        const instruction = questionHeader?.querySelector('[data-m02-question-instruction]');
        if (questionTitle) {
            questionTitle.textContent = finalizationText;
            questionTitle.dataset.m02ActionId = 'M02:action:finalize';
            questionTitle.dataset.m02FinalizeHelpText = <?=$m02FinalizeHelpTextJson;?>;
        }
        if (instruction) instruction.hidden = true;
        if (questionHeader) questionHeader.hidden = false;
        const finalizationActions = card.querySelector('.m02-finalization-actions');
        if (finalizationActions) finalizationActions.hidden = false;
        const composer = card.querySelector('[data-m02-composer-shell]');
        if (composer) composer.hidden = true;
        const dictationStatus = card.querySelector('[data-m02-dictation-status]');
        if (dictationStatus) {
            dictationStatus.textContent = '';
            dictationStatus.hidden = true;
        }
        const result = card.querySelector('[data-m02-result]');
        if (result) {
            result.textContent = '';
            result.hidden = true;
        }
        let actionArea = card.querySelector('.m02-finalization-actions');
        if (!actionArea) {
            actionArea = document.createElement('div');
            actionArea.className = 'm02-finalization-actions';
            if (questionHeader) questionHeader.insertAdjacentElement('afterend', actionArea);
            else card.appendChild(actionArea);
        }
        actionArea.querySelectorAll('.patient-interview-message.assistant').forEach(message => message.remove());
        let action = card.querySelector('[data-m02-resolution-action]');
        if (!action) {
            action = document.createElement('button');
            action.type = 'button';
            action.dataset.m02ResolutionAction = '1';
            action.className = 'btn btn-primary';
            action.textContent = 'Finalizar antecedentes familiares y continuar';
            action.setAttribute('aria-label', 'Finalizar antecedentes familiares y continuar');
        }
        if (action.parentElement !== actionArea) actionArea.appendChild(action);
        if (action.dataset.m02ResolutionBound !== '1') {
            action.addEventListener('click', () => finalizeM02(card));
            action.dataset.m02ResolutionBound = '1';
        }
        action.hidden = false;
    };
    const showConversationStage = index => {
        conversationStage = Math.max(0, Math.min(index, conversationStages.length));
        if (typeof stopRecognition === 'function') stopRecognition();
        hideConversationCards();
        const stage = conversationStages[conversationStage];
        if (!stage) {
            setCurrentModule('Entrevista');
            setStatus('No hay otra etapa autorizada pendiente. Puedes consultar las respuestas anteriores.');
            updateProgress();
            return;
        }
        setCurrentModule(stageLabel(stage));
        if (stage.card) {
            const moduleId = stage.card.dataset.module || '';
            const resolver = stageConversationState(stage) || {};
            if (stage.card.dataset.module !== 'M02') {
                const target = voiceTargetForStage(stage) || `stage:${conversationStage}`;
                if (!target) {
                    setStatus(`No se pudo resolver la pregunta activa de ${moduleId}. Puedes continuar por escrito.`);
                    return;
                }
                conversationCoordinator.setTarget(target);
            }
            if (resolver.state === 'BLOCKED_PREREQUISITE'
                && (moduleId === 'M07' || stage.card.dataset.m07WriteEnabled !== '1')) {
                conversationCoordinator.markModuleBlocked(resolver.reason || 'MODULE_PREREQUISITE_BLOCKED');
            }
        }
        if (stage.type === 'm03') {
            const pending = questions.findIndex(question => !hasConfirmedAnswer(question) && question.dataset.eligible === '1');
            if (pending >= 0) showQuestion(pending);
            else { showConversationStage(nextPendingStage(conversationStage + 1)); return; }
        }
        else {
            stage.card.classList.remove('chat-stage-hidden');
            setCurrentModule(stageLabel(stage));
            const resolver = stage.card.dataset.module === 'M02' ? (stageConversationState(stage) || {}) : {};
            if (stage.card.dataset.module === 'M02' && resolver.state === 'READY_TO_FINALIZE') {
                const actionTarget = conversationCoordinator.setActionTarget(resolver.action_id || '');
                if (!actionTarget) {
                    conversationCoordinator.markModuleBlocked('M02_FINALIZATION_ACTION_UNRESOLVED');
                    setStatus('No se pudo resolver la acción de finalización de M02. La captura permanece protegida.');
                    updateProgress();
                    return;
                }
            }
            const mayAnswerM02 = !resolver.state || resolver.state === 'ANSWERABLE';
            const focusedM02 = stage.card.dataset.module === 'M02' && mayAnswerM02
                ? focusM02Pending(stage.card)
                : false;
            if (stage.card.dataset.module === 'M02' && !focusedM02) {
                const completion = stageCompletion(stage) || {};
                const hasPendingQuestion = Array.isArray(completion.pending_question_ids) && completion.pending_question_ids.length > 0;
                const requiresFinalization = resolver.state === 'READY_TO_FINALIZE'
                    || completion.resolution === 'AUTHORIZED_FINALIZATION_REQUIRED';
                const message = resolver.state === 'AWAITING_PROFESSIONAL_REVIEW'
                    ? 'M02 requiere revisión profesional antes de continuar. No se modificó ninguna respuesta.'
                    : requiresFinalization
                        ? 'M02 tiene respuestas documentadas y requiere una finalización expresa. No se cerró automáticamente.'
                        : hasPendingQuestion
                            ? 'M02 tiene una pregunta pendiente, pero no pudo resolverse su identificador canónico. La captura permanece bloqueada.'
                        : 'M02 no pudo resolver una pregunta activa por un bloqueo técnico. La captura permanece protegida.';
                if (requiresFinalization && !hasPendingQuestion) {
                    // This is an explicit, answerable finalization action, not
                    // a coordinator dead-end: keep requests enabled so the
                    // POST can transition through the normal save guard.
                    exposeM02ResolutionAction(stage.card);
                    setCurrentModule(stageLabel(stage));
                    setStatus(message);
                    window.setTimeout(() => { if (typeof bindVoiceControls === 'function') bindVoiceControls(); }, 0);
                    updateProgress();
                    return;
                }
                if (resolver.state === 'AWAITING_PROFESSIONAL_REVIEW') {
                    setStatus(message);
                    return;
                }
                conversationCoordinator.markModuleBlocked(completion.status === 'blocked' ? 'M02_CONTRACT_BLOCKED' : 'M02_ACTIVE_QUESTION_UNRESOLVED');
                setStatus(message);
                return;
            }
        }
        window.setTimeout(() => {
            if (typeof bindVoiceControls === 'function') bindVoiceControls();
            if (stage.card?.dataset.module === 'M02') syncM02DictationPermission(stage.card);
        }, 0);
        updateProgress();
    };
    const advanceConversation = () => {
        if (!conversationCoordinator.navigate('confirmed_backend_response')) { setStatus('La respuesta aún no está confirmada por el servidor; la pregunta permanece activa.'); return; }
        const currentCompletion = stageCompletion(conversationStages[conversationStage]);
        if (currentCompletion?.status === 'blocked') { setStatus(currentCompletion.message || 'Este módulo permanece bloqueado.'); return; }
        const nextStage = nextPendingStage(conversationStage + 1);
        if (nextStage < conversationStages.length) showConversationStage(nextStage);
        else { conversationCoordinator.transition('MODULE_COMPLETED', {reason: 'all_authorized_stages_reviewed'}); setStatus('Entrevista disponible para reanudar. No hay otra etapa autorizada pendiente.'); }
    };
    const m02Drafts = new Map();
    let m02ConsultReturnStage = null;
    const diagnoseM02DictationEligibility = (target, input) => {
        const snapshot = conversationCoordinator.snapshot();
        const card = input?.closest('[data-module="M02"]');
        const module = conversationContract.modules?.M02;
        const question = conversationCoordinator.questionContract(target);
        if (!target) return {allowed: false, code: 'QUESTION_TARGET_MISSING'};
        if (target !== snapshot.currentTarget) return {allowed: false, code: 'ACTIVE_QUESTION_ID_MISMATCH'};
        if (snapshot.paused) return {allowed: false, code: 'INTERVIEW_PAUSED'};
        if (snapshot.activeOperation) return {allowed: false, code: 'INTERVIEW_OPERATION_IN_PROGRESS'};
        if (!input?.matches('[data-m02-input]')) return {allowed: false, code: 'M02_INPUT_NOT_FOUND'};
        if (!card || card.hidden || card.closest('[hidden], .chat-stage-hidden')) return {allowed: false, code: 'M02_QUESTION_NOT_VISIBLE'};
        if (card.dataset.stableId !== target) return {allowed: false, code: 'VISIBLE_QUESTION_ID_MISMATCH'};
        if (card.dataset.m02Finalized === '1' || module?.state?.finalized === true) return {allowed: false, code: 'M02_FINALIZED'};
        if (card.dataset.m02Consultation === '1') return {allowed: false, code: 'M02_HISTORY_CONSULTATION'};
        if (input.disabled) return {allowed: false, code: 'QUESTION_INPUT_DISABLED'};
        if (input.readOnly) return {allowed: false, code: 'QUESTION_NOT_EDITABLE'};
        if (card.dataset.m02WriteEnabled !== '1') return {allowed: false, code: 'M02_WRITE_DISABLED_IN_VIEW'};
        if (!question || question.module_id !== 'M02') return {allowed: false, code: 'QUESTION_NOT_IN_M02_CONTRACT'};
        if (question.stable_id !== target) return {allowed: false, code: 'CONTRACT_QUESTION_ID_MISMATCH'};
        if (!module || module.conversation_state?.state !== 'ANSWERABLE') return {allowed: false, code: 'MODULE_NOT_ANSWERABLE'};
        if (module.state?.write_enabled !== true) return {allowed: false, code: 'MODULE_WRITE_DISABLED'};
        if (question.write_enabled !== true) return {allowed: false, code: 'QUESTION_WRITE_DISABLED'};
        const response = question.response || {};
        if (response.status === 'pending' && module.conversation_state?.stable_id !== target) {
            return {allowed: false, code: 'PENDING_QUESTION_NOT_RESOLVER_ACTIVE'};
        }
        if (response.status === 'documented' && response.persisted !== true) {
            return {allowed: false, code: 'SAVED_QUESTION_NOT_PERSISTED'};
        }
        if (!PatientInterviewLocalDictation.isM02DictationContractEligible(target, question, module)) {
            return {allowed: false, code: 'M02_QUESTION_CONTRACT_INELIGIBLE'};
        }
        return {allowed: true, code: 'AUTHORIZED'};
    };
    const diagnoseM03DictationEligibility = (target, input) => {
        const snapshot = conversationCoordinator.snapshot();
        const card = input?.closest('.patient-interview-question[data-question-index]');
        const module = conversationContract.modules?.M03;
        const question = conversationCoordinator.questionContract(target);
        if (!target) return {allowed: false, code: 'QUESTION_TARGET_MISSING'};
        const authorizedPreviousEdit = m03PreviousEditOnly
            && target === m03ReviewTarget
            && card === questions[active]
            && hasM03HistoricalEditAuthorization(card, m03ReviewReturnIndex);
        if (target !== snapshot.currentTarget && !authorizedPreviousEdit) return {allowed: false, code: 'ACTIVE_QUESTION_ID_MISMATCH'};
        if (snapshot.paused) return {allowed: false, code: 'INTERVIEW_PAUSED'};
        if (snapshot.activeOperation) return {allowed: false, code: 'INTERVIEW_OPERATION_IN_PROGRESS'};
        if (conversationStages[conversationStage]?.type !== 'm03') return {allowed: false, code: 'M03_MODULE_INACTIVE'};
        if (!input?.matches('.m03-input-wrap > .interview-text')) return {allowed: false, code: 'M03_INPUT_NOT_FOUND'};
        if (!card || card.hidden || card.closest('[hidden], .chat-stage-hidden')) return {allowed: false, code: 'M03_QUESTION_NOT_VISIBLE'};
        if (card.dataset.m03Saving === '1' || card.dataset.m03Interpreting === '1') return {allowed: false, code: 'INTERVIEW_OPERATION_IN_PROGRESS'};
        const questionId = String(card.dataset.questionId || '');
        if (!/^[1-9]\d*$/.test(questionId) || target !== `M03:question:${questionId}`) return {allowed: false, code: 'VISIBLE_QUESTION_ID_MISMATCH'};
        if (module?.state?.finalized === true) return {allowed: false, code: 'M03_FINALIZED'};
        if (card.dataset.m03WriteEnabled !== '1' || module?.state?.write_enabled !== true) return {allowed: false, code: 'M03_WRITE_DISABLED'};
        if (input.disabled) return {allowed: false, code: 'QUESTION_INPUT_DISABLED'};
        if (input.readOnly) return {allowed: false, code: 'QUESTION_NOT_EDITABLE'};
        if (!question || question.module_id !== 'M03') return {allowed: false, code: 'QUESTION_NOT_IN_M03_CONTRACT'};
        if (question.stable_id !== target || String(question.question_id) !== questionId) return {allowed: false, code: 'CONTRACT_QUESTION_ID_MISMATCH'};
        if (!module || module.conversation_state?.state !== 'ANSWERABLE') return {allowed: false, code: 'MODULE_NOT_ANSWERABLE'};
        if (module.conversation_state?.stable_id !== target && !authorizedPreviousEdit) return {allowed: false, code: 'M03_ACTIVE_TARGET_STALE'};
        if (card.dataset.eligible !== '1') return {allowed: false, code: 'M03_QUESTION_NOT_ELIGIBLE'};
        if (question.response?.eligible !== true) return {allowed: false, code: 'M03_CONTRACT_ELIGIBILITY_STALE'};
        if (question.write_enabled !== true) return {allowed: false, code: 'QUESTION_WRITE_DISABLED'};
        const response = question.response || {};
        if (response.status !== 'pending' && !(response.status === 'documented' && response.persisted === true)) {
            return {allowed: false, code: 'M03_RESPONSE_NOT_DICTATABLE'};
        }
        if (typeof window.PatientInterviewLocalDictation?.isM03DictationContractEligible !== 'function') {
            return {allowed: false, code: 'DICTATION_CLIENT_NOT_INITIALIZED'};
        }
        if (!PatientInterviewLocalDictation.isM03DictationContractEligible(target, question, module)) return {allowed: false, code: 'M03_QUESTION_CONTRACT_INELIGIBLE'};
        return {allowed: true, code: 'AUTHORIZED'};
    };
    const syncM02DictationPermission = card => {
        const input = card?.querySelector('[data-m02-input]');
        const module = conversationContract.modules?.M02;
        if (module?.conversation_state?.state === 'READY_TO_FINALIZE') {
            if (typeof stopRecognition === 'function') stopRecognition();
            if (main) main.dataset.sttState = 'IDLE';
            const inlineStatus = card?.querySelector('[data-m02-dictation-status]');
            if (inlineStatus) {
                inlineStatus.textContent = '';
                inlineStatus.hidden = true;
                inlineStatus.dataset.sttState = 'IDLE';
            }
            if (status) {
                status.textContent = '';
                status.hidden = true;
                status.removeAttribute('data-stt-state');
            }
            return;
        }
        const target = conversationCoordinator.snapshot().currentTarget;
        const eligibility = diagnoseM02DictationEligibility(target, input);
        const canDictate = eligibility.allowed;
        const unavailableReason = !input || input.disabled || input.readOnly
            ? 'Esta pregunta es de solo lectura; puedes consultarla, pero no dictarla.'
            : card?.dataset.m02Finalized === '1' || module?.state?.finalized === true
                ? 'M02 está finalizado. Las respuestas históricas no se pueden reabrir.'
                : card?.dataset.m02Consultation === '1'
                    ? 'Estás consultando respuestas previas. Vuelve a una pregunta editable para dictar.'
                    : module?.conversation_state?.state !== 'ANSWERABLE' || module?.state?.write_enabled !== true
                        ? 'M02 no tiene una pregunta editable activa en este momento.'
                        : 'Esta no es la pregunta M02 activa. Selecciona la pregunta vigente para dictar.';
        card?.querySelectorAll('.chat-mic-button, .chat-mic-unavailable, .chat-dictation-button, [data-dictation-button]').forEach(button => {
            // Keep the original microphone affordance visible, but expose it
            // as actionable only when startRecognition's complete guard passes.
            button.hidden = false;
            const unavailable = button.matches('.chat-mic-unavailable') || !canDictate;
            button.disabled = unavailable;
            button.setAttribute('aria-disabled', unavailable ? 'true' : 'false');
            if (unavailable) {
                button.title = button.matches('.chat-mic-unavailable')
                    ? (button.title || 'El dictado local no está disponible en este navegador o entorno.')
                    : unavailableReason;
                button.setAttribute('aria-label', button.matches('.chat-mic-unavailable')
                    ? 'Dictado no disponible. ' + button.title
                    : 'Dictado deshabilitado. ' + unavailableReason);
            } else {
                button.title = 'Dictado local en español';
                button.setAttribute('aria-label', 'Iniciar dictado');
            }
        });
        if (!canDictate && main.dataset.m02DictationDiagnostics === '1'
            && card && !card.hidden && !card.closest('[hidden], .chat-stage-hidden')) {
            setRecognitionStatus('UNAVAILABLE', `M02_DICTATION_BLOCKED:${eligibility.code}`);
        }
    };
    const m02SourcesFor = card => [...(card?.querySelectorAll('template[data-m02-question-source]') || [])];
    const m02SourceForStableId = (card, stableId) => m02SourcesFor(card).find(source => source.dataset.stableId === stableId) || null;
    const m02PendingQuestion = () => {
        const pendingIds = conversationContract.modules?.M02?.completion?.pending_question_ids;
        if (!Array.isArray(pendingIds)) return null;
        return pendingIds.map(stableId => conversationContract.question_index?.[stableId])
            .find(question => question?.module_id === 'M02' && question.stable_id) || null;
    };
    const m02PendingSource = card => {
        const pending = m02PendingQuestion();
        return pending ? m02SourceForStableId(card, pending.stable_id) : null;
    };
    const m02CurrentSource = card => m02SourceForStableId(card, card?.dataset.stableId || '');
    const updateM02AnswerCount = (card, input = card?.querySelector('[data-m02-input]')) => {
        const count = card?.querySelector('[data-answer-count]');
        if (count && input) count.textContent = `${input.value.length}/2000`;
    };
    const applyM02Source = (card, source, {preserveDraft = true} = {}) => {
        if (!card || !source) return false;
        const stableId = String(source.dataset.stableId || '');
        const canonical = conversationContract.question_index?.[stableId];
        if (!stableId || canonical?.module_id !== 'M02' || canonical.stable_id !== stableId) return false;
        const input = card.querySelector('[data-m02-input]');
        const previousStableId = card.dataset.stableId || '';
        if (preserveDraft && input && previousStableId && previousStableId !== stableId) {
            m02Drafts.set(previousStableId, input.value);
        }
        if (previousStableId !== stableId) {
            if (typeof stopAssistantVoice === 'function') stopAssistantVoice();
            if (typeof stopRecognition === 'function') stopRecognition();
        }
        card.dataset.stableId = stableId;
        card.dataset.m02Disease = source.dataset.m02Disease || String(canonical.question_id || '');
        card.dataset.m02Version = source.dataset.m02Version || '';
        card.dataset.m02CurrentDisease = card.dataset.m02Disease;
        const title = card.querySelector('[data-m02-question-text]');
        const instruction = card.querySelector('[data-m02-question-instruction]');
        const label = card.querySelector('[data-m02-question-label]');
        const message = card.querySelector('[data-m02-question-message]');
        const composer = card.querySelector('[data-m02-composer-shell]');
        const result = card.querySelector('[data-m02-result]');
        const finalizationActions = card.querySelector('.m02-finalization-actions');
        if (title) {
            title.textContent = source.dataset.m02QuestionText || canonical.visible_text || '';
            title.removeAttribute('data-m02-action-id');
            delete title.dataset.m02FinalizeHelpText;
        }
        if (instruction) {
            instruction.textContent = source.dataset.m02Instruction || canonical.instruction || 'Responde la pregunta.';
            instruction.hidden = false;
        }
        if (label) label.textContent = card.dataset.m02Disease;
        if (message) message.hidden = false;
        if (finalizationActions) finalizationActions.hidden = true;
        if (composer) composer.hidden = false;
        if (result) { result.textContent = ''; result.hidden = true; }
        card.removeAttribute('data-m02-context');
        card.removeAttribute('data-m02-proposal');
        const canWrite = card.dataset.m02Finalized !== '1'
            && card.dataset.m02WriteEnabled === '1'
            && source.dataset.m02Editable === '1';
        const saved = source.dataset.m02Documented === '1' ? (source.dataset.m02Answer || '') : '';
        const nextValue = m02Drafts.has(stableId) ? m02Drafts.get(stableId) : saved;
        if (input) {
            input.value = nextValue;
            input.readOnly = !canWrite;
            input.disabled = false;
            input.dataset.m02LastInput = nextValue;
            updateM02AnswerCount(card, input);
        }
        const submit = card.querySelector('[data-m02-interpret]');
        if (submit) { submit.disabled = !canWrite; submit.hidden = false; }
        const safeNo = card.querySelector('[data-m02-safe-no]');
        const isPending = source === m02PendingSource(card);
        if (safeNo) {
            safeNo.hidden = !canWrite || !isPending;
            safeNo.disabled = !canWrite || !isPending;
        }
        card.querySelectorAll('.chat-mic-button, .chat-mic-unavailable, .chat-dictation-button, [data-dictation-button]').forEach(button => {
            button.disabled = !canWrite;
            button.setAttribute('aria-disabled', canWrite ? 'false' : 'true');
        });
        conversationCoordinator.setTarget(stableId);
        const composerInput = card.querySelector('[data-m02-input]');
        if (composerInput && canWrite) composerInput.removeAttribute('aria-readonly');
        else composerInput?.setAttribute('aria-readonly', 'true');
        return true;
    };
    const m02DocumentedSources = card => m02SourcesFor(card).filter(source => source.dataset.m02Documented === '1');
    const focusM02Pending = card => {
        const pending = m02PendingSource(card);
        if (!pending) return false;
        card.dataset.m02Consultation = '0';
        const focused = applyM02Source(card, pending, {preserveDraft: false});
        if (focused) syncM02Navigation(card);
        return focused;
    };
    const syncM02Navigation = card => {
        if (!card) return;
        const sources = m02SourcesFor(card);
        const current = m02CurrentSource(card);
        const currentIndex = sources.indexOf(current);
        const pending = m02PendingSource(card);
        const pendingIndex = sources.indexOf(pending);
        const isConsulting = card.dataset.m02Consultation === '1';
        const finalizedConsultation = isConsulting && card.dataset.m02Finalized === '1';
        const previous = currentIndex > 0
            ? sources.slice(0, currentIndex).filter(source => source.dataset.m02Documented === '1').slice(-1)[0] || null
            : null;
        const next = finalizedConsultation
            ? sources.slice(currentIndex + 1).find(source => source.dataset.m02Documented === '1') || null
            : currentIndex >= 0 && pendingIndex >= 0 && currentIndex < pendingIndex
                ? sources.slice(currentIndex + 1, pendingIndex).find(source => source.dataset.m02Documented === '1') || null
                : null;
        const returnButton = card.querySelector('[data-m02-return-current]');
        const previousButton = card.querySelector('[data-m02-previous]');
        const nextButton = card.querySelector('[data-m02-next]');
        if (previousButton) { previousButton.hidden = !previous; previousButton.disabled = !previous; }
        if (nextButton) { nextButton.hidden = isConsulting || !next; nextButton.disabled = isConsulting || !next; }
        if (returnButton) {
            returnButton.hidden = isConsulting ? false : !pending || current === pending;
            returnButton.disabled = returnButton.hidden;
            returnButton.textContent = isConsulting ? 'Volver a respuestas previas' : '→ Volver a pregunta actual';
        }
    };
    const showM02Source = (card, source) => {
        if (!card || !source || card.dataset.m02Saving === '1' || card.dataset.m02Interpreting === '1') return false;
        if (conversationCoordinator.snapshot().activeOperation) {
            setStatus('Espera a que termine la operación actual antes de cambiar de pregunta.');
            return false;
        }
        const stableId = source.dataset.stableId || '';
        if (conversationContract.question_index?.[stableId]?.module_id !== 'M02') {
            setStatus('No se pudo resolver la identidad canónica de la pregunta. M02 permanece sin cambios.');
            return false;
        }
        if (typeof stopAssistantVoice === 'function') stopAssistantVoice();
        if (typeof stopRecognition === 'function') stopRecognition();
        if (card.dataset.m02Consultation !== '1' && !conversationCoordinator.goBack()) {
            setStatus('No se puede cambiar de pregunta mientras hay una operación pendiente.');
            return false;
        }
        const changed = applyM02Source(card, source);
        if (!changed) return false;
        setCurrentModule('M02 · Antecedentes heredofamiliares');
        syncM02Navigation(card);
        window.setTimeout(() => {
            if (typeof bindVoiceControls === 'function') bindVoiceControls();
            syncM02DictationPermission(card);
        }, 0);
        setStatus('');
        return true;
    };
    const closeM02Consultation = card => {
        const returnStage = m02ConsultReturnStage;
        m02ConsultReturnStage = null;
        if (typeof stopAssistantVoice === 'function') stopAssistantVoice();
        if (typeof stopRecognition === 'function') stopRecognition();
        card.dataset.m02Consultation = '0';
        card.classList.add('chat-stage-hidden');
        if (Number.isInteger(returnStage)) showConversationStage(returnStage);
    };
    document.querySelectorAll('[data-module="M02"]').forEach(card => {
        card.querySelector('[data-m02-previous]')?.addEventListener('click', () => {
            const sources = m02SourcesFor(card);
            const currentIndex = sources.indexOf(m02CurrentSource(card));
            const previous = currentIndex > 0 ? sources.slice(0, currentIndex).filter(source => source.dataset.m02Documented === '1').slice(-1)[0] : null;
            if (previous) showM02Source(card, previous);
        });
        card.querySelector('[data-m02-next]')?.addEventListener('click', () => {
            const sources = m02SourcesFor(card);
            const currentIndex = sources.indexOf(m02CurrentSource(card));
            const pendingIndex = sources.indexOf(m02PendingSource(card));
            const finalizedConsultation = card.dataset.m02Consultation === '1' && card.dataset.m02Finalized === '1';
            const next = finalizedConsultation
                ? sources.slice(currentIndex + 1).find(source => source.dataset.m02Documented === '1') || null
                : currentIndex >= 0 && pendingIndex >= 0
                    ? sources.slice(currentIndex + 1, pendingIndex).find(source => source.dataset.m02Documented === '1') || null
                    : null;
            if (next) showM02Source(card, next);
        });
        card.querySelector('[data-m02-return-current]')?.addEventListener('click', () => {
            if (card.dataset.m02Consultation === '1') { closeM02Consultation(card); return; }
            const pending = m02PendingSource(card);
            if (pending) showM02Source(card, pending);
        });
    });
    const advanceM02 = (row, finalized = false) => { const current = row; if (current) current.dataset.chatDone = '1'; if (finalized === true) { advanceConversation(); return; } window.location.reload(); };
    const historyButton = document.querySelector('[data-chat-history]');
    const historyPanel = document.querySelector('[data-chat-history-panel]');
    const historyContent = document.querySelector('[data-chat-history-content]');
    const renderRegisteredAnswers = () => {
        if (!historyContent) return;
        historyContent.textContent = '';
        const savedQuestions = questions.filter(hasConfirmedAnswer);
        const m02Card = document.querySelector('[data-module="M02"]');
        const m02HistorySources = m02Card ? m02DocumentedSources(m02Card) : [];
        const m06HistoryEntries = [];
        const m06Card = document.querySelector('[data-module="M06"]');
        if (m06Card) {
            try {
                const omissionsByRecord = JSON.parse(m06Card.dataset.m06OmissionsByRecord || '{}');
                Object.entries(omissionsByRecord || {}).forEach(([recordId, omissions]) => {
                    Object.entries(omissions || {}).forEach(([field, state]) => {
                        if (m06OmissionLabels[field] && ['UNKNOWN', 'SKIPPED'].includes(state)) m06HistoryEntries.push({recordId, field, state});
                    });
                });
            } catch (error) { /* Invalid optional history data must not affect the active question. */ }
        }
        if (!savedQuestions.length && !m02HistorySources.length && !m06HistoryEntries.length) {
            const empty = document.createElement('p');
            empty.className = 'chat-history-empty';
            empty.textContent = 'No hay respuestas registradas para consultar.';
            historyContent.appendChild(empty);
            return;
        }
        const list = document.createElement('ul');
        list.className = 'chat-history-list';
        if (m02HistorySources.length) {
            const item = document.createElement('li');
            item.className = 'chat-history-item';
            const summary = document.createElement('span');
            summary.textContent = `M02 · ${m02HistorySources.length} ${m02HistorySources.length === 1 ? 'respuesta disponible' : 'respuestas disponibles'}`;
            const consult = document.createElement('button');
            consult.type = 'button';
            consult.className = 'btn btn-outline-primary btn-sm ms-2';
            consult.textContent = 'Consultar preguntas M02';
            consult.addEventListener('click', () => {
                if (!m02Card || conversationCoordinator.snapshot().activeOperation) return;
                if (typeof stopAssistantVoice === 'function') stopAssistantVoice();
                if (typeof stopRecognition === 'function') stopRecognition();
                m02ConsultReturnStage = conversationStage;
                hideConversationCards();
                m02Card.dataset.m02Consultation = '1';
                m02Card.classList.remove('chat-stage-hidden');
                const source = m02HistorySources[m02HistorySources.length - 1];
                if (!applyM02Source(m02Card, source)) {
                    m02Card.classList.add('chat-stage-hidden');
                    m02Card.dataset.m02Consultation = '0';
                    return;
                }
                setCurrentModule('M02 · Antecedentes heredofamiliares');
                syncM02Navigation(m02Card);
                if (historyPanel) historyPanel.hidden = true;
                window.setTimeout(() => {
                    if (typeof bindVoiceControls === 'function') bindVoiceControls();
                    syncM02DictationPermission(m02Card);
                }, 0);
                setStatus('');
            });
            item.append(summary, consult);
            list.appendChild(item);
        }
        savedQuestions.forEach(question => {
            const item = document.createElement('li');
            item.className = 'chat-history-item';
            const details = document.createElement('dl');
            const module = document.createElement('dt');
            module.textContent = question.querySelector('.interview-message-header strong')?.textContent.trim() || 'M03';
            const prompt = document.createElement('dt');
            prompt.textContent = 'Pregunta';
            const promptValue = document.createElement('dd');
            promptValue.textContent = question.querySelector('.interview-question-text')?.textContent.trim() || 'Pregunta clínica';
            const answer = document.createElement('dt');
            answer.textContent = 'Respuesta guardada';
            const answerValue = document.createElement('dd');
            answerValue.textContent = answerLabel(question);
            details.append(module, prompt, promptValue, answer, answerValue);
            item.appendChild(details);
            list.appendChild(item);
        });
        m06HistoryEntries.forEach(({recordId, field, state}) => {
            const item = document.createElement('li');
            item.className = 'chat-history-item';
            const label = document.createElement('strong');
            label.textContent = `Medicamento M06 · ${m06OmissionLabels[field]}`;
            const status = document.createElement('span');
            status.className = 'patient-interview-muted d-block';
            status.textContent = state === 'UNKNOWN' ? 'No lo sé' : 'Dato omitido';
            const revisit = document.createElement('button');
            revisit.type = 'button';
            revisit.className = 'btn btn-outline-secondary btn-sm mt-2';
            revisit.dataset.m06Revisit = field;
            revisit.dataset.m06RecordId = recordId;
            revisit.textContent = `Completar ${m06OmissionLabels[field]}`;
            item.append(label, status, revisit);
            list.appendChild(item);
        });
        historyContent.appendChild(list);
    };
    if (historyButton && historyPanel) historyButton.addEventListener('click', () => { historyPanel.hidden = !historyPanel.hidden; if (!historyPanel.hidden) renderRegisteredAnswers(); });
    const updateProgress = () => {
        const current = Math.min(conversationStage + 1, conversationStages.length || 1);
        const total = conversationStages.length || 1;
        const percentage = conversationStages.length ? (current / total) * 100 : 0;
        if (progress) progress.textContent = `Etapa ${current} de ${total}`;
        if (progressBar) progressBar.style.width = `${percentage}%`;
        const percentageText = document.getElementById('interview-progress-percentage');
        if (percentageText) percentageText.textContent = `${percentage} %`;
        if (progressMeter) {
            progressMeter.setAttribute('aria-valuemax', String(total));
            progressMeter.setAttribute('aria-valuenow', String(current));
            progressMeter.setAttribute('aria-valuetext', `Etapa ${current} de ${total}`);
        }
    };
    const hasM03HistoricalEditAuthorization = (question, returnIndex) => {
        const module = conversationContract.modules?.M03;
        const target = question ? `M03:question:${question.dataset.questionId || ''}` : '';
        const returnQuestion = Number.isInteger(returnIndex) ? questions[returnIndex] : null;
        const pendingTarget = returnQuestion ? `M03:question:${returnQuestion.dataset.questionId || ''}` : '';
        const currentQuestion = pendingTarget ? conversationCoordinator.questionContract(pendingTarget) : null;
        const candidateContract = target ? conversationCoordinator.questionContract(target) : null;
        if (!question
            || question.dataset.eligible !== '1'
            || candidateContract?.response?.status !== 'documented'
            || candidateContract?.response?.persisted !== true
            || candidateContract?.response?.editable !== true
            || !module || module.state?.write_enabled !== true || module.state?.finalized === true
            || module.conversation_state?.state !== 'ANSWERABLE'
            || module.conversation_state?.stable_id !== pendingTarget
            || currentQuestion?.response?.status !== 'pending'
            || currentQuestion?.response?.persisted === true) return false;
        const eligibleQuestions = (module.questions || []).filter(item => item?.response?.eligible === true);
        const pendingIndex = eligibleQuestions.findIndex(item => item.stable_id === pendingTarget
            && item.response?.status === 'pending' && item.response?.persisted !== true);
        const targetIndex = eligibleQuestions.findIndex(item => item.stable_id === target
            && item.response?.status === 'documented' && item.response?.persisted === true);
        if (pendingIndex < 1 || targetIndex < 0 || targetIndex >= pendingIndex) return false;
        return eligibleQuestions.slice(targetIndex + 1, pendingIndex).every(item =>
            item.response?.status === 'documented' && item.response?.persisted === true);
    };
    const showQuestion = (index, options = {}) => {
        const candidate = questions[index];
        if (candidate && candidate.dataset.eligible === '0' && !hasConfirmedAnswer(candidate)) {
            return false;
        }
        const reviewOnly = options.mode === 'review';
        const previousEditOnly = options.mode === 'direct-edit';
        const candidateTarget = candidate ? `M03:question:${candidate.dataset.questionId || index}` : '';
        const module = conversationContract.modules?.M03;
        const candidateContract = conversationCoordinator.questionContract(candidateTarget);
        const returnQuestion = Number.isInteger(options.returnIndex) ? questions[options.returnIndex] : null;
        const returnTarget = returnQuestion ? `M03:question:${returnQuestion.dataset.questionId || ''}` : '';
        const validReturnTarget = Boolean(returnQuestion
            && module?.conversation_state?.stable_id === returnTarget
            && module.conversation_state.state === 'ANSWERABLE'
            && module.state?.write_enabled === true
            && module.state?.finalized !== true);
        if (reviewOnly && (!candidate
            || !hasConfirmedAnswer(candidate)
            || candidate.dataset.eligible !== '1'
            || candidateContract?.response?.status !== 'documented'
            || candidateContract?.response?.persisted !== true
            || module?.conversation_state?.stable_id === candidateTarget
            || !validReturnTarget)) {
            return false;
        }
        if (previousEditOnly && (!validReturnTarget || !hasM03HistoricalEditAuthorization(candidate, options.returnIndex))) return false;
        const previousQuestion = questions[active];
        if (options.discardDraft === true && previousQuestion && previousQuestion !== candidate) {
            const previousInput = previousQuestion.querySelector('.interview-text');
            if (previousInput) {
                previousInput.value = String(previousQuestion.dataset.savedValue || '');
                updateM03AnswerCount(previousQuestion);
            }
        }
        active = index;
        m03QuestionViewRevision += 1;
        m03ReviewOnly = reviewOnly;
        m03PreviousEditOnly = previousEditOnly;
        m03ReviewReturnIndex = reviewOnly ? options.returnIndex : null;
        if (previousEditOnly) m03ReviewReturnIndex = options.returnIndex;
        m03ReviewTarget = reviewOnly || previousEditOnly ? candidateTarget : '';
        if (typeof stopAssistantVoice === 'function') stopAssistantVoice();
        if (typeof stopRecognition === 'function') stopRecognition();
        const question = questions[active];
        setCurrentModule(question ? `M03 · ${question.dataset.themeName || 'Pregunta activa'}` : 'M03');
        if (question) {
            questions.forEach(card => { delete card.dataset.m03ReviewOnly; });
            questions.forEach(card => { delete card.dataset.m03PreviousEdit; });
            if (reviewOnly) question.dataset.m03ReviewOnly = '1';
            else if (previousEditOnly) question.dataset.m03PreviousEdit = '1';
            else {
                conversationCoordinator.setTarget(`M03:question:${question.dataset.questionId || active}`);
            }
        }
        if ((previousEditOnly || options.discardDraft === true) && question) {
            const input = question.querySelector('.interview-text');
            if (input) {
                input.value = String(question.dataset.savedValue || '');
                updateM03AnswerCount(question);
            }
        }
        const responseZone = question?.querySelector('[data-interview-zone="response"]');
        const canEditQuestion = question?.dataset.m03WriteEnabled === '1' && question?.dataset.eligible === '1';
        if (responseZone) {
            responseZone.hidden = reviewOnly || !canEditQuestion;
            if (reviewOnly) {
                responseZone.querySelectorAll('input, button, select, textarea').forEach(control => { control.disabled = true; });
            } else if (canEditQuestion && question.dataset.m03Saving !== '1' && question.dataset.m03Interpreting !== '1') {
                responseZone.querySelectorAll('input, button, select, textarea').forEach(control => { control.disabled = false; });
            }
        }
        moduleCards.forEach(card => card.classList.add('chat-stage-hidden'));
        questions.forEach((question, questionIndex) => { question.hidden = questionIndex !== active; });
        if (conversationStages[conversationStage]?.type === 'm03') questions.forEach(question => { question.hidden = questions.indexOf(question) !== active; });
        syncM03ContextualActions();
        const composer = questions[active]?.querySelector('.m03-composer');
        if (composer) composer.hidden = reviewOnly;
        if (reviewOnly || previousEditOnly) {
            const confirmed = question?.querySelector('[data-confirmed-message]');
            if (confirmed) confirmed.hidden = !reviewOnly;
        }
        window.setTimeout(() => { if (typeof bindVoiceControls === 'function') bindVoiceControls(); }, 0);
        updateProgress();
        updateM03PreviousButton();
        if (options.narrateQuestion === true) {
            speakM03VisibleQuestion(question, candidateTarget, {
                userGesture: options.userGesture === true,
                allowCurrentPending: options.allowCurrentPending === true,
            });
        }
        return Boolean(question);
    };
    const valueFor = question => {
        const input = question.querySelector('.interview-text');
        return input ? input.value.trim() : '';
    };
    const updateM03AnswerCount = question => {
        const input = question?.querySelector('.interview-text');
        const counter = question?.querySelector('[data-answer-count]');
        if (input && counter) counter.textContent = `${input.value.length}/2000`;
    };
    const isDirectNumericValue = value => /^\d+(?:[.,]\d+)?$/.test(String(value || '').trim());
    const directDurationPattern = /^(?:hace|desde hace|durante)\s+(?:\d+(?:[.,]\d+)?|uno|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce)\s+(?:años?|mes(?:es)?|semanas?|días?)\.?$/i;
    const directModeForM03 = question => {
        if (question?.dataset.m03DirectCapable !== '1') return null;
        const value = valueFor(question);
        if (isDirectNumericValue(value)) return 'numeric';
        return directDurationPattern.test(value) ? 'literal' : null;
    };
    const isDirectM03Candidate = question => directModeForM03(question) !== null;
    const updateM03SubmitAffordance = question => {
        if (!question) return;
        const button = question.querySelector('.interview-submit');
        if (!button) return;
        const direct = isDirectM03Candidate(question);
        button.setAttribute('aria-label', direct ? 'Guardar y continuar' : 'Interpretar respuesta');
        button.title = direct ? 'Guardar esta respuesta y continuar a la siguiente pregunta' : 'Interpretar respuesta';
        button.dataset.directMode = direct ? '1' : '0';
    };
    const updateM03PreviousButton = () => {
        const current = questions[active];
        const module = conversationContract.modules?.M03;
        const serverTarget = module?.conversation_state?.stable_id || '';
        const historicalMode = m03ReviewOnly || m03PreviousEditOnly;
        const boundaryIndex = historicalMode && Number.isInteger(m03ReviewReturnIndex)
            ? m03ReviewReturnIndex
            : active;
        const moduleWritable = conversationStages[conversationStage]?.type === 'm03'
            && current?.dataset.m03WriteEnabled === '1'
            && module?.state?.write_enabled === true
            && module?.state?.finalized !== true
            && module?.conversation_state?.state === 'ANSWERABLE'
            && serverTarget === `M03:question:${questions[boundaryIndex]?.dataset.questionId || ''}`;
        const isPersistedEligible = question => question?.dataset.eligible === '1'
            && hasConfirmedAnswer(question)
            && conversationCoordinator.questionContract(`M03:question:${question.dataset.questionId || ''}`)?.response?.status === 'documented'
            && conversationCoordinator.questionContract(`M03:question:${question.dataset.questionId || ''}`)?.response?.persisted === true;
        const previousIndex = moduleWritable
            ? [...questions].map((question, index) => ({question, index}))
                .filter(item => item.index < active && item.index < boundaryIndex && isPersistedEligible(item.question))
                .pop()?.index
            : undefined;
        const nextHistoryIndex = historicalMode && moduleWritable
            ? questions.findIndex((question, index) => index > active && index < boundaryIndex && isPersistedEligible(question))
            : -1;
        const previousAvailable = moduleWritable && previousIndex !== undefined;
        const nextAvailable = historicalMode && moduleWritable
            && (nextHistoryIndex >= 0 || active < boundaryIndex);
        document.querySelectorAll('[data-m03-previous]').forEach(button => {
            const owner = button.closest('.patient-interview-question');
            const visible = previousAvailable && owner === current;
            button.hidden = !visible;
            button.disabled = !visible;
            button.setAttribute('aria-label', 'Ir a la pregunta anterior');
        });
        document.querySelectorAll('[data-m03-next]').forEach(button => {
            const owner = button.closest('.patient-interview-question');
            const visible = nextAvailable && owner === current;
            button.hidden = !visible;
            button.disabled = !visible;
            button.setAttribute('aria-label', 'Ir a la pregunta siguiente');
        });
    };
    const navigateM03Previous = button => {
        if (conversationStages[conversationStage]?.type !== 'm03'
            || conversationCoordinator.snapshot().activeOperation) return false;
        const module = conversationContract.modules?.M03;
        const serverTarget = module?.conversation_state?.stable_id || '';
        if (m03ReviewOnly || m03PreviousEditOnly) {
            const returnIndex = m03ReviewReturnIndex;
            const returnQuestion = Number.isInteger(returnIndex) ? questions[returnIndex] : null;
            if (!returnQuestion
                || serverTarget !== `M03:question:${returnQuestion.dataset.questionId || ''}`
                || module?.conversation_state?.state !== 'ANSWERABLE'
                || module?.state?.write_enabled !== true
                || module?.state?.finalized === true) return false;
            for (let index = active - 1; index >= 0; index -= 1) {
                const candidate = questions[index];
                if (index >= returnIndex || !isPersistedEligibleM03Question(candidate)) continue;
                const mode = hasM03HistoricalEditAuthorization(candidate, returnIndex) ? 'direct-edit' : 'review';
                conversationCoordinator.invalidate('history_previous_question');
                return showQuestion(index, {mode, returnIndex, discardDraft: true, narrateQuestion: true, userGesture: true});
            }
            return false;
        }
        const current = questions[active];
        if (!current || serverTarget !== `M03:question:${current.dataset.questionId || ''}`
            || module?.conversation_state?.state !== 'ANSWERABLE'
            || module?.state?.write_enabled !== true
            || module?.state?.finalized === true) return false;
        const previousIndex = [...questions].map((question, index) => ({question, index}))
            .filter(item => item.index < active && item.question.dataset.eligible === '1' && hasConfirmedAnswer(item.question))
            .pop()?.index;
        const previous = previousIndex === undefined ? null : questions[previousIndex];
        const previousTarget = previous ? `M03:question:${previous.dataset.questionId || ''}` : '';
        const previousContract = previous ? conversationCoordinator.questionContract(previousTarget) : null;
        if (!previous
            || previous.dataset.m03WriteEnabled !== '1'
            || previousContract?.response?.status !== 'documented'
            || previousContract?.response?.persisted !== true) return false;
        const previousEditAllowed = hasM03HistoricalEditAuthorization(previous, active);
        conversationCoordinator.invalidate(previousEditAllowed ? 'previous_question_edit' : 'previous_question_review');
        setStatus('');
        return showQuestion(previousIndex, {mode: previousEditAllowed ? 'direct-edit' : 'review', returnIndex: active, discardDraft: true, narrateQuestion: true, userGesture: true});
    };
    const isPersistedEligibleM03Question = question => {
        if (!question || question.dataset.eligible !== '1' || !hasConfirmedAnswer(question)) return false;
        const contract = conversationCoordinator.questionContract(`M03:question:${question.dataset.questionId || ''}`);
        return contract?.response?.status === 'documented' && contract.response.persisted === true;
    };
    const navigateM03Next = button => {
        if (conversationStages[conversationStage]?.type !== 'm03'
            || conversationCoordinator.snapshot().activeOperation
            || (!m03ReviewOnly && !m03PreviousEditOnly)) return false;
        const module = conversationContract.modules?.M03;
        const returnIndex = m03ReviewReturnIndex;
        const returnQuestion = Number.isInteger(returnIndex) ? questions[returnIndex] : null;
        if (!returnQuestion
            || module?.conversation_state?.stable_id !== `M03:question:${returnQuestion.dataset.questionId || ''}`
            || module?.conversation_state?.state !== 'ANSWERABLE'
            || module?.state?.write_enabled !== true
            || module?.state?.finalized === true
            || active >= returnIndex) return false;
        for (let index = active + 1; index <= returnIndex; index += 1) {
            if (index === returnIndex) {
                conversationCoordinator.invalidate('history_return_to_pending');
                setStatus('');
                return showQuestion(returnIndex, {discardDraft: true, narrateQuestion: true, userGesture: true, allowCurrentPending: true});
            }
            const candidate = questions[index];
            if (!isPersistedEligibleM03Question(candidate)) continue;
            const mode = hasM03HistoricalEditAuthorization(candidate, returnIndex) ? 'direct-edit' : 'review';
            conversationCoordinator.invalidate('history_next_question');
            setStatus('');
            return showQuestion(index, {mode, returnIndex, discardDraft: true, narrateQuestion: true, userGesture: true});
        }
        return false;
    };
    const showNextM03Question = (question, nextQuestionId = null, finalized = false) => {
        const next = nextQuestionId
            ? questions.find(candidate => Number(candidate.dataset.questionId) === Number(nextQuestionId)
                && candidate.dataset.eligible === '1'
                && !hasConfirmedAnswer(candidate))
            : null;
        if (next) { showQuestion(questions.indexOf(next)); return; }
        const pending = questions.findIndex(candidate => !hasConfirmedAnswer(candidate) && candidate.dataset.eligible === '1');
        if (pending >= 0) { showQuestion(pending); return; }
        if (finalized !== true) { setStatus('Respuesta M03 guardada. El servidor aún no confirmó el cierre del módulo; permanece aquí.'); return; }
        setStatus('M03 completado.');
        advanceConversation();
    };
    const applyM03ServerEligibility = (data, confirmedQuestionId) => {
        const result = window.PatientInterviewM03DictationUi?.applyServerReadback({
            contract: conversationContract,
            cards: questions,
            questionId: Number(confirmedQuestionId),
            data,
        });
        if (!result?.applied) return false;
        const confirmedCard = questions.find(item => Number(item.dataset.questionId) === Number(confirmedQuestionId));
        if (confirmedCard) {
            if (Object.prototype.hasOwnProperty.call(data, 'readback_value')
                && (typeof data.readback_value === 'string' || data.readback_value === null)) {
                confirmedCard.dataset.savedValue = String(data.readback_value ?? '');
            }
            if (typeof data.semantic_state === 'string') confirmedCard.dataset.semanticState = data.semantic_state.toUpperCase();
            if (Number(data.response_id) > 0) confirmedCard.dataset.responseId = String(data.response_id);
        }
        if (data.version) questions.forEach(item => { item.dataset.m03Version = data.version; });
        syncM03ContextualActions();
        if (typeof refreshM03DictationUi === 'function') refreshM03DictationUi();
        return true;
    };
    const isCurrentM03View = (question, revision) => m03QuestionViewRevision === revision
        && questions[active] === question
        && conversationStages[conversationStage]?.type === 'm03';
    const saveAndAdvanceM03Direct = async (question, value, directMode) => {
        if (!question || question.dataset.m03Saving === '1' || question.dataset.m03Interpreting === '1') return;
        const viewRevision = m03QuestionViewRevision;
        question.dataset.m03Saving = '1';
        refreshM03DictationUi?.();
        const controls = question.querySelectorAll('button, input');
        controls.forEach(control => { control.disabled = true; });
        setStatus('Guardando y continuando…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-directo', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({
                    idPaciente: Number(main.dataset.patient),
                    idPregunta: Number(question.dataset.questionId),
                    idRespuesta: Number(question.dataset.responseId || 0),
                    detalle: value,
                    expectedVersion: question.dataset.m03Version || '',
                    saveAndContinue: true,
                    directMode,
                    allowEdit: hasConfirmedAnswer(question)
                })
            });
            const data = await response.json();
            const editingHistoricalAnswer = m03PreviousEditOnly && question === questions[active] && hasConfirmedAnswer(question);
            if (!response.ok || !data.resultado || data.mode !== 'direct' || data.readback !== true
                || (editingHistoricalAnswer && Number(data.response_id) !== Number(question.dataset.responseId || 0))) {
                throw new Error(data.mensaje || 'No fue posible guardar la respuesta.');
            }
            question.dataset.savedValue = typeof data.readback_value === 'string' ? data.readback_value : value;
            question.dataset.semanticState = String(data.semantic_state || '').toUpperCase();
            if (Number(data.response_id) > 0) question.dataset.responseId = String(data.response_id);
            if (!applyM03ServerEligibility(data, Number(question.dataset.questionId))) {
                throw new Error('El guardado se confirmó, pero no se pudo actualizar el estado autorizado de M03. Recarga para verificar antes de continuar.');
            }
            if (!isCurrentM03View(question, viewRevision)) return;
            const message = question.querySelector('[data-confirmed-message]');
            if (message) { message.textContent = `Respuesta confirmada: ${answerLabel(question)}`; message.hidden = false; }
            setStatus('Respuesta guardada. Siguiente pregunta…');
             showNextM03Question(question, data.next_question_id, data.finalized === true);
        } catch (error) {
            if (isCurrentM03View(question, viewRevision)) setStatus(error.message || 'No fue posible guardar la respuesta.');
        } finally {
            controls.forEach(control => { control.disabled = false; });
            question.dataset.m03Saving = '0';
            syncM03ContextualActions();
            refreshM03DictationUi?.();
        }
    };
    const confirmAndSave = async (question, value, proposalBox = null, semanticState = '') => {
        const normalizedState = String(semanticState || '').toUpperCase();
        const stateWithoutValue = ['UNKNOWN', 'NOT_REMEMBERED'].includes(normalizedState);
        if (!value && !stateWithoutValue) { setStatus('Selecciona o escribe una respuesta antes de continuar.'); return; }
        const viewRevision = m03QuestionViewRevision;
        const buttons = question.querySelectorAll('button, input');
        buttons.forEach(button => { button.disabled = true; });
        refreshM03DictationUi?.();
        setStatus('Guardando tu respuesta…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({ idPaciente: Number(main.dataset.patient), idPregunta: Number(question.dataset.questionId), idTema: Number(question.dataset.themeId), idRespuesta: Number(question.dataset.responseId || 0), detalle: value || '', estadoRespuesta: normalizedState, expectedVersion: question.dataset.m03Version || '', expectedRespuesta: question.dataset.savedValue || '' })
            });
            const data = await response.json();
            const editingPreviousAnswer = m03PreviousEditOnly && question === questions[active];
            if (!response.ok || !data.resultado || data.readback !== true) {
                throw new Error(data.mensaje || 'No fue posible verificar la respuesta guardada.');
            }
            if (editingPreviousAnswer && Number(data.response_id) !== Number(question.dataset.responseId || 0)) {
                throw new Error('La respuesta guardada no coincide con la que se estaba editando. Recarga la pregunta para verificarla.');
            }
            question.dataset.savedValue = String(data.readback_value ?? value);
            const answerInput = question.querySelector('.interview-text');
            if (answerInput) answerInput.value = question.dataset.savedValue;
            if (Number(data.response_id) > 0) question.dataset.responseId = String(data.response_id);
            question.dataset.semanticState = String(data.semantic_state || normalizedState).toUpperCase();
            if (data.version) questions.forEach(item => { item.dataset.m03Version = data.version; });
            if (!applyM03ServerEligibility(data, Number(question.dataset.questionId))) {
                throw new Error('El guardado se confirmó, pero no se pudo actualizar el estado autorizado de M03. Recarga para verificar antes de continuar.');
            }
            if (!isCurrentM03View(question, viewRevision)) return;
            const message = question.querySelector('[data-confirmed-message]');
            if (message) { message.textContent = `Paciente: ${answerLabel(question)}`; message.hidden = false; }
            if (proposalBox) proposalBox.hidden = true;
            setStatus('Respuesta guardada en tu Historia Clínica.');
             showNextM03Question(question, data.next_question_id, data.finalized === true);
        } catch (error) {
            if (isCurrentM03View(question, viewRevision)) setStatus(error.message || 'No fue posible guardar la respuesta.');
        } finally {
            buttons.forEach(button => { button.disabled = false; });
            syncM03ContextualActions();
            refreshM03DictationUi?.();
        }
    };
    const interpret = async question => {
        const utterance = valueFor(question);
        if (!utterance) { announceEmptyResponse(question, 'M03'); return; }
        if (question.dataset.m03Interpreting === '1' || question.dataset.m03Saving === '1') return;
        const viewRevision = m03QuestionViewRevision;
        const directMode = directModeForM03(question);
        if (directMode) return saveAndAdvanceM03Direct(question, utterance, directMode);
        question.dataset.m03Interpreting = '1';
        const controls = question.querySelectorAll('button, input');
        controls.forEach(control => { control.disabled = true; });
        refreshM03DictationUi?.();
        setStatus('Interpretando tu respuesta…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), idPregunta: Number(question.dataset.questionId), idTema: Number(question.dataset.themeId), modulo:'M03', utterance})
            });
            const data = await response.json();
            if (!isCurrentM03View(question, viewRevision)) return;
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible interpretar la respuesta.');
            const proposal = data.proposal || {};
            if (proposal.kind === 'explanation') {
                setStatus(proposal.explanation || 'Puedes responder la pregunta cuando estés preparado. No se guardó ningún dato.');
                return;
            }
            if (proposal.needs_clarification) {
                const unknownStorageUnavailable = proposal.unknown_response
                    && proposal.unknown_response.persist === false
                    && proposal.unknown_response.never_infer_negative === true;
                if (proposal.error_code === 'M03_TOBACCO_ANSWER_AMBIGUOUS') {
                    await announceM03TobaccoInvalidResponse(question, proposal, viewRevision);
                    return;
                }
                setStatus(unknownStorageUnavailable
                    ? 'No se guardó el dato desconocido ni se interpretó como “No”. La pregunta permanece pendiente.'
                    : (proposal.clarification || 'Necesito una aclaración antes de guardar la respuesta.'));
                return;
            }
            if (proposal.direct_save === true && proposal.can_confirm === true) {
                // The server has classified this deterministic catalog value as
                // directly admissible. Reuse the normal capture endpoint so
                // CSRF, authorization, versioning and readback remain active.
                await confirmAndSave(question, proposal.structured_value || '', null, proposal.semantic_state || '');
                return;
            }
            setStatus(proposal.clarification || 'La respuesta no puede guardarse todavía; precisa la información solicitada.');
        } catch (error) {
            if (isCurrentM03View(question, viewRevision)) setStatus(error.message || 'No fue posible interpretar la respuesta.');
        } finally {
            controls.forEach(control => { control.disabled = false; });
            question.dataset.m03Interpreting = '0';
            syncM03ContextualActions();
            refreshM03DictationUi?.();
        }
    };
    const captureM03ContextualAction = async (question, button) => {
        const actionKey = String(button?.dataset.actionKey || '').trim();
        if (!question || !actionKey || question.dataset.m03Saving === '1' || question.dataset.m03Interpreting === '1') return;
        const viewRevision = m03QuestionViewRevision;
        question.dataset.m03Saving = '1';
        const controls = question.querySelectorAll('button, input');
        controls.forEach(control => { control.disabled = true; });
        refreshM03DictationUi?.();
        setStatus('Guardando y verificando tu respuesta…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({
                    idPaciente: Number(main.dataset.patient),
                    idPregunta: Number(question.dataset.questionId),
                    idTema: Number(question.dataset.themeId),
                    contextualActionKey: actionKey,
                    expectedVersion: question.dataset.m03Version || '',
                    expectedRespuesta: question.dataset.savedValue || ''
                })
            });
            const data = await response.json();
            if (!response.ok || !data.resultado || data.readback !== true || !Array.isArray(data.eligible_question_ids)) throw new Error(data.mensaje || 'No fue posible verificar la respuesta guardada.');
            question.dataset.savedValue = String(data.readback_value ?? '');
            question.dataset.semanticState = String(data.semantic_state || '').toUpperCase();
            if (data.version) questions.forEach(item => { item.dataset.m03Version = data.version; });
            if (!applyM03ServerEligibility(data, Number(question.dataset.questionId))) {
                throw new Error('El guardado se confirmó, pero no se pudo actualizar el estado autorizado de M03. Recarga para verificar antes de continuar.');
            }
            if (!isCurrentM03View(question, viewRevision)) return;
            const message = question.querySelector('[data-confirmed-message]');
            const confirmationLabel = String(button.dataset.confirmationLabel || 'Respuesta confirmada');
            if (message) { message.textContent = `Respuesta confirmada: ${confirmationLabel}`; message.hidden = false; }
            setStatus('Respuesta guardada y verificada.');
            showNextM03Question(question, data.next_question_id, data.finalized === true);
        } catch (error) {
            if (isCurrentM03View(question, viewRevision)) setStatus(error.message || 'No fue posible guardar la respuesta. Puedes reintentar.');
        } finally {
            controls.forEach(control => { control.disabled = false; });
            question.dataset.m03Saving = '0';
            syncM03ContextualActions();
            refreshM03DictationUi?.();
        }
    };
    document.querySelectorAll('[data-m03-contextual-action]').forEach(button => button.addEventListener('click', () => {
        captureM03ContextualAction(button.closest('.patient-interview-question'), button);
    }));
    document.querySelectorAll('.interview-submit').forEach(button => button.addEventListener('click', () => {
        const question = button.closest('.patient-interview-question');
        interpret(question);
    }));
    document.querySelectorAll('[data-m03-previous]').forEach(button => button.addEventListener('click', () => navigateM03Previous(button)));
    document.querySelectorAll('[data-m03-next]').forEach(button => button.addEventListener('click', () => navigateM03Next(button)));
    document.querySelectorAll('.interview-text').forEach(input => input.addEventListener('keydown', event => {
        if (event.key !== 'Enter' || event.shiftKey) return;
        event.preventDefault();
        interpret(input.closest('.patient-interview-question'));
    }));
    document.querySelectorAll('.interview-text').forEach(input => input.addEventListener('input', () => {
        const question = input.closest('.patient-interview-question');
        question?.removeAttribute('data-last-empty-response-error-key');
        const confirmed = question?.querySelector('[data-confirmed-message]');
        if (confirmed && input.value.trim() !== (question?.dataset.savedValue || '').trim()) confirmed.hidden = true;
        updateM03SubmitAffordance(question);
        updateM03AnswerCount(question);
    }));
    questions.forEach(question => { updateM03SubmitAffordance(question); updateM03AnswerCount(question); });
    syncM03ContextualActions();
    document.querySelectorAll('.interview-composer input[type="text"]:not(.interview-text)').forEach(input => input.addEventListener('keydown', event => {
        if (event.key !== 'Enter' || event.shiftKey) return;
        event.preventDefault();
        input.closest('.interview-composer')?.querySelector('[data-m02-interpret], [data-m04-interpret], [data-m05-interpret], [data-m05-v2-interpret], [data-m06-interpret], [data-m08-interpret], [data-m09-interpret]')?.click();
    }));
    document.querySelectorAll('[data-m01-interpret]').forEach(button => button.addEventListener('click', async () => {
        const field = button.closest('[data-m01-field]');
        const input = field.querySelector('[data-m01-input]');
        const result = field.querySelector('[data-m01-result]');
        const utterance = input ? input.value.trim() : '';
        if (!utterance) { setStatus('Escribe una propuesta M01 para revisarla.'); return; }
        button.disabled = true;
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), modulo: 'M01', campo: field.dataset.m01Field, utterance})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible revisar la propuesta M01.');
            if (result) { result.textContent = data.proposal.message || 'Propuesta solo informativa; no se guardó.'; result.hidden = false; }
            setStatus('M01 permanece sin cambios. La propuesta solo se mostró para revisión.');
        } catch (error) {
            setStatus(error.message || 'No fue posible revisar la propuesta M01.');
        } finally { button.disabled = false; }
    }));
    const saveM02Proposal = async (card, proposal, trigger = null) => {
        if (!card || card.dataset.m02Saving === '1') return;
        card.removeAttribute('data-m02-last-error-key');
        card.dataset.m02Saving = '1';
        if (trigger) trigger.disabled = true;
        setStatus('Guardando respuesta…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m02', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), enfermedad: card.dataset.m02Disease, expectedVersion: card.dataset.m02Version, proposal})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) {
                announceM02Error(card, 'SAVE_FAILED');
                return;
            }
            if (data.readback !== true) {
                announceM02Error(card, 'READBACK_FAILED');
                return;
            }
            const result = card.querySelector('[data-m02-result]');
            if (result) { result.textContent = 'Respuesta M02 guardada.'; result.hidden = false; }
            card.dataset.m02Version = data.record && data.record.version ? data.record.version : card.dataset.m02Version;
            card.removeAttribute('data-m02-context');
            card.removeAttribute('data-m02-proposal');
            setStatus('Respuesta M02 guardada.');
             advanceM02(card, data.finalized === true);
        } catch (error) {
            announceM02Error(card, 'SAVE_FAILED');
        } finally {
            if (trigger) trigger.disabled = false;
            card.dataset.m02Saving = '0';
        }
    };
    const interpretM02 = async (card, trigger = null) => {
        const input = card.querySelector('[data-m02-input]');
        const result = card.querySelector('[data-m02-result]');
        const utterance = input ? input.value.trim() : '';
        if (!utterance) { announceM02Error(card, 'EMPTY_RESPONSE'); return; }
        if (card.dataset.m02Interpreting === '1') return;
        card.removeAttribute('data-m02-last-error-key');
        card.dataset.m02Interpreting = '1';
        if (trigger) trigger.disabled = true;
        setStatus('Interpretando tu respuesta…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), modulo: 'M02', enfermedad: card.dataset.m02Disease, utterance, context: JSON.parse(card.dataset.m02Context || '{}')})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) {
                announceM02Error(card, 'INTERPRET_FAILED');
                return;
            }
            const proposal = data.proposal || {};
            card.dataset.m02Proposal = JSON.stringify(proposal);
            card.dataset.m02Context = JSON.stringify(data.context || proposal.context || {});
            if (proposal.kind === 'clarification' || proposal.needs_clarification) {
                if (result) { result.textContent = proposal.clarification || proposal.message || 'M02 solicita una aclaración.'; result.hidden = false; }
                setStatus(proposal.clarification || 'M02 solicita una aclaración. No se guardó ningún dato.');
                speakM02Followup(card, proposal.clarification || proposal.message || 'M02 solicita una aclaración.');
                return;
            }
            if (proposal.direct_save === true && proposal.can_confirm === true) {
                if (proposal.replacement_confirmation_required === true) {
                    const approved = window.confirm('Este antecedente ya contiene información. ¿Deseas sustituirla por la nueva respuesta? El cambio se verificará antes de guardarse.');
                    if (!approved) {
                        if (result) { result.textContent = 'No se modificó el antecedente anterior.'; result.hidden = false; }
                        setStatus('Se conservó la respuesta anterior. No se guardó ningún cambio.');
                        return;
                    }
                    proposal.confirm_replace_existing = true;
                    card.dataset.m02Proposal = JSON.stringify(proposal);
                }
                await saveM02Proposal(card, proposal, trigger);
                return;
            }
            if (result) { result.textContent = proposal.message || 'M02 no pudo validar la respuesta.'; result.hidden = false; }
            announceM02Error(card, 'INVALID_RESPONSE');
        } catch (error) {
            announceM02Error(card, 'INTERPRET_FAILED');
        } finally { if (trigger) trigger.disabled = false; card.dataset.m02Interpreting = '0'; }
    };
    document.querySelectorAll('[data-m02-interpret]').forEach(button => button.addEventListener('click', () => {
        interpretM02(button.closest('[data-m02-disease]'), button);
    }));
    document.querySelectorAll('[data-m02-input]').forEach(input => input.addEventListener('input', () => {
        const card = input.closest('[data-m02-disease]');
        updateM02AnswerCount(card, input);
        card?.removeAttribute('data-m02-last-error-key');
        card?.removeAttribute('data-last-empty-response-error-key');
        const interpretButton = card?.querySelector('[data-m02-interpret]');
        if (interpretButton) interpretButton.hidden = false;
        if (card?.dataset.m02Proposal && card.dataset.m02LastInput !== input.value) {
            card.removeAttribute('data-m02-proposal');
            card.removeAttribute('data-m02-last-error-key');
            setStatus('La respuesta cambió. Vuelve a enviarla para validarla; todavía no se guardó ningún dato.');
        }
        if (card) card.dataset.m02LastInput = input.value;
    }));
    document.querySelectorAll('[data-m02-input]').forEach(input => input.dispatchEvent(new Event('input')));
    document.querySelectorAll('[data-m02-safe-no]').forEach(button => button.addEventListener('click', async () => {
        const card = button.closest('[data-m02-disease]');
        const module = card?.closest('[data-module="M02"]');
        if (!card || !module || module.dataset.m02WriteEnabled !== '1' || card.dataset.m02Saving === '1') return;
        const input = card.querySelector('[data-m02-input]');
        if ((input && input.value.trim() !== '') || card.dataset.m02Proposal) {
            setStatus('Hay una respuesta o propuesta pendiente. Revísala o cancélala antes de guardar una respuesta negativa.');
            return;
        }
        card.removeAttribute('data-m02-last-error-key');
        card.dataset.m02Saving = '1';
        const controls = card.querySelectorAll('button, input');
        controls.forEach(control => { control.disabled = true; });
        setStatus('Guardando respuesta negativa…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m02', {method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf}, body:JSON.stringify({idPaciente:Number(main.dataset.patient), enfermedad:card.dataset.m02Disease, expectedVersion:card.dataset.m02Version, fastNo:true})});
            const data = await response.json();
            if (!response.ok || !data.resultado) {
                announceM02Error(card, 'SAVE_FAILED');
                return;
            }
            if (data.readback !== true) {
                announceM02Error(card, 'READBACK_FAILED');
                return;
            }
            const result = card.querySelector('[data-m02-result]');
            if (result) { result.textContent = 'Respuesta guardada.'; result.hidden = false; }
            card.removeAttribute('data-m02-context'); card.removeAttribute('data-m02-proposal');
            setStatus('Respuesta guardada.');
             advanceM02(card, data.finalized === true);
        } catch (error) {
            announceM02Error(card, 'SAVE_FAILED');
        } finally {
            card.dataset.m02Saving = '0';
            controls.forEach(control => { control.disabled = false; });
        }
    }));
    const M04_STAGES = {
        initial: 'M04:record:new',
        name: 'M04:record:new:name',
        observations: 'M04:record:new:observations',
        addAnother: 'M04:record:new:add-another',
    };
    let m04DraftSurgeryName = '';
    const refreshM04QuestionVoice = (card, speak = true) => {
        const message = card?.querySelector('.patient-interview-message.assistant');
        if (message) {
            message.querySelectorAll('.chat-voice-button, .chat-help-button').forEach(control => control.remove());
            message.removeAttribute('data-voice-bound');
        }
        const target = card ? canonicalStageTarget({card}) : null;
        if (target) conversationCoordinator.setTarget(target);
        if (target && speak && conversationStages[conversationStage]?.card === card) {
            const question = conversationContract.question_index?.[target];
            const event = conversationCoordinator.voiceEvent('QUESTION', target, false);
            if (question?.module_id === 'M04' && event?.stable_id === target && event.text === question.visible_text) {
                voiceController.speakEvent({...event, module_id: 'M04'}, {automatic: true});
            }
        }
        window.setTimeout(() => { if (typeof bindVoiceControls === 'function') bindVoiceControls(); }, 0);
    };
    const setM04Stage = (card, stage, {preserveDraft = false, speak = true} = {}) => {
        if (!card || !Object.prototype.hasOwnProperty.call(M04_STAGES, stage)) return false;
        const stableId = M04_STAGES[stage];
        const question = conversationContract.question_index?.[stableId];
        if (!question || question.module_id !== 'M04') return false;
        card.dataset.m04StableId = stableId;
        card.dataset.m04Stage = stage;
        const title = card.querySelector('[data-m04-question-title]');
        const instruction = card.querySelector('[data-m04-question-instruction]');
        if (title) title.textContent = question.visible_text || question.original_question || '';
        if (instruction) instruction.textContent = question.instruction || '';
        const inputStage = card.querySelector('[data-m04-input-stage]');
        if (inputStage) inputStage.hidden = !['name', 'observations'].includes(stage);
        card.querySelectorAll('[data-m04-stage-actions]').forEach(actions => {
            actions.hidden = actions.dataset.m04StageActions !== (stage === 'initial' ? 'initial' : stage === 'addAnother' ? 'add-another' : stage);
        });
        const input = card.querySelector('[data-m04-input]');
        if (input) {
            input.value = '';
            input.placeholder = stage === 'name' ? 'Escribe el nombre de la cirugía' : 'Escribe lo que recuerdas sobre cuándo ocurrió';
            input.setAttribute('aria-label', stage === 'name' ? 'Nombre de la cirugía' : 'Observaciones sobre cuándo ocurrió');
            if (['name', 'observations'].includes(stage)) window.setTimeout(() => input.focus(), 0);
        }
        const submit = card.querySelector('[data-m04-submit]');
        if (submit) {
            const label = submit.querySelector('.chat-send-label');
            if (label) label.textContent = stage === 'name' ? 'Continuar' : 'Guardar cirugía';
            submit.setAttribute('aria-label', stage === 'name' ? 'Continuar al dato de observaciones' : 'Guardar cirugía');
        }
        const result = card.querySelector('[data-m04-result]');
        if (result) { result.textContent = ''; result.hidden = true; }
        refreshM04QuestionVoice(card, speak);
        return true;
    };
    const appendM04PersistedRecord = (card, record) => {
        if (!record || !Number.isInteger(Number(record.id)) || Number(record.id) < 1) return false;
        const list = card.querySelector('[data-m04-record-list]');
        const records = card.querySelector('[data-m04-records]');
        const count = card.querySelector('[data-m04-record-count]');
        if (!list || !records) return false;
        if (records.querySelector(`[data-m04-record-id="${Number(record.id)}"]`)) return true;
        const item = document.createElement('div');
        item.className = 'patient-interview-context-item';
        item.dataset.m04RecordId = String(Number(record.id));
        const heading = document.createElement('strong');
        heading.textContent = 'Cirugía';
        const details = document.createElement('dl');
        details.className = 'mb-0';
        for (const [labelText, valueText] of [['Nombre', record.cirugia], ['Observaciones', record.observaciones || 'Sin observaciones adicionales']]) {
            const term = document.createElement('dt'); term.textContent = labelText;
            const value = document.createElement('dd'); value.textContent = String(valueText || '');
            details.append(term, value);
        }
        item.append(heading, details);
        records.prepend(item);
        list.hidden = false;
        if (count) {
            const total = records.querySelectorAll('[data-m04-record-id], .patient-interview-context-item').length;
            count.textContent = `Cirugías registradas — ${total} ${total === 1 ? 'registro' : 'registros'}`;
        }
        card.querySelector('[data-m04-pending-note]')?.remove();
        card.querySelector('[data-m04-negative-note]')?.remove();
        return true;
    };
    const saveM04Proposal = async (card, proposal, trigger = null) => {
        if (!card || card.dataset.m04Saving === '1') return;
        card.dataset.m04Saving = '1';
        const input = card.querySelector('[data-m04-input]');
        if (trigger) trigger.disabled = true;
        setStatus('Guardando respuesta M04…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m04', {
                method: 'POST', headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), expectedVersion: card.dataset.m04Version, proposal})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible guardar M04.');
            const result = card.querySelector('[data-m04-result]');
            const isNegative = proposal.kind === 'm04_negative_proposal';
            if (!isNegative && data.readback !== true) throw new Error('La cirugía se guardó, pero el servidor no confirmó su lectura posterior. La pregunta permanece activa.');
            if (isNegative && (data.readback !== true || data.finalized !== true)) throw new Error('La declaración no quedó confirmada mediante lectura y finalización. La pregunta permanece activa.');
            if (!isNegative && !appendM04PersistedRecord(card, data.record)) throw new Error('No se pudo mostrar el registro confirmado por el servidor. La pregunta permanece activa.');
            card.dataset.m04Version = data.version || data.record?.version || card.dataset.m04Version;
            if (data.negative_declaration?.estado === 'NEGATIVE_DECLARED') card.dataset.m04NegativeConfirmed = '1';
            const savedMessage = isNegative ? 'Declaración negativa M04 guardada.' : 'Cirugía guardada y confirmada.';
            if (result) { result.textContent = savedMessage; result.hidden = false; }
            setStatus(savedMessage);
            if (isNegative) {
                advanceConversation();
            } else {
                m04DraftSurgeryName = '';
                card.dataset.m04Record = String(data.record.id);
                setM04Stage(card, 'addAnother');
                conversationCoordinator.transition('ASKING', {reason: 'm04_additional_surgery'});
            }
        } catch (error) { setStatus(error.message || 'No fue posible guardar M04.'); }
        finally { if (trigger) trigger.disabled = false; card.dataset.m04Saving = '0'; }
    };
    const finishM04 = async (card, trigger = null) => {
        if (!card || card.dataset.m04Saving === '1' || card.dataset.m04Interpreting === '1') return;
        card.dataset.m04Saving = '1';
        if (trigger) trigger.disabled = true;
        setM04ControlsDisabled(card, true);
        setStatus('Confirmando cierre de M04…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m04', {
                method: 'POST', headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), expectedVersion: card.dataset.m04Version, intent: 'FINISH_ADDITIONAL_SURGERIES'})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible finalizar M04.');
            if (data.finalized !== true || (data.finalization_readback !== true && data.readback !== true)) {
                throw new Error('M04 no confirmó el cierre en el servidor. La pregunta permanece activa.');
            }
            card.dataset.m04Version = data.version || card.dataset.m04Version;
            const result = card.querySelector('[data-m04-result]');
            if (result) { result.textContent = 'M04 finalizado y confirmado.'; result.hidden = false; }
            setStatus('M04 finalizado y confirmado.');
            advanceConversation();
        } catch (error) { setStatus(error.message || 'No fue posible finalizar M04.'); }
        finally { setM04ControlsDisabled(card, false); if (trigger) trigger.disabled = false; card.dataset.m04Saving = '0'; }
    };
    const setM04ControlsDisabled = (card, disabled) => {
        card?.querySelectorAll('[data-m04-input], [data-m04-submit], [data-m04-finish], [data-m04-quick-response], [data-m04-start-surgery], [data-m04-negative], [data-m04-add-another], [data-m04-unknown]').forEach(control => { control.disabled = disabled; });
    };
    const submitM04Stage = (card, trigger = null) => {
        if (!card || card.dataset.m04Saving === '1') return;
        const input = card.querySelector('[data-m04-input]');
        const value = String(input?.value || '').trim();
        if (card.dataset.m04Stage === 'name') {
            if (!value) { setStatus('Escribe el nombre de la cirugía para continuar.'); input?.focus(); return; }
            const normalizedName = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
            if (/^(?:no recuerdo|no me acuerdo|no lo se|no se|desconozco)(?:\b.*)?$/.test(normalizedName)) {
                setStatus('Para registrar una cirugía se necesita su nombre. No se guardó un nombre supuesto y la respuesta permanece pendiente.');
                input?.focus();
                return;
            }
            m04DraftSurgeryName = value;
            setM04Stage(card, 'observations', {preserveDraft: true});
            return;
        }
        if (card.dataset.m04Stage !== 'observations') return;
        if (!m04DraftSurgeryName) { setStatus('El nombre de la cirugía no está disponible. Vuelve a ingresarlo.'); setM04Stage(card, 'name', {speak: false}); return; }
        if (!value) { setStatus('Escribe lo que recuerdas o selecciona «No recuerdo».'); input?.focus(); return; }
        saveM04Proposal(card, {kind: 'm04_proposal', surgery: m04DraftSurgeryName, date: null, observations: value}, trigger);
    };
    const saveM04UnknownDate = card => {
        if (!card || card.dataset.m04Stage !== 'observations' || !m04DraftSurgeryName || card.dataset.m04Saving === '1') return;
        saveM04Proposal(card, {
            kind: 'm04_proposal', surgery: m04DraftSurgeryName, date: null,
            observations: 'El paciente seleccionó «No recuerdo» para cuándo ocurrió la cirugía.',
        }, card.querySelector('[data-m04-unknown]'));
    };
    document.querySelectorAll('[data-m04-start-surgery]').forEach(button => button.addEventListener('click', () => {
        const card = button.closest('[data-module="M04"]');
        m04DraftSurgeryName = '';
        setM04Stage(card, 'name');
    }));
    document.querySelectorAll('[data-m04-negative]').forEach(button => button.addEventListener('click', () => {
        const card = button.closest('[data-module="M04"]');
        if (card?.dataset.m04Stage !== 'initial') return;
        saveM04Proposal(card, {
            kind: 'm04_negative_proposal', semantic_state: 'NEGATIVE_DECLARED', origin: 'PATIENT_DECLARATION',
        }, button);
    }));
    document.querySelectorAll('[data-m04-add-another]').forEach(button => button.addEventListener('click', () => {
        const card = button.closest('[data-module="M04"]');
        if (card?.dataset.m04Stage !== 'addAnother') return;
        m04DraftSurgeryName = '';
        setM04Stage(card, 'name');
    }));
    document.querySelectorAll('[data-m04-submit]').forEach(button => button.addEventListener('click', () => {
        submitM04Stage(button.closest('[data-module="M04"]'), button);
    }));
    document.querySelectorAll('[data-m04-input]').forEach(input => input.addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); submitM04Stage(input.closest('[data-module="M04"]'), input.closest('[data-module="M04"]')?.querySelector('[data-m04-submit]')); }
    }));
    document.querySelectorAll('[data-m04-unknown]').forEach(button => button.addEventListener('click', () => saveM04UnknownDate(button.closest('[data-module="M04"]'))));
    document.querySelectorAll('[data-m04-finish]').forEach(button => button.addEventListener('click', () => {
        const card = button.closest('[data-module="M04"]');
        if (!card || card.dataset.m04Stage !== 'addAnother' || button.dataset.intent !== 'FINISH_ADDITIONAL_SURGERIES') return;
        finishM04(card, button);
    }));
    const refreshM05QuestionVoice = card => {
        const message = card?.querySelector('.patient-interview-message.assistant');
        if (message) {
            message.querySelectorAll('.chat-voice-button, .chat-help-button').forEach(control => control.remove());
            message.removeAttribute('data-voice-bound');
        }
        const target = card ? canonicalStageTarget({card}) : null;
        if (target) conversationCoordinator.setTarget(target);
        window.setTimeout(() => { if (typeof bindVoiceControls === 'function') bindVoiceControls(); }, 0);
    };
    const syncM05QuickActions = (card, next) => {
        const quickActions = card?.querySelector('[data-m05-quick-actions]');
        if (quickActions) quickActions.hidden = String(next?.tipo || '') !== 'select';
    };
    const showNextM05Question = (card, next) => {
         if (!next) { setStatus('Respuesta M05 guardada. El servidor aún no confirmó el cierre del módulo; permanece aquí.'); return; }
        card.dataset.m05PendingQuestion = String(next.question_id || 0);
        const stableId = next.stable_id || conversationCoordinator.stableIdForQuestion('M05', next.question_id);
        if (stableId) { card.dataset.m05StableId = stableId; conversationCoordinator.setTarget(stableId); }
        const theme = card.querySelector('[data-m05-theme-label]');
        const question = card.querySelector('[data-m05-question-label]');
        if (theme) theme.textContent = next.tema || 'Pregunta M05';
        if (question) question.textContent = next.pregunta || '';
        const input = card.querySelector('[data-m05-input]'); if (input) input.value = '';
        const result = card.querySelector('[data-m05-result]'); if (result) { result.textContent = ''; result.hidden = true; }
        syncM05QuickActions(card, next);
        card.removeAttribute('data-m05-proposal');
        refreshM05QuestionVoice(card);
        setStatus('Respuesta M05 guardada. Continúa con la siguiente pregunta.');
    };
    const showNextM05Disease = (card, next) => {
         if (!next) { setStatus('Respuesta M05 V2 guardada. El servidor aún no confirmó el cierre del módulo; permanece aquí.'); return; }
        card.dataset.m05V2Disease = String(next.disease_id || 0);
        const stableId = next.stable_id || `M05:v2:${next.disease_id}`;
        if (stableId) { card.dataset.m05StableId = stableId; conversationCoordinator.setTarget(stableId); }
        const label = card.querySelector('[data-m05-v2-disease-label]'); if (label) label.textContent = next.enfermedad || 'Enfermedad M05';
        const input = card.querySelector('[data-m05-v2-input]'); if (input) input.value = '';
        const result = card.querySelector('[data-m05-v2-result]'); if (result) { result.textContent = ''; result.hidden = true; }
        card.removeAttribute('data-m05Proposal');
        card.removeAttribute('data-m05-v2-followup');
        refreshM05QuestionVoice(card);
        setStatus('Respuesta M05 V2 guardada. Continúa con la siguiente enfermedad.');
    };
    const saveM05Proposal = async (card, proposal, target, trigger = null) => {
        if (!card || card.dataset.m05Saving === '1') return;
        card.dataset.m05Saving = '1';
        if (trigger) trigger.disabled = true;
        setStatus('Guardando respuesta M05…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m05', {method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),expectedVersion:card.dataset.m05Version,proposal})});
            const data = await response.json();
            if(!response.ok||!data.resultado||data.readback!==true) throw new Error(data.mensaje||'No fue posible confirmar la respuesta M05 mediante readback.');
            card.dataset.m05Version=data.version||card.dataset.m05Version;
             if (target === 'v2') { if (data.next_disease) showNextM05Disease(card,data.next_disease); else if (data.finalized === true) advanceConversation(); else setStatus('Respuesta M05 V2 guardada. El servidor aún no confirmó el cierre del módulo; permanece aquí.'); } else { if (data.next_question) showNextM05Question(card,data.next_question); else if (data.next_disease || data.finalized === true) advanceConversation(); else setStatus('Respuesta M05 guardada. El servidor aún no confirmó el cierre del módulo; permanece aquí.'); }
        } catch(error){setStatus(error.message||'No fue posible guardar M05.');}
        finally{if (trigger) trigger.disabled=false;card.dataset.m05Saving='0';}
    };
    const setM05ControlsDisabled = (card, disabled) => {
        card?.querySelectorAll('[data-m05-input], [data-m05-interpret], [data-m05-quick]').forEach(control => { control.disabled = disabled; });
    };
    const submitM05V1 = async (card, utterance, trigger = null) => {
        if (!card || card.dataset.m05Interpreting === '1' || card.dataset.m05Saving === '1') return;
        const result = card.querySelector('[data-m05-result]');
        const text = String(utterance || '').trim();
        if (!text) { setStatus('Escribe una respuesta M05 para revisarla.'); return; }
        card.dataset.m05Interpreting = '1';
        setM05ControlsDisabled(card, true);
        try {
            const qid = Number(card.dataset.m05PendingQuestion || 0);
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar', {method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),idPregunta:qid,modulo:'M05',utterance:text})});
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible validar M05.');
            const proposal = data.proposal || {};
            if (proposal.kind === 'clarification' || proposal.needs_clarification) {
                if (result) { result.textContent = proposal.clarification || 'M05 solicita una aclaración.'; result.hidden = false; }
                setStatus(proposal.clarification || 'M05 solicita una aclaración. No se guardó ningún dato.');
                return;
            }
            if (proposal.direct_save === true && proposal.can_confirm === true) { await saveM05Proposal(card, proposal, 'v1', trigger); return; }
            if (result) { result.textContent = proposal.message || 'M05 no pudo validar la respuesta.'; result.hidden = false; }
            setStatus('M05 no pudo validar la respuesta. No se guardó ningún dato.');
        } catch (error) { setStatus(error.message || 'No fue posible validar M05.'); }
        finally { setM05ControlsDisabled(card, false); card.dataset.m05Interpreting = '0'; }
    };
    document.querySelectorAll('[data-m05-interpret]').forEach(button => button.addEventListener('click', () => {
        const card = button.closest('[data-module="M05"]');
        submitM05V1(card, card?.querySelector('[data-m05-input]')?.value || '', button);
    }));
    document.querySelectorAll('[data-m05-quick]').forEach(button => button.addEventListener('click', () => {
        const card = button.closest('[data-module="M05"]');
        submitM05V1(card, button.dataset.value || '', button);
    }));
    const m05V2FollowupText = (card, utterance) => {
        const previous = String(card?.dataset.m05V2Followup || '').trim();
        return previous ? `${previous}, ${utterance}` : utterance;
    };
    const rememberM05V2Followup = (card, combined, proposal) => {
        if (!card || (proposal?.response !== 'Si' && !card.dataset.m05V2Followup)) return;
        const details = [];
        const type = combined.match(/\b(tipo\s+1|tipo\s+2|gestacional|epoc|asma)\b/i);
        const year = combined.match(/\b(19|20)\d{2}\b/);
        if (type) details.push(type[1]);
        if (year) details.push(year[0]);
        card.dataset.m05V2Followup = ['Sí', ...details].join(', ');
    };
    document.querySelectorAll('[data-m05-v2-interpret]').forEach(button => button.addEventListener('click', async () => {
        const card = button.closest('[data-module="M05-V2"]');
        const utterance = card.querySelector('[data-m05-v2-input]')?.value.trim() || '';
        const result = card.querySelector('[data-m05-v2-result]');
        if (!utterance) { setStatus('Escribe una respuesta M05 V2.'); return; }
        const combinedUtterance = m05V2FollowupText(card, utterance);
        button.disabled = true;
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar', {method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),idPregunta:1,enfermedad_id:Number(card.dataset.m05V2Disease||0),modulo:'M05',utterance:combinedUtterance})});
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible validar M05 V2.');
            const proposal = data.proposal || {};
            if (proposal.kind === 'clarification' || proposal.needs_clarification) {
                rememberM05V2Followup(card, combinedUtterance, proposal);
                if (result) { result.textContent = proposal.clarification || 'M05 V2 solicita una aclaración.'; result.hidden = false; }
                setStatus(proposal.clarification || 'M05 V2 solicita una aclaración. No se guardó ningún dato.');
                return;
            }
            if (proposal.direct_save === true && proposal.can_confirm === true) {
                await saveM05Proposal(card, proposal, 'v2', button);
                return;
            }
            if (result) { result.textContent = proposal.message || 'M05 V2 no pudo validar la respuesta.'; result.hidden = false; }
            setStatus('M05 V2 no pudo validar la respuesta. No se guardó ningún dato.');
        } catch (error) { setStatus(error.message || 'No fue posible validar M05 V2.'); }
        finally { button.disabled = false; }
    }));
    const m06FieldPrompts = {
        medicamento: '¿Qué medicamento tomas?',
        descripcion: '¿Para qué lo utilizas?',
        tiempo_uso: '¿Desde cuándo lo utilizas?',
        dosis: '¿Qué dosis utilizas?',
        medico: '¿Qué médico te lo indicó?'
    };
    const m06Fields = ['medicamento', 'descripcion', 'tiempo_uso', 'dosis', 'medico'];
    const m06ContextFrom = card => {
        try {
            const context = JSON.parse(card?.dataset.m06Context || '{}');
            return m06Fields.reduce((safe, field) => {
                if (typeof context[field] === 'string' && context[field].trim() !== '') safe[field] = context[field].trim();
                return safe;
            }, {});
        } catch (error) { return {}; }
    };
    const m06SkippedFrom = card => {
        try {
            const skipped = JSON.parse(card?.dataset.m06Skipped || '[]');
            return Array.isArray(skipped) ? skipped.filter(field => m06Fields.includes(field)) : [];
        } catch (error) { return []; }
    };
    const m06SetSkipped = (card, fields) => { card.dataset.m06Skipped = JSON.stringify([...new Set(fields.filter(field => m06Fields.includes(field)))]); };
    const m06OmissionLabels = {descripcion: 'motivo de uso', tiempo_uso: 'tiempo de tratamiento', dosis: 'dosis', medico: 'médico'};
    const m06OmissionsFrom = card => {
        try {
            const omissions = JSON.parse(card?.dataset.m06Omissions || '{}');
            return Object.keys(omissions || {}).reduce((safe, field) => {
                if (m06OmissionLabels[field] && ['UNKNOWN', 'SKIPPED'].includes(omissions[field])) safe[field] = omissions[field];
                return safe;
            }, {});
        } catch (error) { return {}; }
    };
    const m06SetOmissions = (card, omissions) => {
        const safe = Object.keys(omissions || {}).reduce((result, field) => {
            if (m06OmissionLabels[field] && ['UNKNOWN', 'SKIPPED'].includes(omissions[field])) result[field] = omissions[field];
            return result;
        }, {});
        card.dataset.m06Omissions = JSON.stringify(safe);
        const recordId = Number(card.dataset.m06PendingRecord || 0);
        if (Object.keys(safe).length && recordId > 0) card.dataset.m06OmissionRecord = String(recordId);
        else if (!Object.keys(safe).length && recordId > 0) card.dataset.m06OmissionRecord = '';
        if (recordId > 0) {
            try {
                const omissionsByRecord = JSON.parse(card.dataset.m06OmissionsByRecord || '{}');
                if (Object.keys(safe).length) omissionsByRecord[String(recordId)] = safe;
                else delete omissionsByRecord[String(recordId)];
                card.dataset.m06OmissionsByRecord = JSON.stringify(omissionsByRecord);
            } catch (error) { card.dataset.m06OmissionsByRecord = '{}'; }
        }
        m06SetSkipped(card, Object.keys(safe));
    };
    const m06CurrentField = card => {
        const explicit = card?.dataset.m06ActiveField || '';
        return m06Fields.includes(explicit) ? explicit : m06NextField(m06ContextFrom(card), m06SkippedFrom(card));
    };
    const m06NextField = (context, skipped = []) => m06Fields.find(field => !String(context?.[field] || '').trim() && !skipped.includes(field)) || null;
    const refreshM06QuestionVoice = card => {
        const message = card?.querySelector('.patient-interview-message.assistant');
        if (message) {
            message.querySelectorAll('.chat-voice-button, .chat-help-button').forEach(control => control.remove());
            message.removeAttribute('data-voice-bound');
        }
        const target = card ? canonicalStageTarget({card}) : null;
        // M06 keeps the same target while progressively filling one record.
        // Always re-apply it so RESPONSE_SAVED can be re-armed for the next
        // field; the coordinator still rejects genuinely concurrent submits.
        if (target) conversationCoordinator.setTarget(target);
        window.setTimeout(() => { if (typeof bindVoiceControls === 'function') bindVoiceControls(); }, 0);
    };
    const speakM06Prompt = (card, userGesture = false) => {
        const target = (card && canonicalStageTarget({card})) || card?.dataset.m06StableId || 'M06:current-medication:new';
        const snapshot = conversationCoordinator.snapshot();
        if (target !== snapshot.currentTarget || !voiceController?.enabled || voiceController.muted) return false;
        const prompt = card.querySelector('[data-m06-question]')?.textContent?.trim() || '';
        if (!prompt) return false;
        const contractVoice = conversationCoordinator.voiceEvent('QUESTION', target, false);
        if (!contractVoice.text) return false;
        return voiceController.speakEvent({...contractVoice, text: prompt}, {force: true, userGesture, automatic: !userGesture && voiceController.automaticMode});
    };
    const syncM06QuickActions = (card, state) => {
        const quickActions = card?.querySelector('[data-m06-quick-actions]');
        if (!quickActions) return;
        const composer = card.querySelector('[data-m06-composer]');
        if (composer) composer.hidden = state === 'after-save';
        const startButtons = Array.from(quickActions.querySelectorAll('[data-m06-action="START_MEDICATION"]'));
        startButtons.forEach(button => { button.hidden = button.dataset.m06Stage !== state; });
        const activeField = m06CurrentField(card);
        const canOmit = state === 'capturing' && Number(card?.dataset.m06PendingRecord || 0) > 0 && activeField !== 'medicamento';
        quickActions.querySelectorAll('[data-m06-skip]').forEach(button => { button.hidden = !canOmit; });
        const finishButton = quickActions.querySelector('[data-m06-quick][data-m06-action^="FINISH_"]');
        if (!finishButton) return;
        finishButton.hidden = state === 'capturing';
        finishButton.dataset.m06Action = state === 'after-save'
            ? 'FINISH_ADDITIONAL_MEDICATIONS' : 'FINISH_NO_CURRENT_MEDICATION';
        finishButton.dataset.intent = finishButton.dataset.m06Action;
        finishButton.textContent = state === 'after-save' ? 'No, terminar medicación' : 'No tomo medicamentos';
    };
    const setM06Prompt = (card, prompt, state, context = {}, userGesture = false, clearInput = false) => {
        if (!card) return;
        card.dataset.m06State = state;
        card.dataset.m06Context = JSON.stringify(context || {});
        const pendingRecord = Number(card.dataset.m06PendingRecord || 0);
        const skipped = m06SkippedFrom(card);
        const requestedField = card.dataset.m06RequestedField || '';
        const activeField = state === 'capturing'
            ? (m06Fields.includes(requestedField) ? requestedField : m06NextField(context, skipped))
            : null;
        card.dataset.m06RequestedField = '';
        card.dataset.m06ActiveField = activeField || '';
        card.dataset.m06StableId = state === 'capturing' && pendingRecord > 0
            ? `M06:current-medication:${pendingRecord}`
            : 'M06:current-medication:new';
        const question = card.querySelector('[data-m06-question]');
        if (question) question.textContent = prompt;
        const instruction = card.querySelector('[data-m06-instruction]');
        if (instruction) instruction.textContent = state === 'capturing'
            ? activeField === 'medicamento'
                ? (card.dataset.m06HasRecords === '1' ? 'Escribe el nombre del siguiente medicamento.' : 'Escribe el nombre del medicamento.')
                : 'Indica sólo el dato solicitado. Si no lo recuerdas, dilo sin inventar información.'
            : state === 'after-save'
                ? 'Puedes agregar otro medicamento o elegir No, terminar medicación.'
                : 'Responde Sí o No. Si respondes Sí, te preguntaré los cinco datos oficiales uno por uno.';
        syncM06QuickActions(card, state);
        const input = card.querySelector('[data-m06-input]');
        if (input && clearInput) {
            input.value = '';
            input.dispatchEvent(new Event('input', {bubbles: true}));
        }
        refreshM06QuestionVoice(card);
        speakM06Prompt(card, userGesture);
    };
    const initialM06Card = document.querySelector('[data-module="M06"]');
    if (initialM06Card) { syncM06QuickActions(initialM06Card, initialM06Card.dataset.m06State || 'initial'); }
    const finishM06 = async (card, intent, trigger = null) => {
        if (!card || card.dataset.m06Saving === '1') return;
        card.dataset.m06Saving = '1';
        if (trigger) trigger.disabled = true;
        setStatus('Confirmando el cierre de M06…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m06', {method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf}, body:JSON.stringify({idPaciente:Number(main.dataset.patient), expectedVersion:card.dataset.m06Version, intent})});
            const data = await response.json();
            if (!response.ok || !data.resultado || data.finalized !== true || data.readback !== true) {
                const message = data.mensaje || 'M06 no pudo confirmar su finalización mediante readback.';
                const result = card.querySelector('[data-m06-result]');
                if (result) { result.textContent = message; result.hidden = false; }
                throw new Error(message);
            }
            card.dataset.m06Version = data.version || card.dataset.m06Version;
            setStatus(intent === 'FINISH_NO_CURRENT_MEDICATION' ? 'Declaración de ausencia de medicación confirmada.' : 'Medicación M06 confirmada. Continuando…');
            advanceConversation();
        } catch (error) { setStatus(error.message || 'No fue posible finalizar M06.'); }
        finally { if (trigger) trigger.disabled = false; card.dataset.m06Saving = '0'; }
    };
    const startM06Medication = (card, trigger = null) => {
        if (!card || card.dataset.m06Saving === '1') return;
        const addingAnother = card.dataset.m06State === 'after-save' || card.dataset.m06HasRecords === '1';
        card.dataset.m06HasRecords = '1';
        card.dataset.m06PendingRecord = '0';
        card.dataset.m06OmissionRecord = '';
        card.dataset.m06ActiveField = '';
        card.dataset.m06RequestedField = '';
        m06SetOmissions(card, {});
        m06SetSkipped(card, []);
        setM06Prompt(card, m06FieldPrompts.medicamento, 'capturing', {}, Boolean(trigger), true);
        setStatus(addingAnother ? 'Escribe el nombre del siguiente medicamento.' : 'Escribe el nombre del medicamento.');
    };
    const skipM06Field = async (card, mode, trigger = null) => {
        if (!card || card.dataset.m06State !== 'capturing' || card.dataset.m06Interpreting === '1' || card.dataset.m06Saving === '1') return;
        const context = m06ContextFrom(card);
        const activeField = m06CurrentField(card);
        const recordId = Number(card.dataset.m06PendingRecord || 0);
        if (!activeField || activeField === 'medicamento' || recordId < 1) return;
        card.dataset.m06Saving = '1';
        if (trigger) trigger.disabled = true;
        setStatus('Guardando el estado de la respuesta M06…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m06', {method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf}, body:JSON.stringify({idPaciente:Number(main.dataset.patient), expectedVersion:card.dataset.m06Version, omission:{recordId, field:activeField, state:mode === 'unknown' ? 'UNKNOWN' : 'SKIPPED'}})});
            const data = await response.json();
            if (!response.ok || !data.resultado || data.readback !== true) throw new Error(data.mensaje || 'La omisión M06 no quedó confirmada mediante readback.');
            const savedRecord = data.record || {};
            const nextContext = Object.keys(savedRecord).length ? savedRecord : context;
            m06SetOmissions(card, savedRecord.omissions || data.omissions || m06OmissionsFrom(card));
            card.dataset.m06Version = data.version || card.dataset.m06Version;
            const pendingId = Number(data.pending_record || 0);
            card.dataset.m06PendingRecord = pendingId > 0 ? String(pendingId) : '0';
            const nextField = data.next_field || m06NextField(nextContext, m06SkippedFrom(card));
            if (nextField) {
                setM06Prompt(card, m06FieldPrompts[nextField], 'capturing', nextContext, Boolean(trigger), true);
                setStatus(mode === 'unknown' ? 'No lo sé quedó guardado y confirmado. Puedes continuar con el siguiente dato.' : 'El dato quedó omitido y confirmado. Puedes continuar con el siguiente dato.');
            } else {
                setM06Prompt(card, '¿Quieres agregar otro medicamento?', 'after-save', nextContext, false, true);
                setStatus('Los datos disponibles quedaron confirmados. Puedes agregar otro medicamento o terminar la captura.');
            }
        } catch (error) { setStatus(error.message || 'No fue posible guardar el estado de M06.'); }
        finally { if (trigger) trigger.disabled = false; card.dataset.m06Saving = '0'; }
    };
    const saveM06Proposal = async (card, proposal, trigger = null) => {
        if (!card || card.dataset.m06Saving === '1') return;
        card.dataset.m06Saving = '1';
        if (trigger) trigger.disabled = true;
        setStatus('Guardando respuesta M06…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m06', {method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf}, body:JSON.stringify({idPaciente:Number(main.dataset.patient), expectedVersion:card.dataset.m06Version, recordId:Number(card.dataset.m06PendingRecord || 0) || null, createNew:Number(card.dataset.m06PendingRecord || 0) === 0 && card.dataset.m06State === 'capturing', proposal})});
            const data = await response.json();
            if (!response.ok || !data.resultado || data.readback !== true) throw new Error(data.mensaje || 'La medicación no quedó confirmada mediante readback. La pregunta permanece activa.');
            card.dataset.m06Version = data.version || card.dataset.m06Version;
            const savedRecord = data.record || {};
            const pendingId = Number(data.pending_record || 0);
            const nextContext = Object.keys(savedRecord).length ? savedRecord : {};
            m06SetOmissions(card, savedRecord.omissions || data.omissions || m06OmissionsFrom(card));
            if (pendingId > 0) card.dataset.m06PendingRecord = String(pendingId);
            else card.dataset.m06PendingRecord = '0';
            const nextField = m06NextField(nextContext, m06SkippedFrom(card));
            if (data.next_action === 'COMPLETE_MEDICATION' && pendingId > 0 && nextField) {
                setM06Prompt(card, m06FieldPrompts[nextField], 'capturing', nextContext, false, true);
            } else {
                setM06Prompt(card, '¿Quieres agregar otro medicamento?', 'after-save', nextContext, false, true);
            }
            const result = card.querySelector('[data-m06-result]');
            if (result) { result.textContent = ''; result.hidden = true; }
            setStatus(data.next_action === 'COMPLETE_MEDICATION' ? 'Medicamento guardado. Completa el dato pendiente.' : 'Medicamento guardado y confirmado.');
        } catch (error) { setStatus(error.message || 'No fue posible guardar M06.'); }
        finally { if (trigger) trigger.disabled = false; card.dataset.m06Saving = '0'; }
    };
    const submitM06 = async (card, utterance, trigger = null) => {
        if (!card || card.dataset.m06Interpreting === '1' || card.dataset.m06Saving === '1') return;
        const text = String(utterance || '').trim();
        const normalized = text.toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
        const state = card.dataset.m06State || 'initial';
        if (!text) { setStatus('Escribe una respuesta M06.'); return; }
        if (/^no(?:,?\s*(continuar|gracias))?$/.test(normalized) && state === 'initial') { await finishM06(card, 'FINISH_NO_CURRENT_MEDICATION', trigger); return; }
        if (/^no(?:,?\s*(continuar|gracias))?$/.test(normalized) && state === 'after-save') { await finishM06(card, 'FINISH_ADDITIONAL_MEDICATIONS', trigger); return; }
        if (/^(si|sí)(?:,?\s*agregar otro)?$/.test(normalized) && (state === 'initial' || state === 'after-save')) { startM06Medication(card, trigger); return; }
        const result = card.querySelector('[data-m06-result]');
        card.dataset.m06Interpreting = '1';
        if (trigger) trigger.disabled = true;
        const context = m06ContextFrom(card);
        const activeField = m06CurrentField(card);
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar', {method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf}, body:JSON.stringify({idPaciente:Number(main.dataset.patient), modulo:'M06', utterance:text, context, campo:activeField})});
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible validar M06.');
            const proposal = data.proposal || {};
            if (proposal.kind === 'clarification' || proposal.needs_clarification) {
                const nextContext = proposal.context || proposal.fields || context;
                card.dataset.m06Context = JSON.stringify(nextContext);
                const nextField = proposal.missing?.find(field => !m06SkippedFrom(card).includes(field)) || m06NextField(nextContext, m06SkippedFrom(card));
                setM06Prompt(card, m06FieldPrompts[nextField] || 'Indica el dato pendiente del medicamento.', 'capturing', nextContext, false);
                if (result) { result.textContent = proposal.clarification || 'M06 solicita una aclaración.'; result.hidden = false; }
                setStatus(proposal.clarification || 'M06 solicita una aclaración. No se guardó ningún dato.');
                return;
            }
            if (proposal.direct_save === true && proposal.can_confirm === true) { await saveM06Proposal(card, proposal, trigger); return; }
            if (result) { result.textContent = proposal.message || 'M06 no pudo validar los datos.'; result.hidden = false; }
            setStatus('M06 no pudo validar los datos. No se guardó ningún dato.');
        } catch (error) { setStatus(error.message || 'No fue posible validar M06.'); }
        finally { if (trigger) trigger.disabled = false; card.dataset.m06Interpreting = '0'; }
    };
    document.querySelectorAll('[data-m06-interpret]').forEach(button => button.addEventListener('click', () => { const card = button.closest('[data-module="M06"]'); submitM06(card, card?.querySelector('[data-m06-input]')?.value || '', button); }));
    document.querySelectorAll('[data-m06-quick]').forEach(button => button.addEventListener('click', () => {
        const card = button.closest('[data-module="M06"]');
        const action = button.dataset.m06Action || button.dataset.intent || '';
        if (action === 'START_MEDICATION') startM06Medication(card, button);
        else if (action === 'FINISH_NO_CURRENT_MEDICATION' || action === 'FINISH_ADDITIONAL_MEDICATIONS') finishM06(card, action, button);
    }));
    document.querySelectorAll('[data-m06-skip]').forEach(button => button.addEventListener('click', () => {
        const card = button.closest('[data-module="M06"]');
        skipM06Field(card, button.dataset.m06SkipMode || 'omit', button);
    }));
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-m06-revisit]');
        if (!button) return;
        const card = button.closest('[data-module="M06"]');
        if (!card || card.dataset.m06Saving === '1') return;
        const field = button.dataset.m06Revisit || '';
        const recordId = Number(card.dataset.m06RecordId || 0) || Number(card.dataset.m06PendingRecord || 0) || Number(card.dataset.m06OmissionRecord || 0);
        if (!m06Fields.includes(field) || recordId < 1) return;
        card.dataset.m06PendingRecord = String(recordId);
        card.dataset.m06RequestedField = field;
        if (historyPanel) historyPanel.hidden = true;
        setM06Prompt(card, m06FieldPrompts[field], 'capturing', m06ContextFrom(card), true, false);
        setStatus('Puedes proporcionar ahora el dato omitido.');
    });
    const m07Card = document.querySelector('[data-module="M07"]');
    const m07Result = m07Card?.querySelector('[data-m07-message]');
    const m07SetResult = message => { if (m07Result) { m07Result.textContent = message || ''; m07Result.hidden = !message; } };
    const m07PayloadFor = item => {
        const result = item.querySelector('[data-m07-result]:checked')?.value || '';
        const consumption = item.querySelector('[data-m07-consumption]:checked')?.value || '';
        const fields = {respuesta:'Si', dosis:item.querySelector('[data-m07-dose]')?.value.trim() || '', resultados:result, consumo:consumption, descripcion:item.querySelector('[data-m07-description]')?.value.trim() || ''};
        if (!fields.dosis || !fields.resultados || !fields.consumo || (fields.resultados === 'Tuve efectos adversos' && !fields.descripcion)) throw new Error('Completa dosis, resultado, consumo y, si aplica, la descripción de efectos adversos.');
        return {question_id:Number(item.dataset.questionId), theme_id:Number(item.dataset.themeId), fields, confirm_clear:true};
    };
    const m07MarkReadback = (items, data) => {
        if (!m07Card) return;
        m07Card.dataset.m07Version = data.version || m07Card.dataset.m07Version;
        const ids = new Set(items.map(item => String(item.question_id)));
        m07Card.querySelectorAll('[data-m07-item]').forEach(item => {
            if (!ids.has(String(item.dataset.questionId))) return;
            item.dataset.status = 'complete';
            item.removeAttribute('data-dirty');
            const badge = item.querySelector('[data-m07-status]'); if (badge) badge.textContent = 'Completo';
        });
        m07Card.querySelectorAll('[data-m07-category]').forEach(category => {
            const pending = Array.from(category.querySelectorAll('[data-m07-item]')).filter(item => item.dataset.status !== 'complete').length;
            category.dataset.categoryPending = String(pending);
            const state = category.querySelector('[data-m07-category-state]'); if (state) state.textContent = pending ? 'Respuestas pendientes en esta categoría.' : 'Categoría revisada.';
        });
        m07SetResult('Respuesta M07 confirmada. El servidor devolvió el estado actualizado; la lectura no crea filas.');
        if (Number(data.pending_items || 0) === 0) {
            if (data.finalized === true && data.finalization_readback === true) advanceConversation();
            else m07SetResult('Las respuestas están completas, pero el servidor aún no confirmó la finalización. La entrevista permanece en M07.');
        }
    };
    const m07Submit = async (items, button) => {
        if (!m07Card || !items.length) { setStatus('Selecciona una respuesta M07.'); return; }
        if (items.length !== 1) { setStatus('Confirma una pregunta M07 a la vez.'); return; }
        button.disabled = true;
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m07', {method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf}, body:JSON.stringify({idPaciente:Number(main.dataset.patient), expectedVersion:m07Card.dataset.m07Version, items})});
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible guardar M07.');
            if (['create','update'].includes(items[0]?.other_action)) {
                const saved = data.record;
                const linked = data.readback?.other_medications?.some(entry => Number(entry.id) === Number(saved?.id) && Number(entry.question_id) === Number(items[0].question_id));
                if (!saved || !linked || !data.readback?.version || data.version !== data.readback.version) throw new Error('El servidor no confirmó el vínculo y la lectura del medicamento adicional.');
                m07Card.dataset.m07Version = data.version;
                m07SetResult('Medicamento adicional guardado y verificado.');
                window.setTimeout(() => window.location.reload(), 100);
                return;
            }
            m07MarkReadback(items, data);
            setStatus('M07 guardó únicamente las respuestas confirmadas.');
        } catch (error) { m07SetResult(error.message || 'No fue posible guardar M07.'); setStatus(error.message || 'No fue posible guardar M07.'); }
        finally { button.disabled = false; }
    };
    if (m07Card) {
        const m07Categories = Array.from(m07Card.querySelectorAll('[data-m07-category]'));
        const m07CategoryNav = Array.from(m07Card.querySelectorAll('[data-m07-category-goto]'));
        const m07RefreshCategory = category => {
            if (!category) return;
            const pending = Array.from(category.querySelectorAll('[data-m07-item]')).filter(item => item.dataset.status !== 'complete').length;
            category.dataset.categoryPending = String(pending);
            const state = category.querySelector('[data-m07-category-state]'); if (state) state.textContent = pending ? 'Respuestas pendientes en esta categoría.' : 'Categoría revisada.';
        };
        const m07ShowCategory = index => {
            const bounded = Math.max(0, Math.min(Number(index) || 0, m07Categories.length - 1));
            m07Categories.forEach(category => { category.hidden = Number(category.dataset.categoryIndex) !== bounded; });
            m07CategoryNav.forEach(button => { const active = Number(button.dataset.m07CategoryGoto) === bounded; button.setAttribute('aria-current', active ? 'true' : 'false'); });
            m07Card.dataset.m07ActiveCategory = String(bounded);
            m07Categories[bounded]?.querySelector('[data-m07-used]:not(:disabled)')?.focus({preventScroll:true});
        };
        const m07CategoryCanLeave = category => !category?.querySelector('[data-m07-item][data-dirty="1"]') && Number(category?.dataset.categoryPending || 0) === 0;
        m07Categories.forEach(category => {
            m07RefreshCategory(category);
            category.querySelector('[data-m07-previous-category]')?.addEventListener('click', () => m07ShowCategory(Number(category.dataset.categoryIndex) - 1));
            category.querySelector('[data-m07-next-category]')?.addEventListener('click', () => {
                if (!m07CategoryCanLeave(category)) { setStatus('Confirma las respuestas pendientes de esta categoría antes de continuar.'); return; }
                m07ShowCategory(Number(category.dataset.categoryIndex) + 1);
            });
        });
        m07CategoryNav.forEach(button => button.addEventListener('click', () => {
            const current = m07Categories.find(category => Number(category.dataset.categoryIndex) === Number(m07Card.dataset.m07ActiveCategory));
            const target = Number(button.dataset.m07CategoryGoto);
            if (current && target !== Number(current.dataset.categoryIndex) && !m07CategoryCanLeave(current)) { setStatus('Confirma las respuestas pendientes de la categoría actual antes de cambiar.'); return; }
            m07ShowCategory(target);
        }));
        m07ShowCategory(Number(m07Card.dataset.m07ActiveCategory || <?=$m07FirstCategory;?>));
        const writable = m07Card.dataset.m07WriteEnabled === '1';
        m07Card.querySelectorAll('[data-m07-used]').forEach(input => input.addEventListener('change', () => {
            const item = input.closest('[data-m07-item]'); const details = item?.querySelector('[data-m07-details]'); if (details) details.hidden = !input.checked; if (item) item.dataset.dirty = '1';
        }));
        m07Card.querySelectorAll('[data-m07-result]').forEach(input => input.addEventListener('change', () => {
            const item = input.closest('[data-m07-item]'); const adverse = item?.querySelector('[data-m07-adverse-wrap]'); if (adverse) adverse.hidden = input.value !== 'Tuve efectos adversos'; if (item) item.dataset.dirty = '1';
        }));
        m07Card.querySelectorAll('[data-m07-dose], [data-m07-description], [data-m07-consumption]').forEach(input => input.addEventListener('input', () => { const item = input.closest('[data-m07-item]'); if (item) item.dataset.dirty = '1'; }));
        m07Card.querySelectorAll('[data-m07-save-selected], [data-m07-confirm-negatives]').forEach(button => { button.disabled = !writable; });
        m07Card.querySelectorAll('[data-m07-create-other], [data-m07-save-other], [data-m07-other-no]').forEach(button => { button.disabled = !writable; });
        m07Card.querySelectorAll('[data-m07-create-other]').forEach(button => button.addEventListener('click', async () => {
            const item = button.closest('[data-m07-item]');
            const name = item?.querySelector('[data-m07-new-other-name]')?.value.trim() || '';
            if (!name) { setStatus('Escribe el nombre del medicamento adicional antes de guardar.'); return; }
            await m07Submit([{question_id:Number(item.dataset.questionId),theme_id:Number(item.dataset.themeId),fields:{respuesta:'Si'},other_action:'create',name}],button);
        }));
        m07Card.querySelectorAll('[data-m07-save-other]').forEach(button => button.addEventListener('click', async () => {
            const entry = button.closest('[data-m07-other-entry]'); const item = button.closest('[data-m07-item]');
            const patch = {};
            const nameInput=entry.querySelector('[data-m07-other-name]'); const name=nameInput?.value.trim()||'';
            if(name && name!==nameInput.dataset.originalName) patch.nombre=name;
            const nextField=entry.dataset.nextField;
            const fieldControl={dosis:'[data-m07-other-dose]',consumo:'[data-m07-other-consumption]',resultados:'[data-m07-other-result]',descripcion:'[data-m07-other-description]'}[nextField];
            if(nextField==='complete') {
                patch.dosis=entry.querySelector('[data-m07-other-dose]')?.value.trim()||'';
                patch.consumo=entry.querySelector('[data-m07-other-consumption]')?.value||'';
                patch.resultados=entry.querySelector('[data-m07-other-result]')?.value||'';
                patch.descripcion=entry.querySelector('[data-m07-other-description]')?.value.trim()||'';
            } else if(fieldControl) {
                const control=entry.querySelector(fieldControl); const value=String(control?.value ?? '').trim();
                if(value) patch[nextField]=value;
            }
            Object.keys(patch).forEach(key => { if (!patch[key]) delete patch[key]; });
            if (!Object.keys(patch).length) { setStatus('Completa un dato antes de guardar.'); return; }
            await m07Submit([{question_id:Number(item.dataset.questionId),theme_id:Number(item.dataset.themeId),other_action:'update',entry_id:Number(entry.dataset.entryId),entry_version:Number(entry.dataset.entryVersion),patch}],button);
        }));
        m07Card.querySelectorAll('[data-m07-other-result]').forEach(input => input.addEventListener('change', () => { const wrap=input.closest('[data-m07-other-entry]')?.querySelector('[data-m07-other-adverse-wrap]'); if(wrap)wrap.hidden=input.value!=='Tuve efectos adversos'; }));
        m07Card.querySelectorAll('[data-m07-other-no]').forEach(button => button.addEventListener('click', async () => {
            const item=button.closest('[data-m07-item]');
            await m07Submit([{question_id:Number(item.dataset.questionId),theme_id:Number(item.dataset.themeId),fields:{respuesta:'No'},confirm_clear:false}],button);
        }));
        m07Card.querySelectorAll('[data-m07-save-selected]').forEach(button => button.addEventListener('click', async () => {
            try {
                const items = Array.from(m07Card.querySelectorAll('[data-m07-item]')).filter(item => item.querySelector('[data-m07-used]')?.checked && item.dataset.status !== 'complete').map(m07PayloadFor);
                await m07Submit(items, button);
            } catch (error) { setStatus(error.message || 'Completa la respuesta M07 antes de revisar.'); }
        }));
        m07Card.querySelectorAll('[data-m07-confirm-negatives]').forEach(button => button.addEventListener('click', async () => {
            const next = Array.from(button.closest('[data-m07-category]')?.querySelectorAll('[data-m07-item]') || []).find(item => item.dataset.status === 'pending' && item.dataset.otherBlocked !== '1' && !item.querySelector('[data-m07-used]')?.checked);
            const items = next ? [{question_id:Number(next.dataset.questionId), theme_id:Number(next.dataset.themeId), fields:{respuesta:'No'}, confirm_clear:true}] : [];
            if (!items.length) { setStatus('No hay medicamentos pendientes que puedan confirmarse como no utilizados.'); return; }
            if (!window.confirm('Confirma explícitamente que los medicamentos no seleccionados de esta categoría no fueron utilizados.')) return;
            await m07Submit(items, button);
        }));
    }
    const m07CanonicalForm = m07Card?.querySelector('[data-m07-canonical-editor]');
    if (m07CanonicalForm) {
        const canonicalStatus = m07CanonicalForm.querySelector('[data-m07-canonical-status]');
        const showCanonicalStatus = message => { if (canonicalStatus) { canonicalStatus.textContent = message || ''; canonicalStatus.hidden = !message; } };
        const selectedM07Response = () => m07CanonicalForm.querySelector('[data-m07-response]:checked')?.value || '';
        m07CanonicalForm.querySelectorAll('[data-m07-response]').forEach(input => input.addEventListener('change', () => {
            const yes = selectedM07Response() === 'Si';
            const dependencies = m07CanonicalForm.querySelector('[data-m07-dependencies]');
            const otherName = m07CanonicalForm.querySelector('[data-m07-other-name-wrap]');
            if (dependencies) dependencies.hidden = !yes;
            if (otherName) otherName.hidden = !yes;
        }));
        m07CanonicalForm.querySelector('[data-m07-result]')?.addEventListener('change', event => {
            const adverse = m07CanonicalForm.querySelector('[data-m07-adverse-wrap]');
            if (adverse) adverse.hidden = event.currentTarget.value !== 'Tuve efectos adversos';
        });
        m07CanonicalForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (m07Card.dataset.m07WriteEnabled !== '1' || m07Card.dataset.m07Saving === '1') return;
            const mode = m07CanonicalForm.dataset.mode;
            const questionId = Number(m07CanonicalForm.dataset.questionId);
            const themeId = Number(m07CanonicalForm.dataset.themeId);
            const responseValue = selectedM07Response();
            const baseItem = {question_id:questionId, theme_id:themeId};
            let item;
            if (mode === 'catalog') {
                if (!['Si', 'No'].includes(responseValue)) { showCanonicalStatus('Selecciona Sí o No.'); return; }
                const fields = {respuesta:responseValue, dosis:'', resultados:'', consumo:'', descripcion:''};
                if (responseValue === 'Si') {
                    fields.dosis = m07CanonicalForm.querySelector('[data-m07-dose]')?.value.trim() || '';
                    fields.resultados = m07CanonicalForm.querySelector('[data-m07-result]')?.value || '';
                    fields.consumo = m07CanonicalForm.querySelector('[data-m07-consumption]')?.value || '';
                    fields.descripcion = m07CanonicalForm.querySelector('[data-m07-description]')?.value.trim() || '';
                    if (!fields.dosis || !['Funciono','No funciono','Tuve efectos adversos'].includes(fields.resultados) || !['Si','No'].includes(fields.consumo) || (fields.resultados === 'Tuve efectos adversos' && !fields.descripcion)) {
                        showCanonicalStatus('Completa los datos dependientes requeridos. El texto permanece disponible para corregirlo.'); return;
                    }
                } else {
                    const hasDependents = m07CanonicalForm.dataset.hasDependents === '1';
                    if (hasDependents && !window.confirm('La respuesta No requiere limpiar datos dependientes ya registrados. ¿Confirmas esta corrección?')) return;
                    item = {...baseItem, fields, confirm_clear:hasDependents};
                }
                if (!item) item = {...baseItem, fields, confirm_clear:false};
            } else if (mode === 'other_create') {
                if (!['Si','No'].includes(responseValue)) { showCanonicalStatus('Selecciona Sí o No.'); return; }
                if (responseValue === 'Si') {
                    const name = m07CanonicalForm.querySelector('[data-m07-other-name]')?.value.trim() || '';
                    if (!name) { showCanonicalStatus('Indica el nombre del medicamento adicional.'); return; }
                    item = {...baseItem, fields:{respuesta:'Si'}, other_action:'create', name};
                } else item = {...baseItem, fields:{respuesta:'No'}};
            } else if (mode === 'other_update') {
                const field = m07CanonicalForm.dataset.field;
                const value = m07CanonicalForm.querySelector('[data-m07-other-field]')?.value.trim() || '';
                if (!value) { showCanonicalStatus('Completa el dato solicitado.'); return; }
                item = {...baseItem, other_action:'update', entry_id:Number(m07CanonicalForm.dataset.entryId), entry_version:Number(m07CanonicalForm.dataset.entryVersion), patch:{[field]:value}};
            } else {
                showCanonicalStatus('El estado de esta pregunta no permite captura.'); return;
            }

            const send = m07CanonicalForm.querySelector('[data-m07-send]');
            if (!send) return;
            m07Card.dataset.m07Saving = '1'; send.disabled = true; showCanonicalStatus('Guardando. Espera la confirmación del servidor…');
            try {
                const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m07', {
                    method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},
                    body:JSON.stringify({idPaciente:Number(main.dataset.patient), expectedVersion:m07Card.dataset.m07Version, items:[item]})
                });
                const data = await response.json();
                if (!response.ok || data.resultado !== true || !data.readback || !data.version || data.version !== data.readback.version) throw new Error(data.mensaje || 'El servidor no confirmó la respuesta M07.');
                if (mode === 'other_create' || mode === 'other_update') {
                    const saved = data.record;
                    const readback = data.readback.other_medications?.find(entry => Number(entry.id) === Number(saved?.id) && Number(entry.question_id) === questionId);
                    if (!saved || !readback || Number(readback.version) !== Number(saved.version)) throw new Error('El servidor no confirmó el medicamento adicional mediante readback.');
                    if (mode === 'other_update' && String(readback.fields?.[m07CanonicalForm.dataset.field] ?? '').trim() !== String(item.patch[m07CanonicalForm.dataset.field]).trim()) throw new Error('El servidor no confirmó el campo actualizado.');
                } else {
                    const saved = data.readback.records?.find(record => Number(record.question_id) === questionId);
                    if (!saved || saved.status !== 'complete') throw new Error('El servidor aún no confirma esta pregunta como completa. La pregunta permanece activa.');
                    for (const field of ['respuesta','dosis','resultados','consumo','descripcion']) if (String(saved.fields?.[field] ?? '').trim() !== String(item.fields[field] ?? '').trim()) throw new Error('El readback no coincide con la respuesta enviada. La pregunta permanece activa.');
                }
                m07Card.dataset.m07Version = data.version;
                showCanonicalStatus('Respuesta confirmada y verificada. Actualizando la siguiente pregunta…');
                window.location.reload();
            } catch (error) {
                showCanonicalStatus(error.message || 'No fue posible guardar M07. La pregunta permanece activa.');
                setStatus(error.message || 'No fue posible guardar M07.');
            } finally {
                m07Card.dataset.m07Saving = '0';
                send.disabled = false;
            }
        });
        m07CanonicalForm.querySelector('[data-m07-repeat]')?.addEventListener('click', () => {
            const target = m07CanonicalForm.dataset.stableId;
            if (!target || target !== m07Card.querySelector('[data-m07-canonical-editor]')?.dataset.stableId) return;
            repeatActiveQuestion({dataset:{voiceTarget:target}});
        });
        m07CanonicalForm.querySelector('[data-m07-help]')?.addEventListener('click', () => {
            const target = m07CanonicalForm.dataset.stableId;
            const question = conversationContract.question_index?.[target];
            showCanonicalStatus(question?.help || 'Responde únicamente la pregunta activa y espera la confirmación del servidor.');
        });
    }
    const saveM08Proposal = async (card, proposal, trigger = null) => {
        if (!card || card.dataset.m08Saving === '1') return;
        card.dataset.m08Saving = '1';
        if (trigger) trigger.disabled = true;
        setStatus('Guardando respuesta M08…');
        try { const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m08',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),expectedVersion:card.dataset.m08Version,proposal})});const data=await response.json();if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible guardar M08.');card.dataset.m08Version=data.version||card.dataset.m08Version;if(data.finalized!==true){setStatus('Respuesta M08 guardada. Aún hay tratamientos pendientes; permanece en este módulo.');return;}setStatus('Respuesta M08 guardada.');advanceConversation();}catch(error){setStatus(error.message||'No fue posible guardar M08.');}finally{if(trigger)trigger.disabled=false;card.dataset.m08Saving='0';}
    };
    document.querySelectorAll('[data-m08-interpret]').forEach(button => button.addEventListener('click', async () => {
        const card=button.closest('[data-module="M08"]'), utterance=card.querySelector('[data-m08-input]')?.value.trim()||'', result=card.querySelector('[data-m08-result]');
        if(!utterance){setStatus('Escribe una respuesta M08.');return;} button.disabled=true;
        try{const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),modulo:'M08',tratamiento_id:<?=json_encode((int)($m08Pending['id_tratamiento']??0));?>,utterance})});const data=await response.json();if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible validar M08.');const proposal=data.proposal||{};if(proposal.kind==='clarification'||proposal.needs_clarification){if(result){result.textContent=proposal.clarification||'M08 solicita una aclaración.';result.hidden=false;}setStatus(proposal.clarification||'M08 solicita una aclaración. No se guardó ningún dato.');return;}if(proposal.direct_save===true&&proposal.can_confirm===true){await saveM08Proposal(card,proposal,button);return;}if(result){result.textContent=proposal.message||'M08 no pudo validar la respuesta.';result.hidden=false;}setStatus('M08 no pudo validar la respuesta. No se guardó ningún dato.');}catch(error){setStatus(error.message||'No fue posible validar M08.');}finally{button.disabled=false;}
    }));
    const saveM09Proposal = async (card, proposal, trigger = null) => {
        if (!card || card.dataset.m09Saving === '1') return;
        card.dataset.m09Saving = '1';
        if (trigger) trigger.disabled = true;
        setStatus('Guardando respuesta M09…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m09', {method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf}, body:JSON.stringify({idPaciente:Number(main.dataset.patient), expectedVersion:card.dataset.m09Version, proposal})});
            const data = await response.json(); if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible guardar M09.');
            if (data.finalized !== true) throw new Error('La evaluación se guardó, pero M09 no quedó finalizado. La evaluación permanece activa.');
            card.dataset.m09Version = data.version || card.dataset.m09Version;
            setStatus('Evaluación M09 guardada.'); advanceConversation();
        } catch (error) { setStatus(error.message || 'No fue posible guardar M09.'); }
        finally { if (trigger) trigger.disabled = false; card.dataset.m09Saving = '0'; }
    };
    document.querySelectorAll('[data-m09-interpret]').forEach(button => button.addEventListener('click', async () => {
        const card = button.closest('[data-module="M09"]'), utterance = card.querySelector('[data-m09-input]')?.value.trim() || '', result = card.querySelector('[data-m09-result]');
        if (!utterance) { setStatus('Escribe los datos de M09 para revisarlos.'); return; }
        button.disabled = true;
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar', {method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf}, body:JSON.stringify({idPaciente:Number(main.dataset.patient), modulo:'M09', utterance})});
            const data = await response.json(); if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible validar M09.');
            const proposal = data.proposal || {};
            if (proposal.kind === 'clarification' || proposal.needs_clarification) {
                if (result) { result.textContent = proposal.clarification || 'M09 solicita una aclaración.'; result.hidden = false; }
                setStatus(proposal.clarification || 'M09 solicita una aclaración. No se guardó ningún dato.');
                return;
            }
            if (proposal.direct_save === true && proposal.can_confirm === true) { await saveM09Proposal(card, proposal, button); return; }
            if (result) { result.textContent = proposal.message || 'M09 no pudo validar la respuesta.'; result.hidden = false; }
            setStatus('M09 no pudo validar la respuesta. No se guardó ningún dato.');
        } catch (error) { setStatus(error.message || 'No fue posible revisar M09.'); } finally { button.disabled = false; }
    }));
    const localDictationEnabled = main.dataset.localDictationEnabled === '1';
    let localDictation = null;
    let activeRecognitionState = null;
    let m03MicPermissionDenied = false;
    let m03MicPermissionStatus = null;
    let m03DictationRuntime = {target: '', state: 'IDLE', message: ''};
    let refreshM03DictationUi = null;
    const setRecognitionStatus = (state, message) => {
        if (typeof main !== 'undefined' && main) main.dataset.sttState = state;
        if (typeof status !== 'undefined' && status) status.dataset.sttState = state;
        const currentTarget = activeM03DictationTarget();
        if (/^M03:question:[1-9]\d*$/.test(currentTarget)) {
            m03DictationRuntime = {target: currentTarget, state, message: String(message || '').trim()};
            if (state === 'ERROR' && /^Permiso de micrófono denegado\./i.test(m03DictationRuntime.message)) {
                m03MicPermissionDenied = true;
            }
            if (['IDLE', 'TRANSCRIPT_READY', 'LISTENING', 'RECOGNIZING'].includes(state)) {
                m03DictationRuntime.message = '';
            }
            if (typeof refreshM03DictationUi === 'function') window.setTimeout(refreshM03DictationUi, 0);
        }
        const m02Card = document.querySelector('.m02-active-question:not([hidden]):not(.chat-stage-hidden)');
        const inlineStatus = m02Card?.querySelector('[data-m02-dictation-status]');
        if (inlineStatus) {
            inlineStatus.dataset.sttState = state;
            const microphone = m02Card.querySelector('.chat-mic-button');
            if (microphone && ['LISTENING', 'RECOGNIZING', 'STOPPING'].includes(state)) {
                microphone.setAttribute('aria-label', 'Detener dictado');
                microphone.title = 'Detener dictado';
            } else if (microphone && ['IDLE', 'TRANSCRIPT_READY', 'ERROR'].includes(state)) {
                microphone.setAttribute('aria-label', 'Iniciar dictado');
                microphone.title = 'Iniciar dictado local';
            }
            // Interim hypotheses update the input, not this live region.
            if (state !== 'RECOGNIZING') {
                inlineStatus.textContent = String(message || '').trim();
                inlineStatus.hidden = inlineStatus.textContent === '';
            }
            if (status) {
                status.textContent = '';
                status.hidden = true;
            }
            return;
        }
        setStatus(message);
    };
    const speechApi = ('speechSynthesis' in window) ? window.speechSynthesis : null;
    const voicePanel = document.querySelector('[data-voice-panel]');
    const voiceStatus = voicePanel?.querySelector('[data-voice-status]');
    const voiceRepeatButton = voicePanel?.querySelector('[data-voice-repeat]');
    const voiceStopButton = voicePanel?.querySelector('[data-voice-stop]');
    const voiceRateControl = voicePanel?.querySelector('[data-voice-rate]');
    const voiceSelectorPanel = voicePanel?.querySelector('[data-voice-selector]');
    const voiceCompareSelect = voicePanel?.querySelector('[data-voice-compare]');
    const voiceInventoryStatus = voicePanel?.querySelector('[data-voice-inventory]');
    const voiceLocalNotice = voicePanel?.querySelector('[data-voice-local-notice]');
    const voiceSampleSelect = voicePanel?.querySelector('[data-voice-sample]');
    const voicePreviewButton = voicePanel?.querySelector('[data-voice-preview]');
    const voiceCompareButton = voicePanel?.querySelector('[data-voice-compare-start]');
    const voiceApplyButton = voicePanel?.querySelector('[data-voice-apply]');
    const voiceAutoButton = voicePanel?.querySelector('[data-voice-auto]');
    const voiceAppliedStatus = voicePanel?.querySelector('[data-voice-applied-status]');
    const voiceDiagnosticStatus = voicePanel?.querySelector('[data-voice-diagnostic]');
    const voicePreviewStatus = voicePanel?.querySelector('[data-voice-preview-status]');
    let voicePreviewGeneration = 0;
    let voicePreviewDelay = null;
    const updateVoicePreviewActions = (muted = false) => {
        const selectedCount = voiceCompareSelect?.selectedOptions?.length || 0;
        if (voicePreviewButton) voicePreviewButton.disabled = muted || selectedCount < 1;
        if (voiceCompareButton) voiceCompareButton.disabled = muted || selectedCount < 1;
        if (voiceApplyButton) voiceApplyButton.disabled = muted || selectedCount !== 1;
    };
    const voicePreferenceStorageKey = 'clinica-dolor-interview-tts-voice-v1';
    const readVoicePreference = () => {
        try {
            const value = JSON.parse(window.localStorage?.getItem(voicePreferenceStorageKey) || 'null');
            if (!value || typeof value !== 'object') return null;
            const identity = {voiceURI: String(value.voiceURI || ''), lang: String(value.lang || ''), name: String(value.name || '')};
            return identity.lang && (identity.voiceURI || identity.name) ? identity : null;
        } catch (error) { return null; }
    };
    const writeVoicePreference = identity => {
        try {
            if (!identity) window.localStorage?.removeItem(voicePreferenceStorageKey);
            else window.localStorage?.setItem(voicePreferenceStorageKey, JSON.stringify({voiceURI: identity.voiceURI, lang: identity.lang, name: identity.name}));
            return true;
        } catch (error) { return false; }
    };
    let voiceController = null;
    let syncAvatarRepeatControls = () => {};
    let syncAvatarStopControls = () => {};
    let syncAvatarHelpControls = () => {};
    const renderVoiceSelection = () => {
        if (!voiceController) return;
        const selection = voiceController.getVoiceSelection();
        if (voiceAppliedStatus) {
            if (!selection.preferred) voiceAppliedStatus.textContent = 'Usando selección automática de Chrome.';
            else if (selection.available) voiceAppliedStatus.textContent = `Voz aplicada a la entrevista: ${selection.applied.name} (${selection.applied.lang}).`;
            else if (selection.effective) voiceAppliedStatus.textContent = `La voz guardada no está disponible. Se usa temporalmente ${selection.effective.name} (${selection.effective.lang}); se volverá a comprobar cuando Chrome actualice sus voces.`;
            else voiceAppliedStatus.textContent = 'La voz guardada no está disponible y Chrome aún no ofrece una voz alternativa.';
        }
        if (voiceDiagnosticStatus) {
            const diagnostic = voiceController.getVoiceDiagnostic();
            const voiceSummary = identity => identity
                ? `${identity.name || 'voz sin nombre'} [${identity.voiceURI || 'voiceURI no informado'}; ${identity.lang || 'idioma no informado'}]`
                : 'automática / no disponible';
            voiceDiagnosticStatus.textContent = diagnostic
                ? `Diagnóstico local (${diagnostic.context === 'preview' ? 'muestra ficticia' : 'pregunta de entrevista'}; fuente=${diagnostic.source || 'web_speech'}; sin texto clínico): solicitada=${voiceSummary(diagnostic.requested)}; efectiva=${voiceSummary(diagnostic.effective)}; rate=${diagnostic.rate}; pitch=${diagnostic.pitch}; volume=${diagnostic.volume}; fallback=${diagnostic.fallbackReason || 'no'}.`
                : 'Diagnóstico local: aún no hay reproducción.';
        }
    };
    const renderVoiceInventory = inventory => {
        if (!voiceCompareSelect) return;
        const previousSelection = Array.from(voiceCompareSelect.selectedOptions).map(option => option.value);
        const spanishVoices = (Array.isArray(inventory) ? inventory : [])
            .filter(entry => /^es(?:[-_]|$)/i.test(entry.lang))
            .sort((left, right) => {
                const localeRank = entry => /^es[-_]mx$/i.test(entry.lang) ? 0 : 1;
                const rankDifference = localeRank(left) - localeRank(right);
                if (rankDifference) return rankDifference;
                const daliaRank = entry => /microsoft\s+dalia/i.test(entry.name) ? 0 : 1;
                return daliaRank(left) - daliaRank(right) || left.name.localeCompare(right.name, 'es');
            });
        while (voiceCompareSelect.firstChild) voiceCompareSelect.removeChild(voiceCompareSelect.firstChild);
        spanishVoices.forEach(entry => {
            const option = document.createElement('option');
            const localLabel = entry.localService === true ? 'local según Chrome' : entry.localService === false ? 'no local según Chrome' : 'localidad no informada';
            option.value = entry.key;
            option.textContent = `${entry.name} — ${entry.lang || 'idioma no informado'} · ${localLabel}${entry.default ? ' · predeterminada de Chrome' : ''}`;
            option.dataset.voiceLang = entry.lang;
            option.dataset.voiceLocal = entry.localService === null ? 'unknown' : entry.localService ? 'local' : 'non-local';
            option.selected = previousSelection.includes(entry.key);
            voiceCompareSelect.appendChild(option);
        });
        const mexicanCount = spanishVoices.filter(entry => /^es[-_]mx$/i.test(entry.lang)).length;
        const otherSpanishCount = spanishVoices.length - mexicanCount;
        if (voiceInventoryStatus) {
            voiceInventoryStatus.textContent = spanishVoices.length
                ? `Disponibles en Chrome: ${mexicanCount} es-MX y ${otherSpanishCount} otras es-*. “Local” refleja únicamente el indicador del navegador.`
                : 'Chrome todavía no informa voces en español. La lista se actualizará si llega voiceschanged.';
        }
        if (spanishVoices.length === 0) {
            const emptyOption = document.createElement('option');
            emptyOption.disabled = true;
            emptyOption.textContent = 'No hay voces españolas disponibles';
            voiceCompareSelect.appendChild(emptyOption);
        }
        updateVoicePreviewActions(voicePanel?.dataset.voiceState === 'MUTED');
        updateVoiceLocalNotice();
    };
    const updateVoiceLocalNotice = () => {
        if (!voiceLocalNotice || !voiceCompareSelect) return;
        const selected = Array.from(voiceCompareSelect.selectedOptions);
        if (selected.some(option => option.dataset.voiceLocal === 'non-local')) {
            voiceLocalNotice.textContent = 'Chrome marca al menos una voz como no local; el navegador podría procesar la frase fuera del dispositivo. Solo se usan las muestras ficticias indicadas.';
        } else if (selected.some(option => option.dataset.voiceLocal === 'unknown')) {
            voiceLocalNotice.textContent = 'Chrome no informa la disponibilidad local de al menos una voz seleccionada. No se infiere privacidad por el nombre.';
        } else {
            voiceLocalNotice.textContent = 'Las muestras son frases ficticias y no contienen respuestas ni datos de pacientes.';
        }
    };
    const voiceMuteStorageKey = 'clinica-dolor-interview-voice-muted';
    const readVoiceMutePreference = () => { try { return window.sessionStorage?.getItem(voiceMuteStorageKey) === '1'; } catch (error) { return false; } };
    const writeVoiceMutePreference = muted => { try { window.sessionStorage?.setItem(voiceMuteStorageKey, muted ? '1' : '0'); } catch (error) {} };
    voiceController = new window.PatientInterviewVoiceController({
        speechApi,
        utteranceConstructor: window.SpeechSynthesisUtterance,
        audioConstructor: window.Audio,
        piperAudio: piperM02Audio,
        preferredVoice: readVoicePreference(),
        onVoicesChanged: inventory => { renderVoiceInventory(inventory); renderVoiceSelection(); syncAvatarRepeatControls(); syncAvatarStopControls(); syncAvatarHelpControls(); },
        onStateChange: snapshot => {
            if (!voicePanel) return;
            voicePanel.dataset.voiceState = snapshot.state;
            if (voiceStatus) voiceStatus.textContent = snapshot.audioBlocked
                ? 'El navegador necesita una interacción; pulsa Repetir pregunta para activar la narración vigente.'
                : (({DISABLED:'Activando guía por voz', READY:'Guía por voz lista', SPEAKING:'Hablando', AUDIO_PENDING:'Activando audio…', PAUSED:'Narración pausada', MUTED:'Narración silenciada', VOICE_UNAVAILABLE:'Voz no disponible', VOICE_ERROR:'Error de reproducción'})[snapshot.state] || snapshot.state);
            if (voiceRepeatButton) voiceRepeatButton.disabled = snapshot.state === 'VOICE_UNAVAILABLE';
            if (voiceStopButton) voiceStopButton.disabled = !snapshot.speaking && !snapshot.paused && !snapshot.audioPending && !snapshot.audioBlocked;
            updateVoicePreviewActions(snapshot.muted);
            renderVoiceSelection();
            syncAvatarRepeatControls();
            syncAvatarStopControls(snapshot);
            syncAvatarHelpControls();
        },
        onError: error => setStatus(error?.code === 'AUDIO_BLOCKED'
            ? 'El navegador necesita una interacción para activar el audio.'
            : error?.code === 'QUESTION_TEXT_MISSING'
                ? 'No se pudo resolver la pregunta activa para narrarla. Puedes continuar por escrito.'
                : error?.code === 'PIPER_AUDIO_UNAVAILABLE'
                    ? 'La narración institucional no está disponible en este momento. Puedes continuar con las instrucciones escritas.'
                : 'La narración tuvo un problema. Puedes continuar con las instrucciones escritas.'),
    });
    renderVoiceSelection();
    voiceController.setRate(voiceRateControl?.value || 1);
    voiceController.setMuted(readVoiceMutePreference());
    updateVoicePreviewActions(voiceController.muted);
    const speechSupported = () => voiceController.supported();
    const setVoiceButtonState = button => { button.title = 'Repetir la pregunta activa'; button.setAttribute('aria-label', 'Repetir pregunta'); button.disabled = !speechSupported(); };
    const resetVoiceButtons = () => document.querySelectorAll('.chat-voice-button').forEach(setVoiceButtonState);
    const stopAssistantVoice = () => { voiceController.cancel(); resetVoiceButtons(); };
    const ensureLocalDictation = () => {
        if (localDictation) return localDictation;
        const DictationClient = window.PatientInterviewLocalDictation;
        if (typeof DictationClient !== 'function') return null;
        try {
            localDictation = new DictationClient({
                ticketUrl: main.dataset.dictationTicketUrl,
                workletUrl: main.dataset.dictationWorkletUrl,
                getPatientId: () => Number(main.dataset.patient),
                getCsrf: () => main.dataset.csrf,
                getContractVersion: () => {
                    const target = activeM03DictationTarget();
                    const m03Match = /^M03:question:([1-9]\d*)$/.exec(target);
                    if (m03Match) {
                        const card = questions.find(item => String(item.dataset.questionId || '') === m03Match[1]);
                        return String(card?.dataset.m03Version || conversationCoordinator.questionContract(target)?.response?.version || '');
                    }
                    return String(main.dataset.conversationContractVersion || '');
                },
                getTarget: () => activeM03DictationTarget(),
                isPaused: () => conversationCoordinator.snapshot().paused,
                isTargetAuthorized: (target, input) => {
                    const targetModule = String(target || '').split(':', 1)[0];
                    if (targetModule === 'M02') return diagnoseM02DictationEligibility(target, input).allowed;
                    if (targetModule === 'M03') return diagnoseM03DictationEligibility(target, input).allowed;
                    return false;
                },
                stopVoice: stopAssistantVoice,
                setStatus: setRecognitionStatus,
            });
        } catch (_) {
            localDictation = null;
        }
        return localDictation;
    };
    ensureLocalDictation();
    const m02ErrorMessages = Object.freeze({
        EMPTY_RESPONSE: 'Escribe una respuesta para revisarla.',
        INVALID_RESPONSE: 'M02 no pudo validar la respuesta. No se guardó ningún dato.',
        INTERPRET_FAILED: 'No fue posible revisar tu respuesta. Puedes intentarlo de nuevo.',
        SAVE_FAILED: 'No pude confirmar el guardado. Revisa la respuesta antes de volver a intentar.',
        READBACK_FAILED: 'No pude confirmar que tu respuesta quedara guardada. No avancé.',
    });
    const m03TobaccoInvalidErrorCode = 'M03_TOBACCO_ANSWER_AMBIGUOUS';
    const m03TobaccoInvalidErrorText = 'No pude identificar tu respuesta. Para continuar, dime claramente si fumas cigarros de nicotina. Puedes responder: sí, fumo; no, no fumo; no lo sé o no lo recuerdo. Tu respuesta no se guardó. Puedes intentarlo nuevamente.';
    const announceM03TobaccoInvalidResponse = async (card, proposal, viewRevision) => {
        const stableId = 'M03:question:1';
        const snapshot = conversationCoordinator.snapshot();
        const canonical = conversationCoordinator.questionContract(stableId);
        if (proposal?.error_code !== m03TobaccoInvalidErrorCode
            || String(card?.dataset.questionId || '') !== '1'
            || String(card?.dataset.themeId || '') !== '1'
            || !isCurrentM03View(card, viewRevision)
            || snapshot.currentTarget !== stableId
            || canonical?.module_id !== 'M03'
            || canonical?.stable_id !== stableId
            || canonical?.question_id !== 1) return false;
        const operationKey = `${snapshot.revision}|${snapshot.operation_generation}`;
        if (card.dataset.m03LastAmbiguousPlaybackKey === operationKey) return false;
        card.dataset.m03LastAmbiguousPlaybackKey = operationKey;

        if (localDictation?.state) {
            try { await stopRecognition(true); } catch (error) {}
        }
        if (!isCurrentM03View(card, viewRevision)
            || conversationCoordinator.snapshot().currentTarget !== stableId) return false;

        voiceController?.cancel();
        const text = String(proposal.clarification || '');
        setStatus(text || m03TobaccoInvalidErrorText);
        // This audio is bound to one explicit server classification. It never
        // submits, clears the draft, advances, or starts microphone capture.
        if (text === m03TobaccoInvalidErrorText && voiceController?.enabled && !voiceController.muted) {
            voiceController.speakEvent({
                type: 'ERROR', module_id: 'M03', error_code: m03TobaccoInvalidErrorCode,
                stable_id: 'M03:error:tobacco-answer-ambiguous', question_stable_id: stableId,
                text, revision: snapshot.revision, operation_generation: snapshot.operation_generation,
            }, {force: true});
        }
        return true;
    };
    const announceM02Error = (card, code) => {
        if (code === 'EMPTY_RESPONSE') return announceEmptyResponse(card, 'M02');
        const text = m02ErrorMessages[code];
        const stableId = String(card?.dataset.stableId || '');
        const snapshot = conversationCoordinator.snapshot();
        const question = stableId && stableId === snapshot.currentTarget
            ? conversationCoordinator.questionContract(stableId)
            : null;
        if (!text || !question || question.module_id !== 'M02' || question.stable_id !== stableId) return false;
        const errorKey = `${code}|${stableId}`;
        if (card.dataset.m02LastErrorKey === errorKey) return false;
        card.dataset.m02LastErrorKey = errorKey;
        setStatus(text);
        if (voiceController?.enabled && !voiceController.muted) {
            voiceController.speakEvent({
                type: 'ERROR', module_id: 'M02', error_code: code,
                stable_id: `M02:error:${code}`, question_stable_id: stableId, text,
            }, {force: true});
        }
        return true;
    };
    const announceEmptyResponse = (card, moduleId) => {
        const stableId = moduleId === 'M02'
            ? String(card?.dataset.stableId || '')
            : `M03:question:${String(card?.dataset.questionId || '')}`;
        const snapshot = conversationCoordinator.snapshot();
        const question = stableId && stableId === snapshot.currentTarget
            ? conversationCoordinator.questionContract(stableId)
            : null;
        if (!question || question.module_id !== moduleId || question.stable_id !== stableId) return false;
        const errorKey = `EMPTY_RESPONSE|${stableId}`;
        if (card.dataset.lastEmptyResponseErrorKey === errorKey) return false;
        card.dataset.lastEmptyResponseErrorKey = errorKey;
        const text = m02ErrorMessages.EMPTY_RESPONSE;
        setStatus(text);
        // Cancel the active question or stale error even when voice is muted;
        // speakEvent will then select the signed Piper clip (or safe fallback).
        voiceController?.cancel();
        if (voiceController?.enabled && !voiceController.muted) {
            voiceController.speakEvent({
                type: 'ERROR', module_id: moduleId, error_code: 'EMPTY_RESPONSE',
                stable_id: 'M02:error:EMPTY_RESPONSE', question_stable_id: stableId, text,
            }, {force: true});
        }
        return true;
    };
    const moduleBlockedVoiceEventFor = target => {
        const stage = conversationStages[conversationStage];
        if (target !== 'M07' || !isM07BlockedStage(stage)) return null;
        const event = conversationCoordinator.voiceEvent('MODULE_BLOCKED', target, false);
        return event.text ? {...event, type: 'MODULE_BLOCKED', stable_id: target} : null;
    };
    const handleVoiceEvent = event => {
        if (!event || ['SAVING', 'SUCCESS', 'ERROR', 'SESSION_EXPIRED'].includes(event.type)) return false;
        if (event.type === 'CLARIFICATION' && event.stable_id === 'M03:question:1') {
            const question = conversationCoordinator.questionContract(event.stable_id);
            if (question?.module_id === 'M03' && question?.question_id === 1) {
                voiceController?.cancel();
                return false;
            }
        }
        if (event.type === 'RESUME') {
            const current = conversationCoordinator.snapshot().currentTarget;
            const question = moduleBlockedVoiceEventFor(current) || conversationCoordinator.voiceEvent('QUESTION', current, false);
            if (!question.text || question.stable_id !== current) return false;
            const canonicalQuestion = conversationCoordinator.questionContract(current);
            return voiceController.handleEvent(canonicalQuestion?.module_id === 'M02' ? {...question, module_id: 'M02'} : question);
        }
        if (event.action_id) {
            const current = conversationCoordinator.snapshot().currentTarget;
            const action = conversationCoordinator.actionContract(current);
            if (!event.text || !action || event.action_id !== current || action.action_id !== current) return false;
            return voiceController.handleEvent(action.module_id === 'M02' ? {...event, module_id: 'M02'} : event);
        }
        if (event.type !== 'QUESTION') return voiceController.handleEvent(event);
        if (!event.text || !event.stable_id || event.stable_id !== conversationCoordinator.snapshot().currentTarget) return false;
        const question = conversationCoordinator.questionContract(event.stable_id);
        return voiceController.handleEvent(question?.module_id === 'M02' ? {...event, module_id: 'M02'} : event);
    };
    const stableTargetForMessage = message => {
        const question = message?.closest('.patient-interview-question');
        if (question?.dataset.questionId) return `M03:question:${question.dataset.questionId}`;
        const card = message?.closest('.interview-module-card');
        const module = card?.dataset.module || '';
        if (module === 'M02') {
            const actionTitle = message.querySelector('[data-m02-action-id]');
            const actionId = actionTitle?.dataset.m02ActionId || '';
            const action = actionId ? conversationCoordinator.actionContract(actionId) : null;
            if (action?.module_id === 'M02' && action.action_id === actionId) return actionId;
            const stableId = card.dataset.stableId || '';
            const canonicalQuestion = conversationCoordinator.questionContract(stableId);
            return canonicalQuestion?.module_id === 'M02' && canonicalQuestion.stable_id === stableId ? stableId : null;
        }
        if (module === 'M04' || module === 'M05' || module === 'M05-V2' || module === 'M06' || module === 'M07') {
            const stage = conversationStages.find(candidate => candidate.card === card) || {card};
            return voiceTargetForStage(stage) || conversationCoordinator.snapshot().currentTarget;
        }
        if (module === 'M09-MAP') return 'M09:body-map';
        if (module) return module;
        return conversationCoordinator.snapshot().currentTarget;
    };
    const speakM02Followup = (card, text) => {
        const target = String(card?.dataset.stableId || '').trim();
        const snapshot = conversationCoordinator.snapshot();
        if (!target || target !== snapshot.currentTarget || !text || !voiceController?.enabled || voiceController.muted) return false;
        const questionEvent = conversationCoordinator.voiceEvent('QUESTION', target, false);
        if (!questionEvent?.text || questionEvent.stable_id !== target) return false;
        return voiceController.speakEvent({...questionEvent, module_id: 'M02', text: String(text)}, {
            force: true,
            automatic: voiceController.automaticMode,
        });
    };
    const resolveActiveRepeatEvent = (avatarOnly = false) => {
        const activeCard = questions[active];
        const snapshot = conversationCoordinator.snapshot();
        const scopedVoiceTarget = m03VoiceTargetForCard(activeCard);
        const activeTarget = scopedVoiceTarget || snapshot.currentTarget;
        if (!activeTarget || (avatarOnly && /^(?:M04|M07):/.test(activeTarget))) return null;
        const visibleMessages = Array.from(document.querySelectorAll(
            '.patient-interview-question:not([hidden]):not(.chat-stage-hidden) > .patient-interview-message.assistant, .interview-module-card:not([hidden]):not(.chat-stage-hidden) > .patient-interview-message.assistant'
        ));
        if (!visibleMessages.some(message => stableTargetForMessage(message) === activeTarget)) return null;
        const blockedEvent = moduleBlockedVoiceEventFor(activeTarget);
        const action = conversationCoordinator.actionContract(activeTarget);
        const event = blockedEvent || conversationCoordinator.voiceEvent(action ? 'ACTION' : 'QUESTION', activeTarget, false);
        const targetMatches = blockedEvent
            ? event?.stable_id === activeTarget
            : action
                ? event?.action_id === activeTarget && event?.stable_id === null
                : event?.stable_id === activeTarget;
        if (!event?.text || !targetMatches) return null;
        if (!blockedEvent && action?.module_id === 'M02') event.module_id = 'M02';
        else if (!blockedEvent && conversationCoordinator.questionContract(activeTarget)?.module_id === 'M02') event.module_id = 'M02';
        return {target: activeTarget, event};
    };
    const refreshAvatarRepeatControls = () => {
        const speechReady = speechSupported();
        const repeatable = Boolean(speechReady && resolveActiveRepeatEvent(true));
        const reason = !speechReady
            ? 'La narración por voz no está disponible en este navegador.'
            : repeatable
                ? ''
                : 'No hay una pregunta activa disponible para repetir.';
        document.querySelectorAll('[data-doctor-repeat]').forEach(button => {
            button.disabled = !repeatable;
            button.setAttribute('aria-disabled', repeatable ? 'false' : 'true');
            button.setAttribute('aria-label', repeatable ? 'Repetir pregunta' : `Repetir pregunta. ${reason}`);
            button.title = repeatable ? 'Repetir la pregunta clínica activa' : reason;
        });
        const statusNode = document.getElementById('patient-interview-doctor-repeat-status');
        if (statusNode && statusNode.textContent !== reason) statusNode.textContent = reason;
    };
    syncAvatarRepeatControls = refreshAvatarRepeatControls;
    const refreshAvatarStopControls = (snapshot = null) => {
        const snap = snapshot || (voiceController ? voiceController.snapshot() : null);
        const active = Boolean(snap && (snap.speaking || snap.paused || snap.audioPending || snap.audioBlocked));
        document.querySelectorAll('[data-doctor-stop]').forEach(button => {
            button.disabled = !active;
            button.setAttribute('aria-disabled', active ? 'false' : 'true');
            button.setAttribute('aria-label', active ? 'Detener audio' : 'Detener audio (no hay audio activo)');
            button.title = active ? 'Detener la narración activa' : 'No hay una reproducción activa para detener.';
        });
    };
    syncAvatarStopControls = refreshAvatarStopControls;
    const repeatActiveQuestion = (button = null) => {
        const avatarOnly = button?.hasAttribute('data-doctor-repeat') === true;
        const resolved = resolveActiveRepeatEvent(avatarOnly);
        const activeTarget = resolved?.target || '';
        const buttonTarget = button?.dataset.voiceTarget || activeTarget;
        if (!activeTarget || buttonTarget !== activeTarget || !resolved) {
            stopAssistantVoice();
            setStatus('La pregunta cambió. Selecciona la pregunta activa para repetirla.');
            return false;
        }
        if (!speechSupported()) {
            setStatus('La narración por voz no está disponible. Puedes seguir con las instrucciones escritas.');
            return false;
        }
        const event = resolved.event;
        if (String(activeTarget).startsWith('M03:') && typeof stopRecognition === 'function') void stopRecognition(true);
        stopAssistantVoice();
        const voiceState = voiceController.snapshot();
        let started;
        if (voiceController.muted) writeVoiceMutePreference(false);
        if (voiceController.muted || !voiceController.enabled || !voiceController.lastEvent || voiceState.audioBlocked) {
            started = voiceController.activate(event, null, {userGesture:true});
        } else {
            started = voiceController.speakEvent(event, {force:true, userGesture:true});
        }
        if (button && !button.hasAttribute('data-doctor-repeat')) setVoiceButtonState(button);
        refreshAvatarStopControls();
        return started;
    };
    const bindDoctorRepeatControls = () => {
        document.querySelectorAll('[data-doctor-repeat]:not([data-doctor-repeat-bound])').forEach(button => {
            button.addEventListener('click', () => repeatActiveQuestion(button));
            button.dataset.doctorRepeatBound = '1';
        });
        refreshAvatarRepeatControls();
    };
    bindDoctorRepeatControls();
    const bindDoctorStopControls = () => {
        document.querySelectorAll('[data-doctor-stop]:not([data-doctor-stop-bound])').forEach(button => {
            button.addEventListener('click', () => {
                stopAssistantVoice();
                refreshAvatarStopControls();
            });
            button.dataset.doctorStopBound = '1';
        });
        refreshAvatarStopControls();
    };
    bindDoctorStopControls();
    const resolveActiveHelpTarget = () => {
        const snapshot = conversationCoordinator.snapshot();
        const activeCard = questions[active];
        const visibleM03ReviewTarget = (m03ReviewOnly || m03PreviousEditOnly) && m03ReviewTarget
            && activeCard?.dataset.questionId
            && m03ReviewTarget === `M03:question:${activeCard.dataset.questionId}`
            ? m03ReviewTarget
            : '';
        const target = visibleM03ReviewTarget || snapshot.currentTarget;
        if (!target) return null;
        const visibleMessages = Array.from(document.querySelectorAll(
            '.patient-interview-question:not([hidden]):not(.chat-stage-hidden) > .patient-interview-message.assistant, .interview-module-card:not([hidden]):not(.chat-stage-hidden) > .patient-interview-message.assistant'
        ));
        const message = visibleMessages.find(candidate => stableTargetForMessage(candidate) === target) || null;
        if (!message) return null;
        const blockedEvent = moduleBlockedVoiceEventFor(target);
        if (blockedEvent) return null;
        const action = conversationCoordinator.actionContract(target);
        const question = action ? null : conversationCoordinator.questionContract(target);
        const event = conversationCoordinator.voiceEvent('HELP', target, false);
        const isM04ObservationQuestion = question?.stable_id === 'M04:record:new:observations';
        const extraM04UnknownHelp = isM04ObservationQuestion
            ? ' Si no recuerda una respuesta, puede seleccionar No recuerdo.'
            : '';
        const helpText = action?.module_id === 'M02' && action.action_id === 'M02:action:finalize'
            ? String(message.querySelector('[data-m02-action-id="M02:action:finalize"]')?.dataset.m02FinalizeHelpText || '').trim()
            : `${String(question?.help || event?.text || '').trim()}${extraM04UnknownHelp}`;
        if ((!action && (!question || question.stable_id !== target))
            || (action && (action.module_id !== 'M02' || action.action_id !== 'M02:action:finalize' || action.stable_id !== null))
            || !helpText) {
            return null;
        }
        return {target, message, helpText};
    };
    const refreshAvatarHelpControls = () => {
        const resolved = resolveActiveHelpTarget();
        const helpAvailable = Boolean(resolved);
        const reason = helpAvailable
            ? ''
            : 'No hay ayuda disponible para este paso.';
        document.querySelectorAll('[data-doctor-help]').forEach(button => {
            button.disabled = !helpAvailable;
            button.setAttribute('aria-disabled', helpAvailable ? 'false' : 'true');
            button.setAttribute('aria-label', helpAvailable ? 'Ayuda con esta pregunta' : `Ayuda con esta pregunta. ${reason}`);
            button.title = helpAvailable ? 'Ayuda sobre la pregunta activa' : reason;
        });
    };
    syncAvatarHelpControls = refreshAvatarHelpControls;
    const bindDoctorHelpControls = () => {
        document.querySelectorAll('[data-doctor-help]:not([data-doctor-help-bound])').forEach(button => {
            button.addEventListener('click', () => showContractHelp(button));
            button.dataset.doctorHelpBound = '1';
        });
        refreshAvatarHelpControls();
    };
    bindDoctorHelpControls();
    clearContextualHelp = () => document.querySelectorAll('[data-question-help]').forEach(panel => {
        panel.textContent = '';
        panel.hidden = true;
    });
    const showContractHelp = button => {
        const snapshot = conversationCoordinator.snapshot();
        const activeCard = questions[active];
        const visibleM03ReviewTarget = (m03ReviewOnly || m03PreviousEditOnly) && m03ReviewTarget
            && activeCard?.dataset.questionId
            && m03ReviewTarget === `M03:question:${activeCard.dataset.questionId}`
            ? m03ReviewTarget
            : '';
        const isAvatar = button?.hasAttribute('data-doctor-help') === true || !button?.closest('.patient-interview-question');
        const target = visibleM03ReviewTarget && (isAvatar || button?.closest('.patient-interview-question') === activeCard)
            ? visibleM03ReviewTarget
            : snapshot.currentTarget;
        if (String(target || '').startsWith('M03:') && typeof stopRecognition === 'function') void stopRecognition(true);
        const visibleMessages = Array.from(document.querySelectorAll(
            '.patient-interview-question:not([hidden]):not(.chat-stage-hidden) > .patient-interview-message.assistant, .interview-module-card:not([hidden]):not(.chat-stage-hidden) > .patient-interview-message.assistant'
        ));
        const message = visibleMessages.find(candidate => stableTargetForMessage(candidate) === target) || null;
        if (!target || !message) {
            stopAssistantVoice();
            setStatus('La pregunta activa se está actualizando. Intenta abrir su ayuda de nuevo.');
            return;
        }
        if (button && button.closest('.patient-interview-message.assistant') === message) button.dataset.voiceTarget = target;
        const blockedEvent = moduleBlockedVoiceEventFor(target);
        if (blockedEvent) {
            stopAssistantVoice();
            setStatus(blockedEvent.text || 'Esta sección permanece bloqueada.');
            return;
        }
        const action = conversationCoordinator.actionContract(target);
        const question = action ? null : conversationCoordinator.questionContract(target);
        const event = conversationCoordinator.voiceEvent('HELP', target, false);
        const isM04ObservationQuestion = question?.stable_id === 'M04:record:new:observations';
        const extraM04UnknownHelp = isM04ObservationQuestion
            ? ' Si no recuerda una respuesta, puede seleccionar No recuerdo.'
            : '';
        const helpText = action?.module_id === 'M02' && action.action_id === 'M02:action:finalize'
            ? String(message.querySelector('[data-m02-action-id="M02:action:finalize"]')?.dataset.m02FinalizeHelpText || '').trim()
            : `${String(question?.help || event.text || '').trim()}${extraM04UnknownHelp}`;
        stopAssistantVoice();
        if ((!action && (!question || question.stable_id !== target))
            || (action && (action.module_id !== 'M02' || action.action_id !== 'M02:action:finalize' || action.stable_id !== null))
            || !helpText) {
            setStatus('No hay una explicación adicional disponible para este paso. Puedes continuar con el control disponible.');
            return;
        }
        let panel = message.querySelector('[data-question-help]');
        if (!panel) {
            panel = document.createElement('p');
            panel.className = 'interview-contextual-help visually-hidden';
            panel.dataset.questionHelp = '1';
            panel.setAttribute('role', 'note');
            panel.setAttribute('aria-live', 'polite');
            message.appendChild(panel);
        } else if (!panel.classList.contains('visually-hidden')) {
            panel.classList.add('visually-hidden');
        }
        panel.textContent = helpText;
        panel.hidden = false;
        setStatus('');
        const helpEvent = {...event, type: 'HELP', module_id: action?.module_id || question.module_id, action_id: action?.action_id || null,
            stable_id: action ? null : question.stable_id, text: helpText};
        if (speechSupported() && isM04ObservationQuestion && piperM02Audio.m04?.enabled === true) {
            const helpIds = ['M04:help:3', 'M04:help:4'];
            const events = helpIds.map(helpId => {
                const entry = piperM02Audio.m04.help_audio?.[helpId];
                return entry ? {...helpEvent, text: entry.text, help_id: helpId} : null;
            }).filter(Boolean);
            if (events.length === helpIds.length) voiceController.speakSequence(events, {force:true, userGesture:true});
            else voiceController.speakEvent(helpEvent, {force:true, userGesture:true});
        } else if (speechSupported()) voiceController.speakEvent(helpEvent, {force:true, userGesture:true});
    };
    const startRecognition = async (button, input) => {
        if (conversationContract.modules?.M02?.conversation_state?.state === 'READY_TO_FINALIZE') {
            setRecognitionStatus('IDLE', '');
            return false;
        }
        const target = activeM03DictationTarget();
        const moduleId = String(target || '').split(':')[0];
        if (moduleId === 'M03' && (m03ReviewOnly || (m03PreviousEditOnly && target !== m03ReviewTarget))) {
            setRecognitionStatus('IDLE', '');
            return false;
        }
        if (moduleId === 'M03') ensureLocalDictation();
        const eligibility = moduleId === 'M02'
            ? diagnoseM02DictationEligibility(target, input)
            : moduleId === 'M03'
                ? diagnoseM03DictationEligibility(target, input)
                : {allowed: false, code: 'MODULE_NOT_AUTHORIZED'};
        if (!eligibility.allowed) {
            const message = moduleId === 'M03'
                ? 'No se pudo iniciar el micrófono.'
                : main.dataset.m02DictationDiagnostics === '1'
                    ? `${moduleId}_DICTATION_BLOCKED:${eligibility.code}`
                    : `El dictado solo está disponible para la pregunta ${moduleId} activa y editable.`;
            setRecognitionStatus('UNAVAILABLE', message);
            return false;
        }
        if (!localDictationEnabled) {
            setRecognitionStatus('UNAVAILABLE', 'El dictado local no está habilitado en este entorno. Puedes responder por escrito.');
            return false;
        }
        ensureLocalDictation();
        if (typeof localDictation?.start !== 'function') {
            setRecognitionStatus('UNAVAILABLE', 'El cliente local de dictado no se pudo inicializar. Puedes responder por escrito.');
            return false;
        }
        const started = await localDictation.start(button, input);
        activeRecognitionState = localDictation.state;
        return started;
    };
    const stopRecognition = (abort = true) => {
        activeRecognitionState = localDictation?.state || null;
        const stopped = localDictation?.stop(abort) || Promise.resolve();
        return stopped.finally(() => { activeRecognitionState = localDictation?.state || null; });
    };
    const startupVoiceEvents = () => {
        const snapshot = conversationCoordinator.snapshot();
        if (!snapshot.currentTarget) { setStatus('No hay una pregunta disponible para iniciar la guía por voz.'); return null; }
        const action = conversationCoordinator.actionContract(snapshot.currentTarget);
        const blockedM07 = moduleBlockedVoiceEventFor(snapshot.currentTarget);
        const question = blockedM07 || conversationCoordinator.voiceEvent(action ? 'ACTION' : 'QUESTION', snapshot.currentTarget, false);
        const canonicalQuestion = action ? null : conversationCoordinator.questionContract(snapshot.currentTarget);
        const moduleId = action?.module_id || canonicalQuestion?.module_id;
        const targetValid = blockedM07
            ? question.stable_id === snapshot.currentTarget
            : action
                ? question.action_id === snapshot.currentTarget && question.stable_id === null
                : Boolean(question.stable_id && question.stable_id === snapshot.currentTarget);
        if (!question.text || !targetValid) {
            voiceController.reportError({code: 'QUESTION_TEXT_MISSING'});
            return null;
        }
        return {question: !blockedM07 && moduleId === 'M02' ? {...question, module_id: 'M02'} : question};
    };
    const autoStartVoice = () => {
        const snapshot = conversationCoordinator.snapshot();
        if (!speechSupported() || voiceController.enabled || voiceController.muted || snapshot.paused) return;
        const startup = startupVoiceEvents();
        if (!startup) return;
        const question = startup.question;
        voiceController.activateAutomatically(question);
    };
    const repeatOrUnlockVoice = () => repeatActiveQuestion();
    voiceRepeatButton?.addEventListener('click', repeatOrUnlockVoice);
    voiceStopButton?.addEventListener('click', () => stopAssistantVoice());
    voiceRateControl?.addEventListener('change', () => voiceController.setRate(voiceRateControl.value));
    voiceCompareSelect?.addEventListener('change', () => {
        const selected = Array.from(voiceCompareSelect.selectedOptions);
        if (selected.length > 3) {
            selected.slice(3).forEach(option => { option.selected = false; });
            if (voiceInventoryStatus) voiceInventoryStatus.textContent = 'Puedes comparar hasta tres voces a la vez.';
        }
        updateVoicePreviewActions(voiceController.muted);
        updateVoiceLocalNotice();
        renderVoiceSelection();
    });
    const selectedPreviewVoices = () => Array.from(voiceCompareSelect?.selectedOptions || [])
        .filter(option => !option.disabled)
        .slice(0, 3)
        .map(option => ({
            voice: voiceController.voiceForKey(option.value),
            name: option.textContent.split(' — ')[0],
            lang: option.dataset.voiceLang || '',
            local: option.dataset.voiceLocal || 'unknown',
        }))
        .filter(entry => entry.voice);
    voiceApplyButton?.addEventListener('click', () => {
        const selected = selectedPreviewVoices();
        if (selected.length !== 1) {
            setPreviewStatus('Para aplicar una voz, selecciona exactamente una en la lista.');
            return;
        }
        const voice = selected[0].voice;
        const identity = {voiceURI: String(voice.voiceURI || ''), lang: String(voice.lang || ''), name: String(voice.name || '')};
        const selection = voiceController.setPreferredVoice(identity);
        const persisted = writeVoicePreference(identity);
        renderVoiceSelection();
        setPreviewStatus(persisted
            ? `Voz aplicada a las próximas preguntas y a Repetir: ${identity.name} (${identity.lang}).`
            : `Voz aplicada durante esta página: ${identity.name} (${identity.lang}), pero el navegador no permitió guardarla para futuras visitas.`);
        if (!selection.available) setPreviewStatus('La voz seleccionada dejó de estar disponible; se mantendrá el fallback automático hasta que Chrome la vuelva a informar.');
    });
    voiceAutoButton?.addEventListener('click', () => {
        voiceController.setPreferredVoice(null);
        const persisted = writeVoicePreference(null);
        renderVoiceSelection();
        setPreviewStatus(persisted ? 'Se restauró la selección automática de Chrome.' : 'Se usa la selección automática durante esta página.');
    });
    const setPreviewStatus = message => { if (voicePreviewStatus) voicePreviewStatus.textContent = message; };
    const startVoicePreview = compare => {
        const voices = selectedPreviewVoices();
        if (!voices.length) { setPreviewStatus('Selecciona al menos una voz disponible en español.'); return; }
        const text = String(voiceSampleSelect?.value || '').trim();
        if (!text) { setPreviewStatus('No hay una frase ficticia disponible para la muestra.'); return; }
        voicePreviewGeneration += 1;
        const generation = voicePreviewGeneration;
        if (voicePreviewDelay !== null) { clearTimeout(voicePreviewDelay); voicePreviewDelay = null; }
        voiceController.cancel();
        const queue = compare ? voices.slice(0, 3) : voices.slice(0, 1);
        const playAt = index => {
            if (generation !== voicePreviewGeneration) return;
            const entry = queue[index];
            if (!entry) return;
            setPreviewStatus(`Muestra ${index + 1} de ${queue.length}: esperando inicio · ${entry.name} (${entry.lang}).`);
            const started = voiceController.preview(text, entry.voice, {
                lang: 'es-MX',
                rate: Number(voiceRateControl?.value || .95),
                pitch: 1,
                volume: 1,
                onStart: () => { if (generation === voicePreviewGeneration) setPreviewStatus(`onstart: reproducción iniciada · ${entry.name}. El evento no confirma calidad audible.`); },
                onEnd: () => {
                    if (generation !== voicePreviewGeneration) return;
                    if (index + 1 < queue.length) {
                        setPreviewStatus(`onend: terminó la solicitud de ${entry.name}; sigue la voz ${index + 2} de ${queue.length}.`);
                        voicePreviewDelay = setTimeout(() => { voicePreviewDelay = null; playAt(index + 1); }, 450);
                    } else {
                        const voiceCountLabel = queue.length === 1 ? 'voz' : 'voces';
                        setPreviewStatus(`onend: terminó la solicitud de muestra para ${queue.length} ${voiceCountLabel}. Confirma personalmente la calidad del audio.`);
                    }
                },
                onError: () => { if (generation === voicePreviewGeneration) setPreviewStatus(`onerror: Chrome no pudo completar la muestra de ${entry.name}.`); },
                onCancel: () => { if (generation === voicePreviewGeneration) setPreviewStatus(`cancel: muestra de ${entry.name} interrumpida.`); },
            });
            if (!started && generation === voicePreviewGeneration) {
                setPreviewStatus(voiceController.muted
                    ? 'La guía está silenciada. Activa el sonido para escuchar una muestra.'
                    : `No se pudo iniciar la muestra de ${entry.name}.`);
            }
        };
        playAt(0);
    };
    voicePreviewButton?.addEventListener('click', () => startVoicePreview(false));
    voiceCompareButton?.addEventListener('click', () => startVoicePreview(true));
    if (!speechSupported()) {
        if (voiceRepeatButton) voiceRepeatButton.disabled = true;
        if (voiceStatus) voiceStatus.textContent = 'La narración por voz no está disponible. Puedes seguir con las instrucciones escritas.';
    }
    const syncM03DictationPermission = () => {
        const m03IsActive = conversationStages[conversationStage]?.type === 'm03';
        const activeQuestion = m03IsActive ? questions[active] : null;
        const resolverTarget = conversationCoordinator.snapshot().currentTarget;
        const dictationTarget = activeM03DictationTarget();
        const m03Module = conversationContract.modules?.M03;
        const moduleWriteEnabled = m03Module?.state?.write_enabled === true;
        const moduleFinalized = m03Module?.state?.finalized === true;
        document.querySelectorAll('.patient-interview-question[data-question-index]').forEach(card => {
            const host = card.querySelector('.m03-input-wrap');
            const input = host?.querySelector('.interview-text');
            const target = `M03:question:${String(card.dataset.questionId || '')}`;
            const question = conversationCoordinator.questionContract(target);
            const activeCardVisible = card === activeQuestion
                && m03IsActive
                && !card.hidden
                && !card.closest('[hidden], .chat-stage-hidden');
            const responseZone = input?.closest('[data-interview-zone="response"]');
            const fieldVisible = activeCardVisible
                && Boolean(input?.matches('.m03-input-wrap > .interview-text'))
                && Boolean(responseZone && !responseZone.hidden)
                && !input.closest('[hidden], .chat-stage-hidden');
            const client = activeCardVisible ? ensureLocalDictation() : localDictation;
            const eligibility = fieldVisible
                ? diagnoseM03DictationEligibility(target, input)
                : {allowed: false, code: 'M03_QUESTION_NOT_VISIBLE'};
            const clientInitialized = typeof client?.supported === 'function' && typeof client?.start === 'function';
            const supported = clientInitialized && client.supported();
            const available = window.PatientInterviewM03DictationUi?.canInitiate({
                featureEnabled: localDictationEnabled,
                clientSupported: supported,
                permissionDenied: m03MicPermissionDenied,
            }) === true;
            const unavailableReason = m03MicPermissionDenied
                ? 'Chrome bloqueó el permiso del micrófono para este sitio. Permítelo en la configuración del navegador y vuelve a cargar la entrevista.'
                : !localDictationEnabled
                    ? 'El dictado local no está habilitado en este entorno. Puedes responder por escrito.'
                    : !clientInitialized
                        ? 'El cliente local de dictado no se pudo inicializar. Puedes responder por escrito.'
                    : !supported
                        ? 'Este navegador no ofrece los requisitos del dictado local. Puedes responder por escrito.'
                        : 'El dictado local no está disponible. Puedes responder por escrito.';
            const runtime = m03DictationRuntime.target === target ? m03DictationRuntime : {state: 'IDLE', message: ''};
            const unavailableReasonCode = m03MicPermissionDenied
                ? 'MICROPHONE_PERMISSION_DENIED'
                : !localDictationEnabled
                    ? 'DICTATION_FEATURE_DISABLED'
                    : !clientInitialized
                        ? 'DICTATION_CLIENT_NOT_INITIALIZED'
                        : !supported
                            ? 'DICTATION_BROWSER_UNSUPPORTED'
                            : 'DICTATION_UNAVAILABLE';
            const syncResult = window.PatientInterviewM03DictationUi?.syncCard({
                card,
                active: activeCardVisible,
                fieldVisible,
                input,
                eligibility,
                available,
                unavailableReason,
                unavailableReasonCode,
                runtimeState: runtime.state,
                runtimeMessage: runtime.message,
                localDiagnostics: main.dataset.m02DictationDiagnostics === '1' ? {
                    module_active: m03IsActive,
                    question_visible: fieldVisible,
                    question_eligible: card.dataset.eligible === '1',
                    question_editable: Boolean(input && !input.disabled && !input.readOnly),
                    module_write_enabled: moduleWriteEnabled,
                    question_contract_available: Boolean(question),
                    question_write_enabled: question?.write_enabled === true,
                    question_response_status: ['pending', 'documented', 'read_only', 'not_applicable'].includes(question?.response?.status) ? question.response.status : 'unknown',
                    question_response_persisted: question?.response?.persisted === true,
                    module_finalized: moduleFinalized,
                    stable_id_matches: target === resolverTarget
                        || (m03PreviousEditOnly && target === dictationTarget && hasM03HistoricalEditAuthorization(card, m03ReviewReturnIndex)),
                    vosk_client_available: supported,
                    microphone_permission_state: m03MicPermissionStatus?.state || 'unsupported_or_unknown',
                } : null,
                onStart: (button, activeInput) => startRecognition(button, activeInput),
            });
            if (main.dataset.m02DictationDiagnostics === '1') {
                card.dataset.m03DictationDiagnostics = JSON.stringify({
                    module_active: m03IsActive,
                    question_visible: fieldVisible,
                    question_eligible: card.dataset.eligible === '1',
                    question_editable: Boolean(input && !input.disabled && !input.readOnly),
                    module_write_enabled: moduleWriteEnabled,
                    question_contract_available: Boolean(question),
                    question_write_enabled: question?.write_enabled === true,
                    question_response_status: ['pending', 'documented', 'read_only', 'not_applicable'].includes(question?.response?.status) ? question.response.status : 'unknown',
                    question_response_persisted: question?.response?.persisted === true,
                    module_finalized: moduleFinalized,
                    stable_id_matches: target === resolverTarget
                        || (m03PreviousEditOnly && target === dictationTarget && hasM03HistoricalEditAuthorization(card, m03ReviewReturnIndex)),
                    vosk_client_available: supported,
                    microphone_permission_state: m03MicPermissionStatus?.state || 'unsupported_or_unknown',
                    button_disabled: syncResult?.mounted ? Boolean(host?.querySelector('[data-m03-dictation-control]')?.disabled) : true,
                    disabled_reason: syncResult?.reasonCode || host?.querySelector('[data-m03-dictation-control]')?.dataset.dictationReason || '',
                    last_disable_function: syncResult?.disabledBy || null,
                });
            } else {
                delete card.dataset.m03DictationDiagnostics;
            }
        });
    };
    refreshM03DictationUi = syncM03DictationPermission;
    window.addEventListener('online', syncM03DictationPermission, {passive: true});
    window.addEventListener('offline', syncM03DictationPermission, {passive: true});
    window.addEventListener('patient-interview:dictation-availability-change', syncM03DictationPermission);
    const syncM03MicPermissionState = () => {
        if (!navigator.permissions?.query) {
            m03MicPermissionDenied = false;
            syncM03DictationPermission();
            return;
        }
        navigator.permissions.query({name: 'microphone'}).then(permission => {
            if (m03MicPermissionStatus !== permission) {
                m03MicPermissionStatus?.removeEventListener?.('change', syncM03MicPermissionState);
                m03MicPermissionStatus = permission;
                permission.addEventListener?.('change', syncM03MicPermissionState);
            }
            m03MicPermissionDenied = permission.state === 'denied';
            syncM03DictationPermission();
        }).catch(() => {
            // An unsupported/indeterminate Permissions API must not suppress the click
            // that lets getUserMedia display Chrome's native permission prompt.
            m03MicPermissionDenied = false;
            syncM03DictationPermission();
        });
    };
    syncM03MicPermissionState();
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) syncM03MicPermissionState();
    });
    function bindVoiceControls() {
        const activeQuestionMessage = document.querySelector('.patient-interview-question:not([hidden]):not(.chat-stage-hidden) > .patient-interview-message.assistant');
        const activeModuleMessage = document.querySelector('.interview-module-card:not(.chat-stage-hidden):not([hidden]) > .patient-interview-message.assistant');
        document.querySelectorAll('.patient-interview-message.assistant[data-voice-bound]').forEach(message => {
            if (message !== activeQuestionMessage && message !== activeModuleMessage) return;
            const coordinatorTarget = conversationCoordinator.snapshot().currentTarget;
            const resolvedTarget = stableTargetForMessage(message);
            const owner = message.closest('.patient-interview-question');
            const scopedVoiceTarget = m03VoiceTargetForCard(owner);
            const activeTarget = scopedVoiceTarget || coordinatorTarget;
            if (!resolvedTarget || resolvedTarget !== activeTarget) return;
            message.querySelectorAll('.chat-voice-button, .chat-help-button').forEach(btn => btn.remove());
            message.querySelectorAll('.chat-voice-button, .chat-help-button').forEach(control => {
                control.dataset.voiceTarget = resolvedTarget;
            });
        });
        document.querySelectorAll('.patient-interview-message.assistant:not([data-voice-bound])').forEach(message => {
            const visible = !message.closest('[hidden], .chat-stage-hidden') && !message.hidden;
            if (!visible || (message !== activeQuestionMessage && message !== activeModuleMessage)) return;
            const header = message.querySelector('.interview-message-header');
            if (!header) return;
            const resolvedTarget = stableTargetForMessage(message);
            const owner = message.closest('.patient-interview-question');
            const scopedVoiceTarget = m03VoiceTargetForCard(owner);
            const voiceTarget = scopedVoiceTarget || resolvedTarget;
            if (!voiceTarget || (!scopedVoiceTarget && voiceTarget !== conversationCoordinator.snapshot().currentTarget)) return;
            header.querySelectorAll('.chat-voice-button, .chat-help-button').forEach(btn => btn.remove());
            message.dataset.voiceBound = '1';
        });
        document.querySelectorAll('[data-module="M02"] .patient-interview-answer input[type="text"]').forEach(input => {
            const answer = input.closest('.patient-interview-answer');
            const visible = !input.closest('[hidden], .chat-stage-hidden') && !input.hidden && !answer?.hidden;
            if (!answer || !visible || input.disabled) return;
            let host = answer.querySelector('.interview-composer-input') || answer.querySelector('.m03-input-wrap');
            if (!host && answer.classList.contains('interview-composer')) {
                host = document.createElement('div');
                host.className = 'interview-composer-input';
                const label = input.previousElementSibling?.tagName === 'LABEL' ? input.previousElementSibling : null;
                if (label) host.appendChild(label);
                answer.insertBefore(host, answer.firstChild);
                host.appendChild(input);
            }
            if (!host && answer.classList.contains('m03-composer')) host = answer.querySelector('.m03-input-wrap');
            if (!host) return;
            const existingButton = host.querySelector('.chat-mic-button');
            const existingIndicator = host.querySelector('.chat-mic-unavailable');
            if (localDictationEnabled && localDictation?.supported?.() === true) {
                existingIndicator?.remove();
                if (!existingButton) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-outline-secondary chat-mic-button';
                    button.textContent = '🎙';
                    button.title = 'Dictado local en español';
                    button.setAttribute('aria-label', 'Iniciar dictado');
                    button.addEventListener('click', () => startRecognition(button, input));
                    host.appendChild(button);
                }
            } else {
                existingButton?.remove();
                if (!existingIndicator) {
                    const indicator = document.createElement('button');
                    indicator.type = 'button';
                    indicator.className = 'chat-mic-unavailable';
                    indicator.textContent = '🎙';
                    indicator.title = localDictationEnabled ? 'El navegador o el servicio local no admite dictado seguro; usa el teclado.' : 'El dictado local no está habilitado en este entorno; usa el teclado.';
                    indicator.setAttribute('aria-disabled', 'true');
                    indicator.setAttribute('aria-label', localDictationEnabled ? '🎙 Dictado local no disponible; usa la captura escrita' : '🎙 Dictado local no habilitado; usa la captura escrita');
                    indicator.addEventListener('click', () => setStatus(localDictationEnabled ? 'El navegador o el servicio local no admite dictado seguro. Responde por escrito.' : 'El dictado local no está habilitado en este entorno.'));
                    host.appendChild(indicator);
                }
            }
            input.dataset.micBound = '1';
        });
        syncM03DictationPermission();
        if (typeof syncAvatarRepeatControls === 'function') syncAvatarRepeatControls();
        if (typeof syncAvatarStopControls === 'function') syncAvatarStopControls();
        if (typeof syncAvatarHelpControls === 'function') syncAvatarHelpControls();
    }
    document.addEventListener('visibilitychange', () => { if (document.hidden) { stopAssistantVoice(); stopRecognition(); } });
    window.addEventListener('pagehide', () => { conversationCoordinator.invalidate('page_hidden'); stopAssistantVoice(); stopRecognition(); });
    const mapCard = document.querySelector('[data-module="M09-MAP"]');
    if (mapCard) {
        const mapState = {};
        const mapVersion = () => mapCard.dataset.m09MapVersion || '';
        const imageUrl = orientation => '<?=SERVIDOR;?>historia-clinica/paciente/imagen-evaluacion-dolor/' + orientation + '/' + Number(main.dataset.patient) + '?chat_map=' + Date.now();
        const loadMap = orientation => new Promise(resolve => { const canvas=mapCard.querySelector('[data-map-canvas="'+orientation+'"]'), image=new Image(); if(!canvas){resolve();return;} image.onload=()=>{const ctx=canvas.getContext('2d');ctx.clearRect(0,0,canvas.width,canvas.height);ctx.drawImage(image,0,0,canvas.width,canvas.height);mapState[orientation]={canvas,ctx,image,loaded:true,color:'#ff0000',dirty:false};resolve();}; image.onerror=()=>{setStatus('No se pudo cargar el mapa '+orientation+'.');resolve();}; image.src=imageUrl(orientation); });
        const point = (canvas,event) => { const rect=canvas.getBoundingClientRect(); return {x:Math.max(0,Math.min(canvas.width,(event.clientX-rect.left)*canvas.width/rect.width)),y:Math.max(0,Math.min(canvas.height,(event.clientY-rect.top)*canvas.height/rect.height))}; };
        const bindMap = orientation => { const canvas=mapState[orientation]?.canvas; if(!canvas)return; let drawing=false,last=null; canvas.addEventListener('pointerdown',event=>{const state=mapState[orientation];event.preventDefault();drawing=true;last=point(canvas,event);canvas.setPointerCapture?.(event.pointerId);state.ctx.strokeStyle=state.color;state.ctx.lineWidth=Math.max(18,canvas.width/48);state.ctx.lineCap='round';state.ctx.lineJoin='round';state.ctx.beginPath();state.ctx.moveTo(last.x,last.y);state.ctx.lineTo(last.x+.1,last.y+.1);state.ctx.stroke();state.dirty=true;}); canvas.addEventListener('pointermove',event=>{if(!drawing)return;const state=mapState[orientation];event.preventDefault();const next=point(canvas,event);state.ctx.beginPath();state.ctx.moveTo(last.x,last.y);state.ctx.lineTo(next.x,next.y);state.ctx.stroke();last=next;}); const finish=event=>{if(!drawing)return;event.preventDefault();drawing=false;last=null;}; canvas.addEventListener('pointerup',finish);canvas.addEventListener('pointercancel',finish);canvas.addEventListener('pointerleave',event=>{if(drawing&&event.buttons===0)finish(event);}); };
        ['frente','espalda'].forEach(orientation=>{ loadMap(orientation).then(()=>bindMap(orientation)); mapCard.querySelectorAll('[data-map-panel="'+orientation+'"] [data-map-color]').forEach(button=>button.addEventListener('click',()=>{mapState[orientation].color=button.dataset.mapColor||'#ff0000';})); mapCard.querySelector('[data-map-clear="'+orientation+'"]').addEventListener('click',()=>{loadMap(orientation);mapCard.querySelector('[data-map-preview-box="'+orientation+'"]').hidden=true;setStatus('Edición local del mapa '+orientation+' limpiada. No se guardó ningún dato.');}); mapCard.querySelector('[data-map-preview="'+orientation+'"]').addEventListener('click',()=>{const state=mapState[orientation],box=mapCard.querySelector('[data-map-preview-box="'+orientation+'"][data-map-preview-box]'),preview=mapCard.querySelector('[data-map-preview-image="'+orientation+'"]');if(!state?.canvas)return;mapState[orientation].preview=state.canvas.toDataURL('image/png');preview.src=mapState[orientation].preview;box.hidden=false;setStatus('Previsualización lista. No se ha guardado el mapa '+orientation+'.');}); mapCard.querySelector('[data-map-edit="'+orientation+'"]').addEventListener('click',()=>{mapCard.querySelector('[data-map-preview-box="'+orientation+'"]').hidden=true;setStatus('Puedes corregir el mapa '+orientation+' antes de confirmar.');}); mapCard.querySelector('[data-map-cancel="'+orientation+'"]').addEventListener('click',()=>{mapState[orientation].preview=null;mapCard.querySelector('[data-map-preview-box="'+orientation+'"]').hidden=true;setStatus('Previsualización cancelada. No se guardó ningún mapa.');}); mapCard.querySelector('[data-map-confirm="'+orientation+'"]').addEventListener('click',async button=>{const preview=mapState[orientation]?.preview;if(!preview){setStatus('Genera una previsualización antes de confirmar.');return;}button.currentTarget.disabled=true;try{const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m09-mapa',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),orientation,image:preview,expectedVersion:mapVersion()})});const data=await response.json();if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible guardar el mapa.');mapCard.dataset.m09MapVersion=data.version||mapVersion();mapState[orientation].preview=null;mapCard.querySelector('[data-map-preview-box="'+orientation+'"]').hidden=true;await loadMap(orientation);setStatus('Mapa '+orientation+' confirmado y recuperado desde el almacenamiento.');}catch(error){setStatus(error.message||'No fue posible guardar el mapa.');}finally{button.currentTarget.disabled=false;}}); });
    }
    // setM04Stage() synchronously changes the coordinator target and invokes
    // its state/voice callbacks, so initialize M04 only after those handlers.
    document.querySelectorAll('[data-module="M04"]').forEach(card => {
        setM04Stage(card, card.dataset.m04StableId === M04_STAGES.addAnother ? 'addAnother' : 'initial', {speak: false});
    });
    showConversationStage(conversationStage);
    bindVoiceControls();
    document.querySelectorAll('[data-module="M02"]').forEach(syncM02DictationPermission);
    window.setTimeout(autoStartVoice, 0);
})();
</script>
<script src="<?=RUTA_JS;?>main.js"></script>
<script>
(() => {
    document.addEventListener('keydown', event => {
        const sidebar = document.getElementById('sidebar');
        if (event.key === 'Escape') {
            if (!sidebar || window.innerWidth >= 768 || !sidebar.classList.contains('active')) return;
            event.preventDefault();
            sidebar.classList.remove('active');
            document.querySelectorAll('.sidebar-toggler').forEach(control => {
                control.setAttribute('aria-expanded', 'false');
                control.setAttribute('aria-label', 'Abrir menú principal');
                control.setAttribute('title', 'Abrir menú principal');
            });
        }
    });
})();
</script>
</body>
</html>
