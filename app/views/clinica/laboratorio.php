<?php
$nombrePacienteSeguro = htmlspecialchars((string)($data['nombre_paciente'] ?? ''), ENT_QUOTES, 'UTF-8');
$nombrePacienteVisible = $nombrePacienteSeguro !== '' ? $nombrePacienteSeguro : 'Paciente';
$volverLaboratorioUrl = htmlspecialchars(SERVIDOR . 'clinica/laboratorio/paciente/' . (int)($data['id_paciente'] ?? 0), ENT_QUOTES, 'UTF-8');
$nombreArchivoSeguro = htmlspecialchars((string)($data['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
$descargaUrl = htmlspecialchars('../../descargar/' . (string)($data['ruta'] ?? '') . '/' . (string)($data['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
$estadoCivilSeguro = htmlspecialchars((string)($data['estado_civil'] ?? ''), ENT_QUOTES, 'UTF-8');
$curpSeguro = htmlspecialchars((string)($data['curp'] ?? ''), ENT_QUOTES, 'UTF-8');
$emailSeguro = htmlspecialchars((string)($data['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$telefonoSeguro = htmlspecialchars((string)($data['telefono'] ?? ''), ENT_QUOTES, 'UTF-8');
$celularSeguro = htmlspecialchars((string)($data['celular'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <title><?=htmlspecialchars((string)$data['title'], ENT_QUOTES, 'UTF-8');?></title>
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>

 </head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-lab-detail-page">
<div class="LoaderPage"></div>
    <div id="app">
        
        <?=$data['sidebar'];?>

        <div id="main">
            
            <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
            
            <div class="main-content container-fluid">
            <header class="doctor-page-hero doctor-lab-detail-hero">
                <div class="doctor-page-hero-copy">
                    <p class="dashboard-eyebrow">Resultado de laboratorio</p>
                    <h1><?=$nombrePacienteVisible;?></h1>
                    <p>Consulta el resultado de laboratorio y el documento asociado.</p>
                </div>
                <div class="doctor-page-hero-actions">
                    <a class="btn doctor-page-hero-action" href="<?=$volverLaboratorioUrl;?>">
                        <i data-feather="arrow-left" aria-hidden="true"></i>
                        <span>Volver a laboratorios</span>
                    </a>
                </div>
            </header>

            <section class="doctor-lab-detail-content">
            <div class="row doctor-lab-detail-grid">
                <div class="col-12 col-xl-5">
                    <article class="card doctor-lab-detail-card doctor-lab-patient-card">
                        <div class="card-header">
                            <div>
                                <p class="dashboard-eyebrow">Contexto clínico</p>
                                <h2 class="card-title">Información del paciente</h2>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="doctor-lab-patient-primary">
                                <span class="doctor-lab-field-label">Nombre del paciente</span>
                                <strong><?=$nombrePacienteVisible;?></strong>
                            </div>

                            <div class="doctor-lab-field-grid">
                                <div class="doctor-lab-field">
                                    <span class="doctor-lab-field-label">Fecha de alta</span>
                                    <span><?=(new DateTime($data['fecha_alta']))->format('d/m/Y');[0];?></span>
                                </div>
                                <div class="doctor-lab-field">
                                    <span class="doctor-lab-field-label">Fecha de nacimiento</span>
                                    <span><?=date("d/m/Y", strtotime($data['fecha_nacimiento']));?></span>
                                </div>
                                <div class="doctor-lab-field">
                                    <span class="doctor-lab-field-label">Edad</span>
                                    <span><?=htmlspecialchars((string)$data['edad'], ENT_QUOTES, 'UTF-8');?> años</span>
                                </div>
                                <div class="doctor-lab-field">
                                    <span class="doctor-lab-field-label">Sexo</span>
                                    <span><?=($data['sexo'] == 'M') ? 'Masculino' : 'Femenino';?></span>
                                </div>
                                <div class="doctor-lab-field">
                                    <span class="doctor-lab-field-label">Estado civil</span>
                                    <span><?=$estadoCivilSeguro;?></span>
                                </div>
                                <div class="doctor-lab-field">
                                    <span class="doctor-lab-field-label">CURP</span>
                                    <span><?=$curpSeguro;?></span>
                                </div>
                            </div>

                            <div class="doctor-lab-contact-block">
                                <p class="dashboard-eyebrow">Contacto</p>
                                <div class="doctor-lab-field-grid doctor-lab-contact-grid">
                                    <div class="doctor-lab-field">
                                        <span class="doctor-lab-field-label">Email</span>
                                        <span><?=$emailSeguro;?></span>
                                    </div>
                                    <div class="doctor-lab-field">
                                        <span class="doctor-lab-field-label">Teléfono</span>
                                        <span><?=$telefonoSeguro;?></span>
                                    </div>
                                    <div class="doctor-lab-field">
                                        <span class="doctor-lab-field-label">Celular</span>
                                        <span><?=$celularSeguro;?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-xl-7">
                    <article class="card doctor-lab-detail-card doctor-lab-result-card">
                        <div class="card-header doctor-lab-result-header">
                            <div>
                                <p class="dashboard-eyebrow">Documento clínico</p>
                                <h2 class="card-title">Detalle del laboratorio</h2>
                            </div>
                            <a download="<?=$nombreArchivoSeguro;?>" href="<?=$descargaUrl;?>" class="btn doctor-lab-download" aria-label="Descargar documento de laboratorio">
                                <i data-feather="download" aria-hidden="true"></i>
                                <span>Descargar</span>
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="doctor-lab-result-meta">
                                <div>
                                    <span class="doctor-lab-field-label">Fecha</span>
                                    <strong><?=$data['fecha_laboratorio'];?></strong>
                                </div>
                                <div>
                                    <span class="doctor-lab-field-label">Hora</span>
                                    <strong><?=$data['hora_laboratorio'];?></strong>
                                </div>
                            </div>

                            <div class="doctor-lab-description-block">
                                <span class="doctor-lab-field-label">Descripción</span>
                                <div class="doctor-lab-description"><?=$data['descripcion'];?></div>
                            </div>
                        </div>
                    </article>
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
    <script src="<?=RUTA_JS?>search-main.js"></script>
    
</body>
</html>
