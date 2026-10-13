# TH-SEC-ENV-OFFLINE-HARDEN-05A

## Resultado

```text
TH_SEC_ENV_OFFLINE_HARDEN_05A:

STATUS: COMPLETE — STATIC HARDENING ONLY; RUNTIME PREFLIGHT BLOCKED
CURRENT_BRANCH: Silvino
CURRENT_HEAD: 1b08d9b4c41080c176fbe4588212863f3a1ac18c
WORKTREE_PRESERVED: YES

H01_LOCAL_CONTEXT: HARDENED — requires desktop-linux, rejects ambiguous Docker/Compose environment overrides, verifies the local Docker Desktop named pipe, and fails closed on context/daemon inspection errors.
H02_COLLISION_ERRORS: HARDENED — distinguishes existing, missing, permission, communication, and unknown inspection results for project, container, network, and volume.
H03_COMPOSE_EFFECTIVE_CONFIG: HARDENED — prepares a non-mutating `docker compose config --format json` check using an explicit temporary interpolation file; output is held in memory and not printed.
H04_SECRET_PROTECTION: HARDENED — requires an absolute external directory, rejects repository/reparse/project paths, checks the required file and ACLs, and never reads its value.
H05_IMAGE_RESTRICTION: HARDENED — Compose is fixed to `mariadb:10.6`, `pull_policy: never`, and the preflight records the full local ID while checking the owner-reported prefix `1e008264230a`.
H06_FIXTURE_MANIFEST: HARDENED — validates SHA-256, canonical paths under `tests/fixtures/`, duplicates and reparse points; provenance remains a separate blocking gate.
H07_DATABASE_PROVISIONING_SCOPE: DOCUMENTED — the Compose database is only the reference initialization target; disposable database creation, accounts, and grants require a later authorized procedure.
H08_PREFLIGHT_STATUS_CONTRACT: HARDENED — emits PASS/BLOCKED checks, `PREFLIGHT_TECHNICAL_STATUS`, `READY_FOR_INFRASTRUCTURE_CREATION: NO`, and `DATABASE_CREATION_AUTHORIZED: NO`.

POWERSHELL_SYNTAX: PASS — static Parser::ParseFile validation.
YAML_SYNTAX: NO VERIFICADO — no offline YAML parser was available; Docker/Compose validation was prohibited.
SECRET_SCAN: PASS — no literal private key, URL credential, or secret value found in the permitted infrastructure files.
GLOBAL_DIFF_CHECK: PASS — Git reported only pre-existing LF/CRLF normalization warnings in unrelated files.

STATIC_HARDENING_COMPLETE: YES
MANUAL_PREFLIGHT_READY: YES
INFRASTRUCTURE_CREATION_READY: NO

DOCKER_EXECUTED_BY_CODEX: NO
INFRASTRUCTURE_CREATED: NO
BD_CLINICA_ACCESSED: NO
MYSQL_XAMPP_ACCESSED: NO
APPLICATION_CODE_CHANGES: NONE
ENV_CHANGES: NONE
SCHEMA_CHANGES: NONE
CLINICAL_DATA_CHANGES: NONE

DOCUMENTATION_CREATED: docs/audits/TH_SEC_ENV_OFFLINE_HARDEN_05A.md
MASTER_CLOSURE_UPDATED: docs/audits/TRADITIONAL_HISTORY_CLOSURE_MASTER.md — phase reference appended
COMMITS: NONE
DEPLOYMENTS: NONE

P1_REMEDIATION_COMPLETED: NO
IMPLEMENTATION_AUTHORIZED: NO
PRODUCTION_READY: NO
```

## Alcance y preservación

La ejecución fue exclusivamente estática. No se ejecutaron Docker, Docker Compose, el preflight, SQL, PHP, HTTP, migraciones, importaciones, descargas, PDFs ni pruebas clínicas. No se modificaron `.env`, la aplicación, modelos, controladores, rutas, esquemas, bases, permisos o datos. La Entrevista Conversacional quedó fuera de alcance.

El worktree ya contenía cambios locales y artefactos offline de la fase anterior. Se conservaron sin reset, limpieza, staging o commit.

## Hallazgos corregidos H01–H08

### H01 — Contexto local

`tests/security/infra/preflight-offline.ps1:3-7,127-166` exige `desktop-linux`, rechaza `DOCKER_HOST`, `DOCKER_CONTEXT`, `COMPOSE_FILE` y `COMPOSE_PROJECT_NAME` activos, inspecciona el endpoint y acepta únicamente `npipe:////./pipe/dockerDesktopLinuxEngine`. Los errores de contexto o daemon quedan bloqueados; no se cambia el contexto del propietario.

### H02 — Colisiones

`preflight-offline.ps1:169-184,386-404` clasifica el resultado de las inspecciones de proyecto, contenedor, red y volumen. Un recurso ausente es PASS para la compuerta de colisión; existencia, permisos, comunicación o errores desconocidos son BLOCKED. No se detienen ni modifican recursos.

### H03 — Compose efectivo

`preflight-offline.ps1:186-263` prepara `docker compose config --format json` con un `--env-file` temporal que sólo contiene la ruta del secreto. La salida no se imprime. Se validan servicios, imagen, `pull_policy`, puertos, red interna, comandos, secretos, volúmenes nombrados y ausencia de bind mounts prohibidos. La revisión estática adicional está en `preflight-offline.ps1:266-297`.

`tests/security/infra/compose.offline.yml:3-31` conserva un único servicio `db`, sin puertos, con red `internal: true`, volumen nombrado, secreto externo y `pull_policy: never`.

### H04 — Secretos

`preflight-offline.ps1:72-124` exige una ruta absoluta externa al repositorio, rechaza reparse points y rutas de proyecto/XAMPP/clínicas, verifica el archivo requerido y revisa ACLs sin leer el contenido. `tests/security/infra/.gitignore:2-10` excluye explícitamente `mariadb-root-password` y extensiones de contraseña.

### H05 — Imagen

`compose.offline.yml:5-8` fija la única candidata a `mariadb:10.6` y no permite descarga. `preflight-offline.ps1:19,359-384` rechaza otra imagen, inspecciona la imagen local, conserva el ID completo en el resultado y compara su prefijo con el reporte del propietario `1e008264230a`.

### H06 — Fixtures

`tests/security/infra/fixtures.sha256:1-6` mantiene los cuatro hashes sin alterarlos y usa rutas explícitas `tests/fixtures/...`. `preflight-offline.ps1:300-352` valida hash, ruta canónica, ausencia de duplicados y reparse points. La integridad no se convierte en procedencia: `FIXTURE_PROVENANCE` queda BLOCKED hasta que el propietario aporte evidencia independiente.

### H07 — Alcance de bases

`tests/security/infra/README.md:45-52` y el Compose documentan que `MARIADB_DATABASE` inicializa sólo la base de referencia. La base descartable, cuentas y grants requieren una fase posterior autorizada. No se agregó SQL ni aprovisionamiento automático.

### H08 — Contrato de salida

`preflight-offline.ps1:412-448` produce el estado técnico, las comprobaciones PASS/BLOCKED, `READY_FOR_INFRASTRUCTURE_CREATION: NO`, `DATABASE_CREATION_AUTHORIZED: NO` y detalles sin valores secretos. La procedencia bloqueada impide un PASS global aunque los hashes sean correctos.

## Archivos autorizados modificados

- `tests/security/infra/compose.offline.yml`
- `tests/security/infra/preflight-offline.ps1`
- `tests/security/infra/fixtures.sha256` — sólo se corrigieron las rutas del manifiesto; los hashes permanecen iguales.
- `tests/security/infra/.gitignore`
- `tests/security/infra/README.md`
- `docs/audits/TH_SEC_ENV_OFFLINE_HARDEN_05A.md`
- `docs/audits/TRADITIONAL_HISTORY_CLOSURE_MASTER.md` — sólo se añadió la referencia de esta fase.

## Bloqueantes residuales y ejecución manual

1. El propietario debe ejecutar el preflight desde su PowerShell autorizado, con Docker Desktop disponible, sin modificar el contexto y sin ejecutar `compose up`.
2. Debe confirmarse el endpoint local, el ID completo de `mariadb:10.6`, la ausencia de colisiones, la protección ACL del secreto y la configuración efectiva de Compose.
3. Debe aportarse evidencia separada de procedencia sintética. `FIXTURE_HASHES: PASS` nunca implica `PROVENANCE_VERIFIED` ni `IMPORT_APPROVED`.
4. Hasta completar esas condiciones, no existe autorización para crear infraestructura, importar fixtures, acceder a `bd_clinica`/XAMPP o ejecutar pruebas P01–P11.

No se ejecutó ninguna acción destructiva y no se realizaron commits ni despliegues.
