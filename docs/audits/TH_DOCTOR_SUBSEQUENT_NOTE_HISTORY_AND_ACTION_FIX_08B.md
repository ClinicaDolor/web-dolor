# TH-DOCTOR-SUBSEQUENT-NOTE-HISTORY-AND-ACTION-FIX-08B

## Alcance

Corrección focalizada del listado de Nota subsecuente para el paciente autorizado,
sin modificar el editor de alta, el guardado de Phase 07, recetas, laboratorios,
esquema de base de datos ni rutas ajenas al módulo.

## Identificación y causa

La vista de listado creada en la fase 08 consultaba directamente
`nota_subsecuente` con `id_paciente` y `codigo_referencia` de la URL. Por ello,
el historial visible quedaba limitado a la referencia actual, aunque el modelo
ya contemplaba el modo histórico completo mediante referencia `0`.

Además, el botón **Nueva nota subsecuente** estaba fuera de la tarjeta
**Historial de notas**, y el estado vacío propio de la vista se mostraba junto
con el estado `noRows` generado por Simple-DataTables.

## Corrección aplicada

- La autorización server-side del paciente se ejecuta primero en
  `ClinicaController::notaSubsecuentePacienteContext()` mediante
  `assertPatientAccess()`.
- El controlador solicita el historial completo mediante
  `NotaSubsecuenteModel::getHistoryByPatient()`.
- La consulta del modelo filtra únicamente por el paciente autorizado y
  conserva `codigo_referencia` por registro para trazabilidad.
- La referencia actual se conserva en la URL de **Nueva nota subsecuente**;
  no se usa para ocultar notas históricas de otras referencias.
- El botón de alta quedó dentro del encabezado de la tarjeta de historial,
  alineado a la derecha en escritorio y apilable en móvil.
- Cuando no hay registros, sólo se renderiza un estado vacío propio con la
  acción de crear una nota. Cuando sí hay registros, Simple-DataTables sólo
  informa cuando una búsqueda no produce coincidencias.
- El detalle continúa usando `/clinica/nota-subsecuente/{id}` y su autorización
  server-side existente.

## Consulta de conteos para ejecutar en Windows

Codex no tuvo acceso al MySQL/XAMPP local durante esta intervención. Las
siguientes consultas son exclusivamente de lectura y deben ejecutarse sólo
después de confirmar que el entorno autorizado es sintético y que la base
seleccionada es `bd_clinica`:

```sql
SELECT DATABASE() AS selected_database;

SELECT COUNT(*) AS patient_note_count
FROM bd_clinica.nota_subsecuente
WHERE id_paciente = 310;

SELECT COUNT(*) AS current_reference_note_count
FROM bd_clinica.nota_subsecuente
WHERE id_paciente = 310
  AND codigo_referencia = '6ad3f95768c6c4.34892158';

SELECT COUNT(*) AS other_reference_note_count
FROM bd_clinica.nota_subsecuente
WHERE id_paciente = 310
  AND (codigo_referencia <> '6ad3f95768c6c4.34892158'
       OR codigo_referencia IS NULL
       OR codigo_referencia = '');
```

No se ejecutaron estas consultas desde Codex; por tanto, no se reportan
conteos ni se afirma que el paciente tenga notas hasta obtener esa evidencia.

## Validación estática

- Se conserva la separación entre listado y creación implementada en la fase
  08.
- Se conserva el formulario independiente, el partial del editor, el token
  CSRF y la transacción de guardado de Phase 07.
- La creación de una nota continúa usando la referencia actual de su contexto.
- No se ejecutaron HTTP, navegador, SQL, PDO ni escrituras.

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_HISTORY_AND_ACTION_FIX_08B:
  STATUS: IMPLEMENTED_STATIC_VALIDATION_PENDING_RUNTIME
  TARGET_ROUTE: /clinica/nota-subsecuente/paciente/310/referencia/6ad3f95768c6c4.34892158
  LIST_VIEW_PRESERVED: PASS_STATIC
  NEW_NOTE_VIEW_PRESERVED: YES
  PATIENT_HAS_NOTES: NOT_VERIFIED
  PATIENT_NOTE_COUNT: NOT_VERIFIED
  CURRENT_REFERENCE_NOTE_COUNT: NOT_VERIFIED
  OTHER_REFERENCES_NOTE_COUNT: NOT_VERIFIED
  HISTORY_ROOT_CAUSE: PHASE_08_REFERENCE_FILTER_EXCLUDED_OTHER_REFERENCES; RUNTIME_DATA_NOT_VERIFIED
  HISTORY_SCOPE_BEFORE: AUTHORIZED_PATIENT_AND_CURRENT_REFERENCE
  HISTORY_SCOPE_AFTER: AUTHORIZED_PATIENT_FULL_HISTORY; CURRENT_REFERENCE_ONLY_FOR_NEW_NOTE_CONTEXT
  HISTORY_QUERY_CORRECTED: PASS_STATIC
  AUTHORIZED_PATIENT_HISTORY: PASS_STATIC_ONLY
  PATIENT_AUTHORIZATION: PASS_STATIC_ONLY
  CLINIC_ISOLATION: PASS_STATIC_ONLY
  RECORD_DETAIL_AUTHORIZATION: PASS_STATIC_ONLY
  REFERENCE_INTEGRITY: PASS_STATIC_ONLY
  NOTES_VISIBLE: PASS_STATIC_ONLY
  EMPTY_STATE_CORRECT: PASS_STATIC_ONLY
  DUPLICATE_EMPTY_MESSAGE_REMOVED: PASS_STATIC_ONLY
  NEW_NOTE_BUTTON_INSIDE_CARD: PASS_STATIC_ONLY
  NEW_NOTE_BUTTON_ALIGNED_RIGHT: PASS_STATIC_ONLY
  NEW_NOTE_BUTTON_PURPLE: PASS_STATIC_ONLY
  NEW_NOTE_BUTTON_ROUTE_PRESERVED: PASS_STATIC_ONLY
  DUPLICATE_BUTTONS: NO
  PHASE_07_SAVE_PRESERVED: PASS_STATIC_ONLY
  CSRF_PRESERVED: PASS_STATIC_ONLY
  CREATE_VIEW_UNCHANGED: YES
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  FOCUSED_TESTS: PASS_STATIC
  DIFF_CHECK: PASS_WITH_HISTORICAL_LINE_ENDING_WARNINGS
  BROWSER_VALIDATION: NOT_EXECUTED
  DATABASE_READ_ONLY_VALIDATION: NOT_VERIFIED
  FILES_MODIFIED:
    - app/Controllers/ClinicaController.php
    - app/Models/NotaSubsecuenteModel.php
    - app/views/clinica/pacientes-nota-subsecuente.php
    - public/assets/css/app.css
    - tests/doctor-subsequent-note/independent-views-contract-08.php
    - docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_HISTORY_AND_ACTION_FIX_08B.md
  DOCUMENT_CREATED: YES
  DATABASE_MODIFIED: NO
  SCHEMA_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS: XAMPP HTTP/MySQL NO DISPONIBLE DESDE CODEX; clinica.test NO RESUELVE EN ESTE ENTORNO
  NEXT_MANUAL_VALIDATION: EJECUTAR LOS CONTEOS SQL Y PROBAR EN CHROME CON PACIENTE SINTÉTICO AUTORIZADO
```

P09 y otros módulos no se iniciaron ni modificaron. El resultado permanece
pendiente de validación visual y funcional real.
