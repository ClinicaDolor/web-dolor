<?php 
use App\Config\Database;
use App\Models\PacienteModulosModelo;
$bd = Database::getInstance();
$model = new PacienteModulosModelo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$data['title'];?></title>
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>logo-clinica.png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>

    <style>
        .clinical-summary-panel .clinical-summary-meta { color: #5f6b7a; font-size: .875rem; margin-bottom: .75rem; }
        .clinical-summary-panel .clinical-summary-notice { border-left: 3px solid #0d6efd; padding-left: .75rem; color: #495057; }
        .clinical-summary-panel .clinical-summary-section { border-top: 1px solid #e9ecef; padding: 1rem 0; }
        .clinical-summary-panel .clinical-summary-section h3 { font-size: 1.05rem; margin-bottom: .35rem; color: #17324d; }
        .clinical-summary-panel .clinical-summary-section li { margin-bottom: .35rem; }
        .row {
            display: flex;
            flex-wrap: wrap;
        }
        .resizable {
            transition: all 0.3s ease-in-out; /* Suaviza el cambio de tamaño */
        }
    </style>

    <script>

    let clinicalTablesStarted = false;

    function initializeClinicalTables(event) {
        if (clinicalTablesStarted && !(event && event.persisted)) return;

        clinicalTablesStarted = true;
        tableNotas()
        tableLaboratorio()
        tableRecetas()
        tableCofepris()
    }

    window.addEventListener("pageshow", initializeClinicalTables);
    document.addEventListener("DOMContentLoaded", initializeClinicalTables, { once: true });

    function getClinicalPatientId() {
        const usuarioDiv = document.getElementById('main');
        return usuarioDiv ? usuarioDiv.getAttribute('data-paciente') : null;
    }

    function setClinicalCardState(containerId, state, message) {
        const resultsContainer = document.getElementById(containerId);
        if (!resultsContainer) return;

        resultsContainer.dataset.state = state;
        resultsContainer.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');

        const icon = state === 'error' ? 'alert-circle' : (state === 'empty' ? 'inbox' : 'loader');
        const role = state === 'error' ? 'alert' : 'status';
        resultsContainer.innerHTML = `<div class="patient-record-async-state patient-record-async-state-${state}" role="${role}">
            <i data-feather="${icon}" aria-hidden="true"></i>
            <span>${message}</span>
        </div>`;

        if (window.feather && typeof window.feather.replace === 'function') window.feather.replace();
    }

    function renderClinicalTable(containerId, html, tableSelector, emptyMessage) {
        const resultsContainer = document.getElementById(containerId);
        if (!resultsContainer) return false;

        resultsContainer.innerHTML = html;
        const table = resultsContainer.querySelector(tableSelector);
        if (!table) throw new Error('Tabla clínica no disponible');

        const rows = table.querySelectorAll('tbody tr');
        if (!rows.length) {
            setClinicalCardState(containerId, 'empty', emptyMessage);
            return false;
        }

        resultsContainer.dataset.state = 'ready';
        resultsContainer.setAttribute('aria-busy', 'false');
        if (window.feather && typeof window.feather.replace === 'function') window.feather.replace();
        return true;
    }

    function tableNotas(){

        const idPaciente = getClinicalPatientId();
        if (!idPaciente) return;
        const referencia = 0;

        setClinicalCardState('conteNotasSubsecuentes', 'loading', 'Cargando notas subsecuentes…');
        fetch(`/buscar/tabla-notas-subsecuentes/${encodeURIComponent(idPaciente)}/${encodeURIComponent(referencia)}`)
            .then(response => {
            if (!response.ok) {
            throw new Error('Respuesta no disponible');
            }
            return response.text();
            })
            .then(data => {
                if (!renderClinicalTable('conteNotasSubsecuentes', data, '#tableNotasSubsecuentes', 'No hay notas subsecuentes registradas.')) return;

                initPersistentDataTable("#tableNotasSubsecuentes", "datatableState_tableNotas", {
                searchable: true,
                fixedHeight: false,
                perPageSelect: false,
                columns: [
                    { select: 0, sort: "desc" },
                    { select: [2], sortable: false }
                ]
            });
        }).catch(() => setClinicalCardState('conteNotasSubsecuentes', 'error', 'No se pudieron cargar las notas subsecuentes.'));

    }

     function tableRecetas(){

        const idPaciente = getClinicalPatientId();
        if (!idPaciente) return;

        setClinicalCardState('conteRecetas', 'loading', 'Cargando recetas médicas…');
        fetch(`/buscar/tabla-recetas/${encodeURIComponent(idPaciente)}`)
            .then(response => {
            if (!response.ok) {
            throw new Error('Respuesta no disponible');
            }
            return response.text();
            })
            .then(data => {
                if (!renderClinicalTable('conteRecetas', data, '#tableRecetas', 'No hay recetas médicas registradas.')) return;
                initPersistentDataTable("#tableRecetas", "datatableState_tableRecetas", {
                searchable: true,
                fixedHeight: false,
                perPageSelect: false,
                columns: [
                    { select: 0, sort: "desc" }
                ]
            });  
        }).catch(() => setClinicalCardState('conteRecetas', 'error', 'No se pudieron cargar las recetas médicas.'));
    }

    function tableLaboratorio(){
        const idPaciente = getClinicalPatientId();
        if (!idPaciente) return;
        const referencia = 0;

        setClinicalCardState('conteLaboratorio', 'loading', 'Cargando estudios de laboratorio…');
        fetch(`/buscar/tabla-laboratorio/${encodeURIComponent(idPaciente)}/${encodeURIComponent(referencia)}`)
            .then(response => {
            if (!response.ok) {
            throw new Error('Respuesta no disponible');
            }
            return response.text();
            })
            .then(data => {
                if (!renderClinicalTable('conteLaboratorio', data, '#tableLaboratorio', 'No hay estudios de laboratorio registrados.')) return;
                initPersistentDataTable("#tableLaboratorio", "datatableState_tableLaboratorio", {
                searchable: true,
                fixedHeight: false,
                perPageSelect: false,
                columns: [
                    { select: 0, sort: "desc" }
                ]
            });    
        }).catch(() => setClinicalCardState('conteLaboratorio', 'error', 'No se pudieron cargar los estudios de laboratorio.'));
    }

        function NuevoPin(idPaciente){

        const parametros = {
        idPaciente : idPaciente
        };

        fetch('/clinica/paciente/insert-pin', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(parametros)
        })
        .then(response => response.json())
        .then(data => {

        if (data.resultado) {
            location.reload()
        } else {
            document.getElementById('mensaje').textContent = 'Error: ' + data.mensaje;
        }

        });

        }

        function NuevaNotaSubsecuente(idPaciente,referencia){
            window.location.href = '/clinica/nota-subsecuente/paciente/' + idPaciente + '/referencia/' + referencia;
        }

        function DetalleNota(idNota){
            window.location.href = '/clinica/nota-subsecuente/' + idNota;
        }

        function NuevaReceta(idPaciente){
            window.location.href = '/clinica/receta/paciente/' + idPaciente;
        }

        function DetalleReceta(idReceta){
            window.location.href = '/clinica/receta/' + idReceta;
        }

        function NuevoLaboratorio(idPaciente){
            window.location.href = '/clinica/laboratorio/paciente/' + idPaciente;
        }

        function DetalleLaboratorio(idLaboratorio){
            window.location.href = '/clinica/laboratorio/' + idLaboratorio;
        }

        function tableCofepris(){
        const idPaciente = getClinicalPatientId();
        if (!idPaciente) return;
        const referencia = 0;

        setClinicalCardState('conteCofepris', 'loading', 'Cargando registros de COFEPRIS…');
        fetch(`/buscar/tabla-cofepris/${encodeURIComponent(idPaciente)}`)
            .then(response => {
            if (!response.ok) {
            throw new Error('Respuesta no disponible');
            }
            return response.text();
            })
            .then(data => {
                if (!renderClinicalTable('conteCofepris', data, '#tableCofepris', 'No hay registros de COFEPRIS disponibles.')) return;
                initPersistentDataTable("#tableCofepris", "datatableState_tableCofepris", {
                searchable: true,
                fixedHeight: false,
                perPageSelect: false,
                columns: [
                    { select: 0, sort: "desc" },
                    { select: [2], sortable: false }
                ]
            });       
        }).catch(() => setClinicalCardState('conteCofepris', 'error', 'No se pudieron cargar los registros de COFEPRIS.'));
        }

        function NuevoCofepris(idPaciente){
            window.location.href = '/clinica/cofepris/paciente/' + idPaciente;
        }

        function DetalleCofepris(id){
            window.location.href = '/clinica/cofepris/' + id;
        }

    
        function initPersistentDataTable(selector, storageKey, options = {}) {
    const table = document.querySelector(selector);
    if (!table || typeof simpleDatatables === 'undefined' || typeof simpleDatatables.DataTable !== 'function') return null;

    let savedState = {};
    try {
        savedState = JSON.parse(localStorage.getItem(storageKey) || '{}') || {};
    } catch (error) {
        savedState = {};
    }

    const dataTable = new simpleDatatables.DataTable(table, {
        ...options,
        perPage: savedState.perPage || 10,
    });

    dataTable.on('datatable.init', function () {
        if (savedState.page) dataTable.page(savedState.page);
        if (savedState.search) {
            dataTable.input.value = savedState.search;
            dataTable.search(savedState.search);
        }
    });

    dataTable.on('datatable.page', function (page) {
        savedState.page = page;
        localStorage.setItem(storageKey, JSON.stringify(savedState));
    });

    dataTable.on('datatable.perpage', function (perPage) {
        savedState.perPage = perPage;
        localStorage.setItem(storageKey, JSON.stringify(savedState));
    });

    dataTable.on('datatable.sort', function (column, direction) {
        savedState.sort = direction;
        localStorage.setItem(storageKey, JSON.stringify(savedState));
    });

    dataTable.on('datatable.search', function (query) {
        savedState.search = query;
        localStorage.setItem(storageKey, JSON.stringify(savedState));
    });
}

    </script>


 </head>

    <body class="app-shell-body app-shell-doctor doctor-patient-record-page">
    <div class="LoaderPage"></div>
   <div id="app">
        
    <?=$data['sidebar'];?>

    <div id="main" data-paciente="<?=$data['idPaciente'];?>">
    <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
    <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
            
    <div class="main-content container-fluid">
    
    <?php
    $patientId = (int)$data['idPaciente'];
    $patientName = htmlspecialchars((string)$data['nombre_paciente'], ENT_QUOTES, 'UTF-8');
    $patientSex = ($data['sexo'] === 'M') ? 'Masculino' : 'Femenino';
    $patientAdmissionDate = (new DateTime($data['fecha_alta']))->format('d/m/Y');
    ?>
    <header class="patient-record-header" aria-labelledby="patient-record-title">
        <div class="patient-record-header-main">
            <p class="dashboard-eyebrow">EXPEDIENTE CLÍNICO</p>
            <h1 id="patient-record-title"><?=$patientName;?></h1>
            <div class="patient-record-meta" aria-label="Contexto del expediente">
                <?php if ($patientId > 0): ?>
                <span class="patient-record-id">Expediente #<?=$patientId;?></span>
                <?php endif; ?>
                <span>Edad <?=$data['edad'];?> años</span>
                <span>Sexo <?=$patientSex;?></span>
                <span>Alta <?=$patientAdmissionDate;?></span>
            </div>
        </div>

        <div class="patient-record-header-actions">
            <a href="<?=SERVIDOR?>clinica/pacientes" class="btn btn-light patient-record-back">
                <i data-feather="arrow-left" aria-hidden="true"></i>
                <span>Volver a pacientes</span>
            </a>
            <a href="<?=SERVIDOR?>clinica/paciente/editar/<?=$patientId;?>" class="btn btn-primary patient-record-edit">
                <i data-feather="edit-2" aria-hidden="true"></i>
                <span>Editar paciente</span>
            </a>
            <button id="toggleButton" type="button" class="btn icon btn-light patient-record-layout-toggle" onclick="toggleSize()" aria-label="Cambiar distribución de la pantalla" title="Cambiar distribución de la pantalla">
                <i id="toggleIcon" data-feather="columns" aria-hidden="true"></i>
                <span>Cambiar distribución</span>
            </button>
        </div>
    </header>

    <section class="col-12 mt-4 clinical-summary-entry" aria-labelledby="clinicalSummaryEntryTitle">
        <article class="clinical-summary-entry-card">
            <div class="clinical-summary-entry-icon" aria-hidden="true"><i data-feather="cpu"></i></div>
            <div class="clinical-summary-entry-content">
                <p class="dashboard-eyebrow mb-1">APOYO A LA REVISIÓN</p>
                <h2 class="clinical-summary-entry-title" id="clinicalSummaryEntryTitle">Resumen clínico inteligente</h2>
                <p class="clinical-summary-entry-description">Consulta una lectura estructurada de los nueve módulos del expediente, con referencias documentales y datos faltantes.</p>
                <div class="clinical-summary-entry-meta" aria-label="Características del resumen">
                    <span><i data-feather="layers" aria-hidden="true"></i> 9 módulos clínicos</span>
                    <span><i data-feather="shield" aria-hidden="true"></i> Solo lectura</span>
                    <span><i data-feather="server" aria-hidden="true"></i> Generación local</span>
                </div>
            </div>
            <a class="btn btn-primary clinical-summary-entry-action" href="<?=SERVIDOR?>clinica/paciente/<?=$patientId?>/resumen-clinico">
                <i data-feather="arrow-right" aria-hidden="true"></i>
                <span>Abrir resumen</span>
            </a>
        </article>
    </section>

    <div class="row mt-4 patient-record-layout">

    <section class="col-12 resizable patient-record-summary-section" aria-labelledby="patient-summary-title">
    <article class="card patient-record-summary-card">
    <div class="card-header patient-record-section-header">
    <div class="float-end">
    <a href="<?=SERVIDOR?>clinica/paciente/editar/<?=$data['idPaciente']?>" class="btn icon btn-primary text-white" aria-label="Editar información del paciente">
    <i data-feather="edit-2" aria-hidden="true"></i>
    </a>
    </div>
    <p class="dashboard-eyebrow">RESUMEN DEL PACIENTE</p>
    <h2 id="patient-summary-title" class="card-title">Información del paciente</h2>
    </div>
    <div class="card-body patient-record-summary-body">

    <div class="patient-record-identity-grid">
    <div class="patient-record-field patient-record-field-name">
    <div class="text-secondary">Nombre Paciente:</div>
    <h3><?=$data['nombre_paciente'];?></h3>
    </div>

    <div class="patient-record-field">
    <div class="text-secondary">Fecha Alta:</div>
    <h5><?=(new DateTime($data['fecha_alta']))->format('d/m/Y');?></h5>
    </div>

    <div class="patient-record-field">
    <div class="text-secondary">Fecha Nacimiento:</div>
    <h5><?=date("d/m/Y", strtotime($data['fecha_nacimiento']));?></h5>
    </div>

    <div class="patient-record-field">
    <div class="text-secondary">Edad:</div>
    <h5><?=$data['edad'];?> años</h5>
    </div>

    <div class="patient-record-field">
    <div class="text-secondary">Sexo:</div>
    <h5><?=($data['sexo'] == 'M')? 'Masculino': 'Femenino';?></h5>
    </div>

    <div class="patient-record-field">
    <div class="text-secondary">Estado Civil:</div>
    <h5><?=$data['estado_civil'];?></h5>
    </div>

    <div class="patient-record-field">
    <div class="text-secondary">CURP:</div>
    <h5><?=$data['curp'];?></h5>
    </div>
    </div>

    <div class="patient-record-summary-divider" role="presentation"></div>

    <section class="patient-record-contact-block" aria-labelledby="patient-contact-title">
    <h3 id="patient-contact-title" class="patient-record-subsection-title">Contacto del paciente</h3>
    <div class="patient-record-contact-grid">
    <div class="patient-record-field">
    <div class="text-secondary">Email:</div>
    <h5><?=$data['email'];?></h5>
    </div>

    <div class="patient-record-field">
    <div class="text-secondary">Telefono:</div>
    <h5><?=$data['telefono'];?></h5>
    </div>

    <div class="patient-record-field">
    <div class="text-secondary">Celular:</div>
    <h5><?=$data['celular'];?></h5>
    </div>
    </div>
    </section>

    <section class="patient-record-reason-block" aria-labelledby="patient-reason-title">
    <h3 id="patient-reason-title" class="patient-record-subsection-title">Motivo por el que solicita atención</h3>
    <p><?=empty($data['motivo_atencion'])? 'S/I': $data['motivo_atencion'];?></p>
    </section>

    </div>
    </article>
    </section>

    <!-- Card Historia Clínica: se mantiene antes del resto de tarjetas para priorizar seguimiento -->
    <section class="col-12 resizable patient-record-history-section" aria-labelledby="patient-history-title">
    <div class="card patient-record-history-card">
    <div class="card-header patient-record-section-header">
    <p class="dashboard-eyebrow">SEGUIMIENTO CLÍNICO</p>
    <h2 id="patient-history-title" class="card-title">Historia Clínica</h2>
    </div>

    <div class="card-body">
    <?php
    try {
    $stmt = $bd->query("SELECT * FROM historia_clinica_modulos");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
    throw new \RuntimeException('Clinical history modules unavailable', 0, $e);
    }
    ?>

    <table class='table table-sm table-striped pb-0 mb-0 patient-history-table' id="table2">
    <thead>
    <tr>
    <th class="text-center" scope="col">Id</th>
    <th scope="col">Nombre del módulo</th>
    <th class="text-center" scope="col">Cumplimiento</th>
    <th class="text-center patient-history-action-column" scope="col" aria-label="Acciones">
        <span class="patient-history-action-header-label">Acciones</span>
    </th>
    </tr>
    </thead>

    <tbody>
    <?php
    $porcentajeAcumulado = 0; // Acumulador para el total
    $contadorModulos = 0;

    foreach ($registros as $registro):
    ?>
    <tr>
    <td class="text-center"><?=$registro['id']?></td>
    <td><b><?=$registro['nombre']?></b></td>
    <td>

    <?php
    $porcentajeTotal = 0;

    switch ($registro['id']) {
    case 1:
        $porcentajeTotal = floatval($model->porcentajeModulo1($data['idPaciente']));
        break;
    case 2:
        $porcentajeTotal = floatval($model->porcentajeModulo2($data['idPaciente']));
        break;
    case 3:
        $porcentajeTotal = floatval($model->porcentajeModulo3($data['idPaciente']));
        break;
    case 4:
        $porcentajeTotal = floatval($model->porcentajeModulo4($data['idPaciente']));
        break;
    case 5:
        $porcentajeTotal = floatval($model->porcentajeModulo5($data['idPaciente']));
        break;
    case 6:
        $porcentajeTotal = floatval($model->porcentajeModulo6($data['idPaciente']));
        break;
    case 7:
        $porcentajeTotal = floatval($model->porcentajeModulo7($data['idPaciente']));
        break;
    case 8:
        $porcentajeTotal = floatval($model->porcentajeModulo8($data['idPaciente']));
        break;
    case 9:
        $porcentajeTotal = floatval($model->porcentajeModulo9($data['idPaciente']));
        break;
    }

    $porcentajeAcumulado += $porcentajeTotal;
    $contadorModulos++;

    $porcentajeTexto = htmlspecialchars((string)$porcentajeTotal, ENT_QUOTES, 'UTF-8');
    $nombreModulo = htmlspecialchars((string)$registro['nombre'], ENT_QUOTES, 'UTF-8');
    echo '<div class="patient-history-progress" role="group" aria-label="Cumplimiento de '.$nombreModulo.': '.$porcentajeTexto.'%">
    <span class="patient-history-progress-value">'.$porcentajeTexto.'%</span>
    <div class="progress bg-light rounded-pill patient-history-progress-bar">
    <div class="progress-bar bg-success rounded-pill text-white fw-bold text-center"
    role="progressbar" style="width: '.$porcentajeTotal.'%;" aria-valuenow="'.$porcentajeTexto.'" aria-valuemin="0" aria-valuemax="100">
    <span class="visually-hidden">'.$porcentajeTexto.'% de cumplimiento</span>
    </div>
    </div>
    </div>';
    ?>
    </td>
    <td class="text-center patient-history-actions-cell">
        <div class="patient-history-actions">
            <button type="button" class="patient-history-actions-toggle" data-history-actions-toggle aria-haspopup="menu" aria-expanded="false" aria-controls="history-actions-<?=$registro['id']?>" aria-label="Acciones para <?=$nombreModulo?>">
                <i data-feather="more-horizontal" aria-hidden="true"></i>
                <span class="patient-history-actions-label">Acciones</span>
                <span class="visually-hidden"> para <?=$nombreModulo?></span>
            </button>
            <div class="patient-history-actions-menu" id="history-actions-<?=$registro['id']?>" role="menu" aria-label="Acciones para <?=$nombreModulo?>" hidden>
                <a href="<?=SERVIDOR?>clinica/<?=$registro['url']?>/paciente/<?=$data['idPaciente']?>" role="menuitem">
                    <i data-feather="edit" aria-hidden="true"></i>
                    <span>Editar módulo</span>
                </a>
                <a href="<?=SERVIDOR?>clinica/resumen/<?=$registro['url']?>/paciente/<?=$data['idPaciente']?>" role="menuitem">
                    <i data-feather="file-text" aria-hidden="true"></i>
                    <span>Ver resumen</span>
                </a>
            </div>
        </div>
    </td>
    </tr>
    <?php endforeach; ?>

    </tbody>
    </table>
    <?php
    $promedioTotal = ($contadorModulos > 0) ? round($porcentajeAcumulado / $contadorModulos, 2) : 0;
    $promedioTexto = htmlspecialchars((string)$promedioTotal, ENT_QUOTES, 'UTF-8');
    ?>
    <div class="patient-history-total-row" role="group" aria-label="Porcentaje total: <?=$promedioTexto?>%">
    <strong class="patient-history-total-label">Porcentaje total</strong>
    <div class="patient-history-total-progress">
    <?php
    echo '<div class="patient-history-progress" role="group" aria-label="Porcentaje total: '.$promedioTexto.'%">
    <span class="patient-history-progress-value">'.$promedioTexto.'%</span>
    <div class="progress bg-light rounded-pill patient-history-progress-bar">
    <div class="progress-bar bg-success rounded-pill text-white fw-bold text-center"
    role="progressbar" style="width: '.$promedioTotal.'%;" aria-valuenow="'.$promedioTexto.'" aria-valuemin="0" aria-valuemax="100">
    <span class="visually-hidden">'.$promedioTexto.'% de cumplimiento total</span>
    </div>
    </div>
    </div>';
    ?>
    </div>
    </div>
    </div>
    </div>
    </section>

    <div class="col-12 patient-record-secondary-section" aria-label="Gestión del expediente">
    <div class="row g-3 patient-record-secondary-grid">

    <!-- Inicio PIN -->
    <div class="col-12 col-sm-6 resizable patient-record-data-card" data-index="0">
    <div class="card patient-record-data-panel">
    <div class="card-header">
    <div class="patient-record-card-heading">
    <p class="dashboard-eyebrow">ACCESO DEL PACIENTE</p>
    <h2 class="card-title">PIN de acceso</h2>
    </div>
    <button type="button" class="btn btn-success patient-record-card-action" onclick="NuevoPin(<?=$data['idPaciente'];?>)" aria-label="Generar un nuevo PIN de acceso" aria-controls="pinAccessContent">
        <i data-feather="plus" aria-hidden="true"></i>
        <span>Nuevo PIN</span>
    </button>
    </div>
                        <div class="card-body" id="pinAccessContent">

                        <?php
                        $hoy = new DateTime();
                        $pinQueryFailed = false;
                        try {
                            $stmt = $bd->query("SELECT * FROM pc_paciente_acceso WHERE paciente_id = '".$data['idPaciente']."' ORDER BY fecha_creacion DESC");
                            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            $registros = [];
                            $pinQueryFailed = true;
                        }
                        ?>

                        <?php if ($pinQueryFailed): ?>
                            <div class="patient-record-async-state patient-record-async-state-error" role="alert" data-state="error">
                                <i data-feather="alert-circle" aria-hidden="true"></i>
                                <span>No se pudo cargar el estado del PIN.</span>
                            </div>
                        <?php elseif (empty($registros)): ?>
                            <div class="patient-record-async-state patient-record-async-state-empty" role="status" data-state="empty">
                                <i data-feather="key" aria-hidden="true"></i>
                                <span>No hay un PIN de acceso generado.</span>
                            </div>
                        <?php else: ?>
                        <table class="table table-sm table-striped pb-0 mb-0" id="tablePin">
                            <thead>
                                <tr>
                                    <th class="text-center">PIN</th>
                                    <th class="text-center">Fecha creación</th>
                                    <th class="text-center">Fecha expiración</th>
                                    <th class="text-center">Estatus</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php

                            foreach ($registros as $registro):
                            $fechaExpiracion = new DateTime($registro['fecha_expiracion']);
                            if ($hoy > $fechaExpiracion){
                                $pin = '******';
                                $estatus = '<span class="badge bg-danger">Cancelado</span>';
                            }else{
                                $pin = '<label class="text-primary fw-bold">cdp'.$data['idPaciente'].'</label>';
                                $estatus = '<span class="badge bg-success">Activo</span>';
                            }
                            ?>
                                <tr>
                                    <td class="text-center align-middle"><?=$pin;?></td>
                                    <td class="text-center align-middle"><?=(new DateTime($registro['fecha_creacion']))->format('d/m/Y');?></td>
                                    <td class="text-center align-middle"><b><?=(new DateTime($registro['fecha_expiracion']))->format('d/m/Y');?></b></td>
                                    <td class="text-center align-middle"><?=$estatus;?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>

                        </div>
    </div>
    </div>
    <!-- Fin PIN -->

    <!-- Inicio Clinica -->
    <div class="col-12 col-sm-6 resizable patient-record-data-card" data-index="1">

    <div class="card patient-record-data-panel">
        <div class="card-header">
        <div class="patient-record-card-heading">
            <p class="dashboard-eyebrow">SEGUIMIENTO CLÍNICO</p>
            <h2 class="card-title">Nota subsecuente</h2>
        </div>
        <button type="button" class="btn btn-success patient-record-card-action" onclick="NuevaNotaSubsecuente(<?=$data['idPaciente'];?>,'<?=$data['referencia'];?>')" aria-controls="conteNotasSubsecuentes">
            <i data-feather="plus" aria-hidden="true"></i>
            <span>Nueva nota</span>
        </button>

        </div>
        <div class="card-body">
            <div id="conteNotasSubsecuentes"></div>
        </div>
        </div>

        </div>

        <div class="col-12 col-sm-6 resizable patient-record-data-card" data-index="2">
        <div class="card patient-record-data-panel">
        <div class="card-header">
        <div class="patient-record-card-heading">
            <p class="dashboard-eyebrow">ESTUDIOS</p>
            <h2 class="card-title">Laboratorio</h2>
        </div>
        <button type="button" class="btn btn-success patient-record-card-action" onclick="NuevoLaboratorio(<?=$data['idPaciente'];?>)" aria-controls="conteLaboratorio">
            <i data-feather="plus" aria-hidden="true"></i>
            <span>Nuevo laboratorio</span>
        </button>

        </div>
        <div class="card-body">
        <div id="conteLaboratorio"></div>
        </div>
        </div>

        </div>
        
        <!-- Inicio Recetas -->
        <div class="col-12 col-sm-6 resizable patient-record-data-card" data-index="3">
        <div class="card patient-record-data-panel">
        <div class="card-header">
        <div class="patient-record-card-heading">
            <p class="dashboard-eyebrow">TRATAMIENTO</p>
            <h2 class="card-title">Receta médica</h2>
        </div>
        <button type="button" class="btn btn-success patient-record-card-action" onclick="NuevaReceta(<?=$data['idPaciente'];?>)" aria-controls="conteRecetas">
            <i data-feather="plus" aria-hidden="true"></i>
            <span>Nueva receta</span>
        </button>

        </div>
        <div class="card-body">

        <div id="conteRecetas"></div>

        </div>
        </div>
        <!-- Fin Recetas -->

        </div>

         <!-- Inicio Cofepris -->
         <div class="col-12 col-sm-6 resizable patient-record-data-card" data-index="4">
         <div class="card patient-record-data-panel">
        <div class="card-header">
        <div class="patient-record-card-heading">
            <p class="dashboard-eyebrow">REGULACIÓN</p>
            <h2 class="card-title">COFEPRIS</h2>
        </div>
        <button type="button" class="btn btn-success patient-record-card-action" onclick="NuevoCofepris(<?=$data['idPaciente'];?>)" aria-controls="conteCofepris">
            <i data-feather="plus" aria-hidden="true"></i>
            <span>Nuevo registro</span>
        </button>

        </div>
        <div class="card-body">

        <div id="conteCofepris"></div>

        </div>
        </div>
        <!-- Fin Cofepris -->

        </div>
        </div>
        </div>

    </div>
    </div>

    <!----- FOOTER ---------->
    <?php include_once __DIR__ . '/../components/footer-doctor.php';?>
    </div>
    </div>
        
    <script src="<?=RUTA_JS;?>feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script> 
    <script src="<?=RUTA_JS;?>main.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS?>search-main.js"></script>
    <script src="<?=RUTA_JS?>clinical-summary.js"></script>
    
    <script>

    const hasSimpleDataTables = typeof simpleDatatables !== 'undefined' && typeof simpleDatatables.DataTable === 'function';
    let tablePin = document.querySelector('#tablePin');
    let dataTablePin = tablePin && hasSimpleDataTables ? new simpleDatatables.DataTable(tablePin,{
    fixedHeight: false,
    perPageSelect: false,
    searchable: false,
    columns: [
    {
        select: 1, sort: "desc"
    }
    ]
}) : null;

let table1 = document.querySelector('#table2');
let dataTable = table1 && hasSimpleDataTables ? new simpleDatatables.DataTable(table1,{
	searchable: true,
    fixedHeight: false,
    columns: [
    { select: [2,3], sortable: false },
	]
    }) : null;

if (dataTable) {
    const historyActionState = {
        trigger: null,
        menu: null,
        template: null
    };

    function closeHistoryActions(restoreFocus) {
        const trigger = historyActionState.trigger;
        const menu = historyActionState.menu;
        const template = historyActionState.template;
        if (!trigger || !menu) return;

        menu.remove();
        trigger.setAttribute('aria-expanded', 'false');
        if (template) trigger.setAttribute('aria-controls', template.id);
        historyActionState.trigger = null;
        historyActionState.menu = null;
        historyActionState.template = null;

        if (restoreFocus) trigger.focus();
    }

    function positionHistoryActions(trigger, menu) {
        menu.style.visibility = 'hidden';
        menu.style.left = '0px';
        menu.style.top = '0px';
        const triggerRect = trigger.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();
        const gap = 6;
        const edge = 8;
        let left = triggerRect.right - menuRect.width;
        let top = triggerRect.bottom + gap;

        if (left < edge) left = edge;
        if (left + menuRect.width > window.innerWidth - edge) {
            left = Math.max(edge, window.innerWidth - menuRect.width - edge);
        }
        if (top + menuRect.height > window.innerHeight - edge && triggerRect.top - menuRect.height - gap >= edge) {
            top = triggerRect.top - menuRect.height - gap;
        }

        menu.style.left = Math.round(left) + 'px';
        menu.style.top = Math.round(top) + 'px';
        menu.style.visibility = 'visible';
    }

    function openHistoryActions(trigger) {
        const menuId = trigger.getAttribute('aria-controls');
        const template = menuId ? document.getElementById(menuId) : null;
        if (!template) return;

        closeHistoryActions(false);
        const menu = template.cloneNode(true);
        menu.id = menuId + '-popup';
        menu.hidden = false;
        document.body.appendChild(menu);
        menu.setAttribute('data-open', 'true');
        trigger.setAttribute('aria-expanded', 'true');
        trigger.setAttribute('aria-controls', menu.id);
        historyActionState.trigger = trigger;
        historyActionState.menu = menu;
        historyActionState.template = template;
        positionHistoryActions(trigger, menu);
        const firstItem = menu.querySelector('[role="menuitem"]');
        if (firstItem) firstItem.focus();
    }

    function focusHistoryMenuItem(direction) {
        if (!historyActionState.menu) return;
        const items = [...historyActionState.menu.querySelectorAll('[role="menuitem"]')];
        const currentIndex = items.indexOf(document.activeElement);
        if (!items.length) return;
        const nextIndex = currentIndex < 0 ? 0 : (currentIndex + direction + items.length) % items.length;
        items[nextIndex].focus();
    }

    document.addEventListener('click', function (event) {
        const eventElement = event.target instanceof Element ? event.target : event.target?.parentElement;
        const trigger = eventElement?.closest('[data-history-actions-toggle]');
        if (trigger && table1.contains(trigger)) {
            event.preventDefault();
            if (historyActionState.trigger === trigger) {
                closeHistoryActions(false);
            } else {
                openHistoryActions(trigger);
            }
            return;
        }

        if (historyActionState.menu && historyActionState.menu.contains(event.target)) return;
        if (historyActionState.trigger && !historyActionState.trigger.contains(event.target)) closeHistoryActions(false);
    });

    document.addEventListener('keydown', function (event) {
        const eventElement = event.target instanceof Element ? event.target : null;
        const activeTrigger = eventElement?.closest('[data-history-actions-toggle]');
        if (activeTrigger && table1.contains(activeTrigger) && event.key === 'ArrowDown') {
            event.preventDefault();
            if (historyActionState.trigger !== activeTrigger) openHistoryActions(activeTrigger);
            historyActionState.menu?.querySelector('[role="menuitem"]')?.focus();
            return;
        }

        if (event.key === 'Escape' && historyActionState.menu) {
            event.preventDefault();
            closeHistoryActions(true);
            return;
        }

        if (!historyActionState.menu || !historyActionState.menu.contains(document.activeElement)) return;
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            focusHistoryMenuItem(1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            focusHistoryMenuItem(-1);
        } else if (event.key === 'Home') {
            event.preventDefault();
            historyActionState.menu.querySelector('[role="menuitem"]')?.focus();
        } else if (event.key === 'End') {
            event.preventDefault();
            const items = historyActionState.menu.querySelectorAll('[role="menuitem"]');
            items[items.length - 1]?.focus();
        } else if (event.key === 'Tab') {
            closeHistoryActions(false);
        }
    });

    window.addEventListener('resize', function () {
        if (historyActionState.trigger && historyActionState.menu) positionHistoryActions(historyActionState.trigger, historyActionState.menu);
    });
    window.addEventListener('scroll', function () {
        if (historyActionState.trigger && historyActionState.menu) positionHistoryActions(historyActionState.trigger, historyActionState.menu);
    }, true);

    ['datatable.init', 'datatable.page', 'datatable.perpage', 'datatable.sort', 'datatable.search'].forEach(function (eventName) {
        dataTable.on(eventName, function () {
            closeHistoryActions(false);
        });
    });
}

</script>
    
</body>
</html>

