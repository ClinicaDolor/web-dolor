<?php
namespace App\Controllers;

use App\Models\BusquedasModel;
use App\Models\RecetaModel;
use App\Models\NotaSubsecuenteModel;
use App\Models\LaboratorioModel;
//--------- MODULOS ----------
use App\Models\AntecedenteFamiliarModel;
use App\Models\AntecedentesNoPatologicosModel;
use App\Models\AntecedentesQuirurgicos;
use App\Models\AntecedentesPatologicosModel;
use App\Models\MedicacionDolorModel;
use App\Models\MedicacionActualModel;
use App\Models\ProcedimientosDolorModel;
use App\Models\EvaluacionDolorModel;

use App\Models\PacienteModulosModelo;
use App\Models\CofeprisModel;

class BusquedasController{

    public function buscarPacientes(){

        $query = $_GET['query'] ?? '';
        $model = new BusquedasModel();
        $suggestions = $model->getPacientes($query);
        header('Content-Type: application/json');
        echo json_encode($suggestions);

    }

    public function buscarReceta($idReceta){
        
        $model = new RecetaModel();
        echo $model->getReceta($idReceta);

    }

    public function buscarNotaSubsecuente($idNota){

        $model = new NotaSubsecuenteModel();
        echo $model->getNotaSubsecuente($idNota);

    }

    public function buscarLaboratorio($idLaboratorio){

        $model = new LaboratorioModel();
        echo $model->getLaboratorio($idLaboratorio);

    }

    public function tableRecetas($idPaciente){

        $model = new RecetaModel();
        echo $model->mostrarTablaRecetas($idPaciente);

    }

    public function tableLaboratorio($idPaciente,$referencia){

        $model = new LaboratorioModel();
        echo $model->mostrarTablaLaboratorio($idPaciente,$referencia);

    }

    public function tableNotasSubsecuentes($idPaciente,$referencia){
        $model = new NotaSubsecuenteModel();
        echo $model->mostrarTablaNotas($idPaciente,$referencia);
    }

    //---------- CONTENIDO DE LOS MODULOS ----------
    public function contenidoPreguntasM2($idPaciente,$idRol){
        $model = new AntecedenteFamiliarModel();
        echo $model->mostrarPreguntasM2($idPaciente,$idRol);
    }

    public function contenidoPreguntasM3($idPaciente,$idRol,$idCuestionario){
        $model = new AntecedentesNoPatologicosModel();
        echo $model->mostrarPreguntasM3($idPaciente,$idRol,$idCuestionario);
    }
 
    public function contenidoPreguntasM4($idPaciente,$idRol){
        $model = new AntecedentesQuirurgicos();
        echo $model->mostrarPreguntasM4($idPaciente,$idRol);
    }

    public function contenidoPreguntasM5V1($idPaciente,$idRol,$idCuestionario){
        $model = new AntecedentesPatologicosModel();
        echo $model->mostrarPreguntasM5V1($idPaciente,$idRol,$idCuestionario);
    }

    public function contenidoPreguntasM5V2($idPaciente,$idRol){
    $model = new AntecedentesPatologicosModel();
    echo $model->mostrarPreguntasM5V2($idPaciente,$idRol);
    }

    public function contenidoPreguntasM6($idPaciente,$idRol){
    $model = new MedicacionActualModel();
    echo $model->mostrarPreguntasM6($idPaciente,$idRol);
    }
    

    public function contenidoPreguntasM7($idPaciente,$idRol,$idCuestionario){
    $model = new MedicacionDolorModel();
    echo $model->mostrarPreguntasM7($idPaciente,$idRol,$idCuestionario);
    }

    public function contenidoPreguntasM8($idPaciente,$idRol){
    $model = new ProcedimientosDolorModel();
    echo $model->mostrarPreguntasM8($idPaciente,$idRol);
    }

    public function contenidoPreguntasM8V2($idPaciente,$idRol){
    $model = new ProcedimientosDolorModel();
    echo $model->mostrarPreguntasM8V2($idPaciente,$idRol);
    }

    public function contenidoPreguntasM9($idPaciente,$idRol){
    $model = new EvaluacionDolorModel();
    echo $model->mostrarPreguntasFrenteM9($idPaciente,$idRol);
    }

    public function contenidoPreguntasM9V2($idPaciente,$idRol){
    $model = new EvaluacionDolorModel();
    echo $model->mostrarPreguntasEspaldaM9($idPaciente,$idRol);
    }
 
    public function contenidoPreguntasM9V3($idPaciente,$idRol){
    $model = new EvaluacionDolorModel();
    echo $model->mostrarPreguntasM9($idPaciente,$idRol);
    }   

    //---------- CONTENIDO COMENTARIOS ----------
    public function contenidoComentariosModulo($idPaciente,$idRol,$idModulo){
    $model = new PacienteModulosModelo();
    echo $model->mostrarComentariosModulo($idPaciente,$idRol,$idModulo);
    }

    public function tableCofepris($idPaciente){
        $model = new CofeprisModel();
        echo $model->mostrarTablaCofepris($idPaciente);
    }

    public function buscarCofepris($idCofepris){

        $model = new CofeprisModel();
        echo $model->getCofepris($idCofepris);

    }

    public function buscarCofeprisFolios(){
        $model = new CofeprisModel();
        echo $model->getCofeprisFolios();
    }


}