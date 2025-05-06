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
    
    <h1 class="mt-4">Inyección epidural</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <small class="text-black-50">Inyección epidural</small>
    </div>

    <p class="fs-4 fw-light mt-4" align="justify">
      La inyección epidural de esteroides conocido también como bloqueo peridural desinflamatorio, es el procedimiento mediante el cual se inyecta un medicamento de tipo esteroide en el espacio epidural. El espacio epidural se encuentra entre su médula espinal y las vértebras. Los esteroides reducen la inflamación y la acumulación de líquido en su columna que podrían estar provocando el dolor.
    </p>


    <div class="row">
  <div class="col-12 col-sm-4 mt-4">
    <div class="list-group" id="list-tab" role="tablist">
      <a class="list-group-item list-group-item-action active fs-5 fw-light" id="list-1-list" data-bs-toggle="list" href="#list-1" role="tab" aria-controls="1">¿Cómo prepararse?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-2-list" data-bs-toggle="list" href="#list-2" role="tab" aria-controls="2">El día del procedimiento</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-3-list" data-bs-toggle="list" href="#list-3" role="tab" aria-controls="3">¿De qué se trata el procedimiento?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-4-list" data-bs-toggle="list" href="#list-4" role="tab" aria-controls="4">Después del procedimiento</a>
    </div>
  </div>
  <div class="col-12 col-sm-8 mt-4">
    <div class="tab-content" id="nav-tabContent">
      <div class="tab-pane fade show active" id="list-1" role="tabpanel" aria-labelledby="list-1-list">
        
        <h2>¿Cómo prepararse?</h2>

      <p class="fs-4 fw-light mt-4" align="justify">
      Días antes del procedimiento:
      </p>

      <ul class="fs-4 fw-light mt-4">        
      <li>Registre el día, hora y lugar de su cita.</li>
      <li>Pida a un familiar o amigo que lo acompañe al procedimiento. No deberá manejar a casa.</li>
      <li>Traiga a la consulta una lista de todos los medicamentos que toma y mencione a su médico si toma algún medicamento naturista o suplementos.</li>
      <li>Deberá notificar a su médico si toma anticoagulantes, puede ser necesario suspenderlo antes del procedimiento.</li>
      <li>No olvide traer los estudios de imagen previos (radiografía, resonancia, tomografía, electromiografía).</li>
      </ul>

      </div>
      <div class="tab-pane fade" id="list-2" role="tabpanel" aria-labelledby="list-2-list">
        
      <h2>¿Cómo prepararse?</h2>

      <p class="fs-4 fw-light mt-4" align="justify">
      El día del procedimiento:
      </p>

      <ul class="fs-4 fw-light mt-4">
      <li>Acuda en ayuno de 8 horas</li>
      <li>Únicamente tome los medicamentos que su médico haya indicado</li>
      <li>Deberá firmar un consentimiento informado, es importante resolver todas sus dudas antes del procedimiento</li>
      </ul>

      </div>
      <div class="tab-pane fade" id="list-3" role="tabpanel" aria-labelledby="list-3-list">
        
        <h2>¿De qué se trata el procedimiento?</h2>
        <p class="fs-4 fw-light mt-4" align="justify">
          Dependiendo el área del dolor, se colocará la aguja en el área de cuello, parte media de la espalda o en el área del cóccix o hueso sacro. Se puede inyectar medicamento junto a los nervios que están provocando el dolor y en el espacio epidural, esto ayuda a que el medicamento de desplace a más nervios.<br>
          El medicamento puede incluir esteroides y anestesia, se podrá inyectar medicamento en más de un área con problemas.
        </p>

      </div>
      <div class="tab-pane fade" id="list-4" role="tabpanel" aria-labelledby="list-4-list">

      <h2>Después del procedimiento</h2>

      <p class="fs-4 fw-light mt-4" align="justify">
      Pasará al área de observación y se mantendrá acostado durante 30 a 60 minutos. Podría llegar a sentir mareo, dolor de cabeza o adormecimiento de la zona intervenida, son efectos normales que se vigilarán.<br>
      Al alta, el médico podrá indicar medicamentos adicionales para el dolor o antibióticos así como rehabilitación, siempre siga las indicaciones.
      </p>

      <p class="fs-4 fw-light mt-4" align="justify"><strong>Riesgos:</strong></p>
      <ul class="fs-4 fw-light mt-4">        
      <li>Sangrado en el sitio de inyección</li>
      <li>Infección</li>
      <li>Daño en nervios, vasos sanguíneos, ligamentos o músculos cercanos al área que se va a intervenir</li>
      <li>Absceso: colección de pus bajo la piel</li>
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