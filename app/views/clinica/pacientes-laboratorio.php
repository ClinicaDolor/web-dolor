<?php 
use App\Config\Database;
use App\Models\LaboratorioModel;
use App\Services\ClinicalSummarySecurity;
$bd = Database::getInstance();
$nombrePacienteSeguro = htmlspecialchars((string)($data['nombre_paciente'] ?? ''), ENT_QUOTES, 'UTF-8');
$nombrePacienteVisible = $nombrePacienteSeguro !== '' ? $nombrePacienteSeguro : 'Paciente';
$volverExpedienteUrl = htmlspecialchars(SERVIDOR . 'clinica/paciente/' . (int)$data['idPaciente'], ENT_QUOTES, 'UTF-8');
$laboratorioCsrfToken = ClinicalSummarySecurity::captureCsrfToken((int)$data['idPaciente']);
$laboratorioMaxUploadBytes = LaboratorioModel::MAX_UPLOAD_BYTES;
try {
    $stmt = $bd->query("SELECT * FROM laboratorio WHERE id_paciente = '".$data['idPaciente']."' ORDER BY fecha_hora DESC");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}
$laboratorioCount = count($registros);
$laboratorioCountLabel = $laboratorioCount === 1 ? 'registro' : 'registros';
?>
<!DOCTYPE html>
<html lang="es">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <title><?=$data['title'];?></title>
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/quill/quill.snow.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>
    <script>
    let laboratorioGuardando = false;
    let laboratorioModalTrigger = null;
    let laboratorioDetalleTrigger = null;
    let laboratorioDetalleRequest = 0;

    function actualizarBloqueoScrollLaboratorio() {
        const nuevoModal = document.getElementById('nuevo-laboratorio-modal');
        const detalleModal = document.getElementById('detalle-laboratorio-modal');
        const nuevoModalVisible = nuevoModal && !nuevoModal.hidden;
        const detalleModalVisible = detalleModal && !detalleModal.hidden;
        document.body.classList.toggle('doctor-lab-modal-open', Boolean(nuevoModalVisible || detalleModalVisible));
    }

    function abrirNuevoLaboratorioModal() {
        const modal = document.getElementById('nuevo-laboratorio-modal');
        const closeButton = document.getElementById('closeNuevoLaboratorioButton');
        const trigger = document.getElementById('openNuevoLaboratorioButton');
        if (!modal) {
            return;
        }

        const detalleModal = document.getElementById('detalle-laboratorio-modal');
        if (detalleModal && !detalleModal.hidden) {
            cerrarDetalleLaboratorio();
        }

        laboratorioModalTrigger = trigger || document.activeElement;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        actualizarBloqueoScrollLaboratorio();
        if (closeButton) {
            closeButton.focus();
        }
    }

    function laboratorioFormularioTieneCambios() {
        const tituloInput = document.getElementById('Titulo');
        const fileInput = document.getElementById('Archivo');
        const editor = document.querySelector('#snow .ql-editor');
        const contenido = editor ? editor.innerHTML : '';
        return Boolean(
            (tituloInput && tituloInput.value.trim()) ||
            (fileInput && fileInput.files && fileInput.files.length) ||
            (contenido && contenido !== '<p><br></p>')
        );
    }

    function cerrarModalLaboratorio() {
        const modal = document.getElementById('nuevo-laboratorio-modal');
        if (!modal || modal.hidden || laboratorioGuardando) {
            return;
        }

        if (laboratorioFormularioTieneCambios() && !window.confirm('Hay información sin guardar. ¿Deseas cerrar el formulario?')) {
            return;
        }

        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        actualizarBloqueoScrollLaboratorio();
        if (laboratorioModalTrigger && typeof laboratorioModalTrigger.focus === 'function') {
            laboratorioModalTrigger.focus();
        }
    }

    function cerrarDetalleLaboratorio() {
        const modal = document.getElementById('detalle-laboratorio-modal');
        if (!modal || modal.hidden) {
            return;
        }

        laboratorioDetalleRequest += 1;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');

        const resultsContainer = document.getElementById('detalleLaboratorioModalBody');
        const status = document.getElementById('detalleLaboratorioModalStatus');
        if (resultsContainer) {
            resultsContainer.textContent = '';
            resultsContainer.removeAttribute('aria-busy');
        }
        if (status) {
            status.textContent = '';
        }

        actualizarBloqueoScrollLaboratorio();
        const trigger = laboratorioDetalleTrigger;
        laboratorioDetalleTrigger = null;
        if (trigger && document.contains(trigger) && typeof trigger.focus === 'function') {
            trigger.focus();
            return;
        }

        const table = document.getElementById('table1');
        if (table && typeof table.focus === 'function') {
            table.focus();
        }
    }

    document.addEventListener('keydown', function (event) {
        const detalleModal = document.getElementById('detalle-laboratorio-modal');
        const nuevoModal = document.getElementById('nuevo-laboratorio-modal');
        const modalActivo = detalleModal && !detalleModal.hidden
            ? detalleModal
            : (nuevoModal && !nuevoModal.hidden ? nuevoModal : null);

        if (!modalActivo) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            if (modalActivo === detalleModal) {
                cerrarDetalleLaboratorio();
            } else {
                cerrarModalLaboratorio();
            }
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const elementosEnfocables = Array.from(modalActivo.querySelectorAll(
            'button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        ));
        if (!elementosEnfocables.length) {
            return;
        }

        const primero = elementosEnfocables[0];
        const ultimo = elementosEnfocables[elementosEnfocables.length - 1];
        if (event.shiftKey && document.activeElement === primero) {
            event.preventDefault();
            ultimo.focus();
        } else if (!event.shiftKey && document.activeElement === ultimo) {
            event.preventDefault();
            primero.focus();
        }
    });

    function marcarCampoLaboratorio(elemento, invalido) {
        if (!elemento) {
            return;
        }

        elemento.style.border = invalido ? '2px solid #d44e31' : '';
        elemento.setAttribute('aria-invalid', invalido ? 'true' : 'false');
    }

    function eliminarSeparadorFechaHoraLaboratorio(resultsContainer) {
        if (!resultsContainer) {
            return;
        }

        const labels = Array.from(resultsContainer.querySelectorAll('label.text-primary'));
        const fechaLabel = labels.find(label => label.textContent.trim().startsWith('Fecha:'));
        const horaLabel = labels.find(label => label.textContent.trim().startsWith('Hora:'));
        const fechaValor = fechaLabel ? fechaLabel.nextElementSibling : null;
        if (!fechaValor || !horaLabel) {
            return;
        }

        let separador = fechaValor.nextSibling;
        while (separador && separador !== horaLabel) {
            const siguiente = separador.nextSibling;
            if (separador.nodeType === Node.TEXT_NODE && /^\s*,\s*$/.test(separador.nodeValue || '')) {
                separador.nodeValue = separador.nodeValue.replace(',', '');
                return;
            }
            separador = siguiente;
        }
    }

    function AgregarLaboratorio(idPaciente) {
        if (laboratorioGuardando) {
            return;
        }

        const referencia = 0;
        const tituloInput = document.getElementById('Titulo');
        const fileInput = document.getElementById('Archivo');
        const editor = document.querySelector('#snow .ql-editor');
        const mensaje = document.getElementById('mensaje');
        const submitButton = document.getElementById('agregarLaboratorioButton');
        const titulo = tituloInput ? tituloInput.value : '';
        const file = fileInput && fileInput.files ? fileInput.files[0] : null;
        const contenidoLaboratorio = editor ? editor.innerHTML : '';
        const editorVacio = !contenidoLaboratorio || contenidoLaboratorio === '<p><br></p>';

        marcarCampoLaboratorio(fileInput, !file);
        marcarCampoLaboratorio(editor, editorVacio);

        if (!file || editorVacio) {
            if (mensaje) {
                mensaje.textContent = 'Selecciona un archivo y agrega una descripción para continuar.';
            }
            return;
        }

        if (file.size > <?=$laboratorioMaxUploadBytes;?>) {
            marcarCampoLaboratorio(fileInput, true);
            if (mensaje) {
                mensaje.textContent = 'El archivo no puede superar los 10 MiB.';
            }
            return;
        }

        const formData = new FormData();
        formData.append('idPaciente', idPaciente);
        formData.append('file', file);
        formData.append('contenidoLaboratorio', contenidoLaboratorio);
        formData.append('referencia', referencia);
        formData.append('titulo', titulo);

        laboratorioGuardando = true;
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
        }
        if (mensaje) {
            mensaje.textContent = 'Guardando laboratorio…';
        }
        const laboratorioMain = document.getElementById('main');

        fetch('/clinica/laboratorio/insert-laboratorio', {
            method: 'POST',
            headers: {
                'X-M03-CSRF': laboratorioMain ? laboratorioMain.getAttribute('data-laboratorio-csrf') || '' : ''
            },
            body: formData
        })
            .then(response => response.json().then(data => ({ok: response.ok, data})))
            .then(({ok, data}) => {
                if (ok && data.resultado) {
                    location.reload();
                    return;
                }

                throw new Error(data.mensaje || 'No fue posible guardar el laboratorio.');
            })
            .catch(error => {
                if (mensaje) {
                    mensaje.textContent = 'Error: ' + error.message;
                }
                laboratorioGuardando = false;
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.removeAttribute('aria-busy');
                }
            });
    }

    function DetalleLaboratorio(idLaboratorio, trigger) {
        const recordId = Number.parseInt(String(idLaboratorio), 10);
        const modal = document.getElementById('detalle-laboratorio-modal');
        const closeButton = document.getElementById('closeDetalleLaboratorioButton');
        const resultsContainer = document.getElementById('detalleLaboratorioModalBody');
        const status = document.getElementById('detalleLaboratorioModalStatus');
        const nuevoModal = document.getElementById('nuevo-laboratorio-modal');
        if (!modal || !resultsContainer || !status || !Number.isInteger(recordId) || recordId < 1 || (nuevoModal && !nuevoModal.hidden)) {
            return;
        }

        laboratorioDetalleTrigger = trigger || document.activeElement;
        const requestId = ++laboratorioDetalleRequest;
        resultsContainer.textContent = '';
        resultsContainer.setAttribute('aria-busy', 'true');
        status.textContent = 'Cargando detalle del laboratorio…';
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        actualizarBloqueoScrollLaboratorio();
        if (closeButton) {
            closeButton.focus();
        }

        fetch(`/buscar/laboratorio/${encodeURIComponent(recordId)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor: ' + response.status);
                }
                return response.text();
            })
            .then(data => {
                if (requestId !== laboratorioDetalleRequest) {
                    return;
                }

                resultsContainer.innerHTML = data;
                eliminarSeparadorFechaHoraLaboratorio(resultsContainer);
                status.textContent = '';
                if (window.feather) {
                    feather.replace();
                }
            })
            .catch(() => {
                if (requestId !== laboratorioDetalleRequest) {
                    return;
                }

                resultsContainer.textContent = '';
                status.textContent = 'No fue posible cargar el detalle del laboratorio.';
            })
            .finally(() => {
                if (requestId !== laboratorioDetalleRequest) {
                    return;
                }

                resultsContainer.removeAttribute('aria-busy');
            });
    }

    function activarDetalleLaboratorioDesdeTabla(event) {
        const elemento = event.target instanceof Element ? event.target : null;
        const fila = elemento ? elemento.closest('tbody tr[data-laboratorio-id]') : null;
        if (!fila || !event.currentTarget.contains(fila) || elemento.closest('a, button, input, select, textarea')) {
            return;
        }

        if (event.type === 'keydown') {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
        }

        DetalleLaboratorio(fila.dataset.laboratorioId, fila);
    }

    function registrarEventosTablaLaboratorio(table) {
        if (!table || table.dataset.detailEventsBound === 'true') {
            return;
        }

        table.addEventListener('click', activarDetalleLaboratorioDesdeTabla);
        table.addEventListener('keydown', activarDetalleLaboratorioDesdeTabla);
        table.dataset.detailEventsBound = 'true';
    }

    </script>

 </head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-lab-page">
<div class="LoaderPage"></div>
    <div id="app">
        
        <?=$data['sidebar'];?>

        <div id="main"
             data-paciente="<?= (int)$data['idPaciente'];?>"
             data-laboratorio-csrf="<?=htmlspecialchars($laboratorioCsrfToken, ENT_QUOTES, 'UTF-8');?>">
            <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
            
            <div class="main-content container-fluid">

            <header class="doctor-page-hero doctor-lab-hero">
                <div class="doctor-page-hero-copy">
                    <p class="dashboard-eyebrow">Control clínico</p>
                    <h1><?=htmlspecialchars((string)$data['title'], ENT_QUOTES, 'UTF-8');?></h1>
                    <p>Consulta los resultados de laboratorio y agrega nueva documentación de <?=$nombrePacienteVisible;?>.</p>
                </div>
                <div class="doctor-page-hero-actions">
                    <a class="btn doctor-page-hero-action" data-doctor-back-action href="<?=$volverExpedienteUrl;?>">
                        <i data-feather="arrow-left" aria-hidden="true"></i>
                        <span>Volver al expediente</span>
                    </a>
                </div>
            </header>

            <section class="doctor-lab-patient-context" aria-label="Contexto del paciente">
                <div class="doctor-lab-patient-strip">
                    <span class="doctor-lab-patient-icon" aria-hidden="true">
                        <i data-feather="user"></i>
                    </span>
                    <div class="doctor-lab-patient-context-copy">
                        <p class="dashboard-eyebrow">EXPEDIENTE CLÍNICO</p>
                        <h2><?=$nombrePacienteVisible;?></h2>
                        <p>Expediente #<?=htmlspecialchars((string)$data['idPaciente'], ENT_QUOTES, 'UTF-8');?></p>
                    </div>
                </div>
            </section>

            <section aria-labelledby="laboratory-section-title">
                <div class="row doctor-lab-workspace">
                    <div class="col-12 doctor-lab-list-column">
                        <div class="card doctor-lab-card doctor-lab-list-card">
                            <div class="card-header doctor-lab-section-header">
                                <div class="doctor-lab-section-heading">
                                    <p class="dashboard-eyebrow">REGISTROS DISPONIBLES</p>
                                    <h2 id="laboratory-section-title" class="card-title">Laboratorios</h2>
                                    <p id="laboratoryCount" class="doctor-lab-count" aria-live="polite">
                                        <?=$laboratorioCount;?> <?=$laboratorioCountLabel;?>
                                    </p>
                                </div>
                                <button id="openNuevoLaboratorioButton" type="button" class="btn btn-success doctor-lab-new-button" aria-haspopup="dialog" aria-controls="nuevo-laboratorio-modal" onclick="abrirNuevoLaboratorioModal()">
                                    Nuevo laboratorio <i data-feather="plus" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="doctor-lab-empty-state" <?= $laboratorioCount > 0 ? 'hidden' : ''; ?> role="status">
                                    <i data-feather="file-text" aria-hidden="true"></i>
                                    <p>No hay estudios de laboratorio registrados.</p>
                                </div>
                                <div class="doctor-lab-table-region" <?= $laboratorioCount > 0 ? '' : 'hidden'; ?> >
                                    <table class="table table-striped table-hover table-sm pb-0 mb-0" id="table1" data-has-records="<?= $laboratorioCount > 0 ? 'true' : 'false'; ?>" aria-describedby="laboratoryCount">
                                        <thead>
                                            <tr>
                                                <th class="text-center">#</th>
                                                <th>Fecha y hora</th>
                                                <th>Título</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($registros as $registro):
                                            $laboratorioId = (int)$registro['id'];
                                            $tituloLaboratorio = htmlspecialchars((string)($registro['titulo'] ?? 'Sin título'), ENT_QUOTES, 'UTF-8');
                                            $etiquetaFila = htmlspecialchars('Ver laboratorio: ' . ((string)($registro['titulo'] ?? 'Sin título')), ENT_QUOTES, 'UTF-8');
                                        ?>
                                            <tr role="button" tabindex="0" data-laboratorio-id="<?=$laboratorioId;?>" aria-label="<?=$etiquetaFila;?>" aria-haspopup="dialog" aria-controls="detalle-laboratorio-modal">
                                                <td class="text-center"><?=$laboratorioId;?></td>
                                                <td><?=(new DateTime(datetime: $registro['fecha_hora']))->format('d/m/Y h:i a');?></td>
                                                <td><?=$tituloLaboratorio;?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <div id="nuevo-laboratorio-modal" class="doctor-lab-modal" role="dialog" aria-modal="true" aria-labelledby="nuevo-laboratorio-modal-title" aria-hidden="true" hidden>
                <div class="doctor-lab-modal-content">
                    <div class="doctor-lab-modal-header">
                        <div>
                            <p class="dashboard-eyebrow">REGISTRO CLÍNICO</p>
                            <h2 id="nuevo-laboratorio-modal-title">Nuevo laboratorio</h2>
                        </div>
                        <button id="closeNuevoLaboratorioButton" type="button" class="doctor-lab-modal-close" aria-label="Cerrar" title="Cerrar" onclick="cerrarModalLaboratorio()">
                            <i data-feather="x" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="doctor-lab-modal-body">
                        <div class="doctor-lab-field">
                            <label class="doctor-lab-field-label" for="Titulo">Título</label>
                            <input type="text" class="form-control" id="Titulo">
                        </div>

                        <div class="doctor-lab-field">
                            <label class="doctor-lab-field-label" for="Archivo">Archivo</label>
                            <input type="file" class="form-control" id="Archivo" accept="application/pdf,.pdf">
                            <small class="doctor-lab-field-help">PDF, máximo 10 MiB.</small>
                        </div>

                        <div class="doctor-lab-field">
                            <label class="doctor-lab-field-label" for="snow">Descripción</label>
                            <div id="snow" class="editor"></div>
                        </div>

                        <div id="mensaje" role="alert" aria-live="polite"></div>
                    </div>
                    <div class="doctor-lab-modal-actions">
                        <button type="button" class="btn doctor-lab-modal-cancel" onclick="cerrarModalLaboratorio()">Cancelar</button>
                        <button id="agregarLaboratorioButton" type="button" class="btn btn-success" onclick="AgregarLaboratorio(<?=$data['idPaciente'];?>)">
                            Guardar laboratorio <i data-feather="check" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div id="detalle-laboratorio-modal" class="doctor-lab-modal" role="dialog" aria-modal="true" aria-labelledby="detalleLaboratorioModalTitle" aria-describedby="detalleLaboratorioModalStatus" aria-hidden="true" hidden>
                <div class="doctor-lab-modal-content doctor-lab-detail-modal-content">
                    <div class="doctor-lab-modal-header">
                        <div>
                            <p class="dashboard-eyebrow">REGISTRO CLÍNICO</p>
                            <h2 id="detalleLaboratorioModalTitle">Detalle del laboratorio</h2>
                        </div>
                        <button id="closeDetalleLaboratorioButton" type="button" class="doctor-lab-modal-close" aria-label="Cerrar" title="Cerrar" onclick="cerrarDetalleLaboratorio()">
                            <i data-feather="x" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="doctor-lab-modal-body doctor-lab-detail-modal-body">
                        <p id="detalleLaboratorioModalStatus" class="doctor-lab-modal-status" role="status" aria-live="polite"></p>
                        <div id="detalleLaboratorioModalBody" class="doctor-lab-detail-content" aria-live="polite"></div>
                    </div>
                </div>
            </div>


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
    <script src="<?=RUTA_JS?>search-main.js"></script>
    
    <script>

    const table1 = document.querySelector('#table1');
    registrarEventosTablaLaboratorio(table1);
    if (table1 && table1.dataset.hasRecords === 'true' && window.simpleDatatables) {
        new simpleDatatables.DataTable(table1, {
            searchable: true,
            fixedHeight: false,
            perPageSelect: false,
            columns: [
                {select: 1, sort: 'desc'}
            ]
        });
    }

    const snowElement = document.querySelector('#snow');
    if (snowElement && window.Quill) {
        new Quill('#snow', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic'],
                    [{list: 'ordered'}, {list: 'bullet'}]
                ]
            },
            bounds: '#snow'
        });
    }

    </script>
</body>
</html>

