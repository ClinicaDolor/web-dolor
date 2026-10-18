<?php 
use App\Models\PacienteModulosModelo;
use App\Models\AntecedentesNoPatologicosModel;
use App\Services\ClinicalSummarySecurity;

$model = new AntecedentesNoPatologicosModel();

$modulos = $model->obtenerModulos();

$model2 = new PacienteModulosModelo();
$botonFinalizar = $model2->botonFinalizarModulo(3,$data['idPaciente'],$data['idRol']);
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$pageTitle = $data['title'] ?? 'Antecedentes Personales No Patológicos';
$patientName = $data['nombre'] ?? 'Paciente';
$m03CsrfToken = ClinicalSummarySecurity::captureCsrfToken((int)$data['idPaciente']);
$versionedAsset = static function (string $baseUrl, string $assetPath, string $publicPath): string {
    $filesystemPath = realpath(__DIR__ . '/../../public/' . ltrim($publicPath, '/'));
    $version = $filesystemPath !== false && is_file($filesystemPath) ? filemtime($filesystemPath) : false;

    return $baseUrl . $assetPath . ($version === false ? '' : '?v=' . (int) $version);
};
$appCssUrl = $versionedAsset(RUTA_CSS, 'app.css', 'assets/css/app.css');
$audioStatusJsUrl = $versionedAsset(RUTA_JS, 'patient-audio-status.js', 'assets/js/patient-audio-status.js');

?>
 
    <!DOCTYPE html>
    <html lang="es-MX">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$escape($pageTitle);?></title>
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?=$appCssUrl;?>">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>
 
    <style>
    /* Contenedor de preguntas visible inicialmente */
    body.patient-nonpathological-page #preguntas-container { display: block; }
    body.patient-nonpathological-page .pregunta-container { display: none; }
    body.patient-nonpathological-page .pregunta-container.active { display: block; }
    /* Sección de comentarios oculta inicialmente */
    body.patient-nonpathological-page #comentarios-container { display: none; }
    </style>

    <script>
    // Pasar los datos de PHP a JavaScript
    const modulos = <?=json_encode($modulos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;

    // Crear el arreglo de temas (adaptado al formato que usas en el frontend)
    const temas = Object.keys(modulos).map(id => {
    return { id: id, nombre: modulos[id] };
    });
    const p05ModuleId = 3;
    let p05SaveInFlight = false;
    let p05TransitionInFlight = false;
    let p05PendingAdvance = null;
    let p05FinalizationInFlight = false;
    let p05LastFocusedElement = null;
    let p05SpeechMode = null;
    let p05SpeechRequestId = 0;
    let p05VoiceFeedbackTimer = null;
    let p05VoicesChangeBound = false;
    let p05SpeechStarted = false;
    function p05SetQuestionVoiceActive(active) {
    const speakButton = document.getElementById('p05ReadQuestionButton');
    const stopButton = document.getElementById('p05StopQuestionButton');
    if (speakButton) speakButton.setAttribute('aria-pressed', active ? 'true' : 'false');
    if (stopButton) {
    stopButton.hidden = !active;
    stopButton.setAttribute('aria-hidden', active ? 'false' : 'true');
    stopButton.tabIndex = active ? 0 : -1;
    }
    }

    function p05MarkVoiceNeedsAction(needsAction) {
    document.querySelectorAll('.p05-voice-speak, [data-audio-controls-group="p05-instructions"] [data-audio-control="listen"]').forEach(button => {
    button.classList.toggle('p05-voice-needs-action', needsAction);
    });
    }

    function p05ClearVoiceFeedbackTimer() {
    if (p05VoiceFeedbackTimer !== null) {
    window.clearTimeout(p05VoiceFeedbackTimer);
    p05VoiceFeedbackTimer = null;
    }
    }

    function p05StopVoice(announce = false) {
    const hadSpeech = p05SpeechStarted;
    const previousMode = p05SpeechMode;
    p05SpeechRequestId += 1;
    p05SpeechMode = null;
    p05SpeechStarted = false;
    p05ClearVoiceFeedbackTimer();
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    p05SetQuestionVoiceActive(false);
    p05MarkVoiceNeedsAction(false);
    if (previousMode === 'instructions') {
    PatientAudioControls.setState('p05-instructions', announce && hadSpeech ? 'stopped' : 'idle');
    }
    if (!announce || !hadSpeech) return;

    const status = document.getElementById('p05VoiceStatus');
    if (previousMode === 'question') {
    if (status) PatientAudioStatus.stopped(status);
    return;
    }
    const prompt = document.getElementById('p05VoicePrompt');
    if (prompt) PatientAudioStatus.stopped(prompt);
    }

    function p05BindVoicesChanged() {
    if (p05VoicesChangeBound || !('speechSynthesis' in window)) return;
    const synthesis = window.speechSynthesis;
    if (typeof synthesis.addEventListener !== 'function') return;
    synthesis.addEventListener('voiceschanged', () => {}, { once: true });
    p05VoicesChangeBound = true;
    }

    function p05GetSpanishVoice() {
    if (!('speechSynthesis' in window)) return null;
    p05BindVoicesChanged();
    const voices = window.speechSynthesis.getVoices();
    return voices.find(voice => /^es-MX/i.test(voice.lang))
        || voices.find(voice => /^es(-|_)/i.test(voice.lang))
        || null;
    }

    function p05SpeakText(text, mode = 'instructions') {
    if (!('speechSynthesis' in window) || typeof SpeechSynthesisUtterance === 'undefined') {
    p05SetMessage('La lectura por voz no está disponible en este navegador. Puedes continuar con las instrucciones escritas.');
    const prompt = document.getElementById('p05VoicePrompt');
    if (prompt) prompt.textContent = 'La lectura por voz no está disponible. Puedes continuar con las instrucciones escritas.';
    p05SetQuestionVoiceActive(false);
    p05MarkVoiceNeedsAction(true);
    if (mode === 'instructions') PatientAudioControls.unavailable('p05-instructions');
    return false;
    }

    const cleanText = String(text || '').replace(/\s+/g, ' ').trim();
    if (!cleanText) {
    p05SetMessage('No hay texto disponible para leer en este momento.');
    return false;
    }

    p05StopVoice();
    p05BindVoicesChanged();
    const utterance = new SpeechSynthesisUtterance(cleanText);
    const voice = p05GetSpanishVoice();
    utterance.lang = voice ? voice.lang : 'es-MX';
    if (voice) utterance.voice = voice;
    utterance.rate = .88;
    utterance.pitch = 1;
    const requestId = p05SpeechRequestId;
    p05SpeechMode = mode;
    p05SpeechStarted = false;
    p05MarkVoiceNeedsAction(false);
    if (mode === 'instructions') {
    const prompt = document.getElementById('p05VoicePrompt');
    if (prompt) PatientAudioStatus.idle(prompt);
    PatientAudioControls.idle('p05-instructions');
    } else {
    const status = document.getElementById('p05VoiceStatus');
    if (status) status.textContent = 'Intentando iniciar la lectura de la pregunta.';
    }
    utterance.onstart = () => {
    if (requestId !== p05SpeechRequestId) return;
    p05SpeechStarted = true;
    p05ClearVoiceFeedbackTimer();
    p05MarkVoiceNeedsAction(false);
    p05SetQuestionVoiceActive(mode === 'question');
    const prompt = document.getElementById('p05VoicePrompt');
    if (mode === 'question') {
    const status = document.getElementById('p05VoiceStatus');
    if (status) status.textContent = 'Reproduciendo la pregunta activa. Puedes detenerla o repetirla.';
    } else if (prompt) {
    PatientAudioStatus.playing(prompt);
    PatientAudioControls.playing('p05-instructions');
    }
    };
    utterance.onend = () => {
    if (requestId !== p05SpeechRequestId) return;
    p05ClearVoiceFeedbackTimer();
    p05SpeechStarted = false;
    p05SpeechMode = null;
    p05SetQuestionVoiceActive(false);
    const status = document.getElementById('p05VoiceStatus');
    if (status) status.textContent = mode === 'question' ? 'Lectura de la pregunta finalizada.' : '';
    const prompt = document.getElementById('p05VoicePrompt');
    if (mode === 'instructions' && prompt) PatientAudioStatus.idle(prompt);
    if (mode === 'instructions') PatientAudioControls.ended('p05-instructions');
    };
    utterance.onerror = () => {
    if (requestId !== p05SpeechRequestId) return;
    p05ClearVoiceFeedbackTimer();
    p05SpeechStarted = false;
    p05SpeechMode = null;
    p05SetQuestionVoiceActive(false);
    p05MarkVoiceNeedsAction(true);
    const status = document.getElementById('p05VoiceStatus');
    if (status && mode === 'question') status.textContent = 'No se pudo iniciar la lectura. Presiona Escuchar para intentarlo de nuevo.';
    const prompt = document.getElementById('p05VoicePrompt');
    if (prompt && mode === 'instructions') prompt.textContent = 'No se pudo iniciar la lectura. Presiona Escuchar para intentarlo de nuevo.';
    if (mode === 'instructions') PatientAudioControls.idle('p05-instructions');
    };
    try {
    window.speechSynthesis.speak(utterance);
    p05VoiceFeedbackTimer = window.setTimeout(() => {
    p05VoiceFeedbackTimer = null;
    if (requestId !== p05SpeechRequestId || p05SpeechStarted || !('speechSynthesis' in window)) return;
    window.speechSynthesis.cancel();
    p05SpeechMode = null;
    p05SetQuestionVoiceActive(false);
    p05MarkVoiceNeedsAction(true);
    const status = document.getElementById('p05VoiceStatus');
    if (status && mode === 'question') status.textContent = 'Presiona Escuchar para oír la pregunta.';
    const prompt = document.getElementById('p05VoicePrompt');
    if (prompt && mode === 'instructions') prompt.textContent = 'No se pudo iniciar la lectura automáticamente. Presiona Escuchar para intentarlo.';
    }, 1500);
    return true;
    } catch (error) {
    p05SpeechMode = null;
    p05SpeechStarted = false;
    p05SetQuestionVoiceActive(false);
    p05MarkVoiceNeedsAction(true);
    const status = document.getElementById('p05VoiceStatus');
    if (status && mode === 'question') status.textContent = 'No se pudo iniciar la lectura. Presiona Escuchar para intentarlo de nuevo.';
    const prompt = document.getElementById('p05VoicePrompt');
    if (prompt && mode === 'instructions') prompt.textContent = 'No se pudo iniciar la lectura. Presiona Escuchar para intentarlo de nuevo.';
    return false;
    }
    }

    function p05ReadInstructions() {
    const instructions = document.getElementById('p05InstructionsText');
    p05SpeakText(instructions ? instructions.textContent : 'Lee o escucha cada pregunta y selecciona la respuesta disponible.');
    }

    function p05ReadCurrentQuestion() {
    const modal = document.getElementById('p05InstructionsModal');
    if (modal && modal.getAttribute('aria-hidden') !== 'true') {
    p05SetMessage('Cierra las instrucciones para escuchar la pregunta.');
    return;
    }
    const active = document.querySelector('.patient-nonpathological-generated .pregunta-container.active');
    if (!active) {
    p05SetMessage('Espera a que cargue la pregunta para utilizar la lectura por voz.');
    return;
    }
    const copy = active.cloneNode(true);
    copy.querySelectorAll('button, input, select, textarea, .p05-choice-card, .mt-3.d-flex, [aria-live], .btnLeer').forEach(control => control.remove());
    p05SpeakText(copy.textContent, 'question');
    }

    function p05ReadQuestionWhenReady() {
    const readActiveQuestion = () => {
    const modal = document.getElementById('p05InstructionsModal');
    if (modal && modal.getAttribute('aria-hidden') !== 'true') return;
    const active = document.querySelector('.patient-nonpathological-generated .pregunta-container.active');
    if (active) p05ReadCurrentQuestion();
    };
    if (typeof queueMicrotask === 'function') queueMicrotask(readActiveQuestion);
    else if (typeof requestAnimationFrame === 'function') requestAnimationFrame(readActiveQuestion);
    else window.setTimeout(readActiveQuestion, 0);
    }

    function p05OpenInstructions() {
    const modal = document.getElementById('p05InstructionsModal');
    if (!modal) return;
    if (modal.getAttribute('aria-hidden') !== 'true') {
    const currentControl = modal.querySelector('button');
    if (currentControl) currentControl.focus();
    return;
    }
    p05StopVoice();
    p05LastFocusedElement = PatientInstructionsModal.capture(modal, document.activeElement, '.patient-nonpathological-help');
    PatientInstructionsModal.open(modal, { opener: p05LastFocusedElement, focusSelector: '.th-modal-close' });
    p05ReadInstructions();
    }

    function p05CloseInstructions() {
    const modal = document.getElementById('p05InstructionsModal');
    if (!modal) return;
    PatientInstructionsModal.close(modal, { returnFocus: p05LastFocusedElement, fallbackSelector: '.patient-nonpathological-help' });
    p05StopVoice();
    p05LastFocusedElement = null;
    p05ReadQuestionWhenReady();
    }

    function p05MarkSidebarActive() {
    const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
    document.querySelectorAll('#sidebar a.sidebar-link').forEach(link => {
    const linkPath = new URL(link.href, window.location.origin).pathname.replace(/\/+$/, '') || '/';
    const item = link.closest('.sidebar-item');
    const active = linkPath === currentPath;
    if (item) item.classList.toggle('active', active);
    if (active) link.setAttribute('aria-current', 'page');
    else link.removeAttribute('aria-current');
    });
    }

    document.addEventListener('DOMContentLoaded', p05MarkSidebarActive, { once: true });

    document.addEventListener('keydown', event => {
    const modal = document.getElementById('p05InstructionsModal');
    if (!modal || modal.getAttribute('aria-hidden') === 'true') return;
    if (event.key === 'Escape') {
    event.preventDefault();
    p05CloseInstructions();
    return;
    }
    if (event.key !== 'Tab') return;
    const controls = Array.from(modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'))
    .filter(control => !control.disabled && control.offsetParent !== null);
    if (!controls.length) return;
    const first = controls[0];
    const last = controls[controls.length - 1];
    if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
    }
    });

    document.addEventListener('click', event => {
    if (event.target && event.target.id === 'p05InstructionsModal') p05CloseInstructions();
    });

    window.addEventListener('pagehide', () => p05StopVoice());

    function p05StorageKey(name) {
    const main = document.getElementById('main');
    const idPaciente = main ? main.getAttribute('data-paciente') : 'unknown';
    return 'p05:' + idPaciente + ':modulo-' + p05ModuleId + ':' + name;
    }

    function p05SetMessage(message, target = 'preguntas') {
    const container = document.getElementById(target === 'comentarios' ? 'comentarios-container' : 'preguntas-container');
    const element = container ? container.querySelector('[data-p05-message]') : null;
    if (element) element.textContent = message || '';
    }

    function p05ThemeById(idTema) {
    return temas.find(tema => String(tema.id) === String(idTema)) || null;
    }

    function p05ThemeIndex(idTema) {
    return temas.findIndex(tema => String(tema.id) === String(idTema));
    }

    function p05StoredSection() {
    return localStorage.getItem(p05StorageKey('seccionActual'));
    }

    function p05SetSection(value) {
    localStorage.setItem(p05StorageKey('seccionActual'), value);
    }

    function p05HasPendingSave() {
    if (p05SaveInFlight) {
    p05SetMessage('Guardando la respuesta actual.');
    return true;
    }
    return false;
    }

    function p05QuestionIsReadyToAdvance(question) {
    if (!question) return true;
    const choices = Array.from(question.querySelectorAll('input[type="radio"]'));
    if (!choices.length) return true;
    const selected = choices.find(input => input.checked);
    if (!selected) return false;
    const requiredFields = Array.from(question.querySelectorAll('input[required], select[required], textarea[required]'))
    .filter(field => !field.disabled && field.offsetParent !== null);
    return requiredFields.every(field => String(field.value || '').trim() !== '');
    }

    function p05NotifyMissingAnswer(question) {
    p05SetMessage('Selecciona una respuesta para continuar.');
    const firstChoice = question ? question.querySelector('input[type="radio"]') : null;
    if (firstChoice && !firstChoice.checked) firstChoice.focus();
    }

    function p05ControlValue(control) {
    if (!control) return '';
    return String(control.value ?? '');
    }

    function p05ControlIsDirty(control) {
    if (!control || control.disabled) return false;
    if (control.type === 'radio') return control.checked
        && p05ControlValue(control) !== String(control.dataset.p05SavedValue || '');
    return p05ControlValue(control) !== String(control.dataset.p05SavedValue || '');
    }

    function p05ActiveQuestion() {
    return document.querySelector('.patient-nonpathological-generated .pregunta-container.active');
    }

    function p05SetNavigationBusy(button, busy) {
    if (!button) return;
    button.disabled = Boolean(busy);
    button.setAttribute('aria-busy', busy ? 'true' : 'false');
    if (busy) button.dataset.p05Busy = 'true';
    else delete button.dataset.p05Busy;
    }

    function p05UpdateRadioSavedState(control) {
    if (!control || control.type !== 'radio') return;
    const question = control.closest('.pregunta-container');
    if (!question) return;
    question.querySelectorAll('input[type="radio"][data-p05-control]').forEach(radio => {
    radio.dataset.p05SavedValue = p05ControlValue(control);
    });
    }

    function p05ClearDependentControls(control) {
    if (!control || control.value !== 'No') return;
    const question = control.closest('.pregunta-container');
    if (!question) return;
    const theme = question.getAttribute('data-tema');
    document.querySelectorAll('.patient-nonpathological-generated .pregunta-container[data-tema="' + theme + '"] [data-p05-control]').forEach(field => {
    if (field === control) return;
    if (field.type === 'radio') field.checked = false;
    else field.value = '';
    field.dataset.p05SavedValue = '';
    });
    }

    function p05SetControlSaved(control) {
    if (!control) return;
    if (control.type === 'radio') p05UpdateRadioSavedState(control);
    else control.dataset.p05SavedValue = p05ControlValue(control);
    p05ClearDependentControls(control);
    }

    function p05GetDirtyControls(question) {
    if (!question) return [];
    return Array.from(question.querySelectorAll('[data-p05-control]')).filter(p05ControlIsDirty);
    }

    function p05QueueAdvance(idTema, trigger) {
    if (!p05PendingAdvance) p05PendingAdvance = { idTema, trigger };
    else if (trigger) p05PendingAdvance.trigger = trigger;
    if (trigger) p05SetNavigationBusy(trigger, true);
    p05SetMessage('Guardando la respuesta actual.');
    }

    document.addEventListener('click', event => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    const radio = target.closest('.patient-nonpathological-generated input[type="radio"][data-p05-control]');
    if (radio) {
    if (p05SaveInFlight || p05TransitionInFlight || radio.disabled) return;
    const question = radio.closest('.pregunta-container');
    if (!question || !question.classList.contains('active')) return;
    const idTema = question.getAttribute('data-tema');
    p05SaveControl(radio).then(saved => {
    if (saved) p05AdvanceCurrentQuestion(idTema);
    });
    return;
    }

    const nextButton = target.closest('[data-p05-next]');
    if (nextButton) {
    event.preventDefault();
    const nextQuestion = nextButton.closest('.pregunta-container');
    if (!nextQuestion || !nextQuestion.classList.contains('active')) return;
    if (nextButton.dataset.p05Busy === 'true' || p05TransitionInFlight) return;
    p05AdvanceCurrentQuestion(nextButton.dataset.p05Theme, nextButton);
    return;
    }

    const nextThemeButton = target.closest('[data-p05-next-theme]');
    if (nextThemeButton) {
    event.preventDefault();
    if (p05TransitionInFlight) return;
    irASiguienteSeccion(nextThemeButton.dataset.p05NextTheme, Number(nextThemeButton.dataset.p05NextThemeId));
    return;
    }

    const finalizeButton = target.closest('[data-p05-finalize]');
    if (finalizeButton) {
    event.preventDefault();
    if (p05TransitionInFlight) return;
    p05AdvanceCurrentQuestion(finalizeButton.getAttribute('data-p05-theme') || undefined, finalizeButton);
    }
    });

    document.addEventListener('change', event => {
    const target = event.target instanceof Element ? event.target : null;
    const control = target ? target.closest('.patient-nonpathological-generated [data-p05-control]') : null;
    if (!control || control.type === 'radio' || control.disabled) return;
    if (p05SaveInFlight || p05TransitionInFlight) return;
    p05SaveControl(control);
    });

    // Al cargar la página se recupera sólo la navegación de este paciente y módulo.
    document.addEventListener("DOMContentLoaded", function() {
    const seccionActual = p05StoredSection();
    let temaActual = temas.find(t => t.nombre === seccionActual) || temas[0];

    if (seccionActual === "comentarios") {
    finalizarPreguntas();
    } else if (temaActual) {
    p05SetSection(temaActual.nombre);
    contenidoPreguntas(temaActual.id);
    }

    // Inicializar sólo el estado de navegación de los temas existentes.
    for (const tema of temas) {
    const key = p05StorageKey('preguntaActual_' + tema.id);
    if (localStorage.getItem(key) === null) {
    localStorage.setItem(key, 0);
    }
    }

    if (window.feather) feather.replace();
    p05OpenInstructions();

    });

    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas(idCuestionario, idValor = 0) {
    const tema = p05ThemeById(idCuestionario);
    if (!tema) {
    p05SetMessage('No se encontró la sección solicitada.');
    return Promise.resolve(false);
    }
    p05StopVoice();

    // Limpia las preguntas anteriores, si existen
    document.querySelectorAll('.pregunta-container').forEach(p => p.remove());

    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    return fetch('/buscar/contenido-preguntas-modulo-3/'
    + encodeURIComponent(idPaciente) + '/'
    + encodeURIComponent(idRol) + '/'
    + encodeURIComponent(idCuestionario))
    .then(response => {
    if (!response.ok) throw new Error('No se pudo cargar la sección.');
    return response.text();
    })
    .then(data => {
    // Insertar el contenido en el contenedor específico del módulo
    document.getElementById('contePreguntas_' + idCuestionario).innerHTML = data;
    feather.replace();

    // Reactivar el IntersectionObserver para las nuevas secciones (si lo utilizas)
    document.querySelectorAll('.sectionQuestion').forEach(section => {
    if (typeof observer === 'undefined') return;
    observer.observe(section);
    });

    // Cargar el progreso para el módulo actual
    cargarProgreso(idCuestionario, idValor);
    p05ReadQuestionWhenReady();
    return true;
    })
    .catch(() => {
    p05SetMessage('No se pudo cargar la sección. Intenta nuevamente.');
    return false;
    });
    }

    // ---------- CONTENIDO DEL COMENTARIO ----------
    function contenidoComentario() {
    p05StopVoice();
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');
    const idModulo = 3;

    return fetch('/buscar/contenido-comentarios-modulos/'
    + encodeURIComponent(idPaciente) + '/'
    + encodeURIComponent(idRol) + '/'
    + idModulo)
    .then(response => {
    if (!response.ok) throw new Error('No se pudieron cargar los comentarios.');
    return response.text();
    })
    .then(data => {

    document.getElementById('conteComentario').innerHTML = data;
    feather.replace();   
    return true;
    })
    .catch(() => {
    p05SetMessage('No se pudieron cargar los comentarios.', 'comentarios');
    return false;
    });
    }  

    // ---------- GUARDAR Y CARGAR PROGRESO ----------
    function guardarProgreso(index, idCuestionario) {
    localStorage.setItem(p05StorageKey('preguntaActual_' + idCuestionario), index);
    }

    function cargarProgreso(idCuestionario, idValor = 0) {
    let indexGuardado = parseInt(localStorage.getItem(p05StorageKey('preguntaActual_' + idCuestionario)), 10);
    if (!Number.isInteger(indexGuardado) || indexGuardado < 0) {
    indexGuardado = Number.isInteger(idValor) && idValor >= 0 ? idValor : 0;
    }
    const preguntas = document.querySelectorAll('.pregunta-container');

    // Remover la clase 'active' de todas las preguntas
    preguntas.forEach(pregunta => pregunta.classList.remove('active'));

    // Asegurar que el índice guardado no exceda el número de preguntas
    if (preguntas.length > 0) {
    if (indexGuardado >= preguntas.length) {
    indexGuardado = preguntas.length - 1;
    }
    preguntas[indexGuardado].classList.add('active');
    }
    return indexGuardado;
    }

    // ---------- NAVEGACIÓN ENTRE PREGUNTAS ----------
    function p05AdvanceDom(idTema) {
    const preguntas = Array.from(document.querySelectorAll('.patient-nonpathological-generated .pregunta-container'));
    const activeQuestion = p05ActiveQuestion();
    if (!preguntas.length || !activeQuestion) return false;
    const activeIndex = preguntas.indexOf(activeQuestion);
    if (activeIndex < 0) return false;

    p05StopVoice();
    activeQuestion.classList.remove('active');

    const selected = activeQuestion.querySelector('input[type="radio"]:checked');
    const themeIndex = p05ThemeIndex(idTema || activeQuestion.getAttribute('data-tema'));
    if (selected && selected.value === 'No' && activeIndex === 0) {
    const nextTheme = themeIndex >= 0 ? temas[themeIndex + 1] : null;
    if (nextTheme) {
    p05SetSection(nextTheme.nombre);
    contenidoPreguntas(nextTheme.id);
    return true;
    }
    finalizarPreguntas();
    return true;
    }

    if (activeIndex < preguntas.length - 1) {
    const nextIndex = activeIndex + 1;
    preguntas[nextIndex].classList.add('active');
    guardarProgreso(nextIndex, idTema || activeQuestion.getAttribute('data-tema'));
    p05SetMessage('');
    p05ReadQuestionWhenReady();
    return true;
    }

    const nextTheme = themeIndex >= 0 ? temas[themeIndex + 1] : null;
    if (nextTheme) {
    p05SetSection(nextTheme.nombre);
    contenidoPreguntas(nextTheme.id);
    } else {
    finalizarPreguntas();
    }
    return true;
    }

    async function p05AdvanceCurrentQuestion(idTema, trigger = null) {
    if (p05TransitionInFlight) return false;
    const activeQuestion = p05ActiveQuestion();
    if (!activeQuestion) return false;
    const resolvedTheme = idTema || activeQuestion.getAttribute('data-tema');
    if (!p05QuestionIsReadyToAdvance(activeQuestion)) {
    p05NotifyMissingAnswer(activeQuestion);
    return false;
    }
    if (p05SaveInFlight) {
    p05QueueAdvance(resolvedTheme, trigger);
    return false;
    }

    p05TransitionInFlight = true;
    if (trigger) p05SetNavigationBusy(trigger, true);
    try {
    const dirtyControls = p05GetDirtyControls(activeQuestion);
    for (const control of dirtyControls) {
    const saved = await p05SaveControl(control);
    if (!saved) return false;
    }
    return p05AdvanceDom(resolvedTheme);
    } finally {
    p05TransitionInFlight = false;
    if (trigger && trigger.isConnected) p05SetNavigationBusy(trigger, false);
    }
    }

    function siguientePregunta(idTema) {
    return p05AdvanceCurrentQuestion(idTema);
    }

    //---------- MUESTRA LA PREGUNTA ANTERIOR ----------
    function anteriorPregunta(idTema) {
    if (p05HasPendingSave()) return;
    p05StopVoice();
    const preguntas = document.querySelectorAll('.pregunta-container');
    let activeIndex = -1;

    $(".LoaderPage").hide();

    preguntas.forEach((pregunta, index) => {
    if (pregunta.classList.contains('active')) {
    activeIndex = index;
    pregunta.classList.remove('active');
    }
    });
 
    if (activeIndex === -1 && preguntas.length > 0) {
    activeIndex = cargarProgreso(idTema);
    }

    // Si se está en la primera pregunta del módulo...
    if (activeIndex <= 0) {
    let seccionActual = p05StoredSection() || (temas[0] ? temas[0].nombre : '');
    let currentIndex = temas.findIndex(t => t.nombre === seccionActual);
    if (currentIndex > 0) {
    let nuevoTema = temas[currentIndex - 1];
    p05SetSection(nuevoTema.nombre);
    contenidoPreguntas(nuevoTema.id);
    return;
    }
    } else {
    // Retroceder a la pregunta anterior dentro del mismo módulo
    const nuevoIndice = activeIndex - 1;
    preguntas[nuevoIndice].classList.add('active');
    guardarProgreso(nuevoIndice, idTema);
    p05ReadQuestionWhenReady();
    }
    }

    //---------- IR A LA SIGUIENTE SECCION ----------
    function irASiguienteSeccion(nombreTema, idTema) {
    p05StopVoice();
    $(".LoaderPage").hide();
    p05SetSection(nombreTema);
    contenidoPreguntas(idTema);
    }

    //---------- MUESTRA LA SECCION DE PREGUNTAS Y COMENTARIOS ----------
    function seccionPreguntas() {
    p05StopVoice();
    $(".LoaderPage").hide();
    document.getElementById("preguntas-container").style.display = "block";
    document.getElementById("comentarios-container").style.display = "none";
    const ultimoTema = temas[temas.length - 1];
    if (!ultimoTema) return;
    p05SetSection(ultimoTema.nombre);
    contenidoPreguntas(ultimoTema.id);
    }

    function finalizarPreguntas() {
    p05StopVoice();
    $(".LoaderPage").hide();
    document.getElementById("preguntas-container").style.display = "none";
    document.getElementById("comentarios-container").style.display = "block";
    p05SetSection("comentarios");
    contenidoComentario();
    }


    //---------- CONTROL SERVER ----------
    function gestionarAntecedentesNoPatologicos(url, parametros, callback, idUpdate = 0) {
    if (p05SaveInFlight) {
    p05SetMessage('Guardando la respuesta actual.');
    return Promise.resolve(false);
    }
    p05SaveInFlight = true;
    if(idUpdate == 1){
    $(".LoaderPage").show();
    }
    const main = document.getElementById('main');
    const csrfToken = main ? main.getAttribute('data-m03-csrf') : '';
    return fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-M03-CSRF': csrfToken },
    body: JSON.stringify(parametros)
    }).then(res => {
    if (!res.ok) throw new Error('Solicitud no completada.');
    return res.json();
    }).then(data => {
    if(idUpdate == 1){
    $(".LoaderPage").hide();
    }
    if (data.resultado) {
    if (typeof callback === 'function') callback();
    return true;
    }
    p05SetMessage('Error: ' + (data.mensaje || 'No se pudo guardar la respuesta.'));
    return false;
    }).catch(() => {
    if(idUpdate == 1) $(".LoaderPage").hide();
    p05SetMessage('No se pudo guardar la respuesta. Intenta nuevamente.');
    return false;
    }).finally(() => {
    p05SaveInFlight = false;
    });
    }

    function p05SaveControl(control) {
    if (!control || !control.isConnected || control.disabled) return Promise.resolve(false);
    if (!p05ControlIsDirty(control)) return Promise.resolve(true);
    if (p05SaveInFlight) return Promise.resolve(false);

    const main = document.getElementById('main');
    const idPaciente = Number(control.dataset.p05Patient || (main ? main.getAttribute('data-paciente') : 0));
    const idRespuesta = Number(control.dataset.p05Response || 0);
    const idPregunta = Number(control.dataset.p05QuestionId || control.closest('.pregunta-container')?.getAttribute('data-pregunta-id') || 0);
    const idTema = Number(control.dataset.p05Theme || 0);
    const idTipo = control.dataset.p05Type || '';
    const idRol = control.dataset.p05Role || (main ? main.getAttribute('data-rol') : '');
    const detalle = p05ControlValue(control);
    if (!idPaciente || !idPregunta || !idTema || !idTipo || !idRol) {
    p05SetMessage('No se pudo identificar la respuesta. Intenta nuevamente.');
    return Promise.resolve(false);
    }

    let saved = false;
    return gestionarAntecedentesNoPatologicos('/' + (idRol === 'Paciente' ? 'historia-clinica' : 'clinica') + '/paciente/editar-cuestionario-modulo3', {
    idPaciente,
    idRespuesta,
    idPregunta,
    idTema,
    idTipo,
    detalle,
    expectedRespuesta: String(control.dataset.p05SavedValue || ''),
    idRol
    }, null, control.type === 'radio' ? 1 : 0).then(result => {
    saved = result;
    if (saved) {
    p05SetControlSaved(control);
    p05SetMessage('');
    }
    return saved;
    }).finally(() => {
    const pending = p05PendingAdvance;
    if (!pending) return;
    p05PendingAdvance = null;
    if (saved) {
    p05AdvanceCurrentQuestion(pending.idTema, pending.trigger);
    } else if (pending.trigger && pending.trigger.isConnected) {
    p05SetNavigationBusy(pending.trigger, false);
    }
    });
    }

    function p05RequestJson(url, parametros) {
    const main = document.getElementById('main');
    const csrfToken = main ? main.getAttribute('data-m03-csrf') : '';
    return fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-M03-CSRF': csrfToken },
    body: JSON.stringify(parametros)
    }).then(res => {
    if (!res.ok) throw new Error('Solicitud no completada.');
    return res.json();
    }).then(data => {
    if (!data.resultado) throw new Error(data.mensaje || 'No se pudo completar la solicitud.');
    return data;
    });
    }


    //---------- EDITAR ENFERMEDADES DEL PACIENTE ----------
    function respuestaPreguntaSelect (idPaciente, idRespuesta, elemento, idTema, idTipo, idRol, idUpdate = 0) {
    if (!elemento) return Promise.resolve(false);
    elemento.dataset.p05Patient = String(idPaciente);
    elemento.dataset.p05Response = String(idRespuesta);
    elemento.dataset.p05Theme = String(idTema);
    elemento.dataset.p05Type = String(idTipo);
    elemento.dataset.p05Role = String(idRol);
    return p05SaveControl(elemento);
    }
   
    //---------- AGREGAR COMENTARIO DEL MODULO ----------
    async function agregarComentario(idModulo, idPaciente, idRol) {
    if (p05FinalizationInFlight) return;
    if (p05HasPendingSave()) return;
    p05StopVoice();
    p05FinalizationInFlight = true;
    let comentario = document.getElementById('comentarioModulos').value;
    const button = document.querySelector('#comentarios-container button.btn-success');
    if (button) {
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    }
    try {
    await p05RequestJson('/' + (idRol === "Paciente" ? "historia-clinica" : "clinica") + '/paciente/agregar-comentario-modulo', {
    idModulo, idPaciente, comentarioModulos: comentario, idRol
    });
    const result = await p05RequestJson('/historia-clinica/paciente/finalizar-modulo-paciente', { idModulo, idPaciente });
    const nextUrl = typeof result.next_url === 'string' && result.next_url.startsWith('/') ? result.next_url : '/historia-clinica';
    window.location.assign(nextUrl);
    } catch (error) {
    p05SetMessage('No se pudo guardar el comentario o finalizar el módulo.', 'comentarios');
    } finally {
    p05FinalizationInFlight = false;
    if (button) {
    button.disabled = false;
    button.setAttribute('aria-busy', 'false');
    }
    }
    }

    //---------- FINALIZAR MODULO DEL PACIENTE----------
    function FinalizarModuloPaciente(idModulo, idPaciente) {
    agregarComentario(idModulo, idPaciente, 'Paciente');
    }
    </script>
    </head>

<body class="app-shell-body app-shell-patient patient-clinical-form patient-nonpathological-page">
    <div class="LoaderPage"></div>
    <div id="app">
        
    <!---------- SIDEBAR ---------->
    <?=$data['sidebar'];?>
    
    <div id="main" data-rol="<?=$escape($data['idRol']);?>" data-paciente="<?=$escape($data['idPaciente']);?>" data-m03-csrf="<?=$escape($m03CsrfToken);?>">
    <nav class="navbar navbar-header navbar-expand navbar-light portal-topbar" aria-label="Barra de herramientas del portal del paciente">
    <button type="button" class="sidebar-toggler border-0 bg-transparent" aria-label="Abrir menú principal" aria-controls="sidebar" aria-expanded="true"><span class="navbar-toggler-icon"></span></button>
    <button class="btn navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
    </button>
                
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav d-flex align-items-center navbar-light ms-auto">
    </ul>
    </div>
    </nav>

    <!---------- CONTENIDO DE LA PAGINA ---------->
    <main class="main-content container-fluid patient-nonpathological-content">

    <header class="page-title portal-page-title patient-nonpathological-hero mb-4">
    <div class="patient-nonpathological-hero-copy">
    <p class="dashboard-eyebrow">MI HISTORIA CLÍNICA</p>
    <h1><?=$escape($pageTitle);?></h1>
    <p>Responde las preguntas sobre tus hábitos y antecedentes personales no patológicos.</p>
    <span class="patient-nonpathological-context">Paciente: <?=$escape($patientName);?></span>
    </div>
    <div class="patient-nonpathological-hero-actions">
    <button type="button" id="p05HelpButton" class="btn patient-nonpathological-help" aria-label="¿Cómo llenar esta sección?" aria-haspopup="dialog" aria-controls="p05InstructionsModal" onclick="p05OpenInstructions()">
    <i data-feather="help-circle" aria-hidden="true"></i>
    <span>¿Cómo llenar esta sección?</span>
    </button>
    <a class="btn patient-nonpathological-back" href="<?=SERVIDOR;?>historia-clinica">
    <i data-feather="arrow-left" aria-hidden="true"></i>
    <span>Volver a mi Historia Clínica</span>
    </a>
    </div>
    </header>

    <div id="p05-page-message" class="patient-nonpathological-message" role="alert" aria-live="polite"></div>

    <section class="section patient-nonpathological-workspace" aria-label="Antecedentes Personales No Patológicos">

    <section id="preguntas-container" class="card patient-nonpathological-card" aria-labelledby="patient-nonpathological-questions-title">

    <div class="card-header patient-nonpathological-card-header">
    <div class="patient-nonpathological-card-heading">

    <div id="seccion100" data-autoplay="false" class="sectionQuestion patient-nonpathological-instruction-heading">
    <div>
    <p class="dashboard-eyebrow">ANTECEDENTES PERSONALES NO PATOLÓGICOS</p>
    <h2 id="patient-nonpathological-questions-title">Responde una pregunta a la vez</h2>
    <p class="patient-nonpathological-instruction texto">A continuación, responde las preguntas indicando si presentas alguna de estas conductas o hábitos.</p>
    </div>
    </div>
    </div>
    </div>

    <div class="card-body patient-nonpathological-card-body">
    <div id="mensaje-preguntas" data-p05-message class="patient-nonpathological-inline-message" role="alert" aria-live="polite"></div>
    <div class="patient-nonpathological-question-tools" aria-label="Controles de lectura de la pregunta">
    <button id="p05ReadQuestionButton" type="button" class="btn patient-nonpathological-voice-button p05-voice-speak" aria-pressed="false" onclick="p05ReadCurrentQuestion()">
    <i data-feather="volume-2" aria-hidden="true"></i>
    <span>Escuchar</span>
    </button>
    <button id="p05StopQuestionButton" type="button" class="btn patient-nonpathological-voice-button p05-question-stop" hidden aria-hidden="true" tabindex="-1" onclick="p05StopVoice(true)">
    <i data-feather="square" aria-hidden="true"></i>
    <span>Detener</span>
    </button>
    <span id="p05VoiceStatus" class="visually-hidden" role="status" aria-live="polite"></span>
    </div>
    <div class="patient-nonpathological-generated">
    <?php foreach ($modulos as $idTema => $tema): ?>
    <div id="contePreguntas_<?= (int)$idTema; ?>"></div>
    <?php endforeach; ?>
    </div>
    </div>

    </section>

    <!---------- CONTENEDOR DE COMENTARIO ---------->
    <section id="comentarios-container" class="card patient-nonpathological-card" aria-labelledby="patient-nonpathological-comments-title">

    <div class="card-header patient-nonpathological-card-header">
    <div class="patient-nonpathological-card-heading">

    <div id="seccion101" data-autoplay="false" class="sectionQuestion patient-nonpathological-instruction-heading">
    <div>
    <p class="dashboard-eyebrow">COMENTARIOS</p>
    <h2 id="patient-nonpathological-comments-title">Comparte información adicional</h2>
    <p class="patient-nonpathological-instruction texto">Si tienes alguna otra información o comentario que desees compartir, por favor, indícalo.</p>
    </div>
    </div>
    </div>
    </div>

    <div class="card-body patient-nonpathological-card-body">
    <div id="mensaje-comentarios" data-p05-message class="patient-nonpathological-inline-message" role="alert" aria-live="polite"></div>
    <div id="conteComentario"></div>

    <div class="patient-nonpathological-comment-actions">
    <button class="btn btn-secondary" onclick="seccionPreguntas()"><i data-feather="chevron-left"></i> Preguntas</button>
    <?=$botonFinalizar?>
    </div>

    </div>
  
    </section>

    </section>

    </main>
    <div id="p05InstructionsModal" class="modal fade patient-nonpathological-modal patient-instructions-modal" tabindex="-1" role="dialog" aria-labelledby="p05InstructionsTitle" aria-describedby="p05InstructionsText" aria-modal="true" aria-hidden="true" style="display: none;">
    <div class="modal-dialog modal-dialog-centered patient-instructions-dialog">
    <div class="modal-content patient-nonpathological-modal-content patient-instructions-modal-content">
    <div class="modal-header patient-instructions-modal-header">
    <h2 id="p05InstructionsTitle" class="modal-title patient-instructions-modal-title">Antes de comenzar: Antecedentes Personales No Patológicos</h2>
    <?php $modalCloseConfig = ['onclick' => 'p05CloseInstructions()']; include __DIR__ . '/partials/modal-close.php'; unset($modalCloseConfig); ?>
    </div>
    <div class="modal-body patient-instructions-modal-body">
    <p id="p05InstructionsText" data-narration-instructions>En esta sección responderás preguntas sobre tus hábitos y antecedentes personales no patológicos. Lee cada pregunta, elige la respuesta disponible y completa los campos adicionales cuando aparezcan. Usa Anterior y Siguiente para avanzar o revisar tus respuestas. Al terminar, podrás compartir información adicional y guardar la sección.</p>
    <p id="p05VoicePrompt" class="patient-nonpathological-voice-prompt patient-instructions-voice-prompt" role="status" aria-live="polite">Para escuchar las instrucciones, presiona este botón.</p>
    <p class="patient-nonpathological-modal-note patient-instructions-modal-note">Tus respuestas se guardan mientras avanzas. Puedes cerrar esta ventana sin perder tu progreso.</p>
    </div>
    <div class="modal-footer patient-nonpathological-modal-actions patient-instructions-modal-actions">
    <?php $modalAudioConfig = ['group' => 'p05-instructions', 'listen_id' => 'p05ReadInstructionsButton', 'stop_id' => 'p05StopInstructionsButton', 'listen_class' => 'btn patient-nonpathological-secondary patient-instructions-secondary p05-voice-speak', 'stop_class' => 'btn patient-nonpathological-secondary patient-instructions-secondary', 'listen_attrs' => ['onclick' => 'p05ReadInstructions()'], 'stop_attrs' => ['onclick' => 'p05StopVoice(true)']]; include __DIR__ . '/partials/modal-audio-controls.php'; unset($modalAudioConfig); ?>
    <button type="button" class="btn btn-primary" onclick="p05CloseInstructions()">Entendido, comenzar</button>
    </div>
    </div>
    </div>
    </div>
    <!----- FOOTER ---------->
<?php include_once __DIR__ . '/../components/footer-doctor.php';?>
    </div>
    </div>


    <script src="<?=$audioStatusJsUrl;?>"></script>
    <script src="<?=RUTA_JS;?>patient-instructions-modal.js"></script>
    <script src="<?=RUTA_JS;?>patient-narration-highlight.js"></script>
    <script src="<?=RUTA_JS;?>voice-utilities.js"></script>
    <script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>    
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS;?>main.js"></script>
    
    </body>
    </html>
