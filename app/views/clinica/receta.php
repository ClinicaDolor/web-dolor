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

            <section class="mt-3">

            <div class="row">
                <div class="col-12 col-sm-6">

                    <div class="card">
                    <div class="card-header">
                    <h4 class="card-title">Información del Paciente</h4>
                    </div>
                        <div class="card-body">

                        <div class="row">
                                <div class="col-12 col-sm-12">
                                <label class="text-primary"><small>Nombre Paciente:</small></label>
                                <div class="fs-4"><?=$data['nombre_paciente'];?></div>
                                </div>
                            </div>

                            <div class="row mt-2">
                                <div class="col-12 col-sm-4">
                                <label class="text-primary"><small>Fecha Alta:</small></label>
                                <div class="fs-5"><?=(new DateTime($data['fecha_alta']))->format('d/m/Y');[0];?></div>
                                </div>

                                <div class="col-12 col-sm-4">
                                <label class="text-primary"><small>Fecha Nacimiento:</small></label>
                                <div class="fs-5"><?=date("d/m/Y", strtotime($data['fecha_nacimiento']));?></div>
                                </div>

                                <div class="col-12 col-sm-4">
                                <label class="text-primary"><small>Edad:</small></label>
                                <div class="fs-5"><?=$data['edad'];?> años</div>
                                </div>

                            </div>

                            <div class="row mt-2">

                            <div class="col-12 col-sm-4">
                            <label class="text-primary"><small>Sexo:</small></label>
                            <div class="fs-5"><?=($data['sexo'] == 'M')? 'Masculino': 'Femenino';?></div>
                            </div>

                            <div class="col-12 col-sm-4">
                            <label class="text-primary"><small>Estado Civil:</small></label>
                            <div class="fs-5"><?=$data['estado_civil'];?></div>
                            </div>

                            <div class="col-12 col-sm-4">
                            <label class="text-primary"><small>CURP:</small></label>
                            <div class="fs-5"><?=$data['curp'];?></div>
                            </div>

                            </div>

                            <div class="mt-3 fs-6 text-success">Contacto del paciente:</div>

                            <div class="row mt-3">

                            <div class="col-12 col-sm-4">
                            <label class="text-primary"><small>Email:</small></label>
                            <div class="fs-5"><?=$data['email'];?></div>
                            </div>

                            <div class="col-12 col-sm-4">
                            <label class="text-primary"><small>Telefono:</small></label>
                            <div class="fs-5"><?=$data['telefono'];?></div>
                            </div>

                            <div class="col-12 col-sm-4">
                            <label class="text-primary"><small>Celular:</small></label>
                            <div class="fs-5"><?=$data['celular'];?></div>
                            </div>

                            </div>         

                        </div>

                </div>

                </div>
                <div class="col-12 col-sm-6">
                        
                <div class="card">
                    <div class="card-header">
                    <h4 class="card-title">Detalle de la Receta
                    <div class="float-end"><a target="_blank" href="/pdf/receta/<?=$data['id_receta'];?>" class="btn icon btn-primary"><i data-feather="printer"></i></a></div>
                    </h4>
                    </div>
                    <div class="card-body">

                    <div><small class="text-primary">Fecha: </small> <label class="fs-5"><?=$data['fecha_receta'];?></label>, <small class="text-primary">Hora: </small> <label class="fs-5"><?=$data['hora_receta'];?></label></div>
                    <div class="mt-3"><small class="text-primary">Diagnostico: </small> <label class="fs-5"><?=$data['diagnostico_receta'];?></label></div>

                    <label class="mt-4"><small class="text-primary">Medicamento: </small></label>
                    <div class="fs-5"><?=$data['medicamento_receta'];?></div>

                                        
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
    <script src="<?=RUTA_JS?>search-main.js"></script>
    
</body>
</html>

