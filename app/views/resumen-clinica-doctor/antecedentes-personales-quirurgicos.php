<?php 
use App\Config\Database;
use App\Models\PacienteModulosModelo;
use App\Models\AntecedentesQuirurgicos;
$bd = Database::getInstance();

$model2 = new PacienteModulosModelo();
$model = new AntecedentesQuirurgicos();
$contenidoAntescedentesQR= $model->editorTextoAQ($data['idPaciente']); 

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
<script src="<?=RUTA_JS;?>editor-functions.js"></script>
<script src="<?=RUTA_JS;?>loader.js"></script>
</head>

<body>
<div class="LoaderPage"></div>
<div id="app">
<?=$data['sidebar'];?>  

<div id="main" data-rol="<?=$data['idRol'];?>" data-paciente="<?=$data['idPaciente'];?>" data-tema="<?=$data['title'];?>">
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

<!-- CONTENIDO INICIAL -->
<div id="contenido-fijo">
<div class="row">

<div class="col-12 mb-3">
<h8 class="text-primary fw-bold texto"><strong>Nombre:</strong> <?=$data['nombre'] ?? ''?></h8>
</div>

<div class="col-12 mb-3">
<strong>A continuación, indique si a su paciente se le han realizado una o más cirugías:</strong>
</div>

<div class="col-12">
<?=$contenidoAntescedentesQR?>
</div>

</div>
</div>

<!---------- EDITOR DE TEXTO CKEditor ---------->
<label class="mt-4 mb-1" for="editor"><strong>Notas adicionales:</strong></label>
<textarea id="editor" rows="20" cols="80"><?= htmlspecialchars($contenidoEditor = $model2->contenidoEditorPAC($data['idPaciente'],$data['title'])) ?></textarea>
</div>

<div class="card-footer">
<button class="btn btn-success float-end" onclick="guardarContenidoEditor()">Guardar cambios</button>
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
