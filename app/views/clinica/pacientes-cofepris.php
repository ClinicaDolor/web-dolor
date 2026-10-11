<?php 
use App\Config\Database;
use App\Models\CofeprisModel;
$bd = Database::getInstance();

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$folio = ($data['id_cofepris'] == 0)
    ? ''
    : '<div class="doctor-patient-cofepris-folio"><small>Folio:</small> <span>'.$escape($data['folio']).'</span></div>';

$fechaOriginal = ($data['id_cofepris'] == 0)? date('d/m/Y') : $data['fecha'];
$fechaHora = DateTime::createFromFormat('d/m/Y', $fechaOriginal);
$fechaFormateada = $fechaHora->format('Y-m-d');

$sub_text = ($data['id_cofepris'] == 0)? 'Nuevo Registro': 'Editar Registro';
$boton_titulo = ($data['id_cofepris'] == 0)? 'Agregar': 'Editar';
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

window.addEventListener("pageshow", () => {
        tableCofepris()
    });

    document.addEventListener("DOMContentLoaded", function() {
        tableCofepris()
    });


        function AgregarCofepris(idPaciente,idCofepris){

            const Fecha = document.getElementById('Fecha').value;
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
            idCofepris : idCofepris,
            fecha : Fecha,
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
                
                if(idCofepris == 0){
                document.getElementById('Fecha').value = "";
                document.getElementById('Diagnostico').value = "";
                document.getElementById('Medicamento').value = "";
                document.getElementById('NumCajas').value = "";
                document.getElementById('Presentacion').value = "";
                document.getElementById('Dosificacion').value = "";
                document.getElementById('NumDias').value = "";
                document.getElementById('ViaAdministracion').value = "";
                }

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
                    feather.replace();

                    const tabla = document.querySelector("#tableCofepris");
                    const hasRows = Boolean(tabla && tabla.tBodies.length && tabla.tBodies[0].rows.length);
                    resultsContainer.classList.toggle('is-empty', !hasRows);

                    const emptyState = resultsContainer.querySelector('.doctor-patient-cofepris-empty');
                    if (emptyState) emptyState.remove();
                    if (!hasRows) {
                        const message = document.createElement('p');
                        message.className = 'doctor-patient-cofepris-empty';
                        message.setAttribute('role', 'status');
                        message.textContent = 'No hay registros COFEPRIS para este paciente.';
                        resultsContainer.appendChild(message);
                    }

                    if (tabla && hasRows) {
                        dataTable = new simpleDatatables.DataTable(tabla,{
                            searchable: true,
                            fixedHeight: false,
                            perPageSelect: false,
                            columns: [
                            {
                                select: 1, sort: "desc"
                            },
                            { select: [2], sortable: false },
                            ]
                        });
                    }          
        });
    }

    </script>

 </head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-patient-cofepris-page">
<div class="LoaderPage"></div>
    <div id="app">
        
        <?=$data['sidebar'];?>

        <div id="main" data-paciente="<?=$data['idPaciente'];?>">
            
            <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
            
            <main class="main-content container-fluid doctor-patient-cofepris-content">
            <header class="doctor-page-hero doctor-patient-cofepris-hero">
                <div class="doctor-page-hero-copy">
                    <p class="dashboard-eyebrow">CONTROL COFEPRIS</p>
                    <h1><?=$sub_text;?> COFEPRIS</h1>
                    <p>Gestiona el registro COFEPRIS del paciente autorizado.</p>
                    <p class="doctor-patient-cofepris-context">Paciente: <strong><?=$escape($data['nombre_paciente']);?></strong></p>
                </div>
                <div class="doctor-page-hero-actions doctor-patient-cofepris-hero-actions">
                    <a href="<?=SERVIDOR?>clinica/paciente/<?=$data['idPaciente'];?>" class="btn doctor-page-hero-action">
                        <i data-feather="arrow-left" aria-hidden="true"></i>
                        <span>Volver al expediente</span>
                    </a>
                    <button id="toggleButton" class="btn doctor-patient-cofepris-toggle" type="button" onclick="toggleSize()" aria-label="Cambiar distribución de la pantalla" title="Cambiar distribución de la pantalla">
                        <i id="toggleIcon" data-feather="columns" aria-hidden="true"></i>
                    </button>
                </div>
            </header>

            <section class="doctor-patient-cofepris-section doctor-patient-cofepris-summary" aria-labelledby="doctor-patient-cofepris-summary-title">
            <div class="row">
                <div class="col-12 resizable">
                    <div class="card doctor-patient-cofepris-card doctor-patient-cofepris-summary-card">
                        <div class="card-header doctor-patient-cofepris-card-header">
                            <div>
                                <p class="dashboard-eyebrow">CONTEXTO CLÍNICO</p>
                                <h2 id="doctor-patient-cofepris-summary-title" class="card-title">Información del paciente</h2>
                            </div>
                        </div>
                        <div class="card-body doctor-patient-cofepris-card-body">
                            <div class="row doctor-patient-cofepris-patient-grid">
                                <div class="col-12 col-sm-4">
                                    <span class="doctor-patient-cofepris-label">Nombre del paciente</span>
                                    <div class="doctor-patient-cofepris-value doctor-patient-cofepris-patient-name"><?=$escape($data['nombre_paciente']);?></div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <span class="doctor-patient-cofepris-label">Fecha de alta</span>
                                    <div class="doctor-patient-cofepris-value"><?=(new DateTime($data['fecha_alta']))->format('d/m/Y');?></div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <span class="doctor-patient-cofepris-label">Fecha de nacimiento</span>
                                    <div class="doctor-patient-cofepris-value"><?=date('d/m/Y', strtotime($data['fecha_nacimiento']));?></div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <span class="doctor-patient-cofepris-label">Edad</span>
                                    <div class="doctor-patient-cofepris-value"><?=$escape($data['edad']);?> años</div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <span class="doctor-patient-cofepris-label">Sexo</span>
                                    <div class="doctor-patient-cofepris-value"><?=($data['sexo'] == 'M') ? 'Masculino' : 'Femenino';?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </section>

            <section class="doctor-patient-cofepris-section" aria-label="Registros y formulario COFEPRIS">
            <div class="row doctor-patient-cofepris-layout">
                <div class="col-12 col-sm-5 resizable">
                <div class="card doctor-patient-cofepris-card doctor-patient-cofepris-list-card">
                <div class="card-header doctor-patient-cofepris-card-header">
                    <div>
                        <h2 class="card-title">Registros COFEPRIS</h2>
                    </div>
                </div>
                <div class="card-body doctor-patient-cofepris-card-body">
                <div id="conteCofepris" class="doctor-patient-cofepris-table-wrap">
                <?php
                        try {
                            $stmt = $bd->query("SELECT * FROM cofepris WHERE id_paciente = '".$data['idPaciente']."' ORDER BY fecha_hora DESC");
                            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            die("Error en la consulta: " . $e->getMessage());
                        }
                ?>

                <table class="table table-striped table-hover table-sm doctor-patient-cofepris-table" id="table1">
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
                        
                <div class="card doctor-patient-cofepris-card doctor-patient-cofepris-detail-card">
                    <div class="card-header doctor-patient-cofepris-card-header">
                        <div>
                            <p class="dashboard-eyebrow">CONSULTA</p>
                            <h2 class="card-title">Detalle de COFEPRIS</h2>
                        </div>
                    </div>
                    <div class="card-body doctor-patient-cofepris-card-body">
                        <div id="detalleCofepris" class="doctor-patient-cofepris-detail-content">
                            <?php
                            $model = new CofeprisModel();
                            echo $model->ultimoRegistro($data['idPaciente']);
                            ?>
                        </div>
                    </div>
                </div>

                <div class="card doctor-patient-cofepris-card doctor-patient-cofepris-form-card">
                    <div class="card-header doctor-patient-cofepris-card-header">
                        <div>
                            <p class="dashboard-eyebrow">REGISTRO</p>
                            <h2 class="card-title"><?=$sub_text;?> COFEPRIS</h2>
                        </div>
                    </div>
                    <div class="card-body doctor-patient-cofepris-card-body">
                    
                    <?=$folio;?>
                 
                    <div class="doctor-patient-cofepris-field">
                    <label class="doctor-patient-cofepris-form-label" for="Fecha">Fecha</label>
                    <input type="date" class="form-control doctor-patient-cofepris-control" id="Fecha" value="<?=$fechaFormateada;?>">
                    </div>

                    <div class="doctor-patient-cofepris-field">
                    <label class="doctor-patient-cofepris-form-label" for="Diagnostico">Diagnóstico</label>
                    <textarea class="form-control doctor-patient-cofepris-control" id="Diagnostico" rows="2"><?=$escape($data['diagnostico']);?></textarea>
                    </div>
                    
                    <div class="doctor-patient-cofepris-field">
                    <label class="doctor-patient-cofepris-form-label" for="Medicamento">Medicamento</label>
                    <textarea class="form-control doctor-patient-cofepris-control" id="Medicamento" rows="2"><?=$escape($data['medicamento']);?></textarea>
                    </div>
                    
                    <div class="row doctor-patient-cofepris-field-row">
                    <div class="col-12 col-sm-6">

                    <label class="doctor-patient-cofepris-form-label" for="NumCajas">Cantidad (número y letra)</label>
                    <input type="text" class="form-control doctor-patient-cofepris-control" id="NumCajas" value="<?=$escape($data['numcajas']);?>">

                    </div>
                    <div class="col-12 col-sm-6">

                    <label class="doctor-patient-cofepris-form-label" for="Presentacion">Presentación</label>
                    <input type="text" class="form-control doctor-patient-cofepris-control" id="Presentacion" value="<?=$escape($data['presentacion']);?>">

                    </div>
                    </div>

                    <div class="doctor-patient-cofepris-field">
                    <label class="doctor-patient-cofepris-form-label" for="Dosificacion">Dosificación</label>
                    <textarea class="form-control doctor-patient-cofepris-control" id="Dosificacion" rows="2"><?=$escape($data['dosificacion']);?></textarea>
                    </div>

                    <div class="row doctor-patient-cofepris-field-row">
                    <div class="col-12 col-sm-6">

                    <label class="doctor-patient-cofepris-form-label" for="NumDias">No. de días de prescripción</label>
                    <input type="text" class="form-control doctor-patient-cofepris-control" id="NumDias" value="<?=$escape($data['numdias']);?>">

                    </div>
                    <div class="col-12 col-sm-6">

                    <label class="doctor-patient-cofepris-form-label" for="ViaAdministracion">Vía de administración</label>
                    <input type="text" class="form-control doctor-patient-cofepris-control" id="ViaAdministracion" value="<?=$escape($data['viaadministracion']);?>">

                    </div>
                    </div>

                    
                    <div class="doctor-patient-cofepris-actions"><button type="button" class="btn doctor-patient-cofepris-submit" onclick="AgregarCofepris(<?=$data['idPaciente'];?>,<?=$data['id_cofepris'];?>)"><?=$boton_titulo;?> COFEPRIS<i data-feather="chevron-right" aria-hidden="true"></i></button></div>
                    
                    <div class="text-center text-danger" id="mensaje"></div>
                    </div>
                </div>

                 </div>
            </div>
            </section>
            </main>

            <?php include_once __DIR__ . '/../components/footer-doctor.php';?>
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

