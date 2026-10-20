<?php 
use App\Config\Database;
use App\Services\ClinicalSummarySecurity;
$bd = Database::getInstance();
$nombrePacienteSeguro = htmlspecialchars((string)($data['nombre_paciente'] ?? ''), ENT_QUOTES, 'UTF-8');
$nombrePacienteVisible = $nombrePacienteSeguro !== '' ? $nombrePacienteSeguro : 'Paciente';
$volverExpedienteUrl = htmlspecialchars(SERVIDOR . 'clinica/paciente/' . (int)$data['idPaciente'], ENT_QUOTES, 'UTF-8');
$recetaCsrfToken = ClinicalSummarySecurity::captureCsrfToken((int)$data['idPaciente']);
$recetaCount = null;
try {
    $stmt = $bd->query("SELECT * FROM receta_medica WHERE id_paciente = '".$data['idPaciente']."' ORDER BY fecha_hora DESC");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $recetaCount = count($registros);
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}
$recetaCountLabel = $recetaCount === 1 ? 'registro' : 'registros';
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
<link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
<title><?=htmlspecialchars((string)$data['title'], ENT_QUOTES, 'UTF-8');?></title>
<link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
<link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/quill/quill.snow.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?=RUTA_JS;?>loader.js"></script>
<script>
let dataTable = null;
let recetaGuardando = false;
let recetaModalTrigger = null;
let recetaDetalleTrigger = null;
let recetaDetalleRequest = 0;
let recetaPrintRequest = 0;
let recetaPrintObjectUrl = null;
let recetaPrintTrigger = null;
let recetaPrintReturnModal = null;
let recetaPrintReturnFocus = null;
let recetaPrintId = null;
let recetaPrintTipo = 'registrado';
let recetaRowsDelegationReady = false;

function actualizarBloqueoScrollRecetas() {
const agregarModal = document.getElementById('agregar-receta-modal');
const detalleModal = document.getElementById('detalle-receta-modal');
const printModal = document.getElementById('modalImprimirReceta');
const modalVisible = (agregarModal && !agregarModal.hidden) || (detalleModal && !detalleModal.hidden) || (printModal && !printModal.hidden);
document.body.classList.toggle('doctor-recipe-modal-open', Boolean(modalVisible));
}

function ocultarAgregarRecetaModal() {
const modal = document.getElementById('agregar-receta-modal');
if (!modal) return;
modal.hidden = true;
modal.setAttribute('aria-hidden', 'true');
actualizarBloqueoScrollRecetas();
if (recetaModalTrigger && document.contains(recetaModalTrigger) && typeof recetaModalTrigger.focus === 'function') {
recetaModalTrigger.focus();
}
recetaModalTrigger = null;
}

function abrirAgregarRecetaModal() {
const modal = document.getElementById('agregar-receta-modal');
const closeButton = document.getElementById('closeAgregarRecetaButton');
const trigger = document.getElementById('openAgregarRecetaButton');
if (!modal) return;

const detalleModal = document.getElementById('detalle-receta-modal');
if (detalleModal && !detalleModal.hidden) cerrarDetalleReceta();

recetaModalTrigger = trigger || document.activeElement;
modal.hidden = false;
modal.setAttribute('aria-hidden', 'false');
actualizarBloqueoScrollRecetas();
if (closeButton) closeButton.focus();
}

function recetaFormularioTieneCambios() {
const diagnostico = document.getElementById('Diagnostico');
const editor = document.querySelector('#snow .ql-editor');
const campos = ['TA', 'FC', 'SpO2', 'Temperatura'];
return Boolean(
(diagnostico && diagnostico.value.trim()) ||
(editor && editor.innerHTML !== '<p><br></p>') ||
campos.some(id => {
const campo = document.getElementById(id);
return campo && String(campo.value || '').trim() !== '';
})
);
}

function cerrarAgregarRecetaModal() {
const modal = document.getElementById('agregar-receta-modal');
if (!modal || modal.hidden || recetaGuardando) return false;
if (recetaFormularioTieneCambios() && !window.confirm('Hay información sin guardar. ¿Deseas cerrar el formulario?')) return false;
ocultarAgregarRecetaModal();
return true;
}

function cerrarDetalleReceta() {
const modal = document.getElementById('detalle-receta-modal');
if (!modal || modal.hidden) return false;

recetaDetalleRequest += 1;
modal.hidden = true;
modal.setAttribute('aria-hidden', 'true');
const resultsContainer = document.getElementById('detalleRecetaModalBody');
const status = document.getElementById('detalleRecetaModalStatus');
if (resultsContainer) {
resultsContainer.textContent = '';
resultsContainer.removeAttribute('aria-busy');
}
if (status) status.textContent = '';
actualizarBloqueoScrollRecetas();

const trigger = recetaDetalleTrigger;
recetaDetalleTrigger = null;
if (trigger && document.contains(trigger) && typeof trigger.focus === 'function') {
trigger.focus();
} else {
const table = document.getElementById('table1');
if (table && typeof table.focus === 'function') table.focus();
}
return true;
}

function liberarVistaPreviaReceta() {
if (recetaPrintObjectUrl) {
URL.revokeObjectURL(recetaPrintObjectUrl);
recetaPrintObjectUrl = null;
}
}

function cargarVistaPreviaReceta(id, tipo) {
const modal = document.getElementById('modalImprimirReceta');
const frame = document.getElementById('recetaPreviewFrame');
const status = document.getElementById('recetaPreviewStatus');
const printButton = document.getElementById('printRecipePreviewButton');
if (!modal || modal.hidden || !frame || !status || !printButton) return;

const requestId = ++recetaPrintRequest;
const conFormato = document.querySelector('input[name="formatoReceta"]:checked')?.value || '0';
liberarVistaPreviaReceta();
frame.hidden = true;
frame.removeAttribute('src');
frame.setAttribute('aria-busy', 'true');
printButton.disabled = true;
status.textContent = 'Cargando vista previa de la receta…';

const endpoint = '/pdf/' + (tipo === 'externo' ? 'receta-externo/' : 'receta/') + encodeURIComponent(id);
const body = new URLSearchParams({con_formato: conFormato});
fetch(endpoint, {
method: 'POST',
headers: {
'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
'Accept': 'application/pdf'
},
body
})
.then(response => {
if (!response.ok) throw new Error('No fue posible cargar el documento de la receta.');
const contentType = (response.headers.get('content-type') || '').toLowerCase();
if (contentType && !contentType.includes('application/pdf')) {
throw new Error('El documento no está disponible para vista previa.');
}
return response.blob();
})
.then(blob => {
if (requestId !== recetaPrintRequest) return;
recetaPrintObjectUrl = URL.createObjectURL(blob);
frame.src = recetaPrintObjectUrl;
frame.hidden = false;
frame.removeAttribute('aria-busy');
printButton.disabled = false;
status.textContent = 'Vista previa lista.';
})
.catch(error => {
if (requestId !== recetaPrintRequest) return;
frame.hidden = true;
frame.removeAttribute('src');
frame.removeAttribute('aria-busy');
printButton.disabled = true;
status.textContent = error.message || 'No fue posible cargar la vista previa.';
});
}

function imprimirReceta(id, tipo) {
const recordId = Number.parseInt(String(id), 10);
const modal = document.getElementById('modalImprimirReceta');
const closeButton = document.getElementById('closeRecipePreviewButton');
const detailModal = document.getElementById('detalle-receta-modal');
if (!modal || !Number.isInteger(recordId) || recordId < 1) return;

if (modal.hidden) {
recetaPrintTrigger = document.activeElement;
recetaPrintReturnModal = null;
recetaPrintReturnFocus = null;
if (detailModal && !detailModal.hidden) {
recetaPrintReturnModal = 'detail';
recetaPrintReturnFocus = document.activeElement;
detailModal.hidden = true;
detailModal.setAttribute('aria-hidden', 'true');
}
}

recetaPrintId = recordId;
recetaPrintTipo = tipo || 'registrado';
modal.hidden = false;
modal.setAttribute('aria-hidden', 'false');
actualizarBloqueoScrollRecetas();
if (closeButton) closeButton.focus();
cargarVistaPreviaReceta(recetaPrintId, recetaPrintTipo);
}

function cerrarVistaPreviaReceta() {
const modal = document.getElementById('modalImprimirReceta');
if (!modal || modal.hidden) return false;
recetaPrintRequest += 1;
liberarVistaPreviaReceta();
const frame = document.getElementById('recetaPreviewFrame');
const status = document.getElementById('recetaPreviewStatus');
const printButton = document.getElementById('printRecipePreviewButton');
if (frame) {
frame.hidden = true;
frame.removeAttribute('src');
frame.removeAttribute('aria-busy');
}
if (status) status.textContent = '';
if (printButton) printButton.disabled = true;
modal.hidden = true;
modal.setAttribute('aria-hidden', 'true');

const detailModal = document.getElementById('detalle-receta-modal');
const returnFocus = recetaPrintReturnFocus || recetaPrintTrigger;
if (recetaPrintReturnModal === 'detail' && detailModal) {
detailModal.hidden = false;
detailModal.setAttribute('aria-hidden', 'false');
}
recetaPrintReturnModal = null;
recetaPrintReturnFocus = null;
recetaPrintTrigger = null;
actualizarBloqueoScrollRecetas();
if (returnFocus && document.contains(returnFocus) && typeof returnFocus.focus === 'function') {
returnFocus.focus();
} else if (detailModal && !detailModal.hidden) {
const closeButton = document.getElementById('closeDetalleRecetaButton');
if (closeButton) closeButton.focus();
}
return true;
}

function generarPDF() {
const frame = document.getElementById('recetaPreviewFrame');
const status = document.getElementById('recetaPreviewStatus');
if (!frame || frame.hidden || !frame.src) {
if (status) status.textContent = 'La vista previa todavía no está disponible.';
return;
}

try {
const previewWindow = window.open(frame.src, '_blank', 'noopener,noreferrer');
if (previewWindow) {
previewWindow.addEventListener('load', () => previewWindow.print(), {once: true});
return;
}
} catch (error) {
// Se utiliza el visor embebido como fallback si el navegador bloquea la ventana.
}

try {
if (frame.contentWindow && typeof frame.contentWindow.print === 'function') {
frame.contentWindow.focus();
frame.contentWindow.print();
} else if (status) {
status.textContent = 'El navegador no permite iniciar la impresión desde esta vista previa.';
}
} catch (error) {
if (status) status.textContent = 'El navegador no permite iniciar la impresión desde esta vista previa.';
}
}

document.addEventListener('keydown', function (event) {
const agregarModal = document.getElementById('agregar-receta-modal');
const detalleModal = document.getElementById('detalle-receta-modal');
const printModal = document.getElementById('modalImprimirReceta');
const modalActivo = printModal && !printModal.hidden
? printModal
: (detalleModal && !detalleModal.hidden
? detalleModal
: (agregarModal && !agregarModal.hidden ? agregarModal : null));
if (!modalActivo) return;

if (event.key === 'Escape') {
event.preventDefault();
if (modalActivo === printModal) cerrarVistaPreviaReceta();
else if (modalActivo === detalleModal) cerrarDetalleReceta();
else cerrarAgregarRecetaModal();
return;
}

if (event.key !== 'Tab') return;
const enfocables = Array.from(modalActivo.querySelectorAll(
'button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [contenteditable="true"], [tabindex]:not([tabindex="-1"])'
));
if (!enfocables.length) return;
const primero = enfocables[0];
const ultimo = enfocables[enfocables.length - 1];
if (event.shiftKey && document.activeElement === primero) {
event.preventDefault();
ultimo.focus();
} else if (!event.shiftKey && document.activeElement === ultimo) {
event.preventDefault();
primero.focus();
}
});

function setRecetaSavingState(isSaving) {
const button = document.getElementById('agregarRecetaButton');
const label = button ? button.querySelector('.doctor-recipe-submit-label') : null;
recetaGuardando = isSaving;
if (!button) return;
button.disabled = isSaving;
button.setAttribute('aria-busy', isSaving ? 'true' : 'false');
if (label) label.textContent = isSaving ? 'Guardando…' : 'Guardar receta';
}

function syncRecipeCountFromTable() {
const container = document.getElementById('conteRecetas');
const countValue = document.getElementById('recipeCountValue');
const countLabel = document.getElementById('recipeCountLabel');
if (!container || !countValue || !countLabel) return;

const rows = container.querySelectorAll('tbody tr');
const total = rows.length;
countValue.textContent = total;
countLabel.textContent = total === 1 ? 'registro' : 'registros';
}

function syncRecipeEmptyState() {
const container = document.getElementById('conteRecetas');
const emptyState = document.getElementById('recetasEmptyState');
if (!container || !emptyState) return;

const rows = container.querySelectorAll('tbody tr');
const table = container.querySelector('table');
const isEmpty = rows.length === 0;
emptyState.hidden = !isEmpty;
if (table) table.hidden = isEmpty;
syncRecipeCountFromTable();
}

function enhanceRecipeRows() {
const container = document.getElementById('conteRecetas');
if (!container) return;

container.querySelectorAll('tbody tr').forEach((row) => {
const inlineHandler = row.getAttribute('onclick') || '';
const inlineId = inlineHandler.match(/DetalleReceta\s*\(\s*(\d+)/);
const rowId = row.dataset.recetaId || (row.id.match(/^receta(\d+)$/) || [])[1];
const recetaId = rowId || (inlineId ? inlineId[1] : '');
if (!recetaId) return;
row.dataset.recetaId = recetaId;
row.removeAttribute('onclick');
row.setAttribute('role', 'button');
row.setAttribute('tabindex', '0');
row.setAttribute('aria-label', 'Abrir detalle de receta');
row.setAttribute('aria-haspopup', 'dialog');
row.setAttribute('aria-controls', 'detalle-receta-modal');
});

if (recetaRowsDelegationReady) return;
container.addEventListener('click', (event) => {
const elemento = event.target instanceof Element ? event.target : null;
const row = elemento ? elemento.closest('tbody tr[data-receta-id]') : null;
if (!row || !container.contains(row)) return;
if (elemento.closest('a, button, input, select, textarea')) return;
DetalleReceta(row.dataset.recetaId, row);
});
container.addEventListener('keydown', (event) => {
if (event.key !== 'Enter' && event.key !== ' ') return;
const elemento = event.target instanceof Element ? event.target : null;
const row = elemento ? elemento.closest('tbody tr[data-receta-id]') : null;
if (!row || !container.contains(row)) return;
event.preventDefault();
DetalleReceta(row.dataset.recetaId, row);
});
recetaRowsDelegationReady = true;
}

function initializeRecipeTable() {
syncRecipeEmptyState();
const table = document.querySelector('#conteRecetas table');
if (!table || !table.querySelector('tbody tr')) {
enhanceRecipeRows();
return;
}

dataTable = new simpleDatatables.DataTable(table, {
searchable: true,
fixedHeight: false,
perPageSelect: false,
columns: [
{
select: 1, sort: 'desc'
}
]
});
enhanceRecipeRows();
syncRecipeCountFromTable();
}

function AgregarReceta(idPaciente){

if (recetaGuardando) return;

const referencia = 0;
const Diagnostico = document.getElementById('Diagnostico').value;
const editor = document.querySelector('#snow .ql-editor');
const contenidoReceta = editor ? editor.innerHTML : '';

// Signos vitales
const TA = document.getElementById('TA').value;
const FC = document.getElementById('FC').value;
const SpO2 = document.getElementById('SpO2').value;
const Temperatura = document.getElementById('Temperatura').value;

document.querySelector('#Diagnostico').style.border = "";
if (editor) editor.style.border = "";

if(Diagnostico != ""){
if(editor && contenidoReceta != '<p><br></p>'){

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

const main = document.getElementById('main');
const csrfToken = main ? main.getAttribute('data-receta-csrf') || '' : '';
setRecetaSavingState(true);
document.getElementById('mensaje').textContent = 'Guardando receta…';

fetch('/clinica/paciente/insert-receta', {
method: 'POST',
headers: {
'Content-Type': 'application/json',
'X-M03-CSRF': csrfToken
},
body: JSON.stringify(parametros)
})
.then(async response => {
const data = await response.json();
if (!response.ok) {
throw new Error(data.mensaje || 'No fue posible guardar la receta.');
}
return data;
})
.then(data => {

if (!data.resultado) {
throw new Error(data.mensaje || 'No fue posible guardar la receta.');
}

const idReceta = data.mensaje;

tableRecetas(idPaciente)
ocultarAgregarRecetaModal();
DetalleReceta(idReceta, null, {openModal: false, autoPrint: true});

document.getElementById('Diagnostico').value = "";
editor.innerHTML = "";

})
.catch(error => {
document.getElementById('mensaje').textContent = 'Error: ' + (error.message || 'No fue posible guardar la receta.');
})
.finally(() => {
setRecetaSavingState(false);
});

}else{
if (editor) editor.style.border = "2px solid #d44e31";
}
}else{
document.querySelector('#Diagnostico').style.border = "2px solid #d44e31";
}

}

function DetalleReceta(idReceta, trigger = null, options = {}){
const recordId = Number.parseInt(String(idReceta), 10);
const modal = document.getElementById('detalle-receta-modal');
const closeButton = document.getElementById('closeDetalleRecetaButton');
const resultsContainer = document.getElementById('detalleRecetaModalBody');
const status = document.getElementById('detalleRecetaModalStatus');
const agregarModal = document.getElementById('agregar-receta-modal');
if (!modal || !resultsContainer || !status || !Number.isInteger(recordId) || recordId < 1) return;
if (agregarModal && !agregarModal.hidden && !cerrarAgregarRecetaModal()) return;

const requestId = ++recetaDetalleRequest;
const shouldOpenModal = options.openModal !== false;
recetaDetalleTrigger = trigger || document.activeElement;
resultsContainer.textContent = '';
resultsContainer.setAttribute('aria-busy', 'true');
status.textContent = 'Cargando detalle de la receta…';
if (shouldOpenModal) {
modal.hidden = false;
modal.setAttribute('aria-hidden', 'false');
actualizarBloqueoScrollRecetas();
if (closeButton) closeButton.focus();
}

fetch(`/buscar/receta/${encodeURIComponent(recordId)}`)
.then(response => {
if (!response.ok) {
throw new Error('Error en la respuesta del servidor: ' + response.status);
}
return response.text();
})
.then(data => {
if (requestId !== recetaDetalleRequest) return;
resultsContainer.innerHTML = data;
eliminarSeparadorFechaHora(resultsContainer);
status.textContent = '';
if (window.feather) feather.replace();
if (options.autoPrint) imprimirReceta(recordId, 'registrado');
})
.catch(() => {
if (requestId !== recetaDetalleRequest) return;
resultsContainer.textContent = '';
status.textContent = 'No fue posible cargar el detalle de la receta.';
})
.finally(() => {
if (requestId !== recetaDetalleRequest) return;
resultsContainer.removeAttribute('aria-busy');

});
} 

function eliminarSeparadorFechaHora(container){
const labels = Array.from(container.querySelectorAll('small'));
const fechaLabel = labels.find(label => label.textContent.trim() === 'Fecha:');
const horaLabel = labels.find(label => label.textContent.trim() === 'Hora:');
if (!fechaLabel || !horaLabel || fechaLabel.parentElement !== horaLabel.parentElement) return false;

let node = fechaLabel.nextSibling;
let removed = false;
while (node && node !== horaLabel) {
const nextNode = node.nextSibling;
if (node.nodeType === Node.TEXT_NODE && /^\s*,\s*$/.test(node.nodeValue || '')) {
node.nodeValue = ' ';
removed = true;
}
node = nextNode;
}
return removed;
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
if (dataTable && typeof dataTable.destroy === 'function') {
dataTable.destroy();
}
dataTable = null;
resultsContainer.innerHTML = data;
initializeRecipeTable();
});
}

</script>

</head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-recipes-page">
<div class="LoaderPage"></div>
<div id="app">

<?=$data['sidebar'];?>

<div id="main" data-receta-csrf="<?=htmlspecialchars($recetaCsrfToken, ENT_QUOTES, 'UTF-8');?>">

<!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

<div class="main-content container-fluid">

<header class="doctor-page-hero doctor-recipe-hero">
<div class="doctor-page-hero-copy">
<p class="dashboard-eyebrow">Seguimiento farmacológico</p>
<h1>Recetas</h1>
<p>Consulta y registra recetas médicas para <?=$nombrePacienteVisible;?>.</p>
</div>
<div class="doctor-page-hero-actions">
<a class="btn doctor-page-hero-action" data-doctor-back-action href="<?=$volverExpedienteUrl;?>">
<i data-feather="arrow-left" aria-hidden="true"></i>
<span>Volver al expediente</span>
</a>
</div>
</header>

<section class="doctor-recipe-patient-strip" aria-label="Paciente del expediente">
<span class="doctor-recipe-patient-icon" aria-hidden="true"><i data-feather="user"></i></span>
<div class="doctor-recipe-patient-context">
<p class="dashboard-eyebrow">PACIENTE</p>
<h2><?=$nombrePacienteVisible;?></h2>
<p>Expediente #<?=htmlspecialchars((string)(int)$data['idPaciente'], ENT_QUOTES, 'UTF-8');?></p>
</div>
</section>

<section class="doctor-recipe-workspace" aria-label="Gestión de recetas médicas">
<div class="row doctor-recipe-list-row">
<div class="col-12">

<div class="card doctor-recipe-card doctor-recipe-list-card">
<div class="card-header doctor-recipe-section-header">
<div class="doctor-recipe-card-heading">
<p class="dashboard-eyebrow">REGISTROS DISPONIBLES</p>
<h2 id="recipe-list-title" class="card-title">Recetas médicas</h2>
<p class="doctor-recipe-count" aria-live="polite"><strong id="recipeCountValue"><?=$recetaCount;?></strong> <span id="recipeCountLabel"><?=$recetaCountLabel;?></span></p>
</div>
<button id="openAgregarRecetaButton" type="button" class="btn btn-primary doctor-recipe-new-button" aria-haspopup="dialog" aria-controls="agregar-receta-modal" onclick="abrirAgregarRecetaModal()">
Agregar Receta <i data-feather="plus" aria-hidden="true"></i>
</button>
</div>
<div class="card-body">

<div id="conteRecetas" class="doctor-recipe-table-content">
<?php if ($recetaCount > 0): ?>
<table class="table table-striped table-hover table-sm pb-0 mb-0" id="table1">
<thead>
<tr>
<th class="text-center" scope="col">#</th>
<th scope="col">Fecha y Hora</th>
</tr>
</thead>
<tbody>
<?php foreach ($registros as $registro): ?>
<tr id="receta<?=$registro['id']?>" data-receta-id="<?=$registro['id'];?>">
<td class="text-center"><?=$registro['id']?></td>
<td><?=(new DateTime(datetime: $registro['fecha_hora']))->format('d/m/Y h:i a');?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
<div id="recetasEmptyState" class="doctor-recipe-empty-state" <?=($recetaCount > 0) ? 'hidden' : '';?> role="status">
<i data-feather="clipboard" aria-hidden="true"></i>
<span>No hay recetas médicas registradas.</span>
</div>

</div>
</div>

</div>
</div>

</section>

<div id="agregar-receta-modal" class="doctor-recipe-modal" role="dialog" aria-modal="true" aria-labelledby="agregar-receta-modal-title" aria-describedby="mensaje" aria-hidden="true" hidden>
<div class="doctor-recipe-modal-content doctor-recipe-create-modal-content">
<div class="doctor-recipe-modal-header">
<div>
<p class="dashboard-eyebrow">REGISTRO CLÍNICO</p>
<h2 id="agregar-receta-modal-title">Agregar Receta</h2>
</div>
<button id="closeAgregarRecetaButton" type="button" class="doctor-recipe-modal-close" aria-label="Cerrar" title="Cerrar" onclick="cerrarAgregarRecetaModal()">
<i data-feather="x" aria-hidden="true"></i>
</button>
</div>
<div class="doctor-recipe-modal-body">
<div class="doctor-recipe-field">
<label class="doctor-recipe-field-label" for="Diagnostico">Diagnóstico</label>
<textarea class="form-control fs-5" id="Diagnostico" rows="2"></textarea>
</div>

<div class="row">
<div class="col-md-6 mb-3">
<label class="doctor-recipe-field-label" for="TA">Tensión Arterial (TA)</label>
<input type="text" class="form-control fs-5" id="TA">
</div>

<div class="col-md-6 mb-3">
<label class="doctor-recipe-field-label" for="FC">Frecuencia Cardíaca (FC)</label>
<input type="number" class="form-control fs-5" id="FC">
</div>

<div class="col-md-6 mb-3">
<label class="doctor-recipe-field-label" for="SpO2">Saturación de Oxígeno (SpO₂)</label>
<input type="number" class="form-control fs-5" id="SpO2">
</div>

<div class="col-md-6 mb-3">
<label class="doctor-recipe-field-label" for="Temperatura">Temperatura</label>
<div class="input-group">
<input type="number" class="form-control fs-5" id="Temperatura" step="0.1">
<span class="input-group-text fs-5">°C</span>
</div>
</div>
</div>

<div class="doctor-recipe-field">
<label class="doctor-recipe-field-label" for="snow">Medicamento</label>
<div id="snow" class="editor" role="textbox" aria-multiline="true" aria-label="Medicamento"></div>
</div>
<div id="mensaje" role="alert" aria-live="polite" aria-atomic="true"></div>
</div>
<div class="doctor-recipe-modal-actions">
<button type="button" class="btn doctor-recipe-modal-cancel" onclick="cerrarAgregarRecetaModal()">Cancelar</button>
<button id="agregarRecetaButton" type="button" class="btn btn-primary" onclick="AgregarReceta(<?=$data['idPaciente'];?>)">
<span class="doctor-recipe-submit-label">Guardar receta</span> <i data-feather="check" aria-hidden="true"></i>
</button>
</div>
</div>
</div>

<div id="detalle-receta-modal" class="doctor-recipe-modal" role="dialog" aria-modal="true" aria-labelledby="detalleRecetaModalTitle" aria-describedby="detalleRecetaModalStatus" aria-hidden="true" hidden>
<div class="doctor-recipe-modal-content doctor-recipe-detail-modal-content">
<div class="doctor-recipe-modal-header">
<div>
<p class="dashboard-eyebrow">REGISTRO CLÍNICO</p>
<h2 id="detalleRecetaModalTitle">Detalle de receta</h2>
</div>
<button id="closeDetalleRecetaButton" type="button" class="doctor-recipe-modal-close" aria-label="Cerrar" title="Cerrar" onclick="cerrarDetalleReceta()">
<i data-feather="x" aria-hidden="true"></i>
</button>
</div>
<div class="doctor-recipe-modal-body doctor-recipe-detail-modal-body">
<p id="detalleRecetaModalStatus" class="doctor-recipe-modal-status" role="status" aria-live="polite"></p>
<div id="detalleRecetaModalBody" class="doctor-recipe-detail-content" aria-live="polite"></div>
</div>
</div>
</div>

<div id="modalImprimirReceta" class="doctor-recipe-modal doctor-recipe-print-modal" role="dialog" aria-modal="true" aria-labelledby="recetaPreviewTitle" aria-describedby="recetaPreviewStatus" aria-hidden="true" hidden>
<div class="doctor-recipe-modal-content doctor-recipe-print-modal-content">
<div class="doctor-recipe-modal-header">
<div>
<p class="dashboard-eyebrow">DOCUMENTO CLÍNICO</p>
<h2 id="recetaPreviewTitle">Vista previa de receta</h2>
</div>
<button id="closeRecipePreviewButton" type="button" class="doctor-recipe-modal-close" aria-label="Cerrar" title="Cerrar" onclick="cerrarVistaPreviaReceta()">
<i data-feather="x" aria-hidden="true"></i>
</button>
</div>
<div class="doctor-recipe-modal-body doctor-recipe-print-modal-body">
<div class="doctor-recipe-print-options" aria-label="Formato de receta">
<div class="form-check">
<input class="form-check-input" type="radio" name="formatoReceta" id="formatoDatos" value="0" checked>
<label class="form-check-label" for="formatoDatos">Imprimir únicamente los datos</label>
</div>
<div class="doctor-recipe-print-help">Genera la receta únicamente con los datos del paciente, sin diseño ni fondo oficial.</div>
<div class="form-check">
<input class="form-check-input" type="radio" name="formatoReceta" id="formatoCompleto" value="1">
<label class="form-check-label" for="formatoCompleto">Imprimir con formato de receta</label>
</div>
<div class="doctor-recipe-print-help">Genera la receta con el diseño y fondo oficial.</div>
</div>
<p id="recetaPreviewStatus" class="doctor-recipe-modal-status" role="status" aria-live="polite"></p>
<div class="doctor-recipe-preview-frame-wrap">
<iframe id="recetaPreviewFrame" title="Vista previa del documento de receta" hidden></iframe>
</div>
</div>
<div class="doctor-recipe-modal-actions">
<button type="button" class="btn doctor-recipe-modal-cancel" onclick="cerrarVistaPreviaReceta()">Cerrar</button>
<button id="printRecipePreviewButton" type="button" class="btn btn-primary" disabled onclick="generarPDF()">
<i data-feather="printer" aria-hidden="true"></i> Imprimir
</button>
</div>
</div>
</div>


</div>

<?php include_once __DIR__ . '/../components/footer-doctor.php';?>
</div>
</div>
<script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="<?=RUTA_JS;?>app.js"></script> 
<script src="<?=RUTA_JS;?>main.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/quill/quill.min.js"></script>
<script src="<?=RUTA_JS?>search-main.js"></script>

<script>

initializeRecipeTable();

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

document.querySelectorAll('input[name="formatoReceta"]').forEach((input) => {
input.addEventListener('change', () => {
const printModal = document.getElementById('modalImprimirReceta');
if (printModal && !printModal.hidden && recetaPrintId) {
cargarVistaPreviaReceta(recetaPrintId, recetaPrintTipo);
}
});
});

</script>
</body>
</html>

