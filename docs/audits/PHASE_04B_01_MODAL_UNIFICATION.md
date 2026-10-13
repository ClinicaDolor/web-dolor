# PHASE-04B-01 — Unificación canónica de modales P01–P11

Fecha de ejecución: 2026-10-12  
Proyecto: Clínica del Dolor  
Rama: `Silvino`  
HEAD de referencia: `1b08d9b`

## Resultado

```text
PHASE_04B_01_MODAL_UNIFICATION:
STATUS: PARTIAL
MODALS_INSPECTED: P04, P05, P06, P07, P08, P09, P10 y P11; P01/P02 no requieren modal de instrucciones; P03 es una pantalla de lectura y no requiere modal.
CANONICAL_P04_CONTRACT: PASS estático y PASS renderizado en la evidencia disponible a 1280x720; estructura común, atributos ARIA, foco, Escape y desplazamiento seguro.
HEADER_PARITY: PASS estático en P04-P11; misma jerarquía de encabezado, título, cierre y separación.
BODY_PARITY: PASS estático; cuerpo desplazable para contenido largo, sin overflow horizontal del modal.
FOOTER_PARITY: PASS estático; controles Escuchar, Detener y Entendido, comenzar en una fila canónica con adaptación móvil declarada por CSS.
BUTTON_PARITY: PASS estático; contrato de controles y clases compartidas conservado.
P07_OVERFLOW: PASS a 1280x720; el diálogo mide 620x688, el cuerpo mide 604x489 y usa overflow-y auto; scrollWidth de página y cuerpo no excede su ancho.
P04_FOCUS_RETURN: PASS en navegador sintético; Escape cierra el modal y devuelve foco a p04HelpButton.
AUDIO_REGRESSION: PASS en contrato mock; reproducción audible humana NO VERIFICADO. No se implementó resaltado amarillo de PHASE-04B-03.
RESPONSIVE: NO VERIFICADO para 1440x900, 768x1024, 430x932 y 375x667; el navegador utilizable sólo permitió medir 1280x720.
BROWSER_SCREENSHOTS: CAPTURAS CUA visibles de P04, P07 y P10 con modal abierto a 1280x720 durante la ejecución; no se exportaron PNG persistentes por la limitación de la API.
CONSOLE: NO VERIFICADO; la API disponible no expuso DevTools/console del tab.
MODIFIED_FILES: 8 vistas paciente P04-P11, public/assets/css/app.css, public/assets/js/patient-instructions-modal.js, tests/phase04b/modal-unification.mjs y tests/p10/modal-parity.mjs.
GLOBAL_DIFF_CHECK: PASS; únicamente avisos históricos de conversión LF/CRLF.
UNEXPECTED_CHANGES: No se observaron cambios fuera del alcance de modales en esta intervención; el worktree ya contenía cambios previos no relacionados y se preservó.
REMAINING_RISKS: pruebas responsive completas, consola, audio audible, VIS-02 resaltado progresivo amarillo, inconsistencias de shell/footer de baja prioridad, efectos secundarios GET, autorización documental y ACT-05C-02.
PRODUCTION_READY: NO
```

## 1. Modales inspeccionados y diferencias

La auditoría PHASE-04A identificó como VIS-01 la fragmentación de la familia de modales: P04 era la referencia más completa; P05/P07/P09 compartían otra familia; P06 y P08 tenían wrappers propios; P10/P11 reutilizaban parcialmente la familia de P04. P03 no tiene modal porque es una pantalla de lectura. P01 y P02 tampoco requieren uno.

Se conservaron los textos, identificadores, eventos de audio y controles clínicos existentes. La unificación se limita a clases estructurales y a un utilitario de foco compartido; no se creó un modal para una pantalla que no lo necesita.

## 2. Contrato canónico

P04 define el contrato común:

1. Contenedor raíz `patient-instructions-modal` con `role="dialog"`, `aria-modal`, `aria-labelledby`, `aria-describedby` y `aria-hidden`.
2. Diálogo centrado `patient-instructions-dialog`.
3. Contenido `patient-instructions-modal-content` con límite seguro de altura.
4. Encabezado `patient-instructions-modal-header` y título `patient-instructions-modal-title`.
5. Cuerpo `patient-instructions-modal-body`, con scroll vertical para instrucciones extensas.
6. Pie `patient-instructions-modal-actions`, con botones secundarios `patient-instructions-secondary` y acción primaria.
7. Foco inicial en el cierre; Escape, cierre explícito y cierre permitido por backdrop restauran el foco al disparador válido.
8. En móvil, el pie permite apilamiento y el contenido permanece dentro del viewport.

El CSS común quedó acotado a `body.app-shell-body.patient-clinical-form` y a `.patient-instructions-modal`; no se cambió la paleta ni se implementó el resaltado progresivo de voz.

## 3. Cambios por módulo

| Módulo | Cambio aplicado | Estado |
|---|---|---|
| P04 | Clases canónicas, `aria-describedby`, utilitario compartido y retorno de foco para apertura manual/automática. | Implementado |
| P05 | Clases canónicas, relación descriptiva y retorno de foco compartido. | Implementado |
| P06 | Wrapper canónico de diálogo/contenido, clases compartidas, Escape, foco y cierre por backdrop. | Implementado |
| P07 | Wrapper/clases canónicas, foco, Escape y cuerpo desplazable para contenido largo. | Implementado |
| P08 | Wrapper canónico, clases compartidas, foco, Escape y cierre por backdrop. | Implementado |
| P09 | Clases canónicas, relación descriptiva, foco y utilitario compartido. | Implementado |
| P10 | Clases canónicas y utilitario compartido, conservando controles y textos propios. | Implementado |
| P11 | Clases canónicas, relación descriptiva, foco y utilitario compartido. | Implementado |

El helper nuevo es `public/assets/js/patient-instructions-modal.js`. No se modificaron modelos, controladores, rutas, AJAX, persistencia, finalización, `next_url`, catálogos, mapas ni sidebar.

## 4. P07: causa y corrección del overflow

La causa era la combinación de contenido de instrucciones largo con una composición que no imponía un límite de altura al contenido del modal ni un desplazamiento vertical interno; a 1280x720 el footer quedaba fuera de la captura visible. La corrección aplica al contenido canónico `max-height: calc(100vh - 2rem)`, cuerpo con `overflow-y: auto`, ancho máximo de 620px y footer separado del área desplazable. En la comprobación renderizada a 1280x720 el cuerpo tuvo `overflow-y: auto`, ancho de contenido igual a su `clientWidth` y la página mantuvo `scrollWidth === clientWidth`.

## 5. P04: corrección del retorno de foco

El flujo automático de P04 no conservaba el disparador antes de abrir. P04 ahora captura un opener válido con fallback `.patient-family-history-help`, enfoca el cierre al abrir y restaura `p04HelpButton` al cerrar con Escape. La prueba de navegador sintético confirmó `activeElement.id === "p04HelpButton"` y `aria-hidden="true"` después del cierre.

El mismo contrato se integró en P05–P11 sin eliminar la lógica de narración existente. La captura de foco descarta elementos desconectados, ocultos o no enfocables.

## 6. Pruebas estructurales y técnicas

PASS ejecutado:

- `php -l` para las ocho vistas P04–P11.
- `node --check public/assets/js/patient-instructions-modal.js`.
- `node --check public/assets/js/voice-utilities.js`.
- `node tests/phase04b/modal-unification.mjs`.
- `node tests/p10/modal-parity.mjs`.
- `node tests/patient-module-parity-gate.mjs`.
- `node tests/p04/audio-mock.mjs`: contrato mock PASS; reproducción audible `false`.
- `git diff --check`: PASS, con avisos históricos LF/CRLF sin errores de whitespace.

El contrato de fase comprueba ahora el orden de raíz → diálogo → contenido → encabezado/título → cuerpo → acciones, relaciones ARIA a elementos existentes, controles y carga del helper. No se limita a buscar palabras o clases aisladas.

## 7. Evidencia de navegador

Se utilizó el login real de paciente sintético en `p04-20261012.localhost:8131`, servido por el runner aislado de P04 y la base `clinica_dolor_demo_20261010_final`. No se usó `bd_clinica`, no se enviaron respuestas ni se ejecutaron escrituras clínicas.

Se observaron y capturaron durante la ejecución:

- P04 con modal abierto: encabezado, cuerpo, estado de voz, botones y foco visible.
- P07 con modal abierto: contenido largo desplazable y footer visible dentro del diálogo.
- P10 con modal abierto: misma distribución canónica y contenido sintético.

La dimensión real medida fue `1280x720`. La API CUA no permitió fijar 1440x900, 768x1024, 430x932 o 375x667 ni exportar PNG persistentes; esas comprobaciones quedan `NO VERIFICADO`, no PASS. Tampoco se obtuvo la consola DevTools mediante la API disponible.

## 8. Regresiones y pendientes

No se observó regresión de navegación en los GET autenticados de P04, P07 y P10. Los controles Escuchar/Detener y la narración existente permanecieron presentes. La narración audible humana sigue sin verificarse. El resaltado progresivo amarillo queda expresamente fuera de alcance para PHASE-04B-03.

Se mantienen sin resolver los riesgos heredados de PHASE-04A y del proyecto: efectos secundarios GET, autorización clínica/documental, PDF/descargas/almacenamiento de ACT-05C-02, riesgos de COFEPRIS y recetas externas, y validación responsive completa. Este informe no constituye aprobación de producción.

