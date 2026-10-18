# 22 — Critical findings evidence V1.3

## Alcance y estado

Esta entrega continúa V1.2; no repite el inventario global. Es una conciliación documental y estática de los dos P0 y ocho P1 conservados en `findings_reconciled.csv`.

**STATUS:** `PARTIAL_STATIC / EVIDENCE_RECONCILED / RUNTIME_UNVERIFIED / NOT_PRODUCTION_READY`

- Línea base V1.2 preservada: sí.
- Código, configuración, base de datos y entorno: sin modificaciones.
- MySQL/PDO, HTTP, navegador, Docker y pruebas mutantes: no ejecutados.
- Ningún hallazgo se cierra por esta entrega.

## Clases de evidencia

| Clase | Significado |
|---|---|
| AUTHORIZED | El propietario permite una actividad o entorno; no prueba que el control exista. |
| IMPLEMENTED | Hay una implementación identificable en el código; no prueba cobertura, ejecución ni autorización efectiva. |
| STATIC_VERIFIED | La relación entre evidencia y conclusión pudo comprobarse leyendo código o matrices. |
| RUNTIME_VERIFIED | Resultado reproducible de ejecución en entorno autorizado. No existe para estos diez hallazgos en V1.3. |

La autorización histórica de `bd_clinica` y del run `P04-AUTH-20261014121758` no se convierte en evidencia runtime de esta auditoría.

## Reconciliación de indicadores V1.2

| Indicador | Reconciliación V1.3 | Interpretación correcta |
|---|---|---|
| Rutas | 136 declaraciones textuales producen 163 filas efectivas | La clausura común se expande bajo Doctor y Paciente. |
| Autenticación | 105 YES, 26 NO, 32 UNVERIFIED | Las 105 son clasificación estática/inferida; no son una prueba HTTP de sesión. |
| CSRF | 12 YES_STATIC + 66 UNVERIFIED/remediación requerida + 85 NOT_APPLICABLE = 163 | Las 66 poblaciones coinciden; `UNVERIFIED` no significa explotación demostrada. |
| SQL | 114 `POTENTIALLY_UNSAFE_OR_MIXED_MODEL_SURFACE` + 27 `UNVERIFIED` | 114 es una superficie de revisión; no son 114 inyecciones confirmadas. |
| GET | 0 escrituras confirmadas por esta lectura estática; 52 potenciales + 9 genéricas no verificadas | El histórico de siembras/efectos no queda resuelto sin traza antes/después. |
| Recursos | 1 `YES_STATIC_GUARD_PARTIAL` + 109 `PARTIAL_STATIC_GUARD` + 8 ausentes/no verificables + 2 `UNVERIFIED` = 120 | El 1/8/2 pertenece a 120 filas de recursos; no a la misma denominación que referencias parciales de ruta. |
| Autenticación no verificada | 32 filas compartidas | La ruta común no declara middleware por sí sola; la guarda debe verificarse en el controlador y en runtime. |

### A. Las 66 CSRF

Sí, son la misma población que V1.2 describió como “por remediar”: 66 filas `UNVERIFIED`. Esto significa que no se demostró validación server-side uniforme. Las 12 filas `YES_STATIC` sólo muestran protección localizada; las 85 restantes son GET o no aplican al control CSRF de POST. La corrección propuesta debe revisar cada POST/AJAX que cambie estado y ejecutar un negativo sin token.

### B. Las 114 superficies SQL

La clasificación procede de la lectura de modelos, controladores y vistas que invocan `query()`, SQL heredado, concatenación o identificadores dinámicos. Es una señal de superficie potencialmente mixta y difícil de revisar; no prueba que cada ruta sea explotable ni que cada parámetro sea controlable por el usuario. El cierre exige revisión de flujo/taint y regresión aislada con consultas parametrizadas.

### C. GET_WRITE_SIDE_EFFECTS

El valor reconciliado es cero escrituras confirmadas en la lectura estática V1.3, no cero riesgo. V1.2 conservó 52 filas `POTENTIAL_SIDE_EFFECT_UNVERIFIED`, 9 `UNVERIFIED` y 24 `READ_ONLY_STATIC_NOT_RUNTIME_TESTED`; los relatos históricos de siembra/preparación durante GET no pudieron conciliarse con una traza de base. Por eso `CLIN-GET-006` permanece abierto.

### D. Conjunto 1/8/2 de autorización por recurso

Es el resumen de la matriz `resource_authorization_matrix_v1_2.csv` de 120 filas: una guarda estática parcial (`YES_STATIC_GUARD_PARTIAL`), ocho filas sin evidencia suficiente (dos `MISSING_STATIC` y seis `MISSING_OR_UNVERIFIED`) y dos `UNVERIFIED`. Las 109 filas `PARTIAL_STATIC_GUARD` son evidencia parcial, no autorización completa. La referencia de V1.2 a 23 recursos/rutas con alguna guarda usa un denominador de ruta distinto y no se sustituye por el resumen de recursos.

### E. Las 32 rutas de autenticación no verificada

La matriz las marca así porque son rutas compartidas sin middleware explícito en la declaración. El grupo incluye `/clinica/pacientes`, las búsquedas `/buscar/*`, contenido clínico por paciente/rol, `/pdf/*`, `/descargar/*`, `/cerrar-sesion` y `/buscar/web`. La lectura de controladores sugiere guardas para varias búsquedas y recursos, mientras `/buscar/web` es pública y el cierre de sesión es una operación de sesión; esa inferencia no reemplaza la prueba del contrato. Cada fila debe recibir una decisión explícita: pública, sesión autenticada, rol Doctor/Administrador, o autorización por recurso.

### F. Rutas “protegidas” por inferencia

Las 105 filas `YES` se derivan del prefijo efectivo y/o guardas encontradas, pero las declaraciones de rutas no expresan middleware completo. Por tanto son `STATIC_VERIFIED` sólo en el sentido de que existe una señal de protección, no `RUNTIME_VERIFIED`; la autorización por paciente, clínica, recurso y rol permanece separada.

## P0 — revisión individual

### SEC-DOC-001 — PDF de receta externa

- **Evidencia:** `app/Controllers/PdfController.php:239-242`, `POST /pdf/receta-externo/{id}`.
- **Causa estática:** el manejador carga una receta externa por identificador y en el tramo focal no se demuestra una aserción de propiedad por paciente/clínica antes de renderizar.
- **Control:** no se afirma que la ruta sea anónima; la autenticación efectiva del método y su ownership deben probarse como contrato completo.
- **Impacto:** posible exposición de un documento/receta de otro paciente o clínica.
- **Estado:** ausencia de evidencia de ownership confirmada estáticamente; explotación y caso legítimo no ejecutados.
- **Contención:** fail-closed para la sección externa o denegación si no puede resolverse propietario.
- **Dependencias:** modelo histórico de propiedad/traslado, política PDF y fixture A/B.
- **Cierre:** caso autorizado produce PDF; ID de paciente/clínica ajena, inexistente y sin propietario produce denegación sin datos ni archivo.

### SEC-DOC-002 — Catálogo de recetas externas

- **Evidencia:** `app/Models/RecetaExternoModel.php:192`, listados/búsquedas de recetas externas.
- **Causa estática:** `SELECT` global sobre `receta_medica_externos` ordenado por fecha; no se demuestra relación con propietario clínico verificable.
- **Control:** algunas rutas llamadoras pueden tener guardas de sesión, pero una sesión no demuestra propiedad del registro.
- **Impacto:** listado o detalle cruzado entre clínicas.
- **Estado:** propietario clínico no demostrable en el esquema/flujo revisado; no se ejecutó exposición A/B.
- **Contención:** retirar o bloquear la sección hasta resolver ownership; no tratarla como catálogo global por defecto.
- **Dependencias:** decisión de propiedad histórica, transferencias y registros sin propietario.
- **Cierre:** cada fila retornada tiene propietario verificable y prueba negativa entre clínicas, o el endpoint falla cerrado.

Ambos P0 conservan su prioridad porque el control ausente/no demostrado es visible en el código. El runtime requerido determina exposición efectiva y cierre, no la existencia del hallazgo estático.

## P1 — revisión individual

| ID | Evidencia y causa raíz | Estado estático | Runtime requerido para cerrar |
|---|---|---|---|
| SEC-FILE-003 | `DownloadController.php:11-42`; confinamiento de path sin ownership documental ni allowlist de tipo demostrados. | Gap de control confirmado; no se declara descarga cruzada explotada. | Fixtures de almacenamiento y matriz propio/ajeno/rol/tipo. |
| SEC-CSRF-004 | `PatientInterviewController.php`, `ClinicalSummarySecurity.php`, `routes.php:42-90`; protección explícita localizada, cobertura uniforme no demostrada. | Gap de cobertura estática. | Negativos sin token y con token inválido en cada POST/AJAX. |
| SEC-SQL-005 | `app/Models/*.php`, `app/views/clinica/*.php`; SQL heredado/concatenado/dinámico. | Superficie de riesgo estática, sin taint/explotación por ruta concluida. | Revisión focal de taint y regresión de comportamiento aislada. |
| CLIN-GET-006 | `PacienteModulosModelo.php` y registros históricos; conflicto entre narrativas de siembra y lectura actual. | Riesgo de integridad no resuelto, no escritura actual confirmada. | Traza de base antes/después, repetición e idempotencia por módulo. |
| SEC-AUTH-007 | `routes.php:195-228` y controladores; endpoints por ID sin matriz completa de ownership. | Brecha de evidencia/definición de autorización confirmada. | A/B de paciente, clínica y rol en lectura/escritura/eliminación. |
| AI-PRIV-008 | `GeminiProvider.php:44-45,111-112`, `PatientInterviewController.php:262`; proveedor externo/trazas sin prueba de minimización, consentimiento o retención. | Riesgo de privacidad estático; no se probó fuga. | Proveedor mock, payload sintético, fallo cerrado, logs y retención. |
| QA-RUNTIME-010 | Alcance V1.1/V1.2; no hubo MySQL/PDO/HTTP/browser/Docker. | Brecha de evidencia confirmada, no vulnerabilidad de producto por sí sola. | Suites runtime reproducibles y artefactos firmados por entorno autorizado. |
| TEST-018 | `docs/audits/PROJECT_GLOBAL_BASELINE_V1`, `tests/p04/*`; `P04-AUTH-20261014121758` es histórico. | Evidencia no vigente confirmada; no se cuenta como PASS V1.3. | Salida actual con timestamp, DB diff y autorización A/B. |

Los cinco primeros/sextos P1 anteriores tienen una hipótesis de control estático concreta; `CLIN-GET-006`, `QA-RUNTIME-010` y `TEST-018` son especialmente brechas de integridad/evidencia que requieren runtime antes de elevar o cerrar impacto.

## P04 histórico

`P04-AUTH-20261014121758` con pacientes 10 y 28 se conserva como resultado reportado por el propietario para aislamiento intra-clínico. No fue ejecutado durante V1.3, no demuestra otros endpoints, no demuestra separación inter-clínica y no extiende evidencia a recetas externas, PDF, descargas, COFEPRIS ni P05–P11.

El estado de CSRF se mantiene separado: existencia de `ClinicalSummarySecurity` y controles localizados es `IMPLEMENTED/STATIC_VERIFIED`; no se afirma `RUNTIME_VERIFIED` para todos los módulos.

## Conclusión de la compuerta

Los diez hallazgos P0/P1 tienen una fila con evidencia estática trazable y criterio de cierre. Ninguno tiene evidencia runtime suficiente para cierre. Los próximos pasos deben aprobar primero ownership/contención de recursos documentales y después ejecutar lotes pequeños en ambiente sintético, sin convertir esta auditoría en una autorización de producción.
