<?php

use App\Models\NotaSubsecuenteModel;

$model = new NotaSubsecuenteModel();
$nombrePacienteSeguro = htmlspecialchars((string)($data['nombre_paciente'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$nombrePacienteVisible = $nombrePacienteSeguro !== '' ? $nombrePacienteSeguro : 'Paciente';
$referenciaSegura = htmlspecialchars((string)($data['referencia'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$volverExpedienteUrl = htmlspecialchars(SERVIDOR . 'clinica/paciente/' . (int)$data['idPaciente'], ENT_QUOTES, 'UTF-8');
$newUrl = htmlspecialchars((string)($data['new_url'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$saved = isset($_GET['saved']) && (string)$_GET['saved'] === '1';
$registros = is_array($data['registros'] ?? null) ? $data['registros'] : [];

$primerNotaId = (int)$model->primerNota((int)$data['idPaciente']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars((string)$data['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></title>
<link rel="shortcut icon" href="<?=RUTA_IMAGES;?>logo-clinica.png">
<link rel="apple-touch-icon" href="<?=RUTA_IMAGES;?>logo-clinica.png">
<link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
<link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
</head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-note-page">
<div id="app">

<?=$data['sidebar'];?>

<div id="main" data-referencia="<?=$referenciaSegura;?>" data-paciente="<?=htmlspecialchars((string)$data['idPaciente'], ENT_QUOTES, 'UTF-8');?>">
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

<div class="main-content container-fluid doctor-note-main-content">

<header class="doctor-page-hero doctor-note-hero">
<div class="doctor-page-hero-copy">
<p class="dashboard-eyebrow">Nota subsecuente</p>
<h1>Notas subsecuentes</h1>
<p>Consulta el historial de seguimiento de esta referencia clínica y abre una nueva nota cuando corresponda.</p>
</div>
<div class="doctor-page-hero-actions">
<a class="btn doctor-page-hero-action" data-doctor-back-action href="<?=$volverExpedienteUrl;?>">
<i data-feather="arrow-left" aria-hidden="true"></i>
<span>Volver al expediente</span>
</a>
</div>
</header>

<section class="doctor-note-patient-context" aria-label="Contexto del paciente">
<div class="doctor-note-patient-strip">
<span class="doctor-note-patient-icon" aria-hidden="true"><i data-feather="user"></i></span>
<div class="doctor-note-patient-context-copy">
<p class="dashboard-eyebrow">EXPEDIENTE CLÍNICO</p>
<h2><?=$nombrePacienteVisible;?></h2>
<p>Expediente #<?=htmlspecialchars((string)$data['idPaciente'], ENT_QUOTES, 'UTF-8');?> · Referencia <?=htmlspecialchars((string)$data['referencia'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></p>
</div>
</div>
</section>

<section class="doctor-note-history-section" aria-labelledby="doctor-note-history-title">
<?php if ($saved): ?>
<div class="alert alert-success" role="status">Nota subsecuente guardada correctamente.</div>
<?php endif; ?>

<div class="row doctor-note-history-grid">
<div class="col-12">
<div class="card doctor-note-card doctor-note-history-card">
<div class="card-header doctor-note-history-card-header">
<div>
<p class="dashboard-eyebrow mb-1">Seguimiento clínico</p>
<h2 id="doctor-note-history-title" class="card-title mb-0">Historial de notas</h2>
</div>
<a class="btn btn-primary doctor-note-history-action" href="<?=$newUrl;?>">
<i data-feather="plus" aria-hidden="true"></i>
<span>Nueva nota subsecuente</span>
</a>
</div>
<div class="card-body">
<?php if ($registros): ?>
<table class="table table-striped table-hover table-sm pb-0 mb-0" id="table1">
<thead><tr><th class="text-center">#</th><th>Fecha y hora</th><th>Referencia</th><th>Próxima cita</th><th class="text-end">ACCIONES</th></tr></thead>
<tbody>
<?php foreach ($registros as $registro):
    $registroId = (int)$registro['id'];
    $historialReferenciaRaw = (string)($data['referencia'] ?? '');
    $historialContextQuery = 'context_ref=' . rawurlencode($historialReferenciaRaw) . '&context_patient=' . rawurlencode((string)$data['idPaciente']);
    $detalleUrl = htmlspecialchars(SERVIDOR . 'clinica/nota-subsecuente/' . $registroId . '?' . $historialContextQuery, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $primer = $primerNotaId === $registroId ? '<i class="text-info" data-feather="star" width="18" aria-label="Primera nota"></i>' : '';
    $codigoReferenciaRaw = (string)($registro['codigo_referencia'] ?? '');
    $codigoReferencia = htmlspecialchars($codigoReferenciaRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $proximaCita = trim((string)($registro['proxima_cita'] ?? ''));
    $proximaCitaVisible = $proximaCita === '' ? 'S/I' : (new DateTime($proximaCita))->format('d/m/Y');
?>
<tr>
<td class="text-center"><?=$registroId;?></td>
<td><?=(new DateTime((string)$registro['fecha_hora']))->format('d/m/Y h:i a');?> <?=$primer;?></td>
<td><code class="doctor-note-history-reference" title="<?=$codigoReferencia;?>"><?=$codigoReferencia;?></code></td>
<td><?=$proximaCitaVisible;?></td>
<td class="text-end">
<a class="btn btn-sm doctor-note-detail-action" href="<?=$detalleUrl;?>" aria-label="Ver detalle de la nota <?=$registroId;?>">
<i data-feather="file-text" aria-hidden="true"></i>
<span>Ver detalle</span>
</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?>
<div class="doctor-note-empty-state" role="status">
<p>No hay notas subsecuentes registradas para este paciente.</p>
<a class="btn btn-primary" href="<?=$newUrl;?>">Crear nueva nota subsecuente</a>
</div>
<?php endif; ?>
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
<script src="<?=RUTA_JS;?>search-main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.querySelector('#table1');
    if (table && window.simpleDatatables) {
        new simpleDatatables.DataTable(table, {
            searchable: true,
            fixedHeight: false,
            perPageSelect: false,
            labels: {
                placeholder: 'Buscar...',
                noRows: 'No se encontraron coincidencias para la búsqueda.'
            },
            columns: [{ select: 1, sort: 'desc' }]
        });
    }
    if (window.feather) {
        feather.replace();
    }
});
</script>
</body>
</html>
