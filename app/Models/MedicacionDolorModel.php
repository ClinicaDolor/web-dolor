<?php
namespace App\Models;
use App\Config\Database;
use App\Models\PacienteModulosModelo;

class MedicacionDolorModel{

    private $bd;

    public function __construct(){
    $this->bd = Database::getInstance();
    
    }


    //---------- OBTENER DATOS DEL MODULO ----------//
    public function obtenerModulosM7(){
    $stmt = $this->bd->query("SELECT id, tema FROM pac_temas_modulo_7 ORDER BY id ASC");
    $modulos = array();
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    // Se indexa el arreglo por id para facilitar el acceso
    $modulos[$row['id']] = $row['tema'];
    }
    return $modulos;
    }


    public function obtenerNameModulos($idTema){
    $stmt = $this->bd->query("SELECT tema FROM pac_temas_modulo_7 WHERE id = '".$idTema."' ORDER BY id ASC");
    $modulos = array();
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $tema = $row['tema'];
    }
    return $tema;
    }

    //---------- OBTENER DATOS DEL MODULO ----------//
    public function obtenerPreguntasModulos(){
    $stmt = $this->bd->query("SELECT id FROM pac_preguntas_modulo_7 ORDER BY id ASC");
    $idPreguntas = [];
                
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $idPreguntas[] = $row['id'];
    }
        
    return $idPreguntas;
    }


    //---------- AGREGA PREGUNTAS AL INICIAR ----------//
    public function medicacionDolorQuest($idPaciente, $idPregunta) {
    $sql = "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_7 WHERE id_paciente = ? AND id_pregunta = ?";
    $stmt = $this->bd->prepare($sql);
    $stmt->execute([$idPaciente, $idPregunta]);
    $numero = $stmt->fetchColumn();
                    
    if ($numero == 0) {
    $sql_insert = "INSERT INTO pac_respuestas_paciente_modulo_7 (id_pregunta, id_paciente) 
    VALUES (?, ?)";
    $stmt_insert = $this->bd->prepare($sql_insert);
    $stmt_insert->execute([$idPregunta, $idPaciente]);
    }
    }

    //-------------------- EDITOR DE TEXTO WORD --------------------//
    public function editorTextoMD($idPaciente, $idTema){
    $result = ''; 

    $stmt = $this->bd->query("SELECT 
    pac_respuestas_paciente_modulo_7.id AS idRespuesta, 
    pac_temas_modulo_7.tema,
    pac_preguntas_modulo_7.pregunta, 
    pac_respuestas_paciente_modulo_7.respuesta, 
    pac_respuestas_paciente_modulo_7.dosis, 
    pac_respuestas_paciente_modulo_7.resultados, 
    pac_respuestas_paciente_modulo_7.descripcion, 
    pac_respuestas_paciente_modulo_7.consumo
    FROM pac_temas_modulo_7 
    INNER JOIN pac_preguntas_modulo_7 ON pac_preguntas_modulo_7.id_tema = pac_temas_modulo_7.id 
    INNER JOIN pac_respuestas_paciente_modulo_7 ON pac_preguntas_modulo_7.id = pac_respuestas_paciente_modulo_7.id_pregunta 
    WHERE pac_respuestas_paciente_modulo_7.id_paciente = '".$idPaciente."' AND pac_temas_modulo_7.id = '".$idTema."' ORDER BY pac_temas_modulo_7.id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $modulos = $this->obtenerNameModulos($idTema);

    if (!empty($preguntas)) {
    $result .= '<table class="table-bordered" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">
    <tr>
    <th class="text-center align-middle" colspan="7">'.$modulos.'</th>
    </tr>
    
    <tr>
    <th class="text-center align-middle" width="40px">#</th>
    <th class="text-start align-middle">Nombre del medicamento</th>
    <th class="text-start align-middle text-center" width="200px">Si/No</th>
    <th class="text-start align-middle text-center">Dosis que te utiliza</th>
    <th class="text-start align-middle text-center" width="280px">Resultados obtenidos</th>
    <th class="text-start align-middle text-center">Descripción de resultados</th>
    <th class="text-start align-middle text-center">Aún lo utiliza</th>
    </tr>';
    
    $num = 1;
    foreach ($preguntas as $pregunta): 
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];
    $dosis = $pregunta['dosis'];
    $resultados = $pregunta['resultados'];
    $descripcion = $pregunta['descripcion'];
    $consumo = $pregunta['consumo'];

    $result .= '    
    <tr>
    <td class="text-center align-middle">'.$num.'</td>
    <td class="text-start align-middle">'.$preguntaPC.'</td>
    <td class="text-center align-middle">'.$respuesta.'</td>
    <td class="text-center align-middle">'.$dosis.'</td>
    <td class="text-center align-middle">'.$resultados.'</td>
    <td class="text-center align-middle">'.$descripcion.'</td>
    <td class="text-center align-middle">'.$consumo.'</td>
    </tr>';
    
    $num++;
    endforeach;

    $result .= '</table>';
    }


    return $result;
    }
    
    //---------- OBTENER PREGUNTAS DEL MODULO (V1) ----------//
    public function mostrarPreguntasM7($idPaciente, $idRol, $idTema){
    $result = '';
        
    $stmt = $this->bd->query("SELECT 
    pac_respuestas_paciente_modulo_7.id AS idRespuesta, 
    pac_temas_modulo_7.tema,
    pac_preguntas_modulo_7.pregunta, 
    pac_respuestas_paciente_modulo_7.respuesta, 
    pac_respuestas_paciente_modulo_7.dosis, 
    pac_respuestas_paciente_modulo_7.resultados, 
    pac_respuestas_paciente_modulo_7.descripcion, 
    pac_respuestas_paciente_modulo_7.consumo
    FROM pac_temas_modulo_7 
    INNER JOIN pac_preguntas_modulo_7 ON pac_preguntas_modulo_7.id_tema = pac_temas_modulo_7.id 
    INNER JOIN pac_respuestas_paciente_modulo_7 ON pac_preguntas_modulo_7.id = pac_respuestas_paciente_modulo_7.id_pregunta 
    WHERE pac_respuestas_paciente_modulo_7.id_paciente = '".$idPaciente."' AND pac_temas_modulo_7.id = '".$idTema."' ORDER BY pac_temas_modulo_7.id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);


    if($idRol == "Paciente"){
    $modulos = $this->obtenerModulosM7();
    $model = new PacienteModulosModelo();
    $botonFinalizar = $model->botonFinalizarModulo(7,$idPaciente,$idRol);

    if (!empty($preguntas)) {
    $num = 1;
    foreach ($preguntas as $index => $pregunta) :
    $temaModulo = $pregunta['tema'];
    $idRespuesta = $pregunta['idRespuesta'];
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];
    $dosis = $pregunta['dosis'];
    $resultados = $pregunta['resultados'];
    $descripcion = $pregunta['descripcion'];
    $consumo = $pregunta['consumo'];

    // La primera pregunta se muestra activa
    $claseActiva = ($index == 0) ? 'active' : '';  
    $result .= '<div class="pregunta-container '.$claseActiva.'" data-index="'.$index.'" data-id="'.$num.'" data-pregunta="'.$preguntaPC.'" data-rol="'.$idRol.'" data-tema="'.$idTema.'">';

    $result .= '<h4 class="text-success fw-bold"><b>Clase de medicamento: '.$temaModulo.'</b></h4>';
    $result .= '<div id="seccion'.$idRespuesta.'" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mt-3 pb-0">';
    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>'.$preguntaPC.'</b></h8>
    </div>';

    $result .= '
    <div class="d-flex flex-wrap gap-2 mb-3">
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 1, \''.$idRol.'\', 1)" 
            data-value="Si" value="Si" '.($respuesta == 'Si' ? 'checked' : '').'>
            <h5 class="text-secondary">Sí</h5>
        </div>
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 1, \''.$idRol.'\', 1)" 
            data-value="No" value="No" '.($respuesta == 'No' ? 'checked' : '').'>
            <h5 class="text-secondary">No</h5>
        </div>
    </div>';

    if($respuesta == 'Si'){
    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>¿Cuál es la dosis que te utiliza?</b></h8>';
    $result .= '<input type="text" class="form-control mb-3" value="' . ($dosis == '' ? '' : $dosis) . '" placeholder="Ingresa aquí la dosis..." onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 2,\''.$idRol.'\')">';

    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>¿Cuales fueron tus resultado obtenidos al utilizar el medicamento?</b></h8>';
    $result .= '
    <div class="d-flex flex-wrap gap-2 mb-3">
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 3, \''.$idRol.'\')" 
            data-value="Si" value="Funciono" '.($resultados == 'Funciono' ? 'checked' : '').'>
            <h5 class="text-secondary">Funcionó</h5>
        </div>
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 3, \''.$idRol.'\')" 
            data-value="Si" value="No funciono" '.($resultados == 'No funciono' ? 'checked' : '').'>
            <h5 class="text-secondary">No funcionó</h5>
        </div>
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 3, \''.$idRol.'\')" 
            data-value="Si" value="Tuve efectos adversos" '.($resultados == 'Tuve efectos adversos' ? 'checked' : '').'>
            <h5 class="text-secondary">Efectos adversos</h5>
        </div>
    </div>';
    
    if($resultados == 'Tuve efectos adversos'){
    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>Descripción de los efectos adversos:</b></h8>';
    $result .= '<input type="text" class="form-control mb-3" value="' . ($descripcion == '' ? '' : $descripcion) . '" placeholder="Ingresa aquí la descripcion..." onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 5,\''.$idRol.'\')">';
    }

    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>¿Sigues utilizando este medicamento actualmente?</b></h8>';
    $result .= '
    <h8 class="text-secondary fw-bold mb-1 texto"><b>¿Sigues utilizando este medicamento actualmente?</b></h8>
    <div class="d-flex flex-wrap gap-2 mb-3 mt-2">
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1"
            onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 4, \''.$idRol.'\')" 
            data-value="Si" value="Si" '.($consumo == 'Si' ? 'checked' : '').'>
            <h5 class="text-secondary">Sí</h5>
        </div>
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1"
            onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 4, \''.$idRol.'\')" 
            data-value="No" value="No" '.($consumo == 'No' ? 'checked' : '').'>
            <h5 class="text-secondary">No</h5>
        </div>
    </div>';
    
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
    if (empty($respuesta)) {
    // Si la respuesta está vacía, no mostrar los botones "Siguiente" ni "Ir a siguiente sección"
    $result .= '</div>'; // Cierre contenedor de botones
    $result .= '</div>'; // Cierre contenedor de la pregunta
    $num++;
    continue; // Saltar a la siguiente iteración
    }
       
    // Botón "Siguiente" o "Ir a siguiente sección"
    if ($index < count($preguntas) - 1) {
    if($index == 0 && $respuesta == "No"){
    // Botón para ir a la última sección
    if(isset($modulos[$idTema+1])){
    $nextNombre = $modulos[$idTema+1];
    $nextId = $idTema + 1;
    $result .= '<button class="btn btn-primary" onclick="siguientePregunta(' . $idTema . ')">Siguiente <i data-feather="chevron-right"></i></button>';
    } else {
    $result .= '<button class="btn btn-success" onclick="irASiguienteSeccion(\''.$nextNombre.'\','.$nextId.')">'.$nextNombre.' <i data-feather="chevron-right"></i></button>';
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
    $result .= $botonFinalizar;
    }
    }
   
    $result .= '</div>';  // Cierre contenedor de botones 

    $result .= '</div>';
    $num++;
    endforeach;
    }

    }else{

    $modulos = $this->obtenerNameModulos($idTema);
    
    $result .= '<div class="alert alert-success text-center" role="alert">
    '.$modulos.'
    </div>';

    $result .= '    
    <div class="table-responsive">
    <table class="table table-striped" id="table_medicamentos_'.$idTema.'">
    <thead>
    <tr>
    <th class="text-center align-middle" width="40px">#</th>
    <th class="text-start align-middle">Nombre del medicamento</th>
    <th class="text-start align-middle text-center" width="200px">Si/No</th>
    <th class="text-start align-middle text-center">Dosis que te utiliza</th>
    <th class="text-start align-middle text-center" width="280px">Resultados obtenidos</th>
    <th class="text-start align-middle text-center">Descripción de resultados</th>
    <th class="text-start align-middle text-center">Aún lo utiliza</th>
    </tr>
    </thead>
    <tbody>';  
    if (!empty($preguntas)) {
    $num = 1;
    foreach ($preguntas as $index => $pregunta) :
    $idRespuesta = $pregunta['idRespuesta'];
    $preguntaPC = $pregunta['pregunta'];
    $respuesta = $pregunta['respuesta'];
    $dosis = $pregunta['dosis'];
    $resultados = $pregunta['resultados'];
    $descripcion = $pregunta['descripcion'];
    $consumo = $pregunta['consumo'];

    $result .= '<tr>';
    $result .= '<td class="text-center align-middle">'.$num.'</td>';
    $result .= '<td class="text-start align-middle">'.$preguntaPC.'</td>';
    
    $result .= '<td class="text-start align-middle">
    <select class="form-select text-center" onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 1,\''.$idRol.'\',1)">
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Si" ' . ($respuesta == 'Si' ? 'selected' : '') . '>Sí</option>
    <option value="No" ' . ($respuesta == 'No' ? 'selected' : '') . '>No</option>
    </select>
    </td>';

    $result .= '<td class="text-start align-middle">
    <input type="text" class="form-control text-center" value="' . ($dosis == '' ? '' : $dosis) . '" placeholder="Ingresa aquí la dosis..." onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 2,\''.$idRol.'\')"
    ' . ($respuesta == 'Si' ? '' : 'disabled') . '>
    </td>';

    $result .= '<td class="text-start align-middle">
    <select class="form-select text-center" onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 3,\''.$idRol.'\')" ' . ($respuesta == 'Si' ? '' : 'disabled') . '>
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Funciono" ' . ($resultados == 'Funciono' ? 'selected' : '') . '>Funciono</option>
    <option value="No funciono" ' . ($resultados == 'No funciono' ? 'selected' : '') . '>No funciono</option>
    <option value="Tuve efectos adversos" ' . ($resultados == 'Tuve efectos adversos' ? 'selected' : '') . '>Tuve efectos adversos</option>
    </select>
    </td>';

    $result .= '<td class="text-start align-middle">
    <input type="text" class="form-control text-center" value="' . ($descripcion == '' ? '' : $descripcion) . '" placeholder="Ingresa aquí la descripción..." onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 5,\''.$idRol.'\')"
    ' . ($resultados == 'Tuve efectos adversos' ? '' : 'disabled') . '>
    </td>';

    $result .= '<td class="text-start align-middle">
    <select class="form-select text-center" onchange="respuestaPreguntaSelect('.$idPaciente.','.$idRespuesta.', this, '.$idTema.', 4,\''.$idRol.'\')" ' . ($respuesta == 'Si' ? '' : 'disabled') . '>
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Si" ' . ($consumo == 'Si' ? 'selected' : '') . '>Sí</option>
    <option value="No" ' . ($consumo == 'No' ? 'selected' : '') . '>No</option>
    </select>
    </td>';

    $result .= '</tr>';
    $num++;
    endforeach;
    }
    $result .= '</tbody>
    </table>
    </div>';

    }

    return $result;
    }



    public function editarCuestionarioM7Paciente($data){
    $resultado = "";
    $opcionEdicion = $data['idTipo'];

    $consulta2 = "";
    if($opcionEdicion == 1){
    $consulta = "respuesta";
    if($data['detalle'] == "No"){
    $consulta2 = ", dosis = '', resultados = '', descripcion = '', consumo = ''";
    }  
    }else if($opcionEdicion == 2){
    $consulta = "dosis";
    }else if($opcionEdicion == 3){
    $consulta = "resultados";

    if($data['detalle'] == "Funciono" || $data['detalle'] == "No funciono"){
    $consulta2 = ", descripcion = ''";
    }

    }else if($opcionEdicion == 4){
    $consulta = "consumo";
    }else if($opcionEdicion == 5){
    $consulta = "descripcion";
    }

    
    $sql = "UPDATE pac_respuestas_paciente_modulo_7 SET 
    $consulta = :detalle $consulta2 WHERE id = :idRespuesta";
    
    $stmt = $this->bd->prepare($sql);                    
                
    $datos = [
    ':idRespuesta' => $data['idRespuesta'],
    ':detalle' => $data['detalle']
    ];
            
    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se actualizo la informacion correctamente!');
            
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al actualizar la enfermedad de la lista!');
    }

    }

}