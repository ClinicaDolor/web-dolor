# TH-SEC-FIX-01 — Seguridad y aislamiento de recetas por clínica

Fecha: 2026-10-12  
Proyecto: Clínica del Dolor  
Estado: **PARTIAL — correcciones internas aplicadas; superficies sin propietario verificable bloqueadas para decisión**

## Alcance y preflight

- Rama: `Silvino`.
- HEAD observado: `1b08d9b4c41080c176fbe4588212863f3a1ac18c`.
- El worktree ya contenía cambios locales de actividades anteriores; se conservaron.
- La configuración `.env` habitual apunta a `bd_clinica`. No se ejecutaron escrituras allí. La inspección inicial de esquema fue sólo lectura.
- Las pruebas sintéticas permitidas utilizaron únicamente `clinica_dolor_demo_20261010_final`, con pacientes 9301/9302 en clínicas 9101/9102.
- La guarda existente de aislamiento y el preflight P11 confirmaron la base sintética y no dejaron escrituras persistentes.

## Contrato de propiedad verificado

La propiedad interna se resuelve por la relación existente:

`receta_medica.id_paciente → pc_paciente.id → pc_paciente.id_clinica`.

La consulta de catálogo interno utiliza `INNER JOIN pc_paciente` y filtra por `pc_paciente.id_clinica`. La tabla sintética `receta_medica_externos` no existe en la base allowlisted y, en el esquema revisado de la base protegida, no ofrece una relación verificable con paciente o clínica. No se inventó una relación ni se ejecutó una migración.

COFEPRIS sí tiene `id_paciente`; su propiedad clínica puede resolverse por la misma relación de paciente. Los archivos PDF sintéticos asociados no existen, por lo que la autorización cruzada se pudo probar antes del acceso al archivo, pero la generación propia quedó NO VERIFICADA.

## Correcciones aplicadas

1. `RecetaModel::mostrarTablaRecetas()` dejó de concatenar `id_paciente` y usa una consulta parametrizada, conservando HTML, orden y acciones.
2. `PdfController::pdfReceta()` ahora autentica como clínica y llama a `assertPatientAccess()` antes de emitir bytes del PDF.
3. `PdfController::pdfCofepris()` aplica la misma compuerta para el paciente propietario antes de resolver el archivo documental.

No se modificaron modelos externos, rutas, esquema, CSS, JavaScript, persistencia clínica ni endpoints de descarga.

## Superficies externas y descargas

Quedaron sin corregir y expresamente abiertas:

- `/receta-externo/{id}` y `/receta-externo/editar/{id}`.
- `/buscar/tabla-recetas-externos` y `/buscar/receta-externo/{id}`.
- POST de inserción/edición de recetas externas.
- `/pdf/receta-externo/{id}`.
- `/descargar/{ruta}/{archivo}`, cuyo contrato no incluye un identificador de paciente/recurso que permita resolver propiedad clínica sin cambiar el contrato.

Estas superficies no deben considerarse seguras por este cambio. Requieren decisión del propietario sobre un vínculo de propiedad o una política fail-closed explícita antes de TH-SEC-FIX-02.

## Pruebas ejecutadas

| Prueba | Resultado | Evidencia / límite |
|---|---|---|
| Base sintética y pacientes separados | PASS | Preflight P11 y guarda P06; 9301/9101 y 9302/9102. |
| Listado interno por clínica | PASS | Lectura de modelo: A devuelve 9401, B devuelve 9402, sin intersección. |
| Tabla interna por paciente | PASS | Render sintético de `tableRecetas` con consulta parametrizada. |
| PDF receta propia | PASS | El controlador produjo salida `%PDF` para 9401 con clínica A. |
| PDF receta cruzada | PASS | 9402 desde clínica A terminó en `No autorizado.` antes de emitir PDF. |
| PDF COFEPRIS cruzado | PASS | Folio 9506 desde clínica A fue rechazado antes de acceder al archivo. |
| PDF COFEPRIS propio | NO VERIFICADO | Los archivos sintéticos `CARPETA-SINTETICA/FOLIO-*` no existen. |
| Receta externa propia/cruzada | NO VERIFICADO | El catálogo/tabla externa no existe en la base sintética y no hay owner verificable. |
| Descarga ligada a recurso clínico | NO VERIFICADO | El endpoint recibe sólo ruta/archivo; no permite resolver propietario sin ampliar contrato. |
| Acceso anónimo HTTP completo | NO VERIFICADO | El runner HTTP built-in devolvió errores genéricos; la compuerta del Router/AuthMiddleware quedó revisada estáticamente. |
| Cambios persistentes de base | PASS | Las pruebas realizadas fueron lecturas o emisión de PDF; no hubo INSERT/UPDATE/DELETE. |
| PHP lint | PASS | `RecetaModel.php` y `PdfController.php`. |
| `git diff --check` focal/global | PASS | Sin errores de whitespace; sólo advertencias históricas LF/CRLF. |

## Matriz de autorización

| Escenario | Antes | Después |
|---|---|---|
| Clínica A lista recetas internas | Exposición dependiente de la consulta | Sólo recetas cuyo paciente pertenece a A |
| Clínica B lista recetas internas | Exposición dependiente de la consulta | Sólo recetas cuyo paciente pertenece a B |
| Clínica A detalle/PDF de receta B | PDF sin compuerta propia en `PdfController` | Rechazo por `assertPatientAccess()` |
| Receta externa | Sin propiedad verificable | Sin cambio; decisión requerida, no PASS |
| COFEPRIS de otra clínica | PDF sin compuerta propia | Rechazo por paciente propietario |
| Descarga documental | Sesión clínica y contención de ruta, sin vínculo al recurso | Riesgo abierto; no se declara aislada |

## Resultado formal

```text
TH_SEC_FIX_01_RECIPE_TENANT_AUTHORIZATION:
- STATUS: PARTIAL
- CLINIC_OWNERSHIP_CONTRACT: CONFIRMED
- INTERNAL_RECIPE_OWNERSHIP: PASS — receta_medica.id_paciente -> pc_paciente.id_clinica
- EXTERNAL_RECIPE_OWNERSHIP: BLOCKED — relación no verificable; decisión requerida
- HISTORICAL_RECORDS_WITHOUT_OWNER: FAIL-CLOSED — no aparecen en INNER JOIN/listado; detalle/PDF sin paciente no autoriza
- SCHEMA_CHANGES: NONE
- ROUTES_INSPECTED: /clinica/recetas, /receta/{idReceta}, /receta/paciente/{idPaciente}, /buscar/tabla-recetas/{idPaciente}, /buscar/receta/{idReceta}, /receta-externo/{id}, /receta-externo/editar/{id}, /buscar/tabla-recetas-externos, /buscar/receta-externo/{id}, /pdf/receta/{idReceta}, /pdf/receta-externo/{id}, /pdf/cofepris/{id}, /descargar/{ruta}/{archivo}
- CONFIRMED_VULNERABILITIES: PDF interno sin autorización en el controlador; PDF COFEPRIS sin autorización; SQL concatenado en tabla interna; propiedad externa ausente; descarga sin vínculo de propietario
- FIXED_ENDPOINTS: /pdf/receta/{idReceta}, /pdf/cofepris/{id}; tabla interna parametrizada
- INTERNAL_RECIPE_AUTHORIZATION: PASS — compuerta reutilizada AuthMiddleware::assertPatientAccess()
- EXTERNAL_RECIPE_AUTHORIZATION: BLOCKED / DECISION_REQUIRED
- RECIPE_LIST_TENANT_ISOLATION: PASS — lectura sintética A=9401, B=9402
- RECIPE_EDIT_TENANT_ISOLATION: PARTIAL — inserción interna conserva guardPatientAccess; prueba HTTP de escritura no ejecutada
- PDF_RECIPE_AUTHORIZATION: PASS — propio genera PDF; cruzado rechazado
- PDF_EXTERNAL_RECIPE_AUTHORIZATION: BLOCKED / DECISION_REQUIRED
- PDF_COFEPRIS_AUTHORIZATION: PARTIAL — cruzado rechazado; propio NO VERIFICADO por archivo sintético ausente
- DOWNLOAD_RESOURCE_AUTHORIZATION: NO VERIFICADO / BLOCKED
- CROSS_CLINIC_A_TO_B: PASS para listado, detalle y PDF interno; superficies externas/download NO VERIFICADAS
- CROSS_CLINIC_B_TO_A: PASS por simetría del filtro/listado; HTTP completo NO VERIFICADO
- ANONYMOUS_ACCESS: NO VERIFICADO en runner HTTP; rutas sensibles conservan guardas
- ROLE_PERMISSION_REGRESSION: NO VERIFICADO dinámicamente
- DOCTOR_D40_REGRESSION: NO VERIFICADO en esta actividad; no se modificó D40
- GET_WITHOUT_WRITES: PASS para las lecturas ejecutadas; no se hicieron GET autenticados sobre superficies de escritura
- SYNTHETIC_CLEANUP: PASS — no hubo escrituras que limpiar
- PHP_LINT: PASS
- GLOBAL_DIFF_CHECK: PASS
- MODIFIED_FILES: app/Models/RecetaModel.php; app/Controllers/PdfController.php; docs/audits/TH_SEC_FIX_01_RECIPE_TENANT_AUTHORIZATION.md; docs/audits/TRADITIONAL_HISTORY_CLOSURE_MASTER.md
- USER_DECISIONS_REQUIRED: propiedad de recetas externas; política fail-closed o contrato de owner; vínculo seguro para descargas; alcance de TH-SEC-FIX-02
- REMAINING_SECURITY_RISKS: ACT-05C-02; recetas externas; descargas y almacenamiento; SQL heredado; COFEPRIS propio no verificado por fixture documental ausente
- PRODUCTION_READY: NO
```

No se inició TH-SEC-FIX-02. No se realizaron commits, despliegues ni escrituras contra `bd_clinica`.
