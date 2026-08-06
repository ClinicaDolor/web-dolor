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

class ClinicaController extends BaseController{


//---------- LISTADO RECETAS ----------/
public function recetasIndex(){
$authMiddleware = new AuthMiddleware('clinica');
$sidebar = new Sidebar();
$sidebarController = new SidebarController();

$authMiddleware->authPermisos();

$sidebarController->configureSidebar('DOCTOR', 'clinica-recetas', $sidebar);
$sidebar->setActivarItem('Recetas');
$sidebarHtml = $sidebar->render();

$data = ['title' => 'Recetas', 'sidebar' => $sidebarHtml];
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

$authMiddleware->authPermisos();

$sidebarController->configureSidebar('DOCTOR', 'clinica', $sidebar);
$sidebar->setActivarItem('Pacientes');
$sidebarHtml = $sidebar->render();

$data = ['title' => 'Pacientes', 'sidebar' => $sidebarHtml];
$this->view('/clinica/pacientes.php', $data);
}

public function pacientesModulos($idPaciente){

$authMiddleware = new AuthMiddleware('clinica');
$paciente = new PacienteModel($idPaciente);
$sidebar = new Sidebar();
$sidebarController = new SidebarController();

$authMiddleware->authPermisos();        
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

$authMiddleware->authPermisos();

$sidebarController->configureSidebar('DOCTOR', 'clinica-paciente-nuevo', $sidebar, $idPaciente);
$sidebar->setActivarItem('Paciente Nuevo');
$sidebarHtml = $sidebar->render();

$data = ['title' => 'Paciente Nuevo', 'titulo_boton' => 'Guardar Paciente', 'idPaciente' => $idPaciente, 'sidebar' => $sidebarHtml];
$this->view('/clinica/paciente-nuevo-editar.php', $data);
}

public function pacienteEditar($idPaciente){

$authMiddleware = new AuthMiddleware('clinica');
$sidebar = new Sidebar();
$paciente = new PacienteModel($idPaciente);
$sidebarController = new SidebarController();

$authMiddleware->authPermisos();

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

if($data['idPaciente'] == 0){

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

$authMiddleware->authPermisos();      

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
 
$data = ['title' => 'Expediente', 
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
'referencia' => $referencia,  

'sidebar' => $sidebarHtml];

if (is_null($fechaAlta)) {
$this->view('/errors/404.php');
}else{
$this->view('/clinica/pacientes-detalle.php', $data);
}

}

public function pacientePin($idPaciente){

$authMiddleware = new AuthMiddleware('clinica');
$paciente = new PacienteModel($idPaciente);
$sidebar = new Sidebar();
$sidebarController = new SidebarController();

$authMiddleware->authPermisos();

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

$authMiddleware->authPermisos();        
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
$resultModel = $model->insertPacienteReceta($data);

if ($resultModel['resultado'] == 200) {
echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
} else {
echo HttpMethod::jsonResponse(401, false, $resultModel['mensaje']);
}

}

public function pacienteNotaSubsecuente($idPaciente,$referencia){

$authMiddleware = new AuthMiddleware('clinica');
$paciente = new PacienteModel($idPaciente);
$sidebar = new Sidebar();
$sidebarController = new SidebarController();
$nota = new NotaSubsecuenteModel();
$id_nota_subsecuente = $nota->codigoReferencia($idPaciente, $referencia);
$nota->NotaSubsecuente($id_nota_subsecuente);
$receta = new RecetaModel();
$idReceta = $receta->idRecetaReferencia($idPaciente,$referencia);
$receta->receta($idReceta);

$authMiddleware->authPermisos();        
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

$sidebarController->configureSidebar('DOCTOR', 'clinica-paciente-notas', $sidebar, $idPaciente, $referencia);
$sidebar->setActivarItem('Paciente Notas');
$sidebarHtml = $sidebar->render();

$title = ($id_nota_subsecuente == 0)? 'Nueva Nota Subsecuente' : 'Editar Nota Subsecuente';

$data = ['title' => $title, 
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
'referencia' => $referencia,
'id_nota_subsecuente' => $id_nota_subsecuente,  

'fecha_hora' => $nota->getFechaHora(),
'ta' => $nota->getTa(),
'frec_cardiaca' => $nota->getFrecCardiaca(),
'pulso' => $nota->getPulso(),
'spo2' => $nota->getSpo2(),
'fio2' => $nota->getFio2(),
'ecog' => $nota->getEcog(),
'karnovsky' => $nota->getKarnovsky(),
'peso' => $nota->getPeso(),
'talla' => $nota->getTalla(),
'contenido' => $nota->getContenido(),
'proximacita' => $nota->getProximaCita(),

'idreceta' => $idReceta,
'diagnostico' => $receta->getDiagnostico(),
'medicamento' => $receta->getMedicamento(),

'sidebar' => $sidebarHtml];
$this->view('/clinica/pacientes-nota-subsecuente.php', $data);
}

public function pacienteInsertNotaSubsecuente(){
$authMiddleware = new AuthMiddleware('clinica');
$authMiddleware->authPermisos();

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

$resultModel = ($data['idNota'] == 0)
? $model->insertNotaSubsecuente($data)
: $model->editarNotaSubsecuente($data);

if ($resultModel['resultado'] == 200) {
echo HttpMethod::jsonResponse(200, true, $resultModel['mensaje']);
} else {
echo HttpMethod::jsonResponse(401, false, $resultModel['mensaje']);
}
}



public function pacienteLaboratorio($idPaciente){

$authMiddleware = new AuthMiddleware('clinica');
$paciente = new PacienteModel($idPaciente);
$sidebar = new Sidebar();
$sidebarController = new SidebarController();

$authMiddleware->authPermisos();        
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
$authMiddleware->authPermisos();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
echo json_encode([
'resultado' => false,
'mensaje' => 'Método no permitido. Usa POST.'
]);
return;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
echo json_encode([
'resultado' => false,
'mensaje' => 'No se recibió archivo o hubo un error en la subida. Código de error: ' . ($_FILES['file']['error'] ?? 'desconocido')
]);
return;
}

// Obtener datos del archivo
$fileName = $_FILES['file']['name'];
$fileTmpName = $_FILES['file']['tmp_name'];
$fileSize = $_FILES['file']['size'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Insertar en el modelo
$model = new LaboratorioModel();
$resultModel = $model->insertArchivo(
$_POST['idPaciente'] ?? null,
$_POST['contenidoLaboratorio'] ?? '',
$fileName,
$fileTmpName,
$fileSize,
$fileExtension,
$_POST['referencia'] ?? '',
$_POST['titulo'] ?? ''
);

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

$authMiddleware->authPermisos();        
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