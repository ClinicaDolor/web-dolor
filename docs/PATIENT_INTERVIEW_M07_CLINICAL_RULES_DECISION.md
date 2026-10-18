# M07 — Cierre de reglas clínicas y contrato conversacional

Proyecto: `C:\xampp\htdocs\clinica-dolor`  
Fecha: 2026-10-14  
Alcance: análisis contractual previo a una única implementación conversacional de M07.  

STATUS: PROPOSED_PENDING_CLINICAL_APPROVAL

Este documento no aprueba por sí mismo cambios clínicos, de esquema o de
persistencia. El documento de alcance
`docs/PATIENT_INTERVIEW_M07_CONVERSATIONAL_SCOPE_PROPOSAL.md` conserva su
aprobación funcional previa; las cuatro decisiones de este documento siguen
pendientes de validación clínica.

## 1. Resumen ejecutivo

Se verificó que el catálogo P09 contiene seis categorías y 46 entradas con sus
identificadores actuales. El contrato técnico tradicional conserva una fila por
paciente y medicamento catalogado, con los campos `respuesta`, `dosis`,
`resultados`, `consumo` y `descripcion`.

Las conclusiones son:

1. La completitud propuesta puede definirse sin cambiar el significado de los
   campos: T1 siempre es obligatorio; T2, T3 y T4 son obligatorios después de
   T1=`Si`; T5 es obligatorio cuando T3=`Tuve efectos adversos`; T2–T5 son no
   aplicables después de una negativa confirmada y limpiada.
2. La propuesta anterior requiere aprobación clínica porque el código actual no
   declara formalmente esos campos como obligatorios para finalizar.
3. No se encontró un destino canónico para el nombre específico de `Otro/Otros`.
   La recomendación operativa es mantenerlo pendiente y no guardar un nombre en
   `descripcion`; la solución completa requeriría un contrato de colección para
   medicamentos no catalogados.
4. T3 es excluyente y no conserva eficacia y efectos adversos simultáneos. La
   captura combinada debe quedar bloqueada hasta aprobar una representación en
   dimensiones independientes; no se debe escoger una opción que pierda uno de
   los hechos.
5. La clasificación histórica se conserva. Hay candidatos claros para revisión
   clínica, especialmente tres entradas de `Ansioliticos`, dos entradas de
   `Antiflamatorios`, la denominación `Clorhidrato de Lisina` y las etiquetas
   `Otro/Otros`.

No se implementó código, no se importó el catálogo, no se escribieron bases de
datos, no se modificó el paciente 310, no se utilizó Gemini ni STT externo y no
se realizaron commits o despliegues.

## 2. Fuentes revisadas y clasificación de evidencia

### 2.1 Fuentes del repositorio

- `docs/PATIENT_INTERVIEW_M07_CONVERSATIONAL_SCOPE_PROPOSAL.md`.
- `tests/p09/README.md`.
- `tests/p09/import-catalog.php`.
- `tests/p09/prepare-synthetic.php`.
- `tests/p09/contract-check.mjs`.
- `tests/p09/http-crud.php`.
- `tests/p09/cleanup-synthetic.php`.
- `app/Models/MedicacionDolorModel.php`:
  `obtenerModulosM7`, `obtenerPreguntasModulos`,
  `medicacionDolorQuest`, `mostrarPreguntasM7Paciente`,
  `editarCuestionarioM7Paciente` y `finalizarModuloPaciente`.
- `app/Controllers/BusquedasController.php::contenidoPreguntasM7`.
- `app/Controllers/ModulosController.php::pacienteEditarCuestionarioM7`.
- `app/Config/routes.php`.
- `app/Services/ClinicalDetailService.php` para lectura de M07.
- `tests/fixtures/security-fixtures.sql` para la estructura sintética.
- Contrato general de entrevista en
  `docs/AI_PATIENT_INTERVIEW/02_ARCHITECTURE_AND_CAPTURE_CONTRACT.md`.

### 2.2 Fuente P09 sanitizada

Se revisó en memoria, sin extraer ni ejecutar:

`C:\Users\silvi\Downloads\P09_CATALOGOS_SEGUROS.zip`

El paquete contiene los archivos de catálogo y estructura esperados por
`tests/p09/import-catalog.php`. Su contrato exige seis temas, 46 preguntas,
identificadores únicos, relaciones válidas y ningún `INSERT` de respuestas de
pacientes.

### 2.3 Qué significa cada hallazgo

| Etiqueta | Significado en este documento |
|---|---|
| Regla clínica existente | Está expresamente en el contrato clínico o fue aprobada por el responsable clínico. |
| Validación técnica existente | Está en modelo, controlador, ruta, prueba o esquema; no implica obligatoriedad clínica. |
| Comportamiento observado | Es lo que hace el formulario o el flujo actual; no se eleva a regla nueva. |
| Propuesta | Regla recomendada para discusión, todavía no implementable. |
| Aprobación requerida | Decisión que debe tomar el responsable clínico/propietario antes de programar. |

## 3. Contrato actual M07/P09

### 3.1 Estructura canónica observada

`pac_respuestas_paciente_modulo_7` contiene:

`id`, `id_pregunta`, `id_paciente`, `respuesta`, `dosis`, `resultados`,
`consumo`, `descripcion`.

El catálogo de preguntas contiene `id`, `id_tema` y `pregunta`; no contiene un
tipo de respuesta, una opción de “desconocido”, un campo de nombre libre ni una
regla de completitud.

### 3.2 Validaciones técnicas existentes

`editarCuestionarioM7Paciente`:

- acepta tipos 1 a 5;
- valida `idPaciente`, `idRespuesta`, `idPregunta` e `idTema`;
- verifica que la pregunta pertenezca al tema;
- limita longitudes: T1/T4 20, T3 40, T2/T5 5000;
- permite T1/T4 `Si` o `No`;
- permite T3 `Funciono`, `No funciono` o `Tuve efectos adversos`;
- exige T1=`Si` antes de T2, T3 o T4;
- exige T3=`Tuve efectos adversos` antes de T5;
- acota el `UPDATE` a respuesta, paciente y pregunta;
- puede crear una fila vacía si se recibe `idRespuesta=0` y no existe fila;
- con T1=`No` limpia dosis, resultados, descripción y consumo;
- con T3=`Funciono` o `No funciono` limpia la descripción.

La creación de una fila vacía y la limpieza son comportamientos técnicos del
modelo. No demuestran que el paciente haya contestado ni que la limpieza sea
clínicamente aceptable sin confirmación.

`medicacionDolorQuest` también puede insertar filas vacías al inicializar el
formulario tradicional. La entrevista no debe utilizar la existencia de una
fila como evidencia de respuesta.

`finalizarModuloPaciente` es transaccional e idempotente, pero el método no
define por sí mismo los requisitos clínicos para que el módulo pueda finalizar.

## 4. Decisión 01 — propuesta de completitud T1–T5

### 4.1 Estados de una entrada de medicamento

La siguiente máquina de estados es una propuesta concreta para aprobación; no
se encuentra todavía en el código.

| Estado | Condición | Escritura permitida | ¿Completa el medicamento? |
|---|---|---|---|
| `UNANSWERED` | No existe respuesta clínica o `respuesta` está vacío | No | No |
| `NO_USED_PENDING_CONFIRMATION` | El paciente expresa que no lo utilizó, pero aún no confirma | No | No |
| `NO_USED_CONFIRMED` | T1=`No`, confirmado explícitamente, sin dependientes o después de confirmar su limpieza | T1=`No`; dependientes quedan no aplicables/vacíos | Sí |
| `USED_INCOMPLETE` | T1=`Si` y falta T2, T3 o T4 obligatorio | Sólo propuesta; no finalizar | No |
| `USED_PENDING_ADVERSE_DETAIL` | T3=`Tuve efectos adversos` y T5 falta | Sólo propuesta; no finalizar | No |
| `USED_COMPLETE` | T1=`Si`, T2/T3/T4 completos y T5 completo cuando aplica | Sí, tras validación/confirmación | Sí |
| `UNKNOWN_PENDING` | El paciente dice “no recuerdo”, “no sé” o equivalente | No se traduce a valor canónico | No |
| `CONTRADICTORY_REVIEW` | La nueva respuesta contradice un dato guardado o contiene dos resultados incompatibles | No hasta revisión | No |
| `EDIT_PENDING_CONFIRMATION` | Se modifica una respuesta previamente completa | No hasta mostrar el cambio | No durante la edición |

La existencia de una fila con todos los campos vacíos permanece en
`UNANSWERED`, no en `NO_USED_CONFIRMED`.

### 4.2 Regla propuesta por tipo

| Campo | Regla propuesta | Estado cuando T1=`No` | Estado cuando falta información |
|---|---|---|---|
| T1 `respuesta` | Obligatorio: `Si` o `No`, elegido/expresado por el paciente | `No` sólo después de confirmación explícita | `UNANSWERED` |
| T2 `dosis` | Obligatorio si T1=`Si`; aceptar texto clínicamente legible sin inventar unidades | No aplicable | `UNKNOWN_PENDING` o `USED_INCOMPLETE`; nunca vacío confirmado |
| T3 `resultados` | Obligatorio si T1=`Si`; sólo sus tres valores actuales hasta aprobar cambio | No aplicable | `UNKNOWN_PENDING` o `USED_INCOMPLETE` |
| T4 `consumo` | Obligatorio si T1=`Si`; `Si` o `No` | No aplicable | `UNKNOWN_PENDING` o `USED_INCOMPLETE` |
| T5 `descripcion` | Obligatorio si T3=`Tuve efectos adversos`; no aplicable en los otros resultados | No aplicable | `USED_PENDING_ADVERSE_DETAIL` |

Esta regla es la recomendación de completitud, no una regla clínica ya aprobada.
Requiere aprobación porque el modelo tradicional sólo valida dependencias y no
declara todos estos campos como requisito de finalización.

### 4.3 Negativa T1=`No`

La negativa debe seguir este flujo:

1. La frase/selección se interpreta como propuesta `No`, no como respuesta
   guardada.
2. Si no hay dependientes persistidos, se muestra la propuesta y una acción
   explícita de confirmación; después se persiste T1=`No`.
3. Si hay dosis, resultado, consumo o descripción guardados, se muestran los
   datos que quedarían sin aplicación y se solicita confirmación específica de
   la limpieza.
4. Si el paciente cancela, la respuesta anterior permanece intacta.
5. La ausencia de selección en una lista agrupada nunca genera T1=`No`.
6. Una categoría puede presentar una confirmación agrupada de negativas sólo si
   cada medicamento aparece identificado y el paciente confirma expresamente
   el conjunto; la interfaz no debe ocultar qué negativas se van a registrar.

### 4.4 Respuestas desconocidas, contradictorias y abandono

- “No recuerdo la dosis” no se convierte en `0`, vacío confirmado, `No` ni una
  unidad inventada. Se conserva como pendiente de completar.
- “No recuerdo si funcionó” no se convierte en `No funciono`.
- “Lo usé antes” no determina por sí solo T4; el paciente debe responder si aún
  lo utiliza según la pregunta oficial.
- Una contradicción entre una respuesta nueva y una guardada abre revisión y no
  sobrescribe automáticamente.
- Al abandonar una categoría, las entradas `USED_COMPLETE` y
  `NO_USED_CONFIRMED` permanecen; la reanudación comienza en la primera entrada
  no terminal o en el estado de revisión.

### 4.5 Completitud de categoría y módulo

Recomendación concreta:

- una categoría está completa cuando todas sus entradas están en
  `NO_USED_CONFIRMED` o `USED_COMPLETE`;
- M07 está completo cuando las seis categorías están completas y no existen
  estados `UNKNOWN_PENDING`, `CONTRADICTORY_REVIEW` o
  `EDIT_PENDING_CONFIRMATION`;
- sólo entonces puede solicitarse `finalizarModuloPaciente`;
- la finalización debe ser idempotente y volver a comprobar autorización,
  pertenencia y estado actual en servidor;
- modificar una entrada completa reabre esa entrada y su categoría hasta que la
  nueva respuesta cumpla la misma regla.

Una respuesta pendiente nunca se completa por haber mostrado la tarjeta ni por
haber recorrido las 46 entradas.

## 5. Decisión 02 — `Otro/Otros`

### 5.1 Investigación

No se verificó en el contrato P09:

- un campo `nombre_otro` en `pac_respuestas_paciente_modulo_7`;
- una tabla canónica de medicamentos no catalogados;
- una relación aprobada con `pac_medicacion_actual_modulo_6`;
- un destino en `descripcion` con significado de nombre de medicamento.

M06 (`pac_medicacion_actual_modulo_6`) representa medicación actual y tiene
semántica, campos y ciclo de vida distintos. No es un destino seguro para
respuestas M07 sin una decisión contractual.

El formulario tradicional muestra la entrada `Otro/Otros`, pero la estructura
observada no resuelve de forma demostrable cómo almacenar varios nombres libres
en una misma categoría.

### 5.2 Alternativas

| Alternativa | Descripción | Integridad | Compatibilidad | Riesgo |
|---|---|---|---|---|
| A | Reutilizar un destino existente, por ejemplo M06 o `descripcion` | Baja: cambia el significado o mezcla módulos | Aparente, no contractual | Pérdida de trazabilidad y mezcla de hechos |
| B | Ampliar el contrato con una colección canónica de medicamentos no catalogados, con paciente, tema, nombre normalizado/mostrado y los campos clínicos aprobados | Alta si se diseña y valida formalmente | Requiere adaptador de lectura/escritura y migración conceptual | Cambio de esquema/contrato; múltiples nombres deben definirse |
| C | Mantener `Otro/Otros` como pendiente no persistible hasta aprobar un destino | Alta en el estado actual | Total, porque no cambia P09 | M07 no puede declararse completo si el paciente necesita capturar otro medicamento |

### 5.3 Recomendación

Recomiendo **C como política inmediata**: no guardar el nombre en campos
existentes y marcar la entrada como pendiente. Para capturar el caso de forma
completa, recomiendo **B como decisión contractual futura**.

El cambio exacto que requeriría aprobación es: definir una colección canónica
para uno o varios medicamentos no catalogados por paciente y categoría, con
identificador propio, nombre capturado, reglas de T1–T5, lectura, edición,
autorización, concurrencia y finalización. No se propone crearla ahora.

Los registros históricos no se migrarían por inferencia. Un texto existente en
`descripcion` seguiría siendo descripción de efectos adversos, nunca se
reinterpretaría como nombre de medicamento.

## 6. Decisión 03 — eficacia y efectos adversos simultáneos

### 6.1 Limitación actual

T3 es una selección excluyente entre:

- `Funciono`;
- `No funciono`;
- `Tuve efectos adversos`.

T5 depende de la tercera opción y `descripcion` se usa para describir los
efectos adversos. El contrato actual no conserva una dimensión independiente de
eficacia y otra de tolerabilidad.

Por eso la frase “me ayudó, pero me produjo mareos” no puede mapearse sin
pérdida a uno de los tres valores actuales.

### 6.2 Alternativas

| Alternativa | Representación | Compatibilidad | Resultado |
|---|---|---|---|
| A | Dos dimensiones independientes: eficacia (`Funciono`/`No funciono`/valor aprobado de desconocido) y presencia de efectos adversos, más descripción cuando aplica | Requiere cambio contractual y probablemente almacenamiento/capa de compatibilidad | Conserva ambos hechos sin ambigüedad |
| B | Usar `resultados` actual y `descripcion` para guardar ambos hechos en texto | No demostrada: `descripcion` ya significa efectos adversos y no tiene estructura de eficacia | Rechazada por ambigüedad |
| C | Bloquear la captura combinada y enviarla a revisión | Compatible ahora, sin pérdida | Recomendación inmediata hasta aprobar A |

### 6.3 Recomendación y compatibilidad histórica

Recomiendo **C ahora** y **A como contrato objetivo**. No se deben ampliar los
valores permitidos de T3 ni guardar una frase combinada en `descripcion`.

La definición contractual que debe aprobarse para A incluye, como mínimo:

1. un valor canónico independiente para eficacia;
2. un valor canónico independiente para presencia/ausencia de efectos adversos;
3. la descripción de efectos adversos sólo cuando corresponda;
4. estados desconocidos explícitos, si se decide permitirlos;
5. lectura y edición de ambos ejes;
6. reglas de completitud y migración.

No se fijan nombres de columnas ni valores nuevos en esta fase.

Para registros históricos, la migración conceptual segura sería:

- `Funciono` conserva eficacia positiva y no permite inferir que no hubo efectos
  adversos;
- `No funciono` conserva eficacia negativa y no permite inferir tolerabilidad;
- `Tuve efectos adversos` conserva efectos adversos y deja eficacia como
  desconocida, no como positiva ni negativa;
- ningún valor histórico se convierte automáticamente en un resultado
  combinado.

## 7. Decisión 04 — clasificación del catálogo

La clasificación siguiente es una revisión de riesgo del catálogo histórico,
no una reclasificación aplicada. Los IDs, textos y relaciones no se mueven.

Las etiquetas regulatorias oficiales consultadas sirven sólo para justificar
por qué ciertos elementos requieren revisión; no sustituyen al responsable
clínico del catálogo. Por ejemplo, etiquetas de DailyMed describen olanzapina y
risperidona como antipsicóticos atípicos, zolpidem como sedante/hipnótico,
ketorolaco como AINE y acetaminofén/paracetamol como analgésico/antitérmico.

| ID | Medicamento | Categoría actual | Observación | Riesgo de interpretación | Recomendación | Impacto en IDs |
|---:|---|---|---|---|---|---|
| 1 | Parches de Buprenorfina | Opioides | Consistente con la etiqueta histórica de opioides | Bajo si se conserva como catálogo P09 | Confirmar categoría sin cambiar texto | Conservar 1 |
| 2 | Parches de Fentanilo | Opioides | Consistente con categoría histórica | Bajo | Confirmar | Conservar 2 |
| 3 | Oxicodona | Opioides | Consistente con categoría histórica | Bajo | Confirmar | Conservar 3 |
| 4 | Morfina | Opioides | Consistente con categoría histórica | Bajo | Confirmar | Conservar 4 |
| 5 | Tapentadol | Opioides | Requiere confirmar si el catálogo lo agrupa aquí por finalidad clínica | Medio | Revisión clínica del catálogo, sin mover | Conservar 5 |
| 6 | Tramadol | Opioides | Consistente con finalidad analgésica histórica | Bajo/medio | Confirmar | Conservar 6 |
| 7 | Hidromorfona | Opioides | Consistente con categoría histórica | Bajo | Confirmar | Conservar 7 |
| 8 | Otro | Opioides | Placeholder sin nombre específico | Alto para captura completa | Resolver como `Otro` pendiente; no inferir nombre | Conservar 8 |
| 9 | Pregabalina (Lyrica, etc.) | Anticonvulsivos | También se usa en contextos de dolor neuropático | Medio por etiqueta mixta | Conservar etiqueta histórica y aprobar texto explicativo | Conservar 9 |
| 10 | Gabapentina | Anticonvulsivos | También se usa en contextos de dolor neuropático | Medio | Confirmar | Conservar 10 |
| 11 | Carbamazepina | Anticonvulsivos | Consistente como fármaco de esta familia histórica | Medio | Confirmar | Conservar 11 |
| 12 | Oxcarbazepina | Anticonvulsivos | Consistente con categoría histórica | Medio | Confirmar | Conservar 12 |
| 13 | Lamotrigina | Anticonvulsivos | Consistente con categoría histórica | Medio | Confirmar | Conservar 13 |
| 14 | Topiramato | Anticonvulsivos | Consistente con categoría histórica | Medio | Confirmar | Conservar 14 |
| 15 | Otro | Anticonvulsivos | Placeholder sin nombre específico | Alto | Resolver como `Otro` pendiente | Conservar 15 |
| 16 | Baclofeno | Relajantes Musculares | Consistente con categoría histórica | Bajo | Confirmar | Conservar 16 |
| 17 | Carisoprodol (Dorsal, Nasodol, etc.) | Relajantes Musculares | Mezcla nombre genérico y marcas en el texto | Medio por normalización | Mantener texto e ID; revisar presentación | Conservar 17 |
| 18 | Ciclobenzaprina (Vuralex, Donkirelax) | Relajantes Musculares | Mezcla nombre genérico y marcas | Medio | Mantener texto e ID; revisar presentación | Conservar 18 |
| 19 | Clorzoxazona (talorel flex) | Relajantes Musculares | Denominación y capitalización histórica | Medio | Confirmar identidad y presentación | Conservar 19 |
| 20 | Metocarbamol (Dolocam plus, etc) | Relajantes Musculares | Mezcla nombre genérico y marca | Medio | Mantener ID; revisar texto aprobado | Conservar 20 |
| 21 | Tizanidina (Sirdalud) | Relajantes Musculares | Mezcla nombre genérico y marca | Medio | Mantener ID; revisar texto aprobado | Conservar 21 |
| 22 | Otro | Relajantes Musculares | Placeholder sin nombre específico | Alto | Resolver como `Otro` pendiente | Conservar 22 |
| 23 | Amitriptilina | Antidepresivos | Consistente con categoría histórica | Bajo/medio | Confirmar | Conservar 23 |
| 24 | Escitalopram | Antidepresivos | Consistente con categoría histórica | Bajo | Confirmar | Conservar 24 |
| 25 | Duloxetina (Cymbalta) | Antidepresivos | Mezcla nombre genérico y marca | Bajo/medio | Mantener ID; revisar texto | Conservar 25 |
| 26 | Imipramina | Antidepresivos | Consistente con categoría histórica | Bajo | Confirmar | Conservar 26 |
| 27 | Fluoxetina | Antidepresivos | Consistente con categoría histórica | Bajo | Confirmar | Conservar 27 |
| 28 | Paroxetina | Antidepresivos | Consistente con categoría histórica | Bajo | Confirmar | Conservar 28 |
| 29 | Sertralina | Antidepresivos | Consistente con categoría histórica | Bajo | Confirmar | Conservar 29 |
| 30 | Otro | Antidepresivos | Placeholder sin nombre específico | Alto | Resolver como `Otro` pendiente | Conservar 30 |
| 31 | Alprazolam | Ansioliticos | Consistente con categoría histórica | Bajo | Confirmar categoría y acentuación | Conservar 31 |
| 32 | Diazepam | Ansioliticos | Consistente con categoría histórica | Bajo | Confirmar | Conservar 32 |
| 33 | Lorazepam | Ansioliticos | Consistente con categoría histórica | Bajo | Confirmar | Conservar 33 |
| 34 | Olanzapina | Ansioliticos | La etiqueta oficial consultada la describe como antipsicótico atípico | Alto | Revisión clínica de clasificación antes de explicar la categoría | Conservar 34 |
| 35 | Risperidona | Ansioliticos | La etiqueta oficial consultada la describe como antipsicótico atípico | Alto | Revisión clínica de clasificación antes de explicar la categoría | Conservar 35 |
| 36 | Zolpidem | Ansioliticos | La etiqueta oficial consultada lo describe como sedante/hipnótico | Alto | Revisión clínica de clasificación antes de explicar la categoría | Conservar 36 |
| 37 | Otro | Ansioliticos | Placeholder sin nombre específico | Alto | Resolver como `Otro` pendiente | Conservar 37 |
| 38 | Celecoxib | Antiflamatorios | Etiqueta oficial: AINE selectivo COX-2; la categoría histórica es compatible de forma amplia | Medio | Confirmar categoría y etiqueta ortográfica | Conservar 38 |
| 39 | Etoricoxib | Antiflamatorios | Requiere confirmación clínica de disponibilidad/uso en el alcance local | Medio | Confirmar sin modificar ID | Conservar 39 |
| 40 | Ibuprofeno | Antiflamatorios | Compatible con categoría histórica de AINE | Bajo | Confirmar | Conservar 40 |
| 41 | Naproxeno | Antiflamatorios | Compatible con categoría histórica de AINE | Bajo | Confirmar | Conservar 41 |
| 42 | Ketorolaco | Antiflamatorios | Etiqueta oficial: AINE | Bajo/medio por duración y contexto de uso | Confirmar explicación clínica, sin inferir indicación | Conservar 42 |
| 43 | Diclofenaco | Antiflamatorios | Compatible con categoría histórica de AINE | Bajo | Confirmar | Conservar 43 |
| 44 | Clorhidrato de Lisina | Antiflamatorios | La identidad exacta del principio activo/uso no queda demostrada por el catálogo | Alto | Revisión de identidad y clasificación antes de interpretar | Conservar 44 |
| 45 | Paracetamol | Antiflamatorios | Etiqueta oficial: analgésico/antitérmico; no debe describirse automáticamente como AINE | Alto | Revisión clínica de clasificación y texto explicativo | Conservar 45 |
| 46 | Otros | Antiflamatorios | Placeholder plural sin destino para múltiples nombres | Alto | Resolver colección `Otro/Otros` antes de completitud | Conservar 46 |

No se detectaron IDs duplicados en el catálogo revisado. Las marcas entre
paréntesis deben permanecer como texto histórico hasta que el responsable
decida normalización; no deben generar medicamentos adicionales ni cambiar los
identificadores. Tampoco se debe corregir unilateralmente la ortografía de
`Ansioliticos` o `Antiflamatorios`.

Fuentes regulatorias usadas sólo como señal de revisión: [DailyMed —
olanzapina](https://dailymed.nlm.nih.gov/dailymed/drugInfo.cfm?setid=032e589c-47b1-4eb1-9466-8160297b81e9), [DailyMed —
risperidona](https://dailymed.nlm.nih.gov/dailymed/lookup.cfm?setid=713b5ac7-53d6-4139-8599-3028f789de4b), [DailyMed —
zolpidem](https://dailymed.nlm.nih.gov/dailymed/downloadpdffile.cfm?setId=6a123164-e688-5ce3-e053-2a91aa0a76bc), [DailyMed —
ketorolaco](https://dailymed.nlm.nih.gov/dailymed/downloadpdffile.cfm?setId=e5e7f0cf-70ba-2247-e053-2a95a90a314d), [DailyMed —
celecoxib](https://dailymed.nlm.nih.gov/dailymed/getFile.cfm?setid=1aae31e7-eb98-4535-9e09-286ee94d2ed6) y [DailyMed —
acetaminofén](https://dailymed.nlm.nih.gov/dailymed/lookup.cfm?setid=983debdf-4a61-4dde-ba34-567fdd17720e&version=1).

Estas fuentes no autorizan una reclasificación del catálogo de Clínica del
Dolor.

## 8. Experiencia conversacional definitiva propuesta

### 8.1 Flujo por categoría

1. Mostrar la categoría P09 y sus medicamentos agrupados.
2. Permitir selección múltiple sólo como ayuda de navegación/preselección.
3. Para cada medicamento seleccionado, solicitar T1 y luego los campos
   dependientes aplicables.
4. Presentar los medicamentos no seleccionados como lista explícita de
   confirmación; no guardar negativas por ausencia de selección.
5. Permitir una acción explícita única para una respuesta inequívoca y completa,
   evitando confirmaciones redundantes.
6. Abrir revisión adicional si existe ambigüedad, edición de un dato guardado,
   limpieza de dependientes o contradicción.
7. Guardar individualmente, recuperar por servidor y avanzar sólo después del
   resultado canónico.

### 8.2 Estados visibles

La interfaz debe distinguir literalmente:

- Sin responder.
- No utilizado, pendiente de confirmar.
- No utilizado, guardado.
- Utilizado, incompleto.
- Utilizado, completo.
- Información pendiente/no recordada.
- Respuesta en revisión.
- Respuesta guardada.

El dictado nativo, cuando la política local lo permita, sólo actualiza el
textarea editable. Nunca cambia el estado a guardado ni autoriza un POST.

### 8.3 Edición

Al editar una respuesta completa:

- el servidor vuelve a comprobar paciente, pregunta, tema, tipo y versión;
- el cliente muestra la diferencia y las consecuencias;
- T1=`Si`→`No` requiere confirmar la limpieza de dependientes;
- T3=`Tuve efectos adversos`→otro valor requiere confirmar la limpieza de T5;
- cancelar conserva la fila anterior;
- una respuesta antigua no se duplica al volver atrás;
- el conflicto de versión impide sobrescribir el cambio ajeno.

## 9. Compatibilidad e impactos

| Decisión | Tabla canónica | Formulario tradicional | Entrevista | Lectura/edición | Finalización | Migración |
|---|---|---|---|---|---|---|
| Completitud propuesta | Sin cambio inmediato; usa los cinco campos existentes | Se conserva | Requiere máquina de estados nueva | Debe distinguir vacío de no aplicado | Requiere guardia de completitud | No, salvo aprobación de valores desconocidos |
| `Otro/Otros` | No hay destino confirmado | Se conserva sin inventar captura | Estado pendiente hasta resolver | No hay readback de nombre hoy | Bloquea completitud si es necesario | Sí, sólo si se aprueba nuevo contrato |
| Eficacia + adversos | T3 actual insuficiente | Se conserva sin ampliar valores | Caso combinado bloqueado | Migración conceptual no inferida | No finalizar combinado sin decisión | Probable si se elige A |
| Clasificación | IDs y filas intactos | Se conserva | Mostrar categoría histórica sin afirmación farmacológica nueva | Readback sin reclasificar | No afecta hasta aprobación | No para revisión de texto; sí si se cambian filas |

Riesgos de pérdida:

- guardar `No` por ausencia de selección;
- convertir “no recuerdo” a una respuesta válida;
- guardar eficacia en `descripcion`;
- cambiar una categoría y romper referencias por ID;
- borrar dependientes sin mostrar la consecuencia;
- considerar una fila vacía como respuesta.

## 10. Matriz de decisiones para aprobación

| ID | Problema | Regla actual | Alternativas | Recomendación | Impacto | Requiere aprobación |
|---|---|---|---|---|---|---|
| CL-01 | Completitud T1–T5 | El modelo valida dependencias, pero no declara una regla clínica global | Mantener parcial; exigir T2/T3/T4 con T1=Si; otras reglas | Aprobar la máquina propuesta: T1 obligatorio, T2–T4 obligatorios con Si, T5 con adversos, No confirmado como terminal | Cambia finalización conversacional, no el significado de campos | Sí, responsable clínico |
| CL-02 | Negativas grupales | El catálogo no prueba que una ausencia sea No | Guardar automáticamente; confirmar individualmente; dejar pendiente | Confirmar explícitamente cada negativa identificada; nunca inferirla por omisión | Cambia UX y requiere confirmación antes del POST | Sí, propietario y responsable clínico |
| CL-03 | Nombre `Otro/Otros` | No existe destino canónico demostrado | Reusar M06/descripcion; crear colección; bloquear pendiente | C ahora; diseñar B como contrato futuro | B requiere esquema, lectura, edición y pruebas nuevas | Sí, responsable clínico y arquitectura |
| CL-04 | Eficacia + efectos adversos | T3 excluyente y T5 sólo describe adversos | Ampliar a dimensiones; texto libre; bloquear combinado | Bloquear combinado ahora; aprobar A como destino contractual | Probable cambio de contrato/esquema y compatibilidad histórica | Sí, responsable clínico |
| CL-05 | Clasificación | Se conserva clasificación histórica P09 | Mover filas; cambiar sólo textos; conservar y marcar revisión | Conservar IDs/categorías; revisar candidatos antes de explicar farmacología | Sin impacto técnico inmediato; posible cambio futuro de catálogo | Sí, responsable clínico |
| CL-06 | Finalización | Método tradicional es idempotente, no define requisitos clínicos | Finalizar al recorrer; finalizar por estados terminales | Sólo finalizar con todas las entradas terminales y sin pendientes/contradicciones | Cambia la condición previa a `finalizarModuloPaciente` | Sí, responsable clínico |

## 11. Escenarios de aceptación sintética propuestos

No se ejecutan en esta fase. Requerirán una base sintética allowlisted y un
snapshot/restauración aprobados.

1. T1=`Si`, dosis, resultado y consumo completos → una acción explícita guarda
   y hace readback.
2. T1=`No` sin dependientes → se propone, se confirma y se guarda una negativa.
3. T1=`No` con dependientes → se muestra la limpieza; cancelar no modifica;
   confirmar limpia sólo la fila autorizada.
4. Selección múltiple → ninguna no selección se escribe automáticamente.
5. “No recuerdo la dosis” → queda pendiente, sin cero, `No` ni vacío
   confirmado.
6. T3 adverso → T5 requerido; descripción vacía no permite completar.
7. Eficaz + adverso → flujo bloqueado para revisión mientras no exista contrato
   de dos dimensiones.
8. `Otro/Otros` → estado pendiente, sin escribir en `descripcion`.
9. Edición positiva→negativa → confirmación y limpieza focal sin duplicados.
10. Edición con cambio concurrente → conflicto seguro, sin sobrescritura.
11. Lectura posterior → los cinco campos se recuperan del servidor.
12. Finalización → sólo con seis categorías completas; reintento idempotente.
13. Acceso cruzado entre pacientes/clínicas → rechazo seguro sin escritura.
14. Regresión M03/M04/M05/M08 y cero cambios en formularios tradicionales.

## 12. Riesgos residuales y decisiones solicitadas

### Decisiones que debe aprobar el responsable clínico

1. La obligatoriedad propuesta de T2, T3 y T4 tras T1=`Si`.
2. La obligatoriedad de T5 cuando hay efectos adversos.
3. La semántica de `No` confirmado y la limpieza de dependientes.
4. La representación de `No recuerdo`/desconocido.
5. El contrato para uno o varios `Otro/Otros`.
6. La representación de eficacia y tolerabilidad simultáneas.
7. La clasificación de Olanzapina, Risperidona, Zolpidem, Paracetamol,
   Clorhidrato de Lisina y las categorías señaladas.
8. La regla exacta para finalizar medicamento, categoría y módulo.

### Riesgos que permanecen bloqueados

- No debe implementarse M07 conversacional con campos clínicamente ambiguos.
- No debe ampliarse T3 de forma unilateral.
- No debe usarse `descripcion` como nombre libre.
- No deben moverse categorías ni cambiar IDs.
- No debe ejecutarse la finalización por recorrido visual.
- No deben realizarse escrituras en `bd_clinica` ni sobre el paciente 310.

## 13. Reporte final

```yaml
PHASE: M07_CLINICAL_RULES_CLOSURE_PROPOSAL

OFFICIAL_CATALOG: VERIFIED
THEMES: 6
MEDICATIONS: 46

T1_COMPLETENESS_PROPOSAL: REQUIRED_EXPLICIT_YES_OR_NO
T2_COMPLETENESS_PROPOSAL: REQUIRED_AFTER_T1_YES
T3_COMPLETENESS_PROPOSAL: REQUIRED_AFTER_T1_YES
T4_COMPLETENESS_PROPOSAL: REQUIRED_AFTER_T1_YES
T5_COMPLETENESS_PROPOSAL: REQUIRED_WHEN_T3_ADVERSE
MODULE_FINALIZATION_PROPOSAL: ALL_6_CATEGORIES_TERMINAL_NO_PENDING_OR_CONTRADICTIONS

NEGATIVE_CONFIRMATION_POLICY: EXPLICIT_PER_MEDICATION_OR_EXPLICIT_IDENTIFIED_GROUP
DEPENDENT_DATA_CLEARING_POLICY: SHOW_CONSEQUENCE_CONFIRM_BEFORE_CLEARING
UNKNOWN_ANSWER_POLICY: PENDING_NO_CANONICAL_WRITE

OTHER_MEDICATION_RECOMMENDATION: KEEP_PENDING_NOW_DESIGN_CANONICAL_COLLECTION_LATER
OTHER_MEDICATION_STORAGE_CONTRACT: NOT_AVAILABLE_REQUIRES_NEW_APPROVED_CONTRACT

T3_COMBINED_OUTCOME_RECOMMENDATION: BLOCK_NOW_APPROVE_INDEPENDENT_DIMENSIONS
T3_CANONICAL_REPRESENTATION: CURRENT_SCHEMA_CANNOT_PRESERVE_BOTH_FACTS

CATALOG_CLASSIFICATION_FINDINGS: REVIEW_ANSIOLITICOS_ANTIFLAMATORIOS_AND_OTHER_ENTRIES
CATALOG_CHANGE_RECOMMENDATION: PRESERVE_CURRENT_IDS_UNTIL_CLINICAL_REVIEW

BACKWARD_COMPATIBILITY: PRESERVE_HISTORICAL_VALUES_NO_INFERENCE
SCHEMA_CHANGE_REQUIRED: NOT_NOW_PROBABLE_FOR_OTHER_AND_COMBINED_OUTCOME
CLINICAL_CONTRACT_CHANGE_REQUIRED: YES_FOR_COMPLETENESS_OTHER_AND_COMBINED_OUTCOME

DECISION_MATRIX_COMPLETED: YES
OWNER_APPROVAL_REQUIRED: YES
CLINICAL_APPROVAL_REQUIRED: YES

DOCUMENT_CREATED: docs/PATIENT_INTERVIEW_M07_CLINICAL_RULES_DECISION.md
CLINICAL_CODE_MODIFIED: NO
DATABASE_MODIFIED: NO
ENV_MODIFIED: NO
COMMITS_CREATED: NO

FINAL_STATUS: PROPOSED_PENDING_CLINICAL_APPROVAL
```

Detenerse aquí. La siguiente acción requiere las decisiones clínicas explícitas
anteriores; no se autoriza implementar M07 con supuestos.
