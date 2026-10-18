# 21 — Reconciliación de auditoría de seguridad V1.2

## Resultado

'V1.2_STATIC_RECONCILED / RUNTIME_UNVERIFIED / NOT_PRODUCTION_READY'

V1.2 amplía la cobertura de V1.1 sin alterar el código de aplicación. Se conservaron las cifras estructurales reconciliadas: **3435 archivos first-party**, **163 rutas efectivas**, **11 módulos paciente**, **10 entrevista**, **40 Doctor**, **9 admin**, **170 pruebas/fixtures**, **31 recursos DB inferidos** y **170 documentos**. La nueva matriz de rutas tiene **163 filas** y la matriz de recursos **120**.

## Reconciliación de hallazgos

- Hallazgos V1.1 conservados: **18** ('SEC-DOC-001' a 'TEST-018', incluyendo cobertura y gobierno).
- Nuevos hallazgos de vulnerabilidad confirmados por V1.2: **0**; no se convirtió una brecha estática en explotación sin runtime.
- Duplicados creados por V1.2: **0**; las observaciones se referencian a 'SEC-DOC-001', 'SEC-DOC-002', 'SEC-FILE-003', 'SEC-CSRF-004', 'SEC-SQL-005', 'CLIN-GET-006', 'SEC-AUTH-007', 'AI-PRIV-008' y 'QA-RUNTIME-010'.
- Matrices nuevas: 'route_security_matrix_v1_2.csv' y 'resource_authorization_matrix_v1_2.csv'.

## Cobertura por control

| Control | Estado V1.2 |
|---|---|
| Declaración vs ruta efectiva | PASS_STATIC: 136/163 conciliadas; clausura común expandida explícitamente |
| Documentación de filas de ruta | PASS_STATIC: 163/163 |
| Autenticación | PARTIAL_STATIC: guardas de controlador; middleware de registro no declarado; runtime pendiente |
| Ownership | PARTIAL_STATIC: 120 recursos; casos cruzados pendientes |
| CSRF | PARTIAL_STATIC: 12 rutas con validación localizada; 66 no demostradas |
| GET side effects | UNVERIFIED_RUNTIME: 52 rutas potenciales |
| SQL | PARTIAL_STATIC: superficie mixta/potencial; sin ejecución |
| PDF/descargas | OPEN: rutas identificadas; ownership documental pendiente |
| Errores | PARTIAL_STATIC; HTTP/status/cuerpo no comprobados |

## Dependencias críticas

1. Entorno sintético aislado y sesión reproducible.
2. Dos pacientes/clínicas sintéticos para pruebas A/B.
3. Fixtures de recursos, documentos, recetas externas y COFEPRIS con propietario explícito.
4. Contrato CSRF para todos los POST clínicos.
5. Decisión clínica sobre cualquier escritura durante GET antes de habilitar pruebas de recorrido.

## No modificados

'APPLICATION_CODE_MODIFIED: NO'; 'DATABASE_MODIFIED: NO'; 'HTTP_EXECUTED: NO'; 'ENV_MODIFIED: NO'; 'COMMITS: NONE'; 'DEPLOYMENTS: NONE'.

El cierre visual/funcional histórico no se interpreta como autorización, privacidad o seguridad de producción.

## Addendum posterior V1.3 — compuerta de evidencia crítica

V1.3 preserva el resultado V1.2 y no cambia sus matrices ni sus 18 hallazgos. La revisión focal de los dos P0 y ocho P1 confirmó evidencia estática trazable, pero no produjo evidencia runtime. Las cifras quedan reconciliadas así: 12 CSRF `YES_STATIC`, 66 `UNVERIFIED` y 85 no aplicables; 114 superficies SQL potencialmente mixtas y 27 no verificadas; cero escrituras GET confirmadas por lectura estática frente a 52 potenciales y 9 genéricas no verificadas; y 120 recursos con 1 `YES_STATIC_GUARD_PARTIAL`, 109 `PARTIAL_STATIC_GUARD`, 8 ausentes/no verificables y 2 `UNVERIFIED`.

La diferencia entre referencias parciales de ruta y el resumen 1/8/2 se documenta en `22_CRITICAL_FINDINGS_EVIDENCE_V1_3.md`; no se alteró silenciosamente ningún total. `P04-AUTH-20261014121758` continúa siendo evidencia histórica reportada, no una ejecución V1.3. Los lotes, decisiones y estrategia runtime están en los documentos 23–25. Estado posterior: `PARTIAL_STATIC / EVIDENCE_RECONCILED / RUNTIME_UNVERIFIED / NOT_PRODUCTION_READY`.
