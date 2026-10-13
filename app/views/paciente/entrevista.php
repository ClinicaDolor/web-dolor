<?php

$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$questionsJson = json_encode($data['questions'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$questionList = $data['questions'] ?? [];
$versionedAsset = static function (string $baseUrl, string $assetPath, string $publicPath): string {
    $filesystemPath = realpath(__DIR__ . '/../../public/' . ltrim($publicPath, '/'));
    $version = $filesystemPath !== false && is_file($filesystemPath) ? filemtime($filesystemPath) : false;

    return $baseUrl . $assetPath . ($version === false ? '' : '?v=' . (int)$version);
};
$bootstrapCssUrl = $versionedAsset(RUTA_CSS, 'bootstrap.css', 'assets/css/bootstrap.css');
$perfectScrollbarCssUrl = $versionedAsset(RUTA_PUBLIC, 'libs/perfect-scrollbar/perfect-scrollbar.css', 'assets/libs/perfect-scrollbar/perfect-scrollbar.css');
$appCssUrl = $versionedAsset(RUTA_CSS, 'app.css', 'assets/css/app.css');
$logoUrl = $versionedAsset(RUTA_IMAGES, 'logo-clinica.png', 'assets/images/logo-clinica.png');
$firstPending = count($questionList);
foreach ($questionList as $questionIndex => $questionItem) {
    if (trim((string)($questionItem['respuesta'] ?? '')) === '') { $firstPending = $questionIndex; break; }
}
if ($firstPending >= count($questionList) && $questionList) $firstPending = count($questionList) - 1;
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
        .patient-interview-page { background:#f4f0ff; }
        .patient-interview-main { min-height:calc(100vh - 4rem); background:linear-gradient(180deg,#f7f4ff 0%,#f1edff 100%); }
        .patient-interview-shell { max-width: 980px; margin: 0 auto; }
        .patient-interview-header { display:flex; gap:1rem; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; }
        .patient-interview-header, .interview-progress-card, .patient-interview-conversation-card { border:1px solid #e5ddf7; background:#fff; border-radius:1.25rem; box-shadow:0 .45rem 1.25rem rgba(72,45,120,.08); }
        .patient-interview-header { padding:1.25rem 1.35rem; }
        .patient-interview-header h1, #interview-chat h2 { color:#27154e; }
        .patient-interview-conversation-card > .card-body { padding:1.35rem; }
        #interview-chat { display:flex; flex-direction:column; gap:1rem; border:0; background:transparent; border-radius:0; box-shadow:none; }
        .patient-interview-message { max-width:82%; padding:1rem 1.15rem; border-radius:1rem; box-shadow:0 0.35rem 1rem rgba(31,52,72,.08); }
        .patient-interview-message.assistant { align-self:stretch; width:100%; max-width:100%; background:#eef5f7; color:#173042; }
        .patient-interview-message.patient { align-self:flex-end; background:#1b6b73; color:#fff; }
        .patient-interview-message[hidden] { display:none; }
        .interview-module-card { padding:0; border:0; border-radius:0; background:transparent; box-shadow:none; }
        .interview-module-card[hidden] { display:none !important; }
        .interview-message-header { display:flex; align-items:center; justify-content:space-between; gap:.75rem; min-height:2.75rem; }
        .interview-message-header::before { content:'✦'; flex:0 0 2.75rem; width:2.75rem; height:2.75rem; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; color:#5a13bb; background:#f0eaff; font-size:1.35rem; }
        .interview-message-header > strong { flex:1; min-width:0; }
        .interview-question-text { margin:.45rem 0 0; color:#274968; font-size:1.05rem; line-height:1.45; }
        .interview-composer-help { display:flex; gap:.45rem; align-items:flex-start; margin:.7rem 0 0; color:#587080; font-size:.92rem; line-height:1.4; }
        .patient-interview-answer { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-top:1rem; }
        .patient-interview-answer input { min-width:min(100%, 25rem); }
        .patient-interview-answer button { min-height:2.9rem; }
        .patient-interview-muted { color:#587080; font-size:.92rem; }
        .patient-interview-proposal { border-left:.25rem solid #1b6b73; padding:.9rem 1rem; background:#f4fafb; color:#173042; }
        .patient-interview-proposal[hidden] { display:none; }
        .interview-proposal-fields { display:grid; grid-template-columns:repeat(auto-fit,minmax(12rem,1fr)); gap:.65rem; margin:.75rem 0 0; }
        .interview-proposal-fields > div { min-width:0; padding:.65rem .75rem; border:1px solid #d8e8eb; border-radius:.65rem; background:#fff; }
        .interview-proposal-fields dt { color:#587080; font-size:.78rem; font-weight:600; }
        .interview-proposal-fields dd { margin:.2rem 0 0; color:#173042; overflow-wrap:anywhere; }
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
        .interview-composer { display:grid; grid-template-columns:minmax(0,1fr) auto; grid-template-rows:auto auto; gap:.65rem .7rem; align-items:center; width:100%; }
        .interview-composer-input { grid-column:1; grid-row:1; min-width:0; min-height:3.9rem; display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:.25rem; padding:.2rem .35rem .2rem .9rem; border:1px solid #bdc7dc; border-radius:.85rem; background:#fff; box-shadow:0 .15rem .45rem rgba(38,56,91,.08); }
        .interview-composer-input > label { grid-column:1 / -1; }
        .interview-composer-input > input { grid-column:1; grid-row:1; min-width:0; width:100%; min-height:3.35rem; padding:.75rem .25rem; border:0; box-shadow:none; background:transparent; }
        .interview-composer-input > input:focus { outline:0; box-shadow:none; }
        .interview-composer .chat-send-button { grid-column:2; grid-row:1; width:3.9rem; min-width:3.9rem; min-height:3.9rem; padding:.25rem; border-radius:.8rem; font-size:1.7rem; line-height:1; color:#fff; background:#5b16c8; border-color:#5b16c8; }
        .interview-quick-actions, .m03-quick-actions { grid-column:1 / -1; grid-row:2; display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
        .interview-quick-actions button, .m03-quick-actions button { min-height:2.75rem; }
        .m03-composer { display:grid; }
        .m03-input-wrap { min-width:0; }
        .m03-input-wrap .interview-text { min-width:0; width:100%; }
        #interview-chat .btn-primary:hover, #interview-chat .btn-primary:focus-visible, #interview-chat .btn-primary:active { color:#fff; background:#4c1499; border-color:#4c1499; }
        #interview-chat .btn-outline-primary:hover, #interview-chat .btn-outline-primary:focus-visible, #interview-chat .btn-outline-primary:active { color:#fff; background:#5b21b6; border-color:#5b21b6; }
        #interview-chat .btn-outline-secondary:hover, #interview-chat .btn-outline-secondary:focus-visible, #interview-chat .btn-outline-secondary:active { color:#24134f; background:#eee6ff; border-color:#8b6bc9; }
        #interview-chat button:disabled { color:#6f6f78; background:#ececf0; border-color:#d2d2d8; opacity:.85; }
        #interview-chat button:focus-visible { outline:3px solid #f3c969; outline-offset:2px; }
        .chat-voice-button, .chat-mic-button, .chat-mic-unavailable { min-width:2.75rem; min-height:2.75rem; width:2.75rem; padding:.25rem; display:inline-flex; align-items:center; justify-content:center; }
        .chat-voice-button { width:auto; padding:.55rem .85rem; white-space:nowrap; font-size:.95rem; }
        .chat-mic-button, .chat-mic-unavailable { grid-column:2; grid-row:1; border-radius:50%; color:#5a13bb; background:#f0eaff; border:1px solid #e0d2ff; font-size:1.2rem; }
        .chat-mic-unavailable { cursor:help; opacity:.82; }
        .chat-mic-button.is-listening { color:#fff !important; background:#b42318 !important; border-color:#b42318 !important; }
        @media (max-width:575.98px) { .patient-interview-message { max-width:100%; } .patient-interview-header { padding:.95rem; } .patient-interview-conversation-card > .card-body { padding:.95rem; } .interview-composer { grid-template-columns:minmax(0,1fr) auto; gap:.55rem; } .interview-composer-input { min-height:3rem; padding-left:.65rem; } .interview-composer-input > input { min-height:2.55rem; } .interview-composer .chat-send-button { width:2.75rem; min-width:2.75rem; min-height:3rem; } .interview-quick-actions, .m03-quick-actions { flex-direction:row; } .interview-quick-actions button, .m03-quick-actions button { width:auto; } }
    </style>
</head>
<body class="app-shell-body app-shell-patient patient-interview-page">
<div id="app">
    <?=$data['sidebar'];?>
    <div id="main" data-patient="<?=$escape($data['idPaciente']);?>" data-csrf="<?=$escape($data['csrf']);?>">
        <nav class="navbar navbar-header navbar-expand navbar-light portal-topbar" aria-label="Barra de herramientas del portal del paciente">
            <div class="container-fluid"><span class="portal-badge">Entrevista Conversacional</span></div>
        </nav>
        <main class="main-content container-fluid patient-interview-main">
            <div class="patient-interview-shell">
                <header class="page-title portal-page-title patient-interview-header mb-4">
                    <div>
                        <p class="dashboard-eyebrow">MI HISTORIA CLÍNICA</p>
                        <h1>Entrevista Conversacional</h1>
                        <p class="text-muted mb-1">Entrevista conversacional M01 → M09. Modo sin proveedor: Piloto determinista.</p>
                        <p class="patient-interview-muted mb-0">Responde con tus propias palabras. El asistente preparará una interpretación para que la revises antes de guardarla.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-outline-primary" data-chat-history>Consultar respuestas previas</button><a class="btn btn-outline-secondary" href="<?=SERVIDOR;?>historia-clinica">Volver a mi Historia Clínica</a></div>
                </header>
                <div class="chat-history-panel mb-3" data-chat-history-panel hidden><strong>Respuestas ya registradas</strong><p class="patient-interview-muted mb-0">La consulta no modifica el expediente. Cierra este panel para continuar con la pregunta activa.</p><div data-chat-history-content class="mt-2"></div></div>

                <section class="card border-0 rounded-4 shadow-sm interview-progress-card" aria-labelledby="interview-progress-title">
                    <div class="card-body">
                        <div class="d-flex justify-content-between gap-3 flex-wrap"><strong id="interview-progress-title">Progreso de la entrevista</strong><span id="interview-progress">Pregunta 1 de 5</span></div>
                        <div class="progress mt-2" aria-hidden="true"><div id="interview-progress-bar" class="progress-bar" style="width:20%"></div></div>
                    </div>
                </section>

                <section class="card border-0 rounded-4 shadow-sm mt-4 patient-interview-conversation-card" aria-labelledby="interview-chat-title">
                    <div class="card-body patient-interview-chat" id="interview-chat" aria-live="polite">
                        <h2 id="interview-chat-title" class="h4">Conversación clínica</h2>
                        <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente:</strong></div><p class="interview-question-text">Comencemos con la siguiente pregunta pendiente de tu entrevista. Puedes revisar cada propuesta antes de confirmarla.</p></div>
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
                        <?php if (!empty($data['m02']['records'])): ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M02" data-m02-pending="<?=count(array_filter($data['m02']['records'] ?? [], static fn(array $record): bool => ($record['status'] ?? 'pending') !== 'documented'));?>" data-m02-write-enabled="<?=!empty($data['m02']['write_enabled']) ? '1' : '0';?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M02</strong></div><p class="interview-question-text">Revisaremos tus antecedentes familiares. Una negativa sencilla puede guardarse con «No, guardar y continuar»; las respuestas con datos adicionales se revisan antes de guardarse.</p></div>
                            <div class="patient-interview-context-grid">
                                <?php foreach ($data['m02']['records'] as $record): ?>
                                <div data-m02-disease="<?=$escape($record['disease']);?>" data-m02-version="<?=$escape($record['version'] ?? '');?>" data-m02-write-enabled="<?=!empty($record['write_enabled']) ? '1' : '0';?>">
                                    <dt><?=$escape($record['disease']);?></dt>
                                    <dd><?=($record['status'] === 'documented') ? 'Respuesta documentada' : 'Pendiente';?></dd>
                                    <div class="patient-interview-answer interview-composer m02-composer">
                                        <div class="interview-composer-input">
                                            <label class="visually-hidden" for="m02-input-<?=$escape(md5($record['disease']));?>">Respuesta sobre <?=$escape($record['disease']);?></label>
                                            <input id="m02-input-<?=$escape(md5($record['disease']));?>" class="form-control" data-m02-input type="text" placeholder="Ej. mi madre tiene esta enfermedad">
                                        </div>
                                        <div class="interview-quick-actions" aria-label="Respuesta rápida M02"><button type="button" class="btn btn-outline-primary btn-sm" data-m02-quick data-value="Sí">Sí</button><?php if (!empty($data['m02']['write_enabled'])): ?><button type="button" class="btn btn-primary btn-sm" data-m02-safe-no>No, guardar y continuar</button><?php endif; ?></div>
                                        <button type="button" class="btn btn-outline-primary btn-sm chat-send-button" data-m02-interpret hidden aria-label="Interpretar respuesta" title="Interpretar respuesta">➤</button>
                                    </div>
                                    <small class="d-block patient-interview-muted mt-1" data-m02-result hidden></small>
                                    <div class="patient-interview-answer mt-2" data-m02-confirmation hidden>
                                        <button type="button" class="btn btn-primary btn-sm" data-m02-confirm>Guardar y continuar</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-m02-cancel>Corregir</button>
                                        <?php if (!empty($data['m02']['write_enabled'])): ?><button type="button" class="btn btn-outline-secondary btn-sm" data-m02-defer>Responder después</button><?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="patient-interview-muted mb-0 mt-3">«Responder después» no escribe ni convierte la pregunta pendiente en una respuesta negativa.</p>
                        </article>
                        <?php endif; ?>
                        <?php foreach ($questionList as $index => $question): ?>
                        <?php $m03Eligible = ($question['eligible'] ?? true) === true; $m03Parent = (int)($question['parentQuestionId'] ?? 0); $m03DirectNumeric = ($question['tipo'] ?? '') === 'input' && preg_match('/\b(?:cu[aá]ntos|cu[aá]nta|cu[aá]nto|qu[eé] edad|hace cu[aá]nto|durante cu[aá]nto)\b/iu', (string)($question['pregunta'] ?? '')) === 1; ?>
                        <?php $hasSavedAnswer = trim((string)($question['respuesta'] ?? '')) !== ''; ?>
                        <article class="patient-interview-question interview-module-card" data-question-index="<?=$index;?>" data-question-id="<?=$escape($question['idPregunta']);?>" data-theme-id="<?=$escape($question['idTema']);?>" data-parent-id="<?=$escape($m03Parent);?>" data-eligible="<?=$m03Eligible ? '1' : '0';?>" data-type="<?=$escape($question['tipo']);?>" data-response-id="<?=$escape($question['idRespuesta'] ?? '');?>" data-saved-value="<?=$escape($question['respuesta'] ?? '');?>" data-m03-version="<?=$escape($data['m03']['version'] ?? '');?>" data-m03-write-enabled="<?=!empty($data['m03']['write_enabled']) ? '1' : '0';?>" data-m03-direct-capable="<?=$m03DirectNumeric ? '1' : '0';?>"<?=$index === $firstPending ? '' : ' hidden';?>>
                            <div class="patient-interview-message assistant">
                                <div class="interview-message-header"><strong>Asistente · <?=$escape($question['tema'] ?? 'M03');?>:</strong></div>
                                <p class="interview-question-text"><?=$escape($question['pregunta']);?></p>
                                <div class="patient-interview-answer interview-composer m03-composer"<?=$hasSavedAnswer && $index < $firstPending ? ' hidden' : ''; ?>>
                                    <div class="interview-composer-input m03-input-wrap">
                                        <label class="visually-hidden" for="interview-answer-<?=$index;?>">Respuesta</label>
                                        <input id="interview-answer-<?=$index;?>" class="form-control interview-text" type="text" value="<?=$escape($question['respuesta'] ?? '');?>" placeholder="Escribe tu respuesta…" autocomplete="off">
                                    </div>
                                    <?php if (($question['tipo'] ?? '') === 'select'): ?>
                                        <div class="m03-quick-actions" aria-label="Respuestas rápidas">
                                            <button type="button" class="btn btn-outline-primary" data-m03-quick-yes>Sí</button>
                                            <?php if (!empty($data['m03']['write_enabled']) && $m03Eligible): ?><button type="button" class="btn btn-primary" data-m03-safe-no>No, guardar y seguir</button><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-primary chat-send-button interview-submit" aria-label="Interpretar respuesta" title="Interpretar respuesta">➤</button>
                                </div>
                                <p class="interview-composer-help"><span aria-hidden="true">ⓘ</span> Escribe o selecciona una respuesta. Las respuestas complejas se revisan antes de guardarse.</p>
                            </div>
                            <div class="patient-interview-message patient" data-confirmed-message<?=$hasSavedAnswer ? '' : ' hidden';?>><?=$hasSavedAnswer ? $escape('Respuesta confirmada: ' . $question['respuesta']) : '';?></div>
                            <div class="patient-interview-proposal" data-proposal hidden>
                                <strong>Revisión clínica</strong>
                                <div data-proposal-text class="mt-1"></div>
                                <label class="visually-hidden" for="interview-proposal-<?=$index;?>">Valor propuesto</label>
                                <input id="interview-proposal-<?=$index;?>" class="form-control mt-2" data-proposal-input type="text" autocomplete="off">
                                <div class="patient-interview-answer mt-2">
                                    <button type="button" class="btn btn-primary" data-proposal-confirm>Guardar y continuar</button>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                        <?php if (!empty($data['m04'])): ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M04" data-m04-version="<?=$escape($data['m04']['version'] ?? '');?>" data-m04-write-enabled="<?=!empty($data['m04']['write_enabled']) ? '1' : '0';?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M04</strong></div><p class="interview-question-text">Revisaremos tus antecedentes quirúrgicos con los campos del expediente. La propuesta puede guardarse únicamente después de revisarla y confirmarla.</p></div>
                            <?php if (!empty($data['m04']['records'])): ?>
                            <div class="patient-interview-context-grid">
                                <?php foreach ($data['m04']['records'] as $record): ?>
                                <div><dt>Registro quirúrgico</dt><dd><?= $escape($record['cirugia'] ?? 'Sin nombre registrado'); ?></dd><small class="patient-interview-muted"><?= $escape($record['fecha'] ?? 'Fecha no registrada'); ?></small></div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?><p class="patient-interview-muted">No hay cirugías registradas.</p><?php endif; ?>
                            <div class="patient-interview-answer interview-composer" data-m04-review>
                                <div class="interview-composer-input"><label class="visually-hidden" for="m04-input">Respuesta sobre antecedentes quirúrgicos</label>
                                <input id="m04-input" class="form-control" data-m04-input type="text" placeholder="Ej. me operaron de la vesícula hace cinco años"></div>
                                <button type="button" class="btn btn-outline-primary chat-send-button" data-m04-interpret aria-label="Interpretar respuesta M04" title="Interpretar respuesta M04">➤</button>
                            </div>
                            <div class="patient-interview-proposal" data-m04-result hidden></div>
                            <div class="patient-interview-answer mt-2" data-m04-confirmation hidden>
                                <label class="visually-hidden" for="m04-surgery">Cirugía propuesta</label>
                                <input id="m04-surgery" class="form-control" data-m04-surgery type="text" placeholder="Cirugía">
                                <label class="visually-hidden" for="m04-date">Fecha propuesta</label>
                                <input id="m04-date" class="form-control" data-m04-date type="date">
                                <label class="visually-hidden" for="m04-observations">Observaciones propuestas</label>
                                <input id="m04-observations" class="form-control" data-m04-observations type="text" placeholder="Observaciones">
                                <button type="button" class="btn btn-primary" data-m04-confirm>Confirmar y guardar M04</button>
                                <button type="button" class="btn btn-outline-secondary" data-m04-cancel>Cancelar</button>
                            </div>
                            <p class="patient-interview-muted mb-0">Sólo se guardarán los campos revisados después de confirmar.</p>
                        </article>
                        <?php endif; ?>
                        <?php if (!empty($data['m05'])): $m05Pending = $data['m05']['pending_question'] ?? null; ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M05" data-m05-version="<?=$escape($data['m05']['version'] ?? '');?>" data-m05-write-enabled="<?=!empty($data['m05']['write_enabled']) ? '1' : '0';?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M05</strong></div><p class="interview-question-text">Continuaremos con antecedentes personales patológicos. Revisa la propuesta antes de confirmar; no se guardará nada sin tu confirmación.</p></div>
                            <?php $m05Existing = array_filter($data['m05']['questions'] ?? [], static fn(array $row): bool => trim((string)($row['respuesta'] ?? '')) !== ''); ?>
                            <?php if ($m05Existing): ?><div class="patient-interview-context-grid" aria-label="Respuestas M05 existentes">
                                <?php foreach ($m05Existing as $row): ?><div><dt><?=$escape($row['tema'] ?? 'M05');?></dt><dd><?=$escape($row['respuesta']);?></dd></div><?php endforeach; ?>
                            </div><?php endif; ?>
                            <?php if ($m05Pending): ?>
                            <p class="patient-interview-muted"><strong><?=$escape($m05Pending['tema'] ?? 'Pregunta M05');?></strong><br><?=$escape($m05Pending['pregunta'] ?? '');?></p>
                            <div class="patient-interview-answer interview-composer">
                                <div class="interview-composer-input"><label class="visually-hidden" for="m05-input">Respuesta M05</label>
                                <input id="m05-input" class="form-control" data-m05-input type="text" placeholder="Escribe tu respuesta…"></div>
                                <button type="button" class="btn btn-outline-primary chat-send-button" data-m05-interpret aria-label="Interpretar respuesta M05" title="Interpretar respuesta M05">➤</button>
                            </div>
                            <div class="patient-interview-proposal" data-m05-result hidden></div>
                            <div class="patient-interview-answer mt-2" data-m05-confirmation hidden>
                                <label class="visually-hidden" for="m05-value">Valor M05</label>
                                <input id="m05-value" class="form-control" data-m05-value type="text">
                                <button type="button" class="btn btn-primary" data-m05-confirm>Confirmar y guardar M05</button>
                                <button type="button" class="btn btn-outline-secondary" data-m05-cancel>Cancelar</button>
                            </div>
                            <?php else: ?><p class="patient-interview-muted">No hay preguntas M05 pendientes de respuesta.</p><?php endif; ?>
                        </article>
                        <?php endif; ?>
                        <?php if (!empty($data['m05']['diseases'])): $m05Disease = null; foreach ($data['m05']['diseases'] as $disease) { if (trim((string)($disease['respuesta'] ?? '')) === '') { $m05Disease = $disease; break; } } ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M05-V2" data-m05-version="<?=$escape($data['m05']['version'] ?? '');?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M05 enfermedades</strong></div><p class="interview-question-text">Revisaremos el catálogo de enfermedades y sus datos dependientes. No se guardará nada sin confirmación.</p></div>
                            <?php if ($m05Disease): ?><p class="patient-interview-muted"><strong><?=$escape($m05Disease['enfermedad']);?></strong><br>¿Has sido diagnosticado con esta enfermedad?</p>
                            <div class="patient-interview-answer interview-composer"><div class="interview-composer-input"><label class="visually-hidden" for="m05-v2-input">Respuesta M05 V2</label><input id="m05-v2-input" class="form-control" data-m05-v2-input type="text" placeholder="Ej. sí, Tipo 2, 2020"></div><button type="button" class="btn btn-outline-primary chat-send-button" data-m05-v2-interpret aria-label="Interpretar respuesta M05 V2" title="Interpretar respuesta M05 V2">➤</button></div>
                            <div class="patient-interview-proposal" data-m05-v2-result hidden></div><div class="patient-interview-answer mt-2" data-m05-v2-confirmation hidden><button type="button" class="btn btn-primary" data-m05-v2-confirm>Confirmar y guardar M05</button><button type="button" class="btn btn-outline-secondary" data-m05-v2-cancel>Cancelar</button></div>
                            <?php else: ?><p class="patient-interview-muted">No hay enfermedades M05 V2 pendientes.</p><?php endif; ?>
                        </article>
                        <?php endif; ?>
                        <?php if (!empty($data['m06'])): $m06Pending = $data['m06']['pending_record'] ?? null; ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M06" data-m06-version="<?=$escape($data['m06']['version'] ?? '');?>" data-m06-context="{}">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M06</strong></div><p class="interview-question-text">Continuaremos con medicación actual. Indica los cinco datos del medicamento y revisa la propuesta antes de guardarla.</p></div>
                            <?php if (!empty($data['m06']['records'])): ?><div class="patient-interview-context-grid"><?php foreach ($data['m06']['records'] as $record): ?><div><dt>Medicamento</dt><dd><?=$escape($record['descripcion'] ?? 'Registro existente');?></dd></div><?php endforeach; ?></div><?php endif; ?>
                            <div class="patient-interview-answer interview-composer"><div class="interview-composer-input"><label class="visually-hidden" for="m06-input">Respuesta M06</label><input id="m06-input" class="form-control" data-m06-input type="text" placeholder="Ej. tomo ibuprofeno para dolor durante 2 semanas, dosis 400 mg, médico López"></div><button type="button" class="btn btn-outline-primary chat-send-button" data-m06-interpret aria-label="Interpretar respuesta M06" title="Interpretar respuesta M06">➤</button></div>
                            <div class="patient-interview-proposal" data-m06-result role="status" aria-live="polite" hidden></div><div class="patient-interview-answer mt-2" data-m06-confirmation hidden><button type="button" class="btn btn-primary" data-m06-confirm>Confirmar y guardar M06</button><button type="button" class="btn btn-outline-secondary" data-m06-cancel>Cancelar</button></div>
                        </article>
                        <?php endif; ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M07">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M07</strong></div><p class="interview-question-text">Este módulo está pendiente porque el catálogo oficial de preguntas no está disponible en esta entrega.</p></div>
                            <p class="patient-interview-muted mb-0">M07 no se marcará como completo ni generará respuestas ficticias.</p>
                        </article>
                        <?php if (!empty($data['m08'])) { $m08Items = $data['m08']['items'] ?? []; $m08Pending = null; foreach ($m08Items as $m08Item) { $m08Utilizo = trim((string)($m08Item['utilizo'] ?? '')); $m08Result = trim((string)($m08Item['resultado'] ?? '')); $m08Description = trim((string)($m08Item['descripcion'] ?? '')); $m08Incomplete = ($m08Utilizo === '') || ($m08Utilizo === 'Si' && ($m08Result === '' || ($m08Result === 'Tuve efectos adversos' && $m08Description === ''))); if ($m08Incomplete) { $m08Pending = $m08Item; break; } } ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M08" data-m08-version="<?=$escape($data['m08']['version'] ?? '');?>" data-m08-write-enabled="<?=!empty($data['m08']['write_enabled']) ? '1' : '0';?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M08 tratamientos</strong></div><p class="interview-question-text">Revisaremos los tratamientos del catálogo uno por uno. No se guardará ninguna respuesta hasta que la revises y confirmes.</p></div>
                            <?php if ($m08Pending): ?>
                            <p class="patient-interview-muted"><strong>Tratamiento: <?=$escape($m08Pending['tratamiento'] ?? '');?></strong></p>
                            <div class="patient-interview-answer interview-composer"><div class="interview-composer-input"><label class="visually-hidden" for="m08-input">Respuesta M08</label><input id="m08-input" class="form-control" data-m08-input type="text" placeholder="Ej. no; o utilizo: Sí; resultado: Funciono"></div><button type="button" class="btn btn-outline-primary chat-send-button" data-m08-interpret aria-label="Interpretar respuesta M08" title="Interpretar respuesta M08">➤</button></div>
                            <div class="patient-interview-proposal" data-m08-result hidden></div>
                            <div class="patient-interview-answer mt-2" data-m08-confirmation hidden>
                                <label class="visually-hidden" for="m08-utilizo">¿Utilizó el tratamiento?</label><select id="m08-utilizo" class="form-select" data-m08-utilizo><option value="Si">Sí</option><option value="No">No</option></select>
                                <label class="visually-hidden" for="m08-resultado">Resultado</label><select id="m08-resultado" class="form-select" data-m08-resultado><option value="">Selecciona resultado</option><option value="Funciono">Funcionó</option><option value="No funciono">No funcionó</option><option value="Tuve efectos adversos">Tuve efectos adversos</option></select>
                                <label class="visually-hidden" for="m08-descripcion">Descripción</label><input id="m08-descripcion" class="form-control" data-m08-descripcion type="text" placeholder="Descripción documentada, si aplica">
                                <?php if (!empty($data['m08']['comments_available'])): ?><label class="visually-hidden" for="m08-comentarios">Comentarios</label><input id="m08-comentarios" class="form-control" data-m08-comentarios type="text" placeholder="Comentarios documentados"><?php endif; ?>
                                <button type="button" class="btn btn-primary" data-m08-confirm>Confirmar y guardar M08</button><button type="button" class="btn btn-outline-secondary" data-m08-cancel>Cancelar</button>
                            </div>
                            <?php else: ?><p class="patient-interview-muted">Tratamientos M08 completos o sin pendientes documentales.</p><?php endif; ?>
                            <p class="patient-interview-muted mb-0">Los procedimientos M08 permanecen separados del catálogo de tratamientos y no se capturan desde esta entrega porque el contrato existente crea filas vacías antes de completar la confirmación.</p>
                        </article>
                        <?php } ?>
                        <?php if (!empty($data['m09'])): $m09Evaluation = $data['m09']['evaluation'] ?? null; ?>
                        <article class="patient-interview-module-context interview-module-card" data-module="M09" data-m09-version="<?=$escape($data['m09']['version'] ?? '');?>">
                            <div class="patient-interview-message assistant"><div class="interview-message-header"><strong>Asistente · M09</strong></div><p class="interview-question-text">Revisaremos los cuatro campos de evaluación del dolor. El mapa corporal se mantiene separado y requiere selección visual.</p></div>
                            <?php if ($m09Evaluation): ?><div class="patient-interview-context-grid"><div><dt>Evaluación existente</dt><dd><?=trim((string)($m09Evaluation['dolor'] ?? '')) !== '' ? 'Respuesta documentada' : 'Pendiente';?></dd></div></div><?php endif; ?>
                            <div class="patient-interview-answer interview-composer"><div class="interview-composer-input"><label class="visually-hidden" for="m09-input">Respuesta M09</label><input id="m09-input" class="form-control" data-m09-input type="text" placeholder="dolor: Sí; tiempo: 3 meses; descripción: inició gradualmente; incremento: igual"></div><button type="button" class="btn btn-outline-primary chat-send-button" data-m09-interpret aria-label="Interpretar respuesta M09" title="Interpretar respuesta M09">➤</button></div>
                            <div class="patient-interview-proposal" data-m09-result hidden></div><div class="patient-interview-answer mt-2" data-m09-confirmation hidden><button type="button" class="btn btn-primary" data-m09-confirm>Confirmar y guardar M09</button><button type="button" class="btn btn-outline-secondary" data-m09-cancel>Cancelar</button></div>
                            <p class="patient-interview-muted mb-0">La propuesta no se guarda hasta que la confirmes. La localización anatómica no se interpreta desde texto libre.</p>
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
                        <div id="interview-status" class="patient-interview-muted" role="status" aria-live="polite"></div>
                    </div>
                </section>
            </div>
        </main>
        <?php include_once __DIR__ . '/../components/footer-doctor.php';?>
    </div>
</div>
<script>
(() => {
    const main = document.getElementById('main');
    const questions = Array.from(document.querySelectorAll('.patient-interview-question'));
    const status = document.getElementById('interview-status');
    const progress = document.getElementById('interview-progress');
    const progressBar = document.getElementById('interview-progress-bar');
    let active = questions.findIndex(question => !question.hidden);
    if (active < 0) active = 0;
    const setStatus = message => { if (status) status.textContent = message || ''; };
    const moduleCards = Array.from(document.querySelectorAll('[data-module]'));
    const conversationStages = [];
    const addModuleStage = selector => { const card = document.querySelector(selector); if (card) conversationStages.push({type:'module', card}); };
    addModuleStage('[data-module="M02"]');
    conversationStages.push({type:'m03', card:null});
    addModuleStage('[data-module="M04"]');
    addModuleStage('[data-module="M05"]');
    addModuleStage('[data-module="M05-V2"]');
    addModuleStage('[data-module="M06"]');
    addModuleStage('[data-module="M07"]');
    addModuleStage('[data-module="M08"]');
    addModuleStage('[data-module="M09"]');
    addModuleStage('[data-module="M09-MAP"]');
    const m02Card = document.querySelector('[data-module="M02"]');
    const firstM03Stage = Math.max(0, conversationStages.findIndex(stage => stage.type === 'm03'));
    let conversationStage = m02Card && Number(m02Card.dataset.m02Pending || 0) > 0 ? 0 : firstM03Stage;
    const hideConversationCards = () => { moduleCards.forEach(card => card.classList.add('chat-stage-hidden')); questions.forEach(question => { question.hidden = true; }); };
    const showConversationStage = index => {
        conversationStage = Math.max(0, Math.min(index, conversationStages.length - 1));
        if (typeof stopAssistantVoice === 'function') stopAssistantVoice();
        if (typeof stopRecognition === 'function') stopRecognition();
        hideConversationCards();
        const stage = conversationStages[conversationStage];
        if (!stage) return;
        if (stage.type === 'm03') { const pending = questions.findIndex(question => !question.dataset.savedValue); if (pending >= 0) showQuestion(pending); else { conversationStage += 1; showConversationStage(conversationStage); return; } }
        else { stage.card.classList.remove('chat-stage-hidden'); if (stage.card.dataset.module === 'M02') focusM02Pending(stage.card); }
        window.setTimeout(() => { if (typeof bindVoiceControls === 'function') bindVoiceControls(); }, 0);
        updateProgress();
    };
    const advanceConversation = () => { if (conversationStage < conversationStages.length - 1) showConversationStage(conversationStage + 1); else setStatus('Entrevista disponible para reanudar. No hay otra etapa autorizada pendiente.'); };
    const focusM02Pending = card => { const rows = Array.from(card.querySelectorAll('.patient-interview-context-grid > div')); rows.forEach(row => row.classList.add('chat-stage-hidden')); const next = rows.find(row => !row.dataset.chatDone && /Pendiente/i.test(row.querySelector('dd')?.textContent || '')); if (next) next.classList.remove('chat-stage-hidden'); return Boolean(next); };
    const advanceM02 = row => { const current = row; const card = row.closest('[data-module="M02"]'); if (current) current.dataset.chatDone = '1'; if (!focusM02Pending(card)) advanceConversation(); };
    const historyButton = document.querySelector('[data-chat-history]');
    const historyPanel = document.querySelector('[data-chat-history-panel]');
    const historyContent = document.querySelector('[data-chat-history-content]');
    if (historyButton && historyPanel && historyContent) historyButton.addEventListener('click', () => { historyPanel.hidden = !historyPanel.hidden; if (!historyPanel.hidden) { historyContent.textContent = ''; const rows = questions.filter(question => question.dataset.savedValue).map(question => `${question.querySelector('.patient-interview-message.assistant')?.textContent || 'Pregunta'}: ${question.dataset.savedValue}`); historyContent.textContent = rows.length ? rows.join(' · ') : 'No hay respuestas M03 registradas para consultar.'; } });
    const updateProgress = () => {
        const current = Math.min(conversationStage + 1, conversationStages.length || 1);
        if (progress) progress.textContent = `Etapa ${current} de ${conversationStages.length}`;
        if (progressBar) progressBar.style.width = `${conversationStages.length ? (current / conversationStages.length) * 100 : 0}%`;
    };
    const showQuestion = index => {
        active = index;
        if (typeof stopAssistantVoice === 'function') stopAssistantVoice();
        if (typeof stopRecognition === 'function') stopRecognition();
        moduleCards.forEach(card => card.classList.add('chat-stage-hidden'));
        questions.forEach((question, questionIndex) => { question.hidden = questionIndex !== active; });
        if (conversationStages[conversationStage]?.type === 'm03') questions.forEach(question => { question.hidden = questions.indexOf(question) !== active; });
        window.setTimeout(() => { if (typeof bindVoiceControls === 'function') bindVoiceControls(); }, 0);
        updateProgress();
    };
    const valueFor = question => {
        const input = question.querySelector('.interview-text');
        return input ? input.value.trim() : '';
    };
    const isDirectNumericValue = value => /^\d+(?:[.,]\d+)?$/.test(String(value || '').trim());
    const isDirectM03Candidate = question => question?.dataset.m03DirectCapable === '1' && isDirectNumericValue(valueFor(question));
    const updateM03SubmitAffordance = question => {
        if (!question) return;
        const button = question.querySelector('.interview-submit');
        if (!button) return;
        const direct = isDirectM03Candidate(question);
        button.setAttribute('aria-label', direct ? 'Guardar y continuar' : 'Interpretar respuesta');
        button.title = direct ? 'Guardar esta respuesta y continuar a la siguiente pregunta' : 'Interpretar respuesta';
        button.dataset.directMode = direct ? '1' : '0';
    };
    const showNextM03Question = (question, nextQuestionId = null) => {
        const next = nextQuestionId
            ? questions.find(candidate => Number(candidate.dataset.questionId) === Number(nextQuestionId) && !candidate.dataset.savedValue)
            : null;
        if (next) { showQuestion(questions.indexOf(next)); return; }
        const pending = questions.findIndex(candidate => !candidate.dataset.savedValue && candidate.dataset.eligible === '1');
        if (pending >= 0) { showQuestion(pending); return; }
        setStatus('M03/Tabaquismo completado.');
        advanceConversation();
    };
    const saveAndAdvanceM03Direct = async (question, value) => {
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
                    saveAndContinue: true
                })
            });
            const data = await response.json();
            if (!response.ok || !data.resultado || data.mode !== 'direct' || data.readback !== true) {
                throw new Error(data.mensaje || 'La respuesta necesita revisión antes de guardarse.');
            }
            question.dataset.savedValue = value;
            if (data.version) questions.forEach(item => { item.dataset.m03Version = data.version; });
            const message = question.querySelector('[data-confirmed-message]');
            if (message) { message.textContent = `Respuesta confirmada: ${value}`; message.hidden = false; }
            setStatus('Respuesta guardada. Siguiente pregunta…');
            showNextM03Question(question, data.next_question_id);
        } catch (error) {
            setStatus(error.message || 'No fue posible guardar la respuesta.');
        } finally {
            controls.forEach(control => { control.disabled = false; });
            question.dataset.m03Saving = '0';
        }
    };
    const confirmAndSave = async (question, value, proposalBox = null) => {
        if (!value) { setStatus('Selecciona o escribe una respuesta antes de continuar.'); return; }
        const buttons = question.querySelectorAll('button, input');
        buttons.forEach(button => { button.disabled = true; });
        setStatus('Guardando tu respuesta…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({ idPaciente: Number(main.dataset.patient), idPregunta: Number(question.dataset.questionId), idTema: Number(question.dataset.themeId), detalle: value, expectedVersion: question.dataset.m03Version || '', expectedRespuesta: question.dataset.savedValue || '' })
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible guardar la respuesta.');
            question.dataset.savedValue = value;
            if (data.version) questions.forEach(item => { item.dataset.m03Version = data.version; });
            const message = question.querySelector('[data-confirmed-message]');
            if (message) { message.textContent = `Paciente: ${value}`; message.hidden = false; }
            if (proposalBox) proposalBox.hidden = true;
            setStatus('Respuesta guardada en tu Historia Clínica.');
            if (active < questions.length - 1) showQuestion(active + 1);
            else { setStatus('M03/Tabaquismo completado.'); advanceConversation(); }
        } catch (error) {
            setStatus(error.message || 'No fue posible guardar la respuesta.');
        } finally { buttons.forEach(button => { button.disabled = false; }); }
    };
    const interpret = async question => {
        const utterance = valueFor(question);
        if (!utterance) { setStatus('Escribe una respuesta antes de continuar.'); return; }
        if (question.dataset.m03Interpreting === '1' || question.dataset.m03Saving === '1') return;
        if (isDirectM03Candidate(question)) return saveAndAdvanceM03Direct(question, utterance);
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
            const box = question.querySelector('[data-proposal]');
            if (box) box.hidden = true;
            if (proposal.kind === 'explanation') {
                if (box) { box.textContent = proposal.explanation || 'Esta pregunta corresponde al formulario clínico actual.'; box.hidden = false; }
                setStatus('Puedes responder la pregunta cuando estés preparado. No se guardó ningún dato.');
                return;
            }
            if (proposal.needs_clarification) {
                if (box) { box.textContent = proposal.clarification || 'Necesito una respuesta más precisa.'; box.hidden = false; }
                setStatus('Necesito una aclaración antes de proponer un valor.');
                return;
            }
            const text = box ? box.querySelector('[data-proposal-text]') : null;
            const input = box ? box.querySelector('[data-proposal-input]') : null;
            if (text) text.textContent = 'Interpretación propuesta: ' + (proposal.interpretation || proposal.structured_value);
            if (input) input.value = proposal.structured_value || '';
            if (box) box.hidden = false;
            setStatus('Revisa la propuesta y confirma o corrige el valor antes de guardar.');
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
            if (data.version) questions.forEach(item => { item.dataset.m03Version = data.version; });
            const message = question.querySelector('[data-confirmed-message]');
            if (message) { message.textContent = 'Respuesta confirmada: No'; message.hidden = false; }
            setStatus('Respuesta negativa guardada.');
            if (active < questions.length - 1) showQuestion(active + 1);
            else { setStatus('M03/Tabaquismo completado.'); advanceConversation(); }
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
    document.querySelectorAll('.interview-text').forEach(input => input.addEventListener('keydown', event => {
        if (event.key !== 'Enter' || event.shiftKey) return;
        event.preventDefault();
        interpret(input.closest('.patient-interview-question'));
    }));
    document.querySelectorAll('.interview-text').forEach(input => input.addEventListener('input', () => {
        const question = input.closest('.patient-interview-question');
        const box = question?.querySelector('[data-proposal]');
        if (box && !box.hidden && question.dataset.m03Interpreting !== '1') {
            box.hidden = true;
            setStatus('La respuesta cambió. Envía el nuevo texto para revisarlo; todavía no se guardó ningún dato.');
        }
        updateM03SubmitAffordance(question);
    }));
    questions.forEach(updateM03SubmitAffordance);
    document.querySelectorAll('.interview-composer input[type="text"]:not(.interview-text)').forEach(input => input.addEventListener('keydown', event => {
        if (event.key !== 'Enter' || event.shiftKey) return;
        event.preventDefault();
        input.closest('.interview-composer')?.querySelector('[data-m02-interpret], [data-m04-interpret], [data-m05-interpret], [data-m05-v2-interpret], [data-m06-interpret], [data-m08-interpret], [data-m09-interpret]')?.click();
    }));
    document.querySelectorAll('[data-proposal-confirm]').forEach(button => button.addEventListener('click', () => {
        const question = button.closest('.patient-interview-question');
        const box = question.querySelector('[data-proposal]');
        const input = box.querySelector('[data-proposal-input]');
        const value = input ? input.value.trim() : '';
        if (!value) { setStatus('Indica un valor antes de confirmar.'); return; }
        confirmAndSave(question, value, box);
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
            if (result) {
                result.textContent = proposal.clarification || proposal.message || ('Propuesta: ' + (proposal.detail || '') + (proposal.relative ? ', familiar: ' + proposal.relative : '') + (proposal.diabetes_type ? ', tipo: ' + proposal.diabetes_type : ''));
                result.hidden = false;
            }
            card.dataset.m02Proposal = JSON.stringify(proposal);
            card.dataset.m02Context = JSON.stringify(data.context || proposal.context || {});
            const confirmation = card.querySelector('[data-m02-confirmation]');
            if (confirmation) confirmation.hidden = !(proposal.can_confirm === true);
            setStatus(proposal.kind === 'clarification' ? 'M02 solicita una aclaración. No se guardó ningún dato.' : (proposal.can_confirm ? 'Revisa la propuesta M02 y confirma si es correcta.' : 'M02 preparó una propuesta solo informativa. No se guardó ningún dato.'));
        } catch (error) {
            setStatus(error.message || 'No fue posible revisar M02.');
        } finally { if (trigger) trigger.disabled = false; card.dataset.m02Interpreting = '0'; }
    };
    document.querySelectorAll('[data-m02-interpret]').forEach(button => button.addEventListener('click', () => {
        interpretM02(button.closest('[data-m02-disease]'), button);
    }));
    document.querySelectorAll('[data-m02-confirm]').forEach(button => button.addEventListener('click', async () => {
        const card = button.closest('[data-m02-disease]');
        if (card.dataset.m02Saving === '1') return;
        card.dataset.m02Saving = '1';
        const proposal = JSON.parse(card.dataset.m02Proposal || '{}');
        const confirmation = card.querySelector('[data-m02-confirmation]');
        button.disabled = true;
        setStatus('Guardando respuesta…');
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m02', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), enfermedad: card.dataset.m02Disease, expectedVersion: card.dataset.m02Version, proposal})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible guardar M02.');
            if (confirmation) confirmation.hidden = true;
            const result = card.querySelector('[data-m02-result]');
            if (result) { result.textContent = 'Respuesta M02 confirmada y guardada.'; result.hidden = false; }
            card.dataset.m02Version = data.record && data.record.version ? data.record.version : card.dataset.m02Version;
            card.removeAttribute('data-m02-context');
            setStatus('Respuesta M02 guardada.'); advanceM02(card);
        } catch (error) {
            setStatus(error.message || 'No fue posible guardar M02.');
        } finally { button.disabled = false; card.dataset.m02Saving = '0'; }
    }));
    document.querySelectorAll('[data-m02-cancel]').forEach(button => button.addEventListener('click', () => {
        const card = button.closest('[data-m02-disease]');
        const confirmation = card.querySelector('[data-m02-confirmation]');
        if (confirmation) confirmation.hidden = true;
        card.removeAttribute('data-m02-proposal');
        card.removeAttribute('data-m02-context');
        setStatus('La propuesta M02 se canceló. No se guardó ningún dato.');
    }));
    document.querySelectorAll('[data-m02-input]').forEach(input => input.addEventListener('input', () => {
        const card = input.closest('[data-m02-disease]');
        const interpretButton = card?.querySelector('[data-m02-interpret]');
        const hasText = input.value.trim() !== '';
        if (interpretButton) interpretButton.hidden = !hasText;
        if (card?.dataset.m02Proposal && card.dataset.m02LastInput !== input.value) {
            card.removeAttribute('data-m02-proposal');
            const confirmation = card.querySelector('[data-m02-confirmation]');
            if (confirmation) confirmation.hidden = true;
            setStatus('La respuesta cambió. Revisa la nueva propuesta; todavía no se guardó ningún dato.');
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
            advanceM02(card);
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
    document.querySelectorAll('[data-m04-interpret]').forEach(button => button.addEventListener('click', async () => {
        const card = button.closest('[data-module="M04"]');
        const input = card.querySelector('[data-m04-input]');
        const result = card.querySelector('[data-m04-result]');
        const utterance = input ? input.value.trim() : '';
        if (!utterance) { setStatus('Escribe una respuesta M04 para revisarla.'); return; }
        button.disabled = true;
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar', {
                method: 'POST', headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), modulo: 'M04', utterance})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible revisar M04.');
            const proposal = data.proposal || {};
            if (result) {
                result.textContent = proposal.clarification || proposal.message || (proposal.kind === 'm04_negative_review' ? 'No se identificó una cirugía.' : 'Propuesta M04 preparada.');
                result.hidden = false;
            }
            card.dataset.m04Proposal = JSON.stringify(proposal);
            const confirmation = card.querySelector('[data-m04-confirmation]');
            if (confirmation) {
                confirmation.hidden = !(proposal.can_confirm === true);
                const surgery = confirmation.querySelector('[data-m04-surgery]');
                const date = confirmation.querySelector('[data-m04-date]');
                const observations = confirmation.querySelector('[data-m04-observations]');
                if (surgery) surgery.value = proposal.surgery || '';
                if (date) date.value = proposal.date || '';
                if (observations) observations.value = proposal.observations || '';
            }
            setStatus(proposal.kind === 'clarification' ? 'M04 solicita una aclaración. No se guardó ningún dato.' : (proposal.can_confirm ? 'Revisa la propuesta M04 y confirma si es correcta.' : 'M04 preparó una propuesta sin guardarla.'));
        } catch (error) { setStatus(error.message || 'No fue posible revisar M04.'); }
        finally { button.disabled = false; }
    }));
    document.querySelectorAll('[data-m04-confirm]').forEach(button => button.addEventListener('click', async () => {
        const card = button.closest('[data-module="M04"]');
        const proposal = JSON.parse(card.dataset.m04Proposal || '{}');
        proposal.kind = 'm04_proposal';
        proposal.surgery = card.querySelector('[data-m04-surgery]')?.value.trim() || '';
        proposal.date = card.querySelector('[data-m04-date]')?.value || null;
        proposal.observations = card.querySelector('[data-m04-observations]')?.value.trim() || null;
        button.disabled = true;
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m04', {
                method: 'POST', headers: {'Content-Type': 'application/json', 'X-M03-CSRF': main.dataset.csrf},
                body: JSON.stringify({idPaciente: Number(main.dataset.patient), expectedVersion: card.dataset.m04Version, proposal})
            });
            const data = await response.json();
            if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible guardar M04.');
            card.dataset.m04Version = data.record?.version || card.dataset.m04Version;
            card.querySelector('[data-m04-confirmation]').hidden = true;
            setStatus('Antecedente M04 guardado.'); advanceConversation();
        } catch (error) { setStatus(error.message || 'No fue posible guardar M04.'); }
        finally { button.disabled = false; }
    }));
    document.querySelectorAll('[data-m04-cancel]').forEach(button => button.addEventListener('click', () => {
        const card = button.closest('[data-module="M04"]');
        card.querySelector('[data-m04-confirmation]').hidden = true;
        card.removeAttribute('data-m04-proposal');
        setStatus('La propuesta M04 se canceló. No se guardó ningún dato.');
    }));
    document.querySelectorAll('[data-m05-interpret]').forEach(button => button.addEventListener('click', async () => {
        const card=button.closest('[data-module="M05"]'), input=card.querySelector('[data-m05-input]'), result=card.querySelector('[data-m05-result]');
        const utterance=input?.value.trim() || ''; if(!utterance){setStatus('Escribe una respuesta M05 para revisarla.');return;} button.disabled=true;
        try { const qid=<?=json_encode((int)($m05Pending['question_id'] ?? 0));?>; const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),idPregunta:qid,modulo:'M05',utterance})}); const data=await response.json(); if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible revisar M05.'); const proposal=data.proposal||{}; card.dataset.m05Proposal=JSON.stringify(proposal); if(result){result.textContent=proposal.clarification||proposal.interpretation||('Propuesta: '+(proposal.structured_value||''));result.hidden=false;} const confirm=card.querySelector('[data-m05-confirmation]'); if(confirm){confirm.hidden=!(proposal.can_confirm===true); const value=confirm.querySelector('[data-m05-value]');if(value)value.value=proposal.structured_value||'';} setStatus(proposal.kind==='clarification'?'M05 solicita una aclaración. No se guardó ningún dato.':(proposal.can_confirm?'Revisa la propuesta M05 y confirma si es correcta.':'M05 preparó una propuesta sin guardarla.')); } catch(error){setStatus(error.message||'No fue posible revisar M05.');} finally{button.disabled=false;}
    }));
    document.querySelectorAll('[data-m05-confirm]').forEach(button => button.addEventListener('click', async () => {
        const card=button.closest('[data-module="M05"]'), proposal=JSON.parse(card.dataset.m05Proposal||'{}'); proposal.structured_value=card.querySelector('[data-m05-value]')?.value.trim()||''; button.disabled=true;
        try { const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m05',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),expectedVersion:card.dataset.m05Version,proposal})}); const data=await response.json(); if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible guardar M05.'); card.dataset.m05Version=data.version||card.dataset.m05Version; card.querySelector('[data-m05-confirmation]').hidden=true; setStatus('Respuesta M05 guardada.'); advanceConversation(); } catch(error){setStatus(error.message||'No fue posible guardar M05.');} finally{button.disabled=false;}
    }));
    document.querySelectorAll('[data-m05-cancel]').forEach(button => button.addEventListener('click', () => { const card=button.closest('[data-module="M05"]'); card.querySelector('[data-m05-confirmation]').hidden=true; card.removeAttribute('data-m05-proposal'); setStatus('La propuesta M05 se canceló. No se guardó ningún dato.'); }));
    document.querySelectorAll('[data-m05-v2-interpret]').forEach(button => button.addEventListener('click', async () => { const card=button.closest('[data-module="M05-V2"]'), utterance=card.querySelector('[data-m05-v2-input]')?.value.trim()||'', result=card.querySelector('[data-m05-v2-result]'); if(!utterance){setStatus('Escribe una respuesta M05 V2.');return;} button.disabled=true; try{const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),idPregunta:1,enfermedad_id:<?=json_encode((int)($m05Disease['disease_id']??0));?>,modulo:'M05',utterance})});const data=await response.json();if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible revisar M05 V2.');card.dataset.m05Proposal=JSON.stringify(data.proposal||{});if(result){result.textContent=data.proposal?.clarification||data.proposal?.interpretation||'Propuesta M05 V2 preparada.';result.hidden=false;}card.querySelector('[data-m05-v2-confirmation]').hidden=data.proposal?.can_confirm!==true;setStatus(data.proposal?.can_confirm?'Revisa y confirma la propuesta M05 V2.':'M05 V2 solicita una aclaración. No se guardó ningún dato.');}catch(error){setStatus(error.message||'No fue posible revisar M05 V2.');}finally{button.disabled=false;}}));
    document.querySelectorAll('[data-m05-v2-confirm]').forEach(button => button.addEventListener('click', async () => { const card=button.closest('[data-module="M05-V2"]'),proposal=JSON.parse(card.dataset.m05Proposal||'{}');button.disabled=true;try{const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m05',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),expectedVersion:card.dataset.m05Version,proposal})});const data=await response.json();if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible guardar M05 V2.');card.dataset.m05Version=data.version||card.dataset.m05Version;card.querySelector('[data-m05-v2-confirmation]').hidden=true;setStatus('Respuesta M05 V2 guardada.');advanceConversation();}catch(error){setStatus(error.message||'No fue posible guardar M05 V2.');}finally{button.disabled=false;}}));
    document.querySelectorAll('[data-m05-v2-cancel]').forEach(button => button.addEventListener('click',()=>{const card=button.closest('[data-module="M05-V2"]');card.querySelector('[data-m05-v2-confirmation]').hidden=true;card.removeAttribute('data-m05-proposal');setStatus('La propuesta M05 V2 se canceló. No se guardó ningún dato.');}));
    document.querySelectorAll('[data-m06-interpret]').forEach(button => button.addEventListener('click', async () => { const card=button.closest('[data-module="M06"]'),utterance=card.querySelector('[data-m06-input]')?.value.trim()||'',result=card.querySelector('[data-m06-result]');if(!utterance){setStatus('Escribe los datos de M06.');return;}button.disabled=true;let context={};try{context=JSON.parse(card.dataset.m06Context||'{}');}catch(error){context={};}try{const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),modulo:'M06',utterance,context})});const data=await response.json();if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible revisar M06.');const proposal=data.proposal||{};card.dataset.m06Proposal=JSON.stringify(proposal);card.dataset.m06Context=JSON.stringify(proposal.context||proposal.fields||{});if(result){renderM06Proposal(result,proposal);result.hidden=false;}card.querySelector('[data-m06-confirmation]').hidden=proposal.can_confirm!==true;setStatus(proposal.can_confirm?'Revisa la propuesta M06 y confirma si es correcta.':'M06 solicita una aclaración. No se guardó ningún dato.');}catch(error){setStatus(error.message||'No fue posible revisar M06.');}finally{button.disabled=false;}}));
    document.querySelectorAll('[data-m06-confirm]').forEach(button => button.addEventListener('click',async()=>{const card=button.closest('[data-module="M06"]'),proposal=JSON.parse(card.dataset.m06Proposal||'{}');button.disabled=true;try{const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m06',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),expectedVersion:card.dataset.m06Version,proposal})});const data=await response.json();if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible guardar M06.');card.dataset.m06Version=data.version||card.dataset.m06Version;card.querySelector('[data-m06-confirmation]').hidden=true;setStatus('Medicación M06 guardada.');advanceConversation();}catch(error){setStatus(error.message||'No fue posible guardar M06.');}finally{button.disabled=false;}}));
    document.querySelectorAll('[data-m06-cancel]').forEach(button=>button.addEventListener('click',()=>{const card=button.closest('[data-module="M06"]');card.querySelector('[data-m06-confirmation]').hidden=true;card.removeAttribute('data-m06-proposal');setStatus('La propuesta M06 se canceló. No se guardó ningún dato.');}));
    document.querySelectorAll('[data-m08-interpret]').forEach(button => button.addEventListener('click', async () => {
        const card=button.closest('[data-module="M08"]'), utterance=card.querySelector('[data-m08-input]')?.value.trim()||'', result=card.querySelector('[data-m08-result]');
        if(!utterance){setStatus('Escribe una respuesta M08.');return;} button.disabled=true;
        try{const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),modulo:'M08',tratamiento_id:<?=json_encode((int)($m08Pending['id_tratamiento']??0));?>,utterance})});const data=await response.json();if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible revisar M08.');const proposal=data.proposal||{};card.dataset.m08Proposal=JSON.stringify(proposal);if(result){result.textContent=proposal.clarification||proposal.interpretation||'Propuesta M08 preparada.';result.hidden=false;}const confirm=card.querySelector('[data-m08-confirmation]');confirm.hidden=proposal.can_confirm!==true;const fields=proposal.fields||{};if(confirm){confirm.querySelector('[data-m08-utilizo]').value=fields.utilizo||'Si';confirm.querySelector('[data-m08-resultado]').value=fields.resultado||'';confirm.querySelector('[data-m08-descripcion]').value=fields.descripcion||'';const comments=confirm.querySelector('[data-m08-comentarios]');if(comments)comments.value=fields.comentarios||'';}setStatus(proposal.kind==='clarification'?'M08 solicita una aclaración. No se guardó ningún dato.':(proposal.can_confirm?'Revisa la propuesta M08 y confirma si es correcta.':'M08 preparó una propuesta sin guardarla.'));}catch(error){setStatus(error.message||'No fue posible revisar M08.');}finally{button.disabled=false;}
    }));
    document.querySelectorAll('[data-m08-confirm]').forEach(button => button.addEventListener('click', async () => {const card=button.closest('[data-module="M08"]'),proposal=JSON.parse(card.dataset.m08Proposal||'{}');proposal.fields=proposal.fields||{};proposal.fields.utilizo=card.querySelector('[data-m08-utilizo]').value;proposal.fields.resultado=card.querySelector('[data-m08-resultado]').value;proposal.fields.descripcion=card.querySelector('[data-m08-descripcion]').value.trim();const comments=card.querySelector('[data-m08-comentarios]');if(comments)proposal.fields.comentarios=comments.value.trim();button.disabled=true;try{const response=await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m08',{method:'POST',headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf},body:JSON.stringify({idPaciente:Number(main.dataset.patient),expectedVersion:card.dataset.m08Version,proposal})});const data=await response.json();if(!response.ok||!data.resultado)throw new Error(data.mensaje||'No fue posible guardar M08.');card.dataset.m08Version=data.version||card.dataset.m08Version;card.querySelector('[data-m08-confirmation]').hidden=true;setStatus('Respuesta M08 guardada.');advanceConversation();}catch(error){setStatus(error.message||'No fue posible guardar M08.');}finally{button.disabled=false;}}));
    document.querySelectorAll('[data-m08-cancel]').forEach(button=>button.addEventListener('click',()=>{const card=button.closest('[data-module="M08"]');card.querySelector('[data-m08-confirmation]').hidden=true;card.removeAttribute('data-m08-proposal');setStatus('La propuesta M08 se canceló. No se guardó ningún dato.');}));
    document.querySelectorAll('[data-m09-interpret]').forEach(button => button.addEventListener('click', async () => {
        const card = button.closest('[data-module="M09"]'), utterance = card.querySelector('[data-m09-input]')?.value.trim() || '', result = card.querySelector('[data-m09-result]');
        if (!utterance) { setStatus('Escribe los datos de M09 para revisarlos.'); return; }
        button.disabled = true;
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/interpretar', {method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf}, body:JSON.stringify({idPaciente:Number(main.dataset.patient), modulo:'M09', utterance})});
            const data = await response.json(); if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible revisar M09.');
            const proposal = data.proposal || {}; card.dataset.m09Proposal = JSON.stringify(proposal);
            if (result) { result.textContent = proposal.clarification || proposal.interpretation || 'Propuesta M09 preparada.'; result.hidden = false; }
            card.querySelector('[data-m09-confirmation]').hidden = proposal.can_confirm !== true;
            setStatus(proposal.kind === 'clarification' ? 'M09 solicita una aclaración. No se guardó ningún dato.' : (proposal.can_confirm ? 'Revisa la propuesta M09 y confirma si es correcta.' : 'M09 preparó una propuesta sin guardarla.'));
        } catch (error) { setStatus(error.message || 'No fue posible revisar M09.'); } finally { button.disabled = false; }
    }));
    document.querySelectorAll('[data-m09-confirm]').forEach(button => button.addEventListener('click', async () => {
        const card = button.closest('[data-module="M09"]'), proposal = JSON.parse(card.dataset.m09Proposal || '{}'); button.disabled = true;
        try {
            const response = await fetch('<?=SERVIDOR;?>historia-clinica/entrevista/capturar-m09', {method:'POST', headers:{'Content-Type':'application/json','X-M03-CSRF':main.dataset.csrf}, body:JSON.stringify({idPaciente:Number(main.dataset.patient), expectedVersion:card.dataset.m09Version, proposal})});
            const data = await response.json(); if (!response.ok || !data.resultado) throw new Error(data.mensaje || 'No fue posible guardar M09.');
            card.dataset.m09Version = data.version || card.dataset.m09Version; card.querySelector('[data-m09-confirmation]').hidden = true; setStatus('Evaluación M09 guardada.'); advanceConversation();
        } catch (error) { setStatus(error.message || 'No fue posible guardar M09.'); } finally { button.disabled = false; }
    }));
    document.querySelectorAll('[data-m09-cancel]').forEach(button => button.addEventListener('click', () => { const card=button.closest('[data-module="M09"]'); card.querySelector('[data-m09-confirmation]').hidden=true; card.removeAttribute('data-m09-proposal'); setStatus('La propuesta M09 se canceló. No se guardó ningún dato.'); }));
    const renderM06Proposal = (result, proposal) => {
        result.textContent = '';
        if (proposal.clarification) { result.textContent = proposal.clarification; return; }
        const summary = document.createElement('p');
        summary.className = 'mb-2';
        summary.textContent = proposal.interpretation || 'Revisa los cinco datos de la medicación antes de guardarla.';
        result.appendChild(summary);
        const fields = proposal.fields || {};
        const labels = {medicamento:'Medicamento', descripcion:'Motivo de uso', tiempo_uso:'Duración', dosis:'Dosis', medico:'Médico'};
        const list = document.createElement('dl');
        list.className = 'interview-proposal-fields mb-0';
        Object.entries(labels).forEach(([key, label]) => {
            const item = document.createElement('div');
            const term = document.createElement('dt');
            const value = document.createElement('dd');
            term.textContent = label;
            value.textContent = String(fields[key] ?? '').trim() || 'No indicado';
            item.append(term, value);
            list.appendChild(item);
        });
        result.appendChild(list);
    };
    let activeRecognition = null;
    let activeMicButton = null;
    const speechApi = ('speechSynthesis' in window) ? window.speechSynthesis : null;
    const spanishVoice = () => { const voices = speechApi ? speechApi.getVoices() : []; return voices.find(voice => /^es-MX/i.test(voice.lang)) || voices.find(voice => /^es(-|_)/i.test(voice.lang)) || null; };
    const setVoiceButtonState = (button, speaking) => { button.dataset.speaking = speaking ? '1' : '0'; button.textContent = speaking ? '⏹ Detener' : '🔊 Leer'; button.title = speaking ? 'Detener lectura' : 'Leer pregunta en voz alta'; button.setAttribute('aria-label', speaking ? 'Detener lectura' : 'Leer pregunta en voz alta'); };
    const stopAssistantVoice = () => { if (speechApi) speechApi.cancel(); document.querySelectorAll('.chat-voice-button').forEach(button => setVoiceButtonState(button, false)); };
    const speakAssistantMessage = button => { if (!speechApi || typeof SpeechSynthesisUtterance === 'undefined') { setStatus('La lectura por voz no está disponible en este navegador.'); return; } if (button.dataset.speaking === '1') { stopAssistantVoice(); return; } stopAssistantVoice(); const message = button.closest('.patient-interview-message.assistant'); const text = message ? message.cloneNode(true) : null; text?.querySelectorAll('button').forEach(control => control.remove()); const value = text?.textContent.replace(/\s+/g, ' ').trim(); if (!value) { setStatus('No hay una pregunta disponible para leer.'); return; } const utterance = new SpeechSynthesisUtterance(value); const voice = spanishVoice(); utterance.lang = voice?.lang || 'es-MX'; if (voice) utterance.voice = voice; utterance.rate = .9; setVoiceButtonState(button, true); utterance.onend = utterance.onerror = () => setVoiceButtonState(button, false); speechApi.speak(utterance); };
    const recognitionConstructor = window.SpeechRecognition || window.webkitSpeechRecognition;
    let localRecognitionAvailable = Boolean(recognitionConstructor && recognitionConstructor.prototype && 'processLocally' in recognitionConstructor.prototype);
    const detectLocalRecognition = async () => {
        if (!localRecognitionAvailable) return false;
        const availability = recognitionConstructor && recognitionConstructor.available;
        if (typeof availability !== 'function') return true;
        try {
            const state = await availability.call(recognitionConstructor, {langs: ['es-MX']});
            localRecognitionAvailable = state === 'available';
        } catch (error) {
            localRecognitionAvailable = false;
        }
        bindVoiceControls();
        return localRecognitionAvailable;
    };
    const stopRecognition = () => { if (activeRecognition) { try { activeRecognition.abort(); } catch (error) {} } if (activeMicButton) { activeMicButton.classList.remove('is-listening'); activeMicButton.textContent = '🎙'; activeMicButton.title = 'Dictado local en español'; activeMicButton.setAttribute('aria-label', 'Responder mediante micrófono'); } activeRecognition = null; activeMicButton = null; };
    const startRecognition = (button, input) => { if (!localRecognitionAvailable) { setStatus('El navegador no confirma reconocimiento local. Por privacidad, usa la captura escrita.'); return; } if (activeRecognition) { stopRecognition(); return; } stopAssistantVoice(); const recognition = new recognitionConstructor(); recognition.lang = 'es-MX'; recognition.continuous = false; recognition.interimResults = false; recognition.maxAlternatives = 1; recognition.processLocally = true; activeRecognition = recognition; activeMicButton = button; button.classList.add('is-listening'); button.textContent = '⏹'; button.title = 'Detener micrófono'; button.setAttribute('aria-label', 'Detener micrófono'); setStatus('Escuchando. La transcripción aparecerá en el campo; todavía no se ha guardado.'); recognition.onresult = event => { const transcript = event.results?.[0]?.[0]?.transcript || ''; if (transcript) { input.value = transcript; input.dispatchEvent(new Event('input', {bubbles:true})); setStatus('Transcripción lista. Corrígela si hace falta y pulsa enviar.'); } }; recognition.onerror = event => { setStatus(event.error === 'not-allowed' ? 'Permiso de micrófono denegado. Puedes responder por escrito.' : 'No fue posible transcribir; puedes responder por escrito.'); }; recognition.onend = stopRecognition; try { recognition.start(); } catch (error) { stopRecognition(); setStatus('No fue posible iniciar el micrófono. Puedes responder por escrito.'); } };
    function bindVoiceControls() {
        const activeQuestionMessage = document.querySelector('.patient-interview-question:not([hidden]) > .patient-interview-message.assistant');
        const activeModuleMessage = document.querySelector('.interview-module-card:not(.chat-stage-hidden) > .patient-interview-message.assistant');
        document.querySelectorAll('.patient-interview-message.assistant:not([data-voice-bound])').forEach(message => {
            const visible = !message.closest('.chat-stage-hidden') && !message.hidden;
            if (!visible || (message !== activeQuestionMessage && message !== activeModuleMessage)) return;
            const header = message.querySelector('.interview-message-header');
            if (!header) return;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-secondary btn-sm chat-voice-button ms-2';
            setVoiceButtonState(button, false);
            button.addEventListener('click', () => speakAssistantMessage(button));
            header.appendChild(button);
            message.dataset.voiceBound = '1';
        });
        document.querySelectorAll('.patient-interview-answer input[type="text"]').forEach(input => {
            const answer = input.closest('.patient-interview-answer');
            const visible = !input.closest('.chat-stage-hidden') && !input.hidden && !answer?.hidden;
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
            const existingButton = host.querySelector('.chat-mic-button');
            const existingIndicator = host.querySelector('.chat-mic-unavailable');
            if (localRecognitionAvailable) {
                existingIndicator?.remove();
                if (!existingButton) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-outline-secondary chat-mic-button';
                    button.textContent = '🎙';
                    button.title = 'Dictado local en español';
                    button.setAttribute('aria-label', 'Responder mediante micrófono');
                    button.addEventListener('click', () => startRecognition(button, input));
                    host.appendChild(button);
                }
            } else {
                existingButton?.remove();
                if (!existingIndicator) {
                    const indicator = document.createElement('span');
                    indicator.className = 'chat-mic-unavailable';
                    indicator.textContent = '🎙';
                    indicator.title = 'El navegador no confirma reconocimiento local; usa el teclado.';
                    indicator.setAttribute('role', 'img');
                    indicator.setAttribute('aria-label', '🎙 No disponible; el navegador no confirma reconocimiento local; usa la captura escrita');
                    host.appendChild(indicator);
                }
            }
            input.dataset.micBound = '1';
        });
    }
    window.addEventListener('pagehide', () => { stopAssistantVoice(); stopRecognition(); });
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
    detectLocalRecognition();
})();
</script>
</body>
</html>
