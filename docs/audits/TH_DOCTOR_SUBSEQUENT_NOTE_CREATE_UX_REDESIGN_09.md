# TH-DOCTOR-SUBSEQUENT-NOTE-CREATE-UX-REDESIGN-09

## Estado

**STATUS: PARTIAL — IMPLEMENTACIÓN VISUAL Y CONTRATOS ESTÁTICOS PASS; NAVEGADOR NO VERIFICADO**

La pantalla independiente de creación/edición de Nota subsecuente fue reorganizada conservando los contratos clínicos y de seguridad existentes. No se ejecutaron HTTP, PDO, SQL ni escrituras clínicas desde Codex.

## Identificación real

- Vista: `app/views/clinica/pacientes-nota-subsecuente-nueva.php`.
- Editor compartido: `app/views/clinica/partials/nota-subsecuente-editor.php`.
- Ruta objetivo: `/clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}/nueva`.
- Edición: la vista compartida conserva `edit_mode`, `idNota` e `idReceta`.
- Scripts: JavaScript inline existente para Quill, laboratorio, guardado, salida protegida y navegación; no se modificó ningún archivo JS.

## Cambios visuales

- Se dejó un único regreso en el hero: **Volver al historial**, conservando la URL real del listado y la protección de salida.
- Se mantuvo una franja compacta con nombre, expediente y referencia disponibles en el contexto autorizado.
- Se eliminó la tarjeta redundante de contexto de consulta.
- Se separaron valoración/signos vitales, evolución, receta opcional, laboratorio y próxima cita.
- Se amplió el espacio útil de los editores Quill y se conservaron alturas naturales.
- Laboratorio distingue estudios registrados de adjuntar un nuevo estudio.
- Las acciones finales quedaron en un bloque único: **Cancelar** y un único botón principal **Guardar nota subsecuente** / **Guardar cambios**.
- Se añadió CSS scoped a `.doctor-note-create-page` con adaptación móvil y sin `height: 100%` nuevo.

## Contratos preservados

Se conservaron IDs, nombres visibles y payloads existentes, incluidos `frecCardiaca`, `fc` condicional, `temperatura` de receta, `idNota`, `idReceta` y `proximacita`. También se conservaron los endpoints, CSRF de nota y laboratorio, sanitización de contenido, autorización/controlador existentes, transacción backend y funciones `AgregarNota()`, `AgregarLaboratorio()`, `tableLaboratorio()` y `DetalleLaboratorio()`.

No se modificaron modelos, controladores, rutas ni JavaScript.

## Validación

| Comprobación | Resultado |
|---|---|
| PHP lint de vista nueva | PASS |
| PHP lint de parcial | PASS |
| Contratos existentes 07, 08, 08D y 08E | PASS_STATIC |
| Contrato nuevo de creación 09 | PASS_STATIC |
| IDs y funciones preservados | PASS_STATIC |
| `git diff --check` | PASS, sin whitespace nuevo atribuible a este cambio |
| HTTP/PDO/SQL | NO EJECUTADO |
| Navegador, consola, foco y responsive renderizado | NO VERIFICADO |
| Capturas 375/768/1024/1366/1920 | NO VERIFICADO |

El contrato histórico `tests/doctor-subsequent-note/list-actions-contract-08c.php` continúa fallando por el requisito anterior de menú de acciones en el listado, mientras que el listado actual conserva el flujo de detalle único de 08D/08E. No se modificó el listado porque está fuera del alcance de esta tarea.

## Riesgos y pendientes

- La equivalencia visual real debe revisarse en Chrome/Edge con una sesión sintética autorizada.
- Deben comprobarse en navegador los estados de foco, carga, error, edición de Quill, responsive y ausencia de overflow.
- No se declara cierre funcional ni producción; la pantalla permanece pendiente de revisión visual.
