<?php 
use App\Config\Database;
use App\Models\CofeprisModel;
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

window.addEventListener("pageshow", () => {
        tableCofepris()
    });

    document.addEventListener("DOMContentLoaded", function() {
        tableCofepris()
    });


        function AgregarCofepris(idPaciente){

            const Diagnostico = document.getElementById('Diagnostico').value;
            const Medicamento = document.getElementById('Medicamento').value;
            const NumCajas = document.getElementById('NumCajas').value;
            const Presentacion = document.getElementById('Presentacion').value;
            const Dosificacion = document.getElementById('Dosificacion').value;
            const NumDias = document.getElementById('NumDias').value;
            const ViaAdministracion = document.getElementById('ViaAdministracion').value;

           
            if(Diagnostico != ""){

            const parametros = {
            idPaciente : idPaciente,
            diagnostico : Diagnostico,
            medicamento : Medicamento,
            numCajas : NumCajas,
            presentacion : Presentacion,
            dosificacion : Dosificacion,
            numDias : NumDias,
            viaAdministracion : ViaAdministracion
            };

            fetch('/clinica/paciente/insert-cofepris', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(parametros)
            })
            .then(response => response.json())
            .then(data => {

            if (data.resultado) {

                id = data.mensaje;

                tableCofepris()
                DetalleCofepris(id)
                                
                document.getElementById('Diagnostico').value = "";
                document.getElementById('Medicamento').value = "";
                document.getElementById('NumCajas').value = "";
                document.getElementById('Presentacion').value = "";
                document.getElementById('Dosificacion').value = "";
                document.getElementById('NumDias').value = "";
                document.getElementById('ViaAdministracion').value = "";

                window.open('/pdf/cofepris/' + id, '_blank');
                

            } else {
                document.getElementById('mensaje').textContent = 'Mensaje: ' + data.mensaje;
            }
          
        });

    }else{
        document.querySelector('#Diagnostico').style.border = "2px solid #d44e31";
    }

    }

    function DetalleCofepris(idReceta){

        fetch(`/buscar/cofepris/${encodeURIComponent(idReceta)}`)
                .then(response => {
                if (!response.ok) {
                throw new Error('Error en la respuesta del servidor: ' + response.status);
                }
                return response.text();
                })
                .then(data => {
                    
                    const resultsContainer = document.getElementById('detalleCofepris');
                    resultsContainer.innerHTML = data;
                    feather.replace();

                });
    } 

    function tableCofepris(){

        const usuarioDiv = document.getElementById('main');
        const idPaciente = usuarioDiv.getAttribute('data-paciente');

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

                    const tabla = document.querySelector("#tableCofepris");
                    if (tabla) {
                        dataTable = new simpleDatatables.DataTable(tabla,{
                            searchable: true,
                            fixedHeight: true,
                            perPageSelect: false,
                            columns: [
                            {
                                select: 1, sort: "desc"
                            }
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

            <section>
            <div class="row mt-3">
                <div class="col-12 col-sm-12 resizable">
                    <div class="card">

                        <div class="card-header text-light">
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
                <div class="card-header">
                <h4 class="card-title">Cofepris</h4>
                </div>
                <div class="card-body">

                <div id="conteCofepris">
                <?php
                        try {
                            $stmt = $bd->query("SELECT * FROM cofepris WHERE id_paciente = '".$data['idPaciente']."' ORDER BY fecha_hora DESC");
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
                    <?php foreach ($registros as $registro): ?>
                        <tr id="cofepris<?=$registro['id']?>" onclick="DetalleCofepris(<?=$registro['id']?>)">
                            <td class="text-center"><?=$registro['id']?></td>
                            <td><?=(new DateTime(datetime: $registro['fecha_hora']))->format('d/m/Y h:i a');?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                </div>
                </div>

                </div>
                <div class="col-12 col-sm-7 resizable">
                        
                <div class="card">
                    <div class="card-header">
                    <h4 class="card-title">Detalle de Cofepris</h4>
                    </div>
                    <div class="card-body">
                        <div id="detalleCofepris">
                            <?php
                            $model = new CofeprisModel();
                            echo $model->ultimoRegistro($data['idPaciente']);
                            ?>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header text-primary">
                    <h4 class="card-title">Nuevo Registro</h4>
                    </div>
                    <div class="card-body">

                    <div class="">
                    <label class="text-primary mb-1 mt-3"><smallal>Diagnostico:</smallal></label>
                    <textarea class="form-control fs-5" id="Diagnostico" rows="1"></textarea>
                    </div>
                    
                    <div class="">
                    <label class="text-primary mb-1 mt-3"><smallal>Medicamento:</smallal></label>
                    <textarea class="form-control fs-5" id="Medicamento" rows="1"></textarea>
                    </div>
                    
                    <div class="row mt-3">
                    <div class="col-12 col-sm-6">

                    <label class="text-primary mb-1"><smallal>Cantidad (numero y letra):</smallal></label>
                    <input type="text" class="form-control" id="NumCajas">

                    </div>
                    <div class="col-12 col-sm-6">

                    <label class="text-primary mb-1"><smallal>Presentación:</smallal></label>
                    <input type="text" class="form-control" id="Presentacion">

                    </div>
                    </div>

                    <div class="">
                    <label class="text-primary mb-1 mt-3"><smallal>Dosificación:</smallal></label>
                    <textarea class="form-control fs-5" id="Dosificacion" rows="1"></textarea>
                    </div>

                    <div class="row mt-3">
                    <div class="col-12 col-sm-6">

                    <label class="text-primary mb-1"><smallal>No. de días de prescripción:</smallal></label>
                    <input type="text" class="form-control" id="NumDias">

                    </div>
                    <div class="col-12 col-sm-6">

                    <label class="text-primary mb-1"><smallal>Via de administración:</smallal></label>
                    <input type="text" class="form-control" id="ViaAdministracion">

                    </div>
                    </div>


                    
                    <div class="text-end mt-3"><button class="btn btn-success" onclick="AgregarCofepris(<?=$data['idPaciente'];?>)">Agregar Cofepris <i data-feather="chevron-right"></i></button></div>
                    
                    <div class="text-center text-danger" id="mensaje"></div>
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
    <script src="<?=RUTA_JS?>search-main.js"></script>
    
   
</body>
</html>

