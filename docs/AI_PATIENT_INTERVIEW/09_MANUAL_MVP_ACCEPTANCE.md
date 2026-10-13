# Fase 3.4 — Validación manual asistida del MVP M03

Fecha de preparación: 11 de octubre de 2026.  
Estado: guía preparada; ejecución humana pendiente.

## Objetivo

Validar con un paciente sintético que `Formulario tradicional` y `Entrevista Guiada` usan el mismo catálogo M03/Tabaquismo, las mismas respuestas persistidas y los mismos controles de seguridad.

## Entorno seguro

- Proyecto: `C:\xampp\htdocs\clinica-dolor`
- Base única permitida: `clinica_dolor_demo_20261010_final`
- Servidor aislado: `127.0.0.1:8087`
- Pacientes de fixture: 9301 y 9302, en clínicas distintas.
- Gemini: deshabilitado y fuera del piloto.
- `bd_clinica`: no utilizar.
- No cambiar `.env`, Apache/XAMPP ni procesos ajenos.

El arranque ejecuta el preflight y carga sólo el catálogo sintético P05. La prueba HTTP previa confirmó aislamiento, autorización, CSRF, persistencia y conflicto 409; esta fase requiere observación humana del navegador.

## Instrucciones exactas para el propietario

### 1. Iniciar servidor

En PowerShell:

```powershell
Set-Location 'C:\xampp\htdocs\clinica-dolor'
$serverOutput = .\tests\p05\start-server.ps1 -Port 8087
$serverOutput
```

Conservar únicamente el `PID` mostrado por el script. Confirmar que la salida indica la base `clinica_dolor_demo_20261010_final` y la URL `http://127.0.0.1:8087`.

### 2. Acceder con cuenta sintética

Abrir en Chrome o Edge:

`http://127.0.0.1:8087/historia-clinica/acceso`

Usar el PIN sintético vigente del fixture local. El PIN no debe copiarse al informe, capturas, chat ni logs. No utilizar ninguna cuenta real.

### 3. Recorrido principal

1. Entrar a Historia Clínica.
2. Confirmar que aparecen `Formulario tradicional` y `Entrevista Guiada`.
3. Seleccionar `Entrevista Guiada`.
4. Abrir M03/Tabaquismo, tema 1.
5. Verificar las preguntas originales IDs 1–5 y sus opciones.
6. Seleccionar una respuesta.
7. Cancelar la confirmación y verificar que no se guardó.
8. Repetir la respuesta y aceptar la confirmación.
9. Comprobar que el mensaje de éxito aparece y conservar la hora de la acción.
10. Cambiar a `Formulario tradicional` y comprobar la misma respuesta.
11. Modificar un valor permitido desde el formulario tradicional.
12. Regresar a `Entrevista Guiada`, comprobar el nuevo valor y recargar la página.
13. Confirmar que la respuesta sigue presente y que no hay duplicados.

### 4. Casos adicionales

Registrar cada caso como PASS, FAIL o NOT_VERIFIED:

- Cancelación antes de guardar.
- Respuesta previamente registrada.
- Cambio Guiada → Tradicional → Guiada.
- Reanudación después de recarga.
- Sobrescritura confirmada.
- Conflicto HTTP 409 con respuesta obsoleta.
- Cambio `No` con dependencias existentes: debe bloquearse sin limpiar datos.
- CSRF ausente o inválido: HTTP 403.
- Acceso al expediente 9302 usando la sesión de 9301: HTTP 403.
- Error de servidor y recuperación.
- Doble clic o envío repetido: sin duplicados.

No forzar operaciones destructivas sobre datos reales. Todas las comprobaciones se realizan sólo sobre 9301/9302.

## Responsive y accesibilidad básica

En DevTools probar 375×667, 430×932, 768×1024 y 1440×900. Capturar sólo la interfaz sintética y no mostrar PIN, cookies, JWT ni datos reales.

Verificar:

- Texto legible y pregunta actual identificable.
- Botones grandes y operables.
- Confirmación comprensible.
- Navegación Anterior/Siguiente clara.
- Retorno al formulario tradicional.
- Mensajes de error visibles.
- Sin scroll horizontal ni superposiciones.
- Navegación por teclado.
- Consola JavaScript sin errores.

## Persistencia posterior

Después de la captura, antes de limpiar, entregar a Codex sólo:

- resultado PASS/FAIL de cada caso;
- hora aproximada de cada confirmación/cancelación;
- viewport probado;
- captura sintética sin credenciales ni identificadores reales.

Codex ejecutará después SELECT parametrizados sobre la base allowlisted para confirmar paciente, tema, pregunta, valor, ausencia de duplicados, ausencia de escritura tras cancelar y ausencia de cambios en otros módulos. No se debe copiar contenido clínico real al informe.

## Limpieza y detención

Desde el mismo directorio, mientras el PID sintético siga identificado:

```powershell
php tests/p05/cleanup-synthetic.php --database=clinica_dolor_demo_20261010_final
Stop-Process -Id <PID_DEL_SERVIDOR_P05> -Force
php tests/p05/verify-isolation.php --database=clinica_dolor_demo_20261010_final --json
```

El último comando debe mostrar la base allowlisted y `response_count: 0`. No ejecutar `Stop-Process` sobre Apache/XAMPP ni sobre otro PID.

## Checklist preparado

| Área | Estado actual |
|---|---|
| Entorno sintético | READY |
| Login sintético | NOT_VERIFIED manualmente |
| Navegación M03 | NOT_VERIFIED manualmente |
| Confirmación | NOT_VERIFIED manualmente |
| Persistencia compartida | PASS HTTP; manual pendiente |
| Compatibilidad tradicional | PASS HTTP contractual; manual pendiente |
| Responsive | NOT_VERIFIED |
| Teclado/consola | NOT_VERIFIED |
| Integridad final | PASS en pruebas automatizadas previas |

## Decisión provisional

`MANUAL_TEST_ENVIRONMENT: READY`

`SYNTHETIC_PATIENT_LOGIN: NOT_VERIFIED`

`M03_INTERVIEW_NAVIGATION: NOT_VERIFIED`

`M03_CONFIRMATION_FLOW: PARTIAL`

`M03_SHARED_PERSISTENCE: PASS`

`M03_TRADITIONAL_COMPATIBILITY: PARTIAL`

`M03_RESPONSIVE_UI: NOT_VERIFIED`

`M03_SYNTHETIC_BROWSER_E2E: NOT_VERIFIED`

`MVP_SYNTHETIC_DELIVERY: CONDITIONAL`

`REAL_PATIENT_RELEASE: NOT_AUTHORIZED`

La prueba manual no ha sido ejecutada por Codex. El MVP no puede declararse GO hasta recibir evidencia humana y completar la verificación SELECT posterior.

## Conteo de esta fase

- Pruebas manuales ejecutadas: 0.
- Pruebas manuales aprobadas: 0.
- Pruebas manuales fallidas: 0.
- Grupos manuales pendientes: 8 escenarios funcionales y 4 viewports responsive.
- Pruebas automatizadas/HTTP previas reutilizables: PASS según Fases 3.1–3.3; no se presentan como evidencia manual.
