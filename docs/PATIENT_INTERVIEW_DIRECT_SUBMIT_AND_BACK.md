# Entrevista Conversacional: envío directo y pregunta anterior

Fecha: 2026-10-13

## Flujo

Antes, toda respuesta M03 que no fuera un número pasaba por interpretación,
mostraba una propuesta y exigía una segunda confirmación aunque el valor fuera
literalmente aceptable.

Ahora, una respuesta M03 de tipo `input` se envía directamente solo cuando:

- la pregunta está identificada y es elegible;
- el módulo sigue editable;
- la respuesta es un número o una duración literal completa como «hace dos años»;
- no se requiere transformación clínica, selección ni aclaración.

Una duración solo se considera directa si coincide completa con el patrón
autorizado (`hace|desde hace|durante` + cantidad + `años|meses|semanas|días`,
con punto opcional). Frases con texto adicional, por ejemplo «hace dos años y
algo más», permanecen en revisión y no se guardan por esta vía.

El botón Enviar llama al endpoint directo existente, que valida sesión,
autorización, CSRF, versión, catálogo y lectura posterior. La persistencia se
realiza mediante `ConversationalM03Adapter::capture()`, el mismo mecanismo
canónico usado por la confirmación tradicional. No se crea una segunda tabla ni
una vía de persistencia paralela.

Las respuestas select, ambiguas, incompletas, contradictorias o que dependan
de una respuesta pendiente conservan la revisión clínica. No se usa Gemini para
decidir si una confirmación contractual puede omitirse.

## Pregunta anterior

Durante M03 aparece `← Pregunta anterior` cuando existe una respuesta previa
accesible y el módulo permite edición. La navegación es interna al compositor,
no usa `history.back()`, no escribe y recupera el valor persistido para editarlo.
Una corrección se envía con el mismo endpoint canónico, control de versión y
lectura posterior. Los registros posteriores no se borran ni se duplican.

El control se oculta cuando el módulo está finalizado o no es editable. El
reconocimiento activo se detiene al cambiar de pregunta, evitando que resultados
tardíos contaminen otro campo.

## Pruebas

- Contrato estático de endpoint directo, duración, navegación y reutilización
  canónica: `tests/patient-interview/direct-submit-back-contract.php`.
- Persistencia sintética de una duración literal, lectura posterior, edición sin
  duplicado y rechazo sin escritura de una respuesta inválida:
  `tests/patient-interview/m03-direct-duration-synthetic.php`.
- Recorrido HTTP sintético del endpoint directo, lectura posterior, avance,
  rechazo sin escritura y cero llamadas Gemini:
  `tests/patient-interview/m03-direct-http-acceptance.php`.
- Se conserva `m03-direct-capture-synthetic.php` para los valores numéricos y
  sus comprobaciones de versión, dependencias y duplicados.

Las pruebas de persistencia usan exclusivamente la base sintética y restauran
el conjunto original del paciente de prueba. No se modifican `bd_clinica`, el
paciente 310, formularios tradicionales, `.env`, Gemini ni producción.

La aceptación visual y manual en Chrome queda pendiente de ejecución por el
propietario.
