# TH-DOCTOR-SUBSEQUENT-NOTE-CARD-SPACING-FIX-09C

## Estado

**STATUS: PARTIAL**

El ajuste scoped y la validación estática están implementados. La comprobación
visual real en Chrome queda **NO VERIFICADA** porque no se ejecutó una sesión
autenticada reproducible en este entorno.

## Causa raíz

La tarjeta de Laboratorio no tenía un margen propio de 85 px. El hueco visible
se producía por la combinación de tres factores:

1. `#mensaje` estaba dentro de `doctor-note-entry-primary`, después de la
   tarjeta Receta, y heredaba del shell Doctor `min-height: 1.4rem` y
   `margin-top: .75rem`.
2. Aunque estaba vacío, `#mensaje` seguía siendo un elemento flex dentro de un
   contenedor con `gap: 1.5rem`. Por tanto reservaba su propia altura y dos
   separaciones adicionales antes de Laboratorio.
3. La separación estructural entre los contenedores principales ya debía ser
   un único `gap`, pero quedaba visualmente amplificada por ese feedback vacío.

La estructura HTML se conservó: Receta permanece dentro de
`doctor-note-entry-primary`, Laboratorio y Próxima cita dentro de
`doctor-note-entry-related`, y la barra 09D continúa después de ese bloque.

## Corrección aplicada

En `public/assets/css/app.css`, exclusivamente bajo
`body.app-shell-body.doctor-note-create-page`:

```css
#mensaje {
  min-height: 0;
  margin: 0;
}

#mensaje:empty {
  display: none;
}
```

Así, un mensaje vacío no participa en el layout y no deja un hueco entre
Receta y Laboratorio. Cuando JavaScript escribe un error o confirmación, el
contenedor vuelve a ocupar únicamente la altura natural de su contenido.

También se fijó el `row-gap` del wrapper exterior a `1rem` en móvil. Las
separaciones existentes quedan uniformes:

- Escritorio: `1.5rem` (aproximadamente 24 px).
- Móvil: `1rem` (aproximadamente 16 px).

No se utilizaron márgenes negativos, alturas artificiales, padding internos
nuevos ni reglas sobre Receta, Laboratorio, Próxima cita o los botones.

## Elementos preservados

- Checkbox de Receta.
- Checkbox de Laboratorio.
- Quill y editores existentes.
- Próxima cita.
- Barra de acciones y botones de 09D.
- JavaScript, AJAX, CSRF, autorización, endpoints y payloads.
- Guardado, protección de cambios sin guardar y persistencia.

## Validación

| Comprobación | Resultado |
|---|---|
| Contrato focalizado 09C | PASS estático |
| IDs y tarjetas principales | PASS estático |
| Feedback vacío sin espacio reservado | PASS estático |
| Espaciado de escritorio | PASS estático: 1.5 rem |
| Espaciado móvil | PASS estático: 1 rem |
| Ausencia de márgenes negativos | PASS estático |
| Preservación de botones 09D | PASS estático |
| PHP lint | PASS para vista, parcial y contrato 09C |
| Revisión CSS scoped | PASS, feedback vacío fuera del flujo y gaps scoped |
| `git diff --check` | PASS, salida 0; avisos de conversión de fin de línea históricos |
| Chrome / 375 / 768 / 1024 / escritorio | NO VERIFICADO |
| Overflow horizontal | NO VERIFICADO en navegador |

## Archivos de esta intervención

- `public/assets/css/app.css`
- `tests/doctor-subsequent-note/card-spacing-contract-09c.php`
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_CREATE_UI_SPACING_BUTTONS_09C.md`

No se modificaron PHP de aplicación, JavaScript, backend, base de datos,
controladores, modelos, rutas ni persistencia. No se realizaron commits ni
despliegues.

## Resultado resumido

```yaml
TH_DOCTOR_SUBSEQUENT_NOTE_CARD_SPACING_FIX_09C:
  STATUS: PARTIAL
  ROOT_CAUSE: mensaje_vacio_reservaba_altura_margen_y_dos_gaps
  RECIPE_LABORATORY_GAP: PASS_STATIC_24PX_DESKTOP_16PX_MOBILE
  LABORATORY_APPOINTMENT_GAP: PASS_STATIC_24PX_DESKTOP_16PX_MOBILE
  UNIFORM_CARD_SPACING: PASS_STATIC
  HIDDEN_CONTENT_SPACING: PASS_STATIC
  RESPONSIVE_STATIC: PASS_STATIC
  BUTTONS_09D_PRESERVED: PASS_STATIC
  FUNCTIONAL_LOGIC_MODIFIED: NO
  BACKEND_MODIFIED: NO
  DATABASE_MODIFIED: NO
  BROWSER_VALIDATION: NO_VERIFICADO
  COMMITS: NONE
  DEPLOYMENTS: NONE
```

