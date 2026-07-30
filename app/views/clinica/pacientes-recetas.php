<?php 
use App\Config\Database;
use App\Models\RecetaModel;
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
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/quill/quill.snow.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?=RUTA_JS;?>loader.js"></script>
<style>
.editor{
font-size: 20px;
height: 250px;
}
.row {
display: flex;
flex-wrap: wrap;
}
.resizable {
transition: all 0.3s ease-in-out; /* Suaviza el cambio de tamaño */
}
</style>
<script>
function AgregarReceta(idPaciente){

const referencia = 0;
const Diagnostico = document.getElementById('Diagnostico').value;
const contenidoReceta = document.querySelector('.ql-editor').innerHTML;

// Signos vitales
const TA = document.getElementById('TA').value;
const FC = document.getElementById('FC').value;
const SpO2 = document.getElementById('SpO2').value;
const Temperatura = document.getElementById('Temperatura').value;

document.querySelector('#Diagnostico').style.border = "";
document.querySelector('.ql-editor').style.border = "";

if(Diagnostico != ""){
if(contenidoReceta != '<p><br></p>'){

const parametros = {
idPaciente : idPaciente,
diagnostico : Diagnostico,
medicamento : contenidoReceta,
referencia : referencia,
ta: TA,
fc: FC,
spo2: SpO2,
temperatura: Temperatura,
};

fetch('/clinica/paciente/insert-receta', {
method: 'POST',
headers: {
'Content-Type': 'application/json'
},
body: JSON.stringify(parametros)
})
.then(response => response.json())
.then(data => {

if (data.resultado) {

idReceta = data.mensaje;

tableRecetas(idPaciente)
DetalleReceta(idReceta)

document.getElementById('Diagnostico').value = "";
document.querySelector('.ql-editor').innerHTML = "";

imprimirReceta(idReceta, 'registrado');

} else {
document.getElementById('mensaje').textContent = 'Error: ' + data.mensaje;
}

});

}else{
document.querySelector('.ql-editor').style.border = "2px solid #d44e31";
}
}else{
document.querySelector('#Diagnostico').style.border = "2px solid #d44e31";
}

}

function DetalleReceta(idReceta){

fetch(`/buscar/receta/${encodeURIComponent(idReceta)}`)
.then(response => {
if (!response.ok) {
throw new Error('Error en la respuesta del servidor: ' + response.status);
}
return response.text();
})
.then(data => {

const resultsContainer = document.getElementById('detalleReceta');
resultsContainer.innerHTML = data;
feather.replace();

});
} 

function tableRecetas(idPaciente){

fetch(`/buscar/tabla-recetas/${encodeURIComponent(idPaciente)}`)
.then(response => {
if (!response.ok) {
throw new Error('Error en la respuesta del servidor: ' + response.status);
}
return response.text();
})
.then(data => {

const resultsContainer = document.getElementById('conteRecetas');
resultsContainer.innerHTML = data;

const tabla = document.querySelector("#tableRecetas");
if (tabla) {
dataTable = new simpleDatatables.DataTable(tabla,{
searchable: true,
fixedHeight: true,
perPageSelect: false,
searchable: false,
columns: [
{
select: 1, sort: "desc"
}
]
});
}          
});
}

</script>

</head>
<body>
<div class="LoaderPage"></div>
<div id="app">

<?=$data['sidebar'];?>

<div id="main">

<!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

<div class="main-content container-fluid">

<button id="toggleButton" class="btn icon btn-light text-dark float-end" onclick="toggleSize()">
<i id="toggleIcon" data-feather="columns"></i>
</button>

<div class="page-title">
<h3><?=$data['title'];?></h3>
</div>

<section>
<div class="row mt-3">
<div class="col-12 col-sm-12 resizable">
<div class="card">

<div class="card-header text-light">
<h4 class="card-title">Información del paciente</h4>
</div>

<div class="card-body">

<div class="row">
<div class="col-12 col-sm-4">
<label class="text-primary"><small>Nombre Paciente:</small></label>
<div class="fs-5"><?=$data['nombre_paciente'];?></div>
</div>

<div class="col-12 col-sm-2">
<label class="text-primary"><small>Fecha Alta:</small></label>
<div class="fs-5"><?=(new DateTime($data['fecha_alta']))->format('d/m/Y');[0];?></div>
</div>

<div class="col-12 col-sm-2">
<label class="text-primary"><small>Fecha Nacimiento:</small></label>
<div class="fs-5"><?=date("d/m/Y", strtotime($data['fecha_nacimiento']));?></div>
</div>

<div class="col-12 col-sm-2">
<label class="text-primary"><small>Edad:</small></label>
<div class="fs-5"><?=$data['edad'];?> años</div>
</div>

<div class="col-12 col-sm-2">
<label class="text-primary"><small>Sexo:</small></label>
<div class="fs-5"><?=($data['sexo'] == 'M')? 'Masculino': 'Femenino';?></div>
</div>

</div>                

</div>
</div>

</div>
</div>
</section>

<section>

<div class="row">
<div class="col-12 col-sm-5 resizable">

<div class="card">
<div class="card-header">
<h4 class="card-title">Recetas</h4>
</div>
<div class="card-body">

<div id="conteRecetas">
<?php
try {
$stmt = $bd->query("SELECT * FROM receta_medica WHERE id_paciente = '".$data['idPaciente']."' ORDER BY fecha_hora DESC");
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
die("Error en la consulta: " . $e->getMessage());
}
?>

<table class="table table-striped table-hover table-sm pb-0 mb-0" id="table1">
<thead>
<tr>
<th class="text-center">#</th>
<th>Fecha y Hora</th>
</tr>
</thead>
<tbody>
<?php foreach ($registros as $registro): ?>
<tr id="receta<?=$registro['id']?>" onclick="DetalleReceta(<?=$registro['id']?>)">
<td class="text-center"><?=$registro['id']?></td>
<td><?=(new DateTime(datetime: $registro['fecha_hora']))->format('d/m/Y h:i a');?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

</div>
</div>

</div>
<div class="col-12 col-sm-7 resizable">

<div class="card">
<div class="card-header">
<h4 class="card-title">Detalle de la Receta</h4>
</div>
<div class="card-body">
<div id="detalleReceta">
<?php
$model = new RecetaModel();
echo $model->ultimaReceta($data['idPaciente']);
?>
</div>
</div>
</div>

<div class="card">
<div class="card-header text-primary">
<h4 class="card-title">Nueva Receta</h4>
</div>
<div class="card-body">


<div class="mb-3">
<label class="text-primary mb-1"><smallal>Diagnostico:</smallal></label>
<textarea class="form-control fs-5" id="Diagnostico" rows="2"></textarea>
</div>

<div class="row">
<div class="col-md-6 mb-3">
<label class="text-primary mb-1"><small>Tensión Arterial (TA):</small></label>
<input type="text" class="form-control fs-5" id="TA">
</div>

<div class="col-md-6 mb-3">
<label class="text-primary mb-1"><small>Frecuencia Cardíaca (FC):</small></label>
<input type="number" class="form-control fs-5" id="FC">
</div>

<div class="col-md-6 mb-3">
<label class="text-primary mb-1"><small>Saturación de Oxígeno (SpO₂):</small></label>
<input type="number" class="form-control fs-5" id="SpO2">
</div>

<div class="col-md-6 mb-3">
<label class="text-primary mb-1"><small>Temperatura:</small></label>
<div class="input-group">
<input type="number" class="form-control fs-5" id="Temperatura" step="0.1">
<span class="input-group-text fs-5">°C</span>
</div>
</div>
</div>

<label class="text-primary mt-3 mb-1"><smallal>Medicamento:</smallal></label>
<div id="snow" class="editor"></div>
<div class="text-end mt-3"><button class="btn btn-success" onclick="AgregarReceta(<?=$data['idPaciente'];?>)">Agregar Receta <i data-feather="chevron-right"></i></button></div>
<div id="mensaje"></div>
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
<script src="<?=RUTA_PUBLIC;?>libs/quill/quill.min.js"></script>
<script src="<?=RUTA_JS?>search-main.js"></script>

<?php include_once __DIR__ . '/../components/modal-impresion-receta.php';?>

<script>

let table1 = document.querySelector('#table1');
let dataTable = new simpleDatatables.DataTable(table1,{
searchable: true,
fixedHeight: true,
perPageSelect: false,
searchable: false,
columns: [
{
select: 1, sort: "desc"
}
]
});

var snow = new Quill('#snow', {
theme: 'snow',
modules: {
toolbar: [
['bold', 'italic'], 
[{ 'list': 'ordered'}, { 'list': 'bullet' }]
],
},
bounds: '#snow',
height: '500px',
});

</script>
</body>
</html>

