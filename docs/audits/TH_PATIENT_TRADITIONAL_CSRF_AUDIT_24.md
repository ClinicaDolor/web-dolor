# TH-PATIENT-TRADITIONAL-CSRF-AUDIT-24

## Auditoría estática de CSRF y autorización P04–P11

**Proyecto:** Clínica del Dolor
**Fecha:** 2026-10-14
**Rama:** `Silvino`
**HEAD observado:** `59654e05dc9f931c21b5392238961bcddb3ab2e4`
**Alcance:** Historia Clínica Tradicional del Paciente, módulos P04–P11.
**Modalidad:** inspección estática únicamente.

## Resultado ejecutivo

La revisión confirma que las rutas de P04–P11 pasan por autenticación JWT y por una comprobación de pertenencia del paciente antes de acceder a los modelos. También confirma una defensa CSRF explícita en P05: el token se deriva de `APP_KEY`, del identificador del paciente y de un hash de la cookie JWT; se emite en la vista y se valida en el controlador.

En P04 y P06–P11, incluidos los endpoints comunes de editor, comentarios y finalización, no se observó validación CSRF explícita, comprobación `Origin`/`Referer` ni otro token anti-CSRF en los controladores revisados. La cookie JWT usa `HttpOnly` y `SameSite=Lax`, lo que constituye una mitigación de navegador, pero no sustituye una defensa CSRF explícita para operaciones de escritura.

Esto se registra como **defensa CSRF incompleta con explotación HTTP no verificada**. No se corrige en esta actividad.

**Estado global:** `PARTIAL — REMEDIATION_REQUIRED`
**Cambios de aplicación:** ninguno.
**Base de datos, HTTP y PDO durante esta auditoría:** no utilizados.

## Reconciliación de P1-AUTH-01

La evidencia funcional reportada para `P04-AUTH-20261014121758` queda incorporada como evidencia previa, no como una nueva ejecución de esta auditoría:

| Evidencia | Resultado reportado | Alcance real |
|---|---|---|
| Lectura y AJAX propios sin escritura | PASS | Pacientes sintéticos 10/28, clínica 1 |
| Lectura anónima y cruzada | PASS | HTTP autorizado en el entorno reportado |
| Eliminación propia autorizada | PASS | Registro sintético probado |
| Eliminación cruzada | PASS | El registro del otro paciente no fue modificado |
| Registro inexistente | PASS | Respuesta controlada |
| Eliminación sin sesión | PASS | Rechazo reportado |
| Aislamiento entre clínicas | NO VERIFICADO | La ejecución fue intra-clínica |
| Login real por PIN | NO VERIFICADO en esa corrida | El contexto fue preparado por el harness |

La corrección estática de eliminación se conserva en `app/Models/AntecedenteFamiliarModel.php:415-423`: la sentencia parametrizada condiciona conjuntamente `id` e `id_paciente`. El controlador obtiene y valida el paciente mediante `AuthMiddleware::assertPatientAccess()` antes de llamar al modelo. Esta evidencia no cierra los riesgos CSRF.

## Componentes de autenticación y credenciales

| Componente | Evidencia | Evaluación |
|---|---|---|
| Cookie de sesión | `app/Models/LoginModel.php:80-107` | `CLINICAJWT`, `HttpOnly`, `SameSite=Lax`, `Secure` según HTTPS, expiración de 7 días |
| Validación de JWT | `app/Middleware/AuthMiddleware.php:16-60` | Firma HS256 con `APP_KEY`, expiración y vista comprobadas |
| Acceso al paciente | `app/Middleware/AuthMiddleware.php:63-96` | Paciente: `id_usuario == pc_paciente.id`; clínica: `id_clinica == pc_paciente.id_clinica` |
| Token CSRF P05 | `app/Services/ClinicalSummarySecurity.php:66-75` | Ligado a cookie JWT, paciente y `APP_KEY`; comparación constante |
| Middleware CSRF global | No encontrado en los componentes revisados | No existe una defensa global visible para P04–P11 |

La cookie es la credencial efectiva de las peticiones AJAX. Los identificadores enviados por el navegador no sustituyen la comprobación de pertenencia del servidor, pero sí forman parte de la superficie que debe seguir validándose.

## Inventario de endpoints

### Vistas y lecturas

| Módulo | Endpoint | Implementación | Autorización estática | CSRF |
|---|---|---|---|---|
| P04–P11 | `GET /historia-clinica/{modulo}/paciente/{idPaciente}` | `routes.php:187` → `ModulosController::moduloVistaPaciente:134-201` | `authPermisos()` y `assertPatientAccess()` en `:138-139` | N/A, lectura |
| P04 | `GET /buscar/contenido-preguntas-modulo-2/{idPaciente}/{idRol}` | `routes.php:215` → `BusquedasController::contenidoPreguntasM2:114-118` | `guardPatient():25-29` | N/A, lectura |
| P05 | `GET /buscar/contenido-preguntas-modulo-3/{idPaciente}/{idRol}/{idCuestionario}` | `routes.php:216` → `:120-130` | `guardPatient()`; el rol efectivo se deriva de la sesión en `:127` | N/A, lectura |
| P06 | `GET /buscar/contenido-preguntas-modulo-4/{idPaciente}/{idRol}` | `routes.php:217` → `:132-137` | `guardPatient()`; rol efectivo en `:133-136` | N/A, lectura |
| P07 | `GET /buscar/contenido-preguntasV1-modulo-5/...` y `...V2...` | `routes.php:218-219` → `:139-155` | `guardPatient()`; validación de cuestionario y rol efectivo | N/A, lectura |
| P08 | `GET /buscar/contenido-preguntas-modulo-6/{idPaciente}/{idRol}` | `routes.php:220` → `:158-171` | `guardPatient()` | N/A, lectura |
| P09 | `GET /buscar/contenido-preguntas-modulo-7/{idPaciente}/{idRol}/{idCuestionario}` | `routes.php:221` → `:174-194` | `guardPatient()` y cuestionario entero | N/A, lectura |
| P10 | `GET /buscar/contenido-preguntas-modulo-8/{idPaciente}/{idRol}` y tratamiento | `routes.php:222-223` → `:196-238` | `guardPatient()` | N/A, lectura |
| P11 | `GET /buscar/contenido-evaluacion-frente-modulo-9/...`, espalda y cuestionario | `routes.php:224-226` → `:240-259` | `guardPatient()` | N/A, lectura |
| P04–P11 | `GET /buscar/contenido-comentarios-modulos/{idPaciente}/{idRol}/{idModulo}` | `routes.php:228` → `BusquedasController:262-266` | `guardPatient()` | N/A, lectura |
| P11 | `GET /paciente/imagen-evaluacion-dolor/{orientacion}/{idPaciente}` | `routes.php:73` → `ModulosController::pacienteImagenEvaluacionDolor:978-997` | `authAny()` y `assertPatientAccess()` en `:980-985` | N/A, lectura; `readfile` después de autorizar |

### Operaciones de escritura

Todas las filas siguientes son POST JSON y se registran como operaciones con efecto de estado. La columna **CSRF** significa “validación explícita observada en el controlador”, no la mera presencia de autenticación.

| Módulo | Rutas | Controlador | Autorización | CSRF estático |
|---|---|---|---|---|
| P04 | `/paciente/agregar-enfermedad-antecedentes`, `/paciente/editar-enfermedad-antecedentes`, `/paciente/eliminar-enfermedad-antecedentes` | `ModulosController:279`, `:305`, `:359`; rutas `42-44` | `AuthMiddleware($view)` + `assertPatientAccess()` antes del modelo | **NO OBSERVADO** |
| P05 | `/paciente/editar-cuestionario-modulo3` | `ModulosController:423`; ruta `47` | `authPermisos()` + `assertPatientAccess()` | **PASS estático**: `X-M03-CSRF`/`csrf_token`, controlador `:459-460` |
| P06 | `/paciente/agregar-cirugia-antecedentes`, `/paciente/editar-cirugia-antecedentes`, `/paciente/eliminar-cirugia-antecedentes` | `ModulosController:496`, `:538`, `:586`; rutas `50-52` | POST, `authPermisos()` y `assertPatientAccess()` | **NO OBSERVADO** |
| P07 | `/paciente/editar-cuestionarioV1-modulo5`, `/paciente/editar-cuestionarioV2-modulo5` | `ModulosController:633`, `:670`; rutas `55-56` | POST, `authPermisos()` y `assertPatientAccess()` | **NO OBSERVADO** |
| P08 | `/paciente/agregar-medicacion-actual`, `/paciente/editar-medicacion-actual`, `/paciente/eliminar-medicacion-actual` | `ModulosController:707`, `:742`, `:778`; rutas `59-61` | POST, `authPermisos()` y `assertPatientAccess()` | **NO OBSERVADO** |
| P09 | `/paciente/editar-cuestionario-modulo7` | `ModulosController:816`; ruta `64` | POST, `authPermisos()` y `assertPatientAccess()` | **NO OBSERVADO** |
| P10 | `/paciente/agregar-procedimiento-dolor-modulo8`, `/paciente/editar-procedimiento-dolor-modulo8`, `/paciente/eliminar-procedimiento-dolor-modulo8`, `/paciente/editar-tratamiento-dolor-modulo8` | `ModulosController:891`, `:906`, `:921`, `:938`; rutas `67-70` | `p10Payload():861-880`, `authAny()` + `assertPatientAccess()` | **NO OBSERVADO** |
| P11 | `/paciente/editar-img-frente-modulo9`, `/paciente/eliminar-img-frente-modulo9`, `/paciente/editar-img-espalda-modulo9`, `/paciente/eliminar-img-espalda-modulo9`, `/paciente/editar-evaluacion-dolor-modulo9` | `ModulosController:999`, `:1012`, `:1025`, `:1038`, `:1053`; rutas `74-80` | `p11Payload():954-970`, `authAny()` + `assertPatientAccess()` | **NO OBSERVADO** |
| P04–P11 | `/paciente/guardar-contenido-editor`, `/paciente/agregar-comentario-modulo`, `/paciente/eliminar-comentario-modulo`, `/paciente/finalizar-modulo-paciente` | `ModulosController:1072`, `:1099`, `:1149`, `:1177`; rutas `83`, `86-87`, `90` | Auth y `assertPatientAccess()` en cada flujo; finalización limita `idModulo` a 1–9 | **NO OBSERVADO** |

## Cobertura de CSRF observada en las vistas

| Módulo | Evidencia de petición | Resultado |
|---|---|---|
| P04 | POST JSON en `antecedentes-familiares.php:660-663` y `:746-749`; sólo `Content-Type` | No envía token CSRF |
| P05 | POST JSON en `antecedentes-personales-no-patologicos.php:788-791` y `:864-867`; `X-M03-CSRF` leído desde `data-m03-csrf` en `:933` | Compatible con la validación del controlador |
| P06 | POST JSON en `antecedentes-personales-quirurgicos.php:81-84` y finalización `:336-339` | No envía token CSRF |
| P07 | POST JSON en `antecedentes-personales-patologicos.php:320-323`, `:667-670` y finalización `:869-872` | No envía token CSRF |
| P08 | POST JSON en `medicacion-actual.php:72`, `:163` | No envía token CSRF |
| P09 | POST JSON en `medicacion-control-dolor.php:101` y finalización `:105`; `X-Requested-With` no es un token CSRF | No envía token CSRF |
| P10 | POST JSON en `procedimientos-control-dolor.php:60` | No envía token CSRF |
| P11 | POST JSON en `evaluacion-dolor.php:112`; el GET usa `X-Requested-With` en `:124` | No envía token CSRF |

## Hallazgos

### P1-CSRF-01 — Defensa anti-CSRF incompleta en escrituras P04 y P06–P11

**Estado:** `CONFIRMED_IN_CODE / HTTP_EXPLOITATION_NOT_VERIFIED`
**Prioridad:** P1 para remediación coordinada
**Superficie:** 26 operaciones POST de P04–P11 y endpoints comunes, agrupadas arriba.
**Causa:** los controladores verifican método, JWT y pertenencia del paciente, pero no muestran un token CSRF común, validación `Origin`/`Referer` ni equivalente para estas operaciones. `SameSite=Lax` reduce algunos envíos cross-site, pero no constituye por sí solo el contrato de protección requerido.
**Impacto potencial:** una petición de escritura originada desde un contexto no confiable podría intentar reutilizar la cookie de sesión, según navegador, contexto same-site y política efectiva. No se ha demostrado explotación por HTTP en esta auditoría.
**Acción propuesta:** diseñar una estrategia común de token por sesión/paciente o protección de origen, con pruebas negativas para todos los endpoints; no aplicar aquí.

### P2-CSRF-02 — P05 tiene protección específica, no una política común

**Estado:** `STATIC_PASS_FOR_P05 / COVERAGE_NOT_GENERALIZED`
`captureCsrfToken()` usa la cookie JWT actual y el paciente; P05 lo entrega en el DOM y lo envía como `X-M03-CSRF`; el controlador valida antes de actualizar. La cobertura no se extiende automáticamente a P04 ni a P06–P11.

### R-AUTH-CTX-03 — Parámetros de rol y contexto enviados por cliente

**Estado:** `RESIDUAL_REVIEW`
Varias rutas incluyen `idRol` y algunos payloads lo envían. Los buscadores P05–P11 derivan el rol efectivo de la sesión en el controlador, pero P04 y los flujos comunes conservan parámetros de contexto heredados. Esto no se clasifica como CSRF ni se modifica en esta auditoría; requiere revisión específica de confianza en parámetros.

### R-GET-WRITE-04 — Efectos secundarios durante GET previamente documentados

**Estado:** `OPEN / OUTSIDE_THIS_AUDIT`
La revisión CSRF no convierte los GET de carga en operaciones seguras si alguna vista/modelo realiza preparación o siembra durante la renderización. Los riesgos de escrituras durante GET de módulos anteriores permanecen documentados y deben auditarse por separado.

### R-AUTH-RECOVERY-05 — Evidencia P1-AUTH-01 limitada a contexto intra-clínica

**Estado:** `PARTIAL`
La evidencia reportada demuestra el caso de clínica 1 con pacientes sintéticos 10/28, pero no una matriz independiente de dos clínicas ni login por PIN reproducido en esa ejecución.

## Separación de autorización y CSRF

| Control | Estado |
|---|---|
| JWT válido y vista correcta | Observado en `AuthMiddleware` |
| Pertenencia del paciente | Observada en `assertPatientAccess()` |
| Aislamiento de eliminación P04 | Corregido estáticamente y evidenciado dinámicamente intra-clínica; cobertura cross-clinic pendiente |
| Token CSRF P05 | Observado y validado estáticamente |
| Token CSRF P04/P06–P11 | No observado |
| Prueba HTTP de explotación CSRF | No ejecutada |
| Cierre de seguridad para producción | No autorizado |

Tener autorización del paciente no prueba la resistencia CSRF: son controles distintos. La primera limita qué recurso puede tocar la sesión; la segunda limita qué origen puede inducir la petición.

## Plan de verificación dinámica segura — no ejecutado

Para una fase posterior, con la base sintética y dos identidades autorizadas confirmadas:

1. Obtener dos pacientes sintéticos de clínicas distintas y no usar datos clínicos reales.
2. Ejecutar P05 con token correcto, token ausente y token perteneciente a otro paciente.
3. Ejecutar cada POST P04/P06–P11 y los endpoints comunes con petición same-origin válida.
4. Repetir cada operación sin token anti-CSRF cuando la remediación esté disponible, y desde un origen distinto controlado.
5. Verificar HTTP, filas afectadas y ausencia de cambios en el paciente/clinica no autorizados.
6. Repetir con cookie ausente, JWT expirado y `Origin`/`Referer` ausentes o cruzados.
7. Comprobar que los GET de lectura no escriban y que los endpoints de imagen/documento mantengan autorización independiente.

No se deben presentar esos casos como PASS hasta contar con respuestas HTTP y comprobación directa de persistencia en la base sintética.

## Criterios de cierre

- Token CSRF común o estrategia de origen aprobada para todos los POST de P04–P11 y comunes.
- Rechazo controlado con token ausente, incorrecto, de otro paciente y origen no permitido.
- Confirmación de que la autorización de paciente continúa funcionando en acceso propio y cruzado.
- Evidencia de no-op en intentos cruzados y de ausencia de escritura antes de autorización.
- Revisión específica de parámetros `idRol`/contexto y de efectos secundarios GET.
- Prueba funcional con login sintético real, sin pacientes reales.

## Validaciones ejecutadas en esta auditoría

- PHP lint de `ModulosController.php`, `AuthMiddleware.php`, `ClinicalSummarySecurity.php` y `BusquedasController.php`: **PASS**.
- Inspección de rutas, controladores, middleware, vistas P04–P11 y llamadas AJAX: **PASS estático**.
- `git diff --check` sobre archivos de aplicación ya modificados: **PASS**, con advertencias preexistentes de conversión LF/CRLF.
- HTTP, navegador, SQL, PDO, base de datos y pruebas de explotación: **NO VERIFICADO / NO EJECUTADO por alcance**.

## Control de cambios

```yaml
TH_PATIENT_TRADITIONAL_CSRF_AUDIT_24:
  STATUS: PARTIAL_REMEDIATION_REQUIRED
  SCOPE: P04-P11
  CSRF_P05_STATIC: PASS
  CSRF_P04_P06_P11_STATIC: EXPLICIT_DEFENSE_NOT_OBSERVED
  HTTP_EXPLOITATION: NOT_VERIFIED
  P1_AUTH_01: INTRA_CLINIC_EVIDENCE_RECONCILED
  CROSS_CLINIC_AUTHORIZATION: NOT_VERIFIED_IN_THIS_AUDIT
  PHP_LINT: PASS
  JS_RUNTIME: NOT_EXECUTED
  DATABASE_ACCESSED: NO
  HTTP_EXECUTED: NO
  PDO_EXECUTED: NO
  APPLICATION_FILES_MODIFIED: NO
  CLOSURE_DOCUMENT_UPDATED: NO
  WORKTREE_PRESERVED: YES
  COMMITS: NONE
  DEPLOYMENTS: NONE
  PRODUCTION_READY: NO
```
