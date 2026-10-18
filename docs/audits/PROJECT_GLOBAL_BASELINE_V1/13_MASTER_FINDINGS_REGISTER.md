# 13 — Registro maestro de hallazgos

Fuente estructurada: `findings.csv`. Se consolidan hallazgos sin duplicar por documento histórico.

| ID | Severidad | Área | Evidencia actual | Estado |
|---|---|---|---|---|
| SEC-DOC-001 | Crítica | Receta externa/PDF | `PdfController::pdfRecetaExterno()` no muestra `assertPatientAccess` antes de leer/renderizar por ID | OPEN / validar y corregir |
| SEC-DOC-002 | Crítica | Receta externa | `RecetaExternoModel` consulta catálogo global y la propiedad clínica no está demostrada | OPEN / fail closed |
| SEC-FILE-003 | Alta | Descargas | `DownloadController` confina path pero no evidencia propietario del documento | OPEN |
| SEC-CSRF-004 | Alta | POST clínico | protección explícita localizada en flujo de resumen/P05; cobertura uniforme P04/P06–P11 no demostrada | OPEN |
| SEC-SQL-005 | Alta | Persistencia | consultas `query()`/SQL concatenadas en modelos y vistas heredadas | OPEN |
| CLIN-GET-006 | Alta | Integridad | siembras/efectos GET documentados históricamente; código/base actual no reconciliados | OPEN |
| SEC-AUTH-007 | Alta | Registro | endpoints y métodos por ID necesitan matriz de ownership completa | OPEN |
| AI-PRIV-008 | Alta | IA | cURL a Gemini y trazas; consentimiento/minimización/retención no probados | OPEN |
| OPS-REPRO-009 | Media | Supply chain | falta `composer.lock`; entorno/versiones no fijados | OPEN |
| QA-RUNTIME-010 | Alta | Evidencia | no runtime/browser/DB en este corte | BLOCKED_BY_ENVIRONMENT |
| A11Y-011 | Media | UX | contraste/foco/teclado/audio responsive no cubiertos globalmente | OPEN |
| DOC-GOV-012 | Media | Gobierno | cierres visuales históricos pueden confundirse con seguridad/función | OPEN |

No se inventaron CVE, datos, explotaciones ni porcentajes.

## Registro reconciliado V1.1

`findings_reconciled.csv` conserva los 12 hallazgos heredados y añade seis hallazgos de cobertura/gobierno/evidencia histórica: COV-013, COV-014, COV-015, COV-016, INT-D41-017 y TEST-018. Cada fila contiene causa, archivo/línea, confianza, estado de validación, impacto, severidad, dependencias, criterio de cierre y pruebas requeridas. No se elevó una ausencia de runtime a explotación confirmada.

## Reconciliación V1.2 — seguridad de rutas y recursos

La auditoría V1.2 creó `route_security_matrix_v1_2.csv` con 163 rutas efectivas y `resource_authorization_matrix_v1_2.csv` con 120 endpoints/recursos. No se agregaron hallazgos de vulnerabilidad nuevos: las observaciones de autenticación por controlador, ownership por recurso, CSRF, SQL, GET side effects, PDF, descargas y falta de runtime se mapearon a los hallazgos existentes `SEC-DOC-001`, `SEC-DOC-002`, `SEC-FILE-003`, `SEC-CSRF-004`, `SEC-SQL-005`, `CLIN-GET-006`, `SEC-AUTH-007`, `AI-PRIV-008` y `QA-RUNTIME-010`. Duplicados nuevos: 0.

La matriz confirma estáticamente guardas en partes de los controladores, pero no demuestra autorización efectiva sin pruebas propias/cruzadas. El estado de los 18 hallazgos permanece sin cierre de seguridad ni producción.

## Reconciliación V1.3 — compuerta de evidencia crítica

V1.3 revisó únicamente los dos P0 (`SEC-DOC-001`, `SEC-DOC-002`) y ocho P1 (`SEC-FILE-003`, `SEC-CSRF-004`, `SEC-SQL-005`, `CLIN-GET-006`, `SEC-AUTH-007`, `AI-PRIV-008`, `QA-RUNTIME-010`, `TEST-018`). No se añadieron hallazgos ni se modificó la severidad histórica.

Se conciliaron los indicadores de las matrices: 12 CSRF con validación localizada, 66 no verificadas y 85 no aplicables; 114 superficies SQL potencialmente mixtas, no 114 explotaciones; cero escrituras GET confirmadas estáticamente, con potenciales históricos aún abiertos; y 120 recursos con estados 1/109/8/2 según la matriz de recursos. La prueba histórica `P04-AUTH-20261014121758` permanece fuera de la evidencia runtime V1.3.

Los entregables `22_CRITICAL_FINDINGS_EVIDENCE_V1_3.md`, `critical_findings_matrix_v1_3.csv`, `23_SECURITY_REMEDIATION_EXECUTION_PLAN_V1_3.md`, `24_OWNER_DECISIONS_REGISTER_V1_3.md` y `25_RUNTIME_VALIDATION_STRATEGY_V1_3.md` documentan la evidencia, lotes, decisiones y pruebas futuras. Los 18 hallazgos siguen abiertos o bloqueados para seguridad/producción.
