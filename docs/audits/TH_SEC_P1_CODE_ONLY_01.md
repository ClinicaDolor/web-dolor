# TH-SEC-P1-CODE-ONLY-01

## Remediación focalizada de seguridad sin base de datos

**Proyecto:** Clínica del Dolor

**Fecha:** 2026-10-12
**Alcance ejecutado:** revisión estática de código, rutas y documentación; sin acceso a bases de datos, HTTP, navegador, Docker ni servicios externos.

## Estado ejecutivo

```yaml
TH_SEC_P1_CODE_ONLY_01:
  STATUS: PARTIAL
  CURRENT_BRANCH: Silvino
  CURRENT_HEAD: 1b08d9b4c41080c176fbe4588212863f3a1ac18c
  WORKTREE_PRESERVED: YES

  P1_FINDINGS_REVIEWED: YES
  PURE_CODE_CHANGES_IDENTIFIED: YES
  PURE_CODE_CHANGES_IMPLEMENTED: NONE
  FILES_MODIFIED: docs/audits/TH_SEC_P1_CODE_ONLY_01.md, docs/audits/TH_TRADITIONAL_MVP_EXIT_GATES_V1.md
  INPUT_VALIDATION_CHANGES: NONE
  SAFE_ERROR_HANDLING_CHANGES: NONE
  PURE_AUTHORIZATION_POLICY: PROPOSED_ONLY
  PURE_UNIT_TESTS: NONE

  PHP_LINT: NOT_RUN — no PHP was modified in this phase
  JS_SYNTAX: NOT_RUN — no JavaScript was modified in this phase
  GLOBAL_DIFF_CHECK: PASS_WITH_PREEXISTING_LINE_ENDING_WARNINGS

  DATABASE_CONNECTIONS: NONE
  PDO_EXECUTED: NO
  SQL_EXECUTED: NONE
  HTTP_REQUESTS: NONE
  DOCKER_EXECUTED: NO
  ENV_MODIFIED: NO
  SCHEMA_CHANGES: NONE
  DATA_CHANGES: NONE
  MIGRATIONS: NONE

  EXTERNAL_RECIPES_DISABLED: NO
  PDF_DISABLED: NO
  DOWNLOADS_DISABLED: NO
  COFEPRIS_DISABLED: NO

  P1_FINDINGS_FULLY_REMEDIATED: NONE
  P1_FINDINGS_PARTIALLY_MITIGATED: D14_INTERNAL_RECIPES_EXISTING_CONTAINMENT_ONLY
  P1_FINDINGS_STILL_OPEN: YES
  DYNAMIC_AUTHORIZATION_VERIFIED: NO

  MVP_EXIT_GATES_CREATED: YES
  G01_STATUS: NOT_VERIFIED
  G02_STATUS: NOT_VERIFIED
  G03_STATUS: NOT_VERIFIED
  G04_STATUS: BLOCKED
  G05_STATUS: NOT_VERIFIED
  G06_STATUS: BLOCKED
  G07_STATUS: BLOCKED
  G08_STATUS: BLOCKED
  G09_STATUS: BLOCKED
  G10_STATUS: NOT_VERIFIED
  G11_STATUS: NOT_STARTED

  NEXT_RECOMMENDED_PHASE: S1 isolated synthetic evidence and resource ownership mapping, then S2 endpoint remediation
  USER_DECISIONS_REQUIRED: YES
  DOCUMENTATION_CREATED: YES
  MASTER_CLOSURE_UPDATED: NO — preexisting local modification conflict
  COMMITS: NONE
  DEPLOYMENTS: NONE
  PRODUCTION_READY: NO
```

## Preflight y límites

Se revisaron estáticamente los hallazgos y restricciones registrados en:

- `docs/audits/TH_SEC_P1_TRIAGE_01_SECURITY_FINDINGS.md`
- `docs/audits/TH_SEC_P1_CONTAIN_PLAN_01.md`
- `docs/audits/TH_SEC_P1_EVIDENCE_GATE_01.md`
- `docs/audits/TH_SEC_AUTH_08_ROLE_AND_MULTICLINIC_AUTH_AUDIT.md`
- `docs/audits/TRADITIONAL_HISTORY_CLOSURE_MASTER.md`
- `docs/architecture/TH_SEC_ARCH_01_HISTORICAL_RECIPE_OWNERSHIP_V1.1.md`
- `docs/architecture/TH_SEC_ARCH_01_DATA_MODEL_PROPOSAL_V1.1.md`

El worktree ya contenía modificaciones locales en `routes.php`, controladores, modelos, vistas, CSS, JavaScript, fixtures y el documento maestro de cierre, además de servicios y pruebas no versionados. Los archivos de aplicación relevantes para recetas, PDF, descargas y COFEPRIS no fueron modificados en esta fase. Tampoco se modificó el documento maestro: hacerlo habría mezclado esta ejecución con cambios previos no atribuibles a este trabajo.

## Matriz de revisión P1

| Superficie | Evidencia estática | Clasificación | Acción en esta fase |
|---|---|---|---|
| Receta externa: detalle, edición y búsquedas | Existen GET de detalle/edición y endpoints de búsqueda; la evidencia revisada no demuestra resolución de propietario antes de leer el recurso | Requiere evidencia de BD, arquitectura y aprobación clínica | No modificar; requiere resolver propiedad histórica y contexto de clínica |
| Receta externa: alta/edición | Existen POST dedicados; no se validó dinámicamente propiedad ni contrato de traslado | Requiere evidencia de BD, arquitectura y aprobación clínica | No modificar |
| PDF de receta interna/externa y COFEPRIS | El router aplica autenticación global, pero la autorización del recurso no queda demostrada por inspección estática | P1 abierto | No modificar ni declarar protegido |
| Descargas | Existe ruta genérica de descarga y autenticación global; no se demostró autorización del archivo/propietario | P1 abierto | No modificar |
| COFEPRIS por paciente | Hay rutas con `assertPatientAccess()` en algunos flujos; la pertenencia de carpeta, folio y surtido no queda demostrada en todos los endpoints | Parcial / no verificado | No modificar |
| Recetas internas D14 | El flujo existente usa la clínica autenticada y el inventario registra contención previa | Mitigación previa, no revalidada aquí | Conservar sin tocar |
| Validación de entrada | Es posible añadir prevalidaciones puras antes de persistencia | Candidato a cambio de código puro | No aplicar: controladores relevantes ya están modificados localmente |
| Manejo seguro de errores | Es posible normalizar mensajes sin exponer detalles técnicos | Candidato a cambio de código puro | No aplicar sin contrato de consumidores y archivo limpio |
| Política pura de autorización | Podría extraerse una política de decisión separada | Propuesta | No agregar código muerto ni duplicar servicios preexistentes |

## Hallazgos de rutas revisados

La inspección de `app/Config/routes.php` identificó, entre otras, estas superficies sensibles:

- `/receta-externo/{id}` y `/receta-externo/editar/{id}`.
- POST de inserción y edición de recetas externas.
- `/buscar/receta-externo/{id}` y `/buscar/tabla-recetas-externos`.
- `/pdf/receta/{idReceta}`, `/pdf/receta-externo/{id}` y `/pdf/cofepris/{id}`.
- `/descargar/{ruta}/{archivo}`.
- `/cofepris/{idCofepris}`, `/cofepris/paciente/{idPaciente}`, `/cofepris/carpeta/{idCarpeta}` y POST de folios/surtido.
- Endpoints de búsqueda de recetas y COFEPRIS.

`app/Core/Router.php` aplica autenticación para PDF y descarga, pero autenticación no equivale a autorización del recurso. Esta distinción permanece abierta.

## Cambios realizados

No hubo cambios en PHP, JavaScript, modelos, rutas, consultas, esquemas, configuración ni datos. Sólo se crearon los dos documentos de esta fase. No se añadieron políticas o servicios nuevos porque el contexto de propiedad no puede demostrarse sin evidencia de datos/arquitectura y ya existen cambios locales relacionados.

## Validación estática

- `git diff --check`: PASS; únicamente reportó advertencias históricas de conversión LF/CRLF en archivos preexistentes.
- Revisión de rutas y controladores: PASS como inspección estática, no como autorización dinámica.
- PHP lint: NO VERIFICADO / no aplicable a cambios de esta fase.
- Sintaxis JavaScript: NO VERIFICADO / no se modificó JavaScript.
- BD, PDO y SQL: no ejecutados.
- HTTP, navegador, PDF y descarga: no ejecutados.

## Riesgos que siguen abiertos

1. Propiedad histórica y aislamiento de recetas externas.
2. Autorización por recurso para PDF, descargas y archivos clínicos.
3. Propiedad de carpetas, folios y operaciones COFEPRIS.
4. Reconciliación y traslado de recetas históricas.
5. Evidencia dinámica de autorización propia, cruzada y sin sesión.
6. Impacto clínico/operativo de cualquier bloqueo temporal fail-closed.

## Decisiones requeridas

- Autorizar un entorno sintético aislado y sus fixtures de propiedad.
- Aprobar el modelo de resolución de propietario antes de tocar endpoints.
- Decidir si se acepta contención temporal fail-closed para recursos sin propietario verificable.
- Definir el flujo operativo para registros históricos no conciliados.

## Siguiente fase recomendada

Ejecutar primero S1: evidencia sintética y mapa de propiedad por recurso, sin modificar producción. Con esa evidencia, diseñar S2 para corregir autorización de endpoints. Después repetir pruebas cruzadas de HTML, AJAX, PDF y descarga. No cerrar ningún P1 ni declarar producción hasta contar con evidencia dinámica reproducible.

## Estado de entrega

El cierre es **PARTIAL**. Se completó la clasificación estática y se creó el tablero de salida, pero no se remedió ningún hallazgo P1 porque las correcciones seguras requieren contexto de propiedad y los archivos relevantes están previamente modificados. El sistema **no está listo para producción**.

