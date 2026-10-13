# Fase 2.1 y MVP M03 — Informe de implementación y pruebas

Fecha: 11 de octubre de 2026.  
Alcance autorizado: M03/Tabaquismo, tema 1, preguntas 1–5.  
Base de pruebas de escritura: no utilizada; no se escribieron datos en `bd_clinica`.

## Actividades realizadas

- Se leyó la documentación de Fase 1 y Fase 2 antes de modificar código.
- Se implementó un gate CSRF focal para M03.
- Se mantuvo la autorización existente por sesión y paciente/clínica.
- Se añadió control de concurrencia mediante el valor persistido leído.
- Se añadió selector de Formulario tradicional / Entrevista Guiada en la vista Paciente.
- La Entrevista Guiada reutiliza preguntas, tipos, navegación y persistencia existentes; no duplica textos clínicos en JavaScript.
- Se añadió confirmación explícita antes de guardar en modo guiado.
- Se bloquea en modo guiado el cambio `Sí` → `No` cuando existen respuestas dependientes.
- Se añadió opción para continuar con el formulario tradicional.
- No se integró Gemini ni se transmitieron datos externos.

Tiempo: la ejecución HTTP sintética se completó en menos de 30 s de tiempo de herramienta, incluyendo preparación, servidor, preflight, recorrido y limpieza; no es latencia clínica ni tiempo de respuesta por petición. La implementación focal y la validación de contratos se midieron por separado.

## Solución CSRF

Se reutilizó `ClinicalSummarySecurity` como utilidad común y se añadieron `captureCsrfToken()` y `validCaptureCsrf()`. El token es un HMAC derivado de la clave de aplicación, el paciente y un hash de la cookie JWT autenticada. El servidor rechaza token ausente, inválido, de otro paciente o de otra sesión antes de ejecutar el modelo.

La vista tradicional Paciente y la vista Doctor envían `X-M03-CSRF`. El controlador M03 valida el token, el contexto de ruta, la sesión y `assertPatientAccess`. El token no se registra ni se expone como credencial.

## Contrato efectivo de captura

- Las preguntas se cargan desde `pac_temas_modulo_3` y `pac_preguntas_modulo_3`.
- Las respuestas se actualizan en `pac_respuestas_paciente_modulo_3` mediante `AntecedentesNoPatologicosModel`.
- La entrevista no genera SQL y no crea tablas.
- El valor previo se envía como `expectedRespuesta`; si cambió en servidor, el modelo devuelve conflicto `409` y no actualiza.
- La confirmación guiada muestra el valor exacto antes de persistir.
- El cambio destructivo `Sí` → `No` queda bloqueado en modo guiado si hay dependencias con valor.
- La modalidad tradicional conserva su flujo y recibe el mismo gate CSRF.

## Archivos modificados

- `app/Services/ClinicalSummarySecurity.php`
- `app/Controllers/ModulosController.php`
- `app/Models/AntecedentesNoPatologicosModel.php`
- `app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php`
- `app/views/historia-clinica-doctor/antecedentes-personales-no-patologicos.php`
- `tests/patient-interview/m03-csrf-contract.php`
- Documentación `docs/AI_PATIENT_INTERVIEW/*`

No se crearon tablas, migraciones, dependencias ni servicios clínicos paralelos.

## Pruebas ejecutadas

- Sintaxis PHP de seguridad, controlador, modelo y vistas: PASS.
- `tests/patient-interview/m03-csrf-contract.php`: PASS, 6 aserciones, cero escrituras.
  - token ausente: rechazado;
  - token inválido: rechazado;
  - paciente distinto: rechazado;
  - sesión distinta: rechazado;
  - token coincidente: aceptado.
- `tests/clinical-summary/coverage-contract.php`: PASS, 11 aserciones.
- `tests/clinical-summary/narrative-contract.php`: PASS, 24 aserciones.
- Acceso HTTP sin sesión a la ruta Paciente: redirección 302; no se entregó expediente.
- POST HTTP sin cookie/token M03: inicialmente expuso que Apache convertía el código no estándar 419 en 500; se corrigió el endpoint para responder 403 seguro ante CSRF ausente/inválido.
- Revisión de catálogo M03: tema 1 y preguntas 1–5 confirmados; tipos `select`, `input`, `input`, `input`, `input`.
- Se ejecutó servidor PHP aislado sobre `clinica_dolor_demo_20261010_final` con paciente 9301 y clínica 9101: lectura propia, lectura cruzada, escritura propia con CSRF, escritura cruzada rechazada y limpieza posterior: PASS.
- La segunda escritura con `expectedRespuesta` obsoleto devolvió HTTP 409 y no aplicó el cambio; se corrigió el mapeo del controlador que inicialmente la presentaba como 422.
- La limpieza sintética eliminó 25 respuestas temporales M03; el preflight final confirmó 0 respuestas y catálogo intacto.
- Los contratos Node P05 (`contract-check`, `navigation-contract`, `flow-speed-contract`, `audio-mock`) pasaron.
- El navegador local estuvo operativo y confirmó el rechazo sin sesión, pero no se automatizó login ni se reutilizaron credenciales; la visual autenticada queda NOT VERIFIED.
- Fase 3.2: el login sintético normal respondió HTTP 200 con cookie de sesión, sin registrar su valor; el navegador integrado no pudo cargar el servidor local y devolvió `Solicitud no disponible`, por lo que no se fabricó evidencia visual.

## Resultados y limitaciones

La protección CSRF y el control de concurrencia están implementados en servidor. La autorización de paciente reutiliza `AuthMiddleware` y `assertPatientAccess`. La persistencia, aislamiento y limpieza sintéticos quedaron verificados por HTTP; la visual autenticada, la reanudación desde navegador y la accesibilidad requieren una sesión sintética operable en navegador.

La vista tradicional actual inicializa filas vacías al cargar el módulo; la entrevista no agrega una segunda inicialización, pero esta conducta histórica debe ser considerada en la prueba de integridad antes de declarar que visitar una pregunta nunca escribe.

La confirmación usa el diálogo nativo del navegador como mecanismo mínimo accesible. La validación visual con pacientes mayores, teclado y lector de pantalla queda pendiente.

## Estado de entrega

`M03_CSRF_GATE: PASS`

`M03_PATIENT_AUTHORIZATION: PASS`

`M03_SHARED_CAPTURE: PARTIAL`

`M03_CONFIRMATION_FLOW: PARTIAL`

`M03_LEGACY_COMPATIBILITY: PARTIAL`

`M03_INTERVIEW_UI: PARTIAL`

`M03_SYNTHETIC_E2E: NOT_VERIFIED`

`M03_DATA_INTEGRITY: PASS`

`MVP_DELIVERY: CONDITIONAL`

`M03_AUTHENTICATED_BROWSER: NOT_VERIFIED`

`REAL_PATIENT_RELEASE: NOT_AUTHORIZED`

Fase 3.4: se preparó la validación manual asistida en `09_MANUAL_MVP_ACCEPTANCE.md`. No se ejecutó interacción humana ni se simularon capturas; la persistencia posterior queda pendiente de SELECT parametrizados tras recibir evidencia.

Fase 3.4 arquitectónica: se retiró el selector conversacional del formulario M03 y se creó la entrada independiente `/historia-clinica/entrevista`. El chat determinista reutiliza el catálogo y el modelo M03; el login sintético, GET independiente, CSRF 403, captura 200 y recuperación persistida fueron comprobados sobre la base sintética. No se llamó a Gemini.

No se declara el MVP completo ni listo para producción. La siguiente actividad autorizada es ejecutar E2E autenticado exclusivamente sobre copia sintética aislada y completar validación visual; si esa prueba falla, conservar `MVP_DELIVERY: NO-GO` para escritura clínica.
