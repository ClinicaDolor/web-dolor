# 04 — Portal Paciente y flujos clínicos

## Portal Paciente

El grupo `/historia-clinica` incluye acceso, login, dashboard y ruta genérica `/{modulo}/paciente/{idPaciente}`; los endpoints de módulos se registran en la raíz compartida. `AuthMiddleware('historia-clinica')` y `assertPatientAccess` aparecen en los controladores revisados. La correspondencia del paciente mostrado con la sesión, el rechazo cruzado y la recuperación de cada módulo requieren runtime sintético.

## Historia Clínica Tradicional

Los módulos conservan preguntas, respuestas, dependencias, comentarios y finalización; existen POST para captura/edición/eliminación y endpoints de imagen P11. Se observan SQL heredados y reglas de siembra/normalización en modelos; la documentación histórica no es consistente sobre si GET escribe en todos los módulos. Debe resolverse con trazas/queries en base descartable antes de afirmar integridad.

## Entrevista Conversacional

`PatientInterviewController` expone índice y endpoints de interpretación/captura para M02–M09, incluido mapa M09. El código actual usa auth de Historia, `assertPatientAccess` y token `ClinicalSummarySecurity` en rutas de captura examinadas; los adaptadores mantienen una ruta determinista/fallback y el proveedor Gemini es opt-in según entorno. No se verificaron límites de sesión, rate limit, privacidad de trazas, consentimiento, audio/STT, idempotencia real ni persistencia en esta fase.

## Integridad clínica

El sistema separa captura, interpretación y persistencia en varios flujos, lo cual debe conservarse. Riesgos: doble escritura, reintentos, versionado, respuestas previamente guardadas, campos dependientes, limpieza al responder No, orden clínico y finalización. Cualquier cambio debe probarse con dos pacientes sintéticos y baseline restaurable; no se ejecutó aquí.

## Estado

`PARTIAL_STATIC`. No se realizaron acciones clínicas, no se consultó la DB y no se modificaron modelos/controladores.
