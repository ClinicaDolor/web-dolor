<?php
declare(strict_types=1);

$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];
$reviewUrl = SERVIDOR . 'historia-clinica/respuestas-previas/m02';
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
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?=RUTA_JS;?>loader.js"></script>
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
        @media (max-width:575.98px) {
            .previous-responses-content { padding:.85rem; }
            .previous-responses-hero { align-items:stretch; flex-direction:column; }
            .previous-responses-hero > .btn { width:100%; }
            .previous-response-body { padding:1rem; }
            .previous-response-actions > .btn { flex:1 1 100%; }
        }
    </style>
</head>
<body class="app-shell-body app-shell-patient dashboard-patient previous-responses-page">
<div class="LoaderPage" aria-hidden="true"></div>
<div id="app">
    <?=$data['sidebar'];?>
    <div id="main" data-csrf="<?=$escape($data['csrf'] ?? '');?>" data-edit-url="<?=$escape($reviewUrl);?>">
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
                <a class="btn btn-outline-primary" href="<?=htmlspecialchars(SERVIDOR . 'historia-clinica/entrevista', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>">Cerrar revisión y volver a la entrevista</a>
            </header>

            <aside class="previous-responses-notice" aria-label="Información sobre la revisión">
                <strong>Revisa y corrige tus respuestas</strong>
                <p>Puedes completar o corregir lo que registraste. Al guardar, se mostrará únicamente la respuesta actualizada.</p>
                <?php if (!empty($data['m02Finalized'])): ?><p class="mt-2 mb-0">M02 está finalizado. Revisar o corregir una respuesta no reabre el módulo ni cambia su progreso.</p><?php endif; ?>
            </aside>

            <section aria-labelledby="m02-previous-title">
                <h2 id="m02-previous-title" class="previous-responses-section-title">M02 · Antecedentes heredofamiliares</h2>
                <?php if ($entries === []): ?>
                    <p class="previous-responses-empty">Aún no hay respuestas M02 registradas para consultar.</p>
                <?php else: ?>
                    <div class="previous-responses-list">
                        <?php foreach ($entries as $index => $entry): ?>
                            <?php $cardId = 'm02-previous-' . ($index + 1); ?>
                            <article class="previous-response-card" data-m02-review-card data-stable-id="<?=$escape($entry['stable_id']);?>" data-version="<?=$escape($entry['version']);?>" data-editable="<?=$entry['editable'] ? '1' : '0';?>">
                                <div class="previous-response-heading">Pregunta <?=$index + 1;?></div>
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
            </section>
            <div class="previous-responses-footer">
                <a class="btn btn-primary" href="<?=htmlspecialchars(SERVIDOR . 'historia-clinica/entrevista', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>">Cerrar revisión y volver a la entrevista</a>
            </div>
        </main>
    </div>
</div>
<script src="<?=RUTA_JS;?>/feather-icons/feather.min.js"></script>
<script src="<?=RUTA_PUBLIC;?>libs/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="<?=RUTA_JS;?>app.js"></script>
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
})();
</script>
</body>
</html>
