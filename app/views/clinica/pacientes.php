<?php 
use App\Config\Database;
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
    <h3>Pacientes</h3>
    </div>

    <section class="section mt-4">
    <?php 
    /*print_r($data);
    echo "</br>".$data['datos']['rol'];*/
    ?>

    <div class="card">
        
    <div class="card-header">
    <div class="row">
    <div class="col-10">
    <h4 class="card-title">Lista de Pacientes</h4>
    </div>

    <div class="col-2">
    <div class="float-end">
    <a href="<?=SERVIDOR?>clinica/paciente/nuevo" class="btn icon btn-success"> <i data-feather="plus" width="20"></i> </a>
    </div>
    </div>
    </div>
    </div>
            
    <div class="card-body">
    <?php
    try {
    $stmt = $bd->query("SELECT * FROM pc_paciente");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
    }
     ?>
    <table class='table table-striped' id="table1">
    <thead>
    <tr>
    <th class="text-center aling-middle">Id</th>
    <th class="text-center aling-middle">Fecha Alta</th>
    <th class="text-start aling-middle">Nombre Completo</th>
    <th class="text-center aling-middle">Edad</th>
    <th class="text-center aling-middle">Sexo</th>
    <th class="text-center aling-middle">Fecha nacimiento</th>
    <th class="text-center aling-middle" width="20px"><i data-feather="folder" width="20px"></i> </th>
    <th class="text-center aling-middle" width="20px"><i data-feather="key" width="20px"></i> </th>
    <th class="text-center aling-middle" width="20px"><i data-feather="grid" width="20px"></i> </th>
    <th class="text-center aling-middle" width="20px"><i data-feather="edit" width="20px"></i> </th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($registros as $registro): ?>
    <tr>
    <td class="text-center aling-middle"><?=$registro['id']?></td>
    <td class="text-center aling-middle"><?=$registro['fecha_alta']?></td>
    <td class="text-start aling-middle"><b><?=$registro['nombre_completo']?></b></td>
    <td class="text-center aling-middle"><?=$registro['edad']?> años</td>
    <td class="text-center align-middle" style="color: <?= $registro['sexo'] == 'F' ? 'pink' : 'blue' ?>;">
    <?=$registro['sexo']?>
    </td>
    <td class="text-center aling-middle"><?=$registro['fecha_nacimiento']?></td>
    <td class="text-center"><a href="<?=SERVIDOR?>clinica/paciente/<?=$registro['id']?>"><i data-feather="folder" width="20"></i></a></td>
    <td class="text-center"><a href="<?=SERVIDOR?>clinica/paciente/pin/<?=$registro['id']?>"><i data-feather="key" width="20"></i></a></td>
    <td class="text-center"><a href="<?=SERVIDOR?>clinica/modulos/paciente/<?=$registro['id']?>"><i data-feather="grid" width="20"></i></a></td>
    <td class="text-center"><a href="<?=SERVIDOR?>clinica/paciente/editar/<?=$registro['id']?>"><i data-feather="edit" width="20"></i></a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
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
	{
		select: 1, sort: "desc"
	},
    { select: [6,7,8], sortable: false },

	]
    });

    </script>

    </body>
    </html>

