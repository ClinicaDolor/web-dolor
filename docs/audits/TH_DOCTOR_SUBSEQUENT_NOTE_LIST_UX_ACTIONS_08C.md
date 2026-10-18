# TH-DOCTOR-SUBSEQUENT-NOTE-LIST-UX-ACTIONS-08C

## Alcance

Corrección focalizada del listado de Nota subsecuente. Se retiró la tarjeta
**Contexto de la consulta** únicamente de esta vista y se sustituyó **Ver
detalle** por un menú de acciones con **Editar** y **Detalle**.

La vista independiente de creación, el guardado de Phase 07, las recetas
opcionales, el laboratorio, la próxima cita, las rutas existentes y los
controles de autorización se conservaron.

## Cambios aplicados

### Contexto de la consulta

Se eliminó del HTML del listado la tarjeta con Fecha Alta, Fecha Nacimiento,
Edad y Sexo. La franja compacta del paciente, su expediente y la referencia
clínica permanecen. El controlador conserva los datos de contexto porque la
misma preparación se reutiliza en la vista independiente de creación y en la
edición.

### Menú de acciones

La columna ahora se llama **ACCIONES**. Cada fila contiene un único control
**Acciones** con exactamente:

1. **Editar**
2. **Detalle**

El menú es HTML seguro, usa URLs generadas server-side con el identificador de
la nota y su referencia, y se posiciona como `position: fixed` después de
medir el botón. Así evita quedar recortado por el contenedor horizontal de
Simple-DataTables.

El comportamiento se implementa con un solo listener delegado en el documento.
Se soportan clic fuera, Escape, flechas arriba/abajo, foco visible y
navegación nativa mediante teclado. El listener no depende de que Simple-
DataTables conserve los nodos de las filas, por lo que sigue funcionando tras
buscar, ordenar o paginar.

### Detalle

**Detalle** conserva la ruta existente:

`/clinica/nota-subsecuente/{idNota}`

El controlador de detalle autentica la sesión Doctor, carga el registro por
identificador y ejecuta `assertPatientAccess()` antes de renderizarlo. No se
modifican registros.

### Edición

**Editar** utiliza la ruta nueva:

`/clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}/editar/{idNota}`

La acción no reutiliza la creación como un alta nueva. El controlador:

- autentica la sesión Doctor;
- verifica el paciente con `assertPatientAccess()`;
- carga la nota únicamente con `id`, `id_paciente` y `codigo_referencia`;
- carga la receta sólo con la misma pareja paciente/referencia;
- reutiliza la vista y partial del editor independiente;
- conserva `idNota` e `idReceta` en el contexto del formulario.

El POST existente distingue `idNota = 0` para alta y `idNota > 0` para
actualización. La actualización conserva las condiciones de paciente y
referencia, CSRF, consultas preparadas y la transacción existente. La receta
asociada se actualiza sólo si el selector explícito permanece activo; si el
selector se desactiva, no se crea ni modifica una receta automáticamente.

No se implementaron estados de firma o bloqueo porque no existe un contrato
separado verificable en el código inspeccionado. La autorización de registro y
la pertenencia a paciente/referencia sí quedan verificadas estáticamente.

## Simple-DataTables y estado de la tabla

La tabla mantiene búsqueda, ordenamiento, paginación y `fixedHeight: false`.
El listener del dropdown es delegado, no se duplica al reconstruir filas y no
se propaga a una acción de fila. El estado vacío y la etiqueta de no
coincidencias de la fase 08B permanecen.

## Validación

Se ejecutaron lint PHP, sintaxis JavaScript inline, los contratos focalizados
de Phase 07, seguridad, separación de vistas, referencia y el contrato 08C.
No se ejecutaron HTTP, navegador, SQL, PDO ni escrituras porque XAMPP no está
disponible desde el entorno Codex.

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_LIST_UX_ACTIONS_08C:
  STATUS: IMPLEMENTED_STATIC_VALIDATION_PENDING_RUNTIME
  TARGET_ROUTE: /clinica/nota-subsecuente/paciente/310/referencia/6ad3f95768c6c4.34892158
  CONSULTATION_CONTEXT_CARD_REMOVED: PASS_STATIC
  PATIENT_IDENTITY_PRESERVED: PASS_STATIC
  HISTORY_TABLE_PRESERVED: PASS_STATIC
  NEW_NOTE_BUTTON_INSIDE_CARD: PASS_STATIC
  NEW_NOTE_BUTTON_ROUTE_PRESERVED: PASS_STATIC
  ACTION_DROPDOWN_IMPLEMENTED: PASS_STATIC
  ACTION_DROPDOWN_ITEMS: Editar, Detalle
  ACTION_DROPDOWN_STYLING: PASS_STATIC
  ACTION_DROPDOWN_KEYBOARD: PASS_STATIC
  ACTION_DROPDOWN_RESPONSIVE: PASS_STATIC
  DETAIL_ACTION_FUNCTIONAL: PASS_STATIC
  DETAIL_ROUTE_PRESERVED: PASS_STATIC
  DETAIL_RECORD_AUTHORIZATION: PASS_STATIC
  EDIT_ACTION_FUNCTIONAL: PASS_STATIC_ROUTE_AND_VIEW
  EDIT_ROUTE: /clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}/editar/{idNota}
  EDIT_VIEW: pacientes-nota-subsecuente-nueva.php IN EDIT MODE
  EDIT_PERMISSION_CHECK: PASS_STATIC
  EDIT_RECORD_OWNERSHIP: PASS_STATIC
  EDIT_CSRF: PASS_STATIC
  EDIT_CLINICAL_STATE_RESTRICTIONS: NOT_VERIFIED
  EDIT_DOES_NOT_CREATE_NEW_NOTE: PASS_STATIC
  DATATABLE_EVENTS_PRESERVED: PASS_STATIC
  DATATABLE_SEARCH: PASS_STATIC
  DATATABLE_SORT: PASS_STATIC
  DATATABLE_PAGINATION: PASS_STATIC
  PHASE_07_PRESERVED: PASS_STATIC
  PHASE_08_PRESERVED: PASS_STATIC
  PHASE_08B_PRESERVED: PASS_STATIC
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  CSS_REVIEW: PASS_STATIC
  FOCUSED_TESTS: PASS_STATIC
  DIFF_CHECK: PASS_WITH_HISTORICAL_LINE_ENDING_WARNINGS
  BROWSER_VALIDATION: NOT_EXECUTED
  EDIT_RUNTIME_VALIDATION: NOT_EXECUTED
  FILES_MODIFIED:
    - app/Config/routes.php
    - app/Controllers/ClinicaController.php
    - app/Models/NotaSubsecuenteModel.php
    - app/views/clinica/pacientes-nota-subsecuente.php
    - app/views/clinica/pacientes-nota-subsecuente-nueva.php
    - app/views/clinica/partials/nota-subsecuente-editor.php
    - public/assets/css/app.css
    - tests/doctor-subsequent-note/list-actions-contract-08c.php
    - docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_LIST_UX_ACTIONS_08C.md
  DOCUMENT_CREATED: YES
  DATABASE_MODIFIED: NO
  SCHEMA_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS: XAMPP HTTP/MySQL NO DISPONIBLE DESDE CODEX; NO SE PUDO PROBAR EN NAVEGADOR NI CON REGISTROS SINTÉTICOS
  NEXT_MANUAL_VALIDATION: PROBAR LISTADO, DETALLE Y EDICIÓN CON DOS PACIENTES SINTÉTICOS Y VERIFICAR BÚSQUEDA, ORDENAMIENTO, PAGINACIÓN, TECLADO, ESCAPE Y PERSISTENCIA
```
