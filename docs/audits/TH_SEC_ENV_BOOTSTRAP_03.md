# TH-SEC-ENV-BOOTSTRAP-03 — Creación controlada de entorno MySQL sintético y aislado

Fecha: 2026-10-12  
Proyecto: Clínica del Dolor  
Modo: preparación de infraestructura local sintética  
Resultado: **BLOCKED — no se creó infraestructura**

## A. Preflight

Se verificaron la rama, el HEAD, el estado del worktree, la configuración local y la disponibilidad de motores de contenedores.

| Elemento | Resultado |
|---|---|
| Directorio | `C:\xampp\htdocs\clinica-dolor` |
| Rama | `Silvino` |
| HEAD | `1b08d9b4c41080c176fbe4588212863f3a1ac18c` |
| Worktree | Preservado; ya contenía cambios locales y archivos no rastreados |
| Docker CLI | Disponible, versión 29.4.3 |
| Docker Compose | Disponible, versión v5.1.3 |
| Docker daemon | No disponible; el named pipe `docker_engine` no responde |
| Podman/Nerdctl | No detectados |
| MySQL/MariaDB independiente | No detectado |
| `.env` | No modificado; continúa apuntando a `bd_clinica` |

No se ejecutaron comandos destructivos, staging, commits, push, despliegues ni limpieza del worktree. No se iniciaron Docker Desktop, XAMPP ni servicios globales.

## B. Estado inicial y límites

La base `bd_clinica` quedó completamente fuera de alcance. No se utilizó el servidor MySQL de XAMPP, no se probaron sus credenciales y no se realizó ninguna conexión.

La fase autorizaba crear una instancia nueva sólo si podía garantizarse independencia física, red aislada, volumen propio, cuentas nuevas y procedencia sintética de los fixtures. Al no estar disponible el daemon, no se alcanzó esa compuerta y no se intentó una alternativa menos segura.

La Entrevista Conversacional permaneció fuera de alcance.

## C. Infraestructura encontrada

No había archivos `docker-compose.yml`, `compose.yaml`, configuraciones en `tests/security/infra/` ni otra infraestructura local reutilizable en el repositorio. La carpeta de infraestructura permitida no se creó porque faltaba un motor operativo.

El cliente Docker devolvió el error de conexión al daemon mediante `npipe:////./pipe/docker_engine`. El acceso al archivo de configuración de Docker también fue restringido; no se intentó corregir permisos ni cambiar perfiles.

## D. Estrategia de aislamiento elegida

La estrategia preferida sigue siendo un contenedor MySQL o MariaDB nuevo, con:

- identificador exclusivo `th-sec-env-bootstrap-03`;
- red interna dedicada;
- volumen propio;
- sin puertos publicados, o sólo `127.0.0.1` si fuese indispensable;
- sin mounts del proyecto clínico, `.env` o archivos de producción;
- sin conectividad con el MySQL de XAMPP;
- cuentas de aprovisionamiento, sólo lectura y runner descartable separadas;
- dos bases lógicas nuevas: `clinica_dolor_demo_20261010_final` y `clinica_dolor_e2e_disposable_20261012`.

Esta estrategia está diseñada pero no implementada. No se afirmó que exista ninguna de esas bases dentro de una instancia nueva.

## E. Justificación de independencia

No se pudo demostrar independencia porque el motor no está disponible. Crear otra base en `localhost:3306` habría reutilizado precisamente la instancia prohibida y habría violado el alcance. Por ello:

```text
ISOLATED_ENGINE_BOOTSTRAP: BLOCKED
```

No se hizo ningún intento de comprobar o crear `clinica_dolor_demo_20261010_final` en el servidor existente.

## F. Recursos creados

No se crearon contenedores, redes, volúmenes, bases, usuarios, grants, archivos de secretos, scripts de bootstrap ni configuraciones Docker. Sólo se generó este informe y se añadió una referencia de estado al inventario maestro.

## G. Configuración de red

No existe red sintética verificable. No se modificaron redes Docker existentes, reglas de firewall, XAMPP, hosts, servicios ni variables globales.

## H. Cuentas técnicas

No se creó ninguna cuenta. En particular, no se usó ni se reutilizó la cuenta `root` configurada en `.env`.

La futura cuenta `th_sec_readonly` deberá limitarse a la base de referencia sintética y metadatos permitidos, sin escritura, `GRANT` ni privilegios globales. La futura cuenta `th_sec_test_runner` deberá limitarse a la base descartable. Las credenciales deberán permanecer fuera del repositorio.

## I. Privilegios

No hubo instancia sobre la cual verificar privilegios. No se ejecutó `SHOW GRANTS` en ningún servidor. No se modificaron permisos. El aislamiento de cuentas queda `NOT_VERIFIED` hasta que exista un motor nuevo y cuentas creadas dentro de él.

## J. Fixtures utilizados y admisibilidad

No se importó ningún fixture. Se revisaron estáticamente los candidatos del repositorio y se calculó su SHA-256. La revisión confirma que `security-fixtures.sql` se declara sintético y usa IDs reservados; no confirma la procedencia de una base concreta porque no se creó ninguna.

No se encontraron credenciales, tokens de producción o volcados reales en el contenido revisado para esta fase. Esta conclusión es de inspección de los candidatos listados, no una certificación global del repositorio.

## K. Manifiesto y hashes

Los hashes fueron calculados localmente el 2026-10-12. No existe un manifiesto firmado o hash de referencia externo, por lo que la verificación de integridad es `PARTIAL`.

| Archivo | SHA-256 | Longitud |
|---|---|---:|
| `tests/fixtures/security-fixtures.sql` | `15FE5FF795829C78D92EF594BBE2514D382F0ED8D9CE42DB770D64D4B44B3FB7` | 19980 |
| `tests/fixtures/p05-catalog.php` | `4CC8D03CB6233B942B7EE90862D2905ED4EFF7CC24DD1F175C7A142F53BE138E` | 3514 |
| `tests/fixtures/p07-catalog.php` | `D255F3BA33307D7C82C28A94E8FBA18678623378081F74FE95A27C91371450D2` | 776 |
| `tests/fixtures/doctor-kpi-chart-fixtures.json` | `1DEA756F05D64CF1621B4D7E208029CA42335482CC435F9F10C693C5D4FB35A8` | 682 |

Los scripts de preparación fueron inspeccionados sin ejecución. Algunos contienen `PDO`, `SELECT`, y en P05/P07/P08 o scripts de entrevista operaciones de preparación que pueden escribir; por eso no fueron iniciados.

## L. Bases sintéticas

| Base | Estado | Motivo |
|---|---|---|
| `clinica_dolor_demo_20261010_final` | NOT_CREATED | No existe motor independiente disponible |
| `clinica_dolor_e2e_disposable_20261012` | NOT_CREATED | No existe motor independiente disponible |
| `bd_clinica` | FUERA DE ALCANCE | No se accedió ni se utilizó |

No se declaró que una base existente tenga contenido sintético.

## M. Tablas presentes

No se inspeccionó ninguna base. Las tablas siguientes sólo están definidas en fixtures o contratos y no se presentan como presentes:

- `clinicas`, `doctores`, `accesos_doctores`, `pc_paciente` y `pc_paciente_acceso`;
- tablas de los módulos tradicionales P01–P11;
- `receta_medica` y cualquier tabla externa;
- COFEPRIS, carpetas, folios y surtido;
- tablas de archivos, documentos, roles, permisos y membresías.

Estado de presencia dinámica: `NOT_VERIFIED` para todas.

## N. Esquema faltante

No se rellenaron estructuras faltantes. Permanecen por determinar, en una fase autorizada posterior:

- tablas reales de recetas externas y owner verificable;
- roles, permisos, membresías y superadministrador;
- relaciones históricas de receta, clínica y traslado;
- ownership documental y mapping privado;
- relaciones COFEPRIS completas.

Si el fixture no contiene esas estructuras, deberán reportarse como ausentes o propuestas, no simuladas como implementación productiva.

## O. Verificación del aislamiento

No se pudo verificar contenedor, imagen, red, volumen, mounts, conectividad, cuentas ni base efectiva. No se ejecutó `SELECT DATABASE()` ni ninguna prueba de privilegios.

Resultado: `ISOLATED_NETWORK_VERIFIED: NO`, `DATABASE_IDENTITY_VERIFIED: NO`, `PRODUCTION_NETWORK_ACCESS: NOT_VERIFIED`.

## P. Disponibilidad de roles y superadministrador

No se creó ni verificó catálogo RBAC. La presencia de claims JWT no sería suficiente para demostrar roles o superadministrador. La regla de conciliación histórica permanece documentada, pero no implementada ni probada.

Estado: `ROLE_CATALOG_VERIFIED: BLOCKED`; `SUPERADMIN_IMPLEMENTATION_VERIFIED: BLOCKED`.

## Q. Preparación de pruebas A/B

La infraestructura futura deberá permitir escenarios para clínicas `9101` y `9102`, pacientes `9301` y `9302`, recetas internas y externas, documentos, COFEPRIS, usuario no autorizado y superadministrador sintético. Ningún registro de esos escenarios fue creado o verificado.

## R. Limitaciones

1. Docker daemon apagado o inaccesible.
2. No hay motor alternativo detectable.
3. No se puede crear una instancia independiente sin instalar o iniciar software, acción fuera de la autorización actual.
4. No se puede demostrar procedencia de una base concreta porque no existe una nueva instancia preparada.
5. No se pueden verificar grants, esquema, red o cuentas.

## S. Comandos seguros de operación futura

Estos son sólo comandos de referencia; no se ejecutaron:

```text
docker version
docker compose version
docker ps -a --filter name=th-sec-env-bootstrap-03
docker network ls
docker volume ls
```

Antes de usar cualquier comando de creación se requiere que el daemon esté disponible y que el propietario autorice la ejecución en una instancia independiente. No deben usarse `docker system prune`, `docker volume prune` ni `docker compose down -v` sobre proyectos existentes.

## T. Limpieza futura

No se creó ningún recurso, por lo que no hubo limpieza. Una futura limpieza deberá limitarse por nombre/ID a los recursos creados por `th-sec-env-bootstrap-03`, sin tocar redes, volúmenes, contenedores o bases ajenos. No se ejecutará automáticamente.

## U. Riesgos residuales

- La aplicación local sigue apuntando a `bd_clinica`; no debe usarse para pruebas.
- No existe una instancia sintética independiente disponible.
- No existe evidencia de cuentas aisladas ni grants limitados.
- Los fixtures del repositorio no prueban la procedencia de una base runtime.
- Roles, superadministrador, recetas externas, documentos y COFEPRIS siguen sin verificación dinámica.
- No se autorizan pruebas HTTP, escritura ni remediación P1.

## V. Siguiente fase recomendada

Solicitar autorización separada para iniciar Docker Desktop o instalar un motor compatible si el propietario lo aprueba. Después:

1. Verificar que Docker daemon responde.
2. Crear sólo el contenedor, red y volumen con identificador exclusivo.
3. Crear las dos bases y las tres cuentas dentro de esa instancia nueva.
4. Importar sólo fixtures con procedencia y hash aprobados.
5. Verificar privilegios y `SELECT DATABASE()` dentro de la instancia.
6. Documentar tablas y ausencias sin inventar esquema.
7. Detenerse y solicitar autorización nueva antes de pruebas HTTP o de escritura.

## W. Exclusión de Entrevista Conversacional

La Entrevista Conversacional no fue auditada, modificada ni ejecutada. Cualquier dependencia compartida queda fuera de esta fase.

## X. Estado formal

```text
TH_SEC_ENV_BOOTSTRAP_03:

- STATUS: BLOCKED
- CURRENT_BRANCH: Silvino
- CURRENT_HEAD: 1b08d9b4c41080c176fbe4588212863f3a1ac18c
- WORKTREE_PRESERVED: YES
- DOCKER_AVAILABLE: NO — CLI disponible, daemon no disponible
- INDEPENDENT_DATABASE_INSTANCE: NO
- ISOLATION_METHOD: NOT_ESTABLISHED — Docker preferido, sin daemon operativo
- ISOLATED_NETWORK_VERIFIED: NO
- PRODUCTION_NETWORK_ACCESS: NOT_VERIFIED — no hubo instancia creada
- EXISTING_MYSQL_ACCESSED: NO
- BD_CLINICA_ACCESSED: NO
- PRODUCTION_CREDENTIALS_USED: NO
- ENV_MODIFIED: NO
- BOOTSTRAP_RESOURCES_CREATED: NONE
- SYNTHETIC_READONLY_DATABASE: NOT_CREATED
- SYNTHETIC_DISPOSABLE_DATABASE: NOT_CREATED
- SYNTHETIC_PROVENANCE_VERIFIED: NO — sólo fixtures del repositorio revisados
- FIXTURE_MANIFEST: embebido en este informe, sección K
- FIXTURE_HASHES_VERIFIED: PARTIAL — SHA-256 calculado, sin baseline firmado
- READONLY_ACCOUNT_CREATED: NO
- READONLY_GRANTS_VERIFIED: NO
- DISPOSABLE_TEST_ACCOUNT_CREATED: NO
- DISPOSABLE_GRANTS_VERIFIED: NO
- DATABASE_IDENTITY_VERIFIED: NO
- SCHEMA_COMPLETENESS: NOT_CREATED / NOT_VERIFIED
- TABLES_AVAILABLE: NOT_VERIFIED
- TABLES_MISSING: roles, permisos, superadministrador, owner documental/externo y relaciones COFEPRIS; presencia dinámica no verificada
- ROLE_CATALOG_VERIFIED: BLOCKED
- SUPERADMIN_IMPLEMENTATION_VERIFIED: BLOCKED
- TEST_FIXTURES_AVAILABLE: definiciones estáticas de fixtures y catálogos
- TEST_FIXTURES_MISSING: instancia runtime, A/B efectivos, owner externo, documentos, COFEPRIS, roles y superadministrador
- ISOLATION_TESTS: NOT_EXECUTED
- SECURITY_BLOCKERS: daemon Docker no disponible; no hay instancia ni cuentas aisladas; no existe procedencia runtime verificable
- FUTURE_TEST_READINESS: NO
- FUTURE_WRITE_TESTS_AUTHORIZED: NO
- APPLICATION_CODE_CHANGES: NONE
- ENV_CHANGES: NONE
- EXISTING_DATABASE_CHANGES: NONE
- SYNTHETIC_INFRASTRUCTURE_CHANGES: NONE
- CLINICAL_DATA_CHANGES: NONE
- P1_REMEDIATION_COMPLETED: NO
- DOCUMENTATION_CREATED: docs/audits/TH_SEC_ENV_BOOTSTRAP_03.md
- MASTER_CLOSURE_UPDATED: YES — referencia añadida; auditorías anteriores preservadas
- GLOBAL_DIFF_CHECK: PASS — código 0; advertencias históricas LF/CRLF
- COMMITS: NONE
- DEPLOYMENTS: NONE
- IMPLEMENTATION_AUTHORIZED: NO — aplicación
- PRODUCTION_READY: NO
```

