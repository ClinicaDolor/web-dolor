# TH-SEC-ENV-BOOTSTRAP-03R — Reanudación controlada del entorno MySQL sintético

Fecha: 2026-10-12  
Proyecto: Clínica del Dolor  
Resultado: **BLOCKED en la compuerta A**

## A. Preflight

| Campo | Resultado |
|---|---|
| Directorio | `C:\xampp\htdocs\clinica-dolor` |
| Rama | `Silvino` |
| HEAD | `1b08d9b4c41080c176fbe4588212863f3a1ac18c` |
| Worktree | Preservado; cambios locales y archivos no rastreados previos conservados |
| Docker Desktop | Reportado por el propietario como iniciado en su sesión |
| Acceso de Codex al daemon | Bloqueado |
| Aplicación/`.env` | Sin modificaciones |

No se ejecutaron `git clean`, `git reset`, staging, commits, push o despliegues.

## B. Resultado de Docker Desktop

El propietario reportó Docker Desktop 4.74.0, Docker Engine 29.4.3, Compose disponible y contexto `desktop-linux`. Esos datos no pudieron ser confirmados por Codex en esta ejecución.

El contexto esperado no pudo resolverse desde el proceso de Codex. No se intentó cambiarlo, usar `DOCKER_HOST`, modificar permisos del named pipe, elevar privilegios ni iniciar otra instancia.

## C. Acceso de Codex al daemon

Se ejecutó exclusivamente la comprobación autorizada:

```text
docker --context desktop-linux version
```

Resultado:

```text
Failed to initialize: unable to resolve docker endpoint:
context "desktop-linux": open
C:\Users\silvi\.docker\contexts\meta\...\meta.json:
Access is denied.
```

La misma restricción impidió `docker --context desktop-linux info`.

Resultado formal:

```text
CODEX_DOCKER_ACCESS: BLOCKED
DOCKER_AVAILABLE_IN_USER_SESSION: YES — reportado por el propietario, no verificable desde Codex
INFRASTRUCTURE_CREATION: NOT_STARTED
```

La compuerta A exige detenerse ante este resultado. No se alcanzaron las compuertas B, C o D.

## D. Contexto Docker

El contexto indicado por el propietario es `desktop-linux`, con endpoint esperado `npipe:////./pipe/dockerDesktopLinuxEngine`. Codex no pudo leer la metadata local necesaria para verificarlo.

No se verificó que el endpoint corresponda al daemon local, ni que el servidor responda, ni que el contexto no sea remoto. No se cambió ningún contexto.

## E. Recursos Docker preexistentes

No se ejecutaron `docker ps`, `docker images`, `docker network ls` ni `docker volume ls` después del bloqueo de la compuerta A. Por tanto, los recursos preexistentes permanecen **NOT_VERIFIED** en esta fase.

No se inició, detuvo, eliminó, inspeccionó o modificó ningún recurso ajeno.

## F. Fixtures y procedencia

No se importó ningún fixture. Se conserva el manifiesto estático calculado en TH-SEC-ENV-BOOTSTRAP-03 para los archivos del repositorio:

| Fixture | SHA-256 registrado previamente | Uso en esta fase |
|---|---|---|
| `tests/fixtures/security-fixtures.sql` | `15FE5FF795829C78D92EF594BBE2514D382F0ED8D9CE42DB770D64D4B44B3FB7` | No importado |
| `tests/fixtures/p05-catalog.php` | `4CC8D03CB6233B942B7EE90862D2905ED4EFF7CC24DD1F175C7A142F53BE138E` | No importado |
| `tests/fixtures/p07-catalog.php` | `D255F3BA33307D7C82C28A94E8FBA18678623378081F74FE95A27C91371450D2` | No importado |
| `tests/fixtures/doctor-kpi-chart-fixtures.json` | `1DEA756F05D64CF1621B4D7E208029CA42335482CC435F9F10C693C5D4FB35A8` | No importado |

La revisión de procedencia runtime queda sin alcanzar. No se utilizaron respaldos, datos reales, `.env`, credenciales de XAMPP ni pacientes reales.

## G. Imagen utilizada

Ninguna imagen fue consultada o utilizada. `DATABASE_IMAGE_AVAILABLE: NOT_VERIFIED`.

La fase no autoriza descargar imágenes nuevas y no se pudo comprobar si existe localmente una imagen oficial con versión explícita.

## H. Red y volumen creados

Ninguno. No se creó la red dedicada ni el volumen exclusivo de `th-sec-env-bootstrap-03r`. No se publicaron puertos ni se montaron directorios.

## I. Contenedor

Ninguno. No se creó ni inició una instancia MySQL/MariaDB. No se utilizó `--privileged`, `--network host`, socket Docker, `.env`, `C:\xampp\mysql\data` ni directorios clínicos.

## J. Bases creadas

| Base | Estado |
|---|---|
| `clinica_dolor_demo_20261010_final` | NOT_CREATED |
| `clinica_dolor_e2e_disposable_20261012` | NOT_CREATED |
| `bd_clinica` | Fuera de alcance; no accedida |

No se ejecutó `SELECT DATABASE()` ni ninguna sentencia SQL.

## K. Cuentas técnicas y privilegios

No se crearon cuentas de aprovisionamiento, `th_sec_readonly` o `th_sec_test_runner`. No se ejecutó `SHOW GRANTS` ni se configuraron privilegios.

## L. Esquema instalado y tablas faltantes

No existe instancia sintética creada en esta fase; todas las tablas y relaciones son `NOT_VERIFIED` dinámicamente. No se inventó ni creó esquema faltante.

Permanecen sin verificar roles, permisos, superadministrador, recetas externas, COFEPRIS, documentos y relaciones históricas.

## M. Verificaciones de aislamiento

No se pudo verificar:

- identidad del contenedor;
- red interna;
- volumen propio;
- ausencia de mounts;
- ausencia de puertos;
- bases efectivas;
- cuentas y grants;
- conectividad externa;
- `SELECT DATABASE()`.

La falta de acceso del proceso de Codex es el bloqueo, no una razón para cambiar permisos o buscar un daemon alternativo.

## N. Incidentes y bloqueantes

1. `docker --context desktop-linux version` falla antes de comunicarse con el daemon.
2. La metadata del contexto bajo `C:\Users\silvi\.docker\contexts\meta` devuelve `Access is denied`.
3. No puede confirmarse desde Codex el endpoint local ni la versión del servidor.
4. No se puede pasar a inspección de colisiones, imagen o fixtures de runtime.

No se intentó superar estos bloqueantes automáticamente.

## O. Cambios realizados

Sólo se creó este informe y se añadió una referencia de estado al documento maestro. No se modificaron aplicación, `.env`, fixtures, tests, middleware, modelos, rutas, bases existentes, Docker, WSL o permisos.

## P. Comandos seguros de operación futura

Una futura ejecución, después de que el propietario resuelva el acceso de la sesión de Codex, deberá comenzar con:

```text
docker --context desktop-linux version
docker --context desktop-linux info
docker --context desktop-linux ps -a --filter name=th-sec-env-bootstrap-03r
```

Sólo si las comprobaciones anteriores pasan podrán inspeccionarse colisiones y crear recursos nuevos. No se autoriza ahora `docker run`, `pull`, `compose up`, `start`, `stop`, `rm`, comandos de limpieza ni SQL.

## Q. Preparación para pruebas A/B

No está lista. Las bases, cuentas, fixtures runtime, roles, recetas, COFEPRIS y documentos sintéticos no fueron creados ni verificados. No se ejecutaron pruebas HTTP, clínicas, de autenticación, PDF, descarga o escritura.

## R. Próxima fase recomendada

Resolver manualmente el acceso de la sesión de Codex al contexto local `desktop-linux`, sin cambiar permisos del named pipe desde esta fase. Después solicitar un nuevo preflight que vuelva a verificar:

1. `docker --context desktop-linux version` con Client y Server.
2. `docker info` sin endpoint remoto.
3. Colisiones de nombre sin modificar recursos.
4. Imagen oficial local disponible.
5. Procedencia y hashes de fixtures.

Si se requiere elevación, reparación, migración de Docker/WSL o cambio de configuración, debe obtenerse autorización independiente.

## S. Exclusión de Entrevista Conversacional

La Entrevista Conversacional quedó fuera de alcance y no fue auditada, ejecutada ni modificada.

## T. Estado formal

```text
TH_SEC_ENV_BOOTSTRAP_03R:

STATUS: BLOCKED

CURRENT_BRANCH: Silvino
CURRENT_HEAD: 1b08d9b4c41080c176fbe4588212863f3a1ac18c
WORKTREE_PRESERVED: YES

DOCKER_DESKTOP_VERSION: 4.74.0.227015 — reportado por propietario, no verificado por Codex
DOCKER_ENGINE_VERSION: 29.4.3 — reportado por propietario, no verificado por Codex
DOCKER_CONTEXT: desktop-linux — metadata inaccesible
CODEX_DOCKER_ACCESS: BLOCKED

DOCKER_IMAGE_AVAILABLE: NOT_VERIFIED
DOCKER_IMAGE_ID: NOT_VERIFIED
FIXTURE_PROVENANCE_GATE: NOT_REACHED — revisión estática previa PARTIAL
FIXTURES_ACCEPTED: NONE
FIXTURES_REJECTED: NONE

INFRASTRUCTURE_CREATION: NOT_STARTED
ISOLATED_CONTAINER: NOT_CREATED
ISOLATED_NETWORK: NOT_CREATED
DEDICATED_VOLUME: NOT_CREATED
HOST_PORTS_PUBLISHED: NONE
EXISTING_DOCKER_RESOURCES_MODIFIED: NO

SYNTHETIC_REFERENCE_DATABASE: NOT_CREATED
SYNTHETIC_DISPOSABLE_DATABASE: NOT_CREATED
DATABASE_IDENTITY_VERIFIED: NO
READONLY_ACCOUNT_CREATED: NO
READONLY_GRANTS_VERIFIED: NO
TEST_RUNNER_ACCOUNT_CREATED: NO
TEST_RUNNER_GRANTS_VERIFIED: NO

SCHEMA_COMPLETENESS: NOT_CREATED / NOT_VERIFIED
TABLES_AVAILABLE: NOT_VERIFIED
TABLES_MISSING: NOT_VERIFIED — no instance creada
ROLE_CATALOG_AVAILABLE: NOT_VERIFIED
SUPERADMIN_IMPLEMENTATION_VERIFIED: BLOCKED
EXTERNAL_RECIPE_SCHEMA_AVAILABLE: NOT_VERIFIED
COFEPRIS_SCHEMA_AVAILABLE: NOT_VERIFIED

MYSQL_XAMPP_ACCESSED: NO
BD_CLINICA_ACCESSED: NO
PRODUCTION_CREDENTIALS_USED: NO
ENV_MODIFIED: NO
APPLICATION_CODE_CHANGES: NONE
CLINICAL_DATA_CHANGES: NONE

INFRASTRUCTURE_SQL_EXECUTED: NONE
APPLICATION_HTTP_TESTS: NONE
CLINICAL_TESTS_EXECUTED: NONE

DOCUMENTATION_CREATED: docs/audits/TH_SEC_ENV_BOOTSTRAP_03R.md
MASTER_CLOSURE_UPDATED: YES — referencia añadida; documentos anteriores preservados
GLOBAL_DIFF_CHECK: PASS — código 0; advertencias históricas LF/CRLF
COMMITS: NONE
DEPLOYMENTS: NONE

READY_FOR_READONLY_TESTS: NO
READY_FOR_MUTATING_TESTS: NO
P1_REMEDIATION_COMPLETED: NO
IMPLEMENTATION_AUTHORIZED: NO
PRODUCTION_READY: NO
```

