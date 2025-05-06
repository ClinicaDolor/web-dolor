<?php
namespace App\Models;

use App\Config\Database;
use App\Models\ClinicaModel;
use App\Models\RecetaModel;

class NotaSubsecuenteModel{

    private $fecha_hora;
    private $id_paciente;
    private $contenido;

    private $ta;
    private $frec_cardiaca;
    private $pulso;
    private $spo2;
    private $fio2;
    private $ecog;
    private $karnovsky;
    private $peso;
    private $talla;
    private $proxima_cita;

    private $bd;
    public function __construct(){

        $this->bd = Database::getInstance();

    }

    public function NotaSubsecuente($idNota){

        $query = "SELECT * FROM nota_subsecuente WHERE id = :id";
        $stmt = $this->bd->prepare($query);
        $stmt->bindParam(':id', $idNota);
        $stmt->execute();
        $registros = $stmt->fetch(\PDO::FETCH_ASSOC);

        
        $campos = [
            'fecha_hora',
            'id_paciente',
            'contenido',
            'ta',
            'frec_cardiaca',
            'pulso',
            'spo2',
            'fio2',
            'ecog',
            'karnovsky',
            'peso',
            'talla',
            'proxima_cita',
                        
        ];
        
        foreach ($campos as $campo) {
            $this->$campo = $registros[$campo] ?? null;
        }

    }

    //--------------------------------------------------------------------------
    //--------------------------------------------------------------------------
    
    public function getFechaHora()
    {
        return $this->fecha_hora;
    }

    public function getIdPaciente()
    {
        return $this->id_paciente;
    }

    public function getContenido()
    {
        return $this->contenido;
    }
    public function getTa()
    {
        return $this->ta;
    }

    public function getFrecCardiaca()
    {
        return $this->frec_cardiaca;
    }

    public function getPulso()
    {
        return $this->pulso;
    }

    public function getSpo2()
    {
        return $this->spo2;
    }

    public function getFio2()
    {
        return $this->fio2;
    }

    public function getEcog()
    {
        return $this->ecog;
    }

    public function getKarnovsky()
    {
        return $this->karnovsky;
    }

    public function getPeso()
    {
        return $this->peso;
    }

    public function getTalla()
    {
        return $this->talla;
    }

    public function getProximaCita()
    {
        return $this->proxima_cita;
    }

    //-------------------------------------------------------------------------
    //----- CODIGO REFERENCIA NOTA SUBSECUENTE --------------------------------

    public function codigoReferencia($idPaciente,$codigo){

        $query = "SELECT id FROM nota_subsecuente WHERE id_paciente = :id_paciente AND codigo_referencia = :codigo LIMIT 1";
        $stmt = $this->bd->prepare($query);

        $stmt->bindParam(':id_paciente', $idPaciente);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['id'] ?? 0;

    }

    //-------------------------------------------------------------------------

    //-------------------------------------------------------------------------
    //--------------- PRIMERA NOTA SUBSECUENTE DEL PACIENTE -------------------

    public function primerNota(){

        $query = "SELECT id FROM nota_subsecuente ORDER BY id ASC LIMIT 1";
        $stmt = $this->bd->prepare($query);
        $stmt->execute();
        $registros = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $registros['id'];

    }

    //--------------------------------------------------------------------------
    //--------------------------------------------------------------------------

    function mostrarTablaNotas($idPaciente,$referencia){

        $result = '';

        $qry_referencia = ($referencia == 0)? ' id_paciente = "'.$idPaciente.'"' : ' id_paciente = "'.$idPaciente.'" AND codigo_referencia = "'.$referencia.'" ';
        $query = $this->bd->query("SELECT * FROM nota_subsecuente WHERE $qry_referencia ORDER BY fecha_hora DESC");
        $registros = $query->fetchAll(\PDO::FETCH_ASSOC);

        $result .= '<table class="table table-striped table-hover table-sm pb-0 mb-0" id="tableNotasSubsecuentes">
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th>Fecha y Hora</th>
                <th>Proxima cita</th>
                <th class="text-center" width="20"><i data-feather="edit-2" width="20"></i></th>
            </tr>
        </thead>
        <tbody>';

        foreach ($registros as $registro): 

        $primer = ($this->primerNota() == $registro['id'])? '<i class="text-info text-end" data-feather="star" width="20"></i>': '';
        $fecha_hora = (new \DateTime(datetime: $registro['fecha_hora']))->format('d/m/Y h:i a');
        $procima_cita = ($registro['proxima_cita'] == "")? 'S/I': (new \DateTime(datetime: $registro['proxima_cita']))->format('d/m/Y');

            $result .= '<tr onclick="DetalleNota('.$registro['id'].')">
                <td class="text-center">'.$registro['id'].'</td>
                <td>'.$fecha_hora.' '.$primer.'</td>
                <td>'.$procima_cita.'</td>
                <td class="text-center"><a href="../nota-subsecuente/paciente/'.$idPaciente.'/referencia/'.$registro['codigo_referencia'].'"><i data-feather="edit-2" width="20"></i></a></td>
            </tr>';
        endforeach;
        $result .= '</tbody>
        </table>';

        return $result;
    }
    public function insertNotaSubsecuente($data){

        $model = new ClinicaModel();
        
        $sql_nota = "INSERT INTO nota_subsecuente (
            id_paciente,

            ta,
            frec_cardiaca,
            pulso,
            spo2,
            fio2,
            ecog,
            karnovsky,
            peso,
            talla,

            contenido,            
            codigo_referencia,
            proxima_cita
        ) VALUES (
            :id_paciente,

            :ta,
            :frec_cardiaca,
            :pulso,
            :spo2,
            :fio2,
            :ecog,
            :karnovsky,
            :peso,
            :talla,

            :contenido,
            :codigo_referencia,
            :proxima_cita
        )";

        $stmt = $this->bd->prepare($sql_nota);         

            $datos = [
            ':id_paciente' => $data['idPaciente'],

            ':ta' => $data['ta'],
            ':frec_cardiaca' => $data['frecCardiaca'],
            ':pulso' => $data['pulso'],
            ':spo2' => $data['spo2'],
            ':fio2' => $data['fio2'],
            ':ecog' => $data['ecog'],
            ':karnovsky' => $data['karnovsky'],
            ':peso' => $data['peso'],
            ':talla' => $data['talla'],

            ':contenido' => $data['contenidoNota'],
            ':codigo_referencia' => $data['referencia'],
            ':proxima_cita' => $data['proximacita']
            
            ];
        
            if ($stmt->execute($datos)) {
            
                $idNota = $this->bd->lastInsertId();
                $receta = $model->insertPacienteReceta($data);
                $idReceta = $receta['mensaje'];
            
                return array('resultado' => 200,'mensaje' => array('idNota' => $idNota, 'idReceta' => $idReceta));

            } else {
                return array('resultado' => 401,'mensaje' => '¡Error al agregar nueva nota subsecuente a la lista!');
            }
            

    }

    public function editarNotaSubsecuente($data){

    $sql = "UPDATE nota_subsecuente SET 
            ta = :ta,
            frec_cardiaca = :frec_cardiaca,
            pulso = :pulso,
            spo2 = :spo2,
            fio2 = :fio2,
            ecog = :ecog,
            karnovsky = :karnovsky,
            peso = :peso,
            talla = :talla,
            contenido = :contenido,
            proxima_cita = :proximacita
            WHERE id = :id";

    $stmt = $this->bd->prepare($sql);

    $stmt->bindParam(':ta', $data['ta']);
    $stmt->bindParam(':frec_cardiaca', $data['frecCardiaca']);
    $stmt->bindParam(':pulso', $data['pulso']);
    $stmt->bindParam(':spo2', $data['spo2']);
    $stmt->bindParam(':fio2', $data['fio2']);
    $stmt->bindParam(':ecog', $data['ecog']);
    $stmt->bindParam(':karnovsky', $data['karnovsky']);
    $stmt->bindParam(':peso', $data['peso']);
    $stmt->bindParam(':talla', $data['talla']);
    $stmt->bindParam(':contenido', $data['contenidoNota']);
    $stmt->bindParam(':proximacita', $data['proximacita']);
    $stmt->bindParam(':id', $data['idNota']);

    if ($stmt->execute()) {

        $sql_receta = "UPDATE receta_medica SET 
            diagnostico = :diagnostico,
            medicamento = :medicamento
            WHERE id = :id";

        $stmt_receta = $this->bd->prepare($sql_receta);
        $stmt_receta->bindParam(':diagnostico', $data['diagnostico']);
        $stmt_receta->bindParam(':medicamento', $data['medicamento']);
        $stmt_receta->bindParam(':id', $data['idReceta']);
        $stmt_receta->execute();

        return array('resultado' => 200,'mensaje' => array('idNota' => $data['idNota'], 'idReceta' => $data['idReceta']));

    } else {
        return array('resultado' => 401,'mensaje' => '¡Error al agregar nueva nota subsecuente a la lista!');
    }

}

    public function ultimaNota($idPaciente){

        $result = '';
 
        $sql = "SELECT id FROM nota_subsecuente WHERE id_paciente = :id ORDER BY id DESC LIMIT 1";
        $stmt = $this->bd->prepare($sql);
        $stmt->execute([':id' => $idPaciente]);

        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        $idNota = isset($data['id']) && !empty($data['id']) ? $data['id'] : 0;
        $result .= $this->getNotaSubsecuente($idNota);
               
        return $result;        

    }

    public function getNotaSubsecuente($idNota){

        $model = new RecetaModel();
        $result = '';
 
        $sql = "SELECT id_paciente, fecha_hora, ta,
        frec_cardiaca,
        pulso,
        spo2,
        fio2,
        ecog,
        karnovsky,
        peso,
        talla, contenido, codigo_referencia FROM nota_subsecuente WHERE id = :id";
        $stmt = $this->bd->prepare($sql);
        $stmt->execute([':id' => $idNota]);
        
        if($data = $stmt->fetch(\PDO::FETCH_ASSOC)){

            $idPaciente = $data['id_paciente'];
            $referencia = $data['codigo_referencia'];

            $idReceta = $model->idRecetaReferencia($idPaciente,$referencia);

            $fecha = (new \DateTime($data['fecha_hora']))->format('d/m/Y');
            $hora = (new \DateTime($data['fecha_hora']))->format('h:i a');
            
            $result .= '<div class="float-end"><a href="'. SERVIDOR .'clinica/nota-subsecuente/paciente/'.$idPaciente.'/referencia/'.$data['codigo_referencia'].'" class="btn icon btn-primary"><i data-feather="edit-2"></i></a></div>';
            $result .= '
            <div><label class="text-primary"><small>Fecha:</small></label> <label class="fs-5"> '.$fecha.'</label>, <label class="text-primary"><small>Hora:</small></label> <label class="fs-5">'.$hora.'</label></div>
            
            <label class="text-primary mt-3"><small>Signos vitales:</small></label>
            <p class="mt-2">
            <label class="text-success"><b>-TA.:</b></label>  <label class="text-secondary">'.$data['ta'].' mmHg,</label>
            <label class="text-success"><b>-Frec cardiaca:</b></label>  <label class="text-secondary">'.$data['frec_cardiaca'].' lpm,</label>
            <label class="text-success"><b>-Pulso:</b></label> <label class="text-secondary">'.$data['pulso'].' lpm,</label>
            <label class="text-success"><b>-SpO2.:</b></label> <label class="text-secondary">'.$data['spo2'].' %,</label>
            <label class="text-success"><b>-FiO2:</b></label> <label class="text-secondary">'.$data['fio2'].' %,</label>
            <label class="text-success"><b>-ECOG.:</b></label> <label class="text-secondary">'.$data['ecog'].',</label>
            <label class="text-success"><b>-Karnovsky:</b></label> <label class="text-secondary">'.$data['karnovsky'].',</label>
            <label class="text-success"><b>-Peso.:</b></label> <label class="text-secondary">'.$data['peso'].',</label>
            <label class="text-success"><b>-Talla.:</b></label> <label class="text-secondary">'.$data['talla'].'</label>
            </p>

            <label class="text-primary mt-3"><small>Nota:</small></label>
            <div class="fs-5">'.$data['contenido'].'</div>
            
            <hr>
            
            <div>
            <h5>Receta:</h5>
            '.$model->getReceta($idReceta,false).'</div>

            <hr>

            <div>
            <h5>Laboratorio:</h5>
            '.$this->mostrarTablaLaboratorio($idPaciente,$referencia).'</div>

            ';

        }else{

            $result = '<div class="text-center p-4 text-light">No se encontró información.</div>';

        }
                      
        return $result;

    }

    public function mostrarTablaLaboratorio($idPaciente,$referencia){

        $result = '';

        $qry_referencia = ($referencia == 0)? ' id_paciente = "'.$idPaciente.'"' : ' id_paciente = "'.$idPaciente.'" AND codigo_referencia = "'.$referencia.'" ';
        $query = $this->bd->query("SELECT * FROM laboratorio WHERE $qry_referencia ORDER BY fecha_hora DESC");
        $registros = $query->fetchAll(\PDO::FETCH_ASSOC);

        $result .= '<table class="table table-sm table-striped table-hover pb-0 mb-0" id="LaboratorioNotaSub">
        <thead>
            <tr>
                <th>Fecha y Hora</th>
                <th>Descripción</th>
                <th>Titulo</th>
            </tr>
        </thead>
        <tbody>';
        if (!empty($registros)) {
           foreach ($registros as $data_laboratorio):

            $fecha_hora = (new \DateTime(datetime: $data_laboratorio['fecha_hora']))->format('d/m/Y h:i a');

            $result .= '<tr>
                <td>'.$fecha_hora.'</td>
                <td>'.$data_laboratorio['titulo'].'</td>
                <td class="text-center">
            <a download="'.$data_laboratorio['nombre'].'" href="/descargar/'.$data_laboratorio['ruta'].'/'.$data_laboratorio['nombre'].'" class="btn icon btn-primary"><i data-feather="download"></i></a>
                </td>
            </tr>';
            endforeach;  
        }
    $result .= '</tbody>
    </table>';
    
    return $result; 
    }

    
}   