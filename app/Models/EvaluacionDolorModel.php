<?php
namespace App\Models;
use App\Config\Database;

class EvaluacionDolorModel{

    private $bd;

    public function __construct(){
    $this->bd = Database::getInstance();
    
    }

public function cuestionarioModulo9($idPaciente) {
    $sql = "SELECT COUNT(*) FROM pc_paciente_evaluacion_dolor WHERE id_paciente = ?";
    $stmt = $this->bd->prepare($sql);
    $stmt->execute([$idPaciente]);
    $numero = $stmt->fetchColumn();

    if ($numero == 0) {
        $sql_insert = "INSERT INTO pc_paciente_evaluacion_dolor (id_paciente) VALUES (?)";
        $stmt_insert = $this->bd->prepare($sql_insert);
        $stmt_insert->execute([$idPaciente]);
    }
}


    public function editorTextoED($idPaciente){
    $result = "";
    $stmt = $this->bd->query("SELECT * FROM pc_paciente_evaluacion_dolor WHERE id_paciente = '".$idPaciente."' ORDER BY id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $result .= '<table class="table-bordered" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">';
 
    if (!empty($preguntas)) {
    $num = 1;
    foreach ($preguntas as $pregunta): 
    $dolor = !empty($pregunta['dolor']) ? $pregunta['dolor'] : 'No';
    $tiempo_dolor = !empty($pregunta['tiempo_dolor']) ? $pregunta['tiempo_dolor'] : 'Sin información';
    $descripcion = !empty($pregunta['descripcion']) ? $pregunta['descripcion'] : 'Sin información';
    $incremento = !empty($pregunta['incremento']) ? $pregunta['incremento'] : 'Sin información';

    $result .= '    
    <tr><td><strong>¿Tiene dolor actualmente?:</strong></td></tr>
    <tr><td>'.$dolor.'</td></tr>

    <tr><td><strong>¿Desde hace cuánto tiempo tiene dolor?:</strong></td></tr>
    <tr><td>'.$tiempo_dolor.'</td></tr>
    
    <tr><td><strong>Describa cómo inició el dolor, características y todo lo relacionado al dolor:</strong></td></tr>
    <tr><td>'.$descripcion.'</td></tr>
    
    <tr><td><strong>¿Se ha incrementado el dolor o permanece igual desde el inicio?:</strong></td></tr>
    <tr><td>'.$incremento.'</td></tr>';
    
    $num++;
    endforeach;
    }else{
    $result .= '    
    <tr>
    <td class="text-center">No se encontro información</td>
    </tr>';
    }
    $result .= '</table>';
    
    return $result;
    }

    
    public function mostrarPreguntasM9($idPaciente, $idRol){
    $result = "";
    $stmt = $this->bd->query("SELECT * FROM pc_paciente_evaluacion_dolor WHERE id_paciente = '".$idPaciente."' ORDER BY id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($preguntas as $index => $pregunta) :
    $idEvaluacion = $pregunta['id'];
    $dolor = $pregunta['dolor'];
    $tiempo_dolor = $pregunta['tiempo_dolor'];
    $descripcion = $pregunta['descripcion'];
    $incremento = $pregunta['incremento'];
    endforeach;


    if($idRol == "Paciente"){
    $model = new PacienteModulosModelo();
    $botonFinalizar = $model->botonFinalizarModulo(9,$idPaciente,$idRol);

    $result .= '<div class="card-header pb-0">
    <div id="seccion111" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-4">
    <img src="'.RUTA_IMAGES.'/iconos/audio.png" onclick="readQuestion()" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion111">
      
    <p class="text-primary fw-bold texto mb-0">
    <b>A continuación, deberás responder las siguientes preguntas con base en la evaluación de dolor previamente realizada.</b>
    Por favor, asegúrate de responder con la mayor claridad y detalle posible para facilitar una mejor comprensión de tu malestar.
    </p>
    </div>
    </div>';
      

    $result .= '<div class="card-body">';

    $result .= '
    <div class="col-12">
        <div class="text-secondary fw-bold mb-1">¿Tiene dolor actualmente?</div>
    
        <div class="d-flex flex-wrap gap-4 mt-2">
            <div class="text-center d-flex flex-column align-items-center">
                <input type="checkbox" class="form-check-input custom-checkbox mb-2" 
                onchange="editarEvaluacionDolor('.$idEvaluacion.', this, 1, \''.$idRol.'\', 1)" 
                data-value="Si" value="Si" '.($dolor == 'Si' ? 'checked' : '').'>
                <h5 class="text-secondary">Sí</h5>
            </div>
    
            <div class="text-center d-flex flex-column align-items-center">
                <input type="checkbox" class="form-check-input custom-checkbox mb-2" 
                onchange="editarEvaluacionDolor('.$idEvaluacion.', this, 1, \''.$idRol.'\', 1)" 
                data-value="No" value="No" '.($dolor == 'No' ? 'checked' : '').'>
                <h5 class="text-secondary">No</h5>
            </div>
        </div>
  
    </div>';
     

    if($dolor == "Si"){
    $result .= '
    <div class="col-12">
    <div class="text-secondary fw-bold mb-1">¿Desde hace cuánto tiempo tiene dolor?:</div>
    <input type="text" onchange="editarEvaluacionDolor('.$idEvaluacion.', this, 2, \''.$idRol.'\')" placeholder="Ingresa aquí la respuesta..." class="form-control mb-3" value="'.$tiempo_dolor.'">
    </div>';

    $result .= '
    <div class="col-12">
    <div class="text-secondary fw-bold mb-1">Describa cómo inició el dolor, características y todo lo relacionado al dolor:</div>
    <textarea class="form-control mb-3" rows="6" placeholder="Ingresa aquí la respuesta..." onchange="editarEvaluacionDolor('.$idEvaluacion.', this, 3, \''.$idRol.'\')">'.$descripcion.'</textarea>
    </div>';

    $result .= '
    <div class="col-12">
    <div class="text-secondary fw-bold mb-1">¿Se ha incrementado el dolor o permanece igual desde el inicio?:</div>
    <input type="text" placeholder="Ingresa aquí la respuesta..." onchange="editarEvaluacionDolor('.$idEvaluacion.', this, 4, \''.$idRol.'\')" class="form-control mb-3" value="'.$incremento.'">
    </div>';
    }

    $result .= '<div class="col-12">
    <div class="mt-3 d-flex justify-content-between">';

    $result .= '<button class="btn btn-success" onclick="seccionEspalda()"><i data-feather="chevron-left"></i> Imagen de Espalda</button>';

    if (!empty($dolor)) {
    $result .= $botonFinalizar;
    }

    $result .= '</div>
    </div>';


    $result .= '</div>';

    }else{

    $result .= '<div class="card-header pb-0">
    <p class="text-primary fw-bold">
    <b>A continuación, deberás responder las siguientes preguntas con base en la evaluación de dolor previamente realizada.</b>
    Por favor, asegúrate de responder con la mayor claridad y detalle posible para facilitar una mejor comprensión de tu malestar.
    </p>
    </div>';
       
    $result .= '<div class="card-body">';
    $result .= '<div class="col-12">
    <div class="text-secondary fw-bold mb-1">¿Tiene dolor actualmente?:</div>
    <select class="form-select mb-3" onchange="editarEvaluacionDolor('.$idEvaluacion.', this, 1, \''.$idRol.'\',1)">
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Si" ' . ($dolor == 'Si' ? 'selected' : '') . '>Si</option>
    <option value="No" ' . ($dolor == 'No' ? 'selected' : '') . '>No</option>
    </select>
    </div>';

    $result .= '
    <div class="col-12">
    <div class="text-secondary fw-bold mb-1">¿Desde hace cuánto tiempo tiene dolor?:</div>
    <input type="text" onchange="editarEvaluacionDolor('.$idEvaluacion.', this, 2, \''.$idRol.'\')" class="form-control mb-3" value="'.$tiempo_dolor.'"
    '.(($dolor == 'Si') ? '' : 'disabled').' placeholder="Ingresa aquí tu respuesta...">
    </div>';

    $result .= '
    <div class="col-12">
    <div class="text-secondary fw-bold mb-1">Describa cómo inició el dolor, características y todo lo relacionado al dolor:</div>
    <textarea class="form-control mb-3" rows="6" onchange="editarEvaluacionDolor('.$idEvaluacion.', this, 3, \''.$idRol.'\')" 
    '.(($dolor == 'Si') ? '' : 'disabled').' placeholder="Ingresa aquí tu respuesta...">'.$descripcion.'</textarea>
    </div>';

    $result .= '
    <div class="col-12">
    <div class="text-secondary fw-bold mb-1">¿Se ha incrementado el dolor o permanece igual desde el inicio?:</div>
    <input type="text" onchange="editarEvaluacionDolor('.$idEvaluacion.', this, 4, \''.$idRol.'\')" class="form-control mb-3" value="'.$incremento.'"
    '.(($dolor == 'Si') ? '' : 'disabled').' placeholder="Ingresa aquí tu respuesta...">
    </div>';
 
    $result .= '</div>';

    }

    return $result;
    }

    public function mostrarPreguntasFrenteM9($idPaciente, $idRol){
    $result = '';

    if($idRol == "Paciente"){
    $result .= '<div class="card-header pb-0">
    <div id="seccion1" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-4">
    <img src="'.RUTA_IMAGES.'/iconos/audio.png" onclick="readQuestion()" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion1">

    <h8 class="text-primary fw-bold texto">
    <b >A continuación, debera de indicar las zonas donde siente malestar utilizando los siguientes colores:</b>
    Rojo: Área con Dolor, Amarillo</span>: Área Dormida y con Dolor, Verde: Área con Molestia al tacto y roce de la ropa, 
    Azul: Área sin Sensibilidad y sin Dolor, Morado : Área con Punzadas y Calambres.
    <br>
    Evite marcar la zona con una "X". En su lugar, cúbrala por completo utilizando el color que corresponda.
    </h8>
    </div>
    </div>';

    $result .= '<div class="card-body">';
    }
 
    $result .= '<div class="row">
    <div class="col-12">
    <div class="row">
    <div class="col-8">
    <h8 class="text-success fw-bold texto">Imagen de frente</h8>
    </div>


    <div class="col-4"> 
    <button class="btn btn-danger float-end" onclick="cleanFrenteImg()">Limpiar <i data-feather="trash-2"></i></button>
    </div>

    </div>
    </div>
    
    <div class="col-12 ">'; 

    if($idRol == "Doctor"){
    $result .= '  
    <select class="form-select mt-3  mb-2" id="colorFrente">
    <option value="#FFFFFF">Selecciona una color...</option>
    <option value="#ff0000">🟥 Dolor (Rojo)</option>
    <option value="#0000ff">🟦 Adormecido (Azul)</option>
    <option value="#00ff00">🟩 Molestia al roce (Verde)</option>
    <option value="#ffff00">🟨 Sin sensibilidad (Amarillo)</option>
    <option value="#ff00ff">🟪 Punzadas y Calambres (Magenta)</option>
    </select>';
    }else{

    $result .= '<div class="d-flex flex-wrap gap-4 mb-3 mt-3 justify-content-center">
    <!-- Rojo -->
    <div class="text-center d-flex flex-column align-items-center">
    <input type="radio" name="colorFrente" id="color-rojo" 
    class="form-check-input custom-checkbox mb-1" data-color="#ff0000" value="#ff0000">
    <h5 class="text-secondary">Dolor</h5>
    </div>

    <!-- Azul -->
    <div class="text-center d-flex flex-column align-items-center">
    <input type="radio" name="colorFrente" id="color-azul"
    class="form-check-input custom-checkbox mb-1" data-color="#0000ff" value="#0000ff">
    <h5 class="text-secondary">Adormecido</h5>
    </div>

    <!-- Verde -->
    <div class="text-center d-flex flex-column align-items-center">
    <input type="radio" name="colorFrente" id="color-verde" class="form-check-input custom-checkbox mb-1" 
    data-color="#00ff00" value="#00ff00">
    <h5 class="text-secondary">Molestia al roce</h5>
    </div>

    <!-- Amarillo -->
    <div class="text-center d-flex flex-column align-items-center">
    <input type="radio" name="colorFrente" id="color-amarillo"
    class="form-check-input custom-checkbox mb-1" data-color="#ffff00"value="#ffff00">
    <h5 class="text-secondary">Sin sensibilidad</h5>
    </div>

    <!-- Magenta -->
    <div class="text-center d-flex flex-column align-items-center">
    <input type="radio"
    name="colorFrente" id="color-magenta" class="form-check-input custom-checkbox mb-1"
    data-color="#ff00ff" value="#ff00ff">
    <h5 class="text-secondary">Punzadas y calambres</h5>
    </div>
    </div>';
    }
    $result .= '</div>

    <div class="col-12 mt-2 mb-3"><canvas id="painFrente" width="1200px" height="1800px"></canvas></div>';
   
    if($idRol == "Paciente"){
    $result .= '<div class="col-12">
    <button class="btn btn-success float-end" onclick="seccionEspalda()">Imagen de Espalda <i data-feather="chevron-right"></i></button>
    </div>';
    }

    $result .= '</div>';

    if($idRol == "Paciente"){
    $result .= '</div>';
    }

    return $result;
    }


    public function mostrarPreguntasEspaldaM9($idPaciente, $idRol){
    $result = '';
    
    if($idRol == "Paciente"){
    $result .= '<div class="card-header pb-0">
    <div id="seccion1" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-4">
    <img src="'.RUTA_IMAGES.'/iconos/audio.png" onclick="readQuestion()" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion1">

    <h8 class="text-primary fw-bold texto">
    <b >A continuación, debera de indicar las zonas donde siente malestar utilizando los siguientes colores:</b>
    Rojo: Área con Dolor, Amarillo</span>: Área Dormida y con Dolor, Verde: Área con Molestia al tacto y roce de la ropa, 
    Azul: Área sin Sensibilidad y sin Dolor, Morado : Área con Punzadas y Calambres.
    <br>
    Evite marcar la zona con una "X". En su lugar, cúbrala por completo utilizando el color que corresponda.
    </h8>
    </div>
    </div>';
    $result .= '<div class="card-body">';
    }

    $result .= '<div class="row">
    <div class="col-12">
    <div class="row">
    <div class="col-8">
    <h8 class="text-success fw-bold texto">Imagen de espalda</h8>
    </div>


    <div class="col-4"> 
    <button class="btn btn-danger float-end" onclick="cleanEspaldaImg()">Limpiar <i data-feather="trash-2"></i></button>
    </div>

    </div>
    </div>
    
    <div class="col-12">';

    if($idRol == "Doctor"){
    $result .= '  
    <select class="form-select mt-3  mb-2" id="colorEspalda">
    <option value="#FFFFFF">Selecciona una color...</option>
    <option value="#ff0000">🟥 Dolor (Rojo)</option>
    <option value="#0000ff">🟦 Adormecido (Azul)</option>
    <option value="#00ff00">🟩 Molestia al roce (Verde)</option>
    <option value="#ffff00">🟨 Sin sensibilidad (Amarillo)</option>
    <option value="#ff00ff">🟪 Punzadas y Calambres (Magenta)</option>
    </select>';
    }else{
    $result .= '<div class="d-flex flex-wrap gap-4 mb-3 mt-3 justify-content-center">
    <!-- Rojo -->
    <div class="text-center d-flex flex-column align-items-center">
    <input type="radio" name="colorEspalda" id="color-rojo" 
    class="form-check-input custom-checkbox mb-1" data-color="#ff0000" value="#ff0000">
    <h5 class="text-secondary">Dolor</h5>
    </div>

    <!-- Azul -->
    <div class="text-center d-flex flex-column align-items-center">
    <input type="radio" name="colorEspalda" id="color-azul"
    class="form-check-input custom-checkbox mb-1" data-color="#0000ff" value="#0000ff">
    <h5 class="text-secondary">Adormecido</h5>
    </div>

    <!-- Verde -->
    <div class="text-center d-flex flex-column align-items-center">
    <input type="radio" name="colorEspalda" id="color-verde" class="form-check-input custom-checkbox mb-1" 
    data-color="#00ff00" value="#00ff00">
    <h5 class="text-secondary">Molestia al roce</h5>
    </div>

    <!-- Amarillo -->
    <div class="text-center d-flex flex-column align-items-center">
    <input type="radio" name="colorEspalda" id="color-amarillo"
    class="form-check-input custom-checkbox mb-1" data-color="#ffff00"value="#ffff00">
    <h5 class="text-secondary">Sin sensibilidad</h5>
    </div>

    <!-- Magenta -->
    <div class="text-center d-flex flex-column align-items-center">
    <input type="radio"
    name="colorEspalda" id="color-magenta" class="form-check-input custom-checkbox mb-1"
    data-color="#ff00ff" value="#ff00ff">
    <h5 class="text-secondary">Punzadas y calambres</h5>
    </div>
    </div>';
    }

    $result .= '</div>';
    
    
    $result .= '<div class="col-12 mt-2 mb-3"><canvas id="painEspalda" width="1200px" height="1800px"></canvas></div>';
        
    if($idRol == "Paciente"){
    $result .= '<div class="col-6">
    <button class="btn btn-success" onclick="seccionFrente()"><i data-feather="chevron-left"></i> Imagen de Frente </button>
    </div>

    <div class="col-6">
    <button class="btn btn-success float-end" onclick="seccionCuestionario()">Cuestionario <i data-feather="chevron-right"></i></button>
    </div>';
    }

    $result .= '</div>';

    if($idRol == "Paciente"){
    $result .= '</div>';
    }

    return $result;
    }

    //---------- CARGAR IMAGEN DE FRENTE ----------
    public function imagenFrenteModulo9($idPaciente, $idSexo){
    // Ruta en el sistema de archivos (para PHP)
    $rutaBaseLocal = $_SERVER['DOCUMENT_ROOT'] . "/public/assets/images/evaluacion-dolor/frente/";
        
    // Ruta pública (para el navegador)
    $rutaBaseUrl = RUTA_IMAGES;
    
    // Nombres de archivos según sexo
    $nombreImagenPaciente = ($idSexo === 'F') ? "Frente_F_{$idPaciente}.png" : "Frente_M_{$idPaciente}.png";
    $nombreImagenDefault = ($idSexo === 'F')  ? "Frente_F.png" : "Frente_M.png";
    
    // Rutas locales (físicas) para trabajar con archivos
    $imagenPacienteLocal = $rutaBaseLocal . $nombreImagenPaciente;
    $imagenDefaultLocal = $rutaBaseLocal . $nombreImagenDefault;
    
    // Ruta URL para el navegador
    $imagenPacienteUrl = $rutaBaseUrl . $nombreImagenPaciente;
    
    // Si la imagen del paciente ya existe, devolver la URL
    if (file_exists($imagenPacienteLocal)) {
    //return $imagenPacienteUrl;
    }else{
    if (copy($imagenDefaultLocal, $imagenPacienteLocal)) {
    //return $imagenPacienteUrl;
    }
    }

    // Si algo falla, retorna error o lanza excepción
    //return 'No se pudo generar la imagen de frente del paciente.';
    }
 
    //---------- CARGAR IMAGEN DE FRENTE ----------
    public function imagenEspaldaModulo9($idPaciente, $idSexo){
    // Ruta en el sistema de archivos (para PHP)
    $rutaBaseLocal = $_SERVER['DOCUMENT_ROOT'] . "/public/assets/images/evaluacion-dolor/espalda/";
            
    // Ruta pública (para el navegador)
    $rutaBaseUrl = RUTA_IMAGES;
        
    // Nombres de archivos según sexo
    $nombreImagenPaciente = ($idSexo === 'F') ? "Espalda_F_{$idPaciente}.png" : "Espalda_M_{$idPaciente}.png";
    $nombreImagenDefault = ($idSexo === 'F')  ? "Espalda_F.png" : "Espalda_M.png";
        
    // Rutas locales (físicas) para trabajar con archivos
    $imagenPacienteLocal = $rutaBaseLocal . $nombreImagenPaciente;
    $imagenDefaultLocal = $rutaBaseLocal . $nombreImagenDefault;
        
    // Ruta URL para el navegador
    $imagenPacienteUrl = $rutaBaseUrl . $nombreImagenPaciente;
        
    // Si la imagen del paciente ya existe, devolver la URL
    if (file_exists($imagenPacienteLocal)) {
    //return $imagenPacienteUrl;
    }else{
    if (copy($imagenDefaultLocal, $imagenPacienteLocal)) {
    //return $imagenPacienteUrl;
    }
    }
    
    // Si algo falla, retorna error o lanza excepción
    //return 'No se pudo generar la imagen de frente del paciente.';
    }   

    public function editarImgFrente($data){
    $idPaciente = $data['idPaciente'];
    $img = $data['imgFrente']; // Se espera que sea base64 (ej: data:image/png;base64,...)
    $sexo = $data['idSexo'];
    
    $rutaBaseLocal = $_SERVER['DOCUMENT_ROOT'] . "/public/assets/images/evaluacion-dolor/frente/";
    $nombreImagenPaciente = ($sexo === 'F') ? "Frente_F_{$idPaciente}.png" : "Frente_M_{$idPaciente}.png";
    $imagenPacienteLocal = $rutaBaseLocal . $nombreImagenPaciente;
    
    // Quitar encabezado base64
    if (strpos($img, 'data:image') === 0) {
    $img = preg_replace('#^data:image/\w+;base64,#i', '', $img);
    }
    
    // Decodificar
    $fileBin = base64_decode($img);
    
    if ($fileBin && file_put_contents($imagenPacienteLocal, $fileBin)) {
    return array('resultado' => 200, 'mensaje' => '¡Se actualizó la imagen correctamente!');
    } else {
    return array('resultado' => 401, 'mensaje' => '¡Error al guardar la imagen!');
    }
    }


    public function editarImgEspalda($data){
    $idPaciente = $data['idPaciente'];
    $img = $data['imgEspalda']; // Se espera que sea base64 (ej: data:image/png;base64,...)
    $sexo = $data['idSexo'];
    
    $rutaBaseLocal = $_SERVER['DOCUMENT_ROOT'] . "/public/assets/images/evaluacion-dolor/espalda/";
    $nombreImagenPaciente = ($sexo === 'F') ? "Espalda_F_{$idPaciente}.png" : "Espalda_M_{$idPaciente}.png";
    $imagenPacienteLocal = $rutaBaseLocal . $nombreImagenPaciente;
    
    // Quitar encabezado base64
    if (strpos($img, 'data:image') === 0) {
    $img = preg_replace('#^data:image/\w+;base64,#i', '', $img);
    }
    
    // Decodificar
    $fileBin = base64_decode($img);
    
    if ($fileBin && file_put_contents($imagenPacienteLocal, $fileBin)) {
    return array('resultado' => 200, 'mensaje' => '¡Se actualizó la imagen correctamente!');
    } else {
    return array('resultado' => 401, 'mensaje' => '¡Error al guardar la imagen!');
    }
    }
    
    public function eliminarImgFrente($data){
    $idPaciente = $data['idPaciente'];
    $sexo = $data['idSexo'];
            
    // Ruta en el sistema de archivos (para PHP)
    $rutaBaseLocal = $_SERVER['DOCUMENT_ROOT'] . "/public/assets/images/evaluacion-dolor/frente/";
            
    // Nombres de archivos según sexo
    $nombreImagenPaciente = ($sexo === 'F') ? "Frente_F_{$idPaciente}.png" : "Frente_M_{$idPaciente}.png";
    $nombreImagenDefault = ($sexo === 'F')  ? "Frente_F.png" : "Frente_M.png";
        
    // Rutas locales (físicas) para trabajar con archivos
    $imagenPacienteLocal = $rutaBaseLocal . $nombreImagenPaciente;
    $imagenDefaultLocal = $rutaBaseLocal . $nombreImagenDefault;
    
    if (file_exists($imagenPacienteLocal)) {
    if (copy($imagenDefaultLocal, $imagenPacienteLocal)){
    return array('resultado' => 200, 'mensaje' => '¡Se actualizó la imagen correctamente!');
    }else{
    return array('resultado' => 401, 'mensaje' => '¡Error al guardar la imagen!');
    }
    
    }else{
    if (copy($imagenDefaultLocal, $imagenPacienteLocal)){
    return array('resultado' => 200, 'mensaje' => '¡Se actualizó la imagen correctamente!');
    }else{
    return array('resultado' => 401, 'mensaje' => '¡Error al guardar la imagen!');
    }  
    }
    
    }

    public function eliminarImgEspalda($data){
        $idPaciente = $data['idPaciente'];
        $sexo = $data['idSexo'];
                
        // Ruta en el sistema de archivos (para PHP)
        $rutaBaseLocal = $_SERVER['DOCUMENT_ROOT'] . "/public/assets/images/evaluacion-dolor/espalda/";
                
        // Nombres de archivos según sexo
        $nombreImagenPaciente = ($sexo === 'F') ? "Espalda_F_{$idPaciente}.png" : "Espalda_M_{$idPaciente}.png";
        $nombreImagenDefault = ($sexo === 'F')  ? "Espalda_F.png" : "Espalda_M.png";
            
        // Rutas locales (físicas) para trabajar con archivos
        $imagenPacienteLocal = $rutaBaseLocal . $nombreImagenPaciente;
        $imagenDefaultLocal = $rutaBaseLocal . $nombreImagenDefault;
        
        if (file_exists($imagenPacienteLocal)) {
        if (copy($imagenDefaultLocal, $imagenPacienteLocal)){
        return array('resultado' => 200, 'mensaje' => '¡Se actualizó la imagen correctamente!');
        }else{
        return array('resultado' => 401, 'mensaje' => '¡Error al guardar la imagen!');
        }
        
        }else{
        if (copy($imagenDefaultLocal, $imagenPacienteLocal)){
        return array('resultado' => 200, 'mensaje' => '¡Se actualizó la imagen correctamente!');
        }else{
        return array('resultado' => 401, 'mensaje' => '¡Error al guardar la imagen!');
        }  
        }
        
        }


    public function editarEvaluacion($data) {
    
    $opcionEdicion = $data['edicion'];
        
    $consulta2 = "";
    if($opcionEdicion == 1){
    $consulta = "dolor";
    if($data['detalle'] == "No"){
    $consulta2 = ",tiempo_dolor = '', descripcion = '', incremento = ''";
    }
    }else if($opcionEdicion == 2){
    $consulta = "tiempo_dolor";
    }else if($opcionEdicion == 3){
    $consulta = "descripcion";
    }else if($opcionEdicion == 4){
    $consulta = "incremento";
    }
            
     $sql = "UPDATE pc_paciente_evaluacion_dolor SET 
    $consulta = :detalle $consulta2 WHERE id = :idEvaluacion";
            
    $stmt = $this->bd->prepare($sql);                    
                        
    $datos = [
    ':idEvaluacion' => $data['idEvaluacion'],
    ':detalle' => $data['detalle']
    ];
                    
    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se actualizo la informacion correctamente!');
                    
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al actualizar la enfermedad de la lista!');
    }
    }

  
    /**
     * P11 safe rendering and persistence helpers.
     * GET requests only read existing rows/files; initialization happens on POST.
     */
    private function p11PatientSex(int $idPaciente): string
    {
        $stmt = $this->bd->prepare('SELECT sexo FROM pc_paciente WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $idPaciente]);
        $sex = (string) ($stmt->fetchColumn() ?: 'M');
        return strtoupper($sex) === 'F' ? 'F' : 'M';
    }

    private function p11StorageRoot(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'evaluacion-dolor';
    }

    private function p11DefaultPath(string $orientation, string $sex): string
    {
        $orientation = $orientation === 'espalda' ? 'espalda' : 'frente';
        $prefix = $orientation === 'espalda' ? 'Espalda_' : 'Frente_';
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR
            . 'images' . DIRECTORY_SEPARATOR . 'evaluacion-dolor' . DIRECTORY_SEPARATOR . $orientation
            . DIRECTORY_SEPARATOR . $prefix . $sex . '.png';
    }

    private function p11PrivatePath(int $idPaciente, string $orientation): string
    {
        $orientation = $orientation === 'espalda' ? 'espalda' : 'frente';
        return $this->p11StorageRoot() . DIRECTORY_SEPARATOR . $orientation . '_' . $idPaciente . '.png';
    }

    /** Return an authorized caller-facing path; authorization is enforced by the controller. */
    public function p11ImagePath(int $idPaciente, string $orientation): ?string
    {
        $orientation = $orientation === 'espalda' ? 'espalda' : ($orientation === 'frente' ? 'frente' : '');
        if ($orientation === '') {
            return null;
        }

        $private = $this->p11PrivatePath($idPaciente, $orientation);
        if (is_file($private)) {
            return $private;
        }

        // Legacy files remain readable through the authorized endpoint for compatibility.
        // They are not copied or created by GET. Their direct public exposure remains a tracked risk.
        $sex = $this->p11PatientSex($idPaciente);
        $prefix = $orientation === 'espalda' ? 'Espalda_' : 'Frente_';
        $legacy = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'evaluacion-dolor' . DIRECTORY_SEPARATOR
            . $orientation . DIRECTORY_SEPARATOR . $prefix . $sex . '_' . $idPaciente . '.png';
        if (is_file($legacy)) {
            return $legacy;
        }

        $default = $this->p11DefaultPath($orientation, $sex);
        return is_file($default) ? $default : null;
    }

    private function p11SavePng(int $idPaciente, string $orientation, string $dataUri): array
    {
        if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=\r\n]+)$/', $dataUri, $matches)) {
            return ['resultado' => 422, 'mensaje' => 'La imagen no tiene un formato válido.'];
        }
        $binary = base64_decode($matches[1], true);
        if ($binary === false || strlen($binary) > 4 * 1024 * 1024) {
            return ['resultado' => 422, 'mensaje' => 'La imagen excede el tamaño permitido.'];
        }
        $imageInfo = @getimagesizefromstring($binary);
        if (!$imageInfo || ($imageInfo[2] ?? null) !== IMAGETYPE_PNG || ($imageInfo[0] ?? 0) < 1 || ($imageInfo[1] ?? 0) < 1) {
            return ['resultado' => 422, 'mensaje' => 'La imagen no pudo validarse.'];
        }

        $root = $this->p11StorageRoot();
        if (!is_dir($root) && !mkdir($root, 0770, true) && !is_dir($root)) {
            return ['resultado' => 500, 'mensaje' => 'No se pudo preparar el almacenamiento seguro.'];
        }
        $target = $this->p11PrivatePath($idPaciente, $orientation);
        $temporary = tempnam($root, 'p11_');
        if ($temporary === false || file_put_contents($temporary, $binary, LOCK_EX) === false || !rename($temporary, $target)) {
            if ($temporary !== false && is_file($temporary)) {
                @unlink($temporary);
            }
            return ['resultado' => 500, 'mensaje' => 'No se pudo guardar la imagen. Inténtalo nuevamente.'];
        }
        return ['resultado' => 200, 'mensaje' => 'Imagen guardada.'];
    }

    public function p11GuardarImagen(int $idPaciente, string $orientation, string $dataUri): array
    {
        return $this->p11SavePng($idPaciente, $orientation, $dataUri);
    }

    public function p11LimpiarImagen(int $idPaciente, string $orientation): array
    {
        $orientation = $orientation === 'espalda' ? 'espalda' : ($orientation === 'frente' ? 'frente' : '');
        if ($orientation === '') {
            return ['resultado' => 422, 'mensaje' => 'Orientación no válida.'];
        }
        $default = $this->p11DefaultPath($orientation, $this->p11PatientSex($idPaciente));
        if (!is_file($default)) {
            return ['resultado' => 500, 'mensaje' => 'La imagen base no está disponible.'];
        }
        $root = $this->p11StorageRoot();
        if (!is_dir($root) && !mkdir($root, 0770, true) && !is_dir($root)) {
            return ['resultado' => 500, 'mensaje' => 'No se pudo preparar el almacenamiento seguro.'];
        }
        $target = $this->p11PrivatePath($idPaciente, $orientation);
        $temporary = tempnam($root, 'p11_');
        if ($temporary === false || !copy($default, $temporary) || !rename($temporary, $target)) {
            if ($temporary !== false && is_file($temporary)) {
                @unlink($temporary);
            }
            return ['resultado' => 500, 'mensaje' => 'No se pudo limpiar el mapa. Inténtalo nuevamente.'];
        }
        return ['resultado' => 200, 'mensaje' => 'Mapa limpio.'];
    }

    private function p11Evaluation(int $idPaciente): ?array
    {
        $stmt = $this->bd->prepare(
            'SELECT id, id_paciente, dolor, tiempo_dolor, descripcion, incremento
             FROM pc_paciente_evaluacion_dolor
             WHERE id_paciente = :id_paciente ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([':id_paciente' => $idPaciente]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function p11Html($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    public function p11MostrarMapa(int $idPaciente, string $role, string $orientation): string
    {
        $orientation = $orientation === 'espalda' ? 'espalda' : 'frente';
        $label = $orientation === 'espalda' ? 'Imagen de espalda' : 'Imagen de frente';
        $name = $orientation === 'espalda' ? 'colorEspalda' : 'colorFrente';
        $canvas = $orientation === 'espalda' ? 'painEspalda' : 'painFrente';
        $next = $orientation === 'espalda' ? 'cuestionario' : 'espalda';
        $previous = $orientation === 'espalda' ? 'frente' : '';
        $html = '<div class="p11-map-content" data-p11-map="' . $orientation . '">';
        $html .= '<div class="p11-section-heading"><div><p class="dashboard-eyebrow">MAPA CORPORAL</p><h2>' . $label . '</h2><p class="p11-muted">Selecciona una sensación y marca la zona correspondiente.</p></div>';
        $html .= '<button type="button" class="btn p11-secondary" data-p11-action="clear-' . $orientation . '"><i data-feather="trash-2" aria-hidden="true"></i><span>Limpiar ' . ($orientation === 'espalda' ? 'espalda' : 'frente') . '</span></button></div>';
        $html .= '<p class="p11-legend-note">Usa una categoría por zona. Las etiquetas conservan el significado vigente del módulo; la correspondencia histórica de azul/amarillo queda documentada como pendiente de confirmación clínica.</p>';
        $colors = [
            ['#ff0000', 'color-rojo-' . $orientation, 'Dolor'],
            ['#0000ff', 'color-azul-' . $orientation, 'Adormecido'],
            ['#00ff00', 'color-verde-' . $orientation, 'Molestia al roce'],
            ['#ffff00', 'color-amarillo-' . $orientation, 'Sin sensibilidad'],
            ['#ff00ff', 'color-magenta-' . $orientation, 'Punzadas y calambres'],
        ];
        $html .= '<fieldset class="p11-colors"><legend>Selecciona el tipo de sensación</legend><div class="p11-color-options">';
        foreach ($colors as [$color, $id, $text]) {
            $html .= '<label class="p11-color-option"><input type="radio" name="' . $name . '" id="' . $id . '" value="' . $color . '" data-color="' . $color . '"><span class="p11-color-swatch" style="--p11-color:' . $color . '" aria-hidden="true"></span><span>' . $text . '</span></label>';
        }
        $html .= '</div></fieldset><div class="p11-canvas-wrap"><canvas id="' . $canvas . '" width="1200" height="1800" data-p11-orientation="' . $orientation . '" aria-label="Mapa corporal: ' . $label . '"></canvas></div>';
        $html .= '<div class="p11-nav">';
        if ($role === 'Paciente' && $previous !== '') {
            $html .= '<button type="button" class="btn p11-secondary" data-p11-nav="frente"><i data-feather="chevron-left" aria-hidden="true"></i><span>Imagen de frente</span></button>';
        }
        if ($role === 'Paciente' && $next !== '') {
            $html .= '<button type="button" class="btn btn-primary" data-p11-nav="' . $next . '"><span>' . ($next === 'cuestionario' ? 'Cuestionario' : 'Imagen de espalda') . '</span><i data-feather="chevron-right" aria-hidden="true"></i></button>';
        }
        $html .= '</div></div>';
        return $html;
    }

    public function p11MostrarCuestionario(int $idPaciente, string $role): string
    {
        $row = $this->p11Evaluation($idPaciente) ?: [];
        $dolor = (string) ($row['dolor'] ?? '');
        $readonly = $role === 'Doctor' ? ' readonly' : '';
        $html = '<div class="p11-questionnaire" data-p11-questionnaire="true">';
        $html .= '<div class="p11-section-heading"><div><p class="dashboard-eyebrow">EVALUACIÓN DEL DOLOR</p><h2>Responde las preguntas</h2><p class="p11-muted">Describe tu dolor con la mayor claridad posible.</p></div></div>';
        $html .= '<div class="p11-question"><h3>¿Tiene dolor actualmente?</h3><div class="p11-choice-grid">';
        foreach (['Si' => 'Sí', 'No' => 'No'] as $value => $text) {
            $checked = $dolor === $value ? ' checked' : '';
            $disabled = $role === 'Doctor' ? ' disabled' : '';
            $html .= '<label class="p11-choice"><input type="radio" name="p11Dolor" value="' . $value . '"' . $checked . $disabled . '><span>' . $text . '</span></label>';
        }
        $html .= '</div></div><div class="p11-dependent-fields"' . ($dolor === 'Si' ? '' : ' hidden') . '>';
        $html .= '<label>¿Desde hace cuánto tiempo tiene dolor?<textarea name="tiempo_dolor" rows="2"' . $readonly . '>' . $this->p11Html($row['tiempo_dolor'] ?? '') . '</textarea></label>';
        $html .= '<label>Describa cómo inició el dolor, sus características y localización.<textarea name="descripcion" rows="4"' . $readonly . '>' . $this->p11Html($row['descripcion'] ?? '') . '</textarea></label>';
        $html .= '<label>¿Se ha incrementado el dolor o permanece igual desde el inicio?<textarea name="incremento" rows="3"' . $readonly . '>' . $this->p11Html($row['incremento'] ?? '') . '</textarea></label></div>';
        if ($role === 'Paciente') {
            $html .= '<div class="p11-question-actions"><button type="button" class="btn p11-secondary" data-p11-nav="espalda"><i data-feather="chevron-left" aria-hidden="true"></i><span>Volver a espalda</span></button><button type="button" class="btn p11-secondary" data-p11-action="save-question"><span>Guardar respuestas</span></button><button type="button" class="btn btn-primary" data-p11-action="finish"><span>Finalizar módulo</span><i data-feather="check" aria-hidden="true"></i></button></div>';
        }
        $html .= '</div>';
        return $html;
    }

    public function p11ActualizarEvaluacion(int $idPaciente, array $changes): array
    {
        $allowed = [1 => 'dolor', 2 => 'tiempo_dolor', 3 => 'descripcion', 4 => 'incremento'];
        $normalized = [];
        foreach ($changes as $field => $value) {
            $field = (int) $field;
            if (!isset($allowed[$field])) {
                return ['resultado' => 422, 'mensaje' => 'Campo de evaluación no válido.'];
            }
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($field === 1) {
                if ($value === 'Sí') $value = 'Si';
                if (!in_array($value, ['Si', 'No'], true)) {
                    return ['resultado' => 422, 'mensaje' => 'Selecciona una respuesta válida.'];
                }
            } elseif (mb_strlen($value) > 4000) {
                return ['resultado' => 422, 'mensaje' => 'La respuesta es demasiado larga.'];
            }
            $normalized[$allowed[$field]] = $value;
        }
        if ($normalized === []) {
            return ['resultado' => 422, 'mensaje' => 'No hay cambios para guardar.'];
        }
        $this->bd->beginTransaction();
        try {
            $row = $this->p11Evaluation($idPaciente);
            if (!$row) {
                $insert = $this->bd->prepare('INSERT INTO pc_paciente_evaluacion_dolor (id_paciente) VALUES (:id_paciente)');
                $insert->execute([':id_paciente' => $idPaciente]);
                $row = $this->p11Evaluation($idPaciente);
            }
            if (!$row) throw new \RuntimeException('evaluation-row');
            if (($normalized['dolor'] ?? null) === 'No') {
                $normalized['tiempo_dolor'] = '';
                $normalized['descripcion'] = '';
                $normalized['incremento'] = '';
            }
            $sets = [];
            $params = [':id' => (int) $row['id'], ':id_paciente' => $idPaciente];
            foreach ($normalized as $column => $value) {
                if ((string) ($row[$column] ?? '') === $value) continue;
                $placeholder = ':v_' . $column;
                $sets[] = $column . ' = ' . $placeholder;
                $params[$placeholder] = $value;
            }
            if ($sets !== []) {
                $sql = 'UPDATE pc_paciente_evaluacion_dolor SET ' . implode(', ', $sets) . ' WHERE id = :id AND id_paciente = :id_paciente';
                $stmt = $this->bd->prepare($sql);
                $stmt->execute($params);
            }
            $this->bd->commit();
            return ['resultado' => 200, 'mensaje' => 'Respuesta guardada.'];
        } catch (\Throwable $exception) {
            if ($this->bd->inTransaction()) $this->bd->rollBack();
            throw $exception;
        }
    }

    public function p11EstaCompleto(int $idPaciente): bool
    {
        $row = $this->p11Evaluation($idPaciente);
        if (!$row) return false;
        if ((string) $row['dolor'] === 'No') return true;
        return (string) $row['dolor'] === 'Si'
            && trim((string) $row['tiempo_dolor']) !== ''
            && trim((string) $row['descripcion']) !== ''
            && trim((string) $row['incremento']) !== '';
    }

    public function p11FinalizarModulo(int $idPaciente): array
    {
        $existing = $this->bd->prepare('SELECT id FROM pac_historia_clinica_finalizar WHERE id_modulo = 9 AND id_paciente = :id LIMIT 1');
        $existing->execute([':id' => $idPaciente]);
        if ($existing->fetchColumn() !== false) return ['resultado' => 200, 'mensaje' => 'El módulo ya estaba finalizado.'];
        if (!$this->p11EstaCompleto($idPaciente)) return ['resultado' => 422, 'mensaje' => 'Completa la evaluación antes de finalizar.'];
        $insert = $this->bd->prepare('INSERT INTO pac_historia_clinica_finalizar (id_modulo, id_paciente) VALUES (9, :id)');
        $insert->execute([':id' => $idPaciente]);
        return ['resultado' => 200, 'mensaje' => 'El módulo se finalizó correctamente.'];
    }
}
