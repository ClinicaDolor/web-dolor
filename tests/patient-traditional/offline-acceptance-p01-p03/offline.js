'use strict';

const sections = [...document.querySelectorAll('.screen')];
const showSection = (id) => {
    sections.forEach((section) => {
        section.hidden = section.id !== id;
    });
};

const focusHeading = (id) => {
    const heading = document.querySelector(`#${id} h1`);
    if (!heading) return;
    heading.tabIndex = -1;
    heading.focus({ preventScroll: true });
};

document.querySelectorAll('[data-screen]').forEach((control) => {
    control.addEventListener('click', () => {
        const id = control.dataset.screen;
        if (!['p01', 'p02', 'p03'].includes(id)) return;
        showSection(id);
        document.querySelectorAll('[data-screen]').forEach((item) => {
            if (item.closest('.offline-nav')) {
                item.setAttribute('aria-current', item.dataset.screen === id ? 'page' : 'false');
            }
        });
        focusHeading(id);
    });
});

showSection('p01');
document.querySelector('.offline-nav [data-screen="p01"]').setAttribute('aria-current', 'page');

const validateOfflinePin = () => {
    const input = document.getElementById('offline-pin');
    const message = document.getElementById('p01-message');
    const blank = input.value.trim() === '';
    input.setAttribute('aria-invalid', String(blank));
    message.hidden = !blank;
    message.textContent = blank
        ? 'Ingresa tu PIN de acceso.'
        : 'Validación local completada; la autenticación real no se ejecuta offline.';
    message.classList.toggle('message-ok', !blank);
};

document.getElementById('p01-submit').addEventListener('click', validateOfflinePin);
document.getElementById('offline-pin').addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        validateOfflinePin();
    }
});
