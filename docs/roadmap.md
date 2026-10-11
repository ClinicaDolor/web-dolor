# Roadmap de modernización

## Estado actual

La Iteración 3 cierra la evidencia visual, verifica autorización en fixtures aislados, corrige el KPI por clínica y audita SEO sin implementarlo. Se mantiene el stack actual y se evita modificar datos clínicos productivos.

## Fases

### Fase 1 — Dashboards y base visual

Estado: completada en esta iteración.

- Sistema visual compartido.
- Dashboard médico responsive.
- Dashboard paciente responsive y accesible.
- Validación en 375, 768, 1024 y 1440 px.
- Corrección de warning de porcentajes sin datos.

### Fase 2 — Seguridad crítica

Estado: parcialmente verificada; implementación restante bloquea publicación clínica.

- CSRF para POST JSON y multipart.
- Sustitución progresiva de SQL concatenado por consultas parametrizadas.
- Archivos clínicos fuera del webroot y descarga autorizada.
- Auditoría de lecturas y cambios clínicos.
- Fixtures sintéticos versionados y pruebas cruzadas ejecutadas para dos médicos, dos clínicas y dos pacientes.
- Propiedad de archivos clínicos y autorización por expediente.

### Fase 3 — Integridad clínica y operación

Estado: pendiente.

- Versionado de notas y addendas.
- Migraciones reproducibles.
- Backups cifrados y restauración periódica.
- Observabilidad, alertas y continuidad operativa.

### Fase 4 — SEO de la web pública

Estado: auditado; no implementado.

El alcance SEO será exclusivamente http://clinica.test/:

- SEO técnico: títulos, metadatos, canonical, sitemap, robots, enlaces y errores HTTP.
- Contenido médico: estructura temática, claridad, evidencia editorial y revisión clínica.
- Indexación: cobertura, exclusiones, páginas duplicadas y datos estructurados.
- Rendimiento: Core Web Vitals, peso de recursos, caché e imágenes.
- Posicionamiento local: datos institucionales, contacto, ubicación y consistencia de la entidad.

Los dashboards privados, rutas de clínica, rutas de paciente y contenido detrás de sesión quedan excluidos de indexación y de cualquier estrategia SEO.

## Pendientes para el 12 de octubre de 2026

- Cerrar CSRF, SQL heredado, auditoría, integridad de notas, respaldos y autorización de archivos.
- Completar revisión visual aprobatoria de ambas áreas sin ampliar a formularios internos.
- Decidir y ejecutar, con autorización separada, el backlog SEO sólo de la web pública.
