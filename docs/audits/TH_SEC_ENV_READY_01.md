# TH-SEC-ENV-READY-01 — Preparación segura del entorno sintético

Fecha de revisión: 2026-10-12  
Proyecto: Clínica del Dolor  
Alcance: preflight estático y diseño de la compuerta de conexión; no es una autorización de ejecución.

## 1. Resultado ejecutivo

Esta actividad se completó únicamente como revisión documental y estática. Se inspeccionaron la precedencia de configuración, el singleton de PDO, el punto de entrada público, el router y los mecanismos locales de preflight. No se abrió ninguna conexión, no se ejecutó SQL, no se hicieron peticiones HTTP y no se modificaron `.env`, PHP, fixtures, tablas, permisos, datos o documentación clínica.

El entorno sintético **no queda listo para conexión**. La configuración activa de `.env` resuelve `DB_DATABASE=bd_clinica`, por lo que cualquier ejecución que dependa de esa configuración queda bloqueada por fail-closed. El nombre autorizado para una ejecución futura es `clinica_dolor_demo_20261010_final`, pero su existencia, esquema, fixtures y aislamiento de la cuenta no se verificaron porque hacerlo requeriría conexión o evidencia externa autorizada.

El mecanismo recomendado es un proceso local separado, con una base exacta en allowlist, una cuenta de sólo lectura para preflight, una guarda externa que rechace cualquier base distinta de la sintética y una comprobación de `SELECT DATABASE()` antes de cualquier lectura. Las sesiones sintéticas directas disponibles son útiles para infraestructura aislada, pero no equivalen a un login real ni deben usarse como evidencia de autenticación.

## 2. Límites y garantías de esta ejecución

- La base `bd_clinica` se trató como protegida.
- No se abrió PDO ni se invocó `Database::getInstance()`.
- No se ejecutaron consultas `SELECT`, `SHOW`, `INSERT`, `UPDATE`, `DELETE`, DDL ni migraciones.
- No se levantó servidor ni se ejecutaron scripts de pruebas.
- No se accedió a pacientes, recetas, documentos, PDF o descargas.
- No se modificó el `.env` principal ni se creó una puerta trasera de autenticación.
- La Entrevista Conversacional quedó fuera de alcance.
- El worktree se preservó; los cambios locales previos no se limpiaron ni reordenaron.

## 3. Estado del repositorio

La rama y el commit observados antes de documentar el preflight fueron:

| Campo | Resultado |
|---|---|
| Rama | `Silvino` |
| HEAD | `1b08d9b4c41080c176fbe4588212863f3a1ac18c` |
| Worktree | Preservado; ya contenía cambios locales previos |
| Cambios de aplicación de esta actividad | Ninguno |
| Cambios de entorno de esta actividad | Ninguno |

El `git diff --check` previo terminó con código 0. Las advertencias observadas corresponden a conversiones históricas LF/CRLF, no a una edición realizada por esta actividad.

## 4. Archivos inspeccionados

Se revisaron completamente, sin ejecutar, los siguientes archivos de infraestructura:

- `tests/security/isolated-router.php`
- `tests/p04/verify-isolation.php`
- `tests/p11/preflight.php`
- `tests/phase02/doctor-read-only.php`
- `app/Config/Database.php`
- `public/index.php`
- `app/Core/Router.php`

También se consultaron, sin repetir auditorías completas, los documentos existentes:

- `docs/audits/TH_SEC_P1_EVIDENCE_GATE_01.md`
- `docs/audits/TH_SEC_P1_CONTAIN_PLAN_01.md`
- `docs/audits/TH_SEC_P1_TRIAGE_01_SECURITY_FINDINGS.md`
- `docs/audits/TH_SEC_AUTH_08_ROLE_AND_MULTICLINIC_AUTH_AUDIT.md`
- `docs/audits/TRADITIONAL_HISTORY_CLOSURE_MASTER.md`
- `docs/architecture/TH_SEC_ARCH_01_HISTORICAL_RECIPE_OWNERSHIP_V1.1.md`
- `docs/architecture/TH_SEC_ARCH_01_DATA_MODEL_PROPOSAL_V1.1.md`

## 5. Preflight y precedencia de configuración

`public/index.php` carga Composer, registra el manejador de errores, carga `constantes.php`, crea Dotenv con `Dotenv::createImmutable(__DIR__ . '/../')`, ejecuta `load()` y después construye el router. La aplicación, por tanto, toma la configuración del `.env` antes del despacho de rutas.

`app/Config/Database.php` lee `$_ENV['DB_HOST']`, `$_ENV['DB_DATABASE']`, `$_ENV['DB_USERNAME']` y `$_ENV['DB_PASSWORD']` sólo cuando se construye el singleton. No incluye el puerto en el DSN y no recibe un nombre de base como parámetro del método público.

La inferencia estática es la siguiente: un valor colocado en `$_ENV` antes de `Dotenv::createImmutable(...)->load()` no debería ser sustituido por Dotenv immutable; sin embargo, esa precedencia no se ejecutó en este gate. Por ello queda como `PARTIAL`, no como prueba dinámica. Además, una variable de proceso por sí sola no prueba que la cuenta MySQL carezca de acceso a otra base.

## 6. Revisión del singleton de PDO

El constructor de `Database` crea una sola conexión PDO con `ERRMODE_EXCEPTION`, `FETCH_ASSOC` y `ATTR_EMULATE_PREPARES=false`. `getInstance()` reutiliza el objeto mientras viva el proceso.

Implicaciones para una futura prueba:

1. La identidad de la base debe fijarse antes de la primera llamada a `getInstance()`.
2. No se debe cambiar `$_ENV['DB_DATABASE']` después de que el singleton haya sido creado.
3. El proceso de prueba debe ser nuevo por escenario de base.
4. La guarda debe comparar el nombre esperado antes de crear PDO.
5. La comprobación posterior debe confirmar la base efectiva, no sólo la variable de entorno.

No se modificó el singleton. No es seguro reutilizarlo para este gate sin un wrapper externo que aplique la allowlist y un proceso aislado.

## 7. Mecanismos existentes de aislamiento

### `tests/security/isolated-router.php`

Es un router local destinado a pruebas y no a exposición pública. Asigna `DB_DATABASE` mediante `CLINICA_AUDIT_DB` o, por defecto, `clinica_dolor_audit_20261010_iter3`, fija el document root de runtime y expone `/__audit-session/doctor` y `/__audit-session/patient`. Esos endpoints crean directamente claims JWT sintéticos con IDs de prueba y redirigen a la aplicación.

Conclusión: es un candidato de infraestructura, no evidencia de login real. Su base por defecto no coincide con la base autorizada de este gate y la variable acepta un valor arbitrario; no debe iniciarse sin un wrapper que fuerce exactamente `clinica_dolor_demo_20261010_final`, impida exposición pública y compruebe el entorno antes de arrancar. No se modificó ni ejecutó.

### `tests/p04/verify-isolation.php`

Es el candidato de preflight más directo para una futura autorización: exige la base exacta `clinica_dolor_demo_20261010_final` o un argumento/variable explícito que pase una allowlist, rechaza nombres protegidos como `bd_clinica`, carga credenciales sin imprimirlas, comprueba la base efectiva y revisa tablas, pacientes sintéticos, clínicas, módulos y ausencia de filas P04 fuera de los pacientes sintéticos. El archivo contiene lecturas directas PDO; por eso no se ejecutó en esta fase.

No demuestra por sí solo los privilegios MySQL de la cuenta ni un login real.

### `tests/p11/preflight.php`

Fija la base sintética allowlisted y revisa, sólo mediante lecturas, columnas y filas de evaluación del dolor, pacientes y finalización. Complementa el preflight de P04, pero no prueba grants MySQL, login real ni autorización de todos los recursos.

### `tests/phase02/doctor-read-only.php`

Está diseñado para una futura verificación de sólo lectura: acepta la base sintética exacta, comprueba `APP_KEY`, crea JWT sintéticos, realiza GET y compara snapshots antes/después para detectar escrituras, además de revisar acceso anónimo y cruzado. No se ejecutó porque conectaría a la base y requeriría un servidor HTTP. La generación directa de JWT no debe presentarse como login real.

## 8. Restricción de base de datos

La base autorizada para futuras pruebas es:

```text
clinica_dolor_demo_20261010_final
```

La configuración actual del proyecto apunta a `bd_clinica`. La diferencia es un bloqueo determinante. No se intentó corregirla editando `.env`, porque el encargo exige preservar la configuración principal y no permite asumir que `bd_clinica` sea sintética.

El estado `SYNTHETIC_DB_EXISTENCE_VERIFIED` debe permanecer `NO` hasta que el propietario autorice una conexión explícita y se verifique la base efectiva dentro de un proceso aislado.

## 9. Evaluación de la cuenta MySQL

No se ejecutó `SHOW GRANTS`, no se consultó metadata y no se creó ninguna cuenta. Por tanto, no está demostrado que la cuenta configurada no pueda leer o escribir `bd_clinica`.

Requisito mínimo para la siguiente fase:

- cuenta dedicada al entorno sintético;
- acceso únicamente a `clinica_dolor_demo_20261010_final`;
- sin privilegios sobre `bd_clinica` ni otras bases clínicas;
- permisos de escritura sólo si el propietario autoriza pruebas de persistencia, separados de la cuenta de preflight de sólo lectura;
- evidencia de grants entregada por el responsable de infraestructura, sin almacenar contraseñas en el repositorio.

Hasta contar con esa evidencia, `DB_ACCOUNT_ISOLATION_VERIFIED` es `NO`.

## 10. Esquema y fixtures requeridos

La siguiente matriz es un contrato de preparación, no una confirmación de existencia:

| Área | Requisitos sintéticos a confirmar en fase autorizada |
|---|---|
| Clínicas | `9101` y `9102`, independientes |
| Médicos | cuentas sintéticas asociadas a cada clínica |
| Pacientes | `9301` y `9302`, con clínicas distintas |
| Acceso | relación médico–clínica y autorización de paciente |
| Historia | nueve módulos y respuestas sintéticas mínimas |
| P04/P11 | filas requeridas por sus preflights |
| Recetas | registros internos con owner verificable y casos externos fail-closed |
| COFEPRIS | folios sintéticos con contexto autorizado o bloqueo explícito |
| Documentos | mapping privado y casos sin owner, sin PDF/descarga en esta fase |
| Roles | catálogo técnico de roles, permisos y superadministrador verificable |

No se creó ni cargó ningún fixture en esta actividad.

## 11. Dependencias de roles y superadministrador

Las auditorías previas no demostraron un catálogo técnico de roles/permisos, una identidad de superadministrador ni una membresía multiclínica verificable. La arquitectura V1.1 propone que las reconciliaciones y vínculos históricos sean exclusivos de un superadministrador verificable, pero eso es un diseño no implementado.

En consecuencia:

- `ROLE_CATALOG_VERIFIED: BLOCKED`.
- `SUPERADMIN_IMPLEMENTATION_VERIFIED: BLOCKED`.
- No se pueden probar transferencias, conciliaciones ni acceso histórico multiclínica.
- Los registros sin owner verificable deben mantenerse fail-closed.

## 12. Procedimiento seguro recomendado

La secuencia propuesta para una futura autorización es:

1. Revisar el hash/estado del worktree sin modificarlo.
2. Lanzar un proceso local separado con un wrapper que sólo acepte `clinica_dolor_demo_20261010_final`.
3. Cargar credenciales desde el entorno del proceso, nunca desde documentos ni argumentos visibles.
4. Verificar host, base permitida y modo de pruebas antes de crear PDO.
5. Crear primero una conexión de sólo lectura y comprobar la base efectiva.
6. Ejecutar `tests/p04/verify-isolation.php` como preflight allowlisted, seguido de `tests/p11/preflight.php` y, si se autoriza servidor, `tests/phase02/doctor-read-only.php`.
7. Confirmar fixtures y autorización propia/cruzada sin realizar escrituras.
8. Obtener autorización independiente para cualquier prueba de persistencia; usar un proceso y cuenta de escritura sintética separados.
9. Limitar HTTP a localhost y conservar capturas/logs sin datos sensibles.
10. Detener el proceso y revisar que no haya cambiado ninguna base protegida.

La autenticación real debe probarse mediante el flujo de login existente. Los endpoints de sesión directa de `isolated-router.php` sólo pueden servir para pruebas de harness previamente aprobadas y deben etiquetarse como sesión sintética inyectada, no como login.

## 13. Orden de autorización de pruebas

Se recomienda separar las fases:

- **A — Inspección estática:** completada en este documento.
- **B — Evidencia de aislamiento:** requiere base exacta, cuenta aislada y autorización del propietario.
- **C — Autorización de owner:** debe cubrir base, fixtures, cuentas, pacientes sintéticos y alcance de pruebas.
- **D — Ejecución:** sólo después de A–C; primero lecturas, luego persistencia sintética si se autoriza.

Este documento no constituye la autorización de las fases B, C o D.

## 14. Evidencia faltante y bloqueos

1. Existencia efectiva de `clinica_dolor_demo_20261010_final`.
2. Host/puerto efectivos y allowlist operativa.
3. Grants de la cuenta y ausencia de acceso a `bd_clinica`.
4. Esquema compatible y fixtures sintéticos completos.
5. Login sintético real, distinto de un JWT inyectado.
6. Catálogo de roles/permisos y superadministrador.
7. Owner verificable para recetas externas, documentos y COFEPRIS.
8. Aprobación explícita para ejecutar lecturas y, en su caso, escrituras sintéticas.

No se deben marcar estas evidencias como PASS por inspección de código o por la existencia de scripts.

## 15. Recomendación final

La infraestructura documental existente es reutilizable con restricciones: `tests/p04/verify-isolation.php` debe ser el primer preflight dinámico cuando exista autorización; P11 y Phase02 son complementarios. `isolated-router.php` no debe usarse sin wrapper de base exacta ni como prueba de login.

El gate queda cerrado para conexión. No se recomienda cambiar código, `.env`, esquema o fixtures para desbloquearlo. El siguiente paso es obtener evidencia de aislamiento de cuenta y autorización del propietario; después podrá solicitarse por separado la ejecución del preflight de sólo lectura.

## 16. Estado formal

```text
TH_SEC_ENV_READY_01:
- STATUS: COMPLETE
- CURRENT_BRANCH: Silvino
- CURRENT_HEAD: 1b08d9b4c41080c176fbe4588212863f3a1ac18c
- WORKTREE_PRESERVED: YES
- EXISTING_ISOLATION_MECHANISMS: tests/security/isolated-router.php; tests/p04/verify-isolation.php; tests/p11/preflight.php; tests/phase02/doctor-read-only.php
- RECOMMENDED_PREFLIGHT_SCRIPT: tests/p04/verify-isolation.php
- RECOMMENDED_ISOLATION_METHOD: proceso local separado; base exacta en allowlist; wrapper fail-closed; cuenta PDO de solo lectura; comprobación de SELECT DATABASE(); sin usar JWT sintético como evidencia de login
- ENV_PRECEDENCE_VERIFIED: PARTIAL — revisión estática; sin ejecución
- DATABASE_SINGLETON_REVIEWED: YES
- DB_ACCOUNT_ISOLATION_VERIFIED: NO
- SYNTHETIC_DB_NAME: clinica_dolor_demo_20261010_final
- SYNTHETIC_DB_EXISTENCE_VERIFIED: NO
- STATIC_ISOLATION_REVIEW: PARTIAL
- READY_FOR_READONLY_CONNECTION_APPROVAL: NO
- READONLY_CONNECTION_EXECUTED: NO
- REAL_DATABASE_ACCESSED: NO
- DB_QUERIES_EXECUTED: NONE
- ROLE_CATALOG_VERIFIED: BLOCKED
- SUPERADMIN_IMPLEMENTATION_VERIFIED: BLOCKED
- REQUIRED_SCHEMA: clínicas, médicos, pacientes, autorización de acceso, nueve módulos P01-P11, recetas internas/externas, COFEPRIS, documentos y auditoría según contratos existentes; existencia no verificada
- REQUIRED_FIXTURES: clínicas 9101/9102; médicos y pacientes sintéticos 9201/9202 y 9301/9302; autorización propia/cruzada; respuestas P04/P11; owner de receta y casos sin owner; COFEPRIS; mapping documental; roles y superadministrador
- UNRESOLVED_BLOCKERS: .env activo apunta a bd_clinica; base sintética no verificada; grants no verificados; esquema/fixtures no verificados; login real no verificado; roles y superadministrador no verificados; owners documentales no verificados
- NEXT_AUTHORIZATION_REQUIRED: autorización del propietario para conectar al entorno sintético, evidencia de cuenta sin acceso a bd_clinica, confirmación de fixtures/alcance y autorización separada para cualquier escritura sintética
- DOCUMENTATION_CREATED: docs/audits/TH_SEC_ENV_READY_01.md
- MASTER_CLOSURE_UPDATED: YES — sección de estado añadida sin modificar secciones anteriores
- GLOBAL_DIFF_CHECK: PASS — código 0; advertencias históricas LF/CRLF
- APPLICATION_CODE_CHANGES: NONE
- ENV_CHANGES: NONE
- SCHEMA_CHANGES: NONE
- DATA_CHANGES: NONE
- SQL_EXECUTED: NONE
- HTTP_REQUESTS: NONE
- COMMITS: NONE
- DEPLOYMENTS: NONE
- P1_REMEDIATION_COMPLETED: NO
- IMPLEMENTATION_AUTHORIZED: NO
- PRODUCTION_READY: NO
```

