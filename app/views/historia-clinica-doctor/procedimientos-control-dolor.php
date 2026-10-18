<?php
use App\Models\ProcedimientosDolorModel;

$model = new ProcedimientosDolorModel();

$escape = static function ($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};
$patientId = (int) ($data['idPaciente'] ?? 0);
$patientName = trim((string) ($data['nombre'] ?? ''));
$title = $data['title'] ?? 'Procedimientos usados para controlar el dolor';
$patientUrl = $escape(SERVIDOR . 'clinica/paciente/' . $patientId);
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escape($title); ?></title>
    <link rel="shortcut icon" href="<?= RUTA_IMAGES ?>logo-clinica.png">
    <link rel="apple-touch-icon" href="<?= RUTA_IMAGES ?>logo-clinica.png">
    <link rel="stylesheet" href="<?= RUTA_CSS; ?>bootstrap.css">
    <link rel="stylesheet" href="<?= RUTA_PUBLIC; ?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?= RUTA_PUBLIC; ?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?= RUTA_CSS; ?>app.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?= RUTA_JS; ?>loader.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        contenidoPreguntas();
        contenidoTratamientos();
    });

    function prepararEncabezadoProcedimientos() {
        const contenedor = document.getElementById('contePreguntas');
        const encabezado = contenedor ? contenedor.querySelector('.card-header') : null;
        if (!encabezado) {
            return;
        }

        encabezado.classList.add('doctor-procedures-dynamic-header');
        const columnaPregunta = encabezado.querySelector('.col-11');
        const columnaAccion = encabezado.querySelector('.col-1');
        const boton = encabezado.querySelector('#btnAgregarCirugia');

        if (columnaPregunta) {
            columnaPregunta.classList.add('doctor-procedures-question-col');
        }
        if (columnaAccion) {
            columnaAccion.classList.add('doctor-procedures-add-col');
        }
        if (boton) {
            boton.type = 'button';
            boton.classList.remove('float-end');
            boton.classList.add('doctor-procedures-add-action');
            boton.setAttribute('aria-label', 'Agregar procedimiento');
            boton.setAttribute('title', 'Agregar procedimiento');
        }
    }

    function prepararEncabezadoTratamientos() {
        const contenedor = document.getElementById('conteTratamiento');
        const encabezado = contenedor ? contenedor.querySelector('.card-header') : null;
        if (encabezado) {
            encabezado.classList.add('doctor-treatments-dynamic-header');
        }
    }

    // ---------- CONTENIDO DE PROCEDIMIENTOS ----------
    function contenidoPreguntas() {
        const usuarioDiv = document.getElementById('main');
        const idPaciente = usuarioDiv.getAttribute('data-paciente');
        const idRol = usuarioDiv.getAttribute('data-rol');
        const stateKey = 'datatableProcedimientoState';
        const savedState = JSON.parse(localStorage.getItem(stateKey)) || {};

        fetch(`/buscar/contenido-preguntas-modulo-8/${idPaciente}/${idRol}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('contePreguntas').innerHTML = data;
                prepararEncabezadoProcedimientos();
                feather.replace();

                const tabla = document.querySelector('#table_procedimiento');
                if (tabla) {
                    const dataTable = new simpleDatatables.DataTable(tabla, {
                        searchable: true,
                        fixedHeight: false,
                        perPage: savedState.perPage || 10,
                        perPageSelect: [10, 20, 50],
                        columns: [
                            { select: 0, sort: savedState.sort || 'asc' },
                            { select: [1, 2, 3], sortable: false }
                        ]
                    });

                    dataTable.on('datatable.init', function () {
                        if (savedState.page) {
                            dataTable.page(savedState.page);
                        }
                        if (savedState.search) {
                            dataTable.input.value = savedState.search;
                            dataTable.search(savedState.search);
                        }
                    });

                    dataTable.on('datatable.page', page => {
                        savedState.page = page;
                        localStorage.setItem(stateKey, JSON.stringify(savedState));
                    });

                    dataTable.on('datatable.perpage', perPage => {
                        savedState.perPage = perPage;
                        localStorage.setItem(stateKey, JSON.stringify(savedState));
                    });

                    dataTable.on('datatable.sort', (column, direction) => {
                        savedState.sort = direction;
                        localStorage.setItem(stateKey, JSON.stringify(savedState));
                    });

                    dataTable.on('datatable.search', query => {
                        savedState.search = query;
                        localStorage.setItem(stateKey, JSON.stringify(savedState));
                    });
                }
            });
    }

    // ---------- CONTENIDO DE TRATAMIENTOS ----------
    function contenidoTratamientos() {
        const usuarioDiv = document.getElementById('main');
        const idPaciente = usuarioDiv.getAttribute('data-paciente');
        const idRol = usuarioDiv.getAttribute('data-rol');
        const stateKey = 'datatableTratamientosState';
        const savedState = JSON.parse(localStorage.getItem(stateKey)) || {};

        fetch(`/buscar/contenido-preguntas-tratamiento-modulo-8/${idPaciente}/${idRol}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('conteTratamiento').innerHTML = data;
                prepararEncabezadoTratamientos();
                feather.replace();

                const tabla = document.querySelector('#table_tratamientos');
                if (tabla) {
                    const dataTable = new simpleDatatables.DataTable(tabla, {
                        searchable: true,
                        fixedHeight: false,
                        perPage: savedState.perPage || 10,
                        perPageSelect: [10, 20, 50],
                        columns: [
                            { select: 0, sort: savedState.sort || 'asc' },
                            { select: [1, 2], sortable: false }
                        ]
                    });

                    dataTable.on('datatable.init', function () {
                        if (savedState.page) {
                            dataTable.page(savedState.page);
                        }
                        if (savedState.search) {
                            dataTable.input.value = savedState.search;
                            dataTable.search(savedState.search);
                        }
                    });

                    dataTable.on('datatable.page', page => {
                        savedState.page = page;
                        localStorage.setItem(stateKey, JSON.stringify(savedState));
                    });

                    dataTable.on('datatable.perpage', perPage => {
                        savedState.perPage = perPage;
                        localStorage.setItem(stateKey, JSON.stringify(savedState));
                    });

                    dataTable.on('datatable.sort', (column, direction) => {
                        savedState.sort = direction;
                        localStorage.setItem(stateKey, JSON.stringify(savedState));
                    });

                    dataTable.on('datatable.search', query => {
                        savedState.search = query;
                        localStorage.setItem(stateKey, JSON.stringify(savedState));
                    });
                }
            });
    }

    // ---------- CONTROL SERVER ----------
    function gestionarControlDolor(url, parametros, callback, idUpdate = 0) {
        if (idUpdate == 1) {
            $('.LoaderPage').show();
        }

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(parametros)
        }).then(res => res.json()).then(data => {
            if (idUpdate == 1) {
                $('.LoaderPage').fadeOut(1000);
            }
            if (data.resultado) {
                callback();
            } else {
                document.getElementById('mensaje').textContent = 'Error: ' + data.mensaje;
            }
        });
    }

    // ---------- ACCIONES DE PROCEDIMIENTOS Y TRATAMIENTOS ----------
    function agregarProcedimientosDolor(idPaciente, idRol) {
        gestionarControlDolor(`/${idRol === 'Paciente' ? 'historia-clinica' : 'clinica'}/paciente/agregar-procedimiento-dolor-modulo8`, {
            idPaciente,
            idRol
        },
        () => contenidoPreguntas(),
        1);
    }

    function editarProcedimientosDolor(idProcedimiento, elemento, parametro, idRol) {
        gestionarControlDolor(`/${idRol === 'Paciente' ? 'historia-clinica' : 'clinica'}/paciente/editar-procedimiento-dolor-modulo8`, {
            idProcedimiento,
            detalle: elemento.value,
            edicion: parametro,
            idRol
        },
        () => contenidoPreguntas());
    }

    function eliminarProcedimientosDolor(idProcedimiento, idRol) {
        gestionarControlDolor(`/${idRol === 'Paciente' ? 'historia-clinica' : 'clinica'}/paciente/eliminar-procedimiento-dolor-modulo8`, {
            idProcedimiento,
            idRol
        },
        () => contenidoPreguntas(),
        1);
    }

    function editarTratamientosDolor(idTratamiento, elemento, parametro, idRol, idUpdate = 0) {
        gestionarControlDolor(`/${idRol === 'Paciente' ? 'historia-clinica' : 'clinica'}/paciente/editar-tratamiento-dolor-modulo8`, {
            idTratamiento,
            detalle: elemento.value,
            edicion: parametro,
            idRol
        },
        () => contenidoTratamientos(),
        idUpdate);
    }
    </script>
</head>

<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-module-page doctor-procedures-pain-page">
    <div class="LoaderPage"></div>
    <div id="app">
        <?= $data['sidebar']; ?>

        <div id="main" data-rol="<?= $escape($data['idRol'] ?? ''); ?>" data-paciente="<?= $patientId; ?>">
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php'; ?>

            <main class="main-content container-fluid doctor-procedures-pain-content">
                <header class="doctor-page-hero doctor-procedures-pain-hero" aria-labelledby="doctor-procedures-pain-title">
                    <div class="doctor-procedures-pain-hero-copy">
                        <p class="dashboard-eyebrow">HISTORIA CLÍNICA</p>
                        <h1 id="doctor-procedures-pain-title"><?= $escape($title); ?></h1>
                        <p>Consulta y revisa los procedimientos y tratamientos utilizados para controlar el dolor.</p>
                        <?php if ($patientName !== ''): ?>
                            <p class="doctor-procedures-pain-patient-context">Paciente autorizado: <strong><?= $escape($patientName); ?></strong></p>
                        <?php endif; ?>
                    </div>
                    <div class="doctor-procedures-pain-hero-actions">
                        <a class="doctor-procedures-pain-action" data-doctor-back-action href="<?= $patientUrl; ?>">
                            <i data-feather="arrow-left" aria-hidden="true"></i>
                            <span>Volver al expediente</span>
                        </a>
                    </div>
                </header>

                <section class="doctor-procedures-pain-sections" aria-label="Procedimientos y tratamientos para controlar el dolor">
                    <div id="preguntas-container">
                        <article class="card doctor-procedures-pain-card" aria-label="Procedimientos utilizados para controlar el dolor">
                            <div id="contePreguntas"></div>
                        </article>
                    </div>

                    <div id="tratamientos-container">
                        <article class="card doctor-procedures-pain-card" aria-label="Tratamientos utilizados para controlar el dolor">
                            <div id="conteTratamiento"></div>
                        </article>
                    </div>
                </section>
            </main>

            <?php include_once __DIR__ . '/../components/footer-doctor.php'; ?>
        </div>
    </div>

    <script src="<?= RUTA_JS; ?>voice-utilities.js"></script>
    <script src="<?= RUTA_JS; ?>/feather-icons/feather.min.js"></script>
    <script src="<?= RUTA_PUBLIC; ?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?= RUTA_JS; ?>app.js"></script>
    <script src="<?= RUTA_PUBLIC; ?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?= RUTA_JS; ?>main.js"></script>
    <script src="<?= RUTA_JS; ?>search-main.js"></script>
</body>
</html>
