# TH-DOCTOR-LAYOUT-TOGGLE-GLOBAL-AUDIT-AND-CLEANUP-10

## Estado

**STATUS: PARTIAL**

La auditoría y la limpieza selectiva están implementadas mediante inspección
estática. La comprobación visual y funcional en navegador real queda
**NO VERIFICADA** porque este entorno no tiene una sesión Chrome/XAMPP
reproducible.

## Identificación real

El control auditado sí corresponde a `toggleSize()`, definida en
`public/assets/js/loader.js` como función global. La función recorre
`.resizable`, alterna la clase Bootstrap `col-sm-12` y, cuando existe
`data-index`, restaura el orden inline del bloque.

No utiliza `localStorage` ni `sessionStorage`; no existe una preferencia
persistida de distribución que deba migrarse o limpiarse.

## Clasificación por vista

| Vista | Estructura real | Clasificación | Acción |
|---|---|---|---|
| `app/views/clinica/recetas.php` | Dos columnas `col-sm-6 resizable` | FUNCIONAL Y NECESARIO | Conservado |
| `app/views/clinica/receta-externo.php` | Dos columnas `col-sm-6 resizable` | FUNCIONAL Y NECESARIO | Conservado |
| `app/views/clinica/nota-subsecuente.php` | Dos columnas `col-sm-6 resizable` | FUNCIONAL Y NECESARIO | Conservado |
| `app/views/clinica/pacientes-detalle.php` | Dos secciones `col-12` | SIN EFECTO | Eliminado |
| `app/views/clinica/pacientes-cofepris.php` | Un único bloque `col-12` | SIN EFECTO | Eliminado |
| `app/views/clinica/pacientes-laboratorio.php` | Un único bloque `col-12` | SIN EFECTO | Eliminado |
| `app/views/clinica/pacientes-nota-subsecuente.php` | Un único bloque `col-12` | SIN EFECTO | Eliminado |
| `app/views/clinica/pacientes-nota-subsecuente-nueva.php` | Complementos apilados en `100%` | REDUNDANTE | Eliminado |
| `app/views/clinica/pacientes-recetas.php` | Un único bloque `col-12` | SIN EFECTO | Eliminado |
| `app/views/clinica/recetas-nuevas.php` | Un único bloque `col-12`, sin control | HEREDADO/OBSOLETO | Clase eliminada |

No se encontraron otras apariciones de `onclick="toggleSize()"` en las vistas
Doctor actuales. Las vistas de Historia Clínica Doctor y los módulos del
Portal Paciente no contienen este control.

## Limpieza realizada

Se eliminaron los botones y los atributos `toggleButton`/`toggleIcon` de las
siete vistas sin efecto. También se retiraron sus clases `resizable` cuando ya
no tenían consumidor y se eliminaron los estilos CSS exclusivos de:

- `patient-record-layout-toggle`;
- `doctor-patient-cofepris-toggle`;
- `doctor-lab-toggle`;
- `doctor-note-toggle`;
- `doctor-recipe-toggle`.

La función global `toggleSize()` se conserva porque sigue siendo consumida por
las tres vistas funcionales. También se conservan sus estilos para
`doctor-followup-toggle`, `doctor-recipes-toggle` y
`doctor-external-recipe-layout-toggle`.

No se alteraron endpoints, rutas, formularios clínicos, consultas, permisos,
autenticación, navegación, tablas ni datos.

## Topbar, sidebar y responsive

- El sidebar no fue modificado.
- Las acciones restantes de las vistas funcionales conservan su icono
  `columns`, `aria-label`, `title`, foco y comportamiento responsive.
- En expediente y COFEPRIS/laboratorio/notas/recetas del paciente, la acción
  restante del hero conserva su alineación sin reservar espacio para un toggle.
- No se introdujeron anchos artificiales ni cambios en las tablas o formularios.

## Validación

| Comprobación | Resultado |
|---|---|
| Identificación de `toggleSize()` | PASS estático |
| Inventario de apariciones Doctor | PASS estático |
| Clasificación por vista | PASS estático |
| Consumidores JavaScript | PASS estático |
| Dependencias CSS | PASS estático |
| Código huérfano de controles retirados | PASS estático |
| PHP lint de vistas modificadas y contrato | PASS |
| Sintaxis JavaScript | PASS de scripts/inline revisados |
| Contrato focalizado 10 | PASS estático |
| Contratos relevantes de Nota 09C/09D/09E | PASS estático |
| Revisión CSS | PASS; selectores retirados sin referencias restantes |
| `git diff --check` | PASS; avisos históricos de LF/CRLF |
| Navegación clínica y endpoints | NO VERIFICADO en runtime; no se modificaron |
| Responsive renderizado | NO VERIFICADO; sin navegador/XAMPP |
| Consola y medición visual | NO VERIFICADO |

## Archivos de esta intervención

Se modificaron únicamente vistas Doctor y CSS asociado, además del contrato y
este informe:

- `app/views/clinica/pacientes-detalle.php`
- `app/views/clinica/pacientes-cofepris.php`
- `app/views/clinica/pacientes-laboratorio.php`
- `app/views/clinica/pacientes-nota-subsecuente.php`
- `app/views/clinica/pacientes-nota-subsecuente-nueva.php`
- `app/views/clinica/partials/nota-subsecuente-editor.php`
- `app/views/clinica/pacientes-recetas.php`
- `app/views/clinica/recetas-nuevas.php`
- `public/assets/css/app.css`
- `tests/doctor/layout-toggle-global-contract-10.php`
- `docs/audits/TH_DOCTOR_LAYOUT_TOGGLE_GLOBAL_AUDIT_AND_CLEANUP_10.md`

No se modificaron backend, modelos, controladores, rutas, endpoints, base de
datos, Portal Paciente, web pública ni `.env`. No hubo commits ni despliegues.

## Resultado resumido

```yaml
TH_DOCTOR_LAYOUT_TOGGLE_GLOBAL_AUDIT_AND_CLEANUP_10:
  STATUS: PARTIAL
  BUTTON_IDENTIFIED: toggleSize() en public/assets/js/loader.js
  BUTTON_FUNCTION: alterna col-sm-12 sobre .resizable y restaura data-index
  TOGGLESIZE_RELATED: PASS_STATIC
  DOCTOR_VIEWS_AUDITED: 10
  BUTTON_OCCURRENCES: 10 antes / 3 después
  FUNCTIONAL_OCCURRENCES: 3
  REDUNDANT_OCCURRENCES: 1
  OBSOLETE_OCCURRENCES: 1
  NO_EFFECT_OCCURRENCES: 5
  UNVERIFIED_OCCURRENCES: 0
  BUTTONS_REMOVED: 7
  BUTTONS_PRESERVED: 3
  FILES_WITH_REMOVALS: 8
  UNUSED_JS_REMOVED: NO; toggleSize() conserva consumidores
  UNUSED_CSS_REMOVED: PASS_STATIC
  DASHBOARD_LAYOUT_PRESERVED: PASS_STATIC
  TOPBAR_ALIGNMENT: PASS_STATIC
  SIDEBAR_FUNCTIONALITY: PASS_STATIC_UNCHANGED
  TABLE_LAYOUT_PRESERVED: PASS_STATIC
  FORM_LAYOUT_PRESERVED: PASS_STATIC
  RESPONSIVE_PRESERVED: PASS_STATIC
  ACCESSIBILITY_PRESERVED: PASS_STATIC
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  FOCUSED_TESTS: PASS_STATIC
  CSS_REVIEW: PASS
  DIFF_CHECK: PASS
  BROWSER_VALIDATION: NO_VERIFICADO
  BACKEND_MODIFIED: NO
  DATABASE_MODIFIED: NO
  SCHEMA_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
```

## Riesgos residuales

La medición geométrica y la comprobación de consola, teclado y responsive
requieren una sesión visual real. Las limitaciones funcionales y de seguridad
preexistentes del área Doctor no se modificaron en esta intervención.
