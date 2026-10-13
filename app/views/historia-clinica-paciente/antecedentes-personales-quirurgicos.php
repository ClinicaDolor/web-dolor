<?php
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pageTitle = $data['title'] ?? 'Antecedentes Personales Quirúrgicos';
$patientName = $data['nombre'] ?? 'Paciente';
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
    <link rel="stylesheet" href="<?= RUTA_PUBLIC; ?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?= $appCssUrl; ?>">
    <script src="<?= $loaderJsUrl; ?>"></script>
    <script>
    let p06PendingRequest = false;
    let p06ContentLoading = false;
    let p06FinalizationInFlight = false;
    let p06LastFocusedElement = null;
    let p06SpeechRequestId = 0;

    function p06PatientId() {
        const main = document.getElementById('main');
        return main ? main.getAttribute('data-paciente') : '';
    }

    function p06StorageKey() {
        return 'p06:' + String(p06PatientId() || 'unknown') + ':recordIndex';
    }

    function p06SetMessage(message, success = false) {
        const element = document.getElementById('mensaje');
        if (!element) return;
        element.textContent = message || '';
        element.classList.toggle('is-success', Boolean(success));
        element.classList.toggle('is-error', !success && Boolean(message));
    }

    function p06ErrorMessage(error) {
        const status = Number(error && error.status);
        if (status === 403) return 'No tienes autorización para modificar este registro.';
        if (status === 401 || status === 404) return 'La sesión ya no es válida. Vuelve a iniciar sesión.';
        if (status === 422) return 'Revisa la información e inténtalo nuevamente.';
        if (status === 429) return 'Hay demasiadas solicitudes. Espera un momento e inténtalo de nuevo.';
        return 'No se pudo completar la operación. Inténtalo nuevamente.';
    }

    function p06SetBusy(busy) {
        document.querySelectorAll('.p06-add-button, .p06-list-actions button, .p06-danger-button, .p06-navigation button, .p06-empty-actions button').forEach(button => {
            button.disabled = Boolean(busy);
        });
        const loader = document.querySelector('.LoaderPage');
        if (loader) loader.style.display = busy ? 'block' : 'none';
    }

    function p06RequestJson(url, payload, callback) {
        if (p06PendingRequest || p06FinalizationInFlight) {
            p06SetMessage('Espera a que termine el guardado actual.');
            return Promise.resolve(false);
        }

        p06PendingRequest = true;
        p06SetBusy(true);
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
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
                const requestError = new Error('request-failed');
                requestError.status = response.status;
                throw requestError;
            }
            if (typeof callback === 'function') callback(data);
            return data;
        }).catch(error => {
            p06SetMessage(p06ErrorMessage(error));
            return false;
        }).finally(() => {
            p06PendingRequest = false;
            p06SetBusy(false);
        });
    }

    function p06LoadContent(offset = 0) {
        if (p06ContentLoading) return Promise.resolve(false);
        const idPaciente = p06PatientId();
        if (!idPaciente) {
            p06SetMessage('No se pudo identificar al paciente.');
            return Promise.resolve(false);
        }

        p06ContentLoading = true;
        return fetch('/buscar/contenido-preguntas-modulo-4/' + encodeURIComponent(idPaciente) + '/Paciente')
            .then(async response => {
                if (!response.ok) {
                    const requestError = new Error('content-failed');
                    requestError.status = response.status;
                    throw requestError;
                }
                return response.text();
            })
            .then(html => {
                const container = document.getElementById('contePreguntas');
                if (!container) return;
                container.innerHTML = html;
                if (window.feather && typeof window.feather.replace === 'function') window.feather.replace();
                p06ApplyRecordIndex(offset);
            })
            .catch(error => p06SetMessage(p06ErrorMessage(error)))
            .finally(() => { p06ContentLoading = false; });
    }

    function contenidoPreguntas(offset = 0) {
        return p06LoadContent(offset);
    }

    function p06ApplyRecordIndex(offset = 0) {
        const records = Array.from(document.querySelectorAll('#contePreguntas .p06-record'));
        if (!records.length) return;
        const saved = Number.parseInt(localStorage.getItem(p06StorageKey()), 10);
        let index = Number.isInteger(saved) ? saved + Number(offset || 0) : 0;
        index = Math.max(0, Math.min(index, records.length - 1));
        records.forEach((record, recordIndex) => {
            record.classList.toggle('active', recordIndex === index);
            record.setAttribute('aria-hidden', recordIndex === index ? 'false' : 'true');
        });
        localStorage.setItem(p06StorageKey(), String(index));
    }

    function guardarProgreso(index) {
        localStorage.setItem(p06StorageKey(), String(Math.max(0, Number(index) || 0)));
    }

    function cargarProgreso(offset = 0) {
        p06ApplyRecordIndex(offset);
    }

    function p06CurrentIndex() {
        const active = document.querySelector('#contePreguntas .p06-record.active');
        return active ? Number.parseInt(active.getAttribute('data-index'), 10) || 0 : 0;
    }

    function p06Navigate(delta) {
        if (p06PendingRequest || p06ContentLoading || p06FinalizationInFlight) {
            p06SetMessage('Espera a que termine el guardado actual.');
            return;
        }
        const records = Array.from(document.querySelectorAll('#contePreguntas .p06-record'));
        if (!records.length) return;
        const nextIndex = Math.max(0, Math.min(p06CurrentIndex() + delta, records.length - 1));
        guardarProgreso(nextIndex);
        p06StopVoice(false);
        p06ApplyRecordIndex(0);
        p06ReadCurrentQuestion(false);
    }

    function anteriorPregunta() { p06Navigate(-1); }
    function siguientePregunta() { p06Navigate(1); }
    function mostrarBotonAgregar() { return true; }

    function p06Mutate(url, payload, reloadOffset = 0) {
        return p06RequestJson(url, payload, () => {
            p06SetMessage('Cambios guardados.', true);
            p06LoadContent(reloadOffset);
        });
    }

    function agregarCirugiaPaciente(idPaciente) {
        if (String(idPaciente) !== String(p06PatientId())) {
            p06SetMessage('No se pudo validar el paciente.');
            return;
        }
        p06Mutate('/historia-clinica/paciente/agregar-cirugia-antecedentes', {
            idPaciente: Number(idPaciente),
            idRol: 'Paciente'
        }, 1);
    }

    function editarCirugia(idCirugia, elemento, parametro) {
        if (!elemento || p06ContentLoading || p06FinalizationInFlight) return;
        const idPaciente = Number(p06PatientId());
        if (!idPaciente || p06PendingRequest) {
            p06SetMessage('Espera a que termine el guardado actual.');
            return;
        }
        p06RequestJson('/historia-clinica/paciente/editar-cirugia-antecedentes', {
            idPaciente,
            idCirugia: Number(idCirugia),
            detalle: elemento.value,
            edicion: Number(parametro),
            idRol: 'Paciente'
        }, () => p06SetMessage('Cambios guardados.', true));
    }

    function p06FocusCurrentRecord() {
        const active = document.querySelector('#contePreguntas .p06-record.active');
        const firstField = active ? active.querySelector('input, textarea') : null;
        if (firstField) firstField.focus();
        p06SetMessage('Los cambios se guardan automáticamente al salir de cada campo.', true);
    }

    function eliminarCirugiaPaciente(idQuirurgico) {
        if (!window.confirm('¿Deseas eliminar esta cirugía? Esta acción no se puede deshacer.')) return;
        p06Mutate('/historia-clinica/paciente/eliminar-cirugia-antecedentes', {
            idPaciente: Number(p06PatientId()),
            idQuirurgico: Number(idQuirurgico),
            idRol: 'Paciente'
        }, 0);
    }

    function p06SetVoiceState(speaking) {
        document.querySelectorAll('.p06-voice-button').forEach(button => button.setAttribute('aria-pressed', speaking ? 'true' : 'false'));
        const status = document.getElementById('p06VoiceStatus');
        if (status) status.textContent = speaking ? 'Narración en curso.' : 'Narración detenida.';
    }

    function p06StopVoice(clearStatus = true) {
        p06SpeechRequestId += 1;
        if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        p06SetVoiceState(false);
        if (clearStatus) {
            const status = document.getElementById('p06VoiceStatus');
            if (status) status.textContent = 'Narración detenida.';
        }
    }

    function p06Speak(text) {
        const cleanText = String(text || '').replace(/\s+/g, ' ').trim();
        if (!cleanText) return false;
        if (!('speechSynthesis' in window) || typeof SpeechSynthesisUtterance === 'undefined') {
            const status = document.getElementById('p06VoiceStatus');
            if (status) status.textContent = 'La narración no está disponible en este navegador.';
            return false;
        }
        p06StopVoice(false);
        const requestId = ++p06SpeechRequestId;
        const utterance = new SpeechSynthesisUtterance(cleanText);
        utterance.lang = 'es-MX';
        utterance.rate = .88;
        utterance.onstart = () => { if (requestId === p06SpeechRequestId) p06SetVoiceState(true); };
        utterance.onend = () => { if (requestId === p06SpeechRequestId) p06SetVoiceState(false); };
        utterance.onerror = () => { if (requestId === p06SpeechRequestId) p06SetVoiceState(false); };
        window.speechSynthesis.speak(utterance);
        return true;
    }

    function p06ReadInstructions() {
        const text = document.getElementById('p06InstructionsText');
        return p06Speak(text ? text.textContent : 'En esta sección registra las cirugías, sus fechas y observaciones.');
    }

    function p06ReadCurrentQuestion(auto = true) {
        const active = document.querySelector('#contePreguntas .p06-record.active');
        const instruction = active ? active.querySelector('.p06-record-instruction') : document.querySelector('#contePreguntas .p06-instruction-text');
        if (!instruction) return false;
        return p06Speak(instruction.textContent);
    }

    function readQuestion() { return p06ReadCurrentQuestion(false); }

    function p06OpenInstructions() {
        const modal = document.getElementById('p06InstructionsModal');
        if (!modal) return;
        p06LastFocusedElement = PatientInstructionsModal.capture(modal, document.activeElement, '.patient-surgery-help');
        PatientInstructionsModal.open(modal, { opener: p06LastFocusedElement, display: 'flex', focusSelector: '.p06-modal-close' });
        document.body.classList.add('p06-modal-open');
        p06ReadInstructions();
    }

    function p06CloseInstructions() {
        const modal = document.getElementById('p06InstructionsModal');
        if (!modal) return;
        p06StopVoice();
        PatientInstructionsModal.close(modal, { returnFocus: p06LastFocusedElement, fallbackSelector: '.patient-surgery-help' });
        document.body.classList.remove('p06-modal-open');
        p06LastFocusedElement = null;
    }

    async function finalizarModuloPAC(idModulo, idPaciente) {
        if (p06FinalizationInFlight || p06PendingRequest || p06ContentLoading) {
            p06SetMessage('Espera a que termine el guardado actual.');
            return false;
        }
        if (String(idPaciente) !== String(p06PatientId())) {
            p06SetMessage('No se pudo validar el paciente.');
            return false;
        }
        p06FinalizationInFlight = true;
        p06SetBusy(true);
        try {
            const data = await fetch('/historia-clinica/paciente/finalizar-modulo-paciente', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ idModulo: Number(idModulo), idPaciente: Number(idPaciente) })
            }).then(async response => {
                const json = await response.json();
                if (!response.ok || !json || !json.resultado) {
                    const requestError = new Error('finalization-failed');
                    requestError.status = response.status;
                    throw requestError;
                }
                return json;
            });
            const nextUrl = typeof data.next_url === 'string' && data.next_url.startsWith('/') ? data.next_url : '/historia-clinica';
            window.location.assign(nextUrl);
            return true;
        } catch (error) {
            p06SetMessage(p06ErrorMessage(error));
            return false;
        } finally {
            p06FinalizationInFlight = false;
            p06SetBusy(false);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        p06LoadContent(0);
        p06OpenInstructions();
        document.addEventListener('keydown', event => {
            const modal = document.getElementById('p06InstructionsModal');
            PatientInstructionsModal.handleKeydown(event, modal, p06CloseInstructions);
        });
        document.addEventListener('click', event => {
            const modal = document.getElementById('p06InstructionsModal');
            if (modal && event.target === modal) p06CloseInstructions();
        });
    }, { once: true });

    window.addEventListener('pagehide', () => p06StopVoice(false));
    </script>
</head>
<body class="app-shell-body app-shell-patient patient-clinical-form patient-surgery-page">
    <div class="LoaderPage" aria-hidden="true"></div>
    <div id="app">
        <?= $data['sidebar']; ?>
        <div id="main" data-rol="<?= $escape($data['idRol']); ?>" data-paciente="<?= $escape($data['idPaciente']); ?>">
            <nav class="navbar navbar-header navbar-expand navbar-light portal-topbar" aria-label="Barra de herramientas del portal del paciente">
                <button type="button" class="sidebar-toggler border-0 bg-transparent" aria-label="Abrir menú principal" aria-controls="sidebar" aria-expanded="true"><span class="navbar-toggler-icon"></span></button>
                <button class="btn navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Mostrar navegación"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent"><ul class="navbar-nav d-flex align-items-center navbar-light ms-auto"></ul></div>
            </nav>

            <main class="main-content container-fluid patient-surgery-content">
                <header class="page-title portal-page-title patient-surgery-hero mb-4">
                    <div class="patient-surgery-hero-copy">
                        <p class="dashboard-eyebrow">MI HISTORIA CLÍNICA</p>
                        <h1><?= $escape($pageTitle); ?></h1>
                        <p>Registra y consulta los antecedentes de cirugías de tu historia clínica.</p>
                        <span class="patient-surgery-context">Paciente: <?= $escape($patientName); ?></span>
                    </div>
                    <div class="patient-surgery-hero-actions">
                        <button type="button" id="p06HelpButton" class="btn patient-surgery-help" aria-label="¿Cómo llenar esta sección?" aria-haspopup="dialog" aria-controls="p06InstructionsModal" onclick="p06OpenInstructions()"><i data-feather="help-circle" aria-hidden="true"></i><span>¿Cómo llenar esta sección?</span></button>
                        <a class="btn patient-surgery-back" href="<?= SERVIDOR; ?>historia-clinica"><i data-feather="arrow-left" aria-hidden="true"></i><span>Volver a mi Historia Clínica</span></a>
                    </div>
                </header>

                <div id="mensaje" class="patient-surgery-message" role="alert" aria-live="polite"></div>
                <section class="section patient-surgery-workspace" aria-label="Antecedentes personales quirúrgicos">
                    <section class="card patient-surgery-card" aria-labelledby="p06Title">
                        <div class="card-header patient-surgery-card-header">
                            <div class="patient-surgery-card-heading">
                                <p class="dashboard-eyebrow">ANTECEDENTES QUIRÚRGICOS</p>
                                <h2 id="p06Title">Registra tus cirugías</h2>
                                <p>Captura cada procedimiento con su fecha y las observaciones relevantes.</p>
                            </div>
                            <div class="patient-surgery-question-tools">
                                <button id="p06ReadQuestionButton" type="button" class="btn p06-voice-button" aria-pressed="false" onclick="p06ReadCurrentQuestion(false)"><i data-feather="volume-2" aria-hidden="true"></i><span>Escuchar</span></button>
                                <button id="p06StopQuestionButton" type="button" class="btn p06-voice-button" onclick="p06StopVoice()"><i data-feather="square" aria-hidden="true"></i><span>Detener</span></button>
                                <span id="p06VoiceStatus" class="visually-hidden" role="status" aria-live="polite"></span>
                            </div>
                        </div>
                        <div class="card-body patient-surgery-card-body">
                            <div id="contePreguntas" class="patient-surgery-generated" aria-live="polite"></div>
                        </div>
                    </section>
                </section>
            </main>

            <div id="p06InstructionsModal" class="p06-modal patient-instructions-modal" role="dialog" aria-modal="true" aria-labelledby="p06InstructionsTitle" aria-describedby="p06InstructionsText" aria-hidden="true" style="display: none;">
                <div class="modal-dialog modal-dialog-centered patient-instructions-dialog">
                <div class="p06-modal-content patient-instructions-modal-content">
                    <div class="p06-modal-header patient-instructions-modal-header"><h2 id="p06InstructionsTitle" class="modal-title patient-instructions-modal-title">Antes de comenzar: antecedentes quirúrgicos</h2><button type="button" class="p06-modal-close patient-instructions-close btn-close" aria-label="Cerrar" onclick="p06CloseInstructions()"><i data-feather="x" aria-hidden="true"></i></button></div>
                    <div class="p06-modal-body patient-instructions-modal-body"><p id="p06InstructionsText" data-narration-instructions>En esta sección puedes registrar el nombre de una cirugía, la fecha en que se realizó y observaciones adicionales. Puedes agregar más de un registro, editar la información y eliminar un registro agregado por error. Si no corresponde registrar una cirugía, puedes finalizar la sección sin crear datos.</p><p id="p06VoicePrompt" class="patient-instructions-voice-prompt" role="status" aria-live="polite">Para escuchar las instrucciones, presiona este botón.</p><p class="patient-instructions-modal-note">Tus respuestas se guardan mientras avanzas. Puedes cerrar esta ventana sin perder tu progreso.</p></div>
                    <div class="p06-modal-actions patient-instructions-modal-actions"><button type="button" class="btn p06-secondary-button patient-instructions-secondary" onclick="p06ReadInstructions()"><i data-feather="volume-2" aria-hidden="true"></i><span>Escuchar</span></button><button type="button" class="btn p06-secondary-button patient-instructions-secondary" onclick="p06StopVoice()"><i data-feather="square" aria-hidden="true"></i><span>Detener</span></button><button type="button" class="btn btn-primary" onclick="p06CloseInstructions()">Entendido, comenzar</button></div>
                </div>
                </div>
            </div>

            <?php include_once __DIR__ . '/../components/footer-doctor.php'; ?>
        </div>
    </div>
    <script src="<?= RUTA_JS; ?>patient-instructions-modal.js"></script>
    <script src="<?= RUTA_JS; ?>patient-narration-highlight.js"></script>
    <script src="<?= $voiceUtilitiesJsUrl; ?>"></script>
    <script src="<?= $featherJsUrl; ?>"></script>
    <script src="<?= RUTA_PUBLIC; ?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?= $appJsUrl; ?>"></script>
    <script src="<?= RUTA_PUBLIC; ?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?= $mainJsUrl; ?>"></script>
</body>
</html>
