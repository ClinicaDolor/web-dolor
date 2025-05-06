<?php 
use App\Config\Database;
use App\Models\PacienteModulosModelo;

$bd = Database::getInstance();
$model = new PacienteModulosModelo();
?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <title><?=$data['title'];?></title>
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>
    <script src="<?=RUTA_JS;?>clean-data.js"></script>
    </head>

    <body> 
    <div class="LoaderPage"></div>
    <div id="app">
        
    <!---------- SIDEBAR ---------->
    <?=$data['sidebar'];?>
    
    <div id="main">
            
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

    <!--
    <div class="search float-end">
    <div class="search-input">
    <a href="" target="_blank" hidden></a>
    <input type="text" class="form-control round" placeholder="Buscar...">
    <div class="autocom-box"></div>
    <div class="icon"><i data-feather="search"></i></div>
    </div>
    </div>
    -->

    <div class="page-title mb-4">     
    <h8><?= $data['datos']['nombre']?></h8>
    <h3>Historia Clinica</h3>
    </div>
    
    <section class="section">
    <?php
    try {
    $stmt = $bd->query("SELECT * FROM historia_clinica_modulos ORDER BY id ASC");
    $modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener registros finalizados para el paciente específico
    $id_paciente = $data['datos']['id_usuario'];
    $stmtFinalizados = $bd->prepare("SELECT id_modulo FROM pac_historia_clinica_finalizar WHERE id_paciente = ?");
    $stmtFinalizados->execute([$id_paciente]);
    $modulosFinalizados = $stmtFinalizados->fetchAll(PDO::FETCH_COLUMN);    

    } catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
    }
    ?>

    <div class="row">
    <?php foreach ($modulos as $modulo): 

    // Verificar si el módulo está finalizado
    $deshabilitado = in_array($modulo['id'], $modulosFinalizados);

    $referenciaCard = '';
    $classCard = "card-mvsd-disabled";
    if (!$deshabilitado):
    $referenciaCard = 'href="'.SERVIDOR.'historia-clinica/'.$modulo['url'].'/paciente/'.$data['datos']['id_usuario'].'"';
    $classCard = "card-mvsd";
    endif;
    ?>
        
    <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 mb-3">
    <a <?=$referenciaCard?>>
    <div class="card border-0 rounded-4 position-relative <?=$classCard?> text-center">  

    <!-- Badge en la esquina superior izquierda -->
    <div class="position-absolute top-0 start-0 m-4">
    <span class="badge bg-primary "> <?=$modulo['id']?> </span>
    </div>

    <div class="card-body pt-5 pb-0">

    <div class="row">
    <div class="col-12">
    <!-- Nombre del módulo -->
    <h5 class="fw-bold text-primary mb-3"><?=$modulo['nombre']?></h5>

    <!-- Imagen centrada -->
    <div class="d-flex justify-content-center">
    <img src="<?=RUTA_IMAGES ?>/iconos/<?=$modulo['imagen']?>" class="img-fluid" style="max-height: 90px;">
    </div>
    </div>

    <div class="col-12">
    <h6 class="fw-bold text-secondary mt-4">Porcentaje de cumplimiento:</h6>
       
    <?php
    $porcentajeTotal = 0;

   if($modulo['id'] == 1){
   $porcentajeTotal = $model->porcentajeModulo1($id_paciente);
   }else if($modulo['id'] == 2){
   $porcentajeTotal = $model->porcentajeModulo2($id_paciente);
   }else if($modulo['id'] == 3){
   $porcentajeTotal = $model->porcentajeModulo3($id_paciente);
   }else if($modulo['id'] == 4){
   $porcentajeTotal = $model->porcentajeModulo4($id_paciente);
   }else if($modulo['id'] == 5){
   $porcentajeTotal = $model->porcentajeModulo5($id_paciente);
   } else if($modulo['id'] == 6){
   $porcentajeTotal = $model->porcentajeModulo6($id_paciente);
   } else if($modulo['id'] == 7){
   $porcentajeTotal = $model->porcentajeModulo7($id_paciente);
   } else if($modulo['id'] == 8){
   $porcentajeTotal = $model->porcentajeModulo8($id_paciente);
   } else if($modulo['id'] == 9){
   $porcentajeTotal = $model->porcentajeModulo9($id_paciente);
   }

   echo '<div class="progress bg-light rounded-pill" style="height: 15px;">
   <div class="progress-bar bg-success rounded-pill text-white fw-bold text-center" 
   role="progressbar" style="width: '.$porcentajeTotal.'%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"> '.$porcentajeTotal.'% </div>
   </div>';
  

   ?> 
   


    </div>

    </div>

    </div>


    </div>
    </a>
    </div>

    <?php endforeach; ?>
    </div>

    </section>
    </div>
 
    <!----- FOOTER ---------->
    <?php include_once __DIR__ . '/../components/footer-mvsd.php';?>
    
    </div>
    </div>

    <script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>    
    <script src="<?=RUTA_JS;?>main.js"></script>

    </body>
    </html>
