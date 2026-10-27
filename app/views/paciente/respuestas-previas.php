<?php
declare(strict_types=1);

$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];
$m02StatusItems = is_array($data['m02StatusItems'] ?? null) ? $data['m02StatusItems'] : [];
$m03Themes = is_array($data['m03Themes'] ?? null) ? $data['m03Themes'] : [];
$m04Entries = is_array($data['m04Entries'] ?? null) ? $data['m04Entries'] : [];
$m05V1Themes = is_array($data['m05V1Themes'] ?? null) ? $data['m05V1Themes'] : [];
$m05V2Diseases = is_array($data['m05V2Diseases'] ?? null) ? $data['m05V2Diseases'] : [];
$statusLabel = static fn(string $status): string => match ($status) {
    'REGISTRADA' => 'Registrada',
    'PENDIENTE' => 'Pendiente',
    'POR_ACLARAR' => 'Por aclarar',
    'NO_APLICA' => 'No aplica',
    'REQUIERE_REVISION' => 'Requiere revisión',
    'SIN_REGISTRO' => 'Sin respuesta registrada',
    default => 'Requiere revisión',
};
$reviewUrl = SERVIDOR . 'historia-clinica/respuestas-previas/m02';
$m03ReviewUrl = is_string($data['m03EditUrl'] ?? null) ? $data['m03EditUrl'] : SERVIDOR . 'historia-clinica/respuestas-previas/m03';
$m04ReviewUrl = is_string($data['m04EditUrl'] ?? null) ? $data['m04EditUrl'] : SERVIDOR . 'historia-clinica/respuestas-previas/m04';
$m05V1ReviewUrl = is_string($data['m05V1EditUrl'] ?? null) ? $data['m05V1EditUrl'] : SERVIDOR . 'historia-clinica/respuestas-previas/m05/v1';
$m05V2ReviewUrl = is_string($data['m05V2EditUrl'] ?? null) ? $data['m05V2EditUrl'] : SERVIDOR . 'historia-clinica/respuestas-previas/m05/v2';
$bootstrapCss = RUTA_CSS . 'bootstrap.css';
$appCss = RUTA_CSS . 'app.css';
?>
<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?=$escape($data['title'] ?? 'Respuestas previas');?></title>
    <link rel="shortcut icon" href="<?=RUTA_IMAGES;?>logo-clinica.png">
    <link rel="stylesheet" href="<?=$escape($bootstrapCss);?>">
    <link rel="stylesheet" href="<?=$escape($appCss);?>">
    <style>
        body.previous-responses-page { background:#f6f5fb; color:#241b45; }
        .previous-responses-content { max-width:1120px; margin-inline:auto; padding:clamp(1rem,3vw,2.2rem); }
        .previous-responses-hero, .previous-responses-notice, .previous-response-card { border:1px solid #e8e2f4; border-radius:1.15rem; background:#fff; box-shadow:0 .3rem 1rem rgba(56,38,91,.055); }
        .previous-responses-hero { display:flex; align-items:flex-end; justify-content:space-between; gap:1.25rem; padding:clamp(1.25rem,3vw,2rem); margin-bottom:1rem; }
        .previous-responses-hero h1 { margin:.15rem 0 .45rem; color:#27154e; font-size:clamp(1.8rem,3.6vw,2.5rem); font-weight:750; }
        .previous-responses-hero p:last-child { margin:0; color:#61708d; }
        .previous-responses-eyebrow { margin:0; color:#5b16c8; font-size:.78rem; font-weight:750; letter-spacing:.08em; }
        .previous-responses-notice { margin-bottom:1.4rem; padding:1rem 1.25rem; background:linear-gradient(110deg,#f4efff,#fbfaff); }
        .previous-responses-notice strong { color:#4f20a8; }
        .previous-responses-notice p { margin:.25rem 0 0; color:#596985; }
        .previous-responses-section-title { margin:1.5rem 0 .85rem; color:#35215f; font-size:1.15rem; font-weight:700; }
        .m04-section-heading { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
        .m04-section-heading .previous-responses-section-title { margin-bottom:.85rem; }
        .m04-create-dialog { width:calc(100% - 2rem); max-width:38rem; max-height:min(90vh,48rem); padding:0; overflow:auto; border:1px solid #e8e2f4; border-radius:1.15rem; background:#fff; color:#241b45; box-shadow:0 1rem 3rem rgba(32,20,66,.24); }
        .m04-create-dialog::backdrop { background:rgba(28,19,54,.48); backdrop-filter:blur(2px); }
        .m04-create-dialog-form { gap:.8rem; margin:0; padding:clamp(1rem,4vw,1.5rem); }
        .m04-create-dialog-title { margin:0 0 .2rem; color:#35215f; font-size:1.25rem; font-weight:750; }
        .m04-create-guidance { margin:0; color:#596985; font-size:.92rem; }
        .previous-responses-page .m04-create-autocomplete-host { position:relative; }
        .previous-responses-page .m04-create-dialog .m04-surgery-suggestions[hidden] { display:none !important; }
        .previous-responses-page .m04-create-dialog .m04-surgery-suggestions {
            position:absolute; z-index:20; top:calc(100% + .3rem); left:0; width:100%; max-height:min(16rem,36vh); overflow-y:auto;
            margin:0; padding:.3rem; list-style:none; border:1px solid #e3d7f5; border-radius:.75rem; background:#fff;
            box-shadow:0 .5rem 1.25rem rgba(39,21,78,.14);
        }
        .previous-responses-page .m04-create-dialog .m04-surgery-suggestion {
            display:flex; align-items:baseline; gap:.35rem; width:100%; padding:.55rem .7rem; border:0; border-radius:.5rem;
            color:#292540; background:transparent; font:inherit; line-height:1.35; text-align:left; cursor:pointer;
        }
        .previous-responses-page .m04-create-dialog .m04-surgery-suggestion[aria-selected="true"],
        .previous-responses-page .m04-create-dialog .m04-surgery-suggestion:hover { color:#2b174c; background:#f2ecfc; }
        .previous-responses-page .m04-create-dialog .m04-surgery-suggestion:focus-visible { outline:2px solid #7041b5; outline-offset:-2px; }
        .previous-responses-page .m04-create-dialog .m04-surgery-suggestion-name { font-weight:650; }
        .previous-responses-page .m04-create-dialog .m04-surgery-suggestion-detail { color:#5d6b86; font-size:.9em; }
        .m04-create-field-error { margin:-.45rem 0 0; color:#a32737; font-size:.88rem; }
        .m04-create-field-error[hidden] { display:none !important; }
        .m04-create-actions { justify-content:flex-end; }
        .m04-create-actions > .btn { min-width:8rem; }
        .m04-create-trigger { white-space:nowrap; }
        .previous-responses-module-divider { margin:2rem 0 1rem; border:0; border-top:1px solid #ded5ed; opacity:1; }
        .previous-responses-theme { margin:1.1rem 0 1.4rem; }
        .previous-responses-theme-title { margin:0 0 .7rem; color:#4d2486; font-size:1.02rem; font-weight:750; }
        .previous-responses-list { display:grid; gap:.9rem; }
        .previous-response-card { overflow:hidden; }
        .previous-response-heading { padding:.8rem 1.15rem; color:#5120a6; background:#f3edff; font-size:.84rem; font-weight:750; }
        .previous-response-body { padding:1.1rem 1.2rem 1.2rem; }
        .previous-response-question { margin:0 0 1rem; color:#26194e; font-size:clamp(1.15rem,2.4vw,1.4rem); font-weight:700; line-height:1.35; }
        .previous-response-label { display:block; margin-bottom:.35rem; color:#5d6a83; font-size:.84rem; font-weight:700; }
        .previous-response-answer { margin:0; padding:.8rem .9rem; border:1px solid #e5e0f0; border-radius:.75rem; background:#fcfbff; color:#34425f; line-height:1.55; overflow-wrap:anywhere; white-space:pre-wrap; }
        .previous-response-type { margin:.55rem 0 0; color:#5c6680; font-size:.92rem; }
        .previous-response-actions { display:flex; flex-wrap:wrap; align-items:center; gap:.65rem; margin-top:1rem; }
        .previous-response-edit, .previous-response-save { border-color:#5b16c8; background:#5b16c8; color:#fff; }
        .previous-response-edit:hover, .previous-response-save:hover { border-color:#4810a6; background:#4810a6; color:#fff; }
        .previous-response-edit:disabled { border-color:#cfc8db; background:#eceaf1; color:#626174; opacity:1; }
        .previous-response-editor { display:grid; gap:.75rem; margin-top:1rem; }
        .previous-response-relatives { margin:0; padding:.85rem; border:1px solid #e5e0f0; border-radius:.75rem; }
        .previous-response-relatives legend { float:none; width:auto; padding:0 .25rem; }
        .previous-response-relative-options { display:flex; flex-wrap:wrap; gap:.5rem 1rem; margin-bottom:.55rem; }
        .previous-response-relative-option { display:inline-flex; align-items:center; gap:.4rem; color:#34425f; }
        .previous-response-editor[hidden], .previous-response-display[hidden] { display:none !important; }
        .previous-response-message { min-height:1.4em; margin:.2rem 0 0; color:#52627a; font-size:.92rem; }
        .previous-response-message[data-state="error"] { color:#a32737; }
        .previous-response-message[data-state="success"] { color:#13714e; }
        .previous-responses-empty { padding:1.25rem; border:1px dashed #cfc5e5; border-radius:1rem; background:#fff; color:#596985; }
        .previous-responses-footer { display:flex; justify-content:flex-end; margin-top:1.35rem; }
        body.previous-responses-page .previous-responses-close { transition:color .18s ease, background-color .18s ease, border-color .18s ease; }
        body.previous-responses-page .previous-responses-close:hover,
        body.previous-responses-page .previous-responses-close:focus-visible,
        body.previous-responses-page .previous-responses-close:active { background:var(--clinic-primary); border-color:var(--clinic-primary); color:#FFFFFF; }
        body.previous-responses-page .previous-responses-close:focus-visible { outline:3px solid var(--clinic-primary) !important; outline-offset:3px; box-shadow:0 0 0 2px #fff; }
        @media (prefers-reduced-motion:reduce) {
            body.previous-responses-page .previous-responses-close { transition:none; }
        }
        .previous-responses-filters { display:grid; grid-template-columns:minmax(0,1fr) minmax(11rem,.34fr); gap:.85rem 1rem; align-items:end; margin:0 0 1.35rem; padding:1rem 1.2rem; border:1px solid #e8e2f4; border-radius:1rem; background:#fff; box-shadow:0 .25rem .8rem rgba(56,38,91,.04); }
        .previous-responses-filter-label { display:block; margin-bottom:.35rem; color:#4a3a70; font-size:.88rem; font-weight:700; }
        .previous-responses-filter-options { display:flex; align-items:center; justify-content:space-between; gap:1rem; grid-column:1 / -1; }
        .previous-responses-filter-check { display:inline-flex; align-items:center; gap:.55rem; margin:0; color:#4e5d79; }
        .previous-responses-filter-meta { display:flex; align-items:center; gap:1rem; color:#5c6680; }
        .previous-responses-filter-count { margin:0; font-weight:700; }
        .previous-responses-filter-lock { grid-column:1 / -1; margin:0; color:#765d21; font-size:.88rem; }
        .previous-responses-no-results { margin:0 0 1rem; padding:1rem 1.2rem; border:1px dashed #cfc5e5; border-radius:.9rem; background:#fff; color:#596985; }
        .previous-response-heading { display:flex; align-items:center; justify-content:space-between; gap:.7rem; }
        .previous-response-status { display:inline-flex; align-items:center; border-radius:999px; padding:.22rem .62rem; background:#ebe6f4; color:#54476d; font-size:.76rem; font-weight:700; white-space:nowrap; }
        .previous-response-status[data-status="PENDIENTE"], .previous-response-status[data-status="POR_ACLARAR"] { background:#fff2d8; color:#71500c; }
        .previous-response-status[data-status="NO_APLICA"] { background:#eef0f5; color:#5c6475; }
        .previous-response-status[data-status="REQUIERE_REVISION"] { background:#fce8e8; color:#8e3030; }
        .previous-response-pending-action { margin-top:.85rem; }
        [data-review-card][hidden], [data-review-module][hidden], [data-review-theme][hidden] { display:none !important; }
        @media (max-width:575.98px) {
            .previous-responses-content { padding:.85rem; }
            .previous-responses-hero { align-items:stretch; flex-direction:column; }
            .previous-responses-hero > .btn { width:100%; }
            .m04-section-heading { align-items:stretch; flex-direction:column; }
            .m04-create-trigger { width:100%; }
            .previous-responses-page .m04-create-dialog .m04-surgery-suggestion { display:block; padding:.55rem .6rem; }
            .previous-responses-page .m04-create-dialog .m04-surgery-suggestion-detail { display:block; margin-top:.08rem; }
            .previous-response-body { padding:1rem; }
            .previous-response-actions > .btn { flex:1 1 100%; }
            .m04-create-actions > .btn { flex:1 1 100%; }
            .previous-responses-filters { grid-template-columns:1fr; padding:.9rem; }
            .previous-responses-filter-options { align-items:stretch; flex-direction:column; }
            .previous-responses-filter-meta { justify-content:space-between; }
        }
    </style>
</head>
<body class="app-shell-body app-shell-patient dashboard-patient previous-responses-page">
<div id="app">
    <?=$data['sidebar'];?>
        <div id="main" data-csrf="<?=$escape($data['csrf'] ?? '');?>" data-edit-url="<?=$escape($reviewUrl);?>" data-m03-edit-url="<?=$escape($m03ReviewUrl);?>" data-m04-edit-url="<?=$escape($m04ReviewUrl);?>" data-m04-create-url="<?=$escape($data['m04CreateUrl'] ?? SERVIDOR . 'historia-clinica/respuestas-previas/m04/nueva');?>" data-m04-version="<?=$escape($data['m04Version'] ?? '');?>" data-m05-v1-edit-url="<?=$escape($m05V1ReviewUrl);?>" data-m05-v2-edit-url="<?=$escape($m05V2ReviewUrl);?>" data-m05-v1-version="<?=$escape($data['m05V1Version'] ?? '');?>" data-m05-v2-version="<?=$escape($data['m05V2Version'] ?? '');?>">
        <nav class="navbar navbar-header navbar-expand navbar-light portal-topbar" aria-label="Barra de herramientas del portal del paciente">
            <button type="button" class="sidebar-toggler border-0 bg-transparent" aria-label="Abrir menú principal" aria-controls="sidebar" aria-expanded="true"><span class="navbar-toggler-icon"></span></button>
            <div class="ms-auto"><span class="portal-badge">Portal del paciente</span></div>
        </nav>
        <main class="main-content previous-responses-content">
            <header class="previous-responses-hero">
                <div>
                    <p class="previous-responses-eyebrow">MI HISTORIA CLÍNICA</p>
                    <h1>Respuestas previas</h1>
                    <p>Revisa tus respuestas por módulo y completa la información que necesites.</p>
                </div>
                <a class="btn btn-outline-primary previous-responses-close" href="<?=htmlspecialchars(SERVIDOR . 'historia-clinica/entrevista', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>">Cerrar revisión y volver a la entrevista</a>
            </header>

            <section class="previous-responses-filters" aria-label="Buscar preguntas y filtrar respuestas" data-review-filters>
                <div>
                    <label class="previous-responses-filter-label" for="previous-responses-search">Buscar por módulo, tema, pregunta o identificador</label>
                    <input class="form-control" id="previous-responses-search" type="search" autocomplete="off" data-review-search>
                </div>
                <div>
                    <label class="previous-responses-filter-label" for="previous-responses-module">Módulo</label>
                    <select class="form-select" id="previous-responses-module" data-review-module-filter>
                        <option value="">Todos</option>
                        <option value="M02">M02</option>
                        <option value="M03">M03</option>
                        <option value="M04">M04</option>
                        <option value="M05">M05</option>
                    </select>
                </div>
                <div class="previous-responses-filter-options">
                    <label class="previous-responses-filter-check" for="previous-responses-pending">
                        <input class="form-check-input" id="previous-responses-pending" type="checkbox" data-review-pending-filter>
                        <span>Solo pendientes <small class="d-block text-muted">Incluye por aclarar y requiere revisión; excluye No aplica.</small></span>
                    </label>
                    <div class="previous-responses-filter-meta">
                        <p class="previous-responses-filter-count" data-review-count aria-live="polite">0 resultados</p>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-review-clear>Limpiar filtros</button>
                    </div>
                </div>
                <p class="previous-responses-filter-lock" data-review-filter-lock role="status" hidden>Guarda o cancela la edición abierta para cambiar los filtros. Tu borrador se conservará.</p>
            </section>
            <p class="previous-responses-no-results" data-review-no-results hidden>No encontramos preguntas con esos filtros.</p>
            <p class="text-muted small">Este apoyo de preparación muestra información disponible de M02, M03, M04 y M05; no representa toda la historia clínica ni sustituye la revisión con tu equipo médico.</p>

            <aside class="previous-responses-notice" aria-label="Información sobre la revisión">
                <strong>Revisa y corrige tus respuestas</strong>
                <p>Puedes completar o corregir lo que registraste. Al guardar, se mostrará únicamente la respuesta actualizada.</p>
                <?php if (!empty($data['m02Finalized'])): ?><p class="mt-2 mb-0">M02 está finalizado. Revisar o corregir una respuesta no reabre el módulo ni cambia su progreso.</p><?php endif; ?>
            </aside>

            <section aria-labelledby="m02-previous-title" data-review-module="M02">
                <h2 id="m02-previous-title" class="previous-responses-section-title">M02 · Antecedentes heredofamiliares</h2>
                <?php if ($entries === []): ?>
                    <p class="previous-responses-empty">Aún no hay respuestas M02 registradas para consultar.</p>
                <?php else: ?>
                    <div class="previous-responses-list">
                        <?php foreach ($entries as $index => $entry): ?>
                            <?php $cardId = 'm02-previous-' . ($index + 1); ?>
                            <?php
                                $entryNumber = $entry['number'] ?? ($index + 1);
                                $entryReviewStatus = is_string($entry['review_status'] ?? null) ? $entry['review_status'] : 'REQUIERE_REVISION';
                                $entryReviewPending = (bool)($entry['review_pending'] ?? ($entryReviewStatus === 'REQUIERE_REVISION'));
                            ?>
                            <article class="previous-response-card" data-review-card data-review-module-id="M02" data-review-module-name="Antecedentes heredofamiliares" data-review-theme="Antecedentes heredofamiliares" data-review-question="<?=$escape($entry['question']);?>" data-review-question-number="<?=$escape($entryNumber);?>" data-review-question-id="<?=$escape($entry['stable_id']);?>" data-review-status="<?=$escape($entryReviewStatus);?>" data-review-pending="<?=$entryReviewPending ? '1' : '0';?>" data-m02-review-card data-stable-id="<?=$escape($entry['stable_id']);?>" data-version="<?=$escape($entry['version']);?>" data-editable="<?=$entry['editable'] ? '1' : '0';?>">
                                <div class="previous-response-heading"><span>Pregunta <?=$escape($entryNumber);?></span><span class="previous-response-status" data-status="<?=$escape($entryReviewStatus);?>"><?=$escape($statusLabel($entryReviewStatus));?></span></div>
                                <div class="previous-response-body">
                                    <h3 class="previous-response-question"><?=$escape($entry['question']);?></h3>
                                    <div class="previous-response-display" data-response-display>
                                        <span class="previous-response-label">Respuesta registrada</span>
                                        <p class="previous-response-answer" id="<?=$cardId;?>-answer" data-current-answer><?=$escape($entry['answer']);?></p>
                                        <?php if ($entry['disease'] === 'Diabetes Mellitus' && $entry['diabetes_type'] !== ''): ?>
                                            <p class="previous-response-type" data-current-diabetes-type>Tipo de diabetes: <span data-type-value><?=$escape($entry['diabetes_type']);?></span></p>
                                        <?php elseif ($entry['disease'] === 'Diabetes Mellitus'): ?>
                                            <p class="previous-response-type" data-current-diabetes-type hidden>Tipo de diabetes: <span data-type-value></span></p>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($entry['editable']): ?>
                                        <form class="previous-response-editor" data-response-editor data-edit-value="<?=$escape($entry['answer_input']);?>" data-relative-values="<?=$escape(json_encode($entry['relatives'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));?>" hidden>
                                            <label class="previous-response-label" for="<?=$cardId;?>-input"><?=$entry['structured_editable'] ? 'Descripción adicional (opcional)' : 'Respuesta / descripción familiar';?></label>
                                            <textarea class="form-control" id="<?=$cardId;?>-input" data-answer-input maxlength="2000" rows="3" placeholder="<?=$escape($entry['structured_editable'] ? 'Puedes agregar o corregir una descripción complementaria.' : 'Escribe quién tiene esta enfermedad o responde No.');?>" <?=$entry['structured_editable'] ? '' : 'required';?>><?=$escape($entry['answer_input']);?></textarea>
                                            <?php if ($entry['structured_editable']): ?>
                                                <fieldset class="previous-response-relatives" data-relative-editor>
                                                    <legend class="previous-response-label">Parentescos registrados</legend>
                                                    <div class="previous-response-relative-options">
                                                        <?php foreach (($entry['relative_options'] ?? []) as $relativeValue => $relativeLabel): ?>
                                                            <label class="previous-response-relative-option"><input class="form-check-input" type="checkbox" data-relative-input value="<?=$escape($relativeValue);?>" <?=in_array($relativeValue, $entry['relatives'] ?? [], true) ? 'checked' : '';?>> <span><?=$escape($relativeLabel);?></span></label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <small class="text-muted">Conserva al menos un parentesco. Los cambios se guardan junto con la respuesta.</small>
                                                </fieldset>
                                            <?php endif; ?>
                                            <?php if ($entry['disease'] === 'Diabetes Mellitus'): ?>
                                                <label class="previous-response-label" for="<?=$cardId;?>-type">Tipo de diabetes (opcional)</label>
                                                <select class="form-select" id="<?=$cardId;?>-type" data-diabetes-type>
                                                    <option value="__KEEP__" selected>Conservar el tipo actual</option>
                                                    <option value="Tipo 1">Tipo 1</option>
                                                    <option value="Tipo 2">Tipo 2</option>
                                                    <option value="Gestacional">Gestacional</option>
                                                </select>
                                            <?php endif; ?>
                                            <div class="previous-response-actions">
                                                <button type="submit" class="btn previous-response-save" data-save-response>Guardar cambios</button>
                                                <button type="button" class="btn btn-outline-secondary" data-cancel-edit>Cancelar</button>
                                            </div>
                                            <p class="previous-response-message" data-edit-message aria-live="polite"></p>
                                        </form>
                                    <?php endif; ?>

                                    <div class="previous-response-actions" data-display-actions>
                                        <button type="button" class="btn previous-response-edit" data-start-edit <?=$entry['editable'] ? '' : 'disabled aria-disabled="true"';?> aria-describedby="<?=$cardId;?>-edit-note">Editar</button>
                                    </div>
                                    <p class="previous-response-message" id="<?=$cardId;?>-edit-note" data-edit-note><?=$escape($entry['edit_blocker']);?></p>
                                    <p class="previous-response-message" data-saved-message aria-live="polite"></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($m02StatusItems !== []): ?>
                    <div class="previous-responses-list">
                        <?php foreach ($m02StatusItems as $item): ?>
                            <?php $statusCardId = 'm02-status-' . (int)$item['number']; ?>
                            <article class="previous-response-card" data-review-card data-review-module-id="M02" data-review-module-name="Antecedentes heredofamiliares" data-review-theme="Antecedentes heredofamiliares" data-review-question="<?=$escape($item['question']);?>" data-review-question-number="<?=$escape($item['number']);?>" data-review-question-id="<?=$escape($item['stable_id']);?>" data-review-status="<?=$escape($item['status']);?>" data-review-pending="<?=$item['pending'] ? '1' : '0';?>">
                                <div class="previous-response-heading"><span>Pregunta <?=$escape($item['number']);?></span><span class="previous-response-status" data-status="<?=$escape($item['status']);?>"><?=$escape($statusLabel($item['status']));?></span></div>
                                <div class="previous-response-body">
                                    <h3 class="previous-response-question"><?=$escape($item['question']);?></h3>
                                    <?php if ($item['status'] === 'PENDIENTE'): ?>
                                        <p class="previous-response-type">Pendiente de responder.</p>
                                        <a class="btn btn-outline-primary previous-responses-pending-action" href="<?=htmlspecialchars(SERVIDOR . 'historia-clinica/entrevista', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>">Ir a la entrevista</a>
                                    <?php else: ?>
                                        <p class="previous-responses-empty"><?=$escape($item['reason']);?></p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <hr class="previous-responses-module-divider">
            <section aria-labelledby="m03-previous-title" data-review-module="M03">
                <h2 id="m03-previous-title" class="previous-responses-section-title">M03 · Antecedentes personales no patológicos</h2>
                <?php if (!empty($data['m03Finalized'])): ?><p class="previous-responses-notice">M03 está finalizado. La revisión no reabre el módulo ni modifica su progreso.</p><?php endif; ?>
                <?php if ($m03Themes === []): ?>
                    <p class="previous-responses-empty">No se pudo cargar el catálogo M03 para esta sesión.</p>
                <?php else: ?>
                    <?php foreach ($m03Themes as $themeIndex => $theme): ?>
                        <?php $themeId = 'm03-previous-theme-' . (int)($theme['theme_id'] ?? $themeIndex + 1); ?>
                        <section class="previous-responses-theme" aria-labelledby="<?=$escape($themeId);?>" data-review-theme>
                            <h3 class="previous-responses-theme-title" id="<?=$escape($themeId);?>"><?=$escape($theme['theme'] ?? 'Tema M03');?></h3>
                            <?php $themeEntries = is_array($theme['entries'] ?? null) ? $theme['entries'] : []; $themeConflicts = is_array($theme['conflicts'] ?? null) ? $theme['conflicts'] : []; $themeStatusItems = is_array($theme['status_items'] ?? null) ? $theme['status_items'] : []; ?>
                            <?php if ($themeEntries === [] && $themeConflicts === [] && $themeStatusItems === []): ?>
                                <p class="previous-responses-empty">Aún no hay respuestas registradas en este tema.</p>
                            <?php else: ?>
                                <?php if ($themeEntries === []): ?><p class="previous-responses-empty">Aún no hay respuestas registradas en este tema.</p><?php endif; ?>
                                <div class="previous-responses-list">
                                    <?php foreach ($themeEntries as $entry): ?>
                                        <?php $cardId = 'm03-previous-' . (int)$entry['question_id']; ?>
                                        <?php
                                            $entryReviewStatus = is_string($entry['review_status'] ?? null) ? $entry['review_status'] : 'REQUIERE_REVISION';
                                            $entryReviewPending = (bool)($entry['review_pending'] ?? ($entryReviewStatus === 'REQUIERE_REVISION'));
                                        ?>
                                        <article class="previous-response-card" data-review-card data-review-module-id="M03" data-review-module-name="Antecedentes personales no patológicos" data-review-theme="<?=$escape($theme['theme'] ?? 'Tema M03');?>" data-review-question="<?=$escape($entry['question']);?>" data-review-question-number="<?=$escape($entry['number']);?>" data-review-question-id="<?=$escape($entry['stable_id']);?>" data-review-status="<?=$escape($entryReviewStatus);?>" data-review-pending="<?=$entryReviewPending ? '1' : '0';?>" data-m03-review-card data-stable-id="<?=$escape($entry['stable_id']);?>" data-response-id="<?=$escape($entry['response_id']);?>" data-version="<?=$escape($entry['version']);?>" data-editable="<?=$entry['editable'] ? '1' : '0';?>" data-question-type="<?=$escape($entry['type']);?>" data-question-id="<?=$escape($entry['question_id']);?>">
                                            <div class="previous-response-heading"><span>Pregunta <?=$escape($entry['number']);?></span><span class="previous-response-status" data-status="<?=$escape($entryReviewStatus);?>"><?=$escape($statusLabel($entryReviewStatus));?></span></div>
                                            <div class="previous-response-body">
                                                <h4 class="previous-response-question"><?=$escape($entry['question']);?></h4>
                                                <div class="previous-response-display" data-m03-response-display>
                                                    <span class="previous-response-label">Respuesta registrada</span>
                                                    <p class="previous-response-answer" data-m03-current-answer><?=$escape($entry['answer']);?></p>
                                                </div>
                                                <?php if ($entry['editable']): ?>
                                                    <form class="previous-response-editor" data-m03-response-editor data-edit-value="<?=$escape($entry['answer_input']);?>" data-edit-state="<?=$escape($entry['semantic_state']);?>" hidden>
                                                        <?php if ($entry['type'] === 'select' && ($entry['editor_control'] ?? 'select') === 'select'): ?>
                                                            <label class="previous-response-label" for="<?=$escape($cardId);?>-state">Respuesta</label>
                                                            <select class="form-select" id="<?=$escape($cardId);?>-state" data-m03-semantic-state>
                                                                <option value="KNOWN" <?=$entry['semantic_state'] === 'KNOWN' ? 'selected' : '';?>>Sí</option>
                                                                <option value="NEGATIVE" <?=$entry['semantic_state'] === 'NEGATIVE' ? 'selected' : '';?>>No</option>
                                                                <option value="UNKNOWN" <?=$entry['semantic_state'] === 'UNKNOWN' ? 'selected' : '';?>>No lo sé</option>
                                                                <option value="NOT_REMEMBERED" <?=$entry['semantic_state'] === 'NOT_REMEMBERED' ? 'selected' : '';?>>No recuerdo</option>
                                                            </select>
                                                        <?php else: ?>
                                                            <label class="previous-response-label" for="<?=$escape($cardId);?>-input">Respuesta</label>
                                                            <textarea class="form-control" id="<?=$escape($cardId);?>-input" data-m03-answer-input maxlength="2000" rows="3" placeholder="Escribe tu respuesta registrada."><?=$escape($entry['answer_input']);?></textarea>
                                                            <label class="previous-response-label" for="<?=$escape($cardId);?>-state">Estado de la respuesta</label>
                                                            <select class="form-select" id="<?=$escape($cardId);?>-state" data-m03-semantic-state>
                                                                <option value="KNOWN" <?=$entry['semantic_state'] === 'KNOWN' ? 'selected' : '';?>>Respuesta conocida</option>
                                                                <?php if ((int)$entry['question_id'] === 5): ?><option value="NEGATIVE" <?=$entry['semantic_state'] === 'NEGATIVE' ? 'selected' : '';?>>No he dejado de fumar</option><?php endif; ?>
                                                                <?php if ((int)$entry['question_id'] === 1): ?><option value="NEGATIVE" <?=$entry['semantic_state'] === 'NEGATIVE' ? 'selected' : '';?>>No</option><?php endif; ?>
                                                                <option value="UNKNOWN" <?=$entry['semantic_state'] === 'UNKNOWN' ? 'selected' : '';?>>No lo sé</option>
                                                                <option value="NOT_REMEMBERED" <?=$entry['semantic_state'] === 'NOT_REMEMBERED' ? 'selected' : '';?>>No recuerdo</option>
                                                            </select>
                                                            <small class="text-muted">No lo sé y No recuerdo se conservan como estados distintos; no equivalen a No.</small>
                                                        <?php endif; ?>
                                                        <div class="previous-response-actions">
                                                            <button type="submit" class="btn previous-response-save" data-m03-save-response>Guardar cambios</button>
                                                            <button type="button" class="btn btn-outline-secondary" data-m03-cancel-edit>Cancelar</button>
                                                        </div>
                                                        <p class="previous-response-message" data-m03-edit-message aria-live="polite"></p>
                                                    </form>
                                                <?php endif; ?>
                                                <div class="previous-response-actions" data-m03-display-actions>
                                                    <button type="button" class="btn previous-response-edit" data-m03-start-edit <?=$entry['editable'] ? '' : 'disabled aria-disabled="true"';?>>Editar</button>
                                                </div>
                                                <p class="previous-response-message" data-m03-edit-note><?=$escape($entry['edit_blocker']);?></p>
                                                <p class="previous-response-message" data-m03-saved-message aria-live="polite"></p>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                    <?php foreach ($themeConflicts as $conflict): ?>
                                        <article class="previous-response-card" data-review-card data-review-module-id="M03" data-review-module-name="Antecedentes personales no patológicos" data-review-theme="<?=$escape($theme['theme'] ?? 'Tema M03');?>" data-review-question="<?=$escape($conflict['question']);?>" data-review-question-number="<?=$escape($conflict['number']);?>" data-review-question-id="<?=$escape($conflict['stable_id'] ?? ('M03:question:' . (int)$conflict['question_id']));?>" data-review-status="REQUIERE_REVISION" data-review-pending="1" data-m03-duplicate-conflict>
                                            <div class="previous-response-heading"><span>Pregunta <?=$escape($conflict['number']);?></span><span class="previous-response-status" data-status="REQUIERE_REVISION">Requiere revisión</span></div>
                                            <div class="previous-response-body">
                                                <h4 class="previous-response-question"><?=$escape($conflict['question']);?></h4>
                                                <p class="previous-responses-empty"><?=$escape($conflict['reason'] ?? 'La respuesta requiere revisión y se mantiene en solo lectura.');?></p>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                    <?php foreach ($themeStatusItems as $item): ?>
                                        <article class="previous-response-card" data-review-card data-review-module-id="M03" data-review-module-name="Antecedentes personales no patológicos" data-review-theme="<?=$escape($theme['theme'] ?? 'Tema M03');?>" data-review-question="<?=$escape($item['question']);?>" data-review-question-number="<?=$escape($item['number']);?>" data-review-question-id="<?=$escape($item['stable_id']);?>" data-review-status="<?=$escape($item['status']);?>" data-review-pending="<?=$item['pending'] ? '1' : '0';?>">
                                            <div class="previous-response-heading"><span>Pregunta <?=$escape($item['number']);?></span><span class="previous-response-status" data-status="<?=$escape($item['status']);?>"><?=$escape($statusLabel($item['status']));?></span></div>
                                            <div class="previous-response-body">
                                                <h4 class="previous-response-question"><?=$escape($item['question']);?></h4>
                                                <?php if ($item['status'] === 'PENDIENTE'): ?>
                                                    <p class="previous-response-type">Pendiente de responder.</p>
                                                    <a class="btn btn-outline-primary previous-responses-pending-action" href="<?=htmlspecialchars(SERVIDOR . 'historia-clinica/entrevista', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>">Ir a la entrevista</a>
                                                <?php else: ?>
                                                    <p class="previous-responses-empty"><?=$escape($item['reason']);?></p>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
            <hr class="previous-responses-module-divider">
            <section aria-labelledby="m04-previous-title" data-review-module="M04">
                <div class="m04-section-heading">
                    <h2 id="m04-previous-title" class="previous-responses-section-title">M04 · Antecedentes quirúrgicos</h2>
                    <button type="button" class="btn previous-response-save m04-create-trigger" data-m04-open-create aria-haspopup="dialog" aria-controls="m04-create-surgery-dialog" <?=!empty($data['m04CreateAuthorized']) ? '' : 'disabled aria-disabled="true"';?>>+ Agregar cirugía</button>
                </div>
                <?php if (!empty($data['m04Finalized'])): ?><p class="previous-responses-notice">M04 está finalizado. La revisión de una cirugía existente no reabre el módulo ni modifica su progreso.</p><?php endif; ?>
                <p class="previous-responses-empty" data-m04-empty-state<?=$m04Entries === [] ? '' : ' hidden';?>>No hay cirugías registradas en los antecedentes disponibles.</p>
                <div class="previous-responses-list" data-m04-record-list<?=$m04Entries === [] ? ' hidden' : '';?>>
                        <?php foreach ($m04Entries as $entry): ?>
                            <?php $cardId = 'm04-previous-' . (int)$entry['record_id']; ?>
                            <article class="previous-response-card" data-review-card data-review-module-id="M04" data-review-module-name="Antecedentes quirúrgicos" data-review-theme="Antecedentes quirúrgicos" data-review-question="<?=$escape($entry['surgery']);?>" data-review-question-number="<?=$escape($entry['record_id']);?>" data-review-question-id="<?=$escape($entry['stable_id']);?>" data-review-status="REGISTRADA" data-review-pending="0" data-m04-review-card data-stable-id="<?=$escape($entry['stable_id']);?>" data-record-id="<?=$escape($entry['record_id']);?>" data-version="<?=$escape($entry['version']);?>" data-original-surgery="<?=$escape($entry['surgery']);?>" data-original-date="<?=$escape($entry['date']);?>" data-original-observations="<?=$escape($entry['observations']);?>" data-editable="<?=$entry['editable'] ? '1' : '0';?>">
                                <div class="previous-response-heading"><span>Cirugía registrada</span><span class="previous-response-status" data-status="REGISTRADA">Registrada</span></div>
                                <div class="previous-response-body">
                                    <div class="previous-response-display" data-m04-response-display>
                                        <span class="previous-response-label">Nombre o descripción</span>
                                        <p class="previous-response-answer" data-m04-current-surgery><?=$escape($entry['surgery']);?></p>
                                        <div data-m04-date-wrap<?=$entry['date'] === '' ? ' hidden' : '';?>>
                                            <span class="previous-response-label">Fecha registrada</span>
                                            <p class="previous-response-answer" data-m04-current-date><?=$escape($entry['date']);?></p>
                                        </div>
                                        <div data-m04-observations-wrap<?=$entry['observations'] === '' ? ' hidden' : '';?>>
                                            <span class="previous-response-label">Observaciones</span>
                                            <p class="previous-response-answer" data-m04-current-observations><?=$escape($entry['observations']);?></p>
                                        </div>
                                    </div>
                                    <?php if ($entry['editable']): ?>
                                        <form class="previous-response-editor" data-m04-response-editor hidden>
                                            <label class="previous-response-label" for="<?=$escape($cardId);?>-surgery">Nombre o descripción</label>
                                            <input class="form-control" id="<?=$escape($cardId);?>-surgery" type="text" maxlength="2000" required data-m04-surgery-input value="<?=$escape($entry['surgery']);?>">
                                            <label class="previous-response-label" for="<?=$escape($cardId);?>-observations">Observaciones</label>
                                            <textarea class="form-control" id="<?=$escape($cardId);?>-observations" maxlength="2000" rows="3" data-m04-observations-input><?=$escape($entry['observations']);?></textarea>
                                            <div class="previous-response-actions">
                                                <button type="submit" class="btn previous-response-save" data-m04-save-surgery>Guardar cambios</button>
                                                <button type="button" class="btn btn-outline-secondary" data-m04-cancel-edit>Cancelar</button>
                                            </div>
                                            <p class="previous-response-message" data-m04-edit-message aria-live="polite"></p>
                                        </form>
                                    <?php endif; ?>
                                    <div class="previous-response-actions" data-m04-display-actions>
                                        <button type="button" class="btn previous-response-edit" data-m04-start-edit <?=$entry['editable'] ? '' : 'disabled aria-disabled="true"';?>>Editar</button>
                                    </div>
                                    <p class="previous-response-message" data-m04-edit-note><?=$escape($entry['edit_blocker']);?></p>
                                    <p class="previous-response-message" data-m04-saved-message aria-live="polite"></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                </div>
                <dialog class="m04-create-dialog" id="m04-create-surgery-dialog" data-m04-create-dialog aria-labelledby="m04-create-surgery-title">
                    <form class="previous-response-editor m04-create-dialog-form" data-m04-create-form novalidate>
                        <h3 class="m04-create-dialog-title" id="m04-create-surgery-title">Registrar nueva cirugía</h3>
                        <p class="m04-create-guidance">Si solo conoces el mes o el año, anótalo en Observaciones; no inventes una fecha exacta.</p>
                        <label class="previous-response-label" for="m04-create-surgery-name">Nombre de la cirugía</label>
                        <div class="m04-create-autocomplete-host">
                            <input class="form-control" id="m04-create-surgery-name" type="text" maxlength="2000" required autocomplete="off" aria-describedby="m04-create-surgery-name-error" data-m04-create-name>
                            <div class="m04-surgery-suggestions" id="m04-create-surgery-suggestions" role="listbox" aria-label="Sugerencias de nombres de cirugía" data-m04-create-suggestions hidden></div>
                        </div>
                        <p class="m04-create-field-error" id="m04-create-surgery-name-error" data-m04-create-name-error hidden></p>
                        <label class="previous-response-label" for="m04-create-surgery-observations">Observaciones <span class="text-muted fw-normal">(opcional)</span></label>
                        <textarea class="form-control" id="m04-create-surgery-observations" maxlength="2000" rows="3" aria-describedby="m04-create-surgery-observations-error" data-m04-create-observations></textarea>
                        <p class="m04-create-field-error" id="m04-create-surgery-observations-error" data-m04-create-observations-error hidden></p>
                        <div class="previous-response-actions m04-create-actions">
                            <button type="button" class="btn btn-outline-secondary" data-m04-create-cancel>Cancelar</button>
                            <button type="submit" class="btn previous-response-save" data-m04-create-save>Guardar cirugía</button>
                        </div>
                        <p class="previous-response-message" data-m04-create-message aria-live="polite"></p>
                    </form>
                </dialog>
                <template data-m04-create-card-template>
                    <article class="previous-response-card" data-review-card data-review-module-id="M04" data-review-module-name="Antecedentes quirúrgicos" data-review-theme="Antecedentes quirúrgicos" data-review-status="REGISTRADA" data-review-pending="0" data-m04-review-card data-editable="1">
                        <div class="previous-response-heading"><span>Cirugía registrada</span><span class="previous-response-status" data-status="REGISTRADA">Registrada</span></div>
                        <div class="previous-response-body">
                            <div class="previous-response-display" data-m04-response-display>
                                <span class="previous-response-label">Nombre o descripción</span>
                                <p class="previous-response-answer" data-m04-current-surgery></p>
                                <div data-m04-date-wrap hidden><span class="previous-response-label">Fecha registrada</span><p class="previous-response-answer" data-m04-current-date></p></div>
                                <div data-m04-observations-wrap hidden><span class="previous-response-label">Observaciones</span><p class="previous-response-answer" data-m04-current-observations></p></div>
                            </div>
                            <form class="previous-response-editor" data-m04-response-editor hidden>
                                <label class="previous-response-label" data-m04-label-surgery>Nombre o descripción</label>
                                <input class="form-control" type="text" maxlength="2000" required data-m04-surgery-input>
                                <label class="previous-response-label" data-m04-label-observations>Observaciones</label>
                                <textarea class="form-control" maxlength="2000" rows="3" data-m04-observations-input></textarea>
                                <div class="previous-response-actions">
                                    <button type="submit" class="btn previous-response-save" data-m04-save-surgery>Guardar cambios</button>
                                    <button type="button" class="btn btn-outline-secondary" data-m04-cancel-edit>Cancelar</button>
                                </div>
                                <p class="previous-response-message" data-m04-edit-message aria-live="polite"></p>
                            </form>
                            <div class="previous-response-actions" data-m04-display-actions><button type="button" class="btn previous-response-edit" data-m04-start-edit>Editar</button></div>
                            <p class="previous-response-message" data-m04-edit-note></p>
                            <p class="previous-response-message" data-m04-saved-message aria-live="polite"></p>
                        </div>
                    </article>
                </template>
            </section>
            <hr class="previous-responses-module-divider">
            <section aria-labelledby="m05-previous-title" data-review-module="M05">
                <h2 id="m05-previous-title" class="previous-responses-section-title">M05 · Antecedentes personales patológicos</h2>
                <?php if (!empty($data['m05Finalized'])): ?><p class="previous-responses-notice">M05 está finalizado. La revisión de respuestas no reabre el módulo ni modifica su progreso.</p><?php endif; ?>
                <h3 class="previous-responses-section-title">Preguntas por tema</h3>
                <?php if ($m05V1Themes === []): ?>
                    <p class="previous-responses-empty">No hay preguntas M05 disponibles en el catálogo.</p>
                <?php else: ?>
                    <?php foreach ($m05V1Themes as $theme): ?>
                        <section class="previous-responses-theme" data-review-theme="<?=$escape($theme['theme']);?>">
                            <h4 class="previous-responses-theme-title"><?=$escape($theme['theme']);?></h4>
                            <div class="previous-responses-list">
                                <?php foreach (($theme['entries'] ?? []) as $entry): ?>
                                    <?php $cardId = 'm05-v1-' . (int)$entry['question_id']; ?>
                                    <article class="previous-response-card" data-review-card data-review-module-id="M05" data-review-module-name="Antecedentes personales patológicos" data-review-theme="<?=$escape($theme['theme']);?>" data-review-question="<?=$escape($entry['question']);?>" data-review-question-number="<?=$escape($entry['question_id']);?>" data-review-question-id="<?=$escape($entry['stable_id']);?>" data-review-status="<?=$escape($entry['status']);?>" data-review-pending="<?=$entry['pending'] ? '1' : '0';?>" data-m05-v1-card data-stable-id="<?=$escape($entry['stable_id']);?>" data-question-id="<?=$escape($entry['question_id']);?>" data-response-id="<?=$escape($entry['response_id']);?>" data-version="<?=$escape($entry['version']);?>" data-original-answer="<?=$escape($entry['answer']);?>" data-editable="<?=$entry['editable'] ? '1' : '0';?>">
                                        <div class="previous-response-heading"><span><?=$escape($entry['question']);?></span><span class="previous-response-status" data-status="<?=$escape($entry['status']);?>"><?=$escape($statusLabel($entry['status']));?></span></div>
                                        <div class="previous-response-body">
                                            <div class="previous-response-display" data-m05-v1-display>
                                                <span class="previous-response-label">Respuesta</span>
                                                <p class="previous-response-answer" data-m05-v1-current-answer><?=$entry['status'] === 'REQUIERE_REVISION' ? 'Hay varias respuestas registradas; requieren revisión.' : ($entry['answer'] !== '' ? $escape($entry['answer']) : ($entry['response_id'] > 0 ? 'Respuesta registrada sin texto.' : 'Sin respuesta registrada.'));?></p>
                                            </div>
                                            <?php if ($entry['editable']): ?>
                                                <form class="previous-response-editor" data-response-editor data-m05-v1-editor hidden>
                                                    <label class="previous-response-label" for="<?=$escape($cardId);?>-answer">Respuesta</label>
                                                    <?php if ($entry['editor_control'] === 'select'): ?>
                                                        <select class="form-select" id="<?=$escape($cardId);?>-answer" data-m05-v1-answer required>
                                                            <option value="">Selecciona una respuesta</option>
                                                            <option value="Si" <?=$entry['answer'] === 'Si' || $entry['answer'] === 'Sí' ? 'selected' : '';?>>Sí</option>
                                                            <option value="No" <?=$entry['answer'] === 'No' ? 'selected' : '';?>>No</option>
                                                        </select>
                                                    <?php else: ?>
                                                        <textarea class="form-control" id="<?=$escape($cardId);?>-answer" rows="3" maxlength="2000" data-m05-v1-answer required><?=$escape($entry['answer']);?></textarea>
                                                    <?php endif; ?>
                                                    <div class="previous-response-actions">
                                                        <button type="submit" class="btn previous-response-save" data-m05-v1-save>Guardar cambios</button>
                                                        <button type="button" class="btn btn-outline-secondary" data-m05-v1-cancel>Cancelar</button>
                                                    </div>
                                                    <p class="previous-response-message" data-m05-v1-message aria-live="polite"></p>
                                                </form>
                                            <?php endif; ?>
                                            <div class="previous-response-actions" data-m05-v1-display-actions>
                                                <button type="button" class="btn previous-response-edit" data-m05-v1-start-edit <?=$entry['editable'] ? '' : 'disabled aria-disabled="true"';?>>Editar</button>
                                            </div>
                                            <p class="previous-response-message" data-m05-v1-edit-note><?=$escape($entry['edit_blocker']);?></p>
                                            <p class="previous-response-message" data-m05-v1-saved-message aria-live="polite"></p>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>

                <h3 class="previous-responses-section-title">Enfermedades registradas</h3>
                <?php if ($m05V2Diseases === []): ?>
                    <p class="previous-responses-empty">No hay enfermedades M05 disponibles en el catálogo.</p>
                <?php else: ?>
                    <div class="previous-responses-list">
                        <?php foreach ($m05V2Diseases as $entry): ?>
                            <?php $cardId = 'm05-v2-' . (int)$entry['disease_id']; $yearForInput = preg_match('/^(19|20)\d{2}$/D', (string)$entry['year']) ? $entry['year'] : ''; ?>
                            <article class="previous-response-card" data-review-card data-review-module-id="M05" data-review-module-name="Antecedentes personales patológicos" data-review-theme="Enfermedades" data-review-question="<?=$escape($entry['enfermedad']);?>" data-review-question-number="<?=$escape($entry['disease_id']);?>" data-review-question-id="<?=$escape($entry['stable_id']);?>" data-review-status="<?=$escape($entry['status']);?>" data-review-pending="<?=$entry['pending'] ? '1' : '0';?>" data-m05-v2-card data-stable-id="<?=$escape($entry['stable_id']);?>" data-disease-id="<?=$escape($entry['disease_id']);?>" data-response-id="<?=$escape($entry['response_id']);?>" data-version="<?=$escape($entry['version']);?>" data-original-response="<?=$escape($entry['answer']);?>" data-original-year="<?=$escape($entry['year']);?>" data-editable="<?=$entry['editable'] ? '1' : '0';?>">
                                <div class="previous-response-heading"><span><?=$escape($entry['enfermedad']);?></span><span class="previous-response-status" data-status="<?=$escape($entry['status']);?>"><?=$escape($statusLabel($entry['status']));?></span></div>
                                <div class="previous-response-body">
                                    <div class="previous-response-display" data-m05-v2-display>
                                        <span class="previous-response-label">Respuesta</span>
                                                <p class="previous-response-answer" data-m05-v2-current-response><?=$entry['status'] === 'REQUIERE_REVISION' ? 'Hay varias respuestas registradas; requieren revisión.' : ($entry['answer'] !== '' ? $escape($entry['answer']) : ($entry['response_id'] > 0 ? 'Respuesta registrada sin texto.' : 'Sin respuesta registrada.'));?></p>
                                        <div data-m05-v2-year-wrap<?=$entry['year_display'] === '' ? ' hidden' : '';?>><span class="previous-response-label">Año de diagnóstico</span><p class="previous-response-answer" data-m05-v2-current-year><?=$escape($entry['year_display']);?></p></div>
                                    </div>
                                    <?php if ($entry['editable']): ?>
                                        <form class="previous-response-editor" data-response-editor data-m05-v2-editor hidden>
                                            <label class="previous-response-label" for="<?=$escape($cardId);?>-response">Respuesta registrada</label>
                                            <textarea class="form-control" id="<?=$escape($cardId);?>-response" rows="3" maxlength="2000" data-m05-v2-response required><?=$escape($entry['answer']);?></textarea>
                                            <label class="previous-response-label" for="<?=$escape($cardId);?>-year">Año de diagnóstico <span class="text-muted fw-normal">(opcional)</span></label>
                                            <input class="form-control" id="<?=$escape($cardId);?>-year" type="text" inputmode="numeric" pattern="(19|20)[0-9]{2}" maxlength="4" data-m05-v2-year value="<?=$escape($yearForInput);?>" aria-describedby="<?=$escape($cardId);?>-year-help">
                                            <small class="text-muted" id="<?=$escape($cardId);?>-year-help">Deja vacío para conservar el año guardado. Para eliminarlo, marca la casilla.</small>
                                            <label class="previous-response-relative-option"><input class="form-check-input" type="checkbox" data-m05-clear-year> <span>Eliminar el año guardado</span></label>
                                            <div class="previous-response-actions">
                                                <button type="submit" class="btn previous-response-save" data-m05-v2-save>Guardar cambios</button>
                                                <button type="button" class="btn btn-outline-secondary" data-m05-v2-cancel>Cancelar</button>
                                            </div>
                                            <p class="previous-response-message" data-m05-v2-message aria-live="polite"></p>
                                        </form>
                                    <?php endif; ?>
                                    <div class="previous-response-actions" data-m05-v2-display-actions>
                                        <button type="button" class="btn previous-response-edit" data-m05-v2-start-edit <?=$entry['editable'] ? '' : 'disabled aria-disabled="true"';?>>Editar</button>
                                    </div>
                                    <p class="previous-response-message" data-m05-v2-edit-note><?=$escape($entry['edit_blocker']);?></p>
                                    <p class="previous-response-message" data-m05-v2-saved-message aria-live="polite"></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <div class="previous-responses-footer">
                <a class="btn btn-primary previous-responses-close" href="<?=htmlspecialchars(SERVIDOR . 'historia-clinica/entrevista', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>">Cerrar revisión y volver a la entrevista</a>
            </div>
        </main>
    </div>
</div>
<script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="<?=RUTA_JS;?>app.js"></script>
<script src="<?=RUTA_JS;?>/patient-previous-responses-filters.js"></script>
<script src="<?=RUTA_JS;?>/patient-previous-responses-surgery-autocomplete.js"></script>
<script>
(() => {
    const root = document.getElementById('main');
    if (!root) return;
    const endpoint = root.dataset.editUrl || '';
    const csrf = root.dataset.csrf || '';
    const showMessage = (node, message, state = '') => {
        if (!node) return;
        node.textContent = message;
        node.dataset.state = state;
    };

    document.querySelectorAll('[data-m02-review-card][data-editable="1"]').forEach(card => {
        const display = card.querySelector('[data-response-display]');
        const editor = card.querySelector('[data-response-editor]');
        const displayActions = card.querySelector('[data-display-actions]');
        const edit = card.querySelector('[data-start-edit]');
        const input = card.querySelector('[data-answer-input]');
        const save = card.querySelector('[data-save-response]');
        const cancel = card.querySelector('[data-cancel-edit]');
        const message = card.querySelector('[data-edit-message]');
        const savedMessage = card.querySelector('[data-saved-message]');
        const relativeInputs = [...editor.querySelectorAll('[data-relative-input]')];
        const setRelativeSelection = values => {
            const selected = Array.isArray(values) ? values : [];
            relativeInputs.forEach(option => { option.checked = selected.includes(option.value); });
        };
        if (!display || !editor || !edit || !input || !save || !cancel) return;

        edit.addEventListener('click', () => {
            if (card.dataset.saving === '1') return;
            input.value = editor.dataset.editValue || '';
            setRelativeSelection(JSON.parse(editor.dataset.relativeValues || '[]'));
            showMessage(message, '');
            showMessage(savedMessage, '');
            display.hidden = true;
            displayActions.hidden = true;
            editor.hidden = false;
            input.focus();
        });
        cancel.addEventListener('click', () => {
            if (card.dataset.saving === '1') return;
            input.value = editor.dataset.editValue || '';
            setRelativeSelection(JSON.parse(editor.dataset.relativeValues || '[]'));
            const typeControl = editor.querySelector('[data-diabetes-type]');
            if (typeControl) typeControl.value = '__KEEP__';
            editor.hidden = true;
            display.hidden = false;
            displayActions.hidden = false;
            showMessage(message, '');
            showMessage(savedMessage, '');
        });
        editor.addEventListener('submit', async event => {
            event.preventDefault();
            if (card.dataset.saving === '1') return;
            const answer = input.value.trim();
            const relatives = relativeInputs.filter(option => option.checked).map(option => option.value);
            if (Array.from(answer).length > 2000 || (!answer && relatives.length === 0)) {
                showMessage(message, relativeInputs.length ? 'Conserva al menos un parentesco o escribe una descripción.' : 'Escribe una respuesta de hasta 2000 caracteres.', 'error');
                input.focus();
                return;
            }
            if (!csrf || !endpoint) {
                showMessage(message, 'La sesión de seguridad no está disponible. Recarga la página.', 'error');
                return;
            }
            const typeControl = editor.querySelector('[data-diabetes-type]');
            card.dataset.saving = '1';
            save.disabled = true;
            cancel.disabled = true;
            edit.disabled = true;
            showMessage(message, 'Guardando y verificando la respuesta…');
            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'X-M03-CSRF': csrf, 'Accept': 'application/json'},
                    body: JSON.stringify({
                        stable_id: card.dataset.stableId || '',
                        expected_version: card.dataset.version || '',
                        answer,
                        ...(relativeInputs.length ? {relatives} : {}),
                        ...(typeControl ? {diabetes_type: typeControl.value} : {}),
                    }),
                });
                let result;
                try { result = await response.json(); } catch (_) { throw new Error('No se pudo leer la confirmación del servidor.'); }
                if (!response.ok || result.resultado !== true || result.readback !== true || typeof result.answer !== 'string' || typeof result.version !== 'string') {
                    if (response.status === 409) throw new Error(result.mensaje || 'La respuesta cambió. Recarga la página para revisar el valor actual.');
                    throw new Error(result.mensaje || 'No se guardó la respuesta. Puedes conservar y revisar el texto antes de intentar de nuevo.');
                }
                card.querySelector('[data-current-answer]').textContent = result.answer;
                const typeDisplay = card.querySelector('[data-current-diabetes-type]');
                if (typeDisplay) {
                    const typeValue = typeDisplay.querySelector('[data-type-value]');
                    const currentType = result.diabetes_type || '';
                    if (typeValue) typeValue.textContent = currentType;
                    typeDisplay.hidden = currentType === '';
                    if (typeControl) typeControl.value = '__KEEP__';
                }
                card.dataset.version = result.version;
                editor.dataset.editValue = answer;
                if (Array.isArray(result.relatives)) editor.dataset.relativeValues = JSON.stringify(result.relatives);
                setRelativeSelection(result.relatives);
                editor.hidden = true;
                display.hidden = false;
                displayActions.hidden = false;
                showMessage(message, '');
                showMessage(savedMessage, 'Respuesta actualizada.', 'success');
            } catch (error) {
                showMessage(message, error instanceof Error ? error.message : 'No se guardó la respuesta. Inténtalo de nuevo.', 'error');
            } finally {
                card.dataset.saving = '0';
                save.disabled = false;
                cancel.disabled = false;
                edit.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-m03-review-card][data-editable="1"]').forEach(card => {
        const display = card.querySelector('[data-m03-response-display]');
        const editor = card.querySelector('[data-m03-response-editor]');
        const displayActions = card.querySelector('[data-m03-display-actions]');
        const edit = card.querySelector('[data-m03-start-edit]');
        const input = card.querySelector('[data-m03-answer-input]');
        const stateControl = card.querySelector('[data-m03-semantic-state]');
        const save = card.querySelector('[data-m03-save-response]');
        const cancel = card.querySelector('[data-m03-cancel-edit]');
        const message = card.querySelector('[data-m03-edit-message]');
        const savedMessage = card.querySelector('[data-m03-saved-message]');
        const showM03Message = (node, value, state = '') => {
            if (!node) return;
            node.textContent = value;
            node.dataset.state = state;
        };
        if (!display || !editor || !displayActions || !edit || !stateControl || !save || !cancel) return;

        edit.addEventListener('click', () => {
            if (card.dataset.saving === '1') return;
            if (input) input.value = editor.dataset.editValue || '';
            stateControl.value = editor.dataset.editState || stateControl.value;
            showM03Message(message, '');
            showM03Message(savedMessage, '');
            display.hidden = true;
            displayActions.hidden = true;
            editor.hidden = false;
            (input || stateControl).focus();
        });
        cancel.addEventListener('click', () => {
            if (card.dataset.saving === '1') return;
            if (input) input.value = editor.dataset.editValue || '';
            stateControl.value = editor.dataset.editState || stateControl.value;
            editor.hidden = true;
            display.hidden = false;
            displayActions.hidden = false;
            showM03Message(message, '');
            showM03Message(savedMessage, '');
        });
        editor.addEventListener('submit', async event => {
            event.preventDefault();
            if (card.dataset.saving === '1') return;
            const answer = input ? input.value.trim() : '';
            const semanticState = String(stateControl.value || '').toUpperCase();
            if (input && semanticState === 'KNOWN' && answer === '') {
                showM03Message(message, 'Completa la respuesta o selecciona un estado de incertidumbre.', 'error');
                input.focus();
                return;
            }
            if (input && ['UNKNOWN', 'NOT_REMEMBERED'].includes(semanticState) && answer !== '') {
                showM03Message(message, 'Para guardar este estado, deja vacío el campo de valor clínico.', 'error');
                input.focus();
                return;
            }
            const validNegative = (card.dataset.questionId === '5' && answer === 'No he dejado de fumar')
                || (card.dataset.questionId === '1' && answer === '');
            if (semanticState === 'NEGATIVE' && !validNegative) {
                showM03Message(message, 'La respuesta negativa no corresponde a esta pregunta.', 'error');
                if (input) input.focus();
                return;
            }
            if (!csrf || !root.dataset.m03EditUrl) {
                showM03Message(message, 'La sesión de seguridad no está disponible. Recarga la página.', 'error');
                return;
            }
            card.dataset.saving = '1';
            save.disabled = true;
            cancel.disabled = true;
            edit.disabled = true;
            showM03Message(message, 'Guardando y verificando la respuesta…');
            try {
                const response = await fetch(root.dataset.m03EditUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'X-M03-CSRF': csrf, 'Accept': 'application/json'},
                    body: JSON.stringify({
                        stable_id: card.dataset.stableId || '',
                        response_id: Number(card.dataset.responseId || 0),
                        expected_version: card.dataset.version || '',
                        answer,
                        semantic_state: semanticState,
                    }),
                });
                let result;
                try { result = await response.json(); } catch (_) { throw new Error('No se pudo leer la confirmación del servidor.'); }
                if (!response.ok || result.resultado !== true || result.readback !== true
                    || typeof result.answer !== 'string' || typeof result.version !== 'string'
                    || Number(result.response_id) !== Number(card.dataset.responseId)) {
                    if (response.status === 409) throw new Error(result.mensaje || 'La respuesta cambió. Recarga para revisar el valor actual.');
                    throw new Error(result.mensaje || 'No se confirmó el guardado. Puedes conservar el borrador e intentarlo de nuevo.');
                }
                card.querySelector('[data-m03-current-answer]').textContent = result.answer;
                card.dataset.version = result.version;
                editor.dataset.editValue = input ? answer : '';
                editor.dataset.editState = result.semantic_state;
                stateControl.value = result.semantic_state;
                if (input) input.value = editor.dataset.editValue;
                editor.hidden = true;
                display.hidden = false;
                displayActions.hidden = false;
                showM03Message(message, '');
                showM03Message(savedMessage, 'Respuesta actualizada.', 'success');
            } catch (error) {
                showM03Message(message, error instanceof Error ? error.message : 'No se guardó la respuesta. Inténtalo de nuevo.', 'error');
            } finally {
                card.dataset.saving = '0';
                save.disabled = false;
                cancel.disabled = false;
                edit.disabled = false;
            }
        });
    });

    const validExactDate = value => {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''));
        if (!match) return false;
        const year = Number(match[1]);
        const month = Number(match[2]);
        const day = Number(match[3]);
        const parsed = new Date(Date.UTC(year, month - 1, day));
        return parsed.getUTCFullYear() === year && parsed.getUTCMonth() === month - 1 && parsed.getUTCDate() === day;
    };
    const setM04Message = (node, value, state = '') => {
        if (!node) return;
        node.textContent = value;
        node.dataset.state = state;
    };
    const bindM04ReviewCard = card => {
        const display = card.querySelector('[data-m04-response-display]');
        const editor = card.querySelector('[data-m04-response-editor]');
        const displayActions = card.querySelector('[data-m04-display-actions]');
        const edit = card.querySelector('[data-m04-start-edit]');
        const surgeryInput = card.querySelector('[data-m04-surgery-input]');
        const observationsInput = card.querySelector('[data-m04-observations-input]');
        const save = card.querySelector('[data-m04-save-surgery]');
        const cancel = card.querySelector('[data-m04-cancel-edit]');
        const message = card.querySelector('[data-m04-edit-message]');
        const savedMessage = card.querySelector('[data-m04-saved-message]');
        if (!display || !editor || !displayActions || !edit || !surgeryInput || !observationsInput || !save || !cancel) return;

        const restoreValues = () => {
            surgeryInput.value = card.dataset.originalSurgery || '';
            observationsInput.value = card.dataset.originalObservations || '';
        };
        edit.addEventListener('click', () => {
            if (card.dataset.saving === '1') return;
            restoreValues();
            setM04Message(message, '');
            setM04Message(savedMessage, '');
            display.hidden = true;
            displayActions.hidden = true;
            editor.hidden = false;
            surgeryInput.focus();
        });
        cancel.addEventListener('click', () => {
            if (card.dataset.saving === '1') return;
            restoreValues();
            editor.hidden = true;
            display.hidden = false;
            displayActions.hidden = false;
            setM04Message(message, '');
            setM04Message(savedMessage, '');
        });
        editor.addEventListener('submit', async event => {
            event.preventDefault();
            if (card.dataset.saving === '1') return;
            const surgery = surgeryInput.value.trim();
            const observations = observationsInput.value;
            const expectedDate = card.dataset.originalDate || '';
            if (!surgery || Array.from(surgery).length > 2000 || Array.from(observations).length > 2000) {
                setM04Message(message, 'Completa el nombre de la cirugía y revisa los límites de texto.', 'error');
                surgeryInput.focus();
                return;
            }
            if (!csrf || !root.dataset.m04EditUrl) {
                setM04Message(message, 'La sesión de seguridad no está disponible. Recarga la página.', 'error');
                return;
            }
            card.dataset.saving = '1';
            save.disabled = true;
            cancel.disabled = true;
            edit.disabled = true;
            setM04Message(message, 'Guardando y verificando la cirugía…');
            try {
                const response = await fetch(root.dataset.m04EditUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'X-M03-CSRF': csrf, 'Accept': 'application/json'},
                    body: JSON.stringify({
                        stable_id: card.dataset.stableId || '',
                        record_id: Number(card.dataset.recordId || 0),
                        expected_version: card.dataset.version || '',
                        cirugia: surgery,
                        observaciones: observations,
                    }),
                });
                let result;
                try { result = await response.json(); } catch (_) { throw new Error('No se pudo leer la confirmación del servidor.'); }
                if (!response.ok || result.resultado !== true || result.readback !== true
                    || Number(result.record_id) !== Number(card.dataset.recordId)
                    || typeof result.cirugia !== 'string' || typeof result.observaciones !== 'string'
                    || (result.fecha !== null && typeof result.fecha !== 'string')
                    || (result.fecha || '') !== expectedDate
                    || typeof result.version !== 'string' || !/^[a-f0-9]{64}$/.test(result.version)) {
                    if (response.status === 409) throw new Error(result.mensaje || 'La cirugía cambió. Recarga para revisar la información actual.');
                    throw new Error(result.mensaje || 'No se confirmó la edición. Puedes conservar el borrador e intentarlo de nuevo.');
                }
                card.dataset.originalSurgery = result.cirugia;
                card.dataset.originalDate = result.fecha || '';
                card.dataset.originalObservations = result.observaciones;
                card.querySelector('[data-m04-current-surgery]').textContent = result.cirugia;
                const dateWrap = card.querySelector('[data-m04-date-wrap]');
                const dateDisplay = card.querySelector('[data-m04-current-date]');
                if (dateDisplay) dateDisplay.textContent = result.fecha || '';
                if (dateWrap) dateWrap.hidden = !result.fecha;
                const observationsWrap = card.querySelector('[data-m04-observations-wrap]');
                const observationsDisplay = card.querySelector('[data-m04-current-observations]');
                if (observationsDisplay) observationsDisplay.textContent = result.observaciones;
                if (observationsWrap) observationsWrap.hidden = result.observaciones === '';
                root.querySelectorAll('[data-m04-review-card]').forEach(item => { item.dataset.version = result.version; });
                card.dataset.version = result.version;
                root.dataset.m04Version = result.version;
                restoreValues();
                editor.hidden = true;
                display.hidden = false;
                displayActions.hidden = false;
                setM04Message(message, '');
                setM04Message(savedMessage, 'Cirugía actualizada.', 'success');
            } catch (error) {
                setM04Message(message, error instanceof Error ? error.message : 'No se confirmó la edición. Inténtalo de nuevo.', 'error');
            } finally {
                card.dataset.saving = '0';
                save.disabled = false;
                cancel.disabled = false;
                edit.disabled = false;
            }
        });
    };
    document.querySelectorAll('[data-m04-review-card][data-editable="1"]').forEach(bindM04ReviewCard);

    const createDialog = root.querySelector('[data-m04-create-dialog]');
    const createForm = root.querySelector('[data-m04-create-form]');
    const createTrigger = root.querySelector('[data-m04-open-create]');
    const createCancel = root.querySelector('[data-m04-create-cancel]');
    const createSave = root.querySelector('[data-m04-create-save]');
    const createName = root.querySelector('[data-m04-create-name]');
    const createObservations = root.querySelector('[data-m04-create-observations]');
    const createMessage = root.querySelector('[data-m04-create-message]');
    const cardTemplate = root.querySelector('template[data-m04-create-card-template]');
    const recordList = root.querySelector('[data-m04-record-list]');
    const emptyState = root.querySelector('[data-m04-empty-state]');
    let createInFlight = false;

    const setCreateFieldError = (input, node, message) => {
        if (!input || !node) return;
        node.textContent = message;
        node.hidden = message === '';
        if (message) input.setAttribute('aria-invalid', 'true');
        else input.removeAttribute('aria-invalid');
    };
    const clearCreateErrors = () => {
        setCreateFieldError(createName, root.querySelector('[data-m04-create-name-error]'), '');
        setCreateFieldError(createObservations, root.querySelector('[data-m04-create-observations-error]'), '');
        setM04Message(createMessage, '');
    };

    if (createDialog && createForm && createTrigger && createCancel && createSave && createName
        && createObservations && cardTemplate && recordList && emptyState) {
        createTrigger.addEventListener('click', () => {
            if (typeof createDialog.showModal !== 'function') {
                setM04Message(createMessage, 'Este navegador no permite abrir el formulario seguro.', 'error');
                return;
            }
            clearCreateErrors();
            createForm.reset();
            createDialog.showModal();
            createName.focus();
        });
        createCancel.addEventListener('click', () => {
            if (createInFlight) return;
            createDialog.close('cancel');
        });
        createDialog.addEventListener('cancel', event => {
            if (createInFlight) event.preventDefault();
        });
        createDialog.addEventListener('close', () => {
            if (!createInFlight) createTrigger.focus();
        });

        createForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (createInFlight) return;
            clearCreateErrors();

            const surgery = createName.value.trim();
            const observations = createObservations.value;
            const nameError = root.querySelector('[data-m04-create-name-error]');
            const observationsError = root.querySelector('[data-m04-create-observations-error]');
            let invalid = false;
            if (!surgery) {
                setCreateFieldError(createName, nameError, 'Escribe el nombre de la cirugía.');
                invalid = true;
            } else if (Array.from(surgery).length > 2000) {
                setCreateFieldError(createName, nameError, 'El nombre no puede superar 2000 caracteres.');
                invalid = true;
            }
            if (Array.from(observations).length > 2000) {
                setCreateFieldError(createObservations, observationsError, 'Las observaciones no pueden superar 2000 caracteres.');
                invalid = true;
            }
            if (invalid) {
                (createName.getAttribute('aria-invalid') === 'true' ? createName : createObservations).focus();
                return;
            }
            if (!csrf || !root.dataset.m04CreateUrl || !/^[a-f0-9]{64}$/.test(root.dataset.m04Version || '')) {
                setM04Message(createMessage, 'No se pudo verificar la sesión o la versión de la lista. Recarga Respuestas previas.', 'error');
                return;
            }

            createInFlight = true;
            createSave.disabled = true;
            createCancel.disabled = true;
            createForm.setAttribute('aria-busy', 'true');
            setM04Message(createMessage, 'Guardando y verificando la cirugía…');
            try {
                const response = await fetch(root.dataset.m04CreateUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'X-M03-CSRF': csrf, 'Accept': 'application/json'},
                    body: JSON.stringify({
                        expected_version: root.dataset.m04Version,
                        cirugia: surgery,
                        fecha: null,
                        observaciones: observations,
                    }),
                });
                let result;
                try { result = await response.json(); } catch (_) { throw new Error('No se pudo leer la confirmación del servidor.'); }
                const recordId = Number(result.record_id);
                if (!response.ok || result.resultado !== true || result.readback !== true || !Number.isSafeInteger(recordId) || recordId < 1
                    || typeof result.cirugia !== 'string' || typeof result.observaciones !== 'string'
                    || (result.fecha !== null && (typeof result.fecha !== 'string' || !validExactDate(result.fecha)))
                    || typeof result.version !== 'string' || !/^[a-f0-9]{64}$/.test(result.version)
                    || typeof result.finalized !== 'boolean') {
                    if (response.status === 409) throw new Error(result.mensaje || 'La lista cambió. Recarga Respuestas previas antes de intentar de nuevo.');
                    throw new Error(result.mensaje || 'No se confirmó el registro. Conservamos lo que escribiste para que puedas intentarlo de nuevo.');
                }

                const card = cardTemplate.content.firstElementChild.cloneNode(true);
                const cardId = 'm04-previous-' + recordId;
                const dateValue = result.fecha || '';
                card.dataset.stableId = 'M04:record:' + recordId;
                card.dataset.recordId = String(recordId);
                card.dataset.version = result.version;
                card.dataset.originalSurgery = result.cirugia;
                card.dataset.originalDate = dateValue;
                card.dataset.originalObservations = result.observaciones;
                card.dataset.editable = '1';
                card.dataset.reviewQuestion = result.cirugia;
                card.dataset.reviewQuestionNumber = String(recordId);
                card.dataset.reviewQuestionId = card.dataset.stableId;
                card.setAttribute('data-stable-id', card.dataset.stableId);
                card.setAttribute('data-record-id', String(recordId));
                card.setAttribute('data-version', result.version);
                card.setAttribute('data-original-surgery', result.cirugia);
                card.setAttribute('data-original-date', dateValue);
                card.setAttribute('data-original-observations', result.observaciones);
                card.dataset.reviewStatus = 'REGISTRADA';

                const surgeryDisplay = card.querySelector('[data-m04-current-surgery]');
                const dateWrap = card.querySelector('[data-m04-date-wrap]');
                const dateDisplay = card.querySelector('[data-m04-current-date]');
                const observationsWrap = card.querySelector('[data-m04-observations-wrap]');
                const observationsDisplay = card.querySelector('[data-m04-current-observations]');
                const surgeryInput = card.querySelector('[data-m04-surgery-input]');
                const observationsInput = card.querySelector('[data-m04-observations-input]');
                const surgeryLabel = card.querySelector('[data-m04-label-surgery]');
                const observationsLabel = card.querySelector('[data-m04-label-observations]');
                if (!surgeryDisplay || !dateWrap || !dateDisplay || !observationsWrap || !observationsDisplay
                    || !surgeryInput || !observationsInput || !surgeryLabel || !observationsLabel) {
                    throw new Error('No se pudo preparar la tarjeta de la cirugía confirmada.');
                }
                surgeryDisplay.textContent = result.cirugia;
                dateDisplay.textContent = dateValue;
                dateWrap.hidden = dateValue === '';
                observationsDisplay.textContent = result.observaciones;
                observationsWrap.hidden = result.observaciones === '';
                surgeryInput.id = cardId + '-surgery';
                observationsInput.id = cardId + '-observations';
                surgeryLabel.htmlFor = surgeryInput.id;
                observationsLabel.htmlFor = observationsInput.id;
                surgeryInput.value = result.cirugia;
                observationsInput.value = result.observaciones;

                root.querySelectorAll('[data-m04-review-card]').forEach(item => { item.dataset.version = result.version; });
                root.dataset.m04Version = result.version;
                bindM04ReviewCard(card);
                recordList.insertBefore(card, recordList.firstChild);
                recordList.hidden = false;
                emptyState.hidden = true;
                const filters = window.PatientPreviousResponsesFilters;
                if (filters && typeof filters.refreshCards === 'function') filters.refreshCards(document);
                setM04Message(card.querySelector('[data-m04-saved-message]'), 'Cirugía registrada.', 'success');
                createForm.reset();
                createDialog.close('success');
            } catch (error) {
                setM04Message(createMessage, error instanceof Error ? error.message : 'No se confirmó el registro. Inténtalo nuevamente.', 'error');
            } finally {
                createInFlight = false;
                createSave.disabled = false;
                createCancel.disabled = false;
                createForm.removeAttribute('aria-busy');
                if (!createDialog.open) createTrigger.focus();
            }
        });
    }

    const setM05Message = (node, message, state = '') => {
        if (!node) return;
        node.textContent = message;
        node.dataset.state = state;
    };
    const postM05Edit = async (url, payload) => {
        const target = new URL(url, window.location.href);
        if (target.origin !== window.location.origin || !csrf) throw new Error('La sesión segura no está disponible. Recarga Respuestas previas.');
        const response = await fetch(target.href, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'X-M03-CSRF': csrf, 'Accept': 'application/json'},
            body: JSON.stringify(payload),
        });
        let result;
        try { result = await response.json(); } catch (_) { throw new Error('No se pudo leer la confirmación del servidor.'); }
        if (!response.ok || result.resultado !== true || result.readback !== true) {
            throw new Error(result.mensaje || 'No se confirmó la actualización. Recarga antes de reintentar.');
        }
        return result;
    };
    const refreshM05Versions = (selector, version) => root.querySelectorAll(selector).forEach(card => { card.dataset.version = version; });
    const refreshPreviousResponseFilters = () => window.PatientPreviousResponsesFilters?.refreshCards?.(document);

    root.querySelectorAll('[data-m05-v1-card][data-editable="1"]').forEach(card => {
        const display = card.querySelector('[data-m05-v1-display]');
        const editor = card.querySelector('[data-m05-v1-editor]');
        const actions = card.querySelector('[data-m05-v1-display-actions]');
        const edit = card.querySelector('[data-m05-v1-start-edit]');
        const answer = card.querySelector('[data-m05-v1-answer]');
        const save = card.querySelector('[data-m05-v1-save]');
        const cancel = card.querySelector('[data-m05-v1-cancel]');
        const message = card.querySelector('[data-m05-v1-message]');
        const saved = card.querySelector('[data-m05-v1-saved-message]');
        if (!display || !editor || !actions || !edit || !answer || !save || !cancel) return;
        const restore = () => {
            const original = card.dataset.originalAnswer || '';
            answer.value = answer.tagName === 'SELECT' && original === 'Sí' ? 'Si' : original;
        };
        edit.addEventListener('click', () => {
            if (card.dataset.saving === '1') return;
            restore(); setM05Message(message, ''); setM05Message(saved, '');
            display.hidden = true; actions.hidden = true; editor.hidden = false; answer.focus();
        });
        cancel.addEventListener('click', () => {
            if (card.dataset.saving === '1') return;
            restore(); editor.hidden = true; display.hidden = false; actions.hidden = false; setM05Message(message, '');
        });
        editor.addEventListener('submit', async event => {
            event.preventDefault();
            if (card.dataset.saving === '1') return;
            const value = answer.value;
            if (!value.trim() || value.length > 2000) { setM05Message(message, 'Escribe una respuesta de hasta 2000 caracteres.', 'error'); answer.focus(); return; }
            card.dataset.saving = '1'; save.disabled = true; cancel.disabled = true; edit.disabled = true;
            setM05Message(message, 'Guardando y verificando la respuesta…');
            try {
                const result = await postM05Edit(root.dataset.m05V1EditUrl || '', {
                    stable_id: card.dataset.stableId || '', response_id: Number(card.dataset.responseId || 0),
                    expected_version: card.dataset.version || '', answer: value,
                });
                if (Number(result.response_id) !== Number(card.dataset.responseId)
                    || Number(result.question_id) !== Number(card.dataset.questionId)
                    || typeof result.answer !== 'string' || typeof result.version !== 'string') {
                    throw new Error('La lectura posterior no corresponde a esta pregunta. Recarga para revisar el estado actual.');
                }
                card.dataset.originalAnswer = result.answer;
                card.setAttribute('data-original-answer', result.answer);
                answer.value = result.answer;
                card.querySelector('[data-m05-v1-current-answer]').textContent = result.answer;
                card.dataset.reviewStatus = 'REGISTRADA'; card.dataset.reviewPending = '0';
                const status = card.querySelector('[data-status]');
                if (status) { status.dataset.status = 'REGISTRADA'; status.textContent = 'Registrada'; }
                refreshM05Versions('[data-m05-v1-card]', result.version);
                root.dataset.m05V1Version = result.version;
                editor.hidden = true; display.hidden = false; actions.hidden = false;
                setM05Message(message, ''); setM05Message(saved, 'Respuesta actualizada.', 'success'); refreshPreviousResponseFilters();
            } catch (error) {
                setM05Message(message, error instanceof Error ? error.message : 'No se confirmó la actualización.', 'error');
            } finally {
                card.dataset.saving = '0'; save.disabled = false; cancel.disabled = false; edit.disabled = false;
            }
        });
    });

    root.querySelectorAll('[data-m05-v2-card][data-editable="1"]').forEach(card => {
        const display = card.querySelector('[data-m05-v2-display]');
        const editor = card.querySelector('[data-m05-v2-editor]');
        const actions = card.querySelector('[data-m05-v2-display-actions]');
        const edit = card.querySelector('[data-m05-v2-start-edit]');
        const answer = card.querySelector('[data-m05-v2-response]');
        const year = card.querySelector('[data-m05-v2-year]');
        const clearYear = card.querySelector('[data-m05-clear-year]');
        const save = card.querySelector('[data-m05-v2-save]');
        const cancel = card.querySelector('[data-m05-v2-cancel]');
        const message = card.querySelector('[data-m05-v2-message]');
        const saved = card.querySelector('[data-m05-v2-saved-message]');
        if (!display || !editor || !actions || !edit || !answer || !year || !clearYear || !save || !cancel) return;
        const restore = () => {
            answer.value = card.dataset.originalResponse || '';
            const oldYear = card.dataset.originalYear || '';
            year.value = /^(19|20)\d{2}$/.test(oldYear) ? oldYear : '';
            clearYear.checked = false;
        };
        edit.addEventListener('click', () => {
            if (card.dataset.saving === '1') return;
            restore(); setM05Message(message, ''); setM05Message(saved, '');
            display.hidden = true; actions.hidden = true; editor.hidden = false; answer.focus();
        });
        cancel.addEventListener('click', () => {
            if (card.dataset.saving === '1') return;
            restore(); editor.hidden = true; display.hidden = false; actions.hidden = false; setM05Message(message, '');
        });
        clearYear.addEventListener('change', () => { if (clearYear.checked) year.value = ''; });
        editor.addEventListener('submit', async event => {
            event.preventDefault();
            if (card.dataset.saving === '1') return;
            const responseText = answer.value;
            const originalYear = card.dataset.originalYear || '';
            const yearText = year.value.trim();
            if (!responseText.trim() || responseText.length > 2000) { setM05Message(message, 'Escribe la respuesta completa antes de guardar.', 'error'); answer.focus(); return; }
            if (yearText !== '' && !/^(19|20)\d{2}$/.test(yearText)) { setM05Message(message, 'Escribe un año válido de cuatro dígitos o conserva el año guardado.', 'error'); year.focus(); return; }
            if (clearYear.checked && yearText !== '') { setM05Message(message, 'Elimina el texto del año o desmarca la casilla de eliminación.', 'error'); year.focus(); return; }
            const payload = {
                stable_id: card.dataset.stableId || '', disease_id: Number(card.dataset.diseaseId || 0),
                response_id: Number(card.dataset.responseId || 0), expected_version: card.dataset.version || '',
                response: responseText, clear_year: clearYear.checked,
            };
            if (!clearYear.checked && yearText !== '' && yearText !== originalYear) payload.year_diagnostico = yearText;
            card.dataset.saving = '1'; save.disabled = true; cancel.disabled = true; edit.disabled = true;
            setM05Message(message, 'Guardando y verificando la respuesta…');
            try {
                const result = await postM05Edit(root.dataset.m05V2EditUrl || '', payload);
                if (Number(result.response_id) !== Number(card.dataset.responseId)
                    || Number(result.disease_id) !== Number(card.dataset.diseaseId)
                    || typeof result.response !== 'string' || typeof result.version !== 'string'
                    || result.tipo_unchanged !== true) {
                    throw new Error('La lectura posterior no corresponde a esta enfermedad. Recarga para revisar el estado actual.');
                }
                const nextYear = result.year_diagnostico == null ? '' : String(result.year_diagnostico);
                const nextYearDisplay = nextYear === 'NR' ? 'No recuerdo' : nextYear;
                card.dataset.originalResponse = result.response; card.dataset.originalYear = nextYear;
                card.setAttribute('data-original-response', result.response); card.setAttribute('data-original-year', nextYear);
                answer.value = result.response; year.value = /^(19|20)\d{2}$/.test(nextYear) ? nextYear : ''; clearYear.checked = false;
                card.querySelector('[data-m05-v2-current-response]').textContent = result.response;
                const yearWrap = card.querySelector('[data-m05-v2-year-wrap]');
                const yearNode = card.querySelector('[data-m05-v2-current-year]');
                if (yearNode) yearNode.textContent = nextYearDisplay;
                if (yearWrap) yearWrap.hidden = nextYearDisplay === '';
                const nextStatus = result.needs_clarification === true ? 'POR_ACLARAR' : 'REGISTRADA';
                card.dataset.reviewStatus = nextStatus; card.dataset.reviewPending = result.needs_clarification === true ? '1' : '0';
                const status = card.querySelector('[data-status]');
                if (status) { status.dataset.status = nextStatus; status.textContent = nextStatus === 'POR_ACLARAR' ? 'Por aclarar' : 'Registrada'; }
                refreshM05Versions('[data-m05-v2-card]', result.version);
                root.dataset.m05V2Version = result.version;
                editor.hidden = true; display.hidden = false; actions.hidden = false;
                setM05Message(message, ''); setM05Message(saved, 'Respuesta actualizada.', 'success'); refreshPreviousResponseFilters();
            } catch (error) {
                setM05Message(message, error instanceof Error ? error.message : 'No se confirmó la actualización.', 'error');
            } finally {
                card.dataset.saving = '0'; save.disabled = false; cancel.disabled = false; edit.disabled = false;
            }
        });
    });
})();
</script>
</body>
</html>
