<?php

$nombrePacienteSeguro = htmlspecialchars((string)($data['nombre_paciente'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$nombrePacienteVisible = $nombrePacienteSeguro !== '' ? $nombrePacienteSeguro : 'Paciente';
$referenciaSegura = htmlspecialchars((string)($data['referencia'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$listUrl = htmlspecialchars((string)($data['list_url'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$editMode = !empty($data['edit_mode']);
$viewHeading = $editMode ? 'Editar nota subsecuente' : 'Nueva nota subsecuente';
$viewDescription = $editMode
    ? 'Actualiza los datos de esta nota sin crear un registro adicional.'
    : 'Captura la evolución clínica de esta referencia. Los apartados de receta, laboratorio y próxima cita son opcionales.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars((string)$data['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></title>
<link rel="shortcut icon" href="<?=RUTA_IMAGES;?>logo-clinica.png">
<link rel="apple-touch-icon" href="<?=RUTA_IMAGES;?>logo-clinica.png">
<link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
<link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/quill/quill.snow.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?=RUTA_JS;?>loader.js"></script>
</head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-note-page doctor-note-create-page">
<div class="LoaderPage"></div>
<div id="app">

<?=$data['sidebar'];?>

<div id="main"
data-referencia="<?=$referenciaSegura;?>"
data-paciente="<?=htmlspecialchars((string)$data['idPaciente'], ENT_QUOTES, 'UTF-8');?>"
data-list-url="<?=$listUrl;?>">
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

<div class="main-content container-fluid doctor-note-main-content">
<header class="doctor-page-hero doctor-note-hero">
<div class="doctor-page-hero-copy">
<p class="dashboard-eyebrow">Nota subsecuente</p>
<h1><?=htmlspecialchars($viewHeading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></h1>
<p><?=htmlspecialchars($viewDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></p>
</div>
<div class="doctor-page-hero-actions">
<a class="btn doctor-page-hero-action doctor-note-history-back" data-note-exit data-doctor-back-action href="<?=$listUrl;?>">
<i data-feather="arrow-left" aria-hidden="true"></i><span>Volver al historial</span>
</a>
</div>
</header>

<section class="doctor-note-patient-context" aria-label="Contexto del paciente">
<div class="doctor-note-patient-strip">
<span class="doctor-note-patient-icon" aria-hidden="true"><i data-feather="user"></i></span>
<div class="doctor-note-patient-context-copy">
<p class="dashboard-eyebrow">EXPEDIENTE CLÍNICO</p>
<h2><?=$nombrePacienteVisible;?></h2>
<p>Expediente #<?=htmlspecialchars((string)$data['idPaciente'], ENT_QUOTES, 'UTF-8');?> · Referencia <?=htmlspecialchars((string)$data['referencia'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></p>
</div>
</div>
</section>

<section class="doctor-note-entry-section" aria-labelledby="doctor-note-editor-title">
<form id="notaSubsecuenteForm" novalidate>
<?php include __DIR__ . '/partials/nota-subsecuente-editor.php'; ?>
</form>
</section>

</div>
<?php include_once __DIR__ . '/../components/footer-doctor.php';?>
</div>
</div>
<script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="<?=RUTA_JS;?>app.js"></script>
<script src="<?=RUTA_JS;?>main.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/quill/quill.min.js"></script>
<script src="<?=RUTA_JS;?>search-main.js"></script>
<?php include_once __DIR__ . '/../components/modal-impresion-receta.php';?>
<script>
let notaGuardando = false;
let notaDirty = false;
let laboratorioCargado = false;

function notaEditorConfig() {
    return document.getElementById('nota-editor-config');
}

function recetaSolicitada() {
    const selector = document.getElementById('IncluirReceta');
    return Boolean(selector && selector.checked);
}

function recetaTieneContenido(editor) {
    return Boolean(editor && editor.innerText && editor.innerText.trim() !== '');
}

function mostrarMensajeNota(message) {
    const target = document.getElementById('mensaje');
    if (target) target.textContent = message;
}

function tableLaboratorio() {
    const config = notaEditorConfig();
    const resultsContainer = document.getElementById('conteLaboratorio');
    if (!config || !resultsContainer) return;

    const idPaciente = config.getAttribute('data-id-paciente');
    const referencia = config.getAttribute('data-referencia');
    fetch(`/buscar/tabla-laboratorio/${encodeURIComponent(idPaciente)}/${encodeURIComponent(referencia)}`)
        .then(response => {
            if (!response.ok) throw new Error('No fue posible cargar el historial de laboratorio.');
            return response.text();
        })
        .then(data => {
            resultsContainer.innerHTML = data;
            const tabla = document.querySelector('#tableLaboratorio');
            if (tabla && window.simpleDatatables) {
                new simpleDatatables.DataTable(tabla, {
                    searchable: true,
                    fixedHeight: false,
                    perPageSelect: false,
                    columns: [{ select: 1, sort: 'desc' }]
                });
            }
            if (window.feather) feather.replace();
        })
        .catch(() => mostrarMensajeNota('No fue posible cargar el historial de laboratorio.'));
}

function DetalleLaboratorio(idLaboratorio) {
    fetch(`/buscar/laboratorio/${encodeURIComponent(idLaboratorio)}`)
        .then(response => {
            if (!response.ok) throw new Error('No fue posible cargar el detalle del laboratorio.');
            return response.text();
        })
        .then(data => {
            const target = document.getElementById('detalleLaboratorio');
            if (target) target.innerHTML = data;
            if (window.feather) feather.replace();
        })
        .catch(() => mostrarMensajeNota('No fue posible cargar el detalle del laboratorio.'));
}

function AgregarLaboratorio() {
    const config = notaEditorConfig();
    const fileInput = document.getElementById('Archivo');
    const editor = document.querySelector('#snow3 .ql-editor');
    const file = fileInput && fileInput.files ? fileInput.files[0] : null;
    if (!config || !file || !editor || !recetaTieneContenido(editor)) {
        mostrarMensajeNota('Selecciona un archivo y completa la descripción del laboratorio.');
        return;
    }

    const formData = new FormData();
    formData.append('idPaciente', config.getAttribute('data-id-paciente'));
    formData.append('file', file);
    formData.append('contenidoLaboratorio', editor.innerHTML);
    formData.append('referencia', config.getAttribute('data-referencia'));
    formData.append('titulo', document.getElementById('Titulo').value);
    if (window.jQuery) $('.LoaderPage').show();

    fetch('/clinica/laboratorio/insert-laboratorio', {
        method: 'POST',
        headers: { 'X-M03-CSRF': config.getAttribute('data-laboratorio-csrf') || '' },
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (!data.resultado) throw new Error('No fue posible guardar el laboratorio.');
            tableLaboratorio();
            fileInput.value = '';
            document.getElementById('Titulo').value = '';
            editor.innerHTML = '<p><br></p>';
            mostrarMensajeNota('Laboratorio agregado correctamente.');
        })
        .catch(error => mostrarMensajeNota(error instanceof Error ? error.message : 'No fue posible guardar el laboratorio.'))
        .finally(() => {
            if (window.jQuery) $('.LoaderPage').hide();
        });
}

function AgregarNota() {
    if (notaGuardando) return;
    const config = notaEditorConfig();
    const contenidoEditor = document.querySelector('#snow1 .ql-editor');
    const recetaEditor = document.querySelector('#snow2 .ql-editor');
    if (!config || !contenidoEditor || !recetaEditor) return;

    const incluirReceta = recetaSolicitada();
    const diagnostico = document.getElementById('Diagnostico').value;
    const temperatura = document.getElementById('Temperatura').value;
    const ta = document.getElementById('TA').value;
    const frecCardiaca = document.getElementById('FrecCardiaca').value;
    const spo2 = document.getElementById('Spo2').value;

    if (incluirReceta && (!diagnostico.trim() || !recetaTieneContenido(recetaEditor) || !ta.trim() || !frecCardiaca.trim() || !spo2.trim() || !temperatura.trim())) {
        mostrarMensajeNota('Completa los campos requeridos de la receta antes de guardarla.');
        return;
    }

    const parametros = {
        idPaciente: config.getAttribute('data-id-paciente'),
        referencia: config.getAttribute('data-referencia'),
        contenidoNota: contenidoEditor.innerHTML,
        diagnostico: diagnostico,
        medicamento: recetaEditor.innerHTML,
        incluirReceta: incluirReceta,
        fc: incluirReceta ? frecCardiaca : '',
        temperatura: incluirReceta ? temperatura : '',
        ta: ta,
        frecCardiaca: frecCardiaca,
        pulso: document.getElementById('Pulso').value,
        spo2: spo2,
        fio2: document.getElementById('Fio2').value,
        ecog: document.getElementById('Ecog').value,
        karnovsky: document.getElementById('Karnovsky').value,
        peso: document.getElementById('Peso').value,
        talla: document.getElementById('Talla').value,
        idNota: Number(config.getAttribute('data-id-nota') || '0'),
        idReceta: Number(config.getAttribute('data-idreceta') || '0'),
        proximacita: document.getElementById('FechaProximaCita').value
    };

    const button = document.querySelector('.doctor-note-submit .btn');
    notaGuardando = true;
    if (button) {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    }

    fetch('/clinica/paciente/insert-nota-subsecuente', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-M03-CSRF': config.getAttribute('data-nota-csrf') || ''
        },
        body: JSON.stringify(parametros)
    })
        .then(async response => {
            let data;
            try { data = await response.json(); } catch (error) { throw new Error('El servidor devolvió una respuesta no válida.'); }
            if (!response.ok) {
                const messages = { 401: 'La sesión ya no es válida. Vuelve a iniciar sesión.', 403: 'No tienes autorización para modificar este contexto clínico.', 422: 'Revisa los datos requeridos antes de guardar.', 500: 'El servidor no pudo completar el guardado.' };
                throw new Error((data && typeof data.mensaje === 'string' ? data.mensaje : null) || messages[response.status] || 'No fue posible completar el guardado.');
            }
            return data;
        })
        .then(data => {
            if (!data.resultado) {
                mostrarMensajeNota(typeof data.mensaje === 'string' ? data.mensaje : 'No fue posible guardar la nota.');
                return;
            }
            notaDirty = false;
            const returnIdReceta = Number(data.mensaje && data.mensaje.idReceta ? data.mensaje.idReceta : 0);
            if (returnIdReceta > 0 && typeof imprimirReceta === 'function') imprimirReceta(returnIdReceta, 'registrado');
            const listUrl = document.getElementById('main').getAttribute('data-list-url');
            window.location.href = listUrl + (listUrl.includes('?') ? '&' : '?') + 'saved=1';
        })
        .catch(error => mostrarMensajeNota(error instanceof Error ? error.message : 'No fue posible guardar la nota. Inténtalo nuevamente.'))
        .finally(() => {
            notaGuardando = false;
            if (button) {
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
        });
}

function markNotaDirty() { notaDirty = true; }

function sincronizarCamposReceta() {
    const selector = document.getElementById('IncluirReceta');
    const fields = document.getElementById('doctor-note-recipe-fields');
    if (!selector || !fields) return;
    const visible = selector.checked;
    fields.hidden = !visible;
    fields.setAttribute('aria-hidden', visible ? 'false' : 'true');
}

function sincronizarCamposLaboratorio() {
    const selector = document.getElementById('IncluirLaboratorio');
    const fields = document.getElementById('doctor-note-lab-fields');
    if (!selector || !fields) return;
    const visible = selector.checked;
    fields.hidden = !visible;
    fields.setAttribute('aria-hidden', visible ? 'false' : 'true');
    if (visible && !laboratorioCargado) {
        laboratorioCargado = true;
        tableLaboratorio();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const editorOptions = { theme: 'snow', modules: { toolbar: [['bold', 'italic'], [{ list: 'ordered' }, { list: 'bullet' }]] } };
    ['#snow1', '#snow2', '#snow3'].forEach(selector => {
        if (document.querySelector(selector) && window.Quill) {
            const editor = new Quill(selector, { ...editorOptions, bounds: selector });
            editor.on('text-change', markNotaDirty);
        }
    });

    document.querySelectorAll('#notaSubsecuenteForm input, #notaSubsecuenteForm textarea, #notaSubsecuenteForm select').forEach(element => {
        element.addEventListener('input', markNotaDirty);
        element.addEventListener('change', markNotaDirty);
    });

    const recetaSelector = document.getElementById('IncluirReceta');
    if (recetaSelector) {
        recetaSelector.addEventListener('change', sincronizarCamposReceta);
        sincronizarCamposReceta();
    }

    const laboratorioSelector = document.getElementById('IncluirLaboratorio');
    if (laboratorioSelector) {
        laboratorioSelector.addEventListener('change', sincronizarCamposLaboratorio);
        sincronizarCamposLaboratorio();
    }

    document.querySelectorAll('[data-note-cancel]').forEach(link => link.addEventListener('click', function () {
        notaDirty = false;
    }));

    document.querySelectorAll('[data-note-exit]:not([data-note-cancel])').forEach(link => link.addEventListener('click', function (event) {
        if (notaGuardando || !notaDirty) return;
        if (!window.confirm('Hay cambios sin guardar. ¿Deseas salir de esta nota?')) event.preventDefault();
    }));

    window.addEventListener('beforeunload', function (event) {
        if (notaDirty && !notaGuardando) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    if (window.feather) feather.replace();
});
</script>
</body>
</html>
