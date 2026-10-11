<nav class="navbar navbar-header navbar-expand navbar-light" aria-label="Barra de herramientas clínica">
<button class="sidebar-toggler btn" type="button" aria-label="Abrir o cerrar menú"><span class="navbar-toggler-icon"></span></button>
<button class="btn navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
<span class="navbar-toggler-icon"></span>
</button>
                
<div class="collapse navbar-collapse" id="navbarSupportedContent">
<ul class="navbar-nav d-flex align-items-center navbar-light ms-auto">
</ul>
</div>

<div class="search float-end mt-3">
<div class="search-input">
<a href="" target="_blank" hidden></a>
<label class="visually-hidden" for="clinicalSearch">Buscar pacientes, recetas o notas</label>
<input type="search" class="form-control round" id="clinicalSearch" placeholder="Buscar pacientes, recetas o notas" autocomplete="off">
<div class="autocom-box"></div>
<div class="icon"><i data-feather="search"></i></div>
</div>
</div>

<?php
$nombreSesion = htmlspecialchars((string)($data['datos']['nombre'] ?? 'Equipo clínico'), ENT_QUOTES, 'UTF-8');
?>
<div class="topbar-session" aria-label="Sesión activa">
<span class="topbar-session-icon"><i data-feather="user" aria-hidden="true"></i></span>
<span class="topbar-session-copy"><strong><?=$nombreSesion;?></strong><small>Equipo clínico</small></span>
</div>
</nav>
