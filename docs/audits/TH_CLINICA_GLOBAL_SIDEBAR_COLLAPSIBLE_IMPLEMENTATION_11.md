# TH-CLINICA-GLOBAL-SIDEBAR-COLLAPSIBLE-IMPLEMENTATION-11

## Estado

**STATUS: PARTIAL**

Se implementó el comportamiento global del sidebar compartido para los shells
Doctor y Paciente. La validación visual renderizada queda **NO VERIFICADA**:
este entorno no dispone de una sesión Chrome/XAMPP reproducible para capturar
los viewports solicitados.

## Identificación

El sidebar no está duplicado por vista. Se genera mediante
`app/Helpers/Sidebar.php` y se inyecta como `$data['sidebar']`. El mismo
componente se utiliza en las páginas autenticadas Doctor y Paciente. El
control de la barra superior es `.sidebar-toggler`; en las vistas con
`search-bar-doctor.php` procede del componente compartido y en las vistas de
Historia Clínica Paciente conserva el mismo contrato de clase y atributos.

El modo `toggleSize()` de distribución de columnas es independiente. Se
conservó en `public/assets/js/loader.js` y no se reutilizó ni modificó para el
sidebar.

La revisión estática no encontró persistencia previa de la preferencia del
sidebar (`localStorage`/`sessionStorage`). No se añadió almacenamiento nuevo;
el estado compacto dura la página actual y se restablece al recargar, evitando
mezclar una preferencia visual con la sesión clínica.

## Inventario real de consumidores

Se localizaron **53 vistas autenticadas** que reciben `$data['sidebar']`.
Todas renderizan el componente compartido `App\Helpers\Sidebar`, por lo que
conservan la misma lista de enlaces, URLs, iconos y cálculo del elemento
activo. El controlador `public/assets/js/main.js` está presente en todas las
vistas después de adaptar Entrevista Conversacional.

| Área | Vistas consumidoras | Layout/controlador | Intervención |
|---|---:|---|---|
| Doctor — núcleo clínico y configuración | 24 | `app/Helpers/Sidebar.php` + `components/search-bar-doctor.php` | Estado global |
| Doctor — Historia Clínica | 9 | `app/Helpers/Sidebar.php` + topbar Doctor | Estado global |
| Doctor — resúmenes | 9 | `app/Helpers/Sidebar.php` + `components/search-bar-doctor.php` | Estado global |
| Paciente — Historia Clínica tradicional y portal | 10 | `app/Helpers/Sidebar.php` + topbar Paciente | Estado global |
| Paciente — Entrevista Conversacional | 1 | `app/Helpers/Sidebar.php` + topbar específico | Adaptador mínimo |

Los 24 consumidores Doctor comprenden dashboard, pacientes, expediente,
Historia Clínica, PIN, notas, recetas, laboratorio, COFEPRIS, resumen clínico,
perfil y vistas de alta/edición. Las 9 vistas de Historia Clínica Doctor y 9
vistas de resumen corresponden a los módulos clínicos ya existentes. Las 10
vistas Paciente restantes comprenden dashboard y módulos tradicionales.

## Implementación realizada

- `app/Helpers/Sidebar.php`
  - Conserva las rutas, títulos, iconos, elemento activo y `aria-current`.
  - Añade `title` como tooltip nativo y conserva `aria-label` para que el
    nombre siga disponible cuando el texto se oculta visualmente.
- `public/assets/js/main.js`
  - En escritorio, `.sidebar-toggler` alterna `.sidebar-compact` sin cerrar la
    navegación.
  - Sincroniza `aria-pressed`, `aria-expanded`, `aria-label` y `title`.
  - En móvil mantiene el comportamiento de drawer mediante `.active`.
  - Sincroniza el shell al redimensionar la ventana.
- `public/assets/css/app.css`
  - Añade la variante compacta de 84 px.
  - Reduce y centra el logo, centra iconos y oculta visualmente etiquetas sin
    eliminarlas del DOM.
  - Conserva el estado activo, foco y tooltip nativo sin duplicar tooltips CSS.
  - Refluye `#main` de 276/220 px a 84 px, según el estado y breakpoint.
  - Mantiene el drawer móvil en 276 px.
- `app/views/paciente/entrevista.php`
  - Conserva el topbar específico, pero reutiliza la clase `.sidebar-toggler` y
    el controlador compartido.
  - Se retiró únicamente el listener y CSS paralelo de `is-compact`; no se
    tocó el flujo de conversación, voz ni mapa.
- `tests/doctor/sidebar-collapsible-contract-11.php`
  - Contrato estático sin HTTP, PDO ni ejecución de la aplicación.

## Preservación funcional

No se modificaron rutas, URLs, permisos, autenticación, controladores,
modelos, endpoints, CSRF, formularios ni persistencia. El HTML de cada enlace
conserva su `href`; sólo se añadieron atributos presentacionales y de
accesibilidad. No se modificó `toggleSize()` ni sus tres consumidores reales.

## Matriz de comportamiento

| Contexto | Estado expandido | Acción del control | Estado compacto/cerrado |
|---|---|---|---|
| Escritorio >= 768 px | Sidebar visible con logo, iconos y etiquetas | Alterna `.sidebar-compact` | Sidebar visible de 84 px, iconos centrados y etiquetas accesibles |
| Móvil < 768 px | Drawer abierto mediante `.active` | Abre/cierra el drawer | Drawer fuera de pantalla; overlay/flujo existente no se sustituye |

Entrevista Conversacional tenía previamente un adaptador propio de 76 px y el
estado `is-compact`. Se identificó como la única variante. Ahora usa el mismo
estado `sidebar-compact`, ancho de 84 px, atributos ARIA y controlador
compartido.

El código existente no define un overlay nuevo; se conserva el patrón de drawer
actual y su botón interno de cierre.

## Validación

| Comprobación | Resultado |
|---|---|
| Inventario de componente compartido | PASS estático |
| Contrato Doctor/Paciente del sidebar | PASS estático |
| Rutas y atributos `href` | PASS por preservación del render compartido |
| Nombres accesibles, tooltip y estado activo | PASS estático |
| Reflujo desktop expandido/compacto | PASS estático |
| Drawer móvil | PASS estático; render real NO VERIFICADO |
| Entrevista sin toggle paralelo | PASS estático |
| Separación respecto de `toggleSize()` | PASS estático |
| PHP lint | PASS |
| Sintaxis JavaScript | PASS |
| CSS y `git diff --check` | PASS; avisos LF/CRLF históricos del worktree |
| Browser 1440/1280/1024/768/430/375 | NO VERIFICADO |
| Capturas, consola y Network | NO VERIFICADO |

## Riesgos y límites

La comprobación visual real del ancho, reflujo, tooltip, foco y drawer requiere
Chrome/Edge con una sesión autenticada reproducible. No se modificó el entorno
personal del usuario ni se accedió a la base de datos. El estado final es
`PARTIAL` hasta esa comprobación.

## Resultado

```yaml
TH_CLINICA_GLOBAL_SIDEBAR_COLLAPSIBLE_IMPLEMENTATION_11:
  STATUS: PARTIAL
  SHARED_COMPONENT: app/Helpers/Sidebar.php
  AUTHENTICATED_SIDEBAR_VIEWS: 53
  DOCTOR_VIEWS_UPDATED: 42
  PATIENT_VIEWS_UPDATED: 11
  DESKTOP_COLLAPSE: IMPLEMENTED_STATIC
  COMPACT_WIDTH: 84px
  LABELS_PRESERVED: YES
  ACTIVE_STATE_PRESERVED: YES
  MOBILE_DRAWER_PRESERVED: YES
  TOGGLESIZE_TOUCHED: NO
  ROUTES_AUTH_PERMISSIONS_CHANGED: NO
  DATABASE_ACCESSED: NO
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  FOCUSED_CONTRACT: PASS_STATIC
  CSS_REVIEW: PASS_STATIC
  BROWSER_VALIDATION: NO_VERIFICADO
  COMMITS: NONE
  DEPLOYMENTS: NONE
```
