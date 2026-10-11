<?php
use App\Controllers\ClinicaController;
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
<link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
<title><?=$data['title'];?></title>
<link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
<link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?=RUTA_JS;?>loader.js"></script>
<script>
const tableStates = [
'datatableState',
'datatableState_tableNotas',
'datatableState_tableRecetas',
'datatableState_tableLaboratorio',
'datatableState_tableCofepris',

'datatableMedicamentosState_1',
'datatableMedicamentosState_2',
'datatableMedicamentosState_3',
'datatableMedicamentosState_4',
'datatableMedicamentosState_5',
'datatableMedicamentosState_6',
'datatableProcedimientoState',
'datatableTratamientosState',
'datatableState_table_cirugia',
'datatableState_table_enfermedades',
'datatableState_table_medicacion'
];

tableStates.forEach(key => localStorage.removeItem(key));
</script>
</head>

<body class="app-shell-body app-shell-doctor dashboard-doctor">
<div class="LoaderPage"></div>
<div id="app">
<?=$data['sidebar'];?>

<div id="main">
<!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

<div class="main-content container-fluid doctor-dashboard-content">

<header class="doctor-dashboard-hero">
<div>
<p class="dashboard-eyebrow">PORTAL MÉDICO</p>
<h3>Panel clínico</h3>
<p class="doctor-dashboard-hero-copy">Consulta pacientes y continúa la atención clínica desde un espacio claro y enfocado.</p>
</div>
<span class="portal-badge"><i data-feather="shield" aria-hidden="true"></i> Entorno clínico</span>
</header>

<section class="doctor-dashboard-summary" aria-labelledby="doctor-summary-title">
<?php
$dashboardSummary = $data['dashboard_summary'] ?? [];
$averageHistory = $dashboardSummary['promedio_historia_clinica'] ?? null;
$averageHistoryText = $averageHistory === null
    ? '—'
    : rtrim(rtrim(number_format((float)$averageHistory, 1, '.', ''), '0'), '.');
$averageHistoryValue = $averageHistory === null
    ? null
    : max(0, min(100, (float)$averageHistory));
$metricValue = static function ($value): string {
    return htmlspecialchars((string)((int)$value), ENT_QUOTES, 'UTF-8');
};
?>
<div class="doctor-dashboard-summary-panel">
<div class="doctor-dashboard-summary-panel-header">
<div>
<p class="dashboard-eyebrow">RESUMEN</p>
<h4 id="doctor-summary-title">Resumen de la clínica</h4>
<p class="doctor-dashboard-summary-description">Indicadores generales · clínica actual</p>
</div>
</div>

<div class="doctor-dashboard-summary-grid">
<article class="doctor-summary-metric">
<span class="doctor-summary-metric-icon" aria-hidden="true"><i data-feather="users"></i></span>
<span class="doctor-summary-metric-label">Pacientes</span>
<strong><?= $metricValue($dashboardSummary['total_pacientes'] ?? 0); ?></strong>
<span class="doctor-summary-metric-helper">Registrados</span>
</article>

<article class="doctor-summary-metric doctor-summary-metric-progress">
<span class="doctor-summary-metric-icon" aria-hidden="true"><i data-feather="activity"></i></span>
<span class="doctor-summary-metric-label">Avance promedio HC</span>
<strong><?=htmlspecialchars($averageHistoryText, ENT_QUOTES, 'UTF-8');?><?php if ($averageHistoryValue !== null): ?>%<?php endif; ?></strong>
<?php if ($averageHistoryValue !== null): ?>
<div class="doctor-summary-meter" role="progressbar" aria-label="Avance promedio de Historia Clínica" aria-valuenow="<?=htmlspecialchars((string)$averageHistoryValue, ENT_QUOTES, 'UTF-8');?>" aria-valuemin="0" aria-valuemax="100">
<span style="width: <?=htmlspecialchars((string)$averageHistoryValue, ENT_QUOTES, 'UTF-8');?>%;"></span>
</div>
<?php else: ?>
<span class="doctor-summary-metric-helper">Sin pacientes para calcular</span>
<?php endif; ?>
</article>

<article class="doctor-summary-metric">
<span class="doctor-summary-metric-icon" aria-hidden="true"><i data-feather="clipboard"></i></span>
<span class="doctor-summary-metric-label">Notas subsecuentes</span>
<strong><?= $metricValue($dashboardSummary['total_notas'] ?? 0); ?></strong>
<span class="doctor-summary-metric-helper">Registros</span>
</article>

<article class="doctor-summary-metric">
<span class="doctor-summary-metric-icon" aria-hidden="true"><i data-feather="file-text"></i></span>
<span class="doctor-summary-metric-label">Laboratorios</span>
<strong><?= $metricValue($dashboardSummary['total_laboratorios'] ?? 0); ?></strong>
<span class="doctor-summary-metric-helper">Registros</span>
</article>

<article class="doctor-summary-metric">
<span class="doctor-summary-metric-icon" aria-hidden="true"><i data-feather="edit-3"></i></span>
<span class="doctor-summary-metric-label">Recetas</span>
<strong><?= $metricValue($dashboardSummary['total_recetas'] ?? 0); ?></strong>
<span class="doctor-summary-metric-helper">Internas autorizadas</span>
</article>

<article class="doctor-summary-metric">
<span class="doctor-summary-metric-icon" aria-hidden="true"><i data-feather="archive"></i></span>
<span class="doctor-summary-metric-label">Registros COFEPRIS</span>
<strong><?= $metricValue($dashboardSummary['total_cofepris'] ?? 0); ?></strong>
<span class="doctor-summary-metric-helper">Ligados a pacientes</span>
</article>
</div>
</div>
</section>

<section class="doctor-dashboard-actions" aria-labelledby="doctor-actions-title">
<div class="doctor-section-heading">
<div>
<p class="dashboard-eyebrow">ACCESOS RÁPIDOS</p>
<h4 id="doctor-actions-title">Continúa tu trabajo</h4>
</div>
<span class="doctor-section-caption">Rutas disponibles</span>
</div>

<div class="doctor-action-grid">
<a class="doctor-action-card" href="<?=SERVIDOR?>clinica/pacientes">
<span class="doctor-action-icon" aria-hidden="true"><i data-feather="users"></i></span>
<span class="doctor-action-copy"><strong>Directorio de pacientes</strong><small>Consulta y gestiona los expedientes registrados.</small></span>
<i class="doctor-action-arrow" data-feather="arrow-right" aria-hidden="true"></i>
</a>
<a class="doctor-action-card" href="<?=SERVIDOR?>clinica/paciente/nuevo">
<span class="doctor-action-icon" aria-hidden="true"><i data-feather="user-plus"></i></span>
<span class="doctor-action-copy"><strong>Nuevo paciente</strong><small>Inicia el registro de una persona en la clínica.</small></span>
<i class="doctor-action-arrow" data-feather="arrow-right" aria-hidden="true"></i>
</a>
<a class="doctor-action-card" href="<?=SERVIDOR?>clinica/recetas/nueva">
<span class="doctor-action-icon" aria-hidden="true"><i data-feather="file-plus"></i></span>
<span class="doctor-action-copy"><strong>Nueva receta</strong><small>Accede al flujo existente para emitir una receta.</small></span>
<i class="doctor-action-arrow" data-feather="arrow-right" aria-hidden="true"></i>
</a>
<a class="doctor-action-card" href="<?=SERVIDOR?>clinica/indicadores">
<span class="doctor-action-icon" aria-hidden="true"><i data-feather="bar-chart-2"></i></span>
<span class="doctor-action-copy"><strong>Ver indicadores completos</strong><small>Explora evolución, cobertura y actividad agregada.</small></span>
<i class="doctor-action-arrow" data-feather="arrow-right" aria-hidden="true"></i>
</a>
</div>
</section>
</div>

<!----- FOOTER ---------->
<?php include_once __DIR__ . '/../components/footer-doctor.php';?>

</div>
</div>

<script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="<?=RUTA_JS;?>app.js"></script>    
<script src="<?=RUTA_JS;?>main.js"></script>
<script src="<?=RUTA_JS?>search-main.js"></script>

</body>
</html>
