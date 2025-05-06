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

$contenidoAntescedentesNP1 = $model->editorTextoANP($data['idPaciente'], 1); 
$contenidoAntescedentesNP2 = $model->editorTextoANP($data['idPaciente'], 2); 
$contenidoAntescedentesNP3 = $model->editorTextoANP($data['idPaciente'],3); 
$contenidoAntescedentesNP4 = $model->editorTextoANP($data['idPaciente'],4); 
$contenidoAntescedentesNP5 = $model->editorTextoANP($data['idPaciente'],5); 
$contenidoAntescedentesNP6 = $model->editorTextoANP($data['idPaciente'],6); 
$contenidoAntescedentesNP7 = $model->editorTextoANP($data['idPaciente'],7); 

$model2 = new PacienteModulosModelo();
$comentariosModulos = $model2->comentariosModulos($data['idPaciente'],3); 
?> 

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=$data['title'];?></title>
<link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
<link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
<link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
<!-- CKEditor 5 desde CDN -->
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- html2pdf -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="<?=RUTA_JS;?>loader.js"></script>
</head>

<body>
<div class="LoaderPage"></div>
<div id="app">
<?=$data['sidebar'];?>  

<div id="main" data-rol="<?=$data['idRol'];?>" data-paciente="<?=$data['idPaciente'];?>">
<nav class="navbar navbar-header navbar-expand navbar-light">
<a class="sidebar-toggler"><span class="navbar-toggler-icon"></span></a>
<button class="btn navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
<span class="navbar-toggler-icon"></span>
</button>
<div class="collapse navbar-collapse" id="navbarSupportedContent">
<ul class="navbar-nav d-flex align-items-center navbar-light ms-auto"></ul>
</div>
</nav>

<div class="main-content container-fluid">
<div class="page-title mb-4">
<h8><?=$data['nombre'];?></h8>
<h3><?=$data['title'];?></h3>
</div>

<section class="section">
<div class="card">
<div class="card-header">
<button class="btn btn-danger float-end me-2" onclick="downloadPDF('<?=RUTA_IMAGES?>', '<?=$data['nombre']?>', '<?=$data['title']?>')"><i data-feather="printer"></i></button>
</div>

<div class="card-body">
<!---------- EDITOR DE TEXTO CKEditor ---------->
<textarea id="editor">

<!-- CONTENIDO INICIAL -->
<div class="col-12 col-sm-6 mb-3">
<h8 class="text-primary fw-bold texto"><strong>Nombre:</strong> <?=$data['nombre'] ?? ''?></h8>
</div>
<br>
<strong> A continuacion, responda las siguientes preguntas indicando si presenta alguna de estas conductas o hábitos: </strong>
<?=$contenidoAntescedentesNP1?>
<?=$contenidoAntescedentesNP2?>
<?=$contenidoAntescedentesNP3?>
<?=$contenidoAntescedentesNP4?>
<?=$contenidoAntescedentesNP5?>
<?=$contenidoAntescedentesNP6?>
<?=$contenidoAntescedentesNP7?>
<?=$comentariosModulos?>
</textarea>
</div>
</div>
</section>
</div>

<?php include_once __DIR__ . '/../components/footer-mvsd.php';?>  
</div>
</div>

<script src="<?=RUTA_JS;?>CKEditor-utilities.js"></script>
<script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="<?=RUTA_JS;?>app.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
<script src="<?=RUTA_JS;?>main.js"></script>
</body>
</html>
