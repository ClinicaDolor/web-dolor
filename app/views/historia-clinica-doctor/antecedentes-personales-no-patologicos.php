<?php 
use App\Config\Database;
use App\Models\AntecedentesNoPatologicosModel;
$bd = Database::getInstance();

$model = new AntecedentesNoPatologicosModel();
$preguntas_fijas = $model->obtenerPreguntasModulos(); 
foreach ($preguntas_fijas as $preg) {
echo $model->antecedentesNoPatologicos($data['idPaciente'], $preg);
}

$escape = static function ($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};
$data['datos'] = is_array($data['datos'] ?? null) ? $data['datos'] : [];
$patientId = (int) ($data['idPaciente'] ?? 0);
$patientName = $data['nombre'] ?? '';
$title = $data['title'] ?? 'Antecedentes Personales No Patológicos';
$patientUrl = htmlspecialchars(SERVIDOR . 'clinica/paciente/' . $patientId, ENT_QUOTES, 'UTF-8');
?> 

    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <title><?=$escape($title);?></title>
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

    <body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-module-page doctor-antecedentes-no-patologicos-page">
    <div class="LoaderPage"></div>
    <div id="app">
    <?=$data['sidebar'];?>
    <div id="main" data-rol="<?=$escape($data['idRol'] ?? '');?>" data-paciente="<?=$patientId;?>">
    <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

    <main class="main-content container-fluid doctor-nonpathological-content">
    <header class="doctor-page-hero doctor-nonpathological-hero" aria-labelledby="doctor-nonpathological-title">
    <div class="doctor-nonpathological-hero-copy">
    <p class="dashboard-eyebrow">HISTORIA CLÍNICA</p>
    <h1 id="doctor-nonpathological-title">Antecedentes Personales No Patológicos</h1>
    <p>Consulta y revisa las conductas y hábitos registrados del paciente.</p>
    <p class="doctor-nonpathological-patient-context">Paciente autorizado: <strong><?=$escape($patientName);?></strong></p>
    </div>
    <div class="doctor-nonpathological-hero-actions">
    <a class="doctor-nonpathological-action" href="<?=$patientUrl;?>">
    <i data-feather="arrow-left" aria-hidden="true"></i>
    <span>Volver al expediente</span>
    </a>
    </div>
    </header>

    <section class="doctor-nonpathological-sections" aria-label="Antecedentes Personales No Patológicos">
    <article class="card doctor-nonpathological-card doctor-nonpathological-questions-card">
    <header class="card-header doctor-nonpathological-card-header">
    <p class="dashboard-eyebrow">CUESTIONARIO CLÍNICO</p>
    <h2>Conductas y hábitos</h2>
    <p>A continuación, responda las preguntas indicando si presenta alguna de estas conductas o hábitos.</p>
    </header>
    <div class="card-body doctor-nonpathological-card-body">
    <div class="doctor-nonpathological-question-list">
    <div class="doctor-nonpathological-question-slot" id="contePreguntas_1"></div>
    <div class="doctor-nonpathological-question-slot" id="contePreguntas_2"></div>
    <div class="doctor-nonpathological-question-slot" id="contePreguntas_3"></div>
    <div class="doctor-nonpathological-question-slot" id="contePreguntas_4"></div>
    <div class="doctor-nonpathological-question-slot" id="contePreguntas_5"></div>
    <div class="doctor-nonpathological-question-slot" id="contePreguntas_6"></div>
    <div class="doctor-nonpathological-question-slot" id="contePreguntas_7"></div>
    </div>
    </div>
    </article>

    <article class="card doctor-nonpathological-card doctor-nonpathological-comments-card" aria-labelledby="doctor-nonpathological-comments-title">
    <header class="card-header doctor-nonpathological-card-header">
    <div id="seccion2" data-autoplay="false" class="doctor-nonpathological-comment-heading sectionQuestion">
    <p class="dashboard-eyebrow">SEGUIMIENTO CLÍNICO</p>
    <h2 id="doctor-nonpathological-comments-title">Comentarios adicionales</h2>
    <p>Si tiene alguna otra información o comentario que desee compartir, por favor, indíquelo.</p>
    </div>
    </header>
    <div class="card-body doctor-nonpathological-card-body">
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
    <script src="<?=RUTA_JS;?>main.js"></script>
    <script src="<?=RUTA_JS;?>search-main.js"></script>

    </body>
    </html>
