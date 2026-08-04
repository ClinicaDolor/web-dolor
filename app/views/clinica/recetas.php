<?php 
use App\Config\Database;
use App\Models\RecetaExternoModel;
use App\Models\PacienteModel;
$bd = Database::getInstance();
?>
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
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
<link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?=RUTA_JS;?>loader.js"></script>
<style>
.row {
display: flex;
flex-wrap: wrap;
}
.resizable {
transition: all 0.3s ease-in-out;
}
</style>

</head>
<body>
<div class="LoaderPage"></div>
<div id="app">

<?=$data['sidebar'];?>

<div id="main">

<!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

<div class="main-content container-fluid">

<div class="d-flex align-items-center justify-content-between mb-3">
<!-- 1. Título al inicio (Izquierda) -->
<div class="page-title">
<h3 class="m-0"><?=$data['title'];?></h3>
</div>

<!-- 2. Botones al final (Derecha) -->
<div class="d-flex align-items-center gap-2">
<!-- Primero: Nueva Receta -->
<a href="/clinica/recetas/nueva" class="btn btn-sm btn-primary">
<i data-feather="plus"></i> Nueva Receta
</a>

<!-- Segundo: Toggle -->
<button id="toggleButton" class="btn icon btn-light text-dark" onclick="toggleSize()">
<i id="toggleIcon" data-feather="columns"></i>
</button>
</div>
</div>

<section>
<div class="row mt-3">
 
<!---------- PACIENTES REGISTRADOS ---------->
<div class="col-12 col-sm-6 resizable" data-index="0">

<div class="card">
<div class="card-header">
<h4 class="card-title">Pacientes</h4>
</div>
<div class="card-body">

<div id="contePacientesRegistrados">
<?php
$recetas = [];
try {
$stmt = $bd->query("SELECT * FROM receta_medica ORDER BY fecha_hora DESC");
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
foreach ($rows as $row) {
$pm = new PacienteModel($row['id_paciente']);
$recetas[] = [
'id' => $row['id'],
'fecha_hora' => $row['fecha_hora'],
'paciente' => $pm->getNombreCompleto(),
'id_paciente' => $row['id_paciente']
];
}
} catch (\Exception $e) {
$recetas = [];
}
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
<tr onclick="window.location.href='/clinica/receta/<?=$r['id']?>'" style="cursor:pointer">
<td class="text-center"><?=$r['id']?></td>
<td><?=$r['paciente']?></td>
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

<div class="card">
<div class="card-header">
<h4 class="card-title">Pacientes Externos</h4>
</div>
<div class="card-body">

<div id="conteRecetasExternos">
<?php
$model = new RecetaExternoModel();
echo $model->mostrarTablaRecetas();
?>
</div>

</div>
</div>

</div>

</div>
</section>

</div>

<footer>
<div class="footer clearfix mb-0 text-muted">
<div class="float-start">
<p>2025 &copy; tratamientosdeldolor.org</p>
</div>
</div>
</footer>

</div>
</div>
<script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="<?=RUTA_JS;?>app.js"></script>
<script src="<?=RUTA_JS;?>main.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
<script src="<?=RUTA_JS?>search-main.js"></script>

<script>

let tableExternos = document.querySelector('#tableRecetasExternos');
if (tableExternos) {
new simpleDatatables.DataTable(tableExternos, {
searchable: true,
fixedHeight: true,
perPageSelect: [10, 25, 50, 100],
columns: [
{ select: 2, sort: "desc" }
]
});
}

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
