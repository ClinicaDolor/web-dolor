<?php 
use App\Config\Database;
use App\Models\AntecedentesNoPatologicosModel;
$bd = Database::getInstance();

$model = new AntecedentesNoPatologicosModel();
$preguntas_fijas = $model->obtenerPreguntasModulos(); 
foreach ($preguntas_fijas as $preg) {
echo $model->antecedentesNoPatologicos($data['idPaciente'], $preg);
}

?> 

    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <title><?=$data['title'];?></title>
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
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
    contenidoPreguntas(7);
    contenidoComentario();
    });

    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas(idCuestionario) {
    // Limpia las preguntas anteriores, si existen
    document.querySelectorAll('.pregunta-container').forEach(p => p.remove());

    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    fetch(`/buscar/contenido-preguntas-modulo-3/${idPaciente}/${idRol}/${idCuestionario}`)
    .then(response => response.text())
    .then(data => {
    // Insertar el contenido en el contenedor específico del módulo
    document.getElementById('contePreguntas_' + idCuestionario).innerHTML = data;
    feather.replace();

    });
    }

    // ---------- CONTENIDO DEL COMENTARIO ----------
    function contenidoComentario() {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');
    const idModulo = 3;

    fetch(`/buscar/contenido-comentarios-modulos/${idPaciente}/${idRol}/${idModulo}`)
    .then(response => response.text())
    .then(data => {

    document.getElementById('conteComentario').innerHTML = data;
    feather.replace();   

    });
    } 
 
    //---------- CONTROL SERVER ----------
    function gestionarAntecedentesNoPatologicos(url, parametros, callback, idUpdate = 0) {
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
    gestionarAntecedentesNoPatologicos(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-cuestionario-modulo3`, {
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
    <h3>Antecedentes Personales No Patológicos</h3>
    </div>
     
    <section class="section">

    <div class="row">

    <div class="col-12">
    <div class="card">
    <div class="card-header pb-0">
    <h8><b>A continuacion, responda las siguientes preguntas indicando si presenta alguna de estas conductas o hábitos:</b></h8>
    </div>

    <div class="card-body pb-0">
    <div class="row mt-3">
    <div  class="col-12" id="contePreguntas_1"></div>
    <div  class="col-12" id="contePreguntas_2"></div>
    <div  class="col-12" id="contePreguntas_3"></div>
    <div  class="col-12" id="contePreguntas_4"></div>
    <div  class="col-12" id="contePreguntas_5"></div>
    <div  class="col-12" id="contePreguntas_6"></div>
    <div  class="col-12" id="contePreguntas_7"></div>
    </div>
    </div>
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
    <script src="<?=RUTA_JS;?>main.js"></script>

    </body>
    </html>
