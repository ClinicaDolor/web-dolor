<?php

$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$questionsJson = json_encode($data['questions'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$conversationContractJson = json_encode($data['conversationContract'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
$questionList = $data['questions'] ?? [];
$versionedAsset = static function (string $baseUrl, string $assetPath, string $publicPath): string {
    $filesystemPath = realpath(dirname(__DIR__, 3) . '/public/' . ltrim($publicPath, '/'));
    $version = $filesystemPath !== false && is_file($filesystemPath) ? filemtime($filesystemPath) : false;

    return $baseUrl . $assetPath . ($version === false ? '' : '?v=' . (int)$version);
};
$bootstrapCssUrl = $versionedAsset(RUTA_CSS, 'bootstrap.css', 'assets/css/bootstrap.css');
$perfectScrollbarCssUrl = $versionedAsset(RUTA_PUBLIC, 'libs/perfect-scrollbar/perfect-scrollbar.css', 'assets/libs/perfect-scrollbar/perfect-scrollbar.css');
$appCssUrl = $versionedAsset(RUTA_CSS, 'app.css', 'assets/css/app.css');
$coordinatorJsUrl = $versionedAsset(RUTA_JS, 'patient-interview-coordinator.js', 'assets/js/patient-interview-coordinator.js');
$voiceJsUrl = $versionedAsset(RUTA_JS, 'patient-interview-voice.js', 'assets/js/patient-interview-voice.js');
$logoUrl = $versionedAsset(RUTA_IMAGES, 'logo-clinica.png', 'assets/images/logo-clinica.png');
$firstPending = count($questionList);
foreach ($questionList as $questionIndex => $questionItem) {
    $eligible = ($questionItem['eligible'] ?? true) === true;
    $semanticState = strtoupper(trim((string)($questionItem['semanticState'] ?? $questionItem['estadoRespuesta'] ?? '')));
    $hasSemanticAnswer = in_array($semanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true);
    if ($eligible && trim((string)($questionItem['respuesta'] ?? '')) === '' && !$hasSemanticAnswer) { $firstPending = $questionIndex; break; }
}
if ($firstPending >= count($questionList) && $questionList) {
    foreach ($questionList as $questionIndex => $questionItem) {
        if (($questionItem['eligible'] ?? true) === true) { $firstPending = $questionIndex; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$escape($data['title'] ?? 'Entrevista Conversacional');?></title>
    <link rel="shortcut icon" href="<?=$logoUrl;?>">
    <link rel="stylesheet" href="<?=$bootstrapCssUrl;?>">
    <link rel="stylesheet" href="<?=$perfectScrollbarCssUrl;?>">
    <link rel="stylesheet" href="<?=$appCssUrl;?>">
    <style>
        body.patient-interview-page { overflow-x:clip; }
        .patient-interview-page { background:#f4f0ff; }
        .patient-interview-main { min-height:calc(100vh - 4rem); background:linear-gradient(180deg,#f7f4ff 0%,#f1edff 100%); overflow-x:clip; font-size:16px; }
        .patient-interview-shell { width:100%; max-width:1360px; min-width:0; margin:0 auto; }
        .patient-interview-header { display:grid; grid-template-columns:minmax(0,1.15fr) minmax(30rem,.85fr); gap:1rem; align-items:center; }
        .patient-interview-header, .patient-interview-conversation-card { border:1px solid #e5ddf7; background:#fff; border-radius:1.25rem; box-shadow:0 .45rem 1.25rem rgba(72,45,120,.08); }
        .patient-interview-header { padding:.75rem 1rem; }
        .patient-interview-header-copy { min-width:0; }
        .patient-interview-header-copy .dashboard-eyebrow { margin-bottom:.25rem; font-size:.72rem; letter-spacing:.08em; }
        .patient-interview-header-copy h1 { margin-bottom:.2rem; font-size:clamp(1.6rem,2vw,1.85rem); line-height:1.1; font-weight:600; }
        .patient-interview-header-copy .patient-interview-muted { max-width:100%; overflow:hidden; font-size:.92rem; line-height:1.3; text-overflow:ellipsis; white-space:nowrap; }
        .patient-interview-header-side { display:grid; gap:.45rem; min-width:0; }
        .patient-interview-header-context { display:flex; justify-content:flex-end; align-items:center; gap:.4rem; flex-wrap:wrap; }
        .patient-interview-header-progress { padding:.5rem .7rem; border:1px solid #ebe3f8; border-radius:.75rem; background:#fcfbff; }
        .patient-interview-header-progress .interview-progress-layout { align-items:center; }
        .patient-interview-header-progress .interview-progress-layout strong, .patient-interview-header-progress #interview-progress { font-size:.84rem; }
        .patient-interview-header-progress .progress { height:.4rem; background:#e8e0f8; }
        .patient-interview-header-progress .progress-bar { background:linear-gradient(90deg,#5b16c8,#1b8f7a); }
        .patient-interview-header-actions { justify-content:flex-end; flex-wrap:nowrap; gap:.4rem; }
        .patient-interview-header-actions > * { min-height:2.3rem; padding:.35rem .65rem; font-size:.875rem; }
        .patient-interview-header-actions [data-feather] { width:1rem; height:1rem; vertical-align:-.15em; }
        .interview-module-badge, .interview-state-badge { min-height:1.75rem; padding:.25rem .6rem; font-size:.78rem; }
        .patient-interview-page .btn { white-space:nowrap; word-break:normal; overflow-wrap:normal; }
        .patient-interview-topbar-inner { display:flex; align-items:center; gap:.9rem; }
        .patient-interview-breadcrumb { color:#3b2464; font-weight:650; }
        .patient-interview-breadcrumb span { margin-inline:.3rem; color:#8c71b8; font-size:1.2rem; }
        .portal-topbar .portal-badge { margin-left:auto; display:inline-flex; align-items:center; gap:.5rem; }
        .portal-status-dot { width:.7rem; height:.7rem; border-radius:50%; background:#1baa78; box-shadow:0 0 0 .22rem rgba(27,170,120,.14); }
        .patient-interview-header h1, #interview-chat h2 { color:#27154e; }
        .patient-interview-conversation-card > .card-body { padding:1.35rem; }
        .patient-interview-conversation-layout { display:grid; grid-template-columns:minmax(0,1fr) minmax(16rem,19rem); gap:1.35rem; align-items:start; }
        #interview-chat { display:flex; flex-direction:column; gap:1rem; border:0; background:transparent; border-radius:0; box-shadow:none; }
        .patient-interview-conversation-card { min-width:0; overflow:visible; }
        .patient-interview-context-panel { display:grid; gap:.9rem; }
        .patient-interview-context-card { padding:1rem; border:1px solid #e5ddf7; border-radius:1rem; background:#fff; box-shadow:0 .25rem .8rem rgba(72,45,120,.05); }
        .patient-interview-context-card h3 { display:flex; align-items:center; gap:.55rem; margin:0 0 .75rem; color:#27154e; font-size:1rem; }
        .patient-interview-context-card h3 [data-feather] { width:1.15rem; height:1.15rem; color:#5b16c8; }
        .patient-interview-context-current { padding:.75rem; border-radius:.75rem; background:#f4efff; }
        .patient-interview-context-current strong { color:#5b16c8; }
        .patient-interview-context-current p { margin:.3rem 0 0; color:#587080; line-height:1.35; }
        .patient-interview-tips { margin:0; padding:0; list-style:none; display:grid; gap:.55rem; color:#587080; }
        .patient-interview-tips li { display:flex; gap:.55rem; line-height:1.35; }
        .patient-interview-tips li::before { content:'•'; color:#1b9f7d; font-weight:800; }
        .patient-interview-message { max-width:82%; min-width:0; padding:1rem 1.15rem; border-radius:1rem; box-shadow:0 0.35rem 1rem rgba(31,52,72,.08); overflow-wrap:anywhere; font-size:1rem; line-height:1.5; }
        .patient-interview-message.assistant { align-self:stretch; width:100%; max-width:100%; background:linear-gradient(135deg,#f4efff 0%,#eef5f7 100%); color:#173042; }
        .patient-interview-message.patient { align-self:flex-end; background:#1b6b73; color:#fff; }
        .patient-interview-message[hidden] { display:none; }
        .interview-module-card { padding:0; border:0; border-radius:0; background:transparent; box-shadow:none; }
        .interview-module-card[hidden] { display:none !important; }
        .interview-message-header { display:flex; align-items:center; justify-content:space-between; gap:.75rem; min-height:2.75rem; flex-wrap:wrap; }
        .interview-message-header > .chat-voice-button, .interview-message-header > .chat-help-button { flex:0 0 auto; }
        .interview-message-header::before { content:'✦'; flex:0 0 2.75rem; width:2.75rem; height:2.75rem; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; color:#5a13bb; background:#f0eaff; font-size:1.35rem; }
        .interview-message-header > strong { flex:1; min-width:0; }
        .interview-question-text { margin:.45rem 0 0; color:#274968; font-size:1.08rem; line-height:1.5; }
        .patient-interview-question > .patient-interview-message.assistant { padding:1.2rem 1.35rem; border:1px solid #e4daf7; background:linear-gradient(135deg,#f5f0ff 0%,#edf5f7 100%); }
        .patient-interview-question > .patient-interview-message.assistant .interview-message-header strong { color:#27154e; font-size:1rem; }
        .patient-interview-question > .patient-interview-message.assistant .interview-question-text { color:#1f2854; font-size:clamp(1.35rem,2.2vw,1.65rem); font-weight:700; line-height:1.25; }
        .interview-composer-shell { margin-top:.8rem; padding:1rem; border:1px solid #e2d9f4; border-radius:1rem; background:#fff; box-shadow:0 .2rem .7rem rgba(72,45,120,.05); }
        .interview-composer-help { display:flex; gap:.45rem; align-items:flex-start; margin:.7rem 0 0; color:#52627a; font-size:.95rem; line-height:1.4; }
        .interview-composer-footer { display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; }
        .interview-answer-count { flex:0 0 auto; color:#587080; font-size:.82rem; white-space:nowrap; }
        .patient-interview-answer { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-top:1rem; }
        .patient-interview-answer input { min-width:min(100%, 25rem); }
        .patient-interview-answer button { min-height:2.9rem; }
        .patient-interview-muted { color:#52627a; font-size:.95rem; }
        .patient-interview-context-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(12rem,1fr)); gap:.75rem; margin-top:.85rem; }
        .patient-interview-context-grid > div { padding:.75rem; border:1px solid #dcebed; border-radius:.75rem; background:#fbfefe; }
        .patient-interview-context-grid dt { color:#587080; font-size:.82rem; }
        .patient-interview-context-grid dd { margin:.2rem 0 0; color:#173042; }
        .chat-p11-map { border:1px solid #dcebed; border-radius:1rem; padding:1rem; background:#fbfefe; }
        .chat-p11-map-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(16rem,1fr)); gap:1rem; }
        .chat-p11-canvas-wrap { overflow:auto; background:#f1f5f6; border-radius:.75rem; text-align:center; }
        .chat-p11-canvas-wrap canvas { width:100%; max-width:22rem; height:auto; touch-action:none; background:#fff; }
        .chat-p11-preview { max-width:22rem; width:100%; border:2px solid #1b6b73; border-radius:.75rem; }
        .chat-stage-hidden { display:none !important; }
        .chat-history-panel { border:1px solid #dcebed; border-radius:.75rem; padding:.75rem 1rem; background:#fbfefe; }
        .chat-history-list { display:grid; gap:.65rem; margin:0; padding:0; list-style:none; }
        .chat-history-item { padding:.8rem .9rem; border:1px solid #e4daf2; border-radius:.75rem; background:#fff; }
        .chat-history-item dl { margin:0; }
        .chat-history-item dt { color:#587080; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
        .chat-history-item dd { margin:.2rem 0 0; color:#173042; line-height:1.45; overflow-wrap:anywhere; }
        .chat-history-empty { margin:0; color:#587080; }
        .m07-categories { display:grid; gap:1rem; }
        .m07-category { border:1px solid #dcebed; border-radius:1rem; padding:1rem; background:#fbfefe; }
        .m07-items { display:grid; gap:.75rem; }
        .m07-item { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.5rem .75rem; align-items:start; padding:.85rem; border:1px solid #e2edf0; border-radius:.75rem; background:#fff; }
        .m07-item-choice { display:flex; gap:.6rem; align-items:flex-start; min-width:0; color:#173042; font-weight:600; }
        .m07-item-choice span { overflow-wrap:anywhere; }
        .m07-item-details { grid-column:1 / -1; padding-top:.5rem; border-top:1px solid #edf3f4; }
        .m07-item-details[hidden] { display:none; }
        .m07-actions { justify-content:flex-start; }
        .m07-category-nav { display:flex; flex-wrap:wrap; align-items:center; gap:.45rem; margin:.9rem 0 1rem; padding:.75rem; border:1px solid #e2edf0; border-radius:.75rem; background:#fff; }
        .m07-category-nav button[aria-current="true"] { color:#fff; background:#5b1978; border-color:#5b1978; }
        .m07-category[hidden] { display:none; }
        .m07-navigation { justify-content:space-between; }
        [data-m07-status] { white-space:normal; text-align:center; }
        body.patient-interview-page [data-chat-history] { display:inline-flex; align-items:center; gap:.4rem; color:#5b1978; background:#fff; border-color:#5b1978; transition:background-color .18s ease,color .18s ease,border-color .18s ease; }
        body.patient-interview-page [data-chat-history]:hover, body.patient-interview-page [data-chat-history]:focus-visible { color:#fff; background:#5b1978; border-color:#5b1978; }
        body.patient-interview-page [data-chat-history] svg { width:1rem; height:1rem; }
        .interview-previous-button { display:inline-flex; align-items:center; gap:.35rem; min-height:2.5rem; margin:0 0 .7rem; padding:.45rem .7rem; color:#5b1978; background:transparent; border:1px solid transparent; border-radius:.6rem; font-size:.9rem; font-weight:650; }
        .interview-previous-button:hover, .interview-previous-button:focus-visible { color:#5b1978; background:#f3e8fa; border-color:#d9c7eb; }
        .interview-previous-button[hidden] { display:none; }
        body.patient-interview-page .patient-interview-sidebar-toggle { display:inline-flex; align-items:center; justify-content:center; gap:.4rem; min-height:2.6rem; min-width:2.6rem; padding:.45rem .7rem; color:#5b1978; background:transparent; border:1px solid transparent; border-radius:.65rem; }
        body.patient-interview-page .navbar.navbar-header .patient-interview-sidebar-toggle::before { content:none; }
        body.patient-interview-page .patient-interview-sidebar-toggle:hover, body.patient-interview-page .patient-interview-sidebar-toggle:focus-visible { background:#f3e8fa; border-color:#d9c7eb; }
        @media (max-width:767.98px) { body.patient-interview-page .patient-interview-sidebar-toggle { display:inline-flex; } body.patient-interview-page .interview-previous-button { margin-bottom:.55rem; } }
        .interview-composer { display:grid; grid-template-columns:minmax(0,1fr) auto; grid-template-rows:auto auto; gap:.65rem .7rem; align-items:center; width:100%; min-width:0; }
        .interview-composer-input { grid-column:1; grid-row:1; min-width:0; min-height:3.9rem; display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:.25rem; padding:.2rem .35rem .2rem .9rem; border:1px solid #bdc7dc; border-radius:.85rem; background:#fff; box-shadow:0 .15rem .45rem rgba(38,56,91,.08); }
        .interview-composer-input > label { grid-column:1 / -1; }
        .interview-composer-input > input, .interview-composer-input > textarea { grid-column:1; grid-row:1; min-width:0; width:100%; min-height:3.35rem; padding:.75rem .25rem; border:0; box-shadow:none; background:transparent; }
        .interview-composer-input > input:focus, .interview-composer-input > textarea:focus { outline:0; box-shadow:none; }
        .interview-composer .chat-send-button { grid-column:2; grid-row:1; width:auto; min-width:8rem; flex:0 0 auto; min-height:3.9rem; padding:.25rem .95rem; border-radius:.8rem; font-size:1.15rem; line-height:1; white-space:nowrap; word-break:normal; overflow-wrap:normal; color:#fff; background:#5b16c8; border-color:#5b16c8; }
        .interview-quick-actions, .m03-quick-actions { grid-column:1 / -1; grid-row:2; display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
        .m04-quick-actions { grid-column:1 / -1; grid-row:2; display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
        .interview-quick-actions button, .m03-quick-actions button { min-height:2.75rem; white-space:nowrap; word-break:normal; overflow-wrap:normal; }
        .m04-quick-actions button { min-height:2.75rem; white-space:nowrap; word-break:normal; overflow-wrap:normal; }
        .m03-composer { display:grid; }
        .m03-input-wrap { min-width:0; }
        .m03-input-wrap .interview-text { min-width:0; width:100%; }
        #interview-chat .btn-primary:hover, #interview-chat .btn-primary:focus-visible, #interview-chat .btn-primary:active { color:#fff; background:#4c1499; border-color:#4c1499; }
        #interview-chat .btn-outline-primary:hover, #interview-chat .btn-outline-primary:focus-visible, #interview-chat .btn-outline-primary:active { color:#fff; background:#5b21b6; border-color:#5b21b6; }
        #interview-chat .btn-outline-secondary:hover, #interview-chat .btn-outline-secondary:focus-visible, #interview-chat .btn-outline-secondary:active { color:#24134f; background:#eee6ff; border-color:#8b6bc9; }
        #interview-chat button:disabled { color:#6f6f78; background:#ececf0; border-color:#d2d2d8; opacity:.85; }
        #interview-chat button:focus-visible { outline:3px solid #f3c969; outline-offset:2px; }
        .patient-interview-page .btn-primary:hover, .patient-interview-page .btn-primary:focus-visible, .patient-interview-page .btn-primary:active,
        .patient-interview-page .btn-outline-primary:hover, .patient-interview-page .btn-outline-primary:focus-visible, .patient-interview-page .btn-outline-primary:active { color:#fff; }
        .patient-interview-page .btn-primary:hover, .patient-interview-page .btn-primary:focus-visible, .patient-interview-page .btn-primary:active { background:#4c1499; border-color:#4c1499; }
        .patient-interview-page .btn-outline-primary:hover, .patient-interview-page .btn-outline-primary:focus-visible, .patient-interview-page .btn-outline-primary:active { background:#5b21b6; border-color:#5b21b6; }
        .patient-interview-page .btn-primary svg, .patient-interview-page .btn-outline-primary svg { color:currentColor; stroke:currentColor; }
        .patient-interview-page .btn:focus-visible { outline:3px solid #f3c969; outline-offset:2px; }
        .patient-interview-page .btn-primary:disabled, .patient-interview-page .btn-outline-primary:disabled { color:#6f6f78; background:#ececf0; border-color:#d2d2d8; opacity:.85; }
        .chat-voice-button, .chat-mic-button, .chat-mic-unavailable { min-width:2.75rem; min-height:2.75rem; width:2.75rem; padding:.25rem; display:inline-flex; align-items:center; justify-content:center; }
        .chat-voice-button { width:auto; padding:.55rem .85rem; white-space:nowrap; font-size:.95rem; }
        .chat-mic-button, .chat-mic-unavailable { grid-column:2; grid-row:1; border-radius:50%; color:#5a13bb; background:#f0eaff; border:1px solid #e0d2ff; font-size:1.2rem; }
        .chat-mic-unavailable { cursor:help; opacity:.82; }
        .chat-mic-button.is-listening { color:#fff !important; background:#b42318 !important; border-color:#b42318 !important; }
        .interview-voice-panel { display:flex; align-items:center; gap:.55rem; flex-wrap:wrap; width:100%; margin:0; padding:.75rem .85rem; border:1px solid #dcebed; border-radius:.85rem; background:#fbfefe; }
        .patient-interview-context-panel .interview-voice-panel { display:grid; grid-template-columns:1fr; gap:.65rem; }
        .interview-voice-panel > strong { color:#27154e; }
        .interview-voice-status { color:#587080; font-size:.88rem; }
        .interview-voice-panel button, .interview-voice-panel select { min-height:2.5rem; }
        .interview-voice-panel select { width:auto; min-width:6.5rem; padding:.35rem .55rem; border:1px solid #bdc7dc; border-radius:.55rem; background:#fff; color:#173042; }
        .interview-voice-panel [hidden] { display:none !important; }
        .interview-voice-panel .voice-speed-control { display:inline-flex; align-items:center; justify-content:space-between; gap:.4rem; margin-left:auto; color:#52627a; font-size:.95rem; }
        .interview-voice-panel[data-voice-state="VOICE_UNAVAILABLE"] { color:#7a1e18; background:#fff4f2; border-color:#e4aaa5; }
        .interview-voice-panel[data-voice-state="SPEAKING"] { border-color:#acdcca; background:#eaf8f2; }
        .interview-voice-panel[data-voice-state="PAUSED"], .interview-voice-panel[data-voice-state="MUTED"] { border-color:#ddccfa; background:#f4eafd; }
        .interview-progress-layout { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
        .interview-progress-context { display:flex; align-items:center; justify-content:flex-end; gap:.5rem; flex-wrap:wrap; }
        .interview-module-badge, .interview-state-badge { display:inline-flex; align-items:center; min-height:2rem; padding:.35rem .7rem; border-radius:999px; font-size:.84rem; font-weight:700; line-height:1.2; }
        .interview-module-badge { color:#4b1372; background:#f0eaff; border:1px solid #ddccfa; }
        .interview-state-badge { color:#24465a; background:#eef5f7; border:1px solid #c9e0e4; }
        .interview-state-badge[data-state="INTERPRETING"], .interview-state-badge[data-state="SAVING"] { color:#6b3f00; background:#fff4d6; border-color:#f1d28a; }
        .interview-state-badge[data-state="CONFIRMATION_REQUIRED"], .interview-state-badge[data-state="CLARIFICATION_REQUIRED"] { color:#5b1978; background:#f4eafd; border-color:#dcc5f2; }
        .interview-state-badge[data-state="SAVE_FAILED"], .interview-state-badge[data-state="SESSION_EXPIRED"], .interview-state-badge[data-state="MODULE_BLOCKED"] { color:#7a1e18; background:#fff0ef; border-color:#e4aaa5; }
        .interview-state-badge[data-state="RESPONSE_SAVED"], .interview-state-badge[data-state="MODULE_COMPLETED"] { color:#155d4b; background:#eaf8f2; border-color:#acdcca; }
        .patient-interview-chat { min-width:0; }
        .patient-interview-chat > h2 { margin-bottom:0; }
        .patient-interview-status { color:#173042; }
        .patient-interview-status[hidden] { display:none !important; }
        .patient-interview-status:not(:empty) { display:block; min-height:1.5rem; margin-top:.25rem; padding:.55rem .75rem; border-left:.25rem solid #1b6b73; border-radius:.65rem; background:#f4fafb; }
        .patient-interview-status:not(:empty)[role="alert"] { color:#7a1e18; background:#fff4f2; border-left-color:#b42318; }
        .interview-module-card, .patient-interview-question { min-width:0; }
        .interview-composer-input { min-width:0; }
        .interview-composer-input > input, .interview-composer-input > textarea, .interview-proposal-fields input, .patient-interview-answer input { max-width:100%; }
        .interview-zone-label { margin:0 0 .55rem; color:#587080; font-size:.8rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
        .chat-help-button { min-height:2.75rem; }
        @media (max-width:1199.98px) { .patient-interview-shell { max-width:1100px; } }
        @media (max-width:1024px) { .patient-interview-shell { max-width:920px; } .patient-interview-header, .patient-interview-conversation-card > .card-body { padding:1rem; } .patient-interview-header { grid-template-columns:1fr; gap:.8rem; } .patient-interview-header-copy .patient-interview-muted { overflow:visible; white-space:normal; } .patient-interview-header-context, .patient-interview-header-actions { justify-content:flex-start; } }
        @media (max-width:900px) { .patient-interview-conversation-layout { grid-template-columns:1fr; } }
        @media (max-width:767.98px) { .patient-interview-shell { padding-inline:.25rem; } .patient-interview-header { align-items:stretch; } .patient-interview-header-side { width:100%; } .patient-interview-header-actions { flex-wrap:wrap; } .patient-interview-header-actions > * { flex:1 1 auto; } .interview-progress-context { justify-content:flex-start; } .patient-interview-message { max-width:100%; } }
        @media (max-width:600px) { .patient-interview-header-actions { display:grid !important; grid-template-columns:1fr; } .patient-interview-header-actions > * { width:100%; } }
        @media (max-width:430px) { .patient-interview-main { padding-inline:.5rem !important; } .patient-interview-header, .patient-interview-conversation-card > .card-body { padding:.85rem; } .interview-progress-layout { gap:.65rem; } .interview-progress-context { width:100%; justify-content:space-between; } .interview-module-badge, .interview-state-badge { font-size:.78rem; } .interview-message-header { align-items:flex-start; } .interview-quick-actions button, .m03-quick-actions button { flex:1 1 auto; } .interview-voice-panel { align-items:stretch; } .interview-voice-panel > * { flex:1 1 auto; } .interview-voice-panel .voice-speed-control { width:100%; margin-left:0; justify-content:space-between; } .interview-voice-panel select { flex:0 1 9rem; } .interview-composer-footer { align-items:flex-start; } }
        @media (max-width:375px) { .patient-interview-main { padding-inline:.35rem !important; } .interview-composer { gap:.45rem; } .interview-composer-input { padding-left:.55rem; } .interview-composer .chat-send-button { width:2.75rem; min-width:2.75rem; padding-inline:.3rem; } .chat-send-label { display:none; } .chat-mic-button, .chat-mic-unavailable { width:2.55rem; min-width:2.55rem; } .interview-quick-actions, .m03-quick-actions { gap:.35rem; } .interview-quick-actions button, .m03-quick-actions button { padding-inline:.55rem; font-size:.88rem; } }
        @media (prefers-reduced-motion:reduce) { *, *::before, *::after { scroll-behavior:auto !important; animation-duration:.01ms !important; animation-iteration-count:1 !important; transition-duration:.01ms !important; } }

        /* Chat-first visual system: presentation-only overrides; functional hooks remain unchanged. */
        .patient-interview-page {
            --interview-ink:#17133d;
            --interview-muted:#5d6b86;
            --interview-purple:#5b21b6;
            --interview-purple-soft:#f4efff;
            --interview-border:#e3e7f2;
            background:#f7f8fc;
        }
        .patient-interview-main {
            background:linear-gradient(180deg,#fbfbff 0%,#f4f5fb 100%);
        }
        .patient-interview-shell { max-width:1440px; padding-inline:.25rem; }
        .patient-interview-header {
            grid-template-columns:minmax(16rem,.9fr) minmax(32rem,1.35fr);
            gap:1.15rem;
            padding:.8rem 1.1rem;
            border-color:var(--interview-border);
            border-radius:1rem;
            box-shadow:0 .35rem 1.1rem rgba(29,35,70,.06);
        }
        .patient-interview-header-copy .dashboard-eyebrow { color:var(--interview-purple); font-size:.68rem; font-weight:750; }
        .patient-interview-header-copy h1 { color:var(--interview-ink); font-size:clamp(1.55rem,2vw,2rem); letter-spacing:-.025em; }
        .patient-interview-header-copy .patient-interview-muted { color:var(--interview-muted); }
        .patient-interview-header-side {
            grid-template-columns:auto minmax(11rem,1fr) auto;
            gap:.55rem;
            align-items:center;
        }
        .patient-interview-header-context { justify-content:flex-start; }
        .patient-interview-header-progress { padding:.45rem .65rem; border-color:var(--interview-border); background:#fff; }
        .patient-interview-header-progress .interview-progress-layout { gap:.5rem; }
        .patient-interview-header-progress .interview-progress-layout strong,
        .patient-interview-header-progress #interview-progress { color:var(--interview-ink); font-size:.78rem; }
        .patient-interview-header-actions { justify-content:flex-end; gap:.35rem; }
        .patient-interview-header-actions > * { min-height:2.15rem; padding:.3rem .55rem; font-size:.8rem; }
        .patient-interview-header-actions .btn-outline-primary,
        .patient-interview-header-actions .btn-outline-secondary { border-color:#b6abd3; color:var(--interview-purple); background:#fff; }
        .patient-interview-conversation-layout {
            grid-template-columns:minmax(0,3.25fr) minmax(15rem,1fr);
            gap:1rem;
            align-items:start;
        }
        .patient-interview-conversation-card { border-color:var(--interview-border); border-radius:1.05rem; box-shadow:0 .35rem 1.1rem rgba(29,35,70,.055); }
        .patient-interview-conversation-card > .card-body { padding:1.15rem; }
        .patient-interview-chat { gap:.8rem; }
        .patient-interview-chat > h2 { margin-bottom:.1rem; color:var(--interview-ink); font-size:1.35rem; letter-spacing:-.015em; }
        .interview-zone-label { margin-bottom:.25rem; color:var(--interview-purple); font-size:.7rem; letter-spacing:.08em; }
        .patient-interview-message { box-shadow:none; border:1px solid var(--interview-border); border-radius:.9rem; }
        .patient-interview-message.assistant { background:#f7f8fe; color:#33415d; }
        .patient-interview-message.patient { background:#26747a; }
        .patient-interview-message[data-interview-zone="assistant"] { max-width:78%; padding:.75rem .95rem; background:#f4f5fc; }
        .patient-interview-message[data-interview-zone="assistant"] .interview-message-header::before { display:none; }
        .patient-interview-message[data-interview-zone="assistant"] .interview-question-text { margin:.2rem 0 0; font-size:.95rem; line-height:1.42; }
        .patient-interview-question:not([hidden]):not(.chat-stage-hidden) {
            padding:.8rem;
            border:1px solid #dcd3f4;
            border-left:.3rem solid var(--interview-purple);
            border-radius:1rem;
            background:#fff;
            box-shadow:0 .4rem 1.2rem rgba(91,33,182,.08);
        }
        .patient-interview-question > .patient-interview-message.assistant {
            padding:.85rem 1rem;
            border:0;
            border-radius:.75rem;
            background:var(--interview-purple-soft);
        }
        .patient-interview-question > .patient-interview-message.assistant .interview-message-header::before { flex-basis:2.25rem; width:2.25rem; height:2.25rem; font-size:1.05rem; }
        .patient-interview-question > .patient-interview-message.assistant .interview-question-text { color:var(--interview-ink); font-size:clamp(1.25rem,1.9vw,1.65rem); line-height:1.24; }
        .interview-composer-shell { margin-top:.65rem; padding:.75rem; border-color:var(--interview-border); border-radius:.8rem; box-shadow:none; }
        .interview-composer-input { min-height:3.55rem; border-color:#c8cfe0; border-radius:.7rem; box-shadow:none; }
        .interview-composer-input > input, .interview-composer-input > textarea { min-height:3rem; font-size:1rem; }
        .interview-composer .chat-send-button { min-height:3.55rem; min-width:7rem; border-radius:.7rem; font-size:1rem; }
        .chat-mic-button, .chat-mic-unavailable { min-width:2.55rem; width:2.55rem; min-height:2.55rem; height:2.55rem; font-size:1.05rem; }
        .interview-quick-actions, .m03-quick-actions { gap:.4rem; }
        .m04-quick-actions { gap:.4rem; }
        .interview-quick-actions button, .m03-quick-actions button { min-height:2.45rem; padding:.3rem .7rem; font-size:.9rem; }
        .m04-quick-actions button { min-height:2.45rem; padding:.3rem .7rem; font-size:.9rem; }
        .interview-composer-help { margin-top:.55rem; color:var(--interview-muted); font-size:.84rem; }
        .interview-answer-count { color:var(--interview-muted); font-size:.78rem; }
        .patient-interview-context-panel { gap:.7rem; }
        .patient-interview-context-card { padding:.85rem; border-color:var(--interview-border); border-radius:.85rem; box-shadow:0 .25rem .8rem rgba(29,35,70,.04); }
        .patient-interview-context-card h3 { margin-bottom:.55rem; color:var(--interview-ink); font-size:.95rem; }
        .patient-interview-context-current { padding:.65rem; background:#f5f1ff; }
        .patient-interview-context-current p, .patient-interview-tips { font-size:.88rem; }
        .patient-interview-tips { gap:.4rem; }
        .patient-interview-context-panel .interview-voice-panel { gap:.45rem; padding:.65rem; border-color:#dce9e5; background:#f8fcfa; }
        .patient-interview-context-panel .interview-voice-panel > strong { font-size:.9rem; }
        .patient-interview-context-panel .interview-voice-status { font-size:.8rem; }
        .patient-interview-context-panel .interview-voice-panel button,
        .patient-interview-context-panel .interview-voice-panel select { min-height:2.2rem; font-size:.85rem; }
        .patient-interview-context-panel .interview-voice-panel .voice-speed-control { margin-left:0; font-size:.82rem; }
        .patient-interview-context-panel .interview-voice-panel select { min-width:5.8rem; }
        body.patient-interview-page #sidebar.is-compact,
        body.patient-interview-page #sidebar.sidebar-compact { transition:width .2s ease, transform .2s ease; }
        @media (max-width:1199.98px) {
            .patient-interview-header { grid-template-columns:minmax(15rem,.85fr) minmax(30rem,1.15fr); }
            .patient-interview-header-side { grid-template-columns:auto minmax(10rem,1fr); }
            .patient-interview-header-actions { grid-column:1 / -1; justify-content:flex-start; }
        }
        @media (max-width:1024px) {
            .patient-interview-shell { max-width:920px; }
            .patient-interview-header { grid-template-columns:1fr; gap:.7rem; }
            .patient-interview-header-side { grid-template-columns:auto minmax(12rem,1fr) auto; }
            .patient-interview-header-actions { grid-column:auto; justify-content:flex-end; }
        }
        @media (max-width:900px) {
            .patient-interview-conversation-layout { grid-template-columns:1fr; }
            .patient-interview-context-panel { grid-template-columns:repeat(3,minmax(0,1fr)); align-items:start; }
        }
        @media (max-width:767.98px) {
            .patient-interview-shell { padding-inline:.15rem; }
            .patient-interview-header-side { grid-template-columns:1fr; }
            .patient-interview-header-context, .patient-interview-header-actions { justify-content:flex-start; }
            .patient-interview-header-actions { display:flex !important; }
            .patient-interview-header-actions > * { flex:1 1 auto; }
            .patient-interview-context-panel { grid-template-columns:1fr; }
            .patient-interview-message[data-interview-zone="assistant"] { max-width:100%; }
            .patient-interview-question:not([hidden]):not(.chat-stage-hidden) { padding:.55rem; }
        }
        @media (max-width:430px) {
            .patient-interview-header, .patient-interview-conversation-card > .card-body { padding:.75rem; }
            .patient-interview-header-actions { display:grid !important; grid-template-columns:repeat(2,minmax(0,1fr)); }
            .patient-interview-header-actions > * { width:100%; }
            .patient-interview-header-actions > :last-child { grid-column:1 / -1; }
            .interview-composer { grid-template-columns:minmax(0,1fr) auto; }
            .interview-composer .chat-send-button { min-width:6rem; }
        }
        @media (max-width:375px) {
            .patient-interview-header-actions { grid-template-columns:1fr; }
            .patient-interview-header-actions > :last-child { grid-column:auto; }
            .interview-composer .chat-send-button { min-width:2.75rem; }
        }

        /* Reference-parity correction: keep the existing DOM and hooks, but restore the open header and conversation hierarchy. */
        .patient-interview-main { padding-top:1rem !important; }
        .patient-interview-shell { max-width:1395px; padding-inline:0; }
        .patient-interview-header {
            grid-template-columns:minmax(0,1fr) minmax(31rem,.96fr);
            min-height:6.9rem;
            margin-bottom:.55rem !important;
            padding:.35rem .2rem .75rem;
            border:0;
            border-radius:0;
            background:transparent;
            box-shadow:none;
        }
        .patient-interview-header-copy { padding-top:.2rem; }
        .patient-interview-header-copy .dashboard-eyebrow { margin-bottom:.35rem; font-size:.73rem; }
        .patient-interview-header-copy h1 { margin-bottom:.35rem; font-size:clamp(2rem,2.55vw,2.85rem); font-weight:700; letter-spacing:-.035em; }
        .patient-interview-header-copy .patient-interview-muted { overflow:visible; white-space:normal; font-size:1.02rem; line-height:1.35; }
        .patient-interview-header-side {
            grid-template-columns:minmax(0,1fr) auto;
            grid-template-areas:"progress context" "actions actions";
            align-content:center;
            gap:.55rem .75rem;
        }
        .patient-interview-header-context { grid-area:context; align-self:start; justify-content:flex-end; }
        .patient-interview-header-progress { grid-area:progress; align-self:start; min-width:0; padding:.45rem .65rem; border-color:#e8e5f2; background:rgba(255,255,255,.6); }
        .patient-interview-header-actions { grid-area:actions; justify-content:flex-end; }
        .patient-interview-header-actions > * { min-height:2.2rem; padding:.3rem .7rem; font-size:.84rem; }
        .interview-module-badge, .interview-state-badge { min-height:2.1rem; padding:.35rem .8rem; font-size:.86rem; }
        .patient-interview-conversation-layout { grid-template-columns:minmax(0,3.2fr) minmax(17rem,1fr); gap:1.35rem; }
        .patient-interview-conversation-card { border-color:#eceaf2; border-radius:1rem; box-shadow:0 .3rem 1rem rgba(35,30,77,.045); }
        .patient-interview-conversation-card > .card-body { padding:1.65rem 2rem; }
        .patient-interview-chat { gap:1.15rem; }
        .patient-interview-chat > .interview-zone-label,
        .patient-interview-chat > h2 { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
        .patient-interview-message.assistant {
            position:relative;
            width:min(100%,86%);
            max-width:86%;
            margin-left:3.7rem;
            padding:1rem 1.2rem;
            border:0;
            border-radius:.95rem;
            background:#f3f1ff;
            box-shadow:none;
        }
        .patient-interview-message.assistant .interview-message-header { position:relative; min-height:2.45rem; justify-content:flex-start; }
        .patient-interview-message.assistant .interview-message-header::before {
            position:absolute;
            left:-3.65rem;
            top:0;
            flex-basis:2.65rem;
            width:2.65rem;
            height:2.65rem;
            color:#5b21b6;
            background:#eee9ff;
            font-size:1.2rem;
        }
        .patient-interview-message.assistant .interview-question-text { margin-top:.35rem; color:#536484; font-size:1rem; line-height:1.45; }
        .patient-interview-message[data-interview-zone="assistant"] { width:min(100%,86%); max-width:86%; padding:.95rem 1.2rem; background:#f4f2ff; }
        .patient-interview-message[data-interview-zone="assistant"] .interview-message-header::before { display:inline-flex; }
        .patient-interview-question:not([hidden]):not(.chat-stage-hidden) {
            padding:0;
            border:0;
            border-radius:1rem;
            background:transparent;
            box-shadow:none;
        }
        .patient-interview-question > .patient-interview-message.assistant {
            width:100%;
            max-width:100%;
            margin-left:0;
            padding:1.05rem 1.2rem 1.15rem 4.45rem;
            border:1px solid #e4dcf7;
            border-radius:1rem 1rem .3rem .3rem;
            background:linear-gradient(135deg,#f3efff 0%,#f0f7f7 100%);
        }
        .patient-interview-question > .patient-interview-message.assistant .interview-message-header::before { left:-3.45rem; }
        .patient-interview-question > .patient-interview-message.assistant .interview-message-header strong { color:#1b1648; font-size:1rem; }
        .patient-interview-question > .patient-interview-message.assistant .interview-question-text { margin-top:.45rem; color:#191746; font-size:clamp(1.55rem,2.15vw,2rem); line-height:1.18; }
        .interview-composer-shell { margin-top:0; padding:1rem 1.2rem 1.05rem; border:1px solid #e4dcf7; border-top:0; border-radius:.3rem  .3rem 1rem 1rem; background:#fff; box-shadow:0 .25rem .8rem rgba(35,30,77,.04); }
        .interview-composer-input { min-height:3.75rem; border-color:#c8c7dd; border-radius:.7rem; background:#fff; }
        .interview-composer-input > input, .interview-composer-input > textarea { min-height:3.2rem; padding-inline:.35rem; color:#27365a; font-size:1rem; }
        .interview-composer .chat-send-button { min-height:3.75rem; min-width:8.2rem; border-radius:.7rem; font-size:1.05rem; }
        .interview-composer-help { margin-top:.65rem; color:#687795; font-size:.86rem; }
        .interview-answer-count { color:#687795; font-size:.8rem; }
        .patient-interview-context-panel { gap:.85rem; }
        .patient-interview-context-card { padding:1rem; border:0; border-radius:1rem; background:rgba(255,255,255,.82); box-shadow:0 .25rem .8rem rgba(35,30,77,.04); }
        .patient-interview-context-card h3 { margin-bottom:.7rem; color:#1b1648; font-size:1rem; }
        .patient-interview-context-current { padding:.8rem; background:#f3efff; }
        .patient-interview-context-current strong { font-size:1.02rem; }
        .patient-interview-context-current p { color:#66718d; font-size:.95rem; }
        .patient-interview-context-panel .interview-voice-panel { gap:.55rem; padding:.8rem; border:1px solid #dfe9e7; border-radius:.8rem; background:#f8fcfb; }
        .patient-interview-context-panel .interview-voice-status { padding:.55rem .7rem; border-radius:.65rem; color:#3c5f61; background:#e7f7ef; font-size:.86rem; }
        .patient-interview-context-panel .interview-voice-panel button { min-height:2.5rem; }
        .patient-interview-context-panel .interview-voice-panel [data-voice-repeat] { color:#fff; background:#5b21b6; border-color:#5b21b6; }
        .patient-interview-context-panel .interview-voice-panel .voice-speed-control { padding-top:.3rem; border-top:1px solid #e5ecea; }
        .patient-interview-tips { color:#66718d; font-size:.9rem; }
        @media (max-width:1199.98px) {
            .patient-interview-header { grid-template-columns:minmax(0,1fr) minmax(28rem,1fr); }
        }
        @media (max-width:1024px) {
            .patient-interview-main { padding-top:.65rem !important; }
            .patient-interview-header { grid-template-columns:1fr; min-height:0; padding-inline:0; }
            .patient-interview-header-side { grid-template-columns:minmax(0,1fr) auto; grid-template-areas:"progress context" "actions actions"; }
            .patient-interview-header-actions { justify-content:flex-start; }
        }
        @media (max-width:900px) {
            .patient-interview-conversation-layout { grid-template-columns:1fr; }
            .patient-interview-context-panel { grid-template-columns:repeat(3,minmax(0,1fr)); }
        }
        @media (max-width:767.98px) {
            .patient-interview-header-copy h1 { font-size:clamp(1.8rem,7vw,2.35rem); }
            .patient-interview-header-copy .patient-interview-muted { font-size:.95rem; }
            .patient-interview-header-side { grid-template-columns:1fr; grid-template-areas:"context" "progress" "actions"; }
            .patient-interview-header-context { justify-content:flex-start; }
            .patient-interview-header-actions { display:flex !important; flex-wrap:wrap; }
            .patient-interview-header-actions > * { flex:1 1 auto; }
            .patient-interview-conversation-card > .card-body { padding:1rem; }
            .patient-interview-context-panel { grid-template-columns:1fr; }
            .patient-interview-message.assistant,
            .patient-interview-message[data-interview-zone="assistant"] { width:calc(100% - 2.8rem); max-width:calc(100% - 2.8rem); margin-left:2.8rem; }
            .patient-interview-question > .patient-interview-message.assistant { width:100%; max-width:100%; margin-left:0; padding-left:3.75rem; }
            .patient-interview-question > .patient-interview-message.assistant .interview-question-text { font-size:clamp(1.3rem,5.5vw,1.7rem); }
            .interview-composer-shell { padding-inline:.75rem; }
        }
        @media (max-width:430px) {
            .patient-interview-header { padding-top:.1rem; }
            .patient-interview-header-actions { display:grid !important; grid-template-columns:repeat(2,minmax(0,1fr)); }
            .patient-interview-header-actions > :last-child { grid-column:1 / -1; }
            .patient-interview-message.assistant,
            .patient-interview-message[data-interview-zone="assistant"] { width:calc(100% - 2.25rem); max-width:calc(100% - 2.25rem); margin-left:2.25rem; padding-inline:.85rem; }
            .patient-interview-message.assistant .interview-message-header::before { left:-2.55rem; width:2.15rem; height:2.15rem; }
            .patient-interview-question > .patient-interview-message.assistant { width:100%; max-width:100%; margin-left:0; padding-left:3.2rem; }
            .interview-composer { grid-template-columns:minmax(0,1fr) auto; }
            .interview-composer .chat-send-button { min-width:6.8rem; }
        }
        @media (max-width:375px) {
            .patient-interview-header-actions { grid-template-columns:1fr; }
            .patient-interview-header-actions > :last-child { grid-column:auto; }
            .interview-composer .chat-send-button { min-width:2.75rem; }
        }
    </style>
</head>
<body class="app-shell-body app-shell-patient patient-interview-page">
<div id="app">
    <?=$data['sidebar'];?>
    <div id="main" data-patient="<?=$escape($data['idPaciente']);?>" data-csrf="<?=$escape($data['csrf']);?>" data-native-stt-enabled="<?=!empty($data['nativeSttEnabled']) ? '1' : '0';?>" data-conversation-contract-version="<?=$escape($data['conversationContract']['version'] ?? '');?>">
        <nav class="navbar navbar-header navbar-expand navbar-light portal-topbar" aria-label="Barra de herramientas del portal del paciente">
            <div class="container-fluid patient-interview-topbar-inner"><button type="button" class="patient-interview-sidebar-toggle sidebar-toggler" data-interview-sidebar-toggle aria-controls="sidebar" aria-expanded="true" aria-label="Compactar menú principal"><i data-feather="menu" aria-hidden="true"></i><span class="visually-hidden">Menú</span></button><span class="patient-interview-breadcrumb">Mi historia clínica <span aria-hidden="true">›</span></span><span class="portal-badge"><span class="portal-status-dot" aria-hidden="true"></span>Entrevista conversacional</span></div>
        </nav>
        <main class="main-content container-fluid patient-interview-main">
            <div class="patient-interview-shell">
                <header class="page-title portal-page-title patient-interview-header mb-3" data-interview-zone="header">
                    <div class="patient-interview-header-copy">
                        <p class="dashboard-eyebrow">MI HISTORIA CLÍNICA</p>
                        <h1>Entrevista Conversacional</h1>
                        <p class="patient-interview-muted mb-0">Responde una pregunta a la vez; pulsa Enviar para validar y guardar cuando la respuesta sea válida.</p>
                    </div>
                    <div class="patient-interview-header-side">
                        <div class="patient-interview-header-context" aria-label="Contexto actual de la entrevista">
                            <span class="interview-module-badge" data-interview-current-module>M01</span>
                            <span class="interview-state-badge" data-interview-state-label data-state="INITIALIZING" aria-live="polite" aria-atomic="true">Preparando</span>
                        </div>
                        <div class="patient-interview-header-progress" data-interview-zone="progress">
                            <div class="interview-progress-layout">
                                <strong id="interview-progress-title">Progreso de la entrevista</strong>
                                <div id="interview-progress" class="patient-interview-muted">Etapa 1 de 1</div>
                            </div>
                            <div class="progress mt-1" role="progressbar" aria-label="Avance de la entrevista" aria-valuemin="0" aria-valuemax="1" aria-valuenow="0" aria-valuetext="Preparando la entrevista"><div id="interview-progress-bar" class="progress-bar" style="width:0%"></div></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 patient-interview-header-actions" data-interview-zone="navigation" aria-label="Acciones de la entrevista"><button type="button" class="btn btn-outline-primary" data-chat-history aria-label="Respuestas previas"><i data-feather="clipboard" aria-hidden="true"></i> Respuestas previas</button><button type="button" class="btn btn-outline-secondary" data-interview-pause aria-label="Pausar entrevista"><i data-feather="pause-circle" aria-hidden="true"></i> Pausar</button><button type="button" class="btn btn-outline-primary" data-interview-resume aria-label="Reanudar entrevista" hidden><i data-feather="play-circle" aria-hidden="true"></i> Reanudar</button><a class="btn btn-outline-secondary" href="<?=SERVIDOR;?>historia-clinica" aria-label="Mi historia clínica"><i data-feather="arrow-left" aria-hidden="true"></i> Mi historia clínica</a></div>
                    </div>
                </header>
                <div class="chat-history-panel mb-3" data-chat-history-panel hidden><strong>Respuestas ya registradas</strong><p class="patient-interview-muted mb-0">La consulta no modifica el expediente. Cierra este panel para continuar con la pregunta activa.</p><div data-chat-history-content class="mt-3"></div></div>

                <div class="patient-interview-conversation-layout" data-ux-layout="chat-first">
                <section class="card border-0 rounded-4 shadow-sm mt-0 patient-interview-conversation-card" data-interview-zone="conversation" aria-labelledby="interview-chat-title">
                    <div class="card-body patient-interview-chat" id="interview-chat" aria-describedby="interview-status" aria-busy="false">
                        <p class="interview-zone-label">Conversación y respuesta</p>
                        <h2 id="interview-chat-title" class="h4">Conversación clínica</h2>
                        <div class="patient-interview-message assistant" data-interview-zone="assistant"><div class="interview-message-header"><strong>Asistente:</strong></div><p class="interview-question-text">Responderás una pregunta a la vez. Pulsa Enviar para validar y guardar tu respuesta.</p></div>
                        <?php if (!empty($data['m01']['fields'])): ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M01">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M01</strong></div><p class="interview-question-text">Estos son tus datos de identificación ya registrados. Se muestran como lectura informativa; esta entrega no modifica M01.</p></div>
                            <div class="patient-interview-context-grid">
                                <?php foreach ($data['m01']['fields'] as $field): ?>
                                <div data-m01-field="<?=$escape($field['key']);?>">
                                    <dt><?=$escape($field['label']);?></dt><dd><?=$escape($field['value']);?></dd>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="patient-interview-muted mb-0">M01 consultado. La captura conversacional continúa en M03.</p>
                        </article>
                        <?php endif; ?>
                        <?php
                            $m02Records = $data['m02']['records'] ?? [];
                            $m02Contract = $data['conversationContract']['modules']['M02'] ?? [];
                            $m02PendingIds = is_array($m02Contract['completion']['pending_question_ids'] ?? null)
                                ? array_values($m02Contract['completion']['pending_question_ids'])
                                : [];
                            $m02QuestionIndex = $data['conversationContract']['question_index'] ?? [];
                            $m02ActiveQuestion = null;
                            foreach ($m02PendingIds as $m02PendingId) {
                                $candidate = $m02QuestionIndex[$m02PendingId] ?? null;
                                if (is_array($candidate) && ($candidate['module_id'] ?? '') === 'M02') {
                                    $m02ActiveQuestion = $candidate;
                                    break;
                                }
                            }
                            $m02ActiveDisease = is_array($m02ActiveQuestion) ? (string)($m02ActiveQuestion['question_id'] ?? '') : '';
                            $m02ActiveRecord = null;
                            foreach ($m02Records as $m02Record) {
                                if ($m02ActiveDisease !== '' && (string)($m02Record['disease'] ?? '') === $m02ActiveDisease) {
                                    $m02ActiveRecord = $m02Record;
                                    break;
                                }
                            }
                            $m02ResolverState = (string)($m02Contract['conversation_state']['state'] ?? 'INCONSISTENT');
                        ?>
                        <?php if (!empty($m02Records)): ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M02" data-m02-pending="<?=count($m02PendingIds);?>" data-m02-write-enabled="<?=!empty($data['m02']['write_enabled']) ? '1' : '0';?>">
                            <?php if (is_array($m02ActiveRecord) && is_array($m02ActiveQuestion)): ?>
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · Antecedentes heredofamiliares</strong></div><p class="interview-question-text"><?=$escape($m02ActiveQuestion['visible_text'] ?? '');?></p><p class="patient-interview-muted mb-0"><?=$escape($m02ActiveQuestion['instruction'] ?? 'Responde la pregunta activa.');?></p></div>
                            <div class="patient-interview-context-grid m02-active-question" aria-label="Pregunta M02 activa">
                                <div data-m02-disease="<?=$escape($m02ActiveRecord['disease']);?>" data-m02-version="<?=$escape($m02ActiveRecord['version'] ?? '');?>" data-m02-write-enabled="<?=!empty($m02ActiveRecord['write_enabled']) ? '1' : '0';?>">
                                    <dt class="visually-hidden">Pregunta activa</dt>
                                    <dd class="patient-interview-muted">Pregunta activa · <?=$escape($m02ActiveRecord['disease']);?></dd>
                                    <div class="patient-interview-answer interview-composer m02-composer">
                                        <div class="interview-composer-input">
                                            <label class="visually-hidden" for="m02-input-<?=$escape(md5($m02ActiveRecord['disease']));?>">Respuesta sobre <?=$escape($m02ActiveRecord['disease']);?></label>
                                            <input id="m02-input-<?=$escape(md5($m02ActiveRecord['disease']));?>" class="form-control" data-m02-input type="text" placeholder="Ej. mi madre tiene esta enfermedad">
                                        </div>
                                        <div class="interview-quick-actions" aria-label="Respuesta rápida M02"><button type="button" class="btn btn-outline-primary btn-sm" data-m02-quick data-value="Sí">Sí</button><?php if (!empty($data['m02']['write_enabled'])): ?><button type="button" class="btn btn-primary btn-sm" data-m02-safe-no>No, guardar y continuar</button><?php endif; ?></div>
                                        <button type="button" class="btn btn-outline-primary btn-sm chat-send-button" data-m02-interpret hidden aria-label="Interpretar respuesta" title="Interpretar respuesta">➤</button>
                                    </div>
                                    <small class="d-block patient-interview-muted mt-1" data-m02-result hidden></small>
                                    <?php if (!empty($data['m02']['write_enabled'])): ?><button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-m02-defer>Responder después</button><?php endif; ?>
                                </div>
                            </div>
                            <p class="patient-interview-muted mb-0 mt-3">La respuesta se valida y guarda antes de presentar la siguiente pregunta.</p>
                            <?php elseif ($m02ResolverState === 'READY_TO_FINALIZE'): ?>
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · Antecedentes heredofamiliares</strong></div><p class="interview-question-text">Tus antecedentes familiares están registrados. ¿Deseas finalizar esta sección y continuar?</p></div>
                            <button type="button" class="btn btn-primary" data-m02-resolution-action aria-label="Finalizar antecedentes familiares y continuar">Finalizar antecedentes familiares y continuar</button>
                            <?php elseif ($m02ResolverState === 'AWAITING_PROFESSIONAL_REVIEW'): ?>
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · Antecedentes heredofamiliares</strong></div><p class="interview-question-text">Esta sección requiere revisión profesional antes de continuar.</p></div>
                            <?php else: ?>
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · Antecedentes heredofamiliares</strong></div><p class="interview-question-text">No se pudo resolver una única acción conversacional para esta sección.</p></div>
                            <?php endif; ?>
                        </article>
                        <?php endif; ?>
                        <?php foreach ($questionList as $index => $question): ?>
                        <?php $m03Eligible = ($question['eligible'] ?? true) === true; $m03Parent = (int)($question['parentQuestionId'] ?? 0); $m03DirectNumeric = ($question['tipo'] ?? '') === 'input' && preg_match('/\b(?:cu[aá]ntos|cu[aá]nta|cu[aá]nto|qu[eé] edad|hace cu[aá]nto|durante cu[aá]nto)\b/iu', (string)($question['pregunta'] ?? '')) === 1; ?>
                        <?php $m03SemanticState = strtoupper(trim((string)($question['semanticState'] ?? $question['estadoRespuesta'] ?? ''))); $m03HasSemanticAnswer = in_array($m03SemanticState, ['UNKNOWN', 'NOT_REMEMBERED'], true); $hasSavedAnswer = trim((string)($question['respuesta'] ?? '')) !== '' || $m03HasSemanticAnswer; $m03SemanticLabel = $m03SemanticState === 'UNKNOWN' ? 'Dato desconocido declarado por el paciente' : ($m03SemanticState === 'NOT_REMEMBERED' ? 'Dato no recordado declarado por el paciente' : ''); ?>
                        <article class="patient-interview-question patient-interview-active-question interview-module-card" data-question-index="<?=$index;?>" data-question-id="<?=$escape($question['idPregunta']);?>" data-theme-id="<?=$escape($question['idTema']);?>" data-theme-name="<?=$escape($question['tema'] ?? 'M03');?>" data-parent-id="<?=$escape($m03Parent);?>" data-eligible="<?=$m03Eligible ? '1' : '0';?>" data-type="<?=$escape($question['tipo']);?>" data-response-id="<?=$escape($question['idRespuesta'] ?? '');?>" data-saved-value="<?=$escape($question['respuesta'] ?? '');?>" data-semantic-state="<?=$escape($m03SemanticState);?>" data-m03-version="<?=$escape($data['m03']['version'] ?? '');?>" data-m03-write-enabled="<?=!empty($data['m03']['write_enabled']) ? '1' : '0';?>" data-m03-direct-capable="<?=$m03DirectNumeric ? '1' : '0';?>"<?=$index === $firstPending ? '' : ' hidden';?>>
                            <button type="button" class="interview-previous-button" data-m03-previous hidden aria-label="Volver a la pregunta anterior"><span aria-hidden="true">←</span> Pregunta anterior</button>
                            <div class="patient-interview-message assistant">
                                <div class="interview-message-header"><strong>Asistente · <?=$escape($question['tema'] ?? 'M03');?>:</strong></div>
                                <p class="interview-question-text"><?=$escape($question['pregunta']);?></p>
                            </div>
                            <div class="interview-composer-shell" data-interview-zone="response"<?=$hasSavedAnswer && $index < $firstPending ? ' hidden' : ''; ?>>
                                <div class="patient-interview-answer interview-composer m03-composer">
                                    <div class="interview-composer-input m03-input-wrap">
                                        <label class="visually-hidden" for="interview-answer-<?=$index;?>">Respuesta</label>
                                        <input id="interview-answer-<?=$index;?>" class="form-control interview-text" type="text" value="<?=$escape($question['respuesta'] ?? '');?>" placeholder="Escribe tu respuesta…" autocomplete="off" maxlength="2000">
                                    </div>
                                    <?php if (($question['tipo'] ?? '') === 'select'): ?>
                                        <div class="m03-quick-actions" aria-label="Respuestas rápidas">
                                            <button type="button" class="btn btn-outline-primary" data-m03-quick-yes>Sí</button>
                                            <?php if (!empty($data['m03']['write_enabled']) && $m03Eligible): ?><button type="button" class="btn btn-primary" data-m03-safe-no>No, guardar y seguir</button><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-primary chat-send-button interview-submit" aria-label="Interpretar respuesta" title="Interpretar respuesta">➤ <span class="chat-send-label">Enviar</span></button>
                                </div>
                                <div class="interview-composer-footer"><p class="interview-composer-help"><span aria-hidden="true">ⓘ</span> Escribe o dicta tu respuesta y pulsa Enviar para validarla y guardarla.</p><span class="interview-answer-count" data-answer-count>0/2000</span></div>
                            </div>
                            <div class="patient-interview-message patient" data-confirmed-message<?=$hasSavedAnswer ? '' : ' hidden';?>><?=$hasSavedAnswer ? $escape('Respuesta confirmada: ' . ($m03SemanticLabel !== '' ? $m03SemanticLabel : $question['respuesta'])) : '';?></div>
                        </article>
                        <?php endforeach; ?>
                        <?php if (!empty($data['m04'])):
                            $m04Questions = $data['conversationContract']['modules']['M04']['questions'] ?? [];
                            $m04QuestionIndex = $data['conversationContract']['question_index'] ?? [];
                            $m04Records = array_values(array_filter($data['m04']['records'] ?? [], static fn(array $record): bool => trim((string)($record['cirugia'] ?? '')) !== ''));
                            $m04HasRecords = !empty($m04Records);
                            $m04NegativeDeclaration = is_array($data['m04']['negative_declaration'] ?? null) ? $data['m04']['negative_declaration'] : null;
                            $m04NegativeConfirmed = ($m04NegativeDeclaration['estado'] ?? null) === 'NEGATIVE_DECLARED' && !$m04HasRecords;
                            $m04Finalized = !empty($data['m04']['finalized']);
                            $m04Question = ($m04HasRecords && !$m04Finalized && is_array($m04QuestionIndex['M04:record:new'] ?? null))
                                ? $m04QuestionIndex['M04:record:new']
                                : ($m04Questions[0] ?? []);
                            $m04StableId = (string)($m04Question['stable_id'] ?? 'M04:record:new');
                            $m04ValidDate = static function ($value): ?string {
                                $value = trim((string)$value);
                                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return null;
                                [$year, $month, $day] = array_map('intval', explode('-', $value));
                                if ($year < 1 || !checkdate($month, $day, $year)) return null;
                                return sprintf('%04d-%02d-%02d', $year, $month, $day);
                            };
                        ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M04" data-m04-record="<?=$m04HasRecords ? (int)($m04Records[0]['id'] ?? 0) : 0;?>" data-m04-stable-id="<?=$escape($m04StableId);?>" data-m04-version="<?=$escape($data['m04']['version'] ?? '');?>" data-m04-write-enabled="<?=!empty($data['m04']['write_enabled']) ? '1' : '0';?>" data-m04-negative-confirmed="<?=$m04NegativeConfirmed ? '1' : '0';?>" aria-labelledby="m04-question-title">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · Antecedentes quirúrgicos</strong></div><h3 id="m04-question-title" class="interview-question-text" data-m04-question-title><?=$escape($m04Question['visible_text'] ?? '¿Te realizaron alguna cirugía?');?></h3><p id="m04-question-instruction" class="patient-interview-muted mb-0" data-m04-question-instruction><?=$escape($m04Question['instruction'] ?? 'Describe una cirugía o pulsa «No, continuar».');?></p></div>
                            <?php if ($m04HasRecords): ?>
                            <h4 class="patient-interview-context-heading">Cirugías registradas — <?=count($m04Records);?> <?=count($m04Records) === 1 ? 'registro' : 'registros';?></h4>
                            <div class="patient-interview-context-grid" aria-label="Cirugías registradas">
                                <?php foreach ($m04Records as $record): ?>
                                <?php $m04Date = $m04ValidDate($record['fecha'] ?? null); $m04Observations = trim((string)($record['observaciones'] ?? '')); ?>
                                <div><dt>Registro quirúrgico</dt><dd><?= $escape($record['cirugia'] ?? ''); ?></dd><?php if ($m04Date !== null): ?><small class="patient-interview-muted">Fecha exacta: <?=$escape($m04Date);?></small><?php endif; ?><?php if ($m04Observations !== ''): ?><small class="patient-interview-muted d-block"><?=$escape($m04Observations);?></small><?php endif; ?></div>
                                <?php endforeach; ?>
                            </div>
                            <?php elseif ($m04NegativeConfirmed): ?><p class="patient-interview-muted">Declaración confirmada: no se registraron cirugías.</p>
                            <?php else: ?><p class="patient-interview-muted">Datos quirúrgicos pendientes de respuesta.</p><?php endif; ?>
                            <?php if (!$m04Finalized && !empty($data['m04']['write_enabled'])): ?><div class="patient-interview-answer interview-composer">
                                <div class="interview-composer-input"><label class="visually-hidden" for="m04-input">Respuesta sobre antecedentes quirúrgicos</label>
                                <input id="m04-input" class="form-control" data-m04-input type="text" placeholder="<?=$m04NegativeConfirmed ? 'Ej. corrige la respuesta: me operaron de la vesícula' : 'Ej. me operaron de la vesícula hace cinco años';?>"></div>
                                <button type="button" class="btn btn-outline-primary chat-send-button" data-m04-interpret aria-label="Interpretar respuesta M04" title="Interpretar respuesta M04">➤</button>
                                <div class="m04-quick-actions" role="group" aria-label="Continuar con antecedentes quirúrgicos"><button type="button" class="btn btn-outline-primary" data-m04-finish data-m04-quick-response data-intent="FINISH_ADDITIONAL_SURGERIES">No, continuar</button></div>
                            </div><?php elseif ($m04Finalized): ?><p class="patient-interview-muted" role="status">M04 finalizado. La edición ordinaria está bloqueada.</p><?php endif; ?>
                            <small class="d-block patient-interview-muted mt-1" data-m04-result role="status" aria-live="polite" hidden></small>
                            <p class="patient-interview-muted mb-0">La respuesta válida se guarda después de la validación del servidor.</p>
                        </article>
                        <?php endif; ?>
                        <?php if (!empty($data['m05'])): $m05Pending = $data['m05']['pending_question'] ?? null; ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M05" data-m05-version="<?=$escape($data['m05']['version'] ?? '');?>" data-m05-write-enabled="<?=!empty($data['m05']['write_enabled']) ? '1' : '0';?>" data-m05-pending="<?=$m05Pending ? '1' : '0';?>" data-m05-pending-question="<?=$m05Pending ? (int)$m05Pending['question_id'] : 0;?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M05</strong></div><p class="interview-question-text">Continuaremos con antecedentes personales patológicos. Pulsa Enviar para validar y guardar la respuesta.</p></div>
                            <?php $m05Existing = array_filter($data['m05']['questions'] ?? [], static fn(array $row): bool => trim((string)($row['respuesta'] ?? '')) !== ''); ?>
                            <?php if ($m05Existing): ?><div class="patient-interview-context-grid" aria-label="Respuestas M05 existentes">
                                <?php foreach ($m05Existing as $row): ?><div><dt><?=$escape($row['tema'] ?? 'M05');?></dt><dd><?=$escape($row['respuesta']);?></dd></div><?php endforeach; ?>
                            </div><?php endif; ?>
                            <?php if ($m05Pending): ?>
                            <p class="patient-interview-muted" data-m05-prompt><strong data-m05-theme-label><?=$escape($m05Pending['tema'] ?? 'Pregunta M05');?></strong><br><span data-m05-question-label><?=$escape($m05Pending['pregunta'] ?? '');?></span></p>
                            <div class="patient-interview-answer interview-composer">
                                <div class="interview-composer-input"><label class="visually-hidden" for="m05-input">Respuesta M05</label>
                                <input id="m05-input" class="form-control" data-m05-input type="text" placeholder="Escribe tu respuesta…"></div>
                                <button type="button" class="btn btn-outline-primary chat-send-button" data-m05-interpret aria-label="Interpretar respuesta M05" title="Interpretar respuesta M05">➤</button>
                            </div>
                            <small class="d-block patient-interview-muted mt-1" data-m05-result role="status" aria-live="polite" hidden></small>
                            <?php else: ?><p class="patient-interview-muted">No hay preguntas M05 pendientes de respuesta.</p><?php endif; ?>
                        </article>
                        <?php endif; ?>
                        <?php if (!empty($data['m05']['diseases'])): $m05Disease = null; foreach ($data['m05']['diseases'] as $disease) { if (trim((string)($disease['respuesta'] ?? '')) === '') { $m05Disease = $disease; break; } } ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M05-V2" data-m05-version="<?=$escape($data['m05']['version'] ?? '');?>" data-m05-v2-pending="<?=$m05Disease ? '1' : '0';?>" data-m05-v2-disease="<?=$m05Disease ? (int)$m05Disease['disease_id'] : 0;?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M05 enfermedades</strong></div><p class="interview-question-text">Revisaremos el catálogo de enfermedades y sus datos dependientes. Pulsa Enviar para validar y guardar la respuesta.</p></div>
                            <?php if ($m05Disease): ?><p class="patient-interview-muted" data-m05-v2-prompt><strong data-m05-v2-disease-label><?=$escape($m05Disease['enfermedad']);?></strong><br><span data-m05-v2-question-label>¿Has sido diagnosticado con esta enfermedad?</span></p>
                            <div class="patient-interview-answer interview-composer"><div class="interview-composer-input"><label class="visually-hidden" for="m05-v2-input">Respuesta M05 V2</label><input id="m05-v2-input" class="form-control" data-m05-v2-input type="text" placeholder="Ej. sí, Tipo 2, 2020"></div><button type="button" class="btn btn-outline-primary chat-send-button" data-m05-v2-interpret aria-label="Interpretar respuesta M05 V2" title="Interpretar respuesta M05 V2">➤</button></div>
                            <small class="d-block patient-interview-muted mt-1" data-m05-v2-result role="status" aria-live="polite" hidden></small>
                            <?php else: ?><p class="patient-interview-muted">No hay enfermedades M05 V2 pendientes.</p><?php endif; ?>
                        </article>
                        <?php endif; ?>
                        <?php if (!empty($data['m06'])): $m06Pending = $data['m06']['pending_record'] ?? null; ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M06" data-m06-pending-record="<?=$m06Pending ? (int)($m06Pending['id'] ?? 0) : 0;?>" data-m06-version="<?=$escape($data['m06']['version'] ?? '');?>" data-m06-context="{}">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M06</strong></div><p class="interview-question-text">Continuaremos con medicación actual. Indica los cinco datos del medicamento y pulsa Enviar para validar y guardar la respuesta.</p></div>
                            <?php if (!empty($data['m06']['records'])): ?><div class="patient-interview-context-grid"><?php foreach ($data['m06']['records'] as $record): ?><div><dt>Medicamento</dt><dd><?=$escape($record['descripcion'] ?? 'Registro existente');?></dd></div><?php endforeach; ?></div><?php endif; ?>
                            <div class="patient-interview-answer interview-composer"><div class="interview-composer-input"><label class="visually-hidden" for="m06-input">Respuesta M06</label><input id="m06-input" class="form-control" data-m06-input type="text" placeholder="Ej. tomo ibuprofeno para dolor durante 2 semanas, dosis 400 mg, médico López"></div><button type="button" class="btn btn-outline-primary chat-send-button" data-m06-interpret aria-label="Interpretar respuesta M06" title="Interpretar respuesta M06">➤</button></div>
                            <small class="d-block patient-interview-muted mt-1" data-m06-result role="status" aria-live="polite" hidden></small>
                        </article>
                        <?php endif; ?>
                        <?php $m07State = $data['m07'] ?? []; $m07Categories = $m07State['categories'] ?? []; $m07StatusLabels = ['pending' => 'Pendiente', 'complete' => 'Completo', 'blocked' => 'M07 bloqueado por contrato', 'conflict' => 'Revisar inconsistencia', 'duplicate' => 'Duplicado']; $m07FirstCategory = 0; foreach ($m07Categories as $categoryIndex => $category) { $categoryPending = array_filter($category['items'] ?? [], static fn(array $item): bool => ($item['status'] ?? 'pending') !== 'complete'); if ($categoryPending) { $m07FirstCategory = (int)$categoryIndex; break; } } ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M07" data-m07-version="<?=$escape($m07State['version'] ?? '');?>" data-m07-pending="<?=((int)($m07State['pending_items'] ?? 0) > 0) ? '1' : '0';?>" data-m07-write-enabled="<?=!empty($m07State['write_enabled']) ? '1' : '0';?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M07 medicamentos para el dolor</strong></div><p class="interview-question-text">Revisaremos los medicamentos agrupados por categoría. Selecciona los que sí utilizaste o confirma explícitamente los que no utilizaste. Nada se guarda al seleccionar; la persistencia requiere una acción explícita.</p></div>
                            <?php if (!$m07Categories): ?><p class="patient-interview-muted">El catálogo P09 no está disponible; M07 permanece bloqueado y no se generaron respuestas.</p><?php endif; ?>
                            <?php if ($m07Categories): ?><nav class="m07-category-nav" data-m07-category-nav aria-label="Categorías de medicamentos M07"><span class="patient-interview-muted">Categoría:</span><?php foreach ($m07Categories as $categoryIndex => $category): ?><button type="button" class="btn btn-sm btn-outline-secondary" data-m07-category-goto="<?=$categoryIndex;?>" aria-controls="m07-category-<?=$categoryIndex;?>"><?=$escape($category['theme']);?></button><?php endforeach; ?></nav><?php endif; ?>
                            <div class="m07-categories" data-m07-categories>
                            <?php foreach ($m07Categories as $categoryIndex => $category): $categoryPending = array_filter($category['items'] ?? [], static fn(array $item): bool => ($item['status'] ?? 'pending') !== 'complete'); ?>
                                <section id="m07-category-<?=$categoryIndex;?>" class="m07-category" data-m07-category data-category-index="<?=$categoryIndex;?>" data-category-pending="<?=count($categoryPending);?>" data-theme-id="<?=(int)$category['theme_id'];?>"<?=$categoryIndex !== $m07FirstCategory ? ' hidden' : '';?> aria-labelledby="m07-category-title-<?=$categoryIndex;?>">
                                    <h3 id="m07-category-title-<?=$categoryIndex;?>" class="h5 mb-3"><?=$escape($category['theme']);?></h3>
                                    <div class="m07-items" role="list">
                                    <?php foreach (($category['items'] ?? []) as $item): $fields = $item['fields'] ?? []; $questionId = (int)$item['question_id']; $itemKey = 'm07-' . $questionId; $itemStatus = (string)($item['status'] ?? 'pending'); $isOther = !empty($item['other_blocked']); $used = ($fields['respuesta'] ?? '') === 'Si'; ?>
                                        <div class="m07-item" data-m07-item data-question-id="<?=$questionId;?>" data-theme-id="<?=(int)$category['theme_id'];?>" data-status="<?=$escape($itemStatus);?>" data-other-blocked="<?=$isOther ? '1' : '0';?>" data-current-result="<?=$escape($fields['resultados'] ?? '');?>">
                                            <label class="m07-item-choice" for="<?=$itemKey;?>-used">
                                                <input id="<?=$itemKey;?>-used" type="checkbox" class="form-check-input" data-m07-used <?=$used ? 'checked' : '';?> <?=$isOther ? 'disabled' : '';?> aria-describedby="<?=$itemKey;?>-status">
                                                <span><?=$escape($item['question'] ?? 'Medicamento');?></span>
                                            </label>
                                            <span id="<?=$itemKey;?>-status" class="badge text-bg-light" data-m07-status><?=$escape($m07StatusLabels[$itemStatus] ?? 'Pendiente');?></span>
                                            <?php if ($isOther): ?><p class="patient-interview-muted mb-2">“Otro/Otros” requiere una decisión clínica sobre el campo donde se guardará su nombre. No se puede registrar desde esta entrega.</p><?php endif; ?>
                                            <div class="m07-item-details" data-m07-details <?=$used ? '' : 'hidden';?>>
                                                <div class="row g-2">
                                                    <div class="col-12 col-md-6"><label class="form-label" for="<?=$itemKey;?>-dose">Dosis documentada</label><input id="<?=$itemKey;?>-dose" class="form-control" type="text" data-m07-dose value="<?=$escape($fields['dosis'] ?? '');?>" placeholder="Escribe la dosis, sin inventar unidades"></div>
                                                    <div class="col-12 col-md-6"><fieldset><legend class="form-label">Resultado</legend><div class="d-flex flex-wrap gap-2"><label><input type="radio" name="<?=$itemKey;?>-result" value="Funciono" data-m07-result <?=($fields['resultados'] ?? '') === 'Funciono' ? 'checked' : '';?>> Funcionó</label><label><input type="radio" name="<?=$itemKey;?>-result" value="No funciono" data-m07-result <?=($fields['resultados'] ?? '') === 'No funciono' ? 'checked' : '';?>> No funcionó</label><label><input type="radio" name="<?=$itemKey;?>-result" value="Tuve efectos adversos" data-m07-result <?=($fields['resultados'] ?? '') === 'Tuve efectos adversos' ? 'checked' : '';?>> Efectos adversos</label></div></fieldset></div>
                                                    <div class="col-12" data-m07-adverse-wrap <?=($fields['resultados'] ?? '') === 'Tuve efectos adversos' ? '' : 'hidden';?>><label class="form-label" for="<?=$itemKey;?>-adverse">Descripción de efectos adversos</label><textarea id="<?=$itemKey;?>-adverse" class="form-control" rows="2" data-m07-description><?=$escape($fields['descripcion'] ?? '');?></textarea></div>
                                                    <div class="col-12"><fieldset><legend class="form-label">¿Todavía lo utilizas?</legend><label class="me-3"><input type="radio" name="<?=$itemKey;?>-consumption" value="Si" data-m07-consumption <?=($fields['consumo'] ?? '') === 'Si' ? 'checked' : '';?>> Sí</label><label><input type="radio" name="<?=$itemKey;?>-consumption" value="No" data-m07-consumption <?=($fields['consumo'] ?? '') === 'No' ? 'checked' : '';?>> No</label></fieldset></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                    <div class="patient-interview-answer mt-3 m07-actions">
                                        <button type="button" class="btn btn-outline-secondary" data-m07-confirm-negatives>Confirmar no utilizados</button>
                                        <button type="button" class="btn btn-primary" data-m07-save-selected>Guardar seleccionados</button>
                                    </div>
                                    <div class="patient-interview-answer mt-2 m07-navigation" aria-label="Navegación de categorías"><button type="button" class="btn btn-outline-secondary" data-m07-previous-category<?=$categoryIndex === 0 ? ' disabled' : '';?>>← Categoría anterior</button><span class="patient-interview-muted" data-m07-category-state><?=count($categoryPending) ? 'Respuestas pendientes en esta categoría.' : 'Categoría revisada.';?></span><button type="button" class="btn btn-outline-secondary" data-m07-next-category<?=$categoryIndex === count($m07Categories) - 1 ? ' disabled' : '';?>>Siguiente categoría →</button></div>
                                </section>
                            <?php endforeach; ?>
                            </div>
                            <div class="patient-interview-status mt-3" data-m07-message role="status" aria-live="polite" hidden></div>
                            <p class="patient-interview-muted mb-0">Los datos desconocidos permanecen pendientes. Las respuestas combinadas de eficacia y efectos adversos no se reducen a una opción. La finalización de M07 sigue deshabilitada hasta cerrar las reglas de completitud clínica.</p>
                        </article>
                        <?php if (!empty($data['m08'])) { $m08Items = $data['m08']['items'] ?? []; $m08Pending = null; foreach ($m08Items as $m08Item) { $m08Utilizo = trim((string)($m08Item['utilizo'] ?? '')); $m08Result = trim((string)($m08Item['resultado'] ?? '')); $m08Description = trim((string)($m08Item['descripcion'] ?? '')); $m08Incomplete = ($m08Utilizo === '') || ($m08Utilizo === 'Si' && ($m08Result === '' || ($m08Result === 'Tuve efectos adversos' && $m08Description === ''))); if ($m08Incomplete) { $m08Pending = $m08Item; break; } } ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M08" data-m08-version="<?=$escape($data['m08']['version'] ?? '');?>" data-m08-write-enabled="<?=!empty($data['m08']['write_enabled']) ? '1' : '0';?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M08 tratamientos</strong></div><p class="interview-question-text">Revisaremos los tratamientos del catálogo uno por uno. Pulsa Enviar para validar y guardar la respuesta.</p></div>
                            <?php if ($m08Pending): ?>
                            <p class="patient-interview-muted"><strong>Tratamiento: <?=$escape($m08Pending['tratamiento'] ?? '');?></strong></p>
                            <div class="patient-interview-answer interview-composer"><div class="interview-composer-input"><label class="visually-hidden" for="m08-input">Respuesta M08</label><input id="m08-input" class="form-control" data-m08-input type="text" placeholder="Ej. no; o utilizo: Sí; resultado: Funciono"></div><button type="button" class="btn btn-outline-primary chat-send-button" data-m08-interpret aria-label="Interpretar respuesta M08" title="Interpretar respuesta M08">➤</button></div>
                            <small class="d-block patient-interview-muted mt-1" data-m08-result role="status" aria-live="polite" hidden></small>
                            <?php else: ?><p class="patient-interview-muted">Tratamientos M08 completos o sin pendientes documentales.</p><?php endif; ?>
                            <p class="patient-interview-muted mb-0">Los procedimientos M08 permanecen separados del catálogo de tratamientos y se guardan únicamente cuando su contrato confirma que los campos están completos.</p>
                        </article>
                        <?php } ?>
                        <?php if (!empty($data['m09'])): $m09Evaluation = $data['m09']['evaluation'] ?? null; ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M09" data-m09-version="<?=$escape($data['m09']['version'] ?? '');?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M09</strong></div><p class="interview-question-text">Revisaremos los cuatro campos de evaluación del dolor. Pulsa Enviar para validar y guardar la respuesta; el mapa corporal se mantiene separado.</p></div>
                            <?php if ($m09Evaluation): ?><div class="patient-interview-context-grid"><div><dt>Evaluación existente</dt><dd><?=trim((string)($m09Evaluation['dolor'] ?? '')) !== '' ? 'Respuesta documentada' : 'Pendiente';?></dd></div></div><?php endif; ?>
                            <div class="patient-interview-answer interview-composer"><div class="interview-composer-input"><label class="visually-hidden" for="m09-input">Respuesta M09</label><input id="m09-input" class="form-control" data-m09-input type="text" placeholder="dolor: Sí; tiempo: 3 meses; descripción: inició gradualmente; incremento: igual"></div><button type="button" class="btn btn-outline-primary chat-send-button" data-m09-interpret aria-label="Interpretar respuesta M09" title="Interpretar respuesta M09">➤</button></div>
                            <small class="d-block patient-interview-muted mt-1" data-m09-result role="status" aria-live="polite" hidden></small>
                            <p class="patient-interview-muted mb-0">La localización anatómica no se interpreta desde texto libre.</p>
                        </article>
                        <?php endif; ?>
                        <?php if (!empty($data['m09Map'])): $m09MapVersion = $data['m09Map']['version'] ?? ''; ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M09-MAP" data-m09-map-version="<?=$escape($m09MapVersion);?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M09 mapa corporal</strong></div><p class="interview-question-text">Ahora vamos a identificar dónde sientes dolor. Marca las zonas en el mapa, revisa la previsualización y confirma solo cuando la imagen sea correcta.</p></div>
                            <div class="chat-p11-map-grid">
                                <?php foreach (['frente' => 'Imagen frontal', 'espalda' => 'Imagen posterior'] as $orientation => $label): ?>
                                <section class="chat-p11-map" data-map-panel="<?=$orientation;?>" aria-labelledby="chat-map-title-<?=$orientation;?>">
                                    <h3 id="chat-map-title-<?=$orientation;?>" class="h5"><?=$escape($label);?></h3>
                                    <div class="d-flex flex-wrap gap-2 mb-2" role="group" aria-label="Tipo de sensación <?=$escape($label);?>">
                                        <?php foreach (['#ff0000' => 'Dolor', '#0000ff' => 'Adormecido', '#00ff00' => 'Molestia al roce', '#ffff00' => 'Sin sensibilidad', '#ff00ff' => 'Punzadas y calambres'] as $color => $colorLabel): ?><button type="button" class="btn btn-sm btn-outline-secondary chat-map-color" data-map-color="<?=$color;?>" aria-label="<?=$escape($colorLabel);?>" style="border-color:<?=$color;?>;">● <?=$escape($colorLabel);?></button><?php endforeach; ?>
                                    </div>
                                    <div class="chat-p11-canvas-wrap"><canvas width="1200" height="1800" data-map-canvas="<?=$orientation;?>" aria-label="Mapa corporal <?=$escape($label);?>"></canvas></div>
                                    <div class="patient-interview-answer mt-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-map-clear="<?=$orientation;?>">Limpiar edición</button><button type="button" class="btn btn-outline-primary btn-sm" data-map-preview="<?=$orientation;?>">Previsualizar PNG</button></div>
                                    <div class="mt-2" data-map-preview-box="<?=$orientation;?>" hidden><p class="patient-interview-muted mb-1">Previsualización: nada se ha guardado.</p><img class="chat-p11-preview" data-map-preview-image="<?=$orientation;?>" alt="Previsualización del mapa corporal"><div class="patient-interview-answer"><button type="button" class="btn btn-outline-secondary btn-sm" data-map-edit="<?=$orientation;?>">Volver a editar</button><button type="button" class="btn btn-primary btn-sm" data-map-confirm="<?=$orientation;?>">Confirmar y guardar</button><button type="button" class="btn btn-outline-secondary btn-sm" data-map-cancel="<?=$orientation;?>">Cancelar</button></div></div>
                                </section>
                                <?php endforeach; ?>
                            </div>
                            <p class="patient-interview-muted mb-0">Las marcas se guardan únicamente después de confirmar la previsualización. El mapa utiliza la persistencia PNG existente y no interpreta texto como regiones.</p>
                        </article>
                        <?php endif; ?>
                        <div id="interview-status" class="patient-interview-muted patient-interview-status" data-interview-status role="status" aria-live="polite" aria-atomic="true" hidden></div>
                    </div>
                </section>
                <aside class="patient-interview-context-panel" aria-label="Contexto y guía de la entrevista">
                    <section class="patient-interview-context-card" aria-labelledby="interview-context-title">
                        <h3 id="interview-context-title"><i data-feather="clipboard" aria-hidden="true"></i> Contexto de la entrevista</h3>
                        <div class="patient-interview-context-current"><strong data-context-current-module>M01</strong><p data-context-summary>Información de contexto de la entrevista.</p></div>
                    </section>
                    <section class="patient-interview-context-card" aria-labelledby="interview-voice-title">
                        <h3><i data-feather="headphones" aria-hidden="true"></i> Guía por voz</h3>
                        <div class="interview-voice-panel" data-voice-panel data-voice-state="DISABLED" role="region" aria-labelledby="interview-voice-title">
                            <strong class="visually-hidden" id="interview-voice-title">Guía por voz</strong>
                            <span class="interview-voice-status" data-voice-status aria-live="polite">Activando guía por voz…</span>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-voice-start>Activar audio</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-voice-repeat hidden>Repetir</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-voice-pause hidden>Pausar narración</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-voice-resume hidden>Reanudar narración</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-voice-mute hidden>Silenciar</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-voice-stop hidden>Detener</button>
                            <label class="voice-speed-control" for="interview-voice-rate">Velocidad <select id="interview-voice-rate" data-voice-rate aria-label="Velocidad de narración"><option value="0.85">Lenta</option><option value="0.95" selected>Normal</option><option value="1.1">Rápida</option></select></label>
                        </div>
                    </section>
                    <section class="patient-interview-context-card" aria-labelledby="interview-tips-title">
                        <h3 id="interview-tips-title"><i data-feather="sun" aria-hidden="true"></i> Consejos para responder</h3>
                        <ul class="patient-interview-tips"><li>Responde con tus propias palabras.</li><li>Puedes escribir o dictar tu respuesta.</li><li>Las respuestas complejas se revisan antes de guardarse.</li><li>Si necesitas ayuda, usa el botón de la pregunta.</li></ul>
                    </section>
                </aside>
                </div>
            </div>
        </main>
        <?php include_once __DIR__ . '/../components/footer-doctor.php';?>
    </div>
</div>
<script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
<script src="<?=$coordinatorJsUrl;?>"></script>
<script src="<?=$voiceJsUrl;?>"></script>
<script>
(() => {
    const main = document.getElementById('main');
    const conversationContract = <?=$conversationContractJson;?>;
    window.patientInterviewContract = conversationContract;
    const questions = Array.from(document.querySelectorAll('.patient-interview-question'));
    const status = document.getElementById('interview-status');
    const progress = document.getElementById('interview-progress');
    const progressBar = document.getElementById('interview-progress-bar');
    const progressMeter = progressBar?.closest('[role="progressbar"]');
    const conversationChat = document.getElementById('interview-chat');
    const stateBadge = document.querySelector('[data-interview-state-label]');
    const currentModuleBadge = document.querySelector('[data-interview-current-module]');
    const contextModuleLabel = document.querySelector('[data-context-current-module]');
    const contextModuleSummary = document.querySelector('[data-context-summary]');
    let active = questions.findIndex(question => !question.hidden);
    if (active < 0) active = 0;
    const setStatus = message => {
        if (!status) return;
        const text = String(message ?? '').trim();
        status.textContent = text;
        status.hidden = text === '';
    };
    const semanticAnswerStates = new Set(['UNKNOWN', 'NOT_REMEMBERED']);
    const semanticAnswerState = question => String(question?.dataset.semanticState || '').trim().toUpperCase();
    const hasConfirmedAnswer = question => Boolean(question?.dataset.savedValue?.trim()) || semanticAnswerStates.has(semanticAnswerState(question));
    const answerLabel = question => {
        const value = question?.dataset.savedValue?.trim() || '';
        if (value) return value;
        if (semanticAnswerState(question) === 'UNKNOWN') return 'Dato desconocido declarado por el paciente';
        if (semanticAnswerState(question) === 'NOT_REMEMBERED') return 'Dato no recordado declarado por el paciente';
        return '';
    };
    const coordinatorStateLabels = Object.freeze({
        INITIALIZING: 'Preparando', READY: 'Lista para comenzar', ASKING: 'Pregunta activa',
        WAITING_FOR_RESPONSE: 'Esperando tu respuesta', INTERPRETING: 'Interpretando',
        CLARIFICATION_REQUIRED: 'Necesita aclaración', CONFIRMATION_REQUIRED: 'Confirmación requerida',
        SAVING: 'Guardando', SAVE_FAILED: 'No se pudo guardar', RESPONSE_SAVED: 'Respuesta guardada',
        NAVIGATING: 'Avanzando', PAUSED: 'Pausada', RESUMING: 'Reanudando',
        MODULE_COMPLETED: 'Módulo revisado', MODULE_BLOCKED: 'Módulo bloqueado', SESSION_EXPIRED: 'Sesión expirada'
    });
    const alertStates = new Set(['SAVE_FAILED', 'SESSION_EXPIRED']);
    const busyStates = new Set(['INTERPRETING', 'SAVING', 'NAVIGATING', 'RESUMING']);
    const syncCoordinatorState = event => {
        const state = String(event?.state || 'INITIALIZING');
        if (main) { main.dataset.conversationState = state; main.dataset.interviewState = state; }
        if (stateBadge) {
            stateBadge.dataset.state = state;
            stateBadge.textContent = coordinatorStateLabels[state] || state;
        }
        if (status) {
            status.dataset.state = state;
            status.setAttribute('role', alertStates.has(state) ? 'alert' : 'status');
        }
        if (conversationChat) conversationChat.setAttribute('aria-busy', busyStates.has(state) ? 'true' : 'false');
    };
    const conversationCoordinator = new window.PatientInterviewCoordinator({
        contract: conversationContract,
        patientId: Number(main?.dataset.patient || 0),
        onStateChange: event => {
            syncCoordinatorState(event);
            if (['PAUSED', 'INTERPRETING', 'SAVING', 'MODULE_BLOCKED', 'MODULE_COMPLETED', 'SESSION_EXPIRED'].includes(event.state) || ['target_changed', 'previous_question'].includes(event.metadata?.reason)) {
                if (typeof stopAssistantVoice === 'function') stopAssistantVoice();
            }
            if (['NAVIGATING', 'PAUSED', 'MODULE_BLOCKED', 'MODULE_COMPLETED', 'SESSION_EXPIRED'].includes(event.state) && typeof stopRecognition === 'function') stopRecognition();
            if (event.state === 'SESSION_EXPIRED') setStatus('La sesión expiró. Recarga la entrevista para continuar sin perder respuestas ya confirmadas.');
        },
        onVoiceEvent: event => {
            if (main && event.type) main.dataset.lastVoiceEvent = event.type;
            if (typeof handleVoiceEvent === 'function') handleVoiceEvent(event);
        },
    });
    // Todos los POST conversacionales pasan por el coordinador; sus endpoints
    // existentes siguen validando sesión, CSRF, paciente, versión y persistencia.
    const fetch = (url, init) => conversationCoordinator.request('auto', url, init);
    window.patientInterviewCoordinator = conversationCoordinator;
    const moduleCards = Array.from(document.querySelectorAll('[data-module]'));
    const conversationStages = [];
    const addModuleStage = selector => { const card = document.querySelector(selector); if (card) conversationStages.push({type:'module', card}); };
    addModuleStage('[data-module="M02"]');
    conversationStages.push({type:'m03', card:null});
    addModuleStage('[data-module="M04"]');
    const m05Card = document.querySelector('[data-module="M05"]');
    const m05V2Card = document.querySelector('[data-module="M05-V2"]');
    if (m05Card?.dataset.m05Pending === '1') conversationStages.push({type:'module', card:m05Card});
    if (m05V2Card?.dataset.m05V2Pending === '1') conversationStages.push({type:'module', card:m05V2Card});
    addModuleStage('[data-module="M06"]');
    addModuleStage('[data-module="M07"]');
    addModuleStage('[data-module="M08"]');
    addModuleStage('[data-module="M09"]');
    addModuleStage('[data-module="M09-MAP"]');
    const stageCompletion = stage => {
        if (stage?.type === 'm03') return conversationContract.modules?.M03?.completion || null;
        const moduleId = stage?.card?.dataset.module || '';
        return moduleId ? (conversationContract.modules?.[moduleId]?.completion || null) : null;
    };
    const stageConversationState = stage => {
        const moduleId = stageModuleId(stage);
        return moduleId ? (conversationContract.modules?.[moduleId]?.conversation_state || null) : null;
    };
    const stageModuleId = stage => stage?.type === 'm03' ? 'M03' : (stage?.card?.dataset.module || '');
    const stageNeedsResume = stage => {
        const completion = stageCompletion(stage);
        const resolverState = stageConversationState(stage)?.state || '';
        return ['ANSWERABLE', 'READY_TO_FINALIZE', 'AWAITING_PROFESSIONAL_REVIEW', 'BLOCKED_PREREQUISITE', 'INCONSISTENT'].includes(resolverState)
            || completion?.resolution === 'AUTHORIZED_FINALIZATION_REQUIRED'
            || !completion
            || completion.complete !== true;
    };
    const nextPendingStage = startIndex => {
        for (let index = Math.max(0, startIndex); index < conversationStages.length; index += 1) {
            if (stageNeedsResume(conversationStages[index])) return index;
        }
        return conversationStages.length;
    };
    const serverResumeModuleId = String(conversationContract.interview_progress?.first_pending_module_id || '').trim();
    const serverPendingStage = serverResumeModuleId
        ? conversationStages.findIndex(stage => stageModuleId(stage) === serverResumeModuleId && stageNeedsResume(stage))
        : -1;
    const firstPendingStage = serverPendingStage >= 0
        ? serverPendingStage
        : conversationStages.findIndex(stageNeedsResume);
    let conversationStage = firstPendingStage >= 0 ? firstPendingStage : conversationStages.length;
    const stageLabel = stage => {
        if (!stage) return 'Entrevista';
        if (stage.type === 'm03') {
            const firstQuestion = questions.find(question => !question.hidden) || questions[0];
            return `M03 · ${firstQuestion?.dataset.themeName || 'Pregunta activa'}`;
        }
        const module = stage.card?.dataset.module || 'Entrevista';
        if (module === 'M09-MAP') return 'M09 · Mapa corporal';
        return conversationContract.modules?.[module]?.title ? `${module} · ${conversationContract.modules[module].title}` : module;
    };
    const canonicalStageTarget = stage => {
        const card = stage?.card;
        const moduleId = card?.dataset.module || '';
        if (!moduleId) return null;
        const moduleQuestions = conversationContract.modules?.[moduleId]?.questions || [];
        if (!moduleQuestions.length) return null;
        const declaredStableId = String(card.dataset.m04StableId || '').trim();
        if (declaredStableId && conversationContract.question_index?.[declaredStableId]?.module_id === moduleId) return declaredStableId;
        if (moduleId === 'M04') {
            const recordId = String(card.dataset.m04Record || '').trim();
            if (recordId && recordId !== '0') {
                const recordQuestion = moduleQuestions.find(question => String(question.question_id ?? '') === recordId);
                if (recordQuestion?.stable_id) return recordQuestion.stable_id;
            }
        }
        return moduleQuestions[0]?.stable_id || null;
    };
    const contextSummaryFor = label => {
        const module = String(label || '').split('·')[0].trim();
        const summaries = {
            M01: 'Datos de identificación en modo informativo.',
            M02: 'Antecedentes familiares pendientes de revisión.',
            M03: 'Evaluación de hábitos de consumo.',
            M04: 'Antecedentes quirúrgicos y temporalidad.',
            M05: 'Antecedentes personales y dependencias activas.',
            'M05-V2': 'Seguimiento de antecedentes con campos relacionados.',
            M06: 'Medicamentos, dosis, duración y respuesta.',
            M07: 'Medicamentos para el dolor agrupados por categoría.',
            M08: 'Tratamientos y resultados documentados.',
            M09: 'Evaluación actual del dolor y su evolución.',
        };
        return summaries[module] || 'Contexto activo de la entrevista.';
    };
    const setCurrentModule = label => {
        const activeLabel = label || 'Entrevista';
        if (currentModuleBadge) currentModuleBadge.textContent = activeLabel;
        if (contextModuleLabel) contextModuleLabel.textContent = activeLabel;
        if (contextModuleSummary) contextModuleSummary.textContent = contextSummaryFor(activeLabel);
    };
    const hideConversationCards = () => { moduleCards.forEach(card => card.classList.add('chat-stage-hidden')); questions.forEach(question => { question.hidden = true; }); };
    const finalizeM02 = async card => {
        if (!card || card.dataset.m02Finalizing === '1') return;
        card.dataset.m02Finalizing = '1';
        const action = card.querySelector('[data-m02-resolution-action]');
        if (action) action.disabled = true;
        setStatus('Comprobando y finalizando antecedentes familiares…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m02', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), finalize: true})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado || data.finalized !== true || data.finalization_readback !== true) {
                throw new Error(data.mensaje || 'No fue posible confirmar el cierre de antecedentes familiares.');
            }
            setStatus('Antecedentes familiares finalizados. Continuando…');
            if (typeof data.next_url === 'string' && data.next_url.startsWith('/')) {
                window.location.assign(data.next_url);
            } else {
                window.location.reload();
            }
        } catch (error) {
            setStatus(error.message || 'No fue posible finalizar antecedentes familiares.');
            if (action) action.disabled = false;
        } finally {
            card.dataset.m02Finalizing = '0';
        }
    };
    const exposeM02ResolutionAction = card => {
        if (!card || card.dataset.module !== 'M02') return;
        let action = card.querySelector('[data-m02-resolution-action]');
        if (!action) {
            action = document.createElement('button');
            action.type = 'button';
            action.dataset.m02ResolutionAction = '1';
            action.className = 'btn btn-outline-primary mt-2';
            action.textContent = 'Finalizar antecedentes familiares y continuar';
            action.setAttribute('aria-label', 'Finalizar antecedentes familiares y continuar');
            card.appendChild(action);
        }
        if (action.dataset.m02ResolutionBound !== '1') {
            action.addEventListener('click', () => finalizeM02(card));
            action.dataset.m02ResolutionBound = '1';
        }
        action.hidden = false;
    };
    const showConversationStage = index => {
        conversationStage = Math.max(0, Math.min(index, conversationStages.length));
        if (typeof stopRecognition === 'function') stopRecognition();
        hideConversationCards();
        const stage = conversationStages[conversationStage];
        if (!stage) {
            setCurrentModule('Entrevista');
            setStatus('No hay otra etapa autorizada pendiente. Puedes consultar las respuestas anteriores.');
            updateProgress();
            return;
        }
        setCurrentModule(stageLabel(stage));
        if (stage.card) {
            const moduleId = stage.card.dataset.module || '';
            const resolver = stageConversationState(stage) || {};
            if (stage.card.dataset.module !== 'M02') {
                const target = stage.card.dataset.module === 'M04'
                    ? canonicalStageTarget(stage)
                    : stage.card.dataset.module || `stage:${conversationStage}`;
                if (!target) {
                    setStatus('No se pudo resolver la pregunta M04 activa. Puedes continuar por escrito.');
                    return;
                }
                conversationCoordinator.setTarget(target);
            }
            if (resolver.state === 'BLOCKED_PREREQUISITE'
                && (moduleId === 'M07' || stage.card.dataset.m07WriteEnabled !== '1')) {
                conversationCoordinator.markModuleBlocked(resolver.reason || 'MODULE_PREREQUISITE_BLOCKED');
            }
        }
        if (stage.type === 'm03') {
            const pending = questions.findIndex(question => !hasConfirmedAnswer(question) && question.dataset.eligible === '1');
            if (pending >= 0) showQuestion(pending);
            else { showConversationStage(nextPendingStage(conversationStage + 1)); return; }
        }
        else {
            stage.card.classList.remove('chat-stage-hidden');
            const resolver = stage.card.dataset.module === 'M02' ? (stageConversationState(stage) || {}) : {};
            const mayAnswerM02 = !resolver.state || resolver.state === 'ANSWERABLE';
            const focusedM02 = stage.card.dataset.module === 'M02' && mayAnswerM02
                ? focusM02Pending(stage.card)
                : false;
            if (stage.card.dataset.module === 'M02' && !focusedM02) {
                const completion = stageCompletion(stage) || {};
                const hasPendingQuestion = Array.isArray(completion.pending_question_ids) && completion.pending_question_ids.length > 0;
                const requiresFinalization = resolver.state === 'READY_TO_FINALIZE'
                    || completion.resolution === 'AUTHORIZED_FINALIZATION_REQUIRED';
                const message = resolver.state === 'AWAITING_PROFESSIONAL_REVIEW'
                    ? 'M02 requiere revisión profesional antes de continuar. No se modificó ninguna respuesta.'
                    : requiresFinalization
                        ? 'M02 tiene respuestas documentadas y requiere una finalización expresa. No se cerró automáticamente.'
                        : hasPendingQuestion
                            ? 'M02 tiene una pregunta pendiente, pero no pudo resolverse su identificador canónico. La captura permanece bloqueada.'
                        : 'M02 no pudo resolver una pregunta activa por un bloqueo técnico. La captura permanece protegida.';
                if (requiresFinalization && !hasPendingQuestion) {
                    // This is an explicit, answerable finalization action, not
                    // a coordinator dead-end: keep requests enabled so the
                    // POST can transition through the normal save guard.
                    exposeM02ResolutionAction(stage.card);
                    setStatus(message);
                    return;
                }
                if (resolver.state === 'AWAITING_PROFESSIONAL_REVIEW') {
                    setStatus(message);
                    return;
                }
                conversationCoordinator.markModuleBlocked(completion.status === 'blocked' ? 'M02_CONTRACT_BLOCKED' : 'M02_ACTIVE_QUESTION_UNRESOLVED');
                setStatus(message);
                return;
            }
        }
        window.setTimeout(() => { if (typeof bindVoiceControls === 'function') bindVoiceControls(); }, 0);
        updateProgress();
    };
    const advanceConversation = () => {
        if (!conversationCoordinator.navigate('confirmed_backend_response')) { setStatus('La respuesta aún no está confirmada por el servidor; la pregunta permanece activa.'); return; }
        const currentCompletion = stageCompletion(conversationStages[conversationStage]);
        if (currentCompletion?.status === 'blocked') { setStatus(currentCompletion.message || 'Este módulo permanece bloqueado.'); return; }
        const nextStage = nextPendingStage(conversationStage + 1);
        if (nextStage < conversationStages.length) showConversationStage(nextStage);
        else { conversationCoordinator.transition('MODULE_COMPLETED', {reason: 'all_authorized_stages_reviewed'}); setStatus('Entrevista disponible para reanudar. No hay otra etapa autorizada pendiente.'); }
    };
    const focusM02Pending = card => {
        const rows = Array.from(card.querySelectorAll('.patient-interview-context-grid > div'));
        rows.forEach(row => row.classList.add('chat-stage-hidden'));
        const module = conversationContract.modules?.M02 || {};
        const pendingIds = Array.isArray(module.completion?.pending_question_ids) ? module.completion.pending_question_ids : [];
        const pendingQuestion = pendingIds
            .map(stableId => conversationContract.question_index?.[stableId])
            .find(question => question?.module_id === 'M02' && question?.question_id);
        if (!pendingQuestion) return false;
        const disease = String(pendingQuestion.question_id).trim();
        const next = rows.find(row => !row.dataset.chatDone && String(row.dataset.m02Disease || '').trim() === disease);
        if (!next) return false;
        next.classList.remove('chat-stage-hidden');
        const target = pendingQuestion.stable_id || conversationCoordinator.stableIdForQuestion('M02', disease);
        if (!target || conversationContract.question_index?.[target]?.module_id !== 'M02') return false;
        conversationCoordinator.setTarget(target);
        return true;
    };
    const advanceM02 = (row, finalized = false) => { const current = row; const card = row.closest('[data-module="M02"]'); if (current) current.dataset.chatDone = '1'; if (!focusM02Pending(card)) { if (finalized !== true) { setStatus('Respuesta M02 guardada. El servidor aún no confirmó el cierre del módulo; permanece aquí.'); return; } advanceConversation(); } };
    const historyButton = document.querySelector('[data-chat-history]');
    const historyPanel = document.querySelector('[data-chat-history-panel]');
    const historyContent = document.querySelector('[data-chat-history-content]');
    const renderRegisteredAnswers = () => {
        if (!historyContent) return;
        historyContent.textContent = '';
        const savedQuestions = questions.filter(hasConfirmedAnswer);
        if (!savedQuestions.length) {
            const empty = document.createElement('p');
            empty.className = 'chat-history-empty';
            empty.textContent = 'No hay respuestas M03 registradas para consultar.';
            historyContent.appendChild(empty);
            return;
        }
        const list = document.createElement('ul');
        list.className = 'chat-history-list';
        savedQuestions.forEach(question => {
            const item = document.createElement('li');
            item.className = 'chat-history-item';
            const details = document.createElement('dl');
            const module = document.createElement('dt');
            module.textContent = question.querySelector('.interview-message-header strong')?.textContent.trim() || 'M03';
            const prompt = document.createElement('dt');
            prompt.textContent = 'Pregunta';
            const promptValue = document.createElement('dd');
            promptValue.textContent = question.querySelector('.interview-question-text')?.textContent.trim() || 'Pregunta clínica';
            const answer = document.createElement('dt');
            answer.textContent = 'Respuesta guardada';
            const answerValue = document.createElement('dd');
            answerValue.textContent = answerLabel(question);
            details.append(module, prompt, promptValue, answer, answerValue);
            item.appendChild(details);
            list.appendChild(item);
        });
        historyContent.appendChild(list);
    };
    if (historyButton && historyPanel) historyButton.addEventListener('click', () => { historyPanel.hidden = !historyPanel.hidden; if (!historyPanel.hidden) renderRegisteredAnswers(); });
    const pauseButton = document.querySelector('[data-interview-pause]');
    const resumeButton = document.querySelector('[data-interview-resume]');
    const syncPauseControls = () => {
        const paused = conversationCoordinator.snapshot().paused;
        if (pauseButton) pauseButton.hidden = paused;
        if (resumeButton) resumeButton.hidden = !paused;
    };
    pauseButton?.addEventListener('click', () => {
        if (!conversationCoordinator.pause()) { setStatus('Espera a que termine la interpretación o el guardado antes de pausar.'); return; }
        if (typeof stopRecognition === 'function') stopRecognition();
        syncPauseControls();
        setStatus('Entrevista pausada. No se guardó información nueva; puedes reanudar cuando quieras.');
    });
    resumeButton?.addEventListener('click', () => {
        if (!conversationCoordinator.resume()) return;
        syncPauseControls();
        showConversationStage(conversationStage);
        setStatus('Entrevista reanudada desde el estado confirmado por el servidor.');
    });

    const updateProgress = () => {
        const current = Math.min(conversationStage + 1, conversationStages.length || 1);
        const total = conversationStages.length || 1;
        const percentage = conversationStages.length ? (current / total) * 100 : 0;
        if (progress) progress.textContent = `Etapa ${current} de ${total}`;
        if (progressBar) progressBar.style.width = `${percentage}%`;
        if (progressMeter) {
            progressMeter.setAttribute('aria-valuemax', String(total));
            progressMeter.setAttribute('aria-valuenow', String(current));
            progressMeter.setAttribute('aria-valuetext', `Etapa ${current} de ${total}`);
        }
    };
    const showQuestion = index => {
        const candidate = questions[index];
        if (candidate && candidate.dataset.eligible === '0' && !hasConfirmedAnswer(candidate)) {
            return false;
        }
        active = index;
        if (typeof stopAssistantVoice === 'function') stopAssistantVoice();
        if (typeof stopRecognition === 'function') stopRecognition();
        const question = questions[active];
        setCurrentModule(question ? `M03 · ${question.dataset.themeName || 'Pregunta activa'}` : 'M03');
        if (question) conversationCoordinator.setTarget(`M03:question:${question.dataset.questionId || active}`);
        const responseZone = question?.querySelector('[data-interview-zone="response"]');
        const canEditQuestion = question?.dataset.m03WriteEnabled === '1' && question?.dataset.eligible === '1';
        if (responseZone) {
            // A previously confirmed question is rendered with its composer
            // hidden until the first pending question. Returning to it is an
            // explicit edit intent, so restore the response zone and its
            // controls for the active, eligible question only.
            responseZone.hidden = !canEditQuestion;
            if (canEditQuestion && question.dataset.m03Saving !== '1' && question.dataset.m03Interpreting !== '1') {
                responseZone.querySelectorAll('input, button, select, textarea').forEach(control => { control.disabled = false; });
            }
        }
        moduleCards.forEach(card => card.classList.add('chat-stage-hidden'));
        questions.forEach((question, questionIndex) => { question.hidden = questionIndex !== active; });
        if (conversationStages[conversationStage]?.type === 'm03') questions.forEach(question => { question.hidden = questions.indexOf(question) !== active; });
        const composer = questions[active]?.querySelector('.m03-composer');
        if (composer) composer.hidden = false;
        window.setTimeout(() => { if (typeof bindVoiceControls === 'function') bindVoiceControls(); }, 0);
        updateProgress();
        updateM03PreviousButton();
        return Boolean(question);
    };
    const valueFor = question => {
        const input = question.querySelector('.interview-text');
        return input ? input.value.trim() : '';
    };
    const updateM03AnswerCount = question => {
        const input = question?.querySelector('.interview-text');
        const counter = question?.querySelector('[data-answer-count]');
        if (input && counter) counter.textContent = `${input.value.length}/2000`;
    };
    const isDirectNumericValue = value => /^\d+(?:[.,]\d+)?$/.test(String(value || '').trim());
    const directDurationPattern = /^(?:hace|desde hace|durante)\s+(?:\d+(?:[.,]\d+)?|uno|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce)\s+(?:años?|mes(?:es)?|semanas?|días?)\.?$/i;
    const directModeForM03 = question => {
        if (question?.dataset.m03DirectCapable !== '1') return null;
        const value = valueFor(question);
        if (isDirectNumericValue(value)) return 'numeric';
        return directDurationPattern.test(value) ? 'literal' : null;
    };
    const isDirectM03Candidate = question => directModeForM03(question) !== null;
    const updateM03SubmitAffordance = question => {
        if (!question) return;
        const button = question.querySelector('.interview-submit');
        if (!button) return;
        const direct = isDirectM03Candidate(question);
        button.setAttribute('aria-label', direct ? 'Guardar y continuar' : 'Interpretar respuesta');
        button.title = direct ? 'Guardar esta respuesta y continuar a la siguiente pregunta' : 'Interpretar respuesta';
        button.dataset.directMode = direct ? '1' : '0';
    };
    const updateM03PreviousButton = () => {
        const current = questions[active];
        const previousIndex = [...questions].map((question, index) => ({question, index}))
            .filter(item => item.index < active && item.question.dataset.eligible === '1' && hasConfirmedAnswer(item.question))
            .pop()?.index;
        const previous = previousIndex === undefined ? null : questions[previousIndex];
        const available = conversationStages[conversationStage]?.type === 'm03'
            && current?.dataset.m03WriteEnabled === '1'
            && hasConfirmedAnswer(previous);
        document.querySelectorAll('[data-m03-previous]').forEach(button => {
            const owner = button.closest('.patient-interview-question');
            const visible = available && owner === current;
            button.hidden = !visible;
            button.disabled = !visible;
        });
    };
    const showNextM03Question = (question, nextQuestionId = null, finalized = false) => {
        const next = nextQuestionId
            ? questions.find(candidate => Number(candidate.dataset.questionId) === Number(nextQuestionId)
                && candidate.dataset.eligible === '1'
                && !hasConfirmedAnswer(candidate))
            : null;
        if (next) { showQuestion(questions.indexOf(next)); return; }
        const pending = questions.findIndex(candidate => !hasConfirmedAnswer(candidate) && candidate.dataset.eligible === '1');
        if (pending >= 0) { showQuestion(pending); return; }
        if (finalized !== true) { setStatus('Respuesta M03 guardada. El servidor aún no confirmó el cierre del módulo; permanece aquí.'); return; }
        setStatus('M03 completado.');
        advanceConversation();
    };
    const saveAndAdvanceM03Direct = async (question, value, directMode) => {
        if (!question || question.dataset.m03Saving === '1' || question.dataset.m03Interpreting === '1') return;
        question.dataset.m03Saving = '1';
        const controls = question.querySelectorAll('button, input');
        controls.forEach(control => { control.disabled = true; });
        setStatus('Guardando y continuando…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-directo', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({
                    idPaciente: Number(main.dataset.patient),
                    idPregunta: Number(question.dataset.questionId),
                    detalle: value,
                    expectedVersion: question.dataset.m03Version || '',
                    saveAndContinue: true,
                    directMode,
                    allowEdit: hasConfirmedAnswer(question)
                })
            });
            const data = await response.json();
            if (!response.ok || !data.resultado || data.mode !== 'direct' || data.readback !== true) {
                throw new Error(data.mensaje || 'No fue posible guardar la respuesta.');
            }
            question.dataset.savedValue = value;
            question.dataset.semanticState = String(data.semantic_state || '').toUpperCase();
            if (data.version) questions.forEach(item => { item.dataset.m03Version = data.version; });
            const message = question.querySelector('[data-confirmed-message]');
            if (message) { message.textContent = `Respuesta confirmada: ${answerLabel(question)}`; message.hidden = false; }
            setStatus('Respuesta guardada. Siguiente pregunta…');
             showNextM03Question(question, data.next_question_id, data.finalized === true);
        } catch (error) {
            setStatus(error.message || 'No fue posible guardar la respuesta.');
        } finally {
            controls.forEach(control => { control.disabled = false; });
            question.dataset.m03Saving = '0';
        }
    };
    const confirmAndSave = async (question, value, proposalBox = null, directContract = false, semanticState = '') => {
        const normalizedState = String(semanticState || '').toUpperCase();
        const stateWithoutValue = ['UNKNOWN', 'NOT_REMEMBERED'].includes(normalizedState);
        if (!value && !stateWithoutValue) { setStatus('Selecciona o escribe una respuesta antes de continuar.'); return; }
        const buttons = question.querySelectorAll('button, input');
        buttons.forEach(button => { button.disabled = true; });
        setStatus('Guardando tu respuesta…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({ idPaciente: Number(main.dataset.patient), idPregunta: Number(question.dataset.questionId), idTema: Number(question.dataset.themeId), detalle: value || '', estadoRespuesta: normalizedState, expectedVersion: question.dataset.m03Version || '', expectedRespuesta: question.dataset.savedValue || '' })
            });
            const data = await response.json();
            if (!response.ok || !data.resultado || (directContract && data.readback !== true)) {
                throw new Error(data.mensaje || 'No fue posible verificar la respuesta guardada.');
            }
            question.dataset.savedValue = value;
            question.dataset.semanticState = String(data.semantic_state || normalizedState).toUpperCase();
            if (data.version) questions.forEach(item => { item.dataset.m03Version = data.version; });
            const message = question.querySelector('[data-confirmed-message]');
            if (message) { message.textContent = `Paciente: ${answerLabel(question)}`; message.hidden = false; }
            if (proposalBox) proposalBox.hidden = true;
            setStatus('Respuesta guardada en tu Historia Clínica.');
             showNextM03Question(question, null, data.finalized === true);
        } catch (error) {
            setStatus(error.message || 'No fue posible guardar la respuesta.');
        } finally { buttons.forEach(button => { button.disabled = false; }); }
    };
    const interpret = async question => {
        const utterance = valueFor(question);
        if (!utterance) { setStatus('Escribe una respuesta antes de continuar.'); return; }
        if (question.dataset.m03Interpreting === '1' || question.dataset.m03Saving === '1') return;
        const directMode = directModeForM03(question);
        if (directMode) return saveAndAdvanceM03Direct(question, utterance, directMode);
        question.dataset.m03Interpreting = '1';
        const controls = question.querySelectorAll('button, input');
        controls.forEach(control => { control.disabled = true; });
        setStatus('Interpretando tu respuesta…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), idPregunta: Number(question.dataset.questionId), idTema: Number(question.dataset.themeId), modulo:'M03', utterance})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible interpretar la respuesta.');
            const proposal = data.proposal || {};
            if (proposal.kind === 'explanation') {
                setStatus(proposal.explanation || 'Puedes responder la pregunta cuando estés preparado. No se guardó ningún dato.');
                return;
            }
            if (proposal.needs_clarification) {
                const unknownStorageUnavailable = proposal.unknown_response
                    && proposal.unknown_response.persist === false
                    && proposal.unknown_response.never_infer_negative === true;
                setStatus(unknownStorageUnavailable
                    ? 'No se guardó el dato desconocido ni se interpretó como “No”. La pregunta permanece pendiente.'
                    : (proposal.clarification || 'Necesito una aclaración antes de guardar la respuesta.'));
                return;
            }
            if (proposal.direct_save === true && proposal.can_confirm === true) {
                // The server has classified this deterministic catalog value as
                // directly admissible. Reuse the normal capture endpoint so
                // CSRF, authorization, versioning and readback remain active.
                await confirmAndSave(question, proposal.structured_value || '', null, true, proposal.semantic_state || '');
                return;
            }
            setStatus(proposal.clarification || 'La respuesta no puede guardarse todavía; precisa la información solicitada.');
        } catch (error) {
            setStatus(error.message || 'No fue posible interpretar la respuesta.');
        } finally { controls.forEach(control => { control.disabled = false; }); question.dataset.m03Interpreting = '0'; }
    };
    document.querySelectorAll('[data-m03-quick-yes]').forEach(button => button.addEventListener('click', () => {
        const question = button.closest('.patient-interview-question');
        const input = question?.querySelector('.interview-text');
        if (!question || !input || question.dataset.m03Interpreting === '1' || question.dataset.m03Saving === '1') return;
        if (input.value.trim() !== '' && input.value.trim() !== 'Sí') {
            setStatus('Ya existe una respuesta escrita. No se reemplazó; revísala o bórrala antes de usar Sí.');
            input.focus();
            return;
        }
        input.value = 'Sí';
        interpret(question);
    }));
    const captureSafeM03No = async (question, button) => {
        if (!question || question.dataset.m03Saving === '1' || question.dataset.m03Interpreting === '1') return;
        question.dataset.m03Saving = '1';
        const controls = question.querySelectorAll('button, input');
        controls.forEach(control => { control.disabled = true; });
        setStatus('Guardando respuesta negativa…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({
                    idPaciente: Number(main.dataset.patient),
                    idPregunta: Number(question.dataset.questionId),
                    idTema: Number(question.dataset.themeId),
                    detalle: 'No',
                    expectedVersion: question.dataset.m03Version || '',
                    expectedRespuesta: question.dataset.savedValue || ''
                })
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible guardar la respuesta negativa.');
            question.dataset.savedValue = 'No';
            question.dataset.semanticState = String(data.semantic_state || 'NEGATIVE').toUpperCase();
            if (data.version) questions.forEach(item => { item.dataset.m03Version = data.version; });
            const message = question.querySelector('[data-confirmed-message]');
            if (message) { message.textContent = 'Respuesta confirmada: No'; message.hidden = false; }
            setStatus('Respuesta negativa guardada.');
             showNextM03Question(question, null, data.finalized === true);
        } catch (error) {
            setStatus(error.message || 'No fue posible guardar la respuesta negativa. Puedes reintentar.');
        } finally {
            controls.forEach(control => { control.disabled = false; });
            question.dataset.m03Saving = '0';
        }
    };
    document.querySelectorAll('[data-m03-safe-no]').forEach(button => button.addEventListener('click', () => {
        captureSafeM03No(button.closest('.patient-interview-question'), button);
    }));
    document.querySelectorAll('.interview-submit').forEach(button => button.addEventListener('click', () => {
        const question = button.closest('.patient-interview-question');
        interpret(question);
    }));
    document.querySelectorAll('[data-m03-previous]').forEach(button => button.addEventListener('click', () => {
        const previousIndex = [...questions].map((question, index) => ({question, index}))
            .filter(item => item.index < active && item.question.dataset.eligible === '1' && hasConfirmedAnswer(item.question))
            .pop()?.index;
        const previous = previousIndex === undefined ? null : questions[previousIndex];
        if (!previous || !hasConfirmedAnswer(previous) || previous.dataset.m03WriteEnabled !== '1') return;
        if (!conversationCoordinator.goBack()) { setStatus('No se puede volver mientras existe una operación pendiente.'); return; }
        showQuestion(previousIndex);
        const input = previous.querySelector('.interview-text');
        if (input) { input.value = previous.dataset.savedValue || ''; input.focus(); }
        const confirmed = previous.querySelector('[data-confirmed-message]');
        if (confirmed) confirmed.hidden = true;
        updateM03SubmitAffordance(previous);
        setStatus('');
    }));
    document.querySelectorAll('.interview-text').forEach(input => input.addEventListener('keydown', event => {
        if (event.key !== 'Enter' || event.shiftKey) return;
        event.preventDefault();
        interpret(input.closest('.patient-interview-question'));
    }));
    document.querySelectorAll('.interview-text').forEach(input => input.addEventListener('input', () => {
        const question = input.closest('.patient-interview-question');
        const confirmed = question?.querySelector('[data-confirmed-message]');
        if (confirmed && input.value.trim() !== (question?.dataset.savedValue || '').trim()) confirmed.hidden = true;
        updateM03SubmitAffordance(question);
        updateM03AnswerCount(question);
    }));
    questions.forEach(question => { updateM03SubmitAffordance(question); updateM03AnswerCount(question); });
    document.querySelectorAll('.interview-composer input[type="text"]:not(.interview-text)').forEach(input => input.addEventListener('keydown', event => {
        if (event.key !== 'Enter' || event.shiftKey) return;
        event.preventDefault();
        input.closest('.interview-composer')?.querySelector('[data-m02-interpret], [data-m04-interpret], [data-m05-interpret], [data-m05-v2-interpret], [data-m06-interpret], [data-m08-interpret], [data-m09-interpret]')?.click();
    }));
    document.querySelectorAll('[data-m01-interpret]').forEach(button => button.addEventListener('click', async () => {
        const field = button.closest('[data-m01-field]');
        const input = field.querySelector('[data-m01-input]');
        const result = field.querySelector('[data-m01-result]');
        const utterance = input ? input.value.trim() : '';
        if (!utterance) { setStatus('Escribe una propuesta M01 para revisarla.'); return; }
        button.disabled = true;
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), modulo: 'M01', campo: field.dataset.m01Field, utterance})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible revisar la propuesta M01.');
            if (result) { result.textContent = data.proposal.message || 'Propuesta solo informativa; no se guardó.'; result.hidden = false; }
            setStatus('M01 permanece sin cambios. La propuesta solo se mostró para revisión.');
        } catch (error) {
            setStatus(error.message || 'No fue posible revisar la propuesta M01.');
        } finally { button.disabled = false; }
    }));
    const saveM02Proposal = async (card, proposal, trigger = null) => {
        if (!card || card.dataset.m02Saving === '1') return;
        card.dataset.m02Saving = '1';
        if (trigger) trigger.disabled = true;
        setStatus('Guardando respuesta…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m02', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), enfermedad: card.dataset.m02Disease, expectedVersion: card.dataset.m02Version, proposal})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible guardar M02.');
            const result = card.querySelector('[data-m02-result]');
            if (result) { result.textContent = 'Respuesta M02 guardada.'; result.hidden = false; }
            card.dataset.m02Version = data.record && data.record.version ? data.record.version : card.dataset.m02Version;
            card.removeAttribute('data-m02-context');
            card.removeAttribute('data-m02-proposal');
            setStatus('Respuesta M02 guardada.');
             advanceM02(card, data.finalized === true);
        } catch (error) {
            setStatus(error.message || 'No fue posible guardar M02.');
        } finally {
            if (trigger) trigger.disabled = false;
            card.dataset.m02Saving = '0';
        }
    };
    const interpretM02 = async (card, trigger = null) => {
        const input = card.querySelector('[data-m02-input]');
        const result = card.querySelector('[data-m02-result]');
        const utterance = input ? input.value.trim() : '';
        if (!utterance) { setStatus('Escribe una respuesta M02 para revisarla.'); return; }
        if (card.dataset.m02Interpreting === '1') return;
        card.dataset.m02Interpreting = '1';
        if (trigger) trigger.disabled = true;
        setStatus('Interpretando tu respuesta…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), modulo: 'M02', enfermedad: card.dataset.m02Disease, utterance, context: JSON.parse(card.dataset.m02Context || '{}')})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible revisar M02.');
            const proposal = data.proposal || {};
            card.dataset.m02Proposal = JSON.stringify(proposal);
            card.dataset.m02Context = JSON.stringify(data.context || proposal.context || {});
            if (proposal.kind === 'clarification' || proposal.needs_clarification) {
                if (result) { result.textContent = proposal.clarification || proposal.message || 'M02 solicita una aclaración.'; result.hidden = false; }
                setStatus(proposal.clarification || 'M02 solicita una aclaración. No se guardó ningún dato.');
                return;
            }
            if (proposal.direct_save === true && proposal.can_confirm === true) {
                await saveM02Proposal(card, proposal, trigger);
                return;
            }
            if (result) { result.textContent = proposal.message || 'M02 no pudo validar la respuesta.'; result.hidden = false; }
            setStatus('M02 no pudo validar la respuesta. No se guardó ningún dato.');
        } catch (error) {
            setStatus(error.message || 'No fue posible revisar M02.');
        } finally { if (trigger) trigger.disabled = false; card.dataset.m02Interpreting = '0'; }
    };
    document.querySelectorAll('[data-m02-interpret]').forEach(button => button.addEventListener('click', () => {
        interpretM02(button.closest('[data-m02-disease]'), button);
    }));
    document.querySelectorAll('[data-m02-input]').forEach(input => input.addEventListener('input', () => {
        const card = input.closest('[data-m02-disease]');
        const interpretButton = card?.querySelector('[data-m02-interpret]');
        const hasText = input.value.trim() !== '';
        if (interpretButton) interpretButton.hidden = !hasText;
        if (card?.dataset.m02Proposal && card.dataset.m02LastInput !== input.value) {
            card.removeAttribute('data-m02-proposal');
            setStatus('La respuesta cambió. Vuelve a enviarla para validarla; todavía no se guardó ningún dato.');
        }
        if (card) card.dataset.m02LastInput = input.value;
    }));
    document.querySelectorAll('[data-m02-input]').forEach(input => input.dispatchEvent(new Event('input')));
    document.querySelectorAll('[data-m02-quick]').forEach(button => button.addEventListener('click', () => {
        const card = button.closest('[data-m02-disease]');
        const input = card?.querySelector('[data-m02-input]');
        if (!input) return;
        const value = button.dataset.value || '';
        if (input.value.trim() !== '' && input.value.trim() !== value) {
            setStatus('Ya existe una respuesta escrita. No se reemplazó; revísala o bórrala antes de usar una respuesta rápida.');
            input.focus();
            return;
        }
        input.value = value;
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.focus();
        const interpretButton = card.querySelector('[data-m02-interpret]');
        interpretM02(card, interpretButton);
    }));
    document.querySelectorAll('[data-m02-safe-no]').forEach(button => button.addEventListener('click', async () => {
        const card = button.closest('[data-m02-disease]');
        const module = card?.closest('[data-module="M02"]');
        if (!card || !module || module.dataset.m02WriteEnabled !== '1' || card.dataset.m02Saving === '1') return;
        const input = card.querySelector('[data-m02-input]');
        if ((input && input.value.trim() !== '') || card.dataset.m02Proposal) {
            setStatus('Hay una respuesta o propuesta pendiente. Revísala o cancélala antes de guardar una respuesta negativa.');
            return;
        }
        card.dataset.m02Saving = '1';
        const controls = card.querySelectorAll('button, input');
        controls.forEach(control => { control.disabled = true; });
        setStatus('Guardando respuesta negativa…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m02', {method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf}, body:JSON.stringify({idPaciente:Number(main.dataset.patient), enfermedad:card.dataset.m02Disease, expectedVersion:card.dataset.m02Version, fastNo:true})});
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible guardar la respuesta.');
            const result = card.querySelector('[data-m02-result]');
            if (result) { result.textContent = 'Respuesta guardada.'; result.hidden = false; }
            card.removeAttribute('data-m02-context'); card.removeAttribute('data-m02-proposal');
            setStatus('Respuesta guardada.');
             advanceM02(card, data.finalized === true);
        } catch (error) {
            setStatus(error.message || 'No fue posible guardar la respuesta. Puedes reintentar.');
        } finally {
            card.dataset.m02Saving = '0';
            controls.forEach(control => { control.disabled = false; });
        }
    }));
    document.querySelectorAll('[data-m02-defer]').forEach(button => button.addEventListener('click', () => {
        const row = button.closest('[data-m02-disease]');
        const module = row?.closest('[data-module="M02"]');
        if (!row || !module || module.dataset.m02WriteEnabled === '0') { setStatus('M02 requiere responder antes de continuar.'); return; }
        row.removeAttribute('data-m02-context');
        row.removeAttribute('data-m02-proposal');
        setStatus('Pregunta M02 pendiente. No se guardó ni se convirtió en «No».');
        advanceM02(row);
    }));
    const M04_CONTINUATION_QUESTION = '¿Quieres agregar otra cirugía?';
    const M04_CONTINUATION_INSTRUCTION = 'Puedes escribir otra cirugía o seleccionar «No, continuar» para pasar al siguiente módulo.';
    const M04_CONTINUATION_VOICE = 'Tu cirugía quedó registrada. Puedes agregar otra cirugía o seleccionar No, continuar para pasar al siguiente módulo.';
    const updateM04Continuation = card => {
        const question = card?.querySelector('[data-m04-question-title]');
        const instruction = card?.querySelector('[data-m04-question-instruction]');
        if (question) question.textContent = M04_CONTINUATION_QUESTION;
        if (instruction) instruction.textContent = M04_CONTINUATION_INSTRUCTION;
        if (card) card.dataset.m04Continuation = '1';
    };
    const speakM04Continuation = (card, version) => {
        const key = String(version || 'readback');
        if (!card || card.dataset.m04ContinuationVoiceKey === key) return;
        const target = String(card.dataset.m04StableId || conversationCoordinator.snapshot().currentTarget || '').trim();
        if (!target || target !== conversationCoordinator.snapshot().currentTarget) return;
        card.dataset.m04ContinuationVoiceKey = key;
        const snapshot = conversationCoordinator.snapshot();
        voiceController.speakEvent({
            type: 'QUESTION',
            stable_id: target,
            text: M04_CONTINUATION_VOICE,
            state: snapshot.state,
            patient_id: null,
            context: 'M04',
            revision: snapshot.revision,
            operation_generation: snapshot.sequence,
        }, {automatic: true});
    };
    const saveM04Proposal = async (card, proposal, trigger = null) => {
        if (!card || card.dataset.m04Saving === '1') return;
        card.dataset.m04Saving = '1';
        const input = card.querySelector('[data-m04-input]');
        if (trigger) trigger.disabled = true;
        setStatus('Guardando respuesta M04…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m04', {
                method: 'POST', headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), expectedVersion: card.dataset.m04Version, proposal})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible guardar M04.');
            card.dataset.m04Version = data.version || data.record?.version || card.dataset.m04Version;
            if (data.negative_declaration?.estado === 'NEGATIVE_DECLARED') card.dataset.m04NegativeConfirmed = '1';
            const result = card.querySelector('[data-m04-result]');
            const isNegative = proposal.kind === 'm04_negative_proposal';
            if (!isNegative && data.readback !== true) throw new Error('La cirugía se guardó, pero el servidor no confirmó su lectura posterior. La pregunta permanece activa.');
            if (isNegative && data.finalized !== true) throw new Error('La declaración se guardó, pero M04 no quedó finalizado. La pregunta permanece activa.');
            const savedMessage = isNegative
                ? 'Declaración negativa M04 guardada.'
                : 'Tu cirugía quedó registrada. Puedes agregar otra cirugía o seleccionar «No, continuar» para pasar al siguiente módulo.';
            if (result) { result.textContent = savedMessage; result.hidden = false; }
            setStatus(savedMessage);
            if (isNegative) {
                advanceConversation();
            } else {
                updateM04Continuation(card);
                speakM04Continuation(card, data.version || data.record?.version || 'readback');
                conversationCoordinator.transition('ASKING', {reason: 'm04_additional_surgery'});
                if (input) {
                    input.value = '';
                    input.focus();
                }
            }
        } catch (error) { setStatus(error.message || 'No fue posible guardar M04.'); }
        finally { if (trigger) trigger.disabled = false; card.dataset.m04Saving = '0'; }
    };
    const finishM04 = async (card, trigger = null) => {
        if (!card || card.dataset.m04Saving === '1' || card.dataset.m04Interpreting === '1') return;
        card.dataset.m04Saving = '1';
        if (trigger) trigger.disabled = true;
        setM04ControlsDisabled(card, true);
        setStatus('Confirmando cierre de M04…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m04', {
                method: 'POST', headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), expectedVersion: card.dataset.m04Version, intent: 'FINISH_ADDITIONAL_SURGERIES'})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible finalizar M04.');
            if (data.finalized !== true || (data.finalization_readback !== true && data.readback !== true)) {
                throw new Error('M04 no confirmó el cierre en el servidor. La pregunta permanece activa.');
            }
            card.dataset.m04Version = data.version || card.dataset.m04Version;
            const result = card.querySelector('[data-m04-result]');
            if (result) { result.textContent = 'M04 finalizado y confirmado.'; result.hidden = false; }
            setStatus('M04 finalizado y confirmado.');
            advanceConversation();
        } catch (error) { setStatus(error.message || 'No fue posible finalizar M04.'); }
        finally { setM04ControlsDisabled(card, false); if (trigger) trigger.disabled = false; card.dataset.m04Saving = '0'; }
    };
    const setM04ControlsDisabled = (card, disabled) => {
        card?.querySelectorAll('[data-m04-input], [data-m04-interpret], [data-m04-finish], [data-m04-quick-response]').forEach(control => { control.disabled = disabled; });
    };
    const interpretM04 = async (card, trigger = null) => {
        if (!card || card.dataset.m04Interpreting === '1' || card.dataset.m04Saving === '1') return;
        const input = card.querySelector('[data-m04-input]');
        const result = card.querySelector('[data-m04-result]');
        const utterance = input ? input.value.trim() : '';
        if (!utterance) { setStatus('Escribe una respuesta M04 para revisarla.'); return; }
        card.dataset.m04Interpreting = '1';
        setM04ControlsDisabled(card, true);
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar', {
                method: 'POST', headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), modulo: 'M04', utterance})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible revisar M04.');
            const proposal = data.proposal || {};
            if (result) {
                result.textContent = proposal.clarification || proposal.message || '';
                result.hidden = false;
            }
            if (proposal.kind === 'm04_negative_review') {
                const limitation = 'Respuesta «No» reconocida, pero no se pudo preparar un estado durable. La pregunta permanece pendiente.';
                if (result) result.textContent = limitation;
                setStatus(limitation);
                return;
            }
            if (proposal.kind === 'clarification' || proposal.needs_clarification) {
                setStatus(proposal.clarification || 'M04 solicita una aclaración. No se guardó ningún dato.');
                return;
            }
            if (proposal.direct_save === true && proposal.can_confirm === true) {
                await saveM04Proposal(card, proposal, trigger);
                return;
            }
            setStatus(proposal.message || 'M04 no pudo validar la respuesta. No se guardó ningún dato.');
        } catch (error) { setStatus(error.message || 'No fue posible revisar M04.'); }
        finally { setM04ControlsDisabled(card, false); card.dataset.m04Interpreting = '0'; }
    };
    document.querySelectorAll('[data-m04-interpret]').forEach(button => button.addEventListener('click', () => {
        interpretM04(button.closest('[data-module="M04"]'), button);
    }));
    document.querySelectorAll('[data-m04-finish]').forEach(button => button.addEventListener('click', () => {
        const card = button.closest('[data-module="M04"]');
        if (!card || button.dataset.intent !== 'FINISH_ADDITIONAL_SURGERIES') return;
        finishM04(card, button);
    }));
    const showNextM05Question = (card, next) => {
         if (!next) { setStatus('Respuesta M05 guardada. El servidor aún no confirmó el cierre del módulo; permanece aquí.'); return; }
        card.dataset.m05PendingQuestion = String(next.question_id || 0);
        const theme = card.querySelector('[data-m05-theme-label]');
        const question = card.querySelector('[data-m05-question-label]');
        if (theme) theme.textContent = next.tema || 'Pregunta M05';
        if (question) question.textContent = next.pregunta || '';
        const input = card.querySelector('[data-m05-input]'); if (input) input.value = '';
        const result = card.querySelector('[data-m05-result]'); if (result) { result.textContent = ''; result.hidden = true; }
        card.removeAttribute('data-m05-proposal');
        setStatus('Respuesta M05 guardada. Continúa con la siguiente pregunta.');
    };
    const showNextM05Disease = (card, next) => {
         if (!next) { setStatus('Respuesta M05 V2 guardada. El servidor aún no confirmó el cierre del módulo; permanece aquí.'); return; }
        card.dataset.m05V2Disease = String(next.disease_id || 0);
        const label = card.querySelector('[data-m05-v2-disease-label]'); if (label) label.textContent = next.enfermedad || 'Enfermedad M05';
        const input = card.querySelector('[data-m05-v2-input]'); if (input) input.value = '';
        const result = card.querySelector('[data-m05-v2-result]'); if (result) { result.textContent = ''; result.hidden = true; }
        card.removeAttribute('data-m05Proposal');
        setStatus('Respuesta M05 V2 guardada. Continúa con la siguiente enfermedad.');
    };
    const saveM05Proposal = async (card, proposal, target, trigger = null) => {
        if (!card || card.dataset.m05Saving === '1') return;
        card.dataset.m05Saving = '1';
        if (trigger) trigger.disabled = true;
        setStatus('Guardando respuesta M05…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m05', {method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),expectedVersion:card.dataset.m05Version,proposal})});
            const data = await response.json();
            if(!response.ok||!data.resultado) throw new Error(data.mensaje||'No fue posible guardar M05.');
            card.dataset.m05Version=data.version||card.dataset.m05Version;
             if (target === 'v2') { if (data.next_disease) showNextM05Disease(card,data.next_disease); else if (data.finalized === true) advanceConversation(); else setStatus('Respuesta M05 V2 guardada. El servidor aún no confirmó el cierre del módulo; permanece aquí.'); } else { if (data.next_question) showNextM05Question(card,data.next_question); else if (data.finalized === true) advanceConversation(); else setStatus('Respuesta M05 guardada. El servidor aún no confirmó el cierre del módulo; permanece aquí.'); }
        } catch(error){setStatus(error.message||'No fue posible guardar M05.');}
        finally{if (trigger) trigger.disabled=false;card.dataset.m05Saving='0';}
    };
    document.querySelectorAll('[data-m05-interpret]').forEach(button => button.addEventListener('click', async () => {
        const card=button.closest('[data-module="M05"]'), input=card.querySelector('[data-m05-input]'), result=card.querySelector('[data-m05-result]');
        const utterance=input?.value.trim() || ''; if(!utterance){setStatus('Escribe una respuesta M05 para revisarla.');return;} button.disabled=true;
        try { const qid=Number(card.dataset.m05PendingQuestion||0); const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),idPregunta:qid,modulo:'M05',utterance})}); const data=await response.json(); if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible validar M05.'); const proposal=data.proposal||{}; if(proposal.kind==='clarification'||proposal.needs_clarification){if(result){result.textContent=proposal.clarification||'M05 solicita una aclaración.';result.hidden=false;}setStatus(proposal.clarification||'M05 solicita una aclaración. No se guardó ningún dato.');return;} if(proposal.direct_save===true&&proposal.can_confirm===true){await saveM05Proposal(card,proposal,'v1',button);return;} if(result){result.textContent=proposal.message||'M05 no pudo validar la respuesta.';result.hidden=false;}setStatus('M05 no pudo validar la respuesta. No se guardó ningún dato.'); } catch(error){setStatus(error.message||'No fue posible validar M05.');} finally{button.disabled=false;}
    }));
    document.querySelectorAll('[data-m05-v2-interpret]').forEach(button => button.addEventListener('click', async () => { const card=button.closest('[data-module="M05-V2"]'), utterance=card.querySelector('[data-m05-v2-input]')?.value.trim()||'', result=card.querySelector('[data-m05-v2-result]'); if(!utterance){setStatus('Escribe una respuesta M05 V2.');return;} button.disabled=true; try{const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),idPregunta:1,enfermedad_id:Number(card.dataset.m05V2Disease||0),modulo:'M05',utterance})});const data=await response.json();if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible validar M05 V2.');const proposal=data.proposal||{};if(proposal.kind==='clarification'||proposal.needs_clarification){if(result){result.textContent=proposal.clarification||'M05 V2 solicita una aclaración.';result.hidden=false;}setStatus(proposal.clarification||'M05 V2 solicita una aclaración. No se guardó ningún dato.');return;}if(proposal.direct_save===true&&proposal.can_confirm===true){await saveM05Proposal(card,proposal,'v2',button);return;}if(result){result.textContent=proposal.message||'M05 V2 no pudo validar la respuesta.';result.hidden=false;}setStatus('M05 V2 no pudo validar la respuesta. No se guardó ningún dato.');}catch(error){setStatus(error.message||'No fue posible validar M05 V2.');}finally{button.disabled=false;}}));
    const saveM06Proposal = async (card, proposal, trigger = null) => {
        if (!card || card.dataset.m06Saving === '1') return;
        card.dataset.m06Saving = '1';
        if (trigger) trigger.disabled = true;
        setStatus('Guardando respuesta M06…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m06',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),expectedVersion:card.dataset.m06Version,proposal})});
            const data = await response.json();
            if(!response.ok||!data.resultado) throw new Error(data.mensaje||'No fue posible guardar M06.');
            if(data.finalized!==true){setStatus('Respuesta M06 guardada. Aún hay información pendiente; permanece en este módulo.');return;}
            card.dataset.m06Version=data.version||card.dataset.m06Version;
            setStatus('Medicación M06 guardada.');
            advanceConversation();
        }catch(error){setStatus(error.message||'No fue posible guardar M06.');}
        finally{if(trigger)trigger.disabled=false;card.dataset.m06Saving='0';}
    };
    document.querySelectorAll('[data-m06-interpret]').forEach(button => button.addEventListener('click', async () => { const card=button.closest('[data-module="M06"]'),utterance=card.querySelector('[data-m06-input]')?.value.trim()||'',result=card.querySelector('[data-m06-result]');if(!utterance){setStatus('Escribe los datos de M06.');return;}button.disabled=true;let context={};try{context=JSON.parse(card.dataset.m06Context||'{}');}catch(error){context={};}try{const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),modulo:'M06',utterance,context})});const data=await response.json();if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible validar M06.');const proposal=data.proposal||{};if(proposal.kind==='clarification'||proposal.needs_clarification){if(result){result.textContent=proposal.clarification||'M06 solicita una aclaración.';result.hidden=false;}setStatus(proposal.clarification||'M06 solicita una aclaración. No se guardó ningún dato.');return;}if(proposal.direct_save===true&&proposal.can_confirm===true){await saveM06Proposal(card,proposal,button);return;}if(result){result.textContent=proposal.message||'M06 no pudo validar los datos.';result.hidden=false;}setStatus('M06 no pudo validar los datos. No se guardó ningún dato.');}catch(error){setStatus(error.message||'No fue posible validar M06.');}finally{button.disabled=false;}}));
    const m07Card = document.querySelector('[data-module="M07"]');
    const m07Result = m07Card?.querySelector('[data-m07-message]');
    const m07SetResult = message => { if (m07Result) { m07Result.textContent = message || ''; m07Result.hidden = !message; } };
    const m07PayloadFor = item => {
        const result = item.querySelector('[data-m07-result]:checked')?.value || '';
        const consumption = item.querySelector('[data-m07-consumption]:checked')?.value || '';
        const fields = {respuesta:'Si', dosis:item.querySelector('[data-m07-dose]')?.value.trim() || '', resultados:result, consumo:consumption, descripcion:item.querySelector('[data-m07-description]')?.value.trim() || ''};
        if (!fields.dosis || !fields.resultados || !fields.consumo || (fields.resultados === 'Tuve efectos adversos' && !fields.descripcion)) throw new Error('Completa dosis, resultado, consumo y, si aplica, la descripción de efectos adversos.');
        return {question_id:Number(item.dataset.questionId), theme_id:Number(item.dataset.themeId), fields, confirm_clear:true};
    };
    const m07MarkReadback = (items, data) => {
        if (!m07Card) return;
        m07Card.dataset.m07Version = data.version || m07Card.dataset.m07Version;
        const ids = new Set(items.map(item => String(item.question_id)));
        m07Card.querySelectorAll('[data-m07-item]').forEach(item => {
            if (!ids.has(String(item.dataset.questionId))) return;
            item.dataset.status = 'complete';
            item.removeAttribute('data-dirty');
            const badge = item.querySelector('[data-m07-status]'); if (badge) badge.textContent = 'Completo';
        });
        m07Card.querySelectorAll('[data-m07-category]').forEach(category => {
            const pending = Array.from(category.querySelectorAll('[data-m07-item]')).filter(item => item.dataset.status !== 'complete').length;
            category.dataset.categoryPending = String(pending);
            const state = category.querySelector('[data-m07-category-state]'); if (state) state.textContent = pending ? 'Respuestas pendientes en esta categoría.' : 'Categoría revisada.';
        });
        m07SetResult('Respuesta M07 confirmada. El servidor devolvió el estado actualizado; la lectura no crea filas.');
        if (Number(data.pending_items || 0) === 0) advanceConversation();
    };
    const m07Submit = async (items, button) => {
        if (!m07Card || !items.length) { setStatus('Selecciona al menos una respuesta M07.'); return; }
        button.disabled = true;
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m07', {method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf}, body:JSON.stringify({idPaciente:Number(main.dataset.patient), expectedVersion:m07Card.dataset.m07Version, items})});
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible guardar M07.');
            m07MarkReadback(items, data);
            setStatus('M07 guardó únicamente las respuestas confirmadas.');
        } catch (error) { m07SetResult(error.message || 'No fue posible guardar M07.'); setStatus(error.message || 'No fue posible guardar M07.'); }
        finally { button.disabled = false; }
    };
    if (m07Card) {
        const m07Categories = Array.from(m07Card.querySelectorAll('[data-m07-category]'));
        const m07CategoryNav = Array.from(m07Card.querySelectorAll('[data-m07-category-goto]'));
        const m07RefreshCategory = category => {
            if (!category) return;
            const pending = Array.from(category.querySelectorAll('[data-m07-item]')).filter(item => item.dataset.status !== 'complete').length;
            category.dataset.categoryPending = String(pending);
            const state = category.querySelector('[data-m07-category-state]'); if (state) state.textContent = pending ? 'Respuestas pendientes en esta categoría.' : 'Categoría revisada.';
        };
        const m07ShowCategory = index => {
            const bounded = Math.max(0, Math.min(Number(index) || 0, m07Categories.length - 1));
            m07Categories.forEach(category => { category.hidden = Number(category.dataset.categoryIndex) !== bounded; });
            m07CategoryNav.forEach(button => { const active = Number(button.dataset.m07CategoryGoto) === bounded; button.setAttribute('aria-current', active ? 'true' : 'false'); });
            m07Card.dataset.m07ActiveCategory = String(bounded);
            m07Categories[bounded]?.querySelector('[data-m07-used]:not(:disabled)')?.focus({preventScroll:true});
        };
        const m07CategoryCanLeave = category => !category?.querySelector('[data-m07-item][data-dirty="1"]') && Number(category?.dataset.categoryPending || 0) === 0;
        m07Categories.forEach(category => {
            m07RefreshCategory(category);
            category.querySelector('[data-m07-previous-category]')?.addEventListener('click', () => m07ShowCategory(Number(category.dataset.categoryIndex) - 1));
            category.querySelector('[data-m07-next-category]')?.addEventListener('click', () => {
                if (!m07CategoryCanLeave(category)) { setStatus('Confirma las respuestas pendientes de esta categoría antes de continuar.'); return; }
                m07ShowCategory(Number(category.dataset.categoryIndex) + 1);
            });
        });
        m07CategoryNav.forEach(button => button.addEventListener('click', () => {
            const current = m07Categories.find(category => Number(category.dataset.categoryIndex) === Number(m07Card.dataset.m07ActiveCategory));
            const target = Number(button.dataset.m07CategoryGoto);
            if (current && target !== Number(current.dataset.categoryIndex) && !m07CategoryCanLeave(current)) { setStatus('Confirma las respuestas pendientes de la categoría actual antes de cambiar.'); return; }
            m07ShowCategory(target);
        }));
        m07ShowCategory(Number(m07Card.dataset.m07ActiveCategory || <?=$m07FirstCategory;?>));
        const writable = m07Card.dataset.m07WriteEnabled === '1';
        m07Card.querySelectorAll('[data-m07-used]').forEach(input => input.addEventListener('change', () => {
            const item = input.closest('[data-m07-item]'); const details = item?.querySelector('[data-m07-details]'); if (details) details.hidden = !input.checked; if (item) item.dataset.dirty = '1';
        }));
        m07Card.querySelectorAll('[data-m07-result]').forEach(input => input.addEventListener('change', () => {
            const item = input.closest('[data-m07-item]'); const adverse = item?.querySelector('[data-m07-adverse-wrap]'); if (adverse) adverse.hidden = input.value !== 'Tuve efectos adversos'; if (item) item.dataset.dirty = '1';
        }));
        m07Card.querySelectorAll('[data-m07-dose], [data-m07-description], [data-m07-consumption]').forEach(input => input.addEventListener('input', () => { const item = input.closest('[data-m07-item]'); if (item) item.dataset.dirty = '1'; }));
        m07Card.querySelectorAll('[data-m07-save-selected], [data-m07-confirm-negatives]').forEach(button => { button.disabled = !writable; });
        m07Card.querySelectorAll('[data-m07-save-selected]').forEach(button => button.addEventListener('click', async () => {
            try {
                const items = Array.from(m07Card.querySelectorAll('[data-m07-item]')).filter(item => item.querySelector('[data-m07-used]')?.checked).map(m07PayloadFor);
                await m07Submit(items, button);
            } catch (error) { setStatus(error.message || 'Completa la respuesta M07 antes de revisar.'); }
        }));
        m07Card.querySelectorAll('[data-m07-confirm-negatives]').forEach(button => button.addEventListener('click', async () => {
            const items = Array.from(button.closest('[data-m07-category]')?.querySelectorAll('[data-m07-item]') || []).filter(item => item.dataset.status === 'pending' && item.dataset.otherBlocked !== '1' && !item.querySelector('[data-m07-used]')?.checked).map(item => ({question_id:Number(item.dataset.questionId), theme_id:Number(item.dataset.themeId), fields:{respuesta:'No'}, confirm_clear:true}));
            if (!items.length) { setStatus('No hay medicamentos pendientes que puedan confirmarse como no utilizados.'); return; }
            if (!window.confirm('Confirma explícitamente que los medicamentos no seleccionados de esta categoría no fueron utilizados.')) return;
            await m07Submit(items, button);
        }));
    }
    const saveM08Proposal = async (card, proposal, trigger = null) => {
        if (!card || card.dataset.m08Saving === '1') return;
        card.dataset.m08Saving = '1';
        if (trigger) trigger.disabled = true;
        setStatus('Guardando respuesta M08…');
        try { const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m08',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),expectedVersion:card.dataset.m08Version,proposal})});const data=await response.json();if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible guardar M08.');card.dataset.m08Version=data.version||card.dataset.m08Version;if(data.finalized!==true){setStatus('Respuesta M08 guardada. Aún hay tratamientos pendientes; permanece en este módulo.');return;}setStatus('Respuesta M08 guardada.');advanceConversation();}catch(error){setStatus(error.message||'No fue posible guardar M08.');}finally{if(trigger)trigger.disabled=false;card.dataset.m08Saving='0';}
    };
    document.querySelectorAll('[data-m08-interpret]').forEach(button => button.addEventListener('click', async () => {
        const card=button.closest('[data-module="M08"]'), utterance=card.querySelector('[data-m08-input]')?.value.trim()||'', result=card.querySelector('[data-m08-result]');
        if(!utterance){setStatus('Escribe una respuesta M08.');return;} button.disabled=true;
        try{const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),modulo:'M08',tratamiento_id:<?=json_encode((int)($m08Pending['id_tratamiento']??0));?>,utterance})});const data=await response.json();if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible validar M08.');const proposal=data.proposal||{};if(proposal.kind==='clarification'||proposal.needs_clarification){if(result){result.textContent=proposal.clarification||'M08 solicita una aclaración.';result.hidden=false;}setStatus(proposal.clarification||'M08 solicita una aclaración. No se guardó ningún dato.');return;}if(proposal.direct_save===true&&proposal.can_confirm===true){await saveM08Proposal(card,proposal,button);return;}if(result){result.textContent=proposal.message||'M08 no pudo validar la respuesta.';result.hidden=false;}setStatus('M08 no pudo validar la respuesta. No se guardó ningún dato.');}catch(error){setStatus(error.message||'No fue posible validar M08.');}finally{button.disabled=false;}
    }));
    const saveM09Proposal = async (card, proposal, trigger = null) => {
        if (!card || card.dataset.m09Saving === '1') return;
        card.dataset.m09Saving = '1';
        if (trigger) trigger.disabled = true;
        setStatus('Guardando respuesta M09…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m09', {method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf}, body:JSON.stringify({idPaciente:Number(main.dataset.patient), expectedVersion:card.dataset.m09Version, proposal})});
            const data = await response.json(); if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible guardar M09.');
            if (data.finalized !== true) throw new Error('La evaluación se guardó, pero M09 no quedó finalizado. La evaluación permanece activa.');
            card.dataset.m09Version = data.version || card.dataset.m09Version;
            setStatus('Evaluación M09 guardada.'); advanceConversation();
        } catch (error) { setStatus(error.message || 'No fue posible guardar M09.'); }
        finally { if (trigger) trigger.disabled = false; card.dataset.m09Saving = '0'; }
    };
    document.querySelectorAll('[data-m09-interpret]').forEach(button => button.addEventListener('click', async () => {
        const card = button.closest('[data-module="M09"]'), utterance = card.querySelector('[data-m09-input]')?.value.trim() || '', result = card.querySelector('[data-m09-result]');
        if (!utterance) { setStatus('Escribe los datos de M09 para revisarlos.'); return; }
        button.disabled = true;
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar', {method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf}, body:JSON.stringify({idPaciente:Number(main.dataset.patient), modulo:'M09', utterance})});
            const data = await response.json(); if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible validar M09.');
            const proposal = data.proposal || {};
            if (proposal.kind === 'clarification' || proposal.needs_clarification) {
                if (result) { result.textContent = proposal.clarification || 'M09 solicita una aclaración.'; result.hidden = false; }
                setStatus(proposal.clarification || 'M09 solicita una aclaración. No se guardó ningún dato.');
                return;
            }
            if (proposal.direct_save === true && proposal.can_confirm === true) { await saveM09Proposal(card, proposal, button); return; }
            if (result) { result.textContent = proposal.message || 'M09 no pudo validar la respuesta.'; result.hidden = false; }
            setStatus('M09 no pudo validar la respuesta. No se guardó ningún dato.');
        } catch (error) { setStatus(error.message || 'No fue posible revisar M09.'); } finally { button.disabled = false; }
    }));
    const nativeSttEnabled = main.dataset.nativeSttEnabled === '1';
    const recognitionConstructor = window.SpeechRecognition || window.webkitSpeechRecognition;
    const speechRecognitionSupported = typeof recognitionConstructor === 'function';
    let localLanguageAvailability = 'unknown';
    let activeRecognition = null;
    let activeMicButton = null;
    let activeRecognitionState = null;
    let recognitionStartPending = false;
    let recognitionGeneration = 0;
    const setRecognitionStatus = (state, message) => {
        if (typeof main !== 'undefined' && main) main.dataset.sttState = state;
        if (typeof status !== 'undefined' && status) status.dataset.sttState = state;
        setStatus(message);
    };
    const speechApi = ('speechSynthesis' in window) ? window.speechSynthesis : null;
    const voicePanel = document.querySelector('[data-voice-panel]');
    const voiceStatus = voicePanel?.querySelector('[data-voice-status]');
    const voiceStartButton = voicePanel?.querySelector('[data-voice-start]');
    const voiceRepeatButton = voicePanel?.querySelector('[data-voice-repeat]');
    const voicePauseButton = voicePanel?.querySelector('[data-voice-pause]');
    const voiceResumeButton = voicePanel?.querySelector('[data-voice-resume]');
    const voiceMuteButton = voicePanel?.querySelector('[data-voice-mute]');
    const voiceStopButton = voicePanel?.querySelector('[data-voice-stop]');
    const voiceRateControl = voicePanel?.querySelector('[data-voice-rate]');
    const voiceMuteStorageKey = 'clinica-dolor-interview-voice-muted';
    const readVoiceMutePreference = () => { try { return window.sessionStorage?.getItem(voiceMuteStorageKey) === '1'; } catch (error) { return false; } };
    const writeVoiceMutePreference = muted => { try { window.sessionStorage?.setItem(voiceMuteStorageKey, muted ? '1' : '0'); } catch (error) {} };
    const voiceController = new window.PatientInterviewVoiceController({
        speechApi,
        utteranceConstructor: window.SpeechSynthesisUtterance,
        onStateChange: snapshot => {
            if (!voicePanel) return;
            voicePanel.dataset.voiceState = snapshot.state;
            if (voiceStatus) voiceStatus.textContent = ({DISABLED:'Activando guía por voz', READY:'Guía por voz lista', SPEAKING:'Hablando', AUDIO_PENDING:'Activando audio…', AUDIO_BLOCKED:'El navegador necesita una interacción para activar el audio.', PAUSED:'Narración pausada', MUTED:'Narración silenciada', VOICE_UNAVAILABLE:'Voz no disponible', VOICE_ERROR:'Error de reproducción'})[snapshot.state] || snapshot.state;
            if (voiceStartButton) { voiceStartButton.hidden = snapshot.enabled && !snapshot.audioBlocked && !snapshot.audioPending; voiceStartButton.textContent = snapshot.audioBlocked || snapshot.audioPending ? 'Activar audio' : 'Activar guía por voz'; }
            if (voiceRepeatButton) voiceRepeatButton.hidden = !snapshot.enabled || !snapshot.lastEvent;
            if (voicePauseButton) voicePauseButton.hidden = !snapshot.speaking;
            if (voiceResumeButton) voiceResumeButton.hidden = !snapshot.paused;
            if (voiceMuteButton) { voiceMuteButton.hidden = !snapshot.enabled; voiceMuteButton.textContent = snapshot.muted ? 'Activar sonido' : 'Silenciar'; voiceMuteButton.setAttribute('aria-label', snapshot.muted ? 'Activar sonido de la guía por voz' : 'Silenciar guía por voz'); }
            if (voiceStopButton) voiceStopButton.hidden = !snapshot.enabled || (!snapshot.speaking && !snapshot.paused);
        },
        onError: error => setStatus(error?.code === 'AUDIO_BLOCKED'
            ? 'El navegador necesita una interacción para activar el audio.'
            : error?.code === 'QUESTION_TEXT_MISSING'
                ? 'No se pudo resolver la pregunta activa para narrarla. Puedes continuar por escrito.'
                : 'La narración tuvo un problema. Puedes continuar con las instrucciones escritas.'),
    });
    voiceController.setMuted(readVoiceMutePreference());
    const speechSupported = () => voiceController.supported();
    const setVoiceButtonState = (button, speaking) => { button.dataset.speaking = speaking ? '1' : '0'; button.textContent = speaking ? '⏹ Detener' : '🔊 Escuchar esta pregunta'; button.title = speaking ? 'Detener lectura' : 'Escuchar esta pregunta en voz alta'; button.setAttribute('aria-label', speaking ? 'Detener lectura' : 'Escuchar esta pregunta en voz alta'); };
    const resetVoiceButtons = () => document.querySelectorAll('.chat-voice-button').forEach(button => setVoiceButtonState(button, false));
    const stopAssistantVoice = () => { voiceController.cancel(); resetVoiceButtons(); };
    const handleVoiceEvent = event => {
        if (!event || ['SAVING', 'SUCCESS', 'ERROR', 'SESSION_EXPIRED'].includes(event.type)) return false;
        if (event.type === 'RESUME') {
            const current = conversationCoordinator.snapshot().currentTarget;
            const question = conversationCoordinator.voiceEvent('QUESTION', current, false);
            return voiceController.speakStartup(event, question, {automatic: voiceController.automaticMode});
        }
        if (event.type !== 'QUESTION') return voiceController.handleEvent(event);
        if (!event.text || !event.stable_id || event.stable_id !== conversationCoordinator.snapshot().currentTarget) return false;
        return voiceController.handleEvent(event);
    };
    const stableTargetForMessage = message => {
        const question = message?.closest('.patient-interview-question');
        if (question?.dataset.questionId) return `M03:question:${question.dataset.questionId}`;
        const card = message?.closest('.interview-module-card');
        const module = card?.dataset.module || '';
        if (module === 'M04') return canonicalStageTarget({card}) || conversationCoordinator.snapshot().currentTarget;
        if (module === 'M05' && card.dataset.m05PendingQuestion && card.dataset.m05PendingQuestion !== '0') return `M05:v1:${card.dataset.m05PendingQuestion}`;
        if (module === 'M05-V2' && card.dataset.m05V2Disease && card.dataset.m05V2Disease !== '0') return `M05:v2:${card.dataset.m05V2Disease}`;
        if (module === 'M06') return `M06:current-medication:${card.dataset.m06PendingRecord || 'new'}`;
        if (module === 'M09-MAP') return 'M09:body-map';
        if (module) return module;
        return conversationCoordinator.snapshot().currentTarget;
    };
    const speakAssistantMessage = (button, target = button?.dataset.voiceTarget) => {
        if (button.dataset.speaking === '1') { stopAssistantVoice(); return; }
        if (target && target !== conversationCoordinator.snapshot().currentTarget) conversationCoordinator.setTarget(target);
        stopAssistantVoice();
        const contractVoice = conversationCoordinator.voiceEvent('QUESTION', target || conversationCoordinator.snapshot().currentTarget, false);
        if (!contractVoice.text) { setStatus('No se pudo resolver la pregunta activa. La captura escrita sigue disponible.'); return; }
        voiceController.speakEvent(contractVoice, {force:true, userGesture:true});
    };
    const showContractHelp = button => {
        const target = button?.dataset.voiceTarget || conversationCoordinator.snapshot().currentTarget;
        const snapshot = conversationCoordinator.snapshot();
        if (!target || target !== snapshot.currentTarget) {
            stopAssistantVoice();
            setStatus('La ayuda ya no corresponde a la pregunta activa. Selecciona la pregunta actual para continuar.');
            return;
        }
        const question = conversationCoordinator.questionContract(target);
        const event = conversationCoordinator.voiceEvent('HELP', target, false);
        stopAssistantVoice();
        if (!question || !event.text) {
            setStatus('No hay una explicación adicional disponible para esta pregunta. Puedes responder por escrito o pedir que se repita.');
            return;
        }
        setStatus(event.text);
        voiceController.speakEvent(event, {force:true, userGesture:true});
    };
    const normalizedSpeechWords = value => String(value || '').toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').split(/\s+/).filter(Boolean);
    const appendSpeechText = (base, addition) => {
        const left = String(base || '').trim();
        const right = String(addition || '').trim();
        if (!right) return left;
        if (!left) return right;
        const leftWords = left.split(/\s+/);
        const rightWords = right.split(/\s+/);
        const leftNormalized = normalizedSpeechWords(left).join(' ');
        const rightNormalized = normalizedSpeechWords(right).join(' ');
        if (leftNormalized.includes(rightNormalized)) return left;
        let overlap = 0;
        const maximum = Math.min(12, leftWords.length, rightWords.length);
        for (let size = maximum; size > 0; size -= 1) {
            const tail = normalizedSpeechWords(leftWords.slice(-size).join(' ')).join(' ');
            const head = normalizedSpeechWords(rightWords.slice(0, size).join(' ')).join(' ');
            if (tail && tail === head) { overlap = size; break; }
        }
        return `${left} ${rightWords.slice(overlap).join(' ')}`.trim();
    };
    const resetMicButton = button => { if (!button) return; button.disabled = false; button.classList.remove('is-listening'); button.textContent = '🎙'; button.title = 'Dictado de voz en español'; button.setAttribute('aria-label', 'Responder mediante micrófono'); };
    const clearRecognitionState = () => { activeRecognition = null; activeMicButton = null; activeRecognitionState = null; };
    const stopRecognition = (abort = true) => {
        recognitionGeneration += 1;
        if (abort) recognitionStartPending = false;
        const recognition = activeRecognition;
        if (recognition) {
            try { abort ? recognition.abort() : recognition.stop(); } catch (error) {}
        }
        if (!abort && activeMicButton) {
            activeMicButton.disabled = true;
            activeMicButton.textContent = '…';
            activeMicButton.title = 'Finalizando dictado';
            activeMicButton.setAttribute('aria-label', 'Finalizando dictado');
            setRecognitionStatus('STOPPING', 'Finalizando el dictado… espera el último resultado.');
            const state = activeRecognitionState;
            if (state && typeof setTimeout === 'function') state.stopTimer = setTimeout(() => {
                if (activeRecognitionState === state) finishRecognition(state, 'El reconocimiento se interrumpió. Revisa el texto disponible y continúa por escrito si lo necesitas.');
            }, 1500);
        }
        if (abort) {
            resetMicButton(activeMicButton);
            clearRecognitionState();
        }
    };
    const combineSpeechResults = results => results.reduce((text, result) => appendSpeechText(text, result.text), '').trim();
    const renderRecognitionResults = (event, state) => {
        for (let index = event.resultIndex || 0; index < event.results.length; index += 1) {
            const result = event.results[index];
            const transcript = result?.[0]?.transcript?.trim() || '';
            if (transcript) state.resultParts[index] = {text: transcript, final: result.isFinal === true};
        }
        const finalText = combineSpeechResults(state.resultParts.filter(Boolean).filter(result => result.final));
        const interimText = combineSpeechResults(state.resultParts.filter(Boolean).filter(result => !result.final));
        const composedText = combineSpeechResults([{text: finalText}, {text: interimText}].filter(result => result.text));
        const newlyFinal = finalText.startsWith(state.finalText) ? finalText.slice(state.finalText.length).trim() : appendSpeechText(state.finalText, finalText).slice(state.finalText.length).trim();
        state.finalText = finalText;
        state.interimText = interimText;
        state.applying = true;
        if (state.manualEdit) {
            if (newlyFinal) state.input.value = appendSpeechText(state.input.value, newlyFinal);
        } else {
            state.input.value = appendSpeechText(state.baseValue, composedText);
        }
        state.input.dispatchEvent(new Event('input', {bubbles: true}));
        state.applying = false;
        if (interimText && !state.manualEdit) setRecognitionStatus('RECOGNIZING', 'Texto provisional… sigue hablando o detén el dictado.');
        else if (newlyFinal) setRecognitionStatus('TRANSCRIPT_READY', state.manualEdit ? 'Edición manual conservada; se añadió texto final nuevo.' : 'Texto reconocido. Puedes seguir hablando.');
    };
    const finishRecognition = (state, message = '') => {
        if (state?.stopTimer && typeof clearTimeout === 'function') clearTimeout(state.stopTimer);
        resetMicButton(state?.button || activeMicButton);
        clearRecognitionState();
        if (message) setRecognitionStatus(state?.input?.value.trim() ? 'TRANSCRIPT_READY' : 'ERROR', message);
        else if (state?.input?.value.trim()) setRecognitionStatus('TRANSCRIPT_READY', 'Dictado detenido. Revisa o corrige la respuesta y pulsa enviar.');
        else setRecognitionStatus('IDLE', 'Dictado detenido. Puedes responder por escrito.');
    };
    const waitForVoiceCancellation = () => new Promise(resolve => {
        if (typeof setTimeout === 'function') setTimeout(resolve, 0);
        else resolve();
    });
    const startRecognition = async (button, input) => {
        if (!nativeSttEnabled) { setRecognitionStatus('UNAVAILABLE', 'El dictado de voz no está disponible en este entorno. Puedes responder por escrito.'); return false; }
        if (!speechRecognitionSupported) { setRecognitionStatus('UNAVAILABLE', 'Este navegador no admite reconocimiento de voz. Puedes responder por escrito.'); return false; }
        if (recognitionStartPending) return false;
        if (activeRecognition) { if (activeMicButton === button) stopRecognition(false); return false; }
        recognitionStartPending = true;
        const startGeneration = ++recognitionGeneration;
        const targetAtStart = conversationCoordinator.snapshot().currentTarget;
        stopAssistantVoice();
        await waitForVoiceCancellation();
        if (recognitionGeneration !== startGeneration || conversationCoordinator.snapshot().currentTarget !== targetAtStart || conversationCoordinator.snapshot().paused) {
            recognitionStartPending = false;
            return false;
        }
        let recognition;
        try { recognition = new recognitionConstructor(); } catch (error) {
            recognitionStartPending = false;
            setRecognitionStatus('ERROR', 'No fue posible preparar el micrófono. Puedes responder por escrito.');
            return false;
        }
        const state = {button, input, baseValue: input.value.trim(), finalText: '', interimText: '', resultParts: [], manualEdit: false, applying: false};
        recognition.lang = 'es-MX';
        recognition.continuous = true;
        recognition.interimResults = true;
        recognition.maxAlternatives = 1;
        activeRecognition = recognition;
        activeMicButton = button;
        activeRecognitionState = state;
        recognitionStartPending = false;
        button.classList.add('is-listening');
        button.textContent = '⏹';
        button.title = 'Detener dictado';
        button.setAttribute('aria-label', 'Detener dictado');
        setRecognitionStatus('PREPARING', 'Preparando el micrófono… todavía no se ha guardado ningún dato.');
        recognition.onstart = () => setRecognitionStatus('LISTENING', 'Escuchando… habla con naturalidad.');
        recognition.onresult = event => { if (activeRecognitionState === state) renderRecognitionResults(event, state); };
        recognition.onerror = event => {
            if (activeRecognitionState !== state) return;
            const messages = { 'not-allowed': 'Permiso de micrófono denegado. Puedes responder por escrito.', 'service-not-allowed': 'El navegador no permite el servicio de reconocimiento. Puedes responder por escrito.', 'audio-capture': 'No se encontró un micrófono disponible. Puedes responder por escrito.', 'no-speech': 'No se detectó voz. Puedes intentarlo de nuevo o responder por escrito.', 'network': 'El servicio de reconocimiento no respondió. Puedes continuar por escrito.', 'language-not-supported': 'El idioma de reconocimiento no está disponible. Puedes responder por escrito.', 'aborted': 'El dictado se interrumpió. Revisa el texto disponible o continúa por escrito.' };
            state.error = messages[event.error] || 'No fue posible reconocer la voz. Puedes responder por escrito.';
            try { recognition.abort(); } catch (error) {}
            finishRecognition(state, state.error);
        };
        recognition.onend = () => { if (activeRecognitionState === state) finishRecognition(state, state.error || 'Dictado detenido. Revisa o corrige la respuesta y pulsa enviar.'); };
        try { recognition.start(); } catch (error) { finishRecognition(state, 'No fue posible iniciar el micrófono. Puedes responder por escrito.'); }
        return true;
    };
    const inspectLocalLanguageAvailability = async () => {
        const availability = recognitionConstructor && recognitionConstructor.available;
        if (typeof availability !== 'function') return localLanguageAvailability;
        try {
            const state = await availability.call(recognitionConstructor, {langs: ['es-MX']});
            localLanguageAvailability = state === 'available' ? 'available' : state;
        } catch (error) {
            localLanguageAvailability = 'unknown';
        }
        return localLanguageAvailability;
    };
    const startupVoiceEvents = () => {
        const snapshot = conversationCoordinator.snapshot();
        if (!snapshot.currentTarget) { setStatus('No hay una pregunta disponible para iniciar la guía por voz.'); return null; }
        const introType = conversationCoordinator.initialInterviewState() === 'FIRST_START' ? 'FIRST_START' : 'RESUME';
        const welcome = conversationCoordinator.voiceEvent('WELCOME');
        const firstStart = conversationCoordinator.voiceEvent('FIRST_START', snapshot.currentTarget, false);
        const intro = introType === 'FIRST_START'
            ? {...firstStart, text: firstStart.text || welcome.text}
            : conversationCoordinator.voiceEvent('RESUME', snapshot.currentTarget, false);
        const question = conversationCoordinator.voiceEvent('QUESTION', snapshot.currentTarget, false);
        if (!intro.text || !question.text || !question.stable_id || question.stable_id !== snapshot.currentTarget) {
            setStatus('No se pudo resolver la pregunta activa para iniciar la guía por voz. Puedes continuar por escrito.');
            return null;
        }
        return {intro, question};
    };
    const autoStartVoice = () => {
        const snapshot = conversationCoordinator.snapshot();
        if (!speechSupported() || voiceController.enabled || voiceController.muted || snapshot.paused) return;
        const startup = startupVoiceEvents();
        if (!startup) return;
        const intro = startup.intro;
        const question = startup.question;
        voiceController.activateAutomatically(intro, null, question);
    };
    voiceStartButton?.addEventListener('click', () => {
        if (!speechSupported()) { setStatus('La narración por voz no está disponible. Puedes seguir con las instrucciones escritas.'); return; }
        if (voiceController.unlock()) return;
        const startup = startupVoiceEvents();
        if (!startup) return;
        voiceController.activateStartup(startup.intro, startup.question, {userGesture:true});
    });
    voiceRepeatButton?.addEventListener('click', () => voiceController.repeat(true));
    voicePauseButton?.addEventListener('click', () => voiceController.pause());
    voiceResumeButton?.addEventListener('click', () => voiceController.resume());
    voiceMuteButton?.addEventListener('click', () => { const muted = voiceController.toggleMute(); writeVoiceMutePreference(muted); resetVoiceButtons(); });
    voiceStopButton?.addEventListener('click', () => stopAssistantVoice());
    voiceRateControl?.addEventListener('change', () => voiceController.setRate(voiceRateControl.value));
    if (!speechSupported()) {
        if (voiceStartButton) voiceStartButton.disabled = true;
    }
    function bindVoiceControls() {
        const activeQuestionMessage = document.querySelector('.patient-interview-question:not([hidden]):not(.chat-stage-hidden) > .patient-interview-message.assistant');
        const activeModuleMessage = document.querySelector('.interview-module-card:not(.chat-stage-hidden):not([hidden]) > .patient-interview-message.assistant');
        document.querySelectorAll('.patient-interview-message.assistant:not([data-voice-bound])').forEach(message => {
            const visible = !message.closest('[hidden], .chat-stage-hidden') && !message.hidden;
            if (!visible || (message !== activeQuestionMessage && message !== activeModuleMessage)) return;
            const header = message.querySelector('.interview-message-header');
            if (!header) return;
            const voiceTarget = stableTargetForMessage(message);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-secondary btn-sm chat-voice-button ms-2';
            button.dataset.voiceTarget = voiceTarget || '';
            setVoiceButtonState(button, false);
            button.addEventListener('click', () => speakAssistantMessage(button, voiceTarget));
            header.appendChild(button);
            const help = document.createElement('button');
            help.type = 'button';
            help.className = 'btn btn-outline-secondary btn-sm chat-help-button ms-1';
            help.dataset.voiceTarget = voiceTarget || '';
            help.textContent = '? Ayuda';
            help.title = 'Mostrar ayuda de la pregunta';
            help.setAttribute('aria-label', 'Mostrar ayuda de la pregunta');
            help.addEventListener('click', () => showContractHelp(help));
            header.appendChild(help);
            message.dataset.voiceBound = '1';
        });
        document.querySelectorAll('.patient-interview-answer input[type="text"], .patient-interview-answer textarea').forEach(input => {
            const answer = input.closest('.patient-interview-answer');
            const visible = !input.closest('[hidden], .chat-stage-hidden') && !input.hidden && !answer?.hidden;
            if (!answer || !visible || input.disabled) return;
            let host = answer.querySelector('.interview-composer-input') || answer.querySelector('.m03-input-wrap');
            if (!host && answer.classList.contains('interview-composer')) {
                host = document.createElement('div');
                host.className = 'interview-composer-input';
                const label = input.previousElementSibling?.tagName === 'LABEL' ? input.previousElementSibling : null;
                if (label) host.appendChild(label);
                answer.insertBefore(host, answer.firstChild);
                host.appendChild(input);
            }
            if (!host && answer.classList.contains('m03-composer')) host = answer.querySelector('.m03-input-wrap');
            if (!host) return;
            if (input.dataset.nativeSttEditBound !== '1') {
                input.addEventListener('input', () => {
                    if (activeRecognitionState?.input === input && !activeRecognitionState.applying) activeRecognitionState.manualEdit = true;
                });
                input.dataset.nativeSttEditBound = '1';
            }
            const existingButton = host.querySelector('.chat-mic-button');
            const existingIndicator = host.querySelector('.chat-mic-unavailable');
            if (nativeSttEnabled && speechRecognitionSupported) {
                existingIndicator?.remove();
                if (!existingButton) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-outline-secondary chat-mic-button';
                    button.textContent = '🎙';
                    button.title = 'Dictado de voz en español';
                    button.setAttribute('aria-label', 'Responder mediante micrófono');
                    button.addEventListener('click', () => startRecognition(button, input));
                    host.appendChild(button);
                }
            } else {
                existingButton?.remove();
                if (!existingIndicator) {
                    const indicator = document.createElement('button');
                    indicator.type = 'button';
                    indicator.className = 'chat-mic-unavailable';
                    indicator.textContent = '🎙';
                    indicator.title = nativeSttEnabled ? 'Este navegador no admite reconocimiento de voz; usa el teclado.' : 'El dictado de voz no está disponible en este entorno; usa el teclado.';
                    indicator.setAttribute('aria-disabled', 'true');
                    indicator.setAttribute('aria-label', nativeSttEnabled ? '🎙 No disponible; el navegador no admite reconocimiento de voz; usa la captura escrita' : '🎙 Dictado no disponible en este entorno; usa la captura escrita');
                    indicator.addEventListener('click', () => setStatus(nativeSttEnabled ? 'Este navegador no admite reconocimiento de voz; responde por escrito.' : 'El dictado de voz no está disponible en este entorno.'));
                    host.appendChild(indicator);
                }
            }
            input.dataset.micBound = '1';
        });
    }
    document.addEventListener('visibilitychange', () => { if (document.hidden) { stopAssistantVoice(); stopRecognition(); } });
    window.addEventListener('pagehide', () => { conversationCoordinator.invalidate('page_hidden'); stopAssistantVoice(); stopRecognition(); });
    const mapCard = document.querySelector('[data-module="M09-MAP"]');
    if (mapCard) {
        const mapState = {};
        const mapVersion = () => mapCard.dataset.m09MapVersion || '';
        const imageUrl = orientation => '<?=SERVIDOR;?>historia-clinica/paciente/imagen-evaluacion-dolor/' + orientation + '/' + Number(main.dataset.patient) + '?chat_map=' + Date.now();
        const loadMap = orientation => new Promise(resolve => { const canvas=mapCard.querySelector('[data-map-canvas="'+orientation+'"]'), image=new Image(); if(!canvas){resolve();return;} image.onload=()=>{const ctx=canvas.getContext('2d');ctx.clearRect(0,0,canvas.width,canvas.height);ctx.drawImage(image,0,0,canvas.width,canvas.height);mapState[orientation]={canvas,ctx,image,loaded:true,color:'#ff0000',dirty:false};resolve();}; image.onerror=()=>{setStatus('No se pudo cargar el mapa '+orientation+'.');resolve();}; image.src=imageUrl(orientation); });
        const point = (canvas,event) => { const rect=canvas.getBoundingClientRect(); return {x:Math.max(0,Math.min(canvas.width,(event.clientX-rect.left)*canvas.width/rect.width)),y:Math.max(0,Math.min(canvas.height,(event.clientY-rect.top)*canvas.height/rect.height))}; };
        const bindMap = orientation => { const canvas=mapState[orientation]?.canvas; if(!canvas)return; let drawing=false,last=null; canvas.addEventListener('pointerdown',event=>{const state=mapState[orientation];event.preventDefault();drawing=true;last=point(canvas,event);canvas.setPointerCapture?.(event.pointerId);state.ctx.strokeStyle=state.color;state.ctx.lineWidth=Math.max(18,canvas.width/48);state.ctx.lineCap='round';state.ctx.lineJoin='round';state.ctx.beginPath();state.ctx.moveTo(last.x,last.y);state.ctx.lineTo(last.x+.1,last.y+.1);state.ctx.stroke();state.dirty=true;}); canvas.addEventListener('pointermove',event=>{if(!drawing)return;const state=mapState[orientation];event.preventDefault();const next=point(canvas,event);state.ctx.beginPath();state.ctx.moveTo(last.x,last.y);state.ctx.lineTo(next.x,next.y);state.ctx.stroke();last=next;}); const finish=event=>{if(!drawing)return;event.preventDefault();drawing=false;last=null;}; canvas.addEventListener('pointerup',finish);canvas.addEventListener('pointercancel',finish);canvas.addEventListener('pointerleave',event=>{if(drawing&&event.buttons===0)finish(event);}); };
        ['frente','espalda'].forEach(orientation=>{ loadMap(orientation).then(()=>bindMap(orientation)); mapCard.querySelectorAll('[data-map-panel="'+orientation+'"] [data-map-color]').forEach(button=>button.addEventListener('click',()=>{mapState[orientation].color=button.dataset.mapColor||'#ff0000';})); mapCard.querySelector('[data-map-clear="'+orientation+'"]').addEventListener('click',()=>{loadMap(orientation);mapCard.querySelector('[data-map-preview-box="'+orientation+'"]').hidden=true;setStatus('Edición local del mapa '+orientation+' limpiada. No se guardó ningún dato.');}); mapCard.querySelector('[data-map-preview="'+orientation+'"]').addEventListener('click',()=>{const state=mapState[orientation],box=mapCard.querySelector('[data-map-preview-box="'+orientation+'"][data-map-preview-box]'),preview=mapCard.querySelector('[data-map-preview-image="'+orientation+'"]');if(!state?.canvas)return;mapState[orientation].preview=state.canvas.toDataURL('image/png');preview.src=mapState[orientation].preview;box.hidden=false;setStatus('Previsualización lista. No se ha guardado el mapa '+orientation+'.');}); mapCard.querySelector('[data-map-edit="'+orientation+'"]').addEventListener('click',()=>{mapCard.querySelector('[data-map-preview-box="'+orientation+'"]').hidden=true;setStatus('Puedes corregir el mapa '+orientation+' antes de confirmar.');}); mapCard.querySelector('[data-map-cancel="'+orientation+'"]').addEventListener('click',()=>{mapState[orientation].preview=null;mapCard.querySelector('[data-map-preview-box="'+orientation+'"]').hidden=true;setStatus('Previsualización cancelada. No se guardó ningún mapa.');}); mapCard.querySelector('[data-map-confirm="'+orientation+'"]').addEventListener('click',async button=>{const preview=mapState[orientation]?.preview;if(!preview){setStatus('Genera una previsualización antes de confirmar.');return;}button.currentTarget.disabled=true;try{const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m09-mapa',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),orientation,image:preview,expectedVersion:mapVersion()})});const data=await response.json();if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible guardar el mapa.');mapCard.dataset.m09MapVersion=data.version||mapVersion();mapState[orientation].preview=null;mapCard.querySelector('[data-map-preview-box="'+orientation+'"]').hidden=true;await loadMap(orientation);setStatus('Mapa '+orientation+' confirmado y recuperado desde el almacenamiento.');}catch(error){setStatus(error.message||'No fue posible guardar el mapa.');}finally{button.currentTarget.disabled=false;}}); });
    }
    showConversationStage(conversationStage);
    void inspectLocalLanguageAvailability();
    bindVoiceControls();
    window.setTimeout(autoStartVoice, 0);
})();
</script>
<script src="<?=RUTA_JS;?>main.js"></script>
<script>
(() => {
    document.addEventListener('keydown', event => {
        const sidebar = document.getElementById('sidebar');
        if (event.key === 'Escape') {
            if (!sidebar || window.innerWidth >= 768 || !sidebar.classList.contains('active')) return;
            event.preventDefault();
            sidebar.classList.remove('active');
            document.querySelectorAll('.sidebar-toggler').forEach(control => {
                control.setAttribute('aria-expanded', 'false');
                control.setAttribute('aria-label', 'Abrir menú principal');
                control.setAttribute('title', 'Abrir menú principal');
            });
        }
    });
})();
</script>
</body>
</html>
