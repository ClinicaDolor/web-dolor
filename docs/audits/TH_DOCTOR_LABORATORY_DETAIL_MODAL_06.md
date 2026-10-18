# TH-DOCTOR-LABORATORY-DETAIL-MODAL-06

## Alcance

Sustitución visual del bloque fijo «Detalle del laboratorio» por un modal de consulta en la vista Doctor de laboratorios. No se modificaron controladores, modelos, rutas, esquema, datos ni persistencia.

## Identificación verificada

- Vista: `app/views/clinica/pacientes-laboratorio.php`
- Ruta de pantalla: `/clinica/laboratorio/paciente/{idPaciente}`
- Controlador de pantalla: `ClinicaController::pacienteLaboratorio`
- Consulta de detalle preservada: `GET /buscar/laboratorio/{idLaboratorio}`
- Controlador de detalle: `BusquedasController::buscarLaboratorio`
- Autorización existente preservada: el controlador obtiene el paciente del registro y ejecuta `guardPatient()` antes de devolver el detalle.
- Descarga preservada: el HTML existente de `LaboratorioModel::getLaboratorio()` continúa siendo el contenido recibido por el modal, incluido su enlace autorizado de descarga.

## Cambios realizados

- Eliminada únicamente la tarjeta fija de detalle y la consulta inicial `ultimoLaboratorio()` que la alimentaba.
- Añadido `#detalle-laboratorio-modal`, separado del modal existente `#nuevo-laboratorio-modal`.
- Las filas de la tabla conservan su ID real, `role="button"`, foco de teclado y etiqueta accesible.
- El clic, Enter y Espacio se gestionan mediante delegación sobre `#table1`, compatible con redibujos de Simple-DataTables y sin handlers inline duplicados.
- `DetalleLaboratorio()` reutiliza el endpoint existente y conserva el HTML de detalle recibido.
- Se añadió estado de carga, error genérico, invalidación de respuestas obsoletas y limpieza al cerrar.
- Escape, X, foco de cierre, retorno del foco a la fila y ciclo de tabulación quedan implementados para el modal de detalle; el modal de creación comparte el bloqueo de scroll sin quedar activo simultáneamente.
- El CSS añadido está scoped a `body.app-shell-body.doctor-patient-lab-page` y conserva el layout de tabla de ancho completo.

## Preservación

- Simple-DataTables, búsqueda, ordenamiento descendente por fecha y paginación permanecen configurados.
- El modal de creación, sus IDs, validaciones, AJAX y acción de guardado no fueron rediseñados en esta actividad.
- No se modificaron `app/controllers/`, `app/models/`, `app/Config/routes.php`, `.env` ni bases de datos.
- Se preservaron los cambios locales preexistentes del archivo de vista y de `app.css`.

## Validación

| Comprobación | Resultado | Evidencia / limitación |
|---|---|---|
| PHP lint de la vista | PASS | `No syntax errors detected` |
| Sintaxis JavaScript inline | PASS | 2 bloques inline, compilados con `new Function` tras sustituir sólo el placeholder PHP de tamaño |
| IDs HTML duplicados | PASS | 22 IDs inspeccionados; sin duplicados |
| Contrato de tabla y modal | PASS | Sin `detalleReceta`, sin `ultimoLaboratorio()` en la vista, sin handlers inline de detalle; `fixedHeight: false` conservado |
| Integridad CSS | PASS | Conteo de llaves balanceado |
| `git diff --check` | PASS | Código de salida 0; los avisos LF/CRLF corresponden a archivos locales históricos no afectados por esta corrección |
| GET/AJAX autenticado | NO VERIFICADO | El entorno Codex no alcanzó `clinica.test`/XAMPP; no se ejecutaron HTTP ni base de datos |
| Navegador, consola y responsive | NO VERIFICADO | Pendiente de comprobación real a 375, 430, 768, 1024 y 1440 px |

## Estado

`PARTIAL — CODE_READY_RUNTIME_PENDING`

La corrección estática quedó preparada y el detalle fijo fue sustituido. Falta la comprobación visual y funcional en navegador con una sesión autorizada para confirmar apertura por clic/teclado, carga del detalle, descarga, cierre, foco, errores de red y responsive.

Base de datos accedida durante esta actividad: **NO**. Escrituras clínicas: **NO**. Commits: **NINGUNO**. Despliegues: **NINGUNO**.
