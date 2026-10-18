# 03 — Inventario funcional global

## Familias

| Área | Función observada | Superficie | Estado de auditoría |
|---|---|---|---|
| Web pública | páginas informativas, búsqueda web, login/landing | GET | estática revisada; contenido/SEO runtime UNVERIFIED |
| Portal Paciente | dashboard, acceso PIN, Historia Clínica tradicional P01–P11 | GET + POST/AJAX | contratos y rutas inventariados; runtime actual UNVERIFIED |
| Entrevista Conversacional | índice, interpretación, captura M02–M09, mapa y direct-submit | GET + POST | código y rutas revisados; DB, proveedor y experiencia UNVERIFIED |
| Doctor | dashboard, pacientes, expediente, módulos, notas, laboratorio, recetas, COFEPRIS, indicadores | GET + POST/AJAX | rutas y autorización estática; runtime actual UNVERIFIED |
| Documental | PDF receta interna/externa/COFEPRIS y descarga | POST/GET | riesgos de propiedad y descarga abiertos |
| Administración/roles | login clínico, permisos, clínica, indicadores y perfil | GET + POST | reglas inferidas; matriz completa de roles UNVERIFIED |

## P01–P11

P01 acceso; P02 dashboard; P03 ficha de identificación; P04 antecedentes familiares; P05 antecedentes personales no patológicos; P06 antecedentes personales quirúrgicos; P07 antecedentes personales patológicos; P08 medicación actual; P09 medicación para controlar el dolor; P10 procedimientos/tratamientos para controlar el dolor; P11 evaluación del dolor/mapa corporal. Nombres y rutas se contrastan con `routes.php`, vistas y `docs/UI_DESIGN_CLOSURE_MASTER.md`; no se asume que el estado visual equivale a cierre funcional.

## D01–D40

El inventario maestro registra pantallas Doctor D01–D40, con D02 actualmente `VALIDACIÓN` y el resto documentado históricamente como aprobaciones visuales según la fecha del documento. La auditoría actual no ratifica ese estado: cada ruta/controlador/vista debe volver a verificarse en navegador y autorización. D01–D09 cubren acceso/dashboard/expediente/módulos/PIN/nota; D10–D19 recetas, laboratorio, COFEPRIS y módulos; D20–D31 módulos clínicos; D32–D40 resúmenes, indicadores y vistas documentales según el inventario maestro.

## Contratos de cierre

Persistencia, autorización clínica, documentos, endpoints JSON, AJAX, finalización, errores y responsive son contratos independientes. Un “CERRADA” visual sólo prueba una aprobación visual histórica; no autoriza producción.
