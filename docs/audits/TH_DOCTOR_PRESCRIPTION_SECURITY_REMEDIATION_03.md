# TH-DOCTOR-PRESCRIPTION-SECURITY-REMEDIATION-03

## Remediación focalizada de seguridad y estabilidad

**Proyecto:** Clínica del Dolor  
**Área:** Doctor  
**Módulo:** Receta médica interna  
**Fecha:** 2026-10-15  
**Modalidad:** revisión y validación estática; sin HTTP, navegador, SQL ni escrituras clínicas

## Alcance

Se revisaron la auditoría `TH_DOCTOR_PRESCRIPTION_UX_AUDIT_01`, la implementación visual `TH_DOCTOR_PRESCRIPTION_UX_IMPLEMENTATION_02` y el flujo real de alta, listado y detalle de recetas internas.

Se preservaron la composición visual aprobada, el listado Simple-DataTables, el contador, el estado vacío, el formulario existente, la impresión/PDF y las autorizaciones existentes. No se implementaron modales.

## Correcciones aplicadas

### CSRF del alta interna

El endpoint `ClinicaController::pacienteInsertReceta()` no tenía una validación CSRF explícita. Ahora valida, antes de llamar a `ClinicaModel::insertPacienteReceta()`:

- `idPaciente` entero y positivo;
- token `X-M03-CSRF` (con compatibilidad de fallback JSON);
- token vinculado a la cookie autenticada y al paciente mediante `ClinicalSummarySecurity::validCaptureCsrf()`;
- autorización existente mediante `assertPatientAccess()`.

Las solicitudes sin token, con token inválido, con paciente inválido o sin autorización no llegan al modelo de persistencia.

### Detalle AJAX

`GET /buscar/receta/{idReceta}` continúa entregando el mismo bloque HTML para no alterar el contrato del detalle ni la impresión. El contenido clínico dinámico ahora se protege en `RecetaModel::getReceta()`:

- diagnóstico, signos vitales y atributos dinámicos se escapan como texto;
- el contenido de medicamento conserva el formato clínico compatible con Quill (`p`, `br`, énfasis y listas);
- una sanitización DOM con allowlist elimina scripts, estilos, elementos embebidos, atributos y manejadores de eventos;
- no se usa reemplazo por expresiones regulares ni se modifica el dato almacenado, el SQL o el PDF.

### Doble envío del formulario

`AgregarReceta()` incorpora una compuerta de envío en curso. Después de superar las validaciones locales, el botón se deshabilita, marca `aria-busy="true"` y muestra `Guardando…`. El estado se restaura en éxito o error, incluido error de red, y una segunda activación mientras la petición está pendiente se ignora.

Esto reduce el doble clic desde esta interfaz. No se añadió idempotencia de servidor, clave de solicitud, tabla ni índice; por tanto, reintentos concurrentes desde otros clientes o solicitudes repetidas a nivel HTTP siguen siendo un riesgo pendiente.

## Preservación y límites

- No se modificaron rutas, esquemas, consultas de persistencia ni datos.
- No se modificaron recetas externas, COFEPRIS, laboratorio, PDF ni la Entrevista Conversacional.
- No se modificaron reglas de autorización; se conserva la autorización por paciente y clínica ya existente.
- No se añadió protección CSRF al flujo PDF en esta intervención; el hallazgo independiente permanece abierto.
- La validación de rangos clínicos de signos vitales y la idempotencia real de servidor permanecen fuera de este alcance.

## Validaciones

- PHP lint: PASS en controlador, modelo, vista y prueba focal.
- Sintaxis JavaScript inline de la vista: PASS mediante `new Function` sobre los bloques inline.
- Prueba contractual aislada: PASS en `tests/doctor-prescription/security-contract.php`; verifica el orden de CSRF/autorización antes de la escritura y prueba el allowlist con un payload sintético malicioso.
- CSS: PASS por revisión; no se añadió CSS.
- `git diff --check`: PASS en los archivos de esta intervención.
- HTTP, navegador, consola, Network, autorización cruzada runtime, persistencia y PDF: NO VERIFICADO; no se ejecutaron por restricción de alcance y ausencia de sesión local reproducible desde Codex.

## Resultado formal

```yaml
TH_DOCTOR_PRESCRIPTION_SECURITY_REMEDIATION_03:
  STATUS: PARTIAL_STATIC_REMEDIATION_RUNTIME_VALIDATION_PENDING
  AUDIT_REVIEWED: PASS
  CREATE_FLOW_IDENTIFIED: PASS
  DETAIL_AJAX_IDENTIFIED: PASS
  CSRF_ROOT_CAUSE: explicit CSRF validation was absent from the internal create endpoint
  CSRF_IMPLEMENTED: PASS
  CSRF_SERVER_SIDE: PASS_STATIC
  CSRF_INVALID_REJECTED: PASS_STATIC
  AJAX_HTML_RISK_CONFIRMED: PASS
  AJAX_HTML_REMEDIATED: PASS_STATIC_DOM_ALLOWLIST
  CLINICAL_CONTENT_PRESERVED: PASS_STATIC_FOR_QUILL_FORMATTING
  UNSAFE_HTML_EXECUTION_PREVENTED: PASS_STATIC
  DOUBLE_SUBMIT_RISK_CONFIRMED: PASS
  FRONTEND_DOUBLE_SUBMIT_PROTECTION: PASS_STATIC
  BACKEND_IDEMPOTENCY: NOT_IMPLEMENTED
  DUPLICATE_CREATION_RISK: REDUCED_FRONTEND_ONLY
  PATIENT_AUTHORIZATION: PRESERVED_STATIC
  CLINIC_ISOLATION: PRESERVED_STATIC
  RESOURCE_AUTHORIZATION: PRESERVED_STATIC
  CREATE_FORM_PRESERVED: PASS_STATIC
  DETAIL_FLOW_PRESERVED: PASS_STATIC
  PRESCRIPTION_FIELDS_PRESERVED: PASS_STATIC
  PDF_GENERATION_PRESERVED: PASS_STATIC
  PDF_DOWNLOAD_PRESERVED: PASS_STATIC
  PRINT_FLOW_PRESERVED: PASS_STATIC
  VISUAL_DESIGN_PRESERVED: PASS_STATIC_NO_LAYOUT_OR_CSS_CHANGE
  DATATABLE_PRESERVED: PASS_STATIC
  DYNAMIC_COUNT_PRESERVED: PASS_STATIC
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  CSS_REVIEW: PASS_NO_CSS_CHANGE
  DIFF_CHECK: PASS
  SECURITY_STATIC_TESTS: PASS
  SECURITY_RUNTIME_TESTS: NOT_EXECUTED
  BROWSER_VALIDATION: NOT_EXECUTED
  DATABASE_MODIFIED: NO
  PRESCRIPTIONS_CREATED: NO
  SCHEMA_MODIFIED: NO
  FILES_MODIFIED: app/Controllers/ClinicaController.php; app/Models/RecetaModel.php; app/views/clinica/pacientes-recetas.php; tests/doctor-prescription/security-contract.php
  DOCUMENT_CREATED: docs/audits/TH_DOCTOR_PRESCRIPTION_SECURITY_REMEDIATION_03.md
  CREATE_MODAL_IMPLEMENTED: NO
  DETAIL_MODAL_IMPLEMENTED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS: runtime synthetic HTTP, cross-patient authorization, persistence, browser and network evidence remain pending
  RESIDUAL_RISKS: server-side idempotency, PDF CSRF, server-side clinical range validation, runtime authorization
  READY_FOR_MODAL_IMPLEMENTATION: CONDITIONAL_STATIC_ONLY_RUNTIME_GATE_PENDING
  NEXT_RECOMMENDED_ACTION: run isolated synthetic HTTP tests for CSRF, cross-patient access, duplicate requests and recovery before authorizing modal work
```

Los archivos ya contenían cambios locales previos de otras actividades; se conservaron y no se ejecutaron restauraciones, staging, commits ni despliegues.
