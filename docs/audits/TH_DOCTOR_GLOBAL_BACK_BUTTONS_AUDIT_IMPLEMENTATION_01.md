# TH-DOCTOR-GLOBAL-BACK-BUTTONS-AUDIT-IMPLEMENTATION-01

## Resultado

~~~yaml
STATUS: PARTIAL_STATIC
SCOPE: authenticated Doctor views
BACK_LINKS_INVENTORIED: 24
BACK_LINKS_STANDARDIZED: 24
BROWSER_COMPUTED_STYLE_VERIFIED: NO
BROWSER_VALIDATION: NOT_EXECUTED
DATABASE_ACCESSED: NO
BACKEND_MODIFIED: NO
ROUTES_MODIFIED: NO
MODELS_MODIFIED: NO
MIDDLEWARE_MODIFIED: NO
PATIENT_VIEWS_MODIFIED: NO
CONVERSATIONAL_INTERVIEW_MODIFIED: NO
COMMITS: NONE
DEPLOYMENTS: NONE
~~~

La auditoría estática identificó 24 enlaces navegables de regreso en las vistas Doctor incluidas en el inventario. Todos conservan su href, texto contextual, icono arrow-left y mecanismo de navegación; únicamente incorporan el atributo visual compartido data-doctor-back-action.

## Causa confirmada por inspección estática

No existía un contrato visual único para los enlaces de regreso. Las vistas combinaban clases específicas (doctor-page-hero-action, acciones de módulos de Historia Clínica, clinical-summary-back y patient-record-back) con reglas diferentes. En varios estados interactivos, las reglas existentes devolvían el fondo a var(--clinic-primary-soft) o a variantes lavanda.

La causa del fondo no se confirmó mediante estilos computados del navegador porque el navegador local de XAMPP no estuvo disponible para esta ejecución. La comprobación de superficies expuso únicamente IAB/MCP Apps, sin Chrome o Edge nativo y sin una pestaña conectada a XAMPP. Por tanto, el resultado runtime permanece pendiente.

## Implementación

Se añadió data-doctor-back-action sólo a los 24 enlaces inventariados. Se agregó al final de public/assets/css/app.css un contrato scoped a:

~~~css
body.app-shell-body.app-shell-doctor a[data-doctor-back-action][href]
~~~

El contrato establece:

- fondo #FFFFFF en estado normal e interactivo;
- background-image: none;
- texto e icono con var(--clinic-primary-dark);
- borde institucional, radio de 10 px y altura mínima de 44 px;
- tipografía, peso, padding, separación y alineación comunes;
- foco visible accesible;
- sin cambios de ancho, columnas, destinos o comportamiento responsive de los contenedores existentes.

La regla usa el atributo data-doctor-back-action junto con href para limitar el contrato a enlaces de navegación. Se eligió un atributo HTML en lugar de alterar la forma de los atributos class heredados, porque los contratos focalizados existentes verifican esas clases institucionales literalmente. No afecta botones de creación, distribución, búsqueda, envío, cancelación, modal, impresión o descarga.

## Inventario revisado

El detalle fila por fila está en [TH_DOCTOR_GLOBAL_BACK_BUTTONS_MATRIX_01.csv](TH_DOCTOR_GLOBAL_BACK_BUTTONS_MATRIX_01.csv).

Vistas app/views/clinica/:

- cofepris-folios.php
- laboratorio.php
- nota-subsecuente.php
- paciente-nuevo-editar.php
- paciente-pin.php
- pacientes-cofepris.php
- pacientes-detalle.php
- pacientes-laboratorio.php
- pacientes-modulos.php
- pacientes-nota-subsecuente.php
- pacientes-recetas.php
- perfil.php
- receta.php
- recetas-nuevas.php
- resumen-clinico.php

Vistas app/views/historia-clinica-doctor/:

- antecedentes-familiares.php
- antecedentes-personales-no-patologicos.php
- antecedentes-personales-patologicos.php
- antecedentes-personales-quirurgicos.php
- evaluacion-dolor.php
- ficha-identificiacion.php
- medicacion-actual.php
- medicacion-control-dolor.php
- procedimientos-control-dolor.php

## Exclusiones verificadas

No se incluyeron:

- Nuevo paciente, Nueva receta, Nueva nota, Nuevo PIN ni otros botones de alta;
- Cancelar del formulario de paciente;
- botones de distribución/toggle;
- botones de impresión, descarga, vista previa o cierre de modal;
- enlaces del Portal Paciente;
- rutas, controladores, modelos, middleware, JavaScript funcional o base de datos.

## Validación estática

| Comprobación | Resultado | Evidencia |
|---|---|---|
| Inventario de enlaces con arrow-left | PASS | 24 coincidencias |
| Enlaces con data-doctor-back-action | PASS | 24 coincidencias |
| Destinos y textos contextuales | PASS estático | Se conservaron las expresiones href y el texto existentes |
| CSS scoped Doctor | PASS estático | Selector limitado a body.app-shell-body.app-shell-doctor a[data-doctor-back-action][href] |
| JavaScript | NO MODIFICADO | No se cambiaron archivos JS |
| PHP lint | PASS | 24 vistas lintadas |
| git diff --check | PASS con advertencias de normalización de finales de línea | No reportó errores whitespace; el worktree contiene cambios históricos ajenos |
| Estilos computados y regla ganadora | NO VERIFICADO | Requiere navegador |
| Viewports 375/430/768/1024/1440 | NO VERIFICADO | Requiere navegador |
| Navegación e historial runtime | NO VERIFICADO | No se ejecutaron GET autenticados |

## Procedimiento manual de Chrome para cerrar la evidencia runtime

Ejecutar en el Windows donde XAMPP atiende clinica.test, usando una sesión Doctor autorizada y sin registrar credenciales en informes.

1. Abrir una vista representativa de cada familia: expediente, PIN, laboratorio, receta, COFEPRIS, resumen clínico, perfil, formulario de paciente y al menos un módulo de Historia Clínica Doctor.
2. Abrir DevTools y ejecutar:

~~~js
const b = [...document.querySelectorAll('a[data-doctor-back-action]')]
  .find(e => e.offsetParent !== null);
const s = getComputedStyle(b);
({
  href: b.href,
  className: b.className,
  backgroundColor: s.backgroundColor,
  backgroundImage: s.backgroundImage,
  opacity: s.opacity,
  color: s.color,
  borderColor: s.borderColor,
  fontFamily: s.fontFamily,
  fontSize: s.fontSize,
  fontWeight: s.fontWeight,
  padding: s.padding,
  borderRadius: s.borderRadius,
  width: b.getBoundingClientRect().width,
  height: b.getBoundingClientRect().height
});
~~~

3. Confirmar rgb(255, 255, 255) en normal, hover, focus/focus-visible y active; confirmar texto e icono morados.
4. Confirmar la hoja efectiva y su versionado:

~~~js
[...document.querySelectorAll('link[rel="stylesheet"]')]
  .map(e => e.href)
  .filter(x => x.includes('app.css'));

performance.getEntriesByType('resource')
  .map(e => e.name)
  .filter(x => x.includes('app.css'));
~~~

5. Repetir en 375×667, 430×932, 768×1024, 1024×768 y 1440×900. Confirmar que no aparece overflow horizontal.
6. Pulsar cada enlace representativo y verificar que el destino contextual y el historial permanecen intactos.

## Riesgos y limitaciones

- La validación visual runtime y los estilos computados no se declaran PASS.
- El worktree ya contenía cambios locales extensos; no se restauraron, limpiaron, añadieron al staging ni comprometieron.
- El fondo blanco en todos los estados queda implementado estáticamente, pero debe comprobarse en el navegador para descartar hojas cargadas después de app.css o caché.
