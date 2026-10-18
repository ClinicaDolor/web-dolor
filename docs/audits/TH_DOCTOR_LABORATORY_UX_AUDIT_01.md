# TH-DOCTOR-LABORATORY-UX-AUDIT-01

## Auditoría focalizada de diseño y experiencia de usuario — Laboratorio Doctor

**Proyecto:** Clínica del Dolor  
**Área:** Doctor / expediente del paciente  
**Alcance:** inspección estática del módulo Laboratorio  
**Fecha:** 2026-10-15  
**Estado:** AUDITORÍA COMPLETADA — SIN IMPLEMENTACIÓN

> Esta auditoría no ejecutó SQL, PDO, HTTP, cargas de archivos ni operaciones clínicas. No se modificó la aplicación. Los hallazgos de seguridad y funcionalidad son estáticos y no equivalen a una prueba de explotación.

## 1. Identificación real

### Ruta principal

- `GET /clinica/laboratorio/paciente/{idPaciente}`
- Controlador: `ClinicaController::pacienteLaboratorio($idPaciente)`.
- Vista: `app/views/clinica/pacientes-laboratorio.php`.
- La vista construye el regreso contextual a `/clinica/paciente/{idPaciente}`.

### Rutas relacionadas

Definidas en `app/Config/routes.php`:

- `GET /laboratorio/{idLaboratorio}` → `LaboratorioController::laboratorio`.
- `GET /laboratorio/paciente/{idPaciente}` → `ClinicaController::pacienteLaboratorio`.
- `POST /laboratorio/insert-laboratorio` → `ClinicaController::pacienteInsertLaboratorio`.
- `GET /buscar/laboratorio/{idLaboratorio}` → `BusquedasController::buscarLaboratorio`.
- `GET /buscar/tabla-laboratorio/{idPaciente}/{referencia}` → `BusquedasController::tableLaboratorio`.

### Componentes inspeccionados

- `app/views/clinica/pacientes-laboratorio.php`.
- `app/Controllers/ClinicaController.php`.
- `app/Controllers/LaboratorioController.php`.
- `app/Controllers/BusquedasController.php`.
- `app/Models/LaboratorioModel.php`.
- `app/Middleware/AuthMiddleware.php`.
- `public/assets/css/app.css`.
- `public/assets/js/loader.js`.
- Dependencias de Bootstrap, Feather, Simple-DataTables, Quill, jQuery y Perfect Scrollbar.

## 2. Propósito y flujo actual

La pantalla presenta información básica del paciente, el listado de archivos de laboratorio, el detalle del registro seleccionado y un formulario en línea para cargar un nuevo laboratorio.

El flujo observado es:

1. `ClinicaController::pacienteLaboratorio()` autentica el área `clinica` y ejecuta `assertPatientAccess()` antes de renderizar.
2. La vista consulta el listado de `laboratorio` por `id_paciente` y ordena por `fecha_hora DESC`.
3. Simple-DataTables agrega búsqueda, ordenamiento y paginación al listado.
4. Cada fila llama a `DetalleLaboratorio(id)` mediante `onclick`.
5. `DetalleLaboratorio()` consulta `/buscar/laboratorio/{idLaboratorio}` e inserta el HTML recibido en `#detalleReceta`.
6. El formulario captura título, archivo y descripción Quill, y envía `FormData` a `/clinica/laboratorio/insert-laboratorio`.
7. El controlador valida la sesión, verifica acceso al paciente y delega la carga a `LaboratorioModel::insertArchivo()`.
8. Tras una respuesta exitosa, la vista recarga la página.

## 3. Evaluación visual y UX

### 3.1 Encabezado institucional

**Estado:** parcialmente consistente.

Fortalezas:

- Utiliza el shell Doctor (`app-shell-doctor`, sidebar y topbar compartidos).
- Presenta título, descripción contextual con el nombre del paciente y regreso al expediente.
- Conserva el control de distribución `toggleSize()` con `id="toggleButton"`, icono `columns`, `aria-label` y `title`.
- El CSS scoped de la página contempla adaptación móvil.

Hallazgos:

- El eyebrow `Control clínico` no sigue exactamente la convención visual de los encabezados institucionales que utilizan texto en mayúsculas y mayor tracking.
- El bloque no emplea el mismo patrón semántico de eyebrow + título destacado que la referencia de `app/views/clinica/pacientes.php` (`REGISTROS DISPONIBLES` / `Lista de pacientes`).
- La acción de distribución debe conservarse como icono compacto y accesible; no se observó una necesidad de introducir texto visible.

**Prioridad UX:** P3, salvo que el equipo defina este encabezado como componente institucional obligatorio.

### 3.2 Información del paciente

La tarjeta `Información del paciente` muestra nombre, fecha de alta, fecha de nacimiento, edad y sexo. La información aporta contexto y está visualmente separada del listado.

Hallazgos:

- Es una tarjeta de altura natural, no una tarjeta artificialmente estirada.
- Puede compactarse en futuras iteraciones para reducir repetición respecto al hero, pero no existe evidencia suficiente para eliminar campos en esta auditoría.
- Las etiquetas y valores tienen una jerarquía clara en el CSS específico de la pantalla.

**Prioridad UX:** P3, mejora opcional.

### 3.3 Listado de laboratorios

La tarjeta se titula `Archivos` y presenta las columnas `#`, `Fecha y Hora` y `Titulo`.

Hallazgos confirmados:

- `fixedHeight: true` en Simple-DataTables puede producir una altura o scrollbar artificial cuando hay pocos registros o ningún registro.
- La propiedad `searchable: true` aparece duplicada en la configuración inline.
- El estado vacío no tiene un mensaje específico dentro de la tabla; el `tbody` puede quedar vacío sin una explicación contextual.
- Las filas dependen de `onclick` y no muestran en el HTML inspeccionado un rol interactivo, `tabindex` ni una ruta alternativa para teclado.
- `Archivos` es un título genérico; `Laboratorios` o `Resultados de laboratorio` comunicaría mejor el contenido, sujeto a aprobación de terminología.
- El CSS ya contempla `overflow-x: auto` y ajustes móviles; no se debe declarar ausencia de overflow sin una ejecución real del navegador.

**Prioridades UX:**

- P2: eliminar la interacción exclusivamente de ratón y hacer seleccionables las filas por teclado.
- P2: revisar `fixedHeight` y el estado vacío para evitar scroll vertical artificial.
- P3: normalizar título y mensajes de estado.

### 3.4 Detalle del laboratorio

El panel `Detalle del Laboratorio` muestra inicialmente el registro más reciente mediante `ultimoLaboratorio()` y posteriormente reemplaza `#detalleReceta` con HTML obtenido por AJAX.

Hallazgos:

- La existencia de un detalle inicial es útil, pero puede no corresponder a una selección explícita del usuario cuando existen varios archivos.
- El estado sin información existe como texto, pero no como un estado vacío institucional con icono, espaciado y jerarquía equivalentes al resto del Doctor.
- El contenido recibido se inserta con `innerHTML`; cualquier modernización futura debe conservar el contrato, pero también asegurar escape de valores antes de generar HTML.

**Prioridad UX:** P3 para el estado vacío; P1/P2 de seguridad para el escape de contenido, tratado en la sección 6.

### 3.5 Formulario de alta

El formulario se presenta como tarjeta en línea con título `Nueva Archivo`, título de archivo, selector de archivo, editor Quill y botón `Agregar Laboratorio`.

Hallazgos:

- `Nueva Archivo` presenta una inconsistencia gramatical y no comunica con precisión si se crea un archivo o un registro de laboratorio.
- El formulario ocupa una tarjeta completa y queda permanentemente visible; un modal podría reducir el peso visual inicial, pero el cambio no es trivial por el selector de archivos, Quill, foco, validación y errores de carga.
- La validación actual de archivo y descripción se realiza principalmente en cliente. La respuesta de error se escribe en `#mensaje`.
- El botón de alta tiene un tamaño táctil adecuado según el CSS scoped observado.
- No se observó un estado de carga o deshabilitación que prevenga doble envío durante la petición.

**Prioridades UX:**

- P2: prevenir doble envío y comunicar estado de carga, si se autoriza una corrección funcional de interacción.
- P3: corregir la etiqueta de la tarjeta y mejorar la presentación de errores.
- P3: evaluar modal sólo con un contrato explícito de foco, Escape, archivo, Quill y recuperación de errores.

### 3.6 Responsive

El CSS scoped contempla breakpoints para escritorio y móvil: apila el hero, permite que las acciones pasen a otra línea, reduce padding de tarjetas y adapta la tabla.

No se ejecutó navegador ni se obtuvieron capturas en 1440, 1280, 1024, 768, 430 o 375 px dentro de esta auditoría. Por ello:

- `RESPONSIVE_STATIC_REVIEW: PASS`.
- `RESPONSIVE_RENDERED_VALIDATION: NO VERIFICADO`.
- `OVERFLOW_HORIZONTAL_RUNTIME: NO VERIFICADO`.

## 4. Viabilidad de un modal para “Nuevo laboratorio”

### Resultado

**Viable en principio, no listo para implementación inmediata.**

No se encontró un modal Doctor específico reutilizable para alta de laboratorio. Sí existen reglas genéricas de modal y componentes de modales en otros módulos, pero no se confirmó un contrato común que pueda copiarse sin revisión.

El formulario actual podría trasladarse a un modal manteniendo:

- `FormData` multipart.
- Campos `Titulo`, `Archivo` y descripción Quill.
- Endpoint `/laboratorio/insert-laboratorio`.
- Mensajes de validación.
- Recarga o actualización del listado después de éxito.

Riesgos de la migración:

- Gestión del foco al abrir, cerrar y volver al botón `Nuevo`.
- Cierre con Escape sin perder una carga seleccionada o texto no guardado.
- Altura del editor Quill y scroll interno en móvil.
- Errores de upload y respuesta JSON sin dejar el formulario en un estado ambiguo.
- Prevención de dobles envíos.
- Compatibilidad con Bootstrap/JS ya cargados y prevención de scripts duplicados.
- Persistencia del estado de la tabla y refresco del detalle.

Recomendación: diseñar primero un contrato de interacción y aceptación; después implementar un modal scoped reutilizando el patrón institucional que se elija. No se recomienda introducirlo como ajuste únicamente visual sin resolver el flujo de carga.

## 5. Integridad del acceso y autorización observada

Hallazgos positivos estáticos:

- `ClinicaController::pacienteLaboratorio()` autentica la vista `clinica` y llama a `assertPatientAccess($idPaciente, $auth)` antes de renderizar.
- `pacienteInsertLaboratorio()` vuelve a comprobar la autorización del `idPaciente` recibido antes de delegar la escritura.
- `LaboratorioController::laboratorio()` obtiene el paciente desde el registro de laboratorio y luego valida el acceso.
- `BusquedasController::buscarLaboratorio()` obtiene el `idPaciente` del registro antes de llamar a `guardPatient()`.
- La carga utiliza consultas preparadas para el alta y para las consultas por identificador en `LaboratorioModel`.

Limitación: estas observaciones son estáticas. No se ejecutaron GET, AJAX, POST ni pruebas cruzadas con dos pacientes.

## 6. Hallazgos funcionales y de seguridad separados del diseño

Estos puntos no se corrigen en esta auditoría:

| ID | Hallazgo estático | Impacto | Prioridad | Estado |
|---|---|---|---|---|
| LAB-SEC-01 | No se observó token CSRF en la vista ni en `pacienteInsertLaboratorio()`. | Un POST autenticado podría quedar expuesto a solicitudes forjadas. | P1 | Requiere remediación y prueba específica |
| LAB-SEC-02 | No se observó allowlist de MIME/extensión ni límite de tamaño del archivo en el flujo inspeccionado. | Riesgo de carga de archivos no permitidos o excesivos. | P1/P2 | Pendiente |
| LAB-SEC-03 | `insertArchivo()` usa `basename($fileName)` como destino. Un nombre original repetido puede sobrescribir el archivo existente antes del INSERT. | Pérdida o sustitución de documento. | P1/P2 | Pendiente |
| LAB-SEC-04 | El directorio se crea con permisos `0777`. | Permisos excesivos según el entorno de despliegue. | P2 | Pendiente |
| LAB-SEC-05 | El enlace de descarga `/descargar/{ruta}/{nombre}` se genera desde el modelo. No se verificó el controlador de descarga ni su autorización dentro de esta auditoría. | Riesgo de acceso directo a documentos. | P1 | NO VERIFICADO |
| LAB-SEC-06 | `getLaboratorio()` y `mostrarTablaLaboratorio()` interpolan valores de BD en HTML sin escape visible. | Riesgo XSS almacenado si un valor controlable llega a título o descripción. | P1/P2 | Pendiente |
| LAB-SEC-07 | `mostrarTablaLaboratorio()` construye parte del filtro SQL por concatenación. | Riesgo de inyección/robustez, aunque existe guardia de paciente en el controlador. | P1/P2 | Pendiente |
| LAB-SEC-08 | La vista consulta directamente `laboratorio` concatenando el paciente recibido por el controlador. | Acoplamiento y validación incompleta en la capa de presentación. | P2/P3 | Pendiente |

No se ejecutaron cargas, descargas, POST, explotación, consultas SQL ni pruebas con registros reales. Estos riesgos no deben interpretarse como demostración de explotación.

## 7. Contratos que deben preservarse en una implementación futura

- Rutas y parámetros existentes.
- `assertPatientAccess()` y `guardPatient()`.
- `idPaciente` validado en servidor; no confiar sólo en el cliente.
- `FormData` multipart con archivo, título, descripción y referencia.
- Respuesta JSON del endpoint de inserción.
- `DetalleLaboratorio(idLaboratorio)` y el contrato HTML del detalle.
- `#table1`, `#detalleReceta`, `#Titulo`, `#Archivo`, `#snow` y `#mensaje` mientras no exista una migración explícita.
- `toggleSize()` e identidad del sidebar Doctor.
- Búsqueda, ordenamiento y paginación de Simple-DataTables.

## 8. Recomendaciones priorizadas

### P0/P1 — seguridad y trazabilidad, fuera de esta auditoría visual

1. Añadir protección CSRF al POST.
2. Definir allowlist de extensiones/MIME, límite de tamaño y política de almacenamiento segura.
3. Evitar colisiones de nombres y revisar permisos del directorio.
4. Revisar autorización del endpoint de descarga.
5. Escapar todo contenido generado en HTML y parametrizar filtros SQL.

### P2 — experiencia y accesibilidad

1. Hacer las filas de laboratorio operables con teclado, foco visible y nombre accesible.
2. Reemplazar o justificar `fixedHeight: true`; mostrar un estado vacío explícito sin scrollbar artificial.
3. Prevenir doble envío del formulario y comunicar carga/error.
4. Verificar el estado de detalle cuando no hay selección o no hay registros.

### P3 — consistencia visual

1. Homologar eyebrow, título y descripción con el patrón Doctor aprobado.
2. Revisar los nombres `Archivos` y `Nueva Archivo` con terminología del producto.
3. Compactar la tarjeta de paciente sólo si la referencia institucional y la lectura en móvil lo justifican.
4. Evaluar un modal de alta después de resolver el contrato funcional y de accesibilidad.

## 9. Criterios de aceptación para una futura corrección UX

- La vista conserva ruta, permisos, IDs, eventos, AJAX y persistencia.
- El encabezado mantiene regreso contextual y distribución accesible.
- Las filas pueden abrir detalle con ratón y teclado.
- El estado vacío es explícito, legible y no genera altura/scroll artificial.
- La tabla funciona con cero, uno y varios registros.
- El formulario conserva archivo, título, descripción, mensajes y respuesta JSON.
- Una sola pulsación no genera doble POST.
- El modal, si se implementa, conserva foco, Escape, cierre seguro y errores de carga.
- Se validan 1440×900, 1280×720, 1024×768, 768×1024, 430×932 y 375×667 en navegador real.
- Se ejecutan pruebas de autorización propia y cruzada sólo con datos sintéticos autorizados.

## 10. Validación de esta auditoría

```yaml
TH_DOCTOR_LABORATORY_UX_AUDIT_01:
  STATUS: COMPLETED_STATIC_AUDIT
  MODULE_IDENTIFIED: PASS
  ROUTES_AND_DEPENDENCIES_INSPECTED: PASS
  VIEW_INSPECTED: PASS
  CONTROLLER_INSPECTED: PASS
  MODEL_INSPECTED: PASS
  SHARED_CSS_INSPECTED: PASS
  AUTHORIZATION_STATIC_REVIEW: PASS
  VISUAL_BROWSER_VALIDATION: NOT_VERIFIED
  RESPONSIVE_RENDERED_VALIDATION: NOT_VERIFIED
  DATABASE_ACCESSED: NO
  SQL_OR_PDO_EXECUTED: NO
  HTTP_EXECUTED: NO
  FILES_UPLOADED_OR_DOWNLOADED: NO
  APPLICATION_FILES_MODIFIED: NO
  AUDIT_FILE_CREATED: docs/audits/TH_DOCTOR_LABORATORY_UX_AUDIT_01.md
  COMMITS: NONE
  DEPLOYMENTS: NONE
  PRODUCTION_READY: NO
```

## 11. Conclusión

El módulo Laboratorio ya utiliza el shell Doctor y cuenta con una base visual responsive, pero necesita una revisión específica de accesibilidad de filas, altura de Simple-DataTables, estado vacío y jerarquía de tarjetas para alcanzar paridad institucional.

La incorporación de un modal para `Nuevo laboratorio` es factible, pero debe tratarse como una mejora UX con validación funcional y de accesibilidad, no como un simple cambio de maquetación. Los riesgos de CSRF, carga/descarga de archivos, escape HTML y consultas concatenadas permanecen abiertos y separados del alcance visual.

**No se realizó ninguna implementación en la aplicación.**
