# TH-DOCTOR-COFEPRIS-HISTORY-RETURN-FIX-03

## Diagnóstico

La edición interna se distingue ahora de un acceso directo mediante una marca temporal en `sessionStorage`, escrita antes de seguir el enlace normal hacia `/clinica/cofepris/paciente/{idPaciente}/editar/{idCofepris}`. Así se conserva la entrada real creada por el navegador y no se confunde con una URL escrita, un marcador o una pestaña nueva.

## Corrección

- Desde el listado, el enlace `Editar` mantiene navegación HTTP normal y conserva la entrada `/editar/{id}`.
- Al cargar esa ruta, sólo si la marca temporal coincide con la URL actual, el modal queda identificado como edición interna.
- X, cierre/cancelación existente y Escape consumen esa entrada con una única `history.back()`.
- No se ejecutan dos retrocesos, no se usa `history.go(-2)` y no se crea una URL duplicada del listado.
- Si la ruta se abrió directamente sin origen interno verificable, el cierre usa el fallback seguro existente: `replaceState` hacia el listado autorizado del mismo paciente.
- No se añadió `popstate` porque la navegación interna ocurre entre documentos y no requiere sincronización History API dentro del mismo documento.

## Botón de regreso

El botón `Volver al expediente` conserva el patrón de Laboratorio y Receta médica: fondo blanco, borde institucional, texto morado, icono `arrow-left`, etiqueta exacta y destino dinámico mediante `$data['idPaciente']`. No se modificó su diseño.

## Resultado

```yaml
TH_DOCTOR_COFEPRIS_HISTORY_RETURN_FIX_03:
  STATUS: PARTIAL_STATIC_IMPLEMENTATION_RUNTIME_VALIDATION_PENDING
  ROOT_CAUSE: "La edición interna debe conservar la entrada real /editar/{id}; ocultar el modal o reemplazar superficialmente la URL no consume esa entrada."
  EDIT_NAVIGATION_TYPE: "Navegación HTTP normal desde el enlace interno, con atestación temporal de origen en sessionStorage."
  HISTORY_ENTRY_CREATED: PASS_STATIC_EXPECTED
  CLOSE_BEHAVIOR_BEFORE: "El cierre no consumía de forma verificable la entrada de edición y podía dejar una navegación redundante."
  CLOSE_BEHAVIOR_AFTER: "Edición interna: una única history.back(); acceso directo: replaceState seguro al listado."
  HISTORY_BACK_WHEN_APPROPRIATE: PASS_STATIC
  DIRECT_URL_FALLBACK: PASS_STATIC
  POPSTATE_HANDLING: NOT_REQUIRED_STATIC
  DUPLICATE_NAVIGATION_REMOVED: PASS_STATIC
  CLOSE_X: PASS_STATIC
  CLOSE_CANCEL: PASS_STATIC_EXISTING_CLOSE_FLOW
  CLOSE_ESCAPE: PASS_STATIC
  SINGLE_BACK_TO_PREVIOUS_PAGE: NOT_EXECUTED_RUNTIME
  BACK_FORWARD_NAVIGATION: NOT_EXECUTED_RUNTIME
  DIRECT_EDIT_URL_PRESERVED: PASS_STATIC
  MODALS_PRESERVED: PASS_STATIC
  DESIGN_PRESERVED: PASS_STATIC
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  FOCUSED_TESTS: PASS
  DIFF_CHECK: PASS
  BROWSER_VALIDATION: NOT_EXECUTED
  FILES_MODIFIED:
    - app/views/clinica/pacientes-cofepris.php
    - tests/doctor-cofepris/browser-history-fix-02-contract.php
    - tests/doctor-cofepris/navigation-correction-02-contract.php
    - docs/audits/TH_DOCTOR_COFEPRIS_HISTORY_RETURN_FIX_03.md
  DATABASE_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS: "Codex no ejecutó Chrome/XAMPP; falta confirmar Atrás, Adelante, X, Escape y la preservación visual/funcional con la aplicación renderizada."
  NEXT_MANUAL_VALIDATION: "Con sesión Doctor autorizada: Expediente -> COFEPRIS -> Editar 351 -> X/Cancelar/Escape -> Atrás; repetir Adelante, búsqueda/ordenamiento/paginación y acceso directo /editar/351."
```

## Validaciones ejecutadas

- `php -l app/views/clinica/pacientes-cofepris.php`: PASS.
- PHP lint de ambos contratos focalizados: PASS.
- `php tests/doctor-cofepris/browser-history-fix-02-contract.php`: PASS.
- `php tests/doctor-cofepris/navigation-correction-02-contract.php`: PASS.
- Parseo de JavaScript inline mediante Node: PASS.
- Revisión estática de rutas, eventos e History API: PASS.
- `git diff --check`: PASS.

No se ejecutaron HTTP, navegador, base de datos ni operaciones clínicas. COFEPRIS permanece en validación.
