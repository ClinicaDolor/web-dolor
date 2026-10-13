# Plan maestro de entrega del MVP

Fecha límite declarada: 12 de octubre de 2026, 17:00, America/Mexico_City.  
Estado al cierre de Fase 2.1: implementación focal iniciada; E2E sintético pendiente.

## Seguimiento de Fase 1

| ID | Actividad | Prioridad | Tiempo estimado | Estado | Evidencia |
|---|---|---:|---:|---|---|
| AI-P01 | Auditoría de arquitectura | P0 | 20 min | PASS | Este documento y matriz de rutas |
| AI-P02 | Auditoría de datos M01–M09 | P0 | 20 min | PARTIAL | `01_COMPATIBILITY_AUDIT.md`; esquema leído sin filas |
| AI-P03 | Revisión del guardado actual | P0 | 20 min | PASS | Flujo Controller → Model + CSRF focal |
| AI-P04 | Diseño de arquitectura compartida | P0 | 20 min | PROPUESTO | `02_ARCHITECTURE_AND_CAPTURE_CONTRACT.md` |
| AI-P05 | Inventario de reutilización | P0 | 15 min | PARTIAL | Sección de componentes y riesgos |
| AI-P06 | Documentación inicial | P0 | 25 min | PASS | README y documentos iniciales |
| AI-P07 | Selección de alcance M03 | P0 | 15 min | PASS documental | Tema 1 Tabaquismo; temas 2–7 diferidos |
| AI-P08 | Gate CSRF M03 | P0 | 15 min | PASS técnico; E2E pendiente | Prueba aislada de 6 aserciones |
| AI-P09 | Contratos de confirmación/sobrescritura | P0 | 20 min | APPROVED | Autorización del propietario; implementación focal |
| AI-P10 | Diseño del adaptador | P0 | 10 min | ADAPT | Autorizado; reutiliza modelo/controlador |
| AI-P11 | Matriz de aceptación | P0 | 10 min | PARTIAL | 22 casos; E2E sintético pendiente |

## Fases restantes

| Fase | Horario | Objetivo | Dependencias | Criterio de salida |
|---|---|---|---|---|
| 2 | 12/10 09:00–10:00 | Cerrar alcance M03 y contrato | aprobación humana, CSRF, temas | GO técnico documentado |
| 3 | 12/10 10:00–13:00 | Implementar selector y entrevista guiada | contrato aprobado, adaptador | flujo sintético de lectura/confirmación/guardado |
| 4 | 12/10 13:00–15:30 | Integrar y probar | fixture sintético, navegador | seguridad y persistencia sintéticas verificadas; navegador autenticado pendiente |
| 5 | 12/10 15:30–16:30 | Corregir defectos | resultados Fase 4 | sin bloqueos P0 abiertos |
| 6 | 12/10 16:30–17:00 | Entregar MVP | evidencia completa | GO/NO-GO explícito; sin despliegue automático |

## Ruta crítica

1. Aprobar temas y contrato.
2. Resolver CSRF del guardado M03.
3. Implementar adaptador sin duplicar reglas.
4. Probar aislamiento, actualización idempotente y consecuencias de `No`.
5. Validar cambio de modalidad y reanudación con fixture sintético.

El calendario es de alto riesgo: deja poco margen para corregir una incompatibilidad del modelo, un defecto de autorización o una falla de navegador.

## Pruebas requeridas para Fase 4

- Paciente autenticado accede solo a su expediente.
- Paciente no autenticado es rechazado.
- ID de otro paciente es rechazado.
- Doctor no puede usar el flujo Paciente por sustitución de ruta.
- Lectura de respuestas existentes.
- Confirmación antes de guardar.
- `Sí`, `No`, NULL/vacío y cero cuando corresponda.
- Tema condicional: confirmación y limpieza esperada.
- No hay duplicados ni cambios en otros módulos.
- Cambio tradicional ↔ guiado conserva respuestas persistidas.
- Reapertura recupera servidor, no un borrador clínico del navegador.
- Errores no exponen contenido clínico.
- Responsive básico y teclado.
- No hay llamadas externas.

## Cierre de Fase 3.1

La prueba HTTP sintética y la integridad de base pasaron en `clinica_dolor_demo_20261010_final`. La autenticación normal sintética respondió HTTP 200, pero el navegador integrado no pudo cargar el servidor local para completar el recorrido autenticado; por tanto, la evidencia visual responsive y la confirmación observada permanecen pendientes. El MVP conserva estado condicional. El detalle está en `06_MVP_E2E_ACCEPTANCE.md` y `07_MVP_FINAL_ACCEPTANCE.md`.

## Riesgos priorizados

| Riesgo | Nivel | Impacto | Mitigación | Actividad | Estado / decisión |
|---|---|---|---|---|---|
| Falta CSRF específico en guardado M03 | Alto | escritura no protegida frente a cross-site | resolver antes de implementar | AI-P03 | BLOQUEO P0 |
| Duplicar reglas en JS | Alto | inconsistencias clínicas | adaptador a modelo actual | AI-P04 | Mitigable |
| Filas inicializadas vacías | Alto | falsas respuestas | clasificar por valor, no por existencia de fila | AI-P02 | Conocido |
| `No` limpia el tema | Alto | pérdida de respuestas esperadas | confirmación previa y prueba | AI-P04/F4 | Pendiente |
| Temas condicionales no totalmente catalogados | Alto | avance o limpieza incorrecta | seleccionar solo temas verificados | F2 | Pendiente |
| Cambios paralelos en formularios | Alto | conflictos y regresiones | no tocar P05–P11; revisar diff antes de integrar | F2–F5 | Activo |
| Voz no equivalente a transcripción | Medio | alcance reducido | texto obligatorio; voz solo lectura | F3 | Aceptable |
| Persistencia local de borrador clínico | Alto | exposición de datos | no almacenar respuestas/transcripciones en navegador | F3 | Mitigable |
| Tiempo de entrega limitado | Alto | MVP incompleto | limitar a uno/dos temas y fallback determinista | plan alterno | Activo |

## Plan alternativo

Si no se autoriza o no se puede verificar un motor externo, entregar una **Entrevista Guiada determinista**. Si no se verifica la persistencia compartida o CSRF, entregar únicamente una demostración aislada de lectura/confirmación, con NO-GO para guardado clínico.

## Estados de Fase 1

`PHASE_1_INVENTORY: PASS`

`LEGACY_DATABASE_COMPATIBILITY: PARTIAL`

`EXISTING_COMPONENT_REUSE: PARTIAL`

`SHARED_CAPTURE_CONTRACT: APPROVED`

`M03_PILOT_FEASIBILITY: CONDITIONAL`

`DOCUMENTATION_READY: YES`

`MVP_DELIVERY_RISK: HIGH — login HTTP PASS; navegador autenticado y visual NOT VERIFIED`

Fase 3.3: no se encontró Playwright/Puppeteer/WebDriver instalado. Chrome y Edge existen, pero el navegador integrado y la ejecución headless disponible no produjeron un flujo autenticado utilizable. Se mantiene el procedimiento manual en `08_LOCAL_BROWSER_ACCEPTANCE.md` y no se eleva el MVP a GO.

Fase 3.4: entorno sintético READY y guía manual preparada en `09_MANUAL_MVP_ACCEPTANCE.md`. La ejecución humana y la posterior verificación SELECT siguen pendientes; el estado continúa CONDITIONAL.

Corrección arquitectónica Fase 3.4: M03 volvió a ser formulario tradicional; la entrevista determinista se ubicó en `/historia-clinica/entrevista` con persistencia compartida y sin tablas nuevas. El piloto M03 queda técnicamente integrado; la aceptación visual manual permanece separada.

La siguiente orden recomendada es: **“Ejecuta la validación E2E del MVP M03/Tabaquismo únicamente sobre una base sintética aislada y, si pasa, completa la validación visual del navegador; no uses `bd_clinica`.”**
