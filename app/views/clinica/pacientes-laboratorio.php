<?php 
use App\Config\Database;
use App\Models\LaboratorioModel;
$bd = Database::getInstance();
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
    <style>
        .editor{
            font-size: 20px;
            height: 250px;
        }
        .row {
            display: flex;
            flex-wrap: wrap;
        }
        .resizable {
            transition: all 0.3s ease-in-out; /* Suaviza el cambio de tamaño */
        }
    </style>
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
<body>
<div class="LoaderPage"></div>
    <div id="app">
        
        <?=$data['sidebar'];?>

        <div id="main">
            <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
            
            <div class="main-content container-fluid">

            <button id="toggleButton" class="btn icon btn-light text-dark float-end" onclick="toggleSize()">
            <i id="toggleIcon" data-feather="columns"></i>
            </button>
            
            <div class="page-title">
                <h3><?=$data['title'];?></h3>
            </div>

            <section>
            <div class="row mt-3">
                <div class="col-12 col-sm-12 resizable">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Información del paciente</h4>
                        </div>
                        <div class="card-body">

                            <div class="row">
                                <div class="col-12 col-sm-4">
                                <label class="text-primary"><small>Nombre Paciente:</small></label>
                                <div class="fs-5"><?=$data['nombre_paciente'];?></div>
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

            <div class="row">
                <div class="col-12 col-sm-5 resizable">

                <div class="card">
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
                        
                <div class="card">
                    <div class="card-header text-light">
                    <h4 class="card-title">Detalle del Laboratorio</h4>
                    </div>
                    <div class="card-body">
                        <div id="detalleReceta">
                            <?php
                            $model = new LaboratorioModel();
                            echo $model->ultimoLaboratorio($data['idPaciente']);
                            ?>
                        </div>
                    </div>
                </div>

                <div class="card">
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
                <div class="text-end mt-3"><button class="btn btn-success" onclick="AgregarLaboratorio(<?=$data['idPaciente'];?>)">Agregar Laboratorio <i data-feather="chevron-right"></i></button></div>
                <div id="mensaje"></div>
                </div>
                </div>

                </div>
            </div>

                
            </section>


        </div>

            <footer>
                <div class="footer clearfix mb-0 text-muted">
                    <div class="float-start">
                        <p>2025 &copy; tratamientosdeldolor.org</p>
                    </div>
                </div>
            </footer>
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

