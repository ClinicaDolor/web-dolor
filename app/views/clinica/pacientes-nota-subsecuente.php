<?php 

use App\Config\Database;
use App\Models\NotaSubsecuenteModel;
$bd = Database::getInstance();
$model = new NotaSubsecuenteModel();

$detalle_subtitulo = ($data['id_nota_subsecuente'] == 0)? 'Agregar' : 'Editar';

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
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/quill/quill.snow.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>
    <style>
        .editor1{
            font-size: 20px;
            height: 390px;
        }

        .editor2{
            font-size: 20px;
            height: 155px;
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

document.addEventListener("DOMContentLoaded", function() {
        tableLaboratorio()
    });

function tableLaboratorio(){
        const usuarioDiv = document.getElementById('main');
        const idPaciente = usuarioDiv.getAttribute('data-paciente');
        const referencia = usuarioDiv.getAttribute('data-referencia');

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
                    dataTable = new simpleDatatables.DataTable(tabla,{
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
                }          
        });
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

            const resultsContainer = document.getElementById('detalleLaboratorio');
            resultsContainer.innerHTML = data;
            feather.replace();

        });

        } 

        function AgregarNota(){

            const usuarioDiv = document.getElementById('main');
            const idPaciente = usuarioDiv.getAttribute('data-paciente');
            const referencia = usuarioDiv.getAttribute('data-referencia');
            const idNota = usuarioDiv.getAttribute('data-nota');
            const idReceta = usuarioDiv.getAttribute('data-idreceta');
            
            const contenidoNota = document.querySelector('#snow1 .ql-editor').innerHTML;
            const Diagnostico = document.getElementById('Diagnostico').value;
            const contenidoReceta = document.querySelector('#snow2 .ql-editor').innerHTML;

            const TA = document.getElementById('TA').value;
            const FrecCardiaca = document.getElementById('FrecCardiaca').value;
            const Pulso = document.getElementById('Pulso').value;
            const Spo2 = document.getElementById('Spo2').value;
            const Fio2 = document.getElementById('Fio2').value;
            const Ecog = document.getElementById('Ecog').value;
            const Karnovsky = document.getElementById('Karnovsky').value;
            const Peso = document.getElementById('Peso').value;
            const Talla = document.getElementById('Talla').value;
            const FechaProximaCita = document.getElementById('FechaProximaCita').value;
            

            document.querySelector('#snow1 .ql-editor').style.border = "";
            document.querySelector('#Diagnostico').style.border = "";
            document.querySelector('#snow2 .ql-editor').style.border = "";

           
            const parametros = {
            idPaciente : idPaciente,
            referencia : referencia,
            contenidoNota : contenidoNota,
            diagnostico : Diagnostico,
            medicamento : contenidoReceta,

            ta : TA,
            frecCardiaca : FrecCardiaca,
            pulso : Pulso,
            spo2 : Spo2,
            fio2 : Fio2,
            ecog : Ecog,
            karnovsky : Karnovsky,
            peso : Peso,
            talla : Talla,
            idNota : idNota,
            idReceta : idReceta,
            proximacita : FechaProximaCita
            };

            fetch('/clinica/paciente/insert-nota-subsecuente', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(parametros)
            })
            .then(response => response.json())
            .then(data => {

            if (data.resultado) {

                let return_idReceta = data.mensaje.idReceta;
                let return_idNota = data.mensaje.idNota;

                document.getElementById('Diagnostico').value = "";
                document.querySelector('#snow1 .ql-editor').innerHTML = "";
                document.querySelector('#snow2 .ql-editor').innerHTML = "";
                
                window.open('/pdf/receta/' + return_idReceta, '_blank');
                window.location.href = '/clinica/nota-subsecuente/' + return_idNota;
                
            } else {
                document.getElementById('mensaje').textContent = 'Error: ' + data.mensaje;
            }
          
        });

           
    }

    function AgregarLaboratorio() {
      
    const usuarioDiv = document.getElementById('main');
    const idPaciente = usuarioDiv.getAttribute('data-paciente');
    const referencia = usuarioDiv.getAttribute('data-referencia');
    const titulo = document.getElementById('Titulo').value;

    const fileInput = document.getElementById('Archivo');
    const file = fileInput.files[0];
    const contenidoLaboratorio = document.querySelector('#snow3 .ql-editor').innerHTML;

    document.getElementById('Archivo').style.border = "";
    document.querySelector('#snow3 .ql-editor').style.border = "";


    if (file) {
        if (contenidoLaboratorio !== '<p><br></p>') {


            const formData = new FormData();
            formData.append('idPaciente', idPaciente);
            formData.append('file', file);
            formData.append('contenidoLaboratorio', contenidoLaboratorio);
            formData.append('referencia', referencia);
            formData.append('titulo', titulo);
            $(".LoaderPage").show();
            fetch('/clinica/laboratorio/insert-laboratorio', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    $(".LoaderPage").hide();
                if (data.resultado) {

                    tableLaboratorio()
                
                } else {
                    document.getElementById('mensaje').textContent = 'Error: ' + data.mensaje;
                }

                });
               
               

        } else {
            document.querySelector('#snow3 .ql-editor').style.border = "2px solid #d44e31";
        }
    } else {
        document.getElementById('Archivo').style.border = "2px solid #d44e31";
    }
}

    function DetalleNota(idNota){

        fetch(`/buscar/nota-subsecuente/${encodeURIComponent(idNota)}`)
                .then(response => {
                if (!response.ok) {
                throw new Error('Error en la respuesta del servidor: ' + response.status);
                }
                return response.text();
                })
                .then(data => {

                    const resultsContainer = document.getElementById('detalleNota');
                    resultsContainer.innerHTML = data;
                    feather.replace();

                    const tabla = document.querySelector("#LaboratorioNotaSub");
                if (tabla) {
                    dataTable = new simpleDatatables.DataTable(tabla,{
                        searchable: true,
                        fixedHeight: true,
                        perPageSelect: false,
                        searchable: true,
                        columns: [
                        {
                            select: 0, sort: "desc"
                        },
                        { select: [3], sortable: false },
                        ]
                    });
                }     

                });

    }

    </script>
 </head>
<body>
<div class="LoaderPage"></div>
    <div id="app">
        
        <?=$data['sidebar'];?>

    <div id="main" data-referencia="<?=$data['referencia'];?>" 
    data-paciente="<?=$data['idPaciente'];?>" 
    data-nota="<?=$data['id_nota_subsecuente'];?>"
    data-idreceta="<?=$data['idreceta'];?>">
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
                <div class="col-12 col-sm-12">
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
                <div class="col-12 col-sm-4 resizable">

                <div class="card">
                <div class="card-header">
                <h4 class="card-title">Notas Subsecuentes</h4>
                </div>
                <div class="card-body">

                <?php
                        try {
                            $stmt = $bd->query("SELECT * FROM nota_subsecuente WHERE id_paciente = '".$data['idPaciente']."' ORDER BY fecha_hora DESC");
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
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    foreach ($registros as $registro): 
                    $primer = ($model->primerNota() == $registro['id'])? '<i class="text-info text-end" data-feather="star" width="20"></i>': '';
                    ?>
                        <tr onclick="DetalleNota(<?=$registro['id']?>)">
                            <td class="text-center"><?=$registro['id']?></td>
                            <td><?=(new DateTime(datetime: $registro['fecha_hora']))->format('d/m/Y h:i a');?> <?=$primer;?> </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                </div>
                </div>

                </div>
                <div class="col-12 col-sm-8 resizable">
                        
                <div class="card">
                    <div class="card-header">
                    <h4 class="card-title">Detalle Nota Subsecuente</h4>

                    </div>
                    <div class="card-body">
                        <div id="detalleNota">
                        <?php
                            echo $model->ultimaNota($data['idPaciente']);
                            ?>
                        </div>
                    </div>
                </div>

               </div>
            </div>

                
            </section>
                     
            <section class="mt-3">

            <hr>
            <small class="text-light">Crear nueva Nota Subsecuente</small>

            <div class="row mt-3">
            <div class="col-12 col-sm-6 order-2 order-sm-1 resizable" data-index="1">

            <div class="card">
                <div class="card-header">
                <h4 class="card-title"><?=$detalle_subtitulo;?> Nota Subsecuente</h4>
                </div>
                <div class="card-body">
                    
                <?php 
                if (!empty($data['fecha_hora'])) {
                    $fechaHora = new \DateTime($data['fecha_hora']);
                    $fecha = $fechaHora->format('d/m/Y');
                    $hora = $fechaHora->format('h:i a');

                    echo '<div class="pb-2"><small>Fecha: '.$fecha.', Hora: '.$hora.'</small></div>';
                }
                                
                ?>

                <label class="text-primary mb-1"><smallal>Signos vitales: </smallal></label>
                
                <div class="row p-2">

              <div class="col-12 col-sm-4 p-1">
                    <div class="input-group">
                    <span class="input-group-text">TA.</span>
                    <input type="text" class="form-control" id="TA" value="<?=$data['ta'];?>">
                    <span class="input-group-text bg-white">mmHg</span>
                    </div>
                </div>

                <div class="col-12 col-sm-4 p-1">

                    <div class="input-group">
                    <span class="input-group-text">Frec cardiaca</span>
                    <input type="text" class="form-control" id="FrecCardiaca" value="<?=$data['frec_cardiaca'];?>">
                    <span class="input-group-text bg-white">lpm</span>
                    </div>

                </div>

                <div class="col-12 col-sm-4 p-1">
                    <div class="input-group">
                    <span class="input-group-text">Pulso</span>
                    <input type="text" class="form-control" id="Pulso" value="<?=$data['pulso'];?>">
                    <span class="input-group-text bg-white">lpm</span>
                    </div>
                </div>

                    <div class="col-12 col-sm-4 p-1">
                        <div class="input-group">
                        <span class="input-group-text">SpO2.</span>
                        <input type="text" class="form-control" id="Spo2" value="<?=$data['spo2'];?>">
                        <span class="input-group-text bg-white">%</span>
                        </div>
                    </div>

                    <div class="col-12 col-sm-4 p-1">
                        <div class="input-group">
                        <span class="input-group-text">FiO2.</span>
                        <input type="text" class="form-control" id="Fio2" value="<?=$data['fio2'];?>">
                        <span class="input-group-text bg-white">%</span>
                        </div>
                    </div>

                    <div class="col-12 col-sm-4 p-1">

                    <div class="input-group">
                    <span class="input-group-text">ECOG.</span>
                    <input type="text" class="form-control" id="Ecog" value="<?=$data['ecog'];?>">
                    </div>

                    </div>
                    <div class="col-12 col-sm-4 p-1">

                    <div class="input-group">
                    <span class="input-group-text">Karnovsky</span>
                    <input type="text" class="form-control" id="Karnovsky" value="<?=$data['karnovsky'];?>">
                    </div>
                        
                    </div>
                    <div class="col-12 col-sm-4 p-1">

                        <div class="input-group">
                        <span class="input-group-text">Peso.</span>
                        <input type="text" class="form-control" id="Peso" value="<?=$data['peso'];?>">
                        </div>
                        
                    </div>
                    <div class="col-12 col-sm-4 p-1">
                        <div class="input-group">
                        <span class="input-group-text">Talla.</span>
                        <input type="text" class="form-control" id="Talla" value="<?=$data['talla'];?>">
                        </div>
                    </div>
                </div>
                
                <label class="text-primary mb-1"><smallal>Detalle: </smallal></label>

                <div id="snow1" class="editor1"><?=$data['contenido'];?></div>
                </div>
                </div>
                
                <div class="card">
                <div class="card-header text-primary">
                <h4 class="card-title"><?=$detalle_subtitulo;?> Receta</h4>
                </div>
                <div class="card-body">
                <div class="">
                <label class="text-primary mb-1"><smallal>Diagnostico:</smallal></label>
                <textarea class="form-control fs-5" id="Diagnostico" rows="2"><?=$data['diagnostico'];?></textarea>
                </div>
                    
                <label class="text-primary mt-3 mb-1"><smallal>Medicamento:</smallal></label>
                <div id="snow2" class="editor1"><?=$data['medicamento'];?></div>
                </div>
                </div>
                <div id="mensaje"></div>
        
            </div>
            <div class="col-12 col-sm-6 col-sm-2 order-1 resizable" data-index="0">

            <div class="card">
                <div class="card-header">
                <h4 class="card-title">Laboratorio</h4>
                </div>
                <div class="card-body">

                <div id="conteLaboratorio"></div>
                <div class="p-2">
                    <div id="detalleLaboratorio">
                    </div>
                </div>
                
                <hr>
                
                <label class="text-primary"><small>Titulo:</small></label>
                <input type="text" class="form-control" id="Titulo">

                <label class="text-primary mt-2"><small>Archivo:</small></label>
                <div class="mb-3"><input type="file" class="form-control" id="Archivo"></div>
                
                <label class="text-primary"><small>Descripción:</small></label>

                <div id="snow3" class="editor2"></div>
                <div class="text-end mt-3"><button class="btn btn-primary" onclick="AgregarLaboratorio()">Agregar <i data-feather="chevron-right"></i></button></div>

                </div>
                </div>

                <div class="card">
                <div class="card-header">
                <h4 class="card-title">Proxima cita</h4>
                </div>
                <div class="card-body">

                <label class="text-primary"><small>Fecha:</small></label>
                <input type="date" class="form-control" id="FechaProximaCita" value="<?=$data['proximacita'];?>">

                </div>
                </div>

                <div class="text-end pb-3"><button class="btn btn-success p-3 fs-5" onclick="AgregarNota()"><?=$detalle_subtitulo;?> Nota Subsecuente <i data-feather="chevron-right"></i> </button></div>

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
    <script src="<?=RUTA_PUBLIC;?>libs/simple-datatables/simple-datatables.js"></script>
    <script src="<?=RUTA_PUBLIC;?>libs/quill/quill.min.js"></script>
    <script src="<?=RUTA_JS?>search-main.js"></script>
   
<script>
document.addEventListener("DOMContentLoaded", function () {

    let table1 = document.querySelector('#table1');
    if (table1) {
        new simpleDatatables.DataTable(table1, {
            searchable: true,
            fixedHeight: true,
            perPageSelect: false,
            columns: [
                { select: 1, sort: "desc" }
            ]
        });
    }

    let LaboratorioNotaSub = document.querySelector('#LaboratorioNotaSub');
    if (LaboratorioNotaSub) {
        new simpleDatatables.DataTable(LaboratorioNotaSub, {
            searchable: true,
            fixedHeight: true,
            perPageSelect: false,
            columns: [
                { select: 1, sort: "desc" }
            ]
        });
    }

    if (document.querySelector('#snow1')) {
        new Quill('#snow1', { theme: 'snow', modules: { toolbar: [['bold', 'italic'], [{ 'list': 'ordered' }, { 'list': 'bullet' }]] }, bounds: '#snow1' });
    }

    if (document.querySelector('#snow2')) {
        new Quill('#snow2', { theme: 'snow', modules: { toolbar: [['bold', 'italic'], [{ 'list': 'ordered' }, { 'list': 'bullet' }]] }, bounds: '#snow2' });
    }

    if (document.querySelector('#snow3')) {
        new Quill('#snow3', { theme: 'snow', modules: { toolbar: [['bold', 'italic'], [{ 'list': 'ordered' }, { 'list': 'bullet' }]] }, bounds: '#snow3' });
    }

});
</script>
</body>
</html>

