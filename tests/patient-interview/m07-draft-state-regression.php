<?php

declare(strict_types=1);

/**
 * No-DB regression for M07 draft/negative state boundaries.
 * It invokes only private pure mapping guards through reflection; no PDO
 * connection and no clinical write are performed.
 */
$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';

$reflection = new ReflectionClass(App\Services\M07CanonicalService::class);
$service = $reflection->newInstanceWithoutConstructor();
$status = $reflection->getMethod('status');
$status->setAccessible(true);
$draftBaseline = $reflection->getMethod('assertDraftBaseline');
$draftBaseline->setAccessible(true);

$assert = static function (bool $condition, string $name): void {
    if (!$condition) {
        fwrite(STDERR, "M07 draft regression: FAIL: {$name}\n");
        exit(1);
    }
};

$emptyHistorical = [
    'respuesta' => '', 'dosis' => '', 'resultados' => '', 'consumo' => '', 'descripcion' => '',
    'm07_respuesta_state' => 'NOT_DOCUMENTED_HISTORICAL',
];
$draftBaseline->invoke($service, $emptyHistorical);
$assert(true, 'unanswered_baseline_allowed');

$historicalNegative = [
    'respuesta' => 'No', 'dosis' => '', 'resultados' => '', 'consumo' => '', 'descripcion' => '',
    'm07_respuesta_state' => 'CONFIRMED',
];
$negativeRejected = false;
try {
    $draftBaseline->invoke($service, $historicalNegative);
} catch (App\Services\M07CanonicalException $exception) {
    $negativeRejected = $exception->codeName === 'M07_DRAFT_EXISTING_RESPONSE';
}
$assert($negativeRejected, 'historical_negative_not_reinterpreted');

$pendingPartial = $status->invoke($service, [
    'respuesta' => 'Si', 'dosis' => '10 mg sintético', 'resultados' => '', 'consumo' => 'No', 'descripcion' => '',
], ['m07_resultados_state' => 'NOT_DOCUMENTED_HISTORICAL']);
$assert($pendingPartial === 'pending', 'partial_capture_remains_pending');

$negativeStatus = $status->invoke($service, [
    'respuesta' => 'No', 'dosis' => '', 'resultados' => '', 'consumo' => '', 'descripcion' => '',
], ['m07_resultados_state' => 'NOT_APPLICABLE']);
$assert($negativeStatus === 'complete', 'confirmed_negative_remains_negative');

$emptyStatus = $status->invoke($service, [
    'respuesta' => '', 'dosis' => '', 'resultados' => '', 'consumo' => '', 'descripcion' => '',
], ['m07_resultados_state' => 'NOT_DOCUMENTED_HISTORICAL']);
$assert($emptyStatus === 'pending', 'unanswered_record_is_pending');

$editedStatus = $status->invoke($service, [
    'respuesta' => 'Si', 'dosis' => '10 mg sintético', 'resultados' => 'Funciono', 'consumo' => 'No', 'descripcion' => '',
], ['m07_resultados_state' => 'CONFIRMED']);
$assert($editedStatus === 'complete', 'edited_confirmed_response_remains_complete');

echo "M07 draft state regression: PASS (6 assertions; no database opened)\n";
