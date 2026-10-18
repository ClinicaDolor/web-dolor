# 16 — Plan manual y runtime

## Preflight seguro

Usar una base allowlisted sintética separada de `bd_clinica`, dos pacientes de clínicas distintas, snapshot/rollback y guard fail-closed. Confirmar host, DB, versión, fixture y ausencia de datos reales antes de cualquier POST. No copiar credenciales a informes.

## Matriz mínima

1. Login paciente/doctor y logout; sin sesión recibe 302/403 apropiado.
2. Propio/cruzado para cada GET, AJAX, POST, PDF, download, imagen y finalización.
3. No-op y doble clic con conteo SQL.
4. GET no muta; si muta, captura exacta, decisión clínica y rollback.
5. P01–P11: respuesta, dependencia, guardado, recuperación, navegación y finalización.
6. Entrevista: token, límites, provider/fallback, consentimiento, traces, captura y map.
7. Doctor/admin D01–D40, roles, búsquedas, documentos y COFEPRIS.
8. Browser: 1440x900, 1280x720, 1024x768, 768x1024, 430x932, 375x667; consola, Network, foco, teclado, overflow, audio y canvas.

## Evidencia

Cada caso debe registrar commit/worktree, fixture ID no personal, request/response resumido, filas antes/después sin datos clínicos, captura y PASS/FAIL/NO_VERIFIED. Prohibidos POST sobre producción, paciente real, volcado indiscriminado o bypass.
