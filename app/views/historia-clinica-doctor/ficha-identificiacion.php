<?php 
use App\Config\Database;
use App\Models\PacienteModulosModelo;
$bd = Database::getInstance();

?>
 
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$data['title'];?></title>
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>

    <script>
 
    </script>
    
    </head>

    <body>
    <div class="LoaderPage"></div>
    <div id="app">
           
    <!---------- SIDEBAR ---------->
    <?=$data['sidebar'];?>
          
    <div id="main" data-rol="<?=$data['idRol'];?>" data-paciente="<?=$data['idPaciente'];?>">
    <nav class="navbar navbar-header navbar-expand navbar-light">
    <a class="sidebar-toggler"><span class="navbar-toggler-icon"></span></a>
    <button class="btn navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
    </button>
                
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav d-flex align-items-center navbar-light ms-auto">
    </ul>
    </div>
    </nav>

    <!---------- CONTENIDO DE LA PAGINA ---------->
    <div class="main-content container-fluid">

    <div class="page-title mb-4">     
    <h8>
        
    <?=$data['nombre'];?></h8>
    <h3><?=$data['title'];?></h3>
    </div>
    
    <section class="section">
    <div class="card">
    <div class="card-body">
    <div class="row">

    <div class="col-12 mb-3 text-end">
    <a href="<?=SERVIDOR?>clinica/paciente/editar/<?=$data['idPaciente']?>">
    <button class="btn btn-success"> Editar <i data-feather="edit-2"></i></button>
    </a>
    </div>
 
    <div class="col-12 col-sm-6 mb-3">
    <h8 class="text-primary fw-bold texto">Nombre completo:</h8>
    <p><?=$data['nombre'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-3 mb-3">
    <h8 class="text-primary fw-bold texto">Edad:</h8>
    <p><?=$data['edad'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-3 mb-3">
    <h8 class="text-primary fw-bold texto">Sexo:</h8>
    <p><?php if(isset($data['sexo'])){ echo ($data['sexo'] == 'M')? 'Masculino': 'Femenino';}else{echo '';} ?></p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Estado civil:</h8>
    <p><?php if(isset($data['estado_civil'])){ echo $data['estado_civil'];}else{echo '';} ?></p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Fecha de nacimiento:</h8>
    <p><?=$data['fecha_nacimiento'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">CURP:</h8>
    <p><?=$data['curp'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Lugar de Origen:</h8>
    <p><?=$data['lugar_origen'] ?? ''?></p>
    </div>
    
    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Lugar de Residencia:</h8>
    <p><?=$data['lugar_residencia'] ?? ''?></p>
    </div>
    
    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Ocupación:</h8>
    <p><?=$data['ocupacion'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Número de hijos:</h8>
    <p><?=$data['num_hijos'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Edad de sus hijos:</h8>
    <p><?=$data['edad_hijos'] ?? ''?></p>
    </div>

    <div class="col-12 mb-3">
    <hr>
    <p class="text-success fw-bold texto">¿Quien lo recomienda? O porque medio se entero de la Clinica del Dolor y Cuidados Paliativos?:</p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Persona que lo recomendo:</h8>
    <p><?=$data['quien_recomienda'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Redes sociales:</h8>
    <p><?php if(isset($data['redes_sociales'])){ echo $data['redes_sociales'];}else{echo '';} ?></p>
    </div>

    <div class="col-12 mb-3">
    <hr>
    <h8 class="text-primary fw-bold texto">Motivo por el que solicita atención en la Clinica de Dolor y Cuidados Paliativos:</h8>
    <p><?=$data['motivo_atencion'] ?? ''?></p>
    </div>

    <div class="col-12 mb-3">
    <hr>
    <p class="text-success fw-bold texto">Dirección actual</p>
    </div>

    <div class="col-12 col-sm-8 mb-3">
    <h8 class="text-primary fw-bold texto">Calle:</h8>
    <p><?=$data['calle'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-2 mb-3">
    <h8 class="text-primary fw-bold texto">Numero Interior:</h8>
    <p><?=$data['num_interior'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-2 mb-3">
    <h8 class="text-primary fw-bold texto">Numero Exterior:</h8>
    <p><?=$data['num_exterior'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-5 mb-3">
    <h8 class="text-primary fw-bold texto">Colonia:</h8>
    <p><?=$data['colonia'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-5 mb-3">
    <h8 class="text-primary fw-bold texto">Delegación:</h8>
    <p><?=$data['delegacion'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-2 mb-3">
    <h8 class="text-primary fw-bold texto">Código postal:</h8>
    <p><?=$data['cp'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-6 mb-3">
    <h8 class="text-primary fw-bold texto">Municipio:</h8>
    <p><?=$data['municipio'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-6 mb-3">
    <h8 class="text-primary fw-bold texto">Distancia de casa a Clínica del Dolor Hospital Ángeles Lomas:</h8>
    <p><?=$data['distancia'] ?? ''?> (Minutos / Horas)</p>
    </div>

    <div class="col-12 mb-3">
    <hr>
    <p class="text-success fw-bold texto">Contacto</p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Correo electrónico:</h8>
    <p><?=$data['email'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Teléfono de casa:</h8>
    <p><?=$data['telefono'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Celular:</h8>
    <p><?=$data['celular'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-3 mb-3">
    <h8 class="text-primary fw-bold texto">¿Tiene cuidador(a)?:</h8>
    <p><?php if(isset($data['cuidador'])){ echo ($data['cuidador'])? 'Si': 'No';}else{echo '';} ?></p>
    </div>

    <div class="col-12 col-sm-5 mb-3">
    <h8 class="text-primary fw-bold texto">Nombre:</h8>
    <p><?=$data['cuidador'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Teléfono:</h8>
    <p><?=$data['cuidador_telefono'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Nombre del familiar responsable:</h8>
    <p><?=$data['res_nombre'] ?? ''?></p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Teléfono:</h8>
    <p><?=$data['res_telefono'] ?? ''?></p>
    </div>
    </div>

    </div>
    </div>
    </section>
 
    </div>
    <!----- FOOTER ---------->
    <?php include_once __DIR__ . '/../components/footer-mvsd.php';?>
    </div>
    </div>

    <script src="<?=RUTA_JS;?>voice-utilities.js"></script>
    <script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>    
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS;?>main.js"></script>
    
    </body>
    </html>
