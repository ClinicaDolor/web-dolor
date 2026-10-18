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
    
    let cofeprisFoliosRequestInFlight = false;
    let cofeprisModalTrigger = null;

    window.addEventListener("pageshow", () => {
        tableCofeprisFolios()
    });

    document.addEventListener("DOMContentLoaded", function() {
        tableCofeprisFolios();

        const modal = document.getElementById('cofepris-config-modal');
        const form = document.getElementById('cofepris-folios-form');
        const openButton = document.getElementById('openCofeprisConfigModal');
        const closeButton = document.getElementById('closeCofeprisConfigModal');
        const cancelButton = document.getElementById('cancelCofeprisConfigModal');

        if (openButton) openButton.addEventListener('click', () => abrirCofeprisConfigModal(openButton));
        if (closeButton) closeButton.addEventListener('click', cerrarCofeprisConfigModal);
        if (cancelButton) cancelButton.addEventListener('click', cerrarCofeprisConfigModal);
        if (form) form.addEventListener('submit', function(event) {
            event.preventDefault();
            AgregarCofepris();
        });
        if (modal) modal.addEventListener('click', function(event) {
            if (event.target === modal) cerrarCofeprisConfigModal();
        });
        document.addEventListener('keydown', function(event) {
            if (!modal || modal.hidden) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                cerrarCofeprisConfigModal();
                return;
            }
            if (event.key !== 'Tab') return;
            const focusable = modal.querySelectorAll('button:not([disabled]), input:not([disabled])');
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    });

    function abrirCofeprisConfigModal(triggerElement = null) {
        const modal = document.getElementById('cofepris-config-modal');
        const closeButton = document.getElementById('closeCofeprisConfigModal');
        if (!modal) return;
        cofeprisModalTrigger = triggerElement || document.getElementById('openCofeprisConfigModal');
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('doctor-cofepris-config-modal-open');
        if (closeButton) closeButton.focus();
    }

    function cerrarCofeprisConfigModal() {
        const modal = document.getElementById('cofepris-config-modal');
        if (!modal || modal.hidden || cofeprisFoliosRequestInFlight) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('doctor-cofepris-config-modal-open');
        if (cofeprisModalTrigger) cofeprisModalTrigger.focus();
    }

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

    if (cofeprisFoliosRequestInFlight) return;

    const referencia = 0;
    const folio = document.getElementById('Folio').value;
    const fileInput = document.getElementById('Archivo');
    const file = fileInput.files[0];
    const submitButton = document.getElementById('submitCofeprisConfig');
    const message = document.getElementById('mensaje');

    document.getElementById('Folio').style.border = "";
    document.getElementById('Archivo').style.border = "";
    message.textContent = '';

    if (folio) {
    if (file) {

        cofeprisFoliosRequestInFlight = true;
        if (submitButton) submitButton.disabled = true;
        $(".LoaderPage").fadeIn(0);
       
            const formData = new FormData();
            formData.append('file', file);
            formData.append('folio', folio);
            const csrfToken = document.getElementById('cofepris-folios-form')?.dataset.cofeprisCsrf || '';
               
            fetch('/clinica/cofepris/insert-folios', {
                    method: 'POST',
                    headers: {
                        'X-COFEPRIS-CSRF': csrfToken
                    },
                    body: formData
                })
                .then(response => {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(data => {
              
                if (data.resultado) {
                    document.getElementById('Folio').value = "";
                    document.getElementById('Archivo').value = "";      
                    cofeprisFoliosRequestInFlight = false;
                    cerrarCofeprisConfigModal();
                    tableCofeprisFolios();
                } else {
                    $(".LoaderPage").fadeOut("slow");
                    message.textContent = 'Error: ' + data.mensaje;
                }

                })
                .catch(() => {
                    message.textContent = 'No fue posible guardar el registro. Intenta nuevamente.';
                })
                .finally(() => {
                    cofeprisFoliosRequestInFlight = false;
                    if (submitButton) submitButton.disabled = false;
                    $(".LoaderPage").fadeOut("slow");
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
                $canCreateCofeprisFolio = !$model->cofeprisFoliosActivos();
                ?>

            <div class="card doctor-cofepris-config-card doctor-cofepris-config-list-card">
                <div class="card-header doctor-cofepris-config-card-header">
                    <div>
                        <p class="dashboard-eyebrow">REGISTROS COFEPRIS</p>
                        <h2 class="doctor-cofepris-config-card-title">Folios COFEPRIS</h2>
                    </div>
                    <button type="button" class="btn doctor-cofepris-config-new-button" id="openCofeprisConfigModal" <?= $canCreateCofeprisFolio ? '' : 'disabled'; ?> aria-controls="cofepris-config-modal" aria-haspopup="dialog">
                        <i data-feather="plus" aria-hidden="true"></i>
                        <span>Nuevo registro</span>
                    </button>
                </div>
                <div class="card-body doctor-cofepris-config-card-body">
                    <div id="conteCofepris" class="doctor-cofepris-config-results" aria-live="polite"></div>
                </div>
            </div>

            <div class="doctor-cofepris-config-modal" id="cofepris-config-modal" role="dialog" aria-modal="true" aria-labelledby="cofepris-config-modal-title" aria-hidden="true" hidden>
                <div class="doctor-cofepris-config-modal-panel" role="document">
                    <div class="doctor-cofepris-config-modal-header">
                        <div>
                            <p class="dashboard-eyebrow">NUEVO REGISTRO</p>
                            <h2 id="cofepris-config-modal-title">Nuevo registro COFEPRIS</h2>
                        </div>
                        <button type="button" class="doctor-cofepris-config-modal-close" id="closeCofeprisConfigModal" aria-label="Cerrar" title="Cerrar">
                            <i data-feather="x" aria-hidden="true"></i>
                        </button>
                    </div>
                    <form id="cofepris-folios-form" class="doctor-cofepris-config-modal-form" novalidate data-cofepris-csrf="<?= htmlspecialchars((string)($data['cofepris_csrf'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="doctor-cofepris-config-modal-body">
                            <p class="doctor-cofepris-config-help">Carga un rango de folios y su archivo PDF de respaldo para habilitarlo en la clínica.</p>

                            <div class="doctor-cofepris-config-field">
                                <label class="doctor-cofepris-config-label" for="Folio">Folio inicial</label>
                                <input type="text" class="form-control doctor-cofepris-config-control" id="Folio" <?= $canCreateCofeprisFolio ? '' : 'disabled'; ?> autocomplete="off">
                            </div>

                            <div class="doctor-cofepris-config-field">
                                <label class="doctor-cofepris-config-label" for="Archivo">Archivo PDF</label>
                                <input type="file" class="form-control doctor-cofepris-config-control" id="Archivo" accept="application/pdf,.pdf" <?= $canCreateCofeprisFolio ? '' : 'disabled'; ?> >
                            </div>

                            <div id="mensaje" class="doctor-cofepris-config-message" role="alert" aria-live="polite"></div>
                        </div>
                        <div class="doctor-cofepris-config-modal-footer">
                            <button type="button" class="btn doctor-cofepris-config-cancel" id="cancelCofeprisConfigModal">Cancelar</button>
                            <button type="submit" class="btn doctor-cofepris-config-submit" id="submitCofeprisConfig" <?= $canCreateCofeprisFolio ? '' : 'disabled'; ?>>
                                <span>Guardar registro</span>
                                <i data-feather="check" aria-hidden="true"></i>
                            </button>
                        </div>
                    </form>
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

