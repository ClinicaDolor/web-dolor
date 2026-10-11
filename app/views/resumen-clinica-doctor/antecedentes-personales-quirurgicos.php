<?php
use App\Config\Database;
use App\Models\PacienteModulosModelo;
use App\Models\AntecedentesQuirurgicos;

$bd = Database::getInstance();
$model2 = new PacienteModulosModelo();
$model = new AntecedentesQuirurgicos();
$contenidoAntescedentesQR = $model->editorTextoAQ($data['idPaciente']);

$escape = static function ($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$patientId = (int) ($data['idPaciente'] ?? 0);
$patientNameRaw = (string) ($data['nombre'] ?? '');
$titleRaw = (string) ($data['title'] ?? 'Antecedentes Personales Quirúrgicos');
$patientName = $escape($patientNameRaw);
$title = $escape($titleRaw);
$patientUrl = $escape(SERVIDOR . 'clinica/paciente/' . $patientId);
$editorContent = $model2->contenidoEditorPAC($patientId, $titleRaw);
$editorContentEscaped = $escape($editorContent);
$pdfRoute = json_encode(RUTA_IMAGES, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$pdfName = json_encode($patientNameRaw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$pdfTitle = json_encode($titleRaw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$title;?></title>
    <link rel="shortcut icon" href="<?=RUTA_IMAGES;?>logo-clinica.png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="<?=RUTA_JS;?>editor-functions.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>
</head>

<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-summary-page doctor-summary-aq-page">
    <div class="LoaderPage"></div>
    <div id="app">
        <?=$data['sidebar'];?>

        <div id="main" data-rol="<?=$escape($data['idRol'] ?? '');?>" data-paciente="<?=$patientId;?>" data-tema="<?=$title;?>">
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php'; ?>

            <main class="main-content container-fluid doctor-summary-content">
                <header class="doctor-summary-hero" aria-labelledby="doctor-summary-title">
                    <div class="doctor-summary-hero-copy">
                        <p class="dashboard-eyebrow">RESUMEN CLÍNICO</p>
                        <h1 id="doctor-summary-title"><?=$title;?></h1>
                        <p>Consulta los antecedentes personales quirúrgicos registrados del paciente.</p>
                        <?php if ($patientNameRaw !== ''): ?>
                            <p class="doctor-summary-patient-context">Paciente autorizado: <strong><?=$patientName;?></strong></p>
                        <?php endif; ?>
                    </div>
                    <div class="doctor-summary-hero-actions">
                        <a class="doctor-summary-action doctor-summary-back-action" href="<?=$patientUrl;?>">
                            <i data-feather="arrow-left" aria-hidden="true"></i>
                            <span>Volver al expediente</span>
                        </a>
                        <button
                            class="doctor-summary-action doctor-summary-print-action"
                            type="button"
                            onclick="downloadPDF(<?=$pdfRoute;?>, <?=$pdfName;?>, <?=$pdfTitle;?>)"
                            aria-label="Imprimir o descargar antecedentes personales quirúrgicos"
                            title="Imprimir o descargar antecedentes personales quirúrgicos">
                            <i data-feather="printer" aria-hidden="true"></i>
                            <span>Imprimir resumen</span>
                        </button>
                    </div>
                </header>

                <section class="doctor-summary-sections" aria-label="Resumen de antecedentes personales quirúrgicos">
                    <article class="card doctor-summary-card doctor-summary-aq-card">
                        <header class="doctor-summary-card-header">
                            <div>
                                <p class="dashboard-eyebrow">ANTECEDENTES QUIRÚRGICOS</p>
                                <h2>Antecedentes personales quirúrgicos</h2>
                                <p>Cirugías, fechas y observaciones registradas en el expediente.</p>
                            </div>
                        </header>

                        <div class="card-body doctor-summary-card-body doctor-summary-aq-body">
                            <div id="contenido-fijo" class="doctor-summary-fixed-content doctor-summary-aq-content">
                                <p class="doctor-summary-intro"><strong>A continuación, indique si a su paciente se le han realizado una o más cirugías:</strong></p>
                                <?=$contenidoAntescedentesQR;?>
                            </div>
                        </div>
                    </article>

                    <article class="card doctor-summary-card doctor-summary-notes-card">
                        <header class="doctor-summary-card-header">
                            <div>
                                <p class="dashboard-eyebrow">SEGUIMIENTO</p>
                                <h2>Notas adicionales</h2>
                                <p>Consulta o actualiza las notas asociadas a este resumen clínico.</p>
                            </div>
                        </header>
                        <div class="card-body doctor-summary-notes-body">
                            <label class="doctor-summary-editor-label" for="editor">Notas adicionales</label>
                            <textarea id="editor" rows="20" cols="80"><?=$editorContentEscaped;?></textarea>
                            <div id="mensaje" class="doctor-summary-message" role="status" aria-live="polite"></div>
                        </div>
                        <footer class="card-footer doctor-summary-card-footer">
                            <button class="btn doctor-summary-save-action" type="button" onclick="guardarContenidoEditor()">
                                <i data-feather="save" aria-hidden="true"></i>
                                <span>Guardar cambios</span>
                            </button>
                        </footer>
                    </article>
                </section>
            </main>

            <?php include_once __DIR__ . '/../components/footer-doctor.php'; ?>
        </div>
    </div>

    <script src="<?=RUTA_JS;?>CKEditor-utilities.js"></script>
    <script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS;?>main.js"></script>
    <script src="<?=RUTA_JS;?>search-main.js"></script>
</body>
</html>
