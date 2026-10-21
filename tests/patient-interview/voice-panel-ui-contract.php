<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$voice = file_get_contents($root . '/public/assets/js/patient-interview-voice.js');
$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};

preg_match('/<div class="voice-action-row">(.*?)<\/div>/s', $view, $actionRow);
$assert(isset($actionRow[1]), 'Voice action row is missing');
$assert(substr_count($actionRow[1], '<button') === 2, 'Voice action row must contain exactly two buttons');
$assert(str_contains($actionRow[1], 'data-voice-repeat') && str_contains($actionRow[1], 'data-voice-stop'), 'Repeat and stop actions must remain in the row');
$assert(str_contains($actionRow[1], 'data-feather="repeat"') && str_contains($actionRow[1], 'data-feather="square"'), 'Actions must use the existing repeat and stop icons');
$assert(str_contains($actionRow[1], 'aria-label="Repetir pregunta"') && str_contains($actionRow[1], 'title="Repetir pregunta"'), 'Repeat icon button must be accessible');
$assert(str_contains($actionRow[1], 'aria-label="Detener narración"') && str_contains($actionRow[1], 'title="Detener narración"'), 'Stop icon button must be accessible');
$assert(str_contains($view, 'grid-template-columns:repeat(2,minmax(0,1fr))') && str_contains($view, 'gap:.8rem'), 'The two actions must share a horizontal equal-width row');
$assert(str_contains($view, 'min-width:44px; min-height:44px'), 'Icon actions must satisfy the 44px touch target');
$assert(str_contains($view, 'data-voice-rate') && str_contains($view, 'for="interview-voice-rate"'), 'Speed control must remain in the voice panel');
$assert(str_contains($view, '<option value="1.0" selected>Normal</option>') && str_contains($view, 'voiceController.setRate(voiceRateControl?.value || 1)'), 'Normal speed must mean 1.0x and initialize the shared controller');
$assert(str_contains($view, 'visually-hidden" data-voice-status aria-live="polite"'), 'Status announcements must remain accessible without occupying visual space');
$assert(str_contains($view, 'data-voice-selector hidden') && !str_contains($view, 'data-voice-selector-toggle'), 'Voice-selection infrastructure remains hidden and cannot be tabbed to from a removed toggle');
$assert(!str_contains($view, 'data-voice-start') && !str_contains($view, 'data-voice-pause') && !str_contains($view, 'data-voice-resume') && !str_contains($view, 'data-voice-mute'), 'Separate start, pause and mute controls must not be rendered');
$assert(str_contains($view, "const repeatOrUnlockVoice = () => repeatActiveQuestion();") && str_contains($view, "voiceRepeatButton?.addEventListener('click', repeatOrUnlockVoice);"), 'The sidebar repeat button must use the shared active-question handler');
$assert(str_contains($view, "voiceController.activate(event, null, {userGesture:true})") && str_contains($view, "voiceController.speakEvent(event, {force:true, userGesture:true})") && str_contains($view, "button.addEventListener('click', () => repeatActiveQuestion(button))"), 'Both panel and contextual repeat must use a real gesture and the active canonical question');
$assert(substr_count($view, "voiceRepeatButton?.addEventListener('click', repeatOrUnlockVoice);") === 1 && str_contains($view, "message.dataset.voiceBound = '1';"), 'Repeat controls must not receive duplicate click handlers');
$assert(str_contains($view, 'voiceController.muted || !voiceController.enabled || !voiceController.lastEvent'), 'Repeat must recover a saved mute/disabled state from a real user gesture');
$assert(str_contains($view, "voiceStopButton?.addEventListener('click', () => stopAssistantVoice())") && str_contains($view, 'const stopAssistantVoice = () => { voiceController.cancel();'), 'Stop must cancel and clear current playback');
$assert(!str_contains($view, 'voiceStartButton') && !str_contains($view, 'voiceSelectorToggle'), 'Removed panel controls must not leave dangling JavaScript references');
$assert(str_contains($view, "voiceRateControl?.addEventListener('change', () => voiceController.setRate(voiceRateControl.value))"), 'Speed control must update the shared controller');
$assert(str_contains($voice, 'this.activeAudio.playbackRate = next'), 'Speed updates must reach Piper HTMLAudioElement playback');
$assert(str_contains($voice, 'utterance.rate = this.rate'), 'Speed updates must reach Web Speech utterances');
$assert(str_contains($voice, 'this.clearActiveAudio(true)') && str_contains($voice, 'this.speechApi?.cancel()'), 'Cancellation must stop Piper and Web Speech without overlap');

echo 'Voice panel UI contract: PASS (' . substr_count(file_get_contents(__FILE__), '$assert(') . " assertions; static only)\n";
