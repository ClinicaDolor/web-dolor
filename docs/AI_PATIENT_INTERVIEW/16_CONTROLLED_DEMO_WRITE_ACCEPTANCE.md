# Fase 4.4 — Aceptación de escritura demo controlada

**Fecha:** 2026-10-12  
**Entorno:** `http://clinica.test` / `bd_clinica` local  
**Alcance autorizado:** paciente técnico `310`, clínica `1`, módulo M03, pregunta `5` únicamente.

## Estado de esta fase

La preparación y el respaldo focal fueron completados. La captura de la respuesta 5 **no se ejecutó automáticamente**: debe realizarla manualmente el propietario desde la interfaz autenticada. Por ello este documento registra una aceptación parcial y no una aprobación de la escritura.

## Confirmaciones previas

- La aplicación resolvió la base efectiva como `bd_clinica`.
- El paciente 310 pertenece a la clínica 1.
- La pregunta 5 existe en M03 y su tipo de respuesta del catálogo es `input`.
- El conjunto M03 del paciente contiene 25 filas; las preguntas 1–4 no fueron tocadas.
- La pregunta 5 estaba vacía antes de la prueba.
- Se observaron 3 respuestas dependientes no vacías en el conjunto M03; no se autoriza alterarlas.
- La creación del respaldo no ejecutó escrituras persistentes (`persistent_db_write=false`).
- En la comprobación de concurrencia no se observó otra conexión MySQL no inactiva; esta comprobación sólo es válida para ese instante y debe repetirse inmediatamente antes de capturar.
- Gemini permaneció deshabilitado. No se enviaron datos a proveedores externos.

## Respaldo focal

El respaldo se creó fuera del repositorio, en la carpeta temporal local:

`C:\Users\silvi\AppData\Local\Temp\clinica-demo-m03-310-20261012-140310.sql`

Verificación del archivo:

- Tamaño: 2898 bytes.
- Sentencias `INSERT`: 25.
- SHA-256: `4621bc1da84f3d8eaaac54e7cd26d629054914b2b8e5d157038fd02212de6b3b`.
- Alcance: filas seleccionadas exclusivamente para el paciente 310 mediante la consulta focal previa; no se imprimieron valores clínicos.
- El archivo no se incorporó al repositorio ni se envió a ningún servicio externo.

El respaldo es recuperable, pero **no debe ejecutarse todavía**. Su restauración deberá hacerse únicamente con autorización posterior y con una transacción, restringida al paciente 310 y al estado inicial de M03.

## Procedimiento manual autorizado

1. Cerrar otras pestañas o sesiones que puedan editar el paciente 310 y confirmar que no haya otra persona trabajando en ese expediente.
2. Usar la sesión demo ya autenticada y abrir `http://clinica.test/historia-clinica/entrevista`.
3. En M03, modificar exclusivamente la pregunta 5 con el valor sintético elegido por el propietario.
4. No tocar las preguntas 1–4 ni ninguna respuesta dependiente.
5. Confirmar manualmente el diálogo nativo de guardado y esperar la respuesta completa de la interfaz.
6. No ejecutar la restauración automáticamente. Informar después de que la captura haya terminado para realizar las comprobaciones de solo lectura.

No se debe escribir el valor elegido en este documento, en logs, capturas ni en el chat.

## Verificación posterior pendiente

Después de la confirmación manual se comparará, mediante consultas de solo lectura, el estado actual contra el respaldo:

- Sólo la fila de la pregunta 5 puede diferir en su valor o metadatos de captura permitidos.
- Las preguntas 1–4 deben conservar identificadores y valores.
- Las respuestas dependientes deben conservar identificadores y valores.
- No deben aparecer filas de otros pacientes ni escrituras ajenas.
- La interfaz tradicional M03 deberá recuperar el dato. Se documentará por separado cualquier inicialización de filas vacías causada por su comportamiento heredado, sin atribuirla a la captura si no corresponde.

La restauración, si se solicita, será focal, transaccional y posterior a la evidencia. No se ejecutó en esta fase.

## Resultado

```yaml
DEMO_BACKUP: PASS
QUESTION_5_CAPTURE: NOT_VERIFIED
SHARED_PERSISTENCE: NOT_VERIFIED
LEGACY_M03_COMPATIBILITY: NOT_VERIFIED
EXISTING_ANSWERS_PRESERVED: NOT_VERIFIED
UNEXPECTED_WRITES: NO
RESTORATION_STATUS: NOT_EXECUTED
DEMO_ACCEPTANCE: PARTIAL
```

`UNEXPECTED_WRITES: NO` se refiere únicamente a la fase de preparación y respaldo; la verificación posterior a la captura está pendiente.

## Addendum — verificación posterior a la captura

**Fecha:** 2026-10-12  
**Fuente adicional:** consultas de solo lectura contra `bd_clinica` y comparación con el respaldo SHA-256 anterior. Las capturas aportadas se consideran evidencia visual del flujo, no evidencia suficiente de integridad de base.

Resultados:

- La fila de M03/P5 persistió: conserva su identificador de fila y su huella cambió desde el valor vacío del respaldo.
- El conjunto actual conserva 25 filas y no presenta duplicados por `id_pregunta` para el paciente 310.
- La base efectiva volvió a confirmarse como `bd_clinica`.
- Las preguntas 1 y 4 coinciden con el respaldo.
- Las preguntas 2 y 3 **no coinciden** con el respaldo: sus huellas y/o valores persistidos difieren. Como estaban fuera del alcance autorizado, se clasifica como escritura inesperada hasta aclarar su origen.
- Por la misma razón, no puede declararse que todas las respuestas existentes hayan permanecido intactas.
- Las respuestas dependientes incluidas en la comparación no quedan aprobadas como intactas mientras persista la discrepancia de M03/P2–P3.
- El código heredado y el código de entrevista consultan la misma tabla `pac_respuestas_paciente_modulo_3`; no se abrió el formulario tradicional en navegador porque su GET puede inicializar filas vacías y la autorización exigía sólo consultas de lectura. La compatibilidad de ejecución queda pendiente.
- No se ejecutaron restauraciones, limpiezas ni nuevas escrituras.

La prueba se detiene aquí. No se atribuye automáticamente la discrepancia a una persona o a una ruta concreta; debe revisarse la secuencia de captura y cualquier sesión concurrente antes de una nueva acción. No se imprimieron valores clínicos, únicamente identificadores técnicos, conteos y huellas.

```yaml
DEMO_BACKUP: PASS
QUESTION_5_CAPTURE: PASS
SHARED_PERSISTENCE: PASS
LEGACY_M03_COMPATIBILITY: NOT_VERIFIED
EXISTING_ANSWERS_PRESERVED: FAIL
UNEXPECTED_WRITES: YES
RESTORATION_STATUS: NOT_EXECUTED
DEMO_ACCEPTANCE: FAIL
```

La restauración del estado inicial de P5 sigue sin ejecutarse y requiere confirmación expresa. Antes de restaurar o repetir la prueba debe investigarse la discrepancia de P2–P3 y conservarse este respaldo como evidencia.

## Restricciones respetadas

No se modificaron código, formularios, rutas, controladores, modelos, JavaScript, esquema, `.env`, M01–M09 ni P05–P11. No se activó Gemini. No se realizaron commits, push ni despliegues.
