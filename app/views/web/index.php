
<!DOCTYPE html>
<html lang="es">

<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">	
<link rel="icon" type="image/png" href="<?=RUTA_IMAGES ?>/logo-clinica.png" />	
<link rel="shortcut icon" type="image/x-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png" />
<title><?=$data['title'];?></title>
<meta name="description" content="La clínica de dolor y cuidados paliativos del Hospital Ángeles Lomas es un grupo de especialistas líderes en dolor.">
<meta name="viewport" content="width=device-width initial-scale=1.0">
<link href="<?=RUTA_WEB_CSS;?>bootstrap.css" rel="stylesheet" />
<link href="<?=RUTA_WEB_CSS;?>navbar.min.css" rel="stylesheet" />
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.0/animate.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?=RUTA_JS;?>loader.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style type="text/css">
	    
body {
background: url(assets/images/fondo/fondo-hospital-angeles.jpg) no-repeat center center fixed;
background-blend-mode:  hard-light;
background-size: cover;
-moz-background-size: cover;
-webkit-background-size: cover;
-o-background-size: cover;
}

.fondo-color{
background: var(--primary);
}

.border-b{
border-bottom: 1px solid #EAEAEA;
}

.fondo-color-claro{
background: var(--primary-claro);
}

.fondo-color-claro2{
background: var(--primary-claro2);
}

.margin-lif-10{
margin-left: 10px;
}

.color-secundario{
color: var(--secundario);
}


</style>


<script type="text/javascript">

	$(document).ready(function($){

var URLactual = window.location;

if (localStorage){
    if(localStorage.getItem('URLSERVER') !== undefined && localStorage.getItem('URLSERVER')){

    }else{
    localStorage.setItem('URLSERVER', URLactual);	
    }
}

});


function verMasDiv(valDiv){

if(valDiv <= 6){

var divTipos = document.getElementById("tiposDolor"+valDiv);
divTipos.style.display = "block";

contadorMas= valDiv + 1;
contadorMenos = valDiv;

var valMas = "";
var valMenos = "";

if(valDiv == 6){
valMas = "d-none"
}

$("#btnContador").html(' <img src="<?=RUTA_IMG_ICONOS;?>flecha-abajo2.png" class="botonesControl p-2 '+valMas+'" onclick="verMasDiv('+contadorMas+')"> <img src="<?=RUTA_IMG_ICONOS;?>flecha-arriba2.png" class="botonesControl p-2 '+valMenos+'" onclick="verMenosDiv('+contadorMenos+')">');
}
}


function verMenosDiv(valDiv){

var divTipos = document.getElementById("tiposDolor"+valDiv);
divTipos.style.display = "none";

var valC = valDiv - 1;
var divTiposC = document.getElementById("tiposDolor"+valC);
divTiposC.style.display = "block";

contadorMas= valDiv;
contadorMenos = valDiv-1;

var valMas = "";
var valMenos = "";

if(valDiv == 2){
valMenos = "d-none"
}


$("#btnContador").html(' <img src="<?=RUTA_IMG_ICONOS;?>flecha-abajo2.png" class="botonesControl p-2 '+valMas+'" onclick="verMasDiv('+contadorMas+')"> <img src="<?=RUTA_IMG_ICONOS;?>flecha-arriba2.png" class="botonesControl p-2 '+valMenos+'" onclick="verMenosDiv('+contadorMenos+')">');
}

</script>

</head>

<body>
<div class="LoaderPage"></div>
<!-------- BARRA DE NAVEGACION ---------->
<?php include_once __DIR__ . '/../components/web-navbar.php';?>


<div class="container" style="margin-top: 15em;margin-bottom: 21em;">
<div class="text-center">

<h1 class="text-white" style="text-shadow: 2px 2px 5px #424242;font-size: 3rem;">
  Clínica del Dolor y Cuidados Paliativos <br>Hospital Ángeles Lomas</h1>
</div>
</div>

<div style="box-shadow: 0px 0px 40px #333333;">
<div class="row g-0">
<div class="shadow col-12 col-sm-3 fondo-color ">
<div class="p-4">

<div class="border-b pb-1">
<h5 class="text-white">
<span><i class="bi bi-clock-history"></i></span>	
<span class="margin-lif-10">Horarios de atención:</span>
</h5>
</div>

<div class="mt-3">
<p class="text-white">
<strong>Lunes a Viernes:</strong><br><br>
10:00 a 14:00 hrs.<br> 
16:00 a 20:00 hrs.</p>
</div>
					

</div>
</div>
			
<div class="shadow col-12 col-sm-4 fondo-color-claro">
<div class="p-4">

<div class="border-b pb-1">
<h5 class="text-white">
<span><i class="bi bi-telephone"></i></span>						
<span class="margin-lif-10">Telefonos:</span>
</h5>
</div>

<div class="mt-3">
<p class="text-white">
55-5247-0116<br> 
55-5246-5000, ext. 3008<br> 

<strong>En caso de urgencias comuníquese al:</strong><br> 
55-5247-0116 <br>
<small>Jefatura de Clínica del Dolor</br> <strong>Dra. Rocío Torres Méndez</strong></small></p>
</div>
</div>
</div>


<div class="shadow col-12 col-sm-5 fondo-color-claro2">
<div class="p-4">

<div class="border-b pb-1">
<h5 class="text-white">
<span><i class="bi bi-envelope"></i></span>							
<span class="margin-lif-10">Emails:</span>
</h5>
</div>

<div class="mt-3">
<div class="overflow-auto">
<p class="text-white">
dolor_angeleslomas@tratamientosdeldolor.org<br>
clinicadeldolor@tratamientosdeldolor.org
</p>
</div>
</div>
					
</div>
</div>
</div>
</div>

<div class="bg-white">
<div class="container pt-4 pb-4">

	<h2 class="text-center">Clínica del Dolor y Cuidados Paliativos</h2>
	<img class="mt-4" src="<?=RUTA_IMG_FONDO;?>grupo-doctores.jpg" width="100%">

<p class="fs-4 fw-light mt-4" style="text-align: justify;">
Somos un grupo de especialistas líderes en dolor que en conjunto con otras especialidades (Neurología, Ortopedia, Cirugía, Psicología, Rehabilitación, entre otros) ofrece una amplia gama de tratamientos para dolor agudo no controlado, dolor postoperatorio y dolor crónico de todo tipo (neuropático, oncológico, postquirúrgico).<br/><br/> Los tratamientos están basados en las guías y protocolos internacionales de control de dolor. Cada paciente es evaluado de manera integral para ofrecer un tratamiento individualizado para el control del dolor con medicamentos y procedimientos de vanguardia.</p>

<div class="text-end mb-5">
<a class="btn btn-light mt-5" href="<?=LINK_QUIENES_SOMOS;?>" role="button">Ver más <i class="bi bi-chevron-right"></i></a>
</div>
	
</div>	
</div>


<div class="bg-light">
<div class="container pt-4 pb-4">
<div class="row mt-5">

<div class="col-12 col-sm-4 mb-5">
<img src="<?=RUTA_IMG_FONDO;?>fondo-dolor.jpg" width="100%">
</div>

<div class="col-12 col-sm-8">
<div class="">
<h2 class="text-center">¿Qué es el dolor?</h2>	
<p class="fs-4 fw-light mt-5" style="text-align: justify;">
El dolor es un problema complicado, a menudo debilitante que puede tener un impacto importante en el bienestar físico y mental de quien lo padece. Debido a que es una experiencia personal y subjetiva, su valoración es complicada y su evaluación es vital.
<br><br>
Según la International Asociation for the Study of Pain (IASP)— es es una experiencia sensorial (objetiva) y emocional (subjetiva), generalmente desagradable que esta asociada a una lesión tisular o se expresa como si ésta existiera.
</p>

<div class="text-end mt-5">
<a class="btn btn-light" href="<?=LINK_QUEES_DOLOR;?>">Ver más <i class="bi bi-chevron-right"></i></a>
</div>
</div>							
</div>

</div>
</div>	
</div>


<div class="bg-white">
<div class="container pt-4 pb-4">

<h2 class="text-center mt-4">Tipos de dolor</h2>

<div class="row mt-5">

<div class="col-12 col-md-6">
<div class="bg-light p-4">
<h3 class="text-center color-secundario">Dolor agudo</h3>

<div class="text-center mt-4"><img src="<?=RUTA_IMG_ICONOS;?>señal-dolor.png"></div>
<p class="fs-4 fw-light mt-4" align="justify">
<strong>El dolor agudo</strong> suele ser una señal de alarma para indicarnos que existe una lesión en los tejidos o una enfermedad. La intensidad del dolor se asocia con el nivel y severidad, pero también tiene un componente individual pues cada persona puede percibir y sentir el dolor con intensidad diferente.
</p> 

</div>
</div>

<div class="col-12 col-md-6">
<div class="bg-light p-4">
<h3 class="text-center color-secundario">Dolor es crónico</h3>

<p class="fs-4 fw-light mt-4" align="justify">
Si el dolor persiste más del tiempo de la curación normal de la enfermedad que lo causo, que se considera aproximadamente tres meses, entonces estamos hablando de dolor crónico y puede ser que no responda al tratamiento médico habitual. 
<br>
El dolor crónico se considerad una enfermedad que se genero por cambios en el sistema nervioso, es una secuela de alguna lesión o enfermedad que incluso ya pudo haber sido curada. El dolor crónico no siempre está relacionado con el diagnóstico original o lesión, si es que hubo alguno.
</p> 

</div>
</div>
		
</div>

</div>		
</div>


<div class="fondo-color-claro pt-4 pb-4">

<!-- OPCION NUMERO 1  --->
<div class="container" id="tiposDolor1">
<h2 class="text-center text-white mt-4 mb-4">Enfermedades que causan dolor</h2>

<div class="row mt-5">                            

  <div class="col-12 col-md-3 mb-4">              
    <div class="card shadow card border-0">
    <div class="card-content">
        
      <div class="card-header-blue">
      <h5 class="color-secundario text-center pt-3">Dolor por Cáncer</h5>
      </div>

      <div class="card-body" align="center">
      <img src="<?=RUTA_IMG_ICONOS;?>dolor1.png" class="mb-3" >

      <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
      Más de la mitad de pacientes con cáncer tiene dolor desde el inicio de su padecimiento y hasta un 95 % de personas que fallecen por cáncer tendrán dolor severo al final de sus días 
      </p>

      <div class="text-end pt-4">
      <a class="btn btn-light" href="<?=LINK_DOLOR_CANCER;?>"><small> Ver más </small> <i class="bi bi-chevron-right"></i></a>    
      </div>  

      </div>     

    </div>
    </div>
  </div>

  <div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
        
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Causalgia</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor6.png"  class="mb-3">

    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Dolor crónico del brazo o la pierna después de una lesión, cirugía, derrame cerebral o infarto. 
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>
    </div>  

    </div>
     
    </div>
  </div>
</div>

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
        
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Ciática</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor8.png"  class="mb-3">

    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Dolor que se extiende a lo largo del nervio ciático, desde la espalda baja hasta una o ambas piernas.
    </p>

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button> 
    </div>  

    </div>
    </div>
  </div>
</div>

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
        
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Compresión Medular</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor5.png"  class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Compresión externa de la médula espinal que causa síntomas neurológicos.
    </p>

    <div class="text-end pt-5">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>  

    </div>
        
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
        
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Paciente en Fase Terminal</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor9.png"  class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Los cuidados para pacientes terminales ayudan a las personas con enfermedades que no se pueden curar y que están a punto de morir. 
    </p>

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>  
    </div> 

    </div>
      
    </div>
  </div>
</div>

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
        
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Cuidados Paliativos</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor10.png"  class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Previenen y alivian el sufrimiento así como brindan una mejor calidad de vida posible a pacientes que padecen de una enfermedad grave.
    </p>

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>    
    </div> 

    </div>
        
    </div>
  </div>
</div>

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
        
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor al Final de la Vida</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor3.png"  class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    La presencia de dolor al final de la vida se sitúa entre el segundo y el quinto síntoma más frecuente (69-85%), según los estudios. 
    </p>

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>     
    </div>

    </div>        
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
        
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor en el Cuello</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor2.png"  class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    El dolor de cuello puede tener causas que no se deben a una enfermedad subyacente. Por ejemplo, el esfuerzo prolongado.
    </p>

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Leer más</small> <i class="bi bi-chevron-right"></i></button>    
    </div>

    </div>
    
    </div>
  </div>
</div>
                  
</div>
</div>



<!-------- OPCION NUMERO 2 ---------->

<div class="container" id="tiposDolor2" style="display:none;">
<div class="row mt-2">   

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
        
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Paciente Critico Crónico</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor9.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Síndrome que afecta a los pacientes ingresados en las unidades de terapia intensiva por un
    largo periodo, sin lograr la recuperación clínica.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>      
    </div> 

    </div>

    </div>
  </div>
</div>

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
        
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor en Tórax</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor5.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Malestar en el pecho que puede incluir un dolor leve, una sensación de ardor, un dolor punzante agudo y dolor que se irradia hacia el cuello o los hombros.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>       
    </div>

    </div>
  
    </div>
  </div>
</div>

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
        
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor de Espalda</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor8.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Malestar físico que se produce en cualquier parte de la columna o la espalda que va de moderada a incapacitante.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>       
    </div>

    </div>
    
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
        
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor Hombros, Rodillas y Otras Articulaciones</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor4.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    El dolor articular se presenta en forma de pinchazo, dolor agudo, rigidez e inflamación en la articulación.
    </p> 

    <div class="text-end">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>    
    </div> 

    </div>
      
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
        
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor De Cadera</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor1.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    El dolor de cadera es una queja frecuente que puede deberse a una extensa variedad de problemas.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>

    </div>

    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
        
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor Articulación Sacroiliaca y Sacroileitis</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor8.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Inflamación de una o ambas articulaciones sacroilíacas, ubicadas en la zona donde se conectan la parte baja de la columna vertebral y la pelvis.
    </p> 

    <div class="text-end">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>  

    </div>
      
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor Inguinal</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor2.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Dolor que se produce en la zona en la que se unen el muslo superior interno y la parte baja del abdomen.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>    
    </div> 

    </div>
      
    </div>
  </div>
</div>

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
        
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor de Hernia de Disco y Enfermedad Degenerativa</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor12.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Afección caracterizada por un problema en el disco cartilaginoso ubicado entre los huesos de la columna vertebral.
    </p> 

    <div class="text-end">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>    
    </div>   

    </div>
    
    </div>
  </div>
</div>

</div>

</div>


<!-------- OPCION NUMERO 3 ---------->
<div class="container" id="tiposDolor3" style="display:none;">
<div class="row mt-2"> 

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor Mantenido por el Simpático</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor5.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Grupo de desordenes en los cuales puede presentarse síndromes dolorosos malignos como benignos, siendo este él más severo.
    </p> 

    <div class="text-end">
    <button class="btn btn-light"> <small> Leer más</small> <i class="bi bi-chevron-right"></i></button>    
    </div> 

    </div>
      
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor Neuropático</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor1.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Dolor generado en el sistema nervioso central o en periférico que aparece sin necesidad de que exista realmente una amenaza.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>    
    </div>

    </div>

    </div>
  </div>
</div>



<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor Miofascial</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor7.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Enfermedad en la que la presión en ciertos puntos sensibles en los músculos provoca dolor en partes del cuerpo.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>

    </div>

    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor Pélvico</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor2.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Dolor localizado en la zona baja del abdomen que puede ser debido a alguna alteración aguda o crónica en los órganos.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>     
    </div>  

    </div>

    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor Postoperatorio Agudo</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor9.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Dolor de inicio reciente, duración limitada que aparece como consecuencia de la intervención quirúrgica.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>     
    </div> 

    </div>
 
    </div>
  </div>
</div>

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor Postoperatorio Crónico</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor5.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Dolor que persiste más de 3-6 meses como consecuencia de la intervención quirúrgica.
    </p> 

    <div class="text-end pt-5">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>  
    </div>

    </div>

    </div>
  </div>
</div>

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor Postlaminectomía</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor8.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Aparición o persistencia de dolor lumbar en un paciente que ha sufrido una operación en la columna para tratar un dolor.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>  
    </div> 

    </div>
        
    </div>
  </div>
</div>



<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Enfermedad Vascular Periférica</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor3.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Afección circulatoria en la que el estrechamiento de los vasos sanguíneos reduce la irrigación sanguínea a los miembros.</p> 
  
    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>

    </div>
    
    </div>
  </div>
</div>

</div>
</div>


<!-------- OPCION NUMERO 4 ---------->
<div class="container" id="tiposDolor4" style="display:none;">
<div class="row mt-2"> 


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor por Sección Medular</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor8.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Dolor espinal o radicular que causa cambios permanentes en la fortaleza, la sensibilidad y otras funciones corporales debajo del sitio de la lesión.</p> 
    
    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>

    </div>
  
    </div>
  </div>
</div>



<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor por Osteoartritis</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor4.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Es la forma más común de artritis. Causa dolor, inflamación y disminución de los movimientos en las articulaciones.</p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>

    </div>          
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor por Osteoporosis</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor3.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Enfermedad que adelgaza y debilita los huesos, lo que causa que se quiebren fácilmente.</p> 

    <div class="text-end pt-5">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>

    </div>      
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor por Radioterapia (Neuritis Postradiación)</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor1.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    La radiación no solo destruye o hace lento el crecimiento de las células cancerosas, puede también afectar las células sanas.
    </p> 

    <div class="text-end">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>    
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor Secundario a Cirugía o Trauma</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor9.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Lesión de alguna estructura nerviosa durante el acto quirúrgico generada por un corte, avulsión, contusión, retracción o estiramiento de la misma.
    </p> 

    <div class="text-end">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>     
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor Secundario a Dolor Oncológico</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor5.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Puede surgir por la presión ejercida por un tumor, por infiltración de tejido, por procedimientos de diagnóstico  o por la respuesta inmunológica.
    </p> 

    <div class="text-end">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>     
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Dolor Visceral</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor10.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Se debe a lesiones o disfunciones de los órganos internos, aunque hay vísceras que no duelen, como el hígado o el pulmón.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>   
    </div>
  </div>
</div>

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Espasticidad Relacionada al Dolor</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor6.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Enfermedades neurológicas que afecta a la movilidad y causa graves complicaciones como dolor, limitación articular, contracturas y úlceras por presión.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>     
    </div>
  </div>
</div>

</div>
</div>


<!-------- OPCION NUMERO 5 ---------->
<div class="container" id="tiposDolor5" style="display:none;">
<div class="row mt-2"> 

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Eutanasia</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor11.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Intervención deliberada para poner fin a la vida de un paciente sin perspectiva de cura.
    </p> 

    <div class="text-end pt-5">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>   
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Fracturas Vertebrales</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor8.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Intervención deliberada para poner fin a la vida de un paciente sin perspectiva de cura.
    </p> 

    <div class="text-end pt-5">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>   
    </div>
  </div>
</div>

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Ley de Voluntad Anticipada</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor9.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Ley con el cual una persona expresa su deseo acerca de las atenciones médicas que desea recibir en caso de padecer una enfermedad terminal.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>     
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Lumbalgia</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor6.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Presencia de dolor en la región lumbar, es decir, en la espalda y cintura, que con frecuencia se recorre a los glúteos y muslos.
    </p>
    
    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div> 
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Lesión en Medula Espinal</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor8.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Lesión en cualquier parte de la médula espinal que suele causar la pérdida permanente de la fuerza, la sensibilidad y la movilidad debajo del lugar de la lesión.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>    
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Miembro Fantasma Doloroso</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor1.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Cuadro de sensaciones, dolor, picor, disestesias que sienten algunas personas en un miembro amputado, que persiste pese a no tenerlo.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>    
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Neuralgia Postherpética</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor5.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Trastorno que afecta a las fibras nerviosas y la piel, que causa un dolor quemante que dura mucho tiempo después de que el sarpullido y las ampollas del herpes zóster han desaparecido.
    </p> 

    <div class="text-end pt-4">
    <a class="btn btn-light" href="<?=LINK_DOLOR_NEURALGIA_POSTHERPETICA;?>" role="button"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></a>   
    </div>

    </div>
    <div class="text-end">  
    </div>       
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Neuropatía Diabética</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor6.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Daño neurológico que puede ocurrir como consecuencia de la diabetes, esta enfermedad suele afectar con mayor frecuencia a las piernas y los pies.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>      
    </div>
  </div>
</div>

</div>
</div>


<!-------- OPCION NUMERO 6 ---------->
<div class="container" id="tiposDolor6" style="display:none;">
<div class="row mt-2"> 

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Neuralgia del Trigémino</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor1.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Trastorno de dolor crónico que afecta el nervio trigémino, que transmite las sensaciones del rostro al cerebro.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>     
    </div>
  </div>
</div>

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Neuralgia del Occipital</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor2.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Problema de salud caracterizado por un fuerte dolor en la parte posterior de la cabeza o la base del cráneo.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>       
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Neuralgia Intercostal</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor12.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Dolor que afecta la distribución de los nervios torácicos, que cómo su nombre indica “intercostal” afecta los nervios entre cada costilla.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>     
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
  <div class="card-content">
    
  <div class="card-header-blue">
  <h5 class="color-secundario text-center pt-3">Neuropatías</h5>
  </div>

  <div class="card-body" align="center">
  <img src="<?=RUTA_IMG_ICONOS;?>dolor4.png" class="mb-3">
  <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
  Debilidad, entumecimiento y dolor, generalmente en las manos y los pies, ocasionado por un daño neurológico.
  </p> 

  <div class="text-end pt-4">
  <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
  </div>

  </div>     
  </div>
  </div>
</div>

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Periféricas (Lesión de Nervios)</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor10.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Afectación a la capacidad del cerebro para comunicarse con los músculos y los órganos.
    </p> 

    <div class="text-end pt-5">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>      
    </div>
  </div>
</div>

<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Síndrome Facetario</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor1.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Sobrecarga de las pequeñas articulaciones que están en la parte posterior de las vértebras, se da mayormente a nivel lumbar.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>     
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Síndrome Complejo Regional Doloroso</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor6.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Dolor crónico del brazo o la pierna después de una lesión, cirugía, derrame cerebral o infarto.
    </p> 

    <div class="text-end">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>     
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Sufrimiento por Dolor</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor9.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    El dolor es una percepción sensorial, localizada y subjetiva con intensidad variable que puede resultar molesta y desagradable en una parte del cuerpo.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>
    </div>      
    </div>
  </div>
</div>


<div class="col-12 col-md-3 mb-4">              
  <div class="card shadow card border-0">
    <div class="card-content">
      
    <div class="card-header-blue">
    <h5 class="color-secundario text-center pt-3">Síndrome de Espalda Fallida</h5>
    </div>

    <div class="card-body" align="center">
    <img src="<?=RUTA_IMG_ICONOS;?>dolor8.png" class="mb-3">
    <p class="truncate-multiline text-secondary fs-5 fw-light" align="justify">
    Dolor lumbar de origen desconocido, que persiste o aparece después de una intervención quirúrgica de la columna, realizada con la intención de tratar un dolor localizado originalmente en la misma zona.
    </p> 

    <div class="text-end pt-4">
    <button class="btn btn-light"> <small> Ver más</small> <i class="bi bi-chevron-right"></i></button>   
    </div>

    </div>     
    </div>
  </div>
</div>

</div>
</div>


<div id="btnContador" class="text-center mt-4">

<img src="<?=RUTA_IMG_ICONOS;?>flecha-abajo2.png" class="botonesControl" onclick="verMasDiv(2)"> 

</div>
</div>

<?php include_once __DIR__ . '/../components/web-ubicacion-contacto.php';?>

<script src="<?=RUTA_WEB_JS;?>navbar.min.js"></script>    
<script src="<?=RUTA_WEB_JS;?>bootstrap.min.js"></script>    
</body>
</html>