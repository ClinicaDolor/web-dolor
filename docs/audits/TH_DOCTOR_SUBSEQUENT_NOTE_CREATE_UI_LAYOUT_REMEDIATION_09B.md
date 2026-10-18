# TH-DOCTOR-SUBSEQUENT-NOTE-CREATE-UI-LAYOUT-REMEDIATION-09B

## Estado

**STATUS: PARTIAL**

La corrección estructural y los contratos estáticos quedan implementados. La
validación con navegador, sesión autenticada, consola, red y persistencia queda
**NO VERIFICADA** en este entorno.

## Alcance

Se trabajó exclusivamente sobre la vista independiente de creación/edición de
Nota subsecuente del área Doctor. No se modificaron controladores, modelos,
rutas, endpoints, autenticación, autorización, esquema ni persistencia.

## Identificación y causa

La vista utiliza:

- `app/views/clinica/pacientes-nota-subsecuente-nueva.php` como página y lógica
  JavaScript de la captura.
- `app/views/clinica/partials/nota-subsecuente-editor.php` como estructura del
  formulario.
- `public/assets/css/app.css` para los estilos scoped
  `body.app-shell-body.doctor-note-create-page ...`.

La separación defectuosa tenía dos causas de layout:

1. El wrapper `doctor-note-entry-primary` no tenía un `gap` propio y la tarjeta
   de receta seguía inmediatamente a la tarjeta de valoración.
2. `doctor-note-entry-related` conservaba una cuadrícula de dos columnas que
   reservaba una segunda columna para las acciones. Esto hacía que la barra
   `Cancelar/Guardar` dependiera de la distribución del bloque clínico y no
   fuese una franja independiente debajo de `Próxima cita`.

## Cambios realizados

### Laboratorio opcional

- Se añadió el checkbox nativo `#IncluirLaboratorio` siguiendo el patrón visual
  y accesible de `#IncluirReceta`.
- El control usa `aria-controls="doctor-note-lab-fields"`, ayuda asociada y
  etiqueta visible «Agregar laboratorio relacionado».
- `#doctor-note-lab-fields` inicia oculto con `hidden` y
  `aria-hidden="true"`.
- `sincronizarCamposLaboratorio()` muestra u oculta el bloque sin limpiar
  campos ni cambiar el contrato clínico.
- `tableLaboratorio()` ya no se ejecuta al cargar siempre la vista. Se llama
  al activar el bloque por primera vez y sigue llamándose después de una carga
  exitosa mediante el flujo independiente `AgregarLaboratorio()`.
- No se mezcló la carga/registro de laboratorio con el POST de la nota.
  Permanecen el endpoint, `X-M03-CSRF`, la autorización y el flujo AJAX
  existentes.

### Distribución y acciones

- Nota médica y receta se apilan con altura natural y `gap: 1.5rem` en
  escritorio (`1rem` en móvil).
- Laboratorio y próxima cita se apilan con la misma separación.
- Cancelar y Guardar están en `.doctor-note-action-row`, fuera de las tarjetas,
  ocupando todo el ancho disponible.
- La barra conserva la alineación a la derecha en escritorio y se apila a ancho
  completo en móvil.
- Se conserva el orden clínico: valoración/receta, laboratorio, próxima cita y
  después las acciones finales.
- No se añadió `height: 100%`, overflow artificial ni posicionamiento absoluto.

## Contratos preservados

Se conservaron los IDs y nombres usados por la vista y su JavaScript, incluidos
`TA`, `FrecCardiaca`, `fc`, `Temperatura`, `idNota`, `idReceta`,
`FechaProximaCita`, `proximacita`, `snow1`, `snow2`, `snow3`,
`conteLaboratorio`, `detalleLaboratorio`, `Titulo` y `Archivo`.

También se conservaron:

- Quill y los tres editores.
- Sanitización de contenido.
- CSRF de Nota y Laboratorio.
- AJAX y payloads existentes.
- Receta opcional y su impresión existente.
- Guardado, edición, salida protegida y navegación.
- Endpoints y persistencia sin cambios.

## Validación

| Comprobación | Resultado |
|---|---|
| Contrato estático de creación 09/09A/09B | PASS, ejecutable sin BD ni HTTP |
| IDs y controles nuevos sin duplicidad | PASS, revisión estática |
| Laboratorio oculto inicialmente | PASS, revisión estática |
| Visibilidad y carga bajo activación | PASS, revisión estática |
| PHP lint | PASS para vista, parcial y contrato estático |
| Sintaxis JavaScript inline | PASS, 1 bloque inline |
| Revisión CSS scoped | PASS, layout natural y sin grid antigua de dos columnas |
| `git diff --check` | PASS, salida 0; avisos de conversión de fin de línea históricos |
| Navegador 1440×900 / 1280×720 / 768×1024 / 375×667 | NO VERIFICADO |
| Consola, Network, AJAX y persistencia | NO VERIFICADO |
| Responsive y foco con navegador real | NO VERIFICADO |

La tabla distingue las comprobaciones que deben ejecutarse antes de una
aprobación visual. No se declara PASS visual ni funcional.

## Archivos de esta intervención

- `app/views/clinica/pacientes-nota-subsecuente-nueva.php`
- `app/views/clinica/partials/nota-subsecuente-editor.php`
- `public/assets/css/app.css`
- `tests/doctor-subsequent-note/create-ux-contract-09.php`
- `docs/audits/TH_DOCTOR_SUBSEQUENT_NOTE_CREATE_UI_LAYOUT_REMEDIATION_09B.md`

No se realizaron commits, staging, despliegues, escrituras clínicas ni acceso a
base de datos.

## Pendientes y riesgos abiertos

- Ejecutar lint, sintaxis inline, contratos y `git diff --check`.
- Reproducir la pantalla con una sesión segura para confirmar que el checkbox
  de laboratorio no genera carga mientras permanece desactivado.
- Confirmar en navegador la composición, foco, responsive, AJAX, persistencia y
  ausencia de errores de consola.
- Mantener separados los riesgos funcionales y de seguridad heredados; esta
  remediación no los corrige.

