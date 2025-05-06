<?php
namespace App\Models;
use App\Config\Database;
use App\Core\PasswordHelper;
use Firebase\JWT\JWT;

class LoginModel{
    private $bd;
    private $valida;

    public function __construct(){
        $this->bd = Database::getInstance();
        $this->valida = new PasswordHelper();
    }

    public function validaDoctor($usuario, $password){

        $stmt = $this->bd->prepare("SELECT
        clinicas.id AS idClinica,
        clinicas.nombre,
        doctores.id AS idDoctor,
        doctores.nombre,
        doctores.especialidad,
        accesos_doctores.contraseña,
        accesos_doctores.activo
        FROM clinicas
        INNER JOIN doctores 
        ON doctores.clinica_id = clinicas.id
        INNER JOIN accesos_doctores
        ON accesos_doctores.doctor_id = doctores.id
        WHERE usuario = :usuario AND accesos_doctores.activo = 1");
        $stmt->execute(['usuario' => $usuario]);
        $user = $stmt->fetch();

        if ($user && $this->valida->verifyPassword($password, $user['contraseña'])) {
            $resultado = $this->generateToken($user['idClinica'],$user['idDoctor'],$user['nombre'],'clinica','Doctor');


            return $resultado;
        }

        return array('resultado' => 401,'token' => '');
    }

    public function validaPaciente($pin){

        $stmt = $this->bd->prepare("SELECT 
        pc_paciente.id AS idPaciente, 
        pc_paciente.id_clinica AS idClinica, 
        pc_paciente.nombre_completo, 
        pc_paciente_acceso.pin, 
        pc_paciente_acceso.fecha_creacion, 
        pc_paciente_acceso.fecha_expiracion 
        FROM pc_paciente 
        INNER JOIN pc_paciente_acceso 
        ON pc_paciente_acceso.paciente_id = pc_paciente.id
        WHERE pc_paciente_acceso.estatus = 0 AND fecha_expiracion > NOW()");
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user && $this->valida->verifyPassword($pin, $user['pin'])) {
            $resultado = $this->generateToken($user['idClinica'],$user['idPaciente'],$user['nombre_completo'],'historia-clinica','Paciente');


            return $resultado;
        }

        return array('resultado' => 401,'token' => '');

    }

    public function generateToken($idClinica,$idUsuario, $nombre, $vista, $rol){

        $issuedAt = time();
        $expirationTime = $issuedAt + 604800;

        $cookie_time = time() + (7 * 24 * 60 * 60);
        $options = [
            'expires' => $cookie_time,
            'path' => '/'
        ];

        $payload = [
            'iat' => $issuedAt,
            'iss' =>$_ENV['APP_NAME'],
            'exp' => $expirationTime, 
            'id_clinica' => $idClinica,
            'id_usuario' => $idUsuario,
            'nombre' => $nombre,
            'vista' => $vista,
            'rol' => $rol
        ];

        $jwt = JWT::encode($payload, $_ENV['APP_KEY'], 'HS256');

        setcookie("CLINICAJWT", $jwt, $options);

        return array('resultado' => 200,
            'token' => $jwt);

    }
}