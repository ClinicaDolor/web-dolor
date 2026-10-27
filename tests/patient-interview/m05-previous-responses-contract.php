<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$routes = file_get_contents($root . '/app/Config/routes.php');
$adapter = file_get_contents($root . '/app/Services/ConversationalM05Adapter.php');
$view = file_get_contents($root . '/app/views/paciente/respuestas-previas.php');

$assert(is_string($routes)
    && str_contains($routes, "'/respuestas-previas/m05/v1'")
    && str_contains($routes, "'/respuestas-previas/m05/v2'"),
    'M05 V1 and V2 history-edit routes must be registered as separate POST endpoints');
$assert(is_string($controller)
    && str_contains($controller, 'public function updatePreviousM05V1')
    && str_contains($controller, 'public function updatePreviousM05V2')
    && str_contains($controller, 'new ConversationalM05Adapter())->read($patientId)')
    && str_contains($controller, '\'m05V1Themes\' => $m05V1Themes')
    && str_contains($controller, '\'m05V2Diseases\' => $m05V2Diseases'),
    'M05 previous-response read and edit handlers must use the authenticated patient and adapter');

foreach ([
    ['public function updatePreviousM05V1', 'public function updatePreviousM05V2'],
    ['public function updatePreviousM05V2', 'public function createPreviousM04'],
] as [$start, $end]) {
    $startAt = strpos($controller, $start);
    $endAt = $startAt === false ? false : strpos($controller, $end, $startAt + strlen($start));
    $method = $startAt !== false && $endAt !== false ? substr($controller, $startAt, $endAt - $startAt) : '';
    $assert($method !== '' && str_contains($method, "['REQUEST_METHOD']")
        && str_contains($method, 'AuthMiddleware')
        && str_contains($method, 'assertPatientAccess')
        && str_contains($method, 'validCaptureCsrf'),
        'Each M05 history edit endpoint must enforce POST, authenticated patient scope and CSRF');
}

$v1Start = strpos($adapter, 'public function editPreviousResponseV1(');
$v1End = $v1Start === false ? false : strpos($adapter, 'public function editPreviousResponseV2(', $v1Start);
$v1 = $v1Start !== false && $v1End !== false ? substr($adapter, $v1Start, $v1End - $v1Start) : '';
$v2Start = strpos($adapter, 'public function editPreviousResponseV2(');
$v2End = $v2Start === false ? false : strpos($adapter, 'public function interpret(', $v2Start);
$v2 = $v2Start !== false && $v2End !== false ? substr($adapter, $v2Start, $v2End - $v2Start) : '';
$assert($v1 !== '' && str_contains($v1, 'UPDATE pac_respuestas_paciente_modulo_5')
    && str_contains($v1, 'id_paciente') && str_contains($v1, 'id_pregunta')
    && str_contains($v1, 'expectedVersion') && str_contains($v1, 'readback')
    && !str_contains($v1, 'INSERT INTO'),
    'M05 V1 editing must update only one existing patient/question row with version and readback');
$assert($v2 !== '' && str_contains($v2, 'UPDATE pac_enfermedades_respuestas_modulo_5')
    && str_contains($v2, 'id_paciente') && str_contains($v2, 'id_enfermedad')
    && str_contains($v2, 'year_diagnostico') && str_contains($v2, 'tipo')
    && str_contains($v2, 'expectedVersion') && str_contains($v2, 'readback')
    && !str_contains($v2, 'INSERT INTO')
    && !preg_match('/UPDATE\s+pac_enfermedades_respuestas_modulo_5\s+SET[^;]*\btipo\s*=/is', $v2),
    'M05 V2 editing must update one existing response/year while preserving tipo');

$assert(is_string($view)
    && str_contains($view, 'data-review-module="M05"')
    && str_contains($view, 'data-m05-v1-card')
    && str_contains($view, 'data-m05-v2-card')
    && str_contains($view, 'data-review-module-filter')
    && str_contains($view, 'data-m05-v1-edit-url')
    && str_contains($view, 'data-m05-v2-edit-url')
    && str_contains($view, 'data-m05-clear-year')
    && str_contains($view, 'data-m05-v1-save')
    && str_contains($view, 'data-m05-v2-save'),
    'M05 must render both review sections with edit controls and use the shared filter surface');

foreach ([
    'SERVIDOR' => 'https://clinica.test/', 'RUTA_CSS' => '/assets/css/',
    'RUTA_IMAGES' => '/assets/images/', 'RUTA_PUBLIC' => '/assets/', 'RUTA_JS' => '/assets/js/',
] as $constant => $value) {
    if (!defined($constant)) define($constant, $value);
}
$diseases = [];
for ($id = 1; $id <= 19; $id++) {
    $diseases[] = [
        'disease_id' => $id, 'stable_id' => 'M05:v2:' . $id,
        'enfermedad' => $id === 1 ? 'Hipertensión' : ($id === 2 ? 'Diabetes Mellitus' : 'Enfermedad sintética ' . $id),
        'response_id' => $id === 1 ? 801 : 0,
        'answer' => $id === 1 ? 'Sí, tengo hipertensión' : '',
        'tipo' => $id === 1 ? 'Tipo preexistente' : '',
        'year' => $id === 1 ? '2018' : '',
        'version' => str_repeat('a', 64), 'editable' => $id === 1,
        'status' => $id === 1 ? 'REGISTRADA' : 'SIN_REGISTRO',
        'pending' => $id !== 1, 'edit_blocker' => $id === 1 ? '' : 'Sin respuesta registrada.',
        'year_display' => $id === 1 ? '2018' : '',
    ];
}
$data = [
    'title' => 'Respuestas previas', 'csrf' => 'fixture-token', 'sidebar' => '<nav></nav>',
    'entries' => [], 'm02StatusItems' => [], 'm03Themes' => [], 'm04Entries' => [],
    'm05Finalized' => true, 'm05V1Version' => str_repeat('b', 64),
    'm05V2Version' => str_repeat('c', 64),
    'm05V1EditUrl' => 'https://clinica.test/historia-clinica/respuestas-previas/m05/v1',
    'm05V2EditUrl' => 'https://clinica.test/historia-clinica/respuestas-previas/m05/v2',
    'm05V1Themes' => [[
        'theme_id' => 1, 'theme' => 'Antecedentes personales patológicos',
        'entries' => [[
            'question_id' => 11, 'stable_id' => 'M05:v1:11', 'question' => 'Pregunta sintética',
            'response_id' => 701, 'type' => 'input', 'editor_control' => 'text', 'answer' => 'Respuesta completa sintética',
            'version' => str_repeat('b', 64), 'editable' => true,
            'status' => 'REGISTRADA', 'pending' => false, 'edit_blocker' => '',
        ]],
    ]],
    'm05V2Diseases' => $diseases,
];
ob_start();
require $root . '/app/views/paciente/respuestas-previas.php';
$rendered = (string)ob_get_clean();
$assert(preg_match_all('/<article\b[^>]*\bdata-m05-v2-card\b/', $rendered) === 19,
    'The V2 section must render every catalog entry, including diseases without a response row');
$assert(str_contains($rendered, 'Sí, tengo hipertensión')
    && str_contains($rendered, '2018')
    && str_contains($rendered, 'Respuesta completa sintética')
    && str_contains($rendered, 'M05 está finalizado')
    && str_contains($rendered, 'Eliminar el año'),
    'Rendered M05 must show full saved values, year, finalization notice and explicit year-clear action');

echo "PASS M05 previous-responses static/render contract (fixture only; no database writes).\n";
