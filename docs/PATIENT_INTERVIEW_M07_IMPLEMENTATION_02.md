# M07 — Implementación conversacional 02

Fecha: 2026-10-14  
Proyecto: Clínica del Dolor  
Alcance: Entrevista Conversacional, M07/P09 y pruebas sintéticas aisladas

## Resultado

Esta entrega completa la navegación secuencial de las seis categorías oficiales
de M07 y verifica la persistencia canónica directamente contra la base sintética
permitida. La aceptación HTTP autenticada y la aceptación de navegador no se
declaran porque la guardia P04 detuvo el arranque del servidor: faltan dos
identidades de acceso sintéticas activas e independientes que puedan verificarse
sin crear cuentas ni modificar datos fuera del procedimiento autorizado.

No se modificaron formularios tradicionales, el esquema, `.env`, `bd_clinica`,
el paciente 310, Gemini, STT ni configuración productiva.

## Cambios funcionales

### Navegación M07

`app/views/paciente/entrevista.php` ahora presenta las categorías oficiales en
secuencia, con:

- navegación directa por categoría;
- categoría anterior y siguiente;
- selección inicial de la primera categoría con elementos pendientes;
- bloqueo de avance cuando existen cambios locales sin confirmar o respuestas
  pendientes;
- lectura y actualización del estado de categoría después del readback;
- navegación anterior sin escritura clínica.

La selección, los valores T1–T5 y la confirmación explícita de negativas
continúan separados de la navegación. No se interpreta una casilla no
seleccionada como negativa y no se habilita la finalización de M07.

### Corrección transaccional focal

Se comprobó durante la prueba MySQL que el adaptador abría la transacción con
una conexión PDO mientras `MedicacionDolorModel` obtenía otra conexión global.
Eso podía dejar cambios fuera de la transacción aunque el adaptador devolviera
`M07_ATOMIC_ROLLBACK`.

La corrección mínima fue:

- `app/Models/MedicacionDolorModel.php`: constructor compatible hacia atrás con
  PDO opcional;
- `app/Services/ConversationalM07Adapter.php`: inyección de la misma PDO
  transaccional al modelo canónico.

No cambió SQL, esquema ni contratos de persistencia. Las llamadas históricas sin
argumento siguen utilizando `Database::getInstance()`.

## Evidencia sintética

La prueba `tests/patient-interview/m07-mysql-integration.php` se ejecutó con
PowerShell/PHP fuera del sandbox restringido, usando únicamente
`clinica_dolor_demo_20261010_final`, paciente sintético 9301 como sujeto de la
prueba y 9302 como control de aislamiento. No imprimió valores clínicos,
credenciales ni payloads.

Resultado final:

- preflight de base, marcadores sintéticos y tablas: PASS;
- lectura de las seis categorías y 46 medicamentos: PASS;
- escritura canónica de un lote confirmado: PASS;
- rollback ante rechazo de validación: PASS;
- filas y versión iguales después del rollback: PASS;
- rechazo de replay con versión obsoleta: PASS;
- readback posterior: PASS;
- restauración focal de las filas del sujeto sintético: PASS;
- hash del paciente sintético de control sin cambios: PASS.

La prueba realizó escrituras temporales únicamente sobre el sujeto sintético
9301 y restauró sus filas focales antes de terminar. No tocó `bd_clinica` ni el
paciente 310.

## Guardias HTTP P04

Se ejecutó el preflight de aislamiento solicitado para:

- base: `clinica_dolor_demo_20261010_final`;
- pacientes: 9301 y 9302;
- modo: `cross-clinic`.

Pasaron la coincidencia de base efectiva, host/puerto local, tablas requeridas,
marcadores sintéticos, pacientes sintéticos, clínicas distintas y filas de
acceso sintético. El único bloqueo fue:

`independent_active_access_credentials: false`

Por esa razón no se inició `start-server.ps1`, no se ejecutaron peticiones HTTP
ni se declaró aceptación de navegador. No se modificó la allowlist de P04, no
se crearon usuarios y no se relajó la política.

## Regresiones y alcance de pruebas

Pruebas focales ejecutadas:

- `php tests/patient-interview/m07-conversational-implementation-contract.php`:
  PASS, 21 aserciones estáticas y comportamiento SQLite en memoria PASS;
- `php -l` en modelo M07, adaptador, vista, rutas y arnés MySQL: PASS;
- arnés MySQL sintético focal: PASS;
- `voice-and-navigation-contract.php`: PASS, 11 aserciones, sin escrituras;
- preflight P04 de solo lectura: BLOCKED por credenciales sintéticas
  independientes no verificables.

Regresiones no atribuibles a esta entrega:

- `m03-compact-ux-contract.php`: FAIL por una aserción preexistente que busca
  el texto exacto de disponibilidad del reconocimiento nativo; no se modificó
  M03 en esta entrega;
- M04, M05 y M08 DB-backed: BLOCKED porque apuntan a la base de navegador no
  autorizada por P04 y la conexión fue rechazada por el sandbox;
- HTTP M07: BLOCKED antes del arranque por P04;
- navegador M07: NOT_VERIFIED.

## Estado contractual

```yaml
PHASE: M07_CONVERSATIONAL_IMPLEMENTATION_02
M07_SEQUENTIAL_NAVIGATION: IMPLEMENTED_STATIC_AND_CLIENT_STATE
M07_PREVIOUS_QUESTION: PASS_NO_WRITE_CATEGORY_NAVIGATION
M07_NEXT_PENDING_QUESTION: PASS_AFTER_CONFIRMED_READBACK
M07_CANONICAL_READBACK: PASS_SYNTHETIC_MYSQL
M07_EDIT_AUTHORIZATION: SERVER_PATH_PRESERVED_DIRECT_SYNTHETIC_PASS
M07_EXPLICIT_NEGATIVES: PASS_EXPLICIT_CONFIRMATION_REQUIRED

MYSQL_TEST_ENVIRONMENT: PASS_SYNTHETIC_DIRECT_DB
P04_GUARD_STATUS: BLOCKED_ACTIVE_ACCESS_CREDENTIALS
MYSQL_CONNECTION_VERIFIED: PASS
MYSQL_PERSISTENCE: PASS
MYSQL_ATOMICITY: PASS_AFTER_SHARED_PDO_FIX
MYSQL_ROLLBACK: PASS
MYSQL_VERSION_PROTECTION: PASS
MYSQL_IDEMPOTENCY: PASS_STALE_REPLAY_REJECTED

M07_HTTP_ACCEPTANCE: DB_HTTP_BLOCKED_P04
M07_BROWSER_ACCEPTANCE: NOT_VERIFIED

M03_REGRESSION: FAIL_PREEXISTING_NATIVE_STT_TEXT_ASSERTION
M04_REGRESSION: BLOCKED_BROWSER_DB_SANDBOX
M05_REGRESSION: BLOCKED_BROWSER_DB_SANDBOX
M08_REGRESSION: BLOCKED_BROWSER_DB_SANDBOX
VOICE_REGRESSION: PASS_SOURCE_CONTRACT

FILES_MODIFIED:
  - app/Models/MedicacionDolorModel.php
  - app/Services/ConversationalM07Adapter.php
  - app/views/paciente/entrevista.php
  - tests/patient-interview/m07-conversational-implementation-contract.php
  - tests/patient-interview/m07-mysql-integration.php
  - docs/PATIENT_INTERVIEW_M07_IMPLEMENTATION_02.md

TESTS_EXECUTED:
  - php -l focal de M07
  - contrato estático y SQLite en memoria de M07
  - arnés MySQL sintético focal con rollback, readback y restauración focal
  - preflight P04 de aislamiento
  - contrato de voz y navegación
  - regresiones M03/M04/M05/M08 con sus bloqueos documentados

TESTS_PASSED:
  - M07 estático, navegación y comportamiento en memoria
  - MySQL sintético, persistencia, rollback, versionado y readback
  - voz/navegación estática

TESTS_FAILED:
  - M03 compact UX por texto esperado preexistente no coincidente

TESTS_BLOCKED:
  - P04 HTTP por credenciales sintéticas independientes no verificables
  - M04/M05/M08 DB-backed por base de navegador no autorizada
  - navegador M07 por ausencia de servidor HTTP autorizado

SCHEMA_MODIFIED: NO
TRADITIONAL_FORMS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
ENV_MODIFIED: NO
COMMITS_CREATED: NO
DEPLOYMENTS: NO

M07_FINALIZATION_ENABLED: NO
REMAINING_CLINICAL_BLOCKERS:
  - Reglas de completitud T2/T3/T4/T5 pendientes de validación clínica.
  - Almacenamiento de Otro/Otros pendiente de decisión clínica.
  - Resultado combinado eficacia/efectos adversos pendiente de decisión clínica.

REMAINING_TECHNICAL_BLOCKERS:
  - P04 requiere dos accesos sintéticos activos e independientes antes de HTTP.
  - La aserción textual preexistente de M03 necesita resolver su expectativa sin
    mezclarla con M07.

FINAL_STATUS: PARTIAL_WITH_BLOCKERS
RECOMMENDED_NEXT_ACTION: Renovar o verificar por el procedimiento sintético autorizado dos accesos independientes para que P04 permita una prueba HTTP autenticada; después ejecutar una única aceptación HTTP/browser M07 sin tocar bd_clinica ni el paciente 310.
```

No se declara producción completa ni finalización clínica de M07.
