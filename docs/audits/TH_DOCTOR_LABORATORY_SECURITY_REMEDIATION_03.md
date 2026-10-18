# TH-DOCTOR-LABORATORY-SECURITY-REMEDIATION-03

## Registro y carga segura de archivos de Laboratorio

**Proyecto:** Clínica del Dolor  
**Área:** Doctor — Laboratorio  
**Fecha:** 2026-10-15  
**Estado:** REMEDIACIÓN PARCIAL — VALIDACIÓN DE RUNTIME PENDIENTE

## Alcance y flujo identificado

El flujo existente conserva estas rutas:

- GET /laboratorio/paciente/{idPaciente}: vista Doctor de Laboratorio.
- POST /laboratorio/insert-laboratorio: alta del archivo y registro en laboratorio.
- GET /laboratorio/{idLaboratorio}: detalle del registro.
- GET /buscar/laboratorio/{idLaboratorio}: detalle HTML solicitado por la vista.
- GET /buscar/tabla-laboratorio/{idPaciente}/{referencia}: tabla usada por Nota subsecuente.
- GET /descargar/{ruta}/{archivo}: descarga física.

La causa raíz confirmada del POST era la ausencia de validación CSRF y de una política server-side de archivo. El modelo usaba el nombre original como destino físico, por lo que una colisión podía sobrescribir un archivo existente. También devolvía éxito lógico cuando move_uploaded_file() fallaba. La descarga autenticaba al usuario, pero no relacionaba el archivo de Laboratorio con el paciente autorizado.

## Correcciones implementadas

### CSRF y autorización

- ClinicaController::pacienteInsertLaboratorio() valida idPaciente como entero positivo.
- El POST acepta el token en X-M03-CSRF (con compatibilidad de campo csrf_token) y lo valida mediante ClinicalSummarySecurity::validCaptureCsrf().
- El flujo rechaza token ausente o inválido con HTTP 403 antes del tratamiento del archivo.
- La autorización Doctor continúa usando AuthMiddleware::assertPatientAccess() después de la validación CSRF.
- Las dos vistas que reutilizan el endpoint envían el token ligado a la cookie de sesión y al paciente.

### Archivo y almacenamiento

- Las altas nuevas aceptan únicamente PDF: extensión, MIME detectado con fileinfo y firma %PDF-.
- Se exige un upload PHP real mediante is_uploaded_file().
- Se rechazan nombres vacíos o con separadores de ruta; el nombre original nunca se usa como ruta física.
- El límite se calcula dinámicamente como el menor de upload_max_filesize y post_max_size. En la inspección local fue 2 MiB (upload_max_filesize=2M, post_max_size=8M); no se modificó php.ini.
- Se genera un nombre aleatorio lab_<token>.pdf y se comprueba que no exista antes de moverlo.
- El directorio nuevo se crea con 0750; no se modificaron permisos del directorio existente.
- Si falla la inserción de base de datos, se intenta eliminar únicamente el archivo recién creado y se devuelve un error genérico.
- Los registros y archivos históricos no se migran ni se modifican. Las filas históricas no PDF siguen siendo datos legibles; la política PDF aplica a altas nuevas.

La política de tipo y tamaño queda técnicamente aplicada, pero requiere aprobación formal del propietario clínico antes de considerarse política definitiva.

### Descargas

DownloadController aplica una guardia adicional sólo cuando la ruta es laboratorio: busca el nombre en laboratorio mediante consulta preparada y llama a assertPatientAccess() con el id_paciente almacenado. Si no existe una fila asociada, la descarga falla cerrada con 404. Las rutas de otros directorios no fueron modificadas.

## Preservación y riesgos residuales

- No se modificaron rutas, esquemas, migraciones, .env, modelos de otros módulos ni controladores de otros flujos.
- No se implementó el modal de alta; sigue fuera de este alcance hasta contar con validación de runtime.
- No se ejecutaron uploads, POST, descargas, SQL/PDO, HTTP ni pruebas mutantes.
- La autorización integrada y la ausencia de escrituras no han sido verificadas en navegador/XAMPP.
- La política de PDF y tamaño necesita aprobación formal del propietario.
- Archivos históricos sin fila asociada en laboratorio quedan bloqueados por diseño en la descarga.
- El modelo conserva riesgos heredados fuera del alta focalizada: HTML no escapado en ciertos detalles, consulta concatenada en mostrarTablaLaboratorio() y otros directorios del DownloadController sin guardia específica de propiedad.
- La protección contra doble clic existente es una guardia de cliente; no constituye idempotencia frente a reintentos o replay deliberados del POST.

## Validación ejecutada

- php -l pasó para controlador, modelo, controlador de descarga y ambas vistas.
- Los bloques JavaScript inline de ambas vistas pasaron parseo con Node.js.
- git diff --check no reportó errores de whitespace nuevos; los avisos LF/CRLF son históricos del worktree.
- Se verificó estáticamente la presencia del header CSRF, la guardia server-side, is_uploaded_file(), fileinfo, firma PDF, límite dinámico, nombre aleatorio, limpieza tras fallo y guardia de descarga.
- No se ejecutó navegador ni runtime de aplicación. No se accedió a base de datos y no se subieron/eliminaron archivos.

TH_DOCTOR_LABORATORY_SECURITY_REMEDIATION_03:
  STATUS: PARTIAL
  CURRENT_FLOW_IDENTIFIED: PASS
  ROOT_CAUSES_CONFIRMED: PASS
  CSRF_IMPLEMENTED: IMPLEMENTED
  CSRF_SERVER_VALIDATION: IMPLEMENTED
  INVALID_CSRF_FAIL_CLOSED: STATIC_VERIFIED
  FILE_TYPE_VALIDATION: IMPLEMENTED
  FILE_EXTENSION_VALIDATION: IMPLEMENTED
  FILE_CONTENT_VALIDATION: IMPLEMENTED
  FILE_SIZE_LIMIT: IMPLEMENTED_FROM_SERVER_CONFIG
  FILE_SIZE_POLICY_APPROVED: PENDING_OWNER_POLICY
  UNIQUE_STORAGE_NAME: IMPLEMENTED
  OVERWRITE_PROTECTION: STATIC_VERIFIED
  HISTORICAL_FILES_PRESERVED: STATIC_VERIFIED
  PATIENT_AUTHORIZATION: STATIC_VERIFIED
  CLINIC_ISOLATION: STATIC_VERIFIED
  UNAUTHORIZED_WRITE_REJECTED: NOT_EXECUTED
  STORAGE_FAILURE_HANDLING: STATIC_VERIFIED
  DATABASE_FAILURE_HANDLING: STATIC_VERIFIED
  DUPLICATE_SUBMISSION_PROTECTION: STATIC_VERIFIED_CLIENT_GUARD_ONLY
  CURRENT_CREATE_FLOW_PRESERVED: PASS
  CURRENT_READ_FLOW_PRESERVED: PASS
  DESIGN_PRESERVED: PASS
  DATATABLE_PRESERVED: PASS
  QUILL_PRESERVED: PASS
  SECURITY_TESTS: NOT_EXECUTED
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  DIFF_CHECK: PASS
  DATABASE_ACCESSED: NO
  DATABASE_WRITES: NO
  FILES_UPLOADED: NO
  FILES_DELETED: NO
  FILES_MODIFIED:
    - app/Controllers/ClinicaController.php
    - app/Models/LaboratorioModel.php
    - app/Controllers/DownloadController.php
    - app/views/clinica/pacientes-laboratorio.php
    - app/views/clinica/pacientes-nota-subsecuente.php
    - docs/audits/TH_DOCTOR_LABORATORY_SECURITY_REMEDIATION_03.md
  DOCUMENT_CREATED: docs/audits/TH_DOCTOR_LABORATORY_SECURITY_REMEDIATION_03.md
  MODAL_IMPLEMENTED: NO
  MODAL_VALIDATION: NOT_EXECUTED
  BROWSER_VALIDATION: NOT_EXECUTED
  RUNTIME_VALIDATION: NOT_EXECUTED
  BLOCKERS:
    - Entorno runtime/XAMPP no ejecutado desde Codex
    - Aprobación formal pendiente de la política PDF/tamaño
  RESIDUAL_RISKS:
    - Revisión e integración HTTP con dos pacientes sintéticos
    - Descarga histórica sin asociación queda bloqueada
    - Riesgos heredados de HTML/SQL concatenado fuera del alcance focalizado
    - No hay idempotencia server-side para replay deliberado
  READY_FOR_MODAL_IMPLEMENTATION: NO
  NEXT_RECOMMENDED_ACTION: Ejecutar pruebas controladas en XAMPP con pacientes sintéticos y revisar la política de archivo con el propietario antes de implementar un modal.
  COMMITS: NONE
  DEPLOYMENTS: NONE
  PRODUCTION_READY: NO

