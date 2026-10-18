# TH-DOCTOR-COFEPRIS-DOCUMENT-SECURITY-REMEDIATION-DESIGN-12D-SEC-01

## Diseño de remediación para documentos COFEPRIS

**Proyecto:** Clínica del Dolor
**Directorio revisado:** `C:\xampp\htdocs\clinica-dolor`
**Rama revisada:** `Silvino`
**Tipo de actividad:** inventario estático, compatibilidad y diseño de remediación
**Estado:** diseño pendiente de aprobación e implementación posterior

Este documento no implementa la remediación. No se modificaron PHP, JavaScript, CSS, rutas, Apache, `.htaccess`, `.env`, modelos, controladores, servicios, base de datos ni documentos PDF. Tampoco se repitió la prueba HTTP.

### Clasificación de la evidencia

- **OWNER_VERIFIED:** resultados HTTP sintéticos y conteos SQL aportados por el propietario; no se repitieron aquí.
- **STATICALLY_VERIFIED:** rutas, controladores, modelos, vistas, `.htaccess` y configuración local inspeccionados en el worktree.
- **DESIGN_PROPOSAL:** arquitectura, fases SEC-02, matriz de pruebas y rollback descritos en este documento.
- **NOT_VERIFIED:** comportamiento posterior a una remediación, acceso en producción y autorización Paciente.
- **REQUIRES_OWNER_APPROVAL:** raíz privada, contención, política Paciente, rangos históricos y cualquier backfill.

## 1. Resumen ejecutivo

El propietario confirmó el resultado real de la prueba sintética autorizada:

- `HTTP_STATUS=200`.
- `SYNTHETIC_BYTES_MATCH=True`.
- `DIRECT_HTTP_ACCESS=True`.
- `CONTENT_TYPE=application/pdf`.
- `CURL_EXIT_CODE=0`.
- Limpieza y código de salida final correctos.

Por tanto, un PDF ubicado bajo `public/storage/cofepris` puede ser descargado sin autenticación mediante HTTP directo. La autenticación estática del controlador PDF no protege ese acceso directo.

La recomendación principal es la **Opción A: almacenamiento privado fuera de `DocumentRoot` y entrega mediante un controlador autenticado**. La **Opción B: bloqueo explícito en Apache** es una contención inmediata posible, pero no resuelve por sí sola la autorización por registro, la ruta genérica de descarga, los rangos globales ni la ambigüedad histórica de propiedad.

La remediación debe tratarse como prioridad alta porque la prueba demostró acceso HTTP directo en desarrollo. La evidencia no demostró acceso a contenido clínico real ni exposición en producción; por eso la clasificación de esta evidencia es **P1_CONFIRMED_LOCAL_DIRECT_HTTP_ACCESS**, sin minimizar el impacto potencial si el mismo patrón existiera en otro entorno.

## 2. Hechos y límites de esta actividad

### Evidencia confirmada

La evidencia de ejecución fue proporcionada por el propietario. Codex no volvió a ejecutar la prueba HTTP ni accedió a MySQL, PDFs clínicos o registros reales.

El inventario estático confirmó además:

- `public/storage/cofepris` existe dentro del árbol servido por Apache.
- No existe `.htaccess` específico en `public/storage` ni en `public/storage/cofepris`.
- El `.htaccess` de `public` deja pasar archivos físicos mediante las condiciones `!-f` y `!-d`.
- El controlador PDF usa `public/storage/cofepris/{carpeta}/{folio}.pdf` después de autenticar el contexto del paciente.
- El acceso HTTP directo no pasa por ese controlador.

### Hechos históricos que deben preservarse

La reconciliación previa y los datos aportados por el propietario registran:

- 6 rangos COFEPRIS.
- 1,200 folios declarados.
- 345 registros clínicos.
- 343 coincidencias numéricas y de carpeta.
- Los registros 23 y 26 presentan diferencias de carpeta.
- 1 registro apunta a un paciente inexistente: el registro **256**.
- Los registros **256 y 257** comparten carpeta y folio, y existe el mismo PDF físico.
- El registro 257 corresponde a un paciente válido de la Clínica 1; esa relación no atribuye automáticamente el PDF compartido al registro 257.
- La carpeta física contiene un inventario estático reportado de 2,006 PDFs.

La existencia de un archivo físico no demuestra su propietario clínico. No se debe atribuir, mover, renombrar, duplicar, borrar ni exponer automáticamente ningún documento histórico por esos datos.

## 3. Inventario técnico confirmado

### 3.1 Rutas y controladores

| Ruta | Método | Componente | Contexto actual | Dependencia documental | Riesgo o compatibilidad |
|---|---:|---|---|---|---|
| `/cofepris` | GET | `HomeController::indexCofepris` | Doctor autenticado | Configuración y lista de folios | Debe seguir funcionando aunque el almacenamiento sea privado. |
| `/cofepris/{idCofepris}` | GET | `CofeprisController::cofepris` | Doctor; carga registro y paciente | Vista de detalle | Usa autorización del paciente derivada del registro; requiere autorización por registro y clínica explícita en el diseño final. |
| `/cofepris/paciente/{idPaciente}` | GET | `ClinicaController::pacienteCofepris` | Doctor; paciente autorizado | Listado y modales de COFEPRIS | No depende de una URL pública del archivo para renderizar la tabla. |
| `/cofepris/paciente/{idPaciente}/editar/{idCofepris}` | GET | `ClinicaController::pacienteCofepris` | Doctor; edición contextual | Formulario de registro | La autorización del paciente existe; la pertenencia del `idCofepris` al paciente debe quedar cerrada por consulta/servicio. |
| `/paciente/insert-cofepris` | POST | `ClinicaController::pacienteInsertCofepris` | Doctor; POST y paciente autorizado | Alta/edición de metadata | El modelo actual actualiza por identificador; requiere revisión de propiedad antes de implementar. |
| `/cofepris/insert-folios` | POST | `CofeprisController::insertFolios` | Doctor; CSRF de clínica | Carga de PDF y generación de folios | Escribe en almacenamiento público actual; la futura migración debe conservar validación MIME, tamaño y aislamiento. |
| `/cofepris/carpeta/{idCarpeta}` | GET | `CofeprisController::carpetaCofepris` | Doctor autenticado | Lista de folios de una carpeta | El parámetro de carpeta se recibe directamente; falta demostrar propiedad de rango/clínica. |
| `/cofepris/edit-surtido` | POST | `CofeprisController::editSurtido` | Doctor autenticado | Cambia `surtido` | El controlador no muestra una autorización por registro; queda fuera de implementación y abierto como dependencia. |
| `/buscar/tabla-cofepris/{idPaciente}` | GET | `BusquedasController::tableCofepris` | Doctor o Paciente mediante `guardPatient` | Tabla por paciente | `assertPatientAccess` protege el paciente; el diseño final debe mantenerlo y añadir alcance de registro cuando corresponda. |
| `/buscar/cofepris/{idCofepris}` | GET | `BusquedasController::buscarCofepris` | Carga registro y aplica acceso al paciente asociado | HTML de detalle con enlace PDF | No debe convertirse en un canal para resolver rutas físicas del cliente. |
| `/buscar/tabla-cofepris-folios` | GET | `BusquedasController::buscarCofeprisFolios` | Doctor mediante `guardClinic` | Tabla de rangos | La decisión funcional aprobada es que cada clínica administra sus propios rangos; la identidad administrativa de los rangos históricos aún debe conciliarse. |
| `/pdf/cofepris/{id}` | GET | `PdfController::pdfCofepris` | Doctor autenticado y `assertPatientAccess` | Transmisión/impresión PDF | Es el flujo autorizado existente que debe preservarse o reemplazarse por un resolver privado equivalente. |
| `/descargar/{ruta}/{archivo}` | GET | `DownloadController::download` | Doctor autenticado | Descargas genéricas | Tiene una rama explícita para laboratorio, pero no para COFEPRIS; no debe aceptar COFEPRIS por defecto. |

Las rutas exactas constan en `app/Config/routes.php`. El front controller aplica además autenticación para las rutas `/pdf/` y `/descargar/`, pero esto no intercepta un archivo físico servido por Apache.

### 3.2 Controladores y autorización

#### `app/Middleware/AuthMiddleware.php`

- `authPermisos()` valida cookie/JWT, vista y permisos, y entrega `id_clinica`, usuario, rol y token.
- `authAny()` permite resolver el contexto de vista autenticado.
- `assertPatientAccess()` consulta `pc_paciente` y autoriza al paciente exacto en vista Paciente o a la clínica del paciente en vista Doctor.

Este mecanismo es una base reutilizable para el diseño, pero no sustituye una consulta que resuelva conjuntamente `id_cofepris`, paciente, clínica y documento físico.

#### `app/Controllers/PdfController.php::pdfCofepris`

El método carga el registro COFEPRIS, autentica vista Doctor, ejecuta `assertPatientAccess()` sobre el paciente del registro y construye el PDF desde `carpeta + folio`. La validación debe evolucionar hacia un resolver de documento por identificador de registro, con alcance de clínica y rechazo fail-closed de referencias ambiguas.

#### `app/Controllers/DownloadController.php::download`

La validación de ruta física evita traversal mediante `realpath` y prefijo de almacenamiento, pero la autorización específica visible sólo contempla laboratorio. La ausencia de una política COFEPRIS explícita es un bloqueo de diseño: una ruta genérica no debe convertirse en una segunda vía de acceso a documentos regulados.

### 3.3 Modelo y persistencia relevante

`app/Models/CofeprisModel.php` confirma:

- `cofepris($id)` carga `id_paciente`, `carpeta` y `folio`.
- `getCofepris()` genera enlaces hacia `/pdf/cofepris/{id}`.
- `guardarFolio()` escribe actualmente en `DOCUMENT_ROOT/public/storage/cofepris`, crea carpetas y divide el PDF recibido en archivos por folio.
- `insertPacienteCofepris()` genera metadata y folios usando el rango global activo.
- `editarSurtido()` actualiza por `id` sin una restricción de clínica visible en el método.
- `mostrarTablaCofepris()` filtra por `id_paciente`; el inventario estático observó concatenación de SQL y no autoriza por sí mismo.
- `validaCofeprisFolio()` relaciona COFEPRIS con `pc_paciente`, pero la consulta inspeccionada usa valores interpolados y no prueba propiedad de rango.
- `getCofeprisFolios()` y `foliosPendientes()` consultan rangos globales.

Estos hallazgos son dependencias para el diseño de seguridad. No se modifican en esta tarea.

### 3.4 Vistas y JavaScript

Componentes que dependen del flujo documental o de sus enlaces:

- `app/views/clinica/cofepris.php`: detalle de registro y acción «Imprimir registro», enlazada a `/pdf/cofepris/{id}`.
- `app/views/clinica/pacientes-cofepris.php`: listado, edición, alta, detalle, recuperación AJAX y apertura de `/pdf/cofepris/{id}`.
- `app/views/clinica/cofepris-folios.php`: lista por carpeta y enlace de descarga a `/pdf/cofepris/{id}`; también ejecuta `POST /clinica/cofepris/edit-surtido`.
- `app/views/clinica/configuracion-cofepris.php`: consulta folios, carga sintética futura de PDF y usa CSRF de clínica.
- `app/views/clinica/pacientes-detalle.php`: enlaza al módulo `/clinica/cofepris/paciente/{idPaciente}` y presenta el conteo.
- `app/views/clinica/resumen-clinico.php`: presenta metadata COFEPRIS; no debe recibir una URL física como sustituto.
- `app/views/components/search-bar-doctor.php` y `footer-doctor.php`: dependencias de shell, no deben contener acceso documental directo.

No se detectó en las vistas inspeccionadas una dependencia que requiera exponer `public/storage/cofepris/...` directamente al navegador para el flujo principal. La dependencia pública explícita es `/pdf/cofepris/{id}`.

## 4. Contexto Apache y exposición

El contexto revisado es:

```text
DocumentRoot "C:/xampp/htdocs"
<Directory "C:/xampp/htdocs">
    Options Indexes FollowSymLinks Includes ExecCGI
    AllowOverride All
</Directory>

ServerName clinica.test
DocumentRoot "C:/xampp/htdocs/clinica-dolor"
<Directory "C:/xampp/htdocs/clinica-dolor">
    AllowOverride All
</Directory>
```

El `.htaccess` raíz envía solicitudes al directorio `public`. `public/.htaccess` sólo reescribe cuando el recurso no es archivo ni directorio. No hay regla específica para `public/storage/cofepris`. La combinación, junto con la evidencia HTTP real, confirma que existe un bypass del front controller y de la autorización de aplicación.

La exposición no se remedia eliminando sólo el enlace de una vista: el archivo físico continúa siendo direccionable mientras permanezca bajo el árbol público y Apache lo entregue.

## 5. Matriz de compatibilidad

| Superficie | Comportamiento que debe conservarse | Efecto de Opción A | Efecto de Opción B | Estado de diseño |
|---|---|---|---|---|
| Doctor: detalle COFEPRIS | Abrir/visualizar/imprimir el registro autorizado | Se conserva mediante resolver privado y respuesta PDF autenticada | Se conserva si `/pdf/cofepris/{id}` no depende de la ruta física pública | Compatible, pendiente de implementación y pruebas. |
| Doctor: listado del paciente | Tabla, edición, detalle y enlace de impresión | La URL del botón puede permanecer en `/pdf/cofepris/{id}` | Igual, pero todos los archivos públicos deben quedar bloqueados | Compatible según diseño. |
| Paciente: módulos e historial | No habilitar descargas nuevas sin política aprobada | Denegación por defecto hasta decidir si el Portal Paciente puede descargar COFEPRIS | El bloqueo no define autorización de Paciente | Política pendiente; preservar sólo comportamiento explícitamente autorizado. |
| Configuración de folios | Cargar un PDF autorizado, crear rango y contar folios | Escribe en almacenamiento privado con referencia interna | La escritura actual puede continuar sólo como transición y bajo contención comprobada | Requiere etapa de migración. |
| Folios por carpeta | Consultar rangos y marcar surtido | Debe resolver rango y clínica, no aceptar carpeta arbitraria | Apache no corrige la autorización del rango | Riesgo abierto. |
| `/descargar/{ruta}/{archivo}` | Descargas de módulos que ya tengan contrato | COFEPRIS debe quedar fuera de esta ruta o tener resolver dedicado | El bloqueo no autoriza esta ruta | Rechazar COFEPRIS por defecto. |
| PDF histórico | No perder bytes ni modificar documentos | Migrar sólo con manifiesto, hash y cuarentena de ambiguos | No mover; bloquear acceso público | Requiere decisión de propiedad. |
| Enlaces `target=_blank` | Mantener apertura de documento autorizado | Sigue siendo válido con headers privados y autorización | Sigue siendo válido si el backend ya no lee una ruta pública | Compatible. |

La Opción B no debe publicarse como solución completa: un bloqueo Apache evita el bypass directo, pero los endpoints de aplicación todavía deben validar paciente, clínica, registro y documento.

## 6. Arquitecturas consideradas

### Opción A — almacenamiento privado externo a todo `DocumentRoot` (recomendada)

Diseño propuesto:

1. Crear una raíz privada físicamente externa a todos los `DocumentRoot`, `Alias`, enlaces públicos y demás raíces servidas por Apache. Una carpeta dentro de `C:\xampp\htdocs\clinica-dolor` no cumple este criterio aunque tenga un nombre “privado”. La ubicación definitiva no se elige en SEC-01: requiere aprobación del propietario y de la operación local.
2. Verificar antes de usarla: ruta física canónica, directorio no publicado, permisos mínimos de lectura/escritura para el proceso PHP, ausencia de Alias público, separación de temporales y definitivos, y ausencia de enlaces/reparse points que salgan de la raíz.
3. Mantener en base de datos únicamente una referencia interna segura o un identificador de documento; no aceptar `carpeta`, `folio` o ruta física como autorización.
4. Resolver `id_cofepris` mediante una consulta que una registro, `pc_paciente` y clínica, validando simultáneamente:
   - `cofepris.id` solicitado.
   - `cofepris.id_paciente` autorizado.
   - `pc_paciente.id_clinica` igual a la clínica del token Doctor.
   - reglas explícitas para vista Paciente, si se habilita.
   - referencia física única y no ambigua.
5. Transmitir sólo después de resolver el documento. El controlador debe abrir un archivo dentro de la raíz privada, con comprobación de ruta canónica y rechazo ante enlaces/reparse points o referencias fuera de raíz.
6. Emitir headers coherentes, por ejemplo `Content-Type: application/pdf`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store` y un `Content-Disposition` definido por el contrato de impresión/descarga.
7. El cliente conserva una URL lógica por identificador de registro, nunca una URL pública de almacenamiento.

Ventajas: elimina el bypass físico, centraliza la autorización y permite auditar cada entrega. Costes: migración, resolución histórica, cambio de escritura y pruebas de impresión/descarga.

### Opción B — contención Apache

Bloquear explícitamente el acceso HTTP a `public/storage/cofepris` y sus alias conocidos, sin alterar otras carpetas. Esta opción puede usarse como contención después de verificar que el flujo `/pdf/cofepris/{id}` puede leer el documento desde el backend autorizado sin que Apache sirva el archivo directamente.

Limitaciones:

- No corrige la autorización por registro, rango o clínica.
- No corrige la ruta genérica `/descargar/{ruta}/{archivo}`.
- No decide la propiedad del documento compartido por 256/257 ni del registro huérfano 256.
- No impide que una futura vista reintroduzca una URL física.
- Requiere prueba real con el propietario; no se implementa en esta tarea.

### Decisión recomendada

Adoptar Opción A como arquitectura definitiva y Opción B como contención temporal sólo si el propietario aprueba una ventana de bloqueo y un plan de verificación. No usar la contención para declarar cerrado el riesgo de autorización.

## 7. Diseño de autorización y aislamiento por clínica

El servicio/resolver futuro debe ser fail-closed:

- Obtener `id_clinica`, identidad, rol y vista exclusivamente del contexto autenticado del servidor.
- Para Doctor, unir el registro COFEPRIS con `pc_paciente` y exigir `pc_paciente.id_clinica = auth.id_clinica`.
- Para Paciente, mantener denegación por defecto hasta que exista una política explícita; si se aprueba acceso, exigir además `pc_paciente.id = auth.id_usuario` o el identificador paciente validado por el mecanismo vigente.
- Nunca autorizar con un `id_paciente`, `id_cofepris`, carpeta o folio tomado sólo del navegador.
- Rechazar con respuesta controlada registros inexistentes, registros de otra clínica y documentos sin referencia única.
- No devolver la existencia, ruta o metadata de un registro que el contexto no pueda leer.
- Registrar de forma no clínica el motivo de rechazo y el identificador interno de auditoría, según el sistema aprobado.

La decisión funcional aprobada es que **cada clínica administra sus propios rangos COFEPRIS**. Las consultas actuales no demuestran todavía que esa regla esté aplicada a todos los caminos. Para los seis rangos históricos quedan pendientes únicamente:

1. identificar su propietario administrativo;
2. definir el tratamiento de rangos sin propietario demostrado;
3. aprobar el procedimiento de conciliación y autorización de cualquier backfill.

No debe asignarse automáticamente ninguno de los seis rangos a Clínica 1. Tampoco debe inferirse propiedad por coincidencia de carpeta, folio, fecha o existencia del PDF.

## 8. Tratamiento de históricos y documentos ambiguos

El plan no debe hacer una migración masiva irreversible. Se propone:

1. Generar un manifiesto de sólo lectura con `id_cofepris`, paciente, clínica derivada, carpeta, folio, ruta candidata, existencia, tamaño y SHA-256. El manifiesto no debe incluir contenido clínico.
2. Marcar como **válidos para migración** sólo los registros con paciente y clínica inequívocos y una referencia física única.
3. Marcar 23 y 26 como **requieren conciliación** por diferencia de carpeta.
4. Marcar el registro 256 como **huérfano**, sin asignarlo a una clínica ni hacerlo descargable.
5. Marcar 256 y 257 como **documento físico ambiguo compartido**. No atribuir el PDF a uno de ellos automáticamente. Requieren decisión del propietario y evidencia independiente.
6. Conservar los PDFs originales hasta terminar la conciliación, pero impedir su acceso HTTP público una vez aprobada la contención.
7. Copiar/migrar sólo después de validar hashes y registrar el resultado. No borrar el origen hasta aprobar la verificación y el rollback.

La migración no debe “reparar” la base de datos por inferencia ni modificar folios históricos sin una decisión clínica/operativa independiente.

### Dependencia de identidad documental

El esquema inspeccionado permite identificar un registro clínico mediante `cofepris.id`, pero no demuestra que `carpeta + folio` sea una identidad documental única: 256 y 257 comparten esa referencia y el mismo PDF físico. Por ello, SEC-01 no debe asumir que la ruta física actual identifica el documento.

La fase 12D-02 deberá determinar si el esquema existente puede representar de forma inequívoca, sin ampliar tablas, los siguientes conceptos separados:

- autorización del registro COFEPRIS;
- propiedad de la clínica;
- referencia física privada;
- integridad del archivo mediante hash;
- identidad documental inequívoca y estado de cuarentena.

Si el esquema no puede expresar esa separación, 12D-02 deberá proponer una ampliación estructural y su migración controlada. No se implementa una ampliación estructural en SEC-01. Los registros 256 y 257 permanecen en cuarentena lógica documental hasta resolución independiente.

## 9. Secuencia propuesta para SEC-02

Este plan es una propuesta futura; ninguna etapa se ejecutó. La implementación deberá dividirse en cuatro fases y no comenzar hasta la aprobación técnica de SEC-01.

### SEC-02A — contención HTTP

- Comprobar la compatibilidad del controlador PDF existente con una lectura PHP autorizada independiente del servicio estático de Apache.
- Verificar que el bloqueo HTTP no impida la lectura autorizada desde la raíz privada que se defina posteriormente.
- Identificar reglas Apache específicas, reversibles y aplicables sólo a COFEPRIS.
- Revisar rutas alternativas, `Alias`, enlaces y cualquier raíz pública equivalente.
- Definir pruebas negativas HTTP para acceso sin sesión, con cookie, con JWT, mediante alias y mediante `/descargar`.
- Definir pruebas positivas de descarga autorizada, impresión y navegador.
- Documentar rollback sin restablecer nunca el acceso público indiscriminado.
- No aplicar todavía el bloqueo.

### SEC-02B — resolver autorizado y almacenamiento privado para nuevos documentos

- Elegir, con aprobación del propietario, una raíz físicamente externa a todo `DocumentRoot`, `Alias` y enlace público.
- Implementar un resolver por `id_cofepris` que valide registro, paciente, clínica, permisos y referencia física única.
- Cambiar la escritura de nuevos PDFs para que no cree copias bajo `public/`.
- Mantener validación de tipo, tamaño, CSRF, aislamiento, archivos temporales separados y limpieza segura.
- Mantener enlaces lógicos por identificador, sin aceptar rutas físicas del navegador.
- Mantener denegación por defecto para Portal Paciente hasta decisión explícita.

### SEC-02C — transición histórica con manifiesto e integridad

- Generar manifiesto de sólo lectura con existencia, tamaño y SHA-256, sin contenido clínico.
- Migrar sólo documentos con identidad y clínica inequívocas.
- Mantener 256 y 257 en cuarentena lógica hasta resolver el documento físico compartido.
- Mantener el registro 256 sin asignación clínica por ser huérfano.
- Conciliar los rangos históricos y autorizar cualquier backfill antes de modificar referencias.
- Verificar hashes, permisos, trazabilidad y ausencia de acceso HTTP directo.

### SEC-02D — regresión, verificación y cierre

- Ejecutar la matriz completa con archivos sintéticos y pacientes sintéticos autorizados.
- Verificar controladores, almacenamiento físico, rutas alternativas, impresión y descarga en navegador.
- Verificar que un fallo del resolver no cree copias públicas ni reabra el origen.
- Reconciliar rutas, archivos, referencias y manifiestos.
- Obtener aprobación expresa antes de declarar cierre o preparación para producción.

## 10. Matriz de pruebas propuesta

Estas pruebas son para la fase de implementación y no fueron ejecutadas en esta actividad.

| ID | Caso | Resultado esperado | Estado actual |
|---|---|---|---|
| AUTH-01 | Usuario sin sesión solicita `/pdf/cofepris/{id}` | Rechazo controlado, sin bytes PDF | NO EJECUTADO |
| AUTH-02 | Usuario sin sesión solicita almacenamiento físico | Rechazo HTTP, sin PDF | EXPOSICIÓN CONFIRMADA PREVIA; REMEDIACIÓN NO EJECUTADA |
| AUTH-03 | Doctor de clínica propietaria solicita registro propio | PDF correcto, sin ruta física expuesta | NO EJECUTADO |
| AUTH-04 | Doctor de otra clínica solicita el mismo registro | `403`/`404` controlado, sin bytes | NO EJECUTADO |
| AUTH-05 | Paciente no autorizado solicita el documento | Rechazo sin filtración | NO EJECUTADO |
| AUTH-06 | `id_cofepris` inexistente o manipulado | Rechazo controlado | NO EJECUTADO |
| AUTH-07 | Documento ambiguo 256/257 | No entrega automática; cuarentena | NO EJECUTADO |
| AUTH-08 | Documento físicamente ausente | Error controlado, sin fallback público | NO EJECUTADO |
| HTTP-01 | GET directo a `public/storage/cofepris/...` sin credenciales | Rechazo; no PDF | EXPOSICIÓN CONFIRMADA PREVIA; REMEDIACIÓN NO EJECUTADA |
| HTTP-02 | GET directo con cookie/JWT | Sigue rechazado por ser acceso físico | NO EJECUTADO |
| HTTP-03 | GET `/descargar/cofepris/...` | Rechazo o ruta no permitida | NO EJECUTADO |
| HTTP-04 | Alias, enlace o ruta Apache alternativa | Sin bypass equivalente | NO EJECUTADO |
| HTTP-05 | Descarga autorizada | PDF esperado, `nosniff`, cache privada, bytes íntegros | NO EJECUTADO |
| HTTP-06 | Visualización e impresión en navegador | Funciona sólo con autorización y sin URL física | NO EJECUTADO |
| REG-01 | Alta de nuevo folio | Sin copias públicas; clínica correcta | NO EJECUTADO |
| REG-02 | Edición de registro de otra clínica | Ninguna fila ni archivo modificado | NO EJECUTADO |
| REG-03 | Edición de `surtido` con ID manipulado | Rechazo fail-closed | NO EJECUTADO |
| REG-04 | Listado por paciente/rango | Sólo registros autorizados | NO EJECUTADO |
| REG-05 | Generación de nuevo PDF | Archivo sólo en raíz privada, sin copia bajo `public/` | NO EJECUTADO |
| REG-06 | Integridad SHA-256 antes/después de migración | Hash idéntico y trazable | NO EJECUTADO |
| REG-07 | Restauración tras fallo del resolver | Disponibilidad sólo mediante mecanismo autenticado | NO EJECUTADO |
| REG-08 | Repetición/idempotencia y error de red | Sin duplicar archivos ni metadata | NO EJECUTADO |

Todas las pruebas de escritura deben usar datos sintéticos aislados y un mecanismo de limpieza aprobado. No deben usar PDFs clínicos reales ni el paciente real 310 para escritura.

## 11. Rollback y reversibilidad

El rollback debe ser diseñado antes de migrar y nunca puede volver a habilitar la exposición pública de los documentos:

1. Crear un manifiesto inmutable de origen con identificadores, rutas candidatas, tamaños y hashes; no incluir contenido clínico.
2. Mantener la referencia lógica anterior hasta validar el nuevo resolver, pero mantener bloqueado el acceso público directo.
3. Activar el nuevo resolver mediante una configuración/feature flag reversible, si la implementación final lo permite.
4. Ante un fallo, recuperar la disponibilidad funcional mediante el mecanismo autenticado y una copia privada fuera de todos los `DocumentRoot`; no asumir que el controlador antiguo funcionará después del traslado físico.
5. Si el nuevo resolver falla, el modo seguro es rechazar temporalmente la entrega antes que publicar el origen o aceptar una ruta física. La recuperación debe reparar o sustituir el resolver autenticado, no desactivar la contención.
6. Conservar copias privadas, hashes y logs de migración hasta completar la conciliación.
7. Para documentos ambiguos, el rollback no debe cambiar su estado de cuarentena ni atribuirlos a un paciente.
8. Declarar rollback completo sólo después de repetir la matriz AUTH, HTTP y REG completa.

No se propone restaurar ni modificar datos clínicos automáticamente. Cualquier migración de esquema o cambio de propiedad requiere aprobación separada.

## 12. Riesgos residuales y dependencias abiertas

- Almacenamiento actual bajo `DocumentRoot` queda expuesto mientras no se aplique contención.
- El acceso directo ya fue confirmado por la prueba sintética del propietario.
- `/descargar/{ruta}/{archivo}` no tiene política COFEPRIS específica visible.
- `carpetaCofepris()` recibe una carpeta y la vista consulta rangos sin alcance de clínica demostrado.
- `editSurtido()` actualiza por ID sin alcance de registro visible en el controlador/modelo revisado.
- La regla funcional aprobada es que cada clínica administra sus propios rangos, pero las consultas actuales no demuestran todavía ese alcance en todos los caminos.
- Existen concatenaciones SQL en consultas COFEPRIS que requieren una remediación técnica separada; no se corrigen aquí.
- Los archivos 23/26 y 256/257 no tienen propiedad histórica inequívoca con la evidencia disponible.
- El registro 256 es el único huérfano confirmado y no debe asignarse automáticamente.
- El inventario físico de 2,006 PDFs puede contener archivos sin referencia clínica o con nombre no normalizado.
- No se ha definido todavía si el Portal Paciente puede ver documentos COFEPRIS.
- La impresión/descarga puede depender de headers, popup y `target=_blank`; debe probarse en navegador y con sesión sintética.

## 13. Decisiones requeridas del propietario

1. Aprobar Opción A como arquitectura definitiva.
2. Autorizar o rechazar la Opción B como contención inmediata.
3. Definir la raíz privada externa a todos los `DocumentRoot`, `Alias` y enlaces públicos, junto con sus permisos operativos.
4. Confirmar la política de acceso del Portal Paciente para COFEPRIS; mientras tanto, mantener denegación por defecto.
5. Identificar administrativamente los seis rangos históricos.
6. Aprobar el tratamiento de rangos históricos sin propietario demostrado y el procedimiento de backfill.
7. Resolver 23 y 26 con evidencia documental independiente.
8. Resolver el PDF compartido de 256/257 sin atribución automática y mantenerlo en cuarentena.
9. Aprobar el manifiesto de migración, retención de origen y ventana de rollback.
10. Autorizar una fase posterior de implementación y pruebas autenticadas sintéticas.

## 14. Conclusión

El problema es real y está confirmado por HTTP: el almacenamiento COFEPRIS público permite descargar un PDF sintético sin autenticación. La corrección debe separar almacenamiento de presentación, resolver documentos por registro autorizado y aplicar aislamiento por clínica y paciente. El diseño queda listo para decisión, pero no para declarar remediación cerrada: no se implementó ningún cambio, no se ejecutó migración y no se verificó todavía el comportamiento posterior.

```yaml
TH_DOCTOR_COFEPRIS_DOCUMENT_SECURITY_REMEDIATION_DESIGN_12D_SEC_01:
  STATUS: DESIGN_READY_PENDING_OWNER_APPROVAL
  CONFIRMED_HTTP_EXPOSURE: YES_OWNER_CONFIRMED_DIRECT_UNAUTHENTICATED_PDF_ACCESS
  SECURITY_PRIORITY: P1_CONFIRMED_LOCAL_DIRECT_HTTP_ACCESS
  AFFECTED_CONTROLLERS:
    - app/Controllers/CofeprisController.php
    - app/Controllers/ClinicaController.php
    - app/Controllers/BusquedasController.php
    - app/Controllers/PdfController.php
    - app/Controllers/DownloadController.php
    - app/Controllers/HomeController.php
  AFFECTED_MODELS:
    - app/Models/CofeprisModel.php
    - app/Models/PacienteModel.php
  AFFECTED_VIEWS:
    - app/views/clinica/cofepris.php
    - app/views/clinica/pacientes-cofepris.php
    - app/views/clinica/cofepris-folios.php
    - app/views/clinica/configuracion-cofepris.php
    - app/views/clinica/pacientes-detalle.php
    - app/views/clinica/resumen-clinico.php
  AFFECTED_ROUTES:
    - GET /cofepris
    - GET /cofepris/{idCofepris}
    - GET /cofepris/paciente/{idPaciente}
    - GET /cofepris/paciente/{idPaciente}/editar/{idCofepris}
    - POST /cofepris/insert-folios
    - POST /paciente/insert-cofepris
    - GET /cofepris/carpeta/{idCarpeta}
    - POST /cofepris/edit-surtido
    - GET /buscar/tabla-cofepris/{idPaciente}
    - GET /buscar/cofepris/{idCofepris}
    - GET /buscar/tabla-cofepris-folios
    - GET /pdf/cofepris/{id}
    - GET /descargar/{ruta}/{archivo}
  PUBLIC_URL_DEPENDENCIES:
    - /pdf/cofepris/{id}
    - /public/storage/cofepris/{carpeta}/{folio}.pdf
    - /descargar/{ruta}/{archivo} (generic route; COFEPRIS policy absent)
  DOCTOR_COMPATIBILITY: AUTHORIZED_PDF_FLOW_CAN_BE_PRESERVED_WITH_PRIVATE_RESOLVER
  PATIENT_COMPATIBILITY: POLICY_REQUIRED_BEFORE_IMPLEMENTATION
  HISTORICAL_PDF_COMPATIBILITY: QUARANTINE_AND_MANIFEST_REQUIRED; NO_AUTO_ATTRIBUTION
  RECOMMENDED_ARCHITECTURE: OPTION_A_PRIVATE_STORAGE_OUTSIDE_DOCUMENT_ROOT_WITH_AUTHENTICATED_CONTROLLER
  IMMEDIATE_CONTAINMENT_DESIGN: OPTION_B_EXPLICIT_APACHE_BLOCK_AS_INTERIM_ONLY; NOT_IMPLEMENTED
  PRIVATE_STORAGE_DESIGN: EXTERNAL_TO_ALL_DOCUMENT_ROOTS_ALIASES_AND_PUBLIC_LINKS; UNIQUE_RECORD_RESOLVER_SCOPED_BY_RECORD_PATIENT_AND_CLINIC
  AUTHORIZED_DOWNLOAD_DESIGN: LOGICAL_RECORD_ID_ONLY_PRIVATE_HEADERS_NOSNIFF_NO_STORE_FAIL_CLOSED
  CLINIC_ISOLATION_DESIGN: SESSION_CLINIC_JOINED_TO_PATIENT_AND_RECORD; NO_CLIENT_ONLY_SCOPE
  DOCUMENT_256_257_HANDLING: QUARANTINE_SHARED_PHYSICAL_PDF; OWNER_NOT_PROVEN
  HISTORICAL_RANGE_OWNERSHIP: ADMINISTRATIVE_IDENTITY_NOT_PROVEN; PER_CLINIC_POLICY_APPROVED; OWNER_DECISION_REQUIRED_FOR_SIX_HISTORICAL_RANGES
  IMPLEMENTATION_PLAN_READY: YES_PENDING_APPROVAL
  TEST_MATRIX_READY: YES_NOT_EXECUTED
  ROLLBACK_PLAN_READY: YES_NO_PUBLIC_REEXPOSURE
  DOCUMENT_CREATED: YES
  CODE_MODIFIED: NO
  DATABASE_MODIFIED: NO
  APACHE_MODIFIED: NO
  PDF_MODIFIED: NO
  MIGRATIONS_EXECUTED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS:
    - Owner approval of architecture and containment window
    - Explicit COFEPRIS access policy for Portal Paciente
    - Administrative ownership and backfill decisions for six historical ranges
    - Resolution of orphan record 256 and ambiguous document 256/257
    - Future implementation and synthetic authenticated validation
  OWNER_DECISIONS_REQUIRED:
    - Approve Option A
    - Approve or reject interim Option B
    - Define a private storage root external to all DocumentRoots, Aliases and public links
    - Define patient access policy
    - Adjudicate records 23, 26, 256 and 257
    - Define treatment of orphan record 256
    - Approve migration manifest and rollback window
  NEXT_RECOMMENDED_PHASE: OWNER_APPROVAL_THEN_IMPLEMENT_PRIVATE_STORAGE_AND_AUTHORIZED_RESOLVER
```
