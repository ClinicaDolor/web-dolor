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

        $(".LoaderPage").fadeIn(0).fadeOut("slow");
       
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

                <?php 
                use App\Models\CofeprisModel;
                $model = new CofeprisModel();     
                $result = ( $model->cofeprisFoliosActivos() )? 'disabled' : 'enabled';
                ?>

            <div class="row">
                <div class="col-12 col-sm-4">
                    
                <div class="card">
                    <div class="card-header text-primary">
                    <h4 class="card-title">Nueva Cofepris</h4>
                    </div>
                    <div class="card-body">
                        
                    <div class="">
                    <label class="text-primary mb-1"><smallal>Folio inicio:</smallal></label>
                    <input type="text" class="form-control" id="Folio" <?=$result;?>>
                    </div>

                    <div class="mt-2">
                    <label class="text-primary mb-1"><smallal>Archivo:</smallal></label>
                    <input type="file" class="form-control" id="Archivo" <?=$result;?>>
                    </div>
                    
                    <div class="text-end mt-3"><button class="btn btn-success" onclick="AgregarCofepris()" <?=$result;?> >Agregar Receta <i data-feather="chevron-right"></i></button></div>
                    <div id="mensaje"></div>
                    </div>
                </div>

                </div>
                <div class="col-12 col-sm-8">

                <div class="card">
                <div class="card-header">
                <h4 class="card-title">Cofepris</h4>
                </div>
                <div class="card-body">

                <div id="conteCofepris"></div>

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
    
</body>
</html>

