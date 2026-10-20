<?php 
use App\Models\AntecedentesPatologicosModel;

$model = new AntecedentesPatologicosModel();
    
$modulos = $model->obtenerModulosM5();
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$pageTitle = $data['title'] ?? 'Antecedentes Personales Patológicos';
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
    <title><?=$escape($pageTitle);?></title>
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?=$appCssUrl;?>">
    <script src="<?=$loaderJsUrl;?>"></script>

    <style>
    /* Contenedor de preguntas visible inicialmente */
    body.patient-pathological-page #preguntas-container { display: block; }
    body.patient-pathological-page .pregunta-container { display: none; }
    body.patient-pathological-page .pregunta-container.active { display: block; }
    /* Sección de comentarios oculta inicialmente */
    body.patient-pathological-page #enfermedades-container { display: none; }
    body.patient-pathological-page .enfermedad-container { display: none; }
    body.patient-pathological-page .enfermedad-container.active { display: block; }
    </style>

    <script>
    // Pasar los datos de PHP a JavaScript
    const modulos = <?=json_encode($modulos)?>;

    // Crear el arreglo de temas (adaptado al formato que usas en el frontend)
    const temas = Object.keys(modulos).map(id => {
    return { id: id, nombre: modulos[id] };
    });

    
    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas(idCuestionario, idValor = 0) {
    // Limpia las preguntas anteriores, si existen
    document.querySelectorAll('.pregunta-container').forEach(p => p.remove());

    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    fetch(`/buscar/contenido-preguntasV1-modulo-5/${idPaciente}/${idRol}/${idCuestionario}`)
    .then(response => response.text())
    .then(data => {
    // Insertar el contenido en el contenedor específico del módulo
    document.getElementById('contePreguntas_' + idCuestionario).innerHTML = data;
    feather.replace();

    // Reactivar el IntersectionObserver para las nuevas secciones (si lo utilizas)
    document.querySelectorAll('.sectionQuestion').forEach(section => {
    observer.observe(section);
    });

    // Cargar el progreso para el módulo actual
    cargarProgreso(idCuestionario, idValor);
    });
    }

    // ---------- GUARDAR Y CARGAR PROGRESO ----------
    function guardarProgreso(index, idCuestionario) {
    localStorage.setItem("preguntaActual_" + idCuestionario, index);
    }

    function cargarProgreso(idCuestionario, idValor = 0) {
    let indexGuardado = localStorage.getItem("preguntaActual_" + idCuestionario);
    indexGuardado = indexGuardado ? parseInt(indexGuardado) : idValor;
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
    function siguientePregunta(idTema) {
    const preguntas = document.querySelectorAll('.pregunta-container');
    let activeIndex = -1;

    // Mostrar y ocultar el loader (suponiendo que usas jQuery para ello)
    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);

    preguntas.forEach((pregunta, index) => {
    if (pregunta.classList.contains('active')) {
    activeIndex = index;
    pregunta.classList.remove('active');
    }
    });

    if (activeIndex === -1 && preguntas.length > 0) {
    activeIndex = cargarProgreso(idTema);
    }

    // Si se está en la última pregunta del módulo...
    if (activeIndex >= preguntas.length - 1) {
    // Buscar el módulo actual en el arreglo de temas
    let seccionActual = localStorage.getItem("seccionActual") || temas[0].nombre;
    let currentIndex = temas.findIndex(t => t.nombre === seccionActual);

    // Si existe un siguiente módulo, preparar el botón para dirigir a esa sección
    if (currentIndex < temas.length - 1) {
    let nuevoTema = temas[currentIndex + 1];
    // Se coloca el botón en el contenedor de botones de la última pregunta
    let lastPregunta = preguntas[preguntas.length - 1];
    let botonesContainer = lastPregunta.querySelector('.mt-3.d-flex.justify-content-between');
    botonesContainer.innerHTML = `<button class="btn btn-success" onclick="irASiguienteSeccion('${nuevoTema.nombre}', ${nuevoTema.id})">${nuevoTema.nombre} <i data-feather="message-circle"></i></button>`;
    return;
    } 

    } else {
    // Avanzar a la siguiente pregunta dentro del mismo módulo
    const nuevoIndice = activeIndex + 1;
    preguntas[nuevoIndice].classList.add('active');
    guardarProgreso(nuevoIndice, idTema);
    }
    }

    //---------- MUESTRA LA PREGUNTA ANTERIOR ----------
    function anteriorPregunta(idTema) {
    const preguntas = document.querySelectorAll('.pregunta-container');
    let activeIndex = -1;

    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);

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
    let seccionActual = localStorage.getItem("seccionActual") || temas[0].nombre;
    let currentIndex = temas.findIndex(t => t.nombre === seccionActual);
    if (currentIndex > 0) {
    let nuevoTema = temas[currentIndex - 1];
    localStorage.setItem("seccionActual", nuevoTema.nombre);
    // Se asume que al cargar el módulo anterior se muestra la última pregunta
    contenidoPreguntas(nuevoTema.id, true);
    return;
    }
    } else {
    // Retroceder a la pregunta anterior dentro del mismo módulo
    const nuevoIndice = activeIndex - 1;
    preguntas[nuevoIndice].classList.add('active');
    guardarProgreso(nuevoIndice, idTema);
    }
    }

    //---------- IR A LA SIGUIENTE SECCION ----------
    function irASiguienteSeccion(nombreTema, idTema) {
    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);
    localStorage.setItem("seccionActual", nombreTema);
    contenidoPreguntas(idTema);
    }


    //---------- MUESTRA LA SECCION DE PREGUNTAS Y COMENTARIOS ----------
    function seccionPreguntas() {
    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);
    document.getElementById("preguntas-container").style.display = "block";
    document.getElementById("enfermedades-container").style.display = "none";
    localStorage.setItem("seccionActual", "Alergias (Medicamentos)");
    contenidoPreguntas(4);
    }


    // ------------------------------ CONTENIDO PREGUNTAS V2 ------------------------------
    function contenidoEnfermedades(idValor = 0) {
    // Limpia las preguntas anteriores, si existen
    document.querySelectorAll('.enfermedad-container').forEach(p => p.remove());
    
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    fetch(`/buscar/contenido-preguntasV2-modulo-5/${idPaciente}/${idRol}`)
    .then(response => response.text())
    .then(data => {
    document.getElementById('contePreguntasV2').innerHTML = data;
    feather.replace();
            
    // Reactivar el IntersectionObserver para las nuevas secciones (si lo utilizas)
    document.querySelectorAll('.sectionQuestion').forEach(section => {
    observer.observe(section);
    });

    cargarProgresoV2(idValor);
    });
    }

    // ---------- VER PREGUNTAS V2 ----------
    function seccionEnfermedades() {
    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);
    document.getElementById("preguntas-container").style.display = "none";
    document.getElementById("enfermedades-container").style.display = "block";
    localStorage.setItem("seccionActual", "enfermedades");
    contenidoEnfermedades();
    }

 
    function guardarProgresoV2(index) {
    localStorage.setItem("preguntaActualV2", index);
    }

    function cargarProgresoV2(idValor) {
    let indexGuardado = localStorage.getItem("preguntaActualV2");
    indexGuardado = indexGuardado ? parseInt(indexGuardado) : 0;

    const preguntas = document.querySelectorAll('.enfermedad-container');
    
    // Calcular el nuevo índice dentro de los límites permitidos
    let nuevoIndex = indexGuardado + idValor;
    if (nuevoIndex < 0) nuevoIndex = 0; // Evita ir antes de la primera pregunta
    if (nuevoIndex > preguntas.length) nuevoIndex = preguntas.length - 1; // Evita ir después de la última

    // Remover la clase 'active' de todas las preguntas
    preguntas.forEach(pregunta => pregunta.classList.remove('active'));

    // Activar la nueva pregunta
    preguntas[nuevoIndex].classList.add('active');

    // Guardar el nuevo progreso
    guardarProgresoV2(nuevoIndex);
    }

    function siguientePreguntaV2() {
    const preguntas = document.querySelectorAll('.enfermedad-container');
    let activeIndex = -1;

    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);

    preguntas.forEach((pregunta, index) => {
    if (pregunta.classList.contains('active')) {
    activeIndex = index;
    pregunta.classList.remove('active');
    }
    });

    if (activeIndex < preguntas.length - 1) {
    const nuevoIndice = activeIndex + 1;
    preguntas[nuevoIndice].classList.add('active');
    guardarProgresoV2(nuevoIndice); // Guarda el progreso al avanzar
    }

    }

    function anteriorPreguntaV2() {
    const preguntas = document.querySelectorAll('.enfermedad-container');
    let activeIndex = -1;

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
    guardarProgresoV2(nuevoIndice); // Guarda el progreso al retroceder
    }

    }
    

    //---------- CONTROL SERVER ----------
    function gestionarAntecedentesPatologicos(url, parametros, callback, idUpdate = 0) {
    if(idUpdate == 1){
    $(".LoaderPage").show();
    }
    fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(parametros)
    }).then(res => res.json()).then(data => {
    if(idUpdate == 1){
    $(".LoaderPage").fadeOut(1000);
    }
    if (data.resultado) callback();
    else document.getElementById('mensaje').textContent = 'Error: ' + data.mensaje;
    });
    }


    //---------- EDITAR ENFERMEDADES DEL PACIENTE ----------
    function respuestaPreguntaSelect (idPaciente, idRespuesta, elemento, idTema, idTipo, idRol, idUpdate = 0) {
    gestionarAntecedentesPatologicos(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-cuestionarioV1-modulo5`, {
    idPaciente,
    idRespuesta, 
    idTema,
    idTipo,
    detalle: elemento.value,
    idRol
    }, 
    () => contenidoPreguntas(idTema),
    idUpdate);
    }


    //---------- EDITAR ENFERMEDADES DEL PACIENTE ----------
    function editarEnfermedadV2(idEnfermedad, elemento, parametro, idRol, idUpdate = 0) {
    gestionarAntecedentesPatologicos(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-cuestionarioV2-modulo5`, {
    idEnfermedad, detalle: elemento.value, edicion: parametro, idRol
    }, () => contenidoEnfermedades(0),
    idUpdate);
    }

    //---------- FINALIZAR MODULO DEL PACIENTE----------
    function finalizarModuloPAC(idModulo, idPaciente) {
    gestionarAntecedentesPatologicos('/historia-clinica/paciente/finalizar-modulo-paciente', { idModulo, idPaciente }, () => window.location.href = '/historia-clinica');
    }

    /* P07-COMPLETE-01: navegación delegada y guardado confirmado para V1/V2. */
    const p07Themes = Object.keys(modulos).map(id => ({ id: String(id), nombre: String(modulos[id]) }));
    const p07ModuleId = 5;
    let p07SaveInFlight = false;
    let p07TransitionInFlight = false;
    let p07SpeechRequestId = 0;
    let p07LastFocusedElement = null;
    let p07SpeechMode = null;
    let p07SpeechStarted = false;
    let p07VoiceFeedbackTimer = null;
    let p07VoicesChangeBound = false;

    function p07Main() { return document.getElementById('main'); }
    function p07PatientId() { return p07Main()?.getAttribute('data-paciente') || ''; }
    function p07Role() { return p07Main()?.getAttribute('data-rol') || 'Paciente'; }
    function p07StorageKey(name) { return 'p07:' + String(p07PatientId() || 'unknown') + ':modulo-' + p07ModuleId + ':' + name; }
    function p07Message(message) {
        const node = document.getElementById('mensaje');
        if (node) node.textContent = message || '';
    }
    function p07SetQuestionVoiceActive(active) {
        document.querySelectorAll('[data-p07-voice="question"]').forEach(button => {
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        document.querySelectorAll('[data-p07-stop-voice="question"]').forEach(button => {
            button.hidden = !active;
            button.setAttribute('aria-hidden', active ? 'false' : 'true');
            button.tabIndex = active ? 0 : -1;
        });
    }

    function p07MarkVoiceNeedsAction(needsAction) {
        document.querySelectorAll('[data-p07-voice="question"], [data-p07-voice="instructions"]').forEach(button => {
            button.classList.toggle('p07-voice-needs-action', needsAction);
        });
    }

    function p07ClearVoiceFeedbackTimer() {
        if (p07VoiceFeedbackTimer !== null) {
            window.clearTimeout(p07VoiceFeedbackTimer);
            p07VoiceFeedbackTimer = null;
        }
    }

    function p07SetVoicePrompt(message) {
        const prompt = document.getElementById('p07VoicePrompt');
        if (prompt) prompt.textContent = message || '';
    }

    function p07SetVoiceStatus(message) {
        const status = document.getElementById('p07VoiceStatus');
        if (status) status.textContent = message || '';
    }

    function p07StopVoice(announce = false) {
        const hadSpeech = p07SpeechStarted;
        const previousMode = p07SpeechMode;
        p07SpeechRequestId += 1;
        p07SpeechMode = null;
        p07SpeechStarted = false;
        p07ClearVoiceFeedbackTimer();
        if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        p07SetQuestionVoiceActive(false);
        p07MarkVoiceNeedsAction(false);
        if (previousMode === 'instructions') {
            PatientAudioControls.setState('p07-instructions', announce && hadSpeech ? 'stopped' : 'idle');
        }
        if (!announce || !hadSpeech) return;
        if (previousMode === 'question') {
            p07SetVoiceStatus(PatientAudioStatus.messages.stopped);
            return;
        }
        p07SetVoicePrompt(PatientAudioStatus.messages.stopped);
    }

    function p07BindVoicesChanged() {
        if (p07VoicesChangeBound || !('speechSynthesis' in window)) return;
        const synthesis = window.speechSynthesis;
        if (typeof synthesis.addEventListener !== 'function') return;
        synthesis.addEventListener('voiceschanged', () => {}, { once: true });
        p07VoicesChangeBound = true;
    }

    function p07GetSpanishVoice() {
        if (!('speechSynthesis' in window)) return null;
        p07BindVoicesChanged();
        const voices = window.speechSynthesis.getVoices();
        return voices.find(voice => /^es-MX/i.test(voice.lang))
            || voices.find(voice => /^es(-|_)/i.test(voice.lang))
            || null;
    }

    function p07SpeakText(text, mode = 'instructions') {
        if (!('speechSynthesis' in window) || typeof SpeechSynthesisUtterance === 'undefined') {
            const message = 'La lectura por voz no está disponible en este navegador. Puedes continuar con las instrucciones escritas.';
            p07Message(message);
            if (mode === 'instructions') p07SetVoicePrompt(message);
            else p07SetVoiceStatus(message);
            p07SetQuestionVoiceActive(false);
            p07MarkVoiceNeedsAction(true);
            if (mode === 'instructions') PatientAudioControls.unavailable('p07-instructions');
            return false;
        }

        const cleanText = String(text || '').replace(/\s+/g, ' ').trim();
        if (!cleanText) {
            const message = 'No hay texto disponible para leer en este momento.';
            if (mode === 'instructions') p07SetVoicePrompt(message);
            else p07SetVoiceStatus(message);
            return false;
        }

        p07StopVoice();
        p07BindVoicesChanged();
        const utterance = new SpeechSynthesisUtterance(cleanText);
        const voice = p07GetSpanishVoice();
        utterance.lang = voice ? voice.lang : 'es-MX';
        if (voice) utterance.voice = voice;
        utterance.rate = .88;
        utterance.pitch = 1;
        const requestId = p07SpeechRequestId;
        p07SpeechMode = mode;
        p07SpeechStarted = false;
        p07MarkVoiceNeedsAction(false);
        if (mode === 'instructions') { p07SetVoicePrompt(PatientAudioStatus.messages.idle); PatientAudioControls.idle('p07-instructions'); }
        else p07SetVoiceStatus('Intentando iniciar la lectura de la pregunta.');

        utterance.onstart = () => {
            if (requestId !== p07SpeechRequestId) return;
            p07SpeechStarted = true;
            p07ClearVoiceFeedbackTimer();
            p07SetQuestionVoiceActive(mode === 'question');
            if (mode === 'instructions') { p07SetVoicePrompt(PatientAudioStatus.messages.playing); PatientAudioControls.playing('p07-instructions'); }
            else p07SetVoiceStatus('Reproduciendo la pregunta activa. Puedes detenerla o repetirla.');
        };
        utterance.onend = () => {
            if (requestId !== p07SpeechRequestId) return;
            p07ClearVoiceFeedbackTimer();
            p07SpeechStarted = false;
            p07SpeechMode = null;
            p07SetQuestionVoiceActive(false);
            if (mode === 'instructions') { p07SetVoicePrompt(PatientAudioStatus.messages.idle); PatientAudioControls.ended('p07-instructions'); }
            else p07SetVoiceStatus('Lectura de la pregunta finalizada.');
        };
        utterance.onerror = () => {
            if (requestId !== p07SpeechRequestId) return;
            p07ClearVoiceFeedbackTimer();
            p07SpeechStarted = false;
            p07SpeechMode = null;
            p07SetQuestionVoiceActive(false);
            p07MarkVoiceNeedsAction(true);
            const message = 'No se pudo iniciar la lectura. Presiona Escuchar para intentarlo de nuevo.';
            if (mode === 'instructions') p07SetVoicePrompt(message);
            else p07SetVoiceStatus(message);
            if (mode === 'instructions') PatientAudioControls.idle('p07-instructions');
        };

        try {
            window.speechSynthesis.speak(utterance);
            p07VoiceFeedbackTimer = window.setTimeout(() => {
                p07VoiceFeedbackTimer = null;
                if (requestId !== p07SpeechRequestId || p07SpeechStarted) return;
                window.speechSynthesis.cancel();
                p07SpeechMode = null;
                p07SetQuestionVoiceActive(false);
                p07MarkVoiceNeedsAction(true);
                const message = 'No se pudo iniciar la lectura automáticamente. Presiona Escuchar para intentarlo.';
                if (mode === 'instructions') p07SetVoicePrompt(message);
                else p07SetVoiceStatus(message);
            }, 1500);
            return true;
        } catch (error) {
            p07SpeechMode = null;
            p07SpeechStarted = false;
            p07SetQuestionVoiceActive(false);
            p07MarkVoiceNeedsAction(true);
            const message = 'No se pudo iniciar la lectura. Presiona Escuchar para intentarlo de nuevo.';
            if (mode === 'instructions') p07SetVoicePrompt(message);
            else p07SetVoiceStatus(message);
            return false;
        }
    }

    function p07SpeechTextForActive(active) {
        if (!active) return '';
        const parts = [];
        if (active.classList.contains('pregunta-container')) {
            const topic = active.querySelector(':scope > h4');
            const question = active.querySelector(':scope > .sectionQuestion .texto');
            if (topic) parts.push(topic.textContent);
            if (question) parts.push(question.textContent);
        } else {
            Array.from(active.children).forEach(child => {
                if (child.matches('button, input, select, textarea, script, style, [aria-live], .mt-3.d-flex')) return;
                const style = window.getComputedStyle(child);
                if (style.display === 'none' || style.visibility === 'hidden') return;
                if (child.classList.contains('sectionQuestion')) {
                    const title = child.querySelector('.texto');
                    if (title) parts.push(title.textContent);
                    return;
                }
                if (child.classList.contains('text-secondary')) parts.push(child.textContent);
            });
        }
        return parts.map(value => String(value || '').replace(/\s+/g, ' ').trim()).filter(Boolean).join('. ');
    }

    function p07ReadActive(prefix = '') {
        const modal = document.getElementById('p07InstructionsModal');
        if (modal && modal.getAttribute('aria-hidden') !== 'true') return;
        const active = document.querySelector('.pregunta-container.active, .enfermedad-container.active');
        if (!active) return;
        const text = [prefix, p07SpeechTextForActive(active)].filter(Boolean).join('. ');
        p07SpeakText(text, 'question');
    }

    function p07ReadQuestionWhenReady() {
        const readActiveQuestion = () => {
            const modal = document.getElementById('p07InstructionsModal');
            if (modal && modal.getAttribute('aria-hidden') !== 'true') return;
            p07ReadActive();
        };
        if (typeof queueMicrotask === 'function') queueMicrotask(readActiveQuestion);
        else if (typeof requestAnimationFrame === 'function') requestAnimationFrame(readActiveQuestion);
        else window.setTimeout(readActiveQuestion, 0);
    }

    function p07ReadInstructions() {
        const instructions = document.getElementById('p07InstructionsText');
        p07SpeakText(instructions ? instructions.textContent : 'Lee o escucha cada pregunta y selecciona la respuesta disponible.');
    }

    function p07OpenInstructions() {
        const modal = document.getElementById('p07InstructionsModal');
        if (!modal) return;
        if (modal.getAttribute('aria-hidden') !== 'true') {
            const currentControl = modal.querySelector('button, [href], input, select, textarea');
            if (currentControl) currentControl.focus();
            return;
        }
        p07StopVoice();
        p07LastFocusedElement = PatientInstructionsModal.capture(modal, document.activeElement, '#p07HelpButton');
        PatientInstructionsModal.open(modal, { opener: p07LastFocusedElement, focusSelector: '#p07InstructionsCloseButton' });
        p07ReadInstructions();
    }

    function p07CloseInstructions() {
        const modal = document.getElementById('p07InstructionsModal');
        if (!modal) return;
        PatientInstructionsModal.close(modal, { returnFocus: p07LastFocusedElement, fallbackSelector: '#p07HelpButton' });
        p07StopVoice();
        p07LastFocusedElement = null;
        p07ReadQuestionWhenReady();
    }
    function p07CurrentSection() {
        return localStorage.getItem(p07StorageKey('seccionActual'));
    }
    function p07SetSection(value) { localStorage.setItem(p07StorageKey('seccionActual'), value); }
    function p07ThemeIndex(id) { return p07Themes.findIndex(theme => String(theme.id) === String(id)); }
    function p07SetBusy(busy) {
        document.querySelectorAll('[data-p07-next], [data-p07-next-theme], [data-p07-next-diseases], [data-p07-v2-next], [data-p07-previous], [data-p07-v2-previous]').forEach(button => {
            button.disabled = Boolean(busy);
            button.setAttribute('aria-busy', busy ? 'true' : 'false');
        });
    }
    function p07Error(error) {
        const status = Number(error?.status || 0);
        if (status === 403) return 'No tienes autorización para modificar esta respuesta.';
        if (status === 401 || status === 404) return 'La sesión ya no es válida. Vuelve a iniciar sesión.';
        return 'No se pudo guardar la respuesta. Inténtalo nuevamente.';
    }
    function p07Value(control) { return String(control?.value ?? ''); }
    function p07Dirty(control) {
        if (!control || control.disabled) return false;
        if (control.type === 'radio') return control.checked && p07Value(control) !== String(control.dataset.p07SavedValue || '');
        return p07Value(control) !== String(control.dataset.p07SavedValue || '');
    }
    function p07MarkSaved(control) {
        if (!control) return;
        const scope = control.closest('.pregunta-container, .enfermedad-container') || document;
        const group = control.type === 'radio'
            ? scope.querySelectorAll('[data-p07-control][data-p07-response="' + control.dataset.p07Response + '"]')
            : [control];
        group.forEach(item => { item.dataset.p07SavedValue = p07Value(control); });
    }
    async function p07Save(control) {
        if (!control || !control.isConnected || control.disabled) return true;
        if (!p07Dirty(control)) return true;
        if (p07SaveInFlight) return false;
        const idPaciente = Number(control.dataset.p07Patient || p07PatientId());
        const idRespuesta = Number(control.dataset.p07Response || 0);
        const idRol = control.dataset.p07Role || p07Role();
        const isV2 = control.dataset.p07Edicion !== undefined;
        const payload = isV2 ? {
            idPaciente, idEnfermedad: idRespuesta, idEnfermedadCatalogo: Number(control.closest('.enfermedad-container')?.dataset.p07DiseaseId || 0), edicion: Number(control.dataset.p07Edicion),
            detalle: p07Value(control), idRol
        } : {
            idPaciente, idRespuesta, idPregunta: Number(control.dataset.p07QuestionId || control.closest('.pregunta-container')?.getAttribute('data-pregunta-id') || 0), idTema: Number(control.dataset.p07Theme || 0),
            idTipo: control.dataset.p07Type || '', detalle: p07Value(control), idRol
        };
        const url = '/' + (idRol === 'Paciente' ? 'historia-clinica' : 'clinica')
            + '/paciente/editar-cuestionario' + (isV2 ? 'V2' : 'V1') + '-modulo5';
        p07SaveInFlight = true;
        p07SetBusy(true);
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (!response.ok || !data?.resultado) {
                const error = new Error('save-failed');
                error.status = response.status;
                throw error;
            }
            p07MarkSaved(control);
            p07Message('');
            return true;
        } catch (error) {
            p07Message(p07Error(error));
            return false;
        } finally {
            p07SaveInFlight = false;
            p07SetBusy(false);
        }
    }
    function p07Active(selector) { return document.querySelector(selector + '.active'); }
    function p07VisibleControls(container) {
        return Array.from(container?.querySelectorAll('[data-p07-control]') || []).filter(control => !control.disabled && control.offsetParent !== null);
    }
    function p07ReadyV1(question) {
        if (!question) return false;
        const controls = p07VisibleControls(question);
        const radios = controls.filter(control => control.type === 'radio');
        if (radios.length > 0 && !radios.some(control => control.checked)) return false;
        return controls.filter(control => control.type !== 'radio')
            .every(control => p07Value(control).trim() !== '');
    }
    function p07ReadyV2(disease) {
        if (!disease) return false;
        const selected = disease.querySelector('input[data-p07-edicion="1"]:checked');
        if (!selected) return false;
        if (selected.value === 'No') return true;
        const dependencies = p07VisibleControls(disease).filter(control => control.dataset.p07Edicion !== '1');
        const dependencyRadios = dependencies.filter(control => control.type === 'radio');
        if (dependencyRadios.length > 0 && !dependencyRadios.some(control => control.checked)) return false;
        return dependencies.filter(control => control.type !== 'radio')
            .every(control => p07Value(control).trim() !== '');
    }
    function p07HasV2Dependencies(disease) {
        return disease?.dataset.p07HasDependencies === '1'
            || Boolean(disease?.querySelector('[data-p07-edicion="2"], [data-p07-edicion="3"]'));
    }
    async function p07SaveActive(container) {
        const dirty = p07VisibleControls(container).filter(p07Dirty);
        for (const control of dirty) {
            if (!await p07Save(control)) return false;
        }
        return true;
    }
    function p07LoadV1(idTema, offset = 0) {
        p07StopVoice();
        document.querySelectorAll('.pregunta-container, .enfermedad-container').forEach(node => node.remove());
        document.getElementById('preguntas-container').style.display = 'block';
        document.getElementById('enfermedades-container').style.display = 'none';
        p07SetSection(p07Themes.find(theme => String(theme.id) === String(idTema))?.nombre || '');
        return fetch('/buscar/contenido-preguntasV1-modulo-5/' + encodeURIComponent(p07PatientId()) + '/Paciente/' + encodeURIComponent(idTema))
            .then(async response => {
                if (!response.ok) throw new Error('load-failed');
                return response.text();
            })
            .then(html => {
                const target = document.getElementById('contePreguntas_' + idTema);
                if (!target) throw new Error('target-missing');
                target.innerHTML = html;
                if (window.feather) feather.replace();
                const questions = Array.from(document.querySelectorAll('.pregunta-container'));
                const stored = Number.parseInt(localStorage.getItem(p07StorageKey('preguntaActual_' + idTema)), 10);
                const index = Number.isInteger(stored) ? Math.max(0, Math.min(stored, questions.length - 1)) : Math.max(0, offset);
                questions.forEach((question, questionIndex) => question.classList.toggle('active', questionIndex === index));
                p07ReadActive();
            })
            .catch(() => p07Message('No se pudo cargar la sección. Intenta nuevamente.'));
    }
    function p07LoadV2(announceTransition = false) {
        p07StopVoice();
        document.querySelectorAll('.pregunta-container, .enfermedad-container').forEach(node => node.remove());
        document.getElementById('preguntas-container').style.display = 'none';
        document.getElementById('enfermedades-container').style.display = 'block';
        p07SetSection('enfermedades');
        return fetch('/buscar/contenido-preguntasV2-modulo-5/' + encodeURIComponent(p07PatientId()) + '/Paciente')
            .then(async response => {
                if (!response.ok) throw new Error('load-failed');
                return response.text();
            })
            .then(html => {
                const target = document.getElementById('contePreguntasV2');
                if (!target) throw new Error('target-missing');
                target.innerHTML = html;
                if (window.feather) feather.replace();
                const diseases = Array.from(document.querySelectorAll('.enfermedad-container'));
                const stored = Number.parseInt(localStorage.getItem(p07StorageKey('preguntaActualV2')), 10);
                const index = Number.isInteger(stored) ? Math.max(0, Math.min(stored, diseases.length - 1)) : 0;
                diseases.forEach((disease, diseaseIndex) => disease.classList.toggle('active', diseaseIndex === index));
                p07ReadActive(announceTransition ? 'Ahora revisaremos diferentes enfermedades para saber si alguna vez te han sido diagnosticadas' : '');
            })
            .catch(() => p07Message('No se pudo cargar la lista de enfermedades. Intenta nuevamente.'));
    }
    function p07NextTheme(idTema) {
        const index = p07ThemeIndex(idTema);
        const next = index >= 0 ? p07Themes[index + 1] : null;
        if (next) return p07LoadV1(next.id);
        return p07LoadV2(true);
    }
    async function p07AdvanceV1(trigger = null) {
        if (p07TransitionInFlight) return;
        const question = p07Active('.pregunta-container');
        if (!question) return;
        if (!p07ReadyV1(question)) {
            p07Message('Completa la respuesta antes de continuar.');
            return;
        }
        p07TransitionInFlight = true;
        if (trigger) trigger.disabled = true;
        try {
            if (!await p07SaveActive(question)) return;
            const questions = Array.from(document.querySelectorAll('.pregunta-container'));
            const index = questions.indexOf(question);
            const selected = question.querySelector('input[type="radio"][data-p07-control]:checked');
            if (selected?.value === 'No' && index === 0) {
                await p07NextTheme(question.getAttribute('data-tema'));
                return;
            }
            if (index < questions.length - 1) {
                question.classList.remove('active');
                questions[index + 1].classList.add('active');
                localStorage.setItem(p07StorageKey('preguntaActual_' + question.getAttribute('data-tema')), String(index + 1));
                p07ReadActive();
            } else {
                await p07NextTheme(question.getAttribute('data-tema'));
            }
        } finally {
            p07TransitionInFlight = false;
            if (trigger?.isConnected) trigger.disabled = false;
        }
    }
    async function p07AdvanceV2(trigger = null) {
        if (p07TransitionInFlight) return;
        const disease = p07Active('.enfermedad-container');
        if (!disease) return;
        if (!p07ReadyV2(disease)) {
            p07Message('Completa los datos necesarios antes de continuar.');
            return;
        }
        p07TransitionInFlight = true;
        if (trigger) trigger.disabled = true;
        try {
            if (!await p07SaveActive(disease)) return;
            const diseases = Array.from(document.querySelectorAll('.enfermedad-container'));
            const index = diseases.indexOf(disease);
            if (index < diseases.length - 1) {
                disease.classList.remove('active');
                diseases[index + 1].classList.add('active');
                localStorage.setItem(p07StorageKey('preguntaActualV2'), String(index + 1));
                p07ReadActive();
            } else {
                const finish = disease.querySelector('[data-finalizar-modulo]');
                if (finish) finish.click();
            }
        } finally {
            p07TransitionInFlight = false;
            if (trigger?.isConnected) trigger.disabled = false;
        }
    }
    function p07PreviousV1() {
        if (p07SaveInFlight || p07TransitionInFlight) return;
        const questions = Array.from(document.querySelectorAll('.pregunta-container'));
        const active = p07Active('.pregunta-container');
        const index = questions.indexOf(active);
        if (index > 0) {
            active.classList.remove('active');
            questions[index - 1].classList.add('active');
            localStorage.setItem(p07StorageKey('preguntaActual_' + active.getAttribute('data-tema')), String(index - 1));
            p07ReadActive();
            return;
        }
        const themeIndex = p07ThemeIndex(active?.getAttribute('data-tema'));
        if (themeIndex > 0) p07LoadV1(p07Themes[themeIndex - 1].id, 9999);
    }
    function p07PreviousV2() {
        if (p07SaveInFlight || p07TransitionInFlight) return;
        const diseases = Array.from(document.querySelectorAll('.enfermedad-container'));
        const active = p07Active('.enfermedad-container');
        const index = diseases.indexOf(active);
        if (index > 0) {
            active.classList.remove('active');
            diseases[index - 1].classList.add('active');
            localStorage.setItem(p07StorageKey('preguntaActualV2'), String(index - 1));
            p07ReadActive();
        } else {
            const last = p07Themes[p07Themes.length - 1];
            if (last) p07LoadV1(last.id, 9999);
        }
    }
    function finalizarModuloPAC(idModulo, idPaciente) {
        if (p07SaveInFlight) return;
        fetch('/historia-clinica/paciente/finalizar-modulo-paciente', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ idModulo, idPaciente })
        }).then(async response => {
            const data = await response.json();
            if (!response.ok || !data?.resultado) throw new Error('finalize-failed');
            const nextUrl = typeof data.next_url === 'string' && data.next_url.startsWith('/') ? data.next_url : '/historia-clinica';
            window.location.assign(nextUrl);
        }).catch(() => p07Message('No se pudo finalizar la sección. Inténtalo nuevamente.'));
    }
    async function p07HandleRadio(control) {
        if (!control || p07TransitionInFlight) return;
        const saved = await p07Save(control);
        if (!saved) return;
        const question = control.closest('.pregunta-container');
        const disease = control.closest('.enfermedad-container');
        if (question && (control.value === 'No' || !question.querySelector('[data-p07-control][data-p07-type="input"]'))) {
            await p07AdvanceV1();
        }
        if (!disease) return;
        if (control.value === 'No' || p07HasV2Dependencies(disease)) {
            if (control.value === 'No') await p07AdvanceV2();
            return;
        }
        const diseases = Array.from(document.querySelectorAll('.enfermedad-container'));
        localStorage.setItem(p07StorageKey('preguntaActualV2'), String(diseases.indexOf(disease)));
        await p07LoadV2(false);
    }

    document.addEventListener('click', event => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        if (target.closest('#p07HelpButton')) { event.preventDefault(); p07OpenInstructions(); return; }
        if (target.closest('[data-p07-voice="instructions"]')) { event.preventDefault(); p07ReadInstructions(); return; }
        if (target.closest('[data-p07-voice="question"]')) { event.preventDefault(); p07ReadActive(); return; }
        if (target.closest('[data-p07-stop-voice]')) { event.preventDefault(); p07StopVoice(true); return; }
        if (target.closest('#p07InstructionsCloseButton, #p07StartButton')) { event.preventDefault(); p07CloseInstructions(); return; }
        if (target.id === 'p07InstructionsModal') { p07CloseInstructions(); return; }
        if (target.closest('[data-p07-next]')) { event.preventDefault(); p07AdvanceV1(target.closest('[data-p07-next]')); return; }
        if (target.closest('[data-p07-next-theme], [data-p07-next-diseases]')) { event.preventDefault(); p07AdvanceV1(target.closest('[data-p07-next-theme], [data-p07-next-diseases]')); return; }
        if (target.closest('[data-p07-v2-next]')) { event.preventDefault(); p07AdvanceV2(target.closest('[data-p07-v2-next]')); return; }
        if (target.closest('[data-p07-previous]')) { event.preventDefault(); p07PreviousV1(); return; }
        if (target.closest('[data-p07-v2-previous]')) { event.preventDefault(); p07PreviousV2(); }
    });
    document.addEventListener('keydown', event => {
        const modal = document.getElementById('p07InstructionsModal');
        if (!modal || modal.getAttribute('aria-hidden') === 'true') return;
        if (event.key === 'Escape') {
            event.preventDefault();
            p07CloseInstructions();
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
    window.addEventListener('pagehide', () => p07StopVoice());
    document.addEventListener('change', event => {
        const target = event.target instanceof Element ? event.target : null;
        const control = target?.closest('[data-p07-control]');
        if (!control || p07TransitionInFlight) return;
        if (control.type === 'radio') { p07HandleRadio(control); return; }
        p07Save(control);
    });
    document.addEventListener('DOMContentLoaded', () => {
        const stored = p07CurrentSection();
        const theme = p07Themes.find(item => item.nombre === stored) || p07Themes[0];
        if (stored === 'enfermedades') p07LoadV2(true);
        else if (theme) p07LoadV1(theme.id);
        if (window.feather) feather.replace();
        p07OpenInstructions();
    }, { once: true });

    </script>
    </head>

<body class="app-shell-body app-shell-patient patient-clinical-form patient-nonpathological-page patient-pathological-page">
    <div class="LoaderPage"></div>
    <div id="app">
        
    <!---------- SIDEBAR ---------->
    <?=$data['sidebar'];?>
    
    <div id="main" data-rol="<?=$data['idRol'];?>" data-paciente="<?=$data['idPaciente'];?>">
    <nav class="navbar navbar-header navbar-expand navbar-light">
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
    <p>Responde las preguntas sobre tus antecedentes personales patológicos.</p>
    <span class="patient-nonpathological-context">Paciente: <?=$escape($patientName);?></span>
    </div>
    <div class="patient-nonpathological-hero-actions">
    <button type="button" id="p07HelpButton" class="btn patient-nonpathological-help" aria-label="¿Cómo llenar esta sección?" aria-haspopup="dialog" aria-controls="p07InstructionsModal">
    <i data-feather="help-circle" aria-hidden="true"></i>
    <span>¿Cómo llenar esta sección?</span>
    </button>
    <a class="btn patient-nonpathological-back" href="<?=SERVIDOR;?>historia-clinica">
    <i data-feather="arrow-left" aria-hidden="true"></i>
    <span>Volver a mi Historia Clínica</span>
    </a>
    </div>
    </header>
    
    <section class="section">

    <div class="card patient-nonpathological-card">

    <!---------- CONTENEDOR DE PREGUNTAS ---------->
    <div id="preguntas-container">

    <div class="card-header patient-nonpathological-card-header">
    <div class="row">

    <div id="seccion100" data-autoplay="false" class="col-12 col-md-9 d-flex align-items-center sectionQuestion mb-2">

    <h8 class="text-primary fw-bold texto">
    <b>A continuacion, responda las siguientes preguntas indicando si presenta alguna de estas conductas o hábitos:</b>
    </h8>
    </div>

    <div class="col-12 col-md-3">
    <div class="patient-nonpathological-question-tools p07-question-tools" aria-label="Controles de lectura de la pregunta">
    <button id="p07ReadQuestionV1" type="button" class="btn patient-nonpathological-voice-button" data-p07-voice="question" aria-pressed="false">
    <i data-feather="volume-2" aria-hidden="true"></i><span>Escuchar</span>
    </button>
    <button id="p07StopQuestionV1" type="button" class="btn patient-nonpathological-voice-button" data-p07-stop-voice="question" hidden aria-hidden="true" tabindex="-1">
    <i data-feather="square" aria-hidden="true"></i><span>Detener</span>
    </button>
    </div>
    </div>

    <div class="col-12">
    <div id="mensaje" class="text-center text-danger mt-2" role="alert" aria-live="polite"></div>
    </div>

    </div>
    </div>

    <div class="card-body patient-nonpathological-card-body patient-nonpathological-generated">
    <div id="contePreguntas_1"></div>
    <div id="contePreguntas_2"></div>
    <div id="contePreguntas_3"></div>
    <div id="contePreguntas_4"></div>
    </div>
    </div>


    <!---------- CONTENEDOR DE ENFERMEDADES ---------->
    <div id="enfermedades-container">

    <div class="card-header patient-nonpathological-card-header">
    <div class="row">

    <div id="seccion101" data-autoplay="false" class="col-12 col-md-9 d-flex align-items-center sectionQuestion mb-2">

    <h8 class="text-primary fw-bold texto">
    <b>A continuación se presentará una serie de distintas enfermedades.
    Si usted padece o ha padecido alguna de ellas, por favor indique el año aproximado en que fue diagnosticado:</b>
    </h8>
    </div>

    <div class="col-12 col-md-3">
    <div class="patient-nonpathological-question-tools p07-question-tools" aria-label="Controles de lectura de la pregunta">
    <button id="p07ReadQuestionV2" type="button" class="btn patient-nonpathological-voice-button" data-p07-voice="question" aria-pressed="false">
    <i data-feather="volume-2" aria-hidden="true"></i><span>Escuchar</span>
    </button>
    <button id="p07StopQuestionV2" type="button" class="btn patient-nonpathological-voice-button" data-p07-stop-voice="question" hidden aria-hidden="true" tabindex="-1">
    <i data-feather="square" aria-hidden="true"></i><span>Detener</span>
    </button>
    </div>
    </div>

    <div class="col-12">
    <div class="text-center text-danger mt-2" aria-hidden="true"></div>
    </div>

    </div>
    </div>

    
    <div class="card-body patient-nonpathological-card-body patient-nonpathological-generated">
    <div id="contePreguntasV2"></div>
    </div>
    </div>



    </div>
    </section>
    </main>
    <div id="p07InstructionsModal" class="modal fade patient-nonpathological-modal patient-instructions-modal" tabindex="-1" role="dialog" aria-labelledby="p07InstructionsTitle" aria-describedby="p07InstructionsText" aria-modal="true" aria-hidden="true" style="display: none;">
    <div class="modal-dialog modal-dialog-centered patient-instructions-dialog">
    <div class="modal-content patient-nonpathological-modal-content patient-instructions-modal-content">
    <div class="modal-header patient-instructions-modal-header">
    <h2 id="p07InstructionsTitle" class="modal-title patient-instructions-modal-title">Antes de comenzar: Antecedentes Personales Patológicos</h2>
    <?php $modalCloseConfig = ['id' => 'p07InstructionsCloseButton']; include __DIR__ . '/partials/modal-close.php'; unset($modalCloseConfig); ?>
    </div>
    <div class="modal-body patient-instructions-modal-body">
    <p id="p07InstructionsText" data-narration-instructions>En esta sección te preguntaremos sobre tus antecedentes personales de salud y enfermedades que hayas padecido o que tengas actualmente.<br><br>Primero responderás algunas preguntas generales. Después revisaremos diferentes enfermedades para saber si alguna vez te han sido diagnosticadas.<br><br>Selecciona Sí o No según corresponda. Si respondes Sí, es posible que necesitemos información adicional, como el tipo de enfermedad o el año aproximado del diagnóstico.<br><br>Puedes regresar para revisar tus respuestas. La información se guardará mientras avances.<br><br>Cuando termines todas las preguntas, podrás finalizar esta sección y continuar con tu Historia Clínica.</p>
    <p id="p07VoicePrompt" class="patient-nonpathological-voice-prompt patient-instructions-voice-prompt" role="status" aria-live="polite">Para escuchar las instrucciones, presiona este botón.</p>
    <p class="patient-nonpathological-modal-note patient-instructions-modal-note">Tus respuestas se guardan mientras avanzas. Puedes cerrar esta ventana sin perder tu progreso.</p>
    </div>
    <div class="modal-footer patient-nonpathological-modal-actions patient-instructions-modal-actions">
    <?php $modalAudioConfig = ['group' => 'p07-instructions', 'listen_id' => 'p07ReadInstructionsButton', 'stop_id' => 'p07StopInstructionsButton', 'listen_class' => 'btn patient-nonpathological-secondary patient-instructions-secondary', 'stop_class' => 'btn patient-nonpathological-secondary patient-instructions-secondary', 'listen_attrs' => ['data-p07-voice' => 'instructions'], 'stop_attrs' => ['data-p07-stop-voice' => 'instructions']]; include __DIR__ . '/partials/modal-audio-controls.php'; unset($modalAudioConfig); ?>
    <button id="p07StartButton" type="button" class="btn btn-primary">Entendido, comenzar</button>
    </div>
    </div>
    </div>
    </div>
    <!----- FOOTER ---------->
<?php include_once __DIR__ . '/../components/footer-doctor.php';?>
    </div>

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
