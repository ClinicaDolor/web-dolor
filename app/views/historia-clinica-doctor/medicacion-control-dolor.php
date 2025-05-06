<?php 
use App\Config\Database;
use App\Models\MedicacionDolorModel;
$bd = Database::getInstance();

$model = new MedicacionDolorModel();
$preguntas_fijas = $model->obtenerPreguntasModulos(); 
foreach ($preguntas_fijas as $preg) {
echo $model->medicacionDolorQuest($data['idPaciente'], $preg);
}

$modulos = $model->obtenerModulosM7();
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
    contenidoPreguntas(5);
    contenidoPreguntas(6);
    });
 
    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas(idCuestionario) {
    // Limpia las preguntas anteriores, si existen
    document.querySelectorAll('.pregunta-container').forEach(p => p.remove());

    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    fetch(`/buscar/contenido-preguntas-modulo-7/${idPaciente}/${idRol}/${idCuestionario}`)
    .then(response => response.text())
    .then(data => {
    // Insertar el contenido en el contenedor específico del módulo
    document.getElementById('contePreguntas_' + idCuestionario).innerHTML = data;
    feather.replace();

    const tabla = document.querySelector("#table_medicamentos_" + idCuestionario);
    if (tabla) {
    dataTable = new simpleDatatables.DataTable(tabla,{
	searchable: true,
    fixedHeight: true,
	columns: [
	{
	select: 0, sort: "asc"
	},
    { select: [2,3,4,5], sortable: false },

	]
    });
    }  
    });
    }

    //---------- CONTROL SERVER ----------
    function gestionarMedicacionControl(url, parametros, callback, idUpdate = 0) {
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
    gestionarMedicacionControl(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-cuestionario-modulo7`, {
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

    <div class="card">

    <!---------- CONTENEDOR DE PREGUNTAS ---------->
    <div id="preguntas-container">

    <div class="card-header">
    <div class="row">

    <h8 class="text-primary fw-bold ">
    A continuación, se mostrará el listado de medicamentos comúnmente utilizados para el control del dolor.
    Por favor, indique si el paciente ha utilizado alguno de ellos. En caso afirmativo, anote la dosis que utiliza, los resultados obtenidos y si actualmente lo sigue utilizando o no.
    </h8>

    <div class="col-12">
    <div id="mensaje" class="text-center text-danger mt-2"></div>
    </div>

    </div>
    </div>

    <div class="card-body">
    <div id="contePreguntas_1" ></div>
    <div id="contePreguntas_2" ></div>
    <div id="contePreguntas_3" ></div>
    <div id="contePreguntas_4" ></div>
    <div id="contePreguntas_5" ></div>
    <div id="contePreguntas_6" ></div>
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

    <script src="<?=RUTA_JS;?>voice-utilities.js"></script>
    <script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>    
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS;?>main.js"></script>
    </body>
    </html>
