<?php
$escape = static fn ($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
$idReceta = (int)($data['id_receta'] ?? 0);
$idPaciente = (int)($data['id_paciente'] ?? 0);
$nombrePaciente = $escape($data['nombre_paciente'] ?? '');
$nombrePacienteVisible = $nombrePaciente !== '' ? $nombrePaciente : 'Paciente';
$titulo = $escape($data['title'] ?? 'Receta');
$volverExpedienteUrl = htmlspecialchars(SERVIDOR . 'clinica/paciente/' . $idPaciente, ENT_QUOTES, 'UTF-8');
$editarPacienteUrl = htmlspecialchars(SERVIDOR . 'clinica/paciente/editar/' . $idPaciente, ENT_QUOTES, 'UTF-8');
$sexoVisible = (($data['sexo'] ?? '') === 'M') ? 'Masculino' : 'Femenino';
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
<link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
<title><?=$titulo;?></title>
<link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
<link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?=RUTA_JS;?>loader.js"></script>

</head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-recipe-detail-page">
<div class="LoaderPage"></div>
<div id="app">

<?=$data['sidebar'];?>
 
<div id="main">

<!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

<div class="main-content container-fluid">

<header class="doctor-page-hero doctor-recipe-detail-hero">
<div class="doctor-page-hero-copy">
<p class="dashboard-eyebrow">DETALLE DE RECETA</p>
<h1>Receta médica</h1>
<p>Consulta la receta médica de <?=$nombrePacienteVisible;?> y su información clínica asociada.</p>
</div>
<div class="doctor-page-hero-actions">
<a class="btn doctor-page-hero-action" href="<?=$volverExpedienteUrl;?>">
<i data-feather="arrow-left" aria-hidden="true"></i>
<span>Volver al expediente</span>
</a>
</div>
</header>

<section class="mt-3 doctor-recipe-detail-grid">

<div class="row">
<div class="col-12 col-sm-6">

<div class="card doctor-recipe-detail-card doctor-recipe-patient-card">
<div class="card-header">
<h4 class="card-title">Información del paciente</h4>
<div class="doctor-recipe-card-actions">
<a href="<?=$editarPacienteUrl;?>" class="btn doctor-recipe-card-action">
<i data-feather="edit-2" aria-hidden="true"></i>
<span>Editar paciente</span>
</a>
</div>
</div>
<div class="card-body">

<div class="row">
<div class="col-12 col-sm-12">
<label class="text-primary"><small>Nombre Paciente:</small></label>
<div class="fs-4"><?=$nombrePacienteVisible;?></div>
</div>
</div>

<div class="row mt-2">
<div class="col-12 col-sm-4">
<label class="text-primary"><small>Fecha Alta:</small></label>
<div class="fs-5"><?=(new DateTime($data['fecha_alta']))->format('d/m/Y');[0];?></div>
</div>

<div class="col-12 col-sm-4">
<label class="text-primary"><small>Fecha Nacimiento:</small></label>
<div class="fs-5"><?=date("d/m/Y", strtotime($data['fecha_nacimiento']));?></div>
</div>

<div class="col-12 col-sm-4">
<label class="text-primary"><small>Edad:</small></label>
<div class="fs-5"><?=$escape($data['edad'] ?? '');?> años</div>
</div>

</div>

<div class="row mt-2">

<div class="col-12 col-sm-4">
<label class="text-primary"><small>Sexo:</small></label>
<div class="fs-5"><?=$sexoVisible;?></div>
</div>

<div class="col-12 col-sm-4">
<label class="text-primary"><small>Estado Civil:</small></label>
<div class="fs-5"><?=$escape($data['estado_civil'] ?? '');?></div>
</div>

<div class="col-12 col-sm-4">
<label class="text-primary"><small>CURP:</small></label>
<div class="fs-5"><?=$escape($data['curp'] ?? '');?></div>
</div>

</div>

<div class="mt-3 fs-6 text-success">Contacto del paciente:</div>

<div class="row mt-3">

<div class="col-12 col-sm-4">
<label class="text-primary"><small>Email:</small></label>
<div class="fs-5"><?=$escape($data['email'] ?? '');?></div>
</div>

<div class="col-12 col-sm-4">
<label class="text-primary"><small>Telefono:</small></label>
<div class="fs-5"><?=$escape($data['telefono'] ?? '');?></div>
</div>

<div class="col-12 col-sm-4">
<label class="text-primary"><small>Celular:</small></label>
<div class="fs-5"><?=$escape($data['celular'] ?? '');?></div>
</div>

</div>         

</div>

</div>

</div>
<div class="col-12 col-sm-6">

<div class="card doctor-recipe-detail-card doctor-recipe-content-card">
<div class="card-header">
<h4 class="card-title">Detalle de la receta</h4>
<div class="doctor-recipe-card-actions">
<a href="javascript:void(0)" onclick="imprimirReceta(<?=$idReceta;?>, 'registrado')" class="btn doctor-recipe-card-action" aria-label="Imprimir receta" title="Imprimir receta">
<i data-feather="printer" aria-hidden="true"></i>
<span>Imprimir receta</span>
</a>
</div>
</div>
<div class="card-body">

<div><small class="text-primary">Fecha: </small> <label class="fs-5"><?=$escape($data['fecha_receta'] ?? '');?></label>, <small class="text-primary">Hora: </small> <label class="fs-5"><?=$escape($data['hora_receta'] ?? '');?></label></div>
<div class="mt-3"><small class="text-primary">Diagnóstico: </small> <label class="fs-5"><?=$escape($data['diagnostico_receta'] ?? '');?></label></div>

<div class="mt-4">
<small class="text-primary">Signos Vitales:</small>

<div class="row mt-2">
<div class="col-md-6 mb-2">
<small class="text-muted">Tensión Arterial (TA):</small><br>
<label class="fs-5"><?=!empty($data['ta']) ? $escape($data['ta']).' mmHg' : 'S/I';?></label>
</div>

<div class="col-md-6 mb-2">
<small class="text-muted">Frecuencia Cardíaca (FC):</small><br>
<label class="fs-5"><?=!empty($data['fc']) ? $escape($data['fc']).' lpm' : 'S/I';?></label>
</div>

<div class="col-md-6 mb-2">
<small class="text-muted">Saturación de Oxígeno (SpO₂):</small><br>
<label class="fs-5"><?=!empty($data['spo2']) ? $escape($data['spo2']).' %' : 'S/I';?></label>
</div>

<div class="col-md-6 mb-2">
<small class="text-muted">Temperatura</small><br>
<label class="fs-5"><?=!empty($data['temperatura']) ? $escape($data['temperatura']).' °C' : 'S/I';?></label>
</div>
</div>
</div>

<label class="mt-4"><small class="text-primary">Medicamento: </small></label>
<div class="fs-5 doctor-recipe-rich-text"><?=$data['medicamento_receta'];?></div>

    
</div>
</div>

</div>
</div>

</section>

</div>

<?php include_once __DIR__ . '/../components/footer-doctor.php';?>

</div>
</div>

<script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="<?=RUTA_JS;?>app.js"></script> 
<script src="<?=RUTA_JS;?>main.js"></script>
<script src="<?=RUTA_JS?>search-main.js"></script>

<?php include_once __DIR__ . '/../components/modal-impresion-receta.php';?>

</body>
</html>

