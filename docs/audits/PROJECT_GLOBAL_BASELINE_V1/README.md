# PROJECT GLOBAL BASELINE V1

Auditoría global estática y documental de Clínica del Dolor. Fecha de corte: 2026-10-14. Rama observada: `Silvino`; HEAD observado: `59654e05dc9f931c21b5392238961bcddb3ab2e4`.

## Resultado ejecutivo

`PARTIAL_STATIC / RUNTIME_UNVERIFIED / NOT_PRODUCTION_READY`. Se revisaron el arranque, rutas, controladores, modelos, servicios, vistas, assets, pruebas y documentación con búsquedas automatizadas y lectura focalizada. No se accedió a MySQL, HTTP, navegador, Docker ni datos clínicos. El worktree tenía cambios locales antes de esta auditoría y se preservó.

## Índice

- [00 Executive summary](00_EXECUTIVE_SUMMARY.md)
- [01 Scope and evidence](01_AUDIT_SCOPE_AND_EVIDENCE.md)
- [02 Repository and architecture](02_REPOSITORY_AND_ARCHITECTURE.md)
- [03 Functional inventory](03_GLOBAL_FUNCTIONAL_INVENTORY.md)
- [04 Patient and clinical workflows](04_PATIENT_AND_CLINICAL_WORKFLOWS.md)
- [05 Doctor, admin and roles](05_DOCTOR_ADMIN_AND_ROLES.md)
- [06 Security and privacy](06_SECURITY_AND_PRIVACY.md)
- [07 Database and data integrity](07_DATABASE_AND_DATA_INTEGRITY.md)
- [08 APIs, integrations and AI](08_APIS_INTEGRATIONS_AND_AI.md)
- [09 UX/UI and accessibility](09_UX_UI_AND_ACCESSIBILITY.md)
- [10 Testing and quality](10_TESTING_AND_QUALITY.md)
- [11 Performance and operations](11_PERFORMANCE_AND_OPERATIONS.md)
- [12 Compliance and governance](12_COMPLIANCE_AND_GOVERNANCE.md)
- [13 Master findings register](13_MASTER_FINDINGS_REGISTER.md)
- [14 Completion roadmap](14_PROJECT_COMPLETION_ROADMAP.md)
- [15 Production readiness checklist](15_PRODUCTION_READINESS_CHECKLIST.md)
- [16 Manual/runtime verification plan](16_MANUAL_AND_RUNTIME_VERIFICATION_PLAN.md)
- [17 Audit execution log](17_AUDIT_EXECUTION_LOG.md)

## Inventarios CSV

`files_and_directories.csv`, `modules.csv`, `routes.csv`, `models_and_tables.csv`, `tests.csv`, `findings.csv`, `pending.csv`, `documents.csv`, `traceability.csv`.

Los CSV no contienen credenciales, secretos, PIN, hashes ni registros personales. `UNVERIFIED` significa que el dato requiere ejecución o acceso no disponible en esta fase.

## Cómo leerlo

Los documentos distinguen evidencia del código actual, evidencia histórica de reportes previos y pruebas no ejecutadas en este entorno. Una aprobación visual de D/P no implica autorización clínica, seguridad ni preparación productiva.

## Reconciliación V1.1

La fase V1.1 añadió inventarios individuales y conservó el alcance estático:

- `18_AUDIT_COMPLETENESS_RECONCILIATION.md`: resultado, denominadores, limitaciones y ruta crítica.
- `files_manifest.csv`: 3,435 archivos propios candidatos al corte.
- `routes_complete.csv`: 163 rutas efectivas derivadas de 136 declaraciones.
- `functional_patient.csv`, `functional_interview.csv`, `functional_doctor.csv`, `functional_admin.csv` y `functional_shared.csv`.
- `tests_complete.csv`: 170 archivos de prueba/fixture/harness.
- `database_resources_complete.csv` y `documents_complete.csv`.
- `findings_reconciled.csv`, `roadmap_actions.csv` y `coverage_matrix.csv`.

La exhaustividad declarada es de inventario dentro de las exclusiones documentadas; la revisión semántica, la matriz de seguridad y la ejecución runtime permanecen parciales.
