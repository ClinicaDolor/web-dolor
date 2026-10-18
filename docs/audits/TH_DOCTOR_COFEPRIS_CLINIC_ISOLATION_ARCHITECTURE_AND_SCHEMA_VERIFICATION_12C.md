# TH-DOCTOR-COFEPRIS-CLINIC-ISOLATION-ARCHITECTURE-AND-SCHEMA-VERIFICATION-12C

## 1. Resumen ejecutivo

**STATUS: PARTIAL-R2 — evidencia local de MySQL reportada por el propietario incorporada; ejecución runtime desde Codex bloqueada.**

La decisión funcional queda registrada como aprobada: **Opción B — folios COFEPRIS independientes por clínica**. Esta fase no ejecutó migraciones, DDL, escrituras, backfill, HTTP clínico, subidas, cambios de surtido, descargas ni pruebas destructivas.

La configuración del proyecto apunta a `localhost:3306` y `bd_clinica`. El propietario confirmó mediante phpMyAdmin, en modo de solo lectura, MariaDB 10.4.32 y los conteos agregados incorporados en el addendum R2. Se intentó además una conexión PDO desde Codex con una guarda que exigía exactamente esa base y un host local; el entorno de Codex rechazó el acceso al socket antes de ejecutar `SELECT DATABASE()`, con:

```text
SQLSTATE[HY000] [2002] Intento de acceso a un socket no permitido por sus permisos de acceso
```

Por ello, el documento distingue la evidencia reportada por el propietario de la evidencia ejecutada por Codex:

- `DATABASE_ENVIRONMENT: OWNER_REPORTED_LOCAL_SQL_VERIFIED`.
- `SCHEMA_RUNTIME: OWNER_REPORTED_LOCAL_SQL_VERIFIED; CODEX_RUNTIME_NOT_VERIFIED`.
- `READ_ONLY_DATABASE_ACCESS: OWNER_REPORTED; CODEX_ATTEMPT_BLOCKED_BEFORE_QUERY`.
- Los conteos agregados reportados por el propietario están incorporados; no fueron reproducidos por Codex.
- La evidencia real confirma que `cofepris_folio` no tiene `id_clinica`.

La propuesta técnica provisional es **Opción técnica 1 condicionada**: agregar propiedad explícita de clínica al catálogo de rangos, con restricciones e integridad verificables y con el registro de uso/documento ligado de forma inequívoca. Si los datos reales muestran carpetas reutilizadas, rangos solapados o históricos ambiguos, la alternativa recomendada pasa a ser **Opción técnica 2/4**, con una asignación o entidad de folio durable.

## 2. Decisión funcional aprobada

| Contrato | Estado objetivo |
|---|---|
| Cada clínica administra exclusivamente sus folios | Aprobado por el propietario |
| Un Doctor sólo consulta folios de su clínica | Aprobado |
| Crear folios usa la clínica autenticada | Aprobado |
| Editar y surtir exige propiedad de clínica | Aprobado |
| Documentos y PDF respetan clínica y paciente | Aprobado |
| `id_clinica` no se acepta como autoridad desde frontend | Aprobado |
| Propiedad verificable en datos persistidos | Aprobado |

La decisión funcional **no autoriza todavía** modificaciones PHP, migraciones, nuevas columnas, índices, backfill, cambios de rutas ni actualización de datos.

## 3. Estado del repositorio

| Campo | Resultado |
|---|---|
| Rama | `Silvino` |
| HEAD | `59654e05dc9f931c21b5392238961bcddb3ab2e4` |
| Worktree | Preservado; contiene numerosos cambios locales previos |
| Documentos base | 12, 12A y 12B revisados |
| Código funcional modificado en 12C | No |
| Esquema modificado en 12C | No |
| Datos modificados en 12C | No |
| Documentos clínicos modificados | No |
| Commits/deploys | Ninguno |

No se usaron `git reset`, `git clean`, staging ni comandos destructivos.

## 4. Base de datos inspeccionada

### 4.1 Configuración efectiva sin secretos

La lectura de nombres de variables de `.env` y la configuración de `app/Config/Database.php` mostró:

```text
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=bd_clinica
```

El usuario y la contraseña no se imprimieron. La conexión no llegó a ejecutar una consulta porque el sandbox bloqueó el socket local.

### 4.2 Estado de verificación

```yaml
DATABASE_ENVIRONMENT: CONFIGURED_LOCAL_BD_CLINICA_RUNTIME_UNVERIFIED
DATABASE_NAME: bd_clinica
MYSQL_VERSION: MariaDB 10.4.32_OWNER_REPORTED
SCHEMA_RUNTIME: OWNER_REPORTED_LOCAL_SQL_VERIFIED; CODEX_RUNTIME_NOT_VERIFIED
READ_ONLY_DATABASE_ACCESS: OWNER_REPORTED; CODEX_ATTEMPT_BLOCKED_BEFORE_QUERY
DATABASE_BLOCKER: Codex sandbox no permite el acceso al socket MySQL local; evidencia local fue entregada por el propietario
PRODUCTION_ACCESS: NOT_DEMONSTRATED; no se salió del host local configurado
```

## 5. Evidencia de esquema estático

Fuente: `tests/fixtures/security-fixtures.sql`. Es una referencia sintética del repositorio, no una confirmación runtime.

### 5.1 `cofepris_folio`

```text
id            INT AUTO_INCREMENT PRIMARY KEY
fecha_hora    TIMESTAMP
folio_inicial INT
folio_final   INT
carpeta       VARCHAR(50)
total_folio   INT
estado        INT
```

Evidencia del fixture:

- `CLINIC_KEY`: no.
- `PATIENT_KEY`: no.
- `FOLIO_KEY`: rango inicial/final y carpeta.
- `PRIMARY_KEY`: `id`.
- `UNIQUE_INDEXES`: no observados en el fixture aparte de la PK.
- `FOREIGN_KEYS`: no observadas.
- Propiedad clínica persistida: no demostrada.

### 5.2 `cofepris`

```text
id                   INT AUTO_INCREMENT PRIMARY KEY
fecha_hora           TIMESTAMP
id_paciente          INT
carpeta              TEXT
folio                VARCHAR(20)
diagnostico          TEXT
medicamento          TEXT
num_cajas            TEXT
presentacion         VARCHAR(100)
dosificacion         TEXT
num_dias             VARCHAR(100)
via_administracion   TEXT
surtido              INT
farmacia             DOUBLE
paciente             DOUBLE
medico               DOUBLE
```

Evidencia del fixture:

- `CLINIC_KEY`: no directo.
- `PATIENT_KEY`: `id_paciente`.
- `FOLIO_KEY`: `carpeta` + `folio` como relación lógica.
- `PRIMARY_KEY`: `id`.
- `UNIQUE_INDEXES`: no observados en el fixture aparte de la PK.
- `FOREIGN_KEYS`: no se observa FK a `pc_paciente`.
- Clínica indirecta: `cofepris.id_paciente -> pc_paciente.id -> pc_paciente.id_clinica`, aplicada por PHP/consultas, no por FK visible.

### 5.3 Tablas relacionadas

| Tabla | Evidencia estática | Propósito |
|---|---|---|
| `clinicas` | `id` como PK | Tenant clínico |
| `pc_paciente` | `id`, `id_clinica`, índice/FK a `clinicas` en fixture | Propiedad clínica del paciente |
| `cofepris` | Registro clínico y uso de folio | Registro COFEPRIS por paciente |
| `cofepris_folio` | Catálogo de rangos por carpeta | Administración de rangos |
| `public/storage/cofepris` | Ruta construida por código | Archivos PDF generados |

No se encontró en el código inspeccionado una tabla documental separada que agregue una clave de clínica o una auditoría de descargas.

## 6. Modelo actual reconstruido

```text
clinicas.id
    ^
    |  FK visible en fixture
pc_paciente.id_clinica
    ^
    |  relación lógica por id_paciente
cofepris.id_paciente
    |
    +-- cofepris.carpeta + cofepris.folio
             ^
             | relación lógica, no FK
       cofepris_folio.carpeta + [folio_inicial, folio_final]

cofepris.carpeta + cofepris.folio
    -> public/storage/cofepris/{carpeta}/{folio}.pdf
```

### 6.1 Respuestas verificables sólo estáticamente

| Pregunta | Resultado 12C |
|---|---|
| `cofepris_folio` tiene `id_clinica` | No en fixture; runtime no verificado |
| Otra tabla determina explícitamente clínica del rango | No encontrada estáticamente |
| El registro COFEPRIS se asigna a paciente | Sí, `id_paciente` |
| El mismo folio puede aparecer en varias clínicas | No se puede determinar sin datos reales y regla de negocio |
| El surtido conserva clínica | No directamente; depende del registro/paciente y no se valida en `editarSurtido()` |
| Documento relacionado con folio/paciente | Ruta por carpeta/folio; autorización por paciente en `PdfController` |
| IDs técnicos globales | `cofepris.id` y `cofepris_folio.id` son PK locales de tabla; no prueban propiedad de tenant |
| Folio textual global o por clínica | No resuelto sin datos/decisión de carpeta y unicidad |

## 7. Folios históricos

### 7.1 Conteos

No se ejecutaron por el bloqueo de MySQL. No se imprimieron datos personales ni folios reales.

```yaml
HISTORICAL_FOLIOS_TOTAL: NOT_VERIFIED
HISTORICAL_FOLIOS_WITH_CLEAR_OWNER: NOT_VERIFIED
HISTORICAL_FOLIOS_WITHOUT_OWNER: NOT_VERIFIED
HISTORICAL_FOLIOS_AMBIGUOUS: NOT_VERIFIED
HISTORICAL_FOLIOS_WITH_USE: NOT_VERIFIED
HISTORICAL_FOLIOS_UNUSED: NOT_VERIFIED
HISTORICAL_DUPLICATE_LOGICAL_KEYS: NOT_VERIFIED
HISTORICAL_INCONSISTENT_REFERENCES: NOT_VERIFIED
DOCUMENTS_WITHOUT_CLEAR_ASSOCIATION: NOT_VERIFIED
HISTORICAL_DATA_STATUS: RUNTIME_READ_ONLY_PENDING
```

### 7.2 Consultas agregadas preparadas para ejecución local

Las siguientes consultas son de solo lectura y están condicionadas a confirmar primero `DATABASE() = bd_clinica`. No se ejecutaron desde Codex. Deben adaptarse si el `SHOW CREATE TABLE` runtime difiere del fixture.

```sql
SELECT DATABASE() AS selected_database;
SELECT VERSION() AS database_version;

SELECT COUNT(*) AS total_ranges
FROM cofepris_folio;

SELECT COUNT(*) AS ranges_with_usage
FROM cofepris_folio f
WHERE EXISTS (
    SELECT 1
    FROM cofepris c
    WHERE c.carpeta = f.carpeta
);

SELECT COUNT(*) AS ranges_without_usage
FROM cofepris_folio f
WHERE NOT EXISTS (
    SELECT 1
    FROM cofepris c
    WHERE c.carpeta = f.carpeta
);

SELECT COUNT(*) AS used_records
FROM cofepris;

SELECT COUNT(*) AS used_records_with_patient_clinic
FROM cofepris c
INNER JOIN pc_paciente p ON p.id = c.id_paciente
WHERE p.id_clinica IS NOT NULL;

SELECT COUNT(*) AS ambiguous_carpeta_groups
FROM (
    SELECT c.carpeta
    FROM cofepris c
    LEFT JOIN pc_paciente p ON p.id = c.id_paciente
    GROUP BY c.carpeta
    HAVING COUNT(DISTINCT p.id_clinica) > 1
        OR COUNT(DISTINCT CASE WHEN p.id_clinica IS NULL THEN c.id END) > 0
) q;

SELECT COUNT(*) AS duplicate_carpeta_folio_pairs
FROM (
    SELECT carpeta, folio
    FROM cofepris
    GROUP BY carpeta, folio
    HAVING COUNT(*) > 1
) q;

SELECT COUNT(*) AS records_outside_declared_ranges
FROM cofepris c
WHERE NOT EXISTS (
    SELECT 1
    FROM cofepris_folio f
    WHERE f.carpeta = c.carpeta
      AND CAST(c.folio AS UNSIGNED)
          BETWEEN f.folio_inicial AND f.folio_final
);
```

Estas consultas no pueden demostrar por sí solas la propiedad histórica cuando el catálogo no tiene clínica: sólo identifican evidencia disponible y ambigüedades. No se debe asignar automáticamente una clínica, borrar huérfanos ni tomar el primer paciente como propietario.

### 7.3 Clasificación futura

| Grupo | Criterio | Tratamiento futuro |
|---|---|---|
| H1 | Clínica demostrable por relación inequívoca | Migración documentada y validación humana/automática aprobada |
| H2 | Sin clínica determinable | Mantener aislado/no exponible hasta conciliación |
| H3 | Más de una clínica posible | Revisión humana; no reasignar automáticamente |
| H4 | Rango sin uso | Asignar sólo con evidencia administrativa, nunca por inferencia |
| H5 | Folio/rango/documento inconsistente | Preservar, marcar y corregir mediante procedimiento controlado |

## 8. Unicidad y concurrencia

### 8.1 Estado actual estático

- `cofepris_folio.id` es la única unicidad demostrada en el fixture.
- No se observa `UNIQUE(carpeta, folio)` en `cofepris`.
- El modelo calcula el siguiente folio con `MAX(CAST(folio AS UNSIGNED))` por carpeta.
- La asignación no muestra una transacción o bloqueo que evite dos solicitudes concurrentes.
- El rango activo se obtiene globalmente con `estado = 0`, ordenado por `id`.
- El cierre de rango usa una actualización construida con la carpeta.

### 8.2 Estrategia objetivo

La unicidad futura debe definirse después de confirmar si el valor comercial puede repetirse entre clínicas:

- Si los folios son independientes por clínica: candidato conceptual `UNIQUE(id_clinica, folio)` en la entidad que representa el uso, más unicidad de carpeta/rango por clínica.
- Si una carpeta es el rango durable: candidato conceptual `UNIQUE(id_clinica, carpeta)` y una regla que impida rangos solapados dentro de una clínica.
- Si el documento requiere identidad estable: usar un `folio_id` técnico persistente como referencia del registro y archivo, sin reutilizar únicamente el número textual.

No se implementaron índices ni constraints.

## 9. Inventario de las 12 rutas

| Ruta | Método | Controlador | Modelo/tabla | Operación | Fuente clínica | Propiedad actual | Riesgo | Remediación futura |
|---|---|---|---|---|---|---|---|---|
| `/cofepris` | GET | `HomeController::indexCofepris` | `cofepris_folio` | Listar configuración | JWT `id_clinica` | Sólo auth de clínica | P1/P2 rango global | Filtrar por propiedad persistida |
| `/cofepris/insert-folios` | POST | `CofeprisController::insertFolios` | `CofeprisModel::guardarFolio` | Crear rango/PDF fuente | JWT + CSRF 12A | Token ligado a clínica, rango no ligado | P1/P2 | Insertar dentro de tenant del servidor |
| `/cofepris/{idCofepris}` | GET | `CofeprisController::cofepris` | `cofepris` | Detalle | Paciente recuperado por registro | `assertPatientAccess` después del lookup | P2 orden | Resolver y autorizar consulta en una operación |
| `/cofepris/paciente/{idPaciente}` | GET | `ClinicaController::pacienteCofepris` | `cofepris` | Lista/alta | JWT + paciente | `assertPatientAccess` | P2 lectura antes de auth del paciente | Auth antes de cargar recursos |
| `/cofepris/paciente/{idPaciente}/editar/{idCofepris}` | GET | `ClinicaController::pacienteCofepris` | `cofepris` | Formulario edición | `idPaciente` de URL | No prueba relación registro-paciente | P1 | Consultar por ambos IDs y clínica |
| `/paciente/insert-cofepris` | POST | `ClinicaController::pacienteInsertCofepris` | `cofepris` | Alta/edición | `idPaciente` JSON + JWT | Alta/edición no comparten scope completo | P1 | Ignorar tenant del cliente; resolver registro en servidor |
| `/cofepris/carpeta/{idCarpeta}` | GET | `CofeprisController::carpetaCofepris` | `cofepris_folio`, `cofepris` | Ver rango | JWT solamente | Sin propiedad de carpeta | P1/P2 | Consultar `WHERE id_clinica = auth` |
| `/cofepris/edit-surtido` | POST | `CofeprisController::editSurtido` | `cofepris` | Surtir | JWT solamente | `UPDATE ... WHERE id` | P1 | Scope por clínica y registro |
| `/buscar/tabla-cofepris/{idPaciente}` | GET | `BusquedasController::tableCofepris` | `cofepris` | Tabla por paciente | `guardPatient` | Scope paciente estático | P2 SQL heredado | Parámetros tipados y join explícito |
| `/buscar/cofepris/{idCofepris}` | GET | `BusquedasController::buscarCofepris` | `cofepris` | Detalle AJAX | Paciente recuperado | Lookup antes de guard | P2 | Query autorizada por ID + paciente + clínica |
| `/buscar/tabla-cofepris-folios` | GET | `BusquedasController::buscarCofeprisFolios` | `cofepris_folio` | Tabla de rangos | `guardClinic` | Listado global | P1/P2 | Scope por clínica |
| `/pdf/cofepris/{id}` | GET | `PdfController::pdfCofepris` | `cofepris` + storage | PDF | JWT + paciente | Auth de paciente después de lookup; path por carpeta/folio | P1/P2 | Autorizar clínica, paciente y archivo antes de abrir |

No existe ruta DELETE identificada.

## 10. Contrato de autorización futuro

| Operación | Regla obligatoria |
|---|---|
| CREATE | La clínica se toma del JWT validado; el frontend no decide `id_clinica` |
| READ | Toda consulta administrativa incluye la clínica autenticada en la condición de propiedad |
| UPDATE | Registro, folio y paciente deben coincidir con la clínica de sesión; no basta un ID válido |
| DELETE | Si se incorpora, exige propiedad, dependencias y respuesta fail-closed |
| SURTIDO | Verifica propiedad del registro y rango antes de cambiar estado |
| DOCUMENTOS | La autorización del registro debe preceder a la resolución/lectura del archivo |
| PDF | Valida clínica, paciente, registro, folio y path permitido antes de `setSourceFile` |
| PACIENTE | Mantiene `assertPatientAccess` además de la propiedad administrativa |
| CSRF | Conserva el token de clínica 12A y lo valida antes de procesar el request |

El contrato no debe depender de ocultar botones, filtros JavaScript, `id_clinica` del formulario ni conocimiento de un ID.

## 11. Trazabilidad documental y PDF

### 11.1 Estado actual

```text
cofepris_folio.carpeta / folio_inicial / folio_final
          |
          | relación lógica por carpeta y número
          v
cofepris.id_paciente / carpeta / folio / surtido
          |
          v
pc_paciente.id_clinica

cofepris.carpeta + cofepris.folio
          |
          v
public/storage/cofepris/{carpeta}/{folio}.pdf
```

El `PdfController` autentica y llama `assertPatientAccess()` antes de importar el PDF, pero el filesystem no tiene en la ruta una clave explícita de clínica. La protección contra una solicitud directa al path público no fue verificada.

### 11.2 Requisitos futuros

- Una identidad documental persistente y única.
- Propiedad de clínica verificable.
- Relación con registro COFEPRIS y paciente.
- Ruta física no enumerable o protección de servidor equivalente.
- Validación de path dentro de un directorio permitido.
- Control de descarga por clínica y paciente.
- Registro de operación de descarga si el cumplimiento lo exige.
- No usar el número textual de folio como única llave técnica.

No se descargaron ni modificaron PDFs.

## 12. Alternativas técnicas de esquema

| Alternativa | Compatibilidad con Opción B | Integridad | Históricos | Complejidad | Evaluación |
|---|---|---|---|---|---|
| 1. `id_clinica` directo en `cofepris_folio` | Alta | FK a `clinicas`, unicidad por clínica/carpeta y reglas de rango | Requiere clasificar carpetas históricas | Baja-media | Preferida si no hay solapamientos/ambigüedades |
| 2. Tabla de relación clínica-rango | Alta | Permite asignación explícita e historial | Mejor para casos ambiguos, más joins | Media | Preferida si una carpeta puede cambiar/asociarse a más de una entidad |
| 3. Relación existente por paciente | Parcial | Protege registros clínicos, no catálogo global | No resuelve folios sin uso | Baja | Insuficiente por sí sola |
| 4. Reestructurar catálogo y asignaciones | Alta | Entidad durable de folio/documento y ownership directo | Mejor trazabilidad, migración mayor | Alta | Mejor cuando el esquema real tiene solapamientos o documentos huérfanos |

### Recomendación

**RECOMMENDED_SCHEMA_OPTION: OPTION_1_CONDITIONAL_WITH_RECORD_SCOPE**.

Agregar propiedad clínica al catálogo de rangos es el cambio mínimo compatible con la decisión aprobada, siempre que el análisis runtime confirme:

1. que una `carpeta` no representa varias clínicas;
2. que no existen rangos solapados incompatibles;
3. que la relación entre el registro de uso y el rango puede resolverse inequívocamente;
4. que los documentos históricos pueden conciliarse sin asignación automática.

Si cualquiera de esas condiciones falla, la recomendación cambia a **Opción 2 o 4**, con una entidad/asignación de folio durable y una referencia técnica estable en `cofepris` y documentos.

## 13. Propuesta estructural futura — no autorizada para ejecución

La implementación posterior deberá evaluar, como mínimo:

1. Propiedad de clínica en `cofepris_folio` o en una tabla de asignación.
2. FK a `clinicas` y reglas de nulabilidad compatibles con históricos ambiguos.
3. Scope persistido en `cofepris` o consulta inequívoca al rango propietario.
4. Unicidad de `(id_clinica, carpeta)` y/o `(id_clinica, folio)` según el modelo confirmado.
5. Prevención de rangos solapados dentro de la misma clínica.
6. Operación atómica para reservar el siguiente folio.
7. Identidad documental estable y almacenamiento privado.
8. Consultas y endpoints con ownership server-side.

No se creó archivo ejecutable de migración. Cualquier SQL futuro deberá marcarse **BORRADOR — NO AUTORIZADO PARA EJECUCIÓN**.

## 14. Estrategia de registros históricos

### H1 — propietario inequívoco

- Evidencia: relación verificable con clínica, paciente, asignación administrativa y rango.
- Tratamiento: asignación controlada y validación de unicidad.
- Revisión humana: según política documental.

### H2 — propietario no determinable

- Evidencia insuficiente.
- Mantener registros y documentos preservados, pero no exponerlos automáticamente a una clínica.
- Requiere conciliación humana o fuente administrativa autorizada.

### H3 — múltiples clínicas posibles

- No elegir la primera coincidencia.
- No reasignar por paciente histórico sin evidencia.
- Marcar como ambiguo y mantener fuera de operaciones tenant-scoped hasta resolución.

### H4 — folios sin uso

- Asignar sólo desde la política administrativa aprobada.
- No inferir propiedad por el último paciente o carpeta consultada.

### H5 — referencias/documentos inconsistentes

- Preservar evidencia.
- Separar la conciliación de la migración técnica.
- No borrar archivos ni registros huérfanos.

Una futura columna `NOT NULL` sólo podrá aplicarse después de resolver H2/H3/H5 o de disponer de un estado explícito de propietario desconocido.

## 15. Plan futuro de migración

1. **Respaldo verificado:** copia y prueba de restauración antes de cualquier DDL.
2. **Inventario:** metadatos, índices, rangos, registros, documentos y consumidores.
3. **Relación controlada:** introducir la clave/asignación de clínica en una fase compatible con lectura histórica.
4. **Conciliación:** clasificar H1-H5 con evidencia y revisión.
5. **Aplicación:** adaptar consultas, autorización y generación de folios.
6. **Integridad:** validar FK, unicidad, rangos y ausencia de cruces.
7. **Pruebas:** dos clínicas sintéticas, sin datos reales.
8. **Documentos/PDF:** validar correspondencia, paths y rechazo cross-clinic.
9. **Regresión:** resumen clínico, expediente, COFEPRIS, PDF y navegación.
10. **Cierre:** evidencia, aprobación y plan de rollback firmado.

## 16. Plan futuro de rollback

- Detener escrituras nuevas en una ventana controlada.
- Conservar el esquema anterior y snapshot verificado.
- Revertir código sólo después de confirmar compatibilidad con datos.
- Restaurar asignaciones nuevas desde un registro de migración auditable.
- No borrar datos históricos durante rollback.
- Revalidar lectura por clínica y documentos antes de reabrir operaciones.

No se ejecutó ninguna etapa.

## 17. Plan futuro de pruebas entre clínicas

Con dos clínicas sintéticas A/B:

| ID | Caso |
|---|---|
| TEST-01 | Doctor A lista sólo folios A |
| TEST-02 | Doctor B lista sólo folios B |
| TEST-03 | A no lee folio B por ID |
| TEST-04 | A no edita folio B |
| TEST-05 | A no surte registro B |
| TEST-06 | A no descarga documento B |
| TEST-07 | B no edita folio A |
| TEST-08 | Crear usa clínica autenticada, no payload |
| TEST-09 | `id_clinica` manipulado no cambia propiedad |
| TEST-10 | Sin sesión no se accede a documentos |
| TEST-11 | PDF exige clínica y paciente |
| TEST-12 | Modal de Fase 12 conserva funcionamiento |
| TEST-13 | CSRF de 12A continúa funcionando |
| TEST-14 | Históricos conciliados son visibles sólo en su clínica |
| TEST-15 | Históricos ambiguos no se exponen automáticamente |
| TEST-16 | No hay efectos cruzados en Doctor/Paciente |

En la fase posterior se deben ejecutar también pruebas de no-op SQL, concurrencia de folios, path traversal, URL directa de storage y contenido clínico no autorizado. No se ejecutaron POST, UPDATE, DELETE ni fixtures en 12C.

## 18. Dependencias y regresiones

### `ClinicalSummarySecurity.php`

La modificación de 12A agregó de forma aditiva `clinicActionCsrfToken()` y `validClinicActionCsrf()`. Los métodos existentes de resumen, captura e interview permanecen en el código inspeccionado. Resultado: `PASS_STATIC`; runtime no verificado.

### Consumidores a revisar en implementación

- `CofeprisController.php` — creación de rangos, carpeta y surtido.
- `ClinicaController.php` — lista, alta y edición por paciente.
- `CofeprisModel.php` — generación, consultas, edición, surtido y HTML.
- `BusquedasController.php` — tabla por paciente, detalle y tabla de folios.
- `PdfController.php` — autorización y resolución física del PDF.
- `HomeController.php` — token 12A y configuración global.
- `ClinicalSupplementService.php` / `ClinicalNarrativeService.php` — lecturas por paciente.
- `ClinicaModel.php` / `ClinicaIndicadoresService.php` — métricas con join a paciente/clínica.
- Vistas COFEPRIS, expediente, resumen e indicadores.

No se modificaron esos consumidores en 12C.

## 19. Hallazgos de seguridad

### P0

**Ninguno confirmado.** No hubo HTTP ni evidencia de exposición efectiva.

### P1

| ID | Ubicación | Condición | Evidencia | Propuesta | Autorización |
|---|---|---|---|---|---|
| P1-12C-01 | `editPacienteCofepris()` | UPDATE por `id` sin scope de paciente/clínica | Análisis estático | Query con ownership server-side | Fase de remediación separada |
| P1-12C-02 | `editarSurtido()` | UPDATE por `id` sólo con auth de clínica | Análisis estático | Resolver registro y clínica antes de actualizar | Fase de remediación separada |
| P1-12C-03 | Folios/PDF | Propiedad de rango/documento no persistida explícitamente | Fixture + código | Diseñar ownership y storage privado | Migración autorizada |

### P2

- SQL concatenado en consultas heredadas de tabla/folio.
- Resolución de recurso antes de completar autorización contextual en algunos endpoints.
- Rango global y generación potencialmente vulnerable a carrera.
- `public/storage/cofepris` sin verificación de acceso directo.
- HTML de detalle con concatenación de campos clínicos sin escape visible.
- Ausencia de constraints runtime confirmadas.

### P3

- Contrato de propiedad histórica no formalizado antes de 12C.
- Falta de auditoría explícita de descarga/uso de documento.
- Sin conteos históricos de conciliación por bloqueo del entorno.

Los hallazgos 12B permanecen abiertos y no se transforman en vulnerabilidades HTTP confirmadas.

## 20. Validaciones de 12C

| Validación | Resultado |
|---|---|
| Rama/HEAD/worktree | PASS_STATIC |
| Configuración efectiva sin secretos | PASS_STATIC |
| Guarda de entorno local `bd_clinica` | PASS_STATIC; configurada, runtime no confirmado |
| Conexión read-only MySQL | BLOCKED antes de consulta por socket sandbox |
| `SELECT DATABASE()` | NOT_EXECUTED |
| `SHOW CREATE TABLE` runtime | NOT_EXECUTED |
| Índices/FK runtime | NOT_EXECUTED |
| Conteos históricos | NOT_EXECUTED |
| Inventario de código/rutas | PASS_STATIC |
| PHP lint | PASS; controladores/modelos/middleware/servicio inspeccionados |
| Análisis de dependencias | PASS_STATIC |
| `git diff --check` | PASS con advertencias históricas de finales de línea |
| Cambios funcionales | Ninguno |

## 21. Informe obligatorio

```yaml
TH_DOCTOR_COFEPRIS_CLINIC_ISOLATION_ARCHITECTURE_AND_SCHEMA_VERIFICATION_12C:
  STATUS: PARTIAL_R2_OWNER_SQL_EVIDENCE_INCORPORATED_CODEX_RUNTIME_BLOCKED
  BRANCH: Silvino
  HEAD: 59654e05dc9f931c21b5392238961bcddb3ab2e4
  WORKTREE_PRESERVED: YES

  FUNCTIONAL_MODEL: CLINIC_INDEPENDENT
  OWNER_DECISION: APPROVED

  DATABASE_ENVIRONMENT: CONFIGURED_LOCAL_BD_CLINICA_RUNTIME_UNVERIFIED
  DATABASE_NAME: bd_clinica_OWNER_REPORTED
  MARIADB_VERSION: 10.4.32_OWNER_REPORTED
  SCHEMA_RUNTIME: OWNER_REPORTED_LOCAL_SQL_VERIFIED_CODEX_RUNTIME_NOT_VERIFIED
  READ_ONLY_DATABASE_ACCESS: OWNER_REPORTED_CODEX_ATTEMPT_BLOCKED_BEFORE_QUERY

  COFEPRIS_FOLIO_TABLE_VERIFIED: OWNER_REPORTED_LOCAL_SQL_VERIFIED
  COFEPRIS_FOLIO_HAS_CLINIC_ID: NO
  COFEPRIS_FOLIO_PRIMARY_KEY: id_STATIC_FIXTURE
  COFEPRIS_FOLIO_UNIQUE_INDEXES: NONE_OBSERVED_APART_FROM_PRIMARY_KEY_STATIC_FIXTURE
  COFEPRIS_FOLIO_FOREIGN_KEYS: NONE_OBSERVED_STATIC_FIXTURE

  RELATED_TABLES: clinicas, pc_paciente, cofepris, cofepris_folio, public/storage/cofepris
  CLINIC_RELATIONS: pc_paciente.id_clinica_STATIC; folio_relation_NOT_VERIFIED
  PATIENT_RELATIONS: cofepris.id_paciente_STATIC_LOGICAL
  DOCUMENT_RELATIONS: carpeta+folio_path_LOGICAL; NOT_RUNTIME_VERIFIED

  CLINICS_TOTAL: 1
  CLINIC_1_EXISTS: YES
  COFEPRIS_TOTAL: 345
  VALID_PATIENT_RELATIONS: 344
  ORPHAN_RECORDS: 1
  HISTORICAL_FOLIOS_TOTAL: 6_RANGES
  DECLARED_FOLIOS: 1200
  RANGES_WITH_ESTADO_1: 5
  RANGES_WITH_ESTADO_0: 1
  HISTORICAL_FOLIOS_WITH_CLEAR_OWNER: 0_EXPLICIT_OWNER_DEMONSTRATED
  HISTORICAL_FOLIOS_WITHOUT_EXPLICIT_OWNER: 6
  HISTORICAL_FOLIOS_AMBIGUOUS: NOT_VERIFIED
  HISTORICAL_DATA_STATUS: OWNER_REPORTED_AGGREGATES_NO_AUTOMATIC_ASSIGNMENT

  ROUTES_REVIEWED: 12
  CREATE_TENANT_CHECK: STATIC_POTENTIAL_RISK
  READ_TENANT_CHECK: STATIC_PARTIAL
  UPDATE_TENANT_CHECK: STATIC_INSUFFICIENT
  SURTIDO_TENANT_CHECK: STATIC_INSUFFICIENT
  PDF_TENANT_CHECK: STATIC_PARTIAL

  CURRENT_OWNERSHIP_MODEL: MIXED_OR_UNRESOLVED
  TARGET_OWNERSHIP_MODEL: CLINIC_INDEPENDENT

  SCHEMA_ALTERNATIVES_EVALUATED: 4
  RECOMMENDED_SCHEMA_OPTION: OPTION_1_CONDITIONAL_WITH_RECORD_SCOPE_FALLBACK_OPTION_2_OR_4
  RECOMMENDATION_JUSTIFICATION: Explicit clinic ownership with minimum change, conditional on range overlap and historical attribution analysis

  SCHEMA_CHANGE_REQUIRED: YES_FOR_COMPLETE_OPTION_B
  SCHEMA_CHANGE_SCOPE: Folio ownership, record scope, uniqueness, FK, document identity/storage
  HISTORICAL_BACKFILL_REQUIRED: YES_OR_EXPLICIT_UNKNOWN_STATE
  HISTORICAL_BACKFILL_RISKS: Ambiguity, shared folders, orphan documents, incorrect reassignment
  UNIQUENESS_STRATEGY: Evaluate composite clinic scope and non-overlapping ranges
  FOREIGN_KEY_STRATEGY: FK to clinicas and durable record/document references after historical plan

  FUTURE_MIGRATION_PLAN: 10_STAGE_DESIGN_DOCUMENTED
  FUTURE_ROLLBACK_PLAN: SNAPSHOT_PLUS_ASSIGNMENT_REVERSAL_DESIGN_DOCUMENTED
  FUTURE_TEST_PLAN: 16_CROSS_CLINIC_CASES_DESIGNED

  P0_CONFIRMED: NO
  P1_RISKS: P1-12C-01, P1-12C-02, P1-12C-03
  P2_RISKS: SQL_CONCATENATION, AUTH_ORDER, GLOBAL_RANGE, PDF_STORAGE, HTML_OUTPUT, RUNTIME_CONSTRAINTS
  P3_RISKS: HISTORICAL_POLICY, DOWNLOAD_AUDIT, RUNTIME_COUNTS

  CLINICAL_SUMMARY_SECURITY_DEPENDENCIES: PASS_STATIC_RUNTIME_NOT_VERIFIED
  COFEPRIS_12A_SECURITY_DEPENDENCIES: PASS_STATIC_RUNTIME_NOT_VERIFIED

  PHP_LINT: PASS
  STATIC_ANALYSIS: PASS
  SCHEMA_METADATA_ANALYSIS: OWNER_REPORTED_LOCAL_SQL_VERIFIED_PLUS_STATIC
  READ_ONLY_AGGREGATES: OWNER_REPORTED_NOT_EXECUTED_BY_CODEX
  DIFF_CHECK: PASS_WITH_HISTORICAL_EOL_WARNINGS

  CODE_MODIFIED: NO
  DATABASE_MODIFIED: NO
  SCHEMA_MODIFIED: NO
  MIGRATIONS_EXECUTED: NO
  DATA_BACKFILLED: NO
  CLINICAL_DOCUMENTS_MODIFIED: NO

  COMMITS: NONE
  DEPLOYMENTS: NONE
  DOCUMENT_CREATED: docs/audits/TH_DOCTOR_COFEPRIS_CLINIC_ISOLATION_ARCHITECTURE_AND_SCHEMA_VERIFICATION_12C.md
  OWNER_DECISIONS_PENDING: Runtime schema confirmation; historical ownership policy; storage policy
  BLOCKERS: CODEX_MYSQL_SOCKET; RANGE_OVERLAP_ANALYSIS; HISTORICAL_OWNER_EVIDENCE; AUTHORIZED_HTTP_TESTS
  RESIDUAL_RISKS: EDIT_BY_ID; SURTIDO_BY_ID; GLOBAL_FOLIOS; PDF_PATH; DIRECT_STORAGE; LEGACY_SQL; HTML_OUTPUT
  READY_FOR_IMPLEMENTATION_AUTHORIZATION: NO
```

## 22. Condiciones para autorizar la implementación posterior

La fase de implementación sólo debe comenzar cuando:

1. El propietario ejecute las consultas de metadatos en el entorno autorizado y confirme el esquema real.
2. Se obtengan los conteos agregados de H1-H5 sin exportar datos personales.
3. Se confirme si existen carpetas o rangos compartidos/solapados.
4. Se defina el tratamiento de históricos ambiguos.
5. Se apruebe el alcance exacto de la migración y su rollback.
6. Se prepare un entorno sintético de dos clínicas.
7. Se autorice por separado el cambio de código funcional y el DDL.

**PRODUCTION_READY: NO**

**COMMITS: NONE**
**DEPLOYMENTS: NONE**

## 23. Addendum R2 — evidencia local reportada por el propietario

Este addendum incorpora evidencia obtenida directamente por el propietario mediante phpMyAdmin sobre `bd_clinica`. Se identifica expresamente como:

```yaml
OWNER_REPORTED_LOCAL_SQL_VERIFIED: YES
CODEX_SQL_EXECUTION: NO
CODEX_DATABASE_BLOCKER: SQLSTATE_HY000_2002_SOCKET_PERMISSION
```

La evidencia no debe presentarse como una consulta ejecutada por Codex ni como una prueba multiclínica. Sólo existe una clínica en la base reportada, por lo que `folios_compartidos_entre_clinicas = 0` no demuestra aislamiento entre tenants.

### 23.1 Conteos agregados incorporados

| Métrica | Resultado reportado | Interpretación |
|---|---:|---|
| Clínicas | 1 | Existe la clínica `id=1`; no prueba escenario multiclínica |
| Registros `cofepris` | 345 | Conteo total |
| Registros con paciente válido | 344 | Relación válida observada |
| Registros con paciente inexistente | 1 | Registro huérfano; no borrar ni reasignar |
| Registros asociados a clínica 1 | 344 | Derivación por paciente válido |
| Pacientes distintos en clínica 1 | 90 | Conteo agregado |
| Valores de folio distintos con paciente válido | 342 | No equivale a unicidad de `(carpeta, folio)` |
| Folios compartidos entre clínicas | 0 reportado | No concluyente con una sola clínica |
| Rangos `cofepris_folio` | 6 | Catálogo real reportado |
| Folios declarados | 1,200 | Suma de rangos reportada |
| Rangos `estado=1` | 5 | Estado semántico debe conservarse hasta confirmación funcional |
| Rangos `estado=0` | 1 | Rango activo reportado |

### 23.2 Rangos históricos reportados

| ID de rango | Capacidad | Estado | Evidencia de clínica |
|---:|---:|---:|---|
| 2 | 200 | 1 | No explícita en catálogo |
| 3 | 200 | 1 | No explícita en catálogo |
| 4 | 200 | 1 | No explícita en catálogo |
| 5 | 200 | 1 | No explícita en catálogo |
| 8 | 200 | 1 | No explícita en catálogo |
| 9 | 200 | 0 | No explícita en catálogo |

No se asignan los seis rangos a la clínica 1 por inferencia. La existencia de una sola clínica actual y de 344 registros vinculados a ella no prueba que todos los rangos históricos le pertenezcan.

### 23.3 Estado de propiedad histórica

```yaml
HISTORICAL_FOLIOS_TOTAL: 6_RANGES
HISTORICAL_DECLARED_CAPACITY: 1200
HISTORICAL_FOLIOS_WITH_CLEAR_EXPLICIT_OWNER: 0_DEMONSTRATED
HISTORICAL_FOLIOS_WITHOUT_EXPLICIT_OWNER: 6
HISTORICAL_FOLIOS_AMBIGUOUS: NOT_VERIFIED
ORPHAN_COFEPRIS_RECORDS: 1
AUTOMATIC_ASSIGNMENT_TO_CLINIC_1: FORBIDDEN
```

El único registro huérfano debe permanecer preservado y fail-closed para lecturas/acciones que requieran paciente o clínica. No se debe ocultar, borrar ni utilizar como evidencia de propiedad.

### 23.4 Consultas adicionales pendientes de solo lectura

Estas consultas conservan los valores como texto para no perder ceros iniciales. Deben adaptarse al `SHOW CREATE TABLE` confirmado por el propietario si algún nombre difiere. No se ejecutaron en Codex.

```sql
-- Rangos y carpetas repetidas sin convertir carpeta a número.
SELECT carpeta, COUNT(*) AS cantidad_rangos,
       MIN(folio_inicial) AS minimo_reportado,
       MAX(folio_final) AS maximo_reportado
FROM cofepris_folio
GROUP BY carpeta
HAVING COUNT(*) > 1;

-- Solapamiento numérico sólo si el contrato confirma que folio_inicial/final son rangos numéricos.
SELECT a.id AS rango_a, b.id AS rango_b, a.carpeta AS carpeta_a, b.carpeta AS carpeta_b
FROM cofepris_folio a
INNER JOIN cofepris_folio b ON a.id < b.id
WHERE CAST(a.folio_inicial AS UNSIGNED) <= CAST(b.folio_final AS UNSIGNED)
  AND CAST(b.folio_inicial AS UNSIGNED) <= CAST(a.folio_final AS UNSIGNED);

-- Ceros iniciales y formatos no numéricos: sólo conteos, no valores clínicos.
SELECT
  SUM(CASE WHEN folio REGEXP '^0[0-9]+$' THEN 1 ELSE 0 END) AS folios_con_cero_inicial,
  SUM(CASE WHEN folio REGEXP '^[0-9]+$' THEN 0 ELSE 1 END) AS folios_no_numericos,
  COUNT(*) AS registros_total
FROM cofepris;

-- Folios fuera de todos los rangos declarados.
SELECT COUNT(*) AS registros_fuera_de_rango
FROM cofepris c
WHERE NOT EXISTS (
    SELECT 1 FROM cofepris_folio f
    WHERE f.carpeta = c.carpeta
      AND CAST(c.folio AS UNSIGNED)
          BETWEEN f.folio_inicial AND f.folio_final
);

-- Referencias de folio sin paciente válido, sólo conteo.
SELECT COUNT(*) AS registros_huerfanos
FROM cofepris c
LEFT JOIN pc_paciente p ON p.id = c.id_paciente
WHERE p.id IS NULL;
```

La consulta de solapamientos debe interpretarse con cuidado: no sustituye una decisión sobre el significado de `carpeta`, `folio_inicial` y `folio_final`, y no debe convertir `cofepris.folio` a entero para persistencia ni modificar datos.

## 24. Cierre R2 y transición a 12D

La fase 12C queda consolidada como **parcial respaldada por evidencia local del propietario**. Se cierra la incertidumbre sobre los conteos reportados y sobre la ausencia de `id_clinica` en `cofepris_folio`, pero permanecen abiertas:

- solapamiento real de rangos;
- propiedad histórica de los seis rangos;
- tratamiento del registro huérfano;
- unicidad lógica efectiva de carpeta/folio;
- autorización HTTP con dos clínicas;
- protección de archivos por acceso directo;
- implementación de cambios de esquema.

La propuesta técnica de Fase 12D se entrega separadamente en:

`docs/audits/TH_DOCTOR_COFEPRIS_PHASE_12D_IMPLEMENTATION_PROPOSAL.md`

No se autoriza aún PHP funcional, DDL, backfill, pruebas de escritura ni despliegue.
