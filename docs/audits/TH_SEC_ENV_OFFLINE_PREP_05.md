# TH-SEC-ENV-OFFLINE-PREP-05 — Preparación offline de infraestructura Docker sintética

Fecha: 2026-10-12  
Modo: preparación de archivos y revisión estática  
Acceso Docker desde Codex: **NO INTENTADO** en esta fase

## 1. Resultado

Se prepararon únicamente artefactos revisables bajo `tests/security/infra/`. No se accedió al daemon Docker, no se ejecutó Compose, no se crearon contenedores, redes, volúmenes, bases o cuentas y no se modificaron `.env`, XAMPP, WSL, la aplicación ni las bases existentes.

El estado es `COMPLETE` para la preparación offline solicitada, no para la disponibilidad de infraestructura ni para pruebas dinámicas.

## 2. Archivos creados

- `tests/security/infra/compose.offline.yml`
- `tests/security/infra/preflight-offline.ps1`
- `tests/security/infra/fixtures.sha256`
- `tests/security/infra/.gitignore`
- `tests/security/infra/README.md`
- `docs/audits/TH_SEC_ENV_OFFLINE_PREP_05.md`

No se sobrescribieron archivos existentes. La única actualización fuera de `tests/security/infra/` es la referencia de esta fase en `docs/audits/TRADITIONAL_HISTORY_CLOSURE_MASTER.md`.

## 3. Plantilla Compose

La plantilla contiene un único servicio de base de datos con:

- imagen proporcionada explícitamente por `TH_SEC_DB_IMAGE`;
- `pull_policy: never`, para impedir descargas automáticas;
- red Docker `internal` dedicada;
- volumen nombrado exclusivamente para esta fase;
- ningún puerto publicado;
- ningún mount de proyecto, `.env`, XAMPP, documentos clínicos o socket Docker;
- sin `privileged`, `network_mode: host`, `build` o servicios adicionales;
- secreto de root suministrado mediante archivo fuera de Git;
- nombres de contenedor, red y volumen prefijados por `th-sec-env-bootstrap-03r`.

La plantilla no afirma que la imagen esté instalada. Si falta la variable de imagen, el directorio de secretos o el archivo de contraseña, Compose debe fallar sin crear recursos.

Las dos bases propuestas siguen siendo `clinica_dolor_demo_20261010_final` y `clinica_dolor_e2e_disposable_20261012`; no se crean automáticamente en esta fase.

## 4. Preflight de sólo lectura

`preflight-offline.ps1` está separado de cualquier aprovisionamiento. Comprueba, cuando lo ejecute el propietario en una fase posterior:

- contexto `desktop-linux` y respuesta del daemon;
- imagen local mediante `docker image inspect`, sin pull;
- colisiones de contenedor, red y volumen;
- hashes del manifiesto;
- presencia del secreto sin revelar su valor;
- configuración Compose contra puertos, mounts, host networking, socket, XAMPP, `bd_clinica`, `build` y políticas de pull inseguras.

El script sólo usa comandos informativos y no contiene `docker compose up`, `pull`, `build`, SQL, migraciones, HTTP ni código de aplicación. No se ejecutó desde Codex.

## 5. Fixtures

El manifiesto incluye los cuatro candidatos previamente hasheados:

| Fixture | Procedencia estática | Estado offline |
|---|---|---|
| `security-fixtures.sql` | Se declara sintético; IDs reservados 9101/9102, 9201/9202 y 9301/9302 | NOT_VERIFIED para procedencia runtime |
| `p05-catalog.php` | Catálogo de prueba del repositorio | NOT_VERIFIED para importación |
| `p07-catalog.php` | Catálogo de prueba del repositorio | NOT_VERIFIED para importación |
| `doctor-kpi-chart-fixtures.json` | Fixture visual de indicadores | NOT_VERIFIED para importación |

No se aceptó ni rechazó definitivamente ningún fixture para una base runtime; `FIXTURE_PROVENANCE_GATE` queda pendiente del propietario. No se importó ninguno.

## 6. Cuentas propuestas

La documentación sólo prepara el procedimiento para crear, dentro de una instancia nueva y en una fase autorizada:

- cuenta de aprovisionamiento, limitada al bootstrap;
- `th_sec_readonly`, sólo lectura en la base de referencia;
- `th_sec_test_runner`, limitada a la base descartable.

No se crearon cuentas ni se escribieron contraseñas. No se reutilizan credenciales de XAMPP ni del `.env`.

## 7. Dependencias y bloqueantes

- Docker daemon inaccesible para Codex; esta fase no volvió a intentarlo.
- Imagen local versionada: disponibilidad no verificada.
- Procedencia runtime de fixtures: pendiente.
- Cuentas, grants, esquema y bases: no creados.
- Revisión del propietario requerida antes de cualquier ejecución manual.

## 8. Procedimiento futuro

El propietario debe revisar `tests/security/infra/README.md`, preparar secretos fuera de Git y ejecutar sólo el preflight desde su PowerShell autorizado. El resultado debe ser revisado antes de cualquier `docker compose up` en una fase posterior. La creación, SQL de aprovisionamiento, grants, importación y comprobaciones de aislamiento no forman parte de esta ejecución.

## 9. Exclusiones

No se accedió a `bd_clinica` ni al MySQL de XAMPP. No se modificó `.env`, código, rutas, modelos, middleware, fixtures preexistentes, migraciones, datos clínicos o permisos. No se ejecutaron pruebas HTTP, PDF, descargas, clínicas o remediaciones P1. La Entrevista Conversacional quedó fuera de alcance.

## 10. Estado formal

```text
TH_SEC_ENV_OFFLINE_PREP_05:

- STATUS: COMPLETE — preparación offline; infraestructura no ejecutada
- CURRENT_BRANCH: Silvino
- CURRENT_HEAD: 1b08d9b4c41080c176fbe4588212863f3a1ac18c
- WORKTREE_PRESERVED: YES
- DOCKER_DAEMON_ACCESSED_BY_CODEX: NO
- OFFLINE_COMPOSE_PREPARED: YES — plantilla revisable, no ejecutada
- IMAGE_REQUIRED: imagen local versionada aprobada por el propietario
- IMAGE_AVAILABLE: NOT_VERIFIED
- FIXTURE_MANIFEST: tests/security/infra/fixtures.sha256
- FIXTURE_PROVENANCE_VERIFIED: NO — revisión estática; procedencia runtime pendiente
- FIXTURES_ACCEPTED: NONE FOR IMPORT
- FIXTURES_REJECTED: NONE — no se ejecutó importación
- READONLY_PREFLIGHT_PREPARED: YES — no ejecutado
- ISOLATION_CONFIGURATION_REVIEWED: YES — estática
- SECRET_HANDLING_REVIEWED: YES — secretos externos a Git; no creados
- MANUAL_EXECUTION_PROCEDURE_PREPARED: YES
- READY_FOR_OWNER_REVIEW: YES
- INFRASTRUCTURE_CREATED: NO
- MYSQL_XAMPP_ACCESSED: NO
- BD_CLINICA_ACCESSED: NO
- ENV_MODIFIED: NO
- APPLICATION_CODE_CHANGES: NONE
- SQL_EXECUTED: NONE
- HTTP_TESTS_EXECUTED: NONE
- P1_REMEDIATION_COMPLETED: NO
- IMPLEMENTATION_AUTHORIZED: NO
- PRODUCTION_READY: NO
- DOCUMENTATION_CREATED: docs/audits/TH_SEC_ENV_OFFLINE_PREP_05.md; tests/security/infra/README.md
- MASTER_CLOSURE_UPDATED: YES — sólo referencia de estado
- GLOBAL_DIFF_CHECK: PASS — código 0; advertencias históricas LF/CRLF
- COMMITS: NONE
- DEPLOYMENTS: NONE
```

