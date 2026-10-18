<?php
namespace App\Models;
use App\Config\Database;
use App\Models\PacienteModulosModelo;
use PDO;

class MedicacionActualModel{
private $bd;

public function __construct(?PDO $db = null){
$this->bd = $db ?: Database::getInstance();

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

    /**
     * Patient-facing renderer for module 6. The legacy Doctor renderer is
     * intentionally left untouched; this method is scoped to P08.
     */
    public function mostrarPreguntasM6Paciente(int $idPaciente): string
    {
        $statement = $this->bd->prepare(
            'SELECT id, medicamento, descripcion, tiempo_uso, dosis, medico
             FROM pac_medicacion_actual_modulo_6
             WHERE id_paciente = :id_paciente
             ORDER BY id ASC'
        );
        $statement->execute([':id_paciente' => $idPaciente]);
        $records = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $escape = static fn ($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
        $finalizedStatement = $this->bd->prepare(
            'SELECT 1 FROM pac_historia_clinica_finalizar
             WHERE id_paciente = :id_paciente AND id_modulo = 6 LIMIT 1'
        );
        $finalizedStatement->execute([':id_paciente' => $idPaciente]);
        $finalized = (bool)$finalizedStatement->fetchColumn();
        $instruction = 'Ingresa el nombre del medicamento, para qué lo utilizas, el tiempo de tratamiento, la dosis actual y el nombre del médico que lo recetó.';
        $result = '<div class="p08-instruction" id="p08QuestionText">' . $escape($instruction) . '</div>';
        $result .= '<div class="p08-message" id="mensaje" role="status" aria-live="polite"></div>';
        $result .= '<div class="p08-toolbar">'
            . '<button type="button" class="btn p08-voice-button" data-action="read-question" aria-label="Escuchar instrucciones">'
            . '<i data-feather="volume-2" aria-hidden="true"></i><span>Escuchar</span></button>'
            . '<button type="button" class="btn p08-voice-button" data-action="stop-voice" hidden aria-label="Detener lectura">'
            . '<i data-feather="square" aria-hidden="true"></i><span>Detener</span></button>'
            . '</div>';

        if (!$records) {
            $result .= '<section class="p08-empty-state" aria-labelledby="p08EmptyTitle">'
                . '<i data-feather="clipboard" class="p08-empty-icon" aria-hidden="true"></i>'
                . '<h3 id="p08EmptyTitle">No hay medicamentos registrados</h3>'
                . '<p>Si utilizas medicamentos actualmente, agrega un registro para completar esta sección.</p>'
                . '<div class="p08-empty-actions"><button type="button" class="btn btn-primary p08-add-button" data-action="add">'
                . '<i data-feather="plus" aria-hidden="true"></i><span>Agregar medicamento</span></button>'
                . ($finalized ? '<span class="p08-finalized" role="status">Módulo finalizado</span>'
                    : '<button type="button" class="btn p08-secondary-button" data-action="finalize">Finalizar módulo</button>')
                . '</div></section>';
            return $result;
        }

        $result .= '<div class="p08-list-toolbar"><p class="p08-list-summary">'
            . $escape(count($records)) . ' ' . (count($records) === 1 ? 'medicamento registrado' : 'medicamentos registrados')
            . '</p><button type="button" class="btn btn-primary p08-add-button" data-action="add">'
            . '<i data-feather="plus" aria-hidden="true"></i><span>Agregar medicamento</span></button></div>';
        $result .= '<div class="p08-records" data-record-count="' . $escape(count($records)) . '">';
        foreach ($records as $index => $record) {
            $id = (int)$record['id'];
            $result .= '<article class="p08-record" data-index="' . $escape($index) . '" data-id="' . $escape($id) . '" aria-hidden="true">'
                . '<div class="p08-record-heading"><div><p class="p08-record-eyebrow">MEDICACIÓN ACTUAL</p>'
                . '<h3>Registro ' . $escape($index + 1) . '</h3></div><span class="p08-record-status">Por completar</span></div>'
                . '<p class="p08-record-help">Completa los cinco datos del medicamento y guarda los cambios antes de continuar.</p>'
                . '<div class="p08-fields">';
            $fields = [
                'descripcion' => ['Nombre del medicamento', 'Escribe el nombre del medicamento'],
                'medicamento' => ['¿Para qué lo utiliza?', 'Escribe el motivo de uso'],
                'tiempo_uso' => ['Tiempo en tratamiento', 'Escribe el tiempo de tratamiento'],
                'dosis' => ['Dosis actual', 'Escribe la dosis actual'],
                'medico' => ['Nombre del médico que lo recetó', 'Escribe el nombre del médico'],
            ];
            foreach ($fields as $field => [$label, $placeholder]) {
                $result .= '<div class="p08-field"><label for="p08-' . $escape($id) . '-' . $escape($field) . '">' . $escape($label) . '</label>'
                    . '<input id="p08-' . $escape($id) . '-' . $escape($field) . '" class="form-control" type="text" data-p08-field="' . $escape($field) . '" value="' . $escape($record[$field] ?? '') . '" placeholder="' . $escape($placeholder) . '"></div>';
            }
            $result .= '</div><div class="p08-record-actions">'
                . '<button type="button" class="btn p08-danger-button" data-action="delete"><i data-feather="trash-2" aria-hidden="true"></i><span>Eliminar</span></button>'
                . '<button type="button" class="btn btn-primary p08-save-button" data-action="save"><i data-feather="save" aria-hidden="true"></i><span>Guardar cambios</span></button>'
                . '</div><div class="p08-navigation">'
                . '<button type="button" class="btn p08-secondary-button" data-action="previous"><i data-feather="chevron-left" aria-hidden="true"></i><span>Anterior</span></button>'
                . '<span class="p08-navigation-status">Registro ' . $escape($index + 1) . ' de ' . $escape(count($records)) . '</span>'
                . '<button type="button" class="btn p08-secondary-button" data-action="next"><span>Siguiente</span><i data-feather="chevron-right" aria-hidden="true"></i></button>'
                . '</div></article>';
        }
        $result .= '</div><div class="p08-finalization">';
        if ($finalized) {
            $result .= '<span class="p08-finalized" role="status">Módulo finalizado</span>';
        } else {
            $result .= '<button type="button" class="btn p08-secondary-button" data-action="finalize">Finalizar módulo</button>';
        }
        $result .= '</div>';
        return $result;
    }


//----- AGREGAR MEDICACION PACIENTE ----------
public function agregarMedicacionPaciente($data){
$idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($idPaciente === false) {
return ['resultado' => 422, 'mensaje' => 'Datos de paciente no válidos.'];
}
$sql = "INSERT INTO pac_medicacion_actual_modulo_6 (id_paciente) VALUES (:id_paciente)"; 
$stmt = $this->bd->prepare($sql);                    
        
$datos = [
':id_paciente' => $idPaciente
];
    
if ($stmt->execute($datos)) {
return array('resultado' => 200,'mensaje' => '¡Se habilito una nuevo medicamento correctamente!');
    
} else {
return array('resultado' => 401,'mensaje' => '¡Error al agregar la nueva cirugia a la lista!');
}
    
}

//----- ELIMINAR MEDICACION PACIENTE ----------

public function editarMedicamentoPaciente($data){
$idMedicamento = filter_var($data['idMedicamento'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($idMedicamento === false || $idPaciente === false) {
return ['resultado' => 422, 'mensaje' => 'Datos de medicación no válidos.'];
}
$owned = $this->bd->prepare('SELECT id, medicamento, descripcion, tiempo_uso, dosis, medico FROM pac_medicacion_actual_modulo_6 WHERE id = :id AND id_paciente = :id_paciente LIMIT 1');
$owned->execute([':id' => $idMedicamento, ':id_paciente' => $idPaciente]);
$current = $owned->fetch(\PDO::FETCH_ASSOC);
if (!$current) return ['resultado' => 403, 'mensaje' => 'No tienes autorización para modificar este registro.'];

$columns = ['descripcion', 'medicamento', 'tiempo_uso', 'dosis', 'medico'];
$updates = [];
if (isset($data['fields']) && is_array($data['fields'])) {
    foreach ($columns as $column) {
        if (array_key_exists($column, $data['fields'])) $updates[$column] = trim((string)$data['fields'][$column]);
    }

} elseif (isset($data['edicion'])) {
    $legacyMap = [1 => 'descripcion', 2 => 'medicamento', 3 => 'tiempo_uso', 4 => 'dosis', 5 => 'medico'];
    $option = filter_var($data['edicion'], FILTER_VALIDATE_INT);
    if (!isset($legacyMap[$option])) return ['resultado' => 422, 'mensaje' => 'Campo de medicación no válido.'];
    $updates[$legacyMap[$option]] = trim((string)($data['detalle'] ?? ''));
}
if (!$updates) return ['resultado' => 422, 'mensaje' => 'No hay cambios de medicación para guardar.'];
$changed = [];
$values = [];
foreach ($updates as $column => $value) {
    if ((string)$current[$column] !== $value) {
        $changed[$column] = $value;
        $values[':'.$column] = $value;
    }
}
if (!$changed) return ['resultado' => 200, 'mensaje' => 'No hubo cambios que guardar.'];
$set = implode(', ', array_map(static fn($column): string => $column . ' = :' . $column, array_keys($changed)));
$values[':id'] = $idMedicamento;
$values[':id_paciente'] = $idPaciente;
$stmt = $this->bd->prepare("UPDATE pac_medicacion_actual_modulo_6 SET {$set} WHERE id = :id AND id_paciente = :id_paciente");
$stmt->execute($values);
return ['resultado' => 200, 'mensaje' => 'Información guardada correctamente.'];
}

    /** Idempotent finalization used only by the P08 patient flow. */
    public function finalizarModuloPaciente(array $data): array
    {
        $idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idPaciente === false) return ['resultado' => 422, 'mensaje' => 'Datos de paciente no válidos.'];
        $existing = $this->bd->prepare(
            'SELECT id FROM pac_historia_clinica_finalizar WHERE id_paciente = :id_paciente AND id_modulo = 6 LIMIT 1'
        );
        $existing->execute([':id_paciente' => $idPaciente]);
        if ($existing->fetchColumn() !== false) return ['resultado' => 200, 'mensaje' => 'El módulo ya estaba finalizado.'];
        $insert = $this->bd->prepare(
            'INSERT INTO pac_historia_clinica_finalizar (id_modulo, id_paciente) VALUES (6, :id_paciente)'
        );
        $insert->execute([':id_paciente' => $idPaciente]);
        return ['resultado' => 200, 'mensaje' => 'Módulo finalizado correctamente.'];
    }

//----- ELIMINAR MEDICACION PACIENTE ----------
public function eliminarMedicamentoPaciente($data){
$idMedicamento = filter_var($data['idMedicamento'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$idPaciente = filter_var($data['idPaciente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($idMedicamento === false || $idPaciente === false) return ['resultado' => 422, 'mensaje' => 'Datos de medicación no válidos.'];
$sql = "DELETE FROM pac_medicacion_actual_modulo_6 WHERE id = :idMedicamento AND id_paciente = :id_paciente";
$stmt = $this->bd->prepare($sql);                    
            
$datos = [
':idMedicamento' => $idMedicamento,
':id_paciente' => $idPaciente
];
        
if ($stmt->execute($datos)) {
if ($stmt->rowCount() === 0) return ['resultado' => 403, 'mensaje' => 'No tienes autorización para eliminar este registro.'];
return array('resultado' => 200,'mensaje' => 'Medicamento eliminado correctamente.');
        
} else {
return array('resultado' => 401,'mensaje' => '¡Error al eliminar el medicamento de la lista!');
}
        
}

}
