<?php 
namespace App\Controllers;

use App\Models\CofeprisModel;
use App\Models\RecetaModel;
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

        $imagePath = '../public/assets/images/receta.jpeg';

        // Obtener el tamaño de la página (ancho y alto)
        $pageWidth = $pdf->getPageWidth();
        $pageHeight = $pdf->getPageHeight();

        // Colocar la imagen de fondo en las coordenadas (0, 0) y ajustarla al tamaño de la página
        //----------------------------------------------------------------------------------------
        //$pdf->Image($imagePath, 0, 0, 215.9, 139.7);
        

        $pdf->SetFont('courier', '', 11);
        $pdf->SetY(24);
        $pdf->SetX(100);
        $pdf->Cell(0, 0, $paciente->getNombreCompleto(), 0, 1, 'L');
        $pdf->Ln(0);

        
        $pdf->SetFont('courier', '', 9);
        $pdf->SetY(32);
        $pdf->SetX(97);
        $pdf->Cell(0, 0, $paciente->getFechaNacimiento(), 0, 1, 'L');
        $pdf->Ln(0);

        $pdf->SetFont('courier', '', 9);
        $pdf->SetY(32);
        $pdf->SetX(129);
        $pdf->Cell(0, 0, $edad.' años', 0, 1, 'L');
        $pdf->Ln(0);

        $pdf->SetFont('courier', '', 9);

        if($paciente->getSexo() == 'M'){
        $pdf->SetY(32);
        $pdf->SetX(165);
        }else{
        $pdf->SetY(32);
        $pdf->SetX(186);
        }

        $pdf->Cell(0, 0, 'X', 0, 1, 'L');
        $pdf->Ln(0);

        $pdf->SetFont('courier', '', 11);
        $pdf->SetY(39);
        $pdf->SetX(71);
        $pdf->Cell(0, 0, 'Fecha: '.$model->getFecha(), 0, 1, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('courier', '', 12);
        $pdf->MultiCell(0, 5, $model->getDiagnostico(), 0, 'L', 0);
        $pdf->Ln(5);

        $pdf->SetFont('courier', '', 12);
        $pdf->MultiCell(0, 1, $textoFormateado);

        $pdf->Output('Receta ' . $paciente->getNombreCompleto() . '.pdf', 'I');

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
        $pdf->Write(0, $paciente->getCalle().' '.$paciente->getNumExterior().', '.$paciente->getColonia().$paciente->getDelegacion(),', '.$paciente->getCp().', '.$paciente->getMunicipio());

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
        $pdf->Write(0, $paciente->getCalle().' '.$paciente->getNumExterior().', '.$paciente->getColonia().$paciente->getDelegacion(),', '.$paciente->getCp().', '.$paciente->getMunicipio());

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
        $pdf->Write(0, $paciente->getCalle().' '.$paciente->getNumExterior().', '.$paciente->getColonia().$paciente->getDelegacion(),', '.$paciente->getCp().', '.$paciente->getMunicipio());

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