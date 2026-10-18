# TH-DOCTOR-COFEPRIS-UX-IMPLEMENTATION-01

## Alcance

Homologación visual focalizada de la vista Doctor de COFEPRIS con los patrones aprobados de Laboratorio y Receta médica. No se ejecutaron HTTP, navegador, base de datos ni operaciones clínicas.

## Identificación confirmada

- Vista: `app/views/clinica/pacientes-cofepris.php`.
- Ruta de trabajo: `/clinica/cofepris/paciente/{idPaciente}`.
- Controlador: `ClinicaController::pacienteCofepris()`.
- Autorización existente: `authPermisos()` y `assertPatientAccess()` en el controlador; las operaciones AJAX conservan sus guardas existentes.
- Dependencias preservadas: `CofeprisModel`, endpoints de consulta/guardado, edición, PDF y apertura de documento.

## Cambios realizados

- Se homologó el botón dinámico `Volver al expediente` con la estructura, icono y clase institucional de Laboratorio/Receta.
- Se conservó `toggleButton`, `toggleSize()`, el icono `columns`, `aria-label` y `title`.
- Se sustituyó el bloque permanente de resumen por una franja compacta de contexto del paciente con nombre y expediente dinámicos.
- Se organizó COFEPRIS en una tarjeta de ancho completo con `REGISTROS DISPONIBLES`, contador dinámico, estado vacío y tabla integrada.
- Se conservaron búsqueda, ordenamiento, paginación y altura natural de Simple-DataTables (`fixedHeight: false`).
- Se agregó soporte de teclado para abrir el detalle desde las filas y se conservaron las acciones de edición dinámicas.
- Se encapsularon los flujos de alta y detalle en modales accesibles dentro de la vista, sin cambiar endpoints, campos ni backend.
- Se conservaron los campos de formulario, PDF, impresión/apertura de documento y contratos AJAX existentes.
- Los estilos nuevos están scoped a `body.app-shell-body.doctor-patient-cofepris-page`.

## Preservación y riesgos

No se modificaron modelos, controladores, rutas, middleware, autenticación, autorización, PDF, base de datos ni `.env`.

La salida HTML histórica de `CofeprisModel` para detalle/listado conserva riesgos de sanitización y autorización que requieren una remediación de seguridad independiente. Esta intervención no los corrige ni los declara resueltos.

La igualdad visual final, el comportamiento real de los modales, foco/Escape, DataTables, PDF, red y responsive necesitan validación manual con XAMPP y una sesión Doctor autorizada. No se afirma PASS runtime.

## Evidencia estática

```yaml
TH_DOCTOR_COFEPRIS_UX_IMPLEMENTATION_01:
  STATUS: PARTIAL_STATIC_IMPLEMENTATION_RUNTIME_VALIDATION_PENDING
  TARGET_VIEW_IDENTIFIED: PASS
  BACK_BUTTON_REFERENCE_LAB: PASS_STATIC
  BACK_BUTTON_REFERENCE_PRESCRIPTION: PASS_STATIC
  BACK_BUTTON_ROOT_DIFFERENCE: "El marcado/estilo heredado de COFEPRIS difería; ahora reutiliza la estructura institucional aprobada."
  BACK_BUTTON_EXACT_FIX: PASS_STATIC
  BACK_BUTTON_MARKUP_MATCH: PASS_STATIC
  BACK_BUTTON_CSS_MATCH: PASS_STATIC
  BACK_BUTTON_ICON_MATCH: PASS_STATIC
  BACK_BUTTON_LABEL_MATCH: PASS_STATIC
  BACK_BUTTON_DESTINATION_PRESERVED: PASS_STATIC_DYNAMIC_ID
  INSTITUTIONAL_HEADER_PRESERVED: PASS_STATIC
  COMPACT_PATIENT_HEADER: PASS_STATIC
  FULL_WIDTH_COFEPRIS_CARD: PASS_STATIC
  INSTITUTIONAL_TYPOGRAPHY: PASS_STATIC_REGISTROS_DISPONIBLES
  REDUNDANT_TITLES_REMOVED: PASS_STATIC
  DYNAMIC_COUNT: PASS_STATIC
  TABLE_SEARCH_PRESERVED: PASS_STATIC
  TABLE_SORT_PRESERVED: PASS_STATIC
  TABLE_PAGINATION_PRESERVED: PASS_STATIC
  EMPTY_STATE_IMPROVED: PASS_STATIC
  KEYBOARD_ACCESSIBILITY: PASS_STATIC
  CREATE_FLOW_IDENTIFIED: PASS_STATIC
  CREATE_MODAL_FEASIBLE: PASS_STATIC_NO_BACKEND_CHANGE
  CREATE_MODAL_IMPLEMENTED: PASS_STATIC
  CREATE_FLOW_PRESERVED: PASS_STATIC
  DETAIL_FLOW_IDENTIFIED: PASS_STATIC
  DETAIL_MODAL_FEASIBLE: PASS_STATIC
  DETAIL_MODAL_IMPLEMENTED: PASS_STATIC
  DETAIL_FLOW_PRESERVED: PASS_STATIC
  COFEPRIS_DOCUMENTS_PRESERVED: PASS_STATIC_NO_DOCUMENT_CHANGES
  PDF_PRESERVED: PASS_STATIC
  PRINT_FLOW_PRESERVED: PASS_STATIC_EXISTING_WINDOW_OPEN
  AUTHORIZATION_PRESERVED: PASS_STATIC_EXISTING_CONTROLLER_AJAX_GUARDS
  RESPONSIVE_STATIC: PASS_STATIC
  BROWSER_VALIDATION: NOT_EXECUTED
  RUNTIME_VALIDATION: NOT_EXECUTED
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  CSS_REVIEW: PASS_STATIC_SCOPED
  DIFF_CHECK: PASS
  FILES_MODIFIED:
    - app/views/clinica/pacientes-cofepris.php
    - public/assets/css/app.css
    - tests/doctor-cofepris/ux-implementation-01-contract.php
    - docs/audits/TH_DOCTOR_COFEPRIS_UX_IMPLEMENTATION_01.md
  DOCUMENT_CREATED: PASS
  DATABASE_MODIFIED: NO
  BACKEND_MODIFIED: NO
  PDF_TEMPLATES_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS: "No se ejecutó XAMPP/Chrome desde Codex; falta validar visual y funcionalmente la vista renderizada."
  RESIDUAL_RISKS: "La salida HTML heredada de CofeprisModel requiere una remediación de seguridad separada."
  NEXT_MANUAL_VALIDATION: "Con sesión Doctor autorizada en /clinica/cofepris/paciente/{id}: revisar 1440/1280/1024/768/430/375, alta, detalle, X, Escape, foco, búsqueda, ordenamiento, paginación, edición, PDF, consola, red y overflow."
```

## Comprobaciones ejecutadas

- `php -l app/views/clinica/pacientes-cofepris.php`: PASS.
- `php -l tests/doctor-cofepris/ux-implementation-01-contract.php`: PASS.
- Contrato focalizado `tests/doctor-cofepris/ux-implementation-01-contract.php`: PASS.
- Parseo de JavaScript inline mediante Node: PASS.
- `git diff --check` sobre la vista y CSS: PASS.

## Estado de cierre

COFEPRIS queda en VALIDACIÓN. Esta intervención se detiene aquí y no implica aprobación funcional, de seguridad ni autorización para producción.
