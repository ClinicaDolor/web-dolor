<?php
$escape = static function ($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$data['datos'] = is_array($data['datos'] ?? null) ? $data['datos'] : [];
$patientId = (int) ($data['idPaciente'] ?? 0);
$patientName = $data['nombre'] ?? '';
$title = $data['title'] ?? 'Ficha de identificación';
$patientUrl = htmlspecialchars(SERVIDOR . 'clinica/paciente/' . $patientId, ENT_QUOTES, 'UTF-8');
$editUrl = htmlspecialchars(SERVIDOR . 'clinica/paciente/editar/' . $patientId, ENT_QUOTES, 'UTF-8');
$sex = isset($data['sexo']) && $data['sexo'] !== ''
    ? ($data['sexo'] === 'M' ? 'Masculino' : 'Femenino')
    : null;
$caregiver = isset($data['cuidador'])
    ? ($data['cuidador'] ? 'Sí' : 'No')
    : null;
$formatValue = static function ($value) use ($escape): string {
    if ($value === null || $value === '') {
        return '<span class="doctor-ficha-empty-value">No registrado</span>';
    }

    return $escape($value);
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="<?=RUTA_IMAGES?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES?>/logo-clinica.png">
    <title><?=$escape($title);?></title>
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>
</head>

<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-module-page doctor-ficha-identificacion-page">
    <div class="LoaderPage"></div>
    <div id="app">
        <?=$data['sidebar'];?>

        <div id="main" data-rol="<?=$escape($data['idRol'] ?? '');?>" data-paciente="<?=$patientId;?>">
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

            <main class="main-content container-fluid doctor-ficha-content">
                <header class="doctor-page-hero doctor-ficha-hero" aria-labelledby="doctor-ficha-title">
                    <div class="doctor-page-hero-copy">
                        <p class="dashboard-eyebrow">HISTORIA CLÍNICA</p>
                        <h1 id="doctor-ficha-title">Ficha de identificación</h1>
                        <p>Consulta y revisa la información de identificación del paciente.</p>
                        <p class="doctor-ficha-patient-context">Paciente autorizado: <strong><?=$escape($patientName);?></strong></p>
                    </div>
                    <div class="doctor-page-hero-actions doctor-ficha-hero-actions">
                        <a class="btn doctor-ficha-action doctor-ficha-action-secondary" href="<?=$patientUrl;?>">
                            <i data-feather="arrow-left" aria-hidden="true"></i>
                            <span>Volver al expediente</span>
                        </a>
                        <a class="btn doctor-ficha-action doctor-ficha-action-primary" href="<?=$editUrl;?>">
                            <i data-feather="edit-2" aria-hidden="true"></i>
                            <span>Editar paciente</span>
                        </a>
                    </div>
                </header>

                <section class="doctor-ficha-sections" aria-label="Información de identificación">
                    <article class="card doctor-ficha-card">
                        <header class="card-header doctor-ficha-card-header">
                            <div>
                                <p class="dashboard-eyebrow">DATOS PERSONALES</p>
                                <h2 class="card-title">Información de identificación</h2>
                            </div>
                        </header>
                        <div class="card-body doctor-ficha-card-body">
                            <dl class="doctor-ficha-grid">
                                <div class="doctor-ficha-field doctor-ficha-field-span-6">
                                    <dt>Nombre completo</dt>
                                    <dd><?=$formatValue($data['nombre'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-3">
                                    <dt>Edad</dt>
                                    <dd><?=$formatValue($data['edad'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-3">
                                    <dt>Sexo</dt>
                                    <dd><?=$formatValue($sex);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-4">
                                    <dt>Estado civil</dt>
                                    <dd><?=$formatValue($data['estado_civil'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-4">
                                    <dt>Fecha de nacimiento</dt>
                                    <dd><?=$formatValue($data['fecha_nacimiento'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-4">
                                    <dt>CURP</dt>
                                    <dd><?=$formatValue($data['curp'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-4">
                                    <dt>Lugar de origen</dt>
                                    <dd><?=$formatValue($data['lugar_origen'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-4">
                                    <dt>Lugar de residencia</dt>
                                    <dd><?=$formatValue($data['lugar_residencia'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-4">
                                    <dt>Ocupación</dt>
                                    <dd><?=$formatValue($data['ocupacion'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-4">
                                    <dt>Número de hijos</dt>
                                    <dd><?=$formatValue($data['num_hijos'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-4">
                                    <dt>Edad de sus hijos</dt>
                                    <dd><?=$formatValue($data['edad_hijos'] ?? null);?></dd>
                                </div>
                            </dl>
                        </div>
                    </article>

                    <article class="card doctor-ficha-card">
                        <header class="card-header doctor-ficha-card-header">
                            <div>
                                <p class="dashboard-eyebrow">CONTEXTO DE ATENCIÓN</p>
                                <h2 class="card-title">Referencia y motivo</h2>
                            </div>
                        </header>
                        <div class="card-body doctor-ficha-card-body">
                            <dl class="doctor-ficha-grid">
                                <div class="doctor-ficha-field doctor-ficha-field-span-6">
                                    <dt>Persona que lo recomendó</dt>
                                    <dd><?=$formatValue($data['quien_recomienda'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-6">
                                    <dt>Redes sociales</dt>
                                    <dd><?=$formatValue($data['redes_sociales'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-12">
                                    <dt>Motivo por el que solicita atención en la Clínica del Dolor y Cuidados Paliativos</dt>
                                    <dd class="doctor-ficha-value-long"><?=$formatValue($data['motivo_atencion'] ?? null);?></dd>
                                </div>
                            </dl>
                        </div>
                    </article>

                    <article class="card doctor-ficha-card">
                        <header class="card-header doctor-ficha-card-header">
                            <div>
                                <p class="dashboard-eyebrow">DOMICILIO</p>
                                <h2 class="card-title">Dirección actual</h2>
                            </div>
                        </header>
                        <div class="card-body doctor-ficha-card-body">
                            <dl class="doctor-ficha-grid">
                                <div class="doctor-ficha-field doctor-ficha-field-span-8">
                                    <dt>Calle</dt>
                                    <dd><?=$formatValue($data['calle'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-2">
                                    <dt>Número interior</dt>
                                    <dd><?=$formatValue($data['num_interior'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-2">
                                    <dt>Número exterior</dt>
                                    <dd><?=$formatValue($data['num_exterior'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-5">
                                    <dt>Colonia</dt>
                                    <dd><?=$formatValue($data['colonia'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-5">
                                    <dt>Delegación</dt>
                                    <dd><?=$formatValue($data['delegacion'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-2">
                                    <dt>Código postal</dt>
                                    <dd><?=$formatValue($data['cp'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-6">
                                    <dt>Municipio</dt>
                                    <dd><?=$formatValue($data['municipio'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-6">
                                    <dt>Distancia a la Clínica del Dolor</dt>
                                    <dd><?=$formatValue($data['distancia'] ?? null);?> <span class="doctor-ficha-unit">(minutos / horas)</span></dd>
                                </div>
                            </dl>
                        </div>
                    </article>

                    <article class="card doctor-ficha-card">
                        <header class="card-header doctor-ficha-card-header">
                            <div>
                                <p class="dashboard-eyebrow">CONTACTO</p>
                                <h2 class="card-title">Contacto y responsable</h2>
                            </div>
                        </header>
                        <div class="card-body doctor-ficha-card-body">
                            <dl class="doctor-ficha-grid">
                                <div class="doctor-ficha-field doctor-ficha-field-span-4">
                                    <dt>Correo electrónico</dt>
                                    <dd><?=$formatValue($data['email'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-4">
                                    <dt>Teléfono de casa</dt>
                                    <dd><?=$formatValue($data['telefono'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-4">
                                    <dt>Celular</dt>
                                    <dd><?=$formatValue($data['celular'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-3">
                                    <dt>¿Tiene cuidador(a)?</dt>
                                    <dd><?=$formatValue($caregiver);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-5">
                                    <dt>Nombre del cuidador</dt>
                                    <dd><?=$formatValue($data['cuidador'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-4">
                                    <dt>Teléfono del cuidador</dt>
                                    <dd><?=$formatValue($data['cuidador_telefono'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-6">
                                    <dt>Nombre del familiar responsable</dt>
                                    <dd><?=$formatValue($data['res_nombre'] ?? null);?></dd>
                                </div>
                                <div class="doctor-ficha-field doctor-ficha-field-span-6">
                                    <dt>Teléfono del familiar responsable</dt>
                                    <dd><?=$formatValue($data['res_telefono'] ?? null);?></dd>
                                </div>
                            </dl>
                        </div>
                    </article>
                </section>
            </main>
        </div>

        <?php include_once __DIR__ . '/../components/footer-doctor.php';?>
    </div>

    <script src="<?=RUTA_JS;?>voice-utilities.js"></script>
    <script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS;?>main.js"></script>
    <script src="<?=RUTA_JS;?>search-main.js"></script>
</body>
</html>
