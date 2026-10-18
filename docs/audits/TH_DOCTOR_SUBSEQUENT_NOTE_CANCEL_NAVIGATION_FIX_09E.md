# TH-DOCTOR-SUBSEQUENT-NOTE-CANCEL-NAVIGATION-FIX-09E

## Estado

**STATUS: PARTIAL**

La corrección está implementada y los contratos estáticos pasan. La navegación
real en Chrome/XAMPP y la comprobación de ausencia de solicitudes de red quedan
**NO VERIFICADAS** en este entorno.

## Causa confirmada

El listener de clic se registraba sobre todos los elementos
`[data-note-exit]`. El enlace Cancelar compartía ese atributo con «Volver al
historial», por lo que ejecutaba `window.confirm()` cuando `notaDirty` era
verdadero. Además, el listener `beforeunload` podía volver a mostrar la
advertencia durante la navegación normal si el estado dirty no se limpiaba
antes de salir.

## Corrección aplicada

El enlace Cancelar conserva `data-note-exit` y añade el atributo de presentación
de comportamiento `data-note-cancel`. Su manejador únicamente ejecuta:

```js
notaDirty = false;
```

Después permite la navegación normal del enlace. No llama `confirm`, `alert`,
SweetAlert, `fetch`, POST, guardado ni finalización.

La guardia de cambios se registra ahora sólo para:

```js
[data-note-exit]:not([data-note-cancel])
```

Por tanto, la salida «Volver al historial» y otras navegaciones protegidas
conservan su confirmación; Cancelar es la única salida explícita que descarta
intencionalmente los cambios sin preguntar.

## Destino

Cancelar reutiliza el `href` ya generado desde `$data['list_url']`. El
controlador construye ese valor con el paciente y la referencia autorizados:

`/clinica/nota-subsecuente/paciente/{idPaciente}/referencia/{referencia}`

No se introdujeron IDs ni referencias fijas, `history.back()`,
`document.referrer` ni rutas nuevas.

## Protecciones preservadas

- `beforeunload` continúa activo para abandonos no intencionales.
- La confirmación continúa activa para el enlace de regreso del hero.
- Guardado, doble envío, Quill, receta opcional, laboratorio opcional y próxima
  cita no fueron modificados.
- Endpoint, payload, CSRF, autorización y persistencia no fueron modificados.
- Los estilos de 09D y el espaciado de 09C permanecen intactos.

## Validación

| Comprobación | Resultado |
|---|---|
| Contrato focalizado 09E | PASS estático |
| PHP lint | PASS para vista, parcial y contrato 09E |
| Sintaxis JavaScript inline | PASS, 1 bloque inline |
| Contratos 07 y 09/09A/09B/09C/09D | PASS estático |
| Cancelar sin cambios | NO VERIFICADO en navegador |
| Cancelar con campos modificados | NO VERIFICADO en navegador |
| Cancelar con Quill/Receta/Laboratorio activos | NO VERIFICADO en navegador |
| Ausencia de POST y escrituras al cancelar | NO VERIFICADO en navegador |
| Historial conserva paciente y referencia | PASS estático del destino; runtime NO VERIFICADO |
| `git diff --check` | PASS, salida 0; avisos de conversión de fin de línea históricos |

No se declara cierre funcional sin la prueba autenticada real.

## Archivos de esta intervención

- `app/views/clinica/pacientes-nota-subsecuente-nueva.php`
- `app/views/clinica/partials/nota-subsecuente-editor.php`
- `tests/doctor-subsequent-note/cancel-navigation-contract-09e.php`
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_CANCEL_NAVIGATION_FIX_09E.md`

No se modificaron backend, base de datos, esquema, endpoints, CSS ni estilos de
09C/09D. No hubo commits ni despliegues.

## Resultado resumido

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_CANCEL_NAVIGATION_FIX_09E:
  STATUS: PARTIAL
  CONFIRM_ROOT_CAUSE: PASS_STATIC
  CANCEL_CONFIRM_REMOVED: PASS_STATIC
  CANCEL_DIRECT_NAVIGATION: PASS_STATIC
  CANCEL_TARGET_DYNAMIC: PASS_STATIC
  CANCEL_TARGET_HISTORY: PASS_STATIC
  BEFOREUNLOAD_SCOPE_PRESERVED: PASS_STATIC
  OTHER_NAVIGATION_GUARDS_PRESERVED: PASS_STATIC
  NO_POST_ON_CANCEL: PASS_STATIC
  NO_CLINICAL_WRITES: PASS_STATIC
  SAVE_BUTTON_PRESERVED: PASS_STATIC
  QUILL_PRESERVED: PASS_STATIC
  RECIPE_OPTIONAL_PRESERVED: PASS_STATIC
  LABORATORY_OPTIONAL_PRESERVED: PASS_STATIC
  PHASE_07_PRESERVED: PASS_STATIC
  PHASE_09D_STYLES_PRESERVED: PASS_STATIC
  BROWSER_VALIDATION: NO_VERIFICADO
  BACKEND_MODIFIED: NO
  DATABASE_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
```

