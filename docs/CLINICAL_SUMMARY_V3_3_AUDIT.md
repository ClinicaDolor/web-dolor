# Auditoría y refinamiento V3.3 del resumen clínico

Fecha: 11/10/2026  
Vista: `/clinica/paciente/{idPaciente}/resumen-clinico`  
Alcance: redacción determinista, fidelidad documental y refinamiento visual focal.

## 1. Hallazgos de V3.2

`CLINICAL_DATA_FIDELITY` permanecía en `PARTIAL` por dos motivos distintos:

1. La matriz de fidelidad no tenía una ejecución exhaustiva de contradicciones, fechas anómalas, duplicados y validación Doctor visual; esas limitaciones no podían convertirse en PASS por el simple hecho de que el código ejecutara correctamente.
2. El resumen ejecutivo presentaba campos etiquetados y valores de base de datos, por ejemplo enfermedad, familiar relacionado y respuesta, en vez de una redacción clínica de apoyo. La información era auditable, pero no estaba sintetizada.

No se demostró una relación incorrecta con catálogos en las nueve fuentes revisadas. Tampoco se modificaron datos originales para corregir inconsistencias.

## 2. Redacción determinista V3.3

Se creó `app/Services/ClinicalNarrativeService.php`. El servicio recibe únicamente los bloques ya recuperados por `ClinicalDetailService` y `ClinicalSupplementService`; no ejecuta consultas nuevas y no modifica `clinical-summary.v1`.

Reglas implementadas:

- Respuestas afirmativas, negativas, NULL y no registradas se distinguen explícitamente.
- Antecedentes familiares no se confunden con diagnósticos del paciente.
- Una respuesta afirmativa sin parentesco se redacta como parentesco no especificado.
- Una respuesta NULL no se convierte en negativa.
- Medicación actual procede exclusivamente de M06.
- M07, M08, recetas históricas y COFEPRIS permanecen diferenciados.
- M09 solo usa dolor, evolución, características y factores documentados; si faltan campos, lo declara como incompleto.
- Las notas subsecuentes se presentan como seguimiento documentado, sin afirmar evolución favorable o desfavorable.
- Los textos se limpian antes de formar narrativa y la interfaz vuelve a escapar el contenido.
- Cada afirmación narrativa conserva referencias `Mxx:Rnn`, `NOTA:Rnn`, `RECETA:Rnn` o `COFEPRIS:Rnn`.

No se generan diagnósticos, causalidad, eficacia terapéutica, estabilidad, complicaciones, urgencias ni recomendaciones.

## 3. Ejecutivo implementado

El controlador añade el bloque `clinical_narrative` a partir de los datos ya autorizados. La vista presenta:

- Síntesis general del caso en un párrafo amplio.
- Antecedentes positivos, negaciones explícitas y pendientes de confirmar.
- Situación actual del dolor.
- Medicación actual documentada.
- Tratamientos para el dolor.
- Procedimientos registrados.
- Prescripciones históricas.
- Registros COFEPRIS.
- Seguimiento clínico reciente.

Los campos completos, preguntas, respuestas, etiquetas y referencias permanecen disponibles en M01–M09.

## 4. Pendientes y línea de tiempo

Los pendientes ahora incluyen mensajes concretos por módulo, por ejemplo `No se encontraron registros de medicación actual en este módulo` y `La evaluación del dolor presenta información incompleta`. Cada entrada enlaza a su módulo. La línea de tiempo V3.2 se conserva, mantiene orden descendente y presenta un estado vacío compacto sin interpretar la ausencia como ausencia de atención.

## 5. Presentación visual

Se mantuvo la estructura V3.2 y la lectura continua. La tarjeta de síntesis general ocupa todo el ancho de la composición narrativa; los bloques secundarios usan dos columnas en escritorio y una en móvil. El texto clínico usa 15 px como base y line-height 1.7. No se añadieron tabs, selectores, acordeones obligatorios ni una nueva arquitectura de Dashboard.

## 6. Pruebas focalizadas

- Contrato de redacción sintética: PASS, 16 aserciones.
- Casos cubiertos: antecedente positivo con familiar, positivo sin familiar, negación, NULL, dosis completa, dosis ausente, receta histórica, dolor incompleto, ausencia de notas, nota documentada, no inferencia de fechas/resultado, módulo vacío, referencias trazables, HTML no confiable y filas técnicas vacías.
- Contrato de cobertura M01–M09 existente: PASS, 11 aserciones.
- Expediente sintético local: narrativa `clinical-narrative.v1`, resumen determinista de 9 secciones y 5 categorías de tratamiento diferenciadas.
- PHP, JavaScript y `git diff --check`: PASS.
- Consultas/escrituras: no se añadieron; el servicio narrativo es de transformación en memoria.

## 7. Validación visual y autenticada

Viewports solicitados: 375×667, 430×932, 768×1024, 1024×768, 1440×900 y 1920×1080.

Resultado: `NO VERIFICADO`. No hubo navegador operativo ni sesión Doctor autorizada en esta ejecución. No se usaron pacientes reales para capturas ni se sustituyó la validación por simulación CSS. Quedan pendientes overflow, contraste computado, teclado, impresión visual, consola y regeneración desde navegador.

## 8. Seguridad y riesgos

Se mantienen autenticación Doctor, aislamiento por clínica/paciente, CSRF, referencias validadas, fallback determinista, consultas de solo lectura, `CLINICAL_AI_EXTERNAL_ENABLED=0` y ausencia del snapshot interno en la respuesta HTTP. No se modificaron `.env`, `bd_clinica`, formularios P05–P11 ni se realizaron commits o despliegues.

Riesgos pendientes: validación visual real, auditoría exhaustiva de contradicciones/fechas en fixtures sintéticos y validación clínica humana de la redacción. Gemini real permanece fuera del flujo de expedientes reales.

## 9. Archivos V3.3

- `app/Services/ClinicalNarrativeService.php`
- `app/Controllers/ClinicalSummaryController.php`
- `public/assets/js/clinical-summary.js`
- `public/assets/css/app.css`
- `tests/clinical-summary/narrative-contract.php`
- `docs/CLINICAL_SUMMARY_V3_3_AUDIT.md`
- `docs/CLINICAL_AI_SUMMARY_PHASE1_REPORT.md`

## 10. Estados

`CLINICAL_DATA_FIDELITY: PARTIAL`

`CLINICAL_NARRATIVE_SUMMARY: PASS` (pruebas deterministas; revisión visual/clínica humana pendiente)

`CLINICAL_EXECUTIVE_SUMMARY_V3_3: PASS` (implementación técnica; navegador NO VERIFICADO)

`CLINICAL_SUMMARY_V3_3_UI: PARTIAL`

`CLINICAL_TIMELINE: PASS` (implementación técnica; navegador NO VERIFICADO)

`REAL_PATIENT_DATA_AUTHORIZATION: NOT_APPROVED`

## Optimización de cobertura documentada — 11/10/2026

La revisión posterior detectó que la narrativa V3.3 todavía convertía filas de cuestionario sin respuesta en frases del tipo `No se dispone de una respuesta...`. Aunque eran técnicamente fieles, producían ruido y no añadían información al informe ejecutivo.

Se corrigió `ClinicalNarrativeService` sin añadir consultas SQL. Cada respuesta se clasifica explícitamente como `affirmation`, `negation`, `numeric`, `text`, `no_response` o `not_applicable`. La clasificación numérica conserva `0`; no se usa `empty()`. Las respuestas NULL, vacías o explícitamente no aplicables quedan fuera del ejecutivo. Las afirmaciones y negaciones documentadas conservan sus referencias.

La lectura detallada filtra únicamente la presentación predeterminada: elimina campos no registrados y filas de cuestionarios sin respuesta, pero no elimina registros persistidos ni altera los formularios originales. Los módulos vacíos o incompletos se representan mediante un estado compacto. La ausencia de reglas de dependencia verificables en los catálogos disponibles impide marcar automáticamente una pregunta condicional como pendiente; por seguridad, una pregunta no respondida se omite hasta que exista una respuesta o una regla de aplicabilidad explícita.

Pruebas actualizadas: contrato narrativo PASS con 21 aserciones; cobertura M01–M09 PASS con 11 aserciones; expediente sintético local en modo determinista con 9 secciones; conteo de pacientes sin cambios; `CLINICAL_AI_EXTERNAL_ENABLED=0`; PHP, JavaScript y `git diff --check` PASS. La validación visual real continúa NO VERIFICADA por ausencia de navegador y sesión Doctor.
