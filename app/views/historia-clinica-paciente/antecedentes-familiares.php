<?php 
use App\Config\Database;
use App\Models\PacienteModulosModelo;
use App\Models\AntecedenteFamiliarModel;
$bd = Database::getInstance();

$model = new AntecedenteFamiliarModel();

$enfermedades_fijas = $model->enfermedadesFijas(); 
foreach ($enfermedades_fijas as $enf) {
echo $model->antecedentesFamiliares($data['idPaciente'], $enf);
}

$model2 = new PacienteModulosModelo();
$botonFinalizar = $model2->botonFinalizarModulo(2,$data['idPaciente'],$data['idRol']);

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
 
    <style>
    /* Contenedor de preguntas visible inicialmente */
    #preguntas-container { display: block; }
    .pregunta-container { display: none; }
    .pregunta-container.active { display: block; }
    /* Sección de comentarios oculta inicialmente */
    #comentarios-container { display: none; }
    </style>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
    const seccionActual = localStorage.getItem("seccionActual");
    
    if (!seccionActual || seccionActual === "preguntas") {
    contenidoPreguntas();
    } else if (seccionActual === "comentarios") {
    finalizarPreguntas();
    }

    });
    
    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas(idValor = 0) {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    fetch(`/buscar/contenido-preguntas-modulo-2/${idPaciente}/${idRol}`)
    .then(response => response.text())
    .then(data => {
    document.getElementById('contePreguntas').innerHTML = data;
    feather.replace();
            
    cargarProgreso(idValor);
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

    function guardarProgreso(index) {
    localStorage.setItem("preguntaActual", index);
    }

    function cargarProgreso(idValor) {
    let indexGuardado = localStorage.getItem("preguntaActual");
    indexGuardado = indexGuardado ? parseInt(indexGuardado) : 0;

    const preguntas = document.querySelectorAll('.pregunta-container');
    
    // Calcular el nuevo índice dentro de los límites permitidos
    let nuevoIndex = indexGuardado + idValor;
    if (nuevoIndex < 0) nuevoIndex = 0; // Evita ir antes de la primera pregunta
    if (nuevoIndex > preguntas.length) nuevoIndex = preguntas.length - 1; // Evita ir después de la última

    // Remover la clase 'active' de todas las preguntas
    preguntas.forEach(pregunta => pregunta.classList.remove('active'));

    // Activar la nueva pregunta
    preguntas[nuevoIndex].classList.add('active');

    // Mostrar el botón si es la última pregunta
    document.getElementById('btnAgregarEnfermedad').style.display =
    nuevoIndex === preguntas.length - 1 ? 'block' : 'none';

    // Guardar el nuevo progreso
    guardarProgreso(nuevoIndex);
    }

    function siguientePregunta() {
    const preguntas = document.querySelectorAll('.pregunta-container');
    let activeIndex = -1;

    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);

    preguntas.forEach((pregunta, index) => {
    if (pregunta.classList.contains('active')) {
    activeIndex = index;
    pregunta.classList.remove('active');
    }
    });

    if (activeIndex < preguntas.length - 1) {
    const nuevoIndice = activeIndex + 1;
    preguntas[nuevoIndice].classList.add('active');
    guardarProgreso(nuevoIndice); // Guarda el progreso al avanzar
    }

    // Mostrar el botón solo si se está en la última pregunta
    if (activeIndex + 1 === preguntas.length - 1) {
    document.getElementById('btnAgregarEnfermedad').style.display = 'block';
    } else {
    document.getElementById('btnAgregarEnfermedad').style.display = 'none';
    }
    }

    function anteriorPregunta() {
    const preguntas = document.querySelectorAll('.pregunta-container');
    let activeIndex = -1;

    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);

    preguntas.forEach((pregunta, index) => {
    if (pregunta.classList.contains('active')) {
    activeIndex = index;
    pregunta.classList.remove('active');
    }
    });

    if (activeIndex > 0) {
    const nuevoIndice = activeIndex - 1;
    preguntas[nuevoIndice].classList.add('active');
    guardarProgreso(nuevoIndice); // Guarda el progreso al retroceder
    }

    // Ocultar el botón si no estamos en la última pregunta
    document.getElementById('btnAgregarEnfermedad').style.display = 'none';
    }

    //---------- MUESTRA LA SECCION DE PREGUNTAS Y COMENTARIOS ----------
    function seccionPreguntas() {
    document.getElementById("preguntas-container").style.display = "block";
    document.getElementById("comentarios-container").style.display = "none";
    localStorage.setItem("seccionActual", "preguntas");
    contenidoPreguntas();
    }

    function finalizarPreguntas() {
    document.getElementById("preguntas-container").style.display = "none";
    document.getElementById("comentarios-container").style.display = "block";
    localStorage.setItem("seccionActual", "comentarios");
    contenidoComentario();
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
 
    //---------- AGREGAR COMENTARIO DEL MODULO ----------
    function agregarComentario(idModulo, idPaciente, idRol) {
    let comentario = document.getElementById('comentarioModulos').value;
    //if (!comentario) return document.getElementById('comentarioModulos').style.border = '2px solid #A52525';
    gestionarEnfermedadPaciente(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/agregar-comentario-modulo`, { idModulo, idPaciente, comentarioModulos: comentario, idRol }, () => FinalizarModuloPaciente(idModulo, idPaciente));
    }

    //---------- FINALIZAR MODULO DEL PACIENTE----------
    function FinalizarModuloPaciente(idModulo, idPaciente) {
    gestionarEnfermedadPaciente('/historia-clinica/paciente/finalizar-modulo-paciente', { idModulo, idPaciente }, () => window.location.href = '/historia-clinica');
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
    <div class="card-header pb-0">
    <div class="row">

    <div id="seccion1" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-3">
    <img src="<?=RUTA_IMAGES ?>/iconos/audio.png" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion1">

    <h8 class="text-primary fw-bold texto">
    <b>A continuación, le preguntaremos si existen antecedentes familiares de alguna de las siguientes enfermedades. <br>Por favor, mencione si alguno de sus familiares cercanos, como abuelos, padres, hermanos, etc., ha padecido alguna de ellas:</b>
    </h8>
    </div>

    <div class="col-12 col-md-1 d-flex justify-content-end align-items-center">
    <button id="btnAgregarEnfermedad" onclick="agregarEnfermedadPaciente(<?=$data['idPaciente'];?>,'<?=$data['idRol'];?>')" class="btn icon btn-success float-end" style="display: none;">
    <i data-feather="plus" width="20"></i>
    </button>
    </div>

    <div class="col-12">
    <div id="mensaje" class="text-center text-danger mt-2"></div>
    </div>

    </div>
    </div>

    <div class="card-body">
    <div id="contePreguntas"></div>
    </div>
    </div>


    <!---------- CONTENEDOR DE COMENTARIO ---------->
    <div id="comentarios-container">

    <div class="card-header pb-0">
    <div class="row">

    <div id="seccion2" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-3">
    <img src="<?=RUTA_IMAGES ?>/iconos/audio.png" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion100">
      
    <h8 class="text-primary fw-bold texto">
    <b>Si tiene alguna otra información o comentarios que desee compartir, por favor, indíquelo:</b>
    </h8>
    </div>

    <div class="col-12">
    <div id="mensaje" class="text-center text-danger mt-2"></div>
    </div>

    </div>
    </div>

    <div class="card-body">
    <div id="conteComentario"></div>

    <div class="mt-3 d-flex justify-content-between">
    <button class="btn btn-secondary" onclick="seccionPreguntas()"><i data-feather="chevron-left"></i> Preguntas</button>
    <?=$botonFinalizar?>
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
