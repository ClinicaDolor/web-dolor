<?php

namespace App\Models;

use App\Config\Database;

class RecetaModel{

private $bd;
private $fecha;
private $hora;
private $id_paciente;
private $diagnostico;
private $medicamento;
private $ta;
private $fc;
private $spo2;
private $temperatura;

public function __construct(){
$this->bd = Database::getInstance();

}

public function idRecetaReferencia($idPaciente,$referencia){

$query = "SELECT id FROM receta_medica WHERE id_paciente = :id_paciente AND codigo_referencia = :referencia LIMIT 1";
$stmt = $this->bd->prepare($query);
$stmt->execute([
    ':id_paciente' => (int)$idPaciente,
    ':referencia' => (string)$referencia
]);
$data = $stmt->fetch(\PDO::FETCH_ASSOC);

return $data['id'] ?? 0;

}

public function belongsToPatientReference(int $idReceta, int $idPaciente, string $referencia): bool
{
    if ($idReceta < 1 || $idPaciente < 1 || $referencia === '') {
        return false;
    }

    $stmt = $this->bd->prepare(
        'SELECT id FROM receta_medica
         WHERE id = :id AND id_paciente = :id_paciente AND codigo_referencia = :referencia
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $idReceta,
        ':id_paciente' => $idPaciente,
        ':referencia' => $referencia
    ]);

    return (bool)$stmt->fetch(\PDO::FETCH_ASSOC);
}

public function receta($idReceta){

$query = "SELECT * FROM receta_medica WHERE id = :id";
$stmt = $this->bd->prepare($query);
$stmt->bindParam(':id', $idReceta);
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

$this->id_paciente = $registros['id_paciente'] ?? null;
$this->diagnostico = $registros['diagnostico'] ?? null;
$this->medicamento = $registros['medicamento'] ?? null;

$this->ta = $registros['ta'] ?? null;
$this->fc = $registros['fc'] ?? null;
$this->spo2 = $registros['spo2'] ?? null;
$this->temperatura = $registros['temperatura'] ?? null;

}

//----------------------------------------------------------
//----------------------------------------------------------
public function getFecha()
{
return $this->fecha;
}

public function getHora()
{
return $this->hora;
}

public function getIdPaciente()
{
return $this->id_paciente;
}

public function getDiagnostico()
{
return $this->diagnostico;
}

public function getMedicamento()
{
return $this->medicamento;
}

public function getTA()
{
return $this->ta;
}

public function getFC()
{
return $this->fc;
}

public function getSPO2()
{
return $this->spo2;
}

public function getTemperatura()
{
return $this->temperatura;
}

/**
 * Obtiene únicamente las recetas internas pertenecientes a una clínica.
 * La pertenencia se resuelve mediante el paciente propietario; nunca se
 * confía en un identificador de clínica enviado por la petición.
 */
public function getRecetasByClinica($idClinica){
$idClinica = filter_var($idClinica, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($idClinica === false) {
return [];
}

$sql = "SELECT
    r.id,
    r.id_paciente,
    r.fecha_hora,
    CONCAT_WS(' ', p.nombres, p.apellido_paterno, p.apellido_materno) AS paciente
FROM receta_medica r
INNER JOIN pc_paciente p ON p.id = r.id_paciente
WHERE p.id_clinica = :id_clinica
ORDER BY r.fecha_hora DESC";

$stmt = $this->bd->prepare($sql);
$stmt->execute([':id_clinica' => $idClinica]);

return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}
//----------------------------------------------------------
//----------------------------------------------------------

public function mostrarTablaRecetas($idPaciente){
$result = '';
$idPaciente = filter_var($idPaciente, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($idPaciente === false) {
return $result;
}

$stmt = $this->bd->prepare("SELECT * FROM receta_medica WHERE id_paciente = :id_paciente ORDER BY fecha_hora DESC");
$stmt->execute([':id_paciente' => $idPaciente]);
$registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$result .= '<table class="table table-striped table-hover table-sm pb-0 mb-0" id="tableRecetas">
<thead>
<tr>
<th class="text-center">#</th>
<th>Fecha y Hora</th>
</tr>
</thead>
<tbody>';

foreach ($registros as $registro):

$fecha_hora = (new \DateTime(datetime: $registro['fecha_hora']))->format('d/m/Y h:i a');

$result .= '<tr onclick="DetalleReceta('.$registro['id'].')">
<td class="text-center">'.$registro['id'].'</td>
<td>'.$fecha_hora.'</td>
</tr>';

endforeach;

$result .= '</tbody>
</table>';

return $result;
}

public function ultimaReceta($idPaciente){

$result = '';

$sql = "SELECT id FROM receta_medica WHERE id_paciente = :id ORDER BY id DESC LIMIT 1";
$stmt = $this->bd->prepare($sql);
$stmt->execute([':id' => $idPaciente]);

$data = $stmt->fetch(\PDO::FETCH_ASSOC);
$idReceta = isset($data['id']) && !empty($data['id']) ? $data['id'] : 0;
$result = $this->getReceta($idReceta);

return $result;

}

private static function escapeDisplayText($value): string
{
return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Conserva el formato que produce el editor Quill sin permitir que el
 * detalle de una receta ejecute HTML o atributos suministrados por el dato.
 * El almacenamiento y el PDF no utilizan esta representación de pantalla.
 */
public static function sanitizeMedicationHtml(string $html): string
{
$html = trim($html);
if ($html === '') {
return '';
}

if (!class_exists(\DOMDocument::class)) {
return self::escapeDisplayText($html);
}

$allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ol', 'ul', 'li'];
$document = new \DOMDocument('1.0', 'UTF-8');
$previousLibxmlState = libxml_use_internal_errors(true);

try {
$loaded = $document->loadHTML(
    '<?xml encoding="UTF-8"><div id="recipe-medication-root">' . $html . '</div>',
    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
);

if (!$loaded) {
return self::escapeDisplayText($html);
}

$root = $document->getElementsByTagName('div')->item(0);
if (!$root) {
return self::escapeDisplayText($html);
}

self::sanitizeMedicationChildren($root, $allowedTags);
$safeHtml = '';
foreach ($root->childNodes as $child) {
$safeHtml .= $document->saveHTML($child);
}

return $safeHtml;
} finally {
libxml_clear_errors();
libxml_use_internal_errors($previousLibxmlState);
}
}

private static function sanitizeMedicationChildren(\DOMNode $parent, array $allowedTags): void
{
for ($child = $parent->firstChild; $child !== null;) {
$next = $child->nextSibling;

if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
$parent->removeChild($child);
$child = $next;
continue;
}

if ($child instanceof \DOMElement) {
$tagName = strtolower($child->tagName);

if (!in_array($tagName, $allowedTags, true)) {
if (in_array($tagName, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'noscript', 'template'], true)) {
$parent->removeChild($child);
$child = $next;
continue;
}

while ($child->firstChild !== null) {
$parent->insertBefore($child->firstChild, $child);
}
$parent->removeChild($child);
$child = $next;
continue;
}

for ($index = $child->attributes->length - 1; $index >= 0; $index--) {
$attribute = $child->attributes->item($index);
if ($attribute !== null) {
$child->removeAttribute($attribute->name);
}
}

self::sanitizeMedicationChildren($child, $allowedTags);
}

$child = $next;
}
}

public function getReceta($idReceta, $fecha_hora = true){
$result = '';

$sql = "SELECT id, fecha_hora, diagnostico, medicamento, ta, fc, spo2, temperatura FROM receta_medica WHERE id = :id";
$stmt = $this->bd->prepare($sql);
$stmt->execute([':id' => $idReceta]);  

if ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {

$fecha = (new \DateTime($data['fecha_hora']))->format('d/m/Y');
$hora = (new \DateTime($data['fecha_hora']))->format('h:i a');
$diagnostico = self::escapeDisplayText($data['diagnostico'] ?? '');
$medicamento = self::sanitizeMedicationHtml((string)($data['medicamento'] ?? ''));
$displayValue = static function ($value, string $suffix = ''): string {
return !empty($value) ? self::escapeDisplayText($value) . $suffix : 'S/I';
};

$result .= '<div class="float-end"><a href="javascript:void(0)" onclick="imprimirReceta(' . (int)$data['id'] . ', \'registrado\')" class="btn icon btn-primary"><i data-feather="printer"></i></a></div>';

if($fecha_hora){
$result .= '<div><small class="text-primary">Fecha: </small> <label class="fs-5">' . $fecha . '</label>, <small class="text-primary">Hora: </small> <label class="fs-5">' . $hora . '</label></div>';   
}

$result .= '<div class="mt-3"><small class="text-primary">Diagnostico:</small> <label class="fs-5">' . $diagnostico . '</label></div>

<div class="mt-4">
<small class="text-primary">Signos Vitales:</small>

<div class="row mt-2">

<div class="col-md-6 mb-2">
<small class="text-muted">Tensión Arterial (TA):</small><br>
<label class="fs-5">'.$displayValue($data['ta'] ?? null, ' mmHg').'</label>
</div>

<div class="col-md-6 mb-2">
<small class="text-muted">Frecuencia Cardíaca (FC):</small><br>
<label class="fs-5">'.$displayValue($data['fc'] ?? null, ' lpm').'</label>
</div>

<div class="col-md-6 mb-2">
<small class="text-muted">Saturación de Oxígeno (SpO₂):</small><br>
<label class="fs-5">'.$displayValue($data['spo2'] ?? null, ' %').'</label>
</div>

<div class="col-md-6 mb-2">
<small class="text-muted">Temperatura</small><br>
<label class="fs-5">'.$displayValue($data['temperatura'] ?? null, ' °C').'</label>
</div>

</div>
</div>

<label class="mt-3"><small class="text-primary">Medicamento: </small></label>
<div class="fs-5">'.$medicamento.'</div>';

} else {
$result = '<div class="text-center p-4 text-light">No se encontró información.</div>';
}

return $result;
}


}
