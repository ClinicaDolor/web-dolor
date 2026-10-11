# Registro de cambios
## 2026-10-10 — UI-04: inventario maestro de cierre visual

- Se creó docs/UI_DESIGN_CLOSURE_MASTER.md con el inventario verificable de
  D01–D40, P01–P11 y componentes compartidos.
- Se distinguieron pantallas, variantes, componentes, endpoints AJAX,
  generadores PDF, descargas y errores.
- Se documentó el estándar de encabezados y se propuso D03 Directorio de
  Pacientes como primera pantalla pendiente de cierre.
- No se modificaron vistas, CSS, JavaScript, rutas, lógica clínica, permisos,
  persistencia, esquemas, almacenamiento, commits ni despliegues.

## 2026-10-10 — Sprint UI-03: cierre visual

- Se reprodujeron y resolvieron las rutas `/clinica/recetas` y
  `/clinica/cofepris` ampliando exclusivamente el fixture con las tablas
  sintéticas `receta_medica_externos` y `cofepris_folio` requeridas por los
  modelos existentes.
- Se corrigió el P1 móvil de `/clinica/recetas`: los IDs de cuatro dígitos ya
  no se parten carácter por carácter. El cambio está scoped a sus dos tablas.
- Se validaron accesos, dashboards, listados, expediente, vistas clínicas y
  nueve formularios en 1440×900, 1280×720, 768×1024 y 375×667.
- P0: ninguno. P2 documentados sin implementación.
- No se modificaron backend clínico, reglas, permisos, descargas,
  almacenamiento, autenticación productiva, esquemas reales ni despliegues.
- Las vulnerabilidades documentales críticas de ACT-05C-02 continúan abiertas.
- No se realizaron commits.

## 2026-10-10 — Sprint UI-02: modernización visual por lotes

- Se añadió el ajuste scoped de la tarjeta Historia Clínica en
  `public/assets/css/app.css`: cuerpo con padding equilibrado, toolbar separado,
  filas compactas y comportamiento apilado en móvil.
- Se unificó el shell visual de las vistas médicas pendientes y de los nueve
  formularios paciente mediante clases de alcance y `footer-doctor.php`.
- Se corrigió un overflow real de `/clinica/paciente/pin/9301` a 375 px,
  causado por la tabla intrínseca de cuatro columnas, el autocomplete del
  topbar y el botón del encabezado. La solución es responsive y scoped; no
  oculta overflow globalmente.
- Se conservaron rutas, campos, IDs, consultas, permisos, cálculos, AJAX y
  persistencia. Los tres guards de `localStorage` sólo evitan excepciones
  cuando el estado guardado no coincide con el catálogo de temas.
- Se validaron cuatro viewports reales, el catálogo de nueve módulos, las
  nueve rutas directas del paciente, consola, PHP lint y sintaxis JavaScript.
- `/clinica/recetas` y `/clinica/cofepris` quedan como NO VERIFICADOS porque el
  fixture sintético devolvió una respuesta genérica de indisponibilidad. No se
  alteraron sus controladores ni contratos.
- Las vulnerabilidades críticas de ACT-05C-02 permanecen abiertas. No se
  modificaron descargas, almacenamiento, autenticación, esquemas ni despliegues.
- No se realizaron commits.

## 2026-10-10 — Sprint final de demostración

- Se amplió únicamente `tests/fixtures/security-fixtures.sql` con los nueve
  módulos reales del catálogo y las tablas mínimas que consultan los métodos de
  porcentaje existentes.
- Se validó el recorrido sintético completo: login médico, dashboard, listado,
  expediente, Historia Clínica y acciones, cierre de sesión, login paciente,
  dashboard paciente y continuación a un módulo existente.
- Se observó el dashboard paciente con nueve tarjetas, 2 módulos finalizados,
  7 enlaces disponibles y promedio visual de 82%; el dashboard médico mostró
  exclusivamente el paciente autorizado del fixture.
- Se actualizaron `docs/testing.md`, `docs/design-system.md` y
  `docs/modules.md` con evidencia y limitaciones.
- Las vulnerabilidades críticas de autorización/almacenamiento documental de
  ACT-05C-02 permanecen abiertas; no se modificaron descargas, autenticación,
  permisos ni datos reales.
- No se realizaron commits ni despliegues.

## 2026-10-10 — Sprint visual prioritario de dashboards

### Dashboard médico

- Se ajustó el alcance visual de `app/views/clinica/index.php` con
  `.doctor-dashboard-content` y se conservaron las métricas y acciones reales
  ya disponibles.
- Se validaron sidebar, topbar, resumen de pacientes, accesos rápidos, footer
  y responsive en 1440×900, 1280×720, 768×1024 y 375×667 con sesión sintética.

### Dashboard paciente

- Se reorganizó `app/views/paciente/index.php` en un encabezado compacto,
  resumen derivado del catálogo/porcentajes existentes y tarjetas de módulos
  responsive.
- Se reemplazó `footer-mvsd.php` por `footer-doctor.php` para compartir la
  identidad del shell privado.
- Se mantuvieron consultas, modelo de porcentajes, registros finalizados,
  autenticación, permisos y persistencia. La única corrección funcional del
  dashboard fue agregar el prefijo `/historia-clinica/` al enlace de continuar
  un módulo, validado contra la ruta real y el paciente sintético.
- Se añadieron reglas delimitadas a `body.dashboard-patient` y ajustes
  menores a `body.dashboard-doctor` en `public/assets/css/app.css`.

### Validación y pendientes

- PASS: PHP lint, ausencia de overflow horizontal, destinos visibles,
  renderizado sintético y consola sin errores.
- Pendiente: ampliar el fixture de dashboard con los nueve módulos y sus tablas
  de respuestas para una validación representativa de todos los estados.
- Las vulnerabilidades críticas de archivos documentales registradas en
  ACT-05C-02 permanecen abiertas; no se tocaron controladores de descarga,
  almacenamiento ni despliegues.
- No se realizaron commits.

## 2026-10-10 — ACT-05A, auditoría del expediente individual

### Diagnóstico y pruebas

- Se auditó `/clinica/paciente/{idPaciente}` desde `routes.php`,
  `ClinicaController`, `pacientes-detalle.php`, `BusquedasController` y los
  modelos de paciente, nota, laboratorio, receta y COFEPRIS.
- Se confirmó que el fixture sintético no contenía `nota_subsecuente`,
  `laboratorio` ni `cofepris`; las solicitudes AJAX producían `PDOException`
  por tabla inexistente.
- Se amplió exclusivamente `tests/fixtures/security-fixtures.sql` con el DDL
  verificado y registros sintéticos mínimos. No se alteró la base productiva ni
  la lógica clínica.
- La comparación de metadatos con la base local coincidió en 15, 10 y 16
  columnas, respectivamente.
- Se verificó el aislamiento del médico de la clínica 9101 frente al paciente
  9302 de la clínica 9102 en la vista y endpoints AJAX.

### No realizado

- No se rediseñó ni modificó la vista del expediente.
- No se añadieron migraciones, `cofepris_folio`, archivos descargables ni
  fixtures para los nueve módulos clínicos.
- No se realizaron commits ni despliegues.

## 2026-10-10 — ACT-05B-01, shell y encabezado del expediente

### Implementado

- Se integró `/clinica/paciente/{idPaciente}` al shell médico compartido.
- Se pasó el contexto de sesión al topbar para mostrar el médico autenticado.
- Se sustituyó `footer-mvsd.php` por `footer-doctor.php`.
- Se añadió una cabecera compacta con nombre, identificador real del
  expediente, edad, sexo, fecha de alta, regreso al listado, edición y el
  control de distribución existente.
- Se ajustó el ancho del sidebar médico en tabletas para evitar solapamiento
  con el contenido.

### Preservado

- Las ocho secciones clínicas.
- Consultas, tablas, endpoints AJAX, rutas y operaciones existentes.
- Autorización por clínica y paciente.

### No realizado

- No se modificaron secciones clínicas internas.
- No se modificaron Dashboard Doctor, listado, alta, Portal Paciente ni web
  pública.
- No se realizaron commits ni despliegues.

## 2026-10-10 — ACT-05B-02, composición estructural del expediente

### Implementado

- Se unificaron identificación, contacto y motivo de atención en una tarjeta
  responsive, conservando los valores existentes.
- Historia Clínica se situó después del resumen y a ancho completo.
- PIN, notas subsecuentes, laboratorio, recetas y COFEPRIS se reorganizaron en
  una cuadrícula de dos columnas en escritorio y una columna en tabletas/móvil.
- Se conservaron tablas, IDs, consultas, AJAX, porcentajes, rutas y acciones.
- Se mantuvo el comportamiento del control `toggleSize()`.

### Validado

- PHP lint de la vista.
- Renderizado con paciente sintético 9301.
- Autorización cruzada contra paciente 9302.
- Responsive en 1440×900, 1280×720, 768×1024 y 375×667.
- Ausencia de overflow horizontal y carga de las seis tablas existentes.

### No realizado

- No se modificaron modelos, controladores clínicos, base de datos ni
  persistencia.
- No se modificaron Portal Paciente, web pública ni formularios internos.
- No se implementaron ACT-05B-03 ni ACT-05B-06.
- No se realizaron commits ni despliegues.

## 2026-10-10 — Modernización clínica, iteración 1

### Visual

- Se añadió un sistema visual clínico unificado en `public/assets/css/app.css`.
- Se modernizaron dashboard, listado de pacientes y matriz de módulos.
- Se mejoró la entrada del portal del paciente con idioma correcto, navegación accesible, jerarquía de instrucciones, estados de avance y etiquetas seguras en los módulos.
- Se añadieron estados de foco, responsive, jerarquía de títulos y estilos de tablas.
- Se agregaron atributos `alt`, `aria-label`, `scope` y `lang="es-MX"` en las pantallas intervenidas.
- El footer dejó de usar un año fijo.

### Seguridad

- Las cookies JWT ahora incluyen `HttpOnly`, `SameSite=Lax` y `Secure` cuando la petición usa HTTPS.
- Se agregó validación de sesión para rutas sensibles de búsqueda, descarga y PDF.
- Se añadió verificación de pertenencia del paciente para vistas, búsquedas AJAX, consultas por ID y escrituras clínicas que reciben idPaciente.
- La búsqueda de pacientes se filtra por id_clinica del token.
- Se ejecutaron pruebas HTTP locales con cookie ausente y JWT sintético; los casos fuera de ámbito respondieron con rechazo (302/404).

### No realizado

- No se modificó la base de datos.
- No se realizaron migraciones.
- No se eliminaron funcionalidades ni campos clínicos.
- No se hicieron commits ni despliegues.
- Las pruebas visuales autenticadas quedan pendientes de una cuenta sintética aislada.

## 2026-10-10 — Validación y refinamiento de dashboards, iteración 2

### Diseño y responsive

- Se validaron ambos dashboards con sesiones JWT sintéticas y capturas reales en 375, 768, 1024 y 1440 px.
- Se hizo visible el icono del menú móvil y se verificó su apertura.
- Se añadió un breakpoint de tablet para reducir el sidebar y convertir el grid de dashboard a una columna hasta 850 px.
- Se corrigió el overflow horizontal causado por la diferencia entre ancho del sidebar y su desplazamiento móvil.
- Se corrigió el warning de porcentajes no inicializados para pacientes sin respuestas.

### Calidad y seguridad

- Consola del navegador sin errores ni warnings en ambos dashboards.
- No se hicieron escrituras, descargas clínicas, migraciones, commits ni despliegues.
- La validación cruzada entre recursos existentes queda pendiente de fixtures sintéticos aislados.
- Se añadió el roadmap SEO como trabajo futuro exclusivo de http://clinica.test/; los dashboards privados quedan excluidos.

## 2026-10-10 — Cierre visual, seguridad y SEO, iteración 3

### Seguridad

- Se corrigió el KPI de pacientes para filtrar por id_clinica en ClinicaModel y HomeController.
- Se validaron lecturas, escrituras y cruces entre dos clínicas y dos pacientes en una base local aislada con datos sintéticos.
- Se restringió DownloadController a archivos reales dentro de public/storage y se verificó rechazo de traversal.
- Se confirmó que la descarga todavía no vincula la ruta de archivo con paciente o clínica; permanece como riesgo abierto.

### SEO y documentación

- Se auditó http://clinica.test/ sin implementar cambios SEO.
- Se verificaron 22 rutas públicas, accesos privados sin sesión, ausencia de robots/sitemap/canonical/JSON-LD y duplicación de metadatos.
- Se añadieron inventarios de rutas, roles, datos, flujos clínicos, instalación, decisiones y backlog SEO.

### Límites

- No se modificó el sitio público ni los formularios clínicos internos.
- No se realizaron migraciones, despliegues ni commits.
- La base de fixtures es local y separada; no contiene información real.

## 2026-10-10 — Corrección UX/UI de accesos médico y paciente

### Responsive y accesibilidad

- Se compactaron los paneles visuales únicamente en anchos móviles de hasta
  480 px y 360 px, conservando el diseño de escritorio de dos columnas.
- Se permitieron altura automática y scroll vertical natural en layouts
  apilados; no se añadió `overflow: hidden` global.
- Se conservaron IDs, rutas, scripts, controles táctiles, colores lavanda,
  violeta y verde, y la composición visual compartida.

### Navegación y validación

- Se añadieron enlaces secundarios entre portal médico y portal paciente con
  destinos `/clinica/acceso` y `/historia-clinica/acceso`.
- Se añadieron mensajes específicos para campos vacíos y mensajes genéricos
  para rechazos de autenticación.
- Se añadió asociación accesible mediante `aria-describedby`, `aria-invalid`,
  `role="alert"`, `aria-live` y foco al primer campo que requiere corrección.
- El servidor repite la validación de entradas y evita exponer errores
  técnicos o la existencia de cuentas.

### Límites y verificación

- No se modificaron dashboards, formularios clínicos internos, base de datos,
  rutas existentes ni web pública.
- No se realizaron commits ni despliegues.
- Las pruebas con credenciales válidas requieren una fixture sintética de
  acceso que no existe en el conjunto aislado actual; no se usaron datos reales.

## 2026-10-10 — Corrección responsive compacta de accesos

### Diseño móvil

- Hasta 900 px el acceso pasa a una columna centrada con altura natural.
- El panel introductorio conserva únicamente el logotipo institucional; se
  ocultan el título narrativo, la descripción y los beneficios para evitar que
  el formulario quede debajo de una primera pantalla excesiva.
- El encabezado operativo del formulario conserva portal, título, campos,
  botón, garantía y enlace al portal alterno.
- El fondo móvil se limita a un gradiente lavanda sutil y se mantienen los
  estilos de escritorio sin cambios.

### Verificación

- Árbol accesible y capturas locales verifican la nueva secuencia móvil para
  ambos portales.
- Captura local 1280x720 verifica que escritorio continúa en dos columnas.
- La matriz exacta de viewports y zoom 200 % requiere un runner Playwright con
  control de viewport; queda como validación pendiente y no se declara como
  ejecutada.

## 2026-10-10 — Corrección de errores de autenticación no visibles

### Causa y corrección

- Se confirmó que el servidor devolvía JSON de rechazo y el cliente recibía
  la respuesta, pero `#responseMessage` estaba después de `Entrar`.
- Se movió el mensaje para que quede inmediatamente después de los campos y
  antes del botón en ambos accesos.
- Se reforzó el lector de respuestas para manejar 400, 401, 403, 429,
  respuestas no JSON y errores de red sin exponer detalles internos.
- Se añadió un guardia de doble envío y se limpian alerta/estados obsoletos al
  editar un campo.
- Se mantuvieron rutas, IDs, funciones, autenticación y validaciones de
  servidor existentes.

### Verificación

- Mensajes médico y paciente visibles en DOM y captura móvil.
- 401, 403, 429 y fallo de red comprobados con datos sintéticos aislados.
- Prueba positiva de credenciales válidas pendiente por ausencia de filas de
  acceso sintéticas; no se modificó la base de datos.

## 2026-10-10 — Implementación de identidad visual unificada

### Diseño compartido

- Se añadieron tokens lavanda, violeta, verde y ciruela para las áreas privadas y accesos.
- Se creó `app/views/components/auth-visual.php` para compartir la narrativa visual entre login médico y login paciente.
- Se creó `app/views/components/auth-icon.php` para que la iconografía de autenticación sea visible aun cuando no se inicialice el JavaScript del sidebar.
- Se unificaron tarjetas, botones, campos, foco, sidebar, barra superior, badges, progreso y layouts responsive en `public/assets/css/app.css`.
- Se conservaron los IDs `usuario`, `password` y `Pin`, además de las funciones `loginClinica()` y `loginPaciente()` para no romper el flujo existente.
- Se aplicó la identidad primero al dashboard médico y después al portal paciente, incluyendo identificación de sesión y estado de información protegida.
- Se normalizó el nombre histórico de imagen del módulo paciente a un archivo dentro de `public/assets/images/iconos`, manteniendo compatibilidad con valores almacenados con o sin el prefijo `iconos/`.

### Límites y verificación

- No se modificaron la web pública ni los formularios clínicos internos.
- No se cambiaron rutas, consultas, migraciones, framework, datos, commits ni despliegues.
- La revisión visual de login y la comprobación responsive final quedan como verificación de esta iteración; cualquier diferencia de contenido debe resolverse sin alterar los contratos de autenticación.

## 2026-10-10 — Autenticación sintética y refinamiento del Dashboard Doctor

### Validación aislada

- Se amplió `tests/fixtures/security-fixtures.sql` con `accesos_doctores` y
  accesos hashados de prueba para los dos médicos sintéticos.
- Se agregaron dos PIN hashados y con expiración futura para los pacientes
  sintéticos.
- En la base desechable `clinica_dolor_audit_auth_20261010`, el login médico
  válido llegó a `/clinica` y el login paciente válido llegó a
  `/historia-clinica`.
- `/cerrar-sesion` redirigió al acceso correspondiente en ambos portales.
- No se utilizaron datos reales, no se modificó `bd_clinica`, y el servidor
  local de pruebas quedó detenido al finalizar.

### Dashboard médico

- `app/views/clinica/index.php` se reorganizó en encabezado de portal, resumen
  del total real de pacientes y accesos rápidos a las tres rutas existentes.
- `public/assets/css/app.css` incorporó estilos acotados a
  `body.dashboard-doctor`: sidebar compacto de escritorio, jerarquía del
  topbar/hero, tarjeta de indicador, tarjetas de acción y apilado móvil.
- No se agregaron citas, alertas, seguimiento ni KPIs sin fuente en
  `HomeController::indexClinica()`.
- Se actualizaron `docs/design-system.md` y `docs/testing.md` con componentes,
  límites de datos y evidencia.

### Límites

- No se modificó el Dashboard Paciente, módulos clínicos internos, web pública,
  rutas de producción, esquema de la base real, commits ni despliegues.

## 2026-10-10 — Validación visual final del Dashboard Doctor

### Evidencia

- Se generaron capturas autenticadas sintéticas en 1440×900, 1280×720,
  768×1024 y 375×667 mediante Chrome/CDP controlado.
- Las capturas se conservaron en
  `docs/evidence/dashboard-doctor-final/cdp-final/`.
- Se comprobó ausencia de overflow horizontal en los cuatro viewports.
- Se verificaron HTTP 200 para `/clinica/pacientes`,
  `/clinica/paciente/nuevo` y `/clinica/recetas/nueva` con sesión sintética.
- Se verificaron las rutas del sidebar: `/clinica`, `/clinica/pacientes`,
  `/clinica/cofepris`, `/clinica/recetas` y `/clinica/perfil` respondieron 200;
  `/cerrar-sesion` respondió 302 hacia el acceso.
- El KPI de pacientes quedó verificado contra `id_clinica`; no se añadieron
  métricas sin fuente.

### Ajustes comprobados

- Corrección del solapamiento tablet entre sidebar y contenido.
- Rejilla de acciones de dos columnas en tablet compacta.
- Topbar móvil en dos filas para conservar identidad del médico y búsqueda.

### Límites

- Playwright no está disponible localmente; la validación se documenta como
  CDP, no como Playwright.
- La fuente remota Raleway no cargó en el runner headless; la validación final
  de tipografía requiere navegador con acceso a la fuente.
- No se modificaron logins, Dashboard Paciente, módulos internos, web pública,
  base productiva, commits ni despliegues.

## 2026-10-10 — Área médica: listado y alta de pacientes

### Listado

- Se modernizó `app/views/clinica/pacientes.php` con el sistema visual del
  Dashboard Doctor, conservando columnas, búsqueda, ordenamiento, paginación y
  acciones existentes.
- Se agregó `ClinicaModel::getPacientesByClinica()` con consulta parametrizada
  por `id_clinica`; se eliminó la lectura directa sin filtro desde la vista.
- Se conservaron y etiquetaron accesiblemente las acciones de expediente, PIN,
  historia clínica y edición.
- Se corrigió el ancho del sidebar para esta vista: 248 px en escritorio y
  220 px en tabletas.

### Alta y edición

- Se reorganizó `app/views/clinica/paciente-nuevo-editar.php` en cuatro
  secciones visuales, manteniendo los 32 IDs y el payload existente.
- Se añadió botón Cancelar hacia `/clinica/pacientes`, feedback accesible en
  `#mensaje` y protección contra doble envío.
- Se conservó `GuardarPaciente()`, el endpoint `POST
  /clinica/paciente/insert-edit` y la navegación existente.

### Verificación y límites

- Capturas CDP sintéticas en 1440×900, 1280×720, 768×1024 y 375×667 para ambas
  vistas; no hubo overflow horizontal del documento.
- Búsqueda y aislamiento por clínica comprobados con pacientes `9301` y `9302`.
- INSERT sintético comprobado y limpiado en la base aislada.
- La redirección posterior no se declara verificada porque el fixture local no
  define `pc_paciente.id` como `AUTO_INCREMENT`; no se modificó el esquema
  productivo.
- No se modificaron Dashboard Paciente, logins aprobados, módulos internos ni
  web pública. No hubo commits ni despliegues.

## 2026-10-10 — ACT-01: corrección del estado activo del sidebar médico

- Se identificó la barra vertical morada en la regla
  `.sidebar-item.active .sidebar-link::before` de `public/assets/css/app.css`.
- Se eliminó visualmente el pseudo-elemento sólo para
  `body.app-shell-body`; el activo conserva fondo lavanda, texto e iconografía
  violetas.
- Se añadió un foco visible violeta/lavanda para `.sidebar-link:focus-visible`.
- El foco violeta usa un override puntual porque la regla global heredada de
  `:focus-visible` también declara `!important`.
- Se añadió `aria-current="page"` al enlace activo en
  `app/Helpers/Sidebar.php`.
- Se verificó el dashboard y `/clinica/pacientes` con sesión sintética,
  incluyendo ausencia de overflow y preservación de destinos.
- El runner no permitió fijar un viewport tablet independiente; queda como
  validación visual pendiente, sin cambios en los breakpoints existentes.
- No se implementaron ACT-02, ACT-03 ni ACT-04. No hubo commits ni despliegues.

## 2026-10-10 — ACT-02: refinamiento del footer médico

- Se identificó que las tres rutas objetivo incluían una sola instancia del
  componente antiguo `app/views/components/footer-mvsd.php`, cuyo orden visual
  era `2026 © tratamientosdeldolor.org`.
- Se creó `app/views/components/footer-doctor.php` con formato `© año ·
  tratamientosdeldolor.org`, año dinámico y estructura semántica
  `role="contentinfo"`.
- Las vistas `app/views/clinica/index.php`,
  `app/views/clinica/pacientes.php` y
  `app/views/clinica/paciente-nuevo-editar.php` ahora reutilizan el componente
  específico del shell médico.
- Se añadieron estilos responsive acotados a `body.app-shell-doctor` en
  `public/assets/css/app.css`; el footer permanece en flujo normal, sin altura
  fija ni solapamiento.
- No se modificó `footer-mvsd.php`, Portal Paciente, logins, web pública, base
  de datos, rutas, commits ni despliegues.
- PHP lint y `git diff --check` fueron PASS en los archivos tocados. Las tres
  rutas se comprobaron con sesión y datos sintéticos: un solo footer, texto
  correcto, `position: static` y ausencia de overflow horizontal.
- ACT-03 y ACT-04 permanecen pendientes de autorización explícita.

## 2026-10-10 — ACT-03: dropdown de acciones en pacientes

- Se auditaron las cuatro acciones originales de
  `app/views/clinica/pacientes.php`: expediente, PIN, historia clínica y
  edición, conservando sus rutas, parámetros y permisos.
- Se reemplazaron los cuatro iconos independientes por un botón accesible
  `Acciones` y un menú con etiquetas descriptivas e iconos.
- El menú se clona temporalmente en `body` con posicionamiento fijo para evitar
  recortes del contenedor responsive de Simple-DataTables; la plantilla oculta
  permanece en la fila para sobrevivir a búsquedas y redibujados.
- Se añadió manejo de clic, clic externo, Escape, flechas, Home, End, foco
  visible y cierre durante eventos de Simple-DataTables.
- Se actualizaron únicamente `app/views/clinica/pacientes.php`,
  `public/assets/css/app.css` y la documentación de ACT-03. No se modificaron
  modelos, consultas, rutas, autenticación, persistencia, Dashboard Doctor,
  Portal Paciente, web pública ni ACT-04.
- En base aislada: las cuatro rutas del paciente propio respondieron 200 y
  las mismas rutas para otra clínica respondieron 403. Se verificaron
  búsqueda, redibujado, apertura/cierre, ARIA, overflow y PHP lint.
- No hubo commits ni despliegues. ACT-04 permanece pendiente de autorización.

## 2026-10-10 — ACT-04: foco y acciones del formulario de pacientes

- Se confirmó que el azul provenía de `.form-select:focus` en Bootstrap y del
  `:focus-visible` global de `public/assets/css/app.css`.
- Se añadió un override reutilizable y delimitado a
  `body.doctor-patient-form-page`: inputs, selects y textareas usan foco
  violeta/lavanda; los estados `.is-invalid` y `[aria-invalid="true"]`
  conservan tratamiento de error.
- Cancelar conserva su enlace a `/clinica/pacientes` y ahora usa fondo lavanda,
  borde violeta y texto contrastante. Guardar conserva su submit y usa el
  primario violeta, con hover, focus y disabled coherentes.
- No se modificaron la vista PHP, los 32 controles, IDs, JavaScript,
  validaciones, rutas, modelos, persistencia, otros módulos ni arquitectura.
- Validación controlada: foco de input/select por ratón y teclado, responsive en
  1366 × 768, 1280 × 720, 1024 × 768, 768 × 1024, 390 × 844, 375 × 667 y
  320 × 568, sin overflow horizontal real; consola JS y PHP lint PASS.
- La alta sintética quedó FAIL por una incompatibilidad preexistente del
  fixture: `pc_paciente.id` no es `AUTO_INCREMENT` y el modelo depende de
  `lastInsertId()`. No se corrigió por estar fuera del alcance de ACT-04.
- No hubo commits ni despliegues. ACT-04 queda lista para revisión.

## 2026-10-10 — Cierre de consolidación del área médica

- Se verificó en modo lectura que la base real define
  `pc_paciente.id INT NOT NULL AUTO_INCREMENT`. Se corrigió únicamente la
  misma definición en `tests/fixtures/security-fixtures.sql`.
- En una base aislada nueva, el alta sintética persistió correctamente,
  generó el ID `9303` y redirigió a `/clinica/paciente/9303`.
- Se compararon `/clinica`, `/clinica/pacientes` y `/clinica/paciente/nuevo`
  en 1280 × 720 y 375 × 667: sidebar, topbar, tokens, footer único, foco y
  responsive mantienen la misma identidad; no se detectaron regresiones del
  shell.
- Se revalidaron dropdown, Escape, búsqueda, rutas de acciones, 32 controles
  del formulario y ausencia de overflow horizontal del documento.
- El expediente sintético revela que el fixture no incluye las tablas
  auxiliares `nota_subsecuente`, `laboratorio` y `cofepris`; queda como
  pendiente de cobertura del fixture, sin cambios en módulos productivos.
- No se modificaron Portal Paciente, web pública, base real ni arquitectura.
  No hubo commits ni despliegues.

## 2026-10-10 — ACT-05B-03A: tarjetas de registros clínicos

- Se auditaron las respuestas de `BusquedasController` y los modelos de notas,
  laboratorio, recetas y COFEPRIS. Se conservaron endpoints, consultas,
  identificadores, funciones JavaScript, acciones y autorización existentes.
- Se unificaron los cinco encabezados y se reemplazaron los botones únicamente
  iconográficos por acciones descriptivas: `Nuevo PIN`, `Nueva nota`, `Nuevo
  laboratorio`, `Nueva receta` y `Nuevo registro`.
- Se añadieron estados de carga, vacío y error genérico a las cargas AJAX. Las
  tablas con filas siguen usando búsqueda, ordenamiento, paginación y sus
  acciones originales; las tablas vacías ya no muestran un contenedor sin
  contexto.
- Se retiró `fixedHeight` de las tablas operativas del expediente y se ajustó
  su presentación móvil sin modificar la Historia Clínica ni `toggleSize()`.
- Se corrigió una anidación HTML previa que colapsaba la tarjeta PIN y apilaba
  incorrectamente las demás tarjetas; la grilla final queda en dos columnas de
  escritorio y una de tableta/móvil.
- Archivos modificados: `app/views/clinica/pacientes-detalle.php`,
  `public/assets/css/app.css`, `docs/design-system.md`, `docs/testing.md`,
  `docs/patients-functional.md` y este changelog.
- No se modificaron controladores, modelos, rutas, tablas, migraciones, Portal
  Paciente, web pública, Historia Clínica, base real, commits ni despliegues.

## 2026-10-10 — ACT-05B-03B: optimización de densidad visual

- Se compactaron exclusivamente las tarjetas secundarias del expediente con
  reglas scoped a `doctor-patient-record-page`.
- Encabezados: de aproximadamente 71 px a 64 px en escritorio y de 114 px a
  105 px en 375 px. Cuerpos: de 21.6 px a 16 px en laterales/inferior.
- Buscadores: 42 px a 40 px. Acciones: se mantuvieron en 40 px, incluyendo
  móvil, para no reducir accesibilidad táctil.
- Reducción observada en 1440×900: PIN 248→224, nota 289→263, laboratorio
  313→263, receta 289→263 y COFEPRIS 289→263 px.
- Se conservaron estados vacíos, búsqueda, paginación, `toggleSize()`, AJAX,
  permisos, acciones y contenido de Historia Clínica.
- Archivos modificados en esta actividad: `public/assets/css/app.css`,
  `docs/design-system.md`, `docs/testing.md`, `docs/patients-functional.md` y
  este changelog. No se modificaron vistas PHP ni lógica clínica.
- No hubo commits ni despliegues.

## 2026-10-10 — ACT-05B-06A: modernización de Historia Clínica

- Se auditó `#table2`, la consulta al catálogo `historia_clinica_modulos`, los
  nueve casos de cálculo de porcentaje y las URLs de edición y resumen.
- Se confirmó que el conteo previo de diez provenía de nueve módulos más la
  fila `Porcentaje total` dentro de `tbody`; no se agregó ni eliminó ningún
  módulo clínico.
- Se movió el resumen a un bloque semántico fuera de la tabla porque
  Simple-DataTables elimina `tfoot` al transformar la tabla. El paginado y la
  búsqueda ahora cuentan sólo filas del catálogo.
- Se agregaron porcentajes numéricos junto a las barras y se corrigió
  `aria-valuenow` para reflejar el valor realmente mostrado.
- Se diferenciaron las acciones `Editar` y `Ver resumen` con iconos, etiquetas
  de escritorio, `aria-label` y `title`, conservando sus rutas originales.
- Se desactivó únicamente `fixedHeight` de `table2` y se añadieron estilos
  scoped para evitar scroll interno y overflow horizontal en tableta/móvil.
- Archivos modificados: `app/views/clinica/pacientes-detalle.php`,
  `public/assets/css/app.css`, `docs/design-system.md`, `docs/testing.md`,
  `docs/patients-functional.md` y este changelog.
- No se modificaron modelos, controladores, consultas clínicas, persistencia,
  fixtures, Dashboard Doctor, Portal Paciente, web pública, commits ni
  despliegues. La actividad queda detenida para aprobación.

## 2026-10-10 — ACT-05B-03C: corrección de padding en tarjetas clínicas

- Se corrigió exclusivamente el espaciado de las cinco tarjetas secundarias
  del expediente mediante una regla scoped al shell médico y marcada en
  `public/assets/css/app.css`.
- Encabezados: `17.6px` verticales/`20px` horizontales en escritorio y
  `16px` en móvil. Cuerpos: `20px` en escritorio y `16px` en móvil. Estados
  vacíos: `16px` internos. La separación entre tarjetas se mantuvo en `16px`
  y las acciones en `40px` de altura.
- Se verificaron registros sintéticos del paciente 9301 y estados vacíos del
  paciente 9302 en 1440×900, 1280×720, 768×1024 y 375×667. No hubo overflow
  horizontal del documento; tablas, AJAX, estados y contenido conservaron su
  comportamiento observable.
- PHP lint y `git diff --check`: PASS. En el navegador aislado no cargó el
  jQuery externo; por ello `loader.js` no registró `toggleSize()` y
  `main.js` reportó su incidencia existente de `classList`. No se modificó
  JavaScript porque esta actividad es exclusivamente visual; queda pendiente
  repetir la regresión con las dependencias frontend disponibles.
- Archivos modificados en esta actividad: `public/assets/css/app.css`,
  `docs/design-system.md`, `docs/testing.md`, `docs/patients-functional.md` y
  este changelog. No se modificaron Historia Clínica, resumen, controladores,
  modelos, base de datos, Portal Paciente, web pública, commits ni despliegues.

## 2026-10-10 — ACT-05B-06B: dropdown de Historia Clínica

- Se sustituyeron las dos columnas de acciones de `#table2` por una única
  columna `Acciones`, sin modificar cálculos, módulos, porcentajes ni la fila
  de cumplimiento total.
- Se conservaron exactamente los destinos `Editar módulo` y `Ver resumen`,
  incluyendo `registro[url]` e `idPaciente`.
- Se reutilizó el patrón de `/clinica/pacientes`: plantilla por fila, clon
  temporal en `body`, posicionamiento `fixed`, menú lavanda/violeta y cierre
  por Escape, clic externo, búsqueda, ordenamiento y redibujado.
- Se validaron clic, teclado, foco, rutas, búsqueda, responsive en
  1440×900, 1280×720, 768×1024 y 375×667, ausencia de overflow del documento,
  PHP lint y `git diff --check`.
- La prueba aislada sólo dispone de un módulo sintético; la cobertura de
  nueve filas, paginación real y primer/último registro distintos queda
  pendiente de fixtures completos.
- Archivos modificados: `app/views/clinica/pacientes-detalle.php`,
  `public/assets/css/app.css`, `docs/design-system.md`, `docs/testing.md`,
  `docs/patients-functional.md` y este changelog. No se modificaron modelos,
  controladores, persistencia, otras tarjetas, Portal Paciente, web pública,
  commits ni despliegues.

## 2026-10-10 — ACT-05B-04A: estabilidad JavaScript del expediente

- Se eliminó la dependencia obligatoria de `$` en `public/assets/js/loader.js`.
  El loader conserva `fadeOut` cuando jQuery existe y usa un fallback nativo
  cuando el CDN no carga. `toggleSize()` queda registrado en `window` sin
  depender de jQuery.
- Se agregaron comprobaciones defensivas en `public/assets/js/main.js` para
  sidebar, submenús, PerfectScrollbar y Feather, evitando `classList` sobre
  elementos inexistentes y manteniendo el comportamiento original.
- Se hizo idempotente la inicialización de las cuatro cargas AJAX del
  expediente en `app/views/clinica/pacientes-detalle.php`; también se protegió
  el acceso a `#main`, la inicialización de Simple-DataTables y el parseo de
  estado local.
- Se validaron tablas, búsquedas, dropdowns, `toggleSize()`, consola,
  aislamiento y responsive en 375×667, 768×1024, 1280×720 y 1440×900 con
  datos sintéticos aislados. No se modificaron controladores, modelos,
  consultas, rutas, permisos, base de datos, estilos, Portal Paciente ni web
  pública.
- Archivos modificados: `public/assets/js/loader.js`,
  `public/assets/js/main.js`, `app/views/clinica/pacientes-detalle.php` y
  documentación técnica/funcional asociada.
- La disponibilidad real del CDN de jQuery no pudo verificarse en este
  entorno; el fallback sin jQuery sí quedó probado. No se realizaron commits ni
despliegues.
## 2026-10-10 — ACT-05B-05A: manejo seguro de errores clínicos

- Se agregó `app/Core/SafeErrorHandler.php` y se registró desde
  `public/index.php` como frontera global para excepciones y errores fatales.
- Se desactivó la exposición de errores en respuestas, se amortiguó la salida
  de la vista y se definieron respuestas genéricas HTTP 500 para HTML, JSON y
  fragmentos HTML de `/buscar/tabla-*`.
- `app/Config/Database.php` dejó de usar `die()` con el mensaje de PDO y ahora
  propaga una excepción técnica sin exponer su detalle.
- La consulta de módulos en `app/views/clinica/pacientes-detalle.php` dejó de
  mostrar SQL/PDO y permite que la frontera global entregue un error completo y
  seguro.
- Se probaron autorización, inexistencia, tabla vacía, tabla faltante,
  excepción PDO de conexión, recuperación visual AJAX y regresión rápida de
  Dashboard, listado y expediente con bases sintéticas aisladas.
- Se actualizaron `docs/security-backlog.md` y `docs/testing.md` con causas,
  alcance, matriz de resultados y riesgos pendientes.
- No se modificaron reglas clínicas, consultas de negocio, esquemas reales,
  rutas, permisos, Portal Paciente, web pública, commits ni despliegues.

## 2026-10-10 — ACT-05C-02: fixtures y línea base de seguridad documental

- Se confirmó mediante `C:/xampp/apache/conf/extra/httpd-vhosts.conf` que
  `clinica.test` usa `C:/xampp/htdocs/clinica-dolor` como `DocumentRoot`.
- Se verificó que el `.htaccess` raíz redirige hacia `public/` y que
  `public/.htaccess` deja pasar archivos físicos existentes.
- Se amplió únicamente `tests/fixtures/security-fixtures.sql` con una receta,
  laboratorio y registro COFEPRIS sintéticos para cada clínica. No se agregó
  propietario a recetas externas porque el modelo no expone esa relación y no
  existe DDL versionado.
- Se generaron PDF sintéticos bajo `tests/security/runtime/public/storage/`
  para laboratorio, COFEPRIS y un recurso web público. No contienen datos
  personales reales ni fueron colocados en `public/storage` del proyecto.
- La línea base confirmó los fallos previstos: descarga de laboratorio,
  receta PDF y COFEPRIS PDF aceptaron acceso cruzado con `200`; las URLs
  estáticas sintéticas devolvieron `200` sin sesión. Traversal respondió `404`.
- Se confirmó que los PDF inexistentes devuelven `500`, pendiente de manejo
  controlado en una actividad posterior.
- Apache fue validado con `httpd.exe -t` (`Syntax OK`) y un `HEAD` al recurso
  público existente `/public/storage/web/Aviso%20de%20privacidad.pdf` devolvió
  `200 application/pdf`; no se leyó su contenido.
- Se actualizaron `docs/testing.md` y `docs/security-backlog.md` con la matriz,
  consumidores, compatibilidad y precondiciones de ACT-05C-03.
- La base sintética y los servidores de prueba se eliminaron al finalizar.
- No se modificaron controladores de descarga/PDF, rutas productivas, bases o
  archivos clínicos reales, autenticación, commits ni despliegues.
## 2026-10-10 — Cierre de entrega para demostración sintética

- Se verificó el recorrido Doctor → Expediente → Historia Clínica →
  Laboratorio → cierre de sesión → Paciente → Dashboard → módulo clínico con
  la base aislada `clinica_dolor_demo_20261010_ui03c`.
- Se confirmó el catálogo de nueve módulos, las sesiones de ambos portales,
  el dropdown de Historia Clínica, el responsive en 1440, 1280, 768 y 375 px
  y la ausencia de errores JavaScript de nivel `error` en el recorrido final.
- No se modificó código de aplicación, esquema real, persistencia,
  autenticación ni despliegue. Se agregó `docs/demo-readiness.md` y se
  actualizaron las referencias de pruebas y documentación.
- Las vulnerabilidades críticas de ACT-05C-02 permanecen abiertas y bloquean
  el uso de información clínica real.

## 2026-10-10 — UI-FIX-D04-02: selectores inválidos

- Se reprodujo el defecto visual en `Sexo` y `EstadoCivil` del formulario
  compartido `app/views/clinica/paciente-nuevo-editar.php`.
- La causa fue `background-repeat: repeat, repeat` sobre las dos capas de
  fondo que Bootstrap aplica a `.form-select.is-invalid`.
- Se añadió sólo en `body.doctor-patient-form-page` el override
  `background-repeat: no-repeat`, conservando borde rojo, flecha, alerta,
  mensajes, foco y validación.
- Se validó Crear y se contrastó Editar mediante las rutas reales de
  `app/Config/routes.php`; D04 permanece en VALIDACIÓN y D05 sigue siendo el
  expediente individual.
- Se verificaron 1440×900, 1280×720, 768×1024 y 375×667, HTTP 422 sintético,
  teclado, consola sin errores y ausencia de overflow. No hubo commits ni
  despliegues.
