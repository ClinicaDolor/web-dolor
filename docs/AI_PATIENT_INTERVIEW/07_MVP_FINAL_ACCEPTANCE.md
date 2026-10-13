# Fase 3.2 — Aceptación final del MVP M03/Tabaquismo

Fecha: 11 de octubre de 2026.  
Alcance: M03/Tabaquismo, tema 1, preguntas 1–5. No se ampliaron temas ni funcionalidades.

## Resultado ejecutivo

La autenticación sintética normal está disponible y fue comprobada por HTTP contra el servidor aislado. El navegador integrado de esta sesión no pudo cargar el servidor local: mostró `Solicitud no disponible` y no permitió completar el formulario real. Por ello, la aceptación visual y el recorrido completo desde navegador permanecen `NOT_VERIFIED`; no se declara PASS por inferencia.

## Entorno y seguridad

- Base utilizada: `clinica_dolor_demo_20261010_final`.
- Pacientes sintéticos: 9301 y 9302, en clínicas distintas.
- La prueba de login usó el endpoint normal `/historia-clinica/login` y el PIN del fixture sintético; no se registra aquí.
- HTTP login sintético: 200, cookie de sesión presente.
- No se modificó `bd_clinica`, `.env`, Gemini ni usuarios reales.
- El servidor temporal P05 se detuvo al finalizar.
- No se guardaron cookies, JWT, PIN ni tokens en capturas o informes.

## Evidencia funcional

| Caso | Resultado |
|---|---|
| Login sintético normal | PASS HTTP |
| Lectura propia M03 | PASS HTTP |
| Aislamiento entre clínicas | PASS HTTP 403 |
| CSRF válido | PASS HTTP 200 |
| CSRF ausente/inválido | PASS HTTP 403 |
| Confirmación y persistencia desde navegador | NOT VERIFIED |
| Cambio Guiada → Tradicional | NOT VERIFIED |
| Cambio Tradicional → Guiada | NOT VERIFIED |
| Reanudación después de recarga | NOT VERIFIED |
| Cancelación de confirmación | NOT VERIFIED |
| Error visual, doble clic y carga | NOT VERIFIED |
| Conflicto obsoleto | PASS HTTP 409; el cambio no se aplicó |
| Limpieza y duplicados | PASS; 0 respuestas M03 al cierre |

La prueba HTTP sintética de Fase 3.1 verificó persistencia, rechazo cruzado, conflicto 409 y limpieza. No sustituye el requisito de observar esos pasos desde el navegador autenticado.

## Validación visual

Viewports requeridos: 375×667, 430×932, 768×1024, 1024×768 y 1440×900.

Estado: `NOT VERIFIED` para todos. El navegador está disponible, pero el proveedor de navegación local de esta sesión no pudo cargar `127.0.0.1:8087`/`localhost:8087` y devolvió una página de error antes del login. No se generaron capturas clínicas porque no habría evidencia válida del flujo.

## Procedimiento manual reproducible pendiente

1. Iniciar el servidor P05 con `tests/p05/start-server.ps1` y confirmar que la base activa es exactamente `clinica_dolor_demo_20261010_final`.
2. Abrir `http://127.0.0.1:8087/historia-clinica/acceso` en Edge o Chrome local.
3. Usar sólo el PIN sintético disponible en el fixture, sin registrar el valor.
4. Abrir M03/Tabaquismo, seleccionar Entrevista Guiada y tomar capturas en los cinco viewports.
5. Seleccionar una respuesta, cancelar la confirmación y comprobar que la base no cambia.
6. Repetir, confirmar, consultar la fila persistida y cambiar a Formulario tradicional.
7. Volver a Guiada, recargar y comprobar la misma respuesta.
8. Ejecutar el recorrido inverso desde Tradicional y verificar lectura en Guiada.
9. Ejecutar cancelación, respuesta previa, doble clic, conflicto 409 y respuesta `No` con dependencia, siempre con limpieza sintética.
10. Detener el servidor y ejecutar el preflight final.

## Correcciones realizadas

No se realizaron correcciones de producto en esta fase porque no se observó un defecto visual autenticado. Se conservaron CSRF, autorización, confirmación, captura compartida y concurrencia. La compatibilidad de PowerShell del arnés y el mapeo HTTP 409 ya fueron corregidos en Fase 3.1.

## Criterio de entrega

`M03_AUTHENTICATED_BROWSER: NOT_VERIFIED`

`M03_CONFIRMATION_FLOW: PARTIAL`

`M03_LEGACY_COMPATIBILITY: PARTIAL`

`M03_INTERVIEW_UI: PARTIAL`

`M03_SYNTHETIC_E2E: NOT_VERIFIED`

`M03_DATA_INTEGRITY: PASS`

`MVP_SYNTHETIC_DELIVERY: CONDITIONAL`

`REAL_PATIENT_RELEASE: NOT_AUTHORIZED`

El piloto no debe promoverse a GO hasta completar el recorrido autenticado y las capturas responsive en un navegador local operativo.

La Fase 3.3 confirmó que no hay Playwright, Puppeteer ni WebDriver instalados. Chrome y Edge están presentes, pero no se obtuvo automatización local utilizable; el procedimiento manual reproducible y el inventario están en `08_LOCAL_BROWSER_ACCEPTANCE.md`.

La Fase 3.4 deja preparado el entorno para validación manual asistida. El procedimiento exacto, checklist y protocolo de entrega de evidencia están en `09_MANUAL_MVP_ACCEPTANCE.md`; la ejecución humana no debe ser simulada.
