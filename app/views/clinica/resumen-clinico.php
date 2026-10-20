<?php
$patientId = (int)($data['idPaciente'] ?? 0);
$patientName = htmlspecialchars((string)($data['nombre_paciente'] ?? ''), ENT_QUOTES, 'UTF-8');
$patientSex = ($data['sexo'] ?? '') === 'M' ? 'Masculino' : (($data['sexo'] ?? '') === 'F' ? 'Femenino' : 'No registrado');
$admissionDate = !empty($data['fecha_alta']) ? (new DateTime($data['fecha_alta']))->format('d/m/Y') : 'No registrado';
$csrfToken = hash_hmac('sha256', 'clinical-summary:'.$patientId, (string)($_ENV['APP_KEY'] ?? ''));
$supplement = is_array($data['clinical_supplement'] ?? null) ? $data['clinical_supplement'] : [];
$h = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
$formatDate = static function ($value): string {
    if (empty($value)) return 'Fecha no registrada';
    try { return (new DateTime((string)$value))->format('d/m/Y H:i'); } catch (Throwable) { return 'Fecha no registrada'; }
};
$records = static fn(string $key): array => is_array($supplement[$key]['records'] ?? null) ? $supplement[$key]['records'] : [];
$hasMore = static fn(string $key): bool => !empty($supplement[$key]['has_more']);
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=htmlspecialchars($data['title'] ?? 'Resumen clínico', ENT_QUOTES, 'UTF-8');?></title>
    <link rel="shortcut icon" href="<?=RUTA_IMAGES?>logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES?>logo-clinica.png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>
</head>
<body class="app-shell-body app-shell-doctor clinical-summary-page">
    <div class="LoaderPage"></div>
    <div id="app">
        <?=$data['sidebar'];?>
        <div id="main">
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
            <main class="main-content container-fluid clinical-summary-main">
                <header class="clinical-summary-hero">
                    <div>
                        <p class="dashboard-eyebrow">APOYO A LA REVISIÓN</p>
                        <h1>Resumen clínico integral</h1>
                        <p class="clinical-summary-hero-description">Lectura estructurada del expediente autorizado, sin modificar sus registros.</p>
                    </div>
                    <a class="btn btn-light clinical-summary-back" data-doctor-back-action href="<?=SERVIDOR?>clinica/paciente/<?=$patientId?>">
                        <i data-feather="arrow-left" aria-hidden="true"></i>
                        <span>Volver al expediente</span>
                    </a>
                </header>

                <section class="clinical-summary-patient-context" aria-labelledby="clinicalSummaryPatientTitle">
                    <div>
                        <p class="dashboard-eyebrow mb-1">EXPEDIENTE AUTORIZADO</p>
                        <h2 id="clinicalSummaryPatientTitle"><?=$patientName;?></h2>
                        <div class="clinical-summary-patient-meta">
                            <span>Expediente #<?=$patientId;?></span>
                            <span>Edad <?=htmlspecialchars((string)($data['edad'] ?? 'No registrada'), ENT_QUOTES, 'UTF-8');?> años</span>
                            <span>Sexo <?=htmlspecialchars($patientSex, ENT_QUOTES, 'UTF-8');?></span>
                            <span>Alta <?=htmlspecialchars($admissionDate, ENT_QUOTES, 'UTF-8');?></span>
                        </div>
                    </div>
                    <div class="clinical-summary-local-badge"><i data-feather="server" aria-hidden="true"></i><span>Generación local</span><small>Gemini externo deshabilitado</small></div>
                </section>

                <section class="clinical-supplement-section" aria-labelledby="clinicalSupplementTitle">
                    <div class="clinical-summary-section-heading">
                        <p class="dashboard-eyebrow">SEGUIMIENTO Y DOCUMENTACIÓN</p>
                        <h2 id="clinicalSupplementTitle">Información clínica complementaria</h2>
                        <p class="clinical-summary-toolbar-helper">Metadatos y registros asociados al paciente autorizado. Los documentos protegidos no se descargan ni se incorporan al contexto externo.</p>
                    </div>
                    <div class="clinical-supplement-grid">
                        <article class="clinical-supplement-card">
                            <div class="clinical-supplement-card-heading"><i data-feather="clipboard" aria-hidden="true"></i><h3>Notas subsecuentes</h3></div>
                            <?php if ($records('notes')): foreach ($records('notes') as $note): ?>
                                <div class="clinical-supplement-record">
                                    <strong><?=$h($formatDate($note['fecha_hora'] ?? null));?></strong>
                                    <?php if (trim((string)($note['contenido'] ?? '')) !== ''): ?><p><?=nl2br($h($note['contenido']));?></p><?php else: ?><p class="clinical-supplement-muted">Contenido no registrado.</p><?php endif; ?>
                                    <div class="clinical-supplement-fields">
                                        <?php foreach (['ta'=>'TA','frec_cardiaca'=>'FC','pulso'=>'Pulso','spo2'=>'SpO2','fio2'=>'FiO2','ecog'=>'ECOG','karnovsky'=>'Karnofsky','peso'=>'Peso','talla'=>'Talla','proxima_cita'=>'Próxima cita'] as $field => $label): if (($note[$field] ?? '') !== '' && $note[$field] !== null): ?>
                                            <span><b><?=$h($label);?></b> <?=$h($field === 'proxima_cita' ? $formatDate($note[$field]) : $note[$field]);?></span>
                                        <?php endif; endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; else: ?><p class="clinical-supplement-empty">No registrado.</p><?php endif; ?>
                            <?php if ($hasMore('notes')): ?><p class="clinical-supplement-more">Hay más notas. Consulta el historial desde el expediente.</p><?php endif; ?>
                        </article>

                        <article class="clinical-supplement-card">
                            <div class="clinical-supplement-card-heading"><i data-feather="edit-3" aria-hidden="true"></i><h3>Prescripciones registradas</h3></div>
                            <?php if ($records('prescriptions')): foreach ($records('prescriptions') as $prescription): ?>
                                <div class="clinical-supplement-record">
                                    <strong><?=$h($formatDate($prescription['fecha_hora'] ?? null));?></strong>
                                    <?php if (($prescription['diagnostico'] ?? '') !== ''): ?><p><b>Diagnóstico documentado:</b> <?=$h($prescription['diagnostico']);?></p><?php endif; ?>
                                    <?php if (($prescription['medicamento'] ?? '') !== ''): ?><p><b>Medicamento prescrito:</b> <?=$h($prescription['medicamento']);?></p><?php endif; ?>
                                    <div class="clinical-supplement-fields">
                                        <?php foreach (['ta'=>'TA','fc'=>'FC','spo2'=>'SpO2','temperatura'=>'Temperatura'] as $field => $label): if (($prescription[$field] ?? '') !== '' && $prescription[$field] !== null): ?><span><b><?=$h($label);?></b> <?=$h($prescription[$field]);?></span><?php endif; endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; else: ?><p class="clinical-supplement-empty">No registrado.</p><?php endif; ?>
                            <?php if ($hasMore('prescriptions')): ?><p class="clinical-supplement-more">Hay más prescripciones. Consulta el historial desde el expediente.</p><?php endif; ?>
                        </article>

                        <article class="clinical-supplement-card">
                            <div class="clinical-supplement-card-heading"><i data-feather="file-text" aria-hidden="true"></i><h3>Laboratorio</h3></div>
                            <?php if ($records('laboratories')): foreach ($records('laboratories') as $laboratory): ?>
                                <div class="clinical-supplement-record">
                                    <strong><?=$h($formatDate($laboratory['fecha_hora'] ?? null));?></strong>
                                    <p><?=$h($laboratory['titulo'] ?? $laboratory['nombre'] ?? 'Estudio sin título');?></p>
                                    <div class="clinical-supplement-fields">
                                        <span><b>Nombre</b> <?=$h($laboratory['nombre'] ?? 'No registrado');?></span>
                                        <span><b>Tipo</b> <?=$h($laboratory['tipo'] ?? 'No registrado');?></span>
                                        <?php if (($laboratory['descripcion'] ?? '') !== ''): ?><span><b>Descripción</b> <?=$h($laboratory['descripcion']);?></span><?php endif; ?>
                                        <span><b>Referencia interna</b> LAB-<?=$h($laboratory['id'] ?? '');?></span>
                                    </div>
                                    <p class="clinical-supplement-muted">Archivo protegido: no se descarga desde este resumen.</p>
                                </div>
                            <?php endforeach; else: ?><p class="clinical-supplement-empty">No registrado.</p><?php endif; ?>
                            <?php if ($hasMore('laboratories')): ?><p class="clinical-supplement-more">Hay más estudios. Consulta el historial desde el expediente.</p><?php endif; ?>
                        </article>

                        <article class="clinical-supplement-card">
                            <div class="clinical-supplement-card-heading"><i data-feather="archive" aria-hidden="true"></i><h3>COFEPRIS</h3></div>
                            <?php if ($records('cofepris')): foreach ($records('cofepris') as $cofepris): ?>
                                <div class="clinical-supplement-record">
                                    <strong><?=$h($formatDate($cofepris['fecha_hora'] ?? null));?></strong>
                                    <p><b>Medicamento:</b> <?=$h($cofepris['medicamento'] ?? 'No registrado');?></p>
                                    <div class="clinical-supplement-fields">
                                        <?php foreach (['diagnostico'=>'Diagnóstico registrado','presentacion'=>'Presentación','dosificacion'=>'Dosificación','num_cajas'=>'Cajas','num_dias'=>'Duración','via_administracion'=>'Vía','surtido'=>'Estado de surtido'] as $field => $label): if (($cofepris[$field] ?? '') !== '' && $cofepris[$field] !== null): ?><span><b><?=$h($label);?></b> <?=$h($cofepris[$field]);?></span><?php endif; endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; else: ?><p class="clinical-supplement-empty">No registrado.</p><?php endif; ?>
                            <?php if ($hasMore('cofepris')): ?><p class="clinical-supplement-more">Hay más registros. Consulta el historial desde el expediente.</p><?php endif; ?>
                        </article>
                    </div>
                </section>

                <section class="clinical-summary-panel clinical-summary-independent-panel" id="clinicalSummaryPanel" aria-labelledby="clinicalSummaryTitle" data-patient-id="<?=$patientId?>" data-csrf-token="<?=htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8')?>">
                    <div class="clinical-summary-toolbar">
                        <div>
                            <p class="dashboard-eyebrow mb-1">RESUMEN CLÍNICO INTELIGENTE</p>
                            <h2 id="clinicalSummaryTitle">Panorama general e historia clínica</h2>
                            <p class="clinical-summary-toolbar-helper">El resumen se genera bajo demanda a partir de la información clínica autorizada.</p>
                        </div>
                        <button type="button" class="btn btn-primary" id="clinicalSummaryGenerate"><i data-feather="play" aria-hidden="true"></i> <span>Generar resumen</span></button>
                    </div>

                    <div class="clinical-summary-output" id="clinicalSummaryOutput" aria-live="polite">
                        <div class="clinical-summary-empty-state" role="status">
                            <i data-feather="file-text" aria-hidden="true"></i>
                            <h3>Resumen listo para consultar</h3>
                            <p>Genera una lectura completa con referencias y datos faltantes. No se guardará una copia del resultado.</p>
                        </div>
                    </div>
                </section>
            </main>
            <?php include_once __DIR__ . '/../components/footer-doctor.php';?>
        </div>
    </div>
    <script src="<?=RUTA_JS;?>feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>
    <script src="<?=RUTA_JS;?>main.js"></script>
    <script src="<?=RUTA_JS?>search-main.js"></script>
    <script src="<?=RUTA_JS?>clinical-summary.js"></script>
</body>
</html>
