# PHASE-04B-03 — Narración sincronizada y resaltado amarillo

Fecha de ejecución: 2026-10-12
Proyecto: Clínica del Dolor
Rama: Silvino
HEAD de referencia: 1b08d9b

## Resultado

~~~text
PHASE_04B_03_AUDIO_HIGHLIGHT:
STATUS: PARTIAL
MODULES_WITH_AUDIO: P04–P11; P01 no tiene narración clínica y P02/P03 no tienen modal de instrucciones marcado para esta capa.
MODULES_WITH_HIGHLIGHT: P04–P11 (8/8 textos de instrucciones con marca y script compartido).
SHARED_AUDIO_CONTROLLER: PASS estático y PASS en fixture de navegador; public/assets/js/patient-narration-highlight.js intercepta speak/cancel sin reemplazar las funciones clínicas existentes.
WORD_BOUNDARY_SYNC: PASS en fixture; onboundary charIndex selecciona el token correspondiente, marca los anteriores como leídos y conserva el texto.
SENTENCE_FALLBACK: PASS honesto; onstart marca sólo el primer token y onend sin boundary deja data-narration-sync="unsupported" y data-narration-fallback="sin-eventos-boundary". No hay animación por tiempo ni se inventa sincronización.
START_STOP_REPEAT: PASS en fixture; start, cancel y nueva utterance limpian/reinician el resaltado.
MODAL_CLOSE_CANCELLATION: PASS estático por reutilización de cancelación existente; runtime de los ocho modales NO VERIFICADO en sesión clínica.
AJAX_RERENDER_SAFETY: PASS estático; el wrapper opera sobre el target actual por texto y no registra listeners de DOM ni toca respuestas AJAX. Re-render clínico completo NO VERIFICADO.
ACCESSIBILITY: PASS estático; los spans no cambian el texto accesible, el resaltado no depende de color para operar, se conserva aria-live de los prompts y se respeta prefers-reduced-motion.
P04_REGRESSION: PASS estático / NO VERIFICADO runtime clínico.
P07_REGRESSION: PASS estático / NO VERIFICADO runtime clínico.
P10_REGRESSION: PASS estático / NO VERIFICADO runtime clínico.
P11_REGRESSION: PASS estático / NO VERIFICADO runtime clínico.
RESPONSIVE: NO VERIFICADO para las vistas clínicas en 1440x900, 1280x720, 768x1024 y 375x667; la capa CSS está scoped al shell paciente y el fixture se observó sin overflow en el navegador disponible.
BROWSER_EVIDENCE: PASS en fixture local sintético a viewport visible del navegador; 12 checks de eventos de voz, cancelación, error y fallback.
HUMAN_AUDIBLE_TEST: NO VERIFICADO; el fixture usa un mock y no reproduce audio.
CONSOLE: PASS en fixture; no se observaron errores visibles. Consola de las vistas clínicas NO VERIFICADA.
MODIFIED_FILES: public/assets/js/patient-narration-highlight.js; public/assets/css/app.css (bloque PHASE-04B-03); vistas paciente P04–P11 para marca/script; tests/phase04b/audio-highlight.mjs; tests/phase04b/audio-highlight.fixture.html; este informe.
GLOBAL_DIFF_CHECK: PASS (exit 0); sólo avisos históricos LF/CRLF en el worktree, sin errores de whitespace.
REMAINING_RISKS: reproducción audible humana, eventos boundary reales por navegador/voz, responsive de cada módulo, consola de cada vista, re-render AJAX clínico y validación autenticada completa siguen pendientes. Se conservan ACT-05C-02, riesgos documentales, recetas externas, COFEPRIS y efectos secundarios GET.
PRODUCTION_READY: NO
~~~

## Alcance implementado

Se añadió una única capa compartida para los textos de instrucciones de P04–P11. Cada vista conserva su función de voz, idioma, modal, prompt, botones y cancelación existentes; el nuevo script únicamente localiza la utterance por el texto normalizado y adjunta callbacks compatibles a la misma SpeechSynthesisUtterance.

El resaltado no sustituye el texto por una animación ni genera contenido nuevo. Durante onstart se marca el primer token; durante onboundary se calcula el intervalo a partir de charIndex; al terminar se marca el texto como leído. Si el navegador no entrega límites de palabra, el estado queda explícitamente como no sincronizado por palabra. speechSynthesis.cancel() limpia la sesión y evita que eventos tardíos de una utterance anterior vuelvan a pintar el texto.

El CSS está limitado a:

~~~css
body.app-shell-body.patient-clinical-form [data-narration-instructions]
~~~

Por tanto, no afecta el área Doctor, los mapas de P11 fuera del modal ni P01–P03. Los spans conservan el contenido textual y los prompts aria-live existentes.

## Pruebas ejecutadas

PASS:

- node tests/phase04b/audio-highlight.mjs.
- node --check public/assets/js/patient-narration-highlight.js.
- node --check tests/phase04b/audio-highlight.mjs.
- PHP lint de las ocho vistas P04–P11.
- node tests/p04/audio-mock.mjs.
- node tests/p07/audio-mock.mjs.
- node tests/p10/audio-mock.mjs.
- node tests/p11/contract-check.mjs.
- node tests/phase04b/modal-unification.mjs.
- node tests/phase04b/sidebar-navigation.mjs.
- git diff --check (exit 0).
- Fixture de navegador tests/phase04b/audio-highlight.fixture.html: inicio, texto intacto, boundary conocido, cancelación, evento tardío ignorado, error, finalización, repetición y fallback explícito.

El fixture se ejecutó con una implementación de SpeechSynthesis controlada. No se introdujeron respuestas clínicas, sesiones, POST, AJAX ni escrituras.

## Límites de validación

No se ejecutó audio audible humano ni se declaró que onboundary esté disponible para todas las voces o navegadores. Tampoco se abrió cada módulo autenticado con la base sintética en esta fase; por ello el runtime de P04, P07, P10 y P11, sus modales reales, sus re-renderizados AJAX y sus cuatro viewports quedan NO VERIFICADO.

Las vistas y app.css ya contenían cambios locales extensos de fases anteriores. El diff bruto de esos archivos no se atribuye íntegramente a esta fase; el cambio de esta fase se limita a las marcas/scripts indicados y al bloque CSS anotado PHASE-04B-03.

No se modificaron modelos, controladores, rutas, persistencia, reglas clínicas, P04B01 ni P04B02. No se realizaron commits ni despliegues.
