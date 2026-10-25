<?php
declare(strict_types=1);

$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];
$m02StatusItems = is_array($data['m02StatusItems'] ?? null) ? $data['m02StatusItems'] : [];
$m03Themes = is_array($data['m03Themes'] ?? null) ? $data['m03Themes'] : [];
$statusLabel = static fn(string $status): string => match ($status) {
    'REGISTRADA' => 'Registrada',
    'PENDIENTE' => 'Pendiente',
    'POR_ACLARAR' => 'Por aclarar',
    'NO_APLICA' => 'No aplica',
    'REQUIERE_REVISION' => 'Requiere revisión',
    default => 'Requiere revisión',
};
$reviewUrl = SERVIDOR . 'historia-clinica/respuestas-previas/m02';
$m03ReviewUrl = is_string($data['m03EditUrl'] ?? null) ? $data['m03EditUrl'] : SERVIDOR . 'historia-clinica/respuestas-previas/m03';
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
            .previous-response-body { padding:1rem; }
            .previous-response-actions > .btn { flex:1 1 100%; }
            .previous-responses-filters { grid-template-columns:1fr; padding:.9rem; }
            .previous-responses-filter-options { align-items:stretch; flex-direction:column; }
            .previous-responses-filter-meta { justify-content:space-between; }
        }
    </style>
</head>
<body class="app-shell-body app-shell-patient dashboard-patient previous-responses-page">
<div id="app">
    <?=$data['sidebar'];?>
    <div id="main" data-csrf="<?=$escape($data['csrf'] ?? '');?>" data-edit-url="<?=$escape($reviewUrl);?>" data-m03-edit-url="<?=$escape($m03ReviewUrl);?>">
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
            <p class="text-muted small">Este apoyo de preparación muestra únicamente preguntas de M02 y M03; no representa toda la historia clínica ni sustituye la revisión con tu equipo médico.</p>

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
})();
</script>
</body>
</html>
