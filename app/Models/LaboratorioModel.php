<?php

namespace App\Models;
use App\Config\Database;

class LaboratorioModel{
    private $bd;
    private $fecha;
    private $hora;
    private $id_paciente;
    private $nombre;
    private $ruta;
    private $tipo;
    private $tamano;
    private $descripcion;
    
    public function __construct(){
    $this->bd = Database::getInstance();
    }

    public function laboratorio($idLaboratorio){

        $query = "SELECT * FROM laboratorio WHERE id = :id";
        $stmt = $this->bd->prepare($query);
        $stmt->bindParam(':id', $idLaboratorio);
        $stmt->execute();
        $registros = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->fecha = (new \DateTime($registros['fecha_hora']))->format('d/m/Y');
        $this->hora = (new \DateTime($registros['fecha_hora']))->format('h:i a');

        $this->id_paciente = $registros['id_paciente'];
        $this->nombre = $registros['nombre'];
        $this->ruta = $registros['ruta'];
        $this->tipo = $registros['tipo'];
        $this->tamano = $registros['tamano'];
        $this->descripcion = $registros['descripcion'];

    }

    //-------------------------------------------------------------------------------------------------------
    //----------------------------- INICIO GETTERS ----------------------------------------------------------

    public function getFecha()
    {
        return $this->fecha;
    }

     public function getHora()
    {
        return $this->hora;
    }

    public function getIdPaciente()
    {
        return $this->id_paciente;
    }

    public function getNombre()
    {
        return $this->nombre;
    }

    public function getRuta()
    {
        return $this->ruta;
    }

    public function getTipo()
    {
        return $this->tipo;
    }

    public function getTamano()
    {
        return $this->tamano;
    }

    public function getDescripcion()
    {
        return $this->descripcion;
    }

    //------------------------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------------------------

    public function ultimoLaboratorio($idPaciente){

        $result = '';
 
        $sql = "SELECT id FROM laboratorio WHERE id_paciente = :id ORDER BY id DESC LIMIT 1";
        $stmt = $this->bd->prepare($sql);
        $stmt->execute([':id' => $idPaciente]);
 
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        $idLaboratorio = isset($data['id']) && !empty($data['id']) ? $data['id'] : 0;
        $result = $this->getLaboratorio($idLaboratorio);        
       
        return $result;

    }

    public function insertArchivo($idPaciente,$contenidoLaboratorio,$fileName, $fileData, $fileSize, $fileExtension, $referencia, $titulo){
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/public/storage/laboratorio/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filePath = $uploadDir . basename($fileName);
        if (move_uploaded_file($fileData, $filePath)) {

            $query = "INSERT INTO laboratorio (id_paciente, nombre, ruta, tipo, tamano, titulo, descripcion, codigo_referencia) 
                  VALUES (:id_paciente, :nombre, :ruta, :tipo, :tamano, :titulo, :descripcion, :codigo_referencia)";
            $stmt = $this->bd->prepare($query);     

            $datos = [
                ':id_paciente' => $idPaciente, 
                ':nombre' => basename($fileName), 
                ':ruta' => 'laboratorio', 
                ':tipo' => $fileExtension, 
                ':tamano' => $fileSize,
                ':titulo' => $titulo, 
                ':descripcion' => $contenidoLaboratorio,
                ':codigo_referencia' => $referencia
                ];

                if ($stmt->execute($datos)) {

                    return array('resultado' => 200,'mensaje' => $this->bd->lastInsertId());
        
                } else {
                    return array('resultado' => 401,'mensaje' => '¡Error al guardar el archivo!');
                }

        }else{
            return array('resultado' => 200,'mensaje' => '¡Error al guardar el archivo!');
        }
        
    }

    public function getLaboratorio($idLaboratorio){

        $result = '';
 
        $sql = "SELECT id, fecha_hora, nombre, ruta, tipo, titulo, descripcion FROM laboratorio WHERE id = :id ORDER BY id DESC LIMIT 1";
        $stmt = $this->bd->prepare($sql);
        $stmt->execute([':id' => $idLaboratorio]);
 
        if ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $fecha = (new \DateTime($data['fecha_hora']))->format('d/m/Y');
            $hora = (new \DateTime($data['fecha_hora']))->format('h:i a');
            $result .= '
            <div class="float-end">
            <a download="'.$data['nombre'].'" href="/descargar/'.$data['ruta'].'/'.$data['nombre'].'" class="btn icon btn-primary"><i data-feather="download"></i></a></div>
            <div>
            <label class="text-primary"><small>Fecha: </small></label> <label class="fs-5">' . $fecha . '</label>, <label class="text-primary"><small>Hora: </small></label> <label class="fs-5">'. $hora .'</label>
            </div>

            <label class="text-primary mt-3"><small>Titulo: </small></label>
            <div class="fs-5">'.$data['titulo'].'</div>

            <label class="text-primary mt-3"><small>Descripción: </small></label>
            <div class="fs-5">'.$data['descripcion'].'</div>';
            
        } else {
            $result = '<div class="text-center p-4 text-light">No se encontró información.</div>';
        }
       
        return $result;

    }

    public function mostrarTablaLaboratorio($idPaciente,$referencia){

        $result = '';

        $qry_referencia = ($referencia == 0)? ' id_paciente = "'.$idPaciente.'"' : ' id_paciente = "'.$idPaciente.'" AND codigo_referencia = "'.$referencia.'" ';
        $query = $this->bd->query("SELECT * FROM laboratorio WHERE $qry_referencia ORDER BY fecha_hora DESC");
        $registros = $query->fetchAll(\PDO::FETCH_ASSOC);

        $result .= '<table class="table table-sm table-striped table-hover pb-0 mb-0" id="tableLaboratorio">
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th>Fecha y Hora</th>
                <th>Titulo</th>
            </tr>
        </thead>
        <tbody>';
           foreach ($registros as $data_laboratorio):

            $fecha_hora = (new \DateTime(datetime: $data_laboratorio['fecha_hora']))->format('d/m/Y h:i a');

            $result .= '<tr onclick="DetalleLaboratorio('.$data_laboratorio['id'].')">
                <td class="text-center">'.$data_laboratorio['id'].'</td>
                <td>'.$fecha_hora.'</td>
                <td>'.$data_laboratorio['titulo'].'</td>
            </tr>';
            endforeach;  
    $result .= '</tbody>
    </table>';
    
    return $result;
    }
}