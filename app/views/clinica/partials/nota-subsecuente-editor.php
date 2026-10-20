<?php

use App\Models\RecetaModel;
use App\Services\ClinicalSummarySecurity;

$editMode = !empty($data['edit_mode']);
$submitLabel = $editMode ? 'Guardar cambios' : 'Guardar Nota subsecuente';
$laboratorioCsrfToken = ClinicalSummarySecurity::captureCsrfToken((int)$data['idPaciente']);
$notaCsrfToken = htmlspecialchars((string)($data['nota_csrf'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$contenidoNotaSeguro = RecetaModel::sanitizeMedicationHtml((string)($data['contenido'] ?? ''));
$medicamentoSeguro = RecetaModel::sanitizeMedicationHtml((string)($data['medicamento'] ?? ''));
$diagnosticoSeguro = htmlspecialchars((string)($data['diagnostico'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$temperaturaRecetaSeguro = htmlspecialchars((string)($data['temperatura'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$recetaSeleccionada = (int)($data['idreceta'] ?? 0) > 0;
?>

<div class="doctor-note-form-intro">
<p class="dashboard-eyebrow">REGISTRO CLÍNICO</p>
<h2 id="doctor-note-editor-title"><?=$editMode ? 'Actualización clínica' : 'Valoración clínica';?></h2>
<p>Registra signos vitales, evolución y los complementos que correspondan a esta consulta.</p>
</div>

<div class="row doctor-note-entry-grid">
<div class="col-12 doctor-note-entry-primary">

<div class="card doctor-note-card doctor-note-editor-card">
<div class="card-header doctor-note-optional-header">
<div>
<p class="dashboard-eyebrow">Valoración clínica</p>
<h3 class="card-title">Signos vitales</h3>
<p class="doctor-note-card-help">Captura únicamente los valores disponibles durante la revisión.</p>
</div>
</div>
<div class="card-body">
<div class="row doctor-note-vitals-grid">
<div class="col-12 col-sm-4 doctor-note-field"><div class="input-group"><span class="input-group-text">TA.</span><input type="text" class="form-control" id="TA" value="<?=htmlspecialchars((string)$data['ta'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>" aria-label="Tensión arterial"><span class="input-group-text bg-white">mmHg</span></div></div>
<div class="col-12 col-sm-4 doctor-note-field"><div class="input-group"><span class="input-group-text">Frec cardiaca</span><input type="text" class="form-control" id="FrecCardiaca" value="<?=htmlspecialchars((string)$data['frec_cardiaca'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>" aria-label="Frecuencia cardiaca"><span class="input-group-text bg-white">lpm</span></div></div>
<div class="col-12 col-sm-4 doctor-note-field"><div class="input-group"><span class="input-group-text">Pulso</span><input type="text" class="form-control" id="Pulso" value="<?=htmlspecialchars((string)$data['pulso'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>" aria-label="Pulso"><span class="input-group-text bg-white">lpm</span></div></div>
<div class="col-12 col-sm-4 doctor-note-field"><div class="input-group"><span class="input-group-text">SpO2.</span><input type="text" class="form-control" id="Spo2" value="<?=htmlspecialchars((string)$data['spo2'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>" aria-label="Saturación de oxígeno"><span class="input-group-text bg-white">%</span></div></div>
<div class="col-12 col-sm-4 doctor-note-field"><div class="input-group"><span class="input-group-text">FiO2.</span><input type="text" class="form-control" id="Fio2" value="<?=htmlspecialchars((string)$data['fio2'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>" aria-label="Fracción inspirada de oxígeno"><span class="input-group-text bg-white">%</span></div></div>
<div class="col-12 col-sm-4 doctor-note-field"><div class="input-group"><span class="input-group-text">ECOG.</span><input type="text" class="form-control" id="Ecog" value="<?=htmlspecialchars((string)$data['ecog'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>" aria-label="ECOG"></div></div>
<div class="col-12 col-sm-4 doctor-note-field"><div class="input-group"><span class="input-group-text">Karnovsky</span><input type="text" class="form-control" id="Karnovsky" value="<?=htmlspecialchars((string)$data['karnovsky'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>" aria-label="Karnovsky"></div></div>
<div class="col-12 col-sm-4 doctor-note-field"><div class="input-group"><span class="input-group-text">Peso.</span><input type="text" class="form-control" id="Peso" value="<?=htmlspecialchars((string)$data['peso'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>" aria-label="Peso"></div></div>
<div class="col-12 col-sm-4 doctor-note-field"><div class="input-group"><span class="input-group-text">Talla.</span><input type="text" class="form-control" id="Talla" value="<?=htmlspecialchars((string)$data['talla'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>" aria-label="Talla"></div></div>
</div>

<div class="doctor-note-subsection doctor-note-evolution-section">
<div class="doctor-note-subsection-heading">
<p class="dashboard-eyebrow">EVOLUCIÓN</p>
<h3>Nota médica</h3>
<p>Describe la evolución clínica de la consulta.</p>
</div>
<label class="text-primary mb-1" for="snow1"><small>Detalle:</small></label>
<div id="snow1" class="editor1 doctor-note-editor-shell" data-note-editor aria-label="Detalle de la nota médica"><?=$contenidoNotaSeguro;?></div>
</div>
</div>
</div>

<div class="card doctor-note-card doctor-note-recipe-card">
<div class="card-header doctor-note-optional-header">
<div>
<p class="dashboard-eyebrow">COMPLEMENTO OPCIONAL</p>
<h3 class="card-title">Receta médica</h3>
<p class="doctor-note-card-help">Actívala sólo cuando deba guardarse una receta junto con esta nota.</p>
</div>
<div class="form-check doctor-note-recipe-toggle">
<input class="form-check-input" type="checkbox" id="IncluirReceta" aria-describedby="IncluirRecetaAyuda" aria-controls="doctor-note-recipe-fields" <?=$recetaSeleccionada ? 'checked' : '';?> >
<label class="form-check-label" for="IncluirReceta">Guardar receta médica con esta nota</label>
<small class="d-block" id="IncluirRecetaAyuda">Déjalo sin seleccionar si sólo deseas guardar la Nota subsecuente.</small>
</div>
</div>
<div id="doctor-note-recipe-fields" class="card-body doctor-note-recipe-fields" data-recipe-fields <?=$recetaSeleccionada ? '' : 'hidden';?> aria-hidden="<?=$recetaSeleccionada ? 'false' : 'true';?>">
<label class="text-primary mb-1" for="Diagnostico"><small>Diagnóstico:</small></label>
<textarea class="form-control fs-5" id="Diagnostico" rows="2"><?=$diagnosticoSeguro;?></textarea>
<div class="mt-3"><label class="text-primary mb-1" for="Temperatura"><small>Temperatura:</small></label><input type="number" class="form-control fs-5" id="Temperatura" step="0.1" value="<?=$temperaturaRecetaSeguro;?>"></div>
<label class="text-primary mt-3 mb-1" for="snow2"><small>Medicamento:</small></label>
<div id="snow2" class="editor1 doctor-note-editor-shell" data-recipe-editor aria-label="Medicamento e indicaciones"><?=$medicamentoSeguro;?></div>
</div>
</div>
<div id="mensaje" role="status" aria-live="polite"></div>
</div>

<div class="col-12 doctor-note-entry-related">
<div class="card doctor-note-card doctor-note-lab-card">
<div class="card-header doctor-note-optional-header">
<div>
<p class="dashboard-eyebrow">COMPLEMENTO OPCIONAL</p>
<h3 class="card-title">Laboratorio</h3>
<p class="doctor-note-card-help">Consulta estudios o adjunta un documento relacionado mediante la acción independiente del laboratorio.</p>
</div>
<div class="form-check doctor-note-recipe-toggle doctor-note-laboratory-toggle">
<input class="form-check-input" type="checkbox" id="IncluirLaboratorio" aria-describedby="IncluirLaboratorioAyuda" aria-controls="doctor-note-lab-fields">
<label class="form-check-label" for="IncluirLaboratorio">Agregar laboratorio relacionado</label>
<small class="d-block" id="IncluirLaboratorioAyuda">Actívalo para mostrar los controles; el archivo se registra mediante su acción independiente.</small>
</div>
</div>
<div id="doctor-note-lab-fields" class="card-body" data-laboratory-fields hidden aria-hidden="true">
<section class="doctor-note-related-block" aria-labelledby="doctor-note-lab-history-title">
<div class="doctor-note-related-heading"><h4 id="doctor-note-lab-history-title">Estudios registrados</h4><p>Revisa los laboratorios asociados a esta referencia.</p></div>
<div id="conteLaboratorio"></div>
<div class="doctor-note-lab-detail" id="detalleLaboratorio"></div>
</section>
<hr>
<section class="doctor-note-related-block doctor-note-lab-upload" aria-labelledby="doctor-note-lab-upload-title">
<div class="doctor-note-related-heading"><h4 id="doctor-note-lab-upload-title">Adjuntar nuevo estudio</h4><p>Completa el título, selecciona el archivo y agrega su descripción.</p></div>
<label class="text-primary" for="Titulo"><small>Título:</small></label>
<input type="text" class="form-control" id="Titulo">
<label class="text-primary mt-3" for="Archivo"><small>Archivo:</small></label>
<div class="mb-3"><input type="file" class="form-control" id="Archivo"></div>
<label class="text-primary" for="snow3"><small>Descripción:</small></label>
<div id="snow3" class="editor2 doctor-note-editor-shell" data-laboratory-editor aria-label="Descripción del estudio"></div>
<div class="text-end mt-3"><button type="button" class="btn btn-outline-primary" onclick="AgregarLaboratorio()">Agregar laboratorio <i data-feather="plus" aria-hidden="true"></i></button></div>
</section>
</div>
</div>

<div class="card doctor-note-card doctor-note-appointment-card">
<div class="card-header doctor-note-card-header">
<div>
<p class="dashboard-eyebrow">SEGUIMIENTO</p>
<h3 class="card-title">Próxima cita <small>(opcional)</small></h3>
<p class="doctor-note-card-help">Registra una fecha de seguimiento sin crear una cita automáticamente.</p>
</div>
</div>
<div class="card-body"><label class="text-primary" for="FechaProximaCita"><small>Fecha:</small></label><input type="date" class="form-control" id="FechaProximaCita" value="<?=htmlspecialchars((string)$data['proximacita'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>"></div>
</div>

</div>

<div class="col-12 doctor-note-action-row">
<div class="doctor-note-form-actions">
<a class="btn btn-outline-primary" data-note-exit data-note-cancel href="<?=htmlspecialchars((string)($data['list_url'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>">Cancelar</a>
<div class="doctor-note-submit"><button type="button" class="btn btn-primary" onclick="AgregarNota()"><?=$submitLabel;?> <i data-feather="check" aria-hidden="true"></i></button></div>
</div>
</div>
</div>

<div id="nota-editor-config" hidden
data-id-paciente="<?=htmlspecialchars((string)$data['idPaciente'], ENT_QUOTES, 'UTF-8');?>"
data-referencia="<?=htmlspecialchars((string)$data['referencia'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>"
data-id-nota="<?=htmlspecialchars((string)($data['id_nota_subsecuente'] ?? 0), ENT_QUOTES, 'UTF-8');?>"
data-idreceta="<?=htmlspecialchars((string)($data['idreceta'] ?? 0), ENT_QUOTES, 'UTF-8');?>"
data-laboratorio-csrf="<?=htmlspecialchars($laboratorioCsrfToken, ENT_QUOTES, 'UTF-8');?>"
data-nota-csrf="<?=$notaCsrfToken;?>"></div>
