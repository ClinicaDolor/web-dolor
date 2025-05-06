<?php

namespace App\Controllers;
use App\Middleware\AuthMiddleware;
use App\Helpers\Sidebar;
use App\Models\LaboratorioModel;
use App\Models\PacienteModel;
use App\Helpers\CalculadoraEdad;

class LaboratorioController extends BaseController{

    public function laboratorio($idLaboratorio){

        $authMiddleware = new AuthMiddleware('clinica');
        $sidebar = new Sidebar();
        $sidebarController = new SidebarController();
        $model = new LaboratorioModel();

        $authMiddleware->authPermisos();
        $model->laboratorio($idLaboratorio);
        
        $paciente = new PacienteModel($model->getIdPaciente());

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

        $sidebarController->configureSidebar('DOCTOR', 'clinica-laboratorio', $sidebar, $idLaboratorio, $model->getIdPaciente());
        $sidebar->setActivarItem('Laboratorio');
        $sidebarHtml = $sidebar->render();


        $data = ['title' => 'Laboratorio', 

        'id_laboratorio' => $idLaboratorio,
        'fecha_laboratorio' => $model->getFecha(),
        'hora_laboratorio' => $model->getFecha(),
        'ruta' => $model->getRuta(),
        'nombre' => $model->getNombre(),
        'descripcion' => $model->getDescripcion(),

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
        $this->view('/clinica/laboratorio.php', $data);

    }
    

}