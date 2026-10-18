# NATIVE-STT: implementación y cierre funcional

Fecha de cierre: 2026-10-13  
Proyecto: Clínica del Dolor

## Estado

El dictado nativo queda integrado únicamente en la Entrevista Conversacional. La
implementación usa la API de reconocimiento del navegador:

```js
window.SpeechRecognition || window.webkitSpeechRecognition
```

Se configura `es-MX`, reconocimiento continuo, resultados provisionales y una
alternativa escrita. La voz solo actualiza el campo visible en el navegador; no
envía ni guarda automáticamente una respuesta clínica. El envío, la propuesta,
la confirmación y la persistencia siguen siendo acciones separadas del flujo
clínico existente.

El inicio del micrófono no muestra un diálogo experimental ni requiere una
confirmación adicional en cada sesión local autorizada. La advertencia de
privacidad se conserva aquí: el navegador puede procesar el audio fuera de este
equipo según su implementación y configuración. Por ese motivo no se fuerza
`processLocally` y el dictado no queda autorizado para expedientes reales.

La disponibilidad del micrófono no se concede por el nombre de host ni por una
sesión local conectada a `bd_clinica`. La compuerta exige autorización sintética
del servidor: `APP_ENV=testing`, `CLINICAL_AI_SYNTHETIC_MODE=1`,
`NATIVE_STT_ENABLED=1`, proveedor externo desactivado y una base sintética de
la allowlist. La autorización de voz no sustituye autenticación, autorización
por paciente, CSRF, validación de contrato ni control de concurrencia.

La API Web Speech puede procesar audio mediante el navegador o su servicio
configurado. No se fuerza `processLocally`; por ello este cierre no afirma que
el reconocimiento sea completamente offline ni autoriza voz clínica real.

## Integración del campo

`app/views/paciente/entrevista.php` mantiene un único compositor por pregunta y
enlaza tanto el campo de texto existente como un `textarea` si un módulo lo
proporciona. El micrófono se inserta en el compositor compatible, conserva la
edición manual y nunca dispara una captura clínica por sí mismo.

El ciclo implementado es:

- `onstart`: informa que el navegador está escuchando.
- `onresult`: combina segmentos finales y provisionales, elimina solapamientos
  y actualiza el mismo campo sin duplicar texto.
- `onerror`: informa permisos, ausencia de micrófono o fallo del servicio y
  mantiene disponible la entrada escrita.
- `onend`: restablece el control y deja la respuesta para revisión y envío
  explícito.
- `pagehide`: detiene el reconocimiento activo al abandonar la página.

## Limpieza de la fase experimental

Se retiraron solamente los componentes que ya no tenían consumidores activos:

- ruta POST temporal de autorización de dictado;
- sesión efímera `native_stt_local_test` y sus hashes de cookie, agente y
  dirección;
- bloqueo de capturas con `NATIVE_STT_READ_ONLY_SESSION`;
- panel y aviso de «Prueba local activa · sólo lectura»;
- deshabilitación temporal de los controles clínicos;
- script de preparación de token local
  `tests/patient-interview/prepare-native-stt-local.ps1`.

No se retiraron autenticación, CSRF, autorización de expediente, validaciones,
adaptadores, contratos M01–M09 ni rutas clínicas. La eliminación del bloqueo
NATIVE-STT no convierte la voz en autorización de escritura: las capturas
continúan exigiendo sus controles normales.

## Verificación

Pruebas focalizadas ejecutadas:

- `php tests/patient-interview/native-stt-contract.php`: compuerta local,
  rechazo productivo, ruta/sesión/bloqueo temporales ausentes, variables
  sintéticas y controles de aplicación.
- `node tests/patient-interview/native-stt-js.test.js`: secuencia provisional
  `hola` → `hola buenas` → `hola buenas tardes`, deduplicación final, edición
  manual, inicio/parada, error de permiso, ausencia de solicitudes clínicas y
  ausencia de diálogos experimentales (`window.confirm`/`alert`).
- `php tests/patient-interview/voice-and-navigation-contract.php`: navegación,
  reconocimiento y ausencia de `processLocally = true`.
- `php -l` sobre los PHP modificados.
- `git diff --check`.

Las pruebas JavaScript simulan eventos del navegador y no sustituyen la
aceptación manual. El propietario reportó en Chrome sobre `clinica.test`:
micrófono iniciado, resultados progresivos, frase completa sin duplicados,
parada y edición manual correctas. Esa aceptación no se reejecutó desde este
entorno de Codex porque no hay una sesión Chrome/Edge disponible aquí.

## Privacidad y autorización clínica

No se incorporó Whisper, Python, un servicio persistente, Gemini ni un endpoint
externo. No se procesaron voces médicas reales, no se modificó `bd_clinica`, no
se modificó el paciente 310 y no se cambió `.env`.

La autorización para procesar datos clínicos reales mediante voz sigue siendo
denegada por esta compuerta. Antes de cualquier uso real deben definirse autorización clínica,
privacidad, soporte del navegador, política de procesamiento de audio y
validación institucional. Este documento no constituye autorización de
producción.

## Mantenimiento

Conservar la separación entre reconocimiento de voz y persistencia clínica.
Cambios futuros deben mantener una sola fuente de estado del campo, envío
explícito, CSRF y las señales de aislamiento local; no deben reintroducir
sesiones temporales ni bloqueos de escritura como mecanismo de prueba del
micrófono.
