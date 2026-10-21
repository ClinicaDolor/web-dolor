const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const voiceSource = fs.readFileSync(path.resolve(__dirname, '../../public/assets/js/patient-interview-voice.js'), 'utf8');
const viewSource = fs.readFileSync(path.resolve(__dirname, '../../app/views/paciente/entrevista.php'), 'utf8');

class MockUtterance {
    constructor(text) { this.text = text; this.lang = ''; this.voice = null; this.rate = 1; this.pitch = 0; this.volume = 1; }
}

class MockSpeechSynthesis {
    constructor(voices = []) { this.voices = voices; this.calls = []; this.cancelCount = 0; this.listeners = new Map(); }
    getVoices() { return this.voices; }
    addEventListener(type, listener) {
        const listeners = this.listeners.get(type) || [];
        listeners.push(listener);
        this.listeners.set(type, listeners);
    }
    emit(type) { (this.listeners.get(type) || []).forEach(listener => listener({type})); }
    cancel() { this.cancelCount += 1; }
    speak(utterance) { this.calls.push(utterance); }
}

function makeController(speech, onVoicesChanged = () => {}, preferredVoice = null) {
    const context = {window: {SpeechSynthesisUtterance: MockUtterance}};
    vm.runInNewContext(voiceSource, context);
    return new context.window.PatientInterviewVoiceController({speechApi: speech, utteranceConstructor: MockUtterance, onVoicesChanged, preferredVoice});
}

async function main() {
    const inventoryUpdates = [];
    const speech = new MockSpeechSynthesis();
    const controller = makeController(speech, inventory => inventoryUpdates.push(inventory));
    assert.equal(controller.selectVoice(), null, 'sin voces, el selector no debe inventar una voz');
    assert.equal(controller.voiceInventory().length, 0, 'inventario inicial vacío permitido');
    assert.equal(speech.listeners.get('voiceschanged')?.length, 1, 'se registra exactamente un listener voiceschanged');
    assert.equal(controller.registerVoicesChanged(), false, 'el registro repetido no añade otro listener');
    assert.equal(speech.listeners.get('voiceschanged').length, 1, 'no hay listeners duplicados');

    const esMx = {name: 'Microsoft Dalia - Spanish (Mexico)', lang: 'es-MX', voiceURI: 'dalia-esmx', localService: true, default: false};
    const esEsRemote = {name: 'Spanish (Spain)', lang: 'es-ES', voiceURI: 'es-es-remote', localService: false, default: true};
    const esMxUnknown = {name: 'Voz México', lang: 'es_MX', voiceURI: 'esmx-unknown', default: false};
    const english = {name: 'English', lang: 'en-US', voiceURI: 'en-us', localService: true, default: false};
    speech.voices = [esEsRemote, english, esMx, esMxUnknown];
    speech.emit('voiceschanged');
    assert.equal(inventoryUpdates.length, 2, 'el evento actualiza el inventario sin volver a suscribirse');
    const inventory = controller.voiceInventory();
    assert.equal(inventory.length, 4, 'el inventario contiene solo voces realmente expuestas por getVoices');
    assert.equal(inventory.find(entry => entry.name === esMx.name).localService, true, 'conserva localService cuando está informado');
    assert.equal(inventory.find(entry => entry.name === esEsRemote.name).localService, false, 'distingue una voz no local');
    assert.equal(inventory.find(entry => entry.name === esMxUnknown.name).localService, null, 'no inventa el indicador local ausente');
    assert.equal(controller.voiceForKey(inventory.find(entry => entry.voice === esMx).key), esMx, 'resuelve de vuelta el objeto SpeechSynthesisVoice correcto');
    assert.equal(controller.selectVoice(), esMx, 'la voz predeterminada actual mantiene primero la coincidencia es-MX, sin mutarla');

    const appliedIdentity = {voiceURI: esEsRemote.voiceURI, lang: esEsRemote.lang, name: esEsRemote.name};
    assert.equal(controller.setPreferredVoice(appliedIdentity).available, true, 'la aplicación explícita resuelve identidad técnica exacta');
    const clinicalQuestion = {type: 'QUESTION', stable_id: 'M03:synthetic-q1', text: 'Pregunta clínica sintética uno.'};
    assert.equal(controller.speakEvent(clinicalQuestion, {force: true}), true, 'la pregunta activa usa el controlador compartido');
    assert.equal(speech.calls.at(-1).voice, esEsRemote, 'la voz aplicada se usa en la narración clínica');
    assert.equal(controller.getVoiceDiagnostic().effective.voiceURI, esEsRemote.voiceURI, 'el diagnóstico informa la voz efectiva');
    assert.equal(controller.getVoiceDiagnostic().context, 'clinical', 'el diagnóstico distingue la pregunta real de una muestra');
    assert.equal(JSON.stringify(controller.getVoiceDiagnostic()).includes(clinicalQuestion.text), false, 'el diagnóstico no contiene texto clínico');
    const nextQuestion = {type: 'QUESTION', stable_id: 'M03:synthetic-q2', text: 'Pregunta clínica sintética dos.'};
    controller.speakEvent(nextQuestion, {force: true});
    assert.equal(speech.calls.at(-1).voice, esEsRemote, 'las preguntas consecutivas conservan la voz aplicada');
    controller.repeat(true);
    assert.equal(speech.calls.at(-1).voice, esEsRemote, 'Repetir usa la voz aplicada');

    const restoredController = makeController(speech, () => {}, appliedIdentity);
    assert.equal(restoredController.selectVoice(), esEsRemote, 'la preferencia se restaura al reconstruir el controlador tras F5');
    speech.voices = [english, esMx];
    controller.refreshVoices();
    assert.equal(controller.getVoiceSelection().available, false, 'la voz ausente se detecta');
    assert.equal(controller.selectVoice(), esMx, 'si la voz elegida falta, selecciona fallback es-MX');
    controller.speakEvent({type: 'QUESTION', stable_id: 'M03:synthetic-q3', text: 'Pregunta clínica sintética tres.'}, {force: true});
    assert.equal(speech.calls.at(-1).voice, esMx, 'la locución usa el fallback disponible');
    assert.equal(controller.getVoiceDiagnostic().fallbackReason, 'PREFERRED_VOICE_UNAVAILABLE', 'diagnóstico identifica fallback temporal');
    speech.voices = [esEsRemote, english, esMx, esMxUnknown];
    speech.emit('voiceschanged');
    assert.equal(controller.getVoiceSelection().available, true, 'voiceschanged recupera la voz cuando vuelve a estar disponible');
    assert.equal(controller.selectVoice(), esEsRemote, 'la narración vuelve a la voz aplicada');
    controller.setPreferredVoice(null);
    assert.equal(controller.selectVoice(), esMx, 'Usar voz automática restaura el fallback automático es-MX');

    const baselineEvent = {type: 'QUESTION', stable_id: 'M02:synthetic-question', text: 'Pregunta activa ficticia.'};
    controller.lastEvent = baselineEvent;
    let starts = 0;
    let ends = 0;
    let cancels = 0;
    const sample = '¿Actualmente tomas algún medicamento para aliviar el dolor?';
    assert.equal(controller.preview(sample, esMx, {onStart: () => starts++, onEnd: () => ends++, onCancel: () => cancels++}), true, 'la muestra manual se encola');
    const first = speech.calls.at(-1);
    assert.equal(first.text, sample, 'la muestra envía únicamente el texto ficticio');
    assert.equal(first.voice, esMx, 'la muestra usa la voz elegida, no la predeterminada del asistente');
    assert.equal(first.lang, 'es-MX', 'la utterance conserva idioma mexicano');
    assert.equal(first.rate, .95, 'velocidad inicial de muestra .95');
    assert.equal(first.pitch, 1, 'tono inicial de muestra 1.0');
    assert.equal(first.volume, 1, 'volumen inicial de muestra 1.0');
    assert.equal(controller.getVoiceDiagnostic().context, 'preview', 'el diagnóstico marca una comparación como muestra');
    first.onstart();
    first.onend();
    assert.equal(starts, 1, 'se expone onstart');
    assert.equal(ends, 1, 'se expone onend');
    assert.equal(controller.lastEvent, baselineEvent, 'la muestra no reemplaza la pregunta activa para Repetir');

    assert.equal(controller.preview('¿Has presentado alguna reacción adversa?', esEsRemote, {onCancel: () => cancels++}), true, 'las voces expuestas se pueden seleccionar para prueba manual');
    const second = speech.calls.at(-1);
    assert.equal(speech.cancelCount >= 2, true, 'antes de cada muestra se cancela la reproducción anterior');
    assert.equal(second.voice, esEsRemote, 'el preview identifica la voz realmente seleccionada');
    assert.equal(cancels, 0, 'la muestra completada no se reporta como cancelada');
    assert.equal(controller.preview('¿Desde hace cuánto tiempo utilizas este medicamento?', esMx, {onCancel: () => cancels++}), true, 'se puede sustituir una muestra que sigue activa');
    assert.equal(cancels, 1, 'reemplazar audio activo comunica cancelación de la muestra previa');
    controller.cancel();
    assert.equal(cancels, 2, 'cancelar una muestra activa emite el callback cancel');

    const fallbackSpeech = new MockSpeechSynthesis([english, esEsRemote]);
    const fallbackController = makeController(fallbackSpeech);
    assert.equal(fallbackController.selectVoice(), esEsRemote, 'fallback elige es-* antes que la primera voz no española');
    fallbackSpeech.voices = [english];
    assert.equal(fallbackController.selectVoice(), english, 'se conserva el fallback histórico cuando no hay ninguna voz española');

    const propertySpeech = {voices: [], onvoiceschanged: null, getVoices() { return this.voices; }};
    const propertyController = makeController(propertySpeech);
    const installedHandler = propertySpeech.onvoiceschanged;
    assert.equal(typeof installedHandler, 'function', 'fallback compatible con onvoiceschanged');
    propertyController.registerVoicesChanged();
    assert.equal(propertySpeech.onvoiceschanged, installedHandler, 'el fallback no encadena ni duplica handlers de forma repetida');
    let previousHandlerCalls = 0;
    const propertySpeechWithExistingHandler = {voices: [], onvoiceschanged() { previousHandlerCalls++; }, getVoices() { return this.voices; }};
    const propertyFallbackController = makeController(propertySpeechWithExistingHandler);
    propertySpeechWithExistingHandler.onvoiceschanged({type: 'voiceschanged'});
    assert.equal(previousHandlerCalls, 1, 'el fallback preserva el handler previo');
    assert.equal(propertyFallbackController.voiceInventory().length, 0, 'el handler previo no impide refrescar el inventario');

    let previewErrors = 0;
    assert.equal(controller.preview('¿Desde hace cuánto tiempo utilizas este medicamento?', esMx, {onError: () => previewErrors++}), true, 'una frase ficticia puede iniciarse con voz elegida');
    speech.calls.at(-1).onerror({error: 'synthesis-failed'});
    assert.equal(previewErrors, 1, 'onerror de muestra se comunica sin exponer el texto a logs');

    assert.match(viewSource, /<div class="voice-selector"[^>]*data-voice-selector hidden>/, 'la infraestructura de selección permanece preservada pero fuera del panel visible');
    assert.doesNotMatch(viewSource, /data-voice-selector-toggle/, 'el panel no debe mostrar un acceso Elegir voz');
    assert.match(viewSource, /data-voice-compare multiple/, 'el comparador permite selección múltiple');
    assert.match(viewSource, /¿Actualmente tomas algún medicamento para aliviar el dolor\?/);
    assert.match(viewSource, /¿Desde hace cuánto tiempo utilizas este medicamento\?/);
    assert.match(viewSource, /¿Has presentado alguna reacción adversa\?/);
    assert.match(viewSource, /No informa[^.]*privacidad por el nombre|no informa la disponibilidad local/i, 'la interfaz evita inferir privacidad por nombre');
    assert.match(viewSource, /data-voice-preview/);
    assert.match(viewSource, /data-voice-compare-start/);
    assert.match(viewSource, /data-voice-apply/);
    assert.match(viewSource, /data-voice-auto/);
    assert.match(viewSource, /clinica-dolor-interview-tts-voice-v1/);
    assert.match(viewSource, /localStorage/, 'la preferencia se guarda localmente, sin escritura MySQL');
    assert.match(viewSource, /preferredVoice: readVoicePreference\(\)/, 'la identidad almacenada se rehidrata al cargar la entrevista');
    assert.match(viewSource, /solicitada=.*efectiva=.*rate=.*pitch=.*volume=.*fallback=/s, 'el diagnóstico local expone voz solicitada/efectiva y parámetros sin texto clínico');
    assert.match(viewSource, /slice\(3\)/, 'la selección queda limitada a tres voces');
    assert.match(viewSource, /onstart:.*reproducción iniciada/s, 'la interfaz distingue el evento de inicio de la calidad audible');
    assert.match(viewSource, /voiceController\.activateAutomatically\(question\)/, 'la guía original continúa con su misma activación y pregunta activa');
    assert.match(voiceSource, /const voice = this\.selectVoice\(\)/, 'narración de pregunta y preferencia aplicada usan el mismo resolver');
    assert.match(voiceSource, /this\.recordVoiceDiagnostic\(voice/, 'el diagnóstico técnico local no necesita texto de pregunta');
    console.log('VOICE_SELECTOR_TEST: PASS');
    console.log('VOICESCHANGED_SINGLE_LISTENER: PASS');
    console.log('EMPTY_INVENTORY_AND_LANGUAGE_FALLBACK: PASS');
    console.log('VOICE_METADATA_AND_LOCALITY: PASS');
    console.log('PREVIEW_RATE_PITCH_VOLUME: PASS');
    console.log('PREVIEW_CANCEL_AND_EVENT_CALLBACKS: PASS');
    console.log('APPLIED_VOICE_QUESTION_NEXT_AND_REPEAT: PASS');
    console.log('VOICE_IDENTITY_RELOAD_AND_FALLBACK: PASS');
    console.log('VOICESCHANGED_RECOVERY_AND_AUTO_RESET: PASS');
}

main().catch(error => { console.error(`VOICE_SELECTOR_TEST: FAIL - ${error.message}`); process.exitCode = 1; });
