# M07 — Implementación conversacional 01

Fecha: 2026-10-14  
Proyecto: Clínica del Dolor  
Alcance: Entrevista Conversacional únicamente

Esta entrega implementa el núcleo conversacional seguro de M07/P09 sobre el
catálogo y la persistencia ya existentes. No cambia los formularios
tradicionales, el esquema, M06, Gemini ni el reconocimiento de voz.

## Decisiones preservadas

- Se leen las seis categorías oficiales y sus 46 medicamentos mediante las
  tablas P09 existentes, conservando IDs, textos y orden.
- La selección está agrupada por categoría. Seleccionar un medicamento no
  escribe; la escritura requiere el botón explícito de confirmación.
- Los medicamentos no seleccionados sólo se guardan como `No` después de una
  confirmación explícita por categoría. La ausencia de selección no se
  interpreta automáticamente como negativa.
- T1 conserva `Si`/`No`; T2 conserva texto de dosis; T3 conserva exactamente
  `Funciono`, `No funciono` y `Tuve efectos adversos`; T4 conserva `Si`/`No`;
  T5 sólo se muestra y guarda como descripción de efectos adversos.
- `no recuerdo` y respuestas equivalentes permanecen pendientes, sin escritura.
- Una respuesta que combine eficacia y efectos adversos permanece pendiente y
  devuelve `M07_COMBINED_OUTCOME_REVIEW`.
- `Otro/Otros` con respuesta afirmativa permanece bloqueado porque el contrato
  actual no tiene un destino aprobado para el nombre específico.
- No se habilita la finalización de M07 mientras sigan pendientes las reglas
  clínicas de completitud.

## Implementación

`app/Services/ConversationalM07Adapter.php` añade:

1. lectura con `LEFT JOIN`, sin llamar al inicializador histórico que crea filas
   vacías;
2. clasificación de estados pendiente, completo, conflicto, duplicado y
   bloqueado;
3. propuestas deterministas sin escritura;
4. validación de catálogo, paciente lógico, tema, dependencias, duplicados y
   versión esperada;
5. persistencia por el método canónico de `MedicacionDolorModel`, dentro de una
   única transacción para un lote confirmado;
6. rollback ante rechazo de cualquier operación y lectura posterior del estado.

`PatientInterviewController` añade lectura M07, interpretación M07 y el endpoint
autenticado `POST /historia-clinica/entrevista/capturar-m07`. La autorización de
paciente y clínica continúa pasando por `AuthMiddleware::assertPatientAccess`, y
CSRF continúa pasando por `ClinicalSummarySecurity::validCaptureCsrf`.

La vista de entrevista sustituye el marcador de M07 por la selección agrupada,
los campos T1–T5, la confirmación separada de negativos y el readback visual.
No se añadió una ruta pública ni una segunda pantalla.

## Evidencia ejecutada

La prueba focal `tests/patient-interview/m07-conversational-implementation-contract.php`
usa exclusivamente SQLite en memoria y no abre ninguna base del proyecto.

- 18 aserciones estáticas: PASS.
- Lectura de catálogo sintético sin crear filas: PASS.
- `no recuerdo` → propuesta pendiente, `can_confirm=false`: PASS.
- eficacia + efectos adversos → `M07_COMBINED_OUTCOME_REVIEW`, sin confirmación:
  PASS.
- `sí` → propuesta estructurada `Si`, confirmable: PASS.
- PHP lint de adaptador, controlador, vista y rutas: PASS.
- No se ejecutó una escritura clínica.

No se declaró una aceptación HTTP/DB porque el sandbox bloqueó la conexión al
MySQL local durante un contrato existente de M05 (`SQLSTATE[HY000] [2002]`), y
la guardia P04 vigente no autoriza usar la base de navegador sin su preflight
específico. No se debilitó esa guardia ni se inició otro servidor.

## Estado contractual

```yaml
PHASE: M07_CONVERSATIONAL_IMPLEMENTATION_01
INITIAL_BRANCH: Silvino
INITIAL_HEAD: 59654e05dc9f931c21b5392238961bcddb3ab2e4
INITIAL_WORKTREE_STATUS: DIRTY_PRESERVED_52_ENTRIES_AT_START

M07_CATALOG: READ_PATH_PRESERVES_P09
M07_6_CATEGORIES: VERIFIED_BY_PRIOR_P09_READ_ONLY_AUDIT
M07_46_MEDICATIONS: VERIFIED_BY_PRIOR_P09_READ_ONLY_AUDIT
M07_GROUPED_SELECTION: IMPLEMENTED
M07_EXPLICIT_NEGATIVES: IMPLEMENTED

M07_T1_CAPTURE: IMPLEMENTED_EXPLICIT_CONFIRMATION
M07_T2_CAPTURE: IMPLEMENTED_CANONICAL_FIELD
M07_T3_CAPTURE: IMPLEMENTED_OFFICIAL_VALUES_ONLY
M07_T4_CAPTURE: IMPLEMENTED_CANONICAL_FIELD
M07_T5_CAPTURE: IMPLEMENTED_ADVERSE_ONLY

M07_CANONICAL_PERSISTENCE: IMPLEMENTED_NOT_DB_EXECUTED_THIS_TURN
M07_READBACK: IMPLEMENTED
M07_ATOMICITY: IMPLEMENTED_TRANSACTION_AND_ROLLBACK_PATH
M07_IDEMPOTENCY: VERSION_GUARD_AND_CANONICAL_NO_CHANGE
M07_VERSION_PROTECTION: IMPLEMENTED
M07_CROSS_PATIENT_DENIAL: EXISTING_AUTH_MIDDLEWARE_PRESERVED
M07_CROSS_CLINIC_DENIAL: EXISTING_AUTH_MIDDLEWARE_PRESERVED

M07_PREVIOUS_QUESTION: GROUPED_REVISIT_AVAILABLE; SEQUENTIAL_CONTROL_PENDING
M07_EDITING: IMPLEMENTED_SERVER_VALIDATED
M07_DEPENDENCY_CLEARING: EXPLICIT_CONFIRMATION_REQUIRED
M07_DIRECT_SUBMIT: IMPLEMENTED_FOR_UNAMBIGUOUS_STRUCTURED_VALUES
M07_CLINICAL_REVIEW: PENDING_FOR_COMPLETENESS_OTHER_AND_COMBINED_OUTCOMES

UNKNOWN_DATA_HANDLING: PENDING_NO_WRITE
OTHER_MEDICATION_HANDLING: BLOCKED_NO_SCHEMA_OR_FIELD_INVENTED
COMBINED_OUTCOME_HANDLING: PENDING_NO_REDUCTION
M07_FINALIZATION_ENABLED: NO_UNTIL_COMPLETE_CONTRACT

VOICE_REGRESSION: NOT_RUN; VOICE CODE UNCHANGED
M03_REGRESSION: NOT_RUN_DB_BACKED
M04_REGRESSION: NOT_RUN_DB_BACKED
M05_REGRESSION: BLOCKED_LOCAL_MYSQL_SOCKET
M08_REGRESSION: NOT_RUN_DB_BACKED

FILES_MODIFIED:
  - app/Controllers/PatientInterviewController.php
  - app/Config/routes.php
  - app/views/paciente/entrevista.php
FILES_CREATED:
  - app/Services/ConversationalM07Adapter.php
  - tests/patient-interview/m07-conversational-implementation-contract.php
  - docs/PATIENT_INTERVIEW_M07_IMPLEMENTATION_01.md

TESTS_EXECUTED:
  - php -l en adaptador, controlador, vista y rutas
  - tests/patient-interview/m07-conversational-implementation-contract.php
  - inspección de estado Git sin limpiar cambios previos
TESTS_PASSED: FOCAL_STATIC_AND_IN_MEMORY
TESTS_FAILED: NONE
TESTS_BLOCKED: DB_BACKED_HTTP_AND_EXISTING_M05_CONTRACT_BY_LOCAL_SOCKET_SANDBOX

BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
ENV_MODIFIED: NO
SCHEMA_MODIFIED: NO
TRADITIONAL_FORMS_MODIFIED: NO
COMMITS_CREATED: NO
DEPLOYMENTS: NO

REMAINING_BLOCKERS:
  - No hay aceptación HTTP/DB de escritura M07 en esta ejecución.
  - Faltan reglas clínicas aprobadas de completitud para T2/T3/T4/T5.
  - Falta decidir el almacenamiento contractual de Otro/Otros.
  - Falta una decisión clínica para resultados combinados.
  - El control secuencial explícito anterior/siguiente queda para una entrega posterior.
FINAL_STATUS: PASS_SYNTHETIC_PARTIAL_IMPLEMENTATION
RECOMMENDED_NEXT_ACTION: Ejecutar preflight P04 y una prueba HTTP sintética autorizada de lectura, validación, transacción, rollback y readback antes de ampliar la cobertura.
```
