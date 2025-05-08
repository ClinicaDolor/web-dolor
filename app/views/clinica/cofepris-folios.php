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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
.swal2-confirm {
    background-color: #501E75 !important; /* Azul */
    color: #fff !important;
}
.swal2-cancel {
    background-color: #6c757d !important; /* Gris */
    color: #fff !important;
}
</style>
    <script>
        function surtirProducto(idCofepris){

        Swal.fire({
        title: '¿Estás seguro?',
        text: "Este cambio no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {

            const parametros = {
                idCofepris : idCofepris
            };

        fetch('/clinica/cofepris/edit-surtido', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(parametros)
        })
        .then(response => response.json())
        .then(data => {


        if (data.resultado) {

            Swal.fire({
            title: 'Hecho',
            text: 'El cambio se realizó.',
            icon: 'success',
            showConfirmButton: false,
            timer: 2000
            });

            setTimeout(function() {
                location.reload()
            }, 2000);

        } else {

            Swal.fire({
            title: 'Error',
            text: 'El cambio no fue realizó.',
            icon: 'question',
            showConfirmButton: false,
            timer: 2000
            });
        }

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

        <div id="main">
            
            <!----- BUSCADOR DE LA BARRA DE NAVEGACION ---------->
            <?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>
            
            <div class="main-content container-fluid">
            <div class="page-title">
                <h3><?=$data['title'];?></h3>
            </div>

            <section class="mt-3">
            <div class="card">
            <div class="card-body">
                

                <?php
                use App\Config\Database;
                use App\Models\CofeprisModel;
                $bd = Database::getInstance();
                $model = new CofeprisModel();

                $query = "SELECT * FROM cofepris_folio WHERE carpeta = :id";
                $stmt = $bd->prepare($query);
                $stmt->bindParam(':id', $data['id_carpeta']);
                $stmt->execute();
                $registros = $stmt->fetch(\PDO::FETCH_ASSOC);

                $folio_inicial = $registros['folio_inicial'];
                $folio_final = $registros['folio_final'];

                echo ' <table class="table table-striped table-hover table-sm pb-0 mb-0" id="cofepris">
                <thead>
                <tr>
                    <th class="text-center">Folio</th>
                    <th>Fecha y Hora</th>
                    <th>Paciente</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center" width="25px"><i data-feather="download"></i></th>
                </tr>
                </thead>
                <tbody>';
                for ($i = $folio_inicial; $i <= $folio_final; $i++) { 

                    $folio = $model->validaCofeprisFolio($i, $data['id_carpeta']);

                    if($folio['id_cofepris'] != 0){

                        $fecha = (new \DateTime($folio['fecha_hora']))->format('d/m/Y');
                        $hora = (new \DateTime($folio['fecha_hora']))->format('h:i a');

                        $paciente = $folio['paciente'];
                        $estado = '<span class="badge bg-success">Finalizado</span>';
                        $descargar = '<a target="_BLANK" href="' . SERVIDOR . 'pdf/cofepris/'.$folio['id_cofepris'].'"><i data-feather="download"></i></a>';
                        $colot_table = 'table-success';

                      }else{
                        $fecha = '';
                        $hora = '';
                        $paciente = '';
                        $estado = '<span class="badge bg-danger">Pendiente</span>';
                        $descargar = '<a"><i data-feather="download"></i></a>';
                        $colot_table = 'table-secondary';

                    }

                    if($folio['id_cofepris'] == 0 && $folio['surtido'] == 0){
                        $surtido = '';
                    }else if($folio['id_cofepris'] != 0 && $folio['surtido'] == 0){
                        $surtido = '<a class="pointer" onclick="surtirProducto('.$folio['id_cofepris'].')"><span class="badge bg-light">No Surtido</span></a>';
                    }else if($folio['id_cofepris'] != 0 && $folio['surtido'] == 1){
                        $surtido = '<span class="badge bg-primary">Surtido</span>';
                    }
                   
                    echo '<tr class="'.$colot_table.'">
                        <td class="text-center fw-bold">' . $i . '</td>
                        <td>' . $fecha . ' ' . $hora . '</td>
                        <td>' . $paciente . '</td>
                        <td class="text-center">' . $estado . ' ' . $surtido .'</td>
                        <td class="text-center">' . $descargar . '</td>
                    </tr>';
                    
                }

                echo '
                </tbody>
                </table>';
                ?>      

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
    document.addEventListener("DOMContentLoaded", function() {
    const tableElement = document.querySelector('#cofepris');

    // Recuperar estado de la tabla desde localStorage
    const savedState = JSON.parse(localStorage.getItem('datatableState')) || {};

    const dataTable = new simpleDatatables.DataTable(tableElement, {
        searchable: true,
        fixedHeight: true,
        perPage: savedState.perPage || 20,
        perPageSelect: [20, 50, 100],
        sortable: true,
        columns: [
            { select: 0, sort: savedState.sort || 'asc' },
            { select: [4], sortable: false },
        ]
    });

    // Restaurar página y búsqueda después de inicializar
    dataTable.on('datatable.init', function () {
        if (savedState.page) {
            dataTable.page(savedState.page);
        }
        if (savedState.search) {
            dataTable.input.value = savedState.search;
            dataTable.search(savedState.search);
        }
    });

    // Guardar estado cada vez que cambia algo
    dataTable.on('datatable.page', function (page) {
        savedState.page = page;
        localStorage.setItem('datatableState', JSON.stringify(savedState));
    });

    dataTable.on('datatable.perpage', function (perPage) {
        savedState.perPage = perPage;
        localStorage.setItem('datatableState', JSON.stringify(savedState));
    });

    dataTable.on('datatable.sort', function (column, direction) {
        savedState.sort = direction;
        localStorage.setItem('datatableState', JSON.stringify(savedState));
    });

    dataTable.on('datatable.search', function (query) {
        savedState.search = query;
        localStorage.setItem('datatableState', JSON.stringify(savedState));
    });
});
</script>

</body>
</html>

