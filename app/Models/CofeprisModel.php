<?php
namespace App\Models;
use App\Config\Database;
use setasign\Fpdi\Tcpdf\Fpdi;

class CofeprisModel{

    private $bd;
    private $fecha;
    private $hora;
    private $id_paciente;
    private $folio;
    private $numcajas;
    private $medicamento;
    private $diagnostico;
    private $presentacion;
    private $dosificacion;
    private $numdias;
    private $viaadministracion;

    private $carpeta;
    public function __construct(){
        $this->bd = Database::getInstance();
    }

    public function cofepris($id){

        $query = "SELECT * FROM cofepris WHERE id = :id";
        $stmt = $this->bd->prepare($query);
        $stmt->bindParam(':id', $id);
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
        $this->carpeta = $registros['carpeta'] ?? null;
        $this->folio = $registros['folio'] ?? null;
        $this->diagnostico = $registros['diagnostico'] ?? null;
        $this->medicamento = $registros['medicamento'] ?? null;
        $this->numcajas = $registros['num_cajas'] ?? null;
        $this->presentacion = $registros['presentacion'] ?? null;
        $this->dosificacion = $registros['dosificacion'] ?? null;
        $this->numdias = $registros['num_dias'] ?? null;
        $this->viaadministracion = $registros['via_administracion'] ?? null;
    }

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

    public function getFolio()
    {
        return $this->folio;
    }

    public function getNumcajas()
    {
        return $this->numcajas;
    }

    public function getMedicamento()
    {
        return $this->medicamento;
    }

    public function getDiagnostico()
    {
        return $this->diagnostico;
    }

    public function getPresentacion()
    {
        return $this->presentacion;
    }
    public function getDosificacion()
    {
        return $this->dosificacion;
    }
    public function getNumdias()
    {
        return $this->numdias;
    }
    public function getViaadministracion()
    {
        return $this->viaadministracion;
    }
    public function getCarpeta()
    {
        return $this->carpeta;
    }

    public function insertPacienteCofepris($data){

        $generaFolio = $this->generarFolio();

        if($generaFolio['carpeta'] != 0){
        $sql = "INSERT INTO cofepris (
            id_paciente,
            carpeta,
            folio,
            diagnostico,
            medicamento,
            num_cajas,
            presentacion,
            dosificacion,
            num_dias,
            via_administracion

        ) VALUES (
            :id_paciente,
            :carpeta,
            :folio,
            :diagnostico,
            :medicamento,
            :num_cajas,
            :presentacion,
            :dosificacion,
            :num_dias,
            :via_administracion
        )";

        $stmt = $this->bd->prepare($sql);         

        $datos = [
            ':id_paciente' => $data['idPaciente'],
            ':carpeta' => $generaFolio['carpeta'],
            ':folio' => $generaFolio['folio'],
            ':diagnostico' => $data['diagnostico'],
            ':medicamento' => $data['medicamento'],
            ':num_cajas' => $data['numCajas'],
            ':presentacion' => $data['presentacion'],
            ':dosificacion' => $data['dosificacion'],
            ':num_dias' => $data['numDias'],
            ':via_administracion' => $data['viaAdministracion']
            ];
        
            if ($stmt->execute($datos)) {
                return array('resultado' => 200,'mensaje' => $this->bd->lastInsertId());
    
            } else {
                return array('resultado' => 401,'mensaje' => '¡Error al agregar nuevo registro a la lista!');
            }
        }else{
            return array('resultado' => 401,'mensaje' => $generaFolio['mensaje']);
        }

    }

    private function generarFolio(){

         // Obtener el rango de folios activos
         $stmt = $this->bd->prepare("SELECT * FROM cofepris_folio WHERE estado = 0 ORDER BY id DESC LIMIT 1");
         $stmt->execute();
         $rango = $stmt->fetch(\PDO::FETCH_ASSOC);
 
         if (!$rango) {
             return array('carpeta' => 0,'folio' => 0, 'mensaje' => 'No hay folios activos.');
         }
 
         $id = (int)$rango['id'];
         $folioInicial = (int)$rango['folio_inicial'];
         $folioFinal   = (int)$rango['folio_final'];
         $carpeta      = $rango['carpeta'];
 
         // Buscar el último folio usado para esa carpeta
         $stmt = $this->bd->prepare("SELECT MAX(CAST(folio AS UNSIGNED)) AS ultimo_folio FROM cofepris WHERE carpeta = ?");
         $stmt->execute([$carpeta]);
         $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);
 
         $ultimoUsado = $resultado['ultimo_folio'] ? (int)$resultado['ultimo_folio'] : ($folioInicial - 1);
         $siguienteFolio = $ultimoUsado + 1;
 
         if ($siguienteFolio > $folioFinal) {

            $stmt = $this->bd->prepare("UPDATE cofepris_folio SET estado = 1 WHERE carpeta = " . $carpeta);
            $stmt->execute();

             return array('carpeta' => 0,'folio' => 0, 'mensaje' => 'Ya no hay folios disponibles');
         }
 
         return array('carpeta' => $carpeta,'folio' => $siguienteFolio, 'mensaje' => 'Folio disponible');
          
    }

    public function mostrarTablaCofepris($idPaciente){

        $result = '';
        $stmt = $this->bd->query("SELECT * FROM cofepris WHERE id_paciente = '".$idPaciente."' ORDER BY fecha_hora DESC");
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result .= '<table class="table table-striped table-hover table-sm pb-0 mb-0" id="tableCofepris">
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th>Fecha y Hora</th>
                <th>Folio</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($registros as $registro):

            $fecha_hora = (new \DateTime(datetime: $registro['fecha_hora']))->format('d/m/Y h:i a');

            $result .= '<tr onclick="DetalleCofepris('.$registro['id'].')">
                        <td class="text-center">'.$registro['id'].'</td>
                        <td>'.$fecha_hora.'</td>
                        <td>'.$registro['folio'].'</td>
                        </tr>';

        endforeach;

        $result .= '</tbody>
        </table>';
    
    return $result;

    }

    public function ultimoRegistro($idPaciente){

        $result = '';
 
        $sql = "SELECT id FROM cofepris WHERE id_paciente = :id ORDER BY id DESC LIMIT 1";
        $stmt = $this->bd->prepare($sql);
        $stmt->execute([':id' => $idPaciente]);
 
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        $idCofepris = isset($data['id']) && !empty($data['id']) ? $data['id'] : 0;
        $result = $this->getCofepris($idCofepris);
          
        return $result;

    }

    public function getCofepris($idCofepris, $fecha_hora = true){
        $result = '';
 
        $sql = "SELECT id, fecha_hora, folio, num_cajas, medicamento, diagnostico, presentacion, dosificacion, num_dias, via_administracion FROM cofepris WHERE id = :id";
        $stmt = $this->bd->prepare($sql);
        $stmt->execute([':id' => $idCofepris]);  

        if ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $fecha = (new \DateTime($data['fecha_hora']))->format('d/m/Y');
            $hora = (new \DateTime($data['fecha_hora']))->format('h:i a');

            $result .= '<div class="float-end"><a target="_blank" href="/pdf/cofepris/'.$data['id'].'" class="btn icon btn-primary"><i data-feather="printer"></i></a></div>';
            if($fecha_hora){
                $result .= '<div><small class="text-primary">Fecha: </small> <label class="fs-5">' . $fecha . '</label>, <small class="text-primary">Hora: </small> <label class="fs-5">' . $hora . '</label></div>';   
            }
 
            $result .= '
            
            <div class="row mt-4">
                    <div class="col-12 col-sm-6">
                        <div class="mt-1"><small class="text-primary">Folio: </small></div> <label class="fs-5">'.$data['folio'].'</label>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="mt-1"><small class="text-primary">Diagnostico: </small></div> <label class="fs-5">'.$data['diagnostico'].'</label>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-sm-6">
                        <div class="mt-1"><small class="text-primary">Medicamento: </small></div> <label class="fs-5">'.$data['medicamento'].'</label>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="mt-1"><small class="text-primary">Número de cajas: </small></div> <label class="fs-5">'.$data['num_cajas'].'</label>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-sm-6">
                        <div class="mt-1"><small class="text-primary">Presentación: </small></div> <label class="fs-5">'.$data['presentacion'].'</label>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="mt-1"><small class="text-primary">Dosificación: </small></div> <label class="fs-5">'.$data['dosificacion'].'</label>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-sm-6">
                        <div class="mt-1"><small class="text-primary">No. de días de prescripción: </small></div> <label class="fs-5">'.$data['num_dias'].'</label>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="mt-1"><small class="text-primary">Via de administración: </small></div> <label class="fs-5">'.$data['via_administracion'].'</label>
                    </div>
                </div>';
            
        } else {
            $result = '<div class="text-center p-4 text-light">No se encontró información.</div>';
        }
       
        return $result;
    }

    public function guardarFolio($folio,$fileName,$fileData,$fileSize,$fileExtension){
        
        $folio_inicial = $folio;
        $fecha = date("Ymd"); 
        $aleatorio = rand(1000, 9999);
        $nombre_carpeta = $fecha . $aleatorio;

        // Carpeta destino para las páginas separadas
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/public/storage/cofepris/';
        $destino = $_SERVER['DOCUMENT_ROOT'] . '/public/storage/cofepris/' . $nombre_carpeta . '/';

        if (!file_exists($destino)) {
            mkdir($destino, 0777, true);
        }

        $filePath = $uploadDir . basename($fileName);
        if (move_uploaded_file($fileData, $filePath)) {

         // Usar el archivo recibido, no uno fijo
         $archivoOriginal = $_SERVER['DOCUMENT_ROOT'] . '/public/storage/cofepris/' . $fileName;

          // Verificar que sea un PDF válido
        if (strtolower($fileExtension) !== 'pdf') {
            throw new \Exception("El archivo debe ser un PDF.");
        }

        $tempPdf = new Fpdi();
        $pageCount = $tempPdf->setSourceFile($archivoOriginal); // Contar páginas

        for ($i = 1; $i <= $pageCount; $i++) {
            $pdf = new Fpdi();
        
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(0, 0, 0);
        
            $pdf->setSourceFile($archivoOriginal);
            $templateId = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($templateId);
        
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);
        
            $nombreArchivo = $destino . $folio . '.pdf';
            $pdf->Output($nombreArchivo, 'F');
        
            $folio++; // Incrementar folio para el siguiente archivo
        }
    
        $folio_final = ($pageCount == 1) ? $folio_inicial : ($folio_inicial + ($pageCount-1));

        $sql = "INSERT INTO cofepris_folio (
            folio_inicial,
            folio_final,
            carpeta,
            total_folio,
            estado
        ) VALUES (
            :folio_inicial,
            :folio_final,
            :carpeta,
            :total_folio,
            :estado
        )";

        $stmt = $this->bd->prepare($sql);         

        $datos = [
            ':folio_inicial' => $folio_inicial,
            ':folio_final' => $folio_final,
            ':carpeta' => $nombre_carpeta,
            'total_folio' => $pageCount,
            ':estado' => 0
            ];
        
            if ($stmt->execute($datos)) {
                return array('resultado' => 200,'mensaje' => 'OK');
    
            } else {
                return array('resultado' => 401,'mensaje' => '¡Error al agregar nuevos folios!');
            }

        }else{

            return array('resultado' => 401,'mensaje' => '¡Error al guardar el archivo!');

        }

            
    }

    public function getCofeprisFolios(){

        $result = '';
        $stmt = $this->bd->query("SELECT * FROM cofepris_folio ORDER BY fecha_hora DESC");
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result .= '<table class="table table-striped table-hover table-sm pb-0 mb-0" id="tableCofepris">
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th>Fecha y Hora</th>
                <th>Folio inicial</th>
                <th>Folio final</th>
                <th>Folder</th>
                <th>Detalle</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($registros as $registro):

            $fecha_hora = (new \DateTime(datetime: $registro['fecha_hora']))->format('d/m/Y h:i a');
            $estado = ($registro['estado'] == 0)? '<span class="badge bg-success">Activo</span>': '<span class="badge bg-danger">Finalizados</span>';
            $folio_pendientes = $this->foliosPendientes($registro['carpeta'],$registro['total_folio']);

            $result .= '<tr>
                        <td class="text-center">'.$registro['id'].'</td>
                        <td>'.$fecha_hora.'</td>
                        <td>'.$registro['folio_inicial'].'</td>
                        <td>'.$registro['folio_final'].'</td>
                        <td><a href="cofepris/carpeta/'.$registro['carpeta'].'">'.$registro['carpeta'].'</a></td>
                        <td>'.$folio_pendientes.'</td>
                        <td class="text-center">'.$estado.'</td>
                        </tr>';

        endforeach;

        $result .= '</tbody>
        </table>';
    
    return $result;

    }

    private function foliosPendientes($carpeta,$total_folio){

        $stmt = $this->bd->prepare("SELECT COUNT(id) AS numfolios FROM cofepris WHERE carpeta = ?");
        $stmt->execute([$carpeta]);
        $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);

        return '<label class="fw-light text-light">'.$resultado['numfolios'].' de '. $total_folio.'</label>';

    }

    public function validaCofeprisFolio($folio, $carpeta){

        $stmt = $this->bd->prepare("SELECT
        cofepris.id,
        cofepris.fecha_hora,
        cofepris.id_paciente,
        pc_paciente.nombre_completo,
        cofepris.carpeta,
        cofepris.folio
        FROM cofepris 
        INNER JOIN pc_paciente 
        ON cofepris.id_paciente = pc_paciente.id
        WHERE cofepris.carpeta = $carpeta AND cofepris.folio = $folio ");
        $stmt->execute();
        $folio = $stmt->fetch(\PDO::FETCH_ASSOC);

        if($folio){
            $array = array('id_cofepris' => $folio['id'], 'fecha_hora' => $folio['fecha_hora'], 'paciente' => $folio['nombre_completo']);
        }else{
            $array = array('id_cofepris' => 0, 'fecha_hora' => '', 'paciente' => '');
        }

        return $array;

    }

    public function cofeprisFoliosActivos(){

        $stmt = $this->bd->prepare("SELECT COUNT(*) as total FROM cofepris_folio WHERE estado = 0");
        $stmt->execute();
        $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);
        $totalRegistros = $resultado['total'];
        return $totalRegistros;

    }

}