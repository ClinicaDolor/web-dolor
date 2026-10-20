<?php

declare(strict_types=1);

/**
 * Contrato estático de alcance paciente/referencia para Nota subsecuente.
 * No inicia la aplicación, no abre PDO y no envía HTTP.
 */

$root = dirname(__DIR__, 2);
$read = static function (string $relative) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('No se pudo leer ' . $relative);
    }
    return $contents;
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$notes = $read('app/Models/NotaSubsecuenteModel.php');
$labs = $read('app/Models/LaboratorioModel.php');
$searches = $read('app/Controllers/BusquedasController.php');
$controller = $read('app/Controllers/ClinicaController.php');

$assert(str_contains($notes, 'WHERE id_paciente = :id_paciente'), 'Nota debe acotar por paciente.');
$assert(str_contains($notes, 'AND codigo_referencia = :referencia'), 'Nota debe acotar por referencia.');
$assert(str_contains($labs, 'WHERE id_paciente = :id_paciente'), 'Laboratorio debe acotar por paciente.');
$assert(str_contains($labs, 'AND codigo_referencia = :referencia'), 'Laboratorio debe acotar por referencia.');
$assert(str_contains($notes, "if ((string)\$referencia !== '0')"), 'El modo historico completo de referencia 0 debe ser explicito.');
$assert(str_contains($labs, "if ((string)\$referencia !== '0')"), 'El modo historico de Laboratorio debe ser explicito.');
$assert(!str_contains($notes, 'FROM nota_subsecuente WHERE $qry_referencia'), 'Nota no debe interpolar referencia en SQL.');
$assert(!str_contains($notes, 'FROM laboratorio WHERE $qry_referencia'), 'Laboratorio embebido no debe interpolar referencia en SQL.');
$assert(!str_contains($labs, 'FROM laboratorio WHERE $qry_referencia'), 'Laboratorio no debe interpolar referencia en SQL.');

$notesEndpoint = strpos($searches, 'public function tableNotasSubsecuentes(');
$labsEndpoint = strpos($searches, 'public function tableLaboratorio(');
$assert($notesEndpoint !== false && strpos(substr($searches, $notesEndpoint, 500), '$this->guardPatient($idPaciente)') !== false, 'Historial de Nota debe autorizar paciente antes del modelo.');
$assert($labsEndpoint !== false && strpos(substr($searches, $labsEndpoint, 500), '$this->guardPatient($idPaciente)') !== false, 'Historial de Laboratorio debe autorizar paciente antes del modelo.');

$mainEndpoint = strpos($controller, 'public function pacienteNotaSubsecuente(');
$assert($mainEndpoint !== false, 'No se encontro la vista principal de Nota subsecuente.');
$contextEndpoint = strpos($controller, 'private function notaSubsecuentePacienteContext(');
$assert($contextEndpoint !== false, 'No se encontro el contexto compartido de Nota subsecuente.');
$contextBlock = substr($controller, $contextEndpoint, 1800);
$assert(str_contains($contextBlock, 'assertPatientAccess'), 'El contexto de Nota debe autorizar paciente antes de renderizar.');

echo "TH_DOCTOR_SUBSEQUENT_NOTE_REFERENCE_SCOPE_CONTRACT_04C: PASS_STATIC\n";
