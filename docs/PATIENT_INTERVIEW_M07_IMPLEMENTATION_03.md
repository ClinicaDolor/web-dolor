# M07 — Implementación 03: cierre HTTP y regresión del modelo compartido

Fecha: 2026-10-14  
Proyecto: Clínica del Dolor  
Alcance: Entrevista Conversacional M07, guardia P04 y compatibilidad P09

## Conclusión

La compatibilidad del cambio PDO y la persistencia conversacional sintética
quedaron verificadas. La aceptación HTTP no pudo iniciarse porque P04 rechazó el
entorno por falta de dos credenciales sintéticas activas e independientes. No
se creó ninguna identidad alternativa ni se relajó la guardia.

El bloqueo es reproducible y específico; no es un fallo de las reglas T1–T5 ni
una razón para modificar el modelo compartido nuevamente.

## Compatibilidad de `MedicacionDolorModel`

El cambio efectivo es un constructor PDO opcional:

```php
new MedicacionDolorModel();       // llamadas tradicionales: conexión global
new MedicacionDolorModel($db);    // M07: PDO de la transacción del adaptador
```

La API pública existente se conserva. Las llamadas tradicionales encontradas en
controladores y vistas siguen usando el constructor sin argumentos. El modelo
no confirma ni revierte la transacción del adaptador conversacional: sus
operaciones de edición no abren una transacción propia y la finalización
tradicional mantiene su transacción local independiente. El adaptador M07 pasa
la misma PDO con la que abre, confirma o revierte el lote.

Evidencia:

- contrato M07: PASS, 21 aserciones y SQLite en memoria;
- integración MySQL sintética de Implementación 02: persistencia, rollback,
  readback, versionado y restauración focal PASS;
- contrato P09: PASS;
- mock de audio P09: PASS, sin afirmar reproducción audible real.

## Requisito exacto de P04

Para el modo `cross-clinic`, la guardia existente exige:

1. base explícita y allowlisted: `clinica_dolor_demo_20261010_final`;
2. dos IDs de paciente distintos proporcionados explícitamente;
3. ambos pacientes existentes y marcados como sintéticos;
4. clínicas distintas;
5. al menos una fila de acceso por paciente, activa y no expirada;
6. al menos dos valores `pin` distintos entre las credenciales activas;
7. al arrancar el servidor, `P04_TEST_APP_KEY` efímera de longitud mínima 32,
   inyectada sólo en el proceso temporal.

El último requisito de acceso se comprueba con:

```sql
COUNT(DISTINCT pin)
FROM pc_paciente_acceso
WHERE paciente_id IN (?, ?)
  AND estatus = 0
  AND fecha_expiracion > NOW()
```

No se imprimieron PIN, nombres de usuario, claves ni tokens.

## Resultado del preflight P04

Se verificó en solo lectura:

- base efectiva coincidente;
- host y puerto MySQL local;
- tablas requeridas;
- pacientes 9301 y 9302;
- marcadores sintéticos;
- clínicas distintas;
- filas de acceso sintéticas.

La única comprobación fallida fue:

`independent_active_access_credentials: false`

Por ello `start-server.ps1` no se ejecutó. La clave temporal, autenticación
HTTP, CSRF y las peticiones M07 no llegaron a probarse porque la guardia debe
pasar antes de iniciar el servidor.

## Regresión P09

Pasaron las pruebas estáticas de contrato y de audio mock. No se ejecutó el
CRUD HTTP P09 porque requiere el mismo servidor sintético y su preflight P04
falló. No se modificó el formulario tradicional, el catálogo, el esquema ni
ningún registro clínico.

## Estado solicitado

```text
PHASE: M07_HTTP_AND_SHARED_MODEL_COMPATIBILITY

SHARED_MODEL_PDO_COMPATIBILITY: PASS_STATIC_AND_SYNTHETIC_TRANSACTION
TRADITIONAL_P09_REGRESSION: PASS_STATIC_CONTRACT_AND_AUDIO_MOCK; HTTP_NOT_RUN
M07_TRANSACTION_INTEGRITY: PASS_SYNTHETIC_MYSQL

P04_REQUIRED_IDENTITIES: TWO_SYNTHETIC_PATIENTS_WITH_DISTINCT_ACTIVE_PINS_AND_DISTINCT_CLINICS
P04_AVAILABLE_SYNTHETIC_IDENTITIES: PATIENTS_AND_ACCESS_ROWS_PRESENT; DISTINCT_ACTIVE_CREDENTIALS_NOT_CONFIRMED
P04_GUARD_STATUS: BLOCKED_P04_IDENTITIES
P04_AUTHENTICATION: NOT_EXECUTED_GUARD_BLOCKED
P04_CSRF: NOT_EXECUTED_GUARD_BLOCKED
P04_TEST_KEY_CONSISTENCY: NOT_REACHED_SERVER_NOT_STARTED

M07_HTTP_READ: BLOCKED_P04_IDENTITIES
M07_HTTP_WRITE: BLOCKED_P04_IDENTITIES
M07_HTTP_READBACK: BLOCKED_P04_IDENTITIES
M07_HTTP_VERSION: BLOCKED_P04_IDENTITIES
M07_HTTP_IDEMPOTENCY: BLOCKED_P04_IDENTITIES
M07_HTTP_ROLLBACK: VERIFIED_DIRECT_MYSQL_NOT_HTTP
M07_CROSS_PATIENT_DENIAL: BLOCKED_P04_IDENTITIES
M07_CROSS_CLINIC_DENIAL: BLOCKED_P04_IDENTITIES

M07_PREVIOUS_NAVIGATION: PASS_STATIC_NO_WRITE
M07_NEXT_QUESTION: PASS_AFTER_CONFIRMED_READBACK_STATIC_AND_DIRECT_SYNTHETIC

TESTS_EXECUTED:
  - tests/patient-interview/m07-conversational-implementation-contract.php
  - tests/patient-interview/m07-mysql-integration.php (evidencia sintética de Implementación 02)
  - tests/p09/contract-check.mjs
  - tests/p09/audio-mock.mjs
  - tests/p04/verify-isolation.php en modo solo lectura
  - php -l de modelo, adaptador, vista, rutas y arneses

TESTS_PASSED:
  - compatibilidad PDO tradicional y conversacional
  - persistencia, rollback, versionado y readback MySQL sintéticos
  - contrato P09 y mock de audio
  - preflight P04 excepto credenciales independientes

TESTS_FAILED: NONE_IN_M07; P04_REJECTED_GUARD
TESTS_BLOCKED:
  - aceptación HTTP M07
  - CRUD HTTP P09
  - denegación HTTP cruzada
  - navegador real

FILES_MODIFIED: NONE_IN_IMPLEMENTATION_03; REPORT_CREATED_ONLY
DATABASE_RESTORATION: NOT_REQUIRED_THIS_TURN; PRIOR_FOCAL_SYNTHETIC_RESTORE_PASS
TEMPORARY_SERVERS_CLOSED: NONE_STARTED

BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
ENV_MODIFIED: NO
SCHEMA_MODIFIED: NO
COMMITS_CREATED: NO
DEPLOYMENTS: NO

M07_FINALIZATION_ENABLED: NO
REAL_BROWSER_ACCEPTANCE: PENDING

REMAINING_BLOCKERS:
  - Dos credenciales sintéticas activas, distintas y verificables para P04.
  - Reglas clínicas de completitud y Otro/Otros siguen pendientes según la
    Implementación 02.

OWNER_AUTHORIZATION_REQUIRED: YES — preparar o renovar las credenciales únicamente mediante el procedimiento P04 autorizado; no crear cuentas ni modificar fixtures sin autorización expresa.
FINAL_STATUS: BLOCKED_P04_IDENTITIES
```

No se declara aceptación HTTP, navegador ni producción completa.

---

## Addendum de cierre — 2026-10-14

Este addendum actualiza y supersede el bloqueo HTTP descrito en la sección
inicial del informe, conservando su trazabilidad histórica.

La autorización expresa del propietario permitió preparar temporalmente una
segunda credencial sintética mediante `ClinicaModel::insertPacientePin`, sin
modificar el autenticador ni el esquema. La primera identidad utilizó el
acceso sintético existente. Los PIN y tokens no se escribieron en archivos ni
se incluyeron en la evidencia.

El primer intento del arnés M07 reveló dos defectos del propio test, no de la
aplicación: el conteo de ítems no reconocía el HTML real y el readback asumía
un radio `value="No"`, aunque M07 representa la negativa como checkbox no
marcado con estado `complete`. Ambos fueron corregidos únicamente en
`tests/patient-interview/m07-p04-http-acceptance.php`. La ejecución final pasó
las comprobaciones HTTP.

La regresión P09 inicialmente encontró que el proceso CLI no exponía la clave
efímera en `$_ENV`; se repitió con `variables_order=EGPCS` y la misma `APP_KEY`
efímera del servidor. No se modificó `.env`.

### Resultado final

```text
PHASE: M07_P04_HTTP_ACCEPTANCE_CLOSURE

INITIAL_BRANCH: Silvino
INITIAL_HEAD: 59654e05dc9f931c21b5392238961bcddb3ab2e4
INITIAL_WORKTREE_STATUS: DIRTY_PRESERVED; NO_RESET_OR_CLEAN

SYNTHETIC_DATABASE_VERIFIED: PASS
SYNTHETIC_CLINIC_A: VERIFIED_SYNTHETIC
SYNTHETIC_CLINIC_B: VERIFIED_SYNTHETIC
SYNTHETIC_IDENTITY_A: VERIFIED_NO_SECRET_EXPOSURE
SYNTHETIC_IDENTITY_B: VERIFIED_TEMPORARY_NO_SECRET_EXPOSURE
DISTINCT_PINS_VERIFIED: PASS
ACTIVE_CREDENTIALS_VERIFIED: PASS

P04_GUARD: PASS
P04_AUTHENTICATION_A: PASS_HTTP_LOGIN
P04_AUTHENTICATION_B: PASS_HTTP_LOGIN
P04_CROSS_PATIENT_DENIAL: PASS_HTTP_403
P04_CROSS_CLINIC_DENIAL: PASS_HTTP_403
P04_CSRF: PASS_VALID_AND_INVALID_CASES
P04_TEST_KEY_CONSISTENCY: PASS_EPHEMERAL_SERVER_AND_RUNNER

M07_HTTP_CATALOG: PASS_6_CATEGORIES_46_ITEMS
M07_HTTP_READ: PASS_NO_WRITE
M07_HTTP_WRITE: PASS_EXPLICIT_T1
M07_HTTP_READBACK: PASS
M07_HTTP_ATOMICITY: PASS
M07_HTTP_ROLLBACK: PASS
M07_HTTP_VERSION: PASS_STALE_REJECTED
M07_HTTP_IDEMPOTENCY: PASS_DUPLICATE_REJECTED
M07_HTTP_NAVIGATION: PASS_STATIC_NO_WRITE_AND_READBACK_CONTINUITY
M07_HTTP_EDIT: PASS_AUTHORIZED_EDIT

TRADITIONAL_P09_HTTP_REGRESSION: PASS
SHARED_MODEL_PDO_COMPATIBILITY: PASS

M03_REGRESSION: NOT_RERUN; PRIOR_TEXT_ASSERTION_MISMATCH_REMAINS_SEPARATE
M04_REGRESSION: NOT_RERUN_IN_THIS_FOCAL_ACCEPTANCE
M05_REGRESSION: NOT_RERUN_IN_THIS_FOCAL_ACCEPTANCE
M08_REGRESSION: NOT_RERUN_IN_THIS_FOCAL_ACCEPTANCE
VOICE_MOCK_REGRESSION: PASS

FIXTURE_SNAPSHOT: PASS_FOCAL_HASHED_ACCESS_AND_M07_ROWS
FIXTURE_RESTORATION: PASS_HASH_VERIFIED
TEMPORARY_CREDENTIALS_REMOVED: PASS
TEMPORARY_SERVER_CLOSED: PASS_PORT_8109_FREE

TESTS_EXECUTED:
  - tests/p04/verify-isolation.php en modo cross-clinic, solo lectura
  - tests/patient-interview/m07-p04-http-acceptance.php
  - tests/p09/http-crud.php con APP_KEY efímera y variables_order=EGPCS
  - tests/patient-interview/m07-conversational-implementation-contract.php
  - tests/p09/contract-check.mjs
  - tests/p09/audio-mock.mjs
  - php -l del arnés HTTP M07
  - verificación final de puerto, accesos, respuestas y finalización

TESTS_PASSED:
  - P04 guard, autenticación A/B, aislamiento cruzado y CSRF
  - M07 HTTP lectura, captura, dependencias, readback, rollback, versionado,
    duplicados, edición y casos pendientes
  - P09 HTTP tradicional completo y cleanup PASS
  - restauración hash-verificada de accesos y respuestas sintéticas

TESTS_FAILED: NONE_FINAL
TESTS_BLOCKED:
  - navegador real; no formó parte de esta aceptación HTTP

FILES_MODIFIED:
  - tests/patient-interview/m07-p04-http-acceptance.php
  - docs/PATIENT_INTERVIEW_M07_IMPLEMENTATION_03.md

SCHEMA_MODIFIED: NO
TRADITIONAL_FORMS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
ENV_MODIFIED: NO
GEMINI_CALLS: 0
EXTERNAL_STT_CALLS: 0
COMMITS_CREATED: NO
DEPLOYMENTS: NO

M07_FINALIZATION_ENABLED: NO
REAL_BROWSER_ACCEPTANCE: PENDING

REMAINING_BLOCKERS:
  - Falta aceptación visual en navegador real; la aceptación HTTP no la
    sustituye.
  - Las decisiones clínicas de completitud T2/T3/T4/T5, Otro/Otros y resultados
    combinados siguen pendientes y no fueron alteradas.

FINAL_STATUS: PASS_HTTP_VERIFIED
RECOMMENDED_NEXT_ACTION: Ejecutar por separado la aceptación de navegador sintético, sin dejar credenciales temporales activas; resolver después las decisiones clínicas pendientes antes de habilitar finalización M07.
```
