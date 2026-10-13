# TH-SEC-ARCH-01 V1.1 — Propuesta de modelo de datos

Proyecto: Clínica del Dolor  
Fecha de revisión: 2026-10-12  
Alcance: diseño arquitectónico y documentación; no implementación.

> Todas las entidades, columnas, claves e índices de este documento están marcados como **PROPUESTO — NO EXISTE EN EL ESQUEMA ACTUAL VERIFICADO**. Este archivo no es una migración ni contiene SQL ejecutable.

## 1. Propósito y límites

Esta propuesta actualiza el modelo V1.0 para cubrir el contrato aprobado de V1.1:

- cada paciente tiene una sola clínica activa;
- los traslados se conservan como historial permanente;
- una receta conserva una clínica emisora inmutable;
- la clínica de destino puede tener acceso histórico de sólo lectura cuando la política lo autorice;
- una receta externa puede ser emitida por un médico de la clínica a una persona que todavía no tiene expediente de paciente;
- la receta externa puede vincularse manualmente después de verificar la identidad;
- vincular una receta no cambia su clínica emisora;
- la conciliación de datos legacy corresponde exclusivamente al superadministrador;
- las operaciones sensibles son auditables y los documentos originales se conservan en almacenamiento privado con integridad verificable.

No se propone ejecutar cambios en `bd_clinica`, en `clinica_dolor_demo_20261010_final` ni en ninguna otra base durante esta actividad.

## 2. Evidencia actual verificada

La inspección fue de lectura sobre `clinica_dolor_demo_20261010_final`. El esquema actual observado contiene, entre otras, estas relaciones:

| Entidad actual | Evidencia observada | Limitación para propiedad histórica |
|---|---|---|
| `clinicas` | PK `id` | No existe historial de vigencia o traslado observado |
| `pc_paciente` | PK `id`; `id_clinica` indexado y FK de fixture | Sólo expresa la clínica actual |
| `receta_medica` | PK `id`; `id_paciente` indexado | No conserva clínica emisora, emisor, documento original ni hash observado |
| `receta_medica_externos` | No está presente en la base sintética allowlisted | No se pudo verificar propietario clínico ni contrato físico actual |
| `cofepris` | PK `id`; `id_paciente` | No existe ownership histórico observado |
| `laboratorio`, `nota_subsecuente` | `id_paciente`; sin FK declarada observada | Requieren política explícita si se trasladan o se consultan históricamente |

El esquema actual no contiene las tablas propuestas en las secciones siguientes. El modelo vigente de autorización por clínica actual no debe reinterpretarse como propiedad histórica.

## 3. Diagrama del modelo actual

```text
clinicas (id)
    1
    |
    *
pc_paciente (id, id_clinica, ...)
    |
    +----< receta_medica (id, id_paciente, ...)
    +----< cofepris (id, id_paciente, ...)
    +----< laboratorio (id, id_paciente, ...)
    +----< nota_subsecuente (id, id_paciente, ...)

receta_medica_externos: ausente en el esquema sintético verificado;
no se asume relación con paciente o clínica.
```

## 4. Diagrama propuesto V1.1

```text
clinicas
  |
  +----< paciente_traslado >---- pc_paciente
  |                                  |
  |                                  +----< receta_medica_propiedad >---- receta_medica
  |                                  +----< receta_externa_vinculo_expediente >---- receta_externa
  |
  +----< receta_externa (clinica_emisora_id, medico_emisor_id, sin paciente obligatorio)
                                      |
                                      +----< receta_externa_verificacion_identidad
                                      +----< documento_receta_original
                                      +----< receta_conciliacion

Todas las operaciones sensibles --------------------< auditoria_operacion_sensible
```

Las flechas representan relaciones propuestas, no relaciones existentes. La entidad `receta_externa` es independiente del expediente al momento de emisión; el vínculo posterior se registra en una tabla separada para no confundir emisión con identidad posteriormente verificada.

## 5. Entidades propuestas

### 5.1 `paciente_traslado`

**PROPUESTO — NO EXISTE EN EL ESQUEMA ACTUAL VERIFICADO.**

Propósito: historial append-only de cambios de clínica, sin reemplazar `pc_paciente.id_clinica` como clínica activa.

Campos conceptuales:

| Campo | Tipo conceptual | Regla |
|---|---|---|
| `id` | entero/bigint | PK |
| `id_paciente` | entero/bigint | FK a `pc_paciente.id` |
| `clinica_origen_id` | entero/bigint | FK a `clinicas.id`; no nulo salvo alta inicial explícita |
| `clinica_destino_id` | entero/bigint | FK a `clinicas.id`; no nulo |
| `efectivo_en` | datetime | Orden temporal del traslado |
| `motivo_codigo` | catálogo controlado | No texto libre para autorización |
| `estado` | catálogo | solicitado, aprobado, ejecutado, cancelado |
| `solicitado_por` | identificador | Sujeto a catálogo de usuarios |
| `aprobado_por` | identificador | Debe ser una identidad con facultad de aprobación |
| `aprobado_en` | datetime | Obligatorio al ejecutar |
| `evidencia_ref` | identificador seguro | Referencia a evidencia privada, sin datos clínicos en claro |
| `created_at` | datetime | Inmutable |

Invariantes: no se permite que dos traslados ejecutados tengan el mismo intervalo activo; `clinica_destino_id` no puede ser igual al origen; una corrección se realiza mediante evento compensatorio, nunca editando el evento histórico.

### 5.2 `receta_medica_propiedad`

**PROPUESTO — NO EXISTE EN EL ESQUEMA ACTUAL VERIFICADO.**

Propósito: registrar la propiedad clínica histórica de recetas internas sin depender de la clínica actual del paciente.

Campos conceptuales:

| Campo | Tipo conceptual | Regla |
|---|---|---|
| `id` | entero/bigint | PK |
| `receta_medica_id` | entero/bigint | FK a `receta_medica.id`; único por versión de receta |
| `clinica_emisora_id` | entero/bigint | FK a `clinicas.id`; obligatorio e inmutable |
| `medico_emisor_id` | identificador | Identidad del emisor; inmutable |
| `emitida_en` | datetime | Momento de emisión |
| `estado_propiedad` | catálogo | activa, anulada, legacy_no_verificable |
| `source_type` | catálogo | nueva, migrada, reconciliada |
| `source_ref` | identificador | Referencia de origen o conciliación |
| `created_at` | datetime | Inmutable |

Restricción conceptual: nunca actualizar `clinica_emisora_id`; una transferencia del paciente no modifica la clínica emisora de la receta.

### 5.3 `receta_externa`

**PROPUESTO — NO EXISTE EN EL ESQUEMA ACTUAL VERIFICADO.**

Definición V1.1: receta emitida por un médico de la clínica autenticada a una persona que todavía no está registrada como paciente de esa clínica. “Externa” describe la ausencia de expediente al emitirla, no una emisión por un tercero ni una receta global.

Campos conceptuales:

| Campo | Tipo conceptual | Regla |
|---|---|---|
| `id` | entero/bigint | PK |
| `folio_publico` | identificador no secuencial expuesto | Único y no reutilizable |
| `clinica_emisora_id` | entero/bigint | FK a `clinicas.id`; obligatorio e inmutable |
| `medico_emisor_id` | identificador | Médico de la clínica emisora; inmutable |
| `persona_nombre` | dato identificativo | Cifrado/privado según política |
| `persona_identidad_ref` | referencia protegida | No exponer en URLs |
| `emitida_en` | datetime | Inmutable |
| `estado` | catálogo | emitida, vinculada, anulada, legacy_no_verificable |
| `created_at` | datetime | Inmutable |

No contiene un `id_paciente` obligatorio en la emisión. La falta de expediente no convierte el registro en global: siempre conserva `clinica_emisora_id`.

### 5.4 `receta_externa_vinculo_expediente`

**PROPUESTO — NO EXISTE EN EL ESQUEMA ACTUAL VERIFICADO.**

Se recomienda una tabla de vínculo separada en vez de añadir un FK nullable directamente a `receta_externa`. Así se conservan el estado de verificación, el solicitante, la decisión y el historial de cambios.

Campos conceptuales:

| Campo | Tipo conceptual | Regla |
|---|---|---|
| `id` | entero/bigint | PK |
| `receta_externa_id` | entero/bigint | FK; un vínculo activo máximo |
| `id_paciente` | entero/bigint | FK a `pc_paciente.id` |
| `estado` | catálogo | solicitado, verificado, rechazado, revocado |
| `solicitado_por` | identificador | Usuario que inicia la solicitud |
| `verificado_por` | identificador | Usuario autorizado que confirma |
| `verificado_en` | datetime | Obligatorio en verificado |
| `motivo` | código controlado | No sustituye la evidencia |
| `evidencia_ref` | identificador seguro | Referencia a evidencia de identidad |
| `created_at` | datetime | Inmutable |

El vínculo no cambia `clinica_emisora_id`. La clínica que recibe el paciente obtiene, como máximo, acceso histórico expresamente autorizado; no se convierte en propietaria de la receta.

### 5.5 `receta_externa_verificacion_identidad`

**PROPUESTO — NO EXISTE EN EL ESQUEMA ACTUAL VERIFICADO.**

Propósito: separar la prueba de identidad de la decisión de vinculación.

Campos conceptuales: `id`, `receta_externa_id`, `id_paciente` opcional durante la evaluación, método de verificación, resultado, verificador, fecha, referencia a evidencia privada, expiración y `created_at`.

Reglas: la evidencia no debe almacenarse en HTML, logs o query strings; una verificación rechazada no debe crear vínculo; una verificación no habilita por sí sola acceso cross-clinic; las correcciones deben ser eventos nuevos.

### 5.6 `documento_receta_original`

**PROPUESTO — NO EXISTE EN EL ESQUEMA ACTUAL VERIFICADO.**

Propósito: conservar el archivo original emitido y demostrar fidelidad en impresión/descarga.

Campos conceptuales:

| Campo | Tipo conceptual | Regla |
|---|---|---|
| `id` | entero/bigint | PK |
| `receta_tipo` | catálogo | interna o externa |
| `receta_id` | entero/bigint | Referencia polimórfica controlada o subtablas por tipo |
| `storage_key` | referencia privada | Nunca una ruta pública controlable por usuario |
| `mime_type` | catálogo | Allowlist |
| `byte_size` | entero/bigint | Límite validado |
| `sha256` | binario/texto normalizado | Integridad del contenido |
| `created_at` | datetime | Inmutable |
| `created_by` | identificador | Emisor del documento |
| `retention_state` | catálogo | vigente, retenido, legal_hold |

La descarga debe autorizar el recurso antes de abrir el `storage_key`, y la salida debe corresponder al hash y al MIME registrados. La regeneración desde datos actuales no sustituye la conservación del original.

### 5.7 `receta_conciliacion`

**PROPUESTO — NO EXISTE EN EL ESQUEMA ACTUAL VERIFICADO.**

Propósito: reconciliar legacy ambiguo sin alterar silenciosamente su propietario.

Campos conceptuales: `id`, tipo/ID de registro, clasificación (`verificada`, `no_verificable`, `conflicto`, `duplicada`), hipótesis de clínica, evidencia, propuesta, decisión, `decidido_por`, `decidido_en`, versión y `created_at`.

Sólo el superadministrador puede aprobar el resultado. Un registro sin evidencia suficiente permanece `legacy_no_verificable` y fail-closed para operaciones sensibles.

### 5.8 `auditoria_operacion_sensible`

**PROPUESTO — NO EXISTE EN EL ESQUEMA ACTUAL VERIFICADO.**

Propósito: trazabilidad de emisión, vínculo, traslado, reconciliación, lectura histórica privilegiada, impresión, descarga y anulación.

Campos conceptuales: `id`, actor, rol, clínica activa, acción, recurso/tipo, recurso/ID interno, resultado, motivo, correlation ID, IP/cliente según política, `created_at`, y hash de encadenamiento si se requiere tamper evidence.

No registrar contenido clínico ni tokens. La auditoría debe ser append-only para el actor de aplicación y accesible sólo a roles autorizados.

## 6. Claves, índices y restricciones propuestas

**PROPUESTO — NO EXISTE EN EL ESQUEMA ACTUAL VERIFICADO.** Como mínimo, el diseño de implementación deberá evaluar:

- FK `paciente_traslado.id_paciente → pc_paciente.id` y FKs a `clinicas.id`.
- Índice por `(id_paciente, efectivo_en)` en traslados.
- Unicidad de la receta interna en `receta_medica_propiedad.receta_medica_id`.
- Índice por `(clinica_emisora_id, emitida_en)` para listados autorizados.
- Unicidad de `receta_externa.folio_publico`.
- Índice de `receta_externa_vinculo_expediente` por receta, paciente y estado.
- Unicidad parcial o equivalente para un vínculo activo por receta.
- Índice de `documento_receta_original` por tipo/ID y hash.
- Índices de auditoría por recurso, actor, clínica y fecha.
- Prohibición de cascadas destructivas sobre propiedad, documentos y auditoría.

La sintaxis exacta, el motor, el tratamiento de índices parciales y la estrategia de FKs deben decidirse durante una fase posterior autorizada.

## 7. Invariantes de dominio

1. `pc_paciente.id_clinica` representa una sola clínica activa.
2. Cada traslado ejecutado crea un evento permanente; no se edita ni borra para corregir el pasado.
3. `clinica_emisora_id` de una receta es inmutable.
4. La receta externa puede existir sin expediente de paciente.
5. Vincular después una receta externa no cambia su origen ni su emisor.
6. La identidad debe verificarse antes de crear el vínculo activo.
7. La clínica de destino no recibe propiedad histórica automáticamente.
8. Un registro sin owner verificable no se muestra ni se descarga por inferencia.
9. La impresión/descarga debe usar el original privado cuando exista y registrar la operación.
10. La conciliación legacy es una decisión explícita, auditable y exclusiva del superadministrador.
11. Las consultas de listado, detalle, AJAX, PDF y descarga deben compartir la misma decisión de autorización.
12. La auditoría no debe depender de datos enviados por el navegador para determinar la clínica emisora.

## 8. Alternativas de vinculación externa

| Alternativa | Ventaja | Riesgo | Decisión V1.1 |
|---|---|---|---|
| FK nullable en `receta_externa` | Menos tablas | Mezcla emisión con vínculo y pierde historial de verificación | No recomendada |
| Tabla `receta_externa_vinculo_expediente` | Historial y estados explícitos | Más joins y flujo de aprobación | Recomendada |
| Híbrida: FK actual + eventos | Consultas simples | Duplicidad y divergencia de fuentes | Sólo si una migración posterior define fuente única |

La propuesta no autoriza implementar ninguna alternativa. La elección final requiere decisión de propietario del producto sobre actores, evidencia y escenarios cross-clinic.

## 9. Compatibilidad legacy

Durante una futura migración:

- no se debe inferir una clínica emisora sólo desde `pc_paciente.id_clinica` actual;
- recetas con owner no demostrable se marcan `legacy_no_verificable`;
- no se debe fabricar un PDF original a partir de una regeneración actual y llamarlo original;
- cada clasificación debe guardar evidencia y actor superadministrador;
- la migración debe ser idempotente, reversible por compensación y ejecutada sólo sobre una copia aprobada;
- los registros ambiguos permanecen fail-closed para descarga, edición y vinculación automática.

## 10. Matriz de autorización propuesta

| Operación | Clínica emisora | Clínica actual del paciente | Superadmin | Condición |
|---|---:|---:|---:|---|
| Emitir receta propia | Sí | Según expediente disponible | Sí | Actor autenticado y autorizado |
| Leer receta interna propia | Sí | Sí si política lo autoriza | Sí | Owner histórico verificado |
| Leer receta histórica de otra clínica | No por defecto | No por defecto | Sí | Acceso histórico explícito, sólo lectura y auditoría |
| Editar/anular receta | Según política de emisor | No por traslado automático | Sí | Regla de negocio explícita y auditada |
| Crear receta externa sin expediente | Sí | No aplica | Sí | Médico de clínica emisora |
| Solicitar vínculo externo | Según política | Según política | Sí | Identidad aportada; no crea acceso todavía |
| Aprobar vínculo externo | Actor autorizado definido pendiente | Actor autorizado definido pendiente | Sí | Verificación de identidad y auditoría |
| Conciliar legacy | No | No | Sí | Evidencia y decisión append-only |
| Descargar original | Sólo si autorizado | Sólo si autorizado | Sí | Owner/documento verificado y auditoría |

“Sí” no equivale a autorización automática: la política concreta, los roles y la evidencia deben formalizarse antes de implementar.

## 11. Decisiones pendientes para implementación

- AUTH-01: rol exacto que puede solicitar y aprobar la vinculación.
- AUTH-02: si la clínica actual puede consultar una receta emitida por otra clínica y con qué alcance.
- AUTH-03: evidencia mínima de identidad y retención.
- AUTH-04: manejo de duplicados o coincidencias de persona.
- AUTH-05: política de anulación y corrección de una receta ya emitida.
- AUTH-06: política de legacy sin documento original o owner verificable.
- AUTH-07: almacenamiento, cifrado, rotación y recuperación del documento original.
- AUTH-08: mecanismo de roles/superadmin que hoy no está verificable en la base sintética.

## 12. Alcance no ejecutado

No se ejecutó SQL, DDL, migración, seed, importación, modificación de registros, cambio de permisos, cambio de rutas, modificación de PHP, CSS o JavaScript, ni acceso a información clínica real. El documento no constituye aprobación para producción.

## 13. Relación con V1.0

La propuesta amplía V1.0 sin reemplazarla:

- V1.0 sigue siendo la base de propiedad histórica, traslado, documento original, conciliación y fail-closed legacy.
- V1.1 agrega la definición de receta externa sin expediente al emitir y el vínculo posterior auditado.
- La recomendación de tabla de vínculo separada evita reinterpretar una receta externa como receta sin dueño o receta global.
- Los documentos V1.0 permanecen intactos.

Documentos relacionados:

- [Arquitectura V1.0](TH_SEC_ARCH_01_HISTORICAL_RECIPE_OWNERSHIP.md)
- [Modelo V1.0](TH_SEC_ARCH_01_DATA_MODEL_PROPOSAL.md)
- [Arquitectura V1.1](TH_SEC_ARCH_01_HISTORICAL_RECIPE_OWNERSHIP_V1.1.md)

## 14. Estado formal

```text
TH_SEC_ARCH_01_V1_1:
- STATUS: COMPLETE
- CURRENT_BRANCH: Silvino
- CURRENT_HEAD: 1b08d9b4c41080c176fbe4588212863f3a1ac18c
- WORKTREE_PRESERVED: YES
- ORIGINAL_ARCHITECTURE_PRESERVED: YES
- APPROVED_DECISIONS_INCORPORATED: DEC-01..DEC-05 de V1.0; definición de receta externa propia sin expediente; vinculación posterior manual auditada; owner de emisión inmutable
- SINGLE_ACTIVE_CLINIC: YES — una clínica activa por paciente
- TRANSFER_HISTORY: PROPUESTO — evento append-only
- INTERNAL_RECIPE_ORIGIN: PROPUESTO — propiedad separada con clínica y médico emisores inmutables
- EXTERNAL_RECIPE_DEFINITION: PROPUESTO — médico de clínica propia a persona aún sin expediente
- EXTERNAL_RECIPE_OWNERSHIP: PROPUESTO — clínica emisora inmutable
- EXTERNAL_PATIENT_WITHOUT_RECORD: YES — permitido al emitir
- LATER_PATIENT_REGISTRATION: YES — mediante alta posterior sin cambiar el owner de emisión
- MANUAL_RECIPE_LINKING: PROPUESTO — tabla de vínculo y estados auditados
- IDENTITY_VERIFICATION_DESIGN: PROPUESTO — verificador, evidencia privada y decisión auditada
- CROSS_CLINIC_LINKING_DESIGN: PROPUESTO — solicitud y aprobación explícitas; política pendiente
- CLINIC_ORIGIN_IMMUTABILITY: YES — inmutable en receta y documento
- HISTORICAL_READ_ACCESS: PROPUESTO — sólo lectura y según política explícita
- PDF_ORIGINAL_FIDELITY: PROPUESTO — bytes originales privados y hash; legacy sin original no se presenta como original
- SUPERADMIN_RECONCILIATION: YES — exclusiva y append-only
- LEGACY_DATA_HANDLING: FAIL-CLOSED si no hay owner o documento verificable
- PROPOSED_SCHEMA: paciente_traslado; receta_medica_propiedad; receta_externa; receta_externa_vinculo_expediente; receta_externa_verificacion_identidad; documento_receta_original; receta_conciliacion; auditoria_operacion_sensible
- ARCHITECTURE_DIAGRAM: incluido en la sección 4
- V1_0_V1_1_CHANGELOG: incluido en la sección 13 y en el documento arquitectónico V1.1
- USER_DECISIONS_REQUIRED: AUTH-01..AUTH-08
- IMPLEMENTATION_STARTED: NO
- SCHEMA_CHANGES: NONE
- APPLICATION_CODE_CHANGES: NONE
- DATA_CHANGES: NONE
- DOCUMENTATION_CREATED: TH_SEC_ARCH_01_HISTORICAL_RECIPE_OWNERSHIP_V1.1.md; TH_SEC_ARCH_01_DATA_MODEL_PROPOSAL_V1.1.md
- GLOBAL_DIFF_CHECK: PASS — advertencias históricas LF/CRLF no corregidas
- PRODUCTION_READY: NO
```
