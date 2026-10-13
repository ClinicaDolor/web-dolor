# PATIENT-HISTORY-GLOBAL-AUDIT-01

## Auditoría integral de Historia Clínica P04–P11

**Proyecto:** Clínica del Dolor  
**Fecha:** 2026-10-11  
**Alcance:** diagnóstico y planificación; no se implementaron correcciones.

## 1. Alcance, método y límites

Se inspeccionaron la rama, el HEAD, el árbol de trabajo, `routes.php`, los
controladores, modelos y vistas P04–P11, además de:

- `docs/UI_DESIGN_CLOSURE_MASTER.md`
- `docs/testing.md`
- `docs/change-log.md`
- `tests/p04` a `tests/p11`
- el diff actual de P11 y el resultado de `git diff --check`

La evidencia funcional previa se acepta como evidencia histórica únicamente
cuando identifica base, pacientes sintéticos, HTTP y limpieza. No se convirtió
una prueba estática en PASS funcional, ni una captura de fixture en captura de
la aplicación real.

No se ejecutaron escrituras nuevas durante esta auditoría. La evidencia HTTP
previa utiliza exclusivamente `clinica_dolor_demo_20261010_final`, pacientes
9301/9302 y limpieza focalizada. No se utilizó `bd_clinica` ni el paciente 310.

El árbol ya estaba modificado antes de esta auditoría. No se hizo reset, limpieza,
commit, push ni despliegue. El único archivo creado por esta actividad es este
informe.

## 2. Estado del repositorio y comprobaciones ejecutadas

| Comprobación | Resultado | Evidencia |
|---|---|---|
| Rama actual | PASS | `Silvino` |
| HEAD observado | PASS | `1b08d9b4c41080c176fbe4588212863f3a1ac18c` |
| Preservación de cambios locales | PASS | árbol previamente sucio; no se alteró |
| PHP lint de controladores, modelos y vistas P04–P11 | PASS | todos los archivos objetivo inspeccionados |
| Contratos P05, P07, P08, P09, P10 y P11 | PASS | runners estáticos ejecutados en esta auditoría |
| Contrato P06 | FAIL | `tests/p06/contract-check.mjs` detecta `!important` fuera del bloque P06 porque corta el CSS hasta EOF; el bloque P06 en sí no contiene esa regla |
| Audio mock P04–P05 y P07–P10 | PASS | contratos de `speechSynthesis`; no prueba audio audible |
| Contrato P04 audio | PASS | `tests/p04/audio-mock.mjs` |
| `git diff --check` | FAIL | `app/Controllers/ModulosController.php:996`, whitespace añadido en el diff actual de P11 |
| Conversión LF/CRLF | NO VERIFICADO | Git emite advertencias de conversión en archivos previamente modificados; no son el fallo de whitespace |

El fallo de la línea 996 es nuevo respecto de `HEAD` porque aparece como línea
añadida (`+    `) dentro del diff actual de `ModulosController.php`. No se corrigió
por tratarse de una auditoría.

## 3. Matriz global P04–P11

Los estados de esta tabla usan exclusivamente `PASS`, `FAIL`, `NO VERIFICADO` o
`BLOQUEADO`. PASS significa que existe evidencia funcional concreta para el
aspecto indicado; no significa que el módulo esté listo para producción.

| Módulo | Visual | Modal | Audio | Guardado | Navegación | Seguridad | Responsive | Finalización | Estado general |
|---|---|---|---|---|---|---|---|---|---|
| P04 | NO VERIFICADO | NO VERIFICADO | NO VERIFICADO | PASS | PASS | FAIL | NO VERIFICADO | PASS | FAIL |
| P05 | PASS | PASS | NO VERIFICADO | PASS | PASS | FAIL | NO VERIFICADO | PASS | FAIL |
| P06 | PASS | PASS | NO VERIFICADO | PASS | PASS | PASS | NO VERIFICADO | PASS | NO VERIFICADO |
| P07 | NO VERIFICADO | NO VERIFICADO | NO VERIFICADO | PASS | PASS | FAIL | NO VERIFICADO | NO VERIFICADO | FAIL |
| P08 | NO VERIFICADO | NO VERIFICADO | NO VERIFICADO | PASS | PASS | PASS | NO VERIFICADO | PASS | NO VERIFICADO |
| P09 | PASS | PASS | NO VERIFICADO | PASS | PASS | FAIL | NO VERIFICADO | PASS | FAIL |
| P10 | NO VERIFICADO | NO VERIFICADO | NO VERIFICADO | PASS | PASS | FAIL | NO VERIFICADO | PASS | FAIL |
| P11 | PASS | PASS | NO VERIFICADO | PASS | PASS | FAIL | NO VERIFICADO | PASS | FAIL |

### Lectura de la matriz

- P04, P05, P07, P09 y P10 conservan rutas de lectura que pueden crear filas
  clínicas vacías al cargar. El comportamiento está demostrado en el código y,
  para P04/P05, también quedó observado en pruebas sintéticas previas. Por eso
  Seguridad es FAIL aunque los cruces HTTP documentados hayan respondido 403.
- P06, P08 y partes de P11 tienen evidencia HTTP y persistencia propia, pero no
  se validaron los cuatro viewports, el audio audible ni el recorrido global en
  esta auditoría. Su estado general no se eleva a PASS.
- P10 tiene una ruta nueva de paciente con GET sin siembra en la prueba HTTP,
  pero conserva renderizadores legacy con métodos que siembran durante GET.
  El riesgo de la ruta legacy queda separado y evita declarar seguridad global.
- El audio PASS de los runners es únicamente contractual/mock. La reproducción
  audible humana permanece NO VERIFICADA para todos los módulos.

## 4. Recorrido y continuidad clínica

### Identificadores confirmados

| Pantalla | ID clínico |
|---|---:|
| P04 Antecedentes familiares | 2 |
| P05 Antecedentes personales no patológicos | 3 |
| P06 Antecedentes personales quirúrgicos | 4 |
| P07 Antecedentes personales patológicos | 5 |
| P08 Medicación actual | 6 |
| P09 Medicamentos para controlar el dolor | 7 |
| P10 Procedimientos para controlar el dolor | 8 |
| P11 Evaluación del dolor | 9 |

La resolución de `next_url`, los saltos de módulo y las finalizaciones
idempotentes tienen evidencia PASS en P04–P11 según sus runners, con las
excepciones documentadas en la matriz: P07 no tiene finalización real
reconfirmada en su ejecución disponible y varios módulos no tienen dashboard
posterior verificado en navegador.

La continuidad completa P04 → P05 → P06 → P07 → P08 → P09 → P10 → P11 no se
ejecutó como un único recorrido con una sola sesión y todos los catálogos. Por
tanto, el recorrido global queda NO VERIFICADO aunque existan pruebas aisladas
PASS.

## 5. Hallazgos clasificados

### P0 — Exposición potencial de documentos clínicos de P11

**Módulo:** P11  
**Archivos:** `public/assets/images/evaluacion-dolor/frente/`,
`public/assets/images/evaluacion-dolor/espalda/`,
`app/Models/EvaluacionDolorModel.php`  
**Causa confirmada:** permanecen imágenes heredadas bajo `public/assets` con
nombres predecibles por sexo e ID. El modelo legacy construye URL pública y
mantiene métodos de lectura/escritura sobre ese directorio. La nueva ruta
autorizada sirve imágenes desde almacenamiento privado, pero mantiene fallback
legacy para compatibilidad.  
**Evidencia:** inspección de rutas y código; la vista P11 nueva usa
`/clinica/paciente/imagen-evaluacion-dolor/{orientacion}/{idPaciente}` y el
controlador comprueba sesión/propiedad, pero el directorio público sigue
existiendo. No se descargó ningún archivo para demostrar el hallazgo.  
**Impacto:** una URL directa puede eludir el controlador si el servidor sirve el
archivo público. Los nombres permiten enumeración si se conoce o adivina un ID.
  
**Corrección propuesta:** retirar el fallback legacy después de inventariar
consumidores; mover los archivos clínicos fuera de `public`; servirlos sólo por
un controlador autorizado y revisar Apache/DocumentRoot. Añadir prueba directa
anónima/cruzada con archivos sintéticos.  
**Afectación:** P11 Doctor, resúmenes y cualquier PDF o vista que conserve las
URLs antiguas; requiere migración y prueba de compatibilidad.

### P1 — Escrituras clínicas durante GET

**Módulos:** P04, P05, P07 y P09; también existen métodos legacy relacionados
con P10.  
**Archivos confirmados:** `AntecedenteFamiliarModel.php`,
`AntecedentesNoPatologicosModel.php`, `AntecedentesPatologicosModel.php`,
`MedicacionDolorModel.php` y renderizadores legacy de P10.  
**Causa confirmada:** métodos llamados al construir preguntas hacen `INSERT` de
filas vacías cuando no encuentran una respuesta. En P04/P05 el efecto se
observó además durante la preparación/GET sintético.  
**Impacto:** una lectura modifica la historia clínica, genera residuos,
contamina porcentajes y complica auditoría, reintentos y navegación.  
**Corrección propuesta:** separar lectura de preparación; realizar la creación
únicamente en POST explícito, con autorización, idempotencia y limpieza/rollback
de pruebas.  
**Afectación:** cálculo de porcentajes, recuperación, finalización y cualquier
resumen Doctor que consuma las tablas.

### P1 — Diferencia entre rutas nuevas y renderizadores legacy de P11

**Módulo:** P11  
**Archivos:** `EvaluacionDolorModel.php`,
`app/views/historia-clinica-doctor/evaluacion-dolor.php`, rutas de imagen y
endpoints legacy.  
**Causa demostrada:** la vista Doctor dejó de inicializar filas/imágenes en GET
y apunta a la ruta protegida, pero el modelo conserva `cuestionarioModulo9`,
`mostrarPreguntasM9`, `imagenFrenteModulo9`, `imagenEspaldaModulo9` y métodos
legacy que usan SQL concatenado y almacenamiento público.  
**Impacto:** un consumidor no migrado puede reactivar el comportamiento antiguo;
la ausencia de una prueba Doctor autenticada posterior al cambio deja una
regresión potencial sin cerrar.  
**Corrección propuesta:** inventariar consumidores, migrar uno por uno al
servicio autorizado y retirar métodos legacy sólo tras pruebas de regresión.
  
**Afectación:** Doctor, resúmenes clínicos y PDF.

### P1 — Whitespace introducido en el diff actual de P11

**Módulo:** P11 / infraestructura de control de calidad  
**Archivo:** `app/Controllers/ModulosController.php:996`  
**Causa confirmada:** línea añadida con espacios al final. `git diff --check`
falla y la línea aparece dentro del hunk actual de P11.  
**Impacto:** bloquea una entrega limpia y dificulta distinguir cambios nuevos de
históricos. No rompe por sí sola la ejecución PHP.  
**Corrección propuesta:** eliminar sólo el whitespace de esa línea y repetir
`git diff --check`; no reformatear el archivo completo.

### P2 — Cobertura visual y de interacción incompleta

**Módulos:** P04–P11  
**Archivos/evidencia:** `docs/testing.md`, `docs/change-log.md`, runners de
`tests/p04`–`tests/p11`.  
**Causa demostrada:** no existe evidencia reproducible de los cuatro viewports
para todos los módulos. P08 no tuvo navegador; P10 no tuvo captura/console;
P07 no tiene captura autenticada posterior a la restauración del modal; P11
sólo tiene navegador a 1280×720.  
**Impacto:** no permite afirmar paridad completa, ausencia de overflow ni
regresión del shell en la secuencia entera.  
**Corrección propuesta:** ejecutar una campaña de navegador aislado con
viewports reales y guardar capturas por módulo, además de consola y geometría.
  
**Afectación:** todos los módulos y el recorrido P04→P11.

### P2 — Contrato estático de P06 con falso negativo

**Módulo:** P06 / prueba  
**Archivo:** `tests/p06/contract-check.mjs:46-48`  
**Causa confirmada:** `css.slice(css.indexOf('/* P06-COMPLETE-01'))` inspecciona
todo el CSS posterior, no sólo el bloque P06; encuentra `!important` de módulos
posteriores. La inspección directa del bloque P06 no encontró `!important`.  
**Impacto:** el runner reporta FAIL aunque no demuestra un defecto visual P06.
  
**Corrección propuesta:** delimitar el bloque entre los marcadores P06 de inicio
y fin, y repetir el contrato. No cambiar CSS de P06 como respuesta a este falso
negativo.  
**Afectación:** calidad de la evidencia de P06, no el runtime del módulo.

### P3 — Significado clínico de colores pendiente

**Módulo:** P11  
**Archivo:** `app/Models/EvaluacionDolorModel.php`  
**Causa/estado:** el renderer actual presenta azul como «Adormecido» y amarillo
como «Sin sensibilidad», mientras la auditoría no tiene una decisión clínica
independiente que confirme que esa correspondencia histórica es la vigente.
  
**Impacto:** una corrección visual o de accesibilidad podría cambiar el
significado de una marca clínica.  
**Corrección propuesta:** decisión clínica escrita y prueba de persistencia que
compare color, etiqueta y valor almacenado antes de cualquier cambio.  
**Afectación:** paciente, Doctor, resumen y reportes de evaluación del dolor.

## 6. Regresiones entre módulos

1. `public/assets/css/app.css` es compartido y contiene bloques P04–P11 y reglas
   históricas. La paridad de P05/P06 y la prueba contractual de P06 muestran que
   una búsqueda CSS por prefijo sin límite puede cruzar módulos. No se confirmó
   una regresión visual runtime de P06; sí se confirmó una regresión de la prueba.
2. `ModulosController.php` concentra AJAX, finalización y autorización de varios
   módulos. La evidencia HTTP previa muestra 403 cruzado para los runners, pero
   el diff grande de P11 y el whitespace añadido requieren una revisión de
   regresión específica del área Doctor antes de producción.
3. `footer-doctor.php`, `voice-utilities.js` y el shell paciente son compartidos.
   Los contratos de audio pasan, pero ningún mock demuestra reproducción audible
   humana ni permite concluir que los ocho módulos se comportan igual en todos
   los navegadores.
4. La resolución común de `next_url` reduce divergencia y tiene evidencia PASS
   aislada; aún falta un recorrido único P04→P11 con estado persistido.

## 7. Plan de remediación incremental

### Fase A — Bloqueo documental P11

1. Inventariar y retirar/aislar las URLs directas de PNG legacy.
2. Confirmar DocumentRoot y reglas Apache en una instancia sintética.
3. Probar anónimo, paciente propio, paciente cruzado y Doctor autorizado.
4. Repetir P11 y los consumidores Doctor/resumen/PDF sin datos reales.

### Fase B — Lecturas sin efectos secundarios

1. P04 y P05: separar renderer de preparación de filas.
2. P07: separar V1/V2 y catálogo de respuestas vacías.
3. P09: separar catálogo de respuestas.
4. Auditar renderizadores legacy P10 y P11.
5. Probar GET repetido, rollback, conteos y porcentajes antes/después.

### Fase C — Calidad de evidencia

1. Corregir el alcance del contrato CSS de P06.
2. Eliminar el whitespace de `ModulosController.php:996`.
3. Conservar una línea base de `git diff --check` antes de cada módulo.

### Fase D — Recorrido global

1. Preflight único de `clinica_dolor_demo_20261010_final`.
2. Sesión sintética 9301 y pruebas cruzadas 9302.
3. Recorrido real P04→P11 con una sola pulsación, recuperación y
   `next_url`.
4. Viewports reales 1440×900, 1280×720, 768×1024 y 375×667.
5. Consola, foco, teclado, overflow, audio audible y capturas almacenadas.
6. Limpieza focal y comparación con baseline de tablas/archivos.

Cada fase debe detenerse si la base no coincide exactamente con la allowlist o
si la autorización del paciente no puede probarse.

## 8. Recomendación de cierre

**PATIENT_HISTORY_GLOBAL_STATUS: BLOCKED**

**PRODUCTION_READY: NO**

### EVIDENCE_GAPS

- No existe un recorrido único persistido P04→P11 con todos los catálogos y
  módulos en una sola sesión.
- No están verificados los cuatro viewports para todos los módulos.
- Faltan capturas autenticadas exportadas y rutas de archivo para P04–P11; varias
  capturas previas sólo fueron mostradas inline.
- Audio audible humano no verificado; sólo existen mocks/estados de API.
- P07 no tiene finalización real reconfirmada en la evidencia disponible.
- P08 no tiene navegador autenticado disponible.
- P10 no tiene responsive, consola DevTools, modal centrado ni error de red
  observados en navegador.
- P11 no tiene 1440×900, 768×1024, 375×667, prueba táctil independiente ni
  conteo instrumentado de no-op SQL.
- `git diff --check` continúa fallando.

### PENDING_CLINICAL_DECISIONS

- Confirmar semántica clínica azul/amarillo de P11.
- Aprobar si la preparación de filas vacías puede ocurrir sólo por POST o debe
  eliminarse totalmente.
- Definir política de conservación/migración de mapas legacy y consumidores de
  PDF/resumen.
- Confirmar comportamiento clínico de finalización cuando faltan dependencias o
  cuando un módulo devuelve porcentaje 100% sin filas.
- Aprobar el catálogo y reglas de datos sintéticos antes de repetir el recorrido
  global.

## 9. Conclusión

P04–P11 muestran avances funcionales importantes y varias pruebas aisladas PASS,
pero la auditoría no permite aprobar el conjunto. Hay un riesgo crítico de
archivos clínicos públicos en P11, efectos secundarios de lectura en módulos
anteriores, un fallo de whitespace dentro del diff actual y brechas de evidencia
visual/audible. El siguiente paso autorizado es aprobar el plan de remediación,
no cerrar ni desplegar la Historia Clínica.
