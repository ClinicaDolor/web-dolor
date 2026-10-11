# Documentación funcional — Área médica de pacientes

## Estado de evidencia

- **Verificado:** comportamiento observado en código, rutas y pruebas
  sintéticas locales.
- **Inferido:** interpretación de etiquetas o agrupaciones visuales que no
  constituyen una regla clínica nueva.
- **Pendiente:** aspecto que requiere confirmación del equipo clínico o del
  esquema productivo.

## Listado de pacientes

Ruta verificada: `/clinica/pacientes`.

El controlador `ClinicaController::pacientesIndex()` valida la sesión clínica,
obtiene el contexto de autorización y solicita los registros mediante
`ClinicaModel::getPacientesByClinica($auth['id_clinica'])`. La consulta usa
parámetro `:id_clinica` y devuelve únicamente las columnas que la vista
presenta: identificador, fecha de alta, nombre, edad, sexo y fecha de
nacimiento.

La vista conserva:

- búsqueda de Simple-DataTables;
- ordenamiento por fecha de alta;
- selección de 10, 20 o 50 registros por página;
- estado de página, búsqueda, orden y cantidad en `localStorage`;
- alta de paciente;
- apertura de expediente;
- gestión de PIN;
- historia clínica;
- edición del paciente.

La prueba sintética mostró un aislamiento correcto: el médico de la clínica
`9101` recibió `9301` y el médico de la clínica `9102` recibió `9302`.

## Alta y edición

Rutas verificadas:

- Alta: `/clinica/paciente/nuevo`.
- Persistencia: `POST /clinica/paciente/insert-edit`.
- Cancelación: `/clinica/pacientes`.

`ClinicaController::pacienteNuevo()` prepara el shell y la vista; el mismo
formulario se reutiliza para edición mediante `pacienteEditar($idPaciente)`.
`GuardarPaciente(idPaciente)` conserva el payload histórico y delega la
persistencia a `ClinicaModel::insertPaciente()` o `editPaciente()` según el
identificador.

Los 32 controles están organizados visualmente en:

1. Identificación clínica.
2. Referencia y motivo de atención.
3. Dirección actual.
4. Contacto y responsables.

La agrupación es visual y no cambia el orden contractual del payload, los IDs,
el endpoint ni la navegación posterior.

## Reglas observables y pendientes

- Los teléfonos conservan entrada numérica, máximo de 10 caracteres y patrón
  de 10 dígitos donde ya existía.
- CURP conserva normalización a mayúsculas mediante `mayus()`.
- La interfaz marca campos con `*` conforme al formulario anterior, pero el
  repositorio no contiene una validación de servidor formal para cada uno.
  La obligatoriedad clínica debe confirmarse y formalizarse antes de hacerla
  más restrictiva.
- La respuesta de guardado muestra el mensaje del endpoint o un mensaje
  genérico de conexión; no expone excepciones JavaScript.
- La redirección posterior depende de que el esquema entregue un ID generado.
  El fixture de auditoría declara `pc_paciente.id` como `AUTO_INCREMENT` y la
  persistencia sintética con ID generado fue verificada en una base aislada.

## Evidencia

Las capturas y resultados están registrados en `docs/testing.md` bajo “Área
médica — listado y alta de pacientes — 2026-10-10”.

## Expediente individual — ACT-05A (auditoría)

Ruta verificada: `/clinica/paciente/{idPaciente}`.

El flujo observado es:

1. `app/Config/routes.php` dirige la ruta a
   `ClinicaController::pacienteDetalle()`.
2. El controlador valida la sesión `clinica` y llama a
   `AuthMiddleware::assertPatientAccess()` antes de renderizar.
3. `PacienteModel` obtiene la ficha base desde `pc_paciente` y la vista
   `app/views/clinica/pacientes-detalle.php` construye el expediente.
4. Las tablas de notas, laboratorio, recetas y COFEPRIS se cargan después
   mediante `/buscar/tabla-*` y sus modelos respectivos.

### Inventario comprobado

La vista contiene ocho secciones: información del paciente, motivo de
atención, PIN de acceso, nota subsecuente, laboratorio, receta médica,
COFEPRIS e historia clínica. Se observaron seis tablas: `tablePin`,
`tableNotasSubsecuentes`, `tableLaboratorio`, `tableRecetas`, `tableCofepris`
y `table2`.

Las acciones visibles conservadas por el código son edición del paciente,
generación de PIN, alta de nota subsecuente, alta de laboratorio, alta de
receta, alta de COFEPRIS, detalle/edición de los registros listados y enlaces
de edición y resumen de cada módulo de historia clínica.

### Dependencias clínicas observadas

- Notas: `NotaSubsecuenteModel`, `nota_subsecuente` y rutas de nota subsecuente.
- Laboratorio: `LaboratorioModel`, `laboratorio`, descarga de archivo y rutas
  de laboratorio.
- Recetas: `RecetaModel`, `receta_medica` y rutas de receta.
- COFEPRIS: `CofeprisModel`, `cofepris`, `cofepris_folio`, PDF y rutas de
  COFEPRIS.
- Historia clínica: `historia_clinica_modulos`, `PacienteModulosModelo` y las
  tablas de respuestas de cada módulo.

### Fixture aislado

La base sintética de ACT-05A incorpora únicamente el DDL verificado de las
tres tablas que faltaban en `tests/fixtures/security-fixtures.sql` y un
registro sintético por tabla para ejercitar la representación del expediente.
La comparación contra `information_schema` de la base local devolvió:

| Tabla | Resultado | Columnas |
|---|---|---:|
| `nota_subsecuente` | Coincide con el esquema local | 15 |
| `laboratorio` | Coincide con el esquema local | 10 |
| `cofepris` | Coincide con el esquema local | 16 |

No se añadió `cofepris_folio`, ni un archivo clínico descargable, porque no
son necesarios para renderizar el expediente y su incorporación requeriría
otra prueba aislada de los flujos de alta/archivo. Son pendientes de ampliar
si se autoriza la fase de implementación.

### Hallazgos pendientes de implementación

- La vista consulta `pc_paciente_acceso` directamente y los modelos de tablas
  construyen parte de sus filtros SQL mediante concatenación; la pertenencia
  se verifica antes en los endpoints auditados, pero la parametrización debe
  revisarse antes de modernizar el expediente.
- Las cuatro cargas AJAX se invocan desde `pageshow` y `DOMContentLoaded`, y
  no tienen un `catch` visible que traduzca errores de red o del servidor a un
  estado clínico comprensible.
- Antes de ACT-05B-01 el detalle usaba `footer-mvsd.php`; desde esta actividad
  utiliza `footer-doctor.php`, el footer compartido del shell médico.
- El fixture reproduce sólo el módulo sintético disponible; la cobertura de
  los nueve módulos y sus tablas de respuestas queda pendiente.

## ACT-05B-01 — Shell y encabezado

La vista conserva sus ocho secciones y ahora utiliza el shell médico compartido:

- `body.app-shell-body.app-shell-doctor` para activar los tokens y layout del
  área médica.
- `search-bar-doctor.php` como topbar común.
- `footer-doctor.php` como footer compartido.
- `patient-record-header` como cabecera visual del expediente.

La cabecera muestra el nombre, el identificador `idPaciente`, edad, sexo y
fecha de alta, todos provenientes del controlador y del modelo existentes. No
se añadieron diagnósticos, alertas, alergias ni estados clínicos nuevos.

Las acciones verificadas son `/clinica/pacientes`,
`/clinica/paciente/editar/{idPaciente}` y el control existente `toggleSize()`.
La autorización de la vista no cambió y sigue dependiendo de
`AuthMiddleware::assertPatientAccess()`.

## ACT-05B-02 — Composición estructural

La vista conserva los ocho bloques funcionales principales observables en la
fuente: información del paciente, motivo, PIN, nota subsecuente, laboratorio,
receta, COFEPRIS e Historia Clínica. La identificación, contacto y motivo se
agrupan únicamente a nivel visual en `patient-record-summary-card`.

La Historia Clínica (`table2`) aparece inmediatamente después del resumen y
mantiene sus porcentajes, enlaces de edición y enlaces de resumen. Las cinco
tarjetas operativas restantes conservan sus IDs y cargas AJAX:
`tablePin`, `tableNotasSubsecuentes`, `tableLaboratorio`, `tableRecetas` y
`tableCofepris`.

El expediente se distribuye en dos columnas para las tarjetas secundarias en
escritorio y en una columna en tableta/móvil. El control `toggleSize()` sigue
siendo funcional. La autorización continúa delegada a
`AuthMiddleware::assertPatientAccess()` y no se añadieron datos ficticios al
runtime.

El código contiene nueve casos de cálculo de porcentaje de módulo (`1` a `9`);
la cobertura de sus tablas de respuestas continúa pendiente en fixtures.

## ACT-05B-03A — Registros clínicos

El expediente conserva cinco tarjetas operativas: PIN, nota subsecuente,
laboratorio, receta médica y COFEPRIS. Los botones de creación mantienen las
funciones y parámetros existentes. Las respuestas de los endpoints AJAX
continúan siendo las tablas generadas por los modelos; la vista sólo decide si
renderiza la tabla o un estado de carga, vacío o error genérico.

La evidencia sintética verificó registros para el paciente `9301` y estados
vacíos para las cuatro cargas auxiliares del paciente `9302`, autenticado con
el médico de su propia clínica. El acceso cruzado fue rechazado por la
autorización existente. No se expone el PIN en los estados vacíos ni se
agregan datos clínicos al expediente.

### Correspondencia de acciones

| Tarjeta | Acción visible | Función preservada |
|---|---|---|
| PIN | Nuevo PIN | `NuevoPin(idPaciente)` |
| Nota subsecuente | Nueva nota | `NuevaNotaSubsecuente(idPaciente, referencia)` |
| Laboratorio | Nuevo laboratorio | `NuevoLaboratorio(idPaciente)` |
| Receta médica | Nueva receta | `NuevaReceta(idPaciente)` |
| COFEPRIS | Nuevo registro | `NuevoCofepris(idPaciente)` |

Las acciones sobre filas existentes continúan siendo las generadas por cada
modelo (`DetalleNota`, `DetalleLaboratorio`, `DetalleReceta` y
`DetalleCofepris`), con sus enlaces de edición y descarga originales.

## ACT-05B-03B — Densidad de tarjetas

La compactación visual se aplicó únicamente a las cinco tarjetas operativas.
No modifica la información, las acciones, las tablas, los estados vacíos ni
la estructura de Historia Clínica. Los buscadores y tablas continúan creciendo
según su contenido, sin alturas fijas nuevas. En móvil se conserva el ajuste de
texto de las tablas ya definido en ACT-05B-03A.

## ACT-05B-06A — Historia Clínica

La vista mantiene `table2`, el buscador, el ordenamiento de Simple-DataTables y
las dos acciones por módulo:

- Editar: `clinica/{url}/paciente/{idPaciente}`.
- Ver resumen: `clinica/resumen/{url}/paciente/{idPaciente}`.

El catálogo se obtiene de `historia_clinica_modulos`. La fila `Porcentaje total`
es un resumen visual calculado con el mismo promedio existente; se muestra
fuera de `tbody` para que no participe en el conteo ni en la búsqueda como si
fuera un módulo. Los porcentajes de cada fila siguen siendo calculados por
`PacienteModulosModelo::porcentajeModulo1` a `porcentajeModulo9`.

Cada porcentaje se presenta en número y barra, y la barra expone el mismo valor
en `aria-valuenow`. No se agregaron módulos, estados clínicos, diagnósticos ni
datos al runtime. En la base sintética de esta actividad sólo se encuentra el
módulo de prueba; la cobertura de nueve módulos requiere ampliar primero el
fixture con sus tablas de respuestas, fuera del alcance visual de ACT-05B-06A.

## ACT-05B-03C — Padding de tarjetas clínicas

Se recuperó el equilibrio interior de las tarjetas PIN, Nota subsecuente,
Laboratorio, Receta médica y COFEPRIS sin alterar sus cinco cargas AJAX,
tablas, estados, acciones, búsqueda, paginación ni permisos. El ajuste es
visual y está delimitado por `body.doctor-patient-record-page` y
`.patient-record-data-panel`.

Los datos sintéticos de paciente 9301 conservaron registros visibles en las
cinco tarjetas. Los datos sintéticos de paciente 9302 conservaron el PIN y
los cuatro estados vacíos auxiliares. La separación del cuerpo respecto del
encabezado y el padding de cada estado se verificaron mediante estilos
computados en 1440, 1280, 768 y 375 px. No se agregaron registros ni se
ejecutaron operaciones clínicas.

## ACT-05B-06B — Acciones de Historia Clínica

La Historia Clínica conserva una sola columna `Acciones` en lugar de las dos
columnas anteriores. Cada fila mantiene exactamente sus destinos construidos
con `registro[url]` e `idPaciente`:

- `Editar módulo`: `clinica/{url}/paciente/{idPaciente}`.
- `Ver resumen`: `clinica/resumen/{url}/paciente/{idPaciente}`.

El menú se genera por módulo, no comparte identificadores entre filas y se
clona temporalmente fuera de la tabla para evitar recortes. Las búsquedas y
redibujados cierran y eliminan el menú activo. El catálogo, porcentajes,
`aria-valuenow`, fila `Porcentaje total` y permisos existentes permanecen sin
cambios.

## ACT-05B-04A — Operación JavaScript del expediente

La carga del expediente individual conserva las cuatro consultas AJAX de
Notas Subsecuentes, Laboratorio, Recetas y COFEPRIS. La vista inicia esas
consultas una sola vez aunque ocurran `pageshow` y `DOMContentLoaded`; no se
modificaron sus endpoints, parámetros, respuestas ni permisos.

El control existente `toggleSize()` continúa alternando las tarjetas
`.resizable` y aplicando el orden definido por `data-index`. Puede ejecutarse
aunque jQuery no esté disponible porque su registro y el ocultamiento del
loader son nativos. Simple-DataTables y Feather se inicializan sólo cuando sus
archivos locales exponen la API esperada.

La regresión aislada verificó paciente sintético 9301 con registros y paciente
sintético 9302 fuera de la clínica de la sesión. No se ejecutaron operaciones
clínicas de escritura, descargas ni cambios de datos.
