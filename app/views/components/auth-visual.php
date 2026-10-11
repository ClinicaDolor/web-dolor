<?php
$isPatient = ($authRole ?? 'doctor') === 'patient';
$roleLabel = $isPatient ? 'Portal de pacientes' : 'Portal médico';
$headline = $isPatient ? "Bienvenido\nPaciente" : "Bienvenido\nDr. / Dra.";
$description = $isPatient
    ? 'Consulta tu historia clínica, da seguimiento a tu atención y mantente en contacto con nuestro equipo de salud.'
    : 'Accede a tu espacio clínico para gestionar pacientes, consultar expedientes y dar seguimiento a su atención.';
$features = $isPatient
    ? [
        ['icon' => 'file-text', 'label' => 'Consulta tu historia clínica'],
        ['icon' => 'activity', 'label' => 'Da seguimiento a tu atención'],
        ['icon' => 'shield', 'label' => 'Acceso seguro y confidencial'],
    ]
    : [
        ['icon' => 'users', 'label' => 'Gestión de pacientes'],
        ['icon' => 'file-text', 'label' => 'Expedientes clínicos'],
        ['icon' => 'shield', 'label' => 'Trabajo seguro y confidencial'],
    ];
?>

<section class="auth-visual" aria-label="<?=htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8');?>">
    <div class="auth-visual-overlay"></div>
    <div class="auth-brand-lockup">
        <img src="<?=RUTA_IMAGES;?>logo-clinica.png" alt="Clínica del Dolor y Cuidados Paliativos">
    </div>

    <div class="auth-visual-content">
        <div class="auth-role-label">
            <?php $authIcon = $isPatient ? 'user' : 'stethoscope'; include __DIR__ . '/auth-icon.php'; ?>
            <span><?=htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8');?></span>
        </div>
        <h1><?=nl2br(htmlspecialchars($headline, ENT_QUOTES, 'UTF-8'));?></h1>
        <p><?=htmlspecialchars($description, ENT_QUOTES, 'UTF-8');?></p>

        <ul class="auth-feature-list">
            <?php foreach ($features as $feature): ?>
                <li>
                    <span class="auth-feature-icon"><?php $authIcon = $feature['icon']; include __DIR__ . '/auth-icon.php'; ?></span>
                    <span><?=htmlspecialchars($feature['label'], ENT_QUOTES, 'UTF-8');?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
