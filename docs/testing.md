# Pruebas y criterios de aceptación

## P07-MODAL-AUDIO-FIX-01 — Modal y narración de instrucciones — 2026-10-11

La corrección se limitó a la vista P07 y reutilizó el patrón de modal, foco,
controles y estados de voz ya usado por P04/P05. Se eliminaron los dos
controles `.btnLeer` heredados de P07 para evitar que `voice-utilities.js`
compitiera con la narración específica del módulo; ese archivo compartido no
fue modificado.

| Control | Resultado | Evidencia |
|---|---|---|
| Contrato modal y botón de ayuda | PASS | `node tests/p07/audio-mock.mjs` |
| Narración `es-MX`, `onstart`, `onend`, `onerror` y cancelación | PASS estático/mock | `tests/p07/audio-mock.mjs` |
| Lectura V1/V2 sin controles ni dependencias ocultas | PASS estático | `p07SpeechTextForActive()` en la vista |
| Foco, Escape, click fuera y restauración de foco | PASS estático | listeners de P07 en la vista |
| Reproducción audible humana | NO VERIFICADO | requiere navegador e interacción humana |
| Modal, consola y responsive renderizados | NO VERIFICADO | no se obtuvo una sesión sintética de navegador en esta ejecución |

No se realizaron escrituras, finalización ni navegación clínica con el
paciente 310; se conserva el entorno sintético previo y P07 permanece en
`VALIDACIÓN`.

## P08-COMPLETE-01 — Medicación Actual — 2026-10-11

Se utilizó exclusivamente la base sintética allowlisted
`clinica_dolor_demo_20261010_final`, con pacientes 9301/9302 de clínicas
9101/9102. `prepare-synthetic.php` confirmó la base activa y creó sólo filas
marcadas `P08-SYNTH-20261011`; `bd_clinica` no se escribió. El servidor se
inició con `tests/p08/start-server.ps1` y una `APP_KEY` efímera.

| Control | Resultado | Evidencia |
|---|---|---|
| Aislamiento de base | PASS | preflight exacto de `SELECT DATABASE()` y clínicas 9101/9102 |
| PHP lint y contrato | PASS | `php -l` y `node tests/p08/contract-check.mjs` |
| GET propio | PASS | HTTP 200 y HTML P08 sintético |
| GET sin sesión | PASS | HTTP 302 sin información clínica |
| GET cruzado | PASS | HTTP 403 |
| AJAX M6 con rol derivado | PASS | render propio sin confiar en `idRol` del cliente |
| CRUD propio | PASS | alta, edición y baja con persistencia real |
| No-op | PASS | segunda edición devuelve éxito sin UPDATE adicional |
| Edición/eliminación cruzadas | PASS | HTTP 403 y sin escritura cruzada |
| Campo fuera de allowlist | PASS | HTTP 422 |
| Finalización módulo 6 | PASS | `next_url` local y segunda finalización idempotente |
| Limpieza sintética | PASS | marcador y finalización temporal eliminados |
| Audio mock/gestión de cancelación | PASS estático | contrato de `speechSynthesis`, `es-MX` y cancelación |
| Capturas y responsive 1440/1280/768/375 | NO VERIFICADO | navegador IAB bloqueó localhost con `net::ERR_BLOCKED_BY_CLIENT`; Chrome no disponible |
| Consola y reproducción audible humana | NO VERIFICADO | no se declara sin navegador utilizable |

La ejecución reproducible es:

```powershell
php tests/p08/prepare-synthetic.php --database=clinica_dolor_demo_20261010_final --json
node tests/p08/contract-check.mjs
powershell -ExecutionPolicy Bypass -File tests/p08/start-server.ps1 -Port 8108
php tests/p08/http-crud.php --database=clinica_dolor_demo_20261010_final --base-url=http://127.0.0.1:8108 --app-key=<APP_KEY_EFIMERA_DEL_SERVIDOR>
php tests/p08/cleanup-synthetic.php --database=clinica_dolor_demo_20261010_final
```

P08 permanece en `VALIDACIÓN`; no se modificaron otros módulos ni se hicieron
commits, despliegues o escrituras sobre la base real.

## P07-COMPLETE-01 + adendum P05-FLOW-SPEED-01 — 2026-10-11

Entorno utilizado: base sintética allowlisted
`clinica_dolor_demo_20261010_final`, con pacientes 9301/9302 en clínicas
distintas. El servidor local se ejecutó con una base explícitamente comprobada;
no se utilizó `bd_clinica`. El catálogo P07 sintético contiene dos temas V1,
una pregunta de selección, una pregunta de texto y dos enfermedades V2.

| Control | Resultado | Evidencia |
|---|---|---|
| PHP lint de P07 | PASS | modelos, controladores, vista y runners `php -l` |
| Sintaxis JS inline | PASS | `vm.Script` sobre el bloque P07 |
| Contrato V1/V2, IDs, eventos y autorización | PASS | `node tests/p07/contract-check.mjs` |
| GET propio de P07 | PASS | HTTP 200 y contenido sintético |
| GET sin sesión | PASS | HTTP 302 sin contenido clínico |
| GET cruzado | PASS | HTTP 403 sin HTML del paciente ajeno |
| Persistencia V1 propia | PASS | POST real y lectura posterior de la respuesta |
| Persistencia V2 propia | PASS | POST real de respuesta, tipo y año |
| POST cruzado V1/V2 | PASS | HTTP 403; no se permitió escritura cruzada |
| No y Sí sin dependencias | PASS | clic real y avance automático en navegador |
| Sí con dependencias | PASS | permanece hasta tipo/año; después avanza |
| Input con un Siguiente | PASS | el texto se guardó y cargó V2 con un clic |
| Respuesta previamente guardada | PASS estático/flujo | `p07Dirty()` evita otro UPDATE; navegación recuperada |
| V1/V2 y narración de transición | PASS estático | eventos delegados y `p07StopVoice()` |
| Responsive/overflow P07 | PASS | navegador sintético a 1440×900 y 375×667 |
| Paridad shell/footer P04 | PASS | P04 renderizado en 1280×720 para comparación |
| Reproducción audible humana | NO VERIFICADO | no se declara a partir de mocks/DOM |
| Finalización y destino del siguiente módulo | NO VERIFICADO | no se ejecutó una finalización clínica sintética |
| P07 a 1280×720 y 768×1024 | NO VERIFICADO | no se ejecutaron esos viewports en P07 |

Las escrituras de prueba se eliminaron con `tests/p07/cleanup-synthetic.php`;
el catálogo sintético se conserva para repetir el procedimiento. P07 permanece
en `VALIDACIÓN`. No se modificaron P04, P05 ni P06.

## P06-COMPLETE-01 — Antecedentes Personales Quirúrgicos — 2026-10-11

Entorno utilizado: base sintética allowlisted
`clinica_dolor_demo_20261010_final`, con pacientes de prueba en clínicas
distintas. El preflight confirmó que las operaciones no apuntaron a
`bd_clinica`; las escrituras de los escenarios se eliminaron o ejecutaron
dentro de transacciones con rollback.

| Control | Resultado | Evidencia |
|---|---|---|
| PHP lint de modelo, controladores, vista y runner HTTP | PASS | `php -l` |
| Contratos P06, IDs, campos, rutas y funciones existentes | PASS | `node tests/p06/contract-check.mjs` |
| Sintaxis JavaScript inline y CSS scoped | PASS | comprobaciones automatizadas |
| Modal, foco, Escape, cierre y retorno de foco | PASS | navegador sintético |
| Narración y cancelación sin solapamiento | PASS (mock) | `node tests/p06/audio-mock.mjs` |
| Reproducción audible real | NO VERIFICADO | no se declara a partir del mock |
| Estado vacío y formulario de nueva cirugía | PASS | navegador sintético, 1440×900 y 375×667 |
| Alta, edición y eliminación propia | PASS | `verify-isolation.php` y `http-crud.php` |
| Edición y eliminación cruzada entre pacientes/clínicas | PASS | HTTP 403; sin escritura cruzada |
| Acceso sin sesión | PASS | HTTP 302 |
| Paciente propietario | PASS | HTTP 200 y contenido autorizado |
| CRUD persistente después de la limpieza | PASS | `persistent_write=false` |
| Finalización y contrato `next_url` | PASS estático/contractual | validador de flujo y URL relativa |
| Regresión focal P04/P05 | PASS | runners existentes |
| Viewports adicionales 1280×720 y 768×1024 | NO VERIFICADO | no se ejecutaron en esta sesión |

La captura visual mostró el hero, estado vacío, modal, controles de voz,
formulario y footer sin overflow visible en 1440×900 y 375×667. La prueba
audible permanece pendiente de un navegador/interacción que permita confirmar
reproducción real. P06 sigue en `VALIDACIÓN`; P07 no fue iniciado.

## P05-RESTORE-REAL-01 — catálogo original y validación real de P05 — 2026-10-11

Se utilizaron los archivos originales suministrados en `C:\Users\silvi\Downloads`
(`pac_temas_modulo_3.sql` y `pac_preguntas_modulo_3.sql`) únicamente como fuente
de lectura para `tests/fixtures/p05-catalog.php`; no se ejecutaron los dumps
completos ni se tocó `bd_clinica`. La base activa fue exactamente
`clinica_dolor_demo_20261010_final`, con pacientes sintéticos 9301/9302 en
clínicas 9101/9102.

La preparación idempotente verificó 7 temas y 25 preguntas (6 `select`, 19
`input`) conservando IDs, textos, tipos y orden. El primer GET real de P05
creó las 25 filas de respuesta vacías previstas por el comportamiento heredado;
se registró y después se eliminó con `tests/p05/cleanup-synthetic.php`, junto
con comentario y finalización del módulo 3, manteniendo el catálogo.

| Control | Resultado |
|---|---|
| Aislamiento y catálogo 7/25 | PASS |
| Sesión real por PIN sintético para 9301 | PASS |
| AJAX real `contenidoPreguntasM3()` | PASS |
| Sí, dependencia, regreso y recuperación | PASS |
| No y cambio entre los siete temas | PASS |
| Grupo Sanguineo con dos inputs | PASS |
| Comentarios, finalización y `next_url` a P06 | PASS |
| GET/POST cruzado entre clínicas | PASS; GET y POST rechazados |
| Audio | PASS de eventos/estados; reproducción audible humana NO VERIFICADA |
| 1440×900 y 375×667 reales | PASS; sin overflow |
| 1280×720 y 768×1024 | NO VERIFICADO en esta ejecución |
| Consola JavaScript | PASS; sin errores/advertencias durante el recorrido |
| Regresión focal P04/P06 | PASS estático y focal; sin escrituras reales |

Se corrigieron dos causas visuales demostradas al cargar el catálogo real: un
override de paridad que convertía todos los `.pregunta-container` en visibles,
y `flex: 1 0 100%` en la ayuda de navegación que producía overflow horizontal.
Ambos cambios quedaron limitados a P05 en `app.css`. P05 permanece en
`VALIDACIÓN`; no se inició P07.

## P05-FIX-UI-NAV-01 — composición y botón Siguiente — 2026-10-11

La salida dinámica de P05 mantiene una única columna: encabezado, controles de
voz, hábito, pregunta, respuestas y navegación quedan dentro del ancho
principal del cuestionario. Los controles de voz se ubican en la parte superior
derecha del cuerpo, siguiendo la composición de P04.

| Comprobación | Resultado | Evidencia |
|---|---|---|
| PHP lint de modelo y vista | PASS | `php -l` |
| HTML contractual de `select` sin respuesta | PASS | `node tests/p05/navigation-contract.mjs` |
| «Siguiente» siempre renderizado cuando falta respuesta | PASS estático | Rama `$respuestaLista=false`, botón disabled y mensaje accesible |
| Guard de avance antes de cambiar pregunta | PASS estático | `p05QuestionIsReadyToAdvance()` + mensaje de bloqueo |
| Cambio de tema y paso a comentarios | PASS contractual | `irASiguienteSeccion()` y `finalizarPreguntas()` conservados |
| Contratos P05 y audio | PASS | `contract-check.mjs`, `audio-mock.mjs` |
| Regresión focal P04 | PASS | aislamiento, finalización, audio mock y next-module |
| Render autenticado y comparación visual 1440/375 | NO VERIFICADO | Falta catálogo P05 aprobado y sesión reproducible |
| Persistencia real, dependencias y siete temas | NO VERIFICADO | No se ejecutaron escrituras clínicas |

El test sintético verifica el contrato del HTML y no inventa temas o preguntas.

## UI-PARITY-GATE-01 — paridad visual real y audio P05 — 2026-10-11

La causa visual confirmada fue estructural: P05 renderizaba el toolbar de voz
dentro del `card-header`, mientras P04 lo renderiza en el cuerpo antes de la
pregunta; además, los wrappers Bootstrap heredados podían conservar columnas
internas y hacer que una pregunta dinámica se presentara a la derecha. El
catálogo sintético P05 permanece vacío, por lo que la pregunta visible se
validó con `tests/p05/visual-fixture.html`, un fixture DOM sin persistencia
basado en la estructura real.

La corrección quedó limitada a la vista y CSS scoped P05: controles de voz al
cuerpo de la tarjeta, flujo vertical forzado para wrappers de fila/columna,
Sí/No horizontales en escritorio y apilados en móvil, navegación a todo el
ancho, modal con `max-width: 540px` igual al máximo computado de P04 y estado
activo del sidebar sincronizado con `aria-current="page"`. No se modificó P04,
P06, el modelo, los controladores, rutas, AJAX, persistencia ni el catálogo.

La causa de audio era un fallback de 350 ms condicionado a que el navegador
reportara `speaking=false` y `pending=false`; una cola bloqueada podía dejar
«Intentando iniciar la lectura…» indefinidamente. P05 ahora confirma
«Reproduciendo…» sólo desde `onstart` y aplica un límite de 1500 ms que cancela
la utterance y muestra una acción manual; `onerror`, cierre, navegación y
`pagehide` conservan cancelación y fallback.

| Comprobación | Resultado | Evidencia |
|---|---|---|
| PHP lint, contratos P05, navegación y `git diff --check` | PASS | `php -l`, runners P05 |
| Gate estático reutilizable P04/P05/P06 | PASS | `node tests/patient-module-parity-gate.mjs` |
| P04 real sintético a 1440×900 | PASS | navegador; hero/tarjeta 968 px, modal 540 px |
| P05 real autenticado a 1440×900 | PASS parcial | navegador; shell, hero, tarjeta, sidebar y modal; 0 preguntas por catálogo vacío |
| Fixture P05 a 1440×900 | PASS | captura y medición; hero/tarjeta 968 px, pregunta bajo instrucciones, Sí/No horizontal, sin overflow |
| Fixture P05 a 375×667 | PASS | captura y medición; Sí/No apilados, modal 344.57 px, sin overflow |
| Consola JavaScript P05/P06 | PASS | `tab.dev.logs()` vacío durante carga sintética |
| Narración mock/onstart/onerror | PASS contractual | `node tests/p05/audio-mock.mjs` y gate estático |
| Reproducción audible humana | NO VERIFICADO | el mock no demuestra audio audible |
| Preguntas, dependencias, guardado y navegación real de siete temas P05 | NO VERIFICADO | catálogo sintético sin temas/preguntas; no se inventaron datos |
| Gate visual P06 | PASS | captura sintética, hero 968 px, sin overflow, estado vacío/modal conservados |
| P06 modificado durante esta actividad | NO | sólo verificación; P07 no iniciado |

La vista P05 y el gate permanecen en `VALIDACIÓN`. El efecto secundario
preexistente de siembra durante GET y los riesgos documentales de
`ACT-05C-02` continúan abiertos.


## P05-COMPLETE-01 — experiencia guiada y audio — 2026-10-11

La vista P05 reutiliza el patrón funcional de audio de P04 sin copiar su
lógica clínica: el modal se abre al cargar, solicita narración automática sólo
cuando `speechSynthesis` está disponible, y ofrece controles «Escuchar» y
«Detener». La pregunta activa puede repetirse manualmente; al cambiar de
pregunta o abandonar la vista se cancela cualquier utterance anterior. La
selección Sí/No, los campos dependientes, AJAX, comentario, finalización y
`next_url` permanecen en la implementación propia de P05.

| Comprobación | Resultado | Evidencia |
|---|---|---|
| Contrato de controles y funciones de voz | PASS | `node tests/p05/contract-check.mjs` |
| Mock de narración, cancelación y estados `onstart/onend/onerror` | PASS | `node tests/p05/audio-mock.mjs` (mock, no reproducción audible) |
| Sintaxis JavaScript inline | PASS | Extracción de script P05 + `new Function(...)` |
| Catálogo real de siete temas | NO VERIFICADO | La base sintética allowlisted permanece sin fuente aprobada |
| Persistencia, dependencias y navegación real de siete temas | NO VERIFICADO | Requiere catálogo P05 y sesión sintética reproducible |
| Capturas autenticadas y responsive 1440/375 | NO VERIFICADO | No hay navegador/sesión/catálogo reproducible disponibles |
| Regresión focal P04 | PASS | `tests/p04/verify-isolation.php`, `finalization-flow.mjs`, `audio-mock.mjs`, `next-module.php` |

La prueba audible real no se infiere del contrato mock. La ausencia de
catálogo no se compensa con preguntas inventadas ni se consulta `bd_clinica`.

## P04-NEXT-01 — continuación al siguiente módulo — 2026-10-11

Se centralizó la resolución en `PacienteModulosModelo::siguienteModuloPendiente()`.
El método consulta `historia_clinica_modulos` en orden clínico y las
finalizaciones formales del paciente después de insertar la finalización. Sólo
acepta la pareja ID/slug canónica de los nueve módulos; cualquier slug
desconocido devuelve `null`, que el controlador convierte en el fallback seguro
`/historia-clinica`. El paciente se autoriza antes de consultar y construir la
URL.

`HttpMethod::jsonResponse()` recibió el parámetro opcional `$extra`; por ello la
respuesta de finalización conserva `resultado`, `mensaje` y `token`, y añade
`next_url` sin romper consumidores existentes. P04 consume ese campo sólo
después de confirmar la finalización. P03 y P05–P11 no fueron modificados.

| Escenario | Resultado | Evidencia |
|---|---|---|
| A. P04 finaliza, P05 pendiente | PASS | UI real; URL final `/historia-clinica/antecedentes-personales-no-patologicos/paciente/9301` |
| B. P05 ya finalizado | PASS | UI real; saltó a `/historia-clinica/antecedentes-personales-quirurgicos/paciente/9301` |
| C. Todos los posteriores finalizados | PASS | UI real; fallback `/historia-clinica` |
| D. Falla comentario | PASS heredado de P04-FIX-FINALIZAR-01 | No finaliza ni navega; cubierto por `finalization-flow.mjs` |
| E. Falla finalización | PASS heredado de P04-FIX-FINALIZAR-01 | Reintento sin duplicar comentario |
| F. Doble clic | PASS heredado de P04-FIX-FINALIZAR-01 | Una sola secuencia |
| G. Acceso cruzado | PASS | Sesión de 9301 recibió «No autorizado» al solicitar 9302 |
| H. Catálogo/slug inválido | PASS | `tests/p04/next-module.php` devolvió fallback seguro dentro de una transacción revertida |
| Módulo siguiente marcado por navegación | PASS | En A, módulo 3 permaneció sin finalización; la navegación GET no lo completó |

Pruebas técnicas: PHP lint de modelo, controlador, helper, vista y fixture
PASS; sintaxis JavaScript PASS mediante el arnés P04; `git diff --check` PASS
sin errores de whitespace. La prueba reproducible del resolvedor es:

```powershell
$env:P04_TEST_DATABASE = 'clinica_dolor_demo_20261010_final'
php tests/p04/next-module.php
```

Las escrituras se ejecutaron sólo sobre la base allowlisted y se restauraron al
baseline: módulo 2 finalizado para 9301, módulos 3–9 pendientes, cero
comentarios de prueba y seis filas sintéticas de antecedentes familiares.

## P04-FIX-FINALIZAR-01 — guardar comentario y finalizar en secuencia — 2026-10-11

La causa raíz confirmada estaba en el callback de éxito de
`gestionarEnfermedadPaciente()`: intentaba iniciar `FinalizarModuloPaciente()`
antes de liberar `p04PendingRequest` en `finally`. La segunda operación legítima
era rechazada con «Espera a que termine el guardado actual».

Se corrigió únicamente el flujo de `app/views/historia-clinica-paciente/antecedentes-familiares.php`:
`agregarComentario()` ahora orquesta comentario y finalización con `async/await`,
mantiene una sola protección de flujo, libera siempre el loader/botón y conserva
el estado de comentario guardado para reintentar sólo la finalización. El
guardado automático de preguntas, audio, modal, CSS, rutas, `idModulo = 2` y
autorización no se modificaron.

La validación real utilizó exclusivamente
`clinica_dolor_demo_20261010_final`; el preflight confirmó la allowlist y que
`bd_clinica` no era el destino. Al terminar se eliminaron los comentarios
insertados por la prueba y se conservó la línea base sintética de finalización.

| Caso | Resultado | Evidencia |
|---|---|---|
| A. Comentario válido → finalización | PASS | UI real; comentario y finalización de 9301, una fila de cada tipo; dashboard en 100% |
| B. Comentario vacío permitido | PASS | UI real; finalización correcta, comentario vacío aceptado por el contrato actual |
| C. Error al guardar comentario | PASS | Arnés controlado con HTTP 500; no se solicitó finalización, mensaje genérico y botón recuperado |
| D. Comentario guardado + error de finalización | PASS | Arnés controlado: reintento emitió sólo la segunda finalización; un solo POST de comentario |
| E. Doble clic | PASS | UI real; exactamente una secuencia, una inserción de comentario y una finalización |
| F. Fallo de red | PASS | Arnés controlado; no redirige, cierra loader y rehabilita el botón |
| G. Finalización exitosa | PASS | Dashboard sintético mostró Antecedentes familiares al 100% |
| Paciente distinto sin cambios | PASS | 9302 conservó 0 comentarios y 0 finalizaciones de P04 |
| Audio, modal y preguntas | NO VERIFICADO en esta actividad | Se conservaron; no se re-auditó su funcionamiento para limitar el alcance |

El arnés reproducible de fallos está en
`tests/p04/finalization-flow.mjs` y se ejecuta con
`node tests/p04/finalization-flow.mjs`. Es una prueba controlada del
orquestador extraído de la vista; los casos A, B, E y G se comprobaron además
con la interfaz y la base sintética real.

## P04-TEST-ENV-01 — entorno sintético reutilizable — 2026-10-11

Se habilitó una línea base reproducible para P04 y futuros módulos P05–P11 en
`tests/p04/`. La guarda `verify-isolation.php` es de solo lectura, exige la
allowlist exacta `clinica_dolor_demo_20261010_final`, comprueba la base activa
con `SELECT DATABASE()` y rechaza `bd_clinica`, nombres locales no autorizados,
ausencia de variable o discrepancias entre CLI y entorno. No se modificó `.env`.

El servidor de `start-server.ps1` sólo se inicia después del preflight, usa
`127.0.0.1`, `variables_order=EGPCS` para compatibilidad con el código que lee
`$_ENV`, y sobreescribe `DB_DATABASE` únicamente en el proceso de pruebas.
`stop-server.ps1` finaliza el PID reportado. El router de pruebas sólo sirve
assets locales y entrega las rutas al front controller real; no crea bypass de
autenticación.

Preflight:

```powershell
$env:P04_TEST_DATABASE = 'clinica_dolor_demo_20261010_final'
php tests/p04/verify-isolation.php --database=clinica_dolor_demo_20261010_final --json
```

Resultado de la preparación: PASS; se verificaron dos pacientes sintéticos en
clínicas distintas, dos accesos, nueve módulos y tablas mínimas P04–P11. La
base no incorpora auxiliares de recetas externas ni COFEPRIS porque no son
dependencias de P04. La sesión real se probó mediante el login por PIN del
fixture, sin ruta de bypass ni secretos en documentación.

El mock de voz se ejecuta con `node tests/p04/audio-mock.mjs`. La reproducción
audible real queda separada y sólo debe marcarse PASS con interacción humana y
un navegador que exponga Speech Synthesis.

### Ejecución controlada de P04

| Prueba | Resultado | Evidencia |
|---|---|---|
| Preflight y base activa | PASS | `clinica_dolor_demo_20261010_final`; guard exit 0 |
| Base protegida como destino | PASS | `bd_clinica` fue rechazada con exit 2 |
| GET sin sesión | PASS | HTTP 302 a `/historia-clinica/acceso` |
| Login sintético por PIN | PASS | HTTP 200 y cookie `CLINICAJWT` en sesión local |
| P04 autorizado | PASS | HTTP 200; modal, catálogo de preguntas y paciente sintético |
| Selección No, guardado y avance | PASS | cambio sintético persistido y siguiente pregunta visible |
| Regreso a pregunta | PASS | radio previamente guardado recuperado como `No` |
| Campo dependiente | PASS parcial | se habilitó al elegir `Sí`; persistencia vía evento de texto no quedó verificada de forma independiente |
| Endpoint autorizado de actualización | PASS | HTTP 200 con paciente/registro propios; la fila se restauró al baseline |
| Comentarios | PASS | se incorporó la tabla sintética faltante y el endpoint devolvió HTTP 200 con `#comentarioModulos` |
| GET cruzado | PASS | paciente de clínica distinta respondió HTTP 403 |
| POST cruzado | PASS | actualización sobre paciente distinto respondió HTTP 403 y no alteró datos |
| Audio mock | PASS | `node tests/p04/audio-mock.mjs`; texto y cancelación sin superposición |
| Reproducción audible | NO VERIFICADO | el navegador de pruebas no permite demostrar audio audible humano |
| Finalización del módulo | NO VERIFICADO | no se ejecutó una escritura de finalización dentro de esta actividad |

La primera comprobación del panel de comentarios devolvió HTTP 500 genérico
porque el fixture no contenía `pac_historia_clinica_comentario`. Se corrigió
únicamente la infraestructura sintética: se agregó esa tabla a
`tests/fixtures/security-fixtures.sql` y a la base sintética autorizada, sin
modificar el runtime clínico. Antes de cerrar la sesión se restauraron las
respuestas P04 sintéticas modificadas durante la prueba; la línea base quedó en
6 filas para 9301 y 0 para 9302.

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
| 8. Procedimientos que ha utilizado para controlar el dolor | **VALIDACIÓN** | Procedimientos y tratamientos con catálogo sintético 10/10 | `/historia-clinica/procedimientos-control-dolor/paciente/9301` — P10-CATALOG-UNBLOCK-02; HTTP PASS, navegador NO VERIFICADO |
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

## P05-FUNC-01 — funcionamiento y seguridad — 2026-10-11

La guarda de P05 confirmó que las comprobaciones se ejecutaron contra
clinica_dolor_demo_20261010_final, nunca contra bd_clinica. Los pacientes
sintéticos 9301 y 9302 pertenecen a clínicas distintas. El preflight fue
PASS, pero el catálogo actual contiene 0 temas, 0 preguntas y 0 respuestas;
además, la tabla activa no tiene todavía la columna pregunta que el SQL real
consume. El fixture de pruebas se amplió únicamente con esa columna
contractual, sin inventar temas, preguntas ni reglas.

| Prueba | Resultado |
|---|---|
| Guardas de localStorage por paciente/módulo | PASS estático |
| Radios Sí/No y etiquetas asociadas | PASS estático |
| Bloqueo de doble guardado/finalización | PASS estático |
| Modelo restringe paciente, pregunta y tema | PASS estático |
| idRol no decide autorización | PASS estático |
| Comentario→finalización y next_url | PASS estático |
| PHP lint, JS inline y git diff --check | PASS |
| GET P05 sin sesión | PASS; HTTP 302 |
| POST de respuesta sin sesión | PASS; HTTP 302 |
| GET propio/cruzado autenticado | NO VERIFICADO |
| Siete temas, dependencias, guardado y recuperación | NO VERIFICADO; catálogo sintético vacío |
| Rollback, doble POST real, finalización y dashboard | NO VERIFICADO |

La creación de respuestas faltantes en GET es un efecto secundario
preexistente y permanece documentado; no se ejecutó sobre datos reales.
La matriz A–O reproducible está en tests/p05/README.md. P05 permanece en
VALIDACIÓN.

## P05-TEST-DATA-01 — catálogo sintético y esquema — 2026-10-11

El diagnóstico revisó `tests/p05`, `tests/p04`,
`tests/fixtures/security-fixtures.sql`, el historial de Git y las referencias
del proyecto. No se encontró SQL versionado, migración, seeder, respaldo ni
fixture que contenga los siete temas y las preguntas reales de P05. No se
consultó ni exportó `bd_clinica` y no se inventaron textos clínicos.

La guarda fail-closed confirmó que la conexión activa fue exactamente
`clinica_dolor_demo_20261010_final`, con pacientes sintéticos 9301/9302 en
clínicas 9101/9102. La base sintética tenía las tres tablas P05 pero la tabla
`pac_preguntas_modulo_3` no tenía `pregunta`; el script reproducible
`tests/p05/prepare-synthetic.php --repair-schema` añadió esa columna sólo en
la base allowlisted, siguiendo el contrato ya presente en
`tests/fixtures/security-fixtures.sql` y en `AntecedentesNoPatologicosModel`.

| Control | Resultado |
|---|---|
| Base activa allowlisted y distinta de `bd_clinica` | PASS |
| Guarda de esquema explícita | PASS |
| Columna `pregunta` en base sintética | PASS; reparada y verificada |
| Siete temas reales encontrados en el repositorio | FAIL/BLOQUEADO; fuente inexistente |
| Preguntas reales importadas | NO VERIFICADO; no se inventaron |
| Respuestas/escenarios sintéticos A–E | NO VERIFICADO; depende del catálogo |
| Pruebas HTTP autenticadas P05 | NO VERIFICADO; catálogo no disponible |
| Regresión focal P04: aislamiento, flujo, siguiente módulo y mock de audio | PASS |
| `bd_clinica` modificada | PASS; no tocada |

P05 permanece en VALIDACIÓN. Para continuar se necesita proporcionar una
fuente versionada/aprobada de los siete temas y preguntas, o autorizar una
lectura limitada y no personal de esas tablas de configuración desde la base
clínica. La siembra preexistente durante GET y los riesgos de autorización
siguen documentados.

## P05-UI-01 — modernización visual — 2026-10-11

La vista `app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php`
fue alineada con el patrón visual local de P04 sin alterar contratos clínicos.
El `app.css` se carga con una versión derivada de `filemtime()` y los estilos
quedaron scoped a `body.patient-nonpathological-page`.

| Control | Resultado |
|---|---|
| Vista real de P05 verificada | PASS |
| Hero, acciones y contexto del paciente | PASS estático |
| Tarjetas Sí/No, labels y foco | PASS estático |
| Modal, Escape, cierre por fondo y retorno de foco | PASS estático |
| Secuencia comentario → finalización → `next_url` preservada | PASS contractual |
| PHP lint | PASS |
| JavaScript inline | PASS |
| CSS y `git diff --check` | PASS |
| P04 sin cambios funcionales | PASS por diff acotado |
| Captura autenticada P05 a 1440/375 | NO VERIFICADO; catálogo sintético vacío y sin sesión reproducible |
| Render real de preguntas y siete temas | NO VERIFICADO; no se inventaron registros |

En el alcance original de P05-UI-01 el modal no implementaba audio; esa
limitación quedó resuelta posteriormente por P05-COMPLETE-01, documentada al
inicio de este archivo. P05 permanece en VALIDACIÓN.
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

## P09-RESTORE-REAL-01 — medicamento para controlar el dolor

La línea base reutilizable de P09 está en `tests/p09/`. El ZIP autorizado
`P09_CATALOGOS_SEGUROS.zip` se validó sin ejecutar volcados completos y se
procesó con `import-catalog.php`, que sólo acepta la base exacta
`clinica_dolor_demo_20261010_final`, valida las cuatro entradas esperadas y
extrae las filas allowlisted. Resultado: 6 temas y 46 preguntas, con la
columna `pregunta` sintética faltante y relaciones de fixture añadidas; 0
respuestas importadas. La segunda ejecución fue idempotente.

Procedimiento reproducible:

```powershell
php tests/p09/import-catalog.php --archive=C:\Users\silvi\Downloads\P09_CATALOGOS_SEGUROS.zip --json
php tests/p09/prepare-synthetic.php --database=clinica_dolor_demo_20261010_final --json
node tests/p09/contract-check.mjs
node tests/p09/audio-mock.mjs
powershell -ExecutionPolicy Bypass -File tests/p09/start-server.ps1 -Port 8109
php tests/p09/http-crud.php --base-url=http://p09clean.localhost:8109 --database=clinica_dolor_demo_20261010_final --app-key=<clave-de-la-ejecución>
php tests/p09/cleanup-synthetic.php --database=clinica_dolor_demo_20261010_final
```

PASS: login sintético, GET propio, seis AJAX, persistencia de No/Sí y sus
dependencias, recuperación, avance automático, una pulsación de Siguiente,
anti-doble-clic, modal, Escape/foco, aislamiento cruzado, finalización,
`next_url` e idempotencia. PASS también para PHP lint, contrato, mock de audio,
importación idempotente, limpieza sintética y `git diff --check`. La prueba
visual real disponible (~1280×720) generó capturas del modal, primera pregunta
y último tema.

NO VERIFICADO: viewports 1440×900, 1280×720 exacto, 768×1024 y 375×667 por
falta de control de viewport en el navegador disponible; consola DevTools,
reproducción audible humana, fallo forzado de red y observación de no-op SQL a
nivel de sentencia. El servidor de prueba fue detenido y las respuestas y
finalizaciones sintéticas se limpiaron; el catálogo se conservó. No se usó
`bd_clinica`, no hubo commits/despliegues y P10 no fue iniciado.

## P10-CATALOG-UNBLOCK-02 — procedimientos para controlar el dolor

La línea base reutilizable de P10 está en `tests/p10/`. El procedimiento es
fail-closed: sólo acepta `clinica_dolor_demo_20261010_final`, comprueba la
estructura existente y no escribe durante el preflight. No se ejecutaron
volcados completos ni se tocó `bd_clinica`.

Procedimiento reproducible:

```powershell
php tests/p10/prepare-synthetic.php --database=clinica_dolor_demo_20261010_final --json
node tests/p10/contract-check.mjs
node tests/p10/audio-mock.mjs
powershell -ExecutionPolicy Bypass -File tests/p09/start-server.ps1 -Port 8109
php tests/p10/http-crud.php --base-url=http://p09clean.localhost:8109 --database=clinica_dolor_demo_20261010_final --app-key=<clave-de-la-ejecución>
```

La preparación confirmó dos pacientes sintéticos independientes (clínicas
9101 y 9102) y la base exacta `clinica_dolor_demo_20261010_final`. Se verificó
`P10_CATALOGO_SEGURO.zip`, se validó el SQL interno y se cargaron exactamente
10 tratamientos. La tabla demo tenía el campo heredado `tratamiento`; como
estaba vacía, se corrigió a `procedimiento`. También se añadió `comentarios` a
la tabla sintética de respuestas. No se ejecutó el dump completo ni se tocó
`bd_clinica`.

PASS: preflight aislado, PHP lint, sintaxis JS inline, contrato HTML/JS, mock
de audio, importación idempotente, catálogo 10/10, login sintético, GET propio
sin siembra, sesión ausente HTTP 302, acceso cruzado GET/AJAX/POST HTTP 403,
JSON seguro, AJAX de los diez tratamientos, respuestas No/Sí, dependencias,
efectos adversos, descripción, comentarios, recuperación, finalización HTTP
200, `next_url` a evaluación del dolor, finalización idempotente, CRUD propio
de procedimientos y limpieza. El frontend usa URLs relativas del mismo origen,
conserva el modal con la estructura Bootstrap centrada de P04 y ofrece mensaje
genérico con reintento.
No se modificó el router de P09 y el servidor de prueba se detuvo al terminar.

NO VERIFICADO: no-op SQL a nivel de sentencia, error de red observado en
navegador, modal centrado observado en navegador, renderizado autenticado,
responsive 1440/1280/768/375, overflow, foco/teclado observado, consola
DevTools, captura full-page y reproducción audible humana. El IAB bloqueó la
página sintética local con «Solicitud no disponible»; no se probó el host
clínico ni se ejecutaron escrituras sobre datos reales. P10 permanece en
VALIDACIÓN y P11 no fue iniciado.

### P10-MODAL-EXACT-PARITY-01

PASS estático: P10 adopta la misma jerarquía HTML de P04 (`modal`,
`modal-dialog modal-dialog-centered`, `modal-content`, encabezado, cuerpo y
footer), el degradado blanco/lavanda del encabezado, las variantes de botones,
el orden Escuchar/Detener/Entendido y las reglas responsive scoped. Se
conservan apertura automática, voz es-MX, cancelación, Escape, foco, clic fuera
y la guarda contra reapertura durante renderizados AJAX.

NO VERIFICADO: comparación renderizada P04/P10 a 1440×900, 1280×720 y
375×667, capturas, consola DevTools, foco observado y reproducción audible
humana; el navegador disponible no ofreció una sesión sintética reproducible.
P10 permanece en VALIDACIÓN.
