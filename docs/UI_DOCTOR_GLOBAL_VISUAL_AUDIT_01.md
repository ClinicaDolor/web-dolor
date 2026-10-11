# Auditoría global visual Doctor — UI-AUDIT-DOCTOR-01

**Proyecto:** Clínica del Dolor
**Fecha de auditoría:** 2026-10-10
**Alcance:** vistas D01–D40 del área Doctor
**Tipo:** diagnóstico estático y planificación; sin cambios de aplicación
**Estado del documento:** pendiente de revisión del responsable

## 1. Diagnóstico ejecutivo

El área Doctor tiene las 40 vistas inventariadas y los archivos PHP asociados existen en el árbol actual. El inventario maestro registra **35 vistas CERRADA, 5 en VALIDACIÓN y 0 PENDIENTE**: D01–D35 están CERRADA y D36–D40 están en VALIDACIÓN. El porcentaje estático es 87.5 % CERRADA y 12.5 % VALIDACIÓN.

La base visual compartida es reconocible: las vistas interiores D02–D40 usan el shell Doctor, `search-bar-doctor.php` y `footer-doctor.php`; D01 conserva un shell de autenticación específico. La hoja `public/assets/css/app.css` contiene patrones compartidos para tarjetas, encabezados, controles, tablas y módulos clínicos, además de bloques scoped para los módulos y resúmenes.

La auditoría estática no demuestra por sí sola aprobación visual, responsive, accesibilidad operativa ni ausencia de errores de consola. En esta ejecución no se obtuvo una sesión autenticada sintética reproducible y segura para abrir las 40 vistas; por tanto, capturas, dimensiones reales, overflow, interacción de teclado, foco, estados dinámicos y consola quedan **NO VERIFICADOS**. Las afirmaciones históricas contenidas en el inventario maestro se conservan como evidencia previa declarada, pero no se presentan como una nueva ejecución.

No se confirma ningún P0 o P1 visual con la evidencia disponible. Sí existen riesgos P2/P3 que requieren comprobación visual o funcional, principalmente alturas fijas en tablas, reglas compartidas de `height: 100%`, cascada CSS heredada y la interacción táctil del mapa de D31. Los riesgos de seguridad y persistencia no se convierten en hallazgos visuales: permanecen separados y abiertos.

**Veredicto:** listo a nivel estático; pendiente de validación visual reproducible. No es una declaración de preparación para producción clínica.

## 2. Método y evidencia

Se revisaron, sin modificar, las siguientes fuentes:

- `docs/UI_DESIGN_CLOSURE_MASTER.md`, como inventario y registro histórico de estados, rutas y evidencias.
- `app/Config/routes.php`, para contrastar rutas disponibles y el enrutamiento genérico de Historia Clínica y resúmenes.
- Los 40 archivos PHP listados en la matriz de esta auditoría.
- `public/assets/css/app.css`, incluidos el shell Doctor compartido y los bloques scoped de D31–D40.
- `public/assets/js/loader.js`, `main.js`, `search-main.js`, `editor-functions.js`, `CKEditor-utilities.js`, `login.js` y `app.js` cuando existe.

Comprobaciones ejecutadas el 2026-10-10:

| Comprobación | Resultado | Evidencia / limitación |
|---|---|---|
| Inventario D01–D40 | PASS estático | 40 filas únicas en `docs/UI_DESIGN_CLOSURE_MASTER.md`; sin IDs faltantes ni duplicados. |
| Estados del inventario | PASS estático | 35 `CERRADA`, 5 `VALIDACIÓN`, 0 `PENDIENTE`; D01–D35 / D36–D40. |
| Existencia de vistas | PASS estático | Los 40 archivos PHP de la matriz existen. |
| PHP lint | PASS | `php -l` sobre las 40 vistas; 0 fallos. |
| Sintaxis JavaScript | PASS | `node --check` sobre los 7 archivos disponibles; 0 fallos. |
| Balance de CSS | PASS estático | `app.css`: 2,746 llaves de apertura y 2,746 de cierre. |
| IDs literales duplicados | PASS estático | No se encontraron duplicados dentro de las 40 vistas mediante escaneo literal. No sustituye un DOM generado. |
| Shell / footer / buscador | PASS estático | D02–D40 incluyen `search-bar-doctor.php` y `footer-doctor.php`; D01 es login y usa shell propio. |
| `git diff --check` | FAIL global preexistente | Reporta whitespace en `app/Controllers/PdfController.php:18,28,43–45,58–60` y `public/assets/css/app.css:4138,4139,4141,4166,4224`. Esta auditoría no modifica esos archivos. |
| Capturas y viewports | NO VERIFICADO | No se estableció sesión sintética autenticada segura para navegar sin ejecutar escrituras GET ni exponer datos. |
| Consola, foco, teclado e interacción | NO VERIFICADO | Requieren navegador y sesión sintética reproducible. |

## 3. Cobertura del inventario

El inventario maestro contiene exactamente 40 filas estables D01–D40. No se detectó renumeración ni una segunda fila para una misma vista. La numeración refleja dos familias de pantallas:

- **D01–D22:** acceso, shell, pacientes, expediente, recetas, COFEPRIS y perfil.
- **D23–D31:** módulos de Historia Clínica Doctor.
- **D32–D40:** resúmenes de Historia Clínica Doctor.

La ruta genérica de D23–D31 es atendida por `ModulosController::moduloVistaDoctor` con el módulo correspondiente. La ruta genérica de D32–D40 es atendida por `ModulosController::moduloVistaResumen`. La existencia del controlador no equivale a autorización correcta para cada parámetro; esa validación se mantiene en la sección de riesgos.

## 4. Matriz maestra D01–D40

**Lectura de la matriz:** “estático PASS” significa que el código revisado contiene el patrón o contrato indicado; “NO VERIFICADO” significa que no se ejecutó la pantalla en navegador durante esta auditoría. Las observaciones P2/P3 son riesgos o comprobaciones pendientes, no defectos visuales confirmados.

| ID | Nombre | Ruta real | Vista PHP | Estado maestro | Shell / composición estática | Hallazgo visual, severidad y evidencia | Pruebas no verificadas |
|---|---|---|---|---|---|---|---|
| D01 | Login Doctor | `GET /clinica/acceso`; `POST /clinica/login` | `app/views/login/login-clinica.php` | CERRADA | Shell de autenticación `auth-page-doctor`; no usa shell interior | Diseño específico separado, coherente por contrato con login; sin hallazgo confirmado. Evidencia: vista, `auth-visual.php`, `auth-icon.php`, `app.css`. | Viewport, foco, mensajes y consola en esta auditoría. |
| D02 | Dashboard Doctor | `GET /clinica` | `app/views/clinica/index.php` | CERRADA | Shell Doctor, sidebar, topbar, buscador y footer | Patrón dashboard institucional presente; sin hallazgo confirmado. Evidencia: vista y `app.css`. | Capturas, KPI autorizados, responsive e interacción. |
| D03 | Directorio de Pacientes | `GET /clinica/pacientes` | `app/views/clinica/pacientes.php` | CERRADA | Shell Doctor, tabla Simple-DataTables, footer | `fixedHeight: true` en `app/views/clinica/pacientes.php:150`; P2 potencial, impacto de altura no confirmado. | Búsqueda, paginación, dropdown, teclado, 1440/1280/768/375 y consola. |
| D04 | Paciente Nuevo / Editar | `GET /clinica/paciente/nuevo`; `GET /clinica/paciente/editar/{idPaciente}` | `app/views/clinica/paciente-nuevo-editar.php` | CERRADA | Shell Doctor, formulario compartido, footer | Formulario scoped e identidad institucional presentes; no se detecta defecto visual estático. | Capturas de ambos modos y validación completa en esta auditoría. |
| D05 | Expediente individual | `GET /clinica/paciente/{idPaciente}` | `app/views/clinica/pacientes-detalle.php` | CERRADA | Shell Doctor, encabezado de paciente, tarjetas clínicas, Historia Clínica y footer | Composición compartida presente; sin defecto visual confirmado. | Estados AJAX, dropdowns, 9 módulos, overflow, teclado y consola. |
| D06 | Historia Clínica de módulos | `GET /clinica/modulos/paciente/{idPaciente}`; destinos `/clinica/{modulo}/paciente/{idPaciente}` y `/clinica/resumen/{modulo}/paciente/{idPaciente}` | `app/views/clinica/pacientes-modulos.php` | CERRADA | Shell Doctor, tabla de módulos, dropdown de acciones | Nueve módulos y fila total están representados en el código; sin defecto estático confirmado. | 9 dropdowns, búsquedas, redibujado, autorización y viewports. |
| D07 | PIN del paciente | `GET /clinica/paciente/pin/{idPaciente}`; `POST /clinica/paciente/insert-pin` | `app/views/clinica/paciente-pin.php` | CERRADA | Shell Doctor, tarjeta de información, tabla y footer | Composición estática consistente; sin defecto visual confirmado. | Generación de PIN, tabla vacía, estados de error, responsive y consola. |
| D08 | Nota subsecuente — alta/edición | `GET /clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}`; `POST /clinica/paciente/insert-nota-subsecuente` | `app/views/clinica/pacientes-nota-subsecuente.php` | CERRADA | Shell Doctor, Quill, tablas, modal y footer | `fixedHeight: true` en líneas 57, 242, 609 y 619; P2 potencial de scroll/altura artificial, no confirmado visualmente. | POST, AJAX, tablas, Quill, estados de error, responsive y consola. |
| D09 | Nota subsecuente — detalle | `GET /clinica/nota-subsecuente/{idNota}` | `app/views/clinica/nota-subsecuente.php` | CERRADA | Shell Doctor, detalle clínico, AJAX y footer | Sin defecto visual estático confirmado. | Acceso cruzado de página/AJAX, descarga, PDF, responsive y consola. |
| D10 | Laboratorio — listado del paciente | `GET /clinica/laboratorio/paciente/{idPaciente}`; `POST /clinica/laboratorio/insert-laboratorio`; `GET /buscar/laboratorio/{idLaboratorio}` | `app/views/clinica/pacientes-laboratorio.php` | CERRADA | Shell Doctor, tablas, Quill, detalle y footer | `fixedHeight: true` en `app/views/clinica/pacientes-laboratorio.php:271`; P2 potencial, no confirmado. | Alta, archivo, búsqueda efectiva, responsive y documentos. |
| D11 | Laboratorio — detalle | `GET /clinica/laboratorio/{idLaboratorio}` | `app/views/clinica/laboratorio.php` | CERRADA | Shell Doctor, detalle y documento | Sin defecto visual confirmado. Warnings/deprecations ante registro inexistente constan como riesgo funcional separado. | Descarga, estados de error, responsive, consola y autorización completa. |
| D12 | Recetas del paciente | `GET /clinica/receta/paciente/{idPaciente}`; POST de receta; AJAX de tabla/detalle; PDF | `app/views/clinica/pacientes-recetas.php` | CERRADA | Shell Doctor, tabla, Quill, modal y footer | `fixedHeight: true` en líneas 128 y 349; P2 potencial de altura artificial, no confirmado. | Guardado, búsqueda efectiva, paginación, PDF, errores y consola. |
| D13 | Receta médica — detalle | `GET /clinica/receta/{idReceta}`; `POST /pdf/receta/{idReceta}` | `app/views/clinica/receta.php` | CERRADA | Shell Doctor, tarjetas de receta, modal y footer | Sin defecto visual estático confirmado. | PDF/impresión, descarga, estados de error, responsive y consola. |
| D14 | Directorio general de Recetas | `GET /clinica/recetas`; destinos internos y externos | `app/views/clinica/recetas.php` | CERRADA | Shell Doctor, dos tablas Simple-DataTables y footer | `fixedHeight: true` en `app/views/clinica/recetas.php:138`; P2 potencial. Riesgo de aislamiento entre clínicas es funcional/seguridad, no visual. | Filtrado real, paginación, externo, responsive e interacción. |
| D15 | Nueva Receta | `GET /clinica/recetas/nueva` | `app/views/clinica/recetas-nuevas.php` | CERRADA | Shell Doctor, formulario/editor y footer | CSS scoped de altura natural presente según inventario; sin defecto visual estático confirmado. | Guardado sintético, editor, responsive, consola y flujo externo. |
| D16 | Receta externa — detalle | `GET /clinica/receta-externo/{id}` | `app/views/clinica/receta-externo.php` | CERRADA | Shell Doctor, detalle externo y modal | Sin defecto visual estático confirmado; propiedad clínica no verificable. | GET autorizado, capturas, PDF, impresión y consola. |
| D17 | Receta externa — edición | `GET /clinica/receta-externo/editar/{id}`; POST de edición | `app/views/clinica/receta-externo-editar.php` | CERRADA | Shell Doctor, formulario Quill y footer | Sin defecto visual estático confirmado; riesgo de autorización externo separado. | POST, autorización, responsive, consola y documentos. |
| D18 | Configuración COFEPRIS | `GET /clinica/cofepris`; `POST /cofepris/insert-folios` | `app/views/clinica/configuracion-cofepris.php` | CERRADA | Shell Doctor, tarjetas, tabla y footer | `fixedHeight: true` en `app/views/clinica/configuracion-cofepris.php:46`; P2 potencial. | POST/archivo, viewports, consola y propiedad de folios. |
| D19 | COFEPRIS del paciente — alta/edición | `GET /clinica/cofepris/paciente/{idPaciente}`; edición y POST de registro | `app/views/clinica/pacientes-cofepris.php` | CERRADA | Shell Doctor, tarjetas, AJAX y footer | Toggle icon-only y orden DOM corregidos según inventario; sin defecto visual estático confirmado. | Capturas, toggle real, tabla, responsive y consola. |
| D20 | COFEPRIS — detalle | `GET /clinica/cofepris/{idCofepris}` | `app/views/clinica/cofepris.php` | CERRADA | Shell Doctor, detalle y footer | Sin defecto visual estático confirmado; PDF/descarga separado. | Autorización cruzada, documentos, responsive y consola. |
| D21 | COFEPRIS — folios/carpeta | `GET /clinica/cofepris/carpeta/{idCarpeta}`; `POST /cofepris/edit-surtido` | `app/views/clinica/cofepris-folios.php` | CERRADA | Shell Doctor, tabla, footer | `fixedHeight: true` en `app/views/clinica/cofepris-folios.php:230`; P2 potencial. | Propiedad de carpeta, POST, PDF, responsive y consola. |
| D22 | Perfil de clínica | `GET /clinica/perfil` | `app/views/clinica/perfil.php` | CERRADA | Shell Doctor, tarjetas de perfil, footer | Sin defecto visual estático confirmado; consulta SQL heredada es riesgo técnico separado. | Autorización, captura, responsive y consola. |
| D23 | Ficha de identificación | `GET /clinica/{modulo}/paciente/{idPaciente}`, módulo `ficha-identificiacion` | `app/views/historia-clinica-doctor/ficha-identificiacion.php` | CERRADA | Shell Doctor, tarjeta clínica, footer | Sin defecto visual estático confirmado; CSS scoped presente. | GET sintético, campos, responsive, teclado y consola. |
| D24 | Antecedentes familiares | `GET /clinica/antecedentes-familiares/paciente/{idPaciente}` | `app/views/historia-clinica-doctor/antecedentes-familiares.php` | CERRADA | Shell Doctor, tablas/cuestionarios y footer | `fixedHeight: true` en líneas 63 y 125; P2 potencial. | GET con siembra, AJAX, responsive, estados y consola. |
| D25 | Antecedentes personales no patológicos | `GET /clinica/antecedentes-personales-no-patologicos/paciente/{idPaciente}` | `app/views/historia-clinica-doctor/antecedentes-personales-no-patologicos.php` | CERRADA | Shell Doctor, siete cuestionarios y footer | Sin defecto visual confirmado; efectos GET constan como riesgo separado. | GET, AJAX, escritura accidental, responsive y consola. |
| D26 | Antecedentes personales quirúrgicos | `GET /clinica/antecedentes-personales-quirurgicos/paciente/{idPaciente}` | `app/views/historia-clinica-doctor/antecedentes-personales-quirurgicos.php` | CERRADA | Shell Doctor, tabla, formularios y footer | `fixedHeight: false` y correcciones scoped registradas; sin defecto visual estático confirmado. | CRUD sintético, autorización, responsive y consola. |
| D27 | Antecedentes personales patológicos | `GET /clinica/antecedentes-personales-patologicos/paciente/{idPaciente}` | `app/views/historia-clinica-doctor/antecedentes-personales-patologicos.php` | CERRADA | Shell Doctor, cuestionarios, tabla y footer | `fixedHeight: true` en `:86`; P2 potencial. Riesgos de contexto/IDs son funcionales separados. | Tabla, AJAX, edición, contexto, responsive y consola. |
| D28 | Medicación actual | `GET /clinica/medicacion-actual/paciente/{idPaciente}` | `app/views/historia-clinica-doctor/medicacion-actual.php` | CERRADA | Shell Doctor, tabla y footer | Altura natural declarada en el inventario; sin defecto visual estático confirmado. | CRUD, autorización, responsive y consola. |
| D29 | Medicamentos usados para controlar el dolor | `GET /clinica/medicacion-control-dolor/paciente/{idPaciente}` | `app/views/historia-clinica-doctor/medicacion-control-dolor.php` | CERRADA | Shell Doctor, seis cuestionarios y footer | Sin defecto visual estático confirmado; siembra GET heredada separada. | AJAX, GET, edición, responsive y consola. |
| D30 | Procedimientos usados para controlar el dolor | `GET /clinica/procedimientos-control-dolor/paciente/{idPaciente}` | `app/views/historia-clinica-doctor/procedimientos-control-dolor.php` | CERRADA | Shell Doctor, dos tarjetas/tablas y footer | Sin defecto visual estático confirmado; siembra GET y autorización separadas. | CRUD, tablas, responsive y consola. |
| D31 | Evaluación del dolor | `GET /clinica/evaluacion-dolor/paciente/{idPaciente}` | `app/views/historia-clinica-doctor/evaluacion-dolor.php` | CERRADA | Shell Doctor, canvas de mapas, cuestionario y footer | P2 funcional/responsive potencial: el código registra `mousedown`/`mousemove`/`mouseup`, pero no se observaron handlers touch/pointer en la inspección estática. Requiere prueba real; no es fallo confirmado. | Interacción táctil, coordenadas, GET, AJAX, responsive y consola. |
| D32 | Resumen de ficha de identificación | `GET /resumen/ficha-identificiacion/paciente/{idPaciente}` mediante `GET /resumen/{modulo}/paciente/{idPaciente}` | `app/views/resumen-clinica-doctor/ficha-identificiacion.php` | CERRADA | Shell Doctor, tarjetas de resumen, editor y footer | Bloque scoped propio; sin defecto visual confirmado. | GET, editor, notas, PDF, responsive y consola. |
| D33 | Resumen de antecedentes familiares | `GET /resumen/antecedentes-familiares/paciente/{idPaciente}` mediante ruta genérica | `app/views/resumen-clinica-doctor/antecedentes-familiares.php` | CERRADA | Shell Doctor, resumen, notas y footer | Bloque scoped propio; sin defecto visual confirmado. | GET, siembra, editor, PDF, responsive y consola. |
| D34 | Resumen de antecedentes no patológicos | `GET /resumen/antecedentes-personales-no-patologicos/paciente/{idPaciente}` mediante ruta genérica | `app/views/resumen-clinica-doctor/antecedentes-personales-no-patologicos.php` | CERRADA | Shell Doctor, siete bloques, notas y footer | Bloque scoped propio; sin defecto visual confirmado. | GET, editor, PDF, responsive y consola. |
| D35 | Resumen de antecedentes quirúrgicos | `GET /resumen/antecedentes-personales-quirurgicos/paciente/{idPaciente}` mediante ruta genérica | `app/views/resumen-clinica-doctor/antecedentes-personales-quirurgicos.php` | CERRADA | Shell Doctor, tabla, notas y footer | Bloque scoped propio; sin defecto visual confirmado. | GET, tabla, editor, PDF, responsive y consola. |
| D36 | Resumen de antecedentes patológicos | `GET /resumen/antecedentes-personales-patologicos/paciente/{idPaciente}` mediante ruta genérica | `app/views/resumen-clinica-doctor/antecedentes-personales-patologicos.php` | VALIDACIÓN | Shell Doctor, cuatro bloques, tabla, notas y footer | Bloque scoped propio; sin defecto visual confirmado. Estado permanece VALIDACIÓN según maestro. | GET, siembra, editor, PDF, viewports, consola y aprobación visual. |
| D37 | Resumen de medicación actual | `GET /resumen/medicacion-actual/paciente/{idPaciente}` mediante ruta genérica | `app/views/resumen-clinica-doctor/medicacion-actual.php` | VALIDACIÓN | Shell Doctor, tabla, editor y footer | Bloque scoped propio; sin defecto visual confirmado. | GET, editor, PDF, viewports, consola y aprobación visual. |
| D38 | Resumen de medicación para control del dolor | `GET /resumen/medicacion-control-dolor/paciente/{idPaciente}` mediante ruta genérica | `app/views/resumen-clinica-doctor/medicacion-control-dolor.php` | VALIDACIÓN | Shell Doctor, seis bloques, notas y footer | Bloque scoped propio; sin defecto visual confirmado. | GET, siembra, editor, PDF, viewports, consola y aprobación visual. |
| D39 | Resumen de procedimientos para control del dolor | `GET /resumen/procedimientos-control-dolor/paciente/{idPaciente}` mediante ruta genérica | `app/views/resumen-clinica-doctor/procedimientos-control-dolor.php` | VALIDACIÓN | Shell Doctor, procedimientos/tratamientos, notas y footer | Bloque scoped propio; sin defecto visual confirmado. | GET, siembra, editor, PDF, viewports, consola y aprobación visual. |
| D40 | Resumen de evaluación del dolor | `GET /resumen/evaluacion-dolor/paciente/{idPaciente}` mediante ruta genérica | `app/views/resumen-clinica-doctor/evaluacion-dolor.php` | VALIDACIÓN | Shell Doctor, mapas/respuestas, editor y footer | Vista y CSS scoped de D40 revisados estáticamente; sin defecto visual confirmado. | GET, autorización, editor, PDF, viewports, consola y escritura GET. |

## 5. Evaluación transversal del sistema visual

### 5.1 Shell, encabezados y navegación

**Hechos comprobados:**

- D02–D40 declaran `app-shell-body app-shell-doctor`; los módulos clínicos agregan `doctor-clinical-view` y los resúmenes agregan `doctor-summary-page`.
- D02–D40 incluyen una instancia de `search-bar-doctor.php` y una de `footer-doctor.php` en el escaneo estático.
- D01 utiliza `auth-page auth-page-doctor` y recursos de autenticación específicos; no debe forzarse al shell de páginas interiores.
- La búsqueda realizada no encontró referencias activas a `footer-mvsd.php` dentro de las 40 vistas Doctor auditadas.
- No se encontraron IDs literales duplicados en los archivos PHP. Esto no valida IDs generados por loops o fragmentos incluidos.

**Evaluación:** la arquitectura visual compartida está consolidada a nivel de includes y clases. La equivalencia real de alturas, navegación por teclado, estado activo único, foco y comportamiento móvil requiere navegador.

### 5.2 Tarjetas, altura y densidad

`public/assets/css/app.css:2975–2978` declara para el shell Doctor:

```css
body.app-shell-body.doctor-clinical-view .card {
    height: 100%;
    margin-bottom: 1rem;
}
```

Hay sobreescrituras scoped para algunas pantallas, pero no existe evidencia estática suficiente para afirmar que todas las tarjetas se comporten con altura natural en todas las combinaciones de contenido. Se observan reglas adicionales de `min-height: 100%` o `height: 100%` en:

- `app.css:3650–3653`, notas subsecuentes.
- `app.css:3897–3900`, tarjeta de seguimiento.
- `app.css:5435–5440`, tarjetas del expediente.
- `app.css:6971–6983`, tarjetas/enlaces del dashboard de paciente.

El bloque `app.css:4145–4153` para `.LoaderPage` se interpreta como overlay de carga y no como defecto de tarjeta.

**Clasificación:** P2 potencial de densidad/espacio; no confirmado sin capturas y mediciones DOM.

### 5.3 Tablas y Simple-DataTables

Las ocurrencias de `fixedHeight: true` están inventariadas en la matriz. La configuración puede ser correcta para una tabla concreta, pero puede producir espacio artificial o scroll interno con pocos registros. No se debe cambiar globalmente sin reproducir cada caso con fixture.

`app.css:3056–3061` aplica `overflow-x: auto` y `border-radius: 10px` a wrappers de tabla. El escaneo de CSS no encontró en esas líneas un `border` adicional; por tanto, no se confirma un borde duplicado global. Los bordes anidados deben verificarse por DOM y estilos computados en cada tabla.

### 5.4 Formularios, editores e interacciones

Los archivos conservan los contratos de formulario, Quill/CKEditor, AJAX y acciones indicados por el inventario. La presencia de IDs y funciones en PHP no demuestra que las referencias existan después de un renderizado real, que no haya listeners duplicados o que los mensajes sean accesibles.

D31 merece una prueba interactiva específica: el mapa usa `getBoundingClientRect()` y escalado `canvas.width / rect.width` y `canvas.height / rect.height`, lo que es una buena señal para coordenadas CSS; sin embargo, la inspección sólo observó eventos de ratón. El soporte táctil/pointer queda NO VERIFICADO.

### 5.5 Cascada y mantenibilidad CSS

Hay un bloque compartido grande en `app.css` y bloques scoped por módulo/resumen. También hay uso de `!important`, por ejemplo en el bloque Doctor alrededor de `app.css:3003–3005` y `3022–3024`, además de reglas históricas globales. No es por sí solo una regresión visual, pero aumenta el riesgo de que un componente aprobado dependa del orden de carga.

Los resúmenes D32–D40 tienen bloques separados en `app.css` —por ejemplo, marcadores alrededor de las líneas 12185, 12521, 12844, 13191 y 14861—. La repetición es un riesgo P3 de mantenibilidad; no se propone consolidar durante esta auditoría porque podría afectar vistas ya aprobadas.

## 6. Hallazgos por severidad

### P0 — Bloqueo crítico

**Ninguno confirmado visualmente.** No se dispone de evidencia de que una vista Doctor no cargue o impida una operación por un defecto de UI en esta ejecución.

### P1 — Defecto evidente de presentación

**Ninguno confirmado.** No se asigna P1 a una regla CSS potencial sin captura, medición DOM o reproducción.

### P2 — Riesgo visual/interactivo que requiere verificación

1. **Altura compartida de tarjetas.** `app.css:2975–2978`, con reglas adicionales de altura total en líneas indicadas arriba. Puede crear espacios vacíos o igualación no deseada dependiendo de la cuadrícula y contenido. Evidencia estática; captura NO VERIFICADA.
2. **Tablas con altura fija.** D03, D08, D10, D12, D14, D18, D21, D24 y D27 contienen `fixedHeight: true` en las líneas de la matriz. El impacto depende del número real de filas y del wrapper. Evidencia estática; reproducción NO VERIFICADA.
3. **Interacción táctil de D31.** Se observaron eventos de ratón y escalado de coordenadas para canvas, pero no handlers touch/pointer en la inspección. Puede afectar uso táctil o accesibilidad; no es un fallo confirmado hasta probarlo.

### P3 — Deuda de consistencia/mantenibilidad

1. **Uso de `!important` y cascada extensa.** Aumenta el riesgo de regresiones al modificar un componente compartido. Evidencia: `app.css` y bloque Doctor alrededor de líneas 3003–3005 y 3022–3024.
2. **Repetición de CSS por resumen.** D32–D40 tienen bloques scoped separados, con posible divergencia futura. Evidencia: marcadores de bloques en `app.css`.
3. **Validación visual incompleta de toda la matriz.** No hay capturas nuevas, medición de `innerWidth/innerHeight`, `scrollWidth`, estilos computados o consola para esta auditoría. Es una limitación de evidencia, no un defecto de una pantalla.

## 7. Riesgos funcionales y de seguridad separados

Estos riesgos no se corrigen ni se reclasifican como defectos visuales:

| Riesgo | Evidencia actual | Estado para la auditoría |
|---|---|---|
| ACT-05C-02: autorización de documentos clínicos, PDF, descargas y almacenamiento | Registro histórico y documentación del proyecto | Abierto; bloquea uso con información clínica real hasta remediación y pruebas. |
| Recetas externas | D14 y D16–D17; no existe propiedad clínica verificable para todos los registros | Abierto; D14 tiene contención interna documentada, rutas independientes pendientes. |
| COFEPRIS | D18–D21, folios y documentos con autorización/propiedad incompleta | Abierto y separado de UI. |
| Escrituras durante GET | Riesgos documentados en D24, D25, D29, D30, D31, D33, D34, D36, D38, D39 y D40 según inventario | Abierto; no ejecutar GET autenticado sin fixture aislado y descartable. |
| SQL concatenado y exposición de errores | Riesgos documentados en vistas/modelos, especialmente notas, recetas y perfil | Abierto; requiere actividad de seguridad independiente. |
| Contexto `idPaciente` / autorización por registro | SEC-D26-01 y riesgos en acciones de módulos/registro | Abierto; la presencia de sesión no basta para demostrar propiedad. |
| D14 aislamiento de recetas internas | El inventario maestro registra el fallo histórico confirmado | Debe verificarse el estado actual de la remediación SEC-D14-02; esta auditoría no lo reejecuta. |

## 8. Validación y compatibilidad

### PASS

- Inventario estático completo D01–D40, sin duplicados ni faltantes.
- Existencia de los 40 archivos PHP de vista.
- PHP lint de las 40 vistas.
- Sintaxis de los 7 scripts JavaScript disponibles.
- Balance de llaves de `app.css`.
- Ausencia de IDs literales duplicados dentro de cada vista escaneada.
- Shell Doctor, footer y barra de búsqueda presentes estáticamente en D02–D40.

### FAIL

- `git diff --check` global: whitespace en `app/Controllers/PdfController.php` y `public/assets/css/app.css`, detallado en la tabla de método. No se atribuye al informe, no se limpia y no se reformatean archivos ajenos.

### NO VERIFICADO

- Capturas reales de D01–D40 en 1440×900, 1280×720, 768×1024 y 375×667 durante esta auditoría.
- `window.innerWidth`, `window.innerHeight`, `scrollWidth`, `scrollHeight` y overflow real por vista.
- Consola JavaScript, focus ring, tab order, Escape, menús, Simple-DataTables y editores.
- Estados vacíos, carga, error, validación, tablas con múltiples páginas y redibujados dinámicos.
- Autorización propia/cruzada y respuestas HTTP por vista.
- POST, AJAX de escritura, guardados clínicos, PDF, impresión, descargas y efectos GET.

## 9. Plan de corrección propuesto — no implementado

1. **Fase de evidencia visual:** habilitar una base y sesión sintéticas descartables, sin datos reales y con GET de escritura bloqueado o aislado; ejecutar una matriz de capturas en las cuatro resoluciones para las 40 vistas. Registrar `innerWidth`, `innerHeight`, `scrollWidth`, `scrollHeight`, consola y foco.
2. **Fase de tarjetas:** sólo si las capturas confirman el problema, sustituir reglas compartidas de `height: 100%` por altura natural o aplicar excepciones scoped por layout. Criterio: sin huecos artificiales, sin superposición y sin regresión en D01–D40.
3. **Fase de tablas:** revisar cada `fixedHeight: true` con datos cero, uno y múltiples registros; conservar paginación/ordenamiento/búsqueda. Cambiar sólo la vista que demuestre scroll o espacio artificial.
4. **Fase de interacción D31:** validar mouse, teclado, touch/pointer, escalado y persistencia con fixture sintético. Si falla touch, abrir actividad funcional separada; no corregirlo como cambio visual global.
5. **Fase de mantenimiento:** después de la evidencia y aprobación, evaluar consolidación de tokens/componentes CSS. No refactorizar bloques de vistas aprobadas durante la presentación.
6. **Fase de seguridad independiente:** remediar ACT-05C-02, propiedad de recetas externas/COFEPRIS, SQL concatenado, errores expuestos, contexto de paciente y escrituras durante GET. Este plan no autoriza esos cambios.

## 10. Recomendación de cierre

La auditoría confirma una cobertura estática completa de las 40 vistas y una identidad Doctor compartida en el código. No confirma que las 40 experiencias estén visualmente aprobadas en navegador porque faltó una sesión sintética reproducible y segura para ejecutar la matriz real.

Se recomienda aceptar este documento como **línea base de auditoría estática**, conservar D01–D35 y D36–D40 en los estados que ya registra el inventario maestro, y ejecutar después la fase de evidencia visual antes de reclasificar P2 o aprobar cambios globales de `app.css`.

Las vulnerabilidades documentales, riesgos de recetas externas/COFEPRIS, escrituras GET, SQL heredado y autorización por registro permanecen abiertos. El cierre visual no equivale a cumplimiento normativo, autorización clínica, seguridad documental ni preparación para producción.

**No se modificaron PHP, CSS, JavaScript, rutas, modelos, controladores, base de datos ni `docs/UI_DESIGN_CLOSURE_MASTER.md` durante esta auditoría.**
