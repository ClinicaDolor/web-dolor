# TH-DOCTOR-COFEPRIS-HTTP-STORAGE-SECURITY-VERIFICATION-12D-01-R3

## Alcance y resultado

Verificación focalizada de exposición HTTP del almacenamiento de documentos COFEPRIS en el entorno local de desarrollo. No se modificaron PHP, CSS, JavaScript, rutas, configuración de Apache, `.htaccess`, base de datos ni documentos clínicos.

La ejecución quedó bloqueada antes de crear el archivo sintético: el contexto de Codex no pudo abrir el socket local de Apache. El archivo `hosts` contiene `127.0.0.1 clinica.test`, pero la resolución desde el sandbox falló y las conexiones a `127.0.0.1:80` y `127.0.0.1:8081` fueron rechazadas/denegadas. Por tanto, no se afirma acceso HTTP directo ni se ejecutó una prueba de navegador.

## Evidencia estática

- `app/Config/routes.php:239` expone `GET /pdf/cofepris/{id}`.
- `app/Controllers/PdfController.php:376-395` recupera el registro, autentica mediante `AuthMiddleware('clinica')`, llama a `assertPatientAccess()` y después construye el PDF desde `DOCUMENT_ROOT/public/storage/cofepris/{carpeta}/{folio}.pdf`.
- `app/Controllers/DownloadController.php:12-32` tiene autorización específica para laboratorio, pero no una rama equivalente para COFEPRIS. Un archivo servido directamente por Apache no pasa por este controlador.
- El `.htaccess` raíz redirige hacia `public/` y `public/.htaccess` deja pasar recursos físicos (`!-f`, `!-d`) sin enviarlos al front controller.
- `public/storage/.htaccess` y `public/storage/cofepris/.htaccess` no existen.
- La configuración local revisada usa `DocumentRoot "C:/xampp/htdocs"`, el vhost de `clinica.test` apunta a `C:/xampp/htdocs/clinica-dolor`, `AllowOverride All` está activo y `Options Indexes` está habilitado en el directorio raíz de Apache.
- La carpeta `public/storage/cofepris` existe y contenía 2,006 archivos PDF al preflight. No se inspeccionó el contenido clínico.

Esto demuestra una posibilidad estática de servir archivos físicos bajo `public/`; no demuestra por sí solo que el servidor activo los entregue por HTTP.

## Archivo PDF 256/257

Se conserva el dato aportado y verificado por el propietario: el archivo físico compartido por 256/257 existe. El propietario de ese documento no está verificado. La coincidencia de ruta física es `SÍ`, conforme a la reconciliación previa. No se abrió ni modificó ese PDF.

## Prueba sintética bloqueada

La prueba autorizada requería crear un subdirectorio único, escribir allí un PDF sintético, solicitarlo mediante GET sin cookies, JWT, `Authorization` ni redirecciones, registrar estado/cabeceras/contenido y eliminar sólo el archivo y el directorio vacío. No se creó ningún archivo porque la compuerta de entorno falló antes de la escritura.

Para reanudarla, el propietario debe ejecutar el procedimiento desde el mismo PowerShell donde XAMPP está activo, confirmando antes que:

1. `clinica.test` resuelve a `127.0.0.1` o `::1`.
2. La ruta es exactamente `C:\xampp\htdocs\clinica-dolor\public\storage\cofepris` y no es un enlace/reparse point.
3. El subdirectorio aleatorio no existe.
4. El GET se realiza sin credenciales, sin cookies y con redirecciones deshabilitadas.
5. El archivo y el directorio se eliminan en `finally`, y el conteo de archivos vuelve al valor inicial.

## Remediación recomendada, no implementada

Se recomienda la Opción A: mover los documentos a almacenamiento privado fuera de `DocumentRoot` y servirlos exclusivamente mediante un controlador autenticado que valide paciente, clínica y registro antes de transmitir el PDF. La Opción B —bloqueo directo en Apache para `/public/storage/cofepris`— puede ser una contención, pero debe probarse cuidadosamente para no romper el flujo autorizado y no sustituye la autorización por registro.

También debe resolverse por separado la propiedad histórica del documento cuando varias filas comparten `carpeta + folio`; la existencia física no prueba quién es su propietario clínico.

## Informe estructurado

```yaml
TH_DOCTOR_COFEPRIS_HTTP_STORAGE_SECURITY_12D_01_R3:
  STATUS: BLOCKED
  ENVIRONMENT: Windows/XAMPP local configurado; HTTP inaccesible desde el sandbox Codex
  DOCUMENT_ROOT: C:\\xampp\\htdocs\\clinica-dolor
  PUBLIC_STORAGE_HTTP_URL: http://clinica.test/public/storage/cofepris/<directorio-unico>/<archivo>.pdf
  APACHE_CONFIGURATION_REVIEW: PASS_STATIC_ONLY
  STORAGE_HTACCESS: ABSENT
  APACHE_DIRECT_ACCESS_POLICY: NOT_VERIFIED_RUNTIME; STATIC_EXPOSURE_POSSIBLE
  SYNTHETIC_PDF_CREATED: NO
  SYNTHETIC_PDF_HTTP_TEST: NOT_EXECUTED
  UNAUTHENTICATED_HTTP_STATUS: NOT_VERIFIED
  EXPECTED_PDF_CONTENT_RETURNED: NOT_VERIFIED
  DIRECT_HTTP_ACCESS: NOT_VERIFIED
  SYNTHETIC_PDF_REMOVED: NOT_APPLICABLE
  TEMP_DIRECTORY_REMOVED: NOT_APPLICABLE
  CLEANUP_STATUS: NOT_APPLICABLE; NO_SYNTHETIC_ARTIFACT_CREATED
  DOCUMENT_CONTROLLER_AUTHENTICATION: PRESENT_STATICALLY_IN_PDF_CONTROLLER
  DOCUMENT_PATIENT_AUTHORIZATION: ASSERT_PATIENT_ACCESS_PRESENT_STATICALLY
  DOCUMENT_CLINIC_AUTHORIZATION: NOT_EXPLICITLY_VERIFIED_IN_THIS_AUDIT
  DOCUMENT_RECORD_AUTHORIZATION: NOT_EXPLICITLY_VERIFIED_IN_THIS_AUDIT
  DIRECT_STORAGE_BYPASS: POTENTIAL_STATICALLY
  PDF_256_257_PHYSICAL_EXISTENCE: OWNER_VERIFIED_TRUE
  PDF_256_257_SAME_PATH: YES
  PDF_256_257_DOCUMENT_OWNER: NOT_VERIFIED
  SECURITY_SEVERITY: HIGH_POTENTIAL; RUNTIME_ACCESS_NOT_DEMONSTRATED
  RECOMMENDED_REMEDIATION: OPTION_A_PRIVATE_STORAGE_OUTSIDE_DOCUMENT_ROOT_AND_AUTHENTICATED_CONTROLLER
  REMEDIATION_IMPLEMENTED: NO
  CODE_MODIFIED: NO
  DATABASE_MODIFIED: NO
  SCHEMA_MODIFIED: NO
  CLINICAL_PDF_MODIFIED: NO
  MIGRATIONS_EXECUTED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS: Codex sandbox no pudo resolver/abrir el HTTP local; 127.0.0.1:80 y 127.0.0.1:8081 no fueron accesibles
  RESIDUAL_RISKS: almacenamiento físico bajo DocumentRoot sin regla específica; posible bypass del controlador; propiedad histórica 256/257 no verificada
  NEXT_ACTION: Ejecutar la compuerta sintética desde PowerShell nativo del propietario y conservar evidencia de estado, cabeceras, contenido y limpieza
```
