(function (global) {
    'use strict';
    const startButton = document.getElementById('start-dictation');
    const stopButton = document.getElementById('stop-dictation');
    const clearButton = document.getElementById('clear-transcript');
    const input = document.getElementById('transcript');
    const message = document.getElementById('diagnostic-message');
    const connection = document.getElementById('connection-state');
    const microphone = document.getElementById('microphone-state');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    if (!startButton || !stopButton || !clearButton || !input || typeof global.PatientInterviewLocalDictation !== 'function') return;

    const client = new global.PatientInterviewLocalDictation({
        mode: 'diagnostic',
        ticketUrl: '/historia-clinica/diagnostico-voz/ticket',
        workletUrl: '/assets/js/patient-interview-audio-worklet.js',
        getCsrf: () => csrf,
        getTarget: () => 'LOCAL_VOICE_DIAGNOSTIC',
        setStatus: (kind, text) => {
            message.textContent = text;
            if (kind === 'AUTHORIZING') connection.textContent = 'Solicitando autorización…';
            if (kind === 'CONNECTING') connection.textContent = 'Abriendo canal WSS seguro…';
            if (kind === 'LISTENING' || kind === 'RECOGNIZING') { connection.textContent = 'Conectada'; microphone.textContent = 'Escuchando'; }
            if (kind === 'TRANSCRIPT_READY' || kind === 'IDLE' || kind === 'ERROR' || kind === 'UNAVAILABLE') {
                if (kind === 'ERROR' || kind === 'UNAVAILABLE') connection.textContent = 'No conectada';
                if (microphone.textContent === 'Escuchando') microphone.textContent = 'Detenido';
            }
        },
    });

    startButton.addEventListener('click', async () => {
        startButton.disabled = true;
        message.textContent = 'Preparando diagnóstico local…';
        const started = await client.start(startButton, input);
        startButton.disabled = false;
        if (started) { startButton.hidden = true; stopButton.disabled = false; }
        else { stopButton.disabled = true; }
    });
    stopButton.addEventListener('click', async () => {
        stopButton.disabled = true;
        await client.stop(false);
        startButton.hidden = false;
        microphone.textContent = 'Detenido';
    });
    clearButton.addEventListener('click', () => { input.value = ''; input.dispatchEvent(new Event('input', {bubbles: true})); });
})(window);
