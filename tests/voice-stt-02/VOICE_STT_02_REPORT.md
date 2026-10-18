# VOICE-STT-02 — Prototipo funcional de dictado local

Fecha de ejecución: 2026-10-13  
Proyecto: `C:\xampp\htdocs\clinica-dolor`

## Alcance y aislamiento

Se implementó y probó un prototipo temporal independiente de la aplicación clínica:

- El puente PHP escuchó únicamente en `127.0.0.1:8767`.
- El servicio Python escuchó únicamente en `127.0.0.1:8766` y no fue accesible directamente desde la interfaz.
- El navegador habló con el puente PHP; el puente habló con el motor STT local.
- El modelo se cargó desde el snapshot local de VOICE-STT-01. No se descargó ningún modelo durante esta fase.
- No hubo conexión con Gemini, Apache, `bd_clinica` ni ninguna base de datos.
- No se modificaron `.env`, datos clínicos, paciente 310, formularios tradicionales ni servicios compartidos.
- El audio se procesó en un archivo temporal y se eliminó después de cada solicitud; no se registraron audios ni transcripciones.

## Resultado reproducible

| Prueba | Resultado |
|---|---|
| `GET /health` a través del puente | HTTP 200 |
| WAV sintético de 3.144 s por PHP → Python → faster-whisper | HTTP 200 |
| Respuesta con texto y `persistido=false` | PASS |
| Audio con firma inválida | HTTP 422 |
| Acceso directo al servicio Python sin token | HTTP 403 |
| Archivos temporales STT remanentes | 0 |
| Procesos/listeners después del cierre | 0 |
| Base de datos consultada o modificada | NO |

La medición final por el puente fue:

- Duración del audio: 3.144 s.
- Latencia informada por el servicio: 6.166 s.
- Tiempo extremo a extremo HTTP: 8.698 s.
- La respuesta no se persistió.

El primer cierre detectó una carrera de terminación entre el proceso padre y su hijo listener. Se corrigió únicamente el script de detención para tolerar que un proceso propio ya hubiera salido; la limpieza final confirmó que no quedó ningún listener, proceso del prototipo ni estado temporal.

## Motor y benchmark reutilizado

- Python: 3.13.15, 64-bit.
- `faster-whisper==1.2.1`.
- `ctranslate2==4.8.3`.
- `av==18.1.0`.
- CPU INT8, 8 hilos.
- Modelo: `Systran/faster-whisper-small`, revisión `536b0662742c02347bc0e980a01041f333bce120`.
- Procedencia y licencia: snapshot del repositorio del modelo; la ficha del modelo declara licencia MIT: <https://huggingface.co/Systran/faster-whisper-small>.
- Almacenamiento local: `tests/voice-stt-01/model-cache/`.

Resultados del benchmark VOICE-STT-01, ejecutado con audio español ficticio y reutilizado como evidencia del motor:

| Audio | Duración | Transcripción | Latencia |
|---|---:|---|---:|
| corto | 3.144 s | negación y medicamentos | 4.402 s |
| medio | 8.848 s | negación, alcohol, ibuprofeno, dosis y frecuencia | 5.610 s |
| largo | 18.361 s | negaciones, medicamentos, dosis, dolor y efectos secundarios | 7.156 s |

- Carga fría del modelo: 2.051 s.
- RAM máxima observada: 588.9 MB.
- CPU agregada máxima observada: 795.2% en el proceso multiproceso.
- Después de cargar el modelo, la transcripción se realizó sin servicios externos.
- No existe consumo de tokens aplicable: faster-whisper es un modelo local y no entrega usage metadata de proveedor.

La prueba larga produjo una confusión potencialmente clínicamente relevante entre el nombre sintético de un medicamento y su transcripción. Esto impide tratar el dictado como verdad clínica automática; el texto debe permanecer editable y requerir revisión y envío explícito.

## Interfaz y navegador

La página independiente se abrió en el navegador integrado con URL `http://127.0.0.1:8767/` y se comprobó:

- Campo editable de respuesta.
- Botón de dictado con estados de permiso, grabación, detención, transcripción, listo y error recuperable en el código.
- Botón de envío explícito.
- La edición manual y el envío de una frase ficticia mostraron el estado «no se guardó ningún dato clínico».
- El navegador integrado dejó la solicitud de permiso de micrófono pendiente; no se concedió ningún permiso y no se obtuvo una captura real completada.
- No se utilizó una sesión clínica ni se intentó el login de la aplicación.

Por tanto, la captura `MediaRecorder → audio → transcripción → compositor` no está verificada con un micrófono real en este entorno. Sí está verificado el tramo HTTP local con WAV sintético y la edición/envío no persistente de la interfaz.

## Archivos creados o modificados por VOICE-STT-02

- `tests/voice-stt-02/stt_service.py`
- `tests/voice-stt-02/bridge.php`
- `tests/voice-stt-02/start-prototype.ps1`
- `tests/voice-stt-02/stop-prototype.ps1`
- `tests/voice-stt-02/VOICE_STT_02_REPORT.md`

El prototipo no se integró en PHP de la aplicación, no creó endpoints públicos, no instaló dependencias nuevas y no creó un servicio persistente.

## Entrega YAML

```yaml
PHASE: VOICE-STT-02
LOCAL_STT_SERVICE: PASS
LOOPBACK_ONLY: PASS
SYNTHETIC_ENVIRONMENT_VERIFIED: PASS
BROWSER_MICROPHONE_CAPTURE: NOT_VERIFIED
MEDIARECORDER_SUPPORTED: NOT_VERIFIED_REAL_CAPTURE
AUDIO_UPLOAD_VALIDATION: PASS
OFFLINE_TRANSCRIPTION: PASS
TRANSCRIPTION_TO_COMPOSER: PASS_FOR_SYNTHETIC_HTTP_PATH; NOT_VERIFIED_FROM_MIC
TRANSCRIPTION_EDITABLE: PASS
MANUAL_SEND: PASS_NON_CLINICAL
AUTOMATIC_CLINICAL_SAVE: NO

COLD_START: 2.051 s
WARM_TRANSCRIPTION_3S: 4.402 s
WARM_TRANSCRIPTION_10S: 5.610 s
WARM_TRANSCRIPTION_20S: 7.156 s
HTTP_BRIDGE_TRANSCRIPTION_3S: 8.698 s end_to_end; 6.166 s service
PEAK_RAM: 588.9 MB
CPU_USAGE: 795.2% aggregate peak observed

SPANISH_TRANSCRIPTION_ACCURACY: PARTIAL; synthetic benchmark passed 2/3 clinical-significance checks
MEDICATION_NAMES: PARTIAL; one long-audio medication name error
DOSES_AND_UNITS: PASS in benchmarked examples
NUMBERS: PASS in benchmarked examples
NEGATIONS: PASS in benchmarked examples
CLINICALLY_SIGNIFICANT_ERRORS: YES; medication-name confusion in long synthetic audio

OFFLINE_TRANSCRIPTION: YES
EXTERNAL_AUDIO_TRANSMISSION: NO
PERSISTENT_SERVICE_CREATED: NO
HTTP_ENDPOINT_CREATED: NO_PUBLIC; TEMPORARY_LOOPBACK_BRIDGE_ONLY

BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
LEGACY_FORMS_MODIFIED: NO
ENV_MODIFIED: NO

FILES_CREATED:
  - tests/voice-stt-02/stt_service.py
  - tests/voice-stt-02/bridge.php
  - tests/voice-stt-02/start-prototype.ps1
  - tests/voice-stt-02/stop-prototype.ps1
  - tests/voice-stt-02/VOICE_STT_02_REPORT.md
DEPENDENCIES_INSTALLED: NO_NEW_DEPENDENCIES; REUSED_VOICE_STT_01_VENV
RESULT: PARTIAL_PROTOTYPE_ACCEPTANCE
NEXT_RECOMMENDATION: HUMAN_REVIEW_AND_EXPLICIT_SEND_REQUIRED; DO_NOT_INTEGRATE_INTO_CLINICAL_APP_YET
```

## Addendum VOICE-STT-03B — Preparación de aceptación manual — 2026-10-13

No se modificó código durante esta validación. El preflight se realizó sobre el servidor existente y sano, sin reiniciar procesos. Hay 2.61 GB de RAM física libre, 7.01 GB de margen de compromiso, un único trabajador propio cargado, PHP en 8767, Python en 8766 y respuestas HTTP 200 para `/` y `/health`. El servicio continúa restringido a loopback y offline.

La prueba queda reservada para una única grabación manual de aproximadamente 20 segundos con Chrome y contenido ficticio. No se activó el micrófono ni se controló el navegador automáticamente. Los campos de aceptación real permanecen pendientes hasta la intervención del propietario.

La evaluación de hardware, solo lectura, identificó una NVIDIA GeForce RTX 4060 Laptop GPU con 8,188 MiB de VRAM total y 6,009 MiB libres en la consulta. No se instaló CUDA/cuDNN, no se ejecutó benchmark GPU y no se modificaron dependencias. La compatibilidad efectiva con CTranslate2 queda por verificar en un benchmark aislado posterior y no debe inferirse únicamente por el modelo de GPU.

```text
PHASE: VOICE-STT-03B

RESOURCE_PREFLIGHT: PASS
CHROME_MICROPHONE: PENDING_OWNER_ACTION
REAL_AUDIO_CAPTURE: PENDING_OWNER_ACTION
PROGRESSIVE_TRANSCRIPTION: PENDING_OWNER_ACTION
FIRST_TEXT_BEFORE_STOP: PENDING_OWNER_ACTION
TIME_TO_FIRST_TEXT: NOT_MEASURABLE_BEFORE_OWNER_TEST
SEGMENTS_PROCESSED: NOT_MEASURABLE_BEFORE_OWNER_TEST
SEGMENT_LATENCIES: NOT_MEASURABLE_BEFORE_OWNER_TEST
ACCUMULATED_DELAY: EXPECTED_LIMITED_BY_PRIOR_CPU_MEASUREMENTS
FINALIZATION_LATENCY: NOT_MEASURABLE_BEFORE_OWNER_TEST

TEXTAREA_PROGRESSIVE_UPDATE: PENDING_OWNER_ACTION
TEXT_ORDER_CORRECT: PENDING_OWNER_ACTION
DUPLICATE_PROTECTION: PENDING_OWNER_ACTION
MANUAL_EDIT_PROTECTION: PENDING_OWNER_ACTION
FINAL_SEGMENT_PROCESSED: PENDING_OWNER_ACTION
MANUAL_SEND: PENDING_OWNER_ACTION

MAX_CONCURRENT_INFERENCE: 1
PEAK_RAM: NOT_MEASURABLE_DURING_OWNER_TEST_YET; PREFLIGHT_AVAILABLE_RAM_2.61GB
CPU_USAGE: NOT_MEASURABLE_DURING_OWNER_TEST_YET

REAL_CHROME_ACCEPTANCE: PENDING
PERFORMANCE_CLASSIFICATION: PENDING_OWNER_ACTION; PRIOR_CPU_HTTP_EVIDENCE_LIMITED_REAL_TIME
PRIMARY_LIMITATION: CPU_SEGMENT_LATENCY_EXCEEDS_4S_WINDOW_IN_PRIOR_REAL_HTTP_TEST

GPU_MODEL: NVIDIA_GEFORCE_RTX_4060_LAPTOP_GPU
GPU_VRAM: 8188_MIB_TOTAL; 6009_MIB_FREE_AT_READ_TIME
GPU_ACCELERATION_FEASIBILITY: POTENTIALLY_FEASIBLE; NOT_BENCHMARKED
GPU_EVALUATION_REQUIRED: YES_IF_MANUAL_TEST_CONFIRMS_PERFORMANCE_LIMIT

CLINICAL_PERSISTENCE: NO
EXTERNAL_AUDIO_TRANSMISSION: NO
GEMINI_CALLS: 0
CODE_MODIFIED: NO
DATABASE_MODIFIED: NO
ENV_MODIFIED: NO

TEMP_FILES_REMAINING: 0
OWN_LISTENERS_REMAINING: 2_EXPECTED_LOOPBACK_LISTENERS_WHILE_RESERVED

FINAL_STATUS: READY_FOR_ONE_OWNER_MANUAL_CHROME_RECORDING
RECOMMENDED_NEXT_ACTION: OWNER_PERFORMS_ONE_20S_FICTITIOUS_RECORDING; THEN_REPORTS_RESULTS_BEFORE_STOPPING_PROTOTYPE
```

## Addendum VOICE-STT-02E — 2026-10-13

### Objetivo y evidencia de entrada

Esta corrección se limitó al prototipo aislado `tests/voice-stt-02`. La evidencia aportada desde Chrome normal indicó `audio/webm;codecs=opus`, Blob de 160063 bytes y HTTP 422. El archivo Blob original de Chrome no está disponible localmente; por ello se reprodujo el mismo formato con un WebM/Opus sintético decodificable y una variante con duración EBML no utilizable.

La validación anterior confirmó que un WebM/Opus sintético con metadatos de duración normales llegaba a Whisper. La reproducción focal sin duración utilizable devolvía exactamente `AUDIO_DURATION_INVALID` desde `validate_audio`, antes de invocar al trabajador STT. No se atribuye el rechazo a PHP, al MIME `audio/webm` ni al codec Opus.

### Corrección aplicada

`stt_service.py` conserva la validación de firma, contenedor, pista y codec Opus, pero cuando la duración de contenedor o stream no es utilizable decodifica los frames de audio mediante `container.decode(stream.index)` y calcula la duración por muestras y frecuencia. PyAV no entregaba frames de este WebM mediante `stream.decode()`; esa fue la corrección concreta que completó el fallback.

Los errores de contenedor, codec y decodificación permanecen explícitos y no se relajan los límites de 8 MB/30 s. `bridge.php` muestra códigos técnicos seguros para estos rechazos y actualiza la versión visible a `VOICE-STT-02E-2026-10-13`.

### Matriz sintética

La matriz se ejecutó con el mismo `Handler` HTTP, un trabajador STT sintético controlado y temporales locales; no se simuló una respuesta de Gemini ni se conectó la aplicación clínica.

| Caso | Resultado |
|---|---|
| A — WAV válido | PASS, HTTP 200, texto sintético no vacío |
| B — WebM/Opus válido | PASS, HTTP 200, codec y contenedor aceptados |
| B — WebM/Opus sin duración utilizable | PASS, HTTP 200, duración recuperada por decodificación PyAV |
| C — WebM malformado | PASS, HTTP 422, `CONTAINER_INVALID` |
| D — cuerpo vacío | PASS, HTTP 413 |
| E — MIME falsificado | PASS, HTTP 422, `CONTAINER_INVALID` |
| F — tamaño declarado mayor de 8 MB | PASS, HTTP 413 |
| G — audio sintético sin habla | PASS de transporte, HTTP 200 con texto vacío controlado; el frontend ya lo trata como `EMPTY_TRANSCRIPT` |
| H — Python directo sin token | PASS, HTTP 403 |
| I — error y timeout del servicio | PASS, HTTP 503/504 con códigos controlados |
| J — temporales | PASS, 0 archivos remanentes |

### Límite de la ejecución Whisper real

El servidor real se inició con el entorno virtual aprobado y el modelo local aprobado. Las solicitudes WAV y WebM posteriores al cambio no alcanzaron la transcripción porque el trabajador no pudo cargar `faster-whisper-small`: `mkl_malloc: failed to allocate memory`, expuesto por el servicio como `STT_STARTUP_FAILED` HTTP 503. La máquina tenía otros procesos Python concurrentes con consumo elevado; no se detuvieron procesos no identificados. El prototipo fue detenido limpiamente y no quedaron listeners 8766/8767.

Por tanto, el contrato de validación WebM/Opus queda PASS en HTTP controlado, pero la transcripción Whisper real posterior a esta corrección queda `NOT_VERIFIED` hasta repetirla con memoria disponible. No se usó un fallback clínico ni se persistió audio o texto.

```yaml
PHASE: VOICE-STT-02E
CHROME_RECORDED_FORMAT: audio/webm;codecs=opus
CHROME_BLOB_BYTES: 160063
CHROME_HTTP_STATUS: 422
EXACT_REJECTION_CONDITION: AUDIO_DURATION_INVALID
REJECTION_LAYER: Python validate_audio
ROOT_CAUSE_CONFIRMED: YES_SYNTHETIC_REPRODUCTION; ACTUAL_CHROME_BLOB_NOT_AVAILABLE
SYNTHETIC_WEBM_REPRODUCTION: PASS; VALID_OPUS_AND_DURATION-NONUTILIZABLE_VARIANT
WEBM_OPUS_VALIDATION: PASS
PYAV_DECODING: PASS_USING_CONTAINER_DECODE
WHISPER_TRANSCRIPTION: NOT_VERIFIED_POST_FIX; STT_STARTUP_FAILED_MKL_MEMORY
JSON_TEXT_RETURNED: PASS_CONTROLLED_HANDLER; REAL_WHISPER_POST_FIX_NOT_VERIFIED
COMPOSER_UPDATE: NOT_VERIFIED_FROM_REAL_CHROME
A_WAV: PASS
B_WEBM_OPUS: PASS
C_MALFORMED_WEBM: PASS_REJECTED
D_EMPTY: PASS_REJECTED
E_FORGED_MIME: PASS_REJECTED
F_OVERSIZED: PASS_REJECTED
G_NO_SPEECH: PASS_CONTROLLED_EMPTY_TEXT
H_DIRECT_NO_AUTH: PASS_REJECTED
I_SERVICE_ERROR: PASS
J_TEMP_FILES: PASS_ZERO
CORRECTION_APPLIED: YES
REAL_CHROME_ACCEPTANCE: NOT_VERIFIED
EXTERNAL_AUDIO_TRANSMISSION: NO
CLINICAL_PERSISTENCE: NO
GEMINI_CALLS: 0
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
ENV_MODIFIED: NO
LISTENERS_REMAINING: 0
FILES_MODIFIED:
  - tests/voice-stt-02/stt_service.py
  - tests/voice-stt-02/bridge.php
  - tests/voice-stt-02/generate-webm-opus-fixture.py
  - tests/voice-stt-02/test-webm-acceptance.py
  - tests/voice-stt-02/VOICE_STT_02_REPORT.md
NEXT_ACTION: REPEAT_REAL_WHISPER_WITH_MEMORY_AVAILABLE; THEN_ONE_MANUAL_CHROME_TEST
```

## Cierre

El prototipo demuestra un servicio STT local, aislado, autenticado y sin persistencia clínica, con transcripción offline y edición manual. No demuestra todavía captura real desde un micrófono en un navegador autorizado. Se detuvieron todos los procesos temporales y no se efectuaron cambios en el producto clínico.

## Addendum VOICE-STT-02C — 2026-10-13

### Evidencia del propietario

La prueba en Chrome normal confirmó activación del micrófono, pero no mostró texto reconocido. El campo editable y el envío explícito sí funcionaron. Por ello, la transcripción real de Chrome permanece como fallo observado y no se declara PASS.

### Causa demostrable en el código

El compositor aceptaba como éxito cualquier JSON con `resultado=true`, incluso cuando `texto` era vacío o no era una cadena. En ese caso asignaba `''` al campo y mostraba «Texto listo», sin distinguir entre audio vacío, respuesta sin palabras y fallo de transporte. Además, la captura dependía de un único evento de detención sin intervalo explícito de entrega de datos.

No fue posible determinar retrospectivamente si la prueba del propietario produjo un Blob vacío o si Whisper devolvió texto vacío, porque el prototipo no registra audio, transcripciones ni contenido clínico. La nueva instrumentación visible diferencia ambos casos sin registrar datos sensibles.

### Corrección aplicada

Se modificó únicamente `tests/voice-stt-02/bridge.php`:

- `MediaRecorder.start(250)` solicita entregas periódicas de chunks.
- `onstop` conserva el `MediaRecorder` activo en una referencia local, construye el Blob con los chunks recibidos y valida su tamaño antes del envío.
- `onerror` marca la grabación como fallida y evita enviar un Blob parcial.
- La respuesta HTTP se valida como JSON y como `resultado=true`.
- Una respuesta sin texto muestra «No se reconocieron palabras. Puedes escribir tu respuesta.»
- Se distinguen errores de audio, formatos no permitidos, servicio no disponible, timeout y respuestas inválidas.
- Se añadió timeout de 30 s con `AbortController` y se bloquean envíos duplicados mientras se transcribe.
- El texto válido se conserva editable y nunca se envía automáticamente a la aplicación clínica.

### Pruebas 02C

| Caso | Resultado |
|---|---|
| WAV sintético conocido por puente | HTTP 200, JSON válido, texto no vacío, `persistido=false` |
| Blob/cuerpo vacío | HTTP 413 |
| Firma de audio inválida | HTTP 422 |
| MIME no permitido | HTTP 415 |
| Python directo sin token | HTTP 403 |
| Temporales después de las pruebas | 0 |
| Mensaje para respuesta JSON sin texto | Implementado y verificable por código; no ejecutado con Chrome real |
| Timeout de frontend | Implementado con `AbortController`; no ejecutado con Chrome real |
| Captura real en Chrome posterior a la corrección | No verificada en esta sesión |

### Entrega 02C

```yaml
PHASE: VOICE-STT-02C
CHROME_USER_TEST: TRANSCRIPTION_NOT_DISPLAYED
MEDIARECORDER_INITIALIZED: NOT_VERIFIED_POST_FIX_IN_CHROME
AUDIO_TRACK_ACTIVE: NOT_VERIFIED_POST_FIX_IN_CHROME
BLOB_GENERATED: NOT_VERIFIED_POST_FIX_IN_CHROME
BLOB_SIZE: NOT_VERIFIED_POST_FIX_IN_CHROME
BLOB_MIME: NOT_VERIFIED_POST_FIX_IN_CHROME
HTTP_REQUEST_SENT: NOT_VERIFIED_POST_FIX_IN_CHROME
HTTP_RESPONSE_STATUS: NOT_VERIFIED_POST_FIX_IN_CHROME
PHP_BRIDGE_ACCEPTED: PASS_SYNTHETIC_WAV
WHISPER_EXECUTED: PASS_SYNTHETIC_WAV
WHISPER_RETURNED_TEXT: PASS_SYNTHETIC_WAV
JSON_VALID: PASS_SYNTHETIC_WAV
COMPOSER_UPDATED: PASS_MANUAL_TEXT; NOT_VERIFIED_FROM_REAL_MIC

ROOT_CAUSE: EMPTY_OR_MISSING_TEXT_WAS_TREATED_AS_SUCCESS_AND_RECORDING_CHUNKS_WERE_NOT_EXPLICITLY_TIMED
CORRECTION: APPLIED_IN_TESTS_VOICE_STT_02_BRIDGE_PHP
ERROR_FEEDBACK: PASS_BY_CODE_PATH
SYNTHETIC_TESTS: PASS_TRANSPORT_AND_VALIDATION; UI_EMPTY_JSON_AND_TIMEOUT_NOT_RUNTIME_VERIFIED
REAL_CHROME_TEST: PENDING_RETEST_BY_OWNER

EXTERNAL_AUDIO_TRANSMISSION: NO
CLINICAL_PERSISTENCE: NO
GEMINI_CALLS: 0
TEMP_FILES_REMAINING: 0
LISTENERS_REMAINING: 0_AFTER_CLEANUP
FILES_MODIFIED:
  - tests/voice-stt-02/bridge.php
  - tests/voice-stt-02/VOICE_STT_02_REPORT.md
REMAINING_BLOCKERS: REAL_CHROME_RETEST_REQUIRED
NEXT_ACTION: REPEAT_ONE_FICTITIOUS_CHROME_TEST_AND_CAPTURE_STATUS_ONLY
```

## Addendum VOICE-STT-02D — 2026-10-13

### Objetivo

Se añadió observabilidad local para una única repetición manual en Chrome. El panel no almacena audio, transcripciones, tokens ni telemetría externa; conserva únicamente la última transición y hasta 60 estados técnicos en memoria del navegador.

### Versión y caché

- Versión visible: `VOICE-STT-02D-2026-10-13`.
- Cabeceras HTML: `Cache-Control: no-store, no-cache, must-revalidate`, `Pragma: no-cache`, `Expires: 0`.
- El panel visible confirma que Chrome está viendo la versión entregada, sin depender de F12.
- El último estado muestra un identificador efímero de ejecución, timestamp relativo y metadatos técnicos permitidos.

### Estados observables

El panel puede mostrar: `PAGE_READY`, `MICROPHONE_CLICKED`, `PERMISSION_GRANTED`, `AUDIO_TRACK_ACTIVE`, `RECORDER_STARTED`, `AUDIO_CHUNK_RECEIVED`, `STOP_CLICKED`, `RECORDER_STOPPED`, `BLOB_READY`, `HTTP_REQUEST_STARTED`, `HTTP_RESPONSE_RECEIVED`, `JSON_PARSED`, `WHISPER_TEXT_RECEIVED`, `COMPOSER_UPDATED` y `ERROR`.

Los metadatos permitidos son MIME, bytes del Blob, número de chunks, pistas de audio, HTTP status, longitud de texto y códigos técnicos. No se muestran contenidos.

### Pruebas focalizadas

| Verificación | Resultado |
|---|---|
| PHP válido | PASS |
| Panel y versión visibles | PASS |
| `PAGE_READY` registrado | PASS |
| Envío manual sin persistencia | PASS |
| WAV sintético conocido | HTTP 200, JSON válido, texto no vacío, `persistido=false` |
| Temporales tras WAV | 0 |
| Estados y errores declarados en frontend | PASS estático |
| Captura real y estados MediaRecorder en Chrome normal | PENDIENTE; Chrome no está disponible en la sesión de Codex |

### Entrega 02D

```yaml
PHASE: VOICE-STT-02D
PROTOTYPE_VERSION: VOICE-STT-02D-2026-10-13
CHROME_CACHE_MITIGATION: PASS_NO_STORE_AND_VISIBLE_VERSION
FRONTEND_HANDLERS_VERIFIED: PASS_STATIC_AND_MANUAL_SEND
VISIBLE_DIAGNOSTICS: PASS
DIAGNOSTIC_STATES_TESTED: PAGE_READY_AND_MANUAL_SEND_RUNTIME; ALL_REQUIRED_STATES_PRESENT
ERROR_HANDLING: PASS_BY_CODE_PATH; REAL_CHROME_PENDING
SYNTHETIC_TESTS: PASS_WAV_TRANSPORT_AND_PAGE_PANEL

REAL_MICROPHONE_ROOT_CAUSE: NOT_CONFIRMED; OBSERVABILITY_REQUIRED
ROOT_CAUSE_CONFIRMED: NO
CORRECTION_IF_CONFIRMED: NOT_APPLIED; INSTRUMENTATION_ONLY

EXTERNAL_AUDIO_TRANSMISSION: NO
CLINICAL_PERSISTENCE: NO
GEMINI_CALLS: 0
BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
ENV_MODIFIED: NO

FILES_MODIFIED:
  - tests/voice-stt-02/bridge.php
  - tests/voice-stt-02/stop-prototype.ps1
  - tests/voice-stt-02/VOICE_STT_02_REPORT.md
READY_FOR_ONE_MANUAL_CHROME_TEST: YES
  NEXT_ACTION: OWNER_RUNS_ONE_FICTITIOUS_CHROME_TEST_AND_REPORTS_LAST_STATE_ONLY
```

## Addendum VOICE-STT-02F — 2026-10-13

### Diagnóstico de memoria

Se inspeccionó Windows mediante lecturas de solo lectura antes y después de la prueba. No había listeners 8766/8767 ni trabajadores propios activos al iniciar. La medición inicial mostró 15.78 GB de RAM física, 1.24 GB disponibles y 48 GB de paginación asignada. La medición final mostró 1.11 GB físicos libres, 63.78 GB de memoria virtual total y 2.89 GB libres; la memoria virtual comprometida era aproximadamente 95.5% del límite observado. Los contadores de rendimiento no estuvieron disponibles, por lo que el porcentaje se calculó con las métricas de `Win32_OperatingSystem` y se identifica como aproximado.

La carga directa del modelo local en un único proceso pasó. En cambio, el trabajador hijo del prototipo devolvió `RuntimeError: mkl_malloc: failed to allocate memory` con 4 hilos y también con 1 hilo. La ejecución HTTP completa con la configuración original de 8 hilos devolvió HTTP 503 `STT_STARTUP_FAILED`. Esto confirma un fallo de asignación durante la inicialización del trabajador hijo bajo el estado de memoria comprometida, no un problema del modelo, de PyAV ni del WebM.

No se terminaron procesos ajenos. La reducción temporal de hilos no cambió el resultado y fue descartada; se conservaron los 8 hilos configurados previamente. No se modificaron variables globales OMP/MKL/CT2 ni se aumentó el número de trabajadores. El manejo existente libera el trabajador propio, responde 503 y la limpieza final dejó cero listeners y temporales.

### Regresión controlada

La matriz A–J se repitió con el mismo `Handler` y trabajador sintético controlado: validación WebM/Opus normal y sin duración utilizable PASS HTTP 200; rechazos de contenedor, MIME, vacío y tamaño PASS; 403 sin autorización; 503/504 para errores y timeout; cero temporales. Esta matriz verifica el contrato de transporte y validación, pero no sustituye la inferencia real de Whisper.

```yaml
PHASE: VOICE-STT-02F
AVAILABLE_RAM_BEFORE_TEST: 1.24_GB_PHYSICAL_FREE
MEMORY_COMMIT_STATUS: APPROX_95.5_PERCENT_USED; 63.78_GB_TOTAL_VIRTUAL; 2.89_GB_FREE_VIRTUAL; PAGEFILE_48_GB
MODEL_LOADING: DIRECT_SINGLE_PROCESS_PASS; PROTOTYPE_CHILD_FAIL
MODEL_MEMORY_USAGE: NOT_MEASURED_EXACTLY; ALLOCATION_FAILED_IN_CHILD
CPU_THREADS: 8_CONFIGURED; 4_AND_1_PROBES_SAME_FAILURE
STT_WORKERS: 1
MEMORY_ROOT_CAUSE: CONFIRMED_SYSTEM_COMMITTED_MEMORY_PRESSURE_DURING_CHILD_MKL_ALLOCATION
MEMORY_CORRECTION: NONE_APPLIED; THREAD_REDUCTION_NOT_EFFECTIVE; ORIGINAL_CONFIGURATION_RESTORED

WEBM_VALIDATION: PASS
WEBM_PYAV_DECODING: PASS
WHISPER_MODEL_LOADED: PASS_DIRECT; FAIL_IN_PROTOTYPE_WORKER
WHISPER_INFERENCE_EXECUTED: NO
WHISPER_TEXT_NONEMPTY: NOT_VERIFIED
PHP_RESPONSE: HTTP_503_STT_STARTUP_FAILED
COMPOSER_TEXT_READY: NOT_VERIFIED_FROM_REAL_WHISPER

END_TO_END_SYNTHETIC: BLOCKED_AT_WORKER_MODEL_INITIALIZATION
REAL_CHROME_ACCEPTANCE: NOT_VERIFIED

EXTERNAL_AUDIO_TRANSMISSION: NO
CLINICAL_PERSISTENCE: NO
GEMINI_CALLS: 0

BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
ENV_MODIFIED: NO

TEMP_FILES_REMAINING: 0
LISTENERS_REMAINING: 0
FILES_MODIFIED_THIS_PHASE:
  - tests/voice-stt-02/VOICE_STT_02_REPORT.md
REMAINING_BLOCKERS: SYSTEM_MEMORY_COMMIT_PRESSURE; CANNOT_STOP_UNRELATED_PROCESSES
NEXT_ACTION: FREE_RESOURCES_OUTSIDE_THIS_TASK_AND_REPEAT_ONE_REAL_WEBM_WHISPER_REQUEST; DO_NOT_RUN_CHROME_MANUAL_ACCEPTANCE_YET
```

## Addendum VOICE-STT-02G — 2026-10-13

### Inspección de arquitectura y recursos

El lanzador inicia exactamente dos procesos propios: el puente PHP en `127.0.0.1:8767` y el servicio Python en `127.0.0.1:8766`. PHP no carga Whisper; sólo reenvía el cuerpo al servicio local. El servicio crea un único `TranscriptionWorker` bajo demanda. Ese trabajador crea un único `multiprocessing.Process` y carga el modelo una vez en `worker_main`; no se demostraron dos modelos cargados concurrentemente dentro de una ejecución.

La ejecución directa usa `tests/voice-stt-01/.venv/Scripts/python.exe`, Python 3.13.15, `faster-whisper 1.2.1`, `ctranslate2 4.8.3` y PyAV 18.1.0. En Windows, el intérprete informa como base ejecutable `C:\Users\silvi\AppData\Local\Programs\Python\Python313\python.exe`; el proceso hijo `spawn` inicia un intérprete fresco y debe reservar el modelo y sus buffers bajo la presión de compromiso existente. La carga directa pasó, pero el hijo devolvió `RuntimeError: mkl_malloc: failed to allocate memory`, tanto con 4 como con 1 hilo. La configuración original de 8 hilos se conservó.

El preflight de esta fase encontró 15.78 GB físicos, sólo 0.64 GB libres, 63.78 GB de memoria virtual total, 2.54 GB libres y 48 GB de paginación asignada. La memoria comprometida aproximada era 96%. No había listeners 8766/8767, estado runtime ni trabajadores propios al comenzar. Por margen de seguridad, la inferencia se bloqueó y no se ejecutó otra carga del modelo.

### Conclusión y propuesta no implementada

La causa demostrada es presión general de memoria comprometida durante la reserva MKL del proceso hijo; no se demostró una duplicación de modelos causada por el prototipo. No se modificó la arquitectura ni se terminaron procesos ajenos. La propuesta mínima, sólo si el propietario libera recursos y el fallo persiste con memoria holgada, sería evaluar una única instancia residente de Whisper en un proceso local ya iniciado, evitando una reserva adicional durante cada arranque de trabajador. Esa propuesta requiere autorización adicional y no se implementa en esta fase.

La validación WebM/Opus y PyAV permanece PASS según la matriz controlada 02E. La transcripción real WebM/Opus sigue sin evidencia porque el preflight actual no permite ejecutarla.

```yaml
PHASE: VOICE-STT-02G
MEMORY_PRESSURE: CONFIRMED; APPROX_96_PERCENT_COMMITTED
WORKER_STARTUP_ROOT_CAUSE: MKL_ALLOCATION_FAILURE_UNDER_SYSTEM_COMMIT_PRESSURE
MODEL_DUPLICATE_LOADING: NOT_DEMONSTRATED; ONE_WORKER_ONE_MODEL_PER_RUN
DIRECT_VS_WORKER_DIFFERENCES: DIRECT_MAIN_PROCESS_PASS; WINDOWS_SPAWN_CHILD_FRESH_INTERPRETER_FAILS
RESOURCE_PREFLIGHT: BLOCKED; 0.64_GB_PHYSICAL_FREE; 2.54_GB_VIRTUAL_FREE
SAFE_TO_RUN_INFERENCE: NO

WEBM_VALIDATION: PASS_PRIOR_CONTROLLED_MATRIX
PYAV_DECODING: PASS_PRIOR_CONTROLLED_MATRIX
WORKER_MODEL_LOADING: FAIL_MKL_MALLOC
WHISPER_INFERENCE: NOT_EXECUTED_THIS_PHASE
TEXT_NONEMPTY: NOT_VERIFIED
PHP_JSON_RESPONSE: HTTP_503_STT_STARTUP_FAILED_IN_PRIOR_REAL_ATTEMPT
TOTAL_LATENCY: NOT_MEASURED_THIS_PHASE
PEAK_MEMORY: NOT_MEASURED_EXACTLY; SYSTEM_MEMORY_MARGIN_INSUFFICIENT

END_TO_END_SYNTHETIC: BLOCKED_BY_RESOURCE_PREFLIGHT
REAL_CHROME_ACCEPTANCE: NOT_VERIFIED

EXTERNAL_AUDIO_TRANSMISSION: NO
CLINICAL_PERSISTENCE: NO
GEMINI_CALLS: 0

TEMP_FILES_REMAINING: 0
LISTENERS_REMAINING: 0
FILES_MODIFIED:
  - tests/voice-stt-02/VOICE_STT_02_REPORT.md
REMAINING_BLOCKERS: INSUFFICIENT_COMMITTED_MEMORY; OWNER_INTERVENTION_REQUIRED_TO_FREE_RESOURCES
NEXT_ACTION: OWNER_FREES_RESOURCES_OR_RESTARTS_SYSTEM; THEN_ONE_PRECHECKED_REAL_WEBM_INFERENCE; NO_CHROME_TEST_YET
```

## Addendum VOICE-STT-02H — 2026-10-13

### Preflight final

El preflight se ejecutó antes de iniciar PHP, Python o Whisper. El equipo reportó 15.78 GB de RAM física, 1.00 GB libre, 63.78 GB de memoria virtual total, 2.82 GB de memoria virtual libre y 48 GB de paginación asignada. El compromiso aproximado fue 95.6%. No existían `runtime-state.json`, listeners 8766/8767 ni trabajadores Python propios del prototipo. Los procesos Python observados no fueron identificados como parte de VOICE-STT-02 y no se detuvieron.

El margen actual es inferior al escenario anterior de 1.24 GB de RAM física libre, en el que el trabajador ya falló con `mkl_malloc: failed to allocate memory`. Con base en esa evidencia previa y en la memoria medida, el preflight se bloqueó; no se inició el servidor, no se cargó el modelo y no se realizó una nueva inferencia.

```yaml
PHASE: VOICE-STT-02H
AVAILABLE_RAM: 1.00_GB_PHYSICAL_FREE
MEMORY_COMMIT: APPROX_95.6_PERCENT_USED; 2.82_GB_VIRTUAL_FREE; PAGEFILE_48_GB
RESOURCE_PREFLIGHT: BLOCKED

WORKER_MODEL_LOADING: NOT_ATTEMPTED; PRIOR_FAILURE_MKL_MALLOC
WEBM_OPUS_DECODING: NOT_REPEATED; PRIOR_CONTROLLED_PASS
WHISPER_INFERENCE: NOT_EXECUTED
TEXT_NONEMPTY: NOT_VERIFIED
PHP_JSON_RESPONSE: NOT_ATTEMPTED_THIS_PHASE; PRIOR_HTTP_503_STT_STARTUP_FAILED
END_TO_END_SYNTHETIC: BLOCKED_BY_PREFLIGHT

STT_LATENCY: NOT_MEASURED
TOTAL_LATENCY: NOT_MEASURED
PEAK_MEMORY: NOT_MEASURED_THIS_PHASE

EXTERNAL_AUDIO_TRANSMISSION: NO
CLINICAL_PERSISTENCE: NO
GEMINI_CALLS: 0

BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
ENV_MODIFIED: NO

TEMP_FILES_REMAINING: 0
LISTENERS_REMAINING: 0
RESULT: BLOCKED_RESOURCE_PREFLIGHT
NEXT_ACTION: OWNER_FREES_MEMORY_OR_RESTARTS_WINDOWS; REPEAT_PREFLIGHT_BEFORE_ONE_INFERENCE; NO_CHROME_TEST_YET
```

## Addendum VOICE-STT-02I — 2026-10-13

### Resultado de la única inferencia autorizada

El preflight fue satisfactorio frente a los fallos anteriores: 1.97 GB de RAM física libre, 10.41 GB de margen de compromiso y aproximadamente 73.9% comprometido. No había `runtime-state.json`, workers propios ni listeners 8766/8767.

Se inició una sola ejecución con el modelo local `faster-whisper-small`, CPU INT8 y un WebM/Opus sintético previamente validado. El recorrido PHP → servicio Python → worker spawn → PyAV → Whisper → PHP finalizó con HTTP 200, JSON válido y texto no vacío. La latencia informada por Whisper fue 10.067 s y el tiempo extremo a extremo fue 13.546 s. El pico muestreado de los procesos PHP/Python durante la solicitud fue 529.4 MB de working set; no representa una medición de memoria privada exclusiva del modelo.

La detención normal dejó un worker spawn huérfano. Se identificó inequívocamente por su PID, ejecutable Python 3.13, argumento `multiprocessing.spawn` y hora de creación coincidente con la prueba. Se detuvo exclusivamente ese proceso propio. La verificación final confirmó cero workers Python del prototipo, cero listeners, cero temporales y ausencia de `runtime-state.json`.

```yaml
PHASE: VOICE-STT-02I
AVAILABLE_RAM: 1.97_GB_PHYSICAL_FREE
COMMITTED_MEMORY: APPROX_73.9_PERCENT_USED
COMMIT_HEADROOM: 10.41_GB
RESOURCE_PREFLIGHT: PASS

WORKER_STARTED: PASS
WHISPER_MODEL_LOADED: PASS
WEBM_OPUS_VALIDATED: PASS
WHISPER_INFERENCE_COUNT: 1
WHISPER_INFERENCE_RESULT: PASS
TRANSCRIBED_TEXT_NONEMPTY: PASS
PHP_HTTP_STATUS: 200
PHP_JSON_VALID: PASS
END_TO_END_SYNTHETIC: PASS

STT_LATENCY: 10.067_S
TOTAL_LATENCY: 13.546_S
PEAK_MEMORY: 529.4_MB_PHP_PYTHON_WORKING_SET_SAMPLED

EXTERNAL_AUDIO_TRANSMISSION: NO
CLINICAL_PERSISTENCE: NO
GEMINI_CALLS: 0

BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
ENV_MODIFIED: NO
CODE_MODIFIED: NO

TEMP_FILES_REMAINING: 0
LISTENERS_REMAINING: 0
RESULT: PASS
REMAINING_BLOCKERS: REAL_CHROME_ACCEPTANCE_NOT_VERIFIED
NEXT_ACTION: STOP_AND_WAIT_FOR_AUTHORIZATION_BEFORE_ANY_MANUAL_CHROME_TEST
```

## Addendum VOICE-STT-02J — Preparación manual — 2026-10-13

El preflight permitió reservar el prototipo para una única prueba manual: 1.65 GB de RAM física libre, 10.01 GB de margen de compromiso y aproximadamente 74.9% comprometido. No había runtime-state, workers propios ni listeners activos antes del arranque. El margen de compromiso es comparable al de VOICE-STT-02I, que completó una inferencia real.

Se inició el procedimiento existente `start-prototype.ps1` sin modificar código. La página raíz y `/health` respondieron HTTP 200. El servicio está limitado a loopback: PHP en `127.0.0.1:8767` y Python en `127.0.0.1:8766`. No se envió audio, no se cargó Whisper y no se ejecutó ninguna inferencia adicional. El servidor queda encendido exclusivamente para la prueba manual del propietario.

```yaml
PHASE: VOICE-STT-02J
RESOURCE_PREFLIGHT: PASS
CHROME_MICROPHONE: PENDING_OWNER_TEST
REAL_AUDIO_CAPTURE: PENDING_OWNER_TEST
REAL_WEBM_TRANSCRIPTION: PENDING_OWNER_TEST
TRANSCRIBED_TEXT: PENDING_OWNER_TEST
TEXT_EDITABLE: PENDING_OWNER_TEST
MANUAL_SEND: PENDING_OWNER_TEST
STT_LATENCY: NOT_MEASURED
TOTAL_LATENCY: NOT_MEASURED
ERROR_CODE: NONE_OBSERVED_BEFORE_AUDIO
CLINICAL_PERSISTENCE: NO
EXTERNAL_AUDIO_TRANSMISSION: NO
TEMP_FILES_REMAINING: 0
LISTENERS_REMAINING: 2_EXPECTED_LOOPBACK_LISTENERS_WHILE_RESERVED
BROWSER_ACCEPTANCE: PENDING_OWNER_ACTION
SERVER_STATUS: RUNNING
ROOT_URL: http://127.0.0.1:8767/
HEALTH_URL: http://127.0.0.1:8767/health
NEXT_ACTION: OWNER_PERFORMS_ONE_FICTITIOUS_RECORDING; THEN_REPORT_RESULT_FOR_CLEANUP
```

## Addendum VOICE-STT-03 — 2026-10-13

### Implementación focal

Se actualizó únicamente el prototipo aislado para usar el mismo `textarea` existente con ventanas completas de `MediaRecorder` de 4 segundos. Cada ventana se cierra como un WebM/Opus decodificable antes de enviarse; los chunks de 250 ms solo alimentan la ventana activa y no se envían como audios autónomos. El navegador procesa una sola ventana a la vez y limita a dos los segmentos pendientes. El envío sigue siendo manual y no existe persistencia clínica.

Se incorporaron estados visibles de escucha, transcripción, texto parcial, finalización, texto listo y error recuperable. La actualización conserva ediciones manuales posteriores al primer texto reconocido y aplica deduplicación de solapamientos de hasta 12 palabras. El botón de envío permanece bloqueado mientras hay captura o transcripción pendiente.

### Verificación sintética real

Con el servidor loopback existente se enviaron exactamente dos WebM/Opus sintéticos, de forma secuencial, a través de PHP → servicio Python → único trabajador `faster-whisper-small` CPU INT8 → PHP. No se usó mock para las respuestas de Whisper en esta prueba. No se imprimió ni almacenó el contenido textual de los segmentos como evidencia; únicamente se verificó que la respuesta tuviera texto no vacío y su longitud.

```yaml
PHASE: VOICE-STT-03
PROGRESSIVE_TRANSCRIPTION_FEASIBLE: PARTIAL_NOT_REAL_TIME_ON_CURRENT_CPU
SEGMENTATION_METHOD: COMPLETE_WEBM_OPUS_WINDOWS_4S; SEQUENTIAL_QUEUE; MAX_PENDING_2
FIRST_TEXT_BEFORE_STOP: NOT_VERIFIED_IN_CHROME; REAL_HTTP_SEGMENTS_RETURNED_TEXT
FIRST_TEXT_LATENCY: NOT_MEASURED_FROM_BROWSER_CAPTURE
SEGMENT_LATENCY: SEGMENT_1_7.925S_ROUNDTRIP; SEGMENT_2_10.052S_ROUNDTRIP
ACCUMULATED_DELAY: EXCEEDS_4S_WINDOW_BUDGET; BACKLOG_GUARD_ACTIVATES_AT_2_PENDING
TEXTAREA_PROGRESSIVE_UPDATE: IMPLEMENTED; BROWSER_ACCEPTANCE_PENDING
DUPLICATE_WORD_PROTECTION: IMPLEMENTED_SOURCE_REVIEW; NOT_YET_ACCEPTED_BY_OWNER_BROWSER
MANUAL_EDIT_PROTECTION: IMPLEMENTED_SOURCE_REVIEW; NOT_YET_ACCEPTED_BY_OWNER_BROWSER
FINAL_SEGMENT_PROCESSED: IMPLEMENTED; NOT_YET_VERIFIED_IN_CHROME
MAX_CONCURRENT_INFERENCE: 1
PEAK_RAM: NOT_CAPTURED_DURING_REQUEST; POST_LOAD_WORKING_SET_APPROX_535MB_FOR_SERVICE_PHP_WORKER
CPU_USAGE: NOT_CAPTURED_DURING_REQUEST; LATENCY_USED_AS_THROUGHPUT_INDICATOR

OFFLINE_ONLY: YES
CLINICAL_PERSISTENCE: NO
EXTERNAL_AUDIO_TRANSMISSION: NO
GEMINI_CALLS: 0

SYNTHETIC_TESTS: PASS_REAL_HTTP_2_SEGMENTS; PASS_PHP_RENDER_200; PASS_PHP_JS_SYNTAX; PASS_NO_TEMP_FILES; PASS_SINGLE_WORKER
REAL_CHROME_ACCEPTANCE: NOT_VERIFIED
FILES_MODIFIED:
  - tests/voice-stt-02/bridge.php
  - tests/voice-stt-02/VOICE_STT_02_REPORT.md
REMAINING_BLOCKERS: CURRENT_CPU_CANNOT_SUSTAIN_4S_PROGRESSIVE_WINDOWS; CHROME_MANUAL_ACCEPTANCE_PENDING; CPU_PEAK_NOT_INSTRUMENTED
NEXT_ACTION: DO_NOT_DECLARE_NATURAL_REAL_TIME; OWNER_MAY_PERFORM_ONE_MANUAL_CHROME_CHECK, THEN EVALUATE LONGER_WINDOWS_OR_FASTER_LOCAL_INFERENCE
```
