# TH-COFEPRIS-12D01 — Consultas SQL de conciliación histórica en modo read-only

## Propósito

Consultas independientes para ejecutar manualmente en phpMyAdmin o en el cliente MariaDB local autorizado. Están preparadas para MariaDB 10.4.32 y sólo utilizan `SELECT`, `SHOW` e `information_schema`.

No ejecutar como script indiscriminado. Ejecutar cada bloque por separado, conservar únicamente los conteos y metadatos solicitados, y no copiar valores clínicos, nombres de pacientes, diagnósticos, medicamentos ni rutas privadas.

Estas consultas no asignan clínicas, no rellenan `cofepris_folio_id`, no modifican rangos y no prueban por sí mismas la propiedad histórica. Una coincidencia por carpeta y rango se clasifica como candidata hasta contar con evidencia administrativa durable.

## Precondiciones y guardas

Antes de ejecutar los bloques, confirmar que la interfaz muestra la base `bd_clinica`. El primer bloque debe devolver exactamente `bd_clinica`.

```sql
SELECT DATABASE() AS selected_database,
       VERSION() AS server_version;
```

Si `selected_database` no es `bd_clinica`, detenerse y no ejecutar los bloques siguientes.

No se incluyen credenciales. No usar `INSERT`, `UPDATE`, `DELETE`, `REPLACE`, `ALTER`, `CREATE`, `DROP`, `TRUNCATE`, `SET`, tablas temporales, procedimientos ni `LOCK TABLES`.

## 1. Esquema, índices y relaciones

Ejecutar cada sentencia por separado y conservar sólo la salida de esquema.

```sql
SHOW CREATE TABLE cofepris_folio;
```

```sql
SHOW CREATE TABLE cofepris;
```

```sql
SHOW CREATE TABLE pc_paciente;
```

```sql
SHOW INDEX FROM cofepris_folio;
```

```sql
SHOW INDEX FROM cofepris;
```

```sql
SELECT TABLE_NAME,
       COLUMN_NAME,
       COLUMN_TYPE,
       IS_NULLABLE,
       COLUMN_KEY,
       ORDINAL_POSITION
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('cofepris_folio', 'cofepris', 'pc_paciente', 'clinicas')
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```

```sql
SELECT TABLE_NAME,
       CONSTRAINT_NAME,
       COLUMN_NAME,
       REFERENCED_TABLE_NAME,
       REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('cofepris_folio', 'cofepris', 'pc_paciente')
ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION;
```

### Resultado que debe copiar el propietario

- Tipo real de `folio_inicial` y `folio_final`.
- Índices `UNIQUE` adicionales a la clave primaria.
- Claves foráneas reales.
- Existencia o ausencia de `id_clinica` y de cualquier referencia durable al rango.

## 2. Inventario de los seis rangos

Esta consulta no convierte los límites en números si contienen caracteres no numéricos. La comparación numérica sólo se marca como posible cuando ambos límites contienen exclusivamente dígitos.

```sql
SELECT
    id,
    total_folio,
    estado,
    carpeta,
    folio_inicial,
    folio_final,
    CHAR_LENGTH(TRIM(CAST(folio_inicial AS CHAR))) AS inicio_longitud,
    CHAR_LENGTH(TRIM(CAST(folio_final AS CHAR))) AS final_longitud,
    CASE
        WHEN TRIM(CAST(folio_inicial AS CHAR)) REGEXP '^0[0-9]'
          OR TRIM(CAST(folio_final AS CHAR)) REGEXP '^0[0-9]'
        THEN 'LEADING_ZERO_PRESENT'
        ELSE 'NO_LEADING_ZERO_OBSERVED'
    END AS leading_zero_status,
    CASE
        WHEN TRIM(CAST(folio_inicial AS CHAR)) REGEXP '^[0-9]+$'
         AND TRIM(CAST(folio_final AS CHAR)) REGEXP '^[0-9]+$'
        THEN 'NUMERIC_TEXT'
        ELSE 'NON_NUMERIC_OR_EMPTY'
    END AS format_status
FROM cofepris_folio
ORDER BY id;
```

```sql
SELECT
    COUNT(*) AS ranges_total,
    SUM(CASE WHEN TRIM(CAST(folio_inicial AS CHAR)) REGEXP '^[0-9]+$'
                   AND TRIM(CAST(folio_final AS CHAR)) REGEXP '^[0-9]+$'
             THEN 1 ELSE 0 END) AS numeric_ranges,
    SUM(CASE WHEN TRIM(CAST(folio_inicial AS CHAR)) REGEXP '^0[0-9]'
                   OR TRIM(CAST(folio_final AS CHAR)) REGEXP '^0[0-9]'
             THEN 1 ELSE 0 END) AS ranges_with_leading_zero,
    SUM(CASE WHEN carpeta IS NULL OR TRIM(CAST(carpeta AS CHAR)) = ''
             THEN 1 ELSE 0 END) AS ranges_without_folder
FROM cofepris_folio;
```

## 3. Orden y tamaño de intervalos

`DECIMAL(65,0)` se usa únicamente después de comprobar el patrón decimal. No se debe sustituir por una comparación lexicográfica ni por un `CAST` a entero de precisión desconocida.

```sql
SELECT
    id,
    total_folio,
    CASE
        WHEN TRIM(CAST(folio_inicial AS CHAR)) REGEXP '^[0-9]+$'
         AND TRIM(CAST(folio_final AS CHAR)) REGEXP '^[0-9]+$'
        THEN CASE
            WHEN CAST(TRIM(CAST(folio_final AS CHAR)) AS DECIMAL(65,0))
               >= CAST(TRIM(CAST(folio_inicial AS CHAR)) AS DECIMAL(65,0))
            THEN 'ORDER_VALID'
            ELSE 'ORDER_INVERTED'
        END
        ELSE 'NOT_NUMERIC_NOT_VERIFIABLE'
    END AS interval_order,
    CASE
        WHEN TRIM(CAST(folio_inicial AS CHAR)) REGEXP '^[0-9]+$'
         AND TRIM(CAST(folio_final AS CHAR)) REGEXP '^[0-9]+$'
         AND total_folio IS NOT NULL
        THEN CASE
            WHEN CAST(TRIM(CAST(folio_final AS CHAR)) AS DECIMAL(65,0))
               - CAST(TRIM(CAST(folio_inicial AS CHAR)) AS DECIMAL(65,0)) + 1
               = CAST(total_folio AS DECIMAL(65,0))
            THEN 'SIZE_MATCHES_DECLARED_TOTAL'
            ELSE 'SIZE_DIFFERS_FROM_DECLARED_TOTAL'
        END
        ELSE 'NOT_NUMERIC_OR_TOTAL_NOT_VERIFIABLE'
    END AS interval_size
FROM cofepris_folio
ORDER BY id;
```

```sql
SELECT
    SUM(CASE WHEN interval_order = 'ORDER_INVERTED' THEN 1 ELSE 0 END) AS inverted_ranges,
    SUM(CASE WHEN interval_size = 'SIZE_DIFFERS_FROM_DECLARED_TOTAL' THEN 1 ELSE 0 END) AS size_mismatches,
    SUM(CASE WHEN interval_order = 'NOT_NUMERIC_NOT_VERIFIABLE'
               OR interval_size = 'NOT_NUMERIC_OR_TOTAL_NOT_VERIFIABLE'
             THEN 1 ELSE 0 END) AS not_verifiable_ranges
FROM (
    SELECT
        CASE
            WHEN TRIM(CAST(folio_inicial AS CHAR)) REGEXP '^[0-9]+$'
             AND TRIM(CAST(folio_final AS CHAR)) REGEXP '^[0-9]+$'
            THEN CASE
                WHEN CAST(TRIM(CAST(folio_final AS CHAR)) AS DECIMAL(65,0))
                   >= CAST(TRIM(CAST(folio_inicial AS CHAR)) AS DECIMAL(65,0))
                THEN 'ORDER_VALID' ELSE 'ORDER_INVERTED'
            END
            ELSE 'NOT_NUMERIC_NOT_VERIFIABLE'
        END AS interval_order,
        CASE
            WHEN TRIM(CAST(folio_inicial AS CHAR)) REGEXP '^[0-9]+$'
             AND TRIM(CAST(folio_final AS CHAR)) REGEXP '^[0-9]+$'
             AND total_folio IS NOT NULL
            THEN CASE
                WHEN CAST(TRIM(CAST(folio_final AS CHAR)) AS DECIMAL(65,0))
                   - CAST(TRIM(CAST(folio_inicial AS CHAR)) AS DECIMAL(65,0)) + 1
                   = CAST(total_folio AS DECIMAL(65,0))
                THEN 'SIZE_MATCHES_DECLARED_TOTAL'
                ELSE 'SIZE_DIFFERS_FROM_DECLARED_TOTAL'
            END
            ELSE 'NOT_NUMERIC_OR_TOTAL_NOT_VERIFIABLE'
        END AS interval_size
    FROM cofepris_folio
) AS interval_checks;
```

## 4. Rangos idénticos y solapamientos

La siguiente consulta sólo devuelve pares de rangos con límites numéricos verificables. Un resultado vacío significa que no se observaron solapamientos entre los límites comparables; no demuestra ausencia de solapamientos en formatos no numéricos.

```sql
SELECT
    a.id AS range_a,
    b.id AS range_b,
    a.carpeta AS carpeta_a,
    b.carpeta AS carpeta_b,
    CASE WHEN a.carpeta = b.carpeta THEN 'SAME_FOLDER' ELSE 'DIFFERENT_FOLDER' END AS folder_relation,
    CASE WHEN a.start_n = b.start_n AND a.end_n = b.end_n THEN 'IDENTICAL_INTERVAL'
         ELSE 'OVERLAPPING_INTERVAL'
    END AS overlap_type
FROM (
    SELECT
        id,
        carpeta,
        CAST(TRIM(CAST(folio_inicial AS CHAR)) AS DECIMAL(65,0)) AS start_n,
        CAST(TRIM(CAST(folio_final AS CHAR)) AS DECIMAL(65,0)) AS end_n
    FROM cofepris_folio
    WHERE TRIM(CAST(folio_inicial AS CHAR)) REGEXP '^[0-9]+$'
      AND TRIM(CAST(folio_final AS CHAR)) REGEXP '^[0-9]+$'
) AS a
JOIN (
    SELECT
        id,
        carpeta,
        CAST(TRIM(CAST(folio_inicial AS CHAR)) AS DECIMAL(65,0)) AS start_n,
        CAST(TRIM(CAST(folio_final AS CHAR)) AS DECIMAL(65,0)) AS end_n
    FROM cofepris_folio
    WHERE TRIM(CAST(folio_inicial AS CHAR)) REGEXP '^[0-9]+$'
      AND TRIM(CAST(folio_final AS CHAR)) REGEXP '^[0-9]+$'
) AS b
  ON a.id < b.id
 AND a.start_n <= b.end_n
 AND b.start_n <= a.end_n
ORDER BY a.id, b.id;
```

```sql
SELECT
    COUNT(*) AS folders_repeated,
    SUM(range_count) AS ranges_in_repeated_folders,
    MAX(range_count) AS maximum_ranges_in_one_folder
FROM (
    SELECT carpeta, COUNT(*) AS range_count
    FROM cofepris_folio
    GROUP BY carpeta
    HAVING COUNT(*) > 1
) AS repeated_folders;
```

No interpretar un hueco entre intervalos como error automático. La continuidad o separación de rangos depende del contrato administrativo.

## 5. Estados y capacidad declarada

```sql
SELECT
    estado,
    COUNT(*) AS range_count,
    SUM(total_folio) AS declared_folios,
    SUM(CASE WHEN total_folio IS NULL OR total_folio < 0 THEN 1 ELSE 0 END) AS invalid_declared_totals
FROM cofepris_folio
GROUP BY estado
ORDER BY estado;
```

```sql
SELECT
    COUNT(*) AS range_count,
    SUM(total_folio) AS declared_folios
FROM cofepris_folio;
```

La semántica observada en el código actual es: `estado = 0` se consulta como activo/disponible y `estado = 1` se presenta como finalizado; esta interpretación de código no sustituye una definición de negocio del propietario.

## 6. Uso histórico y duplicados

Estas consultas no leen campos clínicos. Para compartir resultados, conservar sólo los conteos.

```sql
SELECT
    COUNT(*) AS cofepris_total,
    COUNT(DISTINCT folio) AS distinct_folios,
    COUNT(DISTINCT carpeta) AS distinct_folders,
    COUNT(DISTINCT CONCAT(CAST(carpeta AS CHAR), '|', CAST(folio AS CHAR))) AS distinct_folder_folio,
    SUM(CASE WHEN folio IS NULL OR TRIM(CAST(folio AS CHAR)) = '' THEN 1 ELSE 0 END) AS empty_folios,
    SUM(CASE WHEN carpeta IS NULL OR TRIM(CAST(carpeta AS CHAR)) = '' THEN 1 ELSE 0 END) AS empty_folders
FROM cofepris;
```

```sql
SELECT
    COUNT(*) AS duplicate_folio_groups,
    COALESCE(SUM(group_count), 0) AS records_in_duplicate_groups,
    COALESCE(SUM(group_count - 1), 0) AS duplicate_occurrences,
    COALESCE(MAX(group_count), 0) AS largest_folio_group
FROM (
    SELECT folio, COUNT(*) AS group_count
    FROM cofepris
    GROUP BY folio
    HAVING COUNT(*) > 1
) AS duplicate_folios;
```

```sql
SELECT
    COUNT(*) AS duplicate_folder_folio_groups,
    COALESCE(SUM(group_count), 0) AS records_in_duplicate_groups,
    COALESCE(SUM(group_count - 1), 0) AS duplicate_occurrences,
    COALESCE(MAX(group_count), 0) AS largest_folder_folio_group
FROM (
    SELECT carpeta, folio, COUNT(*) AS group_count
    FROM cofepris
    GROUP BY carpeta, folio
    HAVING COUNT(*) > 1
) AS duplicate_folder_folios;
```

```sql
SELECT
    COUNT(*) AS duplicate_patient_folio_groups,
    COALESCE(SUM(group_count), 0) AS records_in_duplicate_groups,
    COALESCE(SUM(group_count - 1), 0) AS duplicate_occurrences
FROM (
    SELECT id_paciente, folio, COUNT(*) AS group_count
    FROM cofepris
    GROUP BY id_paciente, folio
    HAVING COUNT(*) > 1
) AS duplicate_patient_folios;
```

La diferencia reportada previamente de 344 filas válidas frente a 342 valores de `folio` distintos implica dos ocurrencias adicionales dentro de las filas válidas, pero no determina si existe un grupo de tres o dos grupos de dos. Estas consultas resuelven esa diferencia sin borrar ni corregir registros.

## 7. Relación paciente-clínica y registro huérfano

```sql
SELECT
    COUNT(*) AS cofepris_total,
    SUM(CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END) AS valid_patient_relations,
    SUM(CASE WHEN p.id IS NULL THEN 1 ELSE 0 END) AS orphan_records,
    COUNT(DISTINCT CASE WHEN p.id IS NOT NULL THEN p.id_clinica END) AS clinics_with_valid_records,
    COUNT(DISTINCT CASE WHEN p.id IS NOT NULL THEN c.id_paciente END) AS distinct_valid_patients
FROM cofepris c
LEFT JOIN pc_paciente p ON p.id = c.id_paciente;
```

```sql
SELECT
    COUNT(*) AS orphan_records,
    SUM(CASE WHEN c.carpeta IS NOT NULL AND TRIM(CAST(c.carpeta AS CHAR)) <> '' THEN 1 ELSE 0 END) AS orphan_with_folder,
    SUM(CASE WHEN c.folio IS NOT NULL AND TRIM(CAST(c.folio AS CHAR)) <> '' THEN 1 ELSE 0 END) AS orphan_with_folio,
    SUM(CASE WHEN c.carpeta IS NOT NULL AND TRIM(CAST(c.carpeta AS CHAR)) <> ''
                  AND c.folio IS NOT NULL AND TRIM(CAST(c.folio AS CHAR)) <> ''
             THEN 1 ELSE 0 END) AS orphan_with_folder_and_folio
FROM cofepris c
LEFT JOIN pc_paciente p ON p.id = c.id_paciente
WHERE p.id IS NULL;
```

No buscar el paciente huérfano por nombre ni reasignarlo. La política propuesta es `OWNER_UNKNOWN`, `FAIL_CLOSED`, `PRESERVE_HISTORICAL_RECORD`.

## 8. Candidatos de relación uso-rango

La consulta cuenta candidatos usando carpeta exacta y un intervalo decimal verificable. Un candidato único sigue siendo `MATCH_CANDIDATE`, no `MATCH_EXACT_VERIFIED`, porque el esquema no conserva una referencia durable al rango.

```sql
SELECT
    match_class,
    COUNT(*) AS record_count
FROM (
    SELECT
        c.id,
        CASE COUNT(f.id)
            WHEN 0 THEN 'NO_MATCH'
            WHEN 1 THEN 'UNIQUE_CANDIDATE'
            ELSE 'MULTIPLE_CANDIDATES'
        END AS match_class
    FROM cofepris c
    LEFT JOIN (
        SELECT
            id,
            carpeta,
            CAST(TRIM(CAST(folio_inicial AS CHAR)) AS DECIMAL(65,0)) AS start_n,
            CAST(TRIM(CAST(folio_final AS CHAR)) AS DECIMAL(65,0)) AS end_n
        FROM cofepris_folio
        WHERE TRIM(CAST(folio_inicial AS CHAR)) REGEXP '^[0-9]+$'
          AND TRIM(CAST(folio_final AS CHAR)) REGEXP '^[0-9]+$'
    ) AS f
      ON f.carpeta = c.carpeta
     AND TRIM(CAST(c.folio AS CHAR)) REGEXP '^[0-9]+$'
     AND CAST(TRIM(CAST(c.folio AS CHAR)) AS DECIMAL(65,0)) BETWEEN f.start_n AND f.end_n
    GROUP BY c.id
) AS candidate_counts
GROUP BY match_class
ORDER BY match_class;
```

```sql
SELECT
    candidate_class,
    COUNT(*) AS valid_patient_record_count
FROM (
    SELECT
        c.id,
        CASE COUNT(f.id)
            WHEN 0 THEN 'NO_MATCH'
            WHEN 1 THEN 'UNIQUE_CANDIDATE'
            ELSE 'MULTIPLE_CANDIDATES'
        END AS candidate_class
    FROM cofepris c
    INNER JOIN pc_paciente p ON p.id = c.id_paciente
    LEFT JOIN (
        SELECT
            id,
            carpeta,
            CAST(TRIM(CAST(folio_inicial AS CHAR)) AS DECIMAL(65,0)) AS start_n,
            CAST(TRIM(CAST(folio_final AS CHAR)) AS DECIMAL(65,0)) AS end_n
        FROM cofepris_folio
        WHERE TRIM(CAST(folio_inicial AS CHAR)) REGEXP '^[0-9]+$'
          AND TRIM(CAST(folio_final AS CHAR)) REGEXP '^[0-9]+$'
    ) AS f
      ON f.carpeta = c.carpeta
     AND TRIM(CAST(c.folio AS CHAR)) REGEXP '^[0-9]+$'
     AND CAST(TRIM(CAST(c.folio AS CHAR)) AS DECIMAL(65,0)) BETWEEN f.start_n AND f.end_n
    GROUP BY c.id
) AS valid_candidate_counts
GROUP BY candidate_class
ORDER BY candidate_class;
```

```sql
SELECT
    COUNT(*) AS records_outside_numeric_ranges
FROM cofepris c
LEFT JOIN (
    SELECT
        carpeta,
        CAST(TRIM(CAST(folio_inicial AS CHAR)) AS DECIMAL(65,0)) AS start_n,
        CAST(TRIM(CAST(folio_final AS CHAR)) AS DECIMAL(65,0)) AS end_n
    FROM cofepris_folio
    WHERE TRIM(CAST(folio_inicial AS CHAR)) REGEXP '^[0-9]+$'
      AND TRIM(CAST(folio_final AS CHAR)) REGEXP '^[0-9]+$'
) AS f
  ON f.carpeta = c.carpeta
 AND TRIM(CAST(c.folio AS CHAR)) REGEXP '^[0-9]+$'
 AND CAST(TRIM(CAST(c.folio AS CHAR)) AS DECIMAL(65,0)) BETWEEN f.start_n AND f.end_n
WHERE f.carpeta IS NULL;
```

## 9. Formatos y ceros iniciales en uso

```sql
SELECT
    SUM(CASE WHEN TRIM(CAST(folio AS CHAR)) REGEXP '^[0-9]+$' THEN 1 ELSE 0 END) AS numeric_text_records,
    SUM(CASE WHEN TRIM(CAST(folio AS CHAR)) REGEXP '^0[0-9]' THEN 1 ELSE 0 END) AS leading_zero_records,
    SUM(CASE WHEN folio IS NULL OR TRIM(CAST(folio AS CHAR)) = '' THEN 1 ELSE 0 END) AS empty_folio_records,
    SUM(CASE WHEN TRIM(CAST(folio AS CHAR)) NOT REGEXP '^[0-9]+$'
                  AND folio IS NOT NULL AND TRIM(CAST(folio AS CHAR)) <> ''
             THEN 1 ELSE 0 END) AS non_numeric_records
FROM cofepris;
```

## 10. Relaciones documentales comprobables por datos

SQL no puede demostrar la existencia ni la propiedad de archivos del sistema. Estos conteos sólo verifican que el registro tiene las partes de la clave lógica que el código usa para construir la ruta.

```sql
SELECT
    COUNT(*) AS cofepris_total,
    SUM(CASE WHEN carpeta IS NOT NULL AND TRIM(CAST(carpeta AS CHAR)) <> '' THEN 1 ELSE 0 END) AS records_with_folder,
    SUM(CASE WHEN folio IS NOT NULL AND TRIM(CAST(folio AS CHAR)) <> '' THEN 1 ELSE 0 END) AS records_with_folio,
    SUM(CASE WHEN carpeta IS NOT NULL AND TRIM(CAST(carpeta AS CHAR)) <> ''
                  AND folio IS NOT NULL AND TRIM(CAST(folio AS CHAR)) <> ''
             THEN 1 ELSE 0 END) AS records_with_folder_and_folio
FROM cofepris;
```

La revisión de la ruta `public/storage/cofepris/{carpeta}/{folio}.pdf`, el acceso directo y la autorización del PDF deben permanecer como revisión estática/runtime separada. No descargar documentos durante esta fase.

## 11. Formato de entrega del propietario

Copiar sólo los resultados agregados de cada bloque, junto con:

- `selected_database` y `server_version`.
- Tipos reales y constraints de las tres tablas.
- Conteos por `match_class`.
- Conteos de duplicados.
- Conteos de intervalos inválidos, solapamientos y carpetas repetidas.
- Conteos de registros sin relación documental lógica.

No copiar filas con nombres, diagnósticos, medicamentos, direcciones, PDFs, tokens o credenciales. Si una consulta de inspección devuelve identificadores o valores de carpeta/folio necesarios para conciliación manual, compartirlos sólo por el canal autorizado y no incorporarlos a documentación pública.

## 12. Interpretación mínima

- `NO_MATCH`: no existe un rango candidato por carpeta y límites numéricos; requiere revisión histórica.
- `UNIQUE_CANDIDATE`: un único rango candidato; no equivale a propiedad verificada.
- `MULTIPLE_CANDIDATES`: existe ambigüedad; bloquear backfill automático.
- `MATCH_EXACT_VERIFIED`: sólo puede declararse cuando exista una relación durable o evidencia administrativa independiente.
- Un registro huérfano no debe reasignarse ni eliminarse.
- La ausencia de solapamientos no demuestra por sí sola propiedad clínica.

**DATABASE_MODIFIED: NO**
**SCHEMA_MODIFIED: NO**
**MIGRATIONS_EXECUTED: NO**
