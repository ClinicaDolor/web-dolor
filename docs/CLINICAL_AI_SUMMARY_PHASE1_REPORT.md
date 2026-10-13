# Resumen clínico con IA — Fase 1

Estado de esta entrega: auditoría y E2E sintético completados, sin commit, push, despliegue ni habilitación de expedientes reales.

## Arquitectura

El flujo `POST /clinica/paciente/{idPaciente}/clinical-summary` autentica la sesión `clinica`, exige rol `Doctor`, comprueba pertenencia a la clínica mediante `assertPatientAccess()`, valida un token CSRF específico y coordina:

```text
Doctor -> ClinicalSummaryController -> ClinicalSnapshotService (PDO, solo lectura)
                                      -> ClinicalSummaryService
                                      -> GeminiProvider (solo si está habilitado)
                                      -> DeterministicClinicalSummaryService (fallback)
```

Servicios creados:

- `ClinicalSnapshotService`: captura nueve módulos, referencias opacas `Mxx:Rnnn`, estados documentales y fuentes.
- `ClinicalSummaryService`: selecciona proveedor, valida contrato y referencias, y establece fechas en servidor.
- `GeminiProvider`: HTTP controlado a `generativelanguage.googleapis.com`, JSON estructurado, límites y timeout.
- `DeterministicClinicalSummaryService`: resumen local sin tráfico externo.
- `ClinicalSummarySecurity`: dos banderas y CSRF específico.
- `ClinicalSummaryController`: autenticación, autorización y respuesta JSON.

## Datos

Se consultan, sin llamar métodos de presentación con efectos secundarios: `pc_paciente`, `pc_antecedentes_familiares`, `pac_respuestas_paciente_modulo_3`, `pc_antecedentes_quirurgicos`, las tablas de respuestas de M05, `pac_medicacion_actual_modulo_6`, `pac_respuestas_paciente_modulo_7`, las tablas de M08 y `pc_paciente_evaluacion_dolor`.

Los valores `NULL` y vacíos permanecen ausentes o incompletos; no se convierten a “No”. El módulo 9 se consulta directamente y no invoca `cuestionarioModulo9()`. No se usan porcentajes de cumplimiento como prueba de existencia. Identificadores personales innecesarios se excluyen del snapshot enviado al proveedor.

La base sintética `clinica_dolor_demo_20261010_final` está disponible y fue consultada en modo estrictamente lectura. Contiene 2 pacientes, 6 registros de antecedentes familiares y 1 registro de evaluación de dolor; los demás módulos no tienen filas persistidas en esta base. El snapshot devolvió los nueve módulos para ambos pacientes: uno mostró información en M01, M02 y M09; el otro solo en M01. Las ausencias se clasificaron como `absent`, sin convertirlas en negaciones. Una respuesta negativa explícita presente en el fixture se conservó como `No`.

Las fuentes adicionales (notas, recetas, laboratorio y COFEPRIS) quedan fuera de esta fase hasta revisar sus contratos de autorización específicos.

## Seguridad y configuración

Variables documentadas (sin secretos):

```dotenv
CLINICAL_AI_SUMMARY_ENABLED=false
CLINICAL_AI_EXTERNAL_ENABLED=false
CLINICAL_AI_SYNTHETIC_MODE=false
GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.8-flash
GEMINI_ENDPOINT=https://generativelanguage.googleapis.com/v1beta
```

El modo sintético se habilita únicamente mediante configuración del servidor (`CLINICAL_AI_SYNTHETIC_MODE=true`); nunca mediante el navegador o solo por el ID de paciente. El proveedor externo permanece desactivado por defecto. No se almacenan prompts, respuestas ni resúmenes en la base.

## Validación y pruebas

| Control | Resultado |
|---|---|
| Sintaxis PHP de servicios, controlador y rutas | PASS |
| Consultas del snapshot sin escrituras | PASS por inspección estática |
| CSRF específico, rol Doctor y clínica | PASS por código |
| Fallback determinista | PASS por código |
| JSON estructurado/referencias de Gemini | PASS por inspección; conexión real NO VERIFICADO |
| Base sintética `clinica_dolor_demo_20261010_final` | PASS: servidor aislado y solo lectura |
| Prueba real con Gemini | PASS: solicitud HTTPS autorizada con paciente sintético |
| Responsive 375/768/1024/1440 | PASS: Chrome headless con viewports físicos exactos |

La integración no declara producción lista. Antes de transmitir expedientes reales faltan revisión clínica, legal, contractual y de tratamiento de datos, además de prueba aislada contra una base sintética autorizada.

## Auditoría de cierre

### Ejemplo sintético redactado

Para el paciente sintético con información disponible, el resultado fue `mode=deterministic`, nueve secciones, tres módulos con registros y referencias opacas como `M02:R001`. No se incluyeron nombre, CURP, domicilio, teléfono, correo ni el identificador interno en el contexto externo.

### Tiempos medidos

Medición local sobre la base sintética, una ejecución: snapshot **5.679 ms**, resumen determinista **0.691 ms**, total **6.370 ms**. La llamada real Gemini integrada tardó **7691 ms** desde el cliente local hasta la respuesta HTTP completa. El endpoint oficial `countTokens` midió **894 tokens de entrada** para el prompt y snapshot sintético utilizados. El proveedor actual no expone `usageMetadata`, por lo que los tokens de salida y el costo exacto no están disponibles; no se inventa una estimación de costo.

En HTTP autenticado se midió una generación determinista completa en **53.068 ms** desde el cliente local; incluye autenticación ya establecida, recuperación, resumen y serialización JSON. No representa latencia Gemini.

### Matriz de pruebas

| Prueba | Resultado | Evidencia |
|---|---|---|
| Estado Git y conservación de cambios concurrentes | PASS | `git status --short --branch`; cambios P05–P11 preservados |
| Sintaxis PHP, rutas y diff limpio | PASS | `php -l` y `git diff --check` |
| Base sintética presente | PASS | conexión solo a `clinica_dolor_demo_20261010_final` |
| Recuperación de los nueve módulos | PASS | snapshot de los dos pacientes sintéticos |
| Vacíos y porcentajes sin falso negativo | PASS | filas reales; estados `absent`/`documented` |
| Negación explícita preservada | PASS | aserción sintética sobre valor `No` |
| Escrituras durante snapshot | PASS por inspección | solo `SELECT`; no se llaman inicializadores |
| Sin sesión | PASS | HTTP 302 hacia acceso |
| Gemini sin API key | PASS | `GEMINI_NOT_CONFIGURED`, sin llamada |
| SSRF / endpoint no permitido | PASS | `GEMINI_ENDPOINT_NOT_ALLOWED` |
| Referencia inexistente o vacía | PASS | validación rechaza mock sintético |
| Fallback determinista | PASS | nueve secciones, modo `deterministic` |
| Mock Gemini completo con respuesta válida | PASS | contrato estructurado, referencias y fallback verificados en mocks |
| Médico autorizado, generación | PASS | HTTP 200, `ok=true`, modo determinista, 9 secciones |
| Médico autorizado, regeneración | PASS | segunda petición HTTP 200, `ok=true` |
| Médico de otra clínica | PASS | HTTP 403 sobre paciente sintético de clínica distinta |
| Rol Paciente | PASS | HTTP 302 hacia `/clinica/acceso`; no se entrega resumen |
| Sin sesión | PASS | HTTP 302 hacia acceso |
| CSRF ausente | PASS | HTTP 419 |
| CSRF inválido | PASS | HTTP 419 |
| CSRF válido | PASS | HTTP 200 |
| Integridad de base antes/después | PASS | conteos de tablas clínicas sin cambios |
| Regeneración visual | PASS | botón cambió a “Regenerar” y panel mostró el resumen |
| Responsive 375 × 667 | PASS | captura, 9 secciones, referencias, datos faltantes, advertencia, sin overflow |
| Responsive 768 × 1024 | PASS | captura, 9 secciones, referencias, datos faltantes, advertencia, sin overflow |
| Responsive 1024 × 768 | PASS | captura, 9 secciones, referencias, datos faltantes, advertencia, sin overflow |
| Responsive 1440 × 900 | PASS | captura, 9 secciones, referencias, datos faltantes, advertencia, sin overflow |
| Evidencia visual responsive | PASS | `docs/evidence/clinical-ai-summary/clinical-summary-{375x667,768x1024,1024x768,1440x900}.png` |
| Errores JavaScript en navegador | PASS | 0 errores `error`/`unhandledrejection` en los cuatro viewports |
| Consola / exposición de secretos | PASS | sin API key, `patient_id_internal` ni mensajes clínicos de error |
| Navegación de regreso al expediente | PASS | URL permaneció en `/clinica/paciente/9301` y controles operables |
| Gemini real | PASS | HTTPS real, HTTP 200, modo `gemini`, modelo `gemini-3.8-flash`, 7691 ms |
| E2E Doctor → Gemini desde interfaz | PASS | primera generación posterior a la corrección devolvió 9 de 9 secciones en modo `gemini` |
| Gemini: nueve secciones | PASS | contrato exige exactamente M01–M09; generación real devolvió y presentó las nueve |
| Gemini: referencias | PASS | 2 referencias documentales, formato opaco válido y validadas contra el snapshot |
| Gemini: información personal | PASS | `patient_id_internal` se excluye antes del prompt; no hubo PII en el resumen devuelto |
| Gemini: tokens de entrada | PASS | `countTokens`: 894 tokens |
| Gemini: tokens de salida/costo | NO DISPONIBLE | `GeminiProvider` no propaga `usageMetadata`; no se hizo una estimación no verificable |
| Fallback ante transporte bloqueado | PASS | intentos sandbox devolvieron `GEMINI_TRANSPORT` y resumen determinista de 9 secciones |
| Contrato de cobertura M01–M09 | PASS | 11 aserciones aisladas: 7/8, duplicado, desconocido, orden, sección vacía, referencia inválida y fallback |

### Defecto corregido durante la auditoría

La validación de Gemini inicialmente permitía elementos sin referencias documentales. Se corrigió para exigir contrato `clinical-summary.v1`, tipos, estados permitidos y al menos una referencia existente por afirmación. También se corrigió la minimización del snapshot: nombres, identificadores directos, contactos y responsables ya no se incluyen en el contexto externo; las referencias ya no exponen IDs de base de datos. Los errores continúan devolviendo fallback determinista y categoría técnica sin secretos.

La auditoría M01–M09 demostró además que el validador aceptaba respuestas con siete secciones porque solo comprobaba cada elemento recibido. La corrección focal ahora exige las claves exactas `M01` a `M09`, rechaza duplicados, claves desconocidas, cobertura incompleta, secciones documentadas vacías y estados incompatibles con el snapshot, y normaliza respuestas válidas fuera de orden. El esquema enviado a Gemini declara `enum`, `minItems=9` y `maxItems=9`; la validación PHP continúa siendo obligatoria. Cualquier rechazo conserva únicamente el fallback determinista de nueve secciones con modo `deterministic`; no existe reintento automático.

### Archivos de esta funcionalidad

- `app/Config/routes.php`
- `app/Controllers/ClinicalSummaryController.php`
- `app/Services/ClinicalSnapshotService.php`
- `app/Services/ClinicalSummaryService.php`
- `app/Services/ClinicalSummarySecurity.php`
- `app/Services/DeterministicClinicalSummaryService.php`
- `app/Services/GeminiProvider.php`
- `app/Services/DeterministicClinicalSummaryService.php` (claves de fallback `M01`–`M09`)
- `app/views/clinica/pacientes-detalle.php`
- `public/assets/js/clinical-summary.js`
- `tests/clinical-summary/coverage-contract.php`
- `docs/CLINICAL_AI_SUMMARY_PHASE1_REPORT.md`
- `docs/evidence/clinical-ai-summary/clinical-summary-375x667.png`
- `docs/evidence/clinical-ai-summary/clinical-summary-768x1024.png`
- `docs/evidence/clinical-ai-summary/clinical-summary-1024x768.png`
- `docs/evidence/clinical-ai-summary/clinical-summary-1440x900.png`

### Riesgos pendientes y condiciones para expedientes reales

La validación no demuestra que una afirmación parafraseada por un modelo sea clínicamente correcta solo por citar una referencia; requiere revisión clínica. Antes de habilitar expedientes reales se necesitan autorización clínica, legal y contractual; evaluación de retención y tratamiento de datos con Google; aprobación del modelo y endpoint; pruebas de aislamiento HTTP con cuentas sintéticas; pruebas visuales autenticadas; y una política explícita de no transmisión. Las banderas `CLINICAL_AI_SUMMARY_ENABLED`, `CLINICAL_AI_SYNTHETIC_MODE` y `CLINICAL_AI_EXTERNAL_ENABLED` permanecen desactivadas por defecto.

## Evidencia visual y de navegador

La prueba se ejecutó con Google Chrome headless local mediante Chrome DevTools Protocol, contra un servidor PHP aislado en `127.0.0.1:8083` y la base `clinica_dolor_demo_20261010_final`. No se instalaron dependencias nuevas ni se transmitieron datos fuera del equipo.

En cada viewport se autenticó el Doctor sintético, se abrió el expediente autorizado `9301`, se pulsó el botón real de generación y se esperó el estado `Regenerar`. El panel mostró las nueve secciones, referencias documentales y datos faltantes; la advertencia “Resumen de apoyo sujeto a revisión médica” fue visible. El ancho de documento fue igual al ancho de cliente: 360/360, 753/753, 1009/1009 y 1425/1425 CSS px respectivamente. La altura documentada fue 6293, 5265, 5235 y 4347 px; por ello el resumen completo quedó accesible mediante scroll vertical, sin superposición ni scroll horizontal.

Capturas:

- `docs/evidence/clinical-ai-summary/clinical-summary-375x667.png`
- `docs/evidence/clinical-ai-summary/clinical-summary-768x1024.png`
- `docs/evidence/clinical-ai-summary/clinical-summary-1024x768.png`
- `docs/evidence/clinical-ai-summary/clinical-summary-1440x900.png`

La sesión Paciente sintético obtuvo HTTP 302 al intentar la ruta clínica, con destino seguro `/clinica/acceso`, y no recibió contenido del resumen. La inspección de la respuesta HTML del expediente médico no encontró API keys ni `patient_id_internal`; los identificadores visibles del encabezado corresponden exclusivamente al expediente sintético solicitado, no al contexto externo del resumen.

## Resultado de la primera conexión real

Con autorización expresa y exclusivamente contra `clinica_dolor_demo_20261010_final`, se inició un servidor PHP aislado con `CLINICAL_AI_SUMMARY_ENABLED=1`, `CLINICAL_AI_SYNTHETIC_MODE=1` y `CLINICAL_AI_EXTERNAL_ENABLED=1`. La solicitud del Doctor sintético al endpoint real devolvió HTTP 200, `mode=gemini`, modelo `gemini-3.8-flash`, nueve secciones, dos referencias válidas y sin PII en el resumen. La comparación estructural con el resumen determinista mantuvo las nueve secciones, estados documentales y referencias como criterio de evidencia; la mayor extensión textual de Gemini no se consideró mayor exactitud. No se detectaron afirmaciones sin respaldo mediante la validación de referencias existente. La calidad clínica semántica sigue requiriendo revisión humana.

La clave fue reconocida por PHP sin imprimirse. El endpoint y el modelo observados fueron `https://generativelanguage.googleapis.com/v1beta` y `gemini-3.8-flash`. El conteo `countTokens` devolvió 894 tokens de entrada. La integración no expone los metadatos `usageMetadata` de la respuesta, por lo que no es posible reportar tokens de salida ni costo exacto desde el código actual.

La habilitación externa fue temporal para el proceso de prueba y se restableció al terminar; no se modificó `.env`, no se almacenó la clave, no se tocaron datos y no se dejó un servidor de prueba activo.

## Validación final Doctor → Gemini desde interfaz

El 11/10/2026 se repitió el recorrido desde `http://clinica.test/clinica` con un servidor PHP aislado, configuración temporal del proceso y un router local restringido; el router y el proceso fueron eliminados al terminar. Se autenticó el Doctor sintético, se abrió el expediente sintético `9301` y se pulsó el botón real `Generar resumen con IA`.

La primera generación posterior a la corrección devolvió HTTP 200 y presentó `Gemini · gemini-3.8-flash`, el botón `Regenerar`, exactamente las nueve secciones M01–M09, referencias opacas existentes, estados `absent/incomplete` explícitos para módulos sin datos y la advertencia médica. La interfaz no mostró una respuesta parcial. El resultado `mode=gemini` fue visible en el panel.

La prueba de regresión automatizada cubrió respuestas de siete y ocho secciones, duplicados, claves desconocidas, orden inverso, sección documentada vacía, referencia inexistente e inválida; todas las respuestas inválidas fueron rechazadas y el fallback determinista conserva nueve secciones en orden M01–M09. La regeneración sigue siendo una acción explícita del médico y no hay reintento automático.

La consulta posterior de integridad mantuvo los mismos conteos sintéticos: `pc_paciente=2`, `pc_antecedentes_familiares=6`, `pac_respuestas_paciente_modulo_3=0`, `pc_antecedentes_quirurgicos=0`, M05=0, M06=0, M07=0, M08=0 y `pc_paciente_evaluacion_dolor=1`. No se registraron payloads clínicos ni la clave; el proceso temporal fue detenido y no quedó habilitación externa activa.

## Inspección de integración real Gemini

La revisión del 11/10/2026 confirmó que el proveedor apunta exclusivamente a `https://generativelanguage.googleapis.com/v1beta` y construye la operación `models/{model}:generateContent` con `x-goog-api-key` por HTTPS. El modelo configurado por defecto es `gemini-3.8-flash`, que la [documentación oficial del modelo](https://ai.google.dev/gemini-api/docs/models/gemini-3.8-flash) identifica como estable y con soporte de salidas estructuradas. La [documentación oficial de `generateContent`](https://ai.google.dev/api/generate-content) también muestra que el esquema utilizado (`responseMimeType`/`responseSchema`) es compatible con `generateContent`; `responseSchema` aparece como deprecado en favor de `responseFormat`, pero no se observó un fallo de ejecución que justifique modificarlo en esta validación.

La clave fue reconocida por PHP sin imprimirse. La ejecución real sintética devolvió HTTP 200 en aproximadamente 7691 ms en la medición directa del proveedor; el recorrido E2E de interfaz confirmó el modelo y el resultado estructurado. `countTokens` devolvió 894 tokens de entrada. Google no proporcionó metadatos de salida utilizables por el proveedor actual, por lo que tokens de salida y costo permanecen no disponibles y no se estiman.

Para una futura ejecución autorizada, la configuración deberá inyectarse solo en el proceso aislado del servidor de pruebas, sin editar `.env` ni el repositorio: `GEMINI_API_KEY` válida, `GEMINI_MODEL=gemini-3.8-flash`, `GEMINI_ENDPOINT=https://generativelanguage.googleapis.com/v1beta`, `CLINICAL_AI_SUMMARY_ENABLED=true`, `CLINICAL_AI_SYNTHETIC_MODE=true` y `CLINICAL_AI_EXTERNAL_ENABLED=true`. La clave no debe aparecer en logs, capturas, respuestas HTTP, archivos ni commits. La base debe seguir siendo exclusivamente `clinica_dolor_demo_20261010_final`.

## Estado formal de cierre

## Diagnóstico `CLINICAL_SUMMARY_DISABLED` y entorno web sintético

La causa raíz del mensaje fue `ClinicalSummaryService::generate()`: lanza `CLINICAL_SUMMARY_DISABLED` cuando `ClinicalSummarySecurity::enabled()` devuelve falso. Esa función solo acepta `CLINICAL_AI_SUMMARY_ENABLED=true` o `CLINICAL_AI_SYNTHETIC_MODE=true`; `CLINICAL_AI_EXTERNAL_ENABLED=true` habilita Gemini después de superar esa primera protección. La clave por sí sola no habilita el resumen.

El Apache/XAMPP de `http://clinica.test` carga `.env`, cuyo `DB_DATABASE` efectivo es `bd_clinica`; las tres banderas de IA no están definidas y PHP las evalúa como `false`. Por tanto, ese vhost compartido permanece protegido y no se utilizó para procesar expedientes.

Para la prueba se levantó un proceso PHP aislado, enlazado localmente y restringido por router a la aplicación, con estas variables únicamente en el proceso:

```text
DB_DATABASE=clinica_dolor_demo_20261010_final
CLINICAL_AI_SUMMARY_ENABLED=1
CLINICAL_AI_SYNTHETIC_MODE=1
CLINICAL_AI_EXTERNAL_ENABLED=1
GEMINI_MODEL=gemini-3.8-flash
GEMINI_ENDPOINT=https://generativelanguage.googleapis.com/v1beta
```

La API key se obtuvo de la configuración local sin imprimirse ni almacenarse como evidencia. La petición HTTP autenticada del Doctor sintético al expediente `9301` devolvió `HTTP 200`, `mode=gemini`, nueve secciones `M01`–`M09`, sin fallback y sin modificar la base. La inspección integrada del navegador de esta sesión bloqueó URLs locales con `ERR_BLOCKED_BY_CLIENT`; el recorrido visual E2E autenticado previamente permanece documentado como PASS, y la respuesta real del proceso aislado fue verificada por HTTP sin exponer payloads.

La configuración segura de pruebas debe mantenerse como un proceso aislado y temporal con la base allowlisted; no debe trasladarse al `.env` del vhost que apunta a `bd_clinica`. Para expedientes reales faltan autorización clínica, legal y contractual, política de tratamiento con Google, aislamiento de una base no productiva y aprobación explícita de transmisión. Mientras esas condiciones no existan, `REAL_PATIENT_DATA_AUTHORIZATION` permanece `NOT_APPROVED`.

`PHASE1_SYNTHETIC_CLOSURE: PASS`

`GEMINI_REAL_INTEGRATION: PASS`

`DOCTOR_GEMINI_REAL_E2E: PASS`

`REAL_PATIENT_DATA_AUTHORIZATION: NOT_APPROVED`

## Optimización de información documentada — 11/10/2026

Se redujo el ruido del informe sin cambiar los contratos clínicos. `ClinicalNarrativeService` ahora clasifica respuestas como afirmación, negación, número válido —incluido cero—, texto, sin respuesta o no aplicable explícito. No utiliza `empty()` y omite NULL, vacío y preguntas sin respuesta del resumen ejecutivo. Las negaciones, hechos documentados y referencias permanecen.

La presentación M01–M09 oculta únicamente campos no registrados y filas de cuestionarios sin respuesta; los registros persistidos y formularios originales no se modifican. M06, M07, M08, recetas históricas y COFEPRIS continúan diferenciados. Los pendientes se limitan a módulos ausentes/incompletos o inconsistencias verificadas; no se generaron listas de decenas de preguntas vacías.

Pruebas: narrativa sintética PASS con 21 aserciones; cobertura M01–M09 PASS con 11 aserciones; modo determinista, 9 secciones, cero proveedor externo y conteo de pacientes sin cambios. La ausencia de metadatos de dependencia verificables en los catálogos disponibles se documenta como limitación: no se infiere aplicabilidad y una pregunta no respondida se omite.

`CLINICAL_DATA_FIDELITY: PARTIAL`

`CLINICAL_NARRATIVE_SUMMARY: PASS`

`CLINICAL_EXECUTIVE_SUMMARY_V3_3: PASS`

`CLINICAL_SUMMARY_V3_3_UI: PARTIAL`

`CLINICAL_TIMELINE: PASS`

`REAL_PATIENT_DATA_AUTHORIZATION: NOT_APPROVED`

## Refinamiento V3.3 — redacción médica determinista — 11/10/2026

La auditoría confirmó que `CLINICAL_DATA_FIDELITY` permanecía en `PARTIAL` por limitaciones de validación exhaustiva y por una presentación ejecutiva todavía demasiado cercana a los campos persistidos. No se encontró evidencia de que fuera necesario modificar consultas clínicas ni el contrato M01–M09.

Se añadió `ClinicalNarrativeService`, una transformación en memoria que reutiliza el detalle y las fuentes complementarias ya recuperadas. El nuevo bloque `clinical-narrative.v1` redacta antecedentes, dolor, medicación actual, tratamientos, procedimientos, prescripciones históricas, COFEPRIS y seguimiento, conservando referencias. Distingue afirmaciones, negaciones, NULL, información incompleta y pendientes; no infiere diagnósticos, eficacia, estabilidad, causalidad ni recomendaciones.

La vista ahora presenta una síntesis general en párrafo, antecedentes positivos y negativos, situación del dolor y fuentes terapéuticas diferenciadas. La historia completa M01–M09 y la línea de tiempo V3.2 se conservan. Los pendientes muestran mensajes concretos y anclas por módulo. El ancho y la tipografía narrativa se ajustaron sin rediseñar el Dashboard.

Pruebas: contrato de narrativa sintética PASS con 16 aserciones; contrato de cobertura M01–M09 PASS con 11 aserciones; expediente sintético local con 9 secciones deterministas; PHP, JavaScript y `git diff --check` PASS. Validación visual/autenticada en los viewports solicitados: `NO VERIFICADO` por ausencia de navegador y sesión Doctor. Gemini externo continúa deshabilitado.

Auditoría detallada: `docs/CLINICAL_SUMMARY_V3_3_AUDIT.md`.

`CLINICAL_DATA_FIDELITY: PARTIAL`

`CLINICAL_NARRATIVE_SUMMARY: PASS`

`CLINICAL_EXECUTIVE_SUMMARY_V3_3: PASS`

`CLINICAL_SUMMARY_V3_3_UI: PARTIAL`

`CLINICAL_TIMELINE: PASS`

`REAL_PATIENT_DATA_AUTHORIZATION: NOT_APPROVED`

## Auditoría y evolución UX/UI V3.2 — 11/10/2026

Se realizó la auditoría de fidelidad sobre `ClinicalSnapshotService`, `ClinicalDetailService`, `ClinicalSummaryService`, `ClinicalSupplementService`, el controlador y la vista. La lectura M01–M09 conserva las fuentes y etiquetas verificadas, los valores documentados y las referencias opacas; los valores NULL se presentan como `No registrado` y no se transforman en respuestas negativas. M06 continúa separado de recetas históricas. Las contradicciones y fechas anómalas no se reinterpretan automáticamente y quedan para revisión humana.

La interfaz recibió mejoras focales: resumen ejecutivo amplio con motivo de atención, antecedentes documentados, situación del dolor, tratamientos y seguimiento; línea de tiempo descendente para notas subsecuentes, recetas, laboratorios y COFEPRIS; categorías de pendientes con anclas hacia M01–M09; y navegación rápida sin tabs, selector, acordeón obligatorio ni panel separado. La lectura continua V3.1 permanece intacta. Se añadieron estilos de impresión local sin endpoint PDF ni almacenamiento de documentos.

Como corrección de seguridad, el endpoint ya no devuelve el snapshot interno completo al navegador. El snapshot se usa únicamente en el servidor para validar el resumen; la respuesta devuelve el resumen validado, el detalle autorizado y las fuentes complementarias limitadas necesarias para la interfaz. No se modificaron contratos clínicos, `.env`, `bd_clinica`, P05–P11 ni el estado de Gemini externo.

Documento de auditoría: `docs/CLINICAL_SUMMARY_V3_2_AUDIT.md`. Validación visual con navegador real, capturas, teclado, consola y los seis viewports solicitados: `NO VERIFICADO` por ausencia de navegador/sesión Doctor en esta ejecución. No se declara producción lista ni autorización de datos reales.

`CLINICAL_DATA_FIDELITY: PARTIAL`

`CLINICAL_EXECUTIVE_SUMMARY: PASS`

`CLINICAL_TIMELINE: PASS`

`CLINICAL_NAVIGATION: PASS`

`CLINICAL_SUMMARY_V3_2_UI: PARTIAL`

`REAL_PATIENT_DATA_AUTHORIZATION: NOT_APPROVED`

El cierre sintético es `PASS`: el flujo HTTP autenticado, la seguridad, la recuperación sintética, la generación y regeneración, la integridad de la base, los cuatro viewports reales y la consola del navegador pasaron. Este resultado no autoriza producción ni el procesamiento de expedientes reales.

## Activación operativa determinista local — 11/10/2026

Se habilitó en el `.env` local del vhost habitual únicamente el camino determinista:

```text
DB_DATABASE=bd_clinica
CLINICAL_AI_SUMMARY_ENABLED=1
CLINICAL_AI_SYNTHETIC_MODE=0
CLINICAL_AI_EXTERNAL_ENABLED=0
```

La variable `GEMINI_API_KEY` permanece configurada sin copiarse, mostrarse ni registrarse. La clave por sí sola no activa ningún envío: `ClinicalSummaryService` solo instancia `GeminiProvider` cuando `CLINICAL_AI_EXTERNAL_ENABLED` es verdadero. Con el valor `0`, la respuesta se produce mediante `DeterministicClinicalSummaryService` y no se construye una solicitud HTTP a Google.

La prueba de lectura contra la base efectiva `bd_clinica` seleccionó un expediente sin imprimir su identificador ni contenido. Devolvió `mode=deterministic`, exactamente las nueve claves `M01`–`M09`, estados `documented`, `absent` e `incomplete` según los registros disponibles, y `fallback_reason=null`. Los conteos de las tablas clínicas consultables fueron idénticos antes y después; no hubo INSERT, UPDATE ni DELETE. Los módulos vacíos se presentan en la interfaz como `No registrado.` o `Incompleto; requiere revisión médica.`.

La comprobación HTTP de infraestructura devolvió `/` 200, `/clinica` 302 a `/clinica/acceso`, `/clinica/acceso` 200 y una solicitud POST sin sesión al endpoint clínico 302 a `/clinica/acceso`; una solicitud GET al endpoint devolvió 405. No se usó una credencial real. `bd_clinica` no contiene una cuenta Doctor sintética activa, por lo que no se realizó una sesión autenticada en esa base ni se afirma autorización clínica sobre expedientes reales. Las comprobaciones de autorización, aislamiento de clínica, rol Doctor, CSRF y escape permanecen implementadas en `AuthMiddleware`, `ClinicalSummaryController` y el frontend; su validación autenticada continúa cubierta por la evidencia sintética aislada previa.

El servidor temporal de pruebas PID 17140 no está activo. El puerto 80 lo atiende Apache/XAMPP (PID 4304 y su proceso hijo), con `http://clinica.test` conectado a `bd_clinica`; no se dejó una ruta temporal ni un proceso de demostración ejecutándose.

La integración externa permanece preparada pero bloqueada para datos reales. Para activarla en el futuro se requiere autorización clínica, legal y contractual explícita, una política aprobada de tratamiento externo, una base sintética o un entorno expresamente autorizado, y una inyección temporal de `CLINICAL_AI_EXTERNAL_ENABLED=1` sin mover la API key al código, HTML, JavaScript, logs o capturas. Mientras esas condiciones no existan, no se transmiten expedientes reales a Gemini y `REAL_PATIENT_DATA_AUTHORIZATION` permanece `NOT_APPROVED`.

Limitación de cierre operativo: la funcionalidad local está lista y probada en lectura, pero la prueba completa desde la interfaz con un Doctor autorizado de `bd_clinica` requiere una credencial de prueba autorizada que no existe actualmente en esa base. No se utilizaron credenciales de médicos reales ni se modificaron expedientes para superar esta limitación.

## Rediseño del resumen clínico en vista independiente — 11/10/2026

Se sustituyó el panel extenso del expediente por una tarjeta compacta de acceso y se agregó la ruta protegida `GET /clinica/paciente/{idPaciente}/resumen-clinico`. La nueva vista conserva el sidebar Doctor, la barra superior, la identidad morada/lavanda, el contexto del expediente y el enlace de regreso. La generación continúa usando exclusivamente `POST /clinica/paciente/{idPaciente}/clinical-summary`, con una única petición al pulsar `Generar resumen` o `Regenerar`.

La vista independiente incluye panorama general derivado únicamente de los módulos devueltos, nueve tarjetas documentales M01–M09, referencias, estados `Documentado`, `No registrado`, `Incompleto` y `Requiere revisión`, además de la sección de datos faltantes. Muestra explícitamente `Generación local` y `Gemini externo deshabilitado`; no amplía el contexto de Gemini ni incorpora notas, recetas, laboratorios o COFEPRIS al resumen nuevo. Esas fuentes siguen siendo accesibles únicamente por sus rutas existentes y con sus controles de pertenencia/autorización.

Archivos creados: `app/views/clinica/resumen-clinico.php`.

Archivos modificados: `app/Config/routes.php`, `app/Controllers/ClinicaController.php`, `app/views/clinica/pacientes-detalle.php`, `public/assets/js/clinical-summary.js`, `public/assets/css/app.css` y este informe.

Validación técnica: PASS para sintaxis PHP de rutas/controlador/vista, sintaxis JavaScript, comprobación de espacios Git, redirección de la ruta nueva sin sesión a `/clinica/acceso`, reutilización del endpoint existente, CSRF existente, fallback determinista y configuración externa sin cambios. Responsive y consola autenticados: NO VERIFICADO en esta sesión porque no hubo navegador operativo ni sesión Doctor autorizada; no se generaron capturas nuevas. No se modificaron expedientes, tablas, `.env`, Gemini ni el trabajo paralelo P05–P11.

## Ampliación profesional y fuentes complementarias — 11/10/2026

Se corrigió localmente el botón `Volver al expediente` mediante la especificidad `body.clinical-summary-page .clinical-summary-back.btn.btn-light`, sin cambiar la regla Bootstrap global. Ahora conserva fondo blanco, texto e icono morados, borde lavanda, hover lavanda, foco visible y altura táctil mínima de 44 px.

La vista mantiene los componentes del Dashboard Doctor y ahora organiza la información en encabezado, contexto del expediente, panorama M01–M09, sección `Información clínica complementaria` y tarjeta final de faltantes. Se añadió el modo visible `Generación local`; `CLINICAL_AI_EXTERNAL_ENABLED=0` se conserva.

Se creó `app/Services/ClinicalSupplementService.php`, separado del contrato M01–M09 y de cualquier escritura. Ejecuta cuatro consultas PDO parametrizadas, limitadas a cinco registros por categoría y filtradas por `id_paciente`: `nota_subsecuente`, `receta_medica`, `laboratorio` y `cofepris`. El controlador solo lo invoca después de autenticar la sesión Doctor y ejecutar `assertPatientAccess`. No se incorporan estos datos al prompt Gemini.

Campos presentados:

- Notas: fecha/hora, contenido escapado, signos y medidas disponibles, ECOG, Karnofsky y próxima cita.
- Prescripciones registradas: fecha, diagnóstico, medicamento y signos disponibles; no se presentan como medicación actual.
- Laboratorio: fecha, nombre, tipo, título, descripción y referencia interna; no se descarga ni enlaza ningún archivo protegido.
- COFEPRIS: fecha, diagnóstico, medicamento, presentación, dosificación, cajas, duración, vía y estado de surtido sin reinterpretarlo.

Prueba de lectura contra `bd_clinica`: 0 notas, 1 prescripción, 0 laboratorios y 1 registro COFEPRIS en el expediente seleccionado. Los conteos de las tablas permanecieron sin cambios. Resultado de consulta complementaria: PASS. Autorización cruzada entre clínicas: no verificada con una sesión Doctor real en esta ejecución; el controlador reutiliza el mismo aislamiento de paciente/clínica y no se crearon cuentas ni fixtures en `bd_clinica`.

Los bloqueos documentales SEC-03/SEC-08 del `docs/security-backlog.md` permanecen vigentes. Por ello la nueva pantalla solo muestra metadatos de laboratorio y no crea enlaces a archivos, recetas PDF o COFEPRIS PDF. Responsive autenticado, contraste computado en navegador, navegación por teclado y consola: NO VERIFICADO por falta de un navegador/sesión Doctor disponible. La ruta sin sesión continúa respondiendo 302 a `/clinica/acceso`.

## Rediseño UX/UI V2.0 — 11/10/2026

Se reorganizó visualmente la vista independiente sin modificar el backend clínico ni el contrato M01–M09. El contenedor ahora utiliza el ancho disponible después del sidebar con un límite fluido de 1600 px, mayor padding lateral y separación vertical amplia. El encabezado, la tarjeta de paciente y la acción de regreso conservan la identidad del Dashboard Doctor.

La composición final queda dividida en bloques: encabezado principal; identificación horizontal del paciente con modo de generación separado; panorama clínico en dos columnas; documentación complementaria en dos columnas; índice de módulos en tres columnas; historia detallada M01–M09 como tarjetas expandibles de tres columnas; y datos faltantes en una tarjeta de advertencia suave. En tablet se usan dos columnas para los módulos y en móvil una columna.

Se aumentaron los espacios internos de tarjetas principales a aproximadamente 26–34 px, las separaciones entre tarjetas a 20–24 px, el interlineado clínico a 1.6 y la escala tipográfica de contenido a aproximadamente 14–15 px. Las referencias se muestran como etiquetas discretas y los estados documentales permanecen semánticos, sin porcentajes de formulario ni alertas clínicas inventadas.

El estado de carga ahora comunica `Generando resumen local` con indicador visual; después de una respuesta válida el botón pasa a `Regenerar resumen`. La fecha/hora y el modo real se muestran en la cabecera del resultado. No se duplican solicitudes ni se modificó la ruta POST.

Archivos modificados para V2.0: `public/assets/css/app.css` y `public/assets/js/clinical-summary.js`. La vista independiente sigue en `app/views/clinica/resumen-clinico.php`; no se modificaron `.env`, `bd_clinica`, servicios de resumen, autorizaciones ni P05–P11.

Validación técnica: PHP, JavaScript y `git diff --check` PASS; ruta sin sesión 302 a `/clinica/acceso`; consultas complementarias y M01–M09 sin cambios. Validación visual con Chrome/Edge en 375, 430, 768, 1024, 1440 y 1920 px, capturas comparativas, consola autenticada, navegación por teclado y regeneración real: NO VERIFICADO por no disponer de navegador ni sesión Doctor autorizada en esta ejecución. No se sustituyó esa validación por simulación de CSS.

## Rediseño UX/UI V3.0 — detalle clínico M01–M09 — 11/10/2026

La sección de detalle dejó de utilizar tarjetas expandibles independientes. Ahora tiene dos niveles sin recarga ni nuevas peticiones al cambiar de módulo:

1. Selector accesible M01–M09, con tarjetas de altura uniforme, icono, estado documental y selección visible. Usa botones nativos, foco visible, `aria-selected`, `role=tab` y navegación con flechas, Home y End.
2. Panel de detalle de ancho completo para el módulo seleccionado, con opción `Vista completa` para lectura continua de los nueve módulos apilados.

Se creó `app/Services/ClinicalDetailService.php`, servicio local y de solo lectura que devuelve el bloque separado `clinical_detail`. No modifica `summary`, el contrato `clinical-summary.v1`, las referencias, Gemini ni las consultas de escritura. El controlador solo lo solicita después de autenticar al Doctor y validar el paciente mediante `assertPatientAccess`; el bloque nunca se incluye en el prompt externo.

La presentación usa etiquetas verificadas desde las tablas y catálogos existentes: M01 traduce datos generales y fechas; M03, M05 y M07 recuperan tema, pregunta y respuesta mediante joins a sus catálogos; M08 recupera procedimientos y tratamientos; M02, M04, M06 y M09 usan los campos clínicos comprobados de sus fuentes. No se muestran `id_pregunta`, `id_enfermedad` ni nombres de columnas en el panel. Las respuestas originales quedan disponibles en un apartado expandible por registro, con referencias `Mxx:Rnn` discretas y valores escapados.

La coherencia documental conserva el estado producido por el resumen determinista: `Documentado`, `Incompleto`, `No registrado` y `Requiere revisión`. La sección de pendientes continúa mostrando únicamente los elementos devueltos por el contrato; no se convirtió una ausencia en una negación ni se modificó la regla de clasificación M09.

Pruebas funcionales locales: PASS. El detalle produjo las nueve claves M01–M09; la generación devolvió nueve secciones en modo `deterministic`; la configuración externa resultó `false`; y el conteo de pacientes permaneció sin cambios. La ruta sin sesión sigue respondiendo 302 a `/clinica/acceso`. Sintaxis PHP, JavaScript y `git diff --check`: PASS.

Pruebas visuales reales en 375, 430, 768, 1024, 1440 y 1920 px, capturas, consola autenticada, interacción Doctor y navegación completa por teclado: NO VERIFICADO porque esta sesión no tiene navegador ni sesión Doctor autorizada. No se usó simulación de viewport como sustituto. No se modificaron `.env`, `bd_clinica`, formularios, Gemini ni P05–P11.

## Validación final con sesión Doctor real — 11/10/2026

Resultado: `DOCTOR_REAL_LOCAL_SUMMARY_E2E: NOT_VERIFIED`.

No había una sesión Doctor reutilizable ni un navegador Chrome/Edge operativo disponible en la sesión de validación. La pestaña integrada devolvió `Solicitud no disponible`; no se solicitaron, capturaron ni procesaron credenciales. Las comprobaciones no autenticadas confirmaron que PHP carga `bd_clinica`, el resumen está habilitado, `CLINICAL_AI_SYNTHETIC_MODE=0` y `CLINICAL_AI_EXTERNAL_ENABLED=0`. Una solicitud POST sin sesión fue redirigida con HTTP 302 a `/clinica/acceso`, por lo que no se expuso ningún resumen.

Para cerrar esta validación manualmente, un Doctor debe iniciar sesión directamente en `http://clinica.test/clinica`, abrir un expediente autorizado, pulsar `Generar resumen con IA`, y verificar HTTP 200, nueve módulos, `mode=deterministic`, datos faltantes visibles como `No registrado`/`Incompleto`, y el aviso de resumen local sujeto a revisión médica. Después debe pulsar `Regenerar` y comprobar que los conteos de las tablas clínicas permanecen sin cambios. En DevTools → Network debe observarse únicamente la petición local al endpoint clínico, sin solicitudes a `googleapis.com`.

## Rediseño UX/UI V3.1 — historia clínica en lectura continua — 11/10/2026

Se ajustó exclusivamente la presentación de la vista independiente. Se retiraron el selector de módulos M01–M09, el estado de módulo seleccionado, el panel focal, el botón `Vista completa` y la lógica JavaScript asociada. La respuesta clínica continúa siendo la misma y conserva autenticación Doctor, CSRF, aislamiento por paciente/clínica, fallback determinista y Gemini externo deshabilitado.

La historia ahora presenta los nueve módulos en una columna vertical continua. Cada módulo ocupa el ancho disponible, muestra su encabezado, estado documental, registros, campos con etiquetas de catálogo y referencias discretas `Mxx:Rnn`. Los registros no se ocultan en acordeones. Los módulos sin registros muestran `No registrado.` o `Incompleto; requiere revisión médica.` según el estado recibido. En móvil los campos pasan a una columna y los controles mantienen ancho táctil completo; se añadió una regla de contención para evitar overflow horizontal.

Validación técnica local: PASS. `ClinicalDetailService` devolvió exactamente las nueve claves M01–M09 en lectura; la generación determinista mantuvo nueve secciones, `external=false` y conteo de pacientes sin cambios. La ruta sin sesión continuó devolviendo HTTP 302 a `/clinica/acceso`. Sintaxis PHP del controlador, vista y servicio, sintaxis JavaScript y `git diff --check`: PASS. No se modificaron `.env`, `bd_clinica`, Gemini ni P05–P11, y no se realizaron commits ni despliegues.

Validación visual con navegador real, capturas y consola en 375×667, 768×1024, 1024×768 y 1440×900: NO VERIFICADO. Esta ejecución no dispuso de una sesión Doctor ni de un navegador operativo; no se sustituyó esta comprobación por simulación de dimensiones CSS. Por ello no se declara PASS visual ni se cambia el estado de autorización de datos reales.

`PHASE1_SYNTHETIC_CLOSURE: PASS`

`GEMINI_REAL_INTEGRATION: PASS`

`REAL_PATIENT_DATA_AUTHORIZATION: NOT_APPROVED`
