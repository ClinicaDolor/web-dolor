<?php 
use App\Config\Database;
use App\Models\CofeprisModel;
$bd = Database::getInstance();

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$folio = '<div id="cofeprisFolio" class="doctor-patient-cofepris-folio"'.($data['id_cofepris'] == 0 ? ' hidden' : '').'><small>Folio:</small> <span>'.$escape($data['folio']).'</span></div>';

$fechaOriginal = ($data['id_cofepris'] == 0)? date('d/m/Y') : $data['fecha'];
$fechaHora = DateTime::createFromFormat('d/m/Y', $fechaOriginal);
$fechaFormateada = $fechaHora->format('Y-m-d');

$sub_text = ($data['id_cofepris'] == 0)? 'Nuevo Registro': 'Editar Registro';
$boton_titulo = ($data['id_cofepris'] == 0)? 'Agregar': 'Editar';
try {
    $stmt = $bd->query("SELECT * FROM cofepris WHERE id_paciente = '".$data['idPaciente']."' ORDER BY fecha_hora DESC");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}
$cofeprisCount = count($registros);
$cofeprisCountLabel = $cofeprisCount === 1 ? 'registro' : 'registros';
?>
<!DOCTYPE html>
<html lang="en">
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
    let cofeprisDataTable = null;
    let cofeprisModalTrigger = null;
    let cofeprisDetailTrigger = null;
    let cofeprisDetailRequest = 0;
    let cofeprisFormRequest = 0;
    const cofeprisFormFieldIds = ['Fecha', 'Diagnostico', 'Medicamento', 'NumCajas', 'Presentacion', 'Dosificacion', 'NumDias', 'ViaAdministracion'];

    function actualizarBloqueoScrollCofepris() {
        const formModal = document.getElementById('cofepris-form-modal');
        const detailModal = document.getElementById('detalle-cofepris-modal');
        const modalVisible = (formModal && !formModal.hidden) || (detailModal && !detailModal.hidden);
        document.body.classList.toggle('doctor-cofepris-modal-open', Boolean(modalVisible));
    }

    function abrirCofeprisFormModal(triggerElement = null) {
        const modal = document.getElementById('cofepris-form-modal');
        const closeButton = document.getElementById('closeCofeprisFormButton');
        const trigger = triggerElement || document.getElementById('openNuevoCofeprisButton');
        if (!modal) return;

        cofeprisFormRequest += 1;
        const detailModal = document.getElementById('detalle-cofepris-modal');
        if (detailModal && !detailModal.hidden) cerrarDetalleCofepris();

        cofeprisModalTrigger = trigger || document.activeElement;
        modal.dataset.directEdit = 'false';
        modal.dataset.internalEdit = 'false';
        const defaultDate = document.getElementById('Fecha');
        cofeprisFormFieldIds.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (!field) return;
            field.value = fieldId === 'Fecha' ? (defaultDate?.dataset.newDefault || '') : '';
        });
        const folio = document.getElementById('cofeprisFolio');
        if (folio) {
            folio.hidden = true;
            folio.textContent = '';
        }
        const patientId = Number.parseInt(document.getElementById('main')?.dataset.paciente || '', 10);
        const submitButton = document.querySelector('#cofepris-form-modal .doctor-patient-cofepris-submit');
        if (submitButton && Number.isInteger(patientId)) submitButton.setAttribute('onclick', `AgregarCofepris(${patientId},0)`);
        const formTitle = document.getElementById('cofeprisFormModalTitle');
        if (formTitle) formTitle.textContent = 'Nuevo Registro COFEPRIS';
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        actualizarBloqueoScrollCofepris();
        if (closeButton) closeButton.focus();
    }

    function cerrarCofeprisFormModal() {
        const modal = document.getElementById('cofepris-form-modal');
        if (!modal || modal.hidden) return false;

        cofeprisFormRequest += 1;
        const directEdit = modal.dataset.directEdit === 'true';
        const listingUrl = modal.dataset.listingUrl || '';
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');

        if (directEdit && listingUrl && window.history && typeof window.history.replaceState === 'function') {
            const target = new URL(listingUrl, window.location.href);
            const targetUrl = target.pathname + target.search + target.hash;
            const currentUrl = window.location.pathname + window.location.search + window.location.hash;
            if (currentUrl !== targetUrl) window.history.replaceState(window.history.state, document.title, targetUrl);
        }
        modal.dataset.directEdit = 'false';
        actualizarBloqueoScrollCofepris();
        const trigger = cofeprisModalTrigger;
        cofeprisModalTrigger = null;
        if (trigger && document.contains(trigger) && typeof trigger.focus === 'function') trigger.focus();
        return true;
    }

    function abrirEdicionCofepris(link) {
        const modal = document.getElementById('cofepris-form-modal');
        if (!modal || !link || !link.href) return;

        cofeprisFormRequest += 1;
        const requestId = cofeprisFormRequest;
        cofeprisModalTrigger = link;
        const initialStatus = document.getElementById('cofeprisFormModalStatus');
        if (initialStatus) initialStatus.textContent = 'Cargando edición de COFEPRIS…';

        fetch(link.href, {credentials: 'same-origin'})
            .then(response => {
                if (!response.ok) throw new Error('No fue posible cargar la edición de COFEPRIS.');
                return response.text();
            })
            .then(html => {
                if (requestId !== cofeprisFormRequest) return;
                const parsed = new DOMParser().parseFromString(html, 'text/html');
                const sourceModal = parsed.querySelector('#cofepris-form-modal');
                const sourceSubmit = sourceModal ? sourceModal.querySelector('.doctor-patient-cofepris-submit') : null;
                const patientId = Number.parseInt(sourceSubmit?.dataset.patientId || '', 10);
                const recordId = Number.parseInt(sourceSubmit?.dataset.cofeprisId || '', 10);
                if (!sourceModal || !sourceSubmit || !Number.isInteger(patientId) || !Number.isInteger(recordId) || recordId < 1) {
                    throw new Error('La respuesta de edición no contiene un registro válido.');
                }

                cofeprisFormFieldIds.forEach(fieldId => {
                    const sourceField = parsed.getElementById(fieldId);
                    const targetField = document.getElementById(fieldId);
                    if (!sourceField || !targetField) throw new Error('Falta un campo de edición de COFEPRIS.');
                    targetField.value = sourceField.value;
                });

                const sourceTitle = parsed.getElementById('cofeprisFormModalTitle');
                const targetTitle = document.getElementById('cofeprisFormModalTitle');
                if (sourceTitle && targetTitle) targetTitle.textContent = sourceTitle.textContent;

                const sourceFolio = parsed.getElementById('cofeprisFolio');
                const targetFolio = document.getElementById('cofeprisFolio');
                if (targetFolio) {
                    targetFolio.textContent = sourceFolio ? sourceFolio.textContent : '';
                    targetFolio.hidden = !sourceFolio || sourceFolio.hidden;
                }

                const submitButton = document.querySelector('#cofepris-form-modal .doctor-patient-cofepris-submit');
                if (submitButton) submitButton.setAttribute('onclick', `AgregarCofepris(${patientId},${recordId})`);
                modal.dataset.directEdit = 'false';
                modal.dataset.internalEdit = 'true';
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                actualizarBloqueoScrollCofepris();
                const status = document.getElementById('cofeprisFormModalStatus');
                if (status) status.textContent = '';
                const closeButton = document.getElementById('closeCofeprisFormButton');
                if (closeButton) closeButton.focus();
            })
            .catch(() => {
                if (requestId !== cofeprisFormRequest) return;
                const status = document.getElementById('cofeprisFormModalStatus');
                if (status) status.textContent = 'No fue posible cargar la edición de COFEPRIS.';
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                actualizarBloqueoScrollCofepris();
                const closeButton = document.getElementById('closeCofeprisFormButton');
                if (closeButton) closeButton.focus();
            });
    }

    function cerrarDetalleCofepris() {
        const modal = document.getElementById('detalle-cofepris-modal');
        if (!modal || modal.hidden) return false;

        cofeprisDetailRequest += 1;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        const resultsContainer = document.getElementById('detalleCofepris');
        const status = document.getElementById('detalleCofeprisModalStatus');
        if (resultsContainer) {
            resultsContainer.textContent = '';
            resultsContainer.removeAttribute('aria-busy');
        }
        if (status) status.textContent = '';
        actualizarBloqueoScrollCofepris();

        const trigger = cofeprisDetailTrigger;
        cofeprisDetailTrigger = null;
        if (trigger && document.contains(trigger) && typeof trigger.focus === 'function') trigger.focus();
        return true;
    }

    function prepararFilasCofepris(table) {
        if (!table) return;
        table.querySelectorAll('tbody a[href*="/editar/"]').forEach(link => {
            link.classList.add('doctor-patient-cofepris-edit');
        });
        table.querySelectorAll('tbody tr').forEach(row => {
            const inlineHandler = row.getAttribute('onclick') || '';
            const inlineId = inlineHandler.match(/DetalleCofepris\s*\(\s*(\d+)/);
            const rowId = row.dataset.cofeprisId || (row.id.match(/^cofepris(\d+)$/) || [])[1] || (inlineId ? inlineId[1] : '');
            if (!rowId) return;
            row.dataset.cofeprisId = rowId;
            row.removeAttribute('onclick');
            row.setAttribute('role', 'button');
            row.setAttribute('tabindex', '0');
            row.setAttribute('aria-haspopup', 'dialog');
            row.setAttribute('aria-controls', 'detalle-cofepris-modal');
            row.setAttribute('aria-label', 'Abrir detalle de COFEPRIS');
        });

        if (table.dataset.detailEventsBound === 'true') return;
        table.addEventListener('click', event => {
            const element = event.target instanceof Element ? event.target : null;
            const editLink = element ? element.closest('a.doctor-patient-cofepris-edit') : null;
            if (!editLink || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            abrirEdicionCofepris(editLink);
        });
        table.addEventListener('click', event => {
            const element = event.target instanceof Element ? event.target : null;
            const row = element ? element.closest('tbody tr[data-cofepris-id]') : null;
            if (!row || (element && element.closest('a, button, input, select, textarea'))) return;
            DetalleCofepris(row.dataset.cofeprisId, row);
        });
        table.addEventListener('keydown', event => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            const element = event.target instanceof Element ? event.target : null;
            const row = element ? element.closest('tbody tr[data-cofepris-id]') : null;
            if (!row) return;
            event.preventDefault();
            DetalleCofepris(row.dataset.cofeprisId, row);
        });
        table.dataset.detailEventsBound = 'true';
    }

    function sincronizarConteoCofepris(table) {
        const countValue = document.getElementById('cofeprisCountValue');
        const countLabel = document.getElementById('cofeprisCountLabel');
        if (!countValue || !countLabel) return;
        const total = table && table.tBodies.length ? table.tBodies[0].rows.length : 0;
        countValue.textContent = total;
        countLabel.textContent = total === 1 ? 'registro' : 'registros';
    }

window.addEventListener("pageshow", () => {
        tableCofepris()
    });

    document.addEventListener("DOMContentLoaded", function() {
        const initialTable = document.querySelector('#tableCofepris');
        prepararFilasCofepris(initialTable);
        sincronizarConteoCofepris(initialTable);
        actualizarBloqueoScrollCofepris();
        tableCofepris();
    });


        function AgregarCofepris(idPaciente,idCofepris){

            const Fecha = document.getElementById('Fecha').value;
            const Diagnostico = document.getElementById('Diagnostico').value;
            const Medicamento = document.getElementById('Medicamento').value;
            const NumCajas = document.getElementById('NumCajas').value;
            const Presentacion = document.getElementById('Presentacion').value;
            const Dosificacion = document.getElementById('Dosificacion').value;
            const NumDias = document.getElementById('NumDias').value;
            const ViaAdministracion = document.getElementById('ViaAdministracion').value;

           
            if(Diagnostico != ""){

            const parametros = {
            idPaciente : idPaciente,
            idCofepris : idCofepris,
            fecha : Fecha,
            diagnostico : Diagnostico,
            medicamento : Medicamento,
            numCajas : NumCajas,
            presentacion : Presentacion,
            dosificacion : Dosificacion,
            numDias : NumDias,
            viaAdministracion : ViaAdministracion
            };

            fetch('/clinica/paciente/insert-cofepris', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(parametros)
            })
            .then(response => response.json())
            .then(data => {

            if (data.resultado) {

                const id = data.mensaje;

                tableCofepris();
                cerrarCofeprisFormModal();
                DetalleCofepris(id);
                
                if(idCofepris == 0){
                document.getElementById('Fecha').value = "";
                document.getElementById('Diagnostico').value = "";
                document.getElementById('Medicamento').value = "";
                document.getElementById('NumCajas').value = "";
                document.getElementById('Presentacion').value = "";
                document.getElementById('Dosificacion').value = "";
                document.getElementById('NumDias').value = "";
                document.getElementById('ViaAdministracion').value = "";
                }

                window.open('/pdf/cofepris/' + id, '_blank');
                

            } else {
                document.getElementById('mensaje').textContent = 'Mensaje: ' + data.mensaje;
            }
          
        });

    }else{
        document.querySelector('#Diagnostico').style.border = "2px solid #d44e31";
    }

    }

    function DetalleCofepris(idReceta, trigger = null){
        const recordId = Number.parseInt(String(idReceta), 10);
        const modal = document.getElementById('detalle-cofepris-modal');
        const closeButton = document.getElementById('closeDetalleCofeprisButton');
        const resultsContainer = document.getElementById('detalleCofepris');
        const status = document.getElementById('detalleCofeprisModalStatus');
        if (!modal || !resultsContainer || !status || !Number.isInteger(recordId) || recordId < 1) return;
        const formModal = document.getElementById('cofepris-form-modal');
        if (formModal && !formModal.hidden) cerrarCofeprisFormModal();

        cofeprisDetailTrigger = trigger || document.activeElement;
        const requestId = ++cofeprisDetailRequest;
        resultsContainer.textContent = '';
        resultsContainer.setAttribute('aria-busy', 'true');
        status.textContent = 'Cargando detalle de COFEPRIS…';
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        actualizarBloqueoScrollCofepris();
        if (closeButton) closeButton.focus();

        fetch(`/buscar/cofepris/${encodeURIComponent(recordId)}`)
                .then(response => {
                if (!response.ok) {
                throw new Error('Error en la respuesta del servidor: ' + response.status);
                }
                return response.text();
                })
                .then(data => {
                    if (requestId !== cofeprisDetailRequest) return;
                    const resultsContainer = document.getElementById('detalleCofepris');
                    resultsContainer.innerHTML = data;
                    const status = document.getElementById('detalleCofeprisModalStatus');
                    if (status) status.textContent = '';
                    if (window.feather) feather.replace();
                })
                .catch(() => {
                    if (requestId !== cofeprisDetailRequest) return;
                    resultsContainer.textContent = '';
                    status.textContent = 'No fue posible cargar el detalle de COFEPRIS.';
                })
                .finally(() => {
                    if (requestId === cofeprisDetailRequest) resultsContainer.removeAttribute('aria-busy');
                });
    } 

    function tableCofepris(){

        const usuarioDiv = document.getElementById('main');
        const idPaciente = usuarioDiv.getAttribute('data-paciente');

        fetch(`/buscar/tabla-cofepris/${encodeURIComponent(idPaciente)}`)
                .then(response => {
                if (!response.ok) {
                throw new Error('Error en la respuesta del servidor: ' + response.status);
                }
                return response.text();
                })
                .then(data => {

                    const resultsContainer = document.getElementById('conteCofepris');
                    if (cofeprisDataTable && typeof cofeprisDataTable.destroy === 'function') {
                        cofeprisDataTable.destroy();
                    }
                    cofeprisDataTable = null;
                    resultsContainer.innerHTML = data;
                    if (window.feather) feather.replace();

                    const tabla = resultsContainer.querySelector('#tableCofepris');
                    const hasRows = Boolean(tabla && tabla.tBodies.length && tabla.tBodies[0].rows.length);
                    const emptyState = document.getElementById('cofeprisEmptyState');
                    if (emptyState) emptyState.hidden = hasRows;
                    if (tabla) tabla.hidden = !hasRows;
                    sincronizarConteoCofepris(tabla);

                    if (tabla && hasRows) {
                        prepararFilasCofepris(tabla);
                        cofeprisDataTable = new simpleDatatables.DataTable(tabla, {
                            searchable: true,
                            fixedHeight: false,
                            perPageSelect: false,
                            columns: [
                                {select: 1, sort: 'desc'},
                                {select: [3], sortable: false}
                            ]
                        });
                    }
        });
    }

    document.addEventListener('keydown', event => {
        const detailModal = document.getElementById('detalle-cofepris-modal');
        const formModal = document.getElementById('cofepris-form-modal');
        const modal = detailModal && !detailModal.hidden ? detailModal : (formModal && !formModal.hidden ? formModal : null);
        if (!modal || event.key !== 'Escape') return;
        event.preventDefault();
        if (modal === detailModal) cerrarDetalleCofepris();
        else cerrarCofeprisFormModal();
    });

    </script>

 </head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-cofepris-page">
<div class="LoaderPage"></div>
    <div id="app">
        
        <?=$data['sidebar'];?>

        <div id="main" data-paciente="<?=$data['idPaciente'];?>">
            
            <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
            
            <main class="main-content container-fluid doctor-patient-cofepris-content">
            <header class="doctor-page-hero doctor-patient-cofepris-hero">
                <div class="doctor-page-hero-copy">
                    <p class="dashboard-eyebrow">CONTROL REGULATORIO</p>
                    <h1>COFEPRIS</h1>
                    <p>Consulta y gestiona los registros COFEPRIS del paciente autorizado.</p>
                </div>
                <div class="doctor-page-hero-actions doctor-patient-cofepris-hero-actions">
                    <a href="<?=SERVIDOR?>clinica/paciente/<?=$data['idPaciente'];?>" class="btn doctor-page-hero-action" data-doctor-back-action>
                        <i data-feather="arrow-left" aria-hidden="true"></i>
                        <span>Volver al expediente</span>
                    </a>
                </div>
            </header>

            <section class="doctor-patient-cofepris-patient-context" aria-label="Contexto del paciente">
                <div class="doctor-patient-cofepris-patient-strip">
                    <span class="doctor-patient-cofepris-patient-icon" aria-hidden="true"><i data-feather="user"></i></span>
                    <div class="doctor-patient-cofepris-patient-copy">
                        <p class="dashboard-eyebrow">EXPEDIENTE CLÍNICO</p>
                        <h2><?=$escape($data['nombre_paciente']);?></h2>
                        <p>Expediente #<?=htmlspecialchars((string)(int)$data['idPaciente'], ENT_QUOTES, 'UTF-8');?></p>
                    </div>
                </div>
            </section>

            <section class="doctor-patient-cofepris-section" aria-labelledby="cofepris-list-title">
                <div class="row doctor-patient-cofepris-layout">
                    <div class="col-12">
                        <div class="card doctor-patient-cofepris-card doctor-patient-cofepris-list-card">
                            <div class="card-header doctor-patient-cofepris-card-header">
                                <div class="doctor-patient-cofepris-list-heading">
                                    <p class="dashboard-eyebrow">REGISTROS DISPONIBLES</p>
                                    <h2 id="cofepris-list-title" class="card-title">COFEPRIS</h2>
                                    <p class="doctor-patient-cofepris-count" aria-live="polite"><strong id="cofeprisCountValue"><?=$cofeprisCount;?></strong> <span id="cofeprisCountLabel"><?=$cofeprisCountLabel;?></span></p>
                                </div>
                                <button id="openNuevoCofeprisButton" type="button" class="btn btn-success doctor-patient-cofepris-new-button" aria-haspopup="dialog" aria-controls="cofepris-form-modal" onclick="abrirCofeprisFormModal()">
                                    Nuevo registro <i data-feather="plus" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="card-body doctor-patient-cofepris-card-body">
                                <div id="conteCofepris" class="doctor-patient-cofepris-table-wrap">
                                    <?php if ($cofeprisCount > 0): ?>
                                    <table class="table table-striped table-hover table-sm doctor-patient-cofepris-table" id="tableCofepris" aria-describedby="cofeprisCountValue">
                                        <thead>
                                            <tr>
                                                <th class="text-center" scope="col">#</th>
                                                <th scope="col">Fecha y hora</th>
                                                <th scope="col">Folio</th>
                                                <th class="text-center" scope="col">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($registros as $registro):
                                            $cofeprisId = (int)$registro['id'];
                                            $cofeprisEditUrl = htmlspecialchars(SERVIDOR . 'clinica/cofepris/paciente/' . (int)$data['idPaciente'] . '/editar/' . $cofeprisId, ENT_QUOTES, 'UTF-8');
                                            $cofeprisRowLabel = htmlspecialchars('Abrir detalle de COFEPRIS ' . $cofeprisId, ENT_QUOTES, 'UTF-8');
                                        ?>
                                            <tr id="cofepris<?=$cofeprisId;?>" role="button" tabindex="0" data-cofepris-id="<?=$cofeprisId;?>" aria-label="<?=$cofeprisRowLabel;?>" aria-haspopup="dialog" aria-controls="detalle-cofepris-modal">
                                                <td class="text-center"><?=$cofeprisId;?></td>
                                                <td><?=(new DateTime(datetime: $registro['fecha_hora']))->format('d/m/Y h:i a');?></td>
                                                <td><?=$escape($registro['folio']);?></td>
                                                <td class="text-center"><a class="doctor-patient-cofepris-edit" href="<?=$cofeprisEditUrl;?>" aria-label="Editar registro COFEPRIS <?=$cofeprisId;?>"><i data-feather="edit-2" aria-hidden="true"></i></a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                                <div id="cofeprisEmptyState" class="doctor-patient-cofepris-empty" <?= $cofeprisCount > 0 ? 'hidden' : ''; ?> role="status">
                                    <i data-feather="file-text" aria-hidden="true"></i>
                                    <p>No hay registros COFEPRIS disponibles.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div id="detalle-cofepris-modal" class="doctor-patient-cofepris-modal" role="dialog" aria-modal="true" aria-labelledby="detalleCofeprisModalTitle" aria-describedby="detalleCofeprisModalStatus" aria-hidden="true" hidden>
                <div class="doctor-patient-cofepris-modal-content">
                    <div class="doctor-patient-cofepris-modal-header">
                        <div>
                            <p class="dashboard-eyebrow">REGISTRO REGULATORIO</p>
                            <h2 id="detalleCofeprisModalTitle">Detalle de COFEPRIS</h2>
                        </div>
                        <button id="closeDetalleCofeprisButton" type="button" class="doctor-patient-cofepris-modal-close" aria-label="Cerrar" title="Cerrar" onclick="cerrarDetalleCofepris()"><i data-feather="x" aria-hidden="true"></i></button>
                    </div>
                    <div class="doctor-patient-cofepris-modal-body">
                        <p id="detalleCofeprisModalStatus" class="doctor-patient-cofepris-modal-status" role="status" aria-live="polite"></p>
                        <div id="detalleCofepris" class="doctor-patient-cofepris-detail-content" aria-live="polite">
                            <?php
                            $model = new CofeprisModel();
                            echo $model->ultimoRegistro($data['idPaciente']);
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="cofepris-form-modal" class="doctor-patient-cofepris-modal" data-direct-edit="<?=$data['id_cofepris'] == 0 ? 'false' : 'true';?>" data-internal-edit="false" data-listing-url="<?=htmlspecialchars(SERVIDOR . 'clinica/cofepris/paciente/' . (int)$data['idPaciente'], ENT_QUOTES, 'UTF-8');?>" role="dialog" aria-modal="true" aria-labelledby="cofeprisFormModalTitle" aria-describedby="mensaje" aria-hidden="<?=$data['id_cofepris'] == 0 ? 'true' : 'false';?>" <?= $data['id_cofepris'] == 0 ? 'hidden' : ''; ?>>
                <div class="doctor-patient-cofepris-modal-content doctor-patient-cofepris-form-modal-content">
                    <div class="doctor-patient-cofepris-modal-header">
                        <div>
                            <p class="dashboard-eyebrow">REGISTRO REGULATORIO</p>
                            <h2 id="cofeprisFormModalTitle"><?=$sub_text;?> COFEPRIS</h2>
                        </div>
                        <button id="closeCofeprisFormButton" type="button" class="doctor-patient-cofepris-modal-close" aria-label="Cerrar" title="Cerrar" onclick="cerrarCofeprisFormModal()"><i data-feather="x" aria-hidden="true"></i></button>
                    </div>
                    <div class="doctor-patient-cofepris-modal-body">
                        <p id="cofeprisFormModalStatus" class="doctor-patient-cofepris-modal-status" role="status" aria-live="polite"></p>
                        <?=$folio;?>
                        <div class="doctor-patient-cofepris-field">
                            <label class="doctor-patient-cofepris-form-label" for="Fecha">Fecha</label>
                            <input type="date" class="form-control doctor-patient-cofepris-control" id="Fecha" data-new-default="<?=$fechaFormateada;?>" value="<?=$fechaFormateada;?>">
                        </div>
                        <div class="doctor-patient-cofepris-field">
                            <label class="doctor-patient-cofepris-form-label" for="Diagnostico">Diagnóstico</label>
                            <textarea class="form-control doctor-patient-cofepris-control" id="Diagnostico" rows="2"><?=$escape($data['diagnostico']);?></textarea>
                        </div>
                        <div class="doctor-patient-cofepris-field">
                            <label class="doctor-patient-cofepris-form-label" for="Medicamento">Medicamento</label>
                            <textarea class="form-control doctor-patient-cofepris-control" id="Medicamento" rows="2"><?=$escape($data['medicamento']);?></textarea>
                        </div>
                        <div class="row doctor-patient-cofepris-field-row">
                            <div class="col-12 col-sm-6">
                                <label class="doctor-patient-cofepris-form-label" for="NumCajas">Cantidad (número y letra)</label>
                                <input type="text" class="form-control doctor-patient-cofepris-control" id="NumCajas" value="<?=$escape($data['numcajas']);?>">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="doctor-patient-cofepris-form-label" for="Presentacion">Presentación</label>
                                <input type="text" class="form-control doctor-patient-cofepris-control" id="Presentacion" value="<?=$escape($data['presentacion']);?>">
                            </div>
                        </div>
                        <div class="doctor-patient-cofepris-field">
                            <label class="doctor-patient-cofepris-form-label" for="Dosificacion">Dosificación</label>
                            <textarea class="form-control doctor-patient-cofepris-control" id="Dosificacion" rows="2"><?=$escape($data['dosificacion']);?></textarea>
                        </div>
                        <div class="row doctor-patient-cofepris-field-row">
                            <div class="col-12 col-sm-6">
                                <label class="doctor-patient-cofepris-form-label" for="NumDias">No. de días de prescripción</label>
                                <input type="text" class="form-control doctor-patient-cofepris-control" id="NumDias" value="<?=$escape($data['numdias']);?>">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="doctor-patient-cofepris-form-label" for="ViaAdministracion">Vía de administración</label>
                                <input type="text" class="form-control doctor-patient-cofepris-control" id="ViaAdministracion" value="<?=$escape($data['viaadministracion']);?>">
                            </div>
                        </div>
                        <div class="doctor-patient-cofepris-actions"><button type="button" class="btn doctor-patient-cofepris-submit" data-patient-id="<?=$data['idPaciente'];?>" data-cofepris-id="<?=$data['id_cofepris'];?>" onclick="AgregarCofepris(<?=$data['idPaciente'];?>,<?=$data['id_cofepris'];?>)"><?=$boton_titulo;?> COFEPRIS <i data-feather="chevron-right" aria-hidden="true"></i></button></div>
                        <div class="text-center text-danger" id="mensaje" role="alert" aria-live="polite"></div>
                    </div>
                </div>
            </div>
            </main>

            <?php include_once __DIR__ . '/../components/footer-doctor.php';?>
        </div>
    </div>
    <script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script> 
    <script src="<?=RUTA_JS;?>main.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS?>search-main.js"></script>
    
   
</body>
</html>

