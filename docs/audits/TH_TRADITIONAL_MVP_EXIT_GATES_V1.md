# TH-TRADITIONAL-MVP-EXIT-GATES-V1

## Tablero de gates de salida del MVP clínico

**Proyecto:** Clínica del Dolor

**Fecha:** 2026-10-12
**Fuente:** revisión estática de los hallazgos TH-SEC y del cierre de Historia Clínica Tradicional.

Este tablero separa el cierre documental del cierre funcional y de seguridad. Ningún gate se considera cerrado por lint, inspección de código o capturas aisladas. Las pruebas dinámicas requieren un entorno sintético reproducible y no deben usar datos clínicos reales.

## Resumen

| Gate | Área | Estado | Motivo principal |
|---|---|---|---|
| G01 | Integridad clínica P01–P11 | NOT_VERIFIED | Falta evidencia dinámica integral reproducible en esta fase |
| G02 | Flujo paciente y navegación | NOT_VERIFIED | No se ejecutó navegador ni sesión sintética |
| G03 | Persistencia y no-op | NOT_VERIFIED | No se accedió a BD ni se ejecutaron POST/AJAX |
| G04 | Seguridad de imágenes clínicas | BLOCKED | Privacidad y autorización de archivos siguen abiertas |
| G05 | Área Doctor y continuidad operativa | NOT_VERIFIED | No se ejecutaron recorridos funcionales Doctor |
| G06 | Recetas y propiedad histórica | BLOCKED | No hay propiedad verificable para todos los recursos |
| G07 | PDF y descargas | BLOCKED | Autenticación global no demuestra autorización por recurso |
| G08 | COFEPRIS | BLOCKED | Propiedad de carpeta, folio y surtido no demostrada |
| G09 | Evidencia de entorno seguro | BLOCKED | No se puede abrir BD ni validar el entorno en esta fase |
| G10 | Observabilidad y manejo de errores | NOT_VERIFIED | No se ejecutaron consola, Network ni errores controlados |
| G11 | Piloto y preparación de operación | NOT_STARTED | Depende de gates técnicos y de decisiones clínicas |

## Fichas de aceptación

### G01 — Integridad clínica P01–P11

- **Alcance:** respuestas, dependencias, navegación entre módulos, finalización y restauración.
- **Implementado/evidencia:** existen trabajos previos documentados, pero no se reejecutaron aquí.
- **Estado:** `NOT_VERIFIED`.
- **Hallazgos abiertos:** efectos secundarios GET, contratos AJAX y módulos con validación pendiente.
- **Riesgo clínico:** pérdida, duplicación o avance incorrecto de respuestas.
- **Dependencias:** fixture sintético, sesión reproducible y matriz de casos.
- **Siguiente tarea:** ejecutar la matriz P01–P11 sin escrituras reales.
- **Criterio de aceptación:** cada módulo conserva respuestas, avanza sólo tras guardar y finaliza de forma idempotente.
- **Pruebas faltantes:** Sí/No, dependencias, regreso, doble clic, recarga, finalización y autorización cruzada.
- **Decisión requerida:** aprobación del alcance de fixtures y tratamiento de GET con efectos secundarios.

### G02 — Flujo paciente y navegación

- **Alcance:** login sintético, sidebar, modal inicial, avance, regreso, cierre de sesión y siguiente módulo.
- **Implementado/evidencia:** patrones visuales y correcciones de navegación existen en el worktree; no hay evidencia nueva ejecutada.
- **Estado:** `NOT_VERIFIED`.
- **Hallazgos abiertos:** responsive, audio audible y consistencia de modal no demostrados en esta fase.
- **Riesgo clínico:** paciente bloqueado, salto de módulo o salida accidental.
- **Dependencias:** navegador controlable y paciente sintético autorizado.
- **Siguiente tarea:** compuerta mínima de navegador y sesión sintética.
- **Criterio de aceptación:** recorrido reproducible en escritorio y móvil sin pérdida de contexto.
- **Pruebas faltantes:** viewports, teclado, modal, audio, consola y Network.
- **Decisión requerida:** definir si la comprobación audible requiere observador humano.

### G03 — Persistencia y no-op

- **Alcance:** INSERT/UPDATE legítimos, reingreso, ausencia de UPDATE redundante y finalización idempotente.
- **Implementado/evidencia:** no ejecutado por la restricción de cero BD.
- **Estado:** `NOT_VERIFIED`.
- **Hallazgos abiertos:** no se puede afirmar persistencia ni no-op con inspección estática.
- **Riesgo clínico:** duplicados, respuestas perdidas o escrituras repetidas.
- **Dependencias:** base sintética aislada, trazas y limpieza reversible.
- **Siguiente tarea:** prueba controlada con snapshots antes/después.
- **Criterio de aceptación:** una acción produce exactamente el cambio esperado y repetirla no duplica.
- **Pruebas faltantes:** POST/AJAX real, SQL observado y recuperación tras recarga.
- **Decisión requerida:** autorización explícita del entorno de prueba aislado.

### G04 — Seguridad de imágenes clínicas

- **Alcance:** mapas corporales, imágenes clínicas, acceso propio/cruzado, almacenamiento y descargas.
- **Implementado/evidencia:** existen hallazgos y trabajos previos, pero no se revalidaron dinámicamente.
- **Estado:** `BLOCKED`.
- **Hallazgos abiertos:** autorización de archivos y trazabilidad documental.
- **Riesgo clínico:** exposición de imágenes o asociación errónea con un paciente.
- **Dependencias:** propietario sintético verificable y pruebas de archivo.
- **Siguiente tarea:** cerrar el gate de privacidad y autorización de recursos.
- **Criterio de aceptación:** acceso propio permitido, cruzado rechazado y ningún recurso se enumera sin autorización.
- **Pruebas faltantes:** GET/descarga cruzada, nombres no adivinables y ausencia de filtración en errores.
- **Decisión requerida:** aprobación del mecanismo de almacenamiento y retención.

### G05 — Área Doctor y continuidad operativa

- **Alcance:** expediente, módulos, laboratorio, recetas, COFEPRIS y retorno al paciente.
- **Implementado/evidencia:** cierre visual documentado; la funcionalidad y autorización no se consideran probadas aquí.
- **Estado:** `NOT_VERIFIED`.
- **Hallazgos abiertos:** recetas externas, COFEPRIS, PDF y descargas.
- **Riesgo clínico:** lectura o modificación de información de otra clínica.
- **Dependencias:** mapa de propiedad y fixtures de dos clínicas.
- **Siguiente tarea:** recorrido Doctor sintético por recurso.
- **Criterio de aceptación:** cada pantalla y acción respeta la clínica autenticada y conserva navegación.
- **Pruebas faltantes:** GET/AJAX/POST cruzado, PDF, descarga y estados vacíos.
- **Decisión requerida:** tratamiento operativo de registros históricos sin propietario.

### G06 — Recetas y propiedad histórica

- **Alcance:** recetas internas, externas, detalle, edición, búsqueda, traslado y conciliación.
- **Implementado/evidencia:** D14 interno tiene mitigación previa documentada; la propiedad histórica externa no está demostrada.
- **Estado:** `BLOCKED`.
- **Hallazgos abiertos:** recursos externos sin propietario verificable y traslado histórico.
- **Riesgo clínico:** acceso entre clínicas, modificación no autorizada o pérdida de trazabilidad.
- **Dependencias:** decisión de arquitectura de propiedad histórica y datos sintéticos de dos clínicas.
- **Siguiente tarea:** S1 de propiedad; después resolver endpoints en S2.
- **Criterio de aceptación:** cada receta se autoriza por propiedad actual/histórica explícita, sin confiar en IDs del cliente.
- **Pruebas faltantes:** HTML, AJAX, detalle, edición, PDF, búsqueda y descarga cruzados.
- **Decisión requerida:** aprobar modelo de propietario, traslado y conciliación.

### G07 — PDF y descargas

- **Alcance:** generación de PDF, descarga genérica y archivos clínicos.
- **Implementado/evidencia:** el router comprueba sesión/permisos generales; no se demostró autorización por recurso.
- **Estado:** `BLOCKED`.
- **Hallazgos abiertos:** posible acceso por identificador o ruta sin resolución de propietario.
- **Riesgo clínico:** exfiltración de documentos.
- **Dependencias:** autorización por recurso y pruebas de contenido/headers.
- **Siguiente tarea:** inventario de recursos y política fail-closed aprobada.
- **Criterio de aceptación:** acceso propio 200, cruzado 403/404 indistinguible y sin filtración de metadatos.
- **Pruebas faltantes:** PDF interno/externo/COFEPRIS, descarga por ruta y errores.
- **Decisión requerida:** aprobar bloqueo temporal de recursos no conciliados.

### G08 — COFEPRIS

- **Alcance:** folios, carpetas, surtido, detalle, búsqueda, PDF y relación con paciente/clínica.
- **Implementado/evidencia:** algunas rutas pasan por `assertPatientAccess()`; la cobertura de carpeta/folio/surtido no está demostrada.
- **Estado:** `BLOCKED`.
- **Hallazgos abiertos:** propiedad del folio y autorización de acciones documentales.
- **Riesgo clínico:** exposición de información regulatoria/documental entre clínicas.
- **Dependencias:** mapa de ownership, contrato de carpetas y fixtures sintéticos.
- **Siguiente tarea:** matriz por folio/carpeta/acción y pruebas cruzadas.
- **Criterio de aceptación:** toda lectura/escritura resuelve propietario antes de consultar o mutar.
- **Pruebas faltantes:** GET, AJAX, POST surtido, PDF y búsqueda cruzada.
- **Decisión requerida:** confirmar comportamiento para registros sin propietario.

### G09 — Evidencia de entorno seguro

- **Alcance:** base sintética, allowlist, ausencia de producción, aislamiento y limpieza.
- **Implementado/evidencia:** el presente trabajo no abrió conexiones ni comprobó la base.
- **Estado:** `BLOCKED`.
- **Hallazgos abiertos:** no existe evidencia nueva de que una futura ejecución sea aislada.
- **Riesgo clínico:** escrituras o lecturas en base real.
- **Dependencias:** configuración de prueba verificable y guardas fail-closed.
- **Siguiente tarea:** preflight sin consultas clínicas y después prueba de conexión permitida.
- **Criterio de aceptación:** el proceso se detiene si la base no está en allowlist sintética.
- **Pruebas faltantes:** nombre de base, host, usuario, guardas y limpieza.
- **Decisión requerida:** autorización/configuración del entorno sintético.

### G10 — Observabilidad y manejo de errores

- **Alcance:** errores de red, mensajes al paciente, consola, Network, logs y trazabilidad.
- **Implementado/evidencia:** hay correcciones previas referenciadas, pero no se probaron en navegador.
- **Estado:** `NOT_VERIFIED`.
- **Hallazgos abiertos:** cobertura real de mensajes seguros y ausencia de exposición técnica.
- **Riesgo clínico:** acciones ambiguas, reintentos duplicados o exposición de detalles internos.
- **Dependencias:** navegador y entorno sintético.
- **Siguiente tarea:** provocar fallos controlados y verificar UI/logs.
- **Criterio de aceptación:** el usuario recibe mensaje accionable sin SQL/PDO/rutas internas.
- **Pruebas faltantes:** NetworkError, timeout, 403, 422, 500 y reintento.
- **Decisión requerida:** formato de logging y retención para soporte.

### G11 — Piloto y preparación de operación

- **Alcance:** recorrido de demostración, checklist, responsables, reversión y decisión de go/no-go.
- **Implementado/evidencia:** no iniciado; la seguridad P1 y la evidencia dinámica son prerequisitos.
- **Estado:** `NOT_STARTED`.
- **Hallazgos abiertos:** gates G04, G06, G07, G08 y G09 bloquean el piloto clínico.
- **Riesgo clínico:** presentar un sistema no autorizado como operativo.
- **Dependencias:** gates técnicos cerrados y aprobación responsable.
- **Siguiente tarea:** preparar piloto únicamente después del cierre de seguridad.
- **Criterio de aceptación:** recorrido sintético reproducible, limitaciones visibles y decisión formal no productiva.
- **Pruebas faltantes:** ensayo completo, evidencia de viewport, consola, Network y plan de contingencia.
- **Decisión requerida:** aprobación explícita del alcance del piloto y del mensaje “no producción”.

## Orden de ejecución recomendado

1. **S1 — Evidencia sintética y ownership:** verificar entorno, fixtures, identidad y propietario por recurso sin tocar producción.
2. **S2 — Remediación de endpoints:** aplicar autorización por recurso sólo después de aprobar el modelo y sus contratos.
3. **F1 — Pruebas funcionales:** ejecutar recorridos propios/cruzados, no-op, errores y limpieza.
4. **F2 — Documentos y descargas:** validar PDF/descarga con contenido sintético y autorización por recurso.
5. **V1 — Evidencia visual y responsive:** navegador reproducible, consola, Network y capturas.
6. **R1 — Revisión de salida:** actualizar gates con evidencia, riesgos residuales y decisión go/no-go.

## Criterio de salida global

El MVP tradicional **NO está listo para producción**. Para considerar la salida se requiere que no queden gates `BLOCKED`, que los `NOT_VERIFIED` tengan evidencia reproducible o una decisión formal de exclusión, y que la aprobación clínica/operativa sea explícita. La entrevista conversacional, los documentos clínicos y las descargas no se consideran cubiertos por este tablero sólo por existir código o fixtures.

## Prohibiciones respetadas en esta fase

- No se abrió ninguna base de datos.
- No se ejecutó PDO, SQL, HTTP, navegador ni Docker.
- No se modificó `.env`, esquema, migración, datos, rutas ni código de aplicación.
- No se realizaron commits, pushes ni despliegues.
- No se usaron datos clínicos reales.

