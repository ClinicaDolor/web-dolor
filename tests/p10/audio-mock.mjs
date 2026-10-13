import fs from 'node:fs';
const view = fs.readFileSync('app/views/historia-clinica-paciente/procedimientos-control-dolor.php', 'utf8');
for (const marker of ['utterance.lang = spanish?.lang || \'es-MX\'', 'utterance.onstart', 'utterance.onend', 'utterance.onerror', 'window.speechSynthesis.cancel()', 'p10ReadInstructions()', 'p10ReadCurrentQuestion()']) if (!view.includes(marker)) throw new Error(`P10 audio mock: falta ${marker}`);
console.log('P10_AUDIO_MOCK: PASS (es-MX/cancelación/estados contractual; reproducción audible humana NO VERIFICADA)');
