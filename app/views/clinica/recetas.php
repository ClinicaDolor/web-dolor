<?php 
$titulo = htmlspecialchars((string)($data['title'] ?? 'Recetas'), ENT_QUOTES, 'UTF-8');
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
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
<link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?=RUTA_JS;?>loader.js"></script>

</head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-recipes-page">
<div class="LoaderPage"></div>
<div id="app">

<?=$data['sidebar'];?>

<div id="main">

<!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

<div class="main-content container-fluid">

<header class="doctor-page-hero doctor-recipes-hero">
<div class="doctor-page-hero-copy">
<p class="dashboard-eyebrow">DIRECTORIO DE RECETAS</p>
<h1>Recetas</h1>
<p>Consulta y organiza las recetas médicas internas y externas disponibles para la clínica.</p>
</div>
<div class="doctor-page-hero-actions">
<a href="/clinica/recetas/nueva" class="btn doctor-page-hero-action">
<i data-feather="plus" aria-hidden="true"></i>
<span>Nueva receta</span>
</a>
<button id="toggleButton" type="button" class="btn doctor-recipes-toggle" onclick="toggleSize()" aria-label="Cambiar distribución" title="Cambiar distribución">
<i id="toggleIcon" data-feather="columns" aria-hidden="true"></i>
</button>
</div>
</header>

<section>
<div class="row mt-3 doctor-recipes-workspace">
 
<!---------- PACIENTES REGISTRADOS ---------->
<div class="col-12 col-sm-6 resizable" data-index="0">

<div class="card doctor-recipes-card doctor-recipes-registered-card">
<div class="card-header">
<h4 class="card-title">Pacientes</h4>
</div>
<div class="card-body">

<div id="contePacientesRegistrados" class="doctor-recipes-table-content">
<?php
$recetas = is_array($data['recetas'] ?? null) ? $data['recetas'] : [];
?>

<table class="table table-striped table-hover table-sm pb-0 mb-0" id="tableRecetasRegistrados">
<thead>
<tr>
<th class="text-center">#</th>
<th>Paciente</th>
<th>Fecha y Hora</th>
</tr>
</thead>
<tbody>
<?php foreach ($recetas as $r):
$fecha = (new \DateTime($r['fecha_hora']))->format('d/m/Y h:i a');
?>
<tr onclick="window.location.href='/clinica/receta/<?= (int)$r['id'];?>'" onkeydown="if(event.key === 'Enter' || event.key === ' '){event.preventDefault(); this.click();}" style="cursor:pointer" tabindex="0" role="link" aria-label="Abrir receta de <?=htmlspecialchars((string)$r['paciente'], ENT_QUOTES, 'UTF-8');?>">
<td class="text-center"><?=$r['id']?></td>
<td><?=htmlspecialchars((string)$r['paciente'], ENT_QUOTES, 'UTF-8');?></td>
<td><?=$fecha?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

</div>
</div>

</div>

<!---------- PACIENTES EXTERNOS ---------->
<div class="col-12 col-sm-6 resizable" data-index="1">

<div class="card doctor-recipes-card doctor-recipes-external-card">
<div class="card-header">
<h4 class="card-title">Pacientes Externos</h4>
</div>
<div class="card-body">

<div id="conteRecetasExternos" class="doctor-recipes-table-content">
<div class="alert alert-warning mb-0" role="status">
<strong>Recetas externas no disponibles temporalmente.</strong>
<p class="mb-0 mt-1">El catálogo permanecerá deshabilitado hasta contar con una relación de propiedad clínica verificable.</p>
</div>
</div>

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
<script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
<script src="<?=RUTA_JS?>search-main.js"></script>

<script>
let tableRegistrados = document.querySelector('#tableRecetasRegistrados');
if (tableRegistrados) {
new simpleDatatables.DataTable(tableRegistrados, {
searchable: true,
fixedHeight: true,
perPageSelect: [10, 25, 50, 100],
columns: [
{ select: 2, sort: "desc" }
]
});
}

</script>
</body>
</html>
