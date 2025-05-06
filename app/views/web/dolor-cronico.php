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
    
    <h1 class="mt-4">Dolor crónico</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <small class="text-black-50">Dolor crónico</small>
    </div>
  

    		<div class="row mt-4">
			<div class="col-12 col-sm-8">

				<p class="fs-4 fw-light " style="text-align: justify;">
					El dolor crónico, es decir, que ha persistido más allá del tiempo normal de curación, (3-6 meses de duración), representa un reto para el equipo médico ya que incluye más de una modalidad terapéutica (fármacos, fisioterapia, rehabilitación, bloqueos, etc).<br><br>

                    Este tipo de dolor no tiene ningún propósito vital y no tiene un final reconocible. Generalmente se acompaña de depresión, enojo, ansiedad, miedo que interfiere con las actividades cotidianas de la vida diaria y afecta los roles sociales.<br><br>

                    Puede ser causado por problemas de salud como artritis, cambios degenerativos en los huesos, daño a los nervios o como resultado de un problema específica que a menudo se ha curado, incluso puede aparecer tiempos después (meses o años) de alguna cirugía o accidente. A veces no es posible que los médicos señalen la causa del dolor y puede ser frustrante no tener un diagnóstico.<br><br>

                    El dolor puede ser continuo o variar en su nivel de intensidad cada día, siendo insoportable o empeorar rápidamente, mientras que en otros momentos es más fácil de manejar.</p>

					
			</div>

			<div class="col-12 col-sm-4 text-center">
			<img src="<?=RUTA_IMG_FONDO;?>fondo-dolor.jpg" width="100%">

			</div>
		</div>

        <h2 class="mt-4">Causas frecuentes:</h2>

        <ul class="list-group list-group-flush fs-4 fw-light">
  <li class="list-group-item">Neuropatía diabética</li>
  <li class="list-group-item">Neuropatía postherpética</li>
  <li class="list-group-item">Neuralgia del trigémino</li>
  <li class="list-group-item">Migraña</li>
  <li class="list-group-item">Lumbalgia (dolor espalda)</li>
  <li class="list-group-item">Enfermedad articular degenerativa</li>
  <li class="list-group-item">Artritis</li>
  <li class="list-group-item">Ciática o hernia no quirúrgica</li>
  <li class="list-group-item">Cáncer</li>
  <li class="list-group-item">Dolor postquirúrgico</li>
  <li class="list-group-item">Dolor post-infarto cerebra</li>
</ul>

<p class="fs-4 fw-light mt-4" style="text-align: justify;">
<strong>Para medirlo y evaluarlo es necesario un cuestionario e historia clínica específica que incluye fármacos analgésicos y técnicas que ha utilizado para el control del dolor.<br><br>

El tratamiento incluye la valoración por un especialista del dolor en coordinación con el grupo multidisciplinario abordando la enfermedad que lo origina.</strong>
</p>


	</div>

    <?php include_once __DIR__ . '/../components/web-ubicacion-contacto.php';?>
    <script src="<?=RUTA_WEB_JS;?>navbar.min.js"></script>    
    <script src="<?=RUTA_WEB_JS;?>bootstrap.min.js"></script>    
    </body>
</html>