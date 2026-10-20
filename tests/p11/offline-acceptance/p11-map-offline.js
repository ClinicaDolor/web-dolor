/*
 * Offline adapter for the real P11 map interaction.
 * Core pointer coordinate, color selection, scaling and drawing logic is
 * reproduced from app/views/historia-clinica-paciente/evaluacion-dolor.php
 * (initMap, lines 127-147 in the source tree). No fetch, storage or POST is
 * used here; map marks are intentionally discarded on page close.
 */
(function () {
    'use strict';

    const message = document.getElementById('p11-message');
    const sections = { frente: 'p11-front-panel', espalda: 'p11-back-panel', cuestionario: 'p11-question-panel' };

    function setMessage(text, type) {
        message.textContent = text || '';
        message.className = 'p11-message' + (type ? ' is-' + type : '');
    }

    function initMap(canvas) {
        if (!canvas || canvas.dataset.p11Bound === 'true') return;
        canvas.dataset.p11Bound = 'true';
        const orientation = canvas.dataset.p11Orientation;
        const ctx = canvas.getContext('2d');
        const image = new Image();
        let loaded = false;
        let drawing = false;
        let last = null;

        image.onload = function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
            loaded = true;
        };
        image.onerror = function () {
            setMessage('No se pudo cargar el recurso gráfico local.', 'error');
        };
        image.src = canvas.dataset.p11Image;

        function point(event) {
            const rect = canvas.getBoundingClientRect();
            return {
                x: Math.max(0, Math.min(canvas.width, (event.clientX - rect.left) * canvas.width / rect.width)),
                y: Math.max(0, Math.min(canvas.height, (event.clientY - rect.top) * canvas.height / rect.height))
            };
        }

        function selectedColor() {
            const name = orientation === 'frente' ? 'colorFrente' : 'colorEspalda';
            const selected = document.querySelector('input[name="' + name + '"]:checked');
            return selected ? selected.value : '';
        }

        function draw(pointValue) {
            const color = selectedColor();
            if (!color || !loaded) return;
            ctx.strokeStyle = color;
            ctx.lineWidth = Math.max(18, canvas.width / 48);
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.beginPath();
            if (last) ctx.moveTo(last.x, last.y);
            else ctx.moveTo(pointValue.x, pointValue.y);
            ctx.lineTo(pointValue.x + (last ? 0 : .1), pointValue.y + (last ? 0 : .1));
            ctx.stroke();
            last = pointValue;
        }

        canvas.addEventListener('pointerdown', function (event) {
            if (!selectedColor() || !loaded) {
                setMessage('Selecciona un color antes de marcar.', 'error');
                return;
            }
            event.preventDefault();
            drawing = true;
            last = null;
            if (canvas.setPointerCapture) canvas.setPointerCapture(event.pointerId);
            draw(point(event));
        });
        canvas.addEventListener('pointermove', function (event) {
            if (!drawing) return;
            event.preventDefault();
            draw(point(event));
        });
        const finish = function (event) {
            if (!drawing) return;
            event.preventDefault();
            drawing = false;
            last = null;
            setMessage('Marca visible en esta prueba offline; no se guardó.', 'success');
        };
        canvas.addEventListener('pointerup', finish);
        canvas.addEventListener('pointercancel', finish);
        canvas.addEventListener('pointerleave', function (event) {
            if (drawing && event.buttons === 0) finish(event);
        });
    }

    function showSection(section) {
        Object.keys(sections).forEach(function (key) {
            const panel = document.getElementById(sections[key]);
            if (panel) panel.hidden = key !== section;
        });
        if (section === 'frente') initMap(document.getElementById('painFrente'));
        if (section === 'espalda') initMap(document.getElementById('painEspalda'));
    }

    document.addEventListener('click', function (event) {
        const nav = event.target.closest('[data-p11-nav]');
        if (nav) {
            event.preventDefault();
            showSection(nav.dataset.p11Nav);
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.name !== 'p11Dolor') return;
        const dependent = document.querySelector('.p11-dependent-fields');
        if (dependent) dependent.hidden = event.target.value !== 'Si';
    });

    initMap(document.getElementById('painFrente'));
})();
