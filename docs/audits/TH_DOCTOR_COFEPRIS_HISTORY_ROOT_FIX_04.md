# TH-DOCTOR-COFEPRIS-HISTORY-ROOT-FIX-04

## Resultado de la corrección focalizada

La corrección anterior dependía de `history.back()` después de haber navegado a la ruta de edición. Ese flujo no era estable en Chrome: al cerrar el modal el documento ya estaba en una entrada distinta y el historial conservaba la navegación del listado y de la edición. Por ello, el primer retroceso podía dejar al usuario todavía en COFEPRIS y exigía otro retroceso.

### Causa y diagnóstico

- La tabla conserva el enlace nativo `href="/clinica/cofepris/paciente/{idPaciente}/editar/{idRegistro}"` para acceso directo y navegación alternativa.
- El listener delegado de la tabla intercepta únicamente el clic primario sin modificadores, ejecuta `preventDefault()` y llama a `abrirEdicionCofepris(link)`.
- El listener de detalle de fila excluye enlaces, botones y controles, por lo que el enlace Editar no dispara simultáneamente el detalle.
- La delegación se registra una sola vez mediante `data-detail-events-bound`, incluso después de redibujar Simple-DataTables.
- El HTML que devuelve el redibujado AJAX conserva el `href` de edición pero no siempre la clase visual del enlace; `prepararFilasCofepris()` normaliza esos enlaces con `a[href*="/editar/"]` antes de delegar el clic.
- En el flujo nuevo no se encontró `pushState`, `location.assign`, `location.replace`, `location.href`, `history.back()` ni `history.go(-2)` asociado a la edición interna.
- El uso de `replaceState` queda limitado al cierre de una edición abierta directamente en `/editar/{id}` para ofrecer una salida segura al listado; no se usa para la edición iniciada desde la tabla.

### Flujo corregido

1. Expediente → listado COFEPRIS: conserva la navegación normal.
2. Listado → Editar: el clic se intercepta, la URL permanece en el listado y se solicita la ruta de edición existente con `fetch` del mismo origen.
3. La respuesta se analiza con `DOMParser`; no se inserta su HTML en el documento actual. Los valores se copian desde los controles parseados a los controles ya existentes del modal.
4. El modal se abre con el título, folio, campos y acción de guardado del registro seleccionado.
5. X, Cancelar o Escape cierran el modal interno, restauran el foco y no cambian el historial.
6. Después del cierre, una sola pulsación de Atrás sigue disponible para volver al expediente.
7. La ruta directa `/clinica/cofepris/paciente/{idPaciente}/editar/{idRegistro}` continúa siendo renderizada por el flujo existente; su cierre usa el fallback seguro al listado.

La ruta de edición se reutiliza como fuente de datos y no se creó endpoint nuevo. La validación numérica de paciente y registro se realiza antes de construir el `onclick` del guardado.

### Seguridad y alcance

La nueva ruta de edición interna no usa `innerHTML` ni inserta nodos provenientes de la respuesta. El flujo de detalle existente conserva su renderizado dinámico previo y requiere una revisión de seguridad separada; no se modificó en esta corrección para no ampliar el alcance.

No se modificaron modelos, controladores, rutas, base de datos, CSS, PDF, autenticación ni autorización. No se ejecutaron HTTP, Chrome ni consultas de base de datos desde Codex.

### Validación estática

```yaml
TH_DOCTOR_COFEPRIS_HISTORY_ROOT_FIX_04:
  STATUS: PARTIAL_STATIC_IMPLEMENTATION_RUNTIME_VALIDATION_PENDING
  PREVIOUS_FIX_INEFFECTIVE_CAUSE: "El cierre con history.back() dependía de una navegación previa a la edición y no eliminaba de forma fiable la entrada extra observada en Chrome."
  BASELINE_BACK_WITHOUT_EDIT: NOT_EXECUTED
  EDIT_CLICK_ROOT_CAUSE: "El href nativo podía navegar al documento de edición; el flujo interno no lo interceptaba de forma definitiva."
  DUPLICATE_EVENT_FOUND: "No se observó una segunda apertura del enlace Editar; el listener delegado de detalle excluye enlaces y la vinculación se guarda en data-detail-events-bound."
  NATIVE_LINK_NAVIGATION_FOUND: "Sí, el enlace conserva href para acceso directo; el clic primario del listado ahora usa preventDefault()."
  INTERNAL_EDIT_WITHOUT_NAVIGATION: PASS_STATIC
  URL_REMAINS_LIST: PASS_STATIC
  MODAL_OPENS: PASS_STATIC
  MODAL_CLOSE_WITHOUT_HISTORY_CHANGE: PASS_STATIC
  DIRECT_EDIT_ROUTE_PRESERVED: PASS_STATIC
  DIRECT_EDIT_FALLBACK: PASS_STATIC
  BACK_AFTER_CLOSE_SINGLE_CLICK: NOT_EXECUTED
  FORWARD_NAVIGATION: NOT_EXECUTED
  REPEATED_EDITS_NO_EXTRA_HISTORY: PASS_STATIC
  DATATABLE_EVENTS_PRESERVED: PASS_STATIC
  CREATE_MODAL_PRESERVED: PASS_STATIC
  DETAIL_MODAL_PRESERVED: PASS_STATIC
  EDIT_MODAL_PRESERVED: PASS_STATIC
  BACK_BUTTON_VISUAL_PRESERVED: PASS_STATIC
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  FOCUSED_TESTS: PASS
  DIFF_CHECK: PASS
  BROWSER_VALIDATION: NOT_EXECUTED
  FILES_MODIFIED:
    - app/views/clinica/pacientes-cofepris.php
    - tests/doctor-cofepris/browser-history-fix-02-contract.php
    - tests/doctor-cofepris/navigation-correction-02-contract.php
    - docs/audits/TH_DOCTOR_COFEPRIS_HISTORY_ROOT_FIX_04.md
  DATABASE_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
```

### Procedimiento manual en Chrome

Ejecutar en la instancia local autorizada, sin modificar la aplicación:

1. Desde el expediente abrir COFEPRIS y anotar la URL del listado.
2. Abrir DevTools → Console y ejecutar `location.href` y `history.state` antes de editar.
3. Pulsar Editar para el registro de prueba. Confirmar que aparece el modal, que la URL sigue siendo `/clinica/cofepris/paciente/310` y que la consola no muestra errores.
4. Cerrar con X, Cancelar y Escape en recorridos separados. Confirmar que la URL no cambia y que el foco regresa al control de origen.
5. Pulsar Atrás una sola vez y confirmar el regreso al expediente.
6. Repetir abrir/cerrar cinco veces y después probar búsqueda, ordenamiento y paginación; verificar que no se acumulan navegaciones.
7. Abrir directamente `/clinica/cofepris/paciente/310/editar/351`, confirmar la edición y cerrar; verificar el fallback al listado.
8. Probar Adelante después del recorrido expediente → listado → Atrás.
9. Para apoyar la evidencia, registrar antes y después:

   ```js
   location.href
   history.state
   performance.getEntriesByType('navigation').map(n => ({type: n.type, url: n.name}))
   ```

La comprobación real del historial, consola y navegación queda pendiente hasta ejecutar este procedimiento en Chrome/XAMPP.

### Siguiente acción exacta

Repetir las pruebas 1–8 en Chrome con un registro de prueba autorizado y reportar cada URL observada. No declarar `BROWSER_BACK_FIXED: PASS_RUNTIME` hasta contar con esa evidencia.
