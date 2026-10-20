<?php

declare(strict_types=1);

/**
 * Contrato estático de retorno del detalle de Nota subsecuente.
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

$routes = $read('app/Config/routes.php');
$controller = $read('app/Controllers/NotaSubsecuenteController.php');
$list = $read('app/views/clinica/pacientes-nota-subsecuente.php');
$detail = $read('app/views/clinica/nota-subsecuente.php');

$assert(str_contains($routes, "'/nota-subsecuente/{idNota}'"), 'La ruta de detalle debe permanecer.');
$assert(str_contains($list, 'context_ref='), 'El detalle debe recibir la referencia de origen.');
$assert(str_contains($list, 'context_patient='), 'El detalle debe recibir el paciente de origen.');
$assert(str_contains($list, 'rawurlencode($historialReferenciaRaw)'), 'La referencia de origen debe codificarse en la URL.');
$assert(str_contains($list, 'rawurlencode((string)$data[\'idPaciente\'])'), 'El paciente de origen debe ser dinámico y codificado.');
$assert(str_contains($list, 'clinica/nota-subsecuente/\' . $registroId . \'?\''), 'El enlace debe conservar el ID dinámico de la nota.');

$assert(str_contains($controller, "isset(\$_GET['context_ref'])"), 'El controlador debe leer el contexto explícito.');
$assert(str_contains($controller, "\$_GET['context_patient'] ?? null"), 'El controlador debe leer el paciente del contexto.');
$assert(str_contains($controller, 'assertPatientAccess'), 'La autorización debe depender del paciente real de la nota.');
$assert(str_contains($controller, '$contextPatient === $idPaciente'), 'El contexto debe quedar ligado al paciente autorizado.');
$assert(str_contains($controller, "preg_match('/\\A[a-f0-9]{13}\\.[a-f0-9]{8}\\z/i'"), 'La referencia de origen debe validar el formato del contrato.');
$assert(str_contains($controller, 'getHistoryByPatient($idPaciente)'), 'El acceso directo debe resolver la referencia propia de la nota.');
$assert(str_contains($controller, "['codigo_referencia']"), 'El fallback debe usar la referencia almacenada de la nota.');
$assert(str_contains($controller, "'back_url' => \$backUrl"), 'El controlador debe entregar la URL de retorno construida.');
$assert(str_contains($controller, "'back_label' => \$backLabel"), 'La vista debe recibir una etiqueta coherente con el destino.');
$assert(str_contains($controller, "SERVIDOR . 'clinica/nota-subsecuente/paciente/'"), 'El destino se reconstruye como ruta interna.');
$assert(str_contains($controller, 'rawurlencode($backReference)'), 'La referencia de retorno debe codificarse en servidor.');

$assert(str_contains($detail, '$data[\'back_url\']'), 'La vista debe usar la URL validada por el controlador.');
$assert(str_contains($detail, '$data[\'back_label\']'), 'La vista debe usar la etiqueta validada por el controlador.');
$assert(str_contains($controller, 'Volver a notas subsecuentes'), 'El flujo debe identificar el regreso al historial.');
$assert(str_contains($detail, 'data-doctor-back-action'), 'El botón debe conservar el patrón visual institucional.');
$assert(!str_contains($detail, 'history.back'), 'No debe depender de history.back().');
$assert(!str_contains($detail, 'document.referrer'), 'No debe depender de document.referrer.');
$assert(!str_contains($controller, 'header(\'Location: http'), 'No debe permitir redirecciones externas.');

$assert(str_contains($list, '$volverExpedienteUrl'), 'El botón del listado hacia el expediente debe permanecer.');
$assert(str_contains($list, 'doctor-note-detail-action'), 'El botón único de detalle de 08D debe permanecer.');
$assert(!str_contains($list, 'doctor-note-actions-menu'), 'No debe reintroducirse el dropdown de 08C.');
$assert(!str_contains($list, '20'), 'El listado no debe contener un ID fijo de ejemplo.');
$assert(!str_contains($list, '310'), 'El listado no debe contener un paciente fijo de ejemplo.');
$assert(!str_contains($list, '6ad40ae94c5210.10675456'), 'El listado no debe contener una referencia fija de ejemplo.');
$assert(!str_contains($controller, '6ad40ae94c5210.10675456'), 'El controlador no debe contener la referencia del ejemplo.');

echo "TH_DOCTOR_SUBSEQUENT_NOTE_DETAIL_BACK_NAVIGATION_FIX_08E: PASS_STATIC\n";
