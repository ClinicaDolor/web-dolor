# 00 — Resumen ejecutivo

## Dictamen

Estado del sistema al corte: **parcialmente auditado en estático; no listo para producción**. La aplicación tiene un front controller PHP, routing FastRoute, PDO/MySQL, JWT en cookie, áreas públicas, Paciente, Entrevista Conversacional, Doctor/administración, PDF, descargas y almacenamiento clínico. La sintaxis estática observada es saludable, pero faltan pruebas integradas actuales y cierres de seguridad que no pueden inferirse del código.

## Qué sí se comprobó

- Worktree, rama y HEAD registrados sin mutarlo.
- Inventario de `938` archivos tracked y `996` archivos no excluidos por la exploración amplia; conteo amplio: `245 PHP`, `131 JS/MJS`, `42 CSS`, `1 SQL`, `137 documentos/configuraciones`. El segundo conteo incluye assets, harnesses y artefactos locales; no es un denominador de código propio sin depuración.
- `php -l`: `156/156` archivos PHP bajo `app` y `public` PASS.
- `node --check`: `92/92` JS/MJS no minificados bajo `public/assets/js` PASS.
- `git diff --check`: PASS; las advertencias reportadas son de conversión LF/CRLF al tocar archivos existentes.
- Rutas actuales leídas de `app/Config/routes.php`: `136` registros `addRoute` textuales, con grupos y patrones compartidos; no se trató como número de pantallas.
- Existencia y relación estática de los principales controladores, modelos, servicios, vistas y suites de prueba.

## Riesgos bloqueantes o de alta prioridad

1. **Autorización documental incompleta:** `PdfController::pdfRecetaExterno()` carga una receta externa por ID sin una comprobación equivalente a `assertPatientAccess`; la propiedad clínica de `receta_medica_externos` sigue sin demostrarse. `RecetaExternoModel` mantiene una consulta global del catálogo.
2. **Descargas:** `DownloadController` valida sesión y path dentro de `public/storage`, pero no se evidencia autorización por paciente/documento ni allowlist de tipos; requiere pruebas y diseño de propiedad.
3. **CSRF inconsistente:** sólo se observó protección explícita en el flujo de resumen/P05 mediante token de captura; P04/P06–P11 y varios POST clínicos no muestran contrato CSRF equivalente en la revisión estática. Ver auditorías CSRF históricas, que permanecen sin cierre integrado.
4. **SQL heredado:** existen `query()` con interpolación de identificadores y consultas directas en modelos y vistas. Aunque algunos valores proceden de contexto, la superficie requiere parametrización, validación de columnas y pruebas de regresión.
5. **GET con efectos secundarios / legacy:** la documentación histórica registra siembras de filas durante GET en módulos Doctor; hay contradicción entre reportes posteriores y código/modelos actuales. Requiere prueba aislada y decisión clínica.
6. **Entrevista Conversacional/IA:** hay proveedores externos, claves en entorno, trazas y múltiples endpoints; el fail-closed y la minimización deben probarse con un entorno sintético y revisión de privacidad.
7. **Evidencia runtime:** no se ejecutaron HTTP, DB, navegador, consola, Network, audio audible, viewports ni persistencia en esta auditoría.

## Qué falta realmente para terminar

Cerrar la propiedad y autorización de cada documento/receta/COFEPRIS; estandarizar CSRF y parametrización; resolver o aceptar explícitamente los GET con escritura; ejecutar pruebas P01–P11, Entrevista y Doctor en una base sintética aislada; completar evidencia browser y accesibilidad; establecer backup/restore, logs y secretos para producción; reconciliar contratos y documentación; obtener revisión clínica, privacidad y regulatoria. La respuesta completa está en `14_PROJECT_COMPLETION_ROADMAP.md`.

## Alcance y no afirmaciones

No se modificó código de aplicación, configuración, esquema, DB ni Git. Sólo se crearán estos documentos e inventarios nuevos. La auditoría no certifica cumplimiento legal, seguridad ofensiva ni producción.

## Adenda V1.1 — conciliación de completitud

La identidad Git queda fijada en `59654e05dc9f931c21b5392238961bcddb3ab2e4` sobre la rama `Silvino`. El manifiesto individual documenta 3,435 archivos propios candidatos; la enumeración efectiva de rutas documenta 163 filas, frente a 136 declaraciones textuales; y se individualizan 11 módulos de paciente, 10 componentes de entrevista, 40 vistas Doctor, nueve áreas administrativas, 170 archivos de pruebas, 31 recursos de datos estáticos y 170 documentos.

PHP lint actual: 160/160 PASS. Sintaxis JS/MJS no minificada de `public/assets/js`: 92/92 PASS. `git diff --check`: código 0; los avisos LF/CRLF son preexistentes.

La línea base queda en `PARTIAL_STATIC / RUNTIME_UNVERIFIED / NOT_PRODUCTION_READY`. Se corrigieron los denominadores y la trazabilidad documental, no los riesgos de aplicación. El detalle está en `18_AUDIT_COMPLETENESS_RECONCILIATION.md`.
