# Entrevista Conversacional — M05 y propuesta de alcance M07

Fecha de auditoría: 2026-10-14  
Proyecto: `C:\xampp\htdocs\clinica-dolor`  
Alcance: Entrevista Conversacional; consolidación técnica de M05 e inventario/propuesta contractual de M07.  
Estado vigente: **alcance funcional aprobado con reservas clínicas; M07 no implementado**.

`STATUS: SCOPE_APPROVED_CLINICAL_DECISIONS_PENDING`

## 1. Criterio de seguridad y alcance

Esta revisión fue de lectura para la guardia P04, el catálogo P09 y los
contratos tradicionales existentes. No se modificaron controladores, modelos,
rutas, vistas, contratos clínicos, esquemas, `.env` ni bases de datos. No se
ejecutó ningún servidor nuevo, no se repitió el arnés HTTP bloqueado y no se
realizó ninguna llamada a Gemini o a STT externo.

Los cambios locales y el trabajo paralelo fueron preservados. El único archivo
creado por esta entrega es este documento.

## 2. Cierre técnico focal M05

### 2.1 V1/V2

La separación observada se conserva:

- V1 continúa representada por el adaptador y el contrato de enfermedades de
  M05.
- V2 continúa representada por su adaptador y sus campos de enfermedad,
  tratamiento y navegación específicos.
- La secuencia del adaptador de M05 no mezcla los identificadores de pregunta
  ni los campos canónicos de V1 y V2.
- Los contratos sintéticos previamente ejecutados para lectura, propuesta,
  aclaración, confirmación, lectura posterior, conflicto de versión y
  restauración reportaron PASS. No se repitieron porque esta entrega debía
  investigar el bloqueo de arranque, no reejecutar una prueba ya aprobada.

### 2.2 Causa exacta del bloqueo HTTP

La ejecución focal de solo lectura:

```text
php tests/p04/verify-isolation.php \
  --database=clinica_dolor_browser_20261012 \
  --patient-a=9301 \
  --patient-b=9302 \
  --isolation-mode=cross-clinic \
  --json
```

terminó con código `2` y el rechazo:

```text
La base debe indicarse explícitamente y pertenecer a la allowlist local de P04.
```

La evidencia del código actual es concreta:

- `tests/p04/verify-isolation.php` permite únicamente `bd_clinica` y
  `clinica_dolor_demo_20261010_final`.
- `tests/p04/start-server.ps1` ejecuta esa guarda antes de comprobar el puerto
  o iniciar `php -S`.
- Por tanto, `clinica_dolor_browser_20261012` es rechazada antes de abrir un
  listener, de crear una sesión HTTP o de tocar una fila clínica.
- La comprobación no falló por DNS, HTTP, autorización clínica ni por una
  discrepancia de los pacientes: no alcanzó la conexión PDO.
- El puerto `8099` no tenía listener durante la inspección.

El bloqueo es, por tanto, una incompatibilidad demostrada entre la base usada
por el arnés M05 y la allowlist del servidor P04, no un defecto del contrato
clínico M05.

### 2.3 Segundo desajuste del procedimiento, no ejecutado

`tests/patient-interview/m05-http-acceptance-once.php` fija `8099`, lee
`APP_KEY` desde `.env` para firmar el JWT y usa la base de navegador. En
contraste, `tests/p04/start-server.ps1` exige `P04_TEST_APP_KEY` efímera y la
inyecta sólo al proceso hijo. `tests/patient-interview/start-isolated-browser-server.ps1`
también usa una clave efímera distinta por ejecución.

Aunque se autorizara la base, un arnés que firmara con `.env APP_KEY` no sería
compatible automáticamente con el servidor aislado que usa la clave efímera.
La prueba HTTP M05 no se declaró PASS por esta razón adicional y no se intentó
sortearla.

El arnés actual también contiene una eliminación directa de la respuesta
sintética al final de su flujo. No debe ejecutarse como procedimiento de
aceptación sin una política explícita de snapshot/restauración focal; esa
limpieza no se ejecutó en esta entrega.

### 2.4 Acción mínima requerida para M05

Se requiere una decisión de propietario antes de modificar cualquier script de
prueba:

1. aprobar una base sintética única para M05 HTTP; y
2. hacer que la guardia, el servidor y el arnés reciban explícitamente el mismo
   `database`, `base-url`, `patient IDs` y `P04_TEST_APP_KEY` efímera; y
3. eliminar del arnés cualquier limpieza no focal o sustituirla por el
   procedimiento de snapshot/restauración previamente aprobado.

La corrección no debe ampliar silenciosamente la allowlist, reutilizar
`.env APP_KEY`, iniciar dos servidores ni debilitar el fail-closed. Hasta esa
decisión, la aceptación HTTP de M05 permanece bloqueada.

## 3. Fuente canónica M07/P09

### 3.1 Paquete sanitizado

El paquete local revisado es:

`C:\Users\silvi\Downloads\P09_CATALOGOS_SEGUROS.zip`

Se inspeccionó en memoria, sin extraerlo ni importarlo. Contiene exactamente:

1. `P09_README_SEGURO.md`
2. `pac_temas_modulo_7_CATALOGO_SANITIZADO.sql`
3. `pac_preguntas_modulo_7_CATALOGO_SANITIZADO.sql`
4. `pac_respuestas_paciente_modulo_7_SOLO_ESTRUCTURA.sql`

El README confirma la base sintética prevista y prohíbe importar dumps
originales. El SQL de preguntas contiene 46 filas de catálogo y el SQL de
temas contiene 6 filas. El hash SHA-256 de los dos SQL de catálogo concatenados
durante esta inspección fue:

`15d14ed4372085b9de2fdab9e403fcc193c62e2f9b042c55525e4e4ac964dc02`

El hash se incluye sólo como trazabilidad del paquete, no como credencial ni
como evidencia de datos de pacientes.

### 3.2 Comparación read-only con la base sintética

Se consultó exclusivamente `clinica_dolor_browser_20261012` mediante `SELECT`.
El resultado fue:

- temas en ZIP: `6`; temas en base: `6`; igualdad exacta: `YES`;
- preguntas en ZIP: `46`; preguntas en base: `46`; igualdad exacta: `YES`;
- respuestas de paciente M07 en la base consultada: `0`;
- no se importó el ZIP y no se realizó ninguna escritura.

La base de navegador es, por tanto, una copia sintética útil para inspección del
catálogo, pero la guardia P04 actual no la autoriza para su servidor. El paquete
sanitizado es una fuente disponible para una futura preparación aprobada; no
equivale por sí mismo a autorización para construir el flujo conversacional.

### 3.3 Código tradicional que fija el contrato observable

Las referencias principales son:

- `tests/p09/import-catalog.php`: paquete permitido, seis temas, 46 preguntas,
  estructura de respuestas, pacientes sintéticos y ausencia de respuestas
  importadas.
- `tests/p09/README.md`: procedimiento de preparación y límites del entorno.
- `app/Models/MedicacionDolorModel.php::obtenerModulosM7`: orden de temas por
  `id` ascendente.
- `app/Models/MedicacionDolorModel.php::mostrarPreguntasM7Paciente`: lectura
  por tema, una pregunta por tarjeta, controles `data-p09-*`, dependencias y
  lectura de las respuestas persistidas.
- `app/Models/MedicacionDolorModel.php::editarCuestionarioM7Paciente`:
  validación de paciente, respuesta, pregunta, tema, tipo, opciones,
  dependencias y persistencia canónica.
- `app/Models/MedicacionDolorModel.php::finalizarModuloPaciente`: finalización
  transaccional e idempotente del módulo 7.
- `app/Config/routes.php`: POST
  `/paciente/editar-cuestionario-modulo7` y GET
  `/buscar/contenido-preguntas-modulo-7/{idPaciente}/{idRol}/{idCuestionario}`.
- `app/Controllers/BusquedasController.php::contenidoPreguntasM7`: autentica
  el paciente y selecciona la lectura apropiada.
- `app/Controllers/ModulosController.php::pacienteEditarCuestionarioM7`:
  determina el contexto de vista, valida la sesión y delega al modelo.
- `tests/p09/contract-check.mjs`: contrato estático tradicional.
- `tests/p09/http-crud.php`: aceptación HTTP tradicional sintética, incluyendo
  lectura, aislamiento, persistencia, dependencias, lectura posterior y
  finalización.

## 4. Matriz oficial de las 6 clases y 46 entradas

La tabla `pac_preguntas_modulo_7` no contiene una columna `tipo`, opciones ni
dependencias por fila. Sus columnas verificadas son `id`, `id_tema` y
`pregunta`. Por ello, el texto siguiente es el texto exacto del catálogo
sanitizado y la columna de contrato indica la estructura repetida que el
renderer tradicional aplica a cada medicamento. No se inventa un tipo distinto
para una fila individual.

| ID | ID tema | Tema | Texto exacto del catálogo | Estructura de respuesta documentada | Dependencia | Persistencia canónica | Completitud / lectura / edición |
|---:|---:|---|---|---|---|---|---|
| 1 | 1 | Opioides | Parches de Buprenorfina | T1 respuesta; T2 dosis; T3 resultado; T4 consumo; T5 descripción | T2–T4 sólo con T1=Si; T5 sólo con T3=Tuve efectos adversos | `pac_respuestas_paciente_modulo_7`: `respuesta`, `dosis`, `resultados`, `consumo`, `descripcion` | Reglas de finalización global no codificadas por fila; read/edit tradicionales disponibles |
| 2 | 1 | Opioides | Parches de Fentanilo | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por `id_paciente` + `id_pregunta` | Igual: read/edit tradicional |
| 3 | 1 | Opioides | Oxicodona | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 4 | 1 | Opioides | Morfina | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 5 | 1 | Opioides | Tapentadol | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 6 | 1 | Opioides | Tramadol | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 7 | 1 | Opioides | Hidromorfona | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 8 | 1 | Opioides | Otro | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | El nombre específico de “Otro” no tiene campo separado confirmado; decisión pendiente |
| 9 | 2 | Anticonvulsivos | Pregabalina (Lyrica, etc.) | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 10 | 2 | Anticonvulsivos | Gabapentina | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 11 | 2 | Anticonvulsivos | Carbamazepina | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 12 | 2 | Anticonvulsivos | Oxcarbazepina | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 13 | 2 | Anticonvulsivos | Lamotrigina | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 14 | 2 | Anticonvulsivos | Topiramato | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 15 | 2 | Anticonvulsivos | Otro | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | El nombre específico de “Otro” no tiene campo separado confirmado; decisión pendiente |
| 16 | 3 | Relajantes Musculares | Baclofeno | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 17 | 3 | Relajantes Musculares | Carisoprodol (Dorsal, Nasodol, etc.) | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 18 | 3 | Relajantes Musculares | Ciclobenzaprina (Vuralex, Donkirelax) | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 19 | 3 | Relajantes Musculares | Clorzoxazona (talorel flex) | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 20 | 3 | Relajantes Musculares | Metocarbamol (Dolocam plus, etc) | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 21 | 3 | Relajantes Musculares | Tizanidina (Sirdalud) | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 22 | 3 | Relajantes Musculares | Otro | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | El nombre específico de “Otro” no tiene campo separado confirmado; decisión pendiente |
| 23 | 4 | Antidepresivos | Amitriptilina | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 24 | 4 | Antidepresivos | Escitalopram | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 25 | 4 | Antidepresivos | Duloxetina (Cymbalta) | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 26 | 4 | Antidepresivos | Imipramina | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 27 | 4 | Antidepresivos | Fluoxetina | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 28 | 4 | Antidepresivos | Paroxetina | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 29 | 4 | Antidepresivos | Sertralina | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 30 | 4 | Antidepresivos | Otro | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | El nombre específico de “Otro” no tiene campo separado confirmado; decisión pendiente |
| 31 | 5 | Ansioliticos | Alprazolam | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 32 | 5 | Ansioliticos | Diazepam | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 33 | 5 | Ansioliticos | Lorazepam | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 34 | 5 | Ansioliticos | Olanzapina | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 35 | 5 | Ansioliticos | Risperidona | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 36 | 5 | Ansioliticos | Zolpidem | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 37 | 5 | Ansioliticos | Otro | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | El nombre específico de “Otro” no tiene campo separado confirmado; decisión pendiente |
| 38 | 6 | Antiflamatorios | Celecoxib | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 39 | 6 | Antiflamatorios | Etoricoxib | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 40 | 6 | Antiflamatorios | Ibuprofeno | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 41 | 6 | Antiflamatorios | Naproxeno | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 42 | 6 | Antiflamatorios | Ketorolaco | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 43 | 6 | Antiflamatorios | Diclofenaco | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 44 | 6 | Antiflamatorios | Clorhidrato de Lisina | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 45 | 6 | Antiflamatorios | Paracetamol | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | Igual: read/edit tradicional |
| 46 | 6 | Antiflamatorios | Otros | T1–T5 | T2–T4 con T1=Si; T5 con resultado adverso | Misma fila por pregunta y paciente | El nombre específico de “Otros” no tiene campo separado confirmado; decisión pendiente |

## 5. Contrato de respuesta comprobado

El modelo tradicional permite los siguientes campos por medicamento:

| Tipo | Campo | Control | Valores/longitud observada | Regla observada |
|---:|---|---|---|---|
| T1 | `respuesta` | radio Sí/No | `Si` o `No`; máximo 20 caracteres | siempre disponible |
| T2 | `dosis` | texto | texto; máximo 5000 caracteres | sólo si T1=`Si` |
| T3 | `resultados` | radio | `Funciono`, `No funciono`, `Tuve efectos adversos`; máximo 40 caracteres | sólo si T1=`Si` |
| T4 | `consumo` | radio Sí/No | `Si` o `No`; máximo 20 caracteres | sólo si T1=`Si` |
| T5 | `descripcion` | textarea | texto; máximo 5000 caracteres | sólo si T3=`Tuve efectos adversos` |

Las dependencias son reglas verificadas en
`editarCuestionarioM7Paciente`, no decisiones nuevas de esta propuesta. El
modelo también demuestra dos efectos de actualización que deben mostrarse al
usuario antes de confirmarse en una futura entrevista:

- T1=`No` limpia dosis, resultado, descripción y consumo de esa misma fila.
- T3=`Funciono` o `No funciono` limpia la descripción de efectos adversos.

La acción T1=`No` puede ser candidata a envío directo sólo después de que el
propietario confirme que esa consecuencia es clínicamente aceptable y que la
interfaz la muestra. T1=`Si` no debe finalizar la entrada del medicamento:
abre los campos dependientes. T2–T5 no son candidatos a envío directo sin el
contexto y la validación de su padre.

## 6. Mapeo formulario tradicional → entrevista propuesta

### 6.1 Orden

Orden propuesto, pendiente de aprobación:

1. temas `id` 1 a 6;
2. dentro de cada tema, preguntas `id` ascendente;
3. para cada medicamento, T1 y sólo después las dependencias aplicables;
4. avanzar únicamente después de una propuesta validada, confirmación clínica
   explícita, persistencia y lectura posterior.

No se propone convertir las 46 etiquetas de medicamento en 46 respuestas de
texto libre. Cada una debe conservar controles estructurados equivalentes a la
fuente tradicional.

### 6.2 Presentación conversacional propuesta

- T1: botones/teclas equivalentes a Sí y No, siempre con interpretación o
  selección explícita y confirmación antes de persistir.
- T2: campo de texto para dosis; el dictado nativo, si está habilitado por la
  política local, sólo alimenta el texto editable y nunca guarda por sí mismo.
- T3: opciones cerradas; no aceptar una frase libre que se convierta
  silenciosamente en una opción clínica.
- T4: opciones cerradas Sí/No.
- T5: texto editable sólo después de seleccionar efectos adversos.
- Navegación por teclado: foco visible, radio/acción seleccionable con teclado,
  Enter sólo para una acción que ya tenga una propuesta válida y nunca para
  saltarse una confirmación.
- Pacientes mayores: controles visibles y texto breve; no exigir interpretar
  una respuesta estructurada como texto libre.

### 6.3 Memoria, edición y lectura posterior

La propuesta debe reutilizar el patrón existente de la entrevista:

`Pregunta → respuesta → validación → propuesta → confirmación → persistencia
canónica → lectura posterior → siguiente pregunta`.

La memoria conversacional puede conservar el contexto temporal, pero el valor
clínico debe provenir de la respuesta persistida y no de `localStorage` ni de
una variable de navegador. Editar una pregunta anterior debe volver a validar
paciente, pregunta, tema, tipo, dependencia y versión/concurrencia antes de
actualizar la fila existente. No se deben crear duplicados por volver atrás.

El texto dictado o escrito debe seguir siendo editable antes de interpretar y
antes de confirmar. Una respuesta pendiente, una negativa clínica y un campo
vacío no deben colapsarse en el mismo estado.

## 7. Reglas de completitud propuestas, con límites explícitos

El código actual demuestra las dependencias de visibilidad y de escritura,
pero no aporta una regla clínica formal que permita afirmar que cada campo T2,
T3 y T4 sea obligatorio para finalizar cada medicamento. Por ello:

- `T1=No`: puede considerarse respuesta del medicamento si el propietario
  aprueba la limpieza de dependencias y la confirmación de consecuencias.
- `T1=Si`: no debe considerarse completo mientras la política clínica no defina
  qué campos dependientes son requeridos.
- T5 es obligatorio sólo si se seleccionan efectos adversos, salvo decisión
  clínica distinta.
- Un módulo M07 no debe marcarse completo por haber recorrido las 46 etiquetas;
  debe verificar respuestas y dependencias conforme a una regla aprobada.
- Los datos ausentes deben mostrarse como pendientes, nunca como `No`.
- “Otro/Otros” requiere una decisión sobre cómo documentar el nombre específico,
  porque el esquema observado no presenta un campo de nombre adicional.

Estas son brechas contractuales; no se convierten en reglas de código en esta
fase.

## 8. Matriz de brechas y decisiones

| Área | Reutilizable | Brecha/decisión pendiente |
|---|---|---|
| Catálogo | ZIP sanitizado, tablas de temas/preguntas, 6/46 coincidentes | Confirmar que el paquete queda como fuente aprobada para la entrevista |
| Orden | IDs de tema y pregunta ascendente | Aprobar si ese orden también es el orden clínico conversacional |
| Persistencia | `editarCuestionarioM7Paciente`, columnas canónicas y lectura | Definir adaptador conversacional sin duplicar modelo ni crear otra tabla |
| Validaciones | Tipos 1–5, allowlists, longitudes, paciente/pregunta/tema | Acordar mensajes de propuesta y confirmación para cambios que limpian dependientes |
| Dependencias | T1→T2/T3/T4; T3 adverso→T5 | Aprobar regla formal de completitud por medicamento y módulo |
| “Otro/Otros” | Etiqueta de catálogo existente | Definir dónde se registra el nombre clínico específico |
| Edición | POST tradicional y controles `data-p09-*` | Definir cómo la entrevista volverá a una respuesta anterior sin duplicar |
| Concurrencia | Revalidación de contexto en el modelo; pruebas tradicionales de aislamiento | Definir versión/conflicto específico de M07 conversacional |
| Dictado | Textos T2/T5 pueden ser editables | Aprobar únicamente entrada local; nunca envío automático ni STT externo |
| Finalización | Método tradicional idempotente | Definir criterios conversacionales de completitud antes de llamar a finalización |
| Fuente de código | Modelo, controladores y pruebas P09 | No modificar componentes compartidos hasta aprobar el alcance |

## 9. Aceptación sintética futura

La siguiente entrega, sólo después de aprobar este alcance, debería probar en
una base sintética allowlisted y con datos ficticios:

1. inventario exacto de 6 temas y 46 preguntas frente al ZIP;
2. lectura propia y rechazo cruzado entre clínicas;
3. T1=`No` con confirmación de la consecuencia y sin perder las reglas de
   autorización;
4. T1=`Si` seguido de T2, T3 y T4 en el orden aprobado;
5. T3=`Tuve efectos adversos` seguido de T5;
6. rechazo de T2/T3/T4 sin T1=`Si`;
7. rechazo de T5 sin resultado adverso;
8. persistencia sólo después de confirmación;
9. ausencia de escritura antes de confirmar;
10. lectura posterior de los cinco campos y edición sin duplicados;
11. conflicto de concurrencia controlado;
12. finalización sólo cuando la regla de completitud aprobada se cumpla;
13. regresión de M03, M04, M05 y M08;
14. cero modificaciones de formularios tradicionales, `.env`, `bd_clinica` y
    paciente 310.

El HTTP M05 no puede formar parte de una aceptación PASS hasta corregir el
procedimiento de guardia indicado en la sección 2.4. M07 tampoco puede
declararse implementado por el mero hecho de que el catálogo esté disponible.

## 10. Alcance exacto que requirió aprobación

La siguiente lista fue la base de la decisión del propietario y queda aprobada
como alcance funcional, no como autorización para modificar el producto:

- el orden de temas/preguntas propuesto;
- la reutilización del contrato T1–T5 existente;
- la política de confirmación para T1=`No` y la limpieza de dependencias;
- los campos requeridos para considerar completo un medicamento y el módulo;
- el tratamiento de “Otro/Otros”;
- la edición conversacional y la política de concurrencia;
- el procedimiento seguro para cerrar la brecha HTTP M05.

No se concede por esta aprobación autorización para modificar código, esquema,
formularios tradicionales, datos clínicos, configuración compartida, Gemini,
STT, Apache o producción.

## 11. Estado histórico anterior a la decisión del propietario

```yaml
STATUS: SUPERSEDED_BY_OWNER_SCOPE_APPROVAL
M05_HTTP: BLOCKED_BY_ALLOWLIST_AND_RUNTIME_KEY_PROCEDURE_MISMATCH
M07_IMPLEMENTATION: NOT_STARTED
M07_SOURCE_PACKAGE: AVAILABLE_LOCAL_SANITIZED_ZIP_NOT_IMPORTED
M07_CATALOG_COMPARISON: PASS_READ_ONLY_6_THEMES_46_QUESTIONS
DATABASE_WRITES: 0
REAL_PATIENT_DATA_USED: NO
GEMINI_CALLS: 0
STT_EXTERNAL_CALLS: 0
```

## 12. Addendum — aprobación funcional con reservas clínicas

Fecha de decisión: 2026-10-14.  
Decisión del propietario: **ALCANCE FUNCIONAL APROBADO CON RESERVAS CLÍNICAS**.

### 12.1 Alcance aprobado

Queda aprobado preparar la Entrevista Conversacional M07 usando las seis
categorías oficiales P09, los 46 medicamentos y sus identificadores, agrupados
por categoría, sin modificar el catálogo. La interacción deberá conservar las
estructuras T1–T5, sus dependencias tradicionales y la persistencia individual
por respuesta mediante el mecanismo canónico.

La UX aprobada en alcance incluye selección múltiple accesible cuando la
pregunta lo permita, controles grandes, teclado, textarea editable para campos
de texto, navegación anterior y edición autorizada por servidor. Las respuestas
inequívocas y válidas podrán ser candidatas a envío directo, siempre que pasen
la validación del servidor y no exista ambigüedad ni modificación de información
previamente guardada.

Una selección grupal sólo expresa qué medicamentos fueron seleccionados. No
autoriza guardar automáticamente `No` para los medicamentos no seleccionados.
Cada negativa debe presentarse individualmente y requerir confirmación explícita
del paciente antes de registrarse.

El alcance aprobado no modifica formularios tradicionales, rutas existentes,
modelos compartidos, esquema, catálogo, producción, `.env`, Gemini o STT.

### 12.2 Reservas clínicas que siguen bloqueando implementación

#### A. Completitud

Falta decidir, por cada medicamento con T1=`Si`, si T2 dosis, T3 resultado y
T4 consumo son obligatorios, opcionales o condicionales adicionales. También
falta definir:

- cuándo un medicamento está completo;
- cuándo una categoría está completa;
- cuándo M07 puede finalizarse;
- si T5 es obligatorio siempre que T3 indique efectos adversos;
- cómo se presenta una respuesta incompleta sin convertirla en `No`.

Hasta esa decisión, la implementación no debe finalizar automáticamente un
medicamento, una categoría ni el módulo por el solo hecho de recorrer sus
preguntas.

#### B. `Otro/Otros`

Las entradas `Otro`/`Otros` existen en el catálogo, pero no se verificó un campo
canónico separado para el nombre específico del medicamento. No se reutilizará
`descripcion`, porque ese campo pertenece a efectos adversos, y no se crearán
columnas o tablas. Se requiere decidir si se incorpora un campo contractual,
si se sustituye por una lista oficial aprobada o si se mantiene como entrada sin
nombre adicional.

### 12.3 Caso clínico: medicamento eficaz con efectos adversos

El contrato actual de T3 es una selección única entre `Funciono`, `No funciono`
y `Tuve efectos adversos`. Por ello no representa simultáneamente “fue eficaz” y
“produjo efectos adversos”.

Propuesta concreta para revisión, sin implementarla:

1. mantener el registro actual sin reinterpretar una opción como la otra;
2. presentar el caso como **resultado combinado pendiente de decisión**;
3. evaluar clínicamente una de estas opciones contractuales antes de programar:
   - convertir el resultado en dos ejes canónicos separados: eficacia y efectos
     adversos; o
   - aprobar un valor compuesto explícito para T3 y definir su validación,
     lectura y compatibilidad con datos históricos;
4. si se aprueba la vía de efectos adversos con el contrato actual, registrar
   `Tuve efectos adversos` y exigir T5, dejando documentado que la eficacia no
   queda representada por ese valor.

La opción 4 es compatible con el código actual, pero no debe presentarse como
si conservara ambos hechos clínicos. La decisión clínica debe indicar cuál
representación es válida antes de permitir el cierre del medicamento.

### 12.4 Categorías y entradas para revisión clínica

No se modifica ningún nombre. Se solicita revisar, como posibles puntos de
clasificación y no como errores ya demostrados:

- `Ansioliticos`: contiene Olanzapina, Risperidona y Zolpidem, cuya
  clasificación clínica debe confirmarse para este catálogo.
- `Antiflamatorios`: contiene Paracetamol y Clorhidrato de Lisina; confirmar si
  deben permanecer en esa categoría para el propósito del módulo.
- `Relajantes Musculares`: confirmar la clasificación de todas las marcas y
  denominaciones comerciales incluidas.
- Ortografía y nomenclatura de categorías, incluyendo `Ansioliticos` y
  `Antiflamatorios`, sólo si el responsable clínico decide corregir etiquetas;
  no se corrigen unilateralmente.
- Cada entrada `Otro`/`Otros`, por la ausencia de un nombre específico
  persistible confirmada.

### 12.5 Plan mínimo de implementación, aún no ejecutado

1. **Preflight de contrato:** congelar una copia de lectura del catálogo P09,
   verificar 6/46 y confirmar que la ejecución sea sintética y allowlisted.
2. **Adaptador:** crear un adaptador conversacional M07 que lea los temas y
   preguntas oficiales, exponga T1–T5 y traduzca sólo valores permitidos al
   contrato de `MedicacionDolorModel`; no duplicar las reglas en la vista.
3. **Servicio/orquestador:** reutilizar el flujo actual
   `Pregunta → respuesta → validación → propuesta → confirmación → persistencia
   → readback → siguiente`; los campos reservados por decisión clínica deben
   producir estado pendiente y no una escritura.
4. **Controlador y rutas:** añadir únicamente endpoints propios de la entrevista
   bajo el controlador de entrevista existente, con autenticación, CSRF,
   autorización por paciente/clínica y respuesta estructurada. No reutilizar
   una ruta tradicional cambiando su semántica.
5. **Persistencia individual:** delegar en el mecanismo canónico por una sola
   respuesta/pregunta, con límites transaccionales focales. No guardar negativas
   de grupo automáticamente. No crear duplicados al volver atrás.
6. **Versiones:** transportar el expected-version/snapshot del motor actual y
   rechazar con conflicto seguro si la fila cambió; el token concreto debe
   definirse durante la implementación sin agregar columnas al esquema.
7. **Dependencias:** validar T1 antes de T2–T4 y T3 adverso antes de T5; una
   modificación que limpie dependientes debe mostrar la consecuencia y exigir
   confirmación.
8. **Edición:** cargar la respuesta anterior desde servidor, verificar
   paciente/pregunta/tema/tipo y versión, mostrar propuesta de cambio y escribir
   sólo tras confirmación.
9. **Completitud:** no implementar el cierre hasta recibir la regla clínica de
   medicamento/categoría/módulo.
10. **Pruebas:** ejecutar contratos sintéticos de catálogo, autorización,
    negativas explícitas, respuestas positivas con dependencias, T3 combinado,
    `Otro/Otros`, no-escritura antes de confirmar, readback, edición,
    concurrencia y regresión M03/M04/M05/M08.

### 12.6 Estado de implementación

```yaml
PHASE: M07_CONVERSATIONAL_SCOPE_APPROVAL

OWNER_SCOPE_APPROVAL: CONFIRMED
CATALOG_6_THEMES_46_MEDICATIONS: APPROVED
GROUPED_SELECTION_UX: APPROVED
T1_T5_TRADITIONAL_DEPENDENCIES: PRESERVED
CANONICAL_PERSISTENCE: REQUIRED
EXPLICIT_NEGATIVE_CONFIRMATION: REQUIRED
SERVER_AUTHORIZED_EDITING: REQUIRED

COMPLETENESS_RULES: PENDING_CLINICAL_VALIDATION
OTHER_MEDICATION_STORAGE: PENDING_CLINICAL_VALIDATION
T3_COMBINED_OUTCOME: PENDING_CLINICAL_REVIEW
CATALOG_CLASSIFICATION: PENDING_CLINICAL_REVIEW

IMPLEMENTATION_PLAN: PREPARED
CLINICAL_CODE_MODIFIED: NO
SCHEMA_MODIFIED: NO
DATABASE_MODIFIED: NO
COMMITS_CREATED: NO

FINAL_STATUS: SCOPE_APPROVED_CLINICAL_DECISIONS_PENDING
```
