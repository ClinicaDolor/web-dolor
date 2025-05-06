<!DOCTYPE html>
<html lang="es">
    <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">	
	<link rel="icon" type="image/png" href="<?=RUTA_IMAGES ?>/logo-clinica.png" />	
	<link rel="shortcut icon" type="image/x-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png" />
        <title>Cuidadores</title>
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
    
    <h1 class="mt-4">Cuidadores</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <small class="text-black-50">Cuidadores</small>
    </div>

     
<div class="row mt-4">
    <div class="col-12 col-sm-7">
          <p class="fs-4 fw-light mt-5" align="justify">
    Todos hemos sido cuidados desde que nacimos y a lo largo de nuestra vida es probable que necesitemos de algún cuidado mayor, como ejemplo; en la recuperación de una enfermedad o de una cirugía. Otros tal vez necesitemos asistencia prolongada ya sea por vejez o por alguna enfermedad crónica que nos limite, y es muy probable que al final de nuestra vida necesitemos de alguien que nos atienda y cuide.
</p>
    </div>
     <div class="col-12 col-sm-5">
        <img src="<?=RUTA_IMG_FONDO;?>fondo-cuidadores.jpg" width="100%">
    </div>
</div>


<div class="row mt-1">
  <div class="col-12 col-sm-4 mt-4">
    <div class="list-group" id="list-tab" role="tablist">
      <a class="list-group-item list-group-item-action active fs-5 fw-light" id="list-1-list" data-bs-toggle="list" href="#list-1" role="tab" aria-controls="1">¿El cuidador puede colapsar?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-2-list" data-bs-toggle="list" href="#list-2" role="tab" aria-controls="2">¿Qué es cuidar?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-3-list" data-bs-toggle="list" href="#list-3" role="tab" aria-controls="3">Para poder estar bien como cuidador hay que</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-4-list" data-bs-toggle="list" href="#list-4" role="tab" aria-controls="4">Yo soy cuidador pero… ¿Cuántos tipos de cuidador hay?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-5-list" data-bs-toggle="list" href="#list-5" role="tab" aria-controls="5">¡Cuídate.. y seguirás Cuidando!</a>
    </div>
  </div>
  <div class="col-12 col-sm-8 mt-4">
    <div class="tab-content" id="nav-tabContent">
      <div class="tab-pane fade show active" id="list-1" role="tabpanel" aria-labelledby="list-1-list">
          <h2>¿El cuidador puede colapsar?</h2>
          <p class="fs-4 fw-light mt-4" align="justify">
            Los cuidados que debe realizar un cuidador tal vez sean sencillos y solo de apoyo, o puede requerir de actividades complejas y con alta demanda en tiempo y carga emocional, sin embargo, además de realizar actividades básicas, el cuidador adquiere responsabilidades y aprender nuevas habilidades en forma súbita pues raramente se está entrenado para realizar las muchas tareas que se le requieren como cuidador.<br><br>

Todo el trabajo del cuidador puede representar una amenaza para su bienestar pues también enfrenta problemas de salud, obligaciones sociales, familiares y laborales.<br><br>

El desgaste múltiple, bien sea afectivo o físico, puede dar lugar al denominado <strong>Síndrome de Colapso del Cuidador</strong>.
          </p>
      </div>
      <div class="tab-pane fade" id="list-2" role="tabpanel" aria-labelledby="list-2-list">
          <h2>¿Qué es cuidar?</h2>
          <p class="fs-4 fw-light mt-4" align="justify">
            Cuidar significa prestar atenciones en todo lo referente a la actividad vital de una persona. Cuidar requiere de conocimientos, valores, habilidades y actividades emprendidas a fin de mantener o mejorar las condiciones humanas en el proceso de vivir y morir.<br><br>

El cuidado y atención de un paciente es un proceso dinámico que requerirá incrementar paulatinamente las atenciones y tareas debido a la duración de la enfermedad, que en más de 70% de los casos se trata de un proceso superior a los seis años. Asimismo, la pérdida progresiva de la capacidad física y cognitiva del paciente deriva en una dependencia total o parcial que requiere de los cuidados de personas cercanas en su entorno familiar
        </p>
      </div>
      <div class="tab-pane fade" id="list-3" role="tabpanel" aria-labelledby="list-3-list">
        <h2>Para poder estar bien como cuidador hay que</h2>

        <ul class="fs-4 fw-light mt-4">
            <li>Entender que cuidar puede implicar cambios en los roles que siempre hemos desempeñado.</li>
            <li>Es importante conocer estos cambios para evitar que nos produzcan estrés.</li>
            <li>Comprender que un cuidador de calidad necesariamente se cuida a sí mismo.</li>
            <li>Asumir que las relaciones sociales del cuidador nunca deben dejarse de lado.</li>
            <li>Serán una fuente de energía cuando se encuentre decaído.</li>
            <li>Usted y su ser querido son siempre el núcleo central de equipos de apoyo médico, de familiares y amigos.</li>
        </ul>


      </div>
      <div class="tab-pane fade" id="list-4" role="tabpanel" aria-labelledby="list-4-list">
          <h2>Yo soy cuidador pero… ¿Cuántos tipos de cuidador hay?</h2>

          <h3 class="mt-4 text-primary">Cuidador informal</h3>
<p class="fs-4 fw-light mt-4" align="justify">
Es la persona que mantiene contacto humano de manera estrecha con el paciente incapacitado, sea o no familiar, que a diario le satisface necesidades básicas, lo mantiene vinculado a la sociedad y lo provee de afecto.<br><br>

El cuidador informal puede ser un familiar, amigo, vecino u otra persona y no reciben retribución económica por la ayuda que ofrece.<br><br>

El cuidador primario o principal es aquel que guarda una relación directa con la persona cuidada, puede ser el esposa (o), hijos, hermanos, padres o amigos. Aunque la mayoría de veces la mujer es la persona que asume o le asigna la familia el rol de cuidador principal. No siendo esto siempre lo más recomendable. Hay tareas que son muy pesadas para una mujer.
</p>

<h3 class="mt-4 text-primary">Cuidador formal</h3>
<p class="fs-4 fw-light mt-4" align="justify">
Es la persona capacitada formalmente, que se le contrata, persona que recibe honorarios para realizar las tareas de cuidador. Puede ser personal de salud como enfermería o cuidadores profesionales.
</p>

      </div>

      <div class="tab-pane fade" id="list-5" role="tabpanel" aria-labelledby="list-5-list">
          <h2>¡Cuídate.. y seguirás Cuidando!</h2>

          <p class="fs-4 fw-light mt-4" align="justify">
          Alertas: Las consecuencias de no cuidarse como cuidador. Son Enfermedades graves para cuidadores, Suicidios y muertes de cuidadores…. Maltrato a pacientes por carga excesiva al cuidador.
      </p>

      <h3 class="mt-4 text-primary">¿Qué puede hacer para cuidarse?</h3>

        <ul class="fs-4 fw-light mt-4">

<li>Atienda a su propia salud y bienestar.</li>
<li>Evite el aislamiento y la pérdida de contactos con su entorno familiar y social.</li>
<li>Pida ayuda a las personas de su entorno sin esperar a que se la ofrezcan.</li>
<li>Comparta y delegue tareas y responsabilidades.</li>
<li>Exprese sus sentimientos abiertamente.</li>
<li>Valore y reconozca el esfuerzo que está realizando.</li>
<li>Establezca límites ante la demanda de cuidados de la persona mayor.</li>
<li>Utilice los recursos profesionales y sociales disponibles.</li>
<li>Procure que no se produzca un desequilibrio entre la necesidad de cuidados y los recursos y ayudas que tenga</li>

        </ul>

     </div>
    </div>
  </div>
</div>
<hr>
<img src="<?=RUTA_IMG_FONDO;?>curso-como-ser-cuidador.jpg" width="100%">
      <hr>
<img src="<?=RUTA_IMG_FONDO;?>curso-taller-bienal-2024.jpg" width="100%">

	</div>

    <?php include_once __DIR__ . '/../components/web-ubicacion-contacto.php';?>
    <script src="<?=RUTA_WEB_JS;?>navbar.min.js"></script>    
    <script src="<?=RUTA_WEB_JS;?>bootstrap.min.js"></script>    
    </body>
</html>