<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\ConversationalM04Adapter;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

// Isolated SQLite fixture only. No application database or patient rows are used.
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE pac_historia_clinica_finalizar (id INTEGER PRIMARY KEY AUTOINCREMENT, id_modulo INTEGER NOT NULL, id_paciente INTEGER NOT NULL)');
$db->exec('CREATE TABLE pc_antecedentes_quirurgicos (id INTEGER PRIMARY KEY AUTOINCREMENT, id_paciente INTEGER NOT NULL, fecha TEXT NULL, cirugia TEXT NULL, observaciones TEXT NULL)');
$db->exec("INSERT INTO pc_antecedentes_quirurgicos (id_paciente, fecha, cirugia, observaciones) VALUES
    (9301, '2025-02-10', 'Vesícula', 'Fecha aproximada original: febrero de 2025'),
    (9301, NULL, 'Rodilla', 'Año aproximado: 2020'),
    (9301, NULL, '', 'Fila heredada vacía'),
    (9302, '2024-01-05', 'Cadera', 'Paciente distinto')");

$adapter = new ConversationalM04Adapter($db);
$state = $adapter->read(9301);
$initialCount = (int)$db->query('SELECT COUNT(*) FROM pc_antecedentes_quirurgicos')->fetchColumn();
$edit = $adapter->editExistingSurgery(9301, 1, $state['previous_response_version'], 'Vesícula', '', 'Intervención registrada; fecha aproximada: febrero de 2025');
$assert(($edit['resultado'] ?? 0) === 200 && ($edit['readback'] ?? false) === true, 'an existing surgery must update and pass readback');
$assert((int)$edit['record_id'] === 1 && (int)$edit['record']['id'] === 1, 'readback must identify the same surgery row');
$assert((string)$edit['record']['fecha'] === '2025-02-10', 'an empty date control must preserve the exact stored date');
$assert((string)$edit['record']['observaciones'] === 'Intervención registrada; fecha aproximada: febrero de 2025', 'approximate date precision in observations must be preserved verbatim');
$assert((int)$db->query('SELECT COUNT(*) FROM pc_antecedentes_quirurgicos')->fetchColumn() === $initialCount, 'historical edit must never insert a duplicate surgery');

$stale = $adapter->editExistingSurgery(9301, 1, $state['previous_response_version'], 'Vesícula', '', 'Texto obsoleto');
$assert(($stale['resultado'] ?? 0) === 409 && ($stale['readback'] ?? false) === false, 'a stale module version must be rejected before update');
$afterStale = $db->query('SELECT fecha, cirugia, observaciones FROM pc_antecedentes_quirurgicos WHERE id=1 AND id_paciente=9301')->fetch(PDO::FETCH_ASSOC);
$assert(is_array($afterStale) && $afterStale['fecha'] === '2025-02-10'
    && $afterStale['observaciones'] === 'Intervención registrada; fecha aproximada: febrero de 2025',
    'a stale version must leave the persisted date and observations unchanged');

$nullDateState = $adapter->read(9301);
$emptyObservations = $adapter->editExistingSurgery(9301, 2, $nullDateState['previous_response_version'], 'Rodilla', '', '');
$assert(($emptyObservations['resultado'] ?? 0) === 200 && ($emptyObservations['readback'] ?? false) === true
    && ($emptyObservations['record']['fecha'] ?? null) === null
    && (string)($emptyObservations['record']['observaciones'] ?? 'non-empty') === '',
    'an omitted date must preserve NULL and an intentionally empty observations value must be saved');
$current = $adapter->read(9301);
$exactDateUpdate = $adapter->editExistingSurgery(9301, 1, $current['previous_response_version'], 'Vesícula', '2025-03-14', 'Fecha exacta confirmada');
$assert(($exactDateUpdate['resultado'] ?? 0) === 200 && (string)$exactDateUpdate['record']['fecha'] === '2025-03-14', 'a valid exact date may be updated');
$badDateState = $adapter->read(9301);
$badDate = $adapter->editExistingSurgery(9301, 1, $badDateState['previous_response_version'], 'Vesícula', '2025-02-30', 'No debe persistirse');
$assert(($badDate['resultado'] ?? 0) === 422, 'an impossible exact date must be rejected');

$foreignState = $adapter->read(9301);
$foreignRow = $adapter->editExistingSurgery(9301, 4, $foreignState['previous_response_version'], 'Cadera editada', '', 'No autorizado');
$assert(($foreignRow['resultado'] ?? 0) === 404, 'a record owned by another patient must not be addressable');
$blankRowState = $adapter->read(9301);
$blankRow = $adapter->editExistingSurgery(9301, 3, $blankRowState['previous_response_version'], 'Cirugía inventada', '', '');
$assert(($blankRow['resultado'] ?? 0) === 409, 'a blank legacy row must not become a surgery through historical editing');

$db->exec('INSERT INTO pac_historia_clinica_finalizar (id_modulo, id_paciente) VALUES (4, 9301)');
$finalizedBefore = $adapter->read(9301);
$finalizedEdit = $adapter->editExistingSurgery(9301, 2, $finalizedBefore['previous_response_version'], 'Rodilla', '', 'Año aproximado: 2020, conservado');
$finalizedAfter = $adapter->read(9301);
$assert(($finalizedEdit['resultado'] ?? 0) === 200 && ($finalizedAfter['finalized'] ?? false) === true, 'editing history must not reopen or unfinalize M04');
$assert(($finalizedEdit['record']['fecha'] ?? null) === null, 'editing a NULL-dated surgery must preserve NULL after readback');
$assert((int)$db->query('SELECT COUNT(*) FROM pac_historia_clinica_finalizar WHERE id_modulo=4 AND id_paciente=9301')->fetchColumn() === 1, 'historical edit must preserve the existing finalization record');
$assert((string)$finalizedEdit['record']['observaciones'] === 'Año aproximado: 2020, conservado', 'approximate timing must remain text and must not be converted into a date');
$assert((int)$db->query('SELECT COUNT(*) FROM pc_antecedentes_quirurgicos')->fetchColumn() === $initialCount, 'all historical edits must leave the surgery row count unchanged');

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$routes = file_get_contents($root . '/app/Config/routes.php');
$adapterSource = file_get_contents($root . '/app/Services/ConversationalM04Adapter.php');
$view = file_get_contents($root . '/app/views/paciente/respuestas-previas.php');
$filters = file_get_contents($root . '/public/assets/js/patient-previous-responses-filters.js');
$endpointStart = is_string($controller) ? strpos($controller, 'public function updatePreviousM04') : false;
$endpointEnd = $endpointStart === false ? false : strpos($controller, 'private function formatM02CurrentAnswer', $endpointStart);
$endpointSource = $endpointStart !== false && $endpointEnd !== false ? substr($controller, $endpointStart, $endpointEnd - $endpointStart) : '';
$assert($endpointSource !== '' && str_contains($endpointSource, 'M04:record:([1-9][0-9]*)')
    && str_contains($endpointSource, "HTTP_X_M03_CSRF")
    && str_contains($endpointSource, "\$auth['id_usuario']")
    && str_contains($endpointSource, 'assertPatientAccess')
    && str_contains($endpointSource, "'record_id', 'expected_version', 'cirugia', 'fecha', 'observaciones'")
    && str_contains($endpointSource, "\$date = is_string(\$data['fecha'] ?? null) ? \$data['fecha'] : '';")
    && !str_contains($endpointSource, 'patient_id'),
    'M04 endpoint must use the authenticated patient, CSRF, record identity and an allowlisted payload');
$editStart = is_string($adapterSource) ? strpos($adapterSource, 'public function editExistingSurgery(') : false;
$editEnd = $editStart === false ? false : strpos($adapterSource, 'public function previousResponseEditDatabaseAuthorized', $editStart);
$editSource = $editStart !== false && $editEnd !== false ? substr($adapterSource, $editStart, $editEnd - $editStart) : '';
$assert($editSource !== '' && str_contains($editSource, 'UPDATE pc_antecedentes_quirurgicos')
    && !str_contains($editSource, 'INSERT INTO')
    && str_contains($editSource, 'id_paciente = :patient')
    && str_contains($editSource, 'GET_LOCK')
    && str_contains($editSource, "\$effectiveDate = \$date === '' ? (\$current['fecha'] ?? null) : \$date;")
    && str_contains($editSource, "\$update->bindValue(':date', \$effectiveDate")
    && str_contains($editSource, 'readback'), 'M04 history editing must be scoped, versioned, locked and update-only');
$assert(is_string($routes) && str_contains($routes, "'POST', '/respuestas-previas/m04', ['PatientInterviewController', 'updatePreviousM04']"), 'M04 update route must be POST-registered');
$assert(is_string($view) && str_contains($view, 'data-review-module="M04"')
    && str_contains($view, 'data-m04-review-card')
    && str_contains($view, 'data-m04-cancel-edit')
    && str_contains($view, 'root.dataset.m04EditUrl')
    && str_contains($view, 'result.readback !== true')
    && str_contains($view, "const expectedDate = card.dataset.originalDate || '';")
    && str_contains($view, "|| (result.fecha || '') !== expectedDate")
    && str_contains($view, "cancel.addEventListener('click'")
    && str_contains($view, 'restoreValues();')
    && !str_contains($view, 'data-m04-date-input')
    && !str_contains($view, 'data-m04-label-date')
    && !str_contains($view, 'Fecha exacta')
    && !str_contains($view, 'Deja la fecha vacía para conservar la fecha registrada.'),
    'M04 edit UI must omit the date editor while preserving the read-only persisted date display');
$assert(is_string($filters) && str_contains($filters, '[data-m04-response-editor]'), 'the shared filter lock must respect an open M04 editor');

foreach ([
    'SERVIDOR' => 'https://clinica.test/', 'RUTA_CSS' => '/assets/css/', 'RUTA_IMAGES' => '/assets/images/',
    'RUTA_PUBLIC' => '/assets/', 'RUTA_JS' => '/assets/js/',
] as $constant => $value) {
    if (!defined($constant)) define($constant, $value);
}
$data = [
    'title' => 'Respuestas previas', 'csrf' => 'fixture-token', 'sidebar' => '<nav></nav>',
    'entries' => [], 'm02StatusItems' => [], 'm03Themes' => [], 'm03Finalized' => false,
    'm04Finalized' => true, 'm04EditUrl' => 'https://clinica.test/historia-clinica/respuestas-previas/m04',
    'm04Entries' => [[
        'record_id' => 91, 'stable_id' => 'M04:record:91', 'surgery' => 'Vesícula <script>',
        'date' => '2025-02-10', 'observations' => 'Febrero de 2025, aproximado',
        'version' => str_repeat('d', 64), 'editable' => true, 'edit_blocker' => '',
    ]],
];
ob_start();
require $root . '/app/views/paciente/respuestas-previas.php';
$rendered = (string)ob_get_clean();
$editorStart = strpos($rendered, '<form class="previous-response-editor" data-m04-response-editor');
$editorEnd = $editorStart === false ? false : strpos($rendered, '</form>', $editorStart);
$renderedEditor = $editorStart !== false && $editorEnd !== false ? substr($rendered, $editorStart, $editorEnd - $editorStart) : '';
$assert($renderedEditor !== '' && !str_contains($renderedEditor, 'type="date"')
    && !str_contains($renderedEditor, 'Fecha exacta')
    && str_contains($renderedEditor, 'data-m04-surgery-input')
    && str_contains($renderedEditor, 'data-m04-observations-input')
    && str_contains($renderedEditor, 'data-m04-save-surgery')
    && str_contains($renderedEditor, 'data-m04-cancel-edit'),
    'Rendered M04 editor must contain name, observations, save and cancel but no date control');
$assert(str_contains($rendered, 'data-review-module-id="M04"')
    && str_contains($rendered, 'data-stable-id="M04:record:91"')
    && str_contains($rendered, 'Vesícula &lt;script&gt;')
    && str_contains($rendered, '2025-02-10')
    && str_contains($rendered, 'Febrero de 2025, aproximado')
    && str_contains($rendered, 'data-m04-response-editor')
    && !str_contains($rendered, 'Vesícula <script>'), 'M04 record must render with escaped values, date precision, observations and its editor');

echo 'PASS M04 previous-responses edit contract (isolated SQLite fixture + static route/security/UI checks; not MySQL).' . PHP_EOL;
