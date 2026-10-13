(function (window, document) {
    'use strict';

    const focusableSelector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function focusable(modal) {
        return Array.from(modal.querySelectorAll(focusableSelector))
            .filter(element => element.offsetParent !== null && !element.hidden);
    }

    function capture(modal, candidate, fallbackSelector) {
        const active = candidate || document.activeElement;
        if (active && active !== document.body && active !== document.documentElement && !modal.contains(active)) {
            return active;
        }
        const fallback = fallbackSelector ? document.querySelector(fallbackSelector) : null;
        return fallback && typeof fallback.focus === 'function' ? fallback : null;
    }

    function open(modal, options) {
        if (!modal) return null;
        const config = options || {};
        const opener = config.opener || capture(modal, document.activeElement, config.fallbackSelector);
        modal.__patientInstructionsLastFocus = opener;
        modal.classList.add('show');
        modal.style.display = config.display || 'block';
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        const target = modal.querySelector(config.focusSelector || '.btn-close, [data-action="close-modal"], [data-p06-action="close-modal"], [data-p11-action="close-help"]') || focusable(modal)[0];
        if (target && typeof target.focus === 'function') target.focus();
        return opener;
    }

    function close(modal, options) {
        if (!modal) return;
        const config = options || {};
        modal.classList.remove('show');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        const target = config.returnFocus || modal.__patientInstructionsLastFocus || capture(modal, null, config.fallbackSelector);
        modal.__patientInstructionsLastFocus = null;
        if (target && document.contains(target) && typeof target.focus === 'function') target.focus();
    }

    function handleKeydown(event, modal, closeModal) {
        if (!modal || modal.getAttribute('aria-hidden') === 'true') return false;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal();
            return true;
        }
        if (event.key !== 'Tab') return false;
        const controls = focusable(modal);
        if (!controls.length) return false;
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
            return true;
        }
        if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
            return true;
        }
        return false;
    }

    window.PatientInstructionsModal = Object.freeze({ capture, open, close, handleKeydown });
}(window, document));
