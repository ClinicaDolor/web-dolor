<?php 
use App\Config\Database;
use App\Models\PacienteModulosModelo;
$bd = Database::getInstance();
$model = new PacienteModulosModelo();
?>
 
    <!DOCTYPE html>
    <html lang="es-MX">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$data['title'];?></title>
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>
    </head>
 
    <body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-modules-page">
    <div class="LoaderPage"></div>
    <div id="app">
    <?=$data['sidebar'];?>

    <div id="main">
    <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
    <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
            
    <?php
    $nombrePaciente = trim((string)($data['nombre_paciente'] ?? ''));
    $nombrePacienteSeguro = $nombrePaciente !== ''
        ? htmlspecialchars($nombrePaciente, ENT_QUOTES, 'UTF-8')
        : 'Paciente autorizado';
    ?>

    <main class="main-content container-fluid" id="main-content">
    <header class="doctor-page-hero doctor-modules-hero" aria-labelledby="clinical-modules-title">
    <div>
    <p class="dashboard-eyebrow">HISTORIA CLÍNICA</p>
    <h1 id="clinical-modules-title"><?=$nombrePacienteSeguro;?></h1>
    <p>Consulta y revisa el avance de su historia clínica.</p>
    </div>
    <a href="<?=SERVIDOR?>clinica/paciente/<?=$data['id_paciente'];?>" class="btn btn-light doctor-page-hero-action">
        <i data-feather="arrow-left" aria-hidden="true"></i>
        <span>Volver al expediente</span>
    </a>
    </header>
            
    <section class="section doctor-modules-section mt-4" aria-labelledby="clinical-patient-title">
   
    <div class="card doctor-modules-card">
    <div class="card-header doctor-modules-card-header">
    <div>
    <p class="dashboard-eyebrow">AVANCE CLÍNICO</p>
    <h2 class="card-title" id="clinical-patient-title">Módulos de Historia Clínica</h2>
    </div>
    </div>


    <div class="card-body">
    <?php
    try {
    $stmt = $bd->query("SELECT * FROM historia_clinica_modulos");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
    }
    ?>
             
    <div class="table-responsive">
    <table class='table table-striped table-hover patient-history-table' id="table1">
    <thead>
    <tr>
    <th class="text-center" scope="col">Id</th>
    <th class="text-start" scope="col">Nombre del módulo</th>
    <th class="text-center" scope="col">% de cumplimiento</th>
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
    <?php $nombreModulo = htmlspecialchars((string)$registro['nombre'], ENT_QUOTES, 'UTF-8'); ?>
    <tr>
    <td class="text-center"><?=$registro['id']?></td>
    <td><b><?=$nombreModulo?></b></td>
    <td>

    <?php
    $porcentajeTotal = 0;

    switch ($registro['id']) {
    case 1:
        $porcentajeTotal = floatval($model->porcentajeModulo1($data['id_paciente']));
        break;
    case 2:
        $porcentajeTotal = floatval($model->porcentajeModulo2($data['id_paciente']));
        break;
    case 3:
        $porcentajeTotal = floatval($model->porcentajeModulo3($data['id_paciente']));
        break;
    case 4:
        $porcentajeTotal = floatval($model->porcentajeModulo4($data['id_paciente']));
        break;
    case 5:
        $porcentajeTotal = floatval($model->porcentajeModulo5($data['id_paciente']));
        break;
    case 6:
        $porcentajeTotal = floatval($model->porcentajeModulo6($data['id_paciente']));
        break;
    case 7:
        $porcentajeTotal = floatval($model->porcentajeModulo7($data['id_paciente']));
        break;
    case 8:
        $porcentajeTotal = floatval($model->porcentajeModulo8($data['id_paciente']));
        break;
    case 9:
        $porcentajeTotal = floatval($model->porcentajeModulo9($data['id_paciente']));
        break;
    }

    $porcentajeAcumulado += $porcentajeTotal;
    $contadorModulos++;

    $porcentajeTexto = htmlspecialchars((string)$porcentajeTotal, ENT_QUOTES, 'UTF-8');
    echo '<div class="doctor-modules-progress" role="group" aria-label="Cumplimiento de '.$nombreModulo.': '.$porcentajeTexto.'%">
    <span class="doctor-modules-progress-value">'.$porcentajeTexto.'%</span>
    <div class="progress bg-light rounded-pill doctor-modules-progress-bar">
    <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: '.$porcentajeTexto.'%;" aria-valuenow="'.$porcentajeTexto.'" aria-valuemin="0" aria-valuemax="100"><span class="visually-hidden">'.$porcentajeTexto.'% de cumplimiento</span></div>
    </div>
    </div>';
    ?>
    </td>
    <td class="text-center patient-history-actions-cell">
        <div class="patient-history-actions">
            <button type="button" class="patient-history-actions-toggle" data-d06-history-actions-toggle aria-haspopup="menu" aria-expanded="false" aria-controls="d06-history-actions-<?=$registro['id']?>" aria-label="Acciones para <?=$nombreModulo?>">
                <i data-feather="more-horizontal" aria-hidden="true"></i>
                <span class="patient-history-actions-label">Acciones</span>
                <span class="visually-hidden"> para <?=$nombreModulo?></span>
            </button>
            <div class="patient-history-actions-menu" id="d06-history-actions-<?=$registro['id']?>" role="menu" aria-label="Acciones para <?=$nombreModulo?>" hidden>
                <a href="<?=SERVIDOR?>clinica/<?=$registro['url']?>/paciente/<?=$data['id_paciente']?>" role="menuitem">
                    <i data-feather="edit" aria-hidden="true"></i>
                    <span>Editar módulo</span>
                </a>
                <a href="<?=SERVIDOR?>clinica/resumen/<?=$registro['url']?>/paciente/<?=$data['id_paciente']?>" role="menuitem">
                    <i data-feather="file-text" aria-hidden="true"></i>
                    <span>Ver resumen</span>
                </a>
            </div>
        </div>
    </td>
    </tr>  
    <?php endforeach; ?>

    <tr>
    <td></td>
    <td><b>Porcentaje total</b></td>
    <td colspan="2">
    <?php
    $promedioTotal = ($contadorModulos > 0) ? round($porcentajeAcumulado / $contadorModulos, 2) : 0;
    $promedioTexto = htmlspecialchars((string)$promedioTotal, ENT_QUOTES, 'UTF-8');
    echo '<div class="doctor-modules-progress" role="group" aria-label="Porcentaje total: '.$promedioTexto.'%">
    <span class="doctor-modules-progress-value">'.$promedioTexto.'%</span>
    <div class="progress bg-light rounded-pill doctor-modules-progress-bar">
    <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: '.$promedioTexto.'%;" aria-valuenow="'.$promedioTexto.'" aria-valuemin="0" aria-valuemax="100"><span class="visually-hidden">'.$promedioTexto.'% de cumplimiento total</span></div>
    </div>
    </div>';
    ?>
    </td>
    </tr>
    </tbody>

   
    </table>
    </div>
    </div>
    </div>

    </section>
    </main>
 
    <!----- FOOTER ---------->
    <?php include_once __DIR__ . '/../components/footer-doctor.php';?>

    </div>
    </div>

    <script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>    

    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS;?>main.js"></script>
    <script src="<?=RUTA_JS?>search-main.js"></script>


    <script>
    let table1 = document.querySelector('#table1');
    let dataTable = new simpleDatatables.DataTable(table1,{
	searchable: true,
    fixedHeight: false,
    columns: [
	{ select: [2,3], sortable: false },
	]
    });

    const d06HistoryActionState = {
        trigger: null,
        menu: null,
        template: null
    };

    function closeD06HistoryActions(restoreFocus) {
        const trigger = d06HistoryActionState.trigger;
        const menu = d06HistoryActionState.menu;
        const template = d06HistoryActionState.template;
        if (!trigger || !menu) return;

        menu.remove();
        trigger.setAttribute('aria-expanded', 'false');
        if (template) trigger.setAttribute('aria-controls', template.id);
        d06HistoryActionState.trigger = null;
        d06HistoryActionState.menu = null;
        d06HistoryActionState.template = null;

        if (restoreFocus) trigger.focus();
    }

    function positionD06HistoryActions(trigger, menu) {
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

    function openD06HistoryActions(trigger) {
        const menuId = trigger.getAttribute('aria-controls');
        const template = menuId ? document.getElementById(menuId) : null;
        if (!template) return;

        closeD06HistoryActions(false);
        const menu = template.cloneNode(true);
        menu.id = menuId + '-popup';
        menu.hidden = false;
        document.body.appendChild(menu);
        menu.setAttribute('data-open', 'true');
        trigger.setAttribute('aria-expanded', 'true');
        trigger.setAttribute('aria-controls', menu.id);
        d06HistoryActionState.trigger = trigger;
        d06HistoryActionState.menu = menu;
        d06HistoryActionState.template = template;
        positionD06HistoryActions(trigger, menu);
        menu.querySelector('[role="menuitem"]')?.focus();
    }

    function focusD06HistoryMenuItem(direction) {
        if (!d06HistoryActionState.menu) return;
        const items = [...d06HistoryActionState.menu.querySelectorAll('[role="menuitem"]')];
        const currentIndex = items.indexOf(document.activeElement);
        if (!items.length) return;
        const nextIndex = currentIndex < 0 ? 0 : (currentIndex + direction + items.length) % items.length;
        items[nextIndex].focus();
    }

    document.addEventListener('click', function (event) {
        const eventElement = event.target instanceof Element ? event.target : event.target?.parentElement;
        const trigger = eventElement?.closest('[data-d06-history-actions-toggle]');
        if (trigger && table1.contains(trigger)) {
            event.preventDefault();
            if (d06HistoryActionState.trigger === trigger) {
                closeD06HistoryActions(false);
            } else {
                openD06HistoryActions(trigger);
            }
            return;
        }

        if (d06HistoryActionState.menu && d06HistoryActionState.menu.contains(event.target)) return;
        if (d06HistoryActionState.trigger && !d06HistoryActionState.trigger.contains(event.target)) {
            closeD06HistoryActions(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        const eventElement = event.target instanceof Element ? event.target : null;
        const activeTrigger = eventElement?.closest('[data-d06-history-actions-toggle]');
        if (activeTrigger && table1.contains(activeTrigger) && event.key === 'ArrowDown') {
            event.preventDefault();
            if (d06HistoryActionState.trigger !== activeTrigger) openD06HistoryActions(activeTrigger);
            d06HistoryActionState.menu?.querySelector('[role="menuitem"]')?.focus();
            return;
        }

        if (event.key === 'Escape' && d06HistoryActionState.menu) {
            event.preventDefault();
            closeD06HistoryActions(true);
            return;
        }

        if (!d06HistoryActionState.menu || !d06HistoryActionState.menu.contains(document.activeElement)) return;
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            focusD06HistoryMenuItem(1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            focusD06HistoryMenuItem(-1);
        } else if (event.key === 'Home') {
            event.preventDefault();
            d06HistoryActionState.menu.querySelector('[role="menuitem"]')?.focus();
        } else if (event.key === 'End') {
            event.preventDefault();
            const items = d06HistoryActionState.menu.querySelectorAll('[role="menuitem"]');
            items[items.length - 1]?.focus();
        } else if (event.key === 'Tab') {
            closeD06HistoryActions(false);
        }
    });

    window.addEventListener('resize', function () {
        if (d06HistoryActionState.trigger && d06HistoryActionState.menu) {
            positionD06HistoryActions(d06HistoryActionState.trigger, d06HistoryActionState.menu);
        }
    });

    window.addEventListener('scroll', function () {
        if (d06HistoryActionState.trigger && d06HistoryActionState.menu) {
            positionD06HistoryActions(d06HistoryActionState.trigger, d06HistoryActionState.menu);
        }
    }, true);

    ['datatable.init', 'datatable.page', 'datatable.perpage', 'datatable.sort', 'datatable.search'].forEach(function (eventName) {
        dataTable.on(eventName, function () {
            closeD06HistoryActions(false);
        });
    });
    </script>

    </body>
    </html>

