<?php
namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Helpers\Sidebar;
use App\Models\NotaSubsecuenteModel;
use App\Models\PacienteModel;
use App\Helpers\CalculadoraEdad;

class NotaSubsecuenteController extends BaseController{

    public function notaSubsecuente($idNota){

        $authMiddleware = new AuthMiddleware('clinica');
        $sidebar = new Sidebar();
        $modelNota = new NotaSubsecuenteModel();
        $sidebarController = new SidebarController();

        $auth = $authMiddleware->authPermisos();
        $modelNota->NotaSubsecuente($idNota);
        $idPaciente = $authMiddleware->assertPatientAccess($modelNota->getIdPaciente(), $auth);

        $contextReference = isset($_GET['context_ref']) && is_string($_GET['context_ref'])
            ? trim($_GET['context_ref'])
            : '';
        $contextPatient = filter_var(
            $_GET['context_patient'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $backReference = '';

        // The list creates the context from uniqid('', true). It is only a
        // return-context value; the note and patient authorization above
        // remain the source of access control.
        if (
            $contextPatient === $idPaciente
            && preg_match('/\A[a-f0-9]{13}\.[a-f0-9]{8}\z/i', $contextReference) === 1
        ) {
            $backReference = $contextReference;
        }

        // Direct detail access has no list context. Resolve the note's own
        // reference from the already authorized patient's history instead of
        // trusting a client-provided destination.
        if ($backReference === '') {
            foreach ($modelNota->getHistoryByPatient($idPaciente) as $historyRecord) {
                if ((int)($historyRecord['id'] ?? 0) === (int)$idNota) {
                    $backReference = (string)($historyRecord['codigo_referencia'] ?? '');
                    break;
                }
            }
        }

        $backUrl = SERVIDOR . 'clinica/paciente/' . rawurlencode((string)$idPaciente);
        $backLabel = 'Volver al expediente';
        if ($backReference !== '') {
            $backUrl = SERVIDOR . 'clinica/nota-subsecuente/paciente/'
                . rawurlencode((string)$idPaciente)
                . '/referencia/' . rawurlencode($backReference);
            $backLabel = 'Volver a notas subsecuentes';
        }
        
        $paciente = new PacienteModel($modelNota->getIdPaciente());

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

        $sidebarController->configureSidebar('DOCTOR', 'nota-subsecuente', $sidebar, $idNota, $modelNota->getIdPaciente());
        $sidebar->setActivarItem('Nota Subsecuente');
        $sidebarHtml = $sidebar->render();


        $data = ['title' => 'Nota Subsecuente', 
        'idNota' => $idNota,
        'idPaciente' => $idPaciente,
        'back_url' => $backUrl,
        'back_label' => $backLabel,

        'fecha_hora_nota' => $modelNota->getFechaHora(),
        'contenido_nota' => $modelNota->getContenido(),

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
        $this->view('/clinica/nota-subsecuente.php', $data);

    }
}
