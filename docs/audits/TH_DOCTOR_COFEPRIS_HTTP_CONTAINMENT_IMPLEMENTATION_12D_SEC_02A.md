# TH-DOCTOR-COFEPRIS-HTTP-DIRECT-ACCESS-CONTAINMENT-12D-SEC-02A

## Contención local del acceso HTTP directo

**Proyecto:** Clínica del Dolor
**Directorio:** `C:\xampp\htdocs\clinica-dolor`
**Rama:** `Silvino`
**Entorno autorizado:** desarrollo local XAMPP
**Dominio:** `http://clinica.test`
**Fase:** SEC-02A únicamente

## 1. Alcance y estado

Se implementó únicamente la contención HTTP específica del almacenamiento COFEPRIS. No se inició SEC-02B, SEC-02C, SEC-02D ni 12D-02.

El propietario había confirmado previamente el acceso directo con un PDF sintético:

```text
HTTP_STATUS=200
SYNTHETIC_BYTES_MATCH=True
DIRECT_HTTP_ACCESS=True
CONTENT_TYPE=application/pdf
CURL_EXIT_CODE=0
CLEANUP_STATUS=PASS
PROCESS_EXIT_CODE=0
```

La evidencia final de eficacia HTTP fue ejecutada y confirmada por el propietario para la URL canónica y la variante reescrita. Codex no la repitió: `clinica.test` no resolvió y `127.0.0.1:80` no fue accesible desde este contexto. Con las rutas y la funcionalidad comprobadas, SEC-02A queda técnicamente cerrada en el entorno local de desarrollo. Este cierre no equivale a verificación de producción ni a cobertura universal de rutas, registros o documentos.

### Evidencia de ejecución del propietario

**OWNER_VERIFIED — SEC-HTTP-01 y SEC-HTTP-02:**

```text
CANONICAL_HTTP_STATUS=403
CANONICAL_DIRECT_HTTP_ACCESS=False
CANONICAL_RESULT_CLASSIFICATION=DENIED_VERIFIED
REWRITTEN_HTTP_STATUS=403
REWRITTEN_DIRECT_HTTP_ACCESS=False
REWRITTEN_RESULT_CLASSIFICATION=DENIED_VERIFIED
SYNTHETIC_BYTES_MATCH=False
CLEANUP_STATUS=PASS
PROCESS_EXIT_CODE=0
```

**OWNER_VERIFIED — SEC-HTTP-05:** el propietario abrió correctamente `GET /pdf/cofepris/337` con sesión Doctor en Chrome. La vista previa de impresión se mostró correctamente, incluidas sus páginas, sin errores reportados de visualización o impresión.

## 2. Pre-flight

| Comprobación | Resultado | Evidencia |
|---|---|---|
| Repositorio y rama | PASS | Rama `Silvino`; worktree ya contenía cambios preexistentes. |
| Entorno | PASS estático | Proyecto local XAMPP en `C:\xampp\htdocs\clinica-dolor`. |
| DocumentRoot base | PASS estático | `C:\xampp\htdocs`. |
| VirtualHost del proyecto | PASS estático | `clinica.test` apunta a `C:/xampp/htdocs/clinica-dolor` según configuración local inspeccionada. |
| `AllowOverride` | PASS estático | `AllowOverride All` en `C:\xampp\apache\conf\httpd.conf`. |
| `.htaccess` raíz | PASS inspección | Redirige solicitudes que no empiezan por `/public/` hacia `public/`. |
| `public/.htaccess` | PASS inspección | Reescribe sólo cuando el recurso no es archivo ni directorio. |
| `.htaccess` COFEPRIS previo | ABSENT | No existía antes de esta intervención. |
| Almacenamiento COFEPRIS | PASS | Existe y no es reparse point. Conteo local inspeccionado: 2,006 PDFs. |
| Acceso HTTP desde Codex | NOT VERIFICADO | DNS `clinica.test`: no resuelto; TCP `127.0.0.1:80`: no accesible. La prueba equivalente del propietario sí está registrada como `OWNER_VERIFIED`. |
| PDFs clínicos | NO ACCEDIDOS | No se abrió, descargó, movió ni modificó ningún PDF. |

La lectura PHP de `PdfController::pdfCofepris()` no depende de una solicitud HTTP al archivo: el controlador construye una ruta de filesystem y FPDI la lee internamente. Esta compatibilidad es estática; su recorrido autenticado real queda pendiente.

## 3. Cambio aplicado

Archivo creado:

```text
public/storage/cofepris/.htaccess
```

Contenido efectivo:

```apache
Require all denied
```

La regla deniega el acceso HTTP al directorio protegido y a sus subdirectorios, sin excepciones por nombre, extensión, MIME ni autenticación del cliente. No se modificó el `.htaccess` raíz, `public/.htaccess`, el VirtualHost ni la configuración global de Apache.

La regla no impide que PHP lea el archivo directamente desde el filesystem. La ruta lógica `/pdf/cofepris/{id}` no fue modificada y no queda bloqueada por esta regla; no obstante, su autorización por clínica, registro y propiedad documental pertenece a fases posteriores.

## 4. Compatibilidad estática

Verificado sin ejecutar solicitudes HTTP:

- `app/Config/routes.php` conserva `GET /pdf/cofepris/{id}`.
- `app/Controllers/PdfController.php::pdfCofepris()` conserva su flujo de autenticación y lectura PHP.
- `app/Models/CofeprisModel.php` conserva los enlaces lógicos hacia `/pdf/cofepris/{id}`.
- Las vistas Doctor de listado, detalle, folios, expediente y configuración no fueron modificadas.
- No se modificaron controladores, modelos, vistas, JavaScript, CSS, rutas ni permisos clínicos.
- No se alteraron otros directorios bajo `public/storage`.

Compatibilidad funcional real aún no verificada:

- impresión autorizada del PDF;
- descarga autorizada desde el Doctor;
- modales y enlaces relacionados;
- respuestas de las rutas alternativas;
- ausencia de HTTP 500 después de cargar el `.htaccess`.

## 5. Resultado de validaciones ejecutadas

| Validación | Resultado |
|---|---|
| Regla `.htaccess` presente y sin whitespace defectuoso | PASS |
| `httpd.exe -t -f C:\xampp\apache\conf\httpd.conf` | PASS — `Syntax OK` |
| Parser sintético del probe PowerShell | PASS — `PARSER_SELF_TEST=PASS` |
| Compatibilidad PowerShell 5.1 del probe | PASS estático/ejecutado con `powershell.exe` local |
| PHP lint de `PdfController.php` | PASS; archivo no modificado en esta fase |
| HTTP SEC-HTTP-01 ruta canónica | OWNER_VERIFIED — 403, bytes no entregados, clasificación `DENIED_VERIFIED` |
| HTTP SEC-HTTP-02 ruta reescrita | OWNER_VERIFIED — 403, bytes no entregados, clasificación `DENIED_VERIFIED` |
| HTTP SEC-HTTP-03 ausencia de 500 | NO VERIFICADO |
| HTTP SEC-HTTP-04 resto del sitio | NO VERIFICADO |
| HTTP SEC-HTTP-05 `/pdf/cofepris/{id}` autorizado | OWNER_VERIFIED — `/pdf/cofepris/337` abrió en Chrome y la vista previa de impresión se mostró correctamente |
| PDF histórico abierto o modificado | NO |
| Base de datos consultada o modificada | NO |
| `git diff --check` | PASS; sólo mostró advertencias de conversión LF/CRLF preexistentes |

El probe ejecuta, con el mismo PDF sintético y el mismo directorio temporal, la ruta canónica y la variante reescrita. Cada URL conserva su respuesta, `Content-Type`, código de curl, comparación SHA-256 y clasificación independiente. Una clasificación distinta de `DENIED_VERIFIED` termina con código distinto de cero; un `BYPASS_CONFIRMED` se reporta como fallo de seguridad. La limpieza de ambos conjuntos de respuestas permanece protegida por `finally`. La ejecución final del propietario confirmó `DENIED_VERIFIED` para ambas rutas.

## 6.1 Rutas alternativas identificadas estáticamente

La configuración revisada no contiene un `Alias` específico para COFEPRIS. Las variantes alcanzables identificadas son:

1. `http://clinica.test/public/storage/cofepris/<GUID>/synthetic-probe.pdf`, que llega directamente al subdirectorio protegido.
2. `http://clinica.test/storage/cofepris/<GUID>/synthetic-probe.pdf`, que pasa por la regla raíz y se reescribe hacia `public/storage/cofepris/...`.

La regla `public/storage/cofepris/.htaccess` debe aplicarse a ambas rutas después de la reescritura. Esto sigue siendo una conclusión estática hasta ejecutar el probe en el Windows/XAMPP del propietario. No se detectaron otros `Alias` aplicables en la configuración inspeccionada.

El probe ampliado prueba las dos variantes secuencialmente, no crea un segundo PDF y elimina el fixture, las respuestas, los encabezados, los errores de curl y el directorio temporal en `finally`. La ejecución HTTP real de ambas variantes queda pendiente porque Codex no tiene Apache accesible.

## 7. Procedimiento exacto para validación manual del propietario

Ejecutar desde el mismo PowerShell donde Apache/XAMPP está activo:

```powershell
Set-Location 'C:\xampp\htdocs\clinica-dolor'

Test-Path -LiteralPath '.\public\storage\cofepris' -PathType Container
Get-Content -LiteralPath '.\public\storage\cofepris\.htaccess'

powershell.exe -NoProfile -ExecutionPolicy Bypass `
  -File '.\scripts\audits\COFEPRIS_HTTP_PROBE_12D01_R3.ps1'
```

La ejecución ampliada debe mostrar una sección por ruta, por ejemplo:

```text
CANONICAL_HTTP_STATUS=403
CANONICAL_SYNTHETIC_BYTES_MATCH=False
CANONICAL_DIRECT_HTTP_ACCESS=False
CANONICAL_RESULT_CLASSIFICATION=DENIED_VERIFIED
REWRITTEN_HTTP_STATUS=403
REWRITTEN_SYNTHETIC_BYTES_MATCH=False
REWRITTEN_DIRECT_HTTP_ACCESS=False
REWRITTEN_RESULT_CLASSIFICATION=DENIED_VERIFIED
CLEANUP_STATUS=PASS
PROCESS_EXIT_CODE=0
```

También se conservan los diagnósticos globales y `ROUTE_COUNT=2`. Un `404` aislado, una salida sin estado HTTP, un `CURL_EXIT_CODE` distinto de cero, `INCONCLUSIVE`, `BYPASS_CONFIRMED` o un error de limpieza no constituyen PASS de la contención y producen código de salida distinto de cero.

### Rutas alternativas

Después de confirmar que el probe crea y limpia sólo su fixture sintético, verificar desde el mismo entorno las variantes realmente alcanzables por la configuración local:

1. URL con `/public/storage/cofepris/...`.
2. URL reescrita sin `/public`, si la regla raíz la hace alcanzable.
3. Cualquier `Alias` o enlace HTTP que el preflight local identifique explícitamente.

No construir URLs con carpetas o folios históricos. Si el probe actual no conserva el fixture el tiempo suficiente para probar una segunda variante, la prueba alternativa debe quedar `NO VERIFICADA` hasta adaptar el probe en una intervención separada y aprobada. No se debe crear un PDF manual dentro del almacenamiento clínico.

### Ruta autorizada del Doctor

No ejecutar curl anónimo contra un PDF real. La comprobación positiva requiere una sesión sintética o fixture autorizado ya existente y debe realizarse manualmente:

- abrir la aplicación local con la sesión autorizada;
- navegar al flujo COFEPRIS del Doctor;
- abrir sólo el registro sintético autorizado;
- comprobar que `/pdf/cofepris/{id}` responde y que el navegador muestra/imprime el PDF;
- revisar DevTools para confirmar que no se solicita una URL bajo `public/storage/cofepris`;
- registrar `AUTHORIZED_PDF_RUNTIME_TEST=PASS` sólo con evidencia real.

Si no existe un fixture autenticado seguro, registrar `AUTHORIZED_PDF_RUNTIME_TEST=NOT_EXECUTED`.

## 8. Preservación y rollback

Estado previo de la configuración específica: `public/storage/cofepris/.htaccess` no existía. El cambio de SEC-02A es únicamente la creación de ese archivo.

Rollback seguro:

1. No retirar automáticamente la regla si una prueba funcional falla.
2. Identificar primero si la causa está en lectura PHP, autorización o resolución de ruta.
3. Mantener el almacenamiento protegido durante la corrección.
4. Restaurar disponibilidad sólo mediante un mecanismo autenticado compatible y aprobado.
5. Retirar la regla únicamente después de disponer de una protección equivalente; nunca como forma de obtener PASS.
6. No tocar, mover, renombrar, borrar ni reasignar PDFs históricos.

La reversión del archivo por sí sola volvería a abrir la exposición anterior, por lo que no se considera un rollback funcional seguro sin una protección sustitutiva.

## 9. Estado de datos históricos

Se preservan sin cambios:

- los seis rangos históricos;
- los 345 registros COFEPRIS;
- los PDFs y carpetas existentes;
- los folios actuales;
- las excepciones 23, 26, 256 y 257.

No se ejecutaron SQL, migraciones, POST, escrituras ni conciliaciones. La identidad histórica y la propiedad clínica siguen pendientes de SEC-02C/12D-02.

## 10. Checklist de cierre SEC-02A

- [x] Regla específica en `public/storage/cofepris/.htaccess`.
- [x] `Require all denied` sin excepciones por extensión, MIME o cookie.
- [x] Sintaxis global de Apache validada con `httpd.exe -t`.
- [x] Probe PowerShell 5.1 y parser sintético validados.
- [x] Acceso directo canónico: `OWNER_VERIFIED`, HTTP 403 y sin bytes sintéticos.
- [x] Limpieza del fixture sintético: `OWNER_VERIFIED`.
- [x] `/pdf/cofepris/337` con sesión Doctor: `OWNER_VERIFIED` y apertura correcta en Chrome.
- [x] Probe ampliado para probar la ruta canónica y la variante reescrita con un único PDF sintético.
- [x] Variante reescrita sin `/public`: `OWNER_VERIFIED`, HTTP 403 y clasificación `DENIED_VERIFIED`.
- [ ] Alias/rutas adicionales: no identificados estáticamente; requieren confirmación local si el entorno los añade.
- [x] Impresión del PDF autorizado: `OWNER_VERIFIED_PASS`, vista previa y páginas mostradas correctamente.
- [x] PDFs históricos, base de datos y código funcional: sin cambios.
- [x] SEC-02B, SEC-02C, SEC-02D y 12D-02: no iniciadas.

**SEC_02A_CLOSURE_READY:** `CLOSED_VERIFIED_LOCAL_SCOPE`.

La contención está verificada para las dos rutas probadas del almacenamiento público COFEPRIS. La migración a almacenamiento privado, la autorización interna por clínica/registro y la identidad documental permanecen fuera de esta fase.

## 11. Informe final

```yaml
TH_DOCTOR_COFEPRIS_HTTP_CONTAINMENT_12D_SEC_02A:
  STATUS: CLOSED_VERIFIED_LOCAL_SCOPE
  ENVIRONMENT: DEVELOPMENT_LOCAL_XAMPP
  BRANCH: Silvino
  APACHE_PREFLIGHT: PASS_STATIC; OWNER_HTTP_EVIDENCE_AVAILABLE
  DOCUMENT_ROOT: C:\\xampp\\htdocs\\clinica-dolor
  ALLOW_OVERRIDE: ALL
  STORAGE_DIRECTORY_EXISTS: YES; REPARSE_POINT: NO
  CONTAINMENT_METHOD: DIRECTORY_HTACCESS_DENY
  CONTAINMENT_FILE: public/storage/cofepris/.htaccess
  CONTAINMENT_RULE: Require all denied
  CONTAINMENT_EFFECTIVE: VERIFIED_FOR_TESTED_PUBLIC_STORAGE_ROUTES
  HTTP_TEST_EXECUTED: OWNER_VERIFIED_CANONICAL_AND_REWRITTEN; NOT_REPEATED_FROM_CODEX
  CANONICAL_HTTP_DENIAL: PASS
  REWRITTEN_HTTP_DENIAL: PASS
  HTTP_UNAUTHENTICATED_STATUS: 403_OWNER_VERIFIED_FOR_BOTH_ROUTES
  SYNTHETIC_BYTES_RETURNED: FALSE_OWNER_VERIFIED
  DIRECT_HTTP_ACCESS: FALSE_OWNER_VERIFIED_FOR_BOTH_ROUTES
  ALTERNATIVE_URL_TESTS: OWNER_VERIFIED_PASS
  AUTHORIZED_PDF_STATIC_COMPATIBILITY: PASS
  AUTHORIZED_PDF_RUNTIME_TEST: OWNER_VERIFIED_PASS
  DOCTOR_UI_COMPATIBILITY: OWNER_VERIFIED_PASS
  DOCTOR_PRINT_PREVIEW: OWNER_VERIFIED_PASS
  SYNTHETIC_FILE_CLEANUP: OWNER_VERIFIED_PASS
  CLINICAL_PDF_MODIFIED: NO
  DATABASE_MODIFIED: NO
  SCHEMA_MODIFIED: NO
  APPLICATION_CODE_MODIFIED: NO
  HTACCESS_MODIFIED: YES
  PROBE_SCRIPT_MODIFIED: YES
  DOCUMENT_CREATED: YES
  PHP_LINT: PASS
  POWERSHELL_SYNTAX: PASS
  GIT_DIFF_CHECK: PASS
  COMMITS: NONE
  DEPLOYMENTS: NONE
  PUBLIC_STORAGE_CONTAINMENT: VERIFIED_FOR_TESTED_ROUTES
  PRIVATE_STORAGE_MIGRATION: NOT_STARTED
  PRODUCTION_VALIDATION: NOT_EXECUTED
  MIGRATIONS_EXECUTED: NO
  SECURITY_FINDING: P1_CONFIRMED_LOCAL_DIRECT_HTTP_ACCESS; CONTAINMENT_VERIFIED_LOCAL_SCOPE
  RESIDUAL_RISKS:
    - Record, clinic and historical document ownership remain later-phase work
    - Generic /descargar route requires separate policy review
    - Migration to private storage remains pending
    - Production and untested alternative routes remain outside this closure
  BLOCKERS:
    - Production validation was not executed
    - SEC-02B requires independent authorization
  NEXT_ACTION: AUTHORIZE_SEC_02B_SEPARATELY_IF_REQUIRED
```

SEC-02B, SEC-02C, SEC-02D y 12D-02 no fueron iniciadas.
