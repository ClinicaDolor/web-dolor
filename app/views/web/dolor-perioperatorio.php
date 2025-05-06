
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
        <link href="<?=RUTA_WEB_CSS;?>bootstrap.css" rel="stylesheet" />
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
    
    <h1 class="mt-4">Dolor perioperatorio</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <small class="text-black-50">Dolor por cáncer</small>
    </div>
  

  <p class="fs-4 fw-light mt-4" style="text-align: justify;">
          El dolor perioperatorio es aquel que acompaña a una cirugía, ya sea antes, durante o después del procedimiento. Para medir adecuadamente su dolor requerimos que nos mencione sus experiencias previas al dolor y la respuesta con el tratamiento que recibió. La adecuada evaluación es muy importante para que el control del dolor sea eficaz.</p>


<div class="row">
  <div class="col-12 col-sm-4 mt-4">
    <div class="list-group" id="list-tab" role="tablist">
      <a class="list-group-item list-group-item-action active fs-5 fw-light" id="list-1-list" data-bs-toggle="list" href="#list-1" role="tab" aria-controls="1">¿Por qué es importante medir el dolor perioperatorio?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-2-list" data-bs-toggle="list" href="#list-2" role="tab" aria-controls="2">También es importante</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-3-list" data-bs-toggle="list" href="#list-3" role="tab" aria-controls="3">El día de su cirugía</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-4-list" data-bs-toggle="list" href="#list-4" role="tab" aria-controls="4">Días después de la cirugía</a>
       <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-5-list" data-bs-toggle="list" href="#list-5" role="tab" aria-controls="5">Avise a su médico si presenta alguno de los siguientes síntomas</a>
    </div>
  </div>
  <div class="col-12 col-sm-8 mt-4">
    <div class="tab-content" id="nav-tabContent">
      <div class="tab-pane fade show active" id="list-1" role="tabpanel" aria-labelledby="list-1-list">
      
      <h2>¿Por qué es importante medir el dolor perioperatorio?</h2>

      <ol class="fs-4 fw-light mt-4">
        <li>El paciente requiere cursar el perioperatorio con dolor controlado para tener menos consecuencias fisiológicas por el estrés que genera el dolor.</li>
        <li>El personal de salud debe conocer la intensidad de dolor y características para ofrecer un tratamiento idóneo para cada paciente.</li>
        <li>La intensidad, tipo y evolución del dolor varía de acuerdo a cada procedimiento y paciente, por lo que se requiere de ajustes constantes.</li>
      </ol>

      <p class="fs-4 fw-light mt-4" style="text-align: justify;">Para medir la intensidad de dolor se puede dar un valor utilizando la escala que se muestra en la imagen, en donde ”cero” significa que no tiene dolor y el 10 es el máximo dolor.</p>

      <img class="mt-3" src="<?=RUTA_IMG_FONDO;?>escalaDolor.jpg" width="100%">

      </div>
      <div class="tab-pane fade" id="list-2" role="tabpanel" aria-labelledby="list-2-list">
        
        <h2>También es importante:</h2>

        <ul class="fs-4 fw-light mt-4">
          <li>Identificar si el dolor corresponde al sitio operado o a otra localización.</li>
          <li>Informar las características de cada dolor. Por ejemplo: punzada, ardor, opresión, cólico.</li>
          <li>La duración: si es constante, intermitente o esporádico.</li>
          <li>Si se asocia con algún movimiento o actividad. Por ejemplo: al toser, con ejercicios respiratorios.</li>
          <li>Informar el dolor como usted lo sienta.</li>
          <li>Informar si usted ingiere algún analgésico en forma crónica (incluya medicina tradicional).</li>
        </ul>

      </div>
      <div class="tab-pane fade" id="list-3" role="tabpanel" aria-labelledby="list-3-list">
        
         <h2>El día de su cirugía:</h2>

        <ul class="fs-4 fw-light mt-4">
          <li>Al salir del quirófano, en la sala de recuperación usted recibirá un tratamiento programado para su dolor.</li>
          <li>Si usted percibe que su dolor es mayor a 4 en la escala de intensidad, avísenos para modificar su manejo y retroaliméntenos si el manejo fue efectivo.</li>
          <li>Deberá informar si presenta cualquiera de los efectos secundarios por los medicamentos.</li>
          <li>Saber que los rescates son dosis extras de analgesia para ayudar a controlar el dolor cuando el tratamiento previo no es suficiente. Podrá recibir algún rescate si tu médico lo indica o autoriza.</li>
          <li>Para algunas cirugías que cursan con dolor intenso, se puede disponer de un sistema especial de Analgesia Controlada por el Paciente (PCA) o requerir de un catéter de analgesia epidural (en la columna). Estas modalidades las puede solicitar su médico dependiendo del caso.</li>
        </ul>

      </div>
      <div class="tab-pane fade" id="list-4" role="tabpanel" aria-labelledby="list-4-list">
        
         <h2>Días después de la cirugía:</h2>
<p class="fs-4 fw-light mt-4" style="text-align: justify;">Se prevé que dependiendo de la cirugía haya cierto dolor, que controlaremos adecuadamente. El dolor tiende a disminuir conforme pasan las horas y días de la cirugía, por lo que los analgésicos también deben de retirarse progresivamente. Su médico le dará una receta con las indicaciones para el control del dolor de acuerdo a la intensidad de su dolor y tipo de cirugía.</p>

      </div>
      <div class="tab-pane fade" id="list-5" role="tabpanel" aria-labelledby="list-5-list">

        <h2>Avise a su médico si presenta alguno de los siguientes síntomas:</h2>

        <ul class="fs-4 fw-light mt-4">
          <li>Dolor no controlado con los medicamentos indicados.</li>
          <li>Dolor en el área intervenida que empeora con los días.</li>
          <li>Náusea, vómito, mareo, alucinaciones, sueño intenso.</li>
          <li>No se automedique.</li>
        </ul>

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