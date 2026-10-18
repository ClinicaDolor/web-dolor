# 23 — Security remediation execution plan V1.3

Este documento propone lotes aprobables. No implementa ninguno. El orden depende de severidad, bloqueo y dependencias, no de la numeración de módulos.

## Reglas de ejecución

- Cada lote inicia con fixture sintético aislado, snapshot/DB diff y una decisión de ownership aprobada.
- Fail-closed es preferible a exponer una fila cuyo propietario no se puede resolver.
- No se usarán registros reales, bypass de autenticación, `git reset`, limpieza destructiva ni despliegue.
- Un lote sólo se considera terminado con evidencia estática, runtime y regresión proporcional.

## Lotes

| Lote | Hallazgos | Alcance y código previsto | Dependencias | Riesgo de regresión | Reversión | Pruebas/aceptación |
|---|---|---|---|---|---|---|
| A — contención externa | SEC-DOC-001, SEC-DOC-002 | Bloquear listado/PDF externos cuando no exista propietario demostrable; conservar mensaje informativo sin datos clínicos. | Decisión de ownership histórico; fixture A/B. | Alto: puede interrumpir flujo externo. | Revertir sólo el diff del lote tras conservar evidencia. | A sólo ve A, B sólo B, ownerless/foreign/missing deny; PDF no produce bytes no autorizados. |
| B — ownership por recurso | SEC-AUTH-007 | Completar contrato por paciente, clínica, rol y operación; aplicar aserción server-side a IDs. | Matriz 120 recursos; decisiones Doctor/Asistente/Admin. | Alto: IDs heredados y AJAX. | Feature/fix aislado con rollback probado. | Matriz A/B read/write/delete y errores controlados, sin filtración de existencia. |
| C — documentos y descargas | SEC-FILE-003, parte SEC-DOC-001 | Vincular archivo/PDF a recurso autorizado, allowlist de tipo y confinamiento de ruta. | Ownership documental, política de archivo/PDF. | Alto: enlaces y archivos históricos. | Desactivar endpoint o restaurar comportamiento sólo en entorno sintético si falla. | Propio permitido; ajeno, traversal, missing y tipo no permitido denegados. |
| D — integridad clínica GET | CLIN-GET-006 | Eliminar escrituras GET no intencionales o formalizar preparación idempotente aprobada. | DB sintética, decisión clínica, trazas before/after. | Alto: progreso y datos existentes. | Revertir únicamente cambio de escritura con DB sintética restaurable. | GET repetido no agrega/actualiza/elimina inesperadamente; delta aprobado e idempotente si existe. |
| E — CSRF uniforme | SEC-CSRF-004 | Inventariar cada POST/AJAX y exigir token server-side con contrato único y negativa segura. | Resultado Fase 26; matriz de rutas. | Alto: clientes legacy y reintentos. | Rollback por endpoint, manteniendo protección ya existente. | token válido PASS; ausente/inválido/stale/cross-session FAIL sin mutación. |
| F — SQL parametrizado | SEC-SQL-005 | Sustituir concatenación no confiable y SQL de vista por métodos parametrizados/allowlists, por familia de consultas. | Taint review, regresión y datos sintéticos. | Alto: formato de resultados y ordenamiento. | Lotes por modelo con diff pequeño; no rollback global. | entradas benignas/malformadas no rompen contrato ni cambian alcance; revisión no encuentra concatenación. |
| G — IA y privacidad | AI-PRIV-008 | Minimizar payload, aplicar consentimiento/política, controlar trazas/retención y fail-closed del proveedor. | Decisión privacidad, proveedor y retención. | Alto: entrevista y disponibilidad. | Deshabilitar proveedor/usar mock seguro. | mock no recibe PHI real; falla controlada; logs sin secretos/PHI no permitido. |
| H — evidencia runtime | QA-RUNTIME-010, TEST-018 | Ejecutar gates P04 y suites autorizadas con artefactos de entorno, consola, Network y DB diff. | XAMPP/entorno sintético, navegador reproducible, owner attestation. | Bajo para código; alto para liberación. | No aplica a producto; repetir con fixture aislado si falla. | artefactos timestamped, alcance explícito y sin reusar evidencia histórica. |
| I — no bloqueantes | A11Y-011, OPS-REPRO-009, DOC-GOV-012 | Contraste/teclado, lockfiles y separación de estados en documentación. | No bloquea contención P0. | Bajo/medio. | Revertir por archivo. | lint, browser/accessibility y documentación reconciliada. |

## Dependencias y orden recomendado

1. Aprobar decisiones de `24_OWNER_DECISIONS_REGISTER_V1_3.md` para ownership, documentos, roles y registros antiguos.
2. Ejecutar A como contención fail-closed; C puede iniciar en paralelo sólo con política documental aprobada.
3. Completar B antes de ampliar recursos de C o realizar pruebas negativas de D.
4. Ejecutar D y E sobre fixtures sintéticos antes de migrar SQL en F.
5. Ejecutar G con proveedor mock y política de privacidad aprobada.
6. H valida lotes A–G; I no puede convertir una brecha de seguridad en PASS.

## Criterio de no avance

Detener un lote si no se puede demostrar aislamiento de base, si el owner no es resoluble, si el fixture puede afectar datos previos, si una prueba necesita bypass o si el cambio ampliaría la política clínica sin decisión del propietario.

## Evidencia requerida por lote

Cada entrega debe incluir: archivos exactos y líneas, diff acotado, fixture/IDs sintéticos, autorización del entorno, casos permitidos y denegados, estado de persistencia antes/después, logs sin secretos, `git diff --check`, y resultado `PASS/FAIL/NO VERIFICADO`. La falta de browser o HTTP se reporta como `NO VERIFICADO`, no como PASS.
