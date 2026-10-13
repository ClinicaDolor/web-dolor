# PHASE-02 — Remediación de escrituras clínicas durante GET

Fecha de ejecución: 2026-10-12  
Proyecto: `C:\xampp\htdocs\clinica-dolor`  
Base utilizada: `clinica_dolor_demo_20261010_final`  
Base prohibida: `bd_clinica`

## Resultado requerido

```text
PHASE_02_GET_WRITE_REMEDIATION:
- STATUS: PASS (alcance local sintético; no equivale a producción)
- GET_WRITE_INVENTORY: no quedan llamadas activas de escritura clínica durante GET en P04–P11 ni D40; los métodos legacy de inicialización permanecen sólo como código dormido o se invocan desde POST explícito
- CONFIRMED_ROOT_CAUSES: vistas/modelos que inicializaban filas vacías durante el render; consultas INNER JOIN que ocultaban preguntas sin respuesta; inicializadores históricos de P10/P11 con capacidad de escribir/copiar
- REMEDIATED_METHODS: renderizadores P04, P05 V1, P07 V1/V2 y P09; captura inicial trasladada a POST; D40 sin inicialización
- REMAINING_GET_WRITES: ninguno observado en los recorridos GET auditados; permanecen métodos legacy write-capable no invocados por GET y deben conservarse como riesgo de mantenimiento
- EMPTY_PATIENT_RENDER: PASS
- FIRST_POST_CAPTURE: PASS
- EXISTING_DATA_COMPATIBILITY: PASS en fixtures existentes; respuestas existentes se conservan y se actualizan por su propietario
- P01_P11_REGRESSION: PASS focal P04–P11; P01–P03 inspección estática de lectura, sin escritura clínica en sus vistas
- DOCTOR_READ_ONLY: PASS en D40 y resúmenes sintéticos
- D40_READ_ONLY: PASS
- P11_MAP_SECURITY: PASS; no se copiaron mapas durante GET y se mantuvieron las pruebas de acceso privado
- CROSS_PATIENT_AUTHORIZATION: PASS
- CLINICAL_DATA_DELTA_AFTER_GET: PASS en recorridos ejecutados
- SYNTHETIC_DATA_CLEANUP: PASS
- PHP_LINT: PASS
- CONTRACT_TESTS: PASS salvo el fallo histórico de P06 indicado abajo
- DIFF_CHECK: FAIL histórico en `app/Controllers/ModulosController.php:1000`; PASS focal para los archivos de esta fase excluyendo esa línea histórica
- MODIFIED_FILES: aplicación, pruebas P04/P05/P06/P07, prueba Doctor/D40 y este informe; ver lista exacta abajo
- PENDING_RISKS: métodos legacy write-capable, SQL heredado, pruebas de navegador/consola, no-op SQL no instrumentado, seguridad documental ACT-05C-02 y protección del entorno productivo
- PRODUCTION_READY: NO
```

El `STATUS: PASS` se limita a la remediación y validación local con datos sintéticos. No certifica producción, seguridad documental, despliegue ni cierre funcional de los módulos.

## Precheck y aislamiento

- HEAD registrado: `1b08d9b4c41080c176fbe4588212863f3a1ac18c`.
- Se preservó el worktree sucio; no se ejecutaron `reset`, `checkout`, limpieza global, commit, push ni despliegue.
- El servidor de pruebas utilizado fue exclusivo en `127.0.0.1:8123`, con `APP_ENV=testing`, `DB_DATABASE=clinica_dolor_demo_20261010_final` y una `APP_KEY` efímera.
- Los prechecks confirmaron la base activa allowlisted, pacientes 9301/9302 en clínicas 9101/9102, catálogos sintéticos y ausencia de escrituras en los prechecks.
- No se accedió ni escribió `bd_clinica`, ni se utilizó el paciente real 310.

## Inventario de escrituras y causa raíz

| Área | Método/call-site histórico | Riesgo confirmado | Remediación aplicada | Estado |
|---|---|---|---|---|
| P04 | `AntecedenteFamiliarModel::antecedentesFamiliares()` y llamadas de render históricas | Creación de filas vacías durante la carga | `mostrarPreguntasM2()` arma enfermedades fijas en memoria; la fila se crea únicamente al primer POST autorizado con `idEnfermedad=0` | PASS |
| P05 | `AntecedentesNoPatologicosModel::antecedentesNoPatologicos()` | Dependencia de respuesta precreada; inicialización durante lectura | `LEFT JOIN` con paciente; catálogo vacío renderizable; inicialización sólo dentro de POST después de validar pregunta y contexto | PASS |
| P06 | `AntecedentesQuirurgicos::mostrarPreguntasM4()` | Se revisó como posible inicializador | El render sólo consulta `pc_antecedentes_quirurgicos`; alta/edición/baja están en endpoints POST | PASS |
| P07 V1 | `AntecedentesPatologicosModel::antecedentesPatologicos()` | Filas V1 históricamente creadas para cada pregunta | Catálogo y respuestas se unen con `LEFT JOIN`; creación sólo durante POST con pregunta, tema y tipo validados | PASS |
| P07 V2 | `antecedentesPatologicosV2()` | Posible siembra de enfermedades durante GET | V2 parte del catálogo y une respuestas del paciente; alta sólo por POST con `idEnfermedadCatalogo` validado | PASS |
| P08 | `MedicacionActualModel::mostrarPreguntasM6()` | Se comprobó el riesgo de creación automática | El render y endpoint de contenido son SELECT; alta/edición/baja/finalización sólo POST | PASS |
| P09 | `MedicacionDolorModel::medicacionDolorQuest()` | La primera fila de respuesta dependía de siembra GET | Preguntas se renderizan con `LEFT JOIN`; primera fila se crea al guardar por POST y se devuelve el ID al cliente | PASS |
| P10 | `ProcedimientosDolorModel::procedimientosModulo8()` | Inicializador histórico con INSERT | El render consume catálogo y respuestas sin llamar al inicializador; los procedimientos/respuestas se crean por POST | PASS |
| P11 | `cuestionarioModulo9()`, `imagenFrenteModulo9()`, `imagenEspaldaModulo9()` | Riesgo histórico de filas/mapas al abrir la vista | El GET actual no invoca copia de imágenes ni crea filas; guardado de mapa y cuestionario permanece en POST | PASS |
| D40/resúmenes | Vistas Doctor y resúmenes | Riesgo de inicialización indirecta al revisar información | Inspección de call-sites y prueba sintética de Doctor/resumen con delta cero | PASS |

Los métodos legacy write-capable no se eliminaron porque no son llamados por los recorridos GET comprobados y su eliminación amplia excedería esta fase. Su permanencia se registra como riesgo de mantenimiento, no como escritura GET activa confirmada.

## Estrategia de render vacío y compatibilidad

- Las preguntas de catálogo se presentan aunque el paciente no tenga fila persistida.
- Las respuestas ausentes se representan como valores vacíos; no se convierten en `Sí`, `No`, finalización ni información clínica ficticia.
- La primera captura genera la fila sólo después de validar paciente, módulo, pregunta/enfermedad y autorización.
- Las respuestas existentes conservan su ID y valores; no se recrean.
- P09 actualiza los IDs de respuesta devueltos después de crear una fila en POST, sin reconstruir el control durante el clic.
- P04 conserva enfermedades fijas y enfermedades adicionales ya almacenadas, construyendo únicamente filas temporales en memoria.

## Pruebas HTTP y persistencia

Todas las pruebas siguientes usaron `clinica_dolor_demo_20261010_final`, pacientes 9301/9302 y el servidor sintético 8123.

| Prueba | Resultado | Evidencia |
|---|---|---|
| P04 GET página y AJAX sin delta | PASS | `tests/p04/http-crud.php`: GET propio 200, AJAX 200, sin sesión 302, cruce GET/AJAX 403 |
| P04 primera captura POST | PASS | Primera respuesta de enfermedad fija crea/actualiza sólo por POST; cruce POST 403; cleanup/restauración ejecutado |
| P05 GET y POST | PASS | `tests/p05/http-crud.php`: GET propio/cruzado, primera escritura, stale write 409, cruce 403, restauración |
| P06 GET y CRUD | PASS | `tests/p06/http-crud.php`: GET sin delta, alta/edición/baja sintéticas y cruce 403 |
| P07 V1/V2 GET y POST | PASS | `tests/p07/http-crud.php`: snapshots antes/después del GET, primera captura V1/V2, cruce 403; `cleanup-synthetic.php` PASS |
| P08 GET y CRUD | PASS | `tests/p08/http-crud.php`: GET, AJAX, update/no-op, insert/delete, finalización idempotente y cruce |
| P09 seis temas | PASS | `tests/p09/http-crud.php`: seis AJAX, primera captura POST, dependencias, recuperación, finalización/next_url, cleanup |
| P10 tratamientos | PASS | `tests/p10/http-crud.php`: catálogo 10/10, GET sin siembra, AJAX, dependencias, CRUD, finalización, cleanup |
| P11 mapas | PASS | `tests/p11/http-crud.php`: GET sin delta, mapas privados, guardado/limpieza, cruce 403, finalización idempotente |
| D40 Doctor/resumen | PASS | `tests/phase02/doctor-read-only.php`: Doctor y resumen 200, delta cero, anónimo bloqueado, clínica cruzada 403 |

Observación: los arneses reportan `no-op SQL no instrumentado` cuando no existe instrumentación de sentencias. Se verificó persistencia/estado, pero no se afirma una ausencia de ejecución SQL redundante en esos casos.

## Regresión y contratos

- PHP lint: PASS para `app/` completo y pruebas PHP P04–P11/Phase02.
- Contratos JS/mocks P04, P05, P06 audio, P07, P08, P09, P10, P11: PASS.
- `tests/p06/contract-check.mjs`: FAIL histórico por `!important` innecesario en CSS P06; no fue introducido ni corregido en esta fase.
- `git diff --check`: FAIL por whitespace histórico en `app/Controllers/ModulosController.php:1000`, reservado a Fase 3 según la instrucción; no se modificó. La comprobación focal del resto de los archivos de esta fase no mostró errores de whitespace.
- Browser responsive, consola JS y reproducción audible real: NO VERIFICADO; Chrome/IAB no estaba disponible de forma reproducible. Los mocks de audio no equivalen a reproducción humana.

## Archivos modificados por esta fase

Aplicación y render seguro:

- `app/Models/AntecedenteFamiliarModel.php`
- `app/Models/AntecedentesNoPatologicosModel.php`
- `app/Models/AntecedentesPatologicosModel.php`
- `app/Models/MedicacionDolorModel.php`
- `app/Controllers/ModulosController.php`
- Vistas de paciente, Doctor y resumen que antes ejecutaban inicializadores durante el render:
  - `app/views/historia-clinica-paciente/antecedentes-familiares.php`
  - `app/views/historia-clinica-doctor/antecedentes-familiares.php`
  - `app/views/resumen-clinica-doctor/antecedentes-familiares.php`
  - `app/views/historia-clinica-paciente/antecedentes-personales-no-patologicos.php`
  - `app/views/historia-clinica-doctor/antecedentes-personales-no-patologicos.php`
  - `app/views/resumen-clinica-doctor/antecedentes-personales-no-patologicos.php`
  - `app/views/historia-clinica-paciente/antecedentes-personales-patologicos.php`
  - `app/views/historia-clinica-doctor/antecedentes-personales-patologicos.php`
  - `app/views/resumen-clinica-doctor/antecedentes-personales-patologicos.php`
  - `app/views/historia-clinica-doctor/procedimientos-control-dolor.php`
  - `app/views/resumen-clinica-doctor/procedimientos-control-dolor.php`
  - `app/views/historia-clinica-doctor/medicacion-control-dolor.php`
  - `app/views/historia-clinica-paciente/medicacion-control-dolor.php`
  - `app/views/resumen-clinica-doctor/medicacion-control-dolor.php`
  - `app/views/resumen-clinica-doctor/evaluacion-dolor.php`

Pruebas:

- `tests/p04/http-crud.php`
- `tests/p05/http-crud.php`
- `tests/p06/http-crud.php`
- `tests/p07/http-crud.php`
- `tests/phase02/doctor-read-only.php`

Documentación:

- `docs/audits/PHASE_02_GET_WRITE_REMEDIATION.md`

El worktree contiene muchos cambios previos del usuario; esta lista no pretende reasignar su autoría. No se modificaron CSS, colores, modales, sidebar, narración ni la semántica clínica de P11.

## Riesgos y bloqueos pendientes

1. `ACT-05C-02` continúa abierto para PDF, descargas y almacenamiento de documentos clínicos.
2. La protección del servidor productivo y la validación contra producción no forman parte de esta fase; `PRODUCTION_READY: NO`.
3. Persisten métodos legacy capaces de escribir si fueran invocados directamente; requieren una fase posterior de retiro/refactorización o pruebas de no alcanzabilidad.
4. Persisten riesgos de SQL heredado, autorización documental, recetas externas y COFEPRIS fuera de alcance.
5. La ausencia de actualización SQL redundante no está instrumentada a nivel de sentencia.
6. No se ejecutaron pruebas responsive reales, consola de navegador ni audio audible humano.
7. El fallo histórico de whitespace en `ModulosController.php:1000` y el contrato CSS histórico de P06 permanecen sin tocar por alcance.
8. No se inició Fase 3.

## Limpieza

Los arneses eliminaron/restauraron las respuestas, procedimientos, finalizaciones y mapas creados durante las pruebas. El catálogo sintético P10 se preservó. P07 se limpió explícitamente después de la regresión. No se alteraron datos fuera del ámbito sintético autorizado.
