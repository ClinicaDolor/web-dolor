# Remediación P1 de integridad — Entrevista Conversacional

Fecha: 2026-10-14  
Rama: `Silvino`  
HEAD inicial: `59654e05dc9f931c21b5392238961bcddb3ab2e4`  
Modo: implementación focal con pruebas sintéticas aisladas

## Alcance y protección

Se corrigieron exclusivamente:

1. Atomicidad de la captura compuesta M08.
2. Fidelidad temporal de M04.
3. Autorización de edición previa de M03 desde el servidor.

No se modificaron `.env`, `bd_clinica`, el paciente 310, formularios tradicionales,
esquemas SQL, Gemini, STT externo, producción, commits ni despliegues.

El worktree ya contenía cambios locales y archivos paralelos. Fueron preservados.

Todas las pruebas con escritura utilizaron únicamente `clinica_dolor_browser_20261012`
y el paciente sintético 9302, con restauración focal en `finally`.

## Hallazgos originales y causa raíz

### P1-01 — M08

`ConversationalM08TreatmentAdapter::capture()` llamaba varias veces a
`ProcedimientosDolorModel::p10EditarTratamiento()` sin una transacción global.
Una falla posterior podía dejar persistidos los campos anteriores.

### P1-02 — M04

`ConversationalM04Adapter::interpret()` convertía cualquier año detectado en
`YYYY-01-01`, aunque el paciente no hubiera indicado día ni mes.
El campo canónico `fecha` es `DATE NULL`, por lo que no debe recibir precisión
inventada.

### P1-05 — Edición previa

`captureDirect()` aceptaba `allowEdit` desde el navegador y lo trasladaba al
adaptador como autorización para modificar una respuesta existente. Aunque se
validaban sesión, paciente, CSRF, versión y catálogo, la bandera del cliente no
debía ser la fuente de autoridad.

## Cambios aplicados

### M08: transacción única

Archivos:

- `app/Models/ProcedimientosDolorModel.php`
- `app/Services/ConversationalM08TreatmentAdapter.php`

El modelo acepta opcionalmente la PDO ya abierta. El adaptador pasa la misma
conexión a lectura y escritura, inicia la transacción antes del primer campo,
confirma sólo cuando todos los campos terminan correctamente y hace rollback ante
cualquier excepción o resultado no exitoso.

El lock, la política de finalización, la versión, la validación canónica y la
lectura posterior se conservaron.

### M04: precisión temporal

Archivo:

- `app/Services/ConversationalM04Adapter.php`

Reglas implementadas:

- Día, mes y año válidos: se guarda la fecha exacta.
- Mes y año: se conserva en `observaciones`; `fecha` permanece NULL.
- Año aislado: se conserva en `observaciones`; `fecha` permanece NULL.
- Tiempo relativo o aproximado: se conserva en `observaciones`; `fecha` permanece NULL.
- Fecha inválida: se solicita aclaración y no se genera propuesta persistible.
- “No recuerdo la fecha”: se solicita aclaración y no se clasifica como negación.

No se modificó el esquema ni el formulario tradicional.

### M03: autoridad de edición en servidor

Archivos:

- `app/Controllers/PatientInterviewController.php`
- `app/Services/ConversationalM03Adapter.php`

El controlador ya no utiliza `allowEdit` para autorizar una modificación.
El adaptador calcula en servidor la única respuesta existente que puede editarse:
la respuesta elegible inmediatamente anterior a la primera pregunta pendiente.

Se mantienen:

- sesión autenticada;
- aislamiento del paciente;
- CSRF ligado a cookie JWT y paciente;
- versión esperada;
- catálogo y tipo de pregunta;
- dependencias M03;
- rechazo de módulos finalizados;
- rechazo de accesos cruzados.

Una solicitud manipulada con `allowEdit=true` sobre otra respuesta devuelve
`DIRECT_EDIT_NOT_AUTHORIZED` y no escribe.

## Pruebas ejecutadas

### M08

`tests/patient-interview/m08-atomic-capture-synthetic.php`

Resultado:

```yaml
database: clinica_dolor_browser_20261012
m08_atomic_capture: PASS
intermediate_failure_rollback: PASS
successful_persistence: PASS
readback: PASS
no_external_calls: true
```

La prueba instaló temporalmente un fallo controlado en la base sintética,
confirmó que no quedó la primera escritura parcial, retiró el instrumento de
prueba y restauró las filas originales.

`tests/patient-interview/m08-treatment-contract.php`: PASS, incluyendo lectura,
confirmación, lectura posterior, versión obsoleta y restauración del fixture.

### M04

`tests/patient-interview/m04-temporal-fidelity-synthetic.php`

Resultado:

```yaml
database: clinica_dolor_browser_20261012
m04_temporal_fidelity: PASS
year_only: PRESERVED_AS_OBSERVATION
month_year: PRESERVED_AS_OBSERVATION
exact_date: PRESERVED
relative_time: PRESERVED_AS_OBSERVATION
unknown_and_invalid: CLARIFICATION_NO_WRITE
```

`tests/patient-interview/m04-conversational-contract.php`: PASS.

`tests/patient-interview/m04-http-acceptance-once.php`: PASS con servidor PHP
temporal aislado, sesión sintética, CSRF, lectura posterior, versión obsoleta,
finalización y denegación entre pacientes. El servidor fue detenido y no quedó
listener temporal.

### Edición M03

`tests/patient-interview/m03-direct-duration-synthetic.php`: PASS.

`tests/patient-interview/m03-direct-http-acceptance.php`: PASS.

Resultado relevante:

```yaml
http_direct_save: PASS
readback: PASS
duplicate_count: 0
tampered_allow_edit: PASS
cross_patient_denial: PASS
cross_clinic_denial: PASS
real_gemini_calls: 0
```

También pasaron:

- `direct-submit-back-contract.php`
- `m03-csrf-contract.php`
- `native-stt-contract.php`
- `native-stt-js.test.js`
- lint PHP de los archivos modificados y nuevos

## Regresión focal

```yaml
M08_ATOMIC_CAPTURE: PASS
M04_TEMPORAL_FIDELITY: PASS
PREVIOUS_ANSWER_SERVER_AUTHORIZATION: PASS
DIRECT_SUBMIT_REGRESSION: PASS
VOICE_REGRESSION: PASS
NAVIGATION_REGRESSION: PASS
CLINICAL_REVIEW_REGRESSION: PASS
SCHEMA_MODIFIED: NO
TRADITIONAL_FORMS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
ENV_MODIFIED: NO
GEMINI_CALLS: 0
EXTERNAL_STT_CALLS: 0
```

Estos resultados son focales. No constituyen aceptación browser ni regresión
global de toda la Entrevista Conversacional.

## Riesgos residuales y bloqueos

- M07 continúa sin implementación contractual y no se resolvió en esta fase.
- La privacidad de Web Speech continúa pendiente: el navegador puede utilizar
  procesamiento externo y no se forzó `processLocally`.
- No se ejecutó aceptación visual autenticada en Chrome en esta fase.
- Los contratos estáticos con textos históricos siguen necesitando actualización
  independiente.

## Archivos modificados o creados por esta remediación

- `app/Models/ProcedimientosDolorModel.php`
- `app/Services/ConversationalM08TreatmentAdapter.php`
- `app/Services/ConversationalM04Adapter.php`
- `app/Services/ConversationalM03Adapter.php`
- `app/Controllers/PatientInterviewController.php`
- `tests/patient-interview/m08-atomic-capture-synthetic.php`
- `tests/patient-interview/m04-temporal-fidelity-synthetic.php`
- `tests/patient-interview/m03-direct-duration-synthetic.php`
- `tests/patient-interview/m03-direct-http-acceptance.php`
- este documento

## Estado final

```yaml
PHASE: PATIENT_INTERVIEW_P1_INTEGRITY_REMEDIATION
M08_ATOMIC_CAPTURE: PASS_VERIFIED
M04_TEMPORAL_FIDELITY: PASS_VERIFIED
PREVIOUS_ANSWER_SERVER_AUTHORIZATION: PASS_VERIFIED
REMAINING_P1_FINDINGS:
  - M07 no implementado
  - Privacidad de Web Speech pendiente
REMAINING_BLOCKERS:
  - Aceptación real en navegador
  - Contrato oficial M07
  - Política de privacidad para voz
FINAL_STATUS: PASS_VERIFIED
RECOMMENDED_NEXT_ACTION: revisar los bloqueos residuales antes de cualquier uso con expedientes reales
```

`PASS_VERIFIED` se refiere exclusivamente a las tres correcciones P1 de esta
fase. No autoriza expedientes reales, producción, Gemini real ni reconocimiento
de voz externo.
