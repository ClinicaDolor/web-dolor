<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">	
	<link rel="icon" type="image/png" href="<?=RUTA_IMAGES ?>/logo-clinica.png" />	
	<link rel="shortcut icon" type="image/x-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png" />
        <title>Cursos</title>
        <meta name="description" content="La clínica de dolor y cuidados paliativos del Hospital Ángeles Lomas es un grupo de especialistas líderes en dolor.">
        <meta name="viewport" content="width=device-width initial-scale=1.0">
        <link href="<?=RUTA_WEB_CSS;?>bootstrap.min.css" rel="stylesheet" />
        <link href="<?=RUTA_WEB_CSS;?>navbar.min.css" rel="stylesheet" />
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.0/animate.min.css">
        <script src="<?=RUTA_JS;?>loader.js"></script>

<script type="text/javascript">

</script>
    </head>
    <body>
    <div class="LoaderPage"></div>

    <?php include_once __DIR__ . '/../components/web-navbar.php';?>

    <div class="container pb-4" style="margin-top: 10em;">
    
    <h1 class="mt-4">7° Curso-Taller Bienal 2024 Manejo Integral del Dolor</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <small class="text-black-50">7° Curso-Taller Bienal 2024</small>
    </div>
        
        <div class="d-grid gap-2 mt-4">
        <a class="btn btn-success btn-lg p-3" target="_blank" href="descargar/web/Programa-del-Curso.pdf" download >Descargar Programa del 7° Curso-Taller Bienal 2024 Manejo Integral del Dolor</a>
        </div>

       <iframe class="mt-4" src="<?=RUTA_STORAGE;?>web/Programa-del-Curso.pdf#toolbar=0&navpanes=0&scrollbar=0&zoom=50" type="application/pdf" width="100%" height="750px" ></iframe>
       
	</div>

    <?php include_once __DIR__ . '/../components/web-ubicacion-contacto.php';?>

	<script src="<?=RUTA_WEB_JS;?>navbar.min.js"></script>    
    <script src="<?=RUTA_WEB_JS;?>bootstrap.min.js"></script>    
    </body>
</html>