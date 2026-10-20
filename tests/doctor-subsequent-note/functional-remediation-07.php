<?php

declare(strict_types=1);

/**
 * Contrato estático de la remediación funcional 07.
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
$model = $read('app/Models/NotaSubsecuenteModel.php');
$view = $read('app/views/clinica/pacientes-nota-subsecuente-nueva.php')
    . "\n"
    . $read('app/views/clinica/partials/nota-subsecuente-editor.php');

$postStart = strpos($controller, 'public function pacienteInsertNotaSubsecuente()');
$postEnd = strpos($controller, 'public function pacienteLaboratorio(', $postStart ?: 0);
$post = substr($controller, $postStart ?: 0, ($postEnd ?: strlen($controller)) - ($postStart ?: 0));

$insertStart = strpos($model, 'public function insertNotaSubsecuente(');
$editStart = strpos($model, 'public function editarNotaSubsecuente(', $insertStart ?: 0);
$insert = substr($model, $insertStart ?: 0, ($editStart ?: strlen($model)) - ($insertStart ?: 0));

$assert($postStart !== false, 'No se encontró el endpoint de Nota subsecuente.');
$assert(str_contains($post, 'incluirReceta'), 'El endpoint debe recibir la selección explícita de receta.');
$assert(str_contains($post, 'validCaptureCsrf'), 'La remediación debe conservar CSRF.');
$assert(str_contains($post, 'assertPatientAccess'), 'La remediación debe conservar autorización de paciente.');
$assert(str_contains($post, 'belongsToPatientReference'), 'La remediación debe conservar autorización por referencia.');
$assert(str_contains($view, 'fc: incluirReceta ? frecCardiaca'), 'El contrato POST debe recibir FC explícita para receta.');
$assert(str_contains($view, 'temperatura: incluirReceta ? temperatura'), 'El contrato POST debe recibir temperatura explícita para receta.');

$assert(str_contains($insert, 'if ($incluirReceta)'), 'El alta debe condicionar la receta al opt-in.');
$assert(strpos($insert, 'if ($incluirReceta)') < strpos($insert, 'insertPacienteReceta'), 'La receta no debe crearse fuera del opt-in.');
$assert(str_contains($insert, 'beginTransaction'), 'El alta debe iniciar transacción.');
$assert(str_contains($insert, 'commit'), 'El alta debe confirmar la transacción.');
$assert(str_contains($insert, 'rollBack'), 'El alta debe revertir la transacción ante error.');
$assert(str_contains($insert, '$idReceta = 0;'), 'El alta de sólo Nota debe iniciar sin receta.');
$assert(str_contains($model, 'validateOptionalRecipe'), 'La receta opcional debe validar sus campos propios.');

$assert(str_contains($view, 'id="IncluirReceta"'), 'La vista debe ofrecer opt-in accesible para receta.');
$assert(str_contains($view, 'incluirReceta: incluirReceta'), 'El frontend debe enviar el opt-in de receta.');
$assert(str_contains($view, 'fc: incluirReceta ? frecCardiaca'), 'FC debe mapearse explícitamente sólo al solicitar receta.');
$assert(str_contains($view, 'temperatura: incluirReceta ? temperatura'), 'Temperatura debe enviarse sólo al solicitar receta.');
$assert(str_contains($view, 'response.ok'), 'El frontend debe diferenciar respuestas HTTP fallidas.');
$assert(str_contains($view, 'returnIdReceta > 0'), 'La impresión no debe ejecutarse para una Nota sin receta.');

echo "TH_DOCTOR_SUBSEQUENT_NOTE_FUNCTIONAL_REMEDIATION_07: PASS_STATIC\n";

