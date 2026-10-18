# TH-DOCTOR-SUBSEQUENT-NOTE-CREATE-UI-REMEDIATION-09A

## Estado

**STATUS: PARTIAL — CORRECCIÓN ESTÁTICA IMPLEMENTADA; VALIDACIÓN VISUAL Y RUNTIME PENDIENTES**

La intervención se limitó a la vista de creación/edición de Nota subsecuente, su parcial, el CSS scoped, el contrato estático focalizado y esta documentación. No se modificaron controladores, modelos, rutas, servicios, SQL ni datos clínicos.

## Causa del checkbox desalineado

El control real usa Bootstrap `.form-check`, cuyo `form-check-input` aplica posicionamiento flotante y margen izquierdo negativo para compensar el padding interno convencional de `.form-check`. La tarjeta añadía su propio padding, pero no cancelaba ese contrato heredado. El resultado podía colocar la casilla visualmente fuera o demasiado cerca del borde, especialmente junto al contenedor alineado a la derecha.

La corrección no usa una casilla falsa: conserva el `<input type="checkbox" id="IncluirReceta">` y su `<label for="IncluirReceta">`.

## Estructura y espaciado corregidos

- El selector de receta ahora es una cuadrícula scoped de dos columnas: casilla y contenido.
- La casilla usa `position: static`, `float: none`, `margin: .15rem 0 0` y tamaño de `1.15rem`.
- El label y la ayuda ocupan la segunda columna y quedan alineados entre sí.
- Padding nuevo del selector: `.9rem 1rem` en escritorio y `.85rem .9rem` en móvil.
- Se eliminó el efecto visual del margen negativo heredado; no se añadieron márgenes negativos nuevos.
- La sección de receta conserva separación de `1rem` entre grupos y padding superior de `1.25rem`.
- El encabezado mantiene separación amplia y pasa a disposición vertical en móvil.

## Visibilidad de campos de receta

Los campos Diagnóstico, Temperatura y Medicamento permanecen en el DOM y conservan sus valores, pero se muestran sólo cuando `IncluirReceta` está seleccionado. El estado inicial se deriva de la receta existente en edición. El cambio actualiza `hidden` y `aria-hidden`; no añade `required`, no genera recetas vacías y no modifica el payload del backend.

La nota puede guardarse sin receta porque `AgregarNota()` mantiene `incluirReceta: false` y sólo aplica las validaciones exclusivas de receta cuando el checkbox está marcado.

## Botones y estados

La causa estática del texto incorrecto en estados morados era la ausencia de una regla scoped que fijara el color de los descendientes y SVG cuando Bootstrap o reglas `.btn` más generales intervenían en hover/focus. Se añadieron reglas específicas para:

- Guardar nota subsecuente: `.btn-primary`.
- Cancelar: `.btn-outline-primary`.
- Agregar laboratorio: `.doctor-note-lab-card .btn-outline-primary`.
- Regreso y distribución: foco visible conservado en `.doctor-page-hero-action` y `.doctor-note-toggle`.

En los estados con fondo morado se fuerza `color: #fff` y los iconos SVG usan `currentColor`/`stroke: currentColor`. No se utilizó `!important`.

## Compatibilidad clínica y técnica

Se conservaron IDs, Quill, CSRF, autorización, referencia, paciente, mapeo `frecCardiaca`/`fc`, temperatura de receta, `idNota`, `idReceta`, `proximacita`, endpoints, transacciones y protección de cambios sin guardar. El JavaScript añadido sólo sincroniza la visibilidad del bloque opcional con el checkbox nativo.

## Validación

| Prueba | Resultado |
|---|---|
| PHP lint de vista, parcial y contrato | PASS |
| Sintaxis JavaScript inline | PASS |
| Contrato focalizado 09/09A | PASS_STATIC |
| Contratos existentes 07, 08, 08D, 08E, seguridad y referencia | PASS_STATIC |
| IDs sin duplicados | PASS_STATIC |
| CSS scoped y ausencia de margen negativo nuevo | PASS_STATIC |
| `git diff --check` | PASS |
| HTTP, PDO, SQL y persistencia | NO EJECUTADO |
| Chrome/Edge, consola, hover, foco y responsive 375/768/1366/1920 | NO VERIFICADO |

La validación visual queda pendiente porque Codex no dispone de una sesión conectada al XAMPP anfitrión. No se declara PASS visual ni funcional runtime.

## Archivos de esta intervención

- `app/views/clinica/pacientes-nota-subsecuente-nueva.php`
- `app/views/clinica/partials/nota-subsecuente-editor.php`
- `public/assets/css/app.css`
- `tests/doctor-subsequent-note/create-ux-contract-09.php`
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_CREATE_UI_REMEDIATION_09A.md`

## Checklist manual pendiente

En Chrome con sesión sintética autorizada: abrir la URL objetivo, comprobar checkbox marcado/desmarcado, Tab y barra espaciadora, seleccionar receta, revisar que Quill conserve contenido, probar hover/focus de regreso, cancelar, guardar y agregar laboratorio, y revisar 375, 768, 1366 y 1920 px sin overflow horizontal.

BACKEND_MODIFIED: NO
DATABASE_MODIFIED: NO
SCHEMA_MODIFIED: NO
COMMITS: NONE
DEPLOYMENTS: NONE
