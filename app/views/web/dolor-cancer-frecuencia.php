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
    
    <h1 class="mt-4">Frecuencia</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <a class="text-success" href="<?=LINK_DOLOR_CANCER;?>"><small class="text-success">Dolor por cáncer</small> /</a> <small class="text-black-50">Frecuencia</small>
    </div>
  
<p class="fs-4 fw-light mt-4" align="justify">
A pesar de los mas avanzados tratamientos para el cancer, el dolor continúa siendo un síntoma frecuente en pacientes oncológicos y tiene un impacto severo en la calidad de vida.
</p>
<p class="fs-4 fw-light" align="justify">
El dolor por cancer impide concentrarse o pensar y crea dificultades para realizar las actividades diarias normales y se relaciona con un aumento del sufrimiento emocional y de depresión.
</p>
<p class="fs-4 fw-light" align="justify">
Mas de la mitad de pacientes oncológicos (55%) presentan dolor durante el tratamiento contra el cáncer.
</p>
<p class="fs-4 fw-light" align="justify">
Uno de cada 3 pacientes con cancer (40%) informan que quedan con dolor después del tratamiento curativo y 27% sufre dolor moderado a severo.
</p>
<p class="fs-4 fw-light" align="justify">
El (75%) Tres de cada 4 pacientes con cancer informan dolor durante la enfermedad avanzada, o en caso de presentar metástasis, o al llegar a la fase terminal.
</p>
<p class="fs-4 fw-light" align="justify">
La mitad de los pacientes con cancer (50% ) informan dolor durante los estudios para el cáncer en cualquier etapa de la enfermedad (4 muñecos y dos de color rojo con dolor)
</p>
<p class="fs-4 fw-light" align="justify">
El 40% de todos los pacientes notificaron dolor moderado a intenso (puntuación de escala de calificación numérica >5).
</p>
<p class="fs-4 fw-light" align="justify">
Los pacientes con peor estado funcional (ECOG 2 y 3) tienen más dolor y con mayor intensidad.
</p>

	</div>

    <?php include_once __DIR__ . '/../components/web-ubicacion-contacto.php';?>
    <script src="<?=RUTA_WEB_JS;?>navbar.min.js"></script>    
    <script src="<?=RUTA_WEB_JS;?>bootstrap.min.js"></script>  
    </body>
</html>