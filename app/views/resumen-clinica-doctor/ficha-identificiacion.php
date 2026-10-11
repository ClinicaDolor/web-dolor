<?php
use App\Config\Database;
use App\Models\PacienteModulosModelo;

$bd = Database::getInstance();
$model = new PacienteModulosModelo();

$escape = static function ($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$patientId = (int) ($data['idPaciente'] ?? 0);
$patientNameRaw = (string) ($data['nombre'] ?? '');
$titleRaw = (string) ($data['title'] ?? 'Ficha de identificación');
$patientName = $escape($patientNameRaw);
$title = $escape($titleRaw);
$patientUrl = $escape(SERVIDOR . 'clinica/paciente/' . $patientId);
$editorContent = $model->contenidoEditorPAC($patientId, $titleRaw);
$editorContentEscaped = $escape($editorContent);
$caregiverStatus = isset($data['cuidador'])
    ? ($data['cuidador'] ? 'Si' : 'No')
    : '';
$caregiverName = $escape($data['cuidador'] ?? '');
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

<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-summary-page doctor-summary-ficha-page">
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
                        <p>Consulta la ficha de identificación y las notas adicionales del paciente.</p>
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
                            aria-label="Imprimir o descargar ficha de identificación"
                            title="Imprimir o descargar ficha de identificación">
                            <i data-feather="printer" aria-hidden="true"></i>
                            <span>Imprimir resumen</span>
                        </button>
                    </div>
                </header>

                <section class="doctor-summary-sections" aria-label="Resumen de ficha de identificación">
                    <article class="card doctor-summary-card doctor-summary-identification-card">
                        <header class="doctor-summary-card-header">
                            <div>
                                <p class="dashboard-eyebrow">DATOS DEL PACIENTE</p>
                                <h2>Ficha de identificación</h2>
                                <p>Información personal, contacto y motivo de atención registrada.</p>
                            </div>
                        </header>

                        <div class="card-body doctor-summary-card-body">
                            <div id="contenido-fijo" class="doctor-summary-fixed-content">
                                <table class="table doctor-summary-table">
                                    <tbody>
                                        <tr>
                                            <td><strong>Nombre:</strong> <?=$patientName;?></td>
                                            <td><strong>Edad:</strong> <?=isset($data['edad']) ? $escape($data['edad']) . ' años' : 'Sin Información';?></td>
                                            <td><strong>Sexo:</strong> <?=$escape($data['sexo'] ?? 'Sin información');?></td>
                                            <td><strong>Estado civil:</strong> <?=$escape($data['estado_civil'] ?? 'Sin información');?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2"><strong>Fecha de nacimiento:</strong> <?=$escape($data['fecha_nacimiento'] ?? 'Sin información');?></td>
                                            <td colspan="2"><strong>CURP:</strong> <?=$escape($data['curp'] ?? 'Sin información');?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2"><strong>Lugar de origen:</strong> <?=$escape($data['lugar_origen'] ?? 'Sin información');?></td>
                                            <td colspan="2"><strong>Lugar de residencia:</strong> <?=$escape($data['lugar_residencia'] ?? 'Sin información');?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Ocupación:</strong> <?=$escape($data['ocupacion'] ?? 'Sin información');?></td>
                                            <td><strong>Número de hijos:</strong> <?=$escape($data['num_hijos'] ?? 'Sin información');?></td>
                                            <td colspan="2"><strong>Edad de sus hijos:</strong> <?=isset($data['edad_hijos']) ? $escape($data['edad_hijos']) : 'Sin Información';?></td>
                                        </tr>
                                        <tr class="doctor-summary-table-section">
                                            <th colspan="4">Referencia y motivo de atención</th>
                                        </tr>
                                        <tr>
                                            <td colspan="4"><strong>¿Quién lo recomienda o por qué medio se enteró de la Clínica del Dolor y Cuidados Paliativos?</strong></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4"><strong>Persona que lo recomendó:</strong> <?=isset($data['quien_recomienda']) ? $escape($data['quien_recomienda']) : 'Sin Información';?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4"><strong>Redes sociales:</strong> <?=$escape($data['redes_sociales'] ?? '');?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4"><strong>Motivo de atención en la Clínica del Dolor y Cuidados Paliativos:</strong></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4"><?=$escape($data['motivo_atencion'] ?? '');?></td>
                                        </tr>
                                        <tr class="doctor-summary-table-section">
                                            <th colspan="4">Domicilio y contacto</th>
                                        </tr>
                                        <tr>
                                            <td colspan="4"><strong>Dirección actual:</strong> <?=$escape($data['calle'] ?? '');?> <?=$escape($data['num_interior'] ?? '');?> <?=$escape($data['num_exterior'] ?? '');?> <?=$escape($data['colonia'] ?? '');?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3"><strong>Distancia a Clínica de Dolor Hospital Ángeles Lomas:</strong> <?=$escape($data['distancia'] ?? '');?></td>
                                            <td><strong>minutos / horas</strong></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4"><strong>Correo electrónico:</strong> <?=$escape($data['email'] ?? '');?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2"><strong>Tel. de casa:</strong> <?=$escape($data['telefono'] ?? '');?></td>
                                            <td colspan="2"><strong>Celular:</strong> <?=$escape($data['celular'] ?? '');?></td>
                                        </tr>
                                        <tr class="doctor-summary-table-section">
                                            <th colspan="4">Contacto de apoyo</th>
                                        </tr>
                                        <tr>
                                            <td><strong>¿Tiene cuidador(a)?</strong> <?=$caregiverStatus;?></td>
                                            <td colspan="2"><strong>Nombre:</strong> <?=$caregiverName;?></td>
                                            <td><strong>Teléfono:</strong> <?=$escape($data['cuidador_telefono'] ?? '');?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2"><strong>Nombre del familiar responsable:</strong> <?=$escape($data['res_nombre'] ?? '');?></td>
                                            <td colspan="2"><strong>Teléfono:</strong> <?=$escape($data['res_telefono'] ?? '');?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </article>

                    <article class="card doctor-summary-card doctor-summary-notes-card">
                        <header class="doctor-summary-card-header">
                            <div>
                                <p class="dashboard-eyebrow">SEGUIMIENTO</p>
                                <h2>Notas adicionales</h2>
                                <p>Consulta o actualiza las notas asociadas al resumen clínico.</p>
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
