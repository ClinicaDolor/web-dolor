# TH-DOCTOR-SUBSEQUENT-NOTE-SECURITY-REMEDIATION-04

## Alcance y resultado

Esta intervención remedia de forma focalizada los controles de seguridad de Nota subsecuente y de las integraciones que la pantalla consume directamente: laboratorio y referencia de receta médica. El objetivo fue preservar el contrato clínico y corregir autorización por contexto, CSRF, consultas interpoladas y salida HTML no confiable sin modificar la presentación.

El resultado es **PARCIAL / LISTO PARA VALIDACIÓN ESTÁTICA**. La remediación de código está cubierta por contratos estáticos, pero la validación HTTP, de navegador y de persistencia requiere ejecutarse desde el entorno XAMPP autorizado con pacientes sintéticos.

## Autorización del responsable

- `OWNER_AUTHORIZATION: YES`
- Alcance autorizado: Nota subsecuente y sus integraciones reales.
- Exclusiones respetadas: Entrevista Conversacional, Portal Paciente, módulos no relacionados, recetas/PDF como flujo independiente, esquema y datos.
- No se realizaron commits, despliegues, migraciones ni escrituras clínicas.

Documentación previa revisada antes de la remediación:

- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_UX_AUDIT_01.md`
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_INTEGRATIONS_01.csv`
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_UX_IMPLEMENTATION_02.md`
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_UX_MODALS_AUDIT_03.md`
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_MODALS_MATRIX_03.csv`

`INITIAL_WORKTREE_REVIEWED: YES`, `AUDIT_03_REVIEWED: YES`, `MODALS_MATRIX_REVIEWED: YES`.

## Estado inicial y preservación

El worktree ya contenía numerosos cambios locales de otras actividades. Los archivos de aplicación alcanzados por esta remediación también estaban modificados previamente. Se preservaron esos cambios y no se ejecutaron `git reset`, `git clean`, staging ni restauraciones.

La revisión se limitó a los contratos de Nota subsecuente y a las rutas de laboratorio/receta que la pantalla utiliza. No se afirma que todo el diff de cada archivo corresponda a esta intervención.

## Rutas y operaciones verificadas

| Método | Ruta | Operación | Contexto |
|---|---|---|---|
| GET | `/nota-subsecuente/{idNota}` | Vista de una nota | Nota autorizada por paciente/clínica |
| GET | `/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}` | Alta/edición de nota | Paciente y clínica autorizados |
| POST | `/paciente/insert-nota-subsecuente` | Alta o actualización de nota y referencia de receta | CSRF + paciente/clínica + referencia |
| GET | `/buscar/nota-subsecuente/{idNota}` | Detalle de nota | Autorización derivada del registro |
| GET | `/buscar/tabla-notas-subsecuentes/{idPaciente}/{referencia}` | Tabla histórica | Paciente/clínica + referencia |
| GET | `/buscar/tabla-laboratorio/{idPaciente}/{referencia}` | Tabla de laboratorios embebida | Paciente/clínica + referencia |
| GET | `/buscar/laboratorio/{idLaboratorio}` | Detalle de laboratorio | Autorización derivada del registro |
| POST | `/laboratorio/insert-laboratorio` | Alta de laboratorio desde la pantalla | CSRF + paciente/clínica |
| POST | `/pdf/receta/{idReceta}` | PDF de receta, integración existente | Autorización propia del endpoint; runtime pendiente |

## Controles de autorización

`AuthMiddleware::assertPatientAccess()` continúa siendo la fuente del límite de paciente y clínica para el área Doctor. En la vista principal de Nota subsecuente la autorización se ejecuta antes de cargar paciente, nota o receta. Los endpoints de detalle de Nota y Laboratorio conservan la comprobación de acceso derivada del registro.

Para actualización, el paciente autorizado ya no depende de un `idPaciente` confiado sin contexto. El controlador valida la sesión/autorización y el modelo exige conjuntamente:

1. identificador de la Nota;
2. `id_paciente` autorizado;
3. `codigo_referencia` autorizado.

La referencia de receta se valida de forma equivalente cuando existe `idReceta` positivo. Los registros cruzados devuelven una respuesta controlada y no ejecutan el `UPDATE`.

## CSRF

El POST de Nota subsecuente ahora exige el token emitido para el paciente y lo acepta desde `X-M03-CSRF` o el campo contractual `csrf_token`. La vista expone el token en un atributo de datos escapado y el frontend lo envía en el encabezado.

El flujo de laboratorio ya tenía el mecanismo `ClinicalSummarySecurity::captureCsrfToken()` / `validCaptureCsrf()`; se conserva y se incluye en la matriz para validación runtime. La presencia estática del control no equivale a una prueba HTTP exitosa.

## XSS y salida HTML

Se corrigieron los puntos del flujo objetivo donde datos clínicos o de referencia podían entrar en HTML construido dinámicamente:

- Nota: contenido enriquecido sanitizado mediante `RecetaModel::sanitizeMedicationHtml()`.
- Nota: valores de signos vitales, fechas, diagnóstico, medicamento, títulos y URLs escapados según contexto.
- Laboratorio: título escapado, descripción sanitizada, ruta y nombre de archivo codificados/escapados.
- Rutas de edición: referencia codificada con `rawurlencode()` y atributos HTML escapados.
- Consultas de Nota, Laboratorio y referencia de Receta: parámetros preparados en lugar de interpolación.

El uso de `innerHTML` que permanece en la vista está respaldado por HTML producido por esos sanitizadores/escapadores; la inocuidad efectiva con payloads debe comprobarse en navegador/HTTP aislado.

## Idempotencia y doble envío

El frontend de Nota subsecuente incorpora una guarda de doble envío: mientras se procesa `AgregarNota()`, el botón se deshabilita y se marca `aria-busy`; el estado se restaura en `finally`.

Esto no demuestra idempotencia backend. No se añadió una clave de idempotencia ni se modificó el esquema, por lo que la protección contra reintentos duplicados en servidor permanece **NO VERIFICADA**. No se alteró la persistencia para inventar una solución fuera del alcance.

## Hallazgos y estado

### F-01 — CSRF ausente en alta/edición de Nota

- Estado inicial: vulnerabilidad confirmada por inspección estática.
- Corrección: token M03 validado antes de llamar al modelo.
- Estado actual: remediado estáticamente; prueba HTTP negativa/positiva pendiente.

### F-02 — UPDATE de Nota y Receta sin alcance completo

- Estado inicial: vulnerabilidad confirmada por consultas con `WHERE id=:id` sin paciente/referencia.
- Corrección: autorización del servidor y predicados conjuntos de registro, paciente y referencia; comprobación de pertenencia previa.
- Estado actual: remediado estáticamente; prueba cruzada con dos pacientes pendiente.

### F-03 — Interpolación SQL en rutas de Nota/Laboratorio/Receta

- Estado inicial: control parcial con interpolación de paciente/referencia.
- Corrección: consultas preparadas y validación/cast de identificadores.
- Estado actual: remediado estáticamente; prueba runtime pendiente.

### F-04 — HTML dinámico con datos clínicos sin contexto seguro

- Estado inicial: vulnerabilidad confirmada en salidas construidas para Nota y Laboratorio.
- Corrección: sanitización de rich text y escape contextual de texto, rutas, archivos y atributos.
- Estado actual: remediado estáticamente; payloads XSS sintéticos pendientes en navegador/HTTP.

### F-05 — Idempotencia backend

- Estado: control no demostrado.
- Corrección aplicada: sólo bloqueo de doble envío en frontend.
- Residual: un reintento HTTP o duplicado simultáneo aún requiere una decisión de backend/esquema independiente.

## Pruebas ejecutadas

| Prueba | Resultado | Evidencia |
|---|---|---|
| PHP lint de controladores, modelos, vista y contrato | PASS | `php -l` sin errores |
| Contrato estático focalizado | PASS_STATIC | `security-contract-04.php` |
| Sintaxis JS externo | PASS | `node --check` para app/main/loader/search |
| Sintaxis JS inline | PASS | 2 bloques de la vista Nota y 1 bloque de la vista de detalle |
| `git diff --check` | PASS | Sólo advertencias históricas de conversión LF/CRLF |
| HTTP con CSRF válido/inválido | NO VERIFICADO | Requiere XAMPP/BD autorizada |
| Autorización cruzada A/B | NO VERIFICADO | Requiere dos pacientes sintéticos |
| Persistencia y ausencia de UPDATE redundante | NO VERIFICADO | Requiere BD/HTTP |
| Payload XSS sintético renderizado | NO VERIFICADO | Requiere navegador/HTTP |
| PDF/descarga y autorización runtime | NO VERIFICADO | Fuera de ejecución segura local |
| Responsive/modal | NO VERIFICADO | No se modificó diseño ni se usó navegador |

## Regresiones y preservación clínica

- Preguntas, campos, rutas, referencias, validaciones y persistencia contractual: conservados.
- Diseño, CSS, modales y componentes visuales: no modificados por esta intervención.
- No se modificaron modelos de esquema, migraciones ni registros.
- No se ejecutaron POST, PDF, descargas ni consultas contra una base desde Codex.
- La navegación posterior a guardar sigue el contrato existente; debe verificarse con un caso sintético.

## Archivos alcanzados

Remediación de aplicación/pruebas:

- `app/Controllers/ClinicaController.php`
- `app/Models/NotaSubsecuenteModel.php`
- `app/Models/RecetaModel.php`
- `app/Models/LaboratorioModel.php`
- `app/views/clinica/pacientes-nota-subsecuente.php`
- `tests/doctor-subsequent-note/security-contract-04.php`

Documentación:

- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_SECURITY_REMEDIATION_04.md`
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_SECURITY_MATRIX_04.csv`

Los cinco archivos de aplicación ya tenían cambios locales; fueron preservados.

## Bloqueos y riesgos residuales

1. Falta ejecutar HTTP con dos pacientes sintéticos de clínicas independientes.
2. Falta comprobar CSRF positivo/negativo, registro inexistente, referencia manipulada y acceso cruzado.
3. Falta demostrar idempotencia backend; el frontend sólo reduce doble clic.
4. Falta validar runtime de sanitización, descarga de laboratorio y PDF de receta.
5. Falta validar que los llamadores legacy no envíen una referencia o receta incompatible en todos los escenarios.
6. La autorización general de documentos/PDF y otros módulos sigue fuera de este cierre.

## Procedimiento exacto siguiente

Desde PowerShell nativo del entorno XAMPP autorizado:

1. Ejecutar `php tests/doctor-subsequent-note/security-contract-04.php`.
2. Preparar dos pacientes sintéticos A/B, de clínicas independientes, sin reutilizar registros reales.
3. Ejecutar la suite HTTP focalizada existente o crear una ejecución temporal fuera del repositorio que no escriba sin fixture/rollback.
4. Probar Nota A válida, CSRF ausente/inválido, Nota B con IDs de A, referencia manipulada e ID inexistente.
5. Verificar directamente que los `UPDATE` cruzados afectan `0` filas y que la respuesta no revela el registro ajeno.
6. Repetir el flujo de detalle de Nota y Laboratorio con payloads sintéticos de escape/XSS.
7. Registrar las respuestas HTTP, filas afectadas y consola sin incluir credenciales, PIN, tokens ni datos clínicos.

## Estado final

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_SECURITY_REMEDIATION_04:
  STATUS: PARTIAL_STATIC_REMEDIATION_READY
  OWNER_AUTHORIZATION: YES
  INITIAL_WORKTREE_REVIEWED: YES
  AUDIT_03_REVIEWED: YES
  MODALS_MATRIX_REVIEWED: YES
  ENDPOINTS_INSPECTED: 9
  READ_OPERATIONS: NOTE_VIEW_DETAIL_HISTORY_LABORATORY_RECIPE_LOOKUP
  WRITE_OPERATIONS: NOTE_CREATE_NOTE_UPDATE_LABORATORY_UPLOAD
  DOCTOR_AUTHENTICATION: PASS_STATIC
  CLINIC_ISOLATION: PASS_STATIC_RUNTIME_PENDING
  PATIENT_AUTHORIZATION: PASS_STATIC_RUNTIME_PENDING
  NOTE_RECORD_AUTHORIZATION: PASS_STATIC_RUNTIME_PENDING
  REFERENCE_AUTHORIZATION: PASS_STATIC_RUNTIME_PENDING
  CSRF_NOTE_CREATE: PASS_STATIC
  CSRF_NOTE_UPDATE: PASS_STATIC
  CSRF_LABORATORY: CONTROL_PRESENT_STATIC_RUNTIME_PENDING
  CSRF_OTHER_WRITES: NOT_VERIFIED
  DYNAMIC_HTML_ESCAPE: PASS_TARGETED_STATIC_RUNTIME_PENDING
  RICH_TEXT_SANITIZATION: PASS_STATIC_RUNTIME_PENDING
  LABORATORY_SECURITY: PARTIAL_STATIC_RUNTIME_PENDING
  PRESCRIPTION_INTEGRATION_SECURITY: PARTIAL_STATIC_RUNTIME_PENDING
  DOUBLE_SUBMIT_FRONTEND: PASS_STATIC
  CONCURRENT_REQUEST_PROTECTION: NOT_VERIFIED
  AUTHORIZATION_STATIC: PASS
  CSRF_NOTE_POST_STATIC: PASS
  CSRF_LAB_POST_STATIC: PASS_PRESENT_RUNTIME_PENDING
  UPDATE_SCOPE_STATIC: PASS
  SQL_PARAMETERIZATION_STATIC: PASS_TARGETED
  XSS_OUTPUT_STATIC: PASS_TARGETED_RUNTIME_PENDING
  FRONTEND_DOUBLE_SUBMIT_GUARD: PASS
  BACKEND_IDEMPOTENCY: NOT_VERIFIED
  STATIC_TESTS: PASS
  SECURITY_FOCUSED_TESTS: PASS_STATIC
  INTEGRATION_REGRESSION: NOT_EXECUTED
  CROSS_CLINIC_TESTS: NOT_VERIFIED
  INVALID_RECORD_TESTS: NOT_VERIFIED
  INVALID_REFERENCE_TESTS: NOT_VERIFIED
  CSRF_NEGATIVE_TESTS: NOT_VERIFIED
  XSS_NEGATIVE_TESTS: NOT_VERIFIED
  VALID_USER_REGRESSION: NOT_VERIFIED
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  DIFF_CHECK: PASS
  HTTP_RUNTIME: NOT_EXECUTED
  BROWSER_RUNTIME: NOT_EXECUTED
  CROSS_CLINIC_TEST: NOT_VERIFIED
  NOTE_CREATE_MODAL_SECURITY_READY: PARTIAL_RUNTIME_PENDING
  NOTE_DETAIL_MODAL_SECURITY_READY: PARTIAL_RUNTIME_PENDING
  LABORATORY_CREATE_MODAL_SECURITY_READY: PARTIAL_RUNTIME_PENDING
  LABORATORY_DETAIL_MODAL_SECURITY_READY: PARTIAL_RUNTIME_PENDING
  MODAL_READINESS: PARTIAL_RUNTIME_PENDING
  FILES_MODIFIED: 6_APPLICATION_OR_TEST_FILES_PLUS_2_DOCS
  AUDIT_DOCUMENT: docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_SECURITY_REMEDIATION_04.md
  SECURITY_MATRIX: docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_SECURITY_MATRIX_04.csv
  DATABASE_SCHEMA_MODIFIED: NO
  DATABASE_RECORDS_MODIFIED: NO
  CLINICAL_LOGIC_MODIFIED: NO
  DESIGN_MODIFIED: NO
  PREEXISTING_LOCAL_CHANGES_PRESERVED: YES
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS: HTTP_XAMPP_BD_AND_TWO_SYNTHETIC_PATIENTS_REQUIRED
  RESIDUAL_RISKS: BACKEND_IDEMPOTENCY_DOCUMENT_AUTHORIZATION_RUNTIME_DOWNLOADS
  NEXT_ACTION: RUN_FOCUSED_HTTP_TESTS_IN_AUTHORIZED_XAMPP_ENVIRONMENT
```
