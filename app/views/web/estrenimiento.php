<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">	
	<link rel="icon" type="image/png" href="<?=RUTA_IMAGES ?>/logo-clinica.png" />	
	<link rel="shortcut icon" type="image/x-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png" />
        <title>¿Cómo se inicia un tratamiento con opioides?</title>
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

.fondo-color-claro2{
background: var(--primary-claro2);
}
</style>
    </head>
    <body>

    <div class="LoaderPage"></div>
    <?php include_once __DIR__ . '/../components/web-navbar.php';?>

    <div class="container pb-4" style="margin-top: 10em;">
    
    <h1 class="mt-4">Estreñimiento</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <small class="text-black-50">Estreñimiento</small>
    </div>

    <p class="fs-4 fw-light mt-4" align="justify">
      El estreñimiento se define como defecaciones pequeñas, duras, difíciles, o infrecuentes. Se considera estreñimiento cuando hay menos de tres evacuaciones por semana.<br>
      Cuando el estreñimiento dura más de tres meses, hablamos de estreñimiento crónico habitual.<br>
      Hablamos de estreñimiento secundario cuando existe una causa evidente como fármacos, enfermedades y afección del sistema digestivo.<br>
      El estreñimiento es un problema común en la población general, así que es frecuente que un paciente sea estreñido antes de recibir un tratamiento con opioides.
      </p>
      <p class="fs-4 fw-light mt-4" align="justify">
      <strong>Causas de estreñimiento</strong><br>
      Son muchas las causas posibles para que una persona presente estreñimiento, enseguida mencionamos solo algunas:
    </p>

      <ul class="fs-4 fw-light mt-4"> 
      <li>Sedentarismo o inmovilidad</li>
      <li>Deshidratación</li>
      <li>Dolor</li>
      <li>Cirugías abdominales</li>
      <li>Dieta sin fibra, etc.</li>
      <li>Problemas neurológicos</li>
      <li>Enfermedades metabólicas como diabetes, hipotiroidismo</li>
      <li>Enfermedades que cursan con obstrucción en el colon o el recto</li>
      </ul>

<div class="row">
  <div class="col-12 col-sm-4 mt-4">
    <div class="list-group" id="list-tab" role="tablist">
      <a class="list-group-item list-group-item-action active fs-5 fw-light" id="list-1-list" data-bs-toggle="list" href="#list-1" role="tab" aria-controls="1">¿Cómo saber cuándo el paciente esta estreñido?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-2-list" data-bs-toggle="list" href="#list-2" role="tab" aria-controls="2">¿Porqué los opioides causan estreñimiento?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-3-list" data-bs-toggle="list" href="#list-3" role="tab" aria-controls="3">¿Cuánto tiempo dura el estreñimiento causado por los opioides?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-4-list" data-bs-toggle="list" href="#list-4" role="tab" aria-controls="4">¿Cuál es el tratamiento para el estreñimiento causado por los opioides?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-5-list" data-bs-toggle="list" href="#list-5" role="tab" aria-controls="5">Técnica de masaje abdominal</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-6-list" data-bs-toggle="list" href="#list-6" role="tab" aria-controls="6">Técnica para enema o lavativa</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-7-list" data-bs-toggle="list" href="#list-7" role="tab" aria-controls="7">Consecuencias del estreñimiento</a>
    </div>
  </div>
  <div class="col-12 col-sm-8 mt-4">
    <div class="tab-content" id="nav-tabContent">
      <div class="tab-pane fade show active" id="list-1" role="tabpanel" aria-labelledby="list-1-list">

        <h2>¿Cómo saber cuándo el paciente esta estreñido?</h2>
        
        <p class="fs-4 fw-light mt-4" align="justify">
        La defecación normal es absolutamente individual y depende del sexo, edad, personalidad y cultura; además, no son constantes, incluso en cada persona individualmente en el día a día, la frecuencia, el peso o volumen diario y la consistencia del material defecado.<br>
        Se puede considerar estreñido si el paciente presenta algunos de los siguientes signos y síntomas:
        </p>

        <ul class="fs-4 fw-light mt-4">
        <li>Tener menos de tres evacuaciones por semana</li>
        <li>Tener materia fecal grumosa o dura</li>
        <li>Hacer un gran esfuerzo para tener evacuaciones intestinales</li>
        <li>Sentir como si hubiera una obstrucción en el recto que le impide evacuar</li>
        <li>Sentir como si no pudieras vaciar por completo el recto</li>
        <li>Requerir de ayuda para vaciar el recto; usar las manos para presionar el abdomen o un dedo para sacar las heces del recto</li>
        </ul>

      </div>
      <div class="tab-pane fade" id="list-2" role="tabpanel" aria-labelledby="list-2-list">
        
        <h2>¿Porqué los opioides causan estreñimiento?</h2>

        <p class="fs-4 fw-light mt-4" align="justify">
          Los opioides disminuyen la movilidad del intestino (peristalsis) y pueden incrementar el estreñimiento previo del paciente, este es un efecto adverso muy frecuente (más del 60%), sobre todo si el paciente es estreñido previamente.<br>

          El estreñimiento (estreñimiento secundario) causado por opioides se conoce también como “Disfunción Intestinal Inducida por Opioides” (DIIO). Esta entidad comprende una serie de síntomas gastrointestinales ocasionados por la disminución en la peristalsis, lo que genera acumulación de gases y secreciones, distensión abdominal y endurecimiento de la materia fecal
          </p>

      </div>
      <div class="tab-pane fade" id="list-3" role="tabpanel" aria-labelledby="list-3-list">
        
         <h2>¿Cuánto tiempo dura el estreñimiento causado por los opioides?</h2>

         <p class="fs-4 fw-light mt-4" align="justify">
          El estreñimiento causado por los opioides puede persistir durante todo el tratamiento con un opioide por lo que deberá ser vigilado y tratado en todo momento
          </p>

      </div>
      <div class="tab-pane fade" id="list-4" role="tabpanel" aria-labelledby="list-4-list">
        <h2>¿Cuál es el tratamiento para el estreñimiento causado por los opioides?</h2>
        <p class="fs-4 fw-light mt-4" align="justify">
          El paciente deberá informar si tiene estreñimiento antes de recibir un opioide, en caso de ser afirmativo también deberá comentar sobre el método o laxante que le ha sido útil.<br><br>

Prevenir es lo más importante, saber que existe la posibilidad de tener este efecto adverso ayuda a identificar oportunamente si se requiere de incrementar las medidas para tratarlo.<br><br>

Nosotros recomendamos a nuestros pacientes que no dejen pasar más de dos días sin que haya evacuaciones efectivas. También recomendamos que no dejen que las heces este duras.<br><br>

<div class="text-center fs-4 fw-light"><strong>Es mejor “Flojito y fácil de salir”</strong></div>
        </p>
      </div>
      <div class="tab-pane fade" id="list-5" role="tabpanel" aria-labelledby="list-5-list">
        <h2>Técnica de masaje abdominal</h2>

        <p class="fs-4 fw-light mt-4" align="justify">
        <strong>El tratamiento del estreñimiento habitual es escalonado:</strong>
        </p>

        <ul class="fs-4 fw-light mt-4">
        <li>Medidas dietéticas</li>
        <li>Ejercicio físico regular no extenuante</li>
        <li>Dejar de fumar</li>
        <li>Adoptar un horario definido para poder evacuar</li>
        <li>Tecnica de masaje abdominal</li>
        </ul>

        <p class="fs-4 fw-light mt-4" align="justify">
        Si las medidas anteriores no son suficientes o no es posible llevarlas a cabo por las condiciones del paciente, el médico, puede recurrir a ciertos medicamentos, como laxantes, combinaciones de los mismos y también se pueden combinar fármacos procinéticos que estimulan el movimiento intestinal. Nunca se debe optar por la automedicación, ni por las recomendaciones de amigos o conocidos.
        </p>

        <p class="fs-4 fw-light mt-4" align="justify">
        Cuando no ha sido posible controlar el estreñimiento con laxantes, puede ser necesario realizar una desimpactación rectal, que es la extracción manual de las heces duras que no pueden salir con el pujo. También puede requerirse de uno o varios enemas para que se reblandezcan las heces y puedan salir con más facilidad. Estos procedimientos aunque son fáciles, se recomienda los realice el personal de salud
        </p>

      </div>
      <div class="tab-pane fade" id="list-6" role="tabpanel" aria-labelledby="list-6-list">
        <h2>Técnica para enema o lavativa</h2>

        <p class="fs-4 fw-light mt-4" align="justify">
        Es la introducción de sustancias en el colon a través del recto con la finalidad de eliminar la materia fecal.<br><br>
        <strong>Material y equipo: Todo se puede comprar en la farmacia</strong>
      </p>

      <ul class="fs-4 fw-light mt-4">
      <li>Sistema irrigador (tipo jarra) y/o bolsa para enema desechable</li>
      <li>La bolsa desechable es más apropiada por ser práctica en su uso</li>
      <li>Sonda rectal prelubricada</li>
      <li>Solución tibia para administrar; puede ser agua tibia de la llave.</li>
      <li>Guantes desechables</li>
      <li>Lubricante hidrosoluble (gel); puede ser jabón neutro líquido</li>
      <li>Soporte para la solución (pentapié); un lugar donde colgar la solución , puede ser un fuerte clavo en la pared</li>
      <li>Cómodo (papel higiénico); si la persona no puede ir al baño</li>
      <li>Pinzas de clamp en caso necesario; pueden ser pinzas para colgar ropa</li>
      <li>Bolsa para desechos (para basura)</li>
      </ul>

      <p class="fs-4 fw-light mt-4" align="justify">
      <strong>Procedimiento</strong>
      </p>

<ol class="fs-4 fw-light mt-4" align="justify">
<li>Lavarse las manos</li>
<li>Explicar al paciente en qué consiste la realización del procedimiento, respetando al máximo su intimidad</li>
<li>Preparar y tener dispuesto el material</li>
<li>Colocar el equipo irrigador y/o la bolsa con la solución a administrar, previamente tibia a temperatura corporal en el soporte ( o clavo de la pared ) a una altura máxima de 50 cm (medio metro) sobre el nivel del paciente</li>
<li>Conectar la sonda al extremo del tubo transportador del irrigador o bolsa.</li>
<li>Lubricar la punta de la sonda</li>
<li>Extraer el aire del sistema del equipo para irrigar y de la sonda</li>
<li>Pinzar el sistema para evitar que la solución siga saliendo</li>
<li>Posición: Si no está contraindicado, el paciente deberá estar acostado de lado izquierdo, con el muslo y pierna (extremidad inferior) derecha flexionada. No importa si no tiene exactamente la posición mencionada</li>
<li>Colocarse los guantes</li>
<li>Separar con una mano los glúteos para visualizar el orificio anal; con la otra mano introducir suavemente el extremo distal de la sonda rectal, unos 10 cm aproximadamente</li>
<li>Despinzar el sistema y dejar pasar lentamente la solución al paciente, de tal manera que éste lo tolere sin molestias. Terminar de administrar la cantidad de solución indicada</li>
<li>Pinzar el sistema y retirar suavemente la sonda, desecharla</li>
<li>Colocar al paciente acostado de lado derecho. Motivar al paciente para que retenga la solución de 5 a 10 minutos.</li>
<li>Tener listo el cómodo cerca del paciente y/o ayudarlo a que evacúe en el sanitario (baño) cuando el lo solicite</li>
<li>Asear al paciente o proporcionarle los medios (papel sanitario) para que él, si está en condiciones, se lo realice solo.</li>
<li>Registrar la cantidad de solución administrada y si se cumplió el objetivo para el cual fue administrada. Asimismo incluir la fecha, hora, características de la eliminación e incidencias durante el procedimiento.</li>
</ol>

      </div>
      <div class="tab-pane fade" id="list-7" role="tabpanel" aria-labelledby="list-7-list">
        <h2>Consecuencias del estreñimiento</h2>

        <p class="fs-4 fw-light mt-4" align="justify">
        Si no se vigila o atiende el estreñimiento puede llegar a causar:
        </p>

        <ul class="fs-4 fw-light mt-4">
        <li>Distensión abdominal</li>
        <li>Dolor abdominal intenso</li>
        <li>Falsa oclusión intestinal</li>
        <li>Vómito</li>
        <li>Diarrea ( rebosamiento)</li>
        <li>Confusión mental y delirio</li>
        <li>Hemorroides: Inflamación de las venas del ano</li>
        <li>Fisura anal: Ruptura de la piel del ano Las heces grandes o duras pueden provocar pequeñas rupturas en el ano</li>
        <li>Retención fecal: Heces duras que se atascan en los intestinos</li>
        <li>Rectocele: Al pujar una porción del recto se sale del ano</li>
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