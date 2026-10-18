# TH-DOCTOR-LABORATORY-UX-IMPLEMENTATION-02

## Adecuación visual, funcional y modal — Laboratorio Doctor

**Proyecto:** Clínica del Dolor  
**Vista:** `app/views/clinica/pacientes-laboratorio.php`  
**Ruta:** `/clinica/laboratorio/paciente/{idPaciente}`  
**Fecha:** 2026-10-15  
**Estado:** IMPLEMENTACIÓN VISUAL PARCIAL — MODAL BLOQUEADO

## Alcance ejecutado

Se revisó `TH_DOCTOR_LABORATORY_UX_AUDIT_01` y se modificó únicamente la vista de Laboratorio y su CSS scoped. No se modificaron rutas, controladores, modelos, middleware, base de datos ni otros módulos.

### Cambios realizados

- Se conservó el shell Doctor, el encabezado institucional, el regreso al expediente y el toggle compacto con icono `columns`.
- Se cambió el `lang` de la vista a `es`, coherente con el contenido de la pantalla.
- Se sustituyó la tarjeta extensa de información del paciente por una franja compacta con icono, nombre y expediente dinámico.
- Se reutilizó la jerarquía `dashboard-eyebrow` con `REGISTROS DISPONIBLES` y un único título `Laboratorios`.
- Se mantuvo el contador dinámico derivado del listado actual.
- La tarjeta de laboratorios ocupa ahora el ancho completo disponible antes de las tarjetas de detalle y alta.
- Se eliminó `fixedHeight: true` y la propiedad `searchable` duplicada de Simple-DataTables. La búsqueda, ordenamiento y paginación se conservan cuando existen registros.
- Se añadió un estado vacío explícito: `No hay estudios de laboratorio registrados.`
- Las filas conservan su acción de abrir detalle y ahora incluyen `role="button"`, `tabindex="0"`, nombre accesible, foco visible y activación con Enter o espacio.
- Se escapó el título mostrado en la tabla y en el nombre accesible de la fila.
- Se normalizaron los encabezados a `Detalle del laboratorio` y `Nuevo laboratorio`, sin introducir una tarjeta o título redundante.
- Se mejoraron etiquetas, agrupación de campos, mensajes de error y prevención de doble envío en el flujo de alta existente.
- Se retiró la opción Quill `height: '500px'`, que no es una opción estándar de Quill y podía favorecer alturas artificiales; la altura queda gobernada por el CSS scoped existente.
- Se mantuvo el endpoint y el `FormData` existentes; no se creó una segunda persistencia.

## Modal de “Nuevo laboratorio”

### Resultado de la compuerta

El modal **no se implementó**.

El flujo actual de alta reutiliza el endpoint existente, pero la inspección estática encontró controles insuficientes para habilitar un flujo nuevo de forma segura:

- No se observó protección CSRF en la vista ni en `pacienteInsertLaboratorio()`.
- No se observó una allowlist server-side de tipos MIME/extensiones ni límite de tamaño en el flujo inspeccionado.
- `LaboratorioModel::insertArchivo()` usa el nombre original como destino basado en `basename()`, con riesgo de colisión o sobrescritura.
- El directorio de almacenamiento se crea con permisos `0777`.
- La autorización de descarga no fue comprobada en esta actividad.

Conforme al alcance autorizado, no se introdujo un segundo flujo inseguro ni se modificó unilateralmente la seguridad del backend. El alta existente permanece en página y mantiene sus campos, endpoint y contrato de respuesta.

## Seguridad y preservación

Controles positivos revisados estáticamente:

- La página usa `AuthMiddleware('clinica')->assertPatientAccess()`.
- El POST vuelve a comprobar autorización sobre el `idPaciente`.
- La consulta del detalle deriva el paciente desde el registro antes de aplicar la guardia.

Riesgos que permanecen abiertos y no fueron corregidos en esta intervención:

- CSRF.
- Validación server-side de archivos y tamaño.
- Colisiones/sobrescritura de nombres.
- Permisos del directorio de almacenamiento.
- Autorización del endpoint de descarga.
- Escape HTML en el detalle generado por `LaboratorioModel`.
- Consulta concatenada de `mostrarTablaLaboratorio()`.

No se ejecutaron POST, uploads, descargas, SQL, PDO, HTTP ni pruebas mutantes. No se accedió a `bd_clinica`.

## Validación técnica

```yaml
TH_DOCTOR_LABORATORY_UX_IMPLEMENTATION_02:
  STATUS: PARTIAL
  AUDIT_01_REVIEWED: PASS
  TARGET_VIEW_IDENTIFIED: PASS

  INSTITUTIONAL_COLORS_PRESERVED: PASS
  TYPOGRAPHY_REFERENCE_REUSED: PASS
  REDUNDANT_TITLES_REMOVED: PASS

  COMPACT_PATIENT_HEADER: PASS_STATIC
  FULL_WIDTH_LABORATORY_CARD: PASS_STATIC
  DATATABLE_HEIGHT_REMEDIATED: PASS_STATIC
  EMPTY_STATE_IMPROVED: PASS_STATIC
  KEYBOARD_ACCESSIBILITY: PASS_STATIC

  CREATE_FLOW_REVIEWED: PASS_STATIC
  EXISTING_FORM_REUSED: PASS
  EXISTING_ENDPOINT_REUSED: PASS

  CSRF_GATE: BLOCKED_NOT_PRESENT
  RESOURCE_AUTHORIZATION_GATE: PASS_STATIC
  FILE_UPLOAD_VALIDATION_GATE: BLOCKED_NOT_VERIFIED

  MODAL_IMPLEMENTED: NO
  MODAL_BLOCKERS: CSRF, server-side file validation, size policy, overwrite policy, download authorization
  MODAL_OPEN_CLOSE: NOT_APPLICABLE
  MODAL_VALIDATION: NOT_APPLICABLE
  MODAL_UPLOAD_COMPATIBILITY: NOT_VERIFIED
  MODAL_SAVE: NOT_EXECUTED
  LIST_REFRESH_AFTER_SAVE: NOT_EXECUTED

  RESPONSIVE_375: NOT_VERIFIED
  RESPONSIVE_430: NOT_VERIFIED
  RESPONSIVE_768: NOT_VERIFIED
  RESPONSIVE_1024: NOT_VERIFIED
  RESPONSIVE_1440: NOT_VERIFIED

  PHP_LINT: PASS
  JS_SYNTAX: PASS
  CSS_STATIC_REVIEW: PASS_STATIC
  DIFF_CHECK: PASS_NO_WHITESPACE_ERRORS

  FILES_MODIFIED:
    - app/views/clinica/pacientes-laboratorio.php
    - public/assets/css/app.css
  DOCUMENT_CREATED: docs/audits/TH_DOCTOR_LABORATORY_UX_IMPLEMENTATION_02.md

  ROUTES_MODIFIED: NO
  DATABASE_MODIFIED: NO
  CLINICAL_WRITE_LOGIC_MODIFIED: NO
  AUTHORIZATION_MODIFIED: NO
  ENV_MODIFIED: NO

  WORKTREE_PRESERVED: YES
  COMMITS: NONE
  DEPLOYMENTS: NONE

  BROWSER_VALIDATION: NOT_EXECUTED
  RUNTIME_VALIDATION: NOT_EXECUTED
  PRODUCTION_READY: NO
```

## Comprobaciones realizadas

- `php -l app/views/clinica/pacientes-laboratorio.php`: sin errores de sintaxis.
- Parseo de los dos bloques JavaScript inline con Node.js: correcto.
- `git diff --check`: sin errores de whitespace nuevos. Git mostró avisos históricos de conversión LF/CRLF en otros archivos del worktree.
- Revisión estática de rutas, IDs, eventos, DataTables, Quill, autorización y contrato del endpoint: realizada.

## Procedimiento manual pendiente

En el Windows con XAMPP activo y una sesión autorizada, sin crear ni subir datos durante esta comprobación:

1. Abrir `/clinica/laboratorio/paciente/{idPaciente}` con un paciente sintético autorizado.
2. Verificar que la franja sólo muestre icono, nombre y expediente.
3. Probar la vista con cero y con varios registros ya existentes.
4. Comprobar que la tarjeta de laboratorios ocupa el ancho esperado y que no hay scrollbar vertical artificial.
5. Usar Tab para llegar a una fila y activar el detalle con Enter y espacio.
6. Probar 375, 430, 768, 1024 y 1440 px y comprobar que sólo la tabla, si es necesario, tenga desplazamiento horizontal.
7. Revisar consola y Network; no ejecutar el botón de alta ni seleccionar archivos hasta que se resuelvan los bloqueos de seguridad documentados.

## Archivos y cambios preexistentes

Se preservaron las modificaciones locales que ya existían en el worktree. No se hizo staging, commit, push ni despliegue.
