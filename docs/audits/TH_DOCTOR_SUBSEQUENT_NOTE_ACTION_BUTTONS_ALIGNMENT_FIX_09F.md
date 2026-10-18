# TH-DOCTOR-SUBSEQUENT-NOTE-ACTION-BUTTONS-ALIGNMENT-FIX-09F

## Estado

**STATUS: PARTIAL**

Se corrigió estáticamente el desalineamiento de las acciones finales de la
vista de nueva Nota subsecuente. La comprobación geométrica en navegador real
queda **NO VERIFICADA** en este entorno.

## Causa confirmada

El botón `Guardar Nota subsecuente` está dentro de `.doctor-note-submit`. Esa
envoltura conservaba el `padding: .25rem 0 1rem` definido para el botón de
envío general de la vista. Aunque el botón interno y `Cancelar` compartían
`min-height`, el wrapper tenía altura adicional y producía una alineación
vertical distinta dentro de la barra de acciones.

## Corrección aplicada

Se añadió CSS scoped exclusivamente al contexto
`body.app-shell-body.doctor-note-create-page .doctor-note-form-actions` para:

- convertir el wrapper de Guardar en un contenedor flex sin padding heredado;
- aplicar `margin: 0` y `padding: 0` al wrapper;
- aplicar a ambos controles `box-sizing: border-box`, `height: 44px`,
  `min-height: 44px`, `margin: 0` y `vertical-align: middle`.

No se utilizaron márgenes negativos, transformaciones ni posicionamiento
absoluto. La barra continúa usando flexbox, alineación vertical centrada y
separación uniforme.

## Contratos preservados

- `data-note-cancel` y la navegación directa de 09E permanecen intactos.
- Cancelar no recupera `confirm`, POST ni guardado automático.
- Guardar conserva `AgregarNota()`, su icono, loading y disabled.
- Los colores, foco, Quill, receta, laboratorio, próxima cita y payloads no se
  modificaron.
- En móvil se conserva el apilamiento existente y el ancho flexible.

## Validación

| Comprobación | Resultado |
|---|---|
| Contrato focalizado 09F | PASS estático |
| PHP lint | PASS para vistas y contrato 09F |
| Sintaxis JavaScript inline | PASS, 1 bloque inline |
| Contratos 09C, 09D y 09E | PASS estático |
| Revisión CSS | PASS; reglas scoped, sin transform ni posición absoluta |
| `git diff --check` | PASS, salida 0; avisos históricos de LF/CRLF |
| Medición `getBoundingClientRect()` | NO VERIFICADO; sin navegador/XAMPP accesible |
| Hover, focus y loading en navegador | NO VERIFICADO |
| Responsive renderizado | NO VERIFICADO |

## Archivos de esta intervención

- `public/assets/css/app.css`
- `tests/doctor-subsequent-note/action-buttons-alignment-contract-09f.php`
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_ACTION_BUTTONS_ALIGNMENT_FIX_09F.md`

La vista y el parcial no requirieron cambios estructurales. No se modificaron
backend, modelos, controladores, rutas, endpoints, base de datos ni `.env`.
No hubo commits ni despliegues.

## Resultado resumido

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_ACTION_BUTTONS_ALIGNMENT_FIX_09F:
  STATUS: PARTIAL
  ALIGNMENT_ROOT_CAUSE: PASS_STATIC
  ACTION_BAR_LAYOUT: PASS_STATIC
  BUTTONS_SAME_HEIGHT: PASS_STATIC
  BUTTONS_SAME_TOP: NO_VERIFICADO_BROWSER
  BUTTONS_SAME_BOTTOM: NO_VERIFICADO_BROWSER
  BUTTON_TEXT_CENTERED: PASS_STATIC_CSS
  SAVE_ICON_ALIGNED: PASS_STATIC_CSS
  BUTTON_GAP: PASS_STATIC
  RIGHT_ALIGNMENT: PASS_STATIC_CSS
  CANCEL_STYLES_PRESERVED: PASS_STATIC
  SAVE_STYLES_PRESERVED: PASS_STATIC
  CANCEL_09E_PRESERVED: PASS_STATIC
  SAVE_FUNCTION_PRESERVED: PASS_STATIC
  RESPONSIVE_STATIC: PASS_STATIC
  PHP_LINT: PASS
  JS_SYNTAX: PASS
  CSS_REVIEW: PASS
  FOCUSED_TESTS: PASS_STATIC
  DIFF_CHECK: PASS
  BROWSER_VALIDATION: NO_VERIFICADO
  BACKEND_MODIFIED: NO
  DATABASE_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
```
