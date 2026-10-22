<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$voice = file_get_contents($root . '/public/assets/js/patient-interview-voice.js');
$service = file_get_contents($root . '/app/Services/ConversationalInterviewService.php');
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('M02_CONTEXTUAL_HELP: ' . $message);
};
$assert(is_string($view) && is_string($voice) && is_string($service), 'Required sources must be readable.');

$targetStart = strpos($view, 'const stableTargetForMessage =');
$targetEnd = strpos($view, 'const repeatActiveQuestion =', $targetStart ?: 0);
$targetResolver = $targetStart !== false && $targetEnd !== false ? substr($view, $targetStart, $targetEnd - $targetStart) : '';
$assert(str_contains($targetResolver, "if (module === 'M02')")
    && str_contains($targetResolver, 'card.dataset.stableId')
    && str_contains($targetResolver, "message.querySelector('[data-m02-action-id]')")
    && str_contains($targetResolver, "conversationCoordinator.actionContract(actionId)")
    && str_contains($targetResolver, "action.action_id === actionId")
    && str_contains($targetResolver, "canonicalQuestion?.module_id === 'M02'")
    && str_contains($targetResolver, 'canonicalQuestion.stable_id === stableId'), 'M02 controls must bind to the exact canonical active question, not the module ID.');
$assert(str_contains($view, 'card.dataset.stableId = stableId;')
    && str_contains($view, 'conversationCoordinator.setTarget(stableId)')
    && str_contains($view, "message.querySelectorAll('.chat-voice-button, .chat-help-button')")
    && str_contains($view, 'control.dataset.voiceTarget = resolvedTarget;')
    && str_contains($view, 'bindVoiceControls()'), 'A legitimate M02 question transition must refresh the card target and rebind voice controls to canonical contract data.');

$helpStart = strpos($view, 'const showContractHelp =');
$helpEnd = strpos($view, 'const startRecognition =', $helpStart ?: 0);
$helpHandler = $helpStart !== false && $helpEnd !== false ? substr($view, $helpStart, $helpEnd - $helpStart) : '';
$assert(str_contains($helpHandler, 'const target = snapshot.currentTarget;')
    && str_contains($helpHandler, 'visibleMessages.find(candidate => stableTargetForMessage(candidate) === target)'), 'Help must always resolve from the currently visible canonical question rather than a stale button target.');
$assert(!str_contains($helpHandler, 'La ayuda ya no corresponde a la pregunta activa.'), 'A stale button target must not show a false obsolete-help error when the active question can be resolved.');
$assert(str_contains($helpHandler, 'moduleBlockedVoiceEventFor(target)'), 'Blocked modules must not narrate clinical question help.');
$assert(str_contains($helpHandler, 'question.stable_id !== target'), 'Help must resolve to the exact canonical question.');
$assert(str_contains($helpHandler, 'stableTargetForMessage(candidate) === target'), 'Visible card and active target must remain synchronized during transitions.');
$assert(str_contains($helpHandler, 'panel.textContent = helpText') && str_contains($helpHandler, "panel.setAttribute('role', 'note')"), 'Help must remain visibly available in an accessible contextual block.');
$assert(str_contains($helpHandler, "voiceEvent('HELP', target, false)") && str_contains($helpHandler, 'voiceController.speakEvent(helpEvent'), 'Help narration must use the shared voice controller and canonical HELP contract.');
$assert(str_contains($view, "data-m02-finalize-help-text")
    && str_contains($helpHandler, "action.action_id === 'M02:action:finalize'")
    && str_contains($helpHandler, "action_id: action?.action_id || null")
    && str_contains($helpHandler, "stable_id: action ? null : question.stable_id")
    && str_contains($view, 'Respuestas previas antes de finalizar'), 'Finalization Help must resolve from the real M02 ACTION identity and refer only to the visible history control.');
$assert(!str_contains($helpHandler, 'fetch(') && !str_contains($helpHandler, 'conversationCoordinator.request('), 'Displaying help must not submit or persist a clinical answer.');
$assert(str_contains($view, 'event.metadata?.reason)) clearContextualHelp()'), 'Displayed help must clear when the active target changes.');
$assert(str_contains($view, "\$m02PiperManifest['help_catalog'] ?? []")
    && str_contains($view, 'foreach ($questionIndex as $stableId => $canonicalQuestion)')
    && str_contains($view, "\$canonicalQuestion['voice']['HELP'] ?? ''")
    && str_contains($view, "hash('sha256', \$canonicalHelp)")
    && str_contains($view, "\$m02PiperAudioConfig['entries'][\$stableId]['help']"), 'Optional help audio must be separately mapped and validated against canonical HELP text and its own asset.');
$assert(str_contains($voice, "event.type === 'HELP' ? mappedEntry?.help : mappedEntry"), 'The voice controller must never reuse question audio for HELP.');
$assert(str_contains($service, 'tus propias palabras')
    && str_contains($service, 'No, guardar y continuar')
    && str_contains($service, 'no se registrará como Sí ni como No'), 'Canonical help must describe free-text capture, the visible negative action, and non-persisted uncertainty.');
$assert(str_contains($view, '#F1EAFF') && str_contains($view, '#C4B5FD')
    && str_contains($view, '[data-m02-safe-no]') && !str_contains($view, '[data-m02-quick]'), 'The M02 negative action must retain its visual hierarchy while affirmative quick actions stay removed.');

require_once $root . '/vendor/autoload.php';
$conversation = new App\Services\ConversationalInterviewService();
$questionsMethod = new ReflectionMethod($conversation, 'contractM02Questions');
$questionsMethod->setAccessible(true);
$record = ['disease' => 'Enfermedades del corazón', 'status' => 'pending', 'detail' => null, 'version' => 'test-version', 'relatives' => []];
$closedHelp = $questionsMethod->invoke($conversation, ['multi_relative_write_enabled' => false, 'write_enabled' => true, 'records' => [$record]])[0]['help'];
$openHelp = $questionsMethod->invoke($conversation, ['multi_relative_write_enabled' => true, 'write_enabled' => true, 'records' => [$record]])[0]['help'];
$dynamicHelp = $questionsMethod->invoke($conversation, ['write_enabled' => false, 'records' => [[
    'disease' => 'Enfermedad familiar agregada', 'status' => 'pending', 'detail' => null, 'version' => 'test-version',
]]])[0]['help'];
$assert(str_contains($closedHelp, 'tus propias palabras') && str_contains($closedHelp, 'No, guardar y continuar'), 'Help must show the free-text and negative answer paths.');
$assert($closedHelp === $openHelp, 'Free-text help must not depend on the auxiliary structured-storage flag.');
$assert($dynamicHelp === $closedHelp, 'Dynamically generated M02 family questions must resolve to the same approved generic help contract rather than being omitted by a fixed five-question allowlist.');

$m02 = new App\Services\ConversationalM02Adapter();
$father = $m02->interpret('Enfermedades del corazón', 'Papá');
$mother = $m02->interpret('Enfermedades del corazón', 'Mamá');
$both = $m02->interpret('Enfermedades del corazón', 'Papá y mamá');
$yes = $m02->interpret('Enfermedades del corazón', 'Sí');
$no = $m02->interpret('Enfermedades del corazón', 'No');
$unknown = $m02->interpret('Enfermedades del corazón', 'No recuerdo');
$assert(($father['kind'] ?? '') === 'm02_read_only_proposal' && ($father['detail'] ?? '') === 'Si' && ($father['relative_description'] ?? '') === 'Papá', '“Papá” must be stored as the patient’s free-text description.');
$assert(($mother['kind'] ?? '') === 'm02_read_only_proposal' && ($mother['detail'] ?? '') === 'Si' && ($mother['relative_description'] ?? '') === 'Mamá', '“Mamá” must be stored as the patient’s free-text description.');
$assert(($both['kind'] ?? '') === 'm02_read_only_proposal' && ($both['detail'] ?? '') === 'Si' && ($both['relative_description'] ?? '') === 'Papá y mamá', '“Papá y mamá” must be kept verbatim without catalog classification.');
$assert(($yes['kind'] ?? '') === 'clarification' && ($yes['clarification_reason'] ?? '') === 'positive_first', 'A bare affirmative must ask only which relative.');
$assert(($no['kind'] ?? '') === 'm02_read_only_proposal' && ($no['detail'] ?? '') === 'No', 'A negative answer must preserve the existing negative contract.');
$assert(($unknown['kind'] ?? '') === 'clarification' && ($unknown['detail'] ?? null) === null, '“No recuerdo” must remain uncertain and must never become a negative answer.');

echo "M02_CONTEXTUAL_HELP_CONTRACT=PASS\n";
