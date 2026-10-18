# TH-DOCTOR-SUBSEQUENT-NOTE-SAVE-FUNCTIONAL-REMEDIATION-07

## Alcance

Se corrigió exclusivamente el guardado de Nota subsecuente y el acoplamiento accidental con Receta médica. No se cambiaron rutas, esquema SQL, migraciones, PDF, Laboratorio independiente ni la arquitectura visual propuesta en la revisión 06.

La aplicación local no es accesible desde el entorno de Codex: `127.0.0.1:80`, `127.0.0.1:3306` y `clinica.test` no responden aquí. Por tanto, las pruebas HTTP, de persistencia, autorización negativa y rollback real permanecen pendientes.

## Causa raíz confirmada en código

Antes de la corrección, `AgregarNota()` enviaba un único payload al endpoint `POST /clinica/paciente/insert-nota-subsecuente`. `NotaSubsecuenteModel::insertNotaSubsecuente()` insertaba la nota y llamaba siempre a `ClinicaModel::insertPacienteReceta()`.

El contrato de receta además esperaba claves que el formulario no enviaba:

| Dato | Nota subsecuente | Receta médica |
|---|---|---|
| Frecuencia cardiaca | `frecCardiaca` | `fc` |
| Temperatura | no existía en el formulario | `temperatura` |

Con PDO en modo excepción, una inserción de receta incompleta podía provocar una respuesta JSON 500 genérica. La nota se insertaba antes de la receta y no existía una transacción, por lo que también había riesgo estático de escritura parcial.

## Corrección aplicada

### Nota independiente

La vista ahora incluye un control explícito `IncluirReceta`. Cuando está desmarcado:

1. Se valida el contexto de paciente, referencia y CSRF.
2. Se validan y persisten únicamente los campos propios de `nota_subsecuente`.
3. No se llama a `insertPacienteReceta()`.
4. La respuesta devuelve `idReceta = 0`.
5. No se abre impresión de receta.

La selección queda marcada automáticamente al editar una referencia que ya tiene receta, preservando el comportamiento de edición existente.

### Receta opcional

Cuando el Doctor selecciona la opción explícita para una receta nueva:

- Se requieren diagnóstico, medicamento, TA, FC, SpO2 y temperatura.
- El valor de `FrecCardiaca` se envía explícitamente como `fc`, porque ambos representan frecuencia cardiaca.
- La temperatura se solicita en un campo separado; no se inventa ni se deriva de otro dato.
- La receta se inserta sólo si todos los campos requeridos están presentes.
- En edición, una receta existente se actualiza sólo después de validar su pertenencia a paciente y referencia; sus signos ya almacenados no se sustituyen por valores de la Nota.
- Una receta nueva puede agregarse a una nota existente sólo mediante el opt-in.

No se generan recetas vacías ni medicamentos ficticios.

### Transacción y escrituras parciales

`insertNotaSubsecuente()` y `editarNotaSubsecuente()` usan la misma conexión PDO para envolver la nota y la receta solicitada en una transacción. Si la operación complementaria falla o no devuelve un identificador válido, se ejecuta rollback y se devuelve un mensaje seguro.

El controlador convierte los errores de modelo en estados 403, 422 o 500 controlados. No expone SQL, trazas ni valores clínicos internos.

La transacción está verificada estáticamente. El rollback real ante una falla de base no se ejecutó por falta de acceso a MySQL.

### Manejo frontend de errores

El `fetch()` ahora:

- Comprueba `response.ok`.
- Distingue autorización, validación, sesión y error interno mediante mensajes seguros.
- Rechaza respuestas que no sean JSON válido.
- No reintenta automáticamente escrituras inciertas.
- Mantiene la guardia contra doble clic.
- Sólo llama a `imprimirReceta()` cuando la respuesta devuelve un `idReceta` positivo.

El guardado exitoso continúa navegando al detalle de la nota. Guardar no se interpreta como finalización clínica.

## Campos preservados

Los campos propios de Nota subsecuente permanecen sin renombrar:

- TA.
- Frecuencia cardiaca.
- Pulso.
- SpO2.
- FiO2.
- ECOG.
- Karnovsky.
- Peso.
- Talla.
- Detalle/evolución.
- Próxima cita.
- Paciente y referencia autorizados.

Los campos exclusivos de Receta médica son diagnóstico, medicamento, `fc`, SpO2, TA y temperatura. `fc` sólo se recibe cuando se solicita receta; `temperatura` no se exige para una Nota independiente.

La próxima cita continúa siendo un campo de la nota. Su aceptación como vacío depende de la nulabilidad efectiva de la columna y requiere una comprobación de esquema en el entorno autorizado; no se modificó el esquema.

## Seguridad preservada

Se conservaron:

- `AuthMiddleware` y `assertPatientAccess()`.
- `ClinicalSummarySecurity::validCaptureCsrf()` y el header `X-M03-CSRF`.
- Comprobación de pertenencia de nota y receta por identificador, paciente y referencia.
- Consultas preparadas y filtros de paciente/referencia.
- Sanitización de contenido enriquecido existente.
- Escape de valores de formulario en la vista.

No se debilitó autenticación, autorización, aislamiento clínico ni CSRF.

## Revisión de posibles escrituras previas

No se ejecutaron SELECT contra la base local. No se puede confirmar si los intentos anteriores crearon notas o recetas parciales, ni limpiar registros, porque Codex no alcanza XAMPP y la instrucción prohíbe repetir escrituras sobre el paciente 310.

Antes de cualquier prueba HTTP, el propietario debe ejecutar consultas de sólo lectura para contar registros por paciente y referencia. No deben eliminarse registros existentes ni reutilizarse referencias de pruebas anteriores sin identificar inequívocamente un fixture sintético.

## Pruebas focalizadas

| Prueba | Estado estático | Estado HTTP/BD |
|---|---|---|
| TEST-01 Nota sin receta | PASS_STATIC | NO_VERIFICADO |
| TEST-02 Nota sin temperatura de receta | PASS_STATIC | NO_VERIFICADO |
| TEST-03 Receta con opt-in y campos completos | PASS_STATIC | NO_VERIFICADO |
| TEST-04 Receta incompleta | PASS_STATIC | NO_VERIFICADO |
| TEST-05 Referencia no autorizada | PASS_STATIC de guardas existentes | NO_VERIFICADO |
| TEST-06 CSRF ausente/incorrecto | PASS_STATIC de guardas existentes | NO_VERIFICADO |
| TEST-07 Doble clic | PASS_STATIC | NO_VERIFICADO |
| TEST-08 Falla de receta posterior | PASS_STATIC de transacción | NO_VERIFICADO |
| TEST-09 Respuesta HTTP de error | PASS_STATIC | NO_VERIFICADO |
| TEST-10 Regresión de Laboratorio/referencias | PASS_STATIC de alcance | NO_VERIFICADO |

Contrato ejecutado:

`tests/doctor-subsequent-note/functional-remediation-07.php`

Contratos de seguridad y alcance reejecutados:

- `tests/doctor-subsequent-note/security-contract-04.php`
- `tests/doctor-subsequent-note/reference-scope-contract-04c.php`

## Validación técnica

- PHP lint de controlador, modelo, vista y prueba: PASS.
- Sintaxis JavaScript inline: PASS_STATIC.
- Contrato funcional 07: PASS_STATIC.
- Contrato de seguridad 04: PASS_STATIC.
- Contrato de referencia 04C: PASS_STATIC.
- `git diff --check`: PASS; permanecen advertencias históricas de conversión LF/CRLF en otros archivos locales.
- Navegador: NO_VERIFICADO.
- HTTP runtime: NO_EJECUTADO.
- Persistencia real: NO_VERIFICADA.
- Base de datos: NO ACCEDIDA.

## Archivos modificados en esta intervención

- `app/Controllers/ClinicaController.php`
  - Contexto de receta para edición.
  - Opt-in de receta y normalización del contrato.
  - Estados de error controlados.
- `app/Models/NotaSubsecuenteModel.php`
  - Receta opcional.
  - Validación de campos de receta.
  - Transacciones y rollback.
  - Soporte para receta nueva sólo cuando se solicita.
- `app/views/clinica/pacientes-nota-subsecuente.php`
  - Selector accesible de receta.
  - Campo explícito de temperatura.
  - Payload `fc`/`temperatura` sólo para receta.
  - Manejo de respuestas HTTP y ausencia de impresión cuando no hay receta.
- `tests/doctor-subsequent-note/functional-remediation-07.php`
  - Contrato estático focalizado.
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_SAVE_FUNCTIONAL_REMEDIATION_07.md`

No se modificaron rutas, esquema, migraciones, PDFs, Laboratorio independiente ni módulos no relacionados.

## Resultado

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_SAVE_FUNCTIONAL_REMEDIATION_07:
  STATUS: IMPLEMENTED_STATIC_RUNTIME_PENDING
  ORIGINAL_FAILURE_ROOT_CAUSE: MANDATORY_RECIPE_COUPLING_AND_FC_TEMPERATURE_CONTRACT_MISMATCH
  HTTP_FAILURE_REPRODUCED: NO_RUNTIME_NOT_VERIFIED
  FAILING_ENDPOINT: POST /clinica/paciente/insert-nota-subsecuente
  MANDATORY_RECIPE_COUPLING_REMOVED: PASS_STATIC
  NOTE_ONLY_SAVE_SUPPORTED: PASS_STATIC
  RECIPE_EXPLICIT_OPT_IN: PASS_STATIC
  RECIPE_VALIDATION_PRESERVED: PASS_STATIC_FOR_NEW_RECIPE
  FRECCARDIACA_FC_CONTRACT: EXPLICIT_MAPPING_ONLY_WHEN_RECIPE_REQUESTED
  TEMPERATURE_HANDLING: SEPARATE_REQUIRED_FIELD_FOR_NEW_RECIPE; NOT_REQUIRED_FOR_NOTE_ONLY
  TRANSACTION_STRATEGY: SAME_PDO_TRANSACTION_FOR_NOTE_AND_REQUESTED_RECIPE
  PARTIAL_WRITES_PREVENTED: PASS_STATIC_DESIGN; RUNTIME_NOT_VERIFIED
  PREVIOUS_PARTIAL_WRITES_CHECKED: NO_DATABASE_ACCESS
  CSRF_PRESERVED: PASS_STATIC
  PATIENT_AUTHORIZATION_PRESERVED: PASS_STATIC
  REFERENCE_AUTHORIZATION_PRESERVED: PASS_STATIC
  CLINIC_ISOLATION_PRESERVED: PASS_STATIC_EXISTING_GUARDS
  HTML_SANITIZATION_PRESERVED: PASS_STATIC
  FORM_CONTRACT: NOTE_FIELDS_PRESERVED; RECIPE_OPT_IN_AND_TEMPERATURE_ADDED
  ERROR_HANDLING: PASS_STATIC_SAFE_STATUS_AWARE_JSON_HANDLING
  FRONTEND_DOUBLE_SUBMIT: PASS_STATIC
  BACKEND_IDEMPOTENCY: NOT_IMPLEMENTED; RETRY_AFTER_UNCERTAIN_NETWORK_REMAINS_A_RISK
  NOTE_ONLY_TEST: PASS_STATIC; HTTP_NOT_EXECUTED
  OPTIONAL_RECIPE_TEST: PASS_STATIC; HTTP_NOT_EXECUTED
  INVALID_RECIPE_TEST: PASS_STATIC; HTTP_NOT_EXECUTED
  CSRF_NEGATIVE_TEST: STATIC_GUARD_PASS; HTTP_NOT_EXECUTED
  REFERENCE_NEGATIVE_TEST: STATIC_GUARD_PASS; HTTP_NOT_EXECUTED
  TRANSACTION_ROLLBACK_TEST: STATIC_PATH_PASS; DATABASE_NOT_EXECUTED
  PHP_LINT: PASS
  JS_SYNTAX: PASS_STATIC
  FOCUSED_TESTS: PASS_STATIC
  DIFF_CHECK: PASS
  BROWSER_VALIDATION: NOT_EXECUTED
  HTTP_RUNTIME_VALIDATION: NOT_EXECUTED
  DATABASE_PERSISTENCE_VALIDATION: NOT_EXECUTED
  FILES_MODIFIED: CONTROLLER; MODEL; VIEW; STATIC_TEST; THIS_DOCUMENT
  DOCUMENT_CREATED: YES
  EXISTING_CLINICAL_RECORDS_MODIFIED: NO
  DATABASE_SCHEMA_MODIFIED: NO
  UNRELATED_MODULES_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS: XAMPP_UNREACHABLE_FROM_CODEX; PREVIOUS_PARTIAL_WRITES_AND_RUNTIME_PERSISTENCE_PENDING
  RESIDUAL_RISKS: SERVER_IDEMPOTENCY_KEY_NOT_IMPLEMENTED; NEXT_APPOINTMENT_NULLABILITY_UNVERIFIED; RUNTIME_AUTHORIZATION_PENDING
  READY_FOR_NEW_NOTE_VIEW: NO; REQUIRE_SYNTHETIC_RUNTIME_ACCEPTANCE_FIRST
  NEXT_RECOMMENDED_ACTION: RUN_READ_ONLY_PRECHECKS_THEN_TEST_NOTE_ONLY_AND_OPT_IN_RECIPE_WITH_SYNTHETIC_FIXTURES_IN_WINDOWS_XAMPP
```
