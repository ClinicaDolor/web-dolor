# TH-DOCTOR-SUBSEQUENT-NOTE-DETAIL-BACK-NAVIGATION-FIX-08E

## Diagnóstico

La vista de detalle `app/views/clinica/nota-subsecuente.php` construía el botón de regreso con una URL fija al expediente del paciente:

```text
/clinica/paciente/{idPaciente}
```

Por eso el flujo `Historial → Ver detalle → Regresar` abandonaba el historial de Nota subsecuente.

## Corrección

El listado conserva la acción única `Ver detalle` de 08D y añade al enlace un contexto interno dinámico:

- `context_ref`: referencia de origen generada por el historial.
- `context_patient`: identificador dinámico del paciente del listado.

El controlador de detalle mantiene la autorización sobre el paciente real obtenido desde la nota mediante `assertPatientAccess()`. Sólo acepta el contexto si el paciente recibido coincide con el paciente autorizado y la referencia cumple el formato real de `uniqid('', true)`. Después construye en servidor la URL interna del historial; no acepta una URL de retorno arbitraria.

La referencia propia almacenada en la nota y la referencia del historial de origen se tratan por separado. Si existe contexto válido, se conserva la referencia de origen aunque sea distinta de la referencia propia de la nota. En acceso directo sin contexto, se busca la referencia propia en el historial autorizado del paciente. Si tampoco existe, se mantiene como último fallback el expediente general.

No se utiliza `history.back()`, `document.referrer`, sesión global de pestaña ni redirección externa.

## Archivos modificados

- `app/views/clinica/pacientes-nota-subsecuente.php`
- `app/Controllers/NotaSubsecuenteController.php`
- `app/views/clinica/nota-subsecuente.php`
- `tests/doctor-subsequent-note/detail-back-navigation-contract-08e.php`
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_DETAIL_BACK_NAVIGATION_FIX_08E.md`

No se modificaron modelos, rutas, endpoints POST, datos clínicos ni estilos del historial. Se conservaron la eliminación de la tarjeta de contexto, el historial completo, `Nueva nota subsecuente`, el botón único de detalle 08D, la creación/edición independiente, CSRF, autorización clínica y transacciones de Phase 07.

## Validación estática

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_DETAIL_BACK_NAVIGATION_FIX_08E:
  STATUS: IMPLEMENTED_STATIC_VALIDATION_PENDING_BROWSER
  DETAIL_ROUTE: PRESERVED
  ORIGINAL_BACK_DESTINATION: /clinica/paciente/{idPaciente}
  ROOT_CAUSE: FIXED_STATIC
  HISTORY_ORIGIN_PRESERVED: PASS_STATIC
  PATIENT_ID_DYNAMIC: PASS_STATIC
  REFERENCE_DYNAMIC: PASS_STATIC
  DETAIL_LINK_CONTEXT: PASS_STATIC
  CONTEXT_AUTHORIZATION: PASS_STATIC
  BACK_BUTTON_CORRECTED: PASS_STATIC
  BACK_DESTINATION: INTERNAL_HISTORY_URL
  DIRECT_ACCESS_FALLBACK: PASS_STATIC
  DIFFERENT_REFERENCE_CASE: PASS_STATIC_BY_CONTEXT_BINDING
  MULTI_TAB_ISOLATION: PASS_STATIC_NO_GLOBAL_STATE
  PATIENT_AUTHORIZATION_PRESERVED: PASS_STATIC
  CLINIC_ISOLATION_PRESERVED: PASS_STATIC
  NOTE_DETAIL_PRESERVED: PASS_STATIC
  PHASE_07_PRESERVED: PASS_STATIC
  PHASE_08B_PRESERVED: PASS_STATIC
  PHASE_08D_PRESERVED: PASS_STATIC
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  FOCUSED_TESTS: PASS_STATIC
  DIFF_CHECK: PASS_WITH_HISTORICAL_LINE_ENDING_WARNINGS
  BROWSER_VALIDATION: NOT_EXECUTED
  HTTP_RUNTIME_VALIDATION: NOT_EXECUTED
  DATABASE_MODIFIED: NO
  SCHEMA_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
```

Las pruebas HTTP, la navegación real, el caso de dos pestañas y la comprobación visual quedan pendientes de ejecución en Chrome/Edge sobre XAMPP del propietario. No se realizaron escrituras ni se consultó la base desde Codex.
