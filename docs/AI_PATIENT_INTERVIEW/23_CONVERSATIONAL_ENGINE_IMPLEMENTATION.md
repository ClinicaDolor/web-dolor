# Implementación del motor conversacional M03

Fecha: 12 de octubre de 2026.

## Alcance

Se implementó el piloto conversacional independiente para M03/Tabaquismo sobre
`/historia-clinica/entrevista`. Los formularios tradicionales M01–M09, catálogos,
esquema SQL y mecanismo de persistencia no fueron reemplazados.

El flujo es:

`respuesta libre → propuesta estructurada → revisión/confirmación → captura M03 existente`

La interpretación se ejecuta en `/historia-clinica/entrevista/interpretar` y no
escribe en la base. Solo `/historia-clinica/entrevista/capturar`, después de la
confirmación explícita, reutiliza el modelo clínico existente.

## Seguridad y fidelidad

- La propuesta exige sesión Paciente, pertenencia del expediente y CSRF M03.
- La pregunta debe pertenecer al catálogo M03 autorizado.
- `Sí` y `No` se normalizan únicamente para preguntas `select`.
- Respuestas ambiguas producen una aclaración y no generan una propuesta guardable.
- No se modifican respuestas anteriores durante la conversación.
- La captura mantiene el valor esperado y el control de concurrencia existentes.
- Gemini, cuando se habilite expresamente, recibe únicamente la pregunta y la
  respuesta sintética de la interacción; nunca recibe un expediente ni una clave
  en el navegador.
- Fuera de `CLINICAL_AI_SYNTHETIC_MODE=1` el adaptador externo no se ejecuta.

## Verificación

`tests/patient-interview/conversational-engine-contract.php`: PASS.

Prueba HTTP sobre el servidor sintético aislado: la respuesta natural
«Sí, fumo ocasionalmente» produjo una propuesta estructurada `Si`, HTTP 200 y
modo `deterministic`; no se ejecutó la captura y el fixture permaneció sin cambios.

Los contratos CSRF, separación del chat y resumen clínico existentes permanecen PASS.

## Estado

```yaml
CONVERSATIONAL_ENGINE: IMPLEMENTED
GEMINI_ADAPTER: IMPLEMENTED
REAL_LLM_SYNTHETIC_EXECUTION: BLOCKED
NATURAL_LANGUAGE_INTERPRETATION: PASS
CLINICAL_QUESTION_MAPPING: PASS
PATIENT_CONFIRMATION: PASS
UNCONFIRMED_WRITES: ZERO
LEGACY_MODULES_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
BROWSER_E2E: PENDING
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

## Fase 6.7T — diagnóstico focal del transporte Gemini (2026-10-12)

Esta fase no realizó llamadas nuevas a Gemini ni solicitudes de diagnóstico a
la API. Se revisó el cliente cURL utilizado por la Entrevista Conversacional,
la construcción del endpoint y la conectividad local, y se reprodujeron los
fallos únicamente mediante proveedores simulados. La configuración habitual
de la aplicación no fue modificada.

El cliente usa `POST` sobre el endpoint oficial construido como
`https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`.
El modelo efectivo proviene del valor por defecto del proveedor porque no hay
override `GEMINI_MODEL` en `.env`; el proveedor conserva `gemini-3.8-flash`.
La solicitud usa `responseMimeType=application/json` y `responseSchema`,
`CURLOPT_CONNECTTIMEOUT=5`, `CURLOPT_TIMEOUT=20` y validación TLS normal.
La API key sólo se lee en el proceso y nunca se imprime ni se incorpora al
trace.

La resolución DNS local fue satisfactoria (se observaron registros A), pero
la comprobación TCP local hacia `generativelanguage.googleapis.com:443` no fue
alcanzable. cURL está habilitado (8.21.0), con HTTPS/IPv6/SSL y OpenSSL
3.0.21; el CA configurado existe y es legible; no se detectaron variables de
proxy del proceso y WinHTTP está en conexión directa. Esto demuestra un
bloqueo de conectividad en la ruta TCP 443 del entorno actual, pero no permite
atribuir técnicamente la causa inferior a firewall, routing o política de red.

Como corrección focal de observabilidad, `GeminiProvider` conserva ahora sólo
el número cURL en un código interno `GEMINI_TRANSPORT_CURL_<n>`, sin conservar
el texto potencialmente sensible del error. El orquestador lo clasifica en el
trace opt-in como DNS, conexión, timeout, TLS, proxy o transporte genérico.
El comportamiento clínico no cambia: no se confirma, guarda ni avanza una
respuesta cuando el proveedor falla y se usa el fallback determinista.

```yaml
PHASE: 6.7T
SCOPE: CONVERSATIONAL_INTERVIEW_ONLY
REAL_GEMINI_CALLS_ADDED: 0
CURL_EXTENSION: ENABLED
CURL_VERSION: 8.21.0
TLS_BACKEND: "OpenSSL 3.0.21"
CA_CONFIGURATION: "Configured and readable; TLS verification was not disabled"
PROXY_CONFIGURATION: "No process proxy variables; WinHTTP direct"
ENDPOINT_CONSTRUCTION: "https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent"
ENDPOINT_QUERY_OR_USERINFO: NONE
MODEL_CONFIGURATION: "gemini-3.8-flash default; no GEMINI_MODEL override in .env"
API_KEY_PRESENCE: VERIFIED_BOOLEAN_ONLY
CONNECT_TIMEOUT: 5s
TOTAL_TIMEOUT: 20s
CURLOPT_RETURNTRANSFER: true
CURLOPT_MAXFILESIZE: "65536 bytes for conversation requests; 262144 bytes for summary requests"
DNS_RESOLUTION: PASS
TCP_443_REACHABILITY: FAIL_LOCAL_ENVIRONMENT
TRANSPORT_ERROR_DIAGNOSTICS: "Sanitized cURL errno/category added; raw cURL text is not retained"
SUCCESSFUL_VS_FAILED_ENVIRONMENT_DIFF: "Same application provider path; historical success is not reproducible here; current TCP 443 reachability is the demonstrated difference"
ROOT_CAUSE: "Outbound TCP 443 to generativelanguage.googleapis.com is unreachable from the current environment"
ROOT_CAUSE_PRECISION: "HIGH for local TCP block; firewall/routing/proxy layer not identified"
M03_TRANSPORT_REPRODUCTION: "PASS_SIMULATED_CONNECTION_ERROR; deterministic fallback; no write"
M06_TRANSPORT_REPRODUCTION: "PASS_SIMULATED_TIMEOUT_ERROR; deterministic fallback; no write"
M02_REGRESSION: PASS
M03_REGRESSION: PASS
M06_REGRESSION: PASS
LOCAL_SIMULATED_TRANSPORT_TESTS: PASS
UNCONFIRMED_WRITES: ZERO
SYNTHETIC_FIXTURE_INTEGRITY: PASS_RESTORED
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
PATIENT_GEMINI_ENABLED: NO
LEGACY_FORMS_MODIFIED: NO
ENV_MODIFIED: NO
INFRASTRUCTURE_CHANGE_APPLIED: NO
CORRECTION_IMPLEMENTED: "Sanitized cURL errno classification and opt-in trace categories only"
EXTERNAL_CONFIGURATION_CHANGE_REQUIRED: YES
NEW_REAL_GEMINI_TEST_REQUIRED: YES
FINAL_STATUS: BLOCKED_INFRASTRUCTURE
NEXT_ACTION: "Resolve or verify outbound TCP 443 outside the application, then obtain separate authorization before exactly one new M03 and one new M06 call"
```

## Fase 6.7C — preparación desde Windows/PHP local (2026-10-12)

El propietario verificó fuera del contexto restringido de Codex que Windows y
PHP CLI pueden abrir HTTPS hacia dominios de control y hacia
`generativelanguage.googleapis.com` (`CURL_ERRNO=0`; la raíz devuelve HTTP
404, que confirma transporte sin ejecutar `generateContent`). No se repitieron
esas pruebas ni se llamó a Gemini en esta fase.

La diferencia de ejecución quedó identificada: `where php` coloca primero
`C:\tools\php84\php.exe` (PHP 8.4.24), mientras que la prueba exitosa usa
explícitamente `C:\xampp\php\php.exe` (PHP 8.2.12). El arnés existente usa
`PHP_BINARY` para levantar su servidor local, por lo que el intérprete depende
del PHP que invoque el propietario.

Se añadió un lanzador de pruebas en
`tests/patient-interview/run-gemini-m03-m06-safe.ps1`. Por defecto ejecuta sólo
preflight, fuerza la base sintética mediante el arnés existente, comprueba el
paciente 9302, verifica la base efectiva y exige que el flag externo habitual
permanezca deshabilitado. El modo real está bloqueado salvo `-AllowRealCalls`
y la confirmación literal de dos llamadas sintéticas; cuando se autorice,
invoca el arnés existente en `--verify-only`, sin confirmaciones clínicas ni
escrituras. El puerto de prueba se selecciona libremente para no compartir el
listener sintético preexistente en 8120. No contiene credenciales.

El preflight directo con `C:\xampp\php\php.exe` confirmó HTTP local de entorno
sintético, `SELECT DATABASE() = clinica_dolor_browser_20261012`, paciente 9302
y cero llamadas reales. No se modificó `.env`, `bd_clinica` ni el paciente 310.

```yaml
PHASE: 6.7C
WINDOWS_TCP_443: PASS_OWNER_VERIFIED
WINDOWS_PHP_HTTPS: PASS_OWNER_VERIFIED
CODEX_EXECUTION_NETWORK: RESTRICTED_CONTEXT_DIFFERENT_FROM_OWNER_POWERSHELL
PHP_EXECUTION_CONTEXT_DIFFERENCE: "PATH used C:\\tools\\php84\\php.exe; owner uses C:\\xampp\\php\\php.exe"
SYNTHETIC_LAUNCHER_READY: YES_PREFLIGHT_DEFAULT_REAL_BLOCKED
SYNTHETIC_DATABASE_GUARD: PASS
PATIENT_9302_GUARD: PASS
HABITUAL_GEMINI_EXTERNAL_DISABLED: PASS
REAL_GEMINI_CALLS_ADDED: 0
ENV_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
APPLICATION_CODE_MODIFIED: NO
TEST_HARNESS_ONLY_FILES_MODIFIED: "tests/patient-interview/gemini-m03-m06-http.php; tests/patient-interview/run-gemini-m03-m06-safe.ps1"
NEXT_ACTION: "Desde PowerShell normal ejecutar el preflight; después de nueva autorización, usar el modo Real con sus dos confirmaciones explícitas"
```

## Addendum Fase 6.7 — cobertura generativa sintética M03 y M06 (2026-10-12)

Esta entrega amplió exclusivamente el orquestador de la Entrevista
Conversacional para que M06 utilice el mismo límite `ConversationalProvider`
que M02/M03. El adaptador M06 determinista continúa siendo el fallback y el
endpoint `capturar-m06` continúa siendo la única ruta de persistencia. La
interfaz conserva el contexto de los cinco campos existentes
(`descripcion`, `medicamento`, `tiempo_uso`, `dosis`, `medico`) entre turnos;
no se modificaron formularios tradicionales, modelos compartidos, catálogos
ni contratos SQL.

La cobertura simulada pasó para M03 negativo, positivo con dependencias,
histórico, incertidumbre y contradicción; y para M06 completo, incompleto,
multiturno, dosis desconocida y pregunta irrelevante. El contrato focal M03
también pasó `No` seguro sin dependencias, conflicto HTTP 409 con dependencias,
rechazo de versión obsoleta y restauración exacta de la fixture. Estas pruebas
usaron únicamente `clinica_dolor_browser_20261012`, paciente sintético 9302,
y la fixture quedó restaurada.

La sesión HTTP final se ejecutó en un proceso aislado con la base efectiva
`clinica_dolor_browser_20261012`. El endpoint de preflight devolvió flags
sintéticos activos, API key presente sin mostrarla y `SELECT DATABASE()` igual
a la base permitida. Sin embargo, las dos respuestas HTTP de interpretación
de esta fase cayeron al fallback determinista: no incluyeron `provider_meta`,
no fueron aceptadas como `mode=gemini` y no habilitaron confirmación ni
escritura. Hubo seis intentos HTTP externos en esta fase (cuatro intentos de
diagnóstico iniciales y dos de la ejecución final); cero respuestas Gemini
fueron aceptadas por el orquestador. No se repitieron llamadas para forzar un
resultado y no se registra aquí una causa externa no demostrada; el controlador
ocultó el error del proveedor conforme al fallback seguro.

```yaml
SCOPE: CONVERSATIONAL_INTERVIEW_ONLY
M03_SIMULATED_COVERAGE: PASS
M03_NEGATIVE_INTERPRETATION: PASS_NO_FALSE_NEGATIVE
M03_POSITIVE_DEPENDENCIES: PASS
M03_PAST_VS_CURRENT: PASS_CLARIFICATION
M03_UNCERTAINTY: PASS_NO_INVENTION
M03_CONTRADICTION_HANDLING: PASS_409_WITH_DEPENDENCIES_NO_DELETE
M03_SAFE_NO_WITHOUT_DEPENDENCIES: PASS_SYNTHETIC
M03_ZERO_UNCONFIRMED_WRITES: PASS
M06_SIMULATED_COVERAGE: PASS
M06_FIVE_FIELD_EXTRACTION: PASS
M06_INCOMPLETE_INFORMATION: PASS_CLARIFICATION
M06_MULTITURN_CONTEXT: PASS
M06_UNKNOWN_DOSE: PASS_NO_INVENTION
M06_IRRELEVANT_QUESTION: PASS_CLARIFICATION
M06_ZERO_UNCONFIRMED_WRITES: PASS
SHARED_GEMINI_ENGINE_REUSED: PASS_EXISTING_CONVERSATIONAL_PROVIDER
CLINICAL_CONTRACT_VALIDATION: PASS_M03_AND_M06_ALLOWLISTS
DETERMINISTIC_MODE_PRESERVED: PASS
FALLBACK_PROTECTION: PASS
HTTP_ENVIRONMENT_PREFLIGHT: PASS_ISOLATED_FLAGS_AND_EFFECTIVE_DATABASE
GEMINI_HTTP_REAL_ATTEMPTS: 6
GEMINI_REAL_M03: FAIL_FALLBACK_USED_NO_PROVIDER_META
GEMINI_REAL_M06: FAIL_FALLBACK_USED_NO_PROVIDER_META
GEMINI_REAL_HTTP_PROVIDER_CALL: NOT_ACCEPTED
GEMINI_MODEL: gemini-3.8-flash
GEMINI_ENDPOINT: https://generativelanguage.googleapis.com/v1beta
GEMINI_LATENCY: NOT_REPORTED_BY_ACCEPTED_PROVIDER_RESPONSE
GEMINI_TOKEN_USAGE: NOT_REPORTED_BY_ACCEPTED_PROVIDER_RESPONSE
M03_HTTP_CONFIRMATION: NOT_VERIFIED_REAL_PROVIDER_PROPOSAL
M06_HTTP_CONFIRMATION: NOT_VERIFIED_REAL_PROVIDER_PROPOSAL
UNCONFIRMED_WRITES: ZERO
SYNTHETIC_FIXTURE_RESTORED: PASS
BD_CLINICA_EXPOSED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
REAL_PATIENT_DATA_SENT: NO
PATIENT_GEMINI_ENABLED: NO
CLINICAL_AI_EXTERNAL_ENABLED_GLOBAL: 0
REAL_PATIENT_DATA_AUTHORIZATION: NOT_APPROVED
LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
BROWSER_E2E: NOT_VERIFIED
PHASE_6_7_STATUS: PARTIAL
FILES_MODIFIED: "app/Services/ConversationalInterviewService.php; app/Services/GeminiProvider.php; app/Controllers/PatientInterviewController.php; app/views/paciente/entrevista.php; tests/patient-interview/gemini-m03-m06-synthetic.php; tests/patient-interview/gemini-m03-m06-http.php; tests/patient-interview/gemini-m03-m06-http-router.php; docs/AI_PATIENT_INTERVIEW/23_CONVERSATIONAL_ENGINE_IMPLEMENTATION.md"
BLOCKERS: "El proveedor real no entregó una propuesta aceptada en HTTP; requiere diagnóstico autorizado separado antes de repetir llamadas. No habilitar datos reales."
NEXT_ACTION: "Revisar el error del proveedor en un arnés seguro sin imprimir payload ni secretos; mantener CLINICAL_AI_EXTERNAL_ENABLED=0 fuera del proceso sintético."
```

## Fase 5.4 — política reutilizable y captura M04

Se confirmó que el estado canónico de M04 ya existe: una fila en
`pac_historia_clinica_finalizar` con `id_modulo=4` y el paciente protege el
módulo; la ausencia de esa fila representa el borrador abierto porque es la
misma fuente usada por `botonFinalizarModulo(4)` y `finalizarModuloP06()`. La
nueva `ClinicalWriteEligibilityPolicy` falla cerrada si no puede leer esa
fuente y queda reutilizable para los siguientes adaptadores.

M04 utiliza ahora la ruta conversacional existente con autenticación,
pertenencia del expediente, CSRF, versión hash del conjunto de cirugías,
transacción y named lock por paciente. La persistencia llama al modelo clínico
existente sin modificarlo y sólo crea una cirugía después de confirmar la
propuesta. Las respuestas negativas y ambiguas no crean filas.

La prueba HTTP sobre `clinica_dolor_browser_20261012` confirmó captura,
recuperación, 409 por versión obsoleta, 403 por CSRF ausente, 409 por
finalización y 403 entre pacientes/clínicas. La prueba concurrente real produjo
un 200 y un 409, una sola fila nueva y cero duplicados. M04, finalizaciones
temporales y el estado probado se restauraron exactamente al baseline; no se
tocaron `bd_clinica`, el paciente 310 ni M01–M03.

```yaml
CLINICAL_WRITE_ELIGIBILITY_SOURCE: pac_historia_clinica_finalizar_module_4
SHARED_CAPTURE_POLICY: IMPLEMENTED_FAIL_CLOSED
M04_DRAFT_STATE_VERIFIABLE: PASS
M04_FINALIZATION_STATE_VERIFIABLE: PASS
M04_CONCURRENCY_PROTECTION: PASS_NAMED_LOCK_AND_VERSION
M04_PERSISTENCE: PASS_EXISTING_ANTECEDENTES_QUIRURGICOS_MODEL
M04_SYNTHETIC_WRITE_TEST: PASS_HTTP_AND_RESTORED
M04_FUNCTIONAL_CLOSURE: PASS_SYNTHETIC

TRANSVERSAL_CONTRACT_FOR_M05_M09: PREPARED_POLICY_REQUIRES_MODULE_SOURCE
MINIMUM_CHANGE_REQUIRING_APPROVAL: NONE_FOR_M04; EACH_MODULE_NEEDS_OWN_FINALIZATION_SOURCE

GEMINI_SYNTHETIC_TEST_READINESS: READY_DETERMINISTIC_ONLY
GEMINI_ACTIVATION_REQUIREMENTS: EXPLICIT_ISOLATED_AUTHORIZATION_AND_EXTERNAL_FLAG

LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
UNCONFIRMED_WRITES: ZERO

NEXT_ACTION: PREPARE_M05_READ_AND_MODULE_SPECIFIC_WRITE_CONTRACT
```

## Fase 5.3 — experiencia M04 segura y preparación M05

M04 ahora participa en la única pantalla conversacional después de M03. El
adaptador reutiliza la lectura real de `pc_antecedentes_quirurgicos`, reconoce
respuestas positivas, negativas y ambiguas, y prepara propuestas con sólo
`cirugia`, `fecha` explícita cuando existe y `observaciones` para referencias
relativas como “hace cinco años”. No inventa fechas exactas ni crea registros
para una negación.

Se añadió la ruta exclusiva
`/historia-clinica/entrevista/capturar-m04`. Valida sesión Paciente,
pertenencia del expediente y el CSRF del chat, pero responde 409 de forma
intencional: el contrato legado de M04 no ofrece todavía una combinación
verificable de borrador/finalización, CSRF de captura y versión de
concurrencia. No se llamó al modelo compartido ni se escribió ninguna fila.

Se preparó `ConversationalM05Adapter` en modo lectura sobre las tablas
originales de preguntas, respuestas y enfermedades. La prueba sintética de
lectura confirmó que M04 y M05 no modifican registros.

```yaml
M04_CONVERSATIONAL_READ: PASS
M04_NATURAL_LANGUAGE_INTERPRETATION: PASS_DETERMINISTIC
M04_CLARIFICATION: PASS_NO_WRITE
M04_STRUCTURED_PROPOSAL: PASS_REVIEW_ONLY
M04_EXPLICIT_CONFIRMATION: BLOCKED_NO_PERSISTENCE_CONTRACT

M04_DRAFT_CONTRACT: BLOCKED_NOT_VERIFIABLE
M04_FINALIZED_PROTECTION: BLOCKED_BEFORE_WRITE
M04_CHAT_CAPTURE_ENDPOINT: PASS_ROUTE_AUTH_CSRF_THEN_409
M04_SHARED_PERSISTENCE: NOT_ENABLED
M04_CLINICAL_INTEGRITY: PASS_READ_ONLY
M04_CSRF: PASS_ENDPOINT_GATE
M04_AUTHORIZATION: PASS_PATIENT_SCOPE
M04_STALE_VERSION_409: NOT_APPLICABLE_WRITE_BLOCKED
M04_TRUE_CONCURRENT_RACE: NOT_APPLICABLE_WRITE_BLOCKED

M01_M04_CONTINUITY: PASS_REVIEW_ONLY
CHAT_CONTEXT_RESUME: PASS
UNCONFIRMED_WRITES: ZERO

M05_READ_ADAPTER: PASS
M05_CONVERSATIONAL_READINESS: PASS_READ_ONLY

LEGACY_MODULES_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO

M04_FUNCTIONAL_CLOSURE: PARTIAL_WRITE_CONTRACT_PENDING
NEXT_MODULE: M05
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

## Cierre funcional M02 y preparación M04

Se verificó el contrato de borrador sobre `clinica_dolor_browser_20261012` sin
usar `bd_clinica`. En el sistema existente, M02 está abierto cuando no existe
una fila de `pac_historia_clinica_finalizar` para `id_modulo=2` y el paciente;
está finalizado cuando esa fila existe. La autorización se compone de la sesión
Paciente, `assertPatientAccess`, CSRF ligado al JWT y el gate adicional del
adaptador M02. El modelo compartido conserva su contrato original; el adaptador
no permite capturas después de la finalización.

La prueba HTTP real se ejecutó contra el paciente sintético abierto 9302,
clínica sintética 9102, en una instancia temporal aislada de la base
`clinica_dolor_browser_20261012`. Se confirmó interpretación, propuesta,
confirmación y persistencia mediante
`AntecedenteFamiliarModel::editarEnfermedadPaciente()` para diabetes con madre,
hipertensión con padre y una negación. La entrada incompleta `Sí`, la ausencia
de CSRF, el expediente finalizado 9301 y el acceso cruzado fueron rechazados;
no se crearon duplicados y 9301 permaneció idéntico. La evidencia no contiene
PIN, JWT ni valores clínicos.

La prueba de concurrencia por carrera simultánea no se ejecutó; permanece
protegida por `expectedVersion`, pero requiere un escenario concurrente
independiente para declararse verificada.

M04 quedó preparado como adaptador de lectura sobre
`pc_antecedentes_quirurgicos` con campos `id`, `fecha`, `cirugia` y
`observaciones`. Su escritura permanece bloqueada porque el contrato de
finalización y autorización específico de la conversación aún no está
verificado; no se modificó el formulario tradicional.

```yaml
M02_OPEN_DRAFT_CONTRACT: PASS_ABSENCE_OF_M02_FINALIZATION_ROW
M02_OPEN_DRAFT_FIXTURE: PASS_9302_SYNTHETIC_OPEN_9301_UNTOUCHED
M02_POSITIVE_CAPTURE: PASS_DIABETES_AND_HYPERTENSION
M02_NEGATIVE_CAPTURE: PASS
M02_CLARIFICATION: PASS_NO_WRITE
M02_EXPLICIT_CONFIRMATION: PASS
M02_SHARED_PERSISTENCE: PASS_EXISTING_MODEL
M02_EXISTING_ANSWERS_PRESERVED: PASS
M02_FINALIZED_WRITE_BLOCK: PASS_HTTP_409_9301
M02_CSRF: PASS
M02_CONCURRENCY: PASS_TRUE_CONCURRENT_ONE_200_ONE_409
M02_CROSS_CLINIC_AUTHORIZATION: PASS_HTTP_403

M01_M02_M03_CONTINUITY: PASS_PREVIOUS_STATIC_HTTP_EVIDENCE
CHAT_CONTEXT_RESUME: PASS
UNCONFIRMED_WRITES: ZERO

M04_CONTRACT_IDENTIFIED: PASS_READ_CONTRACT
M04_ADAPTER_READINESS: READ_ONLY_PASS_WRITE_BLOCKED

LEGACY_MODULES_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO

M02_FUNCTIONAL_CLOSURE: PASS
NEXT_MODULE: M04
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

## Fase 5.2 — concurrencia M02 y decisión de escritura M04

Se ejecutaron dos procesos independientes contra el mismo antecedente
sintético abierto 9302 y la misma versión esperada. El resultado fue una única
confirmación HTTP lógica exitosa y una segunda respuesta 409 por versión
obsoleta; no hubo duplicados. El antecedente probado se restauró exactamente al
estado previo y no se tocó 9301, 310 ni `bd_clinica`. El adaptador M02 ahora
serializa por paciente/enfermedad con un named lock MySQL y vuelve a leer el
estado y la versión después de adquirirlo. El modelo clínico compartido no fue
modificado.

M04 fue inspeccionado sobre `AntecedentesQuirurgicos`, sus tres operaciones
existentes y `pac_historia_clinica_finalizar` para módulo 4. La lectura y los
campos reales están verificados, pero la captura conversacional no se habilita:
los endpoints tradicionales de alta/edición no validan el CSRF conversacional,
no aplican un bloqueo de finalización antes de escribir y no ofrecen una
versión de concurrencia. La negativa “sin cirugía” tampoco tiene una
representación persistida específica; finalizar el módulo es una operación
distinta. Para no inventar un contrato ni modificar modelos compartidos, M04
permanece sólo lectura.

```yaml
M02_STALE_VERSION_409: PASS
M02_TRUE_CONCURRENT_RACE: PASS_ONE_200_ONE_409
M02_FINALIZED_PROTECTION: PASS

M04_CONVERSATIONAL_ADAPTER: PASS_READ_ONLY
M04_CLINICAL_READ: PASS
M04_NATURAL_LANGUAGE_INTERPRETATION: NOT_IMPLEMENTED_WRITE_BLOCKED
M04_STRUCTURED_PROPOSAL: NOT_IMPLEMENTED_WRITE_BLOCKED
M04_EXPLICIT_CONFIRMATION: NOT_IMPLEMENTED_WRITE_BLOCKED
M04_WRITE_CONTRACT: BLOCKED_NO_CSRF_FINALIZATION_OR_VERSION_GATE
M04_SHARED_PERSISTENCE: NOT_ENABLED
M04_CLINICAL_INTEGRITY: PASS_READ_ONLY
M04_FINALIZED_PROTECTION: NOT_VERIFIED_FOR_CONVERSATIONAL_WRITE
M04_CSRF: BLOCKED_LEGACY_ENDPOINT_HAS_NO_CONVERSATIONAL_GATE
M04_AUTHORIZATION: PASS_EXISTING_PATIENT_SCOPE_ONLY
M04_CONCURRENCY: BLOCKED_NO_LEGACY_VERSION_CONTRACT

M01_M04_CONTINUITY: PASS_READ_ONLY
CHAT_CONTEXT_RESUME: PASS
UNCONFIRMED_WRITES: ZERO

LEGACY_MODULES_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO

M04_FUNCTIONAL_CLOSURE: BLOCKED_WRITE_CONTRACT
NEXT_MODULE: M05
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

La ejecución real de Gemini no se realizó porque la integración externa permanece
deshabilitada. El piloto verificado usa el fallback determinista y no debe
presentarse como una respuesta generada por Gemini.

## Entrega de interfaz funcional

La pantalla ahora consume el intérprete antes de cualquier captura, muestra una
propuesta editable con acciones explícitas de confirmar o corregir, y presenta
aclaraciones o explicaciones sin guardar. Al regresar, las respuestas anteriores
se muestran como confirmadas y el flujo comienza en la primera pregunta pendiente;
no exige reconfirmarlas.

La continuidad M01–M09 queda preparada como una orquestación por adaptadores:
`módulo → catálogo/pregunta → respuesta existente → dependencia → propuesta →
captura autorizada`. M01 ya cuenta con adaptador de lectura; no se implementaron
escrituras adicionales ni se seleccionó un módulo nuevo para escritura.

## Fase 5 — continuidad M01 → M03

M01 se integró como adaptador de lectura sobre `pc_paciente`. Presenta datos de
identificación clínicamente pertinentes con estado `read_only`; no expone campos
de contacto o credenciales al proveedor externo y no tiene método de escritura.
La conversación continúa después en M03, que conserva su intérprete, CSRF,
concurrencia y persistencia autorizada.

La escritura M01 queda bloqueada hasta contar con un contrato específico de
autorización, finalización, dependencias y persistencia.

## Estado de Fase 5

```yaml
M01_CONVERSATIONAL_ADAPTER: IMPLEMENTED_READ_ONLY
M01_CLINICAL_READ: PASS
M01_NATURAL_LANGUAGE_INTERPRETATION: PASS_READ_ONLY
M01_WRITE_INTEGRATION: BLOCKED_PENDING_CONTRACT
M01_M03_CONTINUITY: PASS
M01_M02_M03_CONTINUITY: PASS_READ_ONLY_M02
CHAT_CONTEXT_RESUME: PASS
M01_M09_ORCHESTRATION: PREPARED_BY_ADAPTER_CONTRACT
GEMINI_ADAPTER: IMPLEMENTED
REAL_GEMINI_SYNTHETIC_TEST: NOT_EXECUTED
GEMINI_BLOCKING_REQUIREMENTS: "CLINICAL_AI_SYNTHETIC_MODE=0; CLINICAL_AI_EXTERNAL_ENABLED=0; no isolated authorization active"
UNCONFIRMED_WRITES: ZERO
LEGACY_MODULES_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
CLINICA_TEST_UI: READY_FOR_AUTHENTICATED_REVIEW
NEXT_MODULE_TO_IMPLEMENT: M02_AFTER_CONTRACT_VERIFICATION
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

La prueba HTTP sintética confirmó lectura M01, propuesta M01 con
`write_enabled=false`, continuidad visual M01→M02→M03 y fixture sin cambios.

## Integración M02

M02 utiliza el adaptador `ConversationalM02Adapter` sobre
`pc_antecedentes_familiares`, conserva las cinco enfermedades fijas del modelo
existente y representa por separado enfermedad, respuesta, parentesco y tipo de
diabetes. Las respuestas ambiguas solicitan aclaración; no se transforma una
ausencia en negación.

La persistencia conversacional M02 está guardada detrás de un gate de
finalización: para un expediente finalizado, como el sintético 9301, devuelve
HTTP 409 y no llama al modelo de persistencia. En un expediente abierto, el
adaptador reutiliza `AntecedenteFamiliarModel::editarEnfermedadPaciente()` dentro
de una transacción, valida enfermedad, detalle, parentesco y tipo de diabetes,
y comprueba la versión esperada para concurrencia.

```yaml
M02_CONVERSATIONAL_ADAPTER: IMPLEMENTED
M02_CLINICAL_READ: PASS
M02_NATURAL_LANGUAGE_INTERPRETATION: PASS
M02_WRITE_INTEGRATION: GUARDED_BY_FINALIZATION
M02_CONFIRMATION: PASS
M02_PERSISTENCE_INTEGRITY: BLOCKED_FOR_FINALIZED_9301
M01_M02_M03_CONTINUITY: PASS
UNCONFIRMED_WRITES: ZERO
GEMINI_SYNTHETIC_EXECUTION: NOT_EXECUTED
PATIENT_310_MODIFIED: NO
```

## Fase 5.5 — captura M05 y prueba Gemini sintética aislada (2026-10-12)

Se verificó el contrato clínico existente de M05. El módulo mantiene dos dominios
distintos: preguntas V1 (`pac_respuestas_paciente_modulo_5`) y enfermedades V2
(`pac_enfermedades_respuestas_modulo_5`). La captura conversacional implementada
se limita deliberadamente a V1, reutiliza `AntecedentesPatologicosModel` y no
modifica modelos compartidos, formularios tradicionales ni el dominio V2. V2
queda bloqueado con `M05_V2_REQUIRES_SEPARATE_CONTRACT` hasta definir sus campos,
dependencias y reglas de confirmación.

Las pruebas usaron únicamente `clinica_dolor_browser_20261012`, paciente
sintético 9302 y texto sintético. La operación de captura se revirtió al estado
inicial dentro de la propia prueba; no se tocó `bd_clinica` ni el paciente 310.

```yaml
M05_CLINICAL_CONTRACT: VERIFIED_V1_QUESTIONS_V2_SEPARATE
M05_CONVERSATIONAL_ADAPTER: IMPLEMENTED_V1_READ_INTERPRET_CAPTURE
M05_NATURAL_LANGUAGE_INTERPRETATION: PASS_DETERMINISTIC_BY_CATALOG_TYPE
M05_STRUCTURED_PROPOSAL: PASS_EDITABLE_V1_ONLY
M05_EXPLICIT_CONFIRMATION: PASS
M05_WRITE_ELIGIBILITY: PASS_FAIL_CLOSED_MODULE_5_FINALIZATION
M05_SHARED_PERSISTENCE: PASS_EXISTING_ANTECEDENTES_PATOLOGICOS_MODEL
M05_SYNTHETIC_WRITE_TEST: PASS_READBACK_RESTORE
M05_FINALIZED_PROTECTION: PASS_HTTP_409_POLICY
M05_CSRF: PASS_CONTROLLER_GATE
M05_AUTHORIZATION: PASS_CONTROLLER_PATIENT_ACCESS_GATE
M05_STALE_VERSION_409: PASS
M05_TRUE_CONCURRENT_RACE: PASS_ONE_200_ONE_409_NO_DUPLICATE
M05_FUNCTIONAL_CLOSURE: PARTIAL_V1_ONLY_V2_BLOCKED

M01_M05_CONTINUITY: PASS_ADAPTER_CHAIN_PREPARED
CHAT_CONTEXT_RESUME: PASS_PENDING_QUESTION_READBACK
UNCONFIRMED_WRITES: ZERO

GEMINI_REAL_SYNTHETIC_CALL: PASS_ONE_ISOLATED_REQUEST
GEMINI_RESPONSE_VALIDATION: PASS_STRUCTURED_VALUE_INTERPRETATION_CLARIFICATION
GEMINI_NO_DATABASE_ACCESS: PASS_PROCESS_DID_NOT_CONNECT_TO_DATABASE
GEMINI_ACTIVATION_BLOCKER: GLOBAL_EXTERNAL_FLAG_REMAINS_DISABLED
GEMINI_AUTHORIZATION_REQUIRED: ISOLATED_SYNTHETIC_REQUEST_AUTHORIZED_FOR_THIS_TEST_ONLY
GEMINI_MODEL: gemini-3.8-flash
GEMINI_ENDPOINT: https://generativelanguage.googleapis.com/v1beta
GEMINI_LATENCY_MS: 8004.4
GEMINI_USAGE_METADATA: NOT_RETURNED_BY_CURRENT_PROVIDER

M06_CONTRACT_READINESS: NOT_STARTED_READ_ONLY_CONTRACT_REQUIRED

LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO

CLINICA_TEST_UI_READY: YES_M05_CARD_AND_ENDPOINT_PRESENT
NEXT_ACTION: DEFINE_AND_APPROVE_M05_V2_CONTRACT_BEFORE_DISEASE_CAPTURE
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

El arnés real aislado (`tests/patient-interview/gemini-synthetic-real.php`)
envió solamente la pregunta sintética «¿Actualmente fuma?» y la respuesta
sintética «Dejé de fumar hace dos años». No se imprimió la respuesta textual ni
la credencial; la respuesta estructurada contenía los tres campos requeridos y
la latencia medida fue 8004.4 ms. El proveedor actual no expone `usageMetadata`,
por lo que no se estiman tokens ni coste.

La bandera global `CLINICAL_AI_EXTERNAL_ENABLED=0` permaneció sin cambios; la
llamada se realizó exclusivamente desde el proceso de prueba y no habilita
Gemini para `clinica.test` ni para pacientes reales. La prueba no constituye
autorización para datos reales.

## Fase 5.6 — M05 V2 y avance M06 (2026-10-12)

Se verificó el contrato tradicional de M05 V2 sin modificarlo. El catálogo de
enfermedades mantiene una respuesta `Si/No`; únicamente `Diabetes Mellitus` y
`Enfermedad pulmonar` exponen los dependientes `tipo` y `year_diagnostico`.
Los tipos aceptados son `Tipo 1`, `Tipo 2`, `Gestacional`, `EPOC` y `Asma`.
La captura conversacional valida esos valores, exige los dependientes para una
afirmación positiva y sólo limpia dependientes ante una respuesta negativa
confirmada mediante el mecanismo clínico existente.

M06 se incorporó sobre `pac_medicacion_actual_modulo_6`. El contrato exige los
cinco campos existentes: nombre/descripcion, motivo de uso, tiempo, dosis y
médico. La captura crea un registro y lo completa dentro de una transacción
focal; las lecturas y propuestas no escriben.

```yaml
M05_V2_CONTRACT: VERIFIED_CATALOG_AND_DEPENDENCIES
M05_V2_INTERPRETATION: PASS_SI_NO_TYPE_YEAR
M05_V2_DEPENDENCY_HANDLING: PASS_FAIL_CLOSED
M05_V2_STRUCTURED_PROPOSAL: PASS
M05_V2_CONFIRMATION: PASS
M05_V2_SHARED_PERSISTENCE: PASS_EXISTING_MODEL
M05_V2_SYNTHETIC_WRITE: PASS_READBACK_RESTORE
M05_V2_INTEGRITY: PASS
M05_V2_FINALIZATION: PASS_POLICY_PATH
M05_V2_CONCURRENCY: PASS_STALE_VERSION
M05_V2_FUNCTIONAL_CLOSURE: PASS_SYNTHETIC_CONTRACT_SCOPE

M06_CONTRACT: VERIFIED_PAC_MEDICACION_ACTUAL_MODULO_6
M06_CONVERSATIONAL_ADAPTER: IMPLEMENTED_READ_INTERPRET_CAPTURE
M06_NATURAL_INTERPRETATION: PASS_FIVE_REQUIRED_FIELDS
M06_CONFIRMATION: PASS
M06_SHARED_PERSISTENCE: PASS_EXISTING_MEDICATION_MODEL
M06_SYNTHETIC_CAPTURE: PASS_READBACK_RESTORE
M06_FUNCTIONAL_CLOSURE: PASS_SYNTHETIC_CONTRACT_SCOPE

M01_M06_CONTINUITY: PASS_ADAPTER_CHAIN
CHAT_CONTEXT_RESUME: PASS
UNCONFIRMED_WRITES: ZERO

GEMINI_REAL_SYNTHETIC_CALL: PASS_PREVIOUS_EVIDENCE
GEMINI_PATIENT_CHAT_ENABLED: NO
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED

LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO

NEXT_MODULE: M07_CONTRACT_INSPECTION
BLOCKING_CLINICAL_DECISIONS: NONE_FOR_VERIFIED_SCOPE
```

Las pruebas focalizadas usaron exclusivamente `clinica_dolor_browser_20261012`
y el paciente sintético 9302. M05 V2 verificó propuesta, persistencia,
lectura posterior, restauración y versión obsoleta `409`; M06 verificó los cinco
campos, persistencia, lectura posterior y restauración. No se habilitó Gemini ni
se modificó `.env`, `bd_clinica` o el paciente 310.

## Fase 5.7 — inventario real M01–M09 y brechas de cobertura (2026-10-12)

La inspección focalizada de la base sintética y de los modelos tradicionales no
habilitó escrituras nuevas en M07–M09. Se conservaron sus contratos porque hay
brechas que requieren datos oficiales o coordinación clínica antes de construir
propuestas conversacionales.

```yaml
M07_CONTRACT: BLOCKED_CATALOG_QUESTION_TEXT_MISSING
M07_INTERPRETATION: NOT_IMPLEMENTED_WITHOUT_OFFICIAL_PROMPTS
M07_CONFIRMATION: NOT_VERIFIED
M07_PERSISTENCE: EXISTING_MODEL_DEPENDENCIES_TYPES_1_TO_5
M07_SYNTHETIC_TEST: NOT_EXECUTED
M08_CONTRACT: PARTIAL_TREATMENT_AND_PROCEDURE_DOMAINS
M08_INTERPRETATION: NOT_IMPLEMENTED
M08_CONFIRMATION: NOT_VERIFIED
M08_PERSISTENCE: EXISTING_TREATMENT_AND_PROCEDURE_PATHS
M08_SYNTHETIC_TEST: NOT_EXECUTED
M09_CONTRACT: QUESTIONNAIRE_PLUS_BODY_MAP
M09_PAIN_REGION_VALIDATION: BLOCKED_MAP_SELECTION_CONTRACT
M09_INTERPRETATION: NOT_IMPLEMENTED
M09_CONFIRMATION: NOT_VERIFIED
M09_PERSISTENCE: EXISTING_EVALUATION_SERVICE
M09_SYNTHETIC_TEST: NOT_EXECUTED

M01_M09_NAVIGATION: PARTIAL_M01_M06
CHAT_CONTEXT_RESUME: PASS_M01_M06
UNCONFIRMED_WRITES: ZERO
M01_TOTAL_QUESTIONS: REGISTRATION_FIELDS_NOT_QUESTIONNAIRE
M01_CONVERSATIONAL_COVERAGE: READ_ONLY
M02_TOTAL_QUESTIONS: CATALOG_NOT_PRESENT_IN_FIXTURE
M02_CONVERSATIONAL_COVERAGE: EXISTING_ADAPTER_CONTRACT
M03_TOTAL_QUESTIONS: 25_TABLE_TOTAL;5_TEMA_1
M03_CONVERSATIONAL_COVERAGE: 5_TEMA_1_TOBACCO_PILOT_ONLY
M04_TOTAL_QUESTIONS: RECORD_CONTRACT_NOT_FIXED_COUNT
M04_CONVERSATIONAL_COVERAGE: SURGERY_RECORD_CAPTURE
M05_TOTAL_QUESTIONS: 2_V1_QUESTIONS_PLUS_2_V2_DISEASES
M05_CONVERSATIONAL_COVERAGE: V1_AND_V2_PASS_SYNTHETIC
M06_TOTAL_QUESTIONS: 5_FIELDS_PER_MEDICATION_RECORD
M06_CONVERSATIONAL_COVERAGE: FIVE_FIELD_RECORD_CAPTURE
M07_TOTAL_QUESTIONS: 46
M07_CONVERSATIONAL_COVERAGE: 0_PROMPTS_MISSING
M08_TOTAL_QUESTIONS: 10_TREATMENTS_PLUS_PROCEDURES
M08_CONVERSATIONAL_COVERAGE: 0
M09_TOTAL_QUESTIONS: 4_EVALUATION_FIELDS_PLUS_BODY_MAP
M09_CONVERSATIONAL_COVERAGE: 0
FULL_CLINICAL_HISTORY_CONVERSATIONAL_COVERAGE: PARTIAL_M01_M06
REMAINING_CLINICAL_GAPS: M03_NON_TOBACCO;M07_PROMPTS;M08_DOMAINS;M09_BODY_MAP
BLOCKING_DECISIONS: OFFICIAL_M07_PROMPTS;M09_REGION_SEMANTICS;M08_UNIFIED_SCOPE
GEMINI_SYNTHETIC_CALL: PASS_PREVIOUS_EVIDENCE
GEMINI_PATIENT_ENABLED: NO
LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
NEXT_ACTION: PROVIDE_OFFICIAL_M07_CATALOG_AND_APPROVE_M08_M09_CONTRACTS
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

M07 contiene 46 filas en `pac_preguntas_modulo_7`, pero el entorno sintético no
contiene texto oficial de pregunta. M08 contiene 10 tratamientos catalogados y
procedimientos independientes. M09 persiste cuatro campos en
`pc_paciente_evaluacion_dolor`, mientras la localización anatómica usa el mapa
corporal existente. Sin esos contratos, interpretar texto libre introduciría
datos no sustentados.

## Addendum — recuperación de contratos oficiales M03/M07–M09 (2026-10-12)

La revisión solicitada se cerró sin escrituras clínicas, sin cambios en los
formularios tradicionales y sin habilitar Gemini.

### M03

El modelo tradicional confirma 25 preguntas en la tabla M03. El contrato
aprobado en `02_ARCHITECTURE_AND_CAPTURE_CONTRACT.md` limita la entrevista a
tema 1/Tabaquismo, preguntas 1–5. Las preguntas restantes pertenecen a temas
que el plan MVP dejó diferidos; no existe en esta revisión una autorización
clínica ni una matriz de dependencias que permita convertirlas en captura
conversacional segura. Por ello no se amplió artificialmente el flujo ni se
alteró `AntecedentesNoPatologicosModel` para interpretar preguntas fuera del
alcance aprobado.

```yaml
M03_OFFICIAL_QUESTIONS: 25
M03_INTERPRETATION_COVERAGE: 5/25_TEMA_1_TABAQUISMO
M03_WRITE_COVERAGE: 5/25_USANDO_CONTRATO_M03_EXISTENTE
M03_SYNTHETIC_TEST: PASS_PREVIOUS_TEMA_1_CONTRACT
M03_REMAINING_GAPS: 20_QUESTIONS_WITHOUT_APPROVED_CONVERSATIONAL_DEPENDENCY_MATRIX
```

### M07

La fuente oficial recuperada es el procedimiento controlado
`tests/p09/import-catalog.php`, respaldado por `tests/p09/README.md`. El
procedimiento exige un ZIP sanitizado con exactamente seis temas, 46 preguntas,
estructura de respuestas sin INSERT clínicos y base allowlisted. También
comprueba las relaciones y la idempotencia. El ZIP indicado por la
documentación (`P09_CATALOGOS_SEGUROS.zip`) no está en el repositorio ni fue
localizado entre los archivos del proyecto; no se fabricaron textos de
pregunta ni se importó un catálogo en ninguna base.

```yaml
M07_OFFICIAL_QUESTIONS_FOUND: 46_EXPECTED_BY_AUTHORIZED_IMPORTER
M07_OFFICIAL_QUESTIONS_MISSING: 0_SCHEMA_ROWS;PACKAGE_ARCHIVE_NOT_AVAILABLE_LOCALLY
M07_CATALOG_SOURCE: tests/p09/import-catalog.php + tests/p09/README.md
M07_SYNTHETIC_CATALOG_READY: NO_ARCHIVE_NOT_PRESENT
M07_CONVERSATIONAL_CAPTURE: NOT_IMPLEMENTED_PENDING_SANITIZED_PACKAGE_AND_UI_CONTRACT
```

El modelo tradicional sí conserva validaciones de tipos 1–5 y dependencias,
pero eso no sustituye el texto oficial de las 46 preguntas ni autoriza una
interpretación conversacional nueva.

### M08

Se verificaron dos dominios independientes en `ProcedimientosDolorModel`:
los diez tratamientos del catálogo y los procedimientos registrados en su
tabla propia. Se mantienen separados; no se creó un adaptador que los fusione
ni se reutilizó una tabla para el otro dominio.

```yaml
M08_TREATMENT_ADAPTER: EXISTING_TRADITIONAL_PATH_VERIFIED
M08_PROCEDURE_ADAPTER: EXISTING_TRADITIONAL_PATH_VERIFIED
M08_VERIFIED_WRITE_SCOPE: NONE_NEW_CONVERSATIONAL_WRITES
```

### M09

`EvaluacionDolorModel` conserva los cuatro campos de evaluación
(`dolor`, `tiempo_dolor`, `descripcion`, `incremento`) y el mapa corporal
separado. El mapa requiere selección visual de orientación/color; no existe un
contrato seguro para transformar una frase libre en una región anatómica.

```yaml
M09_EVALUATION_ADAPTER: EXISTING_FOUR_FIELD_SERVICE_VERIFIED
M09_BODY_MAP_INTEGRATION: EXISTING_VISUAL_MAP;TEXT_MAPPING_NOT_AUTHORIZED
M09_VERIFIED_WRITE_SCOPE: NONE_NEW_CONVERSATIONAL_WRITES
```

### Estado consolidado de esta revisión

```yaml
M01_M09_NAVIGATION: PASS_M01_M06_EXISTING_CHAIN;M07_M09_PENDING_APPROVED_ADAPTERS
CHAT_CONTEXT_RESUME: PASS_M01_M06
UNCONFIRMED_WRITES: ZERO

LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO

GEMINI_SYNTHETIC_CALL: PASS_PREVIOUS_EVIDENCE
GEMINI_PATIENT_ENABLED: NO
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED

COMPLETED_FUNCTIONAL_SCOPE: M03_TEMA_1;M04;M05_V1_V2;M06
REMAINING_BLOCKERS: M03_NON_TOBACCO_APPROVAL;M07_SANITIZED_ARCHIVE;M08_CONVERSATIONAL_SCOPE;M09_BODY_MAP_SEMANTICS
OWNER_DECISIONS_REQUIRED: APPROVE_M03_THEMES;PROVIDE_P09_ARCHIVE;APPROVE_M08_M09_CAPTURE_CONTRACTS
```

No se ejecutaron importaciones, arneses de captura ni escrituras sobre
`bd_clinica`, el paciente 310 o los catálogos tradicionales. El siguiente paso
seguro para M07 es disponer del ZIP sanitizado autorizado y ejecutarlo solo
contra una base sintética allowlisted distinta de `bd_clinica`; para M03, M08 y
M09 primero debe quedar aprobado el contrato clínico específico.

## Fase 5.8 — integración vertical M09 (2026-10-12)

Se implementó una integración conversacional limitada a los cuatro campos de
evaluación de M09. El mapa corporal no se interpreta desde texto libre ni se
mezcla con esta captura; permanece en el componente visual existente.

```yaml
M08_TREATMENT_INTERPRETATION: BLOCKED_PENDING_EXPLICIT_DEPENDENCY_CONTRACT
M08_TREATMENT_CONFIRMATION: NOT_IMPLEMENTED
M08_TREATMENT_PERSISTENCE: NOT_ENABLED
M08_TREATMENT_SYNTHETIC_TEST: NOT_EXECUTED
M08_PROCEDURES: PENDING_SEPARATE_CONTRACT

M09_EVALUATION_INTERPRETATION: PASS_DETERMINISTIC_LABELLED_FIELDS
M09_EVALUATION_PERSISTENCE: PASS_EXISTING_p11ActualizarEvaluacion
M09_EVALUATION_SYNTHETIC_TEST: PASS_READBACK_STALE_409_NO_DUPLICATES_RESTORED
M09_EXISTING_BODY_MAP_REUSE: PRESERVED_SEPARATE_VISUAL_COMPONENT
M09_BODY_MAP_DECISION_REQUIRED: YES_TEXT_TO_REGION_MAPPING_NOT_ALLOWED

M03_DEPENDENCY_MATRIX: CREATED_IN_24_M03_DEPENDENCY_DECISION_MATRIX.md
M03_VERIFIED_RULES: SELECT_SI_NO;DEPENDENT_THEME_CLEANUP;SHARED_M03_PERSISTENCE
M03_SPECIFIC_APPROVALS_REQUIRED: YES_FIVE_DECISIONS_DOCUMENTED

M07_OFFICIAL_ZIP_AVAILABLE: NO
M07_REQUIRED_PACKAGE: P09_CATALOGOS_SEGUROS.zip

NEW_FUNCTIONAL_CAPTURE_DELIVERED: M09_FOUR_FIELD_EVALUATION
NEW_FUNCTIONAL_CAPTURE_TESTED: PASS_SYNTHETIC_9302
UNCONFIRMED_WRITES: ZERO

LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO

GEMINI_SYNTHETIC_CALL: PASS_PREVIOUS_EVIDENCE
GEMINI_PATIENT_ENABLED: NO
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED

OWNER_DECISIONS_REQUIRED: M03_MATRIX_APPROVAL;M09_BODY_MAP_UI_APPROVAL;M08_TREATMENT_SCOPE
NEXT_IMPLEMENTATION: M08_TREATMENT_ADAPTER_AFTER_CONTRACT_REVIEW_OR_M09_BODY_MAP_UI_REUSE
```

La nueva ruta conversacional M09 conserva sesión de paciente, autorización,
CSRF, control de versión, bloqueo de concurrencia y política de finalización.
La prueba sintética usó exclusivamente `clinica_dolor_browser_20261012` y
9302; restauró el estado inicial y no produjo duplicados.

## Fase 5.9 — M03 ampliado y revisión del mapa M09 (2026-10-12)

Se añadió el adaptador M03 sobre el catálogo existente. Las 25 preguntas se
leen en orden y se interpretan determinísticamente; las preguntas dependientes
exigen que su pregunta principal tenga respuesta `Si`. Las diez preguntas de
texto dependientes tienen ruta de captura segura. Las cinco preguntas
principales de selección pueden guardar `Si`; `No` permanece bloqueado en la
entrevista cuando podría limpiar respuestas hermanas, para conservar la
integridad del expediente y remitir al formulario tradicional.

```yaml
M09_EVALUATION_FIELDS: PASS_PREVIOUS_EVIDENCE
M09_BODY_MAP_UI: NOT_INTEGRATED_CONTRACT_LIMITATION
M09_BODY_MAP_OFFICIAL_CATALOG: NOT_FOUND
M09_BODY_MAP_CONFIRMATION: NOT_AVAILABLE_FOR_LEGACY_PNG_ENDPOINT
M09_BODY_MAP_PERSISTENCE: EXISTING_PNG_SERVICE_ONLY
M09_BODY_MAP_SYNTHETIC_TEST: NOT_EXECUTED_IN_CHAT
M09_FUNCTIONAL_CLOSURE: PARTIAL_FOUR_FIELDS_ONLY

M03_TOTAL_QUESTIONS: 25
M03_PREVIOUS_COVERAGE: 5
M03_NEW_QUESTIONS_IMPLEMENTED: 20_INTERPRETATION;10_DEPENDENT_TEXT_WRITES;5_PARENT_SI_WRITES
M03_TOTAL_INTERPRETATION_COVERAGE: 25/25
M03_TOTAL_WRITE_COVERAGE: 15/25_SAFE_PATHS;PARENT_NO_PATHS_BLOCKED
M03_DEPENDENCY_TESTS: PASS_PARENT_REQUIRED;PASS_PARENT_NO_BLOCKED
M03_SYNTHETIC_PERSISTENCE: PASS_READBACK_STALE_409_RESTORED
M03_REMAINING_QUESTIONS: 0_INTERPRETATION;5_DESTRUCTIVE_PARENT_NO_PATHS;MAP_M09
M03_OWNER_DECISIONS_REQUIRED: APPROVE_PARENT_NO_CLEANUP_POLICY;M09_REGION_CONTRACT

M01_M09_CHAT_CONTINUITY: PASS_M01_M06_PLUS_M03_M09_CARDS
UNCONFIRMED_WRITES: ZERO

LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO

GEMINI_REAL_SYNTHETIC_CALL: PASS_PREVIOUS_EVIDENCE
GEMINI_PATIENT_ENABLED: NO
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED

NEXT_ACTION: APPROVE_SAFE_POLICY_FOR_PARENT_NO_OR_KEEP_BLOCKED;DEFINE_REGION_IDENTIFIERS_FOR_M09
```

El mapa original no constituye un catálogo de regiones: el componente dibuja
una imagen en canvas y la guarda como PNG mediante endpoints heredados. No se
inventó una lista de regiones ni se presentó una imagen como si fueran
identificadores confirmables. Integrarlo en el chat requiere una decisión
mínima sobre el contrato de regiones persistidas o una aprobación explícita
para reutilizar esos endpoints de imagen con su semántica actual.

## Ejecución correctiva — M03 negativas y M09 PNG (2026-10-12)

M03 ahora distingue los dos escenarios de una respuesta principal `No`:

- Sin respuestas dependientes no vacías: se actualiza únicamente la fila
  principal mediante `editarCuestionarioDato`; no se ejecuta la rutina
  tradicional que limpia hermanas.
- Con respuestas dependientes: se devuelve `409`, se identifican las preguntas
  en conflicto y se solicita el procedimiento clínico autorizado. No se cambia
  ninguna fila.

```yaml
M03_SAFE_NO_IMPLEMENTED: YES
M03_SAFE_NO_SYNTHETIC_WRITE: PASS
M03_CONFLICTING_NO_BLOCKED: PASS_HTTP_409_WITH_CONFLICTS
M03_UNINTENDED_CHANGES: NONE_AFTER_FIXTURE_RESTORE
```

Prueba nueva: `tests/patient-interview/m03-expanded-contract.php`, sobre
`clinica_dolor_browser_20261012` y paciente sintético 9302. Cubrió aclaración
por dependencia, captura padre/hija, `No` seguro, `No` conflictivo, versión
obsoleta y restauración exacta del fixture.

El editor M09 original fue localizado en
`app/views/historia-clinica-paciente/evaluacion-dolor.php`, con renderizado en
`EvaluacionDolorModel::p11MostrarMapa`, guardado PNG en
`EvaluacionDolorModel::p11GuardarImagen` y endpoints en
`ModulosController.php`. No se reutilizó dentro del chat porque el flujo
heredado guarda automáticamente al terminar el trazo, no usa el CSRF de la
entrevista, no expone una versión/concurrencia del PNG y no ofrece una
confirmación previa a escritura.

```yaml
M09_ORIGINAL_EDITOR_FOUND: YES
M09_EDITOR_REUSED_IN_CHAT: NO_CONTRACT_BLOCKED
M09_PNG_PREVIEW: NOT_DELIVERED
M09_CONFIRMATION_BEFORE_WRITE: NOT_AVAILABLE_IN_SHARED_ENDPOINT
M09_PNG_PERSISTENCE: EXISTING_TRADITIONAL_SERVICE_UNCHANGED
M09_PNG_READBACK: EXISTING_TRADITIONAL_ENDPOINT_ONLY
M09_BLOCKER_IF_ANY: APPROVAL_REQUIRED_FOR_MINIMAL_SHARED_ENDPOINT_CONTRACT_CHANGE
```

Cambio mínimo propuesto, pendiente de autorización: añadir un endpoint de
previsualización sin escritura y un endpoint de confirmación en
`PatientInterviewController.php`, con CSRF de entrevista, verificación de
finalización, bloqueo por paciente y versión/hash del PNG; delegar el guardado
final a `EvaluacionDolorModel::p11GuardarImagen` sin modificar el formulario
tradicional. No se aplicó ese cambio en esta ejecución.

## Fase 6.1 — mapa corporal PNG dentro de la entrevista (2026-10-12)

La autorización permitió una capa aditiva exclusiva del chat. Se implementó
`ConversationalM09MapAdapter` sin modificar `EvaluacionDolorModel`,
`ModulosController` ni el formulario tradicional.

El editor usa canvas con las mismas imágenes, orientaciones y colores del
componente original. Dibujar, limpiar, previsualizar, editar y cancelar son
operaciones locales del navegador. La confirmación envía una sola orientación
PNG al nuevo endpoint `/historia-clinica/entrevista/capturar-m09-mapa`.

```yaml
M09_ORIGINAL_EDITOR_REUSED: YES_ASSETS_AND_PNG_SERVICE_CONTRACT
M09_EDITOR_INTERACTIVE: YES_CHAT_CANVAS_FRONT_AND_BACK
M09_ANTERIOR_POSTERIOR_SUPPORT: YES_SEPARATE_CONFIRMATIONS
M09_PNG_PREVIEW: PASS_LOCAL_CANVAS_TO_DATA_URL
M09_PREVIEW_WRITES: ZERO
M09_CANCEL_WRITES: ZERO
M09_EXPLICIT_CONFIRMATION: YES
M09_CHAT_CAPTURE_ENDPOINT: POST_/historia-clinica/entrevista/capturar-m09-mapa
M09_EXISTING_MODEL_REUSED: YES_p11ImagePath_AND_p11GuardarImagen
M09_SYNTHETIC_PERSISTENCE: PASS
M09_PNG_READBACK: PASS_HASHED_STORAGE_READBACK
M09_EXISTING_IMAGE_PRESERVED: PASS_FIXTURE_RESTORED
M09_FINALIZATION_PROTECTION: PASS_403
M09_CSRF: ENFORCED_BY_CHAT_CONTROLLER
M09_CROSS_PATIENT_AUTHORIZATION: ENFORCED_BY_AUTH_MIDDLEWARE
M09_CONCURRENCY: PASS_LOCK_AND_STALE_VERSION_409
M09_FUNCTIONAL_CLOSURE: PASS_SYNTHETIC_CHAT_MAP_LAYER
```

La prueba nueva `tests/patient-interview/m09-map-contract.php` verificó
persistencia PNG, lectura posterior, versión obsoleta, bloqueo por módulo
finalizado y restauración de archivos sintéticos. La previsualización no llama
al servidor.

```yaml
M03_SAFE_NEGATIVES: PASS_PREVIOUS_EVIDENCE
M03_CONFLICTING_NEGATIVES: BLOCKED_BY_DESIGN
SHARED_CONTRACT_CHANGE_REQUIRED: NO
EXACT_CHANGE_REQUIRING_APPROVAL: NONE_FOR_CHAT_LAYER
CLINICA_TEST_VISUAL_READY: PENDING_MANUAL_BROWSER_REVIEW
```

## Fase 6.2 — captura conversacional M08 (2026-10-12)

Se añadió `ConversationalM08TreatmentAdapter` y una capa exclusiva de la
entrevista para el catálogo real de diez tratamientos. La lectura no siembra
filas. La interpretación sólo acepta respuestas expresas (`No` o una
respuesta etiquetada con `utilizo`, `resultado`, `descripcion` y, cuando está
disponible, `comentarios`); no convierte ausencia de información en negativa
ni infiere tratamientos. La confirmación utiliza `p10EditarTratamiento`, con
CSRF en el controlador, autorización de paciente, estado de finalización,
bloqueo por tratamiento y versión obsoleta.

```yaml
M08_TREATMENT_CATALOG: 10_ITEMS_READ_FROM_EXISTING_CATALOG
M08_TREATMENT_INTERPRETATION: DETERMINISTIC_EXPLICIT_FIELDS_ONLY
M08_CONFIRMATION_BEFORE_WRITE: YES
M08_EXISTING_SERVICE_REUSED: YES_p10EditarTratamiento
M08_READBACK: PASS
M08_STALE_VERSION: PASS_409
M08_FINALIZATION_AND_AUTH: ENFORCED
M08_PROCEDURES: BLOCKED_BY_EXISTING_BLANK_ROW_CONTRACT
M08_PROCEDURE_REASON: p10AgregarProcedimiento_CREATES_EMPTY_ROW_BEFORE_FIELD_COMPLETION
M08_SHARED_MODELS_MODIFIED: NO
M08_LEGACY_FORMS_MODIFIED: NO
M08_SYNTHETIC_DATABASE: clinica_dolor_browser_20261012
M08_FIXTURE_RESTORED: YES
```

La prueba `tests/patient-interview/m08-treatment-contract.php` comprobó
aclaración, propuesta sin escritura, confirmación de `No`, lectura posterior,
versión obsoleta y restauración focal de las filas M08 del paciente sintético
9302. La captura de procedimientos queda pendiente de una decisión técnica
que garantice que una confirmación completa no deje filas vacías ni requiera
un cambio inseguro del contrato compartido.

## Fase 6.3 — orquestación de conversación continua (2026-10-12)

La vista de entrevista dejó de presentar todos los módulos simultáneamente.
Se añadió un orquestador exclusivo en `entrevista.php` que organiza las etapas
M02, M03, M04, M05, M05 V2, M06, M07, M08, M09 y mapa M09. La etapa M03 se
reanuda en la primera pregunta sin respuesta; las demás etapas permanecen
ocultas hasta la transición posterior a una confirmación autorizada. M07 se
muestra como bloqueo documental explícito y no genera respuestas ficticias.

La navegación y la interpretación no escriben. La confirmación sigue usando
los endpoints existentes. Se añadió una consulta secundaria de respuestas M03
registradas, sin permitir edición desde ese panel. El mapa M09 conserva su
aparición como etapa separada posterior a la evaluación del dolor.

```yaml
CONVERSATIONAL_ORCHESTRATOR: IMPLEMENTED_IN_INTERVIEW_VIEW
ONE_ACTIVE_QUESTION: YES_FOR_M03_AND_SINGLE_MODULE_STAGE
NATURAL_LANGUAGE_INPUT: PRESERVED
CLARIFICATION_FLOW: PRESERVED
PROPOSAL_REVIEW: PRESERVED
EXPLICIT_CONFIRMATION: PRESERVED
AUTOMATIC_NEXT_QUESTION: YES_AFTER_AUTHORIZED_CONFIRMATION
PREVIOUS_ANSWERS_REVIEW: READ_ONLY_PANEL
CHAT_CONTEXT_RESUME: FIRST_UNANSWERED_M03_QUESTION
REAL_PROGRESS_INDICATOR: CONVERSATION_STAGE_COUNT
M01_M09_SINGLE_CHAT: YES
M07_BLOCKED_STATE_HANDLING: EXPLICIT_PENDING_STATE
M08_TREATMENTS_PRESERVED: YES
M08_PROCEDURES_REMAIN_BLOCKED: YES
M09_MAP_PRESERVED: YES
UNCONFIRMED_WRITES: ZERO
```

## Fase 6.7 — captura rápida M02 para negativas simples (2026-10-12)

Se añadió una única acción explícita, `No, guardar y continuar`, sólo en la
entrevista conversacional M02 y sólo mientras el módulo permite escritura. La
acción no interpreta texto del paciente ni infiere una respuesta vacía: el
controlador construye en servidor una propuesta M02 negativa, valida paciente,
sesión, clínica, CSRF, versión, finalización y dependencias, y reutiliza el
endpoint y el mecanismo de persistencia existentes. La lectura posterior se
realiza antes de avanzar a la siguiente pregunta.

Las respuestas afirmativas, completas, ambiguas o con dependencias conservan
memoria, propuesta, corrección y confirmación separadas. `Responder después`
continúa sin escritura. Durante cualquier captura se bloquean los controles de
la tarjeta y la captura queda protegida por la versión y el bloqueo de
concurrencia existentes; un reenvío obsoleto devuelve 409 sin duplicar filas.

La prueba sintética utilizó únicamente `clinica_dolor_browser_20261012` y un
paciente sintético distinto de 310. La única fila focal se preparó
temporalmente como pendiente, se verificó la negativa, la lectura posterior,
el rechazo de una repetición sobre una respuesta documentada y el rechazo de
datos dependientes persistidos. Después se restauraron exactamente filas,
identificadores y valores originales; no quedaron cambios colaterales.
No se modificaron formularios tradicionales, modelos compartidos, esquemas,
`bd_clinica`, Gemini ni la configuración del entorno.

```yaml
M02_ONE_CLICK_SAFE_NO: PASS_SYNTHETIC
M02_ONE_CLICK_CLINICAL_CONFIRMATION: PASS_EXPLICIT_ACTION
M02_YES_DEPENDENCY_FLOW: PASS_PRESERVED
M02_COMPLETE_NATURAL_RESPONSE: PASS_PRESERVED
M02_AMBIGUOUS_RESPONSE: PASS_NO_WRITE
M02_DEFER_WITHOUT_WRITE: PASS
M02_CONTEXT_MEMORY_PRESERVED: PASS
DANGEROUS_DEPENDENCY_OVERWRITE: BLOCKED_HTTP_409
DUPLICATE_SUBMISSION_PROTECTION: PASS_UI_GUARD_AND_SERVER_LOCK
PENDING_REQUEST_UI: PASS
PERSISTENCE_READBACK: PASS
AUTOMATIC_NEXT_QUESTION: PASS_AFTER_HTTP_200
STALE_VERSION_HANDLING: PASS_HTTP_409
FINALIZATION_PROTECTION: PASS_EXISTING
UNCONFIRMED_WRITES: ZERO
PREVIOUS_M02_ACTION_COUNT: 3
NEW_M02_ACTION_COUNT: 1
CLICKS_REDUCED: 2
VOICE_CONTROLS_PRESERVED: YES
ORCHESTRATOR_PRESERVED: YES
M09_MAP_PRESERVED: YES
LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
SHARED_CONTROLLERS_MODIFIED: NO
DATABASE_SCHEMA_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
GEMINI_PATIENT_ENABLED: NO
SYNTHETIC_TESTS: PASS_FOCAL_M02_AND_REGRESSIONS
CLINICA_TEST_VISUAL_READY: NOT_VERIFIED_SERVER_UNAVAILABLE
BROWSER_ACCEPTANCE: PENDING
```

## Fase 6.5 — navegación M02, micrófono y mensajes (2026-10-12)

Se corrigió únicamente la capa de entrevista. En M02, las acciones rápidas
`Sí` y `No` sólo rellenan el campo; la interpretación y confirmación siguen
siendo obligatorias. Cuando M02 está abierto, `Responder después` avanza sin
escritura y conserva la fila como pendiente. Si el módulo está finalizado, la
acción no se muestra y el flujo mantiene el bloqueo clínico.

El botón de micrófono se agrega sólo al control de respuesta visible. Queda
habilitado únicamente si el navegador expone `SpeechRecognition` con
`processLocally`; de lo contrario se muestra deshabilitado con explicación y
se conserva el teclado. No se envía audio, no se almacenan grabaciones y no se
modifican endpoints de interpretación o persistencia. La comprobación real de
`clinica.test` no pudo completarse porque la URL respondió “No se pudo
completar la solicitud”; por tanto, permisos y soporte local del navegador
quedan sin verificar.

```yaml
M02_NO_CONFIRMATION: PRESERVED
M02_PENDING_VS_NEGATIVE: VERIFIED
M02_DEFER_OPTION: IMPLEMENTED_WHEN_MODULE_OPEN
M02_UNCONFIRMED_WRITES: ZERO
MICROPHONE_VISIBILITY: VISIBLE_ACTIVE_OR_DISABLED_WITH_REASON
BROWSER_SECURE_CONTEXT: NOT_VERIFIED_SERVER_UNAVAILABLE
LOCAL_RECOGNITION_AVAILABLE: NOT_VERIFIED
MICROPHONE_TEST: NOT_VERIFIED
EXTERNAL_AUDIO_TRANSFER: NONE
ACTIVE_MODULE_MESSAGES: CORRECTED
OBSOLETE_READ_ONLY_MESSAGES: CORRECTED_IN_INTERVIEW
ORCHESTRATOR_PRESERVED: YES
```

## Fase 6.6 — memoria temporal de contexto M02 (2026-10-12)

Se corrigió la interpretación progresiva de antecedentes familiares sin crear
tablas ni guardar aclaraciones. `ConversationalM02Adapter::interpret()` ahora
recibe y devuelve un contexto provisional saneado por enfermedad, respuesta
explícita, familiar y tipo de diabetes. El controlador sólo procesa ese
contexto después de autenticar y autorizar al paciente; la vista lo conserva en
la tarjeta M02 activa y lo elimina al confirmar, cancelar, diferir o cambiar de
pregunta.

Se cubren las secuencias familiar→Sí, Sí→familiar y respuesta completa. Un
`No` explícito produce propuesta negativa; “No recuerdo” produce aclaración y
una contradicción familiar→No no se guarda automáticamente. La confirmación
continúa usando `captureM02`, con CSRF, versión, concurrencia y persistencia
existentes.

```yaml
M02_CONTEXT_MEMORY: PASS_TEMPORARY_NO_CLINICAL_WRITE
M02_PARENT_THEN_YES: PASS
M02_YES_THEN_PARENT: PASS
M02_COMPLETE_SENTENCE: PASS
M02_NEGATIVE_RESPONSE: PASS_EXPLICIT_ONLY
M02_CONTRADICTION_HANDLING: PASS_CLARIFICATION
M02_AMBIGUOUS_RESPONSE: PASS_NO_REMEMBER_IS_NOT_NO
M02_CONTEXT_ISOLATION: PASS_ACTIVE_CARD_AND_AUTHENTICATED_PATIENT
M02_CONTEXT_RESET: PASS_CONFIRM_CANCEL_DEFER_OR_CHANGE
M02_STRUCTURED_PROPOSAL: PASS
M02_EXPLICIT_CONFIRMATION: PRESERVED
M02_SYNTHETIC_PERSISTENCE: PASS_EXISTING_CONTRACT
M02_STALE_PROPOSAL_REJECTION: PASS_409_CONCURRENCY
UNCONFIRMED_WRITES: ZERO
```

## Fase 6.4 — interacción local por voz (2026-10-12)

Se añadieron únicamente controles aditivos dentro de `entrevista.php`:
estados locales de hover, foco visible, activo y deshabilitado; lectura manual
de los mensajes activos mediante `speechSynthesis`; y dictado opcional mediante
`SpeechRecognition`. La lectura se cancela al cambiar de etapa o pregunta y el
micrófono detiene cualquier lectura en curso.

El dictado sólo inicia cuando el navegador expone explícitamente la capacidad
`processLocally`. Si no está disponible, no se inicia reconocimiento para
evitar asumir que el audio se procesa localmente; se informa la limitación y
permanece habilitada la entrada escrita. La transcripción, cuando existe,
solamente rellena el campo visible y exige corrección, interpretación y
confirmación del paciente. No se añadió almacenamiento de audio ni logging de
transcripciones.

```yaml
BUTTON_HOVER_FIXED: YES_INTERVIEW_SCOPE_ONLY
BUTTON_TEXT_CONTRAST: PASS_LOCAL_STATES
KEYBOARD_FOCUS: VISIBLE_FOCUS_RING
RESPONSIVE_CONTROLS: 44PX_MINIMUM_TACTILE_TARGET
ASSISTANT_TTS: IMPLEMENTED_MANUAL_LOCAL_BROWSER_API
SPANISH_VOICE: ES_MX_PREFERRED_ES_FALLBACK
TTS_PLAY_STOP: YES
TTS_LOCAL_PROCESSING: BROWSER_SPEECH_SYNTHESIS_ONLY
MICROPHONE_BUTTON: IMPLEMENTED
MIC_PERMISSION_HANDLING: BROWSER_PERMISSION_AND_DENIAL_MESSAGE
SPEECH_TO_TEXT: LOCAL_ONLY_WHEN_EXPLICITLY_SUPPORTED
SPANISH_TRANSCRIPTION: ES_MX_CONFIGURED
ON_DEVICE_RECOGNITION: NOT_VERIFIED_IN_CURRENT_BROWSER
MICROPHONE_PRIVACY_LIMITATIONS: EXTERNAL_RECOGNITION_NOT_STARTED
MANUAL_TEXT_FALLBACK: YES
AUTO_CLINICAL_WRITES: ZERO
ORCHESTRATOR_PRESERVED: YES
M09_MAP_PRESERVED: YES
```

## Fase 6.8 — simplificación UX M02 y versionado de assets (2026-10-12)

Se realizó una corrección focal exclusivamente en `entrevista.php`. M02 ya no
presenta un botón `No` separado junto con la acción negativa persistente: queda
una única acción explícita `No, guardar y continuar` cuando la captura segura
está habilitada. `Sí` conserva la interpretación progresiva para solicitar los
datos relativos o dependientes que falten, sin escritura durante la
interpretación. El texto libre muestra `Revisar respuesta` únicamente cuando
contiene texto; una propuesta completa mantiene la confirmación clínica y una
respuesta cambiada invalida la propuesta anterior sin descartarla
silenciosamente. Además, una selección rápida no reemplaza texto distinto ya
escrito y la negativa rápida se detiene si hay texto o propuesta pendiente;
`Responder después` continúa siendo una acción secundaria
sin escritura cuando el contrato permite diferir.

La página de entrevista versiona por `filemtime` los recursos externos que
realmente carga: Bootstrap, Perfect Scrollbar, `app.css` y el favicon. Los
recursos CSS/JS propios de la entrevista continúan inline; no existe un bundle
externo específico de entrevista que versionar. El HTML autenticado de la
entrevista usa `private, no-store, no-cache` para evitar que el estado clínico
inline quede en cachés. No se encontró registro de Service Worker para esta
ruta y no se modificó la política global de caché.

La verificación HTTP sin sesión confirmó el redireccionamiento seguro hacia
`/historia-clinica/acceso` (302) en `clinica.test` y en el servidor sintético
8098. Los cuatro recursos versionados respondieron 200. El navegador integrado
está disponible, pero la navegación visual terminó en «No se pudo completar la
solicitud» en `/historia-clinica` y no existe una sesión autenticada sintética
reutilizable. No se introdujeron credenciales; por tanto, no se declara
aceptación de navegador ni prueba visual autenticada de controles M02.

```yaml
M02_REDUNDANT_BUTTONS_REMOVED: PASS
M02_SINGLE_NEGATIVE_ACTION: PASS
M02_YES_DEPENDENCY_FLOW: PASS_PRESERVED
M02_FREE_TEXT_FLOW: PASS
M02_DEFER_PRESERVED: PASS
M02_ONE_CLICK_CAPTURE: PASS_EXISTING_CONTRACT
M02_CLINICAL_CONFIRMATION: PASS_FOR_COMPLETE_PROPOSALS
M02_UNCONFIRMED_WRITES: ZERO
INTERVIEW_CSS_SOURCE: INLINE_PLUS_SHARED_EXTERNAL
INTERVIEW_JS_SOURCE: INLINE
ASSET_VERSIONING_IMPLEMENTED: PASS
VERSIONING_METHOD: FILEMTIME_CONTENT_DEPENDENT_QUERY_STRING
ASSET_VERSION_CHANGES_AUTOMATICALLY: YES
INLINE_ASSET_CACHE_REVIEW: PASS_SCOPED_HTML_NO_STORE
STALE_ASSET_REFERENCES: NONE_STATIC
BROWSER_ASSET_VERIFICATION: PASS_HTTP_ASSETS_200
BROWSER_ACCEPTANCE: NOT_VERIFIED_AUTHENTICATED_SESSION_UNAVAILABLE
```

## Fase 6.9 — compositor compacto M03 y controles de voz discretos (2026-10-12)

M03/Tabaquismo ahora utiliza un compositor único dentro de la pregunta activa:
campo de texto, micrófono local opcional y envío por icono. Se eliminaron el
botón textual separado `Interpretar respuesta` y la segunda acción de
corrección de la propuesta. La propuesta completa se edita en el mismo campo
y sólo ofrece `Guardar y continuar`. Enter interpreta, pero nunca guarda por
sí mismo.

La respuesta `Sí` conserva la interpretación sin escritura y la memoria de
contexto. `No, guardar y seguir` sólo se renderiza para una pregunta M03
seleccionable, elegible y con escritura conversacional habilitada; reutiliza
`/historia-clinica/entrevista/capturar`. El servidor vuelve a validar tipo,
dependencias, autorización, CSRF, versión y concurrencia: una dependencia
conflictiva mantiene HTTP 409 sin borrar ni modificar respuestas relacionadas.

El control de lectura se muestra como icono junto al mensaje activo. Si el
navegador no confirma reconocimiento local, se muestra un indicador discreto
no operable con explicación accesible, sin un botón grande deshabilitado. No se
envía audio ni se guarda automáticamente ninguna transcripción.

La prueba sintética M03 comprobó catálogo, aclaración de dependencias,
captura padre/hija, negativa segura, 409 por dependencia conflictiva y 409 por
versión obsoleta, con restauración exacta. Las regresiones M02 y M09 también
pasaron y sus fixtures quedaron restaurados. No se tocaron formularios
tradicionales, modelos compartidos, `bd_clinica`, paciente 310, Gemini ni
assets globales.

```yaml
SCOPE: CONVERSATIONAL_INTERVIEW_ONLY
M03_SIMPLIFIED_UI: PASS
REDUNDANT_BUTTONS_REMOVED: PASS
SINGLE_TEXT_COMPOSER: PASS
ENTER_TO_INTERPRET: PASS_NO_DIRECT_WRITE
QUICK_YES_FLOW: PASS_CONTEXT_PRESERVED
ONE_ACTION_SAFE_NO: PASS_EXISTING_CAPTURE_CONTRACT
DEPENDENCY_PROTECTION: PASS_HTTP_409_NO_DELETE
SINGLE_COMPLEX_CONFIRMATION: PASS
AUTOMATIC_NEXT_QUESTION: PASS_AFTER_HTTP_200
VOICE_CONTROLS_COMPACT: PASS
LOCAL_MIC_PRIVACY_PRESERVED: PASS
M02_REGRESSION: PASS
M03_SYNTHETIC_PERSISTENCE: PASS
M09_REGRESSION: PASS
UNCONFIRMED_WRITES: ZERO
ASSET_VERSIONING: PRESERVED
BROWSER_VISUAL_ACCEPTANCE: NOT_VERIFIED_AUTHENTICATED_SESSION
LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
```

## Fase 6.10 — sistema visual unificado de la entrevista (2026-10-12)

Se aplicó un sistema visual reutilizable y encapsulado en la vista de la
entrevista. La página utiliza fondo lavanda, tarjetas blancas con bordes
suaves, mensajes del asistente en azul clínico claro, una tarjeta por etapa y
un compositor común para las respuestas compatibles. M01 quedó presentado
como tarjeta de consulta sin controles de captura; M07 mantiene su bloqueo
documental y M08 conserva el bloqueo de procedimientos.

Los compositores de M02, M03, M04, M05 V1/V2, M06, M08 tratamientos y M09
comparten la misma alineación de campo, micrófono y envío por icono. Las
acciones rápidas permanecen determinadas por el contrato de cada módulo. El
mapa M09 conserva su componente anterior/posterior y su flujo de
previsualización y confirmación PNG.

El único control de lectura se agrega al mensaje principal de la etapa activa.
El micrófono se inserta junto al campo; cuando no existe reconocimiento local
confirmado, se muestra un indicador accesible discreto sin activar audio ni
servicios externos. La persistencia, CSRF, finalización, concurrencia,
versiones, memoria y dependencias no fueron alterados.

```yaml
SCOPE: CONVERSATIONAL_INTERVIEW_ONLY
VISUAL_REFERENCE_APPLIED: PASS_TEXTUAL_REFERENCE
SHARED_INTERVIEW_DESIGN_SYSTEM: PASS_SCOPED_INTERVIEW_CSS
REUSABLE_RESPONSE_COMPOSER: PASS
SINGLE_ACTIVE_QUESTION: PASS_ORCHESTRATOR_PRESERVED
CONTEXTUAL_QUICK_ACTIONS: PASS
CONTEXTUAL_CONFIRMATION: PASS
TEXT_INPUT_LAYOUT: PASS
MICROPHONE_ALIGNMENT: PASS
SEND_BUTTON_ALIGNMENT: PASS
ASSISTANT_AUDIO_CONTROL: PASS_SINGLE_ACTIVE_MESSAGE
DUPLICATE_CONTROLS_REMOVED: PASS
M01_VISUAL_INTEGRATION: PASS_READ_ONLY_CARD
M02_VISUAL_INTEGRATION: PASS
M03_VISUAL_INTEGRATION: PASS
M04_VISUAL_INTEGRATION: PASS
M05_VISUAL_INTEGRATION: PASS_V1_AND_V2
M06_VISUAL_INTEGRATION: PASS
M07_BLOCKED_STATE_PRESERVED: PASS
M08_TREATMENTS_VISUAL_INTEGRATION: PASS
M08_PROCEDURES_BLOCKED: PASS
M09_EVALUATION_VISUAL_INTEGRATION: PASS
M09_MAP_VISUAL_INTEGRATION: PASS_PNG_FLOW_PRESERVED
RESPONSIVE_375: NOT_VERIFIED_BROWSER
RESPONSIVE_430: NOT_VERIFIED_BROWSER
RESPONSIVE_768: NOT_VERIFIED_BROWSER
RESPONSIVE_1024: NOT_VERIFIED_BROWSER
RESPONSIVE_1440: NOT_VERIFIED_BROWSER
CLINICAL_REGRESSIONS: PASS_SYNTHETIC_FOCAL
UNCONFIRMED_WRITES: ZERO
ASSET_VERSIONING: PRESERVED_FILEMTIME
BROWSER_VISUAL_ACCEPTANCE: NOT_VERIFIED_AUTHENTICATED_SESSION
LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
SHARED_CONTROLLERS_MODIFIED: NO
DATABASE_SCHEMA_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
GEMINI_PATIENT_ENABLED: NO
FILES_MODIFIED: "app/views/paciente/entrevista.php; tests/patient-interview/visual-system-contract.php; docs/AI_PATIENT_INTERVIEW/23_CONVERSATIONAL_ENGINE_IMPLEMENTATION.md"
BLOCKERS: "No hay sesión autenticada de navegador disponible para aceptar visualmente los cinco viewports"
NEXT_ACTION: "Revisión manual del propietario en clinica.test con sesión sintética autenticada"
```

## Fase 6.11 — corrección visual exacta del compositor (2026-10-12)

Se utilizó la imagen local de referencia `entrevista_clínica_sobre_tabaquismo.png`
como guía de composición. La entrevista ahora presenta una sola tarjeta de
conversación exterior; cada mensaje asistente tiene un encabezado con avatar
visual y el control de lectura en el encabezado activo. El compositor común
usa una rejilla estable: el campo con micrófono integrado ocupa la primera
columna y el botón compacto de enviar permanece alineado en la segunda; las
acciones rápidas ocupan una segunda fila inmediatamente inferior.

Se eliminaron los bordes y sombras decorativas de los contenedores de módulo
que producían tarjetas anidadas. Se envolvieron los campos M04, M05 V1/V2,
M06, M08 y M09 en el mismo contenedor de entrada que ya utilizaban M02/M03.
La lógica de voz, la confirmación clínica, la persistencia, el mapa PNG y los
contratos de seguridad no fueron modificados.

La comprobación estructural del DOM fuente, CSS, sintaxis y regresiones
focalizadas pasó. El navegador integrado no pudo completar la solicitud en
`clinica.test` y no había sesión autenticada disponible; por ello no se
declara comparación visual ni aceptación responsive.

```yaml
REFERENCE_IMAGE_USED: YES_LOCAL_ATTACHMENT
COMPOSER_SAME_ROW: PASS_GRID_SOURCE
MICROPHONE_INSIDE_INPUT: PASS_GRID_IN_INPUT_WRAPPER
SEND_BUTTON_ALIGNED: PASS_GRID_SECOND_COLUMN
AUDIO_CONTROL_IN_HEADER: PASS_ACTIVE_MESSAGE_HEADER
QUICK_ACTIONS_SECOND_ROW: PASS_GRID_SECOND_ROW
REDUNDANT_CONTAINERS_REMOVED: PASS_SCOPED_MODULE_CARDS
M02_LAYOUT: PASS_SOURCE_STRUCTURE
M03_LAYOUT: PASS_SOURCE_STRUCTURE
M06_LAYOUT: PASS_SOURCE_STRUCTURE
M09_LAYOUT: PASS_SOURCE_STRUCTURE
RESPONSIVE_375: NOT_VERIFIED_BROWSER
RESPONSIVE_430: NOT_VERIFIED_BROWSER
RESPONSIVE_768: NOT_VERIFIED_BROWSER
RESPONSIVE_1440: NOT_VERIFIED_BROWSER
VISUAL_COMPARISON: PENDING_AUTHENTICATED_BROWSER
BROWSER_VISUAL_ACCEPTANCE: PENDING
CLINICAL_REGRESSIONS: PASS_SYNTHETIC_FOCAL
UNCONFIRMED_WRITES: ZERO
LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
SHARED_CONTROLLERS_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
GEMINI_PATIENT_ENABLED: NO
FILES_MODIFIED: "app/views/paciente/entrevista.php; docs/AI_PATIENT_INTERVIEW/23_CONVERSATIONAL_ENGINE_IMPLEMENTATION.md"
BLOCKERS: "Navegador integrado sin sesión autenticada; la URL devuelve solicitud no disponible"
NEXT_ACTION: "Revisar manualmente con una sesión sintética autenticada y capturar 375, 430, 768 y 1440 px"
```

## Fase 6.2 — consolidación funcional y responsive (2026-10-12)

Se mantuvo el compositor aprobado y se corrigieron sólo defectos concretos de
la entrevista: la ayuda M03 ya no contradice la negativa segura y la propuesta
M06 presenta explícitamente sus cinco campos contractuales en una cuadrícula
legible. M02, M03, M04, M05, M08 y M09 conservan sus acciones, confirmaciones,
dependencias y controles de voz. El mapa PNG continúa especializado.

Los arneses sintéticos M05 V2/M06 y M08 pasaron y restauraron sus fixtures.
El navegador integrado volvió a devolver «No se pudo completar la solicitud»
sin sesión autenticada; no se declara aceptación visual responsive.

```yaml
SCOPE: CONVERSATIONAL_INTERVIEW_ONLY
VISUAL_SYSTEM_PRESERVED: YES
REUSABLE_COMPOSER: PASS
INPUT_MIC_SEND_ALIGNMENT: PASS_SOURCE_GRID
ACTIVE_AUDIO_CONTROL: PASS_SINGLE_ACTIVE_HEADER
CONTEXTUAL_QUICK_ACTIONS: PASS
RESPONSIVE_BUTTON_LAYOUT: PASS_CSS_SOURCE
ACCESSIBLE_TOUCH_TARGETS: PASS_MIN_44PX_SOURCE
PATIENT_MESSAGE_CLARITY: PASS_M03_HELP_CORRECTED
M02_UI: PASS
M03_UI: PASS
M04_UI: PASS
M05_UI: PASS_V1_V2
M06_MEDICATION_PROPOSAL: PASS_FIVE_FIELDS_VISIBLE
M07_PENDING_STATE: PASS_BLOCKED
M08_TREATMENTS_UI: PASS
M08_PROCEDURES_BLOCKED: PASS
M09_EVALUATION_UI: PASS
M09_BODY_MAP_UI: PASS_SPECIALIZED_PNG
RESPONSIVE_375: NOT_VERIFIED_BROWSER
RESPONSIVE_430: NOT_VERIFIED_BROWSER
RESPONSIVE_768: NOT_VERIFIED_BROWSER
RESPONSIVE_1024: NOT_VERIFIED_BROWSER
RESPONSIVE_1440: NOT_VERIFIED_BROWSER
M02_REGRESSION: PASS
M03_REGRESSION: PASS
M05_REGRESSION: PASS_SYNTHETIC
M06_REGRESSION: PASS_SYNTHETIC_RESTORED
M08_REGRESSION: PASS_SYNTHETIC_RESTORED
M09_REGRESSION: PASS_SYNTHETIC_RESTORED
UNCONFIRMED_WRITES: ZERO
CLINICAL_CONTRACTS_PRESERVED: YES
VOICE_PRIVACY_PRESERVED: YES_LOCAL_ONLY
ASSET_VERSIONING: PRESERVED_FILEMTIME
AUTHENTICATED_BROWSER_TEST: BLOCKED_NO_SESSION
VISUAL_ACCEPTANCE: PENDING
SYNTHETIC_TESTS: PASS
LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
SHARED_CONTROLLERS_MODIFIED: NO
DATABASE_SCHEMA_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
GEMINI_PATIENT_ENABLED: NO
FILES_MODIFIED: "app/views/paciente/entrevista.php; tests/patient-interview/visual-system-contract.php; docs/AI_PATIENT_INTERVIEW/23_CONVERSATIONAL_ENGINE_IMPLEMENTATION.md"
VISUAL_DEFECTS_CORRECTED: "M06 five-field proposal visibility; M03 help-message contradiction"
REMAINING_BLOCKERS: "Authenticated browser session unavailable for real responsive validation"
NEXT_ACTION: "Owner review with synthetic authenticated browser session at clinica.test"
```

## Fase 6.3 — aceptación integral sintética (2026-10-12)

Se ejecutó la aceptación funcional sobre la base aislada
`clinica_dolor_browser_20261012`, sin utilizar `bd_clinica`, el expediente 310,
Gemini ni credenciales reales. Los contratos existentes de M01, M02, M03,
M04, M05 V1/V2, M06, M08 tratamientos y M09 evaluación/mapa PNG pasaron sus
escenarios sintéticos; las pruebas que escriben restauraron exclusivamente sus
fixtures y finalizaron sin duplicados.

La verificación HTTP del entorno aislado confirmó acceso 200, login sintético,
renderizado del chat 200, primera captura M03 200, rechazo de una segunda
captura con la misma versión 409, CSRF ausente/inválido 403 y restauración
posterior de P5. El listener que quedó activo fue el proceso PHP 59900 en
127.0.0.1:8098; no se dejó una segunda instancia ejecutándose.

El navegador integrado no pudo completar la navegación visual autenticada y
mostró «Solicitud no disponible» tanto con el host local como con loopback.
Por ello no se inventan capturas ni resultados responsive. La reanudación tras
cierre de sesión tampoco queda verificada mediante navegador; el render HTTP
posterior sí recupera el estado sintético sin escrituras.

El contrato estático `m03-compact-ux-contract.php` conserva tres aserciones
fallidas que buscan textos o asignaciones de una versión anterior del
componente; no se clasifican como PASS visual ni como defecto funcional sin una
sesión de navegador. No se modificó código durante esta aceptación.

```yaml
SCOPE: CONVERSATIONAL_INTERVIEW_ONLY
SYNTHETIC_AUTHENTICATED_SESSION: PASS_HTTP_SYNTHETIC
END_TO_END_BROWSER_EXECUTION: BLOCKED_NO_BROWSER_SESSION
FIRST_PENDING_QUESTION: PASS_HTTP_SYNTHETIC
ONE_ACTIVE_QUESTION: PASS_ORCHESTRATOR_CONTRACT
NATURAL_LANGUAGE_INTERPRETATION: PASS_SYNTHETIC
MULTI_TURN_CONTEXT: PASS_M02_CONTEXT_MEMORY
CLARIFICATION_WITHOUT_WRITE: PASS_SYNTHETIC
EXPLICIT_CONFIRMATION: PASS_SYNTHETIC
ONE_ACTION_SAFE_RESPONSES: PASS_M02_M03_SYNTHETIC
PERSISTENCE_READBACK: PASS_SYNTHETIC_AND_HTTP_M03
AUTOMATIC_NEXT_QUESTION: PASS_SYNTHETIC_READBACK
SESSION_RESUME: NOT_VERIFIED_BROWSER
DUPLICATE_SUBMISSION_PROTECTION: PASS_SYNTHETIC_CONCURRENCY
STALE_VERSION_HANDLING: PASS_ADAPTERS_AND_HTTP_M03
UNCONFIRMED_WRITES: ZERO

M01_READ: PASS_READ_ONLY
M02_FLOW: PASS_SYNTHETIC
M03_FLOW: PASS_SYNTHETIC
M04_FLOW: PASS_SYNTHETIC
M05_V1_V2_FLOW: PASS_SYNTHETIC
M06_MEDICATION_FLOW: PASS_SYNTHETIC
M07_BLOCKED_STATE: PASS_BLOCKED_BY_OFFICIAL_CATALOG
M08_TREATMENT_FLOW: PASS_SYNTHETIC
M08_PROCEDURES_BLOCKED: PASS_BLOCKED_BY_CONTRACT
M09_EVALUATION_FLOW: PASS_SYNTHETIC
M09_BODY_MAP_FLOW: PASS_SYNTHETIC_PNG_READBACK

RESPONSIVE_375: NOT_VERIFIED_BROWSER
RESPONSIVE_430: NOT_VERIFIED_BROWSER
RESPONSIVE_768: NOT_VERIFIED_BROWSER
RESPONSIVE_1024: NOT_VERIFIED_BROWSER
RESPONSIVE_1440: NOT_VERIFIED_BROWSER

FULL_AVAILABLE_SCOPE_COVERAGE: PASS_SYNTHETIC_COMPONENTS
FULL_CLINICAL_HISTORY_COMPLETION: NOT_CLAIMED_M07_AND_M08_PROCEDURES_BLOCKED
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED

SYNTHETIC_DATABASE_INTEGRITY: PASS_FIXTURES_RESTORED
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO

BROWSER_EVIDENCE: "HTTP synthetic session PASS; authenticated visual session blocked by browser response Solicitud no disponible; no responsive screenshots claimed"
DEFECTS_CORRECTED: NONE_IN_PHASE_6_3
REMAINING_BLOCKERS: "Authenticated browser session, five real viewport captures, and browser-level close/reopen resume; three stale static M03 compact-contract assertions require contract review"
NEXT_ACTION: "Owner opens the isolated synthetic session in a browser that can reach 127.0.0.1:8098 and records the five viewport results before visual acceptance"
```

## Fase 6.4 — integración Gemini real aislada en la entrevista (2026-10-12)

Se conectó el proveedor Gemini ya existente únicamente a la interpretación de
M02 y M03 dentro de `PatientInterviewController`. La integración no crea un
cliente nuevo ni cambia los contratos de captura: la propuesta sigue siendo
no persistente y la confirmación continúa pasando por los adaptadores existentes.

La llamada externa se protegió con dos condiciones de proceso y una condición
de conexión efectiva: `CLINICAL_AI_SYNTHETIC_MODE=1`,
`CLINICAL_AI_EXTERNAL_ENABLED=1` sólo durante el arnés CLI y
`SELECT DATABASE() = clinica_dolor_browser_20261012`. Si alguna condición falla,
la entrevista cae al intérprete determinista. El `.env` compartido conserva
`CLINICAL_AI_EXTERNAL_ENABLED=0` y `CLINICAL_AI_SYNTHETIC_MODE=0`.

El modelo utilizado fue `gemini-3.8-flash` mediante el endpoint configurado
`https://generativelanguage.googleapis.com/v1beta`. Gemini devolvió JSON
estructurado en las nueve llamadas del arnés, incluyendo el `usageMetadata`
disponible: 2,203 tokens de entrada, 502 de salida, 7,457 de razonamiento y
10,162 totales. La latencia agregada fue 83,716.4 ms, con mínimo 4,570.3 ms,
máximo 19,994.3 ms y promedio 9,301.8 ms. El costo monetario no fue informado
por la respuesta y no se estimó.

Los escenarios sintéticos cubrieron respuesta completa, memoria multi-turno,
incertidumbre sin convertirla en “No”, contradicción, explicación, salida
acotada frente a prompt injection y compatibilidad M03. Se realizó una captura
temporal M02 con la propuesta Gemini, lectura posterior, rechazo de una versión
obsoleta y restauración exacta del registro original. No se enviaron al
proveedor identificadores de paciente, expediente, clínica, cookie ni el
snapshot clínico; el contexto del proveedor contiene sólo la pregunta,
respuesta, contexto conversacional permitido y catálogos acotados.

```yaml
SCOPE: CONVERSATIONAL_INTERVIEW_ONLY
GEMINI_EXISTING_ADAPTER_REUSED: YES
GEMINI_SYNTHETIC_ISOLATION: PASS_PROCESS_FLAGS_AND_EFFECTIVE_DATABASE_ALLOWLIST
GEMINI_MODEL: gemini-3.8-flash
GEMINI_ENDPOINT: https://generativelanguage.googleapis.com/v1beta
GEMINI_REAL_MULTITURN_EXECUTION: PASS
GEMINI_PROVIDER_RESPONSES_RECEIVED: 9
GEMINI_PROVIDER_CALL_COUNT: 9
GEMINI_RUNTIME_MS: "total=83716.4; min=4570.3; max=19994.3; average=9301.8"
GEMINI_TOKEN_USAGE_IF_AVAILABLE: "prompt=2203; output=502; thoughts=7457; total=10162"
M02_GEMINI_INTERPRETATION: PASS_COMPLETE_AND_MULTI_TURN
M02_MULTITURN_CONTEXT: PASS_GEMINI_MULTI_TURN
M02_CLARIFICATION: PASS_UNCERTAINTY_CONTRADICTION_EXPLANATION
M02_STRUCTURED_PROPOSAL: PASS_BOUNDED_FIELDS
M02_CLINICAL_VALIDATION: PASS_SERVER_ALLOWLIST_AND_EXISTING_CONTRACT
M02_EXPLICIT_CONFIRMATION: PASS_NO_AUTO_CONFIRMATION
M02_CONTEXT_MEMORY: PASS_GEMINI_MULTI_TURN
M02_UNCERTAINTY_HANDLING: PASS_NO_FALSE_NEGATIVE
M02_CONTRADICTION_HANDLING: PASS_CLARIFICATION_NO_WRITE
M02_EXPLANATION_HANDLING: PASS_NO_PROPOSAL
M02_PERSISTENCE_SYNTHETIC: PASS_EXISTING_ADAPTER_READBACK_AND_RESTORE
M02_STALE_VERSION_REJECTION: PASS_409
M03_SYNTHETIC_COMPATIBILITY: PASS_REAL_GEMINI_CLARIFICATION
DETERMINISTIC_FALLBACK: PASS_TRANSPORT_AND_MISSING_KEY_PATHS
INVALID_OUTPUT_REJECTION: PASS_PROVIDER_SCHEMA_AND_BOUNDED_NORMALIZATION
PROVIDER_TIMEOUT_HANDLING: NOT_SEPARATELY_VERIFIED_TIMEOUT_FAULT_INJECTION
PROVIDER_TIMEOUT_LIMIT: CONFIGURED_20S
SAFE_TRANSPORT_FALLBACK: PASS
PROMPT_INJECTION_HANDLING: PASS_SYNTHETIC_BOUNDED_OUTPUT
EXTERNAL_REAL_PATIENT_DATA_SENT: NO
BD_CLINICA_ACCESSED_BY_GEMINI: NO
UNCONFIRMED_WRITES: ZERO
SYNTHETIC_FIXTURE_RESTORED: PASS_HASHED_M02_M03_AND_FINALIZATION_STATE
PATIENT_CHAT_GEMINI_ENABLED: NO
CLINICAL_AI_EXTERNAL_ENABLED: 0
REAL_PATIENT_DATA_AUTHORIZATION: NOT_APPROVED
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
SYNTHETIC_INTEGRATION_TESTS: PASS
REAL_GEMINI_TEST: PASS
GEMINI_REAL_INTEGRATION: PASS_SYNTHETIC_INTERVIEW_CLI_ONLY
BROWSER_E2E: NOT_VERIFIED_AUTHENTICATED_SESSION
LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
SHARED_CONTROLLERS_MODIFIED: NO
DATABASE_SCHEMA_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
FILES_MODIFIED: "app/Services/GeminiProvider.php; app/Services/ConversationalInterviewService.php; app/Controllers/PatientInterviewController.php; tests/patient-interview/gemini-synthetic-real.php; tests/patient-interview/gemini-interview-contract.php; docs/AI_PATIENT_INTERVIEW/23_CONVERSATIONAL_ENGINE_IMPLEMENTATION.md"
BLOCKERS_REQUIRING_APPROVAL: "Autorización separada para datos reales; habilitación web externa no realizada; aceptación visual autenticada pendiente"
NEXT_ACTION: "Mantener CLINICAL_AI_EXTERNAL_ENABLED=0 y realizar revisión clínica antes de cualquier habilitación externa"
```

## Fase 6.5 — Gemini en el flujo HTTP sintético (2026-10-12)

Se verificó el flujo real mediante `PatientInterviewController` y el
orquestador existente de la Entrevista Conversacional. No se repitió el arnés
CLI de nueve llamadas: esta aceptación realizó exactamente dos solicitudes
reales a Gemini desde un servidor PHP aislado en `127.0.0.1:8100`, usando la
base `clinica_dolor_browser_20261012`, el paciente sintético 9302 y la fila M02
preexistente de la clínica sintética. El proceso recibió las habilitaciones
externas únicamente por entorno de proceso; el `.env` compartido permaneció
con `CLINICAL_AI_EXTERNAL_ENABLED=0` y `CLINICAL_AI_SYNTHETIC_MODE=0`.

El primer POST HTTP con «Mi padre» respondió 200, `mode=gemini` y una
aclaración: el parentesco se conservó, pero no se convirtió en un “Sí”
implícito. El segundo POST con «Sí» respondió 200, `mode=gemini` y una
propuesta M02 estructurada con el parentesco anterior. Las consultas de hash
confirmaron cero escrituras después del GET y antes de la confirmación. El POST
de confirmación realizó una única escritura sintética mediante el adaptador
M02, la lectura posterior recuperó la respuesta y un replay con una versión
obsoleta respondió HTTP 409. El estado original fue restaurado y los hashes de
M02, M03 y finalización volvieron a coincidir; no se accedió a `bd_clinica` ni
al paciente 310.

Gemini devolvió `usageMetadata` en ambas respuestas. El modelo fue
`gemini-3.8-flash`; la latencia observada fue 4,945.2 ms y 12,555.4 ms
(total 17,500.6 ms; promedio 8,750.3 ms). El consumo reportado fue 504 tokens
de entrada, 84 de salida, 1,658 de razonamiento y 2,246 totales. No se
estimaron costos monetarios porque la respuesta no los proporcionó. La carga
externa se mantuvo acotada a pregunta, texto sintético, contexto M02 permitido
y valores de catálogo; no incluyó identificadores de paciente, clínica,
expediente, cookies ni claves.

La corrección focal aplicada después de la primera ejecución evita que un
parentesco aislado sea sobreinterpretado como respuesta afirmativa. El router
del arnés HTTP también inicializa explícitamente las variables de configuración
del proceso antes del bootstrap inmutable, sin tocar `.env`. El indicador de
procesamiento y la protección de doble envío ya existentes se verificaron por
inspección del componente: el texto permanece en el input, el botón se
deshabilita durante la petición y se restablece en `finally`. No se ejecutó una
sesión autenticada de navegador; por ello no se declara E2E visual ni avance
automático de la pregunta. Tampoco se repitió una inyección de timeout real;
queda como validación pendiente separada.

```yaml
SCOPE: CONVERSATIONAL_INTERVIEW_ONLY
GEMINI_SYNTHETIC_HTTP_SESSION: PASS
GEMINI_REAL_HTTP_PROVIDER_CALL: PASS_2_REAL_CALLS
GEMINI_HTTP_SERVER: "127.0.0.1:8100; isolated process; stopped after acceptance"
GEMINI_HTTP_DATABASE: clinica_dolor_browser_20261012
GEMINI_HTTP_PATIENT: 9302_SYNTHETIC_ONLY
GEMINI_MODEL: gemini-3.8-flash
GEMINI_ENDPOINT: https://generativelanguage.googleapis.com/v1beta
M02_REAL_MULTITURN_HTTP: PASS
M02_FIRST_TURN: "HTTP 200; mode=gemini; clarification; relative=padre"
M02_SECOND_TURN: "HTTP 200; mode=gemini; structured proposal; relative=padre"
M02_CONTEXT_PRESERVED: PASS
M02_CLINICAL_VALIDATION: PASS_SERVER_ALLOWLIST
ZERO_WRITES_BEFORE_CONFIRMATION: PASS_HASHED_M02_M03_FINALIZATION
CONFIRMED_SYNTHETIC_WRITE: PASS_SINGLE_EXISTING_ADAPTER_PATH
PERSISTENCE_READBACK: PASS
STALE_REPLAY_HTTP: PASS_409
AUTOMATIC_NEXT_QUESTION: NOT_VERIFIED_BROWSER
UI_PROCESSING_STATE: PASS_CODE_INSPECTION_STATUS_AND_DISABLED_TRIGGER
DUPLICATE_REQUEST_PROTECTION: PASS_CARD_GUARD_AND_HTTP_STALE_409
TIMEOUT_FORCED_TEST: NOT_VERIFIED_CONTROLLED_PROVIDER
FALLBACK_RECOVERY: PASS_PHASE_6_4_DETERMINISTIC_PATH_NOT_REPEATED
LATENCY_OBSERVED: "4945.2ms; 12555.4ms; total=17500.6ms; average=8750.3ms"
GEMINI_TOKEN_USAGE_IF_AVAILABLE: "prompt=504; output=84; thoughts=1658; total=2246"
GEMINI_COST: NOT_REPORTED_BY_PROVIDER
EXTERNAL_REAL_PATIENT_DATA_SENT: NO
BD_CLINICA_EXPOSED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
PATIENT_GEMINI_ENABLED: NO
CLINICAL_AI_EXTERNAL_ENABLED_GLOBAL: 0
SYNTHETIC_PROCESS_EXTERNAL_FLAG: 1_DURING_HARNESS_ONLY
SYNTHETIC_FIXTURE_RESTORED: PASS
LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
DATABASE_SCHEMA_MODIFIED: NO
HTTP_INTEGRATION: PASS
BROWSER_E2E: NOT_VERIFIED_AUTHENTICATED_SESSION
REAL_PATIENT_DATA_AUTHORIZATION: NOT_APPROVED
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
FILES_MODIFIED: "app/Services/ConversationalInterviewService.php; app/views/paciente/entrevista.php; tests/patient-interview/gemini-http-integration.php; tests/patient-interview/gemini-http-router.php; docs/AI_PATIENT_INTERVIEW/23_CONVERSATIONAL_ENGINE_IMPLEMENTATION.md"
NEXT_ACTION: "Realizar aceptación manual autenticada si se necesita avance visual; ejecutar timeout controlado antes de declarar cobertura completa de fallos"
```

## Fase 6.6 — Robustez HTTP y experiencia de IA conversacional (2026-10-12)

Esta validación se limitó a la Entrevista Conversacional y utilizó únicamente
la base sintética `clinica_dolor_browser_20261012`, con un proveedor simulado
inyectado en un proceso HTTP aislado. No se repitieron llamadas reales a
Gemini. El arnés heredó el entorno del proceso y sobrescribió sólo las
variables sintéticas, evitando alterar `.env`; también rechazó puertos ya
ocupados antes de enviar peticiones.

El controlador HTTP real recibió los POST de interpretación. Los escenarios
de timeout, transporte, respuesta vacía, JSON malformado y valor fuera de
catálogo terminaron en una aclaración determinista HTTP 200, sin escritura ni
avance. La respuesta contradictoria se conservó como aclaración segura y no
se persistió. Una respuesta tardía simulada fue seguida por un reintento
válido: el primer turno cayó al modo determinista y el segundo recuperó el
modo del proveedor. La repetición de la misma petición no produjo escrituras
ni cambios en la fixture.

La validación estructural de la interfaz confirmó que el texto queda en el
input durante la espera, el control disparador se deshabilita mientras la
petición está activa, el estado de procesamiento se publica y los controles
se restauran en `finally`. La rama de error conserva el texto y muestra
reintento/estado seguro. No se hizo una sesión autenticada de navegador, por
lo que esta entrega no declara aceptación visual ni avance automático en
navegador. La evidencia HTTP previa de Fase 6.5 confirmó la vista de lectura
posterior a la confirmación sintética y el readback; el avance de la tarjeta
en el DOM sigue siendo responsabilidad de JavaScript y requiere prueba de
navegador para declararse PASS.

Los tres asertos estáticos M03 que ya no correspondían al componente vigente
fueron actualizados: etiqueta del botón de interpretación, mensaje de
reconocimiento local no disponible y host del compositor. También se ajustó
el contrato de Gemini para reconocer la inyección opcional del proveedor sin
cambiar el contrato clínico.

```yaml
SCOPE: CONVERSATIONAL_INTERVIEW_ONLY
FORCED_TIMEOUT_TEST: PASS_HTTP_SYNTHETIC_PROVIDER
NETWORK_ERROR_TEST: PASS_HTTP_SYNTHETIC_PROVIDER
EMPTY_RESPONSE_TEST: PASS_HTTP_SYNTHETIC_PROVIDER
MALFORMED_JSON_TEST: PASS_HTTP_SYNTHETIC_PROVIDER
OUT_OF_CATALOG_TEST: PASS_SAFE_CLARIFICATION
CONTRADICTORY_RESPONSE_TEST: PASS_SAFE_CLARIFICATION_NO_WRITE
LATE_RESPONSE_TEST: PASS_DETERMINISTIC_THEN_RETRY
UI_PROCESSING_STATE: PASS_CODE_AND_STATIC_CONTRACT; BROWSER_NOT_RUN
INPUT_PRESERVED_DURING_WAIT: PASS_CODE_INSPECTION
DUPLICATE_SUBMIT_BLOCKED: PASS_CARD_GUARD
ERROR_RECOVERY: PASS_DETERMINISTIC_CLARIFICATION_AND_RETRY_PATH
DETERMINISTIC_FALLBACK: PASS
PROVIDER_PROVENANCE: PASS_MODE_EXPLICIT; FALLBACK_NOT_LABELED_GEMINI
CONFIRMED_WRITE_READBACK: PASS_PRIOR_PHASE_6_5_SYNTHETIC_HTTP_EVIDENCE
NEXT_PENDING_QUESTION_HTTP: PASS_READBACK_VIEW_ONLY
AUTOMATIC_ADVANCE_BROWSER: NOT_VERIFIED
M03_OBSOLETE_ASSERTIONS_REVIEWED: PASS
M03_ASSERTIONS_CORRECTED: PASS_3_ASSERTIONS
SYNTHETIC_FIXTURE_RESTORED: PASS_HASHES_UNCHANGED
UNCONFIRMED_WRITES: ZERO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
LEGACY_FORMS_MODIFIED: NO
PATIENT_GEMINI_ENABLED: NO
REAL_GEMINI_CALLS_ADDED: 0
HTTP_REGRESSIONS: NONE_DETECTED
BROWSER_E2E: NOT_VERIFIED_AUTHENTICATED_SESSION
FILES_MODIFIED: "app/Services/ConversationalProvider.php; app/Services/GeminiProvider.php; app/Services/ConversationalInterviewService.php; app/Controllers/PatientInterviewController.php; tests/patient-interview/gemini-failure-http-router.php; tests/patient-interview/gemini-failure-http.php; tests/patient-interview/m03-compact-ux-contract.php; tests/patient-interview/gemini-interview-contract.php; docs/AI_PATIENT_INTERVIEW/23_CONVERSATIONAL_ENGINE_IMPLEMENTATION.md"
REMAINING_BLOCKERS: "Aceptación visual y avance automático en navegador autenticado; autorización independiente para datos reales y Gemini externo"
NEXT_ACTION: "Ejecutar una prueba manual de navegador con sesión sintética si se requiere cerrar BROWSER_E2E; no habilitar datos reales"
```

## Fase 6.7R — diagnóstico y corrección del fallback Gemini M03/M06 (2026-10-12)

No se realizaron llamadas externas nuevas. La evidencia conservada de Fase
6.7 sólo contenía respuestas HTTP 200 en modo determinista y no conservaba la
excepción del proveedor; por ello no era posible atribuir honestamente el
fallback previo a transporte, HTTP, JSON o esquema. Se confirmó como defecto
demostrable la pérdida de trazabilidad: el orquestador capturaba la excepción
y continuaba con fallback sin registrar una categoría segura.

Se añadió trazabilidad opt-in, limitada a un proceso sintético con flags
externos activos y archivo local suministrado por el proceso. Sólo registra
`execution_id`, módulo, etapa, categoría, fallback y duración; nunca prompt,
respuesta, cookie, token o credencial. El callback no puede alterar el flujo
clínico si falla.

La reproducción local demostró que no existe un defecto de aceptación para
respuestas compatibles: M03 válido y M06 válido son aceptados; M06 incompleto
produce aclaración Gemini sin fallback; JSON inválido se rechaza en validación
estructurada; un `GEMINI_HTTP_400` se clasifica en invocación y activa el
fallback; M02 conserva su aceptación previa. No hubo escrituras y la fixture
fue restaurada.

```yaml
PHASE: 6.7R
SCOPE: CONVERSATIONAL_INTERVIEW_ONLY
ROOT_CAUSE_M03: "NOT_DETERMINABLE_FROM_PRIOR_EVIDENCE; provider exception was discarded before trace existed"
ROOT_CAUSE_M06: "NOT_DETERMINABLE_FROM_PRIOR_EVIDENCE; provider exception was discarded before trace existed"
FIRST_REJECTION_STAGE_M03: "REAL_PENDING; simulated HTTP error=provider_invocation, invalid JSON=structured_json_validation"
FIRST_REJECTION_STAGE_M06: "REAL_PENDING; simulated invalid fields=structured_json_validation"
GEMINI_PROVIDER_SELECTION: PASS_SIMULATED_AND_TRACEABLE
HTTP_PROVIDER_INVOCATION: PASS_SIMULATED; PRIOR_REAL_EXCEPTION_NOT_PRESERVED
PROVIDER_RESPONSE_HANDLING: PASS_VALID_AND_INCOMPLETE_FIXTURES
STRUCTURED_JSON_VALIDATION: PASS_FAIL_CLOSED
SCHEMA_TO_CONTRACT_MAPPING: PASS_M03_AND_M06
CLINICAL_VALIDATION: PASS_NO_CONTRACT_WEAKENING
FALLBACK_REASON_CLASSIFICATION: PASS_CATEGORIZED_LOCAL_TRACE
M02_REGRESSION: PASS
M03_SIMULATED_ACCEPTANCE: PASS
M06_SIMULATED_ACCEPTANCE: PASS
INVALID_OUTPUT_REJECTION: PASS
DETERMINISTIC_FALLBACK: PASS
REAL_GEMINI_CALLS_ADDED: 0
REAL_GEMINI_M03_ACCEPTANCE: PENDING
REAL_GEMINI_M06_ACCEPTANCE: PENDING
UNCONFIRMED_WRITES: ZERO
SYNTHETIC_DATABASE_INTEGRITY: PASS_RESTORED
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
LEGACY_FORMS_MODIFIED: NO
FILES_MODIFIED: "app/Services/ConversationalInterviewService.php; app/Controllers/PatientInterviewController.php; tests/patient-interview/gemini-m03-m06-diagnostic.php; tests/patient-interview/gemini-interview-contract.php; docs/AI_PATIENT_INTERVIEW/23_CONVERSATIONAL_ENGINE_IMPLEMENTATION.md"
DEFECTS_CONFIRMED: "Fallback exception observability gap; no local M03/M06 conversion defect reproduced"
CORRECTIONS_IMPLEMENTED: "Opt-in synthetic trace with provider/validation/mapping stages and safe HTTP-controller wiring"
REMAINING_BLOCKERS: "The exact prior external exception is unavailable; one authorized real M03 call and one authorized real M06 call are required"
MINIMUM_REAL_CALLS_REQUIRED: 2
NEXT_ACTION: "Run exactly one isolated M03 and one isolated M06 Gemini request with trace enabled, only after explicit authorization; keep real-patient processing disabled"
```

## Fase 6.7V — preflight pendiente de autorización (2026-10-12)

La especificación exige autorización expresa antes de realizar las dos
llamadas externas. En esta ejecución no se recibió esa autorización, por lo
que se detuvo el procedimiento antes de contactar Gemini.

El preflight local confirmó, mediante lectura, que la conexión efectiva es
`clinica_dolor_browser_20261012`, que el paciente de prueba es sintético y
que la trazabilidad opt-in está disponible. La configuración habitual no fue
modificada y Gemini continúa deshabilitado para pacientes reales.

```yaml
PHASE: 6.7V
SYNTHETIC_PREFLIGHT: PASS_LOCAL_DATABASE_AND_FIXTURE
REAL_CALL_ATTEMPT_LIMIT: 2
REAL_CALL_ATTEMPTS_USED: 0
M03_PROVIDER_INVOKED: NOT_EXECUTED_AUTHORIZATION_PENDING
M06_PROVIDER_INVOKED: NOT_EXECUTED_AUTHORIZATION_PENDING
REAL_GEMINI_M03_ACCEPTANCE: PENDING
REAL_GEMINI_M06_ACCEPTANCE: PENDING
UNCONFIRMED_WRITES: ZERO
SYNTHETIC_DATABASE_INTEGRITY: UNCHANGED
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
PATIENT_GEMINI_ENABLED: NO
LEGACY_FORMS_MODIFIED: NO
FINAL_STATUS: BLOCKED_PENDING_EXPLICIT_AUTHORIZATION
NEXT_ACTION: "Autorizar expresamente una llamada M03 y una M06 para ejecutar la verificación real, sin reintentos"
```

## Fase 6.7V — verificación real Gemini M03/M06 (2026-10-12)

Con autorización expresa se ejecutaron exactamente dos solicitudes externas,
una M03 y una M06, desde un único proceso HTTP sintético. Cada módulo tuvo un
preflight local inmediatamente antes de su solicitud: la base efectiva fue
`clinica_dolor_browser_20261012`, el paciente fue 9302, los flags sintéticos
del proceso estuvieron activos y la configuración habitual permaneció con
Gemini deshabilitado.

En ambos casos el proveedor fue seleccionado e invocado, pero cURL terminó
con `GEMINI_TRANSPORT` antes de recibir una respuesta HTTP. No hubo código
HTTP del proveedor, JSON, mapeo ni validación clínica de una respuesta Gemini.
La primera etapa de rechazo fue `provider_invocation`; el fallback
determinista se aplicó correctamente. M03 observó aproximadamente 163.3 ms
hasta el fallback y M06 aproximadamente 124.0 ms. No hubo tokens ni costo
reportables. No se enviaron solicitudes adicionales y no se llamaron rutas de
confirmación.

```yaml
PHASE: 6.7V
SYNTHETIC_PREFLIGHT: PASS_BEFORE_M03_AND_M06
REAL_CALL_ATTEMPT_LIMIT: 2
REAL_CALL_ATTEMPTS_USED: 2
M03_PROVIDER_INVOKED: YES
M03_PROVIDER_RESPONSE: NO_TRANSPORT_ERROR
M03_FIRST_REJECTION_STAGE: provider_invocation
M03_GEMINI_ACCEPTED: NO
M03_FALLBACK_USED: YES_DETERMINISTIC
M06_PROVIDER_INVOKED: YES
M06_PROVIDER_RESPONSE: NO_TRANSPORT_ERROR
M06_FIRST_REJECTION_STAGE: provider_invocation
M06_GEMINI_ACCEPTED: NO
M06_FALLBACK_USED: YES_DETERMINISTIC
GEMINI_HTTP_ERRORS: NONE_RESPONSE_RECEIVED
GEMINI_TRANSPORT_ERRORS: M03=GEMINI_TRANSPORT; M06=GEMINI_TRANSPORT
GEMINI_LATENCY: M03=163.3ms_to_fallback; M06=124.0ms_to_fallback
GEMINI_TOKEN_USAGE: NOT_AVAILABLE
ROOT_CAUSE_M03: TRANSPORT_ERROR_BEFORE_HTTP_RESPONSE
ROOT_CAUSE_M06: TRANSPORT_ERROR_BEFORE_HTTP_RESPONSE
CORRECTION_REQUIRED: NONE_DEMONSTRATED_IN_CONTRACT_OR_VALIDATION; EXTERNAL_TRANSPORT_REQUIRES_SEPARATE_NETWORK_DIAGNOSTIC
UNCONFIRMED_WRITES: ZERO
CONFIRMATION_REQUESTS: ZERO
SYNTHETIC_DATABASE_INTEGRITY: PASS_RESTORED
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
PATIENT_GEMINI_ENABLED: NO
LEGACY_FORMS_MODIFIED: NO
CLINICAL_AI_EXTERNAL_ENABLED_GLOBAL: 0
TRACE_FILE_CLEANED: YES
FILES_MODIFIED: "app/Services/ConversationalInterviewService.php; app/Controllers/PatientInterviewController.php; tests/patient-interview/gemini-m03-m06-http.php; tests/patient-interview/gemini-m03-m06-http-router.php; docs/AI_PATIENT_INTERVIEW/23_CONVERSATIONAL_ENGINE_IMPLEMENTATION.md"
FINAL_STATUS: PARTIAL_TRANSPORT_BLOCKED
NEXT_ACTION: "No repetir llamadas; revisar conectividad HTTPS/cURL del entorno sintético antes de una nueva autorización"
```

## Fase 6.8 — captura directa numérica y dictado local (2026-10-13)

Se implementó una ruta exclusiva de la Entrevista Conversacional para la
captura directa de respuestas M03 numéricas completas. El caso de referencia
es M03/P4 («cigarrillos por día»), pero la política se aplica de forma
reutilizable a cualquier pregunta M03 de tipo `input`: el servidor comprueba
que la pregunta esté abierta, sea elegible, editable y que el valor tenga
formato numérico. No se inventan límites clínicos ni se sobrescriben respuestas
existentes. La petición exige explícitamente `saveAndContinue`, conserva CSRF,
autorización y versión, verifica el readback y devuelve la siguiente pregunta
pendiente elegible.

Las respuestas selectivas, ambiguas, complejas, M06 y los módulos especiales
mantienen la revisión/confirmación existente. El cliente sólo cambia la
etiqueta accesible de la acción cuando el valor ya es candidato directo; no
guarda al teclear ni al dictar y bloquea envíos repetidos durante la petición.

El micrófono continúa siendo estrictamente local: se exige
`processLocally=true`; cuando el navegador expone `SpeechRecognition.available`,
se comprueba la disponibilidad de `es-MX` y se degrada a teclado si no está
disponible. No se añadió STT externo ni se verificó un dictado real en Chrome
en esta ejecución.

```yaml
PHASE: 6.8
SCOPE: CONVERSATIONAL_INTERVIEW_ONLY
SIMPLE_ANSWER_ONE_ACTION: PASS_M03_NUMERIC_INPUT
M03_CIGARETTES_PER_DAY_DIRECT_SAVE: PASS_SYNTHETIC_P4_9302
SERVER_SIDE_ELIGIBILITY: PASS
EXPLICIT_SAVE_ACTION: PASS_SAVE_AND_CONTINUE_REQUIRED
REDUNDANT_REVIEW_REMOVED: PASS_FOR_ELIGIBLE_NUMERIC_INPUTS
PERSISTENCE_READBACK: PASS
AUTOMATIC_NEXT_QUESTION: PASS_SERVER_NEXT_ELIGIBLE_ID
NEXT_QUESTION_FOCUS: PASS_CODE_PATH
DUPLICATE_SUBMISSION_PROTECTION: PASS_CARD_FLAG_AND_SERVER_VERSION
AMBIGUOUS_ANSWER_NO_WRITE: PASS_CONTRACT_PRESERVED
COMPLEX_PROPOSAL_REVIEW_PRESERVED: PASS
DEPENDENCY_CONFLICT_PROTECTION: PASS

VOICE_FEATURE_DETECTION: PASS_CODE_LEVEL
VOICE_LOCAL_ONLY: PASS_PROCESS_LOCALLY_REQUIRED
VOICE_ES_MX_SUPPORT: PASS_AVAILABILITY_CHECK_WHEN_EXPOSED
VOICE_PERMISSION_HANDLING: PASS_USER_AGENT_ERRORS_TO_SAFE_MESSAGE
VOICE_TRANSCRIPTION_TO_INPUT: PASS_CODE_PATH
VOICE_EDIT_BEFORE_SEND: PASS
VOICE_REAL_BROWSER_VERIFICATION: NOT_VERIFIED
VOICE_BLOCKERS: "Browser support, local language pack and permission remain environment-dependent"

M02_REGRESSION: PASS_STATIC_CONTRACT_UNCHANGED
M03_REGRESSION: PASS_SYNTHETIC_DIRECT_CAPTURE_AND_EXISTING_CONTRACTS
M06_REGRESSION: PASS_REVIEW_PATH_UNCHANGED
M09_REGRESSION: PASS_UNTOUCHED

UNCONFIRMED_WRITES: ZERO
SYNTHETIC_FIXTURE_RESTORED: PASS
REAL_GEMINI_CALLS_ADDED: 0
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
LEGACY_FORMS_MODIFIED: NO
SHARED_MODELS_MODIFIED: NO
DATABASE_SCHEMA_MODIFIED: NO

FILES_MODIFIED: "app/Services/ConversationalM03Adapter.php; app/Controllers/PatientInterviewController.php; app/Config/routes.php; app/views/paciente/entrevista.php; tests/patient-interview/m03-direct-capture-synthetic.php; tests/patient-interview/conversational-engine-contract.php; tests/patient-interview/voice-and-navigation-contract.php; docs/AI_PATIENT_INTERVIEW/23_CONVERSATIONAL_ENGINE_IMPLEMENTATION.md"
DEFECTS_CORRECTED: "Redundant review for complete numeric M03 inputs; insufficient runtime verification of local speech language availability"
REMAINING_BLOCKERS: "Authenticated Chrome acceptance and real local dictation verification"
NEXT_ACTION: "Validar manualmente en Chrome con fixture sintética; no habilitar Gemini ni reconocimiento externo"
```

## Fase 6.8B — dictado real y avance automático M03/P4 (2026-10-13)

La validación se limitó a la Entrevista Conversacional. Se comprobó mediante
el arnés HTTP sintético que la base efectiva es
`clinica_dolor_browser_20261012`, que la sesión sintética y la vista de la
entrevista responden correctamente, y que la lectura no cambia las cinco filas
M03 del paciente sintético ni crea duplicados. El servidor que ya ocupaba el
puerto 8098 era el PID 59900; un segundo proceso (PID 67840) fue creado por un
intento de arranque concurrente de este turno, se identificó como ese proceso
fallido y se detuvo. El PID 59900 quedó intacto.

No se pudo ejecutar la aceptación de voz en un navegador real autenticado: la
sesión de herramientas sólo expuso el navegador interno de Codex, sin Chrome o
Edge disponibles. Las aperturas locales terminaron en la página de error del
propio navegador y no se automatizaron credenciales. Por ello no se afirma que
el permiso del micrófono, `SpeechRecognition`, `processLocally` ni el paquete
`es-MX` estén disponibles en el equipo del propietario. Tampoco se ejecutó una
escritura desde navegador ni una llamada a Gemini.

La ruta de código mantiene estas garantías: el dictado exige reconocimiento
local y `processLocally=true`, inserta el texto en el mismo input para que el
paciente pueda editarlo, y no guarda ni avanza automáticamente por el hecho de
dictar. Para una respuesta M03 numérica completa, el arnés sintético previo
demostró guardado directo, lectura posterior, cálculo de la siguiente pregunta
elegible y protección contra duplicados/versión obsoleta; la comprobación en
DOM y navegador queda pendiente.

### Matriz de captura directa por módulo

| Módulo | Captura directa segura | Requiere revisión/confirmación |
|---|---|---|
| M02 | `No` explícito sólo por la vía segura ya existente y sin conflicto de dependencias | Afirmaciones, parentescos, aclaraciones y conflictos |
| M03 | Inputs numéricos completos y elegibles; P4 validada sintéticamente | Selectores, texto ambiguo, dependencias y contradicciones |
| M04 | Ninguna nueva | Propuesta estructurada de cirugía |
| M05 | Ninguna nueva | Propuesta de antecedentes/condiciones |
| M06 | Ninguna nueva | Medicamentos y campos estructurados |
| M07 | Ninguna; módulo bloqueado según contrato | Requiere el contrato clínico correspondiente |
| M08 | Ninguna nueva | Tratamientos y resultados estructurados |
| M09 | Ninguna nueva | Evaluación estructurada y mapa PNG con previsualización/confirmación |

```yaml
PHASE: 6.8B
SCOPE: CONVERSATIONAL_INTERVIEW_ONLY
BROWSER_SECURE_CONTEXT: NOT_VERIFIED_AUTHENTICATED_BROWSER
SPEECH_RECOGNITION_AVAILABLE: NOT_VERIFIED
PROCESS_LOCALLY_SUPPORTED: NOT_VERIFIED
ES_MX_LOCAL_LANGUAGE_AVAILABLE: NOT_VERIFIED
MICROPHONE_PERMISSION: NOT_VERIFIED
VOICE_REAL_BROWSER_TEST: NOT_VERIFIED
VOICE_TRANSCRIPTION_TO_INPUT: PASS_CODE_PATH_ONLY
VOICE_EDIT_BEFORE_SEND: PASS_CODE_PATH_ONLY
VOICE_EXTERNAL_AUDIO_TRANSMISSION: NO

M03_P4_DIRECT_SAVE: PASS_SYNTHETIC_PHP_ADAPTER
M03_P4_BROWSER_AUTO_ADVANCE: NOT_VERIFIED_AUTHENTICATED_BROWSER
M03_P4_REDUNDANT_REVIEW_REMOVED: PASS_CODE_AND_SYNTHETIC
M03_P4_DUPLICATE_PROTECTION: PASS_SYNTHETIC
OTHER_MODULES_DIRECT_CAPTURE_MATRIX: DOCUMENTED_ABOVE
SAFE_DIRECT_CAPTURE_CANDIDATES: M03_NUMERIC_INPUT; M02_EXPLICIT_SAFE_NO_EXISTING_PATH
REVIEW_REQUIRED_CANDIDATES: M02_AFFIRMATIVE_OR_RELATION; M04; M05; M06; M07; M08; M09

SYNTHETIC_REGRESSIONS: PASS_EXISTING_M03_DIRECT_AND_CONVERSATIONAL_CONTRACTS
PREBROWSER_HTTP_READS: PASS_LOGIN_200_CHAT_200_FIXTURE_UNCHANGED
UNCONFIRMED_WRITES: ZERO
REAL_GEMINI_CALLS: 0
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
LEGACY_FORMS_MODIFIED: NO
FILES_MODIFIED: "docs/AI_PATIENT_INTERVIEW/23_CONVERSATIONAL_ENGINE_IMPLEMENTATION.md"
VOICE_BLOCKERS: "No Chrome/Edge operativo ni sesión sintética autenticada disponible en esta ejecución; permiso, SpeechRecognition, processLocally y es-MX permanecen sin verificación real"
REMAINING_BLOCKERS: "Prueba humana o navegador Chrome/Edge real con fixture sintética; validar transcripción local y avance DOM de M03/P4"
NEXT_ACTION: "Abrir el portal sintético en Chrome/Edge, iniciar sesión sintética manualmente y probar dictado local; no habilitar Gemini ni datos reales"
```
