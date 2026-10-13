# Fase 4 — Auditoría global de regresión y aislamiento

Fecha de auditoría: 11 de octubre de 2026  
Proyecto: Clínica del Dolor  
Alcance: lectura, contratos estáticos y pruebas sintéticas seguras.  
Resultado: no se modificó código de aplicación ni de pruebas durante esta auditoría.

## 1. Alcance y límites

La revisión se realizó sobre el estado local existente, preservando los cambios paralelos P05–P11. No se hicieron commits, push ni despliegues. No se modificaron `.env`, `bd_clinica`, esquemas, expedientes reales ni Gemini.

La precondición sintética de solo lectura confirmó la base `clinica_dolor_demo_20261010_final`, los pacientes sintéticos 9301/9302 en clínicas distintas, los accesos sintéticos, las tablas mínimas y los nueve módulos. La salida del preflight indicó `read_only: true` y no ejecutó escrituras.

No se declara aceptación visual: el navegador automatizado disponible no permitió obtener una sesión local reproducible con inspección de consola y viewport. La geometría renderizada, el audio audible y la aceptación manual permanecen pendientes.

## 2. Inventario de cambios compartidos revisados

| Superficie | Evidencia revisada | Riesgo de regresión | Resultado |
|---|---|---|---|
| Rutas | `app/Config/routes.php` | Las dos rutas de entrevista podrían interceptar rutas clínicas | Las rutas nuevas están limitadas a `/historia-clinica/entrevista` y `/historia-clinica/entrevista/capturar`; no se encontró dependencia del chat en rutas M01–M09 |
| Controlador de entrevista | `app/Controllers/PatientInterviewController.php` | Bypass de sesión, paciente o CSRF | Usa middleware de paciente, `assertPatientAccess` y CSRF M03; contrato estático PASS |
| Modelo M03 | `app/Models/AntecedentesNoPatologicosModel.php` | Catálogo duplicado, siembra al leer o duplicados al guardar | La lectura usa `LEFT JOIN`; la captura delega al modelo existente; E2E sintético previo recuperó la respuesta tras recarga |
| Formulario tradicional M03 | `app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php` | Mezcla del chat con el formulario original | El selector conversacional y sus ramas fueron retirados; contrato CSRF M03 PASS |
| Portal paciente | `app/views/paciente/index.php` | La nueva entrada podría sustituir o alterar módulos | La entrevista aparece como opción independiente y los enlaces M01–M09 permanecen |
| Vista de entrevista | `app/views/paciente/entrevista.php` | Exposición de datos o dependencia de Gemini | Piloto determinista, catálogo M03, CSRF y confirmación previa; no contiene API key ni llamada a proveedor |
| Persistencia compartida | `pac_respuestas_paciente_modulo_3` | Historia paralela o escritura indirecta | La captura usa la tabla clínica existente; la limpieza sintética dejó `response_count = 0` |

## 3. Matriz individual M01–M09

`PASS` significa que no se demostró una regresión en el alcance probado; `PARTIAL` significa evidencia estructural o de una prueba compartida sin E2E completo; `NOT_VERIFIED` significa que no existe evidencia segura suficiente para cerrar ese punto.

| Módulo | Acceso y autorización | Catálogo/respuestas originales | Navegación/validación | Persistencia y duplicados | Dependencia del chat | Resultado |
|---|---|---|---|---|---|---|
| M01 — Identificación | Rutas y portal tradicionales conservados; E2E autenticado no ejecutado en esta fase | Vista original revisada; sin contrato específico de Fase 4 | Lint/lectura estática; navegador pendiente | No se hizo escritura | No se encontró referencia a entrevista | PARTIAL |
| M02 — Antecedentes familiares | Contratos P04 y control de acceso del código revisados; sesión cruzada completa pendiente | Catálogo y modelo existentes; mock de voz PASS | Contrato de paridad PASS; navegador pendiente | P04 tiene verificación sintética previa, no repetida con escritura en esta auditoría | Sin dependencia detectada | PARTIAL |
| M03 — Antecedentes no patológicos | E2E sintético previo autenticado PASS; paciente cruzado y CSRF ausente rechazados | Cinco preguntas del catálogo M03; lectura sin siembra en entrevista | M03 CSRF contract PASS; navegación sintética PASS; visual pendiente | Captura confirmada, recarga y limpieza sintética PASS; concurrencia obsoleta probada previamente | Formulario tradicional sin selector ni ramas de chat | PASS en alcance sintético; visual pendiente |
| M04 — Antecedentes quirúrgicos | Contrato P06 exige autorización ligada a paciente; E2E visual y sesión cruzada pendiente | Consulta parametrizada y columnas limitadas verificadas por contrato | Contrato P06 no llega a PASS por falso positivo descrito abajo; mock de voz PASS | Prueba transaccional P06 reporta PASS sin persistencia; no se repitió escritura | Sin dependencia detectada | PARTIAL |
| M05 — Antecedentes patológicos | Código y vista revisados; no existe contrato seguro completo en esta auditoría | Lectura estática; prueba funcional completa pendiente | Navegador y validación completa no verificados | No se ejecutaron escrituras | Sin dependencia detectada | NOT_VERIFIED |
| M06 — Medicación actual | Contrato P08 PASS; aislamiento sintético documentado | Catálogo/modelo y estados revisados estáticamente | Contrato P08 PASS; navegador pendiente | E2E de escritura no repetido por restricción de solo lectura | Sin dependencia detectada | PARTIAL |
| M07 — Medicación para dolor | Contrato P09 PASS; pruebas de autorización de sesión completa pendientes | Catálogo y respuestas revisados estáticamente | Contrato P09 PASS; navegador pendiente | Pruebas CRUD históricas existen, no se ejecutaron en esta fase | Sin dependencia detectada | PARTIAL |
| M08 — Procedimientos/tratamientos | Contrato P10 PASS; autorización cruzada de navegador pendiente | Catálogo y dependencias revisados estáticamente | Contrato P10 PASS; navegador pendiente | No se hicieron escrituras; la documentación exige limpieza aislada si se ejecuta | Sin dependencia detectada | PARTIAL |
| M09 — Evaluación del dolor | Vista, rutas y recursos de imagen revisados; no existe E2E seguro de navegador disponible | Contrato específico completo no verificado | Consola, canvas y navegación no verificados | No se hicieron escrituras | Sin dependencia detectada | NOT_VERIFIED |

La matriz no convierte filas inicializadas vacías, porcentajes ni conteos de preguntas en prueba de contenido clínico. El estado de cada módulo debe seguir distinguiendo registro real, ausencia, incompletitud y estado de formulario.

## 4. Pruebas ejecutadas y resultados

Pruebas no destructivas ejecutadas durante esta auditoría:

- `node tests/patient-module-parity-gate.mjs`: PASS; paridad estática P04/P05/P06. El propio contrato indica que geometría y consola requieren navegador.
- `php tests/p04/verify-isolation.php --database=clinica_dolor_demo_20261010_final --json`: PASS; aislamiento confirmado, solo lectura.
- `php tests/p06/verify-isolation.php --database=clinica_dolor_demo_20261010_final --json`: PASS; transacción de contrato y ausencia de escritura persistente reportadas por el arnés.
- `node tests/p04/audio-mock.mjs`, `node tests/p06/audio-mock.mjs`, `node tests/p09/audio-mock.mjs` y `node tests/p10/audio-mock.mjs`: PASS contractual; reproducción audible real NO VERIFICADA.
- `node tests/p05/contract-check.mjs`, `node tests/p05/navigation-contract.mjs`, `node tests/p05/flow-speed-contract.mjs`: PASS; la navegación/persistencia real no queda demostrada por los contratos estáticos.
- `node tests/p07/contract-check.mjs`, `node tests/p08/contract-check.mjs`, `node tests/p09/contract-check.mjs`, `node tests/p10/contract-check.mjs`: PASS estático.
- `php tests/patient-interview/chat-separation-contract.php`: PASS, 15 aserciones, sin escrituras.
- `php tests/patient-interview/m03-csrf-contract.php`: PASS, 6 aserciones, sin escrituras.
- `php tests/clinical-summary/coverage-contract.php` y `php tests/clinical-summary/narrative-contract.php`: PASS, 11 y 24 aserciones respectivamente.
- Lint PHP de rutas, controlador de entrevista, modelo M03, vistas M03 tradicional/entrevista y portal: PASS.

Evidencia sintética previa, conservada como antecedente y no repetida con nuevas escrituras: login de paciente sintético HTTP 200, entrevista M03 HTTP 200 con cinco preguntas, captura confirmada HTTP 200, captura sin CSRF HTTP 403, formulario tradicional HTTP 200 sin selector, recarga con respuesta recuperada, limpieza de 25 filas temporales y verificación final con cero respuestas temporales.

## 5. Chat independiente y aislamiento

Resultado: `CHAT_UI_ISOLATION: PASS`.

La entrada `/historia-clinica/entrevista` está fuera de los formularios tradicionales. El formulario M03 conserva sus preguntas, catálogo, respuestas, navegación, CSRF y guardado sin selector conversacional. La vista nueva declara modo determinista, no llama a Gemini y usa el mismo catálogo M03 y la misma persistencia clínica.

Resultado: `SHARED_PERSISTENCE: PASS` en el alcance sintético probado. La lectura no crea filas; la captura confirmada reutiliza el modelo existente y la respuesta se recuperó después de recargar. No se creó tabla, catálogo ni historia paralela.

La conversación no se ha extendido a M01–M09. No se ejecutó ninguna integración Gemini ni se llamó a un cuestionario generativo.

## 6. Defecto detectado en un contrato

`node tests/p06/contract-check.mjs` falla con `El CSS P06 introduce !important innecesario.` La causa demostrada es el alcance del propio contrato: toma todo el CSS desde el marcador P06 hasta el final del archivo, aunque el bloque P06 termina explícitamente en `END P06-COMPLETE-01`. Las coincidencias detectadas pertenecen a bloques posteriores de P08 y del resumen clínico, no al bloque P06.

Esto es un defecto del contrato de prueba, no una regresión funcional demostrada de P06. No se modificó el contrato ni el CSS porque la instrucción de esta fase prohíbe corregir automáticamente y exige autorización para cualquier corrección.

Riesgo conocido, documentado en P05: algunas lecturas GET históricas del formulario tradicional pueden inicializar filas vacías. No se atribuye a la separación del chat y no se corrigió en esta auditoría. Debe resolverse o aceptarse explícitamente antes de usar la ausencia de filas como indicador clínico.

## 7. Autorización, CSRF, concurrencia e integridad

- `PATIENT_AUTHORIZATION: PASS` para M03/chat sintético y las guardas estructurales revisadas; autorización E2E completa por todos los módulos: NOT_VERIFIED.
- `CSRF_PROTECTION: PASS` para el endpoint M03 tradicional y el endpoint de entrevista: ausencia de token rechazada con HTTP 403; contrato estático PASS.
- `CONCURRENCY_PROTECTION: PASS` para M03, con respuesta obsoleta HTTP 409 en la prueba sintética previa; cobertura equivalente de todos los módulos: NOT_VERIFIED.
- `CLINICAL_DATA_INTEGRITY: PASS` para la comprobación sintética ejecutada: base allowlisted, clínicas sintéticas separadas y limpieza final sin respuestas temporales. No implica una auditoría de datos reales.

No se registraron payloads clínicos, credenciales, tokens ni claves como evidencia.

## 8. Navegador y pruebas manuales pendientes

`BROWSER_MANUAL_TESTS_PENDING: YES`.

Quedan pendientes, como mínimo, sesión manual sintética y capturas reales para M01–M09, overflow y legibilidad en los viewports definidos por las fases previas, consola JavaScript, controles de voz audibles, navegación completa y pruebas de aislamiento cruzado en navegador. La inspección de HTML y los mocks no se consideran equivalentes.

## 9. Regresiones, bloqueos y correcciones propuestas

`REGRESSIONS_FOUND: P06_CONTRACT_FALSE_POSITIVE; KNOWN_M03_EMPTY_ROW_ON_LEGACY_GET`.

No se demostró una regresión clínica introducida por la separación del chat. El falso positivo P06 corresponde al contrato estático; el efecto de filas vacías M03 es un riesgo heredado que requiere decisión propia.

`BLOCKING_ISSUES: FULL_M01_M09_SAFE_E2E_NOT_VERIFIED; BROWSER_MANUAL_ACCEPTANCE_PENDING; P06_CONTRACT_SCOPE_REQUIRES_AUTHORIZED_FIX`.

Propuestas mínimas, no aplicadas:

1. Ajustar `tests/p06/contract-check.mjs` para limitar la búsqueda de `!important` al bloque entre `P06-COMPLETE-01` y `END P06-COMPLETE-01`.
2. Diseñar una prueba de lectura que confirme que el GET tradicional M03 no siembra filas, o documentar formalmente el comportamiento heredado antes de usar conteos como evidencia de contenido.
3. Completar aceptación manual de navegador y E2E seguro para cada módulo antes de ampliar la entrevista o cualquier IA clínica.

## 10. Decisión de avance

`PHASE_5_AI_READINESS: NO-GO`.

La separación M03/chat está técnicamente aislada en el alcance sintético, pero no existe evidencia suficiente para declarar protegida toda la historia M01–M09 frente a regresiones. La siguiente fase sólo debe comenzar después de autorizar correcciones de los contratos, completar navegador y cerrar la matriz funcional por módulo.

`REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED`

M01_LEGACY_REGRESSION: PARTIAL
M02_LEGACY_REGRESSION: PARTIAL
M03_LEGACY_REGRESSION: PASS
M04_LEGACY_REGRESSION: PARTIAL
M05_LEGACY_REGRESSION: NOT_VERIFIED
M06_LEGACY_REGRESSION: PARTIAL
M07_LEGACY_REGRESSION: PARTIAL
M08_LEGACY_REGRESSION: PARTIAL
M09_LEGACY_REGRESSION: NOT_VERIFIED

CHAT_UI_ISOLATION: PASS
SHARED_PERSISTENCE: PASS
PATIENT_AUTHORIZATION: PASS (alcance M03/chat sintético)
CSRF_PROTECTION: PASS (alcance M03/chat sintético)
CONCURRENCY_PROTECTION: PASS (alcance M03 sintético)
CLINICAL_DATA_INTEGRITY: PASS (alcance sintético)

REGRESSIONS_FOUND: P06_CONTRACT_FALSE_POSITIVE; KNOWN_M03_EMPTY_ROW_ON_LEGACY_GET
BLOCKING_ISSUES: FULL_M01_M09_SAFE_E2E_NOT_VERIFIED; BROWSER_MANUAL_ACCEPTANCE_PENDING; P06_CONTRACT_SCOPE_REQUIRES_AUTHORIZED_FIX
BROWSER_MANUAL_TESTS_PENDING: YES

PHASE_5_AI_READINESS: NO-GO
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED

La auditoría se detiene aquí y queda a la espera de autorización para aplicar cualquier corrección.
