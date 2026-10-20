<?php
namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\ClinicaModel;
use App\Models\PacienteModel;
use App\Helpers\Sidebar;
use App\Core\HttpMethod;
use App\Helpers\CalculadoraEdad;
use App\Models\NotaSubsecuenteModel;
use App\Controllers\SidebarController;
use App\Models\LaboratorioModel;
use App\Models\RecetaModel;
use App\Models\CofeprisModel;
use App\Services\ClinicalSupplementService;
use App\Services\ClinicalSummarySecurity;

class ClinicaController extends BaseController{


//---------- LISTADO RECETAS ----------/
public function recetasIndex(){
$authMiddleware = new AuthMiddleware('clinica');
$sidebar = new Sidebar();
$sidebarController = new SidebarController();

$auth = $authMiddleware->authPermisos();
$idClinica = filter_var($auth['id_clinica'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($idClinica === false) {
http_response_code(403);
exit('No autorizado.');
}

$recetaModel = new RecetaModel();
$recetas = $recetaModel->getRecetasByClinica($idClinica);

$sidebarController->configureSidebar('DOCTOR', 'clinica-recetas', $sidebar);
$sidebar->setActivarItem('Recetas');
$sidebarHtml = $sidebar->render();

$data = [
    'title' => 'Recetas',
    'sidebar' => $sidebarHtml,
    'recetas' => $recetas,
    'recetas_externas_bloqueadas' => true,
];
$this->view('/clinica/recetas.php', $data);
}

//---------- RECETA NUEVA ----------/
public function recetaNueva(){

$authMiddleware = new AuthMiddleware('clinica');
$sidebar = new Sidebar();
$sidebarController = new SidebarController();

$authMiddleware->authPermisos();

$sidebarController->configureSidebar('DOCTOR', 'clinica-receta-nueva', $sidebar);
$sidebar->setActivarItem('Nueva Receta');
$sidebarHtml = $sidebar->render();

$data = ['title' => 'Agregar Nueva Receta', 'sidebar' => $sidebarHtml];
$this->view('/clinica/recetas-nuevas.php', $data);
}
//---------- DETALLE RECETA EXTERNO ----------/
public function recetaExternoDetalle($id){
$authMiddleware = new AuthMiddleware('clinica');
$sidebar = new Sidebar();
$sidebarController = new SidebarController();
$modelExterno = new \App\Models\RecetaExternoModel();

$authMiddleware->authPermisos();
$modelExterno->receta($id);

$sidebarController->configureSidebar('DOCTOR', 'clinica-receta-externo', $sidebar, $id);
$sidebar->setActivarItem('Receta');
$sidebarHtml = $sidebar->render();

$data = ['title' => 'Receta (Externos)',
'id_receta' => $id,
'id_paciente' => $id,
'nombre' => $modelExterno->getNombre(),
'apellido_paterno' => $modelExterno->getApellidoPaterno(),
'apellido_materno' => $modelExterno->getApellidoMaterno(),
'nombre_paciente' => $modelExterno->getNombreCompleto(),
'fecha_nacimiento' => $modelExterno->getFechaNacimiento(),
'edad' => $modelExterno->getEdad(),
'sexo' => $modelExterno->getSexo(),
'fecha_receta' => $modelExterno->getFecha(),
'hora_receta' => $modelExterno->getHora(),
'diagnostico_receta' => $modelExterno->getDiagnostico(),
'medicamento_receta' => $modelExterno->getMedicamento(),
'ta' => $modelExterno->getTA(),
'fc' => $modelExterno->getFC(),
'spo2' => $modelExterno->getSPO2(),
'temperatura' => $modelExterno->getTemperatura(),
'sidebar' => $sidebarHtml];

$this->view('/clinica/receta-externo.php', $data);
}

//---------- EDITAR RECETA EXTERNO (VISTA) ----------/
public function recetaExternoEditar($id){
$authMiddleware = new AuthMiddleware('clinica');
$sidebar = new Sidebar();
$sidebarController = new SidebarController();
$modelExterno = new \App\Models\RecetaExternoModel();

$authMiddleware->authPermisos();
$modelExterno->receta($id);

$sidebarController->configureSidebar('DOCTOR', 'clinica-receta-externo-editar', $sidebar, $id);
$sidebar->setActivarItem('Receta');
$sidebarHtml = $sidebar->render();

$data = ['title' => 'Editar Receta (Externos)',
'id' => $id,
'id_receta' => $id,
'nombre' => $modelExterno->getNombre(),
'apellido_paterno' => $modelExterno->getApellidoPaterno(),
'apellido_materno' => $modelExterno->getApellidoMaterno(),
'nombre_paciente' => $modelExterno->getNombreCompleto(),
'edad' => $modelExterno->getEdad(),
'sexo' => $modelExterno->getSexo(),
'fecha_nacimiento' => $modelExterno->getFechaNacimiento(),
'diagnostico' => $modelExterno->getDiagnostico(),
'medicamento' => $modelExterno->getMedicamento(),
'ta' => $modelExterno->getTA(),
'fc' => $modelExterno->getFC(),
'spo2' => $modelExterno->getSPO2(),
'temperatura' => $modelExterno->getTemperatura(),
'fecha_receta' => $modelExterno->getFecha(),
'hora_receta' => $modelExterno->getHora(),
'sidebar' => $sidebarHtml];

$this->view('/clinica/receta-externo-editar.php', $data);
}

//---------- INSERTAR RECETA EXTERNO ----------/
public function pacienteInsertRecetaExterno(){

$authMiddleware = new AuthMiddleware('clinica');
$authMiddleware->authPermisos();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
return;
}

$model = new \App\Models\RecetaExternoModel();
$data = json_decode(file_get_contents('php://input'), true);
$resultModel = $model->insert($data);

if ($resultModel['resultado'] == 200) {
echo HttpMethod::jsonResponse(200, true, $resultModel['mensaje']);
} else {
echo HttpMethod::jsonResponse(401, false, $resultModel['mensaje']);
}

}

//---------- EDITAR RECETA EXTERNO ----------/
public function pacienteEditRecetaExterno(){

$authMiddleware = new AuthMiddleware('clinica');
$authMiddleware->authPermisos();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
return;
}

$model = new \App\Models\RecetaExternoModel();
$data = json_decode(file_get_contents('php://input'), true);
$resultModel = $model->update($data);

if ($resultModel['resultado'] == 200) {
echo HttpMethod::jsonResponse(200, true, $resultModel['mensaje']);
} else {
echo HttpMethod::jsonResponse(401, false, $resultModel['mensaje']);
}

}


public function pacientesIndex(){
$authMiddleware = new AuthMiddleware('clinica');
$sidebar = new Sidebar();
$sidebarController = new SidebarController();
$model = new ClinicaModel();

$auth = $authMiddleware->authPermisos();
$registros = $model->getPacientesByClinica($auth['id_clinica']);

$sidebarController->configureSidebar('DOCTOR', 'clinica', $sidebar);
$sidebar->setActivarItem('Pacientes');
$sidebarHtml = $sidebar->render();

$data = [
    'title' => 'Pacientes',
    'sidebar' => $sidebarHtml,
    'datos' => $auth,
    'registros' => $registros,
    'total_pacientes' => count($registros),
];
$this->view('/clinica/pacientes.php', $data);
}

public function pacientesModulos($idPaciente){

$authMiddleware = new AuthMiddleware('clinica');
$paciente = new PacienteModel($idPaciente);
$sidebar = new Sidebar();
$sidebarController = new SidebarController();

$auth = $authMiddleware->authPermisos();
$authMiddleware->assertPatientAccess($idPaciente, $auth);
$nombreCompleto = $paciente->getNombreCompleto();

$sidebarController->configureSidebar('DOCTOR', 'clinica-modulos-paciente', $sidebar, $idPaciente);
$sidebar->setActivarItem('Historia Clinica');
$sidebarHtml = $sidebar->render();

$data = ['title' => 'Historia Clinica', 'id_paciente' => $idPaciente, 'nombre_paciente' => $nombreCompleto, 'sidebar' => $sidebarHtml];
$this->view('/clinica/pacientes-modulos.php', $data);
}

public function pacienteNuevo($idPaciente = 0){ 
$authMiddleware = new AuthMiddleware('clinica');
$sidebar = new Sidebar();
$sidebarController = new SidebarController();

$auth = $authMiddleware->authPermisos();

$sidebarController->configureSidebar('DOCTOR', 'clinica-paciente-nuevo', $sidebar, $idPaciente);
$sidebar->setActivarItem('Paciente Nuevo');
$sidebarHtml = $sidebar->render();

$data = ['title' => 'Paciente Nuevo', 'titulo_boton' => 'Guardar Paciente', 'idPaciente' => $idPaciente, 'datos' => $auth, 'sidebar' => $sidebarHtml];
$this->view('/clinica/paciente-nuevo-editar.php', $data);
}

public function pacienteEditar($idPaciente){

$authMiddleware = new AuthMiddleware('clinica');
$sidebar = new Sidebar();
$paciente = new PacienteModel($idPaciente);
$sidebarController = new SidebarController();

$auth = $authMiddleware->authPermisos();
$authMiddleware->assertPatientAccess($idPaciente, $auth);

$nombres = $paciente->getNombres();
$apellido_paterno = $paciente->getApellidoPaterno();
$apellido_materno = $paciente->getApellidoMaterno();

$nombreCompleto = $paciente->getNombreCompleto();
$edad = $paciente->getEdad();
$fechaNacimiento = $paciente->getFechaNacimiento();
$sexo = $paciente->getSexo();
$estado_civil = $paciente->getEstadoCivil();
$curp = $paciente->getCurp();
$lugar_origen = $paciente->getLugarOrigen();
$lugar_residencia = $paciente->getLugarResidencia();
$ocupacion = $paciente->getOcupacion();
$num_hijos = $paciente->getNumHijos();
$edad_hijos = $paciente->getEdadHijos();
$quien_recomienda = $paciente->getRecomienda();
$redes_sociales = $paciente->getRedesSociales();
$motivo_atencion = $paciente->getMotivoAtencion();
$calle = $paciente->getCalle();
$num_interior = $paciente->getNumInterior();
$num_exterior = $paciente->getNumExterior();
$colonia = $paciente->getColonia();
$delegacion = $paciente->getDelegacion();
$cp = $paciente->getCp();
$municipio = $paciente->getMunicipio();
$distancia = $paciente->getDistancia();
$email = $paciente->getEmail();
$telefono = $paciente->getTelefono();
$celular = $paciente->getCelular();
$cuidador = $paciente->getCuidador();
$cuidador_telefono = $paciente->getCuidadorTelefono();
$res_nombre = $paciente->getResNombre();
$res_telefono = $paciente->getResTelefono();


$sidebarController->configureSidebar('DOCTOR', 'clinica-paciente-editar', $sidebar, $idPaciente);
$sidebar->setActivarItem('Paciente Editar');
$sidebarHtml = $sidebar->render();

$data = ['title' => 'Paciente Editar', 
'titulo_boton' => 'Editar Paciente',
'idPaciente' => $idPaciente, 
'datos' => $auth,

'nombres' => $nombres,
'apellido_paterno' => $apellido_paterno,
'apellido_materno' => $apellido_materno,

'nombre_paciente' => $nombreCompleto, 
'fecha_nacimiento' => $fechaNacimiento,
'edad' => $edad,
'sexo' => $sexo, 
'estado_civil' => $estado_civil,
'curp' => $curp,  
'lugar_origen' => $lugar_origen,
'lugar_residencia' => $lugar_residencia,
'ocupacion' => $ocupacion,
'num_hijos' => $num_hijos,
'edad_hijos' => $edad_hijos,
'quien_recomienda' => $quien_recomienda,
'redes_sociales' => $redes_sociales,
'motivo_atencion' => $motivo_atencion,
'calle' => $calle,
'num_interior' => $num_interior,
'num_exterior' => $num_exterior,
'colonia' => $colonia,
'delegacion' => $delegacion,
'cp' => $cp,
'municipio' => $municipio,
'distancia' => $distancia,
'email' => $email,
'telefono' => $telefono,
'celular' => $celular,  
'cuidador' => $cuidador,  
'cuidador_telefono' => $cuidador_telefono,  
'res_nombre' => $res_nombre, 
'res_telefono' => $res_telefono,  

'sidebar' => $sidebarHtml];
$this->view('/clinica/paciente-nuevo-editar.php', $data);

}

public function pacienteInsertEdit(){

$authMiddleware = new AuthMiddleware('clinica');
$result = $authMiddleware->authPermisos();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
return;
}

$model = new ClinicaModel();
$data = json_decode(file_get_contents('php://input'), true);

 if (!is_array($data)) {
 echo HttpMethod::jsonResponse(400, false, "Entrada JSON inválida.");
 return;
 }

 $idPaciente = (int)($data['idPaciente'] ?? 0);
 if ($idPaciente > 0) {
 $authMiddleware->assertPatientAccess($idPaciente, $result);
 }

 $camposObligatorios = [
     'Nombres' => 'Ingresa los nombres.',
     'ApellidoPaterno' => 'Ingresa el apellido paterno.',
     'ApellidoMaterno' => 'Ingresa el apellido materno.',
     'Edad' => 'Ingresa la edad.',
     'Sexo' => 'Selecciona el sexo.',
     'EstadoCivil' => 'Selecciona el estado civil.',
     'FeNacimiento' => 'Ingresa la fecha de nacimiento.',
     'CURP' => 'Ingresa la CURP.',
     'LuOrigen' => 'Ingresa el lugar de origen.',
     'LuResidencia' => 'Ingresa el lugar de residencia.',
     'Ocupacion' => 'Ingresa la ocupación.',
     'NumHijos' => 'Ingresa el número de hijos.',
     'EdadHijos' => 'Ingresa la edad de sus hijos.',
     'motivoAtencionClinica' => 'Ingresa el motivo de atención.'
 ];
 $erroresValidacion = [];
 foreach ($camposObligatorios as $campo => $mensaje) {
     if (!array_key_exists($campo, $data) || !is_scalar($data[$campo]) || trim((string)$data[$campo]) === '') {
         $erroresValidacion[$campo] = $mensaje;
     }
 }
 if ($erroresValidacion) {
     http_response_code(422);
     echo json_encode([
         'resultado' => false,
         'mensaje' => 'Completa los campos obligatorios marcados.',
         'campos' => $erroresValidacion,
         'token' => ''
     ], JSON_UNESCAPED_UNICODE);
     return;
 }

 if($idPaciente == 0){

$resultModelo = $model->insertPaciente($data,$result);

if ($resultModelo['resultado'] == 200) {
echo HttpMethod::jsonResponse(200,true,$resultModelo['mensaje']);
} else {
echo HttpMethod::jsonResponse(401, false, $resultModelo['mensaje']);
}

}else{

$resultModelo = $model->editPaciente($data);

if ($resultModelo['resultado'] == 200) {
echo HttpMethod::jsonResponse(200,true,$resultModelo['mensaje']);
} else {
echo HttpMethod::jsonResponse(401, false, $resultModelo['mensaje']);
}

}        

}

public function pacienteDetalle($idPaciente){

$authMiddleware = new AuthMiddleware('clinica');
$paciente = new PacienteModel($idPaciente);
$sidebar = new Sidebar();
$sidebarController = new SidebarController();

$auth = $authMiddleware->authPermisos();
$authMiddleware->assertPatientAccess($idPaciente, $auth);
$idClinica = filter_var($auth['id_clinica'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($idClinica === false) {
http_response_code(403);
exit('No autorizado.');
}

$fechaAlta = $paciente->getFechaAlta();
$nombreCompleto = $paciente->getNombreCompleto();
$fechaNacimiento = $paciente->getFechaNacimiento();
$sexo = $paciente->getSexo();
$estado_civil = $paciente->getEstadoCivil();
$curp = $paciente->getCurp();

$email = $paciente->getEmail();
$telefono = $paciente->getTelefono();
$celular = $paciente->getCelular();

$edad = CalculadoraEdad::calcularEdad($fechaNacimiento);        

$motivo_atencion = $paciente->getMotivoAtencion();

$sidebarController->configureSidebar('DOCTOR', 'clinica-paciente-detalle', $sidebar, $idPaciente);
$sidebar->setActivarItem('Expediente');
$sidebarHtml = $sidebar->render();

$referencia = uniqid('', true);
$moduleCounts = (new ClinicaModel())->getPatientModuleCounts($idPaciente, $idClinica);
 
$data = ['title' => 'Expediente', 
'idPaciente' => $idPaciente,
'datos' => $auth,
'fecha_alta' => $fechaAlta, 
'nombre_paciente' => $nombreCompleto, 
'fecha_nacimiento' => $fechaNacimiento,
'edad' => $edad,
'sexo' => $sexo, 
'estado_civil' => $estado_civil,
'curp' => $curp, 
'motivo_atencion' => $motivo_atencion,  

'email' => $email,
'telefono' => $telefono,
'celular' => $celular,  
'referencia' => $referencia,  
'module_counts' => $moduleCounts,

'sidebar' => $sidebarHtml];

if (is_null($fechaAlta)) {
$this->view('/errors/404.php');
}else{
$this->view('/clinica/pacientes-detalle.php', $data);
}

}

public function resumenClinico($idPaciente){

$authMiddleware = new AuthMiddleware('clinica');
$paciente = new PacienteModel($idPaciente);
$sidebar = new Sidebar();
$sidebarController = new SidebarController();

$auth = $authMiddleware->authPermisos();
$authMiddleware->assertPatientAccess($idPaciente, $auth);

$fechaAlta = $paciente->getFechaAlta();
$nombreCompleto = $paciente->getNombreCompleto();
$fechaNacimiento = $paciente->getFechaNacimiento();
$sexo = $paciente->getSexo();
$edad = CalculadoraEdad::calcularEdad($fechaNacimiento);

$sidebarController->configureSidebar('DOCTOR', 'clinica-paciente-detalle', $sidebar, $idPaciente);
$sidebar->setActivarItem('Expediente');
$sidebarHtml = $sidebar->render();
$clinicalSupplement = (new ClinicalSupplementService())->forPatient((int)$idPaciente);

$data = [
    'title' => 'Resumen clínico integral',
    'idPaciente' => (int)$idPaciente,
    'datos' => $auth,
    'fecha_alta' => $fechaAlta,
    'nombre_paciente' => $nombreCompleto,
    'fecha_nacimiento' => $fechaNacimiento,
    'edad' => $edad,
    'sexo' => $sexo,
    'sidebar' => $sidebarHtml,
    'clinical_supplement' => $clinicalSupplement,
];

if (is_null($fechaAlta)) {
    $this->view('/errors/404.php');
} else {
    $this->view('/clinica/resumen-clinico.php', $data);
}

}

public function pacientePin($idPaciente){

$authMiddleware = new AuthMiddleware('clinica');
$paciente = new PacienteModel($idPaciente);
$sidebar = new Sidebar();
$sidebarController = new SidebarController();

$auth = $authMiddleware->authPermisos();
$authMiddleware->assertPatientAccess($idPaciente, $auth);

$fechaAlta = $paciente->getFechaAlta();
$nombreCompleto = $paciente->getNombreCompleto();
$fechaNacimiento = $paciente->getFechaNacimiento();
$sexo = $paciente->getSexo();
$estado_civil = $paciente->getEstadoCivil();
$curp = $paciente->getCurp();

$email = $paciente->getEmail();
$telefono = $paciente->getTelefono();
$celular = $paciente->getCelular();

$edad = CalculadoraEdad::calcularEdad($fechaNacimiento); 

$sidebarController->configureSidebar('DOCTOR', 'clinica-paciente-pin', $sidebar, $idPaciente);
$sidebar->setActivarItem('Paciente Pin');
$sidebarHtml = $sidebar->render();


$data = ['title' => 'Paciente Pin', 
'idPaciente' => $idPaciente,
'fecha_alta' => $fechaAlta, 
'nombre_paciente' => $nombreCompleto, 
'fecha_nacimiento' => $fechaNacimiento,
'edad' => $edad,
'sexo' => $sexo, 
'estado_civil' => $estado_civil,
'curp' => $curp,  

'email' => $email,
'telefono' => $telefono,
'celular' => $celular,  
'sidebar' => $sidebarHtml];
$this->view('/clinica/paciente-pin.php', $data);

}

public function pacienteInsertPin(){

$authMiddleware = new AuthMiddleware('clinica');
$authMiddleware->authPermisos();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
return;
}

$model = new ClinicaModel();
$data = json_decode(file_get_contents('php://input'), true);
 if (!is_array($data)) {
 echo HttpMethod::jsonResponse(400, false, "Entrada JSON inválida.");
 return;
 }
 $auth = $authMiddleware->authPermisos();
 $authMiddleware->assertPatientAccess($data['idPaciente'] ?? 0, $auth);
$resultModel = $model->insertPacientePin($data);

if ($resultModel['resultado'] == 200) {
echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
} else {
echo HttpMethod::jsonResponse(401, false, $resultModel['mensaje']);
}

}

public function pacienteReceta($idPaciente){

$authMiddleware = new AuthMiddleware('clinica');
$paciente = new PacienteModel($idPaciente);
$sidebar = new Sidebar();
$sidebarController = new SidebarController();

$auth = $authMiddleware->authPermisos();
$authMiddleware->assertPatientAccess($idPaciente, $auth);
$fechaAlta = $paciente->getFechaAlta();
$nombreCompleto = $paciente->getNombreCompleto();
$fechaNacimiento = $paciente->getFechaNacimiento();
$sexo = $paciente->getSexo();
$estado_civil = $paciente->getEstadoCivil();
$curp = $paciente->getCurp();

$email = $paciente->getEmail();
$telefono = $paciente->getTelefono();
$celular = $paciente->getCelular();

$edad = CalculadoraEdad::calcularEdad($fechaNacimiento);        

$motivo_atencion = $paciente->getMotivoAtencion();

$sidebarController->configureSidebar('DOCTOR', 'clinica-paciente-recetas', $sidebar, $idPaciente);
$sidebar->setActivarItem('Paciente Recetas');
$sidebarHtml = $sidebar->render();

$data = ['title' => 'Recetas', 
'idPaciente' => $idPaciente,
'fecha_alta' => $fechaAlta, 
'nombre_paciente' => $nombreCompleto, 
'fecha_nacimiento' => $fechaNacimiento,
'edad' => $edad,
'sexo' => $sexo, 
'estado_civil' => $estado_civil,
'curp' => $curp, 
'motivo_atencion' => $motivo_atencion,  

'email' => $email,
'telefono' => $telefono,
'celular' => $celular,  

'sidebar' => $sidebarHtml];
$this->view('/clinica/pacientes-recetas.php', $data);

}

public function pacienteInsertReceta(){

$authMiddleware = new AuthMiddleware('clinica');
$authMiddleware->authPermisos();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
return;
}

$model = new ClinicaModel();
$data = json_decode(file_get_contents('php://input'), true);
 if (!is_array($data)) {
 echo HttpMethod::jsonResponse(400, false, "Entrada JSON inválida.");
 return;
 }
$auth = $authMiddleware->authPermisos();
$idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($idPaciente === false) {
http_response_code(422);
echo HttpMethod::jsonResponse(422, false, "Paciente no válido.");
return;
}

$csrfToken = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
if (!ClinicalSummarySecurity::validCaptureCsrf((int)$idPaciente, is_string($csrfToken) ? $csrfToken : null)) {
http_response_code(403);
echo HttpMethod::jsonResponse(403, false, "Token de seguridad inválido o ausente.");
return;
}

$authMiddleware->assertPatientAccess((int)$idPaciente, $auth);
$resultModel = $model->insertPacienteReceta($data);

if ($resultModel['resultado'] == 200) {
echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
} else {
echo HttpMethod::jsonResponse(401, false, $resultModel['mensaje']);
}

}

public function pacienteNotaSubsecuente($idPaciente,$referencia){

$data = $this->notaSubsecuentePacienteContext($idPaciente, $referencia);
$data['title'] = 'Notas Subsecuentes';
$data['list_url'] = SERVIDOR . 'clinica/nota-subsecuente/paciente/' . rawurlencode((string)$data['idPaciente']) . '/referencia/' . rawurlencode((string)$data['referencia']);
$data['new_url'] = $data['list_url'] . '/nueva';
$data['registros'] = (new NotaSubsecuenteModel())->getHistoryByPatient((int)$data['idPaciente']);
$this->view('/clinica/pacientes-nota-subsecuente.php', $data);
}

public function pacienteNuevaNotaSubsecuente($idPaciente,$referencia){

$data = $this->notaSubsecuentePacienteContext($idPaciente, $referencia);
$data['title'] = 'Nueva Nota Subsecuente';
$data['list_url'] = SERVIDOR . 'clinica/nota-subsecuente/paciente/' . rawurlencode((string)$data['idPaciente']) . '/referencia/' . rawurlencode((string)$data['referencia']);
$data['new_url'] = $data['list_url'] . '/nueva';

// La ruta /nueva siempre inicia un registro nuevo. No carga una nota o receta
// existente y, por tanto, no modifica datos durante el GET.
$data += [
    'id_nota_subsecuente' => 0,
    'fecha_hora' => '',
    'ta' => '',
    'frec_cardiaca' => '',
    'pulso' => '',
    'spo2' => '',
    'fio2' => '',
    'ecog' => '',
    'karnovsky' => '',
    'peso' => '',
    'talla' => '',
    'contenido' => '',
    'proximacita' => '',
    'idreceta' => 0,
    'temperatura' => '',
    'diagnostico' => '',
    'medicamento' => '',
];

$this->view('/clinica/pacientes-nota-subsecuente-nueva.php', $data);
}

public function pacienteEditarNotaSubsecuente($idPaciente, $referencia, $idNota){

$data = $this->notaSubsecuentePacienteContext($idPaciente, $referencia);
$idNota = filter_var($idNota, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($idNota === false) {
http_response_code(404);
exit('Nota no encontrada.');
}

$notaModel = new NotaSubsecuenteModel();
$nota = $notaModel->getForPatientReference((int)$data['idPaciente'], (string)$data['referencia'], (int)$idNota);
if ($nota === null) {
http_response_code(404);
exit('Nota no encontrada.');
}

$idReceta = (new RecetaModel())->idRecetaReferencia((int)$data['idPaciente'], (string)$data['referencia']);
$recetaData = [
    'idreceta' => (int)$idReceta,
    'diagnostico' => '',
    'medicamento' => '',
    'temperatura' => '',
];
if ((int)$idReceta > 0) {
    $recetaModel = new RecetaModel();
    $recetaModel->receta((int)$idReceta);
    $recetaData['diagnostico'] = $recetaModel->getDiagnostico();
    $recetaData['medicamento'] = $recetaModel->getMedicamento();
    $recetaData['temperatura'] = $recetaModel->getTemperatura();
}

$data['title'] = 'Editar Nota Subsecuente';
$data['edit_mode'] = true;
$data['list_url'] = SERVIDOR . 'clinica/nota-subsecuente/paciente/' . rawurlencode((string)$data['idPaciente']) . '/referencia/' . rawurlencode((string)$data['referencia']);
$data['new_url'] = $data['list_url'] . '/nueva';
$data += [
    'id_nota_subsecuente' => (int)$nota['id'],
    'fecha_hora' => $nota['fecha_hora'] ?? '',
    'ta' => $nota['ta'] ?? '',
    'frec_cardiaca' => $nota['frec_cardiaca'] ?? '',
    'pulso' => $nota['pulso'] ?? '',
    'spo2' => $nota['spo2'] ?? '',
    'fio2' => $nota['fio2'] ?? '',
    'ecog' => $nota['ecog'] ?? '',
    'karnovsky' => $nota['karnovsky'] ?? '',
    'peso' => $nota['peso'] ?? '',
    'talla' => $nota['talla'] ?? '',
    'contenido' => $nota['contenido'] ?? '',
    'proximacita' => $nota['proxima_cita'] ?? '',
] + $recetaData;

$this->view('/clinica/pacientes-nota-subsecuente-nueva.php', $data);
}

private function notaSubsecuentePacienteContext($idPaciente, $referencia){

$authMiddleware = new AuthMiddleware('clinica');
$auth = $authMiddleware->authPermisos();
$idPaciente = $authMiddleware->assertPatientAccess($idPaciente, $auth);

$paciente = new PacienteModel($idPaciente);
$sidebar = new Sidebar();
$sidebarController = new SidebarController();
$fechaNacimiento = $paciente->getFechaNacimiento();

$sidebarController->configureSidebar('DOCTOR', 'clinica-paciente-notas', $sidebar, $idPaciente, $referencia);
$sidebar->setActivarItem('Paciente Notas');

return [
    'idPaciente' => $idPaciente,
    'fecha_alta' => $paciente->getFechaAlta(),
    'nombre_paciente' => $paciente->getNombreCompleto(),
    'fecha_nacimiento' => $fechaNacimiento,
    'edad' => CalculadoraEdad::calcularEdad($fechaNacimiento),
    'sexo' => $paciente->getSexo(),
    'estado_civil' => $paciente->getEstadoCivil(),
    'curp' => $paciente->getCurp(),
    'motivo_atencion' => $paciente->getMotivoAtencion(),
    'email' => $paciente->getEmail(),
    'telefono' => $paciente->getTelefono(),
    'celular' => $paciente->getCelular(),
    'referencia' => (string)$referencia,
    'nota_csrf' => ClinicalSummarySecurity::captureCsrfToken((int)$idPaciente),
    'sidebar' => $sidebar->render(),
];
}

public function pacienteInsertNotaSubsecuente(){
$authMiddleware = new AuthMiddleware('clinica');
$auth = $authMiddleware->authPermisos();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
return;
}

$model = new NotaSubsecuenteModel();
$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
echo HttpMethod::jsonResponse(400, false, "Entrada JSON inválida.");
return;
}

$idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$idNota = filter_var($data['idNota'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$idReceta = filter_var($data['idReceta'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$referencia = $data['referencia'] ?? null;
$incluirReceta = filter_var($data['incluirReceta'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

if ($idPaciente === false || $idNota === false || $idReceta === false || $incluirReceta === null || !is_scalar($referencia) || (string)$referencia === '') {
echo HttpMethod::jsonResponse(422, false, "Los identificadores de la nota no son válidos.");
return;
}

$csrfToken = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
if (!ClinicalSummarySecurity::validCaptureCsrf((int)$idPaciente, is_scalar($csrfToken) ? (string)$csrfToken : null)) {
http_response_code(403);
echo HttpMethod::jsonResponse(403, false, "Token de seguridad inválido o ausente.");
return;
}

$authMiddleware->assertPatientAccess((int)$idPaciente, $auth);

$data['idPaciente'] = (int)$idPaciente;
$data['idNota'] = (int)$idNota;
$data['idReceta'] = (int)$idReceta;
$data['referencia'] = (string)$referencia;
$data['incluirReceta'] = $incluirReceta;

if ($idNota > 0) {
    $notaModel = new NotaSubsecuenteModel();
    if (!$notaModel->belongsToPatientReference((int)$idNota, (int)$idPaciente, (string)$referencia)) {
        http_response_code(403);
        echo HttpMethod::jsonResponse(403, false, "La nota no pertenece al contexto autorizado.");
        return;
    }

    if ($idReceta > 0) {
        $recetaModel = new RecetaModel();
        if (!$recetaModel->belongsToPatientReference((int)$idReceta, (int)$idPaciente, (string)$referencia)) {
            http_response_code(403);
            echo HttpMethod::jsonResponse(403, false, "La receta no pertenece al contexto autorizado.");
            return;
        }
    }
}

 $resultModel = ($idNota === 0)
? $model->insertNotaSubsecuente($data)
: $model->editarNotaSubsecuente($data);

if ($resultModel['resultado'] == 200) {
echo HttpMethod::jsonResponse(200, true, $resultModel['mensaje']);
} else {
$status = (int)($resultModel['resultado'] ?? 500);
if (!in_array($status, [403, 422, 500], true)) {
    $status = 500;
}
echo HttpMethod::jsonResponse($status, false, $resultModel['mensaje'] ?? 'No fue posible guardar la nota.');
}
}



public function pacienteLaboratorio($idPaciente){

$authMiddleware = new AuthMiddleware('clinica');
$paciente = new PacienteModel($idPaciente);
$sidebar = new Sidebar();
$sidebarController = new SidebarController();

$auth = $authMiddleware->authPermisos();
$authMiddleware->assertPatientAccess($idPaciente, $auth);
$fechaAlta = $paciente->getFechaAlta();
$nombreCompleto = $paciente->getNombreCompleto();
$fechaNacimiento = $paciente->getFechaNacimiento();
$sexo = $paciente->getSexo();
$estado_civil = $paciente->getEstadoCivil();
$curp = $paciente->getCurp();

$email = $paciente->getEmail();
$telefono = $paciente->getTelefono();
$celular = $paciente->getCelular();

$edad = CalculadoraEdad::calcularEdad($fechaNacimiento);        

$motivo_atencion = $paciente->getMotivoAtencion();

$sidebarController->configureSidebar('DOCTOR', 'clinica-paciente-laboratorio', $sidebar, $idPaciente);
$sidebar->setActivarItem('Paciente Laboratorio');
$sidebarHtml = $sidebar->render();

$data = ['title' => 'Laboratorio', 
'idPaciente' => $idPaciente,
'fecha_alta' => $fechaAlta, 
'nombre_paciente' => $nombreCompleto, 
'fecha_nacimiento' => $fechaNacimiento,
'edad' => $edad,
'sexo' => $sexo, 
'estado_civil' => $estado_civil,
'curp' => $curp, 
'motivo_atencion' => $motivo_atencion,  

'email' => $email,
'telefono' => $telefono,
'celular' => $celular,  

'sidebar' => $sidebarHtml];
$this->view('/clinica/pacientes-laboratorio.php', $data);

}
public function pacienteInsertLaboratorio()
{
// Ocultar errores en pantalla y registrar en logs
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$authMiddleware = new AuthMiddleware('clinica');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
echo json_encode([
'resultado' => false,
'mensaje' => 'Método no permitido. Usa POST.'
]);
return;
}

$auth = $authMiddleware->authPermisos();
$idPaciente = filter_var($_POST['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($idPaciente === false) {
http_response_code(422);
echo json_encode([
'resultado' => false,
'mensaje' => 'Paciente no válido.'
]);
return;
}

$csrfToken = $_SERVER['HTTP_X_M03_CSRF'] ?? ($_POST['csrf_token'] ?? null);
if (!ClinicalSummarySecurity::validCaptureCsrf((int)$idPaciente, is_string($csrfToken) ? $csrfToken : null)) {
http_response_code(403);
echo json_encode([
'resultado' => false,
'mensaje' => 'Token de seguridad inválido o ausente.'
]);
return;
}

$authMiddleware->assertPatientAccess((int)$idPaciente, $auth);

$uploadedFile = $_FILES['file'] ?? null;
if (!is_array($uploadedFile) || ($uploadedFile['error'] ?? null) !== UPLOAD_ERR_OK) {
$uploadError = is_array($uploadedFile) ? ($uploadedFile['error'] ?? null) : null;
$uploadMessage = 'No se recibió un archivo válido.';
if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
    $uploadMessage = 'El archivo excede el límite de 10 MiB o el máximo configurado en PHP.';
} elseif ($uploadError === UPLOAD_ERR_PARTIAL) {
    $uploadMessage = 'La carga del archivo quedó incompleta.';
}
http_response_code(422);
echo json_encode([
'resultado' => false,
'mensaje' => $uploadMessage
]);
return;
}

// Obtener datos del archivo
$fileName = $uploadedFile['name'] ?? '';
$fileTmpName = $uploadedFile['tmp_name'] ?? '';
$fileSize = $uploadedFile['size'] ?? 0;
if (!is_string($fileName) || !is_string($fileTmpName)) {
http_response_code(422);
echo json_encode([
'resultado' => false,
'mensaje' => 'El archivo recibido no es válido.'
]);
return;
}
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

try {
    // Insertar en el modelo después de autenticar, autorizar y validar CSRF.
    $model = new LaboratorioModel();
    $resultModel = $model->insertArchivo(
    (int)$idPaciente,
    $_POST['contenidoLaboratorio'] ?? '',
    $fileName,
    $fileTmpName,
    $fileSize,
    $fileExtension,
    $_POST['referencia'] ?? '',
    $_POST['titulo'] ?? ''
    );
} catch (\Throwable $e) {
    error_log('Laboratory upload failed before completion.');
    http_response_code(500);
    echo json_encode([
    'resultado' => false,
    'mensaje' => 'No fue posible guardar el archivo.'
    ]);
    return;
}

// Responder en JSON
echo json_encode([
'resultado' => $resultModel['resultado'] == 200,
'mensaje' => $resultModel['mensaje']
]);
}


public function perfil(){

$authMiddleware = new AuthMiddleware('clinica');
$sidebar = new Sidebar();
$sidebarController = new SidebarController();

$permisos = $authMiddleware->authPermisos();
$idClinica = $permisos['id_clinica'];

$sidebarController->configureSidebar('DOCTOR', 'perfil', $sidebar);
$sidebar->setActivarItem('Perfil');
$sidebarHtml = $sidebar->render();

$data = ['title' => 'Perfil', 'sidebar' => $sidebarHtml, 'idClinica' => $idClinica];
$this->view('/clinica/perfil.php', $data);

}

public function pacienteCofepris($idPaciente, $idCofepris = 0){
$authMiddleware = new AuthMiddleware('clinica');
$paciente = new PacienteModel($idPaciente);
$sidebar = new Sidebar();
$sidebarController = new SidebarController();
$modelCofepris = new CofeprisModel();

$modelCofepris->cofepris($idCofepris);

$auth = $authMiddleware->authPermisos();
$authMiddleware->assertPatientAccess($idPaciente, $auth);
$fechaAlta = $paciente->getFechaAlta();
$nombreCompleto = $paciente->getNombreCompleto();
$fechaNacimiento = $paciente->getFechaNacimiento();
$sexo = $paciente->getSexo();
$estado_civil = $paciente->getEstadoCivil();
$curp = $paciente->getCurp();

$email = $paciente->getEmail();
$telefono = $paciente->getTelefono();
$celular = $paciente->getCelular();

$edad = CalculadoraEdad::calcularEdad($fechaNacimiento);        

$motivo_atencion = $paciente->getMotivoAtencion();

$sidebarController->configureSidebar('DOCTOR', 'clinica-paciente-cofepris', $sidebar, $idPaciente);
$sidebar->setActivarItem('Paciente Cofepris');
$sidebarHtml = $sidebar->render();

$data = ['title' => 'Cofepris', 
'idPaciente' => $idPaciente,
'fecha_alta' => $fechaAlta, 
'nombre_paciente' => $nombreCompleto, 
'fecha_nacimiento' => $fechaNacimiento,
'edad' => $edad,
'sexo' => $sexo, 
'estado_civil' => $estado_civil,
'curp' => $curp, 
'motivo_atencion' => $motivo_atencion,  

'email' => $email,
'telefono' => $telefono,
'celular' => $celular,  

'id_cofepris' => $idCofepris,

'fecha' => $modelCofepris->getFecha(),
'hora' => $modelCofepris->getHora(),
'folio' => $modelCofepris->getFolio(),
'numcajas' => $modelCofepris->getNumcajas(),
'medicamento' => $modelCofepris->getMedicamento(),
'diagnostico' => $modelCofepris->getDiagnostico(),
'presentacion' => $modelCofepris->getPresentacion(),
'dosificacion' => $modelCofepris->getDosificacion(),
'numdias' => $modelCofepris->getNumdias(),
'viaadministracion' => $modelCofepris->getViaadministracion(),

'sidebar' => $sidebarHtml];
$this->view('/clinica/pacientes-cofepris.php', $data);
}

public function pacienteInsertCofepris(){

$authMiddleware = new AuthMiddleware('clinica');
$authMiddleware->authPermisos();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
return;
}

$model = new CofeprisModel();
$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
echo HttpMethod::jsonResponse(400, false, "Entrada JSON inválida.");
return;
}

 $auth = $authMiddleware->authPermisos();
 $authMiddleware->assertPatientAccess($data['idPaciente'] ?? 0, $auth);

if($data['idCofepris'] == 0){
$resultModel = $model->insertPacienteCofepris($data);
}else{
$resultModel = $model->editPacienteCofepris($data);
}


if ($resultModel['resultado'] == 200) {
echo HttpMethod::jsonResponse(200, true, $resultModel['mensaje']);
} else {
echo HttpMethod::jsonResponse(401, false, $resultModel['mensaje']);
}

}

}
