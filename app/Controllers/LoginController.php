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
        $data = is_array($data) ? $data : [];
        $usuario = isset($data['usuario']) && is_scalar($data['usuario']) ? trim((string)$data['usuario']) : '';
        $password = isset($data['password']) && is_scalar($data['password']) ? (string)$data['password'] : '';

        if ($usuario === '' && trim($password) === '') {
            echo HttpMethod::jsonResponse(400, false, 'Ingresa tu usuario y contraseña.');
            return;
        }

        if ($usuario === '') {
            echo HttpMethod::jsonResponse(400, false, 'Ingresa tu usuario.');
            return;
        }

        if (trim($password) === '') {
            echo HttpMethod::jsonResponse(400, false, 'Ingresa tu contraseña.');
            return;
        }

        try {
            $model = new LoginModel();
            $result = $model->validaDoctor($usuario, $password);
        } catch (\Throwable $exception) {
            error_log('Fallo controlado de autenticación médica.');
            echo HttpMethod::jsonResponse(401, false, 'Usuario o contraseña incorrectos.', '');
            return;
        }

        if ($result['resultado'] == 200) {
            echo HttpMethod::jsonResponse(200, true, 'Acceso Autorizado', $result['token']);
        } else {
            echo HttpMethod::jsonResponse(401, false, 'Usuario o contraseña incorrectos.', '');
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
        $data = is_array($data) ? $data : [];
        $pin = isset($data['pin']) && is_scalar($data['pin']) ? trim((string)$data['pin']) : '';

        if ($pin === '') {
            echo HttpMethod::jsonResponse(400, false, 'Ingresa tu PIN de acceso.');
            return;
        }

        try {
            $model = new LoginModel();
            $result = $model->validaPaciente($pin);
        } catch (\Throwable $exception) {
            error_log('Fallo controlado de autenticación de paciente.');
            echo HttpMethod::jsonResponse(401, false, 'PIN incorrecto o no vigente.', '');
            return;
        }

        if ($result['resultado'] == 200) {
            echo HttpMethod::jsonResponse(200, true, 'Acceso Autorizado', $result['token']);
        } else {
            echo HttpMethod::jsonResponse(401, false, 'PIN incorrecto o no vigente.', '');
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
