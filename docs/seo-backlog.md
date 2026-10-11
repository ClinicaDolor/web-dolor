# Auditoría SEO de http://clinica.test/

Fecha: 2026-10-10. Alcance exclusivo: web pública. No se implementaron cambios SEO.

## Evidencia verificada

- Las 22 rutas públicas declaradas en app/Config/routes.php respondieron HTTP 200 localmente.
- La raíz contiene un title y un H1.
- robots.txt y sitemap.xml respondieron 404 y no existen en public/.
- No se detectaron link canonical, meta robots, Open Graph ni application/ld+json en las vistas públicas inspeccionadas.
- Las vistas usan meta description, pero la auditoría detectó una sola descripción repetida en las rutas revisadas.
- Se observaron sólo 7 títulos distintos para 22 rutas; varias páginas reutilizan el título institucional.
- La etiqueta viewport existe, pero usa el valor width=device-width initial-scale=1.0 sin coma.
- /clinica y /historia-clinica sin sesión respondieron 302 hacia sus accesos.
- No se obtuvieron métricas Core Web Vitals de laboratorio o campo; el tiempo local de respuesta no sustituye LCP, INP ni CLS.
- No se verificó indexación externa ni posicionamiento local.

## Backlog priorizado

| ID | Hallazgo | Prioridad | Estado | Criterio de cierre |
|---|---|---:|---|---|
| SEO-01 | robots.txt ausente | Alta | Pendiente | Respuesta 200, reglas públicas revisadas y dashboards excluidos |
| SEO-02 | sitemap.xml ausente | Alta | Pendiente | Sitemap válido, sólo URLs públicas canónicas |
| SEO-03 | titles y descriptions repetidos | Alta | Confirmado | Título y descripción únicos, clínicamente revisados por URL |
| SEO-04 | canonical ausente | Media | Confirmado | Canonical absoluto por ruta pública |
| SEO-05 | datos estructurados ausentes | Media | Confirmado | JSON-LD médico/local validado sin claims no comprobados |
| SEO-06 | SEO local no demostrado | Media | Pendiente | NAP, ubicación, horario y perfil local aprobados |
| SEO-07 | rendimiento móvil/CWV no medido | Alta | Pendiente | LCP, INP y CLS medidos en entorno representativo y presupuesto aprobado |
| SEO-08 | revisión de contenido médico | Alta | Pendiente | Revisión clínica, fuentes, fechas y aviso educativo por contenido |
| SEO-09 | accesibilidad y experiencia móvil pública | Media | Pendiente | Auditoría WCAG y pruebas en dispositivos reales |
| SEO-10 | dashboards privados | Crítica | Parcial | Redirección sin sesión verificada; falta validar noindex explícito y headers finales |

## Regla de alcance

No se recomienda indexar /clinica, /historia-clinica, /buscar, /pdf ni /descargar. Cualquier implementación SEO debe limitarse a la web pública y no debe exponer rutas ni datos clínicos.
