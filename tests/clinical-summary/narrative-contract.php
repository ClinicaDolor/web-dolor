<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\ClinicalNarrativeService;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void { if (!$condition) $failures[] = $message; };
$field = static fn(string $label, ?string $value): array => ['label' => $label, 'value' => $value];
$record = static function (string $key, array $fields) use ($field): array { return ['ref' => $key . ':R001', 'groups' => [['fields' => array_map(fn($item) => $field($item[0], $item[1]), $fields)]]]; };
$empty = static fn(string $key): array => ['key' => $key, 'title' => $key, 'status' => 'absent', 'records' => []];
$module = static fn(string $key, array $records, string $status = 'documented'): array => ['key' => $key, 'title' => $key, 'status' => $status, 'records' => $records];

$modules = [];
foreach (['M01','M02','M03','M04','M05','M06','M07','M08','M09'] as $key) $modules[$key] = $empty($key);
$modules['M01'] = $module('M01', [$record('M01', [['Motivo de atención', 'Dolor de espalda']])]);
$modules['M02'] = $module('M02', [
    $record('M02', [['Enfermedad', 'Hipertensión arterial sistémica'], ['Parentesco especificado', 'Padre'], ['Respuesta documentada', 'Sí']]),
    $record('M02', [['Enfermedad', 'Diabetes mellitus'], ['Parentesco especificado', null], ['Respuesta documentada', 'Sí']]),
    $record('M02', [['Enfermedad', 'Asma'], ['Familiar relacionado', 'Madre'], ['Respuesta documentada', 'No']]),
    $record('M02', [['Enfermedad', 'Migraña'], ['Familiar relacionado', null], ['Respuesta documentada', null]]),
]);
$modules['M03'] = $module('M03', [$record('M03', [['Pregunta', 'Tabaquismo'], ['Respuesta', 'No']])]);
$modules['M05'] = $module('M05', [$record('M05', [['Enfermedad', 'Alergia documentada'], ['Pregunta', 'Alergias'], ['Respuesta', 'Sí']])]);
$modules['M06'] = $module('M06', [$record('M06', [['Medicamento', 'Paracetamol'], ['Dosis', '500 mg'], ['Tiempo de uso', null]])]);
$modules['M07'] = $module('M07', [$record('M07', [['Pregunta', 'Parches de buprenorfina'], ['Respuesta', null]])]);
$modules['M08'] = $module('M08', [$record('M08', [['Procedimiento o tratamiento', 'Bloqueo documentado']])]);
$modules['M09'] = $module('M09', [$record('M09', [['Presencia de dolor documentada', 'Sí'], ['Tiempo de evolución', null], ['Características y localización', 'Espalda'], ['Factores relacionados', null]])]);

$supplement = [
    'notes' => ['records' => [], 'has_more' => false],
    'prescriptions' => ['records' => [['fecha_hora' => '2026-10-10 10:00:00', 'medicamento' => 'Ibuprofeno']], 'has_more' => false],
    'laboratories' => ['records' => [], 'has_more' => false],
    'cofepris' => ['records' => [], 'has_more' => false],
];
$narrative = (new ClinicalNarrativeService())->build(['modules' => $modules], $supplement);
$recordCountsBefore = array_map(static fn(array $module): int => count($module['records']), $modules);
$positive = array_column($narrative['executive']['background']['positive'], 'text');
$negative = array_column($narrative['executive']['background']['negative'], 'text');
$pending = array_column($narrative['executive']['background']['pending'], 'text');
$treatments = array_column($narrative['executive']['treatments']['current_medication'], 'text');

$assert(in_array('Se registra antecedente familiar de Hipertensión arterial sistémica en Padre.', $positive, true), 'positive family relationship should be explicit');
$assert(in_array('Se registra antecedente familiar de Diabetes mellitus, sin parentesco especificado.', $positive, true), 'positive family without relative should be explicit');
$assert(in_array('Se registró respuesta negativa respecto al antecedente familiar de Asma.', $negative, true), 'negative family answer should remain negative');
$assert(!in_array('No se dispone de una respuesta documentada sobre el antecedente familiar de Migraña.', $pending, true), 'null family answer should be omitted from executive noise');
$service = new ClinicalNarrativeService();
$assert($service->classifyResponse(0) === 'numeric', 'numeric zero should be classified as valid data');
$assert($service->classifyResponse(null) === 'no_response', 'NULL should be classified as no response');
$assert($service->classifyResponse('') === 'no_response', 'empty string should be classified as no response');
$assert($service->classifyResponse('No aplica') === 'not_applicable', 'explicit not applicable should be classified separately');
$assert($service->classifyResponse('texto documentado') === 'text', 'free text should be classified as documented text');
$assert(str_contains($treatments[0] ?? '', '500 mg'), 'medication dose should be retained');
$assert(!str_contains(implode(' ', $treatments), 'No registrado'), 'empty medication dose should not become a clinical claim');
$assert(str_contains($narrative['executive']['treatments']['historical_prescriptions'][0]['text'] ?? '', 'prescripción histórica'), 'historical prescription should be distinguished');
$assert(str_contains($narrative['executive']['pain']['summary'], 'información incompleta'), 'incomplete pain evaluation should be identified');
$assert($narrative['executive']['followup'] === [], 'patient without notes should not receive fabricated follow-up');
$withNote = $supplement; $withNote['notes']['records'][] = ['fecha_hora' => '2026-10-11 09:00:00', 'contenido' => 'Seguimiento documentado.'];
$withNoteNarrative = (new ClinicalNarrativeService())->build(['modules' => $modules], $withNote);
$assert(count($withNoteNarrative['executive']['followup']) === 1, 'documented note should appear in follow-up');
$assert(!str_contains($narrative['executive']['general']['text'], 'estable') && !str_contains($narrative['executive']['general']['text'], 'exitoso'), 'narrative should not infer clinical outcome');
$assert(($narrative['pending'][0]['module'] ?? '') === 'M04', 'empty module should produce a concrete module pending item');
$assert(($narrative['executive']['background']['positive'][0]['references'][0] ?? '') === 'M02:R001', 'narrative statements should retain source references');
$htmlRecord = $record('M03', [['Pregunta', '<b>Dolor</b>'], ['Respuesta', '<script>alert(1)</script>']]);
$htmlModules = $modules; $htmlModules['M03'] = $module('M03', [$htmlRecord]);
$htmlNarrative = (new ClinicalNarrativeService())->build(['modules' => $htmlModules], $supplement);
$assert(!str_contains(json_encode($htmlNarrative, JSON_UNESCAPED_UNICODE), '<script>'), 'HTML must not survive narrative sanitization');
$technicalModules = $modules; $technicalModules['M07'] = $module('M07', [$record('M07', [['Pregunta', null], ['Respuesta', null]])]);
$technicalNarrative = (new ClinicalNarrativeService())->build(['modules' => $technicalModules], $supplement);
$assert($technicalNarrative['executive']['treatments']['pain_treatments'] === [], 'technical empty questionnaire rows should not create prose');
$m08CatalogOnly = $module('M08', [['meta' => ['source_kind' => 'tratamiento_cuestionario'], 'groups' => [['fields' => [$field('Procedimiento o tratamiento', 'Bloqueo catalogado'), $field('Utilizó', null)]]]]]);
$m08Narrative = (new ClinicalNarrativeService())->build(['modules' => array_replace($modules, ['M08' => $m08CatalogOnly])], $supplement);
$assert($m08Narrative['executive']['treatments']['procedures'] === [], 'catalog treatment without utilization response should not become a procedure claim');
$m08Answered = $m08CatalogOnly; $m08Answered['records'][0]['groups'][0]['fields'][1]['value'] = 'Sí';
$m08AnsweredNarrative = (new ClinicalNarrativeService())->build(['modules' => array_replace($modules, ['M08' => $m08Answered])], $supplement);
$assert(str_contains($m08AnsweredNarrative['executive']['treatments']['procedures'][0]['text'] ?? '', 'utilizó el tratamiento'), 'answered treatment utilization should remain traceable');
$recordCountsAfter = array_map(static fn(array $module): int => count($module['records']), $modules);
$assert($recordCountsBefore === $recordCountsAfter, 'narrative filtering must not remove persisted detail records');

if ($failures) { fwrite(STDERR, "Clinical narrative contract: FAIL\n" . implode("\n", $failures) . "\n"); exit(1); }
fwrite(STDOUT, "Clinical narrative contract: PASS (24 assertions)\n");
