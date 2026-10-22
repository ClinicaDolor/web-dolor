<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$coordinator = file_get_contents($root . '/public/assets/js/patient-interview-coordinator.js');
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};

preg_match('/<div class="d-flex flex-wrap patient-interview-header-actions"[^>]*>(.*?)<\/div>/s', $view, $navigation);
$assert(isset($navigation[1]), 'Interview navigation action group is missing');
$assert(substr_count($navigation[1], '<button') === 0 && substr_count($navigation[1], '<a ') === 2, 'Header navigation must contain only the independent Previous answers page and My clinical history links');
$assert(str_contains($navigation[1], 'href="<?=SERVIDOR;?>historia-clinica/respuestas-previas"')
    && str_contains($navigation[1], 'aria-label="Mi historia clínica"'), 'Required navigation links must remain');
$assert(!preg_match('/<div\b[^>]*data-chat-history-panel/', $view), 'Previous answers must not reopen as an inline interview panel');
$assert(!str_contains($view, 'data-interview-pause') && !str_contains($view, 'data-interview-resume'), 'Pause/resume controls must be removed from rendered UI');
$assert(!str_contains($view, 'pauseButton') && !str_contains($view, 'resumeButton') && !str_contains($view, 'syncPauseControls'), 'Pause/resume DOM references, synchronization, and click listeners must be removed');
$assert(str_contains($view, 'gap:.75rem !important') && str_contains($view, 'min-height:44px'), 'Header actions must have consistent spacing and touch height');
$assert(str_contains($view, 'max-width:430px') && str_contains($view, 'max-width:375px'), 'Responsive header layouts must remain defined');

// Keep the shared coordinator API and request gate; the removed UI had no server-side pause persistence.
$assert(str_contains($coordinator, 'this.paused = false') && str_contains($coordinator, 'pause() {') && str_contains($coordinator, 'resume() {'), 'Shared coordinator pause state/API must not be deleted');
$assert(str_contains($coordinator, "this.paused ? 'COORDINATOR_PAUSED'") && str_contains($coordinator, "reason: 'server_state_reloaded'"), 'Shared pause request guard and in-memory resume contract must remain intact');
$assert(str_contains($controller, '(new ConversationalM03Adapter())->read($patientId)') && str_contains($controller, '(new ConversationalM02Adapter())->read($patientId)'), 'Re-entry must reconstruct module state from persisted adapter reads');
$assert(str_contains($controller, "'conversationContract' => \$conversationContract"), 'Re-entry must supply the unified contract built from module reads');
$assert(str_contains($view, "['NAVIGATING', 'PAUSED', 'MODULE_BLOCKED', 'MODULE_COMPLETED', 'SESSION_EXPIRED']"), 'Shared coordinator pause protections for voice recognition must remain');

echo "Interview pause/resume UI contract: PASS (static; no server or database execution)\n";
