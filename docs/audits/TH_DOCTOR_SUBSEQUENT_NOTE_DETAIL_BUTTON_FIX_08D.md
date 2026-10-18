# TH-DOCTOR-SUBSEQUENT-NOTE-DETAIL-BUTTON-FIX-08D

## Alcance

Corrección focalizada de la columna `ACCIONES` del listado de Nota subsecuente. No se ejecutaron HTTP, PDO, consultas ni escrituras; el navegador local de XAMPP no está disponible desde Codex.

## Causa y corrección

La vista todavía renderizaba el dropdown incorporado en 08C. Ese control agrupaba `Editar` y `Detalle`, junto con un bloque de JavaScript para posicionamiento, teclado y cierre del menú. La instrucción 08D requiere una sola acción visible por fila.

Se eliminó del listado únicamente:

- El botón `Acciones` y el menú desplegable.
- Las opciones `Editar` y `Detalle` del menú.
- El JavaScript exclusivo del dropdown.
- Las reglas CSS exclusivas del dropdown.

Cada fila conserva ahora un único enlace nativo `Ver detalle`, con icono `file-text`, `aria-label` y la ruta existente `/clinica/nota-subsecuente/{idNota}`. La ruta de edición, su controlador, autorización, modelo y vista independiente no se modificaron; simplemente dejaron de exponerse como acción en el listado.

## Presentación

El botón de detalle utiliza CSS scoped a `body.app-shell-body.doctor-patient-note-page`:

- Estado normal: fondo blanco, texto e icono morados, borde institucional y radio/padding consistentes.
- Hover/active: fondo morado institucional y texto/icono blancos.
- Focus visible: contorno accesible y estado legible.
- Al ser un enlace `<a>`, conserva activación por teclado sin depender de JavaScript delegado.

La tabla mantiene `fixedHeight: false`, búsqueda, ordenamiento y la inicialización existente de Simple-DataTables. También se conservaron la acción `Nueva nota subsecuente`, el historial completo y los contratos de creación, edición, CSRF y transacciones de Phase 07.

## Validación

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_DETAIL_BUTTON_FIX_08D:
  STATUS: IMPLEMENTED_STATIC_VALIDATION_PENDING_BROWSER
  DROPDOWN_REMOVED: PASS_STATIC
  EDIT_ACTION_REMOVED_FROM_LIST: PASS_STATIC
  SINGLE_DETAIL_BUTTON: PASS_STATIC
  DETAIL_ACTION_PRESERVED: PASS_STATIC
  NORMAL_BACKGROUND_WHITE: PASS_STATIC
  NORMAL_TEXT_PURPLE: PASS_STATIC
  HOVER_BACKGROUND_PURPLE: PASS_STATIC
  HOVER_TEXT_WHITE: PASS_STATIC
  HOVER_ICON_WHITE: PASS_STATIC
  FOCUS_VISIBLE: PASS_STATIC
  KEYBOARD_ACCESS: PASS_STATIC_NATIVE_ANCHOR
  DATATABLE_COMPATIBILITY: PASS_STATIC
  NEW_NOTE_BUTTON_PRESERVED: PASS_STATIC
  PATIENT_HISTORY_PRESERVED: PASS_STATIC
  CREATE_VIEW_PRESERVED: YES
  PHASE_07_PRESERVED: PASS_STATIC
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  CSS_REVIEW: PASS_STATIC
  FOCUSED_TESTS: PASS_STATIC
  DIFF_CHECK: PASS_WITH_HISTORICAL_LINE_ENDING_WARNINGS
  BROWSER_VALIDATION: NOT_EXECUTED
  FILES_MODIFIED:
    - app/views/clinica/pacientes-nota-subsecuente.php
    - public/assets/css/app.css
    - tests/doctor-subsequent-note/detail-button-contract-08d.php
    - docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_DETAIL_BUTTON_FIX_08D.md
  DATABASE_MODIFIED: NO
  BACKEND_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
```

La validación visual en escritorio/móvil, hover/focus renderizados y compatibilidad efectiva con el navegador quedan pendientes de comprobación en Chrome/Edge sobre el entorno local del propietario.
