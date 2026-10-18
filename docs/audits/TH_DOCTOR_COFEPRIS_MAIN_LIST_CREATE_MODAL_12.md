# TH-DOCTOR-COFEPRIS-MAIN-LIST-CREATE-MODAL-12

## Alcance y resultado

Se trabajó exclusivamente la ruta global `GET /clinica/cofepris`, que el código registra en `app/Config/routes.php` y resuelve mediante `HomeController::indexCofepris()` en `app/views/clinica/configuracion-cofepris.php`.

**STATUS: PARTIAL — implementación estática lista para revisión visual; persistencia y navegador no verificados.**

La ruta global no es una vista de expediente. Su operación de creación existente administra rangos de folios de la clínica y carga un PDF en `POST /clinica/cofepris/insert-folios`; no crea un registro clínico de paciente. Por ello no se añadió un selector de paciente ni se inventó un `idPaciente`.

## Estructura anterior

- Hero institucional de configuración.
- Tarjeta permanente «Agregar folios» con `Folio` y `Archivo`.
- Tarjeta independiente «Folios COFEPRIS» con el listado AJAX.
- La tabla se solicitaba mediante `/buscar/tabla-cofepris-folios` y se inicializaba con Simple-DataTables.

## Estructura implementada

- Se conserva el hero de configuración.
- Se conserva una sola tarjeta principal de registros, a ancho completo.
- El encabezado de la tarjeta contiene el botón accesible «Nuevo registro».
- El formulario de folios se movió a un único modal `#cofepris-config-modal`.
- El modal tiene X, Cancelar, Escape, retorno de foco, bloqueo del scroll de fondo y scroll interno para contenido largo.
- No se añadió navegación, `pushState`, una ruta nueva ni un segundo formulario.

## Inventario de campos y contratos conservados

| Elemento | Estado |
| --- | --- |
| `#Folio` / Folio inicial | Conservado, único |
| `#Archivo` / Archivo PDF | Conservado, único; se mantiene aceptación PDF |
| Mensaje `#mensaje` | Conservado, único |
| `POST /clinica/cofepris/insert-folios` | Reutilizado |
| `GET /buscar/tabla-cofepris-folios` | Conservado |
| Búsqueda, ordenamiento y paginación de Simple-DataTables | Conservados |
| Tabla y acciones de folios | No modificadas |

El estado de folios activos continúa controlando la disponibilidad del alta: cuando existe un rango activo, el disparador y los controles permanecen deshabilitados, igual que antes.

## Seguridad y contexto

- **Clínica:** `HomeController::indexCofepris()` autentica mediante `AuthMiddleware('clinica')->authPermisos()`; el endpoint existente también conserva esa autenticación.
- **Paciente:** no aplica a esta operación global de folios; no se añadió un paciente implícito, fijo ni «último visitado».
- **Autorización de paciente:** no aplica en el endpoint de folios.
- **CSRF:** el contrato actual de `insertFolios()` no recibe ni valida token CSRF. La vista no tenía un token que preservar. Esto queda como riesgo pendiente; no se modificó el controlador ni se introdujo un token ficticio en esta tarea.
- **Archivos:** se conserva el flujo existente de carga al endpoint y la restricción de selección PDF del control. No se modificó almacenamiento, PDF ni validación backend.
- No se ejecutaron POST, SQL, cargas de archivo ni pruebas sobre datos reales.

## Manejo del modal y guardado

- Abrir no ejecuta POST ni cambia el historial.
- Cancelar, X, clic fuera y Escape cierran sin guardar.
- El foco vuelve al botón «Nuevo registro».
- El formulario usa una sola escucha de `submit` y `preventDefault()`.
- `cofeprisFoliosRequestInFlight` bloquea envíos duplicados.
- Las respuestas HTTP no exitosas se tratan como error.
- Los errores mantienen abierto el modal y muestran un mensaje seguro.
- El éxito limpia los campos, cierra el modal y solicita de nuevo el listado.

Estos comportamientos son conclusiones de inspección estática; no se han demostrado mediante HTTP real.

## Archivos modificados en esta tarea

- `app/views/clinica/configuracion-cofepris.php`
- `public/assets/css/app.css` — reglas scoped a `doctor-cofepris-config-page`.
- `tests/doctor-cofepris/main-list-create-modal-12-contract.php`
- Este documento.

No se modificaron `app/Controllers`, `app/Models`, rutas, esquema, `.env`, `app/views/clinica/pacientes-cofepris.php`, `app/views/clinica/cofepris-folios.php`, Laboratorio, Recetas ni Portal Paciente.

## Validación ejecutada

| Prueba | Resultado |
| --- | --- |
| PHP lint de la vista objetivo | PASS |
| PHP lint de `HomeController.php`, `CofeprisController.php`, `CofeprisModel.php` | PASS; sólo inspección, sin cambios |
| Sintaxis JS inline de la vista objetivo | PASS (`node --check`) |
| Contrato focalizado 12 | PASS |
| Contrato COFEPRIS de historial 02 | PASS |
| Contrato COFEPRIS de navegación 02 | PASS |
| Contrato sidebar global 11 | PASS_STATIC |
| Contrato layout toggle global 10 | PASS_STATIC |
| Integridad estructural CSS | PASS — llaves balanceadas 4205/4205 y bloque 12 marcado |
| `git diff --check` | PASS; sólo advertencias históricas LF/CRLF de otros archivos |
| Contrato histórico `ux-implementation-01` | FAIL preexistente en `pacientes-cofepris.php`: falta la clase esperada `col-12 resizable`; esa vista ya estaba modificada y no fue tocada |
| Navegador, consola, responsive y capturas | NO VERIFICADO |
| HTTP, POST, persistencia y actualización real de tabla | NO VERIFICADO |
| Base de datos | NO ACCEDIDA |

## Riesgos y pendientes

1. El endpoint global de folios no tiene protección CSRF identificable; requiere una actividad de seguridad independiente antes de considerar el flujo listo para producción.
2. La carga y transformación del PDF no se ejecutaron en esta intervención.
3. Debe realizarse una comprobación manual o sintética aislada en Chrome/XAMPP para confirmar apertura, validación de archivo, cancelación, Escape, responsive y actualización real de la tabla.
4. El fallo histórico del contrato `ux-implementation-01` pertenece a la vista contextual por paciente y no se corrigió por la restricción de alcance.

**BROWSER_VALIDATION: NOT_EXECUTED**  
**DATABASE_PERSISTENCE: NOT_VERIFIED**  
**BACKEND_MODIFIED: NO**  
**DATABASE_MODIFIED: NO**  
**SCHEMA_MODIFIED: NO**  
**COMMITS: NONE**  
**DEPLOYMENTS: NONE**  
**READY_FOR_OWNER_VISUAL_REVIEW: YES — con las limitaciones anteriores**
