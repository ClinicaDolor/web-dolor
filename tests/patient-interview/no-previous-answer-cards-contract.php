<?php

declare(strict_types=1);

/**
 * Presentation-only regression for the active conversational capture.
 * It does not open PDO, HTTP, Apache or a clinical database.
 */
$root = dirname(__DIR__, 2);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$m06Adapter = file_get_contents($root . '/app/Services/ConversationalM06Adapter.php');
$m09Adapter = file_get_contents($root . '/app/Services/ConversationalM09Adapter.php');
$m05Adapter = file_get_contents($root . '/app/Services/ConversationalM05Adapter.php');
$m07Service = file_get_contents($root . '/app/Services/M07CanonicalService.php');
if ($view === false || $m06Adapter === false || $m09Adapter === false || $m05Adapter === false || $m07Service === false) {
    throw new RuntimeException('INTERVIEW_FILES_NOT_READABLE');
}

$block = static function (string $source, string $start, string $end): string {
    $from = strpos($source, $start);
    $to = $from === false ? false : strpos($source, $end, $from);
    return $from === false ? '' : substr($source, $from, $to === false ? null : $to - $from);
};

$articlePrefix = '<article class="patient-interview-module-context interview-module-card" ';
$m04 = $block($view, $articlePrefix . 'data-module="M04"', $articlePrefix . 'data-module="M05"');
$m05 = $block($view, $articlePrefix . 'data-module="M05"', $articlePrefix . 'data-module="M05-V2"');
$m06 = $block($view, $articlePrefix . 'data-module="M06"', $articlePrefix . 'data-module="M07"');
$m09 = $block($view, $articlePrefix . 'data-module="M09"', $articlePrefix . 'data-module="M09-MAP"');

$assert($m05 !== '', 'M05 active module block is missing');
$assert(!str_contains($m05, 'm05Existing'), 'M05 still prepares the removed historical summary');
$assert(!str_contains($m05, 'Respuestas M05 existentes'), 'M05 still renders a historical-answer label');
$assert(!str_contains($m05, 'patient-interview-context-grid'), 'M05 still renders accumulated answer cards');
$assert(str_contains($m05, 'data-m05-question-label') && str_contains($m05, 'data-m05-input'), 'M05 active question or input is missing');
$assert(str_contains($m05, 'data-m05-interpret') && str_contains($m05, 'data-m05-quick-actions'), 'M05 send or quick actions are missing');

$assert($m06 !== '' && !str_contains($m06, 'patient-interview-context-grid'), 'M06 still renders a historical medication summary in the active card');
$assert(str_contains($m06Adapter, "'records'") && str_contains($m06Adapter, "'pending_record'"), 'M06 read data needed for persistence and duplicate protection was removed');
$assert($m09 !== '' && !str_contains($m09, 'patient-interview-context-grid'), 'M09 still renders an historical evaluation summary in the active card');
$assert(str_contains($m09Adapter, "'evaluation'") && str_contains($m09Adapter, "'version'"), 'M09 readback/version data was removed from the adapter');

$assert($m04 !== '' && str_contains($m04, 'patient-interview-context-grid') && str_contains($m04, 'Cirugías registradas'), 'M04 operational surgery list was removed');
$assert(str_contains($view, 'data-chat-history') && str_contains($view, 'renderRegisteredAnswers'), 'Previous-answer history access was removed');
$assert(str_contains($m05Adapter, 'resolveV1Eligibility'), 'M05 dependency resolver was removed');
$assert(str_contains($m07Service, 'M07_CANONICAL_DISABLED'), 'M07 closed-gate marker was removed');

echo "No previous-answer cards contract: PASS (M05/M06/M09 active summaries removed; M04 operations and history preserved)\n";
