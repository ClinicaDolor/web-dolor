# M04 — Contrato de declaración negativa persistente

## Alcance

M04 distingue una ausencia de filas quirúrgicas de una declaración explícita
del paciente. La tabla histórica `pc_antecedentes_quirurgicos` sigue siendo la
fuente de procedimientos positivos; no se inserta una cirugía ficticia para
representar «No».

## Estados

| Estado | Significado | ¿Completa la pregunta? |
| --- | --- | --- |
| `PENDING` | No existe una respuesta persistida ni una declaración de ausencia. | No |
| `NEGATIVE_DECLARED` | El paciente declaró explícitamente que no le han realizado cirugías. | Sí |
| `DOCUMENTED_SURGERY` | Existe al menos un procedimiento con nombre no vacío en la tabla histórica. | Sí, sujeto a los detalles exigidos |
| `SUPERSEDED_BY_SURGERY` | Una declaración negativa previa fue rectificada mediante una cirugía positiva capturada por el flujo autorizado. | Se lee la cirugía; se conserva la trazabilidad |

`No sé`, `No recuerdo`, una fecha desconocida y una respuesta vacía no son
negativas. La falta de filas tampoco se convierte automáticamente en `No`.

## Persistencia y seguridad

La extensión aditiva preparada en
`database/migrations/20261018_m04_negative_declaration.sql` crea
`pc_antecedentes_quirurgicos_estado`, con alcance por paciente y clínica,
origen, versión y fechas. Debe verificarse la base efectiva antes de aplicarla;
la migración no se ha ejecutado.

El adaptador:

- exige la política de escritura existente, sesión/CSRF del controlador y
  versión esperada;
- comprueba la clínica actual mediante `pc_paciente`;
- guarda la declaración y hace readback antes de permitir el avance;
- devuelve un bloqueo explícito si la tabla aún no existe;
- rechaza una negación que contradiga procedimientos registrados;
- conserva una declaración negativa previa como `SUPERSEDED_BY_SURGERY`
  cuando una captura positiva autorizada registra los detalles de una cirugía;
- mantiene la operación idempotente y no crea filas en
  `pc_antecedentes_quirurgicos` para una respuesta negativa.

## Reanudación y edición

Una lectura con `NEGATIVE_DECLARED` devuelve `No` como respuesta persistida,
con procedencia `PATIENT_DECLARATION` y estado canónico, por lo que la pregunta
no reaparece como pendiente. La tarjeta conserva la posibilidad de corregir
la respuesta mediante el flujo de servidor; una corrección positiva requiere
los detalles de la cirugía y actualiza el estado histórico sin borrar la
declaración previa.

## Validación

La regresión `tests/patient-interview/m04-negative-contract.php` usa SQLite en
memoria para comprobar interpretación, persistencia, readback, idempotencia,
conflicto con cirugía existente y comportamiento antes de la migración. No es
una validación funcional MySQL ni sustituye una prueba sintética autorizada.
