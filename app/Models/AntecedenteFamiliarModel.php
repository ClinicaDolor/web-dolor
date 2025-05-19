<?php
namespace App\Models;
use App\Config\Database;

class AntecedenteFamiliarModel{
private $bd;

public function __construct(){
$this->bd = Database::getInstance();

}

    public function enfermedadesFijas(){

    $enfermedades_fijas = [
        "Enfermedades del corazón",
        "Hipertensión arterial sistémica",
        "Enfermedad cerebrovascular",
        "Diabetes Mellitus",
        "Cáncer"
    ];

    return $enfermedades_fijas;
    }

    //-------------------- EDITOR DE TEXTO WORD --------------------//
    public function editorTextoAF($idPaciente){
    $result = '';


    $stmt = $this->bd->query("SELECT * FROM pc_antecedentes_familiares WHERE id_paciente = '".$idPaciente."' ORDER BY id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (!empty($preguntas)) {
    $result .= '<table class="table-bordered" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">';
    $result .= '    
    <tr class="bg-white">
    <th class="text-start align-middle">Nombre de la enfermedad</th>
    <th class="text-center align-middle">Tipo de enfermedad</th>
    <th class="text-center align-middle">¿Alguno de tus familiares ha sido diagnosticado con esta enfermedad?</th>
    <th class="text-center align-middle">¿Qué familiar padece esta enfermedad?</th>
    </tr>';

    foreach ($preguntas as $pregunta): 
    $enfermedad = $pregunta['enfermedad'];
    $tipo = $pregunta['tipo'];
    $detalle = $pregunta['detalle'];
    $especificar = $pregunta['especificar'];

    $result .= '    
    <tr>
    <td class="text-start align-middle">'.$enfermedad.'</td>
    <td class="text-center align-middle">'.$tipo.'</td>
    <td class="text-center align-middle">'.$detalle .'</td>
    <td class="text-center align-middle">'.$especificar.'</td>
    </tr>';
    endforeach;

    $result .= '</table>';
    }


    return $result;
    }


    public function mostrarPreguntasM2($idPaciente, $idRol){
    $result = '';

    $enfermedades_fijas = $this->enfermedadesFijas();

    $stmt = $this->bd->query("SELECT * FROM pc_antecedentes_familiares WHERE id_paciente = '".$idPaciente."' ORDER BY id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if($idRol == "Doctor"){
    $result .= '<div class="card-header">
    <div class="row">
    <div class="col-11">
    <h8 class="text-primary">
    <b>A continuación, le preguntaremos si existen antecedentes familiares de alguna de las siguientes enfermedades. <br>Por favor, mencione si alguno de sus familiares cercanos, como abuelos, padres, hermanos, etc., ha padecido alguna de ellas:</b>
    </h8>
    </div>
 
    <div class="col-1">
    <button onclick="agregarEnfermedadPaciente('.$idPaciente.',\''.$idRol.'\')" class="btn icon btn-success float-end"> <i data-feather="plus" width="20"></i> </button>
    </div>

    <div class="col-12">
    <div id="mensaje" class="text-center text-danger mt-2"></div>
    </div>

    </div>
    </div>';

    $result .= '    <div class="card-body">
    <div class="col-12">
    <div class="table-responsive">
    <table class="table table-striped" id="table_enfermedades">
    <thead>
    <tr>
    <th class="text-start align-middle" width="250px">Nombre de la enfermedad</th>
    <th class="text-center align-middle" width="300px">¿Alguno de tus familiares ha sido diagnosticado con esta enfermedad?</th>
    <th class="text-center align-middle" width="250px">Tipo de enfermedad</th>
    <th class="text-center align-middle">¿Qué familiar padece esta enfermedad?</th>
    <th class="text-center align-middle" width="30px"><i data-feather="trash-2"></i></th>
    </tr>
    </thead>
    <tbody>';

    if (!empty($preguntas)) {
        
    foreach ($preguntas as $pregunta): 
    $idEnfermedad = $pregunta['id'];
    $enfermedad = $pregunta['enfermedad'];
    $tipo = $pregunta['tipo'];
    $detalle = $pregunta['detalle'];
    $especificar = $pregunta['especificar'];

    $result .= '<tr>
    <td class="text-start align-middle p-1">
    <span class="d-none search-value">'.$enfermedad.'</span>
    ' . (in_array($enfermedad, $enfermedades_fijas) ? $enfermedad : '<input onchange="editarEnfermedad('.$idEnfermedad.', this, 1, \''.$idRol.'\')" 
    class="form-control nombre-enfermedad" value="' . ($enfermedad ?? '') . '"  placeholder="Escribe la enfermedad...">') . '
    </td>

    <td class="text-center align-middle">
    <select class="form-select tipo-enfermedad text-center" onchange="editarEnfermedad('.$idEnfermedad.', this, 2, \''.$idRol.'\',1)" 
    ' . (in_array($enfermedad, $enfermedades_fijas) ? '' : 'disabled') . '>
    <option value="" disabled selected ' . ($detalle === '' ? 'selected' : '') . '>Selecciona una opción...</option>
    <option value="Si" ' . ($detalle == 'Si' ? 'selected' : '') . '>Sí</option>
    <option value="No" ' . ($detalle == 'No' ? 'selected' : '') . '>No</option>
     </select>
    </td>

    <td class="text-center align-middle tipo-detalle">';
    if($enfermedad == "Diabetes Mellitus"){

    $result.= '<select class="form-select detalle-enfermedad text-center" onchange="editarEnfermedad('.$idEnfermedad.', this, 3, \''.$idRol.'\')" 
    ' . (($enfermedad == "Diabetes Mellitus" && $detalle == "Si") ? '' : 'disabled') . '>
    <option value="" disabled selected ' . ($tipo === '' ? 'selected' : '') . '>Selecciona una opción...</option>
    <option value="Tipo 1" ' . ($tipo === 'Tipo 1' ? 'selected' : '') . '>Tipo 1</option>
    <option value="Tipo 2" ' . ($tipo === 'Tipo 2' ? 'selected' : '') . '>Tipo 2</option>
    <option value="Gestacional" ' . ($tipo === 'Gestacional' ? 'selected' : '') . '>Gestacional</option>
    </select>';
    }   
    $result .= '</td>

    <td class="text-center align-middle">
    <input class="form-control especificar-enfermedad" onchange="editarEnfermedad('.$idEnfermedad.', this, 4, \''.$idRol.'\')" value="' . ($especificar ?? '') . '" 
    placeholder="Escribe aqui el familiar..." ' . ($detalle == 'Si' ? '' : 'disabled') . '>
    </td>

    <td class="text-start align-middle">
    ' . (!in_array($enfermedad, $enfermedades_fijas) ? '<i data-feather="trash-2" class="pointer" onclick="eliminarEnfermedadPaciente('.$idEnfermedad.', \''.$idRol.'\')"></i>' : '') . '
    </td>
    </tr>';

    endforeach;

    }


    }else{
    
    if (!empty($preguntas)) {
    foreach ($preguntas as $index => $pregunta) {
    $idEnfermedad = $pregunta['id'];
    $enfermedad = $pregunta['enfermedad'];
    $tipo = $pregunta['tipo'];
    $detalle = $pregunta['detalle'];
    $especificar = $pregunta['especificar'];
    
    $claseActiva = ($index == 0) ? 'active' : ''; // Se activará la pregunta guardada en localStorage
    $result .= '<div class="pregunta-container '.$claseActiva.'" data-index="'.$index.'" data-id="'.$idEnfermedad.'" data-enfermedad="'.$enfermedad.'" data-rol="'.$idRol.'">';
            
    if (in_array($enfermedad, $enfermedades_fijas)) {
        $result .= '
        <div class="text-secondary fw-bold mb-1">Nombre de la enfermedad:</div>
        <h4 class="mb-3">'.$enfermedad.'</h4>
        <div class="text-secondary fw-bold mb-1">¿Alguno de tus familiares ha sido diagnosticado con esta enfermedad?:</div>
        <div class="d-flex justify-content-start gap-2 mb-3">
            <div class="text-center">
                <input class="form-check-input custom-checkbox tipo-enfermedad" type="checkbox" 
                    name="enfermedad_'.$idEnfermedad.'" 
                    id="enfermedad_'.$idEnfermedad.'_si" 
    value="Si" data-value="Si" '.($detalle == 'Si' ? 'checked' : '').' 
                    onchange="editarEnfermedad('.$idEnfermedad.', this, 2, \''.$idRol.'\', 1)">
                <h5 class=" d-block text-secondary mt-2" for="enfermedad_'.$idEnfermedad.'_si">
                    Si
                </h5>
            </div>
            <div class="text-center">
                <input class="form-check-input custom-checkbox tipo-enfermedad" type="checkbox" 
                    name="enfermedad_'.$idEnfermedad.'" 
                    id="enfermedad_'.$idEnfermedad.'_no" 
    value="No" data-value="No" '.($detalle == 'No' ? 'checked' : '').' 
                    onchange="editarEnfermedad('.$idEnfermedad.', this, 2, \''.$idRol.'\', 1)">
                <h5 class=" d-block text-secondary mt-2" for="enfermedad_'.$idEnfermedad.'_no">
                    No
                </h5>
            </div>
        </div>
        ';
        
    } else {
    
    $result .= '
    <div class="text-secondary fw-bold mb-1">Otras enfermedades de importancia:</div>
    <div class="row">
    <div class="col-11">
    <input onchange="editarEnfermedad('.$idEnfermedad.', this, 1, \''.$idRol.'\')" class="form-control mb-3" value="'.$enfermedad.'" placeholder="Escribe aquí el nombre de la enfermedad...">
    </div>
    
    <div class="col-1">
    <button class="btn btn-danger float-end" onclick="eliminarEnfermedadPaciente('.$idEnfermedad.', \''.$idRol.'\', 1)">
    <i data-feather="trash-2"></i>
    </button>
    </div>
    </div>';
    }
    
    if($detalle == "Si"){
        
    $result .= '
    <div id="divTipoDiabetes_'.$idEnfermedad.'" class="divTipoEnfermedad" style="display: '.(($enfermedad == 'Diabetes Mellitus' && $detalle == 'Si') ? 'block' : 'none').';">
            
    <div class="text-secondary fw-bold mb-1">¿Qué tipo de diabetes?</div>

    <div class="d-flex flex-wrap gap-4 mb-4">
    <div class="text-center d-flex flex-column align-items-center">
    <input type="checkbox" class="detalle-enfermedad form-check-input custom-checkbox mb-2" onchange="editarEnfermedad('.$idEnfermedad.', this, 3, \''.$idRol.'\')" 
    data-value="Si" value="Tipo 1" '.($tipo === 'Tipo 1' ? 'checked' : '').' '.(($enfermedad == 'Diabetes Mellitus' && $detalle == 'Si') ? '' : 'disabled').'>
    <h5 class="text-secondary">Tipo 1</h5>
    </div>
        
    <div class="text-center d-flex flex-column align-items-center">
    <input type="checkbox" class="detalle-enfermedad form-check-input custom-checkbox mb-2" onchange="editarEnfermedad('.$idEnfermedad.', this, 3, \''.$idRol.'\')" 
    data-value="Si" value="Tipo 2" '.($tipo === 'Tipo 2' ? 'checked' : '').' '.(($enfermedad == 'Diabetes Mellitus' && $detalle == 'Si') ? '' : 'disabled').'>
    <h5 class="text-secondary">Tipo 2</h5>
    </div>
        
    <div class="text-center d-flex flex-column align-items-center">
    <input type="checkbox" class="detalle-enfermedad form-check-input custom-checkbox mb-2" onchange="editarEnfermedad('.$idEnfermedad.', this, 3, \''.$idRol.'\')" 
    data-value="Si" value="Gestacional" '.($tipo === 'Gestacional' ? 'checked' : '').' '.(($enfermedad == 'Diabetes Mellitus' && $detalle == 'Si') ? '' : 'disabled').'>
    <h5 class="text-secondary">Gestacional</h5>
    </div>
               
    </div>
    </div>
    
    
    <div class="text-secondary fw-bold mb-1">¿Qué familiar padece esta enfermedad?:</div>
    <input class="form-control especificar-enfermedad" onchange="editarEnfermedad('.$idEnfermedad.', this, 4, \''.$idRol.'\')" value="'.$especificar.'" placeholder="Escribe aqui al familiar..." '.($detalle !== 'Si' ? 'disabled' : '').'>
    ';
        

    }
    
    //----- Botones de navegación
    $result .= '<div class="mt-3 d-flex justify-content-between">';
    if ($index > 0) {
    $result .= '<button class="btn btn-secondary" onclick="anteriorPregunta()"><i data-feather="chevron-left"></i> Anterior</button>';
    } else {
    $result .= '<div></div>';
    }
    
    if ($index < count($preguntas) - 1) {
    $result .= '<button class="btn btn-primary" onclick="siguientePregunta()">Siguiente <i data-feather="chevron-right"></i></button>';
    } else {
    $result .= '<button class="btn btn-success" onclick="finalizarPreguntas()">Comentarios <i data-feather="message-circle"></i></button>';
    }
    
    $result .= '</div>
    </div>'; 
    }
    
    }else{
    $result .= ' <div class="alert alert-danger text-center" role="alert">No se encontraron preguntas en esta sección.</div>';
    }

    }

    return $result;
    }


    public function agregarEnfermedadPaciente($data){

    $sql = "INSERT INTO pc_antecedentes_familiares (
    id_paciente,
    detalle
    ) VALUES (
    :id_paciente,
    :detalle
    )";

    $stmt = $this->bd->prepare($sql);                    
     
    $datos = [
    ':id_paciente' => $data['idPaciente'],
    ':detalle' => "Si"
    ];

    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se habilito una nueva enfermedad correctamente!');

    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al agregar la nueva enfermedad a la lista!');
    }

    }
   
    public function editarEnfermedadPaciente($data){
    $opcionEdicion = $data['edicion'];

    $consulta2 = "";
    if($opcionEdicion == 1){
    $consulta = "enfermedad";
    }else if($opcionEdicion == 2){
    $consulta = "detalle";
    if($data['detalle'] == "No"){
    $consulta2 = ",tipo = '', especificar = ''";
    }
    }else if($opcionEdicion == 3){
    $consulta = "tipo";
    }else if($opcionEdicion == 4){
    $consulta = "especificar";
    }


    $sql = "UPDATE pc_antecedentes_familiares SET 
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

    public function eliminarEnfermedadPaciente($data){

    $sql = "DELETE FROM pc_antecedentes_familiares WHERE id = :id_enfermedad";
    $stmt = $this->bd->prepare($sql);                    
        
    $datos = [
    ':id_enfermedad' => $data['idEnfermedad']
    ];
    
    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se elimino la enfermedad correctamente!');
    
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al eliminar la enfermedad de la lista!');
    }
    
    }

    //---------- AGREGA ENFERMEDADES AL INICIAR ----------//
    public function antecedentesFamiliares($idPaciente, $enfermedad) {
    $sql = "SELECT COUNT(*) FROM pc_antecedentes_familiares WHERE id_paciente = ? AND enfermedad = ?";
    $stmt = $this->bd->prepare($sql);
    $stmt->execute([$idPaciente, $enfermedad]);
    $numero = $stmt->fetchColumn();
    
    if ($numero == 0) {
    $sql_insert = "INSERT INTO pc_antecedentes_familiares (id_paciente, enfermedad, tipo, detalle, especificar) 
    VALUES (?, ?, '', '', '')";
    $stmt_insert = $this->bd->prepare($sql_insert);
    $stmt_insert->execute([$idPaciente, $enfermedad]);
    }
    }


}