<?php 
use App\Config\Database;
use App\Models\LaboratorioModel;
$bd = Database::getInstance();
$nombrePacienteSeguro = htmlspecialchars((string)($data['nombre_paciente'] ?? ''), ENT_QUOTES, 'UTF-8');
$nombrePacienteVisible = $nombrePacienteSeguro !== '' ? $nombrePacienteSeguro : 'Paciente';
$volverExpedienteUrl = htmlspecialchars(SERVIDOR . 'clinica/paciente/' . (int)$data['idPaciente'], ENT_QUOTES, 'UTF-8');
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
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/simple-datatables/style.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/quill/quill.snow.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>
    <script>
    function AgregarLaboratorio(idPaciente) {
    
    const referencia = 0;
    const titulo = document.getElementById('Titulo').value;
    const fileInput = document.getElementById('Archivo');
    const file = fileInput.files[0];
    const contenidoLaboratorio = document.querySelector('.ql-editor').innerHTML;

    document.getElementById('Archivo').style.border = "";
    document.querySelector('.ql-editor').style.border = "";


    if (file) {
        if (contenidoLaboratorio !== '<p><br></p>') {

            const formData = new FormData();
            formData.append('idPaciente', idPaciente);
            formData.append('file', file);
            formData.append('contenidoLaboratorio', contenidoLaboratorio);
            formData.append('referencia', referencia);
            formData.append('titulo', titulo);
               
            fetch('/clinica/laboratorio/insert-laboratorio', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                
                if (data.resultado) {
                    location.reload()                
                } else {
                    document.getElementById('mensaje').textContent = 'Error: ' + data.mensaje;
                }

                });
               
        } else {
            document.querySelector('.ql-editor').style.border = "2px solid #d44e31";
        }
    } else {
        document.getElementById('Archivo').style.border = "2px solid #d44e31";
    }
}

    function DetalleLaboratorio(idLaboratorio){

        fetch(`/buscar/laboratorio/${encodeURIComponent(idLaboratorio)}`)
                .then(response => {
                if (!response.ok) {
                throw new Error('Error en la respuesta del servidor: ' + response.status);
                }
                return response.text();
                })
                .then(data => {

                    const resultsContainer = document.getElementById('detalleReceta');
                    resultsContainer.innerHTML = data;
                    feather.replace();

                });

    } 

    </script>

 </head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-lab-page">
<div class="LoaderPage"></div>
    <div id="app">
        
        <?=$data['sidebar'];?>

        <div id="main">
            <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
            
            <div class="main-content container-fluid">

            <header class="doctor-page-hero doctor-lab-hero">
                <div class="doctor-page-hero-copy">
                    <p class="dashboard-eyebrow">Control clínico</p>
                    <h1><?=htmlspecialchars((string)$data['title'], ENT_QUOTES, 'UTF-8');?></h1>
                    <p>Consulta los resultados de laboratorio y agrega nueva documentación de <?=$nombrePacienteVisible;?>.</p>
                </div>
                <div class="doctor-page-hero-actions">
                    <a class="btn doctor-page-hero-action" href="<?=$volverExpedienteUrl;?>">
                        <i data-feather="arrow-left" aria-hidden="true"></i>
                        <span>Volver al expediente</span>
                    </a>
                    <button id="toggleButton" type="button" class="btn doctor-lab-toggle" onclick="toggleSize()" aria-label="Cambiar distribución de la pantalla" title="Cambiar distribución de la pantalla">
                        <i id="toggleIcon" data-feather="columns" aria-hidden="true"></i>
                        <span>Cambiar distribución</span>
                    </button>
                </div>
            </header>

            <section class="doctor-lab-patient-context">
            <div class="row mt-3">
                <div class="col-12 resizable">
                    <div class="card doctor-lab-card doctor-lab-patient-card">
                        <div class="card-header">
                            <h4 class="card-title">Información del paciente</h4>
                        </div>
                        <div class="card-body">

                            <div class="row">
                                <div class="col-12 col-sm-4">
                                <label class="text-primary"><small>Nombre Paciente:</small></label>
                                <div class="fs-5"><?=$nombrePacienteVisible;?></div>
                                </div>
                            
                                <div class="col-12 col-sm-2">
                                <label class="text-primary"><small>Fecha Alta:</small></label>
                                <div class="fs-5"><?=(new DateTime($data['fecha_alta']))->format('d/m/Y');[0];?></div>
                                </div>

                                <div class="col-12 col-sm-2">
                                <label class="text-primary"><small>Fecha Nacimiento:</small></label>
                                <div class="fs-5"><?=date("d/m/Y", strtotime($data['fecha_nacimiento']));?></div>
                                </div>

                                <div class="col-12 col-sm-2">
                                <label class="text-primary"><small>Edad:</small></label>
                                <div class="fs-5"><?=$data['edad'];?> años</div>
                                </div>
                            
                            <div class="col-12 col-sm-2">
                            <label class="text-primary"><small>Sexo:</small></label>
                            <div class="fs-5"><?=($data['sexo'] == 'M')? 'Masculino': 'Femenino';?></div>
                            </div>

                            </div>                

                        </div>
                    </div>

                </div>
            </div>
            </section>

            <section>

            <div class="row doctor-lab-workspace">
                <div class="col-12 col-sm-5 resizable">

                <div class="card doctor-lab-card doctor-lab-list-card">
                <div class="card-header text-light">
                <h4 class="card-title">Archivos</h4>
                </div>
                <div class="card-body">

                <?php
                        try {
                            $stmt = $bd->query("SELECT * FROM laboratorio WHERE id_paciente = '".$data['idPaciente']."' ORDER BY fecha_hora DESC");
                            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            die("Error en la consulta: " . $e->getMessage());
                        }
                ?>

                <table class="table table-striped table-hover table-sm pb-0 mb-0" id="table1">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th>Fecha y Hora</th>
                            <th>Titulo</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($registros as $registro): ?>
                        <tr onclick="DetalleLaboratorio(<?=$registro['id']?>)">
                            <td class="text-center"><?=$registro['id']?></td>
                            <td><?=(new DateTime(datetime: $registro['fecha_hora']))->format('d/m/Y h:i a');?></td>
                            <td><?=$registro['titulo']?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                </div>
                </div>

                </div>
                <div class="col-12 col-sm-7 resizable">
                        
                <div class="card doctor-lab-card doctor-lab-detail-card">
                    <div class="card-header text-light">
                    <h4 class="card-title">Detalle del Laboratorio</h4>
                    </div>
                    <div class="card-body">
                        <div id="detalleReceta" class="doctor-lab-detail-content">
                            <?php
                            $model = new LaboratorioModel();
                            echo $model->ultimoLaboratorio($data['idPaciente']);
                            ?>
                        </div>
                    </div>
                </div>

                <div class="card doctor-lab-card doctor-lab-create-card">
                <div class="card-header text-light">
                <h4 class="card-title">Nueva Archivo</h4>
                </div>
                <div class="card-body">

                <label class="text-primary"><small>Titulo:</small></label>
                <input type="text" class="form-control" id="Titulo">
                
                <label class="text-primary mt-2"><small>Archivo:</small></label>
                <div class="mb-3"><input type="file" class="form-control" id="Archivo"></div>
                
                <label class="text-primary"><small>Descripción:</small></label>

                <div id="snow" class="editor"></div>
                <div class="text-end mt-3 doctor-lab-submit"><button type="button" class="btn btn-success" onclick="AgregarLaboratorio(<?=$data['idPaciente'];?>)">Agregar Laboratorio <i data-feather="chevron-right" aria-hidden="true"></i></button></div>
                <div id="mensaje"></div>
                </div>
                </div>

                </div>
            </div>

                
            </section>


        </div>

            <?php include_once __DIR__ . '/../components/footer-doctor.php';?>
        </div>
    </div>
    <script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script> 
    <script src="<?=RUTA_JS;?>main.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/quill/quill.min.js"></script>
    <script src="<?=RUTA_JS?>search-main.js"></script>
    
    <script>

    let table1 = document.querySelector('#table1');
    let dataTable = new simpleDatatables.DataTable(table1,{
        searchable: true,
        fixedHeight: true,
        perPageSelect: false,
        searchable: true,
        columns: [
        {
            select: 1, sort: "desc"
        }
        ]
    });

        var snow = new Quill('#snow', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic'], 
                [{ 'list': 'ordered'}, { 'list': 'bullet' }]
            ],
        },
        bounds: '#snow',
        height: '500px',
        });

    </script>
</body>
</html>

