<?php $authRole = 'patient'; ?>
<!DOCTYPE html>
<html lang="es-MX">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso paciente | Clínica del Dolor</title>
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    
    <link rel="shortcut icon" href="<?=RUTA_IMAGES;?>logo-clinica.png" type="image/png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
</head>

<body class="auth-page auth-page-patient">
    <main id="auth" class="auth-shell">
        <div class="auth-shell-inner">
            <?php include __DIR__ . '/../components/auth-visual.php'; ?>

            <section class="auth-form-panel" aria-labelledby="patient-login-title">
                <div class="auth-form-card">
                    <div class="auth-card-header">
                        <span class="auth-kicker">Portal de pacientes</span>
                        <h2 id="patient-login-title">Ingresa tu PIN</h2>
                        <p>Tu información está protegida.</p>
                    </div>

                    <div class="auth-field">
                        <label for="Pin">PIN de acceso</label>
                        <div class="auth-input-wrap">
                            <?php $authIcon = 'lock'; include __DIR__ . '/../components/auth-icon.php'; ?>
                            <input type="password" class="form-control" id="Pin" autocomplete="one-time-code" inputmode="numeric" placeholder="Ingresa tu PIN" aria-describedby="responseMessage" aria-invalid="false">
                        </div>
                    </div>

                    <div class="auth-response" id="responseMessage" role="alert" aria-live="assertive" aria-atomic="true" aria-hidden="true" hidden></div>

                    <button type="button" class="btn btn-primary auth-submit" onclick="loginPaciente()">
                        <span>Entrar</span><?php $authIcon = 'arrow-right'; include __DIR__ . '/../components/auth-icon.php'; ?>
                    </button>

                    <div class="auth-assurance"><?php $authIcon = 'shield'; include __DIR__ . '/../components/auth-icon.php'; ?><span>Acceso seguro para pacientes</span></div>

                    <nav class="auth-portal-switch" aria-label="Cambiar de portal">
                        <p>¿Eres médico?</p>
                        <a class="auth-portal-link" href="<?=SERVIDOR;?>clinica/acceso">
                            <span>Acceder al portal médico</span><?php $authIcon = 'arrow-right'; include __DIR__ . '/../components/auth-icon.php'; ?>
                        </a>
                    </nav>
                </div>
            </section>
        </div>
    </main>
    <script src="<?=RUTA_JS;?>feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>    
    <script src="<?=RUTA_JS;?>main.js"></script>
    <script>if (window.feather) { window.feather.replace(); }</script>
    <script src="<?=RUTA_JS;?>login.js"></script>
</body>

</html>
