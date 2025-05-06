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
    <title><?=$data['title'];?></title>
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>
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
    <h3><?=$data['title'];?></h3>
    </div>
            
    <section class="section mt-4">
   
    <div class="card">
    <div class="card-header">
    <h4 class="card-title"><?=$data['nombre_paciente'];?></h4>
    </div>


    <div class="card-body">
    <?php
    try {
    $stmt = $bd->query("SELECT * FROM historia_clinica_modulos");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
    }
    ?>
             
    <div class="table-responsive">
    <table class='table table-striped' id="table1">
    <thead>
    <tr>
    <th class="text-center">Id</th>
    <th class="text-start">Nombre del modulo</th>
    <th class="text-center">% de cumplimiento</th>
    <th class="text-center" width="20"><i data-feather="edit"></i> </th>
    <th class="text-center" width="20"><i data-feather="file-text"></i> </th>
    </tr>
    </thead>

    <tbody>
    <?php 
    $porcentajeAcumulado = 0; // Acumulador para el total
    $contadorModulos = 0;

    foreach ($registros as $registro):                       
    ?>
    <tr>
    <td class="text-center"><?=$registro['id']?></td>
    <td><b><?=$registro['nombre']?></b></td>
    <td>

    <?php
    $porcentajeTotal = 0;

    switch ($registro['id']) {
    case 1:
        $porcentajeTotal = floatval($model->porcentajeModulo1($data['id_paciente']));
        break;
    case 2:
        $porcentajeTotal = floatval($model->porcentajeModulo2($data['id_paciente']));
        break;
    case 3:
        $porcentajeTotal = floatval($model->porcentajeModulo3($data['id_paciente']));
        break;
    case 4:
        $porcentajeTotal = floatval($model->porcentajeModulo4($data['id_paciente']));
        break;
    case 5:
        $porcentajeTotal = floatval($model->porcentajeModulo5($data['id_paciente']));
        break;
    case 6:
        $porcentajeTotal = floatval($model->porcentajeModulo6($data['id_paciente']));
        break;
    case 7:
        $porcentajeTotal = floatval($model->porcentajeModulo7($data['id_paciente']));
        break;
    case 8:
        $porcentajeTotal = floatval($model->porcentajeModulo8($data['id_paciente']));
        break;
    case 9:
        $porcentajeTotal = floatval($model->porcentajeModulo9($data['id_paciente']));
        break;
    }

    $porcentajeAcumulado += $porcentajeTotal;
    $contadorModulos++;

    echo '<div class="progress bg-light rounded-pill" style="height: 15px;">
    <div class="progress-bar bg-success rounded-pill text-white fw-bold text-center" 
    role="progressbar" style="width: '.$porcentajeTotal.'%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"> '.$porcentajeTotal.'% </div>
    </div>';
    ?>
    </td>
    <td class="text-center"><a href="<?=SERVIDOR?>clinica/<?=$registro['url']?>/paciente/<?=$data['id_paciente']?>"><i data-feather="edit"></i></a></td>
    <td class="text-center"><a href="<?=SERVIDOR?>clinica/resumen/<?=$registro['url']?>/paciente/<?=$data['id_paciente']?>"><i data-feather="file-text"></i></a></td>
    </tr>  
    <?php endforeach; ?>

    <tr>
    <td></td>
    <td><b>Porcentaje total</b></td>
    <td colspan="3">
    <?php
    $promedioTotal = ($contadorModulos > 0) ? round($porcentajeAcumulado / $contadorModulos, 2) : 0;
    echo '<div class="progress bg-light rounded-pill" style="height: 15px;">
    <div class="progress-bar bg-success rounded-pill text-white fw-bold text-center" 
    role="progressbar" style="width: '.$promedioTotal.'%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"> '.$promedioTotal.'% </div>
    </div>';
    ?>
    </td>
    </tr>
    </tbody>

   
    </table>
    </div>
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

    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS;?>main.js"></script>
    <script src="<?=RUTA_JS?>search-main.js"></script>


    <script>
    let table1 = document.querySelector('#table1');
    let dataTable = new simpleDatatables.DataTable(table1,{
	searchable: true,
    fixedHeight: true,
    columns: [
    { select: [2,3,4], sortable: false },
	]
    });
    </script>

    </body>
    </html>

