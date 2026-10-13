// Utilidades compartidas de lectura por voz.
// La voz se inicia únicamente por interacción del usuario o por el comportamiento
// automático ya configurado por cada módulo; nunca se envían datos a servicios externos.
let voicesLoaded = false;
let currentUtterance = null;
let currentSectionId = null;
let shouldReadNext = false;

const speechApi = 'speechSynthesis' in window ? window.speechSynthesis : null;

function preloadVoices() {
  if (!speechApi) return [];
  const voices = speechApi.getVoices();
  voicesLoaded = voices.length > 0;
  return voices;
}

preloadVoices();
if (speechApi) speechApi.onvoiceschanged = preloadVoices;

function spanishVoice() {
  const voices = preloadVoices();
  return voices.find(voice => /^es-MX/i.test(voice.lang))
    || voices.find(voice => /^es(-|_)/i.test(voice.lang))
    || null;
}

function restoreSectionText(sectionId) {
  const section = document.getElementById(sectionId);
  const textoElement = section ? section.querySelector('.texto') : null;
  if (textoElement && textoElement.dataset.original) {
    textoElement.textContent = textoElement.dataset.original;
  }
}

function detenerLectura() {
  if (speechApi) speechApi.cancel();
  if (currentSectionId) restoreSectionText(currentSectionId);
  currentUtterance = null;
  currentSectionId = null;
  shouldReadNext = false;
}

function leerTextoSeccion(sectionId) {
  if (!speechApi || typeof SpeechSynthesisUtterance === 'undefined') {
    console.warn('La síntesis de voz no está disponible en este navegador.');
    return false;
  }

  if (currentSectionId === sectionId && speechApi.speaking) {
    detenerLectura();
    return false;
  }

  detenerLectura();

  const section = document.getElementById(sectionId);
  if (!section) {
    console.warn('Sección no encontrada:', sectionId);
    return false;
  }

  const textoElement = section.querySelector('.texto');
  if (!textoElement) {
    console.warn('Elemento de texto no encontrado en la sección:', sectionId);
    return false;
  }

  const originalText = textoElement.textContent.trim();
  if (!originalText) {
    console.warn('No hay texto para leer en la sección:', sectionId);
    return false;
  }

  textoElement.dataset.original = originalText;
  const utterance = new SpeechSynthesisUtterance(originalText);
  const voice = spanishVoice();
  utterance.lang = voice ? voice.lang : 'es-MX';
  if (voice) utterance.voice = voice;
  utterance.rate = .88;
  utterance.pitch = 1;

  currentSectionId = sectionId;
  currentUtterance = utterance;

  utterance.onend = function () {
    restoreSectionText(sectionId);
    currentSectionId = null;
    currentUtterance = null;
    if (shouldReadNext) {
      shouldReadNext = false;
      leerSiguienteSeccion();
    }
  };

  utterance.onerror = function () {
    restoreSectionText(sectionId);
    currentSectionId = null;
    currentUtterance = null;
  };

  speechApi.speak(utterance);
  return true;
}

function leerSiguienteSeccion() {
  const nextSection = document.querySelector('.sectionQuestion:not([data-autoplay="true"])');
  if (!nextSection) return;
  leerTextoSeccion(nextSection.id);
  nextSection.setAttribute('data-autoplay', 'true');
}

function bindVoiceButtons() {
  document.querySelectorAll('.btnLeer').forEach(button => {
    if (button.dataset.voiceBound === 'true') return;
    button.dataset.voiceBound = 'true';
    button.addEventListener('click', function () {
      leerTextoSeccion(this.getAttribute('data-target'));
    });
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bindVoiceButtons, { once: true });
} else {
  bindVoiceButtons();
}

const observerOptions = {
  root: null,
  rootMargin: '0px',
  threshold: 0.5
};

if ('IntersectionObserver' in window) {
  const observerCallback = entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const section = entry.target;
      if (section.getAttribute('data-autoplay') !== 'false') return;

      if (!currentSectionId) {
        leerTextoSeccion(section.id);
        section.setAttribute('data-autoplay', 'true');
      } else {
        shouldReadNext = true;
      }
    });
  };

  const observer = new IntersectionObserver(observerCallback, observerOptions);
  document.querySelectorAll('.sectionQuestion').forEach(section => observer.observe(section));
}

function readQuestion() {
  const firstSection = document.querySelector('.sectionQuestion');
  if (firstSection) leerTextoSeccion(firstSection.id);
}
