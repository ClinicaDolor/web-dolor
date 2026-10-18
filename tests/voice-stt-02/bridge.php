<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '0');

$token = getenv('VOICE_STT_PROTO_TOKEN') ?: '';
$pythonPort = (int)(getenv('VOICE_STT_PYTHON_PORT') ?: 8766);
$pythonBase = 'http://127.0.0.1:' . $pythonPort;
$path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; connect-src 'self'; media-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-ancestors 'none'");

$json = static function (int $status, array $payload): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

if ($token === '') {
    $json(503, ['resultado' => false, 'mensaje' => 'Prototipo no configurado.']);
}

if ($path === '/health') {
    $curl = curl_init($pythonBase . '/health');
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 3, CURLOPT_HTTPHEADER => ['X-Voice-Prototype-Token: ' . $token]]);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    $json($status === 200 ? 200 : 503, $status === 200 ? (json_decode((string)$body, true) ?: ['status' => 'ok']) : ['resultado' => false, 'mensaje' => 'Servicio local no disponible.']);
}

if ($path === '/transcribe') {
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength <= 0 || $contentLength > 8 * 1024 * 1024) {
        $json(413, ['resultado' => false, 'mensaje' => 'El audio supera el límite permitido.']);
    }
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if (!in_array($contentType, ['audio/webm', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/mpeg', 'audio/mp4'], true)) {
        $json(415, ['resultado' => false, 'mensaje' => 'Formato de audio no permitido.']);
    }
    $body = file_get_contents('php://input');
    if (!is_string($body) || strlen($body) !== $contentLength) {
        $json(400, ['resultado' => false, 'mensaje' => 'Audio incompleto.']);
    }
    $curl = curl_init($pythonBase . '/transcribe');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [
            'Content-Type: ' . $contentType,
            'Content-Length: ' . strlen($body),
            'X-Voice-Prototype-Token: ' . $token,
        ],
    ]);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if (!is_string($response) || $status < 200 || $status >= 600) {
        $json(503, ['resultado' => false, 'mensaje' => 'No fue posible comunicarse con el servicio local.']);
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo $response;
    exit;
}

$pageToken = json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VOICE-STT-02 · Prototipo local</title>
    <style>
        :root { font-family: system-ui, sans-serif; color: #20304d; background: #f4f1ff; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; }
        main { width: min(38rem, calc(100% - 2rem)); background: #fff; padding: 2rem; border-radius: 1rem; box-shadow: 0 .5rem 2rem #38209020; }
        h1 { margin-top: 0; font-size: 1.45rem; }
        .notice { color: #6b557d; font-size: .95rem; }
        label { display: block; margin-top: 1.5rem; font-weight: 700; }
        input, textarea { box-sizing: border-box; width: 100%; margin-top: .5rem; padding: .85rem; border: 1px solid #b9c4d8; border-radius: .7rem; font-size: 1rem; font-family: inherit; }
        textarea { min-height: 6rem; resize: vertical; }
        .actions { display: flex; gap: .75rem; align-items: center; margin-top: .8rem; }
        button { border: 0; border-radius: .7rem; padding: .8rem 1rem; font-weight: 700; cursor: pointer; }
        #mic { color: #fff; background: #5b16c8; }
        #mic[data-recording="1"] { background: #b42318; }
        #send { color: #fff; background: #237a72; }
        button:disabled { opacity: .55; cursor: not-allowed; }
        #status { min-height: 1.5rem; margin-top: 1rem; }
        .state { font-weight: 700; }
        .diagnostics { margin-top: 1.5rem; padding: 1rem; border: 1px solid #c5b9ea; border-radius: .7rem; background: #faf9ff; }
        .diagnostics header { display: flex; justify-content: space-between; gap: .75rem; align-items: baseline; flex-wrap: wrap; }
        .diagnostics h2 { margin: 0; font-size: 1rem; }
        .diagnostics code { font-size: .75rem; color: #5b16c8; }
        #diagnostic-last { margin: .7rem 0; font-weight: 700; }
        #diagnostic-log { margin: 0; padding-left: 1.2rem; max-height: 13rem; overflow: auto; font: .75rem/1.45 ui-monospace, SFMono-Regular, Consolas, monospace; }
        #diagnostic-log li { margin: .15rem 0; }
    </style>
</head>
<body>
<main>
    <h1>Dictado local · prototipo temporal</h1>
    <p class="notice">Audio ficticio o micrófono local. La transcripción es editable. Este prototipo no usa expedientes, no guarda audio y no escribe la Historia Clínica.</p>
    <section class="diagnostics" aria-labelledby="diagnostics-title">
        <header>
            <h2 id="diagnostics-title">Diagnóstico local de ejecución</h2>
            <code id="prototype-version">VOICE-STT-03-2026-10-13</code>
        </header>
        <p id="diagnostic-last" role="status" aria-live="polite">Inicializando…</p>
        <ol id="diagnostic-log" aria-label="Últimas transiciones técnicas"></ol>
    </section>
    <label for="answer">Respuesta editable</label>
    <textarea id="answer" rows="3" autocomplete="off" placeholder="Pulsa el micrófono o escribe una respuesta ficticia"></textarea>
    <div class="actions">
        <button id="mic" type="button" aria-label="Iniciar dictado local">🎙 Dictar</button>
        <button id="send" type="button" disabled>Enviar prueba</button>
    </div>
    <p id="status" class="state" role="status" aria-live="polite">Disponible. No se ha iniciado el micrófono.</p>
</main>
<script>
(() => {
    const prototypeVersion = 'VOICE-STT-03-2026-10-13';
    const token = <?= $pageToken ?>;
    const input = document.getElementById('answer');
    const mic = document.getElementById('mic');
    const send = document.getElementById('send');
    const status = document.getElementById('status');
    const version = document.getElementById('prototype-version');
    const diagnosticLast = document.getElementById('diagnostic-last');
    const diagnosticLog = document.getElementById('diagnostic-log');
    const runId = (window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`).slice(-12);
    const startedAt = performance.now();
    const allowedDiagnosticFields = new Set(['mimeType', 'blobBytes', 'chunks', 'httpStatus', 'textLength', 'errorCode', 'audioTracks', 'segment', 'segmentLatencyMs', 'pendingSegments', 'firstTextMs']);
    const segmentDurationMs = 4000;
    const maxPendingSegments = 2;
    let recorder = null;
    let stream = null;
    let chunks = [];
    let chunkCount = 0;
    let recordingTimer = null;
    let stopTimer = null;
    let segmentTimer = null;
    let segmentIndex = 0;
    let pendingSegments = [];
    let processingSegment = false;
    let finalizing = false;
    let captureActive = false;
    let manualEditDetected = false;
    let applyingTranscript = false;
    let accumulatedTranscript = '';
    let firstTextAt = null;
    let recordingStartedAt = null;
    const setStatus = value => { status.textContent = value; };
    if (version) version.textContent = prototypeVersion;
    const diagnosticDetails = details => Object.entries(details)
        .filter(([key, value]) => allowedDiagnosticFields.has(key) && value !== undefined && value !== null && value !== '')
        .map(([key, value]) => `${key}=${String(value)}`)
        .join(' ');
    const recordDiagnostic = (state, details = {}) => {
        const relativeMs = Math.round(performance.now() - startedAt);
        const suffix = diagnosticDetails(details);
        const line = `${runId} · +${relativeMs}ms · ${state}${suffix ? ` · ${suffix}` : ''}`;
        if (diagnosticLast) diagnosticLast.textContent = line;
        if (diagnosticLog) {
            const item = document.createElement('li');
            item.textContent = line;
            diagnosticLog.appendChild(item);
            while (diagnosticLog.children.length > 60) diagnosticLog.removeChild(diagnosticLog.firstChild);
        }
    };
    const preferredType = () => ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'].find(type => window.MediaRecorder?.isTypeSupported?.(type)) || '';
    let recordingError = false;
    const clearRecordingTimers = () => {
        if (recordingTimer) window.clearTimeout(recordingTimer);
        if (stopTimer) window.clearTimeout(stopTimer);
        if (segmentTimer) window.clearTimeout(segmentTimer);
        recordingTimer = null;
        stopTimer = null;
        segmentTimer = null;
    };
    const releaseCapture = () => { clearRecordingTimers(); stream?.getTracks().forEach(track => track.stop()); stream = null; recorder = null; mic.dataset.recording = '0'; mic.textContent = '🎙 Dictar'; mic.setAttribute('aria-label', 'Iniciar dictado local'); };
    const resetRecording = () => { captureActive = false; finalizing = false; pendingSegments = []; processingSegment = false; releaseCapture(); };
    const requestError = (code, message) => Object.assign(new Error(message), {prototypeCode: code});
    const responseError = (response, data) => {
        const backendCode = typeof data?.codigo === 'string' && /^[A-Z0-9_]+$/.test(data.codigo) ? data.codigo : '';
        const backendMessages = {
            CONTAINER_INVALID: 'No pudimos leer el contenedor de audio.',
            CODEC_UNSUPPORTED: 'El codec de audio no está permitido.',
            AUDIO_DECODE_FAILED: 'No pudimos decodificar la grabación.',
            AUDIO_DURATION_INVALID: 'La duración de la grabación no es válida.',
            AUDIO_STREAM_MISSING: 'La grabación no contiene una pista de audio.'
        };
        if (backendCode) return {code: backendCode, message: backendMessages[backendCode] || 'No pudimos validar la grabación.'};
        if (response.status === 413) return {code: 'AUDIO_TOO_LARGE', message: 'La grabación supera el límite permitido.'};
        if (response.status === 415 || response.status === 422) return {code: 'AUDIO_REJECTED', message: 'No pudimos procesar el formato de la grabación.'};
        if (response.status === 503) return {code: 'STT_UNAVAILABLE', message: 'El servicio de transcripción no está disponible.'};
        if (response.status === 504) return {code: 'STT_TIMEOUT', message: 'La transcripción tardó demasiado. Intenta nuevamente.'};
        return {code: `HTTP_${response.status}`, message: data?.mensaje || 'No pudimos procesar la grabación.'};
    };
    const updateSendState = () => { send.disabled = captureActive || finalizing || processingSegment || !input.value.trim(); };
    const normalizedWords = value => value.toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').split(/\s+/).filter(Boolean);
    const appendWithoutDuplicate = (base, addition) => {
        const left = base.trim();
        const right = addition.trim();
        if (!right) return left;
        if (!left) return right;
        const leftWords = left.split(/\s+/);
        const rightWords = right.split(/\s+/);
        const leftNormalized = normalizedWords(left).join(' ');
        const rightNormalized = normalizedWords(right).join(' ');
        if (leftNormalized.includes(rightNormalized)) return left;
        let overlap = 0;
        const maximum = Math.min(12, leftWords.length, rightWords.length);
        for (let size = maximum; size > 0; size -= 1) {
            const tail = normalizedWords(leftWords.slice(-size).join(' ')).join(' ');
            const head = normalizedWords(rightWords.slice(0, size).join(' ')).join(' ');
            if (tail && tail === head) { overlap = size; break; }
        }
        return `${left} ${rightWords.slice(overlap).join(' ')}`.trim();
    };
    const applyTranscript = responseText => {
        const previousTranscript = accumulatedTranscript;
        accumulatedTranscript = appendWithoutDuplicate(accumulatedTranscript, responseText);
        const newText = accumulatedTranscript.slice(previousTranscript.length).trim();
        if (!newText) return;
        applyingTranscript = true;
        input.value = manualEditDetected ? appendWithoutDuplicate(input.value, newText) : accumulatedTranscript;
        applyingTranscript = false;
        updateSendState();
        if (firstTextAt === null) {
            firstTextAt = performance.now();
            recordDiagnostic('FIRST_TEXT_VISIBLE', {segment: segmentIndex, firstTextMs: Math.round(firstTextAt - recordingStartedAt)});
        }
        recordDiagnostic('COMPOSER_UPDATED', {segment: segmentIndex, textLength: input.value.trim().length});
    };
    const finishFinalization = () => {
        if (!finalizing || processingSegment || pendingSegments.length) return;
        releaseCapture();
        finalizing = false;
        mic.disabled = false;
        updateSendState();
        if (input.value.trim()) {
            recordDiagnostic('FINAL_TEXT_READY', {textLength: input.value.trim().length});
            setStatus('Texto listo. Puedes corregirlo antes del envío explícito.');
        } else {
            recordDiagnostic('ERROR', {errorCode: 'EMPTY_TRANSCRIPT'});
            setStatus('No se reconocieron palabras. Puedes escribir la respuesta.');
        }
    };
    const transcribeSegment = async segment => {
        const startedAt = performance.now();
        setStatus(finalizing ? 'Finalizando transcripción…' : 'Transcribiendo segmento…');
        recordDiagnostic('HTTP_REQUEST_STARTED', {segment: segment.index, mimeType: segment.mimeType, blobBytes: segment.blob.size, chunks: segment.chunks});
        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        const timeout = window.setTimeout(() => controller?.abort(), 30000);
        try {
            const response = await fetch('/transcribe', {method: 'POST', cache: 'no-store', ...(controller ? {signal: controller.signal} : {}), headers: {'Content-Type': segment.mimeType, 'X-Voice-Prototype-Token': token}, body: segment.blob});
            recordDiagnostic('HTTP_RESPONSE_RECEIVED', {segment: segment.index, httpStatus: response.status});
            let data;
            try { data = await response.json(); } catch (_error) { throw requestError('JSON_INVALID', 'El servicio devolvió una respuesta inválida.'); }
            const responseText = typeof data?.texto === 'string' ? data.texto.trim() : '';
            recordDiagnostic('JSON_PARSED', {segment: segment.index, httpStatus: response.status, textLength: responseText.length});
            if (!response.ok || data?.resultado !== true) {
                const failure = responseError(response, data);
                throw requestError(failure.code, failure.message);
            }
            if (responseText) {
                recordDiagnostic('WHISPER_TEXT_RECEIVED', {segment: segment.index, textLength: responseText.length, segmentLatencyMs: Math.round(performance.now() - startedAt)});
                applyTranscript(responseText);
                setStatus(finalizing ? 'Texto parcial disponible. Finalizando…' : 'Texto parcial disponible. Puedes seguir hablando.');
            } else {
                recordDiagnostic('ERROR', {segment: segment.index, errorCode: 'EMPTY_TRANSCRIPT'});
            }
        } catch (error) {
            const code = error?.name === 'AbortError' ? 'HTTP_TIMEOUT' : (error?.prototypeCode || 'HTTP_REQUEST_FAILED');
            recordDiagnostic('ERROR', {segment: segment.index, errorCode: code});
            captureActive = false;
            finalizing = false;
            pendingSegments = [];
            releaseCapture();
            mic.disabled = false;
            updateSendState();
            setStatus(error?.message || 'No pudimos procesar el segmento. Puedes revisar el texto disponible.');
        } finally {
            window.clearTimeout(timeout);
        }
    };
    const processNextSegment = async () => {
        if (processingSegment || !pendingSegments.length) { finishFinalization(); return; }
        processingSegment = true;
        const segment = pendingSegments.shift();
        await transcribeSegment(segment);
        processingSegment = false;
        if (pendingSegments.length) void processNextSegment();
        else finishFinalization();
    };
    const enqueueSegment = (blob, mimeType, recordedChunks) => {
        const segment = {blob, mimeType, chunks: recordedChunks, index: segmentIndex};
        pendingSegments.push(segment);
        recordDiagnostic('SEGMENT_READY', {segment: segment.index, mimeType, blobBytes: blob.size, chunks: recordedChunks});
        if (captureActive && pendingSegments.length >= maxPendingSegments) {
            captureActive = false;
            finalizing = true;
            recordDiagnostic('ERROR', {segment: segment.index, pendingSegments: pendingSegments.length, errorCode: 'SEGMENT_BACKLOG'});
            setStatus('La transcripción va retrasada; finalizando los segmentos pendientes…');
        }
        void processNextSegment();
    };
    const startRecorderSegment = type => {
        if (!captureActive || !stream) return;
        const activeRecorder = new MediaRecorder(stream, {mimeType: type});
        recorder = activeRecorder;
        chunks = [];
        chunkCount = 0;
        const segmentNumber = ++segmentIndex;
        activeRecorder.ondataavailable = event => {
            chunkCount += 1;
            recordDiagnostic('AUDIO_CHUNK_RECEIVED', {segment: segmentNumber, mimeType: event.data?.type || activeRecorder.mimeType || type, blobBytes: event.data?.size || 0, chunks: chunkCount});
            if (event.data?.size > 0) chunks.push(event.data);
        };
        activeRecorder.onerror = () => { recordingError = true; recordDiagnostic('ERROR', {segment: segmentNumber, errorCode: 'MEDIARECORDER_ERROR'}); captureActive = false; finalizing = false; resetRecording(); setStatus('Error recuperable de grabación. Puedes escribir la respuesta.'); };
        activeRecorder.onstop = () => {
            if (segmentTimer) window.clearTimeout(segmentTimer);
            segmentTimer = null;
            const recordedChunks = chunks;
            const mimeType = activeRecorder.mimeType || type;
            chunks = [];
            recordDiagnostic('RECORDER_STOPPED', {segment: segmentNumber, mimeType, chunks: recordedChunks.length});
            if (recordingError) { recordingError = false; return; }
            const blob = new Blob(recordedChunks, {type: mimeType});
            recordDiagnostic('BLOB_READY', {segment: segmentNumber, mimeType: blob.type || mimeType, blobBytes: blob.size, chunks: recordedChunks.length});
            if (!blob.size) {
                recordDiagnostic('ERROR', {segment: segmentNumber, errorCode: 'AUDIO_EMPTY'});
                captureActive = false;
                finalizing = false;
                releaseCapture();
                setStatus('No se detectó audio. Puedes escribir la respuesta.');
                return;
            }
            enqueueSegment(blob, mimeType, recordedChunks.length);
            if (captureActive) startRecorderSegment(type);
            else if (!processingSegment && !pendingSegments.length) finishFinalization();
        };
        activeRecorder.start(250);
        recordDiagnostic('RECORDER_STARTED', {segment: segmentNumber, mimeType: activeRecorder.mimeType || type});
        segmentTimer = window.setTimeout(() => {
            if (recorder === activeRecorder && activeRecorder.state === 'recording') activeRecorder.stop();
        }, segmentDurationMs);
    };
    const stopRecording = reason => {
        if (!recorder || recorder.state !== 'recording') return;
        captureActive = false;
        finalizing = true;
        mic.disabled = true;
        clearRecordingTimers();
        recordDiagnostic(reason, {segment: segmentIndex, mimeType: recorder.mimeType, chunks: chunkCount});
        setStatus('Finalizando transcripción…');
        try {
            recorder.stop();
            stopTimer = window.setTimeout(() => {
                if (recorder && recorder.state !== 'inactive') {
                    recordDiagnostic('ERROR', {errorCode: 'RECORDER_STOP_TIMEOUT'});
                    finalizing = false;
                    resetRecording();
                    mic.disabled = false;
                    setStatus('La grabación no pudo detenerse. Intenta nuevamente.');
                }
            }, 5000);
        } catch (_error) {
            recordDiagnostic('ERROR', {errorCode: 'RECORDER_STOP_FAILED'});
            finalizing = false;
            resetRecording();
            mic.disabled = false;
            setStatus('La grabación no pudo detenerse. Puedes escribir la respuesta.');
        }
    };
    mic.addEventListener('click', async () => {
        if (recorder && recorder.state === 'recording') { stopRecording('STOP_CLICKED'); return; }
        if (finalizing || processingSegment) return;
        recordDiagnostic('MICROPHONE_CLICKED');
        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) { recordDiagnostic('ERROR', {errorCode: 'MEDIARECORDER_UNAVAILABLE'}); setStatus('Este navegador no permite grabación local. Puedes escribir la respuesta.'); return; }
        const type = preferredType();
        if (!type) { recordDiagnostic('ERROR', {errorCode: 'AUDIO_FORMAT_UNSUPPORTED'}); setStatus('No hay un formato de grabación compatible. Puedes escribir la respuesta.'); return; }
        try {
            setStatus('Solicitando permiso…');
            stream = await navigator.mediaDevices.getUserMedia({audio: true});
            recordDiagnostic('PERMISSION_GRANTED');
            const audioTracks = stream.getAudioTracks ? stream.getAudioTracks() : stream.getTracks().filter(track => track.kind === 'audio');
            if (!audioTracks.length) { recordDiagnostic('ERROR', {audioTracks: 0, errorCode: 'AUDIO_TRACK_MISSING'}); resetRecording(); setStatus('No se detectó una pista de audio. Puedes escribir la respuesta.'); return; }
            recordDiagnostic('AUDIO_TRACK_ACTIVE', {audioTracks: audioTracks.length});
            recordingError = false;
            segmentIndex = 0;
            pendingSegments = [];
            processingSegment = false;
            finalizing = false;
            captureActive = true;
            accumulatedTranscript = '';
            manualEditDetected = Boolean(input.value.trim());
            firstTextAt = null;
            recordingStartedAt = performance.now();
            mic.dataset.recording = '1'; mic.textContent = '⏹ Detener'; mic.setAttribute('aria-label', 'Detener dictado local');
            send.disabled = true;
            setStatus('Escuchando…');
            startRecorderSegment(type);
            recordingTimer = window.setTimeout(() => { if (captureActive) { recordDiagnostic('ERROR', {errorCode: 'RECORDING_TIMEOUT'}); stopRecording('RECORDING_TIMEOUT'); } }, 30000);
        } catch (error) {
            recordDiagnostic('ERROR', {errorCode: error?.name === 'NotAllowedError' ? 'MICROPHONE_PERMISSION_DENIED' : 'MICROPHONE_START_FAILED'});
            resetRecording();
            setStatus(error.name === 'NotAllowedError' ? 'Permiso de micrófono denegado. Puedes escribir la respuesta.' : 'No fue posible iniciar el micrófono. Puedes escribir la respuesta.');
        }
    });
    input.addEventListener('input', () => {
        if (!applyingTranscript && (captureActive || processingSegment || finalizing)) manualEditDetected = true;
        updateSendState();
    });
    send.addEventListener('click', () => {
        if (captureActive || processingSegment || finalizing || !input.value.trim()) return;
        recordDiagnostic('MANUAL_SEND', {textLength: input.value.trim().length});
        setStatus('Envío explícito de prueba realizado; no se guardó ningún dato clínico.');
    });
    window.addEventListener('pagehide', resetRecording);
    window.addEventListener('error', () => { recordDiagnostic('ERROR', {errorCode: 'JS_RUNTIME_ERROR'}); setStatus('Ocurrió un error local. Puedes escribir la respuesta.'); });
    window.addEventListener('unhandledrejection', () => { recordDiagnostic('ERROR', {errorCode: 'UNHANDLED_PROMISE'}); setStatus('Ocurrió un error local. Puedes escribir la respuesta.'); });
    recordDiagnostic('PAGE_READY');
})();
</script>
</body>
</html>
