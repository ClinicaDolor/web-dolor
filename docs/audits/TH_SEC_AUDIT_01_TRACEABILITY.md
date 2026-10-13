# TH-SEC-AUDIT-01 — Auditoría de trazabilidad, seguridad e integridad

**Proyecto:** Clínica del Dolor
**Alcance:** Historia Clínica Tradicional P01–P11, persistencia asociada, Doctor/D40 y flujos documentales relacionados
**Fecha de revisión:** 2026-10-12
**Modo:** diagnóstico de solo lectura
**Base sintética de referencia:** 'clinica_dolor_demo_20261010_final'
**Base prohibida:** 'bd_clinica'

## A. Resumen ejecutivo

Esta auditoría contrasta el cierre histórico con el código y la trazabilidad registrada en las rutas actuales. No se modificó código de aplicación, configuración, esquema, permisos ni datos. Tampoco se ejecutaron escrituras clínicas.

La contradicción de TH-GET-001 se resuelve de forma acotada: para las rutas GET actuales de P04–P11 no se demostró una escritura clínica activa en el call graph revisado. Los renderizadores actuales leen catálogos/respuestas y las operaciones de inicialización que insertan filas vacías permanecen como métodos heredados o son invocadas desde POST. La llamada de 'AntecedentesNoPatologicosModel::antecedentesNoPatologicos()' desde 'capturarRespuestaEntrevista()' pertenece a Entrevista Conversacional, que está explícitamente fuera de este alcance; no se auditó ni modificó.

El resultado no equivale a seguridad integral. Persisten riesgos de parametrización SQL heredada, endpoints documentales con controles de propiedad incompletos o no visibles en el código revisado, y dependencia de '.htaccess' para mapas P11. El documento fuente de ACT-05C-02 no fue localizado; por ello no se reconstruyen sus hallazgos históricos.

## B. Preflight y evidencia utilizada

- Rama: 'Silvino'.
- HEAD: '1b08d9b4c41080c176fbe4588212863f3a1ac18c'.
- Directorio: 'C:\xampp\htdocs\clinica-dolor'.
- Worktree: ya contenía cambios locales y archivos no rastreados; se preservaron.
- Base verificada de forma read-only: 'clinica_dolor_demo_20261010_final'.
- No se ejecutaron escrituras sobre 'bd_clinica'.
- Procesos Apache/PHP estaban presentes, pero no había listener confirmado en los puertos inspeccionados durante este preflight; no se detuvieron procesos ajenos.
- Evidencia previa reutilizada sólo como evidencia previa, no como nueva ejecución: 'PHASE_02_GET_WRITE_REMEDIATION.md', 'PHASE_03B_P06_FINALIZATION_IDEMPOTENCY.md' y 'PHASE_05B_GLOBAL_FUNCTIONAL_REGRESSION.md'.

## C. Rutas GET y efectos secundarios

La ruta común '/historia-clinica/{modulo}/paciente/{idPaciente}' llega a 'ModulosController::moduloVistaPaciente()'. La variante Doctor '/clinica/{modulo}/paciente/{idPaciente}' llega a 'ModulosController::moduloVistaDoctor()'. Los dos caminos pasan por autenticación y control de pertenencia; los AJAX de contenido llaman a 'BusquedasController::guardPatient()', que usa 'AuthMiddleware::assertPatientAccess()'.

| Módulo | GET/handler actual inspeccionado | Operación de lectura | Escritura durante GET actual | Estado |
|---|---|---|---|---|
| P04 | 'contenidoPreguntasM2' → 'AntecedenteFamiliarModel::mostrarPreguntasM2()' | SELECT de preguntas/respuestas | No demostrada | 'GET_READ_ONLY_CONFIRMED' por call graph y evidencia PHASE-02 |
| P05 | 'contenidoPreguntasM3' → 'mostrarPreguntasM3()' | Catálogo + LEFT JOIN de respuestas | No demostrada | 'GET_READ_ONLY_CONFIRMED' |
| P06 | 'contenidoPreguntasM4' → 'mostrarPreguntasM4()' | Lectura de catálogo/respuestas | No demostrada | 'GET_READ_ONLY_CONFIRMED' |
| P07 | 'contenidoPreguntasM5V1/V2' → 'mostrarPreguntasM5V1/V2()' | Catálogo + LEFT JOIN de respuestas | No demostrada | 'GET_READ_ONLY_CONFIRMED' |
| P08 | 'contenidoPreguntasM6' → renderer de medicación actual | Lectura de registros/catálogo | No demostrada | 'GET_READ_ONLY_CONFIRMED' |
| P09 | 'contenidoPreguntasM7' → 'mostrarPreguntasM7Paciente()' para paciente | Catálogo + LEFT JOIN de respuestas | No demostrada | 'GET_READ_ONLY_CONFIRMED'; la afirmación histórica de siembra no corresponde al renderer vigente |
| P10 | 'contenidoPreguntasM8/M8V2' → 'p10Procedimientos()'/'p10Tratamientos()' para paciente | Catálogo + respuestas | No demostrada | 'GET_READ_ONLY_CONFIRMED' |
| P11 | 'contenidoPreguntasM9/M9V2/M9V3' → 'p11MostrarMapa()'/'p11MostrarCuestionario()' para paciente | Lectura autorizada de mapa/cuestionario | No demostrada | 'GET_READ_ONLY_CONFIRMED' |
| D40 | '/clinica/resumen/{modulo}/paciente/{idPaciente}' y contenido de resumen | Lecturas de resumen, incluidas consultas legacy de evaluación | No se demostró escritura nueva en la evidencia previa | 'PARTIAL'; revisar consumidores legacy antes de producción |

### Inicializadores heredados

Se localizaron métodos que hacen SELECT COUNT seguido de INSERT vacío:

- 'app/Models/AntecedenteFamiliarModel.php:425' — 'antecedentesFamiliares()'.
- 'app/Models/AntecedentesNoPatologicosModel.php:457' — 'antecedentesNoPatologicos()'.
- 'app/Models/AntecedentesPatologicosModel.php:82' — 'antecedentesPatologicos()'.
- 'app/Models/MedicacionDolorModel.php:51' — 'medicacionDolorQuest()'.
- 'app/Models/ProcedimientosDolorModel.php:27' — 'procedimientosModulo8()'.
- 'app/Models/EvaluacionDolorModel.php:14' — 'cuestionarioModulo9()'.

Los call-sites actuales encontrados no demuestran que esos inicializadores sean llamados por los GET P04–P11 registrados. Los de P05 y P07 aparecen en caminos POST; la llamada de P05 dentro de 'capturarRespuestaEntrevista()' queda fuera de esta auditoría. Los inicializadores de P04, P09, P10 y P11 no mostraron call-site vigente desde los handlers GET inspeccionados. Se clasifican como legacy o riesgo de mantenimiento, no como escritura GET activa demostrada.

### Distinción histórica

El cierre histórico conserva afirmaciones de “siembra durante GET” para P04/P05/P07/P09. PHASE-02, la revisión de los handlers actuales y la inspección de call-sites muestran que esas afirmaciones no describen el camino GET vigente. Se conserva el historial; este informe corrige únicamente el estado de investigación actual y no declara que los métodos heredados sean seguros si se invocan por otro camino.

## D. ACT-05C-02

Se buscó 'ACT-05C-02', 'ACT_05C_02' y '05C-02' en documentación, auditorías, seguridad, arquitectura y referencias del historial Git disponible. No se localizó el documento fuente ni un registro primario con endpoints, fecha, evidencia y severidad originales.

**Estado:** 'ACT_05C_02_SOURCE: NOT_FOUND'.

No se atribuye al documento ausente ningún hallazgo nuevo. Se inspeccionan abajo flujos documentales independientes sólo para conocer el estado actual del código. Los riesgos históricos de PDF, descargas y almacenamiento permanecen abiertos hasta obtener la fuente original y ejecutar una remediación autorizada.

## E. Inventario documental, PDF y descargas

| Endpoint/ruta | Código inspeccionado | Control observado | Estado de auditoría |
|---|---|---|---|
| 'POST /pdf/receta/{idReceta}' | 'app/Controllers/PdfController.php:14' | Carga receta y paciente y genera TCPDF; el controlador no instancia 'AuthMiddleware' ni llama 'assertPatientAccess()' | 'STATIC_RISK_ONLY', autorización no demostrada en este endpoint |
| 'POST /pdf/receta-externo/{id}' | 'PdfController::pdfRecetaExterno()' alrededor de línea 235 | Carga 'RecetaExternoModel' y genera PDF; no se observó comprobación de clínica/propietario | 'STATIC_RISK_ONLY', especialmente sensible porque externos no tienen propietario clínico verificable |
| 'GET /pdf/cofepris/{id}' | 'PdfController::pdfCofepris()' alrededor de línea 372 | Lee 'public/storage/cofepris/{carpeta}/{folio}.pdf'; no se observó autenticación ni assert de paciente en el método | 'STATIC_RISK_ONLY' de alta prioridad |
| 'GET /descargar/{ruta}/{archivo}' | 'DownloadController::download()' | Exige sesión Doctor; usa 'realpath', 'basename', prefijo de storage y rechaza fuera de 'public/storage' | Control de traversal observado; propiedad clínica del archivo no se vincula al usuario |
| 'GET /receta/{idReceta}' | 'RecetaController::receta()' | Carga el registro y después ejecuta 'assertPatientAccess()' con el paciente de la receta | Control propio/cruzado visible; carga previa al assert debe conservarse en pruebas autorizadas |
| 'GET /receta-externo/{id}' | 'ClinicaController::recetaExternoDetalle()' | 'authPermisos()'; no se observó propiedad de clínica o paciente | 'CONFIRMED_ACTIVE' como brecha estática de propiedad; reproducción cruzada no ejecutada en esta auditoría |
| 'GET /receta-externo/editar/{id}' | 'ClinicaController::recetaExternoEditar()' | 'authPermisos()'; no se observó propietario comprobable | 'CONFIRMED_ACTIVE' como brecha estática de propiedad |
| 'GET /buscar/tabla-recetas-externos' y '/buscar/receta-externo/{id}' | 'BusquedasController.php:83–95' | 'guardClinic()' únicamente; consulta catálogo externo global | 'CONFIRMED_ACTIVE' como riesgo de aislamiento, fuera de la contención del listado D14 |
| 'GET /cofepris/{id}', '/buscar/cofepris/{id}' | 'CofeprisController'/'BusquedasController' | Se observa carga y luego 'assertPatientAccess()' en los handlers de registro | 'PARTIAL'; no se ejecutó prueba dinámica de documento/PDF |
| 'GET /laboratorio/{idLaboratorio}' y '/buscar/laboratorio/{idLaboratorio}' | controladores/modelo de laboratorio | Carga y valida paciente mediante 'assertPatientAccess()' | 'PARTIAL'; almacenamiento y descarga no fueron ejecutados |
| 'GET /nota-subsecuente/{idNota}' y '/buscar/nota-subsecuente/{idNota}' | controladores/modelo de nota | Se observó validación de paciente en los handlers principales | 'PARTIAL'; generación documental no ejecutada |

El uso de 'downloadPDF()' en resúmenes Doctor y la ruta 'POST /pdf/...' deben probarse con autorización de registro y aislamiento de clínica antes de producción. Esta auditoría no ejecutó PDF, descarga ni impresión.

## F. TH-SQL-002 — Matriz de SQL heredado

La concatenación se clasifica como riesgo de parametrización y mantenibilidad. No se afirmó inyección explotable sin una reproducción. 'assertPatientAccess()' convierte el identificador de paciente a entero antes de consultar propiedad, lo cual reduce la superficie en varios caminos, pero no convierte automáticamente todos los 'query()' legacy en parametrizados.

| Archivo/línea | Método/patrón | Entrada/origen | Ruta/call-site | Clasificación |
|---|---|---|---|---|
| 'AntecedenteFamiliarModel.php:92' | SELECT por 'id_paciente' concatenado | parámetro de ruta después de 'guardPatient()' | AJAX P04 | 'STATIC_RISK_ONLY'; no explotación reproducida |
| 'RecetaModel.php:143' | tabla por 'id_paciente' concatenado | parámetro de ruta, tras guard del paciente | AJAX de tabla de recetas | 'STATIC_RISK_ONLY' |
| 'MedicacionDolorModel.php:29,248' | 'idTema'/'idPaciente' interpolados | identificadores de rutas o contexto | renderer legacy Doctor/P09 | 'STATIC_RISK_ONLY'; revisar parametrización focal |
| 'ProcedimientosDolorModel.php:46,149,396' | consultas de procedimiento concatenadas | 'idPaciente' de ruta/contexto | renderer legacy Doctor/D40 relacionado | 'STATIC_RISK_ONLY' |
| 'EvaluacionDolorModel.php:30,72' | evaluación por paciente concatenada | 'idPaciente' de contexto | 'editorTextoED()' y renderer legacy M9/D40 | 'STATIC_RISK_ONLY'; consumidor heredado activo en resumen |
| 'PacienteModulosModelo.php:64,71,121' | comentarios/finalización con ids interpolados | ids de módulo/paciente | datos auxiliares de módulos | 'STATIC_RISK_ONLY' |
| 'CofeprisModel.php:252' | tabla COFEPRIS por paciente concatenado | id de ruta tras guard | AJAX de tabla COFEPRIS | 'STATIC_RISK_ONLY' |
| 'NotaSubsecuenteModel.php:171,421' | cláusula '$qry_referencia' construida | referencia clínica | tablas de notas/laboratorio | 'NO_VERIFICADO'; requiere trazado de cada constructor y prueba segura |
| 'RecetaExternoModel.php:192' | catálogo global con 'query()' | sin propietario clínico | endpoint externo legacy | 'CONFIRMED_ACTIVE' como aislamiento insuficiente, no como SQL injection demostrada |

Las consultas preparadas existentes —por ejemplo 'assertPatientAccess()', los renderizadores actuales de P05/P07/P09/P10/P11 y las operaciones CRUD recientes— no se clasifican como riesgo de concatenación en esta matriz.

## G. P11 — almacenamiento y consumidores

### Almacenamiento y servidor

- Métodos legacy 'imagenFrenteModulo9()' y 'imagenEspaldaModulo9()' construyen nombres previsibles 'Frente_[FM]_{id}.png' / 'Espalda_[FM]_{id}.png' bajo 'public/assets/images/evaluacion-dolor/'.
- El flujo actual protegido usa 'ModulosController::pacienteImagenEvaluacionDolor()' y 'EvaluacionDolorModel::p11ImagePath()', con autorización previa.
- '.htaccess' de 'frente' y 'espalda' deniega los nombres específicos de paciente y permite las plantillas anatómicas genéricas.
- Evidencia previa bajo Apache/XAMPP: privados 403 y plantillas 200.
- Evidencia previa bajo PHP built-in: privado 200 porque '.htaccess' no se procesa. Esto no demuestra fallo de Apache, pero impide declarar privacidad universal.
- La configuración Apache leída establece 'Listen 80', 'AllowOverride All' y el VirtualHost 'clinica.test' con 'Require all granted' en el directorio del proyecto. Plesk/Nginx no fue verificado.

### Consumidores

| Consumidor | Archivo/método | Recurso | Autorización observada | Riesgo |
|---|---|---|---|---|
| Portal Paciente P11 | 'app/views/historia-clinica-paciente/evaluacion-dolor.php' | endpoint '/historia-clinica/paciente/imagen-evaluacion-dolor/{orientacion}/{id}' | controller + 'assertPatientAccess' | protegido por endpoint; navegador real no auditado en esta ejecución |
| Doctor P11 | 'app/views/historia-clinica-doctor/evaluacion-dolor.php' | endpoint '/clinica/paciente/imagen-evaluacion-dolor/...' y POST de edición/eliminación | controller y payload contextual | conservar prueba cruzada antes de producción |
| D40 resumen | 'app/views/resumen-clinica-doctor/evaluacion-dolor.php' | mismo endpoint protegido; 'editorTextoED()' para contenido | acceso del resumen + endpoint | depende además de SQL legacy y PDF/descarga no verificados |
| AJAX M9 | 'BusquedasController::contenidoPreguntasM9/M9V2/M9V3()' | mapa/cuestionario paciente nuevo o renderer Doctor legacy | 'guardPatient()' | mezcla ramas nuevas y legacy; requiere regresión antes de migrar |
| Renderers legacy | 'EvaluacionDolorModel::mostrarPreguntasM9', 'mostrarPreguntasFrenteM9', 'mostrarPreguntasEspaldaM9', 'editorTextoED' | filas HTML/mapas/tabla | call-sites existentes para Doctor/D40 | 'P11-LEGACY-002' abierto |
| Métodos de imagen legacy | 'imagenFrenteModulo9()' / 'imagenEspaldaModulo9()' | URL pública predecible | no se encontraron call-sites actuales registrados | 'CONFIRMED_UNREACHABLE_FROM_REGISTERED_CALL-SITES'; no equivale a eliminado |
| PDF/descarga | 'PdfController', 'DownloadController', 'CKEditor-utilities.js' | documentos/archivos, no mapa directamente | incompleto/no uniforme | riesgo documental separado |

No se inspeccionaron imágenes reales ni se ejecutaron acciones de escritura de mapas. Los archivos sintéticos existentes se trataron como fixtures, no como información clínica real.

## H. Dependencias entre componentes

GET /historia-clinica/{modulo}/paciente/{id}
  -> ModulosController::moduloVistaPaciente
  -> vista P04..P11
  -> BusquedasController::contenidoPreguntas...
  -> modelos actuales de lectura

GET /clinica/{modulo}/paciente/{id}
  -> ModulosController::moduloVistaDoctor
  -> renderers Doctor; P11 conserva ramas legacy M9

GET /resumen/{modulo}/paciente/{id}
  -> ModulosController::moduloVistaResumen
  -> resumen D40; P11 usa editorTextoED y endpoint de imagen protegido

POST de respuesta/finalización
  -> ModulosController
  -> métodos de inicialización/CRUD
  -> persistencia sintética

PDF/descarga
  -> PdfController / DownloadController
  -> recetas, COFEPRIS, almacenamiento documental

La dependencia compartida más sensible es 'AntecedentesNoPatologicosModel::capturarRespuestaEntrevista()': llama a un inicializador de P05, pero pertenece a Entrevista Conversacional y por instrucción expresa no se auditó ni se propone modificar.

## I. Hallazgos actuales

### Confirmados o de alta prioridad

1. **TH-AUTH-DOC-001 — autorización no visible en PDF.** 'PdfController' no muestra 'AuthMiddleware' ni 'assertPatientAccess()' en 'pdfReceta', 'pdfRecetaExterno' y 'pdfCofepris'. Las rutas están registradas directamente. Es un riesgo estático de alta prioridad; no se ejecutaron documentos.
2. **TH-AUTH-EXT-002 — propiedad de recetas externas no verificable.** Las rutas de detalle/edición y AJAX externo validan sesión Doctor, pero no muestran una relación de clínica/paciente propietaria. El catálogo fue contenido en D14, pero las rutas independientes continúan abiertas.
3. **P11-LEGACY-002 — consumidores y renderizadores legacy.** D40 y ramas Doctor M9 conservan métodos/SQL históricos; la migración completa no está demostrada.
4. **TH-SQL-002 — parametrización incompleta.** Hay consultas concatenadas en rutas GET y resúmenes. No se confirmó explotación.

### Legacy o no alcanzable con la evidencia disponible

- Inicializadores de filas vacías no llamados por los GET actuales inspeccionados: 'CONFIRMED_UNREACHABLE_FROM_REGISTERED_CALL-SITES', con reserva frente a invocación dinámica o rutas no registradas.
- 'imagenFrenteModulo9()'/'imagenEspaldaModulo9()' sin call-sites registrados: mismo estado; no se eliminan ni se consideran seguros por ello.
- La escritura durante GET histórica de P04/P05/P07/P09: 'FALSE_POSITIVE_FOR_CURRENT_GET_PATH', no falsa como observación histórica del método heredado.

## J. Riesgos sin evidencia suficiente

- Explotación dinámica de PDF, descarga, receta externa y COFEPRIS no ejecutada.
- Conteo exacto de sentencias SQL no-op en todos los módulos.
- Plesk/Nginx y cualquier servidor distinto de Apache/PHP built-in.
- Acceso directo a todos los nombres históricos de mapas en cada configuración de servidor.
- Rutas legacy no registradas o invocación dinámica.
- Concurrencia específica P04/P05/P07, recorrido persistido completo P01→P11, visual responsive real, audio audible humano, centrado P10 y decisión clínica de colores P11.

## K. Propuestas de remediación — no implementadas

1. Autorizar una fase separada para autenticación y autorización por paciente/clínica de todos los endpoints PDF y documentos.
2. Definir una política de propiedad de recetas externas/COFEPRIS antes de modificar rutas.
3. Parametrizar focalmente las consultas heredadas y añadir pruebas de acceso cruzado.
4. Consolidar P11 en almacenamiento privado o aplicar protección equivalente en cada servidor soportado; migrar consumidores sólo después de un mapa de regresión.
5. Separar o retirar inicializadores legacy únicamente después de confirmar la frontera con Entrevista Conversacional.

## L. Decisiones requeridas

- Entregar el informe original ACT-05C-02 o aceptar una nueva evaluación de seguridad documental independiente.
- Definir si las rutas PDF existentes deben ser públicas para un rol autenticado o requieren autorización por registro.
- Confirmar servidores soportados: Apache/XAMPP, PHP built-in sólo para harness, Plesk/Nginx u otros.
- Autorizar cualquier cambio que afecte componentes compartidos con Entrevista Conversacional.
- Obtener decisión clínica documentada sobre colores P11.

## M. Orden recomendado y regresiones posteriores

1. Autorización por recurso documental y recetas externas; probar propio, cruzado, sin sesión, PDF y descarga.
2. Parametrización SQL focal; repetir PHP lint, contratos, AJAX, autorización y regresión Doctor/D40.
3. Protección/migración de mapas P11; probar Apache y cada servidor soportado, endpoints, Doctor, D40, PDF y archivos históricos.
4. Revisión controlada de métodos GET heredados; repetir GET sin cambios de filas, POST, finalización e idempotencia P04–P11.
5. Evidencia visual real P01–P11 con navegador, consola, Network, captura y responsive.

## N. Cobertura y límites

Esta auditoría no inició una fase nueva ni corrigió los riesgos. No se auditó Entrevista Conversacional. No se ejecutaron POST, PDF, descargas, migraciones, cambios de permisos o acciones clínicas. El cierre visual de vistas no se interpreta como seguridad ni autorización de producción.

## Resultado formal

TH_SEC_AUDIT_01_TRACEABILITY:

- STATUS: COMPLETE
- CODE_CHANGES: NONE
- APPLICATION_FILES_MODIFIED: NONE
- GET_ROUTES_INSPECTED: '/historia-clinica/{modulo}/paciente/{idPaciente}', '/clinica/{modulo}/paciente/{idPaciente}', '/clinica/resumen/{modulo}/paciente/{idPaciente}', AJAX de contenido P04–P11, comentarios, imagen P11 y endpoints de tablas/documentos listados en 'routes.php'
- GET_WRITE_ACTIVE: none demonstrated for current registered P04–P11 GET call paths
- GET_WRITE_LEGACY: initializer methods P04/P05/P07/P09/P10/P11; P05 also shared with excluded Conversational Interview; current POST call-sites remain
- GET_WRITE_FALSE_POSITIVES: historical “active GET write” claims for current renderers P04/P05/P07/P09 are superseded for investigation purposes by PHASE-02/current call graph; legacy methods remain write-capable
- ACT_05C_02_SOURCE: NOT_FOUND
- ACT_05C_02_CONFIRMED_FINDINGS: none attributable to the missing original source; independent static document risks remain listed
- DOCUMENT_ENDPOINTS_INSPECTED: PDF recipe/internal/external/COFEPRIS routes, download route, recipe/lab/note/COFEPRIS detail and table AJAX routes, external recipe routes, P11 image endpoint
- SQL_QUERIES_INSPECTED: current P04–P11 renderers, finalization/comment helpers, Recipe/Lab/Note/COFEPRIS models, P11 legacy and D40 consumers
- SQL_CONFIRMED_RISKS: static parametrization risks in reachable legacy/read paths; no SQL injection exploit reproduced
- SQL_LEGACY_ONLY: initializer methods without current registered GET call-site, plus legacy P11 renderers/image URL builders without registered call-site
- P11_IMAGE_CONSUMERS: Patient P11 view, Doctor P11 view, D40 summary, Busquedas M9 branches, authenticated image controller
- P11_LEGACY_CONSUMERS: 'EvaluacionDolorModel' M9 renderers, 'editorTextoED', legacy image URL builders; direct public asset fallback under Apache rules
- APACHE_SECURITY: prior Apache/XAMPP evidence PASS for patient-specific PNG 403 and generic templates 200; 'AllowOverride All' confirmed read-only
- PHP_BUILTIN_SECURITY: prior direct private PNG test FAIL/HTTP 200 because '.htaccess' is ignored; not equivalent to Apache
- PLESK_SECURITY: NOT_VERIFIED
- CROSS_COMPONENT_DEPENDENCIES: GET renderers ↔ BusquedasController ↔ shared models; P11 Doctor/D40 ↔ legacy model/storage; PDF/download ↔ document storage; P05 initializer ↔ excluded Conversational Interview
- CONFIRMED_P0: none dynamically reproduced in this read-only audit; PDF/COFEPRIS authorization absence is high-priority static risk pending authorized reproduction
- CONFIRMED_P1: static document authorization gaps in PDF/external recipe routes; P11 legacy/server-dependence risk; reachable SQL concatenation risks
- CONFIRMED_P2: maintenance debt from legacy initializers/renderers and incomplete evidence coverage
- UNVERIFIED_RISKS: ACT-05C-02 original findings, PDF/download cross-patient access, Plesk/Nginx behavior, dynamic SQL exploitation, unregistered legacy routes, visual/audio gaps
- USER_DECISIONS_REQUIRED: obtain ACT source; define document ownership policy; authorize shared Conversational boundary changes; confirm supported servers; decide P11 colors
- RECOMMENDED_REMEDIATION_ORDER: document authorization → SQL parametrization → P11 storage/server parity → legacy GET cleanup → full functional/visual regression
- DOCUMENTATION_MODIFIED: 'docs/audits/TH_SEC_AUDIT_01_TRACEABILITY.md'; investigation-status appendix only in 'docs/audits/TRADITIONAL_HISTORY_CLOSURE_MASTER.md'
- WORKTREE_PRESERVED: YES; pre-existing changes retained
- PRODUCTION_READY: NO
