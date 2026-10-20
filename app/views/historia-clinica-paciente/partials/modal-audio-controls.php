<?php
$config = isset($modalAudioConfig) && is_array($modalAudioConfig) ? $modalAudioConfig : [];
$listenId = (string)($config['listen_id'] ?? 'patientInstructionsListenButton');
$stopId = (string)($config['stop_id'] ?? 'patientInstructionsStopButton');
$group = (string)($config['group'] ?? 'patient-instructions-audio');
$listenAttrs = isset($config['listen_attrs']) && is_array($config['listen_attrs']) ? $config['listen_attrs'] : [];
$stopAttrs = isset($config['stop_attrs']) && is_array($config['stop_attrs']) ? $config['stop_attrs'] : [];

$renderAttributes = static function (array $attributes): string {
    $html = '';
    foreach ($attributes as $name => $value) {
        if ($value === null || $value === false) continue;
        $attribute = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
        if ($value === true) {
            $html .= ' ' . $attribute;
            continue;
        }
        $html .= ' ' . $attribute . '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';
    }
    return $html;
};
?>
<div class="th-modal-audio-controls" data-audio-controls-group="<?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?>" data-audio-state="idle" role="group" aria-label="Controles de audio de las instrucciones">
    <button id="<?= htmlspecialchars($listenId, ENT_QUOTES, 'UTF-8'); ?>" type="button" class="th-modal-audio-button th-modal-audio-listen" data-audio-control="listen" aria-label="Escuchar instrucciones" aria-disabled="false"<?= $renderAttributes($listenAttrs); ?>>
        <svg class="th-modal-audio-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 9v6h4l5 4V5L8 9H4Z" fill="currentColor"/><path d="M16 9.5a4 4 0 0 1 0 5M18.5 7a7.5 7.5 0 0 1 0 10" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2"/></svg>
        <span>Escuchar</span>
    </button>
    <button id="<?= htmlspecialchars($stopId, ENT_QUOTES, 'UTF-8'); ?>" type="button" class="th-modal-audio-button th-modal-audio-stop" data-audio-control="stop" aria-label="Detener instrucciones" aria-disabled="true" disabled<?= $renderAttributes($stopAttrs); ?>>
        <svg class="th-modal-audio-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="7" y="7" width="10" height="10" rx="1" fill="currentColor"/></svg>
        <span>Detener</span>
    </button>
</div>
