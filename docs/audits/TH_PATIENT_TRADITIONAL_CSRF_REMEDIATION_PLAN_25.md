# TH-PATIENT-TRADITIONAL-CSRF-REMEDIATION-PLAN-25

## Plan técnico de remediación CSRF P04–P11

**Proyecto:** Clínica del Dolor
**Fecha:** 2026-10-14
**Rama:** `Silvino`
**Entorno de referencia:** XAMPP local / `bd_clinica` autorizada para pruebas controladas
**Fuente principal:** `docs/audits/TH_PATIENT_TRADITIONAL_CSRF_AUDIT_24.md`
**Modalidad de esta fase:** análisis y diseño; sin implementación.

## 1. Diagnóstico confirmado

La auditoría 24 reconcilió 26 operaciones POST de P04–P11 y endpoints comunes. La autenticación efectiva es la cookie `CLINICAJWT`, configurada con `HttpOnly`, `SameSite=Lax`, `Secure` según HTTPS y expiración de siete días. `AuthMiddleware` valida el JWT y `assertPatientAccess()` comprueba la pertenencia del paciente.

El control de autorización de paciente y el control CSRF son responsabilidades distintas. La evidencia previa de aislamiento P1-AUTH-01 no demuestra protección contra solicitudes inducidas desde otro origen.

P05 es la única escritura de este alcance con una defensa CSRF explícita observada: el token se genera en `ClinicalSummarySecurity::captureCsrfToken()`, se deriva de `APP_KEY`, el paciente y el hash de la cookie JWT, se entrega en `data-m03-csrf`, se envía como `X-M03-CSRF` y se valida en `ModulosController:459-460` antes de actualizar.

En las otras 25 escrituras no se observó token obligatorio, comprobación `Origin`/`Referer` ni una política CSRF equivalente en servidor. Esto justifica planificar remediación, pero no equivale a una explotación HTTP demostrada.

## 2. Aplicabilidad por endpoint

### 2.1 Clasificación primaria

| Clasificación | Cantidad | Endpoints |
|---|---:|---|
| `CSRF_REQUIRED` | 26 | Todas las operaciones POST JSON de P04–P11 y los endpoints comunes de editor, comentarios y finalización; la cookie JWT se envía automáticamente por el navegador |
| `CSRF_ALREADY_PROTECTED` | 1 | P05: `/paciente/editar-cuestionario-modulo3` |
| `CSRF_REMEDIATION_REQUIRED` | 25 | P04, P06, P07, P08, P09, P10, P11 y editor/comentarios/finalización comunes |
| `CSRF_NOT_APPLICABLE` | Lecturas | Vistas GET, cargas de preguntas/comentarios y lectura de imagen P11; requieren autorización, pero no cambian estado según el contrato inspeccionado |
| `CSRF_UNVERIFIED` | 0 como clasificación estática | La aplicabilidad por código es determinable; la explotación mediante navegador/origen cruzado permanece sin prueba dinámica |

`SameSite=Lax` se conserva como defensa adicional. No se clasifica como sustituto de un token u otra comprobación CSRF principal.

### 2.2 Matriz real de escrituras

| Módulo | Endpoint(s) | Controlador y evidencia | Estado actual | Propuesta |
|---|---|---|---|---|
| P04 | `POST /paciente/agregar-enfermedad-antecedentes`; `POST /paciente/editar-enfermedad-antecedentes`; `POST /paciente/eliminar-enfermedad-antecedentes` | `routes.php:42-44`; `ModulosController:279,305,359` | `CSRF_REMEDIATION_REQUIRED` | Token común para paciente, validado después de autenticación y antes del modelo |
| P05 | `POST /paciente/editar-cuestionario-modulo3` | `routes.php:47`; `ModulosController:423`, validación `:459-460`; vista `antecedentes-personales-no-patologicos.php:788-791,864-867` | `CSRF_ALREADY_PROTECTED` | Preservar; probar regresión y evaluar reutilización controlada |
| P06 | `POST /paciente/agregar-cirugia-antecedentes`; `POST /paciente/editar-cirugia-antecedentes`; `POST /paciente/eliminar-cirugia-antecedentes` | `routes.php:50-52`; `ModulosController:496,538,586` | `CSRF_REMEDIATION_REQUIRED` | Token común y respuesta 403 uniforme |
| P07 | `POST /paciente/editar-cuestionarioV1-modulo5`; `POST /paciente/editar-cuestionarioV2-modulo5` | `routes.php:55-56`; `ModulosController:633,670` | `CSRF_REMEDIATION_REQUIRED` | Token común sin cambiar contratos V1/V2 |
| P08 | `POST /paciente/agregar-medicacion-actual`; `POST /paciente/editar-medicacion-actual`; `POST /paciente/eliminar-medicacion-actual` | `routes.php:59-61`; `ModulosController:707,742,778` | `CSRF_REMEDIATION_REQUIRED` | Token común preservando CRUD y no-op de guardado |
| P09 | `POST /paciente/editar-cuestionario-modulo7` | `routes.php:64`; `ModulosController:816` | `CSRF_REMEDIATION_REQUIRED` | Token común; `X-Requested-With` queda sólo como señal adicional |
| P10 | `POST /paciente/agregar-procedimiento-dolor-modulo8`; `POST /paciente/editar-procedimiento-dolor-modulo8`; `POST /paciente/eliminar-procedimiento-dolor-modulo8`; `POST /paciente/editar-tratamiento-dolor-modulo8` | `routes.php:67-70`; `ModulosController:861-880,891,906,921,938` | `CSRF_REMEDIATION_REQUIRED` | Validar token en `p10Payload()` sin retirar `assertPatientAccess()` |
| P11 | `POST /paciente/editar-img-frente-modulo9`; `POST /paciente/eliminar-img-frente-modulo9`; `POST /paciente/editar-img-espalda-modulo9`; `POST /paciente/eliminar-img-espalda-modulo9`; `POST /paciente/editar-evaluacion-dolor-modulo9` | `routes.php:74-80`; `ModulosController:954-970,999,1012,1025,1038,1053` | `CSRF_REMEDIATION_REQUIRED` | Validar token antes de guardar/eliminar imagen o evaluación |
| Comunes | `POST /paciente/guardar-contenido-editor`; `POST /paciente/agregar-comentario-modulo`; `POST /paciente/eliminar-comentario-modulo`; `POST /paciente/finalizar-modulo-paciente` | `routes.php:83,86-87,90`; `ModulosController:1072,1099,1149,1177` | `CSRF_REMEDIATION_REQUIRED` | Aplicar sólo al contexto paciente y conservar autorización/contrato de finalización |

### 2.3 Lecturas no mutantes

Las siguientes rutas se mantienen como `CSRF_NOT_APPLICABLE` por su contrato de lectura, sin eliminar autenticación:

- Vista de módulo: `GET /historia-clinica/{modulo}/paciente/{idPaciente}` (`routes.php:187`, `ModulosController:134-201`).
- Preguntas P04–P11 y tratamientos: `routes.php:215-226`, atendidas por `BusquedasController:114-259` mediante `guardPatient():25-29`.
- Comentarios: `GET /buscar/contenido-comentarios-modulos/{idPaciente}/{idRol}/{idModulo}` (`routes.php:228`, `BusquedasController:262-266`).
- Imagen P11: `GET /paciente/imagen-evaluacion-dolor/{orientacion}/{idPaciente}` (`routes.php:73`, `ModulosController:978-997`), con autorización antes de `readfile`.

## 3. Evaluación de P05

### Funcionamiento observado

1. La vista calcula `ClinicalSummarySecurity::captureCsrfToken((int)$data['idPaciente'])` y lo expone como `data-m03-csrf`.
2. Las dos operaciones AJAX de P05 lo envían como `X-M03-CSRF` y también conservan `Content-Type: application/json`.
3. El controlador acepta la cabecera o el campo `csrf_token` del JSON.
4. `validCaptureCsrf()` reconstruye el valor esperado usando el paciente, el hash SHA-256 de `CLINICAJWT` y `APP_KEY`, y compara con `hash_equals`.
5. El rechazo ocurre antes de invocar `AntecedentesNoPatologicosModel`.

### Limitaciones que deben conservarse en el diseño

- El token no tiene un nombre genérico; está acoplado al contrato `M03`.
- La implementación es determinista mientras no cambie la cookie JWT; su validez práctica sigue el ciclo de vida de esa cookie.
- El token se encuentra en el DOM porque el navegador debe enviarlo; la protección presupone ausencia de XSS.
- No existe validación `Origin`/`Referer` como capa adicional.
- No se debe copiar automáticamente el nombre `X-M03-CSRF` a otros módulos.

La opción recomendada es extraer un contrato genérico compatible con este mecanismo o encapsularlo en el servicio existente, sin romper P05. No se recomienda añadir una segunda familia de tokens incompatible.

## 4. Arquitectura mínima propuesta

### 4.1 Contrato recomendado

Crear en el servicio existente `app/Services/ClinicalSummarySecurity.php` una API genérica, pendiente de aprobación e implementación:

- `patientWriteCsrfToken(int $patientId): string`.
- `validPatientWriteCsrf(int $patientId, ?string $token): bool`.
- Constante de propósito separada de `m03-capture`, por ejemplo `patient-clinical-write:v1`.

El token debe derivarse de `APP_KEY`, la identidad de sesión JWT actual y el paciente autorizado. No debe depender únicamente de un `idPaciente` enviado por el navegador. La validación debe ejecutarse después de autenticar y autorizar el paciente, y antes de invocar el modelo.

### 4.2 Transporte

- Vista: atributo `data-patient-csrf` en el contenedor raíz de cada módulo que escriba.
- AJAX/fetch: cabecera `X-Patient-CSRF` más `Content-Type` existente.
- Formularios HTML, si se confirma alguno: campo oculto equivalente y validación del mismo contrato.
- Respuesta inválida o ausente: HTTP 403 y mensaje genérico, sin revelar token, JWT ni detalles internos.

### 4.3 Controlador

Añadir una única guardia reutilizable en `ModulosController`, aplicada sólo cuando la petición pertenece al contexto paciente (`historia-clinica`). El orden propuesto es:

1. Decodificar y validar JWT mediante los mecanismos existentes.
2. Resolver el paciente de la sesión/URL y ejecutar `assertPatientAccess()`.
3. Leer la cabecera o el campo compatible.
4. Validar el token genérico.
5. Rechazar con 403 si falta o no coincide.
6. Continuar con las validaciones clínicas y el modelo sin modificar su contrato.

P10 y P11 deben incorporar la guardia en sus helpers `p10Payload()` y `p11Payload()`. Editor, comentarios y finalización necesitan una guardia equivalente sin confiar en `idRol` como identidad.

Los mismos nombres de rutas sirven para el grupo Doctor. Antes de implementar, se debe confirmar la rama real de vista/rol en cada método y aplicar la exigencia únicamente a la ruta paciente; no se debe romper el flujo Doctor dentro de una remediación P04–P11.

### 4.4 Compatibilidad y sesión

- No cambiar la firma ni algoritmo de JWT.
- No invalidar sesiones vigentes sólo por desplegar la guardia.
- Token ausente o inválido debe requerir recarga de la vista para obtener un token válido; no debe auto-guardar ni finalizar.
- Un error de sesión debe ser comprensible para el paciente y permitir reintentar sin perder campos aún no persistidos.
- `SameSite=Lax`, `HttpOnly` y `Secure` se conservan.
- `Origin`/`Referer` pueden verificarse como defensa adicional, con política clara para navegadores que no los envíen; no sustituyen automáticamente al token.

## 5. Tabla de implementación

### Cambios obligatorios, sujetos a aprobación

| Archivo real | Módulo | Cambio propuesto | Justificación | Regresión | Prueba | Prioridad |
|---|---|---|---|---|---|---|
| `app/Services/ClinicalSummarySecurity.php` | P04–P11 | Añadir API genérica compatible con P05 | Evita duplicar fórmulas y nombres | Baja si se conserva P05 | Token correcto/ausente/inválido/otra sesión | P1 |
| `app/Controllers/ModulosController.php` | P04–P11 | Guardia CSRF en 25 escrituras sin protección y helpers P10/P11 | Cierre server-side fail-closed | Media: controlador compartido con Doctor | Matriz POST paciente/Doctor, 403 y no-op | P1 |
| `app/views/historia-clinica-paciente/antecedentes-familiares.php` | P04 | Inyectar token y añadir cabecera en CRUD/finalización | Cliente debe transportar el token | Media: JS AJAX existente | CRUD propio y respuesta 403 | P1 |
| `app/views/historia-clinica-paciente/antecedentes-personales-quirurgicos.php` | P06 | Inyectar token y añadir cabecera en escrituras/finalización | Igualar contrato servidor | Media | CRUD y finalización sin regresión | P1 |
| `app/views/historia-clinica-paciente/antecedentes-personales-patologicos.php` | P07 | Inyectar token en V1/V2 y finalización | Cubrir ambos contratos clínicos | Media | V1/V2, dependencias y avance | P1 |
| `app/views/historia-clinica-paciente/medicacion-actual.php` | P08 | Inyectar token en CRUD/finalización | Cubrir operaciones de medicación | Media | agregar/editar/eliminar/no-op | P1 |
| `app/views/historia-clinica-paciente/medicacion-control-dolor.php` | P09 | Añadir cabecera al guardado/finalización | `X-Requested-With` no es CSRF | Media | respuesta, dependencias y finalización | P1 |
| `app/views/historia-clinica-paciente/procedimientos-control-dolor.php` | P10 | Añadir token a `postJson` y contenedor raíz | Cubrir procedimientos y tratamientos | Media | ambos listados y errores de red | P1 |
| `app/views/historia-clinica-paciente/evaluacion-dolor.php` | P11 | Añadir token al helper POST | Cubrir imágenes y evaluación | Alta: payload de imagen/mapa | mapas, evaluación, no-op y autorización | P1 |

### Cambios condicionales

| Archivo | Condición antes de modificar | Motivo |
|---|---|---|
| `app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php` | Sólo si se decide migrar del contrato M03 al genérico | P05 ya está protegido; debe conservarse como regresión controlada |
| `app/Controllers/BusquedasController.php` | Sólo si se demuestra que una lectura GET cambia estado | Los GET no requieren CSRF, pero sí autorización y ausencia de escrituras |
| `app/Middleware/AuthMiddleware.php` | Sólo si se aprueba una defensa adicional de origen global | No es necesaria para el token principal y puede afectar Doctor/otras áreas |
| `app/Config/routes.php` | Sólo si la implementación necesita separar explícitamente grupos paciente/Doctor | Las rutas actuales deben preservarse por defecto |

### Mejoras opcionales, fuera del cierre

- Política documentada de `Origin`/`Referer` como defensa adicional.
- Rotación explícita de nonce server-side si se incorpora almacenamiento de sesión adecuado.
- Métricas o auditoría de rechazos 403 sin registrar tokens ni datos clínicos.
- Unificación posterior de nombres de cabecera y mensajes de error.

Estas mejoras no deben mezclarse con la corrección mínima.

## 6. Archivos a preservar

- Modelos clínicos y esquemas.
- `app/Config/routes.php`, salvo separación explícita aprobada.
- `app/Middleware/AuthMiddleware.php`, salvo defensa de origen aprobada.
- P05 y `ClinicalSummarySecurity::captureCsrfToken()` durante la primera implementación.
- CSS, componentes visuales y JavaScript compartido no imprescindible.
- Entrevista Conversacional, IA, Doctor, recetas, PDF, COFEPRIS, dashboard y `.env`.

## 7. Secuencia de implementación propuesta

1. Confirmar con el responsable la política: token genérico ligado a JWT/paciente, alcance sólo paciente y formato de error 403.
2. Implementar y probar primero la API de servicio sin modificar modelos ni esquema.
3. Añadir la guardia a un endpoint P04 en una rama de trabajo local y comprobar que la rama Doctor no quede accidentalmente bloqueada.
4. Propagar el token a P04, P06–P11 y endpoints comunes.
5. Mantener P05 sin cambios inicialmente y ejecutar su regresión.
6. Ejecutar lint, sintaxis JavaScript y `git diff --check` antes de cualquier prueba con datos.
7. Ejecutar la matriz dinámica con dos pacientes/clínicas sintéticos y registrar respuestas HTTP, filas afectadas y no-op.
8. Corregir sólo regresiones demostradas.
9. Actualizar la auditoría de seguridad con resultados; no marcar producción como aprobada sin evidencia completa.

## 8. Plan de pruebas futuro — no ejecutado en esta fase

| Caso | Preparación | Resultado esperado |
|---|---|---|
| A. Petición legítima | JWT de paciente sintético + token del mismo paciente | HTTP 2xx, una operación autorizada y respuesta normal |
| B. Token ausente | Misma petición sin `X-Patient-CSRF` | HTTP 403, cero filas modificadas |
| C. Token inválido | Cadena alterada | HTTP 403, cero filas modificadas |
| D. Token de otra sesión | Token generado para otro JWT | HTTP 403, cero filas modificadas |
| E. Sin sesión | Cookie ausente/expirada | Redirección o 401/403 según contrato, cero filas modificadas |
| F. Otro paciente | JWT de A con registro/paciente de B | Rechazo por autorización, cero filas modificadas |
| G. AJAX legítimo | `fetch` real de cada vista | Header presente, 2xx y UI recuperable |
| H. Persistencia propia | Guardar y recuperar en fixture descartable | Valor autorizado persiste una sola vez |
| I. No-op | Repetir respuesta ya guardada | Sin `UPDATE` redundante según contrato del módulo |
| J. Regresión P05 | Token M03 correcto, ausente y alterado | PASS de token correcto; 403 en los otros dos |
| K. GET | Abrir P04–P11 y observar consultas/escrituras | Lectura sin escritura; si existe escritura, bloquear cierre y separar remediación |

La prueba F debe distinguir autorización del paciente de CSRF. La prueba K debe ejecutarse antes de cualquier prueba mutante para evitar sembrar datos en una base no aislada.

## 9. Riesgos de regresión

- Controladores comunes registran rutas para paciente y Doctor; una guardia aplicada sin discriminar vista podría bloquear operaciones Doctor.
- P10 y P11 usan helpers propios de payload; omitirlos dejaría una brecha pese a proteger otros métodos.
- P04–P11 tienen varios helpers AJAX con payloads distintos; una cabecera añadida sólo a un helper no cierra el módulo completo.
- Carga de una vista antigua o caché de asset podría producir token ausente; el error debe ser recuperable con recarga, sin guardar implícitamente.
- La expiración o sustitución de JWT puede dejar un token DOM obsoleto; debe rechazarse de forma controlada.
- P05 no debe perder su contrato `X-M03-CSRF` durante la migración o abstracción.
- No se deben registrar tokens, cookies, PIN, JWT ni datos clínicos en logs.

## 10. Criterios de aceptación

- Cada POST listado tiene una política explícita y validada en servidor.
- P05 continúa rechazando token ausente o inválido.
- P04 y P06–P11 rechazan token ausente, inválido y perteneciente a otra sesión.
- El rechazo ocurre antes del modelo y no modifica persistencia.
- La autorización por paciente y clínica continúa siendo obligatoria.
- No existe una vía alternativa por parámetros `idPaciente`, `idRol` o AJAX.
- Los GET de carga no realizan escrituras; cualquier excepción queda bloqueada y documentada.
- Los flujos propios conservan navegación, guardado, recuperación y finalización.
- La matriz visual y responsive no se declara PASS si no se ejecuta en navegador.
- No se modifican módulos fuera de P04–P11 ni se hacen commits/despliegues en la fase de implementación sin autorización.

## 11. Bloqueos y riesgos residuales

- No hay evidencia HTTP dinámica de explotación CSRF en la auditoría 24.
- No hay matriz cross-clinic completa para P1-AUTH-01.
- Debe confirmarse la separación paciente/Doctor en cada método antes de una guardia centralizada.
- Los efectos secundarios durante GET previamente documentados permanecen abiertos.
- La presencia de JWT en cookie hace incorrecto clasificar las escrituras como `CSRF_NOT_APPLICABLE`.
- No se debe asumir que P05 es una solución universal sin aprobar su generalización.
- Persisten los riesgos de autorización, documentos, PDF, descargas y COFEPRIS fuera de este plan; no forman parte de la implementación P04–P11.

## 12. Control de cambios de esta fase

```yaml
TH_PATIENT_TRADITIONAL_CSRF_REMEDIATION_PLAN_25:
  STATUS: READY_FOR_OWNER_APPROVAL
  SOURCE_AUDIT_REVIEWED: YES
  ENDPOINT_MATRIX_RECONCILED: YES
  CSRF_REQUIRED_ENDPOINTS: 26
  CSRF_ALREADY_PROTECTED_ENDPOINTS: 1
  CSRF_NOT_APPLICABLE_ENDPOINTS: READ_ONLY_GETS
  CSRF_UNVERIFIED_ENDPOINTS: 0_STATIC_CLASSIFICATION
  CSRF_REMEDIATION_REQUIRED_ENDPOINTS: 25
  P05_MECHANISM: COOKIE_JWT_BOUND_HMAC_TOKEN_STATICALLY_VERIFIED
  CENTRALIZED_PROTECTION_FEASIBLE: YES_WITH_PATIENT_DOCTOR_BRANCH_GUARD
  RECOMMENDED_APPROACH: GENERIC_SERVICE_CONTRACT_PLUS_SERVER_SIDE_GUARD
  FILES_TO_MODIFY: SERVICE_CONTROLLER_PATIENT_VIEWS_ONLY_AFTER_APPROVAL
  FILES_TO_PRESERVE: MODELS_ROUTES_SCHEMA_AUTH_UNLESS_EXPLICITLY_APPROVED
  PROPOSED_TESTS: A-K_DEFINED_NOT_EXECUTED
  REGRESSION_RISKS: SHARED_CONTROLLER_P10_P11_P05_SESSION_EXPIRY_GET_WRITES
  BLOCKERS: OWNER_APPROVAL_AND_SYNTHETIC_WRITE_FIXTURE
  DOCUMENT_CREATED: YES
  CLINICAL_CODE_MODIFIED: NO
  DATABASE_MODIFIED: NO
  HTTP_MUTATIONS_EXECUTED: NO
  ENV_MODIFIED: NO
  CONVERSATIONAL_INTERVIEW_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  READY_FOR_OWNER_APPROVAL: YES
  NEXT_IMPLEMENTATION_PHASE: OWNER_APPROVAL_THEN_MINIMAL_CODE_CHANGE
  PRODUCTION_READY: NO
```
