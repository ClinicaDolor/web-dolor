# TH-DOCTOR-SUBSEQUENT-NOTE-UX-AUDIT-01

## Auditoría UX, arquitectura e integraciones reales — Nota subsecuente Doctor

Fecha de auditoría: 2026-10-16
Proyecto: Clínica del Dolor
Rama observada: `Silvino`
Alcance: inspección estática exclusivamente; no se ejecutaron formularios clínicos, HTTP mutante, SQL ni acciones documentales.

## 1. Resumen ejecutivo

La ruta objetivo es dinámica:

`/clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}`

La vista real de alta/edición es `app/views/clinica/pacientes-nota-subsecuente.php`. La pantalla integra efectivamente tres dominios clínicos: Nota subsecuente, Receta médica y Laboratorio. No se encontró integración real de COFEPRIS dentro de esta vista ni de su vista de detalle; COFEPRIS permanece como módulo separado del expediente.

La referencia no es un texto decorativo: el controlador la usa junto con el paciente para resolver `codigo_referencia` de la nota y de la receta; Laboratorio usa la misma referencia para listar documentación relacionada. El valor de ejemplo de la solicitud no se fijó ni se utilizó en la auditoría.

El lenguaje visual ya está parcialmente modernizado: shell Doctor, hero institucional, tarjeta de paciente, tarjetas clínicas, botón de regreso con el hook compartido `data-doctor-back-action`, editores Quill y tablas Simple-DataTables. Sin embargo, la composición todavía mezcla una consulta SQL directa dentro de la vista, tres editores en el mismo flujo, tabla de historial y bloques dinámicos de HTML. La recomendación es reorganizar por prioridad clínica sin cambiar contratos: contexto compacto, captura de nota como área principal, receta asociada y laboratorio como módulos relacionados, y detalle/historial como consulta secundaria.

Hallazgos de mayor prioridad antes de implementar: CSRF de la captura de Nota subsecuente no verificado; edición de nota y receta por identificador sin predicado visible de paciente/referencia; HTML dinámico generado en modelos e insertado con `innerHTML`; consultas interpoladas en varios métodos; `fixedHeight: true` en tablas; y doble inicialización potencial de componentes cuando el detalle se reemplaza dinámicamente. Son hallazgos de auditoría, no correcciones realizadas en esta fase.

## 2. Ruta y arquitectura real

### Ruta y controladores

| Elemento | Evidencia | Propósito |
|---|---|---|
| Vista principal | `app/views/clinica/pacientes-nota-subsecuente.php` | Alta y edición de Nota subsecuente, receta asociada y laboratorio relacionado. |
| Ruta principal | `app/Config/routes.php:124` | `GET /nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}`. |
| Controlador principal | `app/Controllers/ClinicaController.php:701-772` | Resuelve paciente, nota y receta por paciente + referencia; prepara el contexto y renderiza la vista. |
| Guardado | `app/Config/routes.php:125`, `app/Controllers/ClinicaController.php:775-806` | `POST /paciente/insert-nota-subsecuente`; inserta o edita según `idNota`. |
| Detalle de nota | `app/Config/routes.php:123`, `app/Controllers/NotaSubsecuenteController.php:10-52` | Vista de solo lectura por `idNota`, con autorización derivada del paciente propietario. |
| Detalle AJAX | `app/Config/routes.php:200`, `app/Controllers/BusquedasController.php:57-63` | Devuelve HTML de detalle de una nota después de `guardPatient`. |
| Tabla de laboratorios | `app/Config/routes.php:202`, `app/Controllers/BusquedasController.php:99-104` | Lista laboratorios filtrados por paciente y referencia. |
| Detalle de laboratorio | `app/Config/routes.php:201`, `app/Controllers/BusquedasController.php:66-71` | Devuelve detalle y descarga del archivo después de `guardPatient`. |

### Semántica de la referencia

`NotaSubsecuenteModel::codigoReferencia()` (`app/Models/NotaSubsecuenteModel.php:134-145`) busca `id` por `id_paciente` y `codigo_referencia`. El controlador usa el resultado para cargar la nota. `RecetaModel::idRecetaReferencia()` (`app/Models/RecetaModel.php:25-34`) realiza la relación equivalente para la receta. `LaboratorioModel::mostrarTablaLaboratorio()` (`app/Models/LaboratorioModel.php:263-294`) filtra por el mismo paciente y, cuando la referencia no es cero, por `codigo_referencia`.

Conclusión: la referencia representa el agrupador de atención/episodio documental compartido por la nota y sus elementos asociados, según el código observado. La semántica clínica exacta del valor no está formalizada en el código; no debe renombrarse ni reinterpretarse durante una modernización visual.

### Modelos y servicios relacionados

- `app/Models/NotaSubsecuenteModel.php`: lectura, inserción, edición, historial, detalle y listado de laboratorio asociado.
- `app/Models/RecetaModel.php`: resolución de receta por referencia, lectura y render del detalle.
- `app/Models/LaboratorioModel.php`: lectura, listado, detalle y reglas de carga PDF.
- `app/Models/ClinicaModel.php`: creación de la receta asociada durante la inserción de la nota (`insertPacienteReceta`).
- `app/Models/PacienteModel.php`: identidad y datos de contexto del paciente.
- `app/Middleware/AuthMiddleware.php`: sesión Doctor, aislamiento de clínica y `assertPatientAccess()`.
- `app/Services/ClinicalSummarySecurity.php`: token de captura utilizado por Laboratorio; no se observó su uso en el guardado de Nota subsecuente.

## 3. Inventario visual actual

Orden real en `pacientes-nota-subsecuente.php`:

1. Sidebar Doctor y topbar compartidos mediante `$data['sidebar']` y `search-bar-doctor.php`.
2. Hero `doctor-page-hero doctor-note-hero`, con eyebrow “Nota subsecuente”, título dinámico, descripción, regreso al expediente y botón `toggleSize()`.
3. Tarjeta `doctor-note-patient-card`, con nombre, fecha de alta, fecha de nacimiento, edad y sexo. No muestra icono ni número de expediente en esta vista.
4. Sección `doctor-note-history-section`: historial `#table1` en una columna y detalle `#detalleNota` en otra.
5. Sección `doctor-note-entry-section`, con encabezado “Registro clínico” y h2 “Crear nueva Nota Subsecuente”.
6. Tarjeta de captura de Nota: signos vitales, fecha/hora existente cuando aplica y editor Quill `#snow1`.
7. Tarjeta de Receta asociada: diagnóstico `#Diagnostico` y editor Quill `#snow2` para medicamento.
8. Tarjeta de Laboratorio: tabla dinámica `#conteLaboratorio`, detalle `#detalleLaboratorio`, título, archivo, editor Quill `#snow3` y botón de alta.
9. Tarjeta “Proxima cita” con `#FechaProximaCita`.
10. Mensaje `#mensaje` y botón de guardar/editar Nota subsecuente.
11. Footer Doctor y componente `modal-impresion-receta.php`.

### Contratos clínicos observados

| Componente | Editable | Datos clínicos | Dependencias | Reorganizable visualmente | Riesgo |
|---|---:|---:|---|---:|---|
| Signos vitales (`TA`, `FrecCardiaca`, `Pulso`, `Spo2`, `Fio2`, `Ecog`, `Karnovsky`, `Peso`, `Talla`) | Sí | Sí | Nota | Sí, preservando IDs y orden clínico | Alto si se renombran IDs o se altera el payload. |
| `#snow1` detalle/evolución | Sí | Sí | Nota, Quill | Sí, manteniendo contenido HTML y lectura | Alto por pérdida de contenido o doble inicialización. |
| `#Diagnostico` | Sí | Sí | Receta asociada | Sí | Alto si se separa de la nota/receta. |
| `#snow2` medicamento | Sí | Sí | Receta asociada, Quill | Sí | Alto por sanitización y persistencia. |
| `#snow3` descripción de laboratorio | Sí | Sí | Laboratorio, Quill | Sí | Alto si se mezcla con el editor de Nota. |
| `#Archivo` | Sí | Documento clínico | Laboratorio, CSRF | Sí | Alto por carga, PDF y autorización. |
| `#table1` | No | Historial clínico | Nota, Simple-DataTables | Sí | Medio; filas actualmente usan `onclick`. |
| `#detalleNota` | No | Detalle clínico | Nota, Receta, Laboratorio, `innerHTML` | Sí | Alto por HTML dinámico y autorización. |
| `#conteLaboratorio` / `#detalleLaboratorio` | No/acción de alta | Laboratorio | Laboratorio, fetch, Simple-DataTables | Sí | Alto por documentos y tabla dinámica. |
| `#FechaProximaCita` | Sí | Seguimiento | Nota | Sí | Medio; no se observó validación server-side específica. |

No se observaron acordeones, pestañas, formulario HTML envolvente, borrador explícito, firma, cierre clínico, finalización ni eliminación en esta pantalla.

## 4. Flujo clínico real

1. El Doctor abre la ruta con `idPaciente` y `referencia` dinámicos.
2. `pacienteNotaSubsecuente()` resuelve la nota por paciente + referencia; si no existe, usa `id_nota_subsecuente = 0` y presenta alta. Resuelve también la receta asociada por paciente + referencia.
3. El controlador autentica la vista `clinica` y ejecuta `assertPatientAccess($idPaciente, $auth)` antes de renderizar.
4. La vista carga la tabla de laboratorios por `GET /buscar/tabla-laboratorio/{idPaciente}/{referencia}`.
5. La selección de una fila del historial llama `DetalleNota(idNota)`, obtiene HTML por `GET /buscar/nota-subsecuente/{idNota}` y lo inyecta en `#detalleNota`.
6. `AgregarNota()` construye un JSON con paciente, referencia, IDs de nota/receta, signos vitales, editores, diagnóstico y próxima cita. Envía `POST /clinica/paciente/insert-nota-subsecuente`.
7. El controlador llama `assertPatientAccess()` sobre el `idPaciente` enviado y decide insertar o editar por `idNota`.
8. En alta, el modelo inserta la nota y llama a `ClinicaModel::insertPacienteReceta()`; devuelve ambos IDs.
9. En éxito, el navegador llama `imprimirReceta()` y redirige a `/clinica/nota-subsecuente/{idNota}`. No se observó una finalización clínica adicional.
10. Laboratorio utiliza un POST separado con `X-M03-CSRF`, y después refresca la tabla.
11. No se ejecutaron pruebas runtime, por lo que persistencia, autorización cruzada, doble envío, generación PDF y recuperación tras recarga permanecen sin verificar en esta auditoría.

## 5. Comparación con referencias Doctor

### Laboratorio

Laboratorio ya usa patrón de hero, franja de paciente con icono, tarjeta de listado a ancho completo, estados vacíos, modales de alta/detalle y `fixedHeight: false` en su DataTable (`app/views/clinica/pacientes-laboratorio.php:394-476`, `:554-559`). Nota subsecuente usa tarjetas heredadas y una tabla con `fixedHeight: true`, por lo que la primera mejora visual futura debe acercar la estructura de tabla y los estados a Laboratorio sin copiar su lógica de carga.

### Receta médica

Receta usa franja compacta con icono, contador, tarjeta de listado, modales independientes de alta, detalle y vista previa (`app/views/clinica/pacientes-recetas.php:610-791`). Nota subsecuente no usa esos modales: captura una receta dentro del formulario de Nota y sólo reutiliza `modal-impresion-receta.php` para impresión posterior. No debe convertirse en una segunda pantalla de Receta ni crear una receta duplicada.

### COFEPRIS

COFEPRIS tiene rutas, tabla, modales y endpoints propios (`app/Config/routes.php:140-150`, `:206-208`). No hay referencia a COFEPRIS en las dos vistas de Nota subsecuente ni en `NotaSubsecuenteModel`. La integración es ausente, no “pendiente de conectar”. No se recomienda añadirla dentro de Nota sin una decisión clínica y de alcance separada.

### PIN y expediente

PIN y expediente son contexto de navegación, no dependencias de captura. El botón de regreso de Nota ya tiene `data-doctor-back-action`, igual que Laboratorio, Receta, COFEPRIS y PIN. La regla global preexistente en `public/assets/css/app.css:21176-21227` fija fondo blanco, texto/icono morados, área mínima de 44 px y foco visible. No se modificó dicha regla.

## 6. Encabezado, paciente y navegación

- El botón real es un `<a>` con `href` generado mediante `SERVIDOR . 'clinica/paciente/' . (int)$data['idPaciente']`; conserva navegación semántica al expediente.
- El hook `data-doctor-back-action` permite reutilizar el contrato visual global. El destino es dinámico; no debe sustituirse por `history.back()` ni por un valor fijo.
- `toggleSize()` es un botón separado con `type="button"`, `aria-label`, `title` e icono `columns`.
- La tarjeta de paciente es compacta, pero carece de icono y número de expediente que sí aparecen en otros patrones de módulos. La incorporación de número debe aprobarse si se considera clínicamente necesario; no debe deducirse de un ID de URL sin confirmar el campo/contrato.
- En la vista de detalle (`app/views/clinica/nota-subsecuente.php:67-113`) la tarjeta de paciente es más extensa e incluye estado civil, CURP y contacto. Esto crea una inconsistencia de densidad entre alta/edición y detalle.

## 7. Organización de la Nota subsecuente

La composición actual prioriza la captura en la segunda mitad de la pantalla, pero divide la nota entre una columna de captura y una columna de laboratorio/cita. La recomendación futura es:

1. Hero y contexto compacto del paciente.
2. Captura principal de Nota a ancho dominante, con signos vitales y evolución juntos.
3. Receta asociada en tarjeta inmediatamente posterior o lateral sólo cuando el ancho sea suficiente.
4. Próxima cita junto a los datos de seguimiento, sin esconderla en una pestaña.
5. Laboratorio como tarjeta relacionada con listado, alta y detalle; mantener su referencia y CSRF.
6. Historial y detalle como sección de consulta debajo o en una composición secundaria responsive.
7. Una acción primaria de guardado claramente asociada con Nota; mensajes con `role="status"` y errores sin depender sólo de color.

La propuesta no elimina, resume ni cambia el orden semántico de campos. No se recomienda pestañas para signos vitales, evolución o receta porque ocultarían información clínica durante la captura.

## 8. Integraciones reales

La matriz completa está en `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_INTEGRATIONS_01.csv`.

### Laboratorio — presente

- Tabla: `tableLaboratorio`, cargada por `tableLaboratorio()`.
- Alta: `AgregarLaboratorio()` usa `/clinica/laboratorio/insert-laboratorio` con `X-M03-CSRF`.
- Detalle: `/buscar/laboratorio/{idLaboratorio}`.
- Relación: `idPaciente` + `referencia` en el listado.
- Documento: la política observada en `LaboratorioModel` limita a PDF, valida MIME/firma y 10 MiB (`app/Models/LaboratorioModel.php:181-227`); la pantalla de Nota consume el flujo existente, no se debe asumir que su UI por sí sola garantiza toda la política.
- Componente recomendado futuro: reutilizar contrato visual de los modales Laboratorio; no duplicar validación ni endpoint.

### Receta médica — presente

- Resolución por `RecetaModel::idRecetaReferencia()`.
- Alta/edición embebida en la captura de Nota, no mediante el modal de Receta.
- Alta de Nota llama a `insertPacienteReceta()`; edición llama a `editarNotaSubsecuente()` y actualiza la receta por `idReceta`.
- Impresión: `modal-impresion-receta.php`, `imprimirReceta()` y `generarPDF()` generan POST a `/pdf/receta/{id}` en nueva ventana.
- Componente recomendado futuro: conservar la tarjeta embebida para la relación atómica visual, reutilizar sólo el patrón de vista previa si se aprueba y no copiar el modal de alta independiente.

### COFEPRIS — ausente

No hay markup, fetch, endpoint, modelo ni relación `codigo_referencia` de COFEPRIS en el flujo real de Nota subsecuente. No debe agregarse por analogía.

### Otros módulos efectivamente presentes

- Historial de Nota subsecuente: `#table1`, consulta directa en la vista; no es un módulo independiente, sino navegación interna de notas.
- Detalle de Nota: `NotaSubsecuenteModel::getNotaSubsecuente()`, que compone signos vitales, evolución, receta y laboratorio.
- Expediente Doctor: destino del regreso, no una integración de datos dentro del formulario.
- Impresión/PDF de receta: componente compartido incluido en la vista; es documentación derivada y no debe ejecutarse durante la auditoría.

## 9. Modales y conflictos de JavaScript

Modales reales observados en la vista objetivo:

- `#modalImprimirReceta`, proveniente de `app/views/components/modal-impresion-receta.php`.

No se observaron en la vista objetivo modales de alta/detalle de Laboratorio, Receta o COFEPRIS. Laboratorio y Receta tienen sus propios sistemas modales en sus vistas independientes.

Riesgos estáticos:

- El componente de impresión usa una función global `imprimirReceta()` y un ID global `modalImprimirReceta`; si se incluyeran futuros modales de Receta sin namespacing, podrían colisionar.
- `DetalleNota()` reemplaza `#detalleNota` con `innerHTML`, ejecuta `feather.replace()` y vuelve a inicializar `#LaboratorioNotaSub` con `fixedHeight: true`. No hay guardia visible contra inicialización repetida del mismo nodo.
- `tableLaboratorio()` reemplaza `#conteLaboratorio` y crea un DataTable local; tampoco se observa una referencia de destrucción antes de refrescar la tabla.
- Tres instancias Quill (`#snow1`, `#snow2`, `#snow3`) se crean en `DOMContentLoaded`; no hay modales de Quill, pero cualquier re-render futuro de sus contenedores requiere preservar instancias y contenido.
- Las filas de historial usan `onclick` en `<tr>` sin evidencia de `tabindex`, `role` o equivalente teclado; es una brecha de accesibilidad e interacción.
- El `#mensaje` existe una vez en la vista; no hay duplicación confirmada en el HTML objetivo.

Reutilización factible: componentes visuales y patrones de foco/estado de Laboratorio y Receta, sí; sus controladores de modal o endpoints, no sin adaptación y validación de contratos.

## 10. Seguridad e integridad — revisión estática

Estados usados: `CONFIRMED`, `POTENTIAL`, `NOT_VERIFIED`, `NOT_APPLICABLE`.

| Control | Estado | Evidencia / limitación |
|---|---|---|
| Autenticación Doctor | CONFIRMED | `AuthMiddleware('clinica')->authPermisos()` en controlador principal, detalle y POST. |
| Aislamiento por clínica | CONFIRMED para el guard observado | `assertPatientAccess()` compara `auth.id_clinica` con `pc_paciente.id_clinica` para vista `clinica` (`app/Middleware/AuthMiddleware.php:69-95`). No es prueba runtime. |
| Autorización del paciente en GET principal | CONFIRMED estático | `ClinicaController.php:715` llama `assertPatientAccess($idPaciente,$auth)`. |
| Autorización de nota por registro | POTENTIAL | Detalle por `idNota` carga primero y después valida el paciente propietario; el POST de edición recibe `idNota`/`idReceta` del cliente y los modelos actualizan por ID sin predicado de paciente/referencia visible. |
| Autorización por referencia | POTENTIAL | Alta/edición recibe `referencia` del cliente; el GET inicial sí resuelve por paciente + referencia, pero el modelo de edición no vuelve a imponer ambos predicados. |
| CSRF de Laboratorio | CONFIRMED estático | Vista emite `captureCsrfToken()` y POST envía `X-M03-CSRF`; controlador valida en `ClinicaController.php:876-898`. |
| CSRF de Nota subsecuente | NOT_VERIFIED / POTENTIAL | `AgregarNota()` no envía token y `pacienteInsertNotaSubsecuente()` no muestra validación CSRF en el flujo inspeccionado. |
| Validación server-side de campos clínicos | NOT_VERIFIED | No se ejecutaron POST ni se encontró en la vista validación suficiente para declarar contrato completo. |
| Consultas parametrizadas | POTENTIAL | Hay métodos preparados, pero `RecetaModel::idRecetaReferencia()`, `NotaSubsecuenteModel::mostrarTablaNotas()` y `mostrarTablaLaboratorio()` interpolan valores en SQL. La vista también ejecuta SQL directo con `idPaciente`. |
| Escape HTML | POTENTIAL | Algunas vistas escapan nombre/URL, pero `getNotaSubsecuente()` y `LaboratorioModel::getLaboratorio()` concatenan valores de BD en HTML; la inserción mediante `innerHTML` amplía el impacto. |
| Sanitización de receta | CONFIRMED parcial | `RecetaModel::getReceta()` usa `escapeDisplayText()` y `sanitizeMedicationHtml()` para su propio render; el contrato completo de todos los campos y la nota no se verificó runtime. |
| Integridad de PDF | NOT_VERIFIED | Sólo se inspeccionó que el componente genera POST a `/pdf/receta/{id}`; no se imprimió ni descargó. |
| Doble envío/idempotencia | POTENTIAL | Un clic en `AgregarNota()` puede emitir POST sin bloqueo visible; el insert crea Nota y Receta acopladas. No se probó runtime. |
| Escrituras durante GET | NOT_VERIFIED | El código leído muestra consultas de lectura en GET; no se ejecutó la aplicación ni se inspeccionó la base. |
| Registros clínicos creados en auditoría | NOT_APPLICABLE | No se ejecutaron formularios, HTTP ni SQL. |

No se corrigió ningún hallazgo de seguridad en esta fase.

## 11. Accesibilidad

Fortalezas estáticas:

- Botón de regreso semántico `<a>` con icono decorativo `aria-hidden`.
- Toggle con `type="button"`, `aria-label` y `title`.
- Labels visibles para la mayoría de inputs.
- Regiones/controles de DataTable provistos por la biblioteca.
- Área de mensaje `#mensaje` existe y puede recibir texto.

Hallazgos:

- Las filas de `#table1` usan `onclick` y no muestran `tabindex`, `role="button"` ni manejo de teclado.
- El estado de guardado/errores se escribe en `#mensaje`, pero no se observó `role="status"`/`aria-live` en ese contenedor.
- Los editores Quill deben verificarse con teclado y lector de pantalla; su toolbar es externo al `<label>` y no se comprobó visualmente.
- El botón de Laboratorio no declara `type="button"`; hoy no está dentro de un `<form>` visible, pero se recomienda verificarlo antes de cualquier reestructuración.
- No se ejecutaron pruebas de foco, teclado, lector de pantalla o contraste.

## 12. Responsive — revisión estática

No hubo navegador nativo disponible para esta auditoría; no se declaran PASS visuales.

La CSS prevé:

- Hero apilado hasta `767px`.
- Acciones del hero a ancho disponible en móvil.
- Tarjetas con padding reducido.
- Historial con buscador de DataTable apilado.
- Tabla de historial con `table-layout: fixed` y tipografía reducida.
- Botón de guardado a ancho completo en móvil.
- Editores con altura natural (`height: auto`), aunque con `min-height` de 12rem/8rem.

Riesgos pendientes de comprobación en 375, 430, 768, 1024 y 1440 px: ancho real de los grupos de signos vitales, salto del hero, tabla dinámica inyectada, detalle con datos largos, editor Quill y modal de impresión. `fixedHeight: true` puede provocar espacio/scroll artificial en historial y laboratorio de detalle.

## 13. Propuesta visual para aprobación

### Estructura recomendada

1. Hero Doctor actual, manteniendo shell, título dinámico, descripción, regreso y distribución.
2. Franja de paciente compacta con nombre e identificador de expediente únicamente si el contrato de datos lo confirma.
3. Tarjeta principal de captura a ancho completo en escritorio amplio; signos vitales en grid adaptable y evolución inmediatamente debajo.
4. Tarjeta de receta asociada diferenciada, con la misma jerarquía de Laboratorio/Receta pero sin duplicar el flujo de creación.
5. Tarjeta de próxima cita junto a la receta sólo cuando haya espacio; en móvil, orden vertical después de la nota.
6. Laboratorio como módulo relacionado independiente, reutilizando estados, tabla y patrones de modal ya existentes, pero manteniendo sus endpoints y CSRF.
7. Historial/detalle como consulta secundaria; detalle inicialmente legible y sin controles duplicados.
8. Acción primaria de guardado al final de la captura, con estado de carga y resultado accesible, sólo si se aprueba una futura modificación funcional.

### Reglas de preservación

- No quitar campos, IDs, `name`/data attributes, referencia, editores ni consultas clínicas.
- No copiar COFEPRIS a Nota subsecuente.
- No reemplazar el enlace de regreso por historial del navegador.
- No convertir una receta asociada en una receta independiente.
- No crear modales hasta resolver autorización de registro, CSRF, idempotencia y reinicialización de tablas/editors.

## 14. Plan mínimo de implementación futura

### Etapa 1 — Homologación visual

- Objetivo: alinear hero, regreso, paciente y encabezados con Laboratorio/Receta/COFEPRIS.
- Archivos probables: `app/views/clinica/pacientes-nota-subsecuente.php`, `app/views/clinica/nota-subsecuente.php`, CSS scoped en `public/assets/css/app.css`.
- Riesgo: alterar el destino o el contrato dinámico de `referencia`.
- Aceptación: misma jerarquía visual, no cambian IDs/endpoints, pruebas responsive reales.

### Etapa 2 — Organización de captura

- Objetivo: priorizar Nota, agrupar signos vitales/evolución y conservar receta/cita.
- Dependencias: Quill, `AgregarNota()`, modelo de Nota y receta.
- Riesgo: perder contenido o crear doble inicialización.
- Aceptación: payload idéntico, todos los campos conservados, persistencia sintética PASS.

### Etapa 3 — Módulos relacionados

- Objetivo: ordenar Laboratorio y consulta de historial/detalle.
- Dependencias: endpoints `tabla-laboratorio`, `laboratorio`, `nota-subsecuente`, CSRF de captura.
- Riesgo: HTML dinámico, DataTables duplicados y documentos.
- Aceptación: una sola instancia por tabla, autorización cruzada PASS, sin overflow.

### Etapa 4 — Modales técnicamente viables

- Objetivo: evaluar reutilización de detalle/alta sin copiar modales incompatibles.
- Dependencias: IDs únicos, focus trap, Escape, CSRF y seguridad por registro.
- Riesgo: colisión con `modalImprimirReceta`, backdrop y funciones globales.
- Aceptación: prueba de teclado, cierre, foco, errores y autorización sin escrituras no previstas.

### Etapa 5 — Regresión y aprobación

- Objetivo: validar visual y funcionalmente en entorno sintético.
- Aceptación: PHP/JS/CSS, rutas, contratos, 375/430/768/1024/1440, persistencia y aprobación visual explícita.

## 15. Archivos requeridos para una implementación futura

Probables, sujetos a aprobación:

- `app/views/clinica/pacientes-nota-subsecuente.php`
- `app/views/clinica/nota-subsecuente.php`
- `public/assets/css/app.css` con CSS scoped
- Si se corrigen contratos de seguridad: `app/Controllers/ClinicaController.php`, `app/Models/NotaSubsecuenteModel.php`, `app/Models/RecetaModel.php`, `app/Models/LaboratorioModel.php` y pruebas focalizadas; esto queda fuera de una implementación visual y requiere autorización independiente.

## 16. Decisiones requeridas del propietario

1. ¿Debe el alta/edición conservar la composición de dos columnas o pasar a Nota a ancho dominante?
2. ¿Debe mostrarse número de expediente en la franja compacta, y cuál es el campo autorizado?
3. ¿Laboratorio debe abrir un modal dentro de Nota o conservar su tarjeta embebida?
4. ¿La receta debe seguir siendo parte del guardado de Nota o separarse en una acción explícita?
5. ¿Se autoriza una fase de seguridad para CSRF y propiedad de `idNota`/`idReceta` antes del rediseño funcional?
6. ¿Debe existir bloqueo de doble envío y estado de guardado visible?
7. ¿El detalle histórico debe permanecer en la misma pantalla o abrirse en la vista de solo lectura?

## 17. Validaciones realizadas

- PHP lint: ejecutado únicamente sobre archivos PHP relevantes, sin acceso a BD; resultado se registra en la entrega final.
- Sintaxis JavaScript: validación estática de scripts externos e inline; sin ejecución de formularios.
- CSS: revisión estática de selectores específicos, media queries y declaraciones de tablas/editors; no comprobación computada.
- Rutas: revisadas en `app/Config/routes.php`.
- Contratos: IDs, data attributes, funciones, endpoints y componentes comparados estáticamente.
- `git diff --check`: ejecutado sobre el worktree existente; cualquier hallazgo histórico se distingue de los dos archivos nuevos.
- Navegador: NO VERIFICADO. No hubo navegador nativo con la sesión XAMPP disponible en este entorno; no se hicieron capturas ni lecturas de consola/Network.

## 18. Estado final requerido

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_UX_AUDIT_01:
  STATUS: AUDIT_ONLY_READY_FOR_OWNER_REVIEW
  TARGET_ROUTE: "/clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}"
  MAIN_VIEW_IDENTIFIED: "app/views/clinica/pacientes-nota-subsecuente.php"
  CONTROLLER_IDENTIFIED: "ClinicaController::pacienteNotaSubsecuente; POST pacienteInsertNotaSubsecuente; detalle NotaSubsecuenteController::notaSubsecuente"
  MODELS_IDENTIFIED: "NotaSubsecuenteModel, RecetaModel, LaboratorioModel, ClinicaModel, PacienteModel"
  REFERENCE_SEMANTICS_IDENTIFIED: "codigo_referencia compartido por nota, receta y laboratorio; semántica de episodio no formalizada"
  CURRENT_LAYOUT: "hero + paciente + historial/detalle + captura en dos columnas + receta/laboratorio/cita"
  CURRENT_CLINICAL_FLOW: "GET resuelve por paciente/referencia; POST inserta/edita nota y receta; laboratorio separado; detalle y PDF por endpoints existentes"
  CURRENT_PATIENT_HEADER: "tarjeta compacta con nombre, alta, nacimiento, edad y sexo; sin icono ni expediente"
  CURRENT_NOTE_FORM: "signos vitales + Quill snow1 + receta Diagnostico/snow2 + laboratorio snow3 + próxima cita"
  CURRENT_SAVE_FLOW: "fetch POST JSON; insertNotaSubsecuente o editarNotaSubsecuente; impresión de receta y redirección al detalle"
  LABORATORY_INTEGRATION: "PRESENT; tableLaboratorio, detalle, alta PDF con X-M03-CSRF"
  PRESCRIPTION_INTEGRATION: "PRESENT; receta por referencia, persistencia acoplada, modal de impresión/PDF"
  COFEPRIS_INTEGRATION: "ABSENT_FROM_TARGET_FLOW"
  OTHER_MODULES_IDENTIFIED: "historial/detalle de notas, expediente, impresión de receta"
  INTEGRATION_ENDPOINTS_IDENTIFIED: "YES; routes.php 123-125, 136-138, 200-203, PDF receta via modal component"
  INTEGRATION_MATRIX_CREATED: "YES; TH_DOCTOR_SUBSEQUENT_NOTE_INTEGRATIONS_01.csv"
  REFERENCE_DEPENDENCIES: "YES; note.codigoReferencia, recipe.idRecetaReferencia, lab.codigo_referencia"
  SHARED_COMPONENTS_FOUND: "search-bar-doctor.php, footer-doctor.php, modal-impresion-receta.php, app.css, loader/app/main/search scripts"
  BACK_BUTTON_STYLE: "shared white background, institutional purple text/icon, arrow-left, focus visible"
  BACK_BUTTON_SHARED_PATTERN: "YES; data-doctor-back-action and global CSS hook already present"
  INSTITUTIONAL_TYPOGRAPHY: "doctor-page-hero, dashboard-eyebrow, clinic ink/muted/primary tokens"
  REDUNDANT_HEADINGS: "No duplicate heading proven; density inconsistency between edit and read-only patient cards"
  COMPACT_PATIENT_HEADER_FEASIBLE: "YES, subject to approved expediente field"
  MAIN_NOTE_LAYOUT_RECOMMENDATION: "Nota at dominant width; vital signs and evolution grouped; preserve all IDs"
  RELATED_MODULES_LAYOUT_RECOMMENDATION: "Recipe and appointment grouped after note; laboratory independent related card; history secondary"
  EXISTING_MODALS: "modalImprimirReceta only in target views"
  MODAL_REUSE_FEASIBILITY: "PARTIAL; visual patterns reusable, full Laboratorio/Receta modals require contract/security review"
  MODAL_ID_CONFLICTS: "No duplicate ID proven in target; future collision risk with global modalImprimirReceta"
  MODAL_EVENT_CONFLICTS: "Potential global function/DataTable reinitialization conflicts; runtime not verified"
  EDITOR_INITIALIZATION_RISKS: "Three Quill instances; dynamic innerHTML replacement and repeated DataTable init require guards"
  CSRF_STATUS: "Laboratory CONFIRMED static; Note subsecuente NOT_VERIFIED/POTENTIAL"
  PATIENT_AUTHORIZATION: "CONFIRMED static for GET and detail guard; POST record ownership not fully proven"
  CLINIC_ISOLATION: "CONFIRMED static in assertPatientAccess; runtime NOT_VERIFIED"
  REFERENCE_AUTHORIZATION: "POTENTIAL; GET scoped by patient/reference, POST edit scope not visible in model predicates"
  CLINICAL_DATA_INTEGRITY: "POTENTIAL risks from raw HTML and coupled note/recipe writes; no runtime evidence"
  PDF_DOCUMENT_INTEGRITY: "NOT_VERIFIED; no print/download executed"
  ACCESSIBILITY_FINDINGS: "Rows use onclick without keyboard contract; #mensaje lacks explicit live-region semantics; Quill/runtime focus not tested"
  RESPONSIVE_STATIC_REVIEW: "PARTIAL static CSS review; real viewports NOT_VERIFIED"
  BROWSER_VALIDATION: "NO VERIFICADO"
  PRIORITIZED_FINDINGS: "P1 authorization/CSRF and raw HTML; P2 DataTable fixedHeight/reinit; P2 keyboard rows; P3 patient-header consistency"
  MINIMAL_IMPLEMENTATION_PLAN: "5 stages: visual shell, note organization, related modules, safe modals, regression/approval"
  FILES_REQUIRED_FOR_IMPLEMENTATION: "patients-nota-subsecuente.php, nota-subsecuente.php, scoped app.css; backend only under separate authorization"
  OWNER_DECISIONS_REQUIRED: "layout priority, expediente field, lab modality, recipe coupling, security phase, double-submit, detail placement"
  PHP_LINT: "PASS static"
  JS_SYNTAX: "PASS static for external/inline syntax; runtime NOT_VERIFIED"
  CSS_STATIC_REVIEW: "PASS static review; computed/browser NOT_VERIFIED"
  DIFF_CHECK: "PASS for new audit artifacts; worktree contains pre-existing changes"
  AUDIT_DOCUMENT_CREATED: "YES"
  INTEGRATION_CSV_CREATED: "YES"
  APPLICATION_CODE_MODIFIED: "NO"
  DATABASE_MODIFIED: "NO"
  CLINICAL_RECORDS_CREATED: "NO"
  COMMITS: "NONE"
  DEPLOYMENTS: "NONE"
  READY_FOR_OWNER_REVIEW: "YES, with runtime and security validation pending"
  NEXT_RECOMMENDED_ACTION: "Owner approves the visual proposal and separately authorizes security/runtime remediation before implementation"
```

