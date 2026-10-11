# Pruebas y criterios de aceptación
## Sprint final de demostración — 2026-10-10

### Fixture sintético ampliado

Se amplió exclusivamente `tests/fixtures/security-fixtures.sql`. El catálogo
ahora contiene los nueve módulos reales observados en
`app/Controllers/ModulosController.php`, con sus nombres, imágenes existentes y
URLs de ruta. Se agregaron únicamente las tablas mínimas que
`PacienteModulosModelo::porcentajeModulo1()` a `::porcentajeModulo9()` consultan;
no se modificó el esquema real ni la lógica de cálculo.

La base aislada utilizada fue `clinica_dolor_demo_20261010_final`, con clínica
`9101`, médico `9201` y paciente `9301`, todos sintéticos. El fixture marca como
finalizados los módulos 1 y 2 y contiene un antecedente familiar sintético para
ejercitar el cálculo del módulo 2. El resultado observado fue:

| Módulo | Porcentaje observado | Estado visual | Enlace |
|---:|---:|---|---|
| 1. Ficha de identificación del paciente | 92% | Completada | Sin enlace: finalizada |
| 2. Antecedentes familiares | 100% | Completada | Sin enlace: finalizada |
| 3. Antecedentes Personales No Patológicos | 0% | Disponible | `/historia-clinica/antecedentes-personales-no-patologicos/paciente/9301` |
| 4. Antecedentes Personales Quirúrgicos | 100% | Con avance | `/historia-clinica/antecedentes-personales-quirurgicos/paciente/9301` |
| 5. Antecedentes Personales Patológicos | 50% | Con avance | `/historia-clinica/antecedentes-personales-patologicos/paciente/9301` |
| 6. Medicación Actual | 100% | Con avance | `/historia-clinica/medicacion-actual/paciente/9301` |
| 7. Medicamentos que ha utilizado para controlar el dolor | 100% | Con avance | `/historia-clinica/medicacion-control-dolor/paciente/9301` |
| 8. Procedimientos que ha utilizado para controlar el dolor | 100% | Con avance | `/historia-clinica/procedimientos-control-dolor/paciente/9301` |
| 9. Evaluación del dolor | 100% | Con avance | `/historia-clinica/evaluacion-dolor/paciente/9301` |

El comportamiento de los módulos 4, 6, 7, 8 y 9 refleja literalmente las
consultas existentes, que devuelven 100% cuando no hay filas; por ello no se
reinterpretó ese resultado como una nueva regla clínica.

### Recorrido de demostración

| Paso | Resultado | Evidencia observable |
|---|---|---|
| Login médico sintético | PASS | `/clinica/acceso` redirigió a `/clinica` |
| Dashboard Doctor | PASS | 1 paciente autorizado, tres accesos rápidos, un footer |
| Listado y acciones | PASS | Paciente `9301`; menú con expediente, PIN, historia y edición |
| Expediente individual | PASS | `/clinica/paciente/9301`; resumen, tablas y shell médico |
| Historia Clínica y acciones | PASS | `table2`; 9 módulos, porcentajes y dropdown editar/resumen |
| Cierre de sesión médico | PASS | Redirección a `/clinica/acceso` |
| Login paciente sintético | PASS | `/historia-clinica/acceso` redirigió a `/historia-clinica` |
| Dashboard Paciente | PASS | 9 tarjetas, 2 completadas, 7 enlaces disponibles |
| Continuación de módulo | PASS | `/historia-clinica/antecedentes-personales-no-patologicos/paciente/9301` renderizó |

### Validación técnica y visual

- PASS: PHP lint de `app/views/paciente/index.php`.
- PASS: consola JavaScript sin errores en ambos dashboards y en el módulo
  paciente recorrido.
- PASS: sin overflow horizontal en el dashboard paciente a `1280x720` con las
  nueve tarjetas; en el dashboard médico la medición actual disponible fue
  `1280x720`.
- PASS: captura full-page del dashboard paciente con las nueve tarjetas y del
  dashboard médico con su shell completo; las capturas fueron observadas en el
  navegador aislado y no contienen datos reales.
- LIMITACIÓN: el backend del navegador in-app disponible en esta sesión no
  aplicó de forma efectiva los overrides de viewport solicitados. Por ello no
  se declara verificación nueva para `1440x900`, `768x1024` y `375x667` del
  dashboard médico ni del dashboard paciente con el fixture de nueve módulos.
  Las capturas anteriores de otras iteraciones no se presentan como evidencia
  final de este fixture. No se inventan capturas ni resultados para esas
  resoluciones.

### Funciones no verificadas en este sprint

- Escritura/edición de los nueve formularios clínicos.
- Generación de PIN, alta de pacientes, nuevas recetas, notas, laboratorios y
  COFEPRIS desde el dashboard.
- Descarga o visualización de documentos clínicos.
- Pruebas de producción, respaldos, restauración y observabilidad.
- Accesos cruzados entre clínicas; permanecen cubiertos por el backlog de
  seguridad de ACT-05C-02 y no fueron modificados.

## Ejecutadas en esta iteración

- Validación sintáctica PHP mediante `php -l`.
- Revisión de rutas modificadas.
- Inspección de cambios de markup y CSS.
- Pruebas HTTP locales controladas, sin cookie: búsquedas clínicas y descargas respondieron 302 hacia /clinica/acceso.
- /buscar/web permaneció público y respondió sin redirección.
- Con JWT sintético local y paciente inexistente (0/999999), las rutas protegidas respondieron 404.
- Pruebas visuales autenticadas de ambos dashboards ejecutadas con sesiones JWT sintéticas; no se usaron cuentas ni expedientes reales.

## Regresión funcional

- Dashboard médico carga sin cambiar sus enlaces.
- Pacientes conserva alta, edición, expediente, PIN y módulos.
- Matriz de módulos conserva enlaces de edición y resumen.
- Las clases visuales nuevas no eliminan campos ni scripts existentes.
- Las rutas `/buscar/web` y portal público permanecen públicas.
- Las rutas de búsqueda clínica requieren sesión.
- Las rutas de descarga y PDF requieren sesión clínica.

## Seguridad

- Sesiones sintéticas sin registros existentes reciben rechazo 404 al solicitar pacientes inexistentes.

## Área médica — expediente individual — ACT-05A — 2026-10-10

### Entorno

- Servidor local aislado: `127.0.0.1:8104`.
- Base aislada: `clinica_dolor_audit_act05a_20261010`.
- Sesión: médico sintético de clínica `9101`.
- Paciente auditado: `9301`, sintético.
- No se leyeron filas de la base productiva para generar capturas y no se
  ejecutaron escrituras clínicas.

### Resultados

| Prueba | Resultado | Evidencia |
|---|---|---|
| Carga de `/clinica/paciente/9301` | PASS | Vista renderizada sin `Fatal error`, `PDOException` ni tabla faltante |
| Tablas auxiliares del expediente | PASS | Se renderizaron `tableNotasSubsecuentes`, `tableLaboratorio` y `tableCofepris` |
| Receta, PIN e historia clínica | PASS | Se observaron `tableRecetas`, `tablePin` y `table2` |
| Comparación de DDL auxiliar | PASS | 15/10/16 columnas coincidentes para nota/laboratorio/COFEPRIS |
| Paciente de otra clínica en vista | PASS | Médico 9101 recibió `403 No autorizado.` para paciente 9302 |
| Endpoints AJAX cruzados | PASS | Notas, laboratorio, recetas y COFEPRIS devolvieron `No autorizado.` |
| PHP lint | PASS | Controladores, modelos y vista del expediente sin errores sintácticos |
| `/clinica/paciente/9301` a 1280×720 | PASS visual con observaciones | Captura real en la sesión; tarjetas y tablas visibles |
| `/clinica/paciente/9301` a 768×1024 | PASS técnico con observaciones | Captura real; se observó wrapping excesivo en columnas estrechas |
| `/clinica/paciente/9301` a 375×667 | PASS técnico con observaciones | Capturas reales de encabezado y parte inferior; tablas conservan scroll interno |

### Limitaciones de la evidencia

- No se probó la descarga de laboratorio porque el fixture no agrega un archivo
  clínico sintético.
- No se probó alta/edición de COFEPRIS porque `cofepris_folio` no forma parte
  del fixture ACT-05A.
- La base aislada contiene un módulo de historia clínica sintético, no los
  nueve conjuntos de tablas de respuestas.
- No se modificó el código de runtime ni el diseño del expediente.

## ACT-05B-01 — Shell y encabezado del expediente — 2026-10-10

### Cambios validados

- `pacientes-detalle.php` usa `app-shell-body app-shell-doctor`.
- El topbar muestra el médico de la sesión sintética.
- El footer es `footer-doctor.php` y presenta `© 2026 · tratamientosdeldolor.org`.
- Se agregó una cabecera compacta con datos existentes y acciones de listado,
  edición y distribución.
- No se alteraron tablas, AJAX, rutas clínicas ni operaciones de persistencia.

### Matriz de pruebas

| Prueba | Resultado | Evidencia |
|---|---|---|
| PHP lint de controlador y vista | PASS | `php -l` sin errores |
| Ocho secciones presentes | PASS | Conteo DOM: 8 tarjetas |
| Sidebar activo | PASS | Elemento activo: `Expediente` |
| Topbar con sesión médica | PASS | `Doctor sintético A / Equipo clínico` |
| Footer compartido | PASS | `footer-doctor.php`, texto `© 2026 · tratamientosdeldolor.org` |
| Regreso a pacientes | PASS | `/clinica/pacientes` carga correctamente |
| Edición | PASS | `/clinica/paciente/editar/9301` carga formulario |
| Paciente de otra clínica | PASS | Médico 9101 recibe `No autorizado.` para paciente 9302 |
| 1440×900 | PASS | Sin overflow horizontal ni errores visibles |
| 1280×720 | PASS | Cabecera horizontal y acciones visibles |
| 768×1024 | PASS | Sidebar 220 px, sin solapamiento con `#main` |
| 375×667 | PASS | Cabecera apilada, sin overflow horizontal |

Las capturas antes corresponden a la evidencia de ACT-05A; las capturas
después fueron realizadas sobre el fixture aislado de ACT-05A en esta sesión.

## ACT-05B-02 — Composición estructural del expediente — 2026-10-10

### Resultado

- Resumen unificado de identificación, contacto y motivo: PASS.
- Historia Clínica visualmente posterior al resumen y a ancho completo: PASS.
- Tarjetas de PIN, nota, laboratorio, receta y COFEPRIS conservadas: PASS.
- IDs `tablePin`, `tableNotasSubsecuentes`, `tableLaboratorio`,
  `tableRecetas`, `tableCofepris` y `table2`: PASS.
- Cargas AJAX con registros sintéticos: PASS después de completar la carga.
- Autorización cruzada para paciente 9302: PASS, respuesta `No autorizado.`.
- Control `toggleSize()`: PASS; alterna las tarjetas secundarias entre dos
  columnas y ancho completo sin perder tablas.

### Matriz responsive

| Viewport | Resultado | Evidencia |
|---|---|---|
| 1440×900 | PASS | Resumen e Historia a ancho completo; secundarias en dos columnas |
| 1280×720 | PASS | Dos columnas secundarias, sin overflow horizontal |
| 768×1024 | PASS | Resumen, Historia y secundarias en una columna |
| 375×667 | PASS | Cabecera y resumen apilados; sin overflow horizontal |

La validación utilizó exclusivamente la base aislada
`clinica_dolor_audit_act05a_20261010` y el paciente sintético `9301`.

### No ejecutado

- No se modificaron ni probaron operaciones de alta, edición o descarga
  clínica.
- No se utilizaron datos productivos.
- No se implementó ACT-05B-03 ni ACT-05B-06.

### No probado en esta actividad

- No se probaron altas, ediciones o descargas clínicas.
- No se modificaron ni probaron los módulos internos más allá de verificar que
  sus enlaces continúan presentes.
- No se ejecutaron pruebas contra datos productivos.
- La validación cruzada entre dos recursos existentes se ejecutó en la base aislada de Iteración 3, sin datos reales.
- Token manipulado o expirado se rechaza.
- Cookie no es accesible mediante JavaScript.
- POST sin CSRF se rechaza cuando el control sea implementado.

Resultado de las iteraciones previas: se verificó el rechazo de sesión ausente y de identificadores inexistentes. Resultado Iteración 3: la separación entre dos clínicas y dos pacientes sintéticos quedó verificada para las rutas cubiertas; descargas sin relación explícita con paciente permanecen abiertas.

## Iteración 3: matriz de pruebas aisladas

| Caso | Resultado esperado | Resultado observado |
|---|---:|---:|
| Médico A lee paciente A | 200 | 200 |
| Médico A lee paciente B | 403 | 403 |
| Médico B lee paciente B | 200 | 200 |
| Médico B lee paciente A | 403 | 403 |
| Médico A consulta receta de paciente A | 200 | 200 |
| Médico B consulta receta de paciente A | 403 | 403 |
| Médico A crea receta de paciente A | 200 y una fila nueva | 200 y conteo 2 |
| Médico A crea receta de paciente B | 403 y sin fila nueva | 403 |
| Paciente A lee su ficha | 200 | 200 |
| Paciente A lee ficha de paciente B | 403 | 403 |
| Médico descarga archivo sintético autorizado por sesión | 200 | 200 |
| Sin sesión descarga archivo | 302 | 302 |
| Traversal de descarga | 404 | 404 |
| KPI clínica A / clínica B | 1 / 1 | 1 / 1 |

Los helpers están en tests/security y la base de datos es local, separada y desechable; no son parte del runtime productivo.

## Responsive y accesibilidad

- 360–430 px: menú, búsqueda, tarjetas y tablas no rompen el viewport.
- 768 px: navegación y formularios conservan lectura.
- Escritorio: sidebar, toolbar y contenido mantienen jerarquía.

- Portal paciente: tarjetas de módulos conservan sus URLs, muestran avance real en aria-valuenow y no eliminan módulos finalizados.

## Iteración 2: dashboard QA

- Ambos dashboards cargaron con sesiones JWT sintéticas.
- Se capturaron 375, 768, 1024 y 1440 px para médico y paciente.
- El árbol de accesibilidad mostró nombres para sidebar, menú móvil, búsqueda, títulos, enlaces y tarjetas.
- La consola del navegador no reportó errores ni warnings.
- El overflow móvil quedó corregido: scrollWidth igual al viewport.
- Paciente ficticio sin respuestas: porcentajes renderizan 0 y no muestran warnings PHP.

La evidencia detallada de tamaños, hallazgos y refinamientos está en visual-validation.md.
- `lang="es-MX"`, etiquetas de búsqueda, `alt`, `scope` y foco visible.

## Corrección de accesos médico y paciente

### Casos de validación previstos

| Acceso | Entrada | Resultado esperado |
|---|---|---|
| Médico | usuario y contraseña vacíos | `Ingresa tu usuario y contraseña.`; ambos campos inválidos; foco en `usuario` |
| Médico | usuario vacío | `Ingresa tu usuario.`; foco en `usuario` |
| Médico | contraseña vacía | `Ingresa tu contraseña.`; foco en `password` |
| Médico | ambos completos y autenticación rechazada | `Usuario o contraseña incorrectos.` sin revelar si el usuario existe |
| Paciente | PIN vacío | `Ingresa tu PIN de acceso.`; foco en `Pin` |
| Paciente | PIN rechazado o vencido | `PIN incorrecto o no vigente.` |

La validación del cliente evita enviar campos obligatorios vacíos. Los
controladores repiten la validación y normalizan respuestas de autenticación
fallida; los errores técnicos no se muestran en la interfaz. Se conservaron
los IDs `usuario`, `password`, `Pin` y las funciones `loginClinica()` y
`loginPaciente()`.

### Responsive y navegación de portales

- Se verificó el layout de dos columnas en el navegador local de escritorio.
- Se verificó la composición apilada y el scroll natural en el navegador local
  reducido disponible para la auditoría.
- Evidencia visual real de esta ejecución: ambos accesos en un viewport local
  de escritorio de 1280x720 y ambos accesos en el viewport visible reducido
  del navegador integrado, aproximadamente 646x646. En escritorio el enlace
  secundario y el formulario completo quedan visibles; en el viewport reducido
  se observa el apilado y la barra de scroll vertical natural, sin barra
  horizontal.
- Los enlaces secundarios conservan los destinos existentes:
  `/historia-clinica/acceso` desde el acceso médico y `/clinica/acceso` desde
  el acceso paciente.
- La matriz exacta de 375x667, 390x844, 430x932, 768x1024 y 1366x768, junto
  con zoom 125% y 200%, requiere un runner que permita fijar el viewport y
  leer `innerHeight`/`scrollHeight` de forma directa. No se declaran esas
  capturas o métricas como ejecutadas mientras ese runner no esté disponible.

## Corrección responsive compacta de accesos

### Evidencia verificada

- En el viewport reducido disponible del navegador integrado, el árbol
  accesible ya no contiene el título narrativo, la descripción ni los tres
  beneficios del panel introductorio.
- La captura móvil muestra primero el logotipo compacto y enseguida `Portal
  médico`/`Portal de pacientes`, el título operativo, los campos y el botón.
- La captura del acceso paciente muestra el formulario completo y su enlace de
  cambio de portal dentro de la primera vista disponible.
- La captura local de escritorio 1280x720 mantiene el panel introductorio,
  las dos columnas, la decoración y el formulario completo sin regresión.

### Validación pendiente de runner

El runtime local no expone en esta sesión una instancia Playwright ni un
override de viewport para fijar exactamente 320x568, 375x667, 390x844,
430x932, 768x1024, 900x800 y 1280x720, ni para aplicar zoom real de 200 %.
No se inventan esas capturas o métricas; deben ejecutarse en CI o en un
runner Playwright antes de aprobar el cierre visual definitivo.

### Limitación de autenticación sintética (histórica)

Esta limitación quedó resuelta el 2026-10-10 mediante credenciales sintéticas
hashadas agregadas a `tests/fixtures/security-fixtures.sql`. La ejecución se
hizo en la base desechable `clinica_dolor_audit_auth_20261010`, separada de
`DB_DATABASE`; no se usaron cuentas ni expedientes reales. El resultado vigente
se registra en la sección siguiente.

## Corrección de errores de autenticación visibles

### Causa reproducida

El controlador ya devolvía respuestas JSON 400/401 y `login.js` asignaba el
texto a `#responseMessage`, pero el nodo estaba después del botón `Entrar`.
En móvil el mensaje quedaba separado de los campos y podía quedar fuera del
primer viewport, aunque existiera en el DOM. Además, el cliente no diferenciaba
explícitamente estados 429, respuestas no JSON ni errores de red.

### Matriz ejecutada

| Caso | Resultado |
|---|---|
| Médico: usuario/contraseña vacíos | PASS — mensaje específico y foco en usuario |
| Médico: usuario inexistente/credencial rechazada | PASS — `Usuario o contraseña incorrectos.` visible antes del botón |
| Paciente: PIN vacío | PASS — `Ingresa tu PIN de acceso.` visible |
| Paciente: PIN incorrecto | PASS — `PIN incorrecto o no vigente.` visible antes del botón |
| HTTP 401 | PASS — fallback genérico sin revelar existencia |
| HTTP 403 | PASS — fallback genérico visible |
| HTTP 429 | PASS — mensaje de límite de intentos visible |
| Fallo de red | PASS — mensaje genérico visible sin detalle interno |
| Corrección de campo después del error | PASS — alerta y estados obsoletos se limpian |
| Doble envío | PASS — botón deshabilitado y guardia `authSubmitting` |
| Credenciales/PIN válidos | PASS — fixture sintética aislada; navegación al dashboard y cierre de sesión comprobados |

Los estados 403/429 se ejecutaron con un router temporal local y datos
sintéticos; el router se eliminó al terminar. La visibilidad se comprobó en
el árbol accesible y en capturas del DOM renderizado, no solo por código HTTP.

## Validación positiva de autenticación y cierre de sesión

| Portal | Entrada sintética | Resultado observado | Cierre de sesión |
|---|---|---|---|
| Médico | Usuario y contraseña válidos de fixture | PASS — navegación a `/clinica`; dashboard carga y muestra la sesión | PASS — `/cerrar-sesion` redirige a `/clinica/acceso` |
| Paciente | PIN válido de fixture | PASS — navegación a `/historia-clinica`; portal carga | PASS — `/cerrar-sesion` redirige a `/historia-clinica/acceso` |

La prueba se ejecutó en `http://127.0.0.1:8099` con el router local de
auditoría y la base `clinica_dolor_audit_auth_20261010`. Las credenciales se
almacenaron únicamente como hashes dentro del fixture; no se capturaron ni
utilizaron datos reales. El servidor temporal quedó detenido al finalizar.

## Dashboard médico — validación de la iteración actual

- `php -l app/views/clinica/index.php`: PASS.
- Dashboard médico autenticado con sesión sintética: PASS.
- Conteo mostrado en el dashboard: `1`, coincidente con el paciente sintético
  de la clínica A en la base aislada.
- Las tres tarjetas de acceso conservan las rutas `/clinica/pacientes`,
  `/clinica/paciente/nuevo` y `/clinica/recetas/nueva`: PASS mediante inspección
  del DOM.
- Vista de escritorio observada con `innerHeight=720`, `scrollWidth=1265` y
  `scrollWidth` igual al ancho útil: PASS sin overflow horizontal.
- Vista reducida observada con `innerWidth=661`, `innerHeight=627` y
  `scrollWidth=646`: PASS sin overflow horizontal; el scroll vertical es
  natural porque el contenido supera la altura disponible.
- La vista no contiene tarjetas de citas, alertas o KPIs no respaldados por el
  controlador: PASS por inspección de `HomeController` y del markup.

La comprobación exacta de esos viewports y del zoom 125/200 pertenecía a la
iteración previa y no se declaró ejecutada en esa sesión. La validación final
del dashboard se registra explícitamente en la sección siguiente, con su
método CDP y sus límites.

## Validación visual final — Dashboard Doctor — 2026-10-10

### Método y alcance

Playwright no está instalado ni disponible como dependencia local en este
proyecto. Para no inventar una ejecución Playwright, se utilizó Chrome
154.0.8037.58 mediante un cliente CDP temporal, con
`Emulation.setDeviceMetricsOverride` y `Page.captureScreenshot`. La sesión,
la base y los datos fueron sintéticos; no se modificaron datos productivos.
El cliente temporal y los servidores de prueba se eliminaron o detuvieron al
finalizar.

Capturas completas:

- `docs/evidence/dashboard-doctor-final/cdp-final/dashboard-doctor-1440x900.png`
- `docs/evidence/dashboard-doctor-final/cdp-final/dashboard-doctor-1280x720.png`
- `docs/evidence/dashboard-doctor-final/cdp-final/dashboard-doctor-768x1024.png`
- `docs/evidence/dashboard-doctor-final/cdp-final/dashboard-doctor-375x667.png`

### Métricas comprobadas

| Viewport | innerWidth × innerHeight | clientWidth | scrollWidth | scrollHeight | Overflow horizontal |
|---|---:|---:|---:|---:|---|
| 1440 × 900 | 1440 × 900 | 1440 | 1440 | 900 | No |
| 1280 × 720 | 1280 × 720 | 1265 | 1265 | 791 | No |
| 768 × 1024 | 768 × 1024 | 768 | 768 | 1024 | No |
| 375 × 667 | 375 × 667 | 360 | 360 | 1164 | No |

El scroll vertical de 1280 × 720 y 375 × 667 es natural: el contenido supera
la altura disponible y no se ocultó con `overflow: hidden`.

### Acciones visibles y autorización de datos

Con sesión sintética de la clínica `9101`, las acciones visibles conservaron
destinos funcionales y respondieron HTTP 200:

| Acción | Ruta | Resultado |
|---|---|---|
| Ver pacientes / Directorio | `/clinica/pacientes` | PASS |
| Nuevo paciente | `/clinica/paciente/nuevo` | PASS |
| Nueva receta | `/clinica/recetas/nueva` | PASS |

También se comprobó con la misma sesión sintética el destino de cada entrada
del sidebar visible: `/clinica` (200), `/clinica/pacientes` (200),
`/clinica/cofepris` (200), `/clinica/recetas` (200), `/clinica/perfil` (200) y
`/cerrar-sesion` (302 hacia el acceso, comportamiento esperado).

El indicador mostrado es exclusivamente `total_pacientes`. La fuente está
verificada en `HomeController::indexClinica()` y
`ClinicaModel::countPacientes()`, que ejecuta el conteo con
`WHERE id_clinica = :id_clinica`. En la base aislada, las clínicas sintéticas
`9101` y `9102` devolvieron una fila cada una; el dashboard de la sesión `9101`
mostró `1`.

### Hallazgos visuales corregidos

- En 768 × 1024, el sidebar ocupaba 276 px mientras el contenido reservaba
  220 px; se corrigió el ancho compacto a 220 px y se cambió la rejilla de
  acciones a dos columnas para evitar solapamiento y compresión de texto.
- En 375 × 667, la identidad del médico quedaba reducida a un icono y la
  búsqueda compartía una fila demasiado estrecha; se corrigió el topbar a dos
  filas, con identidad compacta y búsqueda de ancho completo.

### Diferencias y limitaciones pendientes

- El diseño aprobado contiene conceptos de citas, alertas, recetas emitidas y
  más indicadores; el controlador actual no entrega esas fuentes. No se
  simularon ni se agregaron.
- El runner CDP no pudo cargar la importación remota de Google Fonts, por lo
  que la apariencia tipográfica exacta debe confirmarse en un navegador con
  acceso a la fuente. No se cambió la tipografía ni el contrato visual por
  esta limitación del entorno.
- No se ejecutó zoom real de 125 % ni 200 % en esta validación; queda como
  criterio pendiente para un runner Playwright o navegador interactivo.

## Área médica — listado y alta de pacientes — 2026-10-10

### Método

Se utilizó Chrome 154.0.8037.58 con CDP y una sesión JWT sintética del médico
de la clínica `9101`, usando la base aislada de auditoría. Playwright no está
instalado localmente. Las capturas no representan datos reales.

### Listado de pacientes

Capturas:

- `docs/evidence/patients-list-final/cdp/patients-1440x900.png`
- `docs/evidence/patients-list-final/cdp/patients-1280x720.png`
- `docs/evidence/patients-list-final/cdp/patients-768x1024.png`
- `docs/evidence/patients-list-final/cdp/patients-375x667.png`

| Viewport | Filas | scrollWidth | Overflow horizontal |
|---|---:|---:|---|
| 1440 × 900 | 1 | 1440 | No |
| 1280 × 720 | 1 | 1265 | No |
| 768 × 1024 | 1 | 768 | No |
| 375 × 667 | 1 | 360 | No |

Resultados:

- Búsqueda Simple-DataTables con `9301`: PASS — una fila visible y coincidente.
- Acceso `Nuevo paciente`: PASS — `/clinica/paciente/nuevo` responde 200.
- Acciones por expediente conservadas en el DOM con etiquetas accesibles.
- Autorización cruzada del listado: PASS — clínica `9101` sólo mostró `9301`;
  clínica `9102` sólo mostró `9302`.
- No se ejecutó ninguna consulta contra datos productivos.

### Registro de nuevo paciente

Capturas:

- `docs/evidence/patient-form-final/cdp/patients-1440x900.png`
- `docs/evidence/patient-form-final/cdp/patients-1280x720.png`
- `docs/evidence/patient-form-final/cdp/patients-768x1024.png`
- `docs/evidence/patient-form-final/cdp/patients-375x667.png`

| Viewport | Controles | Secciones | scrollWidth | Overflow horizontal |
|---|---:|---:|---:|---|
| 1440 × 900 | 32 | 4 | 1425 | No |
| 1280 × 720 | 32 | 4 | 1265 | No |
| 768 × 1024 | 32 | 4 | 753 | No |
| 375 × 667 | 32 | 4 | 360 | No |

Resultados:

- IDs originales presentes: PASS — 32 de 32.
- `GuardarPaciente()` y endpoint `/clinica/paciente/insert-edit`: PASS por
  inspección y ejecución controlada.
- Payload sintético: PASS — el endpoint respondió `resultado=true`, el registro
  se insertó en la base aislada y fue eliminado después de la prueba.
- Doble envío: guardia `pacienteGuardando` y botón deshabilitado durante la
  petición: PASS por inspección del flujo JavaScript.
- Feedback de error: `#mensaje`, `role="alert"` y `aria-live="polite"`
  presentes.
- Cancelar / Volver a pacientes: destino `/clinica/pacientes` responde 200.

### Limitación de fixture

El fixture aislado actual declara `pc_paciente.id` sin `AUTO_INCREMENT`. Por
eso `lastInsertId()` respondió `0` aunque el INSERT sintético fue exitoso; la
persistencia se comprobó y el registro se limpió, pero no se declara PASS para
la redirección posterior al expediente. El esquema productivo no se modificó;
la compatibilidad del `AUTO_INCREMENT` debe verificarse contra el esquema real
antes de publicar el flujo completo.

## ACT-01 — Sidebar médico — 2026-10-10

### Causa y alcance

- Causa confirmada: el pseudo-elemento `::before` definido para el estado
  activo en `public/assets/css/app.css` se mostraba como una línea vertical
  morada dentro del shell médico.
- Cambio limitado a `public/assets/css/app.css` y
  `app/Helpers/Sidebar.php`; no se cambiaron rutas, destinos ni comportamiento
  de navegación.
- No se tocaron footer, listado de acciones de pacientes, formulario de alta,
  Portal Paciente ni web pública.

### Validación controlada

Se usó Chrome mediante el navegador local aislado y una sesión sintética de
doctor (`id_clinica=9101`, paciente sintético `9301`).

| Vista / tamaño observado | Pseudo-elemento activo | Estado activo | `aria-current` | Overflow documento |
|---|---|---|---|---|
| Dashboard Doctor — 1280 × 720 | `display:none`, `content:none` | PASS | `page` | No |
| `/clinica/pacientes` — 1280 × 720 | `display:none`, `content:none` | PASS | `page` | No |
| Shell móvil — 661 × 627 | `display:none`, `content:none` | PASS | `page` | No |

La captura visual del sidebar abierto en móvil mostró el elemento Pacientes
con fondo lavanda, texto e icono violetas y sin línea lateral. Las capturas de
dashboard y listado en escritorio mostraron la misma identidad.

La navegación por teclado se ejecutó sobre el enlace Pacientes y avanzó al
enlace Cofepris; `:focus-visible` quedó activo con anillo
`rgba(125, 69, 193, .28)`, sin alterar el destino.

### Limitación

El runner disponible no expone un control independiente de viewport tablet;
por ello no se declara una captura visual tablet PASS. Se verificó por
inspección que la regla de ACT-01 está acotada a `body.app-shell-body` y no
depende de un breakpoint; los breakpoints existentes de 768–1100 px y móvil
no fueron modificados. Requiere confirmación visual con un runner que permita
fijar, por ejemplo, 768 × 1024.

## ACT-02 — Footer médico

### Alcance y método

Se validaron las rutas `/clinica`, `/clinica/pacientes` y
`/clinica/paciente/nuevo` con sesión de doctor sintética en la base aislada.
La inspección visual se realizó con el navegador local controlado; el runner
disponible expuso 1280 × 720 para escritorio y 661 × 627 para móvil. No se
declara una captura en un tamaño distinto del realmente observado.

### Matriz de resultados

| Ruta | Viewport observado | Footers DOM | Texto | `position` | Overflow horizontal |
|---|---:|---:|---|---|---|
| `/clinica` | 1280 × 720 | 1 | `© 2026 · tratamientosdeldolor.org` | `static` | PASS |
| `/clinica` | 661 × 627 | 1 | `© 2026 · tratamientosdeldolor.org` | `static` | PASS |
| `/clinica/pacientes` | 1280 × 720 | 1 | `© 2026 · tratamientosdeldolor.org` | `static` | PASS |
| `/clinica/pacientes` | 661 × 627 | 1 | `© 2026 · tratamientosdeldolor.org` | `static` | PASS |
| `/clinica/paciente/nuevo` | 1280 × 720 | 1 | `© 2026 · tratamientosdeldolor.org` | `static` | PASS |
| `/clinica/paciente/nuevo` | 661 × 627 | 1 | `© 2026 · tratamientosdeldolor.org` | `static` | PASS |

En las tres rutas el footer queda después del contenido, con altura medida de
aproximadamente 40.6 px en escritorio y 38.2 px en el viewport móvil. Se
capturó el final del documento para comprobar la separación respecto de la
tabla y del formulario; no se observaron superposiciones.

### Evidencia y límites

Las capturas del dashboard, listado y alta se presentaron en la entrega de
ACT-02 desde el navegador controlado. PHP lint fue PASS para el componente y
las tres vistas modificadas. No se ejecutaron pruebas contra datos reales ni
se modificó Portal Paciente. La validación visual independiente de tableta
queda pendiente porque el runner disponible no permite fijar ese viewport.

## ACT-03 — Dropdown de acciones en pacientes

### Auditoría previa

La vista tenía cuatro columnas de iconos por fila. La tabla se inicializa con
Simple-DataTables (`searchable`, `fixedHeight`, `perPageSelect: [10, 20, 50]`)
y guarda búsqueda, orden y tamaño de página en `localStorage`. Los destinos
fueron verificados en la vista y en `app/Config/routes.php`; el aislamiento de
paciente está aplicado por `AuthMiddleware::assertPatientAccess()` en los
controladores de expediente, PIN, módulos y edición.

### Resultados controlados

| Prueba | Resultado | Evidencia |
|---|---|---|
| Una sola columna y botón `Acciones` | PASS | 7 encabezados totales; un botón por fila |
| Cuatro opciones con etiquetas e iconos | PASS | expediente, PIN, historia clínica y edición |
| Parámetro del paciente `9301` | PASS | enlaces generados con `/9301` |
| Rutas propias con sesión clínica `9101` | PASS | cuatro respuestas HTTP 200 |
| Rutas del paciente `9302` de clínica `9102` | PASS | cuatro respuestas HTTP 403 |
| Apertura por clic | PASS | clon visible en `body`, cuatro `menuitem` |
| Cierre por Escape | PASS | `aria-expanded=false`, menú oculto y foco al botón |
| Cierre por clic fuera | PASS | plantilla restaurada en la celda |
| Flecha abajo, foco visible y Escape | PASS | botón con anillo violeta/lavanda durante navegación de teclado |
| Búsqueda sin coincidencias | PASS | Simple-DataTables muestra estado vacío |
| Restauración de búsqueda | PASS | fila y plantilla de cuatro acciones reaparecen |
| Última fila / borde del viewport | PASS | menú fijo reposicionado dentro del viewport; no se recorta |
| Overflow horizontal del documento | PASS | `scrollWidth === clientWidth` en viewport móvil y escritorio |
| PHP lint | PASS | vista modificada sin errores de sintaxis |

### Evidencia visual

Se capturó el listado con menú cerrado y abierto en el navegador local
controlado, en 1280 × 720 y 661 × 627. Las capturas muestran el menú fuera
del contenedor con scroll, con las cuatro opciones visibles. El runner no
permite fijar independientemente un viewport de 375 px ni una tableta; no se
declaran resultados en tamaños no observados.

No se ejecutaron acciones de escritura, no se modificaron consultas ni se
usaron expedientes reales. Las operaciones de PIN y edición continúan siendo
formularios GET de entrada y conservan sus endpoints POST de guardado.

## ACT-04 — Foco y acciones del formulario de nuevo paciente — 2026-10-10

### Diagnóstico y alcance

La inspección de `public/assets/css/bootstrap.css` confirmó la sombra azul de
`.form-select:focus`; la regla global `:focus-visible` de
`public/assets/css/app.css` también usaba un anillo azul. El ajuste quedó
limitado a `body.doctor-patient-form-page` en `public/assets/css/app.css`.
La plantilla PHP no se modificó: conserva 32 controles, sus IDs, el evento
`onsubmit`, `GuardarPaciente()` y `POST /clinica/paciente/insert-edit`.

### Matriz responsive controlada

La comparación de overflow usa `document.documentElement.scrollWidth <=
innerWidth`; comparar con `clientWidth` puede contar como overflow los píxeles
ocupados por la barra vertical del navegador.

| Viewport | Formulario / controles | Overflow horizontal | Estado visual |
|---:|---|---|---|
| 1366 × 768 | presente / 32 | PASS | PASS |
| 1280 × 720 | presente / 32 | PASS | PASS |
| 1024 × 768 | presente / 32 | PASS | PASS |
| 768 × 1024 | presente / 32 | PASS | PASS |
| 390 × 844 | presente / 32 | PASS | PASS |
| 375 × 667 | presente / 32 | PASS | PASS |
| 320 × 568 | presente / 32 | PASS | PASS |

En 1280 × 720 se capturó la sección final con Cancelar y Guardar visibles;
Guardar mostró foco de teclado violeta. En 390 × 844 se capturó el formulario
en una columna y el foco de teclado sobre `ApellidoPaterno`; una segunda captura
mostró ambos botones y el footer sin recorte. En los tamaños móviles el shell
conservó scroll vertical natural.

### Pruebas de estilos, accesibilidad y estabilidad

| Prueba | Resultado | Evidencia |
|---|---|---|
| Foco de `input` (`Nombres` / `ApellidoPaterno`) | PASS | Borde, sombra y outline violeta |
| Foco de `select` (`Sexo`) | PASS | Sin sombra azul; borde y sombra violeta |
| Foco de `textarea` | PASS por selector compartido | `.form-control`; no se modificó su resize |
| Navegación por teclado | PASS | Tab trasladó foco al siguiente campo y a Guardar con anillo visible |
| Cancelar | PASS | Fondo lavanda, borde violeta, texto contrastante y destino intacto |
| Guardar | PASS | Fondo `--clinic-primary`, texto blanco y sombra institucional |
| Estados inválidos | PASS por inspección de reglas | `.is-invalid` y `[aria-invalid="true"]` excluidos del foco válido; anillo rojo preservado |
| Estado disabled/loading de Guardar | PASS por inspección de reglas | selector `[disabled]` conserva cursor/opacity y añade tratamiento violeta atenuado |
| Autofill | PASS por inspección de reglas | no se reemplaza el comportamiento nativo de autofill |
| Consola JavaScript | PASS | sin errores en `tab.dev.logs()` |
| PHP lint | PASS | `app/views/clinica/paciente-nuevo-editar.php` |
| `git diff --check` | PASS | archivo CSS sin errores de whitespace |

### Regresión de guardado sintético

Se intentó una sola alta con datos ficticios en la base aislada del runner
(`clinica_dolor_audit_20261010_iter3`), sin datos reales. La petición atravesó
el flujo existente, pero redirigió a `/clinica/paciente/0` y mostró
`Paciente no válido.`. Es un FAIL preexistente de persistencia, no causado por
ACT-04: `tests/fixtures/security-fixtures.sql` define `pc_paciente.id` como
`INT NOT NULL` sin `AUTO_INCREMENT`, mientras `app/Models/ClinicaModel.php`
inserta sin `id` y usa `lastInsertId()`. No se cambió el esquema ni el modelo.
La corrección requiere una decisión separada sobre generación de IDs y queda
fuera de esta actividad.

### Resoluciones no probadas

No se ejecutaron en esta actividad 1440 × 900, 900 × 800, 430 × 932 ni 360 px.
Tampoco se probó zoom del navegador al 125 % o 200 %. Deben verificarse antes
de aprobar una entrega visual definitiva; los resultados anteriores no se
extrapolan a esos tamaños.

## Cierre de consolidación del área médica — 2026-10-10

### Fixture y persistencia

Se consultó en modo lectura la base real `bd_clinica` mediante metadatos de
`information_schema` y `SHOW CREATE TABLE`. La definición real verificó
`pc_paciente.id INT NOT NULL AUTO_INCREMENT` como clave primaria. El fixture
de `tests/fixtures/security-fixtures.sql` declaraba el mismo campo sin
`AUTO_INCREMENT`; por eso el modelo, que omite `id` y usa `lastInsertId()`, no
podía devolver un identificador válido en la base aislada anterior.

Se corrigió únicamente la definición del fixture, se cargó una base nueva
`clinica_dolor_audit_act04_20261010` y se repitió el alta con datos sintéticos:

| Verificación | Resultado |
|---|---|
| Fixture cargado con `AUTO_INCREMENT` | PASS |
| Alta por `POST /clinica/paciente/insert-edit` | PASS |
| ID generado | PASS — `9303` |
| Persistencia en `pc_paciente` | PASS — clínica sintética `9101` |
| Redirección | PASS — `/clinica/paciente/9303` |

La página de expediente redirigida muestra los datos creados, pero el fixture
no contiene las tablas auxiliares `nota_subsecuente`, `laboratorio` y
`cofepris`; sus paneles solicitan esas tablas y generan errores visibles. No se
amplió el fixture ni se modificaron módulos porque esa limitación no afecta el
alta, el ID ni la redirección, y hacerlo excedería esta consolidación.

### Auditoría visual conjunta

| Ruta | Viewport | Sidebar | Topbar | Footer | Controles/tabla | Overflow documento |
|---|---:|---:|---:|---:|---:|---:|
| `/clinica` | 1280 × 720 / 375 × 667 | 1 | 1 | 1 | dashboard | PASS |
| `/clinica/pacientes` | 1280 × 720 / 375 × 667 | 1 | 1 | 1 | tabla / dropdown | PASS |
| `/clinica/paciente/nuevo` | 1280 × 720 / 375 × 667 | 1 | 1 | 1 | 32 controles | PASS |

Los tokens computados coincidieron en las tres vistas (`--clinic-primary:
#5d16a8`, `--clinic-border: #dfd8f4`). Se capturaron comparativas estables en
ambos tamaños; en móvil se forzó una repintura después de la carga para evitar
el artefacto del loader del runner. No se detectó inconsistencia visual que
justifique otro rediseño dentro de esta fase.

### Regresión funcional

| Prueba | Resultado |
|---|---|
| Dropdown de acciones abre con cuatro opciones | PASS |
| Escape cierra y restaura `aria-expanded=false` | PASS |
| Búsqueda sintética y restauración de filas | PASS |
| Rutas de expediente, PIN, módulos y edición con paciente `9303` | PASS — destinos conservados |
| Responsive 375 px en las tres vistas | PASS — sin overflow real |
| Formulario con 32 controles y Cancelar/Guardar | PASS |
| Consola JavaScript en la ejecución consolidada | PASS en vistas auditadas |

Quedan fuera de esta ejecución 1440 × 900, 900 × 800, 430 × 932, 360 px y
zoom 125 %/200 %. No se declaran como probados.

## ACT-05B-03A — Registros clínicos del expediente — 2026-10-10

### Pruebas ejecutadas

Se utilizó la base aislada `clinica_dolor_audit_act05a_20261010` con los
pacientes sintéticos `9301` y `9302`, y médicos sintéticos de las clínicas
`9101` y `9102`.

| Prueba | Resultado | Evidencia comprobada |
|---|---|---|
| PHP lint de `pacientes-detalle.php` | PASS | Sin errores de sintaxis |
| Registros existentes, paciente `9301` | PASS | PIN, notas, laboratorio, receta y COFEPRIS renderizados |
| Estados vacíos, paciente `9302` | PASS | Cuatro tarjetas AJAX muestran estado vacío; PIN sintético permanece en su tabla |
| Búsqueda | PASS | Búsqueda `9501` en notas conserva la fila coincidente |
| Paginación/ordenamiento | PASS | Simple-DataTables continúa inicializándose en tablas con registros |
| Acciones de alta | PASS | Se conservaron funciones y parámetros originales en los cinco botones |
| `toggleSize()` | PASS | Anchos 471 → 941 → 471 px en 1280 px |
| Responsive poblado | PASS | 1440×900, 1280×720, 768×1024 y 375×667 |
| Overflow horizontal del documento | PASS | `scrollWidth` del documento no supera el viewport en las cuatro resoluciones |
| Aislamiento entre clínicas | PASS | Doctor B no puede abrir paciente 9301; responde `No autorizado.` sin SQL ni excepciones visibles |
| Consola del navegador | PASS | Sin errores registrados durante la carga validada |

La validación de estados de error de red/HTTP queda cubierta por el `catch`
genérico implementado en la vista, pero no se forzó una caída del servidor ni
se alteró un endpoint para producirla. No se realizaron operaciones de alta,
edición, descarga ni cambios clínicos.

### Evidencia visual

Se generaron capturas reales de la vista poblada en escritorio y móvil, y de
la vista del paciente sintético sin registros auxiliares. Las capturas se
mantuvieron dentro de la sesión local de validación para no persistir datos
clínicos ni archivos derivados en el proyecto.

## ACT-05B-03B — Densidad visual — 2026-10-10

### Medición comparativa

| Resolución | Medida | Antes | Después | Resultado |
|---|---|---:|---:|---|
| 1440×900 | Encabezado de tarjeta | 71 px | 64 px | PASS |
| 1440×900 | PIN | 248 px | 224 px | PASS |
| 1440×900 | Nota subsecuente | 289 px | 263 px | PASS |
| 1440×900 | Laboratorio | 313 px | 263 px | PASS |
| 1440×900 | Receta médica | 289 px | 263 px | PASS |
| 1440×900 | COFEPRIS | 289 px | 263 px | PASS |
| 375×667 | Encabezado de tarjeta | 114 px | 105 px | PASS |
| 375×667 | PIN | 310 px | 290 px | PASS |
| 375×667 | Nota subsecuente | 332 px | 309 px | PASS |
| 375×667 | Laboratorio | 331 px | 309 px | PASS |
| 375×667 | Receta médica | 298 px | 276 px | PASS |
| 375×667 | COFEPRIS | 331 px | 309 px | PASS |

En 1280×720, las tarjetas pobladas midieron `239/263/287/263/263 px`;
en 768×1024 midieron `224/263/287/263/263 px`. En ambas resoluciones los
encabezados midieron `64 px` y los botones `40 px`. No se reconstruyó una
medición histórica independiente para 1280×720 y 768×1024; se validó el
resultado final y se conservaron las capturas antes/después disponibles de
1440×900 y 375×667.

### Regresión

| Prueba | Resultado |
|---|---|
| Registros sintéticos del paciente 9301 | PASS |
| Estados vacíos del paciente 9302 | PASS; tarjetas AJAX de 128 px en 1280×720 |
| Búsqueda de notas | PASS; la fila 9501 permanece visible |
| `toggleSize()` | PASS; 471 → 941 → 471 px en 1280×720 |
| Foco y avance por teclado | PASS; botones con `tabIndex=0`, foco continúa al buscador |
| Overflow horizontal | PASS en 1440, 1280, 768 y 375 px |
| Historia Clínica | PASS; tabla y porcentajes sin cambios detectables |
| Consola JavaScript | PASS; sin errores en las cargas validadas |
| PHP lint y `git diff --check` | PASS |

No se ejecutaron altas, edición, descargas ni otras operaciones clínicas.

## ACT-05B-06A — Historia Clínica — 2026-10-10

### Auditoría y evidencia

La fuente de `app/views/clinica/pacientes-detalle.php` consulta el catálogo
`historia_clinica_modulos`, contiene nueve casos de porcentaje (`1` a `9`) y
agrega una fila independiente `Porcentaje total`. Antes de la corrección,
esa fila estaba en `tbody`; la ejecución aislada mostró dos filas para un
fixture de un módulo, barras con `aria-valuenow="0"` y `fixedHeight: true`.
No era un décimo módulo.

El fixture aislado disponible contiene deliberadamente un solo registro en
`historia_clinica_modulos` y no contiene las tablas de respuestas necesarias
para reproducir los nueve cálculos. Por ello no se alteró el fixture ni se
declara una prueba de nueve módulos ejecutada. La cobertura estructural se
verificó con los nueve `case` existentes y el catálogo consultado por la vista.

### Pruebas ejecutadas

| Prueba | Resultado | Evidencia |
|---|---|---|
| PHP lint de la vista y `PacienteModulosModelo` | PASS | Sin errores de sintaxis |
| Conteo del catálogo frente al resumen | PASS | `tbody` sólo contiene módulos; el total queda fuera de la tabla |
| Porcentaje visible y `aria-valuenow` | PASS | Fixture sintético: `92%` visible y `aria-valuenow="92"` |
| Editar y Ver resumen | PASS | URLs existentes conservadas con `url` e `idPaciente` |
| Búsqueda de Historia Clínica | PASS | Filtra `Modulo` y muestra estado sin coincidencias; se restaura la fila |
| `fixedHeight` y scroll interno | PASS | `fixedHeight: false`; contenedor sin estilo de altura |
| 1440×900 | PASS | Sin overflow; historia 318.9 px; total visible |
| 1280×720 | PASS | Sin overflow; total y acciones visibles |
| 768×1024 | PASS | Sin overflow; acciones compactas 36×40 px |
| 375×667 | PASS | Sin overflow; iconos con nombre accesible; scroll vertical natural |
| Consola JavaScript | PASS | Sin errores en la carga sintética validada |
| Aislamiento entre clínicas | PASS | Doctor sintético B recibe `No autorizado.` para paciente 9301 |

No se ejecutaron ediciones clínicas, resúmenes, persistencia ni descargas para
evitar efectos laterales. La prueba de porcentajes completos, parciales y
pendientes para los nueve módulos queda pendiente hasta disponer de fixtures
aislados con las tablas de respuestas requeridas.

## ACT-05B-03C — Validación de padding — 2026-10-10

### Mediciones computadas

| Datos | Resolución | Encabezado | Cuerpo | Estado vacío | Acción | Overflow documento |
|---|---:|---:|---:|---:|---:|---|
| Registros sintéticos, paciente 9301 | 1440×900 | 17.6/20 px | 20 px | No aplica | 40 px | PASS |
| Registros sintéticos, paciente 9301 | 1280×720 | 17.6/20 px | 20 px | No aplica | 40 px | PASS |
| Registros sintéticos, paciente 9301 | 768×1024 | 17.6/20 px | 20 px | No aplica | 40 px | PASS |
| Registros sintéticos, paciente 9301 | 375×667 | 16 px | 16 px | No aplica | 40 px | PASS |
| Estados vacíos, paciente 9302 | 1440×900 | 17.6/20 px | 20 px | 16 px | 40 px | PASS |
| Estados vacíos, paciente 9302 | 1280×720 | 17.6/20 px | 20 px | 16 px | 40 px | PASS |
| Estados vacíos, paciente 9302 | 768×1024 | 17.6/20 px | 20 px | 16 px | 40 px | PASS |
| Estados vacíos, paciente 9302 | 375×667 | 16 px | 16 px | 16 px | 40 px | PASS |

La notación `17.6/20 px` corresponde a padding vertical/horizontal. El
`scrollWidth` del documento coincidió con su ancho cliente en las ocho cargas.
Los cuatro estados vacíos conservaron su texto y `role="status"`. Las tablas
con registros conservaron sus filas, buscadores y acciones.

### Comparación y regresión

Antes de ACT-05B-03C, ACT-05B-03B dejaba el cuerpo con `0 16px 16px` y el
estado vacío con `13.6px 14.4px`, por lo que visualmente iniciaba junto al
divisor del encabezado. Después, el cuerpo queda separado por `20px` y el
estado usa `16px` internos. La Historia Clínica conservó sus paddings
computados propios (`12px 16px 10.4px` en encabezado móvil y
`0 16px 13.6px` en cuerpo móvil), fuera del selector corregido.

Se ejecutó PHP lint de la vista y del modelo: PASS. `git diff --check` para
los archivos fuente modificados: PASS. No se modificaron tablas, AJAX,
`toggleSize()`, controladores ni persistencia.

La consola observó un error en `public/assets/js/main.js:1`: acceso a
`classList` sobre un elemento nulo durante `window.onload`. No se atribuye a
este cambio CSS y no se corrigió por estar fuera del alcance. En el navegador
aislado, además, `window.jQuery` quedó indefinido porque el script externo de
jQuery no cargó; por ello `loader.js` no llegó a registrar `toggleSize()`.
La función sí existe en el archivo fuente, pero su ejecución no pudo
validarse en esta sesión sin modificar dependencias ni configuración. Queda
pendiente una prueba con las dependencias frontend disponibles antes de
declarar regresión JavaScript totalmente limpia.

No se ejecutaron altas, ediciones, descargas ni cambios clínicos.

## ACT-05B-06B — Dropdown de Historia Clínica — 2026-10-10

### Auditoría de acciones

| Acción original | Opción nueva | Destino verificado |
|---|---|---|
| Enlace `clinica/{url}/paciente/{idPaciente}` | Editar módulo | PASS |
| Enlace `clinica/resumen/{url}/paciente/{idPaciente}` | Ver resumen | PASS |

El fixture sintético disponible contiene un módulo (`Modulo sintetico`) para
el paciente 9302; por ello primer y último registro coinciden en esta sesión.
No se inventaron módulos adicionales ni se modificaron fixtures productivos.

### Matriz de pruebas

| Prueba | Resolución/alcance | Resultado |
|---|---|---|
| Encabezado con una columna Acciones | 1440×900, 1280×720, 768×1024, 375×667 | PASS |
| Apertura por clic | Escritorio y móvil | PASS |
| Opciones y parámetros | Paciente 9302, módulo 1 | PASS |
| Cierre con Escape y devolución de foco | 1280×720 y 375×667 | PASS |
| Cierre por clic externo | Expediente sintético | PASS |
| Navegación de opciones con ArrowDown/ArrowUp | Menú abierto | PASS |
| Búsqueda sin coincidencias y restauración | Simple-DataTables | PASS; el menú clonado se elimina |
| Redibujado/paginación | Evento de búsqueda verificado; fixture de una fila | PASS |
| Posicionamiento del menú | Dentro del viewport, 1440 y 375 px | PASS |
| Overflow horizontal del documento | Cuatro resoluciones | PASS |
| Rutas Editar y Ver resumen | HTTP local sintético | PASS; URLs conservadas |
| PHP lint y `git diff --check` | Vista, modelo y fuentes | PASS |
| Consola durante esta validación | Expediente local | PASS; sin errores observados |

En 375 px el documento mantuvo `overflowX=false`; el botón midió `40×40 px`
y el menú quedó completamente dentro del viewport. La tabla presenta un
ancho intrínseco ligeramente superior al contenedor por el botón táctil, pero
no generó overflow del documento ni recorte visual del menú.

No se ejecutaron operaciones de edición, guardado, descargas ni cambios
clínicos; la navegación de ambos destinos sólo verificó la URL y la carga
local. La cobertura con nueve módulos, paginación real y aislamiento cruzado
requiere un fixture que contenga el catálogo completo; queda pendiente sin
alterar la implementación de esta actividad.

## ACT-05B-04A — Estabilidad JavaScript del expediente — 2026-10-10

### Causas verificadas

- `public/assets/js/loader.js` dependía de `$` en el primer statement. Cuando el
  CDN de jQuery no estaba disponible, el archivo se interrumpía antes de
  registrar `toggleSize()` y antes de ocultar el loader.
- `public/assets/js/main.js` accedía a `classList` de `#sidebar` sin comprobar
  que el elemento existiera; también creaba `PerfectScrollbar` y ejecutaba
  `feather.replace()` sin validar sus elementos o dependencias.
- `app/views/clinica/pacientes-detalle.php` registraba las mismas cuatro cargas
  AJAX en `pageshow` y `DOMContentLoaded`, sin una guarda de inicialización.
  Esto podía duplicar solicitudes y reconstrucciones de Simple-DataTables.

### Cambios verificados

- `loader.js` usa eventos nativos, mantiene una animación con jQuery cuando
  está disponible y utiliza un fallback nativo cuando el CDN falla. Expone
  `window.toggleSize` independientemente de jQuery y conserva la alternancia
  de `.resizable` y el orden por `data-index`.
- `main.js` conserva sidebar, scrollbar, cierre móvil y Feather, pero valida
  cada dependencia y elemento antes de usarlo.
- El expediente usa una sola inicialización idempotente para `pageshow` y
  `DOMContentLoaded`, valida la existencia de `#main`/`data-paciente`, protege
  la inicialización de Simple-DataTables y tolera estado JSON inválido en
  `localStorage`.

### Matriz de pruebas aisladas

| Prueba | Resultado | Evidencia observable |
|---|---|---|
| Carga de expediente autorizado con dependencias locales | PASS | Paciente sintético 9301; cuatro contenedores `ready`, una tabla y un wrapper por tarjeta; consola sin errores |
| Falla/ausencia de jQuery CDN | PASS para fallback | Loader oculto y `toggleSize()` operativo sin jQuery; no se agregaron dependencias nuevas |
| CDN de jQuery disponible | NO VERIFICABLE | El entorno aislado no entregó jQuery externo; el camino con `fadeOut` queda cubierto por inspección estática |
| `toggleSize()` | PASS | Clic: agrega `col-sm-12`; segundo clic: restaura columnas y órdenes |
| Inicialización duplicada | PASS | Una tabla y un `.dataTable-wrapper` por cada una de las cuatro cargas AJAX después de `pageshow`/`DOMContentLoaded` |
| Búsqueda de tabla clínica | PASS | Búsqueda por teclado sin coincidencias muestra estado vacío y al limpiar restaura la fila |
| Dropdown Historia Clínica | PASS | Apertura, dos opciones, Escape, cierre y devolución de foco; consola sin errores |
| Dropdown listado de pacientes | PASS | Apertura, menú clonado visible, Escape y cierre; tabla y ruta sin regresión |
| Aislamiento de expediente | PASS | Paciente sintético 9302 responde `No autorizado` para la sesión del médico A |
| Responsive 375×667 | PASS | `overflowX=false`, cuatro estados `ready`, dropdown presente, scroll vertical natural |
| Responsive 768×1024 | PASS | `overflowX=false`, cuatro estados `ready`, consola sin errores |
| Responsive 1280×720 | PASS | `overflowX=false`, cuatro estados `ready`, consola sin errores |
| Responsive 1440×900 | PASS | `overflowX=false`, cuatro estados `ready`, consola sin errores |
| Sintaxis JavaScript/PHP | PASS | `node --check` para `loader.js` y `main.js`; `php -l` para la vista |

No se ejecutaron guardados clínicos, ediciones, descargas ni cambios de base de
datos. La ruta con jQuery CDN realmente disponible, ni la prueba de red
interrumpida durante una petición AJAX, requieren un entorno frontend con los
recursos externos habilitados y quedan pendientes.

## ACT-05B-05A — Manejo seguro de errores clínicos — 2026-10-10

### Alcance y entorno

- Pruebas ejecutadas en servidores PHP locales aislados sobre bases sintéticas
  desechables derivadas de `tests/fixtures/security-fixtures.sql`.
- Se utilizaron los identificadores sintéticos 9101/9102 para clínicas,
  9201/9202 para médicos y 9301/9302 para pacientes. No se utilizaron datos
  reales ni se modificó la base configurada en `.env`.
- Las simulaciones de tablas faltantes se realizaron únicamente en bases
  sintéticas separadas y se usaron para provocar excepciones PDO controladas.

### Matriz de resultados

| Prueba | Resultado | Evidencia observable |
|---|---|---|
| Expediente autorizado | PASS | `/clinica/paciente/9301` respondió 200 y renderizó las ocho secciones con datos sintéticos |
| Paciente de otra clínica | PASS | Médico de clínica 9101 recibió 403 `No autorizado.` para paciente 9302; sin SQL ni traza |
| Paciente inexistente | PASS | Respondió 404 `Paciente no encontrado.`; sin detalles internos |
| Tabla sin registros | PASS | Notas subsecuentes sin datos clínicos no provocaron excepción y no se confundieron con el fragmento de error de servidor |
| Tabla faltante en endpoint AJAX | PASS | `/buscar/tabla-laboratorio/9301/0` respondió 500 con fragmento genérico; no SQL/PDO/ruta/nombre de BD |
| Tabla clínica faltante en expediente | PASS | El expediente respondió 500 completo, sin HTML parcial ni mensaje interno |
| Fallo de conexión PDO | PASS | Base inexistente respondió 500 con “No se pudo completar la solicitud. Inténtalo más tarde.” |
| Recuperación visual de AJAX | PASS | `conteLaboratorio` quedó en estado `error`; los demás estados permanecieron `ready` |
| Dashboard Doctor | PASS | `/clinica` respondió 200; `#main` presente, sin overflow horizontal ni errores de consola |
| Listado de pacientes | PASS | `/clinica/pacientes` respondió 200; tabla presente, sin overflow horizontal ni errores de consola |
| Expediente con tablas disponibles | PASS | Cuatro contenedores AJAX en estado `ready`, Historia Clínica presente y consola sin errores |
| Sintaxis PHP y diff | PASS | `php -l` en archivos modificados y `git diff --check` sin errores en el alcance |

La verificación se realizó mediante el código HTTP y el texto realmente
entregado/renderizado. No se consideró PASS una respuesta basada sólo en el
código de estado.

### Pendientes explícitos

- No se verificó la disponibilidad de infraestructura de logging de producción
  ni su política de retención.
- No se extendió esta actividad a vistas secundarias con `die()` directo,
  descargas/PDF, consultas SQL concatenadas ni autorización de archivos.
- No se ejecutaron escrituras clínicas, generación de PIN, edición, recetas o
  cargas de documentos; son regresiones fuera del alcance de esta actividad.

## ACT-05C-02 — Fixtures y línea base de seguridad documental — 2026-10-10

### Entorno

- Base sintética desechable: `clinica_dolor_audit_files_20261010_c02`.
- Servidor de aplicación aislado: `127.0.0.1:8131`.
- Servidor estático aislado para simular exposición de document root:
  `127.0.0.1:8132`, apuntando exclusivamente a
  `tests/security/runtime/public`.
- Clínicas: `9101` y `9102`.
- Médicos: `9201` y `9202`.
- Pacientes: `9301` y `9302`.
- Recetas internas: `9401` y `9402`.
- Laboratorios: `9511` y `9512`; registro textual `9502` conservado.
- COFEPRIS: `9503` y `9506`.
- No se utilizaron pacientes, documentos ni bases reales.

### DocumentRoot

La configuración local de Apache confirma:

```text
ServerName clinica.test
DocumentRoot C:/xampp/htdocs/clinica-dolor
```

Evidencia: `C:/xampp/apache/conf/extra/httpd-vhosts.conf:58-64`.

El `.htaccess` raíz redirige a `public/`, mientras que
`public/.htaccess` deja pasar archivos físicos existentes. Por ello las
carpetas bajo `public/storage` deben clasificarse y protegerse por separado.
Un `HEAD` al recurso público existente
`http://clinica.test/public/storage/web/Aviso%20de%20privacidad.pdf` respondió
`200 application/pdf`; no se leyó su contenido.

### Matriz de resultados

| Prueba | Resultado | Respuesta observable |
|---|---|---|
| Descarga de laboratorio propio | PASS | `200 application/pdf` |
| Descarga cruzada entre clínicas | FAIL de seguridad | `200 application/pdf` |
| Descarga sin sesión | PASS de autenticación | `302` a acceso médico |
| Archivo inexistente | PASS | `404` |
| Ruta manipulada/traversal | PASS contra escape | `404` |
| Receta interna propia | PASS | `200 application/pdf` |
| Receta interna cruzada | FAIL de seguridad | `200 application/pdf` |
| COFEPRIS propio | PASS | `200 application/pdf` |
| COFEPRIS cruzado | FAIL de seguridad | `200 application/pdf` |
| Documento clínico inexistente en PDF | FAIL de manejo esperado | `500` |
| URL estática de laboratorio | FAIL de exposición | `200` sin sesión |
| URL estática de COFEPRIS | FAIL de exposición | `200` sin sesión |
| URL estática de recurso web público | PASS | `200` sin sesión |

La prueba no considera PASS una respuesta que únicamente tenga el código HTTP:
los PDF se validaron también por `Content-Type` y longitud de respuesta.

### Fixtures de documentos

Los documentos sintéticos se encuentran en:

- `tests/security/runtime/public/storage/laboratorio/`
- `tests/security/runtime/public/storage/cofepris/`
- `tests/security/runtime/public/storage/web/`

Se generaron exclusivamente para esta línea base y no se copiaron archivos
desde `public/storage` del proyecto.

### Recetas externas

No se creó una tabla sintética para `receta_medica_externos` porque no existe
DDL versionado en el repositorio. El modelo observado no contiene
`id_paciente` ni `id_clinica`; la validación de propietario queda pendiente de
confirmación funcional y de esquema antes de probar aislamiento.

### Consumidores con enlaces actuales

| Consumidor | Contrato observado |
|---|---|
| Laboratorio | `/descargar/{ruta}/{archivo}` |
| Nota subsecuente | Enlace generado hacia descarga de laboratorio |
| Receta interna | POST `/pdf/receta/{id}` |
| Receta externa | POST `/pdf/receta-externo/{id}` |
| COFEPRIS | GET `/pdf/cofepris/{id}` |
| Web pública | `descargar/web/...` y `RUTA_STORAGE` hacia `web/` |

### Precondiciones de la siguiente actividad

- Crear autorización por recurso antes de resolver cualquier ruta física.
- Mantener disponible `storage/web` sin mezclarlo con documentos clínicos.
- Definir el alcance de recetas externas.
- Diseñar compatibilidad temporal para enlaces por nombre de archivo.
- Corregir el `500` de documentos inexistentes a una respuesta controlada.
- Probar Apache con las carpetas clínicas bloqueadas antes de mover archivos.

Esta actividad no modificó controladores, rutas productivas, esquemas reales,
almacenamiento clínico, autenticación ni persistencia.

## Sprint visual prioritario — dashboards — 2026-10-10

### Entorno controlado

- Servidor PHP aislado: `http://127.0.0.1:8133` con
  `tests/security/isolated-router.php`.
- Base sintética desechable: `clinica_dolor_sprint_20261010_b`.
- Identificadores: clínicas `9101/9102`, médicos `9201/9202`, pacientes
  `9301/9302`.
- La sesión del médico y la del paciente se crearon con el endpoint de
  pruebas; no se utilizaron credenciales, expedientes ni archivos reales.
- El catálogo sintético usado para la captura contiene un módulo mínimo. La
  ausencia de ocho filas adicionales es una limitación del fixture, no una
  afirmación sobre el catálogo productivo.

### Matriz responsive

| Dashboard | Viewport | `scrollWidth` observado | `scrollHeight` observado | Overflow horizontal | Resultado |
|---|---:|---:|---:|---|---|
| Médico | 1440×900 | 1440 | 900 | No | PASS |
| Médico | 1280×720 | 1265 | 775 | No | PASS |
| Médico | 768×1024 | 768 | 1024 | No | PASS |
| Médico | 375×667 | 360 | 1165 | No | PASS |
| Paciente | 1440×900 | 1440 | 900 | No | PASS |
| Paciente | 1280×720 | 1265 | 860 | No | PASS |
| Paciente | 768×1024 | 768 | 1024 | No | PASS |
| Paciente | 375×667 | 360 | 1101 | No | PASS |

El `scrollWidth` menor que `innerWidth` en páginas con scrollbar vertical es
el ancho útil del viewport, no un overflow horizontal. El scroll vertical de
1280×720 y 375×667 es natural.

### Funcionalidad y consola

| Prueba | Resultado | Evidencia |
|---|---|---|
| Dashboard médico autenticado | PASS | `/clinica` respondió y mostró `1` paciente de la clínica sintética `9101` |
| Acciones médicas visibles | PASS | `/clinica/pacientes`, `/clinica/paciente/nuevo` y `/clinica/recetas/nueva`; el resumen enlaza al directorio |
| Dashboard paciente autenticado | PASS | `/historia-clinica` renderizó el catálogo y el avance del paciente `9301` |
| Acción de continuar módulo | PASS | Ruta corregida y validada: `/historia-clinica/ficha-identificiacion/paciente/9301`; título observado: `Ficha de identificación del paciente` |
| Footer | PASS | Un solo `.clinic-footer` por dashboard y texto `© 2026 · tratamientosdeldolor.org` |
| Consola JavaScript | PASS | Sin errores `error` en los tabs sintéticos usados para ambos dashboards |
| PHP lint | PASS | `app/views/clinica/index.php`, `app/views/paciente/index.php` y `app/views/components/footer-doctor.php` |
| Regresión de seguridad documental | NO EJECUTADA | Se mantuvo fuera del alcance; los FAIL de ACT-05C-02 siguen abiertos |

Las capturas reales fueron obtenidas durante la sesión controlada en
`1440×900`, `1280×720`, `768×1024` y `375×667` para ambos dashboards. El
runner disponible fue Chrome/CUA con viewport controlado; no se afirmó una
ejecución Playwright local porque Playwright no está instalado como dependencia
del proyecto. La evidencia visual se mantuvo en los tabs sintéticos para
revisión y se mostraron capturas de escritorio, tableta y móvil durante la
validación.

### Alcance preservado

No se modificaron rutas de descarga, controladores de documentos, tablas,
migraciones, autenticación, formularios internos, Portal Paciente clínico ni
web pública. Las vulnerabilidades críticas documentales de ACT-05C-02
continúan abiertas y bloquean una publicación con información clínica real.

## Sprint UI-02 — validación de vistas pendientes — 2026-10-10

### Entorno y runner

- Servidor local aislado: `http://127.0.0.1:8136` con
  `tests/security/isolated-router.php`.
- Base sintética desechable: `clinica_dolor_demo_20261010_ui02`.
- Se usaron únicamente los fixtures sintéticos existentes, sin pacientes ni
  documentos reales.
- Runner: navegador CUA con viewport controlado. Las dimensiones observadas
  se leyeron de `window.innerWidth`, `window.innerHeight`,
  `document.documentElement.clientWidth` y `scrollWidth`.

### Responsive

| Superficie | 1440×900 | 1280×720 | 768×1024 | 375×667 |
|---|---|---|---|---|
| Dashboard Doctor y vistas médicas válidas | PASS, sin overflow | PASS, sin overflow | PASS, sin overflow | PASS, sin overflow después del ajuste PIN |
| Expediente e Historia Clínica | PASS, 9 filas | PASS, 9 filas | PASS, 9 filas | PASS, toolbar apilado |
| Dashboard Paciente | PASS, 9 tarjetas | PASS, 9 tarjetas | PASS, 9 tarjetas | PASS, una columna |
| Nueve formularios paciente | PASS | PASS | PASS | PASS |

En páginas con barra vertical, `clientWidth` y `scrollWidth` fueron menores
que `innerWidth` por el ancho del scrollbar, pero iguales entre sí; no se
observó overflow horizontal. La comprobación de formularios cubrió 36 casos
(9 rutas × 4 viewports), con footer único y clase `patient-clinical-form`.

### Funciones y estados

| Prueba | Resultado | Evidencia |
|---|---|---|
| Catálogo paciente | PASS | 9 tarjetas, 9 nombres reales y orden del catálogo |
| Rutas directas de los 9 módulos paciente | PASS en modo lectura | Las 9 rutas renderizaron su encabezado real |
| Historia Clínica del expediente | PASS en modo lectura | 9 filas, buscador/selector y dropdown `Acciones` |
| Shell/footer compartido | PASS en vistas válidas | Un footer por vista cargada |
| Overflow PIN a 375 px | CORREGIDO/PASS | El origen era ancho intrínseco de tabla, autocomplete de 260 px y botón en columna estrecha |
| JS de formularios paciente | PASS | Consola sin errores `error` durante el recorrido sintético |
| `/clinica/recetas` y `/clinica/cofepris` | NO VERIFICADO | La base sintética respondió estado genérico de solicitud no disponible; no se modificó backend para forzar un resultado |
| Guardado, edición, generación de PIN, PDF y descarga | NO VERIFICADO | Fuera del alcance visual; descargas además están bloqueadas por ACT-05C-02 |

### Calidad estática

- `php -l` sobre `app/` y `tests/`: PASS.
- `node --check` sobre `loader.js`, `main.js`, `login.js` y
  `search-main.js`: PASS.
- `git diff --check`: sólo reporta whitespace preexistente en
  `app/Controllers/PdfController.php` y advertencias de conversión de fin de
  línea; no se corrigieron archivos fuera de alcance.

Las capturas reales del dashboard médico, dashboard paciente, expediente,
Historia Clínica, Laboratorio, PIN y un formulario paciente se obtuvieron en
la sesión aislada y se presentaron durante esta ejecución. No se usó
información clínica real.

## Sprint UI-03 — cierre visual y demostración — 2026-10-10

### Rutas antes no verificadas

Se confirmó que `/clinica/recetas` y `/clinica/cofepris` existen y llegan a
sus controladores y vistas reales. La causa de la indisponibilidad anterior
fue la ausencia en el fixture de `receta_medica_externos` y `cofepris_folio`.
Después de agregar únicamente su DDL mínimo, ambas rutas respondieron con
HTTP de aplicación normal, shell médico, footer único, tablas y estados vacíos
reales. No se tocó el backend clínico.

### Regresión ejecutada

| Grupo | Casos | Resultado |
|---|---:|---|
| Accesos Doctor/Paciente | 8 (2 rutas × 4 viewports) | PASS |
| Vistas médicas compartidas | 48 (12 rutas × 4 viewports) | PASS |
| Recetas y COFEPRIS tras ampliar fixture | 8 (2 rutas × 4 viewports) | PASS modo lectura |
| Dashboard Paciente | 4 | PASS; 9 módulos |
| Formularios Paciente | 36 (9 × 4 viewports) | PASS; sin overflow |
| Dropdown Historia Clínica | Apertura, `aria-expanded`, Escape | PASS |

Todos los casos usaron `window.innerWidth`, `window.innerHeight`,
`clientWidth` y `scrollWidth`. No se declaró PASS con un viewport no aplicado.
La consola no reportó errores `error` durante el recorrido sintético.

### Demostración

PASS en modo lectura para: login médico, Dashboard Doctor, listado,
expediente, Historia Clínica, Laboratorio, cierre de sesión, login paciente,
Dashboard Paciente y continuación a
`/historia-clinica/antecedentes-personales-no-patologicos/paciente/9301`.

No se ejecutaron guardados, edición, finalización, generación de PIN, PDF,
descargas ni cargas de archivos.

### P0/P1/P2

- P0: ninguno.
- P1 corregido: IDs de Recetas ilegibles por wrapping carácter a carácter en
  375 px.
- P2 pendiente: copy heredado de estados vacíos y wrapping natural de nombres
  largos en tablas móviles; no bloquean la demostración.
## Cierre de entrega — demostración sintética — 2026-10-10

Se repitió el recorrido Doctor → Expediente → Historia Clínica → Laboratorio
→ cierre de sesión → Paciente → Dashboard → módulo clínico en la base
`clinica_dolor_demo_20261010_ui03c`, iniciada mediante
`tests/security/isolated-router.php`. La consulta de control confirmó 2
clínicas, 2 médicos, 2 pacientes y 9 módulos; no se usó `bd_clinica`.

| Control | Resultado |
|---|---|
| Login/cierre de sesión Doctor | PASS |
| Dashboard, listado y expediente `9301` | PASS |
| Historia Clínica, 9 filas, dropdown y Escape | PASS |
| Laboratorio en modo lectura | PASS |
| Login y Dashboard Paciente | PASS |
| Catálogo y continuación de 9 módulos | PASS |
| Consola JavaScript (`error`) | PASS; sin errores |
| Responsive 1440, 1280, 768 y 375 px | PASS; sin overflow horizontal estable |

No se ejecutaron guardados, ediciones, finalización, alta de pacientes, PIN,
PDF, descargas, cargas de archivos ni operaciones irreversibles. Las
vulnerabilidades documentales críticas de ACT-05C-02 continúan abiertas.
La evidencia narrativa completa está en `docs/demo-readiness.md`.

## UI-FIX-D04-02 — Selectores inválidos — 2026-10-10

### Diagnóstico

La reproducción sintética en `/clinica/paciente/nuevo` confirmó que `Sexo` y
`EstadoCivil` recibían `form-select is-invalid`, `aria-invalid="true"` y sus
mensajes asociados. Bootstrap 5 aplicaba dos imágenes de fondo —flecha y
alerta—, pero el estilo calculado era `background-repeat: repeat, repeat`, por
lo que ambos SVG se repetían dentro del control.

### Corrección

Se añadió únicamente en `public/assets/css/app.css` la regla scoped:

```css
body.doctor-patient-form-page .doctor-form-field .form-select.is-invalid {
  background-repeat: no-repeat;
}
```

No se modificaron la vista PHP, los 32 controles, IDs, validación frontend o
backend, rutas, modelos, permisos ni persistencia.

### Matriz de validación

| Prueba | Resultado | Evidencia |
|---|---|---|
| Formulario inicial sin errores | PASS | 32 controles, 0 inválidos |
| Envío vacío | PASS | 14 errores visibles; foco en `Nombres` |
| `Sexo` y `EstadoCivil` inválidos | PASS | borde rojo, mensajes, flecha y alerta únicas |
| Estilos antes/después | PASS | `repeat, repeat` → `no-repeat, no-repeat` |
| Seleccionar `Sexo` válido | PASS | error y `is-invalid` se limpian al reenviar |
| Seleccionar estado civil válido | PASS | error y `is-invalid` se limpian al reenviar |
| HTTP 422 del servidor | PASS | payload sintético con `Sexo` vacío; `campos=Sexo` |
| Foco y teclado | PASS | foco violeta visible sobre `Sexo` |
| Crear y Editar | PASS | rutas reales contrastadas; misma regla scoped |
| Responsive 1440×900, 1280×720, 768×1024, 375×667 | PASS | sin overflow horizontal; 32 controles |
| Consola JavaScript | PASS | sin errores durante la reproducción |
| PHP lint | PASS | vista y controlador sin errores de sintaxis |

Se generaron capturas antes y después en escritorio, además de la captura
móvil con ambos selectores inválidos. D04 permanece en **VALIDACIÓN**; D05
continúa documentado como expediente individual y no se considera validado por
esta actividad.
