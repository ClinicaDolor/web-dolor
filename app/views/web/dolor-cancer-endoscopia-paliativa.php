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
    
    <h1 class="mt-4">Endoscopia paliativa</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <a class="text-success" href="<?=LINK_DOLOR_CANCER;?>"><small class="text-success">Dolor por cáncer</small> /</a> <small class="text-black-50">Endoscopia paliativa</small>
    </div>
  
<ul class="fs-4 fw-light mt-4" align="justify">
<li>La paliación endoscópica de tumores del aparato digestivo mediante endoprótesis metalicas auto expandibles fue descrita por primera vez por Truong et al en 1992.</li>
<li>Desde entonces esta opción terapeútica para la paliación con endoprotesis plásticas o metálicas de tumores esofágicos, gástricos, pancreáticos, duodenales, de la vía biliar y de colon ha ganado popularidad.</li>
<li>Esta técnica ofrece una rápida descompresión del tubo digestivo y la restauración de su permeabilidad y de la continuidad del aparato digestivo resolviendo los síntomas obstructivos como son nausea, ictericia, dolor, vómito y desnutrición. Reestableciendo la capacidad de comer por el paciente.</li>
<li>Las complicaciones asociadas con la colocación de endoprotesis metaicas auto expandibles pueden ser inmediatas (24 horas) , tempranas o tardías. Las complicaciones inmediatas y tempranas incluyen sangrado gastrointestinal, perforación del tubo digestivo , la mala posición de la endoprotesis y el angulamiento de la misma.</li>
<li>Las complicaciones tardías incluyen migración de la misma, o su oclusión por crecimiento del tumor a través de la malla metálica o en la entrada y salida de la misma.</li>
<li>El índice de complicaciones en general varia de acuerdo a la literatura pero en general es de aproximadamente 10 %.</li>
<li><strong>CONCLUSIÓN:</strong> La colocación de endoprotesis metálicas autoexpandibles constituye una opción efectiva, segura y minimamente invasiva que reconstruye la continuidad del aparato digestivo y permite una pronta ingesta de la vía oral con un bajo índice de complicaciones como medida paliativa en pacientes con un estado avanzado de la enfermedad oncológica.</li>
<li>La endoscopia paliativa le ofrece al paciente una estancia hospitalaria corta (1-2 días en promedio ), menor dolor, mejoría de las condiciones generales del paciente y el poder regresar de manera temprana con su familia mejorando su calidad de vida.</li>
</ul>

	</div>

    <?php include_once __DIR__ . '/../components/web-ubicacion-contacto.php';?>
    <script src="<?=RUTA_WEB_JS;?>navbar.min.js"></script>    
    <script src="<?=RUTA_WEB_JS;?>bootstrap.min.js"></script>  
    </body>
</html>