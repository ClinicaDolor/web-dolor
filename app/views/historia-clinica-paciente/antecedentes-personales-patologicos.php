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
    
$modulos = $model->obtenerModulosM5();
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
    #enfermedades-container { display: none; }
    .enfermedad-container { display: none; }
    .enfermedad-container.active { display: block; }
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
    const seccionActual = localStorage.getItem("seccionActual") || "Oxigenacion";
    localStorage.setItem("seccionActual", seccionActual);
    let temaActual = temas.find(t => t.nombre === seccionActual);


    if (!seccionActual || seccionActual === "enfermedades") {
    seccionEnfermedades();
    } else {
    contenidoPreguntas(temaActual.id);
    }
    
    // Inicializar las preguntas con valor 0 si no están configuradas en localStorage
    for (let i = 1; i <= 7; i++) {
    if (localStorage.getItem(`preguntaActual_${i}`) === null) {
    localStorage.setItem(`preguntaActual_${i}`, 0);
    }
    }

    if (localStorage.getItem(`preguntaActualV2`) === null) {
    localStorage.setItem(`preguntaActualV2`, 0);
    }

    });


    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas(idCuestionario, idValor = 0) {
    // Limpia las preguntas anteriores, si existen
    document.querySelectorAll('.pregunta-container').forEach(p => p.remove());

    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    fetch(`/buscar/contenido-preguntasV1-modulo-5/${idPaciente}/${idRol}/${idCuestionario}`)
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
    document.getElementById("enfermedades-container").style.display = "none";
    localStorage.setItem("seccionActual", "Alergias (Medicamentos)");
    contenidoPreguntas(4);
    }


    // ------------------------------ CONTENIDO PREGUNTAS V2 ------------------------------
    function contenidoEnfermedades(idValor = 0) {
    // Limpia las preguntas anteriores, si existen
    document.querySelectorAll('.enfermedad-container').forEach(p => p.remove());
    
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    fetch(`/buscar/contenido-preguntasV2-modulo-5/${idPaciente}/${idRol}`)
    .then(response => response.text())
    .then(data => {
    document.getElementById('contePreguntasV2').innerHTML = data;
    feather.replace();
            
    // Reactivar el IntersectionObserver para las nuevas secciones (si lo utilizas)
    document.querySelectorAll('.sectionQuestion').forEach(section => {
    observer.observe(section);
    });

    cargarProgresoV2(idValor);
    });
    }

    // ---------- VER PREGUNTAS V2 ----------
    function seccionEnfermedades() {
    $(".LoaderPage").show();
    $(".LoaderPage").fadeOut(1000);
    document.getElementById("preguntas-container").style.display = "none";
    document.getElementById("enfermedades-container").style.display = "block";
    localStorage.setItem("seccionActual", "enfermedades");
    contenidoEnfermedades();
    }

 
    function guardarProgresoV2(index) {
    localStorage.setItem("preguntaActualV2", index);
    }

    function cargarProgresoV2(idValor) {
    let indexGuardado = localStorage.getItem("preguntaActualV2");
    indexGuardado = indexGuardado ? parseInt(indexGuardado) : 0;

    const preguntas = document.querySelectorAll('.enfermedad-container');
    
    // Calcular el nuevo índice dentro de los límites permitidos
    let nuevoIndex = indexGuardado + idValor;
    if (nuevoIndex < 0) nuevoIndex = 0; // Evita ir antes de la primera pregunta
    if (nuevoIndex > preguntas.length) nuevoIndex = preguntas.length - 1; // Evita ir después de la última

    // Remover la clase 'active' de todas las preguntas
    preguntas.forEach(pregunta => pregunta.classList.remove('active'));

    // Activar la nueva pregunta
    preguntas[nuevoIndex].classList.add('active');

    // Guardar el nuevo progreso
    guardarProgresoV2(nuevoIndex);
    }

    function siguientePreguntaV2() {
    const preguntas = document.querySelectorAll('.enfermedad-container');
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
    guardarProgresoV2(nuevoIndice); // Guarda el progreso al avanzar
    }

    }

    function anteriorPreguntaV2() {
    const preguntas = document.querySelectorAll('.enfermedad-container');
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
    guardarProgresoV2(nuevoIndice); // Guarda el progreso al retroceder
    }

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
    }, () => contenidoEnfermedades(0),
    idUpdate);
    }

    //---------- FINALIZAR MODULO DEL PACIENTE----------
    function finalizarModuloPAC(idModulo, idPaciente) {
    gestionarAntecedentesPatologicos('/historia-clinica/paciente/finalizar-modulo-paciente', { idModulo, idPaciente }, () => window.location.href = '/historia-clinica');
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
    </div>
    </div>


    <!---------- CONTENEDOR DE ENFERMEDADES ---------->
    <div id="enfermedades-container">

    <div class="card-header pb-0">
    <div class="row">

    <div id="seccion101" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-2">
    <img src="<?=RUTA_IMAGES ?>/iconos/audio.png" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion101">

    <h8 class="text-primary fw-bold texto">
    <b>A continuación se presentará una serie de distintas enfermedades.
    Si usted padece o ha padecido alguna de ellas, por favor indique el año aproximado en que fue diagnosticado:</b>
    </h8>
    </div>

    <div class="col-12">
    <div id="mensaje" class="text-center text-danger mt-2"></div>
    </div>

    </div>
    </div>

    
    <div class="card-body">
    <div id="contePreguntasV2"></div>
    </div>
    </div>

    </div>



    </div>
    </section>
    <!----- FOOTER ---------->
    <?php include_once __DIR__ . '/../components/footer-mvsd.php';?>
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
