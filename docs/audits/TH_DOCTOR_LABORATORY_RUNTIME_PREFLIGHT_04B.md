# TH-DOCTOR-LABORATORY-RUNTIME-PREFLIGHT-04B

## Preparación segura de pruebas runtime — Laboratorio Doctor

**Proyecto:** Clínica del Dolor
**Área:** Doctor — Laboratorio
**Fecha:** 2026-10-15
**Estado:** BLOCKED — compuertas de aislamiento no satisfechas

## Resultado ejecutivo

Se preparó un preflight de sólo lectura compatible con Windows PowerShell 5.1. El script no abre conexiones MySQL, no hace peticiones HTTP, no carga archivos, no modifica bases, no modifica el entorno de la aplicación y no crea directorios de pruebas.

La ejecución controlada se detuvo porque la configuración principal del proyecto apunta a bd_clinica. Además, no se proporcionaron explícitamente una base sintética aprobada ni un almacenamiento externo de pruebas. No se ejecutaron pruebas runtime mutantes.

## Compuertas verificadas

| Compuerta | Resultado | Evidencia |
|---|---|---|
| Raíz del proyecto | PASS | Se resolvió el proyecto local sin cambiarlo. |
| Base configurada actualmente | BLOCKED | Lectura estática de .env: bd_clinica. |
| Host de base | PASS_LOCAL | .env declara un host local. |
| Entorno de producción | READ_ONLY | No se identificó APP_ENV como producción. |
| Base sintética explícita | BLOCKED | No se proporcionó TestDatabase. |
| Almacenamiento sintético externo | BLOCKED | No se proporcionó TestStorage; el preflight no lo crea. |
| Conexión MySQL | NOT_EXECUTED | Prohibida en esta preparación. |
| HTTP | NONE | No se realizaron peticiones. |
| Mutaciones | NONE | No hubo uploads, POST, SQL ni escrituras. |
| Configuración efectiva Apache/PHP | NOT_EXECUTED | Sólo se hizo presencia estática del php.ini de XAMPP. |

## Configuración observada

La configuración principal contiene DB_DATABASE=bd_clinica. Esa base queda fuera del alcance de cualquier prueba mutante de este preflight.

Se encontró estáticamente C:\xampp\php\php.ini. Sus directivas upload_max_filesize y post_max_size están presentes, pero esto no demuestra los valores efectivos del proceso Apache ni de una petición web. La configuración CLI de Codex es distinta y no debe utilizarse como sustituto de la configuración efectiva de Apache.

La aplicación resuelve el almacenamiento de Laboratorio bajo DOCUMENT_ROOT/public/storage/laboratorio. Por ello, un almacenamiento realmente aislado requiere un proyecto o document root de pruebas aprobado y separado; no basta con cambiar un parámetro de una prueba mientras se sirve el proyecto clínico principal.

## Preflight preparado

Archivo creado:

tests/laboratory/preflight.ps1

Características:

- Compatible con Windows PowerShell 5.1; no requiere pwsh.
- No usa Start-Process ni variables de entorno temporales para iniciar servicios.
- Requiere TestDatabase explícita.
- Sólo permite los nombres sintéticos conocidos clinica_dolor_demo_20261010_final y clinica_dolor_browser_20261012.
- Rechaza bd_clinica, hosts no locales y entornos marcados como producción.
- Requiere TestStorage explícito, absoluto y existente.
- Rechaza almacenamiento dentro del proyecto, rutas clínicas o puntos de reanálisis.
- Devuelve código 2 cuando una compuerta falla y no informa éxito en ese caso.
- No imprime credenciales, tokens, contraseñas ni contenido clínico.

Ejecución estática realizada:

    powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File .\tests\laboratory\preflight.ps1

Resultado observado: STATUS: BLOCKED, con salida de la base principal bd_clinica, parámetros sintéticos ausentes, sin conexiones y sin mutaciones. La ejecución también confirmó que el script puede resolver su raíz de proyecto bajo PowerShell 5.1.

## Matriz runtime preparada, no ejecutada

Una vez aprobado y demostrado el aislamiento, debe ejecutarse exclusivamente en el entorno sintético:

1. Verificar la base efectiva, el host, el entorno y el almacenamiento antes de cualquier escritura.
2. Verificar dos pacientes sintéticos pertenecientes a clínicas independientes, sin asumir que los identificadores históricos siguen disponibles.
3. Confirmar en Apache los valores efectivos de upload_max_filesize, post_max_size, file_uploads y upload_tmp_dir. El límite web debe permitir al menos 10 MiB más el overhead multipart.
4. Probar CSRF ausente, inválido y de otra sesión; cada caso debe fallar cerrado sin archivo ni fila nuevos.
5. Probar PDF válido menor de 10 MiB, exactamente 10485760 bytes y mayor de 10 MiB.
6. Probar extensión, MIME, firma PDF, archivo vacío, upload parcial, nombre duplicado y fallos de almacenamiento o persistencia.
7. Verificar que el nombre físico sea aleatorio y que una falla de persistencia elimine sólo el archivo creado por esa operación.
8. Probar descarga propia, descarga cruzada entre pacientes y clínicas, nombre alterado y recurso sin fila asociada.
9. Ejecutar la regresión de Nota subsecuente, incluyendo el endpoint compartido de Laboratorio y su CSRF, sin cambiar el flujo de notas.
10. Repetir un envío idéntico sólo después de disponer de un fixture descartable y registrar si existe o no idempotencia server-side.
11. Limpiar únicamente artefactos creados durante la prueba; conservar el catálogo o fixtures aprobados y no tocar bd_clinica.

Todas las pruebas anteriores permanecen NOT_EXECUTED hasta superar las compuertas. No se debe reportar PASS con fixtures visuales o revisión estática solamente.

## Dependencias revisadas para la preparación

- LaboratorioModel: validación de PDF, límite de 10485760 bytes, firma, MIME, almacenamiento físico y limpieza ante fallos.
- ClinicaController: CSRF, autorización de paciente, validación de upload y respuestas controladas.
- DownloadController: guardia de propiedad para descargas de Laboratorio.
- pacientes-laboratorio.php: token CSRF y validación de tamaño como ayuda de UX.
- pacientes-nota-subsecuente.php: consumidor del endpoint compartido y token CSRF de Laboratorio.
- AuthMiddleware y ClinicalSummarySecurity: deben validarse en runtime con fixtures sintéticos antes de declarar seguridad integrada.

No se modificaron esas dependencias en esta actividad.

## Estado de seguridad y aislamiento

FAIL-CLOSED_GUARDS:

- La configuración actual apunta a bd_clinica.
- La base sintética debe pertenecer a una allowlist explícita.
- El almacenamiento debe ser externo al proyecto clínico.
- Se rechazan rutas clínicas, de producción y puntos de reanálisis.
- La ausencia de argumentos sintéticos bloquea el preflight.
- No se permite inferir aislamiento por el nombre de una vista o por una sesión autenticada.

No se accedió a MySQL, no se consultaron tablas, no se creó una base, no se modificó bd_clinica y no se utilizaron pacientes reales.

## Procedimiento de reanudación

El propietario debe proporcionar o aprobar un entorno aislado que cumpla simultáneamente:

- Base sintética permitida y comprobable.
- Proyecto o document root separado, porque el modelo usa DOCUMENT_ROOT para el almacenamiento.
- Directorio de archivos sintéticos fuera del proyecto principal y sin enlaces o junctions.
- Apache/PHP sirviendo ese entorno local, con sus valores efectivos comprobables.
- Dos fixtures de pacientes de clínicas independientes y autorización de prueba documentada.

Después, ejecutar el preflight indicando ambos valores explícitamente. No modificar .env del proyecto principal para forzar el resultado. Si alguna compuerta devuelve BLOCKED, no iniciar POST, upload, descarga ni finalización.

## Informe

TH_DOCTOR_LABORATORY_RUNTIME_PREFLIGHT_04B:

    STATUS: BLOCKED
    CURRENT_DATABASE_IDENTIFIED: bd_clinica
    ISOLATED_TEST_DATABASE_PROPOSED: clinica_dolor_demo_20261010_final (no verificada ni creada)
    ISOLATED_STORAGE_PROPOSED: directorio dedicado externo al proyecto (pendiente de aprobación y provisión)
    APACHE_PHP_VERIFIED: NOT_EXECUTED
    APACHE_UPLOAD_MAX_FILESIZE: NOT_EXECUTED_EFFECTIVE_WEB; XAMPP_STATIC_PRESENT
    APACHE_POST_MAX_SIZE: NOT_EXECUTED_EFFECTIVE_WEB; XAMPP_STATIC_PRESENT
    UPLOAD_TESTS_PREPARED: YES
    DOWNLOAD_TESTS_PREPARED: YES
    CSRF_TESTS_PREPARED: YES
    NOTA_SUBSECUENTE_TESTS_PREPARED: YES
    FAIL_CLOSED_GUARDS: YES
    POWERSHELL_5_1_COMPATIBILITY: PASS_STATIC
    RUNTIME_MUTATING_TESTS_EXECUTED: NO
    MYSQL_CONNECTIONS: NONE
    HTTP_REQUESTS: NONE
    DATABASE_CREATED: NO
    BD_CLINICA_MODIFIED: NO
    CLINICAL_FILES_MODIFIED: NO
    PHP_LINT_PRIOR_LAB_FILES: PASS
    GLOBAL_DIFF_CHECK: PASS
    FILES_MODIFIED:
      - tests/laboratory/preflight.ps1
      - docs/audits/TH_DOCTOR_LABORATORY_RUNTIME_PREFLIGHT_04B.md
    DOCUMENT_CREATED: YES
    OWNER_APPROVAL_REQUIRED: YES
    READY_FOR_ISOLATED_TEST_EXECUTION: NO
    COMMITS: NONE
    DEPLOYMENTS: NONE

## Bloqueos pendientes

- La configuración de ejecución principal sigue apuntando a bd_clinica.
- No se verificó la disponibilidad de la base sintética propuesta mediante conexión.
- No existe un TestStorage externo aprobado y comprobado.
- No se verificó la configuración efectiva del Apache que serviría el entorno sintético.
- No se verificaron fixtures sintéticos actuales ni autorización cruzada en runtime.
