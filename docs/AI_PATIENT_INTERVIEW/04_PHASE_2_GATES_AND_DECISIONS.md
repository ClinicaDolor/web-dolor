# Fase 2 — Gates y decisiones M03

Fecha: 11 de octubre de 2026.  
Alcance: cierre documental y preparación; no se implementó la entrevista ni se modificó el guardado.

## M03_PILOT_SCOPE

### Obligatorio para mañana: tema 1 — Tabaquismo

| Campo | Valor verificado |
|---|---|
| ID de tema | `1` |
| Nombre | `Tabaquismo` |
| Preguntas | 1, 2, 3, 4, 5 |
| Tipos | Pregunta 1: `select`; preguntas 2–5: `input` |
| Persistencia | `pac_respuestas_paciente_modulo_3` por `id_pregunta` + `id_paciente` |
| Lectura | `AntecedentesNoPatologicosModel::mostrarPreguntasM3` y endpoint de contenido M03 |
| Actualización | `ModulosController::pacienteEditarCuestionarioM3` → `AntecedentesNoPatologicosModel::editarCuestionarioModulo` |
| Dependencia | `No` en la pregunta principal limpia las demás respuestas del tema y salta al siguiente tema |

Preguntas reales verificadas en el catálogo:

1. ¿Usted fuma cigarros de nicotina? — `select`, valores actuales `Si`/`No`.
2. ¿Desde qué edad empezó a fumar? — `input`.
3. ¿Durante cuánto tiempo ha fumado? — `input`.
4. ¿Cuántos cigarrillos consume en promedio por día? — `input`.
5. Si ya dejó de fumar, ¿hace cuánto tiempo lo suspendió? — `input`.

La regla de salto y limpieza está verificada en el modelo y JavaScript actual. Las preguntas 2–5 no deben convertirse en negaciones cuando la pregunta principal sea `No`.

### Diferido

Temas 2 Alcoholismo, 3 Ejercicio, 4 Estres, 5 Drogas, 6 Dormir y 7 Grupo Sanguineo. Están presentes en el catálogo, pero quedan fuera del MVP.

## M03_CSRF_GATE

**Estado: `PASS` para la corrección técnica; E2E de escritura pendiente.**

Evidencia revisada:

- Ruta POST común: `/paciente/editar-cuestionario-modulo3`.
- `ModulosController::pacienteEditarCuestionarioM3` valida POST, JSON, tipos, contexto de ruta, sesión y `assertPatientAccess`.
- El modelo comprueba que la respuesta pertenezca al paciente y tema, valida el tipo y usa consultas parametrizadas.
- `AuthMiddleware` autentica con cookie JWT y valida pertenencia por paciente/clínica.
- No se encontró en esta ruta un token CSRF de formulario, cabecera o sesión equivalente a `ClinicalSummarySecurity`.

La autenticación y autorización no sustituyen CSRF. La utilidad fue implementada y probada sin escrituras en `bd_clinica`.

### Corrección mínima — `APPROVED` / implementada focalmente

Reutilizar o extraer una utilidad CSRF común del núcleo, emitir un token ligado a la sesión del Paciente, enviarlo desde formulario y entrevista, y validarlo en servidor antes del modelo. El token no debe depender de un ID de paciente controlado por el navegador.

Pruebas necesarias en copia sintética: POST sin token, token inválido, token de otra sesión, token válido con paciente propio y token válido con paciente ajeno. Todos los rechazos deben producir cero escrituras.

## PATIENT_ANSWER_CONFIRMATION_CONTRACT

**Estado: `APPROVED` / implementado en la interfaz paciente.**

Flujo propuesto:

1. Mostrar pregunta real y respuesta persistida, si existe.
2. Recibir texto u opción sin guardar.
3. Clasificar solo como opción permitida, texto válido, vacío o número válido.
4. Mostrar de forma clara el valor exacto que se guardaría.
5. Permitir confirmar o corregir.
6. Ejecutar validadores existentes y CSRF.
7. Llamar al guardado autorizado.
8. Recargar el valor desde el servidor y continuar.

No guardar respuestas parciales; silencio, NULL y vacío no equivalen a `No`. No finalizar automáticamente la historia. Para pacientes mayores se requieren controles grandes, lenguaje literal y botones separados de confirmar/corregir.

## M03_OVERWRITE_POLICY

**Estado: `APPROVED` / control de concurrencia implementado mediante valor leído.**

- Sin respuesta: permitir nueva respuesta tras confirmación.
- Con respuesta previa: mostrar valor existente y ofrecer conservarlo o modificarlo; nunca sobrescribir automáticamente.
- Modificación: mostrar anterior, propuesta y efecto.
- `Sí` → `No` con dependencias: bloquear en entrevista y dirigir al formulario tradicional hasta aprobar trazabilidad e historial, porque el modelo actual vacía las demás respuestas del tema.
- Sin dependencias: permitir solo con confirmación, validación de servidor y CSRF.
- Cambio concurrente: el endpoint no expone versión. El adaptador debe comparar el valor leído con el actual antes de persistir; si difiere, rechazar y pedir recarga.

## M03_SHARED_CAPTURE_ADAPTER

**Estado: `ADAPT` / autorizado e implementado de forma focal; E2E pendiente.**

No se creó un servicio clínico paralelo. La implementación focal reutiliza la vista, el controlador y el modelo existentes; el adaptador mínimo de lectura/confirmación queda embebido en ese flujo y reutilizará:

- `AntecedentesNoPatologicosModel` para consulta y actualización.
- `ModulosController::pacienteEditarCuestionarioM3`, sujeto al gate CSRF.
- `AuthMiddleware::assertPatientAccess`.
- Los catálogos M03 y las validaciones `input`/`select`.
- La transacción actual de limpieza cuando corresponda.

No tendrá SQL propio, no creará tablas y no duplicará reglas clínicas. Entradas: paciente de sesión, tema/pregunta, valor leído, propuesta, confirmación y CSRF. Salidas: pregunta normalizada, estado de respuesta, resultado de persistencia o error seguro.

## Interfaz MVP — `APPROVED` / implementación focal

Pantalla de entrada: “¿Cómo deseas completar tu historia clínica?” con Formulario tradicional y Entrevista Guiada. La entrevista mostraría M03/Tabaquismo, progreso, una pregunta por pantalla, controles grandes, Anterior/Siguiente, Confirmar, Corregir, continuar con formulario tradicional y estado de guardado. `speechSynthesis` sería solo lectura opcional; transcripción no es requisito.

## Matriz de aceptación

| # | Caso | Estado |
|---:|---|---|
| 1 | Paciente autenticado | Pendiente F3 |
| 2 | Sin sesión | Pendiente F3 |
| 3 | Otro expediente | Pendiente F3 |
| 4 | Sin CSRF | Bloqueado |
| 5 | CSRF válido | Bloqueado |
| 6 | Pregunta vacía | Pendiente F3 |
| 7 | Respuesta afirmativa | Pendiente F3 |
| 8 | Respuesta negativa | Pendiente F3 |
| 9 | Cero numérico | Pendiente F3 |
| 10 | Respuesta previa | Pendiente F3 |
| 11 | Cambio confirmado | Pendiente F3 |
| 12 | Cambio cancelado | Pendiente F3 |
| 13 | Sí→No con dependencias | Pendiente F3 |
| 14 | Sobrescritura silenciosa | Pendiente F3 |
| 15 | Cambio de modalidad | Pendiente F3 |
| 16 | Reanudación | Pendiente F3 |
| 17 | Ausencia de duplicados | Pendiente F3 |
| 18 | Reglas condicionales | Pendiente F3 |
| 19 | Error del servidor | Pendiente F3 |
| 20 | Otra clínica | Pendiente F3 |
| 21 | Cero llamadas a Gemini | Pendiente F3 |
| 22 | Otros módulos intactos | Pendiente F3 |

## Decisiones que requieren autorización

| Decisión | Estado |
|---|---|
| Tabaquismo como único tema obligatorio | `APPROVED` |
| Ejercicio y demás temas diferidos | `APPROVED` |
| Resolver CSRF antes de cualquier escritura guiada | `APPROVED` |
| Confirmación explícita | `APPROVED` |
| Bloquear Sí→No destructivo en entrevista | `APPROVED` |
| Adaptador fino sin SQL nuevo ni tablas | `APPROVED` |
| Entrevista determinista, sin Gemini externo | `APPROVED` |

La autorización del propietario aprueba el piloto, el contrato de confirmación, la política conservadora, el adaptador mínimo, la corrección CSRF y la reutilización obligatoria. La aprobación no autoriza producción ni datos reales.

## Estados finales

`PHASE_2_SCOPE: PASS`

`M03_PILOT_TOPICS: VERIFIED`

`M03_CSRF_GATE: PASS`

`PATIENT_ANSWER_CONFIRMATION_CONTRACT: APPROVED`

`M03_OVERWRITE_POLICY: APPROVED`

`SHARED_CAPTURE_ADAPTER: ADAPT`

`PHASE_3_READINESS: CONDITIONAL`

`MVP_DEADLINE_CONFIDENCE: LOW`

La entrega para mañana a las 17:00 es condicional y de baja confianza hasta completar E2E sintético y navegador. La implementación permanece limitada a M03/Tabaquismo.
