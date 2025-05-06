<?php
namespace App\Models;

use App\Config\Database;
class PacienteModel{
    private $bd;
    private $id_paciente;
    private $fecha_alta;
    private $nombre_completo;
    private $edad;
    private $fecha_nacimiento;
    private $sexo;
    private $estado_civil;
    private $lugar_origen;
    private $lugar_residencia;
    private $ocupacion;
    private $num_hijos;
    private $edad_hijos;
    private $quien_recomienda;
    private $redes_sociales;
    private $motivo_atencion;
    private $calle;
    private $num_interior;
    private $num_exterior;
    private $colonia;
    private $delegacion;
    private $cp;
    private $municipio;
    private $distancia;
    private $cuidador;
    private $cuidador_telefono;
    private $email;
    private $telefono;
    private $celular;
    private $curp;
    private $res_nombre;
    private $res_telefono;

    public function __construct($id){
        $this->bd = Database::getInstance();
        $this->id_paciente = $id;

        $query = "SELECT * FROM pc_paciente WHERE id = :id";
        $stmt = $this->bd->prepare($query);
        $stmt->bindParam(':id', $this->id_paciente);
        $stmt->execute();
        $registros = $stmt->fetch(\PDO::FETCH_ASSOC);

        $campos = [
            'fecha_alta', 'nombre_completo', 'edad', 'fecha_nacimiento', 'sexo', 
            'estado_civil', 'curp', 'lugar_origen', 'lugar_residencia', 'ocupacion', 
            'num_hijos', 'edad_hijos', 'quien_recomienda', 'redes_sociales', 
            'motivo_atencion', 'calle', 'num_interior', 'num_exterior', 'colonia', 
            'delegacion', 'cp', 'municipio', 'distancia', 'email', 'telefono', 
            'celular', 'cuidador', 'cuidador_telefono', 'res_nombre', 'res_telefono'
        ];
        
        foreach ($campos as $campo) {
            $this->$campo = $registros[$campo] ?? null;
        }

    }

    public function getFechaAlta(){
        return $this->fecha_alta;
    }

    public function getNombreCompleto(){
        return $this->nombre_completo;
    }

    public function getEdad(){
        return $this->edad;
    }

    public function getFechaNacimiento(){
        return $this->fecha_nacimiento;
    }

    public function getSexo(){
        return $this->sexo;
    }

    public function getEstadoCivil(){
        return $this->estado_civil;
    }

    public function getCurp(){
        return $this->curp;
    }
    
    public function getLugarOrigen(){
        return $this->lugar_origen;
    }

    public function getLugarResidencia(){
        return $this->lugar_residencia;
    }

    public function getOcupacion(){
        return $this->ocupacion;
    }

    public function getNumHijos(){
        return $this->num_hijos;
    }

    public function getEdadHijos(){
        return $this->edad_hijos;
    }

    public function getRecomienda(){
        return $this->quien_recomienda;
    }

    public function getRedesSociales(){
        return $this->redes_sociales;
    }
    public function getMotivoAtencion()
    {
        return $this->motivo_atencion;
    }
    public function getCalle()
    {
        return $this->calle;
    }
    public function getNumInterior()
    {
        return $this->num_interior;
    }

    public function getNumExterior()
    {
        return $this->num_exterior;
    }

    public function getColonia()
    {
        return $this->colonia;
    }

    public function getDelegacion()
    {
        return $this->delegacion;
    }

    public function getCp()
    {
        return $this->cp;
    }
    public function getMunicipio()
    {
        return $this->municipio;
    }

    public function getDistancia()
    {
        return $this->distancia;
    }

    public function getEmail(){
        return $this->email;
    }
    public function getTelefono(){
        return $this->telefono;
    }
    public function getCelular(){
        return $this->celular;
    }

    public function getCuidador()
    {
        return $this->cuidador;
    }

    public function getCuidadorTelefono()
    {
        return $this->cuidador_telefono;
    }
   
    public function getResNombre()
    {
        return $this->res_nombre;
    }

    public function getResTelefono()
    {
        return $this->res_telefono;
    }
}