# PHASE-04A — Auditoría de consistencia visual del Portal Paciente

Fecha: 2026-10-12  
Proyecto: Clínica del Dolor  
Alcance: P01–P11, diagnóstico visual y planificación únicamente

## Método y límites

Se contrastaron `app/Config/routes.php`, `ModulosController`,
`SidebarController`, `app/Helpers/Sidebar.php`, las vistas de
`app/views/historia-clinica-paciente/`, `app/views/login/login-paciente.php`,
`app/views/paciente/index.php`, `public/assets/css/app.css`,
`public/assets/js/voice-utilities.js` y la documentación previa de P01–P11.

La validación renderizada se realizó con datos sintéticos en la base aislada
`clinica_dolor_demo_20261010_final`, con el paciente sintético 9301 y sin usar
`bd_clinica`. No se enviaron respuestas, no se ejecutaron POST clínicos, no se
finalizaron módulos ni se modificó la aplicación.

El navegador integrado permitió árboles de accesibilidad y capturas a la
dimensión disponible de 1280×720, pero no fijar reproduciblemente todos los
viewports exigidos ni exportar PNG desde la API usada. Playwright no está
instalado. Los restantes viewports, consola DevTools y audio audible quedan
`NO VERIFICADO`.

El árbol de trabajo ya estaba modificado antes de esta auditoría: rama
`Silvino`, HEAD `1b08d9b`. Se preservaron esas diferencias.

## Identificación de pantallas

La ruta genérica de los nueve módulos es
`GET /historia-clinica/{modulo}/paciente/{idPaciente}` y la renderiza
`ModulosController::moduloVistaPaciente`. La tabla de slugs está en
`PacienteModulosModelo` y el sidebar de paciente en `SidebarController`.

| ID | Pantalla | Ruta | Vista / controlador | Resultado visual de esta auditoría |
|---|---|---|---|---|
| P01 | Login Paciente | `GET /historia-clinica/acceso` | `login-paciente.php` / `LoginController::accesoPaciente` | `PASS` renderizado sin sesión a 1280×720; responsive y autenticación real `NO VERIFICADO`. |
| P02 | Dashboard Paciente | `GET /historia-clinica` | `paciente/index.php` / `HomeController::indexPaciente` | `PASS` renderizado autenticado: progreso, siguiente sección y nueve módulos; responsive `NO VERIFICADO`. |
| P03 | Ficha de identificación | `GET /historia-clinica/ficha-identificiacion/paciente/{idPaciente}` | `ficha-identificiacion.php` / `ModulosController::moduloVistaPaciente` | `PASS` GET sintético y cuatro tarjetas; modal de instrucciones `NO APLICA`; responsive `NO VERIFICADO`. |
| P04 | Antecedentes familiares | `GET /historia-clinica/antecedentes-familiares/paciente/{idPaciente}` | `antecedentes-familiares.php` / `ModulosController::moduloVistaPaciente` | `PASS` hero, pregunta y modal; `FAIL` foco en apertura automática, VIS-05; responsive `NO VERIFICADO`. |
| P05 | Antecedentes personales no patológicos | `GET /historia-clinica/antecedentes-personales-no-patologicos/paciente/{idPaciente}` | `antecedentes-personales-no-patologicos.php` / `ModulosController::moduloVistaPaciente` | `PASS` pregunta y modal; paridad con P04 parcial; responsive `NO VERIFICADO`. |
| P06 | Antecedentes personales quirúrgicos | `GET /historia-clinica/antecedentes-personales-quirurgicos/paciente/{idPaciente}` | `antecedentes-personales-quirurgicos.php` / `ModulosController::moduloVistaPaciente` | `PASS` estado vacío y modal; modal propio fuera del patrón canónico; responsive `NO VERIFICADO`. |
| P07 | Antecedentes personales patológicos | `GET /historia-clinica/antecedentes-personales-patologicos/paciente/{idPaciente}` | `antecedentes-personales-patologicos.php` / `ModulosController::moduloVistaPaciente` | `PASS` pregunta V1 y modal; `FAIL` altura/visibilidad del modal a 1280×720, VIS-04; responsive completo `NO VERIFICADO`. |
| P08 | Medicación actual | `GET /historia-clinica/medicacion-actual/paciente/{idPaciente}` | `medicacion-actual.php` / `ModulosController::moduloVistaPaciente` | `PASS` registros sintéticos y modal; modal propio fuera del patrón canónico; responsive `NO VERIFICADO`. |
| P09 | Medicamentos usados para controlar el dolor | `GET /historia-clinica/medicacion-control-dolor/paciente/{idPaciente}` | `medicacion-control-dolor.php` / `ModulosController::moduloVistaPaciente` | `PASS` pregunta, controles y modal en AX; captura estable y responsive `NO VERIFICADO`. |
| P10 | Procedimientos usados para controlar el dolor | `GET /historia-clinica/procedimientos-control-dolor/paciente/{idPaciente}` | `procedimientos-control-dolor.php` / `ModulosController::moduloVistaPaciente` | `PASS` estado vacío, acciones y modal; audio en fallback escrito durante captura; responsive `NO VERIFICADO`. |
| P11 | Evaluación del dolor | `GET /historia-clinica/evaluacion-dolor/paciente/{idPaciente}` | `evaluacion-dolor.php` / `ModulosController::moduloVistaPaciente` | `PASS` mapa frontal, controles, modal y navegación inicial; responsive `NO VERIFICADO`. |

La presencia de controles no demuestra guardado, AJAX, audio audible ni
finalización. Esas funciones quedan fuera de esta auditoría visual.

## PHASE_04A_VISUAL_CONSISTENCY_AUDIT

### STATUS

`PARTIAL`: se inspeccionaron P01–P11 y se obtuvo evidencia sintética
renderizada parcial; falta una matriz responsive reproducible y capturas PNG
persistidas para declarar la auditoría completa.

### MODULES_INSPECTED

P01–P11, en el orden anterior. La identificación de P03–P11 coincide con
`routes.php`, `ModulosController` y el mapa de slugs de
`PacienteModulosModelo`. P03 es lectura/finalización; P06/P08 son CRUD; P10
combina procedimientos y tratamientos; P11 usa mapa/canvas y cuestionario.

### MODAL_PARITY

`FAIL` como estándar único, aunque no se observó una ruptura global de acceso.

- P04 es la referencia más completa: `patient-family-history-modal`,
  `modal-dialog modal-dialog-centered`, título, cuerpo, estado de voz y
  acciones.
- P05, P07 y P09 usan `patient-nonpathological-modal`; comparten composición,
  pero no markup/estilo exactos.
- P10 y P11 reutilizan la familia de P04.
- P06 usa `p06-modal` y P08 usa `p08-modal`, ambos con wrappers propios.
- P03 no tiene ayuda/modal porque su pantalla es de lectura; `NO APLICA`.
- P09/P10 tienen ciclo Tab explícito. P04/P05 manejan foco en aperturas
  manuales; P06/P08/P11 no muestran un ciclo Tab equivalente en su vista.

### SIDEBAR_ACTIVE_STATE

`PASS` en estructura y evidencia disponible. `Sidebar::render()` compara el
título exacto, añade una única clase `active` y emite `aria-current="page"`.
`SidebarController` entrega el módulo actual más el menú común. En P02 se
activa Inicio; en los módulos se activa un único módulo. La apertura/cierre
responsive del sidebar queda `NO VERIFICADO`.

### AUDIO_YELLOW_HIGHLIGHT

`FAIL` respecto al resaltado progresivo requerido. `app.css:121` sólo define
`.highlightText { background-color: yellow; }`. No se encontró uso de esa
clase, `SpeechSynthesisUtterance.onboundary`, `Range` ni una rutina que
envuelva palabras progresivamente en P01–P11 o `voice-utilities.js`. Hay
lectura, cancelación y estados, pero no resaltado amarillo por palabra. Audio
audible humano `NO VERIFICADO`.

### BUTTON_CONSISTENCY

`PARTIAL`.

- P04–P11 mantienen normalmente el orden ayuda y regreso; P03 sólo necesita
  regreso (`NO APLICA` para ayuda).
- Los botones de voz se dividen en familias P04/P05/P07/P09, P06, P08, P10 y
  P11. Los iconos y textos son parecidos, no idénticos.
- P08/P10 tienen acciones de agregar y estados vacíos propios; P11 tiene
  controles de mapa.
- El disparador del sidebar no es uniforme: P04/P05/P07/P09 exponen `☰` sin
  nombre accesible en el árbol AX; P03/P06/P08/P10/P11 sí exponen nombre en
  la mayoría de las rutas. Se registra como VIS-03.

### FORM_CONSISTENCY

`PARTIAL`. El shell `app-shell-patient patient-clinical-form` y los tokens de
`app.css` conservan la identidad institucional. La implementación se divide
en lectura de P03, cuestionarios P04/P05/P07/P09, CRUD P06/P08, flujo mixto
P10 y mapa/cuestionario P11. La fragmentación aumenta la probabilidad de
divergencia en modales, estados vacíos, voz y responsive. No se ejecutaron
escrituras en esta auditoría.

### RESPONSIVE

| Viewport | Resultado | Evidencia |
|---|---|---|
| 1440×900 | `NO VERIFICADO` | No se pudo fijar esta dimensión. |
| 1280×720 | `PARTIAL` | Capturas/AX para P01, P02, P04, P06, P07, P08, P10 y P11; VIS-04 en P07. |
| 768×1024 | `NO VERIFICADO` | Sin control reproducible de viewport. |
| 430×932 | `NO VERIFICADO` | Sin control reproducible de viewport. |
| 375×667 | `NO VERIFICADO` | Sin control reproducible de viewport. |

Las reglas CSS sí contienen apilado móvil, pero no se convierten en PASS sin
renderizar cada resolución.

### BROWSER_EVIDENCE

`PARTIAL`.

- Codex In-app Browser mediante CUA, host sintético
  `phase04a.localhost:8131`.
- P01 sin sesión redirigió una ruta protegida a `/historia-clinica/acceso` y
  mostró el login a 1280×720.
- Se utilizó el login real con cuenta sintética; no se añadió bypass.
- P02 y P01 tuvieron capturas estables; P04, P06, P07, P08, P10 y P11
  mostraron modal y su contenido sintético; P03/P05/P09 entregaron AX cargado
  pero captura settled no persistida.
- El cierre de P04 por interacción mostró que la apertura automática no deja
  demostrablemente el foco en el disparador; la causa estática está en VIS-05.
- No se enviaron respuestas, AJAX clínico, finalizaciones, descargas ni PDF.
- No se obtuvo consola DevTools, audio audible humano ni archivos PNG
  exportados por la API. No se declaran como PASS.

### CRITICAL_VISUAL_FINDINGS

No se identificó un P0 que bloquee el acceso o la navegación básica. Hallazgos:

| ID | Prioridad | Hallazgo | Evidencia |
|---|---|---|---|
| VIS-01 | P1 | No hay modal canónico único: P06/P08 usan wrappers propios y el resto usa dos familias; P03 no tiene modal. | Markup y CSS de P01–P11. |
| VIS-02 | P1 | No existe resaltado progresivo amarillo; `.highlightText` está huérfano. | `app.css:121`, `voice-utilities.js` y vistas paciente. |
| VIS-03 | P1 | Nombre accesible inconsistente en el disparador del sidebar; varias vistas exponen sólo `☰`. | Árbol AX P04/P05/P07/P09 y markup. |
| VIS-04 | P1 | Modal largo de P07 rebasa la altura visible de 1280×720; controles inferiores quedan fuera de la captura inicial. | Captura renderizada de P07. |
| VIS-05 | P1 | La apertura automática de P04 llama `p04OpenInstructions()` sin guardar `p04LastFocusedElement`; el cierre no demuestra retorno al botón de ayuda. | Inicialización de P04 y prueba de cierre. |
| VIS-06 | P2 | Shell visual común, pero `footer-doctor.php` se incluye en las nueve vistas paciente y los stacks de scripts no son uniformes. | Includes y bloques `<script>`. |
| VIS-07 | P2 | Estados vacíos, agregar y navegación tienen variantes propias en P06/P08/P10/P11. | Markup/CSS scoped y capturas. |

### CANONICAL_COMPONENT_PROPOSAL

No se implementa en PHASE-04A. Para una fase posterior:

1. `patient-module-hero`: copy, contexto, ayuda y regreso, con orden DOM único.
2. `patient-instructions-modal`: markup único, `aria-labelledby`,
   `aria-describedby`, ciclo Tab, Escape, retorno de foco y estado de voz.
3. `patient-voice-controls`: Escuchar/Detener, `aria-live`, cancelación única
   y eventual resaltado por `onboundary` con fallback escrito.
4. `patient-empty-state` y `patient-action-row` para estados y acciones.
5. `patient-sidebar-toggle` con control semántico, nombre accesible,
   `aria-expanded` y foco visible.

La consolidación debe conservar textos, IDs, endpoints, permisos, preguntas y
persistencia. No se recomienda modificar `footer-doctor.php` sin revisar sus
dependencias globales.

### REMEDIATION_PLAN

1. Resolver VIS-01 migrando P06/P08 al modal canónico y alineando P04/P05/P07/P09/P10/P11.
2. Definir y probar el contrato de resaltado VIS-02 con mock y fallback escrito.
3. Normalizar el disparador del sidebar (VIS-03).
4. Corregir altura/scroll de P07 y foco de apertura automática de P04 (VIS-04/VIS-05).
5. Repetir todos los módulos con 1440×900, 1280×720, 768×1024, 430×932 y
   375×667, capturas PNG persistidas fuera de directorios clínicos, consola y
   revisión visual humana.
6. Mantener separados los riesgos funcionales, de autorización y documentales
   antes de cualquier aprobación visual.

### MODIFIED_APPLICATION_FILES

`NONE`.

Sólo se creó este informe. Los dos scripts temporales del servidor sintético
fueron retirados al finalizar y no forman parte de la aplicación.

### PRODUCTION_READY

`NO`. Siguen abiertos ACT-05C-02 (PDF, descargas y almacenamiento documental),
recetas externas/COFEPRIS, efectos secundarios GET, cobertura responsive
incompleta, audio audible no verificado y VIS-01–VIS-07.

