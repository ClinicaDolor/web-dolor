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

function calcularEdad() {
    const fechaNacimiento = document.getElementById("extFechaNacimiento").value;

    if (!fechaNacimiento) {
        document.getElementById("extEdad").value = "";
        return;
    }

    const hoy = new Date();
    const nacimiento = new Date(fechaNacimiento);

    let edad = hoy.getFullYear() - nacimiento.getFullYear();

    const mes = hoy.getMonth() - nacimiento.getMonth();

    if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) {
        edad--;
    }

    document.getElementById("extEdad").value = edad;
}
</script>

</head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-new-recipe-page">
<div class="LoaderPage"></div>
<div id="app">

<?=$data['sidebar'];?>

<div id="main">

<!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

<main class="main-content container-fluid doctor-new-recipe-content" id="main-content">
<header class="doctor-page-hero doctor-new-recipe-hero">
    <div class="doctor-page-hero-copy">
        <p class="dashboard-eyebrow">RECETAS MÉDICAS</p>
        <h1>Nueva receta</h1>
        <p>Registra la indicación médica para un paciente existente o externo.</p>
    </div>
    <div class="doctor-page-hero-actions">
        <a href="<?=SERVIDOR?>clinica/recetas" class="btn doctor-page-hero-action" data-doctor-back-action>
            <i data-feather="arrow-left" aria-hidden="true"></i>
            <span>Volver a recetas</span>
        </a>
    </div>
</header>
 
<section class="doctor-new-recipe-section" aria-label="Registro de receta">
<div class="row mt-3">



<div class="col-12">

<!----- SELECTOR TIPO PACIENTE ----->
<div class="card doctor-new-recipe-card doctor-new-recipe-selector-card">
<div class="card-header doctor-new-recipe-card-header">
<div>
<p class="dashboard-eyebrow">DESTINATARIO</p>
<h2 class="doctor-new-recipe-card-title">Paciente de la receta</h2>
</div>
<div class="btn-group doctor-new-recipe-type-switch" role="group" aria-label="Tipo de paciente">
<button type="button" class="btn doctor-new-recipe-type-button active" id="tipoRegistrado" onclick="seleccionarTipo('registrado')">Paciente</button>
<button type="button" class="btn doctor-new-recipe-type-button" id="tipoExterno" onclick="seleccionarTipo('externo')">Paciente Externo</button>
</div>
</div>

<div class="card-body doctor-new-recipe-card-body">
<div class="row">

<!---------- PACIENTES EXISTENTES ---------->
<div class="col-12" id="seccionRegistrado">
<h3 class="doctor-new-recipe-subtitle">Nombre del paciente</h3>

<div class="mb-3">
<label class="doctor-new-recipe-label" for="buscarPaciente">Buscar paciente registrado</label>
<input type="text" class="form-control doctor-new-recipe-control" id="buscarPaciente" placeholder="Busca el nombre del paciente..." onkeyup="buscarPaciente(this.value)" oninput="limpiarBorde(this)" autocomplete="off">
<div id="sugerenciasPaciente" class="list-group mt-1"></div>
<div class="mt-3" id="infoPaciente"></div>
</div>

</div>

<!---------- PACIENTES EXTERNOS ---------->
<div class="col-12" id="seccionExterno" style="display:none;">
<h3 class="doctor-new-recipe-subtitle">Datos del paciente externo</h3>

<div class="row">
<div class="col-md-4 mb-3">
<label class="doctor-new-recipe-label" for="extNombre">Nombre</label>
<input type="text" class="form-control doctor-new-recipe-control" id="extNombre" oninput="limpiarBorde(this)">
</div>
<div class="col-md-4 mb-3">
<label class="doctor-new-recipe-label" for="extApellidoPaterno">Apellido paterno</label>
<input type="text" class="form-control doctor-new-recipe-control" id="extApellidoPaterno" oninput="limpiarBorde(this)">
</div>
<div class="col-md-4 mb-3">
<label class="doctor-new-recipe-label" for="extApellidoMaterno">Apellido materno</label>
<input type="text" class="form-control doctor-new-recipe-control" id="extApellidoMaterno" oninput="limpiarBorde(this)">
</div>
</div>

<div class="row">
<div class="col-md-4 mb-3">
<label class="doctor-new-recipe-label" for="extEdad">Edad</label>
<input type="number" class="form-control doctor-new-recipe-control" id="extEdad" oninput="limpiarBorde(this)">
</div>
<div class="col-md-4 mb-3">
<label class="doctor-new-recipe-label">Sexo</label>
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
<label class="doctor-new-recipe-label" for="extFechaNacimiento">Fecha de nacimiento</label>
<input type="date" class="form-control doctor-new-recipe-control" id="extFechaNacimiento" oninput="calcularEdad(); limpiarBorde(this)">
</div>
</div>

</div>

</div>
</div>
</div>





<!----- FORMULARIO RECETA ----->
<div class="card doctor-new-recipe-card doctor-new-recipe-form-card">
<div class="card-header doctor-new-recipe-card-header">
<div>
<p class="dashboard-eyebrow">INFORMACIÓN CLÍNICA</p>
<h2 class="doctor-new-recipe-card-title">Nueva receta</h2>
</div>
</div>
<div class="card-body doctor-new-recipe-card-body">

<div class="mb-3">
<label class="doctor-new-recipe-label" for="Diagnostico">Diagnóstico</label>
<textarea class="form-control doctor-new-recipe-control" id="Diagnostico" rows="2"></textarea>
</div>

<div class="row">
<div class="col-md-6 mb-3">
<label class="doctor-new-recipe-label" for="TA">Tensión arterial (TA)</label>
<input type="text" class="form-control doctor-new-recipe-control" id="TA">
</div>

<div class="col-md-6 mb-3">
<label class="doctor-new-recipe-label" for="FC">Frecuencia cardíaca (FC)</label>
<input type="number" class="form-control doctor-new-recipe-control" id="FC">
</div>

<div class="col-md-6 mb-3">
<label class="doctor-new-recipe-label" for="SpO2">Saturación de oxígeno (SpO₂)</label>
<input type="number" class="form-control doctor-new-recipe-control" id="SpO2">
</div>

<div class="col-md-6 mb-3">
<label class="doctor-new-recipe-label" for="Temperatura">Temperatura</label>
<div class="input-group">
<input type="number" class="form-control doctor-new-recipe-control" id="Temperatura" step="0.1">
<span class="input-group-text doctor-new-recipe-unit">°C</span>
</div>
</div>
</div>

<label class="doctor-new-recipe-label doctor-new-recipe-editor-label" for="snow">Medicamento</label>
<div id="snow" class="editor"></div>

<div class="text-end mt-3 doctor-new-recipe-actions">
<button type="button" class="btn doctor-new-recipe-submit" id="btnAgregarRegistrado" onclick="agregarRecetaRegistrado()">Agregar receta <i data-feather="chevron-right" aria-hidden="true"></i></button>
<button type="button" class="btn doctor-new-recipe-submit" id="btnAgregarExterno" onclick="agregarRecetaExterno()" style="display:none;">Agregar receta <i data-feather="chevron-right" aria-hidden="true"></i></button>
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
