# Backlog de seguridad

| ID | Riesgo | Criticidad | Estado | Responsable pendiente | Criterio de aceptación |
|---|---|---:|---|---|---|
| SEC-01 | Autorización por paciente y clínica | Crítica | Verificado para cobertura probada; pendiente exhaustividad | Seguridad + backend | Ningún usuario accede a un paciente fuera de su ámbito |
| SEC-02 | Rutas `/buscar/*` sensibles sin sesión | Crítica | Implementado en Router | Seguridad + backend | Sin sesión se rechaza toda búsqueda clínica; `/buscar/web` permanece público |
| SEC-03 | Descargas y PDFs sin control central | Crítica | Sesión requerida; autorización por recurso pendiente | Seguridad + backend | Sin sesión y sin pertenencia al recurso se rechazan descargas y PDFs |
| SEC-04 | Cookie JWT sin atributos de seguridad | Alta | Implementado | Seguridad + infraestructura | Cookie con `HttpOnly`, `SameSite` y `Secure` bajo HTTPS |
| SEC-05 | URLs HTTP | Alta | Parcialmente implementado | Infraestructura | Producción redirige y sirve exclusivamente por HTTPS |
| SEC-06 | SQL concatenado | Alta | Pendiente | Backend + revisión de seguridad | Todas las consultas usan parámetros y validación de tipos |
| SEC-07 | CSRF | Alta | Pendiente | Backend + frontend | POST sin token válido se rechaza |
| SEC-08 | Archivos públicos y validación débil | Alta | Traversal bloqueado; exposición directa y propiedad pendientes | Seguridad + infraestructura | Archivos privados, MIME real, tamaño y contenido validados |
| SEC-09 | Auditoría y trazabilidad | Alta | Pendiente | Seguridad + cumplimiento | Lecturas y modificaciones críticas quedan registradas |
| SEC-10 | KPI agregado fuera de clínica | Alta | Remediado y verificado | Backend | El KPI sólo cuenta pacientes de id_clinica de sesión |
| SEC-11 | Exposición de excepciones, SQL y rutas internas | Alta | Remediado para expediente y `/buscar/tabla-*`; cobertura global pendiente | Backend + seguridad | Ninguna respuesta del expediente expone excepciones, SQL, rutas físicas, nombres de BD o datos internos |
| CLIN-01 | Integridad y versionado de notas | Crítica | Pendiente | Dirección clínica + backend | Nota firmada no se sobrescribe; corrección mediante addenda |
| DATA-01 | Migraciones y esquema | Alta | Pendiente | Backend + DBA | Base reproducible en entorno aislado |
| DATA-02 | Respaldos y restauración | Alta | Pendiente | Infraestructura + DBA | Restauración periódica probada con datos ficticios |

Los riesgos críticos pendientes bloquean publicación con información clínica real.

## Evidencia de la iteración 1

- SEC-01: la pertenencia se comprueba en AuthMiddleware y se aplica a vistas clínicas, módulos, búsquedas AJAX, consultas por ID y escrituras con idPaciente. El estado sigue siendo parcial porque faltan fixtures sintéticos y revisión exhaustiva de todos los recursos.
- SEC-02: sin cookie, /buscar/receta/0, /buscar/tabla-laboratorio/0/demo y una ruta de contenido de módulo respondieron 302 hacia /clinica/acceso. /buscar/web permaneció público.
- SEC-03: sin cookie, /descargar/web/Programa-del-Curso.pdf respondió 302 hacia /clinica/acceso. La prueba usó un recurso de curso, no un archivo clínico.
- Con un JWT sintético local y pacientes 0 o 999999, los endpoints protegidos respondieron 404. No se utilizaron registros reales ni se alteró la base.
- Archivos principales afectados: app/Core/Router.php, app/Middleware/AuthMiddleware.php, app/Controllers/ClinicaController.php, app/Controllers/BusquedasController.php, app/Controllers/ModulosController.php y controladores de receta, nota, laboratorio y COFEPRIS.

## Evidencia de la iteración 2

- Se utilizaron tokens sintéticos de médico y paciente con identificadores fuera de los registros; no se usaron pacientes reales.
- Las rutas de dashboard cargaron únicamente la vista y sus consultas agregadas o de módulos; no se realizaron escrituras, descargas ni cambios clínicos.
- La prueba cruzada entre dos médicos, dos clínicas y dos pacientes existentes queda pendiente porque el entorno no contiene fixtures sintéticos versionados y no se debe usar información real.
- CSRF, SQL parametrizado integral, almacenamiento privado, auditoría y versionado clínico siguen pendientes y bloquean publicación con información clínica real.

## Evidencia de la iteración 3

- Se creó tests/fixtures/security-fixtures.sql con dos clínicas, dos médicos, dos pacientes y una receta sintéticos. Se cargó en la base local aislada clinica_dolor_audit_20261010_iter3; no se cambió .env ni se consultaron filas productivas.
- SEC-01: médico A leyó paciente A y recibió 403 para paciente B; médico B leyó paciente B y recibió 403 para paciente A. Paciente A leyó su ficha y recibió 403 al solicitar la ficha de paciente B.
- SEC-01 escritura: una receta sintética para paciente A respondió 200 y persistió; el intento cruzado para paciente B respondió 403 y el conteo quedó sin incremento.
- SEC-02 y SEC-03: se mantuvo el rechazo sin sesión. Una descarga válida sintética respondió 200; una solicitud sin sesión respondió 302.
- SEC-08: DownloadController valida realpath, archivo regular y permanencia bajo public/storage. El intento de traversal sintético respondió 404. La propiedad del archivo sigue sin relación con paciente o clínica: dos sesiones clínicas obtuvieron 200 al conocer la misma ruta.
- SEC-10: ClinicaModel::countPacientes recibe idClinica y usa WHERE id_clinica = :id_clinica. La prueba aislada devolvió kpiA=1 y kpiB=1.
- SEC-06: continúan consultas concatenadas verificables, entre otras EvaluacionDolorModel y CofeprisModel; no se afirma parametrización integral.
- SEC-07, SEC-09, CLIN-01, DATA-01 y DATA-02 permanecen abiertos.

## ACT-05B-05A — Manejo seguro de errores clínicos — 2026-10-10

### Causas raíz verificadas

- `app/Config/Database.php` convertía un fallo de conexión en `die()` con el
  mensaje completo de PDO, potencialmente incluyendo host, nombre de base o
  detalles del servidor.
- `app/views/clinica/pacientes-detalle.php` convertía la excepción de la
  consulta de `historia_clinica_modulos` en `die()` con el SQL/PDO completo.
- Los modelos llamados por `app/Controllers/BusquedasController.php` usan
  `PDO::ERRMODE_EXCEPTION`; sus excepciones podían propagarse sin una frontera
  de respuesta segura cuando faltaba una tabla o fallaba la base.
- `public/index.php` no registraba un manejador global para excepciones y
  errores fatales antes de despachar la ruta.

### Remediación acotada

- `app/Core/SafeErrorHandler.php` registra una frontera global antes de cargar
  el entorno y despachar rutas, desactiva la exposición de errores en la respuesta,
  amortigua la salida y responde HTTP 500 con contenido genérico.
- Las rutas `/buscar/tabla-*` mantienen su contrato de fragmento HTML y
  reciben un estado de error genérico; no se transforman en tablas vacías.
- Las respuestas JSON ya declaradas conservan JSON con mensaje genérico.
- `app/Config/Database.php` propaga una excepción técnica sin incluir su
  mensaje en la respuesta.
- La vista del expediente propaga un contexto técnico genérico para que la
  frontera global devuelva un error completo, sin `die()` ni detalle SQL.
- El registro técnico escribe sólo una categoría (`Unhandled application
  exception` o `Fatal application error`), sin copiar mensajes, rutas,
  credenciales, tokens o datos clínicos.

La corrección cubre el expediente individual y los endpoints de tablas
clínicas incluidos en esta actividad. No afirma que las vistas clínicas
secundarias, descargas, SQL concatenado o autorización de archivos estén
remediados; permanecen como actividades independientes.

## ACT-05C-02 — Fixtures y línea base de seguridad documental — 2026-10-10

### Entorno confirmado

- `clinica.test` usa `C:/xampp/htdocs/clinica-dolor` como `DocumentRoot`, según
  `C:/xampp/apache/conf/extra/httpd-vhosts.conf:58-64`.
- El `.htaccess` raíz redirige las rutas a `public/`; el
  `public/.htaccess` no reescribe archivos físicos existentes.
- La URL física de un archivo bajo `public/storage` queda, por tanto, fuera de
  los controladores PHP si el archivo existe. El mapeo se comprobó sólo con
  archivos sintéticos en un servidor estático aislado.
- Como comprobación adicional, un `HEAD` al recurso público existente
  `/public/storage/web/Aviso de privacidad.pdf` respondió `200
  application/pdf` desde `http://clinica.test`, sin leer su contenido.
- No se leyeron documentos de `public/storage` del proyecto; únicamente se
  inspeccionó su estructura y referencias de código.

### Fixtures agregados

`tests/fixtures/security-fixtures.sql` conserva dos clínicas, dos médicos y
dos pacientes independientes, y ahora incluye recursos de ambos ámbitos:

- Recetas internas `9401` y `9402`.
- Laboratorios `9511` y `9512`, además del registro textual preexistente
  `9502`.
- COFEPRIS `9503` y `9506`.

No se añadió `receta_medica_externos`: el repositorio no contiene su DDL
versionado y el modelo no muestra `id_paciente` ni `id_clinica`; inventar una
relación habría falseado la línea base.

### Archivos sintéticos

Sólo bajo `tests/security/runtime/public/storage/`:

- `laboratorio/resultado-sintetico-a.pdf`
- `laboratorio/resultado-sintetico-b.pdf`
- `cofepris/CARPETA-SINTETICA/FOLIO-9503.pdf`
- `cofepris/CARPETA-SINTETICA-B/FOLIO-9506.pdf`
- `web/recurso-publico-sintetico.pdf`

No contienen datos personales ni clínicos reales. Se eliminaron la base
aislada y los servidores de prueba al finalizar; los fixtures y archivos
sintéticos versionados permanecen para reproducibilidad.

### Línea base reproducible

| Prueba | Resultado | Evidencia |
|---|---|---|
| Médico A descarga laboratorio A | PASS funcional | `200 application/pdf` |
| Médico B descarga laboratorio A | FAIL de autorización | `200 application/pdf`; acceso cruzado confirmado |
| Médico B descarga laboratorio B | PASS funcional | `200 application/pdf` |
| Descarga sin sesión | PASS de autenticación | `302` a `/clinica/acceso` |
| Documento inexistente | PASS de inexistencia | `404` |
| Traversal sintético | PASS contra escape de ruta | `404` |
| Médico A genera receta A | PASS funcional | `200 application/pdf` |
| Médico B genera receta A | FAIL de autorización | `200 application/pdf`; acceso cruzado confirmado |
| Médico B genera receta B | PASS funcional | `200 application/pdf` |
| Médico A genera COFEPRIS A | PASS funcional | `200 application/pdf` |
| Médico B genera COFEPRIS A | FAIL de autorización | `200 application/pdf`; acceso cruzado confirmado |
| Médico B genera COFEPRIS B | PASS funcional | `200 application/pdf` |
| PDF inexistente | FAIL de manejo esperado | `500`; no se transforma en `404` |
| URL estática de laboratorio sintético | FAIL de exposición | `200` sin sesión |
| URL estática de COFEPRIS sintético | FAIL de exposición | `200` sin sesión |
| URL estática del recurso web sintético | PASS de disponibilidad pública | `200` sin sesión |

Los fallos cruzados se conservaron intencionalmente. No se modificaron
controladores para forzar resultados positivos.

### Consumidores identificados

- Laboratorio: `LaboratorioModel`, `laboratorio.php` y enlaces generados con
  `/descargar/{ruta}/{archivo}`.
- Notas subsecuentes: incluyen enlaces a descargas de laboratorio.
- Receta interna: `modal-impresion-receta.php` publica POST a
  `/pdf/receta/{id}`.
- Receta externa: el mismo componente usa `/pdf/receta-externo/{id}`.
- COFEPRIS: vistas y modelos usan `/pdf/cofepris/{id}`.
- Web pública: usa `descargar/web/...` y `RUTA_STORAGE . 'web/...'`.

### Precondiciones para ACT-05C-03

1. Definir autorización por ID para laboratorio, receta y COFEPRIS.
2. Mantener una allowlist separada para recursos públicos de `storage/web`.
3. Resolver si las recetas externas son globales o requieren propietario.
4. Diseñar compatibilidad temporal para los enlaces actuales por nombre.
5. Corregir la respuesta de documento inexistente para evitar `500`.
6. Probar bloqueo Apache de las carpetas clínicas antes de mover archivos.
