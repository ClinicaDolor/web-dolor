# M07 — Propuesta de extensión canónica compartida

Proyecto: `C:\xampp\htdocs\clinica-dolor`  
Fecha: 2026-10-14  
Estado anterior: `M07_HTTP_INTEGRATION_CLOSED_VERIFIED`  
Alcance: diseño técnico para Entrevista Conversacional y P09 tradicional.  

> Este documento es una propuesta. No modifica código, formularios, esquema,
> datos, `.env` ni configuraciones. No se ejecutaron migraciones ni escrituras.

## 1. Decisión arquitectónica propuesta

M07 debe tener una única representación canónica por entrada clínica. La
Entrevista Conversacional y la Historia Clínica Tradicional deben consumir el
mismo servicio de dominio y el mismo modelo de persistencia. Una vista, un
controlador o el navegador no deben interpretar por su cuenta los estados
clínicos.

La compatibilidad se resolverá de forma aditiva:

1. Las 46 entradas oficiales conservan `pac_preguntas_modulo_7` y sus IDs.
2. `pac_respuestas_paciente_modulo_7` sigue siendo la fila canónica de una
   entrada catalogada, ampliada con estados explícitos, versión y los dos ejes
   nuevos del resultado.
3. Una nueva colección canónica contiene cada medicamento no catalogado como
   una entidad independiente; no se reutilizan M06 ni `descripcion`.
4. Un servicio compartido devuelve un DTO M07 uniforme para ambos recorridos.
5. Los valores históricos T1–T5 se conservan y se proyectan a los campos nuevos
   sin inferir hechos que la fuente no documenta.

No se propone crear una segunda copia clínica para la entrevista. En una
transición, las columnas históricas son una proyección de compatibilidad de la
misma operación transaccional; el servicio canónico es la única puerta de
escritura.

## 2. Evidencia del contrato actual

Las fuentes revisadas fueron:

- `docs/PATIENT_INTERVIEW_M07_CONVERSATIONAL_SCOPE_PROPOSAL.md`;
- `docs/PATIENT_INTERVIEW_M07_CLINICAL_RULES_DECISION.md`;
- `docs/PATIENT_INTERVIEW_M07_IMPLEMENTATION_03.md`;
- `app/Services/ConversationalM07Adapter.php`;
- `app/Models/MedicacionDolorModel.php`;
- `app/Services/ClinicalDetailService.php`;
- controlador, vista y JavaScript de P09 tradicional;
- `tests/p09/contract-check.mjs`, `tests/p09/http-crud.php` y
  `tests/p09/README.md`;
- `tests/fixtures/security-fixtures.sql` y los SQL sanitizados de P09.

El esquema observado contiene:

- `pac_temas_modulo_7(id, tema)`: seis categorías;
- `pac_preguntas_modulo_7(id, id_tema, pregunta)`: 46 IDs históricos;
- `pac_respuestas_paciente_modulo_7(id, id_pregunta, id_paciente,
  respuesta, dosis, resultados, consumo, descripcion)`;
- `pc_paciente(id, id_clinica, ...)` y `clinicas(id, ...)`.

No se localizó una carpeta formal de migraciones en el proyecto; el esquema de
referencia se expresa en `tests/fixtures/security-fixtures.sql` y en los
preparadores P09. La propuesta de abajo requiere una migración nueva, revisada
y ejecutada sólo después de aprobación.

La implementación actual ya protege lectura sin inicializar filas desde
`ConversationalM07Adapter`, valida dependencias, versión, duplicados y
atomicidad, y deja `M07_FINALIZATION_ENABLED: NO`. Esa protección se conserva.

## UNKNOWN_DATA_MODEL

### 2.1 Estados canónicos y procedencia

El estado persistido se separa del valor y de la procedencia. El conjunto
mínimo persistido es:

| Código | Significado | ¿Persistido? | ¿Terminal para captura? |
|---|---|---:|---:|
| `NOT_DOCUMENTED_HISTORICAL` | No existe un dato clínico documentado en el registro histórico o sólo existe una fila inicializada vacía | Sí | No |
| `DOES_NOT_REMEMBER` | El paciente declaró expresamente que no recuerda | Sí | Sólo si la política clínica lo aprueba |
| `NOT_APPLICABLE` | Una dependencia confirmada hace que el campo no aplique | Sí | Sí |
| `PENDING_REVIEW` | Ambigüedad, contradicción o combinación que requiere revisión | Sí | No |
| `CONFIRMED` | Valor o negativa explícita confirmada y persistida por el contrato | Sí | Sí |

`UNANSWERED` es un estado de captura del DTO: la pregunta actual aún no ha
recibido respuesta. Puede persistirse sólo si se aprueba un mecanismo de
borradores; no debe ser el valor por defecto aplicado a todas las filas
históricas. `KNOWN` es exclusivamente un estado transitorio de interfaz para
una propuesta validada pero todavía no confirmada; no es una afirmación clínica
persistida. Por tanto, una propuesta no confirmada no puede modificar el
registro canónico.

`DOES_NOT_REMEMBER` sólo se asigna cuando la entrada de captura contiene
evidencia explícita de esa declaración. Nunca se asigna a una ausencia histórica,
una fila vacía creada por el formulario tradicional, una dosis cero, una
respuesta negativa ni un campo no consultado.

La interfaz muestra, sin reinterpretar:

- `NOT_DOCUMENTED_HISTORICAL`: “No documentado históricamente”;
- `UNANSWERED`: “Sin responder”;
- `NOT_APPLICABLE`: “No aplica”;
- `DOES_NOT_REMEMBER`: “El paciente declaró que no lo recuerda”;
- `PENDING_REVIEW`: “Pendiente de revisión clínica”;
- `CONFIRMED`: “Respuesta confirmada”.

La procedencia persistida es `LEGACY`, `TRADITIONAL` o `INTERVIEW`. Una
migración debe asignar `LEGACY` sin inferir una declaración del paciente; cada
nueva operación registra su origen real. Un campo `NULL` se interpreta sólo
junto con su estado y no puede sustituirlo.

### 2.2 Aplicación a T1–T5

Para una entrada catalogada, se proponen estados independientes:

- `respuesta_state`: T1 (`Si`/`No` o estado desconocido);
- `dosis_state`: T2;
- `resultado_state`: compatibilidad de T3;
- `consumo_state`: T4;
- `descripcion_state`: T5.

Después de una negativa T1 confirmada, T2–T5 son `NOT_APPLICABLE`; no se
representan como respuestas negativas y no se borran silenciosamente si ya
había datos. El cambio que limpiaría dependientes requiere confirmación
explícita y una operación transaccional.

La frase “no recuerdo” se conserva como estado declarado, no como dosis,
resultado, consumo, cero o negativa. Una contradicción se mantiene como
`PENDING_REVIEW` y no reemplaza el dato confirmado anterior.

### 2.3 Lectura del Doctor

El Doctor recibirá, por cada entrada, valor, estado, procedencia (`LEGACY`,
`TRADITIONAL`, `INTERVIEW`), versión y fecha de actualización. La lectura debe
mostrar siempre la incertidumbre declarada y diferenciar:

- “No aplica” por dependencia;
- “No recuerda” declarado por el paciente;
- “Sin responder”;
- “Pendiente de revisión”;
- valor confirmado.

La vista no debe convertir estados a frases clínicas nuevas ni ocultar un
conflicto. `ClinicalDetailService` debe leer el DTO del servicio canónico,
manteniendo sus referencias de módulo, en lugar de ejecutar una segunda
interpretación de las columnas.

## OTHER_MEDICATION_COLLECTION

### 3.1 Tabla propuesta

Se propone una tabla nueva, nombre tentativo
`pac_m07_medicamentos_no_catalogados`, una fila por nombre de medicamento
adicional. No se crea en esta fase.

| Columna | Tipo propuesto | Regla |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | identificador estable propio |
| `id_paciente` | `INT NOT NULL` | FK a `pc_paciente(id)` |
| `id_clinica` | `INT NOT NULL` | FK a `clinicas(id)`; debe coincidir con el paciente |
| `ambito` | `VARCHAR(32) NOT NULL` | valor aprobado inicial `M07_P09` |
| `id_tema` | `INT NOT NULL` | FK a `pac_temas_modulo_7(id)` |
| `nombre` | `VARCHAR(255) NOT NULL` | nombre capturado, escapado al leer |
| `nombre_normalizado` | `VARCHAR(255) NOT NULL` | comparación determinista de espacios, mayúsculas y Unicode; no resuelve sinónimos |
| `entry_key` | `CHAR(36) NOT NULL` | identidad estable de este hecho, generada al crear la entrada |
| `estado_registro` | `VARCHAR(16) NOT NULL` | `ACTIVO` o `INACTIVO` |
| `respuesta`, `dosis`, `consumo` | tipos equivalentes a P09 | valores T1, T2 y T4 |
| `respuesta_state`, `dosis_state`, `consumo_state` | `VARCHAR(32) NOT NULL` | estados persistidos definidos en el contrato |
| `eficacia_codigo` | `VARCHAR(20) NULL` | `FUNCIONO` o `NO_FUNCIONO` |
| `eficacia_state` | `VARCHAR(24) NOT NULL` | estado canónico |
| `tolerabilidad_codigo` | `VARCHAR(24) NULL` | `PRESENTO_EFECTOS` o `NO_PRESENTO_EFECTOS` |
| `tolerabilidad_state` | `VARCHAR(24) NOT NULL` | estado canónico |
| `descripcion_efectos` | `TEXT NULL` | sólo cuando corresponde |
| `descripcion_efectos_state` | `VARCHAR(24) NOT NULL` | estado canónico |
| `version` | `BIGINT UNSIGNED NOT NULL DEFAULT 1` | control optimista |
| `created_at`, `updated_at` | `DATETIME NOT NULL` | auditoría técnica |
| `created_by`, `updated_by` | `BIGINT NULL` | actor autorizado, sin secretos |
| `provenance` | `VARCHAR(16) NOT NULL` | `LEGACY`, `TRADITIONAL` o `INTERVIEW` |

Los atributos deben usar el mismo significado que T1–T5, pero se modelan en
los ejes nuevos. No se usa `M06`, no se usa la columna `descripcion` histórica
de efectos adversos para el nombre y no se cambia ningún ID de los 46
medicamentos oficiales.

### 3.2 Operación de la colección

- Alta: sólo con paciente, clínica, tema P09 y nombre no vacío validados en
  servidor; el nombre debe estar confirmado antes de ser `ACTIVO`.
- Lectura: por paciente y clínica de sesión; devuelve `id`, nombre, categoría,
  estados y versión.
- Edición: por `id` y `expected_version`; una edición obsoleta devuelve 409.
- Desactivación: `estado_registro=INACTIVO`, nunca borrado físico desde la
  interfaz; requiere motivo y queda trazado.
- Reactivación: reutiliza la identidad normalizada existente, no crea un
  duplicado silencioso.
- Idempotencia: la misma clave de operación y el mismo hash devuelve el mismo
  resultado; la misma clave con payload distinto se rechaza.

### 3.3 Unicidad y ámbito

La unicidad propuesta anteriormente basada sólo en
`id_paciente,id_clinica,ambito,nombre_normalizado` se descarta: el nombre no
demuestra que dos registros sean el mismo hecho. Tampoco se debe resolver una
variante ortográfica, una marca/principio activo o un uso clínico repetido por
comparación automática.

La restricción final será:

`UNIQUE(id_paciente, id_clinica, ambito, entry_key)`.

`id_tema` no se agrega a esa clave porque la identidad la proporciona
`entry_key`, no la categoría. Se mantiene como FK e índice de consulta
`(id_paciente,id_clinica,ambito,id_tema,estado_registro)`. Así, el mismo
medicamento puede tener dos hechos legítimos en categorías distintas, o dos
entradas deliberadamente separadas en la misma categoría, sin fusionarlas.

`nombre_normalizado` se usa sólo para advertencia de posible duplicado y
reportes, no para bloquear el alta. Una variante o repetición requiere
confirmación explícita y conserva su propio `id`, `entry_key` y trazabilidad.
La aplicación debe comprobar que `pc_paciente.id_clinica` coincide con
`id_clinica` y que el actor tiene acceso al paciente. La FK a clínica no
sustituye la autorización de sesión.

## COMBINED_OUTCOME_MODEL

### 4.1 Campos independientes

En `pac_respuestas_paciente_modulo_7` se proponen columnas aditivas:

| Columna | Tipo | Valores |
|---|---|---|
| `m07_eficacia_codigo` | `VARCHAR(20) NULL` | `FUNCIONO`, `NO_FUNCIONO` |
| `m07_eficacia_state` | `VARCHAR(24) NOT NULL` | estado canónico |
| `m07_tolerabilidad_codigo` | `VARCHAR(24) NULL` | `PRESENTO_EFECTOS`, `NO_PRESENTO_EFECTOS` |
| `m07_tolerabilidad_state` | `VARCHAR(24) NOT NULL` | estado canónico |
| `m07_efectos_descripcion` | `TEXT NULL` | descripción independiente |
| `m07_efectos_descripcion_state` | `VARCHAR(24) NOT NULL` | estado canónico |

También se agregan los cinco estados de compatibilidad: `m07_respuesta_state`,
`m07_dosis_state`, `m07_resultados_state`, `m07_consumo_state` y
`m07_descripcion_state`, además de `m07_version`, `m07_updated_at` y
`m07_updated_by`.

La propuesta inicial no añade `DESCONOCIDO` a los códigos clínicos. El estado
`DOES_NOT_REMEMBER`/`PENDING_REVIEW` expresa esa situación sin inventar una
opción clínica. Si el responsable clínico aprueba un código explícito de
desconocido, debe agregarse a ambos ejes y a sus reglas de completitud antes de
programarlo.

### 4.2 Compatibilidad de T3

El valor histórico `resultados` continúa siendo una proyección, no la fuente
completa del nuevo resultado:

| T3 histórico | Eficacia nueva | Tolerabilidad nueva |
|---|---|---|
| `Funciono` | `FUNCIONO/CONFIRMED` | `NOT_DOCUMENTED_HISTORICAL` |
| `No funciono` | `NO_FUNCIONO/CONFIRMED` | `NOT_DOCUMENTED_HISTORICAL` |
| `Tuve efectos adversos` | `NOT_DOCUMENTED_HISTORICAL` | `PRESENTO_EFECTOS/CONFIRMED` |
| vacío o fila inicializada | `NOT_DOCUMENTED_HISTORICAL` | `NOT_DOCUMENTED_HISTORICAL` |

No se interpreta `Funciono` como ausencia de efectos adversos ni `Tuve efectos
adversos` como falta de eficacia. Para un resultado combinado confirmado, la
interfaz nueva muestra ambos ejes. La proyección para la vista antigua conserva
el indicador histórico más seguro, pero la vista debe advertir que el registro
contiene información adicional y no puede sobrescribirla con un T3 único.

### 4.3 Caso “funcionó y produjo efectos adversos”

La entrevista debe proponer dos hechos independientes y solicitar una
confirmación única que los persista atómicamente. Si sólo llega una dimensión,
la otra queda `PENDING_REVIEW` o desconocida según el contrato aprobado. No se
permite reducir la frase a `Funciono`, `No funciono` o `Tuve efectos adversos`.

## HISTORICAL_COMPATIBILITY

1. Se conservan los 46 IDs, textos, temas y filas existentes.
2. Ningún texto de `descripcion` se reinterpreta como nombre de medicamento.
3. Las filas históricas se migran con procedencia `LEGACY`; no se inventan
   confirmaciones que no estén respaldadas por una respuesta persistida.
4. Una fila histórica con T1=`No` recibe T2–T5=`NOT_APPLICABLE` sólo si están
   vacíos; si contienen datos incompatibles, se marca `PENDING_REVIEW` y no se
   limpia automáticamente.
5. Una fila histórica con T1=`Si` y dependientes incompletos permanece
   incompleta; no se marca completa por tener una fila.
6. Un histórico `Funciono` sólo migra eficacia positiva; la tolerabilidad queda
   desconocida. El resto sigue la tabla anterior.
7. Las filas duplicadas no se fusionan automáticamente. La migración se detiene
   antes de agregar una unicidad nueva y exige resolución focal autorizada.
8. El antiguo modelo puede seguir atendiendo llamadas compatibles durante la
   transición, pero las escrituras nuevas deben pasar por el servicio canónico.

## PROPOSED_TABLES

### A. Tabla existente: `pac_respuestas_paciente_modulo_7`

Se amplía sólo con columnas aditivas de estado, eficacia/tolerabilidad,
versión y trazabilidad técnica. La fila continúa identificando
`id_paciente + id_pregunta` y nunca se usa para representar un medicamento
adicional no catalogado.

### B. Nueva: `pac_m07_medicamentos_no_catalogados`

Colección descrita en `OTHER_MEDICATION_COLLECTION`. Su FK a paciente y clínica,
tema P09, nombre normalizado, estados y versión hacen que cada “Otro/Otros” sea
editable y trazable individualmente.

### C. Nueva: `pac_m07_operaciones`

Registro técnico append-only para idempotencia y trazabilidad de cambios; no se
crea una tabla de eventos separada:

| Columna | Tipo/regla |
|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` |
| `idempotency_key` | `CHAR(36) NOT NULL` |
| `id_paciente`, `id_clinica` | `INT NOT NULL` |
| `target_type`, `target_id` | entrada catalogada o medicamento adicional |
| `expected_version` | `BIGINT UNSIGNED NOT NULL` |
| `request_hash` | `CHAR(64) NOT NULL` |
| `result_code` | `VARCHAR(48) NOT NULL` |
| `provenance`, `action` | origen y operación realizada |
| `before_hash`, `after_hash` | `CHAR(64)` de la representación canónica |
| `actor_id`, `reason` | actor autorizado y motivo cuando aplique |
| `created_at` | `DATETIME NOT NULL` |

Restricción: `UNIQUE(id_paciente, idempotency_key)`. No se almacena el payload
clínico completo en logs; el contenido canónico permanece en su tabla y el hash
permite detectar reuso incorrecto. La aplicación no permite UPDATE/DELETE de
estas filas y el acceso Doctor se filtra por paciente y clínica.

### D. Nueva: `pac_m07_revision_clinica`

Estado separado de revisión médica:

`id`, `id_paciente`, `id_clinica`, `scope=M07`, `status` (`PENDING`,
`ACCEPTED`, `REQUIRES_CORRECTION`), `reviewed_by`, `reviewed_at`,
`version`, `created_at`.

No sustituye los datos M07 ni autoriza por sí sola la finalización; documenta
que la captura declarada fue revisada cuando la gobernanza clínica lo exija.
El proyecto no tiene actualmente una tabla/servicio de auditoría clínica
genérico utilizable por estos caminos; `pac_m07_operaciones` cubre únicamente
la trazabilidad técnica M07, mientras esta tabla conserva el estado clínico de
revisión.

## PROPOSED_COLUMNS

En la tabla histórica se agregan, sin eliminar columnas:

```text
m07_respuesta_state VARCHAR(32) NOT NULL
m07_dosis_state VARCHAR(32) NOT NULL
m07_resultados_state VARCHAR(32) NOT NULL
m07_consumo_state VARCHAR(32) NOT NULL
m07_descripcion_state VARCHAR(32) NOT NULL
m07_eficacia_codigo VARCHAR(20) NULL
m07_eficacia_state VARCHAR(32) NOT NULL
m07_tolerabilidad_codigo VARCHAR(24) NULL
m07_tolerabilidad_state VARCHAR(32) NOT NULL
m07_efectos_descripcion TEXT NULL
m07_efectos_descripcion_state VARCHAR(32) NOT NULL
m07_version BIGINT UNSIGNED NOT NULL
m07_updated_at DATETIME NULL
m07_updated_by BIGINT NULL
m07_provenance VARCHAR(16) NOT NULL
```

No se declara un `DEFAULT 'UNANSWERED'`: una migración debe calcular el estado
de cada fila y una inserción nueva debe proporcionarlo explícitamente. Durante
la fase intermedia de migración las columnas pueden ser nullable; sólo se
vuelven `NOT NULL` después del backfill verificable. El nombre y tipo final
deben verificarse contra MariaDB/MySQL operativos. Se recomienda
`VARCHAR + CHECK` y allowlist de servicio en vez de depender sólo de `ENUM`.

### DDL lógico final — no ejecutado

El siguiente DDL fija la forma de la propuesta. Los nombres de índices y la
compatibilidad exacta de FKs deben comprobarse en el fixture sintético antes de
generar una migración real:

```sql
-- Extensión aditiva de la fila catalogada; no elimina columnas históricas.
ALTER TABLE pac_respuestas_paciente_modulo_7
  ADD COLUMN m07_respuesta_state VARCHAR(32) NULL,
  ADD COLUMN m07_dosis_state VARCHAR(32) NULL,
  ADD COLUMN m07_resultados_state VARCHAR(32) NULL,
  ADD COLUMN m07_consumo_state VARCHAR(32) NULL,
  ADD COLUMN m07_descripcion_state VARCHAR(32) NULL,
  ADD COLUMN m07_eficacia_codigo VARCHAR(20) NULL,
  ADD COLUMN m07_eficacia_state VARCHAR(32) NULL,
  ADD COLUMN m07_tolerabilidad_codigo VARCHAR(24) NULL,
  ADD COLUMN m07_tolerabilidad_state VARCHAR(32) NULL,
  ADD COLUMN m07_efectos_descripcion TEXT NULL,
  ADD COLUMN m07_efectos_descripcion_state VARCHAR(32) NULL,
  ADD COLUMN m07_version BIGINT UNSIGNED NULL,
  ADD COLUMN m07_updated_at DATETIME NULL,
  ADD COLUMN m07_updated_by BIGINT NULL,
  ADD COLUMN m07_provenance VARCHAR(16) NULL;

CREATE TABLE pac_m07_medicamentos_no_catalogados (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entry_key CHAR(36) NOT NULL,
  id_paciente INT NOT NULL,
  id_clinica INT NOT NULL,
  ambito VARCHAR(32) NOT NULL,
  id_tema INT NOT NULL,
  nombre VARCHAR(255) NOT NULL,
  nombre_normalizado VARCHAR(255) NOT NULL,
  estado_registro VARCHAR(16) NOT NULL,
  respuesta VARCHAR(20) NULL,
  respuesta_state VARCHAR(32) NOT NULL,
  dosis TEXT NULL,
  dosis_state VARCHAR(32) NOT NULL,
  consumo VARCHAR(20) NULL,
  consumo_state VARCHAR(32) NOT NULL,
  eficacia_codigo VARCHAR(20) NULL,
  eficacia_state VARCHAR(32) NOT NULL,
  tolerabilidad_codigo VARCHAR(24) NULL,
  tolerabilidad_state VARCHAR(32) NOT NULL,
  descripcion_efectos TEXT NULL,
  descripcion_efectos_state VARCHAR(32) NOT NULL,
  version BIGINT UNSIGNED NOT NULL,
  provenance VARCHAR(16) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  created_by BIGINT NULL,
  updated_by BIGINT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY m07_other_entry_key (id_paciente, id_clinica, ambito, entry_key),
  KEY m07_other_scope (id_paciente, id_clinica, ambito, id_tema, estado_registro),
  KEY m07_other_name_review (id_paciente, id_clinica, ambito, id_tema, nombre_normalizado),
  CONSTRAINT m07_other_patient_fk FOREIGN KEY (id_paciente) REFERENCES pc_paciente (id),
  CONSTRAINT m07_other_clinic_fk FOREIGN KEY (id_clinica) REFERENCES clinicas (id),
  CONSTRAINT m07_other_theme_fk FOREIGN KEY (id_tema) REFERENCES pac_temas_modulo_7 (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_m07_operaciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  idempotency_key CHAR(36) NOT NULL,
  id_paciente INT NOT NULL,
  id_clinica INT NOT NULL,
  ambito VARCHAR(32) NOT NULL,
  target_type VARCHAR(32) NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  expected_version BIGINT UNSIGNED NOT NULL,
  request_hash CHAR(64) NOT NULL,
  result_code VARCHAR(48) NOT NULL,
  action VARCHAR(32) NOT NULL,
  provenance VARCHAR(16) NOT NULL,
  before_hash CHAR(64) NULL,
  after_hash CHAR(64) NULL,
  actor_id BIGINT NULL,
  reason VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY m07_operation_key (id_paciente, idempotency_key),
  KEY m07_operation_resource (id_paciente, id_clinica, ambito, target_type, target_id),
  CONSTRAINT m07_operation_patient_fk FOREIGN KEY (id_paciente) REFERENCES pc_paciente (id),
  CONSTRAINT m07_operation_clinic_fk FOREIGN KEY (id_clinica) REFERENCES clinicas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pac_m07_revision_clinica (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_paciente INT NOT NULL,
  id_clinica INT NOT NULL,
  scope VARCHAR(16) NOT NULL,
  status VARCHAR(24) NOT NULL,
  version BIGINT UNSIGNED NOT NULL,
  reviewed_by BIGINT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY m07_review_scope (id_paciente, id_clinica, scope),
  CONSTRAINT m07_review_patient_fk FOREIGN KEY (id_paciente) REFERENCES pc_paciente (id),
  CONSTRAINT m07_review_clinic_fk FOREIGN KEY (id_clinica) REFERENCES clinicas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Las columnas de la tabla existente son nullable sólo durante la fase de
migración. El backfill debe completar estados, versión y procedencia, verificar
conteos/hash y después convertirlas a `NOT NULL` sin `DEFAULT` semántico.
Las allowlists y las relaciones entre estado y valor se validan en el servicio;
los `CHECK` físicos se agregan sólo tras comprobar la versión MariaDB/MySQL
real.

## PROPOSED_CONSTRAINTS

- Todos los `state_code` persistidos pertenecen a
  `NOT_DOCUMENTED_HISTORICAL`, `DOES_NOT_REMEMBER`, `NOT_APPLICABLE`,
  `PENDING_REVIEW` o `CONFIRMED`; `UNANSWERED` y `KNOWN` son estados
  transitorios del DTO salvo aprobación explícita de borradores.
- `NOT_APPLICABLE` no puede coexistir con un valor dependiente confirmado.
- `DOES_NOT_REMEMBER` no acepta dosis numérica inventada, cero, `No` ni valor
  clínico equivalente.
- `CONFIRMED` exige valor canónico cuando el campo aplica.
- T2/T3/T4 requieren T1=`Si`; T5 requiere efectos adversos presentes.
- `m07_eficacia_codigo` y `m07_tolerabilidad_codigo` son independientes.
- Si tolerabilidad es `PRESENTO_EFECTOS`, la descripción puede ser obligatoria
  sólo después de aprobar esa regla clínica; mientras tanto se muestra como
  pendiente, no se inventa.
- FK de entrada catalogada a paciente/pregunta/tema se valida en servidor.
- FK de medicamento no catalogado a paciente, clínica y tema; la clínica debe
  coincidir con `pc_paciente.id_clinica`.
- `UNIQUE(id_paciente,id_pregunta)` en la tabla histórica sólo después de un
  preflight de duplicados y resolución autorizada.
- `UNIQUE(id_paciente,id_clinica,ambito,entry_key)` para adicionales; el
  nombre normalizado sólo genera advertencia de posible duplicado.
- `version` se incrementa en cada cambio y se compara con `expected_version`.
- Toda escritura requiere autorización por sesión, paciente, clínica, rol y
  CSRF; el `id_clinica` recibido nunca se considera autoridad.

## PROPOSED_MIGRATIONS

No se ejecutan ahora. Orden propuesto:

1. **Preflight:** respaldar esquema y filas afectadas en un entorno sintético;
   contar duplicados, huérfanos y valores fuera de allowlist. Si hay alguno,
   detenerse.
2. **Migración aditiva 1:** crear las tres tablas nuevas recomendadas, índices y
   FKs compatibles; ampliar la tabla histórica con columnas nullable
   temporales, sin defaults semánticos.
3. **Preflight de escritura:** comprobar que toda fila legacy tenga paciente,
   pregunta, tema y combinación de valores reconocible. Los duplicados,
   huérfanos o valores incompatibles detienen la migración.
4. **Backfill focal:** asignar `LEGACY` y estados deterministas. Una ausencia
   histórica se marca `NOT_DOCUMENTED_HISTORICAL`, nunca
   `DOES_NOT_REMEMBER`. Nunca crear nombres “Otro” por inferencia.
5. **Backfill de resultados:** mapear T3 a los ejes independientes según la
   tabla histórica, dejando `NOT_DOCUMENTED_HISTORICAL` en la dimensión que no
   consta.
6. **Servicio dual-read:** leer extensión y compatibilidad, detectar
   divergencias y bloquear la escritura si no coinciden.
7. **Servicio canónico dual-write atómico:** actualizar la extensión y la
   proyección histórica en una sola transacción, registrando una operación
   append-only.
8. **Actualizar ambos formularios:** sólo tras pasar la matriz sintética y
   revisión clínica de etiquetas, completitud y desconocidos.
9. **Unicidad final:** agregar `entry_key` y sus índices; no agregar
   unicidad por nombre. Las restricciones de paciente/pregunta de la tabla
   histórica sólo se añaden después de resolver duplicados
   de manera focal y aprobada.

### Rollback

El rollback inicial es de aplicación: deshabilitar lecturas/escrituras nuevas y
volver a la ruta compatible conservando las tablas y columnas aditivas. No se
eliminan datos nuevos automáticamente. Un rollback destructivo requeriría un
respaldo verificado, una lista exacta de objetos creados y autorización
separada; nunca `TRUNCATE`, limpieza global ni restauración completa de la
base.

## CONVERSATIONAL_IMPACT

`ConversationalM07Adapter` debería delegar en el servicio canónico y devolver
un `M07MedicationRecord` con:

```text
source, id, patient_id, clinic_id, theme_id, question_id, name,
fields.{respuesta,dosis,consumo},
efficacy.{code,state}, tolerability.{code,state,description},
legacy_projection, version, status, pending_reason
```

La interpretación puede producir `KNOWN` en memoria, pero sólo una
confirmación explícita produce `CONFIRMED`. “No recuerdo”, combinaciones y
contradicciones devuelven `PENDING_REVIEW` sin avance automático. La captura de
un medicamento adicional pide primero nombre y categoría, luego aplica el
mismo contrato de dependencias.

La navegación sigue siendo:

`pregunta → interpretación → propuesta → confirmación → persistencia atómica
→ readback → siguiente pregunta`.

La existencia de una tarjeta o de una fila vacía nunca completa M07.

## TRADITIONAL_FORM_IMPACT

Los caminos actuales que deben pasar por la transición son:

- GET/AJAX `/buscar/contenido-preguntas-modulo-7/{idPaciente}/{idRol}/{idCuestionario}`
  → `BusquedasController::contenidoPreguntasM7` → renderer P09 del modelo;
- POST `/paciente/editar-cuestionario-modulo7`
  → `ModulosController::pacienteEditarCuestionarioM7`
  → `MedicacionDolorModel::editarCuestionarioM7Paciente`;
- POST `/paciente/finalizar-modulo-paciente` para módulo 7;
- lectura Doctor de `editorTextoMD` y lectura clínica de
  `ClinicalDetailService`;
- `medicacionDolorQuest`, que puede inicializar filas vacías, debe quedar
  fuera de una lectura canónica y no puede ejecutarse como efecto secundario de
  un GET cuando comience la migración.

P09 debe leer el mismo DTO y mostrar:

- badges de estados desconocidos y pendientes;
- ambos ejes de eficacia/tolerabilidad;
- descripción de efectos adversos separada;
- colección de medicamentos no catalogados con alta, edición y desactivación;
- versión y advertencia cuando existe información que el formulario antiguo no
  podía representar.

El formulario actualizado no debe enviar campos ocultos con valores vacíos.
Debe enviar una `field_mask`, `expected_version` e `idempotency_key`. Si no
puede mostrar una extensión existente, la edición queda bloqueada con
`M07_EXTENDED_DATA_REQUIRES_UPDATED_FORM`; nunca sobrescribe silenciosamente
la información conversacional.

Mientras exista el POST antiguo que llama directamente al modelo sin
`field_mask`/versión, no se habilitan nuevas escrituras ampliadas. La
transición obligatoria es:

1. cambiar ese endpoint para delegar en `M07CanonicalService`;
2. aceptar sólo un parche de campos explícitos, preservando los no enviados;
3. comparar `expected_version` y rechazar 409 si falta o es obsoleta cuando la
   fila contiene extensión;
4. bloquear con 409 cualquier cliente antiguo que no pueda representar una
   extensión, sin limpiar columnas nuevas ni históricas;
5. ejecutar entonces la prueba cruzada antes de reabrir edición P09.

La interfaz tradicional conserva sus preguntas oficiales y su presentación
institucional; sólo cambia su adaptador de lectura/escritura cuando la decisión
sea aprobada. No se propone modificarla en esta fase.

## SHARED_SERVICE_DESIGN

Nombre tentativo: `M07CanonicalService`.

Responsabilidades:

1. resolver paciente, clínica y tema/pregunta;
2. leer catálogo, filas históricas y adicionales en una forma unificada;
3. validar estados, dependencias y longitudes;
4. aplicar `field_mask`, `expected_version` e idempotency key;
5. ejecutar una única transacción por operación;
6. escribir la fila canónica y sus proyecciones compatibles;
7. registrar una fila append-only en `pac_m07_operaciones`, incluyendo
   idempotencia, origen, acción y hashes anterior/posterior;
8. devolver readback desde la misma fuente después del commit;
9. rechazar duplicados, conflictos de clínica y versiones obsoletas.

`MedicacionDolorModel` conservaría su API tradicional como fachada de
compatibilidad, pero no contendría reglas divergentes. `ClinicalDetailService`
consumiría el mismo servicio en modo lectura. Ningún controlador debería
escribir directamente en `pac_respuestas_paciente_modulo_7`.

## VERSIONING_AND_IDEMPOTENCY

- `version` es entero monotónico por entrada; lectura devuelve la versión.
- El cliente envía `expected_version`; si no coincide, respuesta 409 sin
  modificación.
- `idempotency_key` se genera por confirmación, no por cada reintento HTTP.
- Repetir la misma operación con el mismo hash devuelve el resultado previo y
  no duplica filas.
- Reusar la clave con un hash distinto devuelve 409.
- Un lock por paciente/ámbito y una transacción protegen lotes concurrentes.
- La tabla de operaciones no almacena secretos ni el payload clínico completo.

## DATA_LOSS_PREVENTION

- Nunca usar “No” para una pregunta pendiente o desconocida.
- Nunca limpiar dependientes sin confirmación específica y evento trazable.
- Nunca convertir T3 combinado a una opción excluyente.
- Nunca guardar el nombre de Otro en M06 o en efectos adversos.
- No editar una fila legacy desde un cliente incapaz de conservar extensiones.
- Detectar duplicados antes de agregar unicidad; no fusionar automáticamente.
- Readback obligatorio después de cada confirmación.
- Snapshot hash antes/después en pruebas y migraciones.
- Si una transacción falla, rollback completo: ni extensión ni proyección quedan
  parcialmente actualizadas.

## COMPLETENESS_PROPOSAL

La siguiente política es propuesta y requiere aprobación clínica:

Se distinguen tres cierres:

1. `patient_capture_status`: captura abierta o captura completada por el
   paciente; no significa que un médico haya aprobado el contenido.
2. `doctor_review_status`: `NOT_STARTED`, `PENDING`, `ACCEPTED` o
   `REQUIRES_CORRECTION`.
3. `formal_module_status`: `NOT_ELIGIBLE`, `ELIGIBLE` o `FINALIZED`;
   permanece deshabilitado en la entrevista hasta una aprobación posterior.

Para la captura del paciente:

- T1=`No` confirmado: T2–T5 `NOT_APPLICABLE`; entrada terminal.
- T1=`Si`: T2, T3 y T4 deben ser confirmados o contar con estados explícitos
  que la política clínica apruebe como terminales.
- T5 es requerido cuando tolerabilidad es `PRESENTO_EFECTOS`, si así lo aprueba
  el responsable clínico; no se asume automáticamente por esta propuesta.
- Un resultado combinado no está completo hasta resolver ambas dimensiones o
  persistir la incertidumbre declarada bajo la regla aprobada.
- Un medicamento adicional requiere nombre, categoría, T1 y dependencias
  aplicables; no se completa como “Otro” sin nombre.
- `NOT_DOCUMENTED_HISTORICAL` no cuenta como respuesta del paciente en una
  captura nueva. `UNANSWERED`, `KNOWN` y `PENDING_REVIEW` mantienen la
  captura abierta.
- `DOES_NOT_REMEMBER` puede ser terminal de captura sólo si la declaración fue
  explícita y se aprueba esa política; siempre deja revisión médica pendiente.
- Una contradicción puede quedar capturada como `PENDING_REVIEW`, pero bloquea
  la finalización formal.
- Una categoría está completa sólo cuando todas sus entradas activas están en
  estado terminal.
- M07 no está completo por recorrer 46 preguntas ni por crear filas vacías.

## FINALIZATION_PROPOSAL

Se separan tres conceptos:

1. **Captura del paciente:** todos los datos que el paciente quiso declarar
   quedan persistidos con su estado, sin afirmar que el médico los aprobó.
2. **Revisión médica:** el Doctor revisa estados desconocidos, combinaciones,
   adicionales y contradicciones; se registra en `pac_m07_revision_clinica`.
3. **Finalización formal del módulo:** sólo después de que completitud y
   revisión cumplan la política aprobada se puede usar el mecanismo
   `pac_historia_clinica_finalizar`.

Mientras no exista aprobación clínica de completitud y gobernanza, se mantiene
`M07_FINALIZATION_ENABLED: NO` para la entrevista. El servicio puede informar
los tres estados de cierre, pero no debe insertar la finalización por haber
llegado a la última tarjeta.

## CLINICAL_APPROVAL_PENDING

Requieren validación clínica independiente:

- si T2, T3 y T4 son obligatorios en todos los casos T1=`Si`;
- si T5 es obligatorio para todo efecto adverso;
- si `DOES_NOT_REMEMBER` permite cerrar captura aunque quede revisión;
- los valores oficiales de eficacia y tolerabilidad desconocidos;
- si “No presentó efectos adversos” es una respuesta explícita o una ausencia de
  registro;
- si la revisión médica es requisito previo a la finalización formal;
- textos y clasificación de las seis categorías, especialmente las entradas
  históricas señaladas en `CLINICAL_RULES_DECISION`;
- tratamiento clínico de uno o varios “Otro/Otros”.

La aprobación conceptual de la colección no autoriza por sí sola el esquema ni
la escritura sobre expedientes reales.

## TEST_MATRIX

Preparar, pero no ejecutar todavía, en una base MySQL sintética allowlisted:

| # | Caso | Resultado esperado |
|---:|---|---|
| 1 | Entrevista guarda una entrada catalogada | P09 lee el mismo valor y estados |
| 2 | P09 guarda una entrada catalogada | Entrevista lee el mismo DTO |
| 3 | Edición en ambos sentidos | field mask conserva campos no editados |
| 4 | Histórico T3=`Funciono` | eficacia positiva; tolerabilidad no inferida |
| 5 | Varios medicamentos Otro/Otros | IDs, nombres y categorías independientes |
| 6 | “No recuerdo” en dosis/resultado | estado incierto; no cero, no negativo |
| 7 | Eficacia + efectos adversos | ambos ejes persistidos atómicamente |
| 8 | Cambio T1=`No` con dependientes | confirmación requerida; limpieza trazada |
| 9 | Versión obsoleta | HTTP/servicio 409; cero escrituras |
| 10 | Reintento idempotente | mismo resultado; cero duplicados |
| 11 | Falla a mitad de lote | rollback completo |
| 12 | Paciente/clínica cruzados | rechazo 403/409; sin lectura ni escritura |
| 13 | Migración y restauración | hashes y estados preservados |

Cada prueba debe comprobar readback, conteo de filas, IDs, versión, eventos,
ausencia de escrituras fuera del paciente sintético y que `bd_clinica` no se
toque. La matriz consolida los 13 casos ya definidos y agrega sólo los casos
necesarios para los defectos de este ajuste:

| # | Caso adicional | Resultado esperado |
|---:|---|---|
| 14 | Backfill sin dato histórico | `NOT_DOCUMENTED_HISTORICAL`, nunca `DOES_NOT_REMEMBER` |
| 15 | POST P09 antiguo con extensión ampliada | 409 y cero escrituras destructivas |
| 16 | Mismo nombre en otra categoría o como segundo hecho | IDs/entry keys separados; advertencia, no fusión |

Las pruebas que requieran las tablas nuevas no se ejecutan hasta aprobar este
diseño y preparar un fixture aislado.

## IMPLEMENTATION_ORDER

1. Aprobar estados, completitud, combinación y clasificación clínica.
2. Aprobar nombres de tablas/columnas y política de auditoría.
3. Crear migraciones aditivas y fixture sintético; ejecutar preflight de
   duplicados y huérfanos.
4. Implementar `M07CanonicalService` y DTO compartido.
5. Implementar lectura dual y comparación de divergencias.
6. Implementar escritura transaccional, versión, idempotencia y operación
   append-only.
7. Adaptar `ConversationalM07Adapter` sin cambiar contratos de otros módulos.
8. Adaptar P09 y `ClinicalDetailService` con field mask y protección de
   extensiones.
9. Ejecutar la matriz sintética completa, incluida restauración focal.
10. Realizar aceptación clínica y navegador separadas.
11. Mantener finalización conversacional deshabilitada hasta la decisión formal.

## SCHEMA_CHANGE_REQUIRED

`YES — ADDITIVE ONLY`.

Se requieren columnas nuevas en `pac_respuestas_paciente_modulo_7` y, como
mínimo, las tres tablas `pac_m07_medicamentos_no_catalogados`,
`pac_m07_operaciones` y `pac_m07_revision_clinica`. La tabla de eventos
separada fue descartada y quedó consolidada en `pac_m07_operaciones`. No se
propone borrar columnas, cambiar IDs históricos ni alterar la estructura P09
existente en esta fase.

## OWNER_DECISIONS_REQUIRED

1. Aprobar o rechazar los estados persistidos
   `NOT_DOCUMENTED_HISTORICAL`, `DOES_NOT_REMEMBER`,
   `NOT_APPLICABLE`, `PENDING_REVIEW` y `CONFIRMED`, además de los estados
   transitorios `UNANSWERED` y `KNOWN`.
2. Confirmar si un dato desconocido puede cerrar captura, siempre pendiente de
   revisión médica.
3. Aprobar los ejes independientes de eficacia y tolerabilidad y sus códigos.
4. Aprobar la colección de uno o varios medicamentos no catalogados, su nombre,
   categoría y desactivación.
5. Aprobar que la tabla histórica reciba columnas aditivas y que el servicio
   sea la única puerta de escritura.
6. Aprobar la operación append-only como mecanismo técnico M07 y confirmar que
   no existe un servicio de auditoría general que deba reutilizarse.
7. Confirmar las reglas T2/T3/T4/T5 y el requisito de revisión para finalizar.
8. Confirmar la clasificación clínica de las entradas señaladas en la decisión
   M07; no mover IDs unilateralmente.

## Estado de esta entrega

```yaml
PHASE: M07_SHARED_CANONICAL_CONTRACT_REFINEMENT

HISTORICAL_UNKNOWN_SEMANTICS: NOT_DOCUMENTED_HISTORICAL_SEPARATE_FROM_UNANSWERED
EXPLICIT_DOES_NOT_REMEMBER: ONLY_WITH_EXPLICIT_PATIENT_EVIDENCE
PERSISTED_STATE_MODEL: FIVE_PERSISTED_STATES_PLUS_TWO_TRANSIENT_DTO_STATES
PROVENANCE_MODEL: LEGACY_TRADITIONAL_INTERVIEW

OTHER_MEDICATION_UNIQUENESS: ENTRY_KEY_NOT_NAME
OTHER_MEDICATION_MULTI_CATEGORY_POLICY: ALLOW_SEPARATE_FACTS_WITH_CATEGORY_INDEX

LEGACY_T3_COMPATIBILITY: ADDITIVE_EFFICACY_TOLERABILITY_WITH_NO_INFERENCE
TRADITIONAL_FORM_WRITE_PROTECTION: BLOCK_OLD_CLIENT_WHEN_EXTENSION_UNREPRESENTABLE
SHARED_CANONICAL_SERVICE: M07CanonicalService_REQUIRED_FOR_ALL_WRITES

PROPOSED_TABLES_REVIEWED: THREE_NEW_TABLES_RETAINED
DUPLICATE_INFRASTRUCTURE_AVOIDED: EVENT_LOG_MERGED_INTO_OPERATIONS

PATIENT_CAPTURE_COMPLETION: SEPARATE_FROM_REVIEW_AND_FINALIZATION
DOCTOR_REVIEW_STATUS: PENDING_ACCEPTED_REQUIRES_CORRECTION
FORMAL_MODULE_FINALIZATION: DISABLED_UNTIL_APPROVED
CLINICAL_APPROVAL_PENDING: YES

FINAL_DDL_SPECIFICATION: ADDITIVE_MYSQL_COMPATIBLE_NO_SEMANTIC_DEFAULTS
HISTORICAL_BACKFILL_POLICY: DETERMINISTIC_LEGACY_WITH_NO_CLINICAL_INFERENCE
MIGRATION_ROLLBACK_POLICY: APPLICATION_ROLLBACK_NON_DESTRUCTIVE
IMPLEMENTATION_ORDER: PREPARED_WITH_LEGACY_WRITE_GATE
PROPOSED_COLUMNS: STATES_OUTCOMES_VERSION_AND_TRACEABILITY_DEFINED
PROPOSED_CONSTRAINTS: FK_UNIQUENESS_CHECKS_AND_SERVER_AUTHORIZATION_DEFINED
PROPOSED_MIGRATIONS: ADDITIVE_STAGED_NOT_EXECUTED

CONVERSATIONAL_IMPACT: SHARED_DTO_CONFIRMATION_FIRST
TRADITIONAL_FORM_IMPACT: READ_WRITE_SHARED_FIELD_MASK_REQUIRED
SHARED_SERVICE_DESIGN: M07_CANONICAL_SERVICE_REQUIRED
VERSIONING_AND_IDEMPOTENCY: OPTIMISTIC_VERSION_PLUS_OPERATION_KEY
DATA_LOSS_PREVENTION: NO_SILENT_CLEAR_OR_LEGACY_OVERWRITE

COMPLETENESS_PROPOSAL: DEFINED_PENDING_CLINICAL_APPROVAL
FINALIZATION_PROPOSAL: CAPTURE_REVIEW_FINALIZATION_SEPARATED
TEST_MATRIX: 13_PLUS_3_FOCAL_SYNTHETIC_CASES_DEFINED_NOT_EXECUTED
SCHEMA_CHANGE_REQUIRED: YES_ADDITIVE_ONLY
OWNER_DECISIONS_REQUIRED: YES

CODE_MODIFIED: NO
SCHEMA_MODIFIED: NO
DATABASE_MODIFIED: NO
ENV_MODIFIED: NO
COMMITS_CREATED: NO

FINAL_STATUS: PROPOSED_PENDING_TECHNICAL_AND_CLINICAL_APPROVAL
```

**Conclusión:** el contrato refinado separa ausencia histórica de una
declaración del paciente, no impone unicidad clínica por nombre, conserva
procedencia y bloquea los caminos legacy incapaces de representar la extensión.
La ampliación sigue siendo aditiva y requiere aprobación técnica y clínica antes
de iniciar cualquier migración o cambio de código.

## ADDENDUM — IMPLEMENTACIÓN CANÓNICA COMPARTIDA 01

Fecha: 2026-10-14

La implementación técnica se prepara como una extensión opt-in y no cambia las
rutas existentes. Se fijan estas decisiones para la siguiente aceptación:

1. `UNANSWERED` sólo puede persistirse mediante el método explícito de borrador
   del servicio canónico. No se usa durante el backfill y no se activa desde la
   interfaz actual.
2. `provenance` representa el origen de la última operación de la entrada
   (`LEGACY`, `TRADITIONAL` o `INTERVIEW`), no una procedencia independiente de
   cada campo. La máscara de campos y los hashes de
   `pac_m07_operaciones` conservan la trazabilidad de cada operación sin guardar
   el payload clínico completo en el log.
3. La revisión clínica se compara contra el hash de la lectura que revisó el
   médico. Cualquier cambio posterior invalida esa revisión y devuelve un
   conflicto de versión; la revisión no finaliza el módulo.
4. `M07CanonicalActivationGate` queda cerrado por defecto. La aplicación
   habitual y `ConversationalM07Adapter` no lo construyen ni reciben nuevas
   escrituras. Una prueba sintética debe abrirlo explícitamente después de
   verificar base, paciente y clínica.
5. La migración es aditiva, idempotente y fail-closed: si encuentra una
   extensión parcial, duplicados por paciente/pregunta o una conexión distinta
   de `clinica_dolor_demo_20261010_final`, se detiene sin limpiar ni fusionar
   registros.

Estas decisiones no constituyen aprobación clínica de completitud, revisión,
`Otro/Otros` ni finalización formal. Las rutas P09, Doctor y entrevista siguen
fuera de la activación hasta una entrega posterior.
