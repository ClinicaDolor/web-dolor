<?php 
use App\Config\Database;
use App\Models\ProcedimientosDolorModel;
$bd = Database::getInstance();
 
$model = new ProcedimientosDolorModel();
$preguntas_fijas = $model->obtenerPreguntasModulos(); 
foreach ($preguntas_fijas as $preg) {
echo $model->procedimientosModulo8($data['idPaciente'], $preg);
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
    contenidoTratamientos()
    });

    // ---------- CONTENIDO DE LAS PREGUNTAS ----------
    function contenidoPreguntas() {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    const stateKey = 'datatableProcedimientoState';
    const savedState = JSON.parse(localStorage.getItem(stateKey)) || {};

    fetch(`/buscar/contenido-preguntas-modulo-8/${idPaciente}/${idRol}`)
        .then(response => response.text())
        .then(data => {
            const contenedor = document.getElementById('contePreguntas');
            contenedor.innerHTML = data;
            feather.replace();

            const tabla = document.querySelector("#table_procedimiento");
            if (tabla) {
                const dataTable = new simpleDatatables.DataTable(tabla, {
                    searchable: true,
                    fixedHeight: true,
                    perPage: savedState.perPage || 10,
                    perPageSelect: [10, 20, 50],
                    columns: [
                        { select: 0, sort: savedState.sort || "asc" },
                        { select: [1, 2, 3], sortable: false }
                    ]
                });

                dataTable.on('datatable.init', function () {
                    if (savedState.page) {
                        dataTable.page(savedState.page);
                    }
                    if (savedState.search) {
                        dataTable.input.value = savedState.search;
                        dataTable.search(savedState.search);
                    }
                });

                dataTable.on('datatable.page', page => {
                    savedState.page = page;
                    localStorage.setItem(stateKey, JSON.stringify(savedState));
                });

                dataTable.on('datatable.perpage', perPage => {
                    savedState.perPage = perPage;
                    localStorage.setItem(stateKey, JSON.stringify(savedState));
                });

                dataTable.on('datatable.sort', (column, direction) => {
                    savedState.sort = direction;
                    localStorage.setItem(stateKey, JSON.stringify(savedState));
                });

                dataTable.on('datatable.search', query => {
                    savedState.search = query;
                    localStorage.setItem(stateKey, JSON.stringify(savedState));
                });
            }
        });
}


function contenidoTratamientos() {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    const stateKey = 'datatableTratamientosState';
    const savedState = JSON.parse(localStorage.getItem(stateKey)) || {};

    fetch(`/buscar/contenido-preguntas-tratamiento-modulo-8/${idPaciente}/${idRol}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('conteTratamiento').innerHTML = data;
            feather.replace();

            const tabla = document.querySelector("#table_tratamientos");
            if (tabla) {
                const dataTable = new simpleDatatables.DataTable(tabla, {
                    searchable: true,
                    fixedHeight: true,
                    perPage: savedState.perPage || 10,
                    perPageSelect: [10, 20, 50],
                    columns: [
                        { select: 0, sort: savedState.sort || "asc" },
                        { select: [1, 2], sortable: false }
                    ]
                });

                dataTable.on('datatable.init', function () {
                    if (savedState.page) {
                        dataTable.page(savedState.page);
                    }
                    if (savedState.search) {
                        dataTable.input.value = savedState.search;
                        dataTable.search(savedState.search);
                    }
                });

                dataTable.on('datatable.page', page => {
                    savedState.page = page;
                    localStorage.setItem(stateKey, JSON.stringify(savedState));
                });

                dataTable.on('datatable.perpage', perPage => {
                    savedState.perPage = perPage;
                    localStorage.setItem(stateKey, JSON.stringify(savedState));
                });

                dataTable.on('datatable.sort', (column, direction) => {
                    savedState.sort = direction;
                    localStorage.setItem(stateKey, JSON.stringify(savedState));
                });

                dataTable.on('datatable.search', query => {
                    savedState.search = query;
                    localStorage.setItem(stateKey, JSON.stringify(savedState));
                });
            }
        });
    }
    //---------- CONTROL SERVER ----------
    function gestionarControlDolor(url, parametros, callback, idUpdate = 0) {
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

    //---------- AGREGAR PROCEDIMIENTO DEL PACIENTE ----------
    function agregarProcedimientosDolor(idPaciente, idRol) {
    gestionarControlDolor(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/agregar-procedimiento-dolor-modulo8`, {
    idPaciente,
    idRol
    }, 
    () => contenidoPreguntas(),
    1);
    } 

    //---------- EDITAR PROCEDIMIENTO DEL PACIENTE ----------
    function editarProcedimientosDolor(idProcedimiento, elemento, parametro, idRol) {
    gestionarControlDolor(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-procedimiento-dolor-modulo8`, {
    idProcedimiento,
    detalle: elemento.value,
    edicion: parametro,
    idRol
    }, 
    () => contenidoPreguntas());
    }

    //---------- ELIMINAR PROCEDIMIENTO DEL PACIENTE ----------
    function eliminarProcedimientosDolor(idProcedimiento, idRol) {
    gestionarControlDolor(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/eliminar-procedimiento-dolor-modulo8`, {
    idProcedimiento,
    idRol
    }, 
    () => contenidoPreguntas(),
    1);
    }

    //---------- EDITAR TRATAMIENTO DEL PACIENTE ----------
    function editarTratamientosDolor(idTratamiento, elemento, parametro, idRol, idUpdate = 0) {
    gestionarControlDolor(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-tratamiento-dolor-modulo8`, {
    idTratamiento,
    detalle: elemento.value,
    edicion: parametro,
    idRol
    }, 
    () => contenidoTratamientos(),
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

    <!---------- SECCION DE PROCEDIMIENTOS ---------->
    <div id="preguntas-container">
    <div class="card">
    <div id="contePreguntas"></div>
    </div>
    </div>

    <!---------- SECCION DE TRATAMIENTOS ---------->
    <div id="tratamientos-container">
    <div class="card">
    <div id="conteTratamiento"></div>
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
