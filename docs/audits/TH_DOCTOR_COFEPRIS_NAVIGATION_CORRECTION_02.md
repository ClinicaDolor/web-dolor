# TH-DOCTOR-COFEPRIS-NAVIGATION-CORRECTION-02

## Causa raíz

El defecto histórico provenía de que el enlace `Editar` navegaba desde el listado a `/clinica/cofepris/paciente/{idPaciente}/editar/{idCofepris}` como documento completo. El cierre del modal ocultaba la interfaz, pero no eliminaba ni normalizaba esa navegación. Por ello, el historial podía requerir volver primero al listado y después al expediente.

La vista actual contiene la corrección focalizada: el clic normal desde el listado se intercepta y carga el formulario de la ruta de edición dentro del modal actual. El `href` continúa disponible para acceso directo, pestaña nueva o clic modificado. El acceso directo conserva la ruta y, al cerrar, normaliza su URL al listado con un único `replaceState`, sin `history.back()`, `history.go()` ni `pushState()`.

## Botón «Volver al expediente»

La implementación COFEPRIS usa el mismo patrón que Laboratorio y Receta médica:

- clase `doctor-page-hero-action`;
- icono Feather `arrow-left` decorativo;
- texto visible `Volver al expediente`;
- destino dinámico construido con `SERVIDOR` y `$data['idPaciente']`;
- fondo blanco, borde institucional, texto morado y altura mínima de 44 px;
- estados hover/focus y comportamiento responsive equivalentes.

El blanco no es una variante incorrecta: es precisamente el tratamiento institucional de las vistas de referencia.

## Resultado

```yaml
TH_DOCTOR_COFEPRIS_NAVIGATION_CORRECTION_02:
  STATUS: PARTIAL_STATIC_IMPLEMENTATION_RUNTIME_VALIDATION_PENDING
  ROOT_CAUSE: "El enlace de edición creaba una navegación de documento; cerrar el modal no normalizaba esa entrada."
  EDIT_OPEN_NAVIGATION: "Desde el listado, clic normal interceptado; formulario cargado en el modal mediante fetch same-origin."
  EDIT_CLOSE_NAVIGATION: "El listado permanece en la misma entrada; el acceso directo normaliza la URL con replaceState."
  DUPLICATE_HISTORY_ENTRY_CONFIRMED: PASS_STATIC_CAUSAL
  DUPLICATE_HISTORY_ENTRY_CORRECTED: PASS_STATIC
  BROWSER_BACK_SINGLE_CLICK: NOT_EXECUTED
  BROWSER_FORWARD: NOT_EXECUTED
  DIRECT_EDIT_URL: PRESERVED_STATIC
  MODAL_CLOSE_X: PRESERVED_STATIC
  MODAL_CLOSE_CANCEL: PRESERVED_STATIC
  MODAL_CLOSE_ESCAPE: PRESERVED_STATIC
  BACK_BUTTON_REFERENCE: LABORATORY_AND_RECIPE
  BACK_BUTTON_COLOR: "Equivalent white background with institutional border and purple text"
  BACK_BUTTON_CSS_MATCH: PASS_STATIC
  BACK_BUTTON_ICON_MATCH: PASS_STATIC
  BACK_BUTTON_TEXT_MATCH: PASS_STATIC
  BACK_BUTTON_DESTINATION_PRESERVED: PASS_STATIC_DYNAMIC_ID
  CREATE_MODAL_PRESERVED: PASS_STATIC
  EDIT_MODAL_PRESERVED: PASS_STATIC
  DETAIL_MODAL_PRESERVED: PASS_STATIC
  TABLE_STATE_PRESERVED: PASS_STATIC
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  DIFF_CHECK: PASS
  BROWSER_VALIDATION: NOT_EXECUTED
  FILES_MODIFIED:
    - tests/doctor-cofepris/navigation-correction-02-contract.php
    - docs/audits/TH_DOCTOR_COFEPRIS_NAVIGATION_CORRECTION_02.md
  APPLICATION_FILE_VERIFIED:
    - app/views/clinica/pacientes-cofepris.php
  DOCUMENT_CREATED: PASS
  DATABASE_MODIFIED: NO
  CLINICAL_LOGIC_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS: "No se ejecutó Chrome/XAMPP desde Codex; Atrás, Adelante y estado real de la tabla requieren prueba manual."
  NEXT_MANUAL_VALIDATION: "Con sesión Doctor autorizada: Expediente -> COFEPRIS -> Editar -> cerrar -> Atrás; repetir con X/Cancelar/Escape, Adelante, búsqueda/ordenamiento/paginación y /editar/351 directo."
```

## Validaciones ejecutadas

- `php -l app/views/clinica/pacientes-cofepris.php`: PASS.
- `php -l tests/doctor-cofepris/navigation-correction-02-contract.php`: PASS.
- `php tests/doctor-cofepris/navigation-correction-02-contract.php`: PASS.
- `php tests/doctor-cofepris/browser-history-fix-02-contract.php`: PASS.
- Parseo de JavaScript inline mediante Node: PASS.
- Revisión estática de rutas, eventos e History API: PASS.
- `git diff --check`: PASS.

No se ejecutaron HTTP, navegador, base de datos ni operaciones clínicas. No se modificó CSS, backend, rutas, PDF, `.env` ni otras vistas. COFEPRIS permanece en validación hasta la prueba manual real.
