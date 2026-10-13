# Entrevista Inteligente del Paciente — Fase 1

Estado: Fase 2.1 implementada de forma focal; E2E sintético de escritura pendiente.  
Fecha de auditoría: 11 de octubre de 2026.  
Fecha objetivo del MVP: 12 de octubre de 2026, 17:00 America/Mexico_City.

## Objetivo

Preparar una experiencia de entrevista guiada para el paciente que reutilice la historia clínica tradicional, las preguntas de catálogo, las reglas existentes y las tablas clínicas actuales. La entrevista no será un expediente paralelo ni una fuente independiente.

El piloto propuesto se limita a M03, Antecedentes personales no patológicos. El alcance obligatorio seleccionado en Fase 2 es el tema 1, Tabaquismo; Ejercicio queda diferido.

## Modalidades

- **Formulario tradicional:** flujo existente en `app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php`, con variantes Doctor/Paciente.
- **Entrevista guiada:** propuesta de una capa secuencial que lee el mismo catálogo, presenta una pregunta, obtiene texto, muestra una confirmación y llama al guardado clínico existente. En ausencia de autorización para IA externa se denominará **Entrevista Guiada**, no IA generativa.

## Arquitectura propuesta

```text
Formulario tradicional ─┐
                         ├─ autorización + reglas M03 + adaptador mínimo ─> tablas actuales
Entrevista guiada ──────┘
```

La fuente de verdad será `pac_respuestas_paciente_modulo_3`. No se crearán tablas, migraciones ni registros de origen alternativos. La propuesta queda abierta a aprobación; no es un contrato congelado.

## Principios

- Reutilizar antes de crear.
- No enviar expedientes reales a proveedores externos.
- No guardar respuestas clínicas o transcripciones en `localStorage`.
- No convertir catálogos ni IDs en respuestas clínicas.
- No convertir NULL o vacío en `No`.
- Conservar cero numérico cuando sea válido.
- Confirmar la interpretación antes de persistir.
- No sobrescribir silenciosamente una respuesta existente.
- Mantener formularios, rutas y contratos actuales sin cambios durante esta fase.

## Estado actual

M03 dispone de catálogo de temas/preguntas, respuestas inicializadas por paciente, lectura de respuestas, navegación siguiente/anterior, progreso local de navegación y guardado parametrizado por respuesta. La voz existente es lectura mediante `speechSynthesis`; no se verificó una transcripción de voz reutilizable.

La protección CSRF específica fue implementada de forma focal. Permanecen pendientes la prueba E2E autenticada sobre una base sintética aislada y la validación visual real del navegador. El alcance M03 está acotado a Tabaquismo.

## Documentos

- [Auditoría de compatibilidad](01_COMPATIBILITY_AUDIT.md)
- [Arquitectura y contrato inicial](02_ARCHITECTURE_AND_CAPTURE_CONTRACT.md)
- [Plan maestro de entrega](03_MVP_DELIVERY_PLAN.md)
- [Auditoría de procedencia del resumen clínico](../CLINICAL_SUMMARY_DATA_PROVENANCE_AUDIT.md)

## Decisiones

**Verificadas:** arquitectura PHP MVC propia; PDO/MySQL; rutas FastRoute; persistencia M03 en tablas existentes; aislamiento por paciente mediante `AuthMiddleware`; integración Gemini externa separada y no necesaria para el piloto.

**Propuestas:** piloto M03 determinista sobre Tabaquismo; dos modalidades sobre el mismo contrato; confirmación explícita antes de guardar; adaptador pequeño sobre `AntecedentesNoPatologicosModel`.

**Aprobadas por el propietario:** piloto M03/Tabaquismo, confirmación explícita, política conservadora de sobrescritura, adaptador mínimo, corrección CSRF y reutilización de la captura tradicional. **Pendientes:** E2E sintético de escritura, validación visual y autorización de Fase 3 completa.

## Estado de Fase 2

La matriz, los gates y las decisiones están en [04_PHASE_2_GATES_AND_DECISIONS.md](04_PHASE_2_GATES_AND_DECISIONS.md). El gate CSRF técnico está cerrado; la validación E2E sintética y visual permanece pendiente.
## Fase 3.2 — cierre funcional y visual

La autenticación sintética normal responde HTTP 200 en el servidor aislado y los controles funcionales HTTP/integridad de M03 están verificados. El navegador integrado no pudo cargar el servidor local, por lo que la sesión autenticada, la validación responsive y las capturas permanecen `NOT_VERIFIED`. El detalle está en `07_MVP_FINAL_ACCEPTANCE.md`; no se autoriza uso con pacientes reales.

## Fase 3.3 — validación local definitiva

No hay Playwright, Puppeteer ni WebDriver instalados en el proyecto. Chrome y Edge están presentes, pero no se obtuvo una sesión automatizada utilizable contra el servidor local. El procedimiento manual y el estado final están en `08_LOCAL_BROWSER_ACCEPTANCE.md`; el MVP permanece `CONDITIONAL`.

## Fase 3.4 — validación manual asistida

El entorno sintético y los comandos de inicio/limpieza están listos. La ejecución humana debe seguir `09_MANUAL_MVP_ACCEPTANCE.md` y entregar sólo resultados y capturas sintéticas; Codex verificará después la persistencia con SELECT parametrizados. No se declara GO sin esa evidencia.

## Corrección arquitectónica

La Entrevista Conversacional ya no se presenta dentro de M03. El formulario tradicional conserva su ruta y controles originales; el portal ofrece la entrada independiente `/historia-clinica/entrevista`, limitada al piloto determinista M03/Tabaquismo y conectada al mismo catálogo y persistencia. La decisión completa está en `10_ARCHITECTURE_CORRECTION_AND_CHAT_SEPARATION.md`.
