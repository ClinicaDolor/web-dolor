# 24 — Registro de decisiones del propietario V1.3

Ninguna decisión de esta lista se selecciona unilateralmente. Cada una es una compuerta antes del lote que pueda cambiar acceso a datos existentes.

| ID | Problema | Opciones viables | Recomendación | Compatibilidad histórica | Consecuencia de no decidir | Estado |
|---|---|---|---|---|---|---|
| OD-01 | Propiedad de recetas externas | A) owner clínico explícito; B) tabla de transferencia/historial; C) bloqueo total de filas sin owner. | A+B para nuevos y transferidos; C como fail-closed transitorio. | Requiere mapear folios antiguos; no asumir clínica por fecha. | SEC-DOC-002 y búsquedas externas permanecen abiertas. | PENDIENTE |
| OD-02 | PDF externo | A) sólo owner verificable; B) PDF global aprobado por política; C) deshabilitar. | A; si no es resoluble, C. | No expone documentos históricos sin vínculo probado. | SEC-DOC-001 no puede cerrarse. | PENDIENTE |
| OD-03 | Transferencia entre clínicas | A) conserva owner histórico; B) transfiere owner con auditoría; C) ambos con alcance temporal. | B con auditoría y fecha efectiva, sin reescribir historia. | Permite explicar registros previos y actuales. | Las pruebas A/B pueden ser ambiguas. | PENDIENTE |
| OD-04 | Descargas y archivos | A) ownership por documento; B) storage global con ACL; C) bloqueo de tipos/rutas no mapeados. | A+B y C para legacy no mapeado. | Mantiene sólo archivos vinculados. | SEC-FILE-003 y ACT-05C-02 siguen bloqueando producción. | PENDIENTE |
| OD-05 | Roles Doctor/Asistente/Admin | A) permisos por rol y clínica; B) sólo clínica; C) excepción auditada. | A, con deny-by-default y trazabilidad. | Requiere mapear operaciones históricas. | Matriz SEC-AUTH-007 incompleta. | PENDIENTE |
| OD-06 | Registros sin propietario | A) migrar con evidencia; B) cuarentena; C) eliminar. | B hasta conciliación; nunca inferir owner sólo por ID. | No destruye información histórica. | Catálogos/PDF externos deben seguir fail-closed. | PENDIENTE |
| OD-07 | Cambios de esquema/migraciones | A) migración aprobada; B) tabla de ownership separada; C) sólo aplicación. | B como puente si el esquema clínico no puede cambiarse aún; formalizar A después. | Reduce impacto en tablas legacy. | No existe vínculo verificable para cierre. | PENDIENTE |
| OD-08 | Contención temporal | A) ocultar UI; B) bloqueo server-side; C) sólo advertencia. | B; UI informativa sin datos. | Conserva módulos internos no afectados. | CSS/JS no protegerían el dato. | PENDIENTE |
| OD-09 | GET con preparación/escritura | A) GET estrictamente lectura; B) preparación idempotente explícita; C) POST separado. | C cuando exista mutación clínica; B sólo con aceptación clínica. | Evita nuevas siembras durante navegación. | CLIN-GET-006 no puede cerrarse. | PENDIENTE |
| OD-10 | IA, consentimiento y retención | A) proveedor externo minimizado; B) proveedor local/mock; C) deshabilitado hasta política. | A sólo con consentimiento, minimización, retención y fail-closed verificables; C mientras falten. | No se envían datos reales durante pruebas. | AI-PRIV-008 permanece abierto. | PENDIENTE |

## Aprobación necesaria

El propietario debe responder por cada ID con una opción y, cuando aplique, el alcance temporal para registros históricos. La aprobación de un entorno de prueba no equivale a aprobación de una política clínica ni de producción.
