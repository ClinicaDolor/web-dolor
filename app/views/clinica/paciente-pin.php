<?php 
use App\Config\Database;
$bd = Database::getInstance();
$nombrePaciente = trim((string)($data['nombre_paciente'] ?? ''));
$nombrePacienteSeguro = $nombrePaciente !== ''
    ? htmlspecialchars($nombrePaciente, ENT_QUOTES, 'UTF-8')
    : 'Paciente autorizado';
?>

    <!DOCTYPE html>
    <html lang="es-MX">
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

    <body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-pin-page">
    <div class="LoaderPage"></div>
    <div id="app">
        
    <?=$data['sidebar'];?>

    <div id="main">
    <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
    <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
            
    <main class="main-content container-fluid" id="main-content">
    <header class="doctor-page-hero doctor-pin-hero" aria-labelledby="patient-pin-title">
    <div>
    <p class="dashboard-eyebrow">ACCESO DEL PACIENTE</p>
    <h1 id="patient-pin-title">PIN de acceso</h1>
    <p>Administra el acceso seguro al portal del paciente.</p>
    </div>
    <a href="<?=SERVIDOR?>clinica/paciente/<?=$data['idPaciente'];?>" class="btn btn-light doctor-page-hero-action">
        <i data-feather="arrow-left" aria-hidden="true"></i>
        <span>Volver al expediente</span>
    </a>
    </header>

    <div class="row doctor-pin-grid">
    <div class="col-12">

    <section class="card doctor-pin-card doctor-pin-patient-card" aria-labelledby="patient-pin-info-title">
        
    <div class="card-header doctor-pin-card-header">
    <div>
    <p class="dashboard-eyebrow">PACIENTE</p>
    <h2 class="card-title" id="patient-pin-info-title">Información del paciente</h2>
    </div>
    </div>
    <div class="card-body doctor-pin-card-body">

    <div class="row doctor-pin-identity-grid">
    <div class="doctor-pin-field col-12 mb-3">
    <div class="doctor-pin-field-label">Nombre del paciente</div>
    <h3 class="doctor-pin-field-value"><?=$nombrePacienteSeguro;?></h3>
    </div>

    <div class="doctor-pin-field col-12 col-sm-4 mb-3">
    <div class="doctor-pin-field-label">Fecha de alta</div>
    <h5 class="doctor-pin-field-value"><?=(new DateTime($data['fecha_alta']))->format('d/m/Y');[0];?></h5>
    </div>

    <div class="doctor-pin-field col-12 col-sm-4 mb-3">
    <div class="doctor-pin-field-label">Fecha de nacimiento</div>
    <h5 class="doctor-pin-field-value"><?=date("d/m/Y", strtotime($data['fecha_nacimiento']));?></h5>
    </div>

    <div class="doctor-pin-field col-12 col-sm-4 mb-3">
    <div class="doctor-pin-field-label">Edad</div>
    <h5 class="doctor-pin-field-value"><?=$data['edad'];?> años</h5>
    </div>

    <div class="doctor-pin-field col-12 col-sm-4 mb-3">
    <div class="doctor-pin-field-label">Sexo</div>
    <h5 class="doctor-pin-field-value"><?=($data['sexo'] == 'M')? 'Masculino': 'Femenino';?></h5>
    </div>

    <div class="doctor-pin-field col-12 col-sm-4 mb-3">
    <div class="doctor-pin-field-label">Estado civil</div>
    <h5 class="doctor-pin-field-value"><?=$data['estado_civil'];?></h5>
    </div>

    <div class="doctor-pin-field col-12 col-sm-4 mb-3">
    <div class="doctor-pin-field-label">CURP</div>
    <h5 class="doctor-pin-field-value"><?=$data['curp'];?></h5>
    </div>

    </div>

    <h3 class="doctor-pin-subsection-title">Contacto del paciente</h3>

    <div class="row doctor-pin-contact-grid">

    <div class="doctor-pin-field col-12 col-sm-4">
    <div class="doctor-pin-field-label">Email</div>
    <h5 class="doctor-pin-field-value"><?=$data['email'];?></h5>
    </div>

    <div class="doctor-pin-field col-12 col-sm-4">
    <div class="doctor-pin-field-label">Teléfono</div>
    <h5 class="doctor-pin-field-value"><?=$data['telefono'];?></h5>
    </div>

    <div class="doctor-pin-field col-12 col-sm-4">
    <div class="doctor-pin-field-label">Celular</div>
    <h5 class="doctor-pin-field-value"><?=$data['celular'];?></h5>
    </div>

    </div>

    </div>
    </section>
    </div>
                
    <div class="col-12">
    <section class="card doctor-pin-card doctor-pin-access-card" aria-labelledby="patient-pin-access-title">

    <div class="card-header doctor-pin-card-header">
    <div class="doctor-pin-card-heading">
    <p class="dashboard-eyebrow">CREDENCIAL DE ACCESO</p>
    <h2 class="card-title" id="patient-pin-access-title">PIN de acceso para pacientes</h2>
    </div>
    <button type="button" class="btn btn-success doctor-pin-action" onclick="NuevoPin(<?=$data['idPaciente'];?>)" aria-controls="mensaje">
        <i data-feather="plus" aria-hidden="true"></i>
        <span>Generar PIN</span>
    </button>
    </div>
    <div id="mensaje" class="doctor-pin-message" role="alert" aria-live="polite" aria-atomic="true"></div>
                        
    
    
    <div class="card-body doctor-pin-card-body">

                        <?php
                        $hoy = new DateTime();
                        try {
                            $stmt = $bd->query("SELECT * FROM pc_paciente_acceso WHERE paciente_id = '".$data['idPaciente']."' ");
                            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            die("Error en la consulta: " . $e->getMessage());
                        }
                        ?>

                        <table class="table table-striped doctor-pin-table" id="table1">
                            <thead>
                                <tr>
                                    <th class="text-center" scope="col">PIN</th>
                                    <th class="text-center" scope="col">Fecha creación</th>
                                    <th class="text-center" scope="col">Fecha expiración</th>
                                    <th class="text-center" scope="col">Estatus</th>
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
            </section>
            </div>

        </div>
        </main>

    <!----- FOOTER ---------->
    <?php include_once __DIR__ . '/../components/footer-doctor.php';?>

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
    fixedHeight: false,
	columns: [
	{
		select: 1, sort: "desc"
	}
	]
});
    </script>
</body>
</html>

