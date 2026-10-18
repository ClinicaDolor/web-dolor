# COFEPRIS — 12D-02-CONTRACT-01
## Propuesta de contrato estructural previa a implementación

## Estado

```yaml
COFEPRIS_12D_02_CONTRACT_01:
  STATUS: PROPOSED_CONTRACT_PENDING_OWNER_APPROVAL
  SCHEMA_SOURCE: REPOSITORY_FIXTURE_AND_STATIC_CODE; RUNTIME_SQL_PENDING
  SCHEMA_RUNTIME_VERIFIED: NO_FROM_CODEX
  RECOMMENDED_SCHEMA: RANGE_OWNERSHIP_PLUS_DURABLE_DOCUMENT_ENTITY
  RELATIONAL_CONTRACT: DEFINED_BELOW; NOT_IMPLEMENTED
  UNIQUE_CONSTRAINTS: PROPOSED_BELOW; NOT_IMPLEMENTED
  FOREIGN_KEYS: PROPOSED_BELOW; NOT_IMPLEMENTED
  DOCUMENT_STATES: STAGED_READY_UNASSIGNED_ASSIGNED_AMBIGUOUS_QUARANTINED_MISSING_RETIRED
  DOCUMENT_ASSIGNMENT_CARDINALITY: RANGE_1_TO_N_DOCUMENT_0_OR_1_TO_COFEPRIS; COFEPRIS_0_OR_1_TO_DOCUMENT

  CLINIC_OWNERSHIP: SERVER_SIDE_PERSISTED_RANGE_AND_DOCUMENT_OWNER
  CONCURRENCY_STRATEGY: TRANSACTION_RANGE_ROW_LOCK_UNIQUE_DOCUMENT_STATE_BOUNDED_RETRY
  FILESYSTEM_DATABASE_CONSISTENCY: EXPLICIT_STAGED_PROMOTION_AND_RECOVERY; NO_CROSS_SYSTEM_ATOMICITY_CLAIM
  LEGACY_CUTOVER_STRATEGY: ADDITIVE_SCHEMA_PRIVATE_NEW_WRITES_AUTHORIZED_RESOLVER_HISTORICAL_READ_ONLY

  PRIVATE_ROOT_CANDIDATE: C:\\xampp\\private\\clinica-dolor\\cofepris
  PRIVATE_ROOT_APPROVAL: PENDING_OWNER_OPERATIONAL_APPROVAL
  SEC_02A_PRESERVED: YES

  HISTORICAL_BACKFILL: SEPARATE_APPROVAL_REQUIRED; NONE_IN_INITIAL_ACTIVATION
  MIGRATION_EXECUTED: NO
  DATABASE_MODIFIED: NO
  APPLICATION_MODIFIED: NO
  PDF_MODIFIED: NO

  DOCUMENT_CREATED: docs/audits/TH_COFEPRIS_SCHEMA_CONTRACT_FREEZE_PROPOSAL_12D_02_CONTRACT_01.md
  GIT_DIFF_CHECK: PASS
  COMMITS: NONE
  DEPLOYMENTS: NONE
```

Este documento es una propuesta de contrato. No congela automáticamente el esquema ni autoriza su implementación.

## 1. Fuente y esquema existente

La fuente disponible es el fixture `tests/fixtures/security-fixtures.sql`, junto con las consultas y rutas actuales. El runtime de MariaDB no fue consultado por Codex. Los detalles siguientes deben confirmarse con `SHOW CREATE TABLE` antes de cualquier `ALTER`.

### `cofepris_folio` existente según fixture

```text
id            INT NOT NULL AUTO_INCREMENT PRIMARY KEY
fecha_hora    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
folio_inicial INT NOT NULL
folio_final   INT NOT NULL
carpeta       VARCHAR(50) NOT NULL
total_folio   INT NOT NULL
estado        INT NOT NULL
```

No se observa ownership de clínica, FK a `clinicas`, unicidad de carpeta por clínica ni una entidad de documento individual.

### `cofepris` existente según fixture

```text
id                   INT NOT NULL AUTO_INCREMENT PRIMARY KEY
fecha_hora           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
id_paciente          INT NOT NULL
carpeta              TEXT NOT NULL
folio                VARCHAR(20) NOT NULL
diagnostico          TEXT NOT NULL
medicamento          TEXT NOT NULL
num_cajas            TEXT NOT NULL
presentacion         VARCHAR(100) NOT NULL
dosificacion         TEXT NOT NULL
num_dias             VARCHAR(100) NOT NULL
via_administracion   TEXT NOT NULL
surtido              INT NOT NULL
farmacia             DOUBLE NOT NULL
paciente             DOUBLE NOT NULL
medico               DOUBLE NOT NULL
```

La clínica se deriva actualmente desde `id_paciente -> pc_paciente.id_clinica`; no hay una referencia documental durable ni una relación persistida con un PDF individual.

### `pc_paciente` y `clinicas`

El repositorio utiliza `pc_paciente.id_clinica` para determinar el tenant y `clinicas.id` como entidad de clínica. Los tipos, FKs, índices y collations efectivos deben confirmarse en runtime antes de diseñar claves compatibles.

## 2. Consultas de verificación manual

Codex no ejecutará estas consultas contra `bd_clinica`. El propietario puede ejecutarlas en phpMyAdmin o MySQL local después de confirmar que la base seleccionada es la autorizada. Son exclusivamente de lectura.

```sql
SELECT DATABASE() AS selected_database;
SELECT VERSION() AS database_version;

SHOW CREATE TABLE clinicas;
SHOW CREATE TABLE pc_paciente;
SHOW CREATE TABLE cofepris_folio;
SHOW CREATE TABLE cofepris;

SHOW INDEX FROM clinicas;
SHOW INDEX FROM pc_paciente;
SHOW INDEX FROM cofepris_folio;
SHOW INDEX FROM cofepris;

SELECT
    tc.CONSTRAINT_NAME,
    tc.TABLE_NAME,
    tc.CONSTRAINT_TYPE,
    kcu.COLUMN_NAME,
    kcu.REFERENCED_TABLE_NAME,
    kcu.REFERENCED_COLUMN_NAME
FROM information_schema.TABLE_CONSTRAINTS tc
LEFT JOIN information_schema.KEY_COLUMN_USAGE kcu
    ON kcu.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
   AND kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
   AND kcu.TABLE_NAME = tc.TABLE_NAME
WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
  AND tc.TABLE_NAME IN ('clinicas', 'pc_paciente', 'cofepris_folio', 'cofepris')
ORDER BY tc.TABLE_NAME, tc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION;

SELECT
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLLATION_NAME,
    COLUMN_KEY,
    EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('clinicas', 'pc_paciente', 'cofepris_folio', 'cofepris')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SHOW TRIGGERS;
```

Si `DATABASE()` no devuelve `bd_clinica`, se debe detener la consulta y no ejecutar ninguna escritura posterior.

## 3. Contrato propuesto

La opción recomendada separa tres conceptos:

1. Rango de folios y su clínica propietaria.
2. Documento físico individual generado desde el rango.
3. Registro clínico `cofepris` y su asignación opcional al documento.

### 3.1 Propiedad de rango

Se propone una ampliación aditiva de `cofepris_folio` para rangos nuevos:

| Campo propuesto | Tipo propuesto | NULL | Default | Semántica y reglas |
|---|---|---:|---|---|
| `id_clinica` | `INT` compatible con `clinicas.id` | Sí para históricos; no para rangos nuevos | `NULL` | Clínica propietaria. Nunca proviene del frontend. |
| `ownership_state` | `VARCHAR(24)` | Sí para históricos | `UNRESOLVED` para legacy; `OWNED` para nuevos | `OWNED`, `UNRESOLVED`, `AMBIGUOUS`, `RETIRED`. |
| `created_by` | Tipo compatible con usuario autenticado | Sí | `NULL` | Actor que creó el rango, si el modelo de usuarios lo permite. |
| `created_at` | `DATETIME`/`TIMESTAMP` compatible | Sí para legacy | existente/default | Momento de alta del rango nuevo. |

Restricciones propuestas:

- FK `id_clinica -> clinicas.id` con `ON DELETE RESTRICT`.
- Índice por `(id_clinica, estado, id)` para seleccionar rangos activos de una clínica.
- Unicidad de `(id_clinica, carpeta)` para rangos nuevos, sujeta a confirmar que `carpeta` sea una identidad válida y no se repita legalmente.
- Rangos legacy sin owner quedan `NULL/UNRESOLVED`; no se asignan automáticamente.
- `folio_inicial <= folio_final` debe ser un invariant de aplicación y, si la versión efectiva lo admite de manera segura, una restricción adicional.
- No se permite solapamiento de rangos dentro de una clínica; la validación debe ejecutarse dentro de una transacción con locks.

### 3.2 Entidad documental individual

Se propone una entidad nueva, provisionalmente llamada `cofepris_documento`. El nombre final requiere aprobación; no se ha creado.

| Campo propuesto | Tipo propuesto | NULL | Default | Semántica y reglas |
|---|---|---:|---|---|
| `id` | `INT`/`BIGINT` compatible con la decisión de escala | No | autoincrement | Identidad técnica del documento. |
| `id_rango` | Tipo compatible con `cofepris_folio.id` | Sí sólo para históricos no conciliados | `NULL` | Rango que originó el PDF. Nuevos documentos deben tenerlo. |
| `id_clinica` | Tipo compatible con `clinicas.id` | Sí sólo para históricos | `NULL` | Owner materializado para autorización y consistencia. |
| `folio` | Tipo compatible con el rango, preferentemente `VARCHAR(20)` | Sí sólo para registros históricos incompletos | `NULL` | Número comercial dentro del rango; no es identidad única global. |
| `storage_key` | `VARCHAR(255)` o longitud aprobada | Sí sólo para históricos sin resolución | `NULL` | Clave opaca relativa al root privado; nunca ruta absoluta ni entrada del cliente. |
| `sha256` | `CHAR(64)` | Sí para estados históricos incompletos; no para documentos nuevos | `NULL` | Hash hexadecimal del contenido definitivo. |
| `size_bytes` | `BIGINT UNSIGNED` o tipo compatible | Sí para históricos | `NULL` | Tamaño exacto del archivo definitivo. |
| `mime_type` | `VARCHAR(127)` | Sí para históricos | `NULL` | MIME validado por contenido; documentos nuevos deben ser PDF. |
| `state` | `VARCHAR(24)` | No | `STAGED` | Estado de ciclo de vida definido en §4. |
| `id_cofepris` | Tipo compatible con `cofepris.id` | Sí | `NULL` | Asociación opcional; NULL representa documento listo aún no asignado. |
| `created_at` | `DATETIME`/`TIMESTAMP` | No para nuevos | valor de servidor | Creación de identidad documental. |
| `assigned_at` | `DATETIME`/`TIMESTAMP` | Sí | `NULL` | Momento de asociación a registro clínico. |
| `assigned_by` | Tipo compatible con usuario | Sí | `NULL` | Actor de la asignación, si existe identidad compatible. |
| `operation_key` | `CHAR(36)` o formato aprobado | Sí | `NULL` | Clave de idempotencia de la operación; no es autoridad de clínica. |

Restricciones e índices propuestos:

- PK en `id`.
- FK `id_rango -> cofepris_folio.id` con borrado restringido.
- FK `id_clinica -> clinicas.id` con borrado restringido.
- FK `id_cofepris -> cofepris.id` con borrado restringido.
- `UNIQUE(storage_key)` para documentos no nulos.
- `UNIQUE(id_rango, folio)` para documentos nuevos, con política explícita para valores NULL históricos.
- `UNIQUE(id_cofepris)` para impedir que un registro clínico tenga dos documentos; MariaDB permite varios NULL.
- `UNIQUE(operation_key)` si se adopta esta clave para idempotencia.
- Índices por `(id_clinica, state, id_rango, id)` y `(id_cofepris, id_clinica)`.
- No se permite que `id_cofepris` apunte a un registro cuya clínica/paciente no coincida con `id_clinica`.

### 3.3 Registro `cofepris`

Para reforzar la integridad de nuevas asignaciones se propone agregar `id_clinica` nullable a `cofepris`, sin exigir backfill inmediato:

| Campo propuesto | Tipo | NULL | Semántica |
|---|---|---:|---|
| `id_clinica` | Tipo compatible con `clinicas.id` | Sí para históricos; no para nuevas altas | Owner server-side, igual al owner del paciente y del documento. |

Se propone un índice `(id_paciente, id_clinica)` y, si el esquema runtime lo permite sin conflicto, una FK compuesta a `pc_paciente(id, id_clinica)`. Para registros nuevos el servicio debe exigir `id_clinica` no nulo; los históricos permanecen nullable hasta una fase de conciliación.

La entidad documental apunta a `cofepris`; no se propone una referencia bidireccional obligatoria. Si se requiere la FK compuesta documento–registro, debe aprobarse después de confirmar tipos e índices reales.

## 4. Estados y transiciones

Estados propuestos para `cofepris_documento`:

| Estado | Significado | ¿Visible para lectura clínica? |
|---|---|---:|
| `STAGED` | Identidad creada; temporal o metadata pendiente de promoción | No |
| `READY_UNASSIGNED` | Archivo definitivo íntegro, sin paciente asignado | Sólo administración de la clínica propietaria |
| `ASSIGNED` | Documento ligado inequívocamente a `cofepris`, paciente y clínica | Sí, tras autorización |
| `AMBIGUOUS` | No se puede demostrar owner o identidad | No |
| `QUARANTINED` | Fallo de validación, hash, MIME, referencia o recuperación | No |
| `MISSING` | Referencia existe pero archivo no está disponible | No; sólo diagnóstico autorizado |
| `RETIRED` | No se usa para nuevas asignaciones; se conserva trazabilidad | Según política aprobada |

Transiciones permitidas:

```text
STAGED -> READY_UNASSIGNED
STAGED -> QUARANTINED
READY_UNASSIGNED -> ASSIGNED
READY_UNASSIGNED -> QUARANTINED
ASSIGNED -> QUARANTINED   (sólo procedimiento excepcional auditado)
ASSIGNED -> MISSING       (diagnóstico, no borrado)
READY_UNASSIGNED -> RETIRED
AMBIGUOUS -> ASSIGNED     (sólo después de resolución aprobada)
```

No se permite volver a `READY_UNASSIGNED` un documento ya asignado sin procedimiento de reconciliación. No se elimina físicamente un documento clínico para resolver una inconsistencia.

## 5. Cardinalidades e integridad

- Una clínica puede tener muchos rangos.
- Un rango puede originar muchos documentos individuales.
- Un documento pertenece como máximo a un rango y una clínica.
- Un documento puede estar sin `cofepris.id` mientras está disponible.
- Un documento puede asociarse como máximo a un registro `cofepris`.
- Un registro `cofepris` nuevo puede tener como máximo un documento.
- Un paciente puede tener muchos registros COFEPRIS dentro de su clínica.
- Una clínica no puede asignar un documento de otra clínica.
- `cofepris.carpeta` y `cofepris.folio` se conservan por compatibilidad, pero no son la autorización ni la identidad técnica única.
- Un documento con owner o referencia incompleta se trata como `AMBIGUOUS`, `MISSING` o `QUARANTINED`, nunca como válido por defecto.
- Borrado de rango, documento o registro debe ser restringido; la política por defecto es preservación y retiro lógico.

## 6. Concurrencia e idempotencia

### Asignación futura de un documento a paciente

Unidad transaccional propuesta:

1. Validar sesión Doctor, `id_clinica` del JWT y CSRF en servidor.
2. Validar una clave de operación idempotente asociada al intento, sin confiar en ella como tenant.
3. Iniciar transacción.
4. Seleccionar el rango propiedad de la clínica con `SELECT ... FOR UPDATE`.
5. Seleccionar un documento `READY_UNASSIGNED` del rango con `SELECT ... FOR UPDATE`.
6. Verificar que el paciente pertenece a la clínica autenticada.
7. Crear `cofepris` con `id_paciente`, `id_clinica`, rango y folio del documento.
8. Asociar `id_cofepris` al documento y cambiarlo a `ASSIGNED`.
9. Confirmar que las restricciones únicas no se violan.
10. Hacer `COMMIT`.

Si el commit falla, hacer rollback y dejar el documento sin asignación. Ante un conflicto de unicidad o bloqueo, reintentar un número pequeño y controlado de veces; si persiste, devolver error sin consumir otro documento.

### Invariantes

- Nunca dos registros pueden usar el mismo `(id_rango, folio)`.
- Nunca dos documentos pueden usar el mismo `storage_key`.
- Nunca un documento puede pasar a `ASSIGNED` sin registro clínico válido.
- Nunca un registro puede enlazar documento de otra clínica.
- Nunca se selecciona el siguiente folio mediante `MAX(folio)` global.
- La reejecución con la misma clave de operación devuelve el resultado existente y no crea otra asignación.

## 7. Consistencia entre filesystem y base

No existe atomicidad nativa entre una transacción MariaDB y un rename del filesystem. Por ello el contrato no promete una transacción conjunta; usa estados recuperables:

1. Recibir el upload en un temporal fuera del root definitivo.
2. Validar extensión, MIME por contenido, tamaño y firma PDF.
3. Dividir páginas en archivos temporales únicos.
4. Calcular SHA-256, tamaño y metadata de cada página.
5. Registrar metadata `STAGED` con clave de operación idempotente.
6. Promover cada archivo a la ubicación definitiva mediante creación exclusiva/rename atómico en el mismo volumen.
7. Verificar existencia, tipo, tamaño y hash.
8. Cambiar a `READY_UNASSIGNED` sólo después de la comprobación.
9. Si falla promoción o actualización, marcar `QUARANTINED` o ejecutar cleanup seguro; nunca declarar `READY` sin archivo verificable.
10. Un reconciliador posterior debe localizar `STAGED`, `MISSING` y definitivos sin referencia, sin asignar pacientes automáticamente.

La transacción SQL sólo cubre identidad y relaciones SQL. La promoción física se compensa con estados, hash, cleanup e idempotencia.

## 8. Almacenamiento privado

Candidata conservada:

```text
C:\xampp\private\clinica-dolor\cofepris\
```

Clasificación: `PENDING_OWNER_OPERATIONAL_APPROVAL`.

Contrato operativo propuesto:

- root fuera de `C:\xampp\htdocs` y de todos los `DocumentRoot`/`Alias`;
- ruta configurable por entorno del servidor, por ejemplo `COFEPRIS_PRIVATE_ROOT`, sin cambiar `.env` en esta fase;
- el proceso PHP debe tener sólo permisos necesarios sobre temporales y definitivos;
- el navegador nunca recibe la ruta física;
- validar que el root real no sea reparse point, symlink o junction;
- rechazar traversal, separadores inesperados y nombres proporcionados por el cliente;
- separar `tmp`, `definitive` y `quarantine`;
- no usar fallback a `public/storage/cofepris` cuando falte la configuración;
- respaldar por una política operativa que preserve hash y metadata;
- comprobar que ninguna ruta HTTP alcanza el root.

No se creó el directorio ni se modificó Apache.

## 9. Convivencia y cutover legacy

### Estado previo al cutover

- SEC-02A permanece vigente con `Require all denied`.
- El writer actual sigue apuntando al almacenamiento público y no debe considerarse compatible con SEC-02B.
- Los rangos históricos no tienen owner demostrado.
- No se deshabilitan endpoints en esta fase, conforme a la instrucción; tampoco se activan nuevas escrituras.

### Cutover propuesto

1. Aprobar y aplicar migración estructural aditiva.
2. Aprovisionar root privado y ejecutar preflight de boundary/permisos.
3. Implementar writer privado detrás de una compuerta de configuración fail-closed.
4. Activar sólo rangos nuevos con owner explícito y documentos privados.
5. Mantener el lector histórico autorizado, sin URLs públicas ni fallback ante fallo de autorización.
6. Cambiar la carga nueva al writer privado únicamente después de validar fixture sintético.
7. Mantener históricos como `UNRESOLVED/AMBIGUOUS` hasta conciliación separada.
8. Probar regresión Doctor, impresión y autorización cruzada.
9. Declarar el cutover sólo para nuevos documentos; no declarar migrados los históricos.

### Rollback

- Desactivar la activación de nuevos documentos privados, no la contención HTTP.
- Conservar archivos privados, metadata y estados para recuperación.
- Mantener `Require all denied`.
- No restaurar la escritura pública como “solución” a un fallo.
- No borrar ni mover históricos.
- Reanudar sólo después de reparar la causa y repetir las pruebas sintéticas.

## 10. Matriz de pruebas sintéticas

| Caso | Resultado esperado | Estado actual |
|---|---|---|
| Dos clínicas con rangos distintos | Cada clínica sólo lista/asigna sus rangos | No ejecutado |
| Paciente de otra clínica | GET/POST/PDF rechazado sin filtrar datos | No ejecutado |
| Documento libre | Visible sólo para owner; no para paciente antes de asignar | No ejecutado |
| Asignación concurrente | Un solo `cofepris` por documento | No ejecutado |
| Reintento idempotente | No crea segundo registro | No ejecutado |
| `storage_key` duplicado | Restricción/rechazo sin sobrescritura | No ejecutado |
| Hash alterado | `QUARANTINED`/rechazo | No ejecutado |
| MIME no PDF | Rechazo | No ejecutado |
| Traversal/reparse point | Rechazo | No ejecutado |
| Fallo de rename | No queda `READY` sin archivo | No ejecutado |
| Fallo SQL posterior a promoción | Estado recuperable y cleanup/reconciliación | No ejecutado |
| Histórico ambiguo | No se expone por resolver nuevo | No ejecutado |
| SEC-02A directo | 403 y sin bytes sintéticos | OWNER_VERIFIED en SEC-02A |
| Doctor autorizado | PDF y print sólo tras resolver autenticado | Pendiente de SEC-02B |

## 11. Fases propuestas

- **12D-02A:** migración estructural aditiva, sólo tras autorización explícita.
- **12D-02B:** ownership de clínica y asignación segura de nuevos folios.
- **SEC-02B-R2:** root privado, writer y resolver autenticado.
- **SEC-02C:** inventario y transición histórica, con decisión administrativa.
- **SEC-02D:** regresión integral y cierre.

Estas etiquetas son planificación, no autorización.

## 12. Decisiones pendientes

1. Confirmar el esquema real mediante las consultas de §2.
2. Aprobar la opción B y decidir si `id_clinica` se materializa también en `cofepris` para permitir FK compuesta.
3. Aprobar el nombre y cardinalidad finales de la entidad documental.
4. Aprobar la estrategia de idempotencia: clave de operación en documento, tabla de operaciones o equivalente.
5. Aprobar root privado, cuenta PHP, permisos, respaldo y parametrización futura.
6. Definir si la auditoría de lectura/descarga es obligatoria.
7. Definir el comportamiento del writer legacy durante la transición.
8. Definir autoridad y procedimiento para históricos 23, 26, 256 y 257.
9. Autorizar por separado migración, implementación y pruebas de escritura sintéticas.

## Cierre

El contrato recomendado es técnicamente coherente con MariaDB 10.4.32 a nivel conceptual, pero sus tipos, FKs e índices deben contrastarse con el esquema runtime. No se ejecutó SQL ni se alteró el sistema. SEC-02A sigue cerrada en su alcance local y SEC-02B permanece pendiente de autorización estructural y operativa.
