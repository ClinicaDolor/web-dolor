# CIERRE INTEGRAL — HISTORIA CLÍNICA TRADICIONAL P01–P11

Fecha de corte: 2026-10-12  
Proyecto: Clínica del Dolor  
Base de pruebas autorizada: `clinica_dolor_demo_20261010_final`

## 1. Alcance y criterio de cierre

Este documento consolida el cierre de la Historia Clínica tradicional del Portal Paciente, módulos P01–P11. La Entrevista Conversacional y sus servicios, adaptadores y rutas quedan fuera de alcance y no se consideran evidencia de cierre tradicional.

No se realizaron commits, push ni despliegues. No se escribió en `bd_clinica`. Las pruebas autenticadas utilizaron únicamente los pacientes sintéticos 9301/9101 y 9302/9102, pertenecientes a clínicas sintéticas distintas.

`PASS` significa que existe evidencia ejecutada para el alcance indicado. `NO VERIFICADO` significa que la prueba no se ejecutó. El cierre técnico no equivale a autorización clínica, aprobación de seguridad ni preparación para producción.

## 2. Estado ejecutivo

| Dimensión | Estado | Evidencia / limitación |
|---|---|---|
| Funcionalidad tradicional P01–P11 | **PASS PARCIAL** | P01–P03 smoke y P04–P11 HTTP sintético ejecutados; la continuidad global de todos los módulos en una sola sesión no se ejecutó. |
| Finalización P04/P05/P07 | **PASS** | Corrección idempotente en modelo; endpoint HTTP repetido dejó una sola fila y devolvió `next_url` estable para módulos 2, 3 y 5. |
| Finalización P06/P08–P11 | **PASS** | Runners HTTP existentes; P06 incluye concurrencia real entre dos procesos. |
| Seguridad de imágenes P11 | **PASS CONDICIONADO** | Apache/XAMPP devolvió 403 para cuatro patrones de archivos por paciente y 200 para bases anatómicas; el servidor PHP embebido no aplica `.htaccess` y devolvió 200. |
| Modal P10 | **PASS ESTÁTICO / NO VERIFICADO VISUAL** | El contrato estaba obsoleto respecto al helper canónico; se corrigió el runner y pasó. Centrado renderizado, consola y Network requieren navegador. |
| GET sin escrituras | **FAIL / RIESGO ABIERTO** | Permanecen renderizadores que siembran filas durante GET en P04/P05/P07/P09 y rutas legacy relacionadas. |
| Evidencia visual responsive | **NO VERIFICADO** | No hubo control reproducible de viewport, exportación PNG, consola DevTools ni Network para la matriz completa. |
| Preparación para producción | **NO** | Se conservan riesgos de documentos, descargas, almacenamiento, SQL heredado y autorización clínica. |

### Campos consolidados

- `STATUS`: **PARTIAL / BLOCKED FOR PRODUCTION**
- `VERIFIED_FIXES`: finalización idempotente P04/P05/P07; contrato estático P10; arnés cruzado P05; validación Apache de archivos privados P11.
- `OPEN_DEFECTS`: escrituras durante GET; renderizadores legacy de P11; SQL heredado; evidencia visual/runtime incompleta.
- `BLOCKING_SECURITY_RISKS`: ACT-05C-02; protección equivalente a `.htaccess` en cualquier servidor no Apache; consumidores legacy de imágenes; autorización/documentación de recetas/COFEPRIS cuando aplique.
- `FINALIZATION_REGRESSION`: **PASS** para P04–P11 en los runners disponibles; P04/P05/P07 también tienen endpoint HTTP repetido probado después de la corrección.
- `P11_IMAGE_SECURITY`: **PASS bajo Apache/XAMPP; NO PASS universal** hasta confirmar la configuración del servidor objetivo.
- `P10_MODAL`: **PASS estático; NO VERIFICADO en navegador** para centrado, consola y Network.
- `P01_P11_FUNCTIONAL_STATUS`: **PASS parcial**, con pruebas sintéticas aisladas y sin recorrido único completo P01→P11.
- `D40_REGRESSION`: **PASS parcial read-only**, sin autorización de producción ni cierre visual/runtime.
- `VISUAL_VALIDATION`: **NO VERIFICADO** para matriz responsive completa, capturas PNG, consola y Network.
- `USER_DECISIONS_REQUIRED`: GET con escritura, servidor de imágenes, ACT-05C-02, semántica de colores P11 y método de evidencia visual.
- `WORKTREE_STATUS`: árbol local previamente sucio preservado; sin commit, push ni despliegue.
- `MODIFIED_FILES`: `app/Models/PacienteModulosModelo.php`, `tests/p05/http-crud.php`, `tests/p10/contract-check.mjs` y este informe.
- `NEXT_RECOMMENDED_ACTION`: remediación separada de GET/legacy P11 y evidencia visual real P01–P11.
- `PRODUCTION_READY`: **NO**.

## 3. Matriz funcional P01–P11

| Módulo | GET propio / sesión | Acceso cruzado | AJAX / persistencia | Finalización / `next_url` | Estado actual |
|---|---|---|---|---|---|
| P01 Login | PASS, smoke sintético | No aplica | PASS, login sintético | No aplica | PASS parcial |
| P02 Dashboard | PASS, smoke sintético | No aplica por URL | Navegación básica PASS | No aplica | PASS parcial |
| P03 Ficha | PASS HTTP 200 | PASS HTTP 403 | PASS; GET sin cambio de fila | No aplica | PASS |
| P04 Antecedentes familiares | PASS | PASS GET/AJAX/POST | PASS; cleanup sintético | PASS HTTP, repetición sin duplicado | PASS |
| P05 Antecedentes no patológicos | PASS | PASS GET/POST 403 | PASS; stale write 409; cleanup | PASS HTTP, repetición sin duplicado | PASS |
| P06 Antecedentes quirúrgicos | PASS | PASS | PASS CRUD y concurrencia | PASS, idempotencia y `next_url` | PASS |
| P07 Antecedentes patológicos V1/V2 | PASS | PASS | PASS V1/V2; cleanup | PASS HTTP, repetición sin duplicado | PASS |
| P08 Medicación actual | PASS después de preparar fixture | PASS | PASS CRUD, persistencia y cleanup | PASS, idempotencia y `next_url` | PASS |
| P09 Medicación para dolor | PASS | PASS GET/AJAX/POST | PASS catálogo, dependencias, comentarios | PASS, idempotencia y cleanup | PASS |
| P10 Procedimientos para dolor | PASS | PASS GET/AJAX/POST | PASS catálogo 10/10, CRUD, dependencias | PASS, idempotencia y cleanup | PASS |
| P11 Evaluación del dolor | PASS | PASS GET/AJAX/POST | PASS mapas, cuestionario y cleanup | PASS, idempotencia y `next_url` | PASS condicionado por riesgos de assets legacy |

Los estados anteriores de PHASE-05B que marcaban duplicación en P04/P05/P07 quedan supersedidos por la prueba HTTP posterior a la corrección: módulos 2, 3 y 5 recibieron dos POST de finalización, mantuvieron una sola fila por `(id_modulo,id_paciente)` y devolvieron destino local.

### Coberturas no equivalentes a PASS completo

- No se ejecutó un recorrido único que complete P01→P11 con la misma sesión y estado persistido.
- Los mocks de audio verifican construcción de texto, cancelación y estados; no prueban reproducción audible humana.
- Los checks HTTP de “no-op SQL” no instrumentan el número exacto de sentencias `UPDATE`; sólo verifican respuesta y estado observable.
- La accesibilidad completa, teclado real, foco visual y responsive renderizado quedan pendientes de navegador reproducible.

## 4. Correcciones verificadas en esta intervención

### 4.1 Finalización idempotente P04/P05/P07

**Causa:** `finalizarModulos()` insertaba siempre una fila, sin comprobar previamente la combinación lógica módulo/paciente y sin serializar solicitudes concurrentes.

**Corrección aplicada:** `app/Models/PacienteModulosModelo.php` valida identificadores, usa `GET_LOCK` por módulo/paciente, consulta una finalización existente y sólo inserta cuando no existe; libera el bloqueo en `finally`. P06 conserva su método específico.

**Resultado:** PASS en endpoint HTTP para módulos 2, 3 y 5; primera y segunda llamada HTTP devolvieron 200, el conteo final fue 1 y `next_url` fue local y estable. No se añadió una restricción de esquema ni se eliminaron duplicados históricos.

### 4.2 Contrato del modal P10

**Causa:** `tests/p10/contract-check.mjs` exigía manipulación inline del modal, aunque P10 ya usa el helper compartido `patient-instructions-modal.js` y `modal-dialog-centered`.

**Corrección aplicada:** el runner valida la estructura canónica y el helper compartido, sin exigir una implementación inline distinta.

**Resultado:** contrato P10 PASS y `tests/p10/modal-parity.mjs` PASS. El centrado visual real, consola y Network quedan NO VERIFICADOS sin navegador DevTools.

### 4.3 Corrección de la prueba cruzada P05

**Causa del falso fallo:** cuando no existía una fila para 9301, el runner enviaba `idRespuesta=0` y `idPaciente=9302`; la aplicación creaba legítimamente el registro sintético de 9302, por lo que la prueba no intentaba modificar el registro de 9301.

**Corrección aplicada:** el runner usa el identificador real de una respuesta de 9301 como objetivo, pero autentica a 9302. No se modificó la lógica clínica.

**Resultado:** HTTP 403 y cleanup PASS. La autorización por paciente/respuesta quedó demostrada en el escenario sintético.

## 5. Seguridad P11 — imágenes y mapas clínicos

### P11-IMG-001 — acceso directo a archivos por paciente

**Severidad:** P0/P1 según servidor de despliegue.  
**Estado:** **PASS CONDICIONADO / RIESGO DE CONFIGURACIÓN ABIERTO**.

La ruta autenticada de imagen propia y los cruces HTTP del controlador devolvieron los estados esperados. Bajo Apache/XAMPP con el VirtualHost `clinica.test`, los fixtures temporales `Frente_M_9301.png` y `Espalda_M_9301.png`, en ambas variantes de URL, devolvieron HTTP 403; las bases anatómicas genéricas devolvieron HTTP 200. El cleanup de fixtures fue PASS.

El mismo ensayo contra PHP built-in devolvió HTTP 200 porque ese servidor no procesa `.htaccess`; no es evidencia de que Apache falle, pero demuestra que el comportamiento depende del servidor configurado. Antes de producción debe verificarse que todo servidor real aplique la protección equivalente y que no existan consumidores legacy que sirvan los archivos directamente.

### P11-LEGACY-002 — renderizadores y almacenamiento legacy

**Estado:** **ABIERTO**. Persisten métodos/renderizadores legacy y consultas históricas alrededor de imágenes/mapas. No se hizo una migración amplia ni se alteraron rutas documentales en este cierre. Debe revisarse el conjunto de consumidores Doctor, resumen y PDF antes de declarar seguridad integral.

### P11-COLOR-003 — semántica clínica de colores

**Estado:** **USER_DECISION_REQUIRED**. El código y la auditoría previa identifican colores con posible significado clínico. Se requiere decisión clínica documentada antes de cambiar colores o afirmar equivalencias accesibles.

## 6. Defectos y riesgos abiertos

### TH-GET-001 — escrituras durante GET

**Severidad:** P1. **Estado:** abierto.

Auditorías previas y revisión de código mantienen siembra de filas vacías durante renderizado/GET en P04, P05, P07 y P09, además de métodos legacy relacionados con P10. La corrección requiere separar preparación de datos y lectura; no se realizó aquí porque cambia contratos funcionales y necesita una actividad autorizada independiente.

### TH-ACT-05C-001 — documentos, PDF, descargas y almacenamiento

**Severidad:** crítica. **Estado:** abierto / revisión requerida.

Se conservan las vulnerabilidades documentales referidas por ACT-05C-02: PDF, descargas y almacenamiento documental. El documento fuente de ACT-05C-02 no fue localizado en `docs/audits`; por tanto este informe no inventa rutas ni declara remediaciones. Debe mantenerse visible y ser revisado con el informe de seguridad original antes de cualquier despliegue.

### TH-SQL-002 — SQL heredado y concatenación

**Severidad:** P1/P2 según ruta. **Estado:** abierto.

La auditoría previa identifica consultas legacy concatenadas, particularmente en renderizadores y flujos de P11. Las pruebas sintéticas de autorización no sustituyen una revisión completa de parametrización.

### TH-VIS-003 — evidencia visual incompleta

**Severidad:** P1 para presentación, no necesariamente funcional. **Estado:** NO VERIFICADO.

No se pudieron confirmar con evidencia persistente las dimensiones 1440×900, 1280×720, 768×1024, 430×932 y 375×667 para todos los módulos; tampoco consola, Network, PNG, teclado real ni audio audible. Las reglas CSS y contratos estáticos pasan, pero no equivalen a validación visual humana.

### TH-REG-004 — posible regresión Doctor/D40

**Estado:** PASS parcial.

La regresión read-only de Doctor/D40 consignada en PHASE-05B pasó para dashboard, resumen, sesión anónima, acceso cruzado y delta clínico posterior al GET. No se ejecutó una revisión visual completa ni se autoriza inferir cierre de seguridad documental.

## 7. Validaciones técnicas ejecutadas

| Prueba | Resultado |
|---|---|
| PHP lint de `app/` | PASS — 151 archivos, 0 fallos |
| Sintaxis JS de `public/assets/js/` | PASS — 98 archivos, 0 fallos |
| `git diff --check` | PASS — sólo advertencias históricas LF/CRLF |
| Contratos P04–P11 y mocks | PASS |
| Modal, sidebar, audio/highlight, responsive estático PHASE-04B | PASS estático; runtime visual NO VERIFICADO |
| P11 direct-image-security en Apache | PASS: privados 403, bases genéricas 200 |
| P11 direct-image-security en PHP built-in | FAIL esperado por ausencia de `.htaccess`; no representa Apache |
| Limpieza sintética | PASS en runners ejecutados |

## 8. Archivos tocados en esta intervención

La carpeta de trabajo ya estaba sucia antes de esta actividad y contiene cambios locales de fases previas; no se revirtieron ni se normalizaron. Las áreas modificadas específicamente durante este cierre fueron:

- `app/Models/PacienteModulosModelo.php` — idempotencia genérica de finalización.
- `tests/p05/http-crud.php` — objetivo válido para prueba cruzada.
- `tests/p10/contract-check.mjs` — contrato alineado al modal compartido.
- `docs/audits/TRADITIONAL_HISTORY_CLOSURE_MASTER.md` — este consolidado.

No se modificaron bases reales, despliegues ni archivos de configuración de producción. Los servidores PHP temporales de prueba fueron detenidos al finalizar.

## 9. Decisiones requeridas

1. Autorizar una fase separada para eliminar escrituras durante GET sin cambiar el contrato clínico.
2. Confirmar, con el responsable de infraestructura, que todos los servidores de despliegue aplicarán el bloqueo equivalente a `.htaccess` para imágenes privadas.
3. Obtener el informe fuente de ACT-05C-02 y su plan de remediación; este cierre no lo sustituye.
4. Definir clínicamente la semántica de colores de P11.
5. Autorizar evidencia visual con navegador nativo o aceptar el procedimiento manual de PHASE-05A-R1.

## 10. Próxima acción recomendada

No desplegar. La siguiente actividad recomendada es una remediación de seguridad separada para TH-GET-001 y P11-LEGACY-002, seguida de una fase de evidencia visual real P01–P11 con navegador nativo, consola, Network, capturas y recorrido completo persistido.

**PRODUCTION_READY: NO**  
**WORKTREE_STATUS: cambios locales preservados; sin commit/push/deploy**

## TH-SEC-AUDIT-01 — Estado de investigación

**Alcance de esta actualización:** únicamente estado de investigación; no se reescriben ni sustituyen los resultados históricos anteriores.

La auditoría de trazabilidad del 2026-10-12 ('docs/audits/TH_SEC_AUDIT_01_TRACEABILITY.md') contrastó las rutas y call-sites actuales con PHASE-02. Para los GET registrados de P04–P11 no se demostró una escritura clínica activa: los renderizadores actuales son de lectura y las rutinas que insertan filas vacías permanecen como métodos legacy o se invocan desde POST. La llamada compartida desde 'AntecedentesNoPatologicosModel::capturarRespuestaEntrevista()' pertenece a Entrevista Conversacional y queda fuera del alcance de esta investigación.

| Investigación | Estado actual | Evidencia / limitación |
|---|---|---|
| TH-GET-001 — GET P04–P11 | **SIN ESCRITURA ACTIVA DEMOSTRADA** | Call graph actual + PHASE-02; los inicializadores legacy permanecen abiertos como riesgo de mantenimiento. |
| ACT-05C-02 | **FUENTE NO LOCALIZADA / RIESGO ABIERTO** | No se reconstruyen hallazgos históricos; se inspeccionaron independientemente PDF, descargas, almacenamiento y propiedad. |
| TH-SQL-002 | **RIESGO ESTÁTICO ABIERTO** | Persisten consultas concatenadas; no se reprodujo inyección. |
| P11-LEGACY-002 | **ABIERTO** | Doctor/D40 y renderizadores legacy permanecen; privacidad depende de Apache '.htaccess', PHP built-in no la aplica. |

Los documentos históricos que describían siembra durante GET se conservan sin edición; para la decisión actual deben leerse junto con el informe TH-SEC-AUDIT-01. Este apéndice no marca seguridad, cierre funcional ni preparación para producción.

**MASTER_UPDATE_SCOPE: investigation status only**

## TH-SEC-FIX-01 — Estado de remediación de recetas por clínica

La intervención del 2026-10-12 aplicó únicamente controles respaldados por relaciones existentes. La receta interna se aisló por `receta_medica.id_paciente → pc_paciente.id_clinica`; se parametrizó el renderizador de tabla y se añadió `assertPatientAccess()` antes de emitir PDFs internos y COFEPRIS. Las pruebas sintéticas confirmaron listado A/B sin intersección y rechazo cruzado de receta interna y COFEPRIS.

La propiedad de `receta_medica_externos` no pudo verificarse con el esquema disponible: la base sintética allowlisted no contiene esa tabla y el contrato revisado no ofrece una relación paciente/clínica. Las rutas externas, el PDF externo y las descargas quedan abiertos y no deben considerarse protegidos por esta actividad. La generación propia de COFEPRIS quedó NO VERIFICADA porque faltan los archivos PDF sintéticos. El estado global es **PARTIAL**, con decisiones pendientes antes de TH-SEC-FIX-02.

Detalle y matriz reproducible: `docs/audits/TH_SEC_FIX_01_RECIPE_TENANT_AUTHORIZATION.md`.

**TH_SEC_FIX_01_STATUS: PARTIAL — PRODUCTION_READY: NO**

## TH-SEC-FIX-01B — Estado de propiedad histórica de recetas

La auditoría del 2026-10-12 confirmó que el modelo actual sólo conserva `pc_paciente.id_clinica` como clínica vigente. No existe historial de transferencias ni un propietario clínico inmutable en `receta_medica`; tampoco se pudo verificar un propietario para recetas externas. Los PDFs de recetas se regeneran desde datos actuales y no se demostró persistencia del original ni una relación de fidelidad.

Por ello, la autorización vigente de TH-SEC-FIX-01 debe considerarse contención por clínica actual, no propiedad histórica. No es seguro implementar los escenarios A→B, B→A, clínicas simultáneas o multiclínica sin una decisión explícita de modelo, una fase autorizada de esquema/reconciliación y un contrato para documentos. No se modificaron PHP, esquemas, datos ni superficies del Conversational Interview; no se ejecutaron escrituras, PDFs, ediciones, eliminaciones o descargas.

El gate de implementación queda **NO / BLOCKED**. Se recomienda mantener fail-closed todo registro sin owner histórico verificable y decidir por separado: owner permanente de receta, historial de transferencias, lectura de destino como copia de sólo lectura, permisos de la clínica origen, política de registros legacy y propiedad de recetas externas.

Detalle, evidencia y decisiones requeridas: `docs/audits/TH_SEC_FIX_01B_HISTORICAL_RECIPE_OWNERSHIP.md`.

**TH_SEC_FIX_01B_STATUS: BLOCKED — SCHEMA_CHANGES: NONE — LEGACY_DATA_CHANGES: NONE — PRODUCTION_READY: NO**

## TH-SEC-ARCH-01 — Estado del diseño arquitectónico

El diseño arquitectónico del 2026-10-12 documenta, sin implementar, una solución híbrida para propiedad histórica de recetas, traslados, documentos originales y conciliación legacy. La propuesta está fundamentada en el esquema sintético disponible: `pc_paciente.id_clinica` es la única relación de clínica observada; `receta_medica` no conserva clínica emisora ni documento original; `receta_medica_externos` y los catálogos persistentes de usuarios/roles no están disponibles en la base allowlisted. No se hicieron cambios de aplicación, esquema, datos, almacenamiento ni permisos.

La recomendación es separar el historial de traslados, la propiedad inmutable por tipo de receta, el documento original privado y la conciliación append-only exclusiva del superadministrador. Los registros sin owner verificable deben permanecer fail-closed. Las asociaciones simultáneas, políticas multiclínica, recetas emitidas fuera del sistema y la reconciliación legacy requieren aprobación adicional.

Documento principal: `docs/architecture/TH_SEC_ARCH_01_HISTORICAL_RECIPE_OWNERSHIP.md`. Modelo de datos: `docs/architecture/TH_SEC_ARCH_01_DATA_MODEL_PROPOSAL.md`.

**TH_SEC_ARCH_01_STATUS: COMPLETE — IMPLEMENTATION_STARTED: NO — SCHEMA_CHANGES: NONE — DATA_CHANGES: NONE — APPLICATION_CODE_CHANGES: NONE — PRODUCTION_READY: NO**

## TH-SEC-ARCH-01 V1.1 — Estado de revisión arquitectónica

La revisión V1.1 actualiza el diseño sin sustituir ni editar los documentos V1.0. Se incorpora como contrato arquitectónico que cada paciente mantiene una sola clínica activa, con historial permanente de traslados; que toda receta conserva una clínica emisora inmutable; y que el acceso histórico de la clínica de destino, cuando sea aprobado, es de sólo lectura y no transfiere la propiedad de la receta.

La definición de receta externa queda precisada: es una receta emitida por un médico de la propia clínica a una persona que todavía no está registrada como paciente. No es una receta global ni una receta emitida por un tercero. Puede registrarse sin expediente al momento de emisión y, después de una verificación de identidad, puede vincularse manualmente a un expediente mediante un vínculo auditable separado. La vinculación posterior no cambia la clínica emisora ni el propietario histórico. La conciliación de registros legacy permanece exclusiva del superadministrador y fail-closed cuando no exista evidencia verificable.

El modelo V1.1 propone tablas separadas para traslados, propiedad de recetas internas, recetas externas, vínculo posterior, verificación de identidad, documento original privado, conciliación y auditoría de operaciones sensibles. Todas son propuestas no implementadas; no se ejecutaron SQL, migraciones, escrituras, cambios de esquema, cambios de aplicación ni accesos a datos reales.

Se conservan íntegramente las secciones históricas y los documentos V1.0. Documentos V1.1: `docs/architecture/TH_SEC_ARCH_01_HISTORICAL_RECIPE_OWNERSHIP_V1.1.md` y `docs/architecture/TH_SEC_ARCH_01_DATA_MODEL_PROPOSAL_V1.1.md`. Documentos V1.0 preservados: `docs/architecture/TH_SEC_ARCH_01_HISTORICAL_RECIPE_OWNERSHIP.md` y `docs/architecture/TH_SEC_ARCH_01_DATA_MODEL_PROPOSAL.md`.

Las decisiones AUTH-01 a AUTH-08 — actores de vinculación, alcance cross-clinic, evidencia de identidad, duplicados, anulaciones, legacy, almacenamiento privado y roles de superadministrador — quedan pendientes de aprobación antes de cualquier fase de implementación.

**TH_SEC_ARCH_01_V1_1_STATUS: COMPLETE — IMPLEMENTATION_STARTED: NO — SCHEMA_CHANGES: NONE — DATA_CHANGES: NONE — APPLICATION_CODE_CHANGES: NONE — PRODUCTION_READY: NO**

## TH-SEC-AUTH-08 — Estado de auditoría de roles y autorización multiclínica

La auditoría documental del 2026-10-12 revisó autenticación, sesiones, roles, superadministrador, membresía de clínica y autorización de recursos vinculados con Historia Clínica tradicional P01–P11. La ejecución quedó en modo lectura: la guarda fail-closed detectó que la configuración activa resolvía `bd_clinica` y bloqueó la conexión antes de PDO. No se consultaron datos reales ni se ejecutaron POST, SQL de escritura, descargas, PDF, migraciones o cambios de aplicación.

El resultado es **PARTIAL**. El código demuestra autenticación JWT en cookie y controles de clínica vigente para varias rutas internas mediante `assertPatientAccess()`, pero no demuestra un catálogo técnico de roles/permisos, una identidad de superadministrador, membresía multiclínica, owner histórico inmutable de receta ni autorización por recurso documental. Permanecen como riesgos estáticos prioritarios las rutas de recetas externas, PDF externo, descargas genéricas y operaciones COFEPRIS por ID/carpeta/folio.

Las políticas AUTH-01 a AUTH-07 quedan incorporadas como decisiones aprobadas de referencia: vinculación explícita y auditable; lectura histórica de sólo lectura cuando proceda; verificación de identidad; control de duplicados; corrección/anulación con original inmutable y auditoría; fail-closed sin procedencia verificable; y seguridad privada de documentos. AUTH-08 queda registrado como regla aprobada: sólo un superadministrador verificable puede aprobar reconciliaciones de owner histórico, vínculos cross-clinic y correcciones contradictorias. La identidad técnica y la fuente de permisos del superadministrador siguen bloqueadas/no verificadas.

No se modifican ni sustituyen los documentos de arquitectura V1.0/V1.1. El detalle, las matrices actual/objetivo, riesgos y estado formal están en `docs/audits/TH_SEC_AUTH_08_ROLE_AND_MULTICLINIC_AUTH_AUDIT.md`.

**TH_SEC_AUTH_08_STATUS: PARTIAL — IMPLEMENTATION_AUTHORIZED: NO — APPLICATION_CODE_CHANGES: NONE — SCHEMA_CHANGES: NONE — DATA_CHANGES: NONE — COMMITS: NONE — DEPLOYMENTS: NONE — PRODUCTION_READY: NO**

## TH-SEC-P1-TRIAGE-01 — Estado de auditoría focal

La auditoría estática del 2026-10-12 reclasificó los cinco grupos P1 heredados de TH-SEC-AUTH-08: recetas externas, PDF externo, descargas genéricas, COFEPRIS y roles/superadministrador. Se verificaron las rutas registradas y las cadenas de control en controladores, modelos y middleware. Se diferenciaron omisiones `CODE_CONFIRMED` de riesgos `STATIC_RISK`, límites `NOT_VERIFIED` y controles `MITIGATED_IN_CODE` únicamente para el alcance actual de clínica/paciente vigente.

La guarda fail-closed volvió a impedir cualquier conexión porque la configuración activa resolvía `bd_clinica`. No se accedió a la base real ni a una base sintética, no se ejecutaron solicitudes HTTP de escritura, PDFs, descargas, SQL, migraciones o pruebas de explotación. El worktree se preservó y no se modificaron los documentos arquitectónicos V1.0/V1.1.

El triage queda **COMPLETE como auditoría estática**, no como remediación ni declaración de seguridad. Se recomienda revisar de forma prioritaria una contención fail-closed para las superficies externas/documentales sin owner verificable. El detalle, la matriz de rutas, trazabilidad, propuestas CATEGORY-A/B/C/D y el plan de pruebas futuras están en `docs/audits/TH_SEC_P1_TRIAGE_01_SECURITY_FINDINGS.md`.

**TH_SEC_P1_TRIAGE_01_STATUS: COMPLETE — IMPLEMENTATION_AUTHORIZED: NO — APPLICATION_CODE_CHANGES: NONE — SCHEMA_CHANGES: NONE — DATA_CHANGES: NONE — COMMITS: NONE — DEPLOYMENTS: NONE — PRODUCTION_READY: NO**

## TH-SEC-P1-CONTAIN-PLAN-01 — Estado del plan de contención

El plan documental del 2026-10-12 convierte los cinco grupos P1 del triage en propuestas por endpoint, categorías CATEGORY-A/B/C/D, lotes futuros, impacto operativo, estrategia de reversión y matriz de pruebas sintéticas. Se conserva la distinción entre hallazgo de código, riesgo estático, mitigación parcial y verificación dinámica.

El plan no implementa ninguna contención. La guarda fail-closed sigue impidiendo acceso porque la configuración activa apunta a `bd_clinica`; no se accedió a base real ni sintética, no se ejecutaron SQL, HTTP de escritura, PDFs o descargas, y no se modificaron código, esquema, datos, permisos, `.env` o arquitectura. La Entrevista Conversacional permanece fuera de alcance.

La recomendación es mantener fail-closed las superficies externas/documentales sin owner verificable y solicitar decisión del propietario antes de iniciar cualquier lote. El detalle está en `docs/audits/TH_SEC_P1_CONTAIN_PLAN_01.md`.

**TH_SEC_P1_CONTAIN_PLAN_01_STATUS: COMPLETE — P1_REMEDIATION_COMPLETED: NO — IMPLEMENTATION_AUTHORIZED: NO — APPLICATION_CODE_CHANGES: NONE — SCHEMA_CHANGES: NONE — DATA_CHANGES: NONE — COMMITS: NONE — DEPLOYMENTS: NONE — PRODUCTION_READY: NO**

## TH-SEC-P1-EVIDENCE-GATE-01 — Estado de la compuerta de evidencia

La revisión estática del 2026-10-12 verificó que `app/Core/Router.php:38-56` aplica autenticación global a `/pdf/*` y `/descargar/*`. Este control confirma sesión Doctor, pero no confirma permiso funcional, owner de recurso, clínica emisora ni estado documental del PDF externo.

Se identificaron mecanismos locales existentes para pruebas aisladas (`tests/security/isolated-router.php` y preflights de P04/P11), pero no se ejecutaron. La configuración activa sigue resolviendo `bd_clinica`; no se modificó `.env`, no se conectó ninguna base, no se ejecutó SQL/HTTP, y no se usó la sesión sintética directa como evidencia de login real. El entorno sintético queda **NO READY**.

El lote P1-A queda diseñado pero no listo para implementación ni ejecución: falta verificar owner externo, catálogo de roles/permisos, superadministrador, mapping documental y base sintética allowlisted. La Entrevista Conversacional permanece fuera de alcance.

Detalle y estado formal: `docs/audits/TH_SEC_P1_EVIDENCE_GATE_01.md`.

**TH_SEC_P1_EVIDENCE_GATE_01_STATUS: COMPLETE — SYNTHETIC_ENVIRONMENT_READY: NO — P1_REMEDIATION_COMPLETED: NO — IMPLEMENTATION_AUTHORIZED: NO — APPLICATION_CODE_CHANGES: NONE — SCHEMA_CHANGES: NONE — DATA_CHANGES: NONE — COMMITS: NONE — DEPLOYMENTS: NONE — PRODUCTION_READY: NO**

## TH-SEC-ENV-READY-01 — Estado de preparación del entorno sintético

La revisión del 2026-10-12 completó únicamente el preflight estático de configuración, router, singleton PDO y scripts de aislamiento. No se abrió ninguna conexión, no se ejecutó SQL ni HTTP, no se modificaron `.env`, código, fixtures, esquema, datos o permisos. La rama observada fue `Silvino`, con HEAD `1b08d9b4c41080c176fbe4588212863f3a1ac18c`; el worktree se preservó.

La configuración actual resuelve `bd_clinica`, mientras que el único nombre autorizado para una futura prueba es `clinica_dolor_demo_20261010_final`. La existencia de esa base, su esquema, sus fixtures y el aislamiento de la cuenta no están verificados. `tests/security/isolated-router.php` se conserva como mecanismo candidato de harness, pero su base por defecto es ambigua y sus endpoints de sesión inyectan JWT sintéticos; no constituyen evidencia de login real. `tests/p04/verify-isolation.php` queda recomendado como primer preflight dinámico una vez obtenida autorización explícita, con P11 y Phase02 como complementos.

El estado formal, los bloqueos y el procedimiento de autorización están en `docs/audits/TH_SEC_ENV_READY_01.md`. El entorno permanece **NO READY**, `DB_ACCOUNT_ISOLATION_VERIFIED: NO`, `READONLY_CONNECTION_EXECUTED: NO` y `IMPLEMENTATION_AUTHORIZED: NO`. La Entrevista Conversacional y la remediación P1 permanecen fuera de esta ejecución.

**TH_SEC_ENV_READY_01_STATUS: COMPLETE — SYNTHETIC_ENVIRONMENT_READY: NO — READONLY_CONNECTION_EXECUTED: NO — REAL_DATABASE_ACCESSED: NO — APPLICATION_CODE_CHANGES: NONE — ENV_CHANGES: NONE — SCHEMA_CHANGES: NONE — DATA_CHANGES: NONE — SQL_EXECUTED: NONE — HTTP_REQUESTS: NONE — COMMITS: NONE — DEPLOYMENTS: NONE — PRODUCTION_READY: NO**

## TH-SEC-ENV-VERIFY-02 — Estado de validación controlada del entorno

La fase del 2026-10-12 quedó bloqueada antes de PDO. La autorización era condicional y exigía evidencia administrativa de una cuenta sin acceso a `bd_clinica`, privilegios exclusivamente de lectura sobre `clinica_dolor_demo_20261010_final`, procedencia sintética de esa instancia y un método de ejecución aislado. Esa evidencia no está disponible en los documentos revisados.

Se inspeccionaron estáticamente los mecanismos existentes, el fixture SQL sintético y los scripts de preparación. No se ejecutaron conexiones, `SELECT DATABASE()`, `SHOW GRANTS`, metadatos, SQL, HTTP, fixtures, migraciones, PDF o descargas. No se modificaron `.env`, aplicación, permisos, esquema, datos ni el worktree. La base real `bd_clinica` no fue accedida.

El detalle de la matriz de esquema/fixtures, el bloqueo de recetas externas, COFEPRIS, roles y superadministrador, y el procedimiento requerido antes de una futura conexión están en `docs/audits/TH_SEC_ENV_VERIFY_02.md`.

**TH_SEC_ENV_VERIFY_02_STATUS: BLOCKED — DATABASE_CONNECTION_ALLOWED: NO — MYSQL_ACCOUNT_ISOLATION: BLOCKED — SYNTHETIC_DATA_PROVENANCE: NO — DATABASE_CONNECTION_EXECUTED: NO — REAL_DATABASE_ACCESSED: NO — APPLICATION_CODE_CHANGES: NONE — ENV_CHANGES: NONE — SCHEMA_CHANGES: NONE — DATA_CHANGES: NONE — PERMISSION_CHANGES: NONE — HTTP_REQUESTS: NONE — COMMITS: NONE — DEPLOYMENTS: NONE — PRODUCTION_READY: NO**

## TH-SEC-ENV-BOOTSTRAP-03 — Estado de infraestructura sintética aislada

La fase del 2026-10-12 quedó bloqueada en el preflight porque el cliente Docker está instalado pero el daemon no está disponible, no se detectó Podman/Nerdctl ni otra instancia independiente, y no existe infraestructura reutilizable en el repositorio. La configuración de XAMPP/MySQL y `bd_clinica` permaneció completamente fuera de alcance.

Se revisaron estáticamente los fixtures y scripts de preparación y se calcularon hashes SHA-256, pero no se importó ningún archivo ni se creó ninguna base, cuenta, red, volumen o contenedor. No se ejecutaron SQL, `SELECT DATABASE()`, `SHOW GRANTS`, HTTP, fixtures, migraciones, pruebas de escritura, commits o despliegues. La procedencia runtime de una base sintética no puede declararse verificada sin una instancia nueva.

El informe, la matriz de fixtures, los hashes, los bloqueantes y el procedimiento futuro están en `docs/audits/TH_SEC_ENV_BOOTSTRAP_03.md`. No se modificaron código de aplicación, `.env`, bases existentes, permisos ni arquitectura; la Entrevista Conversacional permaneció fuera de alcance.

**TH_SEC_ENV_BOOTSTRAP_03_STATUS: BLOCKED — DOCKER_AVAILABLE: NO — INDEPENDENT_DATABASE_INSTANCE: NO — BD_CLINICA_ACCESSED: NO — ENV_MODIFIED: NO — BOOTSTRAP_RESOURCES_CREATED: NONE — FUTURE_WRITE_TESTS_AUTHORIZED: NO — APPLICATION_CODE_CHANGES: NONE — EXISTING_DATABASE_CHANGES: NONE — P1_REMEDIATION_COMPLETED: NO — COMMITS: NONE — DEPLOYMENTS: NONE — PRODUCTION_READY: NO**

## TH-SEC-ENV-DAEMON-04 — Estado de recuperación controlada de Docker Desktop

La fase del 2026-10-12 confirmó que Docker Desktop está instalado localmente (4.74.0.227015) y que Docker CLI/Compose están disponibles, pero el proceso no estaba activo, `com.docker.service` permanecía detenido y el daemon no respondía por `docker_engine`. El launcher oficial se inició una sola vez; después del intervalo limitado el proceso no permaneció activo y el daemon siguió sin responder.

WSL devolvió `E_ACCESSDENIED` en las consultas de estado/listado y la metadata completa del contexto Docker no pudo leerse por `Access is denied`. No se instalaron, actualizaron o repararon componentes, no se usó elevación, no se cambió configuración, no se crearon recursos Docker y no se accedió a MySQL, `bd_clinica` ni la base sintética.

El detalle y el estado formal están en `docs/audits/TH_SEC_ENV_DAEMON_04.md`. TH-SEC-ENV-BOOTSTRAP-03 permanece detenido hasta una recuperación manual/autorizada de Docker Desktop y un nuevo preflight.

**TH_SEC_ENV_DAEMON_04_STATUS: BLOCKED — DOCKER_DESKTOP_INSTALLED: YES — DOCKER_DESKTOP_STARTED: NO — DOCKER_DAEMON_AVAILABLE: NO — WSL_AVAILABLE: BLOCKED — BD_CLINICA_ACCESSED: NO — EXISTING_MYSQL_ACCESSED: NO — APPLICATION_CODE_CHANGES: NONE — ENV_CHANGES: NONE — CONTAINERS_CREATED: NONE — DATABASES_CREATED: NONE — COMMITS: NONE — DEPLOYMENTS: NONE — PRODUCTION_READY: NO**

## TH-SEC-ENV-BOOTSTRAP-03R — Estado de reanudación del bootstrap sintético

La fase del 2026-10-12 se detuvo en la compuerta A. Aunque el propietario reportó Docker Desktop 4.74.0, Engine 29.4.3 y contexto `desktop-linux` operativo en su sesión, Codex no pudo resolver ese contexto: `docker --context desktop-linux version` y `info` fallaron con `Access is denied` al leer la metadata local del contexto.

No se inspeccionaron recursos Docker, no se descargaron imágenes, no se crearon contenedores, redes, volúmenes, bases o cuentas y no se ejecutó SQL, HTTP, PDO o pruebas clínicas. XAMPP, `bd_clinica`, `.env`, aplicación, fixtures y arquitectura permanecieron sin cambios.

El detalle y el estado formal están en `docs/audits/TH_SEC_ENV_BOOTSTRAP_03R.md`. La siguiente fase requiere recuperar el acceso de Codex al daemon local y repetir el preflight; no se autoriza todavía la creación de infraestructura.

**TH_SEC_ENV_BOOTSTRAP_03R_STATUS: BLOCKED — CODEX_DOCKER_ACCESS: BLOCKED — INFRASTRUCTURE_CREATION: NOT_STARTED — BD_CLINICA_ACCESSED: NO — ENV_MODIFIED: NO — APPLICATION_CODE_CHANGES: NONE — INFRASTRUCTURE_SQL_EXECUTED: NONE — APPLICATION_HTTP_TESTS: NONE — COMMITS: NONE — DEPLOYMENTS: NONE — PRODUCTION_READY: NO**

## TH-SEC-ENV-OFFLINE-PREP-05 — Estado de preparación offline

La fase del 2026-10-12 preparó únicamente artefactos revisables bajo `tests/security/infra/`: plantilla Compose sin puertos ni mounts clínicos, preflight de sólo lectura, manifiesto SHA-256, `.gitignore` de secretos y procedimiento manual. No se accedió al daemon Docker desde Codex, no se ejecutó Compose, no se crearon recursos, no se importaron fixtures y no se ejecutó SQL, HTTP o aplicación.

La disponibilidad de la imagen y la procedencia runtime de los fixtures permanecen sin verificar. El propietario debe revisar los archivos y ejecutar posteriormente el preflight desde su PowerShell autorizado; esa ejecución y cualquier aprovisionamiento requieren una fase separada. XAMPP, `bd_clinica`, `.env`, código de aplicación, fixtures preexistentes y bases existentes permanecieron intactos.

El detalle está en `docs/audits/TH_SEC_ENV_OFFLINE_PREP_05.md` y `tests/security/infra/README.md`. La Entrevista Conversacional y la remediación P1 permanecen fuera de alcance.

**TH_SEC_ENV_OFFLINE_PREP_05_STATUS: COMPLETE — DOCKER_DAEMON_ACCESSED_BY_CODEX: NO — OFFLINE_COMPOSE_PREPARED: YES — INFRASTRUCTURE_CREATED: NO — MYSQL_XAMPP_ACCESSED: NO — BD_CLINICA_ACCESSED: NO — ENV_MODIFIED: NO — APPLICATION_CODE_CHANGES: NONE — SQL_EXECUTED: NONE — HTTP_TESTS_EXECUTED: NONE — COMMITS: NONE — DEPLOYMENTS: NONE — PRODUCTION_READY: NO**

## TH-SEC-ENV-OFFLINE-HARDEN-05A — Estado de endurecimiento offline

Se reforzaron exclusivamente los artefactos offline de `tests/security/infra/`: contexto local explícito y fail-closed, clasificación de errores de colisión, validación futura de la configuración efectiva de Compose, protección externa de secretos, restricción a `mariadb:10.6`, manifiesto de fixtures con rutas canónicas y compuerta separada de procedencia. No se ejecutaron Docker, Compose, SQL, PHP, HTTP ni pruebas clínicas.

El preflight queda preparado para ejecución manual del propietario, pero permanece bloqueado hasta demostrar la procedencia sintética de los fixtures y completar la verificación runtime. `DATABASE_CREATION_AUTHORIZED: NO`, `READY_FOR_INFRASTRUCTURE_CREATION: NO` e `INFRASTRUCTURE_CREATION_READY: NO`. La Entrevista Conversacional y la remediación P1 siguen fuera de alcance.

Detalle formal: `docs/audits/TH_SEC_ENV_OFFLINE_HARDEN_05A.md`.

**TH_SEC_ENV_OFFLINE_HARDEN_05A_STATUS: COMPLETE — STATIC_HARDENING_COMPLETE: YES — MANUAL_PREFLIGHT_READY: YES — INFRASTRUCTURE_CREATION_READY: NO — DOCKER_EXECUTED_BY_CODEX: NO — INFRASTRUCTURE_CREATED: NO — APPLICATION_CODE_CHANGES: NONE — ENV_CHANGES: NONE — SCHEMA_CHANGES: NONE — CLINICAL_DATA_CHANGES: NONE — COMMITS: NONE — DEPLOYMENTS: NONE — PRODUCTION_READY: NO**
