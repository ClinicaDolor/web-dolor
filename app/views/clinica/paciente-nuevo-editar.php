<?php 
use App\Config\Database;
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
    <link rel="shortcut icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="apple-touch-icon" href="<?=RUTA_IMAGES ?>/logo-clinica.png">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    <link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>

    <script>
        
        function GuardarPaciente(idPaciente){
        const NombreCompleto = document.getElementById('NombreCompleto').value;
        const Edad = document.getElementById('Edad').value;
        const Sexo = document.getElementById('Sexo').value;
        const EstadoCivil = document.getElementById('EstadoCivil').value;
        const FeNacimiento = document.getElementById('FeNacimiento').value;
        const CURP = document.getElementById('CURP').value;
        const LuOrigen = document.getElementById('LuOrigen').value;
        const LuResidencia = document.getElementById('LuResidencia').value;
        const Ocupacion = document.getElementById('Ocupacion').value;
        const NumHijos = document.getElementById('NumHijos').value;
        const EdadHijos = document.getElementById('EdadHijos').value;
        const personaRecomienda = document.getElementById('personaRecomienda').value;
        const RedesSociales = document.getElementById('RedesSociales').value;
        const motivoAtencionClinica = document.getElementById('motivoAtencionClinica').value;
        const DACalle = document.getElementById('DACalle').value;
        const DANI = document.getElementById('DANI').value;
        const DANE = document.getElementById('DANE').value;
        const DAColonia = document.getElementById('DAColonia').value;
        const DADelegacion = document.getElementById('DADelegacion').value;
        const DACP = document.getElementById('DACP').value;
        const DAMunicipio = document.getElementById('DAMunicipio').value;
        const Distancia = document.getElementById('Distancia').value;
        const Email = document.getElementById('Email').value;
        const Telefono = document.getElementById('Telefono').value;
        const Celular = document.getElementById('Celular').value;
        const Cuidador = document.getElementById('Cuidador').value;
        const CuiNombre = document.getElementById('CuiNombre').value;
        const CuiTelefono = document.getElementById('CuiTelefono').value;
        const ResNombre = document.getElementById('ResNombre').value;
        const ResTelefono = document.getElementById('ResTelefono').value;

        const parametros = {
        idPaciente : idPaciente,
        NombreCompleto : NombreCompleto,
        Edad : Edad,
        Sexo : Sexo,
        EstadoCivil : EstadoCivil,
        FeNacimiento : FeNacimiento,
        CURP : CURP,
        LuOrigen : LuOrigen,
        LuResidencia : LuResidencia,
        Ocupacion : Ocupacion,
        NumHijos : NumHijos,
        EdadHijos : EdadHijos,
        personaRecomienda : personaRecomienda,
        RedesSociales : RedesSociales,
        motivoAtencionClinica : motivoAtencionClinica,
        DACalle : DACalle,
        DANI : DANI,
        DANE : DANE,
        DAColonia : DAColonia,
        DADelegacion : DADelegacion,
        DACP : DACP,
        DAMunicipio : DAMunicipio,
        Distancia : Distancia,
        Email : Email,
        Telefono : Telefono,
        Celular : Celular,
        Cuidador : Cuidador,
        CuiNombre : CuiNombre,
        CuiTelefono : CuiTelefono,
        ResNombre : ResNombre,
        ResTelefono : ResTelefono
        }

        fetch('/clinica/paciente/insert-edit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(parametros)
    })
    .then(response => response.json())
    .then(data => {

        if (data.resultado) {
            window.location.href = '/clinica/paciente/' + data.mensaje;
        } else {
            document.getElementById('mensaje').textContent = 'Error: ' + data.mensaje;
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
    <div class="page-title"><h3><?=$data['title'];?></h3></div>
            
    <section class="section mt-4">      
    <div class="card">
    <div class="card-body">
    <div class="row">

    <div class="col-12 col-sm-6 mb-3">
    <h8 class="text-primary fw-bold texto">* Nombre completo:</h8>
    <input type="text" class="form-control" id="NombreCompleto" value="<?=$data['nombre_paciente'] ?? ''?>">
    </div>

    <div class="col-12 col-sm-3 mb-3">
    <h8 class="text-primary fw-bold texto">* Edad:</h8>
    <div class="input-group">
    <input type="number" class="form-control" min="0" id="Edad" value="<?=$data['edad'] ?? ''?>">
    <span class="input-group-text">años</span>
    </div>
    </div>

    <div class="col-12 col-sm-3 mb-3">
    <h8 class="text-primary fw-bold texto">* Sexo:</h8>
    <select class="form-select" id="Sexo">
    <option value="<?=$data['sexo'] ?? ''?>"><?php if(isset($data['sexo'])){ echo ($data['sexo'] == 'M')? 'Masculino': 'Femenino';}else{echo 'Seleccione una opción...';} ?></option>
    <option value="M">Masculino</option>
    <option value="F">Femenino</option>
    </select>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">* Estado civil:</h8>
    <select class="form-select" id="EstadoCivil">
    <option value="<?=$data['estado_civil'] ?? ''?>"><?php if(isset($data['estado_civil'])){ echo $data['estado_civil'];}else{echo 'Seleccione una opción';} ?></option>
    <option value="Soltero(a)">Soltero(a)</option>
    <option value="Casado(a)">Casado(a)</option>
    <option value="Viudo(a)">Viudo(a)</option>
    </select>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">* Fecha de nacimiento:</h8>
    <input type="date" class="form-control" id="FeNacimiento" value="<?=$data['fecha_nacimiento'] ?? ''?>">
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">* CURP:</h8>
    <input type="text" class="form-control" id="CURP" onkeyup="mayus(this);" value="<?=$data['curp'] ?? ''?>">
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">* Lugar de Origen:</h8>
    <input type="text" class="form-control" id="LuOrigen" value="<?=$data['lugar_origen'] ?? ''?>">
    </div>
    
    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">* Lugar de Residencia:</h8>
    <input type="text" class="form-control" id="LuResidencia" value="<?=$data['lugar_residencia'] ?? '' ?>">
    </div>
    
    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">* Ocupación:</h8>
    <input type="text" class="form-control" id="Ocupacion" value="<?=$data['ocupacion'] ?? '' ?>">
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">* Número de hijos:</h8>
    <input type="text" class="form-control" id="NumHijos" value="<?=$data['num_hijos'] ?? '' ?>">
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">* Edad de sus hijos:</h8>
    <input type="text" class="form-control" id="EdadHijos" value="<?=$data['edad_hijos'] ?? '' ?>">
    </div>

    <div class="col-12 mb-3">
    <hr>
    <p class="text-success fw-bold texto">¿Quien lo recomienda? O porque medio se entero de la Clinica del Dolor y Cuidados Paliativos?:</p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Persona que lo recomendo:</h8>
    <input type="text" class="form-control" id="personaRecomienda" value="<?=$data['quien_recomienda'] ?? '' ?>"> 
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Redes sociales:</h8>
    <select class="form-select" id="RedesSociales">
    <option value="<?=$data['redes_sociales'] ?? ''?>"><?php if(isset($data['redes_sociales'])){ echo $data['redes_sociales'];}else{echo 'Seleccione una opción...';} ?></option>
    <option>Facebook</option>
    <option>Pagina web</option>
    <option>Otro</option>
    </select>
    </div>

    <div class="col-12 mb-3">
    <hr>
    <h8 class="text-primary fw-bold texto">* Motivo por el que solicita atención en la Clinica de Dolor y Cuidados Paliativos:</h8>
    <textarea class="form-control" id="motivoAtencionClinica"><?=$data['motivo_atencion'] ?? '' ?></textarea>
    </div>

    <div class="col-12 mb-3">
    <hr>
    <p class="text-success fw-bold texto">Dirección actual</p>
    </div>

    <div class="col-12 col-sm-8 mb-3">
    <h8 class="text-primary fw-bold texto">Calle:</h8>
    <input type="text" class="form-control" id="DACalle" value="<?=$data['calle'] ?? '' ?>">
    </div>

    <div class="col-12 col-sm-2 mb-3">
    <h8 class="text-primary fw-bold texto">Numero Interior:</h8>
    <input type="text" class="form-control" id="DANI" value="<?=$data['num_interior'] ?? '' ?>">
    </div>

    <div class="col-12 col-sm-2 mb-3">
    <h8 class="text-primary fw-bold texto">Numero Exterior:</h8>
    <input type="text" class="form-control" id="DANE" value="<?=$data['num_exterior'] ?? '' ?>">
    </div>

    <div class="col-12 col-sm-5 mb-3">
    <h8 class="text-primary fw-bold texto">Colonia:</h8>
    <input type="text" class="form-control" id="DAColonia" value="<?=$data['colonia'] ?? '' ?>">
    </div>

    <div class="col-12 col-sm-5 mb-3">
    <h8 class="text-primary fw-bold texto">Delegación:</h8>
    <input type="text" class="form-control" id="DADelegacion" value="<?=$data['delegacion'] ?? '' ?>">
    </div>

    <div class="col-12 col-sm-2 mb-3">
    <h8 class="text-primary fw-bold texto">Código postal:</h8>
    <input type="text" class="form-control" id="DACP" value="<?=$data['cp'] ?? '' ?>">
    </div>

    <div class="col-12 col-sm-6 mb-3">
    <h8 class="text-primary fw-bold texto">Municipio:</h8>
    <input type="text" class="form-control" id="DAMunicipio" value="<?=$data['municipio'] ?? '' ?>">
    </div>

    <div class="col-12 col-sm-6 mb-3">
    <h8 class="text-primary fw-bold texto">Distancia de casa a Clínica del Dolor Hospital Ángeles Lomas:</h8>
    <div class="input-group">
    <input type="text" class="form-control" id="Distancia" value="<?=$data['distancia'] ?? '' ?>">
    <span class="input-group-text"> (Minutos / Horas)</span>
    </div>
    </div>

    <div class="col-12 mb-3">
    <hr>
    <p class="text-success fw-bold texto">Contacto</p>
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Correo electrónico:</h8>
    <input type="text" class="form-control" id="Email" value="<?=$data['email'] ?? ''?>">
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Teléfono de casa:</h8>
    <input type="number" class="form-control" id="Telefono" value="<?=$data['telefono'] ?? ''?>">
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Celular:</h8>
    <input type="number" class="form-control" id="Celular" value="<?=$data['celular'] ?? ''?>">
    </div>

    <div class="col-12 col-sm-3 mb-3">
    <h8 class="text-primary fw-bold texto">¿Tiene cuidador(a)?:</h8>
    <select class="form-control" id="Cuidador">
    <option value="<?php if(isset($data['cuidador'])){ echo ($data['cuidador'])? 'Si': 'No';}else{echo '';} ?>"><?php if(isset($data['cuidador'])){ echo ($data['cuidador'])? 'Si': 'No';}else{echo 'Seleccione una opción...';} ?></option>
    <option value="Si">Si</option>
    <option value="No">No</option>
    </select>
    </div>

    <div class="col-12 col-sm-5 mb-3">
    <h8 class="text-primary fw-bold texto">Nombre:</h8>
    <input type="text" class="form-control" id="CuiNombre" value="<?=$data['cuidador'] ?? '' ?>">
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Teléfono:</h8>
    <input type="number" class="form-control" id="CuiTelefono" value="<?=$data['cuidador_telefono'] ?? '' ?>">
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Nombre del familiar responsable:</h8>
    <input type="text" class="form-control" id="ResNombre" value="<?=$data['res_nombre'] ?? '' ?>">
    </div>

    <div class="col-12 col-sm-4 mb-3">
    <h8 class="text-primary fw-bold texto">Teléfono:</h8>
    <input type="number" class="form-control" id="ResTelefono" value="<?=$data['res_telefono'] ?? '' ?>">
    </div>

    <div class="col-12 text-end">
    <button class="btn btn-success" onclick="GuardarPaciente(<?=$data['idPaciente'];?>)"><?=$data['titulo_boton'];?> <i data-feather="chevron-right"></i></button>
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

