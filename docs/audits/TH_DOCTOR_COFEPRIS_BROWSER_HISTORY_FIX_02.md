# TH-DOCTOR-COFEPRIS-BROWSER-HISTORY-FIX-02

## Diagnóstico focalizado

El listado COFEPRIS generaba un enlace normal hacia `/clinica/cofepris/paciente/{idPaciente}/editar/{idCofepris}`. Al pulsarlo desde el listado se producía una navegación completa del documento. El cierre sólo ocultaba el modal, por lo que la entrada de edición permanecía en el historial.

Secuencia anterior observada por contrato estático:

`expediente → listado COFEPRIS → /editar/{id} → cerrar (la URL seguía siendo /editar/{id}) → Atrás: listado → Atrás: expediente`

## Corrección

- Los clics normales sobre `Editar` se interceptan únicamente en la vista del listado.
- Se conserva el `href` original y se solicita la ruta de edición existente mediante `fetch` del mismo origen.
- El formulario renderizado por la ruta existente se importa dentro del modal actual; no se creó endpoint nuevo ni se cambió el backend.
- La URL del listado y su entrada de historial permanecen intactas durante la edición en modal.
- Los clics modificados, apertura en pestaña nueva y acceso directo a `/editar/{id}` conservan el enlace/ruta original.
- En un acceso directo, cerrar el modal normaliza la URL actual hacia el listado mediante un único `replaceState`, sin `history.back()`, `history.go()` ni `pushState()`.
- Se conserva el identificador del registro en el botón de guardado, el foco de cierre, Escape, los modales de alta/detalle y las validaciones existentes.

Secuencia corregida desde el listado:

`expediente → listado COFEPRIS → edición en modal (sin nueva entrada) → cerrar → Atrás: expediente`

## Evidencia estática

```yaml
TH_DOCTOR_COFEPRIS_BROWSER_HISTORY_FIX_02:
  STATUS: PARTIAL_STATIC_IMPLEMENTATION_RUNTIME_VALIDATION_PENDING
  ROOT_CAUSE: "El enlace Editar navegaba al documento /editar/{id}; cerrar sólo ocultaba el modal y dejaba esa entrada en el historial."
  PREVIOUS_NAVIGATION_SEQUENCE: "expediente -> listado -> /editar/{id} -> cerrar -> Atrás al listado -> Atrás al expediente"
  CORRECTED_NAVIGATION_SEQUENCE: "expediente -> listado -> edición en modal sin navegación -> cerrar -> Atrás al expediente"
  DUPLICATE_HISTORY_ENTRY_FOUND: PASS_STATIC_CAUSAL
  HISTORY_FIX_IMPLEMENTED: PASS_STATIC
  EDIT_MODAL_PRESERVED: PASS_STATIC
  CREATE_MODAL_PRESERVED: PASS_STATIC
  DETAIL_MODAL_PRESERVED: PASS_STATIC
  DIRECT_EDIT_URL_PRESERVED: PASS_STATIC
  EDIT_FROM_LIST_WORKS: PASS_STATIC_SAME_ORIGIN_FETCH
  MODAL_CLOSE_WORKS: PASS_STATIC
  BROWSER_BACK_SINGLE_CLICK: NOT_VERIFIED_RUNTIME
  BROWSER_FORWARD_WORKS: NOT_VERIFIED_RUNTIME
  PATIENT_CONTEXT_PRESERVED: PASS_STATIC
  TABLE_STATE_PRESERVED: PASS_STATIC_NO_DOCUMENT_NAVIGATION
  UNSAVED_CHANGES_PROTECTED: PASS_STATIC_EXISTING_CLOSE_CONTRACT
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  DIFF_CHECK: PASS
  BROWSER_VALIDATION: NOT_EXECUTED
  FILES_MODIFIED:
    - app/views/clinica/pacientes-cofepris.php
    - tests/doctor-cofepris/browser-history-fix-02-contract.php
    - docs/audits/TH_DOCTOR_COFEPRIS_BROWSER_HISTORY_FIX_02.md
  DATABASE_MODIFIED: NO
  BACKEND_CLINICAL_LOGIC_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
  BLOCKERS: "Codex no ejecutó Chrome/XAMPP; no existe evidencia runtime de Atrás, Adelante o persistencia del estado de la tabla."
  NEXT_MANUAL_VALIDATION: "Con sesión Doctor autorizada, probar expediente -> COFEPRIS -> Editar -> cerrar -> Atrás; luego Atrás/Adelante, alta/detalle, búsqueda/ordenamiento/paginación y acceso directo /editar/{id}."
```

## Validaciones ejecutadas

- `php -l app/views/clinica/pacientes-cofepris.php`: PASS.
- `php -l tests/doctor-cofepris/browser-history-fix-02-contract.php`: PASS.
- Contrato estático de historial: PASS.
- Parseo de JavaScript inline mediante Node: PASS.
- `git diff --check`: PASS.

No se ejecutaron navegador, HTTP, base de datos ni operaciones clínicas. No se modificó CSS, backend, rutas, PDF, registros ni `.env`.

COFEPRIS permanece en validación hasta la comprobación manual del historial real.
