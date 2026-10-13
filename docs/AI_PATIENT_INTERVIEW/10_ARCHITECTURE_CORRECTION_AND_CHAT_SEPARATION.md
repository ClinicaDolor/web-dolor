# Corrección arquitectónica — separación del chat conversacional

Fecha: 11 de octubre de 2026.  
Decisión: el piloto M03 se conserva como prueba de catálogo y persistencia, pero la Entrevista Conversacional deja de vivir dentro del formulario tradicional.

## Desviación corregida

Las Fases 2.1–3.4 habían colocado un selector `Formulario tradicional / Entrevista Guiada` dentro de `antecedentes-personales-no-patologicos.php`. Eso mezclaba dos experiencias de captura y hacía que el formulario M03 dejara de ser una representación exclusivamente tradicional.

La corrección mantiene el principio: dos experiencias de captura, un solo expediente clínico.

## Arquitectura resultante

```text
Portal Paciente /historia-clinica
├── Historia Clínica Tradicional
│   └── módulos M01–M09 existentes
└── Entrevista Conversacional
    └── /historia-clinica/entrevista
        └── piloto determinista M03/Tabaquismo
            └── /historia-clinica/entrevista/capturar
                └── modelo M03 y tablas existentes
```

La entrada independiente requiere sesión Paciente, valida `assertPatientAccess`, usa el CSRF HMAC ligado a sesión/paciente y retorna al portal. No se creó una historia paralela, tabla nueva ni catálogo duplicado.

## Matriz de cambios

| Archivo | Funcionalidad original | Cambio de entrevista | Decisión |
|---|---|---|---|
| `app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php` | Formulario M03 tradicional | Tenía selector y ramas guiadas | Corregir: se retiró la mezcla; se conservaron CSRF, navegación y guardado |
| `app/views/paciente/index.php` | Portal y tarjetas M01–M09 | No tenía entrada independiente | Conservar y añadir dos opciones de experiencia |
| `app/Config/routes.php` | Rutas existentes | No había ruta de entrevista | Añadir GET/POST independientes |
| `app/Controllers/PatientInterviewController.php` | No existía | Orquestación de sesión, autorización, vista y captura | Crear focalmente |
| `app/Models/AntecedentesNoPatologicosModel.php` | Catálogo y persistencia M03 | No había lectura conversacional sin siembra | Añadir lectura LEFT JOIN y adaptador de confirmación |
| `app/views/paciente/entrevista.php` | No existía | Interfaz de conversación para M03 | Crear como pantalla independiente |
| `app/Services/ClinicalSummarySecurity.php` | CSRF resumen/captura | Token M03 ya existente | Reutilizar sin cambios |
| `tests/patient-interview/chat-separation-contract.php` | Contratos previos | No existía contrato de separación | Añadir prueba estática focal |

No se modificaron `.env`, `bd_clinica`, Gemini, esquemas, P05–P11 ni contratos clínicos M01–M09.

## Persistencia y seguridad

La pantalla conversacional lee preguntas del catálogo `pac_preguntas_modulo_3` y respuestas de `pac_respuestas_paciente_modulo_3`. La vista no crea filas al abrirse. La fila faltante sólo se prepara al confirmar una respuesta, y después se delega el guardado al modelo M03 existente.

El controlador:

- autentica la vista `historia-clinica`;
- verifica que el paciente pertenece a la sesión;
- exige `X-M03-CSRF` válido;
- limita el piloto al tema 1 desde la ruta de captura;
- rechaza preguntas fuera del tema;
- conserva el valor esperado y retorna 409 ante concurrencia obsoleta;
- bloquea desde la entrevista un `No` que limpiaría respuestas dependientes;
- no llama a Gemini ni a proveedores externos.

## Conversación y alcance

La interfaz presenta mensajes del asistente, respuesta del paciente, confirmación nativa, progreso 1–5, opciones rápidas para `Sí/No`, entrada de texto para preguntas `input`, reanudación desde respuestas persistidas y retorno al portal.

Este piloto es determinista. No se afirma que exista interpretación generativa. La futura integración LLM requerirá un orquestador clínico independiente, salida estructurada validada contra M01–M09 y autorización explícita para el contexto que se envíe a un proveedor.

## Pruebas realizadas

- Lint PHP de controlador, modelo y vistas: PASS.
- Contrato de separación: PASS, 15 aserciones, sin escrituras.
- Contratos P05 de navegación y regresión: PASS.
- Servidor sintético y login normal: PASS HTTP 200.
- GET independiente autenticado: HTTP 200, cinco preguntas M03.
- GET tradicional autenticado: HTTP 200, sin selector conversacional.
- CSRF ausente en captura: HTTP 403.
- Captura confirmada con CSRF: HTTP 200.
- Recarga del chat: respuesta confirmada recuperada desde la tabla existente.
- Limpieza final: `response_count = 0` en la base sintética.

No se declara PASS visual de navegador ni uso con pacientes reales; esa validación requiere la ejecución manual asistida documentada en `09_MANUAL_MVP_ACCEPTANCE.md`.

## Plan de expansión M01–M09

1. Definir por módulo un adaptador de lectura que no siembre respuestas al renderizar.
2. Reutilizar el modelo y contrato de guardado autorizado de cada módulo.
3. Mantener un orquestador que sólo seleccione preguntas existentes y dependencias catalogadas.
4. Añadir pruebas de autorización, CSRF, concurrencia, duplicados y compatibilidad antes de incorporar otro módulo.
5. No habilitar LLM externo sobre expedientes reales sin autorización independiente.

## Estado

`LEGACY_M03_PRESERVATION: PASS`

`LEGACY_M01_M09_REGRESSION: PARTIAL`

`CHAT_UI_SEPARATION: PASS`

`SHARED_CLINICAL_PERSISTENCE: PASS`

`PATIENT_CHAT_ENTRY: PASS`

`SYNTHETIC_CHAT_FLOW: PASS`

`REAL_LLM_CONVERSATION: NOT_IMPLEMENTED`

`CLINICAL_DATA_INTEGRITY: PASS`

`REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED`

La regresión completa M01–M09 y la aceptación visual manual siguen siendo pendientes separados del piloto M03.
