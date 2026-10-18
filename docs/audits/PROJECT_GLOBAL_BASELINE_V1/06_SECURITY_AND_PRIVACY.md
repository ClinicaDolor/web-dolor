# 06 — Seguridad y privacidad

## Autenticación

JWT HS256 en cookie `CLINICAJWT`; `HttpOnly`, `SameSite=Lax` y `Secure` dependen del esquema observado en `LoginModel`. Password/PIN usa helpers de hash/verificación. No se imprimieron secretos ni valores de `.env`. Falta probar rotación, invalidación, fijación de sesión, expiración efectiva, límites de intentos y recuperación.

## Autorización

La defensa por paciente/clínica existe en middleware y numerosos controladores, pero no puede darse cobertura completa por inspección de llamadas. Persisten superficies que cargan un ID antes de autorizar o no muestran una relación de propietario demostrable: receta externa/PDF externo, descargas, folios COFEPRIS, búsquedas por ID y algunos modelos heredados. P1-AUTH-01 de antecedente familiar aparece corregido estáticamente con condición de registro y paciente, pero la explotación HTTP fue histórica/focalizada y no se reejecutó.

## CSRF

La captura de resumen/P05 usa token específico (`X-M03-CSRF`/campo) validado por `ClinicalSummarySecurity`. En P04/P06–P11 y varios endpoints clínicos no se observó un token CSRF común durante el escaneo. Esto no prueba explotación, pero sí una inconsistencia de defensa que bloquea producción hasta una decisión uniforme y pruebas legítimas.

## SQL e inyección

`Database` configura emulación de prepares desactivada, pero hay consultas `query()` concatenadas en modelos (antecedente familiar, evaluación, COFEPRIS, medicamentos, módulos, recetas externas, laboratorio/notas, procedimientos) y SQL directo en vistas Doctor. Hay también SQL dinámico para nombres de columnas/sets: requiere allowlist estricta y tests. El hallazgo es estático; no se ejecutó una prueba de inyección.

## Archivos y documentos

`DownloadController` hace `realpath`, `basename` y prefijo dentro de `public/storage`, pero no liga el archivo a un paciente/entidad autorizada en el método revisado. `PdfController::pdfReceta` y `pdfCofepris` llaman `assertPatientAccess`; `pdfRecetaExterno` no muestra llamada equivalente. COFEPRIS usa FPDI sobre ruta construida con carpeta/folio. Uploads en laboratorio/COFEPRIS/evaluación requieren MIME, tamaño, extensión, almacenamiento fuera de webroot y autorización probados.

## IA/privacidad

La clave de proveedor viene del entorno y Gemini usa cURL. Deben confirmarse consentimiento, minimización, no envío de identificadores directos, retención/logging, timeouts, fallback y separación de datos sintéticos. La revisión estática no certifica cumplimiento.

## Severidad y estado

Los hallazgos de propiedad documental, CSRF y SQL son **bloqueantes para producción** hasta remediación o aceptación formal con controles compensatorios. No se cambió código en esta fase.
