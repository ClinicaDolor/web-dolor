# Sprint UI-02 — Inventario visual y validación

Fecha de revisión: 2026-10-10.

Este documento registra el lote visual aplicado a las vistas pendientes. Las
afirmaciones se basan en las rutas, vistas y clases CSS observadas en el
repositorio y en una ejecución local con fixtures sintéticos aislados.

## Matriz de vistas

| Ruta o grupo | Estado anterior comprobado | Cambio realizado | Responsive / funciones verificadas | Pendientes |
|---|---|---|---|---|
| `/clinica/paciente/{idPaciente}` — Historia Clínica | Toolbar y cuerpo demasiado próximos al divisor | Padding scoped del cuerpo, separación toolbar-tabla y filas más compactas en `.patient-record-history-card` | 1440×900, 1280×720, 768×1024 y 375×667; 9 filas, porcentajes y dropdown `Acciones` visibles; sin overflow | Escrituras clínicas y autorización cruzada completa: NO VERIFICADO en este sprint |
| `/clinica/paciente/pin/{idPaciente}` | Shell y tabla heredaban presentación antigua; en 375 px había overflow por tabla intrínseca, autocomplete y botón del encabezado | Shell/footer compartidos y reglas scoped para envolver las cuatro columnas en móvil | 1440×900, 1280×720, 768×1024 y 375×667; sin overflow después del ajuste; generación de PIN: NO EJECUTADA | Validar persistencia y límites de PIN en una actividad funcional separada |
| `/clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}` | Formulario con shell visual no unificado | Clase `doctor-clinical-view`, tarjetas, controles y footer compartidos | 4 viewports; render y ausencia de overflow | Guardado y edición: NO VERIFICADOS |
| `/clinica/laboratorio/paciente/{idPaciente}` | Vista clínica pendiente de unificación | Clase `doctor-clinical-view`, tarjetas, tablas, controles y footer compartidos | Captura sintética en escritorio; 4 viewports en el grupo médico; sin overflow en la ejecución válida | Descarga y persistencia documental: NO VERIFICADAS y bloqueadas por ACT-05C-02 |
| `/clinica/receta/paciente/{idPaciente}` | Vista clínica pendiente de unificación | Shell visual compartido y controles delimitados al área médica | 4 viewports; render y ausencia de overflow | Guardado/PDF: NO VERIFICADOS |
| `/clinica/cofepris/paciente/{idPaciente}` | Vista clínica pendiente de unificación | Shell visual compartido y controles delimitados al área médica | 4 viewports; render y ausencia de overflow | Guardado y documentos: NO VERIFICADOS |
| `/clinica/recetas`, `/clinica/cofepris` | La ruta respondió con estado genérico de solicitud no disponible en el fixture | No se modificó backend ni contrato para forzar un PASS | NO VERIFICADO; 8 comprobaciones devolvieron estado genérico en la base sintética | Revisar la infraestructura/fixture de estas consultas antes de una auditoría visual concluyente |
| `/clinica/modulos/paciente/{idPaciente}` | Historia clínica del médico pendiente de lote | Shell, footer y clase visual compartida | 4 viewports; título, navegación y ausencia de overflow | Edición/resumen de módulos: NO VERIFICADOS |
| Otras vistas médicas (`/clinica/recetas/nueva`, `/clinica/perfil`, detalle de laboratorio, nota, receta, COFEPRIS y configuraciones) | Variantes con shell heredado | Se añadieron clases de alcance y footer compartido donde la vista real lo permite | Rutas principales cargadas con fixtures; rutas menos frecuentes no tienen evidencia completa en los 4 viewports | Validación funcional individual y estados de error pendientes |
| `/historia-clinica` | Dashboard ya aprobado; se requería evidencia del catálogo real | Se conserva `dashboard-patient` y los componentes compartidos | 1440×900, 1280×720, 768×1024 y 375×667; 9 tarjetas, 9 enlaces/estados, sin overflow | Acciones de guardado de módulos: NO VERIFICADAS |
| Nueve rutas `/historia-clinica/{modulo}/paciente/{idPaciente}` | Formularios clínicos con presentación heterogénea | Clase `patient-clinical-form`, tarjetas, labels, controles, foco, botones y footer compartidos | 36 combinaciones (9 formularios × 4 viewports); sin overflow, footer único y dimensiones reales coincidentes | Pruebas de cada pregunta, guardado y finalización: NO VERIFICADAS |

## Inventario de módulos paciente

El catálogo real observado contiene estos nueve slugs y nombres:

1. `ficha-identificiacion` — Ficha de identificación del paciente.
2. `antecedentes-familiares` — Antecedentes familiares.
3. `antecedentes-personales-no-patologicos` — Antecedentes Personales No Patológicos.
4. `antecedentes-personales-quirurgicos` — Antecedentes Personales Quirúrgicos.
5. `antecedentes-personales-patologicos` — Antecedentes Personales Patológicos.
6. `medicacion-actual` — Medicación Actual.
7. `medicacion-control-dolor` — Medicamentos que ha utilizado para controlar el dolor.
8. `procedimientos-control-dolor` — Procedimientos que ha utilizado para controlar el dolor.
9. `evaluacion-dolor` — Evaluación del dolor.

Las dos primeras tarjetas del fixture aparecieron como completadas y sin
`href`; las siete restantes conservaron su enlace real. Las nueve rutas
directas fueron comprobadas en modo lectura con el paciente sintético.

## Cambios transversales

- `public/assets/css/app.css`: tokens y reglas de presentación delimitadas por
  `doctor-clinical-view`, `patient-clinical-form` y
  `doctor-patient-record-page`; el ajuste del PIN móvil sólo afecta esa vista
  y el autocomplete del shell privado.
- Vistas médicas pendientes: se añadió el shell visual compartido y
  `footer-doctor.php` sin cambiar rutas, consultas o persistencia.
- Nueve formularios paciente: se añadió la misma clase de shell y footer; se
  conservaron campos, IDs, JavaScript y contratos clínicos.
- Tres formularios tenían un acceso inseguro a `temas[0]` cuando el estado
  persistido de `localStorage` no correspondía al catálogo de temas. Se añadió
  un fallback defensivo sin cambiar preguntas ni cálculos.

## Límites de esta entrega

No se modificaron endpoints de descargas, almacenamiento documental,
autenticación, permisos, tablas, migraciones, reglas clínicas ni web pública.
Las vulnerabilidades críticas de autorización y almacenamiento documental de
ACT-05C-02 continúan abiertas y bloquean datos clínicos reales.

## Resolución UI-03 de rutas no verificadas

En UI-02, `/clinica/recetas` y `/clinica/cofepris` quedaron NO VERIFICADAS por
tablas ausentes en el fixture. UI-03 confirmó sus contratos en
`routes.php`, `ClinicaController`, `HomeController`, `RecetaExternoModel` y
`CofeprisModel`, añadió únicamente las tablas sintéticas
`receta_medica_externos` y `cofepris_folio`, y volvió a comprobar ambas rutas
en 1440×900, 1280×720, 768×1024 y 375×667. Resultado: PASS en modo lectura,
con datos sintéticos y estados vacíos donde corresponde. Las operaciones de
guardado, PDF, folios y archivos siguen sin verificarse.
