# 11 — Rendimiento y operaciones

## Observado

PDO usa prepares no emulados y UTF-8mb4; hay consultas `SELECT *`, generación HTML en modelos/vistas y tablas/consultas repetitivas. Servicios de resumen limitan algunas filas; no se midieron tiempos ni planes.

## Riesgos

- N+1 y consultas sin índices en módulos, tablas y dashboards.
- Generación PDF/FPDI y lectura de archivos grandes en request.
- cURL externo con timeout, pero sin evidencia de circuit breaker/cola.
- Logs y trazas clínicas potencialmente sensibles; retención y permisos no verificados.
- Falta de lock/transaction uniforme para doble captura/finalización.

## Operación pendiente

Definir Windows/XAMPP o hosting objetivo, HTTPS/HSTS, gestión de secretos, backups cifrados, restore ensayado, monitorización, alertas, rotación de logs, permisos de storage, cron/colas, RTO/RPO y runbooks. No se tocaron servicios ni configuraciones.
