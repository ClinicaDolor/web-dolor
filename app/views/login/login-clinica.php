<?php $authRole = 'doctor'; ?>
<!DOCTYPE html>
<html lang="es-MX">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso médico | Clínica del Dolor</title>
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="shortcut icon" href="<?=RUTA_IMAGES;?>logo-clinica.png" type="image/png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="auth-page auth-page-doctor">
    <main id="auth" class="auth-shell">
        <div class="auth-shell-inner">
            <?php include __DIR__ . '/../components/auth-visual.php'; ?>

            <section class="auth-form-panel" aria-labelledby="doctor-login-title">
                <div class="auth-form-card">
                    <div class="auth-card-header">
                        <span class="auth-kicker">Portal médico</span>
                        <h2 id="doctor-login-title">Iniciar sesión</h2>
                        <p>Ingresa tus credenciales para continuar.</p>
                    </div>

                    <div class="auth-field">
                        <label for="usuario">Usuario</label>
                        <div class="auth-input-wrap">
                            <?php $authIcon = 'user'; include __DIR__ . '/../components/auth-icon.php'; ?>
                            <input type="text" class="form-control" id="usuario" autocomplete="username" placeholder="Ingresa tu usuario" aria-describedby="responseMessage" aria-invalid="false">
                        </div>
                    </div>

                    <div class="auth-field">
                        <div class="auth-label-row">
                            <label for="password">Contraseña</label>
                            <a href="" class="auth-recovery">¿Olvidaste tu contraseña?</a>
                        </div>
                        <div class="auth-input-wrap">
                            <?php $authIcon = 'lock'; include __DIR__ . '/../components/auth-icon.php'; ?>
                            <input type="password" class="form-control" id="password" autocomplete="current-password" placeholder="Ingresa tu contraseña" aria-describedby="responseMessage" aria-invalid="false">
                        </div>
                    </div>

                    <div class="auth-response" id="responseMessage" role="alert" aria-live="assertive" aria-atomic="true" aria-hidden="true" hidden></div>

                    <button type="button" class="btn btn-primary auth-submit" onclick="loginClinica()">
                        <span>Entrar</span><?php $authIcon = 'arrow-right'; include __DIR__ . '/../components/auth-icon.php'; ?>
                    </button>

                    <div class="auth-assurance"><?php $authIcon = 'shield'; include __DIR__ . '/../components/auth-icon.php'; ?><span>Acceso seguro y confidencial</span></div>

                    <nav class="auth-portal-switch" aria-label="Cambiar de portal">
                        <p>¿Eres paciente?</p>
                        <a class="auth-portal-link" href="<?=SERVIDOR;?>historia-clinica/acceso">
                            <span>Acceder al portal de pacientes</span><?php $authIcon = 'arrow-right'; include __DIR__ . '/../components/auth-icon.php'; ?>
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
