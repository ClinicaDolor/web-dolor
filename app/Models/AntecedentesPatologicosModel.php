<?php
namespace App\Models;
use App\Config\Database;
use App\Models\PacienteModulosModelo;

class AntecedentesPatologicosModel{
private $bd;

public function __construct(){
$this->bd = Database::getInstance();

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

    $stmt = $this->bd->query("SELECT 
    pac_respuestas_paciente_modulo_5.id AS idRespuesta, 
    pac_temas_modulo_5.tema,
    pac_preguntas_modulo_5.pregunta, 
    pac_preguntas_modulo_5.tipo, 
    pac_respuestas_paciente_modulo_5.respuesta 
    FROM pac_temas_modulo_5 
    INNER JOIN pac_preguntas_modulo_5 ON pac_preguntas_modulo_5.id_tema = pac_temas_modulo_5.id 
    INNER JOIN pac_respuestas_paciente_modulo_5 ON pac_preguntas_modulo_5.id = pac_respuestas_paciente_modulo_5.id_pregunta 
    WHERE pac_respuestas_paciente_modulo_5.id_paciente = '".$idPaciente."' AND pac_temas_modulo_5.id = '".$idTema."' ORDER BY pac_temas_modulo_5.id ASC");
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
    
    $stmt = $this->bd->query("SELECT 
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

    $stmt = $this->bd->query("SELECT 
    pac_respuestas_paciente_modulo_5.id AS idRespuesta, 
    pac_temas_modulo_5.tema,
    pac_preguntas_modulo_5.pregunta, 
    pac_preguntas_modulo_5.tipo, 
    pac_respuestas_paciente_modulo_5.respuesta 
    FROM pac_temas_modulo_5 
    INNER JOIN pac_preguntas_modulo_5 ON pac_preguntas_modulo_5.id_tema = pac_temas_modulo_5.id 
    INNER JOIN pac_respuestas_paciente_modulo_5 ON pac_preguntas_modulo_5.id = pac_respuestas_paciente_modulo_5.id_pregunta 
    WHERE pac_respuestas_paciente_modulo_5.id_paciente = '".$idPaciente."' AND pac_temas_modulo_5.id = '".$idTema."' ORDER BY pac_temas_modulo_5.id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
    if($idRol == "Paciente"){
    $modulos = $this->obtenerModulosM5();

    if (!empty($preguntas)) {
    $num = 1;
    foreach ($preguntas as $index => $pregunta) :
    $temaModulo = $pregunta['tema'];
    $idRespuesta = $pregunta['idRespuesta'];
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];
    $tipo = $pregunta['tipo'];
    
    // La primera pregunta se muestra activa
    $claseActiva = ($index == 0) ? 'active' : '';      
    $result .= '<div class="pregunta-container '.$claseActiva.'" data-index="'.$index.'" data-id="'.$num.'" data-pregunta="'.$preguntaPC.'" data-rol="'.$idRol.'" data-tema="'.$idTema.'">';
                                    
    $result .= '<h4 class="text-success fw-bold"><b>Nombre del tema: '.$temaModulo.'</b></h4>';
    $result .= '<div id="seccion'.$idRespuesta.'" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mt-3 mb-1">';
    // $result .= '<img src="'.RUTA_IMAGES.'/iconos/audio.png" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion'.$idRespuesta.'">';
    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>'.$preguntaPC.'</b></h8>
    </div>';

        
    // Mostrar el tipo de pregunta (select o input)
    if ($tipo == "select") {

        $result .= '
        <div class="d-flex flex-wrap gap-2">
            <div class="text-center d-flex flex-column align-items-center">
                <input type="checkbox" class="form-check-input custom-checkbox mb-1"
                onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', \''.$tipo.'\', \''.$idRol.'\', 1)"
                data-value="Si" value="Si" '.($respuesta == 'Si' ? 'checked' : '').'>
                <h5 class="text-secondary">Sí</h5>
            </div>
            <div class="text-center d-flex flex-column align-items-center">
                <input type="checkbox" class="form-check-input custom-checkbox mb-1"
                onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', \''.$tipo.'\', \''.$idRol.'\', 1)"
                data-value="No" value="No" '.($respuesta == 'No' ? 'checked' : '').'>
                <h5 class="text-secondary">No</h5>
            </div>
        </div>';
        
    } else {
    $result .= '<input type="text" class="form-control" value="' . ($respuesta == 0 ? '' : $respuesta) . '" placeholder="Ingresa aquí tu respuesta..." onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', \''.$tipo.'\',\''.$idRol.'\')">';
    }
                    
    //----- Botones de navegación -----
    $result .= '<div class="mt-3 d-flex justify-content-between">';
    // Botón "Anterior"
    if ($index == 0) {
    if($idTema > 1){ 
    // Se obtiene el nombre del módulo anterior desde el array
    $prevNombre = $modulos[$idTema - 1];
    $prevId = $idTema - 1;
    $result .= '<button class="btn btn-success" onclick="anteriorPregunta(' . $idTema . ')"><i data-feather="chevron-left"></i>'.$prevNombre.'</button>';
    } else {
    $result .= '<div></div>';
    }
    } else {
    $result .= '<button class="btn btn-secondary" onclick="anteriorPregunta(' . $idTema . ')"><i data-feather="chevron-left"></i> Anterior</button>';
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
    $result .= '<button class="btn btn-success" onclick="irASiguienteSeccion(\''.$nextNombre.'\','.$nextId.')">'.$nextNombre.' <i data-feather="chevron-right"></i></button>';
    } else {
    $result .= '<button class="btn btn-success" onclick="seccionEnfermedades()">Padecimientos <i data-feather="heart"></i></button>';
    }
    } else {
    // Botón normal para la siguiente pregunta
    $result .= '<button class="btn btn-primary" onclick="siguientePregunta(' . $idTema . ')">Siguiente <i data-feather="chevron-right"></i></button>';
    }
    } else {
    // Al final del módulo, se comprueba si existe un siguiente módulo
    if(isset($modulos[$idTema+1])){
    $nextNombre = $modulos[$idTema+1];
    $nextId = $idTema + 1;
    $result .= '<button class="btn btn-success" onclick="irASiguienteSeccion(\''.$nextNombre.'\','.$nextId.')">'.$nextNombre.' <i data-feather="chevron-right"></i></button>';
    } else {
    $result .= '<button class="btn btn-success" onclick="seccionEnfermedades()">Padecimientos <i data-feather="heart"></i></button>';
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
    
    if($idRol == "Paciente"){
    
    $model = new PacienteModulosModelo();
    $botonFinalizar = $model->botonFinalizarModulo(5,$idPaciente,$idRol);

    if (!empty($enfermedades)) {
    foreach ($enfermedades as $index2 => $enfermedad) {
    $idRespuesta = $enfermedad['idRespuesta'];
    $preguntaEnf = $enfermedad['pregunta'];
    $respuesta = $enfermedad['respuesta'];
    $tipo = $enfermedad['tipo'];
    $year = $enfermedad['year_diagnostico'];
    
    $claseActiva = ($index2 == 0) ? 'active' : '';
    $result .= '<div class="enfermedad-container '.$claseActiva.'" data-index="'.$index2.'" data-id="'.$idRespuesta.'" data-enfermedad="'.$preguntaEnf.'" data-rol="'.$idRol.'">';
        
    $result .= '
    <div class="text-secondary fw-bold mb-1">Nombre de la enfermedad:</div>
    <div id="seccion50'.$idRespuesta.'" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-3">
    <h4 class="texto">'.$preguntaEnf.'</h4>
    </div>
    <div class="text-secondary fw-bold mb-1">¿Ha sido diagnosticado con esta enfermedad en algún momento?</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <div class="text-center d-flex flex-column align-items-center">
        <input type="checkbox" class="form-check-input custom-checkbox mb-1"
        onchange="editarEnfermedadV2('.$idRespuesta.', this, 1, \''.$idRol.'\', 1)"
        data-value="Si" value="Si" '.($respuesta == 'Si' ? 'checked' : '').'>
        <h5 class="text-secondary">Sí</h5>
    </div>
    <div class="text-center d-flex flex-column align-items-center">
        <input type="checkbox" class="form-check-input custom-checkbox mb-1"
        onchange="editarEnfermedadV2('.$idRespuesta.', this, 1, \''.$idRol.'\', 1)"
        data-value="No" value="No" '.($respuesta == 'No' ? 'checked' : '').'>
        <h5 class="text-secondary">No</h5>
    </div>
</div>';

    // Solo si ha sido diagnosticado, mostramos preguntas adicionales
    if($respuesta == 'Si'){
    
    if($preguntaEnf == 'Diabetes Mellitus'){
    $result .= '<div class="text-secondary fw-bold mb-1">¿Qué tipo de diabetes?</div>
    <div class="d-flex flex-wrap gap-2 mb-3">
    <div class="text-center d-flex flex-column align-items-center mb-2">
        <input type="checkbox" class="form-check-input custom-checkbox mb-1"
            onchange="editarEnfermedadV2('.$idRespuesta.', this, 2, \''.$idRol.'\')"
            data-value="Si" value="Tipo 1" '.($tipo === 'Tipo 1' ? 'checked' : '').'>
        <h5 class="text-secondary">Tipo 1</h5>
    </div>

    <div class="text-center d-flex flex-column align-items-center mb-2">
        <input type="checkbox" class="form-check-input custom-checkbox mb-1"
            onchange="editarEnfermedadV2('.$idRespuesta.', this, 2, \''.$idRol.'\')"
            data-value="Si" value="Tipo 2" '.($tipo === 'Tipo 2' ? 'checked' : '').'>
        <h5 class="text-secondary">Tipo 2</h5>
    </div>

    <div class="text-center d-flex flex-column align-items-center mb-2">
        <input type="checkbox" class="form-check-input custom-checkbox mb-1"
            onchange="editarEnfermedadV2('.$idRespuesta.', this, 2, \''.$idRol.'\')"
           data-value="Si" value="Gestacional" '.($tipo === 'Gestacional' ? 'checked' : '').'>
        <h5 class="text-secondary">Gestacional</h5>
    </div>
    </div>';

    } else if ($preguntaEnf == 'Enfermedad pulmonar'){
    $result .= '<div class="text-secondary fw-bold mb-1">¿Qué tipo de enfermedad pulmonar?</div>
    <div class="d-flex flex-wrap gap-2 mb-3">
    <div class="text-center d-flex flex-column align-items-center mb-2">
        <input type="checkbox" class="form-check-input custom-checkbox mb-1"
            onchange="editarEnfermedadV2('.$idRespuesta.', this, 2, \''.$idRol.'\')"
            data-value="Si" value="EPOC" '.($tipo === 'EPOC' ? 'checked' : '').'>
        <h5 class="text-secondary">EPOC</h5>
    </div>

    <div class="text-center d-flex flex-column align-items-center mb-2">
        <input type="checkbox" class="form-check-input custom-checkbox mb-1"
            onchange="editarEnfermedadV2('.$idRespuesta.', this, 2, \''.$idRol.'\')"
            data-value="Si" value="Asma" '.($tipo === 'Asma' ? 'checked' : '').'>
        <h5 class="text-secondary">Asma</h5>
    </div>
    </div>';  
    }
    
    $result .= '<div class="text-secondary fw-bold mb-1">¿En qué año fue diagnosticado?:</div>
    <input class="form-control especificar-enfermedad" type="number" onchange="editarEnfermedadV2('.$idRespuesta.', this, 3, \''.$idRol.'\')" value="'.$year.'" placeholder="Ingresa aquí el año de diagnóstico..." />';
    }
    
    // Navegación entre preguntas
    $result .= '<div class="mt-3 d-flex justify-content-between">';
    
    if ($index2 > 0) {
    $result .= '<button class="btn btn-secondary" onclick="anteriorPreguntaV2()"><i data-feather="chevron-left"></i> Anterior</button>';
    } else {
    $result .= '<button class="btn btn-secondary" onclick="seccionPreguntas()"><i data-feather="chevron-left"></i> Preguntas</button>';
    }
                    

    if ($index2 < count($enfermedades) - 1) {
    $result .= '<button class="btn btn-primary" onclick="siguientePreguntaV2()">Siguiente <i data-feather="chevron-right"></i></button>';
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
    
 
    public function editarCuestionarioDatoV1($idRespuesta,$detalle){

    $sql = "UPDATE pac_respuestas_paciente_modulo_5 SET respuesta = :detalle WHERE id = :id_respuesta";
    $stmt = $this->bd->prepare($sql);                    
                            
    $datos = [
    ':id_respuesta' => $idRespuesta,
    ':detalle' => $detalle
    ];
                        
     if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se actualizo la respuesta correctamente!');
                        
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al actualizar la respuesta!');
    }
        
    }
    
    public function editarCuestionarioSelectV1($idPaciente, $idRespuesta, $idTema) {
    // Actualizar la respuesta seleccionada a 'No'
    $sql1 = "UPDATE pac_respuestas_paciente_modulo_5 SET respuesta = 'No' WHERE id = :id_respuesta";
    $stmt1 = $this->bd->prepare($sql1);
    $stmt1->bindParam(':id_respuesta', $idRespuesta);
    
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
    
    //---------- Verificamos si el tipo es "input" ----------
    if ($data['idTipo'] == 'input') {
    // Llamamos a la función que actualiza el dato y capturamos el resultado
    $resultado = $this->editarCuestionarioDatoV1($data['idRespuesta'], $data['detalle']);   
    return $resultado;
    }
    
    //---------- Verificamos si el tipo es "select" ----------
    if ($data['idTipo'] == 'select') {
    
    if ($data['detalle'] == 'No') {
    $resultado = $this->editarCuestionarioSelectV1($data['idPaciente'],$data['idRespuesta'], $data['idTema']);
    } elseif ($data['detalle'] == 'Si') {
    $resultado = $this->editarCuestionarioDatoV1($data['idRespuesta'], $data['detalle']);
    } else {
    return array('resultado' => 400, 'mensaje' => 'Detalle no válido');
    }
            
    }
        
    return $resultado; 
    }
    



    public function editarCuestionarioV2Modulo($data) {
    
    $opcionEdicion = $data['edicion'];

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
    
    $sql = "UPDATE pac_enfermedades_respuestas_modulo_5 SET 
    $consulta = :detalle $consulta2 WHERE id = :id_enfermedad";
    
    $stmt = $this->bd->prepare($sql);                    
                
    $datos = [
    ':id_enfermedad' => $data['idEnfermedad'],
    ':detalle' => $data['detalle']
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