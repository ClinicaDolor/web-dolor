# M03 — Addendum de persistencia textual UNKNOWN/NOT_REMEMBERED v1.1

Estado: preparado para validación sintética; no ejecuta migraciones ni habilita M07.

## Propósito

Este addendum complementa `PATIENT_INTERVIEW_M03_UNKNOWN_CONTRACT_V1.md` para
instalaciones que todavía sólo disponen de la columna canónica `respuesta`.
No reemplaza el contrato v1 ni declara equivalente la extensión semántica
aditiva.

La ruta textual se permite únicamente cuando el servidor confirma que la
respuesta pertenece al paciente, la pregunta pertenece al tema oficial, la
sesión tiene autorización, el CSRF es válido, la versión coincide y la captura
se realiza mediante el servicio M03 existente.

## Valores canónicos

| Estado | Valor persistido en `respuesta` | Lectura canónica |
| --- | --- | --- |
| `UNKNOWN` | `No lo sé` | `UNKNOWN` |
| `NOT_REMEMBERED` | `No recuerdo` | `NOT_REMEMBERED` |

Las variantes reconocidas durante la captura se normalizan a esos valores.
Sólo esos valores exactos se vuelven a clasificar durante la lectura. El texto
histórico arbitrario no se convierte en `KNOWN`, `UNKNOWN` ni `NEGATIVE`.

`No` continúa siendo una respuesta negativa únicamente para preguntas `select`
compatibles. En grupo sanguíneo y factor Rh, `No` sigue requiriendo aclaración;
nunca se interpreta como Rh negativo.

## Persistencia, lectura y avance

- La respuesta se guarda sólo después de la confirmación normal del servidor.
- No se crean columnas, tablas ni filas adicionales.
- La misma fila `id_paciente + id_pregunta` se actualiza; no se crean duplicados.
- El readback proyecta los textos canónicos como estados semánticos.
- Una respuesta textual canónica cuenta como contestada y permite reanudar y
  avanzar a la siguiente pregunta elegible.
- Una respuesta vacía continúa siendo `UNANSWERED`.
- Las respuestas `KNOWN` y `NEGATIVE`, las dependencias, el versionado, CSRF,
  autorización y protección contra doble envío conservan su comportamiento.

## Limitaciones frente al contrato v1

La representación textual no conserva en columnas independientes el origen ni
la fecha de clasificación. La procedencia sólo queda respaldada por la ruta
de captura autorizada y el valor canónico almacenado; por ello esta alternativa
no es equivalente a `estado_respuesta + origen_respuesta + estado_respuesta_at`.

No se reclasifican respuestas históricas ni se ejecuta backfill. La migración
aditiva sigue siendo la solución recomendada cuando se requiera trazabilidad
durable completa. Una extensión semántica parcial o una columna `respuesta` que
no cumpla el contrato debe bloquear la operación, no resolverse por inferencia.

## Alcance excluido

M01 permanece de solo lectura. M07 y su finalización permanecen cerrados. No
se modifica `bd_clinica`, el paciente 310, `.env`, producción ni el esquema
mediante este addendum.

La aceptación MySQL/HTTP requiere una ejecución posterior desde el entorno
Medium autorizado y no se considera demostrada por las pruebas SQLite locales.
