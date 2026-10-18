# 15 — Checklist de preparación productiva

| Control | Estado actual | Evidencia requerida |
|---|---|---|
| Auth/session/JWT | PARTIAL_STATIC | pruebas expiración, logout, fixation, rate limit |
| Ownership por paciente/clínica | FAIL/OPEN | matriz propia/cruzada para todas las rutas |
| Receta externa/PDF/download | FAIL/OPEN | fail-closed, ownership, tests |
| CSRF uniforme | FAIL/OPEN | token policy y tests por POST/AJAX |
| SQL parametrizado | PARTIAL/OPEN | scan limpio y regresión |
| GET sin escritura | UNVERIFIED | instrumentación y DB sintética |
| P01–P11 | UNVERIFIED actual | matriz funcional completa |
| Entrevista/IA/STT | UNVERIFIED | privacidad, provider/fallback, persistencia |
| Doctor/admin | PARTIAL_STATIC | roles, cross-clinic, documentos |
| PHP/JS lint | PASS static | CI reproducible |
| Browser/accessibility | UNVERIFIED actual | captures, console, focus, WCAG review |
| DB schema/backups/restore | UNVERIFIED | DDL y drill |
| Secrets/HTTPS/logging | UNVERIFIED | deployment evidence |
| Compliance/sign-off | PENDING | aprobación clínica, seguridad, privacidad/legal |

Resultado: **NO LISTO PARA PRODUCCIÓN**.
