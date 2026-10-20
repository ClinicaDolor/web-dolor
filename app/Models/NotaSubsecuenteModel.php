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

    public function belongsToPatientReference(int $idNota, int $idPaciente, string $referencia): bool
    {
        if ($idNota < 1 || $idPaciente < 1 || $referencia === '') {
            return false;
        }

        $stmt = $this->bd->prepare(
            'SELECT id FROM nota_subsecuente
             WHERE id = :id AND id_paciente = :id_paciente AND codigo_referencia = :referencia
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $idNota,
            ':id_paciente' => $idPaciente,
            ':referencia' => $referencia
        ]);

        return (bool)$stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private static function escapeDisplayText($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function safeReferencePath($value): string
    {
        return rawurlencode((string)$value);
    }

    //-------------------------------------------------------------------------

    //-------------------------------------------------------------------------
    //--------------- PRIMERA NOTA SUBSECUENTE DEL PACIENTE -------------------

    public function primerNota($idPaciente = null){

        $query = "SELECT id FROM nota_subsecuente";
        $params = [];
        if ($idPaciente !== null) {
            $query .= " WHERE id_paciente = :id_paciente";
            $params[':id_paciente'] = (int)$idPaciente;
        }
        $query .= " ORDER BY id ASC LIMIT 1";
        $stmt = $this->bd->prepare($query);
        $stmt->execute($params);
        $registros = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $registros['id'] ?? 0;

    }

    //--------------------------------------------------------------------------
    //--------------------------------------------------------------------------

    function mostrarTablaNotas($idPaciente,$referencia){

        $result = '';
        $idPaciente = filter_var($idPaciente, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idPaciente === false) {
            return $result;
        }

        $query = "SELECT * FROM nota_subsecuente WHERE id_paciente = :id_paciente";
        $params = [':id_paciente' => (int)$idPaciente];
        if ((string)$referencia !== '0') {
            $query .= " AND codigo_referencia = :referencia";
            $params[':referencia'] = (string)$referencia;
        }
        $query .= " ORDER BY fecha_hora DESC";
        $stmt = $this->bd->prepare($query);
        $stmt->execute($params);
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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

        $registroId = (int)$registro['id'];
        $primer = ($this->primerNota((int)$idPaciente) == $registroId)? '<i class="text-info text-end" data-feather="star" width="20"></i>': '';
        $fecha_hora = (new \DateTime(datetime: $registro['fecha_hora']))->format('d/m/Y h:i a');
        $procima_cita = ($registro['proxima_cita'] == "")? 'S/I': (new \DateTime(datetime: $registro['proxima_cita']))->format('d/m/Y');
        $referencePath = self::safeReferencePath($registro['codigo_referencia']);

            $result .= '<tr onclick="DetalleNota('.$registroId.')">
                <td class="text-center">'.$registroId.'</td>
                <td>'.$fecha_hora.' '.$primer.'</td>
                <td>'.$procima_cita.'</td>
                <td class="text-center"><a href="../nota-subsecuente/paciente/'.(int)$idPaciente.'/referencia/'.$referencePath.'"><i data-feather="edit-2" width="20"></i></a></td>
            </tr>';
        endforeach;
        $result .= '</tbody>
        </table>';

        return $result;
    }

    /**
     * Historial completo del paciente autorizado.
     * La autorización de clínica/paciente se realiza antes, en el controlador.
     * La referencia se conserva por registro y se usa sólo para crear la nota nueva.
     */
    public function getHistoryByPatient(int $idPaciente): array
    {
        if ($idPaciente < 1) {
            return [];
        }

        $stmt = $this->bd->prepare(
            'SELECT id, fecha_hora, proxima_cita, codigo_referencia
             FROM nota_subsecuente
             WHERE id_paciente = :id_paciente
             ORDER BY fecha_hora DESC'
        );
        $stmt->execute([':id_paciente' => $idPaciente]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene una nota únicamente dentro del paciente y referencia autorizados.
     * La autorización de clínica/paciente se realiza antes, en el controlador.
     */
    public function getForPatientReference(int $idNota, int $idPaciente, string $referencia): ?array
    {
        if ($idNota < 1 || $idPaciente < 1 || $referencia === '') {
            return null;
        }

        $stmt = $this->bd->prepare(
            'SELECT id, id_paciente, fecha_hora, ta, frec_cardiaca, pulso,
                    spo2, fio2, ecog, karnovsky, peso, talla, contenido,
                    codigo_referencia, proxima_cita
             FROM nota_subsecuente
             WHERE id = :id
               AND id_paciente = :id_paciente
               AND codigo_referencia = :referencia
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $idNota,
            ':id_paciente' => $idPaciente,
            ':referencia' => $referencia,
        ]);

        $registro = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($registro) ? $registro : null;
    }

    private function validateOptionalRecipe(array $data): ?string
    {
        $diagnostico = trim((string)($data['diagnostico'] ?? ''));
        $medicamento = trim(strip_tags((string)($data['medicamento'] ?? '')));
        $ta = trim((string)($data['ta'] ?? ''));
        $fc = trim((string)($data['fc'] ?? ''));
        $spo2 = trim((string)($data['spo2'] ?? ''));
        $temperatura = trim((string)($data['temperatura'] ?? ''));

        if ($diagnostico === '' || $medicamento === '' || $ta === '' || $fc === '' || $spo2 === '' || $temperatura === '') {
            return 'Completa los campos requeridos de la receta antes de guardarla.';
        }

        if (!is_numeric($fc) || !is_numeric($spo2) || !is_numeric($temperatura)) {
            return 'La frecuencia cardiaca, la saturación y la temperatura deben ser valores válidos.';
        }

        return null;
    }

    private function recipeRequested(array $data): bool
    {
        return ($data['incluirReceta'] ?? false) === true;
    }

    public function insertNotaSubsecuente($data){

        $model = new ClinicaModel();
        $incluirReceta = $this->recipeRequested($data);

        if ($incluirReceta) {
            $recipeError = $this->validateOptionalRecipe($data);
            if ($recipeError !== null) {
                return ['resultado' => 422, 'mensaje' => $recipeError];
            }
        }

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
        
        try {
            $this->bd->beginTransaction();

            if (!$stmt->execute($datos)) {
                throw new \RuntimeException('Nota subsecuente insert failed.');
            }

                $idNota = $this->bd->lastInsertId();

            $idReceta = 0;
            if ($incluirReceta) {
                $receta = $model->insertPacienteReceta($data);
                if (($receta['resultado'] ?? null) !== 200 || !is_numeric($receta['mensaje'] ?? null) || (int)$receta['mensaje'] < 1) {
                    throw new \RuntimeException('Optional recipe insert failed.');
                }
                $idReceta = (int)$receta['mensaje'];
            }

            $this->bd->commit();
            return ['resultado' => 200, 'mensaje' => ['idNota' => $idNota, 'idReceta' => $idReceta]];

        } catch (\Throwable $e) {
            if ($this->bd->inTransaction()) {
                $this->bd->rollBack();
            }
            error_log('Subsequent note save transaction failed.');
            return ['resultado' => 500, 'mensaje' => 'No fue posible guardar la nota. No se aplicaron cambios parciales.'];
        }

    }

    public function editarNotaSubsecuente($data){

    $idNota = (int)($data['idNota'] ?? 0);
    $idPaciente = (int)($data['idPaciente'] ?? 0);
    $referencia = (string)($data['referencia'] ?? '');
    $idReceta = (int)($data['idReceta'] ?? 0);
    $incluirReceta = $this->recipeRequested($data);

    if (!$this->belongsToPatientReference($idNota, $idPaciente, $referencia)) {
        return array('resultado' => 403, 'mensaje' => 'La nota no pertenece al contexto autorizado.');
    }

    if ($idReceta > 0) {
        $recetaModel = new RecetaModel();
        if (!$recetaModel->belongsToPatientReference($idReceta, $idPaciente, $referencia)) {
            return array('resultado' => 403, 'mensaje' => 'La receta no pertenece al contexto autorizado.');
        }
    }

    if ($incluirReceta && $idReceta === 0) {
        $recipeError = $this->validateOptionalRecipe($data);
        if ($recipeError !== null) {
            return ['resultado' => 422, 'mensaje' => $recipeError];
        }
    }

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
            WHERE id = :id AND id_paciente = :id_paciente AND codigo_referencia = :codigo_referencia";

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
    $stmt->bindValue(':id', $idNota, \PDO::PARAM_INT);
    $stmt->bindValue(':id_paciente', $idPaciente, \PDO::PARAM_INT);
    $stmt->bindValue(':codigo_referencia', $referencia, \PDO::PARAM_STR);

    try {
        $this->bd->beginTransaction();

        if (!$stmt->execute()) {
            throw new \RuntimeException('Subsequent note update failed.');
        }

        if ($incluirReceta) {
            if ($idReceta > 0) {
                $sql_receta = "UPDATE receta_medica SET
                    diagnostico = :diagnostico,
                    medicamento = :medicamento
                    WHERE id = :id AND id_paciente = :id_paciente AND codigo_referencia = :codigo_referencia";

                $stmt_receta = $this->bd->prepare($sql_receta);
                $stmt_receta->bindParam(':diagnostico', $data['diagnostico']);
                $stmt_receta->bindParam(':medicamento', $data['medicamento']);
                $stmt_receta->bindValue(':id', $idReceta, \PDO::PARAM_INT);
                $stmt_receta->bindValue(':id_paciente', $idPaciente, \PDO::PARAM_INT);
                $stmt_receta->bindValue(':codigo_referencia', $referencia, \PDO::PARAM_STR);
                if (!$stmt_receta->execute()) {
                    throw new \RuntimeException('Optional recipe update failed.');
                }
            } else {
                $receta = (new ClinicaModel())->insertPacienteReceta($data);
                if (($receta['resultado'] ?? null) !== 200 || !is_numeric($receta['mensaje'] ?? null) || (int)$receta['mensaje'] < 1) {
                    throw new \RuntimeException('Optional recipe insert failed.');
                }
                $idReceta = (int)$receta['mensaje'];
            }
        }

        $this->bd->commit();
        return ['resultado' => 200, 'mensaje' => ['idNota' => $idNota, 'idReceta' => $idReceta]];
    } catch (\Throwable $e) {
        if ($this->bd->inTransaction()) {
            $this->bd->rollBack();
        }
        error_log('Subsequent note update transaction failed.');
        return ['resultado' => 500, 'mensaje' => 'No fue posible actualizar la nota. No se aplicaron cambios parciales.'];
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

            $idPaciente = (int)$data['id_paciente'];
            $referencia = (string)$data['codigo_referencia'];
            $referencePath = self::safeReferencePath($referencia);
            $fecha = (new \DateTime($data['fecha_hora']))->format('d/m/Y');
            $hora = (new \DateTime($data['fecha_hora']))->format('h:i a');
            $safeContent = RecetaModel::sanitizeMedicationHtml((string)$data['contenido']);
            $safeValues = [
                'ta' => self::escapeDisplayText($data['ta']),
                'frec_cardiaca' => self::escapeDisplayText($data['frec_cardiaca']),
                'pulso' => self::escapeDisplayText($data['pulso']),
                'spo2' => self::escapeDisplayText($data['spo2']),
                'fio2' => self::escapeDisplayText($data['fio2']),
                'ecog' => self::escapeDisplayText($data['ecog']),
                'karnovsky' => self::escapeDisplayText($data['karnovsky']),
                'peso' => self::escapeDisplayText($data['peso']),
                'talla' => self::escapeDisplayText($data['talla'])
            ];

            $idReceta = $model->idRecetaReferencia($idPaciente,$referencia);
            
            $result .= '<div class="float-end"><a href="'. htmlspecialchars(SERVIDOR .'clinica/nota-subsecuente/paciente/'.$idPaciente.'/referencia/'.$referencePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .'" class="btn icon btn-primary"><i data-feather="edit-2"></i></a></div>';
            $result .= '
            <div><label class="text-primary"><small>Fecha:</small></label> <label class="fs-5"> '.$fecha.'</label>, <label class="text-primary"><small>Hora:</small></label> <label class="fs-5">'.$hora.'</label></div>
            
            <label class="text-primary mt-3"><small>Signos vitales:</small></label>
            <p class="mt-2">
            <label class="text-success"><b>-TA.:</b></label>  <label class="text-secondary">'.$safeValues['ta'].' mmHg,</label>
            <label class="text-success"><b>-Frec cardiaca:</b></label>  <label class="text-secondary">'.$safeValues['frec_cardiaca'].' lpm,</label>
            <label class="text-success"><b>-Pulso:</b></label> <label class="text-secondary">'.$safeValues['pulso'].' lpm,</label>
            <label class="text-success"><b>-SpO2.:</b></label> <label class="text-secondary">'.$safeValues['spo2'].' %,</label>
            <label class="text-success"><b>-FiO2:</b></label> <label class="text-secondary">'.$safeValues['fio2'].' %,</label>
            <label class="text-success"><b>-ECOG.:</b></label> <label class="text-secondary">'.$safeValues['ecog'].',</label>
            <label class="text-success"><b>-Karnovsky:</b></label> <label class="text-secondary">'.$safeValues['karnovsky'].',</label>
            <label class="text-success"><b>-Peso.:</b></label> <label class="text-secondary">'.$safeValues['peso'].',</label>
            <label class="text-success"><b>-Talla.:</b></label> <label class="text-secondary">'.$safeValues['talla'].'</label>
            </p>

            <label class="text-primary mt-3"><small>Nota:</small></label>
            <div class="fs-5">'.$safeContent.'</div>
            
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

        $idPaciente = filter_var($idPaciente, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idPaciente === false) {
            return $result;
        }

        $query = "SELECT * FROM laboratorio WHERE id_paciente = :id_paciente";
        $params = [':id_paciente' => (int)$idPaciente];
        if ((string)$referencia !== '0') {
            $query .= " AND codigo_referencia = :referencia";
            $params[':referencia'] = (string)$referencia;
        }
        $query .= " ORDER BY fecha_hora DESC";
        $stmt = $this->bd->prepare($query);
        $stmt->execute($params);
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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

            $safeTitle = self::escapeDisplayText($data_laboratorio['titulo']);
            $safeName = rawurlencode((string)$data_laboratorio['nombre']);
            $safeRoute = rawurlencode((string)$data_laboratorio['ruta']);
            $result .= '<tr>
                <td>'.$fecha_hora.'</td>
                <td>'.$safeTitle.'</td>
                <td class="text-center">
            <a download="'.self::escapeDisplayText($data_laboratorio['nombre']).'" href="/descargar/'.$safeRoute.'/'.$safeName.'" class="btn icon btn-primary"><i data-feather="download"></i></a>
                </td>
            </tr>';
            endforeach;  
        }
    $result .= '</tbody>
    </table>';
    
    return $result; 
    }

    
}
