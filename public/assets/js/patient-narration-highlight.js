(function (window, document) {
    'use strict';

    if (window.PatientNarrationHighlight) return;

    const speechApi = 'speechSynthesis' in window ? window.speechSynthesis : null;
    let activeSession = null;
    let installed = false;

    function normalizeText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function instructionTargets() {
        return Array.from(document.querySelectorAll('[data-narration-instructions]'));
    }

    function findTarget(text) {
        const normalized = normalizeText(text);
        if (!normalized) return null;
        return instructionTargets().find(target => normalizeText(target.textContent) === normalized) || null;
    }

    function unwrapTokens(target) {
        target.querySelectorAll('.patient-narration-token').forEach(token => {
            token.replaceWith(document.createTextNode(token.textContent));
        });
        target.normalize();
        delete target.dataset.narrationSpeechText;
        target.removeAttribute('data-narration-state');
        target.removeAttribute('data-narration-sync');
        target.removeAttribute('data-narration-fallback');
    }

    function prepareTarget(target) {
        const speechText = normalizeText(target.textContent);
        if (!speechText) return { speechText: '', tokens: [] };

        if (target.dataset.narrationSpeechText !== speechText || !target.querySelector('.patient-narration-token')) {
            unwrapTokens(target);
            const walker = document.createTreeWalker(target, NodeFilter.SHOW_TEXT);
            const textNodes = [];
            let currentNode;
            while ((currentNode = walker.nextNode())) textNodes.push(currentNode);

            let tokenIndex = 0;
            textNodes.forEach(textNode => {
                const fragment = document.createDocumentFragment();
                const value = textNode.nodeValue || '';
                const matcher = /\S+/g;
                let lastIndex = 0;
                let match;
                while ((match = matcher.exec(value))) {
                    if (match.index > lastIndex) fragment.appendChild(document.createTextNode(value.slice(lastIndex, match.index)));
                    const token = document.createElement('span');
                    token.className = 'patient-narration-token';
                    token.dataset.narrationToken = String(tokenIndex++);
                    token.textContent = match[0];
                    fragment.appendChild(token);
                    lastIndex = match.index + match[0].length;
                }
                if (lastIndex < value.length) fragment.appendChild(document.createTextNode(value.slice(lastIndex)));
                textNode.parentNode.replaceChild(fragment, textNode);
            });
            target.dataset.narrationSpeechText = speechText;
        }

        const tokens = Array.from(target.querySelectorAll('.patient-narration-token'));
        let searchStart = 0;
        tokens.forEach(token => {
            const value = token.textContent || '';
            const start = speechText.indexOf(value, searchStart);
            const resolvedStart = start === -1 ? searchStart : start;
            token.dataset.narrationStart = String(resolvedStart);
            token.dataset.narrationEnd = String(resolvedStart + value.length);
            searchStart = resolvedStart + value.length;
            token.classList.remove('is-reading', 'is-read');
        });
        target.dataset.narrationState = 'pending';
        target.dataset.narrationSync = 'waiting-boundary';
        return { speechText, tokens };
    }

    function resetTarget(target) {
        if (!target) return;
        target.querySelectorAll('.patient-narration-token').forEach(token => token.classList.remove('is-reading', 'is-read'));
        target.setAttribute('data-narration-state', 'pending');
        target.removeAttribute('data-narration-fallback');
        target.setAttribute('data-narration-sync', 'idle');
    }

    function markToken(target, index) {
        const tokens = Array.from(target.querySelectorAll('.patient-narration-token'));
        if (!tokens.length) return;
        const safeIndex = Math.max(0, Math.min(index, tokens.length - 1));
        tokens.forEach((token, tokenIndex) => {
            token.classList.toggle('is-read', tokenIndex < safeIndex);
            token.classList.toggle('is-reading', tokenIndex === safeIndex);
        });
        target.setAttribute('data-narration-state', 'reading');
    }

    function markBoundary(target, charIndex) {
        const tokens = Array.from(target.querySelectorAll('.patient-narration-token'));
        if (!tokens.length || !Number.isFinite(charIndex)) return false;
        let selected = tokens.findIndex(token => Number(token.dataset.narrationStart) <= charIndex && charIndex < Number(token.dataset.narrationEnd));
        if (selected < 0) selected = tokens.reduce((last, token, index) => Number(token.dataset.narrationStart) <= charIndex ? index : last, 0);
        markToken(target, selected);
        target.setAttribute('data-narration-sync', 'word-boundary');
        return true;
    }

    function wrapEvent(utterance, name, callback) {
        const original = utterance[name];
        utterance[name] = function (event) {
            callback(event);
            if (typeof original === 'function') original.call(this, event);
        };
    }

    function attach(utterance, target) {
        const prepared = prepareTarget(target);
        if (!prepared.speechText || !prepared.tokens.length) return;
        if (activeSession) resetTarget(activeSession.target);

        const session = { utterance, target, sawBoundary: false };
        activeSession = session;
        wrapEvent(utterance, 'onstart', () => {
            if (activeSession !== session) return;
            markToken(target, 0);
            target.setAttribute('data-narration-sync', 'waiting-boundary');
        });
        wrapEvent(utterance, 'onboundary', event => {
            if (activeSession !== session) return;
            session.sawBoundary = true;
            markBoundary(target, Number(event && event.charIndex));
        });
        wrapEvent(utterance, 'onend', () => {
            if (activeSession !== session) return;
            target.querySelectorAll('.patient-narration-token').forEach(token => {
                token.classList.remove('is-reading');
                token.classList.add('is-read');
            });
            target.setAttribute('data-narration-state', 'complete');
            target.setAttribute('data-narration-sync', session.sawBoundary ? 'word-boundary' : 'unsupported');
            if (!session.sawBoundary) target.setAttribute('data-narration-fallback', 'sin-eventos-boundary');
            activeSession = null;
        });
        wrapEvent(utterance, 'onerror', () => {
            if (activeSession !== session) return;
            resetTarget(target);
            target.setAttribute('data-narration-state', 'error');
            activeSession = null;
        });
    }

    function install() {
        if (installed || !speechApi || typeof speechApi.speak !== 'function') return;
        installed = true;
        const nativeSpeak = speechApi.speak.bind(speechApi);
        const nativeCancel = typeof speechApi.cancel === 'function' ? speechApi.cancel.bind(speechApi) : null;
        speechApi.speak = function (utterance) {
            const target = utterance && findTarget(utterance.text);
            if (target) attach(utterance, target);
            return nativeSpeak(utterance);
        };
        if (nativeCancel) {
            speechApi.cancel = function () {
                if (activeSession) {
                    resetTarget(activeSession.target);
                    activeSession = null;
                }
                return nativeCancel();
            };
        }
    }

    function reset() {
        if (activeSession) resetTarget(activeSession.target);
        activeSession = null;
        instructionTargets().forEach(resetTarget);
    }

    window.PatientNarrationHighlight = Object.freeze({
        findTarget,
        prepareTarget,
        reset,
        install
    });
    install();
}(window, document));
