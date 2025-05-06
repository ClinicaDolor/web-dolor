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
    
    <h1 class="mt-4">Seguimiento del tratamiento del dolor</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <a class="text-success" href="<?=LINK_DOLOR_CANCER;?>"><small class="text-success">Dolor por cáncer</small> /</a> <small class="text-black-50">Seguimiento del tratamiento del dolor</small>
    </div>
  
<p class="fs-4 fw-light mt-4" align="justify">
La evolución del cancer puede interferir con la evolución del dolor, por lo cuál el tratamiento analgésico deberá ser valorado periódicamente, con el objetivo de modificar los medicamentos o tratamiento de dolor de acuerdo al curso de la enfermedad. También se recomienda tener un diario de dolor, donde podrá registrar este mismo la intensidad y frecuencia de dolor, con el fin de recordar su evolución y de informar a su medico sobre la efectividad de su tratamiento.
</p>

	</div>

    <?php include_once __DIR__ . '/../components/web-ubicacion-contacto.php';?>

<script src="<?=RUTA_WEB_JS;?>navbar.min.js"></script>    
<script src="<?=RUTA_WEB_JS;?>bootstrap.min.js"></script>   
    </body>
</html>