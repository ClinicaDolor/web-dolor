# Cierre de entrega — demostración clínica sintética

Fecha de verificación: **2026-10-10**. Este documento prepara una demostración
local reproducible; **no declara preparación para producción ni operación con
información clínica real**.

## Resumen ejecutivo

El recorrido Doctor → Expediente → Historia Clínica → Laboratorio → cierre de
sesión → Paciente → Dashboard → módulo clínico se ejecutó en un entorno
sintético aislado. Los accesos, las sesiones, la navegación de lectura, el
dropdown de Historia Clínica, el catálogo de nueve módulos y la continuación a
un módulo paciente fueron comprobados.

La base usada fue `clinica_dolor_demo_20261010_ui03c`. La consulta de control
confirmó: 2 clínicas, 2 médicos, 2 pacientes, 9 módulos y 2 accesos de
paciente. `tests/security/isolated-router.php` fuerza esta base mediante
`CLINICA_AUDIT_DB`/`DB_DATABASE`; no se conectó la demostración a
`bd_clinica`. No se usaron datos personales reales ni se ejecutaron
operaciones clínicas irreversibles.

## Entorno y cuentas sintéticas

| Elemento | Identificador usado | Estado |
|---|---|---|
| Médico | `doctor-a-sintetico` | PASS; clínica sintética 9101 |
| Paciente | expediente `9301` | PASS; clínica sintética 9101 |
| Segundo conjunto de aislamiento | clínica 9102, médico 9202, paciente 9302 | Presente en fixture; no usado en el recorrido final |
| Catálogo clínico | 9 módulos reales del fixture | PASS |

Las contraseñas y PIN no se incluyen en documentación ni en el informe.

## Recorrido recomendado y resultados

| Paso | Pantalla o acción | Resultado | Evidencia comprobada |
|---:|---|---|---|
| 1 | `/clinica/acceso` — login Doctor | PASS | Autenticación sintética y redirección a `/clinica` |
| 2 | `/clinica` — Dashboard Doctor | PASS | Encabezado, KPI sintético, accesos y shell médico |
| 3 | `/clinica/pacientes` | PASS | Listado y navegación disponibles en modo lectura |
| 4 | `/clinica/paciente/9301` | PASS | Expediente renderizado; 9 filas de Historia Clínica |
| 5 | Acciones de Historia Clínica | PASS | Apertura, `aria-expanded=true`, Escape y cierre |
| 6 | `/clinica/laboratorio/paciente/9301` | PASS | Encabezado y vista de Laboratorio disponibles |
| 7 | `/cerrar-sesion` | PASS | Sesión Doctor cerrada; regreso al acceso |
| 8 | `/historia-clinica/acceso` — login Paciente | PASS | Acceso sintético y redirección a `/historia-clinica` |
| 9 | `/historia-clinica` — Dashboard Paciente | PASS | 9 tarjetas, estados y porcentajes del fixture |
| 10 | Continuación de módulo | PASS | Llegada a `antecedentes-personales-no-patologicos` del paciente 9301 |

La consola del navegador terminó sin mensajes de nivel `error` durante la
verificación final.

## Responsive y capturas

Se comprobaron dimensiones reales mediante `window.innerWidth` y
`window.innerHeight`, con el estado del menú estabilizado antes de medir:

| Superficie | 1440×900 | 1280×720 | 768×1024 | 375×667 |
|---|---|---|---|---|
| Dashboard Doctor | PASS, sin overflow | PASS, sin overflow | PASS, sin overflow | PASS, sin overflow |
| Dashboard Paciente | PASS, 9 módulos | PASS, 9 módulos | PASS, 9 módulos | PASS, 9 módulos |

Se generaron capturas reales de ambos dashboards en escritorio (1440×900) y
móvil (375×667), con las sesiones sintéticas activas, y se presentaron durante
esta ejecución. Las comprobaciones de 1280×720 y 768×1024 se ejecutaron con
viewport real; no se declaró PASS usando un override no aplicado.

## Funciones demostradas

- Autenticación y cierre de sesión de ambos portales.
- Navegación Doctor, listado de pacientes, expediente e Historia Clínica.
- Apertura y cierre del menú de acciones de Historia Clínica.
- Consulta de Laboratorio en modo lectura.
- Dashboard Paciente y catálogo completo de nueve módulos.
- Continuación a un módulo clínico existente.
- Responsive y ausencia de overflow horizontal en las dos superficies.

## Funciones no verificadas en este cierre

No se ejecutaron guardados, ediciones, finalización de módulos, alta de
pacientes, generación o renovación de PIN, carga de archivos, generación de
PDF, descargas ni operaciones clínicas irreversibles. Tampoco se repitió en
este recorrido la matriz completa de autorización cruzada entre clínicas;
debe consultarse `docs/security-backlog.md` y `docs/testing.md` para esa
línea base.

Las rutas `/clinica/recetas` y `/clinica/cofepris` quedaron verificadas en
modo lectura durante UI-03; su detalle se conserva en
`docs/ui03-report.md`.

## Riesgos y limitaciones pendientes

Las vulnerabilidades críticas de ACT-05C-02 permanecen abiertas y deben seguir
bloqueando cualquier publicación con información clínica real:

- documentos clínicos descargables mediante URLs físicas bajo almacenamiento
  público;
- accesos cruzados confirmados en descargas de laboratorio, receta PDF y
  COFEPRIS PDF;
- comportamiento `500` para ciertos documentos inexistentes, pendiente de
  manejo controlado.

No se ocultaron ni corrigieron artificialmente esos riesgos en este cierre.
La demostración debe ejecutarse sólo en el entorno sintético local y no debe
exponerse públicamente.

## Acciones posteriores al lunes

1. Implementar y validar la autorización documental por recurso, paciente y
   clínica antes de usar datos reales.
2. Repetir la matriz de autorización cruzada y pruebas de traversal sin tocar
   almacenamiento productivo.
3. Verificar operaciones de escritura con fixtures desechables y criterios de
   aceptación explícitos.
4. Resolver los pendientes P2 visuales documentados en
   `docs/ui03-report.md` sólo después de cerrar los riesgos de seguridad.

## Fuentes verificables

- `tests/security/isolated-router.php`
- `tests/fixtures/security-fixtures.sql`
- `docs/ui03-report.md`
- `docs/security-backlog.md`
- `docs/testing.md`
