# Catálogo de datos KPI Doctor — V1

Fecha: **2026-10-10**
Pantalla: **D41 — Centro de indicadores clínicos**
Ruta: `GET /clinica/indicadores`

Este catálogo se construyó mediante inspección de modelos, controladores,
`tests/fixtures/security-fixtures.sql` y consultas existentes. No se ejecutó
contra la base configurada porque `.env` apunta a `bd_clinica` y no se demostró
que sea una base sintética aislada.

## Alcance de autorización

- El controlador obtiene `id_clinica` exclusivamente de `AuthMiddleware::authPermisos()`.
- D41 no acepta `id_clinica` por GET, POST ni parámetros de ruta.
- Cada dominio clínico se relaciona con `pc_paciente` y filtra por `p.id_clinica`.
- Las consultas nuevas usan PDO parametrizado y agregaciones SQL; no cargan expedientes individuales.
- Los errores de lectura muestran un estado controlado y no se convierten en ceros.
- No se exponen nombres, diagnósticos, respuestas clínicas, tokens ni datos personales.

## Fuentes medidas

| Dominio | Tabla/modelo | Unidad | Fecha utilizada | Indicadores | Confianza |
|---|---|---|---|---|---|
| Pacientes | `pc_paciente` / `ClinicaModel` | Paciente autorizado | `fecha_alta` para actividad; estado actual sin fecha | Total actual, altas por periodo, pacientes por año | Alta |
| Historia Clínica | `ClinicaModel::getDashboardSummary()` y `PacienteModulosModelo` | Promedio de avance canónico por paciente | Estado actual; no se presenta como histórico | Promedio global de los nueve módulos | Alta para promedio actual |
| Notas | `nota_subsecuente` | Registro y pacientes distintos | `fecha_hora` | Total por periodo, pacientes con nota, promedio por paciente | Alta |
| Laboratorios | `laboratorio` | Registro y pacientes distintos | `fecha_hora` | Total por periodo, pacientes con laboratorio | Alta para conteo; no separa solicitud/resultado |
| Recetas internas | `receta_medica` | Receta y pacientes distintos | `fecha_hora` | Total por periodo, pacientes con receta | Alta |
| COFEPRIS | `cofepris` | Registro y pacientes distintos | `fecha_hora` | Total por periodo, pacientes relacionados | Alta |
| Finalización formal de módulos | `historia_clinica_modulos` + `pac_historia_clinica_finalizar` | Finalización registrada y pacientes distintos | `pac_historia_clinica_finalizar.fecha_hora` | Finalizaciones por módulo | Alta para el evento formal; no equivale por sí solo a completitud clínica |

## Definiciones y fórmulas

- **Pacientes actuales:** `COUNT(*)` de `pc_paciente` con `id_clinica` de la sesión.
- **Altas del periodo:** pacientes cuyo `fecha_alta` está dentro de `[inicio, fin)` del año seleccionado.
- **Avance promedio HC:** valor canónico ya calculado por `ClinicaModel::getDashboardSummary()`, que promedia los nueve módulos con la semántica existente. D41 no lo recalcula.
- **Registro por periodo:** `COUNT(*)` de la tabla de dominio después de unir con `pc_paciente` y filtrar la clínica.
- **Pacientes con registro:** `COUNT(DISTINCT p.id)` con el mismo alcance.
- **Promedio de notas por paciente:** total de notas del periodo dividido entre todos los pacientes autorizados actuales de la clínica; se muestra `—` cuando el denominador es cero.
- **Actividad mensual:** meses 1–12 del año seleccionado, con límites inclusivo/exclusivo. Para “Todos los años” se agrupa por año; cada dominio se visualiza con su propia escala para no comparar unidades de forma engañosa.
- **Fecha:** se utiliza el campo almacenado por cada fuente; no se interpreta automáticamente como fecha del acto clínico.
- **Fecha inválida:** valores nulos, vacíos, cero o fuera de 1900–2200 no se agrupan como año `0`; se contabilizan por separado como `Sin fecha válida` y también aparecen en el panel de calidad.

## Fuentes excluidas

| Fuente | Estado | Motivo |
|---|---|---|
| `receta_medica_externos` | NO APTA PARA EXPOSICIÓN CLÍNICA | No contiene `id_paciente`, `id_clinica` ni propietario verificable. |
| `cofepris_folio` | NO APTA PARA EXPOSICIÓN CLÍNICA | No contiene relación verificable con paciente o clínica. |
| Estados iniciada/parcial/completa por módulo | REQUIERE DEFINICIÓN | El cálculo canónico existe para el promedio, pero no existe un agregador independiente seguro para clasificar cada paciente sin duplicar reglas clínicas. |
| Procedimientos y tratamientos del módulo 8 | REQUIERE DEFINICIÓN | Hay tablas relacionadas con pacientes, pero sus unidades y fechas no son equivalentes; se requiere una definición clínica específica antes de agregarlas. |
| Demografía agregada | REQUIERE DEFINICIÓN | Los campos existen, pero su exposición requiere reglas de minimización y umbral para grupos pequeños. |
| Consultas, citas, alertas y estados de revisión | NO VERIFICADAS | No se identificó una fuente verificable en el esquema revisado. |

## Calidad y limitaciones

D41 cuenta registros sin fecha válida en las cinco fuentes medidas. Un cero en
esa sección significa que la consulta no encontró registros inválidos en la
fuente autorizada; no es un valor sustituto de una consulta fallida.

La pantalla distingue estado actual de actividad del periodo. No reconstruye
historial de transiciones de Historia Clínica ni presenta una fotografía actual
como si fuera completitud histórica.

Las vulnerabilidades documentales de ACT-05C-02, recetas externas, folios
COFEPRIS, SQL heredado, escrituras durante GET y autorización por registro
continúan abiertas y no quedan resueltas por este catálogo.
