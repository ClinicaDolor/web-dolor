<?php
namespace App\Controllers;
use App\Middleware\AuthMiddleware;
use App\Helpers\Sidebar;
use App\Models\CofeprisModel;
use App\Models\PacienteModel;
use App\Helpers\CalculadoraEdad;
use App\Core\HttpMethod;

class CofeprisController extends BaseController{

    public function cofepris($idCofepris){

        $authMiddleware = new AuthMiddleware('clinica');
        $sidebar = new Sidebar();
        $sidebarController = new SidebarController();
        $modelCofepris = new CofeprisModel();

        $authMiddleware->authPermisos();
        $modelCofepris->cofepris($idCofepris);
        
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
        $model = new CofeprisModel();
        $resultModel = $model->guardarFolio(
            $_POST['folio'] ?? '',
            $fileName,
            $fileTmpName,
            $fileSize,
            $fileExtension
        );
    
        // Responder en JSON
        echo json_encode([
            'resultado' => $resultModel['resultado'] == 200,
            'mensaje' => $resultModel['mensaje']
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