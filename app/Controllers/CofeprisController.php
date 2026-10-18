<?php
namespace App\Controllers;
use App\Middleware\AuthMiddleware;
use App\Helpers\Sidebar;
use App\Models\CofeprisModel;
use App\Models\PacienteModel;
use App\Helpers\CalculadoraEdad;
use App\Core\HttpMethod;
use App\Services\ClinicalSummarySecurity;

class CofeprisController extends BaseController{

    public function cofepris($idCofepris){

        $authMiddleware = new AuthMiddleware('clinica');
        $sidebar = new Sidebar();
        $sidebarController = new SidebarController();
        $modelCofepris = new CofeprisModel();

        $auth = $authMiddleware->authPermisos();
        $modelCofepris->cofepris($idCofepris);
        $authMiddleware->assertPatientAccess($modelCofepris->getIdPaciente(), $auth);
        
        $paciente = new PacienteModel($modelCofepris->getIdPaciente());

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

        $sidebarController->configureSidebar('DOCTOR', 'clinica-cofepris', $sidebar, $idCofepris,$modelCofepris->getIdPaciente());
        $sidebar->setActivarItem('Cofepris');
        $sidebarHtml = $sidebar->render();


        $data = ['title' => 'Cofepris', 

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
        $this->view('/clinica/cofepris.php', $data);

    }

    public function insertFolios(){

        header('Content-Type: application/json');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'resultado' => false,
                'mensaje' => 'Método no permitido. Usa POST.'
            ]);
            return;
        }

        $authMiddleware = new AuthMiddleware('clinica');
        $auth = $authMiddleware->authPermisos();
        $clinicId = (int)($auth['id_clinica'] ?? 0);
        $csrfToken = $_SERVER['HTTP_X_COFEPRIS_CSRF'] ?? null;

        if (!ClinicalSummarySecurity::validClinicActionCsrf(
            $clinicId,
            is_string($csrfToken) ? $csrfToken : null
        )) {
            http_response_code(403);
            echo json_encode([
                'resultado' => false,
                'mensaje' => 'Token de seguridad inválido o ausente.'
            ]);
            return;
        }

        $folio = $_POST['folio'] ?? null;
        if (!is_string($folio) || trim($folio) === '' || !ctype_digit(trim($folio))) {
            http_response_code(422);
            echo json_encode([
                'resultado' => false,
                'mensaje' => 'El folio inicial no es válido.'
            ]);
            return;
        }
        $folio = trim($folio);

        $upload = $_FILES['file'] ?? null;
        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            http_response_code(422);
            echo json_encode([
                'resultado' => false,
                'mensaje' => 'No se recibió un archivo PDF válido.'
            ]);
            return;
        }

        $fileName = basename((string)($upload['name'] ?? ''));
        $fileTmpName = (string)($upload['tmp_name'] ?? '');
        $fileSize = (int)($upload['size'] ?? 0);
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileName === '' || $fileName === '.' || $fileName === '..'
            || $fileSize < 1 || !is_uploaded_file($fileTmpName)
            || $fileExtension !== 'pdf') {
            http_response_code(422);
            echo json_encode([
                'resultado' => false,
                'mensaje' => 'El archivo debe ser un PDF válido.'
            ]);
            return;
        }

        try {
            $finfo = class_exists('finfo') ? new \finfo(FILEINFO_MIME_TYPE) : null;
            $mimeType = $finfo ? $finfo->file($fileTmpName) : false;
        } catch (\Throwable $exception) {
            $mimeType = false;
        }
        if ($mimeType !== 'application/pdf') {
            http_response_code(422);
            echo json_encode([
                'resultado' => false,
                'mensaje' => 'El archivo debe ser un PDF válido.'
            ]);
            return;
        }

        try {
            $model = new CofeprisModel();
            $resultModel = $model->guardarFolio(
                $folio,
                $fileName,
                $fileTmpName,
                $fileSize,
                $fileExtension
            );
        } catch (\Throwable $exception) {
            http_response_code(500);
            echo json_encode([
                'resultado' => false,
                'mensaje' => 'No fue posible guardar el registro.'
            ]);
            return;
        }

        echo json_encode([
            'resultado' => ($resultModel['resultado'] ?? 0) == 200,
            'mensaje' => (string)($resultModel['mensaje'] ?? 'No fue posible guardar el registro.')
        ]);

    }

    public function carpetaCofepris($idCarpeta){

        $authMiddleware = new AuthMiddleware('clinica');
        $sidebar = new Sidebar();
        $sidebarController = new SidebarController();

        $authMiddleware->authPermisos();
   
        $sidebarController->configureSidebar('DOCTOR', 'clinica-cofepris-folios', $sidebar, $idCarpeta);
        $sidebar->setActivarItem('Cofepris Folios');
        $sidebarHtml = $sidebar->render();

        $data = ['title' => 'Cofepris Folios', 
        'id_carpeta' => $idCarpeta,
        'sidebar' => $sidebarHtml];
        $this->view('/clinica/cofepris-folios.php', $data);

    }

    public function editSurtido(){

        $authMiddleware = new AuthMiddleware('clinica');
        $authMiddleware->authPermisos();

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
            return;
        }

        $model = new CofeprisModel();
        $data = json_decode(file_get_contents('php://input'), true);
        $resultModel = $model->editarSurtido($data);

            if ($resultModel['resultado'] == 200) {
                echo HttpMethod::jsonResponse(200,true,$resultModel['mensaje']);
            } else {
                echo HttpMethod::jsonResponse(401, false, $resultModel['mensaje']);
            }

    }
}
