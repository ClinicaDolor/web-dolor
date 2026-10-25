(function (root) {
    'use strict';

    const searchIndexCache = new WeakMap();
    const MIN_ALIAS_PREFIX_LENGTH = 3;
    const COMMON_WORDS = new Set(['si', 'ya', 'lo', 'la', 'los', 'las', 'de', 'del', 'el', 'en', 'a', 'al', 'un', 'una', 'y', 'o', 'se', 'su', 'sus', 'que', 'como', 'cual', 'cuando', 'cuanto', 'hace', 'tiempo', 'por', 'para', 'con']);
    const SEARCH_INTENTS = [
        {
            aliases: ['fumar', 'fumo', 'fuma', 'fume', 'fumando', 'fumador', 'fumadores', 'fumadora', 'fumadoras', 'tabaquismo', 'tabaco', 'cigarro', 'cigarros', 'cigarrillo', 'cigarrillos', 'nicotina'],
            terms: ['fumar', 'fumo', 'fuma', 'fum', 'tabaquismo', 'tabaco', 'cigarro', 'cigarrillo', 'nicotina']
        },
        {
            aliases: ['alcohol', 'alcoholismo', 'bebida', 'bebidas', 'bebida alcoholica', 'bebidas alcoholicas', 'consumo de alcohol'],
            terms: ['alcohol', 'alcoholismo', 'bebida', 'bebidas', 'consumo de alcohol', 'consumo alcohol']
        },
        {
            aliases: ['ejercicio', 'actividad fisica', 'deporte', 'deportivo', 'deportiva'],
            terms: ['ejercicio', 'actividad fisica', 'actividad deportiva', 'deporte', 'deportivo', 'deportiva']
        },
        {
            aliases: ['dormir', 'sueno', 'descanso', 'descansar', 'horas de descanso'],
            terms: ['dormir', 'duerme', 'sueno', 'descanso', 'descansar', 'horas de descanso', 'medicacion para dormir']
        },
        {
            aliases: ['cuanto tiempo', 'tiempo', 'duracion', 'antiguedad', 'desde hace', 'desde cuando', 'hace cuanto', 'tiempo de uso', 'por cuanto tiempo'],
            terms: ['cuanto tiempo', 'duracion', 'durante cuanto', 'desde hace', 'desde cuando', 'desde que edad', 'hace cuanto', 'antiguedad', 'tiempo de uso']
        }
    ];

    function normalizeSearchText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('es-MX')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function getSearchIndex(card) {
        if (!card || (typeof card !== 'object' && typeof card !== 'function')) {
            return {fields: [], text: ''};
        }
        const cached = searchIndexCache.get(card);
        if (cached) return cached;
        const data = card.dataset || {};
        const fields = [
            data.reviewModuleId,
            data.reviewModuleName,
            data.reviewTheme,
            data.reviewQuestion,
            data.reviewQuestionNumber,
            data.reviewQuestionId
        ].map(normalizeSearchText).filter(Boolean);
        const index = {fields, text: fields.join(' '), question: normalizeQuestionText(data.reviewQuestion)};
        searchIndexCache.set(card, index);
        return index;
    }

    function normalizeQuestionText(value) {
        return normalizeSearchText(value).replace(/[^\p{L}\p{N}\s]/gu, ' ').replace(/\s+/g, ' ').trim();
    }

    function queryMatchesAlias(query, alias) {
        if (query === alias) return true;
        return query.length >= MIN_ALIAS_PREFIX_LENGTH && alias.startsWith(query);
    }

    function getMatchingIntents(query) {
        if (!query) return [];
        return SEARCH_INTENTS.filter(intent => intent.aliases.some(alias => queryMatchesAlias(query, normalizeSearchText(alias))));
    }

    function searchRelevance(card, query) {
        const normalizedQuery = normalizeSearchText(query);
        if (!normalizedQuery) return 1;
        const index = getSearchIndex(card);
        if (!index.text) return 0;

        const phrase = normalizeQuestionText(query);
        const meaningfulTokens = phrase.split(' ').filter(token => token && !COMMON_WORDS.has(token));
        const intents = getMatchingIntents(phrase);
        // A generic word alone must not match arbitrary substrings (e.g. "si"
        // inside "fisica"). A recognized multiword concept remains searchable.
        if (!meaningfulTokens.length && !(phrase.includes(' ') && intents.length)) return 0;
        if (index.question === phrase) return 200;
        if (phrase.includes(' ') && index.question.includes(phrase)) return 150;

        if (index.fields.some(field => field === normalizedQuery)) return 120;
        if (index.fields.some(field => field.includes(normalizedQuery))) return 100;

        if (meaningfulTokens.length > 1 && meaningfulTokens.every(token => index.question.includes(token))) return 80;

        if (intents.some(intent => intent.terms.some(term => index.text.includes(normalizeSearchText(term))))) return 45;
        return 0;
    }

    function matchesReviewCard(card, filters) {
        const data = card && card.dataset ? card.dataset : {};
        const selectedModule = String(filters.module || '').toUpperCase();
        if (selectedModule && String(data.reviewModuleId || '').toUpperCase() !== selectedModule) return false;
        if (filters.pendingOnly && data.reviewPending !== '1') return false;
        return searchRelevance(card, filters.query) > 0;
    }

    function filterReviewCards(cards, filters) {
        const currentFilters = filters || {};
        const ranked = Array.from(cards || [])
            .map((card, index) => ({card, index, relevance: searchRelevance(card, currentFilters.query)}))
            .filter(item => item.relevance > 0 && matchesReviewCard(item.card, currentFilters));
        const best = Math.max(0, ...ranked.map(item => item.relevance));
        const isPhrase = normalizeQuestionText(currentFilters.query).includes(' ');
        return ranked
            .filter(item => best === 200 ? item.relevance === 200 : (!isPhrase || best < 80 || item.relevance >= 80))
            .sort((left, right) => right.relevance - left.relevance || left.index - right.index)
            .map(item => item.card);
    }

    function countLabel(count) {
        return count === 1 ? '1 resultado' : String(count) + ' resultados';
    }

    function initialize(doc) {
        const documentRef = doc || root.document;
        if (!documentRef) return null;
        const panel = documentRef.querySelector('[data-review-filters]');
        if (!panel) return null;

        const search = panel.querySelector('[data-review-search]');
        const moduleFilter = panel.querySelector('[data-review-module-filter]');
        const pendingFilter = panel.querySelector('[data-review-pending-filter]');
        const clear = panel.querySelector('[data-review-clear]');
        const count = panel.querySelector('[data-review-count]');
        const lockMessage = panel.querySelector('[data-review-filter-lock]');
        const noResults = documentRef.querySelector('[data-review-no-results]');
        const cards = Array.from(documentRef.querySelectorAll('[data-review-card]'));
        const cardOrder = new Map(cards.map((card, index) => [card, index]));
        const cardLists = Array.from(documentRef.querySelectorAll('.previous-responses-list'));
        const modules = Array.from(documentRef.querySelectorAll('[data-review-module]'));
        // Cards carry the same attribute as theme containers. Never let the
        // container visibility pass overwrite an individual card's filter.
        const themes = Array.from(documentRef.querySelectorAll('[data-review-theme]')).filter(node => !cardOrder.has(node));
        const controls = [search, moduleFilter, pendingFilter, clear].filter(Boolean);
        let activeFilters = {query: '', module: '', pendingOnly: false};

        function hasOpenEditor() {
            return Array.from(documentRef.querySelectorAll('[data-response-editor], [data-m03-response-editor]'))
                .some(editor => !editor.hidden);
        }

        function syncEditorLock() {
            const locked = hasOpenEditor();
            controls.forEach(control => { control.disabled = locked; });
            if (lockMessage) lockMessage.hidden = !locked;
            return locked;
        }

        function restoreFilterControls() {
            if (search) search.value = activeFilters.query;
            if (moduleFilter) moduleFilter.value = activeFilters.module;
            if (pendingFilter) pendingFilter.checked = activeFilters.pendingOnly;
        }

        function orderCardsForQuery(query) {
            if (typeof documentRef.querySelectorAll !== 'function') return;
            const normalizedQuery = normalizeSearchText(query);
            cardLists.forEach(list => {
                const listCards = Array.from(list.querySelectorAll('[data-review-card]'));
                const ordered = listCards.sort((left, right) => {
                    const leftScore = normalizedQuery ? searchRelevance(left, normalizedQuery) : 0;
                    const rightScore = normalizedQuery ? searchRelevance(right, normalizedQuery) : 0;
                    return rightScore - leftScore || (cardOrder.get(left) || 0) - (cardOrder.get(right) || 0);
                });
                if (typeof list.appendChild === 'function') ordered.forEach(card => list.appendChild(card));
            });
        }

        function applyFilters() {
            if (syncEditorLock()) {
                restoreFilterControls();
                return;
            }
            const query = search ? search.value : '';
            const globalSearch = Boolean(normalizeSearchText(query));
            if (globalSearch) {
                if (moduleFilter) moduleFilter.value = '';
                if (pendingFilter) pendingFilter.checked = false;
            }
            activeFilters = {
                query,
                module: globalSearch ? '' : (moduleFilter ? moduleFilter.value : ''),
                pendingOnly: globalSearch ? false : Boolean(pendingFilter && pendingFilter.checked)
            };
            orderCardsForQuery(activeFilters.query);
            const visibleCards = new Set(filterReviewCards(cards, activeFilters));
            const visibleCount = visibleCards.size;
            cards.forEach(card => {
                card.hidden = !visibleCards.has(card);
            });
            themes.forEach(theme => {
                const themeCards = Array.from(theme.querySelectorAll('[data-review-card]'));
                theme.hidden = themeCards.length > 0 && !themeCards.some(card => !card.hidden);
            });
            modules.forEach(module => {
                const moduleCards = Array.from(module.querySelectorAll('[data-review-card]'));
                module.hidden = moduleCards.length > 0 && !moduleCards.some(card => !card.hidden);
            });
            if (count) count.textContent = countLabel(visibleCount);
            if (noResults) noResults.hidden = visibleCount !== 0;
        }

        if (search) search.addEventListener('input', applyFilters);
        if (moduleFilter) moduleFilter.addEventListener('change', applyFilters);
        if (pendingFilter) pendingFilter.addEventListener('change', applyFilters);
        if (clear) clear.addEventListener('click', () => {
            if (syncEditorLock()) return;
            if (search) search.value = '';
            if (moduleFilter) moduleFilter.value = '';
            if (pendingFilter) pendingFilter.checked = false;
            applyFilters();
            if (search) search.focus();
        });

        if (typeof root.MutationObserver === 'function') {
            const editors = Array.from(documentRef.querySelectorAll('[data-response-editor], [data-m03-response-editor]'));
            if (editors.length > 0) {
                const observer = new root.MutationObserver(records => {
                    if (records.some(record => record.type === 'attributes' && record.attributeName === 'hidden')) {
                        syncEditorLock();
                    }
                });
                editors.forEach(editor => observer.observe(editor, {attributes: true, attributeFilter: ['hidden']}));
            }
        }
        syncEditorLock();
        applyFilters();
        return {applyFilters, hasOpenEditor, filterReviewCards};
    }

    const api = {normalizeSearchText, searchRelevance, matchesReviewCard, filterReviewCards, countLabel, initialize};
    root.PatientPreviousResponsesFilters = api;
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root.document) initialize(root.document);
})(typeof globalThis !== 'undefined' ? globalThis : window);
