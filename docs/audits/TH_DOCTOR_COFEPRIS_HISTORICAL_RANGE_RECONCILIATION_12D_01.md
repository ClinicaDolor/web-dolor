# TH-DOCTOR-COFEPRIS-HISTORICAL-RANGE-RECONCILIATION-12D-01

## 1. Resumen ejecutivo

**STATUS: PARTIAL_NUMERIC_RECONCILIATION_CLOSED_DOCUMENT_TRACEABILITY_PENDING**

Se realizó una conciliación focalizada de los rangos históricos COFEPRIS, sus usos y la trazabilidad documental. El trabajo fue de lectura y documentación; no se modificaron PHP, JavaScript, CSS, rutas, datos, esquema, PDFs ni permisos.

La evidencia adicional reportada por el propietario cierra la conciliación numérica de los seis rangos: 345 registros, 343 coincidencias de rango y carpeta, dos excepciones de carpeta, cero registros fuera de rango, cero candidatos múltiples, cero solapamientos entre rangos y cero carpetas exactamente duplicadas. Se mantienen 1,200 folios declarados, 344 relaciones válidas con `pc_paciente` y un registro huérfano.

La evidencia numérica es `OWNER_REPORTED_LOCAL_SQL`, no una ejecución de Codex. La existencia física de PDFs, la autorización HTTP del storage y la sobrescritura física no fueron verificadas. La propiedad administrativa de los seis rangos tampoco queda demostrada: la tabla no tiene `id_clinica` y una coincidencia de rango/carpeta no equivale a propiedad histórica.

## 2. Decisión funcional y alcance

El propietario aprobó **Opción B — folios independientes por clínica**. Cada clínica debe administrar sus rangos, usos, surtidos y documentos. La clínica autorizada debe proceder del contexto autenticado y no de un ID enviado por el navegador.

Esta fase no implementa esa decisión. No se asigna ningún rango a la Clínica 1 por inferencia y no se rellena una referencia histórica durable.

## 3. Preflight del repositorio

| Campo | Resultado |
|---|---|
| Rama | `Silvino` |
| HEAD | `59654e05dc9f931c21b5392238961bcddb3ab2e4` |
| Worktree | Preservado; contiene cambios locales preexistentes de múltiples fases |
| Base configurada | `bd_clinica` en `localhost:3306` |
| Motor reportado por propietario | MariaDB 10.4.32 |
| SQL ejecutado por Codex | No; bloqueo del socket antes de `SELECT DATABASE()` |
| Código funcional modificado en 12D-01 | No |
| Datos/esquema/PDF modificados | No |

El preflight confirmó la presencia de los documentos 12, 12A, 12B, 12C y la propuesta 12D. No se ejecutaron comandos destructivos, staging, commits ni despliegues.

## 4. Evidencia de base local reportada por el propietario

Fuente: `OWNER_REPORTED_LOCAL_SQL`, ejecutada por el propietario en phpMyAdmin sobre `bd_clinica`. No es una consulta ejecutada por Codex.

| Métrica | Resultado reportado |
|---|---:|
| Clínicas registradas | 1 |
| Clínica observada | ID 1 |
| Rangos en `cofepris_folio` | 6 |
| Folios declarados | 1,200 |
| Registros `cofepris` | 345 |
| Relaciones válidas con `pc_paciente` | 344 |
| Registros huérfanos | 1 |
| Pacientes existentes relacionados con Clínica 1 | 90 |
| Valores distintos de `id_paciente` en `cofepris` | 91 |
| Valores distintos de `folio` entre relaciones válidas | 342 |
| Folios compartidos entre clínicas | 0 reportados; no concluyente con una sola clínica |
| Coincidencias de rango numérico y carpeta | 343 |
| Excepciones de carpeta | 2 |
| Registros fuera de rango | 0 |
| Registros con múltiples rangos candidatos | 0 |
| Solapamientos entre los seis rangos | 0 |
| Carpetas exactamente duplicadas entre rangos | 0 |
| Registros con ceros iniciales | 0 |

### Rangos reportados

| ID | Total declarado | Estado | Formato reportado |
|---:|---:|---:|---|
| 2 | 200 | 1 | Numérico |
| 3 | 200 | 1 | Numérico |
| 4 | 200 | 1 | Numérico |
| 5 | 200 | 1 | Numérico |
| 8 | 200 | 1 | Numérico |
| 9 | 200 | 0 | Numérico |

La tabla por rango y los resultados agregados entregados por el propietario verifican el uso numérico y la coincidencia de carpeta. Los valores literales de límites y carpetas no se reproducen aquí por minimización de datos; los resultados de intervalos, solapamientos y ceros iniciales se conservan como evidencia reportada por el propietario.

## 5. Esquema y dependencias

La evidencia disponible indica que:

- `cofepris_folio` no contiene `id_clinica`.
- No se observaron índices `UNIQUE` adicionales a `PRIMARY(id)`.
- `cofepris` contiene `id_paciente`, `carpeta` y `folio`, pero no una referencia durable a `cofepris_folio`.
- `pc_paciente.id_clinica` permite derivar la clínica del registro clínico cuando el paciente existe.
- El vínculo histórico entre `cofepris` y `cofepris_folio` es una inferencia por carpeta y límites, no una FK.

La comprobación runtime de `SHOW CREATE TABLE` e índices no fue ejecutada por Codex. Las consultas para confirmar la estructura real están en [TH_COFEPRIS_12D01_READ_ONLY_SQL_QUERIES.md](C:/xampp/htdocs/clinica-dolor/docs/audits/TH_COFEPRIS_12D01_READ_ONLY_SQL_QUERIES.md).

## 6. Semántica de carpeta

La revisión estática muestra que `carpeta` cumple dos funciones actuales:

1. Se persiste en `cofepris_folio` y se copia a `cofepris` al generar un registro.
2. Se utiliza como directorio bajo `public/storage/cofepris/{carpeta}/` para los PDFs.

El alta genera carpetas con una fecha y un sufijo aleatorio del servidor. El código no demuestra que la carpeta sea una propiedad clínica, que sea única por clínica o que nunca se haya reutilizado históricamente.

**FOLDER_SEMANTICS: CODE_STATIC_DIRECTORY_AND_LOGICAL_KEY**
**FOLDER_OWNERSHIP: NOT_VERIFIED**
**FOLDER_DUPLICATION: OWNER_REPORTED_ZERO_EXACT_DUPLICATES_BETWEEN_RANGES**

No se recomienda imponer `UNIQUE(id_clinica, carpeta)` hasta comprobar la reutilización histórica y el contrato administrativo.

## 7. Semántica de `estado`

El código actual:

- selecciona `estado = 0` al generar el siguiente folio;
- cuenta `estado = 0` como rango activo;
- presenta `estado = 0` como `Activo` y `estado = 1` como `Finalizados`;
- actualiza el rango a `estado = 1` cuando considera que se agotó.

Por tanto, la interpretación observable en código es `0 = activo/disponible` y `1 = finalizado/agotado`. El significado de negocio no está documentado de forma independiente; no debe extrapolarse a cancelado, reservado u otro estado.

**ESTADO_SEMANTICS: CODE_INFERRED_0_ACTIVE_1_FINALIZED; BUSINESS_DEFINITION_PENDING**

El rango 9 tiene `estado = 0` y 21 registros reportados. Por ello, `estado = 0` no puede interpretarse como "rango nunca utilizado"; en el código significa que todavía puede ser seleccionado por el generador, sujeto a la lógica vigente.

## 8. Consistencia de intervalos

Los resultados agregados reportados por el propietario verifican la consistencia operacional de los seis rangos sin publicar sus valores literales:

| Comprobación | Resultado |
|---|---|
| `folio_final >= folio_inicial` | `OWNER_REPORTED_NUMERICALLY_CONSISTENT` |
| Tamaño calculado coincide con `total_folio` | `OWNER_REPORTED_CONSISTENT` |
| Intervalo invertido | 0 reportados |
| Rangos idénticos | 0 reportados |
| Solapamientos | 0 reportados |
| Huecos | `NOT_VERIFIED`; no son error automático |
| Ceros iniciales | 0 reportados |
| Formato incompatible | 0 reportados |

Las consultas usan `DECIMAL(65,0)` sólo tras comprobar el patrón decimal y conservan los valores originales como texto. No se deben hacer comparaciones lexicográficas ni conversiones a entero de precisión insuficiente.

## 9. Conciliación de usos y duplicados

| Elemento | Estado |
|---|---|
| Total de registros | 345, reportado por propietario |
| Filas con paciente válido | 344, reportado por propietario |
| Valores distintos de `folio` en filas válidas | 342, reportado por propietario |
| Ocurrencias adicionales frente a valores distintos | 2, derivación aritmética |
| Duplicados por `folio` | Dos ocurrencias adicionales entre válidos; grupos exactos no publicados |
| Duplicados por `carpeta + folio` | Par explícito 256/257 comparte carpeta y folio |
| Duplicados por paciente + folio | `NOT_VERIFIED` |
| Folios fuera de todo intervalo | 0, reportado por propietario |
| Múltiples rangos candidatos | 0, reportado por propietario |
| Relaciones durables uso → rango | No existen en el esquema reportado |

343 registros tienen coincidencia numérica y de carpeta según la evidencia del propietario. Se clasifican como `UNIQUE_CANDIDATE`/`MATCH_CANDIDATE`, no como propiedad administrativa verificada, porque no existe una referencia durable uso → rango ni `id_clinica` en el catálogo.

### 9.1 Matriz definitiva de excepciones

Las referencias siguientes se conservan sin corregir, reasignar ni eliminar:

| Registro | Hallazgo reportado | Paciente/clínica | Tratamiento en 12D-01 |
|---:|---|---|---|
| 23 | Folio dentro del rango numérico 3, pero carpeta del rango 2 | Paciente válido, Clínica 1 | `FOLDER_EXCEPTION`; preservar valores originales |
| 26 | Folio dentro del rango numérico 3, pero carpeta del rango 2 | Paciente válido, Clínica 1 | `FOLDER_EXCEPTION`; preservar valores originales |
| 256 | Rango 8; paciente inexistente; comparte carpeta y folio con 257 | Huérfano | `OWNER_UNKNOWN`; preservar y bloquear reasignación automática |
| 257 | Rango 8; paciente válido; comparte carpeta y folio con 256 | Paciente válido, Clínica 1 | `SHARED_LOGICAL_KEY`; preservar y revisar relación documental |

Los registros 256 y 257 presentan una colisión lógica de `carpeta + folio`. Esto demuestra que ambos calcularían la misma ruta conforme al código actual si los valores reportados son iguales; no demuestra que el archivo físico exista ni que haya ocurrido sobrescritura.

## 10. Matriz de conciliación de rangos

La tabla siguiente registra sólo evidencia disponible. Los campos faltantes se mantienen expresamente como `NOT_VERIFIED`.

| RANGE_ID | DECLARED_TOTAL | NUMERIC_FORMAT | LEADING_ZEROES | INTERVAL_VALIDITY | INTERVAL_SIZE_VALIDITY | OVERLAP_STATUS | FOLDER_SEMANTICS | FOLDER_DUPLICATION | STATUS_SEMANTICS | USAGE_MATCH_STATUS | OWNERSHIP_STATUS | DOCUMENT_RELATION_STATUS | GROUP | REQUIRED_EVIDENCE | READY_FOR_BACKFILL |
|---:|---:|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 2 | 200 | OWNER_REPORTED_NUMERIC | 0 | OWNER_REPORTED_VALID | OWNER_REPORTED_MATCH | OWNER_REPORTED_ZERO | CODE_STATIC_DIRECTORY_AND_LOGICAL_KEY | OWNER_REPORTED_ZERO | CODE_INFERRED_STATE_1_FINALIZED | 28_MATCHING | H2_OWNER_UNKNOWN | DOCUMENT_RELATION_INFERRED | H2 | evidencia administrativa de propiedad | NO |
| 3 | 200 | OWNER_REPORTED_NUMERIC | 0 | OWNER_REPORTED_VALID | OWNER_REPORTED_MATCH | OWNER_REPORTED_ZERO | CODE_STATIC_DIRECTORY_AND_LOGICAL_KEY | OWNER_REPORTED_ZERO | CODE_INFERRED_STATE_1_FINALIZED | 48_MATCHING_PLUS_2_FOLDER_EXCEPTIONS | H2_OWNER_UNKNOWN | DOCUMENT_RELATION_INFERRED | H2 | explicación de excepciones 23/26 y propiedad | NO |
| 4 | 200 | OWNER_REPORTED_NUMERIC | 0 | OWNER_REPORTED_VALID | OWNER_REPORTED_MATCH | OWNER_REPORTED_ZERO | CODE_STATIC_DIRECTORY_AND_LOGICAL_KEY | OWNER_REPORTED_ZERO | CODE_INFERRED_STATE_1_FINALIZED | 97_MATCHING | H2_OWNER_UNKNOWN | DOCUMENT_RELATION_INFERRED | H2 | evidencia administrativa de propiedad | NO |
| 5 | 200 | OWNER_REPORTED_NUMERIC | 0 | OWNER_REPORTED_VALID | OWNER_REPORTED_MATCH | OWNER_REPORTED_ZERO | CODE_STATIC_DIRECTORY_AND_LOGICAL_KEY | OWNER_REPORTED_ZERO | CODE_INFERRED_STATE_1_FINALIZED | 49_MATCHING | H2_OWNER_UNKNOWN | DOCUMENT_RELATION_INFERRED | H2 | evidencia administrativa de propiedad | NO |
| 8 | 200 | OWNER_REPORTED_NUMERIC | 0 | OWNER_REPORTED_VALID | OWNER_REPORTED_MATCH | OWNER_REPORTED_ZERO | CODE_STATIC_DIRECTORY_AND_LOGICAL_KEY | OWNER_REPORTED_ZERO | CODE_INFERRED_STATE_1_FINALIZED | 100_FOLDER_MATCHING_PLUS_1_ORPHAN | H2_OWNER_UNKNOWN | DOCUMENT_RELATION_INFERRED | H2 | relación documental 256/257 y propiedad | NO |
| 9 | 200 | OWNER_REPORTED_NUMERIC | 0 | OWNER_REPORTED_VALID | OWNER_REPORTED_MATCH | OWNER_REPORTED_ZERO | CODE_STATIC_DIRECTORY_AND_LOGICAL_KEY | OWNER_REPORTED_ZERO | CODE_INFERRED_STATE_0_ACTIVE | 21_MATCHING | H2_OWNER_UNKNOWN | DOCUMENT_RELATION_INFERRED | H2 | no asumir activo = nunca utilizado | NO |

No hay rangos H1 con propietario verificado, H3 con varios propietarios demostrados, H4 sin uso comprobado ni H5 con inconsistencia demostrada. Los seis permanecen en H2 porque la tabla no conserva propiedad clínica y la evidencia agregada no asigna rangos.

## 11. Registro huérfano

Existe un registro `cofepris` cuyo `id_paciente` no corresponde a `pc_paciente`. No se buscaron nombres ni datos personales, no se creó paciente, no se reasignó ni se eliminó el registro.

| Regla | Estado |
|---|---|
| `OWNER_UNKNOWN` | Aplicable |
| `FAIL_CLOSED` | Recomendado |
| `PRESERVE_HISTORICAL_RECORD` | Recomendado |
| Reasignación automática | Prohibida |
| Eliminación automática | Prohibida |
| Folio/carpeta del huérfano | Rango 8 y clave compartida con 257, reportado por propietario |
| Documento relacionado | `NOT_VERIFIED`; no se abrió ni descargó ningún PDF |

La conciliación manual del huérfano debe ser una decisión independiente y documentada.

## 12. Relación con PDFs

La relación funcional inferida por código es:

```text
cofepris.id
  -> cofepris.id_paciente
  -> cofepris.carpeta + cofepris.folio
  -> public/storage/cofepris/{carpeta}/{folio}.pdf
```

La ruta se utiliza en `PdfController::pdfCofepris()`. El controlador carga el registro por ID y después valida el acceso al paciente; no existe una FK del PDF ni una propiedad clínica persistida en el catálogo de rangos.

### 12.1 Flujo estático de generación y descarga

- `CofeprisModel::guardarFolio()` crea una carpeta con fecha y sufijo aleatorio bajo `public/storage/cofepris/`, genera un PDF por página como `{folio}.pdf` y registra el rango en `cofepris_folio`.
- `CofeprisModel::generarFolio()` selecciona globalmente el rango más reciente con `estado = 0`, calcula el siguiente folio por `carpeta` y devuelve `carpeta + folio` para el registro clínico.
- `CofeprisModel::insertPacienteCofepris()` persiste esos dos valores en `cofepris`; no persiste `cofepris_folio.id`.
- `PdfController::pdfCofepris($id)` carga `cofepris` por el ID del registro, deriva la ruta con `carpeta + folio`, abre el PDF fuente y superpone datos clínicos.
- La autorización estática observada es `AuthMiddleware('clinica')->authPermisos()` más `assertPatientAccess()` sobre el paciente del registro. No se verificó autorización HTTP real ni la protección del directorio público frente a acceso directo.
- No se observó una identidad documental adicional que diferencie dos registros que compartan `carpeta + folio`; el ID de `cofepris` identifica el registro, no el archivo físico.

Por tanto, 256 y 257 tienen una **misma ruta calculada** según la evidencia reportada, pero no se puede afirmar `PHYSICAL_FILE_EXISTS`, `PHYSICAL_FILE_SHARED` ni `OVERWRITE_DEMONSTRATED` sin inspección de storage autorizada. Esa inspección no se realizó.

| Relación | Estado |
|---|---|
| Carpeta y folio presentes en registro | Reportado para la excepción 256/257 |
| Correspondencia con rango | 343 coincidencias numéricas y de carpeta; excepciones 23/26 |
| Existencia del archivo | `NOT_VERIFIED` |
| Propiedad del archivo por clínica | `NOT_VERIFIED` |
| Acceso directo al storage | `NOT_VERIFIED` |
| Autorización HTTP/PDF cruzada | `NOT_VERIFIED` |

Para 256 y 257, la ruta calculada por el código sería la misma si se confirma que `carpeta` y `folio` son idénticos, pero no se verificó la existencia del archivo físico, un acceso compartido real ni sobrescritura. La existencia de un archivo no debe interpretarse como prueba de propiedad clínica. No se abrieron, descargaron ni modificaron PDFs.

### 12.2 Verificación documental 12D-01-R2

La revisión estática confirmó que `cofepris.id` no forma parte del nombre físico del PDF. Para los registros 256 y 257, que comparten carpeta y folio según la evidencia del propietario, el código calcula la misma expresión de ruta; esto demuestra una colisión lógica potencial, no una sobrescritura física.

La carpeta `public/storage/cofepris` existe localmente y contiene 2,006 archivos PDF contados por metadatos. No se identificó el archivo correspondiente a 256/257 porque Codex no dispone de sus valores literales y no se ejecutó SQL. No se abrieron archivos ni se inspeccionó su contenido.

La configuración Apache inspeccionada no contiene una regla `.htaccess` específica para denegar `public/storage/cofepris`. `public/.htaccess` permite que los archivos existentes no pasen por `index.php`, y el VirtualHost local permite overrides. Esto deja una exposición directa potencial, pero el acceso HTTP no fue probado.

`PdfController::pdfCofepris()` aplica autorización estática por paciente después de cargar el registro por ID. Esa protección no demuestra control de una URL directa al archivo físico. El `DownloadController` no mostró una rama específica de autorización COFEPRIS.

**DOCUMENT_TRACEABILITY_STATUS: STATIC_IDENTITY_VERIFIED_PHYSICAL_FILE_AND_HTTP_PENDING**

## 13. Riesgos y severidades conservadas

### P0

No se confirmó un P0 durante esta auditoría documental.

### P1

- Propiedad clínica no persistida en `cofepris_folio`.
- Edición y surtido históricamente condicionados por identificadores que requieren alcance por paciente/clínica.
- Registro huérfano sin propietario demostrable.
- PDF construido desde carpeta/folio sin vínculo durable de documento y clínica.

### P2

- Generación global sobre el último rango activo y `MAX(CAST(folio AS UNSIGNED))` por carpeta.
- Posibles solapamientos y carpetas reutilizadas.
- SQL heredado con concatenación de parámetros.
- Carga del registro por ID antes de completar autorización contextual en algunos flujos.
- Almacenamiento bajo `public/storage/cofepris` pendiente de verificación de acceso directo.
- Salida HTML clínica del detalle pendiente de revisión de escape.

### P3

- Definición de negocio de `estado` no formalizada.
- Ausencia de referencia durable entre uso y rango.
- Reglas de unicidad y concurrencia no demostradas.
- Evidencia multiclínica inexistente; una sola clínica no permite probar aislamiento entre tenants.

No se redujeron severidades por la ausencia de explotación HTTP demostrada.

## 14. Preparación para backfill, índices y restricciones

| Cambio futuro | Readiness |
|---|---|
| `cofepris_folio.id_clinica` | `REQUIRES_HISTORICAL_RECONCILIATION + REQUIRES_OWNER_DECISION` |
| `cofepris.cofepris_folio_id` | `REQUIRES_HISTORICAL_RECONCILIATION` |
| Índice de consulta por clínica | `READY_FOR_DESIGN; IMPLEMENTATION_BLOCKED` |
| Unicidad de `(clínica, carpeta)` | `REQUIRES_HISTORICAL_RECONCILIATION` |
| Unicidad de `(rango, folio)` | `REQUIRES_HISTORICAL_RECONCILIATION + BUSINESS_RULE` |
| Restricción anti-solapamiento | `REQUIRES_OWNER_DECISION + SCHEMA_DESIGN` |
| Protección del almacenamiento PDF | `READY_FOR_DESIGN; RUNTIME_AND_STORAGE_POLICY_BLOCKED` |
| Backfill de propietario clínico | `BLOCKED — seis rangos H2` |
| Backfill de `cofepris_folio_id` | `BLOCKED — excepciones y relación durable pendientes` |

Precondiciones mínimas para una futura migración:

1. `SHOW CREATE TABLE` e índices runtime confirmados.
2. Límites y carpetas de los seis rangos conciliados, con resultados agregados ya reportados y referencias originales preservadas.
3. Excepciones 23/26 y colisión lógica 256/257 resueltas por decisión documental; no por inferencia.
4. Solapamientos y duplicados clasificados.
5. Propietario histórico aprobado por un responsable.
6. Huérfano tratado por decisión explícita.
7. Estrategia para documentos sin relación durable.
8. Dos clínicas sintéticas para pruebas cruzadas.
9. Plan de rollback y ventana de migración.

## 15. Validaciones

| Validación | Resultado |
|---|---|
| PHP lint de archivos COFEPRIS inspeccionados | PASS; ejecutado previamente en 12C |
| Revisión estática de modelos, controladores, PDF y rutas | PASS_STATIC |
| SQL read-only ejecutado por Codex | NO; socket local bloqueado antes de consulta |
| SQL read-only preparado para propietario | PASS; documento 12D01 creado |
| Resultados agregados locales | OWNER_REPORTED_LOCAL_SQL; consolidados, no reproducidos por Codex |
| Trazabilidad de storage/PDF | STATIC_CODE_REVIEW; archivo físico y acceso directo no verificados |
| Navegador/HTTP/PDF | NOT_VERIFIED |
| Surtido real | NOT_VERIFIED |
| Migración/backfill | NO EJECUTADO |
| `git diff --check` | PASS; sin errores de whitespace nuevos en los documentos creados |

## 16. Recomendación para 12D-02

La conciliación numérica puede considerarse cerrada con evidencia reportada por el propietario: 343 coincidencias, dos excepciones conocidas, cero fuera de rango, cero múltiples, cero solapamientos y cero carpetas duplicadas. No iniciar backfill ni DDL todavía: la siguiente fase requiere una decisión documental sobre propiedad histórica y el tratamiento de las excepciones 23/26/256/257.

La recomendación técnica sigue siendo una propiedad explícita de clínica para el catálogo de rangos y una referencia durable desde `cofepris`, condicionada a la conciliación. Si aparecen carpetas reutilizadas, solapamientos o históricos ambiguos, usar una entidad de asignación/documento durable en lugar de inferir propiedad por coincidencia.

12D-02 deberá diseñar, sin ejecutar:

- contrato de asignación por clínica;
- modelo de referencia durable rango/uso/documento;
- reglas de unicidad y concurrencia;
- estrategia de huérfanos y documentos;
- autorización por clínica y paciente en cada operación;
- migración reversible y pruebas con dos clínicas sintéticas.

## 17. Informe final

```yaml
TH_DOCTOR_COFEPRIS_HISTORICAL_RANGE_RECONCILIATION_12D_01:
  STATUS: PARTIAL_NUMERIC_RECONCILIATION_CLOSED_DOCUMENT_TRACEABILITY_PENDING
  BRANCH: Silvino
  HEAD: 59654e05dc9f931c21b5392238961bcddb3ab2e4
  WORKTREE_PRESERVED: YES
  DATABASE: bd_clinica_OWNER_REPORTED
  MARIADB_VERSION: 10.4.32_OWNER_REPORTED
  DATABASE_ACCESS: CODEX_BLOCKED_BEFORE_QUERY
  OWNER_SQL_EVIDENCE_REUSED: YES
  HISTORICAL_RANGES_TOTAL: 6_OWNER_REPORTED
  DECLARED_FOLIOS_TOTAL: 1200_OWNER_REPORTED
  RANGE_IDS: [2, 3, 4, 5, 8, 9]
  RANGES_VERIFIED: 6_NUMERIC_AND_INTERVAL_RESULTS_OWNER_REPORTED
  REGISTRATIONS_WITH_MATCHING_RANGE_AND_FOLDER: 343_OWNER_REPORTED
  FOLDER_EXCEPTIONS: 2_RECORDS_23_26
  ORPHAN_RECORD: 256
  DUPLICATE_RECORDS: 256_257_SHARED_FOLDER_AND_FOLIO
  NUMERIC_RANGE_FORMAT: OWNER_REPORTED_FOR_SIX_RANGES
  LEADING_ZERO_ANALYSIS: 0_OWNER_REPORTED
  INTERVAL_CONSISTENCY: OWNER_REPORTED_VALID_AND_SIZE_MATCHING
  RANGE_OVERLAPS: 0_OWNER_REPORTED
  FOLDER_DUPLICATES: 0_OWNER_REPORTED_BETWEEN_RANGES
  FOLDER_SEMANTICS: CODE_STATIC_DIRECTORY_AND_LOGICAL_KEY
  ESTADO_SEMANTICS: CODE_INFERRED_0_ACTIVE_1_FINALIZED_BUSINESS_PENDING
  RANGE_STATUS_SEMANTICS: ESTADO_0_SELECTED_AS_ACTIVE_ESTADO_1_FINALIZED_IN_CODE; BUSINESS_DEFINITION_PENDING
  COFEPRIS_RECORDS_TOTAL: 345_OWNER_REPORTED
  VALID_PATIENT_RELATIONS: 344_OWNER_REPORTED
  ORPHAN_RECORDS: 1_OWNER_REPORTED
  DUPLICATE_FOLIO_ANALYSIS: 2_EXTRA_VALID_OCCURRENCES_GROUPS_NOT_FULLY_SPECIFIED
  DUPLICATE_FOLDER_FOLIO_ANALYSIS: EXPLICIT_SHARED_PAIR_256_257
  USAGE_TO_RANGE_MAPPING: 343_NUMERIC_AND_FOLDER_MATCHES_OWNER_REPORTED
  UNMATCHED_USAGE_RECORDS: 2_FOLDER_EXCEPTIONS_23_26
  MULTIPLE_RANGE_CANDIDATES: 0_OWNER_REPORTED
  RANGES_WITH_VERIFIED_OWNER: 0_DEMONSTRATED
  RANGES_WITH_UNKNOWN_OWNER: 6
  DOCUMENT_RELATIONS: SAME_CALCULATED_PATH_FOR_256_257_NOT_PHYSICALLY_VERIFIED
  PDF_STORAGE_REVIEW: STATIC_ONLY_RUNTIME_NOT_VERIFIED
  PDF_COLLISION_256_257: SAME_LOGICAL_PATH_IF_VALUES_EQUAL_OVERWRITE_NOT_DEMONSTRATED
  PDF_PATH_IMPLEMENTATION: public/storage/cofepris/{carpeta}/{folio}.pdf_FROM_CODE
  PDF_FILE_EXISTENCE: NOT_VERIFIED
  PDF_STORAGE_AUTHORIZATION: PATIENT_AUTH_STATIC_AFTER_ID_LOOKUP_DIRECT_HTTP_NOT_VERIFIED
  HISTORICAL_RECONCILIATION_MATRIX: UPDATED_WITH_OWNER_REPORTED_NUMERIC_RESULTS
  BACKFILL_READINESS: NO
  UNIQUENESS_READINESS: NO
  OVERLAP_CONSTRAINT_READINESS: NO
  RANGES_WITH_PROVEN_CLINIC_OWNER: 0
  READY_FOR_BACKFILL: NO
  READY_FOR_SCHEMA_IMPLEMENTATION: DESIGN_ONLY_NOT_IMPLEMENTATION_AUTHORIZED
  P0_CONFIRMED: NONE
  P1_RISKS: CLINIC_OWNERSHIP_RECORD_SCOPE_ORPHAN_DOCUMENT_SCOPE
  P2_RISKS: GLOBAL_RANGE_SQL_CONCATENATION_AUTH_ORDER_PDF_STORAGE_HTML_OUTPUT
  P3_RISKS: BUSINESS_STATE_UNDEFINED_DURABLE_REFERENCE_MISSING_MULTICLINIC_UNVERIFIED
  PHP_LINT: PASS_PREVIOUSLY_EXECUTED
  STATIC_REVIEW: PASS
  READ_ONLY_SQL: OWNER_REPORTED_RESULTS_CONSOLIDATED_CODEX_NOT_EXECUTED
  DIFF_CHECK: PASS
  CODE_MODIFIED: NO
  DATABASE_MODIFIED: NO
  SCHEMA_MODIFIED: NO
  HISTORICAL_RECORDS_MODIFIED: NO
  PDF_MODIFIED: NO
  MIGRATIONS_EXECUTED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  DOCUMENTS_CREATED:
    - docs/audits/TH_DOCTOR_COFEPRIS_HISTORICAL_RANGE_RECONCILIATION_12D_01.md
    - docs/audits/TH_COFEPRIS_12D01_READ_ONLY_SQL_QUERIES.md
    - docs/audits/TH_DOCTOR_COFEPRIS_DOCUMENT_TRACEABILITY_VERIFICATION_12D_01_R2.md
  OWNER_SQL_QUERIES_PREPARED: YES
  BLOCKERS: HISTORICAL_OWNER_ASSIGNMENT_DOCUMENT_STORAGE_AUTHORIZATION_256_257_POLICY_DIRECT_HTTP_RUNTIME
  PENDING_OWNER_DECISIONS: HISTORICAL_RANGE_OWNERSHIP_23_26_256_257_ORPHAN_DOCUMENT_POLICY_UNIQUENESS_CONCURRENCY
  RESIDUAL_RISKS: EDIT_BY_ID_SURTIDO_BY_ID_GLOBAL_FOLIOS_PDF_DIRECT_ACCESS_LEGACY_SQL_FOLIO_RACE
  READY_FOR_12D_02: DESIGN_ONLY — IMPLEMENTATION_BLOCKED
  NEXT_RECOMMENDED_ACTION: REVIEW_EXCEPTIONS_AND_AUTHORIZE_12D_02_DESIGN_WITHOUT_BACKFILL
```

**DATABASE_MODIFIED: NO**
**SCHEMA_MODIFIED: NO**
**CODE_MODIFIED: NO**
**PDF_MODIFIED: NO**
**MIGRATIONS_EXECUTED: NO**
**COMMITS: NONE**
**DEPLOYMENTS: NONE**
