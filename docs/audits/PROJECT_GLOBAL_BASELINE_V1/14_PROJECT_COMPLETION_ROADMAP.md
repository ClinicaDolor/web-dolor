# 14 — Roadmap real de finalización

## Etapa A — Bloqueantes de seguridad

1. Diseñar y aplicar propiedad clínica de recetas externas, COFEPRIS y archivos.
2. Proteger PDF/descargas con ownership y allowlists; probar cruzado.
3. Unificar CSRF para todos los POST/AJAX clínicos y regeneración/token policy.
4. Parametrizar SQL heredado y allowlist de identificadores dinámicos.

## Etapa B — Integridad clínica

5. Ejecutar matriz GET sin escrituras; eliminar o documentar siembra con decisión clínica.
6. Probar idempotencia, versionado, no-op, dependencias, limpieza, finalización y reintentos.
7. Verificar constraints/índices/DDL/backups en DB sintética.

## Etapa C — Flujos

8. Completar P01–P11 y Entrevista con dos pacientes/clínicas sintéticos.
9. Repetir Doctor D01–D40, admin y roles; cubrir estados error/vacío.

## Etapa D — IA/documentos

10. Consentimiento, minimización, proveedor/fallback, trazas, STT, PDF y descarga.

## Etapa E — UX/QA

11. Browser matrix, consola/Network, responsive, teclado, foco, contraste, audio y canvas.
12. CI reproducible: lockfiles, lint, unit/integration, SAST/DAST, dependency audit.

## Etapa F — Operación/gobierno

13. HTTPS, secrets, logs, backup/restore, alertas y runbooks.
14. Revisión clínica, privacidad/legal, seguridad y aceptación del propietario.

Cada etapa depende de evidencia reproducible; no se asignan horas inventadas.

## Roadmap V1.1 accionable

`roadmap_actions.csv` convierte los riesgos y las brechas de cobertura en 11 actividades con prioridad, dependencias, aceptación, pruebas y evidencia requerida. La ruta crítica inicia en autorización de recursos/documentos, matriz de rutas y CSRF; continúa con GET-write/idempotencia, SQL, IA/privacidad, runtime sintético, accesibilidad y gobierno. Ninguna actividad se considera ejecutada por esta conciliación.

## Addendum V1.2 — ruta crítica de autorización

La conciliación de rutas agregó dos artefactos estáticos: `route_security_matrix_v1_2.csv` (163 rutas efectivas) y `resource_authorization_matrix_v1_2.csv` (120 recursos). No sustituyen las pruebas runtime. Antes de cualquier cierre deben ejecutarse, en entorno sintético aislado, los casos A/B de paciente y clínica para lectura, escritura, eliminación, PDF y descargas; además de un no-op repetido, ID inexistente, recurso sin propietario y validación CSRF en cada POST. Recetas externas, PDF externo, COFEPRIS y archivos siguen dependiendo de una decisión explícita de ownership y fail-closed.

La prioridad de roadmap se mantiene: autorización documental y de recursos, CSRF uniforme, GET sin escrituras no deseadas, SQL heredado, runtime/browser y revisión clínica. V1.2 no considera completada ninguna acción por la sola existencia de una guarda estática.

## Addendum V1.3 — lotes aprobables

La compuerta crítica creó un plan por lotes en `23_SECURITY_REMEDIATION_EXECUTION_PLAN_V1_3.md`: A contención externa; B ownership por recurso; C documentos/descargas; D integridad GET; E CSRF; F SQL; G IA/privacidad; H evidencia runtime; e I mejoras no bloqueantes. Ningún lote fue implementado.

La ruta crítica inicia con las decisiones `OD-01` a `OD-08` sobre propiedad, PDF, archivos, roles, registros antiguos y fail-closed. Después requiere fixtures A/B sintéticos, pruebas negativas y trazas de persistencia. `25_RUNTIME_VALIDATION_STRATEGY_V1_3.md` define el gate y `24_OWNER_DECISIONS_REGISTER_V1_3.md` mantiene las decisiones pendientes. La existencia de este plan no cambia el estado de ningún hallazgo ni habilita producción.
