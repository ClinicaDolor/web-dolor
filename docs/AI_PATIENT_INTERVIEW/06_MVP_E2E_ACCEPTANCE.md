# Fase 3.1 — Aceptación E2E del MVP M03/Tabaquismo

Fecha: 11 de octubre de 2026.  
Alcance: únicamente M03/Tabaquismo, tema 1, preguntas 1–5, sobre la base sintética `clinica_dolor_demo_20261010_final`.

## Alcance y aislamiento

- No se utilizó `bd_clinica`.
- El preflight confirmó `SELECT DATABASE() = clinica_dolor_demo_20261010_final`.
- Pacientes sintéticos: 9301 en clínica 9101 y 9302 en clínica 9102.
- Catálogo confirmado: 7 temas y 25 preguntas.
- Gemini y cualquier proveedor externo permanecieron fuera del recorrido.
- Las escrituras de la prueba fueron exclusivamente respuestas M03 de 9301/9302; se eliminaron al finalizar.

## Matriz de aceptación ejecutada

| Caso | Evidencia | Resultado |
|---|---|---|
| Paciente sintético autenticado abre su vista | HTTP real contra servidor PHP aislado, paciente 9301 | PASS |
| Paciente sintético lee su M03 | HTTP 200 y HTML del cuestionario | PASS |
| Acceso cruzado a M03 | 9301 intentando leer 9302, HTTP 403, sin contenido de Tabaquismo | PASS |
| Guardado propio | POST HTTP 200 con sesión, CSRF M03 y `expectedRespuesta` | PASS |
| Guardado cruzado | POST HTTP 403; no se aceptó el cambio | PASS |
| CSRF ausente/inválido | Contrato aislado: 6 aserciones; además el endpoint sin sesión/token responde 403 | PASS |
| Concurrencia optimista | Segunda petición HTTP con `expectedRespuesta` obsoleto devuelve 409 y no aplica el cambio | PASS |
| Reapertura y limpieza | `cleanup-synthetic.php` eliminó 25 respuestas sintéticas; preflight final: 0 respuestas | PASS |
| Contratos JS/HTML P05 | `contract-check`, `navigation-contract`, `flow-speed-contract`, `audio-mock` | PASS |
| Navegador autenticado | Navegador operativo, pero sin credencial sintética reutilizable ni API de cookie disponible; no se automatizó login | NOT VERIFIED |
| Visual autenticado | No se capturó una vista clínica autenticada | NOT VERIFIED |

## Responsive y navegador

Los viewports requeridos fueron definidos como criterio pendiente: 375×667, 430×932, 768×1024, 1024×768 y 1440×900. No se declara PASS porque la sesión clínica no pudo establecerse en el navegador sin automatizar credenciales. No se generaron capturas engañosas de una vista no autenticada como evidencia de la entrevista.

El navegador sí respondió y mostró el rechazo seguro de la ruta sin sesión. El recorrido redirigió a `/historia-clinica` y no entregó expediente ni preguntas clínicas.

## Integridad y regresión

- La base sintética terminó con catálogo intacto y `response_count = 0`.
- La limpieza reportó 25 filas M03 temporales eliminadas, 0 comentarios y 0 finalizaciones.
- No se observaron escrituras fuera de M03 ni cambios en pacientes ajenos.
- La corrección del arnés `tests/p05/start-server.ps1` fue necesaria porque la versión local de PowerShell no admite `Start-Process -Environment`; se sustituyó por `ProcessStartInfo` con variables de entorno explícitas.
- `tests/p05/http-crud.php` se actualizó únicamente para enviar el CSRF y la respuesta esperada que exige el contrato implementado.
- Se corrigió el mapeo HTTP del conflicto de concurrencia M03 para conservar 409 en lugar de convertirlo en 422.

## Pruebas y límites

Conteo de evidencia: 1 preflight inicial, 1 E2E HTTP sintético con lectura/escritura cruzada y conflicto obsoleto, 2 limpiezas de recuperación, 1 preflight final, 4 contratos Node, 1 contrato CSRF de 6 aserciones y lint PHP de los archivos modificados.

No se probaron credenciales reales, `bd_clinica`, Gemini, audio real, lector de pantalla ni una carrera concurrente real de dos navegadores. No se realizaron commits, push ni despliegues.

## Estado de aceptación

`M03_CSRF_GATE: PASS`

`M03_PATIENT_AUTHORIZATION: PASS`

`M03_SHARED_CAPTURE: PASS`

`M03_CONFIRMATION_FLOW: PARTIAL`

`M03_LEGACY_COMPATIBILITY: PARTIAL`

`M03_INTERVIEW_UI: PARTIAL`

`M03_SYNTHETIC_E2E: NOT_VERIFIED`

`M03_DATA_INTEGRITY: PASS`

`MVP_DELIVERY: CONDITIONAL`

La condición pendiente para elevar el estado es ejecutar la entrevista autenticada en navegador y completar las cinco capturas responsive con una sesión sintética controlada. El login HTTP sintético sí respondió 200, pero el navegador integrado no pudo cargar el servidor local. No se autoriza uso con pacientes reales por este informe.
