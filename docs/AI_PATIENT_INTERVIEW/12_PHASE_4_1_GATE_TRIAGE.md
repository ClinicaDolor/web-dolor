# Fase 4.1 — Cierre focal de bloqueos y evidencia de navegador

Fecha: 11 de octubre de 2026  
Proyecto: `C:\xampp\htdocs\clinica-dolor`  
Alcance: diagnóstico, pruebas seguras y preparación de aceptación manual.

## 1. Límites respetados

No se modificaron M01–M09, P05–P11, `.env`, esquemas, `bd_clinica` ni datos reales. No se instalaron dependencias, no se añadieron rutas, no se integró Gemini, no se hicieron commits, push ni despliegues. Sólo se creó este documento de auditoría.

Se inspeccionaron el estado Git, los diffs locales, la auditoría Fase 4, la corrección arquitectónica Fase 3.4, los contratos P04–P11 y los archivos afectados por la entrevista. El trabajo local y paralelo se conservó sin revertir ni normalizar cambios.

## 2. P06 — diagnóstico focal sin modificación

### Evidencia

- Contrato fallido: `tests/p06/contract-check.mjs`, línea 46 en el estado auditado.
- Aserción: `assert(!scopedCss.includes('!important'), 'El CSS P06 introduce !important innecesario.')`.
- Condición evaluada: `scopedCss = css.slice(css.indexOf('/* P06-COMPLETE-01'))`; no existe un corte en `/* END P06-COMPLETE-01 */`.
- El bloque P06 termina en `public/assets/css/app.css:18649`.
- Las coincidencias que hacen fallar la aserción se encuentran después del cierre P06, en reglas posteriores de P08 y del resumen clínico, entre ellas las líneas 18687, 18692, 18693, 18846 y 18895.
- `tests/p06/contract-check.mjs` aparece como archivo no versionado en el estado local (`??`); no existe historial Git del contrato con el que pueda probarse su comportamiento previo al chat.

### Conclusión

`P06_CONTRACT_ROOT_CAUSE: SCOPE_BUG_IN_STATIC_TEST`

El fallo está causado por el alcance incompleto del contrato estático. No demuestra que el bloque P06 contenga `!important`, ni que exista una regresión funcional del módulo. El fallo no depende de las rutas, vista o modelo del chat M03: el contrato inspecciona CSS posterior a P06 y la entrevista no añade controles a P06.

`P06_ACTUAL_REGRESSION: NOT_DEMONSTRATED`

No puede afirmarse históricamente que el fallo existiera antes de implementar el chat porque el contrato es no versionado. Sí está demostrado que el mecanismo de fallo es independiente del chat y que el bloque P06 delimitado no contiene las coincidencias reportadas.

`P06_FIX_REQUIRED: YES_FOR_CONTRACT_ONLY`

Propuesta mínima, no aplicada: limitar la cadena inspeccionada en `tests/p06/contract-check.mjs` al intervalo comprendido entre `P06-COMPLETE-01` y `END P06-COMPLETE-01`. No se propone modificar CSS ni producción. Se requiere autorización antes de editar el contrato.

## 3. M05 — Antecedentes Personales Patológicos

Revisión realizada:

- Rutas originales POST: `/paciente/editar-cuestionarioV1-modulo5` y `/paciente/editar-cuestionarioV2-modulo5`, registradas en `app/Config/routes.php` para los grupos tradicionales.
- Controlador: `ModulosController` conserva los métodos M05 y resuelve el ámbito por ruta/autenticación en los flujos revisados.
- Lectura: `BusquedasController` deriva el rol real de la sesión y delega a `AntecedentesPatologicosModel::mostrarPreguntasM5V1/M5V2`.
- Vista y modelo: contrato `node tests/p07/contract-check.mjs` PASS; se comprobaron controles P07, avance V1/V2, recuperación de contexto, filtro por `id_paciente` y ausencia de handlers inline no autorizados.
- PHP lint de modelo y vistas M05: PASS.
- Búsqueda de dependencias de entrevista/chat/Gemini en modelo y vistas M05: sin referencias directas. La única referencia compartida observada en `ModulosController` corresponde al guardado CSRF M03, fuera de los métodos M05.

No se ejecutó CRUD HTTP con escrituras ni sesión de navegador en M05, por la restricción de esta fase. El fixture sintético no se alteró.

`M05_LEGACY_REGRESSION: PASS (estático/rutas; E2E de navegador NOT_VERIFIED)`

Esto confirma ausencia de regresión demostrada en el alcance estático, no una aceptación funcional completa.

## 4. M09 — Evaluación del dolor

Revisión realizada:

- Rutas originales: lectura de imágenes, edición/borrado frente y espalda, edición de evaluación y finalización M09 en `app/Config/routes.php`.
- Modelo: `EvaluacionDolorModel` conserva lectura, validación de campos permitidos, persistencia ligada a `id_paciente`, dependencias de la respuesta de dolor y recuperación de imágenes.
- Vista paciente y vista Doctor: conservan el flujo del mapa, Pointer Events, preguntas y navegación separada.
- Contrato `node tests/p11/contract-check.mjs`: PASS.
- Preflight `php tests/p11/preflight.php --database=clinica_dolor_demo_20261010_final`: PASS, `writes: false`; pacientes 9301 y 9302 pertenecen a clínicas 9101 y 9102 distintas, la base efectiva coincide con la allowlist y no hay finalizaciones M09 sintéticas.
- PHP lint de modelo, vistas y runner P11: PASS.
- Búsqueda de dependencias de entrevista/chat/Gemini en modelo y vistas M09: sin referencias directas. La referencia compartida a `ClinicalSummarySecurity` en `ModulosController` está dentro del endpoint M03, no en M09.

El preflight reporta una fila base M09 para el paciente sintético 9301 con campos clínicos nulos. Esa fila se considera estructura inicializada, no evidencia de respuestas clínicas documentadas. No se realizó escritura para convertirla en contenido.

`M09_LEGACY_REGRESSION: PASS (contrato/preflight/lint; E2E de navegador NOT_VERIFIED)`

## 5. Aislamiento de entrevista y módulos tradicionales

Se confirmó estáticamente y mediante los contratos existentes:

- M03 tradicional continúa sin `p05GuidedMode`, `data-p05-mode`, selector conversacional ni ramas guiadas.
- La entrevista sólo usa `/historia-clinica/entrevista` y `/historia-clinica/entrevista/capturar`, con vista independiente.
- El portal ofrece la entrevista como opción separada y conserva los enlaces de los módulos tradicionales.
- La captura usa `AntecedentesNoPatologicosModel` y `pac_respuestas_paciente_modulo_3`; no se creó catálogo, tabla ni historia paralela.
- El chat no aparece en las vistas M01–M09 revisadas. No llama a Gemini ni a un cuestionario generativo.
- El contrato de separación obtuvo PASS con 15 aserciones; el contrato CSRF M03 obtuvo PASS con 6 aserciones.
- La evidencia E2E sintética previa conserva HTTP 200 para la entrevista, HTTP 403 sin CSRF, recuperación posterior y limpieza final de la base sintética.

`CHAT_ISOLATION: PASS`

`LEGACY_M03_PRESERVATION: PASS (estático y E2E sintético previo; navegador pendiente)`

## 6. Evidencia segura ejecutada en Fase 4.1

| Prueba | Resultado | Alcance/limitación |
|---|---|---|
| `node tests/p07/contract-check.mjs` | PASS | M05 estático; no navegador |
| `node tests/p11/contract-check.mjs` | PASS | M09 estático; no navegador |
| `php tests/p11/preflight.php --database=clinica_dolor_demo_20261010_final` | PASS | Solo lectura; base allowlisted, clínicas separadas, `writes: false` |
| PHP lint M05/M09 y runner P11 | PASS | Sintaxis; no prueba de ejecución visual |
| Contratos de separación y CSRF M03 | PASS previo conservado | Sin escrituras; E2E sintético previo separado |
| `tests/p06/contract-check.mjs` | FAIL | Falso positivo por alcance CSS; no se modificó |

No se ejecutaron `http-crud.php`, preparadores, limpiadores ni servidores con escritura en esta fase. Tampoco se intentó automatizar nuevamente el navegador, porque el entorno no cambió respecto a la limitación documentada.

## 7. Preparación de aceptación manual sintética

La ejecución manual debe usar únicamente `clinica_dolor_demo_20261010_final` y el servidor aislado en `127.0.0.1:8087`.

Preflight sin escritura:

```powershell
php tests/p04/verify-isolation.php --database=clinica_dolor_demo_20261010_final --json
php tests/p11/preflight.php --database=clinica_dolor_demo_20261010_final
```

Inicio del servidor sintético fail-closed:

```powershell
powershell -ExecutionPolicy Bypass -File tests/p05/start-server.ps1 -Port 8087
```

El script debe mostrar la base allowlisted y arrancar el proceso aislado. No copiar ni documentar la clave efímera que el script pueda imprimir. Si el preflight falla o la base activa no coincide exactamente, detenerse.

Recorrido manual pendiente, con credenciales/PIN sintéticos sólo introducidos por el propietario en el navegador:

1. Abrir `http://127.0.0.1:8087/historia-clinica/acceso`.
2. Iniciar sesión con el paciente sintético mediante el formulario normal.
3. Abrir Historia Clínica Tradicional y entrar a M03.
4. Confirmar que M03 sólo muestra el formulario tradicional, sin controles conversacionales.
5. Volver al portal paciente.
6. Abrir `http://127.0.0.1:8087/historia-clinica/entrevista`.
7. Confirmar la interfaz independiente y el modo determinista.
8. Probar cancelar una respuesta, confirmar una respuesta sintética y recargar para comprobar recuperación.
9. Comprobar la misma respuesta desde el formulario tradicional M03.
10. Registrar manualmente `window.innerWidth` y `window.innerHeight` y repetir en 375×667, 768×1024, 1024×768 y 1440×900.
11. Revisar consola, overflow, navegación, estados de carga/error y ausencia de llamadas externas.
12. Si se realizaron escrituras sintéticas autorizadas, ejecutar sólo la limpieza focal correspondiente y repetir el preflight; nunca usar `bd_clinica`.

`BROWSER_MANUAL_ENVIRONMENT: PREPARED; AUTOMATED_BROWSER_NOT_VERIFIED`

`BROWSER_MANUAL_TESTS: PENDING_OWNER_EXECUTION`

No se declara PASS antes de recibir resultados humanos, capturas y métricas de viewport. No se deben incluir credenciales, PIN, cookies, tokens, payloads clínicos ni claves en las evidencias.

## 8. Integridad y decisión de compuerta

La auditoría distingue respuestas persistidas, filas vacías de estructura, datos del fixture y posibles efectos de GET heredados. El preflight M09 confirmó una fila inicializada con campos nulos; no se interpretó como contenido clínico. El riesgo conocido de inicialización de filas vacías en GET tradicional M03 permanece documentado y no fue modificado.

`SYNTHETIC_DATA_INTEGRITY: PASS (preflight solo lectura; no escrituras Fase 4.1)`

`M03_SYNTHETIC_DEMO_READINESS: READY_FOR_MANUAL; NOT_YET_ACCEPTED`

La separación del chat y la evidencia sintética existente permiten preparar la demostración manual, pero siguen pendientes el navegador, la consola y la aceptación humana. P06 mantiene el bloqueo contractual hasta autorizar una corrección focal del test.

`PHASE_5_AI_READINESS: NO-GO`

La fase siguiente no debe ampliar IA hasta cerrar la aceptación manual y decidir el alcance de la corrección P06. La integración externa y el uso de expedientes reales permanecen fuera de autorización.

`REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED`

## 9. Estado solicitado

```text
P06_CONTRACT_ROOT_CAUSE: SCOPE_BUG_IN_STATIC_TEST
P06_ACTUAL_REGRESSION: NOT_DEMONSTRATED
P06_FIX_REQUIRED: YES_FOR_CONTRACT_ONLY

M05_LEGACY_REGRESSION: PASS (static; browser NOT_VERIFIED)
M09_LEGACY_REGRESSION: PASS (contract/preflight; browser NOT_VERIFIED)
REMAINING_MODULE_GAPS: M01/M02/M04/M06/M07/M08 browser and full safe E2E pending; M05/M09 browser pending

CHAT_ISOLATION: PASS
LEGACY_M03_PRESERVATION: PASS (synthetic/static; browser pending)
SYNTHETIC_DATA_INTEGRITY: PASS (read-only preflight)

BROWSER_MANUAL_ENVIRONMENT: PREPARED; AUTOMATED_BROWSER_NOT_VERIFIED
BROWSER_MANUAL_TESTS: PENDING_OWNER_EXECUTION
M03_SYNTHETIC_DEMO_READINESS: READY_FOR_MANUAL; NOT_YET_ACCEPTED

PHASE_5_AI_READINESS: NO-GO
REAL_PATIENT_AI_RELEASE: NOT_AUTHORIZED
```

La auditoría se detiene aquí. Cualquier corrección del contrato P06 requiere autorización explícita antes de modificar archivos.
