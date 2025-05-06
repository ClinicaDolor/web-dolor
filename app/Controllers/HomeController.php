<?php
namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\ClinicaModel;
use App\Helpers\Sidebar;
use App\Controllers\SidebarController;

class HomeController extends BaseController{

    public function __construct(){

    }
   
    public function indexClinica(){

        $authMiddleware = new AuthMiddleware('clinica');
        $model = new ClinicaModel();
        $sidebar = new Sidebar();
        $sidebarController = new SidebarController();

        $result = $authMiddleware->authPermisos();
        $countPacientes = $model->countPacientes();
        
        $sidebarController->configureSidebar('DOCTOR', 'clinica', $sidebar);
        $sidebar->setActivarItem('Inicio');
        $sidebarHtml = $sidebar->render();

        $data = ['title' => 'Clinica', 'datos' => $result,  'total_pacientes' => $countPacientes, 'sidebar' => $sidebarHtml];
        $this->view('/clinica/index.php', $data);
       
    }

    public function indexPaciente(){

        $authMiddleware = new AuthMiddleware('historia-clinica');
        $sidebar = new Sidebar();
        $sidebarController = new SidebarController();
        $result = $authMiddleware->authPermisos();

        $sidebarController->configureSidebar('PACIENTE', 'historia-clinica', $sidebar);
        $sidebar->setActivarItem('Inicio');
        $sidebarHtml = $sidebar->render();

        $data = ['title' => 'Clinica', 'datos' => $result, 'sidebar' => $sidebarHtml];
        $this->view('/paciente/index.php', $data);
    }

    public function indexCofepris(){

        $authMiddleware = new AuthMiddleware('clinica');
        $model = new ClinicaModel();
        $sidebar = new Sidebar();
        $sidebarController = new SidebarController();

        $result = $authMiddleware->authPermisos();
        $countPacientes = $model->countPacientes();
        
        $sidebarController->configureSidebar('DOCTOR', 'cofepris', $sidebar);
        $sidebar->setActivarItem('Cofepris');
        $sidebarHtml = $sidebar->render();

        $data = ['title' => 'Configuración Cofepris', 'datos' => $result,  'total_pacientes' => $countPacientes, 'sidebar' => $sidebarHtml];
        $this->view('/clinica/configuracion-cofepris.php', $data);

    }

}