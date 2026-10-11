# Informe de implementación KPI Doctor — V1

Fecha: **2026-10-10**
Pantalla nueva: **D41 — Centro de indicadores clínicos**
Ruta: `GET /clinica/indicadores`
Estado visual: **VALIDACIÓN**

## Resultado

Se implementó una vista independiente dentro del shell Doctor. Complementa el
Dashboard `/clinica`; no reemplaza ni rediseña su resumen de seis métricas.

Se implementaron sólo indicadores con fuente, unidad de conteo, fecha y
relación clínica demostrables:

- Pacientes autorizados y altas por año/periodo.
- Avance promedio actual de Historia Clínica usando el cálculo canónico existente.
- Notas subsecuentes por periodo y pacientes con notas.
- Laboratorios por periodo y pacientes con laboratorio.
- Recetas internas por periodo y pacientes relacionados.
- Registros COFEPRIS ligados a pacientes por periodo.
- Finalizaciones formales registradas por módulo.
- Registros sin fecha válida por fuente.

## Arquitectura

```text
AuthMiddleware::authPermisos()
        ↓
IndicadoresController
        ↓
ClinicaIndicadoresService + ClinicaModel (promedio HC canónico)
        ↓
consultas PDO agregadas y parametrizadas
        ↓
app/views/clinica/indicadores.php
        ↓
clinica-indicadores.js (pestañas y gráficos SVG locales)
```

No se añadió una librería de gráficos porque el repositorio no contiene una
dependencia local para esa finalidad. Los gráficos se renderizan como SVG
local, con escalado matemático, ejes, valores, tooltips accesibles y tablas
alternativas. La actividad clínica se separa en cuatro mini-gráficas con
escala propia para conservar las unidades reales cuando las magnitudes son
dispares.

## Archivos modificados o creados

- `app/Config/routes.php`: ruta GET de D41.
- `app/Controllers/IndicadoresController.php`: autenticación, filtro de año y estado de error.
- `app/Services/ClinicaIndicadoresService.php`: agregaciones de sólo lectura.
- `app/Controllers/SidebarController.php`: opción Doctor «Indicadores» en D02 y D41.
- `app/views/clinica/indicadores.php`: vista D41 y pestañas.
- `public/assets/js/clinica-indicadores.js`: interacción de pestañas y gráficos.
- `public/assets/css/app.css`: bloque scoped `body.doctor-kpi-page`.
- `tests/fixtures/doctor-kpi-chart-fixtures.json`: fixture sintético de escalas y fechas inválidas.
- `tests/unit/clinica-indicadores.test.js`: prueba pura de transformación y escala.
- `app/views/clinica/index.php`: único enlace «Ver indicadores completos».
- `docs/UI_DOCTOR_KPI_DATA_CATALOG_V1.md`: catálogo y gobernanza de datos.
- `docs/UI_DESIGN_CLOSURE_MASTER.md`: entrada D41 y contadores.

No se modificaron modelos clínicos, esquemas, migraciones, persistencia ni
vistas D03–D40.

## Seguridad y rendimiento

- El alcance de clínica proviene de la identidad JWT autenticada.
- No se admite un `id_clinica` arbitrario.
- Recetas externas y `cofepris_folio` quedan excluidos.
- Las consultas usan `COUNT`, `COUNT(DISTINCT)`, `GROUP BY` y límites de periodo; no hay N+1 ni expedientes completos en memoria.
- Un fallo de consulta produce un mensaje controlado y no ceros silenciosos.

## Validación

| Prueba | Resultado |
|---|---|
| PHP lint de servicio, controlador, rutas y vistas | PASS |
| Sintaxis de `clinica-indicadores.js` | PASS |
| Fixture puro de escala SVG (`1, 2, 12, 60, 129`) y fechas inválidas | PASS |
| Balance de llaves CSS | PASS |
| Contratos de seis métricas D02 conservados | PASS estático |
| Fuente y filtro de clínica | PASS estático |
| Base sintética, conteos, años y aislamiento cruzado | NO VERIFICADO |
| Capturas y viewports 1440/1280/768/375 | NO VERIFICADO |
| Consola JavaScript y navegación autenticada | NO VERIFICADO |
| `git diff --check` | FAIL histórico en `public/assets/css/app.css`: líneas 4138, 4139, 4141, 4166 y 4224; fuera del bloque D41 y no corregido para evitar alterar cambios ajenos |

## UI-FIX-D41-01 — Diagnóstico y corrección de gráficas

### Diagnóstico demostrado

1. Las gráficas originales asignaban `height: N%` a cada barra, pero el grupo
   tenía `height: 100%` dentro de un contenedor con sólo `min-height`. Al no
   existir una altura resoluble para el porcentaje, el navegador calculaba el
   tamaño contra un contexto indefinido y las barras quedaban en su mínimo de
   3 px. Esto explica los contenedores blancos con pequeñas líneas observados
   en la captura.
2. La escala numérica del JavaScript sí convertía los valores con `Number`,
   pero no podía corregir el problema de layout CSS ni proporcionar ejes,
   ticks o una relación visual verificable.
3. Las agrupaciones SQL de años utilizaban `YEAR(fecha)` sin separar fechas
   nulas, cero o fuera del rango válido. `getPatientsByYear()` podía convertir
   ese grupo a la etiqueta `0`; las series de actividad tenían el mismo riesgo.
4. Las pestañas no asociaban completamente sus IDs de control con
   `aria-labelledby`, y los gráficos no se redimensionaban de forma fiable al
   cambiar de panel.

### Correcciones aplicadas

- Se sustituyeron las barras dependientes de altura porcentual CSS por SVG con
  `viewBox`, ejes desde cero, cuatro líneas guía, valores numéricos y escala
  proporcional al máximo real de cada serie.
- Pacientes por año conserva años válidos ordenados y agrega `Sin fecha válida`
  cuando existen registros fuera del rango temporal verificable.
- La actividad clínica se presenta en cuatro gráficos separados —notas,
  laboratorios, recetas internas y COFEPRIS— cada uno con su unidad y escala
  real, evitando que una magnitud grande oculte las restantes.
- Se excluyen fechas inválidas de las agrupaciones temporales y se contabilizan
  por separado en el panel de calidad; no se generan años ficticios.
- Se corrigieron IDs, `aria-labelledby`, `aria-hidden` y el foco roving de las
  cinco pestañas.
- El hero se alineó con el patrón visual del Dashboard Doctor y los siete KPI
  se reorganizaron en una cuadrícula adaptable de cuatro, tres, dos o una
  columna según el ancho disponible.

### Gráficas inventariadas

| ID | Pestaña | Fuente | Visualización | Unidad | Estado/corrección |
|---|---|---|---|---|---|
| `kpi-patients-chart` | Panorama | `pc_paciente.fecha_alta` | Barras SVG verticales | Pacientes | Corregida; escala desde cero y fechas inválidas separadas |
| `kpi-activity-chart` | Panorama | `nota_subsecuente`, `laboratorio`, `receta_medica`, `cofepris` | Cuatro mini-gráficas SVG verticales | Registros por periodo | Corregida; una escala independiente por fuente |
| Historia Clínica | Historia Clínica | `ClinicaModel::getDashboardSummary()` y finalizaciones formales | KPI y tabla | Porcentaje canónico / eventos formales | Sin cambio de cálculo clínico |
| Atención clínica | Atención clínica | Métricas agregadas del servicio | Tabla | Registros y pacientes | Sin gráfica adicional existente |
| Recetas y documentos | Recetas y documentos | Recetas internas y COFEPRIS ligados a paciente | Tabla | Registros y pacientes | Sin gráfica adicional existente |
| Cobertura y calidad | Cobertura y calidad | Conteos de fechas inválidas y exclusiones | Tarjetas informativas | Registros sin fecha | Sin gráfica adicional existente |

### Pruebas sintéticas añadidas

`tests/unit/clinica-indicadores.test.js` verifica sin conexión a la base clínica
que los valores `1, 2, 12, 60, 129` producen alturas estrictamente crecientes,
que `129` ocupa la altura máxima, que los ceros no generan altura positiva y que
la etiqueta de fecha inválida no se convierte en `0`.

Las pruebas de navegador, consola, viewports y consultas contra una base
sintética siguen siendo **NO VERIFICADAS** hasta disponer de ese entorno.

`.env` apunta a `bd_clinica`; no se ejecutaron consultas de integración ni
escrituras contra esa base. Tampoco se inventaron capturas ni resultados de
aislamiento.

## UI-FIX-D41-02 — Iconos, colores y gráfico de avance HC

### Alcance implementado

Se ajustó exclusivamente la presentación de las siete tarjetas KPI en
`app/views/clinica/indicadores.php` y el bloque scoped de
`body.doctor-kpi-page` en `public/assets/css/app.css`. El controlador, el
servicio, las consultas, los valores, denominadores, periodos y contratos de
persistencia no fueron modificados.

Cada tarjeta conserva su métrica real y ahora incluye un icono Feather
decorativo (`aria-hidden="true"`) dentro de un contenedor compacto con el
acento visual solicitado. El catálogo local no contiene `flask`, `pill` ni
`clipboard-list`; por ello se utilizaron equivalentes disponibles y
semánticamente cercanos: `package` para Laboratorios, `file-plus` para Recetas
internas y `clipboard` para COFEPRIS. No se añadió ninguna dependencia.

La tarjeta «Avance promedio HC» muestra un anillo SVG compacto calculado en la
vista a partir de `$current['promedio_historia_clinica']`, que ya proviene del
cálculo canónico del backend. Sólo se limita el arco visual al rango 0–100;
el texto conserva el valor numérico recibido y un valor nulo, vacío o no
numérico continúa mostrándose como `—`, sin convertirse en 0%. El anillo es decorativo y el contenedor conserva
un nombre accesible con el estado textual.

La cuadrícula mantiene exactamente siete métricas, con cuatro columnas en
escritorio amplio, tres en anchos intermedios, dos en tableta y una en móvil.
Las tarjetas no usan altura fija; el padding y la tipografía fueron
compactados sin recortar etiquetas ni controles.

### Validación específica

| Prueba | Resultado |
|---|---|
| Catálogo Feather local para `users`, `activity`, `user-plus`, `file-text`, `package`, `file-plus` y `clipboard` | PASS |
| Exactamente siete tarjetas KPI y conservación de los textos/valores existentes | PASS estático |
| Valores puros del anillo: `0`, `25`, `67.55`, `100` y no disponible | PASS (`tests/unit/clinica-kpi-ring.test.php`) |
| Anillo no disponible no convierte el valor a 0% | PASS estático y puro |
| PHP lint de la vista | PASS |
| Sintaxis de `public/assets/js/clinica-indicadores.js` | PASS; no fue necesario modificarlo |
| Balance de llaves del bloque CSS D41 | PASS |
| Viewports reales, capturas, consola y navegación autenticada | NO VERIFICADO: no se dispone de una sesión/base sintética conectada |
| `git diff --check` | FAIL histórico en `public/assets/css/app.css` (líneas 4138, 4139, 4141, 4166 y 4224) y `app/Controllers/PdfController.php` (líneas 18, 28, 43–45 y 58–59); fuera del alcance y no corregido |

## UI-FIX-D41-03 — Gráficas de ancho completo en una sola columna

### Reorganización aplicada

Se modificaron exclusivamente la estructura visual de Panorama en
`app/views/clinica/indicadores.php`, los selectores scoped de D41 y la
presentación SVG de `public/assets/js/clinica-indicadores.js`.

- `doctor-kpi-chart-grid` fue sustituido por `doctor-kpi-chart-stack` con una
  sola columna.
- La tarjeta de pacientes conserva su propia tarjeta de ancho completo.
- Actividad clínica dejó de ser una tarjeta exterior que contiene cuatro
  minicards: ahora es una sección semántica con cuatro figuras independientes
  apiladas en `doctor-kpi-series-stack`.
- El orden visual queda: pacientes, notas, laboratorios, recetas internas y
  COFEPRIS.
- Las gráficas SVG ocupan el ancho útil de su tarjeta, con altura adaptable de
  260–320 px y sin `height: 100%`.
- Los estados vacíos de actividad utilizan una región compacta de 96 px y no
  generan una tarjeta alta sin datos.
- Se conservaron títulos, unidades, ejes, valores, leyendas SVG, controles
  «Ver datos tabulares», orden temporal y estados `Sin fecha válida`.
- Las series de actividad usan los acentos visuales ya definidos: notas
  `#B45309`, laboratorios `#0891B2`, recetas `#C24155` y COFEPRIS `#4F46E5`.
  El color de `Sin fecha válida` se mantiene neutro `#64748B`.

No se modificaron las siete tarjetas KPI superiores, el anillo de avance HC,
los cálculos, servicios, controladores, modelos, rutas, permisos ni fuentes de
datos. El SVG utiliza `preserveAspectRatio="none"` y ancho CSS fluido, por lo
que se adapta al cargar, cambiar de pestaña y redimensionar sin recalcular
datos ni introducir fixtures.

### Resultado por pestaña

| Pestaña | Resultado |
|---|---|
| Panorama | PASS estático: una gráfica de pacientes y cuatro gráficas de actividad, cada una en fila completa |
| Historia Clínica | PASS de auditoría: no contiene gráficas adicionales; su tabla y KPI no fueron alterados |
| Atención clínica | PASS de auditoría: sólo contiene tabla, no se convirtió en tarjeta gráfica |
| Recetas y documentos | PASS de auditoría: sólo contiene tabla y nota de exclusión, sin cambios de datos |
| Cobertura y calidad | PASS de auditoría: conserva su cuadrícula de tarjetas informativas; no es una cuadrícula de gráficas |

### Validación UI-FIX-D41-03

| Prueba | Resultado |
|---|---|
| PHP lint de `indicadores.php` | PASS |
| Sintaxis de `clinica-indicadores.js` | PASS |
| Pruebas puras existentes de escalas y fechas | PASS |
| Contratos estáticos de una columna y clases de series | PASS |
| Colores de series y estados vacíos compactos | PASS estático |
| Consultas, cálculos, autorización y datos | PASS de alcance: no modificados |
| Render real, etiquetas, redimensionamiento, pestañas y consola | NO VERIFICADO: no hay sesión/base sintética conectada ni navegador reproducible |
| Viewports 1440×900, 1280×720, 768×1024 y 375×667 | NO VERIFICADO |
| `git diff --check` | FAIL histórico fuera del alcance en `app/Controllers/PdfController.php` y `public/assets/css/app.css`; el bloque D41 no contiene whitespace final |

## UI-FIX-D41-04 — Fondo blanco unificado de las gráficas

### Causa confirmada

La tarjeta de pacientes utiliza el tratamiento base de `.doctor-kpi-card`
(`var(--clinic-surface)`, `var(--clinic-radius)` y `var(--clinic-shadow)`). En
cambio, las figuras creadas para actividad usaban
`.doctor-kpi-series-chart` con `background: rgba(248, 245, 255, .45)`, radio
`12px`, padding menor y sin sombra; el estado vacío de actividad repetía ese
fondo semitransparente. Esa diferencia de selectores producía el fondo lavanda
y el borde visualmente más marcado.

### Corrección aplicada

Se modificó únicamente el CSS scoped `body.doctor-kpi-page`:

- `.doctor-kpi-chart-card` y `.doctor-kpi-series-chart` comparten ahora
  `#FFFFFF`, `var(--clinic-border)`, `var(--clinic-radius)`,
  `var(--clinic-shadow)` y `padding: 1rem`.
- `.doctor-kpi-series-stack.is-empty` conserva el estado compacto, pero ahora
  usa la misma tarjeta blanca, borde sólido, radio, sombra y padding.
- No se modificaron SVG, JavaScript, datos, colores de barras, tablas ni
  consultas.

La auditoría de las cinco pestañas confirmó que sólo Panorama contiene
gráficas; Historia Clínica, Atención clínica, Recetas y documentos y Cobertura
y calidad conservan sus tablas o tarjetas informativas sin convertirlas en
contenedores gráficos.

### Validación UI-FIX-D41-04

| Prueba | Resultado |
|---|---|
| PHP lint de la vista | PASS |
| Sintaxis JavaScript | PASS |
| Selectores scoped y balance CSS | PASS |
| Fondo sólido `#FFFFFF`, radio, borde, sombra y padding compartidos | PASS estático |
| Estado vacío compacto y blanco | PASS estático |
| Siete KPI superiores y consultas/cálculos intactos | PASS de alcance |
| Comparación visual real en 1440×900, 1280×720, 768×1024 y 375×667 | NO VERIFICADO: sin navegador/sesión sintética reproducible |
| Consola, pestañas y overflow real | NO VERIFICADO |
| `git diff --check` | FAIL histórico fuera del alcance en `app/Controllers/PdfController.php` y `public/assets/css/app.css`; el bloque D41 no contiene whitespace final |

## Riesgos abiertos

ACT-05C-02, PDF y descargas, almacenamiento público, recetas externas, folios
COFEPRIS, SQL heredado, escrituras durante GET, autorización por registro y
pruebas funcionales no ejecutadas permanecen abiertos. D41 no es una aprobación
de producción ni de seguridad clínica.
