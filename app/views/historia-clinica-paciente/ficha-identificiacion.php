<?php
use App\Config\Database;
use App\Models\PacienteModulosModelo;

$bd = Database::getInstance();
$model = new PacienteModulosModelo();
$botonFinalizar = $model->botonFinalizarModulo(1, $data['idPaciente'], $data['idRol']);
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$isBlank = static fn($value): bool => $value === null || (is_string($value) && trim($value) === '');
$display = static function ($value) use ($escape, $isBlank): string {
    return $isBlank($value)
        ? '<span class="patient-identification-empty">No registrado</span>'
        : $escape($value);
};
$sexo = $data['sexo'] ?? null;
$sexoDisplay = $isBlank($sexo) ? $display(null) : (($sexo === 'M') ? 'Masculino' : 'Femenino');
$cuidador = $data['cuidador'] ?? null;
$cuidadorDisplay = $isBlank($cuidador) ? $display(null) : ($cuidador ? 'Sí' : 'No');
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=$escape($data['title'] ?? 'Ficha de identificación');?></title>
<link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
<link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
<link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
<link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?=RUTA_JS;?>loader.js"></script>

<script>
//---------- CONTROL SERVER ----------
function gestionarFichaPaciente(url, parametros, callback) {
    $(".LoaderPage").show();
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(parametros)
    }).then(res => res.json()).then(data => {
        $(".LoaderPage").fadeOut(1000);
        if (data.resultado) callback();
        else document.getElementById('mensaje').textContent = 'Error: ' + data.mensaje;
    });
}

//---------- FINALIZAR MODULO DEL PACIENTE----------
function finalizarModuloPAC(idModulo, idPaciente) {
    gestionarFichaPaciente('/historia-clinica/paciente/finalizar-modulo-paciente', { idModulo, idPaciente }, () => window.location.href = '/historia-clinica');
}
</script>
</head>

<body class="app-shell-body app-shell-patient patient-clinical-form patient-identification-page">
<div class="LoaderPage"></div>
<div id="app">

<!---------- SIDEBAR ---------->
<?=$data['sidebar'];?>

<div id="main" data-rol="<?=$escape($data['idRol'] ?? '');?>" data-paciente="<?=$escape($data['idPaciente'] ?? '');?>">
<nav class="navbar navbar-header navbar-expand navbar-light portal-topbar" aria-label="Barra de herramientas del portal del paciente">
<button type="button" class="sidebar-toggler border-0 bg-transparent" aria-label="Abrir menú principal" aria-controls="sidebar" aria-expanded="true"><span class="navbar-toggler-icon"></span></button>
<button class="btn navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Mostrar navegación">
<span class="navbar-toggler-icon"></span>
</button>

<div class="collapse navbar-collapse" id="navbarSupportedContent">
<ul class="navbar-nav d-flex align-items-center navbar-light ms-auto">
<li class="nav-item portal-topbar-meta">
<span class="portal-badge"><i data-feather="user" aria-hidden="true"></i> Portal del paciente</span>
<span class="topbar-session-name"><?=$escape($data['nombre'] ?? 'Paciente');?></span>
</li>
</ul>
</div>
</nav>

<!---------- CONTENIDO DE LA PAGINA ---------->
<main class="main-content container-fluid patient-identification-content">

<header class="page-title portal-page-title patient-identification-hero mb-4">
<div class="patient-identification-hero-copy">
<p class="dashboard-eyebrow">MI HISTORIA CLÍNICA</p>
<h1>Ficha de identificación</h1>
<p>Revisa tus datos personales registrados en la clínica.</p>
</div>
<a class="btn btn-light patient-identification-back" href="<?=SERVIDOR;?>historia-clinica">
<i data-feather="arrow-left" aria-hidden="true"></i>
<span>Volver a mi Historia Clínica</span>
</a>
</header>

<div class="patient-identification-sections">
<section class="patient-identification-card" aria-labelledby="patient-identification-personal-title">
<header class="patient-identification-card-header">
<span class="patient-identification-card-icon" aria-hidden="true"><i data-feather="user"></i></span>
<div>
<p class="dashboard-eyebrow">DATOS PERSONALES</p>
<h2 id="patient-identification-personal-title">Información personal</h2>
</div>
</header>
<dl class="patient-identification-grid">
<div class="patient-identification-field patient-identification-field-wide">
<dt>Nombre completo</dt>
<dd><?=$display($data['nombre'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Edad</dt>
<dd><?=$display($data['edad'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Sexo</dt>
<dd><?=$sexoDisplay;?></dd>
</div>
<div class="patient-identification-field">
<dt>Estado civil</dt>
<dd><?=$display($data['estado_civil'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Fecha de nacimiento</dt>
<dd><?=$display($data['fecha_nacimiento'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>CURP</dt>
<dd class="patient-identification-long-value"><?=$display($data['curp'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Lugar de origen</dt>
<dd><?=$display($data['lugar_origen'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Lugar de residencia</dt>
<dd><?=$display($data['lugar_residencia'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Ocupación</dt>
<dd><?=$display($data['ocupacion'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Número de hijos</dt>
<dd><?=$display($data['num_hijos'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Edad de sus hijos</dt>
<dd><?=$display($data['edad_hijos'] ?? null);?></dd>
</div>
</dl>
</section>

<section class="patient-identification-card" aria-labelledby="patient-identification-address-title">
<header class="patient-identification-card-header">
<span class="patient-identification-card-icon" aria-hidden="true"><i data-feather="map-pin"></i></span>
<div>
<p class="dashboard-eyebrow">DOMICILIO</p>
<h2 id="patient-identification-address-title">Dirección actual</h2>
</div>
</header>
<dl class="patient-identification-grid">
<div class="patient-identification-field patient-identification-field-wide">
<dt>Calle</dt>
<dd><?=$display($data['calle'] ?? null);?></dd>
</div>
<div class="patient-identification-field patient-identification-field-small">
<dt>Número interior</dt>
<dd><?=$display($data['num_interior'] ?? null);?></dd>
</div>
<div class="patient-identification-field patient-identification-field-small">
<dt>Número exterior</dt>
<dd><?=$display($data['num_exterior'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Colonia</dt>
<dd><?=$display($data['colonia'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Delegación</dt>
<dd><?=$display($data['delegacion'] ?? null);?></dd>
</div>
<div class="patient-identification-field patient-identification-field-small">
<dt>Código postal</dt>
<dd><?=$display($data['cp'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Municipio</dt>
<dd><?=$display($data['municipio'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Distancia a la clínica</dt>
<dd><?php if ($isBlank($data['distancia'] ?? null)): ?><?=$display(null);?><?php else: ?><?=$escape($data['distancia']);?> <span class="patient-identification-unit">(Minutos / Horas)</span><?php endif;?></dd>
</div>
</dl>
</section>

<section class="patient-identification-card" aria-labelledby="patient-identification-contact-title">
<header class="patient-identification-card-header">
<span class="patient-identification-card-icon" aria-hidden="true"><i data-feather="phone"></i></span>
<div>
<p class="dashboard-eyebrow">CONTACTO Y RESPONSABLES</p>
<h2 id="patient-identification-contact-title">Contacto y responsables</h2>
</div>
</header>
<dl class="patient-identification-grid">
<div class="patient-identification-field patient-identification-field-wide">
<dt>Correo electrónico</dt>
<dd class="patient-identification-long-value"><?=$display($data['email'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Teléfono de casa</dt>
<dd><?=$display($data['telefono'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Celular</dt>
<dd><?=$display($data['celular'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>¿Tiene cuidador(a)?</dt>
<dd><?=$cuidadorDisplay;?></dd>
</div>
<div class="patient-identification-field patient-identification-field-wide">
<dt>Nombre del cuidador(a)</dt>
<dd><?=$display($data['cuidador'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Teléfono del cuidador(a)</dt>
<dd><?=$display($data['cuidador_telefono'] ?? null);?></dd>
</div>
<div class="patient-identification-field patient-identification-field-wide">
<dt>Nombre del familiar responsable</dt>
<dd><?=$display($data['res_nombre'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Teléfono del familiar responsable</dt>
<dd><?=$display($data['res_telefono'] ?? null);?></dd>
</div>
</dl>
</section>

<section class="patient-identification-card" aria-labelledby="patient-identification-context-title">
<header class="patient-identification-card-header">
<span class="patient-identification-card-icon" aria-hidden="true"><i data-feather="message-circle"></i></span>
<div>
<p class="dashboard-eyebrow">CONTEXTO DE ATENCIÓN</p>
<h2 id="patient-identification-context-title">Motivo y referencia</h2>
</div>
</header>
<dl class="patient-identification-grid">
<div class="patient-identification-field patient-identification-field-wide">
<dt>Persona que lo recomendó</dt>
<dd><?=$display($data['quien_recomienda'] ?? null);?></dd>
</div>
<div class="patient-identification-field">
<dt>Redes sociales</dt>
<dd><?=$display($data['redes_sociales'] ?? null);?></dd>
</div>
<div class="patient-identification-field patient-identification-field-full">
<dt>Motivo por el que solicita atención en la Clínica del Dolor y Cuidados Paliativos</dt>
<dd class="patient-identification-long-text"><?=$display($data['motivo_atencion'] ?? null);?></dd>
</div>
</dl>
</section>
</div>

<section class="patient-identification-finish" aria-labelledby="patient-identification-finish-title">
<div>
<p class="dashboard-eyebrow">SIGUIENTE PASO</p>
<h2 id="patient-identification-finish-title">Cuando hayas revisado tus datos</h2>
<p>Finaliza esta sección para continuar con tu Historia Clínica.</p>
</div>
<div class="patient-identification-finish-action">
<div id="mensaje" class="patient-identification-message" role="alert" aria-live="polite"></div>
<?=$botonFinalizar;?>
</div>
</section>
</main>

<!----- FOOTER ---------->
<?php include_once __DIR__ . '/../components/footer-doctor.php';?>
</div>
</div>

<script src="<?=RUTA_JS;?>voice-utilities.js"></script>
<script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="<?=RUTA_JS;?>app.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
<script src="<?=RUTA_JS;?>main.js"></script>
</body>
</html>
