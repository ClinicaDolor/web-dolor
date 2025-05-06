<?php 
use App\Config\Database;
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

    <script>
    document.addEventListener("DOMContentLoaded", function() {
    contenidoPreguntas();
    });
 
    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas() {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    fetch(`/buscar/contenido-preguntas-modulo-4/${idPaciente}/${idRol}`)
    .then(response => response.text())
    .then(data => {

    const contenedor = document.getElementById('contePreguntas');
    contenedor.innerHTML = data;
    feather.replace();

    const tabla = document.querySelector("#table_cirugia");
    if (tabla) {
    dataTable = new simpleDatatables.DataTable(tabla,{
	searchable: true,
    fixedHeight: true,
	columns: [
	{
	select: 1, sort: "desc"
	},
    { select: [0,1,2,3], sortable: false },

	]
    });
    }   

    });
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
    gestionarAntecedentesQuirurgicos(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/eliminar-cirugia-antecedentes`, { idQuirurgico, idRol }, () => contenidoPreguntas(1),1);
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

    <script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>    
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS;?>main.js"></script>

    </body>
    </html>
