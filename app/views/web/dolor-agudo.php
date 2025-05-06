<!DOCTYPE html>
<html lang="es">
    <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">	
	<link rel="icon" type="image/png" href="<?=RUTA_IMAGES ?>/logo-clinica.png" />	
	<link rel="shortcut icon" type="image/x-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png" />
        <title>Dolor agudo</title>
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
    
    <h1 class="mt-4">Dolor agudo</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <small class="text-black-50">Dolor agudo</small>
    </div>
  

    		<div class="row mt-4">
			<div class="col-12 col-sm-8">

				<p class="fs-4 fw-light " style="text-align: justify;">
					El dolor agudo es aquel que aparece posterior a un trauma, procedimiento o una cirugía y su duración es generalmente menor a 3 meses.<br><br>

					Cuando el dolor tiene una duración mayor a 3-6 meses, la Asociación Internacional para el Estudio del Dolor (IASP) lo clasifica como dolor crónico, es decir que ha persistido más allá del tiempo normal de curación y representa un reto terapéutico especial.<br><br>

					Es un problema multifactorial de componentes físicos y psicológicos: ansiedad, movilidad limitada o reducida, alteraciones del sueño y apetito, depresión, visita a diversos médicos y una reducción demostrable de la calidad de vida del paciente por limitación en la vida laboral y su función social, lo que genera una carga socioeconómica importante.<br><br>

					<strong>Tratar el dolor es tratar la causa, ¡acérquese al médico especialista en dolor!</strong></p>

					
			</div>

			<div class="col-12 col-sm-4 text-center">
			<img src="<?=RUTA_IMG_FONDO;?>fondo-dolor.jpg" width="100%">

			</div>
		</div>

	</div>

    <?php include_once __DIR__ . '/../components/web-ubicacion-contacto.php';?>
    <script src="<?=RUTA_WEB_JS;?>navbar.min.js"></script>    
    <script src="<?=RUTA_WEB_JS;?>bootstrap.min.js"></script>    
    </body>
</html>