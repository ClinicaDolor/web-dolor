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
</head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-external-recipe-edit-page">
<div class="LoaderPage"></div>
<div id="app">

<?=$data['sidebar'];?>

<div id="main">

<!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

<main class="main-content container-fluid doctor-external-recipe-edit-content" id="main-content">
<header class="doctor-page-hero doctor-external-recipe-edit-hero">
    <div class="doctor-page-hero-copy">
        <p class="dashboard-eyebrow">RECETA EXTERNA</p>
        <h1>Editar receta</h1>
        <p>Actualiza los datos registrados y conserva la información clínica existente.</p>
    </div>
</header>

<section class="doctor-external-recipe-edit-section" aria-label="Edición de receta externa">

<div class="doctor-external-recipe-edit-card doctor-external-recipe-edit-patient-card">
<div class="card-header doctor-external-recipe-edit-card-header">
<div>
<p class="dashboard-eyebrow">IDENTIFICACIÓN</p>
<h2 class="doctor-external-recipe-edit-card-title">Datos del paciente externo</h2>
</div>
</div>
<div class="card-body doctor-external-recipe-edit-card-body">

<div class="row">
<div class="col-md-4 mb-3">
<label class="doctor-external-recipe-edit-label" for="extNombre">Nombre</label>
<input type="text" class="form-control doctor-external-recipe-edit-control" id="extNombre" value="<?=htmlspecialchars($data['nombre'] ?? '')?>" oninput="limpiarBorde(this)">
</div>
<div class="col-md-4 mb-3">
<label class="doctor-external-recipe-edit-label" for="extApellidoPaterno">Apellido paterno</label>
<input type="text" class="form-control doctor-external-recipe-edit-control" id="extApellidoPaterno" value="<?=htmlspecialchars($data['apellido_paterno'] ?? '')?>" oninput="limpiarBorde(this)">
</div>
<div class="col-md-4 mb-3">
<label class="doctor-external-recipe-edit-label" for="extApellidoMaterno">Apellido materno</label>
<input type="text" class="form-control doctor-external-recipe-edit-control" id="extApellidoMaterno" value="<?=htmlspecialchars($data['apellido_materno'] ?? '')?>" oninput="limpiarBorde(this)">
</div>
</div>

<div class="row">
<div class="col-md-4 mb-3">
<label class="doctor-external-recipe-edit-label" for="extEdad">Edad</label>
<input type="number" class="form-control doctor-external-recipe-edit-control" id="extEdad" value="<?=htmlspecialchars($data['edad'] ?? '')?>" oninput="limpiarBorde(this)">
</div>
<div class="col-md-4 mb-3">
<span class="doctor-external-recipe-edit-label">Sexo</span>
<div class="doctor-external-recipe-edit-radio-group">
<div class="form-check form-check-inline">
<input class="form-check-input" type="radio" name="extSexo" id="extSexoM" value="M" <?=($data['sexo'] ?? '') == 'M' ? 'checked' : ''?> onclick="limpiarBorde(document.getElementById('extSexoM'));limpiarBorde(document.getElementById('extSexoF'))">
<label class="form-check-label doctor-external-recipe-edit-radio-label" for="extSexoM">Masculino</label>
</div>
<div class="form-check form-check-inline">
<input class="form-check-input" type="radio" name="extSexo" id="extSexoF" value="F" <?=($data['sexo'] ?? '') == 'F' ? 'checked' : ''?> onclick="limpiarBorde(document.getElementById('extSexoM'));limpiarBorde(document.getElementById('extSexoF'))">
<label class="form-check-label doctor-external-recipe-edit-radio-label" for="extSexoF">Femenino</label>
</div>
</div>
</div>
<div class="col-md-4 mb-3">
<label class="doctor-external-recipe-edit-label" for="extFechaNacimiento">Fecha de nacimiento</label>
<input type="date" class="form-control doctor-external-recipe-edit-control" id="extFechaNacimiento" value="<?=htmlspecialchars($data['fecha_nacimiento'] ?? '')?>" oninput="limpiarBorde(this)">
</div>
</div>

</div>
</div>
<div class="doctor-external-recipe-edit-card doctor-external-recipe-edit-detail-card">
<div class="card-header doctor-external-recipe-edit-card-header">
<div>
<p class="dashboard-eyebrow">INDICACIONES</p>
<h2 class="doctor-external-recipe-edit-card-title">Detalle de la receta</h2>
</div>
</div>
<div class="card-body doctor-external-recipe-edit-card-body">

<div class="doctor-external-recipe-edit-meta">
<span class="doctor-external-recipe-edit-label">Fecha de la receta</span>
<span class="doctor-external-recipe-edit-value"><?=$data['fecha_receta'];?> <?=$data['hora_receta'];?></span>
</div>

<div class="mb-3">
<label class="doctor-external-recipe-edit-label" for="Diagnostico">Diagnóstico</label>
<textarea class="form-control doctor-external-recipe-edit-control" id="Diagnostico" rows="2" oninput="limpiarBorde(this)"><?=htmlspecialchars($data['diagnostico'] ?? '')?></textarea>
</div>

<div class="row doctor-external-recipe-edit-vitals-grid">
<div class="col-md-6 mb-3">
<label class="doctor-external-recipe-edit-label" for="TA">Tensión arterial (TA)</label>
<input type="text" class="form-control doctor-external-recipe-edit-control" id="TA" value="<?=htmlspecialchars($data['ta'] ?? '')?>">
</div>

<div class="col-md-6 mb-3">
<label class="doctor-external-recipe-edit-label" for="FC">Frecuencia cardíaca (FC)</label>
<input type="number" class="form-control doctor-external-recipe-edit-control" id="FC" value="<?=htmlspecialchars($data['fc'] ?? '')?>">
</div>

<div class="col-md-6 mb-3">
<label class="doctor-external-recipe-edit-label" for="SpO2">Saturación de oxígeno (SpO₂)</label>
<input type="number" class="form-control doctor-external-recipe-edit-control" id="SpO2" value="<?=htmlspecialchars($data['spo2'] ?? '')?>">
</div>

<div class="col-md-6 mb-3">
<label class="doctor-external-recipe-edit-label" for="Temperatura">Temperatura</label>
<div class="input-group">
<input type="number" class="form-control doctor-external-recipe-edit-control" id="Temperatura" step="0.1" value="<?=htmlspecialchars($data['temperatura'] ?? '')?>">
<span class="input-group-text doctor-external-recipe-edit-unit">°C</span>
</div>
</div>
</div>

<label class="doctor-external-recipe-edit-label doctor-external-recipe-edit-editor-label" for="snow">Medicamento</label>
<div id="snow" class="editor"></div>

<div class="doctor-external-recipe-edit-actions">
<button type="button" class="btn doctor-external-recipe-edit-submit" onclick="GuardarRecetaExterno()">Guardar cambios <i data-feather="check" aria-hidden="true"></i></button>
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
