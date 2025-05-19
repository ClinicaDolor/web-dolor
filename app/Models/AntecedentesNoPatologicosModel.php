<?php
namespace App\Models;
use App\Config\Database;

class AntecedentesNoPatologicosModel{
private $bd;

public function __construct(){
$this->bd = Database::getInstance();

}

    //---------- OBTENER DATOS DEL MODULO ----------//
    public function obtenerModulos(){
    $stmt = $this->bd->query("SELECT id, tema FROM pac_temas_modulo_3 ORDER BY id ASC");
    $modulos = array();
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    // Se indexa el arreglo por id para facilitar el acceso
    $modulos[$row['id']] = $row['tema'];
    }
    return $modulos;
    }

    
    public function obtenerNameModulos($idTema){
    $stmt = $this->bd->query("SELECT tema FROM pac_temas_modulo_3 WHERE id = '".$idTema."' ORDER BY id ASC");
    $modulos = array();
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $tema = $row['tema'];
    }
    return $tema;
    }

    //---------- OBTENER DATOS DEL MODULO ----------//
    public function obtenerPreguntasModulos(){
    $stmt = $this->bd->query("SELECT id FROM pac_preguntas_modulo_3 ORDER BY id ASC");
    $idPreguntas = [];
        
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $idPreguntas[] = $row['id'];
    }

    return $idPreguntas;
    }

    //-------------------- EDITOR DE TEXTO WORD --------------------//
    public function editorTextoANP($idPaciente, $idTema){
    $result = '';

    $stmt = $this->bd->query("SELECT 
    pac_respuestas_paciente_modulo_3.id AS idRespuesta, 
    pac_temas_modulo_3.tema,
    pac_preguntas_modulo_3.pregunta, 
    pac_preguntas_modulo_3.tipo, 
    pac_respuestas_paciente_modulo_3.respuesta 
    FROM pac_temas_modulo_3 
    INNER JOIN pac_preguntas_modulo_3 ON pac_preguntas_modulo_3.id_tema = pac_temas_modulo_3.id 
    INNER JOIN pac_respuestas_paciente_modulo_3 ON pac_preguntas_modulo_3.id = pac_respuestas_paciente_modulo_3.id_pregunta 
    WHERE pac_respuestas_paciente_modulo_3.id_paciente = '".$idPaciente."' AND pac_temas_modulo_3.id = '".$idTema."' ORDER BY pac_temas_modulo_3.id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $modulos = $this->obtenerNameModulos($idTema);


    if (!empty($preguntas)) {
    $result .= '<table class="table-bordered" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">
    <tr>
    <th class="text-center" colspan="3">'.$modulos.'</th>
    </tr>
    
    <tr>
    <th class="text-center" width="20px">#</th>
    <th class="text-start">Pregunta</th>
    <th class="text-center">Respuesta</th>
    </tr>';
    
    $num = 1;
    foreach ($preguntas as $pregunta): 
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];

    $result .= '    
    <tr>
    <td class="text-center">'.$num.'</td>
    <td class="text-start">'.$preguntaPC.'</td>
    <td class="text-center">'.$respuesta.'</td>
    </tr>';
    
    $num++;
    endforeach;

    $result .= '</table>';
    }


    return $result;
    }


    public function mostrarPreguntasM3($idPaciente, $idRol, $idTema){
    $result = '';
      
    $stmt = $this->bd->query("SELECT 
    pac_respuestas_paciente_modulo_3.id AS idRespuesta, 
    pac_temas_modulo_3.tema,
    pac_preguntas_modulo_3.pregunta, 
    pac_preguntas_modulo_3.tipo, 
    pac_respuestas_paciente_modulo_3.respuesta 
    FROM pac_temas_modulo_3 
    INNER JOIN pac_preguntas_modulo_3 ON pac_preguntas_modulo_3.id_tema = pac_temas_modulo_3.id 
    INNER JOIN pac_respuestas_paciente_modulo_3 ON pac_preguntas_modulo_3.id = pac_respuestas_paciente_modulo_3.id_pregunta 
    WHERE pac_respuestas_paciente_modulo_3.id_paciente = '".$idPaciente."' AND pac_temas_modulo_3.id = '".$idTema."' ORDER BY pac_temas_modulo_3.id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
    if($idRol == "Paciente"){
    $modulos = $this->obtenerModulos();

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
                   
    
    $result .= '<h4 class="text-success fw-bold"><b>Nombre de la conducta o hábito: '.$temaModulo.'</b></h4>';
    $result .= '<div id="seccion'.$idRespuesta.'" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mt-3 mb-1">';
    // $result .= '<img src="'.RUTA_IMAGES.'/iconos/audio.png" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion'.$idRespuesta.'">';
    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>'.$preguntaPC.'</b></h8>
    </div>';

        
    // Mostrar el tipo de pregunta (select o input)
    if ($tipo == "select") {
    $result .= '
    <div class="d-flex flex-wrap gap-2">
    <div class="text-center d-flex flex-column align-items-center">
    <input type="checkbox" data-value="Si" class="form-check-input custom-checkbox mb-2" onchange="respuestaPreguntaSelect('.$idPaciente.', '.$idRespuesta.', this, '.$idTema.', \''.$tipo.'\', \''.$idRol.'\', 1)" 
    value="Si" '.($respuesta == 'Si' ? 'checked' : '').'>
    <h5 class="text-secondary">Sí</h5>
    </div>
        
    <div class="text-center d-flex flex-column align-items-center">
    <input type="checkbox" data-value="No" class="form-check-input custom-checkbox mb-2" 
    onchange="respuestaPreguntaSelect('.$idPaciente.', '.$idRespuesta.', this, '.$idTema.', \''.$tipo.'\', \''.$idRol.'\', 1)" value="No" '.($respuesta == 'No' ? 'checked' : '').'>
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
    $result .= '<button class="btn btn-success" onclick="finalizarPreguntas()">Comentarios <i data-feather="message-circle"></i></button>';
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
    $result .= '<button class="btn btn-success" onclick="finalizarPreguntas()">Comentarios <i data-feather="message-circle"></i></button>';
    }
    }

    $result .= '</div>';  // Cierre contenedor de botones
    $result .= '</div>';  // Cierre contenedor de la pregunta
                    
    $num++;
    endforeach;
    }
    
    } else {
    
    $modulos = $this->obtenerNameModulos($idTema);
    
    $result .= '    
    
    <div class="alert alert-success text-center" role="alert">
    '.$modulos.'
    </div>

    <div class="table-responsive mb-3">
    <table class="table table-striped" id="table_enfermedades">
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
    foreach ($preguntas as $index => $pregunta) :
    $idRespuesta = $pregunta['idRespuesta'];
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];
    $tipo = $pregunta['tipo'];

    // Mostrar el tipo de pregunta (select o input)
    if ($tipo == "select") {
    $elementotd = '<select class="form-select text-center" onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', \''.$tipo.'\',\''.$idRol.'\',1)">
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Si" ' . ($respuesta == 'Si' ? 'selected' : '') . '>Sí</option>
    <option value="No" ' . ($respuesta == 'No' ? 'selected' : '') . '>No</option>
    </select>';
    
    } else {

    $deshabilitarInput = $this->validarFormato($idPaciente,$idTema);
    $elementotd = '<input type="text" class="form-control text-center" ' . $deshabilitarInput . ' value="' . ($respuesta == 0 ? '' : $respuesta) . '" placeholder="Ingresa aquí la respuesta..." onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', \''.$tipo.'\',\''.$idRol.'\')">';
    }
    
    $result .= ' <tr> 
    <td class="text-center align-middle">'.$num.'</td>';
    
    $result .= '<td class="text-start align-middle">'.$preguntaPC.'</td>
    <td class="text-start align-middle">'.$elementotd.'</td>
    </tr>';
                
    $num++;
    endforeach;
    }
    
    $result .= '</tbody>
    </table>
    </div>';
    
    }
    
    return $result;
    }


    //---------- AGREGA PREGUNTAS AL INICIAR ----------//
    public function validarFormato($idPaciente, $idTema) {
    $ocultartb = "";

    $stmt = $this->bd->query("SELECT 
    pac_respuestas_paciente_modulo_3.id AS idRespuesta, 
    pac_temas_modulo_3.tema,
    pac_preguntas_modulo_3.pregunta, 
    pac_preguntas_modulo_3.tipo, 
    pac_respuestas_paciente_modulo_3.respuesta 
    FROM pac_temas_modulo_3 
    INNER JOIN pac_preguntas_modulo_3 ON pac_preguntas_modulo_3.id_tema = pac_temas_modulo_3.id 
    INNER JOIN pac_respuestas_paciente_modulo_3 ON pac_preguntas_modulo_3.id = pac_respuestas_paciente_modulo_3.id_pregunta 
    WHERE pac_respuestas_paciente_modulo_3.id_paciente = '".$idPaciente."' AND pac_temas_modulo_3.id = '".$idTema."' AND pac_preguntas_modulo_3.tipo = 'select' ORDER BY pac_temas_modulo_3.id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($preguntas as $index => $pregunta) :
    $respuesta = $pregunta['respuesta'];
    
    if($respuesta == "No" || $respuesta == ""){
    $ocultartb = "disabled";
    }

    endforeach;

    return $ocultartb;
    }

    
    //---------- AGREGA PREGUNTAS AL INICIAR ----------//
    public function antecedentesNoPatologicos($idPaciente, $idPregunta) {
    $sql = "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente = ? AND id_pregunta = ?";
    $stmt = $this->bd->prepare($sql);
    $stmt->execute([$idPaciente, $idPregunta]);
    $numero = $stmt->fetchColumn();
        
    if ($numero == 0) {
    $sql_insert = "INSERT INTO pac_respuestas_paciente_modulo_3 (id_pregunta, id_paciente, respuesta) 
    VALUES (?, ?, '')";
    $stmt_insert = $this->bd->prepare($sql_insert);
    $stmt_insert->execute([$idPregunta, $idPaciente]);
    }
    }

    public function editarCuestionarioDato($idRespuesta,$detalle){

    $sql = "UPDATE pac_respuestas_paciente_modulo_3 SET respuesta = :detalle WHERE id = :id_respuesta";
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


    public function editarCuestionarioSelect($idPaciente,$idRespuesta,$idTema){

    // Si la respuesta es "NO", actualizamos la respuesta a 'NO' y las demás respuestas a vacío
    $sql1 = "UPDATE pac_respuestas_paciente_modulo_3 SET respuesta = 'No'  WHERE id = :id_respuesta";
    $stmt1 = $this->bd->prepare($sql1);
    $stmt1->bindParam(':id_respuesta', $idRespuesta);

    $sql2 = "UPDATE pac_respuestas_paciente_modulo_3 SET respuesta = '' 
    WHERE id_pregunta IN (SELECT id FROM pac_preguntas_modulo_3 WHERE id_tema = :id_tema) AND id_paciente = :id_paciente AND id != :id_respuesta";
    $stmt2 = $this->bd->prepare($sql2);
    $stmt2->bindParam(':id_tema', $idTema);
    $stmt2->bindParam(':id_paciente', $idPaciente);
    $stmt2->bindParam(':id_respuesta', $idRespuesta);

    $this->bd->beginTransaction();
    if ($stmt1->execute()) {
    if ($stmt2->execute()) {
    $this->bd->commit(); // Confirmar cambios
    return array('resultado' => 200, 'mensaje' => '¡Respuestas actualizadas exitosamente!');
        
    } else {
    $this->bd->rollback(); // Deshacer cambios
    return array('resultado' => 401, 'mensaje' => '¡Error al actualizar la respuesta en stmt2!');
    }

    } else {
    $this->bd->rollback(); // Deshacer cambios
    return array('resultado' => 401, 'mensaje' => '¡Error al actualizar la respuesta en stmt1!');
    }
    

    }

    public function editarCuestionarioModulo($data) {
    
    //---------- Verificamos si el tipo es "input" ----------
    if ($data['idTipo'] == 'input') {
    // Llamamos a la función que actualiza el dato y capturamos el resultado
    $resultado = $this->editarCuestionarioDato($data['idRespuesta'], $data['detalle']);   
    return $resultado;
    }

    //---------- Verificamos si el tipo es "select" ----------
    if ($data['idTipo'] == 'select') {

    if ($data['detalle'] == 'No') {
    $resultado = $this->editarCuestionarioSelect($data['idPaciente'],$data['idRespuesta'], $data['idTema']);
    } elseif ($data['detalle'] == 'Si') {
    $resultado = $this->editarCuestionarioDato($data['idRespuesta'], $data['detalle']);
    } else {
    return array('resultado' => 400, 'mensaje' => 'Detalle no válido');
    }
        
    }
    
    return $resultado; 
    }
    
}