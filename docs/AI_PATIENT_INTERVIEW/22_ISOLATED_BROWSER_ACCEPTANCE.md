# Fase 4.10 — Aceptación visual aislada del chat

Fecha: 2026-10-12  
Estado: preparado para intervención manual del propietario; la aceptación visual todavía no está ejecutada.

## Estrategia de aislamiento

La fixture alterada de Fase 4.9 no se reutilizó. Se creó la base local `clinica_dolor_browser_20261012` exclusivamente mediante un volcado de `clinica_dolor_demo_20261010_final`. No se leyó ni modificó `bd_clinica`.

La base dedicada evita que los servidores PHP preexistentes puedan compartir accidentalmente las filas de aceptación. Se comprobó que el puerto 8098 estaba libre antes del arranque y que el listener pertenece al PID iniciado por el arnés. No se detuvieron procesos ajenos.

Archivos de arnés añadidos:

- `tests/patient-interview/prepare-isolated-browser-synthetic.php`
- `tests/patient-interview/start-isolated-browser-server.ps1`
- `tests/patient-interview/verify-isolated-prebrowser.php`

No se modificaron controladores, modelos, rutas, formularios tradicionales, chat, catálogos ni `.env`.

## Escenario sintético

- Base efectiva: `clinica_dolor_browser_20261012`
- Paciente sintético: 9301
- Clínica: 9101
- Paciente de separación: 9302
- Clínica de separación: 9102
- Módulo: M03, tema 1, preguntas 1–5
- P1: `Sí`; P2–P4: respuestas sintéticas no vacías; P5: vacía
- Catálogo verificado: P1 de tipo select y P2–P5 de tipo input
- Cinco filas, una por pregunta, sin duplicados

El snapshot nuevo conserva identificadores, longitudes y hashes de las filas iniciales sin exponer el contenido en este informe:

- Archivo: `C:\Users\silvi\AppData\Local\Temp\clinica-browser-m03-9301-20261012-isolated.json`
- SHA-256: `10917e7ac86229b1d0cfed0a01a49c07c94fa049471e23ed3efbc8c2a4b16049`
- El snapshot 4.8 permanece intacto.

## Verificación previa al navegador

| Etapa | Resultado |
|---|---|
| Base dedicada efectiva | PASS |
| Separación 9301/9302 y clínicas | PASS |
| Preparación focal sintética | PASS |
| Snapshot nuevo | PASS |
| Puerto 8098 libre antes de iniciar | PASS |
| PID propietario del listener | PASS — PID 56588 |
| Login HTTP de lectura | PASS — HTTP 200 |
| GET autenticado de entrevista | PASS — HTTP 200 |
| Render del chat detectado | PASS |
| Fixture sin diferencias tras login/GET | PASS |
| Duplicados M03 | NO |
| Gemini | DESHABILITADO / NO UTILIZADO |

No se ejecutaron suites que escriben sobre el escenario mientras quedó reservado para aceptación manual.

## Acceso manual

Servidor preparado:

- URL de login: `http://127.0.0.1:8098/historia-clinica/login`
- URL de entrevista: `http://127.0.0.1:8098/historia-clinica/entrevista`
- PID: `56588`
- Puerto: `8098`
- Base: `clinica_dolor_browser_20261012`

El PIN sintético no se imprime ni se registra. Para copiarlo localmente al portapapeles sin mostrarlo en la consola:

```powershell
$pin = ([regex]::Match((Get-Content .\tests\p11\http-crud.php -Raw), "pin'\s*=>\s*'([^']+)'")).Groups[1].Value
if ([string]::IsNullOrWhiteSpace($pin)) { throw 'PIN sintético no encontrado' }
Set-Clipboard -Value $pin
Remove-Variable pin
```

El propietario debe pegarlo manualmente en el formulario de login. No usar `clinica.test` para esta aceptación.

## Recorrido solicitado al propietario

1. Abrir la URL de entrevista y comprobar P1–P4.
2. Navegar adelante y atrás sin confirmar ni editar P1–P4.
3. Llegar a P5, escribir una respuesta sintética y cancelar; no debe persistir.
4. Introducir una respuesta sintética válida en P5 y confirmar una sola vez.
5. Recargar la entrevista y avisar cuando finalice.

No se debe cerrar ni reiniciar el servidor antes de comunicar el resultado. La aceptación manual no se sustituye por estas comprobaciones HTTP.

## Estado de aceptación

```text
TEST_ISOLATION: PASS
CONCURRENT_WRITERS_EXCLUDED: PASS
SERVER_PID_VERIFIED: PASS
SYNTHETIC_DATABASE_VERIFIED: PASS

FIXTURE_PREPARED: PASS
INITIAL_SNAPSHOT: PASS
PRE_BROWSER_INTEGRITY: PASS

BROWSER_LOGIN: PASS — comprobación automatizada previa; aceptación humana pendiente
BROWSER_CHAT_RENDER: PASS — comprobación automatizada previa; aceptación humana pendiente
BROWSER_NAVIGATION: NOT_VERIFIED
BROWSER_CANCEL: NOT_VERIFIED
BROWSER_CONFIRM_P5: NOT_VERIFIED

P1_P4_PRESERVED: NOT_VERIFIED
P5_PERSISTED: NOT_VERIFIED
UNEXPECTED_WRITES: NOT_VERIFIED

BD_CLINICA_MODIFIED: NO

## Triage focal — navegación 8098 y paciente demo 310

Fecha: 2026-10-12. Revisión exclusivamente de lectura.

### Paciente demo 310

La consulta efectiva confirmó `DATABASE() = bd_clinica`, 25 filas M03 para el paciente 310, sin duplicados. La comparación técnica contra el respaldo conservado `clinica-demo-m03-310-20261012-140310.sql` —SHA-256 `4621bc1da84f3d8eaaac54e7cd26d629054914b2b8e5d157038fd02212de6b3b`— encontró exactamente las diferencias ya documentadas: P2, P3 y P5. Los identificadores de fila permanecen iguales y no se detectaron cambios adicionales.

No se imprimieron valores clínicos. No se ejecutaron escrituras, restauraciones ni lecturas del formulario tradicional.

### Rutas del servidor sintético

El listener 8098 continúa activo y pertenece al PID 59900. La base configurada para esa instancia es `clinica_dolor_browser_20261012`.

| Ruta | Resultado sin sesión | Interpretación |
|---|---:|---|
| `/historia-clinica` | HTTP 302 a `/historia-clinica/acceso` | Ruta existente; protección de autenticación |
| `/historia-clinica/acceso` | HTTP 200 | Formulario de acceso existente |
| `/historia-clinica/entrevista` | HTTP 302 a `/historia-clinica/acceso` | Ruta existente; requiere sesión |

La misma comprobación sin un encabezado Host artificial confirmó que el redirect apunta a `http://127.0.0.1:8098/historia-clinica/acceso`. Por tanto, el mensaje «No se pudo completar la solicitud» no se reproduce como 404 ni como ruta inexistente; con la evidencia disponible queda `NOT_VERIFIED` si provino de una sesión/cookie, navegación previa o cliente distinto.

Los enlaces inspeccionados son correctos para el portal sintético:

- «Volver a mi Historia Clínica»: `/historia-clinica`
- «Inicio» del menú de paciente: `/historia-clinica`
- Entrevista independiente: `/historia-clinica/entrevista`

```yaml
DEMO_310_CURRENT_INTEGRITY: PASS_AGAINST_LATEST_DOCUMENTED_POST_INCIDENT_STATE
DEMO_310_ADDITIONAL_CHANGES: NO
DEMO_310_P5_STATUS: PRESENT_AND_MATCHES_DOCUMENTED_CAPTURE

PORT_8098_STATUS: LISTENING
SERVER_PID_VERIFIED: PASS — PID 59900
SYNTHETIC_DATABASE_VERIFIED: PASS — clinica_dolor_browser_20261012

ROOT_ROUTE_ERROR_CAUSE: NOT_REPRODUCED_AS_ROUTE_FAILURE — unauthenticated request returns 302
LOGIN_GET_ROUTE: /historia-clinica/acceso — HTTP 200
CHAT_GET_ROUTE: /historia-clinica/entrevista — HTTP 302 without session
BACK_TO_HISTORY_LINK: /historia-clinica
HOME_LINK: /historia-clinica

PRODUCT_FIX_REQUIRED: NO_DEMONSTRATED
READY_FOR_MANUAL_BROWSER_TEST: YES — servidor reservado; no ejecutar otros arneses
BD_CLINICA_MODIFIED_BY_AUDIT: NO
```
PATIENT_310_MODIFIED: NO
LEGACY_M03_MODIFIED: NO
PRODUCT_CODE_MODIFIED: NO

BROWSER_INTEGRITY_GATE: NOT_VERIFIED
PHASE_5_AI_READINESS: BLOCKED
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

La limpieza focal sintética y la detención del PID 56588 se ejecutarán únicamente después de registrar la aceptación manual y sus verificaciones posteriores.

## Addendum — corrección focal de acceso y P1

La comprobación solicitada no se ejecutó contra `clinica.test` ni contra `bd_clinica`. Se realizó exclusivamente contra la base aislada.

### Causa del HTTP 405

El enrutador declara `GET /historia-clinica/acceso` para mostrar el formulario y `POST /historia-clinica/login` para autenticar. Por diseño, `GET /historia-clinica/login` devuelve 405. La URL correcta de acceso es:

`http://127.0.0.1:8098/historia-clinica/acceso`

La autenticación sintética por POST a `/historia-clinica/login` devolvió HTTP 200 y la entrevista autenticada devolvió HTTP 200.

### Causa del error al seleccionar «Sí»

La vista genera el botón visible «Sí» con `data-value="Si"`, que coincide con el valor permitido por el catálogo y con la validación del modelo (`Si`/`No`). No existe una discrepancia `Sí` frente a `Si` en el payload.

La causa demostrada está después de esa validación: `capturarRespuestaEntrevista()` prepara la llamada a `editarCuestionarioModulo()` pero omite `idPregunta`. El método compartido valida `idPregunta < 1` y devuelve `Datos de respuesta no válidos.` con HTTP 400. Por tanto, el defecto está en el contrato entre el flujo nuevo y el modelo M03 compartido, no en el texto del botón.

### Comportamiento de «No»

Con P1 ya respondida y P2–P4 no vacías, la prueba aislada devolvió HTTP 409 con la protección de dependencias. El estado de las cinco filas permaneció idéntico antes y después. No se debilitó la protección ni se eliminaron respuestas.

### Resultado de esta corrección focal

```text
LOGIN_405_ROOT_CAUSE: GET /historia-clinica/login no está declarado; /login es POST únicamente
CORRECT_LOGIN_GET_URL: http://127.0.0.1:8098/historia-clinica/acceso
SYNTHETIC_LOGIN_HTTP: PASS — acceso GET 200; login POST 200; entrevista GET 200

YES_RESPONSE_ERROR_ROOT_CAUSE: contrato chat-modelo incompleto; falta idPregunta al llamar editarCuestionarioModulo
YES_PAYLOAD_VALUE: Si
EXPECTED_CATALOG_VALUE: Si
YES_VALIDATION: FAIL — HTTP 400 posterior a la validación inicial
EXISTING_ANSWER_NAVIGATION: NOT_VERIFIED — el flujo no llega a navegación porque la persistencia falla

NO_DEPENDENCY_PROTECTION: PASS — HTTP 409 sin cambios
UNEXPECTED_WRITES: NO — fixture aislada sin diferencias durante las comprobaciones

CHAT_FIX_REQUIRED: YES
CHAT_FIX_APPLIED: NO — requiere autorización adicional por afectar el modelo M03 compartido
FILES_MODIFIED: sólo arnés sintético y documentación; ningún archivo productivo

P1_P4_PRESERVED: PASS — comprobación aislada
P5_CAPTURE: NOT_VERIFIED
CSRF_RUNTIME: PASS — token presente y aceptado hasta la validación de respuesta
CONCURRENCY_409_RUNTIME: PASS — 409 observado en la protección de dependencias; concurrencia obsoleta no repetida en esta ejecución
CROSS_PATIENT_AUTHORIZATION: NOT_VERIFIED en esta ejecución

BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
LEGACY_M03_MODIFIED: NO

READY_FOR_BROWSER_RETEST: NO — bloqueado por defecto compartido no autorizado para corregir
REAL_LLM_CONVERSATION: NOT_IMPLEMENTED
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

No se realizó ninguna corrección productiva. El servidor aislado fue detenido después del diagnóstico; no se entrega una URL de retest hasta recibir autorización para corregir de forma focal el contrato compartido y volver a ejecutar las pruebas sintéticas.

## Fase 4.11 — Corrección quirúrgica de `idPregunta`

La firma efectiva de `editarCuestionarioModulo($data)` requiere `idPaciente`, `idRespuesta`, `idPregunta`, `idTema`, `detalle` e `idTipo`. El formulario tradicional ya entrega `idPregunta` en su payload. El flujo `capturarRespuestaEntrevista()` validaba el identificador de catálogo, pero omitía ese campo al construir el arreglo destinado al método existente.

Se aplicó únicamente este cambio en `app/Models/AntecedentesNoPatologicosModel.php`, dentro de `capturarRespuestaEntrevista()`: se añadió `'idPregunta' => $idPregunta`. No se modificó la firma de `editarCuestionarioModulo()`, su lógica de autorización, la regla de dependencias, el formulario tradicional ni otros módulos.

### Regresión sintética

La conexión efectiva fue `clinica_dolor_browser_20261012`. Antes de las pruebas se verificaron cinco filas M03 para 9301, catálogo P1 `select`, P2–P5 `input`, separación de clínica y ausencia de duplicados.

| Caso | Resultado |
|---|---|
| GET incorrecto `/historia-clinica/login` | HTTP 405 esperado |
| GET correcto `/historia-clinica/acceso` | HTTP 200 |
| Login POST | HTTP 200 |
| Entrevista autenticada | HTTP 200 |
| P1 con valor contractual `Si` | HTTP 200; persistencia aceptada |
| P1 `No` con dependencias | HTTP 409; sin cambios |
| P5 confirmada | HTTP 200 |
| P1–P4 después de P5 | Preservadas |
| P5 tras la prueba | Restaurada focalmente a vacío |
| CSRF ausente | HTTP 403 |
| CSRF inválido | HTTP 403 |
| Respuesta obsoleta | HTTP 409 |
| Duplicados | 0 |
| Contratos focales | PASS: separación, CSRF, P05, navegación y velocidad |

La prueba de `Sí` fue una confirmación sintética explícita con el valor permitido. La prueba de P5 fue igualmente sintética y se revirtió solo en la base aislada tras conservar la evidencia. No se escribieron `bd_clinica` ni el expediente 310.

### Estado posterior

```text
HTTP_400_ROOT_CAUSE: CONFIRMED
MISSING_ID_PREGUNTA_FIXED: YES
FILES_MODIFIED: app/Models/AntecedentesNoPatologicosModel.php; arnés sintético de diagnóstico; este informe

YES_RESPONSE_HTTP: 200
YES_RESPONSE_VALIDATION: PASS — valor de catálogo `Si`
EXISTING_ANSWER_NAVIGATION: NOT_VERIFIED — requiere navegador; no se añadió lógica de navegación adicional
NO_DEPENDENCY_BLOCK: PASS — HTTP 409 sin escrituras

P5_CAPTURE: PASS — HTTP 200 en fixture aislada; estado restaurado después
P1_P4_PRESERVED: PASS
UNEXPECTED_WRITES: NO
DUPLICATE_PREVENTION: PASS

CSRF_RUNTIME: PASS — 403 ausente e inválido
CONCURRENCY_409: PASS — respuesta obsoleta HTTP 409
PATIENT_AUTHORIZATION: PASS en el flujo autenticado probado
CROSS_CLINIC_AUTHORIZATION: NOT_VERIFIED en esta ejecución

LEGACY_M03_MODIFIED: NO
SHARED_MODEL_MODIFIED: NO — solo se corrigió el arreglo construido por el flujo propio
OTHER_MODULES_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO

SYNTHETIC_REGRESSION: PASS
READY_FOR_BROWSER_RETEST: YES — usando la base aislada y el servidor dedicado
BROWSER_E2E: NOT_VERIFIED
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

El servidor de regresión se detuvo después de las pruebas. Para una nueva aceptación manual debe iniciarse de nuevo el servidor aislado y utilizarse la URL GET `/historia-clinica/acceso`; no se debe usar `clinica.test` para esta prueba.

## Fase 4.12 — Entorno preparado para aceptación manual

Se reutilizó exclusivamente `clinica_dolor_browser_20261012`. La fixture se preparó con P1–P4 respondidas sintéticamente y P5 vacía. Se generó un snapshot independiente sin sobrescribir los anteriores:

- Archivo: `C:\Users\silvi\AppData\Local\Temp\clinica-browser-m03-9301-20261012-phase412.json`
- SHA-256: `b23c1b9985ac9a7965c6c24b86b01d8ec87e38930e0f0000569303526b68ce62`
- Paciente: 9301, clínica sintética 9101
- Base efectiva: `clinica_dolor_browser_20261012`
- Filas M03: 5, sin duplicados

El puerto 8098 estaba libre. Se inició un único servidor y se verificó que el listener pertenece al PID 59900. Las lecturas previas no modificaron P1–P5: login GET `/historia-clinica/acceso` HTTP 200, login POST HTTP 200, entrevista autenticada HTTP 200, render del chat detectado y P5 aún vacía.

### Entrega al propietario

- Acceso: `http://127.0.0.1:8098/historia-clinica/acceso`
- Chat: `http://127.0.0.1:8098/historia-clinica/entrevista`
- PID reservado: `59900`
- Base: `clinica_dolor_browser_20261012`

Para copiar el PIN sintético al portapapeles sin imprimirlo:

```powershell
$pin = ([regex]::Match((Get-Content .\tests\p11\http-crud.php -Raw), "pin'\s*=>\s*'([^']+)'")).Groups[1].Value
if ([string]::IsNullOrWhiteSpace($pin)) { throw 'PIN sintético no encontrado' }
Set-Clipboard -Value $pin
Remove-Variable pin
```

Mientras el propietario realiza la prueba no deben ejecutarse arneses, suites ni peticiones de captura adicionales. La comprobación posterior de P1–P5 se hará solamente después de que comunique que terminó.

```text
BROWSER_ENVIRONMENT_READY: PASS
SERVER_PID_VERIFIED: PASS — PID 59900
SYNTHETIC_FIXTURE_INTEGRITY: PASS — snapshot y lecturas previas coinciden
LOGIN_GET: PASS — HTTP 200 en /historia-clinica/acceso
CHAT_AUTHENTICATED_GET: PASS — HTTP 200
OWNER_MANUAL_TEST: PENDING
BD_CLINICA_MODIFIED: NO
```
