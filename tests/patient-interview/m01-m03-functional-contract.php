<?php

declare(strict_types=1);

/**
 * Delivery 09 focal contract. This test is intentionally static plus
 * deterministic: it does not open PDO, HTTP, Apache or any clinical store.
 */
$root = dirname(__DIR__, 2);
$m01 = file_get_contents($root . '/app/Services/ConversationalM01Adapter.php');
$m02 = file_get_contents($root . '/app/Services/ConversationalM02Adapter.php');
$m03 = file_get_contents($root . '/app/Services/ConversationalM03Adapter.php');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$service = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($m01, 'SELECT fecha_nacimiento') && str_contains($m01, "'write_enabled' => false"), 'M01 read-only projection missing');
$assert(!str_contains($m01, 'INSERT') && !str_contains($m01, 'UPDATE') && !str_contains($m01, 'DELETE'), 'M01 adapter must remain read-only');
$assert(str_contains($view, 'data-module="M01"') && str_contains($view, 'M01 consultado'), 'M01 view guidance missing');

$assert(str_contains($m02, 'FIXED_DISEASES') && str_contains($m02, 'expectedVersion'), 'M02 catalog/version contract missing');
$assert(str_contains($controller, 'interpretM02') && str_contains($controller, 'captureM02'), 'M02 controller integration missing');
$assert(str_contains($view, 'patient-interview-question patient-interview-active-question') && !str_contains($view, 'data-m02-defer') && !str_contains($view, 'Responder después'), 'M02 must use one answerable question card without a defer action');
$assert(str_contains($view, "data-m02-safe-no") && str_contains($view, 'fastNo:true'), 'M02 explicit negative action missing');
$assert(str_contains($m02, 'no guarda actualmente un estado persistente de incertidumbre'), 'M02 uncertainty must be disclosed as not durably persisted');

$noBranch = substr($m03, strpos($m03, 'if ((string)$question[\'tipo\'] === \'select\' && $value === \'No\')'));
$assert(str_contains($noBranch, 'dependentResponses'), 'M03 negative dependency guard missing');
$assert(str_contains($noBranch, 'capturarRespuestaEntrevista'), 'M03 confirmed No must use canonical capture contract');
$assert(!str_contains($noBranch, 'respuesta principal aún no está preparada para guardarse'), 'M03 pending No still has the obsolete rejection');
$assert(str_contains($m03, 'M03_READBACK_FAILED') && str_contains($m03, 'DIRECT_READBACK_FAILED'), 'M03 readback checks missing');
$historyPolicyStart = strpos($m03, 'private static function historicalEditableQuestionIds(');
$historyPolicyEnd = strpos($m03, 'public function interpret(', $historyPolicyStart ?: 0);
$historyPolicy = $historyPolicyStart !== false && $historyPolicyEnd !== false
    ? substr($m03, $historyPolicyStart, $historyPolicyEnd - $historyPolicyStart)
    : '';
$historicalAuthorizationStart = strpos($m03, 'private function serverAuthorizesHistoricalEdit(');
$historicalAuthorizationEnd = strpos($m03, 'private function nextPendingQuestionId(', $historicalAuthorizationStart ?: 0);
$historicalAuthorization = $historicalAuthorizationStart !== false && $historicalAuthorizationEnd !== false
    ? substr($m03, $historicalAuthorizationStart, $historicalAuthorizationEnd - $historicalAuthorizationStart)
    : '';
$assert($historyPolicy !== ''
    && str_contains($historyPolicy, 'if (!$writeEnabled || $finalized) return [];')
    && str_contains($historyPolicy, "!empty(\$question['eligible'])")
    && str_contains($historyPolicy, "(int)(\$question['idRespuesta'] ?? 0) > 0")
    && str_contains($historyPolicy, "['UNKNOWN', 'NOT_REMEMBERED']")
    && str_contains($historyPolicy, 'if ($firstPending === null || $firstPending < 1) return [];')
    && str_contains($historyPolicy, 'array_slice($eligible, 0, $firstPending)'),
    'M03 historical edit scope must be a persisted eligible prefix before the first pending question and close when writes are disabled or finalized');
$assert(str_contains($m03, '$editableQuestionIds = self::historicalEditableQuestionIds(')
    && str_contains($m03, "(bool)(\$policy['editable'] ?? false)")
    && str_contains($m03, "(bool)(\$policy['finalized'] ?? true)")
    && str_contains($m03, "\$question['eligible'] = \$parent === null || (int)\$question['idPregunta'] === \$parent || \$parentIsAffirmative;")
    && str_contains($historicalAuthorization, 'return !empty($question[\'eligible\']) && !empty($question[\'historicalEditable\']);'),
    'M03 historical authorization must derive from server write/finalization policy and reject ineligible or out-of-prefix questions');
$captureStart = strpos($m03, 'public function capture(');
$captureEnd = strpos($m03, 'public function captureDirectText(', $captureStart ?: 0);
$captureBody = $captureStart !== false && $captureEnd !== false ? substr($m03, $captureStart, $captureEnd - $captureStart) : '';
$assert($captureBody !== ''
    && str_contains($captureBody, "if (!\$state['write_enabled'])")
    && str_contains($captureBody, 'if ($expectedResponseId !== null && $expectedResponseId > 0')
    && str_contains($captureBody, "'M03_RESPONSE_ID_CONFLICT'")
    && str_contains($captureBody, "!hash_equals((string)\$state['version'], \$expectedVersion)")
    && str_contains($captureBody, "\$expectedResponseId !== (int)\$question['idRespuesta']")
    && str_contains($captureBody, 'M03_PREVIOUS_EDIT_NOT_AUTHORIZED'),
    'M03 historical capture must reject disabled writes, stale versions, missing or mismatched response identity, and unauthorized history');
$historyEditStart = strpos($m03, 'public function editExistingResponse(');
$historyEditEnd = strpos($m03, 'public static function formatPreviousResponse(', $historyEditStart ?: 0);
$historyEditBody = $historyEditStart !== false && $historyEditEnd !== false
    ? substr($m03, $historyEditStart, $historyEditEnd - $historyEditStart)
    : '';
$m03EndpointStart = strpos($controller, 'public function updatePreviousM03(): void');
$m03EndpointEnd = strpos($controller, 'private function formatM02CurrentAnswer(', $m03EndpointStart === false ? 0 : $m03EndpointStart);
$m03Endpoint = $m03EndpointStart !== false && $m03EndpointEnd !== false
    ? substr($controller, $m03EndpointStart, $m03EndpointEnd - $m03EndpointStart)
    : '';
$assert($historyEditBody !== ''
    && str_contains($historyEditBody, "(int)(\$question['idRespuesta'] ?? 0) !== \$responseId")
    && str_contains($historyEditBody, 'previousResponseVersion($patientId, $question)')
    && str_contains($historyEditBody, '!hash_equals(self::previousResponseVersion($patientId, $question), $expectedVersion)')
    && str_contains($historyEditBody, 'M03_PREVIOUS_VERSION_CONFLICT')
    && str_contains($historyEditBody, 'previousResponseEditBlockerCode($state, $question)')
    && str_contains($historyEditBody, "['KNOWN', 'NEGATIVE', 'UNKNOWN', 'NOT_REMEMBERED']")
    && str_contains($historyEditBody, "'idRespuesta' => \$responseId")
    && str_contains($historyEditBody, 'actualSemantic !== $semanticState')
    && str_contains($historyEditBody, "'M03_PREVIOUS_READBACK_FAILED'"),
    'M03 history edit must validate the same response/version/state and independently read back the updated answer');
$assert($m03Endpoint !== ''
    && str_contains($m03Endpoint, "new AuthMiddleware('historia-clinica')")
    && str_contains($m03Endpoint, "\$patientId = (int)(\$auth['id_usuario'] ?? 0);")
    && str_contains($m03Endpoint, 'assertPatientAccess($patientId, $auth)')
    && str_contains($m03Endpoint, 'validCaptureCsrf($patientId')
    && str_contains($m03Endpoint, "\$_SERVER['HTTP_X_M03_CSRF']")
    && str_contains($m03Endpoint, "(int)(\$questionRows[0]['idRespuesta'] ?? 0) !== (int)\$responseId")
    && str_contains($m03Endpoint, 'editExistingResponse($patientId, $questionId, (int)$responseId, $expectedVersion'),
    'M03 historical edit endpoint must derive patient ownership from the authenticated session and require CSRF, question identity, response identity and version');
$modelSource = file_get_contents($root . '/app/Models/AntecedentesNoPatologicosModel.php');
$modelEditStart = strpos($modelSource, 'public function actualizarRespuestaM03Existente(');
$modelEditEnd = strpos($modelSource, 'private function persistSemanticResponse(', $modelEditStart ?: 0);
$modelEditBody = $modelEditStart !== false && $modelEditEnd !== false
    ? substr($modelSource, $modelEditStart, $modelEditEnd - $modelEditStart)
    : '';
$assert($modelEditBody !== ''
    && str_contains($modelEditBody, 'M03_DEPENDENCY_REVIEW_REQUIRED')
    && str_contains($modelEditBody, 'beginTransaction()')
    && str_contains($modelEditBody, 'UPDATE pac_respuestas_paciente_modulo_3 SET respuesta=:detail')
    && str_contains($modelEditBody, 'WHERE id=:response AND id_paciente=:patient AND id_pregunta=:question')
    && str_contains($modelEditBody, 'M03_PREVIOUS_VERSION_CONFLICT')
    && str_contains($modelEditBody, 'M03_PREVIOUS_UNKNOWN_VALUE_INVALID')
    && str_contains($modelEditBody, 'M03_PREVIOUS_NEGATIVE_INVALID')
    && str_contains($modelEditBody, 'M03_PREVIOUS_READBACK_FAILED')
    && !preg_match('/\bINSERT\s+INTO\s+pac_respuestas_paciente_modulo_3\b/i', $modelEditBody)
    && !str_contains($modelEditBody, 'pac_historia_clinica_finalizar'),
    'M03 historical editing must preserve dependent responses, update only the existing patient/question/response row transactionally with readback, and leave module finalization untouched');
$assert(str_contains($controller, 'expectedVersion') && str_contains($controller, 'saveAndContinue'), 'M03 version and explicit-save request contract missing');
$assert(str_contains($view, "conversationStages.push({type:'m03'"), 'M03 conversation stage missing');
$assert(str_contains($view, 'advanceM02') && str_contains($view, 'showNextM03Question'), 'M02-to-M03 navigation/readback hooks missing');

// Pure deterministic M02 scenarios exercise the interpretation path without
// constructing a database connection or writing clinical data.
require $root . '/vendor/autoload.php';
$historicalPrefix = new ReflectionMethod(App\Services\ConversationalM03Adapter::class, 'historicalEditableQuestionIds');
$historicalPrefix->setAccessible(true);
$historicalQuestion = static fn(int $id, int $responseId, string $answer, string $semantic, bool $eligible = true): array => [
    'idPregunta' => $id,
    'idRespuesta' => $responseId,
    'respuesta' => $answer,
    'semanticState' => $semantic,
    'eligible' => $eligible,
];
$historicalQuestions = [
    $historicalQuestion(1, 101, 'Si', 'KNOWN'),
    $historicalQuestion(2, 102, 'No', 'NEGATIVE'),
    $historicalQuestion(3, 103, '', 'UNKNOWN'),
    $historicalQuestion(4, 104, '', 'NOT_REMEMBERED'),
    $historicalQuestion(5, 0, '', 'UNANSWERED'),
    $historicalQuestion(6, 106, '8 años', 'KNOWN'),
    $historicalQuestion(7, 107, '10 cigarros', 'KNOWN', false),
];
$historicalIds = $historicalPrefix->invoke(null, $historicalQuestions, true, false);
$assert($historicalIds === [1, 2, 3, 4], 'M03 history must include persisted positive, negative, UNKNOWN and NOT_REMEMBERED answers only before the first pending answer');
$assert($historicalPrefix->invoke(null, $historicalQuestions, false, false) === []
    && $historicalPrefix->invoke(null, $historicalQuestions, true, true) === [],
    'M03 historical editing must be disabled when writes are unavailable or the interview module is finalized');
$missingSemanticId = [
    $historicalQuestion(1, 201, 'Si', 'KNOWN'),
    $historicalQuestion(2, 0, '', 'UNKNOWN'),
    $historicalQuestion(3, 203, '42', 'KNOWN'),
];
$assert($historicalPrefix->invoke(null, $missingSemanticId, true, false) === [1],
    'M03 semantic UNKNOWN without a persisted response identity must remain pending and stop historical editing beyond it');
$historicalAuthorizer = new ReflectionMethod(App\Services\ConversationalM03Adapter::class, 'serverAuthorizesHistoricalEdit');
$historicalAuthorizer->setAccessible(true);
$authorizationRows = array_map(static function (array $question) use ($historicalIds): array {
    $question['historicalEditable'] = in_array((int)$question['idPregunta'], $historicalIds, true);
    return $question;
}, $historicalQuestions);
$assert($historicalAuthorizer->invoke(new App\Services\ConversationalM03Adapter(), $authorizationRows, 2) === true
    && $historicalAuthorizer->invoke(new App\Services\ConversationalM03Adapter(), $authorizationRows, 3) === true
    && $historicalAuthorizer->invoke(new App\Services\ConversationalM03Adapter(), $authorizationRows, 5) === false
    && $historicalAuthorizer->invoke(new App\Services\ConversationalM03Adapter(), $authorizationRows, 6) === false
    && $historicalAuthorizer->invoke(new App\Services\ConversationalM03Adapter(), $authorizationRows, 7) === false,
    'M03 server authorization must accept eligible persisted history and reject pending, out-of-prefix and ineligible answers');
$m02Adapter = new App\Services\ConversationalM02Adapter();
$yes = $m02Adapter->interpret('Hipertensión arterial sistémica', 'Sí');
$relative = $m02Adapter->interpret('Hipertensión arterial sistémica', 'Mi padre', $yes['context']);
$unknown = $m02Adapter->interpret('Hipertensión arterial sistémica', 'No recuerdo');
$assert(($yes['kind'] ?? '') === 'clarification' && ($yes['clarification_reason'] ?? '') === 'positive_first', 'M02 bare yes must request a family description');
$assert(($relative['kind'] ?? '') === 'm02_read_only_proposal' && ($relative['detail'] ?? '') === 'Si' && ($relative['relative_description'] ?? '') === 'Mi padre', 'M02 free-text description after yes failed');
$assert(($unknown['kind'] ?? '') === 'clarification' && ($unknown['detail'] ?? null) === null, 'M02 unknown was inferred as negative');

$assert(str_contains($service, "['M01', 'M02', 'M03'"), 'Unified M01-M03 order missing');

echo "M01-M03 functional contract: PASS (static integration plus deterministic interpretation; no database writes)\n";
