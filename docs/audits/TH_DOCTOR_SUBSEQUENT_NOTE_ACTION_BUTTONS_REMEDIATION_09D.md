# TH-DOCTOR-SUBSEQUENT-NOTE-ACTION-BUTTONS-REMEDIATION-09D

## Estado

**STATUS: PARTIAL**

La homologación CSS y la revisión estática están implementadas. La inspección
visual en Chrome, incluyendo hover, focus, responsive y estados de carga, queda
**NO VERIFICADA** en este entorno.

## Alcance y causa

Se revisó exclusivamente la barra inferior de acciones de:

`app/views/clinica/partials/nota-subsecuente-editor.php`

La estructura ya estaba ubicada debajo de «Próxima cita» por la remediación
09B. El problema restante era que Cancelar y Guardar dependían principalmente
de las variantes genéricas de Bootstrap (`btn-outline-primary` y `btn-primary`),
con proporciones mayores que las acciones institucionales equivalentes y con
estados que podían heredar texto oscuro al cambiar el fondo a morado.

## Referencias revisadas

Se compararon los patrones scoped existentes de:

- Historial de notas subsecuentes.
- Laboratorio.
- Receta médica.
- COFEPRIS.
- PIN y acciones del expediente Doctor.

Se seleccionó el patrón de acciones clínicas compactas: altura táctil de 44 px,
radio de 10 px, padding equilibrado, iconos alineados por `currentColor`,
blanco para la acción secundaria y morado institucional para la acción
principal.

## Implementación

Se añadió el bloque scoped:

`body.app-shell-body.doctor-note-create-page .doctor-note-form-actions ...`

Características comunes:

- `min-height: 44px`.
- `padding: .6rem 1rem`.
- `border-radius: 10px`.
- Tipografía de `0.9rem` y peso 700.
- Alineación inline-flex y separación de icono/texto.
- Transición suave sin transformaciones ni cambios de tamaño.

### Cancelar

- Estado normal: fondo `#fff`, borde morado y texto morado oscuro.
- Hover y focus-visible: fondo morado oscuro, texto e iconos blancos.
- Conserva `data-note-exit`, su URL existente y la protección de cambios sin
  guardar.

### Guardar Nota subsecuente

- Estado normal: fondo `var(--clinic-primary)`, texto e icono blancos.
- Hover y active: fondo `var(--clinic-primary-dark)`, texto e icono blancos.
- Disabled/loading: opacidad controlada, dimensiones estables, cursor de espera
  y sin hover engañoso.
- Se conserva `onclick="AgregarNota()"`, la desactivación durante el guardado,
  `aria-busy`, el payload, CSRF, validaciones y redirección existentes.

### Barra inferior y responsive

- `.doctor-note-action-row` sigue siendo el contenedor independiente de ancho
  completo bajo «Próxima cita».
- En escritorio las acciones se alinean a la derecha en una fila.
- En móvil se mantiene el apilado existente a ancho completo, sin alterar el
  espaciado de Receta, Laboratorio o Próxima cita.
- No se utilizó posicionamiento absoluto, margen negativo ni selector global.

## Preservación funcional

No se modificaron controladores, modelos, rutas, endpoints, payloads, Quill,
CSRF, autorización, persistencia, Receta, Laboratorio ni Próxima cita.

## Validación

| Prueba | Resultado |
|---|---|
| Contrato focalizado 09D | PASS estático |
| Contratos funcional 07, vistas 08, detalle 08D/08E, seguridad 04 y referencia 04C | PASS estático |
| Contrato de creación 09/09A/09B | PASS estático |
| Igualdad de altura, padding y radio | PASS estático por CSS scoped |
| Colores normal/hover de Cancelar | PASS estático por CSS scoped |
| Texto/icono blancos de Guardar en estados morados | PASS estático por CSS scoped |
| Focus-visible, disabled y loading | PASS estático por CSS scoped |
| PHP lint | PASS para vista, parcial y contrato 09D |
| Sintaxis JavaScript | PASS, 1 bloque inline |
| `git diff --check` | PASS, salida 0; avisos de conversión de fin de línea históricos |
| Chrome, hover, focus y responsive 375/768/1024/1366/1920 | NO VERIFICADO |
| AJAX, persistencia y flujo clínico | NO VERIFICADO |

No se declara aprobación visual ni funcional.

## Archivos de esta intervención

- `public/assets/css/app.css`
- `tests/doctor-subsequent-note/action-buttons-contract-09d.php`
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_ACTION_BUTTONS_REMEDIATION_09D.md`

Los cambios locales preexistentes de la vista, parcial y demás archivos del
worktree se conservaron. No hubo acceso a base de datos, commits, staging,
push ni despliegues.

## Resultado resumido

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_ACTION_BUTTONS_REMEDIATION_09D:
  STATUS: PARTIAL
  BUTTONS_SAME_HEIGHT: PASS_STATIC
  BUTTONS_VERTICAL_ALIGNMENT: PASS_STATIC
  CANCEL_NORMAL_WHITE: PASS_STATIC
  CANCEL_HOVER_WHITE_TEXT: PASS_STATIC
  SAVE_NORMAL_PURPLE: PASS_STATIC
  SAVE_HOVER_WHITE_TEXT: PASS_STATIC
  SAVE_ICON_WHITE: PASS_STATIC
  FOCUS_VISIBLE: PASS_STATIC
  DISABLED_STATE: PASS_STATIC
  LOADING_STATE: PASS_STATIC
  ACTION_BAR_FULL_WIDTH: PASS_STATIC
  ACTION_BAR_RIGHT_ALIGNED: PASS_STATIC
  MOBILE_LAYOUT: PASS_STATIC
  NO_HORIZONTAL_OVERFLOW: NO_VERIFICADO
  CANCEL_NAVIGATION_PRESERVED: PASS_STATIC
  UNSAVED_CHANGES_PROTECTION_PRESERVED: PASS_STATIC
  SAVE_FUNCTION_PRESERVED: PASS_STATIC
  PHASE_09B_PRESERVED: PASS_STATIC
  BROWSER_VALIDATION: NO VERIFICADO
  BACKEND_MODIFIED: NO
  DATABASE_MODIFIED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
```

