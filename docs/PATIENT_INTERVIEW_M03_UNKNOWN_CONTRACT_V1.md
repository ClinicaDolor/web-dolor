# M03 — Contrato durable para UNKNOWN y NOT_REMEMBERED v1.0

Estado: preparado para revisión y migración sintética autorizada.

## Alcance

Este contrato aplica a la tabla canónica existente `pac_respuestas_paciente_modulo_3` y distingue el significado de una respuesta sin crear una segunda fuente de verdad.

| Estado | Significado | `respuesta` |
| --- | --- | --- |
| `KNOWN` | El paciente declara un valor clínico concreto y válido. | Valor declarado |
| `NEGATIVE` | El paciente declara una negativa válida para una pregunta compatible. | `No` |
| `UNKNOWN` | El paciente declara que desconoce el dato. | Cadena vacía |
| `NOT_REMEMBERED` | El paciente declara que no recuerda el dato. | Cadena vacía |
| `UNANSWERED` | No existe una respuesta confirmada. | Sin fila, o fila histórica sin estado y sin valor |
| `NOT_APPLICABLE` | La pregunta no es elegible por sus dependencias oficiales. | No se persiste como respuesta |

La semántica de `UNKNOWN` y `NOT_REMEMBERED` se almacena únicamente cuando la extensión está presente y el servidor valida la declaración. La ausencia de la extensión produce un rechazo cerrado; nunca simula persistencia.

## Extensión aditiva preparada

La migración propuesta añade a `pac_respuestas_paciente_modulo_3`:

- `estado_respuesta VARCHAR(24) NULL`.
- `origen_respuesta VARCHAR(32) NULL`.
- `estado_respuesta_at DATETIME NULL`.

La combinación de estado, origen y fecha conserva la procedencia de la declaración. No se clasifica automáticamente ninguna respuesta histórica. Los registros antiguos con valor y sin estado se proyectan como `LEGACY_UNCLASSIFIED`, sin convertirlos en `KNOWN` o `NEGATIVE`.

## Reglas clínicas y de seguridad

- El servidor valida paciente, pregunta, tema, estado, valor, CSRF, autorización, versión e idempotencia.
- `UNKNOWN` y `NOT_REMEMBERED` no aceptan un valor clínico inventado.
- `NEGATIVE` sólo se acepta para una pregunta `select` con valor `No` y conserva las comprobaciones de dependencias.
- Grupo sanguíneo y factor Rh son preguntas independientes; `No` en Rh no significa Rh negativo.
- La edición de un estado desconocido requiere una captura explícita posterior; no se sobrescribe silenciosamente.
- El readback debe devolver el valor y el estado semántico guardados.
- La elegibilidad de preguntas dependientes se calcula por el contrato oficial; `NOT_APPLICABLE` no se crea como fila.
- La compuerta canónica M07 y su finalización permanecen cerradas.

## Compatibilidad y procedimiento posterior

Antes de aplicar la migración, el propietario debe ejecutar el modo `preflight` del archivo `tests/patient-interview/m03-unknown-state-migration.php` desde PowerShell Medium contra `clinica_dolor_demo_20261010_final`. Después de una autorización separada, el modo `apply` debe crear un snapshot completo de la tabla antes del DDL, comprobar `SELECT DATABASE()`, aplicar sólo el DDL aditivo y ejecutar una validación de readback sintética. No se permite backfill automático.

El modo `restore` requiere la misma allowlist, un snapshot durable íntegro y autorización explícita. Una diferencia, snapshot inválido o extensión parcial debe bloquear el procedimiento, no corregirse por inferencia.

## Estado de verificación

- Contrato y código: preparados.
- Pruebas unitarias/dobles: ejecutadas sin base externa.
- Migración: creada, no ejecutada.
- MySQL sintético, HTTP y Chrome: pendientes de una fase autorizada.
