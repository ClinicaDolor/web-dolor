<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};
$patientId = (int) ($data['idPaciente'] ?? 0);
$patientName = $data['nombre'] ?? '';
$title = $data['title'] ?? 'Medicación Actual';
$patientUrl = htmlspecialchars(SERVIDOR . 'clinica/paciente/' . $patientId, ENT_QUOTES, 'UTF-8');
?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$escape($title);?></title>
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
    contenidoPreguntas();
    });
 
    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas() {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    // Recuperar estado guardado
    const savedState = JSON.parse(localStorage.getItem('datatableMedicacionState')) || {};

    fetch(`/buscar/contenido-preguntas-modulo-6/${idPaciente}/${idRol}`)
        .then(response => response.text())
        .then(data => {
            const contenedor = document.getElementById('contePreguntas');
            contenedor.innerHTML = data;
            prepararEncabezadoMedicacion();
            feather.replace();

            const tabla = document.querySelector("#table_medicacion");
            if (tabla) {
                const dataTable = new simpleDatatables.DataTable(tabla, {
                    searchable: true,
                    fixedHeight: false,
                    perPage: savedState.perPage || 10,
                    perPageSelect: [10, 20, 50],
                    columns: [
                        { select: 0, sort: savedState.sort || 'desc' },
                        { select: [2, 3, 4, 5], sortable: false }
                    ]
                });

                // Restaurar búsqueda y página
                dataTable.on('datatable.init', function () {
                    if (savedState.page) {
                        dataTable.page(savedState.page);
                    }
                    if (savedState.search) {
                        dataTable.input.value = savedState.search;
                        dataTable.search(savedState.search);
                    }
                });

                // Guardar estado en localStorage
                dataTable.on('datatable.page', function (page) {
                    savedState.page = page;
                    localStorage.setItem('datatableMedicacionState', JSON.stringify(savedState));
                });

                dataTable.on('datatable.perpage', function (perPage) {
                    savedState.perPage = perPage;
                    localStorage.setItem('datatableMedicacionState', JSON.stringify(savedState));
                });

                dataTable.on('datatable.sort', function (column, direction) {
                    savedState.sort = direction;
                    localStorage.setItem('datatableMedicacionState', JSON.stringify(savedState));
                });

                dataTable.on('datatable.search', function (query) {
                    savedState.search = query;
                    localStorage.setItem('datatableMedicacionState', JSON.stringify(savedState));
                });
            }
        });
    }

    function prepararEncabezadoMedicacion() {
    const contenedor = document.getElementById('contePreguntas');
    const encabezado = contenedor ? contenedor.querySelector('.card-header') : null;
    if (!encabezado) {
        return;
    }

    const columnaPregunta = encabezado.querySelector('.col-11');
    const columnaAccion = encabezado.querySelector('.col-1');
    const pregunta = columnaPregunta ? columnaPregunta.querySelector('h8') : null;
    const boton = encabezado.querySelector('#btnAgregarMedicamento');

    encabezado.classList.add('doctor-medication-dynamic-header');
    if (columnaPregunta) {
        columnaPregunta.classList.add('doctor-medication-question-col');
    }
    if (columnaAccion) {
        columnaAccion.classList.add('doctor-medication-add-col');
    }
    if (pregunta) {
        pregunta.classList.add('doctor-medication-question');
        if (!columnaPregunta.querySelector('.doctor-medication-card-title')) {
            const titulo = document.createElement('h2');
            titulo.className = 'doctor-medication-card-title';
            titulo.textContent = 'Medicación actual';
            columnaPregunta.prepend(titulo);
        }
    }
    if (boton) {
        boton.setAttribute('type', 'button');
        boton.setAttribute('aria-label', 'Agregar medicamento');
        boton.setAttribute('title', 'Agregar medicamento');
        boton.classList.remove('float-end');
        boton.classList.add('doctor-medication-add-action');
        if (!boton.querySelector('.doctor-medication-add-label')) {
            boton.innerHTML = '<i data-feather="plus" aria-hidden="true"></i><span class="doctor-medication-add-label">Agregar medicamento</span>';
        }
    }
    }
    
    //---------- CONTROL SERVER ----------
    function gestionarMedicacionActual(url, parametros, callback, idUpdate = 0) {
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

    //---------- AGREGAR CIRUGIA DEL PACIENTE ----------
    function agregarMedicamentoPaciente(idPaciente, idRol) {
    gestionarMedicacionActual(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/agregar-medicacion-actual`, {
    idPaciente,
    idRol
    }, 
    () => contenidoPreguntas(),
    1);
    }

    //---------- EDITAR CIRUGIA DEL PACIENTE ----------
    function editarMedicamento(idMedicamento, elemento, parametro, idRol) {
    gestionarMedicacionActual(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-medicacion-actual`, {
    idMedicamento, 
    detalle: elemento.value, 
    edicion: parametro, 
    idRol
    }, () => contenidoPreguntas());
    }

    //---------- ELIMINAR CIRUGIA DEL PACIENTE ----------
    function eliminarMedicamentoPacientes(idMedicamento, idRol) {
    gestionarMedicacionActual(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/eliminar-medicacion-actual`, { 
    idMedicamento, idRol }, () => contenidoPreguntas(1),
    1);
    }
    </script>
    </head>

    <body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-module-page doctor-medication-actual-page">
    <div class="LoaderPage"></div>
    <div id="app">
        
    <!---------- SIDEBAR ---------->
    <?=$data['sidebar'];?>
    
    <div id="main" data-rol="<?=$escape($data['idRol'] ?? '');?>" data-paciente="<?=$patientId;?>">
    <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

    <main class="main-content container-fluid doctor-medication-content">
    <header class="doctor-page-hero doctor-medication-hero" aria-labelledby="doctor-medication-title">
    <div class="doctor-medication-hero-copy">
    <p class="dashboard-eyebrow">HISTORIA CLÍNICA</p>
    <h1 id="doctor-medication-title"><?=$escape($title);?></h1>
    <p>Consulta y registra la medicación actual del paciente.</p>
    <p class="doctor-medication-patient-context">Paciente autorizado: <strong><?=$escape($patientName);?></strong></p>
    </div>
    <div class="doctor-medication-hero-actions">
    <a class="doctor-medication-action" href="<?=$patientUrl;?>">
    <i data-feather="arrow-left" aria-hidden="true"></i>
    <span>Volver al expediente</span>
    </a>
    </div>
    </header>

    <section class="doctor-medication-sections" aria-label="Medicación actual">
    <article class="card doctor-medication-card" aria-label="Registro de medicación actual">
    <div id="contePreguntas"></div>
    </article>
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
    <script src="<?=RUTA_JS;?>search-main.js"></script>

    </body>
    </html>
