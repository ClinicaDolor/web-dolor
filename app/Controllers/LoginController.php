<?php
namespace App\Controllers;
use App\Models\LoginModel;
use App\Core\HttpMethod;
use App\Middleware\AuthMiddleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
class LoginController extends BaseController{

    //------------------------------------------------------//
    // Controlador para los accesos de clinica y pacientes //

    public function accesoClinica(){

        $authMiddleware = new AuthMiddleware('clinica');
        $authMiddleware->authLogin();

        $data = ['title' => 'Paciente'];
        $this->view('/login/login-clinica.php', $data);
    }

    public function loginClinica(){

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['usuario']) || !isset($data['password'])) {
            echo HttpMethod::jsonResponse(400,false,"Falta agregar usuario o contraseña.");
            return;
        }

        $usuario = $data['usuario'];
        $password = $data['password'];

        $model = new LoginModel();
        $result = $model->validaDoctor($usuario, $password);

        if ($result['resultado'] == 200) {
            echo HttpMethod::jsonResponse(200,true,"Acceso Autorizado", $result['token']);
        } else {
            echo HttpMethod::jsonResponse(401, false, 'Usuario o Contraseña incorrectos.','');
        }

    }

    //-----------------------------------------------------------------------------------------//
    //-----------------------------------------------------------------------------------------//

    public function accesoPaciente(){

        $authMiddleware = new AuthMiddleware('historia-clinica');
        $authMiddleware->authLogin();

        $data = ['title' => 'Paciente'];
        $this->view('/login/login-paciente.php', $data);
    }

    public function loginPaciente(){

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo HttpMethod::jsonResponse(405, false, "Método no permitido. Usa POST.");
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['pin'])) {
            echo HttpMethod::jsonResponse(400,false,"Falta agregar el PIN.");
            return;
        }

        $pin = $data['pin'];

        $model = new LoginModel();
        $result = $model->validaPaciente($pin);

        if ($result['resultado'] == 200) {
            echo HttpMethod::jsonResponse(200,true,"Acceso Autorizado", $result['token']);
        } else {
            echo HttpMethod::jsonResponse(401, false, 'PIN incorrectos.','');
        }

    }

    //-------------------------------------------------------------------------------------------------
    //-------------------------------------------------------------------------------------------------

    public function cerrarSesion(){
        
        $decoded = JWT::decode($_COOKIE['CLINICAJWT'], new Key($_ENV['APP_KEY'], 'HS256'));       
        $authMiddleware = new AuthMiddleware($decoded->vista);
        $authMiddleware->cerrarSesion();       
    }

}
