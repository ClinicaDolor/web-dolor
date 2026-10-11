<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
<link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
<title><?=$data['title'];?></title>
<link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
<link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?=RUTA_JS;?>loader.js"></script>

</head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-external-recipe-page">
<div class="LoaderPage"></div>
<div id="app">

<?=$data['sidebar'];?>

<div id="main"> 

<!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

<main class="main-content container-fluid doctor-external-recipe-content" id="main-content">
<header class="doctor-page-hero doctor-external-recipe-hero">
    <div class="doctor-page-hero-copy">
        <p class="dashboard-eyebrow">RECETA EXTERNA</p>
        <h1>Detalle de receta</h1>
        <p>Consulta la información registrada y sus indicaciones clínicas.</p>
    </div>
    <div class="doctor-page-hero-actions">
        <button id="toggleButton" type="button" class="btn doctor-external-recipe-layout-toggle" onclick="toggleSize()" aria-label="Cambiar distribución de la pantalla" title="Cambiar distribución de la pantalla">
            <i id="toggleIcon" data-feather="columns" aria-hidden="true"></i>
            <span>Cambiar distribución</span>
        </button>
    </div>
</header>

<section class="doctor-external-recipe-section" aria-label="Detalle de receta externa">
<div class="row doctor-external-recipe-grid">
<div class="col-12 col-sm-6 resizable" data-index="0">

<div class="card doctor-external-recipe-card doctor-external-recipe-patient-card">
<div class="card-header doctor-external-recipe-card-header">
<div>
<p class="dashboard-eyebrow">IDENTIFICACIÓN</p>
<h2 class="doctor-external-recipe-card-title">Información del paciente</h2>
</div>
<a href="<?=SERVIDOR?>clinica/receta-externo/editar/<?=$data['id_receta'];?>" class="btn doctor-external-recipe-action">
<i data-feather="edit-2" aria-hidden="true"></i>
<span>Editar</span>
</a>
</div>
<div class="card-body doctor-external-recipe-card-body">

<div class="row">
<div class="col-12 col-sm-12">
<span class="doctor-external-recipe-label">Nombre del paciente</span>
<div class="doctor-external-recipe-patient-name" id="extNombreCompleto"><?=$data['nombre_paciente'];?></div>
</div>
</div>

<div class="row doctor-external-recipe-patient-facts">
<div class="col-12 col-sm-4 doctor-external-recipe-field">
<span class="doctor-external-recipe-label">Edad</span>
<div class="doctor-external-recipe-value" id="extEdadTexto"><?=$data['edad'] ?? 'S/I';?> años</div>
</div>

<div class="col-12 col-sm-4 doctor-external-recipe-field">
<span class="doctor-external-recipe-label">Sexo</span>
<div class="doctor-external-recipe-value" id="extSexoTexto"><?=($data['sexo'] == 'M')? 'Masculino': (($data['sexo'] == 'F')? 'Femenino' : 'S/I');?></div>
</div>

<div class="col-12 col-sm-4 doctor-external-recipe-field">
<span class="doctor-external-recipe-label">Fecha de nacimiento</span>
<div class="doctor-external-recipe-value" id="extFechaNacimientoTexto"><?=!empty($data['fecha_nacimiento']) ? date("d/m/Y", strtotime($data['fecha_nacimiento'])) : 'S/I';?></div>
</div>
</div>

</div>
</div>

</div>
<div class="col-12 col-sm-6 resizable" data-index="1">

<div class="card doctor-external-recipe-card doctor-external-recipe-detail-card">
<div class="card-header doctor-external-recipe-card-header">
<div>
<p class="dashboard-eyebrow">INDICACIONES</p>
<h2 class="doctor-external-recipe-card-title">Detalle de la receta</h2>
</div>
<a href="javascript:void(0)" onclick="imprimirReceta(<?=$data['id_receta'];?>, 'externo')" class="btn doctor-external-recipe-action" aria-label="Imprimir receta" title="Imprimir receta">
<i data-feather="printer" aria-hidden="true"></i>
<span>Imprimir</span>
</a>
</div>
<div class="card-body doctor-external-recipe-card-body">

<div class="doctor-external-recipe-meta">
<div class="doctor-external-recipe-field"><span class="doctor-external-recipe-label">Fecha</span><span class="doctor-external-recipe-value"><?=$data['fecha_receta'];?></span></div>
<div class="doctor-external-recipe-field"><span class="doctor-external-recipe-label">Hora</span><span class="doctor-external-recipe-value"><?=$data['hora_receta'];?></span></div>
</div>
<div class="doctor-external-recipe-clinical-field">
<span class="doctor-external-recipe-label">Diagnóstico</span>
<div class="doctor-external-recipe-long-value"><?=$data['diagnostico_receta'];?></div>
</div>

<div class="doctor-external-recipe-vitals">
<h3 class="doctor-external-recipe-subtitle">Signos vitales</h3>

<div class="row doctor-external-recipe-vitals-grid">
<div class="col-md-6 doctor-external-recipe-field">
<span class="doctor-external-recipe-label">Tensión arterial (TA)</span>
<span class="doctor-external-recipe-value"><?=!empty($data['ta']) ? $data['ta'].' mmHg' : 'S/I';?></span>
</div>

<div class="col-md-6 doctor-external-recipe-field">
<span class="doctor-external-recipe-label">Frecuencia cardíaca (FC)</span>
<span class="doctor-external-recipe-value"><?=!empty($data['fc']) ? $data['fc'].' lpm' : 'S/I';?></span>
</div>

<div class="col-md-6 doctor-external-recipe-field">
<span class="doctor-external-recipe-label">Saturación de oxígeno (SpO₂)</span>
<span class="doctor-external-recipe-value"><?=!empty($data['spo2']) ? $data['spo2'].' %' : 'S/I';?></span>
</div>

<div class="col-md-6 doctor-external-recipe-field">
<span class="doctor-external-recipe-label">Temperatura</span>
<span class="doctor-external-recipe-value"><?=!empty($data['temperatura']) ? $data['temperatura'].' °C' : 'S/I';?></span>
</div>
</div>
</div>

<div class="doctor-external-recipe-medication">
<span class="doctor-external-recipe-label">Medicamento</span>
<div class="doctor-external-recipe-long-value"><?=$data['medicamento_receta'];?></div>
</div>

</div>
</div>

</div>
</div>

</section>

</main>

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
