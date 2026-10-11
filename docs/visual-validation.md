# Validación visual de dashboards — Iteración 2

## Entorno

- Fecha de validación: 2026-10-10.
- Servidor local aislado: 127.0.0.1.
- Sesiones: JWT sintéticos para un médico y un paciente ficticios, con identificadores fuera de los registros clínicos.
- La sesión es ficticia; en la captura original el KPI provino de la consulta existente de ClinicaModel y no se abrió ningún expediente ni se expuso PII. En Iteración 3 el KPI fue corregido y verificado con dos clínicas sintéticas: 1 paciente por clínica.
- Se usó un router HTTP temporal únicamente para servir activos estáticos durante la captura. No pertenece al proyecto y no modifica código, configuración ni base.

## Viewports y resultado

| Viewport | Dashboard médico | Dashboard paciente | Resultado |
|---:|---|---|---|
| 375 px | Capturado | Capturado | Una columna, menú móvil visible, búsqueda usable |
| 768 px | Capturado | Capturado | Sidebar compacto y una columna para conservar legibilidad |
| 1024 px | Capturado | Capturado | Sidebar compacto y grid de tres tarjetas |
| 1440 px | Capturado | Capturado | Sidebar completo, jerarquía estable y grid de tres columnas |

Las capturas se realizaron sobre las páginas renderizadas, después de esperar la desaparición del loader. La evidencia visual quedó mostrada en la sesión de trabajo.

## Estado para aprobación visual

Las ocho capturas reales quedan organizadas por dashboard y viewport en la evidencia de la sesión: médico 375, 768, 1024 y 1440 px; paciente 375, 768, 1024 y 1440 px. No se modificaron componentes aprobados en Iteración 3.

Pendientes visuales observados, sin cambio aplicado:

- Confirmar con usuarios médicos la densidad final de tarjetas y el texto del KPI.
- Revisar contraste y tamaño de texto en tablas clínicas internas antes de ampliar el sistema visual.
- Validar navegación con teclado y lector de pantalla en pantallas de expediente, fuera del alcance de dashboards.
- Ejecutar prueba real de tableta y móvil con dispositivos físicos; las capturas actuales son viewport de navegador.

## Hallazgos y refinamientos

- El botón de menú móvil ocupaba espacio, pero su icono no era distinguible. Se añadió una señal visual consistente y un nombre accesible.
- En 768 px, las tarjetas con nombres clínicos largos eran demasiado estrechas. El grid de dashboard pasa a una columna hasta 850 px.
- El sidebar tenía 276 px de ancho, pero el desplazamiento móvil era de 270 px y producía overflow horizontal. Se alineó el desplazamiento a 276 px.
- El dashboard paciente mostraba warnings PHP cuando un paciente nuevo no tenía respuestas. Se inicializaron porcentajes en cero sin cambiar sus fórmulas.
- No se detectaron errores de consola en los dashboards validados.

## Alcance deliberadamente excluido

- Formularios clínicos internos.
- Módulos de historia clínica.
- Sitio público.
- SEO y contenido indexable.
- Cambios de datos o fixtures persistentes.
