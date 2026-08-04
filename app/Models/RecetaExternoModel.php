<?php
namespace App\Models;

use App\Config\Database;

class RecetaExternoModel{

private $bd;
private $nombre;
private $apellido_paterno;
private $apellido_materno;
private $edad;
private $sexo;
private $fecha_nacimiento;
private $diagnostico;
private $medicamento;
private $ta;
private $fc;
private $spo2;
private $temperatura;
private $fecha;
private $hora;

public function __construct(){
$this->bd = Database::getInstance();
}

public function receta($id){
$query = "SELECT * FROM receta_medica_externos WHERE id = :id";
$stmt = $this->bd->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$registros = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!empty($registros['fecha_hora'])) {
$fechaHora = new \DateTime($registros['fecha_hora']);
$this->fecha = $fechaHora->format('d/m/Y');
$this->hora = $fechaHora->format('h:i a');
} else {
$this->fecha = null;
$this->hora = null;
}

$this->nombre = $registros['nombre'] ?? null;
$this->apellido_paterno = $registros['apellido_paterno'] ?? null;
$this->apellido_materno = $registros['apellido_materno'] ?? null;
$this->edad = $registros['edad'] ?? null;
$this->sexo = $registros['sexo'] ?? null;
$this->fecha_nacimiento = $registros['fecha_nacimiento'] ?? null;
$this->diagnostico = $registros['diagnostico'] ?? null;
$this->medicamento = $registros['medicamento'] ?? null;
$this->ta = $registros['ta'] ?? null;
$this->fc = $registros['fc'] ?? null;
$this->spo2 = $registros['spo2'] ?? null;
$this->temperatura = $registros['temperatura'] ?? null;
}

public function getFecha(){
return $this->fecha;
}

public function getHora(){
return $this->hora;
}

public function getNombre(){
return $this->nombre;
}

public function getApellidoPaterno(){
return $this->apellido_paterno;
}

public function getApellidoMaterno(){
return $this->apellido_materno;
}

public function getNombreCompleto(){
return trim($this->nombre . ' ' . $this->apellido_paterno . ' ' . $this->apellido_materno);
}

public function getEdad(){
return $this->edad;
}

public function getSexo(){
return $this->sexo;
}

public function getFechaNacimiento(){
return $this->fecha_nacimiento;
}

public function getDiagnostico(){
return $this->diagnostico;
}

public function getMedicamento(){
return $this->medicamento;
}

public function getTA(){
return $this->ta;
}

public function getFC(){
return $this->fc;
}

public function getSPO2(){
return $this->spo2;
}

public function getTemperatura(){
return $this->temperatura;
}

public function insert($data){
$sql = "INSERT INTO receta_medica_externos (
nombre, apellido_paterno, apellido_materno, edad, sexo, fecha_nacimiento,
diagnostico, medicamento, ta, fc, spo2, temperatura
) VALUES (
:nombre, :apellido_paterno, :apellido_materno, :edad, :sexo, :fecha_nacimiento,
:diagnostico, :medicamento, :ta, :fc, :spo2, :temperatura
)";

$stmt = $this->bd->prepare($sql);
$datos = [
':nombre'            => $data['nombre'],
':apellido_paterno'  => $data['apellido_paterno'],
':apellido_materno'  => $data['apellido_materno'] ?? null,
':edad'              => $data['edad'] ?? null,
':sexo'              => $data['sexo'] ?? null,
':fecha_nacimiento'  => $data['fecha_nacimiento'] ?? null,
':diagnostico'       => $data['diagnostico'],
':medicamento'       => $data['medicamento'],
':ta'                => $data['ta'] ?? null,
':fc'                => $data['fc'] ?? null,
':spo2'              => $data['spo2'] ?? null,
':temperatura'       => $data['temperatura'] ?? null
];

if ($stmt->execute($datos)) {
return ['resultado' => 200, 'mensaje' => $this->bd->lastInsertId()];
} else {
return ['resultado' => 401, 'mensaje' => '¡Error al agregar nueva receta!'];
}
}

public function update($data){
$sql = "UPDATE receta_medica_externos SET
nombre = :nombre,
apellido_paterno = :apellido_paterno,
apellido_materno = :apellido_materno,
edad = :edad,
sexo = :sexo,
fecha_nacimiento = :fecha_nacimiento,
diagnostico = :diagnostico,
medicamento = :medicamento,
ta = :ta,
fc = :fc,
spo2 = :spo2,
temperatura = :temperatura
WHERE id = :id";

$stmt = $this->bd->prepare($sql);
$datos = [
':id'                 => $data['id'],
':nombre'             => $data['nombre'],
':apellido_paterno'   => $data['apellido_paterno'],
':apellido_materno'   => $data['apellido_materno'] ?? null,
':edad'               => $data['edad'] ?? null,
':sexo'               => $data['sexo'] ?? null,
':fecha_nacimiento'   => $data['fecha_nacimiento'] ?? null,
':diagnostico'        => $data['diagnostico'] ?? null,
':medicamento'        => $data['medicamento'] ?? null,
':ta'                 => $data['ta'] ?? null,
':fc'                 => $data['fc'] ?? null,
':spo2'               => $data['spo2'] ?? null,
':temperatura'        => $data['temperatura'] ?? null
];

if ($stmt->execute($datos)) {
return ['resultado' => 200, 'mensaje' => 'Datos actualizados correctamente'];
} else {
return ['resultado' => 401, 'mensaje' => '¡Error al actualizar los datos!'];
}
}

public function mostrarTablaRecetas(){
$result = '';
$stmt = $this->bd->query("SELECT * FROM receta_medica_externos ORDER BY fecha_hora DESC");
$registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$result .= '<table class="table table-striped table-hover table-sm pb-0 mb-0" id="tableRecetasExternos">
<thead>
<tr>
<th class="text-center">#</th>
<th>Paciente</th>
<th>Fecha y Hora</th>
</tr>
</thead>
<tbody>';

foreach ($registros as $registro):
$nombreCompleto = trim($registro['nombre'] . ' ' . $registro['apellido_paterno'] . ' ' . $registro['apellido_materno']);
$fecha_hora = (new \DateTime($registro['fecha_hora']))->format('d/m/Y h:i a');

$result .= '<tr onclick="window.location.href=\'/clinica/receta-externo/'.$registro['id'].'\'" style="cursor:pointer">
<td class="text-center">'.$registro['id'].'</td>
<td>'.$nombreCompleto.'</td>
<td>'.$fecha_hora.'</td>
</tr>';
endforeach;

$result .= '</tbody>
</table>';

return $result;
}

public function getReceta($id){
$result = '';

$sql = "SELECT * FROM receta_medica_externos WHERE id = :id";
$stmt = $this->bd->prepare($sql);
$stmt->execute([':id' => $id]);

if ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
$nombreCompleto = trim($data['nombre'] . ' ' . $data['apellido_paterno'] . ' ' . $data['apellido_materno']);
$fecha = (new \DateTime($data['fecha_hora']))->format('d/m/Y');
$hora = (new \DateTime($data['fecha_hora']))->format('h:i a');

$result .= '<div class="float-end"><a href="/clinica/receta-externo/'.$data['id'].'" class="btn icon btn-primary"><i data-feather="external-link"></i></a></div>';

$result .= '<div><small class="text-primary">Paciente: </small> <label class="fs-5">' . $nombreCompleto . '</label></div>';

if (!empty($data['edad'])):
$result .= '<div><small class="text-primary">Edad: </small> <label class="fs-5">' . $data['edad'] . ' años</label></div>';
endif;

if (!empty($data['sexo'])):
$result .= '<div><small class="text-primary">Sexo: </small> <label class="fs-5">' . ($data['sexo'] == 'M' ? 'Masculino' : 'Femenino') . '</label></div>';
endif;

$result .= '<div><small class="text-primary">Fecha: </small> <label class="fs-5">' . $fecha . '</label>, <small class="text-primary">Hora: </small> <label class="fs-5">' . $hora . '</label></div>';

$result .= '<div class="mt-3"><small class="text-primary">Diagnostico:</small> <label class="fs-5">' . $data['diagnostico'] . '</label></div>

<div class="mt-4">
<small class="text-primary">Signos Vitales:</small>

<div class="row mt-2">
<div class="col-md-6 mb-2">
<small class="text-muted">Tensión Arterial (TA):</small><br>
<label class="fs-5">'.(!empty($data['ta']) ? $data['ta'].' mmHg' : 'S/I').'</label>
</div>

<div class="col-md-6 mb-2">
<small class="text-muted">Frecuencia Cardíaca (FC):</small><br>
<label class="fs-5">'.(!empty($data['fc']) ? $data['fc'].' lpm' : 'S/I').'</label>
</div>

<div class="col-md-6 mb-2">
<small class="text-muted">Saturación de Oxígeno (SpO₂):</small><br>
<label class="fs-5">'.(!empty($data['spo2']) ? $data['spo2'].' %' : 'S/I').'</label>
</div>

<div class="col-md-6 mb-2">
<small class="text-muted">Temperatura</small><br>
<label class="fs-5">'.(!empty($data['temperatura']) ? $data['temperatura'].' °C' : 'S/I').'</label>
</div>
</div>
</div>

<label class="mt-3"><small class="text-primary">Medicamento: </small></label>
<div class="fs-5">'.$data['medicamento'].'</div>';
} else {
$result = '<div class="text-center p-4 text-light">No se encontró información.</div>';
}

return $result;
}

}
 