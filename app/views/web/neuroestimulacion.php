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
    
    <h1 class="mt-4">Control del Dolor con Estimulación de la Médula Espinal o Neuroestimulación Espinal (NEE)</h1>
    <div class="mt-4">
    <a class="text-success" href="<?=LINK_HOME;?>"><strong>Home</strong></a> <span class="text-success"><strong>/</strong></span> <small class="text-black-50">Control del Dolor con Estimulación de la Médula Espinal o Neuroestimulación Espinal (NEE)</small>
    </div>
  
<div class="row">
  <div class="col-12 col-sm-4 mt-4">
    <div class="list-group" id="list-tab" role="tablist">
      <a class="list-group-item list-group-item-action active fs-5 fw-light" id="list-1-list" data-bs-toggle="list" href="#list-1" role="tab" aria-controls="1">¿Qué es la terapia de neuroestimulación?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-2-list" data-bs-toggle="list" href="#list-2" role="tab" aria-controls="2">Objetivo del neuroestimulador</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-3-list" data-bs-toggle="list" href="#list-3" role="tab" aria-controls="3">¿Como funciona?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-4-list" data-bs-toggle="list" href="#list-4" role="tab" aria-controls="4">Ventajas</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-5-list" data-bs-toggle="list" href="#list-5" role="tab" aria-controls="5">¿Cómo se si el neuroestimulador me va a ayudar?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-6-list" data-bs-toggle="list" href="#list-6" role="tab" aria-controls="6">¿Cómo se pone el neuroestimulador?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-7-list" data-bs-toggle="list" href="#list-7" role="tab" aria-controls="7">¿Cuánto dura?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-8-list" data-bs-toggle="list" href="#list-8" role="tab" aria-controls="8">¿Qué cuidados debo tener?</a>
      <a class="list-group-item list-group-item-action fs-5 fw-light" id="list-9-list" data-bs-toggle="list" href="#list-9" role="tab" aria-controls="9">Complicaciones</a>

    </div>
  </div>
  <div class="col-12 col-sm-8 mt-4">
    <div class="tab-content" id="nav-tabContent">
      <div class="tab-pane fade show active" id="list-1" role="tabpanel" aria-labelledby="list-1-list">
        
          <h2>¿Qué es la terapia de neuroestimulación?</h2>
          <p class="fs-4 fw-light mt-4" style="text-align: justify;">
          La neuroestimulación también es conocida como terapia de neuromodulación, estimulación de la columna vertebral o estimulación de la médula espinal, es una terapia que ayuda al alivio del dolor y recupera la capacidad de llevar una mejor calidad de vida no condicionada por el dolor. La estimulación de la médula espinal (NEE) se refiere al uso de la energía eléctrica pulsada cerca de la médula espinal para el control del dolor crónico.</p>

      </div>
      <div class="tab-pane fade" id="list-2" role="tabpanel" aria-labelledby="list-2-list">
        
        <h2>Objetivo del neuroestimulador</h2>
        <p class="fs-4 fw-light mt-4" style="text-align: justify;">
          El objetivo del neuroestimulador es aliviar o reducir el dolor, no lo elimina por completo. El grado de disminución del dolor, dependerá del padecimiento de cada paciente.
      </p> 

      </div>
      <div class="tab-pane fade" id="list-3" role="tabpanel" aria-labelledby="list-3-list">
        
        <h2>¿Como funciona ?</h2>
        <p class="fs-4 fw-light mt-4" style="text-align: justify;">

En condiciones normales, las señales nerviosas de todo el cuerpo se transmiten a la médula espinal y después al cerebro, es en la corteza cerebral en donde se traducen las señales eléctricas en sensaciones como por ejemplo dolor.<br><br>

La NEE consiste en la implantación un electrodo en el espacio epidural, el cual se conecta a un generador o batería para transmitir esta energía pulsada a través de la médula espinal o cerca de las raíces de los nervios deseados. Posteriormente mediante la administración de impulsos eléctricos se interrumpe la transmisión de las señales de dolor que llegan al cerebro generando alivio.<br><br>

El generador del neuroestimulador (o batería) es un pequeño dispositivo que se implanta bajo la piel normalmente en el abdomen o en la zona de los glúteos. El neuroestimulador genera señales eléctricas que viajan desde el dispositivo (neuroestimulador) a través de unos cables (electrodos) que bloquean la señal de dolor cuando va al cerebro. Sustituye las sensaciones de dolor por una sensación de hormigueo que cubre las áreas específicas en las que se percibe el dolor.<br><br>

Este tratamiento no tiene los efectos secundarios de los analgésicos por lo que se reduce la morbilidad farmacológica. Los propósitos principales de NEE son mejorar la calidad de vida y la función física mediante la reducción de la severidad del dolor y sus características asociadas.<br><br>

La NEE esta indicada preferentemente cuando el dolor crónico es de tipo neuropático y/o vascular rebelde a tratamiento farmacológico, fisioterapia-rehabilitación y bloqueos nerviosos, siempre y cuando la causa que origina el dolor no se pueda tratar quirúrgicamente o por otros tratamientos no quirúrgicos. La selección correcta de pacientes candidatos a neuroestimulación medular en algunos casos todavía es motivo de discusión. En nuestra unidad se siguen las indicaciones aprobadas por la FDA que incluyen los siguientes:

        </p>

        <table class="table table-bordered table-sm fs-5 fw-light mt-4">
          <tr>
            <td colspan="2" class="align-middle text-center table-light"><strong>Indicaciones para la Neuroestimulación Espinal (NEE)</strong></td>
          </tr>
          <tr>
            <td class="align-middle text-center table-light">Diagnosticos</td>
            <td class="align-middle text-center table-light">Causas y Observacione</td>
          </tr>
          <tr>
            <td class="align-middle">Sindrome de Espalda Fallida o Síndrome Postlaminectomía</td>
            <td class="align-middle">En general esta indicado cuando hay dolor neuropático, con éxito mayor al 50%, aunque en algunas ocasiones es difícil diferenciar el dolor nociceptivo del componente neuropático. La prueba terapéutica ayuda a determinar el componente principal de dolor.</td>
          </tr>

          <tr>
            <td class="align-middle">Síndrome Complejo Regional Doloroso I y II</td>
            <td class="align-middle">Los pacientes deben ser diagnosticados correctamente, responder a un bloqueo simpático previo (como diagnóstico), y demostrar mejoría significativa de la intensidad del dolor (> 5/10 en una escala analógica visual), junto con los hallazgos físicos que son de diagnóstico de este trastorno.</td>
          </tr>
          <tr>
            <td class="align-middle">Radiculopatías o Poliradiculopatías o Plexopatía</td>
            <td class="align-middle">
              <strong>Secundario a:</strong><br>
              Fibrosis epidural<br>
              Aracnoiditis<br>
              Lesion intrínseca de una raíz nerviosa o radiculitis.<br>
              <strong>Lesion radicular o ganglionar:</strong><br>
              Radiculopatía cervical o toracica.<br>
              Radiculopatía lumbosacra: ej. sindrome cauda equina.<br>
              Post quirúrgica<br>
              Trauma<br>
              Neuralgia Postherpética.<br>
            </td>
          </tr>
          <tr>
            <td class="align-middle">Neuropatía periférica</td>
            <td class="align-middle">
            Lesion de nervios periféricos por:
            <ul>
              <li>Trauma</li>
              <li>Cirugia: como en los nervios;
                <ul>
                  <li>Ilioinguinal, iliohipogastrico, genitofemoral, intrapatelar, safeno, etc.</li>
                  <li>Dolor en la herida quirúrgica.</li>
                  <li>Radioterapia para el tratamiento oncologico.</li>
                </ul></li>
            </ul>
          </td>
          </tr>
          <tr>
            <td class="align-middle">Neuropatia por terapia oncológica</td>
            <td class="align-middle">
              Dolor neuropático por tratamientos oncológicos.
              <ul>
                <li>Post quirúrgicos</li>
                <li>Post radioterapia</li>
              </ul>
            </td>
          </tr>
          <tr>
            <td class="align-middle">Dolor por enfermedad vascular periférica</td>
            <td class="align-middle">
Ateroesclerosis con dolor en reposo y claudicación (Fontaine grado III y IV)<br>
Angiopatía diabetica<br>
Sindrome de Buerguer<br>
Desordenes vasoespásticos<br>
Síndrome de Raynaud<br>
Frosbite (congelación)<br>
            </td>
          </tr>
        </table>

        <p class="fs-4 fw-light mt-4" style="text-align: justify;">
        El diagnóstico diferencial del dolor neuropático siempre debe excluir los procesos metabólicos transitorios, tales como la diabetes, las etiologías infecciosas, neuropatías por atrapamiento y dolor que se irradia a menudo asociada con (articulaciones sacroilíacas y cigapofisiarias) artropatías, patología visceral o trastornos miofasciales. En caso de presentarlos, sera necesario tratarlos previamente.<br>
En caso de no tener un diagnostico definitivo que genere la causa del dolor, deberá someterse al protocolo de evaluación.
</p>



      </div>
      <div class="tab-pane fade" id="list-4" role="tabpanel" aria-labelledby="list-4-list">
        
        <h2>Ventajas</h2>


<ul class="fs-4 fw-light mt-4">
  <li>Reducción significativa del dolor</li>
  <li>Tiene un programador portátil que ayuda a controlar la estimulación para realizar las actividades de la vida diaria (caminar, sentarse, terapia, ejercicio, etc)</li>
  <li>Mejora funcional y de la calidad de vida en las actividades diarias</li>
  <li>Reducción de medicamentos analgésicos orales</li>
  <li>Es reversible</li>
</ul>



      </div>

      <div class="tab-pane fade" id="list-5" role="tabpanel" aria-labelledby="list-5-list">
        
        <h2>¿Cómo se si el neuroestimulador me va a ayudar?</h2>

      <p class="fs-4 fw-light mt-4" style="text-align: justify;">
      Se realiza una fase de prueba que permite probar si la neuroestimulación es el tratamiento adecuado para cada paciente. Se coloca un electrodo fino cerca de la médula espinal y se conecta a un neuroestimulador externo, de esta manera se evalúa la eficacia de la terapia de neuroestimulación para control de dolor. Si la fase de prueba es exitosa, se programa la colocación del neuroestimulador permanente.
      </p>

      </div>

     <div class="tab-pane fade" id="list-6" role="tabpanel" aria-labelledby="list-6-list">
        
        <h2>¿Cómo se pone el neuroestimulador?</h2>

      <p class="fs-4 fw-light mt-4" style="text-align: justify;">
      Una vez que se vieron resultados en la fase de prueba y se confirma que la terapia de neuromodulación es adecuada para el paciente, se realiza un procedimiento sencillo y poco invasivo para colocar el neuroestimulador.
      </p>

      </div>

      <div class="tab-pane fade" id="list-7" role="tabpanel" aria-labelledby="list-7-list">
        
        <h2>¿Cuánto dura?</h2>

      <p class="fs-4 fw-light mt-4" style="text-align: justify;">
      El estimulador interno tiene una pila recargable que dura hasta 25 años o una batería no recargable que puede durar 8 años, dependiendo de la intensidad del impulso que cada paciente necesite. Cuando se termina la pila, es necesario reemplazar el estimulador en una nueva intervención.
      </p>

      </div>

     <div class="tab-pane fade" id="list-8" role="tabpanel" aria-labelledby="list-8-list">
        
        <h2>¿Qué cuidados debo tener?</h2>

      <p class="fs-4 fw-light mt-4" style="text-align: justify;">
      Evitar exposición a imanes (Dispositivos de seguridad en aeropuertos, líneas de alta tensión, aparato de resonancia magnética, etc): Si tiene que hacerlo, deberá notificar a la persona encargada que es portador de un neuroestimulador.
      </p>

      </div>

           <div class="tab-pane fade" id="list-9" role="tabpanel" aria-labelledby="list-9-list">
        
        <h2>Complicaciones</h2>

      <p class="fs-4 fw-light mt-4" style="text-align: justify;">
      Las complicaciones que son poco frecuentes, pueden ocurrir durante la colocación de electrodos epidurales para la prueba:
      </p>

      <ul class="fs-4 fw-light">
        <li>Cefalea (dolor de cabeza) puede ser intenso y requerir reposo, es temporal</li>
        <li>Infección en el punto de salida del electrodo en la piel</li>
        <li>Desplazamiento del electrodo cuando hay movimientos bruscos</li>
      </ul>

      <p class="fs-4 fw-light mt-4" style="text-align: justify;">
      Las complicaciones que pueden ocurrir tras la implantación definitiva:
      </p>

      <ul class="fs-4 fw-light">
        <li>Hemorragia o hematoma en la zona de colocación: puede requerir intervención quirúrgica</li>
        <li>Seroma (acumulación de líquido) en la zona de implantación: puede requerir intervención quirúrgica</li>
        <li>Apertura de la herida quirúrgica</li>
        <li>Infección en la zona del estimulador y/o electrodos que podría requerir intervención quirúrgica y retirar el estimulador</li>
        <li>Desplazamiento o rotura de los electrodos</li>
      </ul>

      <p class="fs-4 fw-light mt-4" style="text-align: justify;">
      Para la colocación definitiva de un NEE de debe realizar una fase de prueba eficaz que cubra la zona dolorosa. Haber tenido fracaso con los tratamientos analgésicos convencionales y no tener una patología crónica grave que impida la colocación del la NEE. Actualmente se han colocado neuroestimuladores en la Clinica del Dolor del Hospital Angeles Lomas a pacientes con dolor de difícil control, logrando retirar mas del 50% de los analgésicos previos al dispositivo y mejorando la calidad de vida de cada paciente y su familia.<br><br>

      <strong>Si usted considera que es candidato a esta terapia saque una cita para mas detalles.</strong>
      </p>

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