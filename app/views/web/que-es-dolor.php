<!DOCTYPE html>
<html lang="es">
    <head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">	
	<link rel="icon" type="image/png" href="<?=RUTA_IMAGES ?>/logo-clinica.png" />	
	<link rel="shortcut icon" type="image/x-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png" />
        <title>Clínica del Dolor y Cuidados Paliativos del Hospital Ángeles Lomas</title>
        <meta name="description" content="La clínica de dolor y cuidados paliativos del Hospital Ángeles Lomas es un grupo de especialistas líderes en dolor.">
        <meta name="viewport" content="width=device-width initial-scale=1.0">
        <link href="<?=RUTA_WEB_CSS;?>bootstrap.min.css" rel="stylesheet" />
        <link href="<?=RUTA_WEB_CSS;?>navbar.min.css" rel="stylesheet" />
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.0/animate.min.css">
        <script src="<?=RUTA_JS;?>loader.js"></script>
        <style type="text/css">
	a{
		text-decoration: none;
	}
	.fondo-color{
         	background: var(--primary);
         }
	.fondo-color-claro{
         	background: var(--primary-claro);
      }
.color-secundario{
color: var(--secundario);
}
</style>
    </head>
    <body>

    <div class="LoaderPage"></div>

    <?php include_once __DIR__ . '/../components/web-navbar.php';?>

    <div class="container pb-4" style="margin-top: 10em;">
    
    <h1 class="mt-4">¿Qué es el dolor?</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <small class="text-black-50">¿Qué es el dolor?</small>
    </div>
  

    		<div class="row mt-4">
			<div class="col-12 col-sm-8">

				<p class="fs-4 fw-light mt-4" align="justify">
					El dolor es un problema complicado, a menudo debilitante que puede tener un impacto importante en el bienestar físico y mental de quien lo padece. Debido a que es una experiencia personal y subjetiva, su valoración es complicada y su evaluación es vital.<br><br>

					Según la International Asociation for the Study of Pain (IASP)— es es una experiencia sensorial (objetiva) y emocional (subjetiva), generalmente desagradable que esta asociada a una lesión tisular o se expresa como si ésta existiera.<br><br>

					El dolor es un síntoma que indica que algo esta mal. Se puede experimentar dolor aún en ausencia de un daño físico evidente, incluso su intensidad puede no ser proporcional con el daño original y su localización no siempre es el origen de la causa<br><br>

					<strong>Dolor, ¿Alarma o enfermedad?</strong></p>

					

			</div>

			<div class="col-12 col-sm-4 text-center">
			<img src="<?=RUTA_IMG_FONDO;?>fondo-dolor-2.jpg" width="100%">

			</div>
		</div>

<div class="row mt-4">

<div class="col-12 col-md-6">
<div class="bg-light p-4">
<h3 class="text-center color-secundario">Dolor agudo</h3>

<p class="fs-4 fw-light mt-4" align="justify">
<strong>El dolor agudo</strong> suele ser una señal de alarma para indicarnos que existe una lesión en los tejidos o una enfermedad. La intensidad del dolor se asocia con el nivel y severidad, pero también tiene un componente individual pues cada persona puede percibir y sentir el dolor con intensidad diferente.
</p> 

</div>
</div>

<div class="col-12 col-md-6">
<div class="bg-light p-4">
<h3 class="text-center color-secundario">Dolor es crónico</h3>

<p class="fs-4 fw-light mt-4" align="justify">
Se dice que el <strong>dolor es crónico</strong> si persiste más allá del tiempo de curación normal de aproximadamente tres meses y puede no responder al tratamiento médico estándar.

El dolor crónico es una enfermedad, debido a los cambios en el sistema nervioso no relacionados con el diagnóstico original o lesión, si hubo alguno.
</p> 

</div>
</div>
		
</div>

<p class="fs-4 fw-light mt-4 color-secundario" align="justify">
<strong>Recuerde...<br>
Sólo la persona con dolor puede decir realmente lo doloroso que es algo. Porque el dolor es siempre personal, no hay dos personas que lo experimenten de la misma manera. Esto hace que sea muy difícil de definir y tratar.</strong>
</p>

<h2 class="mt-5">Impacto del dolor no controlado:</h2>

<ul class="list-group list-group-flush fs-4 fw-light mt-4">
	<li class="list-group-item">Sufrimiento innecesario</li>
	<li class="list-group-item">Miedo y ansiedad</li>
	<li class="list-group-item">Problemas para dormir</li>
	<li class="list-group-item">Movilidad limitada y poca autonomía del paciente</li>
	<li class="list-group-item">Puede exacerbar el riesgo de complicaciones</li>
	<li class="list-group-item">Recuperación lenta de las funciones y del estilo de vida normales</li>
	<li class="list-group-item">Automedicación (los pacientes toman medicamentos o sustancias que no son prescritas por el médico, para aliviar su dolor)</li>
	<li class="list-group-item">Abuso de analgésicos</li>
	<li class="list-group-item">Estancias hospitalarias prolongada</li>
	<li class="list-group-item">Disminuye la satisfacción del paciente.</li>
	<li class="list-group-item">Cronificación del dolor</li>
	<li class="list-group-item">Calidad de vida reducida</li>
</ul>

	</div>

	<?php include_once __DIR__ . '/../components/web-ubicacion-contacto.php';?>

	<script src="<?=RUTA_WEB_JS;?>navbar.min.js"></script>    
    <script src="<?=RUTA_WEB_JS;?>bootstrap.min.js"></script>    
    </body>
</html>