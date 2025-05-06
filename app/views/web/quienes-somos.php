<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">		
		<link rel="icon" type="image/png" href="<?=RUTA_IMAGES;?>logo.png" /> 
        <link rel="shortcut icon" type="image/x-icon" href="<?=RUTA_IMAGES;?>logo.png" />
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
    
    <h1 class="mt-4">¿Quiénes somos?</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <small class="text-black-50">¿Quiénes somos?</small>
    </div>
  

    		<div class="row mt-4">
			<div class="col-12 col-sm-8">

				<p class="fs-4 fw-light " style="text-align: justify;">
					<strong>La clínica de dolor y cuidados paliativos del Hospital Ángeles Lomas</strong> es un grupo de especialistas líderes en dolor que en conjunto con otras especialidades <strong>(Neurología, Ortopedia, Cirugía, Psicología, Rehabilitación, entre otros)</strong> ofrece una amplia gama de tratamientos para dolor agudo no controlado, dolor postoperatorio y dolor crónico de todo tipo <strong>(neuropático, oncológico, postquirúrgico)</strong>. Los tratamientos están basados en las guías y protocolos internacionales de control de dolor. Cada paciente es evaluado de manera integral para ofrecer un tratamiento individualizado para el control del dolor con medicamentos y procedimientos de vanguardia.</p>

					<h2 class="mt-4">El grupo cuenta con amplia experiencia en diversos tratamientos:</h2>

					<div class="row mt-4">
						<div class="col-12 col-sm-6 mt-2">

							<div class="fondo-color-claro text-white fs-5 fw-light p-3 text-center">
							Titulación de analgesia con opioides ( Adecuar la dosis necesaria de analgesicos )
						    </div>						  

						</div>

						<div class="col-12 col-sm-6 mt-2">

							<div class="fondo-color text-white fs-5 fw-light p-3 text-center">
							Titulación de analgesia con opioides ( Adecuar la dosis necesaria de analgesicos )
						    </div>						  

						</div>

						<div class="col-12 col-sm-6 mt-2">

							<div class="fondo-color-secundario text-white fs-5 fw-light p-3">
							<div class="text-center">Técnicas de neuromodulación</div>

							<ol class="mt-3">
						    	<li><small>Estimulación nerviosa transcutánea</small></li>
						    	<li><small>Estimulación de nervios periféricos</small></li>
						    	<li><small>Estimulación medular</small></li>
						    	<li><small>Estimulación cerebral y cortical profunda</small></li>
						    	<li><small>Administración de fármacos intraespinales</small></li>
						    	<li><small>Lesiones por radiofrecuencia</small></li>
						    </ol>
						    </div>		  

						</div>

						<div class="col-12 col-sm-6 mt-2">
							<div class="fondo-color-claro text-white fs-5 fw-light p-3 text-center">
							Bloqueos guiados por ultrasonido
						    </div>	

						     <div class="fondo-color text-white fs-5 fw-light p-3 text-center mt-2">
							Radiofrecuencia
						    </div>	

						     <div class="fondo-color-claro text-white fs-5 fw-light p-3 text-center mt-2">
							Estimulación medular
						    </div>	

						     <div class="fondo-color text-white fs-5 fw-light p-3 text-center mt-2">
							Infusión intratecal
						    </div>	
						</div>
											

					</div>
						

			<p class="fs-4 fw-light mt-4" style="text-align: justify;">
					Evaluación de la causa y tratamiento del dolor. Cuando se inicia un programa de dolor interdisciplinario, habrá varios miembros del personal y especialistas que evaluarán diferentes áreas de su salud y capacidad. Aquí es donde se identificarán los objetivos del tratamiento y los resultados esperados.
				</p>

				<p class="fs-4 fw-light" style="text-align: justify;">		
				Nos encontramos en Planta Baja, frente a Laboratorio Clínico, a un lado del centro Oncológico, dentro de las instalaciones del Hospital Ángeles Lomas</p>
			</div>

			<div class="col-12 col-sm-4 text-center">
			<img src="<?=RUTA_IMG_FONDO;?>fondo-quienes-somos.jpg" width="100%">

			</div>
		</div>


	</div>

	<?php include_once __DIR__ . '/../components/web-ubicacion-contacto.php';?>

	<script src="<?=RUTA_WEB_JS;?>navbar.min.js"></script>    
    <script src="<?=RUTA_WEB_JS;?>bootstrap.min.js"></script>    
    </body>
</html>