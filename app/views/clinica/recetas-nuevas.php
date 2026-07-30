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
<script>

let pacienteSeleccionado = null;
let timeoutBusqueda = null;

function seleccionarTipo(tipo){
if(tipo == 'registrado'){
document.getElementById('seccionRegistrado').style.display = 'block';
document.getElementById('seccionExterno').style.display = 'none';
document.getElementById('tipoRegistrado').classList.add('active');
document.getElementById('tipoExterno').classList.remove('active');
document.getElementById('btnAgregarRegistrado').style.display = 'inline-block';
document.getElementById('btnAgregarExterno').style.display = 'none';
} else {
document.getElementById('seccionRegistrado').style.display = 'none';
document.getElementById('seccionExterno').style.display = 'block';
document.getElementById('tipoExterno').classList.add('active');
document.getElementById('tipoRegistrado').classList.remove('active');
document.getElementById('btnAgregarRegistrado').style.display = 'none';
document.getElementById('btnAgregarExterno').style.display = 'inline-block';
pacienteSeleccionado = null;
} 
}

function limpiarRojo(){
document.querySelector('#Diagnostico').style.border = "";
document.querySelector('.ql-editor').style.border = "";
document.querySelector('#buscarPaciente').style.border = "";
document.querySelector('#extNombre').style.border = "";
document.querySelector('#extApellidoPaterno').style.border = "";
document.querySelector('#extApellidoMaterno').style.border = "";
document.querySelector('#extEdad').style.border = "";
document.querySelector('#extFechaNacimiento').style.border = "";
document.getElementById('extSexoM').style.outline = "";
document.getElementById('extSexoF').style.outline = "";
}

function limpiarBorde(elemento){
elemento.style.border = "";
elemento.style.outline = "";
}

function buscarPaciente(query){
limpiarBorde(document.getElementById('buscarPaciente'));
if(timeoutBusqueda){
clearTimeout(timeoutBusqueda);
}
if(query.length < 2){
document.getElementById('sugerenciasPaciente').innerHTML = '';
return;
}
timeoutBusqueda = setTimeout(function(){
fetch('/buscar?query=' + encodeURIComponent(query))
.then(response => response.json())
.then(data => {
let html = '';
data.forEach(function(paciente){
let nombreCompleto = paciente.nombre + ' ' + (paciente.apellido_paterno || '') + ' ' + (paciente.apellido_materno || '');
html += '<button type="button" class="list-group-item list-group-item-action" onclick="seleccionarPaciente(' + paciente.id + ', \'' + nombreCompleto.replace(/'/g, "\\'") + '\')">' + nombreCompleto + '</button>';
});
document.getElementById('sugerenciasPaciente').innerHTML = html;
});
}, 300);
}

function seleccionarPaciente(id, nombre){
pacienteSeleccionado = id;
document.getElementById('buscarPaciente').value = nombre;
document.getElementById('sugerenciasPaciente').innerHTML = '';
document.getElementById('infoPaciente').innerHTML =
'<div class="alert alert-info mt-2"><strong>Paciente seleccionado:</strong> ' + nombre;
}

function limpiarFormulario(){
document.getElementById('Diagnostico').value = "";
document.querySelector('.ql-editor').innerHTML = "";
document.getElementById('TA').value = "";
document.getElementById('FC').value = "";
document.getElementById('SpO2').value = "";
document.getElementById('Temperatura').value = "";
document.getElementById('buscarPaciente').value = "";
document.getElementById('sugerenciasPaciente').innerHTML = "";
document.getElementById('infoPaciente').innerHTML = "";
pacienteSeleccionado = null;
document.getElementById('extNombre').value = "";
document.getElementById('extApellidoPaterno').value = "";
document.getElementById('extApellidoMaterno').value = "";
document.getElementById('extEdad').value = "";
document.querySelectorAll('input[name="extSexo"]').forEach(function(r){ r.checked = false; });
document.getElementById('extFechaNacimiento').value = "";
limpiarRojo();
}

function agregarRecetaRegistrado(){

if(!pacienteSeleccionado){
document.getElementById('buscarPaciente').style.border = "2px solid #d44e31";
Swal.fire({
title: 'Validación',
text: 'Debe seleccionar un paciente válido para continuar.',
icon: 'warning',
showConfirmButton: false,
timer: 2500
});
return;
}

const Diagnostico = document.getElementById('Diagnostico').value;
const contenidoReceta = document.querySelector('.ql-editor').innerHTML;
const TA = document.getElementById('TA').value;
const FC = document.getElementById('FC').value;
const SpO2 = document.getElementById('SpO2').value;
const Temperatura = document.getElementById('Temperatura').value;

document.querySelector('#Diagnostico').style.border = "";
document.querySelector('.ql-editor').style.border = "";

if(Diagnostico == ""){
document.querySelector('#Diagnostico').style.border = "2px solid #d44e31";
Swal.fire({
title: 'Validación',
text: 'El diagnóstico es obligatorio.',
icon: 'warning',
showConfirmButton: false,
timer: 2000
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
timer: 2000
});
return;
}

const parametros = {
idPaciente : pacienteSeleccionado,
diagnostico : Diagnostico,
medicamento : contenidoReceta,
referencia : 0,
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
limpiarFormulario();
Swal.fire({
title: 'Receta agregada',
text: 'La receta se agregó correctamente.',
icon: 'success',
showConfirmButton: false,
timer: 1500
});
setTimeout(function() {
window.location.href = '/clinica/receta/' + idReceta;
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

function validarCamposExterno(){
const campos = [
{ id: 'extNombre', label: 'Nombre' },
{ id: 'extApellidoPaterno', label: 'Apellido Paterno' },
{ id: 'extApellidoMaterno', label: 'Apellido Materno' },
{ id: 'extEdad', label: 'Edad' },
{ id: 'extFechaNacimiento', label: 'Fecha de Nacimiento' }
];

let todosValidos = true;

campos.forEach(function(campo){
const el = document.getElementById(campo.id);
if(el.value.trim() == ""){
el.style.border = "2px solid #d44e31";
todosValidos = false;
} else {
el.style.border = "";
}
});

const sexoSeleccionado = document.querySelector('input[name="extSexo"]:checked');
if(!sexoSeleccionado){
document.getElementById('extSexoM').style.outline = "2px solid #d44e31";
document.getElementById('extSexoF').style.outline = "2px solid #d44e31";
todosValidos = false;
} else {
document.getElementById('extSexoM').style.outline = "";
document.getElementById('extSexoF').style.outline = "";
}

return todosValidos;
}

function agregarRecetaExterno(){

if(!validarCamposExterno()){
Swal.fire({
title: 'Validación',
text: 'Faltan datos obligatorios del paciente externo.',
icon: 'warning',
showConfirmButton: false,
timer: 2500
});
return;
}

const nombre = document.getElementById('extNombre').value;
const apellidoPaterno = document.getElementById('extApellidoPaterno').value;
const Diagnostico = document.getElementById('Diagnostico').value;
const contenidoReceta = document.querySelector('.ql-editor').innerHTML;

if(Diagnostico == ""){
document.querySelector('#Diagnostico').style.border = "2px solid #d44e31";
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

document.querySelector('#Diagnostico').style.border = "";
document.querySelector('.ql-editor').style.border = "";

const parametros = {
nombre: nombre,
apellido_paterno: apellidoPaterno,
apellido_materno: document.getElementById('extApellidoMaterno').value,
edad: document.getElementById('extEdad').value,
sexo: document.querySelector('input[name="extSexo"]:checked').value,
fecha_nacimiento: document.getElementById('extFechaNacimiento').value,
diagnostico: Diagnostico,
medicamento: contenidoReceta,
ta: document.getElementById('TA').value,
fc: document.getElementById('FC').value,
spo2: document.getElementById('SpO2').value,
temperatura: document.getElementById('Temperatura').value,
};

fetch('/clinica/paciente/insert-receta-externo', {
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
limpiarFormulario();
Swal.fire({
title: 'Receta agregada',
text: 'La receta se agregó correctamente.',
icon: 'success',
showConfirmButton: false,
timer: 1500
});
setTimeout(function() {
window.location.href = '/clinica/receta-externo/' + idReceta;
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

</head>
<body>
<div class="LoaderPage"></div>
<div id="app">

<?=$data['sidebar'];?>

<div id="main">

<!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

<div class="main-content container-fluid">

<!--
<button id="toggleButton" class="btn icon btn-light text-dark float-end" onclick="toggleSize()">
<i id="toggleIcon" data-feather="columns"></i>
</button>
-->

<div class="page-title">
<h3><?=$data['title'];?></h3>
</div>

<section>
<div class="row mt-3">



<div class="col-12 resizable">

<!----- SELECTOR TIPO PACIENTE ----->
<div class="card">
<div class="card-header text-end">
<div class="btn-group" role="group">
<button type="button" class="btn btn-outline-success active" id="tipoRegistrado" onclick="seleccionarTipo('registrado')">Paciente</button>
<button type="button" class="btn btn-outline-success" id="tipoExterno" onclick="seleccionarTipo('externo')">Paciente Externo</button>
</div>
</div>

<div class="card-body">
<div class="row">

<!---------- PACIENTES EXISTENTES ---------->
<div class="col-12" id="seccionRegistrado">
<h4 class="card-title">Nombre del Paciente</h4>

<div class="mb-3">
<input type="text" class="form-control fs-5" id="buscarPaciente" placeholder="Busca el nombre del paciente..." onkeyup="buscarPaciente(this.value)" oninput="limpiarBorde(this)" autocomplete="off">
<div id="sugerenciasPaciente" class="list-group mt-1"></div>
<div class="mt-3" id="infoPaciente"></div>
</div>

</div>

<!---------- PACIENTES EXTERNOS ---------->
<div class="col-12" id="seccionExterno" style="display:none;">
<h4 class="card-title">Datos del Paciente Externo</h4>

<div class="row">
<div class="col-md-4 mb-3">
<label class="text-primary mb-1"><small>Nombre:</small></label>
<input type="text" class="form-control fs-5" id="extNombre" oninput="limpiarBorde(this)">
</div>
<div class="col-md-4 mb-3">
<label class="text-primary mb-1"><small>Apellido Paterno:</small></label>
<input type="text" class="form-control fs-5" id="extApellidoPaterno" oninput="limpiarBorde(this)">
</div>
<div class="col-md-4 mb-3">
<label class="text-primary mb-1"><small>Apellido Materno:</small></label>
<input type="text" class="form-control fs-5" id="extApellidoMaterno" oninput="limpiarBorde(this)">
</div>
</div>

<div class="row">
<div class="col-md-4 mb-3">
<label class="text-primary mb-1"><small>Edad:</small></label>
<input type="number" class="form-control fs-5" id="extEdad" oninput="limpiarBorde(this)">
</div>
<div class="col-md-4 mb-3">
<label class="text-primary mb-1"><small>Sexo:</small></label>
<div class="mt-2">
<div class="form-check form-check-inline">
<input class="form-check-input" type="radio" name="extSexo" id="extSexoM" value="M" onclick="limpiarBorde(document.getElementById('extSexoM'));limpiarBorde(document.getElementById('extSexoF'))">
<label class="form-check-label fs-5" for="extSexoM">Masculino</label>
</div>
<div class="form-check form-check-inline">
<input class="form-check-input" type="radio" name="extSexo" id="extSexoF" value="F" onclick="limpiarBorde(document.getElementById('extSexoM'));limpiarBorde(document.getElementById('extSexoF'))">
<label class="form-check-label fs-5" for="extSexoF">Femenino</label>
</div>
</div>
</div>
<div class="col-md-4 mb-3">
<label class="text-primary mb-1"><small>Fecha de Nacimiento:</small></label>
<input type="date" class="form-control fs-5" id="extFechaNacimiento" oninput="limpiarBorde(this)">
</div>
</div>

</div>

</div>
</div>
</div>





<!----- FORMULARIO RECETA ----->
<div class="card">
<div class="card-header text-primary">
<h4 class="card-title">Nueva Receta</h4>
</div>
<div class="card-body">

<div class="mb-3">
<label class="text-primary mb-1"><small>Diagnostico:</small></label>
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

<label class="text-primary mt-3 mb-1"><small>Medicamento:</small></label>
<div id="snow" class="editor"></div>

<div class="text-end mt-3">
<button class="btn btn-success" id="btnAgregarRegistrado" onclick="agregarRecetaRegistrado()">Agregar Receta <i data-feather="chevron-right"></i></button>
<button class="btn btn-success" id="btnAgregarExterno" onclick="agregarRecetaExterno()" style="display:none;">Agregar Receta <i data-feather="chevron-right"></i></button>
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
