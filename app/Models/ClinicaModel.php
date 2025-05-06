<?php
namespace App\Models;

use App\Config\Database;
use App\Core\PasswordHelper;
class ClinicaModel{

    private $bd;
    public function __construct(){
        $this->bd = Database::getInstance();
    }

    public function countPacientes(){

    $stmt = $this->bd->prepare("SELECT COUNT(*) as total FROM pc_paciente");
    $stmt->execute();
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    return $row['total'];

    }

    public function insertPaciente($data,$cookie){
        
        $sql = "INSERT INTO pc_paciente (
            id_clinica,
            nombre_completo,
            edad,
            sexo,
            fecha_nacimiento,
            estado_civil,
            curp,
            lugar_origen,    
            lugar_residencia,    
            ocupacion,   
            num_hijos,   
            edad_hijos,  
            medico_tratante, 
            medico_refiere,
            diagnostico_inicial,
            calle,   
            num_interior,    
            num_exterior,  
            colonia, 
            delegacion, 
            cp,  
            municipio,   
            estado,  
            distancia,   
            email,   
            telefono,    
            celular, 
            res_nombre,  
            res_telefono,    
            cuidador,    
            cuidador_telefono,   
            aseguradora, 
            informe_aseguradora, 
            hoja_membretada, 
            formato_aseguradora, 
            honorarios, 
            observaciones,
            motivo_atencion,
            quien_recomienda, 
            redes_sociales,    
            status
        ) VALUES (
            :id_clinica,
            :nombre_completo,
            :edad,
            :sexo,
            :fecha_nacimiento,
            :estado_civil,
            :curp,
            :lugar_origen,    
            :lugar_residencia,    
            :ocupacion,   
            :num_hijos,   
            :edad_hijos,  
            :medico_tratante, 
            :medico_refiere,
            :diagnostico_inicial,
            :calle,   
            :num_interior,    
            :num_exterior,  
            :colonia, 
            :delegacion, 
            :cp,  
            :municipio,   
            :estado,  
            :distancia,   
            :email,   
            :telefono,    
            :celular, 
            :res_nombre,  
            :res_telefono,    
            :cuidador,    
            :cuidador_telefono,   
            :aseguradora, 
            :informe_aseguradora, 
            :hoja_membretada, 
            :formato_aseguradora, 
            :honorarios, 
            :observaciones,
            :motivo_atencion,
            :quien_recomienda,   
            :redes_sociales,   
            :status
        )";

        $stmt = $this->bd->prepare($sql);                    
        
        $datos = [
        ':id_clinica' => $cookie['id_clinica'],
        ':nombre_completo' => $data['NombreCompleto'],
        ':edad' => $data['Edad'],
        ':sexo' => $data['Sexo'],
        ':fecha_nacimiento' => $data['FeNacimiento'],
        ':estado_civil' => $data['EstadoCivil'],
        ':curp' => $data['CURP'],
        ':lugar_origen' => $data['LuOrigen'],    
        ':lugar_residencia' => $data['LuResidencia'],    
        ':ocupacion' => $data['Ocupacion'],   
        ':num_hijos' => $data['NumHijos'],   
        ':edad_hijos' => $data['EdadHijos'],  
        ':medico_tratante' => '', 
        ':medico_refiere' => '',
        ':diagnostico_inicial' => '',
        ':calle' => $data['DACalle'],   
        ':num_interior' => $data['DANI'],    
        ':num_exterior' => $data['DANE'],  
        ':colonia' => $data['DAColonia'], 
        ':delegacion' => $data['DADelegacion'], 
        ':cp' => $data['DACP'],  
        ':municipio' => $data['DAMunicipio'],   
        ':estado' => '',  
        ':distancia' => $data['Distancia'],   
        ':email' => $data['Email'],   
        ':telefono' => $data['Telefono'],    
        ':celular' => $data['Celular'], 
        ':res_nombre' => $data['ResNombre'],  
        ':res_telefono' => $data['ResTelefono'],    
        ':cuidador' => $data['CuiNombre'],    
        ':cuidador_telefono' => $data['CuiTelefono'],   
        ':aseguradora' => '', 
        ':informe_aseguradora' => '', 
        ':hoja_membretada' => '', 
        ':formato_aseguradora' => '', 
        ':honorarios' => '', 
        ':observaciones' => '',
        ':motivo_atencion' => $data['motivoAtencionClinica'],
        ':quien_recomienda' => $data['personaRecomienda'],
        ':redes_sociales' => $data['RedesSociales'],
        ':status' => 1
        ];
    
        if ($stmt->execute($datos)) {
            return array('resultado' => 200,'mensaje' => $this->bd->lastInsertId());

        } else {
            return array('resultado' => 401,'mensaje' => '¡Error al agregar nuevo paciente a la lista!');
        }
        

    }

    public function editPaciente($data){

    $sql = "UPDATE pc_paciente SET 
    nombre_completo = :nombre_completo,
    edad = :edad,
    sexo = :sexo,
    fecha_nacimiento = :fecha_nacimiento,
    estado_civil = :estado_civil,
    curp = :curp,
    lugar_origen = :lugar_origen,    
    lugar_residencia = :lugar_residencia,    
    ocupacion = :ocupacion,   
    num_hijos = :num_hijos,   
    edad_hijos = :edad_hijos,   
    calle = :calle,   
    num_interior = :num_interior,    
    num_exterior = :num_exterior,  
    colonia = :colonia, 
    delegacion = :delegacion, 
    cp = :cp,  
    municipio = :municipio,  
    distancia = :distancia,   
    email = :email,   
    telefono = :telefono,    
    celular = :celular, 
    res_nombre = :res_nombre,  
    res_telefono = :res_telefono,    
    cuidador = :cuidador,    
    cuidador_telefono = :cuidador_telefono,  
    motivo_atencion = :motivo_atencion,
    quien_recomienda = :quien_recomienda, 
    redes_sociales = :redes_sociales
    WHERE id = :id";
    $stmt = $this->bd->prepare($sql);

    $datos = [
        'nombre_completo' => $data['NombreCompleto'],
        'edad' => $data['Edad'],
        'sexo' => $data['Sexo'],
        'fecha_nacimiento' => $data['FeNacimiento'],
        'estado_civil' => $data['EstadoCivil'],
        'curp' => $data['CURP'],
        'lugar_origen' => $data['LuOrigen'],
        'lugar_residencia' => $data['LuResidencia'],
        'ocupacion' => $data['Ocupacion'],
        'num_hijos' => $data['NumHijos'],
        'edad_hijos' => $data['EdadHijos'],
        'calle' => $data['DACalle'],
        'num_interior' => $data['DANI'],
        'num_exterior' => $data['DANE'],
        'colonia' => $data['DAColonia'],
        'delegacion' => $data['DADelegacion'],
        'cp' => $data['DACP'],
        'municipio' => $data['DAMunicipio'],
        'distancia' => $data['Distancia'],
        'email' => $data['Email'],
        'telefono' => $data['Telefono'],
        'celular' => $data['Celular'],
        'res_nombre' => $data['ResNombre'],
        'res_telefono' => $data['ResTelefono'],
        'cuidador' => $data['CuiNombre'],
        'cuidador_telefono' => $data['CuiTelefono'],
        'motivo_atencion' => $data['motivoAtencionClinica'],
        'quien_recomienda' => $data['personaRecomienda'],
        'redes_sociales' => $data['RedesSociales'],
        'id' => $data['idPaciente']
    ];

    if ($stmt->execute($datos)) {
        return array('resultado' => 200,'mensaje' => $data['idPaciente']);

    } else {
        return array('resultado' => 401,'mensaje' => '¡Error al editar la información del paciente!');
    }


    }

    public function insertPacientePin($data){

        date_default_timezone_set('America/Mexico_City');
        $fecha = date('Y-m-d');
        $hora = date('H:i:s');

        $nuevaFecha = date('Y-m-d', strtotime($fecha . ' +30 days'));
    
        $sql = "INSERT INTO pc_paciente_acceso (
            paciente_id,
            pin,
            fecha_expiracion,
            estatus
        ) VALUES (
            :paciente_id,
            :pin,
            :fecha_expiracion,
            :estatus
        )";

        $stmt = $this->bd->prepare($sql);                    
        
        $datos = [
        ':paciente_id' => $data['idPaciente'],
        ':pin' => PasswordHelper::hashPassword('cdp'.$data['idPaciente']),
        ':fecha_expiracion' => $nuevaFecha.' '.$hora,
        ':estatus' => 0
        ];
    
        if ($stmt->execute($datos)) {
            return array('resultado' => 200,'mensaje' => '¡Se creo el PIN Correctamente!');

        } else {
            return array('resultado' => 401,'mensaje' => '¡Error al agregar nuevo paciente a la lista!');
        }

    }

    public function insertPacienteReceta($data){

        $sql = "INSERT INTO receta_medica (
            id_paciente,
            diagnostico,
            medicamento,
            codigo_referencia
        ) VALUES (
            :id_paciente,
            :diagnostico,
            :medicamento,
            :codigo_referencia
        )";

        $stmt = $this->bd->prepare($sql);         

        $datos = [
            ':id_paciente' => $data['idPaciente'],
            ':diagnostico' => $data['diagnostico'],
            ':medicamento' => $data['medicamento'],
            ':codigo_referencia' => $data['referencia']
            ];
        
            if ($stmt->execute($datos)) {
                return array('resultado' => 200,'mensaje' => $this->bd->lastInsertId());
    
            } else {
                return array('resultado' => 401,'mensaje' => '¡Error al agregar nueva receta a la lista!');
            }

    }

}