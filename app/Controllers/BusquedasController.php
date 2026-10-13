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
use App\Models\RecetaExternoModel;
use App\Middleware\AuthMiddleware;

class BusquedasController{

private function guardPatient($idPaciente){
    $authMiddleware = new AuthMiddleware('');
    $auth = $authMiddleware->authAny();
    $authMiddleware->assertPatientAccess($idPaciente, $auth);
    return $auth;
}

private function guardClinic(){
    $authMiddleware = new AuthMiddleware('clinica');
    return $authMiddleware->authPermisos();
}

public function buscarPacientes(){

 $auth = $this->guardClinic();
$query = $_GET['query'] ?? '';
$model = new BusquedasModel();
 $suggestions = $model->getPacientes($query, $auth['id_clinica']);
header('Content-Type: application/json');
echo json_encode($suggestions);

}

public function buscarReceta($idReceta){

$model = new RecetaModel();
 $model->receta($idReceta);
 $this->guardPatient($model->getIdPaciente());
echo $model->getReceta($idReceta);

}

public function buscarNotaSubsecuente($idNota){

$model = new NotaSubsecuenteModel();
 $model->NotaSubsecuente($idNota);
 $this->guardPatient($model->getIdPaciente());
echo $model->getNotaSubsecuente($idNota);

}

public function buscarLaboratorio($idLaboratorio){

$model = new LaboratorioModel();
 $model->laboratorio($idLaboratorio);
 $this->guardPatient($model->getIdPaciente());
echo $model->getLaboratorio($idLaboratorio);

}

public function tableRecetas($idPaciente){

 $this->guardPatient($idPaciente);
$model = new RecetaModel();
echo $model->mostrarTablaRecetas($idPaciente);

}

public function tableRecetasExternos(){

 $this->guardClinic();
$model = new RecetaExternoModel();
echo $model->mostrarTablaRecetas();

}

public function buscarRecetaExterno($id){

 $this->guardClinic();
$model = new RecetaExternoModel();
echo $model->getReceta($id);

}

public function tableLaboratorio($idPaciente,$referencia){

 $this->guardPatient($idPaciente);
$model = new LaboratorioModel();
echo $model->mostrarTablaLaboratorio($idPaciente,$referencia);

}

public function tableNotasSubsecuentes($idPaciente,$referencia){
 $this->guardPatient($idPaciente);
$model = new NotaSubsecuenteModel();
echo $model->mostrarTablaNotas($idPaciente,$referencia);
}

//---------- CONTENIDO DE LOS MODULOS ----------
public function contenidoPreguntasM2($idPaciente,$idRol){
 $this->guardPatient($idPaciente);
$model = new AntecedenteFamiliarModel();
echo $model->mostrarPreguntasM2($idPaciente,$idRol);
}

public function contenidoPreguntasM3($idPaciente,$idRol,$idCuestionario){
 $auth = $this->guardPatient($idPaciente);
 $idCuestionario = filter_var($idCuestionario, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
 if ($idCuestionario === false) {
 http_response_code(404);
 return;
 }
 $rolReal = ($auth['vista'] === 'historia-clinica') ? 'Paciente' : 'Doctor';
$model = new AntecedentesNoPatologicosModel();
echo $model->mostrarPreguntasM3($idPaciente,$rolReal,$idCuestionario);
}

public function contenidoPreguntasM4($idPaciente,$idRol){
 $auth = $this->guardPatient($idPaciente);
 $rolReal = ($auth['vista'] === 'historia-clinica') ? 'Paciente' : 'Doctor';
$model = new AntecedentesQuirurgicos();
echo $model->mostrarPreguntasM4($idPaciente,$rolReal);
}

public function contenidoPreguntasM5V1($idPaciente,$idRol,$idCuestionario){
  $auth = $this->guardPatient($idPaciente);
  $idCuestionario = filter_var($idCuestionario, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
  if ($idCuestionario === false) {
  http_response_code(404);
  return;
  }
  $rolReal = ($auth['vista'] === 'historia-clinica') ? 'Paciente' : 'Doctor';
$model = new AntecedentesPatologicosModel();
 echo $model->mostrarPreguntasM5V1($idPaciente,$rolReal,$idCuestionario);
}

public function contenidoPreguntasM5V2($idPaciente,$idRol){
  $auth = $this->guardPatient($idPaciente);
  $rolReal = ($auth['vista'] === 'historia-clinica') ? 'Paciente' : 'Doctor';
$model = new AntecedentesPatologicosModel();
 echo $model->mostrarPreguntasM5V2($idPaciente,$rolReal);
}

public function contenidoPreguntasM6($idPaciente,$idRol){
 $auth = $this->guardPatient($idPaciente);
$model = new MedicacionActualModel();
try {
    if ($auth['vista'] === 'historia-clinica') {
        echo $model->mostrarPreguntasM6Paciente((int)$idPaciente);
        return;
    }
    echo $model->mostrarPreguntasM6((int)$idPaciente, 'Doctor');
} catch (\Throwable $exception) {
    http_response_code(500);
    echo 'No se pudo cargar la medicación. Inténtalo nuevamente.';
}
}


public function contenidoPreguntasM7($idPaciente,$idRol,$idCuestionario){
 try {
 $auth = $this->guardPatient($idPaciente);
 $idCuestionario = filter_var($idCuestionario, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
 if ($idCuestionario === false) {
 http_response_code(404);
 echo 'No se encontró el cuestionario.';
 return;
 }
 $rolReal = (($auth['vista'] ?? '') === 'historia-clinica') ? 'Paciente' : 'Doctor';
 $model = new MedicacionDolorModel();
 if ($rolReal === 'Paciente') {
 echo $model->mostrarPreguntasM7Paciente((int)$idPaciente, 0, $idCuestionario);
 return;
 }
 echo $model->mostrarPreguntasM7((int)$idPaciente, $rolReal, $idCuestionario);
 } catch (\Throwable $exception) {
 http_response_code(500);
 echo 'No se pudo cargar el cuestionario. Inténtalo nuevamente.';
 }
}

public function contenidoPreguntasM8($idPaciente,$idRol){
 $auth = $this->guardPatient($idPaciente);
 $model = new ProcedimientosDolorModel();
 try {
     if (($auth['vista'] ?? '') === 'historia-clinica') {
         header('Content-Type: application/json');
         echo json_encode(['resultado' => true, 'procedimientos' => $model->p10Procedimientos((int) $idPaciente)]);
         return;
     }
     echo $model->mostrarPreguntasM8((int) $idPaciente, 'Doctor');
 } catch (\Throwable $exception) {
     error_log('[P10] no se pudo cargar procedimientos: ' . get_class($exception));
     http_response_code(500);
     if (($auth['vista'] ?? '') === 'historia-clinica') {
         header('Content-Type: application/json');
         echo json_encode(['resultado' => false, 'mensaje' => 'No se pudieron cargar los procedimientos.']);
     } else {
         echo 'No se pudieron cargar los procedimientos. Inténtalo nuevamente.';
     }
 }
}

public function contenidoPreguntasM8V2($idPaciente,$idRol){
 $auth = $this->guardPatient($idPaciente);
 $model = new ProcedimientosDolorModel();
 try {
     if (($auth['vista'] ?? '') === 'historia-clinica') {
         header('Content-Type: application/json');
         echo json_encode(['resultado' => true] + $model->p10Tratamientos((int) $idPaciente));
         return;
     }
     echo $model->mostrarPreguntasM8V2((int) $idPaciente, 'Doctor');
 } catch (\Throwable $exception) {
     error_log('[P10] no se pudo cargar tratamientos: ' . get_class($exception));
     http_response_code(500);
     if (($auth['vista'] ?? '') === 'historia-clinica') {
         header('Content-Type: application/json');
         echo json_encode(['resultado' => false, 'mensaje' => 'No se pudieron cargar los tratamientos.']);
     } else {
         echo 'No se pudieron cargar los tratamientos. Inténtalo nuevamente.';
     }
 }
}

public function contenidoPreguntasM9($idPaciente,$idRol){
 $auth = $this->guardPatient($idPaciente);
$model = new EvaluacionDolorModel();
$role = (($auth['vista'] ?? '') === 'historia-clinica') ? 'Paciente' : 'Doctor';
echo $role === 'Paciente' ? $model->p11MostrarMapa((int) $idPaciente, $role, 'frente') : $model->mostrarPreguntasFrenteM9((int) $idPaciente, $role);
}

public function contenidoPreguntasM9V2($idPaciente,$idRol){
 $auth = $this->guardPatient($idPaciente);
$model = new EvaluacionDolorModel();
$role = (($auth['vista'] ?? '') === 'historia-clinica') ? 'Paciente' : 'Doctor';
echo $role === 'Paciente' ? $model->p11MostrarMapa((int) $idPaciente, $role, 'espalda') : $model->mostrarPreguntasEspaldaM9((int) $idPaciente, $role);
}

public function contenidoPreguntasM9V3($idPaciente,$idRol){
 $auth = $this->guardPatient($idPaciente);
$model = new EvaluacionDolorModel();
$role = (($auth['vista'] ?? '') === 'historia-clinica') ? 'Paciente' : 'Doctor';
echo $role === 'Paciente' ? $model->p11MostrarCuestionario((int) $idPaciente, $role) : $model->mostrarPreguntasM9((int) $idPaciente, $role);
}   

//---------- CONTENIDO COMENTARIOS ----------
public function contenidoComentariosModulo($idPaciente,$idRol,$idModulo){
 $this->guardPatient($idPaciente);
$model = new PacienteModulosModelo();
echo $model->mostrarComentariosModulo($idPaciente,$idRol,$idModulo);
}

public function tableCofepris($idPaciente){
 $this->guardPatient($idPaciente);
$model = new CofeprisModel();
echo $model->mostrarTablaCofepris($idPaciente);
}

public function buscarCofepris($idCofepris){

$model = new CofeprisModel();
 $model->cofepris($idCofepris);
 $this->guardPatient($model->getIdPaciente());
echo $model->getCofepris($idCofepris);

}

public function buscarCofeprisFolios(){
 $this->guardClinic();
$model = new CofeprisModel();
echo $model->getCofeprisFolios();
}


}
