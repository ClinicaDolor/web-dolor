<?php
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$formatInteger = static fn ($value): string => number_format((int) $value, 0, '.', ',');
$formatDecimal = static function ($value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
};
$indicatorData = $data['indicator_data'] ?? null;
$period = $indicatorData['period'] ?? [];
$current = $indicatorData['current'] ?? [];
$moduleFinalizations = $indicatorData['module_finalizations'] ?? [];
$dataQuality = $indicatorData['data_quality'] ?? [];
$patientsByYear = $indicatorData['patients_by_year'] ?? [];
$activitySeries = $indicatorData['activity_series'] ?? [];
$selectedYear = $data['selected_year'] ?? null;
$periodLabel = $selectedYear === null ? 'Todos los años' : (string) $selectedYear;
$averageHistory = $current['promedio_historia_clinica'] ?? null;
$averageHistoryAvailable = $averageHistory !== null && $averageHistory !== '' && is_numeric($averageHistory);
$averageHistoryText = $averageHistoryAvailable ? $formatDecimal($averageHistory) . '%' : '—';
$historyProgress = $averageHistoryAvailable ? max(0.0, min(100.0, (float) $averageHistory)) : null;
$historyRingRadius = 25;
$historyRingCircumference = 2 * pi() * $historyRingRadius;
$historyRingDash = $historyProgress === null ? 0 : $historyRingCircumference * ($historyProgress / 100);
$historyRingDashText = number_format($historyRingDash, 3, '.', '');
$historyRingCircumferenceText = number_format($historyRingCircumference, 3, '.', '');
$chartPayload = [
    'patientsByYear' => $patientsByYear,
    'activitySeries' => $activitySeries,
    'selectedYear' => $selectedYear,
];
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $escape($data['title'] ?? 'Centro de indicadores'); ?></title>
<link rel="stylesheet" href="<?= RUTA_CSS; ?>bootstrap.css">
<link rel="stylesheet" href="<?= RUTA_PUBLIC; ?>libs/perfect-scrollbar/perfect-scrollbar.css">
<link rel="stylesheet" href="<?= RUTA_CSS; ?>app.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= RUTA_JS; ?>loader.js"></script>
</head>
<body class="app-shell-body app-shell-doctor doctor-kpi-page">
<div class="LoaderPage"></div>
<div id="app">
<?= $data['sidebar']; ?>
<div id="main">
<?php include_once __DIR__ . '/../components/search-bar-doctor.php'; ?>
<main class="main-content container-fluid doctor-kpi-content">
<header class="doctor-page-hero doctor-kpi-hero">
<div class="doctor-page-hero-copy doctor-kpi-hero-copy">
<p class="dashboard-eyebrow">ANÁLISIS CLÍNICO</p>
<h1>Centro de indicadores</h1>
<p>Estadísticas clínicas, evolución y seguimiento de la información registrada.</p>
<span class="doctor-kpi-context">Clínica actual · datos agregados</span>
</div>
<form class="doctor-kpi-period-form" method="get" action="<?= $escape(SERVIDOR . 'clinica/indicadores'); ?>" aria-label="Filtrar indicadores por año">
<label for="kpi-year">Periodo de actividad</label>
<div class="doctor-kpi-period-controls">
<select id="kpi-year" name="year">
<option value="all"<?= $selectedYear === null ? ' selected' : ''; ?>>Todos los años</option>
<?php foreach (($data['available_years'] ?? []) as $year): ?>
<option value="<?= $escape($year); ?>"<?= $selectedYear === (int) $year ? ' selected' : ''; ?>><?= $escape($year); ?></option>
<?php endforeach; ?>
</select>
<button type="submit" class="doctor-page-hero-action">Aplicar</button>
</div>
</form>
</header>

<?php if (!empty($data['filter_error'])): ?>
<div class="doctor-kpi-alert" role="status"><?= $escape($data['filter_error']); ?></div>
<?php endif; ?>

<?php if (!empty($data['data_error'])): ?>
<div class="doctor-kpi-alert doctor-kpi-alert-error" role="alert"><?= $escape($data['data_error']); ?></div>
<?php else: ?>
<section class="doctor-kpi-overview" aria-labelledby="doctor-kpi-overview-title">
<div class="doctor-kpi-section-heading">
<div>
<p class="dashboard-eyebrow">PANORAMA</p>
<h2 id="doctor-kpi-overview-title">Resumen de la clínica</h2>
<p>Estado actual y actividad del periodo: <strong><?= $escape($periodLabel); ?></strong>.</p>
</div>
<span class="doctor-section-caption">Clínica actual · agregados</span>
</div>
<div class="doctor-kpi-metric-grid">
<article class="doctor-kpi-metric doctor-kpi-metric-patients">
<div class="doctor-kpi-metric-top"><span class="doctor-kpi-icon" aria-hidden="true"><i data-feather="users"></i></span><span class="doctor-kpi-metric-label">Pacientes autorizados</span></div>
<strong class="doctor-kpi-metric-value"><?= $formatInteger($current['total_pacientes'] ?? 0); ?></strong><small>Estado actual</small>
</article>
<article class="doctor-kpi-metric doctor-kpi-metric-history">
<div class="doctor-kpi-metric-top"><span class="doctor-kpi-icon" aria-hidden="true"><i data-feather="activity"></i></span><span class="doctor-kpi-metric-label">Avance promedio HC</span></div>
<div class="doctor-kpi-history-metric" role="img" aria-label="Avance promedio de Historia Clínica: <?= $escape($historyProgress === null ? 'no disponible' : $averageHistoryText); ?>">
<?php if ($historyProgress === null): ?>
<span class="doctor-kpi-progress-ring doctor-kpi-progress-ring-unavailable" aria-hidden="true">—</span>
<?php else: ?>
<svg class="doctor-kpi-progress-ring" viewBox="0 0 60 60" aria-hidden="true" focusable="false">
<circle class="doctor-kpi-progress-ring-track" cx="30" cy="30" r="<?= $historyRingRadius; ?>"></circle>
<circle class="doctor-kpi-progress-ring-value" cx="30" cy="30" r="<?= $historyRingRadius; ?>" stroke-dasharray="<?= $historyRingDashText; ?> <?= $historyRingCircumferenceText; ?>" transform="rotate(-90 30 30)"></circle>
</svg>
<?php endif; ?>
<div class="doctor-kpi-history-copy"><strong class="doctor-kpi-metric-value"><?= $escape($averageHistoryText); ?></strong><small>Nueve módulos canónicos</small></div>
</div>
</article>
<article class="doctor-kpi-metric doctor-kpi-metric-enrollments">
<div class="doctor-kpi-metric-top"><span class="doctor-kpi-icon" aria-hidden="true"><i data-feather="user-plus"></i></span><span class="doctor-kpi-metric-label">Altas de pacientes</span></div>
<strong class="doctor-kpi-metric-value"><?= $formatInteger($period['altas_pacientes'] ?? 0); ?></strong><small>Registradas en el periodo</small>
</article>
<article class="doctor-kpi-metric doctor-kpi-metric-notes">
<div class="doctor-kpi-metric-top"><span class="doctor-kpi-icon" aria-hidden="true"><i data-feather="file-text"></i></span><span class="doctor-kpi-metric-label">Notas subsecuentes</span></div>
<strong class="doctor-kpi-metric-value"><?= $formatInteger($period['notas'] ?? 0); ?></strong><small><?= $formatInteger($period['pacientes_con_notas'] ?? 0); ?> pacientes con registro</small>
</article>
<article class="doctor-kpi-metric doctor-kpi-metric-labs">
<div class="doctor-kpi-metric-top"><span class="doctor-kpi-icon" aria-hidden="true"><i data-feather="package"></i></span><span class="doctor-kpi-metric-label">Laboratorios</span></div>
<strong class="doctor-kpi-metric-value"><?= $formatInteger($period['laboratorios'] ?? 0); ?></strong><small><?= $formatInteger($period['pacientes_con_laboratorios'] ?? 0); ?> pacientes con registro</small>
</article>
<article class="doctor-kpi-metric doctor-kpi-metric-prescriptions">
<div class="doctor-kpi-metric-top"><span class="doctor-kpi-icon" aria-hidden="true"><i data-feather="file-plus"></i></span><span class="doctor-kpi-metric-label">Recetas internas</span></div>
<strong class="doctor-kpi-metric-value"><?= $formatInteger($period['recetas'] ?? 0); ?></strong><small><?= $formatInteger($period['pacientes_con_recetas'] ?? 0); ?> pacientes autorizados</small>
</article>
<article class="doctor-kpi-metric doctor-kpi-metric-cofepris">
<div class="doctor-kpi-metric-top"><span class="doctor-kpi-icon" aria-hidden="true"><i data-feather="clipboard"></i></span><span class="doctor-kpi-metric-label">Registros COFEPRIS</span></div>
<strong class="doctor-kpi-metric-value"><?= $formatInteger($period['cofepris'] ?? 0); ?></strong><small><?= $formatInteger($period['pacientes_con_cofepris'] ?? 0); ?> pacientes ligados</small>
</article>
</div>
</section>

<nav class="doctor-kpi-tabs" aria-label="Secciones de indicadores" role="tablist">
<button id="kpi-panorama-tab" type="button" role="tab" aria-selected="true" aria-controls="kpi-panorama" data-kpi-tab="kpi-panorama">Panorama</button>
<button id="kpi-historia-tab" type="button" role="tab" aria-selected="false" aria-controls="kpi-historia" data-kpi-tab="kpi-historia" tabindex="-1">Historia Clínica</button>
<button id="kpi-atencion-tab" type="button" role="tab" aria-selected="false" aria-controls="kpi-atencion" data-kpi-tab="kpi-atencion" tabindex="-1">Atención clínica</button>
<button id="kpi-documentos-tab" type="button" role="tab" aria-selected="false" aria-controls="kpi-documentos" data-kpi-tab="kpi-documentos" tabindex="-1">Recetas y documentos</button>
<button id="kpi-calidad-tab" type="button" role="tab" aria-selected="false" aria-controls="kpi-calidad" data-kpi-tab="kpi-calidad" tabindex="-1">Cobertura y calidad</button>
</nav>

<section id="kpi-panorama" class="doctor-kpi-tab-panel" role="tabpanel" aria-labelledby="kpi-panorama-tab" data-kpi-panel>
<div class="doctor-kpi-chart-stack">
<article class="doctor-kpi-card doctor-kpi-chart-card">
<div class="doctor-kpi-card-heading"><div><p class="dashboard-eyebrow">TENDENCIA</p><h2>Pacientes registrados por año</h2></div></div>
<p class="doctor-kpi-chart-description">Unidad: pacientes con alta registrada en la clínica. El histórico conserva todos los años con fecha válida.</p>
<div id="kpi-patients-chart" class="doctor-kpi-chart" aria-live="polite"></div>
<details class="doctor-kpi-data-table"><summary>Ver datos tabulares</summary><table><thead><tr><th>Año</th><th>Pacientes</th></tr></thead><tbody>
<?php if ($patientsByYear === []): ?><tr><td colspan="2">Sin registros con fecha disponible.</td></tr><?php else: foreach ($patientsByYear as $row): ?><tr><td><?= $escape($row['label']); ?></td><td><?= $formatInteger($row['value']); ?></td></tr><?php endforeach; endif; ?>
</tbody></table></details>
</article>
<section class="doctor-kpi-activity-block" aria-labelledby="kpi-activity-title">
<div class="doctor-kpi-card-heading"><div><p class="dashboard-eyebrow">ACTIVIDAD</p><h2 id="kpi-activity-title">Actividad clínica agregada</h2></div></div>
<p class="doctor-kpi-chart-description">Notas, laboratorios, recetas internas y COFEPRIS con relación autorizada.</p>
<div id="kpi-activity-chart" class="doctor-kpi-series-stack" aria-live="polite"></div>
<details class="doctor-kpi-data-table"><summary>Ver datos tabulares</summary><table><thead><tr><th>Periodo</th><th>Notas</th><th>Laboratorios</th><th>Recetas</th><th>COFEPRIS</th></tr></thead><tbody>
<?php if ($activitySeries === []): ?><tr><td colspan="5">Sin actividad con fecha disponible.</td></tr><?php else: foreach ($activitySeries as $row): ?><tr><th scope="row"><?= $escape($row['label']); ?></th><td><?= $formatInteger($row['notes'] ?? 0); ?></td><td><?= $formatInteger($row['laboratorios'] ?? 0); ?></td><td><?= $formatInteger($row['recetas'] ?? 0); ?></td><td><?= $formatInteger($row['cofepris'] ?? 0); ?></td></tr><?php endforeach; endif; ?>
</tbody></table></details>
</section>
</div>
</section>

<section id="kpi-historia" class="doctor-kpi-tab-panel" role="tabpanel" hidden data-kpi-panel>
<article class="doctor-kpi-card">
<div class="doctor-kpi-card-heading"><div><p class="dashboard-eyebrow">HISTORIA CLÍNICA</p><h2>Avance canónico y finalizaciones</h2></div></div>
<p class="doctor-kpi-chart-description">El promedio utiliza el cálculo canónico de los nueve módulos del Dashboard Doctor. Las finalizaciones se muestran sólo cuando existe el registro formal correspondiente.</p>
<div class="doctor-kpi-callout"><strong><?= $escape($averageHistoryText); ?></strong><span>Avance promedio actual de Historia Clínica</span></div>
<div class="doctor-kpi-table-wrap"><table class="doctor-kpi-table"><thead><tr><th>Módulo</th><th>Pacientes con finalización</th><th>Finalizaciones registradas</th><th>Última fecha</th></tr></thead><tbody>
<?php if ($moduleFinalizations === []): ?><tr><td colspan="4">El catálogo de módulos no está disponible.</td></tr><?php else: foreach ($moduleFinalizations as $module): ?><tr><th scope="row"><?= $escape($module['nombre']); ?></th><td><?= $formatInteger($module['pacientes_finalizados']); ?></td><td><?= $formatInteger($module['finalizaciones']); ?></td><td><?= $module['ultima_fecha'] ? $escape($module['ultima_fecha']) : 'No registrada'; ?></td></tr><?php endforeach; endif; ?>
</tbody></table></div>
<p class="doctor-kpi-note">No se clasifican pacientes como iniciados, parciales o completos por módulo en esta versión: el esquema no expone un agregador independiente y no se sustituye el cálculo canónico por supuestos.</p>
</article>
</section>

<section id="kpi-atencion" class="doctor-kpi-tab-panel" role="tabpanel" hidden data-kpi-panel>
<article class="doctor-kpi-card">
<div class="doctor-kpi-card-heading"><div><p class="dashboard-eyebrow">ATENCIÓN CLÍNICA</p><h2>Registros del periodo</h2></div></div>
<div class="doctor-kpi-table-wrap"><table class="doctor-kpi-table"><thead><tr><th>Dominio</th><th>Registros</th><th>Pacientes con registro</th><th>Denominador adicional</th></tr></thead><tbody>
<tr><th scope="row">Notas subsecuentes</th><td><?= $formatInteger($period['notas'] ?? 0); ?></td><td><?= $formatInteger($period['pacientes_con_notas'] ?? 0); ?></td><td>Promedio por paciente: <?= $escape($formatDecimal($period['promedio_notas_por_paciente'] ?? null)); ?></td></tr>
<tr><th scope="row">Laboratorios</th><td><?= $formatInteger($period['laboratorios'] ?? 0); ?></td><td><?= $formatInteger($period['pacientes_con_laboratorios'] ?? 0); ?></td><td>Solicitudes/resultados no se separan sin estado verificable.</td></tr>
</tbody></table></div>
</article>
</section>

<section id="kpi-documentos" class="doctor-kpi-tab-panel" role="tabpanel" hidden data-kpi-panel>
<article class="doctor-kpi-card">
<div class="doctor-kpi-card-heading"><div><p class="dashboard-eyebrow">DOCUMENTOS CLÍNICOS</p><h2>Recetas y COFEPRIS autorizados</h2></div></div>
<div class="doctor-kpi-table-wrap"><table class="doctor-kpi-table"><thead><tr><th>Dominio</th><th>Registros</th><th>Pacientes relacionados</th><th>Alcance</th></tr></thead><tbody>
<tr><th scope="row">Recetas internas</th><td><?= $formatInteger($period['recetas'] ?? 0); ?></td><td><?= $formatInteger($period['pacientes_con_recetas'] ?? 0); ?></td><td>Relación `receta_medica` → `pc_paciente` → clínica.</td></tr>
<tr><th scope="row">Registros COFEPRIS</th><td><?= $formatInteger($period['cofepris'] ?? 0); ?></td><td><?= $formatInteger($period['pacientes_con_cofepris'] ?? 0); ?></td><td>Relación `cofepris` → `pc_paciente` → clínica.</td></tr>
</tbody></table></div>
<p class="doctor-kpi-note">Las recetas externas y `cofepris_folio` se excluyen porque el esquema actual no demuestra propietario clínico verificable.</p>
</article>
</section>

<section id="kpi-calidad" class="doctor-kpi-tab-panel" role="tabpanel" hidden data-kpi-panel>
<article class="doctor-kpi-card">
<div class="doctor-kpi-card-heading"><div><p class="dashboard-eyebrow">CALIDAD DE DATOS</p><h2>Cobertura y fuentes excluidas</h2></div></div>
<div class="doctor-kpi-quality-grid">
<?php foreach ($dataQuality as $quality): ?><div class="doctor-kpi-quality-item"><strong><?= $formatInteger($quality['invalid_dates']); ?></strong><span><?= $escape($quality['label']); ?> sin fecha válida</span></div><?php endforeach; ?>
</div>
<div class="doctor-kpi-exclusions"><strong>Exclusiones activas</strong><ul><li>Recetas externas: sin relación verificable con paciente o clínica.</li><li><code>cofepris_folio</code>: sin propiedad clínica verificable.</li><li>Estados iniciada/parcial/completa por módulo: no expuestos sin un agregador respaldado.</li></ul></div>
</article>
</section>
<?php endif; ?>
</main>
<?php include_once __DIR__ . '/../components/footer-doctor.php'; ?>
</div>
</div>
<script type="application/json" id="doctor-kpi-data"><?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="<?= RUTA_JS; ?>feather-icons/feather.min.js"></script>
<script src="<?= RUTA_PUBLIC; ?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="<?= RUTA_JS; ?>app.js"></script>
<script src="<?= RUTA_JS; ?>main.js"></script>
<script src="<?= RUTA_JS; ?>search-main.js"></script>
<script src="<?= RUTA_JS; ?>clinica-indicadores.js"></script>
</body>
</html>
