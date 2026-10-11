<?php
use App\Helpers\CalculadoraEdad;

$model = new CalculadoraEdad();
$registros = is_array($data['registros'] ?? null) ? $data['registros'] : [];
$totalPacientes = (int)($data['total_pacientes'] ?? count($registros));
?>
<!DOCTYPE html>
<html lang="es-MX" class="doctor-patients-document">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($data['title'] ?? 'Pacientes', ENT_QUOTES, 'UTF-8');?></title>
<link rel="shortcut icon" href="<?=RUTA_IMAGES?>/logo-clinica.png">
<link rel="apple-touch-icon" href="<?=RUTA_IMAGES?>/logo-clinica.png">
<link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
<link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?=RUTA_JS;?>loader.js"></script>
</head>

<body class="app-shell-body app-shell-doctor doctor-patients-page">
<div class="LoaderPage"></div>
<div id="app">
<?=$data['sidebar'];?>

<div id="main">
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

<main class="main-content container-fluid" id="main-content">
<header class="doctor-page-hero">
    <div>
        <p class="dashboard-eyebrow">DIRECTORIO CLÍNICO</p>
        <h1>Pacientes</h1>
        <p>Consulta y gestiona los expedientes registrados en tu clínica.</p>
    </div>
    <a href="<?=SERVIDOR?>clinica/paciente/nuevo" class="btn btn-primary doctor-page-hero-action">
        <i data-feather="user-plus" aria-hidden="true"></i>
        <span>Nuevo paciente</span>
    </a>
</header>

<section class="doctor-patient-list-card" aria-labelledby="patient-list-title">
    <div class="doctor-patient-list-heading">
        <div>
            <p class="dashboard-eyebrow">REGISTROS DISPONIBLES</p>
            <h2 id="patient-list-title">Lista de pacientes</h2>
            <p class="doctor-patient-list-helper">Usa el buscador para localizar un expediente sin salir de esta vista.</p>
        </div>
        <span class="doctor-patient-count" aria-label="<?=$totalPacientes;?> <?=($totalPacientes === 1) ? 'paciente registrado' : 'pacientes registrados';?>">
            <strong><?=$totalPacientes;?></strong>
            <span><?=($totalPacientes === 1) ? 'paciente' : 'pacientes';?></span>
        </span>
    </div>

    <div class="doctor-patient-table-wrap">
        <div class="table-responsive">
            <table class="table table-striped table-hover doctor-patient-table" id="table1">
                <caption class="visually-hidden">Pacientes registrados en la clínica y acciones disponibles por expediente</caption>
                <thead>
                    <tr>
                        <th class="text-center" scope="col">Id</th>
                        <th class="text-center" scope="col">Fecha alta</th>
                        <th scope="col">Nombre completo</th>
                        <th class="text-center" scope="col">Edad</th>
                        <th class="text-center" scope="col">Sexo</th>
                        <th class="text-center" scope="col">Fecha nacimiento</th>
                        <th class="text-center doctor-patient-actions-header" scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($registros as $registro):
                    $id = (int)($registro['id'] ?? 0);
                    $nombre = trim(($registro['nombres'] ?? '') . ' ' . ($registro['apellido_paterno'] ?? '') . ' ' . ($registro['apellido_materno'] ?? ''));
                    $sexo = (string)($registro['sexo'] ?? '');
                    $sexoLabel = $sexo === 'F' ? 'Femenino' : ($sexo === 'M' ? 'Masculino' : $sexo);
                ?>
                    <tr>
                        <td class="text-center"><span class="doctor-patient-id">#<?=htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8');?></span></td>
                        <td class="text-center"><?=htmlspecialchars((string)($registro['fecha_alta'] ?? ''), ENT_QUOTES, 'UTF-8');?></td>
                        <td><strong class="doctor-patient-name"><?=htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');?></strong></td>
                        <td class="text-center"><?=htmlspecialchars((string)$model->calcularEdad($registro['fecha_nacimiento'] ?? ''), ENT_QUOTES, 'UTF-8');?> años</td>
                        <td class="text-center"><span class="doctor-patient-sex doctor-patient-sex-<?=strtolower($sexo);?>" title="<?=htmlspecialchars($sexoLabel, ENT_QUOTES, 'UTF-8');?>"><?=htmlspecialchars($sexoLabel, ENT_QUOTES, 'UTF-8');?></span></td>
                        <td class="text-center"><?=htmlspecialchars((string)($registro['fecha_nacimiento'] ?? ''), ENT_QUOTES, 'UTF-8');?></td>
                        <td class="text-center doctor-patient-actions-cell">
                            <div class="doctor-patient-actions">
                                <button type="button" class="doctor-patient-actions-toggle" data-patient-actions-toggle aria-haspopup="menu" aria-expanded="false" aria-controls="patient-actions-<?=$id;?>">
                                    <i data-feather="more-horizontal" aria-hidden="true"></i>
                                    <span>Acciones</span>
                                    <span class="visually-hidden"> para <?=htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');?></span>
                                </button>
                                <div class="doctor-patient-actions-menu" id="patient-actions-<?=$id;?>" role="menu" aria-label="Acciones para <?=htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');?>" hidden>
                                    <a href="<?=SERVIDOR?>clinica/paciente/<?=$id;?>" role="menuitem">
                                        <i data-feather="folder" aria-hidden="true"></i>
                                        <span>Abrir expediente</span>
                                    </a>
                                    <a href="<?=SERVIDOR?>clinica/paciente/pin/<?=$id;?>" role="menuitem">
                                        <i data-feather="key" aria-hidden="true"></i>
                                        <span>Gestionar PIN</span>
                                    </a>
                                    <a href="<?=SERVIDOR?>clinica/modulos/paciente/<?=$id;?>" role="menuitem">
                                        <i data-feather="grid" aria-hidden="true"></i>
                                        <span>Abrir historia clínica</span>
                                    </a>
                                    <a href="<?=SERVIDOR?>clinica/paciente/editar/<?=$id;?>" role="menuitem">
                                        <i data-feather="edit-3" aria-hidden="true"></i>
                                        <span>Editar paciente</span>
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPacientes === 0): ?>
        <div class="doctor-patient-empty" role="status">
            <span class="doctor-patient-empty-icon"><i data-feather="users" aria-hidden="true"></i></span>
            <strong>No hay pacientes registrados</strong>
            <p>Cuando registres un paciente, aparecerá en este directorio.</p>
            <a href="<?=SERVIDOR?>clinica/paciente/nuevo" class="btn btn-primary">Registrar paciente</a>
        </div>
    <?php endif; ?>
</section>
</main>

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
document.addEventListener('DOMContentLoaded', function () {
    const tableElement = document.querySelector('#table1');
    if (!tableElement || typeof simpleDatatables === 'undefined') return;

    const savedState = JSON.parse(localStorage.getItem('datatableState') || '{}');
    const dataTable = new simpleDatatables.DataTable(tableElement, {
        searchable: true,
        fixedHeight: true,
        perPage: savedState.perPage || 10,
        perPageSelect: [10, 20, 50],
        columns: [
            { select: 1, sort: savedState.sort || 'desc' },
            { select: 6, sortable: false }
        ]
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
        localStorage.setItem('datatableState', JSON.stringify(savedState));
    });
    dataTable.on('datatable.perpage', function (perPage) {
        savedState.perPage = perPage;
        localStorage.setItem('datatableState', JSON.stringify(savedState));
    });
    dataTable.on('datatable.sort', function (column, direction) {
        savedState.sort = direction;
        localStorage.setItem('datatableState', JSON.stringify(savedState));
    });
    dataTable.on('datatable.search', function (query) {
        savedState.search = query;
        localStorage.setItem('datatableState', JSON.stringify(savedState));
    });

    const actionState = {
        trigger: null,
        menu: null,
        template: null
    };

    function closePatientActions(restoreFocus) {
        const trigger = actionState.trigger;
        const menu = actionState.menu;
        const template = actionState.template;
        if (!trigger || !menu) return;

        menu.remove();
        trigger.setAttribute('aria-expanded', 'false');
        if (template) trigger.setAttribute('aria-controls', template.id);
        actionState.trigger = null;
        actionState.menu = null;
        actionState.template = null;

        if (restoreFocus) trigger.focus();
    }

    function positionPatientActions(trigger, menu) {
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

    function openPatientActions(trigger) {
        const menuId = trigger.getAttribute('aria-controls');
        const template = menuId ? document.getElementById(menuId) : null;
        if (!template) return;

        closePatientActions(false);
        const menu = template.cloneNode(true);
        menu.id = menuId + '-popup';
        menu.hidden = false;
        document.body.appendChild(menu);
        menu.setAttribute('data-open', 'true');
        trigger.setAttribute('aria-expanded', 'true');
        trigger.setAttribute('aria-controls', menu.id);
        actionState.trigger = trigger;
        actionState.menu = menu;
        actionState.template = template;
        positionPatientActions(trigger, menu);
        const firstItem = menu.querySelector('[role="menuitem"]');
        if (firstItem) firstItem.focus();
    }

    function focusMenuItem(direction) {
        if (!actionState.menu) return;
        const items = [...actionState.menu.querySelectorAll('[role="menuitem"]')];
        const currentIndex = items.indexOf(document.activeElement);
        if (!items.length) return;
        const nextIndex = currentIndex < 0 ? 0 : (currentIndex + direction + items.length) % items.length;
        items[nextIndex].focus();
    }

    document.addEventListener('click', function (event) {
        const eventElement = event.target instanceof Element ? event.target : event.target?.parentElement;
        const trigger = eventElement?.closest('[data-patient-actions-toggle]');
        if (trigger && tableElement.contains(trigger)) {
            event.preventDefault();
            if (actionState.trigger === trigger) {
                closePatientActions(false);
            } else {
                openPatientActions(trigger);
            }
            return;
        }

        if (actionState.menu && actionState.menu.contains(event.target)) return;
        if (actionState.trigger && !actionState.trigger.contains(event.target)) closePatientActions(false);
    });

    document.addEventListener('keydown', function (event) {
        const eventElement = event.target instanceof Element ? event.target : null;
        const activeTrigger = eventElement?.closest('[data-patient-actions-toggle]');
        if (activeTrigger && tableElement.contains(activeTrigger) && event.key === 'ArrowDown') {
            event.preventDefault();
            if (actionState.trigger !== activeTrigger) openPatientActions(activeTrigger);
            actionState.menu?.querySelector('[role="menuitem"]')?.focus();
            return;
        }

        if (event.key === 'Escape' && actionState.menu) {
            event.preventDefault();
            closePatientActions(true);
            return;
        }

        if (!actionState.menu || !actionState.menu.contains(document.activeElement)) return;
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            focusMenuItem(1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            focusMenuItem(-1);
        } else if (event.key === 'Home') {
            event.preventDefault();
            actionState.menu.querySelector('[role="menuitem"]')?.focus();
        } else if (event.key === 'End') {
            event.preventDefault();
            const items = actionState.menu.querySelectorAll('[role="menuitem"]');
            items[items.length - 1]?.focus();
        } else if (event.key === 'Tab') {
            closePatientActions(false);
        }
    });

    window.addEventListener('resize', function () {
        if (actionState.trigger && actionState.menu) positionPatientActions(actionState.trigger, actionState.menu);
    });
    window.addEventListener('scroll', function () {
        if (actionState.trigger && actionState.menu) positionPatientActions(actionState.trigger, actionState.menu);
    }, true);

    ['datatable.init', 'datatable.page', 'datatable.perpage', 'datatable.sort', 'datatable.search'].forEach(function (eventName) {
        dataTable.on(eventName, function () {
            closePatientActions(false);
        });
    });
});
</script>
</body>
</html>
