# TH-DOCTOR-SUBSEQUENT-NOTE-INDEPENDENT-VIEWS-IMPLEMENTATION-08

## Resultado

La implementación separa la Nota subsecuente en un listado y una vista de alta independiente. La validación realizada en Codex es estática; Apache, MySQL, HTTP y navegador no fueron utilizados.

## Rutas y comportamiento

- **Listado:** `/clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}`
  - Autoriza el paciente mediante `assertPatientAccess()`.
  - Consulta únicamente el historial de la combinación paciente/referencia.
  - Presenta tabla de ancho completo, búsqueda, ordenamiento, paginación/contador de Simple-DataTables y estado vacío.
  - Incluye «Nueva nota subsecuente» y enlaces de detalle a `/clinica/nota-subsecuente/{idNota}`.
  - No contiene formulario de nota, receta, laboratorio, próxima cita ni panel de detalle permanente.

- **Alta:** `/clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}/nueva`
  - Autoriza el mismo contexto paciente/referencia.
  - Inicia el editor vacío, sin cargar una nota o receta existente y sin escribir durante GET.
  - Incluye los campos clínicos existentes: signos vitales, detalle Quill, receta opcional, temperatura, laboratorio opcional y próxima cita opcional.
  - Guarda con el contrato de Phase 07, CSRF, `fc` y temperatura condicionales, y redirige al listado tras una respuesta exitosa.
  - Incluye cancelación hacia el listado y advertencia ante cambios sin guardar.

La vista de detalle existente continúa siendo la responsable de mostrar una nota individual; no se creó una segunda implementación clínica de detalle.

## Preservación clínica y seguridad

Se mantuvieron los nombres e identificadores de los campos, Quill, sanitización de contenido enriquecido, encabezado `X-M03-CSRF`, validación de acceso al paciente y las comprobaciones de paciente/referencia del endpoint de guardado. La transacción y rollback de la alta de Phase 07 permanecen en `NotaSubsecuenteModel`.

El listado ahora parametriza también `codigo_referencia`, evitando mezclar historiales de otra referencia. El laboratorio conserva su endpoint y CSRF existentes; su alta sigue siendo una operación opcional separada del guardado de la nota.

## Cambios realizados

- `app/Config/routes.php`: nueva ruta GET `/nueva`.
- `app/Controllers/ClinicaController.php`: contexto autorizado compartido, renderizado del listado y renderizado de la vista de alta vacía.
- `app/views/clinica/pacientes-nota-subsecuente.php`: listado independiente.
- `app/views/clinica/pacientes-nota-subsecuente-nueva.php`: editor independiente y navegación de alta.
- `app/views/clinica/partials/nota-subsecuente-editor.php`: único bloque de campos clínicos del editor.
- `public/assets/css/app.css`: ajuste scoped para que el encabezado de sección se adapte a móvil.
- `tests/doctor-subsequent-note/functional-remediation-07.php`: contrato actualizado al nuevo archivo del editor.
- `tests/doctor-subsequent-note/security-contract-04.php` y `reference-scope-contract-04c.php`: contratos actualizados para la separación de vistas.
- `tests/doctor-subsequent-note/independent-views-contract-08.php`: contrato estático nuevo.

## Validación

| Comprobación | Resultado |
|---|---|
| PHP lint de rutas, controlador, listado, alta y parcial | PASS |
| Sintaxis JS inline de la vista de alta | PASS |
| Contrato funcional Phase 07 | PASS_STATIC |
| Contrato de seguridad existente | PASS_STATIC |
| Contrato de alcance paciente/referencia | PASS_STATIC |
| Contrato de vistas independientes | PASS_STATIC |
| `git diff --check` | PASS; sólo avisos históricos de conversión LF/CRLF |
| HTTP/Apache/MySQL | NO VERIFICADO; no accesibles desde Codex |
| Persistencia real y rollback en runtime | NO VERIFICADO |
| Navegador, responsive, consola y Network | NO VERIFICADO |

## Entrega declarativa

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_INDEPENDENT_VIEWS_IMPLEMENTATION_08:
  STATUS: IMPLEMENTED_STATIC_VALIDATION_PENDING_RUNTIME
  INITIAL_AUDIT: PASS_STATIC
  ORIGINAL_LAYOUT_CAUSE: LISTADO, DETALLE Y EDITOR COMPARTIAN UNA SOLA VISTA
  LIST_VIEW_IMPLEMENTED: YES
  LIST_VIEW_ROUTE: /clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}
  LIST_VIEW_ONLY_HISTORY: YES
  LIST_VIEW_NO_CREATION_FORM: YES
  LIST_VIEW_NO_PERMANENT_LAB_FORM: YES
  LIST_VIEW_NO_PERMANENT_NOTE_DETAIL: YES
  NEW_NOTE_BUTTON: Nueva nota subsecuente
  NEW_NOTE_BUTTON_VISIBLE: STATIC_ONLY
  NEW_NOTE_BUTTON_FUNCTIONAL: STATIC_ONLY
  NEW_NOTE_ROUTE_CREATED: YES
  NEW_NOTE_VIEW_CREATED: YES
  INDEPENDENT_NAVIGATION: YES_STATIC
  DIRECT_URL_ACCESS: YES_ROUTE_AND_CONTROLLER
  ALL_CLINICAL_FIELDS_PRESERVED: YES
  QUILL_INITIALIZATION: YES
  CSRF_PRESERVED: YES
  PATIENT_SCOPE_PRESERVED: YES
  REFERENCE_SCOPE_PRESERVED: YES
  NOTE_ONLY_SAVE_PRESERVED: YES
  RECIPE_OPTIONAL: YES
  LABORATORY_OPTIONAL: YES
  NEXT_APPOINTMENT_OPTIONAL: YES
  PHASE_07_TRANSACTION_PRESERVED: YES
  SAVE_ERROR_HANDLING: YES_STATIC
  SUCCESS_REDIRECT: LIST_WITH_SAVED_FLAG
  CANCEL_NAVIGATION: YES
  UNSAVED_CHANGES_GUARD: YES
  NOTE_DETAIL_ACCESS: EXISTING_ROUTE_PRESERVED
  DATATABLE_FUNCTIONS: YES_STATIC
  HISTORY_NO_DUPLICATE_ENTRIES: YES_QUERY_SCOPED
  INSTITUTIONAL_DESIGN: REUSED_EXISTING_DOCTOR_SHELL
  WHITE_BACK_BUTTON: EXISTING_SCOPED_STYLE_REUSED
  RESPONSIVE_STATIC: PASS_STATIC_ONLY
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  FOCUSED_TESTS: PASS_STATIC
  DIFF_CHECK: PASS_WITH_HISTORICAL_LINE_ENDING_WARNINGS
  BROWSER_VALIDATION: NOT_VERIFIED
  SAVE_RUNTIME_VALIDATION: NOT_VERIFIED
  FILES_MODIFIED: SEE_LIST_ABOVE
  DOCUMENT_CREATED: YES
  DATABASE_MODIFIED: NO
  SCHEMA_MODIFIED: NO
  CLINICAL_RECORDS_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS:
    - XAMPP/HTTP/MySQL del host Windows no son accesibles desde el entorno Codex.
    - Falta validación visual y funcional con sesión autorizada y datos sintéticos.
  RESIDUAL_RISKS:
    - El flujo de laboratorio continúa siendo una operación separada del guardado de la nota.
    - La descarga/PDF de receta sigue su contrato existente y no fue ejecutada.
  READY_FOR_OWNER_VISUAL_REVIEW: YES_STATIC_PENDING_BROWSER
  NEXT_RECOMMENDED_ACTION: Ejecutar pruebas HTTP en XAMPP con datos sintéticos; comprobar listado, /nueva, guardado note-only, receta opt-in, laboratorio opcional y cancelación.
```
