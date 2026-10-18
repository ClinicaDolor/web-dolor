# MASTER AUDIT — RECONCILIACIÓN DE COMPLETITUD V1.1

Fecha de corte: 2026-10-14
Repositorio: `C:\\xampp\\htdocs\\clinica-dolor`
Rama: `Silvino`
HEAD auditado: `59654e05dc9f931c21b5392238961bcddb3ab2e4`

## Resultado ejecutivo

V1.1 no repite la auditoría general. Reconcilia los denominadores de V1, los desglosa a nivel de archivo/ruta/módulo/prueba/recurso/documento y conserva las evidencias previas.

El resultado es **PARTIAL_STATIC**:

- El manifiesto de archivos queda definido sobre 3,435 archivos propios candidatos, con exclusiones separadas para terceros, cachés, uploads y artefactos generados.
- Las 136 declaraciones textuales de `addRoute` producen 163 registros efectivos porque la clausura común se registra bajo `/clinica` y `/historia-clinica`.
- Se individualizan P01–P11, M01–M09 más M09_MAP, D01–D40, nueve funciones administrativas, 170 archivos de pruebas, 31 recursos de datos inferidos y 170 documentos.
- Los 18 hallazgos tienen evidencia estática y estado explícito. Los seis nuevos hallazgos de V1.1 son deficiencias de cobertura/documentación o evidencia histórica no vigente; no se presentan como vulnerabilidades explotadas.
- No se ejecutaron MySQL/PDO, HTTP, navegador, Docker ni pruebas mutantes. Por tanto no se certifica comportamiento, autorización efectiva, persistencia, rendimiento, accesibilidad runtime ni producción.

## Reconciliaciones realizadas

### Identidad Git

El HEAD real es el SHA completo de 40 caracteres:

`59654e05dc9f931c21b5392238961bcddb3ab2e4`

La referencia de 39 caracteres del documento anterior queda corregida como discrepancia documental. No se modificó Git.

### Archivos

`files_manifest.csv` usa un denominador explícito de **3,435** archivos propios candidatos al corte V1.1. Cada fila registra ruta, categoría, subsistema, tipo, estado Git, alcance y evidencia.

El denominador no incluye dependencias de terceros, `node_modules`, `vendor`, `public/assets/libs`, `public/uploads` ni cachés/modelos/entornos virtuales/audio generados de voice-STT.

Los archivos binarios propios y fixtures sintéticos permanecen inventariados, pero no se convierten por ello en archivos semánticamente revisados.

Conteos técnicos separados:

- PHP de aplicación y `public`: 160; `php -l`: **160/160 PASS**.
- JavaScript/MJS no minificado en `public/assets/js`: 92; `node --check`: **92/92 PASS**.
- El manifiesto contiene 439 candidatos de código/script; el exceso frente a esos lint específicos corresponde a scripts, pruebas y formatos no cubiertos por esos dos comandos.
- La revisión semántica sigue siendo focal; el conteo de archivos lintados no equivale a lectura exhaustiva.

### Rutas

`routes_complete.csv` contiene **163 rutas efectivas** y conserva la evidencia `app/Config/routes.php:línea`.

- 136 declaraciones textuales.
- 27 declaraciones en `registrarRutasComunes`.
- La clausura común se registra dos veces: una para Doctor y otra para Paciente.
- No se sustituyen rutas parametrizadas por comodines.
- La matriz registra método, prefijo efectivo, controlador, acción, contexto esperado, autorización de recurso, lectura/escritura, CSRF y estado.

La matriz de seguridad completa sigue en **0**: las rutas están inventariadas, pero no todas tienen prueba de middleware, autorización por recurso, CSRF y runtime.

### Módulos y funcionalidades

- Paciente tradicional: 11 filas individuales en `functional_patient.csv`.
- Entrevista: M01–M09 y M09_MAP, 10 filas individuales en `functional_interview.csv`.
- Doctor: 40 filas D01–D40 en `functional_doctor.csv`. La aprobación visual histórica no se convierte en cierre funcional o de seguridad.
- Administración/roles/clínicas: nueve áreas en `functional_admin.csv`.
- Recetas, COFEPRIS, documentos, PDF y descargas quedan separados en la matriz administrativa y compartida; no se consideran seguros por estar listados.

La fuente maestra contiene además una mención a D41 en un contexto de estado/resumen, pero no tiene una fila D41 dentro de la tabla D01–D40. Es una decisión de gobierno pendiente, no una vista inventada ni añadida al denominador.

### Pruebas

`tests_complete.csv` individualiza **170 archivos de prueba, fixture o harness** bajo la política de inclusión declarada. El inventario es exhaustivo a nivel de archivo; el nivel de casos internos, precondiciones y resultados sigue parcial cuando el script no contiene un registro estructurado.

El identificador `P04-AUTH-20261014121758`, pacientes 10 y 28, se conserva como evidencia histórica reportada. V1.1 no lo ejecutó y no lo cuenta como PASS actual.

### Datos y documentos

- `database_resources_complete.csv`: 31 nombres de tablas/recursos detectados por lectura estática de modelos, servicios y pruebas. El esquema y las relaciones se marcan como inferidos; no hubo conexión MySQL.
- `documents_complete.csv`: 170 archivos bajo `docs/`, distinguiendo texto/metadatos de evidencia/activos.
- `findings_reconciled.csv`: 18 hallazgos con causa raíz, evidencia, confianza, estado, impacto, severidad, criterio de cierre y pruebas requeridas.
- Duplicados de V1 se normalizan por ID estable: **0 duplicados nuevos**. V1.1 agrega seis hallazgos de cobertura/gobierno/runtime histórico: COV-013, COV-014, COV-015, COV-016, INT-D41-017 y TEST-018.

## Estado de riesgos

Siguen abiertos, entre otros:

- autorización y propiedad de recetas externas;
- PDF de recetas externas y descargas documentales;
- COFEPRIS y archivos;
- CSRF no demostrado uniformemente;
- SQL heredado/dinámico;
- escrituras o siembras durante GET;
- autorización por recurso y clínica;
- privacidad/fail-closed de IA y trazas;
- evidencia runtime, browser, consola, Network, audio y responsive;
- reproducibilidad de dependencias por ausencia de `composer.lock`;
- decisión documental sobre D41.

La visualización aprobada no equivale a autorización clínica, seguridad, validación funcional ni producción.

## Roadmap definitivo

`roadmap_actions.csv` contiene 11 actividades accionables, separando seguridad documental, autorización, CSRF, SQL, integridad clínica, IA/privacidad, runtime, accesibilidad, reproducibilidad y gobierno.

Ruta crítica recomendada:

1. Confirmar el entorno sintético aislado y sus guardas.
2. Completar propiedad/autorización de recursos, especialmente recetas externas, PDF, descargas y COFEPRIS.
3. Completar matriz de rutas, CSRF y pruebas negativas A/B.
4. Resolver GET con escritura/idempotencia con decisión clínica.
5. Ejecutar las pruebas runtime actuales y conservar evidencia reproducible.
6. Completar browser/accessibility evidence.
7. Resolver D41 y separar formalmente cierres visuales, funcionales, de seguridad y de producción.
8. Repetir checklist de lanzamiento con revisión clínica, privacidad, seguridad y operación.

## Limitaciones

No se accedió a MySQL/PDO ni a `bd_clinica`; no se hicieron solicitudes HTTP ni escrituras; no se inició navegador ni Docker; no se instalaron dependencias; no se modificaron archivos de aplicación, configuración o base de datos.

`git diff --check` termina con código 0. Sus avisos LF/CRLF pertenecen a archivos locales preexistentes y no son errores de whitespace.

## Artefactos detallados

- `files_manifest.csv`
- `routes_complete.csv`
- `functional_patient.csv`
- `functional_interview.csv`
- `functional_doctor.csv`
- `functional_admin.csv`
- `functional_shared.csv`
- `tests_complete.csv`
- `database_resources_complete.csv`
- `documents_complete.csv`
- `findings_reconciled.csv`
- `roadmap_actions.csv`
- `coverage_matrix.csv`

## Declaración final

La auditoría V1.1 queda reconciliada documentalmente dentro de su alcance, pero no completa en sentido semántico, funcional, de seguridad ni runtime. No existe base objetiva para declarar el sistema listo para producción.

## Addendum V1.2 — completitud de seguridad de rutas

V1.2 agrega `19_ROUTE_SECURITY_AUDIT_V1_2.md`, `20_RESOURCE_AUTHORIZATION_MATRIX_V1_2.md`, `21_SECURITY_AUDIT_RECONCILIATION_V1_2.md`, `route_security_matrix_v1_2.csv` y `resource_authorization_matrix_v1_2.csv`. La cobertura estructural de rutas queda conciliada en 136 declaraciones y 163 rutas efectivas; la matriz de rutas contiene 163 filas y la de recursos 120. La documentación de fila está completa, pero la cobertura semántica de autenticación, ownership, CSRF, GET side effects, SQL y documentos permanece parcial o no verificada cuando requiere runtime.

La nueva evidencia no cambia el denominador de archivos, módulos, pruebas, recursos inferidos ni documentos de V1.1. Tampoco agrega hallazgos de vulnerabilidad nuevos ni cierra los 18 hallazgos reconciliados. MySQL/PDO, HTTP, navegador, Docker, escrituras, cambios de aplicación y entorno siguen fuera de esta ejecución.

