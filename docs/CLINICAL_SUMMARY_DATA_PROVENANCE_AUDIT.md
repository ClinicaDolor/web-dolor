# Auditoría de origen de datos clínicos M01–M09

Fecha: 2026-10-11  
Alcance: capa de lectura del resumen clínico, detalle Doctor y narrativa determinista.  
Persistencia: no se realizaron escrituras, migraciones ni modificaciones de catálogos.

## Resultado ejecutivo

La auditoría confirmó que el ruido no provenía únicamente de la vista. Había tres problemas de procedencia en la capa de lectura:

1. El snapshot conservaba identificadores de catálogo (`id_pregunta`, `id_tema`, `id_enfermedad`, `id_tratamiento`) y los usaba indirectamente para clasificar filas inicializadas como documentadas.
2. En M02, `tipo` es el tipo de diabetes; `especificar` es el parentesco. La presentación los intercambiaba.
3. En M08, una fila de tratamiento se podía presentar como procedimiento aunque `utilizo` estuviera sin responder; el nombre del tratamiento proviene del catálogo y no es una afirmación del paciente.

Se corrigieron únicamente esos defectos y se añadió un estado interno por registro (`documented` / `unanswered`). Las filas no respondidas permanecen disponibles para la lectura técnica y no se convierten en afirmaciones, referencias válidas de Gemini ni frases narrativas.

## Matriz de procedencia

| Módulo | Fuente primaria | Respuesta clínica | Datos de catálogo o control | Regla de presentación |
|---|---|---|---|---|
| M01 | `pc_paciente` | motivo, sexo, edad y datos generales persistidos | `id` del paciente | Solo campos clínicos con valor; no se envían identificadores personales al proveedor externo |
| M02 | `pc_antecedentes_familiares` | `detalle`; `especificar` como parentesco; `tipo` solo como tipo de diabetes | enfermedad seleccionada y claves internas | Enfermedad sin `detalle` no es antecedente documentado; se conservan Sí/No explícitos |
| M03 | `pac_respuestas_paciente_modulo_3` | `respuesta` | joins a `pac_preguntas_modulo_3` y `pac_temas_modulo_3`; `id_pregunta` | Pregunta sin respuesta se omite de la narrativa; dependencias se interpretan solo cuando existe respuesta |
| M04 | `pc_antecedentes_quirurgicos` | fecha, cirugía y observaciones | `id` de fila | Fila inicializada sin campos clínicos queda incompleta y no genera afirmación |
| M05 | `pac_respuestas_paciente_modulo_5`, `pac_enfermedades_respuestas_modulo_5` | `respuesta`; año únicamente como dato complementario | preguntas, temas, enfermedades y claves internas | Enfermedad o pregunta sin respuesta no se presenta como antecedente |
| M06 | `pac_medicacion_actual_modulo_6` | medicamento y campos complementarios | `id` de fila | No se enumera medicación si el nombre está vacío |
| M07 | `pac_respuestas_paciente_modulo_7` | respuesta, dosis, resultados, descripción y consumo | pregunta, tema y `id_pregunta` | Filas inicializadas sin respuesta no generan tratamiento documentado |
| M08 | `pac_procedimientos_dolor_modulo_8`, `pac_respuestas_paciente_modulo_8` | procedimiento registrado o `utilizo`/comentarios del tratamiento | catálogo de tratamientos y `id_tratamiento` | Un tratamiento solo aparece si `utilizo` está respondido; se distingue de un procedimiento realizado |
| M09 | `pc_paciente_evaluacion_dolor` | dolor, evolución, características e incremento | `id` de fila | Cero numérico y respuestas explícitas se conservan; campos ausentes se marcan como incompletos |

## Cambios aplicados

- `ClinicalSnapshotService` elimina claves internas de catálogo y aplica una lista de campos clínicos por módulo para determinar si una fila es documentada. No usa `empty()` y conserva `0` como valor válido.
- El snapshot marca filas inicializadas sin respuesta como `unanswered`; el resumen determinista las excluye de `items` y el validador de Gemini no las acepta como referencias documentales.
- M02 presenta `tipo` como `Tipo de diabetes` y `especificar` como `Parentesco especificado`.
- M08 conserva metadatos internos de origen (`procedimiento_registrado` / `tratamiento_cuestionario`) únicamente para la lógica de lectura. Un tratamiento sin `Utilizó` no produce texto ni tarjeta visible.
- La narrativa omite respuestas NULL, vacías y “No aplica”, conserva negaciones explícitas, texto documentado y valores numéricos, incluido cero.
- La vista filtra filas de catálogo no respondidas, sin borrar registros persistidos.

## Estado por módulo

La salida de lectura ejecutada contra el entorno configurado se registró sin nombres, identificadores personales ni contenido clínico. La clasificación observada fue:

| Módulo | Estado observado | Observación de fidelidad |
|---|---|---|
| M01 | `documented` | Datos generales y motivo provienen de `pc_paciente` |
| M02 | `documented` | Respuestas familiares separadas del catálogo y parentesco corregido |
| M03 | `documented` | Solo una fila con respuesta clínica fue clasificada como documentada; las restantes son filas de cuestionario sin respuesta |
| M04 | `documented` | La fuente es el registro quirúrgico persistido |
| M05 | `documented` | Se separan respuestas del cuestionario y enfermedades con respuesta |
| M06 | `absent` | No se generó medicación por filas vacías |
| M07 | `documented` | Las preguntas de catálogo sin respuesta no generan tratamiento |
| M08 | `incomplete` | No había procedimiento o utilización de tratamiento documentados en la lectura auditada |
| M09 | `incomplete` | Existía fila de evaluación, pero sin campos clínicos válidos suficientes |

Estos estados describen la lectura realizada; no implican que se hayan creado o eliminado registros.

## Validación

- `php -l` de los cinco servicios modificados: PASS.
- `node --check public/assets/js/clinical-summary.js`: PASS.
- `tests/clinical-summary/coverage-contract.php`: PASS, 11 aserciones.
- `tests/clinical-summary/narrative-contract.php`: PASS, 22 aserciones.
- `git diff --check`: sin errores de espacios; solo advertencias de normalización de fin de línea en archivos preexistentes no relacionados.
- La lectura de snapshot no emitió fuentes no disponibles y no realizó escrituras.
- No se ejecutó Gemini ni se habilitó integración externa.

## Limitaciones y riesgos pendientes

- La capa de cuestionarios conserva las filas inicializadas para que los formularios sigan funcionando. La presentación las omite cuando no contienen respuesta; no se deben eliminar de la base.
- Las dependencias condicionales M03/M05/M07 solo pueden considerarse “no aplicables” cuando el catálogo y la respuesta explícita lo demuestran. Una fila sin respuesta no se interpreta automáticamente como “No aplica”.
- No se debe usar este documento como autorización para procesar expedientes reales con un proveedor externo. Gemini permanece sujeto a su configuración de seguridad y autorización independiente.
- No se hicieron pruebas visuales autenticadas nuevas en esta auditoría; la corrección se limitó a origen, clasificación y narrativa.

## Archivos modificados en esta auditoría

- `app/Services/ClinicalSnapshotService.php`
- `app/Services/ClinicalDetailService.php`
- `app/Services/ClinicalNarrativeService.php`
- `app/Services/ClinicalSummaryService.php`
- `app/Services/DeterministicClinicalSummaryService.php`
- `public/assets/js/clinical-summary.js`
- `tests/clinical-summary/narrative-contract.php`
- `docs/CLINICAL_SUMMARY_DATA_PROVENANCE_AUDIT.md`

No se realizaron commits, push, despliegues, cambios en `.env`, cambios en `bd_clinica` ni modificaciones de formularios.

`M01–M09_DATA_PROVENANCE: PASS`

`SNAPSHOT_NARRATIVE_FIDELITY: PASS`

`UNSUPPORTED_CLAIMS_IN_VALIDATED_CONTRACTS: 0`
