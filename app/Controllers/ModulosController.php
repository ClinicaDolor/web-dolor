<?php
namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\AntecedenteFamiliarModel;
use App\Models\AntecedentesNoPatologicosModel;
use App\Models\AntecedentesQuirurgicos;
use App\Models\AntecedentesPatologicosModel;
use App\Models\ProcedimientosDolorModel;
use App\Models\MedicacionActualModel;
use App\Models\MedicacionDolorModel;
use App\Models\EvaluacionDolorModel;
use App\Models\PacienteModulosModelo;
use App\Models\PacienteModel;
use App\Helpers\Sidebar;
use App\Controllers\SidebarController;
use App\Core\HttpMethod;
use App\Services\ClinicalSummarySecurity;

class ModulosController extends BaseController {

    public function __construct(){

    }

    
    private function nombreModulo($modulo){
    $elementoModulo = "";
    if($modulo == "ficha-identificiacion"){
    $elementoModulo = "Ficha de identificación del paciente";
    
    }else if($modulo == "antecedentes-familiares"){
    $elementoModulo = "Antecedentes familiares";
       
    }else if($modulo == "antecedentes-personales-no-patologicos"){
    $elementoModulo = "Antecedentes Personales No Patológicos";
       
    }else if($modulo == "antecedentes-personales-quirurgicos"){
    $elementoModulo = "Antecedentes Personales Quirúrgicos";
       
    }else if($modulo == "antecedentes-personales-patologicos"){
    $elementoModulo = "Antecedentes Personales Patológicos";
       
    }else if($modulo == "medicacion-actual"){
    $elementoModulo = "Medicación Actual";
       
    }else if($modulo == "medicacion-control-dolor"){
    $elementoModulo = "Medicamentos que ha utilizado para controlar el dolor";
       
    }else if($modulo == "procedimientos-control-dolor"){
    $elementoModulo = "Procedimientos que ha utilizado para controlar el dolor";
    
    }else if($modulo == "evaluacion-dolor"){
    $elementoModulo = "Evaluación del dolor";
    }

    return $elementoModulo;
    }


    //---------- VISTA DEL DOCTOR ----------
    public function moduloVistaDoctor($modulo, $idPaciente): void {
         
    $authGuard = new AuthMiddleware('clinica');
    $sidebar = new Sidebar();
    $sidebarController = new SidebarController();
    $authMiddleware = $authGuard->authPermisos();
    $authGuard->assertPatientAccess($idPaciente, $authMiddleware);
    $rolUsuario = $authMiddleware['rol'];
    
    $paciente = new PacienteModel($idPaciente);
    
    $sidebarController->configureSidebar('DOCTOR', $modulo, $sidebar, $idPaciente);
    $elementoModulo = $this->nombreModulo($modulo);
    
    $sidebar->setActivarItem($elementoModulo);
    $sidebarHtml = $sidebar->render();
    
    if($modulo == "ficha-identificiacion"){

        $data = ['title' => $elementoModulo, 
        'idPaciente' => $idPaciente, 
        'nombre' => $paciente->getNombreCompleto(), 
        'sexo' => $paciente->getSexo(),
    
        'fecha_nacimiento' => $paciente->getFechaNacimiento(),
        'edad' => $paciente->getEdad(),
        'estado_civil' => $paciente->getEstadoCivil(),
        'curp' => $paciente->getCurp(),  
        'lugar_origen' => $paciente->getLugarOrigen(),
        'lugar_residencia' => $paciente->getLugarResidencia(),
        'ocupacion' => $paciente->getOcupacion(),
        'num_hijos' => $paciente->getNumHijos(),
        'edad_hijos' => $paciente->getEdadHijos(),
        'quien_recomienda' => $paciente->getRecomienda(),
        'redes_sociales' => $paciente->getRedesSociales(),
        'motivo_atencion' => $paciente->getMotivoAtencion(),
        'calle' => $paciente->getCalle(),
        'num_interior' => $paciente->getNumInterior(),
        'num_exterior' => $paciente->getNumExterior(),
        'colonia' => $paciente->getColonia(),
        'delegacion' => $paciente->getDelegacion(),
        'cp' => $paciente->getCp(),
        'municipio' => $paciente->getMunicipio(),
        'distancia' => $paciente->getDistancia(),
        'email' => $paciente->getEmail(),
        'telefono' => $paciente->getTelefono(),
        'celular' => $paciente->getCelular(),  
        'cuidador' => $paciente->getCuidador(),  
        'cuidador_telefono' => $paciente->getCuidadorTelefono(),  
        'res_nombre' => $paciente->getResNombre(), 
        'res_telefono' => $paciente->getResTelefono(),  
    
        'idRol' => $rolUsuario, 
        'sidebar' => $sidebarHtml
        ];
    
        }else{
    
        $data = ['title' => $elementoModulo, 
        'idPaciente' => $idPaciente, 
        'nombre' => $paciente->getNombreCompleto(), 
        'sexo' => $paciente->getSexo(),
        'idRol' => $rolUsuario, 
        'sidebar' => $sidebarHtml
        ];
    
        }
    
    $this->view("/historia-clinica-doctor/$modulo.php", $data);
    }

    //---------- VISTA DEL PACIENTE ----------
    public function moduloVistaPaciente($modulo, $idPaciente): void {
    $authGuard = new AuthMiddleware('historia-clinica');
    $sidebar = new Sidebar();
    $sidebarController = new SidebarController();
    $authMiddleware = $authGuard->authPermisos();
    $authGuard->assertPatientAccess($idPaciente, $authMiddleware);
    $rolUsuario = $authMiddleware['rol'];

    $paciente = new PacienteModel($idPaciente);

    $sidebarController->configureSidebar('PACIENTE', $modulo, $sidebar, $idPaciente);
    $elementoModulo = $this->nombreModulo($modulo);

    $sidebar->setActivarItem($elementoModulo);
    $sidebarHtml = $sidebar->render();

    if($modulo == "ficha-identificiacion"){

    $data = ['title' => $elementoModulo, 
    'idPaciente' => $idPaciente, 
    'nombre' => $paciente->getNombreCompleto(), 
    'sexo' => $paciente->getSexo(),

    'fecha_nacimiento' => $paciente->getFechaNacimiento(),
    'edad' => $paciente->getEdad(),
    'estado_civil' => $paciente->getEstadoCivil(),
    'curp' => $paciente->getCurp(),  
    'lugar_origen' => $paciente->getLugarOrigen(),
    'lugar_residencia' => $paciente->getLugarResidencia(),
    'ocupacion' => $paciente->getOcupacion(),
    'num_hijos' => $paciente->getNumHijos(),
    'edad_hijos' => $paciente->getEdadHijos(),
    'quien_recomienda' => $paciente->getRecomienda(),
    'redes_sociales' => $paciente->getRedesSociales(),
    'motivo_atencion' => $paciente->getMotivoAtencion(),
    'calle' => $paciente->getCalle(),
    'num_interior' => $paciente->getNumInterior(),
    'num_exterior' => $paciente->getNumExterior(),
    'colonia' => $paciente->getColonia(),
    'delegacion' => $paciente->getDelegacion(),
    'cp' => $paciente->getCp(),
    'municipio' => $paciente->getMunicipio(),
    'distancia' => $paciente->getDistancia(),
    'email' => $paciente->getEmail(),
    'telefono' => $paciente->getTelefono(),
    'celular' => $paciente->getCelular(),  
    'cuidador' => $paciente->getCuidador(),  
    'cuidador_telefono' => $paciente->getCuidadorTelefono(),  
    'res_nombre' => $paciente->getResNombre(), 
    'res_telefono' => $paciente->getResTelefono(),  

    'idRol' => $rolUsuario, 
    'sidebar' => $sidebarHtml
    ];

    }else{

    $data = ['title' => $elementoModulo, 
    'idPaciente' => $idPaciente, 
    'nombre' => $paciente->getNombreCompleto(), 
    'sexo' => $paciente->getSexo(),
    'idRol' => $rolUsuario, 
    'sidebar' => $sidebarHtml
    ];

    }

    $this->view("/historia-clinica-paciente/$modulo.php", $data);
    }

    public function moduloVistaResumen($modulo, $idPaciente){

    $authGuard = new AuthMiddleware('clinica');
    $sidebar = new Sidebar();
    $sidebarController = new SidebarController();
    $authMiddleware = $authGuard->authPermisos();
    $authGuard->assertPatientAccess($idPaciente, $authMiddleware);
    $rolUsuario = $authMiddleware['rol'];
        
    $paciente = new PacienteModel($idPaciente);
        
    $sidebarController->configureSidebar('DOCTOR', $modulo, $sidebar, $idPaciente);
    $elementoModulo = $this->nombreModulo($modulo);
        
    $sidebar->setActivarItem($elementoModulo);
    $sidebarHtml = $sidebar->render();
    
    if($modulo == "ficha-identificiacion"){

    $data = ['title' => $elementoModulo, 
    'idPaciente' => $idPaciente, 
    'nombre' => $paciente->getNombreCompleto(), 
    'sexo' => $paciente->getSexo(),

    'fecha_nacimiento' => $paciente->getFechaNacimiento(),
    'edad' => $paciente->getEdad(),
    'estado_civil' => $paciente->getEstadoCivil(),
    'curp' => $paciente->getCurp(),  
    'lugar_origen' => $paciente->getLugarOrigen(),
    'lugar_residencia' => $paciente->getLugarResidencia(),
    'ocupacion' => $paciente->getOcupacion(),
    'num_hijos' => $paciente->getNumHijos(),
    'edad_hijos' => $paciente->getEdadHijos(),
    'quien_recomienda' => $paciente->getRecomienda(),
    'redes_sociales' => $paciente->getRedesSociales(),
    'motivo_atencion' => $paciente->getMotivoAtencion(),
    'calle' => $paciente->getCalle(),
    'num_interior' => $paciente->getNumInterior(),
    'num_exterior' => $paciente->getNumExterior(),
    'colonia' => $paciente->getColonia(),
    'delegacion' => $paciente->getDelegacion(),
    'cp' => $paciente->getCp(),
    'municipio' => $paciente->getMunicipio(),
    'distancia' => $paciente->getDistancia(),
    'email' => $paciente->getEmail(),
    'telefono' => $paciente->getTelefono(),
    'celular' => $paciente->getCelular(),  
    'cuidador' => $paciente->getCuidador(),  
    'cuidador_telefono' => $paciente->getCuidadorTelefono(),  
    'res_nombre' => $paciente->getResNombre(), 
    'res_telefono' => $paciente->getResTelefono(),  

    'idRol' => $rolUsuario, 
    'sidebar' => $sidebarHtml
    ];

    }else{

    $data = ['title' => $elementoModulo, 
    'idPaciente' => $idPaciente, 
    'nombre' => $paciente->getNombreCompleto(), 
    'sexo' => $paciente->getSexo(),
    'idRol' => $rolUsuario, 
    'sidebar' => $sidebarHtml
    ];

    }
    
    $this->view("resumen-clinica-doctor/$modulo.php", $data);
    }

    

 
    //---------- 2. ANTECEDENTES FAMILIARES ----------
    public function pacienteInsertEnfermedad(){
    $data = json_decode(file_get_contents('php://input'), true);
    $idRol = $data['idRol'];  // Obtener el idRol
    $idRol == "Paciente" ? $view = "historia-clinica" : $view = "clinica";
    
    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
    $authMiddleware->assertPatientAccess($data['idPaciente'] ?? 0, $auth);
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
    return;
    }

    $model = new AntecedenteFamiliarModel();
    $resultModel = $model->agregarEnfermedadPaciente($data);

    if ($resultModel['resultado'] == 200) {
    echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
    } else {
    echo HttpMethod::jsonResponse(401, false, $resultModel['mensaje']);
    }

    }

    public function pacienteEditEnfermedad(){
    $data = json_decode(file_get_contents('php://input'), true);
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
    return;
    }

    if (!is_array($data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de actualización no válidos.');
    return;
    }

    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (strpos($requestPath, '/historia-clinica/paciente/') !== false) {
    $view = 'historia-clinica';
    } elseif (strpos($requestPath, '/clinica/paciente/') !== false) {
    $view = 'clinica';
    } else {
    echo HttpMethod::jsonResponse(400, false, 'Contexto de actualización no válido.');
    return;
    }

    $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $idEnfermedad = filter_var($data['idEnfermedad'] ?? 0, FILTER_VALIDATE_INT);
    $edicion = filter_var($data['edicion'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]]);
    if ($idPaciente === false || $idEnfermedad === false || $idEnfermedad < 0 || $edicion === false || !array_key_exists('detalle', $data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de actualización no válidos.');
    return;
    }

    $data['idPaciente'] = $idPaciente;
    $data['idEnfermedad'] = $idEnfermedad;
    $data['enfermedad'] = is_scalar($data['enfermedad'] ?? null) ? (string)$data['enfermedad'] : '';
    $data['edicion'] = $edicion;

    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
    $authMiddleware->assertPatientAccess($idPaciente, $auth);
            
    $model = new AntecedenteFamiliarModel();
    $resultModel = $model->editarEnfermedadPaciente($data);
        
    if ($resultModel['resultado'] == 200) {
    echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
    } elseif ($resultModel['resultado'] == 403) {
    echo HttpMethod::jsonResponse(403, false, 'No tienes autorización para actualizar esta respuesta.');
    } else {
    echo HttpMethod::jsonResponse(422, false, 'No se pudo actualizar la respuesta.');
    }
            
    }
    
    public function pacienteDeleteEnfermedad(){
    $data = json_decode(file_get_contents('php://input'), true);
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
    return;
    }

    if (!is_array($data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de eliminación no válidos.');
    return;
    }

    $idEnfermedad = filter_var($data['idEnfermedad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idEnfermedad === false) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de eliminación no válidos.');
    return;
    }

    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (strpos($requestPath, '/historia-clinica/') !== false) {
    $view = 'historia-clinica';
    } elseif (strpos($requestPath, '/clinica/') !== false) {
    $view = 'clinica';
    } else {
    echo HttpMethod::jsonResponse(400, false, 'Contexto de eliminación no válido.');
    return;
    }

    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
    $model = new AntecedenteFamiliarModel();

    if ($view === 'historia-clinica') {
    // En el portal del paciente, el paciente autorizado procede del JWT.
    $idPacienteAutorizado = $authMiddleware->assertPatientAccess((int)$auth['id_usuario'], $auth);
    } else {
    // En la vista clínica, resolver primero el propietario del registro y
    // después validar que pertenezca al ámbito de la clínica autenticada.
    $idPacienteDelRegistro = $model->obtenerPacienteDeEnfermedad((int)$idEnfermedad);
    if ($idPacienteDelRegistro === null) {
    echo HttpMethod::jsonResponse(404, false, 'No se encontró el antecedente solicitado.');
    return;
    }
    $idPacienteAutorizado = $authMiddleware->assertPatientAccess($idPacienteDelRegistro, $auth);
    }

    $data['idEnfermedad'] = (int)$idEnfermedad;
    $data['idPaciente'] = $idPacienteAutorizado;

    $resultModel = $model->eliminarEnfermedadPaciente($data);
    
    if ($resultModel['resultado'] == 200) {
    echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
    } elseif ($resultModel['resultado'] == 404) {
    echo HttpMethod::jsonResponse(404, false, $resultModel['mensaje']);
    } else {
    echo HttpMethod::jsonResponse(401, false, $resultModel['mensaje']);
    }
    
    }

   //----- 3. ANTECEDENTES NO PATOLOGICOS ----------
   public function pacienteEditarCuestionarioM3(){
    $data = json_decode(file_get_contents('php://input'), true);
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
    return;
    }

    if (!is_array($data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de respuesta no válidos.');
    return;
    }

    $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $idRespuesta = filter_var($data['idRespuesta'] ?? 0, FILTER_VALIDATE_INT);
    $idPregunta = filter_var($data['idPregunta'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $idTema = filter_var($data['idTema'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $idTipo = $data['idTipo'] ?? null;
    if ($idPaciente === false || $idRespuesta === false || $idRespuesta < 0 || $idPregunta === false || $idTema === false
        || !in_array($idTipo, ['input', 'select'], true)
        || !array_key_exists('detalle', $data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de respuesta no válidos.');
    return;
    }

    $data['idPaciente'] = $idPaciente;
    $data['idRespuesta'] = $idRespuesta;
    $data['idPregunta'] = $idPregunta;
    $data['idTema'] = $idTema;
    $data['idTipo'] = $idTipo;
    if (array_key_exists('expectedRespuesta', $data) && !is_scalar($data['expectedRespuesta'])) {
    echo HttpMethod::jsonResponse(422, false, 'Valor previo no válido.');
    return;
    }

    $csrfToken = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
    if (!ClinicalSummarySecurity::validCaptureCsrf((int)$idPaciente, is_scalar($csrfToken) ? (string)$csrfToken : null)) {
    echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.');
    return;
    }

    // El ámbito se resuelve por la ruta y la cookie autenticada; idRol del
    // navegador sólo conserva compatibilidad con el contrato existente.
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (strpos($requestPath, '/historia-clinica/paciente/') !== false) {
    $view = 'historia-clinica';
    } elseif (strpos($requestPath, '/clinica/paciente/') !== false) {
    $view = 'clinica';
    } else {
    echo HttpMethod::jsonResponse(400, false, 'Contexto de actualización no válido.');
    return;
    }
    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
    $authMiddleware->assertPatientAccess($idPaciente, $auth);

    $model = new AntecedentesNoPatologicosModel();
    $resultModel = $model->editarCuestionarioModulo($data);

    if ($resultModel['resultado'] == 200) {
    echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
    } elseif ($resultModel['resultado'] == 403) {
    echo HttpMethod::jsonResponse(403, false, 'No tienes autorización para actualizar esta respuesta.');
    } elseif ($resultModel['resultado'] == 409) {
    echo HttpMethod::jsonResponse(409, false, $resultModel['mensaje']);
    } else {
    echo HttpMethod::jsonResponse(422, false, $resultModel['mensaje']);
    }

    }
    
    //---------- 4. ANTECEDENTES PERSONALES QUIRURGICOS ----------
    public function pacienteInsertCirugia(){
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
    return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de cirugía no válidos.');
    return;
    }

    $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idPaciente === false) {
    echo HttpMethod::jsonResponse(422, false, 'Paciente no válido.');
    return;
    }

    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $view = strpos($requestPath, '/historia-clinica/') !== false ? 'historia-clinica' : (strpos($requestPath, '/clinica/') !== false ? 'clinica' : null);
    if ($view === null) {
    echo HttpMethod::jsonResponse(400, false, 'Contexto de actualización no válido.');
    return;
    }

    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
    $authMiddleware->assertPatientAccess($idPaciente, $auth);
    $data['idPaciente'] = $idPaciente;

    $model = new AntecedentesQuirurgicos();
    $resultModel = $model->agregarCirugiaPaciente($data);

    if ($resultModel['resultado'] == 200) {
    echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
    } else {
    echo HttpMethod::jsonResponse(422, false, 'No se pudo agregar la cirugía.');
    }
    }

    public function pacienteEditCirugia(){
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
    return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de actualización no válidos.');
    return;
    }

    $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $idCirugia = filter_var($data['idCirugia'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $edicion = filter_var($data['edicion'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3]]);
    if ($idPaciente === false || $idCirugia === false || $edicion === false || !array_key_exists('detalle', $data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de actualización no válidos.');
    return;
    }

    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $view = strpos($requestPath, '/historia-clinica/') !== false ? 'historia-clinica' : (strpos($requestPath, '/clinica/') !== false ? 'clinica' : null);
    if ($view === null) {
    echo HttpMethod::jsonResponse(400, false, 'Contexto de actualización no válido.');
    return;
    }

    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
    $authMiddleware->assertPatientAccess($idPaciente, $auth);
    $data['idPaciente'] = $idPaciente;
    $data['idCirugia'] = $idCirugia;
    $data['edicion'] = $edicion;

    $model = new AntecedentesQuirurgicos();
    $resultModel = $model->editarCirugiaPaciente($data);

    if ($resultModel['resultado'] == 200) {
    echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
    } elseif ($resultModel['resultado'] == 403) {
    echo HttpMethod::jsonResponse(403, false, 'No tienes autorización para actualizar esta cirugía.');
    } else {
    echo HttpMethod::jsonResponse(422, false, 'No se pudo actualizar la cirugía.');
    }
    }
 
    public function pacienteDeleteCirugia(){
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
    return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de eliminación no válidos.');
    return;
    }

    $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $idQuirurgico = filter_var($data['idQuirurgico'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idPaciente === false || $idQuirurgico === false) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de eliminación no válidos.');
    return;
    }

    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $view = strpos($requestPath, '/historia-clinica/') !== false ? 'historia-clinica' : (strpos($requestPath, '/clinica/') !== false ? 'clinica' : null);
    if ($view === null) {
    echo HttpMethod::jsonResponse(400, false, 'Contexto de eliminación no válido.');
    return;
    }

    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
    $authMiddleware->assertPatientAccess($idPaciente, $auth);
    $data['idPaciente'] = $idPaciente;
    $data['idQuirurgico'] = $idQuirurgico;

    $model = new AntecedentesQuirurgicos();
    $resultModel = $model->eliminarCirugiaPaciente($data);

    if ($resultModel['resultado'] == 200) {
    echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
    } elseif ($resultModel['resultado'] == 403) {
    echo HttpMethod::jsonResponse(403, false, 'No tienes autorización para eliminar esta cirugía.');
    } else {
    echo HttpMethod::jsonResponse(422, false, 'No se pudo eliminar la cirugía.');
    }
    }
    
   //----- 5. ANTECEDENTES PATOLOGICOS ----------
   public function pacienteEditarCuestionarioV1M5(){
    $data = json_decode(file_get_contents('php://input'), true);
     if (!is_array($data) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
     header('Content-Type: application/json');
     echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
     return;
     }
     $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
     if ($idPaciente === false) {
     header('Content-Type: application/json');
     echo HttpMethod::jsonResponse(422, false, 'Datos de paciente no válidos.');
     return;
     }
     $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
     $view = str_starts_with($path, '/historia-clinica/') ? 'historia-clinica' : 'clinica';
     $data['idPaciente'] = $idPaciente;
    $data['idPregunta'] = (int)($data['idPregunta'] ?? 0);
     $data['idRol'] = $view === 'historia-clinica' ? 'Paciente' : 'Doctor';
            
    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
     $authMiddleware->assertPatientAccess($idPaciente, $auth);
    header('Content-Type: application/json');
            
    $model = new AntecedentesPatologicosModel();
    $resultModel = $model->editarCuestionarioV1Modulo($data);
        
    if ($resultModel['resultado'] == 200) {
    echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
     } elseif (($resultModel['resultado'] ?? 0) == 403) {
     echo HttpMethod::jsonResponse(403, false, 'No tienes autorización para actualizar esta respuesta.');
     } else {
     echo HttpMethod::jsonResponse(422, false, $resultModel['mensaje']);
    }
            
    }

   public function pacienteEditarCuestionarioV2M5(){
    $data = json_decode(file_get_contents('php://input'), true);
     if (!is_array($data) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
     header('Content-Type: application/json');
     echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
     return;
     }
     $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
     if ($idPaciente === false) {
     header('Content-Type: application/json');
     echo HttpMethod::jsonResponse(422, false, 'Datos de paciente no válidos.');
     return;
     }
     $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
     $view = str_starts_with($path, '/historia-clinica/') ? 'historia-clinica' : 'clinica';
     $data['idPaciente'] = $idPaciente;
     $data['idRol'] = $view === 'historia-clinica' ? 'Paciente' : 'Doctor';
            
    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
     $authMiddleware->assertPatientAccess($idPaciente, $auth);
    header('Content-Type: application/json');
            
    $model = new AntecedentesPatologicosModel();
    $resultModel = $model->editarCuestionarioV2Modulo($data);
        
    if ($resultModel['resultado'] == 200) {
    echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
     } elseif (($resultModel['resultado'] ?? 0) == 403) {
     echo HttpMethod::jsonResponse(403, false, 'No tienes autorización para actualizar esta enfermedad.');
     } else {
     echo HttpMethod::jsonResponse(422, false, $resultModel['mensaje']);
    }
            
    }

   //----- 6. MEDICACION ACTUAL ----------
   public function pacienteInsertMedicamento(){

    $data = json_decode(file_get_contents('php://input'), true);
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_array($data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de medicación no válidos.');
    return;
    }
    $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $view = str_starts_with($path, '/historia-clinica/') ? 'historia-clinica' : (str_starts_with($path, '/clinica/') ? 'clinica' : '');
    if ($view === '') {
    echo HttpMethod::jsonResponse(400, false, 'Contexto de medicación no válido.');
    return;
    }
    $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idPaciente === false) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de paciente no válidos.');
    return;
    }
    $data['idPaciente'] = $idPaciente;
    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
    $authMiddleware->assertPatientAccess($idPaciente, $auth);
    try {
    $resultModel = (new MedicacionActualModel())->agregarMedicacionPaciente($data);
    $status = (int)($resultModel['resultado'] ?? 500);
    echo HttpMethod::jsonResponse($status === 200 ? 200 : ($status === 403 ? 403 : 422), $status === 200, $resultModel['mensaje'] ?? 'No se pudo agregar el medicamento.');
    } catch (\Throwable $exception) {
    http_response_code(500);
    echo HttpMethod::jsonResponse(500, false, 'No se pudo agregar el medicamento. Inténtalo nuevamente.');
    }

   }

   
   public function pacienteEditMedicamento(){
    $data = json_decode(file_get_contents('php://input'), true);
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_array($data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de medicación no válidos.');
    return;
    }
    $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $view = str_starts_with($path, '/historia-clinica/') ? 'historia-clinica' : (str_starts_with($path, '/clinica/') ? 'clinica' : '');
    if ($view === '') {
    echo HttpMethod::jsonResponse(400, false, 'Contexto de medicación no válido.');
    return;
    }
    $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $idMedicamento = filter_var($data['idMedicamento'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idPaciente === false || $idMedicamento === false) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de medicación no válidos.');
    return;
    }
    $data['idPaciente'] = $idPaciente;
    $data['idMedicamento'] = $idMedicamento;
    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
    $authMiddleware->assertPatientAccess($idPaciente, $auth);
    try {
    $resultModel = (new MedicacionActualModel())->editarMedicamentoPaciente($data);
    $status = (int)($resultModel['resultado'] ?? 500);
    $httpStatus = $status === 200 ? 200 : ($status === 403 ? 403 : 422);
    echo HttpMethod::jsonResponse($httpStatus, $status === 200, $resultModel['mensaje'] ?? 'No se pudo guardar la medicación.');
    } catch (\Throwable $exception) {
    http_response_code(500);
    echo HttpMethod::jsonResponse(500, false, 'No se pudo guardar la medicación. Inténtalo nuevamente.');
    }
                
    }

   public function pacienteDeleteMedicamento(){
    $data = json_decode(file_get_contents('php://input'), true);
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_array($data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de medicación no válidos.');
    return;
    }
    $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $view = str_starts_with($path, '/historia-clinica/') ? 'historia-clinica' : (str_starts_with($path, '/clinica/') ? 'clinica' : '');
    if ($view === '') {
    echo HttpMethod::jsonResponse(400, false, 'Contexto de medicación no válido.');
    return;
    }
    $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $idMedicamento = filter_var($data['idMedicamento'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idPaciente === false || $idMedicamento === false) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de medicación no válidos.');
    return;
    }
    $data['idPaciente'] = $idPaciente;
    $data['idMedicamento'] = $idMedicamento;
    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
    $authMiddleware->assertPatientAccess($idPaciente, $auth);
    try {
    $resultModel = (new MedicacionActualModel())->eliminarMedicamentoPaciente($data);
    $status = (int)($resultModel['resultado'] ?? 500);
    $httpStatus = $status === 200 ? 200 : ($status === 403 ? 403 : 422);
    echo HttpMethod::jsonResponse($httpStatus, $status === 200, $resultModel['mensaje'] ?? 'No se pudo eliminar el medicamento.');
    } catch (\Throwable $exception) {
    http_response_code(500);
    echo HttpMethod::jsonResponse(500, false, 'No se pudo eliminar el medicamento. Inténtalo nuevamente.');
    }
        
    }


    //----- 7. MEDICACION PARA CONTROLAR EL DOLOR  ----------
    public function pacienteEditarCuestionarioM7(){
    header('Content-Type: application/json');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo HttpMethod::jsonResponse(405, false, 'Método no permitido. Usa POST.');
    return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de respuesta no válidos.');
    return;
    }
    $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idPaciente === false) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de paciente no válidos.');
    return;
    }
    $path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    if (strpos($path, '/historia-clinica/') !== false) {
    $view = 'historia-clinica';
    $data['idRol'] = 'Paciente';
    } elseif (strpos($path, '/clinica/') !== false) {
    $view = 'clinica';
    $data['idRol'] = 'Doctor';
    } else {
    echo HttpMethod::jsonResponse(400, false, 'Contexto de actualización no válido.');
    return;
    }
    $data['idPaciente'] = $idPaciente;

    try {
    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
    $authMiddleware->assertPatientAccess($idPaciente, $auth);
    $csrfToken = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
    if (!ClinicalSummarySecurity::validCaptureCsrf((int)$idPaciente, is_scalar($csrfToken) ? (string)$csrfToken : null)) {
    echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.');
    return;
    }
    $resultModel = (new MedicacionDolorModel())->editarCuestionarioM7Paciente($data);
    $status = (int)($resultModel['resultado'] ?? 422);
    $httpStatus = $status === 200 ? 200 : (in_array($status, [403, 409], true) ? $status : 422);
    echo HttpMethod::jsonResponse($httpStatus, $status === 200, $resultModel['mensaje'] ?? 'No se pudo actualizar la respuesta.', '', ['code' => $resultModel['code'] ?? null, 'idRespuesta' => (int)($resultModel['idRespuesta'] ?? 0), 'sin_cambios' => (bool)($resultModel['sin_cambios'] ?? false)]);
    } catch (\Throwable $exception) {
    http_response_code(500);
    echo HttpMethod::jsonResponse(500, false, 'No se pudo actualizar la respuesta. Inténtalo nuevamente.');
    }
    }

    //---------- 8. PROCEFIMIENTOS PARA CONTROLAR EL DOLOR ----------
    private function p10Payload(): array
    {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Datos no válidos.');
        }

        $idPaciente = filter_var($payload['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idPaciente === false) {
            throw new \InvalidArgumentException('Datos no válidos.');
        }

        // The session determines the role and clinic. idRol from the browser
        // is intentionally ignored for authorization and rendering decisions.
        $guard = new AuthMiddleware('');
        $auth = $guard->authAny();
        $guard->assertPatientAccess($idPaciente, $auth);
        $payload['idPaciente'] = (int) $idPaciente;
        $payload['idRolReal'] = (($auth['vista'] ?? '') === 'historia-clinica') ? 'Paciente' : 'Doctor';
        return $payload;
    }

    private function p10HandleError(\Throwable $exception): void
    {
        // Technical details stay in the server log; the client receives only
        // a stable, actionable message.
        error_log('[P10] operación no completada: ' . get_class($exception));
        echo HttpMethod::jsonResponse(500, false, 'No se pudo completar la operación. Inténtalo nuevamente.');
    }

    public function pacienteInsertProcedimientos(){
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo HttpMethod::jsonResponse(405, false, 'Método no permitido.'); return; }
    try {
        $data = $this->p10Payload();
        $resultModel = (new ProcedimientosDolorModel())->p10AgregarProcedimiento($data);
        echo HttpMethod::jsonResponse($resultModel['resultado'] === 200 ? 200 : $resultModel['resultado'], $resultModel['resultado'] === 200, $resultModel['mensaje']);
    } catch (\InvalidArgumentException $exception) {
        echo HttpMethod::jsonResponse(422, false, 'Datos de procedimiento no válidos.');
    } catch (\Throwable $exception) {
        $this->p10HandleError($exception);
    }
    }

    
    public function pacienteEditProcedimiento(){
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo HttpMethod::jsonResponse(405, false, 'Método no permitido.'); return; }
    try {
        $data = $this->p10Payload();
        $resultModel = (new ProcedimientosDolorModel())->p10EditarProcedimiento($data);
        $status = (int) $resultModel['resultado'];
        echo HttpMethod::jsonResponse($status === 200 ? 200 : $status, $status === 200, $resultModel['mensaje']);
    } catch (\InvalidArgumentException $exception) {
        echo HttpMethod::jsonResponse(422, false, 'Datos de procedimiento no válidos.');
    } catch (\Throwable $exception) {
        $this->p10HandleError($exception);
    }
    }

    public function pacienteDeleteProcedimiento(){
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo HttpMethod::jsonResponse(405, false, 'Método no permitido.'); return; }
    try {
        $data = $this->p10Payload();
        $resultModel = (new ProcedimientosDolorModel())->p10EliminarProcedimiento($data);
        $status = (int) $resultModel['resultado'];
        echo HttpMethod::jsonResponse($status === 200 ? 200 : $status, $status === 200, $resultModel['mensaje']);
    } catch (\InvalidArgumentException $exception) {
        echo HttpMethod::jsonResponse(422, false, 'Datos de procedimiento no válidos.');
    } catch (\Throwable $exception) {
        $this->p10HandleError($exception);
    }
    }

    

    public function pacienteEditTratamiento(){
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo HttpMethod::jsonResponse(405, false, 'Método no permitido.'); return; }
    try {
        $data = $this->p10Payload();
        $resultModel = (new ProcedimientosDolorModel())->p10EditarTratamiento($data);
        $status = (int) $resultModel['resultado'];
        echo HttpMethod::jsonResponse($status === 200 ? 200 : $status, $status === 200, $resultModel['mensaje']);
    } catch (\InvalidArgumentException $exception) {
        echo HttpMethod::jsonResponse(422, false, 'Datos de tratamiento no válidos.');
    } catch (\Throwable $exception) {
        $this->p10HandleError($exception);
    }
    }

    //---------- 9. EVALUACION DEL DOLOR ----------
    private function p11Payload(): array
    {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
    throw new \InvalidArgumentException('Datos no válidos.');
    }
    $idPaciente = filter_var($payload['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idPaciente === false) {
    throw new \InvalidArgumentException('Datos no válidos.');
    }
    $guard = new AuthMiddleware('');
    $auth = $guard->authAny();
    $guard->assertPatientAccess($idPaciente, $auth);
    $csrfToken = $_SERVER['HTTP_X_M03_CSRF'] ?? ($payload['csrf_token'] ?? null);
    if (!ClinicalSummarySecurity::validCaptureCsrf((int) $idPaciente, is_scalar($csrfToken) ? (string) $csrfToken : null)) {
    throw new \RuntimeException('P11_CSRF_INVALID');
    }
    $payload['idPaciente'] = (int) $idPaciente;
    $payload['idRolReal'] = (($auth['vista'] ?? '') === 'historia-clinica') ? 'Paciente' : 'Doctor';
    return $payload;
    }

    private function p11Error(\Throwable $exception): void
    {
    error_log('[P11] operación no completada: ' . get_class($exception));
    if ($exception->getMessage() === 'P11_CSRF_INVALID') {
    echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.');
    return;
    }
    echo HttpMethod::jsonResponse(500, false, 'No se pudo completar la operación. Inténtalo nuevamente.');
    }

    public function pacienteImagenEvaluacionDolor($orientacion, $idPaciente): void
    {
    $guard = new AuthMiddleware('');
    $auth = $guard->authAny();
    $idPaciente = filter_var($idPaciente, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idPaciente === false || !in_array($orientacion, ['frente', 'espalda'], true)) {
    http_response_code(404);
    return;
    }
    $guard->assertPatientAccess($idPaciente, $auth);
    $path = (new EvaluacionDolorModel())->p11ImagePath((int) $idPaciente, $orientacion);
    if (!$path || !is_file($path)) {
    http_response_code(404);
    return;
    }
    header('Content-Type: image/png');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    }

    public function pacienteEditImgED1(){
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo HttpMethod::jsonResponse(405, false, 'Método no permitido.'); return; }
    try {
    $data = $this->p11Payload();
    $result = (new EvaluacionDolorModel())->p11GuardarImagen($data['idPaciente'], 'frente', (string) ($data['imgFrente'] ?? ''));
    $status = (int) ($result['resultado'] ?? 422);
    echo HttpMethod::jsonResponse($status === 200 ? 200 : $status, $status === 200, $result['mensaje']);
    } catch (\InvalidArgumentException $exception) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de imagen no válidos.');
    } catch (\Throwable $exception) { $this->p11Error($exception); }
    }

    public function pacienteDeleteImgED1(){
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo HttpMethod::jsonResponse(405, false, 'Método no permitido.'); return; }
    try {
    $data = $this->p11Payload();
    $result = (new EvaluacionDolorModel())->p11LimpiarImagen($data['idPaciente'], 'frente');
    $status = (int) ($result['resultado'] ?? 422);
    echo HttpMethod::jsonResponse($status === 200 ? 200 : $status, $status === 200, $result['mensaje']);
    } catch (\InvalidArgumentException $exception) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de imagen no válidos.');
    } catch (\Throwable $exception) { $this->p11Error($exception); }
    }

    public function pacienteEditImgED2(){
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo HttpMethod::jsonResponse(405, false, 'Método no permitido.'); return; }
    try {
    $data = $this->p11Payload();
    $result = (new EvaluacionDolorModel())->p11GuardarImagen($data['idPaciente'], 'espalda', (string) ($data['imgEspalda'] ?? ''));
    $status = (int) ($result['resultado'] ?? 422);
    echo HttpMethod::jsonResponse($status === 200 ? 200 : $status, $status === 200, $result['mensaje']);
    } catch (\InvalidArgumentException $exception) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de imagen no válidos.');
    } catch (\Throwable $exception) { $this->p11Error($exception); }
    }

    public function pacienteDeleteImgED2(){
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo HttpMethod::jsonResponse(405, false, 'Método no permitido.'); return; }
    try {
    $data = $this->p11Payload();
    $result = (new EvaluacionDolorModel())->p11LimpiarImagen($data['idPaciente'], 'espalda');
    $status = (int) ($result['resultado'] ?? 422);
    echo HttpMethod::jsonResponse($status === 200 ? 200 : $status, $status === 200, $result['mensaje']);
    } catch (\InvalidArgumentException $exception) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de imagen no válidos.');
    } catch (\Throwable $exception) { $this->p11Error($exception); }
    }



    public function pacienteEditEvaluacion(){
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo HttpMethod::jsonResponse(405, false, 'Método no permitido.'); return; }
    try {
    $data = $this->p11Payload();
    $changes = $data['changes'] ?? [];
    if (!is_array($changes) && isset($data['edicion'])) {
    $changes = [(int) $data['edicion'] => $data['detalle'] ?? ''];
    }
    $result = (new EvaluacionDolorModel())->p11ActualizarEvaluacion($data['idPaciente'], $changes);
    $status = (int) ($result['resultado'] ?? 422);
    echo HttpMethod::jsonResponse($status === 200 ? 200 : $status, $status === 200, $result['mensaje']);
    } catch (\InvalidArgumentException $exception) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de evaluación no válidos.');
    } catch (\Throwable $exception) { $this->p11Error($exception); }
    }


    //---------- GUARDAR CONTENIDO DEL HTML ----------
    public function pacienteContenidoEditor(){
    $data = json_decode(file_get_contents('php://input'), true);
    $idRol = $data['idRol'];  // Obtener el idRol
    $idRol == "Paciente" ? $view = "historia-clinica" : $view = "clinica";
                                    
    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
    $authMiddleware->assertPatientAccess($data['idPaciente'] ?? 0, $auth);
    header('Content-Type: application/json');
                        
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
    return;
    }
                        
    $model = new PacienteModulosModelo();
    $resultModel = $model->editarContenidoEditor($data);
                        
    if ($resultModel['resultado'] == 200) {
    echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
    } else {
    echo HttpMethod::jsonResponse(401, false, $resultModel['mensaje']);
    }
                        
    } 

    //---------- COMENTARIOS MODULO ----------
    public function pacienteComentarioModulo(){
    $data = json_decode(file_get_contents('php://input'), true);
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
    return;
    }

    if (!is_array($data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de comentario no válidos.');
    return;
    }

    $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $idModulo = filter_var($data['idModulo'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idPaciente === false || $idModulo === false || !array_key_exists('comentarioModulos', $data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de comentario no válidos.');
    return;
    }

    $data['idPaciente'] = $idPaciente;
    $data['idModulo'] = $idModulo;
    // El rol recibido se conserva para compatibilidad, pero nunca decide el
    // ámbito de autorización de la petición.
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (strpos($requestPath, '/historia-clinica/paciente/') !== false) {
    $view = 'historia-clinica';
    } elseif (strpos($requestPath, '/clinica/paciente/') !== false) {
    $view = 'clinica';
    } else {
    echo HttpMethod::jsonResponse(400, false, 'Contexto de comentario no válido.');
    return;
    }
    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
    $authMiddleware->assertPatientAccess($idPaciente, $auth);

    $model = new PacienteModulosModelo();
    $resultModel = $model->agregarComentariosModulo($data);
        
    if ($resultModel['resultado'] == 200) {
    echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
    } else {
    echo HttpMethod::jsonResponse(401, false, $resultModel['mensaje']);
    }

    }


    public function pacienteDeleteComentario(){
    $data = json_decode(file_get_contents('php://input'), true);
    $idRol = $data['idRol'];  // Obtener el idRol
    $idRol == "Paciente" ? $view = "historia-clinica" : $view = "clinica";
                
    $authMiddleware = new AuthMiddleware($view);
    $auth = $authMiddleware->authPermisos();
    $authMiddleware->assertPatientAccess($data['idPaciente'] ?? 0, $auth);
    header('Content-Type: application/json');
            
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
    return;
    }
            
    $model = new PacienteModulosModelo();
    $resultModel = $model->eliminarComentariosModulo($data);
            
    if ($resultModel['resultado'] == 200) {
    echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
    } else {
    echo HttpMethod::jsonResponse(401, false, $resultModel['mensaje']);
    } 

    }


    //---------- FINALIZAR MODULO ----------
    public function pacienteFinalizarModulo(){
    $authMiddleware = new AuthMiddleware('historia-clinica');
    $auth = $authMiddleware->authPermisos();
    header('Content-Type: application/json');
                
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
    return;
    }
                
    $model = new PacienteModulosModelo();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de finalización no válidos.');
    return;
    }
    $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $idModulo = filter_var($data['idModulo'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idPaciente === false || $idModulo === false) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de finalización no válidos.');
    return;
    }
    if ($idModulo > 9) {
    echo HttpMethod::jsonResponse(422, false, 'Datos de finalización no válidos.');
    return;
    }
    $data['idPaciente'] = $idPaciente;
    $data['idModulo'] = $idModulo;
    $authMiddleware->assertPatientAccess($idPaciente, $auth);
    $m07Model = null;
    if ($idModulo === 7) {
        $csrfToken = $_SERVER['HTTP_X_M03_CSRF'] ?? ($data['csrf_token'] ?? null);
        if (!ClinicalSummarySecurity::validCaptureCsrf((int)$idPaciente, is_scalar($csrfToken) ? (string)$csrfToken : null)) {
            echo HttpMethod::jsonResponse(403, false, 'Token de seguridad inválido o ausente.');
            return;
        }
        $m07Model = new MedicacionDolorModel();
        $finalizationGuard = $m07Model->validateLegacyM07Finalization((int)$idPaciente);
        if (($finalizationGuard['ok'] ?? false) !== true) {
            echo HttpMethod::jsonResponse(409, false, $finalizationGuard['mensaje'] ?? 'El módulo M07 requiere el formulario actualizado.', '', ['code' => $finalizationGuard['code'] ?? 'M07_EXTENDED_DATA_REQUIRES_UPDATED_FORM']);
            return;
        }
    }
    $resultModel = $idModulo === 9
        ? (new EvaluacionDolorModel())->p11FinalizarModulo($idPaciente)
        : ($idModulo === 8
        ? (new ProcedimientosDolorModel())->p10FinalizarModulo($idPaciente)
        : ($idModulo === 7
            ? $m07Model->finalizarModuloPaciente($idPaciente)
            : ($idModulo === 6
                ? (new MedicacionActualModel())->finalizarModuloPaciente($data)
                : ($idModulo === 4
                    ? $model->finalizarModuloP06($data)
                    : $model->finalizarModulos($data)))));
                
    if ($resultModel['resultado'] == 200) {
    $nextUrl = $model->siguienteModuloPendiente($data['idPaciente'], $data['idModulo']);
    echo HttpMethod::jsonResponse(
    200,
    true,
    $resultModel['mensaje'],
    '',
    ['next_url' => $nextUrl ?? '/historia-clinica']
    );
    } else {
    $resultCode = (int) ($resultModel['resultado'] ?? 422);
    $failureStatus = $idModulo === 8 && in_array($resultCode, [403, 404, 409, 422], true)
        ? $resultCode
        : 401;
    echo HttpMethod::jsonResponse($failureStatus, false, $resultModel['mensaje']);
    } 
    
    }

}
