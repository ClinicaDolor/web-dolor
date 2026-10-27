<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$routes = file_get_contents($root . '/app/Config/routes.php');
$adapter = file_get_contents($root . '/app/Services/ConversationalM04Adapter.php');
$view = file_get_contents($root . '/app/views/paciente/respuestas-previas.php');
$filters = file_get_contents($root . '/public/assets/js/patient-previous-responses-filters.js');
$assert(is_string($controller) && is_string($routes) && is_string($adapter) && is_string($view) && is_string($filters), 'M04_CREATE_REQUIRED_SOURCE_MISSING');

$endpointStart = strpos($controller, 'public function createPreviousM04');
$endpointEnd = $endpointStart === false ? false : strpos($controller, 'private function formatM02CurrentAnswer', $endpointStart);
$endpoint = $endpointStart !== false && $endpointEnd !== false ? substr($controller, $endpointStart, $endpointEnd - $endpointStart) : '';
$assert($endpoint !== ''
    && str_contains($endpoint, "new AuthMiddleware('historia-clinica')")
    && str_contains($endpoint, 'assertPatientAccess($patientId, $auth)')
    && str_contains($endpoint, 'HTTP_X_M03_CSRF')
    && str_contains($endpoint, "\$auth['id_usuario']")
    && str_contains($endpoint, "'expected_version', 'cirugia', 'fecha', 'observaciones'")
    && str_contains($endpoint, "\$date = is_string(\$data['fecha'] ?? null) ? \$data['fecha'] : '';")
    && !str_contains($endpoint, 'idPaciente')
    && str_contains($endpoint, 'createPreviousSurgery('), 'M04_CREATE_ENDPOINT_AUTH_CSRF_ALLOWLIST_FAILED');
$assert(str_contains($routes, "'POST', '/respuestas-previas/m04/nueva', ['PatientInterviewController', 'createPreviousM04']"), 'M04_CREATE_ROUTE_MUST_BE_POST_REGISTERED');

$adapterStart = strpos($adapter, 'public function createPreviousSurgery(');
$adapterEnd = $adapterStart === false ? false : strpos($adapter, 'public function editExistingSurgery(', $adapterStart);
$createMethod = $adapterStart !== false && $adapterEnd !== false ? substr($adapter, $adapterStart, $adapterEnd - $adapterStart) : '';
$assert($createMethod !== ''
    && str_contains($createMethod, 'INSERT INTO pc_antecedentes_quirurgicos')
    && str_contains($createMethod, 'id_paciente = :patient')
    && str_contains($createMethod, 'GET_LOCK')
    && str_contains($createMethod, 'versionForRecords($currentRows)')
    && str_contains($createMethod, "\$dateValue = \$date === '' ? null : \$date;")
    && str_contains($createMethod, 'PDO::PARAM_NULL')
    && str_contains($createMethod, 'readback')
    && !str_contains($createMethod, 'pac_historia_clinica_finalizar'), 'M04_CREATE_MUST_BE_PATIENT_SCOPED_LOCKED_VERSIONED_READBACK_AND_FINALIZATION_NEUTRAL');

$assert(str_contains($view, 'data-m04-open-create')
    && str_contains($view, 'data-m04-create-dialog')
    && str_contains($view, 'data-m04-create-form')
    && str_contains($view, 'data-m04-create-name')
    && str_contains($view, 'data-m04-create-observations')
    && str_contains($view, 'data-m04-create-suggestions')
    && str_contains($view, 'data-m04-create-cancel')
    && str_contains($view, 'data-m04-create-save')
    && str_contains($view, 'if (createInFlight) return;')
    && str_contains($view, "createDialog.addEventListener('cancel'")
    && str_contains($view, 'createSave.disabled = true;')
    && str_contains($view, 'setCreateFieldError(')
    && str_contains($view, 'fecha: null,')
    && str_contains($view, 'observaciones: observations,')
    && str_contains($view, 'result.readback !== true')
    && str_contains($view, 'bindM04ReviewCard(card)'), 'M04_CREATE_MODAL_AND_READBACK_GATED_LIST_UPDATE_REQUIRED');
$assert(str_contains($filters, 'refreshCards(doc)') && str_contains($view, 'filters.refreshCards(document)'), 'M04_CREATED_CARD_MUST_REFRESH_EXISTING_SEARCH_FILTERS');

foreach ([
    'SERVIDOR' => 'https://clinica.test/', 'RUTA_CSS' => '/assets/css/', 'RUTA_IMAGES' => '/assets/images/',
    'RUTA_PUBLIC' => '/assets/', 'RUTA_JS' => '/assets/js/',
] as $constant => $value) {
    if (!defined($constant)) define($constant, $value);
}
$data = [
    'title' => 'Respuestas previas', 'csrf' => 'fixture-csrf', 'sidebar' => '<nav></nav>',
    'entries' => [], 'm02StatusItems' => [], 'm03Themes' => [], 'm03Finalized' => false,
    'm04Finalized' => true, 'm04Version' => str_repeat('a', 64),
    'm04CreateAuthorized' => true, 'm04CreateUrl' => 'https://clinica.test/historia-clinica/respuestas-previas/m04/nueva',
    'm04EditUrl' => 'https://clinica.test/historia-clinica/respuestas-previas/m04', 'm04Entries' => [],
];
ob_start();
require $root . '/app/views/paciente/respuestas-previas.php';
$rendered = (string)ob_get_clean();
$dialogStart = strpos($rendered, '<dialog class="m04-create-dialog"');
$dialogEnd = $dialogStart === false ? false : strpos($rendered, '</dialog>', $dialogStart);
$renderedDialog = $dialogStart !== false && $dialogEnd !== false ? substr($rendered, $dialogStart, $dialogEnd - $dialogStart) : '';
$assert($renderedDialog !== '' && !str_contains($renderedDialog, 'data-m04-create-date')
    && !str_contains($renderedDialog, 'Fecha de la cirugía')
    && !str_contains($renderedDialog, 'type="date"'), 'M04_CREATE_DIALOG_MUST_NOT_REQUEST_A_SURGERY_DATE');
foreach ([
    '+ Agregar cirugía' => 'M04_CREATE_TRIGGER_MISSING_FROM_RENDER',
    'Registrar nueva cirugía' => 'M04_CREATE_DIALOG_TITLE_MISSING_FROM_RENDER',
    'no inventes una fecha exacta' => 'M04_CREATE_TEMPORAL_GUIDANCE_MISSING_FROM_RENDER',
    'role="listbox"' => 'M04_CREATE_SURGERY_SUGGESTIONS_LISTBOX_MISSING_FROM_RENDER',
    'data-m04-create-card-template' => 'M04_CREATE_DYNAMIC_CARD_TEMPLATE_MISSING_FROM_RENDER',
    'data-m04-empty-state' => 'M04_CREATE_EMPTY_STATE_MISSING_FROM_RENDER',
    'data-m04-record-list' => 'M04_CREATE_RECORD_LIST_MISSING_FROM_RENDER',
] as $needle => $error) {
    $assert(str_contains($rendered, $needle), $error);
}

echo 'PASS M04 previous-responses create contract (static security/route/filter checks + rendered view; no database writes).' . PHP_EOL;
