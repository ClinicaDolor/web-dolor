<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="es">
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

 </head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-cofepris-detail-page">
<div class="LoaderPage"></div>
    <div id="app">
        
        <?=$data['sidebar'];?>

        <div id="main">
            
            <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
            
            <main class="main-content container-fluid doctor-cofepris-detail-content">
            <header class="doctor-page-hero doctor-cofepris-detail-hero">
                <div class="doctor-page-hero-copy">
                    <p class="dashboard-eyebrow">DETALLE COFEPRIS</p>
                    <h1>Registro COFEPRIS</h1>
                    <p>Consulta la información autorizada del registro y su contexto clínico.</p>
                    <p class="doctor-cofepris-detail-context">Paciente: <strong><?=$escape($data['nombre_paciente']);?></strong></p>
                </div>
                <div class="doctor-page-hero-actions">
                    <a target="_blank" rel="noopener" href="/pdf/cofepris/<?=$escape($data['id_cofepris']);?>" class="btn doctor-cofepris-detail-print" aria-label="Imprimir registro COFEPRIS" title="Imprimir registro COFEPRIS">
                        <i data-feather="printer" aria-hidden="true"></i>
                        <span>Imprimir registro</span>
                    </a>
                </div>
            </header>

            <section class="doctor-cofepris-detail-section" aria-label="Información del registro COFEPRIS">
            <div class="row doctor-cofepris-detail-grid">
                <div class="col-12 col-xl-5">
                    <article class="card doctor-cofepris-detail-card doctor-cofepris-patient-card">
                        <div class="card-header doctor-cofepris-detail-card-header">
                            <div>
                                <p class="dashboard-eyebrow">CONTEXTO CLÍNICO</p>
                                <h2 class="card-title">Información del paciente</h2>
                            </div>
                        </div>
                        <div class="card-body doctor-cofepris-detail-card-body">
                            <div class="doctor-cofepris-patient-name"><?=$escape($data['nombre_paciente']);?></div>
                            <dl class="doctor-cofepris-data-grid">
                                <div><dt>Fecha de alta</dt><dd><?=(new DateTime($data['fecha_alta']))->format('d/m/Y');?></dd></div>
                                <div><dt>Fecha de nacimiento</dt><dd><?=date('d/m/Y', strtotime($data['fecha_nacimiento']));?></dd></div>
                                <div><dt>Edad</dt><dd><?=$escape($data['edad']);?> años</dd></div>
                                <div><dt>Sexo</dt><dd><?=($data['sexo'] == 'M') ? 'Masculino' : 'Femenino';?></dd></div>
                                <div><dt>Estado civil</dt><dd><?=$escape($data['estado_civil']);?></dd></div>
                                <div><dt>CURP</dt><dd><?=$escape($data['curp']);?></dd></div>
                            </dl>
                            <div class="doctor-cofepris-contact-block">
                                <h3>Contacto del paciente</h3>
                                <dl class="doctor-cofepris-data-grid doctor-cofepris-contact-grid">
                                    <div><dt>Email</dt><dd><?=$escape($data['email']);?></dd></div>
                                    <div><dt>Teléfono</dt><dd><?=$escape($data['telefono']);?></dd></div>
                                    <div><dt>Celular</dt><dd><?=$escape($data['celular']);?></dd></div>
                                </dl>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-xl-7">
                    <article class="card doctor-cofepris-detail-card doctor-cofepris-record-card">
                        <div class="card-header doctor-cofepris-detail-card-header">
                            <div>
                                <p class="dashboard-eyebrow">REGISTRO CLÍNICO</p>
                                <h2 class="card-title">Detalle del registro</h2>
                            </div>
                            <span class="doctor-cofepris-record-badge">Folio <?=$escape($data['folio']);?></span>
                        </div>
                        <div class="card-body doctor-cofepris-detail-card-body">
                            <div class="doctor-cofepris-record-meta">
                                <div><span>Fecha</span><strong><?=$escape($data['fecha']);?></strong></div>
                                <div><span>Hora</span><strong><?=$escape($data['hora']);?></strong></div>
                            </div>
                            <dl class="doctor-cofepris-record-grid">
                                <div><dt>Diagnóstico</dt><dd><?=$escape($data['diagnostico']);?></dd></div>
                                <div><dt>Medicamento</dt><dd><?=$escape($data['medicamento']);?></dd></div>
                                <div><dt>Número de cajas</dt><dd><?=$escape($data['numcajas']);?></dd></div>
                                <div><dt>Presentación</dt><dd><?=$escape($data['presentacion']);?></dd></div>
                                <div><dt>Dosificación</dt><dd><?=$escape($data['dosificacion']);?></dd></div>
                                <div><dt>No. de días de prescripción</dt><dd><?=$escape($data['numdias']);?></dd></div>
                                <div><dt>Vía de administración</dt><dd><?=$escape($data['viaadministracion']);?></dd></div>
                            </dl>
                        </div>
                    </article>
                </div>
            </div>
            </section>
            </main>

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

