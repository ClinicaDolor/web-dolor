# TH-SEC-AUTH-08 — Auditoría de roles, superadministrador y autorización multiclínica

**Proyecto:** Clínica del Dolor  
**Fecha:** 2026-10-12  
**Modo:** auditoría estática y documentación; sólo lectura  
**Alcance:** Historia Clínica tradicional P01–P11 y dependencias de autenticación/autorización  
**Exclusión explícita:** Entrevista Conversacional; no se inspecciona para habilitarla ni se modifica.

## A. Resumen ejecutivo

La auditoría confirma estáticamente un modelo de autenticación basado en JWT dentro de una cookie y una identidad clínica derivada del acceso del doctor. El middleware comprueba presencia, firma/decodificación, expiración y vista, y `assertPatientAccess()` comprueba la clínica vigente del paciente para el contexto Doctor. No se encontró en el código auditado un catálogo técnico verificable de roles, permisos granulares, membresías históricas, propietario inmutable de receta, superadministrador ni reconciliación auditada.

El preflight fail-closed no permitió abrir PDO porque la configuración activa apunta a `bd_clinica`, fuera de la allowlist sintética. Por tanto, el esquema y los controles dinámicos de esta ejecución quedan **NO VERIFICADOS**. El fixture sintético disponible demuestra identidades de dos clínicas, doctores, accesos, pacientes y recetas internas; no demuestra por sí solo que exista un rol de superadministrador.

El resultado es **PARTIAL**: se completó la revisión estática y se documentaron los límites, pero no se puede aprobar la autorización multiclínica ni la identidad del superadministrador. Se identifican riesgos estáticos P1 en recetas externas, PDF externo, descargas y operaciones COFEPRIS por falta de una comprobación de propietario/recurso visible en los call-sites auditados. No se realizaron escrituras, migraciones, descargas, POST, cambios de código ni accesos a datos reales.

## B. Alcance y exclusiones

Se revisaron rutas, middleware, controladores, modelos, documentos de seguridad previos y el fixture sintético relacionados con:

- Login Doctor/Paciente y cierre de sesión.
- Identidad de clínica y paciente.
- P01–P11 de Historia Clínica tradicional.
- Directorio, detalle, recetas internas y externas, COFEPRIS, PDF y descargas.
- Aislamiento entre clínicas y autorización de acciones sensibles.

Quedan fuera la implementación y la auditoría de negocio de Entrevista Conversacional, así como cualquier remediación de ACT-05C-02. La presencia de servicios o archivos relacionados con esa superficie no se interpreta como autorización para modificarla.

## C. Preflight de seguridad y preservación

| Control | Resultado | Evidencia / limitación |
|---|---|---|
| Rama | PASS | `Silvino` |
| HEAD esperado | PASS | `1b08d9b4c41080c176fbe4588212863f3a1ac18c` |
| Worktree preservado | PASS | Había cambios locales previos; no se ejecutó reset, clean ni checkout destructivo. |
| Modo de auditoría | PASS | Lectura estática y documentación. |
| Base permitida | BLOCKED | `.env` activo resuelve `bd_clinica`; la guarda fail-closed detuvo la conexión antes de PDO. |
| Escrituras | PASS | No se ejecutaron SQL de escritura, POST, PDF ni descargas. |
| Producción | NO | No se declara preparación operativa. |

La comprobación de base imprimió `DB_NAME=bd_clinica` y `ALLOWLIST_FAIL`; la conexión no continuó. No se usó la base protegida para obtener evidencia.

## D. Estado del repositorio y evidencia disponible

El repositorio ya estaba sucio antes de esta auditoría, con modificaciones y archivos no rastreados pertenecientes a trabajos anteriores. Esta ejecución no los revirtió ni los mezcló con la auditoría. El único artefacto nuevo autorizado por este encargo es este informe y la referencia de estado agregada al cierre tradicional.

La evidencia disponible es de dos clases:

1. **Código actual y fixture estático:** suficiente para reconstruir el flujo de autenticación y detectar controles ausentes en call-sites.
2. **Observaciones de una ejecución allowlisted anterior, documentadas previamente:** se habían observado limitaciones de tablas de roles, transferencias, propietarios históricos y recetas externas. En esta ejecución no se revalidaron porque la configuración actual quedó bloqueada por la guarda.

## E. Esquema y entidades relevantes

El fixture `tests/fixtures/security-fixtures.sql` contiene, entre otras, `clinicas`, `doctores`, `accesos_doctores`, `pc_paciente`, `pc_paciente_acceso`, `receta_medica` y `receta_medica_externos` como contrato sintético estático. Sus datos de aislamiento usan clínicas 9101/9102, doctores 9201/9202, pacientes 9301/9302 y recetas 9401/9402.

Lo que sí puede sostenerse del fixture:

- `doctores.clinica_id` vincula al doctor con su clínica.
- `accesos_doctores` contiene usuario, contraseña y activo.
- `pc_paciente.id_clinica` identifica la clínica vigente del paciente.
- `receta_medica.id_paciente` permite enlazar una receta interna con el paciente.

Lo que el fixture no prueba:

- Tabla o catálogo de roles y permisos.
- Identidad, asignación o permiso especial de superadministrador.
- Historial de transferencias de paciente.
- Clínica emisora inmutable de una receta.
- Propietario verificable de una receta externa.
- Registro de conciliación, evidencia de identidad o auditoría append-only.
- Documento original, hash, cifrado, claves, retención o recuperación.

## F. Modelo de autenticación observado

`LoginModel::validaDoctor()` consulta la relación clínica/doctor/acceso, exige `activo=1`, verifica la contraseña y genera un token con `id_clinica`, `id_usuario`, nombre, vista `clinica` y rol `Doctor`. `validaPaciente()` recorre accesos de paciente activos/no expirados, verifica PIN y genera un token con vista `historia-clinica`.

`AuthMiddleware::authPermisos()` comprueba cookie, decodificación JWT con `APP_KEY`, expiración y coincidencia exacta de `vista`. `authAny()` comprueba cookie/JWT/expiración, pero no aplica una vista esperada. No se observó revalidación en base de la cuenta, revocación, permiso granular o estado actual de la membresía después de emitir el JWT.

Conclusión: existe autenticación de sesión, pero la autorización fina no está modelada como un catálogo verificable independiente.

## G. Sesiones, cookies y cierre

El token se transporta en cookie; el middleware establece opciones `HttpOnly`, `SameSite=Lax` y `Secure` condicionado a HTTPS. El cierre decodifica el JWT y limpia la sesión/cookie mediante `AuthMiddleware::cerrarSesion()`.

Estas medidas reducen exposición accidental, pero no demuestran revocación inmediata, rotación de sesión, invalidación por cambio de clínica o invalidación por pérdida de permiso. La duración observada del JWT es de siete días. No se ejecutó una prueba dinámica de expiración o revocación por el bloqueo de base.

## H. Usuarios, roles y catálogo de permisos

El código usa la cadena `rol` del JWT y la vista (`clinica` o `historia-clinica`), pero no se encontró un catálogo técnico verificable de roles o permisos para distinguir doctor, administrador y superadministrador. El fixture de seguridad sólo proporciona cuentas de acceso de doctores y pacientes; no proporciona tabla de roles/permisos ni una cuenta superadmin documentada.

Resultado: **ROLE_CATALOG_VERIFICATION: BLOCKED**. No debe inferirse que la cadena `rol=Doctor` concede o niega una operación sensible.

## I. Identidad y autoridad del superadministrador

No se estableció una identidad técnica verificable de superadministrador, una fuente de autorización, un mecanismo de MFA, una membresía global o una bitácora de aprobación para reconciliaciones. La arquitectura V1.1 propone que la conciliación sea exclusiva del superadministrador, pero es una propuesta no implementada.

Resultado formal: **SUPERADMIN_IDENTITY_VERIFICATION: BLOCKED**. No se autoriza ninguna implementación basada en una suposición de superadmin.

## J. Membresía de clínica y usuarios multiclínica

El modelo visible es una relación de clínica vigente: doctor → `doctores.clinica_id` y paciente → `pc_paciente.id_clinica`. No se observó una tabla de membresías múltiples con vigencia, alcance por operación, clínica activa seleccionada, ni historial de transferencias.

La consecuencia es que un usuario autenticado puede representar una clínica actual, pero no queda demostrada una autorización para actuar en varias clínicas ni para reconciliar propiedad histórica. La arquitectura V1.1 describe una sola clínica activa por paciente con historial permanente; ese contrato aún no está implementado.

## K. Autorización de recetas internas

El listado interno usa `RecetaModel::getRecetasByClinica($idClinica)`, que relaciona `receta_medica` con `pc_paciente` y filtra por `p.id_clinica = :id_clinica`. `RecetaController` y las búsquedas internas llaman `assertPatientAccess()` con el paciente de la receta antes de renderizar.

Esto proporciona contención por clínica vigente para las superficies observadas. No prueba propiedad histórica: `receta_medica` no conserva en el código auditado una clínica emisora inmutable, doctor emisor, owner histórico ni vínculo de transferencia. El insert interno conserva `id_paciente` y datos de la receta, pero no registra explícitamente clínica emisora ni usuario emisor.

## L. Autorización de recetas externas

`RecetaExternoModel` obtiene por ID y lista globalmente `receta_medica_externos`; sus operaciones de insert/update no muestran propietario clínico verificable. En el controlador, el detalle, edición, insert y update externos pasan por autenticación Doctor, pero no se observa `assertPatientAccess()`, una comprobación de clínica propietaria ni permiso especial.

La sección externa del directorio D14 fue contenida previamente en la vista/controlador, pero esto no protege las rutas independientes. En particular, deben tratarse como riesgo separado el detalle, edición, AJAX de búsqueda y PDF externo. La auditoría no declara las recetas externas globales ni seguras.

## M. PDF, almacenamiento y descargas

- PDF interno: el controlador carga la receta, obtiene el paciente y ejecuta `assertPatientAccess()` antes de generar el documento. Es aislamiento por clínica vigente, no propiedad histórica.
- PDF externo: en el call-site auditado no se observó `AuthMiddleware` ni una comprobación de propietario antes de generar el documento. Es un riesgo estático P1 que requiere prueba controlada en una fase autorizada.
- PDF COFEPRIS: el detalle realiza autenticación y `assertPatientAccess()` antes de abrir el PDF por paciente; permanecen riesgos en carpetas/folios y en operaciones que actualizan por ID sin revalidar el recurso.
- Descarga genérica: `DownloadController` autentica al contexto Doctor, normaliza la ruta y limita el `realpath` al almacenamiento, pero no comprueba autorización sobre el recurso clínico antes de `readfile()`.

ACT-05C-02 y los riesgos de PDF, descarga, almacenamiento, fidelidad del original y autorización documental permanecen abiertos. No se ejecutaron descargas ni se modificó almacenamiento.

## N. Matriz de permisos observada actualmente

| Actor/superficie | Lectura paciente propio/actual | Lectura cruzada | Edición | PDF/descarga | Evidencia |
|---|---:|---:|---:|---:|---|
| Paciente autenticado | Vista propia por `vista`/ID | No demostrada | Depende del módulo | No aplicable en este audit | Estático; dinámico bloqueado |
| Doctor de clínica vigente | Sí, mediante clínica actual | Debe fallar por `assertPatientAccess` en superficies que lo usan | En módulos internos, sujeto al call-site | Interno/COFEPRIS con controles parciales | Código; no prueba end-to-end |
| Doctor sobre receta externa | Endpoint autenticado | Riesgo: sin owner visible | Riesgo: update por ID | Riesgo: PDF sin auth visible | Código estático |
| Administrador | No existe catálogo verificable | No verificable | No verificable | No verificable | Bloqueado por catálogo |
| Superadministrador | No identidad técnica verificable | No verificable | No verificable | No verificable | Arquitectura propuesta, no implementación |
| Descarga por ruta | Auth Doctor | No recurso | No aplica | Riesgo: path-only | `DownloadController` |

## O. Matriz objetivo de autorización — pendiente de implementación

| Operación | Doctor clínica activa | Admin autorizado | Superadmin | Clínica destino histórica |
|---|---:|---:|---:|---:|
| Ver paciente vigente | Sí, con relación actual | Sí, según alcance | Sí, auditado | Sólo si política lo permite |
| Ver receta interna propia | Sí | Sí | Sí | No por defecto |
| Ver receta histórica asociada | Sólo lectura si contrato explícito | Sólo lectura si contrato explícito | Sí, auditado | Sólo lectura, sin transferencia |
| Editar/anular receta ajena | No | No salvo permiso de emisor definido | No automático | No |
| Vincular receta externa | No por defecto; sólo permiso explícito y evidencia | Sólo permiso explícito | Aprobar/reconciliar | No |
| Corregir vínculo contradictorio | No | No | Sí, append-only y auditado | No |
| Ver PDF/descargar | Sólo recurso autorizado | Sólo recurso autorizado | Sí, auditado | Sólo documento permitido |
| Cambiar clínica/transferir paciente | No | Según política separada | Aprobar y auditar | No |

Esta matriz es objetivo de diseño, no una declaración de capacidades actuales.

## P. Gaps de autorización y control

1. No hay evidencia técnica de roles/permisos ni de superadmin.
2. `authPermisos()` valida claims de sesión, no una autorización de operación contra un catálogo.
3. `assertPatientAccess()` usa clínica vigente del paciente y no owner histórico de receta.
4. Recetas externas carecen de owner clínico comprobable en los call-sites revisados.
5. Detalle/edición/AJAX/PDF externo no muestran controles equivalentes a los internos.
6. Descarga genérica autoriza la ruta/almacenamiento, no el recurso clínico.
7. COFEPRIS tiene operaciones por ID y rutas de carpeta/folio con controles de propiedad incompletos visibles.
8. No se observó bitácora de aprobación, evidencia de identidad ni conciliación append-only.
9. JWT de larga duración no demuestra revocación o revalidación de membresía.

## Q. Riesgo de escalamiento de privilegios

No se demostró un exploit dinámico porque la base real fue bloqueada y no se ejecutaron peticiones mutantes. Sí existe riesgo estático de que la ausencia de permisos por operación y la confianza en claims/view permita que cualquier identidad Doctor autenticada alcance operaciones cuya autoridad debería depender de owner, clínica emisora o permiso administrativo. El riesgo más claro es el acceso por ID en recursos externos y documentales.

No se debe resolver interpretando `rol` como superadmin ni agregando bypasses. La remediación requiere un catálogo y una fuente de verdad de permisos aprobados.

## R. Aislamiento entre clínicas

El aislamiento interno por paciente/clinica vigente está presente en varias rutas y fue documentado por TH-SEC-FIX-01. No se puede generalizar ese resultado a recetas externas, historial de transferencias, PDF externo, descargas genéricas o folios COFEPRIS.

La prueba A/B de clínicas 9101/9102 no se repitió en esta ejecución porque la configuración no permitió leer la base sintética. Por ello, la matriz dinámica de respuestas HTML/AJAX/HTTP queda **NO VERIFICADA** en este informe, aunque los filtros estáticos internos son visibles.

## S. Limitaciones del esquema y de la evidencia

La relación vigente `paciente → clínica` no basta para resolver propiedad histórica. Para cumplir AUTH-01..AUTH-08 se necesitarían, como mínimo, owner/emisor inmutable, historial de traslados, vínculos externos separados, verificación de identidad, permisos explícitos, documentos privados y auditoría de operaciones sensibles.

No se autoriza introducir esas tablas en esta auditoría. Los documentos de arquitectura V1.0/V1.1 permanecen sin modificaciones y deben considerarse propuestas, no evidencia de implementación.

## T. Integración futura recomendada

La siguiente fase autorizada debería definir una fuente única de identidad/rol/membresía y un servicio de autorización por recurso y operación. Ese servicio debe recibir actor, clínica activa, recurso, owner histórico, operación y motivo; fallar cerrado si falta cualquiera de las relaciones necesarias; y emitir una decisión auditable.

La integración debe cubrir receta interna, receta externa, COFEPRIS, PDF y descarga con el mismo contrato. No debe depender de ocultar filas en JavaScript ni de aceptar `id_clinica` desde GET/POST.

## U. Plan de pruebas futuro

Antes de cualquier implementación, preparar una base allowlisted distinta de `bd_clinica` y demostrar el nombre de base antes de abrir PDO. Después ejecutar, en datos sintéticos:

1. Login Doctor A/B y expiración/revocación.
2. Catálogo de roles, permisos y superadmin con evidencia de identidad.
3. Aislamiento de clínicas en HTML, AJAX, detalle, edición, PDF y descarga.
4. Receta histórica, traslado A→B y lectura sólo en destino.
5. Receta externa con owner, vínculo posterior y conflicto.
6. Corrección/anulación con autorización del emisor y auditoría.
7. Fail-closed para owner desconocido.
8. No-op SQL al reintentar una respuesta o finalización.
9. Evidencia de que los endpoints no aceptan contexto de clínica no autorizado.

Ninguna de estas pruebas se marca PASS en esta ejecución.

## V. Riesgos priorizados

| ID | Prioridad | Hallazgo | Estado |
|---|---|---|---|
| AUTH08-001 | P1 | Detalle/edición/AJAX de recetas externas sin owner/clinic assertion visible. | Riesgo estático; prueba dinámica bloqueada. |
| AUTH08-002 | P1 | PDF externo sin middleware/owner check visible en el controlador auditado. | Riesgo estático; NO VERIFICADO dinámicamente. |
| AUTH08-003 | P1 | Descarga genérica por ruta con autenticación, pero sin autorización del recurso clínico. | Riesgo estático; ACT-05C-02 relacionado. |
| AUTH08-004 | P1 | Operaciones COFEPRIS por ID/carpeta/folio con comprobación de propiedad incompleta visible. | Riesgo estático; no se ejecutaron operaciones. |
| AUTH08-005 | P1 | No se pudo establecer superadmin, catálogo de roles ni permisos granulares. | Bloqueo de evidencia, no PASS. |
| AUTH08-006 | P2 | Recetas internas se aíslan por clínica vigente, no por propietario histórico inmutable. | Limitación de modelo. |
| AUTH08-007 | P2 | JWT no muestra revalidación de membresía/revocación por operación. | Riesgo de diseño pendiente. |
| AUTH08-008 | P2 | No se observó auditoría append-only para reconciliación y vínculos. | Limitación de esquema. |

No hay P0 confirmado en esta ejecución; esto no convierte los P1 estáticos en aceptables.

## W. Decisiones y políticas AUTH-01..AUTH-08 incorporadas

Estas son decisiones de política aprobadas que deben orientar una futura implementación; no son capacidades actuales:

- **AUTH-01:** vinculación externa sólo por doctor/admin de la misma clínica con autorización explícita y evidencia; cross-clinic sólo superadmin; no transfiere propiedad.
- **AUTH-02:** una clínica activa por paciente; lectura histórica legítima y de sólo lectura para destino cuando proceda; clínica anterior conserva sus recetas; no edición/anulación por destino.
- **AUTH-03:** verificación de identidad oficial y evidencia; alternativa sólo con revisión superadmin; sin matching automático; no fotos de ID por defecto.
- **AUTH-04:** múltiples recetas externas por paciente; un vínculo activo por receta; sin auto-borrado/matching; contradicciones sólo superadmin con historial.
- **AUTH-05:** corrección/anulación mediante original inmutable, nueva corrección, permiso del emisor, justificación y auditoría; superadmin no obtiene poderes clínicos automáticos.
- **AUTH-06:** si la procedencia histórica es verificable, lectura histórica indicada; reconstrucción sólo informativa y auditada; owner no verificable implica fail-closed.
- **AUTH-07:** almacenamiento privado, autorización por recurso, original inmutable, SHA-256, cifrado/rotación, backups/recuperación, auditoría y retención; política aprobada, implementación pendiente.
- **AUTH-08:** únicamente superadmin puede aprobar reconciliaciones de owner histórico, vínculos cross-clinic y correcciones contradictorias; identidad técnica y permisos aún pendientes.

No se modifican los documentos arquitectónicos V1.0/V1.1; esta sección sólo deja constancia de la incorporación documental en el cierre tradicional.

## X. Recomendación final y estado formal

Detener la implementación de roles, superadmin y autorización multiclínica hasta disponer de una conexión allowlisted a `clinica_dolor_demo_20261010_final` (o una base sintética equivalente), un catálogo de roles/permisos y una identidad de superadmin verificable. Mantener fail-closed las superficies sin owner comprobable y no declarar seguridad de recetas externas, PDF, descargas o COFEPRIS.

```text
TH_SEC_AUTH_08:
- STATUS: PARTIAL
- CURRENT_BRANCH: Silvino
- CURRENT_HEAD: 1b08d9b4c41080c176fbe4588212863f3a1ac18c
- WORKTREE_PRESERVED: YES; pre-existing local changes preserved
- AUTHENTICATION_MODEL: JWT in cookie; doctor/patient login through LoginModel
- SESSION_MODEL: cookie token; HttpOnly; SameSite=Lax; Secure when HTTPS; 7-day expiry observed
- USER_TABLE_VERIFIED: NO — live synthetic DB blocked; static doctores/accesos_doctores fixture inspected
- ROLE_CATALOG_VERIFIED: BLOCKED
- PERMISSION_MODEL_VERIFIED: NO
- SUPERADMIN_IDENTITY_VERIFIED: BLOCKED
- SUPERADMIN_AUTHORIZATION_SOURCE: NOT ESTABLISHED
- CLINIC_MEMBERSHIP_MODEL: current doctor/patient clinic relation; historical/multi-clinic model not verified
- MULTICLINIC_USER_SUPPORT: NO EVIDENCE
- INTERNAL_RECIPE_AUTHORIZATION: PARTIAL — current-clinic checks visible; historical owner not implemented/verified
- EXTERNAL_RECIPE_AUTHORIZATION: NOT VERIFIED; static owner/clinic gaps remain
- HISTORICAL_RECIPE_READ_ACCESS: NOT IMPLEMENTED/VERIFIED
- CROSS_CLINIC_WRITE_PROTECTION: PARTIAL; internal patient guards visible, external/COFEPRIS write checks incomplete in call-sites
- PDF_AUTHORIZATION: PARTIAL — internal/COFEPRIS current-clinic guards visible; external PDF risk static
- DOWNLOAD_AUTHORIZATION: PARTIAL/AT RISK — path containment and Doctor auth, resource authorization not visible
- ROLE_ESCALATION_FINDINGS: P1 static risk; no dynamic exploit executed
- CROSS_CLINIC_ACCESS_FINDINGS: P1 static risks in external/document paths; dynamic matrix NO VERIFICADO
- APPROVED_AUTH_01_07_INCORPORATED: YES, as policy documentation only
- CURRENT_PERMISSION_MATRIX: documented in section N; incomplete and not live-verified
- TARGET_PERMISSION_MATRIX: documented in section O; design target, not implemented
- CONFIRMED_SECURITY_FINDINGS: static authorization gaps listed AUTH08-001..004
- STATIC_SECURITY_RISKS: AUTH08-001..008
- UNKNOWN_OR_UNVERIFIED: live schema, role catalog, superadmin, dynamic cross-clinic tests, PDF/download behavior
- P0_FINDINGS: NONE CONFIRMED
- P1_FINDINGS: AUTH08-001..005
- P2_FINDINGS: AUTH08-006..008
- P3_FINDINGS: NONE
- RECOMMENDED_AUTHORIZATION_DESIGN: resource-and-operation policy service with immutable owner, explicit memberships, fail-closed unknown owner, audit trail, and superadmin-only reconciliation
- USER_DECISIONS_REQUIRED: authorize synthetic DB access, define role/superadmin source, approve historical-owner model and document policy implementation
- DOCUMENTATION_CREATED: docs/audits/TH_SEC_AUTH_08_ROLE_AND_MULTICLINIC_AUTH_AUDIT.md; master status appendix
- APPLICATION_CODE_CHANGES: NONE
- SCHEMA_CHANGES: NONE
- DATA_CHANGES: NONE
- ROLE_CHANGES: NONE
- PERMISSION_CHANGES: NONE
- MIGRATIONS: NONE
- COMMITS: NONE
- DEPLOYMENTS: NONE
- GLOBAL_DIFF_CHECK: PASS (exit 0; existing line-ending warnings only)
- IMPLEMENTATION_AUTHORIZED: NO
- PRODUCTION_READY: NO
```

## Evidencia estática principal

- `app/Middleware/AuthMiddleware.php`: claims JWT, `authPermisos()`, `authAny()` y `assertPatientAccess()`.
- `app/Models/LoginModel.php` y `app/Controllers/LoginController.php`: login y emisión/cierre de sesión.
- `app/Controllers/ClinicaController.php`: directorio, recetas externas y operaciones por ID.
- `app/Controllers/RecetaController.php`, `BusquedasController.php`, `PdfController.php`, `DownloadController.php` y `CofeprisController.php`.
- `app/Models/RecetaModel.php`, `RecetaExternoModel.php`, `CofeprisModel.php` y `ClinicaModel.php`.
- `app/Config/routes.php`.
- `tests/fixtures/security-fixtures.sql`.
- `docs/audits/TH_SEC_FIX_01_RECIPE_TENANT_AUTHORIZATION.md`.
- `docs/audits/TH_SEC_FIX_01B_HISTORICAL_RECIPE_OWNERSHIP.md`.
- `docs/architecture/TH_SEC_ARCH_01_HISTORICAL_RECIPE_OWNERSHIP_V1.1.md`.

**Nota de integridad:** el contenido anterior se obtuvo sin conexión a `bd_clinica`; la guarda de allowlist impidió cualquier consulta cuando la configuración activa resolvió esa base.
