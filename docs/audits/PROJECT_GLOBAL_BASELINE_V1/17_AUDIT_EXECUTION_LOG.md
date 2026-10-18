# 17 — Registro de ejecución

| Fase | Estado | Evidencia |
|---|---|---|
| 01 Inventario/repo | PASS_STATIC | `git`, `rg`, counts, worktree registrado |
| 02 Arquitectura | PASS_STATIC_PARTIAL | bootstrap, Router, Database, middleware, routes leídos |
| 03 Funcionalidad | PARTIAL_STATIC | familias, rutas y controladores inventariados |
| 04 Paciente/Entrevista | PARTIAL_STATIC | P01–P11 y endpoints Conversational leídos; runtime no |
| 05 Doctor/admin | PARTIAL_STATIC | grupo `/clinica`, roles y ownership focalizados |
| 06 Seguridad | PARTIAL_STATIC | auth, CSRF, SQL, files, PDF, IA scan |
| 07 DB | UNVERIFIED_RUNTIME | inferida desde SQL/modelos; sin PDO/SQL |
| 08 APIs/IA | PARTIAL_STATIC | provider, adapters, controller contracts |
| 09 UX/UI | PARTIAL_STATIC | CSS/vistas/docs; sin browser nuevo |
| 10 Calidad | PASS_STATIC_LIMITED | PHP 156/156, JS 92/92, diff-check |
| 11–15 Ops/compliance/gobierno | PARTIAL/DOCUMENTAL | sin entorno productivo ni legal |
| 16 Runtime | BLOCKED_BY_SCOPE | requiere propietario/entorno sintético |
| 17 Consolidación | PASS_DOCUMENTED | docs/CSVs creados en este directorio |

## Archivos creados

Sólo documentación e inventarios dentro de `docs/audits/PROJECT_GLOBAL_BASELINE_V1/`; las modificaciones se limitaron a esos documentos de auditoría. No se modificaron archivos de aplicación, configuración, pruebas productivas ni datos.

## Cambios preservados

El worktree ya estaba sucio; no se interpretó ninguna modificación como propia de esta auditoría. Se evitó staging, commit, reset, clean, push y deploy.

## Fase V1.1 — 2026-10-14

- Verificado HEAD completo y rama `Silvino`.
- Conciliadas 136 declaraciones de ruta con 163 registros efectivos.
- Generados manifiestos individuales de archivos, funcionalidades, pruebas, recursos, documentos, hallazgos, cobertura y roadmap.
- Corregida la discrepancia de inventario D01–D40 y registrada la mención separada de D41 como decisión pendiente.
- Ejecutados sólo `php -l`, `node --check` y `git diff --check`; sin MySQL/PDO, HTTP, navegador, Docker o escrituras.
- Estado de fase: `PARTIAL_STATIC / RUNTIME_UNVERIFIED / NOT_PRODUCTION_READY`.

## Fase V1.2 — 2026-10-14

- Reconciliadas las 136 declaraciones textuales con 163 rutas efectivas, expandiendo explícitamente la clausura común para `/clinica` y `/historia-clinica`.
- Creada la matriz estática de seguridad de rutas con 163/163 filas documentadas y la matriz de autorización de recursos con 120 filas.
- Revisados estáticamente `AuthMiddleware::authPermisos()`, `assertPatientAccess()`, `ClinicalSummarySecurity`, controladores de búsqueda/documentos y rutas PDF/descarga.
- Confirmado que la evidencia de ownership, CSRF uniforme, GET side effects, SQL y documentos sigue incompleta; no se elevó ninguna observación a explotación confirmada.
- Ejecutados únicamente análisis estático y validaciones locales de sintaxis/diff; no MySQL/PDO, HTTP, navegador, Docker ni escrituras.
- Estado V1.2: `PARTIAL_STATIC / RUNTIME_UNVERIFIED / NOT_PRODUCTION_READY`.

## Fase V1.3 — compuerta de evidencia crítica — 2026-10-14

- Revisados los dos P0 y ocho P1 existentes; no se repitió el inventario global ni se crearon hallazgos nuevos.
- Conciliados CSRF, SQL, GET side effects, autorización por recurso, autenticación compartida y el alcance histórico de P04.
- Creados `22_CRITICAL_FINDINGS_EVIDENCE_V1_3.md`, `critical_findings_matrix_v1_3.csv`, `23_SECURITY_REMEDIATION_EXECUTION_PLAN_V1_3.md`, `24_OWNER_DECISIONS_REGISTER_V1_3.md` y `25_RUNTIME_VALIDATION_STRATEGY_V1_3.md`.
- Actualizados el registro maestro, roadmap, log y reconciliación V1.2 mediante adendas; se preservó la evidencia histórica.
- Ejecutada únicamente documentación/revisión estática y validación de artefactos; no MySQL/PDO, HTTP, navegador, Docker, escrituras, cambios de aplicación o entorno.
- Estado V1.3: `PARTIAL_STATIC / EVIDENCE_RECONCILED / RUNTIME_UNVERIFIED / NOT_PRODUCTION_READY`.
