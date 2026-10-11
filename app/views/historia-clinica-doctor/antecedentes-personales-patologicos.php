<?php 
use App\Config\Database;
use App\Models\AntecedentesPatologicosModel;
$bd = Database::getInstance();

$model = new AntecedentesPatologicosModel();
$preguntas_fijas = $model->obtenerPreguntasModulos(); 
foreach ($preguntas_fijas as $preg) {
echo $model->antecedentesPatologicos($data['idPaciente'], $preg);
}

$preguntas_enfermedad = $model->obtenerPreguntasModulosV2(); 
foreach ($preguntas_enfermedad as $preg_enfermedad ) {
echo $model->antecedentesPatologicosV2($data['idPaciente'], $preg_enfermedad);
}

$escape = static function ($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};
$patientId = (int) ($data['idPaciente'] ?? 0);
$patientName = $data['nombre'] ?? '';
$title = $data['title'] ?? 'Antecedentes Personales Patológicos';
$patientUrl = htmlspecialchars(SERVIDOR . 'clinica/paciente/' . $patientId, ENT_QUOTES, 'UTF-8');
    
?>

<!DOCTYPE html>
    <html lang="es">
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
    contenidoPreguntas(1);
    contenidoPreguntas(2);
    contenidoPreguntas(3);
    contenidoPreguntas(4);
    contenidoEnfermedades();
    });

    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas(idCuestionario) {

    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    fetch(`/buscar/contenido-preguntasV1-modulo-5/${idPaciente}/${idRol}/${idCuestionario}`)
    .then(response => response.text())
    .then(data => {
    // Insertar el contenido en el contenedor específico del módulo
    document.getElementById('contePreguntas_' + idCuestionario).innerHTML = data;
    feather.replace();
    });
    }
 
    // ------------------------------ CONTENIDO PREGUNTAS V2 ------------------------------
    function contenidoEnfermedades() {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    // Recuperar estado guardado
    const savedState = JSON.parse(localStorage.getItem('datatableEnfermedadesState')) || {};

    fetch(`/buscar/contenido-preguntasV2-modulo-5/${idPaciente}/${idRol}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('contePreguntasV2').innerHTML = data;
            feather.replace();

            const tabla = document.querySelector("#table_enfermedades");
            if (tabla) {
                const dataTable = new simpleDatatables.DataTable(tabla, {
                    searchable: true,
                    fixedHeight: true,
                    perPage: savedState.perPage || 10,
                    perPageSelect: [10, 20, 50],
                    columns: [
                        { select: 0, sort: savedState.sort || 'asc' },
                        { select: [2, 3, 4], sortable: false }
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
                    localStorage.setItem('datatableEnfermedadesState', JSON.stringify(savedState));
                });

                dataTable.on('datatable.perpage', function (perPage) {
                    savedState.perPage = perPage;
                    localStorage.setItem('datatableEnfermedadesState', JSON.stringify(savedState));
                });

                dataTable.on('datatable.sort', function (column, direction) {
                    savedState.sort = direction;
                    localStorage.setItem('datatableEnfermedadesState', JSON.stringify(savedState));
                });

                dataTable.on('datatable.search', function (query) {
                    savedState.search = query;
                    localStorage.setItem('datatableEnfermedadesState', JSON.stringify(savedState));
                });
            }
        });
    }   
    
    //---------- CONTROL SERVER ----------
    function gestionarAntecedentesPatologicos(url, parametros, callback, idUpdate = 0) {
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

    //---------- EDITAR ENFERMEDADES DEL PACIENTE ----------
    function respuestaPreguntaSelect (idPaciente, idRespuesta, elemento, idTema, idTipo, idRol, idUpdate = 0) {
    gestionarAntecedentesPatologicos(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-cuestionarioV1-modulo5`, {
    idPaciente,
    idRespuesta, 
    idTema,
    idTipo,
    detalle: elemento.value,
    idRol
    }, 
    () => contenidoPreguntas(idTema),
    idUpdate);
    } 

    //---------- EDITAR ENFERMEDADES DEL PACIENTE ----------
    function editarEnfermedadV2(idEnfermedad, elemento, parametro, idRol, idUpdate = 0) {
    gestionarAntecedentesPatologicos(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-cuestionarioV2-modulo5`, {
    idEnfermedad, detalle: elemento.value, edicion: parametro, idRol
    }, () => contenidoEnfermedades(),
    idUpdate);
    }

    </script>
    </head>

    <body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-module-page doctor-antecedentes-patologicos-page">
    <div class="LoaderPage"></div>
    <div id="app">
    <?=$data['sidebar'];?>
    <div id="main" data-rol="<?=$escape($data['idRol'] ?? '');?>" data-paciente="<?=$patientId;?>">
    <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

    <main class="main-content container-fluid doctor-pathological-content">
    <header class="doctor-page-hero doctor-pathological-hero" aria-labelledby="doctor-pathological-title">
    <div class="doctor-pathological-hero-copy">
    <p class="dashboard-eyebrow">HISTORIA CLÍNICA</p>
    <h1 id="doctor-pathological-title"><?=$escape($title);?></h1>
    <p>Consulta y registra los antecedentes personales patológicos del paciente.</p>
    <p class="doctor-pathological-patient-context">Paciente autorizado: <strong><?=$escape($patientName);?></strong></p>
    </div>
    <div class="doctor-pathological-hero-actions">
    <a class="doctor-pathological-action" href="<?=$patientUrl;?>">
    <i data-feather="arrow-left" aria-hidden="true"></i>
    <span>Volver al expediente</span>
    </a>
    </div>
    </header>

    <section class="doctor-pathological-sections" aria-label="Antecedentes Personales Patológicos">
    <article class="card doctor-pathological-card" aria-label="Preguntas de antecedentes patológicos">
    <div class="doctor-pathological-section-heading">
    <p class="dashboard-eyebrow">ANTECEDENTES PERSONALES</p>
    <h2>Enfermedades y antecedentes</h2>
    <p>A continuación, responde las preguntas indicando si presenta alguna de estas conductas, hábitos o enfermedades.</p>
    </div>
    <div class="doctor-pathological-card-body">
    <div id="contePreguntas_1"></div>
    <div id="contePreguntas_2"></div>
    <div id="contePreguntas_3"></div>
    <div id="contePreguntas_4"></div>
    </div>
    </article>

    <article class="card doctor-pathological-card" aria-label="Registro de enfermedades">
    <div class="doctor-pathological-section-heading">
    <p class="dashboard-eyebrow">PADECIMIENTOS</p>
    <h2>Enfermedades diagnosticadas</h2>
    <p>A continuación se presenta una serie de enfermedades. Indica si las padece o las ha padecido y el año aproximado del diagnóstico.</p>
    </div>
    <div class="doctor-pathological-card-body">
    <div id="contePreguntasV2">
    </div>
    </div>
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
