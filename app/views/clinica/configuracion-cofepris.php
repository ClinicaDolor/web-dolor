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
        tableCofeprisFolios()
    });

    document.addEventListener("DOMContentLoaded", function() {
        tableCofeprisFolios()
    });

    function tableCofeprisFolios(){

        fetch(`/buscar/tabla-cofepris-folios`)
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
                    dataTable = new simpleDatatables.DataTable(tabla,{
                        searchable: true,
                        fixedHeight: true,
                        perPageSelect: false,
                        columns: [
                        {
                            select: 0, sort: "desc"
                        },
                        { select: [3], sortable: false },
                        ]
                    });
                }  
                
                $(".LoaderPage").fadeOut("slow");
        });

    }

    function AgregarCofepris() {

    const referencia = 0;
    const folio = document.getElementById('Folio').value;
    const fileInput = document.getElementById('Archivo');
    const file = fileInput.files[0];

    document.getElementById('Folio').style.border = "";
    document.getElementById('Archivo').style.border = "";

    if (folio) {
    if (file) {

        $(".LoaderPage").fadeIn(0);
       
            const formData = new FormData();
            formData.append('file', file);
            formData.append('folio', folio);
               
            fetch('/clinica/cofepris/insert-folios', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
              
                if (data.resultado) {
                    document.getElementById('Folio').value = "";
                    document.getElementById('Archivo').value = "";      
                    tableCofeprisFolios();    
                } else {
                    $(".LoaderPage").fadeOut("slow");
                    document.getElementById('mensaje').textContent = 'Error: ' + data.mensaje;
                }

                });
               
    } else {
        document.getElementById('Archivo').style.border = "2px solid #d44e31";
    }
    } else {
        document.getElementById('Folio').style.border = "2px solid #d44e31";
    }
}
    </script>
 </head>
<body class="app-shell-body app-shell-doctor doctor-clinical-view doctor-cofepris-config-page">
<div class="LoaderPage"></div>
    <div id="app">
        
        <?=$data['sidebar'];?>

        <div id="main">
            
            <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
            
            <main class="main-content container-fluid doctor-cofepris-config-content" id="main-content">
            <header class="doctor-page-hero doctor-cofepris-config-hero">
                <div class="doctor-page-hero-copy">
                    <p class="dashboard-eyebrow">CONTROL COFEPRIS</p>
                    <h1>Configuración COFEPRIS</h1>
                    <p>Administra los folios disponibles y consulta el estado de la configuración clínica.</p>
                </div>
            </header>

            <section class="doctor-cofepris-config-section" aria-label="Configuración de folios COFEPRIS">

                <?php 
                use App\Models\CofeprisModel;
                $model = new CofeprisModel();     
                $result = ( $model->cofeprisFoliosActivos() )? 'disabled' : 'enabled';
                ?>

            <div class="row doctor-cofepris-config-grid">
                <div class="col-12 col-xl-4">
                    
                <div class="card doctor-cofepris-config-card doctor-cofepris-config-entry-card">
                    <div class="card-header doctor-cofepris-config-card-header">
                    <div>
                    <p class="dashboard-eyebrow">NUEVO REGISTRO</p>
                    <h2 class="doctor-cofepris-config-card-title">Agregar folios</h2>
                    </div>
                    </div>
                    <div class="card-body doctor-cofepris-config-card-body">
                    <p class="doctor-cofepris-config-help">Carga un rango de folios y su archivo de respaldo para habilitarlo en el sistema.</p>
                        
                    <div class="doctor-cofepris-config-field">
                    <label class="doctor-cofepris-config-label" for="Folio">Folio inicial</label>
                    <input type="text" class="form-control doctor-cofepris-config-control" id="Folio" <?=$result;?> >
                    </div>

                    <div class="doctor-cofepris-config-field">
                    <label class="doctor-cofepris-config-label" for="Archivo">Archivo PDF</label>
                    <input type="file" class="form-control doctor-cofepris-config-control" id="Archivo" <?=$result;?> >
                    </div>
                    
                    <div class="doctor-cofepris-config-actions"><button type="button" class="btn doctor-cofepris-config-submit" onclick="AgregarCofepris()" <?=$result;?> >Agregar folio <i data-feather="chevron-right" aria-hidden="true"></i></button></div>
                    <div id="mensaje" class="doctor-cofepris-config-message" role="alert" aria-live="polite"></div>
                    </div>
                </div>

                </div>
                <div class="col-12 col-xl-8">

                <div class="card doctor-cofepris-config-card doctor-cofepris-config-list-card">
                <div class="card-header doctor-cofepris-config-card-header">
                <div>
                <p class="dashboard-eyebrow">FOLIOS DISPONIBLES</p>
                <h2 class="doctor-cofepris-config-card-title">Folios COFEPRIS</h2>
                </div>
                </div>
                <div class="card-body doctor-cofepris-config-card-body">

                <div id="conteCofepris" class="doctor-cofepris-config-results" aria-live="polite"></div>

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
    <script src="<?=RUTA_PUBLIC;?>libs/quill/quill.min.js"></script>
    <script src="<?=RUTA_JS?>search-main.js"></script>
    
</body>
</html>

