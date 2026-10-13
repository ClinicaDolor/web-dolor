# Fase 4.6 — Aislamiento de escritura del chat independiente

**Fecha:** 2026-10-12  
**Modo:** diagnóstico y regresión sintética; sin restauración de `bd_clinica`.  
**Base autorizada para pruebas:** `clinica_dolor_demo_20261010_final`.

## Riesgo identificado

La captura histórica mostró diferencias reales en M03/P2 y M03/P3 cuando el alcance autorizado era P5. La auditoría 17 confirmó que la interfaz secuencial envía una petición individual por cada pregunta confirmada y avanza automáticamente. Esto demuestra un mecanismo que permite guardar preguntas anteriores durante una navegación completa, pero no demuestra qué petición concreta produjo los cambios históricos.

`HISTORICAL_ROOT_CAUSE: NOT_VERIFIED`

No se atribuyen los cambios a una causa temporal concreta por falta de logs históricos de solicitudes y SQL.

## Contrato revisado

La revisión estática confirma:

- La carga inicial usa `LEFT JOIN` y no llama al inicializador heredado.
- La confirmación requiere valor y confirmación explícita del navegador.
- El payload contiene un solo `idPregunta`, un solo `detalle` y `expectedRespuesta`.
- El controlador valida sesión, paciente, clínica implícita, CSRF y tema M03.
- El modelo recupera una sola fila por paciente/pregunta y conserva el control de concurrencia HTTP 409.
- Para `input`, el SQL actualiza una sola fila por identificador, paciente y tema.
- La rama que limpia dependencias sólo se activa para `select=No`; P5 es `input`.
- Cancelar la confirmación no ejecuta `fetch`.
- El avance visual ocurre sólo después de una respuesta HTTP exitosa.

El diseño secuencial implica que una sesión que confirma P1–P4 para llegar a P5 puede guardar esas preguntas. No existe una restricción técnica actual que convierta una prueba “P5 solamente” en una allowlist de P5.

## Pruebas ejecutadas

### Contratos sin escritura

- `tests/patient-interview/chat-separation-contract.php`: PASS, 15 aserciones.
- `tests/patient-interview/m03-csrf-contract.php`: PASS, 6 aserciones.
- `tests/p05/contract-check.mjs`: PASS.
- `tests/p05/navigation-contract.mjs`: PASS sintético estático.
- `tests/p05/flow-speed-contract.mjs`: PASS sintético estático.
- Preflight de base: PASS; base activa allowlisted, 7 temas, 25 preguntas, pacientes sintéticos 9301/9302 en clínicas distintas.

### Regresión HTTP sintética

Se añadió el arnés focal `tests/patient-interview/chat-http-isolation.php`. Su preparación se limita a la base sintética, crea valores marcadores no clínicos para P1–P5 y restaura automáticamente el estado previo en un `finally`.

El servidor aislado respondió HTTP 500 en GET y POST del chat, incluso cargando la clave desde el `.env` local. Por ese fallo del arnés no se considera válida la prueba runtime de captura, CSRF, stale 409 o aislamiento cruzado. La consulta posterior confirmó que el estado sintético fue restaurado y no quedó escritura persistente.

No se ejecutó ningún endpoint sobre `bd_clinica` ni se abrió el formulario tradicional.

## Resultado actual

```yaml
CHAT_NAVIGATION_NO_WRITES: PASS_STATIC / HTTP_NOT_VERIFIED
CHAT_CANCEL_NO_WRITES: PASS_STATIC
CHAT_CONFIRM_SINGLE_QUESTION: PASS_STATIC / HTTP_NOT_VERIFIED
PREVIOUS_ANSWERS_PRESERVED: NOT_VERIFIED_RUNTIME
DEPENDENT_ANSWERS_PROTECTED: PASS_STATIC_FOR_P5_INPUT / HTTP_NOT_VERIFIED
HTTP_PAYLOAD_SCOPE: PASS_STATIC_SINGLE_QUESTION
SQL_WRITE_SCOPE: PASS_STATIC_SINGLE_ROW
CSRF_PROTECTION: PASS_STATIC_CONTRACT / HTTP_NOT_VERIFIED_RUNTIME
PATIENT_AUTHORIZATION: PASS_STATIC_CODE / HTTP_NOT_VERIFIED_RUNTIME
CONCURRENCY_409: PASS_STATIC_CODE / HTTP_NOT_VERIFIED_RUNTIME
SHARED_PERSISTENCE: PASS_CODE_REVIEW
LEGACY_M03_MODIFIED: NO
OTHER_CLINICAL_MODULES_MODIFIED: NO
BD_CLINICA_MODIFIED: NO
HISTORICAL_ROOT_CAUSE: NOT_VERIFIED
SYNTHETIC_REGRESSION: PARTIAL — estática PASS, HTTP bloqueada por 500 del arnés
DEMO_RESTORATION_PLAN: NO EJECUTAR; conservar respaldo focal y detectar cambios antes de restaurar
DEMO_RESTORATION_EXECUTED: NO
SAFE_FOR_CONTROLLED_RETEST: NO — requiere resolver primero el 500 del entorno sintético y aislar P5
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

## Cambios realizados

No se modificaron controladores, modelos clínicos, formulario tradicional, JavaScript de producción, rutas, catálogos, `.env`, esquema, Gemini ni P05–P11. Sólo se agregó el arnés de prueba focal `tests/patient-interview/chat-http-isolation.php`; no se aplicó una corrección funcional porque la prueba HTTP no fue válida.

## Corrección mínima propuesta — pendiente de autorización

Antes de otra prueba controlada debe resolverse el HTTP 500 del servidor sintético y añadirse una prueba runtime que capture sólo P5 y compare todas las filas. Si el requisito operativo es permitir una prueba exclusiva de P5, la corrección mínima debe estar en el componente independiente: impedir que una sesión de prueba pueda confirmar preguntas distintas de la allowlist autorizada, sin tocar el modelo compartido ni el formulario tradicional.

La validación debe existir también en el servidor; una protección sólo en JavaScript no es suficiente. Si se requiere cambiar `AntecedentesNoPatologicosModel`, se deberá presentar primero una propuesta separada por tratarse de un componente clínico compartido.

## Restauración demo

No se ejecutó restauración. El respaldo de 25 filas del paciente 310 sigue preservado fuera del repositorio con su SHA-256 original. Cualquier restauración futura deberá:

1. Detectar cambios posteriores comparando identificadores y valores.
2. Requerir autorización expresa.
3. Restringirse a P2/P3 si así se autoriza, sin tocar P1, P4, P5 ni otros pacientes.
4. Ejecutarse transaccionalmente y verificarse con consultas de solo lectura.

## Riesgos pendientes

- P2/P3 de `bd_clinica` continúan diferentes del respaldo y no fueron corregidas.
- La causa histórica no puede reconstruirse sin logs.
- La prueba HTTP sintética específica del chat sigue bloqueada por HTTP 500.
- No se autoriza repetir capturas, restaurar el expediente ni habilitar pacientes reales hasta resolver ambos puntos.
