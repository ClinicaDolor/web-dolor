<?php 
use App\Config\Database;
use App\Models\PacienteModulosModelo;
use App\Models\AntecedentesNoPatologicosModel;
$bd = Database::getInstance();

$model = new AntecedentesNoPatologicosModel();
$preguntas_fijas = $model->obtenerPreguntasModulos(); 
foreach ($preguntas_fijas as $preg) {
echo $model->antecedentesNoPatologicos($data['idPaciente'], $preg);
}

$modulos = $model->obtenerModulos();

$model2 = new PacienteModulosModelo();
$botonFinalizar = $model2->botonFinalizarModulo(3,$data['idPaciente'],$data['idRol']);

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
    // Pasar los datos de PHP a JavaScript
    const modulos = <?=json_encode($modulos)?>;

    // Crear el arreglo de temas (adaptado al formato que usas en el frontend)
    const temas = Object.keys(modulos).map(id => {
    return { id: id, nombre: modulos[id] };
    });

    // Al cargar la página se carga el módulo correspondiente (por defecto "Tabaquismo")
    document.addEventListener("DOMContentLoaded", function() {
    const seccionActual = localStorage.getItem("seccionActual") || "Tabaquismo";
    localStorage.setItem("seccionActual", seccionActual);
    let temaActual = temas.find(t => t.nombre === seccionActual);

    if (!seccionActual || seccionActual === "comentarios") {
    finalizarPreguntas();
    } else {
    contenidoPreguntas(temaActual.id);
    }

    // Inicializar las preguntas con valor 0 si no están configuradas en localStorage
    for (let i = 1; i <= 7; i++) {
    if (localStorage.getItem(`preguntaActual_${i}`) === null) {
    localStorage.setItem(`preguntaActual_${i}`, 0);
    }
    }

    });

    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas(idCuestionario, idValor = 0) {
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

    // Reactivar el IntersectionObserver para las nuevas secciones (si lo utilizas)
    document.querySelectorAll('.sectionQuestion').forEach(section => {
    observer.observe(section);
    });

    // Cargar el progreso para el módulo actual
    cargarProgreso(idCuestionario, idValor);
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

    // ---------- GUARDAR Y CARGAR PROGRESO ----------
    function guardarProgreso(index, idCuestionario) {
    localStorage.setItem("preguntaActual_" + idCuestionario, index);
    }

    function cargarProgreso(idCuestionario, idValor = 0) {
    let indexGuardado = localStorage.getItem("preguntaActual_" + idCuestionario);
    indexGuardado = indexGuardado ? parseInt(indexGuardado) : idValor;
    const preguntas = document.querySelectorAll('.pregunta-container');

    // Remover la clase 'active' de todas las preguntas
    preguntas.forEach(pregunta => pregunta.classList.remove('active'));

    // Asegurar que el índice guardado no exceda el número de preguntas
    if (preguntas.length > 0) {
    if (indexGuardado >= preguntas.length) {
    indexGuardado = preguntas.length - 1;
    }
    preguntas[indexGuardado].classList.add('active');
    }
    return indexGuardado;
    }

    // ---------- NAVEGACIÓN ENTRE PREGUNTAS ----------
    function siguientePregunta(idTema) {
    const preguntas = document.querySelectorAll('.pregunta-container');
    let activeIndex = -1;

    // Mostrar y ocultar el loader (suponiendo que usas jQuery para ello)
    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);

    preguntas.forEach((pregunta, index) => {
    if (pregunta.classList.contains('active')) {
    activeIndex = index;
    pregunta.classList.remove('active');
    }
    });

    if (activeIndex === -1 && preguntas.length > 0) {
    activeIndex = cargarProgreso(idTema);
    }

    // Si se está en la última pregunta del módulo...
    if (activeIndex >= preguntas.length - 1) {
    // Buscar el módulo actual en el arreglo de temas
    let seccionActual = localStorage.getItem("seccionActual") || temas[0].nombre;
    let currentIndex = temas.findIndex(t => t.nombre === seccionActual);

    // Si existe un siguiente módulo, preparar el botón para dirigir a esa sección
    if (currentIndex < temas.length - 1) {
    let nuevoTema = temas[currentIndex + 1];
    // Se coloca el botón en el contenedor de botones de la última pregunta
    let lastPregunta = preguntas[preguntas.length - 1];
    let botonesContainer = lastPregunta.querySelector('.mt-3.d-flex.justify-content-between');
    botonesContainer.innerHTML = `<button class="btn btn-success" onclick="irASiguienteSeccion('${nuevoTema.nombre}', ${nuevoTema.id})">${nuevoTema.nombre} <i data-feather="message-circle"></i></button>`;
    return;
    } 

    } else {
    // Avanzar a la siguiente pregunta dentro del mismo módulo
    const nuevoIndice = activeIndex + 1;
    preguntas[nuevoIndice].classList.add('active');
    guardarProgreso(nuevoIndice, idTema);
    }
    }

    //---------- MUESTRA LA PREGUNTA ANTERIOR ----------
    function anteriorPregunta(idTema) {
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
 
    if (activeIndex === -1 && preguntas.length > 0) {
    activeIndex = cargarProgreso(idTema);
    }

    // Si se está en la primera pregunta del módulo...
    if (activeIndex <= 0) {
    let seccionActual = localStorage.getItem("seccionActual") || temas[0].nombre;
    let currentIndex = temas.findIndex(t => t.nombre === seccionActual);
    if (currentIndex > 0) {
    let nuevoTema = temas[currentIndex - 1];
    localStorage.setItem("seccionActual", nuevoTema.nombre);
    // Se asume que al cargar el módulo anterior se muestra la última pregunta
    contenidoPreguntas(nuevoTema.id, true);
    return;
    }
    } else {
    // Retroceder a la pregunta anterior dentro del mismo módulo
    const nuevoIndice = activeIndex - 1;
    preguntas[nuevoIndice].classList.add('active');
    guardarProgreso(nuevoIndice, idTema);
    }
    }

    //---------- IR A LA SIGUIENTE SECCION ----------
    function irASiguienteSeccion(nombreTema, idTema) {
    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);
    localStorage.setItem("seccionActual", nombreTema);
    contenidoPreguntas(idTema);
    }

    //---------- MUESTRA LA SECCION DE PREGUNTAS Y COMENTARIOS ----------
    function seccionPreguntas() {
    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);
    document.getElementById("preguntas-container").style.display = "block";
    document.getElementById("comentarios-container").style.display = "none";
    localStorage.setItem("seccionActual", "Grupo Sanguineo");
    contenidoPreguntas(7);
    }

    function finalizarPreguntas() {
    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);
    document.getElementById("preguntas-container").style.display = "none";
    document.getElementById("comentarios-container").style.display = "block";
    localStorage.setItem("seccionActual", "comentarios");
    contenidoComentario();
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
   
    //---------- AGREGAR COMENTARIO DEL MODULO ----------
    function agregarComentario(idModulo, idPaciente, idRol) {
    let comentario = document.getElementById('comentarioModulos').value;
    //if (!comentario) return document.getElementById('comentarioModulos').style.border = '2px solid #A52525';
    gestionarAntecedentesNoPatologicos(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/agregar-comentario-modulo`, { idModulo, idPaciente, comentarioModulos: comentario, idRol }, () => FinalizarModuloPaciente(idModulo, idPaciente));
    }

    //---------- FINALIZAR MODULO DEL PACIENTE----------
    function FinalizarModuloPaciente(idModulo, idPaciente) {
    gestionarAntecedentesNoPatologicos('/historia-clinica/paciente/finalizar-modulo-paciente', { idModulo, idPaciente }, () => window.location.href = '/historia-clinica');
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

    <div id="preguntas-container">
    <div class="card-header pb-0">
    <div class="row">

    <div id="seccion100" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-2">
    <img src="<?=RUTA_IMAGES ?>/iconos/audio.png" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion100">

    <h8 class="text-primary fw-bold texto">
    <b>A continuacion, responda las siguientes preguntas indicando si presenta alguna de estas conductas o hábitos:</b>
    </h8>
    </div>

    <div class="col-12">
    <div id="mensaje" class="text-center text-danger mt-2"></div>
    </div>

    </div>
    </div>

    <div class="card-body">
    <div id="contePreguntas_1"></div>
    <div id="contePreguntas_2"></div>
    <div id="contePreguntas_3"></div>
    <div id="contePreguntas_4"></div>
    <div id="contePreguntas_5"></div>
    <div id="contePreguntas_6"></div>
    <div id="contePreguntas_7"></div>

    </div>
    </div>

    <!---------- CONTENEDOR DE COMENTARIO ---------->
    <div id="comentarios-container">

    <div class="card-header pb-0">
    <div class="row">

    <div id="seccion101" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-3">
    <img src="<?=RUTA_IMAGES ?>/iconos/audio.png" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion101">
      
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


    <script src="<?=RUTA_JS;?>voice-utilities.js"></script>
    <script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>    
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS;?>main.js"></script>
    
    </body>
    </html>
