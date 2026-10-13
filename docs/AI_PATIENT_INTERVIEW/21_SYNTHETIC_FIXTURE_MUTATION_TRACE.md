# Fase 4.9 — Trazabilidad de mutación de fixture sintética

Fecha de revisión: 2026-10-12  
Alcance: lectura exclusiva de `clinica_dolor_demo_20261010_final`, paciente sintético 9301, M03/P1–P5.

## Alcance y límites

Esta revisión no ejecutó `INSERT`, `UPDATE`, `DELETE`, restauraciones, limpieza, reinitialización de fixture, peticiones HTTP de escritura ni servidores nuevos. No se modificaron `bd_clinica`, el expediente demo 310, M01–M09 ni Gemini.

El snapshot previo fue conservado sin cambios:

- Archivo: `C:\Users\silvi\AppData\Local\Temp\clinica-browser-m03-9301-20261012.json`
- SHA-256: `90e669c1141683321b1c4547b85d7cec835d9641c91e3bd347efb2980e96b6ae`.
- El snapshot contiene base, pacientes/ clínicas, alcance y `baseline_rows`; el baseline tenía cero filas antes de la preparación.

El snapshot no capturó un segundo estado posterior a la preparación. Por ello se puede demostrar el estado actual y compararlo con el escenario preparado documentado en `prepare-browser-synthetic.php`, pero no reconstruir con certeza el instante ni el actor que produjo la mutación.

## Evidencia actual

La conexión de solo lectura devolvió `DATABASE() = clinica_dolor_demo_20261010_final`. Los pacientes sintéticos permanecen separados: 9301 pertenece a la clínica 9101 y 9302 a la clínica 9102.

Para 9301 existen exactamente cinco filas M03, una por pregunta 1–5, con identificadores 297–301 y sin duplicados. Se registraron solamente longitudes y hashes de respuesta, no los valores clínicos. P1 difiere del valor preparado; P2–P4 tienen longitud cero; P5 permanece con longitud cero. El paciente 9302 tiene cero filas M03. No hay triggers sobre la tabla.

Esto confirma una diferencia de estado, no una traza histórica de quién la escribió.

## Posibles escritores

| Componente | Puede escribir | Puede modificar P1 | Puede vaciar P2–P4 | Evidencia / alcance |
|---|---:|---:|---:|---|
| `tests/patient-interview/prepare-browser-synthetic.php` | Sí | Sí, durante preparación | Sí, por inserción inicial | Preparó las cinco filas y dejó el escenario para navegador; no explica por sí solo el cambio posterior. |
| `tests/patient-interview/chat-http-isolation.php` | Sí | Sí, en su preparación/restauración | Sí, en su preparación/restauración | La prueba usa transiciones HTTP y restaura en `finally`; no se ejecutó en esta trazabilidad. |
| `tests/p05/http-crud.php` | Sí | Sí, mediante POST M03 | Sí, si activa la regla selectiva | Se identificó capacidad; el intento histórico quedó sin autorización de vista y no demuestra esta escritura. |
| `tests/p05/cleanup-synthetic.php` | Sí | Elimina filas M03 | Elimina filas M03 | No se ejecutó; además habría producido ausencia de filas, no el patrón actual de cinco filas. |
| `AntecedentesNoPatologicosModel::capturarRespuestaEntrevista` | Sí, por endpoint | Sí | Puede activar limpieza condicional | Requiere una captura autorizada; el GET no ejecuta esta ruta de escritura. |
| `AntecedentesNoPatologicosModel::editarCuestionarioSelect` | Sí | Sí | Sí | Actualiza la respuesta selectiva y vacía las demás respuestas del tema en una transacción. |
| `tests/p11/http-crud.php` | Sí, pero M09 | No en M03 | No en M03 | Sus escrituras corresponden a evaluación del dolor M09 y archivos asociados. |
| GET de entrevista / navegación | No, salvo inicialización legacy fuera del endpoint nuevo | No demostrado | No demostrado | El flujo guiado nuevo no escribe al cargar; el modelo contiene un inicializador legacy que solo crea fila faltante cuando se invoca desde edición tradicional. |

## Regla condicional

La ruta de captura valida primero la respuesta actual. Si la pregunta es `select` y el nuevo valor es `No`, comprueba dependencias no vacías y, si procede, `editarCuestionarioSelect` actualiza P1 y vacía las demás filas del tema M03 dentro de una transacción. Esa regla es compatible con el patrón P1 cambiado + P2–P4 vacías.

No existe evidencia de que esa regla haya sido ejecutada durante el intervalo investigado. Tampoco existe log SQL o auditoría de fila que permita atribuirla al servidor temporal PID 58184, al servidor válido PID 42880, a un script o a una sesión de navegador. La presencia previa del listener obsoleto demuestra un defecto de aislamiento de servidores, pero no causalidad de esta mutación.

## Cronología reconstruible

1. A las 08:45 se creó el snapshot con baseline vacío y se prepararon cinco filas para el escenario sintético.
2. Se detectaron dos listeners temporales en el puerto 8088. El PID 58184 correspondía al listener obsoleto con prepend diagnóstico eliminado; el PID 42880 correspondía a la instancia válida.
3. Las comprobaciones de login y GET de entrevista funcionaron contra la instancia válida.
4. Antes de una aceptación manual confirmada, una consulta de solo lectura encontró P1 cambiado y P2–P4 vacías.
5. Ambos procesos temporales fueron detenidos. No se realizó limpieza ni restauración posterior.

La cronología no contiene una operación de escritura observada que cierre la atribución.

## Resultado obligatorio

```yaml
ORIGINAL_SNAPSHOT_INTEGRITY: PASS
CURRENT_FIXTURE_INTEGRITY: FAIL — difiere del escenario preparado; estructura sin duplicados

P1_CHANGE_CONFIRMED: YES — diferencia de estado confirmada por hash/longitud
P2_P4_CLEARING_CONFIRMED: YES — estado vacío confirmado frente al escenario preparado; operación histórica no observada
CONDITIONAL_CLEARING_RULE: PRESENT_AND_CODE_SUPPORTED
CONDITIONAL_RULE_EXECUTED_BY: NOT_VERIFIED

POTENTIAL_WRITERS_IDENTIFIED: YES
CONFIRMED_WRITER: NOT_VERIFIED
CONCURRENT_TEST_INTERFERENCE: NOT_VERIFIED
STALE_SERVER_CAUSALITY: NOT_VERIFIED

ROOT_CAUSE: NOT_VERIFIED — el patrón es compatible con la regla selectiva, pero falta evidencia del evento y del actor
ROOT_CAUSE_CONFIDENCE: LOW

PRODUCT_DEFECT_DEMONSTRATED: NO
HARNESS_DEFECT_DEMONSTRATED: YES — coexistencia de listener temporal obsoleto y listener válido
TEST_ISOLATION_GAP: YES — no hubo bloqueo exclusivo ni journal de escrituras para la fixture compartida

MINIMAL_REMEDIATION: No reutilizar la fixture mutada; autorizar una nueva preparación aislada después de cerrar todos los listeners y registrar un snapshot previo y posterior por fila sin valores clínicos
REQUIRES_PRODUCT_CHANGE: NO DEMONSTRATED
REQUIRES_HARNESS_CHANGE: YES — barrera de proceso/puerto, exclusión de sesión concurrente y trazabilidad de escrituras

READY_TO_REPREPARE_SYNTHETIC_FIXTURE: NO — requiere autorización explícita y decisión sobre el estado actual
READY_FOR_BROWSER_ACCEPTANCE: NO

BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
LEGACY_M03_MODIFIED: NO
RESTORATION_EXECUTED: NO
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

## Siguiente paso bloqueado

La fixture no debe reinitializarse, limpiarse ni restaurarse automáticamente. Para continuar hace falta autorización del propietario para escoger una de estas acciones: conservar el estado forense actual o preparar una nueva fixture sintética aislada. La repetición de la aceptación de navegador solo debe comenzar después de esa decisión y con una única instancia de servidor verificable.
