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
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>logo-clinica.png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>
    
    <style>
        .row {
            display: flex;
            flex-wrap: wrap;
        }
        .resizable {
            transition: all 0.3s ease-in-out; /* Suaviza el cambio de tamaño */
        }
    </style>
    
    <script>

    window.addEventListener("pageshow", () => {
        tableNotas()
        tableLaboratorio()
        tableRecetas()
        tableCofepris()
    });

    document.addEventListener("DOMContentLoaded", function() {
        tableNotas()
        tableLaboratorio()
        tableRecetas()
        tableCofepris()
    });

    function tableNotas(){

        const usuarioDiv = document.getElementById('main');
        const idPaciente = usuarioDiv.getAttribute('data-paciente');
        const referencia = 0;

        fetch(`/buscar/tabla-notas-subsecuentes/${encodeURIComponent(idPaciente)}/${encodeURIComponent(referencia)}`)
            .then(response => {
            if (!response.ok) {
            throw new Error('Error en la respuesta del servidor: ' + response.status);
            }
            return response.text();
            })
            .then(data => {

                const resultsContainer = document.getElementById('conteNotasSubsecuentes');
                resultsContainer.innerHTML = data;
                feather.replace();

                const tabla = document.querySelector("#tableNotasSubsecuentes");
                if (tabla) {
                    let dataTable = new simpleDatatables.DataTable(tabla,{
                        searchable: true,
                        fixedHeight: true,
                        perPageSelect: false,
                        columns: [
                        {
                            select: 0, sort: "desc"
                        },
                        { select: [2], sortable: false },
                        ]
                    });
                }       
                
        });

    }

     function tableRecetas(){

        const usuarioDiv = document.getElementById('main');
        const idPaciente = usuarioDiv.getAttribute('data-paciente');

        fetch(`/buscar/tabla-recetas/${encodeURIComponent(idPaciente)}`)
            .then(response => {
            if (!response.ok) {
            throw new Error('Error en la respuesta del servidor: ' + response.status);
            }
            return response.text();
            })
            .then(data => {

                const resultsContainer = document.getElementById('conteRecetas');
                resultsContainer.innerHTML = data;

                const tabla = document.querySelector("#tableRecetas");
                if (tabla) {
                    let dataTable = new simpleDatatables.DataTable(tabla,{
                        searchable: true,
                        fixedHeight: true,
                        perPageSelect: false,
                        columns: [
                        {
                            select: 0, sort: "desc"
                        }
                        ]
                    });
                }          
        });
    }

    function tableLaboratorio(){
        const usuarioDiv = document.getElementById('main');
        const idPaciente = usuarioDiv.getAttribute('data-paciente');
        const referencia = 0;

        fetch(`/buscar/tabla-laboratorio/${encodeURIComponent(idPaciente)}/${encodeURIComponent(referencia)}`)
            .then(response => {
            if (!response.ok) {
            throw new Error('Error en la respuesta del servidor: ' + response.status);
            }
            return response.text();
            })
            .then(data => {

                const resultsContainer = document.getElementById('conteLaboratorio');
                resultsContainer.innerHTML = data;

                const tabla = document.querySelector("#tableLaboratorio");
                if (tabla) {
                    let dataTable = new simpleDatatables.DataTable(tabla,{
                        searchable: true,
                        fixedHeight: true,
                        perPageSelect: false,
                        columns: [
                        {
                            select: 0, sort: "desc"
                        }
                        ]
                    });
                }          
        });
    }

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

        function NuevaNotaSubsecuente(idPaciente,referencia){
            window.location.href = '/clinica/nota-subsecuente/paciente/' + idPaciente + '/referencia/' + referencia;
        }

        function DetalleNota(idNota){
            window.location.href = '/clinica/nota-subsecuente/' + idNota;
        }

        function NuevaReceta(idPaciente){
            window.location.href = '/clinica/receta/paciente/' + idPaciente;
        }

        function DetalleReceta(idReceta){
            window.location.href = '/clinica/receta/' + idReceta;
        }

        function NuevoLaboratorio(idPaciente){
            window.location.href = '/clinica/laboratorio/paciente/' + idPaciente;
        }

        function DetalleLaboratorio(idLaboratorio){
            window.location.href = '/clinica/laboratorio/' + idLaboratorio;
        }

        function tableCofepris(){
        const usuarioDiv = document.getElementById('main');
        const idPaciente = usuarioDiv.getAttribute('data-paciente');
        const referencia = 0;

        fetch(`/buscar/tabla-cofepris/${encodeURIComponent(idPaciente)}`)
            .then(response => {
            if (!response.ok) {
            throw new Error('Error en la respuesta del servidor: ' + response.status);
            }
            return response.text();
            })
            .then(data => {

                const resultsContainer = document.getElementById('conteCofepris');
                resultsContainer.innerHTML = data;
                feather.replace();

                const tabla = document.querySelector("#tableCofepris");
                if (tabla) {
                    let dataTable = new simpleDatatables.DataTable(tabla,{
                        searchable: true,
                        fixedHeight: true,
                        perPageSelect: false,
                        columns: [
                        {
                            select: 0, sort: "desc"
                        },
                        { select: [2], sortable: false },
                        ]
                    });
                }          
        });
        }

        function NuevoCofepris(idPaciente){
            window.location.href = '/clinica/cofepris/paciente/' + idPaciente;
        }

        function DetalleCofepris(id){
            window.location.href = '/clinica/cofepris/' + id;
        }

    </script>


 </head>

    <body>
    <div class="LoaderPage"></div>
   <div id="app">
        
    <?=$data['sidebar'];?>

    <div id="main" data-paciente="<?=$data['idPaciente'];?>">
    <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
    <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
            
    <div class="main-content container-fluid">
    
    <button id="toggleButton" class="btn icon btn-light text-dark float-end" onclick="toggleSize()">
    <i id="toggleIcon" data-feather="columns"></i>
    </button>

    <div class="page-title">
    <h3><?=$data['title'];?></h3>
    </div>

    <div class="row mt-4">

    <div class="col-12 col-sm-6 resizable">
    <div class="card">
        
    <div class="card-header">
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
    <h5 class=""><?=(new DateTime($data['fecha_alta']))->format('d/m/Y');?></h5>
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

    <div class="text-success">Contacto del paciente</div>

    <div class="row mt-3">

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
    
    <!-- Inicio motivo -->
    <div class="card">

        <div class="card-header">
        <h5 class="card-title">Motivo por el que Solicita Atención en la Clinica de Dolor y Cuidados Paliativos</h5>
        </div>

    <div class="card-body">
    <h5><?=empty($data['motivo_atencion'])? 'S/I': $data['motivo_atencion'];?></h5>
    </div>                    
    </div>
    <!-- Fin motivo -->
    
    <!-- Inicio PIN -->
    <div class="card">   
    <div class="card-header">

    <div class="row">
    <div class="col-10">
    <h5 class="card-title">Pin de Acceso para Pacientes</h5>
    </div>

    <div class="col-2">
    <button class="btn icon btn-success float-end" onclick="NuevoPin(<?=$data['idPaciente'];?>)"> <i data-feather="plus" width="20"></i> </button>
    </div>
    </div>
                   
    </div>
                        <div class="card-body">
                      
                        <?php
                        $hoy = new DateTime();
                        try {
                            $stmt = $bd->query("SELECT * FROM pc_paciente_acceso WHERE paciente_id = '".$data['idPaciente']."' ORDER BY fecha_creacion DESC");
                            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            die("Error en la consulta: " . $e->getMessage());
                        }
                        ?>

                        <table class="table table-sm table-striped pb-0 mb-0" id="tablePin">
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
                                $pin = '<label class="text-primary fw-bold">cdp'.$data['idPaciente'].'</label>';
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
    <!-- Fin PIN -->

    </div>
    <!-- Inicio Clinica -->
    <div class="col-12 col-sm-6 resizable">
    
    <div class="card">
        <div class="card-header">

        <div class="row">
            <div class="col-10">
            <h5 class="card-title">Nota Subsecuente</h5>
            </div>

            <div class="col-2">
            <button class="btn icon btn-success float-end" onclick="NuevaNotaSubsecuente(<?=$data['idPaciente'];?>,'<?=$data['referencia'];?>')"> <i data-feather="plus" width="20"></i> </button>
            </div>
            </div>

        </div>
        <div class="card-body">
            <div id="conteNotasSubsecuentes"></div>
        </div>                    
        </div>

        <div class="card">
        <div class="card-header">

        <div class="row">
            <div class="col-10">
            <h5 class="card-title">Laboratorio</h5>
            </div>

            <div class="col-2">
            <button class="btn icon btn-success float-end" onclick="NuevoLaboratorio(<?=$data['idPaciente'];?>)"> <i data-feather="plus" width="20"></i> </button>
            </div>
            </div>

        </div>
        <div class="card-body">
        <div id="conteLaboratorio"></div>
        </div>                    
        </div>
        
        <!-- Inicio Recetas -->
        <div class="card">
        <div class="card-header">

        <div class="row">
            <div class="col-10">
            <h5 class="card-title">Receta Medica</h5>
            </div>

            <div class="col-2">
            <button class="btn icon btn-success float-end" onclick="NuevaReceta(<?=$data['idPaciente'];?>)"> <i data-feather="plus" width="20"></i> </button>
            </div>
            </div>

        </div>
        <div class="card-body">

        <div id="conteRecetas"></div>

        </div>                    
        </div>
        <!-- Fin Recetas -->

         <!-- Inicio Cofepris -->
         <div class="card">
        <div class="card-header">

        <div class="row">
            <div class="col-10">
            <h5 class="card-title">Cofepris</h5>
            </div>

            <div class="col-2">
            <button class="btn icon btn-success float-end" onclick="NuevoCofepris(<?=$data['idPaciente'];?>)"> <i data-feather="plus" width="20"></i> </button>
            </div>
            </div>

        </div>
        <div class="card-body">

        <div id="conteCofepris"></div>

        </div>                    
        </div>
        <!-- Fin Cofepris -->

    </div>
    <!-- Fin Clinica -->

    <!-- Card Modulos --->
    <div class="col-12 resizable">
    <div class="card">
                    
    <div class="card-header">
    <h5 class="card-title">Historia Clinica</h5>        
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

    <table class='table table-sm table-striped pb-0 mb-0' id="table2">
    <thead>
    <tr>
    <th class="text-center">Id</th>
    <th>Nombre del modulo</th>
    <th class="text-center">% de cumplimiento</th>
    <th class="text-center" width="20px"><i data-feather="edit"></i> </th>
    <th class="text-center" width="20px"><i data-feather="file-text"></i> </th>
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
        $porcentajeTotal = floatval($model->porcentajeModulo1($data['idPaciente']));
        break;
    case 2:
        $porcentajeTotal = floatval($model->porcentajeModulo2($data['idPaciente']));
        break;
    case 3:
        $porcentajeTotal = floatval($model->porcentajeModulo3($data['idPaciente']));
        break;
    case 4:
        $porcentajeTotal = floatval($model->porcentajeModulo4($data['idPaciente']));
        break;
    case 5:
        $porcentajeTotal = floatval($model->porcentajeModulo5($data['idPaciente']));
        break;
    case 6:
        $porcentajeTotal = floatval($model->porcentajeModulo6($data['idPaciente']));
        break;
    case 7:
        $porcentajeTotal = floatval($model->porcentajeModulo7($data['idPaciente']));
        break;
    case 8:
        $porcentajeTotal = floatval($model->porcentajeModulo8($data['idPaciente']));
        break;
    case 9:
        $porcentajeTotal = floatval($model->porcentajeModulo9($data['idPaciente']));
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
    <td class="text-center"><a href="<?=SERVIDOR?>clinica/<?=$registro['url']?>/paciente/<?=$data['idPaciente']?>"><i data-feather="edit"></i></a></td>
    <td class="text-center"><a href="<?=SERVIDOR?>clinica/resumen/<?=$registro['url']?>/paciente/<?=$data['idPaciente']?>"><i data-feather="file-text"></i></a></td>
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
        
    </div>
    </div>

    <!----- FOOTER ---------->
    <?php include_once __DIR__ . '/../components/footer-mvsd.php';?> 
    </div>
    </div>
        
    <script src="<?=RUTA_JS;?>feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script> 
    <script src="<?=RUTA_JS;?>main.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_JS?>search-main.js"></script>
    
    <script>

let tablePin = document.querySelector('#tablePin');
let dataTablePin = new simpleDatatables.DataTable(tablePin,{
    fixedHeight: true,
    perPageSelect: false,
    searchable: false,
    columns: [
    {
        select: 1, sort: "desc"
    }
    ]
});

let table1 = document.querySelector('#table2');
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

