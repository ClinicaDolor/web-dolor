# TH-SEC-P1-TRIAGE-01 — Auditoría focal de riesgos P1

**Proyecto:** Clínica del Dolor  
**Fecha:** 2026-10-12  
**Modo:** READ-ONLY + DOCUMENTACIÓN  
**Implementación:** NO AUTORIZADA  
**Producción:** NO AUTORIZADA  
**Alcance:** Historia Clínica tradicional P01–P11 y sus dependencias de autorización  
**Exclusión:** Entrevista Conversacional; no se modificó ni se usó como base para conclusiones de esta auditoría.

## 1. Resumen ejecutivo

Esta auditoría reclasifica los riesgos P1 inventariados en TH-SEC-AUTH-08 mediante inspección de rutas, controladores, modelos, middleware y documentación previa. La aplicación registra y alcanza las rutas externas, PDF, descarga y COFEPRIS descritas en el encargo. Se observan controles de autenticación de sesión en varias superficies, pero la autenticación no equivale a autorización funcional ni a propiedad clínica del recurso.

Hallazgos principales:

- Las rutas de recetas externas por ID, edición, búsqueda, inserción y actualización exigen sesión Doctor, pero no muestran una comprobación de clínica emisora, propietario verificable o permiso de operación independiente.
- El endpoint de PDF de receta externa no muestra autenticación ni autorización de recurso antes de cargar y generar el documento.
- La descarga genérica valida ubicación física mediante `realpath()` y `basename()`, pero no identifica el recurso clínico ni verifica que el archivo pertenezca a la clínica/actor autorizado.
- COFEPRIS tiene una ruta de detalle protegida por acceso al paciente vigente, pero las rutas de carpeta y edición de surtido sólo muestran autenticación Doctor; la inserción de folios tampoco muestra un alcance de clínica explícito en el controlador.
- El modelo de recetas internas sí restringe varias consultas a la clínica vigente del paciente, pero eso no resuelve propiedad histórica después de un traslado.
- El catálogo de roles, permisos y la identidad técnica del superadministrador no pudieron verificarse porque la guarda fail-closed bloqueó cualquier conexión cuando `.env` resolvió `bd_clinica`.

No existe evidencia dinámica nueva en esta fase. No se ejecutaron solicitudes HTTP de escritura, PDF, descargas, SQL, migraciones ni pruebas de explotación. Las conclusiones `CODE_CONFIRMED` describen omisiones visibles en rutas alcanzables; no equivalen por sí solas a explotación dinámica demostrada.

## 2. Alcance, restricciones y criterios de evidencia

Se revisaron únicamente las superficies necesarias para los P1 de TH-SEC-AUTH-08:

- autenticación y autorización del Doctor;
- recetas internas y externas;
- PDF y descargas;
- COFEPRIS;
- pertenencia a clínica vigente y límites de propiedad histórica.

Se excluyó la Entrevista Conversacional. No se modificaron sus servicios, controladores, rutas, vistas, fixtures ni documentación.

Estados utilizados:

- **CODE_CONFIRMED:** la cadena de código examinada muestra una omisión concreta de control en una ruta registrada y alcanzable; no significa explotación dinámica.
- **STATIC_RISK:** hay un patrón potencialmente inseguro, pero falta una relación, configuración o prueba para confirmar impacto.
- **DYNAMIC_CONFIRMED:** no utilizado en esta fase; no hubo pruebas dinámicas.
- **NOT_VERIFIED:** no existe evidencia suficiente para concluir.
- **MITIGATED_IN_CODE:** el código contiene un control verificable para el alcance específico, normalmente sólo clínica vigente.
- **NOT_REACHABLE:** no utilizado para rutas registradas; las rutas no encontradas se marcan `NOT_FOUND`.

## 3. Preflight y preservación del entorno

| Control | Resultado | Evidencia |
|---|---|---|
| Directorio | PASS | `C:\xampp\htdocs\clinica-dolor` |
| Rama | PASS | `Silvino` |
| HEAD actual | PASS | `1b08d9b4c41080c176fbe4588212863f3a1ac18c` |
| HEAD reportado previamente | PASS | Coincide con el HEAD actual |
| Worktree | PRESERVADO | Existían cambios locales y archivos no rastreados; no se hizo reset, clean ni staging |
| Base real | NO ACCEDIDA | No se abrió PDO contra `bd_clinica` |
| Base sintética | DYNAMIC_VERIFICATION_BLOCKED | La configuración activa no resuelve una base allowlisted |
| HTTP de escritura | NONE | No se ejecutaron POST, PUT, PATCH o DELETE |
| PDFs/descargas | NONE | No se generaron ni descargaron documentos |

La guarda de entorno detectó `DB_NAME=bd_clinica` y abortó antes de la conexión. No se modificó `.env`, no se sustituyeron credenciales y no se desactivó la protección.

El repositorio tenía numerosos cambios previos. Esta auditoría sólo crea este informe y añade una referencia de estado al cierre maestro; no atribuye los cambios de aplicación existentes a esta ejecución.

## 4. Documentos revisados

Todos los documentos solicitados están presentes:

| Documento | Resultado | Uso |
|---|---|---|
| `TH_SEC_AUTH_08_ROLE_AND_MULTICLINIC_AUTH_AUDIT.md` | FOUND | Fuente de los cinco grupos P1 y del bloqueo de roles/superadmin |
| `TH_SEC_AUDIT_01_TRACEABILITY.md` | FOUND | Trazabilidad y límites históricos |
| `TH_SEC_FIX_01_RECIPE_TENANT_AUTHORIZATION.md` | FOUND | Aislamiento actual de recetas internas |
| `TH_SEC_FIX_01B_HISTORICAL_RECIPE_OWNERSHIP.md` | FOUND | Propiedad histórica no resuelta |
| `TRADITIONAL_HISTORY_CLOSURE_MASTER.md` | FOUND | Estado consolidado previo |
| `TH_SEC_ARCH_01_HISTORICAL_RECIPE_OWNERSHIP_V1.1.md` | FOUND | Diseño futuro, no evidencia de implementación |
| `TH_SEC_ARCH_01_DATA_MODEL_PROPOSAL_V1.1.md` | FOUND | Modelo futuro, no evidencia de implementación |

Los documentos de arquitectura V1.0/V1.1 no se editaron.

## 5. Inventario de rutas y cadena de ejecución

Las rutas siguientes están registradas en `app/Config/routes.php` y se conectan con los métodos indicados:

| Método | Ruta | Controlador/método | Estado de alcanzabilidad | Evidencia |
|---|---|---|---|---|
| GET | `/receta-externo/{id}` | `ClinicaController::recetaExternoDetalle` | REGISTERED | `routes.php:131`, controlador `:67-100` |
| GET | `/receta-externo/editar/{id}` | `ClinicaController::recetaExternoEditar` | REGISTERED | `routes.php:132`, controlador `:104-137` |
| POST | `/paciente/insert-receta-externo` | `ClinicaController::pacienteInsertRecetaExterno` | REGISTERED | `routes.php:133`, controlador `:141-163` |
| POST | `/paciente/edit-receta-externo` | `ClinicaController::pacienteEditRecetaExterno` | REGISTERED | `routes.php:134`, controlador `:166-188` |
| GET | `/buscar/tabla-recetas-externos` | `BusquedasController::tableRecetasExternos` | REGISTERED | `routes.php:196`, controlador `:83-89` |
| GET | `/buscar/receta-externo/{id}` | `BusquedasController::buscarRecetaExterno` | REGISTERED | `routes.php:197`, controlador `:91-97` |
| POST | `/pdf/receta-externo/{id}` | `PdfController::pdfRecetaExterno` | REGISTERED | `routes.php:234`, controlador `:239-295` |
| GET | `/descargar/{ruta}/{archivo}` | `DownloadController::download` | REGISTERED | `routes.php:239`, controlador `:11-43` |
| POST | `/cofepris/insert-folios` | `CofeprisController::insertFolios` | REGISTERED | `routes.php:141`, controlador `:77-122` |
| GET | `/cofepris/{idCofepris}` | `CofeprisController::cofepris` | REGISTERED | `routes.php:143`, detalle protegido por paciente |
| GET | `/cofepris/carpeta/{idCarpeta}` | `CofeprisController::carpetaCofepris` | REGISTERED | `routes.php:149`, controlador `:124-141` |
| POST | `/cofepris/edit-surtido` | `CofeprisController::editSurtido` | REGISTERED | `routes.php:150`, controlador `:143-165` |
| GET | `/buscar/tabla-cofepris/{idPaciente}` | `BusquedasController::tableCofepris` | REGISTERED | `routes.php:204`, guardado por `guardPatient` |
| GET | `/buscar/cofepris/{idCofepris}` | `BusquedasController::buscarCofepris` | REGISTERED | `routes.php:205`, requiere revisar paciente asociado |
| GET | `/pdf/cofepris/{id}` | `PdfController::pdfCofepris` | REGISTERED | `routes.php:235`, `assertPatientAccess` visible |
| DELETE/POST externo | ruta de eliminación | — | NOT_FOUND | No se encontró una ruta externa de eliminación en `routes.php`; no asumir que no exista en otro despliegue |

La ruta de listado D14 `/clinica/recetas` usa recetas internas filtradas por clínica vigente y marca el catálogo externo como bloqueado en la vista; esa contención no elimina las rutas independientes anteriores.

## 6. P1-A — Recetas externas

### 6.1 Autenticación y lectura por ID

`ClinicaController::recetaExternoDetalle($id)` en `app/Controllers/ClinicaController.php:67-100` crea `AuthMiddleware('clinica')` y ejecuta `authPermisos()` en `:68-74`. Después llama `RecetaExternoModel::receta($id)` y construye la vista con los datos recuperados. No se observa una llamada a `assertPatientAccess()`, una comparación de clínica emisora, un permiso funcional de lectura ni un servicio de propietario de recurso.

`ClinicaController::recetaExternoEditar($id)` en `:104-137` repite el patrón: sesión Doctor y carga por ID, sin comprobación de propiedad o permiso de edición independiente. La edición está protegida por autenticación, pero no se demuestra que el actor pueda editar ese recurso.

**Clasificación:** `CODE_CONFIRMED` para omisión de autorización de recurso; impacto dinámico `NOT_VERIFIED`.  
**Severidad:** P1 potencial, porque la superficie contiene datos clínicos y admite ID arbitrario.

### 6.2 Listado y búsqueda AJAX

`BusquedasController::tableRecetasExternos()` en `app/Controllers/BusquedasController.php:83-89` llama `guardClinic()` (`:85`), que únicamente ejecuta `authPermisos()` (`:32-35`), y luego `RecetaExternoModel::mostrarTablaRecetas()` (`:86-87`). El modelo hace `SELECT * FROM receta_medica_externos ORDER BY fecha_hora DESC` en `app/Models/RecetaExternoModel.php:190-193`, sin parámetro de clínica.

`BusquedasController::buscarRecetaExterno($id)` en `:91-97` usa la misma guarda y llama `getReceta($id)`. El modelo consulta por ID parametrizado en `RecetaExternoModel.php:222-227`, pero no añade una relación de propietario.

**Clasificación:** `CODE_CONFIRMED` para ausencia de alcance de clínica visible; no equivale a exposición dinámica demostrada.  
**Nota:** la consulta está parametrizada en `getReceta`, pero parametrización SQL no sustituye autorización.

### 6.3 Creación y actualización

`ClinicaController::pacienteInsertRecetaExterno()` (`:141-163`) y `pacienteEditRecetaExterno()` (`:166-188`) exigen `authPermisos()` y método POST, pero pasan el JSON directamente a `RecetaExternoModel::insert()` o `update()` sin agregar contexto de clínica/actor ni comprobar permiso de escritura.

El insert del modelo (`RecetaExternoModel.php:118-148`) no recibe ni persiste clínica emisora, usuario emisor o owner. El update (`:150-188`) actualiza por `WHERE id = :id` (`:164`) con datos del request. El schema/semántica documental real no pudo consultarse.

**Clasificación:** `CODE_CONFIRMED` para falta de autorización funcional y de owner visible en el call-site; impacto dinámico `NOT_VERIFIED`.  
**Eliminación:** `NOT_FOUND` en las rutas revisadas; no se concluye que sea imposible en otro archivo o despliegue.

### 6.4 Contrato de negocio

La receta externa se define como emitida por un médico de una clínica a una persona sin expediente. La ausencia de expediente no la vuelve global. El código auditado no presenta una relación que permita distinguir esa clínica emisora, por lo que no es posible diseñar una autorización selectiva segura usando sólo el ID actual.

## 7. P1-B — PDF de recetas externas

La ruta `POST /pdf/receta-externo/{id}` está registrada en `routes.php:234` y llega a `PdfController::pdfRecetaExterno($id)` (`app/Controllers/PdfController.php:239-295`). El método carga `RecetaExternoModel` (`:241-242`), obtiene datos y crea TCPDF (`:247`), sin mostrar `AuthMiddleware`, `authPermisos()`, `assertPatientAccess()` o una autorización de propietario antes de generar la respuesta.

La evidencia distingue:

| Control | Resultado |
|---|---|
| Usuario autenticado | No visible en este método; `NOT_VERIFIED` si existe middleware global no mostrado |
| Perfil/rol | No visible |
| Clínica del usuario | No visible |
| Clínica emisora | No representada en el flujo/modelo auditado |
| Propiedad del recurso | No visible |
| Estado documental | No visible |
| Generación PDF | Sí, TCPDF tras cargar por ID |

**Clasificación:** `CODE_CONFIRMED` para ausencia de guarda local de autenticación/recursos; explotación dinámica `NOT_VERIFIED`.  
**Severidad:** P1 potencial por entrega documental clínica.

No se generó ningún PDF.

## 8. P1-C — Descarga genérica

`GET /descargar/{ruta}/{archivo}` (`routes.php:239`) llega a `DownloadController::download()` (`app/Controllers/DownloadController.php:11-43`). El flujo es:

```text
Ruta registrada
  -> AuthMiddleware('clinica')->authPermisos()                 [línea 12]
  -> realpath(public/storage)                                 [línea 14]
  -> trim(ruta) + basename(archivo)                            [líneas 15-19]
  -> realpath(candidate) y prefijo permitido                  [líneas 19-27]
  -> mime_content_type / headers / readfile                  [líneas 29-43]
```

El control de path evita salir del directorio de almacenamiento mediante una ruta no válida, pero el controlador no recibe ni resuelve paciente, receta, clínica emisora, owner histórico, permiso documental o vínculo entre el archivo y el actor. Una ruta dentro de `public/storage` puede seguir siendo ajena al usuario.

**Clasificación:** `CODE_CONFIRMED` para ausencia de autorización del recurso; exposición horizontal dinámica `NOT_VERIFIED`.  
**Severidad:** P1 potencial y relacionada con ACT-05C-02.

No se abrió ni descargó ningún archivo.

## 9. P1-D — COFEPRIS

### 9.1 Detalle y PDF por registro

El detalle `GET /cofepris/{idCofepris}` está registrado (`routes.php:143`) y el controlador obtiene el registro y ejecuta autenticación y `assertPatientAccess()` contra el paciente del modelo antes de renderizar. `PdfController::pdfCofepris()` (`:376-395`) también carga el modelo, autentica y llama `assertPatientAccess()` (`:379-382`) antes de abrir el PDF.

Para el alcance de paciente/clínica vigente, estos son controles **MITIGATED_IN_CODE**. No demuestran permiso documental separado, propiedad histórica, autorización de carpeta o fidelidad del original. El resultado dinámico sigue `DYNAMIC_VERIFICATION_BLOCKED`.

### 9.2 Carpeta de folios

`GET /cofepris/carpeta/{idCarpeta}` (`routes.php:149`) llega a `CofeprisController::carpetaCofepris()` (`:124-141`). La única guarda visible es `AuthMiddleware('clinica')->authPermisos()` (`:126-130`); el ID de carpeta se pasa a la vista. No se observa comprobación de clínica o de propietario del recurso.

**Clasificación:** `CODE_CONFIRMED` para falta de scope de recurso; impacto dinámico `NOT_VERIFIED`.

### 9.3 Edición de surtido

`POST /cofepris/edit-surtido` (`routes.php:150`) llega a `editSurtido()` (`CofeprisController.php:143-165`). Ejecuta autenticación Doctor (`:145-146`), decodifica JSON y llama `CofeprisModel::editarSurtido()` con el ID proporcionado (`:155-157`). No se observa que recupere el paciente/clínica del registro ni que ejecute `assertPatientAccess()` antes del update.

El modelo actualiza por ID. Esto es una omisión concreta en el call-site. La capacidad de materializar acceso cruzado depende del esquema y del actor real, bloqueados en esta ejecución.

**Clasificación:** `CODE_CONFIRMED` para falta de autorización de escritura por recurso; impacto dinámico `NOT_VERIFIED`.

### 9.4 Inserción de folios y búsqueda

`POST /cofepris/insert-folios` (`routes.php:141`) sólo muestra autenticación Doctor en `CofeprisController::insertFolios()` (`:77-122`) antes de aceptar archivo, folio y metadatos. El alcance clínico del folio y su destino dependen del modelo/esquema no consultado.

**Clasificación:** `STATIC_RISK`; no se afirma que todos los folios sean cruzables.

Las búsquedas de tabla COFEPRIS por paciente pasan por `BusquedasController::guardPatient()` (`:25-29`), que usa `assertPatientAccess()`. `CofeprisModel::mostrarTablaCofepris()` (`:249-284`) filtra por `id_paciente`, pero construye SQL mediante interpolación (`:252`). Esto es un riesgo SQL estático separado; no se ejecutó payload ni se afirma explotación.

## 10. Revisión limitada de recetas internas y traslados

El listado `/clinica/recetas` usa `ClinicaController::recetasIndex()` (`:21-48`), toma `id_clinica` de los claims autenticados (`:26-27`) y llama `RecetaModel::getRecetasByClinica()` (`:34-35`). El modelo relaciona receta interna con `pc_paciente` y filtra la clínica vigente. Las búsquedas internas y el detalle usan `guardPatient()`/`assertPatientAccess()`.

Esto es correcto para la política actual de clínica vigente, pero no para propiedad histórica. En una secuencia A → B → C:

- con `pc_paciente.id_clinica = C`, un doctor de C puede aparecer autorizado a recursos cuyo emisor original fue A;
- un doctor de A puede perder acceso a una receta propia si la política histórica exige conservar lectura del emisor;
- el sistema no puede distinguir lectura histórica legítima de apropiación clínica porque no conserva owner/emisor histórico en la relación observada;
- los PDFs internos siguen el paciente vigente, por lo que sus criterios no resuelven el contrato histórico.

Este hallazgo es una dependencia de arquitectura, no una autorización para sustituir la clínica actual por una heurística.

## 11. Cadena de autorización por endpoint

| Endpoint | Route | Authentication | Functional permission | Clinic scope | Resource ownership | Operation/response | Evidence |
|---|---|---|---|---|---|---|---|
| External detail | GET `/receta-externo/{id}` | `authPermisos` | No catálogo visible | No | No | Renderiza por ID | `CODE_CONFIRMED` |
| External edit view | GET `/receta-externo/editar/{id}` | `authPermisos` | No independiente | No | No | Renderiza formulario | `CODE_CONFIRMED` |
| External table | GET `/buscar/tabla-recetas-externos` | `authPermisos` | No | No; SELECT global | No | HTML global | `CODE_CONFIRMED` |
| External lookup | GET `/buscar/receta-externo/{id}` | `authPermisos` | No | No | No | HTML por ID | `CODE_CONFIRMED` |
| External insert | POST `/paciente/insert-receta-externo` | `authPermisos` | No visible | No context | No owner persisted | INSERT | `CODE_CONFIRMED` |
| External update | POST `/paciente/edit-receta-externo` | `authPermisos` | No visible | No context | `WHERE id` only | UPDATE | `CODE_CONFIRMED` |
| External PDF | POST `/pdf/receta-externo/{id}` | No local auth visible | No | No | No | TCPDF/output | `CODE_CONFIRMED` |
| Generic download | GET `/descargar/{ruta}/{archivo}` | `authPermisos` | No resource permission | No | No | `readfile` | `CODE_CONFIRMED` |
| COFEPRIS detail | GET `/cofepris/{id}` | Auth + patient assertion | Coarse Doctor | Current patient clinic | Current patient relation | Render | `MITIGATED_IN_CODE` current scope |
| COFEPRIS folder | GET `/cofepris/carpeta/{id}` | `authPermisos` | No | No visible | No | Render | `CODE_CONFIRMED` |
| COFEPRIS edit | POST `/cofepris/edit-surtido` | `authPermisos` | No | No visible | No; update by ID | UPDATE | `CODE_CONFIRMED` |
| COFEPRIS PDF | GET `/pdf/cofepris/{id}` | Auth + patient assertion | Coarse Doctor | Current patient clinic | Current patient relation | PDF | `MITIGATED_IN_CODE` current scope |
| Internal recipe table | GET `/buscar/tabla-recetas/{idPaciente}` | `authAny` + assertion | Coarse view | Current clinic | Current patient | HTML | `MITIGATED_IN_CODE` current scope |

## 12. Hallazgos confirmados por código

| ID | Archivo/método/líneas | Ruta | Hallazgo | Clasificación | Severidad |
|---|---|---|---|---|---|
| P1-EXT-001 | `ClinicaController.php:67-100`, `:104-137` | GET detalle/edición externa | Sesión Doctor sin owner/clinic assertion visible | CODE_CONFIRMED | P1 potencial |
| P1-EXT-002 | `BusquedasController.php:83-97`; `RecetaExternoModel.php:190-227` | GET listado/búsqueda externa | Guarda de sesión y consulta global/por ID sin scope clínico | CODE_CONFIRMED | P1 potencial |
| P1-EXT-003 | `ClinicaController.php:141-188`; `RecetaExternoModel.php:118-188` | POST insert/update externo | Escritura autenticada sin permiso funcional ni owner persistido | CODE_CONFIRMED | P1 potencial |
| P1-PDF-001 | `PdfController.php:239-295` | POST PDF externo | No se observa middleware ni autorización de recurso antes de TCPDF | CODE_CONFIRMED | P1 potencial |
| P1-DL-001 | `DownloadController.php:11-43` | GET descarga | Path containment sin autorización del archivo clínico | CODE_CONFIRMED | P1 potencial |
| P1-CFE-001 | `CofeprisController.php:124-165` | GET carpeta / POST surtido | Auth Doctor sin scope de carpeta/registro; update por ID | CODE_CONFIRMED | P1 potencial |

`CODE_CONFIRMED` aquí significa omisión verificable en la cadena inspeccionada. No significa que se haya accedido a datos de otra clínica.

## 13. Riesgos estáticos

| ID | Archivo | Riesgo | Estado |
|---|---|---|---|
| P1-CFE-002 | `CofeprisController.php:77-122` y modelo relacionado | El insert de folios no muestra alcance de clínica/owner; semántica real no verificable | STATIC_RISK |
| P1-ROLE-001 | `AuthMiddleware.php:16-60`, fixture y esquema no accesible | Claims de rol/vista sin catálogo de permisos revalidado | STATIC_RISK / NOT_VERIFIED |
| P2-HIST-001 | `AuthMiddleware.php:69-95`, `RecetaModel.php` | Clínica vigente usada como sustituto de owner histórico | STATIC_RISK, dependencia de arquitectura |
| P2-SQL-001 | `CofeprisModel.php:249-252` | SQL concatenado con `idPaciente`; no se ejecutó payload | STATIC_RISK |
| P2-SQL-002 | Otros call-sites previos documentados | Consultas concatenadas legacy; fuera de una auditoría SQL global | STATIC_RISK |

## 14. Limitaciones de evidencia

- La conexión a `bd_clinica` fue bloqueada por allowlist antes de PDO.
- La base sintética no se consultó en esta ejecución.
- No se probó el dispatcher real con solicitudes HTTP.
- No se verificó si existe middleware global adicional fuera de los controladores revisados.
- No se pudo comprobar el catálogo real de roles, permisos, superadmin o memberships.
- No se pudo comprobar qué filas/archivos existen en `public/storage`.
- No se ejecutaron SQL, POST, PDF, descarga, edición, eliminación ni payloads de inyección.
- No se declara exposición real de pacientes o recetas.

Todo resultado que dependa de esas comprobaciones es `DYNAMIC_VERIFICATION_BLOCKED` o `NOT_VERIFIED`.

## 15. Clasificación P0/P1/P2/P3

| Prioridad | Conteo | Contenido |
|---|---:|---|
| P0 | 0 confirmados | No se ejecutó prueba dinámica y no se observó una evidencia que permita afirmar P0 confirmado. |
| P1 | 5 grupos inventariados | Recetas externas, PDF externo, descargas, COFEPRIS y roles/superadmin. Incluye subhallazgos `P1-EXT-*`, `P1-PDF-001`, `P1-DL-001`, `P1-CFE-*`, `P1-ROLE-001`. |
| P2 | 3 | Owner histórico, SQL concatenado focal y revalidación/revocación de JWT. |
| P3 | 0 | No se asignó P3 a los riesgos de autorización revisados. |

El conteo P1 es de grupos heredados de TH-SEC-AUTH-08; los subhallazgos no deben sumarse como grupos adicionales.

## 16. Matriz de trazabilidad documental

| Hallazgo original | Estado anterior | Evidencia actual | Estado actualizado | Remediación pendiente |
|---|---|---|---|---|
| AUTH08-001 — recetas externas sin owner/scope | STATIC_RISK | Rutas registradas; guards sólo Doctor; SELECT global y update por ID | CODE_CONFIRMED en omisiones; dinámica NOT_VERIFIED | Contención fail-closed o owner persistente |
| AUTH08-002 — PDF externo | STATIC_RISK | `PdfController::pdfRecetaExterno()` genera TCPDF sin guard local visible | CODE_CONFIRMED; dinámica NOT_VERIFIED | Auth + autorización por recurso |
| AUTH08-003 — descarga genérica | STATIC_RISK | `realpath`/`basename` sí; resource authorization no | CODE_CONFIRMED; dinámica NOT_VERIFIED | Registro de documento/recurso o fail-closed |
| AUTH08-004 — COFEPRIS | STATIC_RISK | detalle/PDF con assertion; carpeta/surtido con auth sólo | CODE_CONFIRMED parcial + STATIC_RISK en insert | Unificar scope por recurso |
| AUTH08-005 — roles/superadmin | BLOCKED | DB/role catalog bloqueado; claims visibles pero sin fuente de verdad | NOT_VERIFIED / STATIC_RISK | Definir y verificar catálogo/identidad |
| TH-SEC-FIX-01 — recetas internas | PARTIAL | Filtro por clínica vigente y assertion de paciente | MITIGATED_IN_CODE para alcance actual | Owner histórico y transferencias |
| TH-SEC-FIX-01B — owner histórico | BLOCKED | Modelo actual no muestra owner/emisor histórico | ARCHITECTURE_DEPENDENCY | Nueva relación y política |
| ACT-05C-02 — documentos | OPEN | DownloadController no resource-auth; fuente histórica no sustituida | OPEN / STATIC_RISK | Fase documental separada |

## 17. Propuestas de contención temporal

Las siguientes son propuestas, no instrucciones de implementación.

### C-01 — Denegar fail-closed recetas externas sin owner verificable

- **Riesgo:** listado, detalle, edición, AJAX y PDF externos por ID sin relación comprobable.
- **Archivos potenciales:** `ClinicaController.php`, `BusquedasController.php`, `PdfController.php`, vista D14 y `RecetaExternoModel.php`.
- **Dependencias:** definición del contrato externo y, para permitir lectura selectiva, owner/issuer persistido.
- **Regresión:** médicos no podrían consultar/emitar recetas externas temporalmente.
- **Paciente:** no se modifica su historia; una persona sin expediente podría perder flujo de emisión.
- **Internas:** no deben bloquearse.
- **Externas:** quedarían temporalmente indisponibles hasta definir propiedad.
- **Pruebas:** sin sesión, doctor A/B, owner conocido/desconocido, HTML/AJAX/PDF.
- **Aprobación:** responsable clínico y seguridad.

**Clasificación:** CATEGORY-B. El bloqueo total fail-closed es técnicamente posible como contención; la autorización selectiva es `SAFE_CONTAINMENT_BLOCKED` sin esquema/owner verificable.

### C-02 — Denegar PDF externo hasta demostrar autorización

- **Riesgo:** entrega TCPDF sin guard local visible.
- **Archivos potenciales:** `PdfController.php`, ruta y formulario de origen.
- **Regresión:** impresión de recetas externas.
- **Pruebas:** 302/403 sin sesión, propio/ajeno, ID inválido, owner desconocido.
- **Aprobación:** explícita antes de bloquear la función.

**Clasificación:** CATEGORY-B; no se implementa.

### C-03 — Suspender descarga genérica clínica o exigir un registro de recurso

- **Riesgo:** archivo válido dentro de storage, pero sin propiedad clínica.
- **Archivos potenciales:** `DownloadController.php` y emisores de enlaces.
- **Regresión:** documentos legítimos de Doctor/COFEPRIS.
- **Pruebas:** archivo propio/ajeno, ruta manipulada, huérfano, MIME y traversal.
- **Aprobación:** seguridad/infraestructura; no basta con path containment.

**Clasificación:** CATEGORY-B si se bloquea; CATEGORY-C si se requiere registry documental.

### C-04 — Denegar carpeta/surtido COFEPRIS sin paciente/recurso autorizado

- **Riesgo:** ID de carpeta o registro utilizado con sólo sesión Doctor.
- **Archivos potenciales:** `CofeprisController.php`, `CofeprisModel.php`, AJAX relacionado.
- **Regresión:** operación de folios/surtido para médicos.
- **Pruebas:** propio/ajeno, GET/POST, folio y archivo.
- **Aprobación:** responsable COFEPRIS y seguridad.

**Clasificación:** CATEGORY-B; no se implementa.

## 18. Correcciones independientes y dependencias de arquitectura

### CATEGORY-A — Corrección independiente

Posibles sin cambiar el modelo histórico, sujetos a prueba:

1. Añadir autenticación explícita al PDF externo si no existe un middleware global verificable.
2. Separar explícitamente permiso de lectura y edición, si existe un catálogo autorizado; actualmente la fuente del catálogo está bloqueada.
3. Reusar `assertPatientAccess()` únicamente en recursos que ya tengan un paciente asociado comprobable; no aplicarlo a recetas externas sin inventar esa relación.
4. Parametrizar las consultas COFEPRIS focalizadas; esta auditoría no autoriza una remediación SQL.

### CATEGORY-B — Contención temporal fail-closed

- Bloquear recetas externas, PDF externo, carpeta/surtido y descargas sin vínculo demostrable.
- Mantener recetas internas y COFEPRIS ya protegidas por paciente vigente separadas.
- Informar al usuario una indisponibilidad temporal sin revelar datos.

### CATEGORY-C — Dependiente de arquitectura

- Clínica emisora y owner histórico inmutable.
- Historial de traslados A→B→C.
- Catálogo de roles, permisos y memberships.
- Identidad y MFA/fuente de superadmin.
- Vínculo auditable de recetas externas posteriores a un expediente.
- Registro privado de documentos originales, hash, retención y auditoría.
- Reconciliación append-only.

### CATEGORY-D — Requiere evidencia adicional

- Si existe middleware global que cubra PDF externo.
- Si el despliegue registra ownership fuera de los modelos examinados.
- Qué directorios reales están expuestos bajo `public/storage`.
- Si las rutas de folios son globales por diseño o están asociadas a una clínica en un servicio no visible.
- Qué usuarios/roles existen realmente en la base sintética autorizada.

## 19. Control de regresiones para cualquier fase posterior

Toda remediación deberá demostrar que conserva, cuando corresponda:

- Consulta de recetas internas propias.
- Emisión autorizada.
- Consulta histórica legítima de sólo lectura.
- Impresión legítima y autorizada.
- Descarga legítima y autorizada.
- Emisión de recetas externas para personas sin expediente, sólo si existe owner/emisor verificable.
- Consulta Doctor/D40 según permisos reales.
- Acceso de superadmin sólo conforme al catálogo real.

Un bloqueo global puede ser necesario como contención P1, pero requiere aprobación y debe quedar separado de una solución permanente.

## 20. Plan de pruebas futuras

No se ejecuta en esta fase. Requiere una base sintética allowlisted y fixtures descartables.

| Escenario | Precondición/fixture | Operación | Resultado esperado | Riesgo |
|---|---|---|---|---|
| Sin sesión | Cookie ausente | GET externo/PDF/descarga/COFEPRIS | 302/401/403 sin datos | Auth |
| Doctor A propio | Clínica A, recurso A | GET/AJAX/PDF/edición autorizada | Sólo respuesta A | Scope |
| Doctor B ajeno | Clínica B, recurso A | Mismas operaciones | 403/404 sin HTML clínico | Cross-clinic |
| Recurso sin owner | Registro sintético sin owner | GET/PDF/edición | Fail-closed | Owner desconocido |
| Receta externa sin expediente | Emisor A y registro externo A | Emitir/leer según permiso | Propiedad A auditable | Externas |
| Receta vinculada | Vínculo posterior validado | Leer destino/origen | Lectura definida, no transferencia | AUTH-01/02 |
| Traslado A→B→C | Historial de traslados | Consultar receta por actor | Reglas histórica explícitas | Owner histórico |
| PDF propio/ajeno | Documento original privado | POST/GET PDF | Sólo autorizado | Documentos |
| Descarga manipulada | Archivo propio/ajeno, ruta alterada | GET download | Propio permitido, ajeno/huérfano denegado | Download |
| COFEPRIS propio/ajeno | Registros A/B | Detail/edit/PDF/folder | Scope por paciente/recurso | COFEPRIS |
| Superadmin | Identidad y permiso sintéticos verificables | Reconciliar contradicción | Sólo superadmin, auditoría | AUTH-08 |
| SQL focal | Inputs sintéticos no destructivos | Revisar respuestas/errores | Parametrizado; no payload real | SQL |

Cada prueba futura debe registrar precondiciones, fixture, request, status HTTP, HTML/JSON entregado, auditoría generada, persistencia esperada y limpieza sintética. No usar pacientes reales ni ejecutar SQL contra `bd_clinica`.

## 21. Revisión de rutas y SQL focal

Consultas y patrones relevantes:

- `RecetaExternoModel::getReceta()` usa parámetro `:id`, pero no tiene scope de autorización.
- `RecetaExternoModel::mostrarTablaRecetas()` hace consulta global sin clínica.
- `RecetaExternoModel::update()` usa `WHERE id = :id`, sin owner/scope.
- `CofeprisModel::mostrarTablaCofepris()` interpola `idPaciente` en SQL (`CofeprisModel.php:252`); riesgo estático, no explotación demostrada.
- `CofeprisModel::getCofepris()` usa parámetro `:id`, pero la autorización está fuera del modelo y no aplica a todos los call-sites.
- `CofeprisModel::editarSurtido()` actualiza por ID; su autorización de recurso no aparece en el controlador.
- `DownloadController` no ejecuta SQL, pero no tiene un registro de recurso que enlace archivo con actor/clínica.

No se ejecutó payload, no se extendió la revisión a una auditoría SQL global y no se modificó ningún query.

## 22. Decisiones requeridas

1. Autorizar o no una contención fail-closed temporal de recetas externas y PDF externo.
2. Confirmar el catálogo técnico de roles y la identidad del superadministrador.
3. Aprobar el modelo de owner histórico y transferencias antes de cualquier autorización cross-clinic.
4. Definir si la descarga genérica debe quedar bloqueada hasta contar con resource registry.
5. Definir el alcance de COFEPRIS: clínica, paciente, carpeta y folio.
6. Proporcionar una base sintética allowlisted y procedimiento de conexión para pruebas dinámicas.
7. Autorizar una fase separada para ACT-05C-02 y almacenamiento documental.

## 23. Recomendación de siguiente fase

No iniciar TH-SEC-IMPL-01. Primero resolver la evidencia bloqueada y decidir las contenciones. Si no existe owner verificable, mantener fail-closed las superficies externas/documentales y preservar el acceso interno ya respaldado por paciente/clínica vigente. Después de la decisión funcional, ejecutar una fase de pruebas sintéticas con matriz A/B/C y comprobación de HTML, AJAX, HTTP, PDF y descarga, sin usar datos reales.

## 24. Estado formal

```text
TH_SEC_P1_TRIAGE_01:

STATUS: COMPLETE
CURRENT_BRANCH: Silvino
CURRENT_HEAD: 1b08d9b4c41080c176fbe4588212863f3a1ac18c
WORKTREE_PRESERVED: YES; pre-existing local changes and untracked files preserved

ENVIRONMENT_GUARD: FAIL-CLOSED; active configuration resolves bd_clinica and was not opened
REAL_DATABASE_ACCESSED: NO
SYNTHETIC_DATABASE_ACCESSED: NO — DYNAMIC_VERIFICATION_BLOCKED
HTTP_WRITE_REQUESTS: NONE

P1_FINDINGS_INVENTORIED: 5 groups from TH-SEC-AUTH-08; subfindings documented
ROUTES_INSPECTED: 16 registered/relevant routes plus absence check for external DELETE
CONTROLLERS_INSPECTED: ClinicaController, BusquedasController, PdfController, DownloadController, CofeprisController, RecetaController
MODELS_INSPECTED: RecetaModel, RecetaExternoModel, CofeprisModel, ClinicaModel; Login/Auth dependencies reviewed

EXTERNAL_RECIPE_RISK: CODE_CONFIRMED omissions; dynamic impact NOT_VERIFIED
EXTERNAL_PDF_RISK: CODE_CONFIRMED missing local auth/resource guard; dynamic impact NOT_VERIFIED
GENERIC_DOWNLOAD_RISK: CODE_CONFIRMED path-only/resource authorization gap; dynamic impact NOT_VERIFIED
COFEPRIS_RISK: CODE_CONFIRMED for folder/surtido scope gaps; detail/PDF current-patient guard MITIGATED_IN_CODE; insert STATIC_RISK
INTERNAL_RECIPE_TRANSFER_RISK: STATIC_RISK / CATEGORY-C; current-clinic control does not prove historical ownership

CODE_CONFIRMED_FINDINGS: P1-EXT-001..003, P1-PDF-001, P1-DL-001, P1-CFE-001
STATIC_RISKS: P1-CFE-002, P1-ROLE-001, P2-HIST-001, P2-SQL-001..002
DYNAMIC_CONFIRMED_FINDINGS: NONE
NOT_VERIFIED_FINDINGS: dynamic cross-clinic exposure, role catalog, superadmin, middleware global coverage, storage ownership
MITIGATED_IN_CODE: internal recipes and current-patient COFEPRIS detail/PDF only

P0_COUNT: 0 confirmed
P1_COUNT: 5 inventoried groups
P2_COUNT: 3 prioritized findings
P3_COUNT: 0

CATEGORY_A_INDEPENDENT_FIXES: explicit auth gate for external PDF; focused permission/parameterization where contracts already exist
CATEGORY_B_TEMPORARY_CONTAINMENTS: fail-closed external/PDF/folder/surtido/download surfaces pending approval
CATEGORY_C_ARCHITECTURE_DEPENDENCIES: owner history, transfer, roles/memberships, superadmin, external ownership, document registry
CATEGORY_D_MORE_EVIDENCE_REQUIRED: live schema, global middleware, storage mapping, role catalog, deployment reachability

URGENT_CONTAINMENT_RECOMMENDED: YES — review fail-closed containment for P1 external/document surfaces; not implemented
USER_DECISIONS_REQUIRED: containment approval, role/superadmin source, historical-owner model, COFEPRIS scope, synthetic DB access
FUTURE_TEST_PLAN: documented in section 20; not executed

DOCUMENTATION_CREATED: docs/audits/TH_SEC_P1_TRIAGE_01_SECURITY_FINDINGS.md
MASTER_CLOSURE_UPDATED: YES — reference/status appendix only
GLOBAL_DIFF_CHECK: PASS (exit 0; existing LF/CRLF warnings only)

APPLICATION_CODE_CHANGES: NONE
SCHEMA_CHANGES: NONE
DATA_CHANGES: NONE
PERMISSION_CHANGES: NONE
MIGRATIONS: NONE
COMMITS: NONE
DEPLOYMENTS: NONE

IMPLEMENTATION_AUTHORIZED: NO
PRODUCTION_READY: NO
```

**COMPLETE de triage significa auditoría estática documentada, no sistema seguro, no autorización para corregir y no preparación para producción.**
