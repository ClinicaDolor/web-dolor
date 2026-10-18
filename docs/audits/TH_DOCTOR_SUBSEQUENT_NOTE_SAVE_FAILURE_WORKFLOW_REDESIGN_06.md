# TH-DOCTOR-SUBSEQUENT-NOTE-SAVE-FAILURE-AND-WORKFLOW-REDESIGN-06

## Alcance y estado

Esta revisión es exclusivamente diagnóstica y arquitectónica. No se modificaron PHP, CSS, JavaScript, rutas, modelos, base de datos ni componentes compartidos. El objetivo fue explicar el fallo de guardado reportado y preparar una propuesta para separar el listado de notas de la captura de una nueva nota.

El entorno de Codex no tiene conectividad con el XAMPP local (`clinica.test`, TCP 80 y MySQL local no están disponibles). Por ello, el estado HTTP exacto, la respuesta de red y la persistencia real permanecen sin verificar. Las conclusiones sobre el contrato de aplicación se basan en inspección estática del código actual.

## Identificación real del flujo actual

### Vista y ruta

- Vista actual: `app/views/clinica/pacientes-nota-subsecuente.php`.
- Ruta GET actual: `/clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}`.
- Controlador GET: `ClinicaController::pacienteNotaSubsecuente()`.
- Ruta POST actual: `/clinica/paciente/insert-nota-subsecuente`.
- Controlador POST: `ClinicaController::pacienteInsertNotaSubsecuente()`.
- Detalle posterior: `/clinica/nota-subsecuente/{idNota}`.

El GET autoriza el paciente mediante `assertPatientAccess()`, resuelve la nota y la receta por paciente y referencia, y expone a la vista los identificadores, la referencia y un token de captura CSRF. El POST vuelve a validar método, JSON, identificadores, CSRF, acceso al paciente y pertenencia de nota/receta al paciente y referencia.

### Recorrido de guardado

1. `AgregarNota()` lee los campos clínicos, los editores Quill, `idNota`, `idReceta`, paciente y referencia.
2. Envía un `fetch()` POST JSON al endpoint actual, con `X-M03-CSRF`.
3. El controlador elige `insertNotaSubsecuente()` cuando `idNota` es cero y `editarNotaSubsecuente()` cuando es mayor que cero.
4. En alta, el modelo inserta primero `nota_subsecuente` y después llama siempre a `ClinicaModel::insertPacienteReceta()`.
5. En edición, el modelo actualiza la nota y, si `idReceta > 0`, actualiza también la receta.
6. En éxito, la vista limpia campos, llama a `imprimirReceta()` y navega automáticamente al detalle de la nota.

El flujo actual no tiene una operación separada llamada «finalizar». El POST es una operación de alta o edición; la impresión y la redirección son efectos posteriores del éxito. No debe interpretarse este flujo como una finalización clínica idempotente.

## Diagnóstico del mensaje reportado

### Hallazgo principal confirmado estáticamente

La creación de una nota nueva está acoplada obligatoriamente a la creación de una receta. `NotaSubsecuenteModel::insertNotaSubsecuente()` ejecuta el INSERT de la nota y, sin comprobar si el usuario solicitó receta, llama a `insertPacienteReceta()`.

Además existe un desacuerdo de nombres de campos entre el frontend y el contrato de receta:

| Concepto | Payload enviado por `AgregarNota()` | Clave leída por `insertPacienteReceta()` |
|---|---|---|
| Frecuencia cardiaca | `frecCardiaca` | `fc` |
| Temperatura | no se envía | `temperatura` |
| Saturación | `spo2` | `spo2` |
| Tensión arterial | `ta` | `ta` |

El modelo de nota sí consume `frecCardiaca`, pero el modelo de receta lee `fc` y `temperatura`. En PHP 8, las claves ausentes pueden producir avisos; si las columnas o restricciones de `receta_medica` no aceptan esos valores, el INSERT de receta puede fallar o lanzar una excepción. Esto es una causa técnica demostrable del contrato roto y la causa más probable del error reportado, pero no se declara como causa HTTP confirmada porque no se pudo ejecutar el POST en el entorno local.

### Origen del texto «No se pudo completar la solicitud.»

`SafeErrorHandler` devuelve JSON 500 con:

```json
{"resultado":false,"mensaje":"No se pudo completar la solicitud.","token":""}
```

cuando una excepción de aplicación llega a la frontera JSON. La vista procesa `response.json()` sin comprobar `response.ok`; si recibe ese JSON, muestra `Error: No se pudo completar la solicitud.`. El `catch()` de la vista usa otro texto (`No fue posible guardar la nota. Inténtalo nuevamente.`), por lo que el texto exacto reportado es compatible con una respuesta JSON de `SafeErrorHandler` o con un `resultado:false` del endpoint, no con el `catch()` actual.

### Hallazgos que no deben atribuirse sin prueba runtime

- No está confirmado si la respuesta observada fue HTTP 500, 401, 403 u otro estado.
- No está confirmado si la nota ya quedó persistida cuando apareció el error.
- No está confirmado qué restricción concreta de la base produjo la excepción.
- No está confirmado que CSRF sea la causa: el token se genera en GET, se envía en `X-M03-CSRF` y una invalidación produce un 403 con un mensaje diferente.
- No está confirmado que la autorización sea la causa: el controlador valida acceso al paciente y pertenencia por paciente/referencia antes de escribir.

## Riesgos de integridad y flujo

### Escrituras parciales

La inserción de nota y receta no está dentro de una transacción. Si el INSERT de nota tiene éxito y el de receta falla, la nota puede quedar persistida sin que el frontend tenga una respuesta fiable. El modelo tampoco comprueba el campo `resultado` devuelto por `insertPacienteReceta()` antes de responder éxito; toma `mensaje` como si siempre fuera un `idReceta` válido.

En edición ocurre un riesgo equivalente: el UPDATE de la nota se ejecuta antes del UPDATE de la receta. Si la receta falla, la nota puede quedar modificada y el endpoint devolver error. No existe una compensación ni una transacción que haga indivisible la operación.

### Reintentos y duplicación

La bandera `notaGuardando` evita un doble clic dentro de la misma página, pero no existe una clave idempotente de servidor. Un timeout o una respuesta perdida puede dejar la operación aplicada y provocar una segunda nota al reintentar. La redirección automática y la impresión posterior aumentan la dificultad de distinguir un guardado completado de uno incierto.

### Acoplamiento no solicitado

El guardado de nota llama siempre a `imprimirReceta()`, aun cuando la intención sea únicamente guardar una nota. Esto mezcla persistencia clínica, generación/impresión documental y navegación en una sola acción. No se corrigió en esta intervención.

## Preservación y controles de seguridad

La revisión estática confirma la presencia de:

- CSRF vinculado al paciente y a la cookie de sesión mediante `ClinicalSummarySecurity::validCaptureCsrf()`.
- Autorización del paciente mediante `assertPatientAccess()`.
- Comprobación de pertenencia de nota y receta por identificador, paciente y referencia.
- Actualización de nota con restricciones `id`, `id_paciente` y `codigo_referencia`.
- Contrato de sanitización y seguridad estática previamente cubierto por `tests/doctor-subsequent-note/security-contract-04.php`.

Estos controles no equivalen a una validación HTTP. Las pruebas negativas de CSRF, paciente cruzado, referencia cruzada, persistencia y descarga permanecen pendientes.

## Propuesta de arquitectura independiente

La propuesta es conceptual; no se creó la ruta ni la vista.

### Listado

Conservar el acceso actual como listado/contexto de la referencia durante la migración. Una vista de listado independiente debería mostrar historial y acciones, sin cargar de forma permanente el formulario de alta.

### Alta

Ruta propuesta, pendiente de aprobación y de implementación:

`/clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}/nueva`

La ruta es una propuesta y no forma parte todavía del router. Debe abrir una vista nueva con contexto de paciente y referencia autorizado en servidor. El formulario de alta debe crear únicamente una nota si no se solicitó un complemento.

### Campos clínicos que deben conservarse

La nueva vista debe preservar, sin renombrar ni resumir:

- TA.
- Frecuencia cardiaca.
- Pulso.
- SpO2.
- FiO2.
- ECOG.
- Karnovsky.
- Peso.
- Talla.
- Detalle de la nota (`snow1`).
- Diagnóstico.
- Medicamento/receta (`snow2`) cuando se solicite el complemento.
- Próxima cita.
- Paciente, referencia, CSRF y contexto autorizado.

El laboratorio (`Titulo`, archivo, descripción y editor `snow3`) debe permanecer como flujo separado y opcional. No se debe incluir automáticamente en la creación de una nota.

### Opcionalidad de complementos

| Elemento | Estado actual | Diseño recomendado |
|---|---|---|
| Nota sin receta | No soportado de forma fiable en alta: siempre se intenta crear receta | Sí, guardar nota sin receta; crear receta sólo mediante una intención explícita |
| Receta | Obligatoria accidentalmente en alta; además tiene contrato de campos incompatible | Opcional, con payload propio (`fc`, `temperatura`, etc.) y validación explícita |
| Laboratorio | Flujo separado y accionado por el usuario | Mantenerlo separado y opcional |
| Próxima cita | Se envía en el payload de la nota y puede ser vacía según el formulario | Confirmar nulabilidad y contrato real de la columna antes de declarar PASS; no asumir que siempre es opcional |

La edición existente puede actualizar sólo la nota cuando `idReceta` es cero, pero la vista suele resolver una receta asociada por referencia. Esa diferencia debe resolverse en el diseño de la nueva pantalla, no mediante campos ocultos ambiguos.

## Plan de navegación propuesto

```text
Listado de notas
      │
      ├── Nueva nota ── Guardar nota ──> Detalle o listado
      │                    └── complemento solicitado: receta/laboratorio
      └── Editar nota ── Guardar cambios ──> Detalle o listado
```

- La navegación de éxito debe elegir explícitamente detalle o listado; no depender de `history.back()`.
- Cancelar debe volver al listado/contexto conocido mediante una URL explícita.
- Si hay cambios sin guardar, mostrar confirmación antes de cancelar o salir.
- No imprimir ni descargar automáticamente al guardar una nota, salvo que el propietario apruebe ese contrato como acción separada.
- No marcar una nota como finalizada por el hecho de guardarla.

## Plan para fallos parciales e idempotencia

Antes de implementar el rediseño, el backend debe decidir una de estas dos estrategias:

1. Operación atómica: transacción para nota y cada complemento explícitamente solicitado, con commit sólo cuando todos los resultados esperados estén confirmados.
2. Operaciones separadas: guardar nota y complementos mediante endpoints distintos, con estados claros y sin reintentos automáticos silenciosos.

En ambos casos:

- El servidor debe aceptar una clave idempotente por intento de creación o aplicar una restricción equivalente.
- Un timeout no debe provocar una segunda nota sin consultar el estado anterior.
- Una respuesta de error no debe ocultar que la nota sí pudo persistir.
- El frontend debe comprobar `response.ok`, `Content-Type` y la forma JSON antes de interpretar éxito.
- El usuario debe poder reintentar de forma segura y consciente.

No se recomienda resolver el problema con un simple remapeo silencioso de `frecCardiaca` a `fc` o inventar una `temperatura`: son campos clínicamente distintos y requieren aprobación del contrato.

## Reutilización de endpoints y cambios requeridos

- GET actual: reutilizable para el contexto existente, sujeto a la validación runtime de autorización.
- POST actual: no debe reutilizarse sin corregir primero el contrato de nota/receta y la atomicidad.
- Endpoint de laboratorio: reutilizable como operación separada, sujeto a sus validaciones de archivo, CSRF y autorización.
- Endpoint de receta: puede reutilizarse para una acción explícita de receta, pero debe conservar su CSRF, autorización y sanitización.
- Nueva ruta/vista: recomendable para alta independiente; no creada.
- Backend: requerido para soportar de forma segura receta opcional, resultado de operaciones y atomicidad/idempotencia.
- Cambio de esquema: no se conoce un requisito; debe verificarse la nulabilidad real de `proxima_cita` y las restricciones de receta mediante consulta de sólo lectura antes de decidir.

## Validación pendiente y procedimiento de evidencia

No se ejecutó navegador ni HTTP desde Codex. En Chrome/Windows, con una cuenta sintética autorizada y sin copiar secretos, la comprobación focalizada debe ser:

1. Abrir DevTools → Network y activar `Preserve log`.
2. Filtrar `Fetch/XHR`.
3. Abrir la nota subsecuente y pulsar Guardar una sola vez.
4. Inspeccionar sólo método, URL, código HTTP, `Content-Type`, duración y si la respuesta es JSON; no copiar cookies, tokens, cuerpos clínicos ni credenciales.
5. Registrar si la respuesta contiene `resultado`, `mensaje`, `idNota` e `idReceta`, sin guardar sus valores sensibles.
6. Si hay error, comparar el estado y el mensaje sanitizado con el texto del frontend; comprobar mediante una lectura autorizada y no destructiva si la nota quedó persistida.
7. Repetir sólo con fixtures sintéticos para nota sin receta, receta válida, campos inválidos, token ausente, paciente cruzado y reintento tras timeout.
8. Confirmar que el guardado exitoso no se interpreta como finalización y que la navegación coincide con la decisión del propietario.

No deben ejecutarse pruebas contra paciente 310 ni contra datos reales desde este entorno.

## Informe

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_SAVE_FAILURE_WORKFLOW_REDESIGN_06:
  STATUS: DIAGNOSIS_STATIC_RUNTIME_PENDING_ARCHITECTURE_PROPOSED
  SAVE_FAILURE_REPRODUCED: NO_RUNTIME_NOT_VERIFIED
  SAVE_FAILURE_ROOT_CAUSE: CONFIRMED_STATIC_CONTRACT_MISMATCH_AND_MANDATORY_RECIPE_COUPLING
  FAILING_ENDPOINT: POST /clinica/paciente/insert-nota-subsecuente
  HTTP_STATUS: NOT_VERIFIED_RUNTIME; JSON_500_PATH_IDENTIFIED
  SERVER_ERROR: SAFE_ERROR_HANDLER_CAN_RETURN_GENERIC_JSON_500; EXCEPTION_NOT_IDENTIFIED_RUNTIME
  FRONTEND_ERROR: DATA_RESULT_FALSE_IS_RENDERED_AS_ERROR_PREFIX; CATCH_USES_A_DIFFERENT_MESSAGE
  CSRF_CAUSE: NOT_CONFIRMED; STATIC_HEADER_AND_VALIDATION_PRESENT
  REFERENCE_CAUSE: NOT_CONFIRMED; PATIENT_AND_REFERENCE_GUARDS_PRESENT
  DATABASE_CAUSE: NOT_VERIFIED; RECIPE_CONSTRAINT_OR_MISSING_CONTRACT_FIELDS_ARE_PLAUSIBLE
  EXISTING_NOTE_ALREADY_PERSISTED: NOT_VERIFIED; PARTIAL_WRITE_RISK_CONFIRMED_STATICALLY
  MINIMAL_FIX_PROPOSED: SEPARATE_NOTE_SAVE_FROM_OPTIONAL_RECIPE; CORRECT_EXPLICIT_RECIPE_PAYLOAD; ADD_ATOMICITY_OR_SEPARATE_OPERATION; DO_NOT_IMPLEMENT_IN_THIS TASK
  SECURITY_CONTROLS_PRESERVED: PASS_STATIC; RUNTIME_PENDING
  LIST_ONLY_VIEW_FEASIBLE: YES_WITH_MIGRATION
  NEW_NOTE_ROUTE_PROPOSED: /clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}/nueva; PROPOSED_ONLY
  NEW_NOTE_VIEW_FEASIBLE: YES
  CLINICAL_FIELDS_PRESERVED: ALL_CURRENT_NOTE_FIELDS_AND_CONTEXT; SEE_FIELD_INVENTORY
  PRESCRIPTION_OPTIONAL: CURRENT_NO; PROPOSED_YES_AFTER_CONTRACT_FIX
  LABORATORY_OPTIONAL: YES_AS_SEPARATE_FLOW; RUNTIME_PENDING
  NEXT_APPOINTMENT_OPTIONAL: STATIC_UNCONFIRMED; VERIFY_COLUMN_NULLABILITY
  NOTE_ONLY_SAVE_SUPPORTED: CURRENT_NO_FOR_NEW; YES_FOR_EDIT_WHEN_IDRECETA_IS_ZERO; PROPOSED_YES
  EXISTING_ENDPOINTS_REUSABLE: PARTIAL; GET_AND_SEPARATE_COMPLEMENT_ENDPOINTS_REUSABLE_AFTER_RUNTIME_VALIDATION
  NEW_BACKEND_REQUIRED: YES_FOR_SAFE_OPTIONAL_COMPLEMENT_AND_ATOMICITY_CONTRACT
  DATABASE_SCHEMA_CHANGE_REQUIRED: NOT_KNOWN; READ_ONLY_SCHEMA_CHECK_REQUIRED
  NAVIGATION_PLAN: EXPLICIT_LIST_OR_DETAIL_DESTINATION; NO_HISTORY_BACK; SAVE_IS_NOT_FINALIZATION
  UNSAVED_CHANGES_PLAN: EXPLICIT_CANCEL_URL_AND_CONFIRMATION_WHEN_DIRTY
  PARTIAL_FAILURE_PLAN: TRANSACTION_OR_SEPARATE_OPERATIONS_WITH_EXPLICIT_STATUS; NO_SILENT_RETRY
  IDEMPOTENCY_RISK: CONFIRMED_STATICALLY; FRONTEND_GUARD_ONLY_NO_SERVER_IDEMPOTENCY_KEY
  PHP_LINT: PASS_STATIC_REVIEW_OF_UNMODIFIED_RELEVANT_FILES
  JS_SYNTAX: PASS_STATIC_REVIEW_NO_JS_MODIFIED
  FOCUSED_TESTS: PASS_STATIC_SECURITY_AND_REFERENCE_CONTRACTS; HTTP_NOT_EXECUTED
  BROWSER_VALIDATION: NOT_EXECUTED
  RUNTIME_VALIDATION: NOT_VERIFIED
  FILES_MODIFIED: docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_SAVE_FAILURE_WORKFLOW_REDESIGN_06.md
  APPLICATION_CODE_MODIFIED: NO
  DATABASE_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS: CODEX_CANNOT_REACH_LOCAL_XAMPP; OWNER_RUNTIME_EVIDENCE_REQUIRED
  OWNER_DECISIONS: RECIPE_OPTIONALITY; BLANK_NEXT_APPOINTMENT; SAVE_DESTINATION; AUTOMATIC_PRINT_POLICY
  NEXT_RECOMMENDED_ACTION: CAPTURE_ONE_SANITIZED_NETWORK_TRACE_ON_SYNTHETIC_DATA_THEN_APPROVE_BACKEND_CONTRACT_FOR_SEPARATE_NOTE_SAVE
```

## Conclusión

El fallo no debe cerrarse como un problema puramente visual ni resolverse con otro parche de CSS. La evidencia estática muestra un contrato de alta defectuoso: una nota nueva siempre intenta crear una receta, aunque la receta no sea parte de la intención del usuario, y el payload no contiene las claves que el INSERT de receta consume. La corrección requiere una decisión de producto sobre la opcionalidad de la receta y una corrección de backend con persistencia coherente. Esta tarea deja el sistema sin cambios de aplicación y el runtime pendiente de comprobación controlada.
