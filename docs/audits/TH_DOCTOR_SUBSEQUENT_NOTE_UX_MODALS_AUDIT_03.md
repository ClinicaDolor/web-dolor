# TH-DOCTOR-SUBSEQUENT-NOTE-UX-MODALS-AUDIT-03

## Auditoría exhaustiva de diseño, distribución, formularios y modales — Nota subsecuente

**Fecha:** 2026-10-17  
**Proyecto:** Clínica del Dolor  
**Rama observada:** `Silvino`  
**Alcance:** auditoría estática y propuesta; no implementación  
**Ruta objetivo:** `/clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}`  
**Vista principal:** `app/views/clinica/pacientes-nota-subsecuente.php`

> Este documento no modifica la aplicación. La auditoría se realizó sobre el código presente en el worktree, que ya contenía cambios locales previos. Esos cambios se preservaron.

## 1. Resumen ejecutivo

La vista de Nota subsecuente integra tres flujos reales: nota clínica, receta asociada y laboratorio relacionado. No integra COFEPRIS. La URL de alta/edición recibe paciente y referencia; el controlador usa ambos para resolver la nota y la receta, y Laboratorio utiliza la misma referencia para el listado relacionado.

El problema de composición no se debe únicamente a un `max-width`. La vista conserva dos regiones permanentes que compiten por espacio: historial/detalle en columnas `4/8` y captura relacionada en columnas `8/4`. Además, el CSS específico limita el contenedor a `width: min(100%, 1380px)`, mientras que el shell deja un área útil adicionalmente condicionada por sidebar, padding y breakpoints. La causa estática principal es comprobable; no se obtuvo medición de rectángulos ni estilos computados porque no hubo navegador conectado al XAMPP local.

La recomendación es una pantalla principal de consulta y captura ligera, con historial y laboratorios ocupando el ancho disponible. El detalle de nota, la creación de nota y la creación/detalle de laboratorio pueden trasladarse a diálogos amplios sólo con precondiciones. El principal bloqueo previo a una implementación es de seguridad: el POST de Nota subsecuente valida acceso al paciente enviado, pero no se observó CSRF en ese flujo y la edición del modelo actualiza por identificadores sin imponer visiblemente paciente/referencia en la misma operación. Mover el formulario a un modal no debe ocultar ni duplicar esos controles.

**Resultado:** `AUDIT_ONLY_READY_FOR_OWNER_REVIEW`.

## 2. Fuentes revisadas

- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_UX_AUDIT_01.md`
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_UX_IMPLEMENTATION_02.md`
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_INTEGRATIONS_01.csv`
- `app/views/clinica/pacientes-nota-subsecuente.php`
- `app/views/clinica/nota-subsecuente.php`
- `app/views/clinica/pacientes-laboratorio.php`
- `app/views/clinica/pacientes-recetas.php`
- `app/views/clinica/pacientes-cofepris.php` sólo para confirmar separación de módulo
- `app/views/components/modal-impresion-receta.php`
- `app/Config/routes.php`
- `app/Controllers/ClinicaController.php`
- `app/Controllers/NotaSubsecuenteController.php`
- `app/Controllers/BusquedasController.php`
- `app/Models/NotaSubsecuenteModel.php`
- `app/Models/LaboratorioModel.php`
- `app/Models/RecetaModel.php`
- `app/Middleware/AuthMiddleware.php`
- `app/Services/ClinicalSummarySecurity.php`
- reglas específicas de Nota subsecuente en `public/assets/css/app.css`

## 3. Problemas observados y causas confirmadas

### 3.1 Contenido estrecho

**Confirmado estáticamente:** `public/assets/css/app.css` contiene para `body.app-shell-body.doctor-patient-note-page .main-content`:

```css
width: min(100%, 1380px);
margin-inline: auto;
```

La vista añade `container-fluid`, padding del shell y las columnas Bootstrap siguientes:

- Historial: `col-sm-4` para `#table1` y `col-sm-8` para `#detalleNota`.
- Captura: `col-xl-8` para Nota/Receta y `col-xl-4` para Laboratorio/Próxima cita.

Por ello, aun cuando el área posterior al sidebar sea más amplia, el contenido se presenta como dos composiciones partidas y los editores se comprimen. El cap de 1380 px no debe eliminarse globalmente sin comparar el shell; la solución propuesta es un ajuste scoped de la página y una distribución más lineal, no un cambio global del Doctor.

**No verificado:** ancho computado real, rectángulos, zoom, scrollbar y diferencia visual frente a Laboratorio/Receta/COFEPRIS en navegador.

### 3.2 Detalle permanente

`#detalleNota` vive dentro de `doctor-note-detail-card` junto al historial y se rellena inicialmente con `ultimaNota($data['idPaciente'])`. Esto reserva espacio incluso sin una selección reciente y hace que la vista muestre simultáneamente historial y detalle completo.

### 3.3 Captura permanente

Los editores y campos de Nota/Receta están siempre en el DOM visible. No hay `<form>` HTML: `AgregarNota()` arma un objeto JSON con los IDs y campos y ejecuta el POST manualmente. El traslado visual a un modal es técnicamente posible sin cambiar el endpoint, pero exige conservar todos los IDs, instancias Quill, el payload y el flujo de éxito/error.

### 3.4 Laboratorio permanente

La tarjeta lateral contiene el listado `#conteLaboratorio`, `#detalleLaboratorio`, título, archivo, editor Quill y acción `AgregarLaboratorio()`. La separación en listado + modal de alta + modal de detalle es coherente con `pacientes-laboratorio.php`, pero no debe copiar HTML o scripts literalmente: el flujo integrado tiene referencia, token `X-M03-CSRF` y un endpoint contextual distinto del módulo independiente.

### 3.5 Jerarquía

La vista tiene hero, franja de paciente, contexto, historial, detalle, registro clínico, Nota, Receta, Laboratorio, próxima cita y acciones de guardado. La densidad no proviene de contenido inexistente, sino de la convivencia de consulta y captura en la misma altura visual. La propuesta debe retirar sólo paneles permanentes redundantes; no debe eliminar campos clínicos.

## 4. Identificación, rutas y controladores

| Flujo | Ruta | Controlador / vista | Observación |
|---|---|---|---|
| Alta/edición | `GET /nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}` | `ClinicaController::pacienteNotaSubsecuente()` → `pacientes-nota-subsecuente.php` | Resuelve nota y receta por paciente + referencia. |
| Guardado Nota/Receta | `POST /paciente/insert-nota-subsecuente` | `ClinicaController::pacienteInsertNotaSubsecuente()` → `NotaSubsecuenteModel` | JSON; alta inserta Nota y receta asociada; edición actualiza por IDs. |
| Detalle de nota | `GET /buscar/nota-subsecuente/{idNota}` | `BusquedasController::buscarNotaSubsecuente()` | Devuelve HTML compuesto por modelo y guarda de paciente. |
| Vista de detalle | `GET /nota-subsecuente/{idNota}` | `NotaSubsecuenteController::notaSubsecuente()` → `nota-subsecuente.php` | Vista de consulta separada. |
| Tabla Laboratorio | `GET /buscar/tabla-laboratorio/{idPaciente}/{referencia}` | `BusquedasController::tableLaboratorio()` | Lista por paciente y referencia. |
| Detalle Laboratorio | `GET /buscar/laboratorio/{idLaboratorio}` | `BusquedasController::buscarLaboratorio()` | Detalle y descarga según HTML generado. |
| Alta Laboratorio | `POST /laboratorio/insert-laboratorio` | `ClinicaController::pacienteInsertLaboratorio()` | `FormData`, PDF y `X-M03-CSRF`. |
| Impresión receta | `POST /pdf/receta/{id}` en ventana nueva | `modal-impresion-receta.php` | Acción documental existente; no se ejecutó. |

El controlador de la vista principal ejecuta autenticación Doctor y `assertPatientAccess($idPaciente, $auth)` antes de renderizar. Las búsquedas por registro también tienen guardas estáticas de paciente. Estas conclusiones son de código; no son evidencia runtime.

## 5. Inventario completo de la vista actual

### 5.1 Shell y contexto

- Sidebar Doctor y `search-bar-doctor.php`.
- Hero “Nota subsecuente”, título dinámico y descripción.
- Enlace semántico “Volver al expediente” con URL construida para el paciente.
- `toggleSize()` con icono `columns`, `aria-label` y `title`.
- Franja compacta `doctor-note-patient-strip` con nombre y expediente.
- Tarjeta “Contexto de la consulta”: Fecha Alta, Fecha Nacimiento, Edad y Sexo.

### 5.2 Historial y detalle

- `#table1`, columnas `#` y `Fecha y Hora`.
- Filas con `role="button"`, `tabindex="0"`, `onclick`, Enter y Space.
- `#detalleNota`, inicialmente cargado con `ultimaNota()`.
- Detalle dinámico con receta, signos vitales, evolución y tabla de Laboratorio relacionada según el HTML del modelo.

### 5.3 Captura de Nota subsecuente

Campos reales preservados por el payload de `AgregarNota()`:

| ID | Uso |
|---|---|
| `TA` | presión arterial |
| `FrecCardiaca` | frecuencia cardiaca |
| `Pulso` | pulso |
| `Spo2` | saturación |
| `Fio2` | fracción de oxígeno |
| `Ecog` | escala ECOG |
| `Karnovsky` | escala Karnofsky |
| `Peso` | peso |
| `Talla` | talla |
| `snow1` | evolución/detalle Quill |
| `FechaProximaCita` | próxima cita |

La acción de guardado es un botón `type="button"` que llama `AgregarNota()`; no se observó `<form>` ni `action` HTML.

### 5.4 Receta asociada

- `#Diagnostico`.
- `#snow2` para medicamento Quill.
- `data-idreceta` en `#main`.
- Alta de Nota acoplada a `insertPacienteReceta()`.
- Modal compartido real `#modalImprimirReceta` para la acción documental posterior.

### 5.5 Laboratorio

- `#conteLaboratorio` y `#tableLaboratorio` dinámicos.
- `#detalleLaboratorio` dinámico.
- `#Titulo`.
- `#Archivo`.
- `#snow3` para descripción.
- Botón `Agregar` que llama `AgregarLaboratorio()`.
- Recarga de tabla después de éxito.

### 5.6 Mensajes y pie

- `#mensaje` con `role="status"` y `aria-live="polite"`.
- Footer Doctor.
- No se encontró COFEPRIS, firma, finalización clínica ni eliminación en esta vista.

## 6. Formularios y contratos

No existen formularios HTML convencionales en la vista objetivo. Los dos flujos de escritura son controles JavaScript que construyen requests.

### 6.1 Nota subsecuente y receta

- **Disparador:** `AgregarNota()`.
- **Método:** POST JSON.
- **Endpoint:** `/clinica/paciente/insert-nota-subsecuente`.
- **Identificadores:** `idPaciente`, `referencia`, `idNota`, `idReceta`.
- **Campos:** contenido Quill, diagnóstico, medicamento Quill, nueve campos vitales y próxima cita.
- **CSRF:** no observado en el código de vista/controlador revisado; estado `NOT_VERIFIED / POTENTIAL`, no corregido.
- **Éxito:** limpia diagnóstico/editors, llama `imprimirReceta()` y redirige a `/clinica/nota-subsecuente/{idNota}`.
- **Error:** escribe texto en `#mensaje`; no se observó un bloqueo de doble envío o reintento estructurado.

### 6.2 Laboratorio

- **Disparador:** `AgregarLaboratorio()`.
- **Método:** POST `FormData`.
- **Endpoint:** `/clinica/laboratorio/insert-laboratorio`.
- **Campos:** paciente, archivo PDF, descripción Quill, referencia y título.
- **CSRF:** `data-laboratorio-csrf` y header `X-M03-CSRF`; validación correspondiente visible en controlador.
- **Validaciones de vista:** requiere archivo y descripción no vacía.
- **Contrato observado en modelo:** PDF, MIME/firma y máximo 10 MiB.
- **Éxito:** recarga tabla Laboratorio.

### 6.3 Traslado a modal

Los controles pueden cambiar de posición visual sin cambiar el contrato, pero sólo si se conservan IDs, instancias Quill, atributos `data-*`, payload, orden clínico y mensajes. Los hidden/context values no deben reconstruirse desde el DOM de una tarjeta que se destruya al cerrar. La arquitectura recomendada es mantener un único formulario lógico por flujo, no clonar inputs.

## 7. Flujo clínico y referencia

1. Doctor abre paciente + referencia.
2. Controlador verifica sesión Doctor y pertenencia de paciente a la clínica.
3. La referencia se usa para resolver Nota y Receta y para filtrar Laboratorio.
4. Historial permite seleccionar una nota y consultar detalle por ID.
5. Guardado de Nota escribe Nota y, en alta, receta asociada.
6. Laboratorio se escribe por endpoint separado con archivo y CSRF.
7. Éxito de Nota redirige a la vista de detalle e inicia el flujo de impresión de receta.

La referencia no es decorativa. No debe eliminarse, sustituirse por `history.back()` ni inferirse sólo desde la URL durante una futura implementación.

## 8. Arquitectura visual actual y propuesta

### 8.1 Actual

```text
Hero + regreso + distribución
Paciente + contexto
Historial 4/12 | Detalle permanente 8/12
Captura 8/12  | Laboratorio + próxima cita 4/12
             | guardado de Nota
```

### 8.2 Propuesta recomendada

```text
Hero institucional + Volver al expediente
Franja compacta del paciente
Contexto de consulta compacto
Historial de Notas a ancho completo + Agregar Nota Subsecuente
Listado de Laboratorios relacionados a ancho completo + Agregar laboratorio
Estados vacíos específicos y errores separados
Modales amplios para alta/detalle, sólo después de los gates de seguridad
```

La captura de Nota no debe convertirse en un diálogo pequeño: contiene signos vitales, evolución, próxima cita y receta asociada. Si el propietario aprueba el modal, debe ser XL, con scroll interno accesible y modo casi pantalla completa en móvil. Como alternativa conservadora, puede mantenerse la captura como sección de página a ancho completo y convertir únicamente detalle y Laboratorio a modal.

### 8.3 Paneles redundantes

- `doctor-note-detail-card` es redundante como panel permanente cuando el detalle se abre desde una fila; candidato a modal.
- La tarjeta de captura no es redundante clínicamente; sólo su ubicación comprimida compite por espacio.
- La tabla y el formulario de Laboratorio pueden separarse visualmente; no se debe duplicar su contrato.
- El contexto de consulta no debe duplicarse en cada modal: debe permanecer como contexto visible y el modal debe tener título accesible con paciente/referencia sólo si el diseño lo exige.

### 8.4 Estados vacíos

Usar mensajes específicos sólo donde el endpoint confirme ausencia:

- “No hay notas subsecuentes registradas”.
- “No hay laboratorios registrados”.

Distinguir en UI entre cargando, error de red/servidor, no autorizado y sin registros. No presentar un error de autorización como ausencia de datos.

## 9. Propuesta de modales

### Modal 1 — Agregar Nota subsecuente

**Recomendación:** `RECOMMENDED_WITH_PRECONDITIONS`.

Contenido real: signos vitales, evolución `snow1`, diagnóstico, medicamento `snow2`, próxima cita y acción de guardado. Debe ser XL, con secciones visuales “Signos vitales”, “Evolución”, “Receta asociada” y “Próxima cita”, sin cambiar el orden semántico ni IDs.

Requisitos: un único conjunto de controles; Quill inicializado una sola vez; foco inicial en el primer campo; `role="dialog"`, `aria-modal`, título accesible, Escape y cierre; confirmación de cambios sin guardar para X/Cancelar/Escape/clic fuera; botón Guardar con bloqueo durante request; mensajes anunciados; no redirección hasta confirmar respuesta válida.

**Bloqueos:** CSRF de Nota no verificado; autorización de edición por registro/referencia no demostrada; idempotencia no demostrada; acoplamiento de guardado con receta e impresión.

### Modal 2 — Detalle Nota subsecuente

**Recomendación:** `RECOMMENDED_WITH_PRECONDITIONS`.

Debe abrirse al seleccionar la fila, reutilizar `GET /buscar/nota-subsecuente/{idNota}`, conservar receta, signos, evolución y laboratorio relacionado, y ofrecer el enlace de edición existente sólo si el contrato autoriza esa nota. Debe usar un contenedor de detalle controlado, no insertar HTML no confiable sin sanitización/escape uniforme.

**Bloqueos:** HTML dinámico generado por modelos contiene concatenaciones sin escape uniforme; eventos y DataTables del contenido reemplazado requieren ciclo de vida explícito; autorización por registro debe verificarse antes de considerarlo cerrado.

### Modal 3 — Agregar laboratorio

**Recomendación:** `RECOMMENDED_WITH_PRECONDITIONS`.

Puede reutilizar el patrón visual del modal de `pacientes-laboratorio.php`, no su HTML o listeners por copia. Debe conservar `idPaciente`, `referencia`, `#Titulo`, `#Archivo`, `#snow3`, PDF, límite 10 MiB, CSRF `X-M03-CSRF`, errores y recarga de la tabla.

En móvil, el cuerpo debe tener scroll y el pie permanecer alcanzable; el selector de archivo debe tener label y errores accesibles. No mover el token a un campo que se clone sin control.

### Modal 4 — Detalle laboratorio

**Recomendación:** `RECOMMENDED_WITH_PRECONDITIONS`.

Debe abrirse desde el listado, consumir el endpoint de detalle ya existente y conservar fecha, hora, título, descripción y descarga. Debe validar que el registro pertenece al paciente autorizado antes de exponer el enlace y mantener el riesgo documental/descarga abierto hasta prueba específica.

### Otros modales

- **Impresión/vista previa de receta:** `KEEP_EXISTING / RECOMMENDED_WITH_PRECONDITIONS`. Ya existe `#modalImprimirReceta`; no crear otro modal ni duplicar ID. Requiere validación documental separada.
- **Alta de receta independiente:** `NOT_RECOMMENDED` en esta pantalla. La receta está acoplada al guardado de Nota; copiar el modal de Receta puede generar registros o contratos duplicados.
- **COFEPRIS:** `NOT_RECOMMENDED / NOT_APPLICABLE`. No existe integración real en Nota subsecuente.

## 10. Reutilización de componentes

| Patrón | Reutilización recomendada |
|---|---|
| Shell, hero, paciente y botón de regreso | Sí, CSS/patrones institucionales; conservar URL dinámica. |
| Estado vacío y tarjeta de tabla Laboratorio | Sí, como patrón visual; no copiar endpoints sin adaptar referencia/CSRF. |
| Modal de Laboratorio | Parcial; reutilizar estructura visual y accesibilidad después de revisar IDs y listeners. |
| Modales de Receta | Sólo impresión/vista previa existente; no reutilizar alta independiente en Nota. |
| COFEPRIS | No aplica. |
| Simple-DataTables | Sí, una instancia por tabla y destrucción/reinicio controlados. |
| Quill | Sí, instancias únicas; no clonar nodos con el mismo ID. |

No se recomienda añadir otra biblioteca de modales. Debe elegirse un solo mecanismo compatible con el shell actual, con ciclo de vida y foco explícitos.

## 11. DataTables, eventos y JavaScript

### Confirmado en la vista actual

- Laboratorio se carga por `fetch`, se inserta y se inicializa Simple-DataTables con `fixedHeight:false`.
- Detalle de Nota se carga por `fetch`, reemplaza `#detalleNota`, ejecuta `feather.replace()` e inicializa la tabla de Laboratorio anidada con `fixedHeight:false`.
- Las filas del historial tienen eventos inline y soporte Enter/Space en el markup actual.
- Quill se inicializa en `DOMContentLoaded` para `snow1`, `snow2` y `snow3`.

### Riesgos que deben cubrir las pruebas de implementación

- Reemplazar HTML puede eliminar listeners añadidos externamente o duplicar inicializaciones.
- Refrescar una tabla sin destruir la instancia previa puede duplicar controles o eventos.
- Las filas generadas por Simple-DataTables deben seguir activando detalle después de buscar, ordenar y paginar.
- El contenido HTML del detalle no debe confiar en `innerHTML` sin sanitización/escape de los valores dinámicos.
- Un modal que se abre después de un `fetch` debe enfocar su título o primer control sólo después de completar el render.

## 12. Accesibilidad

### Estado actual

- Regreso es enlace semántico.
- Toggle es botón con nombre accesible.
- Filas de historial tienen rol, foco y teclado en el markup actual.
- `#mensaje` anuncia estado de forma polite.
- El ciclo de foco de `#modalImprimirReceta` y los modales externos no se verificó en navegador.

### Requisitos para nuevos modales

- `role="dialog"`, `aria-modal="true"`, `aria-labelledby` y estado oculto inicial.
- Foco inicial determinista y restauración al botón que abrió.
- Escape y botón X; no usar clic fuera como único cierre.
- Focus trap sin impedir scroll del contenido.
- Confirmación de cambios sin guardar para el modal de Nota y, según contrato, Laboratorio.
- Mensajes de validación con `role="alert"` o región anunciada.
- Botones de al menos 44 px y texto no dependiente sólo del color.

## 13. Responsive estático

El CSS objetivo tiene reglas mobile hasta `767px`: hero en columna, acciones a ancho disponible, tabla con layout fijo y campos de captura a ancho completo. El grid de vitales pasa a dos columnas desde `768px` y la composición Bootstrap cambia en `xl`.

Esto es una lectura de CSS, no una prueba visual. Debe probarse el diseño propuesto en 375, 430, 768, 1024, 1440 y 1920 px. En móvil, el modal de Nota necesita scroll interno, footer visible y cierre alcanzable; la página no debe quedar bloqueada por dos scrollbars.

## 14. Seguridad e integridad

Estados: `CONFIRMED` significa confirmado por lectura estática; `NOT_VERIFIED` significa que requiere runtime; `POTENTIAL` es un riesgo visible que no se explotó.

| Control | Estado | Evidencia / limitación |
|---|---|---|
| Sesión Doctor en GET principal | CONFIRMED estático | `AuthMiddleware('clinica')` y `authPermisos()`. |
| Paciente pertenece a clínica | CONFIRMED estático para la guarda | `assertPatientAccess()` compara clínica; no hubo HTTP. |
| GET detalle de Nota por paciente propietario | CONFIRMED estático en guard | `BusquedasController` obtiene propietario y llama `guardPatient`; no hubo runtime. |
| POST Nota con paciente autorizado | CONFIRMED parcial | Controlador llama `assertPatientAccess()` sobre el `idPaciente` recibido. |
| CSRF de POST Nota | NOT_VERIFIED / POTENTIAL | No se observó token/header equivalente en `AgregarNota()` ni validación específica en el flujo revisado. |
| Edición por paciente + referencia | POTENTIAL | Modelo edita por `idNota`/`idReceta`; la referencia llega del cliente y no se observa predicado conjunto en la actualización. |
| CSRF Laboratorio | CONFIRMED estático | Token capturado y header `X-M03-CSRF`; validación en controlador. |
| Autorización de Laboratorio por registro | CONFIRMED parcial | Búsqueda de detalle usa guarda del paciente propietario; falta prueba runtime. |
| Descarga de documento | NOT_VERIFIED | No se ejecutó descarga ni se cerró el análisis documental asociado. |
| Escape de HTML dinámico | POTENTIAL | `getNotaSubsecuente()` y `getLaboratorio()` concatenan datos en HTML; la vista usa `innerHTML`. |
| Consultas parametrizadas | POTENTIAL | Se observaron interpolaciones en consultas de referencia/listados y una consulta directa en la vista. |
| Idempotencia / doble envío | NOT_VERIFIED / POTENTIAL | No se ejecutaron POST; no se observó bloqueo de request en el cliente. |
| PDF/impresión | NOT_VERIFIED | El modal existe, pero no se ejecutó POST documental. |

Estos riesgos bloquean una implementación segura de los modales de escritura hasta que el propietario autorice un trabajo separado de seguridad. Esta auditoría no corrige ninguno.

## 15. Riesgos de regresión

1. Perder `referencia` al montar el modal o reconstruir `#main`.
2. Duplicar IDs de Quill, inputs, `#mensaje` o `#modalImprimirReceta`.
3. Romper el acoplamiento Nota–Receta al separar la tarjeta.
4. Ejecutar dos veces la inserción por doble clic o reintento.
5. Dejar una tabla anidada con altura fija o una instancia DataTable duplicada.
6. Exponer HTML clínico no escapado al pasar de tarjeta a modal.
7. Perder focus, Escape, restauración o cerrar con cambios no guardados.
8. Convertir una lectura de modal en una entrada de historial del navegador.
9. Sobrescribir estilos globales Doctor para resolver sólo esta pantalla.
10. Activar una descarga o impresión desde un detalle sin repetir autorización.

## 16. Matriz priorizada de implementación

### P0 — Bloqueos antes de mover formularios

| Hallazgo | Evidencia | Causa raíz | Solución propuesta | Archivos previstos | Dependencias | Aceptación |
|---|---|---|---|---|---|---|
| CSRF de Nota no verificable | POST JSON sin token visible | Flujo de Nota no muestra la misma protección que Laboratorio | Trabajo de seguridad separado; después probar con paciente sintético | Controlador, vista/servicio sólo con autorización | No mezclar con rediseño | POST válido protegido; token ausente/replay rechazado sin modificar datos. |
| Edición no acotada visiblemente por paciente/referencia | Modelo actualiza por IDs | El payload acepta `idNota`/`idReceta` del cliente | Revisión backend independiente | Modelo/controlador | Dos pacientes sintéticos | Manipular IDs no cambia registros ajenos. |
| HTML dinámico sin escape uniforme | Modelos concatenan HTML y vista usa `innerHTML` | Render de detalle no tiene allowlist única | Definir renderer seguro antes del modal | Modelos/vista | Datos sintéticos con caracteres especiales | Texto se muestra literal y no ejecuta markup. |
| Idempotencia no demostrada | POST y fetch no probados | No hay evidencia de lock/nonce/resultado repetido | Prueba y corrección independiente | Controlador/modelo/JS según resultado | Persistencia aislada | Doble activación no crea/edita dos veces. |

### P1 — Arquitectura visual y de interacción

| Hallazgo | Evidencia | Solución propuesta | Archivos previstos | Aceptación |
|---|---|---|---|---|
| Detalle permanente consume espacio | `#detalleNota` en columna 8/12 | Modal de detalle con fallback de estado vacío | Vista + CSS/JS scoped | Historial a ancho completo; detalle abre con mouse/teclado y no crea historial. |
| Captura comprimida | grid 8/4 y tres editores visibles | Modal XL o sección de captura a ancho completo; decidir con propietario | Vista + CSS/JS scoped | Todos los campos y payload idénticos; móvil usable. |
| Laboratorio lateral | tabla + alta + detalle en 4/12 | Listado ancho completo + modal alta/detalle | Vista + CSS/JS scoped | CSRF, referencia, PDF y recarga preservados. |
| Modales no tienen contrato común demostrado | Sólo impresión actual en target | Reutilizar un único patrón accesible | Vista/CSS/JS scoped | foco, Escape, X, aria y restauración verificados. |

### P2 — Refinamiento visual y responsive

| Hallazgo | Solución propuesta | Aceptación |
|---|---|---|
| Estados genéricos | Diferenciar sin registros, carga, error y autorización | Mensaje correcto para cada respuesta. |
| DataTables/eventos | Instancia única, teardown y delegación estable | Buscar/ordenar/paginar no rompe selección. |
| Densidad tipográfica | Reusar patrones de Laboratorio/Receta sin cambiar contenido | Encabezados, tablas y botones coherentes. |
| Responsive | Revisar seis viewports y scroll interno | Sin overflow horizontal ni footer inaccesible. |

## 17. Archivos previstos para una implementación posterior

Sólo después de aprobación y gates de seguridad:

- `app/views/clinica/pacientes-nota-subsecuente.php`
- `public/assets/css/app.css`, únicamente con reglas scoped a `doctor-patient-note-page`
- JavaScript de la vista, si se decide separar el inline script; no modificar librerías compartidas sin autorización
- Componentes de modal nuevos o existentes, sólo si se confirma que su alcance no afecta otras vistas

No se recomienda modificar rutas, modelos, controladores, esquemas o permisos como parte de una modernización exclusivamente visual. Los hallazgos de P0 requieren una autorización independiente.

## 18. Pruebas de aceptación futuras

### Pantalla y layout

- 1440, 1920, 1024, 768, 430 y 375 px.
- Sin overflow horizontal.
- Historial y laboratorios aprovechan el ancho disponible.
- Tarjetas no reservan altura artificial.
- Sidebar tiene un único estado activo.

### Nota

- Abrir/cerrar modal sin perder valores.
- Cambios sin guardar solicitan confirmación.
- Validar todos los campos actuales.
- Guardar una sola vez y esperar respuesta antes de redirigir.
- Confirmar Nota, receta y referencia persistidas en base sintética.
- Reingresar y recuperar valores.
- Acceso cruzado rechazado.

### Historial/detalle

- Selección con mouse, Enter y Space.
- Funciona después de búsqueda, ordenamiento y paginación.
- Detalle autorizado no ejecuta HTML de datos.
- Cierre restaura foco y no modifica historial del navegador.

### Laboratorio

- Listado por paciente + referencia.
- Alta con PDF válido, token CSRF, título y descripción.
- Rechazo de archivo inválido o mayor a 10 MiB.
- Detalle y descarga sólo con autorización.
- Error de red visible y reintento controlado.

### Documentación

- Impresión/PDF no se ejecutan durante esta auditoría; requieren prueba documental separada.
- Cada resultado debe registrarse como PASS, FAIL o NO VERIFICADO con evidencia.

## 19. Decisiones requeridas del propietario

1. Aprobar el ancho de pantalla y la estructura con historial/laboratorio a ancho completo.
2. Elegir entre modal XL para captura de Nota o sección de página a ancho completo.
3. Autorizar por separado la remediación de CSRF, autorización por registro/referencia, escape e idempotencia.
4. Definir si el detalle debe conservar enlace de edición y a qué ruta exacta.
5. Confirmar si la impresión de receta debe seguir abriéndose tras guardar o pasar a una acción explícita.
6. Confirmar el comportamiento de cierre con cambios sin guardar.

## 20. Validaciones realizadas en esta auditoría

- **PHP lint:** ejecutar sobre los archivos PHP relevantes; el resultado se registra en el bloque final y no implica ejecución HTTP.
- **JavaScript:** revisión de scripts externos e inline; sin navegador ni requests.
- **CSS:** revisión estática de reglas específicas y balance de llaves; sin estilos computados.
- **Rutas/formularios/eventos:** inventario estático incluido en este documento.
- **Seguridad:** lectura de guards, endpoints y payloads; no explotación.
- **Browser:** `NO VERIFICADO`; Codex no tuvo acceso al XAMPP local ni se obtuvieron capturas nuevas.
- **Datos:** no se consultó base, no se guardaron notas, no se subieron laboratorios y no se generaron recetas/PDF.

## 21. Informe YAML solicitado

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_UX_MODALS_AUDIT_03:
  STATUS: AUDIT_ONLY_READY_FOR_OWNER_REVIEW
  AUDIT_01_REVIEWED: PASS
  IMPLEMENTATION_02_REVIEWED: PASS
  INTEGRATION_MATRIX_REVIEWED: PASS
  MAIN_VIEW: app/views/clinica/pacientes-nota-subsecuente.php
  CONTROLLER: app/Controllers/ClinicaController.php::pacienteNotaSubsecuente
  CURRENT_LAYOUT: Hero, paciente/contexto, historial + detalle permanente 4/8, captura Nota/Receta + Laboratorio/cita 8/4
  CURRENT_MAX_WIDTH_CAUSE: CSS scoped min(100%, 1380px), shell padding/sidebar y grids Bootstrap 4/8 y 8/4
  AVAILABLE_WIDTH_USAGE: PARCIAL; confirmado estáticamente, rectángulos runtime NO VERIFICADOS
  PATIENT_HEADER: Hero Doctor + franja compacta con nombre y expediente; regreso dinámico al expediente
  CONSULTATION_CONTEXT: Tarjeta con fecha alta, fecha nacimiento, edad y sexo
  NOTE_HISTORY: #table1, Simple-DataTables, fecha/hora, selección mouse/teclado
  NOTE_DETAIL: #detalleNota, HTML dinámico por GET y relación receta/laboratorio
  NOTE_CREATE_FORM: Sin form HTML; AgregarNota() envía JSON con signos, Quill, receta y próxima cita
  LABORATORY_SECTION: Listado, detalle y alta PDF integrados; referencia + X-M03-CSRF
  OTHER_INTEGRATIONS: Receta asociada e impresión/vista previa; expediente como navegación
  COFEPRIS_NOT_PRESENT: CONFIRMED_STATIC
  FULL_WIDTH_LAYOUT_RECOMMENDED: YES, scoped to target view
  MAIN_PAGE_STRUCTURE: Hero, paciente/contexto, historial full-width, laboratorios full-width, acciones de alta
  REDUNDANT_PANELS: Detalle permanente; no son redundantes campos de captura ni receta asociada
  EMPTY_STATES: Específicos por recurso y separados de carga/error/autorización
  NOTE_CREATE_MODAL: RECOMMENDED_WITH_PRECONDITIONS; modal XL o alternativa full-width
  NOTE_DETAIL_MODAL: RECOMMENDED_WITH_PRECONDITIONS
  LABORATORY_CREATE_MODAL: RECOMMENDED_WITH_PRECONDITIONS
  LABORATORY_DETAIL_MODAL: RECOMMENDED_WITH_PRECONDITIONS
  OTHER_MODALS: Impresión existente KEEP_EXISTING_WITH_PRECONDITIONS; COFEPRIS NOT_APPLICABLE
  MODAL_REUSE_FEASIBILITY: Parcial; patrón visual reutilizable, contratos y listeners requieren adaptación
  FORMS_MOVABLE_WITHOUT_BACKEND_CHANGES: Sí para el posicionamiento; no para resolver gates P0
  FORM_CONTRACTS_PRESERVED: REQUIRED; no probado runtime
  REFERENCE_RELATION_PRESERVED: REQUIRED; paciente + referencia son contrato clínico
  DATATABLE_EVENTS: Inline/teclado actuales; teardown/delegación requerido tras renders dinámicos
  KEYBOARD_ACCESS: Historial con role/tabindex/Enter/Space; modales propuestos aún no verificados
  MODAL_FOCUS_MANAGEMENT: Requerido; no verificado para los nuevos modales
  UNSAVED_CHANGES_STRATEGY: Confirmar antes de X/Cancelar/Escape/clic fuera/navegación
  BROWSER_HISTORY_STRATEGY: Modal interno sin history.push/back; rutas directas existentes se conservan
  CSRF_STATUS: Nota NOT_VERIFIED/POTENTIAL; Laboratorio CONFIRMED_STATIC
  PATIENT_AUTHORIZATION: CONFIRMED_STATIC_FOR_GET_AND_GUARDS; runtime NO VERIFICADO
  NOTE_RECORD_AUTHORIZATION: POTENTIAL; edición por IDs sin predicado visible conjunto
  REFERENCE_AUTHORIZATION: POTENTIAL; GET resuelve por paciente+referencia, POST/modelo requiere revisión
  DYNAMIC_HTML_ESCAPE: POTENTIAL; concatenación de modelos + innerHTML
  IDEMPOTENCY_STATUS: NOT_VERIFIED/POTENTIAL
  SECURITY_BLOCKERS: CSRF Nota, autorización registro/referencia, escape HTML, idempotencia y descarga/PDF runtime
  RESPONSIVE_FINDINGS: CSS mobile existe; seis viewports NO VERIFICADOS en navegador
  BROWSER_VALIDATION: NO VERIFICADO
  PHP_LINT: PASS_STATIC_CHECK
  JS_SYNTAX: PASS_STATIC_CHECK
  CSS_STATIC_REVIEW: PASS_STATIC_REVIEW
  DIFF_CHECK: PASS_FOR_NEW_AUDIT_FILES; historical_worktree_changes_preserved
  PRIORITY_P0_FINDINGS: CSRF Nota; autorización por registro/referencia; escape HTML; idempotencia
  PRIORITY_P1_FINDINGS: Ancho/grid; detalle permanente; captura permanente; Laboratorio lateral; ciclo de modales
  PRIORITY_P2_FINDINGS: Estados vacíos; DataTables; tipografía/espaciado; responsive
  RECOMMENDED_IMPLEMENTATION_STAGES: 0 owner decisions; 1 security gates; 2 modal/detail prototype; 3 layout/lab; 4 responsive/accessibility; 5 synthetic runtime
  FILES_EXPECTED_TO_CHANGE: Vista objetivo; CSS/JS scoped; componentes modales sólo con autorización; backend sólo en remediación separada
  OWNER_APPROVAL_REQUIRED: YES
  AUDIT_DOCUMENT: docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_UX_MODALS_AUDIT_03.md
  MODALS_MATRIX: docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_MODALS_MATRIX_03.csv
  APPLICATION_CODE_MODIFIED: NO
  DATABASE_MODIFIED: NO
  CLINICAL_RECORDS_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  READY_FOR_OWNER_REVIEW: YES
  NEXT_RECOMMENDED_ACTION: Aprobar estructura y resolver gates P0 en una intervención separada antes de implementar modales
```

