# TH-DOCTOR-SUBSEQUENT-NOTE-REFERENCE-DATA-CHECK-04D

## Alcance

Verificación focalizada, de solo lectura, para explicar las respuestas HTML idénticas reportadas en 04B para una referencia válida y otra inexistente. No se ejecutó desde Codex porque este entorno no tiene acceso al MySQL local de Windows.

## SQL real identificado

| Endpoint | Controlador | Modelo | Tabla | Predicado efectivo |
|---|---|---|---|---|
| `GET /buscar/tabla-notas-subsecuentes/{idPaciente}/{referencia}` | `BusquedasController::tableNotasSubsecuentes` | `NotaSubsecuenteModel::mostrarTablaNotas` | `nota_subsecuente` | `id_paciente = :id_paciente` y, salvo referencia `0`, `codigo_referencia = :referencia` |
| `GET /buscar/tabla-laboratorio/{idPaciente}/{referencia}` | `BusquedasController::tableLaboratorio` | `LaboratorioModel::mostrarTablaLaboratorio` | `laboratorio` | `id_paciente = :id_paciente` y, salvo referencia `0`, `codigo_referencia = :referencia` |

Los dos modelos usan consultas preparadas y ordenan por `fecha_hora DESC`. Los endpoints ejecutan `guardPatient()` antes de llamar al modelo; ese guard aplica `assertPatientAccess()` para el paciente y la clínica autorizados. No se identificó una relación adicional necesaria para estos dos conteos.

## Consultas exactas para Windows/MySQL

Ejecutar en phpMyAdmin o en el cliente MySQL local, sin credenciales en el informe. Confirmar primero que el resultado sea exactamente `bd_clinica`; si no lo es, detenerse.

```sql
SELECT
    DATABASE() AS base_activa,
    CASE WHEN DATABASE() = 'bd_clinica' THEN 'CONTINUAR' ELSE 'DETENER' END AS control_base;

SET @paciente = 310;
SET @referencia_valida = '6ad2a4de1fd757.87676796';
SET @referencia_invalida = 'INVALID04B999999.999999';

SELECT 'NOTA_VALIDA' AS caso, COUNT(*) AS filas
FROM bd_clinica.nota_subsecuente
WHERE id_paciente = @paciente
  AND codigo_referencia = @referencia_valida;

SELECT 'NOTA_INVALIDA' AS caso, COUNT(*) AS filas
FROM bd_clinica.nota_subsecuente
WHERE id_paciente = @paciente
  AND codigo_referencia = @referencia_invalida;

SELECT 'LAB_VALIDO' AS caso, COUNT(*) AS filas
FROM bd_clinica.laboratorio
WHERE id_paciente = @paciente
  AND codigo_referencia = @referencia_valida;

SELECT 'LAB_INVALIDO' AS caso, COUNT(*) AS filas
FROM bd_clinica.laboratorio
WHERE id_paciente = @paciente
  AND codigo_referencia = @referencia_invalida;

SELECT 'NOTA_REFERENCIAS_PACIENTE' AS caso, codigo_referencia, COUNT(*) AS filas
FROM bd_clinica.nota_subsecuente
WHERE id_paciente = @paciente
GROUP BY codigo_referencia
ORDER BY codigo_referencia;

SELECT 'LAB_REFERENCIAS_PACIENTE' AS caso, codigo_referencia, COUNT(*) AS filas
FROM bd_clinica.laboratorio
WHERE id_paciente = @paciente
GROUP BY codigo_referencia
ORDER BY codigo_referencia;
```

Estas consultas sólo leen y agregan conteos. No consultan ni imprimen contenido clínico, nombres de archivos, diagnósticos, medicamentos, descripciones o documentos. No contienen `INSERT`, `UPDATE`, `DELETE`, `REPLACE`, `ALTER`, `DROP`, `TRUNCATE` ni tablas temporales.

## Interpretación

- Si `NOTA_VALIDA` y `NOTA_INVALIDA` son `0`, las respuestas HTML idénticas de Nota son compatibles con la tabla vacía estructural que genera el modelo.
- Si `LAB_VALIDO` y `LAB_INVALIDO` son `0`, aplica la misma explicación para Laboratorio.
- Si la referencia válida tiene filas y la inválida tiene `0`, debe comprobarse el HTML de la referencia válida antes de atribuir la igualdad a una plantilla vacía.
- Si la referencia inválida tiene filas, existe una discrepancia que requiere investigación adicional antes de modificar código.

La referencia `0` no se usa en esta comprobación: el código la trata explícitamente como modo de historial completo del paciente.

## Resultado 04D

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_REFERENCE_DATA_CHECK_04D:
  STATUS: PARTIAL_SQL_PREPARED_RUNTIME_PENDING
  ACTUAL_NOTE_TABLE: bd_clinica.nota_subsecuente
  ACTUAL_LABORATORY_TABLE: bd_clinica.laboratorio
  VALID_REFERENCE_NOTE_COUNT: NOT_VERIFIED
  INVALID_REFERENCE_NOTE_COUNT: NOT_VERIFIED
  VALID_REFERENCE_LAB_COUNT: NOT_VERIFIED
  INVALID_REFERENCE_LAB_COUNT: NOT_VERIFIED
  IDENTICAL_HTML_EXPLANATION: NOT_DETERMINED_WITHOUT_REAL_COUNTS
  DATABASE_READ_ONLY_VERIFIED: NO_CODEX_MYSQL_ACCESS
  APPLICATION_CHANGES: NONE
  DATABASE_CHANGES: NONE
  REFERENCE_FILTER_CONFIRMED: PASS_STATIC
  SECURITY_VULNERABILITY_CONFIRMED: NO
  READY_FOR_UX_PHASE_05: NO_RUNTIME_DATA_PENDING
  BLOCKERS: MySQL local bd_clinica is inaccessible from this Codex execution environment
  NEXT_ACTION: Execute the SELECT block in local Windows MySQL and record only the six count results
```

## Restricciones cumplidas

- No se modificaron PHP, CSS, JavaScript, rutas, modelos ni controladores.
- No se accedió a la base de datos ni se realizaron escrituras.
- No se modificaron registros, esquemas, commits o despliegues.
- El worktree preexistente se preservó.
