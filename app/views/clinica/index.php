<?php
use App\Controllers\ClinicaController;
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
    <script>
        const tableStates = [
    'datatableState',
    'datatableState_tableNotas',
    'datatableState_tableRecetas',
    'datatableState_tableLaboratorio',
    'datatableState_tableCofepris',

    'datatableMedicamentosState_1',
    'datatableMedicamentosState_2',
    'datatableMedicamentosState_3',
    'datatableMedicamentosState_4',
    'datatableMedicamentosState_5',
    'datatableMedicamentosState_6',
    'datatableProcedimientoState',
    'datatableTratamientosState',
    'datatableState_table_cirugia',
    'datatableState_table_enfermedades',
    'datatableState_table_medicacion'
    ];

    tableStates.forEach(key => localStorage.removeItem(key));
    </script>
    </head>

    <body>
    <div class="LoaderPage"></div>
    <div id="app">
    <?=$data['sidebar'];?>
    
    <div id="main">
    <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
    <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
 
    <div class="main-content container-fluid">

    <div class="page-title">
    <h3>Tratamientos del dolor y cuidados paliativos</h3>
    </div>
            
    <section class="section mt-4">
    <div class="row">
        
    <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 mb-4">
    <a href="<?=SERVIDOR?>clinica/pacientes">
    <div class="card border-0 rounded-4 position-relative card-mvsd">               
    <div class="card-body text-center">                   
    <h5 class="text-primary mb-3 mt-2">Lista de Pacientes</h5>
    <div class="col-12 mb-3">
    <img src="<?=RUTA_IMAGES ?>/iconos/paciente.png" class="img-fluid" style="max-height: 90px;">
    </div>
    <div class="col-12">
    <h6 class="text-secondary">No. total de pacientes:</h6>
    <h2 class="text-success"><?=$data['total_pacientes'];?></h2>
    </div>
    </div>
    </div>
    </a>
    </div>
 
    <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 mb-4">
    <a href="<?=SERVIDOR?>clinica/paciente/nuevo">
    <div class="card border-0 rounded-4 position-relative card-mvsd-disabled">
    <div class="card-body text-center">
    <h5 class="text-primary mb-3 mt-2">Agregar Nuevo Paciente</h5>
    <div class="col-12 mb-3">
    <img src="<?=RUTA_IMAGES ?>/iconos/agregar-icon.png" class="img-fluid" style="max-height: 90px;">
    </div>
    </div>
    </div>
    </a>
    </div>
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
    <script src="<?=RUTA_JS?>search-main.js"></script>

    </body>
    </html>
