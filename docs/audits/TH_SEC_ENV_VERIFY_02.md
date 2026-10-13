# TH-SEC-ENV-VERIFY-02 — Validación controlada del entorno sintético y privilegios MySQL

Fecha: 2026-10-12  
Proyecto: Clínica del Dolor  
Modo: PREFLIGHT + READ-ONLY CONDICIONAL  
Resultado: **BLOCKED antes de conexión**

## A. Preflight

Se verificaron estáticamente la rama, el HEAD, los mecanismos de aislamiento existentes, la precedencia de configuración, el singleton de PDO, los scripts de preflight y la documentación de antecedentes indicada por la fase.

La rama actual es `Silvino` y el HEAD es `1b08d9b4c41080c176fbe4588212863f3a1ac18c`. El worktree se preservó. No se ejecutaron `git clean`, `git reset`, staging, commits, push ni despliegues.

La autorización del propietario es condicional: sólo permite una conexión después de demostrar todas las compuertas previas. No autoriza a sustituir la cuenta, modificar privilegios, editar `.env`, crear una base, usar `bd_clinica` o eludir el bloqueo.

## B. Estado inicial

La base autorizada para una fase futura es:

```text
clinica_dolor_demo_20261010_final
```

La base prohibida es `bd_clinica`. La configuración local `.env` continúa resolviendo `DB_DATABASE=bd_clinica` y `DB_USERNAME=root`. Esa configuración no se utilizó para abrir una conexión.

La revisión anterior `TH_SEC_ENV_READY_01` ya había dejado el entorno como `NO READY`, con `DB_ACCOUNT_ISOLATION_VERIFIED: NO`. Esta fase no encontró evidencia nueva que cambie ese resultado.

## C. Mecanismo de aislamiento

Los mecanismos existentes fueron inspeccionados sin ejecutarlos:

| Mecanismo | Hallazgo | Estado para esta fase |
|---|---|---|
| `tests/security/isolated-router.php` | Usa `CLINICA_AUDIT_DB` o una base por defecto distinta y expone sesiones JWT sintéticas directas | No apto sin wrapper externo; no ejecutado |
| `tests/p04/verify-isolation.php` | Allowlist explícita de la base sintética y lecturas de preflight | Candidato futuro; no ejecutado |
| `tests/p11/preflight.php` | Allowlist y lecturas del contrato P11 | Complementario; no ejecutado |
| `tests/phase02/doctor-read-only.php` | Allowlist, `SELECT DATABASE()` y comparación de estado antes/después | Futuro; requiere conexión y HTTP; no ejecutado |
| `app/Config/Database.php` | Singleton PDO basado en `$_ENV` al primer uso | Revisado estáticamente |
| `public/index.php` | Dotenv immutable se carga antes del router | Revisado estáticamente |
| `app/Core/Router.php` | Middleware global para `/pdf/*` y `/descargar/*` | Revisado estáticamente; no es evidencia de aislamiento de DB |

`isolated-router.php` no se usó para simular login. Sus endpoints crean claims directamente y no prueban el flujo real de autenticación.

## D. Evidencia de cuenta MySQL

No existe en los documentos revisados una declaración administrativa verificable de:

- identidad técnica sin contraseña;
- host o ámbito de la cuenta;
- privilegios efectivos;
- ausencia de privilegios sobre `bd_clinica`;
- ausencia de privilegios globales;
- ausencia de escritura y de capacidad de conceder permisos;
- lectura limitada a `clinica_dolor_demo_20261010_final`;
- origen y fecha de la evidencia.

No se ejecutó `SHOW GRANTS`, no se consultó metadata MySQL y no se intentó probar la cuenta configurada. La cuenta `root` presente en `.env` no puede aceptarse como cuenta aislada.

Resultado: `MYSQL_ACCOUNT_ISOLATION: BLOCKED`, `NO_ACCESS_TO_BD_CLINICA: BLOCKED` y `READ_ONLY_PRIVILEGES: BLOCKED`.

## E. Evidencia de procedencia sintética

`tests/fixtures/security-fixtures.sql` declara fixtures sintéticos con IDs reservados para clínicas `9101–9102`, médicos `9201–9202`, pacientes `9301–9302`, recetas `9401–9402` y expediente `9501–9513`. También define tablas de clínicas, doctores, accesos, pacientes y varios módulos.

Los scripts `tests/p05/prepare-synthetic.php`, `tests/p07/prepare-synthetic.php`, `tests/p08/prepare-synthetic.php`, `tests/p09/prepare-synthetic.php` y `tests/p10/prepare-synthetic.php` contienen allowlists del nombre de base y operaciones que pueden leer o escribir al ser ejecutados. Su existencia demuestra contratos de preparación, no que la base concreta haya sido construida exclusivamente con esos fixtures.

Los documentos previos registran pruebas sintéticas históricas, pero no aportan un manifiesto de procedencia firmado o una cadena de custodia de la base autorizada que satisfaga esta compuerta. También existe documentación que confirma que `clinica.test` apunta a `bd_clinica`, por lo que no puede reutilizarse como entorno de conexión.

Resultado: `SYNTHETIC_DATA_PROVENANCE: NO`. La definición de fixtures es sintética; la procedencia de la instancia `clinica_dolor_demo_20261010_final` no queda acreditada en este gate.

## F. Resultado de la compuerta previa

La compuerta exige que todos los elementos sean `PASS`. El resultado estático es:

| Condición | Resultado | Motivo |
|---|---|---|
| Nombre autorizado | PASS como contrato | El nombre allowlisted está definido |
| Aislamiento MySQL | BLOCKED | No hay evidencia de grants |
| Sin acceso a `bd_clinica` | BLOCKED | La cuenta y su ámbito no están demostrados |
| Privilegios sólo lectura | BLOCKED | No hay evidencia administrativa |
| Procedencia sintética | NO | Fixtures del repositorio no prueban la instancia |
| Método aislado | PARTIAL | Scripts allowlisted, pero no wrapper operativo demostrado |
| Precedencia de entorno | PARTIAL | Revisión estática; no se ejecutó un proceso aislado |
| Sin conexiones indirectas | PARTIAL | Call graph revisado; no se ejecutó runtime |
| Autorización del propietario | YES, condicional | La autorización exige pasar las compuertas |

Como existe `BLOCKED`, `NO` o `PARTIAL`, el resultado obligatorio es:

```text
DATABASE_CONNECTION_ALLOWED: NO
```

## G. Conexión ejecutada o bloqueada

La conexión fue bloqueada antes de PDO. No se ejecutó `SELECT DATABASE()`, no se consultó información del esquema y no se intentó otro usuario, servidor o nombre de base.

No se accedió a `bd_clinica`, directa ni indirectamente durante esta fase. Tampoco se ejecutaron SQL, HTTP, PDF, descargas, migraciones, seeders o fixtures.

## H. Base efectiva

No existe base efectiva verificada en esta fase. El valor de `.env` (`bd_clinica`) fue leído sólo como configuración estática y se utilizó como motivo de bloqueo, no como destino de conexión.

## I. Esquema verificable

La siguiente matriz distingue contratos estáticos de verificación dinámica. Ninguna estructura fue consultada en MySQL durante esta fase.

| Entidad | Estado | Evidencia disponible |
|---|---|---|
| `clinicas` | NOT_VERIFIED | Definida en fixture SQL; no consultada |
| `pc_paciente` y relación clínica | NOT_VERIFIED | Definida en fixture y contratos; no consultada |
| Doctores/accesos | NOT_VERIFIED | Definidos en fixture; roles no demostrados |
| `receta_medica` | NOT_VERIFIED | Contratos y fixture; owner histórico no demostrado |
| Recetas externas | NOT_VERIFIED | Tabla/owner real no demostrados |
| COFEPRIS, carpetas, folios y surtido | NOT_VERIFIED | Contratos y fixtures parciales; no consultado |
| Metadatos documentales | NOT_VERIFIED | No hay evidencia de mapping seguro |
| Roles/permisos/membresías | NOT_VERIFIED | No existe catálogo técnico verificable |
| Superadministrador | BLOCKED | No se demostró identidad técnica |

Las tablas propuestas por TH-SEC-ARCH-01 V1.1 siguen siendo arquitectura no implementada y no se presentan como existentes.

## J. Roles y permisos encontrados

La revisión documental previa demuestra claims y controles parciales de autenticación, pero no demuestra un catálogo técnico de roles, permisos, membresías multiclínica ni una representación verificable del superadministrador. No se ejecutó autenticación ni una consulta de usuarios.

Estado: `ROLE_CATALOG_SCHEMA: NOT_VERIFIED`; `SUPERADMIN_IMPLEMENTATION: BLOCKED`.

## K. Superadministrador

No se creó ni se verificó un superadministrador. No se aceptan como evidencia una constante, una cadena, un claim JWT o el nombre de un rol sin una representación técnica y una fuente de permisos verificable.

Las operaciones de conciliación histórica, vínculos entre clínicas y correcciones contradictorias permanecen fuera de alcance y bloqueadas.

## L. Recetas externas

La existencia, esquema, owner clínico y relación de recetas externas no se consultaron. Los documentos previos ya señalan que no puede asumirse que sean globales ni que exista owner verificable. No se realizó ninguna lectura, edición, PDF o descarga.

Estado: `EXTERNAL_RECIPE_SCHEMA: NOT_VERIFIED`; el riesgo permanece abierto y fail-closed.

## M. COFEPRIS

No se inspeccionaron tablas, carpetas, folios, surtidos ni relaciones de COFEPRIS. No se ejecutaron rutas, AJAX, PDF ni escrituras.

Estado: `COFEPRIS_SCHEMA: NOT_VERIFIED`.

## N. Fixtures disponibles

La matriz indica disponibilidad en código/documentación, no presencia en la base. No se creó ni insertó ningún fixture.

| Fixture | Disponible en código/documentación | Estructura verificada en DB | Uso futuro |
|---|---:|---:|---|
| Clínica A / `9101` | Sí | NO | Aislamiento propio |
| Clínica B / `9102` | Sí | NO | Aislamiento cruzado |
| Doctor A/B | Parcial | NO | Acceso por clínica |
| Paciente A/B / `9301`, `9302` | Sí | NO | Autorización propia/cruzada |
| Receta interna A/B | Parcial | NO | Owner verificable |
| Receta externa A | No verificable | NO | Contención fail-closed |
| Receta externa sin owner | No verificable | NO | Rechazo seguro |
| Carpetas COFEPRIS A/B | Parcial | NO | Scope de carpeta |
| Surtido propio/ajeno | Parcial | NO | Autorización por recurso |
| Archivo clínico A/B | Parcial | NO | Registry documental |
| Documento sin owner | No verificable | NO | Denegación |
| Usuario administrativo | No verificable | NO | Roles |
| Superadministrador sintético | No verificable | NO | Conciliación autorizada |

## O. Bloqueantes

1. Falta evidencia administrativa de aislamiento de la cuenta MySQL.
2. `.env` apunta a `bd_clinica` y no debe modificarse para esta fase.
3. No está acreditada la procedencia de la base sintética concreta.
4. No está verificada la existencia del esquema allowlisted.
5. No están verificados los fixtures efectivos, roles ni el superadministrador.
6. No está demostrado un wrapper operativo que impida conexiones indirectas antes de validar la base.

No se intentó corregir ninguno de estos bloqueantes automáticamente.

## P. Pruebas futuras necesarias

Después de recibir evidencia de cuenta y procedencia, y una nueva autorización explícita, el orden seguro será:

1. Usar un proceso local nuevo y un wrapper que acepte únicamente `clinica_dolor_demo_20261010_final`.
2. Usar una cuenta técnica dedicada, sin permisos sobre `bd_clinica`, sin escritura y sin privilegios administrativos.
3. Ejecutar sólo `SELECT DATABASE()` y abortar si no coincide.
4. Revisar metadatos limitados del esquema allowlisted, sin `SHOW DATABASES` y sin contenido clínico.
5. Ejecutar `tests/p04/verify-isolation.php` y `tests/p11/preflight.php` si la autorización cubre sus lecturas.
6. Revisar `tests/phase02/doctor-read-only.php` sólo con servidor aislado y sin acciones de escritura.
7. No ejecutar preparadores P05–P10: varios contienen escrituras y requieren autorización separada.
8. Registrar cuenta, base efectiva, fecha y script sin credenciales ni datos personales.

## Q. Recomendación del siguiente paso

Solicitar al propietario o administrador de infraestructura una evidencia sanitizada de privilegios efectivos y procedencia de `clinica_dolor_demo_20261010_final`. La evidencia debe demostrar que la cuenta no puede acceder a `bd_clinica`, no tiene privilegios globales ni de escritura y que la instancia fue construida sólo con datos sintéticos.

Hasta entonces, la política correcta es detenerse. No se debe usar `root`, cambiar `.env`, intentar `SHOW GRANTS`, iniciar el router aislado ni inferir PASS a partir de scripts o auditorías históricas.

## R. Estado formal

```text
TH_SEC_ENV_VERIFY_02:

STATUS: BLOCKED

CURRENT_BRANCH: Silvino
CURRENT_HEAD: 1b08d9b4c41080c176fbe4588212863f3a1ac18c
WORKTREE_PRESERVED: YES

AUTHORIZED_DATABASE: clinica_dolor_demo_20261010_final
DATABASE_CONNECTION_ALLOWED: NO
PRE_CONNECTION_GATE: BLOCKED — faltan evidencia de cuenta, procedencia sintética y ejecución aislada demostrada

MYSQL_ACCOUNT_ISOLATION: BLOCKED
NO_ACCESS_TO_BD_CLINICA: BLOCKED
READ_ONLY_PRIVILEGES: BLOCKED
SYNTHETIC_DATA_PROVENANCE: NO
ISOLATED_EXECUTION_METHOD: PARTIAL
ENV_PRECEDENCE_REVIEW: PARTIAL

DATABASE_CONNECTION_EXECUTED: NO
EFFECTIVE_DATABASE_VERIFIED: NO
REAL_DATABASE_ACCESSED: NO
REAL_CLINICAL_DATA_ACCESSED: NO

SCHEMA_INSPECTION_EXECUTED: NO
TABLES_VERIFIED: NO
EXTERNAL_RECIPE_SCHEMA: NOT_VERIFIED
ROLE_CATALOG_SCHEMA: NOT_VERIFIED
SUPERADMIN_IMPLEMENTATION: BLOCKED
COFEPRIS_SCHEMA: NOT_VERIFIED

FIXTURES_VERIFIED: NO — sólo definiciones estáticas revisadas
MISSING_FIXTURES: owner de recetas externas; documentos y mapping verificable; catálogo técnico de roles; superadministrador; registros efectivos A/B y COFEPRIS
UNRESOLVED_BLOCKERS: evidencia de grants; exclusión de bd_clinica; procedencia de la base concreta; esquema y fixtures efectivos; wrapper aislado; roles/superadministrador

READY_FOR_FUTURE_AUTHORIZATION_TESTS: NO
READY_FOR_P1_IMPLEMENTATION_APPROVAL: NO

DOCUMENTATION_CREATED: docs/audits/TH_SEC_ENV_VERIFY_02.md
MASTER_CLOSURE_UPDATED: YES — referencia añadida sin modificar auditorías históricas
GLOBAL_DIFF_CHECK: PASS — código 0; advertencias históricas LF/CRLF

APPLICATION_CODE_CHANGES: NONE
ENV_CHANGES: NONE
SCHEMA_CHANGES: NONE
DATA_CHANGES: NONE
PERMISSION_CHANGES: NONE
MIGRATIONS: NONE
HTTP_REQUESTS: NONE
COMMITS: NONE
DEPLOYMENTS: NONE

P1_REMEDIATION_COMPLETED: NO
IMPLEMENTATION_AUTHORIZED: NO
PRODUCTION_READY: NO
```

## Exclusión de Entrevista Conversacional

La Entrevista Conversacional no fue auditada, ejecutada ni modificada. Cualquier dependencia compartida permanece fuera de esta fase.

