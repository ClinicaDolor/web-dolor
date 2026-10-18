# M07 — Implementación canónica compartida 01

Fecha: 2026-10-14
Proyecto: `C:\xampp\htdocs\clinica-dolor`
Alcance: servicio compartido opt-in y migración exclusivamente sintética.

## Resultado ejecutivo

Se creó la primera implementación aislada de `M07CanonicalService`, su DTO,
excepción de dominio, compuerta explícita y herramientas de migración/prueba.
La compuerta permanece cerrada por defecto y no se modificaron las rutas P09,
`ConversationalM07Adapter`, `MedicacionDolorModel`, formularios tradicionales,
Doctor ni `PatientInterviewController`.

La aceptación MySQL no pudo ejecutarse: el preflight de solo lectura no logró
conectarse a `clinica_dolor_demo_20261010_final` en el MySQL local y no se
observó un listener local en el puerto 3306. Por esa razón no se ejecutaron DDL,
backfill ni escrituras sintéticas. No se tocó `bd_clinica`.

## Decisiones implementadas

- Migración aditiva e idempotente, limitada a
  `clinica_dolor_demo_20261010_final`, con exactamente tres tablas nuevas:
  `pac_m07_medicamentos_no_catalogados`, `pac_m07_operaciones` y
  `pac_m07_revision_clinica`.
- Extensión de `pac_respuestas_paciente_modulo_7` con estados, ejes
  independientes de eficacia/tolerabilidad, versión y procedencia.
- Backfill determinista `LEGACY`: ausencia histórica no se convierte en
  `DOES_NOT_REMEMBER`; T3 no infiere tolerabilidad cuando sólo documenta
  eficacia, ni eficacia cuando sólo documenta efectos adversos.
- `UNANSWERED` sólo se puede guardar mediante el método explícito de borrador;
  no es un valor por defecto histórico ni está conectado a la UI.
- DTO unificado para entradas catalogadas y medicamentos no catalogados,
  máscara de campos, versión optimista, idempotencia por hash, transacción
  atómica, readback, operación append-only y bloqueo de revisión obsoleta.
- La procedencia es la fuente de la última operación de la entrada; la máscara
  y los hashes de operaciones conservan la trazabilidad técnica sin registrar
  el payload clínico completo.
- La finalización formal M07 permanece deshabilitada.

## Evidencia ejecutada

| Prueba | Resultado | Alcance |
|---|---|---|
| `m07-conversational-implementation-contract.php` | PASS | 21 aserciones; SQLite sintético existente; lectura sin escritura, pendientes y combinados |
| `tests/p09/contract-check.mjs` | PASS | contratos estáticos P09; no navegador ni persistencia |
| `m07-shared-canonical-contract.php` | PASS | 15 guards; clases, compuerta cerrada, allowlist, tablas y no activación de rutas |
| `php -l` | PASS | clases, migración y pruebas nuevas |
| `m07-shared-canonical-migration.php --mode=preflight` | BLOCKED | conexión MySQL local no disponible; 0 DDL |
| `m07-shared-canonical-integration.php` | BLOCKED | conexión MySQL sintética no disponible; 0 escrituras |

No se imprimieron credenciales, valores clínicos ni payloads.

## Reporte formal

```yaml
PHASE: M07_SHARED_CANONICAL_IMPLEMENTATION_01

SYNTHETIC_DATABASE_VERIFIED: BLOCKED_MYSQL_LOCAL_UNAVAILABLE
SCHEMA_ADDITIVE_MIGRATION: IMPLEMENTED_NOT_EXECUTED
MIGRATION_IDEMPOTENCY: IMPLEMENTED_NOT_EXECUTED
HISTORICAL_BACKFILL: IMPLEMENTED_NOT_EXECUTED
HISTORICAL_DATA_PRESERVATION: NOT_TESTED_NO_DATABASE

PARTIAL_CAPTURE_STATE: IMPLEMENTED_EXPLICIT_UNANSWERED_DRAFT_NOT_EXECUTED
OTHER_MEDICATION_ENTRY_KEY: IMPLEMENTED_NOT_EXECUTED
REVIEW_STALENESS_PROTECTION: IMPLEMENTED_NOT_EXECUTED
COMBINED_OUTCOME_PROJECTION: IMPLEMENTED_INDEPENDENT_AXES_NOT_EXECUTED

M07_CANONICAL_SERVICE: CREATED_OPT_IN_GATE_CLOSED_BY_DEFAULT
UNIFIED_DTO: PASS_STATIC
TRANSACTION_ATOMICITY: IMPLEMENTED_NOT_EXECUTED
VERSION_PROTECTION: PASS_STATIC_IMPLEMENTED_NOT_EXECUTED
IDEMPOTENCY: PASS_STATIC_IMPLEMENTED_NOT_EXECUTED
AUDIT_OPERATIONS: IMPLEMENTED_NOT_EXECUTED
CROSS_PATIENT_DENIAL: IMPLEMENTED_NOT_EXECUTED
CROSS_CLINIC_DENIAL: PASS_STATIC_IMPLEMENTED_NOT_EXECUTED

LEGACY_WRITE_PROTECTION: PASS_NO_CURRENT_ROUTE_ACTIVATION
TRADITIONAL_P09_REGRESSION: PASS_STATIC_CONTRACT
CONVERSATIONAL_M07_REGRESSION: PASS_EXISTING_CONTRACT

TESTS_EXECUTED:
  - m07-conversational-implementation-contract.php
  - tests/p09/contract-check.mjs
  - m07-shared-canonical-contract.php
  - php -l en archivos nuevos
  - preflight de migración sintética
  - preflight de integración MySQL sintética
TESTS_PASSED:
  - contratos existentes M07/P09
  - guards de activación y aislamiento
  - sintaxis PHP
TESTS_FAILED: []
TESTS_BLOCKED:
  - preflight y matriz MySQL por ausencia de conexión local en 3306

FILES_CREATED:
  - app/Services/M07CanonicalActivationGate.php
  - app/Services/M07CanonicalException.php
  - app/Services/M07CanonicalRecord.php
  - app/Services/M07CanonicalService.php
  - tests/patient-interview/m07-shared-canonical-contract.php
  - tests/patient-interview/m07-shared-canonical-migration.php
  - tests/patient-interview/m07-shared-canonical-integration.php
FILES_MODIFIED:
  - docs/PATIENT_INTERVIEW_M07_SHARED_CANONICAL_EXTENSION_PROPOSAL.md

SYNTHETIC_FIXTURE_RESTORATION: NOT_REQUIRED_NO_WRITES_EXECUTED
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
ENV_MODIFIED: NO
PRODUCTION_MODIFIED: NO
COMMITS_CREATED: NO
DEPLOYMENTS: NO

NEW_CLINICAL_WRITES_ENABLED: NO
M07_FINALIZATION_ENABLED: NO
CLINICAL_APPROVAL_PENDING: YES
BROWSER_ACCEPTANCE: PENDING

FINAL_STATUS: BLOCKED_MYSQL_SYNTHETIC_PREFLIGHT
REMAINING_BLOCKERS:
  - iniciar o verificar el MySQL local sólo cuando el propietario lo autorice;
    después ejecutar el preflight contra la base allowlisted
  - aplicar la migración y ejecutar la matriz real con snapshot/restauración focal
  - resolver posteriormente la activación clínica y la transición P09

```

## Addendum — diagnóstico postmigración y reanudación segura

Fecha: 2026-10-14

Se revisaron los runners, los arneses M07/P04 y los informes previos sin
ejecutar DDL, backfill, `--mode=apply`, restauraciones de esquema ni pruebas
con escrituras. No existe en el directorio temporal local un artefacto
`m07-*` que permita reconstruir una ejecución posterior del runner de
migración, y el informe anterior no contiene una salida del apply compartido.
Por tanto, no se atribuye una primera falla posterior a la migración sin
evidencia reproducible.

El PASS documentado en `PATIENT_INTERVIEW_M07_IMPLEMENTATION_03.md` pertenece
a la aceptación HTTP M07/P09 anterior, con su propio snapshot y restauración;
no demuestra que la migración canónica compartida ni su backfill hayan sido
ejecutados. El último bloqueo verificable de esta implementación compartida
continúa siendo el preflight PDO sin conexión desde el contexto Low Integrity.

Se preparó un validador read-only y un runner separado de `validate-existing`.
El runner no invoca `--mode=apply`, no ejecuta DDL ni backfill y no puede
continuar con la matriz sintética si el esquema, el backfill o la integridad
histórica no pasan primero. Las pruebas canónicas que sí modifican filas del
fixture sólo se iniciarían con `-AllowSyntheticWrites`, snapshot focal y
restauración hash-verificada; el runner no fue ejecutado desde Codex.

```yaml
PHASE: M07_POST_MIGRATION_DIAGNOSIS

MYSQL_CONNECTION: NOT_VERIFIED_FROM_CODEX_LOW_INTEGRITY
SYNTHETIC_DATABASE_VERIFIED: NOT_VERIFIED_POSTMIGRATION
MIGRATION_CURRENT_STATE: NOT_VERIFIED_NO_APPLY_OUTPUT
SCHEMA_INTEGRITY: NOT_VERIFIED_MYSQL_ACCESS_REQUIRED
BACKFILL_CURRENT_STATE: NOT_VERIFIED_MYSQL_ACCESS_REQUIRED
HISTORICAL_DATA_INTEGRITY: NOT_VERIFIED_MYSQL_ACCESS_REQUIRED
MIGRATION_SNAPSHOT_AVAILABLE: NOT_FOUND_IN_LOCAL_TEMP_ARTIFACTS

FIRST_FAILED_STEP: NOT_VERIFIED_NO_POSTMIGRATION_RUN_LOG
FIRST_ERROR_MESSAGE_SANITIZED: NOT_VERIFIED
FIRST_ERROR_EXIT_CODE: NOT_VERIFIED
ROOT_CAUSE: PRIOR_KNOWN_BLOCK_PDO_CONNECTION_FROM_LOW_INTEGRITY; POSTMIGRATION_FAILURE_NOT_PROVABLE

CANONICAL_SERVICE_TESTS_STARTED: NOT_VERIFIED
CANONICAL_SERVICE_TESTS_RESULT: NOT_REACHED_OR_NO_OUTPUT
TRADITIONAL_P09_TESTS_STARTED: NOT_VERIFIED
CONVERSATIONAL_M07_TESTS_STARTED: NOT_VERIFIED
PDO_REGRESSION_STARTED: NOT_VERIFIED

CODE_CORRECTION_REQUIRED: YES_DIAGNOSTIC_AND_READ_ONLY_VALIDATION_ONLY
CODE_CORRECTIONS_APPLIED:
  - tests/patient-interview/m07-shared-canonical-validate-existing.php
  - tests/patient-interview/run-m07-validate-existing-medium.ps1
DATABASE_CORRECTION_REQUIRED: NOT_VERIFIED; NO_CORRECTION_PROPOSED
VALIDATE_EXISTING_RUNNER: PREPARED_NOT_EXECUTED
RUNNER_STATIC_VALIDATION: PASS_PHP_LINT_PS_PARSE_DIFF_CHECK_SECURITY_SCAN
MANUAL_POWERSHELL_COMMAND: '& .\tests\patient-interview\run-m07-validate-existing-medium.ps1 -Mode ValidateExisting'
READ_ONLY_POWERSHELL_COMMAND: '& .\tests\patient-interview\run-m07-validate-existing-medium.ps1 -Mode ValidateExisting'
MATRIX_POWERSHELL_COMMAND: '& .\tests\patient-interview\run-m07-validate-existing-medium.ps1 -Mode ValidateExisting -AllowSyntheticWrites'

DDL_EXECUTED: NO
BACKFILL_EXECUTED: NO
DATABASE_WRITES_BY_CODEX: NO
BD_CLINICA_MODIFIED: NO
ENV_MODIFIED: NO
COMMITS_CREATED: NO
NEW_CLINICAL_WRITES_ENABLED: NO
M07_FINALIZATION_ENABLED: NO

FINAL_STATUS: PARTIAL_WITH_BLOCKERS
REMAINING_BLOCKERS:
  - ejecutar desde PowerShell normal de Windows el validador read-only contra la
    base allowlisted y conservar su JSON de salida sin datos clínicos
  - sólo si ese validador devuelve PASS_READ_ONLY_EXISTING_SCHEMA, autorizar por
    separado la matriz sintética con -AllowSyntheticWrites
  - no aplicar DDL/backfill ni usar --mode=restore con la evidencia actual
```

El validador y el runner fueron comprobados estáticamente. La conexión MySQL,
el estado real de las tablas y cualquier resultado postmigración permanecen
`NOT_VERIFIED` hasta ejecutarse desde un proceso Windows con conectividad
efectiva; no se imprimen PIN, secretos, nombres clínicos ni payloads.

## Siguiente acción segura

Cuando `clinica_dolor_demo_20261010_final` esté disponible en el MySQL local,
ejecutar primero, sin escribir:

```powershell
Set-Location C:\xampp\htdocs\clinica-dolor
php tests\patient-interview\m07-shared-canonical-migration.php `
  --mode=preflight `
  --database=clinica_dolor_demo_20261010_final `
  --patient-a=9301 `
  --patient-b=9302
```

Sólo si el resultado es `PASS_READ_ONLY_PREFLIGHT` y existe autorización para
escribir en el fixture sintético, se puede aplicar la migración con
`--mode=apply --allow-synthetic-write`. La aplicación habitual permanecerá
deshabilitada porque ninguna ruta actual utiliza la compuerta nueva.

## Addendum — diagnóstico de conexión MySQL

Fecha: 2026-10-14

Se revisó la instalación local de XAMPP sin modificarla:

```yaml
MYSQL_SERVER_STATUS: STOPPED_OR_NOT_RUNNING_VERIFIED
MYSQL_ACTUAL_PORT: 3306
MYSQL_CONNECTION_VERIFIED: NO
SYNTHETIC_DATABASE_VERIFIED: BLOCKED_BEFORE_CONNECTION
P04_GUARD: BLOCKED_BEFORE_PDO_CONNECTION
M07_MIGRATION: NOT_EXECUTED
M07_BACKFILL: NOT_EXECUTED
M07_HISTORICAL_PRESERVATION: NOT_TESTED
M07_CANONICAL_SERVICE: NOT_EXECUTED_AGAINST_MYSQL
M07_DTO: PASS_STATIC
M07_VERSIONING: NOT_EXECUTED_AGAINST_MYSQL
M07_IDEMPOTENCY: NOT_EXECUTED_AGAINST_MYSQL
M07_TRANSACTION: NOT_EXECUTED_AGAINST_MYSQL
M07_ROLLBACK: NOT_EXECUTED_AGAINST_MYSQL
M07_AUDIT: NOT_EXECUTED_AGAINST_MYSQL
M07_REVIEW_STALENESS: NOT_EXECUTED_AGAINST_MYSQL
M07_CROSS_PATIENT_DENIAL: NOT_EXECUTED_AGAINST_MYSQL
M07_CROSS_CLINIC_DENIAL: NOT_EXECUTED_AGAINST_MYSQL
M07_LEGACY_WRITE_PROTECTION: PASS_STATIC

TESTS_EXECUTED:
  - inspección de C:\xampp\mysql\bin\my.ini
  - comprobación de procesos mysqld/mariadbd
  - comprobación de listeners TCP locales 3306–3310
  - preflight de migración M07
  - preflight de integración M07
TESTS_PASSED:
  - my.ini declara port=3306 y datadir local de XAMPP
  - los arneses apuntan al puerto 3306 y no tienen fallback a bd_clinica
TESTS_FAILED: []
TESTS_BLOCKED:
  - conexión PDO al MySQL sintético, porque mysqld no está activo

SYNTHETIC_DATA_INTEGRITY: UNCHANGED_NO_CONNECTION_NO_WRITES
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
ENV_MODIFIED: NO
COMMITS_CREATED: NO
DEPLOYMENTS: NO

NEW_CLINICAL_WRITES_ENABLED: NO
M07_FINALIZATION_ENABLED: NO
FINAL_STATUS: BLOCKED_MYSQL_START_REQUIRES_OWNER_AUTHORIZATION
REMAINING_BLOCKERS:
  - autorización explícita para iniciar temporalmente el MySQL local de XAMPP;
  - después, repetir el preflight antes de cualquier DDL.

## Addendum — intento autorizado de arranque XAMPP

Fecha: 2026-10-14

Se recibió autorización expresa para iniciar temporalmente el servidor local.
El proceso `mysqld.exe` de esta ejecución inició con PID temporal `10880`, pero
MariaDB 10.4.32 abortó normalmente antes de aceptar conexiones:

```yaml
MYSQL_START_ATTEMPT: EXECUTED
MYSQL_START_RESULT: ABORTED
MYSQL_FAILURE: INNODB_IBDATA1_NOT_WRITABLE
MYSQL_CONNECTION_VERIFIED: NO
MYSQL_ACTUAL_PORT: 3306
P04_GUARD: NOT_REACHED
M07_MIGRATION: NOT_EXECUTED
M07_BACKFILL: NOT_EXECUTED
M07_CANONICAL_SERVICE: NOT_EXECUTED_AGAINST_MYSQL
SYNTHETIC_DATA_INTEGRITY: UNCHANGED_NO_CONNECTION_NO_WRITES
TEMPORARY_MYSQL_PROCESS_REMAINING: NO
TEMPORARY_MYSQL_LISTENER_REMAINING: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
ENV_MODIFIED: NO
COMMITS_CREATED: NO
DEPLOYMENTS: NO
FINAL_STATUS: BLOCKED_INNODB_DATAFILE_NOT_WRITABLE
REMAINING_BLOCKERS:
  - corregir permisos de escritura de la instalación local de XAMPP o ejecutar
    MySQL con la cuenta/configuración autorizada por el propietario;
  - no se debe cambiar `my.ini`, reparar InnoDB ni forzar recuperación sin una
    autorización nueva y específica.
```

No se detuvo ningún proceso ajeno. El proceso iniciado para esta prueba terminó
por sí mismo y no quedó listener en `3306`. Los archivos temporales de diagnóstico
de esta ejecución fueron retirados.

## Addendum — auditoría focal de permisos InnoDB

Fecha: 2026-10-14

```yaml
INNODB_FILE_EXISTS: YES
INNODB_FILE_ATTRIBUTES: ARCHIVE; READONLY_FALSE
INNODB_FILE_PERMISSIONS: ACL_MODIFY_FOR_AUTHENTICATED_USERS
DATA_DIRECTORY_PERMISSIONS: ACL_MODIFY_FOR_AUTHENTICATED_USERS
MYSQL_EXECUTION_IDENTITY: PC\silvino; NON_ADMIN; LOW_MANDATORY_INTEGRITY
FILE_LOCK_STATUS: NOT_VERIFIED_OPENFILES_NOT_ENABLED_HANDLE_TOOL_UNAVAILABLE
AVAILABLE_DISK_SPACE: APPROX_634.67_GB_ON_C
WINDOWS_PROTECTION_STATUS: NOT_VERIFIED_DEFENDER_QUERIES_DENIED

ROOT_CAUSE: LOW_INTEGRITY_EXECUTION_TOKEN_CANNOT_WRITE_TO_NORMAL_INNODB_DATA
EVIDENCE:
  - ibdata1 existe, mide aproximadamente 76 MB y no tiene ReadOnly
  - ibdata1 y data conceden Modify a Authenticated Users
  - el volumen C tiene aproximadamente 634.67 GB libres
  - whoami /all reporta Mandatory Label\Low Mandatory Level
  - mysqld abortó con InnoDB ibdata1 must be writable
  - no hay servicio mysql/MariaDB registrado con los nombres comunes revisados
  - el historial mysql_error.log muestra arranques InnoDB exitosos anteriores
  - los demás archivos InnoDB/Aria tampoco tienen ReadOnly
MINIMUM_CORRECTION_PROPOSED: iniciar mysqld desde un proceso Windows de integridad normal o elevada, con la cuenta autorizada y el my.ini existente; no cambiar ACL inicialmente
AFFECTED_FILES: ibdata1 reportado por el arranque; sin evidencia de atributos bloqueados en los demás archivos
RISK_TO_EXISTING_DATABASES: STARTUP_BLOCKED; NO_REPAIR_OR_DATA_WRITE_EXECUTED; NO_EVIDENCE_OF_CORRUPTION_FROM_THIS_AUDIT
OWNER_AUTHORIZATION_REQUIRED: YES_FOR_NON_LOW_INTEGRITY_START_OR_ANY_PERMISSION_SECURITY_CHANGE

CHANGES_EXECUTED: NO
MYSQL_STARTED: NO
DATABASE_MODIFIED: NO
FINAL_STATUS: ROOT_CAUSE_IDENTIFIED_LOW_INTEGRITY_TOKEN
```

La ACL permite escritura lógica, pero el proceso actual se ejecuta con nivel de
integridad bajo y no puede escribir hacia objetos de integridad normal. No se
encontró una etiqueta de integridad explícita en el descriptor SDDL del archivo;
la ausencia de una concesión de escritura de baja integridad es coherente con
el bloqueo observado. El estado exacto de antivirus/Controlled Folder Access y
los locks no pudo verificarse sin privilegios administrativos, por lo que no se
atribuyen a ellos la causa raíz.

## Addendum — diagnóstico del fallo funcional real del proceso hijo

Fecha: 2026-10-14

La conexión PDO principal y la comprobación focal secundaria ya habían pasado. La
inspección del código confirmó que el último mensaje `MySQL local no disponible`
no identificaba de forma fiable la causa funcional: el clasificador del runner
buscaba la palabra genérica `database`, presente siempre en el JSON del hijo, y
el hijo sustituía cualquier excepción por un mensaje genérico. Por ello no se
declara un defecto de `M07CanonicalService` con base únicamente en el `exit 1`.

```yaml
PHASE: M07_CANONICAL_CHILD_FAILURE_DIAGNOSIS
ROOT_CAUSE: REPORTING_MASKED_CHILD_FAILURE; NO_SERVICE_ROOT_CAUSE_CONFIRMED
LAST_COMPLETED_OPERATION: NOT_VERIFIED_BY_NEW_MANUAL_RETRY
FIRST_FAILED_OPERATION: NOT_VERIFIED_BY_NEW_MANUAL_RETRY
FIRST_FAILED_TEST: NOT_VERIFIED_BY_NEW_MANUAL_RETRY
FIRST_ERROR_CLASS: NOT_VERIFIED_BY_NEW_MANUAL_RETRY
FIRST_ERROR_SQLSTATE: NOT_VERIFIED_BY_NEW_MANUAL_RETRY
FIRST_ERROR_MESSAGE_SANITIZED: NOT_VERIFIED_BY_NEW_MANUAL_RETRY
CHILD_EXIT_CODE: 1_PREVIOUS_RUN

SERVICE_BUG_CONFIRMED: NO
HARNESS_BUG_CONFIRMED: YES_REPORTING_AND_DIAGNOSTICS
CORRECTION_APPLIED: YES
ERROR_REPORTING_VERIFIED: STATIC_ONLY

CANONICAL_GATE_STATUS: CLOSED_BY_DEFAULT; SYNTHETIC_HARNESS_OPT_IN_PRESERVED
P04_PROTECTION_PRESERVED: YES
SNAPSHOT_PROTECTION_PRESERVED: YES
RESTORATION_PROTECTION_PRESERVED: YES

STATIC_VALIDATIONS:
  POWERSHELL_5_1_PARSE: PASS
  PHP_LINT: PASS
  M07_STATIC_CONTRACT: PASS
  M07_CONVERSATIONAL_CONTRACT: PASS
  DIFF_CHECK: PASS

DATABASE_WRITES_BY_CODEX: NO
DDL_EXECUTED: NO
BACKFILL_EXECUTED: NO
BD_CLINICA_MODIFIED: NO
ENV_MODIFIED: NO
COMMITS_CREATED: NO

MANUAL_RETRY_REQUIRED: YES_SINGLE_RETRY
FINAL_STATUS: READY_FOR_SINGLE_MANUAL_INSTRUMENTED_RETRY
```

El hijo ahora reporta únicamente información sanitizada: operación y prueba
fallida, clase de excepción, SQLSTATE, estado de conexión, pruebas alcanzadas y
restauración. No reporta PIN, credenciales, hashes ni valores clínicos. La
restauración clínica continúa en `finally` y la restauración de credenciales
continúa siendo responsabilidad del runner P04.

Comando para la única reejecución manual desde PowerShell normal con MySQL
activo e integridad Medium:

```powershell
Set-Location 'C:\xampp\htdocs\clinica-dolor'
& .\tests\patient-interview\run-m07-validate-existing-medium.ps1 `
  -Mode ValidateExisting `
  -AllowSyntheticWrites
```

La salida `FIRST_FAILED_OPERATION`, `FIRST_FAILED_TEST` y
`FIRST_ERROR_MESSAGE_SANITIZED` de esa ejecución será la evidencia funcional.
No se ejecutó desde Codex Low Integrity, ni se repitió la matriz clínica durante
esta corrección.

## Addendum — preservación de la excepción PDO original

Fecha: 2026-10-14

La inspección focal confirmó que `M07CanonicalService` ya conserva la excepción
inferior mediante el argumento `previous` de `M07CanonicalException`; por tanto,
no se confirmó un defecto del servicio. La pérdida de información estaba en el
proceso hijo: `sanitizeFailure()` convertía la excepción encadenada en el texto
genérico `causa PDO sanitizada`, y el runner la registraba como
`ChildProcessExit`. Ese diagnóstico no permitía identificar la operación SQL ni
distinguir una incompatibilidad de esquema de otro fallo funcional.

El cambio queda limitado a los arneses. El proceso hijo propaga ahora, sin
mensajes SQL ni valores clínicos, la operación y prueba activa, la clase del
wrapper y la clase original, SQLSTATE, código del driver, y únicamente nombres
de tabla o constraint que pasen una allowlist de identificadores. El runner
conserva esos campos en su reporte. No se expone PDO DSN, contraseña, PIN,
hashes, consultas ni valores clínicos.

```yaml
PHASE: M07_ORIGINAL_PDO_EXCEPTION_ANALYSIS
LAST_COMPLETED_CHECKPOINT: NOT_VERIFIED_BY_NEW_MANUAL_RETRY
FIRST_FAILED_STEP: NOT_VERIFIED_BY_NEW_MANUAL_RETRY
FIRST_FAILED_SUBSTEP: NOT_VERIFIED_BY_NEW_MANUAL_RETRY
FIRST_FAILED_SCRIPT: NOT_VERIFIED_BY_NEW_MANUAL_RETRY
FIRST_FAILED_LINE: NOT_VERIFIED_BY_NEW_MANUAL_RETRY
FIRST_ERROR_TYPE: NOT_VERIFIED_BY_NEW_MANUAL_RETRY
FIRST_ERROR_MESSAGE_SANITIZED: NOT_VERIFIED_BY_NEW_MANUAL_RETRY
FIRST_ERROR_EXIT_CODE: 1_PREVIOUS_RUN

ORIGINAL_EXCEPTION_PRESERVED: YES_IN_SERVICE_AND_NOW_STRUCTURED_IN_CHILD_REPORT
ORIGINAL_EXCEPTION_CLASS: NOT_VERIFIED_UNTIL_ONE_CONTROLLED_RETRY
ORIGINAL_SQLSTATE: NOT_VERIFIED_UNTIL_ONE_CONTROLLED_RETRY
ORIGINAL_DRIVER_ERROR_CODE: NOT_VERIFIED_UNTIL_ONE_CONTROLLED_RETRY
ORIGINAL_ERROR_SANITIZED: STRUCTURED_ALLOWLIST_ONLY

SQL_SCHEMA_MISMATCH_CONFIRMED: NO_STATIC_EVIDENCE
ROOT_CAUSE_IDENTIFIED: DIAGNOSTIC_PROPAGATION_LOSS_IN_TEST_HARNESS
SERVICE_DEFECT_CONFIRMED: NO
TEST_HARNESS_DEFECT_CONFIRMED: YES

CORRECTION_APPLIED: CHILD_DIAGNOSTICS_AND_POWERSHELL_PROPAGATION_ONLY
ROLLBACK_CHECK_BEFORE_RESTORATION: PREPARED_NOT_EXECUTED_FROM_CODEX
DIAGNOSTIC_PROPAGATION_TO_POWERSHELL: PREPARED

PHP_LINT: PASS
POWERSHELL_51_VALIDATION: PASS_PARSE_ONLY
STATIC_CONTRACTS: PASS
DIFF_CHECK: PASS

MYSQL_WRITES_EXECUTED: NO
DDL_EXECUTED: NO
BACKFILL_EXECUTED: NO
BD_CLINICA_MODIFIED: NO
ENV_MODIFIED: NO
COMMITS_CREATED: NO

READY_FOR_ONE_CONTROLLED_RETRY: YES
FINAL_STATUS: READY_FOR_ONE_CONTROLLED_RETRY
```

La comprobación `rollback_check_before_restoration` se ejecutará únicamente si
el arnés manual alcanza una excepción durante una operación mutante: compara
lecturas de las dos identidades sintéticas contra el snapshot de esa operación
antes de entrar en `finally`. No sustituye la restauración focal posterior.
Desde Codex solo se validaron sintaxis, contratos estáticos y propagación; no se
ejecutó `-AllowSyntheticWrites`, MySQL, DDL ni backfill.

## Addendum — corrección definitiva del proceso PHP en PowerShell 5.1

Fecha: 2026-10-15

La línea que provocaba la interrupción era la invocación nativa del proceso
PHP con redirección PowerShell:

```powershell
$raw = @(& $PhpPath $ScriptPath @Arguments 2> $stderrPath)
```

Con `$ErrorActionPreference = 'Stop'`, Windows PowerShell 5.1 transforma el
stderr no fatal de un proceso nativo en `System.Management.Automation.RemoteException`,
incluso cuando el proceso hijo termina con exit code `0`. La reproducción
focal confirmó simultáneamente `RemoteException` y exit code `0`, por lo que el
fallo no era clínico ni implicaba que el contrato M07 hubiera fallado. La
clasificación no alcanzaba de forma fiable la lectura separada de stdout,
stderr y `$LASTEXITCODE`.

La corrección mínima sustituye esa invocación en el runner por
`System.Diagnostics.Process`, con `UseShellExecute = false`, stdout y stderr
redirigidos independientemente y lectura asíncrona para evitar bloqueos de
búfer. El exit code real se conserva. La clasificación JSON ahora mantiene
`PASS`, `FAIL`, `NOT_REACHED` y `BLOCKED`; un JSON inválido o un error de
arranque se rechaza explícitamente.

El mismo runner incorpora el modo focal `-FocalProcessTest`. No accede a
MySQL: crea un PHP temporal sintético, lo elimina en `finally` y cubre siete
assertions, incluyendo stderr no fatal con exit code cero.

```yaml
PHASE: M07_POWERSHELL_147_DEFINITIVE_FIX
LINE_147_COMMAND: OLD_NATIVE_INVOCATION_WITH_2_REDIRECT
ROOT_CAUSE: POWERSHELL_5_1_NATIVE_STDERR_REMOTEEXCEPTION_BEFORE_CLASSIFICATION
REMOTE_EXCEPTION_SOURCE: SYSTEM_MANAGEMENT_AUTOMATION_REMOTEEXCEPTION
CHILD_REAL_EXIT_CODE: 0_IN_REPRODUCTION_WITH_NONFATAL_STDERR
STDOUT_STDERR_HANDLING: SEPARATED_BY_SYSTEM_DIAGNOSTICS_PROCESS

FOCAL_REPRODUCTION: PASS
PS51_NATIVE_PROCESS_TESTS: PASS
JSON_STATUS_CLASSIFICATION_TESTS: PASS
NONZERO_EXIT_REJECTION: PASS
INVALID_JSON_REJECTION: PASS

CORRECTION_APPLIED: YES_RUNNER_ONLY
POWERHELL_RUNTIME_VERIFIED: PASS_WINDOWS_POWERSHELL_5_1
PHP_LINT: PASS
M07_STATIC_CONTRACTS: PASS
P09_STATIC_CONTRACTS: PASS
DIFF_CHECK: PASS

MYSQL_WRITES_EXECUTED: NO
DDL_EXECUTED: NO
BACKFILL_EXECUTED: NO
BD_CLINICA_MODIFIED: NO
ENV_MODIFIED: NO
COMMITS_CREATED: NO

READY_FOR_FULL_MANUAL_RETRY: YES
FINAL_STATUS: READY_FOR_FULL_MANUAL_RETRY
```

La validación focal se ejecutó con Windows PowerShell 5.1 usando
`-ExecutionPolicy Bypass` únicamente para ese proceso, porque la política de
autorización predeterminada bloqueaba el script antes de ejecutarlo. No se
modificó la política del sistema. El resultado fue
`FOCAL_PROCESS_CLASSIFICATION=PASS` con 7 assertions y sin temporales
persistentes.
