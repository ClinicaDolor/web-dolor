# 08 — APIs, integraciones e IA

## API interna

La aplicación responde HTML y JSON mediante Router/HttpMethod; endpoints POST de módulos, entrevista, Clinical Summary, PDF y búsquedas GET. Los contratos dependen de IDs de paciente, módulo, registro y tokens; deben versionarse o probarse con fixtures.

## Proveedor externo

`GeminiProvider` usa cURL, API key de entorno, límites de conexión/tiempo/tamaño y validación estructurada posterior. `ConversationalInterviewService` conserva fallback determinista y normaliza propuestas; el código impone listas permitidas en varias rutas. No se verificaron red, cuotas, reintentos, redacción de PII, observabilidad ni comportamiento cuando la clave falta.

## Entrevista Conversacional

M02–M09 tienen adaptadores y endpoints separados; las rutas de captura revisadas invocan autenticación de Historia, autorización del paciente y CSRF de captura. M09 maneja mapa/base64 y archivos; M08/M09 usan locks SQL. El límite de seguridad exige que el proveedor nunca sea una vía de escritura directa: la persistencia debe quedar tras confirmación y validación.

## Otras integraciones

TCPDF/FPDI generan documentos; Simple-DataTables y librerías JS sirven UI; SpeechSynthesis/STT aparecen en assets/harnesses. No se ejecutó ninguna integración.

## Cierre requerido

Contrato de datos mínimo, consentimiento, minimización, registro de proveedor, límites de entrada/salida, pruebas de rechazo de JSON, fallback, timeouts, manejo de error genérico, no filtración de secretos y plan de desactivación. Estado `PARTIAL_STATIC`.
