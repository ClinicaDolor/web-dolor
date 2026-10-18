<?php
use App\Services\ClinicalSummarySecurity;

$escape = static fn($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$pageTitle = $data['title'] ?? 'Evaluación del dolor';
$patientId = (int) ($data['idPaciente'] ?? 0);
$patientName = $data['nombre'] ?? 'Paciente';
$patientSex = $data['sexo'] ?? '';
$p11CsrfToken = ClinicalSummarySecurity::captureCsrfToken($patientId);
$versionedAsset = static function (string $baseUrl, string $assetPath, string $publicPath): string {
    $filesystemPath = realpath(__DIR__ . '/../../public/' . ltrim($publicPath, '/'));
    $version = $filesystemPath !== false && is_file($filesystemPath) ? filemtime($filesystemPath) : false;
    return $baseUrl . $assetPath . ($version === false ? '' : '?v=' . (int) $version);
};
$appCssUrl = $versionedAsset(RUTA_CSS, 'app.css', 'assets/css/app.css');
$loaderJsUrl = $versionedAsset(RUTA_JS, 'loader.js', 'assets/js/loader.js');
$voiceUtilitiesJsUrl = $versionedAsset(RUTA_JS, 'voice-utilities.js', 'assets/js/voice-utilities.js');
$featherJsUrl = $versionedAsset(RUTA_JS, 'feather-icons/feather.min.js', 'assets/js/feather-icons/feather.min.js');
$appJsUrl = $versionedAsset(RUTA_JS, 'app.js', 'assets/js/app.js');
$mainJsUrl = $versionedAsset(RUTA_JS, 'main.js', 'assets/js/main.js');
$audioStatusJsUrl = $versionedAsset(RUTA_JS, 'patient-audio-status.js', 'assets/js/patient-audio-status.js');
$serverUrl = rtrim(SERVIDOR, '/') . '/';
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$escape($pageTitle);?></title>
    <link rel="shortcut icon" href="<?=RUTA_IMAGES;?>logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES;?>logo-clinica.png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=$appCssUrl;?>">
    <script src="<?=$loaderJsUrl;?>"></script>
    <script src="<?=$audioStatusJsUrl;?>"></script>
</head>
<body class="app-shell-body app-shell-patient patient-clinical-form p11-pain-evaluation-page">
<div class="LoaderPage"></div>
<div id="app">
    <?=$data['sidebar'];?>
    <div id="main" data-rol="Paciente" data-paciente="<?=$patientId;?>" data-sexo="<?=$escape($patientSex);?>" data-p11-csrf="<?=$escape($p11CsrfToken);?>">
        <nav class="navbar navbar-header navbar-expand navbar-light portal-topbar" aria-label="Barra de herramientas del portal del paciente">
            <button type="button" class="sidebar-toggler border-0 bg-transparent" aria-label="Abrir menú principal" aria-controls="sidebar" aria-expanded="true"><span class="navbar-toggler-icon"></span></button>
            <button class="btn navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Mostrar navegación"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent"><ul class="navbar-nav d-flex align-items-center navbar-light ms-auto"></ul></div>
        </nav>

        <main class="main-content container-fluid p11-content">
            <header class="page-title portal-page-title p11-hero mb-4">
                <div class="p11-hero-copy">
                    <p class="dashboard-eyebrow">MI HISTORIA CLÍNICA</p>
                    <h1>Evaluación del dolor</h1>
                    <p>Indica las zonas donde sientes dolor o molestias y responde las preguntas de evaluación.</p>
                    <span class="p11-patient-context">Paciente: <?=$escape($patientName);?></span>
                </div>
                <div class="p11-hero-actions">
                    <button type="button" id="p11HelpButton" class="btn p11-help" aria-label="¿Cómo llenar esta sección?" aria-haspopup="dialog" aria-controls="p11InstructionsModal" data-p11-action="open-help"><i data-feather="help-circle" aria-hidden="true"></i><span>¿Cómo llenar esta sección?</span></button>
                    <a class="btn p11-back" href="<?=$serverUrl;?>historia-clinica"><i data-feather="arrow-left" aria-hidden="true"></i><span>Volver a mi Historia Clínica</span></a>
                </div>
            </header>

            <div id="p11-message" class="p11-message" role="status" aria-live="polite"></div>
            <section class="p11-workspace" aria-label="Evaluación del dolor">
                <article id="p11-front-panel" class="card p11-card" hidden><div id="conteFrente"></div></article>
                <article id="p11-back-panel" class="card p11-card" hidden><div id="conteEspalda"></div></article>
                <article id="p11-question-panel" class="card p11-card" hidden><div id="conteCuestionario"></div></article>
            </section>
        </main>

        <div id="p11InstructionsModal" class="modal fade patient-family-history-modal patient-instructions-modal" tabindex="-1" role="dialog" aria-labelledby="p11InstructionsTitle" aria-describedby="p11InstructionsText" aria-modal="true" aria-hidden="true" style="display: none;">
            <div class="modal-dialog modal-dialog-centered patient-instructions-dialog">
                <div class="modal-content patient-family-history-modal-content patient-instructions-modal-content">
                    <div class="modal-header patient-instructions-modal-header">
                        <h2 id="p11InstructionsTitle" class="modal-title patient-instructions-modal-title">Antes de comenzar: Evaluación del dolor</h2>
                        <?php $modalCloseConfig = ['data' => ['data-p11-action' => 'close-help']]; include __DIR__ . '/partials/modal-close.php'; unset($modalCloseConfig); ?>
                    </div>
                    <div class="modal-body patient-instructions-modal-body">
                        <p id="p11InstructionsText" data-narration-instructions>En esta sección vamos a conocer en qué partes de tu cuerpo sientes dolor o alguna otra molestia. Primero verás una imagen del cuerpo de frente y después una imagen de la espalda. Selecciona un color y marca la zona correspondiente. Finalmente responderás algunas preguntas sobre tu dolor. Puedes regresar para revisar o corregir tus respuestas antes de finalizar.</p>
                        <p id="p11VoicePrompt" class="patient-family-history-voice-prompt patient-instructions-voice-prompt" role="status" aria-live="polite">Para escuchar las instrucciones, presiona este botón.</p>
                        <p class="patient-family-history-modal-note patient-instructions-modal-note">Tus respuestas y mapas se guardan mientras avanzas. Puedes cerrar esta ventana sin perder tu progreso.</p>
                    </div>
                    <div class="modal-footer patient-family-history-modal-actions patient-instructions-modal-actions">
                        <?php $modalAudioConfig = ['group' => 'p11-instructions', 'listen_id' => 'p11ReadInstructionsButton', 'stop_id' => 'p11StopInstructionsButton', 'listen_class' => 'btn patient-family-history-secondary patient-instructions-secondary', 'stop_class' => 'btn patient-family-history-secondary patient-instructions-secondary', 'listen_attrs' => ['data-p11-action' => 'read-help'], 'stop_attrs' => ['data-p11-action' => 'stop-voice']]; include __DIR__ . '/partials/modal-audio-controls.php'; unset($modalAudioConfig); ?>
                        <button type="button" class="btn btn-primary" data-p11-action="close-help">Entendido, comenzar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include_once __DIR__ . '/../components/footer-doctor.php';?>
</div>
<script src="<?=RUTA_JS;?>patient-instructions-modal.js"></script>
<script src="<?=RUTA_JS;?>patient-narration-highlight.js"></script>
<script src="<?=$voiceUtilitiesJsUrl;?>"></script>
<script src="<?=$featherJsUrl;?>"></script>
<script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="<?=$appJsUrl;?>"></script>
<script src="<?=$mainJsUrl;?>"></script>
<script>
(function () {
    'use strict';
    const root = document.getElementById('main');
    const patientId = Number(root.dataset.paciente || 0);
    const server = <?=json_encode($serverUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);?>;
    const progressKey = 'p11:' + patientId + ':section';
    const imageBase = server + 'historia-clinica/paciente/imagen-evaluacion-dolor/';
    const sections = { frente: 'p11-front-panel', espalda: 'p11-back-panel', cuestionario: 'p11-question-panel' };
    const state = { active: null, mapSaving: Promise.resolve(), modalOpen: false, lastFocus: null };
    let p11SpeechStarted = false;
    const message = document.getElementById('p11-message');

    function setMessage(text, type) { message.textContent = text || ''; message.className = 'p11-message' + (type ? ' is-' + type : ''); }
    function requestJson(path, payload) {
        return fetch(server + path.replace(/^\//, ''), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-M03-CSRF': root.dataset.p11Csrf || '' }, body: JSON.stringify(payload) })
            .then(async response => { const data = await response.json().catch(() => null); if (!response.ok || !data || !data.resultado) throw new Error((data && data.mensaje) || 'No se pudo completar la operación.'); return data; });
    }
    function refreshIcons() { if (window.feather) window.feather.replace(); }
    function panelFor(section) { return document.getElementById(sections[section]); }
    function loadSection(section) {
        if (!sections[section]) return Promise.resolve();
        state.active = section;
        Object.keys(sections).forEach(key => { const panel = panelFor(key); if (panel) panel.hidden = key !== section; });
        localStorage.setItem(progressKey, section);
        const target = section === 'frente' ? 'conteFrente' : (section === 'espalda' ? 'conteEspalda' : 'conteCuestionario');
        const route = section === 'frente' ? 'buscar/contenido-evaluacion-frente-modulo-9/' : (section === 'espalda' ? 'buscar/contenido-evaluacion-espalda-modulo-9/' : 'buscar/contenido-preguntas-modulo-9/');
        return fetch(server + route + patientId + '/Paciente', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => { if (!response.ok) throw new Error('No se pudo cargar la sección.'); return response.text(); })
            .then(html => { const container = document.getElementById(target); container.innerHTML = html; refreshIcons(); if (section === 'frente' || section === 'espalda') initMap(container.querySelector('canvas')); if (section === 'cuestionario') syncQuestionnaire(); })
            .catch(error => { setMessage(error.message || 'No se pudo cargar la sección. Inténtalo nuevamente.', 'error'); throw error; });
    }
    function initMap(canvas) {
        if (!canvas || canvas.dataset.p11Bound === 'true') return;
        canvas.dataset.p11Bound = 'true';
        const orientation = canvas.dataset.p11Orientation;
        const ctx = canvas.getContext('2d');
        const image = new Image();
        let loaded = false, drawing = false, last = null;
        image.onload = function () { ctx.clearRect(0, 0, canvas.width, canvas.height); ctx.drawImage(image, 0, 0, canvas.width, canvas.height); loaded = true; };
        image.onerror = () => setMessage('No se pudo cargar la imagen base. Inténtalo nuevamente.', 'error');
        image.src = imageBase + orientation + '/' + patientId + '?v=' + Date.now();
        function point(event) { const rect = canvas.getBoundingClientRect(); return { x: Math.max(0, Math.min(canvas.width, (event.clientX - rect.left) * canvas.width / rect.width)), y: Math.max(0, Math.min(canvas.height, (event.clientY - rect.top) * canvas.height / rect.height)) }; }
        function selectedColor() { const selected = document.querySelector('input[name="color' + (orientation === 'frente' ? 'Frente' : 'Espalda') + '"]:checked'); return selected ? selected.value : ''; }
        function draw(pointValue) { const color = selectedColor(); if (!color || !loaded) return; ctx.strokeStyle = color; ctx.lineWidth = Math.max(18, canvas.width / 48); ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.beginPath(); if (last) ctx.moveTo(last.x, last.y); else ctx.moveTo(pointValue.x, pointValue.y); ctx.lineTo(pointValue.x + (last ? 0 : .1), pointValue.y + (last ? 0 : .1)); ctx.stroke(); last = pointValue; }
        canvas.addEventListener('pointerdown', event => { if (!selectedColor() || !loaded) { setMessage('Selecciona un color antes de marcar.', 'error'); return; } event.preventDefault(); drawing = true; last = null; canvas.setPointerCapture?.(event.pointerId); draw(point(event)); });
        canvas.addEventListener('pointermove', event => { if (!drawing) return; event.preventDefault(); draw(point(event)); });
        const finish = event => { if (!drawing) return; event.preventDefault(); drawing = false; last = null; const data = canvas.toDataURL('image/png'); const endpoint = orientation === 'frente' ? 'historia-clinica/paciente/editar-img-frente-modulo9' : 'historia-clinica/paciente/editar-img-espalda-modulo9'; state.mapSaving = state.mapSaving.then(() => requestJson(endpoint, { idPaciente: patientId, [orientation === 'frente' ? 'imgFrente' : 'imgEspalda']: data })).then(() => setMessage('Mapa guardado.', 'success')).catch(() => setMessage('No se pudo guardar el mapa. Tus marcas se conservaron; puedes reintentar.', 'error')); };
        canvas.addEventListener('pointerup', finish); canvas.addEventListener('pointercancel', finish); canvas.addEventListener('pointerleave', event => { if (drawing && event.buttons === 0) finish(event); });
        const container = canvas.closest('.p11-map-content');
        if (!container || container.dataset.p11ActionsBound === 'true') return;
        container.dataset.p11ActionsBound = 'true';
        container.addEventListener('click', event => { const nav = event.target.closest('[data-p11-nav]'); if (nav) { event.preventDefault(); loadSection(nav.dataset.p11Nav); return; } const clear = event.target.closest('[data-p11-action^="clear-"]'); if (!clear) return; const target = clear.dataset.p11Action.replace('clear-', ''); if (!window.confirm('¿Deseas limpiar el mapa de ' + target + '?')) return; requestJson('historia-clinica/paciente/eliminar-img-' + target + '-modulo9', { idPaciente: patientId }).then(() => { setMessage('Mapa limpio.', 'success'); return loadSection(target); }).catch(() => setMessage('No se pudo limpiar el mapa. Inténtalo nuevamente.', 'error')); });
    }
    function syncQuestionnaire() {
        const container = document.getElementById('conteCuestionario');
        if (!container || container.dataset.p11ActionsBound === 'true') return;
        container.dataset.p11ActionsBound = 'true';
        container.addEventListener('change', event => { if (event.target.name === 'p11Dolor') { const dependent = container.querySelector('.p11-dependent-fields'); if (dependent) dependent.hidden = event.target.value !== 'Si'; } });
        container.addEventListener('click', event => { const nav = event.target.closest('[data-p11-nav]'); if (nav) { event.preventDefault(); loadSection(nav.dataset.p11Nav); return; } const action = event.target.closest('[data-p11-action]'); if (!action) return; if (action.dataset.p11Action === 'save-question') saveQuestion(false); if (action.dataset.p11Action === 'finish') saveQuestion(true); });
    }
    function saveQuestion(finishAfter) {
        const container = document.getElementById('conteCuestionario'); const selected = container.querySelector('input[name="p11Dolor"]:checked');
        if (!selected) { setMessage('Selecciona Sí o No para continuar.', 'error'); return; }
        const changes = { 1: selected.value };
        if (selected.value === 'Si') { ['tiempo_dolor', 'descripcion', 'incremento'].forEach((name, index) => { const field = container.querySelector('[name="' + name + '"]'); changes[index + 2] = field ? field.value.trim() : ''; }); if (Object.keys(changes).some(key => Number(key) > 1 && !changes[key])) { setMessage('Completa las respuestas sobre tu dolor antes de continuar.', 'error'); return; } }
        const buttons = container.querySelectorAll('button'); buttons.forEach(button => { button.disabled = true; });
        requestJson('historia-clinica/paciente/editar-evaluacion-dolor-modulo9', { idPaciente: patientId, changes }).then(() => { setMessage('Respuesta guardada.', 'success'); if (finishAfter) return finish(); }).catch(error => setMessage(error.message || 'No se pudo guardar la evaluación.', 'error')).finally(() => buttons.forEach(button => { button.disabled = false; }));
    }
    function finish() { return requestJson('historia-clinica/paciente/finalizar-modulo-paciente', { idPaciente: patientId, idModulo: 9 }).then(data => { setMessage('Módulo finalizado.', 'success'); const next = data.next_url || (server + 'historia-clinica'); window.setTimeout(() => { window.location.href = next; }, 350); }); }
    function speak() { if (!('speechSynthesis' in window)) { setMessage('La narración no está disponible en este navegador.', 'error'); PatientAudioControls.unavailable('p11-instructions'); return; } window.speechSynthesis.cancel(); p11SpeechStarted = false; const prompt = document.getElementById('p11VoicePrompt'); PatientAudioControls.idle('p11-instructions'); const utterance = new SpeechSynthesisUtterance(document.getElementById('p11InstructionsText').textContent); utterance.lang = 'es-MX'; utterance.onstart = () => { p11SpeechStarted = true; PatientAudioStatus.playing(prompt); PatientAudioControls.playing('p11-instructions'); }; utterance.onend = () => { p11SpeechStarted = false; PatientAudioStatus.idle(prompt); PatientAudioControls.ended('p11-instructions'); }; utterance.onerror = () => { p11SpeechStarted = false; PatientAudioStatus.idle(prompt); PatientAudioControls.idle('p11-instructions'); setMessage('La narración no está disponible en este navegador.', 'error'); }; window.speechSynthesis.speak(utterance); }
    function stopVoice() { const hadSpeech = p11SpeechStarted; p11SpeechStarted = false; if ('speechSynthesis' in window) window.speechSynthesis.cancel(); PatientAudioControls.setState('p11-instructions', hadSpeech ? 'stopped' : 'idle'); if (hadSpeech) PatientAudioStatus.stopped(document.getElementById('p11VoicePrompt')); }
    function openHelp() { const modal = document.getElementById('p11InstructionsModal'); state.lastFocus = PatientInstructionsModal.capture(modal, document.activeElement, '[data-p11-action="open-help"]'); state.modalOpen = true; PatientInstructionsModal.open(modal, { opener: state.lastFocus, focusSelector: '[data-p11-action="close-help"]' }); speak(); }
    function closeHelp() { const modal = document.getElementById('p11InstructionsModal'); stopVoice(); state.modalOpen = false; PatientInstructionsModal.close(modal, { returnFocus: state.lastFocus, fallbackSelector: '[data-p11-action="open-help"]' }); state.lastFocus = null; }
    document.addEventListener('click', event => { const action = event.target.closest('[data-p11-action]'); if (action) { const name = action.dataset.p11Action; if (name === 'open-help') openHelp(); if (name === 'close-help') closeHelp(); if (name === 'read-help') speak(); if (name === 'stop-voice') stopVoice(); } const modal = document.getElementById('p11InstructionsModal'); if (state.modalOpen && event.target === modal) closeHelp(); });
    document.addEventListener('keydown', event => { if (!state.modalOpen) return; PatientInstructionsModal.handleKeydown(event, document.getElementById('p11InstructionsModal'), closeHelp); });
    document.addEventListener('DOMContentLoaded', () => { refreshIcons(); loadSection(localStorage.getItem(progressKey) || 'frente').catch(() => {}); window.setTimeout(openHelp, 120); });
})();
</script>
</body>
</html>
