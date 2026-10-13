<?php
namespace App\Models;
use App\Config\Database;
use App\Models\PacienteModulosModelo;

class AntecedentesPatologicosModel{
private $bd;

public function __construct(){
$this->bd = Database::getInstance();

} 

    private function escapeHtml($value): string
    {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    private function obtenerContextoRespuestaV1(int $idPaciente, int $idRespuesta, int $idTema): ?array
    {
    $stmt = $this->bd->prepare('SELECT r.id, r.id_paciente, p.id_tema, p.tipo
        FROM pac_respuestas_paciente_modulo_5 r
        INNER JOIN pac_preguntas_modulo_5 p ON p.id = r.id_pregunta
        WHERE r.id = :id_respuesta AND r.id_paciente = :id_paciente AND p.id_tema = :id_tema
        LIMIT 1');
    $stmt->execute([
        ':id_respuesta' => $idRespuesta,
        ':id_paciente' => $idPaciente,
        ':id_tema' => $idTema,
    ]);
    $context = $stmt->fetch(\PDO::FETCH_ASSOC);
    return $context ?: null;
    }

    private function obtenerContextoRespuestaV2(int $idPaciente, int $idRespuesta): ?array
    {
    $stmt = $this->bd->prepare('SELECT id, id_paciente, id_enfermedad
        FROM pac_enfermedades_respuestas_modulo_5
        WHERE id = :id_respuesta AND id_paciente = :id_paciente
        LIMIT 1');
    $stmt->execute([
        ':id_respuesta' => $idRespuesta,
        ':id_paciente' => $idPaciente,
    ]);
    $context = $stmt->fetch(\PDO::FETCH_ASSOC);
    return $context ?: null;
    }

    //---------- OBTENER DATOS DEL MODULO ----------//
    public function obtenerModulosM5(){
    $stmt = $this->bd->query("SELECT id, tema FROM pac_temas_modulo_5 ORDER BY id ASC");
    $modulos = array();
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    // Se indexa el arreglo por id para facilitar el acceso
    $modulos[$row['id']] = $row['tema'];
    }
    return $modulos;
    }

    public function obtenerNameModulos($idTema){
    $stmt = $this->bd->query("SELECT tema FROM pac_temas_modulo_5 WHERE id = '".$idTema."' ORDER BY id ASC");
    $modulos = array();
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $tema = $row['tema'];
    }
    return $tema;
    }

    //---------- OBTENER DATOS DEL MODULO ----------//
    public function obtenerPreguntasModulos(){
    $stmt = $this->bd->query("SELECT id FROM pac_preguntas_modulo_5 ORDER BY id ASC");
    $idPreguntas = [];
            
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $idPreguntas[] = $row['id'];
    }
    
    return $idPreguntas;
    }

    //---------- AGREGA PREGUNTAS AL INICIAR ----------//
    public function antecedentesPatologicos($idPaciente, $idPregunta) {
    $sql = "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_5 WHERE id_paciente = ? AND id_pregunta = ?";
    $stmt = $this->bd->prepare($sql);
    $stmt->execute([$idPaciente, $idPregunta]);
    $numero = $stmt->fetchColumn();
            
    if ($numero == 0) {
    $sql_insert = "INSERT INTO pac_respuestas_paciente_modulo_5 (id_pregunta, id_paciente, respuesta) 
    VALUES (?, ?, '')";
    $stmt_insert = $this->bd->prepare($sql_insert);
    $stmt_insert->execute([$idPregunta, $idPaciente]);
    }
    }

    //-------------------- EDITOR DE TEXTO WORD --------------------//
    public function editorTextoAP($idPaciente, $idTema){
    $result = '';

    $stmt = $this->bd->prepare("SELECT
    pac_respuestas_paciente_modulo_5.id AS idRespuesta,
    pac_preguntas_modulo_5.id AS idPregunta,
    pac_temas_modulo_5.tema,
    pac_preguntas_modulo_5.pregunta, 
    pac_preguntas_modulo_5.tipo, 
    COALESCE(pac_respuestas_paciente_modulo_5.respuesta, '') AS respuesta
    FROM pac_temas_modulo_5 
    INNER JOIN pac_preguntas_modulo_5 ON pac_preguntas_modulo_5.id_tema = pac_temas_modulo_5.id 
    LEFT JOIN pac_respuestas_paciente_modulo_5
      ON pac_preguntas_modulo_5.id = pac_respuestas_paciente_modulo_5.id_pregunta
     AND pac_respuestas_paciente_modulo_5.id_paciente = :id_paciente
    WHERE pac_temas_modulo_5.id = :id_tema ORDER BY pac_temas_modulo_5.id ASC");
    $stmt->execute([':id_paciente' => (int)$idPaciente, ':id_tema' => (int)$idTema]);
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $modulos = $this->obtenerNameModulos($idTema);

    if (!empty($preguntas)) {
    $result .= '<table class="table-bordered" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">
    <tr>
    <th class="text-center align-middle" colspan="3">'.$modulos.'</th>
    </tr>
    
    <tr>
    <th class="text-center align-middle" width="20px">#</th>
    <th class="text-start align-middle">Pregunta</th>
    <th class="text-center align-middle">Respuesta</th>
    </tr>';
    
    $num = 1;
    foreach ($preguntas as $pregunta): 
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];

    $result .= '    
    <tr>
    <td class="text-center align-middle">'.$num.'</td>
    <td class="text-start align-middle">'.$preguntaPC.'</td>
    <td class="text-center align-middle">'.$respuesta.'</td>
    </tr>';
    
    $num++;
    endforeach;

    $result .= '</table>';
    }


    return $result;
    }


    //-------------------- EDITOR DE TEXTO WORD --------------------//
    public function editorTextoAPE($idPaciente){
    $result = '';

    $stmt = $this->bd->query("SELECT 
    pac_enfermedades_respuestas_modulo_5.id AS idRespuesta,
    pac_enfermedades_modulo_5.id,
    pac_enfermedades_modulo_5.descripcion AS pregunta,
    pac_enfermedades_respuestas_modulo_5.respuesta,
    pac_enfermedades_respuestas_modulo_5.tipo,
    pac_enfermedades_respuestas_modulo_5.year_diagnostico
    FROM pac_enfermedades_respuestas_modulo_5
    INNER JOIN pac_enfermedades_modulo_5 ON pac_enfermedades_respuestas_modulo_5.id_enfermedad = pac_enfermedades_modulo_5.id
    WHERE pac_enfermedades_respuestas_modulo_5.id_paciente = '".$idPaciente."' 
    ORDER BY pac_enfermedades_modulo_5.id ASC");
    $enfermedades = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    

    if (!empty($enfermedades)) {
    $result .= '<table class="table-bordered" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">
    <tr>
    <th class="text-center align-middle" width="20px">#</th>
    <th class="text-center align-middle">Enfermedad</th>
    <th class="text-center align-middle">¿Ha sido diagnosticado con esta enfermedad en algún momento?</th>
    <th class="text-center align-middle">Tipo de enfermedad</th>
    <th class="text-center align-middle">Año de diagnostico</th>
    </tr>';
    
    $num = 1;
    foreach ($enfermedades as $enfermedad): 
    $preguntaEnf = $enfermedad['pregunta'];
    $respuesta = $enfermedad['respuesta'];
    $tipo = $enfermedad['tipo'];
    $year = $enfermedad['year_diagnostico'];
    $result .= '    
    <tr>
    <td class="text-center align-middle">'.$num.'</td>
    <td class="text-start align-middle">'.$preguntaEnf.'</td>
    <td class="text-center align-middle">'.$respuesta.'</td>
    <td class="text-center align-middle">'.$tipo.'</td>
    <td class="text-center align-middle">'.$year.'</td>        
    </tr>';
    
    $num++;
    endforeach;

    $result .= '</table>';
    }


    return $result;
    }


    //---------- AGREGA PREGUNTAS AL INICIAR ----------//
    public function validarFormato($idPaciente, $idTema) {
    $ocultartb = "";
    
    $stmt = $this->bd->prepare("SELECT
    pac_respuestas_paciente_modulo_5.id AS idRespuesta, 
    pac_temas_modulo_5.tema,
    pac_preguntas_modulo_5.pregunta, 
    pac_preguntas_modulo_5.tipo, 
    pac_respuestas_paciente_modulo_5.respuesta 
    FROM pac_temas_modulo_5 
    INNER JOIN pac_preguntas_modulo_5 ON pac_preguntas_modulo_5.id_tema = pac_temas_modulo_5.id 
    INNER JOIN pac_respuestas_paciente_modulo_5 ON pac_preguntas_modulo_5.id = pac_respuestas_paciente_modulo_5.id_pregunta 
    WHERE pac_respuestas_paciente_modulo_5.id_paciente = '".$idPaciente."' AND pac_temas_modulo_5.id = '".$idTema."' AND pac_preguntas_modulo_5.tipo = 'select' ORDER BY pac_temas_modulo_5.id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($preguntas as $index => $pregunta) :
    $respuesta = $pregunta['respuesta'];
        
    if($respuesta == "No" || $respuesta == ""){
    $ocultartb = "disabled";
    }
    
    endforeach;
    
    return $ocultartb;
    }

    //---------- OBTENER PREGUNTAS DEL MODULO (V1) ----------//
    public function mostrarPreguntasM5V1($idPaciente, $idRol, $idTema){
    $result = '';

    $stmt = $this->bd->prepare("SELECT
    pac_respuestas_paciente_modulo_5.id AS idRespuesta,
    pac_preguntas_modulo_5.id AS idPregunta,
    pac_temas_modulo_5.tema,
    pac_preguntas_modulo_5.pregunta,
    pac_preguntas_modulo_5.tipo,
    COALESCE(pac_respuestas_paciente_modulo_5.respuesta, '') AS respuesta
    FROM pac_temas_modulo_5
    INNER JOIN pac_preguntas_modulo_5 ON pac_preguntas_modulo_5.id_tema = pac_temas_modulo_5.id
    LEFT JOIN pac_respuestas_paciente_modulo_5
      ON pac_preguntas_modulo_5.id = pac_respuestas_paciente_modulo_5.id_pregunta
     AND pac_respuestas_paciente_modulo_5.id_paciente = :id_paciente
    WHERE pac_temas_modulo_5.id = :id_tema ORDER BY pac_temas_modulo_5.id ASC");
    $stmt->execute([':id_paciente' => (int)$idPaciente, ':id_tema' => (int)$idTema]);
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
    if($idRol == "Paciente"){
    $modulos = $this->obtenerModulosM5();

    if (!empty($preguntas)) {
    $num = 1;
    foreach ($preguntas as $index => $pregunta) :
    $temaModulo = $pregunta['tema'];
    $idRespuesta = (int)($pregunta['idRespuesta'] ?? 0);
    $idPregunta = (int)$pregunta['idPregunta'];
    $domKey = $idRespuesta > 0 ? (string)$idRespuesta : 'q' . $idPregunta;
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];
    $tipo = $pregunta['tipo'];
    
    // La primera pregunta se muestra activa
    $claseActiva = ($index == 0) ? 'active' : '';      
     $result .= '<div class="pregunta-container '.$claseActiva.'" data-index="'.$index.'" data-id="'.$domKey.'" data-pregunta="'.$this->escapeHtml($preguntaPC).'" data-pregunta-id="'.$idPregunta.'" data-rol="'.$this->escapeHtml($idRol).'" data-tema="'.(int)$idTema.'">';
                                    
    $result .= '<h4 class="text-success fw-bold"><b>Nombre del tema: '.$this->escapeHtml($temaModulo).'</b></h4>';
    $result .= '<div id="seccion'.$domKey.'" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mt-3 mb-1">';
    // $result .= '<img src="'.RUTA_IMAGES.'/iconos/audio.png" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion'.$idRespuesta.'">';
    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>'.$this->escapeHtml($preguntaPC).'</b></h8>
    </div>';

        
    // Mostrar el tipo de pregunta (select o input)
    if ($tipo == "select") {

        $result .= '
        <div class="d-flex flex-wrap gap-2">
            <div class="text-center d-flex flex-column align-items-center">
                <input type="radio" id="p07-v1-si-'.$domKey.'" class="form-check-input custom-checkbox mb-1" name="p07-v1-'.$idTema.'-'.$domKey.'"
                data-p07-control="true" data-p07-patient="'.(int)$idPaciente.'" data-p07-response="'.(int)$idRespuesta.'" data-p07-question-id="'.$idPregunta.'" data-p07-theme="'.(int)$idTema.'" data-p07-type="select" data-p07-role="'.$this->escapeHtml($idRol).'" data-p07-saved-value="'.$this->escapeHtml($respuesta).'"
                data-value="Si" value="Si" '.($respuesta == 'Si' ? 'checked' : '').'>
                <label class="text-secondary" for="p07-v1-si-'.$domKey.'">Sí</label>
            </div>
            <div class="text-center d-flex flex-column align-items-center">
                <input type="radio" id="p07-v1-no-'.$domKey.'" class="form-check-input custom-checkbox mb-1" name="p07-v1-'.$idTema.'-'.$domKey.'"
                data-p07-control="true" data-p07-patient="'.(int)$idPaciente.'" data-p07-response="'.(int)$idRespuesta.'" data-p07-question-id="'.$idPregunta.'" data-p07-theme="'.(int)$idTema.'" data-p07-type="select" data-p07-role="'.$this->escapeHtml($idRol).'" data-p07-saved-value="'.$this->escapeHtml($respuesta).'"
                data-value="No" value="No" '.($respuesta == 'No' ? 'checked' : '').'>
                <label class="text-secondary" for="p07-v1-no-'.$domKey.'">No</label>
            </div>
        </div>';
        
    } else {
    $result .= '<input type="text" class="form-control" value="' . $this->escapeHtml($respuesta == 0 ? '' : $respuesta) . '" placeholder="Ingresa aquí tu respuesta..." data-p07-control="true" data-p07-patient="'.(int)$idPaciente.'" data-p07-response="'.(int)$idRespuesta.'" data-p07-question-id="'.$idPregunta.'" data-p07-theme="'.(int)$idTema.'" data-p07-type="'. $this->escapeHtml($tipo) .'" data-p07-role="'.$this->escapeHtml($idRol).'" data-p07-saved-value="'.$this->escapeHtml($respuesta == 0 ? '' : $respuesta).'">';
    }
                    
    //----- Botones de navegación -----
    $result .= '<div class="mt-3 d-flex justify-content-between">';
    // Botón "Anterior"
    if ($index == 0) {
    if($idTema > 1){ 
    // Se obtiene el nombre del módulo anterior desde el array
    $prevNombre = $modulos[$idTema - 1];
    $prevId = $idTema - 1;
    $result .= '<button type="button" class="btn btn-success" data-p07-previous="'.(int)$idTema.'"><i data-feather="chevron-left"></i>'.$this->escapeHtml($prevNombre).'</button>';
    } else {
    $result .= '<div></div>';
    }
    } else {
    $result .= '<button type="button" class="btn btn-secondary" data-p07-previous="'.(int)$idTema.'"><i data-feather="chevron-left"></i> Anterior</button>';
    }
    
    // Verificar si la respuesta está vacía para preguntas de tipo "select"
    if ($tipo == "select" && empty($respuesta)) {
    // Si la respuesta está vacía, no mostrar los botones "Siguiente" ni "Ir a siguiente sección"
    $result .= '</div>'; // Cierre contenedor de botones
    $result .= '</div>'; // Cierre contenedor de la pregunta
    $num++;
    continue; // Saltar a la siguiente iteración
    }
    
    // Botón "Siguiente" o "Ir a siguiente sección"
    if ($index < count($preguntas) - 1) {
    if($index == 0 && $tipo == "select" && $respuesta == "No"){
    // Botón para ir a la última sección
    if(isset($modulos[$idTema+1])){
    $nextNombre = $modulos[$idTema+1];
    $nextId = $idTema + 1;
    $result .= '<button type="button" class="btn btn-success" data-p07-next-theme="'.$this->escapeHtml($nextNombre).'" data-p07-next-theme-id="'.(int)$nextId.'">'.$this->escapeHtml($nextNombre).' <i data-feather="chevron-right"></i></button>';
    } else {
    $result .= '<button type="button" class="btn btn-success" data-p07-next-diseases>Padecimientos <i data-feather="heart"></i></button>';
    }
    } else {
    // Botón normal para la siguiente pregunta
    $result .= '<button type="button" class="btn btn-primary" data-p07-next data-p07-theme="'.(int)$idTema.'">Siguiente <i data-feather="chevron-right"></i></button>';
    }
    } else {
    // Al final del módulo, se comprueba si existe un siguiente módulo
    if(isset($modulos[$idTema+1])){
    $nextNombre = $modulos[$idTema+1];
    $nextId = $idTema + 1;
    $result .= '<button type="button" class="btn btn-success" data-p07-next-theme="'.$this->escapeHtml($nextNombre).'" data-p07-next-theme-id="'.(int)$nextId.'">'.$this->escapeHtml($nextNombre).' <i data-feather="chevron-right"></i></button>';
    } else {
    $result .= '<button type="button" class="btn btn-success" data-p07-next-diseases>Padecimientos <i data-feather="heart"></i></button>';
    }
    }

    $result .= '</div>';  // Cierre contenedor de botones
    $result .= '</div>';  // Cierre contenedor de la pregunta
      
    $num++;
    endforeach;
    }

    }else{
        
    
    $modulos = $this->obtenerNameModulos($idTema);
    
    $result .= '    
        
    <div class="alert alert-success text-center" role="alert">
    '.$modulos.'
    </div>
    
    <div class="table-responsive mb-3">
    <table class="table table-striped" id="table_patologico">
    <thead>
    <tr>
    <th class="text-center align-middle" width="40px">#</th>
    <th class="text-start align-middle" >Pregunta</th>
    <th class="text-start align-middle text-center" width="280px">Respuesta</th>
    </tr>
    </thead>
    <tbody>';

    if (!empty($preguntas)) {
    $num = 1;
    foreach ($preguntas as $index2 => $pregunta) {
    $temaModulo = $pregunta['tema'];
    $idRespuesta = $pregunta['idRespuesta'];
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];
    $tipo = $pregunta['tipo'];
        
    // Mostrar el tipo de pregunta (select o input)
    if ($tipo == "select") {
    $elementotd = '<select class="form-select" onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', \''.$tipo.'\',\''.$idRol.'\',1)">
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Si" ' . ($respuesta == 'Si' ? 'selected' : '') . '>Sí</option>
    <option value="No" ' . ($respuesta == 'No' ? 'selected' : '') . '>No</option>
    </select>';
        
    } else {
    
    $deshabilitarInput = $this->validarFormato($idPaciente,$idTema);
    $elementotd = '<input type="text" class="form-control" ' . $deshabilitarInput . ' value="' . ($respuesta == 0 ? '' : $respuesta) . '" placeholder="Ingresa aquí la respuesta..." onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', \''.$tipo.'\',\''.$idRol.'\')">';
    }
        
    $result .= ' <tr> 
    <td class="text-center align-middle">'.$num.'</td>';
        
    $result .= '<td class="text-start align-middle">'.$preguntaPC.'</td>
    <td class="text-start align-middle">'.$elementotd.'</td>
    </tr>';
                    
    $num++;
    }
    }


    $result .= '</tbody>
    </table>
    </div>';

    }

    return $result;
    }




    //---------- OBTENER PREGUNTAS DEL MODULO (V1) ----------//
    public function mostrarPreguntasM5V2($idPaciente, $idRol){
    $result = '';
    
    $stmt = $this->bd->prepare("SELECT
    pac_enfermedades_respuestas_modulo_5.id AS idRespuesta,
    pac_enfermedades_modulo_5.id AS idEnfermedad,
    pac_enfermedades_modulo_5.descripcion AS pregunta,
    COALESCE(pac_enfermedades_respuestas_modulo_5.respuesta, '') AS respuesta,
    COALESCE(pac_enfermedades_respuestas_modulo_5.tipo, '') AS tipo,
    COALESCE(pac_enfermedades_respuestas_modulo_5.year_diagnostico, '') AS year_diagnostico
    FROM pac_enfermedades_modulo_5
    LEFT JOIN pac_enfermedades_respuestas_modulo_5
      ON pac_enfermedades_respuestas_modulo_5.id_enfermedad = pac_enfermedades_modulo_5.id
     AND pac_enfermedades_respuestas_modulo_5.id_paciente = :id_paciente
    ORDER BY pac_enfermedades_modulo_5.id ASC");
    $stmt->execute([':id_paciente' => (int)$idPaciente]);
    $enfermedades = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    if($idRol == "Paciente"){
    
    $model = new PacienteModulosModelo();
    $botonFinalizar = $model->botonFinalizarModulo(5,$idPaciente,$idRol);

    if (!empty($enfermedades)) {
    foreach ($enfermedades as $index2 => $enfermedad) {
    $idRespuesta = (int)($enfermedad['idRespuesta'] ?? 0);
    $idEnfermedad = (int)$enfermedad['idEnfermedad'];
    $domKey = $idRespuesta > 0 ? (string)$idRespuesta : 'e' . $idEnfermedad;
    $preguntaEnf = $enfermedad['pregunta'];
    $respuesta = $enfermedad['respuesta'];
    $tipo = $enfermedad['tipo'];
    $year = $enfermedad['year_diagnostico'];
    
    $claseActiva = ($index2 == 0) ? 'active' : '';
    $tieneDependencias = in_array($preguntaEnf, ['Diabetes Mellitus', 'Enfermedad pulmonar'], true);
    $result .= '<div class="enfermedad-container '.$claseActiva.'" data-index="'.$index2.'" data-id="'.$domKey.'" data-p07-disease-id="'.$idEnfermedad.'" data-enfermedad="'.$this->escapeHtml($preguntaEnf).'" data-p07-has-dependencies="'.($tieneDependencias ? '1' : '0').'" data-rol="'.$this->escapeHtml($idRol).'">';
        
    $result .= '
    <div class="text-secondary fw-bold mb-1">Nombre de la enfermedad:</div>
    <div id="seccion50'.$idRespuesta.'" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-3">
    <h4 class="texto">'.$preguntaEnf.'</h4>
    </div>
    <div class="text-secondary fw-bold mb-1">¿Ha sido diagnosticado con esta enfermedad en algún momento?</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <div class="text-center d-flex flex-column align-items-center">
        <input type="radio" id="p07-v2-si-'.$idRespuesta.'" class="form-check-input custom-checkbox mb-1" name="p07-v2-'.$idRespuesta.'"
        data-p07-control="true" data-p07-patient="'.(int)$idPaciente.'" data-p07-response="'.(int)$idRespuesta.'" data-p07-edicion="1" data-p07-role="'.$this->escapeHtml($idRol).'" data-p07-saved-value="'.$this->escapeHtml($respuesta).'"
        data-value="Si" value="Si" '.($respuesta == 'Si' ? 'checked' : '').'>
        <label class="text-secondary" for="p07-v2-si-'.$idRespuesta.'">Sí</label>
    </div>
    <div class="text-center d-flex flex-column align-items-center">
        <input type="radio" id="p07-v2-no-'.$idRespuesta.'" class="form-check-input custom-checkbox mb-1" name="p07-v2-'.$idRespuesta.'"
        data-p07-control="true" data-p07-patient="'.(int)$idPaciente.'" data-p07-response="'.(int)$idRespuesta.'" data-p07-edicion="1" data-p07-role="'.$this->escapeHtml($idRol).'" data-p07-saved-value="'.$this->escapeHtml($respuesta).'"
        data-value="No" value="No" '.($respuesta == 'No' ? 'checked' : '').'>
        <label class="text-secondary" for="p07-v2-no-'.$idRespuesta.'">No</label>
    </div>
</div>';

    // Solo si ha sido diagnosticado, mostramos preguntas adicionales
    if($respuesta == 'Si'){
    
    if($preguntaEnf == 'Diabetes Mellitus'){
    $result .= '<div class="text-secondary fw-bold mb-1">¿Qué tipo de diabetes?</div>
    <div class="d-flex flex-wrap gap-2 mb-3">
    <div class="text-center d-flex flex-column align-items-center mb-2">
        <input type="radio" id="p07-v2-type1-'.$idRespuesta.'" class="form-check-input custom-checkbox mb-1" name="p07-v2-type-'.$idRespuesta.'"
            data-p07-control="true" data-p07-patient="'.(int)$idPaciente.'" data-p07-response="'.(int)$idRespuesta.'" data-p07-edicion="2" data-p07-role="'.$this->escapeHtml($idRol).'" data-p07-saved-value="'.$this->escapeHtml($tipo).'"
            data-value="Si" value="Tipo 1" '.($tipo === 'Tipo 1' ? 'checked' : '').'>
        <label class="text-secondary" for="p07-v2-type1-'.$idRespuesta.'">Tipo 1</label>
    </div>

    <div class="text-center d-flex flex-column align-items-center mb-2">
        <input type="radio" id="p07-v2-type2-'.$idRespuesta.'" class="form-check-input custom-checkbox mb-1" name="p07-v2-type-'.$idRespuesta.'"
            data-p07-control="true" data-p07-patient="'.(int)$idPaciente.'" data-p07-response="'.(int)$idRespuesta.'" data-p07-edicion="2" data-p07-role="'.$this->escapeHtml($idRol).'" data-p07-saved-value="'.$this->escapeHtml($tipo).'"
            data-value="Si" value="Tipo 2" '.($tipo === 'Tipo 2' ? 'checked' : '').'>
        <label class="text-secondary" for="p07-v2-type2-'.$idRespuesta.'">Tipo 2</label>
    </div>

    <div class="text-center d-flex flex-column align-items-center mb-2">
        <input type="radio" id="p07-v2-gestacional-'.$idRespuesta.'" class="form-check-input custom-checkbox mb-1" name="p07-v2-type-'.$idRespuesta.'"
           data-p07-control="true" data-p07-patient="'.(int)$idPaciente.'" data-p07-response="'.(int)$idRespuesta.'" data-p07-edicion="2" data-p07-role="'.$this->escapeHtml($idRol).'" data-p07-saved-value="'.$this->escapeHtml($tipo).'"
           data-value="Si" value="Gestacional" '.($tipo === 'Gestacional' ? 'checked' : '').'>
        <label class="text-secondary" for="p07-v2-gestacional-'.$idRespuesta.'">Gestacional</label>
    </div>
    </div>';

    } else if ($preguntaEnf == 'Enfermedad pulmonar'){
    $result .= '<div class="text-secondary fw-bold mb-1">¿Qué tipo de enfermedad pulmonar?</div>
    <div class="d-flex flex-wrap gap-2 mb-3">
    <div class="text-center d-flex flex-column align-items-center mb-2">
        <input type="radio" id="p07-v2-epoc-'.$idRespuesta.'" class="form-check-input custom-checkbox mb-1" name="p07-v2-type-'.$idRespuesta.'"
            data-p07-control="true" data-p07-patient="'.(int)$idPaciente.'" data-p07-response="'.(int)$idRespuesta.'" data-p07-edicion="2" data-p07-role="'.$this->escapeHtml($idRol).'" data-p07-saved-value="'.$this->escapeHtml($tipo).'"
            data-value="Si" value="EPOC" '.($tipo === 'EPOC' ? 'checked' : '').'>
        <label class="text-secondary" for="p07-v2-epoc-'.$idRespuesta.'">EPOC</label>
    </div>

    <div class="text-center d-flex flex-column align-items-center mb-2">
        <input type="radio" id="p07-v2-asma-'.$idRespuesta.'" class="form-check-input custom-checkbox mb-1" name="p07-v2-type-'.$idRespuesta.'"
            data-p07-control="true" data-p07-patient="'.(int)$idPaciente.'" data-p07-response="'.(int)$idRespuesta.'" data-p07-edicion="2" data-p07-role="'.$this->escapeHtml($idRol).'" data-p07-saved-value="'.$this->escapeHtml($tipo).'"
            data-value="Si" value="Asma" '.($tipo === 'Asma' ? 'checked' : '').'>
        <label class="text-secondary" for="p07-v2-asma-'.$idRespuesta.'">Asma</label>
    </div>
    </div>';  
    }
    
    $result .= '<div class="text-secondary fw-bold mb-1">¿En qué año fue diagnosticado?:</div>
    <input class="form-control especificar-enfermedad" type="number" data-p07-control="true" data-p07-patient="'.(int)$idPaciente.'" data-p07-response="'.(int)$idRespuesta.'" data-p07-edicion="3" data-p07-role="'.$this->escapeHtml($idRol).'" data-p07-saved-value="'.$this->escapeHtml($year).'" value="'.$this->escapeHtml($year).'" placeholder="Ingresa aquí el año de diagnóstico..." />';
    }
    
    // Navegación entre preguntas
    $result .= '<div class="mt-3 d-flex justify-content-between">';
    
    if ($index2 > 0) {
    $result .= '<button type="button" class="btn btn-secondary" data-p07-v2-previous><i data-feather="chevron-left"></i> Anterior</button>';
    } else {
    $result .= '<button type="button" class="btn btn-secondary" data-p07-v2-previous><i data-feather="chevron-left"></i> Preguntas</button>';
    }
                    

    if ($index2 < count($enfermedades) - 1) {
    $result .= '<button type="button" class="btn btn-primary" data-p07-v2-next>Siguiente <i data-feather="chevron-right"></i></button>';
    } else {
    $result .= $botonFinalizar;
    }

    
    $result .= '</div>'; // fin botones
    $result .= '</div>'; // fin enfermedad-container
    }
    
    } else {
    $result .= '<div class="alert alert-danger text-center" role="alert">No se encontraron preguntas en esta sección.</div>';
    }
    
    } else {

    $result .= '    
        
    <div class="table-responsive mb-3">
    <table class="table table-striped" id="table_enfermedades">
    <thead>
    <tr>
    <th class="text-center align-middle" width="40px">#</th>
    <th class="text-start align-middle" width="300px">Enfermedad</th>
    <th class="text-start align-middle text-center" width="300px">¿Ha sido diagnosticado con esta enfermedad en algún momento?</th>
    <th class="text-start align-middle text-center" width="200px">Tipo</th>
    <th class="text-start align-middle text-center" width="150px">Año de diagnostico</th>
    </tr>
    </thead>
    <tbody>';  

    if (!empty($enfermedades)) {
    $num = 1;
    foreach ($enfermedades as $index2 => $enfermedad) {
    $idRespuesta = $enfermedad['idRespuesta'];
    $preguntaEnf = $enfermedad['pregunta'];
    $respuesta = $enfermedad['respuesta'];
    $tipo = $enfermedad['tipo'];
    $year = $enfermedad['year_diagnostico'];

    $result .= '<tr>';
    $result .= '<td class="text-center align-middle">'.$num.'</td>';
    $result .= '<td class="text-start align-middle">'.$preguntaEnf.'</td>';
    $result .= '<td class="text-start align-middle">
    <select class="form-select tipo-enfermedad mb-3" onchange="editarEnfermedadV2('.$idRespuesta.', this, 1, \''.$idRol.'\',1)">
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Si" '.($respuesta == 'Si' ? 'selected' : '').'>Sí</option>
    <option value="No" '.($respuesta == 'No' ? 'selected' : '').'>No</option>
    </select>
    </td>';

    $result .= '<td class="text-start align-middle">';
    if($respuesta == 'Si'){
     
    if($preguntaEnf == 'Diabetes Mellitus'){
    $result .= '<select class="form-select detalle-enfermedad mb-3" onchange="editarEnfermedadV2('.$idRespuesta.', this, 2, \''.$idRol.'\')">
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Tipo 1" '.($tipo === 'Tipo 1' ? 'selected' : '').'>Tipo 1</option>
    <option value="Tipo 2" '.($tipo === 'Tipo 2' ? 'selected' : '').'>Tipo 2</option>
    <option value="Gestacional" '.($tipo === 'Gestacional' ? 'selected' : '').'>Gestacional</option>
    </select>';
    
    } else if ($preguntaEnf == 'Enfermedad pulmonar'){
    $result .= '<select class="form-select detalle-enfermedad mb-3" onchange="editarEnfermedadV2('.$idRespuesta.', this, 2, \''.$idRol.'\')">
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="EPOC" '.($tipo === 'EPOC' ? 'selected' : '').'>EPOC</option>
    <option value="Asma" '.($tipo === 'Asma' ? 'selected' : '').'>Asma</option>
    </select>';  
    }
    }
    $result .= '</td>
    <td class="text-start align-middle"><input class="form-control especificar-enfermedad" type="number" onchange="editarEnfermedadV2('.$idRespuesta.', this, 3, \''.$idRol.'\')" value="'.$year.'" placeholder="Ingresa aquí el año de diagnóstico..." '.($respuesta !== 'Si' ? 'disabled' : '').'></td>
    </tr>';
    $num++;
    }
    }

    $result .= '</tbody>
    </table>
    </div>';
    }
    
    return $result;
    }
    
 
    public function editarCuestionarioDatoV1($idPaciente, $idRespuesta, $idTema, $detalle){
    if (!$this->obtenerContextoRespuestaV1((int)$idPaciente, (int)$idRespuesta, (int)$idTema)) {
    return ['resultado' => 403, 'mensaje' => 'No tienes autorización para actualizar esta respuesta.'];
    }

    $sql = "UPDATE pac_respuestas_paciente_modulo_5 r
        INNER JOIN pac_preguntas_modulo_5 p ON p.id = r.id_pregunta
        SET r.respuesta = :detalle
        WHERE r.id = :id_respuesta AND r.id_paciente = :id_paciente AND p.id_tema = :id_tema";
    $stmt = $this->bd->prepare($sql);
    $ok = $stmt->execute([
        ':id_respuesta' => (int)$idRespuesta,
        ':id_paciente' => (int)$idPaciente,
        ':id_tema' => (int)$idTema,
        ':detalle' => (string)$detalle,
    ]);
    return $ok
        ? ['resultado' => 200, 'mensaje' => '¡Se actualizó la respuesta correctamente!']
        : ['resultado' => 422, 'mensaje' => 'No se pudo actualizar la respuesta.'];
    }
    
    public function editarCuestionarioSelectV1($idPaciente, $idRespuesta, $idTema) {
    // Actualizar la respuesta seleccionada a 'No'
    if (!$this->obtenerContextoRespuestaV1((int)$idPaciente, (int)$idRespuesta, (int)$idTema)) {
    return ['resultado' => 403, 'mensaje' => 'No tienes autorización para actualizar esta respuesta.'];
    }

    $sql1 = "UPDATE pac_respuestas_paciente_modulo_5 r
    INNER JOIN pac_preguntas_modulo_5 p ON p.id = r.id_pregunta
    SET r.respuesta = 'No'
    WHERE r.id = :id_respuesta AND r.id_paciente = :id_paciente AND p.id_tema = :id_tema";
    $stmt1 = $this->bd->prepare($sql1);
    $stmt1->bindParam(':id_respuesta', $idRespuesta);
    $stmt1->bindParam(':id_paciente', $idPaciente);
    $stmt1->bindParam(':id_tema', $idTema);
    
    // Vaciar respuestas de las demás preguntas del mismo módulo, excepto la que se respondió ('No')
    $sql2 = "UPDATE pac_respuestas_paciente_modulo_5 
    SET respuesta = '' WHERE id_pregunta IN (SELECT id FROM pac_preguntas_modulo_5 WHERE id_tema = :id_tema)
    AND id_paciente = :id_paciente AND id != :id_respuesta";
    $stmt2 = $this->bd->prepare($sql2);
    $stmt2->bindParam(':id_tema', $idTema);
    $stmt2->bindParam(':id_paciente', $idPaciente);
    $stmt2->bindParam(':id_respuesta', $idRespuesta);
    
    // Iniciar transacción
    $this->bd->beginTransaction();
    
    if ($stmt1->execute()) {
    if ($stmt2->execute()) {
    // Opcional: logging de filas afectadas para pruebas
    $filasAfectadas = $stmt2->rowCount();
    error_log("Filas actualizadas (vacías) en stmt2: $filasAfectadas");
    
    $this->bd->commit();
    return array('resultado' => 200, 'mensaje' => '¡Respuestas actualizadas exitosamente!');
        
    } else {
    $this->bd->rollback();
    return array('resultado' => 401, 'mensaje' => '¡Error al vaciar las demás respuestas (stmt2)!');
    }
        
    } else {
    $this->bd->rollback();
    return array('resultado' => 401, 'mensaje' => '¡Error al actualizar la respuesta seleccionada (stmt1)!');
    }
    }
    
    public function editarCuestionarioV1Modulo($data) {
    $idPaciente = (int)($data['idPaciente'] ?? 0);
    $idRespuesta = (int)($data['idRespuesta'] ?? 0);
    $idPregunta = (int)($data['idPregunta'] ?? 0);
    $idTema = (int)($data['idTema'] ?? 0);
    if ($idPaciente < 1 || $idRespuesta < 0 || $idPregunta < 1 || $idTema < 1 || !isset($data['detalle'], $data['idTipo'])) {
    return ['resultado' => 400, 'mensaje' => 'Datos de respuesta no válidos.'];
    }
    if ($idRespuesta < 1) {
    $question = $this->bd->prepare('SELECT id, tipo FROM pac_preguntas_modulo_5 WHERE id = :idPregunta AND id_tema = :idTema LIMIT 1');
    $question->execute([':idPregunta' => $idPregunta, ':idTema' => $idTema]);
    $questionData = $question->fetch(\PDO::FETCH_ASSOC);
    if (!$questionData || (string)$questionData['tipo'] !== (string)$data['idTipo']) {
        return ['resultado' => 403, 'mensaje' => 'No tienes autorización para actualizar esta respuesta.'];
    }
    $this->antecedentesPatologicos($idPaciente, $idPregunta);
    $lookup = $this->bd->prepare('SELECT id FROM pac_respuestas_paciente_modulo_5 WHERE id_paciente = :idPaciente AND id_pregunta = :idPregunta LIMIT 1');
    $lookup->execute([':idPaciente' => $idPaciente, ':idPregunta' => $idPregunta]);
    $idRespuesta = (int)$lookup->fetchColumn();
    $data['idRespuesta'] = $idRespuesta;
    }
    $contexto = $this->obtenerContextoRespuestaV1($idPaciente, $idRespuesta, $idTema);
    if (!$contexto || $contexto['tipo'] !== $data['idTipo']) {
    return ['resultado' => 403, 'mensaje' => 'No tienes autorización para actualizar esta respuesta.'];
    }
    
    //---------- Verificamos si el tipo es "input" ----------
    if ($data['idTipo'] == 'input') {
    // Llamamos a la función que actualiza el dato y capturamos el resultado
    $resultado = $this->editarCuestionarioDatoV1($data['idPaciente'], $data['idRespuesta'], $data['idTema'], $data['detalle']);
    return $resultado;
    }
    
    //---------- Verificamos si el tipo es "select" ----------
    if ($data['idTipo'] == 'select') {
    
    if ($data['detalle'] == 'No') {
    $resultado = $this->editarCuestionarioSelectV1($data['idPaciente'],$data['idRespuesta'], $data['idTema']);
    } elseif ($data['detalle'] == 'Si') {
    $resultado = $this->editarCuestionarioDatoV1($data['idPaciente'], $data['idRespuesta'], $data['idTema'], $data['detalle']);
    } else {
    return array('resultado' => 400, 'mensaje' => 'Detalle no válido');
    }
            
    }
        
    return $resultado + ['idRespuesta' => $idRespuesta];
    }
    



    public function editarCuestionarioV2Modulo($data) {
    $opcionEdicion = filter_var($data['edicion'] ?? null, FILTER_VALIDATE_INT);
    if (!in_array($opcionEdicion, [1, 2, 3], true) || !isset($data['detalle'])) {
    return ['resultado' => 400, 'mensaje' => 'Datos de actualización no válidos.'];
    }

    $consulta2 = "";
    if($opcionEdicion == 1){
    $consulta = "respuesta";
    if($data['detalle'] == "No"){
    $consulta2 = ",tipo = '', year_diagnostico = ''";
    }
    }else if($opcionEdicion == 2){
    $consulta = "tipo";
    }else if($opcionEdicion == 3){
    $consulta = "year_diagnostico";
    }
    
    $idPaciente = (int)($data['idPaciente'] ?? 0);
    $idRespuesta = (int)($data['idEnfermedad'] ?? 0);
    $idEnfermedadCatalogo = (int)($data['idEnfermedadCatalogo'] ?? 0);
    if ($idRespuesta < 1 && $idEnfermedadCatalogo > 0) {
    $catalog = $this->bd->prepare('SELECT id FROM pac_enfermedades_modulo_5 WHERE id = :id LIMIT 1');
    $catalog->execute([':id' => $idEnfermedadCatalogo]);
    if (!$catalog->fetchColumn()) return ['resultado' => 403, 'mensaje' => 'La enfermedad no pertenece al catálogo autorizado.'];
    $lookup = $this->bd->prepare('SELECT id FROM pac_enfermedades_respuestas_modulo_5 WHERE id_paciente = :idPaciente AND id_enfermedad = :idEnfermedad LIMIT 1');
    $lookup->execute([':idPaciente' => $idPaciente, ':idEnfermedad' => $idEnfermedadCatalogo]);
    $idRespuesta = (int)$lookup->fetchColumn();
    if ($idRespuesta < 1) {
        $insert = $this->bd->prepare("INSERT INTO pac_enfermedades_respuestas_modulo_5 (id_enfermedad, id_paciente, respuesta, tipo, year_diagnostico) VALUES (:idEnfermedad, :idPaciente, '', '', '')");
        $insert->execute([':idEnfermedad' => $idEnfermedadCatalogo, ':idPaciente' => $idPaciente]);
        $idRespuesta = (int)$this->bd->lastInsertId();
    }
    }
    $contexto = $this->obtenerContextoRespuestaV2($idPaciente, $idRespuesta);
    if ($idPaciente < 1 || $idRespuesta < 1 || !$contexto) {
    return ['resultado' => 403, 'mensaje' => 'No tienes autorización para actualizar esta enfermedad.'];
    }

    $sql = "UPDATE pac_enfermedades_respuestas_modulo_5 SET
    $consulta = :detalle $consulta2
    WHERE id = :id_enfermedad AND id_paciente = :id_paciente";
    
    $stmt = $this->bd->prepare($sql);                    
                
    $datos = [
    ':id_enfermedad' => $idRespuesta,
    ':id_paciente' => $idPaciente,
    ':detalle' => (string)$data['detalle']
    ];
            
    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se actualizo la informacion correctamente!');
            
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al actualizar la enfermedad de la lista!');
    }
    }



    //---------- OBTENER DATOS DEL MODULO ----------//
    public function obtenerPreguntasModulosV2(){
    $stmt = $this->bd->query("SELECT id FROM pac_enfermedades_modulo_5 ORDER BY id ASC");
    $idPreguntas = [];
                
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $idPreguntas[] = $row['id'];
    }
        
    return $idPreguntas;
    }


    //---------- AGREGA PREGUNTAS AL INICIAR ----------//
    public function antecedentesPatologicosV2($idPaciente, $idEnfermedad) {
    $sql = "SELECT COUNT(*) FROM pac_enfermedades_respuestas_modulo_5 WHERE id_paciente = ? AND id_enfermedad = ?";
    $stmt = $this->bd->prepare($sql);
    $stmt->execute([$idPaciente, $idEnfermedad]);
    $numero = $stmt->fetchColumn();
                
    if ($numero == 0) {
    $sql_insert = "INSERT INTO pac_enfermedades_respuestas_modulo_5 (id_enfermedad, id_paciente, respuesta, tipo, year_diagnostico) 
    VALUES (?, ?, '', '', '')";
    $stmt_insert = $this->bd->prepare($sql_insert);
    $stmt_insert->execute(params: [$idEnfermedad, $idPaciente]);
    }
    }




}
