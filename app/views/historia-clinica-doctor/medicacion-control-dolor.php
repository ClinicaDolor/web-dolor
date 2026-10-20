<?php
use App\Models\MedicacionDolorModel;
use App\Services\ClinicalSummarySecurity;

$model = new MedicacionDolorModel();

$modulos = $model->obtenerModulosM7();
$escape = static function ($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};
$patientId = (int) ($data['idPaciente'] ?? 0);
$m03CsrfToken = ClinicalSummarySecurity::captureCsrfToken($patientId);
$patientName = trim((string) ($data['nombre'] ?? ''));
$title = $data['title'] ?? 'Medicamentos para controlar el dolor';
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
        for (let i = 1; i <= 6; i++) {
            contenidoPreguntas(i);
        }
    });

    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas(idCuestionario) {
        const contenedor = document.getElementById('contePreguntas_' + idCuestionario);
        if (contenedor) {
            contenedor.innerHTML = '';
        }

        const usuarioDiv = document.getElementById('main');
        const idPaciente = usuarioDiv.getAttribute('data-paciente');
        const idRol = usuarioDiv.getAttribute('data-rol');
        const stateKey = `datatableMedicamentosState_${idCuestionario}`;
        const savedState = JSON.parse(localStorage.getItem(stateKey)) || {};

        fetch(`/buscar/contenido-preguntas-modulo-7/${idPaciente}/${idRol}/${idCuestionario}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('contePreguntas_' + idCuestionario).innerHTML = data;
                feather.replace();

                const tabla = document.querySelector(`#table_medicamentos_${idCuestionario}`);
                if (tabla) {
                    const dataTable = new simpleDatatables.DataTable(tabla, {
                        searchable: true,
                        fixedHeight: false,
                        perPage: savedState.perPage || 10,
                        perPageSelect: [10, 20, 50],
                        columns: [
                            { select: 0, sort: savedState.sort || 'asc' },
                            { select: [2, 3, 4, 5], sortable: false }
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

                    dataTable.on('datatable.page', function (page) {
                        savedState.page = page;
                        localStorage.setItem(stateKey, JSON.stringify(savedState));
                    });

                    dataTable.on('datatable.perpage', function (perPage) {
                        savedState.perPage = perPage;
                        localStorage.setItem(stateKey, JSON.stringify(savedState));
                    });

                    dataTable.on('datatable.sort', function (column, direction) {
                        savedState.sort = direction;
                        localStorage.setItem(stateKey, JSON.stringify(savedState));
                    });

                    dataTable.on('datatable.search', function (query) {
                        savedState.search = query;
                        localStorage.setItem(stateKey, JSON.stringify(savedState));
                    });
                }
            });
    }

    // ---------- CONTROL SERVER ----------
    function gestionarMedicacionControl(url, parametros, callback, idUpdate = 0) {
        if (idUpdate == 1) {
            $('.LoaderPage').show();
        }

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-M03-CSRF': document.getElementById('main')?.dataset.m03Csrf || '' },
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

    // ---------- EDITAR MEDICACIÓN PARA CONTROL DEL DOLOR ----------
    function respuestaPreguntaSelect(idPaciente, idRespuesta, elemento, idTema, idTipo, idRol, idUpdate = 0) {
        const questionRow = elemento?.closest('[data-id-pregunta]');
        const idPregunta = Number(questionRow?.dataset.idPregunta || 0);
        gestionarMedicacionControl(`/${idRol === 'Paciente' ? 'historia-clinica' : 'clinica'}/paciente/editar-cuestionario-modulo7`, {
            idPaciente,
            idRespuesta,
            idPregunta,
            idTema,
            idTipo,
            detalle: elemento.value,
            idRol
        },
        () => contenidoPreguntas(idTema),
        idUpdate);
    }
    </script>
</head>

<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-module-page doctor-medication-pain-page">
    <div class="LoaderPage"></div>
    <div id="app">
        <?= $data['sidebar']; ?>

        <div id="main" data-rol="<?= $escape($data['idRol'] ?? ''); ?>" data-paciente="<?= $patientId; ?>" data-m03-csrf="<?= $escape($m03CsrfToken); ?>">
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php'; ?>

            <main class="main-content container-fluid doctor-medication-pain-content">
                <header class="doctor-page-hero doctor-medication-pain-hero" aria-labelledby="doctor-medication-pain-title">
                    <div class="doctor-medication-pain-hero-copy">
                        <p class="dashboard-eyebrow">HISTORIA CLÍNICA</p>
                        <h1 id="doctor-medication-pain-title"><?= $escape($title); ?></h1>
                        <p>Consulta y revisa los medicamentos utilizados para el control del dolor.</p>
                        <?php if ($patientName !== ''): ?>
                            <p class="doctor-medication-pain-patient-context">Paciente autorizado: <strong><?= $escape($patientName); ?></strong></p>
                        <?php endif; ?>
                    </div>
                    <div class="doctor-medication-pain-hero-actions">
                        <a class="doctor-medication-pain-action" data-doctor-back-action href="<?= $patientUrl; ?>">
                            <i data-feather="arrow-left" aria-hidden="true"></i>
                            <span>Volver al expediente</span>
                        </a>
                    </div>
                </header>

                <section class="doctor-medication-pain-sections" aria-labelledby="doctor-medication-pain-section-title">
                    <article class="card doctor-medication-pain-card">
                        <div class="doctor-medication-pain-section-heading">
                            <div>
                                <p class="dashboard-eyebrow">CONTROL DEL DOLOR</p>
                                <h2 id="doctor-medication-pain-section-title">Medicamentos utilizados para controlar el dolor</h2>
                                <p class="doctor-medication-pain-instruction">
                                    A continuación, se mostrará el listado de medicamentos comúnmente utilizados para el control del dolor.
                                    Por favor, indique si el paciente ha utilizado alguno de ellos. En caso afirmativo, anote la dosis que utiliza,
                                    los resultados obtenidos y si actualmente lo sigue utilizando o no.
                                </p>
                            </div>
                            <div id="mensaje" class="doctor-medication-pain-feedback" role="status" aria-live="polite"></div>
                        </div>

                        <div class="doctor-medication-pain-card-body" id="preguntas-container">
                            <div id="contePreguntas_1"></div>
                            <div id="contePreguntas_2"></div>
                            <div id="contePreguntas_3"></div>
                            <div id="contePreguntas_4"></div>
                            <div id="contePreguntas_5"></div>
                            <div id="contePreguntas_6"></div>
                        </div>
                    </article>
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
