<?php
$modalCloseConfig = is_array($modalCloseConfig ?? null) ? $modalCloseConfig : [];
$modalCloseId = trim((string) ($modalCloseConfig['id'] ?? ''));
$modalCloseOnclick = trim((string) ($modalCloseConfig['onclick'] ?? ''));
$modalCloseClass = trim((string) ($modalCloseConfig['class'] ?? ''));
$modalCloseData = is_array($modalCloseConfig['data'] ?? null) ? $modalCloseConfig['data'] : [];

$modalCloseAttributes = '';
if ($modalCloseId !== '') {
    $modalCloseAttributes .= ' id="' . htmlspecialchars($modalCloseId, ENT_QUOTES, 'UTF-8') . '"';
}
if ($modalCloseOnclick !== '') {
    $modalCloseAttributes .= ' onclick="' . htmlspecialchars($modalCloseOnclick, ENT_QUOTES, 'UTF-8') . '"';
}
foreach ($modalCloseData as $attribute => $value) {
    if (!is_string($attribute) || !preg_match('/^data-[a-z0-9-]+$/i', $attribute)) {
        continue;
    }
    $modalCloseAttributes .= ' ' . $attribute . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
}
?>
<button type="button" class="th-modal-close<?= $modalCloseClass !== '' ? ' ' . htmlspecialchars($modalCloseClass, ENT_QUOTES, 'UTF-8') : ''; ?>" aria-label="Cerrar" title="Cerrar"<?= $modalCloseAttributes; ?>>
    <svg class="th-modal-close-icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
        <path d="M18 6 6 18M6 6l12 12"></path>
    </svg>
</button>
