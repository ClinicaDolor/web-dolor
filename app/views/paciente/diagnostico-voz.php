<?php
declare(strict_types=1);
$safeCsrf = htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $safeCsrf ?>">
    <title>Diagnóstico local de dictado por voz</title>
    <link rel="stylesheet" href="/assets/css/local-voice-diagnostic.css">
    <script defer src="/assets/js/patient-interview-audio-worklet.js"></script>
    <script defer src="/assets/js/patient-interview-local-dictation.js"></script>
    <script defer src="/assets/js/local-voice-diagnostic.js"></script>
</head>
<body>
<main class="voice-diagnostic" aria-labelledby="page-title">
    <h1 id="page-title">Diagnóstico local de dictado por voz</h1>
    <p>Prueba temporal del micrófono y reconocimiento local. El texto permanece solo en esta pantalla; no se guarda en la historia clínica.</p>
    <dl class="service-state">
        <div><dt>Servicio Vosk</dt><dd id="service-state">Habilitado para runtime local; se confirma al conectar</dd></div>
        <div><dt>Conexión segura</dt><dd id="connection-state" role="status" aria-live="polite">Aún no conectada</dd></div>
        <div><dt>Micrófono</dt><dd id="microphone-state" role="status" aria-live="polite">Sin iniciar</dd></div>
    </dl>
    <div class="voice-controls">
        <button id="start-dictation" type="button">Iniciar dictado</button>
        <button id="stop-dictation" type="button" disabled>Detener dictado</button>
        <button id="clear-transcript" type="button">Limpiar texto</button>
    </div>
    <label for="transcript">Transcripción temporal</label>
    <textarea id="transcript" rows="8" maxlength="12000" autocomplete="off" spellcheck="true" placeholder="Las palabras reconocidas aparecerán aquí mientras hablas."></textarea>
    <p id="diagnostic-message" role="status" aria-live="polite">El micrófono solo se solicita después de autorizar la conexión local.</p>
</main>
</body>
</html>
