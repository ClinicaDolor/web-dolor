# TH-DOCTOR-COFEPRIS-PHASE-12D-IMPLEMENTATION-PROPOSAL

## Estado del documento

**PROPUESTA — NO IMPLEMENTADA.**

Este documento prepara una futura fase de implementación para folios COFEPRIS independientes por clínica. No contiene una migración ejecutable y no autoriza cambios en PHP, JavaScript, rutas, permisos, esquema, datos ni documentos.

| Evidencia | Estado |
|---|---|
| Decisión funcional: folios independientes por clínica | **CONFIRMADA por el propietario** |
| Base | `bd_clinica`, confirmada por el propietario mediante phpMyAdmin |
| Motor | MariaDB 10.4.32, reportado por el propietario |
| `cofepris_folio` sin `id_clinica` | **CONFIRMADO por SQL local reportado** |
| 6 rangos / 1,200 folios declarados | **CONFIRMADO por conteo reportado** |
| 345 registros / 344 relaciones válidas / 1 huérfano | **CONFIRMADO por conteo reportado** |
| Prueba Codex contra MySQL | No disponible: socket bloqueado |
| Pruebas HTTP entre dos clínicas | No ejecutadas |
| Migración o backfill | No ejecutados |

## 1. Objetivo arquitectónico

El sistema debe garantizar que:

1. Cada rango COFEPRIS tenga una clínica propietaria verificable.
2. La clínica provenga del contexto autenticado del servidor.
3. Un Doctor no pueda leer, editar ni surtir rangos o registros de otra clínica cambiando IDs.
4. Un registro COFEPRIS conserve su relación con el paciente autorizado.
5. El PDF sólo se entregue si coinciden clínica, paciente, registro y documento.
6. Los seis rangos históricos no se asignen automáticamente por existir actualmente una sola clínica.
7. El registro huérfano se conserve y se trate fail-closed.

## 2. Modelo objetivo propuesto

### 2.1 Entidades

```text
clinicas
  1 ─── n cofepris_folio
              |
              | FK durable de asignación de rango
              v
        cofepris (uso de folio)
              |
              | id_paciente
              v
        pc_paciente ─── id_clinica
              |
              v
        documento COFEPRIS privado/autorizado
```

### 2.2 Recomendación estructural mínima segura

La propuesta preferida es una variante de la alternativa técnica 1 de 12C:

- Agregar propiedad de clínica a `cofepris_folio`.
- Mantener `cofepris.id_paciente` como relación clínica/paciente.
- Agregar una referencia durable del registro de uso al rango (`cofepris_folio_id` conceptual), porque relacionar sólo por `carpeta` puede ser ambiguo si existen carpetas repetidas o históricas.
- Validar que la clínica del rango coincida con la clínica del paciente al crear o editar.
- Mantener `carpeta` y `folio` como valores históricos/comerciales durante la transición; no usarlos solos como autorización.
- Mover o proteger los PDF con una ruta que incluya una identidad documental interna y no sea enumerable directamente.

Si el inventario histórico demuestra carpetas compartidas, rangos solapados o documentos sin relación durable, debe sustituirse por una entidad de asignación explícita equivalente a la alternativa 2/4 de 12C.

### 2.3 Transición de nulabilidad

No se debe introducir `NOT NULL` de inmediato en una tabla histórica:

- Las filas nuevas deben exigir clínica propietaria.
- Las filas históricas deben conservar un estado explícito de propietario desconocido o una relación nullable durante conciliación.
- Las operaciones administrativas deben excluir fail-closed los registros sin propietario demostrado.
- Sólo después de resolver los grupos históricos y aprobar la evidencia se puede evaluar `NOT NULL`.

## 3. Cambios de esquema propuestos — no ejecutables

### 3.1 Catálogo de rangos

Propuesta conceptual:

- `cofepris_folio.id_clinica` — FK a `clinicas(id)`, nullable durante transición.
- Índice por `(id_clinica, estado, id)` para obtener rangos activos de una clínica.
- Unicidad de `(id_clinica, carpeta)` sólo si se confirma que una carpeta representa una asignación única.
- Restricción o procedimiento para impedir rangos solapados dentro de una clínica.
- Mantener `folio_inicial`, `folio_final`, `total_folio` y `estado` sin reinterpretarlos hasta validar su contrato.

### 3.2 Registro de uso

Propuesta conceptual:

- `cofepris.cofepris_folio_id` — FK nullable durante conciliación.
- Índice por `(cofepris_folio_id, folio)`.
- Unicidad de `(cofepris_folio_id, folio)` sólo después de revisar duplicados históricos y reglas de negocio.
- Mantener `id_paciente`; validar su clínica en servidor.
- No eliminar `carpeta`/`folio` durante la primera migración para preservar compatibilidad y trazabilidad histórica.

### 3.3 Documentos

Alternativa mínima:

- Ruta privada fuera de un directorio público enumerable.
- Nombre interno generado por servidor usando la identidad del registro/rango.
- Metadata mínima de documento: identidad de registro, rango, clínica, path interno, hash/tipo/tamaño y estado de procesamiento si el contrato lo requiere.

Alternativa completa:

- Tabla documental separada con relación a clínica, registro COFEPRIS, paciente y ruta interna.
- Auditoría de generación y descarga si se requiere trazabilidad normativa.

No se recomienda conservar la autorización únicamente en `carpeta/folio` ni confiar en el path recibido del cliente.

### 3.4 Borrador no autorizado

No se incluye SQL ejecutable. Cualquier DDL futuro debe estar etiquetado:

```text
BORRADOR — NO AUTORIZADO PARA EJECUCIÓN
```

## 4. Unicidad, rangos y formato

La evidencia reportada indica 342 valores de folio distintos entre registros con paciente válido, pero no demuestra unicidad de la clave lógica completa. Antes de elegir índices se debe verificar:

- duplicados por `carpeta + folio` como texto;
- ceros iniciales;
- folios no numéricos;
- rangos solapados;
- carpetas repetidas;
- folios fuera de rango;
- efecto de `CAST(... AS UNSIGNED)` usado actualmente por el generador;
- si el número comercial puede repetirse entre clínicas;
- si `estado=0/1` significa activo/agotado y si existe otro estado implícito.

No se debe convertir `VARCHAR(50)` de `carpeta` ni `VARCHAR(20)` de `folio` a tipos numéricos sin confirmar su significado. El cálculo actual con `MAX(CAST(folio AS UNSIGNED))` puede perder ceros iniciales y tiene riesgo de carrera; debe reemplazarse o encapsularse en una operación transaccional posterior.

## 5. Tratamiento histórico

### 5.1 Evidencia incorporada

```yaml
CLINICS_TOTAL: 1
CLINIC_1_EXISTS: YES
COFEPRIS_TOTAL: 345
VALID_PATIENT_RELATIONS: 344
ORPHAN_RECORDS: 1
COFEPRIS_RANGES: 6
DECLARED_FOLIOS: 1200
RANGES_WITH_ESTADO_1: 5
RANGES_WITH_ESTADO_0: 1
FOLIOS_SHARED_BETWEEN_CLINICS: 0_REPORTED_NOT_CONCLUSIVE
```

La única clínica actual no permite atribuir automáticamente los seis rangos a `id=1`. La relación paciente-clínica prueba el ámbito de 344 registros válidos, no la propiedad histórica del catálogo de rangos.

### 5.2 Grupos de conciliación

| Grupo | Tratamiento propuesto |
|---|---|
| H1: rango con propietario inequívoco | Asignar sólo con evidencia administrativa o documental independiente; validar unicidad |
| H2: rango sin propietario | Mantener como `owner_unknown`; no mostrar a ninguna clínica por defecto |
| H3: rango con múltiples propietarios posibles | Revisión humana; no reasignar automáticamente |
| H4: rango sin uso | No asignar por conveniencia; requiere evidencia de administración |
| H5: referencia/documento inconsistente | Preservar y marcar; separar conciliación documental de migración |
| Registro huérfano | Conservar; impedir acciones que requieran paciente/clínica; no borrar ni reasignar |

### 5.3 Regla de no asignación

No se puede usar como evidencia suficiente:

- que haya una sola clínica en la base actual;
- que todos los registros válidos actuales apunten a clínica 1;
- el primer paciente encontrado;
- el número de carpeta sin fuente administrativa;
- el documento físico por sí solo sin una relación verificable.

## 6. Inventario de dependencias para Fase 12D

| Archivo | Cambio futuro previsto | Riesgo/compatibilidad |
|---|---|---|
| `app/Config/routes.php` | Mantener rutas; no se propone cambio de URL | Compatibilidad externa |
| `app/Controllers/HomeController.php` | Obtener clínica del middleware y pasar sólo contexto seguro | Preservar token 12A |
| `app/Controllers/CofeprisController.php` | Scope de listar/crear carpeta/rango/surtido; resolver recursos antes de actuar | No confiar en IDs del frontend |
| `app/Controllers/ClinicaController.php` | Crear/editar sólo con paciente y registro dentro de clínica | Corregir relación `idCofepris`/`idPaciente` |
| `app/Controllers/BusquedasController.php` | Autorizar antes de lookup y devolver sólo recursos del tenant | Evitar enumeración por ID |
| `app/Controllers/PdfController.php` | Autorizar clínica, paciente, registro y archivo antes de abrirlo | Proteger path y PDF |
| `app/Models/CofeprisModel.php` | Consultas con scope, generación transaccional, updates por ownership | Cambia contratos internos de CRUD |
| `app/Services/ClinicalSummarySecurity.php` | Conservar `clinicActionCsrfToken()` de 12A | No romper tokens existentes |
| `app/Services/ClinicalSupplementService.php` | Mantener lecturas por paciente y revisar join de clínica | Resumen clínico |
| `app/Services/ClinicalNarrativeService.php` | Mantener narrativa por paciente; no incluir documentos no autorizados | Riesgo de resumen cross-scope |
| `app/Models/ClinicaModel.php` | Mantener métricas con join paciente-clínica | Validar conteos post-migración |
| `app/Services/ClinicaIndicadoresService.php` | Mantener métricas tenant-scoped | Validar rangos excluidos |
| `app/views/clinica/configuracion-cofepris.php` | Conservar modal y CSRF; no usar `id_clinica` del cliente como autoridad | UI no es seguridad |
| `app/views/clinica/pacientes-cofepris.php` | Conservar modales, tabla y navegación; enviar sólo IDs funcionales | Backend debe resolver ownership |
| `app/views/clinica/cofepris-folios.php` | Mostrar sólo rangos autorizados | No modificar para ocultar riesgo |
| `app/views/clinica/cofepris.php` | Mantener detalle y PDF autorizado | No exponer huérfanos |
| `public/storage/cofepris` | Migrar/proteger ubicación | Prueba de URL directa obligatoria |

## 7. Autorización por operación y ruta

| Operación | Fuente confiable | Regla futura |
|---|---|---|
| Listar rangos | JWT `id_clinica` | `cofepris_folio.id_clinica = auth.id_clinica` |
| Crear rango | JWT `id_clinica` | El modelo recibe contexto server-side; no acepta tenant del formulario |
| Editar rango | JWT `id_clinica` + rango | Resolver rango por ID y clínica antes de modificar |
| Ver carpeta | JWT `id_clinica` + carpeta | Rechazar carpeta sin propietario o de otra clínica |
| Crear registro | JWT + paciente autorizado + rango autorizado | La clínica del paciente y rango deben coincidir |
| Ver registro | JWT + registro + paciente/rango | Query autorizada, no lookup libre seguido de guard tardío |
| Editar registro | JWT + registro + paciente/rango | `UPDATE` debe incluir ownership en la condición |
| Surtir | JWT + registro/rango | Validar propiedad antes del cambio de estado |
| Tabla AJAX paciente | JWT + `assertPatientAccess` | ID no puede ampliar el alcance |
| Detalle AJAX | JWT antes de resolver contenido | No devolver HTML de recurso ajeno |
| Generar PDF | JWT + clínica + paciente + registro | Autorizar antes de resolver path y abrir archivo |
| URL directa de archivo | Política de storage | Denegar por servidor o mantener fuera de ruta pública |

Las respuestas para recursos ajenos deben ser fail-closed y compatibles con la arquitectura existente, sin revelar si el ID existe.

## 8. Cambios PHP futuros por archivo

### `CofeprisModel.php`

- Reemplazar consultas globales de rangos por consultas tenant-scoped.
- Recibir el `id_clinica` confiable desde el controlador o un objeto de contexto server-side.
- Resolver el rango por identidad durable, no sólo por carpeta.
- Validar que el paciente y rango pertenecen a la misma clínica.
- Condicionar `UPDATE` de registro por registro, paciente/rango y clínica.
- Condicionar `UPDATE` de surtido por registro/rango/clínica.
- Eliminar concatenación SQL en consultas heredadas.
- Conservar valores de folio como texto cuando la presentación lo requiera.
- Evitar carreras en generación mediante transacción/bloqueo y constraint.

### `CofeprisController.php`

- Obtener `authPermisos()` una vez y pasar la clínica autenticada.
- Scope de `insertFolios`, carpeta y surtido.
- Mantener CSRF 12A antes de procesar archivos.
- No aceptar `id_clinica` del body como autoridad.

### `ClinicaController.php`

- Autorizar paciente antes de cargar datos del registro.
- Para edición, consultar el registro con ambos IDs y clínica.
- Mantener el contrato JSON existente salvo respuestas fail-closed necesarias.
- Revisar CSRF para la escritura de registro si el contrato de aplicación lo autoriza en una fase independiente.

### `BusquedasController.php`

- Resolver `guardPatient`/`guardClinic` antes de ejecutar lookup por ID.
- Consultar sólo filas autorizadas.
- Evitar que un registro huérfano genere HTML clínico.

### `PdfController.php`

- Resolver autorización clínica y paciente antes de calcular el path.
- Validar la relación registro-rango-documento.
- Rechazar archivo inexistente, no relacionado o de otra clínica.
- No aceptar path o nombre de archivo del cliente.

### Servicios y métricas

- Mantener los métodos existentes de `ClinicalSummarySecurity`.
- No cambiar tokens de captura/interview.
- Validar que `ClinicalSupplementService`, `ClinicaModel` y `ClinicaIndicadoresService` sólo cuenten filas ligadas a pacientes de la clínica autorizada.

## 9. Documentos y PDF

La implementación debe separar:

1. autorización del registro COFEPRIS;
2. autorización de clínica del rango;
3. autorización del paciente;
4. resolución del documento correcto;
5. lectura del archivo desde storage protegido.

El path actual `public/storage/cofepris/{carpeta}/{folio}.pdf` no debe considerarse suficiente para aislamiento. La propuesta es:

- storage fuera de acceso público o regla de servidor que niegue acceso directo;
- path interno generado por servidor;
- identificación por registro/rango estable;
- respuesta PDF sólo después de authorization gate;
- prueba de URL directa, path traversal, archivo de otra clínica y archivo huérfano.

No se modificarán PDFs históricos durante esta fase. Su migración debe preservar hash, asociación y rollback.

## 10. Plan de implementación posterior

### Etapa 0 — aprobación y respaldo

- Aprobar este diseño.
- Respaldar base y storage.
- Probar restauración.
- Congelar el inventario de rangos y documentos.

### Etapa 1 — preflight de metadatos

- Ejecutar `SHOW CREATE TABLE`, índices y FK en `bd_clinica`.
- Confirmar que los conteos reportados son reproducibles.
- Confirmar carpetas, rangos, formato y solapamientos.

### Etapa 2 — migración compatible

- Introducir relaciones nullable/transitorias.
- Crear índices no destructivos.
- No aplicar `NOT NULL` aún.
- Mantener lecturas legacy sólo durante la transición controlada.

### Etapa 3 — conciliación histórica

- Clasificar H1-H5.
- Registrar evidencia y revisión.
- Asociar sólo casos demostrados.
- Mantener H2/H3 en estado desconocido/no exponible.

### Etapa 4 — autorización y generación

- Adaptar consultas y operaciones.
- Resolver rangos por tenant.
- Aplicar transacciones y constraints.
- Preservar CSRF 12A.

### Etapa 5 — documentos

- Proteger storage.
- Asociar documento con registro/rango/clínica.
- Probar acceso propio/ajeno y URL directa.

### Etapa 6 — validación y transición

- Ejecutar matriz sintética de dos clínicas.
- Comparar conteos antes/después.
- Validar módulos consumidores.
- Habilitar nuevas escrituras sólo tras evidencia.

## 11. Rollback futuro

- Mantener snapshot de base, índices y storage.
- Registrar cada asignación histórica en un ledger de migración.
- Revertir código antes de retirar compatibilidad legacy.
- Desactivar nuevas escrituras si fallan las pruebas.
- Restaurar relaciones nuevas sin borrar registros históricos.
- Revalidar acceso por clínica y PDF después del rollback.

No crear scripts de rollback ejecutables hasta aprobación independiente.

## 12. Plan de pruebas de Fase 12D

Con dos clínicas sintéticas A/B:

1. A lista sólo rangos A.
2. B lista sólo rangos B.
3. A no lee rango B por ID.
4. B no lee rango A por ID.
5. A crea sólo en su clínica aunque manipule `id_clinica` del body.
6. A no edita registro B.
7. A no surte registro B.
8. A no descarga PDF B.
9. B no descarga archivo A.
10. Sin sesión no hay lectura ni descarga.
11. CSRF 12A válido funciona; inválido falla.
12. Rango solapado es rechazado.
13. Folio duplicado es rechazado conforme a la clave aprobada.
14. Registro huérfano no se expone ni se modifica.
15. Histórico conciliado aparece sólo en su clínica.
16. Histórico ambiguo permanece aislado.
17. Modal de Fase 12 conserva abrir/cerrar/validación.
18. Resumen clínico e indicadores no cruzan tenants.
19. URL directa al storage no entrega archivos.
20. PDF verifica clínica, paciente, registro y documento.
21. Concurrencia no genera folios repetidos.
22. Rollback conserva conteos, hashes y accesos autorizados.

En la fase actual no se ejecutó ninguno de estos casos.

## 13. Riesgos y bloqueos

### Riesgos

- Asignar históricamente rangos a clínica 1 por inferencia.
- Confundir `carpeta` con identidad global.
- Perder ceros iniciales por el `CAST` actual.
- Crear una unicidad incompatible con rangos históricos.
- Mantener PDF bajo storage público.
- Autorizar por ID antes de confirmar propiedad.
- Romper el token CSRF de 12A o consumidores de resumen.

### Bloqueos

- La evidencia de phpMyAdmin no fue reproducible desde Codex.
- No hay escenario actual de dos clínicas para demostrar aislamiento.
- No se ha confirmado el significado funcional de `estado`.
- No se ha confirmado si `carpeta` es única, reutilizable o compartida.
- No se ha conciliado el registro huérfano.

## 14. Decisiones pendientes para autorizar 12D

1. Aprobar la relación durable `cofepris_folio_id` o una tabla de asignación equivalente.
2. Aprobar el tratamiento de rangos con propietario desconocido.
3. Confirmar si `carpeta` puede repetirse entre clínicas.
4. Confirmar unicidad comercial del folio por clínica.
5. Aprobar storage privado y eventual migración de PDFs.
6. Aprobar cambios de autorización en edición, surtido y PDF.
7. Aprobar pruebas sintéticas de dos clínicas.
8. Aprobar una fase separada de DDL/backfill.

## 15. Informe final

```yaml
TH_DOCTOR_COFEPRIS_SCHEMA_VERIFICATION_CLOSURE_12C_R2:
  STATUS: PARTIAL_R1_NUMERIC_RECONCILIATION_CLOSED_DOCUMENT_TRACEABILITY_PENDING
  DATABASE: bd_clinica
  MARIADB_VERSION: 10.4.32
  OWNER_SQL_EVIDENCE_INCORPORATED: YES
  CODEX_SQL_EXECUTED: NO

  CLINICS_TOTAL: 1
  CLINIC_1_EXISTS: YES
  COFEPRIS_TOTAL: 345
  VALID_PATIENT_RELATIONS: 344
  ORPHAN_RECORDS: 1
  COFEPRIS_RANGES: 6
  DECLARED_FOLIOS: 1200
  RANGES_WITH_ESTADO_1: 5
  RANGES_WITH_ESTADO_0: 1

  CLINIC_OWNERSHIP_MODEL: INDEPENDENT
  TARGET_ARCHITECTURE: CLINIC_OWNED_RANGE_PLUS_DURABLE_RECORD_SCOPE_PLUS_PROTECTED_DOCUMENT
  RECOMMENDED_SCHEMA_CHANGE: COFEPRIS_FOLIO_CLINIC_OWNER_PLUS_DURABLE_USAGE_REFERENCE
  HISTORICAL_RECONCILIATION: H1_H5_WITHOUT_AUTOMATIC_ASSIGNMENT
  RANGE_OVERLAP_ANALYSIS: 0_OWNER_REPORTED
  DOCUMENT_AUTHORIZATION_PLAN: CLINIC_PATIENT_RECORD_DOCUMENT_GATE_RUNTIME_PENDING
  HISTORICAL_EXCEPTIONS: RECORDS_23_26_FOLDER_MISMATCH_RECORDS_256_257_SHARED_FOLDER_FOLIO

  PHASE_12D_DESIGN: CREATED
  PHASE_12D_READY_FOR_APPROVAL: YES_FOR_REVIEW_ONLY_NO_IMPLEMENTATION_AUTHORIZATION

  CODE_MODIFIED: NO
  DATABASE_MODIFIED: NO
  SCHEMA_MODIFIED: NO
  MIGRATIONS_EXECUTED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE

  DOCUMENTS_CREATED_OR_UPDATED:
    - docs/audits/TH_DOCTOR_COFEPRIS_CLINIC_ISOLATION_ARCHITECTURE_AND_SCHEMA_VERIFICATION_12C.md
    - docs/audits/TH_DOCTOR_COFEPRIS_PHASE_12D_IMPLEMENTATION_PROPOSAL.md
  PENDING_DECISIONS: RANGE_OWNER_POLICY; FOLDER_SEMANTICS; UNIQUE_KEY; STORAGE_POLICY; DDL_BACKFILL_AUTHORIZATION
  BLOCKERS: HISTORICAL_OWNER_ASSIGNMENT; DOCUMENT_STORAGE_AUTHORIZATION; EXCEPTION_POLICY_23_26_256_257; IMPLEMENTATION_AUTHORIZATION
  RESIDUAL_RISKS: HISTORICAL_OWNERSHIP; EDIT_BY_ID; SURTIDO_BY_ID; PDF_DIRECT_ACCESS; LEGACY_SQL; FOLIO_RACE
```

**No se implementó la Fase 12D.**

## 16. Entrada de conciliación histórica 12D-01

La auditoría focalizada 12D-01 quedó documentada en:

`docs/audits/TH_DOCTOR_COFEPRIS_HISTORICAL_RANGE_RECONCILIATION_12D_01.md`

Las consultas read-only para ejecutar manualmente en MariaDB 10.4.32 están en:

`docs/audits/TH_COFEPRIS_12D01_READ_ONLY_SQL_QUERIES.md`

Su estado es `PARTIAL_NUMERIC_RECONCILIATION_CLOSED_DOCUMENT_TRACEABILITY_PENDING`: los resultados numéricos agregados fueron reportados por el propietario y consolidan 343 coincidencias, dos excepciones, cero fuera de rango, cero candidatos múltiples, cero solapamientos y cero carpetas duplicadas entre rangos. La propiedad clínica histórica, la identidad física de documentos y la autorización del storage siguen sin demostrarse. No se autoriza backfill, DDL, índices ni restricciones.

## 17. Entrada R1 para el diseño siguiente

Las excepciones 23 y 26 requieren una decisión sobre carpeta inconsistente. Los registros 256 y 257 comparten carpeta y folio; el código calcularía la misma ruta lógica, pero no se verificó existencia, acceso compartido ni sobrescritura física. Los seis rangos permanecen `H2_OWNER_UNKNOWN` y no están listos para backfill.

Para 12D-02:

- `cofepris_folio.id_clinica`: `REQUIRES_HISTORICAL_RECONCILIATION` y `REQUIRES_OWNER_DECISION`.
- `cofepris.cofepris_folio_id`: `REQUIRES_HISTORICAL_RECONCILIATION`.
- Índices de consulta por clínica: `READY_FOR_DESIGN`, implementación bloqueada.
- Unicidad y restricciones anti-solapamiento: requieren decisión de negocio y conciliación previa.
- Protección documental: lista para diseño, bloqueada para implementación hasta definir storage y autorización runtime.

La entrada completa y la matriz están en `TH_DOCTOR_COFEPRIS_HISTORICAL_RANGE_RECONCILIATION_12D_01.md`.

**PRODUCTION_READY: NO**
**COMMITS: NONE**
**DEPLOYMENTS: NONE**
