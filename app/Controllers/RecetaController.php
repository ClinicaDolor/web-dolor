<?php
namespace App\Controllers;
use App\Middleware\AuthMiddleware;
use App\Helpers\Sidebar;
use App\Models\RecetaModel;
use App\Models\PacienteModel;
use App\Helpers\CalculadoraEdad;

class RecetaController extends BaseController{

    public function receta($idReceta){

        $authMiddleware = new AuthMiddleware('clinica');
        $sidebar = new Sidebar();
        $sidebarController = new SidebarController();
        $modelNota = new RecetaModel();

        $authMiddleware->authPermisos();
        $modelNota->receta($idReceta);
        
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

        $sidebarController->configureSidebar('DOCTOR', 'clinica-receta', $sidebar, $idReceta,$modelNota->getIdPaciente());
        $sidebar->setActivarItem('Receta');
        $sidebarHtml = $sidebar->render();


        $data = ['title' => 'Receta', 

        'id_receta' => $idReceta,
        'fecha_receta' => $modelNota->getFecha(),
        'hora_receta' => $modelNota->getHora(),
        'diagnostico_receta' => $modelNota->getDiagnostico(),
        'medicamento_receta' => $modelNota->getMedicamento(),

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
        $this->view('/clinica/receta.php', $data);

    }

}