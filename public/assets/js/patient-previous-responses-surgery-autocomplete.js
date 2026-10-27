(function (root) {
    'use strict';

    // Keep this local suggestion list aligned with the approved M04 catalog in entrevista.php.
    // It is terminology assistance only; free text remains supported and no patient data is used.
    const suggestions = Object.freeze([
        {name: 'Apendicectomía', detail: 'Cirugía del apéndice'},
        {name: 'Colecistectomía', detail: 'Cirugía de vesícula'},
        {name: 'Cesárea', detail: ''},
        {name: 'Histerectomía', detail: ''},
        {name: 'Hernioplastia inguinal', detail: ''},
        {name: 'Amigdalectomía', detail: ''},
        {name: 'Artroscopia de rodilla', detail: ''},
        {name: 'Cirugía de cataratas', detail: ''},
        {name: 'Reemplazo de rodilla', detail: ''},
        {name: 'Reemplazo de cadera', detail: ''},
        {name: 'Cirugía de tiroides', detail: ''},
        {name: 'Cirugía de columna', detail: ''},
    ]);

    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('es-MX')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function findSuggestions(query) {
        const normalizedQuery = normalize(query);
        if (Array.from(normalizedQuery).length < 2) return [];
        return suggestions
            .filter(entry => normalize(`${entry.name} ${entry.detail}`).includes(normalizedQuery))
            .slice(0, 8);
    }

    function initialize(doc) {
        const documentRef = doc || root.document;
        const input = documentRef?.querySelector('[data-m04-create-name]');
        const list = documentRef?.querySelector('[data-m04-create-suggestions]');
        if (!input || !list || input.dataset.surgeryAutocompleteInitialized === '1') return false;

        input.dataset.surgeryAutocompleteInitialized = '1';
        if (!list.id) list.id = 'm04-create-surgery-suggestions';
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', list.id);
        input.setAttribute('aria-expanded', 'false');

        let matches = [];
        let activeIndex = -1;
        let suppressInputRender = false;

        const options = () => Array.from(list.querySelectorAll('[role="option"]'));
        const close = () => {
            list.replaceChildren();
            list.hidden = true;
            matches = [];
            activeIndex = -1;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        };
        const setActive = index => {
            const items = options();
            if (!items.length) return;
            activeIndex = Math.max(0, Math.min(index, items.length - 1));
            items.forEach((option, optionIndex) => {
                const selected = optionIndex === activeIndex;
                option.setAttribute('aria-selected', selected ? 'true' : 'false');
                if (selected) {
                    input.setAttribute('aria-activedescendant', option.id);
                    if (typeof option.scrollIntoView === 'function') option.scrollIntoView({block: 'nearest'});
                }
            });
        };
        const render = () => {
            list.replaceChildren();
            matches = findSuggestions(input.value);
            activeIndex = -1;
            input.removeAttribute('aria-activedescendant');
            if (!matches.length) {
                list.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                return;
            }
            matches.forEach((entry, index) => {
                const option = documentRef.createElement('div');
                option.id = `${list.id}-option-${index + 1}`;
                option.className = 'm04-surgery-suggestion';
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.dataset.surgerySuggestionIndex = String(index);
                const name = documentRef.createElement('span');
                name.className = 'm04-surgery-suggestion-name';
                name.textContent = entry.name;
                option.appendChild(name);
                if (entry.detail) {
                    const detail = documentRef.createElement('span');
                    detail.className = 'm04-surgery-suggestion-detail';
                    detail.textContent = `— ${entry.detail}`;
                    option.appendChild(detail);
                }
                list.appendChild(option);
            });
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };
        const select = option => {
            const index = Number(option?.dataset?.surgerySuggestionIndex);
            const entry = Number.isInteger(index) ? matches[index] : null;
            if (!entry) return;
            input.value = entry.name;
            close();
            const EventConstructor = documentRef.defaultView?.Event || root.Event;
            if (typeof EventConstructor === 'function') {
                suppressInputRender = true;
                input.dispatchEvent(new EventConstructor('input', {bubbles: true}));
            }
        };

        input.addEventListener('input', () => {
            if (suppressInputRender) {
                suppressInputRender = false;
                return;
            }
            render();
        });
        input.addEventListener('focus', render);
        input.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                if (list.hidden) render();
                if (list.hidden) return;
                event.preventDefault();
                setActive(activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
            } else if (event.key === 'Enter' && !list.hidden && activeIndex >= 0) {
                event.preventDefault();
                select(options()[activeIndex]);
            } else if (event.key === 'Escape' && !list.hidden) {
                event.preventDefault();
                event.stopPropagation();
                close();
            } else if (event.key === 'Tab') {
                close();
            }
        });
        list.addEventListener('pointerdown', event => {
            if (event.target?.closest?.('[role="option"]')) event.preventDefault();
        });
        list.addEventListener('click', event => {
            const option = event.target?.closest?.('[role="option"]');
            if (option && list.contains(option)) select(option);
        });
        documentRef.addEventListener('pointerdown', event => {
            if (event.target !== input && !list.contains(event.target)) close();
        });
        input.closest('dialog')?.addEventListener('close', close);
        return true;
    }

    const api = {suggestions, normalize, findSuggestions, initialize};
    root.PatientPreviousResponsesSurgeryAutocomplete = api;
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root.document) initialize(root.document);
})(typeof globalThis !== 'undefined' ? globalThis : window);
