(function (window) {
    'use strict';

    const messages = Object.freeze({
        idle: 'Para escuchar las instrucciones, presiona este botón.',
        playing: 'Reproduciendo instrucciones. Puedes detenerlas o repetirlas.',
        stopped: 'Audio detenido. Presiona Escuchar para repetir.'
    });

    function resolveNode(nodeOrId) {
        if (!nodeOrId) return null;
        if (typeof nodeOrId === 'string') return document.getElementById(nodeOrId);
        return nodeOrId;
    }

    function set(nodeOrId, state) {
        const node = resolveNode(nodeOrId);
        if (!node || !Object.prototype.hasOwnProperty.call(messages, state)) return false;
        node.textContent = messages[state];
        node.dataset.audioStatus = state;
        return true;
    }

    function resolveControls(groupOrNode) {
        if (!groupOrNode) return null;
        if (typeof groupOrNode !== 'string') {
            if (groupOrNode.matches && groupOrNode.matches('[data-audio-controls-group]')) return groupOrNode;
            return groupOrNode.closest ? groupOrNode.closest('[data-audio-controls-group]') : null;
        }
        const groups = document.querySelectorAll('[data-audio-controls-group]');
        return Array.from(groups).find(group => group.getAttribute('data-audio-controls-group') === groupOrNode) || null;
    }

    function setControls(groupOrNode, state) {
        const root = resolveControls(groupOrNode);
        if (!root) return false;
        const listen = root.querySelector('[data-audio-control="listen"]');
        const stop = root.querySelector('[data-audio-control="stop"]');
        const unavailable = state === 'unavailable';
        const playing = state === 'playing';
        if (listen) {
            listen.disabled = playing || unavailable;
            listen.setAttribute('aria-disabled', listen.disabled ? 'true' : 'false');
        }
        if (stop) {
            stop.disabled = !playing || unavailable;
            stop.setAttribute('aria-disabled', stop.disabled ? 'true' : 'false');
        }
        root.dataset.audioState = state;
        return true;
    }

    function initializeControls() {
        document.querySelectorAll('[data-audio-controls-group]').forEach(root => {
            setControls(root, root.dataset.audioState || 'idle');
        });
    }

    window.PatientAudioStatus = Object.freeze({
        messages,
        set,
        idle: nodeOrId => set(nodeOrId, 'idle'),
        playing: nodeOrId => set(nodeOrId, 'playing'),
        stopped: nodeOrId => set(nodeOrId, 'stopped')
    });

    window.PatientAudioControls = Object.freeze({
        setState: setControls,
        idle: groupOrNode => setControls(groupOrNode, 'idle'),
        playing: groupOrNode => setControls(groupOrNode, 'playing'),
        stopped: groupOrNode => setControls(groupOrNode, 'stopped'),
        ended: groupOrNode => setControls(groupOrNode, 'ended'),
        unavailable: groupOrNode => setControls(groupOrNode, 'unavailable')
    });

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeControls, { once: true });
    else initializeControls();
})(window);
