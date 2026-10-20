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
$assert(str_contains($m02, 'No convertiré la duda en “No”'), 'M02 unknown state must not become negative');

$noBranch = substr($m03, strpos($m03, 'if ((string)$question[\'tipo\'] === \'select\' && $value === \'No\')'));
$assert(str_contains($noBranch, 'dependentResponses'), 'M03 negative dependency guard missing');
$assert(str_contains($noBranch, 'capturarRespuestaEntrevista'), 'M03 confirmed No must use canonical capture contract');
$assert(!str_contains($noBranch, 'respuesta principal aún no está preparada para guardarse'), 'M03 pending No still has the obsolete rejection');
$assert(str_contains($m03, 'M03_READBACK_FAILED') && str_contains($m03, 'DIRECT_READBACK_FAILED'), 'M03 readback checks missing');
$assert(str_contains($m03, 'serverAuthorizesPreviousEdit'), 'M03 edit authorization must remain server-side');
$assert(str_contains($controller, 'expectedVersion') && str_contains($controller, 'saveAndContinue'), 'M03 version and explicit-save request contract missing');
$assert(str_contains($view, "conversationStages.push({type:'m03'"), 'M03 conversation stage missing');
$assert(str_contains($view, 'advanceM02') && str_contains($view, 'showNextM03Question'), 'M02-to-M03 navigation/readback hooks missing');

// Pure deterministic M02 scenarios exercise the interpretation path without
// constructing a database connection or writing clinical data.
require $root . '/vendor/autoload.php';
$m02Adapter = new App\Services\ConversationalM02Adapter();
$relative = $m02Adapter->interpret('Hipertensión arterial sistémica', 'Mi padre');
$yes = $m02Adapter->interpret('Hipertensión arterial sistémica', 'Sí', $relative['context']);
$unknown = $m02Adapter->interpret('Hipertensión arterial sistémica', 'No recuerdo');
$assert(($relative['kind'] ?? '') === 'clarification' && ($relative['context']['relative'] ?? null) === 'padre', 'M02 relative clarification failed');
$assert(($yes['kind'] ?? '') === 'm02_read_only_proposal' && ($yes['detail'] ?? '') === 'Si' && ($yes['relative'] ?? '') === 'padre', 'M02 affirmative proposal failed');
$assert(($unknown['kind'] ?? '') === 'clarification' && ($unknown['detail'] ?? null) === null, 'M02 unknown was inferred as negative');

$assert(str_contains($service, "['M01', 'M02', 'M03'"), 'Unified M01-M03 order missing');

echo "M01-M03 functional contract: PASS (static integration plus deterministic interpretation; no database writes)\n";
