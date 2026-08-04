<script>
function imprimirReceta(id, tipo) {
$('#modalImprimirReceta').data('id', id);
$('#modalImprimirReceta').data('tipo', tipo);
$('#modalImprimirReceta').modal('show');
}

function generarPDF() {
var modal = $('#modalImprimirReceta');
var id = modal.data('id');
var tipo = modal.data('tipo');
var conFormato = $('input[name="formatoReceta"]:checked').val();
var form = $('<form>', { method: 'POST', action: '/pdf/' + (tipo === 'externo' ? 'receta-externo/' : 'receta/') + id, target: '_blank' });
form.append($('<input>', { type: 'hidden', name: 'con_formato', value: conFormato }));
form.appendTo('body').submit().remove();
modal.modal('hide');
}
</script> 

<div class="modal fade" id="modalImprimirReceta" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Imprimir Receta</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">

<div>
<div class="form-check">
<input class="form-check-input" type="radio" name="formatoReceta" id="formatoDatos" value="0" checked>
<label class="form-check-label fw-bold" for="formatoDatos">Imprimir únicamente los datos</label>
</div>
<div class="alert alert-info ms-4 mb-3 py-2 ps-3">
<small>Genera la receta únicamente con los datos del paciente, sin diseño ni fondo oficial.</small>
</div>

<div class="form-check mb-2">
<input class="form-check-input" type="radio" name="formatoReceta" id="formatoCompleto" value="1">
<label class="form-check-label fw-bold" for="formatoCompleto">Imprimir con formato de receta</label>
</div>
<div class="alert alert-info ms-4 mb-3 py-2 ps-3">
<small>Genera la receta combinando los datos del paciente con el diseño y fondo oficial.</small>
</div>
</div>

</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-primary" onclick="generarPDF()"><i data-feather="printer"></i> Generar PDF</button>
</div>
</div>
</div>
</div>