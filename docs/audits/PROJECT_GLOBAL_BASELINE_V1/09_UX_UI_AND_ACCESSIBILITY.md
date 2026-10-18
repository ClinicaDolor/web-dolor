# 09 — UX/UI y accesibilidad

## Sistema visual

`public/assets/css/app.css` concentra tokens y estilos de áreas Doctor/Paciente; hay vistas modernizadas con clases scoped, footer Doctor y patrones de tarjetas, modales, tablas y shell. `docs/UI_DESIGN_CLOSURE_MASTER.md` registra aprobaciones visuales D/P, pero también limita pruebas de navegador, consola, responsive o autorización. Ese registro se considera histórico y no sustituye esta auditoría.

## Hallazgos de consistencia

- Hay múltiples shells/footers heredados (`footer-mvsd.php` y `footer-doctor.php`) y componentes incluidos: requieren matriz de dependencias para evitar scripts duplicados.
- Tablas Simple-DataTables, `height/fixedHeight`, wrappers y scrollbars son riesgos recurrentes ya documentados; falta una comprobación global actual.
- Las acciones de distribución, cierres de modal, botones de regreso y sidebar activo tienen correcciones focales, pero no un contrato común aplicado a todo el sistema.
- Formularios clínicos y editores Quill/canvas/mapas requieren pruebas de foco, teclado, contraste, zoom y touch.

## Accesibilidad

La revisión estática encontró atributos ARIA y focos en componentes recientes, pero no permite certificar orden de tabulación, lector de pantalla, contraste computado, Escape, pointer/touch, audio ni contenido dinámico. Deben medirse WCAG aplicable y documentarse excepciones clínicas.

## Estado

`PARTIAL_STATIC`; sin capturas nuevas ni navegador. No se modificó CSS.
