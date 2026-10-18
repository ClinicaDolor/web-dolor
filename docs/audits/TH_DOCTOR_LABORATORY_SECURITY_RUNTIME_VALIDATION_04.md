# TH-DOCTOR-LABORATORY-SECURITY-RUNTIME-VALIDATION-04

## Actualización a 10 MiB y validación funcional y de seguridad

**Proyecto:** Clínica del Dolor  
**Área:** Doctor — Laboratorio  
**Fecha:** 2026-10-15  
**Estado:** PARTIAL — RUNTIME BLOQUEADO POR AISLAMIENTO

## 1. Resumen ejecutivo

La política aprobada para nuevas cargas es PDF exclusivamente, con un máximo exacto de 10485760 bytes (10 MiB). Se actualizó la aplicación para utilizar una constante única de 10 MiB en backend y frontend. Se conservaron CSRF, autorización, MIME, extensión, firma PDF, nombres físicos aleatorios, protección contra sobrescritura y limpieza ante fallos.

No se ejecutaron HTTP, MySQL, uploads, descargas ni pruebas mutantes. El archivo .env principal apunta a bd_clinica, que está fuera del alcance de pruebas de escritura. Por tanto, el resultado runtime queda bloqueado y no se declara seguridad integrada.

## 2. Cambios realizados

- app/Models/LaboratorioModel.php: constante pública MAX_UPLOAD_BYTES = 10485760 y comparación server-side exacta; se conservan las validaciones previas.
- app/Controllers/ClinicaController.php: mensajes controlados para UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE, UPLOAD_ERR_PARTIAL y ausencia de archivo.
- app/views/clinica/pacientes-laboratorio.php: validación previa de tamaño usando la constante del modelo y mensaje de 10 MiB. No se cambió el layout.
- app/Controllers/DownloadController.php: guardia de propiedad de Laboratorio implementada en la fase anterior, preservada.
- app/views/clinica/pacientes-nota-subsecuente.php: token CSRF del endpoint de Laboratorio, implementado en la fase anterior, preservado.

No se modificaron .env, php.ini, Apache, rutas, esquema, migraciones, documentos históricos, base de datos ni otros módulos. No se implementó el modal.

## 3. Configuración PHP inspeccionada

El PHP CLI de Codex es PHP 8.4.24 y carga C:\tools\php84\php.ini:

- upload_max_filesize: 2M.
- post_max_size: 8M.
- file_uploads: 1.
- upload_tmp_dir: valor por defecto.

La configuración instalada de XAMPP en C:\xampp\php\php.ini declara estáticamente:

- upload_max_filesize: 300M.
- post_max_size: 300M.
- file_uploads: On.
- upload_tmp_dir: C:\xampp\tmp.

httpd-xampp.conf carga C:/xampp/php/php8apache2_4.dll. Esto es evidencia estática del archivo de configuración de XAMPP, no una prueba de los valores efectivos de una petición Apache. No se modificó ninguna configuración y no se reinició Apache.

Para aceptar 10 MiB en web, la configuración efectiva de Apache/PHP debe mantener upload_max_filesize mayor o igual a 10M y post_max_size mayor que 10M más el multipart. La comprobación web queda pendiente.

## 4. Política y casos límite

La constante backend es 10485760. La condición de rechazo es estrictamente mayor que ese valor, por lo que el tamaño exacto de 10 MiB queda permitido por la política de aplicación si PHP entrega el upload al controlador. Las demás comprobaciones siguen siendo obligatorias: upload PHP real, extensión PDF, MIME application/pdf, firma %PDF- y autorización/CSRF.

La validación frontend sólo es una ayuda de UX. La validación server-side es la autoridad. Un límite inferior impuesto por PHP produce un error de upload y no alcanza la persistencia.

## 5. Regresión de Nota subsecuente

Se verificó estáticamente que el consumidor de Nota subsecuente conserva su llamada al endpoint y envía X-M03-CSRF desde el atributo generado para la sesión y paciente actuales. No se ejecutó la vista con HTTP ni se crearon notas o laboratorios.

## 6. Compatibilidad histórica

La política de 10 MiB sólo afecta nuevas cargas. No se renombraron ni migraron archivos existentes, no se modificaron filas históricas y no se alteró el contrato de lectura. La guardia de descarga de Laboratorio se conserva. Los archivos históricos sin fila asociada continúan fallando cerrado.

## 7. Aislamiento y pruebas runtime

La configuración principal del proyecto apunta a bd_clinica. No existe una selección de base sintética confirmada para esta fase dentro del entorno de ejecución. En consecuencia:

- no se hizo login;
- no se abrió la vista por HTTP;
- no se ejecutaron POST o GET mutantes;
- no se subieron ni eliminaron archivos;
- no se consultó MySQL;
- no se hicieron pruebas cruzadas por paciente o clínica;
- no se validó la configuración efectiva de Apache;
- no se ejecutó navegador.

### Procedimiento seguro para PowerShell 5.1

Ejecutar en el Windows con XAMPP sólo después de configurar una base y almacenamiento sintéticos autorizados:

1. Leer únicamente DB_DATABASE, DB_HOST y APP_ENV del entorno de prueba; si DB_DATABASE es bd_clinica, detenerse.
2. Exigir que la base y el directorio de almacenamiento pertenezcan a una allowlist sintética aprobada; no inferir aislamiento por el nombre de la vista.
3. Confirmar que el directorio de pruebas no es C:\xampp\htdocs\clinica-dolor\public\storage\laboratorio y que no contiene documentos históricos.
4. Verificar desde una página PHP de diagnóstico no pública o desde el entorno Apache autorizado los valores efectivos de upload_max_filesize, post_max_size, file_uploads y upload_tmp_dir; no usar el resultado de php CLI como sustituto.
5. Usar únicamente dos pacientes sintéticos de clínicas independientes ya preparados. No crear clínicas ni modificar bd_clinica.
6. Probar primero token ausente, inválido y de otra sesión con un PDF sintético; comprobar que no aparece archivo ni registro.
7. Probar PDF válido de menos de 10 MiB y exactamente de 10485760 bytes; después probar uno mayor, tipos no PDF, firma inválida, vacío, parcial y nombres duplicados.
8. Comprobar por consultas de sólo lectura que los dos nombres físicos generados son distintos y que sólo existe el paciente autorizado.
9. Probar descarga propia, descarga cruzada, recurso de otro paciente y nombre alterado; limpiar exclusivamente artefactos creados por esa prueba.
10. Registrar PASS, FAIL o NOT_EXECUTED sin imprimir tokens, contraseñas, hashes, contenido clínico ni rutas internas en el informe.

Si no se puede demostrar cada compuerta, marcar RUNTIME_STATUS: BLOCKED_ENVIRONMENT y no ejecutar la carga.

## 8. Validaciones estáticas ejecutadas

- PHP lint de los cinco archivos afectados: PASS.
- Parseo de JavaScript inline de las vistas de Laboratorio y Nota subsecuente: PASS, sustituyendo únicamente las etiquetas PHP de plantilla para analizar el JS renderizado.
- Revisión de la constante exacta 10485760 y de los mensajes de 10 MiB: PASS.
- Revisión de UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE, UPLOAD_ERR_PARTIAL y ausencia de archivo: PASS.
- Revisión de CSRF, autorización, almacenamiento aleatorio, descarga y preservación histórica: PASS estático.
- git diff --check: PASS; los avisos LF/CRLF observados son históricos del worktree.

TH_DOCTOR_LABORATORY_SECURITY_RUNTIME_VALIDATION_04:
  STATUS: PARTIAL
  POLICY_APPROVED: YES
  ALLOWED_FORMAT: PDF
  MAX_FILE_SIZE_MIB: 10
  MAX_FILE_SIZE_BYTES: 10485760
  BACKEND_LIMIT_UPDATED: PASS_STATIC
  FRONTEND_LIMIT_UPDATED: PASS_STATIC
  VALIDATION_MESSAGES_UPDATED: PASS_STATIC
  PHP_UPLOAD_MAX_FILESIZE: "CLI 2M; XAMPP php.ini static 300M"
  PHP_POST_MAX_SIZE: "CLI 8M; XAMPP php.ini static 300M"
  PHP_APACHE_CONFIG_VERIFIED: NOT_EXECUTED
  PHP_CONFIG_CHANGES_REQUIRED: "NO_STATICALLY_FOR_XAMPP_PHP_INI; WEB_RUNTIME_NOT_VERIFIED"
  PHP_UPLOAD_TMP_DIR: "CLI default; XAMPP php.ini static C:\\xampp\\tmp"
  CSRF_VALIDATION: STATIC_VERIFIED
  PATIENT_AUTHORIZATION: STATIC_VERIFIED_RUNTIME_NOT_EXECUTED
  CLINIC_ISOLATION: NOT_VERIFIED
  UPLOAD_VALIDATION: STATIC_VERIFIED
  FILE_SIGNATURE_VALIDATION: STATIC_VERIFIED
  MIME_VALIDATION: STATIC_VERIFIED
  EXACT_LIMIT_TEST: STATIC_VERIFIED
  OVER_LIMIT_TEST: STATIC_VERIFIED
  OVERWRITE_PROTECTION: STATIC_VERIFIED
  DOWNLOAD_AUTHORIZATION: STATIC_VERIFIED_RUNTIME_NOT_EXECUTED
  STORAGE_FAILURE_HANDLING: STATIC_VERIFIED
  PERSISTENCE_FAILURE_HANDLING: STATIC_VERIFIED
  NOTA_SUBSECUENTE_REGRESSION: STATIC_VERIFIED_RUNTIME_NOT_EXECUTED
  HISTORICAL_COMPATIBILITY: STATIC_VERIFIED
  STATIC_TESTS: PASS
  RUNTIME_TESTS: NOT_EXECUTED
  CROSS_CLINIC_TESTS: NOT_EXECUTED
  BROWSER_VALIDATION: NOT_EXECUTED
  TEST_DATABASE: NONE
  TEST_STORAGE: NONE
  ISOLATED_ENVIRONMENT_CONFIRMED: NO
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  DIFF_CHECK: PASS
  FILES_MODIFIED:
    - app/Models/LaboratorioModel.php
    - app/Controllers/ClinicaController.php
    - app/views/clinica/pacientes-laboratorio.php
    - docs/audits/TH_DOCTOR_LABORATORY_SECURITY_RUNTIME_VALIDATION_04.md
  FILES_PRESERVED_FROM_PREVIOUS_PHASE:
    - app/Controllers/DownloadController.php
    - app/views/clinica/pacientes-nota-subsecuente.php
  TEST_FILES_CREATED: NONE
  DOCUMENT_CREATED: docs/audits/TH_DOCTOR_LABORATORY_SECURITY_RUNTIME_VALIDATION_04.md
  BD_CLINICA_MODIFIED: NO
  PRODUCTION_MODIFIED: NO
  CLINICAL_FILES_MODIFIED: NO
  MIGRATIONS_EXECUTED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  MODAL_IMPLEMENTED: NO
  READY_FOR_MODAL_IMPLEMENTATION: NO
  BLOCKERS:
    - Runtime mutante bloqueado porque .env principal apunta a bd_clinica
    - Configuración web efectiva no verificada mediante Apache
    - No hay fixtures sintéticos aislados confirmados para esta fase
  RESIDUAL_RISKS:
    - No existe evidencia HTTP de aceptación exacta a 10 MiB
    - No existe evidencia runtime de rechazo cruzado por paciente/clínica
    - No existe evidencia runtime de descarga autorizada o denegada
    - La protección contra replay sigue sin idempotencia server-side
  NEXT_RECOMMENDED_ACTION: Ejecutar el procedimiento en un entorno sintético aislado de Windows y actualizar este informe con evidencia real antes de implementar el modal.

