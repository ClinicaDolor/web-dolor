<?php
$e = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$isEdit = (int)($data['idPaciente'] ?? 0) > 0;
?>
<!DOCTYPE html>
<html lang="es-MX" class="doctor-patient-form-document">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=$e($data['title'] ?? 'Paciente');?></title>
<link rel="shortcut icon" href="<?=RUTA_IMAGES?>/logo-clinica.png">
<link rel="apple-touch-icon" href="<?=RUTA_IMAGES?>/logo-clinica.png">
<link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
<link rel="stylesheet" href="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.css">
<link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?=RUTA_JS;?>loader.js"></script>

<script>
let pacienteGuardando = false;

const pacienteCamposObligatorios = Object.freeze({
    Nombres: 'Ingresa los nombres.',
    ApellidoPaterno: 'Ingresa el apellido paterno.',
    ApellidoMaterno: 'Ingresa el apellido materno.',
    Edad: 'Ingresa la edad.',
    Sexo: 'Selecciona el sexo.',
    EstadoCivil: 'Selecciona el estado civil.',
    FeNacimiento: 'Ingresa la fecha de nacimiento.',
    CURP: 'Ingresa la CURP.',
    LuOrigen: 'Ingresa el lugar de origen.',
    LuResidencia: 'Ingresa el lugar de residencia.',
    Ocupacion: 'Ingresa la ocupación.',
    NumHijos: 'Ingresa el número de hijos.',
    EdadHijos: 'Ingresa la edad de sus hijos.',
    motivoAtencionClinica: 'Ingresa el motivo de atención.'
});

function mostrarMensajePaciente(texto, tipo) {
    const mensaje = document.getElementById('mensaje');
    if (!mensaje) return;
    mensaje.hidden = false;
    mensaje.className = 'doctor-form-feedback doctor-form-feedback-' + tipo;
    mensaje.textContent = texto;
}

function limpiarMensajePaciente() {
    const mensaje = document.getElementById('mensaje');
    if (!mensaje) return;
    mensaje.hidden = true;
    mensaje.textContent = '';
    mensaje.className = 'doctor-form-feedback';
}

function limpiarErroresPaciente() {
    Object.keys(pacienteCamposObligatorios).forEach(function (campo) {
        const elemento = document.getElementById(campo);
        const errorId = 'error-' + campo;
        const error = document.getElementById(errorId);

        if (elemento) {
            elemento.classList.remove('is-invalid');
            elemento.removeAttribute('aria-invalid');
            if (elemento.getAttribute('aria-describedby') === errorId) {
                elemento.removeAttribute('aria-describedby');
            }
        }
        if (error) {
            error.hidden = true;
            error.textContent = '';
        }
    });
}

function mostrarErroresPaciente(errores, resumen) {
    limpiarErroresPaciente();
    mostrarMensajePaciente(resumen || 'Completa los campos obligatorios marcados.', 'error');

    let primerCampo = null;
    Object.keys(errores || {}).forEach(function (campo) {
        const elemento = document.getElementById(campo);
        const contenedor = elemento ? elemento.closest('.doctor-form-field') : null;
        if (!elemento || !contenedor) return;

        const errorId = 'error-' + campo;
        let error = document.getElementById(errorId);
        if (!error) {
            error = document.createElement('span');
            error.id = errorId;
            error.className = 'doctor-form-field-error';
            error.setAttribute('role', 'alert');
            error.setAttribute('aria-live', 'polite');
            contenedor.appendChild(error);
        }

        error.textContent = errores[campo];
        error.hidden = false;
        elemento.classList.add('is-invalid');
        elemento.setAttribute('aria-invalid', 'true');
        elemento.setAttribute('aria-describedby', errorId);
        if (!primerCampo) primerCampo = elemento;
    });

    if (primerCampo) primerCampo.focus();
}


function validarPaciente(parametros) {
    const errores = {};
    Object.keys(pacienteCamposObligatorios).forEach(function (campo) {
        const valor = parametros[campo];
        if (valor === null || valor === undefined || String(valor).trim() === '') {
            errores[campo] = pacienteCamposObligatorios[campo];
        }
    });
    return errores;
}

function GuardarPaciente(idPaciente) {
    if (pacienteGuardando) return;

    limpiarMensajePaciente();
    limpiarErroresPaciente();

    const parametros = {
        idPaciente: idPaciente,
        Nombres: document.getElementById('Nombres').value,
        ApellidoPaterno: document.getElementById('ApellidoPaterno').value,
        ApellidoMaterno: document.getElementById('ApellidoMaterno').value,
        Edad: document.getElementById('Edad').value,
        Sexo: document.getElementById('Sexo').value,
        EstadoCivil: document.getElementById('EstadoCivil').value,
        FeNacimiento: document.getElementById('FeNacimiento').value,
        CURP: document.getElementById('CURP').value,
        LuOrigen: document.getElementById('LuOrigen').value,
        LuResidencia: document.getElementById('LuResidencia').value,
        Ocupacion: document.getElementById('Ocupacion').value,
        NumHijos: document.getElementById('NumHijos').value,
        EdadHijos: document.getElementById('EdadHijos').value,
        personaRecomienda: document.getElementById('personaRecomienda').value,
        RedesSociales: document.getElementById('RedesSociales').value,
        motivoAtencionClinica: document.getElementById('motivoAtencionClinica').value,
        DACalle: document.getElementById('DACalle').value,
        DANI: document.getElementById('DANI').value,
        DANE: document.getElementById('DANE').value,
        DAColonia: document.getElementById('DAColonia').value,
        DADelegacion: document.getElementById('DADelegacion').value,
        DACP: document.getElementById('DACP').value,
        DAMunicipio: document.getElementById('DAMunicipio').value,
        Distancia: document.getElementById('Distancia').value,
        Email: document.getElementById('Email').value,
        Telefono: document.getElementById('Telefono').value,
        Celular: document.getElementById('Celular').value,
        Cuidador: document.getElementById('Cuidador').value,
        CuiNombre: document.getElementById('CuiNombre').value,
        CuiTelefono: document.getElementById('CuiTelefono').value,
        ResNombre: document.getElementById('ResNombre').value,
        ResTelefono: document.getElementById('ResTelefono').value
    };

    const erroresLocales = validarPaciente(parametros);
    if (Object.keys(erroresLocales).length > 0) {
        mostrarErroresPaciente(erroresLocales);
        return;
    }

    const button = document.getElementById('guardarPacienteButton');
    pacienteGuardando = true;
    if (button) {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    }

    fetch('/clinica/paciente/insert-edit', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(parametros)
    })
    .then(function (response) {
        return response.json().then(function (data) {
            return {response: response, data: data};
        });
    })
    .then(function (result) {
        if (result.response.ok && result.data.resultado) {
            window.location.href = '/clinica/paciente/' + result.data.mensaje;
            return;
        }
        if (result.data.campos && typeof result.data.campos === 'object') {
            mostrarErroresPaciente(result.data.campos, result.data.mensaje);
        } else {
            mostrarMensajePaciente(result.data.mensaje || 'No fue posible guardar el paciente.', 'error');
        }
    })
    .catch(function () {
        mostrarMensajePaciente('No fue posible conectar con el sistema. Intenta nuevamente.', 'error');
    })
    .finally(function () {
        pacienteGuardando = false;
        if (button) {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    });
}

function mayus(elemento) {
    elemento.value = elemento.value.toUpperCase();
}
</script>
</head>

<body class="app-shell-body app-shell-doctor doctor-patient-form-page">
<div class="LoaderPage"></div>
<div id="app">
<?=$data['sidebar'];?>

<div id="main">
<?php include_once __DIR__ . '/../components/search-bar-doctor.php';?>

<main class="main-content container-fluid" id="main-content">
<header class="doctor-page-hero doctor-form-hero">
    <div>
        <p class="dashboard-eyebrow">EXPEDIENTE CLÍNICO</p>
        <h1><?=$e($data['title'] ?? 'Paciente');?></h1>
        <p><?=($isEdit) ? 'Actualiza la información registrada del paciente.' : 'Registra la información inicial para abrir un expediente.';?></p>
    </div>
    <a href="<?=SERVIDOR?>clinica/pacientes" class="btn btn-light doctor-page-hero-action" data-doctor-back-action>
        <i data-feather="arrow-left" aria-hidden="true"></i>
        <span>Volver a pacientes</span>
    </a>
</header>

<form id="pacienteForm" class="doctor-patient-form-card" onsubmit="event.preventDefault(); GuardarPaciente(<?=$data['idPaciente'];?>);" novalidate>
    <div class="doctor-form-intro">
        <div>
            <p class="dashboard-eyebrow">INFORMACIÓN DEL PACIENTE</p>
            <h2><?=$isEdit ? 'Revisa y actualiza los datos' : 'Completa el registro inicial';?></h2>
            <p>Conserva la información clínica con precisión. Los campos marcados con <span class="doctor-required">*</span> forman parte del formulario actual.</p>
        </div>
        <span class="doctor-form-record-badge"><i data-feather="clipboard" aria-hidden="true"></i> Registro <?=$isEdit ? 'existente' : 'nuevo';?></span>
    </div>

    <div id="mensaje" class="doctor-form-feedback" role="alert" aria-live="polite" hidden></div>

    <fieldset class="doctor-form-section">
        <legend><span class="doctor-form-section-icon"><i data-feather="user" aria-hidden="true"></i></span><span>Identificación clínica</span></legend>
        <div class="doctor-form-grid">
            <div class="doctor-form-field doctor-field--third">
                <label for="Nombres">Nombres <span class="doctor-required">*</span></label>
                <input type="text" class="form-control" id="Nombres" value="<?=$e($data['nombres'] ?? '');?>" autocomplete="given-name" aria-required="true">
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="ApellidoPaterno">Apellido paterno <span class="doctor-required">*</span></label>
                <input type="text" class="form-control" id="ApellidoPaterno" value="<?=$e($data['apellido_paterno'] ?? '');?>" autocomplete="family-name" aria-required="true">
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="ApellidoMaterno">Apellido materno <span class="doctor-required">*</span></label>
                <input type="text" class="form-control" id="ApellidoMaterno" value="<?=$e($data['apellido_materno'] ?? '');?>" aria-required="true">
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="Edad">Edad <span class="doctor-required">*</span></label>
                <div class="input-group">
                    <input type="number" class="form-control" min="0" id="Edad" value="<?=$e($data['edad'] ?? '');?>" aria-required="true">
                    <span class="input-group-text">años</span>
                </div>
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="Sexo">Sexo <span class="doctor-required">*</span></label>
                <select class="form-select" id="Sexo" aria-required="true">
                    <option value="<?=$e($data['sexo'] ?? '');?>"><?php if (isset($data['sexo'])) echo ($data['sexo'] === 'M') ? 'Masculino' : 'Femenino'; else echo 'Seleccione una opción...'; ?></option>
                    <option value="M">Masculino</option>
                    <option value="F">Femenino</option>
                </select>
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="EstadoCivil">Estado civil <span class="doctor-required">*</span></label>
                <select class="form-select" id="EstadoCivil" aria-required="true">
                    <option value="<?=$e($data['estado_civil'] ?? '');?>"><?=$e($data['estado_civil'] ?? 'Seleccione una opción');?></option>
                    <option value="Soltero(a)">Soltero(a)</option>
                    <option value="Casado(a)">Casado(a)</option>
                    <option value="Viudo(a)">Viudo(a)</option>
                </select>
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="FeNacimiento">Fecha de nacimiento <span class="doctor-required">*</span></label>
                <input type="date" class="form-control" id="FeNacimiento" value="<?=$e($data['fecha_nacimiento'] ?? '');?>" aria-required="true">
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="CURP">CURP <span class="doctor-required">*</span></label>
                <input type="text" class="form-control" id="CURP" onkeyup="mayus(this);" value="<?=$e($data['curp'] ?? '');?>" autocomplete="off" aria-required="true">
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="LuOrigen">Lugar de origen <span class="doctor-required">*</span></label>
                <input type="text" class="form-control" id="LuOrigen" value="<?=$e($data['lugar_origen'] ?? '');?>" aria-required="true">
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="LuResidencia">Lugar de residencia <span class="doctor-required">*</span></label>
                <input type="text" class="form-control" id="LuResidencia" value="<?=$e($data['lugar_residencia'] ?? '');?>" aria-required="true">
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="Ocupacion">Ocupación <span class="doctor-required">*</span></label>
                <input type="text" class="form-control" id="Ocupacion" value="<?=$e($data['ocupacion'] ?? '');?>" aria-required="true">
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="NumHijos">Número de hijos <span class="doctor-required">*</span></label>
                <input type="text" class="form-control" id="NumHijos" value="<?=$e($data['num_hijos'] ?? '');?>" aria-required="true">
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="EdadHijos">Edad de sus hijos <span class="doctor-required">*</span></label>
                <input type="text" class="form-control" id="EdadHijos" value="<?=$e($data['edad_hijos'] ?? '');?>" aria-required="true">
            </div>
        </div>
    </fieldset>

    <fieldset class="doctor-form-section">
        <legend><span class="doctor-form-section-icon doctor-form-section-icon-green"><i data-feather="message-circle" aria-hidden="true"></i></span><span>Referencia y motivo de atención</span></legend>
        <div class="doctor-form-grid">
            <div class="doctor-form-field doctor-field--third">
                <label for="personaRecomienda">Persona que lo recomendó</label>
                <input type="text" class="form-control" id="personaRecomienda" value="<?=$e($data['quien_recomienda'] ?? '');?>">
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="RedesSociales">Medio por el que se enteró</label>
                <select class="form-select" id="RedesSociales">
                    <option value="<?=$e($data['redes_sociales'] ?? '');?>"><?=$e($data['redes_sociales'] ?? 'Seleccione una opción...');?></option>
                    <option value="Facebook">Facebook</option>
                    <option value="Pagina web">Página web</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
            <div class="doctor-form-field doctor-field--full">
                <label for="motivoAtencionClinica">Motivo por el que solicita atención <span class="doctor-required">*</span></label>
                <textarea class="form-control" id="motivoAtencionClinica" rows="3" aria-required="true"><?=$e($data['motivo_atencion'] ?? '');?></textarea>
            </div>
        </div>
    </fieldset>

    <fieldset class="doctor-form-section">
        <legend><span class="doctor-form-section-icon"><i data-feather="map-pin" aria-hidden="true"></i></span><span>Dirección actual</span></legend>
        <div class="doctor-form-grid">
            <div class="doctor-form-field doctor-field--address-street">
                <label for="DACalle">Calle</label>
                <input type="text" class="form-control" id="DACalle" value="<?=$e($data['calle'] ?? '');?>">
            </div>
            <div class="doctor-form-field doctor-field--address-small">
                <label for="DANI">Número interior</label>
                <input type="text" class="form-control" id="DANI" value="<?=$e($data['num_interior'] ?? '');?>">
            </div>
            <div class="doctor-form-field doctor-field--address-small">
                <label for="DANE">Número exterior</label>
                <input type="text" class="form-control" id="DANE" value="<?=$e($data['num_exterior'] ?? '');?>">
            </div>
            <div class="doctor-form-field doctor-field--half">
                <label for="DAColonia">Colonia</label>
                <input type="text" class="form-control" id="DAColonia" value="<?=$e($data['colonia'] ?? '');?>">
            </div>
            <div class="doctor-form-field doctor-field--half">
                <label for="DADelegacion">Delegación</label>
                <input type="text" class="form-control" id="DADelegacion" value="<?=$e($data['delegacion'] ?? '');?>">
            </div>
            <div class="doctor-form-field doctor-field--address-small">
                <label for="DACP">Código postal</label>
                <input type="text" class="form-control" id="DACP" value="<?=$e($data['cp'] ?? '');?>">
            </div>
            <div class="doctor-form-field doctor-field--half">
                <label for="DAMunicipio">Municipio</label>
                <input type="text" class="form-control" id="DAMunicipio" value="<?=$e($data['municipio'] ?? '');?>">
            </div>
            <div class="doctor-form-field doctor-field--half">
                <label for="Distancia">Distancia a la clínica</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="Distancia" value="<?=$e($data['distancia'] ?? '');?>">
                    <span class="input-group-text">Min./hrs.</span>
                </div>
            </div>
        </div>
    </fieldset>

    <fieldset class="doctor-form-section">
        <legend><span class="doctor-form-section-icon doctor-form-section-icon-green"><i data-feather="phone" aria-hidden="true"></i></span><span>Contacto y responsables</span></legend>
        <div class="doctor-form-grid">
            <div class="doctor-form-field doctor-field--third">
                <label for="Email">Correo electrónico</label>
                <input type="email" class="form-control" id="Email" value="<?=$e($data['email'] ?? '');?>" autocomplete="email">
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="Telefono">Teléfono de casa</label>
                <input type="tel" class="form-control" id="Telefono" value="<?=$e($data['telefono'] ?? '');?>" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" oninput="this.value = this.value.replace(/\D/g, '')">
            </div>
            <div class="doctor-form-field doctor-field--third">
                <label for="Celular">Celular</label>
                <input type="tel" class="form-control" id="Celular" value="<?=$e($data['celular'] ?? '');?>" maxlength="10" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '')">
            </div>
            <div class="doctor-form-field doctor-field--address-small">
                <label for="Cuidador">¿Tiene cuidador(a)?</label>
                <select class="form-select" id="Cuidador">
                    <option value="<?=$e(isset($data['cuidador']) ? ($data['cuidador'] ? 'Si' : 'No') : '');?>"><?php if (isset($data['cuidador'])) echo $data['cuidador'] ? 'Sí' : 'No'; else echo 'Seleccione una opción...'; ?></option>
                    <option value="Si">Sí</option>
                    <option value="No">No</option>
                </select>
            </div>
            <div class="doctor-form-field doctor-field--caregiver-name">
                <label for="CuiNombre">Nombre del cuidador(a)</label>
                <input type="text" class="form-control" id="CuiNombre" value="<?=$e($data['cuidador'] ?? '');?>">
            </div>
            <div class="doctor-form-field doctor-field--caregiver-phone">
                <label for="CuiTelefono">Teléfono del cuidador(a)</label>
                <input type="tel" class="form-control" id="CuiTelefono" value="<?=$e($data['cuidador_telefono'] ?? '');?>" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" oninput="this.value = this.value.replace(/\D/g, '')">
            </div>
            <div class="doctor-form-field doctor-field--half">
                <label for="ResNombre">Nombre del familiar responsable</label>
                <input type="text" class="form-control" id="ResNombre" value="<?=$e($data['res_nombre'] ?? '');?>">
            </div>
            <div class="doctor-form-field doctor-field--half">
                <label for="ResTelefono">Teléfono del familiar responsable</label>
                <input type="tel" class="form-control" id="ResTelefono" value="<?=$e($data['res_telefono'] ?? '');?>" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" oninput="this.value = this.value.replace(/\D/g, '')">
            </div>
        </div>
    </fieldset>

    <div class="doctor-form-footer">
        <p><span class="doctor-required">*</span> Campos marcados en el formulario clínico actual.</p>
        <div class="doctor-form-actions">
            <a href="<?=SERVIDOR?>clinica/pacientes" class="btn btn-light">Cancelar</a>
            <button id="guardarPacienteButton" type="submit" class="btn btn-success">
                <span><?=$e($data['titulo_boton'] ?? 'Guardar Paciente');?></span>
                <i data-feather="check" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</form>
</main>

<?php include_once __DIR__ . '/../components/footer-doctor.php';?>
</div>
</div>

<script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="<?=RUTA_JS;?>app.js"></script>
<script src="<?=RUTA_JS;?>main.js"></script>
<script src="<?=RUTA_JS?>search-main.js"></script>
</body>
</html>
