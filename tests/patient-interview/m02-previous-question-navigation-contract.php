<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('M02_PREVIOUS_QUESTION_NAVIGATION: ' . $message);
};
$assert(is_string($view), 'Patient interview view must be readable.');

$renderStart = strpos($view, '$m02Questions =');
$renderEnd = strpos($view, '<?php foreach ($questionList', $renderStart ?: 0);
$render = $renderStart !== false && $renderEnd !== false ? substr($view, $renderStart, $renderEnd - $renderStart) : '';
$assert(str_contains($render, "foreach (\$m02Questions as \$sourceQuestion)")
    && str_contains($render, "(\$sourceQuestion['module_id'] ?? '') !== 'M02'")
    && str_contains($render, 'data-m02-question-source')
    && str_contains($render, 'data-stable-id="<?=$escape($source[\'stable_id\']);?>"'), 'M02 answer sources must preserve canonical question order and server-provided stable IDs.');
$assert(substr_count($render, 'data-m02-input') === 1
    && substr_count($render, 'data-m02-interpret') === 1
    && str_contains($render, 'data-m02-question-text')
    && str_contains($render, 'data-m02-composer-shell'), 'The active M02 card must render one question, one answer input and one submit control.');
$assert(str_contains($render, 'data-m02-previous hidden disabled')
    && str_contains($render, 'data-m02-next hidden disabled')
    && str_contains($render, 'data-m02-return-current hidden disabled'), 'M02 navigation must provide previous, next and direct return-to-pending controls.');
$assert(str_contains($render, "'answer' => \$sourceAnswer")
    && str_contains($render, "\$sourceLegacyText !== '' ? \$sourceLegacyText")
    && str_contains($render, "\$sourceDetail === 'No' ? 'No'"), 'Saved free-text, explicit negative and structured answers must be projected without converting No to empty.');
$assert(str_contains($render, "!empty(\$data['m02']['write_enabled']) && !\$m02Finalized")
    && str_contains($render, "'editable' => !empty(\$data['m02']['write_enabled']) && !\$m02Finalized"), 'Finalized and non-writable M02 remain read-only.');
$assert(!str_contains($render, 'Responder después') && !str_contains($render, 'm02-saved-answer'), 'The M02 card must not render obsolete deferred-answer or external saved-answer labels.');

$navigationStart = strpos($view, 'const m02Drafts = new Map();');
$navigationEnd = strpos($view, 'const advanceM02 =', $navigationStart ?: 0);
$navigation = $navigationStart !== false && $navigationEnd !== false ? substr($view, $navigationStart, $navigationEnd - $navigationStart) : '';
$assert(str_contains($navigation, 'conversationContract.modules?.M02?.completion?.pending_question_ids')
    && str_contains($navigation, 'conversationContract.question_index?.[stableId]')
    && str_contains($navigation, 'canonical?.module_id !== \'M02\''), 'Active and historical targets must be checked against the canonical M02 index.');
$assert(str_contains($navigation, 'm02SourcesFor(card)')
    && str_contains($navigation, "source.dataset.m02Documented === '1'"), 'Navigation must traverse only sources backed by documented M02 records.');
$assert(str_contains($navigation, 'm02Drafts.set(previousStableId, input.value)')
    && str_contains($navigation, 'm02Drafts.has(stableId) ? m02Drafts.get(stableId) : saved'), 'Switching questions must preserve unsaved drafts and restore exact saved answers without autosaving.');
$assert(str_contains($navigation, 'conversationCoordinator.goBack()')
    && str_contains($navigation, 'conversationCoordinator.setTarget(stableId)')
    && str_contains($navigation, 'card.dataset.m02Disease = source.dataset.m02Disease'), 'Navigation must synchronize coordinator target, disease and version before answer submission.');
$assert(str_contains($navigation, 'input.readOnly = !canWrite')
    && str_contains($navigation, 'source.dataset.m02Editable === \'1\'')
    && str_contains($navigation, "card.dataset.m02Finalized !== '1'"), 'Editing must follow writability, source eligibility and finalization state, not merely enable controls.');
$assert(str_contains($navigation, 'stopAssistantVoice()')
    && str_contains($navigation, 'stopRecognition()')
    && str_contains($navigation, 'bindVoiceControls()'), 'Changing the active M02 question must cancel stale media and rebind existing question controls.');
$assert(str_contains($navigation, 'data-m02-next')
    && str_contains($navigation, 'const finalizedConsultation = card.dataset.m02Consultation === \'1\' && card.dataset.m02Finalized === \'1\'')
    && str_contains($navigation, 'sources.slice(currentIndex + 1).find(source => source.dataset.m02Documented === \'1\')')
    && str_contains($navigation, 'data-m02-return-current')
    && str_contains($navigation, 'closeM02Consultation(card)'), 'Patients must traverse prior responses and return directly to pending, with finalized consultation closable.');
$assert(!str_contains($navigation, 'fetch(')
    && !str_contains($navigation, 'capturar-m02')
    && !str_contains($navigation, 'updateProgress()'), 'Question navigation itself must not write clinical data or alter interview progress.');

$historyStart = strpos($view, 'const renderRegisteredAnswers =');
$historyEnd = strpos($view, 'if (historyButton && historyPanel)', $historyStart ?: 0);
$history = $historyStart !== false && $historyEnd !== false ? substr($view, $historyStart, $historyEnd - $historyStart) : '';
$assert(str_contains($history, 'Consultar preguntas M02')
    && str_contains($history, 'm02ConsultReturnStage = conversationStage')
    && str_contains($history, 'applyM02Source(m02Card, source)'), 'The existing answer-history affordance must open M02 consultation without a new endpoint or write.');
$assert(str_contains($view, 'stableTargetForMessage(message)')
    && !str_contains($view, '.m02-question-row:not(.chat-stage-hidden)'), 'Piper repeat/help must resolve the canonical target from the single visible M02 card.');
$assert(str_contains($view, 'const advanceM02 = (row, finalized = false)')
    && str_contains($view, 'window.location.reload()'), 'The existing explicit save/readback flow remains the only path that advances after a saved M02 answer.');

echo "M02_PREVIOUS_QUESTION_NAVIGATION_CONTRACT=PASS\n";
