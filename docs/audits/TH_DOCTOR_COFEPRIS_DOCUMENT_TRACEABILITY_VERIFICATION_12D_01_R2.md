# TH-DOCTOR-COFEPRIS-DOCUMENT-TRACEABILITY-VERIFICATION-12D-01-R2

## 1. Resultado y alcance

**STATUS: PARTIAL_DOCUMENT_IDENTITY_STATIC_VERIFIED_PHYSICAL_STORAGE_AND_HTTP_PENDING**

Esta fase completa exclusivamente la revisión de identidad documental y de protección del almacenamiento. La conciliación numérica de rangos permanece cerrada con la evidencia local reportada por el propietario en 12D-01.

No se ejecutaron SQL, POST, descargas, migraciones ni pruebas HTTP contra documentos clínicos. No se modificaron código funcional, datos, configuración, storage ni PDFs.

La evidencia numérica y las excepciones 23, 26, 256 y 257 son `OWNER_REPORTED_LOCAL_SQL`; no fueron ejecutadas por Codex.

## 2. Identidad documental observada en el código

### 2.1 Ruta calculada

`PdfController::pdfCofepris($id)` carga un registro `cofepris` por su ID y utiliza:

```text
{DOCUMENT_ROOT}/public/storage/cofepris/{cofepris.carpeta}/{cofepris.folio}.pdf
```

`CofeprisModel::guardarFolio()` genera los archivos de páginas con el nombre `{folio}.pdf` dentro de una carpeta aleatoria generada por servidor. `cofepris.id` se utiliza para localizar el registro y no forma parte del nombre físico del archivo.

### 2.2 Registros 256 y 257

La evidencia del propietario indica que ambos registros comparten la misma carpeta y el mismo folio.

| Pregunta | Resultado |
|---|---|
| Misma ruta lógica | Sí |
| Misma expresión de ruta resuelta por código | Sí, si los valores reportados son idénticos |
| Identidad adicional en el nombre del PDF | No observada |
| El ID de `cofepris` evita la colisión | No |
| Archivo físico esperado existe | `NOT_VERIFIED` |
| Archivo físico compartido | `NOT_VERIFIED` |
| Sobrescritura demostrada | No |
| Propietario documental inequívoco | No |

La conclusión válida es **colisión lógica potencial demostrada**, no sobrescritura física. No se debe afirmar que un archivo fue reemplazado sin verificar metadatos mínimos del archivo objetivo mediante un procedimiento autorizado.

### 2.3 Identidad documental adicional

No se observó una clave durable que vincule `cofepris` con `cofepris_folio` ni una identidad documental independiente del par `carpeta + folio`. El ID del registro clínico no se incorpora al path y no protege contra dos registros que compartan la misma ruta.

## 3. Almacenamiento local y política Apache

La inspección estática local encontró:

- `public/storage/cofepris` existe.
- Se observaron 2,006 archivos PDF mediante conteo de metadatos; no se publicaron nombres ni contenido.
- No existe `.htaccess` en `public/storage` ni en `public/storage/cofepris`.
- La raíz `.htaccess` redirige rutas que no comienzan con `/public/` hacia `public/`.
- `public/.htaccess` redirige únicamente solicitudes que no corresponden a archivo o carpeta real.
- `C:/xampp/htdocs` tiene `Options Indexes FollowSymLinks Includes ExecCGI` y `AllowOverride All` en la configuración local inspeccionada.
- El VirtualHost de `clinica.test` apunta a `C:/xampp/htdocs/clinica-dolor` y permite overrides.

Esto constituye **potencial de acceso directo** para archivos existentes bajo `public/storage/cofepris`, porque no se observó una denegación específica del directorio. No constituye confirmación de acceso HTTP: Codex no ejecutó una solicitud ni abrió un documento.

El `DownloadController` valida autenticación y tiene una rama de autorización por paciente para `laboratorio`, pero no se observó una rama equivalente para `cofepris`. El endpoint dedicado `/pdf/cofepris/{id}` aplica `authPermisos()` y `assertPatientAccess()` después de cargar el registro por ID, y posteriormente abre el path calculado. La ruta pública del archivo podría eludir ese controlador si Apache sirve directamente el archivo.

El manejo de archivo inexistente tampoco se validó en runtime; `FPDI::setSourceFile()` aparece después del gate estático de paciente y no se observó una respuesta específica de documento no encontrado.

## 4. Excepciones históricas preservadas

| Registro | Evidencia | Evidencia adicional necesaria | Acción realizada |
|---:|---|---|---|
| 23 | Folio numérico del rango 3, carpeta del rango 2; paciente válido de Clínica 1 | Documento administrativo que explique la carpeta y metadatos del registro/rango | Ninguna modificación |
| 26 | Folio numérico del rango 3, carpeta del rango 2; paciente válido de Clínica 1 | Igual que registro 23; confirmar si es excepción legítima o error histórico | Ninguna modificación |
| 256 | Rango 8; paciente inexistente; comparte carpeta y folio con 257 | Evidencia de propietario histórico, existencia del archivo, relación documental y política para huérfanos | Preservado como `OWNER_UNKNOWN` |
| 257 | Rango 8; paciente válido de Clínica 1; comparte carpeta y folio con 256 | Evidencia de identidad documental y tratamiento de la clave compartida | Ninguna modificación |

No se modificaron carpetas, folios, pacientes ni registros. No se adjudicaron propietarios.

## 5. Condiciones técnicas para 12D-02

| Cambio | Clasificación | Condición |
|---|---|---|
| `cofepris_folio.id_clinica` nullable | `REQUIRES_HISTORICAL_RECONCILIATION` + `REQUIRES_OWNER_DECISION` | Los seis rangos siguen H2; no hacer backfill automático |
| `cofepris.cofepris_folio_id` durable | `REQUIRES_HISTORICAL_RECONCILIATION` | Resolver 23/26 y 256/257 antes de poblar |
| Clínica válida para nuevos registros | `READY_FOR_DESIGN` | Implementación requiere autorización independiente y pruebas de dos clínicas |
| Preservar seis rangos sin atribución | `READY_FOR_DESIGN` | Mantener `OWNER_UNKNOWN` hasta decisión documentada |
| Preservar registro 256 | `READY_FOR_DESIGN` | `FAIL_CLOSED`; no reasignar ni eliminar |
| Autorización de documentos | `READY_FOR_DESIGN` | Gate clínica + paciente + registro + documento antes de abrir el archivo |
| Storage no público o denegado por servidor | `REQUIRES_OWNER_DECISION` | Definir política y probar URL directa con archivo sintético |
| Evitar nuevas colisiones | `REQUIRES_HISTORICAL_RECONCILIATION` | Identidad física durable y regla de unicidad aprobada |
| Backfill histórico | `BLOCKED` | Ningún rango tiene propietario clínico demostrado |

No se generaron DDL ni migraciones ejecutables. El diseño debe conservar los seis rangos sin atribución hasta contar con evidencia administrativa suficiente.

## 6. Pruebas posteriores requeridas

Sólo en una base y storage sintéticos aislados:

1. Crear dos registros sintéticos con una combinación controlada de carpeta/folio y confirmar que la nueva arquitectura la rechaza o la identifica de forma durable.
2. Verificar que una clínica no puede leer el PDF de la otra por endpoint autorizado.
3. Verificar que una URL directa al storage no entrega el archivo.
4. Verificar archivo inexistente con respuesta controlada.
5. Verificar el registro huérfano sin exposición ni modificación.
6. Confirmar el tratamiento de 23/26 antes de cualquier backfill histórico.

No utilizar los PDFs clínicos existentes para estas pruebas.

## 7. Validaciones ejecutadas

| Validación | Resultado |
|---|---|
| Inspección estática de `PdfController`, `CofeprisModel`, rutas y descarga | `PASS_STATIC` |
| Revisión de construcción de path | `PASS_STATIC` |
| Conteo de archivos del directorio COFEPRIS | `PASS_METADATA_ONLY` — 2,006 PDFs; nombres y contenido no inspeccionados |
| Revisión `.htaccess` del proyecto/storage | `PASS_STATIC` — sin regla específica de denegación para COFEPRIS |
| Acceso HTTP directo | `NOT_VERIFIED` |
| Existencia del PDF de 256/257 | `NOT_VERIFIED` |
| Sobrescritura física | `NOT_VERIFIED` |
| SQL runtime | `NOT_EXECUTED_BY_CODEX` |
| PHP lint | `PASS` en archivos COFEPRIS inspeccionados |
| `git diff --check` | `PASS` |

## 8. Informe final

```yaml
TH_DOCTOR_COFEPRIS_DOCUMENT_TRACEABILITY_VERIFICATION_12D_01_R2:
  STATUS: PARTIAL_DOCUMENT_IDENTITY_STATIC_VERIFIED_PHYSICAL_STORAGE_AND_HTTP_PENDING
  PDF_256_257_SAME_LOGICAL_PATH: YES
  PDF_256_257_SAME_RESOLVED_PATH: YES_STATIC_PATH_EXPRESSION
  PDF_FILE_EXISTS: NOT_VERIFIED
  PDF_OVERWRITE_CONFIRMED: NO
  PDF_DOCUMENT_OWNER_VERIFIED: NO
  STORAGE_DIRECTORY_EXISTS: YES_METADATA_ONLY
  STORAGE_PDF_COUNT: 2006_METADATA_ONLY
  APACHE_DIRECT_ACCESS_POLICY: NO_STORAGE_DENY_RULE_FOUND_STATIC_POTENTIAL
  DIRECT_HTTP_ACCESS_VERIFIED: NOT_VERIFIED
  DOCUMENT_AUTHORIZATION: PATIENT_AUTH_STATIC_AFTER_ID_LOOKUP_DIRECT_STORAGE_NOT_GATED
  DOCUMENT_COLLISION_RISK: LOGICAL_COLLISION_CONFIRMED_FOR_256_257_PHYSICAL_COLLISION_NOT_VERIFIED
  HISTORICAL_EXCEPTIONS_PRESERVED: YES
  SIX_RANGE_OWNERSHIP_UNRESOLVED: YES
  SCHEMA_DESIGN_READY: DESIGN_ONLY
  BACKFILL_READY: NO
  READY_FOR_SCHEMA_IMPLEMENTATION: NO_IMPLEMENTATION_AUTHORIZATION
  CODE_MODIFIED: NO
  DATABASE_MODIFIED: NO
  SCHEMA_MODIFIED: NO
  PDF_MODIFIED: NO
  MIGRATIONS_EXECUTED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS: TARGET_FILE_MAPPING; DIRECT_HTTP_RUNTIME_TEST; STORAGE_POLICY; HISTORICAL_OWNER_DECISIONS
  NEXT_ACTION: MANUAL_READ_ONLY_METADATA_CHECK_WITH_SYNTHETIC_OR_AUTHORIZED_PROCEDURE_THEN_12D_02_DESIGN_REVIEW
```

**PRODUCTION_READY: NO**
