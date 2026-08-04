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
    
?>

<!DOCTYPE html>
    <html lang="en">
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

    <body>
    <div class="LoaderPage"></div>
    <div id="app">
        
    <!---------- SIDEBAR ---------->
    <?=$data['sidebar'];?>
    
    <div id="main" data-rol="<?=$data['idRol'];?>" data-paciente="<?=$data['idPaciente'];?>">
    <nav class="navbar navbar-header navbar-expand navbar-light">
    <a class="sidebar-toggler"><span class="navbar-toggler-icon"></span></a>
    <button class="btn navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
    </button>
                
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav d-flex align-items-center navbar-light ms-auto">
    </ul>
    </div>
    </nav>

    <!---------- CONTENIDO DE LA PAGINA ---------->
    <div class="main-content container-fluid">

    <div class="page-title mb-4">     
    <h8><?=$data['nombre'];?></h8>
    <h3><?=$data['title'];?></h3>
    </div>
    
    <section class="section">
    <div class="row">

    <div class="col-12">
    <div class="card">
    <div class="card-header">
    <h8 class="text-primary fw-bold texto"><b>A continuacion, responda las siguientes preguntas indicando si presenta alguna de estas conductas o hábitos:</b></h8>
    </div>

    <div class="card-body pb-0">
    <div id="contePreguntas_1"></div>
    <div id="contePreguntas_2"></div>
    <div id="contePreguntas_3"></div>
    <div id="contePreguntas_4"></div>
    </div>
    </div>
    </div>

    <div class="col-12">
    <div class="card">
    <div class="card-header">
    <h8 class="text-primary fw-bold texto"><b>A continuación se presentará una serie de distintas enfermedades.
    Si usted padece o ha padecido alguna de ellas, por favor indique el año aproximado en que fue diagnosticado:</b></h8>
    </div>
    <div class="card-body pb-0">
    <div id="contePreguntasV2">
    </div>
    </div>
    </div>


    </div>

    </section>
    </div>

    <!----- FOOTER ---------->
    <?php include_once __DIR__ . '/../components/footer-mvsd.php';?>
    
    </div>
    </div>

    <script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>    
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS;?>main.js"></script>

    </body>
    </html>
