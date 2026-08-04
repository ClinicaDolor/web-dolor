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
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/quill/quill.snow.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
transition: all 0.3s ease-in-out;
}
.swal2-confirm {
background-color: #501E75 !important;
color: #fff !important;
}
.swal2-cancel {
background-color: #6c757d !important;
color: #fff !important;
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

<div class="page-title">
<h3><?=$data['title'];?></h3>
</div>

<section class="mt-3">

<div class="row">
<div class="col-12">

<div class="card">
<div class="card-header">
<h4 class="card-title">Datos del Paciente Externo</h4>
</div>
<div class="card-body">

<div class="row">
<div class="col-md-4 mb-3">
<label class="text-primary mb-1"><small>Nombre:</small></label>
<input type="text" class="form-control fs-5" id="extNombre" value="<?=htmlspecialchars($data['nombre'] ?? '')?>" oninput="limpiarBorde(this)">
</div>
<div class="col-md-4 mb-3">
<label class="text-primary mb-1"><small>Apellido Paterno:</small></label>
<input type="text" class="form-control fs-5" id="extApellidoPaterno" value="<?=htmlspecialchars($data['apellido_paterno'] ?? '')?>" oninput="limpiarBorde(this)">
</div>
<div class="col-md-4 mb-3">
<label class="text-primary mb-1"><small>Apellido Materno:</small></label>
<input type="text" class="form-control fs-5" id="extApellidoMaterno" value="<?=htmlspecialchars($data['apellido_materno'] ?? '')?>" oninput="limpiarBorde(this)">
</div>
</div>

<div class="row">
<div class="col-md-4 mb-3">
<label class="text-primary mb-1"><small>Edad:</small></label>
<input type="number" class="form-control fs-5" id="extEdad" value="<?=htmlspecialchars($data['edad'] ?? '')?>" oninput="limpiarBorde(this)">
</div>
<div class="col-md-4 mb-3">
<label class="text-primary mb-1"><small>Sexo:</small></label>
<div class="mt-2">
<div class="form-check form-check-inline">
<input class="form-check-input" type="radio" name="extSexo" id="extSexoM" value="M" <?=($data['sexo'] ?? '') == 'M' ? 'checked' : ''?> onclick="limpiarBorde(document.getElementById('extSexoM'));limpiarBorde(document.getElementById('extSexoF'))">
<label class="form-check-label fs-5" for="extSexoM">Masculino</label>
</div>
<div class="form-check form-check-inline">
<input class="form-check-input" type="radio" name="extSexo" id="extSexoF" value="F" <?=($data['sexo'] ?? '') == 'F' ? 'checked' : ''?> onclick="limpiarBorde(document.getElementById('extSexoM'));limpiarBorde(document.getElementById('extSexoF'))">
<label class="form-check-label fs-5" for="extSexoF">Femenino</label>
</div>
</div>
</div>
<div class="col-md-4 mb-3">
<label class="text-primary mb-1"><small>Fecha de Nacimiento:</small></label>
<input type="date" class="form-control fs-5" id="extFechaNacimiento" value="<?=htmlspecialchars($data['fecha_nacimiento'] ?? '')?>" oninput="limpiarBorde(this)">
</div>
</div>

</div>
</div>

</div>
</div>

<div class="row">
<div class="col-12">

<div class="card">
<div class="card-header text-primary">
<h4 class="card-title">Detalle de la Receta</h4>
</div>
<div class="card-body">

<div class="mb-3">
<small class="text-primary">Fecha de la receta: </small>
<label class="fs-5"><?=$data['fecha_receta'];?> <?=$data['hora_receta'];?></label>
</div>

<div class="mb-3">
<label class="text-primary mb-1"><small>Diagnostico:</small></label>
<textarea class="form-control fs-5" id="Diagnostico" rows="2" oninput="limpiarBorde(this)"><?=htmlspecialchars($data['diagnostico'] ?? '')?></textarea>
</div>

<div class="row">
<div class="col-md-6 mb-3">
<label class="text-primary mb-1"><small>Tensión Arterial (TA):</small></label>
<input type="text" class="form-control fs-5" id="TA" value="<?=htmlspecialchars($data['ta'] ?? '')?>">
</div>

<div class="col-md-6 mb-3">
<label class="text-primary mb-1"><small>Frecuencia Cardíaca (FC):</small></label>
<input type="number" class="form-control fs-5" id="FC" value="<?=htmlspecialchars($data['fc'] ?? '')?>">
</div>

<div class="col-md-6 mb-3">
<label class="text-primary mb-1"><small>Saturación de Oxígeno (SpO₂):</small></label>
<input type="number" class="form-control fs-5" id="SpO2" value="<?=htmlspecialchars($data['spo2'] ?? '')?>">
</div>

<div class="col-md-6 mb-3">
<label class="text-primary mb-1"><small>Temperatura:</small></label>
<div class="input-group">
<input type="number" class="form-control fs-5" id="Temperatura" step="0.1" value="<?=htmlspecialchars($data['temperatura'] ?? '')?>">
<span class="input-group-text fs-5">°C</span>
</div>
</div>
</div>

<label class="text-primary mt-3 mb-1"><small>Medicamento:</small></label>
<div id="snow" class="editor"></div>

<div class="text-end mt-3">
<button class="btn btn-success" onclick="GuardarRecetaExterno()">Guardar Cambios</button>
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
<script src="<?=RUTA_PUBLIC;?>libs/quill/quill.min.js"></script>
<script src="<?=RUTA_JS?>search-main.js"></script>

<script>

function limpiarBorde(elemento){
elemento.style.border = "";
elemento.style.outline = "";
}

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

snow.clipboard.dangerouslyPasteHTML(<?=json_encode($data['medicamento'] ?? '')?>);

function GuardarRecetaExterno(){

const nombre = document.getElementById('extNombre').value;
const apellidoPaterno = document.getElementById('extApellidoPaterno').value;
const edad = document.getElementById('extEdad').value;
const sexo = document.querySelector('input[name="extSexo"]:checked');
const Diagnostico = document.getElementById('Diagnostico').value;
const contenidoReceta = document.querySelector('.ql-editor').innerHTML;

if(nombre == ""){
document.getElementById('extNombre').style.border = "2px solid #d44e31";
Swal.fire({
title: 'Validación',
text: 'El nombre es obligatorio.',
icon: 'warning',
showConfirmButton: false,
timer: 2500
});
return;
}

if(!sexo){
document.getElementById('extSexoM').style.outline = "2px solid #d44e31";
document.getElementById('extSexoF').style.outline = "2px solid #d44e31";
Swal.fire({
title: 'Validación',
text: 'Seleccione el sexo del paciente.',
icon: 'warning',
showConfirmButton: false,
timer: 2500
});
return;
}

if(Diagnostico == ""){
document.getElementById('Diagnostico').style.border = "2px solid #d44e31";
Swal.fire({
title: 'Validación',
text: 'El diagnóstico es obligatorio.',
icon: 'warning',
showConfirmButton: false,
timer: 2500
});
return;
}

if(contenidoReceta == '<p><br></p>'){
document.querySelector('.ql-editor').style.border = "2px solid #d44e31";
Swal.fire({
title: 'Validación',
text: 'Agregue al menos un medicamento.',
icon: 'warning',
showConfirmButton: false,
timer: 2500
});
return;
}

var parametros = {
id: <?=$data['id'];?>,
nombre: nombre,
apellido_paterno: apellidoPaterno,
apellido_materno: document.getElementById('extApellidoMaterno').value,
edad: edad,
sexo: sexo.value,
fecha_nacimiento: document.getElementById('extFechaNacimiento').value,
diagnostico: Diagnostico,
medicamento: contenidoReceta,
ta: document.getElementById('TA').value,
fc: document.getElementById('FC').value,
spo2: document.getElementById('SpO2').value,
temperatura: document.getElementById('Temperatura').value
};

fetch('/clinica/paciente/edit-receta-externo', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify(parametros)
})
.then(response => response.json())
.then(data => {

if (data.resultado) {

Swal.fire({
title: 'Registro editado',
text: 'El registro se editó correctamente.',
icon: 'success',
showConfirmButton: false,
timer: 1500
});

setTimeout(function() {
window.location.href = '/clinica/receta-externo/<?=$data['id'];?>';
}, 1500);

} else {
Swal.fire({
title: 'Error',
text: data.mensaje,
icon: 'error',
showConfirmButton: false,
timer: 2000
});
}

})
.catch(function(){
Swal.fire({
title: 'Error',
text: 'Error de conexión con el servidor.',
icon: 'error',
showConfirmButton: false,
timer: 2000
});
});

}

</script>
</body>
</html>