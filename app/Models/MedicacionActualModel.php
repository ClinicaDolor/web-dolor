<?php
namespace App\Models;
use App\Config\Database;
use App\Models\PacienteModulosModelo;

class MedicacionActualModel{
private $bd;

public function __construct(){
$this->bd = Database::getInstance();

} 


    //-------------------- EDITOR DE TEXTO WORD --------------------//
    public function editorTextoMA($idPaciente){
    $result = '';

    $stmt = $this->bd->query("SELECT * FROM pac_medicacion_actual_modulo_6 WHERE id_paciente = '".$idPaciente."' ORDER BY id ASC");
    $preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $result .= '<table class="table-bordered" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;">
    <tr>
    <th class="text-center align-middle">Nombre del medicamento</th>
    <th class="text-center align-middle">¿Para qué lo utiliza?</th>
    <th class="text-center align-middle">Tiempo que ha estado en tratamiento</th>
    <th class="text-center align-middle">Dosis actual</th>
    <th class="text-center align-middle">Nombre del médico que lo recetó</th>
    </tr>';

    if (!empty($preguntas)) {
    $num = 1;
    foreach ($preguntas as $pregunta): 
    $medicamento = $pregunta['medicamento'];
    $descripcion = $pregunta['descripcion'];
    $tiempo_uso = $pregunta['tiempo_uso'];
    $dosis = $pregunta['dosis'];
    $medico = $pregunta['medico'];

    $result .= '    
    <tr>
    <td class="text-center align-middle">'.$medicamento.'</td>
    <td class="text-center align-middle">'.$descripcion.'</td>
    <td class="text-center align-middle">'.$tiempo_uso.'</td>
    <td class="text-center align-middle">'.$dosis.'</td>
    <td class="text-center align-middle">'.$medico.'</td>
    </tr>';
    
    $num++;
    endforeach;
    }else{
    $result .= '    
    <tr>
    <td class="text-center align-middle" colspan="5"> No se encontro información</td>
    </tr>';
    }
    $result .= '</table>';
    
    return $result;
    }



//---------- MOSTRAR PREGUNTAS DEL MODULO 4 ----------/

public function mostrarPreguntasM6($idPaciente,$idRol){
$result = "";

$stmt = $this->bd->query("SELECT * FROM pac_medicacion_actual_modulo_6 WHERE id_paciente = '".$idPaciente."' ORDER BY id ASC");
$preguntas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if($idRol == "Paciente"){
$model = new PacienteModulosModelo();
$botonFinalizar = $model->botonFinalizarModulo(6,$idPaciente,$idRol);

if (!empty($preguntas)) {

$result .= '
<div class="card-header pb-0">
<div class="row">

<div id="seccion101" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-3">
<img src="'.RUTA_IMAGES.'/iconos/audio.png" onclick="readQuestion()" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion101">

<h8 class="text-primary fw-bold texto">
<b>A continuación, deberá ingresar la información correspondiente a cada medicamento que esté utilizando, incluyendo el nombre del medicamento, el motivo de uso, el tiempo que ha estado en tratamiento, la dosis actual y el nombre del médico que lo recetó:</b>
</h8>
</div>

<div class="col-12">
<div id="mensaje" class="text-center text-danger mt-2"></div>
</div>

</div>
</div>';

$result .= '<div class="card-body">';
$result .= '<div class="row">';
foreach ($preguntas as $index => $pregunta) :
$idMedicamento = $pregunta['id'];
$medicamento = $pregunta['medicamento'];
$descripcion = $pregunta['descripcion'];
$tiempo_uso = $pregunta['tiempo_uso'];
$dosis = $pregunta['dosis'];
$medico = $pregunta['medico'];

$claseActiva = ($index == 0) ? 'active' : ''; // Se activará la pregunta guardada en localStorage
$result .= '<div class="pregunta-container '.$claseActiva.'" data-index="'.$index.'" data-id="'.$idMedicamento.'" data-enfermedad="'.$medicamento.'" data-rol="'.$idRol.'">';

$result .= '<div class="col-12">
<div class="text-secondary fw-bold mb-1">¿Cuál es el nombre del medicamento?</div>
<div class="row">
<div class="col-12">
<input type="text" onchange="editarMedicamento('.$idMedicamento.', this, 1, \''.$idRol.'\')" class="form-control mb-3" value="'.$descripcion.'" placeholder="Escribe aquí el nombre del medicamento...">
</div>

</div>
</div>';

$result .= '<div class="col-12">
<div class="text-secondary fw-bold mb-1">¿Para qué lo utiliza?</div>
<input type="text" onchange="editarMedicamento('.$idMedicamento.', this, 2, \''.$idRol.'\')" class="form-control mb-3" value="'.$medicamento.'" placeholder="Escribe aquí el motivo de medicación...">
</div>';

$result .= '<div class="col-12">
<div class="text-secondary fw-bold mb-1">¿Cúal es el tiempo que ha estado en tratamiento?</div>
<input type="text" onchange="editarMedicamento('.$idMedicamento.', this, 3, \''.$idRol.'\')" class="form-control mb-3" value="'.$tiempo_uso.'" placeholder="Escribe aquí el tiempo de tratamiento...">
</div>';

$result .= '<div class="col-12">
<div class="text-secondary fw-bold mb-1">¿Cúal es la dosis actual que utiliza?</div>
<input type="text" onchange="editarMedicamento('.$idMedicamento.', this, 4, \''.$idRol.'\')" class="form-control mb-3" value="'.$dosis.'" placeholder="Escribe aquí la dosis que utiliza...">
</div>';

$result .= '<div class="col-12">
<div class="text-secondary fw-bold mb-1">¿Cúal es nombre del médico que lo recetó?</div>
<input type="text" onchange="editarMedicamento('.$idMedicamento.', this, 5, \''.$idRol.'\')" class="form-control mb-3" value="'.$medico.'" placeholder="Escribe aquí el nombre del medico...">
</div>';

$result .= '   <div class="col-12 mt-5">
<div class="row">

<div class="col-6 text-center">
<img src="'.RUTA_IMAGES.'/iconos/eliminar-icon.png"onclick="eliminarMedicamentoPacientes('.$idMedicamento.', \''.$idRol.'\')" class="img-fluid pointer mb-2" style="width: 35%;">
<div class="mt-2"><h5 class="text-danger fw-bold">Eliminar medicamento</h5></div>
</div>

<div class="col-6 text-center btnAgregarMedicamento">
<img src="'.RUTA_IMAGES.'/iconos/agregar-icon.png" onclick="agregarMedicamentoPaciente('.$idPaciente.', \''.$idRol.'\')" class="img-fluid pointer mb-2" style="width: 35%;" style="display: none;">
<div class="mt-2"><h5 class="text-success fw-bold">Agregar medicamento</h5></div>
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
$result .= $botonFinalizar;
}

$result .= '
</div>
</div>';

$result .= '</div>';
endforeach;
$result .= '</div>';
$result .= '</div>';

}else{
 
$result .= '
<div class="card-header pb-0">
<div class="row">

<div id="seccion100" data-autoplay="false" class="col-12 col-md-11 d-flex align-items-center sectionQuestion mb-4">
<img src="'.RUTA_IMAGES.'/iconos/audio.png" onclick="readQuestion()" class="img-fluid btnLeer pointer" style="max-height: 30px; margin-right: 10px;" data-target="seccion100">

<h8 class="text-primary fw-bold texto">
<b>Si usted está tomando medicamentos actualmente (con excepción de aquellos utilizados únicamente para el control del dolor), presione el botón verde para registrar cada uno de ellos.
En caso de que no esté tomando ningún medicamento en este momento, seleccione el botón “Finalizar”: </b>
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
<img src="'.RUTA_IMAGES.'/iconos/agregar-icon.png" onclick="agregarMedicamentoPaciente('.$idPaciente.',\''.$idRol.'\')"class="img-fluid btnLeer pointer" style="max-height: 90%;"></div>
</div> 
<div class="col-12 text-end">
'.$botonFinalizar.'
</div>
    
</div>';
    
}



}else{

$result .= '
<div class="card-header pb-0">
<div class="row">

<div class="col-11">
<h8 class="text-primary fw-bold texto">
<b>A continuación, ingrese la información correspondiente a cada medicamento que el paciente esté utilizando, incluyendo el nombre del medicamento, el motivo de uso, el tiempo que ha estado en tratamiento, la dosis actual y el nombre del médico que lo recetó:</b>
</h8>
</div>

<div class="col-1">
<button id="btnAgregarMedicamento" onclick="agregarMedicamentoPaciente('.$idPaciente.', \''.$idRol.'\')" class="btn icon btn-success float-end">
<i data-feather="plus" width="20"></i>
</button>
</div>

<div class="col-12">
<div id="mensaje" class="text-center text-danger mt-2"></div>
</div>

</div>
</div> 

<div class="card-body ">
<div class="col-12">
<div class="table-responsive">
<table class="table table-striped" id="table_medicacion">
<thead>
<tr>
<th class="text-center align-middle">Nombre del medicamento</th>
<th class="text-center align-middle">¿Para qué lo utiliza?</th>
<th class="text-center align-middle">Tiempo que ha estado en tratamiento</th>
<th class="text-center align-middle">Dosis actual</th>
<th class="text-center align-middle">Nombre del médico que lo recetó</th>
<th class="text-center align-middle"><i data-feather="trash-2"></i></th>
</tr>
</thead>
<tbody>';   

foreach ($preguntas as $index => $pregunta) :
$idMedicamento = $pregunta['id'];
$medicamento = $pregunta['medicamento'];
$descripcion = $pregunta['descripcion'];
$tiempo_uso = $pregunta['tiempo_uso'];
$dosis = $pregunta['dosis'];
$medico = $pregunta['medico'];

$result.= '<tr>';
   
$result .= '<td class="text-center align-middle p-2">
<span class="d-none search-value">'.$descripcion.'</span>
<input type="text" onchange="editarMedicamento('.$idMedicamento.', this, 1, \''.$idRol.'\')" class="form-control text-center align-middle" value="'.$descripcion.'" placeholder="Escribe aquí el nombre del medicamento...">
</td>';

$result .= '<td class="text-center align-middle p-2">
<span class="d-none search-value">'.$medicamento.'</span>
<input type="text" onchange="editarMedicamento('.$idMedicamento.', this, 2, \''.$idRol.'\')" class="form-control text-center align-middle" value="'.$medicamento.'" placeholder="Escribe aquí el motivo de medicación...">
</td>';

$result .= '<td class="text-center align-middle p-2">
<span class="d-none search-value">'.$tiempo_uso.'</span>
<input type="text" onchange="editarMedicamento('.$idMedicamento.', this, 3, \''.$idRol.'\')" class="form-control text-center align-middle" value="'.$tiempo_uso.'" placeholder="Escribe aquí el tiempo de tratamiento...">
</td>';

$result .= '<td class="text-center align-middle p-2">
<span class="d-none search-value">'.$dosis.'</span>
<input type="text" onchange="editarMedicamento('.$idMedicamento.', this, 4, \''.$idRol.'\')" class="form-control text-center align-middle" value="'.$dosis.'" placeholder="Escribe aquí la dosis que utiliza...">
</td>';

$result .= '<td class="text-center align-middle p-2">
<span class="d-none search-value">'.$medico.'</span>
<input type="text" onchange="editarMedicamento('.$idMedicamento.', this, 5, \''.$idRol.'\')" class="form-control text-center align-middle" value="'.$medico.'" placeholder="Escribe aquí el nombre del medico...">
</td>';

$result.= '<td class="text-center align-middle p-2">
<i data-feather="trash-2" class="pointer" onclick="eliminarMedicamentoPacientes('.$idMedicamento.', \''.$idRol.'\')"></i>
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


//----- AGREGAR MEDICACION PACIENTE ----------
public function agregarMedicacionPaciente($data){
$sql = "INSERT INTO pac_medicacion_actual_modulo_6 (id_paciente) VALUES (:id_paciente)"; 
$stmt = $this->bd->prepare($sql);                    
        
$datos = [
':id_paciente' => $data['idPaciente']
];
    
if ($stmt->execute($datos)) {
return array('resultado' => 200,'mensaje' => '¡Se habilito una nuevo medicamento correctamente!');
    
} else {
return array('resultado' => 401,'mensaje' => '¡Error al agregar la nueva cirugia a la lista!');
}
    
}

//----- ELIMINAR MEDICACION PACIENTE ----------

public function editarMedicamentoPaciente($data){
$opcionEdicion = $data['edicion'];

if($opcionEdicion == 1){
$consulta = "descripcion";
}else if($opcionEdicion == 2){
$consulta = "medicamento";
}else if($opcionEdicion == 3){
$consulta = "tiempo_uso";
}else if($opcionEdicion == 4){
$consulta = "dosis";
}else if($opcionEdicion == 5){
$consulta = "medico";
}
    
$sql = "UPDATE pac_medicacion_actual_modulo_6 SET 
$consulta = :detalle WHERE id = :idMedicamento";
    
$stmt = $this->bd->prepare($sql);                    
                
$datos = [
':idMedicamento' => $data['idMedicamento'],
':detalle' => $data['detalle']
];
            
if ($stmt->execute($datos)) {
return array('resultado' => 200,'mensaje' => '¡Se actualizo la informacion correctamente!');
            
} else {
return array('resultado' => 401,'mensaje' => '¡Error al actualizar la cirugia de la lista!');
}
            
}

//----- ELIMINAR MEDICACION PACIENTE ----------
public function eliminarMedicamentoPaciente($data){

$sql = "DELETE FROM pac_medicacion_actual_modulo_6 WHERE id = :idMedicamento";
$stmt = $this->bd->prepare($sql);                    
            
$datos = [
':idMedicamento' => $data['idMedicamento']
];
        
if ($stmt->execute($datos)) {
return array('resultado' => 200,'mensaje' => '¡Se elimino el medicamento correctamente!');
        
} else {
return array('resultado' => 401,'mensaje' => '¡Error al eliminar el medicamento de la lista!');
}
        
}

}