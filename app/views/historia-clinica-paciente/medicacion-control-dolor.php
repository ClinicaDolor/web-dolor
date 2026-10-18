<?php
use App\Models\MedicacionDolorModel;
use App\Services\ClinicalSummarySecurity;

$model = new MedicacionDolorModel();
$idPaciente = (int)($data['idPaciente'] ?? 0);
$m03CsrfToken = ClinicalSummarySecurity::captureCsrfToken($idPaciente);
$modulos = $model->obtenerModulosM7();
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$pageTitle = $data['title'] ?? 'Medicamentos para controlar el dolor';
$patientName = $data['nombre'] ?? 'Paciente';
$versionedAsset = static function (string $baseUrl, string $assetPath, string $publicPath): string {
    $filesystemPath = realpath(__DIR__ . '/../../public/' . ltrim($publicPath, '/'));
    $version = $filesystemPath !== false && is_file($filesystemPath) ? filemtime($filesystemPath) : false;
    return $baseUrl . $assetPath . ($version === false ? '' : '?v=' . (int)$version);
};
$appCssUrl = $versionedAsset(RUTA_CSS, 'app.css', 'assets/css/app.css');
$audioStatusJsUrl = $versionedAsset(RUTA_JS, 'patient-audio-status.js', 'assets/js/patient-audio-status.js');
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
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= RUTA_JS; ?>loader.js"></script>
<style>
body.patient-nonpathological-page #preguntas-container { display: block; }
body.patient-nonpathological-page .pregunta-container { display: none; }
body.patient-nonpathological-page .pregunta-container.active { display: block; }
</style>
<script>
const p09Themes = <?= json_encode(array_map(static fn($id, $name) => ['id' => (int)$id, 'nombre' => $name], array_keys($modulos), array_values($modulos)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const p09ModuleId = 7;
let p09SaveInFlight = false;
let p09TransitionInFlight = false;
let p09FinalizationInFlight = false;
let p09LastFocusedElement = null;
let p09SpeechRequestId = 0;
let p09SpeechStarted = false;
let p09SpeechMode = null;
let p09VoiceFeedbackTimer = null;

function p09Main() { return document.getElementById('main'); }
function p09PatientId() { return Number(p09Main()?.dataset.paciente || 0); }
function p09Role() { return p09Main()?.dataset.rol || 'Paciente'; }
function p09Key(name) { return `p09:${p09PatientId()}:modulo-${p09ModuleId}:${name}`; }
function p09Theme(id) { return p09Themes.find(theme => String(theme.id) === String(id)) || null; }
function p09SetMessage(message) { const node = document.getElementById('p09-page-message'); if (node) node.textContent = message || ''; }
function p09SetBusy(busy) { document.querySelectorAll('[data-p09-control], [data-p09-next], [data-p09-next-theme], [data-p09-previous-question], [data-p09-previous-theme], [data-p09-finalize]').forEach(control => { if (control.dataset.p09BusyOriginal === undefined) control.dataset.p09BusyOriginal = control.disabled ? 'true' : 'false'; control.disabled = busy || (control.dataset.p09BusyOriginal === 'true'); }); }

function p09StopVoice(announce = false) {
    const hadSpeech = p09SpeechStarted;
    p09SpeechRequestId += 1; p09SpeechStarted = false;
    if (p09VoiceFeedbackTimer !== null) { window.clearTimeout(p09VoiceFeedbackTimer); p09VoiceFeedbackTimer = null; }
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    if (announce && hadSpeech) {
        const status = document.getElementById('p09VoiceStatus'); if (status) PatientAudioStatus.stopped(status);
        const prompt = document.getElementById('p09VoicePrompt'); if (prompt) PatientAudioStatus.stopped(prompt);
    }
    document.querySelectorAll('.p09-voice-stop').forEach(button => { button.hidden = true; button.tabIndex = -1; });
    if (p09SpeechMode === 'instructions') PatientAudioControls.setState('p09-instructions', announce && hadSpeech ? 'stopped' : 'idle');
    p09SpeechMode = null;
}
function p09Speak(text, mode = 'instructions') {
    if (!('speechSynthesis' in window) || typeof SpeechSynthesisUtterance === 'undefined') { p09SetMessage('La lectura por voz no está disponible. Puedes continuar con las instrucciones escritas.'); if (mode === 'instructions') PatientAudioControls.unavailable('p09-instructions'); return false; }
    const cleanText = String(text || '').replace(/\s+/g, ' ').trim(); if (!cleanText) return false;
    p09StopVoice();
    const utterance = new SpeechSynthesisUtterance(cleanText);
    const voices = window.speechSynthesis.getVoices(); const voice = voices.find(item => /^es-MX/i.test(item.lang)) || voices.find(item => /^es[-_]/i.test(item.lang));
    utterance.lang = voice ? voice.lang : 'es-MX'; if (voice) utterance.voice = voice; utterance.rate = .88;
    const requestId = ++p09SpeechRequestId; p09SpeechStarted = false; p09SpeechMode = mode;
    const prompt = document.getElementById('p09VoicePrompt'); const status = document.getElementById('p09VoiceStatus');
    if (mode === 'instructions' && prompt) { PatientAudioStatus.idle(prompt); PatientAudioControls.idle('p09-instructions'); }
    if (mode === 'question' && status) status.textContent = 'Intentando iniciar la lectura de la pregunta.';
    utterance.onstart = () => { if (requestId !== p09SpeechRequestId) return; p09SpeechStarted = true; if (mode === 'instructions' && prompt) { PatientAudioStatus.playing(prompt); PatientAudioControls.playing('p09-instructions'); } if (mode === 'question' && status) status.textContent = 'Reproduciendo la pregunta activa. Puedes detenerla o repetirla.'; document.querySelectorAll('.p09-voice-stop').forEach(button => { button.hidden = false; button.tabIndex = 0; }); };
    utterance.onend = () => { if (requestId !== p09SpeechRequestId) return; p09SpeechStarted = false; if (mode === 'instructions' && prompt) { PatientAudioStatus.idle(prompt); PatientAudioControls.ended('p09-instructions'); } if (mode === 'question' && status) status.textContent = 'Lectura de la pregunta finalizada.'; document.querySelectorAll('.p09-voice-stop').forEach(button => { button.hidden = true; button.tabIndex = -1; }); p09SpeechMode = null; };
    utterance.onerror = () => { if (requestId !== p09SpeechRequestId) return; p09SpeechStarted = false; if (mode === 'instructions' && prompt) { prompt.textContent = 'No se pudo iniciar la lectura. Presiona Escuchar para intentarlo de nuevo.'; PatientAudioControls.idle('p09-instructions'); } if (mode === 'question' && status) status.textContent = 'No se pudo iniciar la lectura. Presiona Escuchar para intentarlo de nuevo.'; p09SpeechMode = null; };
    try { window.speechSynthesis.speak(utterance); p09VoiceFeedbackTimer = window.setTimeout(() => { if (requestId === p09SpeechRequestId && !p09SpeechStarted) { window.speechSynthesis.cancel(); if (mode === 'instructions' && prompt) prompt.textContent = 'No se pudo iniciar automáticamente. Presiona Escuchar para intentarlo.'; } }, 1500); return true; } catch (error) { p09SetMessage('La lectura por voz no está disponible en este momento.'); return false; }
}
function p09ReadInstructions() { p09Speak(document.getElementById('p09InstructionsText')?.textContent || 'En esta sección revisaremos los medicamentos utilizados para controlar el dolor.'); }
function p09ReadCurrentQuestion() { const modal = document.getElementById('p09InstructionsModal'); if (modal && modal.getAttribute('aria-hidden') !== 'true') { p09SetMessage('Cierra las instrucciones para escuchar la pregunta.'); return; } const active = document.querySelector('.p09-question.active'); if (!active) return; const copy = active.cloneNode(true); copy.querySelectorAll('button, input, select, textarea, .p09-navigation, [aria-live]').forEach(node => node.remove()); p09Speak(copy.textContent, 'question'); }
function p09OpenInstructions() { const modal = document.getElementById('p09InstructionsModal'); if (!modal) return; p09LastFocusedElement = PatientInstructionsModal.capture(modal, document.activeElement, '.patient-nonpathological-help'); PatientInstructionsModal.open(modal, { opener: p09LastFocusedElement, focusSelector: '.th-modal-close' }); p09ReadInstructions(); }
function p09CloseInstructions() { const modal = document.getElementById('p09InstructionsModal'); if (!modal) return; PatientInstructionsModal.close(modal, { returnFocus: p09LastFocusedElement, fallbackSelector: '.patient-nonpathological-help' }); p09StopVoice(); p09LastFocusedElement = null; window.setTimeout(p09ReadCurrentQuestion, 0); }
function p09StorageTheme() { return localStorage.getItem(p09Key('theme')) || String(p09Themes[0]?.id || ''); }
function p09StorageIndex(themeId) { const value = Number(localStorage.getItem(p09Key('question-' + themeId))); return Number.isInteger(value) && value >= 0 ? value : 0; }
function p09SavePosition(themeId, index) { localStorage.setItem(p09Key('theme'), String(themeId)); localStorage.setItem(p09Key('question-' + themeId), String(index)); }
function p09Active() { return document.querySelector('.p09-question.active'); }
function p09Questions(themeId) { return Array.from(document.querySelectorAll('#contePreguntas_' + themeId + ' .p09-question')); }
function p09ShowQuestion(themeId, index = p09StorageIndex(themeId)) { const questions = p09Questions(themeId); if (!questions.length) return; const safeIndex = Math.min(Math.max(index, 0), questions.length - 1); questions.forEach((question, questionIndex) => question.classList.toggle('active', questionIndex === safeIndex)); p09SavePosition(themeId, safeIndex); p09RefreshState(); if (window.feather) window.feather.replace(); }
async function p09LoadTheme(themeId, index = null, announce = false) { if (p09TransitionInFlight) return; const theme = p09Theme(themeId); if (!theme) return; p09TransitionInFlight = true; p09SetBusy(true); p09StopVoice(); try { const response = await fetch(`/buscar/contenido-preguntas-modulo-7/${encodeURIComponent(p09PatientId())}/${encodeURIComponent(p09Role())}/${encodeURIComponent(theme.id)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); if (!response.ok) throw new Error('load'); const html = await response.text(); const target = document.getElementById('contePreguntas_' + theme.id); if (!target) throw new Error('target'); document.querySelectorAll('[id^="contePreguntas_"]').forEach(node => { if (node !== target) node.innerHTML = ''; }); target.innerHTML = html; p09ShowQuestion(theme.id, index === null ? p09StorageIndex(theme.id) : index); if (announce) p09SetMessage('Sección cargada: ' + theme.nombre + '.'); } catch (error) { p09SetMessage('No se pudo cargar la sección. Inténtalo nuevamente.'); } finally { p09TransitionInFlight = false; p09SetBusy(false); } }
function p09Value(control) { return control?.value?.trim?.() ?? ''; }
function p09Controls(question) { return Array.from(question?.querySelectorAll('[data-p09-control]') || []); }
function p09Field(question, type) { return question?.querySelector(`[data-p09-control="${type}"]`); }
function p09Ready(question) { if (!question) return false; const response = question.querySelector('[data-p09-control="response"]:checked')?.value || ''; if (!response) return false; if (response === 'No') return true; const dose = p09Value(p09Field(question, 'text')); const result = question.querySelector('[data-p09-control="result"]:checked')?.value || ''; const consumption = question.querySelector('[data-p09-control="consumption"]:checked')?.value || ''; const adverse = result === 'Tuve efectos adversos' ? p09Value(question.querySelector('[data-p09-type="5"]')) : true; return Boolean(dose && result && consumption && adverse); }
function p09RefreshState() { const active = p09Active(); if (!active) return; const next = active.querySelector('[data-p09-next]'); if (next) { const ready = p09Ready(active); next.disabled = !ready || p09SaveInFlight || p09TransitionInFlight; next.setAttribute('aria-disabled', next.disabled ? 'true' : 'false'); next.title = ready ? 'Continuar' : 'Completa la respuesta para continuar'; } }
async function p09Request(control, value) { if (p09SaveInFlight) return false; const payload = { idPaciente: p09PatientId(), idRespuesta: Number(control.dataset.p09Response || 0), idPregunta: Number(control.dataset.p09QuestionId || 0), idTema: Number(control.dataset.p09Theme), idTipo: Number(control.dataset.p09Type), detalle: value, idRol: p09Role() }; p09SaveInFlight = true; p09SetBusy(true); p09SetMessage('Guardando respuesta...'); try { const response = await fetch('/historia-clinica/paciente/editar-cuestionario-modulo7', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-M03-CSRF': document.getElementById('main')?.dataset.m03Csrf || '' }, body: JSON.stringify(payload) }); const data = await response.json(); if (!response.ok || !data || !data.resultado) throw new Error(data?.mensaje || 'No se pudo guardar la respuesta.'); if (Number(data.idRespuesta) > 0) { const question = control.closest('.p09-question'); question?.querySelectorAll('[data-p09-control]').forEach(item => { item.dataset.p09Response = String(data.idRespuesta); }); } control.dataset.p09SavedValue = value; p09SetMessage('Respuesta guardada.'); return true; } catch (error) { p09SetMessage(error.message || 'No se pudo guardar la respuesta.'); return false; } finally { p09SaveInFlight = false; p09SetBusy(false); p09RefreshState(); } }
async function p09SaveActive() { const active = p09Active(); if (!active) return false; const currentResult = active.querySelector('[data-p09-control="result"]:checked')?.value || ''; for (const control of p09Controls(active)) { if (control.dataset.p09Type === '5' && currentResult !== 'Tuve efectos adversos') continue; const value = control.type === 'radio' ? (control.checked ? control.value : null) : p09Value(control); if (value === null || value === (control.dataset.p09SavedValue || '')) continue; if (!await p09Request(control, value)) return false; } return true; }
async function p09HandleControl(control) { const active = p09Active(); if (!active) return; const savedAlready = control.dataset.p09SavedValue === control.value; if (control.dataset.p09Control === 'response') { const saved = savedAlready || await p09Request(control, control.value); if (!saved) return; if (control.value === 'No') { p09Advance(active); return; } await p09LoadTheme(Number(active.dataset.p09Theme), Number(active.dataset.index || 0)); return; } if (control.dataset.p09Control === 'result' || control.dataset.p09Control === 'consumption') { const saved = await p09SaveActive(); if (saved && control.dataset.p09Control === 'result') await p09LoadTheme(Number(active.dataset.p09Theme), Number(active.dataset.index || 0)); } else p09RefreshState(); }
async function p09Advance(active) { if (!active || p09SaveInFlight || p09TransitionInFlight || !(await p09SaveActive())) return; const themeId = Number(active.dataset.p09Theme); const questions = p09Questions(themeId); const index = questions.indexOf(active); if (index < questions.length - 1) { p09ShowQuestion(themeId, index + 1); p09ReadCurrentQuestion(); return; } const themeIndex = p09Themes.findIndex(theme => String(theme.id) === String(themeId)); if (themeIndex < p09Themes.length - 1) { await p09LoadTheme(p09Themes[themeIndex + 1].id, 0, true); p09ReadCurrentQuestion(); return; } await p09Finalize(); }
async function p09Finalize() { if (p09FinalizationInFlight || p09SaveInFlight || p09TransitionInFlight) return; const active = p09Active(); if (!active || !p09Ready(active)) { p09SetMessage('Completa los datos del medicamento antes de finalizar.'); return; } if (!await p09SaveActive()) return; p09FinalizationInFlight = true; p09SetBusy(true); p09SetMessage('Finalizando módulo...'); try { const response = await fetch('/historia-clinica/paciente/finalizar-modulo-paciente', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-M03-CSRF': document.getElementById('main')?.dataset.m03Csrf || '' }, body: JSON.stringify({ idModulo: p09ModuleId, idPaciente: p09PatientId() }) }); const data = await response.json(); if (!response.ok || !data || !data.resultado || typeof data.next_url !== 'string' || !data.next_url.startsWith('/')) throw new Error('finalize'); window.location.assign(data.next_url); } catch (error) { p09SetMessage('No se pudo finalizar el módulo. Inténtalo nuevamente.'); } finally { p09FinalizationInFlight = false; p09SetBusy(false); } }
document.addEventListener('click', event => { const control = event.target.closest?.('[data-p09-control]'); if (control && control.type === 'radio') p09HandleControl(control); const next = event.target.closest?.('[data-p09-next]'); if (next) p09Advance(p09Active()); const nextTheme = event.target.closest?.('[data-p09-next-theme]'); if (nextTheme) p09Advance(p09Active()); const previous = event.target.closest?.('[data-p09-previous-question]'); if (previous) { const active = p09Active(); const questions = p09Questions(Number(active?.dataset.p09Theme)); const index = questions.indexOf(active); if (index > 0) p09ShowQuestion(Number(active.dataset.p09Theme), index - 1); } const previousTheme = event.target.closest?.('[data-p09-previous-theme]'); if (previousTheme) p09LoadTheme(Number(previousTheme.dataset.p09PreviousTheme), 0, true); const finalize = event.target.closest?.('[data-p09-finalize]'); if (finalize) p09Finalize(); });
document.addEventListener('input', event => { if (event.target.matches?.('[data-p09-control="text"]')) p09RefreshState(); });
document.addEventListener('keydown', event => { const modal = document.getElementById('p09InstructionsModal'); if (!modal || modal.getAttribute('aria-hidden') === 'true') return; if (event.key === 'Escape') { event.preventDefault(); p09CloseInstructions(); } if (event.key === 'Tab') { const controls = Array.from(modal.querySelectorAll('button, [href], input, select, textarea')).filter(node => !node.disabled && node.offsetParent !== null); if (controls.length) { const first = controls[0], last = controls[controls.length - 1]; if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); } } } });
document.addEventListener('click', event => { if (event.target?.id === 'p09InstructionsModal') p09CloseInstructions(); });
window.addEventListener('pagehide', () => p09StopVoice());
document.addEventListener('DOMContentLoaded', async () => { if (window.feather) window.feather.replace(); const themeId = Number(p09StorageTheme()) || Number(p09Themes[0]?.id); await p09LoadTheme(themeId); p09OpenInstructions(); });
</script>
</head>
<body class="app-shell-body app-shell-patient patient-clinical-form patient-nonpathological-page patient-pain-medication-page">
<div class="LoaderPage"></div><div id="app">
<?= $data['sidebar']; ?>
<div id="main" data-rol="<?= $escape($data['idRol'] ?? 'Paciente'); ?>" data-paciente="<?= $idPaciente; ?>" data-m03-csrf="<?= $escape($m03CsrfToken); ?>">
<nav class="navbar navbar-header navbar-expand navbar-light"><button type="button" class="sidebar-toggler border-0 bg-transparent" aria-label="Abrir menú principal" aria-controls="sidebar" aria-expanded="true"><span class="navbar-toggler-icon"></span></button><button class="btn navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Abrir navegación"><span class="navbar-toggler-icon"></span></button><div class="collapse navbar-collapse" id="navbarSupportedContent"><ul class="navbar-nav d-flex align-items-center navbar-light ms-auto"></ul></div></nav>
<main class="main-content container-fluid patient-nonpathological-content">
<header class="page-title portal-page-title patient-nonpathological-hero mb-4"><div class="patient-nonpathological-hero-copy"><p class="dashboard-eyebrow">MI HISTORIA CLÍNICA</p><h1><?= $escape($pageTitle); ?></h1><p>Responde las preguntas sobre los medicamentos utilizados para controlar el dolor.</p><span class="patient-nonpathological-context">Paciente: <?= $escape($patientName); ?></span></div><div class="patient-nonpathological-hero-actions"><button type="button" id="p09HelpButton" class="btn patient-nonpathological-help" aria-label="¿Cómo llenar esta sección?" aria-haspopup="dialog" aria-controls="p09InstructionsModal" onclick="p09OpenInstructions()"><i data-feather="help-circle" aria-hidden="true"></i><span>¿Cómo llenar esta sección?</span></button><a class="btn patient-nonpathological-back" href="<?= SERVIDOR; ?>historia-clinica"><i data-feather="arrow-left" aria-hidden="true"></i><span>Volver a mi Historia Clínica</span></a></div></header>
<div id="p09-page-message" class="patient-nonpathological-message" role="alert" aria-live="polite"></div>
<section class="section patient-nonpathological-workspace" aria-label="Medicamentos para controlar el dolor"><section id="preguntas-container" class="card patient-nonpathological-card" aria-labelledby="p09-questions-title"><div class="card-header patient-nonpathological-card-header"><div class="patient-nonpathological-card-heading"><div class="patient-nonpathological-instruction-heading"><div><p class="dashboard-eyebrow">MEDICAMENTOS PARA CONTROLAR EL DOLOR</p><h2 id="p09-questions-title">Responde una pregunta a la vez</h2><p class="patient-nonpathological-instruction texto">Te preguntaremos si has utilizado cada medicamento y, cuando corresponda, podrás registrar dosis, resultados y consumo actual.</p></div></div></div></div><div class="card-body patient-nonpathological-card-body"><div id="mensaje-preguntas" class="patient-nonpathological-inline-message" role="alert" aria-live="polite"></div><div class="patient-nonpathological-question-tools" aria-label="Controles de lectura de la pregunta"><button id="p09ReadQuestionButton" type="button" class="btn patient-nonpathological-voice-button" onclick="p09ReadCurrentQuestion()"><i data-feather="volume-2" aria-hidden="true"></i><span>Escuchar</span></button><button type="button" class="btn patient-nonpathological-voice-button p09-voice-stop" hidden tabindex="-1" onclick="p09StopVoice(true)"><i data-feather="square" aria-hidden="true"></i><span>Detener</span></button><span id="p09VoiceStatus" class="visually-hidden" role="status" aria-live="polite"></span></div><div class="patient-nonpathological-generated"><?php if (!$modulos): ?><div class="alert alert-warning p09-catalog-warning" role="status">El catálogo de este módulo no está disponible en el entorno de prueba. No se cargaron preguntas ni se realizaron cambios.</div><?php else: foreach ($modulos as $idTema => $tema): ?><div id="contePreguntas_<?= (int)$idTema; ?>"></div><?php endforeach; endif; ?></div></div></section></section>
</main>
<div id="p09InstructionsModal" class="modal fade patient-nonpathological-modal patient-instructions-modal" tabindex="-1" role="dialog" aria-labelledby="p09InstructionsTitle" aria-describedby="p09InstructionsText" aria-modal="true" aria-hidden="true" style="display:none;"><div class="modal-dialog modal-dialog-centered patient-instructions-dialog"><div class="modal-content patient-nonpathological-modal-content patient-instructions-modal-content"><div class="modal-header patient-instructions-modal-header"><h2 id="p09InstructionsTitle" class="modal-title patient-instructions-modal-title">Antes de comenzar: Medicamentos para controlar el dolor</h2><?php $modalCloseConfig = ['onclick' => 'p09CloseInstructions()']; include __DIR__ . '/partials/modal-close.php'; unset($modalCloseConfig); ?></div><div class="modal-body patient-instructions-modal-body"><p id="p09InstructionsText" data-narration-instructions>En esta sección revisaremos medicamentos utilizados para controlar el dolor. Te preguntaremos si has utilizado cada medicamento. Si respondes Sí, podrás indicar la dosis, los resultados obtenidos y si todavía lo utilizas. Si tuviste efectos adversos, podrás describirlos. Puedes regresar para revisar tus respuestas. Al terminar continuarás con la siguiente sección de tu Historia Clínica</p><p id="p09VoicePrompt" class="patient-nonpathological-voice-prompt patient-instructions-voice-prompt" role="status" aria-live="polite">Para escuchar las instrucciones, presiona este botón.</p><p class="patient-nonpathological-modal-note patient-instructions-modal-note">Tus respuestas se guardan mientras avanzas. Puedes cerrar esta ventana sin perder tu progreso.</p></div><div class="modal-footer patient-nonpathological-modal-actions patient-instructions-modal-actions"><?php $modalAudioConfig = ['group' => 'p09-instructions', 'listen_id' => 'p09ReadInstructionsButton', 'stop_id' => 'p09StopInstructionsButton', 'listen_class' => 'btn patient-nonpathological-secondary patient-instructions-secondary', 'stop_class' => 'btn patient-nonpathological-secondary patient-instructions-secondary', 'listen_attrs' => ['onclick' => 'p09ReadInstructions()'], 'stop_attrs' => ['onclick' => 'p09StopVoice(true)']]; include __DIR__ . '/partials/modal-audio-controls.php'; unset($modalAudioConfig); ?><button type="button" class="btn btn-primary" onclick="p09CloseInstructions()">Entendido, comenzar</button></div></div></div></div>
<?php include_once __DIR__ . '/../components/footer-doctor.php'; ?>
</div></div>
<script src="<?= $audioStatusJsUrl; ?>"></script><script src="<?= RUTA_JS; ?>patient-instructions-modal.js"></script><script src="<?= RUTA_JS; ?>patient-narration-highlight.js"></script><script src="<?= RUTA_JS; ?>voice-utilities.js"></script><script src="<?= RUTA_JS; ?>/feather-icons/feather.min.js"></script><script src="<?= RUTA_PUBLIC; ?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script><script src="<?= RUTA_JS; ?>app.js"></script><script src="<?= RUTA_JS; ?>main.js"></script>
</body>
</html>
