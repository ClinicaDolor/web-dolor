(function () {
    'use strict';

    const panel = document.getElementById('clinicalSummaryPanel');
    const button = document.getElementById('clinicalSummaryGenerate');
    const output = document.getElementById('clinicalSummaryOutput');
    if (!panel || !button || !output) return;

    const esc = (value) => { const element = document.createElement('span'); element.textContent = value == null ? '' : String(value); return element.innerHTML; };
    const statusLabel = (status) => ({ documented: 'Documentado', absent: 'No registrado', incomplete: 'Incompleto', contradictory: 'Requiere revisión' }[status] || 'No especificado');
    const list = (items, empty = 'No registrado') => items.length ? '<ul>' + items.map((item) => '<li>' + esc(item) + '</li>').join('') + '</ul>' : '<p class="clinical-summary-overview-empty">' + esc(empty) + '.</p>';
    const overviewCard = (icon, title, items) => '<article class="clinical-summary-overview-card"><div class="clinical-summary-overview-icon"><i data-feather="' + icon + '" aria-hidden="true"></i></div><h3>' + esc(title) + '</h3>' + list(items) + '</article>';
    const isRecorded = (value) => value !== null && value !== undefined && String(value).trim() !== '' && !['no registrado', 'no registrada', 'no especificado', 'no especificada', 'no aplica', 'no aplicable'].includes(String(value).trim().toLowerCase());
    const renderFields = (items) => { const visible = items.filter((field) => isRecorded(field.value)); return !visible.length ? '<p class="clinical-detail-empty">No registrado.</p>' : '<dl class="clinical-detail-fields">' + visible.map((field) => '<div><dt>' + esc(field.label) + '</dt><dd>' + esc(field.value) + '</dd></div>').join('') + '</dl>'; };
    const recordHasDocumentedResponse = (record, key) => {
        const allFields = (record.groups || []).flatMap((group) => group.fields || []);
        if (['M02', 'M03', 'M05', 'M07'].includes(key)) return allFields.some((field) => ['Respuesta', 'Respuesta documentada'].includes(field.label) && isRecorded(field.value));
        if (key === 'M08' && record.meta?.source_kind === 'tratamiento_cuestionario') return allFields.some((field) => field.label === 'Utilizó' && isRecorded(field.value));
        return allFields.some((field) => isRecorded(field.value));
    };
    const narrativeList = (items, empty = 'No registrado') => items.length ? '<ul>' + items.map((item) => '<li>' + esc(item.text) + ' ' + (item.references || []).map((ref) => '<span class="clinical-summary-reference">' + esc(ref) + '</span>').join(' ') + '</li>').join('') + '</ul>' : '<p class="clinical-summary-overview-empty">' + esc(empty) + '.</p>';
    const narrativeCard = (title, items, className = '', empty = 'No registrado') => '<article class="clinical-summary-executive-card ' + className + '"><h3>' + esc(title) + '</h3>' + narrativeList(items, empty) + '</article>';

    const renderExecutive = (data, sections) => {
        const narrative = data.clinical_narrative?.executive || {};
        const general = narrative.general || { text: 'No se dispone de una síntesis ejecutiva.', references: [] };
        const background = narrative.background || { positive: [], negative: [], pending: [] };
        const pain = narrative.pain || { items: [], summary: 'No se dispone de una evaluación del dolor registrada.' };
        const treatments = narrative.treatments || {};
        const notes = narrative.followup || [];
        const documented = sections.filter((section) => section.status === 'documented').length;
        return '<section class="clinical-summary-executive" id="clinicalExecutive" aria-labelledby="clinicalExecutiveTitle"><div class="clinical-summary-section-heading"><p class="dashboard-eyebrow">LECTURA RÁPIDA</p><h2 id="clinicalExecutiveTitle">Resumen ejecutivo médico</h2><p class="clinical-summary-section-description">Síntesis determinista de información persistida; no agrega diagnósticos, riesgos ni recomendaciones.</p></div><div class="clinical-summary-executive-grid"><article class="clinical-summary-executive-card clinical-summary-executive-main"><p class="dashboard-eyebrow">SÍNTESIS GENERAL DEL CASO</p><p class="clinical-summary-executive-lead">' + esc(general.text) + '</p><p class="clinical-summary-executive-source">' + general.references.map((ref) => '<span class="clinical-summary-reference">' + esc(ref) + '</span>').join(' ') + ' · ' + esc(documented) + ' de 9 módulos documentados.</p></article>' + narrativeCard('Antecedentes positivos documentados', background.positive, 'clinical-summary-executive-secondary') + narrativeCard('Negaciones explícitas', background.negative, 'clinical-summary-executive-secondary', 'No se identificaron negaciones explícitas en las fuentes consultadas') + narrativeCard('Información pendiente de confirmar', background.pending, 'clinical-summary-executive-wide') + narrativeCard('Situación actual del dolor', pain.items, 'clinical-summary-executive-wide', pain.summary) + narrativeCard('Medicación actual documentada', treatments.current_medication || [], 'clinical-summary-executive-secondary') + narrativeCard('Tratamientos para el dolor', treatments.pain_treatments || [], 'clinical-summary-executive-secondary') + narrativeCard('Procedimientos registrados', treatments.procedures || [], 'clinical-summary-executive-wide') + narrativeCard('Prescripciones históricas', treatments.historical_prescriptions || [], 'clinical-summary-executive-secondary', 'Sin prescripciones históricas registradas') + narrativeCard('Registros COFEPRIS', treatments.cofepris || [], 'clinical-summary-executive-secondary', 'Sin registros COFEPRIS asociados') + narrativeCard('Seguimiento clínico reciente', notes, 'clinical-summary-executive-wide', 'Sin notas subsecuentes registradas en las fuentes consultadas') + '</div></section>';
    };

    const pendingEntries = (data, sections) => {
        if (Array.isArray(data.clinical_narrative?.pending)) return data.clinical_narrative.pending.map((entry) => ({ ...entry, anchor: '#clinical-module-' + entry.module }));
        const result = [];
        sections.forEach((section) => {
            const anchor = '#clinical-module-' + section.key;
            if (section.status === 'absent') result.push({ category: 'Información no registrada', text: section.title, anchor });
            if (section.status === 'incomplete') result.push({ category: 'Información incompleta', text: section.title, anchor });
            if (section.status === 'contradictory') result.push({ category: 'Inconsistencia documental', text: section.title, anchor });
            (section.missing || []).forEach((item) => result.push({ category: 'Revisión médica', text: item, anchor }));
            (section.review || []).forEach((item) => result.push({ category: 'Revisión médica', text: item, anchor }));
        });
        return result;
    };

    const renderPending = (entries) => {
        if (!entries.length) return '<section class="clinical-summary-pending clinical-summary-pending-clear" id="clinicalPending" aria-labelledby="clinicalPendingTitle"><div class="clinical-summary-pending-heading"><i data-feather="check-circle" aria-hidden="true"></i><div><p class="dashboard-eyebrow">SEGUIMIENTO</p><h2 id="clinicalPendingTitle">Sin pendientes documentales clasificados</h2></div></div><p>La ausencia de un pendiente no sustituye la revisión del expediente original.</p></section>';
        return '<section class="clinical-summary-pending" id="clinicalPending" aria-labelledby="clinicalPendingTitle"><div class="clinical-summary-pending-heading"><i data-feather="alert-circle" aria-hidden="true"></i><div><p class="dashboard-eyebrow">SEGUIMIENTO</p><h2 id="clinicalPendingTitle">Información pendiente de revisión</h2></div></div><div class="clinical-summary-pending-list">' + entries.map((entry) => '<div class="clinical-summary-pending-item"><span>' + esc(entry.category) + '</span><a href="' + esc(entry.anchor) + '">' + esc(entry.text) + '</a></div>').join('') + '</div></section>';
    };

    const timelineRecords = (data) => {
        const sourceMap = [['notes', 'Nota subsecuente', 'clipboard', 'NOTA'], ['prescriptions', 'Receta médica', 'edit-3', 'RECETA'], ['laboratories', 'Laboratorio', 'file-text', 'LAB'], ['cofepris', 'COFEPRIS', 'archive', 'COFEPRIS']];
        const events = [];
        sourceMap.forEach(([key, type, icon, prefix]) => (data.clinical_supplement?.[key]?.records || []).forEach((record, index) => {
            let description = 'Registro documentado.';
            if (key === 'notes') description = record.contenido || 'Seguimiento documentado.';
            if (key === 'prescriptions') description = record.medicamento ? 'Medicamento prescrito: ' + record.medicamento : 'Prescripción documentada.';
            if (key === 'laboratories') description = record.titulo || record.nombre || 'Estudio registrado.';
            if (key === 'cofepris') description = record.medicamento ? 'Medicamento registrado: ' + record.medicamento : 'Registro documentado.';
            events.push({ date: record.fecha_hora, type, icon, ref: prefix + ':R' + String(index + 1).padStart(3, '0'), description });
        }));
        return events.sort((a, b) => String(b.date || '').localeCompare(String(a.date || '')));
    };

    const renderTimeline = (data) => {
        const events = timelineRecords(data);
        const formatDate = (date) => { if (!date) return 'Fecha no registrada'; const parsed = new Date(date); return Number.isNaN(parsed.getTime()) ? 'Fecha no registrada' : parsed.toLocaleString('es-MX', { dateStyle: 'long', timeStyle: 'short' }); };
        const body = events.length ? events.map((event) => '<article class="clinical-timeline-event"><div class="clinical-timeline-marker"><i data-feather="' + esc(event.icon) + '" aria-hidden="true"></i></div><div class="clinical-timeline-card"><div class="clinical-timeline-heading"><time datetime="' + esc(event.date || '') + '">' + esc(formatDate(event.date)) + '</time><span class="clinical-summary-reference">' + esc(event.ref) + '</span></div><h3>' + esc(event.type) + '</h3><p>' + esc(event.description) + '</p></div></article>').join('') : '<p class="clinical-timeline-empty">No hay seguimiento complementario registrado.</p>';
        return '<section class="clinical-summary-timeline" id="clinicalTimeline" aria-labelledby="clinicalTimelineTitle"><div class="clinical-summary-section-heading"><p class="dashboard-eyebrow">EVOLUCIÓN DOCUMENTADA</p><h2 id="clinicalTimelineTitle">Línea de tiempo clínica</h2><p class="clinical-summary-section-description">Secuencia descendente de notas, recetas, laboratorios y COFEPRIS autorizados. Los archivos protegidos no se interpretan ni se descargan.</p></div><div class="clinical-timeline-list">' + body + '</div></section>';
    };

    const renderNavigation = (sections) => '<nav class="clinical-summary-quick-nav" aria-label="Navegación rápida del resumen"><strong>Ir a</strong><a href="#clinicalExecutive">Resumen ejecutivo</a><a href="#clinicalTimeline">Seguimiento clínico</a>' + sections.map((section) => '<a href="#clinical-module-' + esc(section.key) + '">' + esc(section.key) + '</a>').join('') + '<a href="#clinicalPending">Datos pendientes</a></nav>';

    const renderModule = (data, section) => {
        const detail = data.clinical_detail?.modules?.[section.key];
        const status = section.status || 'absent';
        const records = (Array.isArray(detail?.records) ? detail.records : []).filter((record) => recordHasDocumentedResponse(record, section.key));
        let body = '';
        if (records.length) {
            body += '<div class="clinical-detail-summary"><p class="dashboard-eyebrow">RESUMEN DOCUMENTADO</p><p>Se encontraron ' + esc(records.length) + ' registro(s) estructurado(s) para este módulo.</p></div><div class="clinical-original-records-body">';
            records.forEach((record, recordIndex) => { body += '<article class="clinical-original-record"><div class="clinical-original-record-heading"><span>Registro ' + esc(recordIndex + 1) + '</span><span class="clinical-summary-reference">' + esc(record.ref) + '</span></div>' + (record.groups || []).map((group) => (group.title ? '<h4>' + esc(group.title) + '</h4>' : '') + renderFields(group.fields || [])).join('') + '</article>'; });
            body += '</div>';
        } else if (section.items && section.items.length) body += '<div class="clinical-detail-summary"><p class="dashboard-eyebrow">RESUMEN DOCUMENTADO</p><ul class="clinical-detail-summary-list">' + section.items.map((item) => '<li>' + esc(item.text) + ' ' + (item.references || []).map((ref) => '<span class="clinical-summary-reference">' + esc(ref) + '</span>').join(' ') + '</li>').join('') + '</ul></div>';
        else body += '<div class="clinical-detail-empty-state"><i data-feather="file-minus" aria-hidden="true"></i><p>' + esc(status === 'incomplete' ? 'Incompleto; requiere revisión médica.' : 'No registrado.') + '</p></div>';
        if (section.missing?.length) body += '<div class="clinical-detail-missing"><strong>Información faltante:</strong> ' + section.missing.map((item) => esc(item)).join(' · ') + '</div>';
        return '<article class="clinical-detail-module clinical-detail-module-continuous" id="clinical-module-' + esc(section.key) + '"><header class="clinical-detail-module-header"><div><p class="dashboard-eyebrow">' + esc(section.key) + '</p><h3>' + esc(detail?.title || section.title) + '</h3></div><span class="clinical-summary-status clinical-summary-status-' + esc(status) + '">' + esc(statusLabel(status)) + '</span></header><div class="clinical-detail-module-body">' + body + '</div></article>';
    };

    const render = (data) => {
        const summary = data.summary || {};
        const sections = Array.isArray(summary.sections) ? summary.sections : [];
        const modeLabel = summary.mode === 'gemini' ? 'Google Gemini' : 'Generado localmente · determinista';
        const pending = pendingEntries(data, sections);
        const documented = sections.filter((section) => section.status === 'documented').length;
        let html = '<div class="clinical-summary-meta"><strong>' + esc(modeLabel) + '</strong>' + (summary.model ? ' · ' + esc(summary.model) : '') + ' <span aria-hidden="true">·</span> ' + esc(new Date(summary.generated_at || Date.now()).toLocaleString()) + '</div><p class="clinical-summary-notice">Resumen de apoyo sujeto a revisión médica. No sustituye la información original del expediente.</p>';
        html += renderExecutive(data, sections) + renderPending(pending) + renderTimeline(data) + renderNavigation(sections);
        html += '<section class="clinical-summary-overview" id="clinicalOverview" aria-labelledby="clinicalSummaryOverviewTitle"><div class="clinical-summary-section-heading"><p class="dashboard-eyebrow">CONTROL DE COBERTURA</p><h2 id="clinicalSummaryOverviewTitle">Panorama de módulos</h2><p class="clinical-summary-section-description">' + esc(documented) + ' de 9 módulos con información documental disponible.</p></div><div class="clinical-summary-overview-grid">' + overviewCard('activity', 'Cobertura clínica', [documented + ' de 9 módulos documentados.']) + overviewCard('search', 'Antecedentes', ['Consulta el detalle verificable en M01–M05.']) + overviewCard('package', 'Medicación y tratamientos', ['Consulta fuentes diferenciadas en M06–M08.']) + overviewCard('bar-chart-2', 'Evaluación del dolor', ['Consulta los campos documentados en M09.']) + '</div></section>';
        html += '<section class="clinical-summary-history" id="clinicalHistory" aria-labelledby="clinicalSummaryHistoryTitle"><div class="clinical-summary-section-heading"><p class="dashboard-eyebrow">HISTORIA CLÍNICA EN LECTURA CONTINUA</p><h2 id="clinicalSummaryHistoryTitle">Detalle de módulos M01–M09</h2><p class="clinical-summary-section-description">La información completa está disponible consecutivamente para revisión clínica.</p></div><div class="clinical-summary-continuous-list">' + sections.map((section) => renderModule(data, section)).join('') + '</div></section>';
        output.innerHTML = html;
        if (window.feather) window.feather.replace();
    };

    button.addEventListener('click', async () => {
        if (button.disabled) return;
        button.disabled = true;
        button.dataset.loading = 'true';
        const label = button.querySelector('span');
        if (label) label.textContent = 'Generando…';
        output.innerHTML = '<div class="clinical-summary-loading" role="status"><span class="clinical-summary-loading-spinner" aria-hidden="true"></span><div><strong>Generando resumen local</strong><p>Recuperando información clínica autorizada…</p></div></div>';
        try {
            const response = await fetch('/clinica/paciente/' + encodeURIComponent(panel.dataset.patientId) + '/clinical-summary', { method: 'POST', headers: { 'X-Clinical-Summary-CSRF': panel.dataset.csrfToken, Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'SUMMARY_ERROR');
            render(data);
            if (label) label.textContent = 'Regenerar resumen';
        } catch (error) {
            output.innerHTML = '<div class="alert alert-warning" role="alert">No fue posible generar el resumen. Código: ' + esc(error.message) + '</div>';
            if (label) label.textContent = 'Reintentar';
        } finally {
            button.disabled = false;
            delete button.dataset.loading;
            if (window.feather) window.feather.replace();
        }
    });
}());
