# TH-DOCTOR-COFEPRIS-TENANT-ISOLATION-AND-DOCUMENT-TRACEABILITY-AUDIT-12B

## Resultado ejecutivo

**STATUS: PARTIAL — auditoría estática completa; esquema en ejecución, HTTP, base de datos y navegador NO VERIFICADOS.**

Esta intervención fue exclusivamente de lectura. No se ejecutó SQL, no se accedió a MySQL/XAMPP, no se modificó código funcional, no se modificó el esquema, no se modificaron registros ni archivos PDF y no se realizaron commits o despliegues.

La evidencia del repositorio no permite declarar que los folios COFEPRIS estén aislados por clínica. El modelo actual es **mixto o no resuelto**:

- `cofepris` identifica el paciente mediante `id_paciente`; el paciente contiene `id_clinica`.
- `cofepris_folio` administra rangos globales por `carpeta`, sin `id_clinica`, `id_paciente` ni una relación verificable.
- Las rutas de registro clínico tienen controles de paciente en algunos puntos, pero las operaciones de edición, surtido, carpeta y documento no tienen un único contrato de propiedad servidor-a-servidor demostrado.
- La descarga/PDF usa autorización de paciente, pero la ruta física se construye desde `carpeta` y `folio` sin una clave de clínica explícita.

Esto es un hallazgo de arquitectura y autorización pendiente, no una demostración de explotación HTTP.

## Contexto y preservación

| Campo | Resultado |
|---|---|
| Proyecto | Clínica del Dolor |
| Rama | `Silvino` |
| HEAD | `59654e05dc9f931c21b5392238961bcddb3ab2e4` |
| Worktree | **Preservado**; ya contenía cambios locales previos, incluida la intervención 12A |
| Alcance | COFEPRIS Doctor: folios, registros de paciente, folios/carpeta, detalle y PDF |
| Base de datos | No accedida en 12B |
| SQL ejecutado | No |
| HTTP ejecutado | No |
| Navegador ejecutado | No |
| Cambios funcionales | Ninguno en 12B |

Los cambios de 12A no se revirtieron. La remediación 12A quedó documentada como estática/parcial y agregó un token CSRF ligado a la sesión y clínica para el alta global de folios; no resolvió la ausencia de propiedad clínica en `cofepris_folio`.

## 1. Inventario de rutas y consumidores

### 1.1 Rutas registradas

| Método y ruta | Controlador | Consumidor | Observación de alcance |
|---|---|---|---|
| `GET /cofepris` | `HomeController::indexCofepris()` | `configuracion-cofepris.php` | Sesión `clinica`; página global de rangos |
| `POST /cofepris/insert-folios` | `CofeprisController::insertFolios()` | formulario de configuración | Sesión `clinica` + CSRF 12A; el catálogo no tiene clave de clínica demostrable |
| `GET /cofepris/{idCofepris}` | `CofeprisController::cofepris()` | `cofepris.php` | Consulta por ID y luego `assertPatientAccess()` |
| `GET /cofepris/paciente/{idPaciente}` | `ClinicaController::pacienteCofepris()` | `pacientes-cofepris.php` | `assertPatientAccess()` sobre paciente de URL |
| `GET /cofepris/paciente/{idPaciente}/editar/{idCofepris}` | `ClinicaController::pacienteCofepris()` | `pacientes-cofepris.php` | La relación entre `idCofepris` y `idPaciente` no se verifica antes de cargar el registro |
| `POST /paciente/insert-cofepris` | `ClinicaController::pacienteInsertCofepris()` | AJAX de alta/edición | Autoriza el `idPaciente` recibido; la edición actualiza por `id` de registro solamente |
| `GET /cofepris/carpeta/{idCarpeta}` | `CofeprisController::carpetaCofepris()` | `cofepris-folios.php` | Sólo sesión/rol; no se demostró propiedad de la carpeta |
| `POST /cofepris/edit-surtido` | `CofeprisController::editSurtido()` | `surtirProducto()` | Sólo sesión/rol; el modelo actualiza por `id` solamente |
| `GET /buscar/tabla-cofepris/{idPaciente}` | `BusquedasController::tableCofepris()` | tabla del expediente | `guardPatient()` antes de consulta por paciente |
| `GET /buscar/cofepris/{idCofepris}` | `BusquedasController::buscarCofepris()` | modal de detalle | Carga por ID antes de `guardPatient()`; riesgo de orden/autorización pendiente de prueba |
| `GET /buscar/tabla-cofepris-folios` | `BusquedasController::buscarCofeprisFolios()` | tabla de rangos | Sólo `guardClinic()`; listado global |
| `GET /pdf/cofepris/{id}` | `PdfController::pdfCofepris()` | impresión/descarga | Carga por ID, autentica y llama `assertPatientAccess()`; construye ruta física con carpeta/folio |

No se encontró una ruta DELETE de COFEPRIS en `app/Config/routes.php`.

### 1.2 Consumidores de aplicación

| Capa | Archivos y función | Uso |
|---|---|---|
| Controladores | `HomeController.php`, `CofeprisController.php`, `ClinicaController.php`, `BusquedasController.php`, `PdfController.php` | Pantallas, alta de folios, CRUD del registro, AJAX, folios/carpeta y PDF |
| Modelos | `CofeprisModel.php` | Lectura, alta/edición, rangos, validación de folio, surtido y HTML de detalle |
| Resumen/indicadores | `ClinicaModel.php`, `ClinicaIndicadoresService.php` | Conteos y series con relación `cofepris -> pc_paciente -> id_clinica` |
| Resumen clínico | `ClinicalSupplementService.php`, `ClinicalNarrativeService.php`, `resumen-clinico.php` | Lectura limitada por paciente y presentación de registros; no descarga documentos desde el resumen |
| Expediente | `pacientes-detalle.php` | Tarjeta y contador de COFEPRIS por paciente |
| Vistas | `configuracion-cofepris.php`, `pacientes-cofepris.php`, `cofepris.php`, `cofepris-folios.php`, `index.php`, `indicadores.php`, `resumen-clinico.php` | Configuración, historia del paciente, detalle, folios, métricas y resumen |
| PDF | `PdfController::pdfCofepris()` y enlaces de las vistas/modelo | Importa el PDF almacenado y agrega datos del paciente |
| Servicios auxiliares | `ClinicalSummarySecurity.php` | Token CSRF de acción de clínica añadido en 12A; los tokens clínicos existentes permanecen |
| Descargas | `DownloadController.php` | No se localizaron consumidores COFEPRIS en la inspección focalizada |

## 2. Evidencia de esquema

La única evidencia de esquema revisada en 12B fue el fixture estático `tests/fixtures/security-fixtures.sql`. La estructura runtime no fue consultada.

### 2.1 Tablas relevantes

| Tabla | Columnas relevantes observadas | Relación de clínica observada |
|---|---|---|
| `clinicas` | `id` | Tabla de clínicas |
| `pc_paciente` | `id`, `id_clinica`, datos personales | `id_clinica` con índice y FK a `clinicas(id)` en el fixture |
| `cofepris` | `id`, `id_paciente`, `carpeta`, `folio`, campos clínicos, `surtido` | **No contiene `id_clinica`**; el fixture no muestra FK `id_paciente -> pc_paciente` |
| `cofepris_folio` | `id`, fechas, `folio_inicial`, `folio_final`, `carpeta`, `total_folio`, `estado` | **No contiene `id_clinica` ni `id_paciente`** |

Conclusiones de esquema estático:

- `COFEPRIS_FOLIO_HAS_CLINIC_ID: NO` en el fixture revisado.
- `COFEPRIS_RECORD_HAS_CLINIC_ID: NO`; el aislamiento depende de la relación lógica por paciente.
- `COFEPRIS_RECORD_PATIENT_FK: NO EVIDENCIADA` en el fixture.
- `SCHEMA_RUNTIME_VERIFIED: NO`.
- `SCHEMA_RUNTIME: NOT_VERIFIED`.

### 2.2 Consultas de verificación para el propietario

No se ejecutaron. Son consultas de lectura para phpMyAdmin/MySQL local y no incluyen credenciales ni mutaciones:

```sql
SELECT DATABASE() AS selected_database;

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('clinicas', 'pc_paciente', 'cofepris', 'cofepris_folio')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SHOW CREATE TABLE cofepris;
SHOW CREATE TABLE cofepris_folio;
SHOW CREATE TABLE pc_paciente;

SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME,
       REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('cofepris', 'cofepris_folio', 'pc_paciente')
ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION;
```

La salida debe confirmar explícitamente que la base seleccionada es `bd_clinica` antes de interpretar resultados. No se deben ejecutar INSERT, UPDATE, DELETE, ALTER, DROP, TRUNCATE ni `REPLACE` como parte de esta auditoría.

## 3. Modelo de propiedad y tenant

### 3.1 Comportamiento observado

El middleware `AuthMiddleware('clinica')->authPermisos()` obtiene `id_clinica`, `id_usuario`, rol y vista desde el JWT firmado. `assertPatientAccess()` consulta `pc_paciente.id_clinica` y permite el acceso del portal Doctor cuando la clínica de sesión coincide con la clínica del paciente.

Esto respalda el siguiente camino para lecturas por paciente:

```text
JWT de clínica -> id_clinica confiable
                    |
                    v
              pc_paciente.id_clinica
                    |
                    v
             cofepris.id_paciente
```

Pero no respalda por sí mismo:

- que un `idCofepris` recibido junto con otro `idPaciente` pertenezca al mismo paciente;
- que una `carpeta` pertenezca a la clínica autenticada;
- que un rango de folios sea propiedad de una clínica;
- que `editarSurtido()` sólo modifique registros dentro del ámbito autorizado;
- que el archivo físico del PDF tenga una propiedad persistente independiente del folio.

### 3.2 Clasificación

| Afirmación | Resultado |
|---|---|
| Registros clínicos pueden asociarse a paciente | **PASS_STATIC**: `cofepris.id_paciente` |
| Paciente puede asociarse a clínica | **PASS_STATIC**: `pc_paciente.id_clinica` |
| Folio/rango puede asociarse a clínica | **NO DEMOSTRADO** |
| Carpeta puede asociarse a clínica | **NO DEMOSTRADO** |
| Edición verifica simultáneamente registro y paciente | **NO** en la consulta actual; riesgo estático |
| Surtido verifica registro y clínica/paciente | **NO** en la consulta actual; riesgo estático |
| Aislamiento cross-clinic por HTTP | **NO VERIFICADO** |
| Vulnerabilidad explotada | **NO CONFIRMADA** |

## 4. Matriz de autorización

| Acción | Autenticación | Contexto usado | Resultado estático | Riesgo residual |
|---|---|---|---|---|
| Ver configuración | `clinica` | `id_clinica` del JWT | PASS_STATIC | Los rangos son globales en esquema |
| Crear rango/folio | `clinica` + CSRF 12A | `id_clinica` sólo para token | PASS_STATIC limitado | Falta propiedad persistente del rango |
| Ver registro por ID | `clinica` + `assertPatientAccess()` | paciente recuperado por registro | PASS_STATIC con orden mejorable | El registro se carga antes de afirmar alcance |
| Ver lista por paciente | `guardPatient()` | paciente de URL + clínica del JWT | PASS_STATIC | La consulta del modelo concatena el ID; riesgo de SQL heredado |
| Ver detalle AJAX | `guardPatient()` después de lookup | paciente tomado del registro | POTENTIAL_RISK | El ID de registro se resuelve antes de autorización explícita |
| Abrir edición | `assertPatientAccess(idPaciente)` | paciente de URL | POTENTIAL_RISK | No se prueba `idCofepris -> idPaciente` |
| Crear registro clínico | `assertPatientAccess(idPaciente)` | `idPaciente` del JSON | POTENTIAL_RISK | El folio se asigna desde rango global; falta CSRF visible en endpoint |
| Editar registro clínico | `assertPatientAccess(idPaciente)` | `idPaciente` del JSON | P1 POTENTIAL_RISK | `UPDATE ... WHERE id = :id` no condiciona por paciente |
| Ver carpeta/rango | `authPermisos()` | sólo clínica/rol | P1/P2 POTENTIAL_RISK | `idCarpeta` no se vincula a clínica |
| Cambiar surtido | `authPermisos()` | sólo clínica/rol | P1 POTENTIAL_RISK | `UPDATE ... WHERE id = :id` |
| Ver folios globales | `guardClinic()` | sólo clínica/rol | P1/P2 POTENTIAL_RISK | `SELECT * FROM cofepris_folio` global |
| Descargar/generar PDF | `assertPatientAccess()` | paciente del registro | POTENTIAL_RISK | Ruta física pública derivada de carpeta/folio; no hay clave clínica |

## 5. Trazabilidad documental

### 5.1 Cadena identificada

```text
POST /cofepris/insert-folios
  -> CofeprisModel::guardarFolio()
  -> public/storage/cofepris/{carpeta}/{folio}.pdf

POST /paciente/insert-cofepris
  -> CofeprisModel::insertPacienteCofepris()
  -> cofepris.id + id_paciente + carpeta + folio

GET /pdf/cofepris/{id}
  -> CofeprisModel::cofepris($id)
  -> assertPatientAccess(id_paciente)
  -> DOCUMENT_ROOT/public/storage/cofepris/{carpeta}/{folio}.pdf
  -> FPDI importa el PDF y genera la respuesta
```

### 5.2 Riesgos de trazabilidad

1. El archivo físico se identifica por `carpeta/folio`, mientras la autorización se deriva de `cofepris.id_paciente`. No existe una clave de clínica explícita en la ruta ni en `cofepris_folio`.
2. La existencia de una comprobación en `PdfController` no demuestra que el acceso directo al path físico esté denegado por el servidor web. La exposición directa de `public/storage/cofepris` no fue probada.
3. `CofeprisModel::getCofepris()` construye HTML concatenando varios campos clínicos; la vista AJAX lo consume mediante HTML. La sanitización/escape de esos campos no quedó demostrada en este recorrido estático; clasificar como riesgo residual de salida, no como explotación confirmada.
4. El detalle y la descarga son consumidores distintos. Un control de autorización del endpoint no sustituye una política de almacenamiento que impida leer el archivo por URL directa.

## 6. Hallazgos priorizados

### P0

**No confirmado.** No se ejecutó una prueba cross-clinic, no se accedió a la base y no se observó exposición real de un documento.

### P1

**P1-COFEPRIS-01 — Propiedad de edición no condicionada por paciente.**

`CofeprisModel::editPacienteCofepris()` actualiza `cofepris` usando únicamente `WHERE id = :id`. El controlador autoriza el `idPaciente` recibido, pero no comprueba que el registro indicado por `idCofepris` pertenezca al mismo paciente. Si un atacante pudiera combinar un paciente autorizado con el ID de otro registro, el alcance de escritura podría ser incorrecto. **Estado: riesgo estático potencial; HTTP y DB no verificados.**

**P1-COFEPRIS-02 — Surtido sin propiedad de registro.**

`CofeprisModel::editarSurtido()` actualiza `surtido` sólo por `id`; `CofeprisController::editSurtido()` valida sesión/rol, pero no verifica paciente, clínica, carpeta ni folio. **Estado: riesgo estático potencial; HTTP y DB no verificados.**

**P1-COFEPRIS-03 — Documento/rango sin propiedad clínica persistente.**

El fixture no muestra `id_clinica` en `cofepris_folio` ni en `cofepris`. La relación indirecta de `cofepris` por paciente puede ser suficiente para registros clínicos, pero no resuelve la propiedad de rangos, carpetas ni archivos. **Estado: brecha de diseño/decisión pendiente, no explotación demostrada.**

### P2

- `CofeprisController::carpetaCofepris()` recibe una carpeta sin una comprobación de pertenencia.
- `getCofeprisFolios()` y `generarFolio()` operan globalmente sobre `cofepris_folio`/`carpeta`.
- `mostrarTablaCofepris()` concatena el identificador de paciente en SQL; `validaCofeprisFolio()` concatena carpeta y folio en su consulta. La validación de entrada y el guardado de parámetros deben endurecerse en una fase separada.
- `buscarCofepris()` y `pdfCofepris()` cargan por ID antes de completar la autorización contextual.
- El almacenamiento bajo `public/storage/cofepris` requiere verificar denegación de acceso directo, enumeración y política de nombres.
- El HTML de detalle del modelo concatena datos clínicos sin escape visible en el fragmento inspeccionado; requiere una revisión de salida segura.

### P3

- No existe un contrato documentado que defina si los rangos de folios son globales, por clínica o asignables a ambas dimensiones.
- No hay evidencia runtime de índices/FK/constraints reales en la base autorizada.
- No hay pruebas HTTP reproducibles de dos clínicas con dos pacientes y dos rangos documentales.
- Los tests estáticos existentes no sustituyen la comprobación de propiedad de documento y descarga.

## 7. Impacto de 12A y regresión de `ClinicalSummarySecurity`

`ClinicalSummarySecurity` conserva los métodos previos `enabled()`, `syntheticMode()`, `externalEnabled()`, `nativeSpeechEnabled()`, `csrfToken()`, `validCsrf()`, `captureCsrfToken()` y `validCaptureCsrf()`.

12A agregó de forma aditiva:

- `clinicActionCsrfToken(int $clinicId)`;
- `validClinicActionCsrf(int $clinicId, ?string $token)`.

La inspección estática muestra que los endpoints existentes de captura/interview siguen usando sus métodos de token anteriores y que sólo el flujo global de alta de COFEPRIS consume el token nuevo. No se cambiaron nombres ni firmas existentes.

| Revisión | Resultado |
|---|---|
| Métodos CSRF clínicos previos | PASS_STATIC |
| Métodos CSRF de captura/interview | PASS_STATIC; no se modificaron |
| Token de acción de clínica 12A | PASS_STATIC |
| Regresión HTTP | NOT_VERIFIED |
| Regresión de navegador | NOT_VERIFIED |

## 8. Opciones de esquema — no implementadas

| Opción | Descripción | Beneficio | Riesgo/impacto |
|---|---|---|---|
| A | Mantener folios globales y documentar explícitamente la política global | Sin migración | No resuelve propiedad clínica ni trazabilidad de archivo |
| B | Agregar `id_clinica` a `cofepris_folio` y al vínculo documental | Aislamiento directo y consultas verificables | Requiere migración, backfill, reglas para rangos existentes y decisión de propiedad |
| C | Crear tabla de asignación `cofepris_folio_clinica` | Permite rangos compartidos/asignados con historial | Más joins y reglas de concurrencia |
| D | Crear entidad documental con `id_clinica`, `id_paciente`, rango, folio y ruta interna | Trazabilidad completa y desacopla archivo de URL pública | Cambio de modelo mayor, migración y política de almacenamiento |
| E | Mantener `cofepris` ligado a paciente y encapsular toda operación en consultas con join a `pc_paciente` | Mejora aislamiento de registros sin cambiar rangos | No resuelve propiedad de rangos ni acceso directo al archivo |

### Recomendación para decisión del propietario

No implementar sin una decisión explícita del propietario sobre si los rangos son globales por operación o propiedad de cada clínica. Técnicamente, la opción más completa es **D**, o **B + E** si el catálogo de rangos debe permanecer simple. Cualquier opción debe incluir una política de migración/backfill, unicidad/concurrencia de folios, asignación de carpetas y almacenamiento privado fuera de una ruta pública enumerable.

## 9. Validaciones ejecutadas

| Validación | Resultado |
|---|---|
| Inventario estático de rutas COFEPRIS | PASS_STATIC |
| Inventario de controladores/modelos/vistas/servicios/PDF | PASS_STATIC |
| Revisión del fixture de esquema | PASS_STATIC_FIXTURE_ONLY |
| Verificación de esquema runtime | NOT_VERIFIED |
| SQL de lectura ejecutado | NO |
| HTTP propio/cruzado | NOT_VERIFIED |
| Persistencia/rollback/no-op SQL | NOT_VERIFIED |
| Descarga/PDF y acceso directo al storage | NOT_VERIFIED |
| Navegador, consola y responsive | NOT_VERIFIED |
| Regresión de `ClinicalSummarySecurity` | PASS_STATIC; runtime no verificado |
| Cambios funcionales de aplicación en 12B | NONE |
| Base de datos modificada | NO |
| Esquema modificado | NO |
| `git diff --check` | PASS; sólo mostró advertencias históricas de finales de línea en archivos ya modificados |

## 10. Decisión y bloqueos

| Campo | Resultado |
|---|---|
| `FOLIO_OWNERSHIP_MODEL` | `MIXED_OR_UNRESOLVED` |
| `SECURITY_VULNERABILITY_CONFIRMED` | `NO`; existen riesgos estáticos P1/P2 que requieren evidencia |
| `OWNER_DECISION_REQUIRED` | Sí: global, por clínica o modelo mixto con asignación |
| `RUNTIME_SCHEMA_BLOCKER` | Sí |
| `HTTP_CROSS_CLINIC_BLOCKER` | Sí |
| `DOCUMENT_ACCESS_BLOCKER` | Sí |
| `READY_FOR_12C` | No hasta decisión de propiedad y ejecución en entorno autorizado |

## 11. Propuesta de Fase 12C

1. Obtener `SHOW CREATE TABLE` y constraints reales desde la base autorizada, sin mutaciones.
2. Registrar la decisión de propiedad de rangos y documentos.
3. Diseñar el cambio de esquema y backfill; no ejecutarlo como parte de esta auditoría.
4. Aislar las operaciones de edición y surtido por `id`, paciente y clínica del servidor.
5. Revalidar carpeta, folio y registro antes de mostrar o descargar un documento.
6. Mover documentos a almacenamiento no enumerable o aplicar una regla de servidor que impida acceso directo sin autorización.
7. Añadir pruebas sintéticas de dos clínicas para GET, AJAX, POST, edición, surtido, PDF, URL directa e IDs manipulados.
8. Repetir pruebas de no-op y regresión de `ClinicalSummarySecurity`.

## Entrega 12B

```yaml
TH_DOCTOR_COFEPRIS_TENANT_ISOLATION_AND_DOCUMENT_TRACEABILITY_AUDIT_12B:
  STATUS: PARTIAL_STATIC_COMPLETE_RUNTIME_NOT_VERIFIED
  BRANCH: Silvino
  HEAD: 59654e05dc9f931c21b5392238961bcddb3ab2e4
  WORKTREE_PRESERVED: YES
  ROUTES_INVENTORIED: 12
  COFEPRIS_FOLIO_HAS_CLINIC_ID: NO_STATIC_FIXTURE_EVIDENCE
  COFEPRIS_RECORD_HAS_CLINIC_ID: NO
  SCHEMA_RUNTIME: NOT_VERIFIED
  OWNERSHIP_MODEL: MIXED_OR_UNRESOLVED
  CROSS_CLINIC_HTTP: NOT_VERIFIED
  PDF_DIRECT_ACCESS: NOT_VERIFIED
  P1_STATIC_RISKS: 3
  P2_STATIC_RISKS: 6
  P0_CONFIRMED: NO
  CODE_MODIFIED_IN_12B: NO
  DATABASE_MODIFIED: NO
  SCHEMA_MODIFIED: NO
  DOCUMENT_CREATED: YES
  OWNER_DECISION: REQUIRED
  BLOCKERS: SCHEMA_RUNTIME_AND_AUTHORIZED_HTTP
  RESIDUAL_RISKS: EDIT_BY_ID, SURTIDO_BY_ID, GLOBAL_FOLIOS, FOLDER_SCOPE, PDF_STORAGE, SQL_CONCATENATION, HTML_OUTPUT
  PHASE_12C_PROPOSED: YES
```

**Documento creado:** `docs/audits/TH_DOCTOR_COFEPRIS_TENANT_ISOLATION_AND_DOCUMENT_TRACEABILITY_AUDIT_12B.md`

**COMMITS: NONE**
**DEPLOYMENTS: NONE**
**PRODUCTION_READY: NO**
