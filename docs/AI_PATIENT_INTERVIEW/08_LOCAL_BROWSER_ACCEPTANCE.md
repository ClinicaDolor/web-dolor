# Fase 3.3 — Validación local definitiva del MVP

Fecha: 11 de octubre de 2026.  
Alcance: M03/Tabaquismo, tema 1, preguntas 1–5, sobre `clinica_dolor_demo_20261010_final`.

## Inventario del entorno

- Playwright en el proyecto: no encontrado.
- Puppeteer: no encontrado.
- WebDriver/ChromeDriver/EdgeDriver: no encontrado.
- Node.js disponible: sí, v20.19.6.
- Chrome local: ejecutable presente.
- Edge local: ejecutable presente.
- Servidor sintético: `127.0.0.1:8087`, con proceso P05 aislado.
- No se instalaron dependencias, no se abrió un túnel y no se modificó firewall.

La página de login fue comprobada por HTTP normal y el endpoint de login sintético respondió 200 con cookie de sesión. El navegador integrado de Codex no pudo conectar con el servidor local y mostró `Solicitud no disponible`. La ejecución headless de Chrome disponible tampoco produjo DOM utilizable en esta sesión. No se declara que el navegador local haya completado el flujo.

## Estado de pruebas

| Prueba | Resultado |
|---|---|
| Aislamiento de base | PASS |
| Login sintético por endpoint normal | PASS HTTP 200 |
| Lectura HTTP propia M03 | PASS |
| Acceso cruzado | PASS HTTP 403 |
| CSRF válido | PASS HTTP 200 |
| CSRF inválido/ausente | PASS HTTP 403 |
| Conflicto obsoleto | PASS HTTP 409 |
| Persistencia sintética y limpieza | PASS; 0 respuestas residuales |
| Navegador local automatizado | NOT VERIFIED |
| Login sintético en navegador | NOT VERIFIED |
| E2E completo Guiada ↔ Tradicional | NOT VERIFIED |
| Capturas responsive | NOT VERIFIED |
| Consola JavaScript y teclado | NOT VERIFIED |

No se repitieron las pruebas HTTP ya cerradas como PASS salvo el preflight y la limpieza necesarios para asegurar el aislamiento.

## Procedimiento manual reproducible

1. Ejecutar desde PowerShell:
   `powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/p05/start-server.ps1 -Port 8087`
2. Confirmar en la salida que la base es `clinica_dolor_demo_20261010_final` y conservar sólo el PID del servidor de prueba.
3. Abrir en Chrome o Edge local: `http://127.0.0.1:8087/historia-clinica/acceso`.
4. Introducir únicamente el PIN sintético vigente del fixture P04/P05. No registrar el PIN, cookie ni JWT.
5. Abrir Historia Clínica, seleccionar `Entrevista Guiada`, entrar a M03/Tabaquismo y verificar las preguntas del catálogo IDs 1–5.
6. Elegir una respuesta y cancelar la confirmación; comprobar en la base sintética que no cambió.
7. Repetir, confirmar y comprobar la fila persistida en `pac_respuestas_paciente_modulo_3`.
8. Cambiar a `Formulario tradicional`, comprobar la misma respuesta, editarla y volver a Guiada.
9. Recargar y comprobar recuperación. Repetir en 375, 430, 768 y 1440 px; revisar teclado, consola y overflow.
10. Ejecutar los casos de cancelación, respuesta previa, doble envío, conflicto 409 y dependencia `No` sin usar pacientes reales.
11. Limpiar y detener únicamente el PID del servidor:
    `php tests/p05/cleanup-synthetic.php --database=clinica_dolor_demo_20261010_final`
    `Stop-Process -Id <PID> -Force`
12. Confirmar el preflight final con `tests/p05/verify-isolation.php` y `response_count = 0`.

## Evidencia y correcciones

No se generaron capturas porque no se obtuvo una sesión clínica visible en navegador. No hubo defecto visual demostrado que justificara cambios de producto. Se preservaron catálogo, contratos, CSRF, autorización, concurrencia, Gemini deshabilitado y tablas clínicas.

## Cierre

`LOCAL_PLAYWRIGHT_AVAILABLE: NO`

`SYNTHETIC_BROWSER_LOGIN: NOT_VERIFIED`

`M03_CONFIRMATION_FLOW: PARTIAL`

`M03_LEGACY_COMPATIBILITY: PARTIAL`

`M03_BROWSER_VISUAL: NOT_VERIFIED`

`M03_SYNTHETIC_E2E: NOT_VERIFIED`

`MVP_SYNTHETIC_DELIVERY: CONDITIONAL`

`REAL_PATIENT_RELEASE: NOT_AUTHORIZED`

El bloqueo restante es instrumental: falta una herramienta de automatización local que pueda cargar el servidor y completar el login normal. No se debe convertir `NOT_VERIFIED` en `PASS` sin ejecutar ese recorrido.

La ejecución manual asistida de Fase 3.4 debe seguir `09_MANUAL_MVP_ACCEPTANCE.md`. Codex no debe marcar los casos humanos como realizados hasta recibir evidencia del propietario.
