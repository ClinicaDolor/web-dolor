# Fase 4.7 — Diagnóstico del HTTP 500 y regresión runtime

**Fecha:** 2026-10-12  
**Alcance:** sólo arnés/infraestructura de prueba y base sintética allowlisted.  
**Base real protegida:** `bd_clinica`, no utilizada para escrituras ni peticiones HTTP.

## Causa del HTTP 500

La primera ejecución de Fase 4.6 utilizó el arranque en segundo plano de `tests/p05/start-server.ps1` sobre el puerto fijo 8087. El script devolvía un PID, pero no comprobaba que ese PID fuese realmente el proceso que había abierto el listener. En la ejecución observada, el proceso reportado no abrió el puerto esperado; las peticiones del arnés no quedaron vinculadas de forma demostrable a la instancia sintética iniciada. El resultado fueron respuestas 500 no atribuibles al controlador clínico.

La evidencia de configuración correcta se obtuvo con una instancia foreground aislada: las huellas de `APP_KEY` del proceso y del entorno coincidieron, y la huella de `DB_DATABASE` coincidió con `clinica_dolor_demo_20261010_final`. En esa instancia, el mismo flujo pasó íntegramente.

```yaml
HTTP_500_ROOT_CAUSE: START_SERVER_HARNESS_DID_NOT_VERIFY_LISTENER_OWNERSHIP
HTTP_500_CLASSIFICATION: TEST_HARNESS_DEFECT
HTTP_500_RESOLVED: PASS_FOR_VALIDATED_FOREGROUND_INSTANCE
```

No se identificó una excepción en el producto clínico. El 500 inicial no se reutiliza como evidencia contra `PatientInterviewController` ni contra `AntecedentesNoPatologicosModel`.

## Corrección focal aplicada

Se modificó únicamente `tests/p05/start-server.ps1` para:

- Esperar el listener local después de iniciar PHP.
- Verificar que el proceso propietario del puerto sea el PID recién creado.
- Fallar cerrado si el puerto pertenece a otra instancia.
- Fallar si el proceso no abre el listener.
- Dejar de imprimir la `APP_KEY` en la salida del script.

Se amplió `tests/patient-interview/chat-http-isolation.php` para probar CSRF ausente e inválido y para fijar siempre `Content-Type` en peticiones POST. Se añadió sólo como arnés de prueba; no se modificó código clínico.

La huella diagnóstica temporal se eliminó después de verificar el entorno.

## Regresión runtime ejecutada

La prueba usó exclusivamente `clinica_dolor_demo_20261010_final`, con pacientes sintéticos 9301 y 9302 en clínicas distintas. El arnés preparó P1–P4 con marcadores sintéticos y P5 vacía, tomó el estado previo y restauró automáticamente el estado original en un bloque `finally`.

Resultado runtime:

- GET de entrevista: HTTP 200.
- GET no produjo escrituras: el conteo de filas permaneció igual.
- Confirmación exclusiva de P5: HTTP 200.
- P5 quedó persistida en su propia fila.
- P1–P4 conservaron identificadores y valores sintéticos exactamente.
- Conteo final durante la prueba: 5 filas, sin duplicados.
- Respuesta esperada obsoleta: HTTP 409.
- CSRF ausente: HTTP 403.
- CSRF inválido: HTTP 403.
- Paciente no autorizado: HTTP 403.
- Restauración automática del fixture sintético: completada.

El preflight posterior confirmó:

- Base efectiva: `clinica_dolor_demo_20261010_final`.
- Catálogo: 7 temas y 25 preguntas.
- Pacientes sintéticos en clínicas distintas.
- `response_count` final: 0.

## Resultado por escenario

```yaml
SYNTHETIC_RUNTIME_DATABASE: PASS
SYNTHETIC_LOGIN: NOT_VERIFIED_AS_BROWSER_LOGIN — JWT sintético controlado por arnés
SYNTHETIC_CHAT_HTTP: PASS
CHAT_READ_NO_WRITES: PASS
CHAT_NAVIGATION_NO_WRITES: PASS_STATIC — navegación visual no ejecutada en navegador
CHAT_CANCEL_NO_WRITES: PASS_STATIC — cancelación visual no ejecutada en navegador
CHAT_CONFIRM_P5_ONLY: PASS
P1_P4_VALUES_PRESERVED: PASS
DEPENDENT_ANSWERS_PRESERVED: PASS — P5 input no activa limpieza de dependencias
CSRF_RUNTIME: PASS
CONCURRENCY_409_RUNTIME: PASS
CROSS_PATIENT_AUTHORIZATION: PASS
CROSS_CLINIC_AUTHORIZATION: PASS — sesión de clínica distinta rechazada
DUPLICATE_PREVENTION: PASS
SYNTHETIC_FIXTURE_CLEANUP: PASS
BD_CLINICA_MODIFIED: NO
LEGACY_M03_MODIFIED: NO
OTHER_CLINICAL_MODULES_MODIFIED: NO
SYNTHETIC_INTEGRITY_GATE: PASS
BROWSER_E2E: NOT_VERIFIED
HISTORICAL_P2_P3_ROOT_CAUSE: NOT_VERIFIED
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

## Evidencia preservada

El respaldo focal del paciente 310 permaneció sin modificaciones:

`C:\Users\silvi\AppData\Local\Temp\clinica-demo-m03-310-20261012-140310.sql`

SHA-256 verificado: `4621bc1da84f3d8eaaac54e7cd26d629054914b2b8e5d157038fd02212de6b3b`.

No se consultó ni alteró el formulario tradicional en navegador. No se restauraron P2/P3 ni P5 del paciente 310. No se habilitó Gemini ni se enviaron datos a proveedores externos.

## Limitaciones y bloqueo restante

- La prueba confirma el aislamiento runtime de P5 en la instancia sintética válida.
- No demuestra navegación visual, cancelación real de navegador ni login manual.
- La causa histórica de los cambios de P2/P3 del paciente 310 continúa sin verificarse por ausencia de logs de aquella sesión.
- El paciente 310 permanece congelado.
- No se autoriza repetir la captura en `bd_clinica` ni liberar el chat para expedientes reales.
