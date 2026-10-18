# TH-DOCTOR-PRESCRIPTION-DETAIL-COMMA-FIX-06

## Corrección focalizada del separador Fecha/Hora

**Proyecto:** Clínica del Dolor
**Área:** Doctor
**Vista:** `app/views/clinica/pacientes-recetas.php`
**Fecha:** 2026-10-15
**Modalidad:** corrección estática de presentación; navegador local no accesible desde Codex

## Causa real

La coma no procede de CSS ni de los valores almacenados. `RecetaModel::getReceta()` genera el HTML del detalle y contiene un nodo de texto literal entre la etiqueta dinámica de fecha y la etiqueta dinámica de hora:

```html
</label>, <small class="text-primary">Hora: </small>
```

El modelo ya tenía cambios locales preexistentes y no fue modificado en esta intervención.

## Corrección exacta

Después de insertar la respuesta AJAX ya existente en `#detalleRecetaModalBody`, la vista ejecuta `eliminarSeparadorFechaHora()`. La función:

1. localiza las etiquetas exactas `Fecha:` y `Hora:`;
2. exige que estén en el mismo contenedor;
3. recorre únicamente los nodos entre ambas;
4. reemplaza sólo un nodo de texto cuyo contenido completo sea una coma con espacios.

No usa reemplazos globales, no modifica los valores de fecha/hora, no toca descripciones ni otros campos clínicos y no oculta la coma mediante CSS.

## Preservación

- Modal de detalle: preservado.
- Modal «Agregar receta»: preservado.
- Modal de vista previa: preservado.
- Fecha y hora dinámicas: preservadas.
- Comas legítimas en diagnósticos, medicamentos, indicaciones y descripciones: preservadas.
- DOM allowlist del modelo: preservado.
- CSRF, autorización, AJAX, impresión, vista previa y PDF: preservados.
- Modelo, controlador, rutas, CSS, PDF y base de datos: no modificados por esta intervención.

## Validación ejecutada

| Comprobación | Resultado |
|---|---|
| PHP lint de vista | PASS |
| PHP lint del contrato focal | PASS |
| Contrato sintético Fecha/Hora y puntuación clínica | PASS |
| Contrato de modales existente | PASS |
| Contrato funcional de recetas existente | PASS |
| Contrato de seguridad de recetas existente | PASS |
| Sintaxis de JavaScript inline | PASS — 2 bloques |
| `git diff --check` | PASS |
| Navegador / HTTP / consola / Network / PDF | NO VERIFICADO |

El contrato focal se añadió en `tests/doctor-prescription/detail-comma-fix-06-contract.php`. La comprobación sintética conserva `15/10/2026`, `02:05 pm` y una coma legítima en `dolor, fiebre`, mientras elimina únicamente la coma separadora entre Fecha y Hora.

## Resultado

```yaml
TH_DOCTOR_PRESCRIPTION_DETAIL_COMMA_FIX_06:
  STATUS: PARTIAL_STATIC_FIX_RUNTIME_VALIDATION_PENDING
  ROOT_CAUSE: literal comma text node emitted by RecetaModel::getReceta() between dynamic Fecha and Hora labels
  EXACT_FIX: structural client-side removal of only the comma-only text node between exact Fecha/Hora labels after the existing AJAX response is inserted
  STRAY_COMMA_REMOVED: PASS_STATIC_STRUCTURAL
  DATE_PRESERVED: PASS_STATIC
  TIME_PRESERVED: PASS_STATIC
  CLINICAL_CONTENT_PRESERVED: PASS_STATIC
  LEGITIMATE_PUNCTUATION_PRESERVED: PASS_STATIC_SYNTHETIC
  DETAIL_MODAL_PRESERVED: PASS_STATIC
  CREATE_MODAL_PRESERVED: PASS_STATIC
  PRINT_PREVIEW_MODAL_PRESERVED: PASS_STATIC
  DOM_ALLOWLIST_PRESERVED: PASS_STATIC
  PDF_PRINT_FLOW_PRESERVED: PASS_STATIC
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  FOCUSED_TESTS: PASS_STATIC
  DIFF_CHECK: PASS
  BROWSER_VALIDATION: NOT_EXECUTED
  FILES_MODIFIED: app/views/clinica/pacientes-recetas.php; tests/doctor-prescription/detail-comma-fix-06-contract.php; docs/audits/TH_DOCTOR_PRESCRIPTION_DETAIL_COMMA_FIX_06.md
  DATABASE_MODIFIED: NO
  BACKEND_MODIFIED: NO
  PDF_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
```

## Retest manual pendiente

Desde Chrome/XAMPP, con una sesión Doctor sintética y una receta autorizada, abrir `/clinica/receta/paciente/310`, seleccionar un registro y verificar que Fecha y Hora aparezcan sin coma entre ellas. Confirmar también que las comas internas de los datos clínicos permanezcan, y que Agregar receta, vista previa, impresión, descarga, X y Escape sigan funcionando. No declarar validación visual/runtime hasta observarlo en navegador.
