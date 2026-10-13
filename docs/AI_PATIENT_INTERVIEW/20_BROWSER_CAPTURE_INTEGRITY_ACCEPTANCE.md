# Fase 4.8 — Aceptación del chat en navegador real

**Fecha:** 2026-10-12  
**Estado:** pendiente de validación humana en Chrome/Edge.  
**Servidor sintético:** `127.0.0.1:8088`, PID `42880`.  
**Base efectiva:** `clinica_dolor_demo_20261010_final`.

## Preparación verificada

- Preflight sintético: PASS.
- Paciente 9301 y paciente 9302 pertenecen a clínicas sintéticas distintas.
- Catálogo M03: 7 temas y 25 preguntas.
- Escenario preparado para 9301: P1–P4 respondidas con marcadores sintéticos permitidos; P5 vacía.
- Filas preparadas: 5.
- Snapshot previo local: `C:\Users\silvi\AppData\Local\Temp\clinica-browser-m03-9301-20261012.json`.
- SHA-256 del snapshot: `90e669c1141683321b1c4547b85d7cec835d9641c91e3bd347efb2980e96b6ae`.
- Respaldo del paciente 310 verificado y sin modificaciones; no se utilizó en esta fase.
- No se modificó `bd_clinica`, `.env`, M01–M09 ni el formulario tradicional.

## Instrucciones al propietario

No introducir credenciales ni valores clínicos reales. El PIN se copia localmente sin imprimirlo:

```powershell
$pin = [regex]::Match((Get-Content .\tests\p11\http-crud.php -Raw), "\['pin'\s*=>\s*'([^']+)'\]").Groups[1].Value
if ([string]::IsNullOrWhiteSpace($pin)) { throw 'PIN sintético no localizado' }
Set-Clipboard -Value $pin
```

1. Abrir `http://127.0.0.1:8088/historia-clinica/acceso`.
2. Pegar el PIN desde el portapapeles y completar el acceso manualmente.
3. Abrir `http://127.0.0.1:8088/historia-clinica/entrevista`.
4. En DevTools, abrir **Network**, activar **Preserve log** y filtrar `Fetch/XHR`.
5. Recorrer P1–P5 sin confirmar ni modificar P1–P4. Si la interfaz exige volver a confirmar una respuesta anterior, detenerse y reportarlo.
6. En P5, escribir un valor sintético compatible y pulsar el botón de revisión.
7. Cancelar la confirmación. Debe no aparecer éxito ni una petición POST de captura.
8. Repetir con otro valor sintético y confirmar una sola vez.
9. Recargar la entrevista y comprobar que P5 se recupera.
10. Revisar viewports 375×667, 430×932, 768×1024 y 1440×900.

No compartir capturas que contengan el PIN, cookies, JWT ni datos fuera del escenario sintético.

## Estado antes de la validación humana

```yaml
SYNTHETIC_BROWSER_ENVIRONMENT: READY
SYNTHETIC_PATIENT_LOGIN: PENDING_HUMAN_VALIDATION
CHAT_BROWSER_RENDER: NOT_VERIFIED
CHAT_NAVIGATION_NO_WRITES: NOT_VERIFIED
CHAT_CANCEL_NO_WRITES: NOT_VERIFIED
CHAT_CONFIRM_P5_ONLY: NOT_VERIFIED
CHAT_RELOAD_PRESERVES_ANSWERS: NOT_VERIFIED
P1_VALUE_PRESERVED: NOT_VERIFIED
P2_VALUE_PRESERVED: NOT_VERIFIED
P3_VALUE_PRESERVED: NOT_VERIFIED
P4_VALUE_PRESERVED: NOT_VERIFIED
P5_CAPTURE_VALID: NOT_VERIFIED
DEPENDENT_ANSWERS_PRESERVED: NOT_VERIFIED
DUPLICATE_PREVENTION: NOT_VERIFIED
UNEXPECTED_HTTP_WRITES: NOT_VERIFIED
UNEXPECTED_DATABASE_WRITES: NOT_VERIFIED
BROWSER_CONSOLE_ERRORS: NOT_VERIFIED
RESPONSIVE_ACCEPTANCE: NOT_VERIFIED
SYNTHETIC_FIXTURE_CLEANUP: PENDING
BD_CLINICA_MODIFIED: NO
LEGACY_M03_MODIFIED: NO
OTHER_CLINICAL_MODULES_MODIFIED: NO
BROWSER_INTEGRITY_GATE: NOT_VERIFIED
PHASE_5_AI_READINESS: NOT_READY
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

La comprobación SQL posterior y la limpieza focal se ejecutarán sólo después de la confirmación humana de que el recorrido terminó. No se declara aceptación visual antes de esa evidencia.

## Addendum — incidencia de arranque y bloqueo de integridad

**Fecha:** 2026-10-12.

La incidencia del archivo `runtime-diagnostic-prepend.php` se explicó por dos listeners simultáneos en el puerto 8088:

- PID 42880: servidor sintético válido.
- PID 58184: instancia de diagnóstico antigua con `auto_prepend_file` apuntando al archivo ya eliminado.

El PID 58184 fue detenido exclusivamente por ser la instancia de diagnóstico creada durante esta validación. No se detuvo Apache/XAMPP ni ningún proceso ajeno. Después quedó un solo listener y las comprobaciones HTTP de login y chat devolvieron 200 sin el fatal del prepend.

Antes de permitir la prueba humana se ejecutó una consulta de solo lectura sobre el escenario preparado. El estado ya no coincidía con el snapshot: P1 presentó el estado `No`, P2–P4 estaban vacías y P5 continuaba vacía. No se ejecutó restauración, limpieza ni reinicialización. Esta combinación es compatible con una operación condicional que limpia dependencias, pero la causa temporal y la petición responsable no están demostradas.

La prueba de navegador queda bloqueada y no se entregará el entorno para capturas hasta preservar y revisar esta discrepancia. El PID 42880 fue detenido.

```yaml
PHP_PREPEND_ROOT_CAUSE: STALE_SECOND_LISTENER_PID_58184
PREPEND_FILE_REQUIRED: NO_FOR_VALID_SERVER
PREPEND_FILE_AVAILABLE: NO — no debe ser requerido por el arranque normal
HARNESS_FIX_APPLIED: PASS — instancia residual detenida y arranque validado por HTTP
SERVER_STARTUP: PASS_FOR_VALID_INSTANCE
SERVER_PORT: 8088
SERVER_PID_VERIFIED: PASS — PID 42880; detenido al detectar la discrepancia
LOGIN_HTTP: PASS — 200 sin fatal
CHAT_HTTP: PASS — 200 sin fatal
SYNTHETIC_DATABASE: PASS — allowlisted antes de la preparación
SNAPSHOT_PRESERVED: PASS
P1_P4_PRESERVED: FAIL — P1/P2/P3/P4 no coinciden con el escenario preparado
P5_STILL_EMPTY: PASS
UNEXPECTED_WRITES: YES — detectadas; origen temporal NOT_VERIFIED
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
READY_FOR_MANUAL_BROWSER_TEST: NO
BROWSER_INTEGRITY_GATE: NOT_VERIFIED
```
