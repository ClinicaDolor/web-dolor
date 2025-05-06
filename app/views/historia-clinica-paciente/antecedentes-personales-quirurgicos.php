<?php 
use App\Config\Database;
use App\Models\PacienteModulosModelo;
$bd = Database::getInstance();

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
    .pregunta-container { display: none; }
    .pregunta-container.active { display: block; }
    </style>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
    contenidoPreguntas();

    }); 
  
    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas(idValor = 0) {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    fetch(`/buscar/contenido-preguntas-modulo-4/${idPaciente}/${idRol}`)
    .then(response => response.text())
    .then(data => {

    const contenedor = document.getElementById('contePreguntas');
    contenedor.innerHTML = data;
    feather.replace();

    // Reactivar el IntersectionObserver para las nuevas secciones
    document.querySelectorAll('.sectionQuestion').forEach(section => {
    observer.observe(section);
    });

    cargarProgreso(idValor);
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
    if (nuevoIndex >= preguntas.length) nuevoIndex = preguntas.length - 1; // Evita ir después de la última pregunta

    // Remover la clase 'active' de todas las preguntas
    preguntas.forEach(pregunta => pregunta.classList.remove('active'));

    // Asegúrate de que el índice esté dentro del rango de preguntas
    if (preguntas[nuevoIndex]) {
    // Activar la nueva pregunta
    preguntas[nuevoIndex].classList.add('active');
    // Mostrar el botón si es la última pregunta
    mostrarBotonAgregar(preguntas[nuevoIndex], nuevoIndex, preguntas.length);
    }

    // Guardar el nuevo progreso
    guardarProgreso(nuevoIndex);
    }

    //---------- PREGUNTA ANTERIOR ----------
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
    mostrarBotonAgregar(preguntas[nuevoIndice], nuevoIndice, preguntas.length);
    guardarProgreso(nuevoIndice); // Guarda el progreso al retroceder
    }

    }

    //---------- PREGUNTA SIGUIENTE ----------
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
    mostrarBotonAgregar(preguntas[nuevoIndice], nuevoIndice, preguntas.length);
    guardarProgreso(nuevoIndice); // Guarda el progreso al avanzar
    }

    }

    // Función nueva para controlar la visibilidad del botón
    function mostrarBotonAgregar(pregunta, index, total) {
    const btnAgregar = pregunta.querySelector('.btnAgregarCirugia');
    if (btnAgregar) {
    if (index === total - 1) {
    btnAgregar.style.display = 'block';
    } else {
    btnAgregar.style.display = 'none';
    }
    }
    }

    //---------- CONTROL SERVER ----------
    function gestionarAntecedentesQuirurgicos(url, parametros, callback, idUpdate = 0) {
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
    function agregarCirugiaPaciente (idPaciente, idRol) {
    gestionarAntecedentesQuirurgicos(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/agregar-cirugia-antecedentes`, {
    idPaciente,
    idRol
    }, 
    () => contenidoPreguntas(1),
    1);
    }

    //---------- EDITAR CIRUGIA DEL PACIENTE ----------
    function editarCirugia(idCirugia, elemento, parametro, idRol) {
    gestionarAntecedentesQuirurgicos(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-cirugia-antecedentes`, {
    idCirugia, 
    detalle: elemento.value, 
    edicion: parametro, 
    idRol
    }, () => contenidoPreguntas(0));
    }
    //---------- ELIMINAR CIRUGIA DEL PACIENTE ----------
    function eliminarCirugiaPaciente(idQuirurgico, idRol) {
    gestionarAntecedentesQuirurgicos(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/eliminar-cirugia-antecedentes`, 
    { idQuirurgico, idRol }, () => contenidoPreguntas(1),
    1);
    }

    //---------- FINALIZAR MODULO DEL PACIENTE----------
    function finalizarModuloPAC(idModulo, idPaciente) {
    gestionarAntecedentesQuirurgicos('/historia-clinica/paciente/finalizar-modulo-paciente', { idModulo, idPaciente }, 
    () => window.location.href = '/historia-clinica');
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
    <div id="contePreguntas"></div>
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
