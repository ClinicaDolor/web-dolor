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

        setcookie('CLINICAJWT', '', time() - 1, '/');
        $this->getLocationAcceso();
        die(); 
        
    }

}
