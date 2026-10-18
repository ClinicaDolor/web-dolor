<?php 
use App\Models\PacienteModulosModelo;
use App\Models\AntecedenteFamiliarModel;

$model = new AntecedenteFamiliarModel();

$model2 = new PacienteModulosModelo();
$botonFinalizar = $model2->botonFinalizarModulo(2,$data['idPaciente'],$data['idRol']);
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$pageTitle = $data['title'] ?? 'Antecedentes familiares';
$patientName = $data['nombre'] ?? 'Paciente';
$versionedAsset = static function (string $baseUrl, string $assetPath, string $publicPath): string {
    $filesystemPath = realpath(__DIR__ . '/../../public/' . ltrim($publicPath, '/'));
    $version = $filesystemPath !== false && is_file($filesystemPath) ? filemtime($filesystemPath) : false;

    return $baseUrl . $assetPath . ($version === false ? '' : '?v=' . (int) $version);
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
    <title><?=$escape($pageTitle);?></title>
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?=$appCssUrl;?>">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=$loaderJsUrl;?>"></script>
 
    <style>
    /* Contenedor de preguntas visible inicialmente */
    body.patient-family-history-page #preguntas-container { display: block; }
    body.patient-family-history-page .pregunta-container { display: none; }
    body.patient-family-history-page .pregunta-container.active { display: block; }
    /* Sección de comentarios oculta inicialmente */
    body.patient-family-history-page #comentarios-container { display: none; }
    </style>

    <script>
    let p04LastFocusedElement = null;
    let p04PendingRequest = false;
    let p04ContentLoading = false;
    let p04SpeechMode = null;
    let p04SpeechRequestId = 0;
    let p04SpeechStarted = false;
    let p04VoiceFeedbackTimer = null;
    let p04VoicesChangeBound = false;
    let p04SaveMessageTimer = null;
    let p04FinalizationInFlight = false;
    let p04FinalizationCommentSaved = false;
    let p04FinalizationCommentContext = null;

    function p04StorageKey(name) {
    const main = document.getElementById('main');
    const idPaciente = main ? main.getAttribute('data-paciente') : 'unknown';
    return `p04:${idPaciente}:${name}`;
    }

    function p04SetMessage(message) {
    const element = document.getElementById('mensaje');
    if (p04SaveMessageTimer !== null) {
    window.clearTimeout(p04SaveMessageTimer);
    p04SaveMessageTimer = null;
    }
    if (element) element.classList.remove('p04-save-confirmation');
    if (element) element.textContent = message || '';
    }

    function p04ShowSavedMessage(message = 'Guardado.') {
    const element = document.getElementById('mensaje');
    if (!element) return;
    if (p04SaveMessageTimer !== null) window.clearTimeout(p04SaveMessageTimer);
    element.textContent = message;
    element.classList.add('p04-save-confirmation');
    p04SaveMessageTimer = window.setTimeout(() => {
    element.textContent = '';
    element.classList.remove('p04-save-confirmation');
    p04SaveMessageTimer = null;
    }, 1800);
    }

    function p04SetQuestionVoiceActive(active) {
    const speakButton = document.getElementById('p04ReadQuestionButton');
    const stopButton = document.getElementById('p04StopQuestionButton');
    if (speakButton) speakButton.setAttribute('aria-pressed', active ? 'true' : 'false');
    if (stopButton) {
    stopButton.hidden = !active;
    stopButton.setAttribute('aria-hidden', active ? 'false' : 'true');
    stopButton.tabIndex = active ? 0 : -1;
    }
    }

    function p04MarkVoiceNeedsAction(needsAction) {
    document.querySelectorAll('.p04-voice-speak, [data-audio-controls-group="p04-instructions"] [data-audio-control="listen"]').forEach(button => {
    button.classList.toggle('p04-voice-needs-action', needsAction);
    });
    }

    function p04ClearVoiceFeedbackTimer() {
    if (p04VoiceFeedbackTimer !== null) {
    window.clearTimeout(p04VoiceFeedbackTimer);
    p04VoiceFeedbackTimer = null;
    }
    }

    function p04StopVoice(announce = false) {
    const hadSpeech = p04SpeechStarted;
    const previousMode = p04SpeechMode;
    p04SpeechRequestId += 1;
    p04SpeechMode = null;
    p04SpeechStarted = false;
    p04ClearVoiceFeedbackTimer();
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    p04SetQuestionVoiceActive(false);
    p04MarkVoiceNeedsAction(false);
    if (previousMode === 'instructions') {
    PatientAudioControls.setState('p04-instructions', announce && hadSpeech ? 'stopped' : 'idle');
    }
    if (!announce || !hadSpeech) return;

    const status = document.getElementById('p04VoiceStatus');
    if (previousMode === 'question') {
    if (status) PatientAudioStatus.stopped(status);
    return;
    }
    const prompt = document.getElementById('p04VoicePrompt');
    if (prompt) PatientAudioStatus.stopped(prompt);
    }

    function p04BindVoicesChanged() {
    if (p04VoicesChangeBound || !('speechSynthesis' in window)) return;
    const synthesis = window.speechSynthesis;
    if (typeof synthesis.addEventListener !== 'function') return;
    synthesis.addEventListener('voiceschanged', () => {
    // La próxima lectura vuelve a consultar getVoices(); no se espera indefinidamente.
    }, { once: true });
    p04VoicesChangeBound = true;
    }

    function p04GetSpanishVoice() {
    if (!('speechSynthesis' in window)) return null;
    p04BindVoicesChanged();
    const voices = window.speechSynthesis.getVoices();
    return voices.find(voice => /^es-MX/i.test(voice.lang))
        || voices.find(voice => /^es(-|_)/i.test(voice.lang))
        || null;
    }

    function p04SpeakText(text, mode = 'instructions') {
    if (!('speechSynthesis' in window) || typeof SpeechSynthesisUtterance === 'undefined') {
    p04SetMessage('La lectura por voz no está disponible en este navegador. Puedes continuar con las instrucciones escritas.');
    const prompt = document.getElementById('p04VoicePrompt');
    if (prompt) prompt.textContent = 'La lectura por voz no está disponible. Puedes continuar con las instrucciones escritas.';
    p04SetQuestionVoiceActive(false);
    p04MarkVoiceNeedsAction(true);
    if (mode === 'instructions') PatientAudioControls.unavailable('p04-instructions');
    return false;
    }

    const cleanText = String(text || '').replace(/\s+/g, ' ').trim();
    if (!cleanText) {
    p04SetMessage('No hay texto disponible para leer en este momento.');
    return false;
    }

    p04StopVoice();
    p04BindVoicesChanged();
    const utterance = new SpeechSynthesisUtterance(cleanText);
    const voice = p04GetSpanishVoice();
    utterance.lang = voice ? voice.lang : 'es-MX';
    if (voice) utterance.voice = voice;
    utterance.rate = .88;
    utterance.pitch = 1;
    const requestId = p04SpeechRequestId;
    p04SpeechMode = mode;
    p04SpeechStarted = false;
    p04MarkVoiceNeedsAction(false);
    if (mode === 'instructions') {
    const prompt = document.getElementById('p04VoicePrompt');
    if (prompt) PatientAudioStatus.idle(prompt);
    PatientAudioControls.idle('p04-instructions');
    } else {
    const status = document.getElementById('p04VoiceStatus');
    if (status) status.textContent = 'Intentando iniciar la lectura de la pregunta.';
    }
    utterance.onstart = () => {
    if (requestId !== p04SpeechRequestId) return;
    p04SpeechStarted = true;
    p04ClearVoiceFeedbackTimer();
    p04MarkVoiceNeedsAction(false);
    p04SetQuestionVoiceActive(mode === 'question');
    const prompt = document.getElementById('p04VoicePrompt');
    if (mode === 'question') {
    const status = document.getElementById('p04VoiceStatus');
    if (status) status.textContent = 'Reproduciendo la pregunta activa. Puedes detenerla o repetirla.';
    } else if (prompt) {
    PatientAudioStatus.playing(prompt);
    PatientAudioControls.playing('p04-instructions');
    }
    };
    utterance.onend = () => {
    if (requestId !== p04SpeechRequestId) return;
    p04ClearVoiceFeedbackTimer();
    p04SpeechMode = null;
    p04SpeechStarted = false;
    p04SetQuestionVoiceActive(false);
    const status = document.getElementById('p04VoiceStatus');
    if (status) status.textContent = mode === 'question' ? 'Lectura de la pregunta finalizada.' : '';
    const prompt = document.getElementById('p04VoicePrompt');
    if (mode === 'instructions' && prompt) PatientAudioStatus.idle(prompt);
    if (mode === 'instructions') PatientAudioControls.ended('p04-instructions');
    };
    utterance.onerror = (event) => {
    if (requestId !== p04SpeechRequestId) return;
    p04ClearVoiceFeedbackTimer();
    p04SpeechMode = null;
    p04SpeechStarted = false;
    p04SetQuestionVoiceActive(false);
    p04MarkVoiceNeedsAction(true);
    const status = document.getElementById('p04VoiceStatus');
    if (status && mode === 'question') status.textContent = 'No se pudo iniciar la lectura. Presiona Escuchar para intentarlo de nuevo.';
    const prompt = document.getElementById('p04VoicePrompt');
    if (prompt && mode === 'instructions') prompt.textContent = 'No se pudo iniciar la lectura. Presiona Escuchar para intentarlo de nuevo.';
    if (mode === 'instructions') PatientAudioControls.idle('p04-instructions');
    };
    try {
    window.speechSynthesis.speak(utterance);
    p04VoiceFeedbackTimer = window.setTimeout(() => {
    p04VoiceFeedbackTimer = null;
    if (requestId !== p04SpeechRequestId || !('speechSynthesis' in window)) return;
    if (!window.speechSynthesis.speaking && !window.speechSynthesis.pending) {
    p04SpeechMode = null;
    p04SpeechStarted = false;
    p04SetQuestionVoiceActive(false);
    p04MarkVoiceNeedsAction(true);
    const status = document.getElementById('p04VoiceStatus');
    if (status && mode === 'question') status.textContent = 'Presiona Escuchar para oír la pregunta.';
    const prompt = document.getElementById('p04VoicePrompt');
    if (prompt && mode === 'instructions') prompt.textContent = 'Presiona Escuchar para oír las instrucciones.';
    }
    }, 350);
    return true;
    } catch (error) {
    p04SpeechMode = null;
    p04SetQuestionVoiceActive(false);
    p04MarkVoiceNeedsAction(true);
    const status = document.getElementById('p04VoiceStatus');
    if (status && mode === 'question') status.textContent = 'No se pudo iniciar la lectura. Presiona Escuchar para intentarlo de nuevo.';
    const prompt = document.getElementById('p04VoicePrompt');
    if (prompt && mode === 'instructions') prompt.textContent = 'No se pudo iniciar la lectura. Presiona Escuchar para intentarlo de nuevo.';
    return false;
    }
    }

    function p04ReadInstructions() {
    const instructions = document.getElementById('p04InstructionsText');
    p04SpeakText(instructions ? instructions.textContent : 'Lee o escucha cada pregunta y selecciona Sí o No.');
    }

    function p04ReadCurrentQuestion() {
    const modal = document.getElementById('p04InstructionsModal');
    if (modal && modal.getAttribute('aria-hidden') !== 'true') {
    p04SetMessage('Cierra las instrucciones para escuchar la pregunta.');
    return;
    }
    const active = document.querySelector('#contePreguntas .pregunta-container.active');
    if (!active) {
    p04SetMessage('Espera a que cargue la pregunta para utilizar la lectura por voz.');
    return;
    }

    const copy = active.cloneNode(true);
    copy.querySelectorAll('button, input, select, textarea, .p04-choice-card, .divTipoEnfermedad .text-center, .mt-3.d-flex, [aria-live]').forEach(control => control.remove());
    p04SpeakText(copy.textContent, 'question');
    }

    function p04ReadQuestionWhenReady() {
    const readActiveQuestion = () => {
    const modal = document.getElementById('p04InstructionsModal');
    if (modal && modal.getAttribute('aria-hidden') !== 'true') return;
    const active = document.querySelector('#contePreguntas .pregunta-container.active');
    if (active) p04ReadCurrentQuestion();
    };

    if (typeof queueMicrotask === 'function') {
    queueMicrotask(readActiveQuestion);
    } else if (typeof requestAnimationFrame === 'function') {
    requestAnimationFrame(readActiveQuestion);
    } else {
    window.setTimeout(readActiveQuestion, 0);
    }
    }

    function p04ReadQuestionAfterChange(previousIndex, nextIndex) {
    if (previousIndex === nextIndex) return;
    p04ReadQuestionWhenReady();
    }

    function p04OpenInstructions() {
    const modal = document.getElementById('p04InstructionsModal');
    if (!modal) return;
    if (modal.getAttribute('aria-hidden') !== 'true') {
    const currentControl = modal.querySelector('button');
    if (currentControl) currentControl.focus();
    return;
    }
    p04StopVoice();
    p04LastFocusedElement = PatientInstructionsModal.capture(modal, document.activeElement, '.patient-family-history-help');
    PatientInstructionsModal.open(modal, { opener: p04LastFocusedElement, focusSelector: '.th-modal-close' });
    const instructions = document.getElementById('p04InstructionsText');
    p04SpeakText(instructions ? instructions.textContent : 'Lee o escucha cada pregunta y selecciona Sí o No.');
    }

    function p04CloseInstructions() {
    const modal = document.getElementById('p04InstructionsModal');
    if (!modal) return;
    p04StopVoice();
    PatientInstructionsModal.close(modal, { returnFocus: p04LastFocusedElement, fallbackSelector: '.patient-family-history-help' });
    p04LastFocusedElement = null;
    p04ReadQuestionWhenReady();
    }

    document.addEventListener('keydown', function(event) {
    const modal = document.getElementById('p04InstructionsModal');
    if (!modal || modal.getAttribute('aria-hidden') === 'true') return;
    if (event.key === 'Escape') {
    event.preventDefault();
    p04CloseInstructions();
    return;
    }
    if (event.key === 'Tab') {
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
    }
    });

    document.addEventListener('click', function(event) {
    if (event.target && event.target.id === 'p04InstructionsModal') p04CloseInstructions();
    });

    window.addEventListener('pagehide', () => p04StopVoice());

    document.addEventListener("DOMContentLoaded", function() {
    const seccionActual = localStorage.getItem(p04StorageKey('seccionActual'));

    if (!seccionActual || seccionActual === "preguntas") {
    contenidoPreguntas();
    } else if (seccionActual === "comentarios") {
    finalizarPreguntas();
    }

    p04OpenInstructions();

    });

    function p04MarkConfirmedChoices() {
    document.querySelectorAll('#contePreguntas input.tipo-enfermedad[type="radio"]').forEach(input => {
    input.dataset.p04Confirmed = input.checked ? 'true' : 'false';
    });
    }

    function p04RestoreChoiceState(elemento) {
    if (!elemento) return;
    const group = Array.from(document.querySelectorAll('#contePreguntas input.tipo-enfermedad[type="radio"]'))
    .filter(input => input.name === elemento.name);
    group.forEach(input => {
    input.checked = input.dataset.p04Confirmed === 'true';
    });
    p04SetChoiceBusy(elemento, false);
    }

    function p04SetChoiceBusy(elemento, busy) {
    const question = elemento ? elemento.closest('.pregunta-container') : null;
    if (!question) return;
    question.setAttribute('aria-busy', busy ? 'true' : 'false');
    question.querySelectorAll('input.tipo-enfermedad[type="radio"]').forEach(input => {
    input.disabled = busy;
    });
    }

    function p04QuestionIsReadyToAdvance(question) {
    if (!question) return false;
    const choice = question.querySelector('input.tipo-enfermedad[type="radio"]:checked');
    if (!choice) return !question.querySelector('input.tipo-enfermedad[type="radio"]');
    if (choice.value === 'No') return true;

    const typeOptions = Array.from(question.querySelectorAll('.detalle-enfermedad:not([disabled])'));
    const detailFields = Array.from(question.querySelectorAll('.especificar-enfermedad:not([disabled])'));
    if (typeOptions.length && !typeOptions.some(input => input.checked)) return false;
    if (detailFields.length && detailFields.some(field => !String(field.value || '').trim())) return false;
    return true;
    }

    function p04AdvanceAfterConfirmedSave(question) {
    if (!p04QuestionIsReadyToAdvance(question)) {
    p04SetMessage('Completa la información adicional antes de continuar.');
    return;
    }
    siguientePregunta(true);
    }

    function p04HandleChoiceSelection(elemento) {
    if (!elemento || elemento.disabled || !elemento.checked || !elemento.isConnected) return;
    const question = elemento.closest('.pregunta-container');
    if (!question) return;
    const main = document.getElementById('main');
    const idRol = question.getAttribute('data-rol') || (main ? main.getAttribute('data-rol') : '');
    const idEnfermedad = Number(question.getAttribute('data-id') || 0);
    const enfermedad = question.getAttribute('data-p04-disease') || '';
    if ((!idEnfermedad && !enfermedad) || !idRol) {
    p04SetMessage('No se pudo identificar la respuesta. Intenta nuevamente.');
    return;
    }

    if (elemento.dataset.p04Confirmed === 'true') {
    if (p04QuestionIsReadyToAdvance(question)) {
    p04SetMessage('');
    p04AdvanceAfterConfirmedSave(question);
    } else {
    p04SetMessage('Completa la información adicional antes de continuar.');
    }
    return;
    }

    editarEnfermedad(idEnfermedad, elemento, 2, idRol, 1);
    }

    document.addEventListener('click', event => {
    const target = event.target instanceof Element ? event.target : null;
    const elemento = target
    ? target.closest('#contePreguntas input.tipo-enfermedad[type="radio"]')
    : null;
    if (!elemento || elemento.disabled || elemento.dataset.p04ClickQueued === 'true') return;
    elemento.dataset.p04ClickQueued = 'true';
    window.setTimeout(() => {
    delete elemento.dataset.p04ClickQueued;
    p04HandleChoiceSelection(elemento);
    }, 0);
    });

    function p04ErrorMessage(error) {
    const status = Number(error && error.status) || 0;
    if (status === 401) return 'Tu sesión ya no es válida. Inicia sesión nuevamente.';
    if (status === 403) return 'No tienes autorización para actualizar esta respuesta.';
    if (status === 404) return 'No se encontró el recurso solicitado.';
    if (status === 422) return 'Revisa la respuesta e inténtalo nuevamente.';
    if (error && error.message === 'Failed to fetch') return 'No se pudo conectar con el servicio. Intenta nuevamente.';
    return 'No se pudo guardar la respuesta. Intenta nuevamente.';
    }
    
    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas(idValor = 0) {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');
    p04ContentLoading = true;

    fetch(`/buscar/contenido-preguntas-modulo-2/${idPaciente}/${idRol}`)
    .then(response => {
    if (!response.ok) throw new Error('contenido-preguntas');
    return response.text();
    })
    .then(data => {
    document.getElementById('contePreguntas').innerHTML = data;
    if (window.feather) feather.replace();
    p04MarkConfirmedChoices();
    const instructionsModal = document.getElementById('p04InstructionsModal');
    if (!instructionsModal || instructionsModal.getAttribute('aria-hidden') === 'true') p04StopVoice();
            
    cargarProgreso(idValor);
    if (!instructionsModal || instructionsModal.getAttribute('aria-hidden') === 'true') p04ReadQuestionWhenReady();
    })
    .catch(() => p04SetMessage('No se pudieron cargar las preguntas. Intenta nuevamente.'))
    .finally(() => {
    p04ContentLoading = false;
    document.querySelectorAll('#contePreguntas input.tipo-enfermedad[type="radio"]')
    .forEach(input => input.disabled = false);
    document.querySelectorAll('#contePreguntas .pregunta-container')
    .forEach(question => question.setAttribute('aria-busy', 'false'));
    });
    }
    
    // ---------- CONTENIDO DEL COMENTARIO ----------
    function contenidoComentario() {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');
    const idModulo = 2;

    fetch(`/buscar/contenido-comentarios-modulos/${idPaciente}/${idRol}/${idModulo}`)
    .then(response => {
    if (!response.ok) throw new Error('contenido-comentarios');
    return response.text();
    })
    .then(data => {

    document.getElementById('conteComentario').innerHTML = data;
    if (window.feather) feather.replace();

    })
    .catch(() => p04SetMessage('No se pudieron cargar los comentarios. Intenta nuevamente.'));
    }

    function guardarProgreso(index) {
    localStorage.setItem(p04StorageKey('preguntaActual'), index);
    }

    function cargarProgreso(idValor) {
    let indexGuardado = localStorage.getItem(p04StorageKey('preguntaActual'));
    indexGuardado = indexGuardado ? parseInt(indexGuardado) : 0;

    const preguntas = document.querySelectorAll('.pregunta-container');
    const botonAgregar = document.getElementById('btnAgregarEnfermedad');
    if (!preguntas.length) {
    if (botonAgregar) botonAgregar.style.display = 'none';
    return;
    }
    
    // Calcular el nuevo índice dentro de los límites permitidos
    let nuevoIndex = indexGuardado + idValor;
    if (nuevoIndex < 0) nuevoIndex = 0; // Evita ir antes de la primera pregunta
    if (nuevoIndex >= preguntas.length) nuevoIndex = preguntas.length - 1; // Evita ir después de la última

    // Remover la clase 'active' de todas las preguntas
    preguntas.forEach(pregunta => pregunta.classList.remove('active'));

    // Activar la nueva pregunta
    if (preguntas[nuevoIndex]) preguntas[nuevoIndex].classList.add('active');

    // Mostrar el botón si es la última pregunta
    if (botonAgregar) {
    botonAgregar.style.display = nuevoIndex === preguntas.length - 1 ? 'block' : 'none';
    }

    // Guardar el nuevo progreso
    guardarProgreso(nuevoIndex);
    }

    function siguientePregunta(confirmedSave = false) {
    const preguntas = document.querySelectorAll('.pregunta-container');
    if (!preguntas.length) return;
    if (!confirmedSave && (p04PendingRequest || p04ContentLoading)) {
    p04SetMessage('Espera a que se guarde la respuesta antes de continuar.');
    return;
    }
    const activeQuestion = document.querySelector('#contePreguntas .pregunta-container.active');
    if (!confirmedSave && activeQuestion && !p04QuestionIsReadyToAdvance(activeQuestion)) {
    p04SetMessage('Completa la información adicional antes de continuar.');
    return;
    }
    let activeIndex = -1;

    p04StopVoice();
    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);

    preguntas.forEach((pregunta, index) => {
    if (pregunta.classList.contains('active')) {
    activeIndex = index;
    pregunta.classList.remove('active');
    }
    });

    if (activeIndex >= 0) {
    const nuevoIndice = Math.min(activeIndex + 1, preguntas.length - 1);
    preguntas[nuevoIndice].classList.add('active');
    guardarProgreso(nuevoIndice); // Guarda el progreso al avanzar
    p04ReadQuestionAfterChange(activeIndex, nuevoIndice);
    }

    // Mostrar el botón solo si se está en la última pregunta
    const botonAgregar = document.getElementById('btnAgregarEnfermedad');
    if (!botonAgregar) return;
    if (activeIndex + 1 >= preguntas.length - 1) {
    botonAgregar.style.display = 'block';
    } else {
    botonAgregar.style.display = 'none';
    }
    }

    function anteriorPregunta() {
    const preguntas = document.querySelectorAll('.pregunta-container');
    if (!preguntas.length) return;
    let activeIndex = -1;

    p04StopVoice();
    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);

    preguntas.forEach((pregunta, index) => {
    if (pregunta.classList.contains('active')) {
    activeIndex = index;
    pregunta.classList.remove('active');
    }
    });

    if (activeIndex > 0) {
    const nuevoIndice = activeIndex - 1;
    preguntas[nuevoIndice].classList.add('active');
    guardarProgreso(nuevoIndice); // Guarda el progreso al retroceder
    p04ReadQuestionAfterChange(activeIndex, nuevoIndice);
    }

    // Ocultar el botón si no estamos en la última pregunta
    const botonAgregar = document.getElementById('btnAgregarEnfermedad');
    if (botonAgregar) botonAgregar.style.display = 'none';
    }

    //---------- MUESTRA LA SECCION DE PREGUNTAS Y COMENTARIOS ----------
    function seccionPreguntas() {
    p04StopVoice();
    document.getElementById("preguntas-container").style.display = "block";
    document.getElementById("comentarios-container").style.display = "none";
    localStorage.setItem(p04StorageKey('seccionActual'), "preguntas");
    contenidoPreguntas();
    }

    function finalizarPreguntas() {
    p04StopVoice();
    document.getElementById("preguntas-container").style.display = "none";
    document.getElementById("comentarios-container").style.display = "block";
    localStorage.setItem(p04StorageKey('seccionActual'), "comentarios");
    contenidoComentario();
    }

    //---------- CONTROL SERVER ----------
    function gestionarEnfermedadPaciente(url, parametros, callback, idUpdate = 0) {
    if (p04PendingRequest) {
    p04SetMessage('Espera a que termine el guardado actual.');
    return Promise.resolve(false);
    }
    p04PendingRequest = true;
    p04SetMessage(idUpdate == 1 ? 'Guardando respuesta...' : '');
    if(idUpdate == 1){
    $(".LoaderPage").show();
    }
    return fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(parametros)
    }).then(async res => {
    let data;
    try {
    data = await res.json();
    } catch (error) {
    const jsonError = new Error('invalid-json');
    jsonError.status = res.status;
    throw jsonError;
    }
    if (!res.ok || !data || !data.resultado) {
    const responseError = new Error('request-failed');
    responseError.status = res.status;
    throw responseError;
    }
    if (typeof callback === 'function') callback(data);
    return true;
    }).catch(error => {
    p04SetMessage(p04ErrorMessage(error));
    return false;
    })
    .finally(() => {
    p04PendingRequest = false;
    if(idUpdate == 1) $(".LoaderPage").fadeOut(250);
    });
    }

    //---------- AGREGAR ENFERMEDAD PACIENTE ----------
    function agregarEnfermedadPaciente(idPaciente, idRol) {
    gestionarEnfermedadPaciente(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/agregar-enfermedad-antecedentes`, { idPaciente, idRol }, () => contenidoPreguntas(1), 1);
    }

    //---------- ELIMINAR ENFERMEDADES DEL PACIENTE ----------
    function eliminarEnfermedadPaciente(idEnfermedad, idRol) {
    gestionarEnfermedadPaciente(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/eliminar-enfermedad-antecedentes`, { idEnfermedad, idRol }, () => contenidoPreguntas(1), 1);
    }

    //---------- EDITAR ENFERMEDADES DEL PACIENTE ----------
    function editarEnfermedad(idEnfermedad, elemento, parametro, idRol, idUpdate = 0) {
    if (!elemento || p04PendingRequest || p04ContentLoading) {
    if (p04PendingRequest || p04ContentLoading) p04SetMessage('Espera a que termine la actualización actual.');
    return Promise.resolve(false);
    }
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv ? usuarioDiv.getAttribute('data-paciente') : '';
    const question = elemento.closest('.pregunta-container');
    const isChoice = parametro === 2 && elemento.matches('input[type="radio"]');
    const selectedValue = elemento.value;
    if (!idPaciente) {
    p04SetMessage('No se pudo identificar el paciente de la sesión.');
    return Promise.resolve(false);
    }
    if (isChoice) p04SetChoiceBusy(elemento, true);

    const request = gestionarEnfermedadPaciente(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-enfermedad-antecedentes`, {
        idPaciente, idEnfermedad, enfermedad, detalle: selectedValue, edicion: parametro, idRol
    }, () => {
    if (isChoice) {
    p04MarkConfirmedChoices();
    if (selectedValue === 'No') {
    p04AdvanceAfterConfirmedSave(question);
    } else {
    p04ShowSavedMessage('Guardado. Completa la información adicional.');
    contenidoPreguntas(0);
    }
    } else if ((parametro === 3 || parametro === 4) && question && p04QuestionIsReadyToAdvance(question)) {
    p04AdvanceAfterConfirmedSave(question);
    } else {
    p04ShowSavedMessage('Guardado.');
    contenidoPreguntas(0);
    }
    }, idUpdate);

    return request.then(success => {
    if (!success && isChoice) p04RestoreChoiceState(elemento);
    return success;
    }).finally(() => {
    if (!p04ContentLoading && isChoice) p04SetChoiceBusy(elemento, false);
    });
    }
 
    //---------- FLUJO DE COMENTARIO Y FINALIZACION DEL MODULO ----------
    function p04RequestJson(url, parametros) {
    return fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(parametros)
    }).then(async response => {
    let data;
    try {
    data = await response.json();
    } catch (error) {
    const jsonError = new Error('invalid-json');
    jsonError.status = response.status;
    throw jsonError;
    }
    if (!response.ok || !data || !data.resultado) {
    const responseError = new Error('request-failed');
    responseError.status = response.status;
    throw responseError;
    }
    return data;
    });
    }

    function p04SetFinalizationBusy(busy) {
    const button = document.querySelector('#comentarios-container .patient-family-history-comment-actions .btn-success');
    if (!button) return;
    button.disabled = busy;
    button.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function p04SameFinalizationContext(idModulo, idPaciente) {
    return p04FinalizationCommentContext !== null
    && p04FinalizationCommentContext.idModulo === String(idModulo)
    && p04FinalizationCommentContext.idPaciente === String(idPaciente);
    }

    //---------- AGREGAR COMENTARIO Y FINALIZAR MODULO DEL PACIENTE ----------
    async function agregarComentario(idModulo, idPaciente, idRol) {
    if (p04FinalizationInFlight || p04PendingRequest) {
    p04SetMessage('Espera a que termine el guardado actual.');
    return false;
    }

    const comentarioElement = document.getElementById('comentarioModulos');
    const comentario = comentarioElement ? comentarioElement.value : '';
    const context = { idModulo: String(idModulo), idPaciente: String(idPaciente) };
    const commentAlreadyStored = comentarioElement && comentarioElement.disabled;
    const commentWasSaved = p04FinalizationCommentSaved && p04SameFinalizationContext(idModulo, idPaciente);

    p04FinalizationInFlight = true;
    p04PendingRequest = true;
    p04FinalizationCommentContext = context;
    p04SetFinalizationBusy(true);
    p04SetMessage('Guardando comentario...');
    $(".LoaderPage").show();

    try {
    if (!commentAlreadyStored && !commentWasSaved) {
    await p04RequestJson(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/agregar-comentario-modulo`, {
    idModulo, idPaciente, comentarioModulos: comentario, idRol
    });
    p04FinalizationCommentSaved = true;
    }

    p04SetMessage('Finalizando módulo...');
    const finalizationResponse = await FinalizarModuloPaciente(idModulo, idPaciente);
    const nextUrl = finalizationResponse && typeof finalizationResponse.next_url === 'string'
    ? finalizationResponse.next_url
    : '/historia-clinica';
    window.location.href = nextUrl;
    return true;
    } catch (error) {
    p04SetMessage(p04ErrorMessage(error));
    return false;
    } finally {
    p04PendingRequest = false;
    p04FinalizationInFlight = false;
    p04SetFinalizationBusy(false);
    $(".LoaderPage").fadeOut(250);
    }
    }

    //---------- FINALIZAR MODULO DEL PACIENTE ----------
    function FinalizarModuloPaciente(idModulo, idPaciente) {
    return p04RequestJson('/historia-clinica/paciente/finalizar-modulo-paciente', { idModulo, idPaciente });
    }
    </script>
    </head>

<body class="app-shell-body app-shell-patient patient-clinical-form patient-family-history-page">
    <div class="LoaderPage"></div>
    <div id="app">
        
    <!---------- SIDEBAR ---------->
    <?=$data['sidebar'];?>
    
    <div id="main" data-rol="<?=$escape($data['idRol']);?>" data-paciente="<?=$escape($data['idPaciente']);?>">
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
    <main class="main-content container-fluid patient-family-history-content">

    <header class="page-title portal-page-title patient-family-history-hero mb-4">
    <div class="patient-family-history-hero-copy">
    <p class="dashboard-eyebrow">MI HISTORIA CLÍNICA</p>
    <h1>Antecedentes familiares</h1>
    <p>Responde las preguntas sobre los antecedentes de salud de tus familiares.</p>
    <span class="patient-family-history-context">Paciente: <?=$escape($patientName);?></span>
    </div>
    <div class="patient-family-history-hero-actions">
    <button type="button" id="p04HelpButton" class="btn patient-family-history-help" aria-label="¿Cómo llenar esta sección?" aria-haspopup="dialog" aria-controls="p04InstructionsModal" onclick="p04OpenInstructions()">
    <i data-feather="help-circle" aria-hidden="true"></i>
    <span>¿Cómo llenar esta sección?</span>
    </button>
    <a class="btn btn-light patient-family-history-back" href="<?=SERVIDOR;?>historia-clinica">
    <i data-feather="arrow-left" aria-hidden="true"></i>
    <span>Volver a mi Historia Clínica</span>
    </a>
    </header>

    <div id="mensaje" class="patient-family-history-message" role="alert" aria-live="polite"></div>

    <section class="section patient-family-history-workspace" aria-label="Antecedentes familiares">

    <!---------- CONTENEDOR DE PREGUNTAS ---------->
    <section id="preguntas-container" class="card patient-family-history-card" aria-labelledby="patient-family-history-questions-title">
    <div class="card-header patient-family-history-card-header">
    <div class="patient-family-history-card-heading">

    <div id="seccion1" data-autoplay="true" class="sectionQuestion patient-family-history-audio-heading">

    <div>
    <p class="dashboard-eyebrow">ANTECEDENTES FAMILIARES</p>
    <h2 id="patient-family-history-questions-title">Responde una pregunta a la vez</h2>
    <p class="patient-family-history-instruction texto">A continuación, te preguntaremos si existen antecedentes familiares de alguna de las siguientes enfermedades. Por favor, menciona si alguno de tus familiares cercanos, como abuelos, padres, hermanos, etc., ha padecido alguna de ellas.</p>
    </div>
    </div>

    <button id="btnAgregarEnfermedad" type="button" onclick="agregarEnfermedadPaciente(<?=$data['idPaciente'];?>,'<?=$data['idRol'];?>')" class="btn btn-success patient-family-history-add" style="display: none;" aria-label="Agregar otra enfermedad">
    <i data-feather="plus" width="20" aria-hidden="true"></i>
    <span>Agregar otra enfermedad</span>
    </button>
    </div>
    </div>

    <div class="card-body patient-family-history-card-body">
    <div class="patient-family-history-question-tools">
    <button id="p04ReadQuestionButton" type="button" class="btn patient-family-history-voice-button p04-voice-speak" aria-pressed="false" onclick="p04ReadCurrentQuestion()">
    <i data-feather="volume-2" aria-hidden="true"></i>
    <span>Escuchar</span>
    </button>
    <button id="p04StopQuestionButton" type="button" class="btn patient-family-history-voice-button p04-question-stop" hidden aria-hidden="true" tabindex="-1" onclick="p04StopVoice(true)">
    <i data-feather="square" aria-hidden="true"></i>
    <span>Detener</span>
    </button>
    <span id="p04VoiceStatus" class="visually-hidden" role="status" aria-live="polite"></span>
    </div>
    <div id="contePreguntas" class="patient-family-history-generated"></div>
    </div>
    </section>


    <!---------- CONTENEDOR DE COMENTARIO ---------->
    <section id="comentarios-container" class="card patient-family-history-card" aria-labelledby="patient-family-history-comments-title">

    <div class="card-header patient-family-history-card-header">
    <div class="patient-family-history-card-heading">

    <div id="seccion2" data-autoplay="true" class="sectionQuestion patient-family-history-audio-heading">
      
    <div>
    <p class="dashboard-eyebrow">COMENTARIOS</p>
    <h2 id="patient-family-history-comments-title">Comparte información adicional</h2>
    <p class="patient-family-history-instruction texto">Si tienes alguna otra información o comentario que desees compartir, por favor, indícalo.</p>
    </div>
    </div>
    </div>
    </div>

    <div class="card-body patient-family-history-card-body">
    <div id="conteComentario" class="patient-family-history-generated"></div>

    <div class="patient-family-history-comment-actions">
    <button type="button" class="btn btn-secondary patient-family-history-secondary" onclick="seccionPreguntas()"><i data-feather="chevron-left" aria-hidden="true"></i> Preguntas</button>
    <?=$botonFinalizar?>
    </div>

    </div>
    </section>

    </section>

    </main>
    <div id="p04InstructionsModal" class="modal fade patient-family-history-modal patient-instructions-modal" tabindex="-1" role="dialog" aria-labelledby="p04InstructionsTitle" aria-describedby="p04InstructionsText" aria-modal="true" aria-hidden="true" style="display: none;">
    <div class="modal-dialog modal-dialog-centered patient-instructions-dialog">
    <div class="modal-content patient-family-history-modal-content patient-instructions-modal-content">
    <div class="modal-header patient-instructions-modal-header">
    <h2 id="p04InstructionsTitle" class="modal-title patient-instructions-modal-title">Antes de comenzar: Antecedentes familiares</h2>
    <?php $modalCloseConfig = ['onclick' => 'p04CloseInstructions()']; include __DIR__ . '/partials/modal-close.php'; unset($modalCloseConfig); ?>
    </div>
    <div class="modal-body patient-instructions-modal-body">
    <p id="p04InstructionsText" data-narration-instructions>En esta sección te preguntaremos si tus familiares han padecido ciertas enfermedades. Lee o escucha cada pregunta y selecciona Sí o No. Si respondes Sí, indica qué familiar ha padecido esa enfermedad. Algunas respuestas pueden solicitar información adicional. Puedes regresar a una pregunta anterior si necesitas revisar tus respuestas. Al terminar, podrás añadir comentarios y finalizar la sección.</p>
    <p id="p04VoicePrompt" class="patient-family-history-voice-prompt patient-instructions-voice-prompt" role="status" aria-live="polite">Para escuchar las instrucciones, presiona este botón.</p>
    <p class="patient-family-history-modal-note patient-instructions-modal-note">Tus respuestas se guardan mientras avanzas. Puedes cerrar esta ventana sin perder tu progreso.</p>
    </div>
    <div class="modal-footer patient-family-history-modal-actions patient-instructions-modal-actions">
    <?php $modalAudioConfig = ['group' => 'p04-instructions', 'listen_id' => 'p04ReadInstructionsButton', 'stop_id' => 'p04StopInstructionsButton', 'listen_class' => 'btn patient-family-history-secondary patient-instructions-secondary p04-voice-speak', 'stop_class' => 'btn patient-family-history-secondary patient-instructions-secondary', 'listen_attrs' => ['onclick' => 'p04ReadInstructions()'], 'stop_attrs' => ['onclick' => 'p04StopVoice(true)']]; include __DIR__ . '/partials/modal-audio-controls.php'; unset($modalAudioConfig); ?>
    <button type="button" class="btn btn-primary" onclick="p04CloseInstructions()">Entendido, comenzar</button>
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
    <script src="<?=$voiceUtilitiesJsUrl;?>"></script>
    <script src="<?=$featherJsUrl;?>"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=$appJsUrl;?>"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=$mainJsUrl;?>"></script>
    </body>
    </html>
