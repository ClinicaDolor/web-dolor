<?php

namespace App\Models;

use App\Config\Database;

class RecetaModel{

    private $bd;
    private $fecha;
    private $hora;
    private $id_paciente;
    private $diagnostico;
    private $medicamento;
    public function __construct(){

        $this->bd = Database::getInstance();

    }

    public function idRecetaReferencia($idPaciente,$referencia){

        $query = "SELECT id FROM receta_medica WHERE id_paciente = '".$idPaciente."' AND codigo_referencia = '".$referencia."' ";
        $stmt = $this->bd->prepare($query);
        $stmt->execute();
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $data['id'] ?? 0;

    }

    public function receta($idReceta){

        $query = "SELECT * FROM receta_medica WHERE id = :id";
        $stmt = $this->bd->prepare($query);
        $stmt->bindParam(':id', $idReceta);
        $stmt->execute();
        $registros = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!empty($registros['fecha_hora'])) {
            $fechaHora = new \DateTime($registros['fecha_hora']);
            $this->fecha = $fechaHora->format('d/m/Y');
            $this->hora = $fechaHora->format('h:i a');
        } else {
            $this->fecha = null;
            $this->hora = null;
        }

        $this->id_paciente = $registros['id_paciente'] ?? null;
        $this->diagnostico = $registros['diagnostico'] ?? null;
        $this->medicamento = $registros['medicamento'] ?? null;

    
    }

    //----------------------------------------------------------
    //----------------------------------------------------------
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

    public function getDiagnostico()
    {
        return $this->diagnostico;
    }

    public function getMedicamento()
    {
        return $this->medicamento;
    }
    //----------------------------------------------------------
    //----------------------------------------------------------

    public function mostrarTablaRecetas($idPaciente){
        $result = '';
        $stmt = $this->bd->query("SELECT * FROM receta_medica WHERE id_paciente = '".$idPaciente."' ORDER BY fecha_hora DESC");
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result .= '<table class="table table-striped table-hover table-sm pb-0 mb-0" id="tableRecetas">
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th>Fecha y Hora</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($registros as $registro):

            $fecha_hora = (new \DateTime(datetime: $registro['fecha_hora']))->format('d/m/Y h:i a');

            $result .= '<tr onclick="DetalleReceta('.$registro['id'].')">
                        <td class="text-center">'.$registro['id'].'</td>
                        <td>'.$fecha_hora.'</td>
                        </tr>';

        endforeach;

        $result .= '</tbody>
        </table>';
    
    return $result;
    }

    public function ultimaReceta($idPaciente){

        $result = '';
 
        $sql = "SELECT id FROM receta_medica WHERE id_paciente = :id ORDER BY id DESC LIMIT 1";
        $stmt = $this->bd->prepare($sql);
        $stmt->execute([':id' => $idPaciente]);
 
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        $idReceta = isset($data['id']) && !empty($data['id']) ? $data['id'] : 0;
        $result = $this->getReceta($idReceta);
          
        return $result;

    }

    public function getReceta($idReceta, $fecha_hora = true){
        $result = '';
 
        $sql = "SELECT id, fecha_hora, diagnostico, medicamento FROM receta_medica WHERE id = :id";
        $stmt = $this->bd->prepare($sql);
        $stmt->execute([':id' => $idReceta]);  

        if ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $fecha = (new \DateTime($data['fecha_hora']))->format('d/m/Y');
            $hora = (new \DateTime($data['fecha_hora']))->format('h:i a');

            $result .= '<div class="float-end"><a target="_blank" href="/pdf/receta/'.$data['id'].'" class="btn icon btn-primary"><i data-feather="printer"></i></a></div>';
            
            if($fecha_hora){

                $result .= '<div><small class="text-primary">Fecha: </small> <label class="fs-5">' . $fecha . '</label>, <small class="text-primary">Hora: </small> <label class="fs-5">' . $hora . '</label></div>';   
            
            }
            
            $result .= '<div class="mt-3"><small class="text-primary">Diagnostico:</small> <label class="fs-5">' . $data['diagnostico'] . '</label></div>

            <label class="mt-3"><small class="text-primary">Medicamento: </small></label>
            <div class="fs-5">'.$data['medicamento'].'</div>';
            
        } else {
            $result = '<div class="text-center p-4 text-light">No se encontró información.</div>';
        }
       
        return $result;
    }


}