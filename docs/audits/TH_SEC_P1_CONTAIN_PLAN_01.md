# TH-SEC-P1-CONTAIN-PLAN-01 — Plan de contención P1

**Proyecto:** Clínica del Dolor  
**Alcance:** Historia Clínica tradicional P01–P11 y dependencias documentales  
**Fecha:** 2026-10-12  
**Tipo:** diseño de contención y planificación de remediación  
**Prioridad:** P1 — Seguridad  
**Modo:** READ-ONLY + DOCUMENTACIÓN  
**Implementación:** NO AUTORIZADA  
**Migraciones:** NO AUTORIZADAS  
**Producción:** NO AUTORIZADA  
**Exclusión absoluta:** Entrevista Conversacional.

## A. Resumen ejecutivo

Este documento transforma los cinco grupos P1 del triage TH-SEC-P1-TRIAGE-01 en un plan futuro de contención, sin ejecutar correcciones. Se conservaron los identificadores originales, se separaron rutas que necesitan controles distintos y se distinguieron los controles actuales de las dependencias de arquitectura.

El plan cubre:

1. recetas externas;
2. PDF de recetas externas;
3. descargas genéricas;
4. carpetas, surtido y PDF COFEPRIS;
5. recetas internas cuando el traslado de paciente afecta propiedad histórica.

El modelo actual sí proporciona algunos controles por clínica vigente mediante `authPermisos()` y `assertPatientAccess()`. No proporciona evidencia suficiente de clínica emisora histórica, owner documental, catálogo de permisos, membresía multiclínica o superadministrador verificable. Por ello, toda autorización cross-clinic, conciliación o acceso a owner desconocido debe permanecer **fail-closed** hasta una decisión y una implementación separadas.

El plan queda **COMPLETE como plan documentado**, no como remediación. No se modificaron PHP, rutas, modelos, esquema, datos, `.env`, permisos ni infraestructura. No se ejecutaron SQL, HTTP de escritura, PDFs, descargas ni pruebas dinámicas.

## B. Estado inicial de riesgos P1

| Grupo original | Estado del triage | Problema operativo | Propiedad verificable actual |
|---|---|---|---|
| AUTH08-001 / P1-EXT | CODE_CONFIRMED en omisiones de alcance | Detalle, edición, listado y AJAX externo dependen sólo de sesión Doctor o consulta global | NO / UNKNOWN |
| AUTH08-002 / P1-PDF | CODE_CONFIRMED | PDF externo genera TCPDF sin guard local de autenticación/owner visible | NO / UNKNOWN |
| AUTH08-003 / P1-DL | CODE_CONFIRMED | Descarga valida path, pero no recurso clínico | UNKNOWN |
| AUTH08-004 / P1-CFE | CODE_CONFIRMED parcial + STATIC_RISK | Carpeta/surtido sin scope visible; detalle/PDF sí tienen guard de paciente vigente | PARCIAL |
| AUTH08-005 / P1-ROLE | NOT_VERIFIED / STATIC_RISK | No se verificó catálogo de roles, permisos ni identidad superadmin | UNKNOWN |

No se confirmó explotación dinámica. La base sintética no se pudo abrir porque la configuración activa resuelve `bd_clinica` y la guarda fail-closed bloquea la conexión.

## C. Documentación examinada

| Documento | Estado | Función en este plan |
|---|---|---|
| `docs/audits/TH_SEC_P1_TRIAGE_01_SECURITY_FINDINGS.md` | FOUND | Fuente primaria de hallazgos y rutas |
| `docs/audits/TH_SEC_AUTH_08_ROLE_AND_MULTICLINIC_AUTH_AUDIT.md` | FOUND | Roles, superadmin, owner y matrices |
| `docs/audits/TH_SEC_AUDIT_01_TRACEABILITY.md` | FOUND | Trazabilidad y límites históricos |
| `docs/audits/TH_SEC_FIX_01_RECIPE_TENANT_AUTHORIZATION.md` | FOUND | Controles actuales de recetas internas |
| `docs/audits/TH_SEC_FIX_01B_HISTORICAL_RECIPE_OWNERSHIP.md` | FOUND | Dependencia de owner histórico |
| `docs/audits/TRADITIONAL_HISTORY_CLOSURE_MASTER.md` | FOUND | Estado consolidado |
| `docs/architecture/TH_SEC_ARCH_01_HISTORICAL_RECIPE_OWNERSHIP_V1.1.md` | FOUND | Diseño futuro, no implementación |
| `docs/architecture/TH_SEC_ARCH_01_DATA_MODEL_PROPOSAL_V1.1.md` | FOUND | Modelo futuro, no implementación |

Los documentos de arquitectura V1.0/V1.1 no se modifican.

## D. Preflight

| Control | Resultado |
|---|---|
| Directorio | `C:\xampp\htdocs\clinica-dolor` |
| Rama | `Silvino` |
| HEAD | `1b08d9b4c41080c176fbe4588212863f3a1ac18c` |
| HEAD esperado | Coincide |
| Worktree | Preservado; había cambios locales y archivos no rastreados |
| Archivos modificados | Registrados por `git status --short`; no se revirtieron |
| Archivos no rastreados | Registrados por `git status --short`; no se eliminaron |
| `bd_clinica` | No accedida |
| Sintética allowlisted | No accedida; `DYNAMIC_VERIFICATION_BLOCKED` |
| `.env` | No modificado |
| HTTP/SQL de escritura | Ninguno |

No se utilizaron `git clean`, `git reset --hard`, staging, commit, push o despliegue. Las advertencias LF/CRLF históricas no se corrigieron.

## E. Matriz de hallazgos originales

| ID original | Superficie | Ruta | Método | Archivo/líneas | Estado de evidencia | Severidad |
|---|---|---|---|---|---|---|
| P1-EXT-001 / AUTH08-001 | Detalle externo | `/receta-externo/{id}` | GET | `ClinicaController.php:67-100` | CODE_CONFIRMED; dinámica bloqueada | P1 potencial |
| P1-EXT-001 / AUTH08-001 | Edición externa | `/receta-externo/editar/{id}` | GET | `ClinicaController.php:104-137` | CODE_CONFIRMED; dinámica bloqueada | P1 potencial |
| P1-EXT-002 / AUTH08-001 | Listado externo | `/buscar/tabla-recetas-externos` | GET | `BusquedasController.php:83-89`; `RecetaExternoModel.php:190-219` | CODE_CONFIRMED | P1 potencial |
| P1-EXT-002 / AUTH08-001 | Consulta externa | `/buscar/receta-externo/{id}` | GET | `BusquedasController.php:91-97`; modelo `:222-227` | CODE_CONFIRMED | P1 potencial |
| P1-EXT-003 / AUTH08-001 | Alta externa | `/paciente/insert-receta-externo` | POST | `ClinicaController.php:141-163`; modelo `:118-148` | CODE_CONFIRMED | P1 potencial |
| P1-EXT-003 / AUTH08-001 | Edición externa | `/paciente/edit-receta-externo` | POST | `ClinicaController.php:166-188`; modelo `:150-188` | CODE_CONFIRMED | P1 potencial |
| P1-PDF-001 / AUTH08-002 | PDF externo | `/pdf/receta-externo/{id}` | POST | `PdfController.php:239-295` | CODE_CONFIRMED; middleware global no verificado | P1 potencial |
| P1-DL-001 / AUTH08-003 | Descarga genérica | `/descargar/{ruta}/{archivo}` | GET | `DownloadController.php:11-43` | CODE_CONFIRMED; impacto dinámico bloqueado | P1 potencial |
| P1-CFE-001 / AUTH08-004 | Carpeta COFEPRIS | `/cofepris/carpeta/{idCarpeta}` | GET | `CofeprisController.php:124-141` | CODE_CONFIRMED | P1 potencial |
| P1-CFE-001 / AUTH08-004 | Surtido COFEPRIS | `/cofepris/edit-surtido` | POST | `CofeprisController.php:143-165` | CODE_CONFIRMED | P1 potencial |
| P1-CFE-002 / AUTH08-004 | Alta de folios | `/cofepris/insert-folios` | POST | `CofeprisController.php:77-122` | STATIC_RISK; esquema bloqueado | P1 potencial |
| P1-CFE-003 | Detalle COFEPRIS | `/cofepris/{idCofepris}` | GET | `CofeprisController.php`; assertion vigente | MITIGATED_IN_CODE para clínica actual | P1 residual histórico |
| P1-CFE-003 | PDF COFEPRIS | `/pdf/cofepris/{id}` | GET | `PdfController.php:376-395` | MITIGATED_IN_CODE para clínica actual | P1 residual histórico |
| AUTH08-005 | Roles/superadmin | N/A | N/A | `AuthMiddleware.php:16-60`, fixture | BLOCKED_BY_EVIDENCE | P1 de gobernanza |
| P2-HIST-001 | Traslados/owner histórico | rutas internas relacionadas | GET/POST | `AuthMiddleware.php:69-95`, `RecetaModel.php` | STATIC_RISK / CATEGORY-C | P2 arquitectónico |

La ausencia de una ruta DELETE externa se registra como `NOT_FOUND` en la revisión de `routes.php`; no se interpreta como garantía para otros despliegues.

## F. Trazabilidad de rutas

### F.1 Recetas externas

```text
GET /receta-externo/{id}
  -> routes.php:131
  -> ClinicaController::recetaExternoDetalle():67-100
  -> authPermisos():68-74
  -> RecetaExternoModel::receta($id)
  -> vista externa
```

La cadena tiene autenticación, pero no un permiso funcional ni owner/clinic assertion visible.

```text
GET /buscar/tabla-recetas-externos
  -> routes.php:196
  -> BusquedasController::tableRecetasExternos():83-89
  -> guardClinic():32-35
  -> RecetaExternoModel::mostrarTablaRecetas():190-219
  -> SELECT global
```

La consulta global hace que una contención del directorio D14 no proteja este endpoint independiente.

```text
POST /paciente/edit-receta-externo
  -> routes.php:134
  -> ClinicaController::pacienteEditRecetaExterno():166-188
  -> authPermisos()
  -> RecetaExternoModel::update():150-188
  -> UPDATE ... WHERE id=:id
```

El request proporciona el ID y el controlador no agrega autorización funcional o de propiedad.

### F.2 PDF externo

```text
POST /pdf/receta-externo/{id}
  -> routes.php:234
  -> PdfController::pdfRecetaExterno():239-295
  -> RecetaExternoModel::receta($id)
  -> TCPDF
  -> respuesta PDF
```

No se observa middleware local antes de cargar/generar; la existencia de middleware global queda `BLOCKED_BY_EVIDENCE`.

### F.3 Descarga

```text
GET /descargar/{ruta}/{archivo}
  -> routes.php:239
  -> DownloadController::download():11-43
  -> authPermisos()
  -> realpath/basename/prefijo storage
  -> readfile()
```

La cadena controla ubicación física, no propiedad clínica.

### F.4 COFEPRIS

```text
GET /cofepris/carpeta/{idCarpeta}
  -> routes.php:149
  -> CofeprisController::carpetaCofepris():124-141
  -> authPermisos()
  -> vista de carpeta
```

```text
POST /cofepris/edit-surtido
  -> routes.php:150
  -> CofeprisController::editSurtido():143-165
  -> authPermisos()
  -> CofeprisModel::editarSurtido(data)
  -> UPDATE por ID
```

El detalle y PDF por paciente tienen una rama distinta con `assertPatientAccess()`; no se debe tratar COFEPRIS como una sola operación.

## G. Contención de recetas externas

### Estado actual

Las rutas externas sólo demuestran sesión Doctor en detalle, edición, AJAX y escritura. `RecetaExternoModel::mostrarTablaRecetas()` lista globalmente; `getReceta()` busca por ID; `insert()` no persiste clínica emisora/doctor; `update()` modifica por ID. La relación `receta_medica_externos → paciente/clínica` no es verificable en el entorno actual.

### Contención propuesta

1. Mantener bloqueado el catálogo global de D14.
2. Evaluar bloqueo fail-closed de listado, detalle, edición, actualización y PDF externo mientras no exista owner verificable.
3. Mantener operativas las recetas internas ya restringidas por clínica vigente.
4. No permitir que un `id` conocido o un `id_clinica` enviado por request sustituya la autorización.
5. Si se habilita lectura selectiva en el futuro, resolver actor, clínica emisora, owner y operación antes de renderizar.

### Funcionalidades que podrían conservarse

Sólo podrían conservarse sin nueva relación si una fase autorizada demuestra una fuente de owner existente fuera de los archivos revisados. La ausencia de expediente no debe provocar alta automática ni hacer global la receta.

### Escenarios futuros

- A consulta su receta externa propia: permitir sólo con owner A verificable.
- B consulta receta A: 403/404 sin datos y sin confirmar existencia.
- B edita receta A: denegar aunque conozca el ID.
- Usuario sin sesión: 302/401 sin contenido.
- Registro legacy sin owner: fail-closed.
- Receta vinculada después: lectura según AUTH-01/02; vínculo no transfiere propiedad.

**Clasificación:** CATEGORY-B para bloqueo temporal; CATEGORY-C para owner/emisor persistente; CATEGORY-D si el esquema real difiere.

## H. Contención de PDF externos

Cadena futura obligatoria:

```text
Solicitud PDF
  -> autenticación
  -> permiso funcional de impresión
  -> resolución de receta
  -> clínica emisora verificable
  -> autorización del recurso
  -> estado documental/original
  -> generación o recuperación autorizada
  -> respuesta
```

Condición actual: `RESOURCE_OWNER_NOT_VERIFIABLE`. La medida temporal más segura sería denegar el endpoint hasta contar con evidencia de owner; esto afecta impresión externa pero evita entregar un PDF por ID. La solución definitiva requiere contrato de owner, documento privado y auditoría. No se recomienda otorgar permiso general de Doctor ni autorizar por conocimiento del ID.

## I. Contención de descargas genéricas

### Alternativa 1 — categorías no clínicas

Restringir la ruta genérica a categorías cuya asociación y sensibilidad estén demostradas. Ventaja: menor riesgo de exposición. Dependencia: inventario completo de categorías y emisores. Regresión: enlaces de documentos clínicos podrían dejar de funcionar.

### Alternativa 2 — endpoints por recurso

Sustituir conceptualmente descargas clínicas por endpoints que resuelvan paciente/receta/documento y autoricen la operación. Ventaja: autorización verificable. Dependencia: resource registry y actualización de emisores, incluidos P11/Doctor/D40. Regresión: requiere migrar enlaces.

### Alternativa 3 — bloqueo temporal clínico

Bloquear descargas clínicas sin owner. Ventaja: fail-closed inmediato. Regresión: impresión/descarga legítima indisponible hasta la remediación.

La protección `realpath()`/`basename()` debe conservarse en cualquier alternativa, pero no se considera suficiente. No se mueven archivos ni se modifican permisos de storage en esta fase.

## J. Contención de COFEPRIS

| Operación | Control actual | Contención propuesta | Dependencia |
|---|---|---|---|
| Tabla por paciente | `guardPatient` + `assertPatientAccess` | Conservar; parametrizar en lote separado | Paciente vigente verificable |
| Detalle por ID | Auth + assertion del paciente del modelo | Conservar; separar permiso de lectura si existe catálogo | Permiso funcional |
| PDF por ID | Auth + assertion actual | Conservar para clínica vigente; bloquear si paciente/owner no se resuelve | Documento/owner histórico |
| Carpeta por ID | Auth Doctor visible | Denegar sin scope de carpeta/clinica verificable | Relación carpeta-clínica |
| Surtido por ID | Auth Doctor; update por ID | Denegar o exigir resolución de paciente/recurso antes de update | Propiedad del registro |
| Alta de folios | Auth Doctor; owner no visible | Permitir sólo con contexto/folio autorizado o contener | Esquema y contrato de folios |
| Descarga relacionada | Ruta genérica | Aplicar resource authorization; si falta, bloquear | Registro documental |

No se debe aplicar un bloqueo indiscriminado a la consulta por paciente si la relación actual es comprobable, ni asumir que el PDF queda protegido sólo porque el detalle lo esté.

## K. Dependencias de recetas internas y traslados

Las recetas internas se filtran por `pc_paciente.id_clinica`, que representa la clínica activa. En A → B → C:

- B/C podrían consultar recetas emitidas por A si sólo se consulta la clínica actual.
- A podría perder el acceso histórico que la política le conserva.
- No puede distinguirse una consulta histórica legítima de una apropiación posterior sin owner/emisor persistente.
- Una corrección independiente no debe reemplazar `id_clinica` por otra inferida.

La solución pertenece a CATEGORY-C: owner inmutable, historial de traslados, política de lectura histórica y auditoría. La contención temporal puede bloquear lecturas ambiguas, pero debe aprobarse por su impacto clínico.

## L. Restricciones de roles y superadministrador

El código emite `rol` en JWT, pero no demuestra catálogo de permisos ni revalidación de privilegios por operación. No se verificó un superadministrador. Las políticas AUTH-01..AUTH-08 dicen que ciertas reconciliaciones son exclusivas de superadmin, pero esa regla no puede implementarse con un ID/rol inventado.

Toda futura contención debe:

- separar regla aprobada de fuente técnica verificable;
- denegar si el permiso administrativo no puede comprobarse;
- no convertir superadmin en emisor/anulador clínico automático;
- auditar aprobaciones sin guardar contenido clínico innecesario.

Dependencia: CATEGORY-D ahora; CATEGORY-C para implementación real.

## M. Alternativas de autorización centralizada

| Alternativa | Seguridad | Regresión | Compatibilidad | Recomendación |
|---|---|---|---|---|
| A. Guardas locales | Rápida, pero propensa a omisiones entre listado/detalle/PDF | Media | Alta | Sólo para gates simples y temporales |
| B. Servicio compartido por recurso/operación | Consistente y testeable | Requiere integración cuidadosa | Compatible si se mantiene PHP actual | Recomendado como destino |
| C. Híbrida | Servicio compartido + guardas HTTP locales | Menor que A si hay contrato único | Mejor transición | Recomendado para lotes P1 |

La solución mínima segura es un modelo híbrido: middleware local para sesión/método, servicio compartido para actor-recurso-operación-owner-clínica y fail-closed cuando falte owner. No se crea ni implementa el servicio ahora.

## N. Política fail-closed

| Situación | Respuesta recomendada | Información a revelar |
|---|---|---|
| Sin sesión | 302 a login o 401 en AJAX | Ningún dato clínico |
| Sesión inválida/expirada | Limpiar sesión y 302/401 | Mensaje genérico |
| Sin permiso funcional | 403 | No confirmar existencia |
| Recurso inexistente | 404 genérico | No distinguirlo de recurso ajeno cuando corresponda |
| Owner no identificable | 403/404 genérico | No owner, rutas ni nombres internos |
| Clínica no corresponde | 403/404 genérico | No confirmar clínica/registro |
| Legacy ambiguo | 403/404 hasta reconciliación autorizada | Sin contenido |
| Documento original ausente | 409/404 según contrato, sin llamarlo original | No generar reconstrucción como original |
| Error de almacenamiento | 500 controlado, sin rutas internas | Log técnico seguro |

Los logs deben registrar actor, operación, recurso abstracto, resultado y correlación, sin copiar diagnósticos, documentos ni datos clínicos innecesarios.

## O. Riesgos de regresión

Una contención puede afectar emisión, consulta, impresión, descarga, surtido, historial y Doctor/D40. El riesgo no se acepta simplemente porque el bloqueo sea seguro.

Debe conservarse, una vez autorizado el lote:

- emisión legítima de recetas internas;
- consulta de recetas propias;
- acceso histórico explícitamente autorizado;
- impresión y descarga legítimas;
- receta externa para persona sin expediente sólo con owner emisora verificable;
- operaciones de surtido permitidas;
- acceso del Portal Paciente que no dependa de un bloqueo Doctor.

No se modifica el Portal Paciente ni la Entrevista Conversacional en este plan.

## P. Impacto operativo

| Actor/flujo | Contención externa/PDF | Contención descarga | Contención COFEPRIS | Decisión requerida |
|---|---|---|---|---|
| Médico emisor | Podría no ver/editar receta externa sin owner | Podría no descargar | Podría no surtir si ID no se resuelve | Aceptar bloqueo temporal o proveer owner |
| Médico consultor | No consulta cross-clinic sin prueba | Sólo archivos asociados | Lectura actual puede continuar | Definir histórico |
| Paciente externo | Emisión eventual puede quedar temporalmente detenida | No expuesto | No aplica normalmente | Priorizar seguridad o flujo alterno |
| Administrador clínica | No obtiene acceso implícito a otra clínica | No descarga por ruta | No edita ajeno | Definir permisos reales |
| Personal surtido | Surtido por ID puede bloquearse | Documentos asociados podrían bloquearse | Se requiere relación del recurso | Validar operación clínica |
| Historial | Lectura ambigua puede bloquearse | PDFs históricos pueden bloquearse | PDF actual protegido parcialmente | Aprobar owner histórico |
| Doctor/D40 | No cambiar sin análisis de dependencias | Revisar enlaces | Revisar documentos asociados | Prueba de regresión |
| Portal Paciente | Sin cambio directo | Sin cambio directo | Sin cambio directo | Confirmar no dependencia compartida |

## Q. Matriz obligatoria de contención

| ID | Hallazgo/ruta | Categoría | Control actual / faltante | Propiedad | Sin esquema | Contención inmediata | Impacto | Residual | Autorización |
|---|---|---|---|---|---|---|---|---|---|
| C-EXT-01 | GET detalle externo | B/C | Auth sí; owner/permiso no | UNKNOWN | BLOCKED | Denegar hasta owner | Detalle externo indisponible | Owner histórico/externo | Seguridad + clínico |
| C-EXT-02 | GET edición externa | B/C | Auth sí; write permission/owner no | UNKNOWN | BLOCKED | Denegar edición | No editar externas | Reconciliación | Seguridad + clínico |
| C-EXT-03 | GET tabla externa | B | Auth sí; SELECT global | NO | YES para bloqueo | Mantener bloqueada | No catálogo externo | Owner no resuelto | Seguridad |
| C-EXT-04 | GET AJAX externo | B/C | Auth sí; resource scope no | UNKNOWN | BLOCKED | Denegar respuesta | UI de búsqueda limitada | Owner | Seguridad |
| C-EXT-05 | POST insert externo | C/D | Auth sí; owner/issuer no persiste | UNKNOWN | BLOCKED | Suspender o require issuer | Emisión externa detenida | Modelo externo | Clínico + arquitectura |
| C-EXT-06 | POST update externo | B/C | Auth sí; `WHERE id` | NO | YES para bloqueo | Denegar update | Edición detenida | Owner y permiso | Seguridad |
| C-PDF-01 | POST PDF externo | A/B/C | No guard local visible | UNKNOWN | YES para auth gate; owner BLOCKED | Denegar PDF | Impresión externa detenida | Documento/owner | Seguridad |
| C-DL-01 | GET descarga genérica | B/C | Path containment; resource auth no | UNKNOWN | BLOCKED selectivo | Bloquear clínicas o usar registry | Descargas detenidas | Registry | Seguridad + infraestructura |
| C-CFE-01 | GET carpeta | B/D | Auth; scope carpeta no | UNKNOWN | BLOCKED | Denegar carpeta ajena/ambigua | Folios no visibles | Relación carpeta | COFEPRIS |
| C-CFE-02 | POST surtido | B/C | Auth; update por ID | UNKNOWN | YES para deny | Denegar ID sin paciente | Surtido manual limitado | Recurso/owner | COFEPRIS |
| C-CFE-03 | POST insert folios | D | Auth; contexto no confirmado | UNKNOWN | BLOCKED | No cambiar sin esquema | Alta de folios limitada | Modelo real | COFEPRIS |
| C-CFE-04 | GET/PDF detalle | A/C | Assertion paciente actual | YES actual / NO histórico | NO para histórico | Mantener actual; bloquear ambiguos | Histórico puede bloquearse | Owner histórico | Arquitectura |
| C-INT-01 | Recetas internas tras A→B→C | C | Clínica vigente; owner histórico no | NO | NO | No alterar; decidir política | Lectura histórica ambigua | Owner/traslados | Arquitectura |
| C-ROLE-01 | Superadmin/roles | D/C | Claims sin catálogo verificable | UNKNOWN | NO | No habilitar reconciliación | Operación admin bloqueada | Fuente de identidad | Propietario |

`SAFE_CONTAINMENT_BLOCKED` aplica a las variantes selectivas que requieren identificar owner/recurso. Un bloqueo total fail-closed es una opción de seguridad, pero requiere aprobación por impacto operativo.

## R. Correcciones CATEGORY-A

Posibles sin tablas nuevas, sólo cuando el contrato actual lo permita:

1. Añadir un gate de autenticación explícito al PDF externo si no existe middleware global.
2. Rechazar métodos HTTP o payloads inválidos antes de tocar modelos.
3. Aplicar el permiso funcional ya existente, si se identifica un catálogo real verificable.
4. Reutilizar `assertPatientAccess()` únicamente en recursos con paciente relacionado comprobable.
5. Parametrizar SQL focalizado de COFEPRIS en una fase separada.

No se debe llamar CATEGORY-A a una corrección que invente owner externo o clínica emisora.

## S. Contenciones CATEGORY-B

1. Mantener el catálogo externo D14 bloqueado.
2. Denegar detalle/edición/AJAX/PDF externo cuando owner no sea verificable.
3. Denegar carpeta y surtido COFEPRIS sin recurso/paciente autorizado.
4. Bloquear descarga clínica genérica cuando no exista registry.
5. Mantener mensajes genéricos y logs de auditoría sin datos clínicos.

Estas medidas no están implementadas. Cada una requiere revisión de regresión y aprobación del propietario.

## T. Dependencias CATEGORY-C

- clínica emisora inmutable de receta;
- historial de traslados y clínica activa;
- owner de documento y relación recurso-archivo;
- catálogo de roles, permisos y memberships;
- identidad técnica de superadmin;
- vínculo auditable de receta externa a paciente;
- conciliación append-only;
- original, hash, cifrado, retención y recuperación documental.

No se consideran listas para implementar.

## U. Limitaciones CATEGORY-D

Requieren evidencia antes de decidir:

- tabla real de recetas externas y columnas de owner;
- existencia de middleware global para PDF;
- catálogo real de roles/permisos;
- mapping de `public/storage` a recursos clínicos;
- semántica de carpetas/folios COFEPRIS;
- dependencias de Doctor/D40 y P11;
- despliegue Apache/Nginx/Plesk y reglas de acceso privado.

## V. Plan por lotes futuro

### LOTE P1-A — Gate y permiso funcional

- **Cubre:** P1-PDF-001 y partes comunes de rutas.
- **Archivos:** middleware/controladores definidos después de evidencias.
- **Control:** autenticación, método, permiso específico, respuesta genérica.
- **Dependencias:** catálogo de roles/permisos.
- **Pruebas:** sin sesión, rol inválido, permiso válido, recurso ajeno.
- **Reversión:** restaurar sólo el gate mediante commit separado autorizado.
- **Aceptación:** no bypass por ID; sin regresión interna.

### LOTE P1-B — Recetas externas

- **Cubre:** P1-EXT-001..003.
- **Control:** bloquear sin owner; separar lectura/edición; persistir issuer sólo con arquitectura aprobada.
- **Dependencias:** owner externo y permisos.
- **Pruebas:** A/B, legacy, vínculo posterior, HTML/AJAX/PDF.
- **Reversión:** feature gate/configuración de contención aprobada, nunca restauración silenciosa.

### LOTE P1-C — PDF externo

- **Cubre:** P1-PDF-001.
- **Control:** authorize(resource, print) antes de TCPDF o recuperar original privado.
- **Dependencias:** owner/document registry.
- **Pruebas:** propio/ajeno/sin owner/original ausente.
- **Aceptación:** ningún PDF para recurso ajeno; original no se confunde con reconstrucción.

### LOTE P1-D — Descargas

- **Cubre:** P1-DL-001 y ACT-05C-02 relacionado.
- **Control:** categorías no clínicas o endpoint vinculado a recurso.
- **Dependencias:** inventario de storage y registry.
- **Pruebas:** traversal, archivo propio/ajeno/huérfano.
- **Reversión:** mantener path containment y revertir sólo el mapping de aplicación, no abrir storage indiscriminadamente.

### LOTE P1-E — COFEPRIS

- **Cubre:** P1-CFE-001..003.
- **Control:** scope por paciente/recurso para carpeta, surtido, PDF y descarga.
- **Dependencias:** relación folio-carpeta-clínica-paciente.
- **Pruebas:** propio/ajeno, GET/POST/PDF/archivo.
- **Aceptación:** lectura y escritura coherentes, sin ampliar permisos.

### LOTE P1-F — Arquitectura histórica

- **Cubre:** AUTH-02, AUTH-04, AUTH-06, AUTH-08 y P2-HIST-001.
- **Control:** owner, traslados, reconciliación y auditoría.
- **Dependencias:** TH-SEC-ARCH-01 y modelo aprobado.
- **Aceptación:** no se implementa hasta aprobación explícita de esquema y política.

Orden recomendado: P1-A → P1-B/P1-C/P1-D/P1-E según decisiones de bloqueo → P1-F. No iniciar ningún lote en esta fase.

## W. Matriz de pruebas futuras

Todas las pruebas siguientes tienen estado `NOT_EXECUTED`.

| ID | Precondición/fixture | Operación | Resultado esperado | Hallazgo |
|---|---|---|---|---|
| T-01 | Sin cookie | GET detalle externo | 302/401 sin datos | P1-EXT |
| T-02 | Doctor A + receta externa A con owner | GET/listado/lookup | Sólo A | P1-EXT |
| T-03 | Doctor B + ID de receta A | GET/listado/lookup | 403/404 genérico | P1-EXT |
| T-04 | Usuario sin permiso de edición | POST update A | 403/422 sin update | P1-EXT |
| T-05 | Legacy externo sin owner | GET/PDF | Fail-closed | P1-EXT/PDF |
| T-06 | PDF propio autorizado | POST PDF | Documento permitido, auditado | P1-PDF |
| T-07 | PDF ajeno/ID conocido | POST PDF | Denegado sin confirmar existencia | P1-PDF |
| T-08 | Archivo propio y registry válido | GET download | Permitido | P1-DL |
| T-09 | Archivo ajeno/huérfano/ruta manipulada | GET download | Denegado; sin path disclosure | P1-DL |
| T-10 | Carpeta COFEPRIS A | GET carpeta | A permitido, B denegado | P1-CFE |
| T-11 | Surtido propio/ajeno | POST edit | Propio según permiso; ajeno 403 | P1-CFE |
| T-12 | PDF COFEPRIS propio/ajeno | GET PDF | Sólo recurso autorizado | P1-CFE |
| T-13 | A→B→C + owner histórico | Consultas/lectura | Política histórica exacta | P2-HIST |
| T-14 | Superadmin sintético verificable | Reconciliar conflicto | Sólo superadmin + auditoría | AUTH-08 |
| T-15 | Emisión interna propia | POST receta | Continúa, no amplía alcance | Regresión |
| T-16 | P11/Doctor-D40 documento relacionado | GET/PDF/download | Sin regresión | Regresión |

Cada prueba deberá capturar precondición, request, status, HTML/JSON, auditoría, estado de base y limpieza sintética. No usar datos reales.

## X. Estrategia de reversión futura

1. Cada lote debe tener diff y aprobación independientes.
2. Antes del despliegue, crear snapshot/backup de la base sintética y verificar restauración.
3. Activar primero contenciones fail-closed, después permitir sólo rutas con pruebas positivas.
4. Si aparecen 403 legítimos, revertir sólo el lote afectado mediante la versión aprobada; no reabrir globalmente rutas externas.
5. Mantener logs de denegación y un procedimiento de recuperación de documentos.
6. Repetir pruebas negativas A/B/C después de la reversión.
7. Suspender el lote si hay exposición, escritura no autorizada, divergencia entre listado/detalle/PDF o pérdida documental.

No se propone rollback de esta fase porque no hubo implementación.

## Y. Riesgos residuales

Incluso después de CATEGORY-A y B podrían permanecer:

- ausencia de propietario histórico y traslados;
- roles y superadmin no verificables;
- owner externo no persistido;
- PDF original no conservado o reconstrucción confundible;
- descargas sin registro de recurso;
- registros legacy ambiguos;
- diferencias entre Apache, Nginx, Plesk y PHP built-in;
- consultas SQL legacy fuera del lote focal;
- dependencia de un servicio compartido que aún no existe;
- regresiones clínicas por bloqueos temporales.

Por tanto, el plan no permite declarar `PRODUCTION_READY: YES`.

## Z. Decisiones del propietario requeridas

1. Aprobar o rechazar bloqueo fail-closed de recetas externas y PDF externo.
2. Proporcionar/autorizar la fuente de roles, permisos y superadmin.
3. Aprobar owner histórico y política A→B→C.
4. Decidir si la descarga genérica clínica se sustituye por endpoints de recurso.
5. Definir scope de carpeta, folio y surtido COFEPRIS.
6. Proporcionar una base sintética allowlisted para pruebas dinámicas.
7. Autorizar por separado cualquier cambio de esquema, modelo, permisos o almacenamiento.
8. Autorizar una fase específica para ACT-05C-02.

## AA. Recomendación final

No iniciar TH-SEC-IMPL-01 ni ningún lote. Mantener el bloqueo de D14 externo ya existente, revisar la contención explícita de las rutas independientes externas/documentales y no habilitar edición, PDF o descarga cuando no exista owner verificable. Preservar las rutas internas actuales que sí demuestran alcance por paciente/clínica vigente, dejando claro que no resuelven propiedad histórica.

La siguiente fase debe comenzar por evidencia sintética allowlisted y catálogo de autorización; después, con aprobación, aplicar el lote P1-A y las contenciones B antes de cualquier arquitectura histórica. La Entrevista Conversacional permanece completamente fuera de alcance.

## AB. Estado formal

```text
TH_SEC_P1_CONTAIN_PLAN_01:

STATUS: COMPLETE

CURRENT_BRANCH: Silvino
CURRENT_HEAD: 1b08d9b4c41080c176fbe4588212863f3a1ac18c
WORKTREE_PRESERVED: YES; pre-existing local changes and untracked files preserved

TRIAGE_REPORT_REVIEWED: YES — TH_SEC_P1_TRIAGE_01_SECURITY_FINDINGS.md
P1_FINDINGS_MAPPED: YES — 5 original groups with route-level subfindings
RELEVANT_ROUTES_MAPPED: YES — registered routes and external DELETE absence check
AUTHORIZATION_CHAINS_REVIEWED: YES

EXTERNAL_RECIPE_CONTAINMENT: PROPOSED FAIL-CLOSED when owner is not verifiable; NOT IMPLEMENTED
EXTERNAL_PDF_CONTAINMENT: PROPOSED DENY until authentication/resource owner is verified; NOT IMPLEMENTED
GENERIC_DOWNLOAD_CONTAINMENT: PROPOSED category restriction/resource registry or temporary deny; NOT IMPLEMENTED
COFEPRIS_CONTAINMENT: PROPOSED differentiated patient/resource checks; detail/PDF current scope remains partial
INTERNAL_RECIPE_DEPENDENCIES: CURRENT-CLINIC CHECK ONLY; historical owner requires CATEGORY-C

CATEGORY_A_FIXES: explicit auth/method/permission gates where existing contracts support them
CATEGORY_B_CONTAINMENTS: fail-closed external/PDF/folder/surtido/download surfaces pending approval
CATEGORY_C_ARCHITECTURE_DEPENDENCIES: owner history, transfers, roles, superadmin, external ownership, documents
CATEGORY_D_EVIDENCE_GAPS: live schema, global middleware, storage mapping, role catalog, deployment behavior

FAIL_CLOSED_POLICY: deny without session, permission, clinic scope, resource owner or verifiable document
CLINICAL_OPERATION_IMPACT: external issuance/edit/print, downloads, folder/surtido and ambiguous history may be temporarily restricted
REGRESSION_RISKS: legitimate historical reads, printing, downloads, Doctor/D40 and COFEPRIS operations may be blocked
ROLE_VERIFICATION_DEPENDENCIES: catalog and superadmin identity unavailable
SCHEMA_VERIFICATION_DEPENDENCIES: synthetic allowlisted DB and owner/resource relations unavailable

RECOMMENDED_IMPLEMENTATION_ORDER: P1-A, then approved B/C containment lots, then P1-F architecture
PROPOSED_IMPLEMENTATION_BATCHES: P1-A through P1-F documented in section V; none started
FUTURE_TEST_MATRIX: section W; all NOT_EXECUTED
ROLLBACK_PLAN: per-lot snapshot, feature-gated deny/allow, targeted revert, repeat negative tests
RESIDUAL_SECURITY_RISKS: historical owner, roles, external ownership, documents, storage, legacy and infrastructure

IMMEDIATE_USER_DECISIONS_REQUIRED: approve containment, provide roles/superadmin source, approve historical model, COFEPRIS scope, synthetic DB access
RECOMMENDED_NEXT_PHASE: authorized evidence setup, then P1-A and approved fail-closed containment

DOCUMENTATION_CREATED: docs/audits/TH_SEC_P1_CONTAIN_PLAN_01.md
MASTER_CLOSURE_UPDATED: YES — reference/status appendix only
GLOBAL_DIFF_CHECK: PASS (exit 0; existing LF/CRLF warnings only)

REAL_DATABASE_ACCESSED: NO
SYNTHETIC_DATABASE_ACCESSED: NO
HTTP_WRITE_REQUESTS: NONE

APPLICATION_CODE_CHANGES: NONE
SCHEMA_CHANGES: NONE
DATA_CHANGES: NONE
ROLE_CHANGES: NONE
PERMISSION_CHANGES: NONE
MIGRATIONS: NONE
COMMITS: NONE
DEPLOYMENTS: NONE

P1_REMEDIATION_COMPLETED: NO
IMPLEMENTATION_AUTHORIZED: NO
PRODUCTION_READY: NO
```

**COMPLETE significa únicamente PLAN DE CONTENCIÓN DOCUMENTADO.**
