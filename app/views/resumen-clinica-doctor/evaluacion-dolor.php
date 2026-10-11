<?php
use App\Config\Database;
use App\Models\PacienteModulosModelo;
use App\Models\EvaluacionDolorModel;

$bd = Database::getInstance();
$model = new EvaluacionDolorModel();
$model2 = new PacienteModulosModelo();

// Se conserva el contrato actual: el modelo prepara la respuesta inicial
// durante la carga. Este efecto secundario de GET queda documentado y no se
// ejecuta contra datos reales en esta actividad visual.
$model->cuestionarioModulo9($data['idPaciente']);
$contenidoEvaluacionDolor = $model->editorTextoED($data['idPaciente']);

$escape = static function ($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$patientId = (int) ($data['idPaciente'] ?? 0);
$patientNameRaw = (string) ($data['nombre'] ?? '');
$titleRaw = (string) ($data['title'] ?? 'Evaluación del dolor');
$patientName = $escape($patientNameRaw);
$title = $escape($titleRaw);
$patientUrl = $escape(SERVIDOR . 'clinica/paciente/' . $patientId);
$editorContent = $model2->contenidoEditorPAC($patientId, $titleRaw);
$editorContentEscaped = $escape($editorContent);
$pdfRoute = json_encode(RUTA_IMAGES, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$pdfName = json_encode($patientNameRaw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$pdfTitle = json_encode($titleRaw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$sexRaw = (string) ($data['sexo'] ?? '');
$frontPath = RUTA_IMAGES . "evaluacion-dolor/frente/Frente_{$sexRaw}_{$patientId}.png";
$frontFile = $_SERVER['DOCUMENT_ROOT'] . parse_url($frontPath, PHP_URL_PATH);
$frontSrc = file_exists($frontFile)
    ? $frontPath
    : RUTA_IMAGES . "evaluacion-dolor/frente/Frente_{$sexRaw}.png";
$backPath = RUTA_IMAGES . "evaluacion-dolor/espalda/Espalda{$sexRaw}_{$patientId}.png";
$backFile = $_SERVER['DOCUMENT_ROOT'] . parse_url($backPath, PHP_URL_PATH);
$backSrc = file_exists($backFile)
    ? $backPath
    : RUTA_IMAGES . "evaluacion-dolor/espalda/Espalda_{$sexRaw}.png";
$frontSrcEscaped = $escape($frontSrc);
$backSrcEscaped = $escape($backSrc);
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

<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-summary-page doctor-summary-ed-page">
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
                        <p>Consulta las respuestas y zonas de dolor registradas para el paciente.</p>
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
                            aria-label="Imprimir o descargar evaluación del dolor"
                            title="Imprimir o descargar evaluación del dolor">
                            <i data-feather="printer" aria-hidden="true"></i>
                            <span>Imprimir resumen</span>
                        </button>
                    </div>
                </header>

                <section class="doctor-summary-sections" aria-label="Resumen de evaluación del dolor">
                    <article class="card doctor-summary-card doctor-summary-ed-card">
                        <header class="doctor-summary-card-header">
                            <div>
                                <p class="dashboard-eyebrow">EVALUACIÓN DEL DOLOR</p>
                                <h2>Respuestas registradas</h2>
                                <p>Consulta la localización, características y evolución del dolor reportado.</p>
                            </div>
                        </header>

                        <div class="card-body doctor-summary-card-body doctor-summary-ed-body">
                            <div id="contenido-fijo" class="doctor-summary-fixed-content doctor-summary-ed-content">
                                <section class="doctor-summary-ed-guidance" aria-labelledby="doctor-summary-ed-guidance-title">
                                    <h3 id="doctor-summary-ed-guidance-title">Mapa de zonas de dolor</h3>
                                    <p>Las imágenes muestran las zonas marcadas durante la evaluación previa.</p>
                                    <ul>
                                        <li><span aria-hidden="true">🟥</span> Rojo: área con dolor.</li>
                                        <li><span aria-hidden="true">🟦</span> Azul: área sin sensibilidad y sin dolor.</li>
                                        <li><span aria-hidden="true">🟩</span> Verde: molestia al tacto o roce de la ropa.</li>
                                        <li><span aria-hidden="true">🟨</span> Amarillo: área dormida y con dolor.</li>
                                        <li><span aria-hidden="true">🟪</span> Morado: punzadas y calambres.</li>
                                    </ul>
                                    <p>La zona se cubre por completo con el color correspondiente; no se utiliza una «X».</p>
                                </section>

                                <div class="doctor-summary-ed-images" aria-label="Mapas de zonas de dolor">
                                    <figure class="doctor-summary-ed-image-card">
                                        <img src="<?=$frontSrcEscaped;?>" alt="Mapa frontal de zonas de dolor">
                                        <figcaption>Imagen de frente</figcaption>
                                    </figure>
                                    <figure class="doctor-summary-ed-image-card">
                                        <img src="<?=$backSrcEscaped;?>" alt="Mapa posterior de zonas de dolor">
                                        <figcaption>Imagen de espalda</figcaption>
                                    </figure>
                                </div>

                                <section class="doctor-summary-ed-answers" aria-labelledby="doctor-summary-ed-answers-title">
                                    <div class="doctor-summary-ed-section-heading">
                                        <p class="dashboard-eyebrow">RESPUESTAS</p>
                                        <h3 id="doctor-summary-ed-answers-title">Descripción de la evaluación</h3>
                                        <p>Respuestas registradas sobre el inicio, evolución y características del dolor.</p>
                                    </div>
                                    <?=$contenidoEvaluacionDolor;?>
                                </section>
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
