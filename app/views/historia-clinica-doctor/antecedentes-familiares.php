<?php 
use App\Config\Database;
use App\Models\AntecedenteFamiliarModel;
$bd = Database::getInstance();

$model = new AntecedenteFamiliarModel();
$enfermedades_fijas = $model->enfermedadesFijas(); 
foreach ($enfermedades_fijas as $enf) {
echo $model->antecedentesFamiliares($data['idPaciente'], $enf);
}
 
$escape = static function ($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};
$data['datos'] = is_array($data['datos'] ?? null) ? $data['datos'] : [];
$patientId = (int) ($data['idPaciente'] ?? 0);
$patientName = $data['nombre'] ?? '';
$title = $data['title'] ?? 'Antecedentes familiares';
$patientUrl = htmlspecialchars(SERVIDOR . 'clinica/paciente/' . $patientId, ENT_QUOTES, 'UTF-8');
?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$escape($title);?></title>
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

    <body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-module-page doctor-antecedentes-familiares-page">
    <div class="LoaderPage"></div>
    <div id="app">
    <?=$data['sidebar'];?>

    <div id="main" data-rol="<?=$escape($data['idRol'] ?? '');?>" data-paciente="<?=$patientId;?>">
    <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

    <main class="main-content container-fluid doctor-family-content">
    <header class="doctor-page-hero doctor-family-hero" aria-labelledby="doctor-family-title">
    <div class="doctor-family-hero-copy">
    <p class="dashboard-eyebrow">HISTORIA CLÍNICA</p>
    <h1 id="doctor-family-title">Antecedentes familiares</h1>
    <p>Consulta y registra los antecedentes familiares del paciente.</p>
    <p class="doctor-family-patient-context">Paciente autorizado: <strong><?=$escape($patientName);?></strong></p>
    </div>
    <div class="doctor-family-hero-actions">
    <a class="doctor-family-action" href="<?=$patientUrl;?>">
    <i data-feather="arrow-left" aria-hidden="true"></i>
    <span>Volver al expediente</span>
    </a>
    </div>
    </header>

    <section class="doctor-family-sections" aria-label="Antecedentes familiares">
    <article class="card doctor-family-card doctor-family-questions-card">
    <div id="contePreguntas"></div>
    </article>

    <article class="card doctor-family-card doctor-family-comments-card" aria-labelledby="doctor-family-comments-title">
    <header class="card-header doctor-family-card-header">
    <div id="seccion2" data-autoplay="false" class="doctor-family-comment-heading sectionQuestion">
    <p class="dashboard-eyebrow">SEGUIMIENTO CLÍNICO</p>
    <h2 id="doctor-family-comments-title">Comentarios adicionales</h2>
    <p>Si tiene alguna otra información o comentarios que desee compartir, por favor, indíquelo.</p>
    </div>
    </header>
    <div class="card-body doctor-family-card-body">
    <div id="conteComentario"></div>
    </div>
    </article>
    </section>
    </main>

    <?php include_once __DIR__ . '/../components/footer-doctor.php';?>
    </div>
    </div>

    <script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS;?>main.js"></script>
    <script src="<?=RUTA_JS;?>search-main.js"></script>

    </body>
    </html>
