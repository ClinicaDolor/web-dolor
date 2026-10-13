<?php 
use App\Config\Database;
use App\Models\PacienteModulosModelo;

$bd = Database::getInstance();
$model = new PacienteModulosModelo();
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

try {
    $stmt = $bd->query("SELECT * FROM historia_clinica_modulos ORDER BY id ASC");
    $modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener registros finalizados para el paciente específico.
    $id_paciente = $data['datos']['id_usuario'];
    $stmtFinalizados = $bd->prepare("SELECT id_modulo FROM pac_historia_clinica_finalizar WHERE id_paciente = ?");
    $stmtFinalizados->execute([$id_paciente]);
    $modulosFinalizados = $stmtFinalizados->fetchAll(PDO::FETCH_COLUMN);

    // La vista conserva las llamadas existentes al modelo; sólo prepara la
    // presentación para evitar consultas repetidas durante el renderizado.
    $moduleCards = [];
    $progressSum = 0;
    $completedCount = 0;

    foreach ($modulos as $modulo) {
        $moduleId = (int)$modulo['id'];
        $porcentajeTotal = match ($moduleId) {
            1 => $model->porcentajeModulo1($id_paciente),
            2 => $model->porcentajeModulo2($id_paciente),
            3 => $model->porcentajeModulo3($id_paciente),
            4 => $model->porcentajeModulo4($id_paciente),
            5 => $model->porcentajeModulo5($id_paciente),
            6 => $model->porcentajeModulo6($id_paciente),
            7 => $model->porcentajeModulo7($id_paciente),
            8 => $model->porcentajeModulo8($id_paciente),
            9 => $model->porcentajeModulo9($id_paciente),
            default => 0,
        };
        $porcentajeTotal = (int)max(0, min(100, round((float)$porcentajeTotal)));
        $deshabilitado = in_array($modulo['id'], $modulosFinalizados);
        $estado = $deshabilitado ? 'Completada' : ($porcentajeTotal > 0 ? 'Con avance' : 'Disponible');
        $moduleCards[] = [
            'data' => $modulo,
            'id' => $moduleId,
            'percentage' => $porcentajeTotal,
            'disabled' => $deshabilitado,
            'status' => $estado,
            'image' => basename((string)$modulo['imagen']),
        ];
        $progressSum += $porcentajeTotal;
        if ($deshabilitado) {
            $completedCount++;
        }
    }
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}

$moduleCount = count($moduleCards);
$overallProgress = $moduleCount > 0 ? (int)round($progressSum / $moduleCount) : 0;
$patientName = trim((string)($data['datos']['nombre'] ?? 'Paciente'));
$nextModule = null;
foreach ($moduleCards as $card) {
    if (!$card['disabled']) {
        $nextModule = $card;
        break;
    }
}
?>

    <!DOCTYPE html>
    <html lang="es-MX">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <title><?=$escape($data['title']);?></title>
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>
    <script src="<?=RUTA_JS;?>clean-data.js"></script>
    </head>

    <body class="app-shell-body app-shell-patient dashboard-patient">
    <div class="LoaderPage"></div>
    <div id="app">
        
    <!---------- SIDEBAR ---------->
    <?=$data['sidebar'];?>
    
    <div id="main">
            
    <nav class="navbar navbar-header navbar-expand navbar-light portal-topbar" aria-label="Barra de herramientas del portal del paciente">
    <button type="button" class="sidebar-toggler border-0 bg-transparent" aria-label="Abrir menú principal" aria-controls="sidebar" aria-expanded="true"><span class="navbar-toggler-icon"></span></button>
    <button class="btn navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
    </button>
                
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav d-flex align-items-center navbar-light ms-auto">
    <li class="nav-item portal-topbar-meta">
    <span class="portal-badge"><i data-feather="user" aria-hidden="true"></i> Portal del paciente</span>
    <span class="topbar-session-name"><?=$escape($data['datos']['nombre'] ?? 'Paciente');?></span>
    </li>
    </ul>
    </div>
    </nav>
        
    <!---------- CONTENIDO DE LA PAGINA ---------->
    <div class="main-content container-fluid patient-dashboard-content">

    <!--
    <div class="search float-end">
    <div class="search-input">
    <a href="" target="_blank" hidden></a>
    <input type="text" class="form-control round" placeholder="Buscar...">
    <div class="autocom-box"></div>
    <div class="icon"><i data-feather="search"></i></div>
    </div>
    </div>
    -->

    <header class="page-title portal-page-title patient-dashboard-hero mb-4">
    <div class="patient-dashboard-hero-copy">
    <p class="dashboard-eyebrow">MI HISTORIA CLÍNICA</p>
    <h1>Mi Historia Clínica</h1>
    <p class="text-muted mb-0">Completa tu información, paso a paso y a tu ritmo.</p>
    </div>
    <span class="patient-dashboard-patient-name">Paciente: <?=$escape($patientName);?></span>
    </header>

    <?php
    $nextModuleHref = $nextModule
        ? SERVIDOR.'historia-clinica/'.rawurlencode((string)$nextModule['data']['url']).'/paciente/'.(int)$id_paciente
        : null;
    $nextActionLabel = $nextModule && $nextModule['percentage'] > 0 ? 'Continuar' : 'Comenzar';
    ?>
    <section class="patient-progress-panel" aria-labelledby="patient-progress-title">
    <div class="patient-progress-main">
    <div>
    <p class="dashboard-eyebrow">TU PROGRESO</p>
    <h2 id="patient-progress-title">Avance de tu Historia Clínica</h2>
    <p class="patient-progress-help">Consulta cuánto has completado y continúa con la siguiente sección disponible.</p>
    </div>
    <strong class="patient-progress-percentage"><?=$overallProgress;?>%</strong>
    </div>
    <div class="progress patient-progress-bar" role="progressbar" aria-label="Progreso general de tu Historia Clínica" aria-valuenow="<?=$overallProgress;?>" aria-valuemin="0" aria-valuemax="100">
    <div class="progress-bar rounded-pill" style="width: <?=$overallProgress;?>%;"></div>
    </div>
    <div class="patient-progress-details">
    <div>
    <strong><?=$completedCount;?> <span>/ <?=$moduleCount;?></span></strong>
    <span>Secciones completadas</span>
    </div>
    <div>
    <strong><?=$nextModule ? $escape($nextModule['data']['nombre']) : 'Historia completa';?></strong>
    <span><?=$nextModule ? 'Siguiente sección' : 'Todas las secciones finalizadas';?></span>
    </div>
    </div>
    <?php if ($nextModule && $nextModuleHref): ?>
    <a class="btn btn-primary patient-progress-action" href="<?=$nextModuleHref;?>">
    <span><?=$nextActionLabel;?> mi siguiente sección</span><i data-feather="arrow-right" aria-hidden="true"></i>
    </a>
    <?php else: ?>
    <span class="patient-progress-complete"><i data-feather="check-circle" aria-hidden="true"></i> Historia Clínica completada</span>
    <?php endif; ?>
    </section>

    <section class="section patient-interview-entry" aria-labelledby="patient-experience-title">
    <div class="patient-modules-heading">
    <div>
    <p class="dashboard-eyebrow">ELIGE CÓMO CONTINUAR</p>
    <h2 id="patient-experience-title">Dos formas de completar tu Historia Clínica</h2>
    <p class="patient-modules-help mb-0">Ambas opciones utilizan el mismo expediente y tus respuestas guardadas.</p>
    </div>
    </div>
    <div class="patient-module-grid patient-experience-grid">
    <a class="patient-module-link" href="#patient-modules-title" aria-label="Abrir Historia Clínica Tradicional">
    <article class="card border-0 rounded-4 position-relative dashboard-card dashboard-module-card patient-module-card">
    <div class="patient-module-card-content"><div class="patient-module-copy"><h5>Historia Clínica Tradicional</h5><p>Continúa con los formularios actuales M01–M09.</p></div></div>
    <div class="patient-module-card-footer"><span>Abrir formularios</span><i data-feather="arrow-down" aria-hidden="true"></i></div>
    </article>
    </a>
    <a class="patient-module-link" href="<?=SERVIDOR;?>historia-clinica/entrevista" aria-label="Abrir Entrevista Conversacional">
    <article class="card border-0 rounded-4 position-relative dashboard-card dashboard-module-card patient-module-card">
    <div class="patient-module-card-content"><div class="patient-module-copy"><h5>Entrevista Conversacional</h5><p>Prueba el piloto determinista de M03/Tabaquismo.</p></div></div>
    <div class="patient-module-card-footer"><span>Iniciar entrevista</span><i data-feather="message-circle" aria-hidden="true"></i></div>
    </article>
    </a>
    </div>
    </section>
    
    <section class="section patient-modules-section" aria-labelledby="patient-modules-title">
    <div class="patient-modules-heading">
    <div>
    <p class="dashboard-eyebrow">HISTORIA CLÍNICA</p>
    <h2 id="patient-modules-title">Tus secciones</h2>
    <p class="patient-modules-help mb-0">Selecciona una sección para consultar o continuar.</p>
    </div>
    </div>

    <div class="patient-module-grid">
    <?php foreach ($moduleCards as $card):
    $modulo = $card['data'];
    $referenceCard = $card['disabled']
        ? ''
        : 'href="'.SERVIDOR.'historia-clinica/'.rawurlencode((string)$modulo['url']).'/paciente/'.(int)$id_paciente.'"';
    $classCard = $card['disabled'] ? 'card-mvsd-disabled' : 'card-mvsd';
    ?>
    <div class="patient-module-grid-item">
    <a <?=$referenceCard?> class="patient-module-link <?=$card['disabled'] ? 'is-disabled' : ''; ?>" aria-label="<?=$escape($modulo['nombre']);?><?=$card['disabled'] ? ', completada' : ', continuar';?>"<?=$card['disabled'] ? ' aria-disabled="true" tabindex="-1"' : '';?>>
    <article class="card border-0 rounded-4 position-relative <?=$classCard?> dashboard-card dashboard-module-card patient-module-card">
    <div class="patient-module-card-header">
    <span class="patient-module-index"><?=$card['id'];?></span>
    <span class="patient-module-status status-<?=strtolower(str_replace(' ', '-', $card['status']));?>"><?=$card['status'];?></span>
    </div>
    <div class="patient-module-card-content">
    <img src="<?=RUTA_IMAGES ?>iconos/<?=$escape($card['image']);?>" class="patient-module-image" alt="" aria-hidden="true">
    <div class="patient-module-copy">
    <h5><?=$escape($modulo['nombre']);?></h5>
    <p><?= $card['disabled'] ? 'Esta sección ya está registrada en tu expediente.' : 'Continúa cuando estés listo.'; ?></p>
    </div>
    </div>
    <div class="patient-module-progress">
    <div class="patient-module-progress-label"><span>Avance de la sección</span><strong><?=$card['percentage'];?>%</strong></div>
    <div class="progress rounded-pill" role="progressbar" aria-label="Avance de <?=$escape($modulo['nombre']);?>" aria-valuenow="<?=$card['percentage'];?>" aria-valuemin="0" aria-valuemax="100">
    <div class="progress-bar rounded-pill" style="width: <?=$card['percentage'];?>%;"></div>
    </div>
    </div>
    <div class="patient-module-card-footer">
    <span><?=$card['disabled'] ? 'Sección completada' : 'Continuar sección';?></span>
    <?php if (!$card['disabled']): ?><i data-feather="arrow-right" aria-hidden="true"></i><?php else: ?><i data-feather="check" aria-hidden="true"></i><?php endif; ?>
    </div>
    </article>
    </a>
    </div>
    <?php endforeach; ?>
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

    </body>
    </html>
