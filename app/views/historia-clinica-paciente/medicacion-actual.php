<?php
$escape = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
$pageTitle = $data['title'] ?? 'Medicación Actual';
$patientId = (int)($data['idPaciente'] ?? 0);
$patientName = $data['nombre'] ?? 'Paciente';
$versionedAsset = static function (string $baseUrl, string $assetPath, string $publicPath): string {
    $filesystemPath = realpath(__DIR__ . '/../../public/' . ltrim($publicPath, '/'));
    $version = $filesystemPath !== false && is_file($filesystemPath) ? filemtime($filesystemPath) : false;
    return $baseUrl . $assetPath . ($version === false ? '' : '?v=' . (int)$version);
};
$appCssUrl = $versionedAsset(RUTA_CSS, 'app.css', 'assets/css/app.css');
$loaderJsUrl = $versionedAsset(RUTA_JS, 'loader.js', 'assets/js/loader.js');
$voiceUtilitiesJsUrl = $versionedAsset(RUTA_JS, 'voice-utilities.js', 'assets/js/voice-utilities.js');
$audioStatusJsUrl = $versionedAsset(RUTA_JS, 'patient-audio-status.js', 'assets/js/patient-audio-status.js');
$featherJsUrl = $versionedAsset(RUTA_JS, 'feather-icons/feather.min.js', 'assets/js/feather-icons/feather.min.js');
$appJsUrl = $versionedAsset(RUTA_JS, 'app.js', 'assets/js/app.js');
$mainJsUrl = $versionedAsset(RUTA_JS, 'main.js', 'assets/js/main.js');
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escape($pageTitle); ?></title>
    <link rel="shortcut icon" href="<?= RUTA_IMAGES; ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?= RUTA_IMAGES; ?>/logo-clinica.png">
    <link rel="stylesheet" href="<?= RUTA_CSS; ?>bootstrap.css">
    <link rel="stylesheet" href="<?= RUTA_PUBLIC; ?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?= $appCssUrl; ?>">
    <script src="<?= $loaderJsUrl; ?>"></script>
    <script>
    let p08PendingRequest = false;
    let p08ContentLoading = false;
    let p08FinalizationInFlight = false;
    let p08LastFocusedElement = null;
    let p08SpeechRequestId = 0;
    let p08SpeechStarted = false;
    let p08SpeechMode = null;
    let p08InitialLoad = true;

    function p08PatientId() {
        const main = document.getElementById('main');
        return main ? main.getAttribute('data-paciente') : '';
    }
    function p08StorageKey() { return 'p08:' + String(p08PatientId() || 'unknown') + ':recordIndex'; }
    function p08SetMessage(message, success = false) {
        const element = document.getElementById('mensaje');
        if (!element) return;
        element.textContent = message || '';
        element.classList.toggle('is-success', Boolean(success));
        element.classList.toggle('is-error', !success && Boolean(message));
    }
    function p08ErrorMessage(error) {
        const status = Number(error && error.status);
        if (status === 403) return 'No tienes autorización para modificar este registro.';
        if (status === 401 || status === 404) return 'La sesión ya no es válida. Vuelve a iniciar sesión.';
        if (status === 422) return 'Revisa la información e inténtalo nuevamente.';
        return 'No se pudo completar la operación. Inténtalo nuevamente.';
    }
    function p08SetBusy(busy) {
        document.querySelectorAll('.p08-record button, .p08-add-button, [data-action="finalize"]').forEach(button => { button.disabled = Boolean(busy); });
        const loader = document.querySelector('.LoaderPage');
        if (loader) loader.style.display = busy ? 'block' : 'none';
    }
    function p08JsonRequest(url, payload) {
        if (p08PendingRequest || p08FinalizationInFlight) {
            p08SetMessage('Espera a que termine la operación actual.');
            return Promise.resolve(false);
        }
        p08PendingRequest = true;
        p08SetBusy(true);
        return fetch(url, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) })
            .then(async response => {
                let data;
                try { data = await response.json(); } catch (error) { const jsonError = new Error('invalid-json'); jsonError.status = response.status; throw jsonError; }
                if (!response.ok || !data || !data.resultado) { const requestError = new Error('request-failed'); requestError.status = response.status; throw requestError; }
                return data;
            })
            .catch(error => { p08SetMessage(p08ErrorMessage(error)); return false; })
            .finally(() => { p08PendingRequest = false; p08SetBusy(false); });
    }
    function p08LoadContent(explicitIndex = null) {
        if (p08ContentLoading) return Promise.resolve(false);
        const idPaciente = p08PatientId();
        if (!idPaciente) { p08SetMessage('No se pudo identificar al paciente.'); return Promise.resolve(false); }
        p08ContentLoading = true;
        return fetch('/buscar/contenido-preguntas-modulo-6/' + encodeURIComponent(idPaciente) + '/Paciente')
            .then(async response => { if (!response.ok) { const requestError = new Error('content-failed'); requestError.status = response.status; throw requestError; } return response.text(); })
            .then(html => {
                const container = document.getElementById('contePreguntas');
                if (!container) return;
                container.innerHTML = html;
                if (window.feather && typeof window.feather.replace === 'function') window.feather.replace();
                p08ApplyRecordIndex(explicitIndex);
                if (p08InitialLoad) { p08InitialLoad = false; p08OpenInstructions(); }
            })
            .catch(error => p08SetMessage(p08ErrorMessage(error)))
            .finally(() => { p08ContentLoading = false; });
    }
    function contenidoPreguntas(index = null) { return p08LoadContent(index); }
    function p08ApplyRecordIndex(explicitIndex = null) {
        const records = Array.from(document.querySelectorAll('#contePreguntas .p08-record'));
        if (!records.length) return;
        const saved = Number.parseInt(localStorage.getItem(p08StorageKey()), 10);
        let index = explicitIndex === null ? (Number.isInteger(saved) ? saved : 0) : Number(explicitIndex);
        index = Math.max(0, Math.min(Number.isFinite(index) ? index : 0, records.length - 1));
        records.forEach((record, recordIndex) => { const active = recordIndex === index; record.classList.toggle('active', active); record.setAttribute('aria-hidden', active ? 'false' : 'true'); });
        localStorage.setItem(p08StorageKey(), String(index));
        p08UpdateNavigation(records, index);
    }
    function p08UpdateNavigation(records = null, index = null) {
        const allRecords = records || Array.from(document.querySelectorAll('#contePreguntas .p08-record'));
        if (!allRecords.length) return;
        const current = index === null ? p08CurrentIndex() : index;
        allRecords.forEach((record, recordIndex) => {
            const previous = record.querySelector('[data-action="previous"]');
            const next = record.querySelector('[data-action="next"]');
            if (previous) previous.disabled = recordIndex === 0;
            if (next) next.disabled = recordIndex === allRecords.length - 1;
        });
        const active = allRecords[current];
        if (active) { const status = active.querySelector('.p08-record-status'); if (status) status.textContent = p08HasChanges(active) ? 'Cambios sin guardar' : 'Guardado'; }
    }
    function p08CurrentIndex() { const active = document.querySelector('#contePreguntas .p08-record.active'); return active ? Number.parseInt(active.getAttribute('data-index'), 10) || 0 : 0; }
    function p08HasChanges(record) { return Boolean(record && Array.from(record.querySelectorAll('[data-p08-field]')).some(input => input.value !== input.defaultValue)); }
    function p08Navigate(delta) {
        if (p08PendingRequest || p08ContentLoading || p08FinalizationInFlight) return;
        const active = document.querySelector('#contePreguntas .p08-record.active');
        if (!active) return;
        if (p08HasChanges(active)) { p08SetMessage('Guarda los cambios antes de continuar.'); return; }
        const records = Array.from(document.querySelectorAll('#contePreguntas .p08-record'));
        const index = Math.max(0, Math.min(p08CurrentIndex() + delta, records.length - 1));
        p08StopVoice(false); p08ApplyRecordIndex(index); p08ReadCurrentQuestion(false);
    }
    function p08RecordFields(record) {
        const fields = {};
        record.querySelectorAll('[data-p08-field]').forEach(input => { fields[input.getAttribute('data-p08-field')] = input.value.trim(); });
        return fields;
    }
    function p08SaveRecord(record) {
        if (!record || p08PendingRequest) return;
        const inputs = Array.from(record.querySelectorAll('[data-p08-field]'));
        if (!inputs.some(input => input.value !== input.defaultValue)) { p08SetMessage('No hay cambios que guardar.', true); return; }
        p08JsonRequest('/historia-clinica/paciente/editar-medicacion-actual', { idPaciente: Number(p08PatientId()), idMedicamento: Number(record.getAttribute('data-id')), fields: p08RecordFields(record) })
            .then(data => { if (!data) return; inputs.forEach(input => { input.defaultValue = input.value; }); p08SetMessage('Información guardada correctamente.', true); p08UpdateNavigation(); });
    }
    function p08AddRecord() {
        const recordCount = document.querySelectorAll('#contePreguntas .p08-record').length;
        p08JsonRequest('/historia-clinica/paciente/agregar-medicacion-actual', {idPaciente: Number(p08PatientId())})
            .then(data => { if (data) return p08LoadContent(recordCount); });
    }
    function p08DeleteRecord(record) {
        if (!record || p08PendingRequest || !window.confirm('¿Deseas eliminar este medicamento?')) return;
        const currentIndex = p08CurrentIndex();
        p08JsonRequest('/historia-clinica/paciente/eliminar-medicacion-actual', { idPaciente: Number(p08PatientId()), idMedicamento: Number(record.getAttribute('data-id')) })
            .then(data => { if (data) return p08LoadContent(Math.max(0, currentIndex - 1)); });
    }
    function p08Finalize() {
        if (p08FinalizationInFlight || p08PendingRequest) return;
        const active = document.querySelector('#contePreguntas .p08-record.active');
        if (active && p08HasChanges(active)) { p08SetMessage('Guarda los cambios antes de finalizar.'); return; }
        p08FinalizationInFlight = true; p08SetBusy(true);
        fetch('/historia-clinica/paciente/finalizar-modulo-paciente', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({idModulo: 6, idPaciente: Number(p08PatientId())}) })
            .then(async response => { const data = await response.json(); if (!response.ok || !data || !data.resultado) { const requestError = new Error('finalize-failed'); requestError.status = response.status; throw requestError; } if (typeof data.next_url !== 'string' || !data.next_url.startsWith('/')) throw new Error('invalid-next-url'); window.location.href = data.next_url; })
            .catch(error => p08SetMessage(p08ErrorMessage(error)))
            .finally(() => { p08FinalizationInFlight = false; p08SetBusy(false); });
    }
    function p08StopVoice(announce = true) {
        const hadSpeech = p08SpeechStarted;
        p08SpeechRequestId += 1;
        p08SpeechStarted = false;
        if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        document.querySelectorAll('#p08InstructionsModal .p08-voice-button[data-action="stop-voice"]').forEach(button => { button.hidden = false; });
        document.querySelectorAll('#p08InstructionsModal .p08-voice-button[data-action="read-question"]').forEach(button => { button.setAttribute('aria-pressed', 'false'); });
        document.querySelectorAll('body > .p08-voice-button[data-action="stop-voice"], .p08-question-tools .p08-voice-button[data-action="stop-voice"]').forEach(button => { button.hidden = true; button.tabIndex = -1; });
        const status = document.getElementById('p08VoiceStatus'); if (announce && hadSpeech && status) PatientAudioStatus.stopped(status);
        if (p08SpeechMode === 'instructions') PatientAudioControls.setState('p08-instructions', announce && hadSpeech ? 'stopped' : 'idle');
        p08SpeechMode = null;
    }
    function p08Speak(text, mode = 'question') {
        if (!('speechSynthesis' in window) || typeof SpeechSynthesisUtterance === 'undefined') { p08SetMessage('La lectura por voz no está disponible. Puedes continuar con las instrucciones escritas.'); if (mode === 'instructions') PatientAudioControls.unavailable('p08-instructions'); return false; }
        const cleanText = String(text || '').replace(/\s+/g, ' ').trim(); if (!cleanText) return false;
        p08StopVoice(false);
        const utterance = new SpeechSynthesisUtterance(cleanText);
        const voices = window.speechSynthesis.getVoices(); const voice = voices.find(item => /^es-MX/i.test(item.lang)) || voices.find(item => /^es(-|_)/i.test(item.lang));
        utterance.lang = voice ? voice.lang : 'es-MX'; if (voice) utterance.voice = voice; utterance.rate = .88;
        const requestId = ++p08SpeechRequestId; p08SpeechMode = mode;
        utterance.onstart = () => { if (requestId !== p08SpeechRequestId) return; p08SpeechStarted = true; document.querySelectorAll('.p08-voice-button[data-action="stop-voice"]:not(#p08StopInstructionsButton)').forEach(button => { button.hidden = false; }); document.querySelectorAll('.p08-voice-button[data-action="read-question"]:not(#p08ReadInstructionsButton)').forEach(button => { button.setAttribute('aria-pressed', 'true'); }); const status = document.getElementById('p08VoiceStatus'); if (status) mode === 'instructions' ? PatientAudioStatus.playing(status) : status.textContent = 'Reproduciendo el registro activo.'; if (mode === 'instructions') PatientAudioControls.playing('p08-instructions'); };
        utterance.onend = () => { if (requestId !== p08SpeechRequestId) return; p08SpeechStarted = false; if (mode === 'instructions') { PatientAudioStatus.idle(document.getElementById('p08VoiceStatus')); PatientAudioControls.ended('p08-instructions'); } p08StopVoice(false); };
        utterance.onerror = () => { if (requestId !== p08SpeechRequestId) return; p08SpeechStarted = false; p08StopVoice(false); if (mode === 'instructions') { PatientAudioStatus.idle(document.getElementById('p08VoiceStatus')); PatientAudioControls.idle('p08-instructions'); p08SetMessage('No se pudo iniciar la lectura. Presiona Escuchar para intentarlo de nuevo.'); } };
        try { window.speechSynthesis.speak(utterance); return true; } catch (error) { return false; }
    }
    function p08ReadCurrentQuestion(fromButton = true) {
        const modal = document.getElementById('p08InstructionsModal');
        if (fromButton && modal && modal.getAttribute('aria-hidden') !== 'true') return p08Speak(document.getElementById('p08InstructionsText')?.textContent, 'instructions');
        const active = document.querySelector('#contePreguntas .p08-record.active'); if (!active) return false;
        const copy = active.cloneNode(true); copy.querySelectorAll('button, input, [aria-live]').forEach(element => element.remove()); return p08Speak(copy.textContent, 'question');
    }
    function p08OpenInstructions() {
        const modal = document.getElementById('p08InstructionsModal'); if (!modal) return;
        p08LastFocusedElement = PatientInstructionsModal.capture(modal, document.activeElement, '.patient-medication-help'); PatientInstructionsModal.open(modal, { opener: p08LastFocusedElement, display: 'flex', focusSelector: '[data-action="close-modal"]' }); document.body.classList.add('modal-open');
        p08Speak(document.getElementById('p08InstructionsText')?.textContent, 'instructions');
    }
    function p08CloseInstructions() {
        const modal = document.getElementById('p08InstructionsModal'); if (!modal) return;
        p08StopVoice(false); PatientInstructionsModal.close(modal, { returnFocus: p08LastFocusedElement, fallbackSelector: '.patient-medication-help' }); document.body.classList.remove('modal-open'); p08LastFocusedElement = null;
    }
    document.addEventListener('click', event => {
        const modal = document.getElementById('p08InstructionsModal');
        if (modal && event.target === modal) return p08CloseInstructions();
        const actionElement = event.target.closest('[data-action]'); if (!actionElement) return;
        const action = actionElement.getAttribute('data-action');
        if (action === 'help') return p08OpenInstructions(); if (action === 'close-modal') return p08CloseInstructions(); if (action === 'read-question') return p08ReadCurrentQuestion(true); if (action === 'stop-voice') return p08StopVoice(true);
        const record = actionElement.closest('.p08-record'); if (action === 'add') return p08AddRecord(); if (action === 'save') return p08SaveRecord(record); if (action === 'delete') return p08DeleteRecord(record); if (action === 'previous') return p08Navigate(-1); if (action === 'next') return p08Navigate(1); if (action === 'finalize') return p08Finalize();
    });
    document.addEventListener('keydown', event => { const modal = document.getElementById('p08InstructionsModal'); PatientInstructionsModal.handleKeydown(event, modal, p08CloseInstructions); });
        document.addEventListener('DOMContentLoaded', () => { PatientAudioStatus.idle(document.getElementById('p08VoiceStatus')); p08LoadContent(); });
    </script>
</head>
<body class="app-shell-body app-shell-patient patient-clinical-form patient-medication-page">
<div class="LoaderPage"></div>
<div id="app">
    <?= $data['sidebar']; ?>
    <div id="main" data-rol="<?= $escape($data['idRol'] ?? 'Paciente'); ?>" data-paciente="<?= $patientId; ?>">
        <nav class="navbar navbar-header navbar-expand navbar-light">
            <button type="button" class="sidebar-toggler border-0 bg-transparent" aria-label="Abrir menú principal" aria-controls="sidebar" aria-expanded="true"><span class="navbar-toggler-icon"></span></button>
            <button class="btn navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Mostrar navegación"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent"><ul class="navbar-nav d-flex align-items-center navbar-light ms-auto"></ul></div>
        </nav>
        <main class="patient-medication-content container-fluid">
            <header class="patient-medication-hero" aria-labelledby="p08-title">
                <div class="patient-medication-hero-copy"><p class="dashboard-eyebrow">MI HISTORIA CLÍNICA</p><h1 id="p08-title"><?= $escape($pageTitle); ?></h1><p>Registra los medicamentos que utilizas actualmente y sus datos de tratamiento.</p><p class="patient-medication-context">Paciente: <?= $escape($patientName); ?></p></div>
                <div class="patient-medication-hero-actions"><button type="button" id="p08HelpButton" class="btn patient-medication-help" aria-label="¿Cómo llenar esta sección?" aria-haspopup="dialog" aria-controls="p08InstructionsModal" data-action="help"><i data-feather="help-circle" aria-hidden="true"></i><span>¿Cómo llenar esta sección?</span></button><a class="btn patient-medication-back" href="/historia-clinica"><i data-feather="arrow-left" aria-hidden="true"></i><span>Volver a mi Historia Clínica</span></a></div>
            </header>
            <section class="patient-medication-workspace" aria-labelledby="p08-card-title"><article class="patient-medication-card"><header class="patient-medication-card-header"><div class="patient-medication-card-heading"><p class="dashboard-eyebrow">MEDICACIÓN ACTUAL</p><h2 id="p08-card-title">Registra una medicación a la vez</h2><p>Completa los datos de cada medicamento. Tus cambios se guardan únicamente al pulsar «Guardar cambios».</p></div></header><div class="patient-medication-card-body" id="contePreguntas"></div></article></section>
        </main>
        <?php include_once __DIR__ . '/../components/footer-doctor.php'; ?>
    </div>
</div>
<div id="p08InstructionsModal" class="p08-modal patient-instructions-modal" role="dialog" aria-modal="true" aria-labelledby="p08InstructionsTitle" aria-describedby="p08InstructionsText" aria-hidden="true" style="display:none"><div class="modal-dialog modal-dialog-centered patient-instructions-dialog"><div class="p08-modal-content patient-instructions-modal-content"><div class="p08-modal-header patient-instructions-modal-header"><h2 id="p08InstructionsTitle" class="patient-instructions-modal-title">Antes de comenzar: Medicación actual</h2><?php $modalCloseConfig = ['data' => ['data-action' => 'close-modal']]; include __DIR__ . '/partials/modal-close.php'; unset($modalCloseConfig); ?></div><div class="p08-modal-body patient-instructions-modal-body"><p id="p08InstructionsText" data-narration-instructions>En esta sección registrarás los medicamentos que utilizas actualmente. Lee o escucha las instrucciones, completa los cinco datos de cada registro y guarda los cambios antes de avanzar. Puedes agregar o eliminar medicamentos y finalizar cuando hayas terminado.</p><p class="p08-voice-status patient-instructions-voice-prompt" id="p08VoiceStatus" role="status" aria-live="polite"></p><p class="patient-instructions-modal-note">Tus respuestas se guardan mientras avanzas. Puedes cerrar esta ventana sin perder tu progreso.</p></div><div class="p08-modal-actions patient-instructions-modal-actions"><?php $modalAudioConfig = ['group' => 'p08-instructions', 'listen_id' => 'p08ReadInstructionsButton', 'stop_id' => 'p08StopInstructionsButton', 'listen_class' => 'btn p08-voice-button patient-instructions-secondary', 'stop_class' => 'btn p08-voice-button patient-instructions-secondary', 'listen_attrs' => ['data-action' => 'read-question'], 'stop_attrs' => ['data-action' => 'stop-voice']]; include __DIR__ . '/partials/modal-audio-controls.php'; unset($modalAudioConfig); ?><button type="button" class="btn btn-primary" data-action="close-modal">Entendido, comenzar</button></div></div></div></div>
<script src="<?= $audioStatusJsUrl; ?>"></script><script src="<?= RUTA_JS; ?>patient-instructions-modal.js"></script><script src="<?= RUTA_JS; ?>patient-narration-highlight.js"></script><script src="<?= $voiceUtilitiesJsUrl; ?>"></script><script src="<?= $featherJsUrl; ?>"></script><script src="<?= RUTA_PUBLIC; ?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script><script src="<?= $appJsUrl; ?>"></script><script src="<?= $mainJsUrl; ?>"></script>
</body>
</html>
