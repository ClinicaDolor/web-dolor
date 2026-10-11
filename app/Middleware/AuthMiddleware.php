<?php
namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Config\Database;

class AuthMiddleware{
    private $vista;
    private $bd;
    public function __construct($vistas){
        $this->vista = $vistas;
        $this->bd = Database::getInstance();
    }

    public function authPermisos(){
       
        if(!$this->validaCookie()){
            $this->cerrarSesion();
        }

        $decoded = JWT::decode($_COOKIE['CLINICAJWT'], new Key($_ENV['APP_KEY'], 'HS256'));
        $this->validaExpCookie($decoded->exp);

            if($decoded->vista == $this->vista){

                $array = array(   
                    'id_clinica' => $decoded->id_clinica,               
                    'id_usuario' => $decoded->id_usuario, 
                    'nombre' => $decoded->nombre,
                    'vista' => $this->vista, 
                    'rol' => $decoded->rol, 
                    'token' => $_COOKIE['CLINICAJWT']
                );
                
                return $array;

            }else{
                $this->cerrarSesion();
            }    

    }

    public function authAny(){
        if (!$this->validaCookie()) {
            header('Location: ' . SERVIDOR . 'clinica/acceso');
            exit;
        }

        $decoded = JWT::decode($_COOKIE['CLINICAJWT'], new Key($_ENV['APP_KEY'], 'HS256'));
        $this->validaExpCookie($decoded->exp);

        return [
            'id_clinica' => $decoded->id_clinica,
            'id_usuario' => $decoded->id_usuario,
            'nombre' => $decoded->nombre,
            'vista' => $decoded->vista,
            'rol' => $decoded->rol,
            'token' => $_COOKIE['CLINICAJWT']
        ];
    }

    /**
     * Verifica que el recurso de paciente pertenezca al ámbito de la sesión.
     * Los controladores siguen siendo responsables de decidir qué acciones
     * permite cada rol; este método evita que un ID de URL sustituya esa
     * decisión de pertenencia.
     */
    public function assertPatientAccess($idPaciente, $auth = null){
        $auth = $auth ?: $this->authPermisos();
        $idPaciente = filter_var($idPaciente, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($idPaciente === false) {
            http_response_code(404);
            exit('Paciente no válido.');
        }

        $stmt = $this->bd->prepare('SELECT id, id_clinica FROM pc_paciente WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $idPaciente]);
        $paciente = $stmt->fetch();

        if (!$paciente) {
            http_response_code(404);
            exit('Paciente no encontrado.');
        }

        $authorized = ($auth['vista'] === 'historia-clinica' && (int)$auth['id_usuario'] === (int)$paciente['id'])
            || ($auth['vista'] === 'clinica' && (int)$auth['id_clinica'] === (int)$paciente['id_clinica']);

        if (!$authorized) {
            http_response_code(403);
            exit('No autorizado.');
        }

        return (int)$paciente['id'];
    }

    public function getLocationAcceso(){
        header("Location: ".SERVIDOR.$this->vista."/acceso");  
    }

    public function getLocationHome(){
        header("Location: ".SERVIDOR.$this->vista);  
    }

    public function authLogin(){
    if($this->validaCookie()){
        $this->getLocationHome();
    } 
    }

    public function validaCookie(){

        if (isset($_COOKIE['CLINICAJWT'])) {
            return true;
        }else{
            return false;
        }

    }

    public function validaExpCookie($exp){

        $date = time();
        $fechaSesion = $exp;

        if ($fechaSesion < $date) {
        $this->cerrarSesion();   
        } 

    }

    public function cerrarSesion(){

        setcookie('CLINICAJWT', '', [
            'expires' => time() - 1,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        $this->getLocationAcceso();
        die(); 
        
    }

}
