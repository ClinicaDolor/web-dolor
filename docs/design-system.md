# Sistema de diseño clínico

## Principios

- Claridad antes que ornamentación.
- Jerarquía visible para decisiones clínicas.
- Acciones primarias consistentes.
- Lectura cómoda en escritorio, tableta y móvil.
- Estados y foco visibles.
- No alterar nombres, campos ni procesos existentes.

## Tokens actuales

Definidos en `public/assets/css/app.css`:

- Fondo: `--clinic-bg`.
- Superficie: `--clinic-surface`.
- Texto principal: `--clinic-ink`.
- Texto secundario: `--clinic-muted`.
- Primario: `--clinic-primary`.
- Acento: `--clinic-accent`.
- Bordes: `--clinic-border`.
- Foco: `--clinic-focus`.
- Radio estándar: `--clinic-radius`.

## UI-PATIENT-TITLES-01 — Títulos de Historia Clínica del Portal Paciente

Los títulos principales de los nueve módulos P03–P11 utilizan exclusivamente
`var(--clinic-accent)` (`#35a78f` en `body.app-shell-body`). La regla está
acotada a `body.app-shell-body.patient-clinical-form`: los módulos genéricos
usan `.page-title h3`, mientras que P03 utiliza su encabezado específico
`.patient-identification-hero h1`; P04 ya empleaba el mismo token.

El eyebrow `MI HISTORIA CLÍNICA`, botones, enlaces, navegación y títulos de
modal conservan el morado institucional. P02 y el área Doctor no forman parte
de este patrón. La decisión unifica únicamente el color de los títulos y no
modifica fondos, composición, tipografía, controles ni lógica clínica.

La comprobación estática confirma el token común en P03–P11. La validación
visual autenticada no se ejecutó contra la base real; cualquier evidencia de
navegador requiere el entorno sintético aislado. La medición de contraste del
token actual es 2.97:1 sobre blanco, 2.71:1 sobre `#f5f3ff` y 2.62:1 sobre
`#e2f5ef`; no alcanza WCAG AA para texto normal ni el umbral de 3:1 de texto
grande en esos fondos. No se sustituyó el color aprobado automáticamente; se
deja como decisión común pendiente revisar un tono de acento más oscuro o un
tratamiento de fondo que preserve la identidad.

## ACT-05B-01 — Encabezado del expediente

La vista `app/views/clinica/pacientes-detalle.php` usa el shell médico
compartido mediante `app-shell-body app-shell-doctor`, el topbar existente y
`app/views/components/footer-doctor.php`.

El componente `patient-record-header` reutiliza los tokens del sistema y
contiene únicamente datos ya disponibles en el expediente: nombre, número de
expediente, edad, sexo y fecha de alta. Sus acciones son regresar al listado,
editar paciente y conservar el control existente de distribución.

Responsive comprobado:

- Escritorio: identidad y acciones en una fila horizontal.
- Tableta: sidebar reducido a 220 px y contenido sin solapamiento.
- Móvil: identidad y acciones apiladas, sin overflow horizontal.

Las ocho secciones clínicas y sus componentes internos no fueron rediseñados
en esta actividad.

## ACT-05B-02 — Composición estructural del expediente

`patient-record-summary-card` reúne visualmente identificación, contacto y
motivo de atención sin agregar datos nuevos. `patient-record-history-card`
aparece inmediatamente después y ocupa todo el ancho disponible.

Las tarjetas de PIN, nota subsecuente, laboratorio, receta y COFEPRIS se
conservan como `patient-record-data-card`. En escritorio se distribuyen en dos
columnas; en tableta y móvil pasan a una columna para evitar compresión. El
control existente `toggleSize()` continúa alternando las tarjetas secundarias
a ancho completo.

Los selectores están acotados a
`body.app-shell-body.doctor-patient-record-page`; no cambian Portal Paciente,
la web pública ni los formularios clínicos internos. Los IDs de tablas y
contenedores AJAX permanecen sin cambios.

La identidad visual aprobada para esta iteración se aplica mediante tokens
heredados únicamente por `body.app-shell-body` y `body.auth-page`, por lo que
no cambia los estilos de la web pública. La combinación verificada es:

- Fondo lavanda muy claro y superficies blancas para separar navegación y contenido.
- Primario violeta para acciones, navegación activa y jerarquía de marca.
- Acento verde para estados seguros, avance y confirmaciones.
- Texto ciruela oscuro y gris azulado para lectura clínica prolongada.
- Radios amplios, sombras suaves y bordes lavanda para mantener la referencia visual sin sacrificar contraste.

La fuente continúa siendo la pila tipográfica existente del proyecto; no se
incorporó una dependencia externa de tipografía.

## Componentes reutilizados

- Sidebar de navegación.
- Barra clínica con búsqueda.
- Barra superior con sesión activa y portal identificado.
- Componente compartido de acceso: `app/views/components/auth-visual.php`.
- Iconografía SVG de acceso reutilizable: `app/views/components/auth-icon.php`.
- Contenedor de autenticación, panel visual, tarjeta de formulario, campos con iconos y bloque de garantía.
- Tarjetas de dashboard.
- Tarjetas de expediente.
- Tablas con encabezados semánticos.
- Formularios con foco accesible.
- Barras de progreso de módulos.
- Botones primarios, secundarios y de estado.

## Alcance visual implementado

- Dashboard médico.
- Listado de pacientes.
- Matriz de módulos.
- Entrada del portal paciente y tarjetas de avance.
- Acceso médico y acceso paciente con la misma composición visual y distintos textos de rol.
- Navegación lateral y barra superior.
- Footer con año dinámico.
- Botón de menú móvil con icono visible y nombre accesible.

## Breakpoints verificados

- 375 px: navegación lateral cerrada, menú operable y tarjetas en una columna.
- 768 px: sidebar compacto y tarjetas de dashboard en una columna para evitar títulos clínicos estrechos.
- 1024 px: sidebar compacto y tarjetas en tres columnas.
- 1440 px: sidebar completo y composición de escritorio.

La hoja de estilos incluye además un ajuste específico para accesos hasta
480 px: el panel visual conserva la marca y el formulario pasa a una columna.
La validación visual de los cuatro accesos debe ejecutarse con sesiones
sintéticas antes de aprobar la entrega final.

## Refinamiento de altura de autenticación

La composición de dos columnas utiliza ahora la altura disponible del viewport
con `100dvh` como mejora progresiva y una altura máxima de 790 px. Se eliminó
la combinación redundante de altura mínima del panel visual, altura mínima del
formulario y padding externo. En alturas reducidas se usa altura automática,
sin ocultar el desbordamiento del documento.

También se aplica `box-sizing: border-box` únicamente dentro de
`body.auth-page`, se permite `min-width: 0` para zoom alto y se apilan los
controles secundarios del login cuando el ancho CSS es insuficiente. Esto
evita overflow horizontal sin reducir el tamaño mínimo de los campos y botones.

La evidencia visual disponible en esta iteración confirma ambos accesos en
1280x720 sin scroll innecesario. La instrumentación temporal de auditoría
registró, para ambos accesos, `innerHeight=720` y `scrollHeight=720` en ese
viewport. También se confirmó scroll natural y controles accesibles en
viewport reducido. La matriz completa solicitada (1920x1080,
1440x900, 1366x768, 1280x720, 1024x768, 390x844 y 375x667, más zoom 125% y
200%) queda registrada como validación operativa pendiente cuando exista un
runner con override de viewport y lectura directa de `innerHeight` y
`scrollHeight`; no se inventan esas métricas a partir de una captura.

## Pendiente

- Prueba formal WCAG con lector de pantalla.
- Biblioteca de mensajes clínicos.
- Estados de guardado, error y conflicto.
- Componentes específicos para notas y recetas.

## Dashboard médico — refinamiento de inicio

La vista `app/views/clinica/index.php` conserva el contrato de datos de
`HomeController::indexClinica()`: nombre/ámbito de sesión, navegación lateral y
`total_pacientes` calculado para la clínica. No se agregaron citas, alertas,
recetas emitidas ni métricas que el controlador no entregue.

Componentes específicos del dashboard:

- `doctor-dashboard-hero`: encabezado de portal y contexto de trabajo.
- `doctor-stat-card`: indicador único de pacientes registrados, con enlace al
  directorio existente.
- `doctor-action-card`: tarjeta de acceso para directorio, alta de paciente y
  nueva receta; todas conservan sus rutas existentes.
- `doctor-section-heading`: jerarquía común para resumen y accesos rápidos.

Los estilos están acotados a `body.dashboard-doctor` dentro de
`public/assets/css/app.css`. En escritorio se usa un sidebar de 248 px. Entre
768 y 1100 px se usa un sidebar compacto de 220 px; entre 768 y 900 px las
acciones se distribuyen en dos columnas para conservar legibilidad. En móvil
las acciones se apilan y la barra superior usa dos filas: identidad compacta y
búsqueda de ancho completo. Los textos se ajustan y el documento mantiene
scroll natural; no se oculta contenido con `overflow: hidden`.

La ausencia de una fuente real para citas, alertas, seguimiento y tablas en
`HomeController` se documenta como pendiente funcional; no se simula en la
interfaz.

La validación visual final del dashboard se realizó en 1440×900, 1280×720,
768×1024 y 375×667. Las capturas CDP están en
`docs/evidence/dashboard-doctor-final/cdp-final/`. El runner no cargó la
fuente remota de Google Fonts, por lo que esa evidencia tipográfica requiere
confirmación en un navegador con acceso a red; la geometría, color, jerarquía,
responsive y ausencia de overflow horizontal sí quedaron comprobadas.

## Accesos: responsive móvil y cambio de portal

Los accesos médico y paciente reutilizan `auth-visual.php`, `auth-icon.php`,
la misma tarjeta de formulario y los mismos tokens visuales. La diferencia de
producto se limita al texto de rol, campo de acceso y destino posterior.

- En anchos de hasta 900 px el layout se apila en una sola columna y el
  documento puede crecer con scroll natural.
- Hasta 480 px se reducen únicamente decoración, espaciado y escala del panel
  visual; los controles de entrada y el botón conservan una altura mínima de
  46 px.
- Hasta 360 px se compacta de nuevo la decoración, sin esconder contenido ni
  usar `overflow: hidden` global.
- Cada acceso incluye un enlace secundario de mínimo 44 px para cambiar entre
  `/clinica/acceso` y `/historia-clinica/acceso`; el enlace no ejecuta el
  formulario ni la autenticación.
- Los mensajes de validación usan `#responseMessage`, `role="alert"`,
  `aria-live="assertive"` y `aria-invalid` en el campo afectado. El estado de
  error es visible con color, borde y texto, no solo con color.

La hoja de estilos mantiene las reglas de escritorio previas y limita los
selectores nuevos a `body.auth-page`. No se cambiaron dashboards, formularios
clínicos internos ni la web pública.

## Corrección móvil compacta

En viewports de hasta 900 px el componente de acceso cambia a una columna
centrada. `auth-visual` deja de funcionar como panel introductorio: conserva
únicamente el logotipo institucional como encabezado compacto. Se ocultan con
CSS el título narrativo, descripción y lista de beneficios porque el portal y
el título operativo ya existen en `auth-card-header`; así no se duplica
contenido accesible.

La secuencia móvil resultante es: logotipo, portal, título del formulario,
campos, botón, garantía y enlace al otro portal. El contenedor no impone una
altura mínima de pantalla y permite scroll vertical cuando el contenido no
cabe. El fondo se reduce a un gradiente lavanda sutil y la identidad visual se
mantiene mediante los mismos tokens, iconos y componentes. Las reglas nuevas
están al final de `public/assets/css/app.css` para no alterar el breakpoint de
escritorio (>900 px).

## Área médica — pacientes

Las vistas `app/views/clinica/pacientes.php` y
`app/views/clinica/paciente-nuevo-editar.php` reutilizan el shell del médico:
sidebar, topbar, tokens lavanda/violeta/verde, botones, tarjetas, campos,
iconografía y estados de foco. Sus selectores están acotados a
`body.doctor-patients-page` y `body.doctor-patient-form-page`.

### Listado

- `doctor-page-hero`: encabezado de directorio y acceso existente a alta de
  paciente.
- `doctor-patient-list-card`: tarjeta contenedora con total real de registros.
- `doctor-patient-table`: conserva las columnas originales y
  Simple-DataTables para búsqueda, ordenamiento y paginación.
- `doctor-patient-action`: cuatro acciones existentes por expediente:
  expediente, PIN, historia clínica y edición.
- En tabletas y móviles la tabla conserva todas sus columnas mediante scroll
  horizontal interno; el documento no genera overflow horizontal.

### Alta y edición

- `doctor-patient-form-card` agrupa los 32 controles existentes en cuatro
  secciones: identificación clínica, referencia/motivo, dirección y contacto.
- Se conservan los IDs `Nombres`, `ApellidoPaterno`, `ApellidoMaterno`,
  `Edad`, `Sexo`, `EstadoCivil`, `FeNacimiento`, `CURP`, `LuOrigen`,
  `LuResidencia`, `Ocupacion`, `NumHijos`, `EdadHijos`, `personaRecomienda`,
  `RedesSociales`, `motivoAtencionClinica`, `DACalle`, `DANI`, `DANE`,
  `DAColonia`, `DADelegacion`, `DACP`, `DAMunicipio`, `Distancia`, `Email`,
  `Telefono`, `Celular`, `Cuidador`, `CuiNombre`, `CuiTelefono`, `ResNombre`
  y `ResTelefono`.
- `GuardarPaciente(idPaciente)` mantiene el endpoint y el payload existentes.
  El botón se deshabilita durante la petición y `#mensaje` anuncia errores
  genéricos o del servidor con `role="alert"`.
- Cancelar y Volver a pacientes navegan a `/clinica/pacientes`; no ejecutan
  persistencia.

### Decisiones y límites

- El listado se obtiene mediante `ClinicaModel::getPacientesByClinica()` con
  parámetro `id_clinica`; no se muestran pacientes de otra clínica.
- No se agregaron columnas, filtros clínicos, pasos, pestañas ni reglas de
  negocio nuevas.
- La validación clínica de obligatoriedad y los criterios de los campos
  marcados con `*` siguen pendientes de formalización en servidor; esta fase
  conserva el comportamiento de persistencia existente.

## ACT-01 — Sidebar médico: estado activo

### Causa corregida

El CSS base de `public/assets/css/app.css` crea una barra vertical mediante
`.sidebar-wrapper .menu .sidebar-item.active .sidebar-link:before`. La capa
visual de `body.app-shell-body` la volvía a mostrar con 3 px de ancho. Esa
regla se neutraliza únicamente dentro del shell médico con `content: none` y
`display: none`; no se modifican rutas ni la estructura de los enlaces.

### Estado visual y accesible

- El elemento activo se identifica mediante fondo lavanda degradado, texto e
  iconografía violeta y peso tipográfico reforzado.
- `:focus-visible` conserva un anillo violeta/lavanda de 3 px con separación
  de 2 px para navegación por teclado. El override puntual con `!important`
  es necesario porque existe una regla global heredada que también usa
  `!important`.
- `app/Helpers/Sidebar.php` añade `aria-current="page"` sólo al enlace activo.
- La corrección aplica al sidebar compartido por Dashboard Doctor, listado,
  alta/edición y demás vistas privadas que usan `app-shell-body`.

## ACT-02 — Footer del shell médico

### Componente y alcance

Las rutas `/clinica`, `/clinica/pacientes` y
`/clinica/paciente/nuevo` incluyen `app/views/components/footer-doctor.php`.
El componente se creó separado de `footer-mvsd.php` porque este último también
se reutiliza en Portal Paciente; así el refinamiento del shell médico no cambia
la presentación del portal del paciente.

El footer se renderiza una sola vez por vista y conserva la denominación
institucional verificada `tratamientosdeldolor.org`. El año se genera en
servidor mediante `date('Y')` y se presenta como `© 2026 ·
tratamientosdeldolor.org` en el entorno auditado.

### Reglas visuales

- `.clinic-footer` usa borde superior lavanda, texto secundario legible y
  alineación centrada, con los tokens del shell médico.
- Los selectores están acotados a `body.app-shell-doctor`; Portal Paciente y la
  web pública no reciben estas reglas.
- El componente no usa `position: fixed`, `position: absolute`, altura fija ni
  `overflow: hidden`; permanece después del contenido y permite scroll natural.
- En pantallas pequeñas reduce padding, margen y tamaño tipográfico, conserva
  el área táctil del contenido circundante y permite que la marca se envuelva
  sin overflow horizontal.

## ACT-03 — Menú de acciones del listado de pacientes

La tabla de `app/views/clinica/pacientes.php` usa Simple-DataTables, con
búsqueda, ordenamiento, paginación y `fixedHeight`. Las cuatro acciones que
existían como iconos independientes se conservan con sus destinos originales:

| Etiqueta nueva | Destino original | Tipo | Alcance verificado |
|---|---|---|---|
| Abrir expediente | `/clinica/paciente/{id}` | GET de navegación | `assertPatientAccess()` |
| Gestionar PIN | `/clinica/paciente/pin/{id}` | GET de formulario | `assertPatientAccess()`; guardado POST sin cambios |
| Abrir historia clínica | `/clinica/modulos/paciente/{id}` | GET de módulo | `assertPatientAccess()` |
| Editar paciente | `/clinica/paciente/editar/{id}` | GET de formulario | `assertPatientAccess()`; guardado POST sin cambios |

Cada fila conserva una plantilla `role="menu"` oculta dentro de la celda y un
botón `Acciones` con `aria-haspopup`, `aria-expanded` y `aria-controls`. Al
abrir, se clona únicamente esa plantilla en `body` con posicionamiento fijo;
esto evita que `.dataTable-container` o `.table-responsive` recorten el menú.
La plantilla original permanece intacta para que Simple-DataTables pueda
redibujar filas después de búsqueda, ordenamiento, cambio de página o tamaño.

El controlador delegado conserva apertura por clic, cierre por clic externo y
Escape, flechas arriba/abajo, Home, End, foco visible y cierre al redibujar la
tabla. El menú usa los tokens lavanda/violeta del sistema, `z-index` local al
componente y límites de ancho de viewport; no introduce dependencias nuevas.

## ACT-04 — Foco y acciones del formulario de pacientes

### Causa comprobada

En `public/assets/css/bootstrap.css`, `.form-select:focus` definía una sombra
azul (`rgba(13,110,253,.25)`). Además, `public/assets/css/app.css` contenía un
selector global `:focus-visible` con un anillo azul y `!important`. La vista
`app/views/clinica/paciente-nuevo-editar.php` reutiliza controles Bootstrap y
las clases `btn-light` y `btn-success`, por lo que Cancelar heredaba un
contraste insuficiente y Guardar no seguía el color primario del shell médico.

### Decisión visual y alcance

- Se añadió al final del bloque del formulario una capa acotada a
  `body.doctor-patient-form-page`; no se cambiaron IDs, nombres, campos,
  payload, JavaScript, rutas ni persistencia.
- `.form-control` y `.form-select` válidos usan `--clinic-focus`, una sombra
  lavanda y un anillo violeta visible para teclado. Los selectores excluyen
  `.is-invalid` y `[aria-invalid="true"]`; esos estados conservan su estilo de
  error. Un anillo rojo específico evita que el foco global azul se aplique a
  un control inválido.
- Cancelar conserva su enlace a `/clinica/pacientes`, pero usa fondo lavanda,
  borde violeta y texto `--clinic-primary-dark`. Guardar conserva `type="submit"`
  e id `guardarPacienteButton`, con fondo `--clinic-primary`, texto blanco y
  estados hover, focus y disabled coherentes.
- Los dos `!important` nuevos sólo afectan el `outline` de `:focus-visible`,
  porque deben superar el `!important` del selector global legado. No se
  utilizan para bordes, sombras, colores ni layout.
- No se añadió una regla que reemplace el autofill del navegador; el cambio
  conserva el comportamiento de autofill y sólo actúa sobre foco, borde y
  sombra del control.

### Controles preservados

La vista mantiene 32 controles (`input`, `select` y `textarea`) y los mismos
IDs consumidos por `GuardarPaciente()`. El encabezado “Volver a pacientes” y
el enlace Cancelar conservan sus destinos. No se modificaron Portal Paciente,
logins, web pública, dashboards ni formularios clínicos adicionales.

## Consolidación del área médica — 2026-10-10

Se compararon `/clinica`, `/clinica/pacientes` y
`/clinica/paciente/nuevo` con sesiones sintéticas. Las tres vistas usan el
shell `app-shell-body app-shell-doctor`, el mismo sidebar, topbar de búsqueda,
tokens `--clinic-primary`/`--clinic-border` y el componente único
`app/views/components/footer-doctor.php`. Cada vista renderiza exactamente un
`role="contentinfo"` y el footer permanece en flujo estático.

La captura estable de 1280 × 720 y la captura estable de 375 × 667 muestran la
misma jerarquía lavanda/violeta/verde, tipografía, estados de navegación y
responsive. En el listado la tabla mantiene el scroll interno que requiere su
conjunto actual de columnas; no se modificó porque no es una inconsistencia del
shell y el alcance no autoriza rediseñar la tabla nuevamente.

## ACT-05B-03A — Tarjetas de registros clínicos

Las tarjetas `PIN de acceso`, `Nota subsecuente`, `Laboratorio`, `Receta médica`
y `COFEPRIS` comparten un encabezado clínico con eyebrow, título, icono de
acción y botón descriptivo. Los botones conservan las funciones existentes:
`NuevoPin`, `NuevaNotaSubsecuente`, `NuevoLaboratorio`, `NuevaReceta` y
`NuevoCofepris`; no se cambiaron sus rutas ni permisos.

Las cargas AJAX siguen usando los endpoints existentes de
`BusquedasController`. La vista conserva la tabla cuando la respuesta contiene
filas, con búsqueda, ordenamiento y paginación de Simple-DataTables. Cuando la
respuesta trae una tabla con `tbody` vacío, la vista muestra un estado vacío;
si la respuesta HTTP no es válida o no contiene la tabla esperada, muestra un
estado de error genérico. Durante la petición se muestra un estado de carga.
No se muestran excepciones, SQL ni rutas internas.

Las tablas operativas de estas tarjetas ya no usan `fixedHeight`; el contenedor
crece con su contenido. En móvil, las tablas de las tarjetas usan una grilla
compacta y ajuste de texto acotado al expediente, evitando el `min-width: 720px`
global del shell y el desplazamiento horizontal innecesario. La Historia
Clínica (`table2`) no fue modificada. `toggleSize()` conserva su comportamiento
de ampliar y restaurar las tarjetas.

## ACT-05B-03B — Densidad visual

El refinamiento se delimitó a `.patient-record-data-panel` dentro de
`body.doctor-patient-record-page`. Los encabezados usan `12px` arriba y
`11.2px` abajo en escritorio, `12px` y `10.4px` en móvil; el cuerpo usa
`16px` lateral/inferior. Los buscadores quedan en `40px`, los selectores en
`38px` y las acciones en `40px`, conservando objetivos táctiles y foco de
teclado.

Se redujeron sólo los espacios de las tarjetas secundarias, encabezados y
controles Simple-DataTables. No se aplicaron alturas fijas, `!important` nuevos
ni reglas globales. La Historia Clínica, su tabla de módulos, porcentajes,
resumen, shell, sidebar, topbar y footer quedan fuera del alcance.

## ACT-05B-06A — Historia Clínica

La sección usa la tabla existente `#table2` y conserva el catálogo que la vista
obtiene mediante `SELECT * FROM historia_clinica_modulos`. Los nueve casos de
porcentaje (`1` a `9`) continúan delegados a `PacienteModulosModelo`; no se
modificaron sus cálculos ni sus datos persistidos.

El resumen `Porcentaje total` ya no se coloca dentro de `tbody`. Se renderiza
como `.patient-history-total-row` inmediatamente después de la tabla para que
Simple-DataTables no lo cuente como módulo. Esto explica el conteo previo de
diez registros cuando el catálogo contiene nueve: eran nueve filas de módulos
más una fila de resumen, no un décimo módulo.

Cada fila muestra el porcentaje numérico junto a su barra y expone el mismo
valor en `aria-valuenow`. Las acciones conservan sus URLs construidas con
`registro[url]` e `idPaciente`, pero ahora se distinguen mediante icono, texto
en escritorio y `aria-label`/`title`: `Editar` y `Ver resumen`.

Los estilos se limitan a `body.doctor-patient-record-page` y a las clases
`.patient-history-*`. La tabla usa `table-layout: fixed`, sin altura fija ni
scroll interno; en tabletas y móviles oculta sólo la etiqueta redundante del
botón, sin ocultar su nombre accesible. El buscador y la inicialización de
Simple-DataTables se conservan, con `fixedHeight: false` para que el contenido
crezca de forma natural.

## ACT-05B-03C — Equilibrio de padding en tarjetas clínicas — 2026-10-10

La corrección se limitó a las cinco tarjetas secundarias del expediente
(`.patient-record-data-panel`) en `public/assets/css/app.css`, a partir de la
regla marcada en la línea 4023. No se modificaron Historia Clínica, resumen,
shell, JavaScript clínico, modelos ni persistencia.

En escritorio, los encabezados usan `17.6px` verticales y `20px` horizontales;
los cuerpos usan `20px` en los cuatro lados y los estados vacíos `16px` en los
cuatro lados. En móvil, encabezado y cuerpo usan `16px`; los botones conservan
una altura computada de `40px`. La separación vertical entre tarjetas se fija
en `16px`, sin alturas artificiales.

El cuerpo conserva una separación superior de `20px` entre la línea del
encabezado y el contenido. Esto evita que los estados vacíos queden pegados al
divisor y mantiene el recuadro vacío con `16px` internos. La regla sólo afecta
PIN, Nota subsecuente, Laboratorio, Receta médica y COFEPRIS.

## ACT-05B-06B — Dropdown de acciones de Historia Clínica — 2026-10-10

La tabla `#table2` usa una única columna `Acciones`. Cada módulo conserva una
plantilla oculta con dos destinos: `Editar módulo` y `Ver resumen`. Al abrir,
la plantilla se clona temporalmente en `body` y se posiciona con `fixed`, por
lo que no queda recortada por Simple-DataTables ni por la tarjeta.

El botón reutiliza la identidad lavanda/violeta y el patrón de acciones del
directorio de pacientes: icono de tres puntos, `aria-haspopup="menu",
`aria-expanded`, `aria-controls` y nombre accesible por módulo. Las opciones
tienen icono, etiqueta, foco visible y objetivos de al menos `40px`.

En móvil la etiqueta visual del botón y del encabezado se compacta a icono;
el nombre accesible `Acciones para {módulo}` permanece disponible. Escape,
clic externo, cambio de página, ordenamiento, búsqueda y redibujado eliminan
el clon y restauran el estado del botón. No se modifican porcentajes, fila de
cumplimiento total, cálculos ni rutas clínicas.

## ACT-05B-04A — Robustez JavaScript del shell médico

El shell médico no debe depender de jQuery para ocultar el loader ni para
registrar `toggleSize()`. `public/assets/js/loader.js` usa eventos nativos y
mantiene jQuery como mejora opcional de la animación. Cuando el CDN no está
disponible, el loader se oculta con `display: none` y la función de cambio de
distribución sigue disponible.

`public/assets/js/main.js` debe tratar sidebar, `.sidebar-wrapper`,
`PerfectScrollbar` y `feather.replace()` como dependencias opcionales o
condicionales. Esto evita que una vista que no tenga alguno de esos elementos
interrumpa el resto de sus scripts.

En el expediente, los eventos `pageshow` y `DOMContentLoaded` pueden coexistir,
pero deben delegar en una única inicialización idempotente. Las cargas AJAX de
Notas, Laboratorio, Recetas y COFEPRIS deben producir una sola tabla por
contenedor y mantener sus estados `loading`, `ready`, `empty` o `error`.
Esta actividad no cambia el diseño visual, las rutas, los permisos ni la
persistencia.

## Sprint visual de dashboards — 2026-10-10

### Evidencia final del catálogo paciente — 2026-10-10

La demostración sintética utiliza el catálogo completo de nueve módulos desde
`historia_clinica_modulos` y muestra las tarjetas en el orden `id ASC`. Los
nombres y URLs del fixture coinciden con `ModulosController::nombreModulo()` y
las rutas de `app/Config/routes.php`. La tarjeta sólo es enlace cuando el
módulo no tiene registro en `pac_historia_clinica_finalizar`; no se inventó un
estado persistido adicional.

La cuadrícula observada en escritorio mantiene tres columnas y el flujo móvil
usa una columna con scroll natural. La captura full-page de la base sintética
mostró las tarjetas 1–9, el resumen de 9 secciones, el promedio 82%, 2
finalizadas y el footer compartido `footer-doctor.php`. El valor `Con avance`
se conserva como etiqueta de presentación del porcentaje calculado; no equivale
a un estado nuevo de la base.

### Dashboard Doctor

`app/views/clinica/index.php` conserva el shell médico, el buscador de la
barra superior y las cuatro rutas que ya estaban disponibles en la pantalla:
`/clinica/pacientes` (resumen y directorio), `/clinica/paciente/nuevo` y
`/clinica/recetas/nueva`. El resumen muestra únicamente
`ClinicaModel::countPacientes($id_clinica)`, sin citas, alertas ni métricas
calculadas nuevas. Se añadió una clase de alcance
`.doctor-dashboard-content` para aplicar espaciado del dashboard sin afectar
formularios ni módulos clínicos.

### Dashboard Paciente

`app/views/paciente/index.php` usa las mismas variables y componentes visuales
del shell privado: `dashboard-patient`, `portal-topbar`, tarjetas con tokens
`--clinic-*` y `footer-doctor.php`. El catálogo sigue viniendo de
`historia_clinica_modulos`; los porcentajes siguen delegados a
`PacienteModulosModelo::porcentajeModulo1()` a `::porcentajeModulo9()` y la
finalización sigue viniendo de `pac_historia_clinica_finalizar`.

El resumen visual sólo deriva datos observables: número de módulos del
catálogo, promedio aritmético de sus porcentajes, número de módulos
finalizados y el primer módulo no finalizado como siguiente acceso. `Con
avance` y `Disponible` describen el porcentaje observado; no representan un
nuevo estado persistido. La etiqueta `Completada` sólo aparece cuando existe
un registro de finalización.

La tarjeta activa conserva el acceso al módulo existente, ahora con el
prefijo de ruta requerido por el grupo paciente:
`/historia-clinica/{modulo}/paciente/{idPaciente}`. No se modificaron
controladores, modelos, permisos, formularios internos ni persistencia.

### Responsive y evidencia

El grid médico pasa de tres a dos y una columna según el ancho disponible. El
grid paciente usa tres columnas en escritorio, dos en tableta y una en
móvil; el shell mantiene scroll natural y no utiliza `overflow: hidden` para
ocultar contenido. Las reglas nuevas están delimitadas a
`body.dashboard-doctor` y `body.dashboard-patient`, con reutilización de los
tokens, botones, badges, foco y footer existentes.

La validación sintética utilizó un catálogo de un módulo porque
`tests/fixtures/security-fixtures.sql` contiene una fila mínima para no
simular los nueve módulos sin sus tablas de respuestas. No se interpretó esa
fila como inventario clínico productivo.

## Sprint UI-02 — lote médico y formularios paciente — 2026-10-10

La modernización visual por lote reutiliza `public/assets/css/app.css` y no
introduce dependencias. Las vistas médicas nuevas o pendientes reciben las
clases `app-shell-doctor doctor-clinical-view`; los nueve formularios del
portal paciente reciben `app-shell-patient patient-clinical-form`. Ambas
familias reutilizan sidebar/topbar, tokens lavanda-violeta-verde, tarjetas,
controles de foco, botones y `app/views/components/footer-doctor.php`.

En `/clinica/paciente/{idPaciente}`, el ajuste de Historia Clínica está
delimitado a `body.doctor-patient-record-page .patient-record-history-card`:
el cuerpo observado quedó con `21.6px 24px 24px` en escritorio, el toolbar
con `17.6px` de separación inferior y las filas usan padding vertical compacto.
En móvil el toolbar se apila sin alterar la tabla, el dropdown `Acciones` ni
los nueve módulos.

El listado de PIN tiene un ajuste móvil scoped para que la tabla de cuatro
columnas envuelva su contenido; también se limita el autocomplete del topbar
al viewport privado y se reordena el botón del encabezado. No se utilizó
`overflow: hidden` para ocultar el problema.

El inventario completo de rutas y el estado de validación se mantiene en
[`docs/ui02-inventory.md`](ui02-inventory.md). Las rutas `/clinica/recetas` y
`/clinica/cofepris` no se marcaron como visualmente aprobadas porque el fixture
sintético devolvió el estado genérico de solicitud no disponible.

## Sprint UI-03 — cierre visual — 2026-10-10

Se mantuvo el sistema visual existente. La única corrección P1 adicional fue
delimitar a `body.doctor-recipes-page` la tabla móvil de Recetas: usa layout
fijo y reserva una primera columna no envolvente para que los identificadores
sean legibles. La regla no cambia columnas, datos ni comportamiento.

Las rutas `/clinica/recetas` y `/clinica/cofepris` ahora pueden demostrarse en
modo lectura porque el fixture incluye los contratos mínimos de
`receta_medica_externos` y `cofepris_folio`. Los estados vacíos continúan
siendo estados reales de datos sintéticos, no placeholders visuales.
