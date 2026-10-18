<?php

declare(strict_types=1);

/**
 * Contrato estático de seguridad para Nota subsecuente.
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

$controller = $read('app/Controllers/ClinicaController.php');
$noteModel = $read('app/Models/NotaSubsecuenteModel.php');
$recipeModel = $read('app/Models/RecetaModel.php');
$labModel = $read('app/Models/LaboratorioModel.php');
$view = $read('app/views/clinica/pacientes-nota-subsecuente-nueva.php')
    . "\n"
    . $read('app/views/clinica/partials/nota-subsecuente-editor.php');

$notePostStart = strpos($controller, 'public function pacienteInsertNotaSubsecuente()');
$notePostEnd = strpos($controller, 'public function pacienteLaboratorio(', $notePostStart ?: 0);
$notePost = substr($controller, $notePostStart ?: 0, ($notePostEnd ?: strlen($controller)) - ($notePostStart ?: 0));

$assert($notePostStart !== false, 'No se encontró el endpoint de Nota subsecuente.');
$assert(str_contains($notePost, 'validCaptureCsrf'), 'El POST de Nota debe validar CSRF.');
$assert(str_contains($notePost, 'HTTP_X_M03_CSRF'), 'El POST de Nota debe aceptar el header CSRF compartido.');
$assert(strpos($notePost, 'validCaptureCsrf') < strpos($notePost, 'insertNotaSubsecuente'), 'CSRF debe validarse antes de escribir la Nota.');
$assert(str_contains($notePost, 'assertPatientAccess'), 'El POST de Nota debe comprobar acceso al paciente.');
$assert(str_contains($notePost, 'belongsToPatientReference'), 'La edición debe comprobar paciente y referencia.');

$assert(str_contains($view, 'data-nota-csrf='), 'La vista debe exponer el token de Nota en el contexto autenticado.');
$assert(str_contains($view, "'X-M03-CSRF': config.getAttribute('data-nota-csrf')"), 'El frontend debe enviar el token de Nota.');
$assert(str_contains($view, 'let notaGuardando = false;'), 'La vista debe tener guardia contra doble envío en frontend.');
$assert(str_contains($view, '.finally(() =>'), 'La guardia de doble envío debe restaurarse ante éxito o error.');

$assert(str_contains($noteModel, 'WHERE id = :id AND id_paciente = :id_paciente AND codigo_referencia = :codigo_referencia'), 'La actualización de Nota debe acotarse por registro, paciente y referencia.');
$assert(str_contains($noteModel, 'RecetaModel::sanitizeMedicationHtml'), 'El detalle de Nota debe sanitizar el contenido enriquecido.');
$assert(!str_contains($noteModel, 'FROM laboratorio WHERE $qry_referencia'), 'La tabla de Laboratorio de Nota no debe interpolar el filtro SQL.');
$assert(!str_contains($noteModel, 'FROM nota_subsecuente WHERE $qry_referencia'), 'La tabla de Notas no debe interpolar el filtro SQL.');

$assert(str_contains($recipeModel, 'codigo_referencia = :referencia'), 'La relación de Receta debe usar referencia parametrizada.');
$assert(str_contains($recipeModel, 'function belongsToPatientReference'), 'Receta debe ofrecer comprobación de contexto.');
$assert(str_contains($labModel, 'RecetaModel::sanitizeMedicationHtml'), 'Descripción de Laboratorio debe sanitizar HTML permitido.');
$assert(!str_contains($labModel, 'FROM laboratorio WHERE $qry_referencia'), 'La tabla de Laboratorio independiente no debe interpolar el filtro SQL.');

echo "TH_DOCTOR_SUBSEQUENT_NOTE_SECURITY_CONTRACT_04: PASS_STATIC\n";

