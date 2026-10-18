<?php
use App\Services\ClinicalSummarySecurity;

$escape = static function ($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};
$patientId = (int) ($data['idPaciente'] ?? 0);
$patientName = trim((string) ($data['nombre'] ?? ''));
$patientSex = $data['sexo'] ?? '';
$p11CsrfToken = ClinicalSummarySecurity::captureCsrfToken($patientId);
$title = $data['title'] ?? 'Evaluación del dolor';
$patientUrl = $escape(SERVIDOR . 'clinica/paciente/' . $patientId);

?>
 
    <!DOCTYPE html>
    <html lang="es-MX">
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
    const seccionActual = localStorage.getItem("seccionActual");
    contenidoFrente();
    contenidoEspalda();
    contenidoCuestionario();
    });

    function contenidoFrente() {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');
    const idSexo = usuarioDiv.getAttribute('data-sexo');

    fetch(`/buscar/contenido-evaluacion-frente-modulo-9/${idPaciente}/${idRol}`)
    .then(response => response.text())
    .then(data => {
    const contenedor = document.getElementById('conteFrente');
    contenedor.innerHTML = data;
    feather.replace();

    // Ejecutar lógica de canvas solo cuando ya está en el DOM
    const canvas = document.getElementById('painFrente');
    const ctx = canvas.getContext('2d');
    const colorPicker = document.getElementById('colorFrente');

    const img = new Image();
    img.src = '<?=SERVIDOR;?>clinica/paciente/imagen-evaluacion-dolor/frente/<?=$patientId;?>?' + Math.random();
    img.onload = () => {
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    };

    let painting = false;

    function startPosition(e) {
    painting = true;
    draw(e);
    }

    function endPosition() {
    if (painting) {
    painting = false;
    ctx.beginPath();
    cambioFrenteImg(idPaciente, idRol, idSexo);
    }
    }

    function draw(e) {
    if (!painting) return;

    ctx.lineWidth = 25;
    ctx.lineCap = 'round';
    ctx.strokeStyle = colorPicker.value;

    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;

    const x = (e.clientX - rect.left) * scaleX;
    const y = (e.clientY - rect.top) * scaleY;

    ctx.lineTo(x, y);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(x, y);
    }

    function cambioFrenteImg(idPaciente, idRol, idSexo) {
    const imgFrente = canvas.toDataURL();
    gestionarEvaluacionDolor(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-img-frente-modulo9`, {
    idPaciente,
    idSexo, 
    idRol,
    imgFrente
    },  () => cargaImagenFrente(0));
    }

    canvas.addEventListener('mousedown', startPosition);
    canvas.addEventListener('mouseup', endPosition);
    canvas.addEventListener('mousemove', draw);
    });
    }

    function cleanFrenteImg() {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');
    const idSexo = usuarioDiv.getAttribute('data-sexo');

    gestionarEvaluacionDolor(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/eliminar-img-frente-modulo9`, {
    idPaciente,
    idSexo, 
    idRol
    },  () => cargaImagenFrente(1),
    1);
    }

    function cargaImagenFrente(tipo = 0) {
    const canvas = document.getElementById('painFrente');
    const ctx = canvas.getContext('2d');
    const usuarioDiv = document.getElementById('main');
    const idSexo = usuarioDiv.getAttribute('data-sexo');
    const img = new Image();

    // Guardar el estado actual del canvas
    const canvasState = ctx.getImageData(0, 0, canvas.width, canvas.height);

    if(tipo == 1){
    img.src = '<?=SERVIDOR;?>clinica/paciente/imagen-evaluacion-dolor/frente/<?=$patientId;?>?' + Math.random();
    img.onload = () => {
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    };
    
    }else{

    imagen = '<?=SERVIDOR;?>clinica/paciente/imagen-evaluacion-dolor/frente/<?=$patientId;?>';
    img.src = imagen + '?' + Math.random(); // Usar "?" antes del random para no alterar la ruta original
    img.onload = () => {
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    // Restaurar el estado del canvas (las ediciones)
    ctx.putImageData(canvasState, 0, 0);
    };
    }
    }

    //-------------------- SECCION DE LA ESPALDA --------------------
    function contenidoEspalda() {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');
    const idSexo = usuarioDiv.getAttribute('data-sexo');

    fetch(`/buscar/contenido-evaluacion-espalda-modulo-9/${idPaciente}/${idRol}`)
    .then(response => response.text())
    .then(data => {
    const contenedor = document.getElementById('conteEspalda');
    contenedor.innerHTML = data;
    feather.replace();

    // Ejecutar lógica de canvas solo cuando ya está en el DOM
    const canvasEspalda  = document.getElementById('painEspalda');
    const ctxEspalda  = canvasEspalda .getContext('2d');
    const colorPicker = document.getElementById('colorEspalda');

    const img = new Image();
    img.src = '<?=SERVIDOR;?>clinica/paciente/imagen-evaluacion-dolor/espalda/<?=$patientId;?>?' + Math.random();
    img.onload = () => {
    ctxEspalda.drawImage(img, 0, 0, canvasEspalda .width, canvasEspalda.height);
    };

    let painting2 = false;

    function startPosition2(e) {
    painting2 = true;
    draw2(e);
    }

    function endPosition2() {
    if (painting2) {
    painting2 = false;
    ctxEspalda.beginPath();
    cambioEspaldaImg(idPaciente, idRol, idSexo);
    }
    }
 
    function draw2(e) {
    if (!painting2) return;

    ctxEspalda.lineWidth = 25 ;
    ctxEspalda.lineCap = 'round';
    ctxEspalda.strokeStyle = colorPicker.value; 

    const rect = canvasEspalda.getBoundingClientRect();
    const scaleX = canvasEspalda.width / rect.width;
    const scaleY = canvasEspalda.height / rect.height;

    const x = (e.clientX - rect.left) * scaleX;
    const y = (e.clientY - rect.top) * scaleY;

    ctxEspalda.lineTo(x, y);
    ctxEspalda.stroke();
    ctxEspalda.beginPath();
    ctxEspalda.moveTo(x, y);
    }

    function cambioEspaldaImg(idPaciente, idRol, idSexo) {
    const imgEspalda = canvasEspalda.toDataURL();
    gestionarEvaluacionDolor(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-img-espalda-modulo9`, {
    idPaciente,
    idSexo, 
    idRol,
    imgEspalda
    },  () => cargaImagenEspalda(0));
    }

    canvasEspalda.addEventListener('mousedown', startPosition2);
    canvasEspalda.addEventListener('mouseup', endPosition2);
    canvasEspalda.addEventListener('mousemove', draw2);
    });
    
    }

    function cleanEspaldaImg() {
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');
    const idSexo = usuarioDiv.getAttribute('data-sexo');

    gestionarEvaluacionDolor(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/eliminar-img-espalda-modulo9`, {
    idPaciente,
    idSexo, 
    idRol
    },  () => cargaImagenEspalda(1),
    1);
    }

    function cargaImagenEspalda(tipo = 0) {
    const canvasEspalda = document.getElementById('painEspalda');
    const ctxEspalda  = canvasEspalda.getContext('2d');
    const usuarioDiv = document.getElementById('main');
    const idSexo = usuarioDiv.getAttribute('data-sexo');
    const img = new Image();

    // Guardar el estado actual del canvas
    const canvasState2 = ctxEspalda.getImageData(0, 0, canvasEspalda.width, canvasEspalda.height);

    if(tipo == 1){
    img.src = '<?=SERVIDOR;?>clinica/paciente/imagen-evaluacion-dolor/espalda/<?=$patientId;?>?' + Math.random();
    img.onload = () => {
    ctxEspalda.drawImage(img, 0, 0, canvasEspalda.width, canvasEspalda.height);
    };
    
    }else{

    imagen = '<?=SERVIDOR;?>clinica/paciente/imagen-evaluacion-dolor/espalda/<?=$patientId;?>';
    img.src = imagen + '?' + Math.random(); // Usar "?" antes del random para no alterar la ruta original
    img.onload = () => {
    ctxEspalda.drawImage(img, 0, 0, canvasEspalda.width, canvasEspalda.height);
    // Restaurar el estado del canvas (las ediciones)
    ctxEspalda.putImageData(canvasState2, 0, 0);
    };
    }
    }

    //-------------------- SECCION DE LA ESPALDA --------------------
    function contenidoCuestionario(){
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const idRol = usuarioDiv.getAttribute('data-rol');

    fetch(`/buscar/contenido-preguntas-modulo-9/${idPaciente}/${idRol}`)
    .then(response => response.text())
    .then(data => {

    const contenedor = document.getElementById('contePreguntas');
    contenedor.innerHTML = data;
    feather.replace();

    // Reactivar el IntersectionObserver para las nuevas secciones
    document.querySelectorAll('.sectionQuestion').forEach(section => {
    observer.observe(section);
    });

    });

    }

    //---------- CONTROL SERVER ----------
    function gestionarEvaluacionDolor(url, parametros, callback, idUpdate = 0) {
    if(idUpdate == 1){
    $(".LoaderPage").show();
    }
    fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-M03-CSRF': document.getElementById('main')?.dataset.p11Csrf || '' },
    body: JSON.stringify(parametros)
    }).then(res => res.json()).then(data => {
 
    if(idUpdate == 1){
    $(".LoaderPage").fadeOut(1000);
    }
    if (data.resultado) callback();
    else document.getElementById('mensaje').textContent = 'Error: ' + data.mensaje;
    });
    }

    //---------- EDITAR PROCEDIMIENTO DEL PACIENTE ----------
    function editarEvaluacionDolor(idEvaluacion, elemento, parametro, idRol, idUpdate = 0) {
    gestionarEvaluacionDolor(`/${idRol === "Paciente" ? "historia-clinica" : "clinica"}/paciente/editar-evaluacion-dolor-modulo9`, {
    idEvaluacion,
    detalle: elemento.value,
    edicion: parametro,
    idRol
    },  () => contenidoCuestionario(),
    idUpdate);
    } 
    </script>
    </head>

    <body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-module-page doctor-pain-evaluation-page">
    <div class="LoaderPage"></div>
    <div id="app">
        <?=$data['sidebar'];?>

        <div id="main" data-rol="<?=$escape($data['idRol'] ?? '');?>" data-paciente="<?=$patientId;?>" data-sexo="<?=$escape($patientSex);?>" data-p11-csrf="<?=$escape($p11CsrfToken);?>">
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php'; ?>

            <main class="main-content container-fluid doctor-pain-evaluation-content">
                <header class="doctor-page-hero doctor-pain-evaluation-hero" aria-labelledby="doctor-pain-evaluation-title">
                    <div class="doctor-pain-evaluation-hero-copy">
                        <p class="dashboard-eyebrow">HISTORIA CLÍNICA</p>
                        <h1 id="doctor-pain-evaluation-title"><?=$escape($title);?></h1>
                        <p>Registra y revisa la evaluación visual y descriptiva del dolor del paciente.</p>
                        <?php if ($patientName !== ''): ?>
                            <p class="doctor-pain-evaluation-patient-context">Paciente autorizado: <strong><?=$escape($patientName);?></strong></p>
                        <?php endif; ?>
                    </div>
                    <div class="doctor-pain-evaluation-hero-actions">
                        <a class="doctor-pain-evaluation-action" data-doctor-back-action href="<?=$patientUrl;?>">
                            <i data-feather="arrow-left" aria-hidden="true"></i>
                            <span>Volver al expediente</span>
                        </a>
                    </div>
                </header>

                <section class="doctor-pain-evaluation-sections" aria-label="Evaluación del dolor">
                    <article class="card doctor-pain-evaluation-card doctor-pain-maps-card">
                        <header class="doctor-pain-evaluation-section-heading">
                            <p class="dashboard-eyebrow">MAPA DEL DOLOR</p>
                            <h2>Evaluación visual</h2>
                            <p>
                                A continuación, deberá indicar las zonas donde siente malestar utilizando los siguientes colores:
                                Rojo: Área con Dolor, Amarillo: Área Dormida y con Dolor, Verde: Área con Molestia al tacto y roce de la ropa,
                                Azul: Área sin Sensibilidad y sin Dolor, Morado: Área con Punzadas y Calambres.
                                Evite marcar la zona con una “X”. En su lugar, cúbrala por completo utilizando el color que corresponda.
                            </p>
                        </header>
                        <div class="card-body doctor-pain-evaluation-card-body">
                            <div class="row doctor-pain-maps-row">
                                <div id="conteFrente" class="col-xl-6 col-lg-6 col-md-12 col-sm-12 doctor-pain-map-panel"></div>
                                <div id="conteEspalda" class="col-xl-6 col-lg-6 col-md-12 col-sm-12 doctor-pain-map-panel"></div>
                            </div>
                        </div>
                    </article>

                    <article class="card doctor-pain-evaluation-card doctor-pain-questions-card" aria-label="Preguntas de evaluación del dolor">
                        <div id="contePreguntas"></div>
                    </article>
                </section>
            </main>

            <?php include_once __DIR__ . '/../components/footer-doctor.php';?>
        </div>
    </div>

    <script src="<?=RUTA_JS;?>voice-utilities.js"></script>
    <script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>    
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS;?>main.js"></script>
    <script src="<?=RUTA_JS;?>search-main.js"></script>
    
    </body>
    </html>
