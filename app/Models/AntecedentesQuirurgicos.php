<?php
namespace App\Models;
use App\Config\Database;
use App\Models\PacienteModulosModelo;

class AntecedentesQuirurgicos{

    private $bd;

    public function __construct(){
    $this->bd = Database::getInstance();
    
    }
  
    //-------------------- EDITOR DE TEXTO WORD --------------------//
    public function editorTextoAQ($idPaciente){
    $result = '';

    $stmt = $this->bd->query("SELECT * FROM pc_antecedentes_quirurgicos WHERE id_paciente = '".$idPaciente."' ORDER BY id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    

    $result .= '<table class="table-bordered" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">
    <tr>
    <th class="text-center align-middle">Fecha</th>
    <th class="text-center align-middle">Nombre de la cirugia</th>
    <th class="text-center align-middle">Observaciones</th>
    </tr>';
 
    if (!empty($preguntas)) {
    $num = 1;
    foreach ($preguntas as $pregunta): 
    $fecha = $pregunta['fecha'];
    $cirugia = $pregunta['cirugia'];
    $observaciones = $pregunta['observaciones'];

    $result .= '    
    <tr>
    <td class="text-center align-middle">'.$fecha.'</td>
    <td class="text-center align-middle">'.$cirugia.'</td>
    <td class="text-center align-middle">'.$observaciones.'</td>
    </tr>';
    
    $num++;
    endforeach;
    }else{
    $result .= '    
    <tr>
    <td class="text-center align-middle" colspan="3">No se encontro información</td>
    </tr>';
    }
    $result .= '</table>';
    
    return $result;
    }


    //---------- MOSTRAR PREGUNTAS DEL MODULO 4 ----------/
    public function mostrarPreguntasM4($idPaciente,$idRol){
    $result = "";

    if($idRol == "Paciente"){
    $stmt = $this->bd->query("SELECT * FROM pc_antecedentes_quirurgicos WHERE id_paciente = '".$idPaciente."' ORDER BY id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $model = new PacienteModulosModelo();
    $botonFinalizar = $model->botonFinalizarModulo(4,$idPaciente,$idRol);
    
    if (!empty($preguntas)) {
    
    $result .= '
    <div class="card-header pb-0">
    <div class="row">

    <div id="seccion101" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-3">
    <img src="'.RUTA_IMAGES.'/iconos/audio.png" onclick="readQuestion()" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion101">

    <h8 class="text-primary fw-bold texto">
    <b>A continuación, deberá ingresar la fecha en que se realizó la cirugía y proporcionar una breve descripción de la misma:</b>
    </h8>
    </div>

    <div class="col-12 col-md-1 d-flex justify-content-end align-items-center">

    </div> 

    <div class="col-12">
    <div id="mensaje" class="text-center text-danger mt-2"></div>
    </div>

    </div>
    </div>';

    $result .= '<div class="card-body">';

    foreach ($preguntas as $index => $pregunta) :
    $idCirugia = $pregunta['id'];
    $fecha = $pregunta['fecha'];
    $cirugia = $pregunta['cirugia'];
    $observaciones = $pregunta['observaciones'];

    $claseActiva = ($index == 0) ? 'active' : ''; // Se activará la pregunta guardada en localStorage
    $result .= '<div class="pregunta-container '.$claseActiva.'" data-index="'.$index.'" data-id="'.$idCirugia.'" data-enfermedad="'.$cirugia.'" data-rol="'.$idRol.'">';

    $result .= '
    <div class="col-12">
    <div class="text-secondary fw-bold mb-1">Nombre de la cirugía:</div>
    <div class="row">
    
    <div class="col-12">
    <input type="text" onchange="editarCirugia('.$idCirugia.', this, 2, \''.$idRol.'\')" class="form-control mb-3" value="'.$cirugia.'" placeholder="Escribe aquí el nombre de la cirugía...">
    </div>

    </div>
    </div>

    <div class="col-12">
    <div class="text-secondary fw-bold mb-1">Fecha en que se realizo la cirugía:</div>
    <input type="date" onchange="editarCirugia('.$idCirugia.', this, 1, \''.$idRol.'\')" class="form-control mb-3" value="'.$fecha.'">
    </div>

    <div class="col-12">
    <div class="text-secondary fw-bold mb-1">Observaciones:</div>
    <input type="text" onchange="editarCirugia('.$idCirugia.', this, 3, \''.$idRol.'\')" class="form-control " value="'.$observaciones.'" placeholder="Escribe aquí tus observaciones...">
    </div>

    <div class="col-12 mt-5">
    <div class="row">

    <div class="col-6 text-center">
    <img src="'.RUTA_IMAGES.'/iconos/eliminar-icon.png" onclick="eliminarCirugiaPaciente('.$idCirugia.', \''.$idRol.'\')" class="img-fluid pointer mb-2" style="width: 35%;">
    <div class="mt-2"><h5 class="text-danger fw-bold">Eliminar cirugía</h5></div>
    </div>

    <div class="col-6 text-center btnAgregarCirugia">
    <img src="'.RUTA_IMAGES.'/iconos/agregar-icon.png" onclick="agregarCirugiaPaciente('.$idPaciente.',\''.$idRol.'\')" class="img-fluid pointer mb-2" style="width: 35%;" style="display: none;">
    <div class="mt-2"><h5 class="text-success fw-bold">Agregar cirugía</h5></div>
    </div>

    </div>
    </div>

    <div class="col-12">
    <div class="mt-3 d-flex justify-content-between">';

    if ($index > 0) {
    $result .= '<button class="btn btn-secondary" onclick="anteriorPregunta()"><i data-feather="chevron-left"></i> Anterior</button>';
    } else {
    $result .= '<div></div>';
    }

    if ($index < count($preguntas) - 1) {
    $result .= '<button class="btn btn-primary" onclick="siguientePregunta()">Siguiente <i data-feather="chevron-right"></i></button>';
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

    <div id="seccion100" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-2">
    <img src="'.RUTA_IMAGES.'/iconos/audio.png" onclick="readQuestion()" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion100">

    <h8 class="text-primary fw-bold texto">
    <b>Si usted ha tenido alguna cirugía, presione el botón verde para agregarla. Si no ha sido sometido a ninguna intervención quirúrgica, seleccione el boton de "Finalizar":</b>
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
    <img src="'.RUTA_IMAGES.'/iconos/agregar-icon.png" onclick="agregarCirugiaPaciente('.$idPaciente.',\''.$idRol.'\')"class="img-fluid btnLeer pointer" style="max-height: 90%;"></div>
    </div> 
    <div class="col-12 text-end">
    '.$botonFinalizar.'
    </div>
    
    </div>';
    
    }

    }else{
    $stmt = $this->bd->query("SELECT * FROM pc_antecedentes_quirurgicos WHERE id_paciente = '".$idPaciente."' ORDER BY fecha DESC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);


    $result .= '
    <div class="card-header pb-0">
    <div class="row">

    <div class="col-11">
    <h8 class="text-primary fw-bold texto">
    <b>A continuación, indique si a su paciente se le han realizado una o más cirugías:</b>
    </h8>
    </div>

    <div class="col-1">
    <button id="btnAgregarCirugia" onclick="agregarCirugiaPaciente('.$idPaciente.', \''.$idRol.'\')" class="btn icon btn-success float-end">
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
    <table class="table table-striped" id="table_cirugia">
    <thead>
    <tr>
    <th class="text-center align-middle" width="60px">Fecha</th>
    <th class="text-start align-middle">Nombre de la cirugía</th>
    <th class="text-start align-middle">Observaciónes</th>
    <th class="text-center align-middle" width="30px"><i data-feather="trash-2"></i></th>
    </tr>
    </thead>
    <tbody>'; 

    foreach ($preguntas as $index => $pregunta) :
    $idCirugia = $pregunta['id'];
    $fecha = $pregunta['fecha'];
    $cirugia = $pregunta['cirugia'];
    $observaciones = $pregunta['observaciones'];

    $result.= '<tr>';
    $result .= '<td class="text-center align-middle p-2">
    <span class="d-none search-value">'.$fecha.'</span>
    <input class="form-control text-center date-input" type="date" onchange="editarCirugia('.$idCirugia.', this, 1, \''.$idRol.'\')" value="'.($fecha ?? '').'" >
    </td>';


    $result .= '<td class="text-center align-middle p-2">
    <input class="form-control text-start" type="text" onchange="editarCirugia('.$idCirugia.', this, 2, \''.$idRol.'\')" value="'.($cirugia ?? '').'" placeholder="Escribe aquí el nombre de la cirugía...">
    </td>';

    $result .= '<td class="text-center align-middle p-2">
    <input class="form-control text-start" type="text" placeholder="Escribe aquí tus observaciones..." onchange="editarCirugia('.$idCirugia.', this, 3, \''.$idRol.'\')" value="'.($observaciones ?? '').'">
    </td>';

    $result.= '<td class="text-center align-middle p-2">
    <i data-feather="trash-2" class="pointer" onclick="eliminarCirugiaPaciente('.$idCirugia.', \''.$idRol.'\')"></i>
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

    public function agregarCirugiaPaciente($data){

    $sql = "INSERT INTO pc_antecedentes_quirurgicos (
    id_paciente
    ) VALUES (
    :id_paciente
    )";
    
    $stmt = $this->bd->prepare($sql);                    
        
    $datos = [
    ':id_paciente' => $data['idPaciente']
    ];
    
    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se habilito una nueva cirugia correctamente!');
    
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al agregar la nueva cirugia a la lista!');
    }
    
    }


    public function editarCirugiaPaciente($data){
    $opcionEdicion = $data['edicion'];

    if($opcionEdicion == 1){
    $consulta = "fecha";
    }else if($opcionEdicion == 2){
    $consulta = "cirugia";
    }else if($opcionEdicion == 3){
    $consulta = "observaciones";
    }
    
    
    $sql = "UPDATE pc_antecedentes_quirurgicos SET 
    $consulta = :detalle WHERE id = :id_cirugia";
    
    $stmt = $this->bd->prepare($sql);                    
                
    $datos = [
    ':id_cirugia' => $data['idCirugia'],
    ':detalle' => $data['detalle']
    ];
            
    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se actualizo la informacion correctamente!');
            
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al actualizar la cirugia de la lista!');
    }
            
    }


    public function eliminarCirugiaPaciente($data){

    $sql = "DELETE FROM pc_antecedentes_quirurgicos WHERE id = :idQuirurgico";
    $stmt = $this->bd->prepare($sql);                    
            
    $datos = [
    ':idQuirurgico' => $data['idQuirurgico']
    ];
        
    if ($stmt->execute($datos)) {
    return array('resultado' => 200,'mensaje' => '¡Se elimino la cirugia correctamente!');
        
    } else {
    return array('resultado' => 401,'mensaje' => '¡Error al eliminar la cirugia de la lista!');
    }
        
    }


}

