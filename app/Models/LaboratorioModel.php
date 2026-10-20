<?php

namespace App\Models;
use App\Config\Database;

class LaboratorioModel{
    public const MAX_UPLOAD_BYTES = 10485760;

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
        $idPaciente = filter_var($idPaciente, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idPaciente === false) {
            return ['resultado' => 422, 'mensaje' => 'Paciente no válido.'];
        }

        $validation = $this->validateUploadedPdf($fileData, $fileName, $fileExtension);
        if (!$validation['valid']) {
            return ['resultado' => $validation['resultado'], 'mensaje' => $validation['mensaje']];
        }

        $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $uploadDir = $documentRoot . '/public/storage/laboratorio/';
        if ($documentRoot === '' || $uploadDir === '/public/storage/laboratorio/') {
            return ['resultado' => 500, 'mensaje' => 'No fue posible preparar el almacenamiento.'];
        }

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
            return ['resultado' => 500, 'mensaje' => 'No fue posible preparar el almacenamiento.'];
        }
        if (!is_writable($uploadDir)) {
            return ['resultado' => 500, 'mensaje' => 'El almacenamiento no está disponible.'];
        }

        $storedName = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $candidate = 'lab_' . bin2hex(random_bytes(16)) . '.pdf';
            } catch (\Throwable $e) {
                return ['resultado' => 500, 'mensaje' => 'No fue posible preparar el archivo.'];
            }
            if (!file_exists($uploadDir . $candidate)) {
                $storedName = $candidate;
                break;
            }
        }

        if ($storedName === null) {
            return ['resultado' => 500, 'mensaje' => 'No fue posible reservar el archivo.'];
        }

        $filePath = $uploadDir . $storedName;
        if (!move_uploaded_file($fileData, $filePath)) {
            return ['resultado' => 500, 'mensaje' => 'No fue posible guardar el archivo.'];
        }

        $storedSize = filesize($filePath);
        if ($storedSize === false || $storedSize <= 0) {
            @unlink($filePath);
            return ['resultado' => 500, 'mensaje' => 'No fue posible verificar el archivo guardado.'];
        }

        try {
            $query = "INSERT INTO laboratorio (id_paciente, nombre, ruta, tipo, tamano, titulo, descripcion, codigo_referencia)
                      VALUES (:id_paciente, :nombre, :ruta, :tipo, :tamano, :titulo, :descripcion, :codigo_referencia)";
            $stmt = $this->bd->prepare($query);
            $stmt->execute([
                ':id_paciente' => $idPaciente,
                ':nombre' => $storedName,
                ':ruta' => 'laboratorio',
                ':tipo' => 'pdf',
                ':tamano' => (int)$storedSize,
                ':titulo' => $titulo,
                ':descripcion' => $contenidoLaboratorio,
                ':codigo_referencia' => $referencia
            ]);

            return ['resultado' => 200, 'mensaje' => $this->bd->lastInsertId()];
        } catch (\Throwable $e) {
            if (is_file($filePath) && !@unlink($filePath)) {
                error_log('Laboratory upload cleanup failed after database error.');
            }
            return ['resultado' => 500, 'mensaje' => 'No fue posible registrar el archivo.'];
        }
    }

    private function validateUploadedPdf($fileData, $fileName, $fileExtension)
    {
        if (!is_string($fileData) || !is_uploaded_file($fileData)) {
            return ['valid' => false, 'resultado' => 422, 'mensaje' => 'El archivo recibido no es válido.'];
        }

        $hasPathSeparator = is_string($fileName)
            && (strpos($fileName, '/') !== false
                || strpos($fileName, '\\') !== false
                || strpos($fileName, "\0") !== false);
        if (!is_string($fileName) || $fileName === '' || $hasPathSeparator) {
            return ['valid' => false, 'resultado' => 422, 'mensaje' => 'El nombre del archivo no es válido.'];
        }

        $extension = strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION));
        if ($extension !== 'pdf' || strtolower((string)$fileExtension) !== 'pdf') {
            return ['valid' => false, 'resultado' => 422, 'mensaje' => 'Sólo se permiten archivos PDF.'];
        }

        $actualSize = filesize($fileData);
        if ($actualSize === false || $actualSize <= 0) {
            return ['valid' => false, 'resultado' => 422, 'mensaje' => 'El archivo está vacío o no se puede leer.'];
        }
        if ($actualSize > self::MAX_UPLOAD_BYTES) {
            return ['valid' => false, 'resultado' => 422, 'mensaje' => 'El archivo no puede superar los 10 MiB.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return ['valid' => false, 'resultado' => 500, 'mensaje' => 'No fue posible verificar el archivo.'];
        }
        $mime = finfo_file($finfo, $fileData);
        finfo_close($finfo);
        if ($mime !== 'application/pdf') {
            return ['valid' => false, 'resultado' => 422, 'mensaje' => 'El contenido del archivo no es un PDF válido.'];
        }

        $handle = @fopen($fileData, 'rb');
        $signature = $handle ? fread($handle, 5) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($signature !== '%PDF-') {
            return ['valid' => false, 'resultado' => 422, 'mensaje' => 'El contenido del archivo no es un PDF válido.'];
        }

        return ['valid' => true, 'resultado' => 200, 'mensaje' => '', 'size' => (int)$actualSize];
    }

    public function getLaboratorio($idLaboratorio){

        $result = '';
 
        $sql = "SELECT id, fecha_hora, nombre, ruta, tipo, titulo, descripcion FROM laboratorio WHERE id = :id ORDER BY id DESC LIMIT 1";
        $stmt = $this->bd->prepare($sql);
        $stmt->execute([':id' => $idLaboratorio]);
 
        if ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $fecha = (new \DateTime($data['fecha_hora']))->format('d/m/Y');
            $hora = (new \DateTime($data['fecha_hora']))->format('h:i a');
            $safeName = htmlspecialchars((string)$data['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeRoute = rawurlencode((string)$data['ruta']);
            $safeFile = rawurlencode((string)$data['nombre']);
            $safeTitle = htmlspecialchars((string)$data['titulo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeDescription = RecetaModel::sanitizeMedicationHtml((string)$data['descripcion']);
            $result .= '
            <div class="float-end">
            <a download="'.$safeName.'" href="/descargar/'.$safeRoute.'/'.$safeFile.'" class="btn icon btn-primary"><i data-feather="download"></i></a></div>
            <div>
            <label class="text-primary"><small>Fecha: </small></label> <label class="fs-5">' . $fecha . '</label>, <label class="text-primary"><small>Hora: </small></label> <label class="fs-5">'. $hora .'</label>
            </div>

            <label class="text-primary mt-3"><small>Titulo: </small></label>
            <div class="fs-5">'.$safeTitle.'</div>

            <label class="text-primary mt-3"><small>Descripción: </small></label>
            <div class="fs-5">'.$safeDescription.'</div>';
            
        } else {
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
            $safeId = (int)$data_laboratorio['id'];
            $safeTitle = htmlspecialchars((string)$data_laboratorio['titulo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $result .= '<tr onclick="DetalleLaboratorio('.$safeId.')">
                <td class="text-center">'.$safeId.'</td>
                <td>'.$fecha_hora.'</td>
                <td>'.$safeTitle.'</td>
            </tr>';
            endforeach;  
    $result .= '</tbody>
    </table>';
    
    return $result;
    }
}
