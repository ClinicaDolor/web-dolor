# TH-DOCTOR-COFEPRIS-PRIVATE-STORAGE-AND-AUTHORIZED-DOCUMENT-RESOLVER-12D-SEC-02B

## Estado de la intervención

Esta intervención realizó únicamente el preflight de SEC-02B y dejó documentadas las compuertas que impiden activar almacenamiento privado o nuevas escrituras. No se ejecutaron SQL, HTTP, subidas, migraciones ni lecturas de documentos clínicos. El código de aplicación y los archivos de configuración quedaron sin modificaciones en esta fase.

`STATUS: PARTIAL_BLOCKED_SCHEMA_DEPENDENCY`

La contención SEC-02A permanece vigente y no se revirtió.

## Alcance y preservación

- Proyecto: `C:\xampp\htdocs\clinica-dolor`
- Rama: `Silvino`
- HEAD observado: `59654e0`
- Entorno: desarrollo local XAMPP
- Worktree inicial: sucio, con numerosos cambios locales previos; se preservó íntegramente.
- Base configurada: `bd_clinica` en host local; no se abrió conexión desde Codex.
- PDFs históricos: no modificados ni migrados.
- Base y esquema: no modificados.
- Commits, push y despliegues: ninguno.

## Compuertas de preflight

### A1 — Compatibilidad del esquema

El contrato estático disponible permite resolver un registro nuevo mediante:

```text
cofepris.id
  -> cofepris.id_paciente
  -> pc_paciente.id_clinica
  -> cofepris.carpeta + cofepris.folio
  -> archivo físico del documento
```

Sin embargo, `cofepris_folio` no contiene una propiedad persistida de clínica en el esquema documentado (`id_clinica` no está presente). El modelo actual reserva rangos globalmente y `guardarFolio()` genera el almacenamiento bajo `public/storage/cofepris`. Por ello el vínculo de una nueva carpeta/rango con la clínica no puede demostrarse de forma durable sólo con el esquema actual.

`SCHEMA_COMPATIBILITY: PARTIAL — record-to-patient mapping exists; durable clinic/range/document ownership is not demonstrated.`

No se hizo una consulta runtime desde Codex: el acceso al MySQL local está aislado del entorno de ejecución. La evidencia de esquema runtime reportada previamente por el propietario permanece distinguida de la evidencia estática.

### A2 — Propiedad de clínica

La decisión funcional de folios independientes por clínica está registrada en 12C, pero la autorización de modificar el esquema o asignar retrospectivamente los rangos históricos no forma parte de esta intervención. No se asignó una clínica a rangos históricos ni se corrigieron las excepciones conocidas (23, 26, 256 y 257).

`CLINIC_RANGE_OWNERSHIP: BLOCKED — no persisted owner for cofepris_folio ranges.`

### B — Límite físico privado

El VirtualHost observado usa `C:\xampp\htdocs\clinica-dolor` como `DocumentRoot`. La ruta actual `public/storage/cofepris` se encuentra dentro de ese árbol. No existe una variable de almacenamiento privado aprobada en `.env` ni se proporcionó un directorio durable fuera de todos los `DocumentRoot`/`Alias` efectivos con permisos operativos verificados.

La regla existente `public/storage/cofepris/.htaccess` contiene `Require all denied` y la contención HTTP de SEC-02A fue confirmada por el propietario para las rutas canónica y reescrita. Esa regla es una contención pública, no una sustitución por almacenamiento privado.

`PRIVATE_STORAGE_ROOT: BLOCKED_PENDING_OWNER_APPROVAL`

`PRIVATE_STORAGE_BOUNDARY_VERIFIED: NO`

`PRIVATE_STORAGE_PERMISSIONS: NOT_VERIFIED`

No se modificó Apache, `.htaccess`, `.env` ni permisos del sistema.

## Arquitectura actual inspeccionada

### Lector PDF

`GET /pdf/cofepris/{id}` permanece en `PdfController::pdfCofepris()`.

- Usa sesión Doctor (`AuthMiddleware('clinica')`).
- Resuelve el registro por ID y después verifica el paciente mediante `assertPatientAccess()`.
- La autorización de clínica se deriva de la pertenencia del paciente a `id_clinica`.
- El archivo se construye con `carpeta` y `folio` recuperados del registro y se lee desde `public/storage/cofepris`.
- No se acepta una ruta física enviada por el cliente.
- No es todavía un resolver privado con una raíz externa, una identidad documental durable ni una consulta de propiedad de registro y documento en una sola operación.

`PRIVATE_RESOLVER_IMPLEMENTED: NO`

`DOCTOR_AUTHORIZATION: PARTIAL_STATIC — existing patient-scope check; SEC-02B resolver not implemented`

`PATIENT_AUTHORIZATION: DENY_BY_ROUTE_STATIC — no new patient permission enabled`

`RECORD_AUTHORIZATION: PARTIAL_STATIC — patient access follows record lookup; same-query tenant/document scope not implemented`

`DOCUMENT_IDENTITY: LOGICAL_ONLY — cofepris.id plus carpeta/folio; no durable document reference`

### Escritor actual

`CofeprisController::insertFolios()` continúa llamando al contrato existente `CofeprisModel::guardarFolio()`. El modelo actual escribe bajo `public/storage/cofepris` y crea el catálogo de rangos sin propiedad persistida de clínica.

No se ejecutó ese flujo. No se declara que las nuevas escrituras estén migradas ni que el flujo sea apto para SEC-02B.

`NEW_PRIVATE_DOCUMENT_WRITES: BLOCKED_PENDING_SCHEMA_AND_PRIVATE_ROOT_AUTHORIZATION`

`NEW_PUBLIC_DOCUMENT_WRITES: LEGACY_PATH_REMAINS_IN_CODE — SECURITY BLOCKER; not executed`

`DURABLE_DOCUMENT_REFERENCE: NOT_AVAILABLE_IN_CURRENT_SCHEMA`

## Compatibilidad histórica

`LEGACY_READER_PRESERVED: YES — no application files were changed.`

`LEGACY_PUBLIC_HTTP_ACCESS: SEC-02A OWNER_VERIFIED_DENIED_FOR_TESTED_ROUTES`

`AMBIGUOUS_DOCUMENT_POLICY: NOT_IMPLEMENTED — resolver must fail closed for the documented ambiguous ranges; no reassignment or backfill was attempted.`

Los PDFs y rangos históricos se mantienen intactos. No se migraron los 2,006 archivos ni se alteraron los seis rangos históricos.

## Clasificación de componentes

| Componente | Estado | Motivo |
|---|---|---|
| Contención directa HTTP | IMPLEMENTED_VERIFIED_LOCAL por SEC-02A | 403 confirmado por el propietario para las dos rutas probadas |
| Lector autorizado existente | IMPLEMENTABLE_WITH_EXISTING_SCHEMA, parcial | Existe vínculo registro-paciente-clínica, pero no resolver privado durable |
| Root privado | REQUIRES_OWNER_DECISION | No hay ruta aprobada fuera de DocumentRoot/Alias ni permisos verificados |
| Propiedad de rangos nuevos | REQUIRES_SCHEMA_CHANGE | `cofepris_folio` no demuestra `id_clinica` |
| Migración histórica | REQUIRES_OWNER_DECISION | No se puede inferir propiedad ni corregir ambigüedades |
| Escrituras privadas nuevas | BLOCKED_SECURITY_PRECONDITIONS | Dependen del root y de ownership durable |
| Resolver SEC-02B | BLOCKED_SECURITY_PRECONDITIONS | No debe activarse con los dos bloqueos anteriores |

## Pruebas y validaciones

### Ejecutadas en Codex

- PHP lint de `PdfController.php`, `CofeprisController.php`, `DownloadController.php`, `CofeprisModel.php`, `AuthMiddleware.php` y `routes.php`: PASS.
- Contrato `create-security-12a-contract.php`: PASS.
- Contrato `main-list-create-modal-12-contract.php`: PASS.
- Contrato `browser-history-fix-02-contract.php`: PASS.
- Contrato `navigation-correction-02-contract.php`: PASS.
- Contrato `ux-implementation-01-contract.php`: FAIL preexistente: `COFEPRIS list must use the full available column`. No se introdujo ningún cambio en la vista durante SEC-02B.
- `git diff --check`: PASS en código de salida; las advertencias reportadas son conversiones LF/CRLF de cambios locales preexistentes.

### No ejecutadas

- B01–B21 de SEC-02B: NOT EXECUTED / BLOCKED. No se creó un resolver privado ni un fixture de escritura aislado.
- SQL runtime, `SHOW CREATE TABLE`, conteos, persistencia y pruebas cruzadas: NOT EXECUTED desde Codex por aislamiento del MySQL local.
- HTTP runtime, navegador, impresión, descarga y consola: NOT EXECUTED desde Codex.
- Pruebas sintéticas de path traversal, reparse points, symlinks, overwrite, atomicidad, MIME, tamaño, rollback y concurrencia: NOT EXECUTED.
- Regresión Doctor sobre el lector privado: NOT EXECUTED; la evidencia de `/pdf/cofepris/337` pertenece a SEC-02A y fue OWNER_VERIFIED allí.

No se convierte ninguna validación estática en `PASS_RUNTIME`.

## Seguridad residual

1. `guardarFolio()` conserva la ruta legacy bajo `public/storage/cofepris`; las nuevas escrituras privadas no están activadas.
2. La propiedad de los rangos no está persistida por clínica y no debe inferirse del número de carpeta o folio.
3. La relación documental actual es lógica (`carpeta` + `folio`) y no una referencia durable única.
4. Los rangos/documentos ambiguos deben rechazarse hasta resolución administrativa.
5. Las rutas de edición, surtido, listado y detalle requieren revisión de ownership por clínica/registro en una fase autorizada.
6. La migración a almacenamiento privado sigue pendiente.
7. Producción y rutas no probadas quedan fuera del alcance.

## Decisiones del propietario requeridas

Antes de implementar SEC-02B se requiere confirmar por escrito:

1. Un root privado permanente fuera de `C:\xampp\htdocs\clinica-dolor` y de cualquier `DocumentRoot`/`Alias`, con permisos de lectura/escritura del proceso PHP.
2. La alternativa de esquema para persistir la clínica propietaria de rangos/documentos (opción 1, 2 o 4 de 12C) y la autorización de una migración posterior.
3. La política para históricos sin propietario demostrable y para las excepciones 23, 26, 256 y 257.
4. Si el endpoint legacy de carga debe quedar rechazado mientras no existan root y ownership privados, o si debe mantenerse deshabilitado por configuración fuera del repositorio.
5. El contrato de identidad documental durable y la estrategia de promoción atómica.

## Rollback y siguiente acción

- No se hizo rollback ni se retiró la contención SEC-02A.
- No se debe retirar `public/storage/cofepris/.htaccess` para obtener una prueba verde.
- No se deben mover ni renombrar PDFs históricos.
- Siguiente acción recomendada: aprobar root privado y diseño de ownership; luego implementar un resolver fail-closed y un writer privado, primero con fixture sintético aislado, antes de habilitar cualquier escritura real.

## Informe final

```yaml
TH_DOCTOR_COFEPRIS_PRIVATE_STORAGE_12D_SEC_02B:
  STATUS: PARTIAL_BLOCKED_SCHEMA_DEPENDENCY
  ENVIRONMENT: DEVELOPMENT_LOCAL_XAMPP
  BRANCH: Silvino
  WORKTREE_INITIAL_STATE: DIRTY_PREEXISTING_CHANGES_PRESERVED

  SCHEMA_COMPATIBILITY: PARTIAL_RECORD_MAPPING_ONLY; DURABLE_CLINIC_DOCUMENT_OWNERSHIP_NOT_VERIFIED
  CLINIC_RANGE_OWNERSHIP: BLOCKED_NOT_PERSISTED
  PRIVATE_STORAGE_ROOT: BLOCKED_PENDING_OWNER_APPROVAL
  PRIVATE_STORAGE_BOUNDARY_VERIFIED: NO
  PRIVATE_STORAGE_PERMISSIONS: NOT_VERIFIED

  PRIVATE_RESOLVER_IMPLEMENTED: NO
  DOCTOR_AUTHORIZATION: PARTIAL_STATIC_EXISTING_PATIENT_SCOPE
  PATIENT_AUTHORIZATION: DENY_BY_ROUTE_STATIC
  RECORD_AUTHORIZATION: PARTIAL_STATIC
  DOCUMENT_IDENTITY: LOGICAL_CARPETA_FOLIO_ONLY

  LEGACY_READER_PRESERVED: YES
  LEGACY_PUBLIC_HTTP_ACCESS: SEC_02A_OWNER_VERIFIED_DENIED_FOR_TESTED_ROUTES
  AMBIGUOUS_DOCUMENT_POLICY: BLOCKED_FAIL_CLOSED_POLICY_NOT_IMPLEMENTED

  NEW_PRIVATE_DOCUMENT_WRITES: BLOCKED_PENDING_SCHEMA_AND_PRIVATE_ROOT_AUTHORIZATION
  NEW_PUBLIC_DOCUMENT_WRITES: LEGACY_PATH_REMAINS_IN_CODE_NOT_EXECUTED
  DURABLE_DOCUMENT_REFERENCE: NOT_AVAILABLE

  SECURITY_TESTS: NOT_EXECUTED_BLOCKED
  SYNTHETIC_TESTS: NOT_EXECUTED
  DOCTOR_REGRESSION: NOT_EXECUTED_SEC_02A_EVIDENCE_PRESERVED
  HTTP_RUNTIME: NOT_EXECUTED_FROM_CODEX
  SEC_02A_PROTECTION_PRESERVED: YES

  HISTORICAL_PDFS_MODIFIED: NO
  HISTORICAL_PDFS_MIGRATED: NO
  DATABASE_MODIFIED: NO
  SCHEMA_MODIFIED: NO

  APPLICATION_FILES_MODIFIED: NONE
  CONFIGURATION_FILES_MODIFIED: NONE
  DOCUMENT_CREATED: docs/audits/TH_DOCTOR_COFEPRIS_PRIVATE_STORAGE_AND_RESOLVER_IMPLEMENTATION_12D_SEC_02B.md

  PHP_LINT: PASS
  GIT_DIFF_CHECK: PASS_WITH_PREEXISTING_LF_CRLF_WARNINGS

  COMMITS: NONE
  DEPLOYMENTS: NONE

  BLOCKERS:
    - No approved private root outside every effective DocumentRoot/Alias
    - No persisted clinic ownership for cofepris_folio ranges
    - No durable document reference for the private resolver
    - Codex cannot execute local MySQL or HTTP runtime validation
  RESIDUAL_RISKS:
    - Legacy writer still targets public/storage/cofepris and must not be treated as SEC-02B compliant
    - Historical ownership and ambiguous documents remain unresolved
    - Existing edit/surtido/list/detail paths require a separate ownership remediation
  OWNER_DECISIONS_REQUIRED:
    - Approve private root and permissions
    - Approve schema/ownership strategy and migration authority
    - Approve historical reconciliation policy
    - Decide legacy writer behavior while SEC-02B remains blocked
  NEXT_RECOMMENDED_ACTION: OWNER_APPROVAL_THEN_IMPLEMENT_FAIL_CLOSED_PRIVATE_RESOLVER_AND_WRITER_WITH_SYNTHETIC_FIXTURES
```

SEC-02B no queda cerrada ni habilitada. SEC-02C/12D-02 no se inició.
