<?php 
namespace App\Controllers;

use App\Models\CofeprisModel;
use App\Models\RecetaModel;
use App\Models\RecetaExternoModel;
use App\Models\PacienteModel;
use App\Helpers\CalculadoraEdad;

use setasign\Fpdi\Tcpdf\Fpdi;

class PdfController{

public function pdfReceta($idReceta){

$model = new RecetaModel();
$model->receta($idReceta);

$paciente = new PacienteModel($model->getIdPaciente());      
$edad = CalculadoraEdad::calcularEdad($paciente->getFechaNacimiento());
$textoFormateado = $this->htmlToMultiCell($model->getMedicamento());

$pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'LETTER', true, 'UTF-8', false);
//$pdf = new \TCPDF('L', 'mm', array(216, 140), true, 'UTF-8', false);

$pdf->SetCreator('Clinica');
$pdf->SetTitle('Receta');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetMargins(5, 0, 5);
$pdf->SetAutoPageBreak(false, 0); 

$pdf->AddPage();

$conFormato = isset($_POST['con_formato']) ? (int)$_POST['con_formato'] : 1;

// 1. Configuración de coordenadas según el formato elegido
if ($conFormato) {
$img   = '../public/assets/images/receta2.jpg';
$c = [
'nomX' => 43, 'diagX' => 43, 'nacX' => 187, 'sexY' => 55.4, 'sexX' => 179.1,
'taY' => 64, 'taX' => 190, 'fcY' => 69.5, 'fcX' => 190, 'spoY' => 74.8, 'spoX' => 193, 'tempY' => 81, 'tempX' => 193
];
} else {
$img   = '../public/assets/images/receta3.jpg';
$c = [
'nomX' => 40, 'diagX' => 40, 'nacX' => null, 'sexY' => 53.5, 'sexX' => 177.5,
'taY' => 62.8, 'taX' => 189, 'fcY' => 68, 'fcX' => 191, 'spoY' => 73.8, 'spoX' => 191, 'tempY' => 79.1, 'tempX' => 191
];
}

// 2. Función helper para reducir la sintaxis de pintado
$put = function($pdf, $x, $y, $txt, $fontSize = 9, $w = 0, $h = 0) {
if ($txt === '' || $txt === null) return;
$pdf->SetFont('courier', '', $fontSize);
$pdf->SetY($y);
$pdf->SetX($x);
$pdf->Cell($w, $h, $txt, 0, 1, 'L');
};

// --- DIBUJO DE LA RECETA ---

$pdf->Image($img, 0, 0, 215.9, 139.7);

// Nombre del paciente
$put($pdf, $c['nomX'], 27, $paciente->getNombreCompleto(), 11);

// Fecha Nacimiento (Separada o completa según el formato)
$fechaRaw = $paciente->getFechaNacimiento();
if ($fechaRaw) {
$time = strtotime(str_replace('/', '-', $fechaRaw));
if ($conFormato) {
$put($pdf, $c['nacX'], 43, $fechaRaw, 9);
} else {
$put($pdf, 202, 43, date('d', $time), 9);
$put($pdf, 193, 43, date('m', $time), 9);
$put($pdf, 182.1, 43, date('Y', $time), 9);
}
}

// Edad y Sexo
$put($pdf, $conFormato ? 190 : 189, $conFormato ? 48.5 : 48, $edad . ' años', 9);
$put($pdf, $c['sexX'], ($paciente->getSexo() == 'M') ? $c['sexY'] : $c['sexY'] + 4.1, 'X', 9);

// Fecha de la consulta
$put($pdf, 9.2, 44, 'Fecha: ' . $model->getFecha(), 11);

// Ajuste dinámico de tamaño de fuente para el Diagnóstico
$diagnostico = trim($model->getDiagnostico());
$anchoMax = 195 - $c['diagX'];
$tamano = 12;

$pdf->SetFont('courier', '', $tamano);
while ($pdf->GetStringWidth($diagnostico) > $anchoMax && $tamano > 6) {
$tamano -= 0.5;
$pdf->SetFont('courier', '', $tamano);
}

$pdf->SetY(33.6);
$pdf->SetX($c['diagX']);
$pdf->Cell($anchoMax, 5, $diagnostico, 0, 0, 'L');

// Texto Formateado
$pdf->SetFont('courier', '', 9.8);
$pdf->SetY(51);
$pdf->SetX(9.2);
$pdf->MultiCell(160, 1, $textoFormateado);

// Signos Vitales
$put($pdf, $c['taX'], $c['taY'], $model->getTA() ? $model->getTA() . ' mmHg' : '', 8.5);
$put($pdf, $c['fcX'], $c['fcY'], $model->getFC() ? $model->getFC() . ' lpm' : '', 8.5);
$put($pdf, $c['spoX'], $c['spoY'], $model->getSPO2() ? $model->getSPO2() . ' %' : '', 8.5);
$put($pdf, $c['tempX'], $c['tempY'], $model->getTemperatura() ?? '', 8.5);

$pdf->Output('Receta ' . $paciente->getNombreCompleto() . '.pdf', 'I');

}

public function pdfRecetaExterno($id){

$model = new RecetaExternoModel();
$model->receta($id);

$edad = $model->getEdad();
$textoFormateado = $this->htmlToMultiCell($model->getMedicamento());

$pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'LETTER', true, 'UTF-8', false);

$pdf->SetCreator('Clinica');
$pdf->SetTitle('Receta');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetMargins(5, 0, 5);
$pdf->SetAutoPageBreak(false, 0);

$pdf->AddPage();

$conFormato = isset($_POST['con_formato']) ? (int)$_POST['con_formato'] : 1;

// 1. Configuración de coordenadas según el formato elegido
if ($conFormato) {
$img   = '../public/assets/images/receta2.jpg';
$c = [
'nomX' => 43, 'diagX' => 43, 'nacX' => 187, 'sexY' => 55.4, 'sexX' => 179.1,
'taY' => 64, 'taX' => 190, 'fcY' => 69.5, 'fcX' => 190, 'spoY' => 74.8, 'spoX' => 193, 'tempY' => 81, 'tempX' => 193
];
} else {
$img   = '../public/assets/images/receta3.jpg';
$c = [
'nomX' => 40, 'diagX' => 40, 'nacX' => null, 'sexY' => 53.5, 'sexX' => 177.5,
'taY' => 62.8, 'taX' => 189, 'fcY' => 68, 'fcX' => 191, 'spoY' => 73.8, 'spoX' => 191, 'tempY' => 79.1, 'tempX' => 191
];
}

// 2. Función helper para reducir la sintaxis de pintado
$put = function($pdf, $x, $y, $txt, $fontSize = 9, $w = 0, $h = 0) {
if ($txt === '' || $txt === null) return;
$pdf->SetFont('courier', '', $fontSize);
$pdf->SetY($y);
$pdf->SetX($x);
$pdf->Cell($w, $h, $txt, 0, 1, 'L');
};

// --- DIBUJO DE LA RECETA ---

$pdf->Image($img, 0, 0, 215.9, 139.7);

// Nombre del paciente
$put($pdf, $c['nomX'], 27, $model->getNombreCompleto(), 11);

// Fecha Nacimiento (Separada o completa según el formato)
$fechaRaw = $model->getFechaNacimiento();
if ($fechaRaw) {
$time = strtotime(str_replace('/', '-', $fechaRaw));
if ($conFormato) {
$put($pdf, $c['nacX'], 43, $fechaRaw, 9);
} else {
$put($pdf, 202, 43, date('d', $time), 9);
$put($pdf, 193, 43, date('m', $time), 9);
$put($pdf, 182.1, 43, date('Y', $time), 9);
}
}

// Edad y Sexo
$put($pdf, $conFormato ? 190 : 189, $conFormato ? 48.5 : 48, $edad . ' años', 9);
$put($pdf, $c['sexX'], ($model->getSexo() == 'M') ? $c['sexY'] : $c['sexY'] + 4.1, 'X', 9);

// Fecha de la consulta
$put($pdf, 9.2, 44, 'Fecha: ' . $model->getFecha(), 11);

// Ajuste dinámico de tamaño de fuente para el Diagnóstico
$diagnostico = trim($model->getDiagnostico());
$anchoMax = 195 - $c['diagX'];
$tamano = 12;

$pdf->SetFont('courier', '', $tamano);
while ($pdf->GetStringWidth($diagnostico) > $anchoMax && $tamano > 6) {
$tamano -= 0.5;
$pdf->SetFont('courier', '', $tamano);
}

$pdf->SetY(33.6);
$pdf->SetX($c['diagX']);
$pdf->Cell($anchoMax, 5, $diagnostico, 0, 0, 'L');

// Texto Formateado
$pdf->SetFont('courier', '', 9.8);
$pdf->SetY(51);
$pdf->SetX(9.2);
$pdf->MultiCell(160, 1, $textoFormateado);

// Signos Vitales
$put($pdf, $c['taX'], $c['taY'], $model->getTA() ? $model->getTA() . ' mmHg' : '', 8.5);
$put($pdf, $c['fcX'], $c['fcY'], $model->getFC() ? $model->getFC() . ' lpm' : '', 8.5);
$put($pdf, $c['spoX'], $c['spoY'], $model->getSPO2() ? $model->getSPO2() . ' %' : '', 8.5);
$put($pdf, $c['tempX'], $c['tempY'], $model->getTemperatura() ?? '', 8.5);

$pdf->Output('Receta ' . $model->getNombreCompleto() . '.pdf', 'I');

}

function htmlToMultiCell($html) {
// Cargamos el HTML en DOMDocument para procesarlo
$dom = new \DOMDocument('1.0', 'UTF-8');
@$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

$result = "";

// Recorremos cada nodo y procesamos las etiquetas importantes
foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $node) {
if ($node->nodeType === XML_TEXT_NODE) {
// Si es texto directo, lo añadimos tal cual
$result .= trim($node->textContent) . " ";
} elseif ($node->nodeName === 'p') {
// Si es un párrafo <p>, agregamos el contenido seguido de una línea nueva
$result .= trim($node->textContent) . "\n";
} elseif ($node->nodeName === 'br') {
// Si es un salto de línea <br>, añadimos una línea nueva
$result .= "\n";
} elseif ($node->nodeName === 'ul' || $node->nodeName === 'ol') {
// Si es una lista (ul o ol), procesamos los elementos <li>
foreach ($node->childNodes as $li) {
if ($li->nodeName === 'li') {
$result .= "• " . trim($li->textContent) . "\n";
}
}
$result .= "\n";
}
}

return $result;
}

public function pdfCofepris($id){

$model = new CofeprisModel();
$model->cofepris($id);

list($dia,$mes,$anio ) = explode('/', $model->getFecha());

$paciente = new PacienteModel($model->getIdPaciente());  

$pdf = new Fpdi();
$pdf->SetTitle('Cofepris');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0);
$pdf->AddPage();

$pdf->setSourceFile($_SERVER['DOCUMENT_ROOT'] . '/public/storage/cofepris/' . $model->getCarpeta() . '/' . $model->getFolio() . '.pdf');
$templateId = $pdf->importPage(1);

$pdf->useTemplate($templateId, 0, 0, 210, 297);

//------------------------------------------------------------------------------
//------------------------------------------------------------------------------

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY(164, 17.5);
$pdf->Write(0, $dia);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY(178, 17.5);
$pdf->Write(0, $mes);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY(192, 17.5);
$pdf->Write(0, $anio);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(44, 25.5);
$pdf->Write(0, $paciente->getNombreCompleto());

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(44, 30);
$pdf->Write(0, $paciente->getCurp());

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(47, 35);
$pdf->Write(0, $paciente->getCalle() . ' ' . $paciente->getNumExterior() . ', ' . $paciente->getColonia() . ', ' . $paciente->getDelegacion() . ', C.P. ' . $paciente->getCp() . ', ' . $paciente->getMunicipio());
$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(29, 39);
$pdf->Write(0, $model->getDiagnostico());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(8, 47);
$pdf->Write(0, $model->getMedicamento());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(44, 51);
$pdf->Write(0, $model->getNumcajas());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(117, 51);
$pdf->Write(0, $model->getPresentacion());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(29, 55);
$pdf->Write(0, $model->getDosificacion());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(55, 59.5);
$pdf->Write(0, $model->getNumdias());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(134, 59.5);
$pdf->Write(0, $model->getViaadministracion());

//------------------------------------------------------------------------------
//------------------------------------------------------------------------------



//------------------------------------------------------------------------------
//------------------------------------------------------------------------------

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY(165, 111.2);
$pdf->Write(0, $dia);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY(179, 111.2);
$pdf->Write(0, $mes);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY(193, 111.2);
$pdf->Write(0, $anio);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(45, 119);
$pdf->Write(0, $paciente->getNombreCompleto());

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(45, 124);
$pdf->Write(0, $paciente->getCurp());

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(48, 129);
$pdf->Write(0, $paciente->getCalle() . ' ' . $paciente->getNumExterior() . ', ' . $paciente->getColonia() . ', ' . $paciente->getDelegacion() . ', C.P. ' . $paciente->getCp() . ', ' . $paciente->getMunicipio());
$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(30, 132.5);
$pdf->Write(0, $model->getDiagnostico());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(9, 141);
$pdf->Write(0, $model->getMedicamento());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(47, 145);
$pdf->Write(0, $model->getNumcajas());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(118, 145);
$pdf->Write(0, $model->getPresentacion());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(31, 149);
$pdf->Write(0, $model->getDosificacion());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(57, 153.5);
$pdf->Write(0, $model->getNumdias());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(135, 153.5);
$pdf->Write(0, $model->getViaadministracion());

//------------------------------------------------------------------------------
//------------------------------------------------------------------------------


//------------------------------------------------------------------------------
//------------------------------------------------------------------------------

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY(165, 206.8);
$pdf->Write(0, $dia);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY(179, 206.8);
$pdf->Write(0, $mes);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY(193, 206.8);
$pdf->Write(0, $anio);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(45, 215);
$pdf->Write(0, $paciente->getNombreCompleto());

$pdf->SetFont('helvetica', '', 9); 
$pdf->SetXY(44, 219.5);
$pdf->Write(0, $paciente->getCurp());

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(48, 224.5);
$pdf->Write(0, $paciente->getCalle() . ' ' . $paciente->getNumExterior() . ', ' . $paciente->getColonia() . ', ' . $paciente->getDelegacion() . ', C.P. ' . $paciente->getCp() . ', ' . $paciente->getMunicipio());
$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(29, 228.5);
$pdf->Write(0, $model->getDiagnostico());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(9, 237);
$pdf->Write(0, $model->getMedicamento());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(46, 241);
$pdf->Write(0, $model->getNumcajas());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(118, 241);
$pdf->Write(0, $model->getPresentacion());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(30, 244.5);
$pdf->Write(0, $model->getDosificacion());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(57, 249);
$pdf->Write(0, $model->getNumdias());

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(135, 249);
$pdf->Write(0, $model->getViaadministracion());

//------------------------------------------------------------------------------
//------------------------------------------------------------------------------

$pdf->Output('Cofepris '.$paciente->getNombreCompleto().'.pdf', 'I');
}

}