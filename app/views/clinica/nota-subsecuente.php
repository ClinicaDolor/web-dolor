<?php
$nombrePacienteSeguro = htmlspecialchars((string)($data['nombre_paciente'] ?? ''), ENT_QUOTES, 'UTF-8');
$nombrePacienteVisible = $nombrePacienteSeguro !== '' ? $nombrePacienteSeguro : 'Paciente';
$volverExpedienteUrl = htmlspecialchars(SERVIDOR . 'clinica/paciente/' . (int)($data['idPaciente'] ?? 0), ENT_QUOTES, 'UTF-8');
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
</head>
<script>

document.addEventListener("DOMContentLoaded", function() {
DetalleNota()
});

function DetalleNota(){

const usuarioDiv = document.getElementById('main');
const idNota = usuarioDiv.getAttribute('data-idnota');

fetch(`/buscar/nota-subsecuente/${encodeURIComponent(idNota)}`)
.then(response => {
if (!response.ok) {
throw new Error('Error en la respuesta del servidor: ' + response.status);
}
return response.text();
})
.then(data => {

const resultsContainer = document.getElementById('detalleNota');
resultsContainer.innerHTML = data;
feather.replace();

const tabla = document.querySelector("#LaboratorioNotaSub");
if (tabla) {
dataTable = new simpleDatatables.DataTable(tabla,{
searchable: true,
fixedHeight: true,
perPageSelect: false,
searchable: true,
columns: [
{
select: 0, sort: "desc"
},
{ select: [2], sortable: false },
]
});
}     

});

}

</script>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-note-page">
<div class="LoaderPage"></div>
<div id="app">
<?=$data['sidebar'];?>

<div id="main" data-idnota="<?=$data['idNota'];?>">
<!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
<div class="main-content container-fluid">

<header class="doctor-page-hero doctor-followup-hero">
<div class="doctor-page-hero-copy">
<p class="dashboard-eyebrow">Seguimiento clínico</p>
<h1><?=htmlspecialchars((string)$data['title'], ENT_QUOTES, 'UTF-8');?></h1>
<p>Consulta la evolución clínica y la documentación asociada de <?=$nombrePacienteVisible;?>.</p>
</div>
<div class="doctor-page-hero-actions">
<a class="btn doctor-page-hero-action" href="<?=$volverExpedienteUrl;?>">
<i data-feather="arrow-left" aria-hidden="true"></i>
<span>Volver al expediente</span>
</a>
<button id="toggleButton" type="button" class="btn doctor-followup-toggle" onclick="toggleSize()" aria-label="Cambiar distribución de la pantalla" title="Cambiar distribución de la pantalla">
<i id="toggleIcon" data-feather="columns" aria-hidden="true"></i>
<span>Cambiar distribución</span>
</button>
</div>
</header>

<section class="doctor-followup-section">

<div class="row">
<div class="col-12 col-sm-6 resizable">

<div class="card doctor-followup-card doctor-followup-patient-card">
<div class="card-header">
<h4 class="card-title">Información del Paciente</h4>
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
<div class="fs-5"><?=$data['edad'];?> años</div>
</div>

</div>

<div class="row mt-2">

<div class="col-12 col-sm-4">
<label class="text-primary"><small>Sexo:</small></label>
<div class="fs-5"><?=($data['sexo'] == 'M')? 'Masculino': 'Femenino';?></div>
</div>

<div class="col-12 col-sm-4">
<label class="text-primary"><small>Estado Civil:</small></label>
<div class="fs-5"><?=$data['estado_civil'];?></div>
</div>

<div class="col-12 col-sm-4">
<label class="text-primary"><small>CURP:</small></label>
<div class="fs-5"><?=$data['curp'];?></div>
</div>

</div>

<div class="mt-3 fs-6 text-success">Contacto del paciente:</div>

<div class="row mt-3">

<div class="col-12 col-sm-4">
<label class="text-primary"><small>Email:</small></label>
<div class="fs-5"><?=$data['email'];?></div>
</div>

<div class="col-12 col-sm-4">
<label class="text-primary"><small>Telefono:</small></label>
<div class="fs-5"><?=$data['telefono'];?></div>
</div>

<div class="col-12 col-sm-4">
<label class="text-primary"><small>Celular:</small></label>
<div class="fs-5"><?=$data['celular'];?></div>
</div>

</div>         

</div>

</div>

</div>
<div class="col-12 col-sm-6 resizable">

<div class="card doctor-followup-card doctor-followup-detail-card">
<div class="card-header">
<h4 class="card-title">Detalle de la Nota</h4>
</div>
<div class="card-body">

<div id="detalleNota" class="doctor-followup-detail-content"></div>

</div>
</div>

</div>
</div>

</section>

</div>


<!----- FOOTER ---------->
<?php include_once __DIR__ . '/../components/footer-doctor.php';?>
</div>
</div>
<script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="<?=RUTA_JS;?>app.js"></script> 
<script src="<?=RUTA_JS;?>main.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
<script src="<?=RUTA_JS?>search-main.js"></script>
<?php include_once __DIR__ . '/../components/modal-impresion-receta.php';?>

</body>
</html>

