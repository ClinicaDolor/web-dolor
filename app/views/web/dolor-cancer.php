<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">	
        <link rel="icon" type="image/png" href="<?=RUTA_IMAGES ?>/logo-clinica.png" />	
	<link rel="shortcut icon" type="image/x-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png" />
        <title>Dolor por cáncer</title>
        <meta name="description" content="La clínica de dolor y cuidados paliativos del Hospital Ángeles Lomas es un grupo de especialistas líderes en dolor.">
        <meta name="viewport" content="width=device-width initial-scale=1.0">
        <link href="<?=RUTA_WEB_CSS;?>bootstrap.min.css" rel="stylesheet" />
        <link href="<?=RUTA_WEB_CSS;?>navbar.min.css" rel="stylesheet" />
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.0/animate.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
    
    <h1 class="mt-4">Dolor por cáncer</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <small class="text-black-50">Dolor por cáncer</small>
    </div>

<p class="fs-4 fw-light mt-4" align="justify">
Más de la mitad de pacientes con cáncer tiene dolor desde el inicio de su padecimiento y hasta un 95 % de personas que fallecen por cáncer tendrán dolor severo al final de sus días</br></br> 
El dolor por cáncer debe ser controlado por un especialista desde el principio del diagnóstico, el paciente oncológico puede tener un adecuado control del dolor en toda la trayectoria de su padecimiento, y en etapas avanzadas cuando oncología ya no tiene nada que ofrecer, Cuidados Paliativos tiene mucho que ofrecer
</br></br> 
Los pacientes con cáncer pueden tener dolor desde el inicio de la enfermedad y las causas pueden ser por los diagnósticos, por los  tratamientos para curar el cáncer  y por la propia invasión del tumor o tumores, pero la buena noticia es que también puede ser prevenido o tratado en forma oportuna
El camino que le espera a una paciente con cáncer puede ser de sufrimiento si no se le previene o trata el dolor en forma temprana
No es necesario que llegue al final de sus días para recibir un adecuado control del dolor</br></br>  
Los analgésicos convencionales con frecuencia no controlan adecuadamente el dolor por cáncer y si pueden dañar más el estómago, riñón e hígado por abuso de estos fármacos</br></br> 
La cínica del dolor puede ofrecer un esquema de analgésicos o procedimientos en tiempo oportuno para que el paciente tenga una trayectoria los menos molesta posible. Hasta que tenga una curación de la enfermedad, en caso de quedar con secuelas o en caso necesario prepararse para el final de sus días
</p>  

<div class="row mt-5"> 
<div class="col-12 col-md-6 mb-4">              
<div class="border p-3">
<h2>Frecuencia</h2> 
<p class="text-secondary fs-4 fw-light" align="justify">A pesar de los mas avanzados tratamientos para el cancer, el dolor continúa siendo un síntoma frecuente en pacientes oncológicos y tiene un impacto severo en la calidad de vida.</p>   

<div class="text-end">
<a class="btn btn-light" href="<?=LINK_DOLOR_CANCER_FRECUENCIA;?>" role="button"><small> Ver más <i class="bi bi-chevron-right"></i></small></a>    
</div>  
</div>
</div>

<div class="col-12 col-md-6 mb-4">              
<div class="border p-3">
<h2>Causas</h2> 
<p class="text-secondary fs-4 fw-light" align="justify">Las causas por las que un paciente con cancer sufre dolor pueden ser por: El propio cancer, ya sea por invasion del cancer, por compresión a algún nervio o alguno otro órgano.</p>   

<div class="text-end">
<a class="btn btn-light" href="<?=LINK_DOLOR_CANCER_CAUSAS;?>" role="button"><small> Ver más <i class="bi bi-chevron-right"></i></small></a>    
</div>  
</div>
</div>

<div class="col-12 col-md-6 mb-4">              
<div class="border p-3">
<h2>Preguntas de su médico para decidir el tratamiento</h2> 
<p class="text-secondary fs-4 fw-light" align="justify">El tipo de dolor experimentado influye en la elección de los medicamentos y su uso.<br>
Su medico le ayudara a identificar y registrara algunos factores que son importantes para decidir el esquema...</p>   

<div class="text-end">
<a class="btn btn-light" href="<?=LINK_DOLOR_CANCER_PREGUNTAS_MEDICO;?>" role="button"><small> Ver más <i class="bi bi-chevron-right"></i></small></a>    
</div>  
</div>
</div>

<div class="col-12 col-md-6 mb-4">              
<div class="border p-3">
<h2>Tratamientos mas frecuentes</h2> 
<ul class="text-secondary fs-4 fw-light ">
<li>Analgésicos</li>
<li>Opioides (Morfina)</li>
<li>Infiltraciones</li>
<li>Bloqueos</li>
<li>Psicoterapia</li>
<li>Cirugia</li>
</ul>
</div>
</div>

<div class="col-12 col-md-6 mb-4">              
<div class="border p-3">
<h2>Seguimiento del tratamiento del dolor</h2> 
<p class="text-secondary fs-4 fw-light" align="justify">La evolución del cancer puede interferir con la evolución del dolor, por lo cuál el tratamiento analgésico deberá ser valorado periódicamente...</p>   
<div class="text-end">
<a class="btn btn-light" href="<?=LINK_DOLOR_CANCER_SEGUIMIENTO_TRATAMIENTO_DOLOR;?>" role="button"><small> Ver más <i class="bi bi-chevron-right"></i></small></a>    
</div>  
</div>
</div>

<div class="col-12 col-md-6 mb-4">              
<div class="border p-3">
<h2>Cinco prioridades para el cuidado de un paciente agónico</h2> 
<p class="text-secondary fs-4 fw-light" align="justify">La recuperación y la curación no siempre son posibles y para cada persona llegará un momento en el que ya no se podrá evitar la muerte o los tratamientos superarán los beneficios...</p>   
<div class="text-end">
<a class="btn btn-light" href="<?=LINK_DOLOR_CANCER_CINCO_PRIORIDADES;?>" role="button"><small> Ver más <i class="bi bi-chevron-right"></i></small></a>    
</div>  
</div>
</div>

<div class="col-12 col-md-6 mb-4">              
<div class="border p-3">
<h2>Endoscopia paliativa</h2> 
<p class="text-secondary fs-4 fw-light" align="justify">La endoscopia paliativa le ofrece al paciente una estancia hospitalaria corta (1-2 días en promedio ), menor dolor, mejoría de las condiciones generales del paciente y el poder regresar de manera temprana con su familia mejorando su calidad de vida.</p>   
<div class="text-end">
<a class="btn btn-light" href="<?=LINK_DOLOR_CANCER_ENDOSCOPIA_PALIATIVA;?>" role="button"><small> Ver más <i class="bi bi-chevron-right"></i></small></a>    
</div>  
</div>
</div>

</div>


	</div>

    <?php include_once __DIR__ . '/../components/web-ubicacion-contacto.php';?>
    <script src="<?=RUTA_WEB_JS;?>navbar.min.js"></script>    
    <script src="<?=RUTA_WEB_JS;?>bootstrap.min.js"></script>     
    </body>
</html>