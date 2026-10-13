# TH-SEC-FIX-01B — Propiedad histórica de recetas y acceso entre clínicas

Fecha: 2026-10-12  
Proyecto: Clínica del Dolor  
Estado: **BLOCKED — el modelo actual no permite demostrar propiedad histórica sin una decisión de producto y una ampliación de contrato**

## Alcance y límites

Esta actividad auditó la propiedad histórica de recetas internas y externas después de un posible cambio de clínica del paciente. No se modificó el Conversational Interview, ni se hicieron escrituras, migraciones, commits o despliegues. El worktree ya contenía cambios locales de actividades anteriores y se conservó completo.

Se revisaron el inventario de trazabilidad, TH-SEC-AUDIT-01, TH-SEC-FIX-01, PHASE-05B, rutas, controladores, modelos, tablas sintéticas y las superficies de detalle, PDF y descarga. Las pruebas de base se limitaron a lecturas en `clinica_dolor_demo_20261010_final`; nunca se utilizó `bd_clinica` para escribir.

## Evidencia del modelo actual

| Área | Evidencia observada | Conclusión |
|---|---|---|
| Paciente y clínica | `pc_paciente.id_clinica` contiene la clínica actual; no hay tabla o columnas de historial de transferencias | No puede reconstruirse el origen histórico |
| Transferencias | No se encontró ruta, controlador, evento, auditoría o tabla de transferencia; `ClinicaModel::editPaciente()` actualiza datos del paciente y no registra un evento de transferencia | `PATIENT_TRANSFER_SUPPORTED` no demostrado |
| Receta interna | `receta_medica` contiene `id_paciente`, fecha, diagnóstico, medicamento y signos; no contiene `id_clinica`, emisor, usuario creador, clínica de origen, versión inmutable ni hash/PDF original | La relación paciente → clínica sólo refleja el estado actual |
| Receta externa | La tabla no está disponible en la base sintética allowlisted; el esquema externo revisado previamente tampoco ofrece propietario paciente/clínica verificable | No puede autorizarse históricamente |
| COFEPRIS | Tiene `id_paciente`, por lo que permite resolver la clínica actual del paciente; no aporta por sí sola clínica de origen de una receta | No prueba propiedad histórica |
| PDFs | `pdfReceta()` regenera un PDF TCPDF desde los datos actuales; no se encontró almacenamiento de un PDF original de receta ni un hash de fidelidad | No se puede probar fidelidad del original |
| Descargas | `DownloadController::download()` recibe ruta y archivo, valida el path y la sesión, pero no recibe un recurso clínico con propietario | La autorización documental queda abierta |

La búsqueda no encontró tablas o columnas de transferencia, asociación histórica múltiple, clínica de origen o emisor inmutable. Tampoco se fabricaron filas A→B→C para aparentar una prueba de propiedad que el esquema no puede representar.

## Property model gate

```text
PROPERTY_MODEL_GATE
PATIENT_TRANSFER_SUPPORTED: NO DEMOSTRADO — sólo existe el id_clinica actual
TRANSFER_HISTORY_AVAILABLE: NO
INTERNAL_RECIPE_ORIGIN_AVAILABLE: NO
EXTERNAL_RECIPE_ORIGIN_AVAILABLE: NO
HISTORICAL_OWNERSHIP_RELIABLE: NO
DESTINATION_ACCESS_VERIFIABLE: NO — la autorización actual no distingue destino autorizado de propietario histórico
ORIGINAL_PDF_PERSISTED: NO DEMOSTRADO
ORIGINAL_PDF_FIDELITY: NO VERIFICABLE
SCHEMA_CHANGE_REQUIRED: SÍ, para representar el contrato solicitado
LEGACY_DATA_RECONCILIATION_REQUIRED: SÍ, para registros existentes sin origen
SHARED_COMPONENT_IMPACT: ALTO — detalle, listados, PDF, COFEPRIS y descargas
IMPLEMENTATION_ALLOWED: NO — sólo documentación y propuesta de decisión
```

El gate es **NO**. No se aplicó una corrección de autorización histórica porque cualquier filtro construido únicamente con `pc_paciente.id_clinica` sería incorrecto después de una transferencia: podría convertir a la clínica destino en propietaria aparente y quitar a la clínica origen su acceso, o hacer imposible diferenciar lectura histórica de edición/eliminación.

## Compatibilidad con TH-SEC-FIX-01

TH-SEC-FIX-01 permanece conservado, pero sus controles internos deben interpretarse como **contención por clínica actual**, no como prueba de propiedad histórica. La consulta parametrizada de listado y `assertPatientAccess()` siguen siendo controles válidos para el estado actual mientras no exista transferencia, pero no satisfacen el contrato A→B solicitado.

Las superficies externas y de descarga ya documentadas como abiertas no quedan protegidas por esta actividad. No se eliminó ningún control previo ni se amplió el alcance a esas rutas.

## Matriz del contrato solicitado

| Escenario | Resultado de esta actividad | Motivo |
|---|---|---|
| Clínica A crea receta; paciente permanece en A | **NO VERIFICADO HISTÓRICAMENTE** | No existe una marca de origen inmutable, aunque el filtro actual devuelve al paciente mientras siga en A |
| Paciente A→B | **BLOCKED** | No hay transferencia ni owner de receta para distinguir lectura B de edición/eliminación |
| Clínica A conserva propiedad original | **BLOCKED** | No existe `id_clinica_origen` o registro de auditoría |
| Clínica B puede leer/ver/ imprimir/descargar copia fiel | **BLOCKED** | No hay relación de destino autorizada ni PDF original persistido |
| Clínica B no puede editar/eliminar/cambiar emisor | **BLOCKED** | No hay separación de permisos por owner histórico |
| Clínica C no relacionada | **FAIL-CLOSED NO DEMOSTRABLE** | La denegación actual depende sólo de la clínica actual del paciente |
| Usuario anónimo | **NO VERIFICADO dinámicamente en esta actividad** | Las guardas existentes no se sustituyeron; el runner HTTP completo no se reutilizó |
| Receta sin propietario histórico | **FAIL-CLOSED RECOMENDADO** | No debe exponerse ni reasignarse por inferencia |

No se ejecutaron POST, edición, eliminación, impresión, PDF o descargas para fabricar escenarios de transferencia. No se modificaron pacientes sintéticos ni se alteró el catálogo existente.

## Decisiones requeridas

Antes de implementar TH-SEC-FIX-01B, el responsable debe elegir y aprobar una alternativa:

1. **Modelo histórico explícito (recomendado):** añadir, mediante una fase autorizada de esquema y reconciliación, un owner inmutable por receta (clínica de origen, emisor, fecha/versionado y vínculo al paciente de creación), una historia de transferencias y una política explícita de lectura de destino. Debe preservar owner para editar/eliminar y producir una copia de sólo lectura para la clínica destino.
2. **Fail-closed hasta contar con owner:** bloquear detalle, PDF, impresión, descarga y edición de cualquier registro cuyo origen no pueda demostrarse. Esto evita exposición, pero no habilita el requisito de lectura de la clínica destino.
3. **Política separada para externas:** definir primero el propietario de `receta_medica_externos`, sus PDFs y sus descargas. No reutilizar la relación actual del paciente ni asumir que son globales.

La reconciliación de registros históricos sin owner debe ser una decisión explícita; no debe inferirse desde la clínica actual ni desde el usuario de la sesión.

## Cambios realizados

- Se creó este informe de auditoría.
- Se conservará el estado previo de TH-SEC-FIX-01.
- Se actualizará únicamente el apartado de estado correspondiente en `TRADITIONAL_HISTORY_CLOSURE_MASTER.md`.
- No se modificó PHP, JavaScript, CSS, rutas, modelos, esquemas, PDFs, almacenamiento ni bases de datos.

## Validación técnica

| Comprobación | Resultado | Alcance |
|---|---|---|
| Base allowlisted | PASS | Lecturas sintéticas previas: `clinica_dolor_demo_20261010_final`; pacientes 9301/9101 y 9302/9102 |
| Transferencia A→B | NO VERIFICADO / BLOCKED | No existe contrato ni fixture que represente transferencia histórica |
| Propiedad interna histórica | BLOCKED | La tabla no tiene owner de origen |
| Propiedad externa histórica | BLOCKED | Tabla/owner no disponibles en el entorno permitido |
| PDF original y fidelidad | NO VERIFICADO | El controlador regenera datos; no hay original persistido demostrable |
| Edición/eliminación por owner | NO VERIFICADO | No se ejecutaron escrituras ni existe separación histórica |
| Descarga autorizada por recurso | NO VERIFICADO | Endpoint sin vínculo a paciente/receta |
| PHP lint | PASS | `app/Models/RecetaModel.php`, `app/Controllers/PdfController.php` sin cambios en esta actividad |
| `git diff --check` | PASS | Sin errores nuevos; advertencias históricas de LF/CRLF permanecen |

## Resultado formal

```text
TH_SEC_FIX_01B_HISTORICAL_RECIPE_OWNERSHIP:
- STATUS: BLOCKED — modelo histórico inexistente; decisión de propiedad y fase de esquema/reconciliación requeridas
- CURRENT_BRANCH: Silvino
- CURRENT_HEAD: 1b08d9b4c41080c176fbe4588212863f3a1ac18c
- WORKTREE_PRESERVED: YES — cambios locales previos intactos
- PATIENT_TRANSFER_MODEL: sólo pc_paciente.id_clinica actual; no hay transferencia auditable
- TRANSFER_HISTORY_AVAILABLE: NO
- INTERNAL_RECIPE_ORIGIN: NO — receta_medica no tiene clínica/emisor de origen
- EXTERNAL_RECIPE_ORIGIN: NO — no verificable en el esquema permitido
- HISTORICAL_OWNERSHIP_RELIABLE: NO
- ORIGINAL_PDF_STORAGE: NO DEMOSTRADO; pdfReceta() regenera desde datos actuales
- ORIGINAL_PDF_FIDELITY: NO VERIFICADO / NO DEMOSTRABLE
- TRANSFER_A_TO_B: BLOCKED
- CLINIC_A_ORIGINAL_OWNERSHIP: BLOCKED
- CLINIC_B_READ_ACCESS: BLOCKED
- CLINIC_B_PRINT_ACCESS: BLOCKED
- CLINIC_B_DOWNLOAD_ACCESS: BLOCKED
- CLINIC_B_EDIT_DENIED: NO VERIFICADO
- CLINIC_B_DELETE_DENIED: NO VERIFICADO
- UNRELATED_CLINIC_DENIED: FAIL-CLOSED RECOMENDADO; prueba histórica NO VERIFICABLE
- ANONYMOUS_ACCESS_DENIED: NO VERIFICADO dinámicamente en esta actividad
- HISTORICAL_RECORDS_WITHOUT_OWNER: FAIL-CLOSED RECOMENDADO
- TH_SEC_FIX_01_COMPATIBILITY: PARTIAL — controles actuales sirven para clínica actual, no para transferencias históricas
- DOCUMENT_DOWNLOAD_AUTHORIZATION: NO VERIFICADO / RIESGO ABIERTO
- SCHEMA_CHANGES: NONE
- LEGACY_DATA_CHANGES: NONE
- IMPLEMENTATION_GATE: NO — sólo documentación/propuesta permitida
- IMPLEMENTED_CORRECTIONS: ninguna de aplicación; se conservaron las correcciones previas de TH-SEC-FIX-01
- BLOCKED_CORRECTIONS: owner histórico, transferencia A→B, lectura de destino, edición/eliminación separadas, PDF fiel y descargas vinculadas
- USER_DECISIONS_REQUIRED: modelo de owner permanente; política A→B→C/B→A; asociaciones simultáneas; multiclínica; reconciliación legacy; contrato externo
- FUNCTIONAL_REGRESSION: NO EJECUTADA — no hubo cambios de aplicación
- SECURITY_REGRESSION: NO EJECUTADA — no hubo cambios de aplicación; riesgos previos permanecen
- PHP_LINT: PASS — baseline de archivos de aplicación relacionados
- GLOBAL_DIFF_CHECK: PASS — sin errores nuevos; whitespace histórico conservado
- MODIFIED_FILES: docs/audits/TH_SEC_FIX_01B_HISTORICAL_RECIPE_OWNERSHIP.md; docs/audits/TRADITIONAL_HISTORY_CLOSURE_MASTER.md
- REMAINING_RISKS: ACT-05C-02; recetas externas sin owner; descargas sin vínculo de recurso; PDF original/fidelidad; SQL heredado; COFEPRIS y autorización histórica; reconciliación legacy
- PRODUCTION_READY: NO
```

No se inició una remediación de esquema, no se realizaron escrituras contra ninguna base y no se hicieron commits, push o despliegues.
