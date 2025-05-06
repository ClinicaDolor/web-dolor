<?php 
use App\Config\Database;
use App\Models\PacienteModulosModelo;
$bd = Database::getInstance();
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
<!-- BOTONES DE IMPRESIÓN Y DESCARGA -->
<!-- <button class="btn btn-primary float-end me-2" onclick="printEditor()"><i data-feather="printer"></i></button> -->
<button class="btn btn-danger float-end me-2" onclick="downloadPDF('<?=RUTA_IMAGES?>', '<?=$data['nombre']?>', '<?=$data['title']?>')"><i data-feather="printer"></i></button>
</div>

<div class="card-body">
<!-- Editor de texto CKEditor -->
<textarea id="editor">

<!-- CONTENIDO INICIAL -->
<div class="col-12 col-sm-6 mb-3">
<h8 class="text-primary fw-bold texto"><strong>Nombre:</strong> <?=$data['nombre'] ?? ''?></h8>
</div>

<table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">

<tr>
<td><strong>Edad:</strong> <?= isset($data['edad']) ? $data['edad'] . ' años' : 'Sin Información' ?></td>
<td><strong>Sexo:</strong> <?=$data['sexo'] ?? 'Sin información'?></td>
<td colspan="2"><strong>Estado civil:</strong> <?=$data['estado_civil'] ?? 'Sin información'?></td>
</tr>

<tr>
<td colspan="2"><strong>Fecha de nacimiento:</strong> <?=$data['fecha_nacimiento'] ?? 'Sin información'?></td>
<td colspan="2"><strong>CURP:</strong> <?=$data['curp'] ?? 'Sin información'?></td>
</tr>

<tr>
<td colspan="2"><strong>Lugar de origen:</strong> <?=$data['lugar_origen'] ?? 'Sin información'?></td>
<td colspan="2"><strong>Lugar de residencia:</strong> <?=$data['lugar_residencia'] ?? 'Sin información'?></td>
</tr>

<tr>
<td><strong>Ocupación:</strong> <?=$data['ocupacion'] ?? 'Sin información'?></td>
<td><strong>Número de hijos:</strong> <?=$data['num_hijos'] ?? 'Sin información'?></td>
<td colspan="2"><strong>Edad de sus hijos:</strong> <?= isset($data['edad_hijos']) ? $data['edad_hijos'] : 'Sin Información' ?></td>
</tr>

<tr><td class="bg-primary" colspan="4"></td></tr>

<tr>
<td colspan="4"><strong>¿Quien lo recomienda? O porque medio se entero de la Clínica del Dolor y Cuidados Paliativos?:</strong></td>
</tr>

<tr>
<td colspan="4"><strong>Persona que lo recomendó:</strong> <?= isset($data['quien_recomienda']) ? $data['quien_recomienda'] : 'Sin Información' ?></td>
</tr>

<tr><td colspan="4"><strong>Redes sociales:</strong>  <?=$data['redes_sociales'] ?? ''?></td></tr>

<tr><td class="bg-primary" colspan="4"></td></tr>

<tr><td colspan="4"><strong>Motivo de atención en la Clínica del Dolor y Cuidados Paliativos:</strong></td></tr>
<tr><td colspan="4"><?=$data['motivo_atencion'] ?? ''?></td></tr>

<tr><td class="bg-primary" colspan="4"></td></tr>

<tr><td colspan="4"><strong>Dirección actual:</strong> <?=$data['calle'] ?? ''?> <?=$data['num_interior'] ?? ''?> <?=$data['num_exterior'] ?? ''?> <?=$data['colonia'] ?? ''?></td></tr>

<tr>
<td colspan="3"><strong>Distancia a Clínica del Dolor Hospital Ángeles Lomas:</strong> <?= $data['distancia'] ? $data['distancia'] : '' ?></td>
<td><strong>minutos / horas</strong></td>
</tr>

<tr><td class="bg-primary" colspan="4"></td></tr>

<tr>
<td colspan="4"><strong>Correo electrónico:</strong> <?=$data['email'] ?? ''?></td>
</tr>

<tr>
<td colspan="2"><strong>Tel. de casa:</strong> <?=$data['telefono'] ?? ''?></td>
<td colspan="2"><strong>Celular:</strong> <?=$data['celular'] ?? ''?></td>
</tr>

<tr>
<td ><strong>¿Tiene cuidador(a)?</strong> <?php if(isset($data['cuidador'])){ echo ($data['cuidador'])? 'Si': 'No';}else{echo '';} ?></td>
<td colspan="2"><strong>Nombre:</strong> <?=$data['cuidador'] ?? ''?></td>
<td><strong>Teléfono:</strong> <?=$data['cuidador_telefono'] ?? ''?></td>
</tr>

<tr>
<td colspan="2"><strong> Nombre del familiar responsable:</strong> <?=$data['res_nombre'] ?? ''?></td>
<td colspan="2"><strong>Teléfono:</strong> <?=$data['res_telefono'] ?? ''?></td>
</tr>

</table>
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
