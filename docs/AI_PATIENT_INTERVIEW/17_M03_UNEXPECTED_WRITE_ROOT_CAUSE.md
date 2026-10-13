# Fase 4.5 — Investigación forense de escrituras no autorizadas

**Fecha:** 2026-10-12  
**Modo:** sólo lectura; sin correcciones ni restauración.  
**Entorno:** `clinica.test` / `bd_clinica` local / paciente 310 / clínica 1.

## Cronología y alcance

La prueba autorizaba únicamente la pregunta 5 de M03. El respaldo previo contiene 25 filas y fue conservado sin modificaciones. Después de la captura visual se confirmó que P5 persistió, pero la comparación también detectó diferencias en P2 y P3.

No se ejecutaron rutas HTTP durante esta auditoría, no se hicieron escrituras, no se restauró el expediente y no se activó Gemini.

## Evidencia preservada

- Respaldo original: `C:\Users\silvi\AppData\Local\Temp\clinica-demo-m03-310-20261012-140310.sql`.
- Tamaño: 2898 bytes.
- Filas/INSERT: 25.
- SHA-256 verificado nuevamente: `4621bc1da84f3d8eaaac54e7cd26d629054914b2b8e5d157038fd02212de6b3b`.
- Estado Git registrado antes de la auditoría; ya existían cambios locales y trabajo paralelo. No se modificaron ni se limpiaron.
- Hora de captura reportada por el propietario: aproximadamente 08:08.
- No se imprimieron respuestas clínicas ni datos personales.

## Comparación focal contra el respaldo

La tabla sólo tiene las columnas `id`, `id_pregunta`, `id_paciente` y `respuesta`; no existen columnas de estado, timestamps ni metadatos adicionales en esta tabla.

| Campo | P2 | P3 |
|---|---|---|
| `id` | UNCHANGED | UNCHANGED |
| `id_pregunta` | UNCHANGED | UNCHANGED |
| `id_paciente` | UNCHANGED | UNCHANGED |
| `respuesta` | VALUE_CHANGED | VALUE_CHANGED |
| representación técnica | No se identificó como causa; comparación directa de serialización SQL | No se identificó como causa; comparación directa de serialización SQL |
| metadatos/estado | NOT_APPLICABLE | NOT_APPLICABLE |

La comparación directa de la representación SQL de `respuesta`, además de las huellas, confirma que P2 y P3 no son únicamente una diferencia de hash. Los identificadores de fila y de paciente permanecen iguales. No se incluyen los valores originales ni los actuales.

Resultado global:

- Respaldo: 25 filas.
- Estado actual: 25 filas.
- Filas faltantes: ninguna.
- Filas adicionales: ninguna.
- Duplicados por `id_pregunta`: ninguno.
- Preguntas modificadas frente al respaldo: P2, P3 y P5.
- P1 y P4: sin cambio.
- P5: cambio compatible con la captura visual autorizada.

Por tanto, existe evidencia de alteración de respuestas clínicas en P2 y P3, no sólo de metadatos. La evidencia disponible no permite demostrar quién o qué sesión produjo temporalmente cada cambio.

## Flujo de guardado auditado

La inspección estática muestra el siguiente comportamiento:

1. La vista `app/views/paciente/entrevista.php` mantiene una pregunta activa.
2. Al confirmar cualquier pregunta, `confirmAndSave()` envía un `POST` individual a `/historia-clinica/entrevista/capturar`.
3. El payload contiene el `idPregunta` de la pregunta activa, su `detalle` y `expectedRespuesta`; no envía un arreglo masivo de respuestas.
4. Tras una respuesta exitosa, el frontend avanza automáticamente a la siguiente pregunta.
5. El controlador autentica la sesión, verifica acceso al paciente y valida CSRF.
6. `capturarRespuestaEntrevista()` valida que la pregunta pertenezca al tema 1, comprueba la respuesta esperada y recupera una sola fila por paciente/pregunta.
7. Para una pregunta `input`, `editarCuestionarioDato()` ejecuta un `UPDATE` restringido por `id`, paciente y tema.
8. La operación de limpiar dependencias sólo está asociada al flujo `select` con detalle `No`; P5 es `input`.

La implementación, por tanto, no tiene un payload especial “sólo P5”: la pantalla es secuencial y permite guardar P1–P5 conforme se confirman. El código no demuestra que la captura de P5 haya enviado P2/P3 en la misma petición. Las capturas sí muestran una navegación que pasó por preguntas anteriores, pero no son un registro HTTP completo.

## Causas evaluadas

- **Guardado masivo de P5:** no demostrado; el payload está limitado a una pregunta por petición.
- **Actualización masiva desde el flujo `input`:** no demostrada; el `UPDATE` usa el identificador de una sola respuesta.
- **Limpieza de dependencias por P5:** descartada por tipo de P5 (`input`); la rama de limpieza sólo se activa para `select` con `No`.
- **Triggers MySQL:** descartados; no hay triggers para la tabla.
- **Columnas de metadatos o timestamps:** descartadas; la tabla sólo tiene cuatro columnas.
- **Duplicados o inserciones adicionales:** descartados en el estado actual; siguen existiendo 25 filas y no hay duplicados por pregunta.
- **Logs HTTP/SQL suficientes para reconstruir la secuencia:** no disponibles. `general_log` está apagado y no hay logs locales de aplicación identificables para esa captura.
- **Sesión concurrente:** no puede reconstruirse retrospectivamente. La consulta actual sólo observó conexiones activas en el momento de la auditoría, no en las 08:08.

## Causa raíz y alcance demostrado

El mecanismo que permite modificar P2/P3 está demostrado: la entrevista independiente guarda cada pregunta confirmada y avanza automáticamente; no está restringida técnicamente a P5. Esto explica cómo P2/P3 podrían cambiar durante una navegación secuencial.

La atribución temporal de los cambios concretos a la captura de las 08:08 no está demostrada porque no existen logs de solicitudes ni auditoría histórica de UPDATE:

`ROOT_CAUSE: NOT_VERIFIED`

Conclusión técnica: existe un mecanismo de riesgo verificable y una alteración clínica verificable en P2/P3, pero no debe afirmarse que una petición concreta de P5 fue la que las modificó.

## Integridad actual

```yaml
BACKUP_INTEGRITY: PASS
ORIGINAL_ROW_COUNT: 25
CURRENT_ROW_COUNT: 25
P1_VALUE_PRESERVED: PASS
P2_VALUE_PRESERVED: FAIL
P3_VALUE_PRESERVED: FAIL
P4_VALUE_PRESERVED: PASS
P5_CAPTURE_VALID: PASS
P2_CHANGE_CLASSIFICATION: VALUE_CHANGED
P3_CHANGE_CLASSIFICATION: VALUE_CHANGED
OTHER_ROWS_CHANGED: NO — sólo P2, P3 y P5 difieren frente al respaldo
P5_REQUEST_PAYLOAD_SCOPE: UNA_PREGUNTA_POR_PETICIÓN, SEGÚN CÓDIGO
P5_SQL_WRITE_SCOPE: UNA_FILA_POR_ID, PACIENTE Y TEMA, SEGÚN CÓDIGO
ADDITIONAL_REQUESTS_DETECTED: NOT_VERIFIED — no hay logs históricos
ROOT_CAUSE: NOT_VERIFIED
CLINICAL_DATA_ALTERED: YES — P2 y P3 difieren del respaldo
ROOT_CAUSE_FIX_REQUIRED: YES, antes de nuevas escrituras
RESTORATION_EXECUTED: NO
PRODUCT_CODE_MODIFIED: NO
BD_CLINICA_MODIFIED_BY_AUDIT: NO
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

## Remediación mínima propuesta — no aplicada

Antes de otra escritura debe elegirse y autorizarse una corrección focal que impida que una prueba de P5 pueda guardar preguntas anteriores. La alternativa mínima a evaluar es separar la navegación de revisión de la captura autorizada o introducir una allowlist de preguntas de prueba en el entorno demo, sin modificar el formulario tradicional ni el modelo clínico compartido sin autorización expresa.

No se propone cambiar silenciosamente el modelo compartido. Si la corrección debe tocar `AntecedentesNoPatologicosModel`, deberá tratarse como dependencia clínica de riesgo y revisarse con una prueba de regresión independiente.

## Regresión sintética propuesta

En una base sintética aislada, con un paciente de prueba:

1. Respaldar las filas M03.
2. Confirmar una captura de P5.
3. Comparar por fila e identificador que sólo P5 cambió.
4. Repetir con P1–P4 prellenadas y verificar que ninguna se actualiza por avanzar.
5. Verificar que una respuesta `select=No` con dependencias sea rechazada sin limpiar datos.
6. Verificar CSRF, paciente, clínica y ausencia de escrituras fuera del paciente.
7. Ejecutar la prueba con logs temporales de solicitudes sin registrar valores clínicos.

No se ejecutó esta regresión.

## Restauración focal

La restauración del estado inicial no se ejecutó. Debe utilizar el respaldo conservado, restringirse al paciente 310 y ejecutarse sólo después de confirmar la causa y recibir autorización expresa. No se recomienda restaurar únicamente P5 mientras P2/P3 sigan sin explicar, porque podría dejar el expediente en un estado parcialmente corregido.

## Riesgos remanentes

- P2 y P3 permanecen alteradas respecto al respaldo.
- No debe repetirse ninguna captura en `bd_clinica`.
- No se debe abrir el formulario tradicional mientras la operación siga bloqueada, porque su GET puede inicializar filas.
- La ausencia de logs impide atribución histórica completa.
- No se autoriza todavía la funcionalidad para pacientes reales ni la restauración.
