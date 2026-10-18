# 25 — Estrategia de validación runtime V1.3

Plan no ejecutado. Su propósito es convertir los hallazgos estáticos en evidencia reproducible sin tocar datos reales.

## Preflight obligatorio

1. Ejecutar desde el mismo entorno autorizado por el propietario y registrar sistema, rama, HEAD, URL, host, puerto y base efectiva.
2. Demostrar que la base es sintética y está en allowlist; si no se puede demostrar, detenerse.
3. Usar dos pacientes sintéticos de clínicas distintas, dos identidades/roles y recursos sintéticos propios, ajenos, inexistentes y sin propietario.
4. Tomar snapshot lógico limitado o hashes de filas objetivo antes y después; no exportar secretos, PIN, hashes ni expedientes completos.
5. No crear bypass de sesión. Autenticarse por el flujo real con credenciales sintéticas protegidas fuera del informe.
6. Registrar request method, ruta, status, cuerpo sanitizado, consola, Network y DB diff. Nunca guardar tokens, cookies, prompts clínicos reales o documentos reales.

## Matriz mínima de pruebas

| Gate | Casos | Escritura permitida | Evidencia PASS |
|---|---|---|---|
| Identidad | anónimo, paciente A/B, clínica A/B, roles permitidos/no permitidos | No | status seguro, sin datos cruzados, redirección o 403/404 según contrato. |
| Recursos | propio, ajeno de paciente, ajeno de clínica, inexistente, ownerless | Sólo fixture sintético y autorización previa | allow/deny por operación sin filtrar existencia sensible. |
| Externos/PDF | catálogo, detalle y PDF A/B; ownerless; ID manipulado | No salvo fixture autorizado | A sólo A; B sólo B; sin bytes ni HTML clínico ajeno. |
| Archivos | propio, ajeno, traversal, tipo no permitido y ruta ausente | No | sólo archivo autorizado; deny seguro para el resto. |
| CSRF | token válido, ausente, inválido, stale, de otra sesión | Sólo caso válido en fixture sintético | válido cambia sólo fixture; negativos no cambian filas. |
| SQL | entrada normal, delimitadores, IDs inválidos y filtros extremos | No destructiva | respuesta de contrato, alcance intacto y sin error SQL. |
| GET | carga inicial, recarga, doble GET, navegación atrás/adelante | No | diff vacío o delta idempotente previamente aprobado. |
| P04 histórico | repetir gate con pacientes sintéticos actuales | Sólo fixture autorizado | salida timestamped; no reutilizar `P04-AUTH-20261014121758`. |
| IA | provider mock success/failure, payload vacío y contenido sintético | No | payload minimizado, fail-closed, trazas sin PHI/secretos. |
| Browser | 1440x900, 1280x720, 768x1024, 430x932, 375x667 | No | capturas reales, consola/Network revisados, foco y overflow documentados. |

## Secuencia segura

### Fase 1 — lectura y autorización

Realizar primero los casos anónimos y de sólo lectura. Confirmar que la respuesta no contiene datos del otro paciente antes de permitir el caso propio. Las respuestas 404/403 deben registrarse sin usar un mensaje que revele si el ID existe.

### Fase 2 — escrituras sintéticas

Sólo después del preflight, crear o seleccionar fixtures descartables expresamente autorizados. Guardar exactamente una respuesta propia, repetirla para comprobar no-op y luego probar un ID ajeno sin permitir modificación. No finalizar módulos de pacientes reales ni usar limpieza amplia.

### Fase 3 — documentos

Usar archivos sintéticos no clínicos con propietario A/B y tipos permitidos/no permitidos. Verificar que el endpoint nunca devuelve bytes para el recurso ajeno. La prueba PDF debe validar tanto autorización como ausencia de generación de artefactos no autorizados.

### Fase 4 — integridad y navegador

Capturar antes/después de GET y repetir requests. Después abrir navegador aislado, inspeccionar consola/Network, teclado, foco, responsive y estado de error. Audio real sólo se marca PASS si hubo observación audible humana.

## Artefacto por caso

Cada caso debe producir un registro con `case_id`, fecha/hora, entorno, identidad sintética, recurso sintético, request sanitizado, status, resultado esperado/observado, DB diff limitado, consola/Network, captura cuando aplique y `PASS/FAIL/NO_VERIFICADO`. La colección no debe contener credenciales ni datos clínicos reales.

## Criterios de interrupción

Detenerse ante base no allowlisted, sesión no reproducible, recurso sin owner para una prueba que requiere ownership, escritura fuera del fixture, respuesta con datos cruzados, token/secretos en logs, PDF/archivo no autorizado, error destructivo o discrepancia de entorno. Reportar el último caso seguro y no “continuar para ver si falla”.

## Relación con los hallazgos

- SEC-DOC-001/002: gates Externos/PDF y Recursos.
- SEC-FILE-003: gate Archivos.
- SEC-CSRF-004: gate CSRF por cada POST/AJAX.
- SEC-SQL-005: gate SQL más revisión estática de taint.
- CLIN-GET-006: gate GET con diff.
- SEC-AUTH-007: gates Identidad/Recursos por operación.
- AI-PRIV-008: gate IA con mock y logs.
- QA-RUNTIME-010: todos los artefactos de la estrategia.
- TEST-018: repetir específicamente P04 y marcar el run histórico como no vigente.

No se debe reportar `RUNTIME_VERIFIED` hasta que exista evidencia completa, reproducible y con alcance explícito. Un PASS en P04 no certifica P05–P11, Doctor, recetas externas, PDF, descargas, COFEPRIS ni producción.
