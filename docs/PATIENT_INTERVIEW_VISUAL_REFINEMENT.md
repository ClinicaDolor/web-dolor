# Refinamiento visual de la Entrevista Conversacional

Fecha: 2026-10-14

## Causas corregidas

- La vista de entrevista no cargaba el recurso local de Feather, por lo que
  los atributos `data-feather` de la navegación permanecían sin icono.
- No había un control propio de menú en esta vista. Se añadió un control
  accesible para escritorio y móvil: escritorio alterna el modo compacto con
  iconos visibles; móvil abre/cierra el panel y permite cerrarlo con Escape.
- El historial construía una cadena desde el `textContent` del bloque completo
  de la pregunta. Esa fuente incluía botones, ayuda y controles del compositor.
  Ahora se leen únicamente módulo, pregunta y respuesta guardada desde los
  atributos/elementos canónicos ya renderizados, usando nodos DOM y
  `textContent` por campo.
- La navegación a una pregunta anterior dejaba visible el mensaje «Respuesta
  confirmada». Ahora recupera el valor en el campo de edición y oculta ese
  mensaje sin generar una confirmación de guardado.

## Alcance preservado

La entrega se limita a `app/views/paciente/entrevista.php` y sus pruebas de
contrato. No modifica rutas, controladores clínicos, modelos, esquema SQL,
formularios tradicionales, `.env`, `bd_clinica`, paciente 310, Gemini ni el
servicio de dictado. El botón de historial sigue siendo una consulta sin
escritura; la navegación anterior tampoco escribe.

## Validación

- `tests/patient-interview/visual-refinement-contract.php`: PASS.
- `tests/patient-interview/direct-submit-back-contract.php`: PASS.
- `tests/patient-interview/native-stt-js.test.js`: PASS.
- Sintaxis PHP de la vista: PASS.
- `git diff --check`: PASS; solo se reportan advertencias de normalización de
  finales de línea de archivos ya modificados.

La aceptación visual real en Chrome a 375, 430, 768, 1024 y 1440 px queda
pendiente porque no hay una sesión autenticada de la Entrevista Conversacional
disponible para automatizar sin solicitar ni manejar credenciales. No se
declara PASS visual por inspección estática.
