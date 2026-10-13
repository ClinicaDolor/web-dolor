# UI-04 — Inventario maestro y cierre visual por vista

Fecha de inventario: **2026-10-10**
Fecha objetivo del proyecto: **lunes 12 de octubre de 2026, 17:00**

Este documento es el inventario maestro de las pantallas privadas del Doctor y
del Paciente. Se construyó a partir de rutas, controladores, vistas, helpers,
componentes, CSS y evidencias documentales existentes. El documento se mantiene
como inventario y registro de cierre: cada actividad visual documenta sus
cambios y conserva separadas las pruebas no verificadas, los riesgos y las
aprobaciones expresas.

## 1. Criterio de evidencia y estados

Una ruta GET no se cuenta automáticamente como una pantalla. Se separan:

- **Pantalla:** respuesta HTML con vista PHP y composición visual propia.
- **Variante:** misma vista PHP con otra ruta o modo de alta/edición.
- **Componente:** fragmento incluido o HTML generado por helper.
- **Endpoint:** respuesta JSON, fragmento AJAX o acción POST; no recibe ID de
  pantalla.
- **Generador/descarga:** PDF o archivo; no recibe ID de pantalla.

Estados usados:

- **CERRADA:** existe aprobación visual explícita del responsable.
- **VALIDACIÓN:** existe implementación y evidencia previa de render,
  responsive o pruebas, pero no aprobación visual individual explícita.
- **PENDIENTE:** no existe una revisión visual individual suficiente para
  cerrar la pantalla.
- **EN PROCESO:** no se usa en este inventario porque no se implementan cambios.
- **BLOQUEADA:** no se usa para una pantalla visual en esta fase. Los riesgos
  documentales de seguridad sí permanecen abiertos y bloquean producción.

Las pruebas automatizadas y las capturas previas no se interpretan como
aprobación visual. En el repositorio se consideran explícitamente aprobadas las
pantallas D01 y D03–D40, conforme a las aprobaciones visuales expresas del
responsable registradas en las actualizaciones correspondientes. D02 se reabre
en VALIDACIÓN únicamente por UI-FINAL-D02-01.

### Fuentes verificadas

- app/Config/routes.php
- app/Controllers/HomeController.php
- app/Controllers/LoginController.php
- app/Controllers/ClinicaController.php
- app/Controllers/ModulosController.php
- app/Controllers/NotaSubsecuenteController.php
- app/Controllers/LaboratorioController.php
- app/Controllers/RecetaController.php
- app/Controllers/CofeprisController.php
- app/Controllers/BusquedasController.php
- app/Controllers/PdfController.php
- app/Controllers/DownloadController.php
- app/Helpers/Sidebar.php y app/Controllers/SidebarController.php
- app/views/clinica/
- app/views/paciente/
- app/views/historia-clinica-doctor/
- app/views/historia-clinica-paciente/
- app/views/resumen-clinica-doctor/
- app/views/login/
- app/views/errors/
- public/assets/css/app.css
- docs/ui02-inventory.md
- docs/ui03-report.md
- docs/visual-validation.md
- docs/design-system.md
- docs/demo-readiness.md

## 2. Estándar de encabezado institucional

El patrón que debe gobernar las vistas interiores, salvo los logins, es:

1. Contenedor blanco con degradado lavanda.
2. Borde lavanda suave, radio y sombra consistentes con el shell existente.
3. Etiqueta superior violeta en mayúsculas.
4. Título principal con jerarquía clara.
5. Descripción breve basada sólo en datos y función reales.
6. Acción principal o Volver a la derecha cuando la pantalla tenga una acción
   contextual.
7. Padding común y reorganización vertical en móvil.
8. Un único encabezado de página; no agregar un segundo encabezado dentro de
   una tabla, tarjeta o modal.

La implementación existente ya dispone de tokens, clases de shell, barra
superior, sidebar y patrones de tarjeta en public/assets/css/app.css. No se
creó un componente PHP nuevo en UI-04 porque esta actividad sólo documenta el
estándar. Antes de consolidarlo debe revisarse que no se rompan las vistas que
todavía usan footer-mvsd o body sin clases de shell.

Las pantallas de referencia oficial son D03 Directorio de Pacientes y D04
Paciente Nuevo/Editar. Se usarán como referencia de composición, no como
evidencia automática de cierre de las demás pantallas.

### 2.1 Estado vigente de aprobaciones visuales

Con fecha 2026-10-10, el responsable aprobó visualmente D01–D41. UI-FINAL-D02-01
reabre D02 en VALIDACIÓN exclusivamente para el ajuste del resumen clínico. Esta
matriz representa el estado vigente y prevalece sobre frases históricas de
pendientes de aprobación que permanezcan dentro de la evidencia de cada fila.

| Vistas | Estado vigente |
|---|---|
| D01, D03–D41 | **CERRADA** |
| D02 | **VALIDACIÓN** |

La aprobación expresa registrada en la actualización de UI-CLOSURE cubre
D01–D41, excepto D02. D02 permanece en **VALIDACIÓN** por los ajustes
UI-FINAL-D02-01 y UI-FINAL-D02-02. D41 queda **CERRADA** por la aprobación
visual expresa registrada para UI-KPI-DOCTOR-01 y UI-FIX-D41-01..04.

La aprobación visual no implica aprobación de seguridad ni autorización para
producción. Los riesgos funcionales, de seguridad y de cobertura de pruebas
descritos en las filas se conservan explícitamente.

## 3. Inventario Doctor

### 3.1 Pantallas base y gestión de pacientes

| ID | Pantalla | Ruta real y parámetros | Vista PHP | Controlador | Acciones principales | Dependencias visuales | Estado | Evidencia y pendientes |
|---|---|---|---|---|---|---|---|---|
| D01 | Login Doctor | GET /clinica/acceso; POST /clinica/login es endpoint | app/views/login/login-clinica.php | LoginController::accesoClinica | Usuario, contraseña, entrar, cambiar a portal paciente | auth-visual.php, auth-icon.php, app.css | **CERRADA** | Aprobación visual explícita. No pertenece al shell interior. |
| D02 | Dashboard Doctor | GET /clinica | app/views/clinica/index.php | HomeController::indexClinica | Resumen clínico, ver pacientes, nuevo paciente, nueva receta, menú y logout | Sidebar, topbar, footer-doctor.php, dashboard-doctor, app.css | **VALIDACIÓN** | UI-FINAL-D02-01 (2026-10-10) incorporó el panel único con seis indicadores y filtros por clínica. UI-FINAL-D02-02 (2026-10-10) refinó exclusivamente su densidad visual: miniindicadores más compactos, contraste ajustado, porcentaje visible con máximo una decimal y retiro de la leyenda decorativa `Datos autorizados`. No se modificaron cálculos, consultas, backend, shell ni acciones. PHP/JS/CSS estáticos PASS. Navegador autenticado, métricas en fixture sintético, capturas, viewports y consola NO VERIFICADOS. D02 permanece en VALIDACIÓN hasta nueva aprobación visual. |
| D03 | Directorio de Pacientes | GET /clinica/pacientes | app/views/clinica/pacientes.php | ClinicaController::pacientesIndex | Buscar, ordenar, paginar, Acciones: expediente, PIN, historia y editar | Sidebar, topbar, footer-doctor.php, Simple-DataTables, app.css | **CERRADA** | UI-CLOSE-D03 (2026-10-10): capturas y viewport real 1440×900, 1280×720, 768×1024 y 375×667; búsqueda sin resultados/restauración, dropdown por clic/Escape/teclado, cuatro destinos y Nuevo paciente verificados; consola sin errores y PHP lint PASS. El fixture sólo tiene un paciente autorizado: paginación multi-registro y estado inicial con cero pacientes quedan NO VERIFICADOS. Aprobación visual expresa confirmada en esta actualización. |
| D04 | Paciente Nuevo / Editar | GET /clinica/paciente/nuevo; GET /clinica/paciente/editar/{idPaciente} | app/views/clinica/paciente-nuevo-editar.php | ClinicaController::pacienteNuevo / pacienteEditar | 32 controles, guardar, cancelar, volver, validación | Sidebar, topbar, footer-doctor.php, doctor-patient-form-page, app.css | **CERRADA** | UI-CLOSE-D04-FIX-01 (2026-10-10): causa raíz confirmada: `novalidate` sin validación equivalente en frontend ni backend. Se incorporó validación mínima para los 14 campos ya marcados con `*`, rechazo HTTP 422 para vacío/espacios/incompleto, errores por campo accesibles y conservación de datos. Vacío y espacios: sin INSERT; incompleto: sólo campo afectado; alta válida y edición válida: 200, persistencia y autorización sintéticas PASS. UI-FIX-D04-02 (2026-10-10): Bootstrap aplicaba flecha y alerta como dos fondos del `select.is-invalid`, pero el valor calculado era `background-repeat: repeat, repeat`; se añadió sólo en `body.doctor-patient-form-page` `background-repeat: no-repeat`, conservando borde rojo, flecha, alerta única, mensaje, foco y validación. Reproducción y corrección PASS en 1440×900, 1280×720, 768×1024 y 375×667; 32 controles, 14 errores y sin overflow. Crear y Editar se contrastaron contra `routes.php`; D05 sigue siendo expediente individual y no se marcó como validado por estas pruebas. Aprobación visual expresa confirmada en esta actualización. |
| D05 | Expediente individual | GET /clinica/paciente/{idPaciente} | app/views/clinica/pacientes-detalle.php | ClinicaController::pacienteDetalle | Editar, volver, resumen, Historia Clínica, PIN, notas, laboratorio, recetas y COFEPRIS | patient-record-header, tarjetas clínicas, Historia Clínica, footer-doctor.php | **CERRADA** | Identidad de D05 verificada en la ruta real y separada de D04: la edición de paciente corresponde a `/clinica/paciente/editar/{idPaciente}` y `app/views/clinica/paciente-nuevo-editar.php`; no se renumera el inventario. UI-CLOSE-D05 (2026-10-10): en fixture sintético aislado (clínica 9101, paciente 9301) se verificaron encabezado institucional, resumen, contacto/motivo, shell Doctor, footer único, PIN, Nota subsecuente, Laboratorio, Receta médica, COFEPRIS y 9 módulos reales de Historia Clínica. Se confirmó que el total de 63% es una fila separada de los 9 módulos; `aria-valuenow` y ancho de barra coinciden en los 9 módulos y el total. Se corrigió únicamente el fondo del `.patient-record-header` para compartir el degradado institucional ya usado por D03/D04; no se modificó lógica, datos ni comportamiento clínico. Validación posterior: 1440×900, 1280×720, 768×1024 y 375×667 con `innerWidth/innerHeight` reales, sin overflow horizontal, 9 acciones por módulo, 5 tarjetas secundarias, estados AJAX `ready`, un footer y consola sin errores. Dropdown: apertura, opciones Editar módulo/Ver resumen, cierre por Escape y foco devuelto al disparador PASS. Búsquedas de Historia Clínica, notas, laboratorio, recetas y COFEPRIS con resultado vacío sintético y recuperación PASS; paginación de múltiples páginas NO VERIFICADA porque el fixture contiene 9 módulos y no supera el límite visible. `toggleSize()` alterna y restaura la distribución PASS. Acceso propio HTTP 200 y paciente sintético de otra clínica HTTP 403 PASS; se verificó navegación a edición/consulta del primer módulo. Estados vacío/error y descargas documentales no se reejecutaron en esta actividad. Capturas full-page de escritorio (1440×900) y móvil (375×667), además de captura del dropdown móvil, generadas. PHP lint de vista/controlador, sintaxis JS de loader/main/search y `git diff --check` PASS. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y responsive descritas. |
| D06 | Historia Clínica de módulos del paciente | GET /clinica/modulos/paciente/{idPaciente}; acciones de módulo: GET /clinica/{modulo}/paciente/{idPaciente} y GET /clinica/resumen/{modulo}/paciente/{idPaciente} | app/views/clinica/pacientes-modulos.php | ClinicaController::pacientesModulos; destinos de edición/resumen en ModulosController::moduloVistaDoctor / moduloVistaResumen | Presenta los 9 módulos del catálogo, porcentajes, búsqueda/ordenamiento de la tabla y acciones existentes de editar/ver resumen | Shell Doctor compartido, encabezado institucional D03/D04/D05, doctor-patient-modules-page, Simple-DataTables, app.css | **CERRADA** | UI-CLOSE-D06 (2026-10-10): se corrigió la discrepancia del inventario anterior: D06 no es una sección renderizada dentro de D05 ni usa `pacientes-detalle.php`; es la vista independiente `/clinica/modulos/paciente/{idPaciente}`, protegida por `ClinicaController::pacientesModulos`. Se incorporó el encabezado institucional, porcentajes visibles con `aria-valuenow` coincidente y la fila de total, conservando datos, permisos, rutas y persistencia. UI-CLOSE-D06-FIX-01 (2026-10-10): el título principal ahora usa el `nombre_paciente` ya autorizado por el controlador, escapado al renderizar y con fallback seguro; la descripción quedó como `Consulta y revisa el avance de su historia clínica.` y se evitó duplicar el nombre en la tarjeta inferior. Las dos columnas EDITAR/RESUMEN se sustituyeron por una columna ACCIONES y un dropdown único por módulo, reutilizando el patrón accesible de D05: nueve botones, 18 destinos originales, icono de tres puntos, `aria-expanded`, `role=menu`, navegación ArrowUp/ArrowDown, Escape con retorno de foco, cierre fuera del menú y limpieza en búsquedas/ordenamiento/redibujado. El menú usa posicionamiento fijo para no quedar recortado por tablas o contenedores responsive. En la base sintética aislada `clinica_dolor_d06_20261010_v1` se verificaron 1440x900, 1280x720, 768x1024 y 375x667 con dimensiones reales, sin overflow horizontal, 9 módulos + total, primer y último dropdown, búsqueda sin resultados y restauración, los 18 destinos, navegación a edición/resumen, acceso propio HTTP 200 y acceso cruzado entre clínicas HTTP 403. No hubo errores JavaScript; se observaron únicamente advertencias preexistentes de CKEditor externo. PHP lint, sintaxis JS y `git diff --check` PASS. Capturas antes/después y dropdown abierto en escritorio y móvil generadas. Paginación de múltiples páginas y estado de error de infraestructura NO VERIFICADOS porque el fixture cabe en una página y no se provocó una falla de servidor. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y de seguridad descritas. D07 y las vulnerabilidades críticas de autorización documental de ACT-05C-02 permanecen fuera de alcance y abiertos. |
| D07 | PIN del paciente | GET /clinica/paciente/pin/{idPaciente}; POST /clinica/paciente/insert-pin | app/views/clinica/paciente-pin.php | ClinicaController::pacientePin; generación en ClinicaController::pacienteInsertPin | Consultar PIN, generar un nuevo PIN mediante `NuevoPin(idPaciente)`, buscar/ordenar la tabla y volver al expediente | Shell Doctor compartido, encabezado institucional, doctor-patient-pin-page, tarjeta de información, tabla Simple-DataTables, footer-doctor.php | **CERRADA** | UI-CLOSE-D07 (2026-10-10): se confirmó mediante `routes.php` y código que D07 es exclusivamente `/clinica/paciente/pin/{idPaciente}` y no se renumeró. Se modernizó sólo la vista con encabezado blanco/lavanda, etiqueta ACCESO DEL PACIENTE, descripción, regreso al expediente, ficha compacta de información/contacto y tarjeta separada de credencial. Se conservaron los campos, `NuevoPin`, IDs, consulta, tabla, estados Activo/Cancelado y footer compartido; la tabla usa altura no fija y controles responsive. En la base sintética `clinica_dolor_d06_20261010_v1`, paciente 9301, se verificaron 1440x900, 1280x720, 768x1024 y 375x667 con dimensiones reales, sin overflow horizontal, dos tarjetas, un registro sintético, búsqueda sin coincidencias y restauración, botón `type=button`, regreso al expediente, un footer y consola sin errores. Acceso propio HTTP 200 y paciente sintético de otra clínica HTTP 403 PASS. La captura full-page de escritorio y la captura full-page móvil final se generaron; se corrigió también la legibilidad móvil del PIN y fechas sin cambiar datos. Paginación de múltiples páginas, tabla realmente vacía, ejecución POST de generación/validación y recuperación ante fallo de red NO VERIFICADOS para evitar escrituras adicionales en el fixture. PHP lint de vista/controlador y sintaxis JS PASS. `git diff --check` general queda NO PASS por whitespace preexistente en otras líneas de `app.css`, no por el bloque D07. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y responsive descritas. Se identificó, sin corregir por estar fuera de alcance, que el `catch` de la consulta de la vista aún usa `die()` con el mensaje de PDO y que `NuevoPin` no tiene manejo explícito de error de red; deben tratarse como actividades de seguridad/robustez independientes. Las vulnerabilidades críticas de ACT-05C-02 permanecen abiertas. |
| D08 | Nota subsecuente — alta/edición | GET `/clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}`; POST `/clinica/paciente/insert-nota-subsecuente` | `app/views/clinica/pacientes-nota-subsecuente.php` | `ClinicaController::pacienteNotaSubsecuente` (GET) y `ClinicaController::pacienteInsertNotaSubsecuente` (POST) | Capturar signos vitales, contenido de nota, próxima cita, receta asociada y laboratorio; consultar notas y detalle; guardar mediante las operaciones existentes | `doctor-patient-note-page`, `NotaSubsecuenteModel`, Quill, Simple-DataTables, AJAX de laboratorio/notas, `modal-impresion-receta.php`, `footer-doctor.php` | **CERRADA** | Identificación contrastada con `routes.php`, controlador y vista: D08 es la pantalla de alta/edición de Nota Subsecuente; D09 es el detalle independiente y no fue intervenido. Se añadió únicamente presentación scoped para `doctor-patient-note-page`: encabezado institucional blanco/lavanda con etiqueta NOTA SUBSECUENTE, título dinámico escapado, descripción y regreso al expediente; contexto del paciente; tarjetas de historial/detalle; registro clínico; controles y editores responsive; footer compartido. Se preservaron IDs, `toggleSize()`, Quill, búsquedas, Simple-DataTables, AJAX, modal, rutas, payloads, permisos y persistencia. Se corrigió sólo la compresión visual de la cuadrícula de signos vitales desde 768 px para mantener valores legibles. Fixture sintético `clinica_dolor_d06_20261010_v1`, paciente autorizado 9301 y referencia `REF-FIXTURE-A`: PASS en 1440×900 (`innerWidth/innerHeight` 1440/900, `scrollWidth/clientWidth` 1425/1425), 1280×720 (1280/720, 1265/1265), 768×1024 (768/1024, 753/753) y 375×667 (375/667, 360/360); sin overflow horizontal, encabezado visible, nombre del paciente autorizado, 43 controles, 3 editores Quill, una tabla de notas y un footer. PASS: shell, navegación a expediente, controles existentes presentes, foco de teclado visible, `toggleSize()` alterna/restaura columnas, consola sin errores, PHP lint de vista/controlador y sintaxis JS de `loader.js`, `main.js` y `search-main.js`. Autorización PASS: paciente propio HTTP 200; paciente sintético de otra clínica HTTP 403 sin filtrar encabezado ni nombre. Capturas full-page de escritorio y móvil generadas durante esta ejecución. NO VERIFICADO: alta/edición POST, HTTP 422, subida de laboratorio, impresión/PDF, estado vacío, fallo AJAX/red, paginación multi-registro y recuperación ante error de infraestructura; no se ejecutaron escrituras para mantener la prueba no destructiva. Pendiente funcional fuera de alcance: la vista conserva consulta SQL concatenada y `die()` con mensaje PDO en el bloque de notas; no se corrigió en esta actividad. Las vulnerabilidades críticas de documentos de ACT-05C-02 permanecen abiertas. No implica aprobación visual expresa ni estado CERRADA. |
| D09 | Nota subsecuente — detalle | GET `/clinica/nota-subsecuente/{idNota}` | `app/views/clinica/nota-subsecuente.php` | `NotaSubsecuenteController::notaSubsecuente` | Consultar detalle de la nota, datos del paciente, signos vitales, contenido, receta, laboratorio, edición, impresión y navegación contextual | `doctor-note-page`, `NotaSubsecuenteModel`, AJAX `/buscar/nota-subsecuente/{idNota}`, `Simple-DataTables`, `modal-impresion-receta.php`, `footer-doctor.php` | **CERRADA** | Identificación contrastada con `routes.php`, `NotaSubsecuenteController` y la vista: D09 es el detalle de una nota existente; D08 es la alta/edición en `/clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}`. Se añadió el `idPaciente` ya obtenido por el controlador al arreglo de datos para construir el regreso al expediente existente, sin nuevas consultas ni cambios de autorización. Se aplicó únicamente presentación scoped para `doctor-note-page`: encabezado institucional con etiqueta SEGUIMIENTO CLÍNICO, título escapado, descripción con paciente autorizado, regreso al expediente, toggle conservado, ficha del paciente y detalle clínico responsive. Se preservaron AJAX, edición, impresión, descargas, IDs, permisos, rutas y contenido generado por el modelo. Fixture sintético `clinica_dolor_d06_20261010_v1`, nota 9501/paciente 9301: PASS en 1440×900 (`innerWidth/innerHeight` 1440/900, `scrollWidth/clientWidth` 1425/1425), 1280×720 (1280/720, 1265/1265), 768×1024 (768/1024, 753/753) y 375×667 (375/667, 360/360); sin overflow horizontal, encabezado visible, paciente autorizado, 2 filas de laboratorio, 24 controles/enlaces, un footer y contenido AJAX cargado. PASS: regreso al expediente con URL existente, `toggleSize()` alterna/restaura columnas, foco de teclado visible, edición/impresión/descargas/PDF presentes en el DOM, consola sin errores, PHP lint de vista/controlador y sintaxis JS de `loader.js`, `main.js` y `search-main.js`. HTTP PASS: sesión propia 200 y encabezado/nombre sintéticos presentes; sin sesión 302; nota inexistente 404. Acceso cruzado entre clínicas: **NO VERIFICADO** en esta actividad porque el fixture sólo contiene la nota 9501 de la clínica 9101 y el router sintético no crea una sesión de médico de la clínica 9102; el candidato 9502 no es una nota y devolvió 404, no se convirtió artificialmente en una prueba 403. Capturas full-page de escritorio y móvil generadas. NO VERIFICADO: descarga de documentos, generación de PDF, impresión de receta, error AJAX/red, estado vacío y recuperación de infraestructura; no se ejecutaron operaciones clínicas ni descargas. Se conservan como riesgos pendientes las consultas SQL concatenadas y posibles exposiciones de errores documentadas en D08; las vulnerabilidades críticas de documentos de ACT-05C-02 permanecen abiertas. No implica aprobación visual expresa ni estado CERRADA. |
| D10 | Laboratorio del paciente | GET /clinica/laboratorio/paciente/{idPaciente}; POST /clinica/laboratorio/insert-laboratorio; GET /buscar/laboratorio/{idLaboratorio} | app/views/clinica/pacientes-laboratorio.php | ClinicaController::pacienteLaboratorio / pacienteInsertLaboratorio; detalle AJAX existente | Consultar registros de laboratorio, búsqueda Simple-DataTables, seleccionar detalle, editor Quill, archivo sintético y acción existente de alta | Shell Doctor, `doctor-patient-lab-page`, encabezado institucional D03–D09, tarjeta de contexto de paciente, tarjetas de laboratorio, `toggleSize()`, AJAX, Quill, Simple-DataTables, `footer-doctor.php` | **CERRADA** | UI-CLOSE-D10 (2026-10-10): D10 se confirmó en `routes.php` como `/clinica/laboratorio/paciente/{idPaciente}` y la vista/controlador reales; no se modificó el controlador ni el contrato de persistencia. Se modernizó exclusivamente la vista: encabezado blanco/lavanda con etiqueta de control clínico, título Laboratorio, descripción con nombre ya autorizado y escapado, regreso al expediente, toggle accesible, contexto del paciente, tarjetas de registros/detalle/nuevo laboratorio, tabla sin ancho mínimo innecesario, controles Quill y footer compartido. Se corrigió un desbordamiento visual confirmado causado por la regla posterior `doctor-clinical-view .card { height: 100% }`: D10 la sobreescribe de forma scoped para que el formulario termine antes del footer. Se conservaron IDs, `AgregarLaboratorio`, `DetalleLaboratorio`, `toggleSize()`, AJAX, SQL existente, Quill, Simple-DataTables, upload y permisos. Fixture sintético `clinica_dolor_d06_20261010_v1`, sesión Doctor sintético A, paciente 9301: PASS visual/responsive en 1440×900 (`innerWidth/innerHeight` reales 1440/900; `clientWidth/scrollWidth` 1425/1425), 1280×720 (1280/720; 1265/1265), 768×1024 (768/1024; 753/753) y 375×667 (375/667; 360/360); en las cuatro no hubo overflow horizontal, encabezado y nombre autorizados visibles, 2 registros sintéticos, detalle cargado, 1 editor Quill y 1 footer. Capturas full-page final de escritorio 1440×900 y móvil 375×667 generadas en esta ejecución. PASS: `toggleSize()` alterna/restaura columnas; selección de registro carga el detalle por GET; foco por Tab llega a controles con indicador visible; acceso propio HTTP 200, paciente de otra clínica HTTP 403, sin sesión HTTP 302 a `/clinica/acceso`, AJAX de detalle sintético HTTP 200 sin datos reales; consola sin errores. PHP lint de vista/controlador, sintaxis JS de `loader.js`, `main.js` y `search-main.js` PASS. La búsqueda se mostró y se intentó con teclado, pero el filtro de Simple-DataTables no produjo cambio observable en este runner: **NO VERIFICADO**, se conserva como pendiente funcional fuera del alcance visual. Alta POST/archivo, persistencia, descargas y fallo de red **NO VERIFICADOS** para evitar escrituras/descargas; no se ejecutó operación clínica. La vista conserva el riesgo fuera de alcance de SQL concatenada y `die()` con mensaje PDO; ACT-05C-02 mantiene abiertas las vulnerabilidades críticas de autorización documental. D09 conserva explícitamente su pendiente de prueba cruzada de página/AJAX. Permanece **CERRADA** por aprobación visual expresa; se conservan las limitaciones funcionales y de seguridad descritas. |
| D11 | Laboratorio — detalle | GET /clinica/laboratorio/{idLaboratorio} | app/views/clinica/laboratorio.php | LaboratorioController::laboratorio | Consultar registro y documento asociado; regresar a laboratorios; descarga existente | `doctor-lab-detail-page`, shell Doctor, sidebar contextual, `footer-doctor.php`, `LaboratorioModel` | **CERRADA** | UI-CLOSE-D11 (2026-10-10): identificación contrastada con `routes.php`, `LaboratorioController`, `LaboratorioModel` y la vista. Se confirmó que D11 es el detalle individual de un registro de laboratorio y que D10 es el listado/alta del paciente. Se añadió al arreglo de datos únicamente `id_paciente`, ya resuelto por el modelo y usado por la autorización existente, para construir el regreso contextual a `/clinica/laboratorio/paciente/{idPaciente}`; no se agregaron consultas ni cambios de permisos. La vista se modernizó exclusivamente con encabezado institucional blanco/lavanda, etiqueta RESULTADO DE LABORATORIO, nombre del paciente autorizado y escapado, descripción, regreso, tarjeta de contexto clínico, tarjeta de documento, descarga existente con nombre/ruta escapados, metadatos, descripción legible y footer compartido. Se conservaron campos, identificadores, ruta de descarga, sidebar, permisos y contenido clínico; CSS scoped en `doctor-lab-detail-page`. Fixture sintético `clinica_dolor_d06_20261010_v1`, registro 9511/paciente 9301: PASS en 1440×900 (`innerWidth/innerHeight` reales 1440/900; `clientWidth/scrollWidth` 1425/1425), 1280×720 (1280/720; 1265/1265), 768×1024 (768/1024; 753/753) y 375×667 (375/667; 360/360); sin overflow horizontal, encabezado, contexto, documento, un footer y acción de descarga presentes. Capturas full-page final de escritorio 1440×900 y móvil 375×667 generadas. PASS: regreso a D10 por enlace HTTP 200, foco por Tab visible, acceso propio HTTP 200, acceso cruzado con registro de otra clínica 9512 HTTP 403, sin sesión HTTP 302 a `/clinica/acceso`, consola sin errores, PHP lint, sintaxis JS y `git diff --check` de archivos D11. El registro inexistente respondió HTTP 404, pero emitió warnings/deprecations preexistentes en `LaboratorioModel.php:29-37`; el manejo seguro de ese caso queda pendiente fuera de esta actividad. La descarga real y la integridad del archivo no se ejecutaron por el riesgo documental abierto de ACT-05C-02: **NO VERIFICADO**. Estados de error visual internos y documentos inexistentes renderizados en la vista **NO VERIFICADOS**; sólo se verificó el contrato HTTP 404. No se modificó D01–D10 ni se realizaron escrituras, commits o despliegues. Permanece **CERRADA** por aprobación visual expresa; se conservan las limitaciones funcionales y documentales descritas. |
| D12 | Recetas del paciente | GET `/clinica/receta/paciente/{idPaciente}`; POST `/clinica/paciente/insert-receta`; GET `/buscar/tabla-recetas/{idPaciente}`; GET `/buscar/receta/{idReceta}`; POST `/pdf/receta/{idReceta}` | `app/views/clinica/pacientes-recetas.php` | `ClinicaController::pacienteReceta` y `ClinicaController::pacienteInsertReceta`; consultas AJAX en `BusquedasController`; PDF en `PdfController` | Consultar recetas, seleccionar detalle, buscar/ordenar la tabla, capturar una nueva receta, imprimir/PDF según los contratos existentes y volver al expediente | Shell Doctor, `doctor-patient-recipes-page`, encabezado institucional, tarjeta de contexto, Simple-DataTables, Quill, `modal-impresion-receta.php`, `footer-doctor.php` | **CERRADA** | UI-CLOSE-D12 (2026-10-10): identificación contrastada con `routes.php`, `ClinicaController` y la vista. Se modernizó exclusivamente la presentación de D12 con encabezado blanco/lavanda, etiqueta SEGUIMIENTO FARMACOLÓGICO, título Recetas, descripción con el paciente ya autorizado y escapado, regreso al expediente, ficha de paciente, tarjetas de listado/detalle/nueva receta, controles Quill y footer compartido. Se conservaron IDs, payloads, `AgregarReceta`, `DetalleReceta`, `tableRecetas`, `toggleSize()`, AJAX, consulta, modal, rutas, permisos y persistencia; no se modificaron modelos ni controladores. Fixture sintético `clinica_dolor_d06_20261010_v1`, sesión Doctor sintético A, paciente 9301 y receta 9401: PASS en 1440×900 (`innerWidth/innerHeight` 1440/900; `clientWidth/scrollWidth` 1440/1440), 1280×720 (1280/720; 1265/1265), 768×1024 (768/1024; 753/753) y 375×667 (375/667; 360/360); `scrollHeight` observado 1736, 1736, 1959 y 2562 respectivamente, sin overflow horizontal. PASS: encabezado, nombre autorizado, ficha, tabla, detalle, editor Quill, shell y footer visibles; fila sintética seleccionable carga el detalle propio; `toggleSize()` alterna y restaura columnas; botón de distribución conserva nombre accesible y foco por teclado; consola sin errores ni warnings en la ejecución. Autorización PASS: página propia HTTP 200, página de paciente de otra clínica HTTP 403, sin sesión HTTP 302 a `/clinica/acceso`, detalle propio `/buscar/receta/9401` HTTP 200 y detalle cruzado `/buscar/receta/9402` HTTP 403. Capturas full-page de 1440×900 y 375×667 generadas durante esta ejecución. NO VERIFICADO: POST de guardado, redirección tras alta, PDF/impresión, paginación multi-registro, búsqueda efectiva de Simple-DataTables, tabla vacía, estados de error/red y recuperación de infraestructura; no se ejecutaron escrituras, descargas ni operaciones clínicas. La vista conserva fuera de alcance la consulta SQL concatenada y `die()` con mensaje PDO en el bloque de listado; no se corrigió en esta actividad. D11 conserva sus warnings/deprecations ante registro inexistente y ACT-05C-02 mantiene abiertas las vulnerabilidades críticas de archivos clínicos. PHP lint de vista/controlador y sintaxis JS de `loader.js`, `main.js` y `search-main.js`: PASS. `git diff --check` global no se usa como criterio porque existen whitespace preexistentes en otras zonas de `app.css`. Permanece **CERRADA** por aprobación visual expresa; se conservan las limitaciones funcionales y de seguridad descritas. |
| D13 | Receta médica — detalle | GET `/clinica/receta/{idReceta}`; POST `/pdf/receta/{idReceta}` | `app/views/clinica/receta.php` | `RecetaController::receta`; impresión/PDF mediante `modal-impresion-receta.php` y `PdfController::pdfReceta` | Consultar datos de la receta y del paciente, editar paciente, volver al expediente, abrir el modal de impresión y generar PDF mediante la operación existente | Shell Doctor, `doctor-recipe-detail-page`, encabezado institucional, tarjetas de paciente y receta, `footer-doctor.php`, `modal-impresion-receta.php` | **CERRADA** | UI-CLOSE-D13 (2026-10-10): identificación contrastada con `routes.php`, `RecetaController`, `RecetaModel`, `PdfController` y la vista. Se modernizó exclusivamente la presentación de D13 con encabezado blanco/lavanda, etiqueta DETALLE DE RECETA, título Receta médica, descripción con el paciente ya autorizado y escapado, regreso al expediente, tarjeta de información del paciente, acción Editar paciente, tarjeta de detalle, acción Imprimir receta y footer compartido. Se conservaron rutas, IDs, parámetros, `imprimirReceta`, modal, edición, permisos, consultas, cálculos y contenido enriquecido de medicamento; no se modificaron modelos ni controladores. Fixture sintético `clinica_dolor_d06_20261010_v1`, sesión Doctor sintético A, receta 9401/paciente 9301: PASS en 1440×900 (`innerWidth/innerHeight` 1440/900; `clientWidth/scrollWidth` 1440/1440; `scrollHeight` 900), 1280×720 (1280/720; 1265/1265; `scrollHeight` 913), 768×1024 (768/1024; 753/753; `scrollHeight` 1359) y 375×667 (375/667; 360/360; `scrollHeight` 1858); sin overflow horizontal. PASS: shell, encabezado, nombre autorizado, información clínica sintética, detalle, footer, regreso HTTP 200, edición HTTP 200, modal de impresión abre/cierra, navegación por teclado con foco visible y consola sin errores ni warnings. Autorización PASS: acceso propio HTTP 200; receta de otra clínica HTTP 403 sin filtrar datos; sin sesión HTTP 302 a `/clinica/acceso`. Capturas full-page finales de 1440×900 y 375×667 generadas. NO VERIFICADO: generación real de PDF, descarga/impresión, estados vacíos o de error, fallo de red y recuperación de infraestructura; no se ejecutaron operaciones documentales. Se mantiene como riesgo fuera de alcance el contenido HTML generado por `RecetaModel::getReceta()` y las consultas SQL existentes; ACT-05C-02 conserva abiertas las vulnerabilidades críticas de archivos clínicos. PHP lint de vista/controlador y sintaxis JS de `loader.js`, `main.js` y `search-main.js`: PASS. D09, D11 y D12 cuentan con aprobación visual expresa; D01–D02 ya estaban CERRADAS por aprobación previa. D13 permanece **CERRADA** por aprobación visual expresa; se conservan las limitaciones funcionales y de seguridad descritas. |

### 3.2 Recetas y COFEPRIS generales

| ID | Pantalla | Ruta real y parámetros | Vista PHP | Controlador | Acciones principales | Dependencias visuales | Estado | Evidencia y pendientes |
|---|---|---|---|---|---|---|---|---|
| D14 | Directorio general de Recetas | GET `/clinica/recetas`; destinos `/clinica/receta/{idReceta}` y `/clinica/receta-externo/{id}` | `app/views/clinica/recetas.php` | `ClinicaController::recetasIndex`; datos internos en `RecetaModel`/`PacienteModel` y externos en `RecetaExternoModel` | Consultar recetas internas y externas, buscar, ordenar, paginar, abrir una receta, ir a Nueva receta y cambiar la distribución de las tablas | Shell Doctor, `doctor-recipes-page`, encabezado institucional, dos tarjetas de tabla, Simple-DataTables, `footer-doctor.php` | **CERRADA** | UI-CLOSE-D14 (2026-10-10): identificación contrastada con `routes.php`, `ClinicaController`, `recetas.php`, `RecetaExternoModel` y `SidebarController`. Se modernizó exclusivamente la presentación con encabezado blanco/lavanda, etiqueta DIRECTORIO DE RECETAS, descripción, acción Nueva receta, toggle accesible, dos tarjetas consistentes, tablas responsive, estados de tabla, foco de fila interna y footer compartido; se preservaron consultas, IDs, destinos, `toggleSize()`, Simple-DataTables y persistencia. Fixture sintético `clinica_dolor_d06_20261010_v1`, sesión Doctor sintético A: PASS en 1440×900 (`innerWidth/innerHeight` 1440/900; `clientWidth/scrollWidth` 1440/1440; `scrollHeight` 900), 1280×720 (1280/720; 1265/1265; `scrollHeight` 810), 768×1024 (768/1024; 753/753; `scrollHeight` 1128) y 375×667 (375/667; 360/360; `scrollHeight` 1232); sin overflow horizontal. PASS: encabezado, dos tarjetas, dos buscadores, selector de registros, dos recetas internas sintéticas, estado vacío externo, navegación de la primera fila por teclado a `/clinica/receta/9401` HTTP 200, Nueva receta HTTP 200, toggle alterna/restaura, sidebar Recetas activo, foco visible, consola sin errores, PHP lint y sintaxis JS. **FAIL de autorización confirmado y fuera del alcance visual:** la página responde HTTP 200 para el médico sintético A y muestra las recetas 9401 y 9402, incluida la de la clínica sintética B; `ClinicaController::recetasIndex` sólo ejecuta `authPermisos()` y la vista consulta `receta_medica` sin filtro de clínica. No se modificó consulta, modelo, permisos ni controlador; requiere actividad independiente y bloquea uso clínico con datos reales. Acceso sin sesión HTTP 302 a `/clinica/acceso` PASS. NO VERIFICADO: filtrado efectivo de búsqueda en este runner —los controles se renderizan pero el cambio no produjo una reducción observable—, paginación multi-página, registros externos, estados de error/red y operaciones de alta, edición, PDF o descarga; no se ejecutaron escrituras ni documentos. La vulnerabilidad crítica documental de ACT-05C-02 permanece abierta. D14 permanece **CERRADA** por aprobación visual expresa; se conservan las limitaciones funcionales y de seguridad descritas. |
| D15 | Nueva Receta | GET /clinica/recetas/nueva | app/views/clinica/recetas-nuevas.php | ClinicaController::recetaNueva | Capturar receta y enviar | doctor-new-recipe-page, formulario, footer-doctor.php | **CERRADA** | UI-CLOSE-D15 (2026-10-10): encabezado institucional, shell Doctor, tarjetas, controles, editor y acciones modernizados con CSS scoped; contratos del formulario preservados. GET sintético y PHP/JS lint PASS. UI-FIX-D15-01 (2026-10-10): se corrigió de forma scoped la altura heredada `doctor-clinical-view .card { height: 100% }` y la expansión del editor Quill; las tarjetas quedan en altura natural y el editor queda acotado a 180 px con desplazamiento interno para contenido extenso. El HTML sintético conserva un único elemento activo `Nueva Receta`; no se confirmó defecto de sidebar. Viewports controlados, consola real y guardado sintético NO VERIFICADOS por limitación del entorno; flujo externo bloqueado por riesgos SEC-D14-03. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y responsive descritas. |
| D16 | Receta externa — detalle | GET /clinica/receta-externo/{id} | app/views/clinica/receta-externo.php | ClinicaController::recetaExternoDetalle | Consultar, imprimir y editar según enlaces existentes | doctor-external-recipe-page, modal-impresion-receta.php | **CERRADA** | UI-CLOSE-D16 (2026-10-10): encabezado institucional, tarjetas de identificación/indicaciones, acciones y shell Doctor modernizados con CSS scoped; IDs, `toggleSize()`, impresión y edición conservados. PHP/JS lint y contratos estáticos PASS. Acceso autenticado al detalle, capturas en viewports controlados y consola real NO VERIFICADOS: el fixture sintético no contiene una receta externa y la ruta no tiene propiedad clínica verificable; no se ejecutaron operaciones vulnerables. Riesgo de autorización permanece abierto. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y responsive descritas. |
| D17 | Receta externa — edición | GET /clinica/receta-externo/editar/{id}; POST edit-receta-externo | app/views/clinica/receta-externo-editar.php | ClinicaController::recetaExternoEditar | Editar y enviar receta externa | doctor-external-recipe-edit-page, formulario, footer-doctor.php | **CERRADA** | UI-CLOSE-D17 (2026-10-10): encabezado institucional, tarjetas de identificación/indicaciones, campos, editor Quill y acción Guardar modernizados con CSS scoped; IDs, precarga, validaciones y endpoint preservados. Sin sesión HTTP 302, PHP/JS lint y contratos estáticos PASS. GET autenticado, POST, consola y viewports NO VERIFICADOS por falta de receta externa sintética con propiedad clínica comprobable; no se ejecutaron operaciones vulnerables. Riesgo de autorización permanece abierto. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y responsive descritas. |
| D18 | Configuración COFEPRIS | GET /clinica/cofepris; POST /cofepris/insert-folios | app/views/clinica/configuracion-cofepris.php | HomeController::indexCofepris | Consultar configuración, folios, subir archivo según vista | doctor-cofepris-config-page, footer-doctor.php | **CERRADA** | UI-CLOSE-D18 (2026-10-10): encabezado institucional, tarjetas de alta/listado, controles, estados vacíos y acción Agregar folio modernizados con CSS scoped; IDs, AJAX y funciones preservados. GET sintético y AJAX vacío HTTP 200, sin sesión HTTP 302, PHP/JS lint y contratos estáticos PASS. POST/archivo, viewports, consola real y capturas NO VERIFICADOS; Chrome headless no pudo iniciar el proceso gráfico. Folios no tienen relación de clínica en el esquema y no se modificó ese riesgo. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y responsive descritas. |
| D19 | COFEPRIS del paciente — alta/edición | GET /clinica/cofepris/paciente/{idPaciente}; GET /clinica/cofepris/paciente/{idPaciente}/editar/{idCofepris}; POST insert-cofepris | app/views/clinica/pacientes-cofepris.php | ClinicaController::pacienteCofepris | Nuevo/editar registro, tabla y acciones existentes | doctor-patient-cofepris-page, encabezado institucional, tarjetas, AJAX, footer-doctor.php | **CERRADA** | UI-CLOSE-D19 (2026-10-10): se modernizó exclusivamente la vista con encabezado contextual, resumen de paciente, historial, detalle y formulario COFEPRIS; se eliminaron estilos inline de fila/editor y se aplicó CSS scoped con tarjetas de altura natural. UI-FIX-D19-01/02 (2026-10-10): el botón de distribución conserva `toggleSize()`, `id="toggleButton"`, icono `columns`, `aria-label` y `title`, y permanece icon-only sin texto visible. UI-FIX-D19-03 (2026-10-10): el orden DOM —base del orden de tabulación— del encabezado es primero `Volver al expediente` y después el toggle. Orden DOM PASS; interacción visual, tabulación real, capturas y viewports NO VERIFICADOS por limitación del navegador. Riesgos de consultas directas, propiedad de `idCofepris` en edición y documentos permanecen abiertos. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y responsive descritas. |
| D20 | COFEPRIS — detalle | GET /clinica/cofepris/{idCofepris} | app/views/clinica/cofepris.php | CofeprisController::cofepris | Consultar registro y documento | doctor-cofepris-detail-page, footer-doctor.php | **CERRADA** | UI-CLOSE-D20 (2026-10-10): encabezado institucional, contexto autorizado del paciente, tarjetas de información y acción descriptiva de impresión modernizados con CSS scoped; valores dinámicos escapados; no se modificaron rutas, consultas ni persistencia. Lint y pruebas de autorización sintéticas PASS; viewports, consola real y capturas NO VERIFICADOS por limitación de Chrome. Riesgos de PDF/descarga de ACT-05C-02 permanecen abiertos. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y responsive descritas. |
| D21 | COFEPRIS — folios/carpeta | GET /clinica/cofepris/carpeta/{idCarpeta}; POST edit-surtido | app/views/clinica/cofepris-folios.php | CofeprisController::carpetaCofepris | Consultar folios y editar surtido | doctor-cofepris-folios-page, tabla, footer-doctor.php | **CERRADA** | UI-CLOSE-D21 (2026-10-10): D21 se confirmó como `GET /clinica/cofepris/carpeta/{idCarpeta}` atendido por `CofeprisController::carpetaCofepris`, con `POST /cofepris/edit-surtido` y vista `app/views/clinica/cofepris-folios.php`. Se aplicó exclusivamente modernización visual de la vista: shell Doctor, encabezado institucional, regreso a configuración COFEPRIS, tarjeta de folios, tabla integrada, densidad natural y responsive scoped. Se conservaron `#cofepris`, Simple-DataTables, estado local, `surtirProducto()`, enlaces PDF, IDs, rutas y contratos. PHP lint y sintaxis JavaScript PASS; navegador, capturas, viewports, navegación de tabla, POST de surtido y autorización cruzada NO VERIFICADOS. El controlador sólo valida sesión/rol y no se modificó; la propiedad de `idCarpeta` y riesgos de PDF/descarga permanecen abiertos. D21 queda **CERRADA** por aprobación visual expresa; se conservan las limitaciones funcionales y de seguridad documentadas. |
| D22 | Perfil de clínica | GET /clinica/perfil | app/views/clinica/perfil.php | ClinicaController::perfil | Consultar datos de clínica y navegación a pacientes | doctor-profile-page, footer-doctor.php | **CERRADA** | UI-CLOSE-D22 (2026-10-10): D22 se confirmó como `GET /clinica/perfil`, atendido por `ClinicaController::perfil`, que obtiene `idClinica` desde `authPermisos()`, configura el sidebar Perfil y renderiza `app/views/clinica/perfil.php`. Se modernizó exclusivamente la presentación: encabezado institucional, regreso a pacientes, tarjetas de información y horarios, valores escapados y layout responsive scoped. Se conservaron la ruta, sesión, datos existentes, `footer-doctor.php` y navegación. PHP lint y sintaxis JavaScript PASS; validación autenticada/cruzada, capturas, viewports y consola real NO VERIFICADOS por limitación del navegador. La vista conserva la consulta SQL directa heredada y no se modificaron controladores, modelos, permisos ni persistencia. D22 queda **CERRADA** por aprobación visual expresa; se conservan las limitaciones funcionales y de seguridad documentadas. |

### 3.2.0 UI-CLOSE-D21 — Modernización visual de folios COFEPRIS

D21 corresponde a `GET /clinica/cofepris/carpeta/{idCarpeta}`, atendido por
`CofeprisController::carpetaCofepris`, con la acción existente
`POST /cofepris/edit-surtido` y la vista
`app/views/clinica/cofepris-folios.php`. La vista consulta el rango de folios
de la carpeta, muestra el estado de cada folio, conserva el surtido mediante
`surtirProducto()`, los enlaces PDF y la tabla `#cofepris` con
Simple-DataTables.

Se aplicó exclusivamente una modernización visual scoped a D21:

- Shell Doctor con `footer-doctor.php` y clase de página específica.
- Encabezado institucional blanco/lavanda con etiqueta, título, descripción y
  regreso a configuración COFEPRIS.
- Tarjeta de folios con encabezado uniforme, altura natural y tabla integrada.
- Contenedor responsive para la tabla, buscador y paginación sin desbordar el
  documento.
- Se preservaron IDs, rutas, JavaScript, estado local de la tabla, acciones,
  descargas y persistencia existentes.

| Verificación | Resultado |
|---|---|
| Identificación de ruta, controlador y vista | **PASS**: contrastada con `routes.php`, `CofeprisController` y el inventario D21. |
| Shell y encabezado institucional | **PASS estático**: clases y componentes D21 incorporados; render visual real **NO VERIFICADO**. |
| Contratos `#cofepris`, `surtirProducto()` y Simple-DataTables | **PASS estático**: conservados sin cambios de ruta ni de operación. |
| PHP lint | **PASS** sobre `cofepris-folios.php`. |
| Sintaxis JavaScript | **PASS** sobre scripts externos y bloque inline de D21. |
| Acceso propio, cruzado y sin sesión | **NO VERIFICADO**: no se ejecutaron solicitudes autenticadas en esta actividad. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667 | **NO VERIFICADO**: no se dispuso de navegador reproducible para capturas. |
| POST de surtido, PDF y descargas | **NO VERIFICADO**: no se ejecutaron operaciones clínicas o documentales. |
| Propiedad de `idCarpeta` | **RIESGO ABIERTO**: el controlador valida autenticación/rol, pero esta actividad no añadió autorización por propietario ni modificó el backend. |
| `git diff --check` | **NO PASS preexistente** en `public/assets/css/app.css` líneas 4136, 4137, 4139, 4164 y 4222; el diff de la vista D21 pasa y el bloque CSS D21 no contiene esos espacios. |

D21 queda **CERRADA** por aprobación visual expresa. La modernización visual no
implica aprobación de seguridad ni autorización para producción; se mantienen
abiertos los riesgos de autorización por carpeta, PDF/descargas,
almacenamiento documental y demás limitaciones funcionales documentadas.

### 3.2.0.1 UI-CLOSE-D22 — Modernización visual del perfil de clínica

D22 se confirmó en `routes.php` como `GET /clinica/perfil`, atendido por
`ClinicaController::perfil`. El controlador exige la sesión Doctor mediante
`AuthMiddleware('clinica')->authPermisos()`, obtiene el `id_clinica` de la
identidad autenticada, configura el elemento activo `Perfil` y entrega los
datos existentes a `app/views/clinica/perfil.php`.

Se aplicó exclusivamente una modernización visual scoped a D22:

- Encabezado institucional blanco/lavanda con etiqueta `PERFIL INSTITUCIONAL`,
  nombre de la clínica, descripción y regreso a pacientes.
- Tarjeta de información institucional con dirección, teléfono, correo y sitio
  web, conservando los valores entregados por la vista.
- Tarjeta independiente de horarios, con estado vacío explícito cuando no hay
  horarios y lista escapada cuando existen.
- Shell Doctor, `footer-doctor.php`, altura natural, foco heredado y layout
  responsive sin modificar rutas, IDs, permisos ni persistencia.

| Verificación | Resultado |
|---|---|
| Identificación de ruta, controlador y vista | **PASS**: contrastada con `routes.php`, `ClinicaController::perfil` y `app/views/clinica/perfil.php`. |
| Contratos visuales D22 y shell Doctor | **PASS estático**: clases `doctor-profile-page`, `doctor-profile-hero`, tarjetas, regreso a pacientes y `footer-doctor.php` presentes. Render visual real **NO VERIFICADO**. |
| Valores dinámicos en HTML | **PASS estático**: nombre, dirección, teléfono, correo, sitio y horarios se escapan antes de renderizarse. |
| PHP lint | **PASS** sobre `app/views/clinica/perfil.php`. |
| Sintaxis JavaScript | **PASS** en scripts externos y sin bloques inline con excepciones. |
| GET sin sesión y GET autenticado | **NO VERIFICADO**: no se dispuso de un navegador/sesión sintética reproducible en esta ejecución. |
| Acceso cruzado por clínica | **NO VERIFICADO**: la vista depende del contexto de sesión y no se ejecutó una matriz autenticada. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667 | **NO VERIFICADO**: no se generaron capturas por limitación del navegador. |
| `git diff --check` | **PASS** en la vista D22; `app.css` conserva incidencias de whitespace preexistentes fuera del bloque D22. |
| SQL y autorización de datos | **RIESGO ABIERTO fuera de alcance**: la vista conserva la consulta SQL directa heredada; no se modificaron controlador, modelo ni permisos. |

D22 queda **CERRADA** por aprobación visual expresa. Esta modernización no
implica aprobación de seguridad ni autorización para producción; continúan
abiertos ACT-05C-02, riesgos documentales, recetas externas, COFEPRIS y las
limitaciones funcionales previamente documentadas.

### 3.2.1 SEC-D14-01 — Verificación de aislamiento por clínica

La verificación controlada del 2026-10-10 se ejecutó contra la base sintética `clinica_dolor_d06_20261010_v1`, con los médicos 9201/9101 y 9202/9102 y las recetas internas 9401/9301 y 9402/9302. No se usaron datos reales ni se realizaron escrituras.

| Caso | Resultado observado | Estado |
|---|---|---|
| Médico A, `GET /clinica/recetas` | HTTP 200; HTML contiene 9401 y 9402 | **FAIL confirmado** |
| Médico B, `GET /clinica/recetas` | HTTP 200; HTML contiene 9401 y 9402 | **FAIL confirmado** |
| Sin sesión, `GET /clinica/recetas` | HTTP 302 hacia `/clinica/acceso` | **PASS** |
| Médico A, `GET /buscar/tabla-recetas/9301` | HTTP 200; sólo contiene 9401 | **PASS** |
| Médico A, `GET /buscar/tabla-recetas/9302` | HTTP 403; no contiene datos clínicos | **PASS** |
| Médico A, `GET /clinica/receta/9402` | HTTP 403; no contiene datos clínicos | **PASS** |
| Médico A, `GET /buscar/tabla-recetas-externos` | HTTP 200; la tabla se renderiza vacía en el fixture | **NO VERIFICADO** para aislamiento |

Causa raíz confirmada: `ClinicaController::recetasIndex()` invoca `AuthMiddleware::authPermisos()` pero no obtiene ni pasa el `id_clinica`; además, `app/views/clinica/recetas.php` ejecuta directamente `SELECT * FROM receta_medica ORDER BY fecha_hora DESC`. Esa consulta no relaciona `receta_medica.id_paciente` con `pc_paciente.id`/`pc_paciente.id_clinica`, por lo que la respuesta HTML se genera con recetas de ambas clínicas. Las rutas AJAX por paciente y el detalle interno sí pasan por `AuthMiddleware::assertPatientAccess()`.

La corrección interna no se implementó en esta ejecución porque la restricción de `SEC-D14-01` limita los cambios a controlador/modelo y prohíbe modificar vistas, mientras que la consulta que produce la exposición está en la vista. Cambiar únicamente el controlador o `RecetaModel` no alteraría la consulta que actualmente ejecuta `recetas.php`; una intercepción global de `PDO::query()` sería insegura y quedaría fuera de alcance. Se requiere autorización explícita para mover esa consulta a un método de modelo parametrizado y hacer que la vista consuma exclusivamente el resultado filtrado, o para aplicar el cambio mínimo equivalente en la vista.

Recetas externas: el esquema real del fixture (`receta_medica_externos`) sólo contiene `id`, fecha, datos demográficos y datos de la receta; no contiene `id_paciente`, `id_clinica`, propietario ni relación verificable. El modelo `RecetaExternoModel::mostrarTablaRecetas()` consulta la tabla completa. Con cero filas sintéticas no es posible demostrar aislamiento positivo ni inventar una relación segura. Su remediación queda **BLOQUEADA / NO VERIFICADA** hasta aprobar un contrato de propiedad existente o una actividad separada de diseño de esquema y autorización.

Archivos inspeccionados: `app/Controllers/ClinicaController.php`, `app/views/clinica/recetas.php`, `app/Models/RecetaModel.php`, `app/Models/RecetaExternoModel.php`, `app/Controllers/BusquedasController.php`, `app/Controllers/RecetaController.php`, `app/Middleware/AuthMiddleware.php`, `app/Config/routes.php` y `tests/fixtures/security-fixtures.sql`. No se modificaron código, vistas, CSS, esquema, fixtures ni datos reales. D14 queda **CERRADA** visualmente por aprobación expresa; el aislamiento de la página general permanece **ABIERTO**. Las vulnerabilidades documentales críticas de ACT-05C-02 permanecen separadas y abiertas.

### 3.2.2 SEC-D14-02 — Resultado de la remediación interna

Se implementó exclusivamente el aislamiento de recetas internas. ClinicaController::recetasIndex() obtiene id_clinica desde AuthMiddleware::authPermisos(), valida que sea un entero positivo y entrega al modelo los registros autorizados. RecetaModel::getRecetasByClinica() usa una consulta PDO parametrizada que relaciona receta_medica con pc_paciente y aplica WHERE p.id_clinica = :id_clinica. app/views/clinica/recetas.php ya no ejecuta SQL directo; renderiza únicamente $data['recetas']. El orden cronológico, la tabla, Simple-DataTables, toggleSize(), enlaces y estilos permanecen sin cambios intencionales.

| Caso | Resultado después de SEC-D14-02 | Estado |
|---|---|---|
| Médico A, /clinica/recetas | HTTP 200; HTML contiene 9401 y no contiene 9402 | **PASS** |
| Médico B, /clinica/recetas | HTTP 200; HTML contiene 9402 y no contiene 9401 | **PASS** |
| Clínica inexistente 9999 | HTTP 200 con tabla interna vacía y sin datos clínicos | **PASS seguro** |
| Sin sesión | HTTP 302 hacia /clinica/acceso | **PASS** |
| AJAX propio y cruzado | 9301 propio HTTP 200; 9302 cruzado HTTP 403 | **PASS / sin regresión** |
| Detalle cruzado /clinica/receta/9402 | HTTP 403, sin datos clínicos | **PASS / sin regresión** |

Validaciones ejecutadas: PHP lint de controlador, modelo y vista; sintaxis JavaScript de loader.js, main.js y search-main.js; git diff --check; navegación visual sintética con una receta autorizada y estado externo vacío. No se modificaron CSS, rutas, esquemas, persistencia, PDF, descargas ni endpoints AJAX.

Recetas externas permanecen **ABIERTAS / NO VERIFICADAS**: RecetaExternoModel::mostrarTablaRecetas() continúa consultando la tabla completa y el esquema no ofrece propietario clínico. El endpoint externo respondió HTTP 200 con tabla vacía en el fixture, pero no es evidencia de aislamiento positivo. No se aplicó un filtro ficticio ni un bloqueo temporal sin autorización adicional.

D14 conserva estado visual **CERRADA** por aprobación expresa. La seguridad de recetas internas queda separadamente **REMEDIADA / PASS** en esta actividad; no implica que el directorio completo sea seguro mientras persista el riesgo externo. Las vulnerabilidades críticas documentales de ACT-05C-02 permanecen abiertas.

### 3.2.3 SEC-D14-03 — Contención temporal del catálogo externo

En D14 se eliminó la instanciación de RecetaExternoModel y la llamada a mostrarTablaRecetas(). ClinicaController::recetasIndex() no consulta ni entrega registros externos; la vista conserva la tarjeta institucional y muestra un estado informativo de indisponibilidad temporal. La contención es del lado del servidor y no depende de CSS o JavaScript. Las recetas internas y su filtrado por clínica permanecen sin cambios.

Resultado sintético: el HTML de /clinica/recetas contiene la tabla interna autorizada y el mensaje de catálogo externo no disponible; no contiene tableRecetasExternos ni datos externos. El endpoint de recetas externas no se invoca desde D14. Sin sesión, la página conserva HTTP 302. PHP lint, sintaxis JavaScript y git diff --check: PASS. Responsive en 1440, 1280, 768 y 375 px: **NO VERIFICADO** en esta actividad porque no hay Playwright instalado y el navegador disponible no permite fijar viewport; no se declara PASS visual nuevo.

Rutas externas independientes que permanecen fuera de esta contención:

| Ruta | Código observado | Estado |
|---|---|---|
| GET /clinica/receta-externo/{id} | ClinicaController::recetaExternoDetalle; autenticación de clínica sin propiedad verificable | **RIESGO ABIERTO** |
| GET /clinica/receta-externo/editar/{id} | ClinicaController::recetaExternoEditar; autenticación de clínica sin propiedad verificable | **RIESGO ABIERTO** |
| POST /clinica/paciente/insert-receta-externo | Inserción externa autenticada, sin propietario clínico en el esquema | **RIESGO ABIERTO** |
| POST /clinica/paciente/edit-receta-externo | Edición externa autenticada, sin propietario clínico en el esquema | **RIESGO ABIERTO** |
| GET /buscar/tabla-recetas-externos | BusquedasController::tableRecetasExternos; consulta global de RecetaExternoModel | **RIESGO ABIERTO** |
| GET /buscar/receta-externo/{id} | BusquedasController::buscarRecetaExterno; consulta por ID sin propietario verificable | **RIESGO ABIERTO** |
| POST /pdf/receta-externo/{id} | PdfController::pdfRecetaExterno; no se observó autorización de clínica en el método | **RIESGO CRÍTICO ABIERTO** |

Estas rutas no se consideran protegidas por SEC-D14-03. No se modificaron modelos externos, PDF, descargas, esquemas ni persistencia. La visualización completa de D14 permanece **CERRADA** por aprobación expresa; la contención del catálogo externo queda **PASS**, mientras la autorización de rutas externas sigue pendiente de una actividad independiente.

### 3.2.4 UI-CLOSE-D15 — Cierre visual de Nueva Receta

La identificación de D15 fue confirmada contra `app/Config/routes.php`,
`app/Controllers/ClinicaController.php` y la vista
`app/views/clinica/recetas-nuevas.php`: corresponde exclusivamente a
`GET /clinica/recetas/nueva`, atendido por
`ClinicaController::recetaNueva()`. El controlador exige sesión de clínica,
configura el sidebar Doctor con `Nueva Receta` como elemento activo y entrega
la vista sin realizar consultas adicionales de pacientes o recetas.

La modernización se limitó a la presentación de D15. Se integró el shell
Doctor existente, `footer-doctor.php`, el encabezado institucional de recetas,
la tarjeta de selección de paciente, la tarjeta de información clínica, los
controles de formulario, el editor Quill y las acciones principales mediante
estilos scoped a `body.doctor-new-recipe-page` en `public/assets/css/app.css`.
Se retiraron estilos inline globales de la vista que afectaban `.row`,
`.editor` y botones de SweetAlert. Se conservaron IDs, funciones JavaScript,
eventos inline, destinos, payloads, Quill, búsqueda de paciente, selección de
paciente y los dos flujos existentes de envío. No se modificaron rutas,
controladores, modelos, consultas, permisos, persistencia ni la contención de
recetas externas de D14.

La revisión UI-FIX-D15-01 confirmó dos causas de la regresión visual. El shell
Doctor aplica posteriormente `body.app-shell-body.doctor-clinical-view .card
{ height: 100% }`, que podía estirar las tarjetas de selección y formulario;
además, Quill define `height: 100%` para `.ql-container` y la vista conserva
una opción `height: '500px'` en su configuración, aunque esa opción no forma
parte del contrato visual documentado de Quill 1.3.6. Se añadió al final de
`public/assets/css/app.css` un bloque exclusivamente scoped a
`body.app-shell-body.doctor-clinical-view.doctor-new-recipe-page`: las tarjetas
y su contenedor se alinean al inicio con `height: auto`, se elimina la altura
mínima heredada y el contenedor Quill se limita a 180 px con scroll interno
para texto extenso. No se modificaron la vista, la inicialización Quill, IDs,
eventos, AJAX, payloads, endpoints ni persistencia. La sección externa sigue
controlada por la lógica existente y no se consulta durante esta corrección.

| Verificación | Resultado |
|---|---|
| Resolución de ruta y controlador | **PASS**: `/clinica/recetas/nueva` y `ClinicaController::recetaNueva()` coinciden con el inventario. |
| Renderizado de lectura con sesión sintética Doctor A | **PASS**: HTTP 200; shell, encabezado, selector de tipo, búsqueda, diagnóstico, signos vitales, editor y acciones presentes. |
| Sin sesión | **PASS**: el middleware conserva la redirección al acceso de clínica. |
| Diagnóstico estructural de alturas y editor | **PASS estático**: se confirmó la regla posterior `doctor-clinical-view .card { height: 100% }`, la regla Quill `.ql-container { height: 100% }` y la opción `height: '500px'` en la inicialización; la corrección quedó delimitada a D15 en CSS. Cálculo de estilos computados en navegador **NO VERIFICADO** por limitación del entorno. |
| Sidebar activo | **PASS**: el HTML sintético contiene un único `sidebar-item active`, corresponde a `Nueva Receta` y expone `aria-current="page"`; no se modificó el componente compartido. |
| Corrección de tarjetas y editor | **PASS estático**: tarjetas y columna D15 tienen `height: auto`/`min-height: 0`, el editor no conserva altura artificial y `.ql-container` queda en 180 px con scroll interno. Evidencia visual antes/después **NO VERIFICADA** por falta de navegador con viewport reproducible. |
| Preservación de contratos del formulario | **PASS**: se conservaron `tipoRegistrado`, `tipoExterno`, `buscarPaciente`, `Diagnostico`, `TA`, `FC`, `SpO2`, `Temperatura`, `snow`, `btnAgregarRegistrado`, `btnAgregarExterno`, `seleccionarTipo()`, `buscarPaciente()`, `agregarRecetaRegistrado()` y `agregarRecetaExterno()`. |
| PHP lint | **PASS** para la vista D15 y el controlador inspeccionado. |
| Sintaxis JavaScript inline y dependencias | **NO VERIFICADO** en navegador con viewport/consola controlados; se preservó el código existente y se inspeccionaron los consumidores visuales. |
| Responsive 1440×900, 1280×720, 768×1024 y 375×667 | **NO VERIFICADO**: el entorno disponible no tiene Playwright ni un navegador con setter de viewport reproducible. No se declara PASS visual por esta limitación. |
| Guardado de receta interna | **NO VERIFICADO**: no se ejecutaron escrituras clínicas, aunque el fixture sintético y el endpoint existente deben validarse en una actividad de prueba autorizada. |
| Guardado/flujo de receta externa | **NO VERIFICADO / BLOQUEADO**: no se ejecutó porque las rutas independientes de recetas externas continúan con riesgos de autorización documentados en SEC-D14-03. |

El estado de D15 permanece **CERRADA** por aprobación visual expresa. La falta de
capturas de viewport controlado, consola de navegador y guardado sintético es
una limitación de esta ejecución, no evidencia de funcionamiento. D14 y sus
riesgos de recetas externas no fueron modificados. Las vulnerabilidades
documentales críticas de ACT-05C-02 permanecen abiertas.

### 3.2.5 UI-CLOSE-D16 — Cierre visual del detalle de receta externa

La identidad de D16 fue confirmada contra el inventario maestro, `app/Config/routes.php`,
`app/Controllers/ClinicaController.php`, `app/Models/RecetaExternoModel.php` y
`app/views/clinica/receta-externo.php`. Corresponde a
`GET /clinica/receta-externo/{id}`, atendido por
`ClinicaController::recetaExternoDetalle($id)`. La pantalla consulta la receta
externa por identificador, presenta datos del paciente externo, fecha/hora,
diagnóstico, signos vitales y medicamento, y conserva enlaces de edición e
impresión mediante los contratos existentes.

Se modernizó exclusivamente la vista y su CSS scoped a
`body.doctor-external-recipe-page` en `public/assets/css/app.css`:

- Encabezado institucional blanco/lavanda con etiqueta `RECETA EXTERNA`.
- Tarjeta de identificación del paciente con acción `Editar`.
- Tarjeta de indicaciones con acción `Imprimir`, metadatos, signos vitales y medicamento.
- Botón `toggleSize()` conservado con nombre accesible.
- Espaciado, tipografía, bordes, foco, botones y distribución responsive unificados.
- Contenido clínico largo con wrapping y sin alturas fijas nuevas.

No se modificaron controlador, modelo, rutas, consultas, permisos, persistencia,
PDF, descargas ni la vista D17. Los identificadores existentes fueron
preservados: `toggleButton`, `toggleIcon`, `extNombreCompleto`, `extEdadTexto`,
`extSexoTexto` y `extFechaNacimientoTexto`; también se conservaron
`toggleSize()`, `imprimirReceta(..., 'externo')` y el destino de edición.

| Verificación | Resultado |
|---|---|
| Identificación de ruta, vista y controlador | **PASS**: coincidencia entre inventario, `routes.php`, controlador y vista. |
| Sin sesión, `GET /clinica/receta-externo/{id}` | **PASS** en router sintético: HTTP 302 hacia `/clinica/acceso`. |
| Acceso autenticado al detalle | **NO VERIFICADO**: `receta_medica_externos` no contiene un registro sintético utilizable; no se creó uno ni se accedió a datos reales por el riesgo de autorización confirmado. |
| Preservación de IDs, destinos y funciones | **PASS** mediante comparación estática con la vista original. |
| PHP lint | **PASS** para vista D16, controlador y modelo inspeccionados. |
| Sintaxis JavaScript | **PASS** para `loader.js`, `main.js` y `search-main.js`; D16 no añade JavaScript inline. |
| Integridad CSS | **PASS**: llaves balanceadas 1700/1700; estilos D16 están scoped. |
| Consola JavaScript y acciones reales | **NO VERIFICADO**: no se abrió un detalle autenticado ni se ejecutaron impresión, PDF o descarga. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667 | **NO VERIFICADO**: Chrome local está instalado, pero no existe un registro externo sintético seguro para renderizar D16 y no se ejecutó la ruta vulnerable para fabricar evidencia. |
| Capturas full-page | **NO GENERADAS** por la limitación anterior. |
| `git diff --check` | **FAIL preexistente** en líneas históricas de `public/assets/css/app.css` (3368, 3369, 3371, 3396 y 3454); no se reformatearon ni alteraron por D16. |

Riesgo de seguridad conservado explícitamente: el controlador sólo aplica
`AuthMiddleware::authPermisos()` y `RecetaExternoModel::receta()` consulta por
ID sin una relación verificable con clínica o usuario. Las rutas independientes
de edición, búsqueda, PDF y descarga externa continúan fuera de alcance y no se
consideran protegidas por este cierre visual. El detalle de D16 permanece
**CERRADA** por aprobación visual expresa; las limitaciones funcionales y de seguridad se conservan.

### 3.2.6 UI-CLOSE-D17 — Cierre visual controlado de edición de receta externa

La identidad de D17 fue contrastada con el inventario, `app/Config/routes.php`,
`app/Controllers/ClinicaController.php`, `app/Models/RecetaExternoModel.php` y
`app/views/clinica/receta-externo-editar.php`. Corresponde a la vista
`GET /clinica/receta-externo/editar/{id}`, atendida por
`ClinicaController::recetaExternoEditar($id)`, con envío mediante
`POST /clinica/paciente/edit-receta-externo`.

La modernización se limitó a la presentación de D17:

- Encabezado institucional `RECETA EXTERNA` / `Editar receta`.
- Tarjeta de identificación del paciente externo.
- Tarjeta de indicaciones con fecha, diagnóstico, signos vitales y medicamento.
- Campos y etiquetas alineados con el sistema Doctor.
- Editor Quill, foco violeta, botones y estados visuales consistentes.
- Acción `Guardar cambios` conservada con `GuardarRecetaExterno()`.
- Estilos inline globales eliminados y sustituidos por CSS scoped a
  `body.doctor-external-recipe-edit-page`.

No se modificaron rutas, controlador, modelo, consultas, permisos, validaciones,
payload, persistencia, PDF, descargas ni D16. Se conservaron los IDs
`extNombre`, `extApellidoPaterno`, `extApellidoMaterno`, `extEdad`, `extSexoM`,
`extSexoF`, `extFechaNacimiento`, `Diagnostico`, `TA`, `FC`, `SpO2`,
`Temperatura` y `snow`, además de `limpiarBorde()`, `GuardarRecetaExterno()` y
la carga inicial del contenido Quill.

| Verificación | Resultado |
|---|---|
| Identificación de ruta, vista y controlador | **PASS**: coincidencia confirmada en inventario y código. |
| Sin sesión, `GET /clinica/receta-externo/editar/{id}` | **PASS** en router sintético: HTTP 302 hacia `/clinica/acceso`. |
| Preservación de IDs, precarga y contratos JS | **PASS** mediante comparación estática con la vista original. |
| PHP lint | **PASS** para vista D17, controlador y modelo inspeccionados. |
| Sintaxis JavaScript | **PASS** para `loader.js`, `main.js` y `search-main.js`; el bloque inline existente se conserva. |
| Integridad CSS | **PASS**: llaves balanceadas 1739/1739; estilos D17 están scoped. |
| GET autenticado | **NO VERIFICADO**: `receta_medica_externos` no contiene un registro sintético con propietario clínico verificable; no se fabricó la relación ni se accedió a datos reales. |
| POST de edición | **NO VERIFICADO / BLOQUEADO**: no se ejecutó escritura externa ni se probó el endpoint cuya autorización permanece abierta. |
| Consola, foco y acciones reales | **NO VERIFICADO**: no se abrió un registro autenticado en navegador. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667 | **NO VERIFICADO**: Chrome está instalado, pero no existe un registro externo seguro para renderizar D17; Playwright no está instalado. |
| Capturas full-page | **NO GENERADAS** por la limitación de autorización/fixture. |
| `git diff --check` | **FAIL preexistente** en líneas históricas de `public/assets/css/app.css`; no se reformatearon archivos ajenos al alcance. |

El riesgo de seguridad permanece explícito: `recetaExternoEditar()` sólo aplica
autenticación de clínica y `RecetaExternoModel` identifica la receta por ID,
sin relación verificable con clínica o usuario. Las rutas externas de detalle,
edición, búsqueda, PDF y descarga siguen fuera de alcance y no se consideran
protegidas por este cierre visual. D17 queda **CERRADA** por aprobación visual
expresa; las limitaciones funcionales y de seguridad se conservan.

### 3.2.7 UI-CLOSE-D18 — Cierre visual de configuración COFEPRIS

La identidad de D18 fue confirmada contra el inventario, `app/Config/routes.php`,
`app/Controllers/HomeController.php` y
`app/views/clinica/configuracion-cofepris.php`. Corresponde a
`GET /clinica/cofepris`, atendido por `HomeController::indexCofepris()`, con
consulta AJAX de lectura en `/buscar/tabla-cofepris-folios` y alta mediante
`POST /clinica/cofepris/insert-folios`.

La vista se modernizó exclusivamente en su presentación:

- Encabezado institucional `CONTROL COFEPRIS` / `Configuración COFEPRIS`.
- Tarjeta de alta de folios y tarjeta de folios disponibles.
- Etiquetas, controles, foco, botón principal y estados vacíos consistentes.
- Shell Doctor y `footer-doctor.php` conservados.
- Layout responsive con columnas amplias en escritorio y una columna en móvil/tableta.
- CSS scoped a `body.doctor-cofepris-config-page`.

Se conservaron los IDs `Folio`, `Archivo`, `mensaje` y `conteCofepris`, las
funciones `tableCofeprisFolios()` y `AgregarCofepris()`, los endpoints AJAX,
el cálculo de folios activos y el flujo existente de carga. No se modificaron
modelos, controladores, rutas, esquema, persistencia, archivos ni PDF.

| Verificación | Resultado |
|---|---|
| Identificación de ruta, vista y controlador | **PASS**: coincidencia confirmada con el inventario y el código. |
| GET autenticado sintético, clínica 9101 | **PASS**: HTTP 200; encabezado, controles, contenedor de resultados y sin datos clínicos expuestos. |
| AJAX `/buscar/tabla-cofepris-folios` | **PASS**: HTTP 200, respuesta vacía con tabla sintética y sin excepciones/SQLSTATE. |
| Sin sesión | **PASS**: HTTP 302 hacia `/clinica/acceso`. |
| Preservación de IDs y contratos | **PASS** mediante comparación estática con la vista original. |
| PHP lint | **PASS** para vista D18, controlador y modelo inspeccionados. |
| Sintaxis JavaScript | **PASS** para `loader.js`, `main.js` y `search-main.js`; no se alteró la lógica inline. |
| Integridad CSS | **PASS**: llaves balanceadas 1772/1772; estilos D18 están scoped. |
| POST de alta y archivo | **NO VERIFICADO**: no se ejecutaron escrituras ni cargas de archivos. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667 | **NO VERIFICADO**: Chrome está instalado, pero el modo headless falló al iniciar el proceso gráfico en este entorno. |
| Capturas full-page | **NO GENERADAS**: el mecanismo disponible fue intentado sin instalar dependencias y falló antes de renderizar. |
| Consola real, teclado y foco en navegador | **NO VERIFICADO** por la misma limitación del navegador. |
| `git diff --check` | **FAIL preexistente** en líneas históricas de `public/assets/css/app.css`; no se reformatearon archivos ajenos al alcance. |

Limitación de datos: `cofepris_folio` no contiene `id_clinica` ni otra relación
de propiedad en el fixture/esquema inspeccionado. D18 se validó únicamente con
la tabla vacía; no se inventó aislamiento por clínica ni se declaró PASS de
autorización sobre folios con datos. El POST, la carga documental y los riesgos
de almacenamiento/PDF permanecen pendientes. D18 queda **CERRADA** por
aprobación visual expresa; las limitaciones funcionales y de seguridad se conservan.

### 3.2.8 UI-CLOSE-D19 — Cierre visual de COFEPRIS del paciente

La identidad de D19 fue confirmada contra `docs/UI_DESIGN_CLOSURE_MASTER.md`,
`app/Config/routes.php`, `ClinicaController::pacienteCofepris()` y
`app/views/clinica/pacientes-cofepris.php`. La vista compartida atiende la
consulta y el alta mediante `GET /clinica/cofepris/paciente/{idPaciente}` y la
edición mediante `GET /clinica/cofepris/paciente/{idPaciente}/editar/{idCofepris}`;
la escritura existente continúa en `POST /clinica/paciente/insert-cofepris`.

La modernización se limitó a la vista y a CSS scoped en
`public/assets/css/app.css`:

- Encabezado institucional `CONTROL COFEPRIS`, contexto del paciente autorizado
  y regreso al expediente.
- Resumen de paciente, historial, detalle y formulario con jerarquía visual
  consistente con D03–D18.
- Tarjetas con altura natural para neutralizar la regla compartida
  `doctor-clinical-view .card { height: 100% }`.
- Eliminación de los estilos inline de `.editor`, `.row` y `.resizable` que
  podían producir alturas y comportamientos inconsistentes.
- Tabla, detalle AJAX, formulario, foco, botones y distribución responsive
  delimitados a `doctor-patient-cofepris-page`.
- El botón de distribución se comparó con D16, D10 y D12. D19 realiza la
  misma alternancia mediante `toggleSize()`, por lo que conserva el icono
  `columns`, `id="toggleButton"` y la acción existente, pero ahora muestra
  `Cambiar distribución` en escritorio y móvil. El `aria-label` y el `title`
  quedaron como `Cambiar distribución de la pantalla`; el CSS responsive no
  oculta el texto.
- Se conservaron IDs, funciones `tableCofepris()`, `DetalleCofepris()` y
  `AgregarCofepris()`, endpoints AJAX, `toggleSize()`, parámetros, campos y
  persistencia existente. No se modificaron modelos, controladores, rutas,
  consultas ni PDF.

El diagnóstico confirmó que el controlador ejecuta `assertPatientAccess()` para
el paciente de la ruta antes de renderizar. El fixture sintético permite
comprobar `cofepris` 9503/paciente 9301 de clínica 9101 y 9506/paciente
9302 de clínica 9102. No se ejecutaron POST, edición, generación PDF ni
descargas. La vista conserva riesgos fuera del alcance visual: consulta SQL
directa y excepción expuesta en el renderizado, `CofeprisModel::mostrarTablaCofepris()`
con consulta concatenada, y ausencia de una comprobación explícita de que
`idCofepris` pertenezca al `idPaciente` de la URL antes de cargar el modelo de
edición. Estos riesgos requieren actividades de seguridad independientes.

| Verificación | Resultado |
|---|---|
| Identificación de rutas, vista y controlador | **PASS**: coincidencia confirmada con `routes.php`, `ClinicaController` y la vista real. |
| GET propio, Doctor sintético A / paciente 9301 | **PASS**: HTTP 200; encabezado, contexto, historial, detalle, formulario y shell Doctor renderizados; contiene el registro sintético 9503. |
| GET propio, Doctor sintético B / paciente 9302 | **PASS**: HTTP 200; contiene el registro sintético 9506. |
| Acceso cruzado Doctor A / paciente 9302 | **PASS**: HTTP 403; no se ejecutó una operación clínica. |
| Sin sesión | **PASS**: HTTP 302 al acceso de clínica. |
| Tabla AJAX propia / cruzada | **PASS**: `/buscar/tabla-cofepris/9301` HTTP 200 para A y `/buscar/tabla-cofepris/9302` HTTP 403 para A. |
| Detalle AJAX propio / cruzado | **PASS**: `/buscar/cofepris/9503` HTTP 200 para A y `/buscar/cofepris/9506` HTTP 403 para A. |
| Consistencia del botón de distribución | **PASS estático**: D16 usa etiqueta visible `Cambiar distribución`; D10/D12 usan icono `columns` con nombre accesible equivalente. D19 conserva el mismo icono, `toggleSize()`, etiqueta visible, `title` y `aria-label`; no se modificaron las otras vistas. Click y alternancia en navegador **NO VERIFICADOS**. |
| Sidebar activo | **PASS estático/renderizado**: un único `sidebar-item active`, `Paciente Cofepris`, con `aria-current="page"`. |
| Preservación de contratos visuales y JavaScript | **PASS estático**: IDs y funciones existentes conservados; no se modificó JavaScript inline. |
| PHP lint | **PASS** para vista D19, controlador, `BusquedasController` y `CofeprisModel`. |
| Sintaxis JavaScript | **PASS** para scripts compartidos e inline de D19. |
| Integridad CSS | **PASS**: llaves balanceadas 1835/1835; bloque D19 sin whitespace final y scoped. |
| `git diff --check` | **FAIL preexistente** en `public/assets/css/app.css` líneas 4047, 4048, 4050, 4075 y 4133; fuera del bloque D19, no se reformateó código ajeno. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667 | **NO VERIFICADO**: Chrome con perfil temporal terminó con código 13 y `Multiple targets are not supported in headless mode` antes de renderizar. |
| Capturas full-page, consola real, teclado y foco | **NO VERIFICADO** por la limitación del navegador. |
| POST de alta/edición, PDF y descargas | **NO VERIFICADO**: se omitieron por tratarse de escrituras y operaciones documentales. |

D19 permanece **CERRADA** por aprobación visual expresa. Los riesgos críticos
documentales de ACT-05C-02, la propiedad
de folios COFEPRIS y las rutas PDF/descarga continúan abiertos.

### 3.2.9 UI-CLOSE-D20 — Modernización visual del detalle COFEPRIS

La identidad de D20 fue confirmada contra `docs/UI_DESIGN_CLOSURE_MASTER.md`,
`app/Config/routes.php`, `CofeprisController::cofepris()` y
`app/views/clinica/cofepris.php`. La ruta real es
`GET /clinica/cofepris/{idCofepris}`. El controlador autentica el portal
Doctor, carga el registro por su identificador y ejecuta
`assertPatientAccess()` antes de cargar los datos del paciente y renderizar la
vista. El shell utiliza el sidebar contextual `clinica-cofepris`, con
`Cofepris` como opción activa y el `footer-doctor.php` compartido.

La modernización se limitó a la vista D20 y a un bloque CSS scoped en
`public/assets/css/app.css`:

- Se sustituyó el título genérico por un encabezado institucional con etiqueta
  `DETALLE COFEPRIS`, título `Registro COFEPRIS`, descripción contextual y
  nombre del paciente recibido por el controlador.
- La acción existente `/pdf/cofepris/{idCofepris}` se conservó sin ejecutar,
  pero ahora se presenta como `Imprimir registro`, con icono, `title`,
  `aria-label` y `rel="noopener"`.
- La información del paciente y del registro se reorganizó en dos tarjetas de
  altura natural, con metadatos, folio, contacto y campos clínicos existentes.
- Se neutralizó únicamente para D20 la regla global heredada
  `doctor-clinical-view .card { height: 100% }`, que provocaba alturas
  artificiales en esta vista de lectura. No se modificaron otras pantallas.
- No existen en D20 tabla, editor Quill ni botón `toggleSize()`; por ello no se
  inventaron ni se alteraron dichos comportamientos. La vista conserva la
  lectura y la acción documental ya existentes.
- Los valores dinámicos renderizados en la nueva estructura usan escape HTML.
  No se agregaron consultas, modelos, rutas, campos ni operaciones de escritura.

La comprobación sintética utilizó los registros de COFEPRIS 9503/9506 y sus
pacientes asociados de clínicas independientes. No se ejecutó el enlace PDF ni
ninguna operación clínica o documental.

| Verificación | Resultado |
|---|---|
| Identificación de ruta, controlador, vista, acciones y shell | **PASS**: coincidencia confirmada por código. |
| GET propio Doctor sintético A / registro 9503 | **PASS**: HTTP 200; shell, encabezado, paciente, folio y campos del registro renderizados. |
| GET propio Doctor sintético B / registro 9506 | **PASS**: HTTP 200. |
| GET cruzado Doctor A / registro 9506 | **PASS**: HTTP 403; no se expuso el registro de otra clínica. |
| GET sin sesión | **PASS**: HTTP 302 al acceso de clínica. |
| Acción PDF/descarga | **NO VERIFICADO**: se preservó el enlace, pero no se ejecutó por el riesgo documental ACT-05C-02. |
| Tabla, paginación, editor Quill y `toggleSize()` | **NO APLICA**: no existen en la vista D20 inspeccionada. |
| PHP lint y contratos estáticos de la vista | **PASS**. |
| Sintaxis de scripts compartidos e inline | **PASS**. |
| Integridad CSS scoped | **PASS**: llaves balanceadas y selector delimitado a D20. |
| `git diff --check` | **FAIL preexistente** en líneas históricas de `public/assets/css/app.css`; no corresponde al bloque D20. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667 | **NO VERIFICADO**: Chrome aislado terminó con código 13 y `Multiple targets are not supported in headless mode` antes de renderizar. |
| Capturas full-page, consola real, teclado y foco | **NO VERIFICADO** por la limitación del navegador. |

D20 queda **CERRADA** por aprobación visual expresa. Continúan
abiertos los riesgos de PDF/descarga y almacenamiento documental de ACT-05C-02;
esta actividad no los corrige.

### 3.2.10 UI-FIX-TOGGLE-01 — Consistencia de botones de distribución

La auditoría del 2026-10-10 localizó ocho vistas Doctor con el contrato
`toggleSize()` + `id="toggleButton"` + icono Feather `columns`. La función
existente en `public/assets/js/loader.js` no se modificó: continúa alternando
las columnas `.resizable` y sus órdenes originales.

| Vista | Ruta real | Estado previo comprobado | Corrección / resultado |
|---|---|---|---|
| D05 — Expediente individual | `GET /clinica/paciente/{idPaciente}` | Icono sin texto; aria/title específicos del expediente. | Se agregó `Cambiar distribución` visible y se normalizaron aria/title. |
| D08 — Nota subsecuente alta/edición | `GET /clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}` | Icono sin texto. | Se agregó etiqueta visible y se conservaron IDs/evento. |
| D09 — Detalle de nota subsecuente | `GET /clinica/nota-subsecuente/{idNota}` | Icono sin texto. | Se agregó etiqueta visible y se conservaron IDs/evento. |
| D10 — Laboratorio del paciente | `GET /clinica/laboratorio/paciente/{idPaciente}` | Icono sin texto. | Se agregó etiqueta visible y se conservaron IDs/evento. |
| D12 — Recetas del paciente | `GET /clinica/receta/paciente/{idPaciente}` | Icono sin texto; era el defecto confirmado por captura. | Se agregó etiqueta visible y se conservaron ubicación, ID y `toggleSize()`. |
| D14 — Directorio de recetas | `GET /clinica/recetas` | Icono sin texto; aria/title referían sólo a tablas. | Se agregó etiqueta visible y se normalizaron aria/title. |
| D16 — Detalle de receta externa | `GET /clinica/receta-externo/{id}` | Texto visible correcto; aria/title no eran uniformes. | Se conservaron icono y etiqueta; se normalizaron aria/title. |
| D19 — COFEPRIS del paciente | `GET /clinica/cofepris/paciente/{idPaciente}` | El código actual ya contenía la etiqueta estándar. | HTML servido sintético confirmó etiqueta, icono y atributos correctos; no se hizo cambio redundante. |

La corrección visual se limitó a las siete vistas con ausencia o inconsistencia
comprobada y a CSS scoped en `public/assets/css/app.css`. Las reglas anteriores
fijaban algunos toggles en 42/44 px, por lo que el texto podía no caber aunque
se agregara al HTML. El override UI-FIX-TOGGLE-01 usa ancho automático, conserva
el foco visible y permite que el grupo de acciones se envuelva en móvil; no
oculta la etiqueta ni modifica otras partes de las pantallas.

| Verificación | Resultado |
|---|---|
| Inventario de ocho vistas y rutas | **PASS**: contrastado con `routes.php` y el inventario D05/D08/D09/D10/D12/D14/D16/D19. |
| Icono `columns`, texto visible y `toggleSize()` | **PASS estático** en las ocho vistas; cada una conserva un único `toggleButton`. |
| HTML servido D12 | **PASS**: HTTP 200; contiene `Cambiar distribución`, aria/title estándar e icono `columns`. |
| HTML servido D19 | **PASS**: HTTP 200; contiene la etiqueta correcta; no aparece la etiqueta antigua `Distribución`. |
| Cambio real de distribución mediante interacción | **NO VERIFICADO**: no hay navegador reproducible disponible en esta ejecución. La presencia del código no se tomó como PASS de interacción. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667 | **NO VERIFICADO** por limitación del navegador. |
| PHP lint de vistas modificadas | **PASS**. |
| Sintaxis JavaScript compartida | **PASS**; no se modificó `toggleSize()`. |
| `git diff --check` | **FAIL preexistente** en whitespace histórico de `public/assets/css/app.css`, fuera del bloque UI-FIX-TOGGLE-01. |

D12 y D19 permanecen **CERRADAS** por aprobación visual expresa. No se
modificaron controladores, modelos, rutas, consultas, persistencia ni permisos.

### 3.2.11 UI-FIX-D19-02 — Corrección definitiva de D19

Esta corrección posterior sustituye únicamente para D19 el resultado anterior
de UI-FIX-TOGGLE-01: la referencia visual aprobada utiliza el botón compacto
con icono `columns` y **sin texto visible**. D12 y las demás vistas no fueron
modificadas en esta actividad.

En `app/views/clinica/pacientes-cofepris.php` se eliminó el `<span>` del
toggle, conservando `id="toggleButton"`, `toggleSize()`, el icono, el
`aria-label` y el `title` estándar. En `public/assets/css/app.css` se dejó el
botón en 44×44 px con el mismo padding, borde, radio, foco y hover del patrón
compacto; la regla está limitada a `doctor-patient-cofepris-page`.

La tarjeta `Registros COFEPRIS` también se ajustó exclusivamente en D19:

- Se eliminó el eyebrow `HISTORIAL` para conservar un solo encabezado.
- Se retiró el recuadro decorativo adicional del contenedor de tabla.
- Se alinearon toolbar, buscador y tabla con padding natural.
- `Simple-DataTables` conserva búsqueda, ordenamiento y paginación, pero ya no
  fuerza `fixedHeight` cuando hay registros.
- Cuando no hay filas, se muestra un estado accesible y no se presenta una
  tabla vacía con scrollbar artificial.

No se modificaron `CofeprisModel`, `BusquedasController`, rutas, consultas,
persistencia ni operaciones clínicas.

| Verificación | Resultado |
|---|---|
| D19 HTML servido con botón sólo icono | **PASS**: HTTP 200; no contiene el `<span>` de texto y conserva icono, ID, aria-label y title. |
| `toggleSize()` y funciones `tableCofepris()` / `DetalleCofepris()` | **PASS estático**: contratos y funciones conservados. Interacción real **NO VERIFICADA** por limitación del navegador. |
| Historial con registros, búsqueda, ordenamiento y paginación | **NO VERIFICADO** en navegador; configuración y selectores preservados. |
| Estado vacío sin scrollbar artificial | **PASS estático**: `fixedHeight:false`, contenedor natural y estado accesible condicionado a cero filas. Render visual real **NO VERIFICADO**. |
| PHP lint y sintaxis JavaScript | **PASS**. |
| `git diff --check` | **FAIL preexistente** en whitespace histórico de `public/assets/css/app.css`, fuera del bloque D19. |
| Viewports y comparación visual D12/D19 | **NO VERIFICADO**: Chrome no pudo proporcionar una sesión reproducible de captura. |

D19 permanece **CERRADA** por aprobación visual expresa; se conservan las
limitaciones funcionales y responsive descritas.

### 3.2.12 UI-FIX-D19-03 — Orden de acciones del encabezado

Se corrigió exclusivamente el orden real de los dos controles del encabezado de D19 en `app/views/clinica/pacientes-cofepris.php`:

- Primero aparece el enlace `Volver al expediente`.
- Inmediatamente después aparece el botón icon-only `toggleButton` con el icono `columns`.
- Se conservaron `toggleSize()`, `aria-label`, `title`, el tamaño compacto y el estilo existente.
- No se utilizaron `order` ni `row-reverse`; el orden visual y la base del orden de teclado dependen ahora del orden DOM requerido. La interacción de teclado real quedó NO VERIFICADA por la limitación del navegador.
- No se modificó D12 ni ninguna otra vista.

| Verificación | Resultado | Evidencia / limitación |
|---|---|---|
| Orden DOM del encabezado | **PASS** | El enlace `doctor-page-hero-action` precede a `#toggleButton` en la vista. |
| Orden visual estático | **PASS** | El contenedor conserva `flex-direction: row` y no contiene reglas D19 de `order` o `row-reverse`. |
| `toggleSize()` e identificadores | **PASS** | `id="toggleButton"`, `onclick="toggleSize()"`, `aria-label` y `title` conservados. |
| PHP lint | **PASS** | Validado sobre la vista modificada. |
| Sintaxis JavaScript | **PASS** | Validada sobre scripts externos y el bloque inline de D19. |
| Interacción visual, tabulación y overflow en viewports | **NO VERIFICADO** | No se dispuso de un navegador utilizable para ejecutar capturas e interacción real en esta ejecución. |

D19 permanece **CERRADA** por aprobación visual expresa; la interacción real y
las pruebas de viewport siguen NO VERIFICADAS donde se indicó.

### 3.3 Nueve módulos clínicos en vista Doctor

Todas estas pantallas usan la ruta genérica
GET /clinica/{modulo}/paciente/{idPaciente}, el método
ModulosController::moduloVistaDoctor y una vista separada en
app/views/historia-clinica-doctor/. El controlador reutiliza el catálogo de
slugs y la autorización por paciente; las plantillas no son compartidas con
Paciente ni con Resumen.

| ID | Módulo | Vista PHP | Acciones principales | Dependencias visuales | Estado | Pendientes |
|---|---|---|---|---|---|---|
| D23 | Ficha de identificación del paciente | GET `/clinica/{modulo}/paciente/{idPaciente}` con `modulo=ficha-identificiacion` | `app/views/historia-clinica-doctor/ficha-identificiacion.php` | `ModulosController::moduloVistaDoctor` | Consultar ficha, volver al expediente y editar paciente | Sidebar Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-ficha-identificacion-page`, CSS scoped | **CERRADA** | UI-CLOSE-D23 (2026-10-10): se confirmó la ruta genérica y el método real; el controlador aplica `authPermisos()` y `assertPatientAccess()` antes de cargar 30 campos visibles derivados de 29 claves únicas; `cuidador` conserva sus dos usos heredados, estado y nombre. Se modernizó exclusivamente la vista: shell Doctor, encabezado institucional, contexto del paciente autorizado, regreso al expediente, edición, cuatro tarjetas de altura natural y valores vacíos legibles. Se preservaron los campos entregados por el controlador, `data-rol`, `data-paciente`, rutas, sidebar y scripts existentes; el footer se cambió de `footer-mvsd.php` a `footer-doctor.php` porque el footer legado sólo contiene marcado y no dependencias JavaScript. PHP lint, sintaxis JavaScript, contratos estáticos e integridad CSS PASS. GET autenticado/cruzado/sin sesión, capturas, viewports y consola real NO VERIFICADOS por limitación del navegador. D23 queda **CERRADA** por aprobación visual expresa; se conservan las limitaciones funcionales y de seguridad documentadas. |
| D24 | Antecedentes familiares | GET `/clinica/antecedentes-familiares/paciente/{idPaciente}`; `app/views/historia-clinica-doctor/antecedentes-familiares.php` | `ModulosController::moduloVistaDoctor` | Consultar/editar enfermedades y respuestas mediante AJAX/POST | Sidebar Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-antecedentes-familiares-page`, CSS scoped | **CERRADA** | UI-CLOSE-D24 (2026-10-10): shell Doctor y encabezado modernizados; autorización y contratos preservados; navegador, capturas, consola y pruebas autenticadas NO VERIFICADOS. El GET conserva el comportamiento preexistente de siembra de filas fijas del modelo. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y de seguridad documentadas. |
| D25 | Antecedentes Personales No Patológicos | GET `/clinica/antecedentes-personales-no-patologicos/paciente/{idPaciente}`; `app/views/historia-clinica-doctor/antecedentes-personales-no-patologicos.php` | `ModulosController::moduloVistaDoctor` | Consultar respuestas de siete cuestionarios y comentarios mediante AJAX | Sidebar Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-antecedentes-no-patologicos-page`, CSS scoped | **CERRADA** | UI-CLOSE-D25 (2026-10-10): shell Doctor y encabezado modernizados; siete contenedores de cuestionario, comentarios, IDs y AJAX preservados; pruebas autenticadas, capturas, viewports y consola NO VERIFICADOS. El GET conserva la siembra preexistente de respuestas faltantes. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y de seguridad documentadas. |
| D26 | Antecedentes Personales Quirúrgicos | GET `/clinica/antecedentes-personales-quirurgicos/paciente/{idPaciente}`; `app/views/historia-clinica-doctor/antecedentes-personales-quirurgicos.php` | `ModulosController::moduloVistaDoctor` | Consultar, agregar, editar y eliminar cirugías mediante AJAX/POST | Sidebar Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-antecedentes-quirurgicos-page`, CSS scoped | **CERRADA** | UI-CLOSE-D26-01 y UI-FIX-D26-02 (2026-10-10): encabezado de cirugía con acción alineada mediante Grid scoped, tabla integrada sin borde decorativo duplicado y `fixedHeight: false` conservado. IDs, payloads, búsqueda, ordenamiento, paginación y acciones preservados. Capturas, viewports, consola e interacción real NO VERIFICADOS. SEC-D26-01 permanece abierto. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y de seguridad documentadas. |
| D27 | Antecedentes Personales Patológicos | GET `/clinica/antecedentes-personales-patologicos/paciente/{idPaciente}`; `app/views/historia-clinica-doctor/antecedentes-personales-patologicos.php` | `ModulosController::moduloVistaDoctor` | Consultar/editar respuestas de cuatro cuestionarios y enfermedades mediante AJAX/POST | Sidebar Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-antecedentes-patologicos-page`, CSS scoped | **CERRADA** | UI-CLOSE-D27 (2026-10-10): shell Doctor, encabezado y dos tarjetas clínicas modernizados; campos, contenedores, AJAX, tabla de enfermedades y funciones preservados. El GET conserva la siembra preexistente de respuestas faltantes; edición V2 no incluye `idPaciente` aunque el controlador exige ese contexto. Capturas, viewports, consola y pruebas autenticadas NO VERIFICADOS por limitación del navegador. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y de seguridad documentadas. |
| D28 | Medicación Actual | GET `/clinica/medicacion-actual/paciente/{idPaciente}`; `app/views/historia-clinica-doctor/medicacion-actual.php` | `ModulosController::moduloVistaDoctor` | Consultar, agregar, editar y eliminar medicamentos mediante AJAX/POST | Sidebar Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-medication-actual-page`, CSS scoped | **CERRADA** | UI-CLOSE-D28 (2026-10-10): shell Doctor, encabezado institucional, tarjeta de altura natural y tabla responsive modernizados; `fixedHeight` pasó a `false`. Capturas, viewports, consola y pruebas autenticadas NO VERIFICADOS por limitación del navegador. Riesgos de autorización en edición/eliminación y lecturas SQL heredadas permanecen abiertos. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y de seguridad documentadas. |
| D29 | Medicamentos usados para controlar el dolor | GET `/clinica/medicacion-control-dolor/paciente/{idPaciente}`; `app/views/historia-clinica-doctor/medicacion-control-dolor.php` | `ModulosController::moduloVistaDoctor` | Consultar/editar respuestas de seis cuestionarios mediante AJAX/POST | Sidebar Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-medication-pain-page`, CSS scoped | **CERRADA** | UI-CLOSE-D29 (2026-10-10): shell Doctor, encabezado institucional, contexto de paciente autorizado, tarjetas de altura natural y tablas sin altura fija. `fixedHeight` pasó a `false`; IDs, consultas AJAX, estado de tablas y función de edición preservados. Capturas, viewports, consola e interacción autenticada NO VERIFICADOS. La vista conserva la siembra de respuestas durante GET y el modelo mantiene lecturas SQL heredadas; no se corrigieron en esta actividad. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y de seguridad documentadas. |
| D30 | Procedimientos usados para controlar el dolor | GET `/clinica/procedimientos-control-dolor/paciente/{idPaciente}`; `app/views/historia-clinica-doctor/procedimientos-control-dolor.php` | `ModulosController::moduloVistaDoctor` | Consultar, agregar, editar y eliminar procedimientos; consultar y editar tratamientos mediante AJAX/POST | Sidebar Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-procedures-pain-page`, CSS scoped | **CERRADA** | UI-CLOSE-D30 (2026-10-10): shell Doctor, encabezado institucional, dos tarjetas clínicas de altura natural, acciones alineadas y tablas sin altura fija. `fixedHeight` pasó a `false` en procedimientos y tratamientos; IDs, funciones, endpoints, estado de tablas y payloads preservados. Capturas, viewports, consola e interacción autenticada NO VERIFICADOS. La vista conserva la siembra de respuestas durante GET y los riesgos de autorización SQL/registro no se corrigieron en esta actividad. Aprobación visual expresa confirmada; se conservan las limitaciones funcionales y de seguridad documentadas. |
| D31 | Evaluación del dolor | GET `/clinica/evaluacion-dolor/paciente/{idPaciente}`; `app/views/historia-clinica-doctor/evaluacion-dolor.php` | Consultar y editar mapas de dolor, imágenes frente/espalda y respuestas del cuestionario mediante AJAX/POST | Sidebar Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-pain-evaluation-page`, CSS scoped | **CERRADA** | UI-CLOSE-D31 (2026-10-10): shell Doctor, encabezado institucional, tarjetas de mapa y cuestionario de altura natural, canvas responsive y controles conservados. Interpolaciones PHP del JS se normalizaron a variables ya preparadas sin cambiar las rutas de imagen. Capturas, viewports, consola e interacción autenticada NO VERIFICADOS. La vista conserva escrituras durante GET y riesgos documentales/autoridad fuera de alcance. Aprobación visual expresa del responsable confirmada en UI-CLOSE-D37; se conservan las limitaciones funcionales y de seguridad. |

### 3.3.1 UI-CLOSE-D23 — Modernización visual de ficha de identificación

D23 corresponde a la ruta genérica `GET /clinica/{modulo}/paciente/{idPaciente}`
con `modulo=ficha-identificiacion`, atendida por
`ModulosController::moduloVistaDoctor`. El controlador obtiene la sesión Doctor,
ejecuta `assertPatientAccess($idPaciente, $authMiddleware)` y entrega a la vista
los 29 claves de datos que producen 30 campos visibles de la ficha, el rol, el
paciente y el sidebar configurado para el módulo. El campo `cuidador` se conserva
en sus dos usos heredados: estado y nombre.

La intervención se limitó a `app/views/historia-clinica-doctor/ficha-identificiacion.php`
y a CSS scoped en `public/assets/css/app.css`:

- Se incorporó el shell Doctor mediante `app-shell-body`,
  `app-shell-doctor`, `search-bar-doctor.php` y `footer-doctor.php`.
- Se aplicó un encabezado blanco/lavanda con etiqueta `HISTORIA CLÍNICA`, título
  de la ficha, descripción, paciente autorizado y acciones Volver/Editar.
- Se organizaron los datos existentes en cuatro tarjetas: datos personales,
  contexto de atención, domicilio y contacto/responsable.
- Se conservaron los campos del controlador, atributos `data-rol` y
  `data-paciente`, rutas de expediente/edición y scripts existentes; no hay
  `toggleSize()`, formulario, AJAX ni POST propios de D23.
- Se eliminó la dependencia visual de `footer-mvsd.php`; su contenido era sólo
  HTML y no se detectaron scripts dependientes del componente.

| Verificación | Resultado |
|---|---|
| Ruta, controlador, vista y autorización en código | **PASS estático**: `routes.php`, `ModulosController::moduloVistaDoctor` y `assertPatientAccess()` contrastados. |
| Campos y acciones originales | **PASS estático**: 30 valores conservados; regreso al expediente y edición mantienen sus rutas. |
| Shell y footer Doctor | **PASS estático**: clase de página, `search-bar-doctor.php` y `footer-doctor.php` presentes; no hay footer duplicado en la vista. |
| Altura natural y responsive scoped | **PASS estático**: D23 sobrescribe la altura heredada sólo para sus tarjetas y no usa `height: 100%`, `order` ni `row-reverse` en su bloque. |
| PHP lint de vista y controlador | **PASS**. |
| Sintaxis JavaScript | **PASS** sobre scripts externos; D23 no contiene bloques inline. |
| Integridad CSS y contratos HTML/JavaScript | **PASS estático**. |
| GET autenticado propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de sesión sintética reproducible en navegador. El control `assertPatientAccess()` sí está confirmado en código. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667 | **NO VERIFICADO**: Chrome existe, pero su perfil habitual falló con `ProcessSingleton`; no se generaron capturas. |
| Consola, foco, overflow y navegación real | **NO VERIFICADO**: requieren render e interacción en navegador. |
| POST clínicos, documentos y descargas | **NO APLICA / NO EJECUTADO**: D23 sólo presenta ficha y navegación; no se realizaron escrituras ni operaciones documentales. |

D23 queda **CERRADA** por aprobación visual expresa. La modernización no implica
aprobación de seguridad ni autorización para producción; ACT-05C-02 y los riesgos
de recetas externas y COFEPRIS permanecen abiertos.

### 3.3.2 UI-CLOSE-D24 — Modernización visual de antecedentes familiares

D24 corresponde al módulo `antecedentes-familiares` de la ruta genérica
`GET /clinica/{modulo}/paciente/{idPaciente}`, atendida por
`ModulosController::moduloVistaDoctor` y renderizada por
`app/views/historia-clinica-doctor/antecedentes-familiares.php`. El controlador
configura el sidebar del módulo, obtiene `idPaciente`, nombre, sexo y rol, y
ejecuta `authPermisos()` seguido de `assertPatientAccess()` antes de entregar la
vista. Los endpoints AJAX de preguntas y comentarios vuelven a verificar el
acceso al paciente mediante `BusquedasController::guardPatient()`; las acciones
POST de agregar, editar y eliminar antecedentes también conservan la guarda de
paciente en `ModulosController`.

La intervención se limitó a la vista D24 y a un bloque CSS con alcance mediante
`doctor-antecedentes-familiares-page` en `public/assets/css/app.css`:

- Se sustituyó el shell visual heredado por el shell Doctor en la vista, con
  `search-bar-doctor.php` y `footer-doctor.php`. No se modificó el componente
  compartido `footer-mvsd.php`.
- Se incorporó el encabezado institucional blanco/lavanda con etiqueta
  `HISTORIA CLÍNICA`, título `Antecedentes familiares`, descripción, nombre del
  paciente escapado y regreso al expediente.
- Se organizaron las preguntas y los comentarios adicionales en tarjetas de
  altura natural, con padding, bordes, estados de tabla y comportamiento
  responsive delimitados a D24.
- Se conservaron `main`, `contePreguntas`, `conteComentario`, `seccion2`,
  `table_enfermedades`, `contenidoPreguntas()`, `contenidoComentario()`,
  `gestionarEnfermedadPaciente()`, `agregarEnfermedadPaciente()`,
  `editarEnfermedad()` y `eliminarEnfermedadPaciente()`, además de los
  endpoints, `localStorage` y callbacks existentes. D24 no contiene
  `toggleSize()`.
- El modelo conserva cinco enfermedades fijas y su comportamiento preexistente
  de insertar filas ausentes durante la preparación GET mediante
  `AntecedenteFamiliarModel::antecedentesFamiliares()`. No se modificó ese
  efecto lateral ni se ejecutaron escrituras clínicas en esta actividad; queda
  como riesgo funcional independiente del rediseño.

| Verificación | Resultado |
|---|---|
| Ruta, controlador, vista y autorización | **PASS estático**: `routes.php`, `ModulosController::moduloVistaDoctor`, `authPermisos()`, `assertPatientAccess()` y guardas AJAX/POST contrastados. |
| Campos, funciones y endpoints originales | **PASS estático**: IDs, funciones inline, rutas AJAX/POST, `localStorage` y tabla dinámica preservados. |
| Shell y footer Doctor | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` presentes; `footer-mvsd.php` ya no se incluye en D24. |
| Encabezado, tarjetas y CSS scoped | **PASS estático**: bloque delimitado a D24, sin `height: 100%`, `order` ni `row-reverse` en la intervención. |
| PHP lint y contratos de la vista | **PASS** después de corregir el bloque PHP accidental y revisar IDs/controles. |
| Sintaxis JavaScript | **PASS estático** sobre scripts y bloque inline; ejecución real de AJAX no verificada. |
| GET autenticado propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de una sesión sintética reproducible en navegador; la guarda está confirmada en código. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667; capturas y consola | **NO VERIFICADO**: Chrome falló al iniciar el proceso GPU (`GPU process isn't usable`); no se inventan capturas ni resultados visuales. |
| POST/AJAX, foco, teclado, overflow y persistencia | **NO VERIFICADO / NO EJECUTADO**: requieren navegador y datos sintéticos autorizados; no se realizaron escrituras. |

D24 queda **CERRADA** por aprobación visual expresa. La modernización no implica
aprobación de seguridad ni autorización para producción; ACT-05C-02, recetas
externas, COFEPRIS y el efecto lateral GET indicado arriba permanecen
documentados.

### 3.3.3 UI-CLOSE-D25 — Modernización visual de antecedentes personales no patológicos

D25 corresponde al módulo `antecedentes-personales-no-patologicos` de la ruta
genérica `GET /clinica/{modulo}/paciente/{idPaciente}`, atendida por
`ModulosController::moduloVistaDoctor` y renderizada por
`app/views/historia-clinica-doctor/antecedentes-personales-no-patologicos.php`.
El controlador conserva el catálogo del módulo, nombre del paciente, rol,
sidebar y la autorización `authPermisos()` + `assertPatientAccess()` antes de
renderizar. El contenido de cada cuestionario se solicita mediante
`BusquedasController::contenidoPreguntasM3()`, que vuelve a ejecutar
`guardPatient()`; los comentarios usan `contenidoComentariosModulo()` con la
misma guarda.

La intervención se limitó a la vista D25 y a un bloque CSS con alcance mediante
`doctor-antecedentes-no-patologicos-page` en `public/assets/css/app.css`:

- Se incorporó el shell Doctor con `search-bar-doctor.php` y
  `footer-doctor.php`, sin modificar componentes compartidos ni el Portal
  Paciente.
- Se aplicó encabezado institucional blanco/lavanda, etiqueta `HISTORIA
  CLÍNICA`, título del módulo, descripción, paciente autorizado escapado y
  regreso al expediente.
- Se organizaron los siete contenedores existentes
  `contePreguntas_1`–`contePreguntas_7` en una tarjeta clínica de altura natural,
  más la tarjeta separada de comentarios adicionales.
- Se conservaron `main`, `data-rol`, `data-paciente`, `seccion2`,
  `conteComentario`, `contenidoPreguntas()`, `contenidoComentario()`,
  `gestionarAntecedentesNoPatologicos()`, `respuestaPreguntaSelect()` y el
  endpoint `editar-cuestionario-modulo3`, además de las siete llamadas AJAX
  iniciales.
- No se alteraron preguntas, respuestas, términos, IDs, rutas, validaciones,
  AJAX, persistencia ni orden de los cuestionarios. D25 no contiene
  `toggleSize()`.

Durante el GET la vista recorre `obtenerPreguntasModulos()` y llama a
`AntecedentesNoPatologicosModel::antecedentesNoPatologicos()`. Ese método inserta
una respuesta vacía cuando falta para una pregunta fija. Es un efecto secundario
real, comparable al documentado en D24; no se modificó y no se ejecutaron
pruebas de escritura contra datos reales.

| Verificación | Resultado |
|---|---|
| Identidad, ruta, vista, controlador y autorización | **PASS estático**: `routes.php`, `ModulosController::moduloVistaDoctor`, `authPermisos()`, `assertPatientAccess()` y guardas AJAX contrastados. |
| Siete cuestionarios y comentarios | **PASS estático**: `contePreguntas_1`–`contePreguntas_7`, `conteComentario`, `seccion2` y orden de carga preservados. |
| Shell y footer Doctor | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` presentes; `footer-mvsd.php` ya no se incluye en D25. |
| Preguntas, respuestas, IDs, AJAX y persistencia | **PASS estático**: no se modificaron contratos clínicos ni el modelo. |
| CSS scoped, tarjetas naturales y contratos HTML/JS | **PASS estático**: bloque D25 balanceado, sin `height: 100%`, `order` ni `row-reverse` en la intervención activa. |
| PHP lint de vista, controladores y componentes | **PASS**. |
| Sintaxis JavaScript externa e inline | **PASS**. |
| GET autenticado propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de sesión sintética reproducible en navegador; la guarda está confirmada en código. |
| Efecto secundario GET, AJAX, POST, persistencia y errores de red | **NO VERIFICADO / NO EJECUTADO**: requieren fixture descartable y sesión reproducible; no se realizaron escrituras. |
| Viewports 1440×900, 1280×720, 768×1024, 375×667, capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: Chrome volvió a fallar con `GPU process isn't usable`; no se inventan capturas ni resultados. |

D25 queda **CERRADA** por aprobación visual expresa. La modernización no implica
aprobación de seguridad ni autorización para producción; ACT-05C-02 y los riesgos
documentales, recetas externas y COFEPRIS permanecen abiertos.

### 3.3.4 UI-CLOSE-D26 — Modernización visual de antecedentes personales quirúrgicos

D26 corresponde al módulo `antecedentes-personales-quirurgicos` de la ruta
genérica `GET /clinica/{modulo}/paciente/{idPaciente}`, atendida por
`ModulosController::moduloVistaDoctor` y renderizada por
`app/views/historia-clinica-doctor/antecedentes-personales-quirurgicos.php`.
El controlador obtiene el rol, el paciente y el sidebar después de ejecutar
`authPermisos()` y `assertPatientAccess()`. El fragmento clínico se carga por
`BusquedasController::contenidoPreguntasM4()`, que vuelve a ejecutar
`guardPatient()`; las operaciones POST de agregar, editar y eliminar cirugía
mantienen la misma guarda de paciente en `ModulosController`.

La intervención se limitó a la vista D26 y a un bloque CSS con alcance mediante
`doctor-antecedentes-quirurgicos-page` en `public/assets/css/app.css`:

- Se integró `search-bar-doctor.php` y `footer-doctor.php`, sin modificar el
  footer legado ni componentes compartidos.
- Se incorporó encabezado institucional blanco/lavanda con etiqueta `HISTORIA
  CLÍNICA`, título dinámico escapado, descripción, paciente autorizado y regreso
  al expediente.
- Se mantuvo el fragmento dinámico de cirugías dentro de una tarjeta de altura
  natural, con tabla responsive, controles de fecha/texto y acción de agregar.
- UI-FIX-D26-01 conservó la pregunta clínica original dentro del contenido, pero
  añadió visualmente el título «Antecedentes quirúrgicos» y el texto accesible y
  visible «Agregar cirugía» al botón existente, sin modificar su `onclick`, ruta
  ni payload.
- La causa del scrollbar vertical artificial era `fixedHeight: true` en la
  inicialización de Simple-DataTables; se cambió exclusivamente en la vista a
  `fixedHeight: false` y se limitaron las reglas de altura/overflow al scope de
  D26. El buscador, selector de registros, ordenamiento y paginación siguen
  siendo provistos por Simple-DataTables.
- UI-FIX-D26-02 confirmó que la alineación defectuosa provenía de dejar la
  distribución del encabezado dependiente del layout Bootstrap de `.col-11` y
  `.col-1`, sin una columna de acción definida explícitamente al extremo derecho.
  Se añadió un Grid scoped al encabezado dinámico: pregunta a la izquierda,
  acción a la derecha y mensaje debajo; en móvil se apilan en ese mismo orden.
  No se usaron `position:absolute`, márgenes fijos, `order` ni `row-reverse`.
- El borde duplicado provenía de la regla D26 específica de
  `.table-responsive` (`border: 1px` y `border-radius: 12px`), no de una
  regla de borde de Simple-DataTables para este encabezado. Se eliminó ese
  borde, radio y sombra del wrapper y se delimitó la misma neutralización para
  `.dataTable-wrapper` y `.dataTable-container`, conservando encabezados,
  separadores, controles, inputs y paginación.
- Se conservaron `main`, `data-rol`, `data-paciente`, `contePreguntas`,
  `btnAgregarCirugia`, `table_cirugia`, `mensaje`,
  `contenidoPreguntas()`, `gestionarAntecedentesQuirurgicos()`,
  `agregarCirugiaPaciente()`, `editarCirugia()` y
  `eliminarCirugiaPaciente()`, además de sus endpoints, Simple-DataTables y
  estado `datatableCirugiaState`.
- D26 no contiene `toggleSize()` ni una siembra de datos durante el GET de la
  vista. La consulta clínica ocurre en el endpoint AJAX; no se ejecutaron POST
  ni escrituras durante esta actividad.
- Riesgo funcional pendiente: `editarCirugia()` y
  `eliminarCirugiaPaciente()` no agregan `idPaciente` a sus payloads, mientras
  `pacienteEditCirugia()` y `pacienteDeleteCirugia()` invocan
  `assertPatientAccess($data['idPaciente'] ?? 0)`. Debe comprobarse con un
  fixture autorizado antes de corregirlo; no se modificó en esta actividad.

| Verificación | Resultado |
|---|---|
| Identidad, ruta, vista, controlador y autorización | **PASS estático**: `routes.php`, `ModulosController::moduloVistaDoctor`, `authPermisos()`, `assertPatientAccess()` y guardas AJAX/POST contrastados. |
| Campos, tabla y acciones originales | **PASS estático**: tabla `table_cirugia`, botón `btnAgregarCirugia`, `mensaje`, inputs y funciones preservados. |
| Shell y footer Doctor | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` presentes; no se incluye `footer-mvsd.php` en D26. |
| Simple-DataTables y estado local | **PASS estático**: inicialización, búsqueda, ordenamiento, paginación y `datatableCirugiaState` conservados. |
| Encabezado y acción Agregar cirugía | **PASS estático**: título separado, pregunta original preservada, Grid scoped con acción a la derecha en escritorio y segunda línea en móvil; botón con texto visible, `aria-label`, `title` y `onclick` conservados. |
| Borde decorativo duplicado de la tabla | **PASS estático**: el borde/radio/sombra del `.table-responsive`, `.dataTable-wrapper` y `.dataTable-container` quedó neutralizado únicamente bajo D26; tarjeta, encabezado violeta, separadores, inputs, búsqueda y paginación se conservan. |
| Altura natural de tabla | **PASS estático**: `fixedHeight: false`, `height/max-height` naturales y `overflow-y: visible` scoped a D26. La ausencia real de scrollbar con una fila requiere navegador. |
| CSS scoped, altura natural y contratos HTML/JS | **PASS estático**: bloque D26 balanceado, sin `height: 100%`, `order` ni `row-reverse` en la intervención activa. |
| PHP lint de vista, controladores y componentes | **PASS**. |
| Sintaxis JavaScript externa e inline | **PASS**. |
| GET autenticado propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de sesión sintética reproducible en navegador; la guarda está confirmada en código. |
| AJAX, POST, persistencia y aislamiento clínico | **NO VERIFICADO / NO EJECUTADO**: no se realizaron escrituras ni acciones clínicas. La discrepancia de `idPaciente` en edición/eliminación queda pendiente. |
| Viewports 1440×900, 1280×720, 768×1024, 375×667, capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: Chrome continúa fallando con `GPU process isn't usable`; no se inventan capturas ni resultados. |

D26 queda **CERRADA** por aprobación visual expresa. La modernización no implica
aprobación de seguridad ni autorización para producción; ACT-05C-02 y los riesgos
documentales, recetas externas y COFEPRIS permanecen abiertos.

### 3.3.5 UI-CLOSE-D27 — Modernización visual de antecedentes personales patológicos

D27 corresponde al módulo `antecedentes-personales-patologicos` de la ruta
genérica `GET /clinica/{modulo}/paciente/{idPaciente}`, atendida por
`ModulosController::moduloVistaDoctor` y renderizada por
`app/views/historia-clinica-doctor/antecedentes-personales-patologicos.php`.
El controlador ejecuta `authPermisos()` y `assertPatientAccess()` antes de
entregar el paciente, el rol y el sidebar. Los fragmentos se cargan mediante
`BusquedasController::contenidoPreguntasM5V1()` y
`contenidoPreguntasM5V2()`, cuyos endpoints llaman `guardPatient()`.

La intervención se limitó a la vista D27 y a un bloque CSS scoped mediante
`doctor-antecedentes-patologicos-page` en
`public/assets/css/app.css`:

- Se incorporaron `search-bar-doctor.php` y `footer-doctor.php`, sin modificar
  `footer-mvsd.php` ni componentes compartidos.
- Se incorporó un encabezado institucional blanco/lavanda con etiqueta
  `HISTORIA CLÍNICA`, título dinámico escapado, descripción, paciente autorizado
  y regreso al expediente.
- Se organizaron los cuatro contenedores de preguntas (`contePreguntas_1` a
  `contePreguntas_4`) y el contenedor de enfermedades (`contePreguntasV2`) en
  dos tarjetas clínicas de altura natural, con tablas y controles responsive.
- Se conservaron `main`, `data-rol`, `data-paciente`, todos los contenedores,
  `contenidoPreguntas()`, `contenidoEnfermedades()`,
  `respuestaPreguntaSelect()`, `editarEnfermedadV2()`, los endpoints AJAX/POST,
  Simple-DataTables y el estado `datatableEnfermedadesState`.
- El GET conserva un efecto de escritura preexistente: la vista llama a
  `antecedentesPatologicos()` y `antecedentesPatologicosV2()`, que insertan
  respuestas vacías faltantes en `pac_respuestas_paciente_modulo_5` y
  `pac_enfermedades_respuestas_modulo_5`. No se ejecutó contra datos reales ni
  se modificó en esta actividad.
- Riesgo funcional pendiente: `editarEnfermedadV2()` no incluye `idPaciente` en
  su payload, mientras `pacienteEditarCuestionarioV2M5()` exige ese contexto
  para `assertPatientAccess()`. Debe verificarse y corregirse en una actividad
  independiente; no se alteró aquí.
- El modelo genera el identificador `table_patologico` en cada uno de los
  cuatro fragmentos V1; si los cuatro fragmentos están presentes, pueden
  existir IDs HTML duplicados. Se conservó para no modificar el contrato
  dinámico del módulo y requiere una corrección funcional/estructural separada.
- Las consultas de lectura del modelo interpolan identificadores en SQL en
  varios métodos heredados de D27. No se modificaron durante el cierre visual;
  queda como riesgo técnico para una actividad de parametrización independiente.
- UI-FIX-D27-01 confirmó que el borde duplicado provenía del
  `.table-responsive` aplicado alrededor de las tablas dinámicas, no de una
  tarjeta adicional ni de Simple-DataTables. Se eliminaron sólo `border`,
  `border-radius` y `box-shadow` del wrapper, `.dataTable-wrapper` y
  `.dataTable-container` dentro de D27; los bordes de la tarjeta, encabezados,
  filas, inputs y selects se conservaron.

| Verificación | Resultado |
|---|---|
| Identidad, ruta, vista, controlador y autorización | **PASS estático**: `routes.php`, `ModulosController::moduloVistaDoctor`, `authPermisos()`, `assertPatientAccess()` y guardas AJAX contrastados. |
| Campos, contenedores, tabla y funciones originales | **PASS estático**: `contePreguntas_1`–`contePreguntas_4`, `contePreguntasV2`, `table_patologico`, `table_enfermedades` y funciones preservados. La posible duplicación heredada de `table_patologico` queda documentada. |
| Shell y footer Doctor | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` presentes; D27 ya no incluye `footer-mvsd.php`. |
| Efecto secundario durante GET | **PASS de identificación estática / NO EJECUTADO**: las dos funciones de preparación realizan `INSERT` condicional; no se ejecutó GET autenticado. |
| Borde externo duplicado de tablas | **PASS estático**: el borde scoped del `.table-responsive` y wrappers internos quedó en `0`; la corrección aplica a `table_patologico` y `table_enfermedades` sin modificar el modelo. |
| CSS scoped, altura natural y contratos HTML/JS | **PASS estático**: bloque D27 balanceado, sin `height: 100%`, `order` ni `row-reverse` en la intervención activa. |
| PHP lint de vista, controladores, modelo y componentes | **PASS**. |
| Sintaxis JavaScript externa e inline | **PASS**. |
| GET autenticado propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de sesión sintética reproducible en navegador. |
| AJAX, POST, persistencia y aislamiento clínico | **NO VERIFICADO / NO EJECUTADO**: no se realizaron escrituras; la discrepancia de `idPaciente` en edición V2 queda pendiente. |
| Viewports 1440×900, 1280×720, 768×1024, 375×667, capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: Chrome continúa fallando con `GPU process isn't usable`; no se inventan capturas ni resultados. |

D27 queda **CERRADA** por aprobación visual expresa. La modernización no implica
aprobación de seguridad ni autorización para producción; ACT-05C-02 y los riesgos
documentales, recetas externas y COFEPRIS permanecen abiertos.

### 3.3.6 UI-CLOSE-D28 — Modernización visual de medicación actual

D28 corresponde al módulo `medicacion-actual` de la ruta genérica
`GET /clinica/{modulo}/paciente/{idPaciente}`, con
`ModulosController::moduloVistaDoctor` como controlador. El controlador aplica
`authPermisos()` y `assertPatientAccess()` antes de renderizar la vista. La carga
del contenido clínico se realiza mediante
`GET /buscar/contenido-preguntas-modulo-6/{idPaciente}/{idRol}`, atendido por
`BusquedasController::contenidoPreguntasM6`; el fragmento Doctor genera la tabla
`table_medicacion`, el mensaje `#mensaje` y la acción `#btnAgregarMedicamento`.

La intervención se limitó a la vista D28, a un bloque CSS scoped y a esta entrada
documental:

- Se reemplazó la composición heredada por el shell Doctor, incorporando
  `search-bar-doctor.php` y `footer-doctor.php`. No se modificaron el sidebar,
  modelo, controlador, rutas ni componentes compartidos.
- Se incorporó el encabezado institucional con etiqueta `HISTORIA CLÍNICA`,
  título dinámico escapado, descripción, paciente autorizado escapado y regreso
  al expediente.
- Se añadió un encabezado visual al fragmento dinámico mediante
  `prepararEncabezadoMedicacion()`, sin cambiar la pregunta clínica ni la acción
  de alta. La etiqueta de la acción y sus atributos accesibles se conservan en
  el fragmento generado.
- Se cambió únicamente la opción visual de Simple-DataTables de
  `fixedHeight: true` a `fixedHeight: false`, conservando búsqueda, ordenamiento,
  paginación y el estado `datatableMedicacionState`.
- Se dejó la tabla con altura natural, los wrappers sin borde/sombra decorativos
  duplicados y el overflow horizontal limitado al contenedor de tabla; los
  encabezados, separadores, inputs y acciones originales se mantienen.
- No existe `toggleSize()` en la vista D28, por lo que no se introdujo un botón
  de distribución nuevo.

| Verificación | Resultado |
|---|---|
| Identidad, ruta, vista, controlador y guardas | **PASS estático**: `routes.php`, `ModulosController::moduloVistaDoctor`, `authPermisos()`, `assertPatientAccess()` y `BusquedasController::contenidoPreguntasM6` contrastados. |
| Campos/tabla, IDs, funciones, AJAX y acciones originales | **PASS estático**: `contePreguntas`, `table_medicacion`, `mensaje`, `btnAgregarMedicamento`, `agregarMedicamentoPaciente`, `editarMedicamento`, `eliminarMedicamentoPacientes` y endpoints preservados. |
| Shell Doctor y footer | **PASS estático**: se incluyen `search-bar-doctor.php` y `footer-doctor.php`; D28 ya no incluye `footer-mvsd.php`. |
| Altura natural y tabla responsive | **PASS estático**: `fixedHeight: false`, sin `height`/`max-height` artificiales en el alcance D28 y wrappers sin borde/sombra decorativos adicionales. La ausencia real de scrollbar requiere navegador. |
| PHP lint y contratos HTML/PHP | **PASS**: lint ejecutado sobre la vista D28, controlador, controlador AJAX y componentes Doctor relacionados; contratos estáticos de la vista conservados. |
| Sintaxis JavaScript externa e inline | **PASS**: `node --check` ejecutado sobre `loader.js`, `main.js` y `search-main.js`; el bloque inline D28 fue parseado con `new Function`. |
| GET autenticado propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de sesión sintética reproducible; la guarda está confirmada estáticamente. No se ejecutó GET contra datos reales. |
| AJAX de carga, POST, persistencia y autorización por registro | **NO VERIFICADO / NO EJECUTADO**: no se realizaron escrituras. El controlador exige `idPaciente` para editar/eliminar, pero el payload actual de esas funciones no lo envía; queda como riesgo funcional/de autorización independiente. |
| Efectos secundarios durante GET | **PASS de inspección estática / NO EJECUTADO**: la vista sólo inicia el GET de lectura del fragmento; no se identificó una llamada de inserción en la carga inicial. No se ejecutó contra datos reales. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667; capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: Chrome continúa fallando con `GPU process isn't usable`; no se inventan capturas ni resultados visuales. |

D28 queda **CERRADA** por aprobación visual expresa. La modernización no implica
aprobación de seguridad ni autorización para producción. Permanecen abiertos
ACT-05C-02, las lecturas SQL interpoladas de `MedicacionActualModel` y la
discrepancia entre el contexto exigido por `assertPatientAccess()` y los payloads
actuales de edición/eliminación.

### 3.3.7 UI-CLOSE-D29 — Modernización visual de medicación para control del dolor

D29 corresponde al módulo `medicacion-control-dolor` de la ruta genérica
`GET /clinica/{modulo}/paciente/{idPaciente}`, atendida por
`ModulosController::moduloVistaDoctor`. La vista aplica el contexto de paciente que
recibe del controlador y las guardas de acceso se ejecutan antes de renderizar en
`moduloVistaDoctor`. La carga dinámica usa
`GET /buscar/contenido-preguntas-modulo-7/{idPaciente}/{idRol}/{idCuestionario}`,
atendido por `BusquedasController::contenidoPreguntasM7`, y la edición conserva el
POST `/clinica/paciente/editar-cuestionario-modulo7` (o su equivalente de paciente)
con la función `respuestaPreguntaSelect()`.

La intervención se limitó a la vista D29, a un bloque CSS scoped y a esta entrada
documental:

- Se sustituyó el shell heredado por `app-shell-body`/`app-shell-doctor`,
  `search-bar-doctor.php` y `footer-doctor.php`; no se modificaron modelos,
  controladores, rutas, consultas ni componentes compartidos.
- Se incorporó el encabezado institucional con etiqueta `HISTORIA CLÍNICA`, título
  dinámico escapado, descripción, contexto del paciente escapado y regreso al
  expediente. El texto clínico original de la instrucción se conserva.
- Se mantuvieron los seis contenedores `contePreguntas_1`–`contePreguntas_6`, la
  tabla dinámica `table_medicamentos_{idCuestionario}`, `#mensaje`, la clave de
  estado local, búsqueda, ordenamiento, paginación y `respuestaPreguntaSelect()`.
- Se cambió únicamente la opción visual de Simple-DataTables de
  `fixedHeight: true` a `fixedHeight: false`. Los wrappers y tablas quedan con
  altura natural, sin bordes decorativos duplicados ni scrollbar vertical impuesto;
  el desbordamiento horizontal se limita al contenedor de tabla.
- No existe `toggleSize()` en D29 ni se introdujo un botón de distribución.

| Verificación | Resultado |
|---|---|
| Identidad, ruta, vista, controlador y guardas | **PASS estático**: `routes.php`, `ModulosController::moduloVistaDoctor`, `authPermisos()`, `assertPatientAccess()` y `BusquedasController::contenidoPreguntasM7` contrastados. |
| Campos, contenedores, tabla, funciones y AJAX | **PASS estático**: seis contenedores, `table_medicamentos_{idCuestionario}`, `mensaje`, `contenidoPreguntas()`, `gestionarMedicacionControl()`, `respuestaPreguntaSelect()` y endpoints preservados. |
| Shell Doctor y footer | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` presentes; D29 ya no incluye `footer-mvsd.php`. |
| Altura natural, wrappers y responsive estructural | **PASS estático**: `fixedHeight: false`, sin `height: 100%` ni `max-height` artificiales en el alcance D29; wrappers sin borde/sombra decorativos. La ausencia visual real de scrollbar requiere navegador. |
| PHP lint y contratos HTML/PHP | **PASS**: `php -l` sin errores en la vista D29, `ModulosController.php`, `BusquedasController.php` y `MedicacionDolorModel.php`; contratos estáticos preservados. |
| Sintaxis JavaScript externa e inline | **PASS**: `node --check` en `loader.js`, `main.js`, `search-main.js` y `voice-utilities.js`; el bloque inline D29 fue parseado con `new Function`. |
| Integridad CSS y `git diff --check` | **PASS scoped**: bloque D29 balanceado (35/35 llaves), sin `height: 100%`, `order`, `row-reverse` ni `!important`. `git diff --check` reporta únicamente whitespace histórico preexistente en `app.css` líneas 4138, 4139, 4141, 4166 y 4224; no en el bloque D29 ni en la vista. |
| GET autenticado propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de sesión sintética reproducible. No se ejecutó GET contra datos reales. |
| AJAX, POST, persistencia y autorización por registro | **NO VERIFICADO / NO EJECUTADO**: no se realizaron escrituras. Se conserva el contrato existente; no se evaluaron operaciones clínicas en esta actividad. |
| Efectos secundarios durante GET | **PASS de inspección estática / NO EJECUTADO**: `MedicacionDolorModel::medicacionDolorQuest()` realiza `INSERT` condicional cuando faltan respuestas; se conserva por alcance y queda como riesgo independiente. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667; capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: no se dispuso de una sesión sintética autenticada y reproducible para capturas; no se inventan resultados. |

D29 queda **CERRADA** por aprobación visual expresa. La modernización no implica
aprobación de seguridad ni autorización para producción. Permanecen abiertos
ACT-05C-02, las lecturas SQL heredadas de `MedicacionDolorModel`, la siembra de
respuestas durante GET y cualquier riesgo de autorización de registros clínicos
que requiera una actividad independiente.

### 3.3.8 UI-CLOSE-D30 — Modernización visual de procedimientos y tratamientos

D30 corresponde al módulo `procedimientos-control-dolor` de la ruta genérica
`GET /clinica/{modulo}/paciente/{idPaciente}`, atendida por
`ModulosController::moduloVistaDoctor`. El controlador aplica
`authPermisos()` y `assertPatientAccess()` antes de renderizar la vista. D30 carga
dos fragmentos protegidos por `BusquedasController`:
`GET /buscar/contenido-preguntas-modulo-8/{idPaciente}/{idRol}` para
`table_procedimiento` y
`GET /buscar/contenido-preguntas-tratamiento-modulo-8/{idPaciente}/{idRol}` para
`table_tratamientos`.

La intervención se limitó a la vista D30, a un bloque CSS scoped y a esta entrada
documental:

- Se sustituyó la composición heredada por el shell Doctor, incorporando
  `search-bar-doctor.php` y `footer-doctor.php`; no se modificaron modelos,
  controladores, rutas ni componentes compartidos.
- Se incorporó el encabezado institucional con etiqueta `HISTORIA CLÍNICA`, título
  dinámico escapado, descripción, contexto del paciente escapado y regreso al
  expediente.
- Se conservaron `preguntas-container`, `tratamientos-container`,
  `contePreguntas`, `conteTratamiento`, `table_procedimiento`,
  `table_tratamientos`, los estados `datatableProcedimientoState` y
  `datatableTratamientosState`, además de las funciones de alta, edición,
  eliminación y navegación existentes.
- Se cambió únicamente la opción visual de Simple-DataTables de
  `fixedHeight: true` a `fixedHeight: false` en ambas tablas. Los wrappers quedan
  con altura natural, sin bordes decorativos duplicados ni scrollbar vertical
  impuesto; el desbordamiento horizontal permanece contenido en cada tabla.
- Se añadieron clases visuales al fragmento dinámico para alinear el botón real de
  agregar procedimiento al extremo derecho del encabezado, conservar su acción y
  agregar nombre accesible. No se usó `position:absolute`, `order` ni
  `row-reverse`.
- D30 no contiene `toggleSize()`; no se introdujo un botón de distribución.
- Los dos fragmentos heredados generan un elemento `#mensaje`; se conservaron sus
  IDs y comportamiento, pero la duplicación de ID queda como riesgo funcional
  independiente para una actividad posterior.

| Verificación | Resultado |
|---|---|
| Identidad, ruta, vista, controlador y guardas | **PASS estático**: `routes.php`, `ModulosController::moduloVistaDoctor`, `authPermisos()`, `assertPatientAccess()` y ambos métodos AJAX de `BusquedasController` contrastados. |
| Campos, contenedores, tablas, funciones y endpoints | **PASS estático**: contenedores, tablas, estados locales, funciones `contenidoPreguntas()`, `contenidoTratamientos()`, `gestionarControlDolor()`, altas, edición, eliminación y endpoints preservados. |
| Shell Doctor y footer | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` presentes; D30 ya no incluye `footer-mvsd.php`. |
| Altura natural, tablas y acciones alineadas | **PASS estático**: `fixedHeight: false` en ambas tablas, wrappers sin borde/sombra decorativos y botón de agregar alineado mediante layout scoped. La comprobación visual real requiere navegador. |
| PHP lint y contratos HTML/PHP | **PASS**: `php -l` sin errores en la vista D30, `ModulosController.php`, `BusquedasController.php` y `ProcedimientosDolorModel.php`. |
| Sintaxis JavaScript externa e inline | **PASS**: `node --check` en scripts compartidos; bloque inline D30 parseado con `new Function`. |
| Integridad CSS y `git diff --check` | **PASS scoped**: bloque D30 balanceado (40/40 llaves), sin `height: 100%`, `order`, `row-reverse` ni `!important`. `git diff --check` reporta únicamente whitespace histórico preexistente en `app.css` líneas 4138, 4139, 4141, 4166 y 4224; no en el bloque D30 ni en la vista. |
| GET autenticado propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de sesión sintética reproducible. No se ejecutó GET contra datos reales. |
| AJAX, POST, persistencia y autorización por registro | **NO VERIFICADO / NO EJECUTADO**: no se realizaron escrituras. Los controladores exigen `idPaciente`, pero los payloads actuales de edición/eliminación se conservan sin una auditoría funcional adicional; los fragmentos también mantienen la duplicación heredada de `#mensaje`. |
| Efectos secundarios durante GET | **PASS de inspección estática / NO EJECUTADO**: `ProcedimientosDolorModel::procedimientosModulo8()` realiza `INSERT` condicional de respuestas faltantes durante la preparación de la vista; se conserva por alcance y queda como riesgo independiente. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667; capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: no se dispuso de una sesión sintética autenticada y reproducible para capturas; no se inventan resultados. |

D30 queda **CERRADA** por aprobación visual expresa. La modernización no implica
aprobación de seguridad ni autorización para producción. Permanecen abiertos
ACT-05C-02, las lecturas SQL heredadas de `ProcedimientosDolorModel`, la siembra
de respuestas durante GET y los riesgos de autorización de operaciones por
registro.

### 3.3.9 UI-CLOSE-D31 — Modernización visual de evaluación del dolor

**Identificación verificada.** D31 corresponde al módulo `evaluacion-dolor`,
expuesto por la ruta genérica `GET /clinica/{modulo}/paciente/{idPaciente}` y
renderizado por `ModulosController::moduloVistaDoctor` en
`app/views/historia-clinica-doctor/evaluacion-dolor.php`. El controlador aplica
`AuthMiddleware('clinica')`, `authPermisos()` y `assertPatientAccess()` antes de
preparar la vista. El título real se obtiene mediante el mapa de módulos de
`ModulosController`.

La vista conserva dos mapas de dolor (frente y espalda), sus imágenes y colores,
el cuestionario de evaluación y las funciones JavaScript existentes. Sus lecturas
AJAX son:

- `GET /buscar/contenido-evaluacion-frente-modulo-9/{idPaciente}/{idRol}`.
- `GET /buscar/contenido-evaluacion-espalda-modulo-9/{idPaciente}/{idRol}`.
- `GET /buscar/contenido-preguntas-modulo-9/{idPaciente}/{idRol}`.

Las operaciones POST conservadas son `pacienteEditImgED1`,
`pacienteDeleteImgED1`, `pacienteEditImgED2`, `pacienteDeleteImgED2` y
`pacienteEditEvaluacion`, con sus rutas existentes. No se modificaron
controladores, modelos, endpoints, permisos ni persistencia.

**Cambios visuales realizados únicamente en D31.**

- Se sustituyó el shell legado de la vista por el shell Doctor ya utilizado en
  las pantallas consolidadas, conservando el sidebar, la barra de búsqueda y el
  footer `footer-doctor.php`.
- Se incorporó un encabezado institucional con etiqueta `HISTORIA CLÍNICA`,
  título dinámico escapado, descripción funcional, contexto del paciente cuando
  está disponible y enlace real de regreso al expediente.
- Se organizaron los mapas y el cuestionario en tarjetas de altura natural,
  con controles e instrucciones conservados y canvas responsive.
- Se añadió CSS exclusivamente bajo
  `body.app-shell-body.doctor-clinical-view.doctor-pain-evaluation-page`.
  No se utilizaron `height: 100%`, `order`, `row-reverse` ni `!important` en el
  bloque D31.
- Se normalizaron cuatro interpolaciones PHP dentro del JavaScript para usar
  variables previamente preparadas (`$patientSex` y `$patientId`); las rutas y
  nombres de imagen resultantes no cambian.
- D31 no contiene `toggleSize()`; no se agregó un control de distribución.

**Riesgos y límites conservados.** La preparación GET de
`EvaluacionDolorModel::cuestionarioModulo9()` inserta una fila en
`pc_paciente_evaluacion_dolor` cuando no existe; `imagenFrenteModulo9()` y
`imagenEspaldaModulo9()` pueden copiar imágenes predeterminadas bajo
`public/assets/images/evaluacion-dolor/`. Estas escrituras no se ejecutaron en
esta actividad. El modelo conserva lecturas SQL heredadas sin parametrización
general. Además, el POST de edición del cuestionario recibe `idEvaluacion`,
`detalle`, `edicion` e `idRol`, pero no `idPaciente`; por ello su validación de
contexto mediante `assertPatientAccess()` permanece como riesgo independiente.
No se intervinieron estos problemas por estar fuera del alcance visual.

| Verificación | Resultado |
|---|---|
| Identidad, ruta, vista, controlador y guardas | **PASS estático**: `routes.php`, `ModulosController::moduloVistaDoctor`, `authPermisos()` y `assertPatientAccess()` contrastados. |
| Contratos HTML/JavaScript, IDs, funciones y endpoints | **PASS estático**: `conteFrente`, `conteEspalda`, `contePreguntas`, canvas, funciones de mapas/cuestionario y endpoints preservados. |
| Shell Doctor, búsqueda y footer | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` incluidos; no se conserva `footer-mvsd.php` en D31. |
| Tarjetas de altura natural y canvas responsive | **PASS de CSS estático**; la comprobación visual real requiere navegador. |
| PHP | **PASS**: lint de la vista D31, controladores/modelo utilizados y componentes incluidos. |
| JavaScript | **PASS estático**: scripts externos con `node --check` y bloque inline D31 parseado con `new Function`. |
| CSS scoped y `git diff --check` | **PASS scoped**: bloque D31 balanceado y sin reglas prohibidas; `git diff --check` sólo reporta whitespace histórico fuera del bloque D31 en `app.css`. |
| GET propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de sesión sintética autenticada reproducible; no se consultaron datos reales. |
| AJAX, POST, persistencia y autorización por registro | **NO VERIFICADO / NO EJECUTADO**: no se realizaron escrituras ni operaciones clínicas. |
| Efectos secundarios de GET | **PASS de inspección estática / NO EJECUTADO**: se identificaron los `INSERT` y copias de imágenes descritos arriba, sin ejecutarlos. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667; capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: no existe una sesión sintética autenticada y reproducible en navegador para esta ejecución. |

D31 queda en estado **CERRADA** por aprobación visual expresa del responsable.
La
modernización no implica aprobación de seguridad ni autorización para producción.
Permanecen abiertos ACT-05C-02, los riesgos de propiedad documental, las
lecturas SQL heredadas y la validación del contexto del POST del cuestionario.

### 3.3.10 UI-CLOSE-D32 — Modernización visual del resumen de ficha de identificación

**Identificación verificada.** D32 es el resumen Doctor de la ficha de
identificación. Usa la ruta genérica `GET /resumen/{modulo}/paciente/{idPaciente}`
con `modulo=ficha-identificiacion`, atendida por
`ModulosController::moduloVistaResumen` y renderizada por
`app/views/resumen-clinica-doctor/ficha-identificiacion.php`. El controlador
ejecuta `authPermisos()` y `assertPatientAccess()` antes de obtener los datos
del paciente y configurar el sidebar. D32 no es el formulario editable de D04 ni
el módulo D23.

**Contratos y contenido preservados.** La vista conserva los datos de
identificación, contacto, domicilio, motivo de atención, cuidador, responsable,
`id="contenido-fijo"`, `id="editor"`, `data-paciente`, `data-rol` y
`data-tema`. También conserva `downloadPDF()` con `contenido-fijo`,
`guardarContenidoEditor()`, CKEditor, la ruta POST existente
`/clinica/paciente/guardar-contenido-editor`, las dependencias de impresión y
los scripts del shell. Se añadió `#mensaje` como región accesible porque
`editor-functions.js` la utiliza para informar errores; no se alteró su contrato.

**Cambios visuales realizados únicamente en D32.**

- Se sustituyó el shell heredado por `search-bar-doctor.php`, clases de shell
  Doctor y `footer-doctor.php`; no se modificó el componente compartido.
- Se incorporó un encabezado blanco/lavanda con etiqueta `RESUMEN CLÍNICO`,
  título dinámico escapado, contexto del paciente, regreso al expediente y la
  acción existente de impresión/descarga con nombre accesible.
- Se reorganizó la ficha en una tarjeta de altura natural con secciones
  legibles para datos del paciente, referencia/motivo, domicilio/contacto y
  contacto de apoyo. No se eliminaron los campos que ya renderizaba la vista.
- Se mantuvo el editor de notas en una tarjeta separada, con botón Guardar
  cambios y estados de foco, error y lectura accesibles.
- Se añadió CSS delimitado por
  `body.app-shell-body.doctor-clinical-view.doctor-summary-ficha-page`; el
  bloque no usa alturas de viewport, `height: 100%`, `order`, `row-reverse` ni
  `!important`.
- Los valores dinámicos usados en HTML y en la llamada de impresión se escapan
  o serializan sin cambiar sus destinos ni contenido.

**Riesgos y límites.** La lectura de notas usa `PacienteModulosModelo::contenidoEditorPAC()`
para buscar un archivo HTML bajo `public/assets/notas-paciente/`; el GET no se
ejecutó en esta actividad y no se realizaron escrituras. El guardado de notas,
la generación de PDF y cualquier descarga permanecen NO VERIFICADOS y siguen
sujetos a ACT-05C-02 y sus riesgos documentales. No se modificaron modelos,
controladores, rutas, permisos ni persistencia.

| Verificación | Resultado |
|---|---|
| Ruta, controlador, vista y autorización | **PASS estático**: `routes.php`, `moduloVistaResumen`, `authPermisos()` y `assertPatientAccess()` contrastados. |
| Campos, IDs y acciones originales | **PASS estático**: contenido de ficha, `contenido-fijo`, editor, impresión, guardado y atributos `data-*` preservados. |
| Shell Doctor y footer | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` incluidos; el footer legado ya no se incluye en D32. |
| PHP | **PASS**: lint de la vista, controlador, modelo utilizado y componentes Doctor. |
| JavaScript | **PASS estático**: `node --check` en scripts compartidos, editor y utilidades CKEditor. No se ejecutaron interacciones reales. |
| Contratos HTML/PHP y CSS scoped | **PASS estático**: IDs únicos, handlers conservados, bloque D32 balanceado 50/50 y sin reglas prohibidas. |
| `git diff --check` | **PASS de alcance D32**: la vista no presenta errores; `app.css` conserva únicamente whitespace histórico en líneas ajenas al bloque D32. |
| GET propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de navegador con sesión sintética reproducible. |
| Guardado de notas, PDF, descargas y persistencia | **NO VERIFICADO / NO EJECUTADO**: no se realizaron escrituras ni operaciones documentales. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667; capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: no se generaron capturas ni se ejecutó navegador autenticado. |

D32 queda en estado **CERRADA** por aprobación visual expresa del responsable.
La
modernización no implica aprobación funcional, de seguridad ni autorización para
producción. Permanecen abiertos ACT-05C-02, la validación de propiedad clínica,
el guardado de notas, PDF, descargas y demás pruebas no ejecutadas.

### 3.3.11 UI-CLOSE-D33 — Modernización visual del resumen de antecedentes familiares

**Identificación verificada.** D33 corresponde al resumen de antecedentes
familiares. Usa la ruta genérica `GET /resumen/{modulo}/paciente/{idPaciente}`
con `modulo=antecedentes-familiares`, atendida por
`ModulosController::moduloVistaResumen` y renderizada por
`app/views/resumen-clinica-doctor/antecedentes-familiares.php`. El controlador
ejecuta `authPermisos()` y `assertPatientAccess()` antes de cargar el paciente,
configurar el sidebar y entregar `idPaciente`, `nombre`, `sexo`, `idRol` y el
HTML del sidebar a la vista.

**Contratos y contenido preservados.** La vista conserva el catálogo real de
cinco enfermedades familiares, el contenido generado por
`AntecedenteFamiliarModel::editorTextoAF()`, `id="contenido-fijo"`,
`id="editor"`, `data-paciente`, `data-rol`, `data-tema`, `downloadPDF()` con
su cuarto parámetro original, `guardarContenidoEditor()`, CKEditor, la ruta
POST existente `/clinica/paciente/guardar-contenido-editor` y los scripts del
shell. La salida HTML de la tabla clínica no se reescribió ni se sustituyeron
preguntas, respuestas o terminología.

**Cambios visuales realizados únicamente en D33.**

- Se sustituyó el shell legado por `search-bar-doctor.php`, clases de shell
  Doctor y `footer-doctor.php`; no se modificó ningún componente compartido.
- Se incorporó un encabezado blanco/lavanda con etiqueta `RESUMEN CLÍNICO`,
  título dinámico escapado, contexto autorizado del paciente, regreso al
  expediente y la acción existente de impresión/descarga.
- Se organizó la tabla de antecedentes en una tarjeta de altura natural y las
  notas en una tarjeta separada, con controles legibles y adaptación móvil.
- Se añadió `#mensaje` como región accesible para el contrato existente de
  `editor-functions.js`; no se cambió la función ni su endpoint.
- Se añadió CSS delimitado por
  `body.app-shell-body.doctor-clinical-view.doctor-summary-family-page`.
  El bloque no usa alturas de viewport, `height: 100%`, `order`,
  `row-reverse` ni `!important`.
- Los valores dinámicos del encabezado, atributos, enlace de regreso y llamada
  de impresión se escapan o serializan sin cambiar sus destinos.

**Riesgos y límites conservados.** El bucle de preparación de la vista llama a
`AntecedenteFamiliarModel::antecedentesFamiliares()` para las cinco
enfermedades y ese método ejecuta `INSERT` cuando falta una fila; por tanto,
el GET puede escribir en `pc_antecedentes_familiares`. Este comportamiento se
conserva por alcance y no se ejecutó contra datos reales. La lectura de
`editorTextoAF()` mantiene SQL concatenado y la impresión/PDF, guardado de
notas y descargas permanecen NO VERIFICADOS; ACT-05C-02 continúa abierto.

| Verificación | Resultado |
|---|---|
| Ruta, controlador, vista y autorización | **PASS estático**: `routes.php`, `moduloVistaResumen`, `authPermisos()` y `assertPatientAccess()` contrastados. |
| Campos, IDs y acciones originales | **PASS estático**: catálogo de cinco enfermedades, salida de `editorTextoAF()`, `contenido-fijo`, editor, impresión, guardado y atributos `data-*` preservados. |
| Shell Doctor y footer | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` incluidos; no se conserva el footer legado en D33. |
| PHP | **PASS**: lint de la vista, controlador, modelos utilizados y componentes Doctor. |
| JavaScript | **PASS estático**: `node --check` en scripts compartidos, editor y utilidades CKEditor; no se ejecutaron interacciones reales. |
| Contratos HTML/PHP y CSS scoped | **PASS estático**: IDs y handlers conservados, `#mensaje` disponible para el editor, bloque D33 balanceado y sin reglas prohibidas. |
| `git diff --check` | **PASS de alcance D33**: la vista y el bloque D33 no presentan errores; se conservan únicamente incidencias históricas fuera del bloque en `app.css`. |
| GET propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de navegador con sesión sintética reproducible; no se consultaron datos reales. |
| Siembra GET, guardado de notas, PDF, descargas y persistencia | **NO EJECUTADO / NO VERIFICADO**: se identificaron las escrituras condicionales y no se realizaron operaciones clínicas o documentales. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667; capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: no se generaron capturas ni se ejecutó navegador autenticado. |

D33 queda en estado **CERRADA** por aprobación visual expresa del responsable.
La
modernización no implica aprobación funcional, de seguridad ni autorización
para producción. Permanecen abiertos ACT-05C-02, la escritura durante GET,
la lectura SQL heredada, la propiedad clínica y las pruebas de notas/PDF.

### 3.3.12 UI-CLOSE-D34 — Modernización visual del resumen de antecedentes personales no patológicos

**Identificación verificada.** D34 corresponde al resumen de antecedentes
personales no patológicos. Usa la ruta genérica `GET /resumen/{modulo}/paciente/{idPaciente}`
con `modulo=antecedentes-personales-no-patologicos`, atendida por
`ModulosController::moduloVistaResumen` y renderizada por
`app/views/resumen-clinica-doctor/antecedentes-personales-no-patologicos.php`.
El controlador ejecuta `authPermisos()` y `assertPatientAccess()` antes de
cargar el paciente, configurar el sidebar y entregar el contexto autorizado a
la vista.

**Contratos y contenido preservados.** La vista conserva las siete consultas
de `AntecedentesNoPatologicosModel::editorTextoANP()` —una por cada tema del
módulo—, el comentario de módulo obtenido con
`PacienteModulosModelo::comentariosModulos()`, `id="contenido-fijo"`,
`id="editor"`, `data-paciente`, `data-rol`, `data-tema`, `downloadPDF()`,
`guardarContenidoEditor()`, CKEditor, la ruta POST existente
`/clinica/paciente/guardar-contenido-editor` y los scripts originales. No se
modificaron preguntas, respuestas, terminología ni el orden de los siete
bloques clínicos.

**Cambios visuales realizados únicamente en D34.**

- Se sustituyó el shell legado por `search-bar-doctor.php`, clases de shell
  Doctor y `footer-doctor.php`; no se modificaron componentes compartidos.
- Se incorporó un encabezado blanco/lavanda con etiqueta `RESUMEN CLÍNICO`,
  título dinámico escapado, contexto autorizado del paciente, regreso al
  expediente y la acción existente de impresión/descarga.
- Se organizaron los siete resultados y los comentarios en una tarjeta de
  altura natural, con estados `No registrado` para bloques sin contenido y
  adaptación móvil sin alterar la salida clínica existente.
- Se mantuvo el editor de notas en una tarjeta separada y se añadió `#mensaje`
  como región accesible requerida por `editor-functions.js`; no se alteró la
  función ni su endpoint.
- Se añadió CSS delimitado por
  `body.app-shell-body.doctor-clinical-view.doctor-summary-anp-page`. El bloque
  no usa alturas de viewport, `height: 100%`, `order`, `row-reverse` ni
  `!important`.
- Los valores dinámicos del encabezado, atributos, enlace de regreso y llamada
  de impresión se escapan o serializan sin cambiar sus destinos.

**Riesgos y límites conservados.** El bucle de preparación llama a
`AntecedentesNoPatologicosModel::antecedentesNoPatologicos()` para cada pregunta
del módulo y ese método ejecuta `INSERT` cuando falta una respuesta; por tanto,
el GET puede escribir en `pac_respuestas_paciente_modulo_3`. Las lecturas SQL
heredadas de los modelos, el guardado de notas, la impresión/PDF y cualquier
descarga permanecen NO VERIFICADOS; ACT-05C-02 y los riesgos de autorización
documental continúan abiertos.

| Verificación | Resultado |
|---|---|
| Ruta, controlador, vista y autorización | **PASS estático**: `routes.php`, `moduloVistaResumen`, `authPermisos()` y `assertPatientAccess()` contrastados. |
| Campos, contenido y acciones originales | **PASS estático**: siete bloques de respuestas, comentario de módulo, `contenido-fijo`, editor, impresión, guardado y atributos `data-*` preservados. |
| Shell Doctor y footer | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` incluidos; no se conserva el footer legado en D34. |
| PHP | **PASS**: lint de la vista, controlador, modelos utilizados y componentes Doctor. |
| JavaScript | **PASS estático**: `node --check` en scripts compartidos, editor y utilidades CKEditor; no se ejecutaron interacciones reales. |
| Contratos HTML/PHP y CSS scoped | **PASS estático**: IDs y handlers conservados, `#mensaje` disponible para el editor, bloque D34 balanceado y sin reglas prohibidas. |
| `git diff --check` | **PASS de alcance D34**: la vista y el bloque D34 no presentan errores; se conservan únicamente incidencias históricas fuera del bloque en `app.css`. |
| GET propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de navegador con sesión sintética reproducible; no se consultaron datos reales. |
| Siembra GET, guardado de notas, PDF, descargas y persistencia | **NO EJECUTADO / NO VERIFICADO**: se identificó la escritura condicional y no se realizaron operaciones clínicas o documentales. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667; capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: no se generaron capturas ni se ejecutó navegador autenticado. |

D34 queda en estado **CERRADA** por aprobación visual expresa del responsable.
La
modernización no implica aprobación funcional, de seguridad ni autorización
para producción. Permanecen abiertos ACT-05C-02, la escritura durante GET,
las lecturas SQL heredadas, la autorización por registro y las pruebas de
notas/PDF.

### 3.3.13 UI-CLOSE-D35 — Modernización visual del resumen de antecedentes personales quirúrgicos

**Identificación verificada.** D35 corresponde al resumen de antecedentes
personales quirúrgicos. Usa la ruta genérica `GET /resumen/{modulo}/paciente/{idPaciente}`
con `modulo=antecedentes-personales-quirurgicos`, atendida por
`ModulosController::moduloVistaResumen` y renderizada por
`app/views/resumen-clinica-doctor/antecedentes-personales-quirurgicos.php`.
El controlador ejecuta `authPermisos()` y `assertPatientAccess()` antes de
cargar el paciente, configurar el sidebar y entregar el contexto autorizado a
la vista.

**Contratos y contenido preservados.** La vista conserva la salida de
`AntecedentesQuirurgicos::editorTextoAQ()`, incluyendo sus columnas de fecha,
nombre de la cirugía y observaciones, así como el estado original cuando no
existe información. También conserva `id="contenido-fijo"`, `id="editor"`,
`data-paciente`, `data-rol`, `data-tema`, `downloadPDF()`,
`guardarContenidoEditor()`, CKEditor, la ruta POST existente
`/clinica/paciente/guardar-contenido-editor` y los scripts originales. No se
modificaron preguntas, respuestas, terminología ni valores clínicos.

**Cambios visuales realizados únicamente en D35.**

- Se sustituyó el shell legado por `search-bar-doctor.php`, clases de shell
  Doctor y `footer-doctor.php`; no se modificaron componentes compartidos.
- Se incorporó un encabezado blanco/lavanda con etiqueta `RESUMEN CLÍNICO`,
  título dinámico escapado, contexto autorizado del paciente, regreso al
  expediente y la acción existente de impresión/descarga.
- Se organizó la tabla quirúrgica en una tarjeta de altura natural, con
  encabezados legibles, contenido extenso adaptable y presentación móvil sin
  alterar la tabla generada por el modelo.
- Se mantuvo el editor de notas en una tarjeta separada y se añadió `#mensaje`
  como región accesible requerida por `editor-functions.js`; no se alteró la
  función ni su endpoint.
- Se añadió CSS delimitado por
  `body.app-shell-body.doctor-clinical-view.doctor-summary-aq-page`. El bloque
  no usa alturas de viewport, `height: 100%`, `order`, `row-reverse` ni
  `!important`.
- Los valores dinámicos del encabezado, atributos, enlace de regreso y llamada
  de impresión se escapan o serializan sin cambiar sus destinos.

**Riesgos y límites conservados.** `editorTextoAQ()` realiza una lectura SQL
heredada concatenada para obtener los registros quirúrgicos; no se modificó
el modelo. La vista también lee las notas almacenadas en archivos mediante
`contenidoEditorPAC()`. No se ejecutaron GET autenticados, guardado de notas,
impresión/PDF ni descargas; ACT-05C-02 y los riesgos de autorización
documental continúan abiertos. En la inspección estática de D35 no se observó
una inserción condicional durante la preparación del resumen.

| Verificación | Resultado |
|---|---|
| Ruta, controlador, vista y autorización | **PASS estático**: `routes.php`, `moduloVistaResumen`, `authPermisos()` y `assertPatientAccess()` contrastados. |
| Campos, contenido y acciones originales | **PASS estático**: tabla quirúrgica, estado sin información, `contenido-fijo`, editor, impresión, guardado y atributos `data-*` preservados. |
| Shell Doctor y footer | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` incluidos; no se conserva el footer legado en D35. |
| PHP | **PASS**: lint de la vista, controlador, modelos utilizados y componentes Doctor. |
| JavaScript | **PASS estático**: `node --check` en scripts compartidos, editor y utilidades CKEditor; no se ejecutaron interacciones reales. |
| Contratos HTML/PHP y CSS scoped | **PASS estático**: IDs y handlers conservados, `#mensaje` disponible para el editor, bloque D35 balanceado y sin reglas prohibidas. |
| `git diff --check` | **PASS de alcance D35**: la vista y el bloque D35 no presentan errores; se conservan únicamente incidencias históricas fuera del bloque en `app.css`. |
| GET propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de navegador con sesión sintética reproducible; no se consultaron datos reales. |
| Guardado de notas, PDF, descargas y persistencia | **NO EJECUTADO / NO VERIFICADO**: no se realizaron operaciones clínicas o documentales. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667; capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: no se generaron capturas ni se ejecutó navegador autenticado. |

D35 queda en estado **CERRADA** por aprobación visual expresa del responsable.
La
modernización no implica aprobación funcional, de seguridad ni autorización
para producción. Permanecen abiertos ACT-05C-02, las lecturas SQL heredadas,
la autorización por registro y las pruebas de notas/PDF.

### 3.3.14 UI-CLOSE-D36 — Modernización visual del resumen de antecedentes personales patológicos

**Identificación verificada.** D36 corresponde al resumen de antecedentes
personales patológicos. Usa la ruta genérica `GET /resumen/{modulo}/paciente/{idPaciente}`
con `modulo=antecedentes-personales-patologicos`, atendida por
`ModulosController::moduloVistaResumen` y renderizada por
`app/views/resumen-clinica-doctor/antecedentes-personales-patologicos.php`.
El controlador ejecuta `authPermisos()` y `assertPatientAccess()` antes de
cargar el paciente, configurar el sidebar y entregar el contexto autorizado a
la vista.

**Contratos y contenido preservados.** La vista conserva cuatro bloques de
`AntecedentesPatologicosModel::editorTextoAP()` y el bloque de enfermedades
diagnosticadas de `editorTextoAPE()`, además de sus preguntas, respuestas,
tipos y años de diagnóstico. También conserva `id="contenido-fijo"`,
`id="editor"`, `data-paciente`, `data-rol`, `data-tema`, `downloadPDF()` con
su cuarto parámetro original, `guardarContenidoEditor()`, CKEditor, la ruta
POST existente `/clinica/paciente/guardar-contenido-editor` y los scripts
originales. No se modificaron terminología, orden ni valores clínicos.

**Cambios visuales realizados únicamente en D36.**

- Se sustituyó el shell legado por `search-bar-doctor.php`, clases de shell
  Doctor y `footer-doctor.php`; no se modificaron componentes compartidos.
- Se incorporó un encabezado blanco/lavanda con etiqueta `RESUMEN CLÍNICO`,
  título dinámico escapado, contexto autorizado del paciente, regreso al
  expediente y la acción existente de impresión/descarga.
- Se organizaron los cuatro bloques de respuestas y el registro de
  enfermedades diagnosticadas en una tarjeta de altura natural, preservando
  los valores vacíos de las filas y mostrando `No registrado` sólo cuando el
  bloque generado no contiene información.
- Se mantuvo el editor de notas en una tarjeta separada y se añadió `#mensaje`
  como región accesible requerida por `editor-functions.js`; no se alteró la
  función ni su endpoint.
- Se añadió CSS delimitado por
  `body.app-shell-body.doctor-clinical-view.doctor-summary-ap-page`. El bloque
  no usa alturas de viewport, `height: 100%`, `order`, `row-reverse` ni
  `!important`.
- Los valores dinámicos del encabezado, atributos, enlace de regreso y llamada
  de impresión se escapan o serializan sin cambiar sus destinos.

**Riesgos y límites conservados.** La preparación de la vista llama a
`antecedentesPatologicos()` y `antecedentesPatologicosV2()` para sembrar
respuestas faltantes en `pac_respuestas_paciente_modulo_5` y
`pac_enfermedades_respuestas_modulo_5`; por tanto, el GET puede escribir en
ambas tablas. Los modelos mantienen lecturas SQL concatenadas. No se
ejecutaron GET autenticados, guardado de notas, impresión/PDF ni descargas;
ACT-05C-02 y los riesgos de autorización documental continúan abiertos.

| Verificación | Resultado |
|---|---|
| Ruta, controlador, vista y autorización | **PASS estático**: `routes.php`, `moduloVistaResumen`, `authPermisos()` y `assertPatientAccess()` contrastados. |
| Campos, contenido y acciones originales | **PASS estático**: cuatro bloques clínicos, enfermedades diagnosticadas, preguntas, respuestas, tipos, años, `contenido-fijo`, editor, impresión, guardado y atributos `data-*` preservados. |
| Shell Doctor y footer | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` incluidos; no se conserva el footer legado en D36. |
| PHP | **PASS**: lint de la vista, controlador, modelos utilizados y componentes Doctor. |
| JavaScript | **PASS estático**: `node --check` en scripts compartidos, editor y utilidades CKEditor; no se ejecutaron interacciones reales. |
| Contratos HTML/PHP y CSS scoped | **PASS estático**: IDs y handlers conservados, `#mensaje` disponible para el editor, bloque D36 balanceado y sin reglas prohibidas. |
| `git diff --check` | **PASS de alcance D36**: la vista y el bloque D36 no presentan errores; se conservan únicamente incidencias históricas fuera del bloque en `app.css`. |
| GET propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de navegador con sesión sintética reproducible; no se consultaron datos reales. |
| Siembra GET, guardado de notas, PDF, descargas y persistencia | **NO EJECUTADO / NO VERIFICADO**: se identificaron las escrituras condicionales y no se realizaron operaciones clínicas o documentales. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667; capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: no se generaron capturas ni se ejecutó navegador autenticado. |

D36 queda en estado **CERRADA** por aprobación visual expresa del responsable.
modernización no implica aprobación funcional, de seguridad ni autorización
para producción. Permanecen abiertos ACT-05C-02, las escrituras durante GET,
las lecturas SQL heredadas, la autorización por registro y las pruebas de
notas/PDF.

### 3.3.15 UI-CLOSE-D37 — Modernización visual del resumen de medicación actual

**Identificación verificada.** D37 corresponde al resumen de medicación actual.
Utiliza la ruta genérica `GET /resumen/{modulo}/paciente/{idPaciente}` con
`modulo=medicacion-actual`, atendida por
`ModulosController::moduloVistaResumen` y renderizada por
`app/views/resumen-clinica-doctor/medicacion-actual.php`. El controlador
mantiene `authPermisos()` y `assertPatientAccess()` antes de entregar a la
vista el paciente, el título, el rol y el sidebar autorizado.

**Contratos y contenido preservados.** La vista sigue obteniendo la tabla desde
`MedicacionActualModel::editorTextoMA()` y conserva sus cinco columnas:
nombre del medicamento, motivo de uso, tiempo en tratamiento, dosis actual y
médico que lo recetó. Se mantienen `id="contenido-fijo"`, `id="editor"`,
`id="mensaje"`, `data-paciente`, `data-rol`, `data-tema`, la llamada existente
a `downloadPDF()` con su cuarto parámetro, `guardarContenidoEditor()`, la ruta
de guardado de notas, el contenido clínico generado por el modelo y los
scripts del editor. No se modificaron modelo, controlador, consultas, rutas ni
persistencia.

**Cambios visuales realizados únicamente en D37.**

- Se sustituyó el shell heredado por las clases Doctor, `search-bar-doctor.php`
  y `footer-doctor.php`, sin cambiar componentes compartidos.
- Se incorporó un encabezado blanco/lavanda con etiqueta `RESUMEN CLÍNICO`,
  título escapado, descripción funcional, contexto autorizado del paciente,
  regreso al expediente e impresión/descarga existente.
- Se organizó la tabla de medicación y las notas adicionales en tarjetas de
  altura natural, con controles contenidos, espaciado consistente y adaptación
  móvil. Se añadió el contenedor `#mensaje` requerido por el editor.
- Se añadió CSS delimitado por
  `body.app-shell-body.doctor-clinical-view.doctor-summary-ma-page`; no usa
  `height: 100%`, `order`, `row-reverse` ni `!important`.

**Riesgos y límites conservados.** El modelo mantiene una lectura SQL
concatenada en `editorTextoMA()`; no se confirmó una escritura durante GET en
la inspección de D37. No se ejecutaron GET autenticados, guardado de notas,
impresión/PDF ni descargas. ACT-05C-02, los riesgos documentales y la
autorización por registro continúan abiertos.

| Verificación | Resultado |
|---|---|
| Ruta, controlador, vista y autorización | **PASS estático**: ruta genérica, `moduloVistaResumen`, `authPermisos()` y `assertPatientAccess()` contrastados. |
| Tabla, cinco columnas, notas y acciones | **PASS estático**: `editorTextoMA()`, `contenido-fijo`, editor, guardado, impresión, regreso y atributos `data-*` preservados. |
| Shell Doctor y footer | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` incluidos; D37 ya no incluye `footer-mvsd.php`. |
| PHP | **PASS**: lint de la vista, controlador, modelo y componentes utilizados. |
| JavaScript | **PASS estático**: scripts compartidos y utilidades del editor comprobados con `node --check`; no se ejecutaron interacciones reales. |
| Contratos HTML/PHP y CSS scoped | **PASS estático**: IDs, handlers, `#mensaje` y bloque D37 balanceados; sin reglas prohibidas en el alcance. |
| `git diff --check` | **PASS de alcance D37**: no se introdujeron errores en la vista ni en el bloque D37; permanecen incidencias históricas fuera del alcance. |
| GET propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de navegador con sesión sintética reproducible. |
| Guardado de notas, PDF, descargas y persistencia | **NO EJECUTADO / NO VERIFICADO**: no se realizaron escrituras ni operaciones documentales. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667; capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: no se generaron capturas ni se ejecutó navegador autenticado. |

D37 queda en estado **CERRADA** por aprobación visual expresa del responsable.
modernización no implica aprobación funcional, de seguridad ni autorización
para producción. Permanecen abiertos ACT-05C-02, la lectura SQL heredada, la
autorización por registro y las pruebas de notas/PDF.

### 3.3.16 UI-CLOSE-D38 — Modernización visual del resumen de medicación para controlar el dolor

**Identificación verificada.** D38 corresponde al resumen de los medicamentos
utilizados para controlar el dolor. Usa la ruta genérica
`GET /resumen/{modulo}/paciente/{idPaciente}` con
`modulo=medicacion-control-dolor`, atendida por
`ModulosController::moduloVistaResumen` y renderizada por
`app/views/resumen-clinica-doctor/medicacion-control-dolor.php`. El controlador
mantiene `authPermisos()` y `assertPatientAccess()` antes de entregar el
contexto autorizado del paciente a la vista.

**Contratos y contenido preservados.** La vista conserva los seis temas
clínicos obtenidos por `MedicacionDolorModel::editorTextoMD()` y las columnas,
respuestas, dosis, resultados, descripciones y continuidad del uso generadas
por el modelo. Se mantienen `id="contenido-fijo"`, `id="editor"`,
`id="mensaje"`, `data-paciente`, `data-rol`, `data-tema`, la llamada existente
a `downloadPDF()` sin agregar parámetros, `guardarContenidoEditor()`, la ruta
de guardado de notas, los seis identificadores de tema y los scripts del
editor. No se modificaron modelo, controlador, consultas, rutas ni
persistencia.

**Cambios visuales realizados únicamente en D38.**

- Se sustituyó el shell heredado por las clases Doctor, `search-bar-doctor.php`
  y `footer-doctor.php`, sin modificar los componentes compartidos.
- Se incorporó un encabezado blanco/lavanda con etiqueta `RESUMEN CLÍNICO`,
  título escapado, descripción funcional, contexto autorizado del paciente,
  regreso al expediente e impresión/descarga existente.
- Se organizaron los seis bloques clínicos en una tarjeta de altura natural,
  con tablas legibles, estados `No registrado` sólo cuando el bloque no genera
  información y una tarjeta independiente para notas adicionales.
- Se añadió CSS delimitado por
  `body.app-shell-body.doctor-clinical-view.doctor-summary-md-page`; no usa
  `height: 100%`, `order`, `row-reverse` ni `!important`.

**Riesgos y límites conservados.** La preparación de la vista llama a
`medicacionDolorQuest()` para insertar respuestas faltantes en
`pac_respuestas_paciente_modulo_7`; por tanto, el GET puede escribir en esa
tabla. El modelo mantiene lecturas SQL concatenadas. No se ejecutaron GET
autenticados, guardado de notas, impresión/PDF ni descargas. ACT-05C-02, los
riesgos documentales y la autorización por registro continúan abiertos.

| Verificación | Resultado |
|---|---|
| Ruta, controlador, vista y autorización | **PASS estático**: ruta genérica, `moduloVistaResumen`, `authPermisos()` y `assertPatientAccess()` contrastados. |
| Seis bloques, contenido clínico y acciones | **PASS estático**: temas 1–6, respuestas, dosis, resultados, `contenido-fijo`, editor, guardado, impresión y regreso preservados. |
| Shell Doctor y footer | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` incluidos; D38 ya no incluye `footer-mvsd.php`. |
| PHP | **PASS**: lint de la vista, controlador, modelo y componentes utilizados. |
| JavaScript | **PASS estático**: scripts compartidos y utilidades del editor comprobados con `node --check`; no se ejecutaron interacciones reales. |
| Contratos HTML/PHP y CSS scoped | **PASS estático**: IDs, handlers, `#mensaje` y bloque D38 balanceados; sin reglas prohibidas en el alcance. |
| `git diff --check` | **PASS de alcance D38**: no se introdujeron errores en la vista ni en el bloque D38; permanecen incidencias históricas fuera del alcance. |
| GET propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de navegador con sesión sintética reproducible. |
| Siembra GET, guardado de notas, PDF, descargas y persistencia | **NO EJECUTADO / NO VERIFICADO**: no se realizaron escrituras ni operaciones documentales. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667; capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: no se generaron capturas ni se ejecutó navegador autenticado. |

D38 queda en estado **CERRADA** por aprobación visual expresa del responsable.
modernización no implica aprobación funcional, de seguridad ni autorización
para producción. Permanecen abiertos ACT-05C-02, la escritura durante GET,
las lecturas SQL heredadas, la autorización por registro y las pruebas de
notas/PDF.

### 3.3.17 UI-CLOSE-D39 — Modernización visual del resumen de procedimientos para controlar el dolor

**Identificación verificada.** D39 corresponde al resumen de procedimientos y
tratamientos utilizados para controlar el dolor. Usa la ruta genérica
`GET /resumen/{modulo}/paciente/{idPaciente}` con
`modulo=procedimientos-control-dolor`, atendida por
`ModulosController::moduloVistaResumen` y renderizada por
`app/views/resumen-clinica-doctor/procedimientos-control-dolor.php`. El
controlador mantiene `authPermisos()` y `assertPatientAccess()` antes de
entregar el contexto autorizado del paciente a la vista.

**Contratos y contenido preservados.** La vista conserva la preparación de
respuestas mediante `ProcedimientosDolorModel::procedimientosModulo8()`, la
tabla de procedimientos de `editorTextoPD()` y la tabla de tratamientos de
`editorTextoTD()`. Se mantienen las columnas, fechas, resultados,
descripciones, comentarios, `id="contenido-fijo"`, `id="editor"`,
`id="mensaje"`, `data-paciente`, `data-rol`, `data-tema`, la llamada existente
a `downloadPDF()` sin agregar parámetros, `guardarContenidoEditor()`, la ruta
de guardado de notas y los scripts del editor. No se modificaron modelo,
controlador, consultas, rutas ni persistencia.

**Cambios visuales realizados únicamente en D39.**

- Se sustituyó el shell heredado por las clases Doctor, `search-bar-doctor.php`
  y `footer-doctor.php`, sin modificar los componentes compartidos.
- Se incorporó un encabezado blanco/lavanda con etiqueta `RESUMEN CLÍNICO`,
  título escapado, descripción funcional, contexto autorizado del paciente,
  regreso al expediente e impresión/descarga existente.
- Se organizaron procedimientos y tratamientos en secciones clínicas dentro
  de una tarjeta de altura natural, conservando ambas tablas y sus estados
  generados por el modelo. Las notas adicionales permanecen en una tarjeta
  independiente.
- Se añadió CSS delimitado por
  `body.app-shell-body.doctor-clinical-view.doctor-summary-pd-page`; no usa
  `height: 100%`, `order`, `row-reverse` ni `!important`.

**Riesgos y límites conservados.** La preparación de la vista llama a
`procedimientosModulo8()` para insertar respuestas faltantes en
`pac_respuestas_paciente_modulo_8`; por tanto, el GET puede escribir en esa
tabla. Los métodos `editorTextoPD()` y `editorTextoTD()` mantienen lecturas SQL
concatenadas. No se ejecutaron GET autenticados, guardado de notas,
impresión/PDF ni descargas. ACT-05C-02, los riesgos documentales y la
autorización por registro continúan abiertos.

| Verificación | Resultado |
|---|---|
| Ruta, controlador, vista y autorización | **PASS estático**: ruta genérica, `moduloVistaResumen`, `authPermisos()` y `assertPatientAccess()` contrastados. |
| Procedimientos, tratamientos, tablas y acciones | **PASS estático**: `procedimientosModulo8()`, `editorTextoPD()`, `editorTextoTD()`, columnas, fechas, resultados, notas, impresión y regreso preservados. |
| Shell Doctor y footer | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` incluidos; D39 ya no incluye `footer-mvsd.php`. |
| PHP | **PASS**: lint de la vista, controlador, modelo y componentes utilizados. |
| JavaScript | **PASS estático**: scripts compartidos y utilidades del editor comprobados con `node --check`; no se ejecutaron interacciones reales. |
| Contratos HTML/PHP y CSS scoped | **PASS estático**: IDs, handlers, `#mensaje` y bloque D39 balanceados; sin reglas prohibidas en el alcance. |
| `git diff --check` | **PASS de alcance D39**: no se introdujeron errores en la vista ni en el bloque D39; permanecen incidencias históricas fuera del alcance. |
| GET propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de navegador con sesión sintética reproducible. |
| Siembra GET, guardado de notas, PDF, descargas y persistencia | **NO EJECUTADO / NO VERIFICADO**: no se realizaron escrituras ni operaciones documentales. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667; capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: no se generaron capturas ni se ejecutó navegador autenticado. |

D39 queda en estado **CERRADA** por aprobación visual expresa del responsable.
modernización no implica aprobación funcional, de seguridad ni autorización
para producción. Permanecen abiertos ACT-05C-02, la escritura durante GET,
las lecturas SQL heredadas, la autorización por registro y las pruebas de
notas/PDF.

### 3.3.18 UI-CLOSE-D40 — Modernización visual del resumen de evaluación del dolor

**Identificación verificada.** D40 corresponde al resumen Doctor de evaluación
del dolor. Usa la ruta genérica `GET /resumen/{modulo}/paciente/{idPaciente}`
con `modulo=evaluacion-dolor`, atendida por
`ModulosController::moduloVistaResumen` y renderizada por
`app/views/resumen-clinica-doctor/evaluacion-dolor.php`. El controlador aplica
`authPermisos()` y `assertPatientAccess()` antes de entregar el contexto del
paciente a la vista.

**Contratos y contenido preservados.** La vista conserva la preparación de la
respuesta mediante `EvaluacionDolorModel::cuestionarioModulo9()`, la tabla
generada por `editorTextoED()`, las imágenes frente/espalda, `id="contenido-fijo"`,
`id="editor"`, `id="mensaje"`, `data-paciente`, `data-rol`, `data-tema`,
`downloadPDF()` y `guardarContenidoEditor()`. Se mantienen los scripts del
editor, las rutas, los parámetros, los permisos y la persistencia; no se
modificaron modelo, controlador ni consultas.

**Cambios visuales realizados únicamente en D40.**

- Se sustituyó el shell heredado por las clases Doctor, `search-bar-doctor.php`
  y `footer-doctor.php`.
- Se incorporó un encabezado blanco/lavanda con etiqueta `RESUMEN CLÍNICO`,
  título escapado, descripción basada en la evaluación, contexto autorizado,
  regreso al expediente y la acción existente de impresión/descarga.
- Se organizaron el mapa frontal/posterior, las instrucciones y las respuestas
  en una tarjeta clínica de altura natural; las notas adicionales permanecen en
  una tarjeta independiente con el editor existente.
- Se añadió CSS delimitado por
  `body.app-shell-body.doctor-clinical-view.doctor-summary-ed-page`; no usa
  `height: 100%`, `order`, `row-reverse`, `overflow: hidden` ni `!important`.

**Riesgos y límites conservados.** `cuestionarioModulo9()` puede insertar una
respuesta inicial durante GET. `editorTextoED()` mantiene una lectura SQL
concatenada y la operación de notas/PDF no fue ejecutada. El acceso cruzado,
la propiedad clínica de los archivos de imágenes y los riesgos documentales de
ACT-05C-02 permanecen abiertos.

| Verificación | Resultado |
|---|---|
| Ruta, controlador, vista y autorización | **PASS estático**: ruta genérica, `moduloVistaResumen`, `authPermisos()` y `assertPatientAccess()` contrastados. |
| Mapas, respuestas, notas y acciones | **PASS estático**: `cuestionarioModulo9()`, `editorTextoED()`, imágenes, editor, impresión, guardado y regreso preservados. |
| Shell Doctor y footer | **PASS estático**: `search-bar-doctor.php` y `footer-doctor.php` incluidos; D40 ya no incluye `footer-mvsd.php`. |
| PHP | **PASS**: lint de la vista, controlador, modelo y componentes utilizados. |
| JavaScript | **PASS estático**: scripts compartidos y utilidades del editor comprobados con `node --check`; no se ejecutaron interacciones reales. |
| Contratos HTML/PHP y CSS scoped | **PASS estático**: IDs, handlers, `#mensaje` y bloque D40 balanceados; sin reglas prohibidas en el alcance. |
| `git diff --check` | **PASS de alcance D40**: no se introdujeron errores en la vista ni en el bloque D40; permanecen incidencias históricas fuera del alcance. |
| GET propio, cruzado y sin sesión | **NO VERIFICADO**: no se dispuso de navegador con sesión sintética reproducible. |
| Siembra GET, guardado de notas, PDF, descargas y persistencia | **NO EJECUTADO / NO VERIFICADO**: no se realizaron escrituras ni operaciones documentales. |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667; capturas, foco, teclado, overflow y consola | **NO VERIFICADO**: no se generaron capturas ni se ejecutó navegador autenticado. |

D40 queda en estado **CERRADA** por aprobación visual expresa del responsable.
modernización no implica aprobación funcional, de seguridad ni autorización
para producción. Permanecen abiertos ACT-05C-02, la escritura durante GET,
las lecturas SQL heredadas, la autorización por registro y las pruebas de
notas/PDF.

### 3.4 Nueve resúmenes clínicos del Doctor

Todas estas pantallas usan GET /resumen/{modulo}/paciente/{idPaciente},
ModulosController::moduloVistaResumen y una vista separada en
app/views/resumen-clinica-doctor/. Son resúmenes de lectura/consulta; no son
la misma plantilla que D23–D31.

| ID | Módulo resumido | Vista PHP | Acciones principales | Dependencias visuales | Estado | Pendientes |
|---|---|---|---|---|---|---|
| D32 | Ficha de identificación | resumen-clinica-doctor/ficha-identificiacion.php | Ver resumen, regresar al expediente, imprimir/descargar y guardar notas adicionales | Shell Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-summary-ficha-page`, CSS scoped | **CERRADA** | UI-CLOSE-D32 (2026-10-10): modernización visual estática completada; shell Doctor, encabezado institucional, ficha reorganizada, editor de notas, impresión y regreso contextual preservados. PHP/JS/CSS y contratos estáticos PASS. GET autenticado, POST de notas, PDF, capturas, viewports, consola e interacciones reales NO VERIFICADOS. Aprobación visual expresa del responsable confirmada en UI-CLOSE-D37; se conservan las limitaciones funcionales y de seguridad. |
| D33 | Antecedentes familiares | resumen-clinica-doctor/antecedentes-familiares.php | Ver resumen, regresar al expediente, imprimir/descargar y guardar notas adicionales | Shell Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-summary-family-page`, CSS scoped | **CERRADA** | UI-CLOSE-D33 (2026-10-10): modernización visual estática completada; encabezado institucional, tarjeta de antecedentes, notas, impresión y regreso contextual preservados. PHP/JS/CSS y contratos estáticos PASS. GET autenticado, siembra GET, POST de notas, PDF, capturas, viewports, consola e interacciones reales NO VERIFICADOS. Aprobación visual expresa del responsable confirmada en UI-CLOSE-D37; se conservan las limitaciones funcionales y de seguridad. |
| D34 | Antecedentes No Patológicos | resumen-clinica-doctor/antecedentes-personales-no-patologicos.php | Ver resumen, regresar al expediente, imprimir/descargar y guardar notas adicionales | Shell Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-summary-anp-page`, CSS scoped | **CERRADA** | UI-CLOSE-D34 (2026-10-10): modernización visual estática completada; encabezado institucional, siete bloques clínicos, comentarios, notas, impresión y regreso contextual preservados. PHP/JS/CSS y contratos estáticos PASS. GET autenticado, siembra GET, POST de notas, PDF, capturas, viewports, consola e interacciones reales NO VERIFICADOS. Aprobación visual expresa del responsable confirmada en UI-CLOSE-D37; se conservan las limitaciones funcionales y de seguridad. |
| D35 | Antecedentes Quirúrgicos | resumen-clinica-doctor/antecedentes-personales-quirurgicos.php | Ver resumen, regresar al expediente, imprimir/descargar y guardar notas adicionales | Shell Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-summary-aq-page`, CSS scoped | **CERRADA** | UI-CLOSE-D35 (2026-10-10): modernización visual estática completada; encabezado institucional, tabla quirúrgica, notas, impresión y regreso contextual preservados. PHP/JS/CSS y contratos estáticos PASS. GET autenticado, POST de notas, PDF, capturas, viewports, consola e interacciones reales NO VERIFICADOS. Aprobación visual expresa del responsable confirmada en UI-CLOSE-D37; se conservan las limitaciones funcionales y de seguridad. |
| D36 | Antecedentes Patológicos | resumen-clinica-doctor/antecedentes-personales-patologicos.php | Ver resumen, regresar al expediente, imprimir/descargar y guardar notas adicionales | Shell Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-summary-ap-page`, CSS scoped | **CERRADA** | UI-CLOSE-D36 (2026-10-10): modernización visual estática completada; encabezado institucional, cuatro bloques patológicos, enfermedades diagnosticadas, notas, impresión y regreso contextual preservados. PHP/JS/CSS y contratos estáticos PASS. GET autenticado, siembra GET, POST de notas, PDF, capturas, viewports, consola e interacciones reales NO VERIFICADOS. Aprobación visual expresa del responsable confirmada; se conservan las limitaciones funcionales y de seguridad. |
| D37 | Medicación Actual | resumen-clinica-doctor/medicacion-actual.php | Ver resumen, regresar, imprimir/descargar y guardar notas adicionales | Shell Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-summary-ma-page`, CSS scoped | **CERRADA** | UI-CLOSE-D37 (2026-10-10): modernización visual estática completada; encabezado institucional, tabla de medicación, editor de notas, impresión y regreso contextual preservados. PHP/JS/CSS y contratos estáticos PASS. GET autenticado, POST de notas, PDF, capturas, viewports, consola e interacciones reales NO VERIFICADOS. Aprobación visual expresa del responsable confirmada; se conservan las limitaciones funcionales y de seguridad. |
| D38 | Medicación para control del dolor | resumen-clinica-doctor/medicacion-control-dolor.php | Ver resumen, regresar, imprimir/descargar y guardar notas adicionales | Shell Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-summary-md-page`, CSS scoped | **CERRADA** | UI-CLOSE-D38 (2026-10-10): modernización visual estática completada; encabezado institucional, seis bloques de medicación, notas, impresión y regreso contextual preservados. PHP/JS/CSS y contratos estáticos PASS. GET autenticado, siembra GET, POST de notas, PDF, capturas, viewports, consola e interacciones reales NO VERIFICADOS. Aprobación visual expresa del responsable confirmada; se conservan las limitaciones funcionales y de seguridad. |
| D39 | Procedimientos para control del dolor | resumen-clinica-doctor/procedimientos-control-dolor.php | Ver resumen, regresar, imprimir/descargar y guardar notas adicionales | Shell Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-summary-pd-page`, CSS scoped | **CERRADA** | UI-CLOSE-D39 (2026-10-10): modernización visual estática completada; encabezado institucional, procedimientos, tratamientos, notas, impresión y regreso contextual preservados. PHP/JS/CSS y contratos estáticos PASS. GET autenticado, siembra GET, POST de notas, PDF, capturas, viewports, consola e interacciones reales NO VERIFICADOS. Aprobación visual expresa del responsable confirmada; se conservan las limitaciones funcionales y de seguridad. |
| D40 | Evaluación del dolor | GET `/resumen/evaluacion-dolor/paciente/{idPaciente}` | `app/views/resumen-clinica-doctor/evaluacion-dolor.php` | `ModulosController::moduloVistaResumen` | Consultar mapa frontal/posterior y respuestas de evaluación; imprimir/descargar resumen; editar y guardar notas adicionales; volver al expediente | Shell Doctor, `search-bar-doctor.php`, `footer-doctor.php`, `doctor-summary-ed-page`, CKEditor, `EvaluacionDolorModel`, `PacienteModulosModelo` | **CERRADA** | UI-CLOSE-D40 (2026-10-10): modernización visual exclusiva de la vista. Se incorporaron shell Doctor, encabezado institucional, contexto del paciente autorizado, regreso al expediente, acción de impresión/descarga, tarjetas de mapas/respuestas y notas, manteniendo `#contenido-fijo`, `#editor`, `guardarContenidoEditor()`, `#mensaje`, `downloadPDF()`, rutas, IDs y contratos existentes. Se conservaron el llamado de preparación de evaluación durante GET, la tabla generada por `EvaluacionDolorModel::editorTextoED()`, imágenes frente/espalda y el editor de notas; no se modificaron modelos, controlador, permisos, cálculos ni persistencia. PHP/JS/CSS y contratos estáticos PASS. GET autenticado, aislamiento cruzado, capturas, viewports, consola, impresión/PDF, guardado de notas y efectos de escritura durante GET NO VERIFICADOS en esta ejecución por no disponer de una sesión/base sintética segura. Aprobación visual expresa del responsable confirmada; permanecen abiertos SQL concatenado, escrituras durante GET y riesgos documentales de ACT-05C-02. |

### 3.5 UI-KPI-DOCTOR-01 — Centro de indicadores clínicos

| ID | Pantalla | Ruta real y parámetros | Vista PHP | Controlador | Acciones principales | Dependencias visuales | Estado | Evidencia y pendientes |
|---|---|---|---|---|---|---|---|---|
| D41 | Centro de indicadores clínicos | GET `/clinica/indicadores`; filtro opcional `?year=YYYY` | `app/views/clinica/indicadores.php` | `IndicadoresController::index`; agregación en `ClinicaIndicadoresService` | Consultar KPI agregados, filtrar por año, cambiar secciones, consultar gráficos y tablas alternativas | Shell Doctor, `search-bar-doctor.php`, `footer-doctor.php`, CSS scoped `doctor-kpi-page`, JS local | **CERRADA** | UI-KPI-DOCTOR-01 (2026-10-10), UI-FIX-D41-01 (2026-10-10), UI-FIX-D41-02 (2026-10-10), UI-FIX-D41-03 (2026-10-10) y UI-FIX-D41-04 (2026-10-10): se conservan las siete métricas reales, iconos Feather, acentos scoped y anillo SVG de avance HC basado en el valor canónico. Panorama presenta pacientes, notas, laboratorios, recetas internas y COFEPRIS en una sola columna de gráficas independientes y de ancho completo; todas comparten ahora tarjeta blanca `#FFFFFF`, borde, radio, sombra y padding institucionales, incluidos los estados vacíos. Se conservan títulos, unidades, ejes, valores, tablas alternativas, orden temporal y estados `Sin fecha válida`; los colores de series son ámbar, cian, coral e índigo. No se modificaron servicios, controladores, modelos, consultas, rutas, permisos ni las siete tarjetas KPI. PHP/JS/CSS y pruebas puras/estáticas PASS. Base sintética, aislamiento cruzado, navegación autenticada, consola, capturas y viewports NO VERIFICADOS por no disponer de una base sintética conectada. Aprobación visual expresa confirmada en GIT-GITHUB-DOCTOR-01; el cierre visual no implica seguridad ni producción. |

## 4. Inventario Paciente

### 4.1 Acceso y dashboard

| ID | Pantalla | Ruta real y parámetros | Vista PHP | Controlador | Acciones principales | Dependencias visuales | Estado | Evidencia y pendientes |
|---|---|---|---|---|---|---|---|---|
| P01 | Login Paciente | GET `/historia-clinica/acceso`; POST `/historia-clinica/login` es endpoint | `app/views/login/login-paciente.php` | `LoginController::accesoPaciente`; autenticación en `LoginController::loginPaciente` | Capturar PIN, entrar y cambiar al portal médico | `auth-visual.php`, `auth-icon.php`, `app.css` compartido | **VALIDACIÓN** | UI-CLOSE-P01 (2026-10-11) y UI-FIX-P01-01 (2026-10-11): identificación verificada contra `routes.php`, `LoginController.php` y la vista real. Se conservó `id="Pin"`, `loginPaciente()`, `responseMessage`, validación/limpieza de errores, prevención de doble envío, redirección y enlace a `/clinica/acceso`; sólo se mantuvo la asociación accesible de la descripción al PIN. El diagnóstico confirmó que el selector `body.auth-page-patient .auth-form-card` añadía la tarjeta flotante; se eliminó ese override y P01 vuelve a utilizar el mismo tratamiento compartido del panel derecho que Doctor: fondo uniforme, sin borde, radio ni sombra interior, con ancho y padding determinados por `.auth-form-panel` y `.auth-form-card` comunes. PHP lint, contratos estáticos, sintaxis JS y `git diff --check` PASS. Comparación real a 1440×900 PASS: Doctor y Paciente conservan las dos columnas y el panel derecho sin tarjeta interior; viewports 1280×720, 768×1024, 430×932 y 375×667, overflow, PIN vacío, alerta accesible, limpieza al corregir, destino del enlace médico y consola sin errores PASS. Autenticación válida/ inválida contra servidor, sesión sintética aislada y zoom 200% NO VERIFICADOS. Requiere aprobación visual expresa; P02–P11 y Doctor permanecen sin cambios. |
| P02 | Dashboard Paciente | GET `/historia-clinica` | `app/views/paciente/index.php` | `HomeController::indexPaciente` | Ver 9 módulos, comenzar/continuar el siguiente módulo disponible, logout | Sidebar Paciente, portal-topbar, `footer-doctor.php`, `dashboard-patient` | **VALIDACIÓN** | **UI-CLOSE-P02 (2026-10-11):** se reorganizó exclusivamente la vista en hero compacto «Mi Historia Clínica», panel único de progreso y catálogo de módulos en dos columnas de escritorio/una en móvil. Se conservaron el catálogo `historia_clinica_modulos`, las llamadas `PacienteModulosModelo::porcentajeModulo1()`–`porcentajeModulo9()`, las finalizaciones de `pac_historia_clinica_finalizar`, el primer módulo no finalizado, URLs, bloqueo de módulos finalizados, sidebar, topbar y cierre de sesión. PASS estático: PHP lint, contratos de datos/ARIA, nueve llamadas de porcentaje, sintaxis JavaScript, integridad CSS y `git diff --check`. NO VERIFICADO: capturas autenticadas en 1440×900, 1280×720, 768×1024, 430×932 y 375×667, escenarios sintéticos sin iniciar/parcial/completa/100 % sin finalización/catálogo vacío/fallo controlado, teclado y consola en navegador; no se dispuso de una sesión y base sintéticas aisladas autorizadas para ejecutar esas pruebas. Riesgo pendiente fuera de alcance: el `catch` de la vista expone el mensaje de `PDOException` mediante `die()`; no se modificó. Requiere aprobación visual expresa. |

### 4.2 Nueve módulos clínicos del Paciente

Todas estas pantallas usan GET
/historia-clinica/{modulo}/paciente/{idPaciente}, el método
ModulosController::moduloVistaPaciente y una plantilla separada en
app/views/historia-clinica-paciente/. Los nueve formularios conservan sus
preguntas, IDs, AJAX, guardado y finalización existentes.

| ID | Módulo | Vista PHP | Acciones principales | Dependencias visuales | Estado | Evidencia y pendientes |
|---|---|---|---|---|---|---|
| P03 | Ficha de identificación del paciente | GET `/historia-clinica/ficha-identificiacion/paciente/{idPaciente}`; `app/views/historia-clinica-paciente/ficha-identificiacion.php` | Consultar datos personales, finalizar módulo según condición existente y volver a Historia Clínica | `app-shell-patient`, `patient-clinical-form`, `footer-doctor.php`, `patient-identification-page`, CSS scoped | **VALIDACIÓN** | **UI-CLOSE-P03 (2026-10-11) + UI-FIX-P03-01 (2026-10-11):** vista y ruta verificadas contra `routes.php`, `ModulosController::moduloVistaPaciente` y la plantilla real. Se reorganizaron los 30 valores visibles derivados de 29 claves del controlador en cuatro tarjetas: información personal, domicilio, contacto/responsables y motivo/referencia. Se conservaron `botonFinalizarModulo()`, `gestionarFichaPaciente()`, `finalizarModuloPAC()`, el endpoint, parámetros, redirección, sidebar, footer y datos originales; `cuidador` mantiene sus dos usos heredados (estado y nombre) y queda documentado como ambigüedad funcional. Se añadieron escape HTML para valores dinámicos, estado «No registrado», `lang="es-MX"`, encabezado institucional y una única región `#mensaje` accesible requerida por el JavaScript. UI-FIX-P03-01 confirmó que `.btn-light` global imponía `background: #fff`; el enlace `patient-identification-back` ahora tiene fondo `#F3E8FA`, texto `#5B1978`, borde `#AB47BC` y estados hover/focus/active scoped, conservando el `<a>` hacia `/historia-clinica`, icono y orden DOM. PHP lint, sintaxis JavaScript, inventario estático de campos/IDs, integridad CSS y `git diff --check` PASS. GET autenticado/cruzado/sin sesión, cinco viewports, capturas sintéticas, consola, hover/focus observados en navegador, finalización real y estados de módulo NO VERIFICADOS por falta de sesión/base sintética aislada; no se ejecutaron escrituras ni finalizaciones reales. P03 permanece en VALIDACIÓN hasta aprobación visual expresa. |
| P04 | Antecedentes familiares | GET `/historia-clinica/antecedentes-familiares/paciente/{idPaciente}`; `app/views/historia-clinica-paciente/antecedentes-familiares.php` | Responder preguntas una a una, seleccionar Sí/No, completar dependencias de diabetes y familiar afectado, agregar/editar/eliminar enfermedades, escuchar instrucciones, comentar y finalizar | `app-shell-patient`, `patient-clinical-form`, `footer-doctor.php`, endpoint `/buscar/contenido-preguntas-modulo-2/{idPaciente}/{idRol}`, `voice-utilities.js`, CSS scoped `patient-family-history-page` | **VALIDACIÓN** | **UI-CLOSE-P04 + P04-FUNC-01 (2026-10-11) + P04-FUNC-03 (2026-10-11) + P04-FUNC-04 (2026-10-11):** se conservaron preguntas, respuestas, dependencias, IDs, AJAX, audio, comentarios y finalización; se añadieron modal accesible, controles manuales de voz, voz `es-MX` preferente, cancelación al navegar, claves `localStorage` aisladas por paciente/módulo, `idModulo = 2`, guardas para preguntas vacías y errores AJAX con cierre del loader. El guardado Sí/No ahora envía `idPaciente`, deriva el ámbito por ruta y restringe la edición por `id` e `id_paciente`. P04 versiona `app.css` y los scripts locales propios con `filemtime()` sobre rutas reales dentro de `public/`; las dependencias de terceros no se alteraron. El modal intenta la lectura inicial sin afirmar reproducción antes de `onstart`, mantiene fallback y resalta «Escuchar» cuando el navegador la bloquea; los controles quedaron reducidos a «Escuchar»/«Detener», con parada sólo visible para la pregunta activa mientras reproduce y lectura de la nueva pregunta después de cambiar el DOM. Los títulos clínicos de P04 reutilizan `var(--clinic-accent)` (`#35a78f`) sin cambiar el hero ni el área Doctor. PHP lint, JS externo, JS inline, CSS, versionado sintético, compatibilidad estática del utilitario compartido y contratos estáticos PASS. Audio real, modal en navegador, Network/404, AJAX, persistencia, autorización cruzada, consola, responsive y capturas NO VERIFICADOS por falta de sesión/base sintética aislada. Riesgos abiertos: siembra durante GET y operaciones de agregado/eliminación fuera de esta corrección. |
| P05 | Antecedentes Personales No Patológicos | historia-clinica-paciente/antecedentes-personales-no-patologicos.php | Responder cuestionario, guardar, comentar y finalizar | `patient-clinical-form`, `patient-nonpathological-page`, AJAX módulo 3, modal y voz P05 | **VALIDACIÓN** | **P05-RESTORE-REAL-01 (2026-10-11):** se extrajeron de los SQL originales suministrados exactamente 7 temas y 25 preguntas (6 `select`, 19 `input`) a `tests/fixtures/p05-catalog.php`; no se ejecutaron dumps completos ni se tocó `bd_clinica`. La base sintética allowlisted confirmó sesión real, AJAX `contenidoPreguntasM3()`, dependencias, recuperación, recorrido de siete temas, Grupo Sanguineo, comentarios, finalización y `next_url` a P06. GET/POST cruzados fueron rechazados. Se corrigieron en CSS scoped dos defectos demostrados con catálogo real: todas las preguntas quedaban visibles por un override de `.pregunta-container`, y la ayuda de navegación provocaba overflow por `flex: 1 0 100%`. 1440×900 y 375×667 PASS sin overflow; consola sin errores; reproducción audible humana, 1280×720, 768×1024, fallo forzado de red y dashboard posterior NO VERIFICADOS. `tests/p05/cleanup-synthetic.php` restauró respuestas, comentario y finalización de 9301/9302, manteniendo el catálogo 7/25. Se conserva el efecto secundario heredado de siembra durante GET y ACT-05C-02. P05 permanece en VALIDACIÓN. |
| P06 | Antecedentes Personales Quirúrgicos | historia-clinica-paciente/antecedentes-personales-quirurgicos.php | Agregar/editar/eliminar cirugías y guardar | `patient-clinical-form`, AJAX módulo 4, modal de instrucciones y voz P06 | **VALIDACIÓN** | **P06-COMPLETE-01 (2026-10-11):** se modernizó exclusivamente el módulo 4 con el patrón visual de P04/P05: hero blanco/menta, título institucional, contexto del paciente, acciones de ayuda/regreso, tarjeta única de altura natural, estado vacío explícito y tarjetas editables para cirugía, fecha y observaciones. Se conservaron los nombres de campos y el flujo existente; se añadieron labels, foco visible, navegación anterior/siguiente, agregar otra cirugía, eliminación con confirmación y finalización centralizada mediante `next_url`. El modal accesible y la narración específica de P06 cancelan audio anterior, soportan Escuchar/Detener y no bloquean la captura. Las lecturas, escrituras y eliminaciones quedaron restringidas al paciente autorizado mediante sesión/ruta, propiedad del registro y consultas parametrizadas; no se confía en `idRol` del cliente. PHP lint, contratos, JS inline, CSS, mock de audio, aislamiento, acceso HTTP propio/cruzado/sin sesión, CRUD HTTP sintético con limpieza y regresiones focales P04/P05 PASS. Capturas reales de estado vacío, modal y formulario a 1440×900 y 375×667 PASS en navegador local sintético; no se exportaron como archivos independientes. Reproducción audible real NO VERIFICADA; no se usaron datos reales ni `bd_clinica`. P06 permanece en VALIDACIÓN hasta revisión visual expresa. |
| P07 | Antecedentes Personales Patológicos | GET `/historia-clinica/antecedentes-personales-patologicos/paciente/{idPaciente}`; `app/views/historia-clinica-paciente/antecedentes-personales-patologicos.php` | Responder/editar preguntas V1, enfermedades V2, dependencias, guardar, avanzar y finalizar | `app-shell-patient`, `patient-clinical-form`, AJAX módulo 5, `footer-doctor.php`, `voice-utilities.js`, CSS institucional reutilizado | **VALIDACIÓN** | **P07-COMPLETE-01 + adendum P05-FLOW-SPEED-01 (2026-10-11):** se conservaron rutas, IDs, campos, catálogo clínico y diferencias V1/V2. El paciente ahora usa eventos delegados y controles etiquetados; cada guardado espera HTTP 200/resultado confirmado antes de avanzar, evita UPDATE si el valor ya estaba guardado, no reconstruye el DOM durante el clic, cancela narraciones previas y conserva el footer/shell del patrón P04. V1 avanza automáticamente con No/Sí sin dependencias y guarda inputs con un solo Siguiente. V2 permanece en Sí mientras requiere tipo/año, recarga sólo después del guardado confirmado para mostrar dependencias y continúa cuando están completas; No limpia dependencias y avanza. Los endpoints V1/V2 derivan el rol de la ruta, exigen `idPaciente`, validan acceso al paciente y verifican la pertenencia respuesta–paciente–tema/enfermedad. Se añadió catálogo sintético allowlisted en `tests/fixtures/p07-catalog.php` y runners `tests/p07/prepare-synthetic.php`, `http-crud.php`, `contract-check.mjs` y `cleanup-synthetic.php`; la base `clinica_dolor_demo_20261010_final` quedó limpia tras las pruebas. PASS: PHP lint, parseo JS inline, contrato P07, HTTP propio/anónimo/cruzado V1/V2 con persistencia real y 403 cruzado, clics efectivos en navegador sintético, avance V1 No, guardado de input, V2 Sí con dependencias, avance tras tipo/año, recuperación al volver, y ausencia de overflow renderizado a 1440×900 y 375×667. El shell/footer de P04 se contrastó renderizado en 1280×720. **P07-MODAL-AUDIO-FIX-01 (2026-10-11):** se restauró el modal automático y desde ayuda con el patrón visual de P04/P05, foco y cierre accesibles, y se reemplazaron únicamente los `.btnLeer` estáticos de P07 por controles Escuchar/Detener delegados. La lectura usa `es-MX` preferente, estados `onstart/onend/onerror`, cancelación segura y texto filtrado: V1 sólo tema/pregunta; V2 sólo enfermedad, pregunta y dependencias visibles. El inicializador antiguo inalcanzable se retiró sin alterar navegación. PASS: contrato mock de modal/voz y lint estático. NO VERIFICADO: reproducción audible humana, modal/consola/responsive renderizados en navegador autenticado y captura exportada. P07 permanece en VALIDACIÓN; no se modificaron P04, P05, P06 ni P08. |
| P08 | Medicación Actual | GET `/historia-clinica/medicacion-actual/paciente/{idPaciente}`; `app/views/historia-clinica-paciente/medicacion-actual.php` | Agregar, editar, eliminar y finalizar medicación; continuar mediante `next_url` | `app-shell-patient`, `patient-clinical-form`, AJAX módulo 6, `MedicacionActualModel`, `tests/p08` | **VALIDACIÓN** | **P08-COMPLETE-01 (2026-10-11):** vista P04-compatible con los cinco campos heredados (`descripcion`, `medicamento`, `tiempo_uso`, `dosis`, `medico`), estado vacío, tarjetas de altura natural, acciones accesibles, modal de instrucciones y voz `es-MX` cancelable. El controlador deriva el contexto desde la ruta y exige `assertPatientAccess()`; el modelo usa consultas preparadas, propiedad por `id` + `id_paciente`, no-op sin UPDATE y finalización idempotente de módulo 6. PASS: PHP lint, contrato estático, GET propio 200, sin sesión 302, acceso cruzado 403, AJAX M6 con rol derivado, CRUD propio persistente, no-op, edición/eliminación cruzadas 403, campo no permitido 422, finalización y `next_url` local; limpieza de fixtures PASS en `clinica_dolor_demo_20261010_final`. Capturas, viewports, overflow, consola y reproducción audible real NO VERIFICADOS: el navegador IAB bloqueó el servidor local (`net::ERR_BLOCKED_BY_CLIENT`) y Chrome no estuvo disponible. `bd_clinica` no fue utilizada. P08 permanece en VALIDACIÓN. |
| P09 | Medicamentos usados para controlar el dolor | GET `/historia-clinica/medicacion-control-dolor/paciente/{idPaciente}`; `app/views/historia-clinica-paciente/medicacion-control-dolor.php` | Responder medicamentos, dosis, resultados, efectos adversos, consumo actual y finalizar módulo 7 | `app-shell-patient`, `patient-clinical-form`, `patient-nonpathological-page`, AJAX módulo 7, `MedicacionDolorModel`, `tests/p09` | **VALIDACIÓN** | **P09-RESTORE-REAL-01 (2026-10-11):** se verificó `P09_CATALOGOS_SEGUROS.zip` y se importaron de forma idempotente, sólo en `clinica_dolor_demo_20261010_final`, exactamente 6 temas y 46 preguntas; se añadió únicamente la columna sintética `pregunta` faltante y las relaciones necesarias del fixture. No se importaron respuestas del ZIP. PASS real: login sintético, GET propio, seis AJAX de temas, GET/AJAX/POST/finalización cruzados bloqueados con HTTP 403, sesión ausente HTTP 302, persistencia de No y Sí con dosis/resultado/consumo/efectos, limpieza al cambiar resultado, recuperación, avance automático, navegación con una pulsación, protección contra doble clic, modal, cierre con Escape y retorno de foco, finalización de módulo 7, `next_url` local e idempotencia. Se corrigieron exclusivamente dos defectos demostrados: pérdida de dosis al redibujar tras seleccionar resultado y duplicación de finalización. PASS estático: PHP lint, contrato P09, mock de audio, importación idempotente, limpieza sintética y `git diff --check`. Capturas reales del modal, primera pregunta y último tema a la dimensión disponible del navegador (~1280×720): PASS. NO VERIFICADO: viewports 1440×900, 1280×720 exacto, 768×1024, 375×667, control de consola del navegador, reproducción audible humana, fallo forzado de red y observación de no-op SQL a nivel de sentencia. No se usó `bd_clinica`, no se realizaron commits ni despliegues y P10 no fue iniciado. Se mantienen abiertos la siembra heredada durante GET y ACT-05C-02. |
| P10 | Procedimientos usados para controlar el dolor | GET `/historia-clinica/procedimientos-control-dolor/paciente/{idPaciente}`; `app/views/historia-clinica-paciente/procedimientos-control-dolor.php` | Agregar/editar/eliminar procedimientos, responder tratamientos y finalizar módulo 8 | `app-shell-patient`, `patient-clinical-form`, AJAX módulo 8, `ProcedimientosDolorModel`, `tests/p10`, CSS scoped `patient-procedures-page` | **VALIDACIÓN** | **P10-CATALOG-UNBLOCK-02 (2026-10-11):** se verificó `P10_CATALOGO_SEGURO.zip` (SQL interno SHA-256 autorizado), se corrigió sólo la estructura sintética incompatible (`tratamiento`→`procedimiento` y columna `comentarios`) y se importaron idempotentemente los 10 registros exactos en `clinica_dolor_demo_20261010_final`; no se ejecutó el volcado completo ni se tocó `bd_clinica`. PASS HTTP sintético: catálogo 10/10, AJAX de tratamientos, respuestas No/Sí, dependencias Funcionó/No funcionó/efectos adversos, descripción, comentarios, recuperación, acceso cruzado GET/AJAX/POST 403, finalización, `next_url` a evaluación del dolor e idempotencia; respuestas y finalización se limpiaron y el catálogo 10/10 se conserva. Se mantienen PASS previos de procedimientos y FETCH-FIX. No-op SQL a nivel de sentencia, modal centrado, NetworkError controlado, responsive, capturas, consola DevTools, navegación visual/teclado y audio audible humano: **NO VERIFICADOS** por bloqueo del navegador IAB. P10 permanece en VALIDACIÓN; el renderer legado Doctor/resumen y ACT-05C-02 siguen como riesgos separados. |
| P11 | Evaluación del dolor | GET `/historia-clinica/evaluacion-dolor/paciente/{idPaciente}`; `app/views/historia-clinica-paciente/evaluacion-dolor.php` | Mapas corporales frontal/posterior, cuestionario (`dolor`, `tiempo_dolor`, `descripcion`, `incremento`), guardar, limpiar y finalizar módulo 9 | `app-shell-patient`, modal/clases canónicas de P04, `EvaluacionDolorModel`, `BusquedasController`, `footer-doctor.php`, Pointer Events, endpoint de imagen autorizado | **VALIDACIÓN** | **P11-COMPLETE-01 (2026-10-11):** se reemplazó el renderer legado por un flujo de tres secciones con modal de P04, Escuchar/Detener, voz `es-MX` preferente, Pointer Events, escalado por `getBoundingClientRect()`, cola de guardado y lectura autorizada de mapas. GET propio no escribe; el rol, sexo y paciente se derivan del contexto autenticado. El cuestionario conserva sus cuatro campos, crea/actualiza sólo en POST, limpia dependencias ante `No`, evita el UPDATE redundante y recupera valores; la limpieza de frente/espalda es independiente y la finalización de módulo 9 es idempotente con `next_url`. La vista Doctor P11 también dejó de preparar filas/imágenes durante GET. La base allowlisted `clinica_dolor_demo_20261010_final` quedó restaurada a su línea base después de las pruebas. PASS: preflight de base/pacientes, login sintético, GET sin escrituras, 302 sin sesión, 403 cruzado GET/AJAX/imagen/POST/finalización, AJAX propio, lectura/guardado/limpieza de imágenes, Sí/dependencias/No, persistencia, recuperación, finalización, `next_url`, idempotencia, PHP lint, contrato estático y `git diff --check` respecto del alcance. En navegador sintético PASS a 1280×720: modal inicial, Escape, foco/ARIA observable, Escuchar/Detener por estado de SpeechSynthesis, navegación frente→espalda→cuestionario, marca Pointer Event persistida y sin overflow ni errores de consola. El no-op HTTP devuelve 200, pero el conteo de sentencias SQL queda NO VERIFICADO. Viewports 1440×900, 768×1024 y 375×667, captura full-page, stylus/táctil independiente y audio audible humano quedan NO VERIFICADOS. `git diff --check` conserva una incidencia histórica fuera de P11 en `app/Controllers/ModulosController.php:996`. Riesgos abiertos: archivos heredados bajo `public/assets/images/evaluacion-dolor` potencialmente accesibles directamente, métodos legacy usados por el resumen D40, ambigüedad clínica azul/amarillo y ACT-05C-02; no se declara protección documental completa. P11 permanece en VALIDACIÓN y no se inició P12. |

#### P08-COMPLETE-01 — Medicación Actual

P08 utiliza `GET /historia-clinica/medicacion-actual/paciente/{idPaciente}` y
el endpoint `GET /buscar/contenido-preguntas-modulo-6/{idPaciente}/{idRol}`.
La vista es `app/views/historia-clinica-paciente/medicacion-actual.php`; las
operaciones pasan por `ModulosController` y `MedicacionActualModel`. Se
conservaron los cinco campos y su semántica heredada: `descripcion` se muestra
como nombre del medicamento, `medicamento` como motivo de uso, seguidos de
tiempo, dosis y médico.

La vista fue llevada al patrón de P04 sin cambiar el catálogo clínico: hero
institucional, contexto del paciente, acciones de ayuda/regreso, estado vacío,
tarjetas de registro, controles de voz y navegación. El JavaScript usa
eventos delegados, una sola solicitud por acción, clave `localStorage` aislada
por paciente, guardas de cambios y confirmación HTTP antes de recargar o
continuar. La narración prefiere `es-MX`, cancela la utterance anterior y no
permite solapamiento. La finalización envía `idModulo = 6`, valida una URL
relativa y deja el registro idempotente.

En seguridad, el controlador deriva el ámbito desde la ruta, no confía en
`idRol` del cliente y llama `assertPatientAccess()` antes de cada operación.
La rama Paciente de P08 usa PDO preparado, restringe sus
lecturas/actualizaciones/eliminaciones por `id` e `id_paciente` y rechaza
campos fuera de la allowlist; el renderer legado Doctor no se alteró. Se añadió
`tests/p08/prepare-synthetic.php`, `http-crud.php`, `cleanup-synthetic.php`,
`contract-check.mjs`, `start-server.ps1` y el procedimiento en
`tests/p08/README.md`; todos fallan si la base no es exactamente
`clinica_dolor_demo_20261010_final`. `bd_clinica` no fue escrita ni utilizada.

PASS: preflight de aislamiento, PHP lint, contrato estático, GET propio 200,
GET sin sesión 302, GET cruzado 403, AJAX M6 con rol derivado, CRUD propio con
persistencia, no-op sin UPDATE, edición/eliminación cruzadas 403, campo no
permitido 422, finalización con `next_url` local, segunda finalización
idempotente y limpieza de fixtures. La comparación visual autenticada,
1440×900, 1280×720, 768×1024, 375×667, overflow, consola y reproducción
audible real quedan **NO VERIFICADAS**: el navegador integrado bloqueó el
servidor local con `net::ERR_BLOCKED_BY_CLIENT` y Chrome no estuvo disponible.
P08 permanece en **VALIDACIÓN** hasta revisión visual humana.

#### P05-EXACT-PARITY-01 — Paridad visual real con P04

- **Causa confirmada:** dos cierres `</div>` faltantes en la vista P05 anidaban cada `card-body` dentro de su `card-header`, reduciendo el ancho útil; además, el selector de paridad ocultaba el prompt clínico y el modelo imprimía etiqueta/nombre del hábito en un solo encabezado.
- **Corrección:** se reparó exclusivamente la estructura HTML de P05, se hizo visible el prompt AJAX, se separaron etiqueta y nombre como en P04 y se eliminó el `p05-navigation-hint` permanente. El botón Siguiente sigue visible y bloqueado sin respuesta mediante `disabled`, `aria-disabled` y `title`; el mensaje visible sólo se activa al intentar avanzar.
- **Evidencia real:** en navegador sintético a 1280×720 P04/P05 compartieron 925.14 px de tarjeta exterior, 884.00 px de contenido y X derecha 1095.34 px para Siguiente; P05 mostró por AJAX la pregunta de tabaquismo, sin overflow, sin hint permanente y sin errores de consola.
- **PASS:** PHP lint, contratos P05/P04/P06, audio mock, HTTP de aislamiento/CRUD sintético y `git diff --check`. **NO VERIFICADO:** 1440×900 y 375×667 en esta ejecución por falta de control reproducible de viewport, reproducción audible humana, fallo forzado de red y dashboard posterior. P05 permanece en `VALIDACIÓN`.

#### P04-FUNC-01 — Restauración de voz y validación funcional focalizada

- **Cambios:** contrato de comentarios corregido a `idModulo = 2`; controles manuales «Escuchar instrucciones», «Escuchar pregunta» y «Detener audio»; modal accesible con foco, Escape y retorno de foco; voz `es-MX` preferente sin espera indefinida de `voiceschanged`; cancelación al navegar o cerrar; claves `localStorage` aisladas por paciente/módulo; guardas para preguntas vacías y errores AJAX con cierre del loader.
- **Preservado:** preguntas, respuestas Sí/No, dependencias, enfermedades adicionales, audio, comentarios, finalización, IDs y endpoints.
- **PASS:** PHP lint, sintaxis JavaScript externa, integridad CSS y contratos estáticos.
- **NO VERIFICADO:** audio real, modal en navegador, AJAX, cinco enfermedades, dependencias, comentarios, finalización, consola, responsive y capturas, por falta de sesión/base sintética aislada.
- **Riesgos abiertos:** siembra de cinco filas durante GET y autorización independiente de operaciones por `idEnfermedad`; el modelo no fue modificado en esta actividad. P04 permanece en `VALIDACIÓN`.

#### P04-FUNC-02 — Audio automático, control único de voz y respuestas exclusivas

- **Cambios:** se eliminaron los dos controles generales heredados de reproducción dentro de las tarjetas de P04; el modal inicial conserva la explicación escrita, intenta reproducirla automáticamente y ofrece un único botón manual «Escuchar instrucciones» con mensaje de fallback cuando el navegador bloquea el audio. La lectura de preguntas queda limitada al botón «Escuchar pregunta» de la pregunta activa y se cancela al navegar, cerrar el modal o detenerla.
- **Respuestas Sí/No:** la rama Paciente de `AntecedenteFamiliarModel::mostrarPreguntasM2()` utiliza radios con el mismo `name` por enfermedad, etiquetas asociadas y estados visuales/foco scoped a P04. La rama Doctor no fue modificada.
- **PASS:** inspección estática de controles, exclusividad de `name`/radio en la rama Paciente, ausencia de `.btnLeer` en P04, modal y fallback accesible, PHP lint, sintaxis JavaScript, integridad CSS y `git diff --check`.
- **NO VERIFICADO:** reproducción automática/manual real, bloqueo de autoplay, AJAX, persistencia de respuestas, navegación por teclado observada en navegador, responsive, consola y capturas; no se dispuso de sesión sintética/base aislada.
- **Riesgos abiertos:** siembra de cinco filas durante GET y autorización independiente de operaciones por `idEnfermedad`; no se modificaron controladores, rutas, esquema ni persistencia. P04 permanece en `VALIDACIÓN`.

#### P04-FUNC-03 — Guardado Sí/No y avance posterior a confirmación

- **Causa confirmada:** `editarEnfermedad()` omitía `idPaciente`, aunque `ModulosController::pacienteEditEnfermedad()` lo necesitaba para `assertPatientAccess()`. Además, el modelo actualizaba por `id` sin verificar la relación con `id_paciente`.
- **Corrección:** el portal Paciente y su vista Doctor acompañante envían el `idPaciente` existente en `#main`; el controlador deriva el ámbito desde la ruta, valida la entrada y autoriza el paciente; `AntecedenteFamiliarModel::editarEnfermedadPaciente()` verifica propiedad y ejecuta el `UPDATE` con `id` e `id_paciente` parametrizados. Se conservan valores `Si`/`No`, dependencias y contratos existentes.
- **Flujo:** las tarjetas completas usan labels asociados a radios; se bloquean solicitudes simultáneas, se muestra «Guardando respuesta…», se restaura el valor confirmado ante fallo y sólo se avanza después de una respuesta exitosa. «No» avanza automáticamente; «Sí» conserva y muestra los campos dependientes. La última pregunta no finaliza el módulo automáticamente.
- **PASS:** revisión estática del payload, ruta, validación, consulta restringida, radios/labels, bloqueo de doble envío, PHP lint, sintaxis JavaScript, integridad CSS y `git diff --check`.
- **NO VERIFICADO:** petición HTTP real, persistencia Sí/No, recarga, avance en navegador, errores 401/403/404/422/500, doble clic, autorización cruzada y regresión Doctor con sesión/base sintética aislada no disponible.
- **Riesgos pendientes:** siembra de filas durante GET y demás operaciones de eliminación/agregado fuera de esta corrección requieren auditoría independiente. P04 permanece en `VALIDACIÓN`.

#### P04-FUNC-04 — Versionado de assets, audio inicial y coherencia cromática

- **Causa de caché confirmada:** la vista cargaba `app.css` y `voice-utilities.js` sin query string versionada; no se encontró un helper institucional reutilizable ni otra capa de caché que justificara modificar el alcance.
- **Versionado:** `app/views/historia-clinica-paciente/antecedentes-familiares.php` conserva `RUTA_CSS`, `RUTA_JS` y `RUTA_PUBLIC`; añade un helper local determinista que resuelve archivos dentro de `public/` y agrega `?v=<filemtime>`. Se versionan `app.css` y los scripts locales propios cargados por P04; Bootstrap, Perfect Scrollbar, Simple-DataTables y jQuery permanecen sin cambios. La prueba sintética de `filemtime()` confirmó que la versión cambia al modificar el archivo; HTML/Network real y respuestas 404 quedan NO VERIFICADOS sin navegador/sesión.
- **Audio:** la apertura del modal intenta leer de inmediato y prefiere `es-MX`, con listener único y no bloqueante de `voiceschanged`. El estado «Reproduciendo» sólo se presenta desde `onstart`; ante autoplay bloqueado o error se conserva el modal, se muestra una indicación segura y se resalta «Escuchar». Cerrar, detener o navegar cancela la síntesis y evita eventos tardíos; la carga AJAX inicial no cancela la lectura del modal mientras permanece abierto. El utilitario compartido no se modificó y sus observadores no reciben las secciones P04 (`data-autoplay="true"`, sin `.btnLeer`).
- **Controles y navegación:** modal con iconos y textos «Escuchar»/«Detener»; pregunta activa con «Escuchar» y «Detener» sólo durante reproducción. Al cambiar de pregunta, se cancela la anterior y se solicita la lectura del contenido de la nueva pregunta después de activarla en el DOM, sin modificar el flujo de guardado de P04-FUNC-03.
- **Color:** títulos clínicos de P04 reutilizan `var(--clinic-accent)` (`#35a78f`), el verde institucional observado en los módulos privados del paciente. El hero, botones violetas/lavanda, errores y vistas Doctor no se modificaron.
- **PASS:** PHP lint de la vista, sintaxis inline y de los 95 JavaScript externos, integridad CSS (3010 llaves abiertas/cerradas), `git diff --check`, rutas reales y `filemtime()` de assets, cambio sintético de versión, ausencia estática de duplicados JS, compatibilidad de `voice-utilities.js` y contratos de controles/colores.
- **NO VERIFICADO:** reproducción automática y manual real, disponibilidad de voces del navegador, respuesta de `voiceschanged`, restricciones de autoplay, Network/404, AJAX/persistencia, avance en navegador, responsive, consola y capturas; no se dispuso de sesión sintética/base aislada ni navegador reproducible.
- **Estado:** P04 permanece en `VALIDACIÓN`; no se modificaron guardado Sí/No, autorización, AJAX clínico, modelos, controladores, rutas, base de datos, finalización, encabezado ni otras vistas.

#### P04-FLOW-01 — Respuestas Sí/No, confirmación y avance seguro

- **Causa confirmada:** los radios Sí/No del portal Paciente tenían `onchange` inline. Al volver a activar la respuesta ya seleccionada, el navegador no dispara `change`, por lo que no se ejecutaba el flujo de avance. El callback también mostraba «Respuesta guardada.» incluso cuando la respuesta ya estaba confirmada y el avance ocurría inmediatamente.
- **Corrección:** se retiró únicamente el `onchange` de los radios Paciente y se añadió un manejador delegado de `click` para contenido dinámico. Las respuestas nuevas conservan el payload, endpoint, autorización, bloqueo de solicitudes simultáneas y restauración ante error. Una respuesta marcada como confirmada no genera un nuevo AJAX ni un `UPDATE`; si la pregunta está completa, avanza, y si requiere dependencias, muestra la indicación de completarlas.
- **Mensajes y dependencias:** el guardado confirmado usa un aviso temporal «Guardado» sólo cuando procede, y los errores permanecen visibles para permitir reintento. «Sí» conserva la carga y validación de tipo de diabetes/familiar afectado; «No» avanza sólo después de confirmación. La última pregunta no finaliza automáticamente. Se conservaron IDs, `idPaciente`, `idEnfermedad`, `edicion`, `idRol`, comentarios, finalización, `localStorage` y todos los controles distintos de las tarjetas Sí/No.
- **Archivos:** `app/Models/AntecedenteFamiliarModel.php`, `app/views/historia-clinica-paciente/antecedentes-familiares.php` y `public/assets/css/app.css`.
- **PASS estático:** ausencia de `onchange` en los dos radios Paciente, rama Doctor preservada, listener delegado único, guardia de doble envío, bypass sin AJAX para respuesta confirmada, comprobación de dependencias antes de avanzar, PHP lint, sintaxis JavaScript inline/externa, integridad CSS y `git diff --check`.
- **NO VERIFICADO:** AJAX HTTP, persistencia, avance observable, doble clic real, errores de red/401/403/422/429/500, autorización cruzada, responsive, consola y capturas por falta de navegador/sesión/base sintética aislada.
- **Estado:** P04 permanece en `VALIDACIÓN`; no se modificaron audio, modal, colores del hero, otros módulos, Doctor, rutas ni esquema.

#### P04-AUDIO-01 — Narración automática y flujo de preguntas

- **Causa identificada:** la vista ya tenía integración de voz específica, pero la lectura de la pregunta activa eliminaba controles sin excluir todas las tarjetas de respuesta y no se reintentaba automáticamente después de cerrar el modal ni después de una recarga AJAX de preguntas. La voz compartida no se modificó.
- **Corrección:** se conserva el intento automático de leer las instrucciones al abrir el modal, con voz `es-MX` preferente, velocidad `0.88`, estados actualizados únicamente desde `onstart`, fallback accesible ante bloqueo y controles manuales «Escuchar»/«Detener». La pregunta activa ahora se construye desde su DOM visible, excluyendo radios, controles, navegación y estados, y conserva las indicaciones de dependencias como diabetes.
- **Sincronización:** al cerrar el modal, tras cargar preguntas por AJAX y después de cambiar o regresar de pregunta, la lectura se agenda mediante el punto real de transición del DOM (`queueMicrotask`, con fallback compatible); no se añadió un retardo arbitrario como mecanismo principal. La respuesta Sí/No, su guardado, avance, autorización y dependencias de P04-FLOW-01 permanecen sin cambios.
- **Cancelación y aislamiento:** se cancela la narración al cerrar el modal, cambiar de pregunta, regresar, cambiar de sección y abandonar la página mediante `pagehide`. Se mantiene una sola narración activa y no se modifica `voice-utilities.js`, su interfaz pública ni `speechSynthesis.onvoiceschanged` global.
- **Archivos:** `app/views/historia-clinica-paciente/antecedentes-familiares.php` y esta documentación. No se modificaron modelos, controladores, rutas, respuestas, base de datos, otros módulos ni CSS visual.
- **PASS estático/mock:** PHP lint, sintaxis JavaScript inline/externa, inspección de modal y controles únicos, construcción de texto desde la pregunta activa, cancelaciones y puntos de transición, preservación de payloads/handlers de flujo y `git diff --check`.
- **NO VERIFICADO:** reproducción real en navegador, política de autoplay, disponibilidad efectiva de voces, inicio manual, detención observable, lectura real de dependencias, consola, responsive, capturas y regresión AJAX/persistencia con sesión/base sintética aislada.
- **Estado:** P04 permanece en `VALIDACIÓN`; los colores, el contenido del modal, las respuestas Sí/No, el guardado y las demás pantallas permanecen fuera de esta actividad.

#### P04-VISUAL-01 — Unificación de colores y legibilidad

- **Verde identificado:** se reutilizó `var(--clinic-accent)`, definido por el shell institucional del Portal Paciente como `#35a78f`. Es el token verde existente usado por los módulos privados; no se creó una paleta nueva ni se modificó P03.
- **Cambios scoped:** el título `Antecedentes familiares` del hero P04 usa el verde institucional. Las preguntas y sus indicaciones dependientes usan `var(--clinic-ink)` (`#21164f`), mientras que el nombre de la enfermedad conserva `var(--clinic-accent)`. Las tarjetas Sí/No mantienen selección exclusiva, clic sobre toda la tarjeta, foco visible, tamaño táctil y estados existentes; sólo se ajustó separación, cursor y legibilidad.
- **Preservado:** modal, audio, narración, respuestas, guardado AJAX, avance, campos dependientes, comentarios, finalización, autorización, P01–P03, P05–P11 y Doctor.
- **PASS estático:** PHP lint, integridad CSS, `git diff --check`, ausencia de cambios JavaScript/modelo/controlador/ruta y selectores limitados a `patient-family-history-page`.
- **NO VERIFICADO:** comprobación visual real a 1440×900 y 375×667, contraste observado en navegador, overflow y regresión visual completa por falta de navegador/sesión sintética reproducible.
- **Estado:** P04 permanece en `VALIDACIÓN`; esta corrección no implica cierre funcional ni aprobación de producción.

#### P04-GRADIENT-01 — Igualación del fondo del hero a P02

- **Referencia verificada:** `body.dashboard-patient .patient-dashboard-hero` en `public/assets/css/app.css`.
- **Cambio puntual:** el hero P04 (`body.app-shell-body.patient-family-history-page .patient-family-history-hero`) reutiliza exactamente el mismo `radial-gradient` menta y `linear-gradient` base de P02: `radial-gradient(circle at 92% 8%, rgba(205, 239, 230, .75), transparent 15rem)` sobre `linear-gradient(135deg, rgba(255, 255, 255, .97), rgba(248, 245, 255, .97))`.
- **Preservado:** título, colores tipográficos, contenido, acciones, bordes, radio, sombra, padding, modal, audio, preguntas, respuestas, guardado y navegación.
- **PASS estático:** selector scoped, valores CSS coincidentes con P02, PHP lint de la vista sin modificaciones, integridad CSS y `git diff --check`.
- **NO VERIFICADO:** comparación visual renderizada de P02/P04 a 1440×900 y 375×667, pseudoelementos computados y overflow móvil por falta de navegador autenticado reproducible.
- **Estado:** P04 permanece en `VALIDACIÓN`; el ajuste no implica cierre funcional ni aprobación de producción.

#### P04-FIX-FINALIZAR-01 — Secuencia de comentario y finalización

- **Causa raíz:** el callback de éxito del guardado llamaba a la finalización
  antes de que `gestionarEnfermedadPaciente()` liberara `p04PendingRequest` en
  `finally`; la operación legítima se rechazaba como guardado concurrente.
- **Corrección:** `agregarComentario()` ahora controla la secuencia completa
  con `async/await`, una guarda de flujo, desbloqueo común y reintento de
  finalización sin repetir el POST del comentario ya confirmado. Se conservan
  `idModulo = 2`, los endpoints, la autorización y la redirección a
  `/historia-clinica`.
- **Archivos:** `app/views/historia-clinica-paciente/antecedentes-familiares.php`,
  `tests/p04/finalization-flow.mjs`, `docs/testing.md` y este registro.
- **PASS:** A/B/E/G con UI real y base sintética allowlisted; C/D/F con arnés
  controlado; PHP lint, sintaxis JavaScript y `git diff --check` quedan
  registrados en `docs/testing.md`.
- **Estado:** P04 permanece en `VALIDACIÓN`; esta corrección no implica cierre
  visual, aprobación funcional de producción ni cambio de seguridad clínica.

#### P04-NEXT-01 — Continuación automática al siguiente módulo

- **Mecanismo:** se añadió `PacienteModulosModelo::siguienteModuloPendiente()`.
  Consulta el catálogo clínico real y las finalizaciones formales actualizadas
  después de completar P04; no usa porcentajes ni URLs enviadas por el cliente.
- **Seguridad:** los nueve pares ID/slug se validan contra una allowlist
  canónica. Un catálogo inválido devuelve fallback a `/historia-clinica` y la
  autorización del paciente se ejecuta antes de resolver la URL.
- **Contrato:** `pacienteFinalizarModulo()` conserva los campos JSON existentes
  y agrega `next_url` sólo en respuestas exitosas. P04 navega con ese valor;
  los demás formularios no fueron modificados.
- **PASS:** P04→P05 pendiente, salto de P05 finalizado a P06, fallback cuando
  no hay posteriores, acceso cruzado rechazado y slug inválido con fallback
  seguro; base sintética restaurada.
- **Estado:** P04 permanece en `VALIDACIÓN`; la continuidad no implica cierre
  funcional, aprobación clínica ni autorización de producción.

#### P05-FUNC-01 — Funcionamiento y seguridad del módulo

- Causa raíz: P05 utilizaba claves seccionActual y preguntaActual_* globales,
  renderizaba Sí/No como checkboxes independientes, permitía navegar mientras
  un POST seguía pendiente y actualizaba respuestas sólo por idRespuesta.
  Además, el controlador y el GET de preguntas podían tomar idRol del
  navegador para decidir la presentación o el ámbito.
- Correcciones: claves aisladas como
  p05:{idPaciente}:modulo-3:*; orden y vecinos derivados del catálogo real;
  radios y etiquetas para selección exclusiva; una sola solicitud activa,
  comentario→finalización secuencial, guardia de doble envío y next_url. El
  modelo verifica paciente, pregunta y tema antes de cualquier UPDATE y el
  contexto se deriva de ruta y sesión.
- Archivos: vista P05, AntecedentesNoPatologicosModel.php,
  ModulosController.php, BusquedasController.php,
  tests/fixtures/security-fixtures.sql y tests/p05/*.
- PASS: preflight fail-closed sobre clinica_dolor_demo_20261010_final,
  pacientes sintéticos 9301/9302 en clínicas distintas, PHP lint, JS inline,
  checks de contrato, git diff --check y GET/POST sin sesión con HTTP 302.
- NO VERIFICADO: A–O autenticados, siete temas, dependencias, persistencia,
  rollback HTTP, acceso cruzado y consola/browser; el catálogo sintético actual
  no contiene temas, preguntas ni respuestas y no se inventaron para producir
  PASS.
- Riesgo conservado: el GET aún puede insertar respuestas faltantes mediante
  antecedentesNoPatologicos(); debe probarse sólo en una base sintética con
  catálogo autorizado. P05 permanece en VALIDACIÓN.

#### P05-FLOW-SPEED-01 — Guardado y avance con una sola interacción

- **Causa confirmada:** el `change` de un control podía iniciar un POST que
  reconstruía la pregunta y restauraba el mismo índice; además, la navegación
  ignoraba el clic mientras el POST estaba pendiente. Las tarjetas Sí/No sólo
  guardaban y no iniciaban la transición automática.
- **Corrección:** la rama Paciente del generador HTML conserva controles radios
  y texto con metadatos `data-p05-*` y valor confirmado. La vista usa
  delegación para contenido AJAX, una única transición asíncrona y una cola de
  una sola intención cuando `Siguiente` se pulsa durante un guardado. El botón
  espera los controles modificados, avanza exactamente una pregunta al recibir
  confirmación, limpia dependencias al confirmar `No` y cancela/narra la
  pregunta siguiente sin bloquear la respuesta. La rama Doctor permanece sin
  cambios.
- **Preservado:** los siete temas y 25 preguntas, IDs no consecutivos, radios
  exclusivos, dependencias, Grupo Sanguíneo con dos inputs, endpoints,
  autorización, validaciones, comentarios, finalización y `next_url`.
- **Archivos:**
  `app/Models/AntecedentesNoPatologicosModel.php`,
  `app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php`,
  `tests/p05/flow-speed-contract.mjs`, `tests/p05/README.md` y esta entrada.
- **PASS real sintético:** preflight de la base allowlisted; `No` avanzó de
  Tabaquismo a Alcoholismo sin Siguiente; `Sí` mostró la dependencia; un input
  avanzó con un solo clic tras persistir; seis respuestas `No` condujeron al
  tema Grupo Sanguíneo; sus dos inputs avanzaron a Comentarios; las respuestas
  1/6/27/28 se verificaron en la base sintética; consola sin errores.
- **PASS estático:** contrato de flujo, PHP lint, sintaxis JavaScript inline,
  contratos P05, mock de audio, aislamiento y `git diff --check`.
- **NO VERIFICADO:** fallo forzado de red, latencia controlada con clic durante
  POST, doble clic con instrumentación de red, reproducción audible humana,
  cuatro viewports reproducibles, capturas y regresión visual adicional. No se
  ejecutó finalización clínica; la base sintética fue limpiada después.
- **Estado:** P05 permanece en `VALIDACIÓN`; el cambio no implica cierre
  funcional, autorización clínica ni aprobación para producción.

#### P05-FIX-UI-NAV-01 — Composición vertical y restauración de Siguiente

- **Causa confirmada:** `mostrarPreguntasM3()` ejecutaba `continue` cuando una
  pregunta `select` tenía respuesta vacía, por lo que cerraba la navegación
  sin renderizar Siguiente. Además, el prompt generado conservaba una clase
  Bootstrap `col-md-11` y P05 tenía el toolbar de voz separado de la
  composición de P04.
- **Corrección:** el modelo siempre emite el footer; con respuesta vacía,
  Siguiente queda visible pero deshabilitado y muestra «Selecciona una
  respuesta para continuar». La vista valida nuevamente la pregunta activa
  antes de avanzar y conserva la guarda contra doble envío. El prompt ocupa
  todo el ancho, la etiqueta del hábito usa texto oscuro y su nombre usa
  `var(--clinic-accent)`, y los controles de voz quedan en la parte superior
  derecha del cuerpo de la tarjeta, siguiendo P04. No se cambiaron rutas,
  preguntas, temas, AJAX o persistencia.
- **PASS:** PHP lint, contrato de navegación sintético, contratos P05, mock de
  audio, sintaxis JavaScript inline, regresión focal P04 y `git diff --check`.
- **NO VERIFICADO:** comparación autenticada a 1440×900/375×667, consola,
  persistencia y navegación real de los siete temas; el catálogo sintético
  continúa vacío. P05 permanece en `VALIDACIÓN`.

#### UI-PARITY-GATE-01 — Corrección visual real y audio P05

- La causa visual confirmada fue el toolbar de voz dentro del encabezado de la
  tarjeta P05, junto con wrappers Bootstrap heredados que podían conservar una
  columna interna para la pregunta. Se movieron los controles al cuerpo antes
  de la pregunta y se limitaron los overrides a `patient-nonpathological-page`:
  flujo vertical, Sí/No horizontal en escritorio, navegación a todo el ancho,
  modal de 540 px y sidebar activo con `aria-current`.
- La causa de audio era un fallback de 350 ms que sólo actuaba cuando el
  navegador informaba `speaking=false` y `pending=false`; una cola bloqueada
  dejaba el estado «Intentando…». P05 ahora reconoce `onstart`, usa un límite
  de 1500 ms, cancela la utterance bloqueada y ofrece lectura manual.
- El gate reutilizable es `tests/patient-module-parity-gate.mjs` y se aplicó
  estáticamente a P04/P05/P06. P05 se verificó con navegador sintético real
  para shell, hero, tarjeta, sidebar, modal y consola; la pregunta se verificó
  con `tests/p05/visual-fixture.html` porque el catálogo allowlisted tiene
  cero temas/preguntas. A 1440×900 el hero/tarjeta del fixture miden 968 px y
  el modal 540 px; a 375×667 las respuestas se apilan y no hay overflow.
- P06 fue sólo verificado: mantiene hero, estado vacío/modal y consola sin
  errores; no se modificó. La narración audible humana, preguntas reales P05,
  persistencia y navegación de siete temas permanecen **NO VERIFICADAS**.
- P05 permanece **VALIDACIÓN**. No se inició P07. Se conservan la siembra
  durante GET y ACT-05C-02 como riesgos abiertos.

### 4.3 Reutilización real entre roles

- Los nueve slugs y el método de despacho se reutilizan, pero Doctor,
  Paciente y Resumen tienen archivos PHP distintos.
- El sidebar se genera con Sidebar y SidebarController para ambos roles, con
  menús diferentes.
- app.css, tokens, botones, foco y patrones de tarjeta son compartidos.
- auth-visual.php y auth-icon.php se comparten entre ambos logins.
- footer-doctor.php se incluye también en el Dashboard Paciente y en los nueve
  formularios paciente, aunque su nombre sea específico del Doctor.
- footer-doctor.php se incluye en D23–D40 y en los formularios del Paciente
  que lo reutilizan. No se detectó una inclusión activa de `footer-mvsd.php`
  en las vistas D/P inspeccionadas; el archivo legado se conserva como
  componente pendiente de revisión transversal.

## 5. Pantallas y artefactos compartidos fuera de D/P

| ID | Tipo | Archivo o origen | Alcance | Estado |
|---|---|---|---|---|
| C01 | Error HTML 404 | app/views/errors/404.php | Paciente inexistente, recurso no encontrado u otra resolución 404 | **PENDIENTE** de revisión visual por rol/contexto |
| C02 | Error HTML 405 | app/views/errors/405.php | Método no permitido o respuesta equivalente | **PENDIENTE** de revisión visual |
| C03 | Componente de acceso | app/views/components/auth-visual.php | Narrativa, logo, panel y tarjeta compartidos por D01/P01 | Documentado; los logins conservan diseño específico |
| C04 | Iconos de acceso | app/views/components/auth-icon.php | Iconografía de campos/acciones de login | Documentado; no es pantalla |
| C05 | Footer médico | app/views/components/footer-doctor.php | Footer de la mayoría de shells privados | Documentado; verificar cobertura por pantalla |
| C06 | Footer legado | app/views/components/footer-mvsd.php | Componente heredado fuera de las vistas D/P modernizadas | Pendiente de revisión transversal; D23–D40 utilizan `footer-doctor.php` |
| C07 | Modal de impresión receta | app/views/components/modal-impresion-receta.php | Incluido en recetas, receta externa y nota subsecuente | Pendiente de revisión visual en cada contexto |
| C08 | Barra de búsqueda Doctor | app/views/components/search-bar-doctor.php | Componente de búsqueda, si la vista lo incluye | Pendiente de confirmar cobertura por ruta |

No son pantallas: BusquedasController y las rutas GET /buscar/* devuelven JSON
o fragmentos AJAX; ModulosController y ClinicaController tienen numerosos POST
de guardado; PdfController genera documentos; DownloadController sirve
archivos. Deben probarse como dependencias funcionales de sus pantallas, pero
no reciben un ID de cierre visual independiente.

## 6. Navegación comprobada por código

### Doctor

SidebarController configura menús según el contexto: Inicio, Pacientes,
Cofepris, Recetas, Nueva Receta, Expediente, Historia Clínica, Perfil y Cerrar
Sesión, además de los nombres específicos de cada módulo o recurso. El enlace
activo se genera en Sidebar.php con aria-current="page" cuando coincide con el
item configurado por el controlador.

### Paciente

El Dashboard Paciente muestra Inicio y Cerrar Sesión. Los formularios de módulo
usan el contexto PACIENTE y muestran el módulo actual. No se observó un menú
Paciente equivalente al menú administrativo del Doctor.

## 7. Matriz de cobertura y progreso real

| Grupo | Total de IDs | CERRADA | VALIDACIÓN | PENDIENTE |
|---|---:|---:|---:|---:|
| Doctor D01–D41 | 41 | 40 | 1 | 0 |
| Paciente P01–P11 | 11 | 0 | 11 | 0 |
| Compartidos C01–C08 | 8 | 0 | 3 documentados | 5 pendientes |

La cifra de VALIDACIÓN no significa aprobación. Representa que hay evidencia
previa de render o responsive en UI-02, UI-03, visual-validation o demo-readiness.
La aprobación visual expresa del responsable queda registrada para D01–D41,
excepto D02. D02 permanece en VALIDACIÓN por
UI-FINAL-D02-01/UI-FINAL-D02-02. El cierre visual no implica aprobación de
seguridad, autorización clínica ni preparación para producción.
Esta aprobación no resuelve riesgos funcionales, de seguridad ni de cobertura
de pruebas descritos en las filas.

## 8. Orden recomendado de cierre

1. **P02 Dashboard Paciente.** Revisar contra el patrón aprobado sin hacerlo
   idéntico al dashboard Doctor.
2. **P03–P11 Formularios Paciente.** Aplicar el patrón común y luego revisar
   excepciones de cada módulo.
3. **C01–C08 compartidos.** Cerrar errores, modales, búsqueda y decisión del
   footer legado como dependencias transversales.

## 9. Defectos y pendientes detectados sin implementar

1. D24–D40 requieren validación individual del encabezado institucional y
   responsive; el paso por una ruta genérica no equivale a revisar nueve
   pantallas. D24 ya recibió intervención estática y permanece en VALIDACIÓN.
2. D22 Perfil permanece en VALIDACIÓN hasta aprobación visual expresa; no tiene
   capturas autenticadas ni pruebas de viewport en esta ejecución.
3. C01/C02 usan títulos heredados y no tienen revisión visual de contexto.
4. C07 Modal de impresión aparece en varios contextos y debe verificarse sin
   duplicar cambios.
5. Las rutas de documento, PDF y descarga siguen limitadas por los riesgos
   críticos de ACT-05C-02; no deben considerarse cerradas para producción.
6. Guardados, edición, finalización, cargas de archivo y descargas no quedan
   aprobados por capturas o pruebas de render.

## 10. Riesgos de seguridad que siguen visibles

Las vulnerabilidades críticas de autorización y almacenamiento documental de
ACT-05C-02 permanecen abiertas: archivos clínicos bajo almacenamiento público,
accesos cruzados confirmados en descargas y casos de documentos inexistentes que
responden 500. Este inventario no las corrige ni las oculta. El cierre visual
no autoriza datos clínicos reales ni despliegue.

## 11. Resultado de UI-04

- Inventario Doctor: **completo en código**, D01–D41.
- Inventario Paciente: **completo en código**, P01–P11.
- Nueve módulos: incluidos en sus tres familias reales.
- Componentes, modales, errores, AJAX, PDF y descargas: clasificados sin
  convertirlos artificialmente en pantallas.
- Modificaciones visuales: **ninguna**.
- Primera pantalla fuera del cierre Doctor: **P02 Dashboard Paciente**.
- D40 queda en **CERRADA** por aprobación visual expresa; no se declara listo
  para producción.
> P09-RESTORE-REAL-01: el ZIP autorizado se validó mediante SHA-256 `7054DA65AC77BB5DEE9763E109E21FD01AFEBB93FE59D71A9874CF91CFE43986`. La preparación quedó restringida a `clinica_dolor_demo_20261010_final`, con 6 temas, 46 preguntas, estructura de respuestas compatible e importación idempotente. El catálogo se conserva para futuras pruebas; las respuestas y finalizaciones sintéticas se limpiaron al terminar.
> P09-RESTORE-REAL-01: el navegador IAB permitió pruebas autenticadas reales mediante el host local de pruebas y generó capturas inline del modal, primera pregunta y último tema. No expone control reproducible de viewport ni consola DevTools; por eso las dimensiones solicitadas fuera de la dimensión disponible, la consola y el audio audible humano permanecen NO VERIFICADOS.

#### P10-CATALOG-UNBLOCK-02 — Procedimientos para controlar el dolor

P10 corresponde a `GET /historia-clinica/procedimientos-control-dolor/paciente/{idPaciente}` y a la vista `app/views/historia-clinica-paciente/procedimientos-control-dolor.php`. La vista usa los endpoints de módulo 8 de `BusquedasController`, las operaciones POST de `ModulosController` y los métodos P10 de `ProcedimientosDolorModel`. Se añadió la preparación reproducible de `tests/p10/`, limitada por guarda fail-closed a `clinica_dolor_demo_20261010_final`.

La vista paciente ya no ejecuta `procedimientosModulo8()` durante GET: la carga inicial es de sólo lectura y el CRUD de procedimientos usa consultas preparadas, allowlist de campos y propiedad `id` + `id_paciente`. El controlador deriva la sesión y el acceso del paciente mediante `assertPatientAccess()`; ignora el `idRol` enviado por el navegador y devuelve mensajes genéricos ante errores técnicos. La finalización de módulo 8 es idempotente y sólo se acepta cuando existe catálogo y las respuestas requeridas están completas; no se genera una URL de módulo siguiente en cliente ni se acepta una URL no relativa.

Se verificó `P10_CATALOGO_SEGURO.zip`, cuyo SQL interno contiene exactamente los IDs 1–10 y los textos autorizados. La base activa fue `clinica_dolor_demo_20261010_final`, nunca `bd_clinica`. La tabla sintética estaba vacía pero usaba el nombre heredado `tratamiento`; se corrigió únicamente en la base demo a `procedimiento`. También se añadió `comentarios` a la tabla sintética de respuestas para cubrir el contrato existente. La importación fue transaccional, exacta e idempotente; el catálogo queda conservado con `CATALOGO_P10 = 10/10` y no contiene respuestas clínicas.

PASS: PHP lint, sintaxis JavaScript externa e inline, contrato estático, mock de audio, importación idempotente, catálogo 10/10, login sintético, GET propio sin siembra, sesión ausente HTTP 302, acceso cruzado GET/AJAX/POST HTTP 403, AJAX de los diez tratamientos, respuestas No/Sí, dependencias de resultado, efectos adversos, descripción, comentarios, recuperación tras reingreso, finalización HTTP 200, `next_url` local a `/historia-clinica/evaluacion-dolor/paciente/9301`, finalización idempotente, CRUD previo de procedimientos y limpieza sintética. El frontend conserva rutas relativas, mensaje genérico con reintento y el modal usa ahora la estructura Bootstrap canónica de P04 (`modal-dialog-centered`, `show` y `display:block`). `git diff --check` no reportó errores de whitespace introducidos por P10; conserva advertencias históricas de conversión LF/CRLF del árbol local.

NO VERIFICADO: no-op SQL a nivel de sentencia, error de red observado en navegador, centrado del modal observado en navegador, responsive 1440×900, 1280×720, 768×1024 y 375×667, capturas, consola DevTools, navegación visual/teclado observada y reproducción audible humana. El navegador IAB bloqueó la página sintética local (`Solicitud no disponible`); no se usó el host clínico real. P10 queda en **VALIDACIÓN**. No se inició P11 ni se realizaron commits o despliegues.

#### P10-MODAL-EXACT-PARITY-01 — Paridad del modal con P04

Se confirmó que la diferencia visual provenía del bloque scoped
`patient-procedures-modal`: su encabezado aplicaba un degradado menta y su
footer no usaba la estructura Bootstrap del modal canónico. P10 ahora usa la
misma jerarquía de P04 (`modal`, `modal-dialog modal-dialog-centered`,
`modal-content`, `modal-header`, `modal-body` y `modal-footer`), las mismas
clases de contenido y variantes de botones secundarios, y el mismo fondo
lavanda/blanco del encabezado. Se conservaron los IDs y funciones P10, la
apertura automática, voz es-MX, cancelación, Escape, foco, clic fuera y el
botón «Entendido, comenzar»; se añadió únicamente el estado `modal-open` y la
guarda contra reapertura mientras está visible para mantener el contrato de
P04.

PASS estático: estructura, orden Escuchar/Detener/Entendido, reglas de color,
bordes, radio, sombra, foco y responsive móvil del contrato P04; PHP lint,
sintaxis JavaScript, mock de audio y `git diff --check`.
NO VERIFICADO: comparación renderizada P04/P10 a 1440×900, 1280×720 y
375×667, capturas, consola DevTools, foco observado en navegador y audio
audible humano. El navegador local no ofreció una sesión sintética reproducible.
P10 permanece en **VALIDACIÓN**; no se modificaron catálogo, CRUD,
persistencia, autorización, finalización, `next_url`, P01–P09, P11 ni el área
Doctor.

#### P11-COMPLETE-01 — Evaluación del dolor y mapa corporal

P11 usa `GET /historia-clinica/evaluacion-dolor/paciente/{idPaciente}` y los
fragmentos autorizados de frente, espalda y cuestionario. La vista paciente
`app/views/historia-clinica-paciente/evaluacion-dolor.php` ahora carga las tres
secciones sin inicializar filas ni archivos durante GET. Las operaciones POST
derivan la sesión con `authAny()` y comprueban `assertPatientAccess()`; no se
aceptan `idRol`, `idSexo` ni una identidad clínica enviados por el navegador.

La vista reutiliza la estructura modal de P04 con instrucciones automáticas,
Escuchar/Detener, `es-MX`, Escape, foco, ARIA y una sola narración activa. Los
mapas usan Pointer Events, conversión de coordenadas respecto del tamaño real
del canvas y guardado en cola para no sobreescribir trazos recientes. Las
imágenes PNG se validan por formato, tamaño y dimensiones mínimas; las nuevas
versiones se escriben de forma atómica en `app/storage/evaluacion-dolor` y se
leen mediante `GET /{rol}/paciente/imagen-evaluacion-dolor/{orientacion}/{id}`
con autorización y `Cache-Control: private, no-store`. Limpiar frente y
espalda es independiente y no borra la otra orientación.

El cuestionario conserva `dolor`, `tiempo_dolor`, `descripcion` e `incremento`.
El primer POST crea la fila sólo cuando el usuario guarda; los campos se
actualizan mediante una lista permitida y `id_paciente`, con limpieza de
dependencias sólo ante `No`. La finalización del módulo 9 comprueba completitud,
es idempotente y conserva el resolvedor `next_url` existente.

PASS estático: PHP lint de P11, estructura de rutas, inventario de campos,
escape de respuestas, eliminación de preparaciones GET de las vistas Paciente
y Doctor, contratos AJAX y `git diff --check` respecto del alcance. PASS real
en `clinica_dolor_demo_20261010_final`: preflight fail-closed, login, GET sin
escrituras, AJAX, persistencia de mapas/cuestionario, limpieza independiente,
no-op HTTP, acceso cruzado 403, finalización idempotente y restauración exacta
de la línea base. PASS de navegador sintético en 1280×720: modal P04, Escape,
estado de narración Escuchar/Detener, navegación de los tres paneles, marca
Pointer Event guardada, recuperación visual del cuestionario, ausencia de
overflow y consola sin errores.

NO VERIFICADO: conteo de sentencias SQL del no-op, reproducción audible humana,
stylus/táctil independiente, 1440×900, 768×1024, 375×667, full-page/capturas
exportadas y configuración de producción. La base quedó restaurada: una fila
P11 vacía histórica para 9301 y cero finalizaciones de módulo 9. El archivo
privado sintético que ya existía para 9301 se restauró byte a byte; no se
dejaron archivos nuevos fuera de esa línea base. `git diff --check` conserva
una incidencia histórica fuera de P11 en
`app/Controllers/ModulosController.php:996`.

Los archivos heredados con nombres predecibles bajo
`public/assets/images/evaluacion-dolor` siguen presentes y son potencialmente
accesibles directamente; sólo las nuevas escrituras usan almacenamiento
privado. Esta limitación impide declarar protección completa de documentos
hasta una remediación de almacenamiento/DocumentRoot independiente. También se
conserva la ambigüedad histórica entre las etiquetas azul/amarillo y su texto
de instrucciones; no se cambió la semántica almacenada. P11 permanece en
`VALIDACIÓN` y no se inició P12.
