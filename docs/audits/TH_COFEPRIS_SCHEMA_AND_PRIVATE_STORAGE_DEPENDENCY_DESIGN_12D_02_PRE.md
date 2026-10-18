# COFEPRIS — 12D-02-PRE — Diseño de dependencias estructurales

## Resultado

```yaml
COFEPRIS_12D_02_PRE:
  STATUS: DESIGN_READY_FOR_OWNER_REVIEW
  PRIVATE_ROOT_CANDIDATE: C:\\xampp\\private\\clinica-dolor\\cofepris
  PRIVATE_ROOT_BOUNDARY: OUTSIDE_CURRENT_DOCUMENTROOT_AND_NO_APPLICABLE_ALIAS_FOUND_STATICALLY
  PRIVATE_ROOT_APPROVAL_REQUIRED: YES
  FOLIO_LIFECYCLE_VERIFIED: STATIC_CODE_RECONSTRUCTION
  DOCUMENT_CREATED_BEFORE_PATIENT_ASSIGNMENT: YES
  RECOMMENDED_SCHEMA_STRATEGY: EXPLICIT_RANGE_OWNERSHIP_PLUS_DURABLE_DOCUMENT_ENTITY
  CLINIC_OWNERSHIP_STRATEGY: SERVER_SIDE_PERSISTED_CLINIC_OWNER
  DOCUMENT_IDENTITY_STRATEGY: TECHNICAL_DOCUMENT_ID_WITH_NULLABLE_COFEPRIS_ASSIGNMENT
  LEGACY_COMPATIBILITY: PRESERVE_HISTORICAL_ROWS_AND_FILES_WITH_FAIL_CLOSED_AMBIGUITY
  SCHEMA_MIGRATION_REQUIRED: YES_BEFORE_PRIVATE_WRITES
  HISTORICAL_BACKFILL_REQUIRED: NO_FOR_INITIAL_ACTIVATION; SEPARATE_OWNER_APPROVED_PHASE_LATER
  CODE_MODIFIED: NO
  DATABASE_MODIFIED: NO
  PDF_MODIFIED: NO
  MIGRATIONS_EXECUTED: NO
  COMMITS: NONE
  DEPLOYMENTS: NONE
```

Esta fase sólo produce diseño técnico. No crea la raíz candidata, no modifica `.env`, Apache, PHP, SQL, tablas, registros ni documentos.

## 1. Arquitectura actual confirmada

El flujo actual se divide en dos momentos:

```text
Doctor carga un PDF de folios
  -> POST /cofepris/insert-folios
  -> CofeprisController::insertFolios()
  -> CofeprisModel::guardarFolio()
  -> crea carpeta pública por fecha + sufijo aleatorio
  -> separa páginas con FPDI
  -> escribe {carpeta}/{folio}.pdf
  -> INSERT en cofepris_folio

Posteriormente, al paciente se le asigna un folio
  -> POST /paciente/insert-cofepris
  -> ClinicaController::pacienteInsertCofepris()
  -> CofeprisModel::insertPacienteCofepris()
  -> generarFolio() busca el rango activo global
  -> calcula MAX(folio) por carpeta
  -> INSERT en cofepris con id_paciente, carpeta y folio

Lectura
  -> GET /pdf/cofepris/{id}
  -> PdfController::pdfCofepris()
  -> carga cofepris por id
  -> AuthMiddleware::assertPatientAccess()
  -> lee public/storage/cofepris/{carpeta}/{folio}.pdf
  -> genera la salida PDF autorizada
```

Cambios de surtido usan `POST /cofepris/edit-surtido` y el modelo actualiza por `id`. La revisión de ownership de esa operación queda fuera de esta fase de diseño.

### Consecuencia temporal

El PDF individual y su combinación `carpeta + folio` existen antes de que exista `cofepris.id` o una relación con paciente. Por tanto, `cofepris` no puede ser la única entidad de identidad documental: no representa folios todavía no asignados.

El modelo actual tampoco liga `cofepris_folio` con `id_clinica`; la selección de rango activo es global (`estado = 0`, orden por `id`). Esto impide demostrar la propiedad multi-clínica de un rango sólo con los campos actuales.

## 2. Evaluación de la raíz privada

Candidata evaluada estáticamente:

```text
C:\xampp\private\clinica-dolor\cofepris\
```

Resultados:

- `C:\xampp\htdocs\clinica-dolor` es el `DocumentRoot` del VirtualHost `clinica.test`.
- `C:\xampp\private` no existe actualmente; no se creó.
- No se identificó un `Alias` de Apache que alcance la candidata.
- Los aliases presentes en la configuración revisada son de XAMPP (`/licenses`, `/phpmyadmin`, `/webalizer`), no de COFEPRIS.
- No se inspeccionaron permisos efectivos del directorio porque la candidata no existe.
- La ubicación sería compatible con el aislamiento del DocumentRoot si el propietario crea el directorio con permisos mínimos para el proceso PHP y deja la ruta fuera de cualquier Alias futuro.
- La ruta debe configurarse mediante una decisión operativa/deployment, no codificarse como autorización ni aceptarse desde el navegador.

`PRIVATE_ROOT_RECOMMENDATION: CANDIDATE_ONLY — REQUIRES_OWNER_APPROVAL_AND_OPERATIONAL_PROVISIONING`.

Condiciones mínimas antes de usarla:

1. Crear el root con una cuenta/proceso PHP explícitamente autorizado.
2. Confirmar que no es reparse point, symlink ni junction.
3. Confirmar que ningún `DocumentRoot`, `Alias`, enlace o servidor alternativo lo expone.
4. Separar temporales de definitivos.
5. Aplicar permisos de menor privilegio.
6. Configurar la ruta fuera de valores controlables por el cliente.
7. Probar lectura/escritura sintética y atomicidad sin tocar PDFs históricos.

## 3. Alternativas de esquema

### Opción A — Sólo columnas adicionales en tablas existentes

Cambios conceptuales:

- `cofepris_folio.id_clinica` nullable para conservar históricos no conciliados.
- Metadatos (`storage_key`, hash, tamaño, MIME y estado) en `cofepris`.
- Posible `cofepris.id_documento` nullable.

Ventajas:

- Menor número de entidades.
- Consultas sencillas para registros ya asignados.

Limitaciones:

- No representa correctamente documentos creados antes de asignar paciente.
- La fila `cofepris` no existe para folios libres.
- La propiedad y el estado del PDF quedarían repartidos entre rango y registro.
- Históricos ambiguos obligan a muchos `NULL` y reglas especiales.

Evaluación: insuficiente como diseño único para el ciclo de vida real.

### Opción B — Propiedad explícita del rango más entidad documental durable

Cambios conceptuales:

- Agregar ownership nullable a `cofepris_folio` o introducir una entidad de rango equivalente.
- Crear una entidad documental para cada PDF individual, con al menos:
  - identificador técnico;
  - rango propietario;
  - clínica propietaria;
  - folio textual;
  - clave física opaca (`storage_key`), nunca una ruta recibida del cliente;
  - SHA-256, tamaño y MIME validado;
  - estado `UNASSIGNED`, `ASSIGNED`, `AMBIGUOUS`, `QUARANTINED` o equivalente;
  - `id_cofepris` nullable;
  - timestamps de creación/asignación y, si aplica, actor.
- Relacionar `cofepris` con el documento mediante una referencia técnica nullable o una relación equivalente.

Ventajas:

- Representa documentos antes y después de asignar paciente.
- Permite hash, integridad, cuarentena y ambigüedad sin falsificar propietario.
- Permite que el resolver autorice documento, clínica, paciente y registro por separado.
- Separa migración estructural de backfill histórico.

Limitaciones:

- Requiere migración de esquema y una operación transaccional de asignación.
- Necesita definir las reglas de compatibilidad para los seis rangos históricos.
- Requiere adaptar carga, asignación, lectura e impresión.

Evaluación: recomendada.

### Opción C — Nuevo agregado de rango/documento/asignación

Crear nuevas entidades para rango, documento y asignación, dejando `cofepris` como legado o vínculo de compatibilidad.

Ventajas:

- Mejor separación de responsabilidades y trazabilidad futura.
- Facilita múltiples propietarios, transferencias auditadas y estados documentales.

Limitaciones:

- Mayor complejidad y superficie de migración.
- Requiere compatibilidad amplia con las rutas existentes.

Evaluación: alternativa si los datos runtime muestran rangos compartidos, solapamientos o transferencias históricas que hagan insuficiente la opción B.

### Recomendación

Adoptar la **Opción B**, condicionada a la verificación runtime del esquema y a la autorización de una migración. La propiedad de clínica debe persistirse en el rango o entidad equivalente y cada PDF individual debe tener una identidad técnica durable antes de habilitar nuevas escrituras privadas.

No se recomienda usar `carpeta + folio`, `id_cofepris` o una ruta física como única identidad.

## 4. Diseño multi-clínica

Reglas obligatorias:

1. La clínica se obtiene del JWT validado en servidor.
2. El cliente nunca proporciona la clínica como autoridad.
3. Un rango nuevo se crea dentro de la clínica autenticada.
4. Todas las consultas administrativas filtran por ownership de clínica.
5. Un registro COFEPRIS se autoriza por registro, paciente y clínica.
6. El resolver verifica la identidad documental antes de abrir el archivo.
7. Una clínica no puede leer, asignar, editar, surtir ni descargar documentos de otra.
8. La ruta física nunca se acepta desde URL, formulario o JSON.
9. Las carpetas/folios ambiguos se rechazan, no se elige el primer resultado.
10. Las operaciones de reserva y asignación usan transacción y bloqueo de fila adecuado.

### Asignación segura futura

La reserva debe seleccionar un rango propiedad de la clínica, bloquearlo o usar un mecanismo equivalente, localizar el siguiente documento libre por clave técnica, marcarlo como asignado y crear el registro COFEPRIS dentro de la misma unidad transaccional. Si falla cualquier paso, no debe quedar un archivo definitivo sin referencia ni un registro clínico sin documento coherente.

No debe conservarse la estrategia actual de `MAX(folio)` global sin una decisión de concurrencia y unicidad.

## 5. Integridad documental

Para documentos nuevos se recomienda conservar:

- hash SHA-256 del archivo definitivo;
- tamaño en bytes;
- MIME validado por contenido;
- extensión derivada del tipo permitido;
- clave de almacenamiento opaca y única;
- estado de ciclo de vida;
- relación opcional con `cofepris` hasta la asignación;
- rango y clínica propietarios;
- timestamps y actor de la operación.

El flujo futuro debe:

1. recibir el upload en un temporal fuera del root público;
2. rechazar reparse points, symlinks, traversal y sobrescritura;
3. validar PDF, tamaño y MIME;
4. calcular hash;
5. escribir con nombre nuevo en temporal;
6. promover atómicamente a definitivo;
7. persistir referencia y estado dentro de una transacción;
8. eliminar temporales ante cualquier fallo.

## 6. Compatibilidad histórica

Se preservan sin cambios:

- 345 registros COFEPRIS reportados;
- 6 rangos históricos;
- 2,006 PDFs reportados;
- excepciones 23/26, 256 huérfano y 256/257 con documento físico compartido.

Tratamiento propuesto:

| Estado histórico | Lectura futura | Backfill |
|---|---|---|
| Relación inequívoca comprobada | Sólo mediante resolver autorizado | Sólo tras aprobación y evidencia |
| Sin propietario de clínica | No exponer por resolver nuevo | Revisión posterior |
| Más de una clínica posible | Rechazar/poner en cuarentena | Revisión humana |
| Documento compartido | Rechazar hasta conciliar identidad | Nunca asignar automáticamente |
| Carpeta/folio discrepante | Preservar y marcar | Procedimiento separado |
| Registro huérfano | No reasignar | Decisión administrativa |

La estructura nueva debe permitir `id_clinica`, `id_documento` o estados equivalentes nullable para que una migración estructural no obligue a atribuir históricos sin evidencia. El backfill histórico es una fase posterior e independiente.

## 7. Plan de implementación propuesto

### Etapa 1 — Preparación estructural

- Confirmar `SHOW CREATE TABLE`, índices, colisiones y relaciones en `bd_clinica` mediante SQL de sólo lectura.
- Aprobar modelo de rango/documento.
- Diseñar constraints y estados nullable para históricos.

Requiere autorización de esquema: sí. No ejecutada.

### Etapa 2 — Ownership por clínica

- Persistir ownership de rangos nuevos.
- Filtrar listados, detalles, edición y surtido por clínica autenticada.
- Incorporar bloqueo/concurrencia.

Requiere migración y cambios funcionales: sí. No ejecutada.

### Etapa 3 — Identidad documental durable

- Crear entidad o columnas aprobadas para documento individual.
- Persistir clave opaca, hash, tamaño, MIME y estado.
- Enlazar documento con rango y `cofepris` nullable.

Requiere esquema: sí. No ejecutada.

### Etapa 4 — Almacenamiento privado nuevo

- Aprovisionar root fuera de DocumentRoot/Alias.
- Implementar writer privado con temporal, atomicidad, permisos y cleanup.
- Rechazar nuevas escrituras legacy públicas mientras no exista el writer privado.

Requiere decisión operativa y código: sí. No ejecutada.

### Etapa 5 — Resolver y controlador PDF

- Aceptar sólo ID lógico de registro.
- Resolver servidor-side registro → paciente → clínica → documento → clave física.
- Rechazar inexistentes, inconsistentes y ambiguos.
- Mantener encabezados PDF/print sin exponer la ruta física.

Requiere código y pruebas: sí. No ejecutada.

### Etapa 6 — Regresión sintética

- Crear fixtures aislados para clínicas distintas, pacientes, rango, documento libre, documento asignado, documento ambiguo y path inválido.
- Validar HTTP, autorización cruzada, hash, traversal, symlink, overwrite, errores y rollback.

Requiere entorno sintético: sí. No ejecutada.

### Etapa 7 — Transición histórica

- Clasificar históricos.
- Resolver excepciones con aprobación humana.
- Backfill sólo de asociaciones demostradas.
- Migrar archivos sólo con inventario y rollback verificados.

Requiere decisión clínica/administrativa y operación separada: sí. No ejecutada.

## 8. Requisitos para desbloquear SEC-02B

1. Aprobación del root `C:\xampp\private\clinica-dolor\cofepris` o una alternativa operativa equivalente.
2. Confirmación de permisos del proceso PHP y ausencia de exposición por Alias/reparse point.
3. Autorización de esquema para ownership por clínica y entidad documental durable.
4. Confirmación de constraints, unicidad y estrategia de concurrencia.
5. Decisión sobre el comportamiento del writer legacy mientras el writer privado no esté activo.
6. Fixtures sintéticos aislados y autorización para pruebas de escritura únicamente en ellos.
7. Política para los seis rangos y las excepciones 23, 26, 256 y 257.

## 9. Decisiones requeridas del propietario

- ¿Se aprueba el root privado candidato o debe usarse otra ubicación?
- ¿Se aprueba la opción B (ownership explícito + entidad documental) o se requiere diseñar la opción C?
- ¿Debe bloquearse el endpoint legacy de carga mientras SEC-02B esté pendiente?
- ¿Qué autoridad aprueba la conciliación de históricos?
- ¿Se exige auditoría de lectura/descarga para documentos COFEPRIS?
- ¿Cuál será el entorno de despliegue futuro para parametrizar el root sin depender de `.env` local?

## 10. Estado de esta fase

```yaml
PRIVATE_ROOT_CANDIDATE: C:\\xampp\\private\\clinica-dolor\\cofepris
PRIVATE_ROOT_BOUNDARY: STATICALLY_OUTSIDE_CURRENT_DOCUMENTROOT; NO_ALIAS_FOUND
PRIVATE_ROOT_APPROVAL_REQUIRED: YES
FOLIO_LIFECYCLE_VERIFIED: YES_STATICALLY_FROM_CURRENT_CONTROLLERS_AND_MODELS
DOCUMENT_CREATED_BEFORE_PATIENT_ASSIGNMENT: YES
SCHEMA_OPTIONS: A_COLUMNS_ONLY; B_RANGE_OWNERSHIP_PLUS_DOCUMENT_ENTITY; C_NEW_RANGE_DOCUMENT_ASSIGNMENT_AGGREGATE
RECOMMENDED_SCHEMA_STRATEGY: B_CONDITIONAL_ON_RUNTIME_SCHEMA_AND_OWNER_AUTHORIZATION
CLINIC_OWNERSHIP_STRATEGY: AUTHENTICATED_SERVER_SIDE_PERSISTED_OWNER
DOCUMENT_IDENTITY_STRATEGY: DURABLE_TECHNICAL_DOCUMENT_ID_WITH_OPAQUE_STORAGE_KEY_AND_NULLABLE_COFEPRIS_LINK
LEGACY_COMPATIBILITY: PRESERVE_AND_FAIL_CLOSED_AMBIGUOUS_HISTORICALS
SEC_02B_UNBLOCK_REQUIREMENTS: PRIVATE_ROOT_APPROVAL_SCHEMA_AUTHORIZATION_DURABLE_DOCUMENT_ID_SYNTHETIC_FIXTURES
SCHEMA_MIGRATION_REQUIRED: YES
HISTORICAL_BACKFILL_REQUIRED: NO_FOR_NEW_WRITES; SEPARATE_APPROVAL_REQUIRED_FOR_HISTORICALS
```

No se reabrió SEC-02A. No se inició SEC-02B, SEC-02C ni 12D-02.
