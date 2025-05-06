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
    <script>
        function NuevoPin(idPaciente){

            const parametros = {
            idPaciente : idPaciente
            };

        fetch('/clinica/paciente/insert-pin', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(parametros)
        })
        .then(response => response.json())
        .then(data => {

        if (data.resultado) {
            location.reload()
        } else {
            document.getElementById('mensaje').textContent = 'Error: ' + data.mensaje;
        }

        });
            
        }
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
    <h3><?=$data['title'];?></h3>
    </div>

    <div class="row mt-3">
    <div class="col-12">

    <div class="card">
        
    <div class="card-header text-light pb-1">
    <h5 class="card-title">Información del paciente</h5>
    </div>
    <div class="card-body">

    <div class="row">
    <div class="col-12 mb-3">
    <div class="text-secondary">Nombre Paciente:</div>
    <h4><?=$data['nombre_paciente'];?></h4>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <div class="text-secondary">Fecha Alta:</div>
    <h5 class=""><?=(new DateTime($data['fecha_alta']))->format('d/m/Y');[0];?></h5>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <div class="text-secondary">Fecha Nacimiento:</div>
    <h5 class=""><?=date("d/m/Y", strtotime($data['fecha_nacimiento']));?></h5>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <div class="text-secondary">Edad:</div>
    <h5 class=""><?=$data['edad'];?> años</h5>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <div class="text-secondary">Sexo:</div>
    <h5 class=""><?=($data['sexo'] == 'M')? 'Masculino': 'Femenino';?></h5>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <div class="text-secondary">Estado Civil:</div>
    <h5 class=""><?=$data['estado_civil'];?></h5>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <div class="text-secondary">CURP:</div>
    <h5 class=""><?=$data['curp'];?></h5>
    </div>

    </div>

    <h5 class="text-success mt-2">Contacto del paciente</h5>

    <div class="row ">

    <div class="col-12 col-sm-4">
    <div class="text-secondary">Email:</div>
    <h5 class=""><?=$data['email'];?></h5>
    </div>

    <div class="col-12 col-sm-4">
    <div class="text-secondary">Telefono:</div>
    <h5 class=""><?=$data['telefono'];?></h5>
    </div>

    <div class="col-12 col-sm-4">
    <div class="text-secondary">Celular:</div>
    <h5 class=""><?=$data['celular'];?></h5>
    </div>

    </div>

    </div>
    </div>
    </div>
                
    <div class="col-12">
    <div class="card">

    <div class="card-header text-light">    
    <div class="row">
    <div class="col-10">     
    <h4 class="card-title">Pin de acceso para pacientes</h4> 
    </div>

    <div class="col-2">     
    <div class="text-end"><button class="btn icon btn-success" onclick="NuevoPin(<?=$data['idPaciente'];?>)"><i data-feather="plus" width="20"></i> PIN </button></div>
    </div>

    <div class="col-12">
    <div id="mensaje" class="text-center text-danger"></div>
    </div>

    </div>
    </div>
                        
    
    
    <div class="card-body">

                        <?php
                        $hoy = new DateTime();
                        try {
                            $stmt = $bd->query("SELECT * FROM pc_paciente_acceso WHERE paciente_id = '".$data['idPaciente']."' ");
                            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            die("Error en la consulta: " . $e->getMessage());
                        }
                        ?>

                        <table class="table table-striped" id="table1">
                            <thead>
                                <tr>
                                    <th class="text-center">PIN</th>
                                    <th class="text-center">Fecha creación</th>
                                    <th class="text-center">Fecha expiración</th>
                                    <th class="text-center">Estatus</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php 
                            
                            foreach ($registros as $registro): 
                            $fechaExpiracion = new DateTime($registro['fecha_expiracion']);
                            if ($hoy > $fechaExpiracion){
                                $pin = '******';
                                $estatus = '<span class="badge bg-danger">Cancelado</span>';
                            }else{
                                $pin = 'cdp'.$data['idPaciente'];
                                $estatus = '<span class="badge bg-success">Activo</span>';
                            }
                                
                            ?>
                                <tr>
                                    <td class="text-center align-middle"><?=$pin;?></td>
                                    <td class="text-center align-middle"><?=(new DateTime($registro['fecha_creacion']))->format('d/m/Y');?></td>
                                    <td class="text-center align-middle"><b><?=(new DateTime($registro['fecha_expiracion']))->format('d/m/Y');?></b></td>
                                    <td class="text-center align-middle"><?=$estatus;?></td>
                                </tr>
                            <?php endforeach; ?> 
                            </tbody>
                        </table>
                            
                        </div>
                    </div>
                </div>
            </div>

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
	}
	]
});
    </script>
</body>
</html>

