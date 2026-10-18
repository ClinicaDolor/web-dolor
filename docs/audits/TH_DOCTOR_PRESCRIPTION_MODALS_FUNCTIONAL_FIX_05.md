# TH-DOCTOR-PRESCRIPTION-MODALS-FUNCTIONAL-FIX-05

## Corrección focalizada de detalle y vista previa de recetas

**Proyecto:** Clínica del Dolor  
**Área:** Doctor  
**Vista:** `app/views/clinica/pacientes-recetas.php`  
**Fecha:** 2026-10-15  
**Modalidad:** corrección estática; runtime local no accesible desde Codex

## Síntomas e investigación

Se revisaron el informe de la implementación de modales, la vista D12, el modelo de recetas, `BusquedasController`, `PdfController`, las rutas y el componente compartido `modal-impresion-receta.php`.

### Detalle

La vista inicial registraba listeners directamente sobre las filas existentes después de inicializar Simple-DataTables. La tabla puede reconstruir esas filas al buscar, ordenar, paginar o actualizarse por AJAX; esos nodos pierden sus listeners. La vista tampoco tenía una delegación estable en el contenedor de la tabla.

La corrección conserva el ID real de cada fila, normaliza los handlers heredados de la respuesta AJAX y delega `click`/`Enter`/`Space` desde `#conteRecetas`. El flujo mantiene `GET /buscar/receta/{idReceta}`, la guarda de autorización del controlador y la sanitización allowlist existente en `RecetaModel::getReceta()`.

### Impresión

El componente compartido sólo abría un modal Bootstrap de selección de formato y después enviaba el formulario POST a una ventana nueva. No cargaba un documento como vista previa dentro de un modal. En esta vista se reemplazó su inclusión por un modal scoped que:

1. conserva las opciones de formato existentes;
2. solicita el PDF autorizado mediante el endpoint POST existente;
3. carga la respuesta PDF en un `iframe` mediante un `blob:` temporal en memoria;
4. permite cerrar sin imprimir;
5. ejecuta la impresión únicamente desde el botón explícito `Imprimir`;
6. restaura el detalle y el foco cuando la vista previa fue abierta desde él.

No se modificaron el controlador, el modelo, las rutas, las plantillas PDF, los permisos ni los datos.

## Validación ejecutada

- PHP lint de vista y contratos focales: PASS.
- Sintaxis de los dos bloques JavaScript inline: PASS.
- Contrato de modales actualizado para tres modales: PASS.
- Contrato funcional nuevo: PASS (`tests/doctor-prescription/functional-fix-05-contract.php`).
- Contrato de seguridad de la fase 03: PASS.
- `git diff --check` en vista, CSS y contratos: PASS.
- Verificación de puertos desde Codex: HTTP `127.0.0.1:80` FAIL; MySQL `127.0.0.1:3306` FAIL.
- Lectura controlada de recetas del paciente 310: NO VERIFICADA; la conexión PDO local fue rechazada por el aislamiento del entorno de ejecución.

No se ejecutaron HTTP, navegador, consola, Network, PDF, impresión, persistencia ni autorización runtime. No se crearon, modificaron ni eliminaron recetas.

```yaml
TH_DOCTOR_PRESCRIPTION_MODALS_FUNCTIONAL_FIX_05:
  STATUS: PARTIAL_STATIC_FIX_RUNTIME_VALIDATION_PENDING
  DETAIL_ROOT_CAUSE: direct row listeners can be lost when Simple-DataTables rebuilds rows; runtime symptom not reproducible from Codex
  PRINT_ROOT_CAUSE: previous shared Bootstrap modal selected format and submitted to a new window but did not provide an embedded document preview
  PATIENT_RECORD_COUNT_VERIFIED: NO_VERIFICADO_CODEX_LOCAL_SERVICES_UNAVAILABLE
  DETAIL_RECORD_AVAILABLE: NO_VERIFICADO
  DETAIL_CLICK_EVENT: PASS_STATIC_DELEGATED_ON_STABLE_TABLE_CONTAINER
  DETAIL_AJAX_REQUEST: PASS_STATIC_RECORD_ID_ENCODED
  DETAIL_AJAX_RESPONSE: PASS_STATIC_ORDER_GUARD_AND_CONTROLLED_ERROR
  DETAIL_DOM_SANITIZATION: PASS_STATIC_EXISTING_MODEL_ALLOWLIST_PRESERVED
  DETAIL_MODAL_VISIBLE: PASS_STATIC
  DETAIL_CORRECT_RECORD: PASS_STATIC_ROW_DATA_ID
  DETAIL_CLOSE_X: PASS_STATIC
  DETAIL_CLOSE_ESCAPE: PASS_STATIC
  PRINT_ACTION_IDENTIFIED: PASS_STATIC_imprimirReceta_and_existing_POST_PDF_route
  PRINT_MODAL_IMPLEMENTED: PASS_STATIC
  PRINT_MODAL_VISIBLE: PASS_STATIC_CUSTOM_SCOPED_DIALOG
  PRINT_PREVIEW_LOADED: NO_VERIFICADO_RUNTIME
  PRINT_CORRECT_RECORD: PASS_STATIC_SELECTED_ID_PROPAGATED
  PRINT_BUTTON_WORKS: PASS_STATIC_HANDLER_RUNTIME_PENDING
  PRINT_NO_AUTO_EXECUTION: PASS_STATIC_NO_PRINT_UNTIL_EXPLICIT_BUTTON
  CREATE_MODAL_PRESERVED: PASS_STATIC
  DETAIL_MODAL_PRESERVED: PASS_STATIC
  PRINT_MODAL_INDEPENDENT: PASS_STATIC
  MODAL_EVENT_CONFLICTS: PASS_STATIC_ONE_ACTIVE_FLOW_AND_RETURN_STATE
  MODAL_FOCUS_CONFLICTS: PASS_STATIC_FOCUS_TRAP_AND_RESTORE
  MODAL_BACKDROP_CONFLICTS: PASS_STATIC_SCOPED_OVERLAY
  TABLE_SEARCH_PRESERVED: PASS_STATIC
  TABLE_SORT_PRESERVED: PASS_STATIC
  TABLE_PAGINATION_PRESERVED: PASS_STATIC
  CSRF_PRESERVED: PASS_STATIC
  PATIENT_AUTHORIZATION_PRESERVED: PASS_STATIC_EXISTING_GUARDS_UNCHANGED
  CLINIC_ISOLATION_PRESERVED: PASS_STATIC_EXISTING_GUARDS_UNCHANGED
  DOM_ALLOWLIST_PRESERVED: PASS_STATIC
  PDF_TEMPLATE_PRESERVED: PASS_STATIC_UNMODIFIED
  BACKEND_IDEMPOTENCY: NOT_IMPLEMENTED
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  CSS_STATIC_REVIEW: PASS_STATIC_SCOPED
  FOCUSED_TESTS: PASS_STATIC
  DIFF_CHECK: PASS
  BROWSER_VALIDATION: NOT_VERIFIED
  HTTP_VALIDATION: NOT_VERIFIED
  PDF_PREVIEW_VALIDATION: NOT_VERIFIED
  DATABASE_MODIFIED: NO
  EXISTING_RECIPES_MODIFIED: NO
  PDF_FILES_MODIFIED: NO
  FILES_MODIFIED: app/views/clinica/pacientes-recetas.php; public/assets/css/app.css; tests/doctor-prescription/modals-contract.php; tests/doctor-prescription/functional-fix-05-contract.php; docs/audits/TH_DOCTOR_PRESCRIPTION_MODALS_FUNCTIONAL_FIX_05.md
  DOCUMENT_CREATED: PASS
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS: XAMPP HTTP/MySQL services are not reachable from Codex; patient 310 record count and real browser behavior remain unverified
  RESIDUAL_RISKS: server-side idempotency, PDF CSRF and runtime authorization remain outside this fix
  NEXT_MANUAL_VALIDATION: see procedure below
```

## Procedimiento manual exacto en Chrome/XAMPP

Ejecutar desde el Windows donde XAMPP esté activo, sin compartir tokens ni contraseñas:

1. Iniciar sesión Doctor con una cuenta de prueba autorizada y abrir `/clinica/receta/paciente/310`.
2. Confirmar primero el contador. Si muestra `0 registros`, registrar que no hay un detalle verificable y no inventar uno.
3. Con una receta existente y autorizada del mismo expediente, abrir DevTools: Console y Network.
4. Seleccionar una fila después de buscar, ordenar y paginar. Esperar el modal `Detalle de receta`; verificar que el contenido corresponda al ID de la fila y que Network muestre `GET /buscar/receta/{id}` con `200`.
5. Cerrar con X, Escape y teclado; confirmar que la tabla conserva su estado.
6. En el detalle, pulsar el icono de impresión. Debe abrirse `Vista previa de receta`, mostrar carga y luego el PDF de esa misma receta; no debe iniciar impresión automáticamente.
7. Pulsar `Imprimir` dentro de la vista previa, comprobar la impresión del documento y después cerrar. Si se abrió desde detalle, el detalle y el foco deben restaurarse.
8. Cambiar entre ambos formatos y confirmar que la vista previa se recarga sin reutilizar un documento anterior.
9. Simular una respuesta de documento no disponible sólo en un entorno sintético autorizado; debe aparecer un mensaje controlado, sin rutas internas ni trazas.
10. Confirmar ausencia de errores JavaScript, solicitudes a dominios externos y datos de otro paciente.

La validación runtime sólo puede declararse PASS después de observar efectivamente esos resultados en el navegador local.

Los cambios locales preexistentes fueron preservados. No se ejecutaron SQL destructivo, escrituras, migraciones, staging, commits, push ni despliegues.
