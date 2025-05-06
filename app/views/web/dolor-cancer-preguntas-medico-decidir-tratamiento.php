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
         .fondo-color-secundario{
         	background: var(--secundario);
      
         }
</style>
    </head>
    <body>

    <div class="LoaderPage"></div>
    <?php include_once __DIR__ . '/../components/web-navbar.php';?>

    <div class="container pb-4" style="margin-top: 10em;">
    
    <h1 class="mt-4">Preguntas de su médico para decidir el tratamiento</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <a class="text-success" href="<?=LINK_DOLOR_CANCER;?>"><small class="text-success">Dolor por cáncer</small> /</a> <small class="text-black-50">Preguntas de su médico para decidir el tratamiento</small>
    </div>
  
<p class="fs-4 fw-light mt-4" align="justify">
El tipo de dolor experimentado influye en la elección de los medicamentos y su uso.
</p>
<p class="fs-4 fw-light mt-4" align="justify">
Su medico le ayudara a identificar y registrara algunos factores que son importantes para decidir el esquema de tratamiento que usted puede necesitar.
</p>

<ul class="fs-4 fw-light mt-4">
<li>Necesitara realizar una historia clínica.</li>
<li>Forma de evaluación del dolor.</li>
</ul>

<ol class="fs-4 fw-light mt-4">
    <li>Localización (Donde duele, pueden ser varias zonas)</li>
    <li>Severidad del dolor:
        <ul>
            <li>La intensidad del dolor se evalúa mediante una escala de valoración numérica (EVN) de 0 a 10. En esta escala, 0 indica ausencia de dolor, 1 a 3 indica dolor leve, 4 a 6 indica dolor moderado y 7 a 10 indica dolor intenso. (Poner LINK DE la escala de dolor que hemos hecho)</li>
        </ul>
    </li>
    <li>Tipo de dolor, como agudo, hormigueo o dolor: Se le harán varias preguntas para identificar el tipo de dolor (Cuestionario breve de dolor)</li>
    <li>Si el dolor es persistente, o viene y se va</li>
    <li>¿Qué actividades o eventos empeoran el dolor?</li>
    <li>¿Qué actividades o eventos mejoran el dolor?</li>
    <li>Medicamentos que ha recibido para controlar el dolor</li>
    <li>Medicamentos que si le alivian el dolor</li>
    <li>El impacto que el dolor tiene en el estilo de vida; calidad del sueño, pérdida de apetito, humor, etc.</li>
</ol>

	</div>

    <?php include_once __DIR__ . '/../components/web-ubicacion-contacto.php';?>
    <script src="<?=RUTA_WEB_JS;?>navbar.min.js"></script>    
    <script src="<?=RUTA_WEB_JS;?>bootstrap.min.js"></script>   
    </body>
</html>