# TH-DOCTOR-COFEPRIS-CREATE-SECURITY-REMEDIATION-12A

## Resultado

**STATUS: PARTIAL — remediación estática aplicada; HTTP, navegador y persistencia no ejecutados desde Codex.**

El alcance fue exclusivamente el alta de folios de la ruta global de COFEPRIS. No se modificaron el esquema, los datos, los endpoints de pacientes ni la interfaz aprobada en la fase 12.

## Contrato real

| Elemento | Implementación comprobada |
| --- | --- |
| Ruta de pantalla | `GET /clinica/cofepris` |
| Controlador de pantalla | `HomeController::indexCofepris()` |
| Vista | `app/views/clinica/configuracion-cofepris.php` |
| Alta | `POST /clinica/cofepris/insert-folios` |
| Controlador de alta | `CofeprisController::insertFolios()` |
| Modelo | `CofeprisModel::guardarFolio()` |
| Transporte | `fetch` + `FormData` (`file`, `folio`) |
| Contexto de paciente | No aplica |
| Contexto confiable | `AuthMiddleware('clinica')->authPermisos()` y su `id_clinica` |

## Causa y corrección CSRF

La implementación anterior autenticaba al Doctor, pero no exigía ni validaba un token CSRF en `insertFolios()`.

Se incorporó un token de acción de clínica en `ClinicalSummarySecurity`. El token se calcula con HMAC usando `APP_KEY`, el `id_clinica` confiable y un hash de la cookie `CLINICAJWT`. La vista recibe el token desde `HomeController` y lo envía en el header `X-COFEPRIS-CSRF`; no se añade a la URL ni se registra en mensajes.

El controlador rechaza el request con HTTP 403 si el token falta, pertenece a otra clínica/sesión o no coincide. La comprobación ocurre antes de leer/procesar el archivo y antes de llamar al modelo.

## Autenticación y alcance de clínica

- **DOCTOR_AUTHENTICATION: PASS_STATIC.** El endpoint conserva `AuthMiddleware('clinica')->authPermisos()`.
- **CLINIC_AUTHORIZATION: PASS_STATIC, alcance limitado.** El token se liga al `id_clinica` emitido por el middleware y no se acepta `id_clinica` desde el navegador.
- **PATIENT_CONTEXT_REQUIRED: NO.** Esta ruta administra rangos globales de folios y no crea un registro clínico de paciente.
- **CROSS_CLINIC_REJECTION: NOT_VERIFIED / RIESGO RESIDUAL.** La tabla actual `cofepris_folio` y las consultas de folios no tienen una columna de clínica. Sin cambiar el esquema no es posible demostrar aislamiento persistente entre clínicas; esta intervención no inventa una relación ni altera migraciones.

## Validaciones y orden seguro

El controlador valida, antes de persistir:

1. Método POST.
2. Autenticación Doctor.
3. CSRF ligado a la clínica autenticada.
4. Folio no vacío y numérico según el contrato usado por el modelo.
5. Upload HTTP válido.
6. Nombre base, tamaño positivo y extensión PDF.
7. MIME real `application/pdf` mediante `finfo`.
8. Llamada al modelo existente.

El modelo conserva el contrato de `guardarFolio()` y corrige el uso inseguro del nombre recibido: mueve el upload a un nombre PDF generado por el servidor, crea directorios con modo `0750`, elimina el origen temporal tras procesarlo y elimina archivos generados si falla la transformación o el INSERT. No se usa la ruta proporcionada por el cliente.

La operación de filesystem y MySQL no es una transacción atómica. La limpieza compensatoria cubre los fallos comprobables del flujo, pero no se declara idempotencia backend ni garantía de consistencia ante una caída abrupta del proceso.

## Respuestas y regresión de interfaz

- Método incorrecto: HTTP 405.
- CSRF ausente o inválido: HTTP 403.
- Folio o archivo inválido: HTTP 422.
- Error de negocio devuelto por el modelo: conserva HTTP 200 y `resultado: false`, como el contrato anterior.
- Excepción inesperada al crear/procesar: HTTP 500 con mensaje seguro.
- Éxito: conserva el JSON `resultado`/`mensaje` consumido por el modal.

Se conserva el bloqueo frontend contra doble envío, el modal, X, Cancelar, Escape, restauración de foco, actualización de tabla, búsqueda, ordenamiento y paginación. No se introdujo un paciente ni se modificó el historial del navegador.

## Pruebas

| Prueba | Resultado |
| --- | --- |
| PHP lint de controlador, modelo, servicio, vista y contrato | PASS_STATIC |
| Contrato focalizado 12A, incluida generación/validación de token | PASS_STATIC |
| Contrato de lista/modal de fase 12 | PASS_STATIC |
| Contratos de navegación COFEPRIS 02 | PASS_STATIC |
| HTTP sin sesión / sin CSRF / token inválido | NOT_EXECUTED |
| HTTP con contexto de otra clínica | NOT_EXECUTED; además requiere clave de clínica en el esquema para demostrar aislamiento persistente |
| Validación HTTP de archivo y persistencia | NOT_EXECUTED |
| Prueba de creación positiva | NOT_EXECUTED |
| Navegador, consola, responsive y capturas | NOT_EXECUTED |
| Base de datos | NO ACCEDIDA |
| `git diff --check` | PASS — con advertencias históricas de finales de línea |

El contrato histórico `tests/doctor-cofepris/ux-implementation-01-contract.php` conserva su fallo preexistente sobre `app/views/clinica/pacientes-cofepris.php`; no se modificó esa vista porque está fuera del alcance 12A.

## Archivos modificados

- `app/Controllers/CofeprisController.php`
- `app/Controllers/HomeController.php`
- `app/Models/CofeprisModel.php`
- `app/Services/ClinicalSummarySecurity.php` — se preservaron los cambios locales previos y se añadió sólo el token de acción de clínica.
- `app/views/clinica/configuracion-cofepris.php`
- `tests/doctor-cofepris/create-security-12a-contract.php`
- Este documento.

## Riesgos residuales y siguiente paso

1. La tabla de rangos de folios es global y no demuestra propiedad por clínica; resolverlo requiere una decisión de esquema/contrato independiente.
2. La creación no tiene idempotencia backend; el bloqueo de doble clic sigue siendo frontend.
3. La descarga y autorización posterior de documentos no forman parte de esta intervención.
4. Debe ejecutarse el conjunto HTTP en XAMPP con una clínica y archivo sintéticos, sin mostrar el token en el informe.

**READY_FOR_OWNER_VALIDATION: YES, con las limitaciones anteriores.**

**DATABASE_MODIFIED: NO**  
**SCHEMA_MODIFIED: NO**  
**COMMITS: NONE**  
**DEPLOYMENTS: NONE**
