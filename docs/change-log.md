# Registro de cambios

## 2026-10-11 — P11-COMPLETE-01: evaluación del dolor y mapa corporal

- Se modernizó exclusivamente P11 con el shell del Portal Paciente y la
  jerarquía visual del modal P04. El flujo conserva frente, espalda y
  cuestionario, con ayuda automática, controles Escuchar/Detener, voz `es-MX`,
  Escape, foco y ARIA.
- Se eliminó la inicialización de filas e imágenes durante GET en las vistas
  Paciente y Doctor. Los fragmentos AJAX derivan el rol desde la sesión. Los
  mapas usan Pointer Events y transformación de coordenadas; los POST validan
  PNG y tamaño, guardan atómicamente en `app/storage/evaluacion-dolor` y se
  sirven mediante endpoint autorizado. Limpiar frente y espalda es
  independiente.
- El cuestionario conserva `dolor`, `tiempo_dolor`, `descripcion` e
  `incremento`, actualiza sólo columnas permitidas y restringe la operación a
  `id_paciente`. La finalización del módulo 9 comprueba completitud, es
  idempotente y mantiene `next_url`.
- PASS estático: PHP lint focal y contratos de rutas/campos. PASS real sobre la
  base allowlisted `clinica_dolor_demo_20261010_final`: login, GET sin
  escrituras, AJAX, persistencia de mapas/cuestionario, limpieza independiente,
  autorización cruzada 403, finalización idempotente, `next_url` y restauración
  exacta de la línea base.
- PASS de navegador sintético a 1280×720: modal P04, Escape, estado de
  narración Escuchar/Detener, navegación frente/espalda/cuestionario, Pointer
  Event con guardado y consola sin errores. NO VERIFICADO: conteo de sentencias
  SQL del no-op, audio audible humano, stylus/táctil independiente, viewports
  1440×900/768×1024/375×667 y capturas exportadas.
- La base quedó restaurada a su línea base; el archivo privado sintético que ya
  existía para 9301 se restauró byte a byte y no se dejaron archivos nuevos de
  la prueba.
- Los mapas heredados que aún existen bajo `public/assets/images/evaluacion-dolor`
  conservan un riesgo de acceso directo; no se declara remediación documental
  completa. La semántica histórica azul/amarillo queda pendiente de decisión
  clínica. No se modificaron P01–P10, no se inició P12, ni hubo commits o
  despliegues.

## 2026-10-11 — P07-MODAL-AUDIO-FIX-01: modal y narración de instrucciones

- Se restauró exclusivamente en P07 el botón de ayuda y el modal automático de
  instrucciones, con título específico de Antecedentes Personales Patológicos,
  botones Escuchar/Detener/Entendido, ARIA, foco, Escape, click fuera y retorno
  de foco al cerrar.
- La narración usa `SpeechSynthesisUtterance`, prioriza `es-MX`, informa el
  estado sólo después de `onstart`, maneja error o falta de voces y cancela la
  lectura anterior al cerrar, repetir o cambiar de pregunta. V1 lee el tema y
  la pregunta; V2 lee la enfermedad, la pregunta y sólo dependencias visibles.
- Se retiraron únicamente los iconos `.btnLeer` estáticos de P07 para evitar
  doble narración con `voice-utilities.js`; el utilitario compartido no se
  modificó. No se alteraron guardado, avance, autorización, finalización,
  endpoints, modelos, P04, P05, P06 ni P08.
- PASS: contrato de modal/voz y sintaxis contractual. NO VERIFICADO: audio
  audible humano, consola y capturas responsive autenticadas. P07 permanece en
  `VALIDACIÓN`; no hubo commits, despliegues ni escrituras reales.

## 2026-10-11 — P08-COMPLETE-01: Medicación Actual

- Se completó exclusivamente P08 con una vista compatible con el patrón visual
  de P04: hero institucional, estado vacío, tarjetas de medicación, controles
  accesibles, modal de instrucciones y navegación responsive. Se conservaron
  los cinco campos heredados y su semántica (`descripcion`, `medicamento`,
  `tiempo_uso`, `dosis`, `medico`).
- Se implementaron eventos delegados, guardas contra doble envío, persistencia
  del contexto en `localStorage` por paciente, confirmación de guardado antes de
  recargar/continuar y voz `es-MX` cancelable sin narraciones superpuestas.
- Las operaciones P08 derivan el ámbito desde la ruta, exigen autorización del
  paciente y restringen el CRUD con consultas preparadas por `id` +
  `id_paciente`. La finalización del módulo 6 es idempotente y devuelve sólo
  `next_url` relativa.
- Se añadieron pruebas sintéticas fail-closed en `tests/p08/`: preflight,
  runner HTTP de CRUD/autorización, contrato estático, limpieza y servidor
  aislado reutilizable. PASS: aislamiento, lint, contrato, acceso propio/
  cruzado/anónimo, CRUD, no-op, finalización y limpieza. Capturas, viewports,
  consola y reproducción audible real quedan NO VERIFICADOS por limitación del
  navegador. `bd_clinica` no se modificó.
- P08 permanece en `VALIDACIÓN`; no hubo commits ni despliegues.

## 2026-10-11 — P07-COMPLETE-01 + adendum P05-FLOW-SPEED-01

- Se completó exclusivamente la navegación interactiva de P07 para V1 y V2:
  guardado confirmado antes de avanzar, avance automático para respuestas No y
  Sí sin dependencias, permanencia hasta completar dependencias, guardado de
  texto con un solo Siguiente y prevención de UPDATE cuando el valor ya está
  persistido.
- Se sustituyeron los manejadores inline del flujo Paciente por eventos
  delegados y controles con etiquetas accesibles. La carga de dependencias V2
  ocurre sólo después de confirmar el guardado y se cancela la narración activa
  durante transiciones. El shell y footer reutilizan el patrón aprobado de P04.
- Los endpoints V1/V2 validan el paciente autorizado y la pertenencia de la
  respuesta al paciente, tema o enfermedad correspondiente; el rol efectivo se
  deriva de la ruta y no del payload del cliente.
- Se añadió el catálogo sintético allowlisted y el runner HTTP con pruebas de
  persistencia propia, bloqueo anónimo y aislamiento cruzado para clínicas
  9101/9102. La base sintética quedó limpia después de la ejecución.
- PASS: contrato estático, lint, clicks efectivos en navegador sintético,
  persistencia V1/V2, autorización cruzada y responsive renderizado 1440×900 /
  375×667. NO VERIFICADO: audio audible humano, finalización y redirección al
  siguiente módulo, y viewports P07 1280×720 / 768×1024. P07 permanece en
  VALIDACIÓN. No se modificaron P04, P05 ni P06; no hubo commits ni despliegues.

## 2026-10-11 — P05-FLOW-SPEED-01: avance sin doble clic

- Se centralizó en P05 el guardado y avance del paciente mediante eventos
  delegados y una única transición asíncrona. Un `Siguiente` sobre un input
  modificado espera la confirmación y avanza una sola pregunta; si el guardado
  iniciado por `change` ya está en curso, el clic queda encolado una vez.
- Las respuestas Sí/No guardan y avanzan automáticamente cuando corresponde;
  `No` conserva la limpieza de dependencias y `Sí` no omite sus campos. Los dos
  inputs de Grupo Sanguíneo avanzan sin doble clic. No se modificaron CSS,
  P04, P06–P11, modelos de negocio ni esquema de base de datos.
- PASS sintético: catálogo 7/25, Tabaquismo→Alcoholismo con `No`, dependencia
  con `Sí`, input con un clic, Grupo Sanguíneo, persistencia y consola sin
  errores. Persisten NO VERIFICADOS fallo forzado de red, latencia/doble clic
  instrumentados, audio audible, viewports/capturas y finalización.
- Se añadió `tests/p05/flow-speed-contract.mjs`; la base sintética
  `clinica_dolor_demo_20261010_final` quedó limpia al finalizar. P05 permanece
  en VALIDACIÓN y no se realizaron commits ni despliegues.

## 2026-10-11 — P05-RESTORE-REAL-01: catálogo original y validación real

- Se extrajeron de forma limitada los datos de los SQL originales suministrados
  para P05 a `tests/fixtures/p05-catalog.php`: 7 temas y 25 preguntas, sin
  respuestas ni datos personales; la carga sólo admite la base sintética
  `clinica_dolor_demo_20261010_final`, es idempotente y rechaza conflictos.
- Se reparó la validación real de P05 con sesión sintética: AJAX, dependencias,
  recuperación, siete temas, Grupo Sanguineo, comentarios, finalización y
  `next_url` a P06 quedaron PASS; el acceso GET y POST cruzado fue rechazado.
- Se corrigieron dos defectos visuales demostrados por el catálogo real: el
  override de `.pregunta-container` que mostraba todas las preguntas y el
  overflow causado por `.p05-navigation-hint`. No se modificó P04 ni P06.
- Se añadieron `tests/p05/http-crud.php` y `tests/p05/cleanup-synthetic.php`.
  La limpieza elimina sólo respuestas, comentarios y finalización de 9301/9302
  en la base sintética, conservando el catálogo. Reproducción audible humana,
  viewports 1280/768 y fallo forzado de red permanecen NO VERIFICADOS.
- P05 permanece en VALIDACIÓN; no se realizaron commits, despliegues ni acceso
  a `bd_clinica`.


## 2026-10-11 — UI-PARITY-GATE-01: paridad visual y audio de P05

- Se corrigió exclusivamente P05 para reproducir la composición de P04: los
  controles de voz ahora están en el cuerpo de la tarjeta, la pregunta ocupa
  una sola columna, Sí/No son horizontales en escritorio y la navegación usa
  todo el ancho disponible; en móvil las opciones se apilan.
- Se fijó el modal P05 al mismo máximo computado de P04 (540 px), se eliminó
  el riesgo de estado «Intentando iniciar» indefinido con un timeout de 1500 ms
  y se sincronizó el elemento activo del sidebar con `aria-current`.
- Se añadieron el gate reutilizable
  `tests/patient-module-parity-gate.mjs` y el fixture visual aislado
  `tests/p05/visual-fixture.html`. P06 sólo fue verificado y no modificado.
- PASS: PHP/JS/CSS, contratos P05, mock de audio, gate estático, consola
  sintética, geometría 1440×900 y responsive 375×667 del fixture. P05 real
  no tiene preguntas porque el catálogo sintético allowlisted está vacío;
  audio audible humano, persistencia y navegación clínica permanecen
  NO VERIFICADOS. P05 sigue en VALIDACIÓN y P07 no fue iniciado.

## 2026-10-11 — P06-COMPLETE-01: modernización integral de Antecedentes Personales Quirúrgicos

- Se modernizó exclusivamente P06 con el patrón visual local de P04/P05:
  hero institucional blanco a menta, contexto del paciente, tarjeta clínica
  de altura natural, estado vacío, campos legibles y acciones responsive.
- Se conservaron los tres campos existentes de `pc_antecedentes_quirurgicos`
  (cirugía, fecha y observaciones), junto con agregar, editar, eliminar,
  confirmación, navegación, finalización y continuidad mediante `next_url`.
- Se incorporó modal accesible de instrucciones y narración específica de P06
  con Escuchar/Detener, cancelación de utterances anteriores y fallback seguro.
- Se reforzó el aislamiento de las operaciones P06: sesión y ruta determinan
  el contexto, el registro debe pertenecer al paciente autorizado y UPDATE/
  DELETE utilizan filtros de paciente y consultas preparadas. El listado y
  el endpoint de contenido no confían en `idRol` enviado por el cliente.
- Se añadieron `tests/p06/contract-check.mjs`, `audio-mock.mjs`,
  `verify-isolation.php`, `http-crud.php` y su README. PHP/JS/CSS, CRUD HTTP
  sintético, rechazo cruzado y regresiones focales P04/P05 quedaron PASS.
- Se verificaron capturas reales en navegador sintético a 1440×900 y 375×667;
  1280×720 y 768×1024, así como reproducción audible real, quedan
  NO VERIFICADOS. No se usaron datos reales, no se tocó `bd_clinica` y no se
  inició P07.

## 2026-10-11 — P05-FIX-UI-NAV-01: composición vertical y navegación

- Se alineó la composición de P05 con P04: el contenido dinámico ocupa una
  sola columna, el encabezado de instrucciones mantiene el control de voz a
  la derecha y el cuestionario conserva el ancho principal de 1040 px.
- Se corrigió el modelo para que las preguntas `select` sin respuesta sigan
  renderizando «Siguiente» en el DOM, deshabilitado y acompañado de un
  mensaje accesible; ya no se omite el footer de navegación.
- Se restauró el tratamiento visual del nombre del hábito: etiqueta oscura y
  nombre en `var(--clinic-accent)`. Se conservaron temas, AJAX, dependencias,
  guardas, persistencia, finalización y `next_url`.
- No se modificaron P04 ni otros módulos. El catálogo P05 sintético continúa
  vacío, por lo que el recorrido clínico real permanece NO VERIFICADO.

## 2026-10-11 — P05-COMPLETE-01: experiencia guiada y audio del módulo paciente

- Se completó P05 reutilizando la experiencia aprobada de P04: modal de
  instrucciones con narración automática, controles explícitos «Escuchar» y
  «Detener», lectura de la pregunta activa y cancelación de audio al cambiar
  de pregunta, sección o página.
- Se retiraron los dos controles de audio heredados `btnLeer` de P05 para
  evitar controles duplicados; la lectura ahora usa funciones y eventos
  exclusivos `p05*`, con voz `es-MX` preferente, fallback textual y guardas
  contra narraciones superpuestas.
- Se conservaron los siete temas dinámicos, preguntas, dependencias, AJAX,
  persistencia, autorización, comentario, finalización y `next_url`. No se
  modificaron P04, P06–P11, Doctor ni el catálogo clínico.
- La base sintética allowlisted sigue sin catálogo P05 aprobado; por ello la
  navegación y persistencia real de los siete temas, capturas autenticadas y
  reproducción audible quedan NO VERIFICADAS.

## 2026-10-11 — P05-UI-01: modernización visual del módulo paciente

- Se modernizó exclusivamente la vista P05 con el patrón visual local de P04:
  hero institucional blanco/lavanda con acento menta, contexto del paciente,
  acciones de instrucciones/regreso, tarjetas clínicas de altura natural,
  navegación y sección de comentarios.
- Se añadieron tarjetas Sí/No grandes y clicables mediante sus labels, foco
  visible, controles de navegación consistentes y estilos responsive scoped a
  `patient-nonpathological-page`.
- Se incorporó un modal de instrucciones accesible, sin narración ni nuevos
  manejadores clínicos; conserva foco, Escape, cierre por fondo y retorno al
  control originario.
- Se versionó la carga de `app.css` mediante `filemtime()` en P05. No se
  modificaron modelos, controladores, rutas, AJAX, persistencia, P04 ni otros
  módulos. P05 permanece en VALIDACIÓN; las capturas autenticadas y la
  validación funcional real siguen bloqueadas por el catálogo sintético vacío.

## 2026-10-11 — P05-TEST-DATA-01: diagnóstico de catálogo sintético

- Se revisaron las fuentes versionadas del proyecto y no existe un catálogo
  aprobado con los siete temas y preguntas reales de P05; no se inventaron
  textos clínicos ni se consultó `bd_clinica`.
- Se añadió `tests/p05/prepare-synthetic.php`, con guarda fail-closed,
  allowlist exacta y reparación explícita del esquema únicamente en
  `clinica_dolor_demo_20261010_final`.
- La base sintética quedó con la columna `pregunta` contractual verificada;
  los conteos de temas y preguntas permanecen en cero hasta disponer de una
  fuente aprobada.
- Las pruebas autenticadas A–O de P05 quedan NO VERIFICADAS/BLOQUEADAS por la
  ausencia del catálogo. La regresión focal existente de P04 pasó y no se
  modificó P04, `bd_clinica`, CSS, audio, otros módulos, commits ni despliegues.

## 2026-10-11 — P05-FUNC-01: funcionamiento y seguridad

- Se corrigió el aislamiento de navegación de P05 con claves
  p05:{idPaciente}:modulo-3:*, selección exclusiva Sí/No, bloqueo de
  navegación durante un guardado pendiente y prevención de doble finalización.
- La finalización conserva la secuencia comentario→finalización y consume
  next_url; los errores de red/HTTP muestran mensajes genéricos sin continuar
  el flujo.
- El modelo de P05 ya no actualiza por idRespuesta aislado: comprueba
  paciente, pregunta y tema, y el controlador deriva el ámbito desde la ruta
  y la cookie, no desde idRol enviado por el navegador. El GET de preguntas
  usa el rol efectivo de la sesión.
- Se agregaron preflight y checks reproducibles en tests/p05/; la base
  sintética confirmó aislamiento, pero su catálogo P05 está vacío, por lo que
  las pruebas autenticadas A–O quedan NO VERIFICADAS. El fixture sólo recibió
  la columna pregunta requerida por el contrato SQL; no se inventaron
  preguntas clínicas.
- Se conserva explícitamente el efecto secundario preexistente de siembra en
  GET. No se modificaron CSS, audio, P01–P04, P06–P11, Doctor, esquema real,
  base bd_clinica, commits ni despliegues.

## 2026-10-11 — P04-NEXT-01: continuación al siguiente módulo

- Se añadió `PacienteModulosModelo::siguienteModuloPendiente()` como resolvedor
  central basado en el catálogo `historia_clinica_modulos` y las finalizaciones
  formales del paciente.
- Se añadió el campo aditivo `next_url` a la respuesta exitosa de
  `ModulosController::pacienteFinalizarModulo()`. Los slugs se validan contra
  la allowlist canónica y los destinos no permitidos usan el dashboard como
  fallback; no se aceptan URLs del navegador.
- P04 consume `next_url` después de la secuencia ya corregida de comentario y
  finalización. P03 y P05–P11 conservan sus callbacks y no fueron modificados.
- Se añadieron pruebas sintéticas de resolución y se validaron los recorridos
  P04→P05, salto P04→P06 y fallback al dashboard, además de autorización
  cruzada.
- No se modificaron CSS, audio, modal, preguntas, guardado clínico, esquemas,
  área Doctor, commits ni despliegues.

## 2026-10-11 — P04-FIX-FINALIZAR-01: desbloqueo de comentario y finalización

- Se confirmó que el callback del guardado de comentario intentaba finalizar
  mientras `p04PendingRequest` seguía activo; ese orden provocaba el mensaje de
  bloqueo y evitaba la redirección.
- Se implementó en la vista P04 un orquestador secuencial con una única guarda
  de flujo, `finally` común para recuperar loader/botón y reintento de
  finalización sin duplicar el comentario ya guardado.
- Se añadió `tests/p04/finalization-flow.mjs` para probar errores de comentario,
  finalización, red, doble envío y comentario vacío sin tocar una base real.
- Se validó el recorrido real A/B/E/G contra la base sintética allowlisted y se
  restauró su línea base; el paciente sintético 9302 no recibió cambios.
- No se modificaron CSS, audio, modal, preguntas, otros módulos, modelos,
  controladores, rutas ni base `bd_clinica`. No hubo commits ni despliegues.

## 2026-10-11 — UI-PATIENT-TITLES-01: títulos P03–P11

- Se unificó el color de los títulos principales de los nueve módulos de
  Historia Clínica del Portal Paciente mediante `var(--clinic-accent)`
  (`#35a78f`), con alcance exclusivo a `patient-clinical-form`.
- Se conservaron el morado de eyebrow, navegación, enlaces, botones y modales,
  además de los fondos, dimensiones y composición existentes.
- No se modificaron P02, el área Doctor, JavaScript, rutas, controladores,
  modelos, persistencia ni funcionalidades clínicas.
- La verificación visual autenticada queda NO VERIFICADA fuera del entorno
  sintético; P03–P11 no se marcan como cerradas ni funcionalmente validadas.
- El contraste medido de `#35a78f` es 2.97:1 sobre blanco y menor sobre los
  fondos institucionales; se conserva sin sustitución automática y queda
  pendiente una decisión común de accesibilidad.

## 2026-10-11 — P04-TEST-ENV-01: entorno sintético reutilizable

- Se añadió `tests/p04/verify-isolation.php`, guarda fail-closed y de solo
  lectura para `clinica_dolor_demo_20261010_final`.
- Se añadió el arranque/detención de un servidor local aislado y un router de
  pruebas que conserva el front controller y la autenticación reales.
- Se añadió un mock reproducible del contrato de voz; no se modificaron CSS,
  JavaScript clínico, controladores, modelos, rutas ni la base `bd_clinica`.
- Se completó el fixture de comentarios con `pac_historia_clinica_comentario`,
  dependiente del panel P04; la tabla se creó sólo en la base sintética
  allowlisted y el endpoint pasó de HTTP 500 por tabla ausente a HTTP 200.
- Se validó el login sintético y el acceso cruzado rechazado para P04. La
  reproducción audible real y la finalización del módulo quedan separadas como
  NO VERIFICADAS cuando el navegador no expone Speech Synthesis.
- No se realizaron commits ni despliegues.

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

## 2026-10-11 — P05-EXACT-PARITY-01: paridad visual real con P04

- Se corrigió exclusivamente P05 (`app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php`, `AntecedentesNoPatologicosModel.php` y su CSS scoped).
- El HTML real mostró dos cierres faltantes que anidaban el `card-body` dentro del `card-header`, reduciendo el ancho útil; se restableció la misma estructura directa de P04.
- Se hizo visible el prompt clínico que quedaba oculto por el selector de paridad, y se separaron la etiqueta del hábito y su nombre para conservar el orden visual de P04.
- Se eliminó el `p05-navigation-hint` permanente del estado vacío. El botón Siguiente continúa visible y bloqueado con `disabled`, `aria-disabled` y `title`; el mensaje de avance sólo aparece de forma contextual al intentar avanzar.
- El navegador sintético confirmó a 1280×720 que P04/P05 comparten 925.14 px de tarjeta exterior, 884.00 px de contenido y la misma alineación derecha de navegación; la pregunta de tabaquismo llegó por AJAX real, sin overflow ni errores de consola.
- PASS: PHP lint, contratos P05/P04/P06, audio mock, HTTP de aislamiento/CRUD sintético y `git diff --check`. NO VERIFICADO: 1440×900 y 375×667 en esta ejecución por falta de control reproducible de viewport, reproducción audible humana, fallo forzado de red y dashboard posterior. No se realizaron commits ni despliegues.

## 2026-10-11 — P09-COMPLETE-01

- Se modernizó exclusivamente el Portal Paciente P09 (medicamentos usados para
  controlar el dolor) con el patrón visual y de voz de P04/P05.
- Se incorporaron controles exclusivos, dependencias de dosis/resultados/
  consumo, guardado confirmado sin doble UPDATE, avance posterior al guardado
  y finalización de módulo 7 mediante `next_url` validado.
- El endpoint y el modelo dejaron de confiar en el rol enviado por el navegador
  y acotaron el UPDATE a paciente, respuesta, pregunta y tema con valores
  permitidos.
- Se añadieron contratos y preparación fail-closed en `tests/p09/`. Los
  contratos estáticos pasan; las pruebas autenticadas, responsive y de audio
  real permanecen NO VERIFICADAS por falta de confirmación del entorno/navegador.
- No se realizaron commits ni despliegues.

## 2026-10-11 — P09-RESTORE-REAL-01

- Se verificó `P09_CATALOGOS_SEGUROS.zip` mediante SHA-256
  `7054DA65AC77BB5DEE9763E109E21FD01AFEBB93FE59D71A9874CF91CFE43986` y se
  procesaron únicamente sus catálogos allowlisted: 6 temas y 46 preguntas.
- La importación quedó restringida a `clinica_dolor_demo_20261010_final`, fue
  idempotente y no ejecutó volcados completos. Se añadió sólo la estructura
  sintética faltante; no se importaron respuestas ni se tocó `bd_clinica`.
- Las pruebas HTTP reales confirmaron login, GET/AJAX, persistencia,
  aislamiento propio/cruzado, modal, navegación, avance automático,
  finalización e idempotencia. Se corrigieron exclusivamente la pérdida de
  dosis al redibujar tras elegir resultado y la duplicación de finalización.
- Se generaron capturas inline del modal, primera pregunta y último tema en la
  dimensión disponible del navegador (~1280×720). Responsive en otros tamaños,
  consola DevTools y reproducción audible humana permanecen NO VERIFICADOS.
- Se ejecutó limpieza sintética; el catálogo se conservó. P09 permanece en
  VALIDACIÓN. No se inició P10 y no hubo commits ni despliegues.

## 2026-10-11 — P10-CATALOG-UNBLOCK-02

- Se verificó `P10_CATALOGO_SEGURO.zip` y se añadió `tests/p10/import-catalog.php`,
  que valida el SQL autorizado, no ejecuta el dump completo y carga de forma
  transaccional/idempotente sólo en `clinica_dolor_demo_20261010_final`.
- La tabla sintética estaba vacía pero tenía el nombre heredado `tratamiento`;
  se corrigió a `procedimiento` y se añadió `comentarios` a la tabla sintética
  de respuestas. El catálogo final contiene exactamente 10/10 registros y no
  se modificó `bd_clinica`.
- Se amplió `tests/p10/http-crud.php` para validar AJAX, No/Sí, dependencias,
  efectos adversos, descripción, comentarios, recuperación, autorización
  cruzada, finalización, `next_url` e idempotencia. El frontend conserva las
  rutas relativas del mismo origen, el mensaje genérico con «Reintentar» y el
  modal centrado con `display:grid`.
- El controlador deriva el acceso de la sesión y la ruta, no confía en el rol
  enviado por el navegador, bloquea el acceso cruzado y responde con mensajes
  genéricos ante fallos técnicos. La finalización de módulo 8 quedó idempotente
  y rechaza catálogo inexistente o respuestas incompletas.
- La línea base `tests/p10/` quedó restringida a
  `clinica_dolor_demo_20261010_final`. PASS: preflight, login, GET/AJAX propio,
  302 sin sesión, 403 cruzado, CRUD persistente de procedimientos, no-op
  repetido, rechazo de tratamiento inexistente y limpieza. No se utilizó
  `bd_clinica`.
- PASS: catálogo 10/10, flujo HTTP sintético completo y limpieza de respuestas
  y finalización; el catálogo se conserva.
- NO VERIFICADO: no-op SQL medido a nivel de sentencia, NetworkError observado
  en navegador, centrado del modal observado en navegador, responsive,
  capturas, consola DevTools, teclado visual y reproducción audible humana.
- Se mantiene P10 en VALIDACIÓN, no se inició P11 y no se realizaron commits ni
  despliegues. Las vulnerabilidades documentales ACT-05C-02 y el renderer
  legado con siembra durante GET permanecen documentados como riesgos abiertos.

## 2026-10-11 — P10-MODAL-EXACT-PARITY-01

- Se identificó que el encabezado verde y la distribución distinta de botones
  provenían del bloque scoped `patient-procedures-modal` exclusivo de P10.
- P10 ahora reutiliza la estructura visual canónica de P04: modal Bootstrap
  centrado, encabezado blanco/lavanda, título y bordes institucionales, footer
  con Escuchar/Detener/Entendido y botones secundarios morados equivalentes.
- Se preservaron la lógica P10, los IDs de voz, las funciones de apertura y
  cierre, `SpeechSynthesis`, foco, Escape, clic fuera, audio es-MX y la guarda
  contra reapertura. No se modificaron catálogos, CRUD, persistencia,
  autorización, finalización, `next_url` ni otras vistas.
- PASS estático: contrato de modal, mock de audio, PHP lint, sintaxis JS y
  `git diff --check`. NO VERIFICADO: comparación visual renderizada, capturas,
  consola DevTools, foco observado y audio audible humano por falta de una
  sesión sintética reproducible en navegador.
- P10 permanece en VALIDACIÓN; no hubo commits ni despliegues.
