# PHASE-05A — Evidencia visual real y responsive P01–P11

**Fecha de ejecución:** 2026-10-12  
**Estado:** `BLOCKED` en la compuerta obligatoria  
**Alcance ejecutado:** precondiciones sintéticas, autenticación de prueba y render de P04 únicamente  
**Fases posteriores:** no iniciadas

## Resultado ejecutivo

La base sintética y la sesión de prueba fueron verificadas, y P04 se renderizó correctamente en el navegador integrado. La ejecución no puede continuar a la matriz de 66 combinaciones porque el navegador disponible no proporciona las capacidades obligatorias para evidencia reproducible: viewport efectivo configurable, exportación PNG a una ruta local, consola JavaScript y observación de red.

No se fabricaron capturas, mediciones ni resultados PASS para pruebas que no pudieron ejecutarse.

## Compuerta obligatoria

| Requisito | Resultado | Evidencia / limitación |
|---|---|---|
| Base de datos sintética aislada | `PASS` | `tests/p04/verify-isolation.php --database=clinica_dolor_demo_20261010_final --json` confirmó la base permitida, tablas requeridas, pacientes sintéticos, clínicas distintas y alcance sintético. La verificación fue de solo lectura. |
| Conexión a `bd_clinica` | `PASS` | La configuración del servidor de prueba utilizó exclusivamente `clinica_dolor_demo_20261010_final`; no se ejecutaron escrituras. |
| Sesión sintética reproducible | `PASS` | Login mediante el flujo real del portal paciente en un host local aislado. No se documentan PIN, cookies ni tokens. |
| P04 renderizado | `PASS` | Se abrió el módulo P04 con la sesión sintética; se observaron hero, preguntas, controles, modal de instrucciones y navegación del portal. Esto fue una prueba de compuerta, no una validación completa de P04. |
| Viewport efectivo `375×667` | `FAIL` | El navegador integrado no expone una API de viewport; la captura observada quedó en `1280×720`. No fue posible demostrar `window.innerWidth/window.innerHeight` en `375×667`. |
| Captura PNG guardada en disco | `FAIL` | `getScreenshot()` sólo entregó una imagen al panel del navegador y no una ruta o bytes exportables mediante la API disponible. No se creó ningún PNG falso. |
| Consola JavaScript accesible | `FAIL` | La API disponible no expone consola ni lectura de errores del documento. |
| Red observable | `FAIL` | La API disponible no expone solicitudes, respuestas ni trazas de red. |

La compuerta falla por cuatro capacidades del navegador, por lo que la especificación exige detener la campaña completa.

## Pruebas realizadas antes del bloqueo

1. Preflight de aislamiento contra `clinica_dolor_demo_20261010_final`: `PASS`, solo lectura.
2. Arranque temporal del servidor sintético local en el puerto `8133`: `PASS`.
3. Login del paciente sintético mediante la ruta real: `PASS`.
4. Render del Dashboard Paciente posterior al login: `PASS` como comprobación de sesión.
5. Render del módulo P04: `PASS` como comprobación de la compuerta.
6. Observación de una captura IAB del estado de P04: disponible en el panel, sin exportación PNG y con tamaño observado `1280×720`.
7. Cierre del servidor temporal de pruebas: `PASS`.

La navegación, audio, persistencia, modales, mapas, consola y autorización cruzada no se declaran validados por estas comprobaciones parciales.

## Matriz de módulos y viewports

La matriz solicitada —P01 a P11 en `1440×900`, `1280×720`, `1024×768`, `768×1024`, `430×932` y `375×667`— quedó **NO EJECUTADA** en su totalidad debido al bloqueo de la compuerta. No se asignan estados PASS/FAIL individuales a las 66 combinaciones.

| Grupo | Resultado |
|---|---|
| P01–P11, seis viewports cada uno | `NO VERIFICADO` |
| Evidencia PNG por combinación | `NO VERIFICADO` |
| Consola por combinación | `NO VERIFICADO` |
| Red por combinación | `NO VERIFICADO` |
| Responsive real y overflow | `NO VERIFICADO` |
| Modal canónico | `NO VERIFICADO` |
| Sidebar y navegación | `NO VERIFICADO` |
| Narración y resaltado | `NO VERIFICADO` |
| Mapa corporal P11 | `NO VERIFICADO` |
| Persistencia y finalización | `NO VERIFICADO` |

## Integridad del proyecto

- Archivos de aplicación modificados durante esta fase: **ninguno**.
- Controladores, modelos, vistas, CSS, JavaScript, rutas, autenticación y persistencia: **sin cambios**.
- Base real `bd_clinica`: **no utilizada**.
- Datos reales: **no utilizados**.
- Commits, push y despliegues: **no realizados**.
- PHASE-05B: **no iniciada**.
- `git diff --check`: `PASS` con advertencias históricas de conversión LF/CRLF reportadas por Git; no se introdujeron cambios de aplicación para corregirlas.

## Bloqueo y precondición para reanudar

Se requiere un navegador automatizable que permita, como mínimo:

- fijar y comprobar `window.innerWidth`/`window.innerHeight` para cada viewport;
- guardar PNG reales con ruta reproducible;
- leer consola JavaScript;
- observar solicitudes y respuestas de red;
- conservar la sesión sintética aislada.

La siguiente ejecución debe repetir la compuerta completa antes de recorrer P01–P11. Mientras esas capacidades no estén disponibles, esta auditoría permanece `BLOCKED` y no debe interpretarse como aprobación visual, funcional, de seguridad ni de producción.

## PHASE-05A-R1 — Desbloqueo de evidencia visual

**Fecha:** 2026-10-12  
**Estado:** `BLOCKED` para automatización desde Codex; `MANUAL_HANDOFF_READY`  
**Resultado:** se detectaron navegadores nativos instalados, pero no existe un canal de control autorizado desde Codex.

### Detección del entorno

| Capacidad | Resultado | Detalle |
|---|---|---|
| Chrome nativo | `B — instalado, inaccesible desde Codex` | Ejecutable detectado en `C:\Program Files\Google\Chrome\Application\chrome.exe`. La creación de una pestaña Chrome mediante CUA respondió `Browser is not available: chrome`. |
| Edge nativo | `B — instalado, inaccesible desde Codex` | Ejecutable detectado en `C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe`. La creación de una pestaña Edge mediante CUA respondió `Browser is not available: edge`. |
| Chromium nativo | `C — no detectado` | No se encontró ejecutable en las rutas locales revisadas. |
| Playwright runner | `C/D — no disponible para Codex` | No existe paquete local de Playwright ni comando `playwright`. Existe caché de navegadores bajo `%LOCALAPPDATA%\ms-playwright`, pero no un runner autorizado para controlarlo. |
| IAB | `Disponible, insuficiente` | Renderiza y permite interacción básica; no ofrece viewport configurable, consola, red ni exportación PNG a disco. No se repitió como método de R1. |

### Compuerta mínima R1

| Campo solicitado | Estado R1 | Nota |
|---|---|---|
| `NATIVE_BROWSER_AVAILABLE` | `PARTIAL` | Chrome y Edge están instalados, pero no son controlables desde Codex. |
| `PLAYWRIGHT_LOCAL` | `NO` | No hay runner local; no se instaló software. |
| `AUTHENTICATED_SESSION` | `PASS` reutilizable | La evidencia equivalente de PHASE-05A confirmó login sintético en la base permitida. No se copiaron cookies, PIN ni tokens. |
| `VIEWPORT_375X667` | `NO VERIFICADO` | Requiere ejecución manual en Chrome/Edge. |
| `PNG_EXPORT` | `NO VERIFICADO` | Requiere captura manual real guardada en la carpeta de evidencia. |
| `CONSOLE_ACCESS` | `NO VERIFICADO` | Requiere DevTools manual. |
| `NETWORK_ACCESS` | `NO VERIFICADO` | Requiere DevTools manual. |
| `P04_VISUAL_EVIDENCE` | `PARTIAL` | P04 fue renderizado en IAB a 1280×720; no existe PNG exportado ni evidencia responsive. |
| `RESPONSIVE_MATRIX` | `BLOCKED` | No iniciar la matriz hasta satisfacer la compuerta mínima. |
| `MANUAL_VALIDATION_HANDOFF` | `READY` | Checklist concreta en `docs/audits/evidence/PHASE_05A/MANUAL_VALIDATION_CHECKLIST.md`. |
| `MODIFIED_APPLICATION_FILES` | `NONE` | Sólo se actualizó documentación de auditoría y se añadió la checklist. |
| `PRODUCTION_READY` | `NO` | Esta fase no otorga autorización de producción. |

### Método manual autorizado

La ejecución manual debe realizarse en Chrome o Edge de Windows con una ventana/perfil temporal aislado, sin importar cookies, contraseñas, tokens ni datos desde perfiles personales. Debe apuntar al entorno sintético y confirmar la base permitida antes del login. La checklist incluye P04, P07, P10 y P11 como primera muestra, seguida de la matriz P01–P11.

Los resultados manuales sólo pueden convertirse en `PASS` cuando exista una captura PNG legible, viewport registrado, observación de consola/red y evidencia suficiente para la interacción concreta. El audio audible requiere comprobación humana; un mock o la presencia del botón no es PASS de audio real.

