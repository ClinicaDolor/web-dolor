<?php 
use App\Config\Database;
use App\Models\AntecedenteFamiliarModel;
$bd = Database::getInstance();

$model = new AntecedenteFamiliarModel();
$enfermedades_fijas = $model->enfermedadesFijas(); 
foreach ($enfermedades_fijas as $enf) {
echo $model->antecedentesFamiliares($data['idPaciente'], $enf);
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
    contenidoPreguntas();
    contenidoComentario();
    });


    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas(idValor = 0) {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    // Recuperar estado guardado (puedes usar otra clave si es otra tabla)
    const savedState = JSON.parse(localStorage.getItem('datatablePreguntasState')) || {};

    fetch(`/buscar/contenido-preguntas-modulo-2/${idPaciente}/${idRol}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('contePreguntas').innerHTML = data;
            feather.replace();

            const tabla = document.querySelector("#table_enfermedades"); // Asegúrate que este ID coincida
            if (tabla) {
                const dataTable = new simpleDatatables.DataTable(tabla, {
                    searchable: true,
                    fixedHeight: true,
                    perPage: savedState.perPage || 10,
                    perPageSelect: [10, 20, 50],
                    columns: [
                        { select: 0, sort: savedState.sort || 'asc' },
                        { select: [1, 2, 3], sortable: false }
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

                // Guardar estado
                dataTable.on('datatable.page', function (page) {
                    savedState.page = page;
                    localStorage.setItem('datatablePreguntasState', JSON.stringify(savedState));
                });

                dataTable.on('datatable.perpage', function (perPage) {
                    savedState.perPage = perPage;
                    localStorage.setItem('datatablePreguntasState', JSON.stringify(savedState));
                });

                dataTable.on('datatable.sort', function (column, direction) {
                    savedState.sort = direction;
                    localStorage.setItem('datatablePreguntasState', JSON.stringify(savedState));
                });

                dataTable.on('datatable.search', function (query) {
                    savedState.search = query;
                    localStorage.setItem('datatablePreguntasState', JSON.stringify(savedState));
                });
            }
        });
    }

    // ---------- CONTENIDO DEL COMENTARIO ----------
    function contenidoComentario() {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');
    const idModulo = 2;

    fetch(`/buscar/contenido-comentarios-modulos/${idPaciente}/${idRol}/${idModulo}`)
    .then(response => response.text())
    .then(data => {

    document.getElementById('conteComentario').innerHTML = data;
    feather.replace();   

    const tabla = document.querySelector("#table_enfermedades");
    if (tabla) {
    dataTable = new simpleDatatables.DataTable(tabla,{
	searchable: true,
    fixedHeight: true,
	columns: [
	{
	select: 1, sort: "desc"
	},
    { select: [1,2,3,4], sortable: false },

	]
    });
    }   

    });
    }

    //---------- CONTROL SERVER ----------
    function gestionarEnfermedadPaciente(url, parametros, callback, idUpdate = 0) {
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

    //---------- AGREGAR ENFERMEDAD PACIENTE ----------
    function agregarEnfermedadPaciente(idPaciente, idRol) {
    gestionarEnfermedadPaciente(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/agregar-enfermedad-antecedentes`, { idPaciente, idRol }, () => contenidoPreguntas(1), 1);
    }

    //---------- ELIMINAR ENFERMEDADES DEL PACIENTE ----------
    function eliminarEnfermedadPaciente(idEnfermedad, idRol) {
    gestionarEnfermedadPaciente(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/eliminar-enfermedad-antecedentes`, { idEnfermedad, idRol }, () => contenidoPreguntas(1), 1);
    }

    //---------- EDITAR ENFERMEDADES DEL PACIENTE ----------
    function editarEnfermedad(idEnfermedad, elemento, parametro, idRol, idUpdate = 0) {
    gestionarEnfermedadPaciente(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-enfermedad-antecedentes`, {
        idEnfermedad, detalle: elemento.value, edicion: parametro, idRol
    }, () => contenidoPreguntas(0),
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
    <h3>Antecedentes familiares</h3>
    </div>
    
    <section class="section">
    
    <div class="row">

    <div class="col-12">
    <div class="card">

    <div id="contePreguntas"></div>


    </div>
    </div> 

    <!---------- CONTENIDO DE C0MENTARIOS COMENTARIOS ---------->
    <div class="col-12">
    <div class="card">
    <div class="card-header pb-0">
    <div class="row">

    <div id="seccion2" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-3">      
    <h8 class="text-primary fw-bold texto">
    <b>Si tiene alguna otra información o comentarios que desee compartir, por favor, indíquelo:</b>
    </h8>
    </div>

    </div>
    </div>

    
    <div class="card-body">
    <div id="conteComentario"></div>
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
