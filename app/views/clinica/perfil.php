    <?php
    use App\Config\Database;
    $bd = Database::getInstance();

    $query = "SELECT * FROM clinicas WHERE id = '".$data['idClinica']."' ";
    $stmt = $bd->prepare($query);
    $stmt->execute();
    $registros = $stmt->fetch(\PDO::FETCH_ASSOC);

    $fecha_creacion = (new \DateTime($registros['fecha_creacion']))->format('d/m/Y');

    $nombre_clinica = $registros['nombre'];
    $direccion = $registros['direccion'];
    $telefono = $registros['telefono'];
    $email = $registros['email'];
    $sitio_web = $registros['sitio_web'];
    $horarios = $registros['horario'];
    $escape = static function ($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    $horarios = trim((string) $horarios, "[]");
    $horarios = $horarios === '' ? [] : array_map('trim', explode(',', $horarios));
    ?>
    <!DOCTYPE html>
    <html lang="en">
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

<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-profile-page">
    <div class="LoaderPage"></div>
    <div id="app">
    <?=$data['sidebar'];?>
    
    <div id="main">
    <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
    <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
 
    <main class="main-content container-fluid doctor-profile-content">
    <header class="doctor-page-hero doctor-profile-hero">
        <div class="doctor-page-hero-copy">
            <p class="dashboard-eyebrow">PERFIL INSTITUCIONAL</p>
            <h1><?=$escape($nombre_clinica);?></h1>
            <p>Consulta la información institucional y los datos de contacto de la clínica.</p>
        </div>
        <div class="doctor-page-hero-actions doctor-profile-hero-actions">
            <a href="<?=SERVIDOR?>clinica/pacientes" class="btn doctor-page-hero-action" data-doctor-back-action>
                <i data-feather="arrow-left" aria-hidden="true"></i>
                <span>Volver a pacientes</span>
            </a>
        </div>
    </header>

    <section class="doctor-profile-section" aria-labelledby="doctor-profile-title">
        <div class="doctor-profile-grid">
            <article class="card doctor-profile-card doctor-profile-contact-card">
                <div class="card-header doctor-profile-card-header">
                    <div>
                        <p class="dashboard-eyebrow">DATOS INSTITUCIONALES</p>
                        <h2 id="doctor-profile-title" class="card-title">Información de la clínica</h2>
                    </div>
                </div>
                <div class="card-body doctor-profile-card-body">
                    <div class="doctor-profile-field doctor-profile-field-wide">
                        <span class="doctor-profile-label">Dirección completa</span>
                        <p class="doctor-profile-value"><?=$escape($direccion);?></p>
                    </div>
                    <div class="doctor-profile-field-grid">
                        <div class="doctor-profile-field">
                            <span class="doctor-profile-label">Teléfono</span>
                            <p class="doctor-profile-value"><?=$escape($telefono);?></p>
                        </div>
                        <div class="doctor-profile-field">
                            <span class="doctor-profile-label">Correo electrónico</span>
                            <p class="doctor-profile-value"><?=$escape($email);?></p>
                        </div>
                        <div class="doctor-profile-field doctor-profile-field-wide">
                            <span class="doctor-profile-label">Sitio web</span>
                            <p class="doctor-profile-value"><?=$escape($sitio_web);?></p>
                        </div>
                    </div>
                </div>
            </article>

            <article class="card doctor-profile-card doctor-profile-schedule-card">
                <div class="card-header doctor-profile-card-header">
                    <div>
                        <p class="dashboard-eyebrow">ATENCIÓN</p>
                        <h2 class="card-title">Horarios de atención</h2>
                    </div>
                </div>
                <div class="card-body doctor-profile-card-body">
                    <?php if ($horarios === []): ?>
                        <p class="doctor-profile-empty" role="status">No hay horarios de atención registrados.</p>
                    <?php else: ?>
                        <ul class="doctor-profile-schedule" aria-label="Horarios de atención">
                            <?php foreach ($horarios as $horario): ?>
                                <li class="doctor-profile-schedule-item"><?=$escape($horario);?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </article>
        </div>
    </section>
    </main>

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
