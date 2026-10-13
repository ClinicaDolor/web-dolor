# PHASE-04B-02 — Unificación del menú lateral y navegación P01–P11

Fecha de ejecución: 2026-10-12
Proyecto: Clínica del Dolor
Rama: `Silvino`
HEAD de referencia: `1b08d9b`

## Resultado

```text
PHASE_04B_02_SIDEBAR_NAVIGATION:
STATUS: PARTIAL
MODULES_INSPECTED: P01–P11 inspeccionados estáticamente; P01, P02, P05 y P07 comprobados en navegador sintético; no se ejecutaron POST ni finalizaciones.
SIDEBAR_COMPONENTS: app/Controllers/SidebarController.php; app/Helpers/Sidebar.php; public/assets/js/main.js; vistas paciente P02–P11.
ROOT_CAUSES: Las etiquetas de P05 y P07 no tenían acento en el menú, mientras que el contexto del módulo sí lo tenía; la comparación exacta dejaba el estado activo inconsistente. Los togglers paciente carecían de aria-controls/aria-expanded sincronizados y algunos heredaban anclas sin acción navegable.
ACTIVE_STATE_PARITY: PASS estático y PASS renderizado en P02, P05 y P07; un único elemento activo y una única aria-current="page" por vista comprobada.
ROUTE_RESOLUTION: PASS estático; los diez módulos paciente se resuelven mediante SidebarController con sus slugs y parámetros existentes. PASS renderizado para P02, P05 y P07.
ACCESSIBLE_NAMES: PASS estático para los diez togglers de las vistas paciente y para los enlaces del helper; PASS renderizado para P02, P05 y P07.
ARIA_CURRENT: PASS; Sidebar::render() emite aria-current="page" sólo en el enlace cuyo título coincide con el contexto activo.
DESKTOP_NAVIGATION: NO VERIFICADO en los viewports de escritorio solicitados; el navegador disponible midió 661x627. Apertura por click y activación por teclado PASS en P07 con la pantalla libre.
MOBILE_NAVIGATION: PASS parcial en 661x627: el sidebar inicia cerrado, el toggler abre/cierra y aria-expanded/aria-label se sincronizan. 430x932 y 375x667: NO VERIFICADO.
RESPONSIVE: NO VERIFICADO para 1440x900, 1280x720, 768x1024, 430x932 y 375x667; la API disponible no permitió fijar esos viewports.
BROWSER_EVIDENCE: P01 acceso sin sidebar interno; P02 Inicio activo; P05 Antecedentes Personales No Patológicos activo; P07 Antecedentes Personales Patológicos activo; sin overflow horizontal en P02, P05 y P07 al viewport 661x627. Se generó captura CUA visible de P07 con el menú cerrado.
MODIFIED_FILES: app/Controllers/SidebarController.php; public/assets/js/main.js; vistas paciente P02–P11; tests/phase04b/sidebar-navigation.mjs; este informe.
SHARED_REGRESSION: PASS estático: la sincronización ARIA está protegida por body.app-shell-patient y no altera el comportamiento Doctor. Runtime Doctor: NO VERIFICADO en esta fase.
GLOBAL_DIFF_CHECK: PASS; sólo avisos históricos de conversión LF/CRLF.
UNEXPECTED_CHANGES: No se introdujeron cambios fuera del alcance de navegación paciente. El worktree ya contenía cambios locales previos, preservados sin reset, limpieza ni commit.
REMAINING_RISKS: viewports completos y consola pendientes; no se recorrieron todas las vistas con sesión en navegador por limitar el alcance a GET sintéticos seguros; la activación sigue dependiendo de coincidencia exacta de etiquetas; permanecen ACT-05C-02, riesgos de autorización documental, recetas externas, COFEPRIS y efectos secundarios GET.
PRODUCTION_READY: NO
```

## 1. Alcance e inventario

Se inspeccionaron el login P01, el dashboard P02 y las nueve vistas de Historia Clínica P03–P11. P01 conserva su pantalla de acceso específica y no incorpora el sidebar interno. P02–P11 reutilizan el mismo shell paciente, el helper de sidebar y `public/assets/js/main.js`.

La resolución de rutas y títulos se conserva en `SidebarController.php`. La corrección de esta fase sólo normaliza las dos etiquetas que no coincidían exactamente con los títulos del contexto:

- P05: `Antecedentes Personales No Patológicos`.
- P07: `Antecedentes Personales Patológicos`.

No se cambiaron slugs, URLs, permisos, formularios, modales, AJAX, persistencia ni lógica clínica.

## 2. Correcciones aplicadas

Los togglers del topbar de P02–P11 son botones semánticos con nombre accesible, `aria-controls="sidebar"` y estado inicial explícito. `main.js` limita la sincronización al shell paciente y actualiza al abrir/cerrar:

- `aria-expanded`.
- `aria-label` (`Abrir menú principal` / `Cerrar menú principal`).
- prevención del hash accidental en togglers ancla heredados.
- cierre inicial del sidebar en viewport menor de 768 px.

`Sidebar::render()` ya conserva la regla de un solo activo y sólo agrega `aria-current="page"` al enlace activo; no fue necesario modificar ese helper.

## 3. Pruebas técnicas

PASS ejecutado:

- `node tests/phase04b/sidebar-navigation.mjs`.
- `php -l` para `SidebarController.php` y las diez vistas paciente.
- `node --check public/assets/js/main.js`.
- `node --check tests/phase04b/sidebar-navigation.mjs`.
- `git diff --check` en los archivos de esta fase.

El contrato estático comprueba las diez vistas paciente, el helper, las etiquetas canónicas P05/P07, el nombre accesible de los togglers, ARIA, y la exclusión del sidebar en P01.

## 4. Evidencia de navegador sintético

Se utilizó el servidor aislado de P04 con la base `clinica_dolor_demo_20261010_final`; no se usó `bd_clinica`, no se enviaron respuestas ni se ejecutaron finalizaciones.

Resultados renderizados:

- P01: acceso disponible sin `#sidebar` ni toggler interno.
- P02: `Inicio` activo, un enlace con `aria-current="page"`.
- P05: `Antecedentes Personales No Patológicos` activo, un elemento activo.
- P07: `Antecedentes Personales Patológicos` activo, un elemento activo.
- P02/P05/P07: sin overflow horizontal en `661x627`.
- P07: click abre el menú; Enter y Space permiten alternar; al cerrar se restaura `aria-expanded="false"` y `Abrir menú principal`.

La captura visible obtenida corresponde a P07 con el menú cerrado y contenido sintético. La API CUA no permitió fijar los cuatro grupos de dimensiones solicitados ni exportar PNG persistentes. La consola DevTools tampoco estuvo expuesta; esas comprobaciones quedan `NO VERIFICADO`.

## 5. Regresiones y pendientes

No se modificó el helper compartido ni el comportamiento Doctor. La validación runtime del shell Doctor y la cobertura visual completa de P01–P11 quedan pendientes para una fase de regresión específica. Este cierre tampoco modifica ni resuelve riesgos de documentos clínicos, PDF, descargas, almacenamiento, recetas externas, COFEPRIS o efectos secundarios GET.

PHASE-04B-03 no se ejecutó.
