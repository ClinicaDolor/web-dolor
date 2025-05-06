    <?php
    use App\Config\Database;
    $bd = Database::getInstance();

    $query = "SELECT * FROM clinicas WHERE id = '".$data['idClinica']."' ";
    $stmt = $bd->prepare($query);
    $stmt->execute();
    $registros = $stmt->fetch(\PDO::FETCH_ASSOC);

    $fecha_creacion = (new \DateTime($registros['fecha_creacion']))->format('d/m/Y');

    $nombre_clinica = $registros['nombre'];
    $direccion = $registros['direccion'];
    $telefono = $registros['telefono'];
    $email = $registros['email'];
    $sitio_web = $registros['sitio_web'];
    $horarios = $registros['horario'];
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
    <h3><?=$nombre_clinica;?></h3>
    </div>
            
    <section class="section mt-4">

        <div class="row">
            <div class="col-12 col-sm-6">
                <div class="card">
                    <div class="card-body">

                        <small class="text-primary">Dirección completa:</small>
                        <p class="fs-4"><?=$direccion;?></p>

                        <div class="mt-4"><small class="text-primary">Telefono:</small></div>
                        <p class="fs-4"><?=$telefono;?></p>

                        <div class="mt-4"><small class="text-primary">Correo electrónico:</small></div>
                        <p class="fs-4"><?=$email;?></p>

                        <div class="mt-4"><small class="text-primary">Sitio web:</small></div>
                        <p class="fs-4"><?=$sitio_web;?></p>

                        <div class="mt-4"><small class="text-primary">Horarios de atención:</small></div>
                        
                        <?php 
                        $horarios = trim($horarios, "[]");
                        $horarios = explode(",", $horarios);
                        foreach ($horarios as $horario) {echo "<div class='fs-4'>".$horario."</div>";}
                        ?>

                    </div>
                </div>
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
    <script src="<?=RUTA_JS?>search-main.js"></script>

    </body>
    </html>