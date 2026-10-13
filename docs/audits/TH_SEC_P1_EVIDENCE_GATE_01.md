# TH-SEC-P1-EVIDENCE-GATE-01 — Verificación de entorno seguro y preparación P1

**Proyecto:** Clínica del Dolor  
**Ámbito:** Historia Clínica tradicional P01–P11  
**Fecha:** 2026-10-12  
**Modo:** READ-ONLY + DOCUMENTACIÓN  
**Cambios de aplicación:** NO AUTORIZADOS  
**Pruebas con escrituras:** NO AUTORIZADAS  
**Producción:** PROHIBIDA  
**Exclusión:** Entrevista Conversacional.

## A. Preflight

| Control | Resultado |
|---|---|
| Directorio | `C:\xampp\htdocs\clinica-dolor` |
| Rama | `Silvino` |
| HEAD | `1b08d9b4c41080c176fbe4588212863f3a1ac18c` |
| HEAD esperado | Coincide |
| Worktree | Preservado; existían modificaciones y archivos no rastreados previos |
| Staging/commit/push | No ejecutados |
| Documentación solicitada | Disponible y revisada como fuente de este gate |
| Entrevista Conversacional | Fuera de alcance; no se audita ni modifica |
| `git diff --check` | PASS al final; advertencias históricas LF/CRLF solamente |

No se ejecutaron `git clean`, `git reset --hard`, checkout destructivo, staging ni despliegue. No se imprimieron secretos, contraseñas, tokens ni valores sensibles de `.env`.

## B. Entorno sintético y bloqueo

### Fuente actual de configuración

`app/Config/Database.php` construye PDO usando `$_ENV['DB_HOST']`, `$_ENV['DB_DATABASE']`, `$_ENV['DB_USERNAME']` y `$_ENV['DB_PASSWORD']`. `public/index.php` carga `.env` con Dotenv antes de crear `App\Core\Router`.

La configuración activa resuelve `DB_DATABASE=bd_clinica`. La guarda fail-closed ya utilizada en auditorías anteriores bloquea la conexión antes de PDO cuando la base no es allowlisted. En esta fase no se modificó `.env`, no se sobrescribió la conexión y no se ejecutó SQL.

**Resultado:** `SYNTHETIC_ENVIRONMENT_READY: NO`.

### Base sintética esperada

La base indicada para una fase posterior es:

`clinica_dolor_demo_20261010_final`

No se confirmó su existencia ni se consultó su esquema en esta ejecución.

### Mecanismos aislados encontrados

| Mecanismo | Evidencia | Evaluación |
|---|---|---|
| `tests/security/isolated-router.php` | Fuerza `CLINICA_AUDIT_DB`/`DB_DATABASE`, restringe assets y corre en un router local | Candidato de servidor aislado; no ejecutado |
| `tests/p04/verify-isolation.php` | Exige nombre allowlisted, valida credenciales locales, `SELECT DATABASE()` y tablas | Candidato de preflight; no ejecutado |
| `tests/p11/preflight.php` | Exige `--database=clinica_dolor_demo_20261010_final`, valida columnas y pacientes sintéticos | Candidato de lectura; no ejecutado |
| `tests/phase02/doctor-read-only.php` | Comprueba base allowlisted y modo de lectura | Candidato de lectura; no ejecutado |

`tests/security/isolated-router.php` tiene un valor por defecto `clinica_dolor_audit_20261010_iter3` y endpoints locales `__audit-session/doctor`/`patient` que firman claims directamente. Es útil para pruebas locales históricas, pero esos endpoints no equivalen al login real y no deben usarse como prueba de autorización o superadmin. Tampoco se debe exponer el router públicamente.

### Requisitos previos para conexión futura sólo lectura

1. Ejecutar en un proceso local aislado, no sobre el servidor público.
2. Proporcionar explícitamente `clinica_dolor_demo_20261010_final`; nunca tomar `.env` como autorización implícita.
3. Comprobar que el host/puerto/credenciales apuntan al servidor local sintético.
4. Abrir PDO únicamente con el `dbname` allowlisted.
5. Ejecutar inmediatamente `SELECT DATABASE()` y abortar si no coincide exactamente.
6. Inspeccionar tablas/columnas requeridas y permitir sólo consultas de lectura durante el gate.
7. Verificar dos clínicas y recursos sintéticos A/B antes de cualquier HTTP.
8. Para pruebas mutantes posteriores, usar una base descartable separada, guardas explícitas y limpieza controlada autorizada.

### Procedimiento futuro de sólo lectura

El procedimiento recomendado es reutilizar un preflight existente, ejecutado con el argumento/nombre allowlisted y en un proceso aislado. No se debe ejecutar en esta fase. El preflight debe devolver únicamente estado, nombre de base activo, tablas/columnas presentes y metadatos sintéticos mínimos; nunca credenciales, tokens, contenido clínico o documentos.

Riesgos del procedimiento:

- el servidor MySQL podría tener credenciales con acceso a más de una base;
- `DB_DATABASE` del proceso podría diferir de la conexión efectiva;
- el router sintético podría emitir claims directos y falsear un login real;
- un test de preparación podría escribir si se usa el script equivocado;
- el nombre de base no prueba por sí solo que el contenido sea sintético.

Autorización adicional necesaria: confirmación del propietario para abrir la base allowlisted y ejecutar pruebas, más confirmación de que la cuenta MySQL no tiene acceso operativo a `bd_clinica`.

## C. Mecanismo seguro de conexión futura

La conexión segura futura debe separar tres compuertas:

```text
Proceso local aislado
  -> nombre de base explícito y allowlisted
  -> conexión PDO con dbname forzado
  -> SELECT DATABASE() == clinica_dolor_demo_20261010_final
  -> inspección de esquema/fixtures
  -> pruebas autorizadas
```

No es suficiente cambiar `$_ENV['DB_DATABASE']` después de cargar una aplicación si la conexión ya fue inicializada. El control debe ejecutarse antes de `Database::getInstance()` y repetir la comprobación de base activa. No se crea ni modifica ese mecanismo ahora.

## D. Roles y superadministrador

### Evidencia estática

- `LoginModel::validaDoctor()` autentica usuario/contraseña activa y emite claims con `vista=clinica` y `rol=Doctor`.
- `LoginModel::validaPaciente()` emite claims con `vista=historia-clinica` y `rol=Paciente`.
- `AuthMiddleware::authPermisos()` valida cookie/JWT, expiración y coincidencia de vista; devuelve `id_clinica`, `id_usuario`, `rol` y token.
- `AuthMiddleware::authAny()` valida cookie/JWT/expiración, pero no aplica una vista esperada.
- El fixture estático contiene `clinicas`, `doctores`, `accesos_doctores`, pacientes y accesos de paciente; no demuestra un catálogo de roles/permisos ni superadmin.

Un claim `rol` no prueba privilegio vigente. No se verificó una tabla de roles, permisos, membresías multiclínica, MFA o fuente de identidad de superadministrador.

```text
SUPERADMIN_IMPLEMENTATION_VERIFIED: BLOCKED
ROLE_CATALOG_VERIFIED: BLOCKED
```

Evidencia principal: `app/Models/LoginModel.php`, `app/Middleware/AuthMiddleware.php`, `tests/fixtures/security-fixtures.sql` y rutas/controladores previamente auditados.

### Evidencia sintética requerida

Para cambiar `BLOCKED` a una conclusión positiva se necesitaría:

- catálogo de roles y permisos real de la base sintética;
- asignación explícita de usuarios sintéticos a roles;
- identidad sintética de superadmin y prueba de autenticación por el flujo real;
- alcance de clínica/membresía por operación;
- prueba negativa de doctor/admin intentando reconciliar owner;
- auditoría de la aprobación superadmin sin facultades clínicas automáticas.

No se inventan IDs, nombres de rol o privilegios.

## E. Cadena efectiva de autorización PDF

### Middleware global verificado

`app/Core/Router.php:20-40` resuelve la ruta y llama `enforceSensitiveRoute()` antes del controlador. En `:54-56` aplica:

```php
if (str_starts_with($uri, '/descargar/') || str_starts_with($uri, '/pdf/')) {
    (new AuthMiddleware('clinica'))->authPermisos();
}
```

Por tanto, el PDF externo sí tiene autenticación global de contexto `clinica` cuando el request atraviesa este router.

```text
PDF_GLOBAL_MIDDLEWARE_VERIFIED: YES — app/Core/Router.php:38-56
```

Esto no demuestra permiso de imprimir, clínica emisora, owner del recurso ni estado documental.

### Cadena del PDF externo

```text
POST /pdf/receta-externo/{id}
  -> routes.php:234
  -> Router::dispatch()/enforceSensitiveRoute():38-56
  -> authPermisos('clinica')                         [autenticación global]
  -> PdfController::pdfRecetaExterno():239-295
  -> RecetaExternoModel::receta($id)
  -> TCPDF
  -> respuesta
```

Controles separados:

| Control | Resultado |
|---|---|
| Autenticación | PRESENTE globalmente |
| Perfil Doctor | Implícito en `vista=clinica`; catálogo funcional no verificado |
| Permiso de imprimir | NO VERIFICADO; no hay permiso específico visible |
| Clínica emisora | NO representada en el flujo/modelo inspeccionado |
| Owner del recurso | NO VERIFICADO / no visible |
| Estado documental | NO VERIFICADO |
| Generación | Sí, TCPDF tras resolver por ID |

La conclusión anterior de “sin autenticación” debe refinarse: el método no tiene guard local, pero el router global sí protege la ruta. El riesgo P1 permanece por autorización de recurso, no por falta demostrada de sesión.

## F. Controles P1-A independientes

El primer lote independiente sólo puede cubrir lo que no depende de una clínica emisora inexistente:

1. Mantener el middleware global de PDF como requisito obligatorio del dispatcher.
2. Añadir una comprobación explícita de método/permiso de impresión si existe una fuente real de permisos.
3. Rechazar ID inválido antes de construir el documento.
4. Evitar que el controlador genere PDF si la resolución del recurso no devuelve una relación autorizable.
5. Conservar respuestas genéricas y logs de auditoría sin contenido clínico.

Estos controles no cierran P1-PDF-001 por sí solos. La clínica emisora y el owner externo siguen sin verificarse.

```text
CATEGORY_A_READY_FOR_APPROVAL: PARTIAL — auth gate global verified; resource authorization not ready
```

Propuesta de cambio mínimo futura: centralizar una decisión `authorize(resource, operation, actor)` antes de TCPDF, sin inventar owner. Condición de aceptación: una prueba positiva propia y pruebas negativas ajena/sin owner; ninguna prueba puede usar `bd_clinica`.

## G. Impacto operativo de contenciones externas

### Opción A — Contención total temporal

Bloquear detalle, búsqueda, edición, impresión, descarga y nuevas operaciones externas mientras no exista owner verificable.

- Rutas afectadas: detalle, edición, AJAX, POST externo, PDF y descargas relacionadas.
- Exposición residual: baja para esas superficies, si el bloqueo es servidor-side.
- Atención clínica: las recetas eventuales para personas sin expediente quedarían detenidas.
- Registros existentes: no se modifican, pero podrían quedar no consultables.
- Dependencia: ninguna nueva tabla para bloquear; owner definitivo sí requiere arquitectura.
- Pruebas: denegación sin datos para A/B/sin owner.
- Autorización: propietario clínico, seguridad y responsable operativo.

### Opción B — Contención diferenciada

Bloquear consulta, edición, PDF y descarga; conservar emisión sólo si se demuestra propiedad de origen con el modelo vigente.

- Ventaja: menor interrupción de emisión.
- Problema: el código inspeccionado no muestra una clínica emisora persistida.
- Estado: no se puede declarar viable; `RESOURCE_OWNER_NOT_VERIFIABLE`.
- Riesgo: mantener una ruta de alta sin owner produce registros que luego no pueden autorizarse.

### Opción C — Habilitación posterior a arquitectura

Mantener bloqueadas las superficies afectadas hasta implementar owner emisor, vínculo auditable, roles y pruebas A/B. Es la opción recomendada para la solución definitiva, pero no es una implementación actual.

## H. Inventario de descargas

### Ruta y control

`GET /descargar/{ruta}/{archivo}` está registrada en `app/Config/routes.php:239`. Tiene doble autenticación en el flujo normal: el router global (`app/Core/Router.php:54-56`) y `DownloadController::download()` (`app/Controllers/DownloadController.php:11-12`).

El controlador:

- calcula `realpath(public/storage)`;
- normaliza `ruta` con `trim`;
- aplica `basename` al archivo;
- comprueba que el archivo esté bajo el prefijo permitido;
- sirve el contenido con `readfile()`.

Esto cubre path containment, no autorización de propietario clínico.

### Consumidores encontrados

| Consumidor | Archivo/línea | Tipo aparente | Asociación visible |
|---|---|---|---|
| Laboratorio | `LaboratorioModel.php:156`, `app/views/clinica/laboratorio.php:6` | Clínico | Registro de laboratorio produce ruta/nombre; endpoint no recibe paciente |
| Nota subsecuente | `NotaSubsecuenteModel.php:442` | Clínico | Datos de laboratorio/notas; endpoint no recibe recurso explícito |
| COFEPRIS | No se encontró `/descargar` directo en los consumidores revisados; usa `/pdf/cofepris/{id}` | Documental clínico | Recurso se autoriza en PDF actual por paciente, con riesgos adicionales |
| P11 / Doctor-D40 | Dependencias no demostradas en la búsqueda actual | Desconocido | Requiere inventario del despliegue/superficie real |

No se modificaron storage, archivos, permisos ni enlaces. La recomendación futura es separar archivos no clínicos claramente clasificados de descargas clínicas por recurso. Para archivos clínicos sin mapping, la opción segura es bloqueo temporal.

## I. Dependencias COFEPRIS

Operaciones diferenciadas:

| Operación | Ruta | Control actual | Dependencia |
|---|---|---|---|
| Tabla por paciente | `/buscar/tabla-cofepris/{idPaciente}` | `guardPatient` + `assertPatientAccess` | Paciente vigente |
| Detalle por ID | `/buscar/cofepris/{idCofepris}` | Debe resolverse contra paciente; dinámica bloqueada | Call-site completo |
| Detalle principal | `/cofepris/{idCofepris}` | Auth + assertion del paciente del modelo | Actual, no histórico |
| Carpeta | `/cofepris/carpeta/{idCarpeta}` | Auth Doctor; scope no visible | Relación carpeta-clínica |
| Surtido | `/cofepris/edit-surtido` | Auth Doctor; update por ID | Recurso/paciente/permiso |
| Alta folios | `/cofepris/insert-folios` | Auth Doctor; owner no confirmado | Contrato de folios |
| PDF | `/pdf/cofepris/{id}` | Router + auth local + assertion | Propiedad histórica/documento |
| Folios globales | `/buscar/tabla-cofepris-folios` | Revisión de alcance requerida | Catálogo global o por clínica |

No se debe usar `pc_paciente.id_clinica` como prueba de clínica emisora histórica. Las operaciones por paciente actual pueden mantenerse separadas de carpetas/surtido hasta verificar el recurso.

## J. Preparación de pruebas sintéticas

No ejecutadas. Cada prueba requiere la base allowlisted, fixtures descartables, `SELECT DATABASE()` coincidente y autorización del propietario.

| ID | Fixture requerido | Operación | Resultado esperado | Condición | Estado |
|---|---|---|---|---|---|
| E-01 | Doctor A/B, clínicas 9101/9102 | Login real sintético | Claims derivados del login, no bypass | Acceso A/B preparado | NOT_EXECUTED |
| E-02 | Recurso externo A con owner A | GET detalle/listado | A permitido, B 403/404 genérico | Owner persistido/verificable | NOT_EXECUTED |
| E-03 | Externo sin owner | GET/PDF/edición | Fail-closed sin datos | Registro sintético ambiguo | NOT_EXECUTED |
| E-04 | Receta externa A | POST edición por B | Denegado, conteo sin cambio | Permiso de edición definido | NOT_EXECUTED |
| E-05 | PDF externo A | POST por A/B/sin sesión | Sólo A con permiso y owner | Middleware/router aislado | NOT_EXECUTED |
| E-06 | Archivo clínico A y ajeno | GET descarga | Sólo recurso autorizado | Registry o mapping sintético | NOT_EXECUTED |
| E-07 | Archivo huérfano/ruta manipulada | GET descarga | 403/404 sin path disclosure | Storage sintético | NOT_EXECUTED |
| E-08 | Carpeta COFEPRIS A/B | GET carpeta | A permitido, B bloqueado | Relación carpeta-clínica | NOT_EXECUTED |
| E-09 | Surtido A/B | POST edit | A permitido según permiso; B bloqueado | Recurso y permiso | NOT_EXECUTED |
| E-10 | PDF COFEPRIS A/B | GET PDF | Sólo paciente/recurso permitido | Fixture PDF sintético | NOT_EXECUTED |
| E-11 | A→B→C con receta emitida A | Lectura histórica | Política aprobada, sin transferencia | Owner/traslado implementados | NOT_EXECUTED |
| E-12 | Usuario admin/superadmin sintético | Reconciliación | Sólo superadmin, auditado | Catálogo real de roles | NOT_EXECUTED |

Preparación mínima: base explícita, dos clínicas, dos doctores, paciente sintético por clínica, receta externa con owner y otra sin owner, archivos no clínicos y clínicos sintéticos, carpetas/folios COFEPRIS vinculados, y snapshot de metadatos. No se deben copiar credenciales reales ni usar el endpoint `__audit-session` como prueba de login.

## K. Brechas de evidencia

1. `clinica_dolor_demo_20261010_final` no se consultó.
2. No se verificó que el host/usuario de `.env` no tenga permisos sobre `bd_clinica`; la guarda sólo impidió usarla.
3. No se verificó el esquema real de recetas externas, roles, documentos o COFEPRIS.
4. No se verificó middleware fuera del router PHP; el middleware global de este router sí fue identificado.
5. No se verificó el contenido/mapa real de `public/storage`.
6. No se verificó el login real con usuarios sintéticos.
7. No se verificaron respuestas HTTP, HTML, JSON, PDF, archivos ni escrituras.
8. No se verificó identidad ni permisos de superadmin.
9. No se verificó el despliegue Apache/Nginx/Plesk.

## L. Decisiones requeridas

1. Autorizar o no la contención total o diferenciada de recetas externas.
2. Confirmar si la emisión externa debe quedar bloqueada sin owner persistido.
3. Proporcionar el catálogo de roles/permisos y la identidad superadmin sintética.
4. Aprobar el owner histórico y el historial de traslados.
5. Decidir si las descargas clínicas se restringen a endpoints por recurso.
6. Definir scope de carpeta, folio y surtido COFEPRIS.
7. Autorizar una conexión posterior a la base sintética allowlisted.
8. Autorizar por separado cualquier cambio de esquema, storage, middleware o permisos.

## M. Primer lote recomendado

### Lote P1-A — Gate de PDF externo y resolución de recurso

**Objetivo:** no entregar PDF externo sin autenticación global, permiso de impresión y owner/recurso verificable.

**Estado:** diseñado, no listo para implementación.

**Controles ya confirmados:** middleware global de `/pdf/*` en `app/Core/Router.php:54-56`.

**Controles faltantes:** permiso de impresión, clínica emisora, owner, estado documental y vínculo a storage/original.

**Cambio mínimo futuro:** introducir una decisión de autorización antes de TCPDF que falle cerrado cuando el recurso no tenga owner. No se autoriza escribirla ahora.

**Pruebas:** E-03, E-05, E-10 y regresiones internas.  
**Condición para iniciar:** catálogo/owner verificables, base sintética allowlisted, aprobación del propietario.  
**Resultado actual:** `READY_FOR_IMPLEMENTATION_APPROVAL: NO`; `READY_FOR_TEST_EXECUTION: NO`.

## N. Criterios de autorización

El lote no puede declararse listo si:

- puede abrir `bd_clinica`;
- depende de un ID fijo o de un claim inventado;
- no existe una relación de owner/recurso verificable;
- habilita emisión, edición, PDF o descarga cruzada;
- usa el router de sesión sintética directa como sustituto del login real;
- no tiene pruebas positivas y negativas entre clínicas;
- puede escribir sin una base descartable y snapshot/cleanup controlado;
- bloquea un flujo crítico sin decisión del propietario.

El gate futuro debe distinguir `READY_FOR_IMPLEMENTATION_APPROVAL` de `READY_FOR_TEST_EXECUTION`. En esta ejecución ambos son **NO**.

## O. Estado formal

```text
TH_SEC_P1_EVIDENCE_GATE_01:

STATUS: COMPLETE
CURRENT_BRANCH: Silvino
CURRENT_HEAD: 1b08d9b4c41080c176fbe4588212863f3a1ac18c
WORKTREE_PRESERVED: YES; pre-existing local changes and untracked files preserved

SYNTHETIC_ENVIRONMENT_READY: NO — active .env resolves bd_clinica; fail-closed; no connection
SYNTHETIC_CONNECTION_EXECUTED: NO
REAL_DATABASE_ACCESSED: NO
ENV_MODIFIED: NO
GUARD_BYPASSED: NO

ROLE_CATALOG_VERIFIED: BLOCKED
SUPERADMIN_IMPLEMENTATION_VERIFIED: BLOCKED
PDF_GLOBAL_MIDDLEWARE_VERIFIED: YES — app/Core/Router.php:38-56
EXTERNAL_RESOURCE_OWNER_VERIFIED: NO — no owner relation visible; live schema blocked

CATEGORY_A_READY_FOR_APPROVAL: PARTIAL — global authentication gate exists; resource authorization unresolved
CATEGORY_B_OPERATIONAL_DECISION_REQUIRED: YES — external/PDF/download/COFEPRIS ambiguous surfaces
DOWNLOAD_RESOURCE_MAPPING: UNKNOWN — consumers in laboratory and nota subsecuente; clinical resource registry absent
COFEPRIS_RESOURCE_SCOPE: PARTIAL — patient detail/PDF current scope visible; folder/surtido scope unresolved

RECOMMENDED_FIRST_BATCH: P1-A PDF external gate and resource authorization design
RECOMMENDED_CONTAINMENT_OPTION: C until owner is verifiable; temporary B fail-closed requires owner approval
REQUIRED_FIXTURES: Doctor A/B, clinics 9101/9102, external owner A, ownerless record, files, COFEPRIS folder/surtido, superadmin catalog
TEST_EXECUTION_READY: NO
USER_DECISIONS_REQUIRED: external-flow containment, roles/superadmin, owner history, download registry, COFEPRIS scope, synthetic DB access

DOCUMENTATION_CREATED: docs/audits/TH_SEC_P1_EVIDENCE_GATE_01.md
MASTER_CLOSURE_UPDATED: YES — reference/status appendix only
GLOBAL_DIFF_CHECK: PASS (exit 0; existing LF/CRLF warnings only)

APPLICATION_CODE_CHANGES: NONE
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

**COMPLETE significa compuerta estática documentada, no entorno listo, no autorización de implementación y no seguridad verificada.**
