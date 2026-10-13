<?php
namespace App\Models;
use App\Config\Database;

class ProcedimientosDolorModel{

    private $bd;

    public function __construct(){
    $this->bd = Database::getInstance();
    
    }

    //---------- OBTENER DATOS DEL MODULO ----------//
    public function obtenerPreguntasModulos(){
    $stmt = $this->bd->query("SELECT id FROM pac_tratamientos_dolor_modulo_8 ORDER BY id ASC");
    $idPreguntas = [];
                
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $idPreguntas[] = $row['id'];
    }
        
    return $idPreguntas;
    }

    //---------- AGREGA PROCEDIMIENTOS AL INICIAR ----------//
    public function procedimientosModulo8($idPaciente, $idEnfermedad) {
    $sql = "SELECT COUNT(*) FROM pac_respuestas_paciente_modulo_8 WHERE id_paciente = ? AND id_tratamiento = ?";
    $stmt = $this->bd->prepare($sql);
    $stmt->execute([$idPaciente, $idEnfermedad]);
    $numero = $stmt->fetchColumn();
                    
    if ($numero == 0) {
    $sql_insert = "INSERT INTO pac_respuestas_paciente_modulo_8 (id_tratamiento, id_paciente) 
    VALUES (?, ?)";
    $stmt_insert = $this->bd->prepare($sql_insert);
    $stmt_insert->execute(params: [$idEnfermedad, $idPaciente]);
    }
    }


    //-------------------- EDITOR DE TEXTO WORD --------------------//
    public function editorTextoPD($idPaciente){
    $result = '';

    $stmt = $this->bd->query("SELECT * FROM pac_procedimientos_dolor_modulo_8 WHERE id_paciente = '".$idPaciente."' ORDER BY id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $result .= '<table class="table-bordered mt-2" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">
    <tr>
    <th class="text-start align-middle">Nombre del procedimiento</th>
    <th class="text-center align-middle" >Fecha</th>
    <th class="text-center align-middle">Resultados obtenidos</th>
    <th class="text-center align-middle">Descripción de resultados</th>
    </tr>';
 
    if (!empty($preguntas)) {
    $num = 1;
    foreach ($preguntas as $pregunta): 
    $procedimiento = $pregunta['procedimiento'];
    $fecha = $pregunta['fecha'];
    $resultados = $pregunta['resultados'];
    $descripcion = $pregunta['descripcion'];

    $result .= '    
    <tr>
    <td class="text-start align-middle">'.$procedimiento.'</td>
    <td class="text-center align-middle">'.$fecha.'</td>
    <td class="text-center align-middle">'.$resultados.'</td>
    <td class="text-center align-middle">'.$descripcion.'</td>
    </tr>';
    
    $num++;
    endforeach;
    }else{
    $result .= '    
    <tr>
    <td class="text-center align-middle" colspan="4"> No se encontro información</td>
    </tr>';
    }
    $result .= '</table>';
    
    return $result;
    }

    
    public function editorTextoTD($idPaciente){
    $result = '';

    $stmt = $this->bd->query("SELECT
    pac_respuestas_paciente_modulo_8.id AS idTratamientoUser,
    pac_tratamientos_dolor_modulo_8.procedimiento,
    pac_respuestas_paciente_modulo_8.utilizo,
    pac_respuestas_paciente_modulo_8.resultado,
    pac_respuestas_paciente_modulo_8.descripcion,
    pac_respuestas_paciente_modulo_8.comentarios
    FROM pac_respuestas_paciente_modulo_8 
    INNER JOIN 
    pac_tratamientos_dolor_modulo_8 ON 
    pac_respuestas_paciente_modulo_8.id_tratamiento = pac_tratamientos_dolor_modulo_8.id
    WHERE pac_respuestas_paciente_modulo_8.id_paciente = '".$idPaciente."' ORDER BY pac_respuestas_paciente_modulo_8.id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $result .= '<table class="table-bordered mt-2" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">
    <tr>
    <th class="text-start align-middle">Nombre del tratamiento</th>
    <th class="text-center align-middle">Recibio el tratamiento</th>
    <th class="text-center align-middle">Resultados obtenidos</th>
    <th class="text-center align-middle">Descripción de resultados</th>
    <th class="text-center align-middle">Comentarios</th>
    </tr>';

    if (!empty($preguntas)) {
    $num = 1;
    foreach ($preguntas as $pregunta): 
    $utilizo = $pregunta['utilizo'];
    $resultado = $pregunta['resultado'];
    $descripcion = $pregunta['descripcion'];
    $comentarios = $pregunta['comentarios'];
    $procedimiento = $pregunta['procedimiento'];

    $result .= '    
    <tr>
    <td class="text-start align-middle">'.$procedimiento.'</td>
    <td class="text-center align-middle">'.$utilizo.'</td>
    <td class="text-center align-middle">'.$resultado.'</td>
    <td class="text-center align-middle">'.$descripcion.'</td>
    <td class="text-center align-middle">'.$comentarios.'</td>
    </tr>';
    
    $num++;
    endforeach;
    }else{
    $result .= '    
    <tr>
    <td></td>
    <td></td>
    <td></td>
    </tr>';
    }
    $result .= '</table>';
    
    return $result;
    }

    //---------- MOSTRAR PREGUNTAS DEL MODULO 8 ----------/
    public function mostrarPreguntasM8($idPaciente,$idRol){
    $result = "";
    $stmt = $this->bd->query("SELECT * FROM pac_procedimientos_dolor_modulo_8 WHERE id_paciente = '".$idPaciente."' ORDER BY id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if($idRol == "Paciente"){
     
    if (!empty($preguntas)) {
    $result .= '
    <div class="card-header pb-0">
    <div class="row">
     
    <div id="seccion101" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-3">
    <img src="'.RUTA_IMAGES.'/iconos/audio.png" onclick="readQuestion()" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion101">
    
    <h8 class="text-primary fw-bold texto">
    <b>A continuación, deberá registrar la fecha en que se realizó cada procedimiento, acompañada de una breve descripción del mismo y de los resultados que experimentó:</b>
    </h8>
    </div>
    
    <div class="col-12">
    <div id="mensaje" class="text-center text-danger mt-2"></div>
    </div>
    
    </div>
    </div>';

    $result .= '<div class="card-body">';

    foreach ($preguntas as $index => $pregunta) :
    $idProcedimiento = $pregunta['id'];
    $procedimiento = $pregunta['procedimiento'];
    $fecha = $pregunta['fecha'];
    $resultados = $pregunta['resultados'];
    $descripcion = $pregunta['descripcion'];

    $claseActiva = ($index == 0) ? 'active' : ''; // Se activará la pregunta guardada en localStorage
    $result .= '<div class="pregunta-container '.$claseActiva.'" data-index="'.$index.'" data-id="'.$idProcedimiento.'" data-enfermedad="'.$procedimiento.'" data-rol="'.$idRol.'">';

    $result .= '
    <div class="col-12">
    <div class="text-secondary fw-bold mb-1">Nombre del procedimiento:</div>
    <div class="row">
    <div class="col-12">
    <input type="text" onchange="editarProcedimientosDolor('.$idProcedimiento.', this, 1, \''.$idRol.'\')" class="form-control mb-3" value="'.$procedimiento.'" placeholder="Escribe aquí el nombre del procedimiento...">
    </div>

    </div>
    </div>';

    $result .= '
    <div class="col-12">
    <div class="text-secondary fw-bold mb-1">Fecha en que se realizo el procedimiento:</div>
    <input type="date" onchange="editarProcedimientosDolor('.$idProcedimiento.', this, 2, \''.$idRol.'\')" class="form-control mb-3" value="'.$fecha.'">
    </div>';
    
    $result .= '<div class="col-12 mb-3">
    <div class="text-secondary fw-bold mt-2 mb-1">Resultados obtenidos:</div>
   <div class="d-flex flex-wrap gap-3">
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="editarProcedimientosDolor('.$idProcedimiento.', this, 3, \''.$idRol.'\')" 
            data-value="Si" value="Funciono" '.($resultados == 'Funciono' ? 'checked' : '').'>
            <h5 class="text-secondary">Funciono</h5>
        </div>
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="editarProcedimientosDolor('.$idProcedimiento.', this, 3, \''.$idRol.'\')" 
            data-value="Si" value="No funciono" '.($resultados == 'No funciono' ? 'checked' : '').'>
            <h5 class="text-secondary">No funciono</h5>
        </div>
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="editarProcedimientosDolor('.$idProcedimiento.', this, 3, \''.$idRol.'\')" 
            data-value="Si" value="Tuve efectos adversos" '.($resultados == 'Tuve efectos adversos' ? 'checked' : '').'>
            <h5 class="text-secondary">Tuve efectos adversos</h5>
        </div>
    </div>
    </div>';

        
    if($resultados == 'Tuve efectos adversos'){
    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>Descripción de los efectos adversos:</b></h8>';
    $result .= '<input type="text" class="form-control mb-3" value="' . ($descripcion == '' ? '' : $descripcion) . '" placeholder="Ingresa aquí la descripcion..." onchange="editarProcedimientosDolor('.$idProcedimiento.', this, 4, \''.$idRol.'\')">';
    }
    

    $result .= '<div class="col-12 mt-5">
    <div class="row">

    <div class="col-6 text-center">
    <img src="'.RUTA_IMAGES.'/iconos/eliminar-icon.png" onclick="eliminarProcedimientosDolor('.$idProcedimiento.', \''.$idRol.'\')" class="img-fluid pointer mb-2" style="width: 35%;">
    <div class="mt-2"><h5 class="text-danger fw-bold">Eliminar procedimiento</h5></div>
    </div>

    <div class="col-6 text-center btnAgregarProcedimiento">
    <img src="'.RUTA_IMAGES.'/iconos/agregar-icon.png" onclick="agregarProcedimientosDolor('.$idPaciente.', \''.$idRol.'\')" class="img-fluid pointer mb-2" style="width: 35%;" style="display: none;">
    <div class="mt-2"><h5 class="text-success fw-bold">Agregar procedimiento</h5></div>
    </div>

    </div>
    </div>';
 
    $result .= '<div class="col-12">
    <div class="mt-3 d-flex justify-content-between">';

    if ($index > 0) {
    $result .= '<button class="btn btn-secondary" onclick="anteriorPregunta()"><i data-feather="chevron-left"></i> Anterior</button>';
    } else {
    $result .= '<div></div>';
    }

    if ($index < count($preguntas) - 1) {
    $result .= '<button class="btn btn-primary" onclick="siguientePregunta()">Siguiente <i data-feather="chevron-right"></i></button>';
    } else {
    $result .= '<button class="btn btn-success" onclick="seccionTratamientos()">Tratamientos <i data-feather="chevron-right"></i></button>';
    }

    $result .= '</div>
    </div>';

    $result .= '</div>';
    endforeach;
    $result .= '</div>';
 
    }else{
  
    $result .= '
    <div class="card-header pb-0">
    <div class="row">

    <div id="seccion100" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-2">
    <img src="'.RUTA_IMAGES.'/iconos/audio.png" onclick="readQuestion()" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion100">

    <h8 class="text-primary fw-bold texto">
    <b>Si usted ha tenido algun procedimiento para controlar el dolor, presione el botón verde para agregarlo. Si no ha sido sometido a ningun tipo de procedimiento, seleccione el boton de "Tratamientos":</b>
    </h8>
    </div>

    <div class="col-12">
    <div id="mensaje" class="text-center text-danger mt-2"></div>
    </div>

    </div>
    </div>

    <div class="card-body">
    <div class="row">
    <div class="col-12 text-center pb-0">
    <img src="'.RUTA_IMAGES.'/iconos/agregar-icon.png" onclick="agregarProcedimientosDolor('.$idPaciente.',\''.$idRol.'\')" class="img-fluid btnLeer pointer" style="max-height: 90%;"></div>
    </div> 
    <div class="col-12 text-end">
    <button class="btn btn-success" onclick="seccionTratamientos()">Tratamientos <i data-feather="chevron-right"></i></button>
    </div>
    
    </div>';
    
    }

    }else{
    $result .= '
    <div class="card-header pb-0">
    <div class="row">
    
    <div class="col-11">
    <h8 class="text-primary fw-bold texto">
    <b>A continuación, deberá registrar la fecha en que se realizó cada procedimiento, acompañada de una breve descripción del mismo y de los resultados que experimentó su paciente:</b>
    </h8>
    </div>
    
    <div class="col-1">
    <button id="btnAgregarCirugia" onclick="agregarProcedimientosDolor('.$idPaciente.', \''.$idRol.'\')" class="btn icon btn-success float-end">
    <i data-feather="plus" width="20"></i>
    </button>
    </div>
    
    <div class="col-12">
    <div id="mensaje" class="text-center text-danger mt-2"></div>
    </div>
    
    </div>
    </div> 
    
    <div class="card-body pb-0">
    <div class="col-12">
    <div class="table-responsive">
    <table class="table table-striped mb-0" id="table_procedimiento">
    <thead>
    <tr>
    <th class="text-start align-middle">Nombre del procedimiento</th>
    <th class="text-center align-middle" width="60px">Fecha</th>
    <th class="text-start align-middle">Resultados obtenidos</th>
    <th class="text-start align-middle">Descripción de resultados</th>
    <th class="text-center align-middle" width="30px"><i data-feather="trash-2"></i></th>
    </tr>
    </thead>
    <tbody>'; 

    foreach ($preguntas as $index => $pregunta) :
    $idProcedimiento = $pregunta['id'];
    $procedimiento = $pregunta['procedimiento'];
    $fecha = $pregunta['fecha'];
    $resultados = $pregunta['resultados'];
    $descripcion = $pregunta['descripcion'];

    $result.= '<tr>';
    $result .= '<td class="text-center align-middle p-2">
    <span class="d-none search-value">'.$procedimiento.'</span>
    <input type="text" onchange="editarProcedimientosDolor('.$idProcedimiento.', this, 1, \''.$idRol.'\')" class="form-control" value="'.$procedimiento.'" placeholder="Escribe aquí el nombre del procedimiento...">
    </td>';

    $result .= '<td class="text-center align-middle p-2">
    <input type="date" onchange="editarProcedimientosDolor('.$idProcedimiento.', this, 2, \''.$idRol.'\')" class="form-control" value="'.($fecha ?? '').'">
    </td>';

    $result .= '<td class="text-center align-middle p-2">
    <select class="form-select" onchange="editarProcedimientosDolor('.$idProcedimiento.', this, 3, \''.$idRol.'\')" >
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Funciono" ' . ($resultados == 'Funciono' ? 'selected' : '') . '>Funciono</option>
    <option value="No funciono" ' . ($resultados == 'No funciono' ? 'selected' : '') . '>No funciono</option>
    <option value="Tuve efectos adversos" ' . ($resultados == 'Tuve efectos adversos' ? 'selected' : '') . '>Tuve efectos adversos</option>
    </select>    </td>';

    $result .= '<td class="text-center align-middle p-2">
    <input type="text" onchange="editarProcedimientosDolor('.$idProcedimiento.', this, 4, \''.$idRol.'\')" placeholder="Ingresa aquí la descripcion..." class="form-control" value="'.($descripcion ?? '').'"
    ' . ($resultados == 'Tuve efectos adversos' ? '' : 'disabled') . '>
    </td>';

    $result.= '<td class="text-center align-middle p-2">
    <i data-feather="trash-2" class="pointer" onclick="eliminarProcedimientosDolor('.$idProcedimiento.', \''.$idRol.'\')"></i>
    </td>';
 
    $result.= '</tr>';

    endforeach;
    $result.= '</tbody>
    </table>
    </div>
    </div>
    </div>';
    }

    return $result;
    }
 
    //---------- MOSTRAR PREGUNTAS DEL MODULO 8 ----------/
    public function mostrarPreguntasM8V2($idPaciente,$idRol){
    $result = "";

    $stmt = $this->bd->query("SELECT
    pac_respuestas_paciente_modulo_8.id AS idTratamientoUser,
    pac_tratamientos_dolor_modulo_8.procedimiento,
    pac_respuestas_paciente_modulo_8.utilizo,
    pac_respuestas_paciente_modulo_8.resultado,
    pac_respuestas_paciente_modulo_8.descripcion,
    pac_respuestas_paciente_modulo_8.comentarios
    FROM pac_respuestas_paciente_modulo_8 
    INNER JOIN 
    pac_tratamientos_dolor_modulo_8 ON 
    pac_respuestas_paciente_modulo_8.id_tratamiento = pac_tratamientos_dolor_modulo_8.id
    WHERE pac_respuestas_paciente_modulo_8.id_paciente = '".$idPaciente."' ORDER BY pac_respuestas_paciente_modulo_8.id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if($idRol == "Paciente"){
    $model = new PacienteModulosModelo();
    $botonFinalizar = $model->botonFinalizarModulo(8,$idPaciente,$idRol);

    $result .= '<div class="card-header pb-0">
    <div class="row">
     
    <div id="seccion102" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-3">
    <img src="'.RUTA_IMAGES.'/iconos/audio.png" onclick="readQuestion()" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion102">
    
    <h8 class="text-primary fw-bold texto">
    <b>A continuación, se le preguntará si ha recibido alguno de los siguientes tratamientos. En caso afirmativo, deberá indicar los resultados que experimentó:</b>    </h8>
    </div>

    <div class="col-12">
    <div id="mensaje" class="text-center text-danger mt-2"></div>
    </div>
    
    </div>
    </div>';

    $result .= '<div class="card-body">';
    $num = 1;
    foreach ($preguntas as $index => $pregunta) :
    $idTratamiento = $pregunta['idTratamientoUser'];
    $utilizo = $pregunta['utilizo'];
    $resultado = $pregunta['resultado'];
    $descripcion = $pregunta['descripcion'];
    $comentarios = $pregunta['comentarios'];
    $procedimiento = $pregunta['procedimiento'];

    $claseActiva = ($index == 0) ? 'active' : ''; // Se activará la pregunta guardada en localStorage
    $result .= '<div class="tratamiento-container '.$claseActiva.'" data-index="'.$index.'" data-id="'.$idTratamiento.'" data-enfermedad="'.$procedimiento.'" data-rol="'.$idRol.'">';

    $result .= '<div class="col-12">

    <div id="seccion200'.$idTratamiento.'" data-autoplay="false" class="col-12 sectionQuestion">
    <div class="text-secondary fw-bold mb-1 texto">¿Usted ha recibido el tratamiento de '.$procedimiento.'?:</div>

        <div class="d-flex flex-wrap gap-2">
            <div class="text-center d-flex flex-column align-items-center">
                <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
                onchange="editarTratamientosDolor('.$idTratamiento.', this, 1, \''.$idRol.'\', 1)" 
                data-value="Si" value="Si" '.($utilizo == 'Si' ? 'checked' : '').'>
                <h5 class="text-secondary">Sí</h5>
            </div>
            <div class="text-center d-flex flex-column align-items-center">
                <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
                onchange="editarTratamientosDolor('.$idTratamiento.', this, 1, \''.$idRol.'\', 1)" 
                data-value="No" value="No" '.($utilizo == 'No' ? 'checked' : '').'>
                <h5 class="text-secondary">No</h5>
            </div>
        </div>
    </div>
    </div>';
    
    if($utilizo == 'Si'){

    $result .= '<div class="col-12 mb-3">
    <div class="text-secondary fw-bold mt-2 mb-1">Resultados obtenidos:</div>
   <div class="d-flex flex-wrap gap-2">
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="editarTratamientosDolor('.$idTratamiento.', this, 2, \''.$idRol.'\')" 
            data-value="Si" value="Funciono" '.($resultado == 'Funciono' ? 'checked' : '').' '.(($utilizo == 'No' || $utilizo == '') ? 'disabled' : '').'>
            <h5 class="text-secondary">Funcionó</h5>
        </div>
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="editarTratamientosDolor('.$idTratamiento.', this, 2, \''.$idRol.'\')" 
            data-value="Si" value="No funciono" '.($resultado == 'No funciono' ? 'checked' : '').' '.(($utilizo == 'No' || $utilizo == '') ? 'disabled' : '').'>
            <h5 class="text-secondary">No funcionó</h5>
        </div>
        <div class="text-center d-flex flex-column align-items-center">
            <input type="checkbox" class="form-check-input custom-checkbox mb-1" 
            onchange="editarTratamientosDolor('.$idTratamiento.', this, 2, \''.$idRol.'\')" 
            data-value="Si" value="Tuve efectos adversos" '.($resultado == 'Tuve efectos adversos' ? 'checked' : '').' '.(($utilizo == 'No' || $utilizo == '') ? 'disabled' : '').'>
            <h5 class="text-secondary">Tuve efectos adversos</h5>
        </div>
    </div>
    </div>';

    if($resultado == 'Tuve efectos adversos'){
    $result .= '<h8 class="text-secondary fw-bold mb-1 texto"><b>Descripción de los efectos adversos:</b></h8>';
    $result .= '<input type="text" class="form-control mb-3" value="' . ($descripcion == '' ? '' : $descripcion) . '" placeholder="Ingresa aquí la descripcion..." onchange="editarTratamientosDolor('.$idTratamiento.', this, 4, \''.$idRol.'\')" >';
    }

    $result .= '
    <div class="col-12 mt-3">
    <div class="text-secondary fw-bold mb-1">Comentarios:</div>
    <input class="form-control" type="text" onchange="editarTratamientosDolor('.$idTratamiento.', this, 3, \''.$idRol.'\')" placeholder="Ingresa aqui tus comentarios..." value="'.$comentarios.'">
    </div>';

    }

    $result .= '<div class="col-12">
    <div class="mt-3 d-flex justify-content-between">';

    if ($index > 0) {
    $result .= '<button class="btn btn-secondary" onclick="anteriorPreguntaV2()"><i data-feather="chevron-left"></i> Anterior</button>';
    } else {
    $result .= '<button class="btn btn-success" onclick="seccionProcedimientos()"><i data-feather="chevron-left"></i> Procedimientos</button>';
    }

    // Verificar si la respuesta está vacía para preguntas de tipo "select"
    if (empty($utilizo)) {
    // Si la respuesta está vacía, no mostrar los botones "Siguiente" ni "Ir a siguiente sección"
    $result .= '</div>'; // Cierre contenedor de botones
    $result .= '</div>'; // Cierre contenedor de la pregunta
    $num++;
    continue; // Saltar a la siguiente iteración
    }

    if ($index < count($preguntas) - 1) {
    $result .= '<button class="btn btn-primary" onclick="siguientePreguntaV2()">Siguiente <i data-feather="chevron-right"></i></button>';
    } else {
    $result .= $botonFinalizar;
    }

    $result .= '</div>
    </div>';


    $result .= '</div>';
    endforeach;
    $result .= '</div>';

    }else{
    $result .= '
    <div class="card-header pb-0">
    <div class="row">
    
    <div class="col-12">
    <h8 class="text-primary fw-bold texto">
    <b>A continuación, se le preguntará si su paciente ha recibido alguno de los siguientes tratamientos. En caso afirmativo, deberá indicar los resultados que experimentó:</b>    </h8>
    </h8>
    </div>
    
    <div class="col-12">
    <div id="mensaje" class="text-center text-danger mt-2"></div>
    </div>
    
    </div>
    </div> 
    
    <div class="card-body">
    <div class="col-12">
    <div class="table-responsive">
    <table class="table table-striped" id="table_tratamientos">
    <thead>
    <tr>
    <th class="text-start align-middle">Nombre del tratamiento</th>
    <th class="text-start align-middle">Recibio el tratamiento</th>
    <th class="text-start align-middle">Resultados obtenidos</th>
    <th class="text-start align-middle">Descripción de resultados</th>
    <th class="text-start align-middle">Comentarios</th>
    </tr>
    </thead>
    <tbody>'; 

    foreach ($preguntas as $index => $pregunta) :
    $idTratamiento = $pregunta['idTratamientoUser'];
    $utilizo = $pregunta['utilizo'];
    $resultado = $pregunta['resultado'];
    $descripcion = $pregunta['descripcion'];
    $comentarios = $pregunta['comentarios'];
    $procedimiento = $pregunta['procedimiento'];
    
    $result.= '<tr>';
    $result .= '<td class="text-start align-middle p-2">
    '.$procedimiento.'
    </td>';

    $result .= '<td class="text-center align-middle p-2">
    <select class="form-select" onchange="editarTratamientosDolor('.$idTratamiento.', this, 1, \''.$idRol.'\', 1)" >
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Si" ' . ($utilizo == 'Si' ? 'selected' : '') . '>Si</option>
    <option value="No" ' . ($utilizo == 'No' ? 'selected' : '') . '>No</option>
    </select>    
    </td>';
 
    $result .= '<td class="text-center align-middle p-2">
    <select class="form-select" onchange="editarTratamientosDolor('.$idTratamiento.', this, 2, \''.$idRol.'\')" '.($utilizo == 'No' || $utilizo == '' ? 'disabled' : '').'>
    <option value="" disabled selected>Selecciona una opción...</option>
    <option value="Funciono" ' . ($resultado == 'Funciono' ? 'selected' : '') . '>Funciono</option>
    <option value="No funciono" ' . ($resultado == 'No funciono' ? 'selected' : '') . '>No funciono</option>
    <option value="Tuve efectos adversos" ' . ($resultado == 'Tuve efectos adversos' ? 'selected' : '') . '>Tuve efectos adversos</option>
    </select>
    </td>';

    $result .= '<td class="text-center align-middle p-2">
    <input type="text" onchange="editarTratamientosDolor('.$idTratamiento.', this, 4, \''.$idRol.'\')" placeholder="Ingresa aquí la descripcion..." class="form-control" value="'.($descripcion ?? '').'"
    ' . ($resultado == 'Tuve efectos adversos' ? '' : 'disabled') . '>
    </td>';

    $result .= '<td class="text-center align-middle p-2">
    <input class="form-control" type="text" onchange="editarTratamientosDolor('.$idTratamiento.', this, 3, \''.$idRol.'\')" placeholder="Ingresa aqui tus comentarios..." value="'.$comentarios.'" '.($utilizo == 'No' || $utilizo == '' ? 'disabled' : '').'>
    </td>';

    $result.= '</tr>';

    endforeach;


    $result.= '</tbody>
    </table>
    </div>
    </div>
    </div>';
    }


    return $result;
    }


    public function agregarProcedimientosPaciente($data){

    $sql = "INSERT INTO pac_procedimientos_dolor_modulo_8 (
    id_paciente
    ) VALUES (
    :id_paciente
    )";
        
    $stmt = $this->bd->prepare($sql);                    
            
    $datos = [
    ':id_paciente' => $data['idPaciente']
    ];
    
    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se agrego el procedimiento correctamente!');
            
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al agregar el procedimiento la lista!');
    }
        
    }
 
    public function editarProcedimientoPaciente($data){
    $opcionEdicion = $data['edicion'];
    $consulta2 = "";

    if($opcionEdicion == 1){
    $consulta = "procedimiento";
    }else if($opcionEdicion == 2){
    $consulta = "fecha";
    }else if($opcionEdicion == 3){
    $consulta = "resultados";

    if($data['detalle'] == "Funciono" || $data['detalle'] == "No funciono"){
    $consulta2 = ", descripcion = ''";
    }
    
    }else if($opcionEdicion == 4){
    $consulta = "descripcion";
    }
        
    $sql = "UPDATE pac_procedimientos_dolor_modulo_8 SET 
    $consulta = :detalle $consulta2 WHERE id = :idProcedimiento";
    
    $stmt = $this->bd->prepare($sql);                    
                    
    $datos = [
    ':idProcedimiento' => $data['idProcedimiento'],
    ':detalle' => $data['detalle']
    ];
                
    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se actualizo la informacion correctamente!');
                
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al actualizar la cirugia de la lista!');
    }
                
    }

    public function eliminarProcedimientoPaciente($data){

    $sql = "DELETE FROM pac_procedimientos_dolor_modulo_8 WHERE id = :idProcedimiento";
    $stmt = $this->bd->prepare($sql);                    
                
    $datos = [
    ':idProcedimiento' => $data['idProcedimiento']
    ];
            
    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se elimino el procedimiento correctamente!');
            
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al eliminar el procedimiento de la lista!');
    }
            
    }

    //---------- SECCION DE PROCEDIMIENTOS ----------
    public function editarTratamientoPaciente($data){
    $opcionEdicion = $data['edicion'];
    
    $consulta2 = "";
   
    if($opcionEdicion == 1){
    $consulta = "utilizo";
    if($data['detalle'] == "No"){
    $consulta2 = ",resultado = '', comentarios = '', descripcion = ''";
    }
    }else if($opcionEdicion == 2){
    $consulta = "resultado";

    if($data['detalle'] == "Funciono" || $data['detalle'] == "No funciono"){
    $consulta2 = ", descripcion = ''";
    }

    }else if($opcionEdicion == 3){
    $consulta = "comentarios";
    
    }else if($opcionEdicion == 4){
    $consulta = "descripcion";
    }
        
    
    
    $sql = "UPDATE pac_respuestas_paciente_modulo_8 SET 
    $consulta = :detalle $consulta2 WHERE id = :idTratamiento";
    $stmt = $this->bd->prepare($sql);                    
                
    $datos = [
    ':idTratamiento' => $data['idTratamiento'],
    ':detalle' => $data['detalle']
    ];
            
    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se actualizo la informacion correctamente!');
            
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al actualizar la enfermedad de la lista!');
    }
            
    }

    /**
     * P10 patient API. These methods deliberately do not use the legacy
     * rendering/seed helpers above: opening the patient page must be read-only.
     */
    public function p10Procedimientos(int $idPaciente): array
    {
        $stmt = $this->bd->prepare(
            'SELECT id, id_paciente, procedimiento, fecha, resultados, descripcion
             FROM pac_procedimientos_dolor_modulo_8
             WHERE id_paciente = :id_paciente ORDER BY id ASC'
        );
        $stmt->execute([':id_paciente' => $idPaciente]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function p10HasCommentsColumn(): bool
    {
        try {
            $stmt = $this->bd->query('SHOW COLUMNS FROM pac_respuestas_paciente_modulo_8');
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $column) {
                if (($column['Field'] ?? '') === 'comentarios') {
                    return true;
                }
            }
        } catch (\Throwable $exception) {
            return false;
        }
        return false;
    }

    public function p10Tratamientos(int $idPaciente): array
    {
        $commentsAvailable = $this->p10HasCommentsColumn();
        $commentsSelect = $commentsAvailable ? 'r.comentarios' : 'NULL';
        $stmt = $this->bd->prepare(
            "SELECT t.id AS id_tratamiento, t.procedimiento AS tratamiento,
                    r.id AS id_respuesta, r.utilizo, r.resultado, r.descripcion,
                    {$commentsSelect} AS comentarios
             FROM pac_tratamientos_dolor_modulo_8 t
             LEFT JOIN pac_respuestas_paciente_modulo_8 r
               ON r.id_tratamiento = t.id AND r.id_paciente = :id_paciente
             ORDER BY t.id ASC"
        );
        $stmt->execute([':id_paciente' => $idPaciente]);
        return [
            'comments_available' => $commentsAvailable,
            'items' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
        ];
    }

    private function p10Text($value, int $max = 2000): string
    {
        $value = trim((string) ($value ?? ''));
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    private function p10Result(int $code, string $message): array
    {
        return ['resultado' => $code, 'mensaje' => $message];
    }

    public function p10AgregarProcedimiento(array $data): array
    {
        $idPaciente = (int) ($data['idPaciente'] ?? 0);
        if ($idPaciente < 1) return $this->p10Result(422, 'Datos de procedimiento no válidos.');

        $stmt = $this->bd->prepare(
            'INSERT INTO pac_procedimientos_dolor_modulo_8 (id_paciente)
             VALUES (:id_paciente)'
        );
        $stmt->execute([':id_paciente' => $idPaciente]);
        return $this->p10Result(200, 'Procedimiento agregado.');
    }

    public function p10EditarProcedimiento(array $data): array
    {
        $idPaciente = (int) ($data['idPaciente'] ?? 0);
        $idProcedimiento = (int) ($data['idProcedimiento'] ?? 0);
        $edicion = (int) ($data['edicion'] ?? 0);
        if ($idPaciente < 1 || $idProcedimiento < 1 || $edicion < 1 || $edicion > 4) {
            return $this->p10Result(422, 'Datos de procedimiento no válidos.');
        }

        $lookup = $this->bd->prepare(
            'SELECT procedimiento, fecha, resultados, descripcion
             FROM pac_procedimientos_dolor_modulo_8
             WHERE id = :id AND id_paciente = :id_paciente LIMIT 1'
        );
        $lookup->execute([':id' => $idProcedimiento, ':id_paciente' => $idPaciente]);
        $current = $lookup->fetch(\PDO::FETCH_ASSOC);
        if (!$current) return $this->p10Result(403, 'No autorizado.');

        $detail = $data['detalle'] ?? '';
        if ($edicion === 1) {
            $column = 'procedimiento';
            $detail = $this->p10Text($detail, 500);
        } elseif ($edicion === 2) {
            $column = 'fecha';
            $detail = $this->p10Text($detail, 10);
            if ($detail !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $detail)) {
                return $this->p10Result(422, 'La fecha no es válida.');
            }
        } elseif ($edicion === 3) {
            $column = 'resultados';
            $detail = $this->p10Text($detail, 40);
            if ($detail !== '' && !in_array($detail, ['Funciono', 'No funciono', 'Tuve efectos adversos'], true)) {
                return $this->p10Result(422, 'El resultado no es válido.');
            }
        } else {
            $column = 'descripcion';
            $detail = $this->p10Text($detail, 2000);
        }

        $currentValue = (string) ($current[$column] ?? '');
        $clearDescription = $edicion === 3 && in_array($detail, ['Funciono', 'No funciono'], true);
        if ($currentValue === $detail && (!$clearDescription || (string) ($current['descripcion'] ?? '') === '')) {
            return $this->p10Result(200, 'Sin cambios.');
        }

        if ($clearDescription) {
            $stmt = $this->bd->prepare(
                'UPDATE pac_procedimientos_dolor_modulo_8
                 SET resultados = :detalle, descripcion = :descripcion
                 WHERE id = :id AND id_paciente = :id_paciente'
            );
            $stmt->execute([
                ':detalle' => $detail,
                ':descripcion' => '',
                ':id' => $idProcedimiento,
                ':id_paciente' => $idPaciente,
            ]);
        } else {
            $stmt = $this->bd->prepare(
                "UPDATE pac_procedimientos_dolor_modulo_8
                 SET {$column} = :detalle
                 WHERE id = :id AND id_paciente = :id_paciente"
            );
            $stmt->execute([
                ':detalle' => $detail,
                ':id' => $idProcedimiento,
                ':id_paciente' => $idPaciente,
            ]);
        }
        return $this->p10Result(200, 'Procedimiento actualizado.');
    }

    public function p10EliminarProcedimiento(array $data): array
    {
        $idPaciente = (int) ($data['idPaciente'] ?? 0);
        $idProcedimiento = (int) ($data['idProcedimiento'] ?? 0);
        if ($idPaciente < 1 || $idProcedimiento < 1) return $this->p10Result(422, 'Datos de procedimiento no válidos.');

        $stmt = $this->bd->prepare(
            'DELETE FROM pac_procedimientos_dolor_modulo_8
             WHERE id = :id AND id_paciente = :id_paciente'
        );
        $stmt->execute([':id' => $idProcedimiento, ':id_paciente' => $idPaciente]);
        if ($stmt->rowCount() < 1) return $this->p10Result(403, 'No autorizado.');
        return $this->p10Result(200, 'Procedimiento eliminado.');
    }

    public function p10EditarTratamiento(array $data): array
    {
        $idPaciente = (int) ($data['idPaciente'] ?? 0);
        $idTratamiento = (int) ($data['idTratamiento'] ?? 0);
        $edicion = (int) ($data['edicion'] ?? 0);
        if ($idPaciente < 1 || $idTratamiento < 1 || $edicion < 1 || $edicion > 4) {
            return $this->p10Result(422, 'Datos de tratamiento no válidos.');
        }

        $catalog = $this->bd->prepare('SELECT id FROM pac_tratamientos_dolor_modulo_8 WHERE id = :id LIMIT 1');
        $catalog->execute([':id' => $idTratamiento]);
        if (!$catalog->fetchColumn()) return $this->p10Result(404, 'Tratamiento no encontrado.');

        $commentsAvailable = $this->p10HasCommentsColumn();
        if ($edicion === 3 && !$commentsAvailable) {
            return $this->p10Result(422, 'Los comentarios no están disponibles en este entorno.');
        }

        $lookup = $this->bd->prepare(
            'SELECT id, utilizo, resultado, descripcion' . ($commentsAvailable ? ', comentarios' : '') . '
             FROM pac_respuestas_paciente_modulo_8
             WHERE id_tratamiento = :id_tratamiento AND id_paciente = :id_paciente LIMIT 1'
        );
        $lookup->execute([':id_tratamiento' => $idTratamiento, ':id_paciente' => $idPaciente]);
        $current = $lookup->fetch(\PDO::FETCH_ASSOC) ?: null;

        $detail = $data['detalle'] ?? '';
        $column = null;
        if ($edicion === 1) {
            $column = 'utilizo';
            $detail = $this->p10Text($detail, 20);
            if (!in_array($detail, ['Si', 'No'], true)) return $this->p10Result(422, 'La respuesta no es válida.');
        } elseif ($edicion === 2) {
            $column = 'resultado';
            $detail = $this->p10Text($detail, 40);
            if (!in_array($detail, ['', 'Funciono', 'No funciono', 'Tuve efectos adversos'], true)) return $this->p10Result(422, 'El resultado no es válido.');
        } elseif ($edicion === 3) {
            $column = 'comentarios';
            $detail = $this->p10Text($detail, 2000);
        } else {
            $column = 'descripcion';
            $detail = $this->p10Text($detail, 2000);
        }

        if ($current && (string) ($current[$column] ?? '') === $detail) {
            return $this->p10Result(200, 'Sin cambios.');
        }

        if (!$current) {
            $values = ['id_tratamiento' => $idTratamiento, 'id_paciente' => $idPaciente, 'utilizo' => null, 'resultado' => null, 'descripcion' => null];
            if ($commentsAvailable) $values['comentarios'] = null;
            $values[$column] = $detail;
            if ($column === 'utilizo' && $detail === 'No') {
                $values['resultado'] = '';
                $values['descripcion'] = '';
                if ($commentsAvailable) $values['comentarios'] = '';
            }
            if ($column === 'resultado' && in_array($detail, ['Funciono', 'No funciono'], true)) $values['descripcion'] = '';
            $columns = array_keys($values);
            $placeholders = array_map(static fn($name) => ':' . $name, $columns);
            $stmt = $this->bd->prepare(
                'INSERT INTO pac_respuestas_paciente_modulo_8 (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
            );
            $params = [];
            foreach ($values as $name => $value) $params[':' . $name] = $value;
            $stmt->execute($params);
            return $this->p10Result(200, 'Tratamiento actualizado.');
        }

        if ($column === 'utilizo' && $detail === 'No') {
            $clear = $commentsAvailable ? ', comentarios = :comentarios' : '';
            $stmt = $this->bd->prepare(
                'UPDATE pac_respuestas_paciente_modulo_8
                 SET utilizo = :detalle, resultado = :resultado, descripcion = :descripcion' . $clear . '
                 WHERE id = :id AND id_paciente = :id_paciente'
            );
            $params = [':detalle' => 'No', ':resultado' => '', ':descripcion' => '', ':id' => $current['id'], ':id_paciente' => $idPaciente];
            if ($commentsAvailable) $params[':comentarios'] = '';
            $stmt->execute($params);
        } elseif ($column === 'resultado' && in_array($detail, ['Funciono', 'No funciono'], true)) {
            $stmt = $this->bd->prepare(
                'UPDATE pac_respuestas_paciente_modulo_8
                 SET resultado = :detalle, descripcion = :descripcion
                 WHERE id = :id AND id_paciente = :id_paciente'
            );
            $stmt->execute([':detalle' => $detail, ':descripcion' => '', ':id' => $current['id'], ':id_paciente' => $idPaciente]);
        } else {
            $stmt = $this->bd->prepare(
                "UPDATE pac_respuestas_paciente_modulo_8 SET {$column} = :detalle
                 WHERE id = :id AND id_paciente = :id_paciente"
            );
            $stmt->execute([':detalle' => $detail, ':id' => $current['id'], ':id_paciente' => $idPaciente]);
        }
        return $this->p10Result(200, 'Tratamiento actualizado.');
    }

    public function p10FinalizarModulo(int $idPaciente): array
    {
        $catalogCount = (int) $this->bd
            ->query('SELECT COUNT(*) FROM pac_tratamientos_dolor_modulo_8')
            ->fetchColumn();
        if ($catalogCount < 1) {
            return $this->p10Result(422, 'El catálogo de tratamientos no está disponible.');
        }

        $incomplete = $this->bd->prepare(
            'SELECT COUNT(*)
             FROM pac_tratamientos_dolor_modulo_8 t
             LEFT JOIN pac_respuestas_paciente_modulo_8 r
               ON r.id_tratamiento = t.id AND r.id_paciente = :id_paciente
             WHERE r.id IS NULL
                OR r.utilizo IS NULL
                OR r.utilizo NOT IN (\'Si\', \'No\')
                OR (r.utilizo = \'Si\' AND (r.resultado IS NULL OR r.resultado = \'\'))
                OR (r.utilizo = \'Si\' AND r.resultado = \'Tuve efectos adversos\'
                    AND (r.descripcion IS NULL OR TRIM(r.descripcion) = \'\'))'
        );
        $incomplete->execute([':id_paciente' => $idPaciente]);
        if ((int) $incomplete->fetchColumn() > 0) {
            return $this->p10Result(422, 'Completa las respuestas de tratamientos antes de finalizar.');
        }

        $lookup = $this->bd->prepare(
            'SELECT id FROM pac_historia_clinica_finalizar
             WHERE id_modulo = 8 AND id_paciente = :id_paciente LIMIT 1'
        );
        $lookup->execute([':id_paciente' => $idPaciente]);
        if ($lookup->fetchColumn()) return $this->p10Result(200, 'El módulo ya estaba finalizado.');

        $stmt = $this->bd->prepare(
            'INSERT INTO pac_historia_clinica_finalizar (id_modulo, id_paciente)
             VALUES (8, :id_paciente)'
        );
        $stmt->execute([':id_paciente' => $idPaciente]);
        return $this->p10Result(200, 'Módulo finalizado.');
    }

}

