# TH-DOCTOR-SUBSEQUENT-NOTE-REFERENCE-SCOPE-VALIDATION-04C

## Resultado ejecutivo

La inspección estática confirma que los historiales de Nota subsecuente y Laboratorio aplican el ámbito de paciente y, para referencias distintas de `0`, el filtro `codigo_referencia` mediante parámetros preparados. No se confirmó un defecto de filtrado.

Las respuestas idénticas reportadas en 04B son compatibles con que ambos valores produzcan la misma respuesta vacía estructural. Esa explicación no puede demostrarse sin recuentos de `bd_clinica`; por ello no se modificó código de aplicación.

## Rutas trazadas

| Ruta | Controlador | Modelo | Filtros estáticos |
|---|---|---|---|
| `GET /buscar/tabla-notas-subsecuentes/{idPaciente}/{referencia}` | `BusquedasController::tableNotasSubsecuentes` | `NotaSubsecuenteModel::mostrarTablaNotas` | `id_paciente = :id_paciente` y `codigo_referencia = :referencia`, excepto referencia `0` |
| `GET /buscar/tabla-laboratorio/{idPaciente}/{referencia}` | `BusquedasController::tableLaboratorio` | `LaboratorioModel::mostrarTablaLaboratorio` | `id_paciente = :id_paciente` y `codigo_referencia = :referencia`, excepto referencia `0` |
| `GET /clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}` | `ClinicaController::pacienteNotaSubsecuente` | `codigoReferencia`, `idRecetaReferencia` y vista | `assertPatientAccess` antes de consultas; Nota/Receta por paciente+referencia |

Los endpoints de historial llaman a `guardPatient()` antes de invocar el modelo. El guard usa la sesión Doctor y `assertPatientAccess()` para comprobar la clínica del paciente. El parámetro `referencia=0` es una excepción explícita del contrato para solicitar el historial completo del paciente; no se aplica a la referencia inválida reportada en 04B.

## Semántica observada

La referencia se almacena en `codigo_referencia` en Nota subsecuente y Laboratorio. La pantalla principal usa la referencia para resolver la Nota y la receta asociada. Los historiales reciben la misma referencia y la pasan a sus consultas independientes.

La evidencia aportada por 04B indicó:

- Vista Nota: HTTP 200 para referencia válida e inválida, con contenido diferente y mensaje de ausencia en la inválida.
- Historial Nota: HTTP 200 en ambos casos, respuestas idénticas de 4332 caracteres.
- Historial Laboratorio: HTTP 200 en ambos casos, respuestas idénticas de 319 caracteres.

Estos resultados son evidencia runtime reportada por 04B, no una nueva ejecución desde esta sesión.

## Causa de las respuestas idénticas

### Nota history

La causa más consistente con el código es una respuesta HTML común para el mismo conjunto vacío: la consulta sí incluye `codigo_referencia = :referencia` cuando la referencia no es `0`, y la plantilla siempre emite la estructura de tabla aunque no existan filas. La presencia de filas para la referencia válida no fue comprobada mediante SELECT.

Estado: `NOT_CONFIRMED_WITH_DATABASE`.

### Laboratorio history

La misma explicación es técnicamente posible: la consulta filtra por paciente y referencia, pero la plantilla emite la tabla vacía con la misma estructura. No se puede distinguir entre “ambas referencias no tienen registros” y otro efecto de datos sin consultar recuentos.

Estado: `NOT_CONFIRMED_WITH_DATABASE`.

No se observó en el código una omisión del parámetro de referencia para valores distintos de `0`, ni una interpolación que lo neutralice.

## Consultas SELECT preparadas

Ejecutar sólo en PowerShell/MySQL nativo del propietario, después de confirmar que la conexión es local y la base es `bd_clinica`. No incluyen contenido clínico:

```sql
SET @paciente = 310;
SET @referencia_valida = '6ad2a4de1fd757.87676796';
SET @referencia_invalida = 'INVALID04B999999.999999';

SELECT 'NOTA_TOTAL' AS caso, COUNT(*) AS filas
FROM nota_subsecuente
WHERE id_paciente = @paciente;

SELECT 'NOTA_VALIDA' AS caso, COUNT(*) AS filas
FROM nota_subsecuente
WHERE id_paciente = @paciente
  AND codigo_referencia = @referencia_valida;

SELECT 'NOTA_INVALIDA' AS caso, COUNT(*) AS filas
FROM nota_subsecuente
WHERE id_paciente = @paciente
  AND codigo_referencia = @referencia_invalida;

SELECT 'LAB_TOTAL' AS caso, COUNT(*) AS filas
FROM laboratorio
WHERE id_paciente = @paciente;

SELECT 'LAB_VALIDA' AS caso, COUNT(*) AS filas
FROM laboratorio
WHERE id_paciente = @paciente
  AND codigo_referencia = @referencia_valida;

SELECT 'LAB_INVALIDA' AS caso, COUNT(*) AS filas
FROM laboratorio
WHERE id_paciente = @paciente
  AND codigo_referencia = @referencia_invalida;

SELECT 'NOTA_REFERENCIAS' AS caso, codigo_referencia, COUNT(*) AS filas
FROM nota_subsecuente
WHERE id_paciente = @paciente
GROUP BY codigo_referencia;

SELECT 'LAB_REFERENCIAS' AS caso, codigo_referencia, COUNT(*) AS filas
FROM laboratorio
WHERE id_paciente = @paciente
GROUP BY codigo_referencia;
```

La salida debe conservar sólo recuentos y referencias sintéticas; no exportar nombres, diagnósticos, medicamentos, descripciones ni documentos.

## Seguridad

- Autenticación sin sesión: 302 a `/clinica/acceso`, según evidencia 04B.
- Aislamiento por clínica: control estático presente mediante `assertPatientAccess`; validación cruzada runtime no ejecutada en esta sesión.
- Ámbito de paciente: PASS_STATIC; prueba HTTP cruzada pendiente.
- Ámbito de referencia: PASS_STATIC para referencias distintas de `0`; prueba de datos pendiente.
- Pertenencia de registro: PASS_STATIC en los endpoints de tabla por guard de paciente; detalle directo requiere validación runtime adicional.
- Consultas: preparadas en los modelos revisados.
- Datos inválidos: la validación HTTP de 04B no demuestra por sí sola el conteo de filas; queda pendiente el SELECT focalizado.

## Remediación

No se aplicó remediación de aplicación porque no se confirmó un defecto real de filtrado. Modificar el código para diferenciar respuestas HTML vacías sería incorrecto sin evidencia de filas autorizadas omitidas y podría alterar el contrato visual/funcional.

Se agregó únicamente el contrato estático focalizado:

`tests/doctor-subsequent-note/reference-scope-contract-04c.php`

## Validaciones

- PHP lint: PASS.
- Contrato 04C: PASS_STATIC.
- Contrato de seguridad 04: PASS_STATIC.
- Sintaxis JavaScript: NOT_APPLICABLE; no hubo cambios JS en 04C.
- `git diff --check`: PASS.
- SELECT en `bd_clinica`: NOT_EXECUTED desde Codex.
- Reproducción HTTP: NOT_EXECUTED desde Codex; se conserva evidencia reportada por 04B.
- Validación entre pacientes: NOT_VERIFIED.
- Validación entre clínicas: NOT_VERIFIED.

## Estado de modales

- Detalle de Nota: `PARTIAL_RUNTIME_PENDING`.
- Detalle de Laboratorio: `PARTIAL_RUNTIME_PENDING`.

La autorización estática y los filtros están preparados, pero no se declara seguridad runtime ni se implementan modales en esta fase.

## Informe final

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_REFERENCE_SCOPE_VALIDATION_04C:
  STATUS: PARTIAL_STATIC_NO_CONFIRMED_REFERENCE_FILTER_VULNERABILITY
  NOTE_VIEW_REFERENCE_FILTER: PASS_STATIC_RUNTIME_REPORTED_04B
  NOTE_HISTORY_REFERENCE_FILTER: PASS_STATIC_RUNTIME_DATA_PENDING
  LAB_HISTORY_REFERENCE_FILTER: PASS_STATIC_RUNTIME_DATA_PENDING
  NOTE_HISTORY_IDENTICAL_CAUSE: MOST_LIKELY_COMMON_EMPTY_HTML_NOT_CONFIRMED_WITH_DB
  LAB_HISTORY_IDENTICAL_CAUSE: MOST_LIKELY_COMMON_EMPTY_HTML_NOT_CONFIRMED_WITH_DB
  VALID_REFERENCE_HAS_NOTES: NOT_VERIFIED
  VALID_REFERENCE_HAS_LABS: NOT_VERIFIED
  INVALID_REFERENCE_HAS_RECORDS: NOT_VERIFIED
  PATIENT_SCOPE: PASS_STATIC_RUNTIME_CROSS_PATIENT_PENDING
  CLINIC_SCOPE: PASS_STATIC_RUNTIME_CROSS_CLINIC_PENDING
  REFERENCE_SCOPE: PASS_STATIC_RUNTIME_DATA_PENDING
  RECORD_OWNERSHIP: PASS_STATIC_RUNTIME_PENDING
  CONFIRMED_VULNERABILITY: NONE_IN_REFERENCE_FILTERS
  ROOT_CAUSE: NOT_CONFIRMED; DATABASE_COUNTS_REQUIRED
  REMEDIATION_APPLIED: NONE_TO_APPLICATION
  READ_ONLY_DATABASE_CHECK: NOT_EXECUTED
  HTTP_RUNTIME_EVIDENCE: REPORTED_BY_04B_NOT_REPRODUCED_HERE
  CROSS_PATIENT_VALIDATION: NOT_EXECUTED
  CROSS_CLINIC_VALIDATION: NOT_EXECUTED
  PHP_LINT: PASS_RELEVANT_PHP_FILES
  JS_SYNTAX: NOT_APPLICABLE_NO_JS_MODIFIED
  FOCUSED_TESTS: PASS_STATIC
  DIFF_CHECK: PASS
  FILES_MODIFIED: tests/doctor-subsequent-note/reference-scope-contract-04c.php plus this document
  DATABASE_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  NOTE_DETAIL_MODAL_READY: PARTIAL_RUNTIME_PENDING
  LABORATORY_DETAIL_MODAL_READY: PARTIAL_RUNTIME_PENDING
  BLOCKERS: Native_read_only_SELECT_and_two_authorized_clinic_contexts_unavailable
  NEXT_RECOMMENDED_ACTION: Run_SELECT_counts_then_repeat_reference_and_cross_scope_HTTP_tests
```
